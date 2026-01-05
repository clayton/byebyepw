<?php

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
	 * @return array Array containing both csrf_token and nonce
	 */
	private function generate_csrf_token() {
		// Generate a unique token identifier
		$token_id = wp_generate_uuid4();
		$csrf_token = bin2hex( random_bytes( 16 ) );

		// Store token in transient with token_id as key
		$token_data = array(
			'token'   => $csrf_token,
			'expires' => time() + 1800, // 30 minutes
		);
		set_transient( 'byebyepw_csrf_' . $token_id, $token_data, 1800 );

		// Set cookie with token_id for retrieval
		Byebyepw::set_csrf_cookie( $token_id );

		return array(
			'csrf_token' => $csrf_token,
			'nonce'      => wp_create_nonce( 'byebyepw_security' ),
		);
	}
	
	/**
	 * Validate CSRF token for public endpoints
	 *
	 * @param string $token Token to validate
	 * @return bool True if valid
	 */
	private function validate_csrf_token( $token ) {
		// Get token_id from cookie
		$token_id = Byebyepw::get_csrf_cookie();
		if ( ! $token_id ) {
			return false;
		}

		// Get token data from transient
		$token_data = get_transient( 'byebyepw_csrf_' . $token_id );
		if ( ! $token_data || ! is_array( $token_data ) ) {
			return false;
		}

		// Check expiration
		if ( ! isset( $token_data['expires'] ) || $token_data['expires'] < time() ) {
			delete_transient( 'byebyepw_csrf_' . $token_id );
			return false;
		}

		// Use hash_equals for constant-time comparison
		if ( ! isset( $token_data['token'] ) ) {
			return false;
		}

		return hash_equals( $token_data['token'], $token );
	}

	/**
	 * Verify both nonce and CSRF token for security
	 *
	 * @param string $nonce Nonce to verify  
	 * @param string $csrf_token CSRF token to verify
	 * @return bool True if both are valid
	 */
	private function verify_security_tokens( $nonce, $csrf_token ) {
		// Verify WordPress nonce
		$nonce_valid = wp_verify_nonce( $nonce, 'byebyepw_security' );
		
		// Verify CSRF token
		$csrf_valid = $this->validate_csrf_token( $csrf_token );
		
		return $nonce_valid && $csrf_valid;
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
		$ip_address = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) );
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
		// Admin AJAX handlers (logged in users only)
		add_action( 'wp_ajax_byebyepw_get_registration_challenge', array( $this, 'handle_get_registration_challenge' ) );
		add_action( 'wp_ajax_byebyepw_register_passkey', array( $this, 'handle_register_passkey' ) );
		add_action( 'wp_ajax_byebyepw_delete_passkey', array( $this, 'handle_delete_passkey' ) );
		add_action( 'wp_ajax_byebyepw_generate_recovery_codes', array( $this, 'handle_generate_recovery_codes' ) );

		// Public AJAX handlers (for login page - unauthenticated users)
		add_action( 'wp_ajax_nopriv_byebyepw_get_authentication_challenge', array( $this, 'handle_get_authentication_challenge' ) );
		add_action( 'wp_ajax_nopriv_byebyepw_authenticate_passkey', array( $this, 'handle_authenticate_passkey' ) );
		add_action( 'wp_ajax_nopriv_byebyepw_authenticate_recovery_code', array( $this, 'handle_authenticate_recovery_code' ) );

		// Allow logged in users to also use authentication endpoints (for re-authentication scenarios)
		add_action( 'wp_ajax_byebyepw_get_authentication_challenge', array( $this, 'handle_get_authentication_challenge' ) );
		add_action( 'wp_ajax_byebyepw_authenticate_passkey', array( $this, 'handle_authenticate_passkey' ) );
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
		$client_data_json = sanitize_text_field( wp_unslash( $_POST['client_data_json'] ?? '' ) );
		$attestation_object = sanitize_text_field( wp_unslash( $_POST['attestation_object'] ?? '' ) );
		$name = sanitize_text_field( wp_unslash( $_POST['name'] ?? 'Unnamed Passkey' ) );

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

		// Verify WordPress nonce (required by WordPress.org)
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'byebyepw_security' ) ) {
			wp_send_json_error( 'Security validation failed. Please refresh and try again.' );
		}

		// Check if specific user is requested (for username+passkey flow)
		$user_id = null;
		$username = sanitize_text_field( wp_unslash( $_POST['username'] ?? '' ) );
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

		// Add CSRF token and nonce to challenge response for subsequent authentication
		$security_tokens = $this->generate_csrf_token();
		$result->csrf_token = $security_tokens['csrf_token'];
		$result->nonce = $security_tokens['nonce'];

		wp_send_json_success( $result );
	}

	/**
	 * Handle authenticate passkey request
	 */
	public function handle_authenticate_passkey() {
		// Rate limiting - 5 authentication attempts per 5 minutes
		if ( $this->is_rate_limited( 'auth_attempt', 5, 300 ) ) {
			wp_send_json_error( 'Too many authentication attempts. Please try again later.' );
		}
		
		// Verify WordPress nonce first (required by WordPress.org)
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'byebyepw_security' ) ) {
			wp_send_json_error( 'Security validation failed. Please refresh and try again.' );
		}
		
		// Additional CSRF protection for public endpoint
		$csrf_token = sanitize_text_field( wp_unslash( $_POST['csrf_token'] ?? '' ) );
		if ( ! $this->validate_csrf_token( $csrf_token ) ) {
			wp_send_json_error( 'Security validation failed. Please refresh and try again.' );
		}

		$credential_id = sanitize_text_field( wp_unslash( $_POST['credential_id'] ?? '' ) );
		$client_data_json = sanitize_text_field( wp_unslash( $_POST['client_data_json'] ?? '' ) );
		$authenticator_data = sanitize_text_field( wp_unslash( $_POST['authenticator_data'] ?? '' ) );
		$signature = sanitize_text_field( wp_unslash( $_POST['signature'] ?? '' ) );
		$user_handle = sanitize_text_field( wp_unslash( $_POST['user_handle'] ?? '' ) );

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
		$redirect_to = esc_url_raw( wp_unslash( $_POST['redirect_to'] ?? admin_url() ) );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Using WordPress core filter
		$redirect_to = apply_filters( 'login_redirect', $redirect_to, $redirect_to, get_user_by( 'id', $user_id ) );

		wp_send_json_success( array(
			'redirect' => $redirect_to,
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
		
		// Verify WordPress nonce first (required by WordPress.org)
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'byebyepw_security' ) ) {
			wp_send_json_error( 'Security validation failed. Please refresh and try again.' );
		}
		
		// Additional CSRF protection for public endpoint
		$csrf_token = sanitize_text_field( wp_unslash( $_POST['csrf_token'] ?? '' ) );
		if ( ! $this->validate_csrf_token( $csrf_token ) ) {
			wp_send_json_error( 'Security validation failed. Please refresh and try again.' );
		}
		
		$username = sanitize_text_field( wp_unslash( $_POST['username'] ?? '' ) );
		$recovery_code = sanitize_text_field( wp_unslash( $_POST['recovery_code'] ?? '' ) );

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
		$redirect_to = esc_url_raw( wp_unslash( $_POST['redirect_to'] ?? admin_url() ) );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Using WordPress core filter
		$redirect_to = apply_filters( 'login_redirect', $redirect_to, $redirect_to, $user );

		wp_send_json_success( array(
			'redirect' => $redirect_to,
			'user_id' => $user->ID,
			'remaining_codes' => $this->recovery_codes->get_remaining_codes_count( $user->ID )
		) );
	}
}