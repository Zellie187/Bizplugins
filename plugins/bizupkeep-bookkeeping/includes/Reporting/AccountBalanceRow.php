<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Reporting;

use BizHub\Bookkeeping\Enums\AccountType;
use BizHub\Bookkeeping\Support\Money;

/**
 * One account's aggregated debit/credit totals for a statement, plus
 * its normal-balance-adjusted net (positive under normal usage: e.g. a
 * debit-normal expense account with more debits than credits nets
 * positive, not negative).
 *
 * @package BizHub\Bookkeeping\Reporting
 */
final readonly class AccountBalanceRow
{
    public function __construct(
        public string $code,
        public string $name,
        public AccountType $type,
        public Money $debit,
        public Money $credit,
        public Money $net
    ) {
    }
}
