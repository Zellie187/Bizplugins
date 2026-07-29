<?php
/**
 * BizUpKeep Bookkeeping Plugin Uninstall Script
 *
 * Runs only when the user deletes the plugin from wp-admin (not on
 * deactivation). Deliberately self-contained - does not rely on
 * Composer autoloading - so cleanup still works even if vendor/ is
 * missing or broken.
 *
 * Ledger data is only deleted if the user has explicitly opted in via
 * the "Delete all BizUpKeep Bookkeeping data on uninstall" setting;
 * otherwise this plugin's tables and options are left in place so
 * reinstalling it doesn't lose a client's books.
 *
 * This script only ever removes rows/capabilities this plugin itself
 * created. It never touches BizHub's own tables, options or roles.
 *
 * @package BizHub\Bookkeeping
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! get_option( 'bizupkeep_bookkeeping_delete_data_on_uninstall', false ) ) {
	return;
}

global $wpdb;

$tables = array(
	'bizhub_bookkeeping_journal_lines',
	'bizhub_bookkeeping_journal_entries',
	'bizhub_bookkeeping_accounts',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is from a fixed internal list, not user input.
}

$options = array(
	'bizupkeep_bookkeeping_db_version',
	'bizupkeep_bookkeeping_dependency_notice',
	'bizupkeep_bookkeeping_delete_data_on_uninstall',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

foreach ( array( 'administrator', 'bizhub_administrator', 'bizhub_manager', 'bizhub_staff', 'bizhub_client' ) as $role_name ) {
	$role = get_role( $role_name );

	if ( null === $role ) {
		continue;
	}

	foreach ( array( 'bookkeeping.view', 'bookkeeping.capture', 'bookkeeping.manage', 'bookkeeping.export' ) as $capability ) {
		$role->remove_cap( $capability );
	}
}
