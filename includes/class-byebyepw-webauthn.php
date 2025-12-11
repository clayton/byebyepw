<?php

/**
 * WebAuthn functionality handler
 *
 * @link       https://claytonlz.com
 * @since      1.0.0
 *
 * @package    Byebyepw
 * @subpackage Byebyepw/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Include all necessary WebAuthn library files
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'lib/WebAuthn/src/WebAuthn.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'lib/WebAuthn/src/WebAuthnException.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'lib/WebAuthn/src/Binary/ByteBuffer.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'lib/WebAuthn/src/CBOR/CborDecoder.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'lib/WebAuthn/src/Attestation/AttestationObject.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'lib/WebAuthn/src/Attestation/AuthenticatorData.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'lib/WebAuthn/src/Attestation/Format/FormatBase.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'lib/WebAuthn/src/Attestation/Format/None.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'lib/WebAuthn/src/Attestation/Format/U2f.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'lib/WebAuthn/src/Attestation/Format/Packed.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'lib/WebAuthn/src/Attestation/Format/Tpm.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'lib/WebAuthn/src/Attestation/Format/Apple.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'lib/WebAuthn/src/Attestation/Format/AndroidKey.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'lib/WebAuthn/src/Attestation/Format/AndroidSafetyNet.php';

/**
 * Handles WebAuthn operations for passkey authentication.
 *
 * @since      1.0.0
 * @package    Byebyepw
 * @subpackage Byebyepw/includes
 * @author     Clayton <clayton@claytonlz.com>
 */
class Byebyepw_WebAuthn {

	/**
	 * WebAuthn library instance
	 *
	 * @var \lbuchs\WebAuthn\WebAuthn
	 */
	private $webauthn;

	/**
	 * Debug logging helper
	 * Only logs when WP_DEBUG is enabled
	 *
	 * @param string $message The message to log
	 */
	private function debug_log( $message ) {
		// Debug logging disabled for WordPress.org compliance
		// if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
		//     error_log( 'ByeByePW: ' . esc_html( $message ) );
		// }
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		$rp_name = get_bloginfo( 'name' );
		$rp_id = wp_parse_url( home_url(), PHP_URL_HOST );
		
		// Initialize WebAuthn with none attestation (simplest option)
		$this->webauthn = new \lbuchs\WebAuthn\WebAuthn( $rp_name, $rp_id, ['none'] );
	}

	/**
	 * Generate registration challenge for new passkey
	 */
	public function get_registration_challenge( $user_id ) {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return new WP_Error( 'invalid_user', 'Invalid user ID' );
		}

		// Get existing credentials to exclude
		$existing_credentials = $this->get_user_credentials( $user_id );
		$exclude_credentials = [];
		
		// Debug log
		$this->debug_log( 'Found ' . count( $existing_credentials ) . ' existing credentials for user ' . $user_id );
		
		foreach ( $existing_credentials as $cred ) {
			$exclude_credentials[] = base64_decode( $cred->credential_id );
			$this->debug_log( 'Excluding credential: ' . $cred->name . ' (ID: ' . substr( $cred->credential_id, 0, 20 ) . '...)' );
		}

		// Generate challenge
		$create_args = $this->webauthn->getCreateArgs(
			$user->ID,
			$user->user_login,
			$user->display_name,
			20, // timeout in seconds
			true, // require resident key for passkeys
			'preferred', // user verification
			null, // attestation conveyance preference
			$exclude_credentials,
			true // cross-platform
		);

		// Get challenge from WebAuthn library
		$challenge = $this->webauthn->getChallenge();
		
		// Generate secure challenge identifier to prevent predictable keys
		$challenge_id = wp_generate_uuid4();
		$user_id = get_current_user_id();
		
