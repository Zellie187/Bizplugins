<?php

declare(strict_types=1);

namespace BizHub\Workflow\Workflows\CompanyAmendment;

use BizHub\Workflow\Contracts\WorkflowDefinitionInterface;
use BizHub\Workflow\DTO\TransitionRule;
use BizHub\Workflow\Enums\WorkflowStatus;

/**
 * The Company Amendment workflow's lifecycle: a single application
 * type covering director, name, and registered-address changes in any
 * combination, matching the combined product catalogue (e.g. "Director
 * & Name Change", "All-in-One").
 *
 * This intentionally departs from docs/workflows/Director-Changes.md,
 * Name-Changes.md and Address-Changes.md, each of which proposed a
 * separate workflow type per amendment kind. The client only ever
 * files one application per amendment request, which may bundle any
 * subset of the three changes - modelling that as three independently
 * running workflow instances per submission would require a new
 * "bundling" concept this engine doesn't have, for no benefit over one
 * workflow instance whose metadata records which change types it
 * covers (see CompanyAmendmentGuard for how each type's required data
 * is validated).
 *
 * Lifecycle mirrors CompanyRegistrationDefinition's shape exactly:
 *   Created -> Pending Documents -> Documents Verified ->
 *   Awaiting Payment -> Processing -> Quality Review ->
 *   Completed -> Archived
 * with Cancel available from any non-terminal status and Reject
 * available from Quality Review.
 *
 * Also mirrors CompanyRegistrationDefinition's second, recoverable
 * Quality Review exit: RejectName, for when CIPC declines a proposed
 * name change. That moves the workflow to NamesRejected so the client
 * can submit new names via ResubmitNames, returning it to Quality
 * Review. Unlike Registration - where every workflow always involves a
 * name - only applies when this amendment actually included a name
 * change (`amendment_types` contains 'name'); CompanyAmendmentGuard
 * enforces that at the reject_name transition itself, not just in the
 * admin UI, since the state machine alone has no way to know what an
 * instance's amendment_types are.
 *
 * @package BizHub\Workflow\Workflows\CompanyAmendment
 */
final class CompanyAmendmentDefinition implements WorkflowDefinitionInterface
{
    public const TYPE = 'company_amendment';

    public const AMENDMENT_TYPE_DIRECTOR = 'director';
    public const AMENDMENT_TYPE_NAME = 'name';
    public const AMENDMENT_TYPE_ADDRESS = 'address';

    /**
     * @var array<int,string>
     */
    public const ALL_AMENDMENT_TYPES = [
        self::AMENDMENT_TYPE_DIRECTOR,
        self::AMENDMENT_TYPE_NAME,
        self::AMENDMENT_TYPE_ADDRESS,
    ];

    public const ACTION_REQUEST_DOCUMENTS = 'request_documents';
    public const ACTION_VERIFY_DOCUMENTS = 'verify_documents';
    public const ACTION_REQUEST_PAYMENT = 'request_payment';
    public const ACTION_CONFIRM_PAYMENT = 'confirm_payment';
    public const ACTION_START_QUALITY_REVIEW = 'start_quality_review';
    public const ACTION_APPROVE = 'approve';
    public const ACTION_ARCHIVE = 'archive';
    public const ACTION_CANCEL = 'cancel';
    public const ACTION_REJECT = 'reject';
    public const ACTION_REJECT_NAME = 'reject_name';
    public const ACTION_RESUBMIT_NAMES = 'resubmit_names';

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
            WorkflowStatus::NamesRejected,
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
                self::ACTION_REJECT_NAME,
                [WorkflowStatus::QualityReview],
                WorkflowStatus::NamesRejected
            ),
            new TransitionRule(
                self::ACTION_RESUBMIT_NAMES,
                [WorkflowStatus::NamesRejected],
                WorkflowStatus::QualityReview
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
