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

$byebyepw_has_passkeys = ! empty( $byebyepw_passkeys );
$byebyepw_has_tested_passkey = false;
foreach ( $byebyepw_passkeys as $byebyepw_passkey ) {
	if ( ! empty( $byebyepw_passkey->last_used ) ) {
		$byebyepw_has_tested_passkey = true;
		break;
	}
}

$byebyepw_setup_steps = array(
	array(
		'complete'    => $byebyepw_has_passkeys,
		'title'       => __( 'Add a passkey', 'bye-bye-passwords' ),
		'description' => $byebyepw_has_passkeys
			? sprintf(
				/* translators: %d: number of registered passkeys. */
				_n( '%d passkey registered', '%d passkeys registered', count( $byebyepw_passkeys ), 'bye-bye-passwords' ),
				count( $byebyepw_passkeys )
			)
			: __( 'Use your device, browser, or security key.', 'bye-bye-passwords' ),
	),
	array(
		'complete'    => (bool) $byebyepw_has_recovery_codes,
		'title'       => __( 'Save recovery codes', 'bye-bye-passwords' ),
		'description' => $byebyepw_has_recovery_codes
			? sprintf(
				/* translators: %d: number of unused recovery codes. */
				_n( '%d unused code remains', '%d unused codes remain', intval( $byebyepw_has_recovery_codes ), 'bye-bye-passwords' ),
				intval( $byebyepw_has_recovery_codes )
			)
			: __( 'Keep a copy somewhere outside WordPress.', 'bye-bye-passwords' ),
	),
	array(
		'complete'    => $byebyepw_has_tested_passkey,
		'title'       => __( 'Test passkey sign-in', 'bye-bye-passwords' ),
		'description' => $byebyepw_has_tested_passkey
			? __( 'A passkey sign-in has succeeded.', 'bye-bye-passwords' )
			: __( 'Sign out and use your passkey once before changing login settings.', 'bye-bye-passwords' ),
	),
);

$byebyepw_current_step = 0;
foreach ( $byebyepw_setup_steps as $byebyepw_step_index => $byebyepw_setup_step ) {
	if ( ! $byebyepw_setup_step['complete'] ) {
		$byebyepw_current_step = $byebyepw_step_index;
		break;
	}
}
?>

