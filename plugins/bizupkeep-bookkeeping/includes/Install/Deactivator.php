<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Install;

use BizHub\Bookkeeping\Billing\SubscriptionReminderServiceProvider;
use BizHub\Bookkeeping\Recurring\RecurringTransactionServiceProvider;

/**
 * Handles deactivation-time cleanup for BizUpKeep Bookkeeping.
 *
 * Deliberately does not touch the database or delete any ledger data -
 * that is reserved for uninstall.php, and only when the user has opted
 * in to deleting their data.
 *
 * @package BizHub\Bookkeeping\Install
 */
final class Deactivator
{
    public function deactivate(): void
    {
        // Uses the raw WP function directly rather than resolving
        // BizHub Framework's Scheduler via the container - deactivation
        // runs standalone (same reasoning as Activator), and an orphaned
        // cron event still firing after deactivation would error trying
        // to autoload a class this plugin's autoloader no longer serves.
        wp_clear_scheduled_hook(SubscriptionReminderServiceProvider::CRON_HOOK);
        wp_clear_scheduled_hook(RecurringTransactionServiceProvider::CRON_HOOK);

        flush_rewrite_rules();
    }
}
