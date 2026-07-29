<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\DTO;

use DateTimeImmutable;

/**
 * Everything needed to post one journal entry, regardless of source
 * (manual admin posting, income/expense capture, or a reversal all
 * build one of these before handing it to LedgerService::postEntry()).
 *
 * @package BizHub\Bookkeeping\DTO
 */
final readonly class ManualJournalEntryData
{
    /**
     * @param JournalLineData[] $lines
     */
    public function __construct(
        public string $companyUuid,
        public DateTimeImmutable $date,
        public string $description,
        public array $lines,
        public int $createdBy
    ) {
    }
}
