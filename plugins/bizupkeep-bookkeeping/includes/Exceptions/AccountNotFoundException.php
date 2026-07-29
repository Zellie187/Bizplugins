<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Exceptions;

/**
 * Raised when an account UUID/code does not resolve, or resolves to an
 * account belonging to a different company, or an inactive account.
 *
 * @package BizHub\Bookkeeping\Exceptions
 */
final class AccountNotFoundException extends BookkeepingException
{
    public static function withUuid(string $uuid): self
    {
        return new self(sprintf('No account found with UUID "%s".', $uuid));
    }

    public static function withCode(string $companyUuid, string $code): self
    {
        return new self(sprintf('No account with code "%s" found for company "%s".', $code, $companyUuid));
    }

    public static function notOwnedByCompany(string $accountUuid, string $companyUuid): self
    {
        return new self(sprintf(
            'Account "%s" does not belong to company "%s".',
            $accountUuid,
            $companyUuid
        ));
    }

    public static function inactive(string $accountUuid): self
    {
        return new self(sprintf('Account "%s" is inactive and cannot be posted to.', $accountUuid));
    }
}
