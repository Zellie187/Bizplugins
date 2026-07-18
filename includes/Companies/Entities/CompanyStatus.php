<?php

declare(strict_types=1);

namespace BizHub\Companies\Entities;

/**
 * Company lifecycle status.
 *
 * Defines the valid statuses that may be assigned to a company.
 *
 * @package BizHub\Companies\Entities
 */
enum CompanyStatus: string
{
    /**
     * Company record has been created.
     */
    case CREATED = 'created';

    /**
     * Awaiting supporting documentation.
     */
    case PENDING_DOCUMENTS = 'pending_documents';

    /**
     * Submitted to CIPC.
     */
    case SUBMITTED = 'submitted';

    /**
     * Currently being processed.
     */
    case PROCESSING = 'processing';

    /**
     * Waiting for an external authority.
     */
    case AWAITING_EXTERNAL_APPROVAL = 'awaiting_external_approval';

    /**
     * Registration completed.
     */
    case REGISTERED = 'registered';

    /**
     * Company is active.
     */
    case ACTIVE = 'active';

    /**
     * Annual return is due.
     */
    case ANNUAL_RETURN_DUE = 'annual_return_due';

    /**
     * Company is under compliance review.
     */
    case COMPLIANCE_REVIEW = 'compliance_review';

    /**
     * Company has been deregistered.
     */
    case DEREGISTERED = 'deregistered';

    /**
     * Company has been closed.
     */
    case CLOSED = 'closed';

    /**
     * Determine whether the company is active.
     */
    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    /**
     * Determine whether the company is registered.
     */
    public function isRegistered(): bool
    {
        return $this === self::REGISTERED;
    }

    /**
     * Determine whether the company has completed registration.
     */
    public function isCompleted(): bool
    {
        return \in_array(
            $this,
            [
                self::REGISTERED,
                self::ACTIVE,
            ],
            true
        );
    }

    /**
     * Determine whether the company is awaiting action.
     */
    public function isPending(): bool
    {
        return \in_array(
            $this,
            [
                self::CREATED,
                self::PENDING_DOCUMENTS,
                self::SUBMITTED,
                self::PROCESSING,
                self::AWAITING_EXTERNAL_APPROVAL,
            ],
            true
        );
    }

    /**
     * Determine whether the company is inactive.
     */
    public function isInactive(): bool
    {
        return \in_array(
            $this,
            [
                self::DEREGISTERED,
                self::CLOSED,
            ],
            true
        );
    }

    /**
     * Return a human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::CREATED => 'Created',
            self::PENDING_DOCUMENTS => 'Pending Documents',
            self::SUBMITTED => 'Submitted',
            self::PROCESSING => 'Processing',
            self::AWAITING_EXTERNAL_APPROVAL => 'Awaiting External Approval',
            self::REGISTERED => 'Registered',
            self::ACTIVE => 'Active',
            self::ANNUAL_RETURN_DUE => 'Annual Return Due',
            self::COMPLIANCE_REVIEW => 'Compliance Review',
            self::DEREGISTERED => 'Deregistered',
            self::CLOSED => 'Closed',
        };
    }
}
