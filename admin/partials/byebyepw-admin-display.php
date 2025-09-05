<?php
/**
 * Admin area view for managing passkeys
 *
 * @link       https://labountylabs.com
 * @since      1.0.0
 *
 * @package    Byebyepw
 * @subpackage Byebyepw/admin/partials
 */

// Get current user
$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// Get existing passkeys
global $wpdb;
$table_name = $wpdb->prefix . 'byebyepw_passkeys';
$passkeys = $wpdb->get_results( $wpdb->prepare(
	"SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC",
	$user_id
) );

// Get recovery codes
$recovery_codes_table = $wpdb->prefix . 'byebyepw_recovery_codes';
$has_recovery_codes = $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM $recovery_codes_table WHERE user_id = %d AND used = 0",
	$user_id
) );
?>

<div class="wrap byebyepw-wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	
	<div class="byebyepw-admin-container">
		<!-- Passkeys Section -->
		<div class="card">
			<h2><?php esc_html_e( 'Your Passkeys', 'byebyepw' ); ?></h2>
			
			<?php if ( empty( $passkeys ) ) : ?>
				<p><?php esc_html_e( 'You have not registered any passkeys yet.', 'byebyepw' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'byebyepw' ); ?></th>
							<th><?php esc_html_e( 'Created', 'byebyepw' ); ?></th>
							<th><?php esc_html_e( 'Last Used', 'byebyepw' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'byebyepw' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $passkeys as $passkey ) : ?>
							<tr>
								<td><?php echo esc_html( $passkey->name ); ?></td>
								<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $passkey->created_at ) ) ); ?></td>
								<td>
									<?php 
									if ( $passkey->last_used ) {
										echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $passkey->last_used ) ) );
									} else {
										esc_html_e( 'Never', 'byebyepw' );
									}
									?>
								</td>
								<td>
									<button class="button button-small byebyepw-delete-passkey" data-passkey-id="<?php echo esc_attr( $passkey->id ); ?>">
										<?php esc_html_e( 'Delete', 'byebyepw' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
			
			<p class="byebyepw-actions">
				<button id="byebyepw-register-passkey" class="button button-primary">
					<?php esc_html_e( 'Register New Passkey', 'byebyepw' ); ?>
				</button>
			</p>
		</div>
		
		<!-- Recovery Codes Section -->
		<div class="card">
			<h2><?php esc_html_e( 'Recovery Codes', 'byebyepw' ); ?></h2>
			
			<?php if ( $has_recovery_codes ) : ?>
				<p><?php esc_html_e( 'You have active recovery codes. Keep them safe!', 'byebyepw' ); ?></p>
				<p><?php echo sprintf( esc_html__( 'Active codes remaining: %d', 'byebyepw' ), $has_recovery_codes ); ?></p>
			<?php else : ?>
				<p><?php esc_html_e( 'You have no active recovery codes.', 'byebyepw' ); ?></p>
			<?php endif; ?>
			
			<p class="byebyepw-actions">
				<button id="byebyepw-generate-recovery-codes" class="button">
					<?php esc_html_e( 'Generate New Recovery Codes', 'byebyepw' ); ?>
				</button>
			</p>
			
			<div class="notice notice-warning">
				<p><?php esc_html_e( 'Warning: Generating new recovery codes will invalidate all existing codes.', 'byebyepw' ); ?></p>
			</div>
		</div>
	</div>
	
	<!-- Registration Modal -->
	<div id="byebyepw-register-modal" class="byebyepw-modal">
		<div class="byebyepw-modal-content">
			<span class="byebyepw-modal-close">&times;</span>
			<h2><?php esc_html_e( 'Register New Passkey', 'byebyepw' ); ?></h2>
			
			<div class="byebyepw-modal-body">
				<label for="byebyepw-passkey-name"><?php esc_html_e( 'Passkey Name:', 'byebyepw' ); ?></label>
				<input type="text" id="byebyepw-passkey-name" placeholder="<?php esc_attr_e( 'e.g., MacBook TouchID', 'byebyepw' ); ?>" />
				
				<button id="byebyepw-start-registration" class="button button-primary">
					<?php esc_html_e( 'Start Registration', 'byebyepw' ); ?>
				</button>
				
				<div id="byebyepw-registration-status"></div>
			</div>
		</div>
	</div>
	
	<!-- Recovery Codes Modal -->
	<div id="byebyepw-recovery-modal" class="byebyepw-modal">
		<div class="byebyepw-modal-content">
			<span class="byebyepw-modal-close">&times;</span>
			<h2><?php esc_html_e( 'Your Recovery Codes', 'byebyepw' ); ?></h2>
			
			<div class="byebyepw-modal-body">
				<p><?php esc_html_e( 'Save these codes in a safe place. Each code can only be used once.', 'byebyepw' ); ?></p>
				
				<div id="byebyepw-recovery-codes-list" class="byebyepw-recovery-codes"></div>
				
				<button id="byebyepw-copy-codes" class="button">
					<?php esc_html_e( 'Copy to Clipboard', 'byebyepw' ); ?>
				</button>
				
				<button id="byebyepw-download-codes" class="button">
					<?php esc_html_e( 'Download as Text File', 'byebyepw' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>
