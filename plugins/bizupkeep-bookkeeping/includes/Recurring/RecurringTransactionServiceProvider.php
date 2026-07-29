<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Recurring;

use BizHub\Framework\Providers\ServiceProvider;
use BizHub\Framework\Scheduler\ScheduledTask;
use BizHub\Framework\Scheduler\Scheduler;

/**
 * Registers the daily WP-Cron job that generates pending occurrences
 * for due recurring transaction templates - same proven pattern as
 * SubscriptionReminderServiceProvider.
 *
 * @package BizHub\Bookkeeping\Recurring
 */
final class RecurringTransactionServiceProvider extends ServiceProvider
{
    public const CRON_HOOK = 'bizupkeep_bookkeeping_generate_recurring_occurrences';

    public function __construct(
        private readonly Scheduler $scheduler,
        private readonly RecurringTransactionService $recurring
    ) {
    }

    public function register(): void
    {
    }

    public function boot(): void
    {
        $recurring = $this->recurring;

        $this->scheduler->schedule(new ScheduledTask(
            self::CRON_HOOK,
            'daily',
            static function () use ($recurring): void {
                $recurring->generateDueOccurrences();
            }
        ));
    }
}
