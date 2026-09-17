<?php

declare(strict_types=1);

namespace BizHub\Stub\Services;

use BizHub\Stub\Contracts\StubApiClientInterface;
use BizHub\Stub\Contracts\StubAuthTokenServiceInterface;

/**
 * Issues Stub auth tokens, cached in a WP transient just under Stub's
 * own 1-hour expiry so the embedded widgets and the staff dashboard
 * aren't hitting /api/auth/token on every single page load.
 *
 * @package BizHub\Stub\Services
 */
final class StubAuthTokenService implements StubAuthTokenServiceInterface
{
    private const CACHE_TTL = 55 * MINUTE_IN_SECONDS;

    public function __construct(
        private readonly StubApiClientInterface $api
    ) {
    }

    public function getToken(string $stubBusinessUid): string
    {
        $cacheKey = 'bizupkeep_stub_token_' . md5($stubBusinessUid);
        $cached = get_transient($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = $this->api->requestAuthToken($stubBusinessUid);
        $token = $response['token'];

        set_transient($cacheKey, $token, self::CACHE_TTL);

        return $token;
    }
}
