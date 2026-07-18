<?php

declare(strict_types=1);

namespace BizHub\Companies\Exceptions;

use RuntimeException;

/**
 * Thrown when a director cannot be found.
 *
 * @package BizHub\Companies\Exceptions
 */
final class DirectorNotFoundException extends RuntimeException
{
    /**
     * Create an exception for a missing director UUID.
     *
     * @param string $uuid Director UUID.
     *
     * @return self
     */
    public static function withUuid(string $uuid): self
    {
        return new self(
            sprintf(
                'Director with UUID "%s" could not be found.',
                $uuid
            )
        );
    }
}
