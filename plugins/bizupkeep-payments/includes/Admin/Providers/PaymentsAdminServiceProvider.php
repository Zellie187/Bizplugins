<?php

declare(strict_types=1);

namespace BizHub\Payments\Admin\Providers;

use BizHub\Framework\Providers\ServiceProvider;
use BizHub\Payments\Admin\PaymentsDashboardPage;
use BizHub\Payments\Admin\PaymentSettingsPage;
use BizHub\Payments\Policies\Capabilities;
use BizHub\Security\Authorization\Contracts\AuthorizationServiceInterface;

/**
 * Registers BizUpKeep Payments' admin surface (Payments dashboard +
 * gateway settings) into BizHub's shared container/provider lifecycle.
 * Mirrors BizUpKeep Core's ServiceCatalogAdminServiceProvider.
 *
 * @package BizHub\Payments\Admin\Providers
 */
final class PaymentsAdminServiceProvider extends ServiceProvider
{
    public function __construct(
        private readonly PaymentsDashboardPage $dashboardPage,
        private readonly PaymentSettingsPage $settingsPage,
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
        // menu registers (default priority 10) - registering a
        // submenu before its parent top-level page exists corrupts
        // the WP admin menu rather than simply failing, a bug already
        // hit once elsewhere in this codebase.
        add_action('admin_menu', [$this, 'addMenuPages'], 25);
    }

    public function addMenuPages(): void
    {
        add_submenu_page(
            'bizhub',
            __('Payments', 'bizupkeep-payments'),
            __('Payments', 'bizupkeep-payments'),
            Capabilities::MANAGE_PAYMENTS,
            PaymentsDashboardPage::SLUG,
            [$this->dashboardPage, 'render']
        );

        add_submenu_page(
            'bizhub',
            __('Payment Settings', 'bizupkeep-payments'),
            __('Payment Settings', 'bizupkeep-payments'),
            Capabilities::MANAGE_PAYMENTS,
            PaymentSettingsPage::SLUG,
            [$this->settingsPage, 'render']
        );
    }
}
