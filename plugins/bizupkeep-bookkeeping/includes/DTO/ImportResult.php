<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\DTO;

/**
 * Counts returned from BankImportService::import() - one bad row must
 * never abort the whole batch, so the outcome is a set of counts
 * rather than an all-or-nothing success/failure.
 *
 * @package BizHub\Bookkeeping\DTO
 */
final readonly class ImportResult
{
    public function __construct(
        public int $imported,
        public int $duplicates,
        public int $unparseable
    ) {
    }
}
