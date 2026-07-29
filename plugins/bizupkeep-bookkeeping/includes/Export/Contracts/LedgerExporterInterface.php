<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Export\Contracts;

use BizHub\Bookkeeping\DTO\DateRange;

/**
 * A single accounting platform's CSV export format.
 *
 * @package BizHub\Bookkeeping\Export\Contracts
 */
interface LedgerExporterInterface
{
    /**
     * Stable machine key, e.g. "quickbooks", "xero", "sage" - used to
     * resolve which exporter to use from the theme's export form.
     */
    public function platformKey(): string;

    public function platformLabel(): string;

    /**
     * Build the ready-to-download CSV content for a date range.
     */
    public function exportJournalEntries(string $companyUuid, DateRange $range): string;
}
