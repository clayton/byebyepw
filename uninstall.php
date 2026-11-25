<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following flow
 * of control:
 *
 * - This method should be static
 * - Check if the $_REQUEST content actually is the plugin name
 * - Run an admin referrer check to make sure it goes through authentication
 * - Verify the output of $_GET makes sense
 * - Repeat with other user roles. Best directly by using the links/query string parameters.
 * - Repeat things for multisite. Once for a single site in the network, once sitewide.
 *
 * This file may be updated more in future version of the Boilerplate; however, this is the
 * general skeleton and outline for how the file should work.
 *
 * For more information, see the following discussion:
 * https://github.com/tommcfarlin/WordPress-Plugin-Boilerplate/pull/123#issuecomment-28541913
 *
 * @link       https://claytonlz.com
 * @since      1.0.0
 *
 * @package    Byebyepw
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete plugin database tables
if ( version_compare( get_bloginfo( 'version' ), '6.2', '>=' ) ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder -- Required for uninstall cleanup; guarded for WordPress >= 6.2
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'byebyepw_passkeys' ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder -- Required for uninstall cleanup; guarded for WordPress >= 6.2
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'byebyepw_recovery_codes' ) );
} else {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Required for uninstall cleanup on WordPress < 6.2 (before %i placeholder)
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}byebyepw_passkeys" );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Required for uninstall cleanup on WordPress < 6.2 (before %i placeholder)
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}byebyepw_recovery_codes" );
}

// Delete plugin options
delete_option( 'byebyepw_db_version' );
delete_option( 'byebyepw_settings' );

// Delete all plugin transients
$byebyepw_transient_like         = $wpdb->esc_like( '_transient_byebyepw_' ) . '%';
$byebyepw_transient_timeout_like = $wpdb->esc_like( '_transient_timeout_byebyepw_' ) . '%';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk transient cleanup on uninstall
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$byebyepw_transient_like,
		$byebyepw_transient_timeout_like
	)
);

// Clean up user meta if any exists
$byebyepw_usermeta_like = $wpdb->esc_like( 'byebyepw_' ) . '%';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk user meta cleanup on uninstall
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
		$byebyepw_usermeta_like
	)
);

// Clear any cached data
wp_cache_flush();
