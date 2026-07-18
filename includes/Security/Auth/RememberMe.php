<?php

declare(strict_types=1);

namespace BizHub\Security\Auth;

/**
 * Manages extended "remember me" authentication cookie duration.
 *
 * @package BizHub\Security\Auth
 */
final class RememberMe
{
    /**
     * Extended cookie lifetime, in seconds (default: 30 days).
     */
    private const EXTENDED_LIFETIME = 30 * DAY_IN_SECONDS;

    /**
     * Register the filter that extends the auth cookie lifetime when
     * the user has opted to be remembered.
     */
    public function register(): void
    {
        add_filter('auth_cookie_expiration', [$this, 'filterExpiration'], 10, 3);
    }

    /**
     * Filter callback for 'auth_cookie_expiration'.
     */
    public function filterExpiration(int $length, int $userId, bool $remember): int
    {
        return $remember ? self::EXTENDED_LIFETIME : $length;
    }
}
