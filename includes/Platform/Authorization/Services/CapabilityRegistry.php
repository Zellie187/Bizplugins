<?php

declare(strict_types=1);

namespace BizHub\Platform\Authorization\Services;

/**
 * Capability Registry.
 *
 * Stores every capability registered by the Platform
 * and installed BizHub modules.
 *
 * @package BizHub\Platform\Authorization\Services
 */
final class CapabilityRegistry
{
    /**
     * Registered capabilities.
     *
     * @var array<string,bool>
     */
    private array $capabilities = [];

    /**
     * Register a capability.
     *
     * @param string $capability Capability name.
     *
     * @return void
     */
    public function register(string $capability): void
    {
        $this->capabilities[$capability] = true;
    }

    /**
     * Register multiple capabilities.
     *
     * @param array<int,string> $capabilities Capability list.
     *
     * @return void
     */
    public function registerMany(array $capabilities): void
    {
        foreach ($capabilities as $capability) {
            $this->register($capability);
        }
    }

    /**
     * Determine whether a capability exists.
     *
     * @param string $capability Capability name.
     *
     * @return bool
     */
    public function has(string $capability): bool
    {
        return isset($this->capabilities[$capability]);
    }

    /**
     * Return every registered capability.
     *
     * @return array<int,string>
     */
    public function all(): array
    {
        return array_keys($this->capabilities);
    }

    /**
     * Return total registered capabilities.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->capabilities);
    }
}