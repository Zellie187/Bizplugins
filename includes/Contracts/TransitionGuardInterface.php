<?php

declare(strict_types=1);

namespace BizHub\Workflow\Contracts;

use BizHub\Workflow\Entities\WorkflowInstance;
use BizHub\Workflow\Enums\WorkflowStatus;
use BizHub\Workflow\Exceptions\PreconditionFailedException;

/**
 * A pluggable Strategy for enforcing a workflow type's business rules
 * and preconditions before a structurally-valid transition is applied.
 *
 * The state machine already guarantees the requested transition is
 * *declared* by the workflow definition; a guard is where "cannot
 * verify documents that were never uploaded" or "cannot start
 * processing before payment is received" is enforced.
 *
 * @package BizHub\Workflow\Contracts
 */
interface TransitionGuardInterface
{
    /**
     * Verify the preconditions for moving $workflow to $to via
     * $action are satisfied.
     *
     * @param array<string,mixed> $context Action-specific data supplied by the caller.
     *
     * @throws PreconditionFailedException If a precondition is not met.
     */
    public function guard(
        WorkflowInstance $workflow,
        WorkflowStatus $to,
        string $action,
        array $context
    ): void;
}
