<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Reporting;

use BizHub\Bookkeeping\DTO\DateRange;
use BizHub\Bookkeeping\Support\Money;

/**
 * @package BizHub\Bookkeeping\Reporting
 */
final readonly class IncomeStatementReport
{
    /**
     * @param AccountBalanceRow[] $incomeRows
     * @param AccountBalanceRow[] $expenseRows
     */
    public function __construct(
        public DateRange $range,
        public array $incomeRows,
        public array $expenseRows,
        public Money $totalIncome,
        public Money $totalExpenses,
        public Money $netIncome
    ) {
    }
}
