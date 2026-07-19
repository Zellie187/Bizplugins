<?php

declare(strict_types=1);

namespace BizHub\Workflow\Workflows\CompanyRegistration;

use BizHub\Workflow\Contracts\WorkflowDefinitionInterface;
use BizHub\Workflow\DTO\TransitionRule;
use BizHub\Workflow\Enums\WorkflowStatus;

/**
 * The Company Registration workflow's lifecycle, per
 * docs/workflows/Company-Registration.md:
 *
 *   Created -> Pending Documents -> Documents Verified ->
 *   Awaiting Payment -> Processing -> Quality Review ->
 *   Completed -> Archived
 *
 * with Cancel available from any non-terminal status and Reject
 * available from Quality Review, per BH-WORKFLOW-SPEC-001 section 7:
 * "No arbitrary state transitions should be allowed."
 *
 * @package BizHub\Workflow\Workflows\CompanyRegistration
 */
final class CompanyRegistrationDefinition implements WorkflowDefinitionInterface
{
    public const TYPE = 'company_registration';

    public const ACTION_REQUEST_DOCUMENTS = 'request_documents';
    public const ACTION_VERIFY_DOCUMENTS = 'verify_documents';
    public const ACTION_REQUEST_PAYMENT = 'request_payment';
    public const ACTION_CONFIRM_PAYMENT = 'confirm_payment';
    public const ACTION_START_QUALITY_REVIEW = 'start_quality_review';
    public const ACTION_APPROVE = 'approve';
    public const ACTION_ARCHIVE = 'archive';
    public const ACTION_CANCEL = 'cancel';
    public const ACTION_REJECT = 'reject';

    /**
     * {@inheritDoc}
     */
    public function workflowType(): string
    {
        return self::TYPE;
    }

    /**
     * {@inheritDoc}
     */
    public function initialStatus(): WorkflowStatus
    {
        return WorkflowStatus::Created;
    }

    /**
     * {@inheritDoc}
     */
    public function transitionRules(): array
    {
        $cancellableFrom = [
            WorkflowStatus::Created,
            WorkflowStatus::PendingDocuments,
            WorkflowStatus::DocumentsVerified,
            WorkflowStatus::AwaitingPayment,
            WorkflowStatus::Processing,
            WorkflowStatus::QualityReview,
        ];

        $rules = [
            new TransitionRule(
                self::ACTION_REQUEST_DOCUMENTS,
                [WorkflowStatus::Created],
                WorkflowStatus::PendingDocuments
            ),
            new TransitionRule(
                self::ACTION_VERIFY_DOCUMENTS,
                [WorkflowStatus::PendingDocuments],
                WorkflowStatus::DocumentsVerified
            ),
            new TransitionRule(
                self::ACTION_REQUEST_PAYMENT,
                [WorkflowStatus::DocumentsVerified],
                WorkflowStatus::AwaitingPayment
            ),
            new TransitionRule(
                self::ACTION_CONFIRM_PAYMENT,
                [WorkflowStatus::AwaitingPayment],
                WorkflowStatus::Processing
            ),
            new TransitionRule(
                self::ACTION_START_QUALITY_REVIEW,
                [WorkflowStatus::Processing],
                WorkflowStatus::QualityReview
            ),
            new TransitionRule(
                self::ACTION_APPROVE,
                [WorkflowStatus::QualityReview],
                WorkflowStatus::Completed
            ),
            new TransitionRule(
                self::ACTION_ARCHIVE,
                [WorkflowStatus::Completed],
                WorkflowStatus::Archived
            ),
            new TransitionRule(
                self::ACTION_REJECT,
                [WorkflowStatus::QualityReview],
                WorkflowStatus::Rejected
            ),
            new TransitionRule(
                self::ACTION_CANCEL,
                $cancellableFrom,
                WorkflowStatus::Cancelled
            ),
        ];

        $byAction = [];

        foreach ($rules as $rule) {
            $byAction[$rule->action] = $rule;
        }

        return $byAction;
    }
}
