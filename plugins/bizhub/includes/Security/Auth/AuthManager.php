<?php

declare(strict_types=1);

namespace BizHub\Security\Auth;

use WP_Error;
use WP_User;

/**
 * Application-facing authentication entry point.
 *
 * Business modules should depend on AuthManager rather than calling
 * WordPress authentication functions or LoginManager/SessionManager
 * directly.
 *
 * @package BizHub\Security\Auth
 */
final class AuthManager
{
    public function __construct(
        private readonly LoginManager $loginManager,
        private readonly SessionManager $sessionManager
    ) {
    }

    /**
     * Determine whether a user is currently authenticated.
     */
    public function check(): bool
    {
        return $this->sessionManager->isActive();
    }

    /**
     * Return the currently authenticated user's ID, or null if none.
     */
    public function id(): ?int
    {
        return $this->sessionManager->currentUserId();
    }

    /**
     * Attempt to authenticate a user.
     *
     * @return WP_User|WP_Error The authenticated user, or a WP_Error on failure.
     */
    public function attempt(string $username, string $password, bool $remember = false): WP_User|WP_Error
    {
        return $this->loginManager->attempt($username, $password, $remember);
    }

    /**
     * Log the current user out.
     */
    public function logout(): void
    {
        $this->sessionManager->destroy();
    }
}
