<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Providers;

use BizHub\Framework\Providers\ServiceProvider;

/**
 * Exists for structural parity with the vertical-module-per-provider
 * convention this plugin follows (see AccountsServiceProvider). No
 * boot-time wiring needed beyond the Container/definitions.php bindings.
 *
 * @package BizHub\Bookkeeping\Providers
 */
final class ReportingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
    }
}
