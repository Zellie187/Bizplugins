<?php
/**
 * Plugin Name:       BizHub
 * Plugin URI:        https://bizhub.co.za
 * Description:       Enterprise Business Management Platform for WordPress.
 * Version:           0.1.0
 * Requires at least: 6.8
 * Requires PHP:      8.2
 * Author:            BizHub
 * Author URI:        https://bizhub.co.za
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bizhub
 * Domain Path:       /languages
 *
 * @package BizHub
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/*
|--------------------------------------------------------------------------
| Framework Constants
|--------------------------------------------------------------------------
*/

define('BIZHUB_VERSION', '0.1.0');
define('BIZHUB_PLUGIN_FILE', __FILE__);
define('BIZHUB_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('BIZHUB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BIZHUB_PLUGIN_BASENAME', plugin_basename(__FILE__));

/*
|--------------------------------------------------------------------------
| Composer Autoloader
|--------------------------------------------------------------------------
*/

$autoload = BIZHUB_PLUGIN_PATH . 'vendor/autoload.php';

if (! file_exists($autoload)) {
    add_action(
        'admin_notices',
        static function (): void {
            ?>
            <div class="notice notice-error">
                <p>
                    <strong>BizHub:</strong>
                    Composer dependencies are missing.
                    Please run <code>composer install</code>.
                </p>
            </div>
            <?php
        }
    );

    return;
}

require_once $autoload;

/*
|--------------------------------------------------------------------------
| Framework Bootstrap
|--------------------------------------------------------------------------
*/

use BizHub\Framework\Bootstrap\Bootstrap;

Bootstrap::boot();