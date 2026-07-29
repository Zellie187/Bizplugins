<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Tests\Unit\BankImport;

use BizHub\Bookkeeping\BankImport\ImportMappingRepository;
use BizHub\Bookkeeping\BankImport\StagedTransactionRepository;
use BizHub\Bookkeeping\Entities\ImportMapping;
use BizHub\Bookkeeping\Entities\StagedTransaction;
use BizHub\Bookkeeping\Enums\ImportAmountStyle;
use BizHub\Bookkeeping\Enums\StagedTransactionStatus;
use BizHub\Bookkeeping\Support\Money;
use BizHub\Bookkeeping\Tests\Mocks\InMemoryDatabase;
use BizHub\Framework\Support\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RepositoriesTest extends TestCase
{
    private const COMPANY = 'company-1';

    private InMemoryDatabase $database;

    protected function setUp(): void
    {
        $this->database = new InMemoryDatabase();
    }

    public function testImportMappingRoundTripsThroughSignedStyle(): void
    {
        $repository = new ImportMappingRepository($this->database);

        $mapping = new ImportMapping(
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

        $repository->save($mapping);

        $found = $repository->findByCompanyUuid(self::COMPANY);

        self::assertNotNull($found);
        self::assertSame('Amount', $found->amountColumn);
        self::assertNull($found->debitColumn);
        self::assertSame(ImportAmountStyle::Signed, $found->amountStyle);
    }

    public function testSavingAnUpdatedMappingOverwritesTheExistingRowForTheSameCompany(): void
    {
        $repository = new ImportMappingRepository($this->database);
        $uuid = Uuid::generate();

        $repository->save(new ImportMapping(
            uuid: $uuid,
            companyUuid: self::COMPANY,
            dateColumn: 'Date',
            descriptionColumn: 'Description',
            amountStyle: ImportAmountStyle::Signed,
            amountColumn: 'Amount',
            debitColumn: null,
            creditColumn: null,
            dateFormat: 'd/m/Y',
            createdAt: new DateTimeImmutable(),
        ));

        $repository->save(new ImportMapping(
            uuid: $uuid,
            companyUuid: self::COMPANY,
            dateColumn: 'TransactionDate',
            descriptionColumn: 'Narrative',
            amountStyle: ImportAmountStyle::DebitCredit,
            amountColumn: null,
            debitColumn: 'Debit',
            creditColumn: 'Credit',
            dateFormat: 'Y/m/d',
            createdAt: new DateTimeImmutable(),
            updatedAt: new DateTimeImmutable(),
        ));

        $found = $repository->findByCompanyUuid(self::COMPANY);

        self::assertNotNull($found);
        self::assertSame('TransactionDate', $found->dateColumn);
        self::assertSame(ImportAmountStyle::DebitCredit, $found->amountStyle);
        self::assertSame('Debit', $found->debitColumn);
        self::assertSame('Credit', $found->creditColumn);
    }

    private function makeStaged(string $hash, StagedTransactionStatus $status = StagedTransactionStatus::Pending): StagedTransaction
    {
        return new StagedTransaction(
            uuid: Uuid::generate(),
            companyUuid: self::COMPANY,
            sourceAccountUuid: 'account-bank',
            transactionDate: new DateTimeImmutable('2026-04-10'),
            description: 'Test transaction',
            amount: Money::fromRands(500.00),
            rowHash: $hash,
            status: $status,
            categoryAccountUuid: null,
            journalEntryUuid: null,
            importedAt: new DateTimeImmutable(),
        );
    }

    public function testInsertManySkipsRowsThatAlreadyExistByHash(): void
    {
        $repository = new StagedTransactionRepository($this->database);

        $first = $this->makeStaged('hash-1');
        $inserted = $repository->insertMany([$first]);
        self::assertSame(1, $inserted);

        // Re-"importing" the same row (same hash) a second time inserts nothing.
        $duplicate = $this->makeStaged('hash-1');
        $insertedAgain = $repository->insertMany([$duplicate]);
        self::assertSame(0, $insertedAgain);

        self::assertCount(1, $repository->findByCompanyUuid(self::COMPANY));
    }

    public function testInsertManyInsertsDistinctHashesAndSkipsOnlyTheDuplicateOnesWithinOneBatch(): void
    {
        $repository = new StagedTransactionRepository($this->database);

        $inserted = $repository->insertMany([
            $this->makeStaged('hash-a'),
            $this->makeStaged('hash-b'),
            $this->makeStaged('hash-a'), // duplicate within the same batch
        ]);

        self::assertSame(2, $inserted);
        self::assertCount(2, $repository->findByCompanyUuid(self::COMPANY));
    }

    public function testFindByCompanyUuidFiltersByStatus(): void
    {
        $repository = new StagedTransactionRepository($this->database);

        $repository->insertMany([
            $this->makeStaged('hash-pending', StagedTransactionStatus::Pending),
            $this->makeStaged('hash-categorized', StagedTransactionStatus::Categorized),
        ]);

        $pending = $repository->findByCompanyUuid(self::COMPANY, StagedTransactionStatus::Pending);

        self::assertCount(1, $pending);
        self::assertSame(StagedTransactionStatus::Pending, $pending[0]->status);
    }

    public function testSaveUpdatesAnExistingStagedTransaction(): void
    {
        $repository = new StagedTransactionRepository($this->database);
        $staged = $this->makeStaged('hash-x');
        $repository->insertMany([$staged]);

        $categorized = new StagedTransaction(
            uuid: $staged->uuid,
            companyUuid: $staged->companyUuid,
            sourceAccountUuid: $staged->sourceAccountUuid,
            transactionDate: $staged->transactionDate,
            description: $staged->description,
            amount: $staged->amount,
            rowHash: $staged->rowHash,
            status: StagedTransactionStatus::Categorized,
            categoryAccountUuid: 'account-sales',
            journalEntryUuid: 'entry-1',
            importedAt: $staged->importedAt,
            categorizedAt: new DateTimeImmutable(),
        );

        $repository->save($categorized);

        $found = $repository->findByUuid($staged->uuid);
        self::assertNotNull($found);
        self::assertSame(StagedTransactionStatus::Categorized, $found->status);
        self::assertSame('entry-1', $found->journalEntryUuid);
    }
}
