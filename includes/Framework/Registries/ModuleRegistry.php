<?php

declare(strict_types=1);

namespace BizHub\Framework\Registries;

/**
 * Registry of active business modules.
 *
 * Allows the application to discover which business modules are
 * currently enabled without depending on their internals directly.
 *
 * @package BizHub\Framework\Registries
 */
final class ModuleRegistry
{
    /**
     * @var array<string,string>
     */
    private array $modules = [];

    /**
     * Register a module by name and root namespace.
     */
    public function register(string $name, string $namespace): void
    {
        $this->modules[$name] = $namespace;
    }

    /**
     * Determine whether a module has been registered.
     */
    public function has(string $name): bool
    {
        return isset($this->modules[$name]);
    }

    /**
     * Return a registered module's root namespace.
     */
    public function namespaceFor(string $name): ?string
    {
        return $this->modules[$name] ?? null;
    }

    /**
     * Return every registered module as [name => namespace].
     *
     * @return array<string,string>
     */
    public function all(): array
    {
        return $this->modules;
    }
}
