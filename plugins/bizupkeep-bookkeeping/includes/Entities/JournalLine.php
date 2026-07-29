<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Entities;

use BizHub\Bookkeeping\Support\Money;
use InvalidArgumentException;

/**
 * A single posted line within a JournalEntry.
 *
 * Never addressed independently of its parent entry - no public UUID,
 * matching the journal_lines table having no uuid column.
 *
 * @package BizHub\Bookkeeping\Entities
 */
final readonly class JournalLine
{
    public function __construct(
        public string $accountUuid,
        public Money $debit,
        public Money $credit,
        public int $lineOrder = 0,
        public string $memo = ''
    ) {
        if ($this->accountUuid === '') {
            throw new InvalidArgumentException('JournalLine accountUuid cannot be empty.');
        }

        if (! $this->debit->isZero() && ! $this->credit->isZero()) {
            throw new InvalidArgumentException('A journal line cannot have both a debit and a credit amount.');
        }

        if ($this->debit->isZero() && $this->credit->isZero()) {
            throw new InvalidArgumentException('A journal line must have either a debit or a credit amount.');
        }

        if ($this->debit->isNegative() || $this->credit->isNegative()) {
            throw new InvalidArgumentException('Journal line amounts cannot be negative.');
        }
    }

    public function isDebit(): bool
    {
        return ! $this->debit->isZero();
    }

    /**
     * The line's single non-zero amount, regardless of which side.
     */
    public function amount(): Money
    {
        return $this->isDebit() ? $this->debit : $this->credit;
    }
}
