<?php

/**
 * Plugin Name:       BizUpKeep Stub
 * Plugin URI:        https://bizupkeep.co.za
 * Description:       Stub Connect API integration (https://developers.stub.africa) - provisions a Stub business per client company, embeds Stub's accounting widgets in the Client Portal, and gives staff a synchronous read-only dashboard over any client's books. Extends the BizHub Framework.
 * Version:           1.0.0
 * Requires at least: 6.7
 * Requires PHP:      8.2
 * Requires Plugins:  bizhub, bizupkeep-core, bizupkeep-bookkeeping
 * Author:            BizUpKeep
 * Author URI:        https://bizupkeep.co.za
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bizupkeep-stub
 * Domain Path:       /languages
 *
 * @package BizHub\Stub
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('BIZUPKEEP_STUB_VERSION', '1.0.0');
define('BIZUPKEEP_STUB_FILE', __FILE__);
define('BIZUPKEEP_STUB_PATH', plugin_dir_path(__FILE__));
define('BIZUPKEEP_STUB_URL', plugin_dir_url(__FILE__));
define('BIZUPKEEP_STUB_BASENAME', plugin_basename(__FILE__));

require_once BIZUPKEEP_STUB_PATH . 'vendor/autoload.php';

use BizHub\Stub\Admin\Providers\StubAdminServiceProvider;
use BizHub\Stub\Bootstrap\Constants;
use BizHub\Stub\Bootstrap\DependencyGuard;
use BizHub\Stub\Bootstrap\Plugin;
use BizHub\Stub\Install\Activator;
use BizHub\Stub\Install\Deactivator;
use BizHub\Stub\Providers\StubServiceProvider;
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
 * BizUpKeep Stub never builds its own Dependency Injection container:
 * it contributes its service bindings and Service Providers into
 * BizHub's shared container via the two extension points BizHub
 * exposes for this purpose. Both hooks below are registered at file-
 * inclusion time (top-level plugin code, not inside a callback that
 * only runs conditionally) since BizHub reads them before this plugin
 * gets any other chance to run code.
 *
 * Both callbacks only run BizHub-side code, which only executes if
 * BizHub itself is active, so no dependency check is needed inside
 * them for BizHub - only for the two sibling plugins, checked below.
 */
add_filter(
    'bizhub/container_definitions',
    static function (array $definitions): array {
        $definitions[] = BIZUPKEEP_STUB_PATH . 'includes/Container/definitions.php';

        return $definitions;
    }
);

add_action(
    'bizhub/register_providers',
    static function (ProviderRegistry $providerRegistry, Container $container): void {
        if (! DependencyGuard::coreActive() || ! DependencyGuard::bookkeepingActive()) {
            return;
        }

        $providerRegistry->add(StubServiceProvider::class);
        $providerRegistry->add(StubAdminServiceProvider::class);
    },
    10,
    2
);

/*
 * By the time this fires (default priority 10, registered after
 * BizHub's own 'plugins_loaded' callback thanks to the priority 20
 * below), BizHub - if active - has already built its container and
 * booted every provider, including StubServiceProvider. This is where
 * BizUpKeep Stub wires up its own WordPress-facing surface
 * (translations, REST routes) and where the full dependency check
 * (BizHub + both siblings) is finally enforced.
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
