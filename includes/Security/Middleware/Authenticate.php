<?php

declare(strict_types=1);

namespace BizHub\Security\Middleware;

use BizHub\Security\Auth\SessionManager;

/**
 * Verifies that a request is coming from an authenticated user.
 *
 * @package BizHub\Security\Middleware
 */
final class Authenticate
{
    public function __construct(
        private readonly SessionManager $session
    ) {
    }

    /**
     * Determine whether the current request may proceed.
     */
    public function check(): bool
    {
        return $this->session->isActive();
    }
}
