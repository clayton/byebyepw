<?php
/**
 * Admin area view for managing passkeys
 *
 * @link       https://claytonlz.com
 * @since      1.0.0
 *
 * @package    Byebyepw
 * @subpackage Byebyepw/admin/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Get current user
$byebyepw_current_user = wp_get_current_user();
$byebyepw_user_id = $byebyepw_current_user->ID;

// Get existing passkeys
global $wpdb;

// Check cache first for passkeys
$byebyepw_passkeys_cache_key = 'byebyepw_passkeys_' . $byebyepw_user_id;
$byebyepw_passkeys = wp_cache_get( $byebyepw_passkeys_cache_key );

if ( false === $byebyepw_passkeys ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query with caching
	$byebyepw_passkeys = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}byebyepw_passkeys WHERE user_id = %d ORDER BY created_at DESC",
		$byebyepw_user_id
	) );

	// Cache for 5 minutes
	wp_cache_set( $byebyepw_passkeys_cache_key, $byebyepw_passkeys, '', 300 );
}

// Check cache first for recovery codes count
$byebyepw_recovery_cache_key = 'byebyepw_recovery_codes_count_' . $byebyepw_user_id;
$byebyepw_has_recovery_codes = wp_cache_get( $byebyepw_recovery_cache_key );

if ( false === $byebyepw_has_recovery_codes ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query with caching
	$byebyepw_has_recovery_codes = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}byebyepw_recovery_codes WHERE user_id = %d AND used = 0",
		$byebyepw_user_id
	) );

	// Cache for 5 minutes
	wp_cache_set( $byebyepw_recovery_cache_key, $byebyepw_has_recovery_codes, '', 300 );
}
?>

<div class="wrap byebyepw-wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	
	<div class="byebyepw-admin-container">
		<!-- Passkeys Section -->
		<div class="card">
			<h2><?php esc_html_e( 'Your Passkeys', 'bye-bye-passwords' ); ?></h2>
			
			<?php if ( empty( $byebyepw_passkeys ) ) : ?>
				<p><?php esc_html_e( 'You have not registered any passkeys yet.', 'bye-bye-passwords' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'bye-bye-passwords' ); ?></th>
							<th><?php esc_html_e( 'Created', 'bye-bye-passwords' ); ?></th>
							<th><?php esc_html_e( 'Last Used', 'bye-bye-passwords' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'bye-bye-passwords' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $byebyepw_passkeys as $byebyepw_passkey ) : ?>
							<tr>
								<td><?php echo esc_html( $byebyepw_passkey->name ); ?></td>
								<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $byebyepw_passkey->created_at ) ) ); ?></td>
								<td>
									<?php
									if ( $byebyepw_passkey->last_used ) {
										echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $byebyepw_passkey->last_used ) ) );
									} else {
										esc_html_e( 'Never', 'bye-bye-passwords' );
									}
									?>
								</td>
								<td>
									<button class="button button-small byebyepw-delete-passkey" data-passkey-id="<?php echo esc_attr( $byebyepw_passkey->id ); ?>">
										<?php esc_html_e( 'Delete', 'bye-bye-passwords' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
			
			<p class="byebyepw-actions">
				<button id="byebyepw-register-passkey" class="button button-primary">
					<?php esc_html_e( 'Register New Passkey', 'bye-bye-passwords' ); ?>
				</button>
			</p>
		</div>
		
		<!-- Recovery Codes Section -->
		<div class="card">
			<h2><?php esc_html_e( 'Recovery Codes', 'bye-bye-passwords' ); ?></h2>
			
			<?php if ( $byebyepw_has_recovery_codes ) : ?>
				<p><?php esc_html_e( 'You have active recovery codes. Keep them safe!', 'bye-bye-passwords' ); ?></p>
				<p><?php
					// translators: %d is the number of remaining recovery codes
					echo sprintf( esc_html__( 'Active codes remaining: %d', 'bye-bye-passwords' ), intval( $byebyepw_has_recovery_codes ) ); ?></p>
			<?php else : ?>
				<p><?php esc_html_e( 'You have no active recovery codes.', 'bye-bye-passwords' ); ?></p>
			<?php endif; ?>
			
			<p class="byebyepw-actions">
				<button id="byebyepw-generate-recovery-codes" class="button">
					<?php esc_html_e( 'Generate New Recovery Codes', 'bye-bye-passwords' ); ?>
				</button>
			</p>
			
			<div class="notice notice-warning">
				<p><?php esc_html_e( 'Warning: Generating new recovery codes will invalidate all existing codes.', 'bye-bye-passwords' ); ?></p>
			</div>
		</div>
	</div>
	
	<!-- Registration Modal -->
	<div id="byebyepw-register-modal" class="byebyepw-modal">
		<div class="byebyepw-modal-content">
			<span class="byebyepw-modal-close">&times;</span>
			<h2><?php esc_html_e( 'Register New Passkey', 'bye-bye-passwords' ); ?></h2>
			
			<div class="byebyepw-modal-body">
				<label for="byebyepw-passkey-name"><?php esc_html_e( 'Passkey Name:', 'bye-bye-passwords' ); ?></label>
				<input type="text" id="byebyepw-passkey-name" placeholder="<?php esc_attr_e( 'e.g., MacBook TouchID', 'bye-bye-passwords' ); ?>" />
				
				<button id="byebyepw-start-registration" class="button button-primary">
					<?php esc_html_e( 'Start Registration', 'bye-bye-passwords' ); ?>
				</button>
				
				<div id="byebyepw-registration-status"></div>
			</div>
		</div>
	</div>
	
	<!-- Recovery Codes Modal -->
	<div id="byebyepw-recovery-modal" class="byebyepw-modal">
		<div class="byebyepw-modal-content">
			<span class="byebyepw-modal-close">&times;</span>
			<h2><?php esc_html_e( 'Your Recovery Codes', 'bye-bye-passwords' ); ?></h2>
			
			<div class="byebyepw-modal-body">
				<p><?php esc_html_e( 'Save these codes in a safe place. Each code can only be used once.', 'bye-bye-passwords' ); ?></p>
				
				<div id="byebyepw-recovery-codes-list" class="byebyepw-recovery-codes"></div>
				
				<button id="byebyepw-copy-codes" class="button">
					<?php esc_html_e( 'Copy to Clipboard', 'bye-bye-passwords' ); ?>
				</button>
				
				<button id="byebyepw-download-codes" class="button">
					<?php esc_html_e( 'Download as Text File', 'bye-bye-passwords' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>
