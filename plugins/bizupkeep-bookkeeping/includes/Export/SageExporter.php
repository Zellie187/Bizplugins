<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Export;

use BizHub\Bookkeeping\Entities\JournalEntry;
use BizHub\Bookkeeping\Support\Money;

/**
 * Sage's 3-column CSV bank statement import format (Banking > Import
 * Statement), confirmed via Sage's own South African knowledge base
 * article (za-kb.sage.com) as of this plugin's build date - this
 * business's clients are South African, so the SA-edition-documented
 * format is used rather than assuming a US/UK Sage product's format.
 *
 * Date format dd/mm/yyyy (the SA default the KB article documents).
 * Amount: payments negative, receipts positive - same sign convention
 * as Xero's bank statement import, just a different column layout
 * (Description before Amount, no separate Payee column).
 *
 * @package BizHub\Bookkeeping\Export
 */
final class SageExporter extends AbstractBankStatementExporter
{
    public function platformKey(): string
    {
        return 'sage';
    }

    public function platformLabel(): string
    {
        return 'Sage';
    }

    protected function headers(): array
    {
        return ['Date', 'Description', 'Amount'];
    }

    protected function row(JournalEntry $entry, Money $amount): array
    {
        return [
            'Date' => $entry->entryDate->format('d/m/Y'),
            'Description' => $entry->description,
            'Amount' => number_format($amount->toRands(), 2, '.', ''),
        ];
    }
}
