<?php

declare(strict_types=1);

namespace BizHub\Framework\Database\Exceptions;

/**
 * Thrown when a requested database record does not exist.
 *
 * @package BizHub\Framework\Database\Exceptions
 */
final class RecordNotFoundException extends DatabaseException
{
    /**
     * Create a record-not-found exception.
     *
     * @param string $table
     * @param string $identifier
     *
     * @return self
     */
    public static function forIdentifier(
        string $table,
        string $identifier
    ): self {
        return new self(
            sprintf(
                'No record found in table "%s" for "%s".',
                $table,
                $identifier
            )
        );
    }
}
