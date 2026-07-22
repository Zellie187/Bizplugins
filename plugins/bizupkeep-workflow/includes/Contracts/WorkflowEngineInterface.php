<?php

declare(strict_types=1);

namespace BizHub\Workflow\Contracts;

use BizHub\Workflow\DTO\CreateWorkflowCommand;
use BizHub\Workflow\DTO\ForceStatusCommand;
use BizHub\Workflow\DTO\RollbackWorkflowCommand;
use BizHub\Workflow\DTO\Transition;
use BizHub\Workflow\DTO\TransitionWorkflowCommand;
use BizHub\Workflow\Entities\WorkflowInstance;
use BizHub\Workflow\Exceptions\InvalidTransitionException;
use BizHub\Workflow\Exceptions\PreconditionFailedException;
use BizHub\Workflow\Exceptions\WorkflowNotFoundException;

/**
 * The public entry point business modules and controllers use to
 * drive workflow instances, per BH-WORKFLOW-SPEC-001 section 4's
 * layering: Controller -> Service -> Workflow Engine -> Repository ->
 * Framework Database.
 *
 * @package BizHub\Workflow\Contracts
 */
interface WorkflowEngineInterface
{
    /**
     * Start a new workflow instance.
     */
    public function create(CreateWorkflowCommand $command): WorkflowInstance;

    /**
     * Perform a named action against an existing workflow instance.
     *
     * @throws WorkflowNotFoundException
     * @throws InvalidTransitionException
     * @throws PreconditionFailedException
     */
    public function transition(TransitionWorkflowCommand $command): WorkflowInstance;

    /**
     * Roll a workflow instance back to its previous status.
     *
     * @throws WorkflowNotFoundException
     * @throws InvalidTransitionException
     */
    public function rollback(RollbackWorkflowCommand $command): WorkflowInstance;

    /**
     * Force a workflow instance directly to a given status, bypassing
     * its definition's transition rules and guard. See
     * ForceStatusCommand for when this is appropriate.
     *
     * @throws WorkflowNotFoundException
     * @throws InvalidTransitionException If the workflow is already terminal, or
     *                                     already at the requested status.
     */
    public function forceStatus(ForceStatusCommand $command): WorkflowInstance;

    /**
     * Retrieve a workflow instance by UUID.
     *
     * @throws WorkflowNotFoundException
     */
    public function find(string $uuid): WorkflowInstance;

    /**
     * Retrieve the full transition history for a workflow instance.
     *
     * @return array<int,Transition>
     */
    public function historyFor(string $uuid): array;
}
