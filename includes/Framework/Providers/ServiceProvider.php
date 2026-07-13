<?php

declare(strict_types=1);

namespace BizHub\Framework\Providers;

/**
 * Base Service Provider.
 *
 * All BizHub service providers extend this class.
 *
 * @package BizHub\Framework\Providers
 */
abstract class ServiceProvider
{
    /**
     * Register services.
     *
     * Override when required.
     */
    public function register(): void
    {
    }

    /**
     * Boot services.
     *
     * Override when required.
     */
    public function boot(): void
    {
    }
}