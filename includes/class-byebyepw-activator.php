<?php

/**
 * Fired during plugin activation
 *
 * @link       https://claytonlz.com
 * @since      1.0.0
 *
 * @package    Byebyepw
 * @subpackage Byebyepw/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Byebyepw
 * @subpackage Byebyepw/includes
 * @author     Clayton <clayton@claytonlz.com>
 */
class Byebyepw_Activator {

	/**
	 * Creates database tables for WebAuthn passkeys and recovery codes.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		// Create passkeys table
		$passkeys_table = $wpdb->prefix . 'byebyepw_passkeys';
		$sql_passkeys = "CREATE TABLE IF NOT EXISTS $passkeys_table (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			name varchar(255) DEFAULT NULL,
			credential_id varchar(255) NOT NULL,
			public_key text NOT NULL,
			sign_count int(11) NOT NULL DEFAULT 0,
			attestation_format varchar(50) DEFAULT NULL,
			user_handle varchar(255) DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			last_used datetime DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY credential_id (credential_id),
			KEY user_id (user_id)
		) $charset_collate;";

		// Create recovery codes table
		$recovery_codes_table = $wpdb->prefix . 'byebyepw_recovery_codes';
		$sql_recovery = "CREATE TABLE IF NOT EXISTS $recovery_codes_table (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			code_hash varchar(255) NOT NULL,
			used tinyint(1) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			used_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id)
		) $charset_collate;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql_passkeys);
		dbDelta($sql_recovery);

		// Store database version for future upgrades
		add_option('byebyepw_db_version', '1.1.0');
		
		// Add default plugin settings
		add_option('byebyepw_settings', array(
			'password_login_disabled' => false,
			'require_passkey_for_admins' => false,
		));
	}

}