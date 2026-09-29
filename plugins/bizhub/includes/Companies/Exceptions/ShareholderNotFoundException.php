<?php

declare(strict_types=1);

namespace BizHub\Companies\Exceptions;

use RuntimeException;

/**
 * Thrown when a shareholder cannot be found.
 *
 * @package BizHub\Companies\Exceptions
 */
final class ShareholderNotFoundException extends RuntimeException
{
    /**
     * Create an exception for a missing shareholder UUID.
     *
     * @param string $uuid Shareholder UUID.
     *
     * @return self
     */
    public static function withUuid(string $uuid): self
    {
        return new self(
            sprintf(
                'Shareholder with UUID "%s" could not be found.',
                $uuid
            )
        );
    }
}
