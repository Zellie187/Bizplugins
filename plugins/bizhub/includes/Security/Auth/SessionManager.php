<?php

declare(strict_types=1);

namespace BizHub\Security\Auth;

/**
 * Wraps WordPress's current-user session state.
 *
 * This is the only class permitted to call WordPress user-session
 * functions directly (is_user_logged_in, wp_get_current_user, wp_logout).
 *
 * @package BizHub\Security\Auth
 */
final class SessionManager
{
    /**
     * Determine whether a user is currently logged in.
     */
    public function isActive(): bool
    {
        return is_user_logged_in();
    }

    /**
     * Return the currently authenticated user's ID, or null if none.
     */
    public function currentUserId(): ?int
    {
        if (! $this->isActive()) {
            return null;
        }

        $id = get_current_user_id();

        return $id > 0 ? $id : null;
    }

    /**
     * Destroy the current session (log the user out).
     */
    public function destroy(): void
    {
        wp_logout();
    }
}
