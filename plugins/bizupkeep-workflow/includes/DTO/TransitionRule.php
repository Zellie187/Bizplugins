<?php

declare(strict_types=1);

namespace BizHub\Workflow\DTO;

use BizHub\Workflow\Enums\WorkflowStatus;

/**
 * Declares that performing a named action while a workflow is in one
 * of a set of allowed source statuses moves it to a single target
 * status.
 *
 * A workflow definition's full set of TransitionRule value objects is
 * the single source of truth the state machine consults to reject
 * arbitrary transitions.
 *
 * @package BizHub\Workflow\DTO
 */
final readonly class TransitionRule
{
    /**
     * @param string                    $action Action name, e.g. "verify_documents".
     * @param array<int,WorkflowStatus> $from   Statuses this action may be performed from.
     * @param WorkflowStatus            $to     Status the workflow moves to.
     */
    public function __construct(
        public string $action,
        public array $from,
        public WorkflowStatus $to,
    ) {
    }

    /**
     * Determine whether this rule may fire from the given status.
     */
    public function allowsFrom(WorkflowStatus $status): bool
    {
        foreach ($this->from as $allowed) {
            if ($allowed === $status) {
                return true;
            }
        }

        return false;
    }
}
