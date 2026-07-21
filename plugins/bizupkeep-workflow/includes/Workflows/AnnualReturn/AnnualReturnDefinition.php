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
 * Unlike Company Registration/Amendment, "request_payment" (Created ->
 * AwaitingPayment) is not something the client's own submission fires
 * automatically - it requires a `quote_amount` in context
 * (AnnualReturnGuard::guardQuoteAmount()), and is only ever fired
 * from the staff-facing Quality Review screen once someone has
 * actually checked CIPC and decided what to charge. This is the
 * "staff to check annual returns on CIPC site >> send quote to
 * client" step from the workflow spec: a client sits in Created,
 * quote-less, until staff act - there's no separate "awaiting quote"
 * status because Created already means exactly that for this type.
 *
 * "revise_quote" is a self-loop (AwaitingPayment -> AwaitingPayment)
 * covering the one gap that design otherwise leaves: once quoted,
 * there was no way to correct a wrong amount without cancelling the
 * whole application. It's guarded by the same quote_amount
 * precondition as request_payment and simply overwrites
 * quote_amount/quote_notes in metadata via the normal
 * context-merge-on-transition mechanism every action already gets -
 * available only before the client pays (AwaitingPayment -> Processing
 * via confirm_payment is the point of no return for this).
 *
 * A single application can cover multiple outstanding financial years
 * at once (metadata `filings`, a list of {financial_year, turnover}
 * pairs - turnover matters because CIPC's filing fee is turnover-
 * banded) rather than one workflow per year, since a client behind on
 * several years' returns files and pays for all of them together, not
 * as separate applications. AnnualReturnService::alreadyFiled() checks
 * every requested year against every one of the company's existing,
 * non-cancelled Annual Return workflows (old single-`financial_year`
 * metadata from before this shape existed is read as a one-entry list,
 * for backward compatibility).
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
    public const ACTION_REVISE_QUOTE = 'revise_quote';
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
                self::ACTION_REVISE_QUOTE,
                [WorkflowStatus::AwaitingPayment],
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
