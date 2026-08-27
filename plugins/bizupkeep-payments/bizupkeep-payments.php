<?php

/**
 * Plugin Name:       BizUpKeep Payments
 * Plugin URI:        https://bizupkeep.co.za
 * Description:       Payment gateway integration (Yoco, SnapScan) for the BizUpKeep platform - lets a client choose a service and generate/pay a real Invoice for it, replacing WooCommerce checkout.
 * Version:           1.0.0
 * Requires at least: 6.7
 * Requires PHP:      8.2
 * Requires Plugins:  bizhub, bizupkeep-core, bizupkeep-workflow, bizupkeep-bookkeeping
 * Author:            BizUpKeep
 * Author URI:        https://bizupkeep.co.za
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bizupkeep-payments
 * Domain Path:       /languages
 *
 * @package BizHub\Payments
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('BIZUPKEEP_PAYMENTS_VERSION', '1.0.0');
define('BIZUPKEEP_PAYMENTS_FILE', __FILE__);
define('BIZUPKEEP_PAYMENTS_PATH', plugin_dir_path(__FILE__));
define('BIZUPKEEP_PAYMENTS_URL', plugin_dir_url(__FILE__));
define('BIZUPKEEP_PAYMENTS_BASENAME', plugin_basename(__FILE__));

require_once BIZUPKEEP_PAYMENTS_PATH . 'vendor/autoload.php';

use BizHub\Payments\Admin\Providers\PaymentsAdminServiceProvider;
use BizHub\Payments\Bootstrap\Constants;
use BizHub\Payments\Bootstrap\DependencyGuard;
use BizHub\Payments\Bootstrap\Plugin;
use BizHub\Payments\Install\Activator;
use BizHub\Payments\Install\Deactivator;
use BizHub\Payments\Providers\PaymentsServiceProvider;
use BizHub\Framework\Registries\ProviderRegistry;
use DI\Container;

/*
 * Registered unconditionally (not gated behind DependencyGuard or
 * 'plugins_loaded'): Activator needs these path constants during
 * activation, which can run before 'plugins_loaded' has ever fired
 * for this plugin in the current request.
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
 * BizUpKeep Payments never builds its own Dependency Injection
 * container: it contributes its service bindings and Service
 * Providers into BizHub's shared container via the two extension
 * points BizHub exposes for this purpose. Both hooks below are
 * registered at file inclusion time (i.e. now), which is always
 * before 'plugins_loaded' fires for any plugin - so registration
 * order between "bizhub", "bizupkeep-core", "bizupkeep-workflow",
 * "bizupkeep-bookkeeping" and "bizupkeep-payments" does not matter
 * here.
 *
 * Both callbacks only run BizHub-side code, which only executes if
 * BizHub itself is active, so no dependency check is needed inside
 * them for BizHub - only for the three sibling plugins, checked below.
 */
add_filter(
    'bizhub/container_definitions',
    static function (array $definitions): array {
        $definitions[] = BIZUPKEEP_PAYMENTS_PATH . 'includes/Container/definitions.php';

        return $definitions;
    }
);

add_action(
    'bizhub/register_providers',
    static function (ProviderRegistry $providerRegistry, Container $container): void {
        if (! DependencyGuard::coreActive() || ! DependencyGuard::workflowActive() || ! DependencyGuard::bookkeepingActive()) {
            return;
        }

        $providerRegistry->add(PaymentsServiceProvider::class);
        $providerRegistry->add(PaymentsAdminServiceProvider::class);
    },
    10,
    2
);

/*
 * By the time this fires (default priority 10, registered after
 * BizHub's own 'plugins_loaded' callback thanks to the priority 20
 * below), BizHub - if active - has already built its container and
 * booted every provider, including PaymentsServiceProvider. This is
 * where BizUpKeep Payments wires up its own WordPress-facing surface
 * (translations, webhook routes) and where the full dependency check
 * (BizHub + all three siblings) is finally enforced.
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
