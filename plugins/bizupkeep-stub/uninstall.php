<?php
/**
 * BizUpKeep Stub Plugin Uninstall Script
 *
 * Runs only when the user deletes the plugin from wp-admin (not on
 * deactivation). Deliberately self-contained - does not rely on
 * Composer autoloading - so cleanup still works even if vendor/ is
 * missing or broken.
 *
 * The company-to-Stub-business links are only deleted if the user has
 * explicitly opted in via the "Delete all BizUpKeep Stub data on
 * uninstall" setting; otherwise this plugin's table and options are
 * left in place so reinstalling it doesn't lose the provisioning
 * record (and doesn't risk re-creating duplicate Stub businesses).
 * This never touches data actually held by Stub itself, nor BizHub's/
 * Core's/Bookkeeping's own tables, options or roles.
 *
 * @package BizHub\Stub
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! get_option( 'bizupkeep_stub_delete_data_on_uninstall', false ) ) {
	return;
}

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bizhub_stub_businesses" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is a fixed internal literal, not user input.

$options = array(
	'bizupkeep_stub_db_version',
	'bizupkeep_stub_dependency_notice',
	'bizupkeep_stub_delete_data_on_uninstall',
	'bizupkeep_stub_api_key',
	'bizupkeep_stub_app_id',
	'bizupkeep_stub_environment',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

foreach ( array( 'administrator', 'bizhub_administrator', 'bizhub_manager' ) as $role_name ) {
	$role = get_role( $role_name );

	if ( null === $role ) {
		continue;
	}

	foreach ( array( 'bizupkeep_stub.manage_stub' ) as $capability ) {
		$role->remove_cap( $capability );
	}
}
