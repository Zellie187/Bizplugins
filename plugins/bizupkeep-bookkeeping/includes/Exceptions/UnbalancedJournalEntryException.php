<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Exceptions;

use BizHub\Bookkeeping\Support\Money;

/**
 * Raised when a journal entry's total debits do not equal its total
 * credits. This is the one non-negotiable gate protecting the ledger's
 * double-entry integrity - nothing is ever persisted when this is
 * thrown.
 *
 * @package BizHub\Bookkeeping\Exceptions
 */
final class UnbalancedJournalEntryException extends BookkeepingException
{
    public static function withTotals(Money $totalDebits, Money $totalCredits): self
    {
        return new self(sprintf(
            'Journal entry is unbalanced: total debits %s do not equal total credits %s.',
            $totalDebits->format(),
            $totalCredits->format()
        ));
    }

    public static function tooFewLines(int $count): self
    {
        return new self(sprintf('A journal entry needs at least 2 lines, %d given.', $count));
    }
}
