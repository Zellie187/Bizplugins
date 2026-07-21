<?php

declare(strict_types=1);

namespace BizHub\Workflow\Tests\Unit\Workflows;

use BizHub\Framework\Support\Uuid;
use BizHub\Workflow\Entities\WorkflowInstance;
use BizHub\Workflow\Enums\WorkflowStatus;
use BizHub\Workflow\Exceptions\PreconditionFailedException;
use BizHub\Workflow\Workflows\CompanyRegistration\CompanyRegistrationDefinition;
use BizHub\Workflow\Workflows\CompanyRegistration\CompanyRegistrationGuard;
use PHPUnit\Framework\TestCase;

final class CompanyRegistrationGuardTest extends TestCase
{
    private CompanyRegistrationGuard $guard;

    private WorkflowInstance $workflow;

    protected function setUp(): void
    {
        $this->guard = new CompanyRegistrationGuard();
        $this->workflow = WorkflowInstance::start(
            Uuid::generate(),
            CompanyRegistrationDefinition::TYPE,
            'company',
            Uuid::generate(),
            WorkflowStatus::PendingDocuments,
            1
        );
    }

    public function test_verify_documents_requires_explicit_confirmation(): void
    {
        $this->expectException(PreconditionFailedException::class);

        $this->guard->guard(
            $this->workflow,
            WorkflowStatus::DocumentsVerified,
            CompanyRegistrationDefinition::ACTION_VERIFY_DOCUMENTS,
            []
        );
    }

    public function test_verify_documents_succeeds_when_confirmed(): void
    {
        $this->guard->guard(
            $this->workflow,
            WorkflowStatus::DocumentsVerified,
            CompanyRegistrationDefinition::ACTION_VERIFY_DOCUMENTS,
            ['documents_verified' => true]
        );

        $this->addToAssertionCount(1);
    }

    public function test_confirm_payment_requires_a_payment_reference(): void
    {
        $this->expectException(PreconditionFailedException::class);

        $this->guard->guard(
            $this->workflow,
            WorkflowStatus::Processing,
            CompanyRegistrationDefinition::ACTION_CONFIRM_PAYMENT,
            ['payment_reference' => '']
        );
    }

    public function test_confirm_payment_succeeds_with_a_reference(): void
    {
        $this->guard->guard(
            $this->workflow,
            WorkflowStatus::Processing,
            CompanyRegistrationDefinition::ACTION_CONFIRM_PAYMENT,
            ['payment_reference' => 'PMT-12345']
        );

        $this->addToAssertionCount(1);
    }

    public function test_approve_requires_a_reviewer(): void
    {
        $this->expectException(PreconditionFailedException::class);

        $this->guard->guard(
            $this->workflow,
            WorkflowStatus::Completed,
            CompanyRegistrationDefinition::ACTION_APPROVE,
            []
        );
    }

    public function test_cancel_has_no_precondition(): void
    {
        $this->guard->guard(
            $this->workflow,
            WorkflowStatus::Cancelled,
            CompanyRegistrationDefinition::ACTION_CANCEL,
            []
        );

        $this->addToAssertionCount(1);
    }

    public function test_resubmit_names_requires_at_least_one_proposed_name(): void
    {
        $this->expectException(PreconditionFailedException::class);

        $this->guard->guard(
            $this->workflow,
            WorkflowStatus::QualityReview,
            CompanyRegistrationDefinition::ACTION_RESUBMIT_NAMES,
            ['proposed_names' => []]
        );
    }

    public function test_resubmit_names_rejects_all_blank_names(): void
    {
        $this->expectException(PreconditionFailedException::class);

        $this->guard->guard(
            $this->workflow,
            WorkflowStatus::QualityReview,
            CompanyRegistrationDefinition::ACTION_RESUBMIT_NAMES,
            ['proposed_names' => ['', '  ']]
        );
    }

    public function test_resubmit_names_succeeds_with_at_least_one_name(): void
    {
        $this->guard->guard(
            $this->workflow,
            WorkflowStatus::QualityReview,
            CompanyRegistrationDefinition::ACTION_RESUBMIT_NAMES,
            ['proposed_names' => ['New Name (Pty) Ltd']]
        );

        $this->addToAssertionCount(1);
    }
}
