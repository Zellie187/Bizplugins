<?php
/**
 * BizUpKeep Workflow Plugin Uninstall Script
 *
 * Runs only when the user deletes the plugin from wp-admin (not on
 * deactivation). Deliberately self-contained - does not rely on
 * Composer autoloading - so cleanup still works even if vendor/ is
 * missing or broken.
 *
 * Workflow instance/transition data is only deleted if the user has
 * explicitly opted in via the "Delete all BizUpKeep Workflow data on
 * uninstall" setting; otherwise this plugin's tables and options are
 * left in place so reinstalling it doesn't lose workflow history.
 *
 * This script only ever removes rows/capabilities this plugin itself
 * created. It never touches BizHub's own tables, options or roles.
 *
 * @package BizHub\Workflow
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! get_option( 'bizupkeep_workflow_delete_data_on_uninstall', false ) ) {
	return;
}

global $wpdb;

$tables = array(
	'bizhub_workflow_transitions',
	'bizhub_workflow_instances',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is from a fixed internal list, not user input.
}

$options = array(
	'bizupkeep_workflow_db_version',
	'bizupkeep_workflow_dependency_notice',
	'bizupkeep_workflow_delete_data_on_uninstall',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

foreach ( array( 'administrator', 'bizhub_administrator', 'bizhub_manager', 'bizhub_staff' ) as $role_name ) {
	$role = get_role( $role_name );

	if ( null === $role ) {
		continue;
	}

	foreach ( array( 'workflow.view', 'workflow.manage', 'workflow.transition' ) as $capability ) {
		$role->remove_cap( $capability );
	}
}
