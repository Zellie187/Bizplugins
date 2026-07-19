<?php

declare(strict_types=1);

namespace BizHub\Admin\Providers;

use BizHub\Admin\AdminMenu;
use BizHub\Framework\Providers\ServiceProvider;

/**
 * Administration Service Provider.
 *
 * @package BizHub\Admin\Providers
 */
final class AdminServiceProvider extends ServiceProvider
{
    public function __construct(
        private readonly AdminMenu $adminMenu
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
        if (! is_admin()) {
            return;
        }

        $this->adminMenu->register();
    }
}
