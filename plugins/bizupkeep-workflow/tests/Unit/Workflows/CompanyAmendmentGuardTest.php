<?php

declare(strict_types=1);

namespace BizHub\Workflow\Tests\Unit\Workflows;

use BizHub\Framework\Support\Uuid;
use BizHub\Workflow\Entities\WorkflowInstance;
use BizHub\Workflow\Enums\WorkflowStatus;
use BizHub\Workflow\Exceptions\PreconditionFailedException;
use BizHub\Workflow\Workflows\CompanyAmendment\CompanyAmendmentDefinition;
use BizHub\Workflow\Workflows\CompanyAmendment\CompanyAmendmentGuard;
use PHPUnit\Framework\TestCase;

final class CompanyAmendmentGuardTest extends TestCase
{
    private CompanyAmendmentGuard $guard;

    protected function setUp(): void
    {
        $this->guard = new CompanyAmendmentGuard();
    }

    private function workflowWithMetadata(WorkflowStatus $status, array $metadata): WorkflowInstance
    {
        return WorkflowInstance::start(
            Uuid::generate(),
            CompanyAmendmentDefinition::TYPE,
            'company',
            Uuid::generate(),
            $status,
            1,
            $metadata
        );
    }

    public function test_request_documents_requires_at_least_one_amendment_type(): void
    {
        $this->expectException(PreconditionFailedException::class);

        $this->guard->guard(
            $this->workflowWithMetadata(WorkflowStatus::Created, []),
            WorkflowStatus::PendingDocuments,
            CompanyAmendmentDefinition::ACTION_REQUEST_DOCUMENTS,
            []
        );
    }

    public function test_request_documents_succeeds_with_a_valid_amendment_type(): void
    {
        $this->guard->guard(
            $this->workflowWithMetadata(WorkflowStatus::Created, ['amendment_types' => ['address']]),
            WorkflowStatus::PendingDocuments,
            CompanyAmendmentDefinition::ACTION_REQUEST_DOCUMENTS,
            []
        );

        $this->addToAssertionCount(1);
    }

    public function test_verify_documents_requires_explicit_confirmation(): void
    {
        $this->expectException(PreconditionFailedException::class);

        $this->guard->guard(
            $this->workflowWithMetadata(WorkflowStatus::PendingDocuments, ['amendment_types' => ['address']]),
            WorkflowStatus::DocumentsVerified,
            CompanyAmendmentDefinition::ACTION_VERIFY_DOCUMENTS,
            []
        );
    }

    public function test_verify_documents_requires_a_proposed_name_when_name_change_selected(): void
    {
        $this->expectException(PreconditionFailedException::class);

        $this->guard->guard(
            $this->workflowWithMetadata(WorkflowStatus::PendingDocuments, [
                'amendment_types' => ['name'],
                'proposed_names' => [],
            ]),
            WorkflowStatus::DocumentsVerified,
            CompanyAmendmentDefinition::ACTION_VERIFY_DOCUMENTS,
            ['documents_verified' => true]
        );
    }

    public function test_verify_documents_succeeds_with_a_proposed_name(): void
    {
        $this->guard->guard(
            $this->workflowWithMetadata(WorkflowStatus::PendingDocuments, [
                'amendment_types' => ['name'],
                'proposed_names' => ['Acme Trading (Pty) Ltd'],
            ]),
            WorkflowStatus::DocumentsVerified,
            CompanyAmendmentDefinition::ACTION_VERIFY_DOCUMENTS,
            ['documents_verified' => true]
        );

        $this->addToAssertionCount(1);
    }

    public function test_verify_documents_requires_director_changes_when_director_amendment_selected(): void
    {
        $this->expectException(PreconditionFailedException::class);

        $this->guard->guard(
            $this->workflowWithMetadata(WorkflowStatus::PendingDocuments, [
                'amendment_types' => ['director'],
                'director_changes' => [],
            ]),
            WorkflowStatus::DocumentsVerified,
            CompanyAmendmentDefinition::ACTION_VERIFY_DOCUMENTS,
            ['documents_verified' => true]
        );
    }

    public function test_verify_documents_requires_a_complete_address_when_address_change_selected(): void
    {
        $this->expectException(PreconditionFailedException::class);

        $this->guard->guard(
            $this->workflowWithMetadata(WorkflowStatus::PendingDocuments, [
                'amendment_types' => ['address'],
                'new_address' => ['address_line_1' => '12 Main Street'],
            ]),
            WorkflowStatus::DocumentsVerified,
            CompanyAmendmentDefinition::ACTION_VERIFY_DOCUMENTS,
            ['documents_verified' => true]
        );
    }

    public function test_verify_documents_succeeds_with_a_combination_of_amendment_types(): void
    {
        $this->guard->guard(
            $this->workflowWithMetadata(WorkflowStatus::PendingDocuments, [
                'amendment_types' => ['director', 'address'],
                'director_changes' => [['action' => 'add', 'first_name' => 'Jane', 'last_name' => 'Doe']],
                'new_address' => [
                    'address_line_1' => '12 Main Street',
                    'city' => 'Cape Town',
                    'postal_code' => '8001',
                ],
            ]),
            WorkflowStatus::DocumentsVerified,
            CompanyAmendmentDefinition::ACTION_VERIFY_DOCUMENTS,
            ['documents_verified' => true]
        );

        $this->addToAssertionCount(1);
    }

    public function test_confirm_payment_requires_a_payment_reference(): void
    {
        $this->expectException(PreconditionFailedException::class);

        $this->guard->guard(
            $this->workflowWithMetadata(WorkflowStatus::AwaitingPayment, ['amendment_types' => ['address']]),
            WorkflowStatus::Processing,
            CompanyAmendmentDefinition::ACTION_CONFIRM_PAYMENT,
            ['payment_reference' => '']
        );
    }

    public function test_approve_requires_a_reviewer(): void
    {
        $this->expectException(PreconditionFailedException::class);

        $this->guard->guard(
            $this->workflowWithMetadata(WorkflowStatus::QualityReview, ['amendment_types' => ['address']]),
            WorkflowStatus::Completed,
            CompanyAmendmentDefinition::ACTION_APPROVE,
            []
        );
    }

    public function test_cancel_has_no_precondition(): void
    {
        $this->guard->guard(
            $this->workflowWithMetadata(WorkflowStatus::Processing, ['amendment_types' => ['address']]),
            WorkflowStatus::Cancelled,
            CompanyAmendmentDefinition::ACTION_CANCEL,
            []
        );

        $this->addToAssertionCount(1);
    }
}
