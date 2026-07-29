<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Tests\Unit\Export;

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
use BizHub\Bookkeeping\Export\SageExporter;
use BizHub\Bookkeeping\Export\XeroExporter;
use BizHub\Bookkeeping\Ledger\JournalRepository;
use BizHub\Bookkeeping\Ledger\LedgerService;
use BizHub\Bookkeeping\Ledger\TransactionCaptureService;
use BizHub\Bookkeeping\Support\Money;
use BizHub\Bookkeeping\Tests\Mocks\InMemoryDatabase;
use BizHub\Bookkeeping\Tests\Mocks\InMemoryTransaction;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ExportersTest extends TestCase
{
    private const COMPANY = 'company-1';

    private AccountRepository $accountRepository;
    private AccountService $accountService;
    private JournalRepository $journalRepository;
    private LedgerService $ledger;
    private TransactionCaptureService $capture;

    protected function setUp(): void
    {
        $database = new InMemoryDatabase();
        $this->accountRepository = new AccountRepository($database);
        $this->accountService = new AccountService($this->accountRepository);
        $this->accountService->ensureSeeded(self::COMPANY);

        $this->journalRepository = new JournalRepository($database, new InMemoryTransaction());
        $this->ledger = new LedgerService($this->journalRepository, $this->accountRepository);

        $subscriptions = new SubscriptionService(new SubscriptionRepository($database));
        $subscriptions->extend(self::COMPANY, 30);

        $this->capture = new TransactionCaptureService(
            $this->ledger,
            $this->accountService,
            $subscriptions,
            new CompanySettingsRepository($database)
        );
    }

    private function seedActivity(): void
    {
        // Bank-touching income capture.
        $this->capture->captureIncome(self::COMPANY, new CaptureTransactionData(
            date: new DateTimeImmutable('2026-04-10'),
            amount: Money::fromRands(2000.00),
            categoryAccountUuid: $this->accountService->getByCode(self::COMPANY, ChartOfAccountsTemplate::CODE_SALES_REVENUE)->uuid,
            paymentMethod: PaymentMethod::Bank,
            description: 'Client invoice',
        ), actorId: 1);

        // Bank-touching expense capture.
        $this->capture->captureExpense(self::COMPANY, new CaptureTransactionData(
            date: new DateTimeImmutable('2026-04-15'),
            amount: Money::fromRands(800.00),
            categoryAccountUuid: $this->accountService->getByCode(self::COMPANY, ChartOfAccountsTemplate::CODE_RENT)->uuid,
            paymentMethod: PaymentMethod::Bank,
            description: 'April rent',
        ), actorId: 1);

        // A manual entry with NO bank/cash leg at all (e.g. depreciation) -
        // must be excluded from the Xero/Sage bank-statement-style exports.
        $this->ledger->postEntry(new ManualJournalEntryData(
            companyUuid: self::COMPANY,
            date: new DateTimeImmutable('2026-04-20'),
            description: 'Monthly depreciation',
            lines: [
                JournalLineData::debit(
                    $this->accountService->getByCode(self::COMPANY, ChartOfAccountsTemplate::CODE_DEPRECIATION)->uuid,
                    Money::fromRands(100.00)
                ),
                JournalLineData::credit(
                    $this->accountService->getByCode(self::COMPANY, ChartOfAccountsTemplate::CODE_ACCUMULATED_DEPRECIATION)->uuid,
                    Money::fromRands(100.00)
                ),
            ],
            createdBy: 1,
        ), JournalSource::Manual);
    }

    private function range(): DateRange
    {
        return DateRange::between(new DateTimeImmutable('2026-04-01'), new DateTimeImmutable('2026-04-30'));
    }

    public function testQuickBooksExportIncludesEveryLineOfEveryEntry(): void
    {
        $this->seedActivity();

        $exporter = new QuickBooksOnlineExporter($this->journalRepository, $this->accountRepository, new CsvWriter());
        $csv = $exporter->exportJournalEntries(self::COMPANY, $this->range());

        $rows = $this->parseCsv($csv);

        self::assertSame(['JournalNo', 'JournalDate', 'AccountName', 'Debits', 'Credits', 'Description', 'Name'], $rows[0]);

        $descriptions = array_column(array_slice($rows, 1), 5);
        // The depreciation entry, which has no bank/cash leg, IS included
        // here (unlike Xero/Sage) - QBO's journal import is a full-GL format.
        self::assertContains('Client invoice', $descriptions);
        self::assertContains('April rent', $descriptions);
        self::assertContains('Monthly depreciation', $descriptions);

        $invoiceLine = $this->findRow($rows, static fn (array $r): bool => $r[5] === 'Client invoice' && $r[3] === '2000.00');
        self::assertNotNull($invoiceLine);
        self::assertSame('04/10/2026', $invoiceLine[1]);

        // 3 entries x 2 lines = 6 data rows + 1 header row.
        self::assertCount(7, $rows);
    }

    public function testXeroExportOnlyIncludesBankTouchingEntriesAsNetSignedAmount(): void
    {
        $this->seedActivity();

        $exporter = new XeroExporter($this->journalRepository, $this->accountRepository, new CsvWriter());
        $rows = $this->parseCsv($exporter->exportJournalEntries(self::COMPANY, $this->range()));

        self::assertSame(['Date', 'Amount', 'Payee', 'Description'], $rows[0]);
        self::assertContains(['10/04/2026', '2000.00', '', 'Client invoice'], $rows);
        self::assertContains(['15/04/2026', '-800.00', '', 'April rent'], $rows);

        $descriptions = array_column(array_slice($rows, 1), 3);
        self::assertNotContains('Monthly depreciation', $descriptions);

        // 2 bank-touching entries + 1 header row.
        self::assertCount(3, $rows);
    }

    public function testSageExportUsesDescriptionBeforeAmountAndDdMmYyyyDates(): void
    {
        $this->seedActivity();

        $exporter = new SageExporter($this->journalRepository, $this->accountRepository, new CsvWriter());
        $rows = $this->parseCsv($exporter->exportJournalEntries(self::COMPANY, $this->range()));

        self::assertSame(['Date', 'Description', 'Amount'], $rows[0]);
        self::assertContains(['10/04/2026', 'Client invoice', '2000.00'], $rows);
        self::assertContains(['15/04/2026', 'April rent', '-800.00'], $rows);

        $descriptions = array_column(array_slice($rows, 1), 1);
        self::assertNotContains('Monthly depreciation', $descriptions);
    }

    public function testPlatformKeysAndLabelsAreDistinct(): void
    {
        $qbo = new QuickBooksOnlineExporter($this->journalRepository, $this->accountRepository, new CsvWriter());
        $xero = new XeroExporter($this->journalRepository, $this->accountRepository, new CsvWriter());
        $sage = new SageExporter($this->journalRepository, $this->accountRepository, new CsvWriter());

        $keys = [$qbo->platformKey(), $xero->platformKey(), $sage->platformKey()];

        self::assertSame(['quickbooks', 'xero', 'sage'], $keys);
        self::assertCount(3, array_unique($keys));
    }

    /**
     * Parses CSV content via str_getcsv rather than raw string matching -
     * PHP's fputcsv quoting of individual fields varies across PHP
     * versions/builds (some quote any field containing whitespace, some
     * only quote when strictly necessary per RFC 4180), and both are
     * equally valid, importable CSV. Asserting on parsed rows keeps these
     * tests correct regardless of that formatting difference.
     *
     * @return array<int,array<int,string>>
     */
    private function parseCsv(string $csv): array
    {
        $lines = array_filter(explode("\n", str_replace("\r\n", "\n", trim($csv))));

        return array_map(static fn (string $line): array => str_getcsv($line, ',', '"', '\\'), $lines);
    }

    /**
     * @param array<int,array<int,string>> $rows
     * @param callable(array<int,string>):bool $predicate
     * @return array<int,string>|null
     */
    private function findRow(array $rows, callable $predicate): ?array
    {
        foreach ($rows as $row) {
            if ($predicate($row)) {
                return $row;
            }
        }

        return null;
    }
}
