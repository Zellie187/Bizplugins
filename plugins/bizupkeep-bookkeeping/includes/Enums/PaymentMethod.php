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
     * The chart-of-accounts code of the offsetting account for this
     * payment method.
     */
    public function accountCode(): string
    {
        return match ($this) {
            self::Bank => ChartOfAccountsTemplate::CODE_BANK_ACCOUNT,
            self::Cash => ChartOfAccountsTemplate::CODE_CASH_ON_HAND,
        };
    }
}
