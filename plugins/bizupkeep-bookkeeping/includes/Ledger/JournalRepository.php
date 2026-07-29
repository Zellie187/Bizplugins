<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Ledger;

use BizHub\Bookkeeping\Contracts\JournalRepositoryInterface;
use BizHub\Bookkeeping\DTO\DateRange;
use BizHub\Bookkeeping\Entities\JournalEntry;
use BizHub\Bookkeeping\Entities\JournalLine;
use BizHub\Bookkeeping\Enums\JournalSource;
use BizHub\Bookkeeping\Support\Money;
use BizHub\Framework\Database\Contracts\DatabaseInterface;
use BizHub\Framework\Database\Contracts\TransactionInterface;
use DateTimeImmutable;

/**
 * The only class touching DatabaseInterface for journal entries/lines.
 *
 * Date-range filtering is done in PHP after an equality-only findAll()
 * fetch (by company_uuid) rather than via raw SQL/QueryBuilder - the
 * DatabaseInterface::findAll() contract only supports equality
 * criteria, and startup-scale transaction volumes make an in-PHP scan
 * entirely reasonable while keeping this class fully testable against
 * the same InMemoryDatabase fake every other repository in this
 * codebase is tested against (its query()/execute() are no-ops).
 *
 * @package BizHub\Bookkeeping\Ledger
 */
final class JournalRepository implements JournalRepositoryInterface
{
    private const ENTRIES_TABLE = 'bizhub_bookkeeping_journal_entries';
    private const LINES_TABLE = 'bizhub_bookkeeping_journal_lines';

    public function __construct(
        private readonly DatabaseInterface $database,
        private readonly TransactionInterface $transaction
    ) {
    }

    public function insertEntryWithLines(JournalEntry $entry): JournalEntry
    {
        return $this->transaction->transactional(function () use ($entry): JournalEntry {
            $this->database->insert(self::ENTRIES_TABLE, $this->dehydrateEntry($entry));

            foreach ($entry->lines as $index => $line) {
                $this->database->insert(self::LINES_TABLE, $this->dehydrateLine($entry, $line, $index));
            }

            return $entry;
        });
    }

    public function findByUuid(string $uuid): ?JournalEntry
    {
        $row = $this->database->findOne(self::ENTRIES_TABLE, ['uuid' => $uuid]);

        return $row === null ? null : $this->hydrateEntryWithLines($row);
    }

    public function findReversalOf(string $entryUuid): ?JournalEntry
    {
        $row = $this->database->findOne(self::ENTRIES_TABLE, ['reversed_entry_uuid' => $entryUuid]);

        return $row === null ? null : $this->hydrateEntryWithLines($row);
    }

    public function findEntriesForCompany(
        string $companyUuid,
        DateRange $range,
        ?int $limit = null,
        int $offset = 0
    ): array {
        $rows = $this->database->findAll(
            self::ENTRIES_TABLE,
            ['company_uuid' => $companyUuid],
            ['entry_date' => 'DESC']
        );

        $entries = [];

        foreach ($rows as $row) {
            if (! $this->withinRange(new DateTimeImmutable((string) $row['entry_date']), $range)) {
                continue;
            }

            $entries[] = $this->hydrateEntryWithLines($row);
        }

        if ($limit !== null) {
            $entries = array_slice($entries, $offset, $limit);
        }

        return $entries;
    }

    public function sumBalancesByAccount(string $companyUuid, DateRange $range): array
    {
        $lineRows = $this->database->findAll(self::LINES_TABLE, ['company_uuid' => $companyUuid]);

        $sums = [];

        foreach ($lineRows as $row) {
            if (! $this->withinRange(new DateTimeImmutable((string) $row['entry_date']), $range)) {
                continue;
            }

            $accountUuid = (string) $row['account_uuid'];

            $sums[$accountUuid] ??= ['debit' => Money::zero(), 'credit' => Money::zero()];
            $sums[$accountUuid]['debit'] = $sums[$accountUuid]['debit']
                ->add(Money::fromMinorUnits((int) $row['debit_minor']));
            $sums[$accountUuid]['credit'] = $sums[$accountUuid]['credit']
                ->add(Money::fromMinorUnits((int) $row['credit_minor']));
        }

        return $sums;
    }

    private function withinRange(DateTimeImmutable $date, DateRange $range): bool
    {
        if ($range->from !== null && $date < $range->from) {
            return false;
        }

        return $date <= $range->to;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrateEntryWithLines(array $row): JournalEntry
    {
        $lineRows = $this->database->findAll(
            self::LINES_TABLE,
            ['entry_uuid' => (string) $row['uuid']],
            ['line_order' => 'ASC']
        );

        return $this->hydrateEntry($row)->withLines(array_map($this->hydrateLine(...), $lineRows));
    }

    /**
     * @return array<string,mixed>
     */
    private function dehydrateEntry(JournalEntry $entry): array
    {
        return [
            'uuid' => $entry->uuid,
            'company_uuid' => $entry->companyUuid,
            'entry_date' => $entry->entryDate->format('Y-m-d'),
            'description' => $entry->description,
            'source' => $entry->source->value,
            'reversed_entry_uuid' => $entry->reversedEntryUuid,
            'created_by' => $entry->createdBy,
            'created_at' => $entry->createdAt->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function dehydrateLine(JournalEntry $entry, JournalLine $line, int $index): array
    {
        return [
            'entry_uuid' => $entry->uuid,
            'company_uuid' => $entry->companyUuid,
            'entry_date' => $entry->entryDate->format('Y-m-d'),
            'account_uuid' => $line->accountUuid,
            'debit_minor' => $line->debit->minorUnits(),
            'credit_minor' => $line->credit->minorUnits(),
            'line_order' => $index,
            'memo' => $line->memo,
            'created_at' => $entry->createdAt->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrateEntry(array $row): JournalEntry
    {
        return new JournalEntry(
            uuid: (string) $row['uuid'],
            companyUuid: (string) $row['company_uuid'],
            entryDate: new DateTimeImmutable((string) $row['entry_date']),
            description: (string) $row['description'],
            source: JournalSource::from((string) $row['source']),
            reversedEntryUuid: isset($row['reversed_entry_uuid']) && $row['reversed_entry_uuid'] !== null
                ? (string) $row['reversed_entry_uuid']
                : null,
            createdBy: (int) $row['created_by'],
            createdAt: new DateTimeImmutable((string) $row['created_at']),
        );
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrateLine(array $row): JournalLine
    {
        return new JournalLine(
            accountUuid: (string) $row['account_uuid'],
            debit: Money::fromMinorUnits((int) $row['debit_minor']),
            credit: Money::fromMinorUnits((int) $row['credit_minor']),
            lineOrder: (int) $row['line_order'],
            memo: (string) $row['memo'],
        );
    }
}
