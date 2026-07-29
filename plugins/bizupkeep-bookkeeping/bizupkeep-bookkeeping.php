<?php

/**
 * Plugin Name:       BizUpKeep Bookkeeping
 * Plugin URI:        https://bizupkeep.co.za
 * Description:       Double-entry bookkeeping (chart of accounts, income/expense capture, financial statements, and Sage/Xero/QuickBooks export) for the BizUpKeep platform. Extends the BizHub Framework.
 * Version:           1.7.1
 * Requires at least: 6.7
 * Requires PHP:      8.2
 * Requires Plugins:  bizhub, bizupkeep-core
 * Author:            BizUpKeep
 * Author URI:        https://bizupkeep.co.za
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bizupkeep-bookkeeping
 * Domain Path:       /languages
 *
 * @package BizHub\Bookkeeping
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('BIZUPKEEP_BOOKKEEPING_VERSION', '1.7.1');
define('BIZUPKEEP_BOOKKEEPING_FILE', __FILE__);
define('BIZUPKEEP_BOOKKEEPING_PATH', plugin_dir_path(__FILE__));
define('BIZUPKEEP_BOOKKEEPING_URL', plugin_dir_url(__FILE__));
define('BIZUPKEEP_BOOKKEEPING_BASENAME', plugin_basename(__FILE__));

require_once BIZUPKEEP_BOOKKEEPING_PATH . 'vendor/autoload.php';

use BizHub\Bookkeeping\Admin\Providers\BookkeepingAdminServiceProvider;
use BizHub\Bookkeeping\Billing\SubscriptionReminderServiceProvider;
use BizHub\Bookkeeping\Bootstrap\Constants;
use BizHub\Bookkeeping\Bootstrap\DependencyGuard;
use BizHub\Bookkeeping\Bootstrap\Plugin;
use BizHub\Bookkeeping\Install\Activator;
use BizHub\Bookkeeping\Install\Deactivator;
use BizHub\Bookkeeping\Providers\AccountsServiceProvider;
use BizHub\Bookkeeping\Providers\BankImportServiceProvider;
use BizHub\Bookkeeping\Providers\LedgerServiceProvider;
use BizHub\Bookkeeping\Providers\ReportingServiceProvider;
use BizHub\Bookkeeping\Recurring\RecurringTransactionServiceProvider;
use BizHub\Framework\Registries\ProviderRegistry;
use DI\Container;

/*
 * Registered unconditionally (not gated behind DependencyGuard or
 * 'plugins_loaded'): Activator needs these path constants during
 * activation, which can run before 'plugins_loaded' has ever fired for
 * this plugin in the current request.
 */
Constants::register();

register_activation_hook(
    __FILE__,
    static function (): void {
        if (! DependencyGuard::satisfied()) {
            return;
        }

        (new Activator())->activate();
    }
);

register_deactivation_hook(
    __FILE__,
    static function (): void {
        (new Deactivator())->deactivate();
    }
);

/*
 * BizUpKeep Bookkeeping never builds its own Dependency Injection
 * container: it contributes its service bindings and Service Providers
 * into BizHub's shared container via the two extension points BizHub
 * exposes for this purpose. Both hooks below are registered at file
 * inclusion time (i.e. now), which is always before 'plugins_loaded'
 * fires for any plugin - so registration order between "bizhub",
 * "bizupkeep-core" and "bizupkeep-bookkeeping" does not matter here.
 */
add_filter(
    'bizhub/container_definitions',
    static function (array $definitions): array {
        $definitions[] = BIZUPKEEP_BOOKKEEPING_PATH . 'includes/Container/definitions.php';

        return $definitions;
    }
);

add_action(
    'bizhub/register_providers',
    static function (ProviderRegistry $providerRegistry, Container $container): void {
        if (! DependencyGuard::coreActive()) {
            return;
        }

        $providerRegistry->add(AccountsServiceProvider::class);
        $providerRegistry->add(LedgerServiceProvider::class);
        $providerRegistry->add(ReportingServiceProvider::class);
        $providerRegistry->add(BookkeepingAdminServiceProvider::class);
        $providerRegistry->add(SubscriptionReminderServiceProvider::class);
        $providerRegistry->add(BankImportServiceProvider::class);
        $providerRegistry->add(RecurringTransactionServiceProvider::class);
    },
    10,
    2
);

/*
 * By the time this fires (default priority 10, registered after
 * BizHub's own 'plugins_loaded' callback thanks to the priority 20
 * below), BizHub - if active - has already built its container and
 * booted every provider, including this plugin's own. This is where
 * BizUpKeep Bookkeeping wires up its own WordPress-facing surface
 * (routes) and where the full dependency check (both BizHub and
 * BizUpKeep Core) is finally enforced.
 */
add_action(
    'plugins_loaded',
    static function (): void {
        DependencyGuard::checkAndNotify();

        if (! DependencyGuard::satisfied()) {
            return;
        }

        Plugin::instance()->boot();
    },
    20
);
