<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Entities;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A company's monthly bookkeeping-access subscription record.
 *
 * Immutable, like every other entity in this plugin - SubscriptionService
 * builds a fresh instance on every state change (extend/suspend/reactivate)
 * and SubscriptionRepository persists it.
 *
 * @package BizHub\Bookkeeping\Entities
 */
final readonly class Subscription
{
    public function __construct(
        public string $uuid,
        public string $companyUuid,
        public ?DateTimeImmutable $paidUntil,
        public bool $isSuspended,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt = null,
        /** 'expiring_soon'|'lapsed'|null - the kind of reminder last sent for this subscription. */
        public ?string $lastReminderType = null,
        /** The paid_until value the last reminder was sent for - a new paid_until (renewal) always makes a fresh reminder eligible. */
        public ?DateTimeImmutable $lastReminderForPaidUntil = null
    ) {
        if ($this->uuid === '') {
            throw new InvalidArgumentException('Subscription uuid cannot be empty.');
        }

        if ($this->companyUuid === '') {
            throw new InvalidArgumentException('Subscription companyUuid cannot be empty.');
        }
    }

    /**
     * Never subscribed, lapsed, or manually suspended all count as
     * inactive - the caller doesn't need to distinguish which.
     */
    public function isActive(?DateTimeImmutable $asOf = null): bool
    {
        if ($this->isSuspended || $this->paidUntil === null) {
            return false;
        }

        return $this->paidUntil >= ($asOf ?? new DateTimeImmutable());
    }
}
