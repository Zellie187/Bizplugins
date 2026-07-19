<?php

declare(strict_types=1);

namespace BizHub\Workflow\DTO;

use BizHub\Workflow\Contracts\CommandInterface;

/**
 * Command: roll a workflow instance back to the status it was in
 * immediately before its most recent transition.
 *
 * Rollback is only permitted while the workflow has not yet reached a
 * terminal status - a completed or archived workflow cannot be rolled
 * back, matching BH-WORKFLOW-SPEC-001 section 6's requirement that
 * every workflow define its rollback behaviour.
 *
 * @package BizHub\Workflow\DTO
 */
final readonly class RollbackWorkflowCommand implements CommandInterface
{
    public function __construct(
        public string $workflowUuid,
        public int $actorId,
        public string $reason,
    ) {
    }
}
