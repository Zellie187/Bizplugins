<?php

declare(strict_types=1);

namespace BizHub\Dashboard\Providers;

use BizHub\Dashboard\DashboardBuilder;
use BizHub\Framework\Providers\ServiceProvider;

/**
 * Dashboard Service Provider.
 *
 * @package BizHub\Dashboard\Providers
 */
final class DashboardServiceProvider extends ServiceProvider
{
    public function __construct(
        private readonly DashboardBuilder $dashboardBuilder
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function register(): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function boot(): void
    {
    }

    /**
     * Return the dashboard builder.
     */
    public function dashboardBuilder(): DashboardBuilder
    {
        return $this->dashboardBuilder;
    }
}
