<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Exceptions;

/**
 * Raised when a journal entry UUID does not resolve.
 *
 * @package BizHub\Bookkeeping\Exceptions
 */
final class JournalEntryNotFoundException extends BookkeepingException
{
    public static function withUuid(string $uuid): self
    {
        return new self(sprintf('No journal entry found with UUID "%s".', $uuid));
    }
}
