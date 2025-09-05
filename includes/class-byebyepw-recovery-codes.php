<?php

/**
 * Recovery codes functionality
 *
 * @link       https://labountylabs.com
 * @since      1.0.0
 *
 * @package    Byebyepw
 * @subpackage Byebyepw/includes
 */

/**
 * Handles recovery codes for emergency access.
 *
 * @since      1.0.0
 * @package    Byebyepw
 * @subpackage Byebyepw/includes
 * @author     Clayton <clayton@labountylabs.com>
 */
class Byebyepw_Recovery_Codes {

	/**
	 * Generate recovery codes for a user
	 *
	 * @param int $user_id User ID
	 * @return array Array of recovery codes
	 */
	public function generate_recovery_codes( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'byebyepw_recovery_codes';

		// Delete existing codes
		$wpdb->delete( $table, array( 'user_id' => $user_id ) );

		$codes = array();
		
		// Generate 10 recovery codes
		for ( $i = 0; $i < 10; $i++ ) {
			$code = $this->generate_code();
			$codes[] = $code;
			
			// Store hashed version in database
			$wpdb->insert(
				$table,
				array(
					'user_id' => $user_id,
					'code_hash' => wp_hash_password( $code ),
					'used' => 0,
				)
			);
		}

		return $codes;
	}

	/**
	 * Generate a single recovery code
	 *
	 * @return string Recovery code
	 */
	private function generate_code() {
		// Generate a random code in format: XXXX-XXXX-XXXX
		$segments = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$segments[] = strtoupper( substr( bin2hex( random_bytes( 2 ) ), 0, 4 ) );
		}
		return implode( '-', $segments );
	}

	/**
	 * Verify a recovery code
	 *
	 * @param int    $user_id User ID
	 * @param string $code    Recovery code to verify
	 * @return bool True if valid and unused, false otherwise
	 */
	public function verify_recovery_code( $user_id, $code ) {
		global $wpdb;
		$table = $wpdb->prefix . 'byebyepw_recovery_codes';

		// Get unused codes for this user
		$stored_codes = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, code_hash FROM $table WHERE user_id = %d AND used = 0",
			$user_id
		) );

		foreach ( $stored_codes as $stored ) {
			if ( wp_check_password( $code, $stored->code_hash ) ) {
				// Mark code as used
				$wpdb->update(
					$table,
					array(
						'used' => 1,
						'used_at' => current_time( 'mysql' ),
					),
					array( 'id' => $stored->id )
				);
				return true;
			}
		}

		return false;
	}

	/**
	 * Get count of remaining recovery codes
	 *
	 * @param int $user_id User ID
	 * @return int Number of unused recovery codes
	 */
	public function get_remaining_codes_count( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'byebyepw_recovery_codes';

		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $table WHERE user_id = %d AND used = 0",
			$user_id
		) );
	}

	/**
	 * Check if user has recovery codes
	 *
	 * @param int $user_id User ID
	 * @return bool True if user has any recovery codes
	 */
	public function has_recovery_codes( $user_id ) {
		return $this->get_remaining_codes_count( $user_id ) > 0;
	}

	/**
	 * Format recovery codes for display
	 *
	 * @param array $codes Array of recovery codes
	 * @return string HTML formatted codes
	 */
	public function format_codes_for_display( $codes ) {
		$html = '<div class="byebyepw-recovery-codes">';
		$html .= '<p><strong>' . __( 'Save these recovery codes in a safe place:', 'byebyepw' ) . '</strong></p>';
		$html .= '<ul class="recovery-codes-list">';
		
		foreach ( $codes as $index => $code ) {
			$html .= '<li><code>' . esc_html( $code ) . '</code></li>';
		}
		
		$html .= '</ul>';
		$html .= '<p class="description">' . __( 'Each code can only be used once. Store them securely.', 'byebyepw' ) . '</p>';
		$html .= '</div>';
		
		return $html;
	}
}