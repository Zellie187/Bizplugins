<?php

declare(strict_types=1);

namespace BizHub\Framework\Providers;

use BizHub\Framework\Application\Application;
use BizHub\Framework\Contracts\ServiceProviderInterface;

/**
 * Base service provider.
 *
 * Provides common functionality for all BizHub providers.
 *
 * @package BizHub\Framework\Providers
 */
abstract class ServiceProvider implements ServiceProviderInterface
{
    /**
     * Application instance.
     *
     * @var Application
     */
    protected Application $application;

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
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
    }

    /**
     * Retrieve application instance.
     *
     * @return Application
     */
    protected function app(): Application
    {
        return $this->application;
    }
}