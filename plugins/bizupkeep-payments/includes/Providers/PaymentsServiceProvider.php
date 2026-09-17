<?php

declare(strict_types=1);

namespace BizHub\Payments\Providers;

use BizHub\Framework\Providers\ServiceProvider;

/**
 * Registers BizUpKeep Payments into BizHub's shared container/provider
 * lifecycle. Empty: every binding this plugin needs is declared in
 * includes/Container/definitions.php and resolved via autowiring, and
 * nothing here needs boot-time registration work of its own (unlike
 * PaymentsAdminServiceProvider, which registers capabilities/menus).
 * Mirrors BizUpKeep Core's CoreServiceProvider.
 *
 * @package BizHub\Payments\Providers
 */
final class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
    }
}
