<?php

declare(strict_types=1);

namespace BizHub\Documents\Exceptions;

use RuntimeException;

/**
 * Thrown when a user attempts to access a document they do not own.
 *
 * @package BizHub\Documents\Exceptions
 */
final class DocumentAccessDeniedException extends RuntimeException
{
    /**
     * Create an exception for a denied access attempt.
     */
    public static function forUser(string $documentUuid, int $userId): self
    {
        return new self(
            sprintf('User %d does not have access to document "%s".', $userId, $documentUuid)
        );
    }
}
