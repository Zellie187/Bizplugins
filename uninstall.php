<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * This file removes only plugin-specific configuration data.
 * User-generated content, uploads and business records are intentionally
 * preserved unless explicitly removed by future uninstall settings.
 *
 * @package BizUpKeep\Core
 */
declare(strict_types=1);
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
/**
 * Delete plugin options.
 */
$options = array(
	'bizupkeep_core_version',
	'bizupkeep_core_installed',
);
foreach ( $options as $option ) {
	delete_option( $option );
	delete_site_option( $option );
}
/**
 * Action hook for future BizUpKeep modules.
 *
 * Allows extensions to clean up their own data during uninstall.
 */
do_action( 'bizupkeep_core_uninstall' );
