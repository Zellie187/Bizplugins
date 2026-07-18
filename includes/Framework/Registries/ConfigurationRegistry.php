<?php

declare(strict_types=1);

namespace BizHub\Framework\Registries;

/**
 * Registry of named, module-provided configuration sets.
 *
 * Distinct from ConfigRepository, which loads configuration files from
 * the plugin's /config directory. This registry allows individual
 * modules to publish their own default configuration at boot time.
 *
 * @package BizHub\Framework\Registries
 */
final class ConfigurationRegistry
{
    /**
     * @var array<string,array<string,mixed>>
     */
    private array $configurations = [];

    /**
     * Register a named configuration set.
     *
     * @param array<string,mixed> $configuration
     */
    public function register(string $namespace, array $configuration): void
    {
        $this->configurations[$namespace] = $configuration;
    }

    /**
     * Determine whether a configuration namespace has been registered.
     */
    public function has(string $namespace): bool
    {
        return isset($this->configurations[$namespace]);
    }

    /**
     * Retrieve a registered configuration set.
     *
     * @return array<string,mixed>
     */
    public function get(string $namespace): array
    {
        return $this->configurations[$namespace] ?? [];
    }

    /**
     * Return every registered configuration set.
     *
     * @return array<string,array<string,mixed>>
     */
    public function all(): array
    {
        return $this->configurations;
    }
}
