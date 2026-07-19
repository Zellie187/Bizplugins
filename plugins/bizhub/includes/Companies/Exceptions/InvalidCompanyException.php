<?php

declare(strict_types=1);

namespace BizHub\Companies\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when company data fails business validation.
 *
 * @package BizHub\Companies\Exceptions
 */
final class InvalidCompanyException extends InvalidArgumentException
{
    /**
     * Create an exception for a duplicate registration number.
     *
     * @param string $registrationNumber Registration number.
     *
     * @return self
     */
    public static function duplicateRegistrationNumber(string $registrationNumber): self
    {
        return new self(
            sprintf(
                'A company with registration number "%s" already exists.',
                $registrationNumber
            )
        );
    }
}
