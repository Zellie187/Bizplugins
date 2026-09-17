<?php

declare(strict_types=1);

namespace BizHub\Stub\Contracts;

/**
 * Issues short-lived Stub auth tokens for a given business, caching
 * them so every widget render/dashboard read doesn't re-hit
 * /api/auth/token.
 *
 * @package BizHub\Stub\Contracts
 */
interface StubAuthTokenServiceInterface
{
    /**
     * A valid token for this Stub business - either a cached one, or a
     * freshly requested one if none is cached or it has expired.
     */
    public function getToken(string $stubBusinessUid): string;
}
