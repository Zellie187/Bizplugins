<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Tests\Unit\Reporting;

use BizHub\Bookkeeping\Accounts\AccountRepository;
use BizHub\Bookkeeping\Accounts\AccountService;
use BizHub\Bookkeeping\Accounts\ChartOfAccountsTemplate;
use BizHub\Bookkeeping\Accounts\CompanySettingsRepository;
use BizHub\Bookkeeping\Billing\SubscriptionRepository;
use BizHub\Bookkeeping\Billing\SubscriptionService;
use BizHub\Bookkeeping\DTO\CaptureTransactionData;
use BizHub\Bookkeeping\DTO\DateRange;
use BizHub\Bookkeeping\DTO\JournalLineData;
use BizHub\Bookkeeping\DTO\ManualJournalEntryData;
use BizHub\Bookkeeping\Entities\CompanySettings;
use BizHub\Bookkeeping\Enums\JournalSource;
use BizHub\Bookkeeping\Enums\PaymentMethod;
use BizHub\Bookkeeping\Ledger\JournalRepository;
use BizHub\Bookkeeping\Ledger\LedgerService;
use BizHub\Bookkeeping\Ledger\TransactionCaptureService;
use BizHub\Bookkeeping\Reporting\FinancialStatementsService;
use BizHub\Bookkeeping\Support\Money;
use BizHub\Bookkeeping\Tests\Mocks\InMemoryDatabase;
use BizHub\Bookkeeping\Tests\Mocks\InMemoryTransaction;
use BizHub\Framework\Support\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class FinancialStatementsServiceTest extends TestCase
{
    private const COMPANY = 'company-1';

    private AccountService $accountService;
    private AccountRepository $accountRepository;
    private LedgerService $ledger;
    private TransactionCaptureService $capture;
    private FinancialStatementsService $statements;
    private CompanySettingsRepository $companySettings;

    protected function setUp(): void
    {
        $database = new InMemoryDatabase();
        $this->accountRepository = new AccountRepository($database);
        $this->accountService = new AccountService($this->accountRepository);
        $this->accountService->ensureSeeded(self::COMPANY);

        $journalRepository = new JournalRepository($database, new InMemoryTransaction());
        $this->ledger = new LedgerService($journalRepository, $this->accountRepository);

        $subscriptions = new SubscriptionService(new SubscriptionRepository($database));
        $subscriptions->extend(self::COMPANY, 30);

        $this->companySettings = new CompanySettingsRepository($database);

        $this->capture = new TransactionCaptureService(
            $this->ledger,
            $this->accountService,
            $subscriptions,
            $this->companySettings
        );
        $this->statements = new FinancialStatementsService($journalRepository, $this->accountRepository);
    }

    private function code(string $code): string
    {
        return $this->accountService->getByCode(self::COMPANY, $code)->uuid;
    }

    /**
     * Seeds a realistic mixed set of postings: opening capital, a cash
     * sale, an expense paid by bank, and a manual adjustment - enough
     * to meaningfully exercise all three statements at once.
     */
    private function seedMixedActivity(): void
    {
        // Opening: owner injects capital into the bank account.
        $this->ledger->postEntry(new ManualJournalEntryData(
            companyUuid: self::COMPANY,
            date: new DateTimeImmutable('2026-03-01'),
            description: 'Opening capital',
            lines: [
                JournalLineData::debit($this->code(ChartOfAccountsTemplate::CODE_BANK_ACCOUNT), Money::fromRands(10000.00)),
                JournalLineData::credit($this->code(ChartOfAccountsTemplate::CODE_SHARE_CAPITAL), Money::fromRands(10000.00)),
            ],
            createdBy: 1,
        ), JournalSource::OpeningBalance);

        // Income capture: a bank sale.
        $this->capture->captureIncome(self::COMPANY, new CaptureTransactionData(
            date: new DateTimeImmutable('2026-04-10'),
            amount: Money::fromRands(2000.00),
            categoryAccountUuid: $this->code(ChartOfAccountsTemplate::CODE_SALES_REVENUE),
            paymentMethod: PaymentMethod::Bank,
            description: 'Client invoice',
        ), actorId: 1);

        // Expense capture: rent paid by bank.
        $this->capture->captureExpense(self::COMPANY, new CaptureTransactionData(
            date: new DateTimeImmutable('2026-04-15'),
            amount: Money::fromRands(800.00),
            categoryAccountUuid: $this->code(ChartOfAccountsTemplate::CODE_RENT),
            paymentMethod: PaymentMethod::Bank,
            description: 'April rent',
        ), actorId: 1);
    }

    public function testTrialBalanceNetsToZero(): void
    {
        $this->seedMixedActivity();

        $report = $this->statements->trialBalance(self::COMPANY, DateRange::sinceInception(new DateTimeImmutable('2026-04-30')));

        self::assertTrue($report->isBalanced());
        self::assertNotEmpty($report->rows);
    }

    public function testTrialBalanceNetsToZeroEvenAfterAReversal(): void
    {
        $this->seedMixedActivity();

        $rentEntry = $this->capture->captureExpense(self::COMPANY, new CaptureTransactionData(
            date: new DateTimeImmutable('2026-04-20'),
            amount: Money::fromRands(300.00),
            categoryAccountUuid: $this->code(ChartOfAccountsTemplate::CODE_OFFICE_SUPPLIES),
            paymentMethod: PaymentMethod::Cash,
            description: 'Supplies - wrong amount',
        ), actorId: 1);

        $this->ledger->reverseEntry($rentEntry->uuid, actorId: 1, reason: 'Wrong amount, redo');

        $report = $this->statements->trialBalance(self::COMPANY, DateRange::sinceInception(new DateTimeImmutable('2026-04-30')));

        self::assertTrue($report->isBalanced());
    }

    public function testIncomeStatementNetsIncomeMinusExpenses(): void
    {
        $this->seedMixedActivity();

        $report = $this->statements->incomeStatement(
            self::COMPANY,
            DateRange::between(new DateTimeImmutable('2026-03-01'), new DateTimeImmutable('2026-04-30'))
        );

        self::assertTrue($report->totalIncome->equals(Money::fromRands(2000.00)));
        self::assertTrue($report->totalExpenses->equals(Money::fromRands(800.00)));
        self::assertTrue($report->netIncome->equals(Money::fromRands(1200.00)));
    }

    public function testBalanceSheetIdentityHolds(): void
    {
        $this->seedMixedActivity();

        $report = $this->statements->balanceSheet(self::COMPANY, new DateTimeImmutable('2026-04-30'));

        self::assertTrue($report->isBalanced());

        // Bank: 10000 (capital) + 2000 (sale) - 800 (rent) = 11200.
        self::assertTrue($report->totalAssets->equals(Money::fromRands(11200.00)));
        self::assertTrue($report->currentYearEarnings->equals(Money::fromRands(1200.00)));
    }

    public function testBalanceSheetFiscalYearIsMarchToFebruary(): void
    {
        // A posting from the PRIOR fiscal year (Jan 2026, before March 1)
        // must not count toward the fiscal-year-to-date current year earnings
        // as of an April 2026 balance sheet date.
        $this->ledger->postEntry(new ManualJournalEntryData(
            companyUuid: self::COMPANY,
            date: new DateTimeImmutable('2026-01-15'),
            description: 'Prior fiscal year sale',
            lines: [
                JournalLineData::debit($this->code(ChartOfAccountsTemplate::CODE_BANK_ACCOUNT), Money::fromRands(5000.00)),
                JournalLineData::credit($this->code(ChartOfAccountsTemplate::CODE_SALES_REVENUE), Money::fromRands(5000.00)),
            ],
            createdBy: 1,
        ), JournalSource::Manual);

        $this->seedMixedActivity();

        $report = $this->statements->balanceSheet(self::COMPANY, new DateTimeImmutable('2026-04-30'));

        // Only the mixed-activity net income (1200), not the prior-year 5000 sale.
        self::assertTrue($report->currentYearEarnings->equals(Money::fromRands(1200.00)));
        self::assertTrue($report->isBalanced());
    }

    public function testVatSummaryIsZeroWhenNoVatPostingsExist(): void
    {
        $this->seedMixedActivity();

        $report = $this->statements->vatSummary(
            self::COMPANY,
            DateRange::between(new DateTimeImmutable('2026-03-01'), new DateTimeImmutable('2026-04-30'))
        );

        self::assertTrue($report->outputVat->isZero());
        self::assertTrue($report->inputVat->isZero());
        self::assertTrue($report->netVatPayable->isZero());
    }

    public function testVatSummaryNetsOutputMinusInputAcrossVatAndNonVatPostings(): void
    {
        $this->companySettings->save(new CompanySettings(
            uuid: Uuid::generate(),
            companyUuid: self::COMPANY,
            isVatRegistered: true,
            vatNumber: '4123456789',
            createdAt: new DateTimeImmutable(),
        ));

        $this->seedMixedActivity();

        // R115.00 VAT-inclusive sale: R100.00 net, R15.00 Output VAT.
        $this->capture->captureIncome(self::COMPANY, new CaptureTransactionData(
            date: new DateTimeImmutable('2026-04-11'),
            amount: Money::fromRands(115.00),
            categoryAccountUuid: $this->code(ChartOfAccountsTemplate::CODE_SALES_REVENUE),
            paymentMethod: PaymentMethod::Bank,
            description: 'VAT invoice',
            includesVat: true,
        ), actorId: 1);

        // R57.50 VAT-inclusive expense: R50.00 net, R7.50 Input VAT.
        $this->capture->captureExpense(self::COMPANY, new CaptureTransactionData(
            date: new DateTimeImmutable('2026-04-16'),
            amount: Money::fromRands(57.50),
            categoryAccountUuid: $this->code(ChartOfAccountsTemplate::CODE_OFFICE_SUPPLIES),
            paymentMethod: PaymentMethod::Bank,
            description: 'VAT expense',
            includesVat: true,
        ), actorId: 1);

        $report = $this->statements->vatSummary(
            self::COMPANY,
            DateRange::between(new DateTimeImmutable('2026-03-01'), new DateTimeImmutable('2026-04-30'))
        );

        self::assertTrue($report->outputVat->equals(Money::fromRands(15.00)));
        self::assertTrue($report->inputVat->equals(Money::fromRands(7.50)));
        self::assertTrue($report->netVatPayable->equals(Money::fromRands(7.50)));
    }
}
