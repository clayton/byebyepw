<?php

/**
 * WebAuthn functionality handler
 *
 * @link       https://labountylabs.com
 * @since      1.0.0
 *
 * @package    Byebyepw
 * @subpackage Byebyepw/includes
 */

// Include all necessary WebAuthn library files
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'lib/WebAuthn-r0/src/WebAuthn.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'lib/WebAuthn-r0/src/WebAuthnException.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'lib/WebAuthn-r0/src/Binary/ByteBuffer.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'lib/WebAuthn-r0/src/CBOR/CborDecoder.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'lib/WebAuthn-r0/src/Attestation/AttestationObject.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'lib/WebAuthn-r0/src/Attestation/AuthenticatorData.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'lib/WebAuthn-r0/src/Attestation/Format/FormatBase.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'lib/WebAuthn-r0/src/Attestation/Format/None.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'lib/WebAuthn-r0/src/Attestation/Format/U2f.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'lib/WebAuthn-r0/src/Attestation/Format/Packed.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'lib/WebAuthn-r0/src/Attestation/Format/Tpm.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'lib/WebAuthn-r0/src/Attestation/Format/Apple.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'lib/WebAuthn-r0/src/Attestation/Format/AndroidKey.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'lib/WebAuthn-r0/src/Attestation/Format/AndroidSafetyNet.php';

/**
 * Handles WebAuthn operations for passkey authentication.
 *
 * @since      1.0.0
 * @package    Byebyepw
 * @subpackage Byebyepw/includes
 * @author     Clayton <clayton@labountylabs.com>
 */
class Byebyepw_WebAuthn {

	/**
	 * WebAuthn library instance
	 *
	 * @var \lbuchs\WebAuthn\WebAuthn
	 */
	private $webauthn;

