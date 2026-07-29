<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Contracts;

use BizHub\Bookkeeping\Entities\JournalEntry;
use BizHub\Bookkeeping\Entities\RecurringOccurrence;
use BizHub\Bookkeeping\Entities\RecurringTemplate;
use BizHub\Bookkeeping\Enums\PaymentMethod;
use BizHub\Bookkeeping\Enums\RecurringFrequency;
use BizHub\Bookkeeping\Enums\TransactionType;
use BizHub\Bookkeeping\Support\Money;
use DateTimeImmutable;

/**
 * Public API for recurring transaction templates and their cron-
 * generated occurrences. Confirming an occurrence always delegates to
 * TransactionCaptureServiceInterface - the same posting path Capture
 * and Bank Import already use - so the subscription gate, VAT support
 * and balanced-entry guarantee are all inherited for free.
 *
 * @package BizHub\Bookkeeping\Contracts
 */
interface RecurringTransactionServiceInterface
{
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
    ): RecurringTemplate;

    /**
     * @throws \BizHub\Bookkeeping\Exceptions\ValidationException
     */
    public function updateTemplate(
        string $companyUuid,
        string $templateUuid,
        Money $amount,
        string $categoryAccountUuid,
        PaymentMethod $paymentMethod,
        string $description,
        bool $includesVat,
        RecurringFrequency $frequency
    ): RecurringTemplate;

    /**
     * @throws \BizHub\Bookkeeping\Exceptions\ValidationException
     */
    public function pauseTemplate(string $companyUuid, string $templateUuid): void;

    /**
     * @throws \BizHub\Bookkeeping\Exceptions\ValidationException
     */
    public function resumeTemplate(string $companyUuid, string $templateUuid): void;

    /**
     * @throws \BizHub\Bookkeeping\Exceptions\ValidationException
     */
    public function deleteTemplate(string $companyUuid, string $templateUuid): void;

    /**
     * @return RecurringTemplate[]
     */
    public function listTemplates(string $companyUuid): array;

    /**
     * Registered as the daily WP-Cron callback: generates a pending
     * occurrence for every active template whose next_due_date has
     * arrived, advances that template's next_due_date, and queues one
     * review-ready notification per affected company.
     *
     * @return int Number of occurrences generated.
     */
    public function generateDueOccurrences(?DateTimeImmutable $asOf = null): int;

    /**
     * @return RecurringOccurrence[]
     */
    public function listPendingOccurrences(string $companyUuid): array;

    /**
     * @throws \BizHub\Bookkeeping\Exceptions\ValidationException
     */
    public function confirmOccurrence(
        string $companyUuid,
        string $occurrenceUuid,
        int $actorId,
        ?Money $overrideAmount = null,
        ?DateTimeImmutable $overrideDate = null
    ): JournalEntry;

    public function skipOccurrence(string $companyUuid, string $occurrenceUuid): void;
}
