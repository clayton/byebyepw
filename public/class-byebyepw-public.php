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
		$password_disabled = $this->is_password_login_disabled();
		?>
		<div id="byebyepw-login-message" class="byebyepw-message" role="alert" aria-live="assertive" hidden></div>

		<div id="byebyepw-login-section">
			<?php if ( ! $password_disabled ) : ?>
			<p class="byebyepw-divider">
				<span><?php esc_html_e( 'or use a passkey', 'bye-bye-passwords' ); ?></span>
			</p>
			<?php endif; ?>
			<button type="button" id="byebyepw-authenticate-passkey" class="button button-large">
				<span class="byebyepw-passkey-mark" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" focusable="false">
						<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
					</svg>
				</span>
				<span><?php esc_html_e( 'Sign in with a passkey', 'bye-bye-passwords' ); ?></span>
			</button>
			<p class="byebyepw-login-hint"><?php esc_html_e( 'Use your fingerprint, face, device PIN, or security key.', 'bye-bye-passwords' ); ?></p>
			<p class="byebyepw-option-text">
				<a href="#" id="byebyepw-use-recovery-code"><?php esc_html_e( 'Sign in with a recovery code', 'bye-bye-passwords' ); ?></a>
			</p>
		</div>

		<div id="byebyepw-recovery-form" aria-labelledby="byebyepw-recovery-heading">
			<h2 id="byebyepw-recovery-heading"><?php esc_html_e( 'Use a recovery code', 'bye-bye-passwords' ); ?></h2>
			<p class="byebyepw-recovery-intro"><?php esc_html_e( 'Enter one unused code from the set you saved during setup.', 'bye-bye-passwords' ); ?></p>
			<?php if ( $password_disabled ) : ?>
			<p class="byebyepw-recovery-username-wrap">
				<label for="byebyepw-recovery-username"><?php esc_html_e( 'Username or Email Address', 'bye-bye-passwords' ); ?></label>
				<input type="text" name="username" id="byebyepw-recovery-username" class="input" size="20" autocomplete="username" />
			</p>
			<?php endif; ?>
			<p>
				<label for="byebyepw-recovery-code"><?php esc_html_e( 'Recovery Code', 'bye-bye-passwords' ); ?></label>
				<input type="text" name="recovery_code" id="byebyepw-recovery-code" class="input" size="20" autocomplete="one-time-code" autocapitalize="characters" spellcheck="false" />
			</p>
			<button type="button" id="byebyepw-submit-recovery" class="button button-primary button-large">
				<?php esc_html_e( 'Sign in with recovery code', 'bye-bye-passwords' ); ?>
			</button>
			<p class="byebyepw-option-text">
				<a href="#" id="byebyepw-back-to-passkey"><?php esc_html_e( 'Back to sign-in options', 'bye-bye-passwords' ); ?></a>
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

		wp_localize_script( $this->plugin_name . '-login', 'byebyepw_i18n', array(
			'browser_not_supported'     => __( 'Passkeys are not available in this browser.', 'bye-bye-passwords' ),
			'browser_not_supported_msg' => __( 'Your browser does not support passkey authentication. Please use a modern browser (Chrome, Firefox, Safari, Edge) or use a recovery code to sign in.', 'bye-bye-passwords' ),
			'initializing'              => __( 'Preparing passkey…', 'bye-bye-passwords' ),
			'failed_get_auth_challenge' => __( 'We could not start passkey sign-in. Check the username, or try another sign-in method.', 'bye-bye-passwords' ),
			'follow_browser_prompts'    => __( 'Check your device…', 'bye-bye-passwords' ),
			'authenticating'            => __( 'Verifying passkey…', 'bye-bye-passwords' ),
			'auth_failed'               => __( 'That passkey could not be verified. Try again or use another sign-in method.', 'bye-bye-passwords' ),
			'auth_failed_detail'        => __( 'Passkey sign-in was canceled or timed out. Try again when you are ready.', 'bye-bye-passwords' ),
			'enter_username'            => __( 'Enter your username or email address.', 'bye-bye-passwords' ),
			'enter_recovery_code'       => __( 'Enter one of your unused recovery codes.', 'bye-bye-passwords' ),
			'recovery_authenticating'   => __( 'Checking recovery code…', 'bye-bye-passwords' ),
			'recovery_failed'           => __( 'That username or recovery code could not be verified. Check both and try again.', 'bye-bye-passwords' ),
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
		if ( $this->is_password_login_disabled() ) {
			$classes[] = 'byebyepw-password-disabled';
		}
		return $classes;
	}

	/**
	 * Reject standard password submissions when password sign-in is disabled.
	 *
	 * Passkey and recovery-code authentication use separate AJAX endpoints and do
	 * not pass through this WordPress login-form filter.
	 *
	 * @since  1.4.0
	 * @param  null|WP_User|WP_Error $user     Authentication result.
	 * @param  string                $username Submitted username.
	 * @param  string                $password Submitted password.
	 * @return null|WP_User|WP_Error Authentication result.
	 */
	public function reject_password_login( $user, $username, $password ) {
		global $pagenow;
		$request_method = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) );

		if ( 'wp-login.php' !== $pagenow || 'POST' !== strtoupper( $request_method ) ) {
			return $user;
		}

		if ( empty( $password ) || ! $this->is_password_login_disabled() ) {
			return $user;
		}

		return new WP_Error(
			'byebyepw_password_login_disabled',
			__( 'Password sign-in is disabled. Use a passkey or recovery code.', 'bye-bye-passwords' )
		);
	}

	/**
	 * Check whether password sign-in is both configured and currently safe to disable.
	 *
	 * Passwords automatically remain available if a new or unprepared
	 * administrator is present, preventing an accidental lockout.
	 *
	 * @since  1.4.0
	 * @return bool Whether password sign-in is effectively disabled.
	 */
	private function is_password_login_disabled() {
		$options = get_option( 'byebyepw_settings', array() );
		if ( empty( $options['password_login_disabled'] ) ) {
			return false;
		}

		$webauthn = new Byebyepw_WebAuthn();
		$recovery_codes = new Byebyepw_Recovery_Codes();
		$administrator_ids = get_users( array(
			'role'   => 'administrator',
			'fields' => 'ID',
		) );

		if ( empty( $administrator_ids ) ) {
			return false;
		}

		foreach ( $administrator_ids as $administrator_id ) {
			$credentials = $webauthn->get_user_credentials( $administrator_id );
			$has_tested_passkey = false;

			foreach ( $credentials as $credential ) {
				if ( ! empty( $credential->last_used ) ) {
					$has_tested_passkey = true;
					break;
				}
			}

			if ( empty( $credentials ) || ! $has_tested_passkey || ! $recovery_codes->has_recovery_codes( $administrator_id ) ) {
				return false;
			}
		}

		return true;
	}

}
