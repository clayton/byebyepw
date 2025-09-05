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
		<div id="byebyepw-login-section" style="margin: 20px 0;">
			<?php if ( ! $password_disabled ) : ?>
			<p class="byebyepw-divider" style="text-align: center; margin: 20px 0;">
				<span style="background: #fff; padding: 0 10px;"><?php _e( 'Or', 'byebyepw' ); ?></span>
			</p>
			<?php endif; ?>
			<button type="button" id="byebyepw-authenticate-passkey" class="button button-large" style="width: 100%;">
				<?php _e( 'Sign in with Passkey', 'byebyepw' ); ?>
			</button>
			<p style="margin-top: 10px; text-align: center;">
				<a href="#" id="byebyepw-use-recovery-code"><?php _e( 'Use recovery code', 'byebyepw' ); ?></a>
			</p>
		</div>
		
		<div id="byebyepw-recovery-form" style="display: none;">
			<?php if ( $password_disabled ) : ?>
			<p>
				<label for="byebyepw-recovery-username"><?php _e( 'Username or Email Address', 'byebyepw' ); ?></label>
				<input type="text" name="username" id="byebyepw-recovery-username" class="input" size="20" />
			</p>
			<?php endif; ?>
			<p>
				<label for="byebyepw-recovery-code"><?php _e( 'Recovery Code', 'byebyepw' ); ?></label>
				<input type="text" name="recovery_code" id="byebyepw-recovery-code" class="input" size="20" />
			</p>
			<button type="button" id="byebyepw-submit-recovery" class="button button-primary button-large" style="width: 100%;">
				<?php _e( 'Sign in with Recovery Code', 'byebyepw' ); ?>
			</button>
			<p style="margin-top: 10px; text-align: center;">
				<a href="#" id="byebyepw-back-to-passkey"><?php _e( 'Back to Passkey', 'byebyepw' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Enqueue login scripts
	 *
	 * @since    1.0.0
	 */
	public function enqueue_login_scripts() {
		wp_enqueue_script( 
			$this->plugin_name . '-login', 
			plugin_dir_url( __FILE__ ) . 'js/byebyepw-login.js', 
			array( 'jquery' ), 
			$this->version, 
			false 
		);
		
		wp_localize_script( $this->plugin_name . '-login', 'byebyepw_ajax', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'redirect_to' => isset( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : admin_url()
		));
	}

	/**
	 * Hide password field if disabled
	 *
	 * @since    1.0.0
	 */
	public function maybe_hide_password_field() {
		$options = get_option( 'byebyepw_settings' );
		if ( isset( $options['password_login_disabled'] ) && $options['password_login_disabled'] ) {
			?>
			<style>
				/* Hide username/email and password fields */
				#loginform #user_login,
				#loginform label[for="user_login"],
				#loginform .user-login-wrap,
				#loginform #user_pass, 
				#loginform label[for="user_pass"],
				#loginform .user-pass-wrap,
				#loginform .forgetmenot,
				#loginform #wp-submit,
				#nav {
					display: none !important;
				}
				
				/* Hide the "Or" divider when passwords disabled */
				.byebyepw-divider {
					display: none !important;
				}
			</style>
			<?php
		}
	}

}