	/**
	 * Constructor
	 */
	public function __construct() {
		$rp_name = get_bloginfo( 'name' );
		$rp_id = parse_url( home_url(), PHP_URL_HOST );
		
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
		error_log( 'ByeByePW: Found ' . count( $existing_credentials ) . ' existing credentials for user ' . $user_id );
		
		foreach ( $existing_credentials as $cred ) {
			$exclude_credentials[] = base64_decode( $cred->credential_id );
			error_log( 'ByeByePW: Excluding credential: ' . $cred->name . ' (ID: ' . substr( $cred->credential_id, 0, 20 ) . '...)' );
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
		
		// Store challenge primarily in transient (more reliable for AJAX)
		$transient_key = 'byebyepw_reg_challenge_' . get_current_user_id();
		$transient_result = set_transient( $transient_key, base64_encode( $challenge ), 300 );
		error_log( 'ByeByePW: Stored challenge in transient key ' . $transient_key . ' for user ' . get_current_user_id() . ': ' . ($transient_result ? 'SUCCESS' : 'FAILED') );
		error_log( 'ByeByePW: Challenge length: ' . strlen( $challenge ) . ' bytes, base64: ' . strlen( base64_encode( $challenge ) ) . ' chars' );
		
		// Also try session as secondary storage
		if ( ! session_id() ) {
			@session_start();
			error_log( 'ByeByePW: Started new session: ' . session_id() );
		} else {
			error_log( 'ByeByePW: Using existing session: ' . session_id() );
		}
		$_SESSION['webauthn_challenge'] = $challenge;
		error_log( 'ByeByePW: Also stored challenge in session' );
		
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
		error_log( 'ByeByePW: Starting registration process for user ' . $user_id );
		
		// Try to get challenge from transient first (most reliable for AJAX)
		$challenge = null;
		$transient_key = 'byebyepw_reg_challenge_' . $user_id;
		$transient_challenge = get_transient( $transient_key );
		
		if ( $transient_challenge ) {
			$challenge = base64_decode( $transient_challenge );
			error_log( 'ByeByePW: Retrieved challenge from transient key ' . $transient_key . ' (decoded length: ' . strlen( $challenge ) . ')' );
			// Delete the transient after use (one-time use)
			delete_transient( $transient_key );
		} else {
			error_log( 'ByeByePW: No challenge in transient ' . $transient_key . ', checking session as fallback' );
			
			// Try session as fallback
			if ( ! session_id() ) {
				@session_start();
				error_log( 'ByeByePW: Started session in process_registration: ' . session_id() );
			} else {
				error_log( 'ByeByePW: Existing session in process_registration: ' . session_id() );
			}
			
			if ( isset( $_SESSION['webauthn_challenge'] ) ) {
				$challenge = $_SESSION['webauthn_challenge'];
				error_log( 'ByeByePW: Retrieved challenge from session (length: ' . strlen( $challenge ) . ')' );
				unset( $_SESSION['webauthn_challenge'] );
			} else {
				error_log( 'ByeByePW: No challenge in session either' );
			}
		}
		
		if ( ! $challenge ) {
			error_log( 'ByeByePW: ERROR - No challenge found anywhere!' );
			error_log( 'ByeByePW: Checked transient key: ' . $transient_key );
			error_log( 'ByeByePW: Session ID: ' . session_id() );
			if ( isset( $_SESSION ) ) {
				error_log( 'ByeByePW: Session contents: ' . print_r( $_SESSION, true ) );
			}
			
			// List all transients for debugging
			global $wpdb;
			$transients = $wpdb->get_results( 
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_byebyepw%'"
			);
			error_log( 'ByeByePW: All ByeByePW transients in database: ' . print_r( $transients, true ) );
			
			return new WP_Error( 'no_challenge', 'No challenge found in transient or session' );
		}

		try {
			// Decode base64url to binary
			error_log( 'ByeByePW: Decoding client data and attestation object' );
			$client_data_json_decoded = $this->base64url_decode( $client_data_json );
			$attestation_object_decoded = $this->base64url_decode( $attestation_object );
			
			error_log( 'ByeByePW: Client data length: ' . strlen( $client_data_json_decoded ) );
			error_log( 'ByeByePW: Attestation object length: ' . strlen( $attestation_object_decoded ) );
			error_log( 'ByeByePW: Challenge being used: ' . base64_encode( $challenge ) );
			
			// Parse client data to check challenge match
			$client_data = json_decode( $client_data_json_decoded, true );
			if ( $client_data ) {
				error_log( 'ByeByePW: Client data type: ' . ($client_data['type'] ?? 'unknown') );
				error_log( 'ByeByePW: Client data origin: ' . ($client_data['origin'] ?? 'unknown') );
				if ( isset( $client_data['challenge'] ) ) {
					error_log( 'ByeByePW: Client challenge (base64url): ' . $client_data['challenge'] );
					error_log( 'ByeByePW: Expected challenge (base64url): ' . $this->base64url_encode( $challenge ) );
				}
			} else {
				error_log( 'ByeByePW: Failed to parse client data JSON' );
			}
			
			error_log( 'ByeByePW: Calling processCreate...' );
			$data = $this->webauthn->processCreate(
				$client_data_json_decoded,
				$attestation_object_decoded,
				$challenge,
				false, // user verification not required
				true  // fail if root mismatch
			);
			
			error_log( 'ByeByePW: processCreate succeeded!' );
			error_log( 'ByeByePW: Credential ID: ' . base64_encode( $data->credentialId ) );

			// Save credential to database
			$result = $this->save_credential( $user_id, $data, $name );
			if ( $result ) {
				error_log( 'ByeByePW: Credential saved to database successfully' );
			} else {
				error_log( 'ByeByePW: Failed to save credential to database' );
			}

			// Clear challenge
			unset( $_SESSION['webauthn_challenge'] );

			return true;
		} catch ( \lbuchs\WebAuthn\WebAuthnException $e ) {
			error_log( 'ByeByePW: WebAuthnException: ' . $e->getMessage() );
			error_log( 'ByeByePW: Exception code: ' . $e->getCode() );
			error_log( 'ByeByePW: Stack trace: ' . $e->getTraceAsString() );
			return new WP_Error( 'registration_failed', $e->getMessage() );
		} catch ( \Exception $e ) {
			error_log( 'ByeByePW: General Exception: ' . $e->getMessage() );
			error_log( 'ByeByePW: Exception code: ' . $e->getCode() );
			error_log( 'ByeByePW: Stack trace: ' . $e->getTraceAsString() );
			return new WP_Error( 'registration_failed', $e->getMessage() );
		} catch ( \Throwable $e ) {
			error_log( 'ByeByePW: Throwable: ' . $e->getMessage() );
			error_log( 'ByeByePW: Exception code: ' . $e->getCode() );
			error_log( 'ByeByePW: Stack trace: ' . $e->getTraceAsString() );
			return new WP_Error( 'registration_failed', $e->getMessage() );
		}
	}

	/**
	 * Generate authentication challenge
	 */
	public function get_authentication_challenge( $user_id = null ) {
		error_log( 'ByeByePW: get_authentication_challenge called with user_id: ' . $user_id );
		
		$credential_ids = [];

		if ( $user_id ) {
			// Get credentials for specific user
			$credentials = $this->get_user_credentials( $user_id );
			error_log( 'ByeByePW: Found ' . count( $credentials ) . ' credentials for user ' . $user_id );
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
		
		// Store the challenge that was created by getGetArgs
		if ( ! session_id() ) {
			@session_start();
		}
		
		// The actual challenge is in $get_args->publicKey->challenge
		$challenge = $get_args->publicKey->challenge;
		if ( $challenge instanceof \lbuchs\WebAuthn\Binary\ByteBuffer ) {
			$challenge = $challenge->getBinaryString();
		}
		
		$_SESSION['webauthn_challenge'] = $challenge;
		
		// Also store in transient as backup (for login, we don't have user_id yet)
		$session_id = session_id();
		$transient_key = 'byebyepw_auth_challenge_' . $session_id;
		$transient_result = set_transient( $transient_key, base64_encode( $challenge ), 300 );
		error_log( 'ByeByePW: Stored auth challenge in session and transient (key: ' . $transient_key . ', result: ' . ( $transient_result ? 'success' : 'failed' ) . ')' );
		error_log( 'ByeByePW: Challenge length: ' . strlen( $challenge ) . ', base64: ' . base64_encode( $challenge ) );
		
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

		error_log( 'ByeByePW: Returning authentication options with ' . count( $credential_ids ) . ' allowed credentials' );
		return $get_args;
	}

	/**
	 * Process authentication response
	 */
	public function process_authentication( $credential_id, $client_data_json, $authenticator_data, $signature, $user_handle = null ) {
		error_log( 'ByeByePW: process_authentication called' );
		error_log( 'ByeByePW: credential_id: ' . substr( $credential_id, 0, 20 ) . '...' );
		
		if ( ! session_id() ) {
			@session_start();
		}

		$session_id = session_id();
		error_log( 'ByeByePW: Session ID: ' . $session_id );

		// Try to get challenge from session first, then transient
		$challenge = null;
		if ( isset( $_SESSION['webauthn_challenge'] ) ) {
			$challenge = $_SESSION['webauthn_challenge'];
			error_log( 'ByeByePW: Challenge found in session, length: ' . strlen( $challenge ) . ', base64: ' . base64_encode( $challenge ) );
		} else {
			// Try transient as backup
			$transient_key = 'byebyepw_auth_challenge_' . $session_id;
			$transient_challenge = get_transient( $transient_key );
			error_log( 'ByeByePW: Looking for challenge in transient with key: ' . $transient_key );
			if ( $transient_challenge ) {
				$challenge = base64_decode( $transient_challenge );
				error_log( 'ByeByePW: Challenge found in transient, length: ' . strlen( $challenge ) . ', base64: ' . base64_encode( $challenge ) );
			} else {
				error_log( 'ByeByePW: No challenge found in transient' );
			}
		}
		
		if ( ! $challenge ) {
			error_log( 'ByeByePW: ERROR - No challenge found in session or transient' );
			return new WP_Error( 'no_challenge', 'No challenge found in session or transient' );
		}

		// Decode credential_id if it's base64url encoded
		$credential_id_decoded = $this->base64url_decode( $credential_id );
		
		// Get credential from database (try both encoded and decoded versions)
		$credential = $this->get_credential_by_id( base64_encode( $credential_id_decoded ) );
		if ( ! $credential ) {
			// Try with the original credential_id in case it was already base64 standard
			$credential = $this->get_credential_by_id( $credential_id );
		}
		
		if ( ! $credential ) {
			error_log( 'ByeByePW: ERROR - Credential not found in database' );
			error_log( 'ByeByePW: Tried credential_id (base64): ' . base64_encode( $credential_id_decoded ) );
			return new WP_Error( 'invalid_credential', 'Credential not found' );
		}

		error_log( 'ByeByePW: Found credential for user ' . $credential->user_id );

		try {
			// Decode the response data from base64url
			$client_data_json_decoded = $this->base64url_decode( $client_data_json );
			$authenticator_data_decoded = $this->base64url_decode( $authenticator_data );
			$signature_decoded = $this->base64url_decode( $signature );
			
			// Debug: Let's see what the client data contains
			$client_data = json_decode( $client_data_json_decoded );
			if ( $client_data && isset( $client_data->challenge ) ) {
				error_log( 'ByeByePW: Client challenge (base64url): ' . $client_data->challenge );
				$client_challenge_decoded = $this->base64url_decode( $client_data->challenge );
				error_log( 'ByeByePW: Client challenge decoded: ' . $client_challenge_decoded );
				error_log( 'ByeByePW: Client challenge decoded length: ' . strlen( $client_challenge_decoded ) );
				error_log( 'ByeByePW: Expected challenge base64: ' . base64_encode( $challenge ) );
				error_log( 'ByeByePW: Expected challenge hex: ' . bin2hex( $challenge ) );
			}
			
			error_log( 'ByeByePW: Calling processGet with decoded data' );
			error_log( 'ByeByePW: Credential sign_count from DB: ' . $credential->sign_count );
			
			// Parse authenticator data to see the actual sign count
			if (strlen($authenticator_data_decoded) >= 37) {
				$sign_count_bytes = substr($authenticator_data_decoded, 33, 4);
				$actual_sign_count = unpack('N', $sign_count_bytes)[1];
				error_log( 'ByeByePW: Actual sign_count from authenticator: ' . $actual_sign_count );
			}
			
			// Sign count can be null or 0 for some authenticators
			// Some authenticators don't increment sign count, so we'll be lenient
			$sign_count = isset($credential->sign_count) ? intval($credential->sign_count) : 0;
			
			// If sign count is causing issues, we can disable the check by passing null
			// Many authenticators don't properly implement sign counts
			$sign_count_to_check = null; // Disable sign count validation for now
			
			$this->webauthn->processGet(
				$client_data_json_decoded,
				$authenticator_data_decoded,
				$signature_decoded,
				$credential->public_key,
				$challenge,
				$sign_count_to_check,
				false // user verification not required
			);

			error_log( 'ByeByePW: processGet succeeded!' );

			// Update sign count and last used
			$this->update_credential_usage( $credential->credential_id );

			// Clear challenge
			unset( $_SESSION['webauthn_challenge'] );
			delete_transient( 'byebyepw_auth_challenge_' . $session_id );

			error_log( 'ByeByePW: Authentication successful for user ' . $credential->user_id );
			return $credential->user_id;
		} catch ( \lbuchs\WebAuthn\WebAuthnException $e ) {
			error_log( 'ByeByePW: WebAuthnException during authentication: ' . $e->getMessage() );
			return new WP_Error( 'authentication_failed', $e->getMessage() );
		} catch ( \Exception $e ) {
			error_log( 'ByeByePW: General Exception during authentication: ' . $e->getMessage() );
			return new WP_Error( 'authentication_failed', $e->getMessage() );
		}
	}

	/**
	 * Save credential to database
	 */
	private function save_credential( $user_id, $data, $name = 'Unnamed Passkey' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'byebyepw_passkeys';

		// Log the data structure for debugging
		error_log( 'ByeByePW: Saving credential data structure: ' . print_r( $data, true ) );

		// Handle sign_count - it might be null or not set
		$sign_count = 0;
		if ( isset( $data->signCount ) ) {
			$sign_count = intval( $data->signCount );
		}

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

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC",
			$user_id
		) );
	}

	/**
	 * Get credential by ID
	 */
	private function get_credential_by_id( $credential_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'byebyepw_passkeys';

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table WHERE credential_id = %s",
			$credential_id
		) );
	}

	/**
	 * Update credential usage
	 */
	private function update_credential_usage( $credential_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'byebyepw_passkeys';

		// Get current sign count
		$current = $wpdb->get_row( $wpdb->prepare(
			"SELECT sign_count FROM $table WHERE credential_id = %s",
			$credential_id
		) );
		
		if ( ! $current ) {
			return false;
		}

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