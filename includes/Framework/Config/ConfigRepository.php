<?php

declare(strict_types=1);

namespace BizHub\Framework\Config;

use BizHub\Framework\Contracts\ConfigInterface;

/**
 * Framework configuration repository.
 *
 * Loads and provides access to the configuration files located in the
 * plugin's /config directory.
 *
 * @package BizHub\Framework\Config
 */
final class ConfigRepository implements ConfigInterface
{
    /**
     * Loaded configuration.
     *
     * @var array<string, mixed>
     */
    private array $items = [];

    /**
     * Constructor.
     *
     * @param string $configPath Configuration directory.
     */
    public function __construct(
        private readonly string $configPath
    ) {
        $this->load();
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * {@inheritDoc}
     */
    public function get(
        string $key,
        mixed $default = null
    ): mixed {
        $segments = explode('.', $key);

        $value = $this->items;

        foreach ($segments as $segment) {
            if (
                ! is_array($value)
                || ! array_key_exists($segment, $value)
            ) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * {@inheritDoc}
     */
    public function set(
        string $key,
        mixed $value
    ): void {
        $segments = explode('.', $key);

        $config =& $this->items;

        foreach ($segments as $segment) {
            if (
                ! isset($config[$segment])
                || ! is_array($config[$segment])
            ) {
                $config[$segment] = [];
            }

            $config =& $config[$segment];
        }

        $config = $value;
    }

    /**
     * {@inheritDoc}
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Load configuration files.
     *
     * @return void
     */
    private function load(): void
    {
        if (! is_dir($this->configPath)) {
            return;
        }

        $files = glob($this->configPath . '*.php');

        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            $name = pathinfo(
                $file,
                PATHINFO_FILENAME
            );

            $config = require $file;

            if (is_array($config)) {
                $this->items[$name] = $config;
            }
        }
    }
}