<div class="wrap byebyepw-wrap">
	<div class="byebyepw-page-header">
		<div>
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p><?php esc_html_e( 'Set up a phishing-resistant way to sign in, with recovery codes as your safety net.', 'bye-bye-passwords' ); ?></p>
		</div>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=byebyepw-settings' ) ); ?>">
			<?php esc_html_e( 'Login settings', 'bye-bye-passwords' ); ?>
		</a>
	</div>

	<section class="byebyepw-setup" aria-labelledby="byebyepw-setup-title">
		<div class="byebyepw-setup-heading">
			<div>
				<p class="byebyepw-eyebrow"><?php esc_html_e( 'Safe setup', 'bye-bye-passwords' ); ?></p>
				<h2 id="byebyepw-setup-title"><?php esc_html_e( 'Your sign-in checklist', 'bye-bye-passwords' ); ?></h2>
			</div>
			<span class="byebyepw-setup-count">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: completed step count, 2: total step count. */
						__( '%1$d of %2$d complete', 'bye-bye-passwords' ),
						count( array_filter( wp_list_pluck( $byebyepw_setup_steps, 'complete' ) ) ),
						count( $byebyepw_setup_steps )
					)
				);
				?>
			</span>
		</div>
		<ol class="byebyepw-setup-rail">
			<?php foreach ( $byebyepw_setup_steps as $byebyepw_step_index => $byebyepw_setup_step ) : ?>
				<li class="<?php echo $byebyepw_setup_step['complete'] ? 'is-complete' : ( $byebyepw_step_index === $byebyepw_current_step ? 'is-current' : '' ); ?>" <?php echo $byebyepw_step_index === $byebyepw_current_step && ! $byebyepw_setup_step['complete'] ? 'aria-current="step"' : ''; ?>>
					<span class="byebyepw-step-marker" aria-hidden="true">
						<?php echo $byebyepw_setup_step['complete'] ? '&#10003;' : esc_html( $byebyepw_step_index + 1 ); ?>
					</span>
					<span class="byebyepw-step-copy">
						<strong><?php echo esc_html( $byebyepw_setup_step['title'] ); ?></strong>
						<span><?php echo esc_html( $byebyepw_setup_step['description'] ); ?></span>
					</span>
				</li>
			<?php endforeach; ?>
		</ol>
	</section>

	<div class="byebyepw-admin-container">
		<!-- Passkeys Section -->
		<section class="byebyepw-panel" aria-labelledby="byebyepw-passkeys-title">
			<div class="byebyepw-panel-heading">
				<div>
					<span class="dashicons dashicons-shield-alt" aria-hidden="true"></span>
					<div>
						<h2 id="byebyepw-passkeys-title"><?php esc_html_e( 'Passkeys', 'bye-bye-passwords' ); ?></h2>
						<p><?php esc_html_e( 'Sign in with your fingerprint, face, device PIN, or security key.', 'bye-bye-passwords' ); ?></p>
					</div>
				</div>
				<?php if ( $byebyepw_has_passkeys ) : ?>
					<span class="byebyepw-status is-ready"><?php esc_html_e( 'Ready', 'bye-bye-passwords' ); ?></span>
				<?php else : ?>
					<span class="byebyepw-status is-needed"><?php esc_html_e( 'Setup needed', 'bye-bye-passwords' ); ?></span>
				<?php endif; ?>
			</div>
			<div id="byebyepw-passkey-status" class="byebyepw-live-status" role="status" aria-live="polite"></div>
			
			<?php if ( empty( $byebyepw_passkeys ) ) : ?>
				<div class="byebyepw-empty-state">
					<strong><?php esc_html_e( 'No passkeys yet', 'bye-bye-passwords' ); ?></strong>
					<p><?php esc_html_e( 'Add the device you are using now. You can add another device later.', 'bye-bye-passwords' ); ?></p>
				</div>
			<?php else : ?>
				<div class="byebyepw-table-wrap">
				<table class="wp-list-table widefat fixed">
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
									<button class="button button-link-delete byebyepw-delete-passkey" data-passkey-id="<?php echo esc_attr( $byebyepw_passkey->id ); ?>">
										<?php esc_html_e( 'Remove', 'bye-bye-passwords' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				</div>
			<?php endif; ?>
			
			<p class="byebyepw-actions">
				<button id="byebyepw-register-passkey" class="button <?php echo $byebyepw_has_passkeys ? '' : 'button-primary'; ?>">
					<?php echo $byebyepw_has_passkeys ? esc_html__( 'Add another passkey', 'bye-bye-passwords' ) : esc_html__( 'Add a passkey', 'bye-bye-passwords' ); ?>
				</button>
			</p>
		</section>
		
		<!-- Recovery Codes Section -->
		<section class="byebyepw-panel" aria-labelledby="byebyepw-recovery-title">
			<div class="byebyepw-panel-heading">
				<div>
					<span class="dashicons dashicons-media-text" aria-hidden="true"></span>
					<div>
						<h2 id="byebyepw-recovery-title"><?php esc_html_e( 'Recovery codes', 'bye-bye-passwords' ); ?></h2>
						<p><?php esc_html_e( 'Use a one-time code if your passkey is unavailable.', 'bye-bye-passwords' ); ?></p>
					</div>
				</div>
				<?php if ( $byebyepw_has_recovery_codes ) : ?>
					<span class="byebyepw-status is-ready"><?php esc_html_e( 'Saved', 'bye-bye-passwords' ); ?></span>
				<?php else : ?>
					<span class="byebyepw-status is-needed"><?php esc_html_e( 'Setup needed', 'bye-bye-passwords' ); ?></span>
				<?php endif; ?>
			</div>
			
			<?php if ( $byebyepw_has_recovery_codes ) : ?>
				<div class="byebyepw-code-count">
					<strong><?php echo esc_html( intval( $byebyepw_has_recovery_codes ) ); ?></strong>
					<span><?php esc_html_e( 'unused codes remain', 'bye-bye-passwords' ); ?></span>
				</div>
				<p class="description"><?php esc_html_e( 'For security, saved codes cannot be shown again. Replace them if you lost your copy.', 'bye-bye-passwords' ); ?></p>
			<?php else : ?>
				<div class="byebyepw-empty-state">
					<strong><?php esc_html_e( 'No recovery codes saved', 'bye-bye-passwords' ); ?></strong>
					<p><?php esc_html_e( 'Generate a set, then download or copy it before closing the window.', 'bye-bye-passwords' ); ?></p>
				</div>
			<?php endif; ?>
			
			<p class="byebyepw-actions">
				<button id="byebyepw-generate-recovery-codes" class="button <?php echo $byebyepw_has_recovery_codes ? '' : 'button-primary'; ?>" data-has-codes="<?php echo $byebyepw_has_recovery_codes ? '1' : '0'; ?>">
					<?php echo $byebyepw_has_recovery_codes ? esc_html__( 'Replace recovery codes', 'bye-bye-passwords' ) : esc_html__( 'Generate recovery codes', 'bye-bye-passwords' ); ?>
				</button>
			</p>
			<div id="byebyepw-recovery-generation-status" class="byebyepw-live-status" role="status" aria-live="polite"></div>
			
			<?php if ( $byebyepw_has_recovery_codes ) : ?>
				<p class="byebyepw-inline-warning"><span class="dashicons dashicons-warning" aria-hidden="true"></span><?php esc_html_e( 'Replacing codes immediately invalidates the current set.', 'bye-bye-passwords' ); ?></p>
			<?php endif; ?>
		</section>
	</div>
	
	<!-- Registration Modal -->
	<div id="byebyepw-register-modal" class="byebyepw-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="byebyepw-register-title">
		<div class="byebyepw-modal-content" role="document">
			<button type="button" class="byebyepw-modal-close" aria-label="<?php esc_attr_e( 'Close', 'bye-bye-passwords' ); ?>">&times;</button>
			<p class="byebyepw-eyebrow"><?php esc_html_e( 'Step 1 of 3', 'bye-bye-passwords' ); ?></p>
			<h2 id="byebyepw-register-title"><?php esc_html_e( 'Add a passkey', 'bye-bye-passwords' ); ?></h2>
			<p class="byebyepw-modal-intro"><?php esc_html_e( 'Choose a name that identifies this device. Your browser will handle the secure setup.', 'bye-bye-passwords' ); ?></p>
			
			<div class="byebyepw-modal-body">
				<label for="byebyepw-passkey-name"><?php esc_html_e( 'Passkey name', 'bye-bye-passwords' ); ?></label>
				<input type="text" id="byebyepw-passkey-name" class="regular-text" autocomplete="off" placeholder="<?php esc_attr_e( 'For example, MacBook Touch ID', 'bye-bye-passwords' ); ?>" />
				<p class="description"><?php esc_html_e( 'Only you and site administrators can see this name.', 'bye-bye-passwords' ); ?></p>
				
				<div id="byebyepw-registration-status" class="byebyepw-live-status" role="status" aria-live="polite"></div>
				<div class="byebyepw-modal-actions">
					<button id="byebyepw-start-registration" class="button button-primary">
						<?php esc_html_e( 'Continue with browser', 'bye-bye-passwords' ); ?>
					</button>
					<button type="button" class="button byebyepw-modal-close"><?php esc_html_e( 'Cancel', 'bye-bye-passwords' ); ?></button>
				</div>
			</div>
		</div>
	</div>
	
	<!-- Recovery Codes Modal -->
	<div id="byebyepw-recovery-modal" class="byebyepw-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="byebyepw-recovery-modal-title">
		<div class="byebyepw-modal-content is-wide" role="document">
			<button type="button" class="byebyepw-modal-close" aria-label="<?php esc_attr_e( 'Close', 'bye-bye-passwords' ); ?>">&times;</button>
			<p class="byebyepw-eyebrow"><?php esc_html_e( 'Save now', 'bye-bye-passwords' ); ?></p>
			<h2 id="byebyepw-recovery-modal-title"><?php esc_html_e( 'Your recovery codes', 'bye-bye-passwords' ); ?></h2>
			
			<div class="byebyepw-modal-body">
				<p class="byebyepw-modal-intro"><?php esc_html_e( 'This is the only time WordPress will show this set. Each code works once.', 'bye-bye-passwords' ); ?></p>
				
				<div id="byebyepw-recovery-codes-list" class="byebyepw-recovery-codes"></div>
				<div id="byebyepw-recovery-action-status" class="byebyepw-live-status" role="status" aria-live="polite"></div>
				<div class="byebyepw-modal-actions">
					<button id="byebyepw-download-codes" class="button button-primary">
						<?php esc_html_e( 'Download codes', 'bye-bye-passwords' ); ?>
					</button>
					<button id="byebyepw-copy-codes" class="button">
						<?php esc_html_e( 'Copy codes', 'bye-bye-passwords' ); ?>
					</button>
					<button type="button" class="button byebyepw-modal-close"><?php esc_html_e( 'Done', 'bye-bye-passwords' ); ?></button>
				</div>
			</div>
		</div>
	</div>
</div>
