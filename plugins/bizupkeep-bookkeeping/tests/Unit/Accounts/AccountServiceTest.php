<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Tests\Unit\Accounts;

use BizHub\Bookkeeping\Accounts\AccountRepository;
use BizHub\Bookkeeping\Accounts\AccountService;
use BizHub\Bookkeeping\Accounts\ChartOfAccountsTemplate;
use BizHub\Bookkeeping\Enums\AccountType;
use BizHub\Bookkeeping\Enums\NormalBalance;
use BizHub\Bookkeeping\Exceptions\AccountNotFoundException;
use BizHub\Bookkeeping\Tests\Mocks\InMemoryDatabase;
use PHPUnit\Framework\TestCase;

final class AccountServiceTest extends TestCase
{
    private InMemoryDatabase $database;
    private AccountService $service;

    protected function setUp(): void
    {
        $this->database = new InMemoryDatabase();
        $this->service = new AccountService(new AccountRepository($this->database));
    }

    public function testEnsureSeededInsertsTheFullDefaultChart(): void
    {
        $this->service->ensureSeeded('company-1');

        $accounts = $this->service->listAccounts('company-1');

        self::assertCount(count(ChartOfAccountsTemplate::defaultAccounts()), $accounts);
    }

    public function testEnsureSeededIsIdempotent(): void
    {
        $this->service->ensureSeeded('company-1');
        $this->service->ensureSeeded('company-1');
        $this->service->ensureSeeded('company-1');

        self::assertCount(
            count(ChartOfAccountsTemplate::defaultAccounts()),
            $this->service->listAccounts('company-1')
        );
    }

    public function testSeedingIsScopedPerCompany(): void
    {
        $this->service->ensureSeeded('company-1');
        $this->service->ensureSeeded('company-2');

        self::assertCount(count(ChartOfAccountsTemplate::defaultAccounts()), $this->service->listAccounts('company-1'));
        self::assertCount(count(ChartOfAccountsTemplate::defaultAccounts()), $this->service->listAccounts('company-2'));
    }

    public function testListIncomeAndExpenseAccountsAreFilteredByType(): void
    {
        $this->service->ensureSeeded('company-1');

        $income = $this->service->listIncomeAccounts('company-1');
        $expense = $this->service->listExpenseAccounts('company-1');

        self::assertNotEmpty($income);
        self::assertNotEmpty($expense);

        foreach ($income as $account) {
            self::assertSame(AccountType::Income, $account->type);
        }

        foreach ($expense as $account) {
            self::assertSame(AccountType::Expense, $account->type);
        }
    }

    public function testGetByCodeResolvesTheBankAccount(): void
    {
        $this->service->ensureSeeded('company-1');

        $account = $this->service->getByCode('company-1', ChartOfAccountsTemplate::CODE_BANK_ACCOUNT);

        self::assertSame('Bank Account', $account->name);
        self::assertSame(NormalBalance::Debit, $account->normalBalance);
    }

    public function testGetAccountThrowsWhenNotFound(): void
    {
        $this->expectException(AccountNotFoundException::class);

        $this->service->getAccount('does-not-exist');
    }

    public function testCreateCustomAccountRejectsDuplicateCode(): void
    {
        $this->service->ensureSeeded('company-1');

        $this->expectException(\BizHub\Bookkeeping\Exceptions\ValidationException::class);

        $this->service->createCustomAccount(
            'company-1',
            ChartOfAccountsTemplate::CODE_BANK_ACCOUNT,
            'Duplicate',
            AccountType::Asset,
            NormalBalance::Debit
        );
    }

    public function testCreateCustomAccountSucceedsWithANewCode(): void
    {
        $account = $this->service->createCustomAccount(
            'company-1',
            '9999',
            'Petty Cash Float',
            AccountType::Asset,
            NormalBalance::Debit
        );

        self::assertSame('9999', $account->code);
        self::assertFalse($account->isSystem);
    }
}
