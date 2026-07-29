<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Enums;

/**
 * Which side (debit or credit) increases a given account's balance.
 *
 * Stored explicitly on each Account row rather than derived from
 * AccountType, because contra accounts break the simple type->side
 * mapping (e.g. Accumulated Depreciation is an Asset that is
 * credit-normal, Drawings is Equity that is debit-normal).
 *
 * @package BizHub\Bookkeeping\Enums
 */
enum NormalBalance: string
{
    case Debit = 'debit';
    case Credit = 'credit';

    public function opposite(): self
    {
        return $this === self::Debit ? self::Credit : self::Debit;
    }
}
