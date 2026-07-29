<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Providers;

use BizHub\Framework\Providers\ServiceProvider;

/**
 * Exists for structural parity with the vertical-module-per-provider
 * convention this plugin follows (see AccountsServiceProvider).
 * BankImport needs no runtime boot-time wiring beyond the container
 * bindings in Container/definitions.php.
 *
 * @package BizHub\Bookkeeping\Providers
 */
final class BankImportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
    }
}
