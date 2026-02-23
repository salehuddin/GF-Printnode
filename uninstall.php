<?php
/**
 * Uninstall routine for the plugin.
 *
 * @package GravityFormsPrintNode
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete custom table.
$table_name = $wpdb->prefix . 'gf_printnode_logs';
$wpdb->query( "DROP TABLE IF EXISTS $table_name" );

// Delete plugin options.
delete_option( 'gravityformsaddon_gravity-forms-printnode_settings' );
delete_option( 'gravityformsaddon_gravity-forms-printnode_version' );

// Delete scheduled cron job.
$timestamp = wp_next_scheduled( 'gf_printnode_daily_cleanup' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'gf_printnode_daily_cleanup' );
}

// Optionally, we could delete the gf_printnode_previews folder here, 
// but it is safer to leave user-generated PDFs unless explicitly requested.
