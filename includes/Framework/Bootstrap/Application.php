<?php

declare(strict_types=1);

namespace BizHub\Framework\Bootstrap;

use BizHub\Framework\Container\ContainerFactory;
use BizHub\Framework\Providers\ProviderRepository;
use BizHub\Platform\Authorization\Providers\AuthorizationServiceProvider;
use DI\Container;

/**
 * BizHub
 *
 * Enterprise Business Management Platform
 *
 * Main application bootstrap responsible for creating the
 * Dependency Injection container and managing the provider lifecycle.
 *
 * @package BizHub
 * @subpackage Framework\Bootstrap
 * @since 0.2.0
 */
final class Application
{
    /**
     * Dependency Injection Container.
     */
    private Container $container;

    /**
     * Provider Repository.
     */
    private ProviderRepository $providerRepository;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->container = ContainerFactory::create();

        $this->providerRepository = new ProviderRepository(
            $this->container
        );
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
         * Register every provider.
         */
        $this->providerRepository->register();

        /*
         * Second pass:
         * Boot every provider.
         */
        $this->providerRepository->boot();
    }

    /**
     * Register Framework and Platform Providers.
     *
     * @return void
     */
    private function registerProviders(): void
    {
        $providers = [
            AuthorizationServiceProvider::class,
        ];

        foreach ($providers as $provider) {
            $this->providerRepository->add($provider);
        }
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