<?php

declare(strict_types=1);

namespace BizHub\Stub\Providers;

use BizHub\Framework\Providers\ServiceProvider;

/**
 * Registers BizUpKeep Stub into BizHub's shared container/provider
 * lifecycle. Empty: every binding this plugin needs is declared in
 * includes/Container/definitions.php and resolved via autowiring.
 * Mirrors bizupkeep-payments' PaymentsServiceProvider.
 *
 * @package BizHub\Stub\Providers
 */
final class StubServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
    }
}
