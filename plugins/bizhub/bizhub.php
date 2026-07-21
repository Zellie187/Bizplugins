<?php
/**
 * Plugin Name: BizHub
 * Plugin URI: https://bizupkeep.co.za
 * Description: Enterprise Business Management Platform.
 * Version: 0.2.5
 * Author: BizUpKeep
 * Author URI: https://bizupkeep.co.za
 * Requires at least: 6.7
 * Requires PHP: 8.2
 * Text Domain: bizhub
 * Domain Path: /languages
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('BIZHUB_PLUGIN_FILE', __FILE__);
define('BIZHUB_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('BIZHUB_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once BIZHUB_PLUGIN_PATH . 'vendor/autoload.php';

use BizHub\Framework\Bootstrap\Application;
use BizHub\Framework\Install\Activator;
use BizHub\Framework\Install\Deactivator;

register_activation_hook(__FILE__, static function (): void {
    (new Activator())->activate();
});

register_deactivation_hook(__FILE__, static function (): void {
    (new Deactivator())->deactivate();
});

add_action(
    'plugins_loaded',
    static function (): void {

        $application = new Application();

        $application->boot();

    }
);

/**
 * Return the booted BizHub Application instance.
 *
 * Returns null if called before the 'plugins_loaded' action has
 * fired, or before BizHub has finished booting. External plugins
 * built on top of BizHub should resolve dependencies from
 * bizhub()?->container() rather than building their own container.
 *
 * @return Application|null
 */
function bizhub(): ?Application
{
    return Application::instance();
}
