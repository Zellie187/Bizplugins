<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Contracts;

use BizHub\Bookkeeping\Entities\Subscription;

/**
 * Public API for a company's monthly bookkeeping-access subscription -
 * the gate TransactionCaptureService and the theme's export handler
 * both check before allowing a paid action.
 *
 * @package BizHub\Bookkeeping\Contracts
 */
interface SubscriptionServiceInterface
{
    public function isActive(string $companyUuid): bool;

    /**
     * Lazily create a (never-paid) subscription record for a company on
     * first touch, so every company always has a queryable row.
     * Idempotent.
     */
    public function getOrCreate(string $companyUuid): Subscription;

    /**
     * Record a successful payment: extends from the LATER of "today" or
     * the current paid_until (so an early renewal never discards
     * already-paid time), and always clears any manual suspension - a
     * real payment should restore access regardless of a prior
     * non-payment suspension.
     */
    public function extend(string $companyUuid, int $days = 30): Subscription;

    /**
     * Staff-only manual override: force inactive regardless of
     * paid_until.
     */
    public function suspend(string $companyUuid): Subscription;

    /**
     * Staff-only manual override: clear a suspension without changing
     * paid_until.
     */
    public function reactivate(string $companyUuid): Subscription;

    /**
     * Record that a reminder of a given kind was sent for the
     * subscription's CURRENT paid_until - called by
     * SubscriptionReminderService after successfully queuing a
     * reminder, so the same (type, paid_until) pair is never reminded
     * twice. A subsequent renewal naturally re-opens eligibility since
     * paid_until will have changed.
     */
    public function markReminderSent(string $companyUuid, string $type): Subscription;
}
