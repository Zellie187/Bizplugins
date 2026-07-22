<?php

declare(strict_types=1);

namespace BizHub\Workflow\DTO;

use BizHub\Workflow\Contracts\CommandInterface;
use BizHub\Workflow\Enums\WorkflowStatus;

/**
 * Command: force a workflow instance directly to a given status,
 * bypassing its definition's transition rules and guard entirely.
 *
 * This is deliberately outside the normal action-based transition()
 * path - an escape hatch for staff to "unstick" a workflow that has
 * ended up somewhere the guarded lifecycle cannot recover from on its
 * own (e.g. an external payment confirmed out of band, or a status
 * corrected after a data-entry mistake), gated by the stricter
 * Capabilities::WORKFLOW_MANAGE rather than WORKFLOW_TRANSITION. Every
 * use is still recorded as an ordinary Transition row so it shows up
 * in the workflow's audit history like any other state change.
 *
 * @package BizHub\Workflow\DTO
 */
final readonly class ForceStatusCommand implements CommandInterface
{
    public function __construct(
        public string $workflowUuid,
        public WorkflowStatus $to,
        public int $actorId,
        public string $reason,
    ) {
    }
}
