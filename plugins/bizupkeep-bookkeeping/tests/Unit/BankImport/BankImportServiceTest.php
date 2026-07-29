<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Tests\Unit\BankImport;

use BizHub\Bookkeeping\Accounts\AccountRepository;
use BizHub\Bookkeeping\Accounts\AccountService;
use BizHub\Bookkeeping\Accounts\ChartOfAccountsTemplate;
use BizHub\Bookkeeping\Accounts\CompanySettingsRepository;
use BizHub\Bookkeeping\BankImport\BankImportService;
use BizHub\Bookkeeping\BankImport\ImportMappingRepository;
use BizHub\Bookkeeping\BankImport\StagedTransactionRepository;
use BizHub\Bookkeeping\Billing\SubscriptionRepository;
use BizHub\Bookkeeping\Billing\SubscriptionService;
use BizHub\Bookkeeping\Entities\ImportMapping;
use BizHub\Bookkeeping\Enums\ImportAmountStyle;
use BizHub\Bookkeeping\Enums\StagedTransactionStatus;
use BizHub\Bookkeeping\Exceptions\SubscriptionInactiveException;
use BizHub\Bookkeeping\Export\CsvReader;
use BizHub\Bookkeeping\Ledger\JournalRepository;
use BizHub\Bookkeeping\Ledger\LedgerService;
use BizHub\Bookkeeping\Ledger\TransactionCaptureService;
use BizHub\Bookkeeping\Support\Money;
use BizHub\Bookkeeping\Tests\Mocks\InMemoryDatabase;
use BizHub\Bookkeeping\Tests\Mocks\InMemoryTransaction;
use BizHub\Framework\Support\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class BankImportServiceTest extends TestCase
{
    private const COMPANY = 'company-1';

    private AccountService $accountService;
    private SubscriptionService $subscriptionService;
    private StagedTransactionRepository $stagedRepository;
    private BankImportService $import;
    private string $bankAccountUuid;

    protected function setUp(): void
    {
        $database = new InMemoryDatabase();

        $accountRepository = new AccountRepository($database);
        $this->accountService = new AccountService($accountRepository);
        $this->accountService->ensureSeeded(self::COMPANY);
        $this->bankAccountUuid = $this->accountService
            ->getByCode(self::COMPANY, ChartOfAccountsTemplate::CODE_BANK_ACCOUNT)->uuid;

        $journalRepository = new JournalRepository($database, new InMemoryTransaction());
        $ledger = new LedgerService($journalRepository, $accountRepository);

        $this->subscriptionService = new SubscriptionService(new SubscriptionRepository($database));
        $this->subscriptionService->extend(self::COMPANY, 30);

        $capture = new TransactionCaptureService(
            $ledger,
            $this->accountService,
            $this->subscriptionService,
            new CompanySettingsRepository($database)
        );

        $this->stagedRepository = new StagedTransactionRepository($database);

        $this->import = new BankImportService(
            new ImportMappingRepository($database),
            $this->stagedRepository,
            $this->accountService,
            $capture,
            new CsvReader()
        );
    }

    private function signedMapping(): ImportMapping
    {
        return new ImportMapping(
            uuid: Uuid::generate(),
            companyUuid: self::COMPANY,
            dateColumn: 'Date',
            descriptionColumn: 'Description',
            amountStyle: ImportAmountStyle::Signed,
            amountColumn: 'Amount',
            debitColumn: null,
            creditColumn: null,
            dateFormat: 'd/m/Y',
            createdAt: new DateTimeImmutable(),
        );
    }

    private function debitCreditMapping(): ImportMapping
    {
        return new ImportMapping(
            uuid: Uuid::generate(),
            companyUuid: self::COMPANY,
            dateColumn: 'Date',
            descriptionColumn: 'Description',
            amountStyle: ImportAmountStyle::DebitCredit,
            amountColumn: null,
            debitColumn: 'Debit',
            creditColumn: 'Credit',
            dateFormat: 'd/m/Y',
            createdAt: new DateTimeImmutable(),
        );
    }

    public function testSignedStyleCsvProducesCorrectlySignedAmounts(): void
    {
        $csv = "Date,Description,Amount\n"
            . "01/04/2026,Client Invoice Payment,2000.00\n"
            . "05/04/2026,Office Rent,-800.00\n";

        $result = $this->import->import(self::COMPANY, $this->bankAccountUuid, $csv, $this->signedMapping());

        self::assertSame(2, $result->imported);
        self::assertSame(0, $result->duplicates);
        self::assertSame(0, $result->unparseable);

        $pending = $this->import->listPending(self::COMPANY);
        self::assertCount(2, $pending);

        $invoice = $this->findByDescription($pending, 'Client Invoice Payment');
        $rent = $this->findByDescription($pending, 'Office Rent');

        self::assertTrue($invoice->amount->equals(Money::fromRands(2000.00)));
        self::assertTrue($invoice->isIncomeShaped());

        self::assertTrue($rent->amount->equals(Money::fromRands(-800.00)));
        self::assertFalse($rent->isIncomeShaped());
    }

    public function testDebitCreditStyleCsvProducesCorrectlySignedAmounts(): void
    {
        $csv = "Date,Description,Debit,Credit\n"
            . "01/04/2026,Client Invoice Payment,,2000.00\n"
            . "05/04/2026,Office Rent,800.00,\n";

        $result = $this->import->import(self::COMPANY, $this->bankAccountUuid, $csv, $this->debitCreditMapping());

        self::assertSame(2, $result->imported);

        $pending = $this->import->listPending(self::COMPANY);
        $invoice = $this->findByDescription($pending, 'Client Invoice Payment');
        $rent = $this->findByDescription($pending, 'Office Rent');

        self::assertTrue($invoice->amount->equals(Money::fromRands(2000.00)));
        self::assertTrue($rent->amount->equals(Money::fromRands(-800.00)));
    }

    public function testReimportingTheIdenticalCsvStagesNoDuplicates(): void
    {
        $csv = "Date,Description,Amount\n01/04/2026,Client Invoice Payment,2000.00\n";
        $mapping = $this->signedMapping();

        $first = $this->import->import(self::COMPANY, $this->bankAccountUuid, $csv, $mapping);
        self::assertSame(1, $first->imported);

        $second = $this->import->import(self::COMPANY, $this->bankAccountUuid, $csv, $mapping);
        self::assertSame(0, $second->imported);
        self::assertSame(1, $second->duplicates);

        self::assertCount(1, $this->import->listPending(self::COMPANY));
    }

    public function testOneMalformedRowDoesNotBlockTheRestOfTheBatch(): void
    {
        $csv = "Date,Description,Amount\n"
            . "01/04/2026,Client Invoice Payment,2000.00\n"
            . "not-a-date,Bad Row,100.00\n"
            . "05/04/2026,Office Rent,-800.00\n";

        $result = $this->import->import(self::COMPANY, $this->bankAccountUuid, $csv, $this->signedMapping());

        self::assertSame(2, $result->imported);
        self::assertSame(1, $result->unparseable);
    }

    public function testCategorizePostsARealJournalEntryAndMarksTheStagedRowCategorized(): void
    {
        $csv = "Date,Description,Amount\n01/04/2026,Client Invoice Payment,2000.00\n";
        $this->import->import(self::COMPANY, $this->bankAccountUuid, $csv, $this->signedMapping());

        $staged = $this->import->listPending(self::COMPANY)[0];
        $salesAccount = $this->accountService->getByCode(self::COMPANY, ChartOfAccountsTemplate::CODE_SALES_REVENUE);

        $entry = $this->import->categorize(self::COMPANY, $staged->uuid, $salesAccount->uuid, actorId: 1);

        self::assertCount(2, $entry->lines);

        $updated = $this->stagedRepository->findByUuid($staged->uuid);
        self::assertNotNull($updated);
        self::assertSame(StagedTransactionStatus::Categorized, $updated->status);
        self::assertSame($entry->uuid, $updated->journalEntryUuid);
        self::assertSame($salesAccount->uuid, $updated->categoryAccountUuid);
    }

    public function testCategorizeThrowsWhenSubscriptionIsInactive(): void
    {
        $this->subscriptionService->suspend(self::COMPANY);

        $csv = "Date,Description,Amount\n01/04/2026,Client Invoice Payment,2000.00\n";
        // import() itself doesn't touch TransactionCaptureService, so it
        // still succeeds even while suspended - only categorize() (which
        // goes through TransactionCaptureService) should be blocked.
        $this->import->import(self::COMPANY, $this->bankAccountUuid, $csv, $this->signedMapping());
        $staged = $this->import->listPending(self::COMPANY)[0];

        $salesAccount = $this->accountService->getByCode(self::COMPANY, ChartOfAccountsTemplate::CODE_SALES_REVENUE);

        $this->expectException(SubscriptionInactiveException::class);

        $this->import->categorize(self::COMPANY, $staged->uuid, $salesAccount->uuid, actorId: 1);
    }

    public function testBulkCategorizeAppliesToMultipleRowsAndTolerantOfOneFailure(): void
    {
        $csv = "Date,Description,Amount\n"
            . "01/04/2026,Sale One,500.00\n"
            . "02/04/2026,Sale Two,600.00\n";
        $this->import->import(self::COMPANY, $this->bankAccountUuid, $csv, $this->signedMapping());

        $pending = $this->import->listPending(self::COMPANY);
        $salesAccount = $this->accountService->getByCode(self::COMPANY, ChartOfAccountsTemplate::CODE_SALES_REVENUE);

        $uuids = array_map(static fn ($s) => $s->uuid, $pending);
        $uuids[] = 'does-not-exist'; // should be tolerated, not fatal the batch

        $count = $this->import->bulkCategorize(self::COMPANY, $uuids, $salesAccount->uuid, actorId: 1);

        self::assertSame(2, $count);
        self::assertCount(0, $this->import->listPending(self::COMPANY));
    }

    public function testIgnoreNeverPosts(): void
    {
        $csv = "Date,Description,Amount\n01/04/2026,Internal Transfer,500.00\n";
        $this->import->import(self::COMPANY, $this->bankAccountUuid, $csv, $this->signedMapping());

        $staged = $this->import->listPending(self::COMPANY)[0];
        $this->import->ignore(self::COMPANY, $staged->uuid);

        $updated = $this->stagedRepository->findByUuid($staged->uuid);
        self::assertNotNull($updated);
        self::assertSame(StagedTransactionStatus::Ignored, $updated->status);
        self::assertNull($updated->journalEntryUuid);
        self::assertCount(0, $this->import->listPending(self::COMPANY));
    }

    /**
     * @param array<int,object> $rows
     */
    private function findByDescription(array $rows, string $description): object
    {
        foreach ($rows as $row) {
            if ($row->description === $description) {
                return $row;
            }
        }

        self::fail("No staged row found with description \"{$description}\"");
    }
}
