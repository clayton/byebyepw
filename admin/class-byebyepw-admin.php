<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://claytonlz.com
 * @since      1.0.0
 *
 * @package    Byebyepw
 * @subpackage Byebyepw/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Byebyepw
 * @subpackage Byebyepw/admin
 * @author     Clayton <clayton@claytonlz.com>
 */
class Byebyepw_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * WebAuthn handler instance.
	 *
	 * @since    1.2.1
	 * @access   private
	 * @var      Byebyepw_WebAuthn    $webauthn    WebAuthn handler.
	 */
	private $webauthn;

	/**
	 * Recovery codes handler instance.
	 *
	 * @since    1.2.1
	 * @access   private
	 * @var      Byebyepw_Recovery_Codes    $recovery_codes    Recovery codes handler.
	 */
	private $recovery_codes;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param    string                      $plugin_name       The name of this plugin.
	 * @param    string                      $version           The version of this plugin.
	 * @param    Byebyepw_WebAuthn|null      $webauthn          Optional WebAuthn handler.
	 * @param    Byebyepw_Recovery_Codes|null $recovery_codes   Optional Recovery codes handler.
	 */
	public function __construct( $plugin_name, $version, $webauthn = null, $recovery_codes = null ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->webauthn = $webauthn;
		$this->recovery_codes = $recovery_codes;

	}

	/**
	 * Get the WebAuthn handler instance (lazy loading).
	 *
	 * @since    1.2.1
	 * @return   Byebyepw_WebAuthn    WebAuthn handler instance.
	 */
	private function get_webauthn() {
		if ( null === $this->webauthn ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-byebyepw-webauthn.php';
			$this->webauthn = new Byebyepw_WebAuthn();
		}
		return $this->webauthn;
	}

	/**
	 * Get the Recovery Codes handler instance (lazy loading).
	 *
	 * @since    1.2.1
	 * @return   Byebyepw_Recovery_Codes    Recovery codes handler instance.
	 */
	private function get_recovery_codes() {
		if ( null === $this->recovery_codes ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-byebyepw-recovery-codes.php';
			$this->recovery_codes = new Byebyepw_Recovery_Codes();
		}
		return $this->recovery_codes;
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles( $hook_suffix = '' ) {
		$is_plugin_page = isset( $_GET['page'] ) && in_array( sanitize_key( wp_unslash( $_GET['page'] ) ), array( 'byebyepw', 'byebyepw-settings' ), true ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
		$is_profile_page = in_array( $hook_suffix, array( 'profile.php', 'user-edit.php' ), true );

		if ( ! $is_plugin_page && ! $is_profile_page ) {
			return;
		}

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/byebyepw-admin.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		$is_passkeys_page = isset( $_GET['page'] ) && 'byebyepw' === sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.

		if ( ! $is_passkeys_page ) {
			return;
		}

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/byebyepw-admin.js', array( 'jquery' ), $this->version, false );
		
		// Localize script for AJAX
		wp_localize_script( $this->plugin_name, 'byebyepw_ajax', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'byebyepw_ajax' )
		));

		wp_localize_script( $this->plugin_name, 'byebyepw_i18n', array(
			'enter_passkey_name'       => __( 'Name this passkey so you can recognize it later.', 'bye-bye-passwords' ),
			'starting_registration'    => __( 'Starting registration...', 'bye-bye-passwords' ),
			'failed_get_challenge'     => __( 'Failed to get challenge', 'bye-bye-passwords' ),
			'invalid_challenge'        => __( 'Invalid challenge received from server', 'bye-bye-passwords' ),
			'follow_browser_prompts'   => __( 'Please follow your browser prompts to create a passkey...', 'bye-bye-passwords' ),
			'processing_passkey'       => __( 'Processing passkey...', 'bye-bye-passwords' ),
			'passkey_registered'       => __( 'Passkey registered. Reloading your setup…', 'bye-bye-passwords' ),
			'verification_failed'      => __( 'Verification failed', 'bye-bye-passwords' ),
			'registration_failed'      => __( 'Registration failed: ', 'bye-bye-passwords' ),
			'confirm_invalidate_codes' => __( 'Replace your recovery codes? Your existing codes will stop working.', 'bye-bye-passwords' ),
			'failed_generate_codes'    => __( 'Failed to generate recovery codes', 'bye-bye-passwords' ),
			'error_generating_codes'   => __( 'Error generating recovery codes', 'bye-bye-passwords' ),
			'codes_copied'             => __( 'Recovery codes copied.', 'bye-bye-passwords' ),
			'copy_codes'               => __( 'Copy codes', 'bye-bye-passwords' ),
			'copied_codes'             => __( 'Copied', 'bye-bye-passwords' ),
			'download_header'          => __( 'Bye Bye Passwords - Recovery Codes', 'bye-bye-passwords' ),
			'download_generated'       => __( 'Generated: ', 'bye-bye-passwords' ),
			'download_keep_safe'       => __( 'Keep these codes safe!', 'bye-bye-passwords' ),
			'confirm_delete_passkey'   => __( 'Are you sure you want to delete this passkey?', 'bye-bye-passwords' ),
			'failed_delete_passkey'    => __( 'Failed to delete passkey', 'bye-bye-passwords' ),
			'error_deleting_passkey'   => __( 'Error deleting passkey', 'bye-bye-passwords' ),
		));
	}

	/**
	 * Add admin menu items
	 *
	 * @since    1.0.0
	 */
	public function add_admin_menu() {
		// Add main menu item
		add_menu_page(
			__( 'Bye Bye Passwords', 'bye-bye-passwords' ),
			__( 'Passkeys', 'bye-bye-passwords' ),
			'manage_options',
			'byebyepw',
			array( $this, 'display_admin_page' ),
			'dashicons-shield-alt',
			100
		);

		add_submenu_page(
			'byebyepw',
			__( 'Passkeys', 'bye-bye-passwords' ),
			__( 'Passkeys', 'bye-bye-passwords' ),
			'manage_options',
			'byebyepw',
			array( $this, 'display_admin_page' )
		);

		// Add submenu for settings
		add_submenu_page(
			'byebyepw',
			__( 'Settings', 'bye-bye-passwords' ),
			__( 'Settings', 'bye-bye-passwords' ),
			'manage_options',
			'byebyepw-settings',
			array( $this, 'display_settings_page' )
		);
		
		// Debug tools removed for WordPress.org compliance
		// add_submenu_page(
		//     'byebyepw',
		//     __( 'Debug Tools', 'bye-bye-passwords' ),
		//     __( 'Debug Tools', 'bye-bye-passwords' ),
		//     'manage_options',
		//     'byebyepw-debug',
		//     array( $this, 'display_debug_page' )
		// );
	}

	/**
	 * Display main admin page
	 *
	 * @since    1.0.0
	 */
	public function display_admin_page() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/byebyepw-admin-display.php';
	}

	/**
	 * Display settings page
	 *
	 * @since    1.0.0
	 */
	public function display_settings_page() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/byebyepw-settings-display.php';
	}
	
	/**
	 * Display debug page (disabled for WordPress.org compliance)
	 *
	 * @since    1.0.0
	 */
	// public function display_debug_page() {
	//     require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/byebyepw-debug-display.php';
	// }

	/**
	 * Register plugin settings
	 *
	 * @since    1.0.0
	 */
	public function register_settings() {
		register_setting( 'byebyepw_settings', 'byebyepw_settings', array(
			'sanitize_callback' => array( $this, 'sanitize_settings' )
		) );

		add_settings_section(
			'byebyepw_general_settings',
			__( 'General Settings', 'bye-bye-passwords' ),
			null,
			'byebyepw-settings'
		);

		add_settings_field(
			'password_login_disabled',
			__( 'Disable Password Login', 'bye-bye-passwords' ),
			array( $this, 'render_password_login_field' ),
			'byebyepw-settings',
			'byebyepw_general_settings'
		);
	}

	/**
	 * Render password login field
	 *
	 * @since    1.0.0
	 */
	public function render_password_login_field() {
		$options = get_option( 'byebyepw_settings' );
		$value = isset( $options['password_login_disabled'] ) ? $options['password_login_disabled'] : false;
		$statuses = $this->get_admin_security_statuses();
		$can_disable = $this->are_all_admins_ready( $statuses );
		?>
		<fieldset class="byebyepw-setting-control">
			<label for="byebyepw-password-login-disabled">
				<input id="byebyepw-password-login-disabled" type="checkbox" name="byebyepw_settings[password_login_disabled]" value="1" <?php checked( 1, $value ); ?> <?php disabled( ! $can_disable ); ?> />
				<?php esc_html_e( 'Require a passkey or recovery code for WordPress sign-in', 'bye-bye-passwords' ); ?>
			</label>
		<?php if ( $can_disable ) : ?>
			<p class="description"><?php esc_html_e( 'All administrators have completed the required safety checks.', 'bye-bye-passwords' ); ?></p>
		<?php else : ?>
			<p class="description">
				<?php esc_html_e( 'Complete every administrator’s passkey, recovery-code, and test sign-in checks first.', 'bye-bye-passwords' ); ?>
			</p>
		<?php endif; ?>
		</fieldset>
		<?php
	}

	/**
	 * Sanitize plugin settings
	 *
	 * @since    1.1.2
	 * @param    array    $input    The input array from the settings form
	 * @return   array              The sanitized settings array
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();
		
		// Sanitize password_login_disabled checkbox
		$sanitized['password_login_disabled'] = isset( $input['password_login_disabled'] ) ? (bool) $input['password_login_disabled'] : false;

		// Only disable password sign-in after every administrator completes the safe setup.
		if ( ! empty( $sanitized['password_login_disabled'] ) ) {
			$statuses = $this->get_admin_security_statuses();
			if ( ! $this->are_all_admins_ready( $statuses ) ) {
				$names = array();
				foreach ( $statuses as $status ) {
					if ( ! $status['ready'] ) {
						$names[] = $status['user']->display_name;
					}
				}
				add_settings_error(
					'byebyepw_settings',
					'incomplete_passkey_setup',
					sprintf(
						/* translators: %s: comma-separated list of administrator display names */
						__( 'Password sign-in remains available. These administrators have not completed passkey setup: %s', 'bye-bye-passwords' ),
						implode( ', ', $names )
					),
					'error'
				);
				$sanitized['password_login_disabled'] = false;
			}
		}

		// Sanitize require_passkey_for_admins checkbox
		$sanitized['require_passkey_for_admins'] = isset( $input['require_passkey_for_admins'] ) ? (bool) $input['require_passkey_for_admins'] : false;

		return $sanitized;
	}

	/**
	 * Get passkey readiness for every administrator.
	 *
	 * @since    1.3.2
	 * @return   array[] Administrator readiness records.
	 */
	public function get_admin_security_statuses() {
		$admins = get_users( array( 'role' => 'administrator' ) );
		$statuses = array();

		foreach ( $admins as $admin ) {
			$credentials = $this->get_webauthn()->get_user_credentials( $admin->ID );
			$has_passkey = ! empty( $credentials );
			$has_tested_passkey = false;

			foreach ( $credentials as $credential ) {
				if ( ! empty( $credential->last_used ) ) {
					$has_tested_passkey = true;
					break;
				}
			}

			$has_recovery_codes = $this->get_recovery_codes()->has_recovery_codes( $admin->ID );
			$statuses[] = array(
				'user'               => $admin,
				'has_passkey'        => $has_passkey,
				'has_recovery_codes' => $has_recovery_codes,
				'has_tested_passkey' => $has_tested_passkey,
				'ready'              => $has_passkey && $has_recovery_codes && $has_tested_passkey,
			);
		}

		return $statuses;
	}

	/**
	 * Check whether every administrator has completed safe passkey setup.
	 *
	 * @since  1.3.2
	 * @param  array[] $statuses Administrator readiness records.
	 * @return bool
	 */
	public function are_all_admins_ready( $statuses ) {
		if ( empty( $statuses ) ) {
			return false;
		}

		foreach ( $statuses as $status ) {
			if ( empty( $status['ready'] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Add user profile fields
	 *
	 * @since    1.0.0
	 * @param    WP_User    $user    The user object being edited.
	 */
	public function add_user_profile_fields( $user ) {
		if ( ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}

		$credentials = $this->get_webauthn()->get_user_credentials( $user->ID );
		$has_recovery_codes = $this->get_recovery_codes()->has_recovery_codes( $user->ID );
		$remaining_codes = $this->get_recovery_codes()->get_remaining_codes_count( $user->ID );

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/byebyepw-user-profile.php';
	}

}
