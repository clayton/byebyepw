<?php

/**
 * AJAX handler for WebAuthn operations
 *
 * @link       https://claytonlz.com
 * @since      1.0.0
 *
 * @package    Byebyepw
 * @subpackage Byebyepw/includes
 */

/**
 * Handles AJAX requests for WebAuthn operations.
 *
 * @since      1.0.0
 * @package    Byebyepw
 * @subpackage Byebyepw/includes
 * @author     Clayton <clayton@claytonlz.com>
 */
class Byebyepw_Ajax {

	/**
	 * WebAuthn handler instance
	 *
	 * @var Byebyepw_WebAuthn
	 */
	private $webauthn;

	/**
	 * Recovery codes handler instance
	 *
	 * @var Byebyepw_Recovery_Codes
	 */
	private $recovery_codes;

	/**
	 * Log debug messages when WP_DEBUG is enabled
	 *
	 * @param string $message The message to log
	 */
	private function debug_log( $message ) {
		// Debug logging disabled for WordPress.org compliance
		// if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
		//     error_log( 'ByeByePW: ' . $message );
		// }
	}
	
	/**
	 * Generate CSRF token for public endpoints
	 *
	 * @return string CSRF token
	 */
	private function generate_csrf_token() {
		if ( ! session_id() ) {
			session_start();
		}
		
		// Generate token if not exists or expired
		if ( ! isset( $_SESSION['byebyepw_csrf_token'] ) || 
			 ! isset( $_SESSION['byebyepw_csrf_expires'] ) ||
			 $_SESSION['byebyepw_csrf_expires'] < time() ) {
			
			$_SESSION['byebyepw_csrf_token'] = bin2hex( random_bytes( 16 ) );
			$_SESSION['byebyepw_csrf_expires'] = time() + 1800; // 30 minutes
		}
		
		return $_SESSION['byebyepw_csrf_token'];
	}
	
	/**
	 * Validate CSRF token for public endpoints
	 *
	 * @param string $token Token to validate
	 * @return bool True if valid
	 */
	private function validate_csrf_token( $token ) {
		if ( ! session_id() ) {
			session_start();
		}
		
		if ( ! isset( $_SESSION['byebyepw_csrf_token'] ) || 
			 ! isset( $_SESSION['byebyepw_csrf_expires'] ) ) {
			return false;
		}
		
		// Check expiration
		if ( $_SESSION['byebyepw_csrf_expires'] < time() ) {
			unset( $_SESSION['byebyepw_csrf_token'], $_SESSION['byebyepw_csrf_expires'] );
			return false;
		}
		
		// Use hash_equals for constant-time comparison
		return hash_equals( $_SESSION['byebyepw_csrf_token'], $token );
	}

	/**
	 * Check rate limiting for authentication endpoints
	 *
	 * @param string $action The action being rate limited
	 * @param int $max_attempts Maximum attempts allowed
	 * @param int $time_window Time window in seconds
	 * @return bool True if rate limit exceeded
	 */
	private function is_rate_limited( $action, $max_attempts = 5, $time_window = 300 ) {
		$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
		$transient_key = 'byebyepw_rate_limit_' . $action . '_' . md5( $ip_address );
		
		$attempts = get_transient( $transient_key );
		if ( ! $attempts ) {
			$attempts = [];
		}
		
		// Clean old attempts outside the time window
		$current_time = time();
		$attempts = array_filter( $attempts, function( $timestamp ) use ( $current_time, $time_window ) {
			return ( $current_time - $timestamp ) <= $time_window;
		});
		
		// Check if rate limit exceeded
		if ( count( $attempts ) >= $max_attempts ) {
			$this->debug_log( 'Rate limit exceeded for action ' . $action . ' from IP: ' . $ip_address );
			return true;
		}
		
		// Add current attempt
		$attempts[] = $current_time;
		set_transient( $transient_key, $attempts, $time_window );
		
		return false;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-byebyepw-webauthn.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-byebyepw-recovery-codes.php';
		
		$this->webauthn = new Byebyepw_WebAuthn();
		$this->recovery_codes = new Byebyepw_Recovery_Codes();
	}

