<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Accounts;

use BizHub\Bookkeeping\Contracts\AccountRepositoryInterface;
use BizHub\Bookkeeping\Contracts\AccountServiceInterface;
use BizHub\Bookkeeping\Entities\Account;
use BizHub\Bookkeeping\Enums\AccountType;
use BizHub\Bookkeeping\Enums\NormalBalance;
use BizHub\Bookkeeping\Exceptions\AccountNotFoundException;
use BizHub\Bookkeeping\Exceptions\ValidationException;
use BizHub\Framework\Support\Uuid;
use DateTimeImmutable;

/**
 * Public API for a company's chart of accounts.
 *
 * @package BizHub\Bookkeeping\Accounts
 */
final class AccountService implements AccountServiceInterface
{
    public function __construct(
        private readonly AccountRepositoryInterface $accounts
    ) {
    }

    public function ensureSeeded(string $companyUuid): void
    {
        if ($this->accounts->existsAnyForCompany($companyUuid)) {
            return;
        }

        $now = new DateTimeImmutable();

        $entities = array_map(
            static fn (array $row): Account => new Account(
                uuid: Uuid::generate(),
                companyUuid: $companyUuid,
                code: $row['code'],
                name: $row['name'],
                type: $row['type'],
                normalBalance: $row['normalBalance'],
                isSystem: true,
                isActive: true,
                createdAt: $now,
            ),
            ChartOfAccountsTemplate::defaultAccounts()
        );

        $this->accounts->insertMany($entities);
    }

    public function listAccounts(string $companyUuid, ?AccountType $type = null): array
    {
        return $this->accounts->findAllForCompany($companyUuid, $type);
    }

    public function listIncomeAccounts(string $companyUuid): array
    {
        return $this->listAccounts($companyUuid, AccountType::Income);
    }

    public function listExpenseAccounts(string $companyUuid): array
    {
        return $this->listAccounts($companyUuid, AccountType::Expense);
    }

    public function getAccount(string $uuid): Account
    {
        return $this->accounts->findByUuid($uuid) ?? throw AccountNotFoundException::withUuid($uuid);
    }

    public function getByCode(string $companyUuid, string $code): Account
    {
        return $this->accounts->findByCode($companyUuid, $code)
            ?? throw AccountNotFoundException::withCode($companyUuid, $code);
    }

    public function createCustomAccount(
        string $companyUuid,
        string $code,
        string $name,
        AccountType $type,
        NormalBalance $normalBalance
    ): Account {
        if ($this->accounts->findByCode($companyUuid, $code) !== null) {
            throw new ValidationException(sprintf('Account code "%s" already exists for this company.', $code));
        }

        $account = new Account(
            uuid: Uuid::generate(),
            companyUuid: $companyUuid,
            code: $code,
            name: $name,
            type: $type,
            normalBalance: $normalBalance,
            isSystem: false,
            isActive: true,
            createdAt: new DateTimeImmutable(),
        );

        return $this->accounts->save($account);
    }
}
