<?php

declare(strict_types=1);

namespace BizHub\Applications\Exceptions;

use RuntimeException;

/**
 * Thrown when an application cannot be found.
 *
 * @package BizHub\Applications\Exceptions
 */
final class ApplicationNotFoundException extends RuntimeException
{
    /**
     * Create an exception for a missing application UUID.
     */
    public static function withUuid(string $uuid): self
    {
        return new self(
            sprintf('Application with UUID "%s" could not be found.', $uuid)
        );
    }
}
