<?php

declare(strict_types=1);

namespace BizHub\Security\Middleware;

use BizHub\Security\Authorization\Contracts\AuthorizationServiceInterface;
use BizHub\Security\Auth\SessionManager;

/**
 * Verifies that the current user holds a required capability.
 *
 * @package BizHub\Security\Middleware
 */
final class Authorize
{
    public function __construct(
        private readonly AuthorizationServiceInterface $authorization,
        private readonly SessionManager $session
    ) {
    }

    /**
     * Determine whether the current user may perform an action.
     *
     * @param array<string,mixed> $context
     */
    public function check(string $capability, array $context = []): bool
    {
        $userId = $this->session->currentUserId();

        if ($userId === null) {
            return false;
        }

        return $this->authorization->can($userId, $capability, $context);
    }
}
