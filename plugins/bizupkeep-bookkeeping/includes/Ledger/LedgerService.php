<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Ledger;

use BizHub\Bookkeeping\Contracts\AccountRepositoryInterface;
use BizHub\Bookkeeping\Contracts\JournalRepositoryInterface;
use BizHub\Bookkeeping\Contracts\LedgerServiceInterface;
use BizHub\Bookkeeping\DTO\JournalLineData;
use BizHub\Bookkeeping\DTO\ManualJournalEntryData;
use BizHub\Bookkeeping\Entities\JournalEntry;
use BizHub\Bookkeeping\Entities\JournalLine;
use BizHub\Bookkeeping\Enums\JournalSource;
use BizHub\Bookkeeping\Exceptions\AccountNotFoundException;
use BizHub\Bookkeeping\Exceptions\AlreadyReversedException;
use BizHub\Bookkeeping\Exceptions\JournalEntryNotFoundException;
use BizHub\Bookkeeping\Exceptions\UnbalancedJournalEntryException;
use BizHub\Bookkeeping\Support\Money;
use BizHub\Framework\Support\Uuid;
use DateTimeImmutable;

/**
 * Posts and reverses journal entries, enforcing the one non-negotiable
 * rule of double-entry bookkeeping: every entry's total debits must
 * equal its total credits, or nothing is persisted.
 *
 * @package BizHub\Bookkeeping\Ledger
 */
final class LedgerService implements LedgerServiceInterface
{
    public function __construct(
        private readonly JournalRepositoryInterface $journal,
        private readonly AccountRepositoryInterface $accounts
    ) {
    }

    public function postEntry(ManualJournalEntryData $data, JournalSource $source): JournalEntry
    {
        return $this->post($data, $source, null);
    }

    public function reverseEntry(string $entryUuid, int $actorId, string $reason = ''): JournalEntry
    {
        $original = $this->journal->findByUuid($entryUuid)
            ?? throw JournalEntryNotFoundException::withUuid($entryUuid);

        if ($this->journal->findReversalOf($entryUuid) !== null) {
            throw AlreadyReversedException::withUuid($entryUuid);
        }

        $reversedLines = array_map(
            static fn (JournalLine $line): JournalLineData => $line->isDebit()
                ? JournalLineData::credit($line->accountUuid, $line->debit, $line->memo)
                : JournalLineData::debit($line->accountUuid, $line->credit, $line->memo),
            $original->lines
        );

        $description = 'Reversal of: ' . $original->description . ($reason !== '' ? " ({$reason})" : '');

        $data = new ManualJournalEntryData(
            companyUuid: $original->companyUuid,
            date: new DateTimeImmutable(),
            description: $description,
            lines: $reversedLines,
            createdBy: $actorId,
        );

        return $this->post($data, JournalSource::Reversal, $entryUuid);
    }

    private function post(ManualJournalEntryData $data, JournalSource $source, ?string $reversedEntryUuid): JournalEntry
    {
        $lines = $data->lines;

        if (count($lines) < 2) {
            throw UnbalancedJournalEntryException::tooFewLines(count($lines));
        }

        $totalDebit = Money::zero();
        $totalCredit = Money::zero();
        $journalLines = [];

        foreach ($lines as $line) {
            $account = $this->accounts->findByUuid($line->accountUuid)
                ?? throw AccountNotFoundException::withUuid($line->accountUuid);

            if ($account->companyUuid !== $data->companyUuid) {
                throw AccountNotFoundException::notOwnedByCompany($line->accountUuid, $data->companyUuid);
            }

            if (! $account->isActive) {
                throw AccountNotFoundException::inactive($line->accountUuid);
            }

            $totalDebit = $totalDebit->add($line->debit);
            $totalCredit = $totalCredit->add($line->credit);

            $journalLines[] = new JournalLine($line->accountUuid, $line->debit, $line->credit, memo: $line->memo);
        }

        if (! $totalDebit->equals($totalCredit)) {
            throw UnbalancedJournalEntryException::withTotals($totalDebit, $totalCredit);
        }

        $entry = (new JournalEntry(
            uuid: Uuid::generate(),
            companyUuid: $data->companyUuid,
            entryDate: $data->date,
            description: $data->description,
            source: $source,
            reversedEntryUuid: $reversedEntryUuid,
            createdBy: $data->createdBy,
            createdAt: new DateTimeImmutable(),
        ))->withLines($journalLines);

        return $this->journal->insertEntryWithLines($entry);
    }
}
