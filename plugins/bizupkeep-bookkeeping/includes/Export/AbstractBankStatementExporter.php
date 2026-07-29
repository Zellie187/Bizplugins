<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Export;

use BizHub\Bookkeeping\Accounts\ChartOfAccountsTemplate;
use BizHub\Bookkeeping\Contracts\AccountRepositoryInterface;
use BizHub\Bookkeeping\Contracts\JournalRepositoryInterface;
use BizHub\Bookkeeping\DTO\DateRange;
use BizHub\Bookkeeping\Entities\Account;
use BizHub\Bookkeeping\Entities\JournalEntry;
use BizHub\Bookkeeping\Export\Contracts\LedgerExporterInterface;
use BizHub\Bookkeeping\Support\Money;

/**
 * Shared base for Xero and Sage, both of whose officially-documented,
 * no-paid-addon CSV import path is a BANK STATEMENT import (Date /
 * Amount / Description, signed positive-for-money-in /
 * negative-for-money-out) - confirmed via Xero's own Central help
 * article and Sage's South African knowledge base article as of this
 * plugin's build date. Neither platform offers a native multi-line
 * general-journal CSV import the way QuickBooksOnlineExporter's
 * platform does, so this deliberately only exports entries that touch
 * the Bank or Cash account, collapsed to one net signed amount per
 * entry - a manual journal entry with no Bank/Cash leg (e.g. pure
 * depreciation) has no representation in either platform's CSV import
 * and is correctly excluded, not silently corrupted.
 *
 * @package BizHub\Bookkeeping\Export
 */
abstract class AbstractBankStatementExporter implements LedgerExporterInterface
{
    private const BANK_CASH_CODES = [
        ChartOfAccountsTemplate::CODE_BANK_ACCOUNT,
        ChartOfAccountsTemplate::CODE_CASH_ON_HAND,
    ];

    public function __construct(
        protected readonly JournalRepositoryInterface $journal,
        protected readonly AccountRepositoryInterface $accounts,
        protected readonly CsvWriter $csv
    ) {
    }

    public function exportJournalEntries(string $companyUuid, DateRange $range): string
    {
        $bankCashAccountUuids = $this->bankCashAccountUuids($companyUuid);
        $entries = $this->journal->findEntriesForCompany($companyUuid, $range);

        $rows = [];

        foreach ($entries as $entry) {
            $amount = Money::zero();
            $touchesBankOrCash = false;

            foreach ($entry->lines as $line) {
                if (! in_array($line->accountUuid, $bankCashAccountUuids, true)) {
                    continue;
                }

                $touchesBankOrCash = true;
                // Bank/Cash is a debit-normal asset: a debit is money
                // in (positive), a credit is money out (negative).
                $amount = $line->isDebit() ? $amount->add($line->debit) : $amount->subtract($line->credit);
            }

            if (! $touchesBankOrCash) {
                continue;
            }

            $rows[] = $this->row($entry, $amount);
        }

        return $this->csv->toString($rows, $this->headers());
    }

    /**
     * @return array<int,string>
     */
    private function bankCashAccountUuids(string $companyUuid): array
    {
        $uuids = [];

        foreach ($this->accounts->findAllForCompany($companyUuid, null, onlyActive: false) as $account) {
            /** @var Account $account */
            if (in_array($account->code, self::BANK_CASH_CODES, true)) {
                $uuids[] = $account->uuid;
            }
        }

        return $uuids;
    }

    /**
     * @return array<int,string>
     */
    abstract protected function headers(): array;

    /**
     * @return array<string,scalar|null>
     */
    abstract protected function row(JournalEntry $entry, Money $amount): array;
}
