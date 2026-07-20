<?php

declare(strict_types=1);

namespace BizHub\Workflow\Workflows\AnnualReturn;

use BizHub\Workflow\Contracts\WorkflowDefinitionInterface;
use BizHub\Workflow\DTO\TransitionRule;
use BizHub\Workflow\Enums\WorkflowStatus;

/**
 * The Annual Return workflow's lifecycle, per
 * docs/workflows/Annual-Returns.md:
 *
 *   Created -> Awaiting Payment -> Processing ->
 *   Quality Review -> Completed -> Archived
 *
 * No PendingDocuments/DocumentsVerified stage: an Annual Return only
 * reaffirms already-registered director/address details rather than
 * collecting new supporting documents, per the spec's Input section.
 *
 * No Reject path: per the spec, "CIPC does not typically reject a
 * compliant Annual Return the way it might reject a name change" -
 * Cancel remains available (e.g. the company is being deregistered
 * instead of filing).
 *
 * @package BizHub\Workflow\Workflows\AnnualReturn
 */
final class AnnualReturnDefinition implements WorkflowDefinitionInterface
{
    public const TYPE = 'annual_return';

    public const ACTION_REQUEST_PAYMENT = 'request_payment';
    public const ACTION_CONFIRM_PAYMENT = 'confirm_payment';
    public const ACTION_START_QUALITY_REVIEW = 'start_quality_review';
    public const ACTION_APPROVE = 'approve';
    public const ACTION_ARCHIVE = 'archive';
    public const ACTION_CANCEL = 'cancel';

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
            WorkflowStatus::AwaitingPayment,
            WorkflowStatus::Processing,
            WorkflowStatus::QualityReview,
        ];

        $rules = [
            new TransitionRule(
                self::ACTION_REQUEST_PAYMENT,
                [WorkflowStatus::Created],
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
