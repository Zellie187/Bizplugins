<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Reporting;

use BizHub\Bookkeeping\Support\Money;
use DateTimeImmutable;

/**
 * @package BizHub\Bookkeeping\Reporting
 */
final readonly class BalanceSheetReport
{
    /**
     * @param AccountBalanceRow[] $assetRows
     * @param AccountBalanceRow[] $liabilityRows
     * @param AccountBalanceRow[] $equityRows
     */
    public function __construct(
        public DateTimeImmutable $asOf,
        public array $assetRows,
        public array $liabilityRows,
        public array $equityRows,
        public Money $totalAssets,
        public Money $totalLiabilities,
        public Money $totalEquityExcludingCurrentYearEarnings,
        public Money $currentYearEarnings,
        public Money $priorYearsEarnings,
        public Money $totalEquity
    ) {
    }

    /**
     * Assets == Liabilities + Equity (including Current Year Earnings).
     */
    public function isBalanced(): bool
    {
        return $this->totalAssets->equals($this->totalLiabilities->add($this->totalEquity));
    }
}
