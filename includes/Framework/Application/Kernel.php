<?php

declare(strict_types=1);

namespace BizHub\Framework\Application;

use BizHub\Framework\Contracts\ServiceProviderInterface;
use BizHub\Framework\Exceptions\FrameworkException;

/**
 * Framework kernel.
 *
 * Controls application startup lifecycle.
 *
 * @package BizHub\Framework\Application
 */
final class Kernel
{
    /**
     * Application instance.
     *
     * @var Application
     */
    private Application $application;

    /**
     * Registered providers.
     *
     * @var array<int, class-string<ServiceProviderInterface>>
     */
    private array $providers = [];

    /**
     * Loaded provider instances.
     *
     * @var array<int, ServiceProviderInterface>
     */
    private array $loadedProviders = [];

    /**
     * Boot status.
     *
     * @var bool
     */
    private bool $booted = false;

    /**
     * Constructor.
     *
     * @param Application $application Application instance.
     */
    public function __construct(Application $application)
    {
        $this->application = $application;
    }

    /**
     * Boot framework.
     *
     * @throws FrameworkException
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->validateEnvironment();

        $this->registerProviders();

        $this->bootProviders();

        $this->booted = true;

        do_action(
            'bizhub_framework_booted',
            $this->application
        );
    }

    /**
     * Register service providers.
     *
     * @return void
     */
    private function registerProviders(): void
    {
        foreach ($this->providers as $provider) {
            $instance = new $provider(
                $this->application
            );

            $instance->register();

            $this->loadedProviders[] = $instance;
        }
    }

    /**
     * Boot service providers.
     *
     * @return void
     */
    private function bootProviders(): void
    {
        foreach ($this->loadedProviders as $provider) {
            $provider->boot();
        }
    }

    /**
     * Add service provider.
     *
     * @param class-string<ServiceProviderInterface> $provider Provider class.
     *
     * @return void
     */
    public function addProvider(string $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * Validate runtime requirements.
     *
     * @throws FrameworkException
     *
     * @return void
     */
    private function validateEnvironment(): void
    {
        if (version_compare(PHP_VERSION, '8.2.0', '<')) {
            throw new FrameworkException(
                'BizHub requires PHP 8.2 or newer.'
            );
        }

        global $wp_version;

        if (
            isset($wp_version)
            && version_compare($wp_version, '6.8', '<')
        ) {
            throw new FrameworkException(
                'BizHub requires WordPress 6.8 or newer.'
            );
        }
    }

    /**
     * Determine whether kernel has booted.
     *
     * @return bool
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Retrieve application.
     *
     * @return Application
     */
    public function application(): Application
    {
        return $this->application;
    }
}