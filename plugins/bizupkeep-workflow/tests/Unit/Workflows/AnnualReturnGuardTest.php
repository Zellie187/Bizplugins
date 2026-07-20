<?php

declare(strict_types=1);

namespace BizHub\Workflow\Tests\Unit\Workflows;

use BizHub\Framework\Support\Uuid;
use BizHub\Workflow\Entities\WorkflowInstance;
use BizHub\Workflow\Enums\WorkflowStatus;
use BizHub\Workflow\Exceptions\PreconditionFailedException;
use BizHub\Workflow\Workflows\AnnualReturn\AnnualReturnDefinition;
use BizHub\Workflow\Workflows\AnnualReturn\AnnualReturnGuard;
use PHPUnit\Framework\TestCase;

final class AnnualReturnGuardTest extends TestCase
{
    private AnnualReturnGuard $guard;

    private WorkflowInstance $workflow;

    protected function setUp(): void
    {
        $this->guard = new AnnualReturnGuard();
        $this->workflow = WorkflowInstance::start(
            Uuid::generate(),
            AnnualReturnDefinition::TYPE,
            'company',
            Uuid::generate(),
            WorkflowStatus::AwaitingPayment,
            1,
            ['financial_year' => 2026]
        );
    }

    public function test_confirm_payment_requires_a_payment_reference(): void
    {
        $this->expectException(PreconditionFailedException::class);

        $this->guard->guard(
            $this->workflow,
            WorkflowStatus::Processing,
            AnnualReturnDefinition::ACTION_CONFIRM_PAYMENT,
            ['payment_reference' => '']
        );
    }

    public function test_confirm_payment_succeeds_with_a_reference(): void
    {
        $this->guard->guard(
            $this->workflow,
            WorkflowStatus::Processing,
            AnnualReturnDefinition::ACTION_CONFIRM_PAYMENT,
            ['payment_reference' => 'PMT-98765']
        );

        $this->addToAssertionCount(1);
    }

    public function test_approve_requires_a_reviewer(): void
    {
        $this->expectException(PreconditionFailedException::class);

        $this->guard->guard(
            $this->workflow,
            WorkflowStatus::Completed,
            AnnualReturnDefinition::ACTION_APPROVE,
            []
        );
    }

    public function test_approve_succeeds_with_a_reviewer(): void
    {
        $this->guard->guard(
            $this->workflow,
            WorkflowStatus::Completed,
            AnnualReturnDefinition::ACTION_APPROVE,
            ['reviewed_by' => 'admin']
        );

        $this->addToAssertionCount(1);
    }

    public function test_cancel_has_no_precondition(): void
    {
        $this->guard->guard(
            $this->workflow,
            WorkflowStatus::Cancelled,
            AnnualReturnDefinition::ACTION_CANCEL,
            []
        );

        $this->addToAssertionCount(1);
    }
}
