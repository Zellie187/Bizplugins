<?php

declare(strict_types=1);

namespace BizHub\Companies\Exceptions;

use RuntimeException;

/**
 * Thrown when a client attempts to access a company they do not own.
 *
 * @package BizHub\Companies\Exceptions
 */
final class CompanyAccessDeniedException extends RuntimeException
{
    /**
     * Create an exception for a denied access attempt.
     *
     * @param string $uuid     Company UUID.
     * @param int    $clientId Client attempting access.
     *
     * @return self
     */
    public static function forClient(string $uuid, int $clientId): self
    {
        return new self(
            sprintf(
                'Client %d does not have access to company "%s".',
                $clientId,
                $uuid
            )
        );
    }
}
