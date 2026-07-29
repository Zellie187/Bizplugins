<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Recurring;

use BizHub\Bookkeeping\Contracts\RecurringOccurrenceRepositoryInterface;
use BizHub\Bookkeeping\Contracts\RecurringTemplateRepositoryInterface;
use BizHub\Bookkeeping\Contracts\RecurringTransactionServiceInterface;
use BizHub\Bookkeeping\Contracts\TransactionCaptureServiceInterface;
use BizHub\Bookkeeping\DTO\CaptureTransactionData;
use BizHub\Bookkeeping\Entities\JournalEntry;
use BizHub\Bookkeeping\Entities\RecurringOccurrence;
use BizHub\Bookkeeping\Entities\RecurringTemplate;
use BizHub\Bookkeeping\Enums\PaymentMethod;
use BizHub\Bookkeeping\Enums\RecurringFrequency;
use BizHub\Bookkeeping\Enums\RecurringOccurrenceStatus;
use BizHub\Bookkeeping\Enums\TransactionType;
use BizHub\Bookkeeping\Exceptions\ValidationException;
use BizHub\Bookkeeping\Support\Money;
use BizHub\ClientPortal\Contracts\ClientRepositoryInterface;
use BizHub\Companies\Contracts\CompanyServiceInterface;
use BizHub\Companies\Exceptions\CompanyNotFoundException;
use BizHub\Framework\Support\Uuid;
use BizHub\Notifications\Notification;
use BizHub\Notifications\NotificationQueue;
use DateTimeImmutable;
use Throwable;

/**
 * Recurring transaction templates and their cron-generated occurrences.
 * Confirming an occurrence always delegates to
 * TransactionCaptureServiceInterface - the same posting path Capture
 * and Bank Import already use - so this module never reinvents posting
 * logic and inherits the subscription gate, VAT support, and
 * balanced-entry guarantee for free.
 *
 * @package BizHub\Bookkeeping\Recurring
 */
final class RecurringTransactionService implements RecurringTransactionServiceInterface
{
    public function __construct(
        private readonly RecurringTemplateRepositoryInterface $templates,
        private readonly RecurringOccurrenceRepositoryInterface $occurrences,
        private readonly TransactionCaptureServiceInterface $capture,
        private readonly CompanyServiceInterface $companies,
        private readonly ClientRepositoryInterface $clients,
        private readonly NotificationQueue $notificationQueue
    ) {
    }

    public function createTemplate(
        string $companyUuid,
        TransactionType $transactionType,
        Money $amount,
        string $categoryAccountUuid,
        PaymentMethod $paymentMethod,
        string $description,
        bool $includesVat,
        RecurringFrequency $frequency,
        DateTimeImmutable $startDate
    ): RecurringTemplate {
        $now = new DateTimeImmutable();

        return $this->templates->save(new RecurringTemplate(
            uuid: Uuid::generate(),
            companyUuid: $companyUuid,
            transactionType: $transactionType,
            amount: $amount,
            categoryAccountUuid: $categoryAccountUuid,
            paymentMethod: $paymentMethod,
            description: $description,
            includesVat: $includesVat,
            frequency: $frequency,
            nextDueDate: $startDate,
            isActive: true,
            createdAt: $now,
        ));
    }

    public function updateTemplate(
        string $companyUuid,
        string $templateUuid,
        Money $amount,
        string $categoryAccountUuid,
        PaymentMethod $paymentMethod,
        string $description,
        bool $includesVat,
        RecurringFrequency $frequency
    ): RecurringTemplate {
        $template = $this->ownedTemplate($companyUuid, $templateUuid);

        return $this->templates->save($template->withDetails(
            $amount,
            $categoryAccountUuid,
            $paymentMethod,
            $description,
            $includesVat,
            $frequency,
            new DateTimeImmutable(),
        ));
    }

    public function pauseTemplate(string $companyUuid, string $templateUuid): void
    {
        $template = $this->ownedTemplate($companyUuid, $templateUuid);
        $this->templates->save($template->withActive(false, new DateTimeImmutable()));
    }

    public function resumeTemplate(string $companyUuid, string $templateUuid): void
    {
        $template = $this->ownedTemplate($companyUuid, $templateUuid);
        $this->templates->save($template->withActive(true, new DateTimeImmutable()));
    }

    public function deleteTemplate(string $companyUuid, string $templateUuid): void
    {
        $template = $this->ownedTemplate($companyUuid, $templateUuid);
        $this->templates->delete($template->uuid);
    }

    public function listTemplates(string $companyUuid): array
    {
        return $this->templates->findByCompanyUuid($companyUuid);
    }

    public function generateDueOccurrences(?DateTimeImmutable $asOf = null): int
    {
        $asOf ??= new DateTimeImmutable();
        $generated = 0;

        /** @var array<string,int> $generatedByCompany */
        $generatedByCompany = [];

        foreach ($this->templates->findDue($asOf) as $template) {
            try {
                if ($this->generateOccurrenceForTemplate($template)) {
                    $generated++;
                    $currentCount = $generatedByCompany[$template->companyUuid] ?? 0;
                    $generatedByCompany[$template->companyUuid] = $currentCount + 1;
                }
            } catch (Throwable) {
                // One template's failure must never block the rest of the batch.
                continue;
            }
        }

        foreach ($generatedByCompany as $companyUuid => $count) {
            $this->notifyReviewReady($companyUuid, $count);
        }

        if ($generated > 0) {
            $this->notificationQueue->processPending();
        }

        return $generated;
    }

