<?php

declare(strict_types=1);

namespace BizHub\Workflow\Enums;

/**
 * The lifecycle states available to a workflow instance.
 *
 * "Created" through "Archived" form the happy-path lifecycle defined
 * by BH-WORKFLOW-SPEC-001 section 7. "Cancelled" and "Rejected" are
 * terminal exception states reachable from any non-terminal status,
 * satisfying the spec's rollback/completion-criteria requirements for
 * workflows that do not reach a successful conclusion.
 *
 * @package BizHub\Workflow\Enums
 */
enum WorkflowStatus: string
{
    case Created = 'created';
    case PendingDocuments = 'pending_documents';
    case DocumentsVerified = 'documents_verified';
    case AwaitingPayment = 'awaiting_payment';
    case Processing = 'processing';
    case QualityReview = 'quality_review';
    case Completed = 'completed';
    case Archived = 'archived';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';

    /**
     * Determine whether this status is a terminal state, i.e. no
     * further transitions are possible once reached.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Archived, self::Cancelled, self::Rejected => true,
            default => false,
        };
    }

    /**
     * Determine whether this status represents a successful
     * conclusion of the workflow (as opposed to cancellation/rejection).
     */
    public function isSuccessful(): bool
    {
        return $this === self::Completed || $this === self::Archived;
    }

    /**
     * A human-readable label, used by admin screens and notifications.
     */
    public function label(): string
    {
        return match ($this) {
            self::Created => __('Created', 'bizupkeep-workflow'),
            self::PendingDocuments => __('Pending Documents', 'bizupkeep-workflow'),
            self::DocumentsVerified => __('Documents Verified', 'bizupkeep-workflow'),
            self::AwaitingPayment => __('Awaiting Payment', 'bizupkeep-workflow'),
            self::Processing => __('Processing', 'bizupkeep-workflow'),
            self::QualityReview => __('Quality Review', 'bizupkeep-workflow'),
            self::Completed => __('Completed', 'bizupkeep-workflow'),
            self::Archived => __('Archived', 'bizupkeep-workflow'),
            self::Cancelled => __('Cancelled', 'bizupkeep-workflow'),
            self::Rejected => __('Rejected', 'bizupkeep-workflow'),
        };
    }
}
