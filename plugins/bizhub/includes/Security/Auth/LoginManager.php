<?php

declare(strict_types=1);

namespace BizHub\Security\Auth;

use WP_Error;
use WP_User;

/**
 * Handles login attempts against WordPress's authentication system.
 *
 * @package BizHub\Security\Auth
 */
final class LoginManager
{
    /**
     * Attempt to authenticate a user.
     *
     * @return WP_User|WP_Error The authenticated user, or a WP_Error on failure.
     */
    public function attempt(string $username, string $password, bool $remember = false): WP_User|WP_Error
    {
        return wp_signon([
            'user_login' => $username,
            'user_password' => $password,
            'remember' => $remember,
        ]);
    }

    /**
     * Log the current user out.
     */
    public function logout(): void
    {
        wp_logout();
    }
}
