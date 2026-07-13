<?php

declare(strict_types=1);

namespace BizHub\Framework\Bootstrap;

use BizHub\Framework\Container\ContainerFactory;
use BizHub\Framework\Providers\ServiceProvider;
use BizHub\Platform\Authorization\Providers\AuthorizationServiceProvider;
use DI\Container;

/**
 * Main BizHub Application.
 *
 * Responsible for bootstrapping the BizHub Framework,
 * initializing the dependency injection container,
 * registering service providers, and booting the platform.
 *
 * @package BizHub\Framework\Bootstrap
 */
final class Application
{
    /**
     * Dependency Injection Container.
     *
     * @var Container
     */
    private Container $container;

    /**
     * Registered service providers.
     *
     * @var array<int, ServiceProvider>
     */
    private array $providers = [];

    /**
     * Application constructor.
     */
    public function __construct()
    {
        $this->container = ContainerFactory::create();
    }

    /**
     * Boot the application.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->registerProviders();

        /*
         * First pass:
         * Register all providers.
         */
        foreach ($this->providers as $provider) {
            $provider->register();
        }

        /*
         * Second pass:
         * Boot all providers.
         */
        foreach ($this->providers as $provider) {
            $provider->boot();
        }
    }

    /**
     * Register Framework service providers.
     *
     * @return void
     */
    private function registerProviders(): void
    {
        $this->providers[] = $this->container->get(
            AuthorizationServiceProvider::class
        );
    }

    /**
     * Return the Dependency Injection Container.
     *
     * @return Container
     */
    public function container(): Container
    {
        return $this->container;
    }
}