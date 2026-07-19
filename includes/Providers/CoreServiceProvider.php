<?php

declare(strict_types=1);

namespace BizUpKeep\Core\Providers;

use BizHub\Framework\Providers\ServiceProvider;

/**
 * Registers BizUpKeep Core into BizHub's shared container/provider
 * lifecycle.
 *
 * Empty for now - Core has no runtime registration work of its own
 * yet. This exists so Core participates in the same
 * 'bizhub/register_providers' lifecycle as every other BizUpKeep
 * module from day one, rather than bolting it on later once Core
 * actually has services to register.
 *
 * @package BizUpKeep\Core\Providers
 */
final class CoreServiceProvider extends ServiceProvider
{
    /**
     * {@inheritDoc}
     */
    public function register(): void
    {
        // Bindings, if any, are declared in includes/Container/definitions.php.
    }

    /**
     * {@inheritDoc}
     */
    public function boot(): void
    {
    }
}
