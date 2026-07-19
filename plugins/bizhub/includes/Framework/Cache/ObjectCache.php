<?php

declare(strict_types=1);

namespace BizHub\Framework\Cache;

/**
 * Cache backend built on the WordPress Object Cache API.
 *
 * Suitable for request-scoped or persistent-object-cache-backed values.
 * Unlike TransientCache, values are not guaranteed to persist unless a
 * persistent object cache plugin is active.
 *
 * @package BizHub\Framework\Cache
 */
final class ObjectCache implements Cache
{
    public function __construct(
        private readonly string $group = 'bizhub'
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = wp_cache_get($key, $this->group, false, $found);

        return $found ? $value : $default;
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        return wp_cache_set($key, $value, $this->group, $ttl);
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $key): bool
    {
        wp_cache_get($key, $this->group, false, $found);

        return (bool) $found;
    }

    /**
     * {@inheritDoc}
     */
    public function forget(string $key): bool
    {
        return wp_cache_delete($key, $this->group);
    }

    /**
     * {@inheritDoc}
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $value = wp_cache_get($key, $this->group, false, $found);

        if ($found) {
            return $value;
        }

        $value = $callback();

        $this->set($key, $value, $ttl);

        return $value;
    }
}
