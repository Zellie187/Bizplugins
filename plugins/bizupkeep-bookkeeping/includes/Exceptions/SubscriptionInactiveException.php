<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Exceptions;

/**
 * Raised when a client attempts a paid action (capture) against a
 * company with no active subscription.
 *
 * @package BizHub\Bookkeeping\Exceptions
 */
final class SubscriptionInactiveException extends BookkeepingException
{
    public static function forCompany(string $companyUuid): self
    {
        return new self(sprintf(
            'Company "%s" has no active bookkeeping subscription.',
            $companyUuid
        ));
    }
}
