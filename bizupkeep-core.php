<?php

/**
 * Plugin Name:       BizUpKeep Core
 * Plugin URI:        https://github.com/Zellie187/BizUpKeepWebsite
 * Description:       Core functionality for the BizUpKeep platform. Provides the application framework, integrations, and shared services for the BizUpKeep ecosystem, built on top of the BizHub Framework.
 * Version:           1.1.0
 * Requires at least: 6.6
 * Requires PHP:      8.2
 * Requires Plugins:  bizhub
 * Author:            BizUpKeep
 * Author URI:        https://github.com/Zellie187
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bizupkeep-core
 * Domain Path:       /languages
 *
 * @package BizUpKeep\Core
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('BIZUPKEEP_CORE_VERSION', '1.1.0');
define('BIZUPKEEP_CORE_FILE', __FILE__);
define('BIZUPKEEP_CORE_PATH', plugin_dir_path(__FILE__));
define('BIZUPKEEP_CORE_URL', plugin_dir_url(__FILE__));
define('BIZUPKEEP_CORE_BASENAME', plugin_basename(__FILE__));

require_once BIZUPKEEP_CORE_PATH . 'vendor/autoload.php';

use BizHub\Framework\Registries\ProviderRegistry;
use BizUpKeep\Core\Bootstrap\DependencyGuard;
use BizUpKeep\Core\Bootstrap\Plugin;
use BizUpKeep\Core\Install\Activator;
use BizUpKeep\Core\Install\Deactivator;
use BizUpKeep\Core\Providers\CoreServiceProvider;
use DI\Container;

register_activation_hook(
    __FILE__,
    static function (): void {
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
 * BizUpKeep Core never builds its own Dependency Injection container:
 * it contributes its service bindings and Service Provider into
 * BizHub's shared container via the two extension points BizHub
 * exposes for this purpose - the same ones BizUpKeep Workflow uses.
 * Both hooks below are registered at file inclusion time (i.e. now),
 * which is always before 'plugins_loaded' fires for any plugin - so
 * registration order between "bizhub" and "bizupkeep-core" does not
 * matter here.
 *
 * Both callbacks only run BizHub-side code, which only executes if
 * BizHub itself is active, so no dependency check is needed inside
 * them.
 */
add_filter(
    'bizhub/container_definitions',
    static function (array $definitions): array {
        $definitions[] = BIZUPKEEP_CORE_PATH . 'includes/Container/definitions.php';

        return $definitions;
    }
);

add_action(
    'bizhub/register_providers',
    static function (ProviderRegistry $providerRegistry, Container $container): void {
        $providerRegistry->add(CoreServiceProvider::class);
    },
    10,
    2
);

/*
 * By the time this fires (default priority 10, registered after
 * BizHub's own 'plugins_loaded' callback), BizHub - if active - has
 * already built its container and booted every provider, including
 * CoreServiceProvider. This is where BizUpKeep Core wires up its own
 * WordPress-facing surface (translations, assets, notices) and where
 * the dependency check on BizHub is finally enforced.
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

/**
 * Return the booted BizUpKeep Core singleton, or null if BizUpKeep
 * Core has not booted yet (BizHub missing/incompatible, or called
 * before 'plugins_loaded' priority 20).
 */
function bizupkeep_core(): ?Plugin
{
    return DependencyGuard::satisfied() ? Plugin::instance() : null;
}
