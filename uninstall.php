<?php
/**
 * BizHub Core Plugin Uninstall Script
 *
 * Runs only when the user deletes the plugin from wp-admin (not on
 * deactivation). Deliberately self-contained - does not rely on
 * Composer autoloading - so cleanup still works even if vendor/ is
 * missing or broken.
 *
 * Table data is only deleted if the user has explicitly opted in via
 * the "Delete all BizHub data on uninstall" setting; otherwise BizHub's
 * tables and options are left in place so reinstalling the plugin
 * doesn't lose business data.
 *
 * @package BizHub
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! get_option( 'bizhub_delete_data_on_uninstall', false ) ) {
	return;
}

global $wpdb;

$tables = array(
	'bizhub_companies',
	'bizhub_directors',
	'bizhub_clients',
	'bizhub_client_notifications',
	'bizhub_applications',
	'bizhub_application_steps',
	'bizhub_application_comments',
	'bizhub_documents',
	'bizhub_document_versions',
	'bizhub_audit_log',
	'bizhub_queue_jobs',
	'bizhub_notification_queue',
	'bizhub_logs',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is from a fixed internal list, not user input.
}

$options = array(
	'bizhub_db_version',
	'bizhub_settings',
	'bizhub_forminator_form_map',
	'bizhub_delete_data_on_uninstall',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

foreach ( array( 'bizhub_administrator', 'bizhub_manager', 'bizhub_staff', 'bizhub_client' ) as $role ) {
	remove_role( $role );
}
