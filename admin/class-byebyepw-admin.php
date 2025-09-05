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
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/byebyepw-admin.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/byebyepw-admin.js', array( 'jquery' ), $this->version, false );
		
		// Localize script for AJAX
		wp_localize_script( $this->plugin_name, 'byebyepw_ajax', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'byebyepw_ajax' )
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
			__( 'Bye Bye Passwords', 'byebyepw' ),
			__( 'Bye Bye PW', 'byebyepw' ),
			'manage_options',
			'byebyepw',
			array( $this, 'display_admin_page' ),
			'dashicons-shield-alt',
			100
		);

		// Add submenu for settings
		add_submenu_page(
			'byebyepw',
			__( 'Settings', 'byebyepw' ),
			__( 'Settings', 'byebyepw' ),
			'manage_options',
			'byebyepw-settings',
			array( $this, 'display_settings_page' )
		);
		
		// Debug tools removed for WordPress.org compliance
		// add_submenu_page(
		//     'byebyepw',
		//     __( 'Debug Tools', 'byebyepw' ),
		//     __( 'Debug Tools', 'byebyepw' ),
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
			__( 'General Settings', 'byebyepw' ),
			null,
			'byebyepw-settings'
		);

		add_settings_field(
			'password_login_disabled',
			__( 'Disable Password Login', 'byebyepw' ),
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
		?>
		<input type="checkbox" name="byebyepw_settings[password_login_disabled]" value="1" <?php checked( 1, $value ); ?> />
		<p class="description"><?php esc_html_e( 'Warning: Only enable this if you have passkeys configured and recovery codes saved!', 'byebyepw' ); ?></p>
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
		
		// Sanitize require_passkey_for_admins checkbox
		$sanitized['require_passkey_for_admins'] = isset( $input['require_passkey_for_admins'] ) ? (bool) $input['require_passkey_for_admins'] : false;
		
		return $sanitized;
	}

	/**
	 * Add user profile fields
	 *
	 * @since    1.0.0
	 */
	public function add_user_profile_fields( $user ) {
		if ( ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-byebyepw-webauthn.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-byebyepw-recovery-codes.php';
		
		$webauthn = new Byebyepw_WebAuthn();
		$recovery_codes = new Byebyepw_Recovery_Codes();
		
		$credentials = $webauthn->get_user_credentials( $user->ID );
		$has_recovery_codes = $recovery_codes->has_recovery_codes( $user->ID );
		$remaining_codes = $recovery_codes->get_remaining_codes_count( $user->ID );
		
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/byebyepw-user-profile.php';
	}

}