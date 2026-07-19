<?php

declare(strict_types=1);

namespace BizHub\Framework\Contracts;

/**
 * Configuration repository contract.
 *
 * Provides access to framework configuration values.
 *
 * @package BizHub\Framework\Contracts
 */
interface ConfigInterface
{
    /**
     * Determine whether a configuration key exists.
     *
     * @param string $key Dot notation configuration key.
     *
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Retrieve a configuration value.
     *
     * @param string $key     Dot notation configuration key.
     * @param mixed  $default Default value.
     *
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Set a configuration value.
     *
     * @param string $key   Dot notation configuration key.
     * @param mixed  $value Configuration value.
     *
     * @return void
     */
    public function set(string $key, mixed $value): void;

    /**
     * Retrieve all configuration values.
     *
     * @return array<string, mixed>
     */
    public function all(): array;
}
