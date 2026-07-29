<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Reporting;

use BizHub\Bookkeeping\Accounts\ChartOfAccountsTemplate;
use BizHub\Bookkeeping\Contracts\AccountRepositoryInterface;
use BizHub\Bookkeeping\Contracts\FinancialStatementsServiceInterface;
use BizHub\Bookkeeping\Contracts\JournalRepositoryInterface;
use BizHub\Bookkeeping\DTO\DateRange;
use BizHub\Bookkeeping\Entities\Account;
use BizHub\Bookkeeping\Enums\AccountType;
use BizHub\Bookkeeping\Enums\NormalBalance;
use BizHub\Bookkeeping\Exceptions\LedgerIntegrityException;
use BizHub\Bookkeeping\Support\Money;
use DateTimeImmutable;

/**
 * Computes Trial Balance, Income Statement and Balance Sheet by summing
 * journal lines by account over a date range - no separate stored/
 * cached balances table, computed fresh on every read. Startup-scale
 * transaction volumes make this entirely reasonable for v1.
 *
 * @package BizHub\Bookkeeping\Reporting
 */
final class FinancialStatementsService implements FinancialStatementsServiceInterface
{
    public function __construct(
        private readonly JournalRepositoryInterface $journal,
        private readonly AccountRepositoryInterface $accounts
    ) {
    }

    public function trialBalance(string $companyUuid, DateRange $range): TrialBalanceReport
    {
        $rows = $this->nonZeroBalanceRows($companyUuid, $range, null);

        $totalDebit = $this->sum($rows, static fn (AccountBalanceRow $r): Money => $r->debit);
        $totalCredit = $this->sum($rows, static fn (AccountBalanceRow $r): Money => $r->credit);

        if (! $totalDebit->equals($totalCredit)) {
            // Should be structurally impossible given LedgerService's balance
            // gate on every post - the cheapest possible correctness tripwire.
            throw new LedgerIntegrityException(sprintf(
                'Trial balance does not net to zero for company "%s": total debits %s, total credits %s.',
                $companyUuid,
                $totalDebit->format(),
                $totalCredit->format()
            ));
        }

        return new TrialBalanceReport($range, $rows, $totalDebit, $totalCredit);
    }

    public function incomeStatement(string $companyUuid, DateRange $range): IncomeStatementReport
    {
        $incomeRows = $this->nonZeroBalanceRows($companyUuid, $range, AccountType::Income);
        $expenseRows = $this->nonZeroBalanceRows($companyUuid, $range, AccountType::Expense);

        $totalIncome = $this->sum($incomeRows, static fn (AccountBalanceRow $r): Money => $r->net);
        $totalExpenses = $this->sum($expenseRows, static fn (AccountBalanceRow $r): Money => $r->net);

        return new IncomeStatementReport(
            $range,
            $incomeRows,
            $expenseRows,
            $totalIncome,
            $totalExpenses,
            $totalIncome->subtract($totalExpenses)
        );
    }

    public function balanceSheet(string $companyUuid, DateTimeImmutable $asOf): BalanceSheetReport
    {
        $range = DateRange::sinceInception($asOf);

        $assetRows = $this->nonZeroBalanceRows($companyUuid, $range, AccountType::Asset);
        $liabilityRows = $this->nonZeroBalanceRows($companyUuid, $range, AccountType::Liability);
        $equityRows = $this->nonZeroBalanceRows($companyUuid, $range, AccountType::Equity);

        $totalAssets = $this->sum($assetRows, static fn (AccountBalanceRow $r): Money => $r->net);
        $totalLiabilities = $this->sum($liabilityRows, static fn (AccountBalanceRow $r): Money => $r->net);
        $totalEquityExcludingEarnings = $this->sum($equityRows, static fn (AccountBalanceRow $r): Money => $r->net);

        /*
         * This system posts no year-end closing entries, so the
         * fundamental accounting identity (Assets = Liabilities +
         * Equity + net income) only holds if Equity absorbs ALL
         * accumulated net income since inception, not just the current
         * fiscal year - Asset/Liability balances (e.g. Bank) already
         * reflect the cash effect of every historical Income/Expense
         * posting, closed year or not. currentYearEarnings is reported
         * separately purely for display (the fiscal-year-to-date
         * slice); priorYearsEarnings is the remainder, standing in for
         * a literal Retained Earnings closing entry this system
         * doesn't perform.
         */
        $accumulatedNetIncome = $this->incomeStatement($companyUuid, $range)->netIncome;
        $currentYearEarnings = $this->incomeStatement($companyUuid, DateRange::fiscalYearToDate($asOf))->netIncome;
        $priorYearsEarnings = $accumulatedNetIncome->subtract($currentYearEarnings);

        return new BalanceSheetReport(
            $asOf,
            $assetRows,
            $liabilityRows,
            $equityRows,
            $totalAssets,
            $totalLiabilities,
            $totalEquityExcludingEarnings,
            $currentYearEarnings,
            $priorYearsEarnings,
            $totalEquityExcludingEarnings->add($accumulatedNetIncome)
        );
    }

    public function vatSummary(string $companyUuid, DateRange $range): VatSummaryReport
    {
        $sums = $this->journal->sumBalancesByAccount($companyUuid, $range);

        $outputVat = $this->vatAccountNet($companyUuid, $sums, ChartOfAccountsTemplate::CODE_VAT_OUTPUT);
        $inputVat = $this->vatAccountNet($companyUuid, $sums, ChartOfAccountsTemplate::CODE_VAT_INPUT);

        return new VatSummaryReport($range, $outputVat, $inputVat, $outputVat->subtract($inputVat));
    }

    /**
     * @param array<string,array{debit:Money,credit:Money}> $sums
     */
    private function vatAccountNet(string $companyUuid, array $sums, string $accountCode): Money
    {
        $account = $this->accounts->findByCode($companyUuid, $accountCode);

        if ($account === null) {
            return Money::zero();
        }

        $debit = $sums[$account->uuid]['debit'] ?? Money::zero();
        $credit = $sums[$account->uuid]['credit'] ?? Money::zero();

        return $this->netFor($account, $debit, $credit);
    }

    /**
     * @return AccountBalanceRow[]
     */
    private function nonZeroBalanceRows(string $companyUuid, DateRange $range, ?AccountType $type): array
    {
        $sums = $this->journal->sumBalancesByAccount($companyUuid, $range);
        $accounts = $this->accounts->findAllForCompany($companyUuid, $type, onlyActive: false);

        $rows = [];

        foreach ($accounts as $account) {
            $debit = $sums[$account->uuid]['debit'] ?? Money::zero();
            $credit = $sums[$account->uuid]['credit'] ?? Money::zero();

            if ($debit->isZero() && $credit->isZero()) {
                continue;
            }

            $rows[] = new AccountBalanceRow(
                $account->code,
                $account->name,
                $account->type,
                $debit,
                $credit,
                $this->netFor($account, $debit, $credit)
            );
        }

        return $rows;
    }

    private function netFor(Account $account, Money $debit, Money $credit): Money
    {
        return $account->normalBalance === NormalBalance::Debit
            ? $debit->subtract($credit)
            : $credit->subtract($debit);
    }

    /**
     * @param AccountBalanceRow[] $rows
     * @param callable(AccountBalanceRow):Money $selector
     */
    private function sum(array $rows, callable $selector): Money
    {
        return array_reduce(
            $rows,
            static fn (Money $carry, AccountBalanceRow $row): Money => $carry->add($selector($row)),
            Money::zero()
        );
    }
}
