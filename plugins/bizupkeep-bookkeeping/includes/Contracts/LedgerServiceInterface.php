<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Contracts;

use BizHub\Bookkeeping\DTO\ManualJournalEntryData;
use BizHub\Bookkeeping\Entities\JournalEntry;
use BizHub\Bookkeeping\Enums\JournalSource;

/**
 * Public API for posting and reversing journal entries.
 *
 * @package BizHub\Bookkeeping\Contracts
 */
interface LedgerServiceInterface
{
    /**
     * Post a balanced journal entry.
     *
     * @throws \BizHub\Bookkeeping\Exceptions\UnbalancedJournalEntryException
     * @throws \BizHub\Bookkeeping\Exceptions\AccountNotFoundException
     */
    public function postEntry(ManualJournalEntryData $data, JournalSource $source): JournalEntry;

    /**
     * Post a reversing entry against an existing one. Never mutates or
     * deletes the original - corrections are always new, linked rows.
     *
     * @throws \BizHub\Bookkeeping\Exceptions\JournalEntryNotFoundException
     * @throws \BizHub\Bookkeeping\Exceptions\AlreadyReversedException
     */
    public function reverseEntry(string $entryUuid, int $actorId, string $reason = ''): JournalEntry;
}
