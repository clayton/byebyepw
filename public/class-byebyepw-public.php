<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://claytonlz.com
 * @since      1.0.0
 *
 * @package    Byebyepw
 * @subpackage Byebyepw/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Byebyepw
 * @subpackage Byebyepw/public
 * @author     Clayton <clayton@claytonlz.com>
 */
class Byebyepw_Public {

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
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/byebyepw-public.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/byebyepw-public.js', array( 'jquery' ), $this->version, false );
	}

	/**
	 * Add passkey login button to login form
	 *
	 * @since    1.0.0
	 */
	public function add_passkey_login_button() {
		$options = get_option( 'byebyepw_settings' );
		$password_disabled = isset( $options['password_login_disabled'] ) ? $options['password_login_disabled'] : false;
		?>
		<div id="byebyepw-login-section">
			<?php if ( ! $password_disabled ) : ?>
			<p class="byebyepw-divider">
				<span><?php esc_html_e( 'Or', 'bye-bye-passwords' ); ?></span>
			</p>
			<?php endif; ?>
			<button type="button" id="byebyepw-authenticate-passkey" class="button button-large">
				<?php esc_html_e( 'Sign in with Passkey', 'bye-bye-passwords' ); ?>
			</button>
			<p class="byebyepw-option-text">
				<a href="#" id="byebyepw-use-recovery-code"><?php esc_html_e( 'Use recovery code', 'bye-bye-passwords' ); ?></a>
			</p>
		</div>

		<div id="byebyepw-recovery-form">
			<?php if ( $password_disabled ) : ?>
			<p>
				<label for="byebyepw-recovery-username"><?php esc_html_e( 'Username or Email Address', 'bye-bye-passwords' ); ?></label>
				<input type="text" name="username" id="byebyepw-recovery-username" class="input" size="20" />
			</p>
			<?php endif; ?>
			<p>
				<label for="byebyepw-recovery-code"><?php esc_html_e( 'Recovery Code', 'bye-bye-passwords' ); ?></label>
				<input type="text" name="recovery_code" id="byebyepw-recovery-code" class="input" size="20" />
			</p>
			<button type="button" id="byebyepw-submit-recovery" class="button button-primary button-large">
				<?php esc_html_e( 'Sign in with Recovery Code', 'bye-bye-passwords' ); ?>
			</button>
			<p class="byebyepw-option-text">
				<a href="#" id="byebyepw-back-to-passkey"><?php esc_html_e( 'Back to Passkey', 'bye-bye-passwords' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Enqueue login scripts and styles
	 *
	 * @since    1.0.0
	 */
	public function enqueue_login_scripts() {
		// Enqueue login page styles
		wp_enqueue_style(
			$this->plugin_name . '-login',
			plugin_dir_url( __FILE__ ) . 'css/byebyepw-public.css',
			array(),
			$this->version,
			'all'
		);

		wp_enqueue_script(
			$this->plugin_name . '-login',
			plugin_dir_url( __FILE__ ) . 'js/byebyepw-login.js',
			array( 'jquery' ),
			$this->version,
			false
		);
		
		// Prepare redirect URL - this is safe to access without nonce as it's just for determining redirect destination
		$redirect_to = admin_url(); // Default
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Not processing form data, just determining redirect URL
		if ( isset( $_REQUEST['redirect_to'] ) ) {
			// Safe URL parameter access for redirect destination (not processing form data)
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Not processing form data, just determining redirect URL
			$redirect_to = esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) );
		}
		
		wp_localize_script( $this->plugin_name . '-login', 'byebyepw_ajax', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'redirect_to' => $redirect_to,
			'nonce' => wp_create_nonce( 'byebyepw_security' ) // Provide nonce for AJAX requests
		));
	}

	/**
	 * Add body class when password login is disabled
	 *
	 * @since    1.0.0
	 * @param    array $classes Body classes.
	 * @return   array Modified body classes.
	 */
	public function maybe_hide_password_field( $classes ) {
		$options = get_option( 'byebyepw_settings' );
		if ( isset( $options['password_login_disabled'] ) && $options['password_login_disabled'] ) {
			$classes[] = 'byebyepw-password-disabled';
		}
		return $classes;
	}

}