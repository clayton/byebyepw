<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://claytonlz.com
 * @since      1.0.0
 *
 * @package    Byebyepw
 * @subpackage Byebyepw/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Byebyepw
 * @subpackage Byebyepw/includes
 * @author     Clayton <clayton@claytonlz.com>
 */
class Byebyepw {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Byebyepw_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'BYEBYEPW_VERSION' ) ) {
			$this->version = BYEBYEPW_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'byebyepw';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_ajax_hooks();
		
		// Initialize PHP sessions if not already started
		add_action( 'init', array( $this, 'start_session' ), 1 );

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Byebyepw_Loader. Orchestrates the hooks of the plugin.
	 * - Byebyepw_i18n. Defines internationalization functionality.
	 * - Byebyepw_Admin. Defines all hooks for the admin area.
	 * - Byebyepw_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-byebyepw-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-byebyepw-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-byebyepw-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-byebyepw-public.php';
		
		/**
		 * The class responsible for WebAuthn functionality
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-byebyepw-webauthn.php';
		
		/**
		 * The class responsible for recovery codes
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-byebyepw-recovery-codes.php';
		
		/**
		 * The class responsible for AJAX handlers
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-byebyepw-ajax.php';

		$this->loader = new Byebyepw_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Byebyepw_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Byebyepw_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Byebyepw_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_admin_menu' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'register_settings' );
		$this->loader->add_action( 'show_user_profile', $plugin_admin, 'add_user_profile_fields' );
		$this->loader->add_action( 'edit_user_profile', $plugin_admin, 'add_user_profile_fields' );

		// Add HTTPS check admin notice
		$this->loader->add_action( 'admin_notices', $this, 'display_https_notice' );

	}

	/**
	 * Display admin notice if site is not using HTTPS.
	 *
	 * WebAuthn requires a secure context (HTTPS) to function properly.
	 *
	 * @since    1.2.1
	 */
	public function display_https_notice() {
		// Only show on plugin pages and only if not using HTTPS
		if ( is_ssl() ) {
			return;
		}

		// Only show to administrators
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check if we're on a plugin page or the login page
		$screen = get_current_screen();
		$is_plugin_page = $screen && ( strpos( $screen->id, 'bye-bye-passwords' ) !== false || strpos( $screen->id, 'bye-bye-passwords' ) !== false );

		if ( ! $is_plugin_page ) {
			return;
		}

		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'Bye Bye Passwords Security Warning:', 'bye-bye-passwords' ); ?></strong>
				<?php esc_html_e( 'Your site is not using HTTPS. WebAuthn/Passkeys require a secure connection (HTTPS) to work properly. Please enable HTTPS on your site before using passkey authentication.', 'bye-bye-passwords' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Byebyepw_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
		$this->loader->add_action( 'login_form', $plugin_public, 'add_passkey_login_button' );
		$this->loader->add_action( 'login_enqueue_scripts', $plugin_public, 'enqueue_login_scripts' );
		$this->loader->add_action( 'login_head', $plugin_public, 'maybe_hide_password_field' );

	}
	
	/**
	 * Register all of the hooks related to AJAX functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_ajax_hooks() {
		$ajax_handler = new Byebyepw_Ajax();
		$ajax_handler->register_ajax_handlers();
	}
	
	/**
	 * Start PHP session for WebAuthn challenges with security settings
	 *
	 * @since    1.0.0
	 */
	public function start_session() {
		if ( ! session_id() && ! headers_sent() ) {
			// Set secure session parameters
			session_set_cookie_params([
				'lifetime' => 900, // 15 minutes
				'path' => '/',
				'domain' => '',
				'secure' => is_ssl(),
				'httponly' => true,
				'samesite' => 'Strict'
			]);
			
			session_start();
			
			// Regenerate session ID for security
			if ( ! isset( $_SESSION['byebyepw_initiated'] ) ) {
				session_regenerate_id( true );
				$_SESSION['byebyepw_initiated'] = true;
				$_SESSION['byebyepw_created'] = time();
			}
			
			// Check session timeout
			if ( isset( $_SESSION['byebyepw_created'] ) && 
				 ( time() - $_SESSION['byebyepw_created'] > 900 ) ) {
				session_destroy();
				session_start();
				$_SESSION['byebyepw_initiated'] = true;
				$_SESSION['byebyepw_created'] = time();
			}
		}
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Byebyepw_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}