	/**
	 * Register AJAX handlers
	 */
	public function register_ajax_handlers() {
		// Admin AJAX handlers (logged in users)
		add_action( 'wp_ajax_byebyepw_get_registration_challenge', array( $this, 'handle_get_registration_challenge' ) );
		add_action( 'wp_ajax_byebyepw_register_passkey', array( $this, 'handle_register_passkey' ) );
		add_action( 'wp_ajax_byebyepw_delete_passkey', array( $this, 'handle_delete_passkey' ) );
		add_action( 'wp_ajax_byebyepw_generate_recovery_codes', array( $this, 'handle_generate_recovery_codes' ) );
		
		// Public AJAX handlers (for login page)
		add_action( 'wp_ajax_nopriv_byebyepw_get_authentication_challenge', array( $this, 'handle_get_authentication_challenge' ) );
		add_action( 'wp_ajax_nopriv_byebyepw_get_authentication_options', array( $this, 'handle_get_authentication_options' ) );
		add_action( 'wp_ajax_nopriv_byebyepw_authenticate_passkey', array( $this, 'handle_authenticate_passkey' ) );
		add_action( 'wp_ajax_nopriv_byebyepw_authenticate', array( $this, 'handle_authenticate' ) );
		add_action( 'wp_ajax_nopriv_byebyepw_authenticate_recovery_code', array( $this, 'handle_authenticate_recovery_code' ) );
		add_action( 'wp_ajax_nopriv_byebyepw_verify_recovery_code', array( $this, 'handle_authenticate_recovery_code' ) );
		// Also allow logged in users to use these
		add_action( 'wp_ajax_byebyepw_get_authentication_options', array( $this, 'handle_get_authentication_options' ) );
		add_action( 'wp_ajax_byebyepw_authenticate', array( $this, 'handle_authenticate' ) );
	}

