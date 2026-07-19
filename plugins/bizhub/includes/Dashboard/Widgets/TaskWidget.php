<?php

declare(strict_types=1);

namespace BizHub\Dashboard\Widgets;

use BizHub\Applications\Contracts\ApplicationServiceInterface;

/**
 * Dashboard widget summarizing a client's outstanding tasks.
 *
 * A "task" is an incomplete workflow step on one of the client's
 * applications; BizHub has no separate task-management domain.
 *
 * @package BizHub\Dashboard\Widgets
 */
final class TaskWidget implements Widget
{
    public function __construct(
        private readonly ApplicationServiceInterface $applications
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function key(): string
    {
        return 'tasks';
    }

    /**
     * {@inheritDoc}
     */
    public function data(int $clientId, string $clientUuid): array
    {
        $tasks = [];

        foreach ($this->applications->getApplicationsForClient($clientId) as $application) {
            foreach ($application->getSteps() as $step) {
                if ($step->isCompleted()) {
                    continue;
                }

                $tasks[] = [
                    'application_uuid' => $application->getUuid(),
                    'application_type' => $application->getType(),
                    'step_uuid' => $step->getUuid(),
                    'step_name' => $step->getName(),
                ];
            }
        }

        return [
            'total' => count($tasks),
            'items' => $tasks,
        ];
    }
}
