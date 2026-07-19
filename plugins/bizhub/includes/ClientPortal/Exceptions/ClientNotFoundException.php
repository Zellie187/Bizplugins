<?php

declare(strict_types=1);

namespace BizHub\ClientPortal\Exceptions;

use RuntimeException;

/**
 * Thrown when a client cannot be found.
 *
 * @package BizHub\ClientPortal\Exceptions
 */
final class ClientNotFoundException extends RuntimeException
{
    /**
     * Create an exception for a missing client UUID.
     */
    public static function withUuid(string $uuid): self
    {
        return new self(
            sprintf('Client with UUID "%s" could not be found.', $uuid)
        );
    }

    /**
     * Create an exception for a missing WordPress user ID.
     */
    public static function withWpUserId(int $wpUserId): self
    {
        return new self(
            sprintf('No client account found for WordPress user #%d.', $wpUserId)
        );
    }
}
