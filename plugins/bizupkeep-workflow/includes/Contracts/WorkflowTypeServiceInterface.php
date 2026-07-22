<?php

declare(strict_types=1);

namespace BizHub\Workflow\Contracts;

use BizHub\Workflow\DTO\Transition;
use BizHub\Workflow\Entities\WorkflowInstance;
use BizHub\Workflow\Enums\WorkflowStatus;
use BizHub\Workflow\Exceptions\InvalidTransitionException;
use BizHub\Workflow\Exceptions\ValidationException;
use BizHub\Workflow\Exceptions\WorkflowNotFoundException;

/**
 * The common shape every concrete workflow type's Service layer
 * implements (CompanyRegistrationService, CompanyAmendmentService,
 * AnnualReturnService, and any future type). Lets code that needs to
 * operate on a workflow instance without knowing its concrete type in
 * advance - e.g. the Quality Review admin screen, which reviews
 * applications across all three types - dispatch to the right
 * concrete service polymorphically instead of hardcoding one type.
 *
 * Each concrete type's Service class remains the only path that ever
 * touches WorkflowEngineInterface for that type (per BH-WORKFLOW-
 * SPEC-001 section 4's Controller -> Service -> Workflow Engine
 * layering) - this interface does not change that, it only names the
 * shape those classes already share.
 *
 * @package BizHub\Workflow\Contracts
 */
interface WorkflowTypeServiceInterface
{
    /**
     * Perform a named action against a workflow instance of this type.
     *
     * @param array<string,mixed> $context
     *
     * @throws ValidationException       If $action is not valid for this workflow type.
     * @throws WorkflowNotFoundException If the workflow does not exist or is not of this type.
     */
    public function performAction(
        string $workflowUuid,
        string $action,
        int $userId,
        string $reason = '',
        array $context = []
    ): WorkflowInstance;

    /**
     * Roll a workflow instance of this type back to its previous status.
     */
    public function rollback(string $workflowUuid, int $userId, string $reason): WorkflowInstance;

    /**
     * Force a workflow instance of this type directly to a given
     * status, bypassing its normal guarded transitions - a staff
     * "unstick" tool, gated by the caller at the stricter
     * Capabilities::WORKFLOW_MANAGE rather than WORKFLOW_TRANSITION.
     *
     * @throws WorkflowNotFoundException  If the workflow does not exist or is not of
     *                                     this type.
     * @throws InvalidTransitionException If the workflow is already terminal, or
     *                                     already at the requested status.
     */
    public function forceStatus(
        string $workflowUuid,
        WorkflowStatus $to,
        int $userId,
        string $reason
    ): WorkflowInstance;

    /**
     * Retrieve a workflow instance of this type.
     *
     * @throws WorkflowNotFoundException
     */
    public function find(string $workflowUuid): WorkflowInstance;

    /**
     * Retrieve a workflow instance's full transition history.
     *
     * @return array<int,Transition>
     */
    public function historyFor(string $workflowUuid): array;
}
