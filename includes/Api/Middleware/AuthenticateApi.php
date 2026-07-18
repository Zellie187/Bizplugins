<?php

declare(strict_types=1);

namespace BizHub\Api\Middleware;

use BizHub\Security\Middleware\Authenticate;
use WP_REST_Request;

/**
 * REST API permission callback requiring an authenticated user.
 *
 * Usable directly as a route's 'permission_callback'.
 *
 * @package BizHub\Api\Middleware
 */
final class AuthenticateApi
{
    public function __construct(
        private readonly Authenticate $authenticate
    ) {
    }

    /**
     * Determine whether the current request may proceed.
     */
    public function __invoke(WP_REST_Request $request): bool
    {
        return $this->authenticate->check();
    }
}
