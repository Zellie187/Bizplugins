<?php

declare(strict_types=1);

namespace BizHub\Framework\Scheduler;

/**
 * Registers and manages recurring tasks via WP-Cron.
 *
 * @package BizHub\Framework\Scheduler
 */
final class Scheduler
{
    /**
     * @var array<int,ScheduledTask>
     */
    private array $tasks = [];

    /**
     * Register a task to run on its recurrence schedule.
     *
     * The task's callback is wired to its hook immediately; the WP-Cron
     * event itself is only scheduled the first time (if not already).
     */
    public function schedule(ScheduledTask $task): void
    {
        $this->tasks[] = $task;

        add_action($task->hook, $task->callback, 10, count($task->arguments));

        if (! wp_next_scheduled($task->hook, $task->arguments)) {
            wp_schedule_event(time(), $task->recurrence, $task->hook, $task->arguments);
        }
    }

    /**
     * Remove a previously scheduled task by hook name.
     *
     * @param array<int,mixed> $arguments
     */
    public function unschedule(string $hook, array $arguments = []): void
    {
        $timestamp = wp_next_scheduled($hook, $arguments);

        if ($timestamp !== false) {
            wp_unschedule_event($timestamp, $hook, $arguments);
        }
    }

    /**
     * Determine whether a hook currently has a pending scheduled event.
     *
     * @param array<int,mixed> $arguments
     */
    public function isScheduled(string $hook, array $arguments = []): bool
    {
        return wp_next_scheduled($hook, $arguments) !== false;
    }

    /**
     * Return every task registered during this request.
     *
     * @return array<int,ScheduledTask>
     */
    public function tasks(): array
    {
        return $this->tasks;
    }
}
