<?php

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fired during plugin deactivation
 *
 * @link       https://claytonlz.com
 * @since      1.0.0
 *
 * @package    Byebyepw
 * @subpackage Byebyepw/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Byebyepw
 * @subpackage Byebyepw/includes
 * @author     Clayton <clayton@claytonlz.com>
 */
class Byebyepw_Deactivator {

	/**
	 * Clean up temporary data on plugin deactivation.
	 *
	 * Removes transients and clears caches but preserves user data
	 * (passkeys, recovery codes, settings) for when the plugin is reactivated.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
		global $wpdb;

		// Delete all plugin transients (rate limiting, challenge data, etc.)
		$transient_like         = $wpdb->esc_like( '_transient_byebyepw_' ) . '%';
		$transient_timeout_like = $wpdb->esc_like( '_transient_timeout_byebyepw_' ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk transient cleanup on deactivation
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$transient_like,
				$transient_timeout_like
			)
		);

		// Clear object cache for plugin data
		wp_cache_flush();
	}

}
