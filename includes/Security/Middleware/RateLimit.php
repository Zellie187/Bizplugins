<?php

declare(strict_types=1);

namespace BizHub\Security\Middleware;

use BizHub\Framework\Cache\Cache;

/**
 * Throttles repeated actions using a fixed-window counter.
 *
 * @package BizHub\Security\Middleware
 */
final class RateLimit
{
    public function __construct(
        private readonly Cache $cache
    ) {
    }

    /**
     * Determine whether the given key is still within its attempt limit,
     * incrementing the counter as a side effect.
     */
    public function attempt(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $cacheKey = 'rate_limit_' . $key;

        $attempts = (int) $this->cache->get($cacheKey, 0);

        if ($attempts >= $maxAttempts) {
            return false;
        }

        $this->cache->set($cacheKey, $attempts + 1, $decaySeconds);

        return true;
    }

    /**
     * Return the number of attempts remaining within the current window.
     */
    public function remaining(string $key, int $maxAttempts): int
    {
        $attempts = (int) $this->cache->get('rate_limit_' . $key, 0);

        return max(0, $maxAttempts - $attempts);
    }

    /**
     * Reset the attempt counter for a key.
     */
    public function clear(string $key): void
    {
        $this->cache->forget('rate_limit_' . $key);
    }
}
