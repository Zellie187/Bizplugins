<?php

/**
 * Plugin Name:       BizUpKeep Workflow
 * Plugin URI:        https://bizupkeep.co.za
 * Description:       Business process automation and workflow engine for the BizUpKeep platform. Extends the BizHub Framework with a workflow-driven, event-driven business process layer.
 * Version:           1.12.0
 * Requires at least: 6.7
 * Requires PHP:      8.2
 * Requires Plugins:  bizhub, bizupkeep-core
 * Author:            BizUpKeep
 * Author URI:        https://bizupkeep.co.za
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bizupkeep-workflow
 * Domain Path:       /languages
 *
 * @package BizHub\Workflow
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('BIZUPKEEP_WORKFLOW_VERSION', '1.12.0');
define('BIZUPKEEP_WORKFLOW_FILE', __FILE__);
define('BIZUPKEEP_WORKFLOW_PATH', plugin_dir_path(__FILE__));
define('BIZUPKEEP_WORKFLOW_URL', plugin_dir_url(__FILE__));
define('BIZUPKEEP_WORKFLOW_BASENAME', plugin_basename(__FILE__));

require_once BIZUPKEEP_WORKFLOW_PATH . 'vendor/autoload.php';

use BizHub\Workflow\Admin\Providers\QualityReviewAdminServiceProvider;
use BizHub\Workflow\Admin\Providers\WorkflowBoardAdminServiceProvider;
use BizHub\Workflow\Admin\Providers\WorkflowDashboardAdminServiceProvider;
use BizHub\Workflow\Bootstrap\Constants;
use BizHub\Workflow\Bootstrap\DependencyGuard;
use BizHub\Workflow\Bootstrap\Plugin;
use BizHub\Workflow\Install\Activator;
use BizHub\Workflow\Install\Deactivator;
use BizHub\Workflow\Providers\AnnualReturnServiceProvider;
use BizHub\Workflow\Providers\CompanyAmendmentServiceProvider;
use BizHub\Workflow\Providers\CompanyRegistrationServiceProvider;
use BizHub\Workflow\Providers\WorkflowServiceProvider;
use BizHub\Framework\Registries\ProviderRegistry;
use DI\Container;

/*
 * Registered unconditionally (not gated behind DependencyGuard or
 * 'plugins_loaded'): Activator and RoleGrant need these path
 * constants during activation, which can run before 'plugins_loaded'
 * has ever fired for this plugin in the current request.
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
 * BizUpKeep Workflow never builds its own Dependency Injection
 * container: it contributes its service bindings and Service Provider
 * into BizHub's shared container via the two extension points BizHub
 * exposes for this purpose. Both hooks below are registered at file
 * inclusion time (i.e. now), which is always before 'plugins_loaded'
 * fires for any plugin - so registration order between "bizhub",
 * "bizupkeep-core" and "bizupkeep-workflow" does not matter here.
 *
 * Both callbacks only run BizHub-side code, which only executes if
 * BizHub itself is active, so no dependency check is needed inside
 * them for BizHub - only for BizUpKeep Core, checked below.
 */
add_filter(
    'bizhub/container_definitions',
    static function (array $definitions): array {
        $definitions[] = BIZUPKEEP_WORKFLOW_PATH . 'includes/Container/definitions.php';

        return $definitions;
    }
);

add_action(
    'bizhub/register_providers',
    static function (ProviderRegistry $providerRegistry, Container $container): void {
        if (! DependencyGuard::coreActive()) {
            return;
        }

        /*
         * The engine itself, then every concrete workflow type built
         * on top of it. Additional workflow types (Director Changes,
         * Address Changes, etc.) register here as they are built.
         */
        $providerRegistry->add(WorkflowServiceProvider::class);
        $providerRegistry->add(CompanyRegistrationServiceProvider::class);
        $providerRegistry->add(CompanyAmendmentServiceProvider::class);
        $providerRegistry->add(AnnualReturnServiceProvider::class);
        $providerRegistry->add(QualityReviewAdminServiceProvider::class);
        $providerRegistry->add(WorkflowDashboardAdminServiceProvider::class);
        $providerRegistry->add(WorkflowBoardAdminServiceProvider::class);
    },
    10,
    2
);

/*
 * By the time this fires (default priority 10, registered after
 * BizHub's own 'plugins_loaded' callback thanks to the priority 20
 * below), BizHub - if active - has already built its container and
 * booted every provider, including WorkflowServiceProvider. This is
 * where BizUpKeep Workflow wires up its own WordPress-facing surface
 * (routes, admin screens, notices) and where the full dependency
 * check (both BizHub and BizUpKeep Core) is finally enforced.
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
