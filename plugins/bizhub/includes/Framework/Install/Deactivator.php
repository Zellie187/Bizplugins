<?php

declare(strict_types=1);

namespace BizHub\Framework\Install;

/**
 * Handles deactivation-time framework cleanup.
 *
 * Deliberately a no-op for now: no module currently schedules a
 * recurring WP-Cron task via BizHub\Framework\Scheduler, so there is
 * nothing to unschedule. Deactivation intentionally does not touch the
 * database - that is reserved for uninstall.php, and only when the
 * user has opted in to deleting their data.
 *
 * @package BizHub\Framework\Install
 */
final class Deactivator
{
    public function deactivate(): void
    {
    }
}
