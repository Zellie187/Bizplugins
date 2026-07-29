<?php

declare(strict_types=1);

namespace BizUpKeep\Core\Admin\Providers;

use BizHub\Framework\Providers\ServiceProvider;
use BizHub\Security\Authorization\Contracts\AuthorizationServiceInterface;
use BizUpKeep\Core\Admin\ServiceCatalogPage;
use BizUpKeep\Core\Policies\Capabilities;

/**
 * Registers BizUpKeep Core's admin surface - currently just the
 * Service Catalog page - into BizHub's shared container/provider
 * lifecycle. Mirrors BizUpKeep Bookkeeping's
 * BookkeepingAdminServiceProvider.
 *
 * @package BizUpKeep\Core\Admin\Providers
 */
final class ServiceCatalogAdminServiceProvider extends ServiceProvider
{
    public function __construct(
        private readonly ServiceCatalogPage $page,
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

        // Priority 20: safely after BizHub's own top-level "bizhub" menu
        // registers (default priority 10) - registering a submenu before
        // its parent top-level page exists corrupts the WP admin menu
        // rather than simply failing, a bug already hit once elsewhere
        // in this codebase (see BookkeepingAdminServiceProvider).
        add_action('admin_menu', [$this, 'addMenuPage'], 20);
    }

    public function addMenuPage(): void
    {
        add_submenu_page(
            'bizhub',
            __('Service Catalog', 'bizupkeep-core'),
            __('Service Catalog', 'bizupkeep-core'),
            Capabilities::MANAGE_SERVICES,
            ServiceCatalogPage::SLUG,
            [$this->page, 'render']
        );
    }
}
