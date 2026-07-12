<?php

declare(strict_types=1);

/**
 * Plugin Name: BizHub
 * Plugin URI: https://bizupkeep.co.za
 * Description: Secure client portal and business management platform for BizUpKeep.
 * Version: 1.1.0
 * Author: BizUpKeep
 * Author URI: https://bizupkeep.co.za
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bizhub
 * Domain Path: /languages
 *
 * @package BizHub
 */

namespace BizHub;

use BizHub\Application;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Plugin constants.
 */
define('BIZHUB_VERSION', '1.1.0');
define('BIZHUB_FILE', __FILE__);
define('BIZHUB_PATH', plugin_dir_path(__FILE__));
define('BIZHUB_URL', plugin_dir_url(__FILE__));
define('BIZHUB_TEXT_DOMAIN', 'bizhub');

/**
 * Load application.
 */
require_once BIZHUB_PATH . 'includes/Helpers/Autoloader.php';

$application = new Helpers\Autoloader();
$application->register();

Application::instance()->boot();

/**
 * Activation hook.
 *
 * Database installation will be added in the next milestone.
 */
register_activation_hook(
	__FILE__,
	function (): void {
		update_option(
			'bizhub_version',
			BIZHUB_VERSION
		);
	}
);

/**
 * Deactivation hook.
 */
register_deactivation_hook(
	__FILE__,
	function (): void {
		/**
		 * Reserved for future cleanup routines.
		 */
	}
);