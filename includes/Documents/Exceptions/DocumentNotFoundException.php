<?php

declare(strict_types=1);

namespace BizHub\Documents\Exceptions;

use RuntimeException;

/**
 * Thrown when a document cannot be found.
 *
 * @package BizHub\Documents\Exceptions
 */
final class DocumentNotFoundException extends RuntimeException
{
    /**
     * Create an exception for a missing document UUID.
     */
    public static function withUuid(string $uuid): self
    {
        return new self(
            sprintf('Document with UUID "%s" could not be found.', $uuid)
        );
    }
}
