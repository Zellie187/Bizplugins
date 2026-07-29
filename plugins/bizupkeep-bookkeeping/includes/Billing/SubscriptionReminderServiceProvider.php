<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Billing;

use BizHub\Framework\Providers\ServiceProvider;
use BizHub\Framework\Scheduler\ScheduledTask;
use BizHub\Framework\Scheduler\Scheduler;

/**
 * Registers the daily WP-Cron job that sends subscription-expiry
 * reminders. First real caller of BizHub Framework's Scheduler class
 * in this codebase - Scheduler::schedule() is idempotent (checks
 * wp_next_scheduled() before scheduling), so this is safe to call on
 * every request without creating duplicate cron events.
 *
 * @package BizHub\Bookkeeping\Billing
 */
final class SubscriptionReminderServiceProvider extends ServiceProvider
{
    public const CRON_HOOK = 'bizupkeep_bookkeeping_send_subscription_reminders';

    public function __construct(
        private readonly Scheduler $scheduler,
        private readonly SubscriptionReminderService $reminders
    ) {
    }

    public function register(): void
    {
    }

    public function boot(): void
    {
        $this->scheduler->schedule(new ScheduledTask(
            self::CRON_HOOK,
            'daily',
            [$this->reminders, 'sendDueReminders']
        ));
    }
}
