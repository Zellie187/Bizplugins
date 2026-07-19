<?php

declare(strict_types=1);

namespace BizHub\Workflow\Contracts;

use BizHub\Workflow\Enums\WorkflowStatus;

/**
 * Declares a single workflow type's lifecycle: its initial status and
 * the full set of transitions permitted between statuses.
 *
 * Concrete definitions (e.g. CompanyRegistrationDefinition) are the
 * single source of truth their WorkflowStateMachine consults, so that
 * "no arbitrary state transitions" (BH-WORKFLOW-SPEC-001 section 7)
 * is enforced structurally rather than by convention.
 *
 * @package BizHub\Workflow\Contracts
 */
interface WorkflowDefinitionInterface
{
    /**
     * A unique, stable identifier for this workflow type, e.g.
     * "company_registration". Stored on every instance and used to
     * look the definition back up.
     */
    public function workflowType(): string;

    /**
     * The status a new instance of this workflow starts in.
     */
    public function initialStatus(): WorkflowStatus;

    /**
     * Every transition rule this workflow type permits, keyed by
     * action name.
     *
     * @return array<string,\BizHub\Workflow\DTO\TransitionRule>
     */
    public function transitionRules(): array;
}