	/**
	 * Handle get registration challenge request
	 */
	public function handle_get_registration_challenge() {
		if ( ! check_ajax_referer( 'byebyepw_ajax', 'nonce', false ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Not logged in' );
		}

		$user_id = get_current_user_id();
		$result = $this->webauthn->get_registration_challenge( $user_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Handle register passkey request
	 */
	public function handle_register_passkey() {
		if ( ! check_ajax_referer( 'byebyepw_ajax', 'nonce', false ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Not logged in' );
		}

		$user_id = get_current_user_id();
		$client_data_json = sanitize_text_field( $_POST['client_data_json'] ?? '' );
		$attestation_object = sanitize_text_field( $_POST['attestation_object'] ?? '' );
		$name = sanitize_text_field( $_POST['name'] ?? 'Unnamed Passkey' );

		if ( empty( $client_data_json ) || empty( $attestation_object ) ) {
			wp_send_json_error( 'Missing required data' );
		}

		$this->debug_log( 'AJAX: Processing registration for user ' . $user_id );
		$this->debug_log( 'AJAX: Passkey name: ' . $name );
		
		$result = $this->webauthn->process_registration( $user_id, $client_data_json, $attestation_object, $name );

		if ( is_wp_error( $result ) ) {
			$error_msg = $result->get_error_message();
			$this->debug_log( 'AJAX: Registration failed with error: ' . $error_msg );
			wp_send_json_error( $error_msg );
		}

		$this->debug_log( 'AJAX: Registration successful!' );
		wp_send_json_success( 'Passkey registered successfully' );
	}

	/**
	 * Handle get authentication challenge request
	 */
	public function handle_get_authentication_challenge() {
		// Rate limiting - 10 challenge requests per 5 minutes
		if ( $this->is_rate_limited( 'auth_challenge', 10, 300 ) ) {
			wp_send_json_error( 'Too many requests. Please try again later.' );
		}
		
		// Start session if not already started
		if ( ! session_id() ) {
			session_start();
		}

		// Check if specific user is requested (for username+passkey flow)
		$username = sanitize_text_field( $_POST['username'] ?? '' );
		$user_id = null;

		if ( ! empty( $username ) ) {
			$user = get_user_by( 'login', $username );
			if ( $user ) {
				$user_id = $user->ID;
			}
		}

		$result = $this->webauthn->get_authentication_challenge( $user_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		// Add CSRF token to challenge response for subsequent authentication
		$result->csrf_token = $this->generate_csrf_token();

		wp_send_json_success( $result );
	}

	/**
	 * Handle get authentication options request (alias for get_authentication_challenge)
	 */
	public function handle_get_authentication_options() {
		$this->handle_get_authentication_challenge();
	}

	/**
	 * Handle authenticate passkey request
	 */
	public function handle_authenticate_passkey() {
		// Rate limiting - 5 authentication attempts per 5 minutes
		if ( $this->is_rate_limited( 'auth_attempt', 5, 300 ) ) {
			wp_send_json_error( 'Too many authentication attempts. Please try again later.' );
		}
		
		// CSRF protection for public endpoint
		$csrf_token = sanitize_text_field( $_POST['csrf_token'] ?? '' );
		if ( ! $this->validate_csrf_token( $csrf_token ) ) {
			wp_send_json_error( 'Security validation failed. Please refresh and try again.' );
		}
		
		// Start session if not already started
		if ( ! session_id() ) {
			session_start();
		}

		$credential_id = sanitize_text_field( $_POST['credential_id'] ?? '' );
		$client_data_json = sanitize_text_field( $_POST['client_data_json'] ?? '' );
		$authenticator_data = sanitize_text_field( $_POST['authenticator_data'] ?? '' );
		$signature = sanitize_text_field( $_POST['signature'] ?? '' );
		$user_handle = sanitize_text_field( $_POST['user_handle'] ?? '' );

		if ( empty( $credential_id ) || empty( $client_data_json ) || empty( $authenticator_data ) || empty( $signature ) ) {
			wp_send_json_error( 'Missing required data' );
		}

		$user_id = $this->webauthn->process_authentication( 
			$credential_id, 
			$client_data_json, 
			$authenticator_data, 
			$signature, 
			$user_handle 
		);

		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error( $user_id->get_error_message() );
		}

		// Log the user in
		wp_clear_auth_cookie();
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );

		// Get redirect URL
		$redirect_to = $_POST['redirect_to'] ?? admin_url();
		$redirect_to = apply_filters( 'login_redirect', $redirect_to, $redirect_to, get_user_by( 'id', $user_id ) );

		wp_send_json_success( array(
			'redirect' => $redirect_to,
			'user_id' => $user_id
		) );
	}

	/**
	 * Handle authenticate request (alias for handle_authenticate_passkey)
	 */
	public function handle_authenticate() {
		// Rate limiting - 5 authentication attempts per 5 minutes
		if ( $this->is_rate_limited( 'auth_attempt', 5, 300 ) ) {
			wp_send_json_error( 'Too many authentication attempts. Please try again later.' );
		}
		
		// CSRF protection for public endpoint
		$csrf_token = sanitize_text_field( $_POST['csrf_token'] ?? '' );
		if ( ! $this->validate_csrf_token( $csrf_token ) ) {
			wp_send_json_error( 'Security validation failed. Please refresh and try again.' );
		}
		
		// Start session if not already started
		if ( ! session_id() ) {
			session_start();
		}

		$this->debug_log( 'handle_authenticate called' );

		$credential_id = sanitize_text_field( $_POST['credential_id'] ?? '' );
		$client_data_json = sanitize_text_field( $_POST['client_data_json'] ?? '' );
		$authenticator_data = sanitize_text_field( $_POST['authenticator_data'] ?? '' );
		$signature = sanitize_text_field( $_POST['signature'] ?? '' );
		$user_handle = sanitize_text_field( $_POST['user_handle'] ?? '' );

		$this->debug_log( 'Authentication data received - credential_id: ' . substr( $credential_id, 0, 20 ) . '...' );

		if ( empty( $credential_id ) || empty( $client_data_json ) || empty( $authenticator_data ) || empty( $signature ) ) {
			$this->debug_log( 'Missing required data in authentication' );
			wp_send_json_error( 'Missing required data' );
		}

		$user_id = $this->webauthn->process_authentication( 
			$credential_id, 
			$client_data_json, 
			$authenticator_data, 
			$signature, 
			$user_handle 
		);

		if ( is_wp_error( $user_id ) ) {
			$this->debug_log( 'Authentication failed - ' . $user_id->get_error_message() );
			wp_send_json_error( $user_id->get_error_message() );
		}

		$this->debug_log( 'Authentication successful for user ID: ' . $user_id );

		// Log the user in
		wp_clear_auth_cookie();
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );

		// Get redirect URL
		$redirect_to = $_POST['redirect_to'] ?? admin_url();
		$redirect_to = apply_filters( 'login_redirect', $redirect_to, $redirect_to, get_user_by( 'id', $user_id ) );

		wp_send_json_success( array(
			'redirect_url' => $redirect_to,
			'user_id' => $user_id
		) );
	}

	/**
	 * Handle delete passkey request
	 */
	public function handle_delete_passkey() {
		if ( ! check_ajax_referer( 'byebyepw_ajax', 'nonce', false ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Not logged in' );
		}

		$user_id = get_current_user_id();
		$passkey_id = intval( $_POST['passkey_id'] ?? 0 );

		if ( empty( $passkey_id ) ) {
			wp_send_json_error( 'Missing passkey ID' );
		}

		$result = $this->webauthn->delete_credential_by_id( $passkey_id, $user_id );

		if ( ! $result ) {
			wp_send_json_error( 'Failed to delete passkey' );
		}

		wp_send_json_success( 'Passkey deleted successfully' );
	}

	/**
	 * Handle generate recovery codes request
	 */
	public function handle_generate_recovery_codes() {
		if ( ! check_ajax_referer( 'byebyepw_ajax', 'nonce', false ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Not logged in' );
		}

		$user_id = get_current_user_id();
		$codes = $this->recovery_codes->generate_recovery_codes( $user_id );

		if ( empty( $codes ) ) {
			wp_send_json_error( 'Failed to generate recovery codes' );
		}

		$html = $this->recovery_codes->format_codes_for_display( $codes );

		wp_send_json_success( array(
			'codes' => $codes,
			'html' => $html
		) );
	}

	/**
	 * Handle authenticate with recovery code
	 */
	public function handle_authenticate_recovery_code() {
		// Rate limiting - 3 recovery code attempts per 10 minutes (stricter)
		if ( $this->is_rate_limited( 'recovery_attempt', 3, 600 ) ) {
			wp_send_json_error( 'Too many recovery code attempts. Please try again later.' );
		}
		
		// CSRF protection for public endpoint  
		$csrf_token = sanitize_text_field( $_POST['csrf_token'] ?? '' );
		if ( ! $this->validate_csrf_token( $csrf_token ) ) {
			wp_send_json_error( 'Security validation failed. Please refresh and try again.' );
		}
		
		$username = sanitize_text_field( $_POST['username'] ?? '' );
		$recovery_code = sanitize_text_field( $_POST['recovery_code'] ?? '' );

		if ( empty( $username ) || empty( $recovery_code ) ) {
			wp_send_json_error( 'Missing required data' );
		}

		$user = get_user_by( 'login', $username );
		if ( ! $user ) {
			// Use same error message to prevent username enumeration
			wp_send_json_error( 'Authentication failed' );
		}

		// Verify recovery code
		if ( ! $this->recovery_codes->verify_recovery_code( $user->ID, $recovery_code ) ) {
			wp_send_json_error( 'Authentication failed' );
		}

		// Log the user in
		wp_clear_auth_cookie();
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );

		// Get redirect URL
		$redirect_to = $_POST['redirect_to'] ?? admin_url();
		$redirect_to = apply_filters( 'login_redirect', $redirect_to, $redirect_to, $user );

		wp_send_json_success( array(
			'redirect' => $redirect_to,
			'user_id' => $user->ID,
			'remaining_codes' => $this->recovery_codes->get_remaining_codes_count( $user->ID )
		) );
	}
}