		// Store challenge with secure binding
		$challenge_data = [
			'challenge' => base64_encode( $challenge ),
			'user_id' => $user_id,
			'created' => time(),
			'type' => 'registration',
			'ip_address' => sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ),
			'used' => false
		];
		
		$transient_key = 'byebyepw_challenge_' . $challenge_id;
		$transient_result = set_transient( $transient_key, $challenge_data, 300 );
		$this->debug_log( 'Stored challenge with secure key for user ' . $user_id . ': ' . ($transient_result ? 'SUCCESS' : 'FAILED') );
		
		// Store challenge ID in session for retrieval
		if ( ! session_id() ) {
			session_start();
		}
		$_SESSION['webauthn_challenge_id'] = $challenge_id;
		$_SESSION['webauthn_challenge_created'] = time();
		$this->debug_log( 'Stored challenge ID in session: ' . $challenge_id );
		
		// Convert binary fields to base64url for JSON transmission
		if ( isset( $create_args->publicKey->challenge ) ) {
			$create_args->publicKey->challenge = $this->base64url_encode( $create_args->publicKey->challenge );
		}
		if ( isset( $create_args->publicKey->user->id ) ) {
			$create_args->publicKey->user->id = $this->base64url_encode( $create_args->publicKey->user->id );
		}
		
		// Convert exclude credentials
		if ( ! empty( $create_args->publicKey->excludeCredentials ) ) {
			foreach ( $create_args->publicKey->excludeCredentials as &$cred ) {
				if ( isset( $cred->id ) ) {
					$cred->id = $this->base64url_encode( $cred->id );
				}
			}
		}

		return $create_args;
	}

	/**
	 * Process registration response
	 */
	public function process_registration( $user_id, $client_data_json, $attestation_object, $name = 'Unnamed Passkey' ) {
		$this->debug_log( 'Starting registration process for user ' . $user_id );
		
		// Get challenge ID from session
		if ( ! session_id() ) {
			session_start();
		}
		
		$challenge_id = sanitize_text_field( $_SESSION['webauthn_challenge_id'] ?? '' ) ?: null;
		if ( ! $challenge_id ) {
			$this->debug_log( 'ERROR - No challenge ID in session' );
			return new WP_Error( 'no_challenge_id', 'No challenge ID found in session' );
		}
		
		// Retrieve challenge data with validation
		$transient_key = 'byebyepw_challenge_' . $challenge_id;
		$challenge_data = get_transient( $transient_key );
		
		if ( ! $challenge_data || ! is_array( $challenge_data ) ) {
			$this->debug_log( 'ERROR - No challenge data found for ID: ' . $challenge_id );
			return new WP_Error( 'no_challenge', 'Challenge not found or expired' );
		}
		
		// Validate challenge data
		if ( $challenge_data['user_id'] !== $user_id ) {
			$this->debug_log( 'ERROR - Challenge user ID mismatch' );
			delete_transient( $transient_key );
			unset( $_SESSION['webauthn_challenge_id'] );
			return new WP_Error( 'challenge_user_mismatch', 'Challenge user ID mismatch' );
		}
		
		if ( $challenge_data['type'] !== 'registration' ) {
			$this->debug_log( 'ERROR - Challenge type mismatch' );
			delete_transient( $transient_key );
			unset( $_SESSION['webauthn_challenge_id'] );
			return new WP_Error( 'challenge_type_mismatch', 'Invalid challenge type' );
		}
		
		if ( $challenge_data['used'] ) {
			$this->debug_log( 'ERROR - Challenge already used' );
			delete_transient( $transient_key );
			unset( $_SESSION['webauthn_challenge_id'] );
			return new WP_Error( 'challenge_already_used', 'Challenge already used' );
		}
		
		// Mark challenge as used immediately to prevent replay
		$challenge_data['used'] = true;
		set_transient( $transient_key, $challenge_data, 60 ); // Keep for 1 minute for debugging
		
		$challenge = base64_decode( $challenge_data['challenge'] );
		$this->debug_log( 'Retrieved and validated challenge (length: ' . strlen( $challenge ) . ')' );

		try {
			// Decode base64url to binary
			$this->debug_log( 'Decoding client data and attestation object' );
			$client_data_json_decoded = $this->base64url_decode( $client_data_json );
			$attestation_object_decoded = $this->base64url_decode( $attestation_object );
			
			$this->debug_log( 'Client data length: ' . strlen( $client_data_json_decoded ) );
			$this->debug_log( 'Attestation object length: ' . strlen( $attestation_object_decoded ) );
			$this->debug_log( 'Challenge being used: ' . base64_encode( $challenge ) );
			
			// Parse client data to check challenge match
			$client_data = json_decode( $client_data_json_decoded, true );
			if ( $client_data ) {
				$this->debug_log( 'Client data type: ' . ($client_data['type'] ?? 'unknown') );
				$this->debug_log( 'Client data origin: ' . ($client_data['origin'] ?? 'unknown') );
				if ( isset( $client_data['challenge'] ) ) {
					$this->debug_log( 'Client challenge (base64url): ' . $client_data['challenge'] );
					$this->debug_log( 'Expected challenge (base64url): ' . $this->base64url_encode( $challenge ) );
				}
			} else {
				$this->debug_log( 'Failed to parse client data JSON' );
			}
			
			$this->debug_log( 'Calling processCreate...' );
			$data = $this->webauthn->processCreate(
				$client_data_json_decoded,
				$attestation_object_decoded,
				$challenge,
				false, // user verification not required
				true  // fail if root mismatch
			);
			
			$this->debug_log( 'processCreate succeeded!' );
			$this->debug_log( 'Credential ID: ' . base64_encode( $data->credentialId ) );

			// Save credential to database
			$result = $this->save_credential( $user_id, $data, $name );
			if ( $result ) {
				$this->debug_log( 'Credential saved to database successfully' );
			} else {
				$this->debug_log( 'Failed to save credential to database' );
			}

			// Clear challenge data
			unset( $_SESSION['webauthn_challenge_id'], $_SESSION['webauthn_challenge_created'] );
			delete_transient( $transient_key );

			return true;
		} catch ( \lbuchs\WebAuthn\WebAuthnException $e ) {
			$this->debug_log( 'WebAuthnException: ' . esc_html( $e->getMessage() ) );
			$this->debug_log( 'Exception code: ' . $e->getCode() );
			$this->debug_log( 'Stack trace: ' . esc_html( $e->getTraceAsString() ) );
			return new WP_Error( 'registration_failed', esc_html( $e->getMessage() ) );
		} catch ( \Exception $e ) {
			$this->debug_log( 'General Exception: ' . esc_html( $e->getMessage() ) );
			$this->debug_log( 'Exception code: ' . $e->getCode() );
			$this->debug_log( 'Stack trace: ' . esc_html( $e->getTraceAsString() ) );
			return new WP_Error( 'registration_failed', esc_html( $e->getMessage() ) );
		} catch ( \Throwable $e ) {
			$this->debug_log( 'Throwable: ' . esc_html( $e->getMessage() ) );
			$this->debug_log( 'Exception code: ' . $e->getCode() );
			$this->debug_log( 'Stack trace: ' . esc_html( $e->getTraceAsString() ) );
			return new WP_Error( 'registration_failed', esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Generate authentication challenge
	 */
	public function get_authentication_challenge( $user_id = null ) {
		$this->debug_log( 'get_authentication_challenge called with user_id: ' . $user_id );
		
		$credential_ids = [];

		if ( $user_id ) {
			// Get credentials for specific user
			$credentials = $this->get_user_credentials( $user_id );
			$this->debug_log( 'Found ' . count( $credentials ) . ' credentials for user ' . $user_id );
			foreach ( $credentials as $cred ) {
				$credential_ids[] = base64_decode( $cred->credential_id );
			}
		}
		// If no user_id, we'll use client-side discoverable credentials

		// Get the args (this creates a new challenge internally)
		$get_args = $this->webauthn->getGetArgs(
			$credential_ids,
			20, // timeout
			true, // allow cross-platform
			'preferred' // user verification
		);
		
		// Generate secure challenge identifier
		$challenge_id = wp_generate_uuid4();
		
		// The actual challenge is in $get_args->publicKey->challenge
		$challenge = $get_args->publicKey->challenge;
		if ( $challenge instanceof \lbuchs\WebAuthn\Binary\ByteBuffer ) {
			$challenge = $challenge->getBinaryString();
		}
		
		// Store challenge with secure binding
		$challenge_data = [
			'challenge' => base64_encode( $challenge ),
			'user_id' => $user_id, // May be null for usernameless flow
			'created' => time(),
			'type' => 'authentication',
			'ip_address' => sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ),
			'used' => false
		];
		
		$transient_key = 'byebyepw_challenge_' . $challenge_id;
		$transient_result = set_transient( $transient_key, $challenge_data, 300 );
		$this->debug_log( 'Stored auth challenge with secure key (result: ' . ( $transient_result ? 'success' : 'failed' ) . ')' );
		
		// Store challenge ID in session for retrieval
		if ( ! session_id() ) {
			session_start();
		}
		$_SESSION['webauthn_challenge_id'] = $challenge_id;
		$_SESSION['webauthn_challenge_created'] = time();
		$this->debug_log( 'Challenge length: ' . strlen( $challenge ) . ', ID: ' . $challenge_id );
		
		// Convert binary fields to base64url for JSON transmission
		if ( isset( $get_args->publicKey->challenge ) ) {
			// If challenge is a ByteBuffer, get the binary string first
			if ( $get_args->publicKey->challenge instanceof \lbuchs\WebAuthn\Binary\ByteBuffer ) {
				$get_args->publicKey->challenge = $this->base64url_encode( $get_args->publicKey->challenge->getBinaryString() );
			} else {
				$get_args->publicKey->challenge = $this->base64url_encode( $get_args->publicKey->challenge );
			}
		}
		
		// Convert allow credentials
		if ( ! empty( $get_args->publicKey->allowCredentials ) ) {
			foreach ( $get_args->publicKey->allowCredentials as &$cred ) {
				if ( isset( $cred->id ) ) {
					$cred->id = $this->base64url_encode( $cred->id );
				}
			}
		}

		$this->debug_log( 'Returning authentication options with ' . count( $credential_ids ) . ' allowed credentials' );
		return $get_args;
	}

	/**
	 * Process authentication response
	 */
	public function process_authentication( $credential_id, $client_data_json, $authenticator_data, $signature, $user_handle = null ) {
		$this->debug_log( 'process_authentication called' );
		$this->debug_log( 'credential_id: ' . substr( $credential_id, 0, 20 ) . '...' );
		
		if ( ! session_id() ) {
			session_start();
		}

		// Get challenge ID from session
		$challenge_id = sanitize_text_field( $_SESSION['webauthn_challenge_id'] ?? '' ) ?: null;
		if ( ! $challenge_id ) {
			$this->debug_log( 'ERROR - No challenge ID in session' );
			return new WP_Error( 'no_challenge_id', 'No challenge ID found in session' );
		}
		
		// Retrieve challenge data with validation
		$transient_key = 'byebyepw_challenge_' . $challenge_id;
		$challenge_data = get_transient( $transient_key );
		
		if ( ! $challenge_data || ! is_array( $challenge_data ) ) {
			$this->debug_log( 'ERROR - No challenge data found for ID: ' . $challenge_id );
			return new WP_Error( 'no_challenge', 'Challenge not found or expired' );
		}
		
		// Validate challenge data
		if ( $challenge_data['type'] !== 'authentication' ) {
			$this->debug_log( 'ERROR - Challenge type mismatch' );
			delete_transient( $transient_key );
			unset( $_SESSION['webauthn_challenge_id'] );
			return new WP_Error( 'challenge_type_mismatch', 'Invalid challenge type' );
		}
		
		if ( $challenge_data['used'] ) {
			$this->debug_log( 'ERROR - Challenge already used' );
			delete_transient( $transient_key );
			unset( $_SESSION['webauthn_challenge_id'] );
			return new WP_Error( 'challenge_already_used', 'Challenge already used' );
		}
		
		// Mark challenge as used immediately to prevent replay
		$challenge_data['used'] = true;
		set_transient( $transient_key, $challenge_data, 60 ); // Keep for 1 minute for debugging
		
		$challenge = base64_decode( $challenge_data['challenge'] );
		$this->debug_log( 'Retrieved and validated auth challenge (length: ' . strlen( $challenge ) . ')' );

		// Decode credential_id if it's base64url encoded
		$credential_id_decoded = $this->base64url_decode( $credential_id );
		
		// Get credential from database (try both encoded and decoded versions)
		$credential = $this->get_credential_by_id( base64_encode( $credential_id_decoded ) );
		if ( ! $credential ) {
			// Try with the original credential_id in case it was already base64 standard
			$credential = $this->get_credential_by_id( $credential_id );
		}
		
		if ( ! $credential ) {
			$this->debug_log( 'ERROR - Credential not found in database' );
			$this->debug_log( 'Tried credential_id (base64): ' . base64_encode( $credential_id_decoded ) );
			return new WP_Error( 'invalid_credential', 'Credential not found' );
		}

		$this->debug_log( 'Found credential for user ' . $credential->user_id );

		try {
			// Decode the response data from base64url
			$client_data_json_decoded = $this->base64url_decode( $client_data_json );
			$authenticator_data_decoded = $this->base64url_decode( $authenticator_data );
			$signature_decoded = $this->base64url_decode( $signature );
			
			// Debug: Let's see what the client data contains
			$client_data = json_decode( $client_data_json_decoded );
			if ( $client_data && isset( $client_data->challenge ) ) {
				$this->debug_log( 'Client challenge (base64url): ' . $client_data->challenge );
				$client_challenge_decoded = $this->base64url_decode( $client_data->challenge );
				$this->debug_log( 'Client challenge decoded: ' . $client_challenge_decoded );
				$this->debug_log( 'Client challenge decoded length: ' . strlen( $client_challenge_decoded ) );
				$this->debug_log( 'Expected challenge base64: ' . base64_encode( $challenge ) );
				$this->debug_log( 'Expected challenge hex: ' . bin2hex( $challenge ) );
			}
			
			$this->debug_log( 'Calling processGet with decoded data' );
			$this->debug_log( 'Credential sign_count from DB: ' . $credential->sign_count );
			
			// Parse actual sign count from authenticator data
			$actual_sign_count = 0;
			if (strlen($authenticator_data_decoded) >= 37) {
				$sign_count_bytes = substr($authenticator_data_decoded, 33, 4);
				$actual_sign_count = unpack('N', $sign_count_bytes)[1];
				$this->debug_log( 'Actual sign_count from authenticator: ' . $actual_sign_count );
			}
			
			// Get stored sign count from database
			$stored_sign_count = isset($credential->sign_count) ? intval($credential->sign_count) : 0;
			$this->debug_log( 'Stored sign_count from DB: ' . $stored_sign_count );
			
			// Validate sign count with lenient rules for platform authenticators
			// Many authenticators (Touch ID, Face ID, Windows Hello) don't properly implement counters
			if ( $actual_sign_count > 0 && $stored_sign_count > 0 && $actual_sign_count <= $stored_sign_count ) {
				$this->debug_log( 'WARNING - Sign count did not increment. Stored: ' . $stored_sign_count . ', Actual: ' . $actual_sign_count );
				// Only fail if there's a significant regression (possible cloning)
				if ( $stored_sign_count - $actual_sign_count > 10 ) {
					$this->debug_log( 'ERROR - Sign count regression too large, possible cloned authenticator' );
					return new WP_Error( 'sign_count_invalid', 'Sign count validation failed - possible cloned authenticator' );
				} else {
					$this->debug_log( 'INFO - Allowing minor sign count inconsistency (common with platform authenticators)' );
				}
			}
			
			// Pass null to disable library's strict sign count validation
			// We handle sign count validation ourselves above with more lenient rules
			$this->webauthn->processGet(
				$client_data_json_decoded,
				$authenticator_data_decoded,
				$signature_decoded,
				$credential->public_key,
				$challenge,
				null, // Disable library sign count validation
				false // user verification not required
			);

			$this->debug_log( 'processGet succeeded!' );

			// Update sign count and last used
			$this->update_credential_usage( $credential->credential_id, $actual_sign_count );

			// Clear challenge data
			unset( $_SESSION['webauthn_challenge_id'], $_SESSION['webauthn_challenge_created'] );
			delete_transient( $transient_key );

			$this->debug_log( 'Authentication successful for user ' . $credential->user_id );
			return $credential->user_id;
		} catch ( \lbuchs\WebAuthn\WebAuthnException $e ) {
			$this->debug_log( 'WebAuthnException during authentication: ' . esc_html( $e->getMessage() ) );
			return new WP_Error( 'authentication_failed', esc_html( $e->getMessage() ) );
		} catch ( \Exception $e ) {
			$this->debug_log( 'General Exception during authentication: ' . esc_html( $e->getMessage() ) );
			return new WP_Error( 'authentication_failed', esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Save credential to database
	 */
	private function save_credential( $user_id, $data, $name = 'Unnamed Passkey' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'byebyepw_passkeys';

		// Log the data structure for debugging (disabled for production)
		// $this->debug_log( 'Saving credential data structure: ' . wp_json_encode( $data ) );

		// Handle sign_count - it might be null or not set
		$sign_count = 0;
		if ( isset( $data->signCount ) ) {
			$sign_count = intval( $data->signCount );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert for passkey storage
		return $wpdb->insert(
			$table,
			array(
				'user_id' => $user_id,
				'name' => $name,
				'credential_id' => base64_encode( $data->credentialId ),
				'public_key' => $data->credentialPublicKey,
				'sign_count' => $sign_count,
				'attestation_format' => $data->attestationFormat,
				'user_handle' => base64_encode( $data->userHandle ?? '' ),
				'created_at' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Get user credentials
	 */
	public function get_user_credentials( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'byebyepw_passkeys';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query, results change frequently
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}byebyepw_passkeys WHERE user_id = %d ORDER BY created_at DESC",
			$user_id
		) );
	}

	/**
	 * Get credential by ID
	 */
	private function get_credential_by_id( $credential_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'byebyepw_passkeys';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Security-sensitive credential lookup
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}byebyepw_passkeys WHERE credential_id = %s",
			$credential_id
		) );
	}

	/**
	 * Update credential usage with actual sign count from authenticator
	 */
	private function update_credential_usage( $credential_id, $actual_sign_count = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'byebyepw_passkeys';

		// If we have actual sign count from authenticator, use it
		if ( $actual_sign_count !== null ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update
			return $wpdb->update(
				$table,
				array(
					'last_used' => current_time( 'mysql' ),
					'sign_count' => intval( $actual_sign_count ),
				),
				array( 'credential_id' => $credential_id )
			);
		}

		// Fallback: increment stored count by 1 (legacy behavior)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
		$current = $wpdb->get_row( $wpdb->prepare(
			"SELECT sign_count FROM {$wpdb->prefix}byebyepw_passkeys WHERE credential_id = %s",
			$credential_id
		) );
		
		if ( ! $current ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update
		return $wpdb->update(
			$table,
			array(
				'last_used' => current_time( 'mysql' ),
				'sign_count' => intval( $current->sign_count ) + 1,
			),
			array( 'credential_id' => $credential_id )
		);
	}

	/**
	 * Delete credential by credential_id
	 */
	public function delete_credential( $credential_id, $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'byebyepw_passkeys';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete
		return $wpdb->delete(
			$table,
			array(
				'credential_id' => $credential_id,
				'user_id' => $user_id,
			)
		);
	}
	
	/**
	 * Delete credential by row ID
	 */
	public function delete_credential_by_id( $id, $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'byebyepw_passkeys';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete
		return $wpdb->delete(
			$table,
			array(
				'id' => $id,
				'user_id' => $user_id,
			)
		);
	}
	
	/**
	 * Encode binary data to base64url
	 */
	private function base64url_encode( $data ) {
		// If data is already a string that looks like base64, return it
		if ( is_string( $data ) && preg_match( '/^[A-Za-z0-9_\-=]+$/', $data ) ) {
			return $data;
		}
		
		// Otherwise encode it
		$base64 = base64_encode( $data );
		return rtrim( strtr( $base64, '+/', '-_' ), '=' );
	}
	
	/**
	 * Decode base64url to binary data
	 */
	private function base64url_decode( $data ) {
		return base64_decode( strtr( $data, '-_', '+/' ) );
	}
}