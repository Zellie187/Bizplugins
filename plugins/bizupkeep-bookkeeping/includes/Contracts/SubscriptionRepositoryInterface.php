<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Contracts;

use BizHub\Bookkeeping\Entities\Subscription;

/**
 * Persistence contract for company bookkeeping subscriptions. The only
 * class allowed to touch DatabaseInterface for this table.
 *
 * @package BizHub\Bookkeeping\Contracts
 */
interface SubscriptionRepositoryInterface
{
    public function findByCompanyUuid(string $companyUuid): ?Subscription;

    /**
     * Every subscription row, across every company. The table is one
     * row per company - small enough that the reminder job and the
     * staff overview page both fetch everything and filter/sort in
     * PHP rather than needing range-query support this table has no
     * use for otherwise.
     *
     * @return Subscription[]
     */
    public function findAll(): array;

    public function save(Subscription $subscription): Subscription;
}
