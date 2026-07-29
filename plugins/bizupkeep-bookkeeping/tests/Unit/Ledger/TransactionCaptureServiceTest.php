<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Tests\Unit\Ledger;

use BizHub\Bookkeeping\Accounts\AccountRepository;
use BizHub\Bookkeeping\Accounts\AccountService;
use BizHub\Bookkeeping\Accounts\ChartOfAccountsTemplate;
use BizHub\Bookkeeping\Accounts\CompanySettingsRepository;
use BizHub\Bookkeeping\Billing\SubscriptionRepository;
use BizHub\Bookkeeping\Billing\SubscriptionService;
use BizHub\Bookkeeping\DTO\CaptureTransactionData;
use BizHub\Bookkeeping\Entities\CompanySettings;
use BizHub\Bookkeeping\Enums\JournalSource;
use BizHub\Bookkeeping\Enums\PaymentMethod;
use BizHub\Bookkeeping\Exceptions\SubscriptionInactiveException;
use BizHub\Bookkeeping\Exceptions\ValidationException;
use BizHub\Bookkeeping\Ledger\JournalRepository;
use BizHub\Bookkeeping\Ledger\LedgerService;
use BizHub\Bookkeeping\Ledger\TransactionCaptureService;
use BizHub\Bookkeeping\Support\Money;
use BizHub\Bookkeeping\Tests\Mocks\InMemoryDatabase;
use BizHub\Bookkeeping\Tests\Mocks\InMemoryTransaction;
use BizHub\Framework\Support\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class TransactionCaptureServiceTest extends TestCase
{
    private const COMPANY = 'company-1';

    private AccountService $accountService;
    private SubscriptionService $subscriptions;
    private CompanySettingsRepository $companySettings;
    private TransactionCaptureService $capture;

    protected function setUp(): void
    {
        $database = new InMemoryDatabase();
        $accountRepository = new AccountRepository($database);
        $this->accountService = new AccountService($accountRepository);
        $this->accountService->ensureSeeded(self::COMPANY);

        $journalRepository = new JournalRepository($database, new InMemoryTransaction());
        $ledger = new LedgerService($journalRepository, $accountRepository);

        $this->subscriptions = new SubscriptionService(new SubscriptionRepository($database));
        $this->subscriptions->extend(self::COMPANY, 30);

        $this->companySettings = new CompanySettingsRepository($database);

        $this->capture = new TransactionCaptureService(
            $ledger,
            $this->accountService,
            $this->subscriptions,
            $this->companySettings
        );
    }

    private function markVatRegistered(string $companyUuid): void
    {
        $this->companySettings->save(new CompanySettings(
            uuid: Uuid::generate(),
            companyUuid: $companyUuid,
            isVatRegistered: true,
            vatNumber: '4123456789',
            createdAt: new DateTimeImmutable(),
        ));
    }

    public function testCaptureIncomeViaBankDebitsBankAndCreditsCategory(): void
    {
        $salesAccount = $this->accountService->getByCode(self::COMPANY, ChartOfAccountsTemplate::CODE_SALES_REVENUE);

        $entry = $this->capture->captureIncome(self::COMPANY, new CaptureTransactionData(
            date: new DateTimeImmutable('2026-04-01'),
            amount: Money::fromRands(500.00),
            categoryAccountUuid: $salesAccount->uuid,
            paymentMethod: PaymentMethod::Bank,
            description: 'Client invoice paid',
        ), actorId: 1);

        self::assertSame(JournalSource::CaptureIncome, $entry->source);
        self::assertCount(2, $entry->lines);

        $bankAccount = $this->accountService->getByCode(self::COMPANY, ChartOfAccountsTemplate::CODE_BANK_ACCOUNT);

        $bankLine = $this->lineFor($entry, $bankAccount->uuid);
        $salesLine = $this->lineFor($entry, $salesAccount->uuid);

        self::assertTrue($bankLine->debit->equals(Money::fromRands(500.00)));
        self::assertTrue($salesLine->credit->equals(Money::fromRands(500.00)));
    }

    public function testCaptureExpenseViaCashDebitsCategoryAndCreditsCash(): void
    {
        $rentAccount = $this->accountService->getByCode(self::COMPANY, ChartOfAccountsTemplate::CODE_RENT);

        $entry = $this->capture->captureExpense(self::COMPANY, new CaptureTransactionData(
            date: new DateTimeImmutable('2026-04-01'),
            amount: Money::fromRands(750.00),
            categoryAccountUuid: $rentAccount->uuid,
            paymentMethod: PaymentMethod::Cash,
            description: 'Office rent',
        ), actorId: 1);

        self::assertSame(JournalSource::CaptureExpense, $entry->source);

        $cashAccount = $this->accountService->getByCode(self::COMPANY, ChartOfAccountsTemplate::CODE_CASH_ON_HAND);

        $rentLine = $this->lineFor($entry, $rentAccount->uuid);
        $cashLine = $this->lineFor($entry, $cashAccount->uuid);

        self::assertTrue($rentLine->debit->equals(Money::fromRands(750.00)));
        self::assertTrue($cashLine->credit->equals(Money::fromRands(750.00)));
    }

    public function testZeroAmountIsRejected(): void
    {
        $salesAccount = $this->accountService->getByCode(self::COMPANY, ChartOfAccountsTemplate::CODE_SALES_REVENUE);

        $this->expectException(ValidationException::class);

        $this->capture->captureIncome(self::COMPANY, new CaptureTransactionData(
            date: new DateTimeImmutable('2026-04-01'),
            amount: Money::zero(),
            categoryAccountUuid: $salesAccount->uuid,
            paymentMethod: PaymentMethod::Bank,
            description: 'Nothing',
        ), actorId: 1);
    }

    public function testCaptureIsRejectedWhenSubscriptionInactive(): void
    {
        $salesAccount = $this->accountService->getByCode(self::COMPANY, ChartOfAccountsTemplate::CODE_SALES_REVENUE);

        // Undo the active subscription set up in setUp() by suspending it.
        $this->subscriptions->suspend(self::COMPANY);

        $this->expectException(SubscriptionInactiveException::class);

        $this->capture->captureIncome(self::COMPANY, new CaptureTransactionData(
            date: new DateTimeImmutable('2026-04-01'),
            amount: Money::fromRands(100.00),
            categoryAccountUuid: $salesAccount->uuid,
            paymentMethod: PaymentMethod::Bank,
            description: 'Should be blocked',
        ), actorId: 1);
    }

    public function testCaptureSucceedsAgainAfterReactivation(): void
    {
        $salesAccount = $this->accountService->getByCode(self::COMPANY, ChartOfAccountsTemplate::CODE_SALES_REVENUE);

        $this->subscriptions->suspend(self::COMPANY);
        $this->subscriptions->reactivate(self::COMPANY);

        $entry = $this->capture->captureIncome(self::COMPANY, new CaptureTransactionData(
            date: new DateTimeImmutable('2026-04-01'),
            amount: Money::fromRands(100.00),
            categoryAccountUuid: $salesAccount->uuid,
            paymentMethod: PaymentMethod::Bank,
            description: 'Should succeed once reactivated',
        ), actorId: 1);

        self::assertSame(JournalSource::CaptureIncome, $entry->source);
    }

    public function testCaptureIsRejectedForACompanyThatHasNeverSubscribed(): void
    {
        $otherCompany = 'company-never-subscribed';
        $this->accountService->ensureSeeded($otherCompany);
        $salesAccount = $this->accountService->getByCode($otherCompany, ChartOfAccountsTemplate::CODE_SALES_REVENUE);

        $this->expectException(SubscriptionInactiveException::class);

        $this->capture->captureExpense($otherCompany, new CaptureTransactionData(
            date: new DateTimeImmutable('2026-04-01'),
            amount: Money::fromRands(100.00),
            categoryAccountUuid: $salesAccount->uuid,
            paymentMethod: PaymentMethod::Bank,
            description: 'Should be blocked - no subscription row at all',
        ), actorId: 1);
    }

    public function testCaptureIncomeWithVatSplitsOutNetAndVatOutput(): void
    {
        $this->markVatRegistered(self::COMPANY);
        $salesAccount = $this->accountService->getByCode(self::COMPANY, ChartOfAccountsTemplate::CODE_SALES_REVENUE);

        $entry = $this->capture->captureIncome(self::COMPANY, new CaptureTransactionData(
            date: new DateTimeImmutable('2026-04-01'),
            amount: Money::fromRands(115.00),
            categoryAccountUuid: $salesAccount->uuid,
            paymentMethod: PaymentMethod::Bank,
            description: 'VAT invoice paid',
            includesVat: true,
        ), actorId: 1);

        self::assertCount(3, $entry->lines);

        $bankAccount = $this->accountService->getByCode(self::COMPANY, ChartOfAccountsTemplate::CODE_BANK_ACCOUNT);
        $vatOutputAccount = $this->accountService->getByCode(self::COMPANY, ChartOfAccountsTemplate::CODE_VAT_OUTPUT);

        self::assertTrue($this->lineFor($entry, $bankAccount->uuid)->debit->equals(Money::fromRands(115.00)));
        self::assertTrue($this->lineFor($entry, $salesAccount->uuid)->credit->equals(Money::fromRands(100.00)));
        self::assertTrue($this->lineFor($entry, $vatOutputAccount->uuid)->credit->equals(Money::fromRands(15.00)));
    }

    public function testCaptureExpenseWithVatSplitsOutNetAndVatInput(): void
    {
        $this->markVatRegistered(self::COMPANY);
        $rentAccount = $this->accountService->getByCode(self::COMPANY, ChartOfAccountsTemplate::CODE_RENT);

        $entry = $this->capture->captureExpense(self::COMPANY, new CaptureTransactionData(
            date: new DateTimeImmutable('2026-04-01'),
            amount: Money::fromRands(115.00),
            categoryAccountUuid: $rentAccount->uuid,
            paymentMethod: PaymentMethod::Bank,
            description: 'Rent incl. VAT',
            includesVat: true,
        ), actorId: 1);

        self::assertCount(3, $entry->lines);

        $bankAccount = $this->accountService->getByCode(self::COMPANY, ChartOfAccountsTemplate::CODE_BANK_ACCOUNT);
        $vatInputAccount = $this->accountService->getByCode(self::COMPANY, ChartOfAccountsTemplate::CODE_VAT_INPUT);

        self::assertTrue($this->lineFor($entry, $rentAccount->uuid)->debit->equals(Money::fromRands(100.00)));
        self::assertTrue($this->lineFor($entry, $vatInputAccount->uuid)->debit->equals(Money::fromRands(15.00)));
        self::assertTrue($this->lineFor($entry, $bankAccount->uuid)->credit->equals(Money::fromRands(115.00)));
    }

    public function testCaptureWithVatIsRejectedWhenCompanyIsNotVatRegistered(): void
    {
        $salesAccount = $this->accountService->getByCode(self::COMPANY, ChartOfAccountsTemplate::CODE_SALES_REVENUE);

        $this->expectException(ValidationException::class);

        $this->capture->captureIncome(self::COMPANY, new CaptureTransactionData(
            date: new DateTimeImmutable('2026-04-01'),
            amount: Money::fromRands(115.00),
            categoryAccountUuid: $salesAccount->uuid,
            paymentMethod: PaymentMethod::Bank,
            description: 'Should be blocked - not VAT registered',
            includesVat: true,
        ), actorId: 1);
    }

    public function testCaptureWithVatFallsBackToATwoLineEntryWhenTheVatPortionRoundsToZero(): void
    {
        $this->markVatRegistered(self::COMPANY);
        $salesAccount = $this->accountService->getByCode(self::COMPANY, ChartOfAccountsTemplate::CODE_SALES_REVENUE);

        $entry = $this->capture->captureIncome(self::COMPANY, new CaptureTransactionData(
            date: new DateTimeImmutable('2026-04-01'),
            amount: Money::fromRands(0.03),
            categoryAccountUuid: $salesAccount->uuid,
            paymentMethod: PaymentMethod::Bank,
            description: 'Sub-cent VAT rounds to zero',
            includesVat: true,
        ), actorId: 1);

        self::assertCount(2, $entry->lines);
    }

    private function lineFor($entry, string $accountUuid)
    {
        foreach ($entry->lines as $line) {
            if ($line->accountUuid === $accountUuid) {
                return $line;
            }
        }

        self::fail("No line found for account {$accountUuid}");
    }
}
