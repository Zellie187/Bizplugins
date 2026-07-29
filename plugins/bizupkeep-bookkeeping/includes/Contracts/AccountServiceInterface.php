<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Contracts;

use BizHub\Bookkeeping\Entities\Account;
use BizHub\Bookkeeping\Enums\AccountType;
use BizHub\Bookkeeping\Enums\NormalBalance;

/**
 * Public API for a company's chart of accounts.
 *
 * @package BizHub\Bookkeeping\Contracts
 */
interface AccountServiceInterface
{
    /**
     * Seed the default chart of accounts for a company if it has none
     * yet. Idempotent - safe to call on every bookkeeping page load.
     */
    public function ensureSeeded(string $companyUuid): void;

    /**
     * @return Account[]
     */
    public function listAccounts(string $companyUuid, ?AccountType $type = null): array;

    /**
     * @return Account[]
     */
    public function listIncomeAccounts(string $companyUuid): array;

    /**
     * @return Account[]
     */
    public function listExpenseAccounts(string $companyUuid): array;

    /**
     * @throws \BizHub\Bookkeeping\Exceptions\AccountNotFoundException
     */
    public function getAccount(string $uuid): Account;

    /**
     * @throws \BizHub\Bookkeeping\Exceptions\AccountNotFoundException
     */
    public function getByCode(string $companyUuid, string $code): Account;

    /**
     * Staff/admin-only: create a custom account beyond the default
     * chart. Not exposed to clients (BOOKKEEPING_MANAGE only).
     */
    public function createCustomAccount(
        string $companyUuid,
        string $code,
        string $name,
        AccountType $type,
        NormalBalance $normalBalance
    ): Account;
}
