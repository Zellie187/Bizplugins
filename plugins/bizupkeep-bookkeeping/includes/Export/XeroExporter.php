<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Export;

use BizHub\Bookkeeping\Entities\JournalEntry;
use BizHub\Bookkeeping\Support\Money;

/**
 * Xero's "Import a CSV bank statement" format (Accounting > Bank
 * accounts > Import a Statement), confirmed via Xero Central's own
 * article as of this plugin's build date.
 *
 * Date format DD/MM/YYYY matches a South African Xero organisation's
 * region setting (Xero uses DD/MM/YYYY for UK/Australia/New Zealand-
 * style regions, MM/DD/YYYY only for US organisations) - this business
 * only serves South African clients. Amount: positive = money in,
 * negative = money out, no thousands separator. Payee is left blank -
 * this plugin doesn't track payee/contact names in v1.
 *
 * @package BizHub\Bookkeeping\Export
 */
final class XeroExporter extends AbstractBankStatementExporter
{
    public function platformKey(): string
    {
        return 'xero';
    }

    public function platformLabel(): string
    {
        return 'Xero';
    }

    protected function headers(): array
    {
        return ['Date', 'Amount', 'Payee', 'Description'];
    }

    protected function row(JournalEntry $entry, Money $amount): array
    {
        return [
            'Date' => $entry->entryDate->format('d/m/Y'),
            'Amount' => number_format($amount->toRands(), 2, '.', ''),
            'Payee' => '',
            'Description' => $entry->description,
        ];
    }
}
