<?php

declare(strict_types=1);

namespace BizHub\Framework\Cache;

/**
 * Contract for cache backends.
 *
 * @package BizHub\Framework\Cache
 */
interface Cache
{
    /**
     * Retrieve a value from the cache.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Store a value in the cache.
     *
     * @param int $ttl Time to live, in seconds. 0 means no expiration.
     */
    public function set(string $key, mixed $value, int $ttl = 0): bool;

    /**
     * Determine whether a key exists in the cache.
     */
    public function has(string $key): bool;

    /**
     * Remove a value from the cache.
     */
    public function forget(string $key): bool;

    /**
     * Retrieve a value from the cache, computing and storing it if missing.
     *
     * @param callable():mixed $callback
     */
    public function remember(string $key, int $ttl, callable $callback): mixed;
}
