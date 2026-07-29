<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Contracts;

use BizHub\Bookkeeping\Entities\Account;
use BizHub\Bookkeeping\Enums\AccountType;

/**
 * Persistence contract for the chart of accounts. The only class in
 * this module allowed to touch DatabaseInterface directly.
 *
 * @package BizHub\Bookkeeping\Contracts
 */
interface AccountRepositoryInterface
{
    public function findByUuid(string $uuid): ?Account;

    public function findByCode(string $companyUuid, string $code): ?Account;

    /**
     * @return Account[]
     */
    public function findAllForCompany(string $companyUuid, ?AccountType $type = null, bool $onlyActive = true): array;

    public function existsAnyForCompany(string $companyUuid): bool;

    /**
     * @param Account[] $accounts
     */
    public function insertMany(array $accounts): void;

    public function save(Account $account): Account;
}
