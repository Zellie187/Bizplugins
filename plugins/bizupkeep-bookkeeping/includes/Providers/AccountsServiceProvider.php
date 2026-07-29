<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Providers;

use BizHub\Framework\Providers\ServiceProvider;

/**
 * Exists for structural parity with the vertical-module-per-provider
 * convention this plugin follows (see AccountsServiceProvider,
 * LedgerServiceProvider, ReportingServiceProvider). Accounts needs no
 * runtime boot-time wiring beyond the container bindings in
 * Container/definitions.php - capability registration and admin-menu
 * wiring live in BookkeepingAdminServiceProvider instead, since that
 * provider is guaranteed to always run regardless of which vertical
 * providers exist.
 *
 * @package BizHub\Bookkeeping\Providers
 */
final class AccountsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
    }
}
