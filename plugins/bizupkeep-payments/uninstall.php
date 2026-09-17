<?php
/**
 * BizUpKeep Payments Plugin Uninstall Script
 *
 * Runs only when the user deletes the plugin from wp-admin (not on
 * deactivation). Deliberately self-contained - does not rely on
 * Composer autoloading - so cleanup still works even if vendor/ is
 * missing or broken.
 *
 * Payment attempt data is only deleted if the user has explicitly
 * opted in via the "Delete all BizUpKeep Payments data on uninstall"
 * setting; otherwise this plugin's tables and options are left in
 * place so reinstalling it doesn't lose payment history.
 *
 * This script only ever removes rows/capabilities this plugin itself
 * created. It never touches BizHub's, Core's, Workflow's, or
 * Bookkeeping's own tables, options or roles - in particular, the
 * Invoices/ledger entries this plugin creates via BizUpKeep
 * Bookkeeping are that plugin's data, not this one's, and are
 * unaffected by this uninstall regardless of the option above.
 *
 * @package BizHub\Payments
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! get_option( 'bizupkeep_payments_delete_data_on_uninstall', false ) ) {
	return;
}

global $wpdb;

$tables = array(
	'bizhub_payments_webhook_log',
	'bizhub_payments_attempts',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is from a fixed internal list, not user input.
}

$options = array(
	'bizupkeep_payments_db_version',
	'bizupkeep_payments_dependency_notice',
	'bizupkeep_payments_delete_data_on_uninstall',
	'bizupkeep_payments_yoco_secret_key',
	'bizupkeep_payments_yoco_public_key',
	'bizupkeep_payments_snapscan_snap_code',
	'bizupkeep_payments_snapscan_api_key',
	'bizupkeep_payments_snapscan_webhook_key',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

foreach ( array( 'administrator', 'bizhub_administrator', 'bizhub_manager' ) as $role_name ) {
	$role = get_role( $role_name );

	if ( null === $role ) {
		continue;
	}

	foreach ( array( 'bizupkeep_payments.manage_payments' ) as $capability ) {
		$role->remove_cap( $capability );
	}
}
