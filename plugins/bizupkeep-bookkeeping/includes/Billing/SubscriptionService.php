<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Billing;

use BizHub\Bookkeeping\Contracts\SubscriptionRepositoryInterface;
use BizHub\Bookkeeping\Contracts\SubscriptionServiceInterface;
use BizHub\Bookkeeping\Entities\Subscription;
use BizHub\Framework\Support\Uuid;
use DateTimeImmutable;

/**
 * Public API for a company's monthly bookkeeping-access subscription.
 *
 * @package BizHub\Bookkeeping\Billing
 */
final class SubscriptionService implements SubscriptionServiceInterface
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptions
    ) {
    }

    public function isActive(string $companyUuid): bool
    {
        $subscription = $this->subscriptions->findByCompanyUuid($companyUuid);

        return $subscription !== null && $subscription->isActive();
    }

    public function getOrCreate(string $companyUuid): Subscription
    {
        $existing = $this->subscriptions->findByCompanyUuid($companyUuid);

        if ($existing !== null) {
            return $existing;
        }

        return $this->subscriptions->save(new Subscription(
            uuid: Uuid::generate(),
            companyUuid: $companyUuid,
            paidUntil: null,
            isSuspended: false,
            createdAt: new DateTimeImmutable(),
        ));
    }

    public function extend(string $companyUuid, int $days = 30): Subscription
    {
        $existing = $this->getOrCreate($companyUuid);
        $now = new DateTimeImmutable();

        // Extend from whichever is later - a renewal made before the
        // current period has lapsed must not discard already-paid time.
        $base = ($existing->paidUntil !== null && $existing->paidUntil > $now) ? $existing->paidUntil : $now;

        // lastReminderType/lastReminderForPaidUntil are carried forward
        // unchanged, not reset here - the new paidUntil above already
        // differs from whatever lastReminderForPaidUntil was, which is
        // what makes SubscriptionReminderService treat this company as
        // freshly eligible for a reminder next cycle. No explicit reset
        // needed.
        return $this->subscriptions->save(new Subscription(
            uuid: $existing->uuid,
            companyUuid: $existing->companyUuid,
            paidUntil: $base->modify(sprintf('+%d days', $days)),
            // A real payment always restores access, even over a prior
            // manual suspension - suspension in practice will almost
            // always have been for non-payment.
            isSuspended: false,
            createdAt: $existing->createdAt,
            updatedAt: $now,
            lastReminderType: $existing->lastReminderType,
            lastReminderForPaidUntil: $existing->lastReminderForPaidUntil,
        ));
    }

    public function suspend(string $companyUuid): Subscription
    {
        $existing = $this->getOrCreate($companyUuid);

        return $this->subscriptions->save(new Subscription(
            uuid: $existing->uuid,
            companyUuid: $existing->companyUuid,
            paidUntil: $existing->paidUntil,
            isSuspended: true,
            createdAt: $existing->createdAt,
            updatedAt: new DateTimeImmutable(),
            lastReminderType: $existing->lastReminderType,
            lastReminderForPaidUntil: $existing->lastReminderForPaidUntil,
        ));
    }

    public function reactivate(string $companyUuid): Subscription
    {
        $existing = $this->getOrCreate($companyUuid);

        return $this->subscriptions->save(new Subscription(
            uuid: $existing->uuid,
            companyUuid: $existing->companyUuid,
            paidUntil: $existing->paidUntil,
            isSuspended: false,
            createdAt: $existing->createdAt,
            updatedAt: new DateTimeImmutable(),
            lastReminderType: $existing->lastReminderType,
            lastReminderForPaidUntil: $existing->lastReminderForPaidUntil,
        ));
    }

    public function markReminderSent(string $companyUuid, string $type): Subscription
    {
        $existing = $this->getOrCreate($companyUuid);

        return $this->subscriptions->save(new Subscription(
            uuid: $existing->uuid,
            companyUuid: $existing->companyUuid,
            paidUntil: $existing->paidUntil,
            isSuspended: $existing->isSuspended,
            createdAt: $existing->createdAt,
            updatedAt: new DateTimeImmutable(),
            lastReminderType: $type,
            lastReminderForPaidUntil: $existing->paidUntil,
        ));
    }
}
