<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Enums;

use BizHub\Bookkeeping\Accounts\ChartOfAccountsTemplate;

/**
 * How a captured income/expense transaction was settled - determines
 * which system account is used as the automatic offsetting leg of the
 * generated journal entry.
 *
 * @package BizHub\Bookkeeping\Enums
 */
enum PaymentMethod: string
{
    case Bank = 'bank';
    case Cash = 'cash';

    /**
     * A payment gateway settlement (e.g. Yoco, SnapScan). Deliberately
     * one shared case rather than one per gateway: per-gateway detail
     * (which gateway, reference, amount, status) already lives one
     * join away in BizUpKeep Payments' own payment-attempt records, so
     * duplicating that taxonomy into the chart of accounts would be
     * redundant, and it keeps adding a future third gateway out of
     * this plugin entirely. All gateway settlements land in the same
     * real bank account, same as this case's accountCode() below.
     */
    case Online = 'online';

    /**
     * The chart-of-accounts code of the offsetting account for this
     * payment method.
     */
    public function accountCode(): string
    {
        return match ($this) {
            self::Bank, self::Online => ChartOfAccountsTemplate::CODE_BANK_ACCOUNT,
            self::Cash => ChartOfAccountsTemplate::CODE_CASH_ON_HAND,
        };
    }
}
