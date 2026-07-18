<?php

declare(strict_types=1);

namespace BizHub\Applications\Entities;

/**
 * Application lifecycle status.
 *
 * @package BizHub\Applications\Entities
 */
enum ApplicationStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case IN_REVIEW = 'in_review';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
    case COMPLETED = 'completed';

    /**
     * Determine whether the application can still be edited.
     */
    public function isEditable(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * Determine whether the application has reached a terminal state.
     */
    public function isFinal(): bool
    {
        return \in_array($this, [self::APPROVED, self::REJECTED, self::CANCELLED, self::COMPLETED], true);
    }

    /**
     * Return a human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SUBMITTED => 'Submitted',
            self::IN_REVIEW => 'In Review',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::CANCELLED => 'Cancelled',
            self::COMPLETED => 'Completed',
        };
    }
}
