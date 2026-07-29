<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Exceptions;

/**
 * Raised when attempting to reverse a journal entry that already has a
 * reversal posted against it.
 *
 * @package BizHub\Bookkeeping\Exceptions
 */
final class AlreadyReversedException extends BookkeepingException
{
    public static function withUuid(string $entryUuid): self
    {
        return new self(sprintf('Journal entry "%s" has already been reversed.', $entryUuid));
    }
}
