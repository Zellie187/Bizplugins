<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Contracts;

use BizHub\Bookkeeping\DTO\DateRange;
use BizHub\Bookkeeping\Entities\JournalEntry;

/**
 * Persistence contract for journal entries/lines. The only class in
 * this module allowed to touch DatabaseInterface directly.
 *
 * @package BizHub\Bookkeeping\Contracts
 */
interface JournalRepositoryInterface
{
    /**
     * Insert an entry header and every one of its lines atomically.
     * The hard correctness requirement of this whole plugin - no
     * partial entry may ever be persisted.
     */
    public function insertEntryWithLines(JournalEntry $entry): JournalEntry;

    public function findByUuid(string $uuid): ?JournalEntry;

    /**
     * Find the reversal entry posted against a given original entry,
     * if one exists.
     */
    public function findReversalOf(string $entryUuid): ?JournalEntry;

    /**
     * @return JournalEntry[]
     */
    public function findEntriesForCompany(
        string $companyUuid,
        DateRange $range,
        ?int $limit = null,
        int $offset = 0
    ): array;

    /**
     * Sum every line's debit/credit within the range, grouped by
     * account UUID.
     *
     * @return array<string,array{debit:\BizHub\Bookkeeping\Support\Money,credit:\BizHub\Bookkeeping\Support\Money}>
     */
    public function sumBalancesByAccount(string $companyUuid, DateRange $range): array;
}
