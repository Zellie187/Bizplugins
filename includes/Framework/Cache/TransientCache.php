<?php

declare(strict_types=1);

namespace BizHub\Framework\Cache;

/**
 * Cache backend built on the WordPress Transients API.
 *
 * Suitable for values that should persist across requests and,
 * depending on the environment, across page caches.
 *
 * @package BizHub\Framework\Cache
 */
final class TransientCache implements Cache
{
    public function __construct(
        private readonly string $prefix = 'bizhub_'
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = get_transient($this->prefixed($key));

        return $value === false ? $default : $value;
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        return set_transient($this->prefixed($key), $value, $ttl);
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $key): bool
    {
        return get_transient($this->prefixed($key)) !== false;
    }

    /**
     * {@inheritDoc}
     */
    public function forget(string $key): bool
    {
        return delete_transient($this->prefixed($key));
    }

    /**
     * {@inheritDoc}
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $value = get_transient($this->prefixed($key));

        if ($value !== false) {
            return $value;
        }

        $value = $callback();

        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Prefix a cache key.
     */
    private function prefixed(string $key): string
    {
        return $this->prefix . $key;
    }
}
