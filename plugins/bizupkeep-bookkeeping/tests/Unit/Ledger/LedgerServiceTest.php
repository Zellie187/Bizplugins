<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Tests\Unit\Ledger;

use BizHub\Bookkeeping\Accounts\AccountRepository;
use BizHub\Bookkeeping\Accounts\AccountService;
use BizHub\Bookkeeping\Accounts\ChartOfAccountsTemplate;
use BizHub\Bookkeeping\DTO\JournalLineData;
use BizHub\Bookkeeping\DTO\ManualJournalEntryData;
use BizHub\Bookkeeping\Enums\JournalSource;
use BizHub\Bookkeeping\Exceptions\AlreadyReversedException;
use BizHub\Bookkeeping\Exceptions\JournalEntryNotFoundException;
use BizHub\Bookkeeping\Exceptions\UnbalancedJournalEntryException;
use BizHub\Bookkeeping\Ledger\JournalRepository;
use BizHub\Bookkeeping\Ledger\LedgerService;
use BizHub\Bookkeeping\Support\Money;
use BizHub\Bookkeeping\Tests\Mocks\InMemoryDatabase;
use BizHub\Bookkeeping\Tests\Mocks\InMemoryTransaction;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class LedgerServiceTest extends TestCase
{
    private const COMPANY = 'company-1';

    private InMemoryDatabase $database;
    private AccountService $accountService;
    private AccountRepository $accountRepository;
    private JournalRepository $journalRepository;
    private LedgerService $ledger;

    protected function setUp(): void
    {
        $this->database = new InMemoryDatabase();
        $this->accountRepository = new AccountRepository($this->database);
        $this->accountService = new AccountService($this->accountRepository);
        $this->accountService->ensureSeeded(self::COMPANY);
        $this->journalRepository = new JournalRepository($this->database, new InMemoryTransaction());
        $this->ledger = new LedgerService($this->journalRepository, $this->accountRepository);
    }

    private function bank(): string
    {
        return $this->accountService->getByCode(self::COMPANY, ChartOfAccountsTemplate::CODE_BANK_ACCOUNT)->uuid;
    }

    private function sales(): string
    {
        return $this->accountService->getByCode(self::COMPANY, ChartOfAccountsTemplate::CODE_SALES_REVENUE)->uuid;
    }

    private function rent(): string
    {
        return $this->accountService->getByCode(self::COMPANY, ChartOfAccountsTemplate::CODE_RENT)->uuid;
    }

    public function testBalancedEntryPostsSuccessfully(): void
    {
        $data = new ManualJournalEntryData(
            companyUuid: self::COMPANY,
            date: new DateTimeImmutable('2026-04-15'),
            description: 'Cash sale',
            lines: [
                JournalLineData::debit($this->bank(), Money::fromRands(1000.00)),
                JournalLineData::credit($this->sales(), Money::fromRands(1000.00)),
            ],
            createdBy: 1,
        );

        $entry = $this->ledger->postEntry($data, JournalSource::Manual);

        self::assertNotSame('', $entry->uuid);
        self::assertCount(2, $entry->lines);
        self::assertNotNull($this->journalRepository->findByUuid($entry->uuid));
    }

    public function testUnbalancedEntryThrowsAndPersistsNothing(): void
    {
        $data = new ManualJournalEntryData(
            companyUuid: self::COMPANY,
            date: new DateTimeImmutable('2026-04-15'),
            description: 'Broken entry',
            lines: [
                JournalLineData::debit($this->bank(), Money::fromRands(1000.00)),
                JournalLineData::credit($this->sales(), Money::fromRands(999.00)),
            ],
            createdBy: 1,
        );

        try {
            $this->ledger->postEntry($data, JournalSource::Manual);
            self::fail('Expected UnbalancedJournalEntryException to be thrown.');
        } catch (UnbalancedJournalEntryException) {
            // expected
        }

        self::assertSame([], $this->database->all('bizhub_bookkeeping_journal_entries'));
        self::assertSame([], $this->database->all('bizhub_bookkeeping_journal_lines'));
    }

    public function testEntryWithFewerThanTwoLinesIsRejected(): void
    {
        $data = new ManualJournalEntryData(
            companyUuid: self::COMPANY,
            date: new DateTimeImmutable('2026-04-15'),
            description: 'Single line',
            lines: [
                JournalLineData::debit($this->bank(), Money::fromRands(100.00)),
            ],
            createdBy: 1,
        );

        $this->expectException(UnbalancedJournalEntryException::class);

        $this->ledger->postEntry($data, JournalSource::Manual);
    }

    public function testReverseEntryProducesSwappedLinesLinkedToTheOriginal(): void
    {
        $original = $this->ledger->postEntry(new ManualJournalEntryData(
            companyUuid: self::COMPANY,
            date: new DateTimeImmutable('2026-04-15'),
            description: 'Rent payment',
            lines: [
                JournalLineData::debit($this->rent(), Money::fromRands(500.00)),
                JournalLineData::credit($this->bank(), Money::fromRands(500.00)),
            ],
            createdBy: 1,
        ), JournalSource::Manual);

        $reversal = $this->ledger->reverseEntry($original->uuid, actorId: 2, reason: 'Wrong amount');

        self::assertSame($original->uuid, $reversal->reversedEntryUuid);
        self::assertSame(JournalSource::Reversal, $reversal->source);

        $rentLine = array_values(array_filter($reversal->lines, fn ($l) => $l->accountUuid === $this->rent()))[0];
        $bankLine = array_values(array_filter($reversal->lines, fn ($l) => $l->accountUuid === $this->bank()))[0];

        // Original: debit Rent, credit Bank -> reversal: credit Rent, debit Bank.
        self::assertTrue($rentLine->credit->equals(Money::fromRands(500.00)));
        self::assertTrue($bankLine->debit->equals(Money::fromRands(500.00)));

        self::assertNotNull($this->journalRepository->findReversalOf($original->uuid));
    }

    public function testReversingAnAlreadyReversedEntryThrows(): void
    {
        $original = $this->ledger->postEntry(new ManualJournalEntryData(
            companyUuid: self::COMPANY,
            date: new DateTimeImmutable('2026-04-15'),
            description: 'Rent payment',
            lines: [
                JournalLineData::debit($this->rent(), Money::fromRands(500.00)),
                JournalLineData::credit($this->bank(), Money::fromRands(500.00)),
            ],
            createdBy: 1,
        ), JournalSource::Manual);

        $this->ledger->reverseEntry($original->uuid, actorId: 2);

        $this->expectException(AlreadyReversedException::class);

        $this->ledger->reverseEntry($original->uuid, actorId: 2);
    }

    public function testReversingAnUnknownEntryThrows(): void
    {
        $this->expectException(JournalEntryNotFoundException::class);

        $this->ledger->reverseEntry('does-not-exist', actorId: 1);
    }

    public function testAccountFromAnotherCompanyIsRejected(): void
    {
        $this->accountService->ensureSeeded('company-2');
        $otherCompanyBank = $this->accountService->getByCode('company-2', ChartOfAccountsTemplate::CODE_BANK_ACCOUNT)->uuid;

        $data = new ManualJournalEntryData(
            companyUuid: self::COMPANY,
            date: new DateTimeImmutable('2026-04-15'),
            description: 'Cross-company leak',
            lines: [
                JournalLineData::debit($otherCompanyBank, Money::fromRands(100.00)),
                JournalLineData::credit($this->sales(), Money::fromRands(100.00)),
            ],
            createdBy: 1,
        );

        $this->expectException(\BizHub\Bookkeeping\Exceptions\AccountNotFoundException::class);

        $this->ledger->postEntry($data, JournalSource::Manual);
    }
}
