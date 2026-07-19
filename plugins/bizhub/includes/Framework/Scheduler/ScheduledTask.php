<?php

declare(strict_types=1);

namespace BizHub\Framework\Scheduler;

/**
 * Describes a single recurring task to be run via WP-Cron.
 *
 * @package BizHub\Framework\Scheduler
 */
final readonly class ScheduledTask
{
    /**
     * @param string             $hook      Unique WP-Cron hook name.
     * @param string             $recurrence WP-Cron recurrence key ('hourly', 'daily', 'twicedaily', etc.).
     * @param callable():void    $callback   Callback to execute when the hook fires.
     * @param array<int,mixed>   $arguments  Arguments passed to the callback.
     */
    public function __construct(
        public string $hook,
        public string $recurrence,
        public mixed $callback,
        public array $arguments = [],
    ) {
    }
}
