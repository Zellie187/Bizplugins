<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Billing;

use BizHub\Bookkeeping\Contracts\SubscriptionRepositoryInterface;
use BizHub\Bookkeeping\Contracts\SubscriptionServiceInterface;
use BizHub\Bookkeeping\Entities\Subscription;
use BizHub\ClientPortal\Contracts\ClientRepositoryInterface;
use BizHub\Companies\Contracts\CompanyServiceInterface;
use BizHub\Companies\Exceptions\CompanyNotFoundException;
use BizHub\Notifications\Notification;
use BizHub\Notifications\NotificationQueue;
use DateTimeImmutable;
use Throwable;

/**
 * The WP-Cron entry point (see SubscriptionReminderServiceProvider):
 * scans every subscription daily and emails a reminder for the two
 * cases a client with no auto-billing needs to be warned about -
 * expiring soon, or already lapsed. Each is sent at most once per
 * paid_until cycle (see Subscription::$lastReminderType/$lastReminderForPaidUntil).
 *
 * Deliberately injects ClientRepositoryInterface directly (not
 * ClientServiceInterface, which has no numeric-id lookup) - the only
 * path from Company::getClientId() (int) to a WP user ID, the same
 * precedented exception ManualJournalEntryPage already makes for its
 * own read-side needs.
 *
 * @package BizHub\Bookkeeping\Billing
 */
final class SubscriptionReminderService
{
    /**
     * How many days before paid_until a subscription is considered
     * "expiring soon" and eligible for that reminder.
     */
    private const REMINDER_WINDOW_DAYS = 3;

    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptions,
        private readonly SubscriptionServiceInterface $subscriptionService,
        private readonly CompanyServiceInterface $companies,
        private readonly ClientRepositoryInterface $clients,
        private readonly NotificationQueue $notificationQueue
    ) {
    }

    /**
     * Registered as the daily WP-Cron callback. One bad company must
     * never stop the rest of the batch, so every subscription's work
     * is individually guarded.
     */
    public function sendDueReminders(): void
    {
        $now = new DateTimeImmutable();
        $sentAny = false;

        foreach ($this->subscriptions->findAll() as $subscription) {
            try {
                if ($this->sendReminderIfDue($subscription, $now)) {
                    $sentAny = true;
                }
            } catch (Throwable) {
                // Skip this company, keep processing the rest of the batch.
                continue;
            }
        }

        // NotificationQueue::enqueue() only inserts a pending row -
        // nothing else in this codebase drains the queue on a
        // schedule, so this cron callback must flush it itself.
        if ($sentAny) {
            $this->notificationQueue->processPending();
        }
    }

    private function sendReminderIfDue(Subscription $subscription, DateTimeImmutable $now): bool
    {
        // Never subscribed - nothing to remind about. Manually
        // suspended - staff are already handling this company directly.
        if ($subscription->paidUntil === null || $subscription->isSuspended) {
            return false;
        }

        $type = $this->dueReminderType($subscription, $now);

        if ($type === null) {
            return false;
        }

        // Already reminded for this exact (type, paid_until) pair -
        // a renewal changes paid_until and naturally re-opens eligibility.
        if (
            $subscription->lastReminderType === $type
            && $subscription->lastReminderForPaidUntil?->format('Y-m-d') === $subscription->paidUntil->format('Y-m-d')
        ) {
            return false;
        }

        $wpUserId = $this->resolveWpUserId($subscription->companyUuid);

        if ($wpUserId === null) {
            return false;
        }

        $this->queueReminder($wpUserId, $type, $subscription);
        $this->subscriptionService->markReminderSent($subscription->companyUuid, $type);

        return true;
    }

    private function dueReminderType(Subscription $subscription, DateTimeImmutable $now): ?string
    {
        if (! $subscription->isActive($now)) {
            return 'lapsed';
        }

        $windowEnd = $now->modify(sprintf('+%d days', self::REMINDER_WINDOW_DAYS));

        return $subscription->paidUntil <= $windowEnd ? 'expiring_soon' : null;
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

    private function queueReminder(int $wpUserId, string $type, Subscription $subscription): void
    {
        /** @var array<string,array{subject:string,body:string}> $templates */
        $templates = require BIZUPKEEP_BOOKKEEPING_CONFIG_PATH . 'reminders.php';
        $template = $templates[$type] ?? null;

        if ($template === null) {
            return;
        }

        $company = $this->companies->getCompany($subscription->companyUuid);

        $replacements = [
            '{company_name}' => $company->getCompanyName(),
            '{paid_until}' => $subscription->paidUntil?->format('Y-m-d') ?? '',
            '{renew_url}' => home_url('/client-portal/client-portal-bookkeeping/'),
        ];

        $this->notificationQueue->enqueue(new Notification(
            $wpUserId,
            strtr($template['subject'], $replacements),
            strtr($template['body'], $replacements),
            ['email']
        ));
    }
}
