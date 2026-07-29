<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Export;

use BizHub\Bookkeeping\Contracts\AccountRepositoryInterface;
use BizHub\Bookkeeping\Contracts\JournalRepositoryInterface;
use BizHub\Bookkeeping\DTO\DateRange;
use BizHub\Bookkeeping\Entities\Account;
use BizHub\Bookkeeping\Export\Contracts\LedgerExporterInterface;

/**
 * QuickBooks Online's native "Import Journal Entries" CSV format
 * (Settings > Import Data > Journal Entries > Download sample CSV),
 * confirmed via QuickBooks' own help article and multiple independent
 * import-tool vendors (dancingnumbers.com, saasant.com) as of this
 * plugin's build date - not written from unchecked training-data recall.
 *
 * Columns: JournalNo, JournalDate, AccountName, Debits, Credits,
 * Description, Name. Every line belonging to one journal entry shares
 * the same JournalNo - QBO groups rows into one entry by that column,
 * not by adjacency. "Name" is only required when AccountName is an
 * Accounts Receivable/Payable account; left blank in v1 since this
 * plugin doesn't track AR/AP customer/vendor names yet.
 *
 * Unlike Xero/Sage, this is a genuine full general-ledger export - QBO
 * natively supports importing a complete multi-line journal entry, not
 * just bank-statement-style single amounts, so every line of every
 * entry in range is included (not filtered to Bank/Cash-touching
 * entries the way AbstractBankStatementExporter is).
 *
 * @package BizHub\Bookkeeping\Export
 */
final class QuickBooksOnlineExporter implements LedgerExporterInterface
{
    private const HEADERS = ['JournalNo', 'JournalDate', 'AccountName', 'Debits', 'Credits', 'Description', 'Name'];

    public function __construct(
        private readonly JournalRepositoryInterface $journal,
        private readonly AccountRepositoryInterface $accounts,
        private readonly CsvWriter $csv
    ) {
    }

    public function platformKey(): string
    {
        return 'quickbooks';
    }

    public function platformLabel(): string
    {
        return 'QuickBooks Online';
    }

    public function exportJournalEntries(string $companyUuid, DateRange $range): string
    {
        $accountNames = $this->accountNames($companyUuid);
        $entries = $this->journal->findEntriesForCompany($companyUuid, $range);

        $rows = [];
        $journalNo = 0;

        foreach ($entries as $entry) {
            $journalNo++;

            foreach ($entry->lines as $line) {
                $rows[] = [
                    'JournalNo' => (string) $journalNo,
                    'JournalDate' => $entry->entryDate->format('m/d/Y'),
                    'AccountName' => $accountNames[$line->accountUuid] ?? $line->accountUuid,
                    'Debits' => $line->debit->isZero() ? '' : number_format($line->debit->toRands(), 2, '.', ''),
                    'Credits' => $line->credit->isZero() ? '' : number_format($line->credit->toRands(), 2, '.', ''),
                    'Description' => $entry->description,
                    'Name' => '',
                ];
            }
        }

        return $this->csv->toString($rows, self::HEADERS);
    }

    /**
     * @return array<string,string>
     */
    private function accountNames(string $companyUuid): array
    {
        $names = [];

        foreach ($this->accounts->findAllForCompany($companyUuid, null, onlyActive: false) as $account) {
            /** @var Account $account */
            $names[$account->uuid] = $account->name;
        }

        return $names;
    }
}
