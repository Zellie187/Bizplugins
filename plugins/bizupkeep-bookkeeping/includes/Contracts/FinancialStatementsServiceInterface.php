<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Contracts;

use BizHub\Bookkeeping\DTO\DateRange;
use BizHub\Bookkeeping\Reporting\BalanceSheetReport;
use BizHub\Bookkeeping\Reporting\IncomeStatementReport;
use BizHub\Bookkeeping\Reporting\TrialBalanceReport;
use BizHub\Bookkeeping\Reporting\VatSummaryReport;
use DateTimeImmutable;

/**
 * Public API for the three financial statements this plugin exists to
 * produce - the payoff of choosing full double-entry over single-entry
 * capture.
 *
 * @package BizHub\Bookkeeping\Contracts
 */
interface FinancialStatementsServiceInterface
{
    /**
     * @throws \BizHub\Bookkeeping\Exceptions\LedgerIntegrityException
     */
    public function trialBalance(string $companyUuid, DateRange $range): TrialBalanceReport;

    public function incomeStatement(string $companyUuid, DateRange $range): IncomeStatementReport;

    /**
     * currentYearEarnings is computed for the fiscal year (March 1 -
     * end of February) containing $asOf - this business's default
     * financial year for every company.
     */
    public function balanceSheet(string $companyUuid, DateTimeImmutable $asOf): BalanceSheetReport;

    /**
     * Output VAT, Input VAT and the net VAT position for a period - the
     * figures needed to complete a VAT201 return.
     */
    public function vatSummary(string $companyUuid, DateRange $range): VatSummaryReport;
}
