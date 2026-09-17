<?php

declare(strict_types=1);

namespace BizHub\Stub\Admin\Providers;

use BizHub\Framework\Providers\ServiceProvider;
use BizHub\Security\Authorization\Contracts\AuthorizationServiceInterface;
use BizHub\Stub\Admin\StubDashboardPage;
use BizHub\Stub\Admin\StubSettingsPage;
use BizHub\Stub\Policies\Capabilities;

/**
 * Registers BizUpKeep Stub's admin surface (books dashboard + API
 * settings) into BizHub's shared container/provider lifecycle. Mirrors
 * bizupkeep-payments' PaymentsAdminServiceProvider.
 *
 * @package BizHub\Stub\Admin\Providers
 */
final class StubAdminServiceProvider extends ServiceProvider
{
    public function __construct(
        private readonly StubDashboardPage $dashboardPage,
        private readonly StubSettingsPage $settingsPage,
        private readonly AuthorizationServiceInterface $authorization
    ) {
    }

    public function register(): void
    {
    }

    public function boot(): void
    {
        foreach (Capabilities::all() as $capability) {
            $this->authorization->registerCapability($capability);
        }

        if (! is_admin()) {
            return;
        }

        // Priority 25: safely after BizHub's own top-level "bizhub"
        // menu registers (default priority 10).
        add_action('admin_menu', [$this, 'addMenuPages'], 25);
    }

    public function addMenuPages(): void
    {
        add_submenu_page(
            'bizhub',
            __('Stub Bookkeeping', 'bizupkeep-stub'),
            __('Stub Bookkeeping', 'bizupkeep-stub'),
            Capabilities::MANAGE_STUB,
            StubDashboardPage::SLUG,
            [$this->dashboardPage, 'render']
        );

        add_submenu_page(
            'bizhub',
            __('Stub Settings', 'bizupkeep-stub'),
            __('Stub Settings', 'bizupkeep-stub'),
            Capabilities::MANAGE_STUB,
            StubSettingsPage::SLUG,
            [$this->settingsPage, 'render']
        );
    }
}
