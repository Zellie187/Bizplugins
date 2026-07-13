<?php

declare(strict_types=1);

namespace BizHub\Framework\Providers;

use DI\Container;

/**
 * BizHub
 *
 * Enterprise Business Management Platform
 *
 * Responsible for managing the registration and boot
 * lifecycle of all Framework and Platform Service Providers.
 *
 * @package BizHub
 * @subpackage Framework\Providers
 * @since 0.2.0
 */
final class ProviderRepository
{
    /**
     * Dependency Injection Container.
     */
    private Container $container;

    /**
     * Registered Service Providers.
     *
     * @var array<class-string<ServiceProvider>, ServiceProvider>
     */
    private array $providers = [];

    /**
     * Constructor.
     *
     * @param Container $container Dependency Injection Container.
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Add a Service Provider.
     *
     * Providers are resolved through the DI container and
     * stored for later registration and booting.
     *
     * @param class-string<ServiceProvider> $providerClass Provider class.
     *
     * @return void
     */
    public function add(string $providerClass): void
    {
        if (isset($this->providers[$providerClass])) {
            return;
        }

        /** @var ServiceProvider $provider */
        $provider = $this->container->get($providerClass);

        $this->providers[$providerClass] = $provider;
    }

    /**
     * Register all providers.
     *
     * @return void
     */
    public function register(): void
    {
        foreach ($this->providers as $provider) {
            $provider->register();
        }
    }

    /**
     * Boot all providers.
     *
     * @return void
     */
    public function boot(): void
    {
        foreach ($this->providers as $provider) {
            $provider->boot();
        }
    }

    /**
     * Return all registered providers.
     *
     * @return array<class-string<ServiceProvider>, ServiceProvider>
     */
    public function all(): array
    {
        return $this->providers;
    }
}