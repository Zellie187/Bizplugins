<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Reporting;

use BizHub\Bookkeeping\DTO\DateRange;
use BizHub\Bookkeeping\Support\Money;

/**
 * @package BizHub\Bookkeeping\Reporting
 */
final readonly class TrialBalanceReport
{
    /**
     * @param AccountBalanceRow[] $rows
     */
    public function __construct(
        public DateRange $range,
        public array $rows,
        public Money $totalDebit,
        public Money $totalCredit
    ) {
    }

    public function isBalanced(): bool
    {
        return $this->totalDebit->equals($this->totalCredit);
    }
}
