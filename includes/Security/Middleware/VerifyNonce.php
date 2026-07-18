<?php

declare(strict_types=1);

namespace BizHub\Security\Middleware;

/**
 * Verifies a WordPress nonce.
 *
 * @package BizHub\Security\Middleware
 */
final class VerifyNonce
{
    /**
     * Determine whether the given nonce is valid for the given action.
     */
    public function check(string $nonce, string $action): bool
    {
        return wp_verify_nonce($nonce, $action) !== false;
    }
}
