<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\DTO;

use BizHub\Bookkeeping\Support\Money;

/**
 * One line of a journal entry: exactly one of $debit/$credit is
 * non-zero. Mirrors the journal_lines table's debit_minor/credit_minor
 * column layout directly, so mapping to/from persistence needs no
 * translation.
 *
 * @package BizHub\Bookkeeping\DTO
 */
final readonly class JournalLineData
{
    private function __construct(
        public string $accountUuid,
        public Money $debit,
        public Money $credit,
        public string $memo = ''
    ) {
    }

    public static function debit(string $accountUuid, Money $amount, string $memo = ''): self
    {
        return new self($accountUuid, $amount, Money::zero(), $memo);
    }

    public static function credit(string $accountUuid, Money $amount, string $memo = ''): self
    {
        return new self($accountUuid, Money::zero(), $amount, $memo);
    }
}
