<?php

declare(strict_types=1);

namespace BizHub\Companies\Exceptions;

use RuntimeException;

/**
 * Thrown when a company cannot be found.
 *
 * @package BizHub\Companies\Exceptions
 */
final class CompanyNotFoundException extends RuntimeException
{
    /**
     * Create an exception for a missing company UUID.
     *
     * @param string $uuid Company UUID.
     *
     * @return self
     */
    public static function withUuid(string $uuid): self
    {
        return new self(
            sprintf(
                'Company with UUID "%s" could not be found.',
                $uuid
            )
        );
    }

    /**
     * Create an exception for a missing registration number.
     *
     * @param string $registrationNumber Registration number.
     *
     * @return self
     */
    public static function withRegistrationNumber(
        string $registrationNumber
    ): self {
        return new self(
            sprintf(
                'Company with registration number "%s" could not be found.',
                $registrationNumber
            )
        );
    }
}
