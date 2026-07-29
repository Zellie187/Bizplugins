<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Enums;

/**
 * The five fundamental account classifications in double-entry
 * bookkeeping.
 *
 * @package BizHub\Bookkeeping\Enums
 */
enum AccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Income = 'income';
    case Expense = 'expense';

    public function label(): string
    {
        return match ($this) {
            self::Asset => 'Asset',
            self::Liability => 'Liability',
            self::Equity => 'Equity',
            self::Income => 'Income',
            self::Expense => 'Expense',
        };
    }

    /**
     * Whether this type appears on the Balance Sheet (as opposed to
     * the Income Statement).
     */
    public function isBalanceSheetType(): bool
    {
        return match ($this) {
            self::Asset, self::Liability, self::Equity => true,
            self::Income, self::Expense => false,
        };
    }
}
