<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Tests\Feature;

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
use BizHub\Bookkeeping\Enums\JournalSource;
use BizHub\Bookkeeping\Enums\PaymentMethod;
use BizHub\Bookkeeping\Export\CsvWriter;
use BizHub\Bookkeeping\Export\QuickBooksOnlineExporter;
use BizHub\Bookkeeping\Exceptions\SubscriptionInactiveException;
use BizHub\Bookkeeping\Ledger\JournalRepository;
use BizHub\Bookkeeping\Ledger\LedgerService;
use BizHub\Bookkeeping\Ledger\TransactionCaptureService;
use BizHub\Bookkeeping\Reporting\FinancialStatementsService;
use BizHub\Bookkeeping\Support\Money;
use BizHub\Bookkeeping\Tests\Mocks\InMemoryDatabase;
use BizHub\Bookkeeping\Tests\Mocks\InMemoryTransaction;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * One end-to-end flow through every module built this round, wired
 * together exactly as BizHub's shared container would wire them at
 * runtime (each service constructed from the layer below it, not
 * mocked) - the closest thing to a full integration test this plugin
 * has without a real WordPress environment.
 */
final class EndToEndLedgerTest extends TestCase
{
    private const COMPANY = 'company-e2e';

    public function testFullLedgerLifecycle(): void
    {
        $database = new InMemoryDatabase();
        $accountRepository = new AccountRepository($database);
        $accountService = new AccountService($accountRepository);

        $journalRepository = new JournalRepository($database, new InMemoryTransaction());
        $ledger = new LedgerService($journalRepository, $accountRepository);
        $subscriptions = new SubscriptionService(new SubscriptionRepository($database));
        $capture = new TransactionCaptureService(
            $ledger,
            $accountService,
            $subscriptions,
            new CompanySettingsRepository($database)
        );
        $statements = new FinancialStatementsService($journalRepository, $accountRepository);

        // 1. Seed the default chart of accounts (idempotent - call twice).
        $accountService->ensureSeeded(self::COMPANY);
        $accountService->ensureSeeded(self::COMPANY);
        self::assertCount(count(ChartOfAccountsTemplate::defaultAccounts()), $accountService->listAccounts(self::COMPANY));

        $bank = $accountService->getByCode(self::COMPANY, ChartOfAccountsTemplate::CODE_BANK_ACCOUNT);
        $sales = $accountService->getByCode(self::COMPANY, ChartOfAccountsTemplate::CODE_SALES_REVENUE);

        // 1b. Capture is blocked before the company ever subscribes -
        // the exact scenario the subscription gate exists for.
        try {
            $capture->captureIncome(self::COMPANY, new CaptureTransactionData(
                date: new DateTimeImmutable('2026-04-01'),
                amount: Money::fromRands(1.00),
                categoryAccountUuid: $sales->uuid,
                paymentMethod: PaymentMethod::Bank,
                description: 'Should be blocked - no subscription yet',
            ), actorId: 2);
            self::fail('Expected SubscriptionInactiveException to be thrown.');
        } catch (SubscriptionInactiveException) {
            // expected
        }

        // Simulates a real client completing WooCommerce checkout for the
        // "Bookkeeping Monthly Access" product - see
        // bizupkeep_child_handle_bookkeeping_subscription_order_payment()
        // in the theme, which calls this same extend() on order completion.
        $subscriptions->extend(self::COMPANY, 30);
        self::assertTrue($subscriptions->isActive(self::COMPANY));

        $rent = $accountService->getByCode(self::COMPANY, ChartOfAccountsTemplate::CODE_RENT);
        $shareCapital = $accountService->getByCode(self::COMPANY, ChartOfAccountsTemplate::CODE_SHARE_CAPITAL);

        // 2. Opening balance via a manual entry (staff admin screen path).
        $ledger->postEntry(new ManualJournalEntryData(
            companyUuid: self::COMPANY,
            date: new DateTimeImmutable('2026-03-01'),
            description: 'Opening capital',
            lines: [
                JournalLineData::debit($bank->uuid, Money::fromRands(5000.00)),
                JournalLineData::credit($shareCapital->uuid, Money::fromRands(5000.00)),
            ],
            createdBy: 1,
        ), JournalSource::OpeningBalance);

        // 3. Client-facing capture (income + expense).
        $capture->captureIncome(self::COMPANY, new CaptureTransactionData(
            date: new DateTimeImmutable('2026-04-05'),
            amount: Money::fromRands(1500.00),
            categoryAccountUuid: $sales->uuid,
            paymentMethod: PaymentMethod::Bank,
            description: 'Invoice #1',
        ), actorId: 2);

        $rentEntry = $capture->captureExpense(self::COMPANY, new CaptureTransactionData(
            date: new DateTimeImmutable('2026-04-10'),
            amount: Money::fromRands(600.00),
            categoryAccountUuid: $rent->uuid,
            paymentMethod: PaymentMethod::Bank,
            description: 'April rent',
        ), actorId: 2);

        // 4. Correct a mistake via reversal (never a hard delete).
        self::assertNull($journalRepository->findReversalOf($rentEntry->uuid));
        $ledger->reverseEntry($rentEntry->uuid, actorId: 1, reason: 'Double-booked, correcting');
        self::assertNotNull($journalRepository->findReversalOf($rentEntry->uuid));

        // Re-capture the rent correctly after reversing the mistake.
        $capture->captureExpense(self::COMPANY, new CaptureTransactionData(
            date: new DateTimeImmutable('2026-04-10'),
            amount: Money::fromRands(600.00),
            categoryAccountUuid: $rent->uuid,
            paymentMethod: PaymentMethod::Bank,
            description: 'April rent (corrected)',
        ), actorId: 2);

        // 5. Statements still balance after all of the above.
        //
        // LedgerService::reverseEntry() dates the reversal at the real
        // wall-clock "now" it was performed (correct real-world
        // accounting behaviour - a correction is recorded when it's
        // actually made, not backdated to match the original mistake),
        // NOT '2026-04-10' like the entry it reverses. So every range
        // below deliberately extends its upper bound well past today
        // rather than stopping at '2026-04-30', or the reversal itself
        // would silently fall outside the query and this end-to-end
        // check would not actually exercise it.
        $wellIntoTheFuture = new DateTimeImmutable('2030-01-01');

        $trialBalance = $statements->trialBalance(self::COMPANY, DateRange::sinceInception($wellIntoTheFuture));
        self::assertTrue($trialBalance->isBalanced());

        $balanceSheet = $statements->balanceSheet(self::COMPANY, $wellIntoTheFuture);
        self::assertTrue($balanceSheet->isBalanced());

        $incomeStatement = $statements->incomeStatement(
            self::COMPANY,
            DateRange::between(new DateTimeImmutable('2026-03-01'), $wellIntoTheFuture)
        );
        // 1500 income - 600 rent (the reversal + re-capture net to exactly
        // one 600 rent charge, not zero or double) = 900 net income.
        self::assertTrue($incomeStatement->netIncome->equals(Money::fromRands(900.00)));

        // 6. Export still works end-to-end against the same data.
        $csv = (new QuickBooksOnlineExporter($journalRepository, $accountRepository, new CsvWriter()))
            ->exportJournalEntries(self::COMPANY, DateRange::sinceInception($wellIntoTheFuture));

        self::assertNotSame('', $csv);
        self::assertStringContainsString('JournalNo,JournalDate,AccountName,Debits,Credits,Description,Name', $csv);
    }
}
