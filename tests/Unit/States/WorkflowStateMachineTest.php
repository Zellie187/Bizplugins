<?php

declare(strict_types=1);

namespace BizHub\Workflow\Tests\Unit\States;

use BizHub\Workflow\Enums\WorkflowStatus;
use BizHub\Workflow\Exceptions\InvalidTransitionException;
use BizHub\Workflow\States\WorkflowStateMachine;
use BizHub\Workflow\Workflows\CompanyRegistration\CompanyRegistrationDefinition;
use PHPUnit\Framework\TestCase;

final class WorkflowStateMachineTest extends TestCase
{
    private WorkflowStateMachine $stateMachine;

    private CompanyRegistrationDefinition $definition;

    protected function setUp(): void
    {
        $this->stateMachine = new WorkflowStateMachine();
        $this->definition = new CompanyRegistrationDefinition();
    }

    public function test_a_declared_action_moves_to_its_declared_target_status(): void
    {
        $to = $this->stateMachine->apply(
            $this->definition,
            WorkflowStatus::Created,
            CompanyRegistrationDefinition::ACTION_REQUEST_DOCUMENTS
        );

        $this->assertSame(WorkflowStatus::PendingDocuments, $to);
    }

    public function test_an_unknown_action_is_rejected(): void
    {
        $this->expectException(InvalidTransitionException::class);

        $this->stateMachine->apply($this->definition, WorkflowStatus::Created, 'teleport');
    }

    public function test_a_declared_action_from_the_wrong_status_is_rejected(): void
    {
        $this->expectException(InvalidTransitionException::class);

        // confirm_payment is only valid from AwaitingPayment, not Created.
        $this->stateMachine->apply(
            $this->definition,
            WorkflowStatus::Created,
            CompanyRegistrationDefinition::ACTION_CONFIRM_PAYMENT
        );
    }

    public function test_no_action_is_permitted_once_a_workflow_is_terminal(): void
    {
        $this->expectException(InvalidTransitionException::class);

        $this->stateMachine->apply(
            $this->definition,
            WorkflowStatus::Archived,
            CompanyRegistrationDefinition::ACTION_CANCEL
        );
    }

    public function test_cancel_is_allowed_from_every_non_terminal_status_but_not_from_completed(): void
    {
        foreach ([
            WorkflowStatus::Created,
            WorkflowStatus::PendingDocuments,
            WorkflowStatus::DocumentsVerified,
            WorkflowStatus::AwaitingPayment,
            WorkflowStatus::Processing,
            WorkflowStatus::QualityReview,
        ] as $status) {
            $to = $this->stateMachine->apply($this->definition, $status, CompanyRegistrationDefinition::ACTION_CANCEL);
            $this->assertSame(WorkflowStatus::Cancelled, $to);
        }

        $this->expectException(InvalidTransitionException::class);
        $this->stateMachine->apply($this->definition, WorkflowStatus::Completed, CompanyRegistrationDefinition::ACTION_CANCEL);
    }

    public function test_allowed_actions_reflects_only_actions_valid_from_current_status(): void
    {
        $actions = $this->stateMachine->allowedActions($this->definition, WorkflowStatus::DocumentsVerified);

        $this->assertContains(CompanyRegistrationDefinition::ACTION_REQUEST_PAYMENT, $actions);
        $this->assertContains(CompanyRegistrationDefinition::ACTION_CANCEL, $actions);
        $this->assertNotContains(CompanyRegistrationDefinition::ACTION_VERIFY_DOCUMENTS, $actions);
        $this->assertNotContains(CompanyRegistrationDefinition::ACTION_APPROVE, $actions);
    }

    public function test_allowed_actions_is_empty_once_terminal(): void
    {
        $this->assertSame([], $this->stateMachine->allowedActions($this->definition, WorkflowStatus::Archived));
    }
}
