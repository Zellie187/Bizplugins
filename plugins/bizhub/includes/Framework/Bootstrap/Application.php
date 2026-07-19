<?php

declare(strict_types=1);

namespace BizHub\Framework\Bootstrap;

use BizHub\Admin\Providers\AdminServiceProvider;
use BizHub\Api\ApiServiceProvider;
use BizHub\Applications\Providers\ApplicationServiceProvider;
use BizHub\ClientPortal\Providers\ClientServiceProvider;
use BizHub\Companies\Providers\CompanyServiceProvider;
use BizHub\Dashboard\Providers\DashboardServiceProvider;
use BizHub\Framework\Container\ContainerFactory;
use BizHub\Framework\Database\Providers\DatabaseServiceProvider;
use BizHub\Framework\Events\EventServiceProvider;
use BizHub\Documents\Providers\DocumentServiceProvider;
use BizHub\Framework\Install\Providers\InstallServiceProvider;
use BizHub\Framework\Registries\ProviderRegistry;
use BizHub\Integrations\Forminator\ServiceProvider as ForminatorServiceProvider;
use BizHub\Integrations\WooCommerce\ServiceProvider as WooCommerceServiceProvider;
use BizHub\Notifications\Providers\NotificationServiceProvider;
use BizHub\Reporting\Providers\ReportingServiceProvider;
use BizHub\Security\Auth\Providers\AuthServiceProvider;
use BizHub\Security\Authorization\Providers\AuthorizationServiceProvider;
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
     * Singleton instance, set once boot() has run.
     */
    private static ?Application $instance = null;

    /**
     * Dependency Injection Container.
     */
    private Container $container;

    /**
     * Provider Registry.
     */
    private ProviderRegistry $providerRegistry;

    /**
     * Constructor.
     */
    public function __construct()
    {
        Constants::register();

        $this->container = ContainerFactory::create();

        $this->providerRegistry = new ProviderRegistry(
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
         * Extension point:
         * Allow external plugins built on top of BizHub (e.g. BizUpKeep
         * Workflow) to register their own Service Providers into this
         * same container/registry, so their services participate in
         * the same register/boot lifecycle instead of bypassing it.
         */
        do_action('bizhub/register_providers', $this->providerRegistry, $this->container);

        /*
         * First pass:
         * Register every provider.
         */
        $this->providerRegistry->register();

        /*
         * Second pass:
         * Boot every provider.
         */
        $this->providerRegistry->boot();

        self::$instance = $this;

        /*
         * Fires once BizHub and every registered provider (core and
         * external) has finished booting.
         */
        do_action('bizhub/booted', $this);
    }

    /**
     * Return the booted Application instance, or null if BizHub has
     * not finished booting yet.
     */
    public static function instance(): ?self
    {
        return self::$instance;
    }

    /**
     * Register Framework and Security Providers.
     *
     * @return void
     */
    private function registerProviders(): void
    {
        $providers = [
            DatabaseServiceProvider::class,
            InstallServiceProvider::class,
            EventServiceProvider::class,
            AuthServiceProvider::class,
            AuthorizationServiceProvider::class,
            CompanyServiceProvider::class,
            ClientServiceProvider::class,
            ApplicationServiceProvider::class,
            DocumentServiceProvider::class,
            WooCommerceServiceProvider::class,
            ForminatorServiceProvider::class,
            DashboardServiceProvider::class,
            NotificationServiceProvider::class,
            ApiServiceProvider::class,
            AdminServiceProvider::class,
            ReportingServiceProvider::class,
        ];

        foreach ($providers as $provider) {
            $this->providerRegistry->add($provider);
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
