<?php

declare(strict_types=1);

namespace BizHub\Framework\Bootstrap;

use BizHub\Platform\Authorization\Providers\AuthorizationServiceProvider;

/**
 * Main BizHub Application.
 *
 * Responsible for bootstrapping the Framework
 * and Platform services.
 *
 * @package BizHub\Framework\Bootstrap
 */
final class Application
{
    /**
     * Registered service providers.
     *
     * @var array<int,object>
     */
    private array $providers = [];

    /**
     * Boot the application.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->registerProviders();

        foreach ($this->providers as $provider) {
            if (method_exists($provider, 'boot')) {
                $provider->boot();
            }
        }
    }

    /**
     * Register Framework service providers.
     *
     * @return void
     */
    private function registerProviders(): void
    {
        $this->providers[] = new AuthorizationServiceProvider();
    }
}