    private function generateOccurrenceForTemplate(RecurringTemplate $template): bool
    {
        if ($this->occurrences->existsForTemplateAndDate($template->uuid, $template->nextDueDate)) {
            // Safety dedup - shouldn't happen since next_due_date always
            // advances past a date once generated, but a cron misfire
            // must never produce a duplicate occurrence.
            return false;
        }

        $this->occurrences->insert(new RecurringOccurrence(
            uuid: Uuid::generate(),
            templateUuid: $template->uuid,
            companyUuid: $template->companyUuid,
            dueDate: $template->nextDueDate,
            status: RecurringOccurrenceStatus::Pending,
            journalEntryUuid: null,
            generatedAt: new DateTimeImmutable(),
        ));

        $this->templates->save($template->withNextDueDate(
            $template->frequency->nextDate($template->nextDueDate),
            new DateTimeImmutable(),
        ));

        return true;
    }

    public function listPendingOccurrences(string $companyUuid): array
    {
        return $this->occurrences->findByCompanyUuid($companyUuid, RecurringOccurrenceStatus::Pending);
    }

    public function confirmOccurrence(
        string $companyUuid,
        string $occurrenceUuid,
        int $actorId,
        ?Money $overrideAmount = null,
        ?DateTimeImmutable $overrideDate = null
    ): JournalEntry {
        $occurrence = $this->ownedPendingOccurrence($companyUuid, $occurrenceUuid);
        $template = $this->requireTemplate($occurrence->templateUuid);

        $data = new CaptureTransactionData(
            date: $overrideDate ?? $occurrence->dueDate,
            amount: $overrideAmount ?? $template->amount,
            categoryAccountUuid: $template->categoryAccountUuid,
            paymentMethod: $template->paymentMethod,
            description: $template->description,
            includesVat: $template->includesVat,
        );

        $entry = $template->transactionType === TransactionType::Income
            ? $this->capture->captureIncome($companyUuid, $data, $actorId)
            : $this->capture->captureExpense($companyUuid, $data, $actorId);

        $this->occurrences->save($occurrence->withPosted($entry->uuid, new DateTimeImmutable()));

        return $entry;
    }

    public function skipOccurrence(string $companyUuid, string $occurrenceUuid): void
    {
        $occurrence = $this->ownedPendingOccurrence($companyUuid, $occurrenceUuid);

        $this->occurrences->save($occurrence->withSkipped(new DateTimeImmutable()));
    }

    private function requireTemplate(string $templateUuid): RecurringTemplate
    {
        return $this->templates->findByUuid($templateUuid)
            ?? throw new ValidationException(sprintf('No recurring template "%s" found.', $templateUuid));
    }

    /**
     * Same ownership-verification shape as ownedPendingOccurrence() -
     * update/pause/resume/delete all mutate a template by UUID alone,
     * so each must independently confirm it belongs to the calling
     * company before mutating it (this class has no other gate on
     * that).
     */
    private function ownedTemplate(string $companyUuid, string $templateUuid): RecurringTemplate
    {
        $template = $this->templates->findByUuid($templateUuid);

        if ($template === null || $template->companyUuid !== $companyUuid) {
            throw new ValidationException(sprintf(
                'No recurring template "%s" found for this company.',
                $templateUuid
            ));
        }

        return $template;
    }

    private function ownedPendingOccurrence(string $companyUuid, string $occurrenceUuid): RecurringOccurrence
    {
        $occurrence = $this->occurrences->findByUuid($occurrenceUuid);

        if ($occurrence === null || $occurrence->companyUuid !== $companyUuid) {
            throw new ValidationException(sprintf(
                'No pending recurring occurrence "%s" found for this company.',
                $occurrenceUuid
            ));
        }

        if ($occurrence->status !== RecurringOccurrenceStatus::Pending) {
            throw new ValidationException('This recurring occurrence has already been confirmed or skipped.');
        }

        return $occurrence;
    }

    private function notifyReviewReady(string $companyUuid, int $count): void
    {
        $wpUserId = $this->resolveWpUserId($companyUuid);

        if ($wpUserId === null) {
            return;
        }

        /** @var array{subject:string,body:string} $template */
        $template = require BIZUPKEEP_BOOKKEEPING_CONFIG_PATH . 'recurring-review.php';

        $company = $this->companies->getCompany($companyUuid);

        $replacements = [
            '{count}' => (string) $count,
            '{company_name}' => $company->getCompanyName(),
            '{review_url}' => home_url('/client-portal/client-portal-bookkeeping/?tab=recurring'),
        ];

        $this->notificationQueue->enqueue(new Notification(
            $wpUserId,
            strtr($template['subject'], $replacements),
            strtr($template['body'], $replacements),
            ['email']
        ));
    }

    private function resolveWpUserId(string $companyUuid): ?int
    {
        try {
            $company = $this->companies->getCompany($companyUuid);
        } catch (CompanyNotFoundException) {
            return null;
        }

        $client = $this->clients->find($company->getClientId());

        return $client?->getWpUserId();
    }
}
