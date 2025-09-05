<?php
/**
 * Debug tools admin page
 *
 * @link       https://labountylabs.com
 * @since      1.0.0
 *
 * @package    Byebyepw
 * @subpackage Byebyepw/admin/partials
 */

// Process actions
$action = $_GET['action'] ?? '';
$message = '';
$message_type = '';

if ( $action === 'clean_user' && isset( $_GET['user_id'] ) && check_admin_referer( 'byebyepw_clean_user' ) ) {
	$user_id = intval( $_GET['user_id'] );
	
	// Only allow cleaning own passkeys or if user is super admin
	if ( $user_id == get_current_user_id() || is_super_admin() ) {
		global $wpdb;
		$passkeys_table = $wpdb->prefix . 'byebyepw_passkeys';
		
		// Delete passkeys
		$deleted = $wpdb->delete( $passkeys_table, array( 'user_id' => $user_id ) );
		
		// Clear transients (use new naming scheme)
		delete_transient( 'byebyepw_reg_challenge_' . $user_id );
		delete_transient( 'byebyepw_challenge_' . $user_id ); // Old key for backwards compatibility
		if ( session_id() ) {
			delete_transient( 'byebyepw_auth_challenge_' . session_id() );
			unset( $_SESSION['webauthn_challenge'] );
		}
		
		$message = sprintf( __( 'Cleaned %d passkey(s) and cleared challenges for user ID %d', 'byebyepw' ), $deleted, $user_id );
		$message_type = 'success';
	} else {
		$message = __( 'You do not have permission to clean this user\'s passkeys.', 'byebyepw' );
		$message_type = 'error';
	}
}

// Get data
global $wpdb;
$passkeys_table = $wpdb->prefix . 'byebyepw_passkeys';
$recovery_table = $wpdb->prefix . 'byebyepw_recovery_codes';

// Check if tables exist
$passkeys_exists = $wpdb->get_var( "SHOW TABLES LIKE '$passkeys_table'" ) === $passkeys_table;
$recovery_exists = $wpdb->get_var( "SHOW TABLES LIKE '$recovery_table'" ) === $recovery_table;

// Get all passkeys
$passkeys = $passkeys_exists ? $wpdb->get_results( "SELECT * FROM $passkeys_table ORDER BY user_id, created_at DESC" ) : [];

// Start session if needed for debug info
if ( ! session_id() ) {
	@session_start();
}

?>

<div class="wrap byebyepw-wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	
	<?php if ( $message ) : ?>
		<div class="notice notice-<?php echo esc_attr( $message_type ); ?> is-dismissible">
			<p><?php echo esc_html( $message ); ?></p>
		</div>
	<?php endif; ?>
	
	<!-- Database Status -->
	<div class="card">
		<h2><?php esc_html_e( 'Database Status', 'byebyepw' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Passkeys Table', 'byebyepw' ); ?></th>
				<td>
					<?php if ( $passkeys_exists ) : ?>
						<span class="dashicons dashicons-yes-alt" style="color: green;"></span>
						<?php esc_html_e( 'Exists', 'byebyepw' ); ?>
						(<code><?php echo esc_html( $passkeys_table ); ?></code>)
					<?php else : ?>
						<span class="dashicons dashicons-dismiss" style="color: red;"></span>
						<?php esc_html_e( 'Missing', 'byebyepw' ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Recovery Codes Table', 'byebyepw' ); ?></th>
				<td>
					<?php if ( $recovery_exists ) : ?>
						<span class="dashicons dashicons-yes-alt" style="color: green;"></span>
						<?php esc_html_e( 'Exists', 'byebyepw' ); ?>
						(<code><?php echo esc_html( $recovery_table ); ?></code>)
					<?php else : ?>
						<span class="dashicons dashicons-dismiss" style="color: red;"></span>
						<?php esc_html_e( 'Missing', 'byebyepw' ); ?>
					<?php endif; ?>
				</td>
			</tr>
		</table>
	</div>
	
	<!-- Session Information -->
	<div class="card">
		<h2><?php esc_html_e( 'Session & Challenge Status', 'byebyepw' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Session ID', 'byebyepw' ); ?></th>
				<td><code><?php echo esc_html( session_id() ?: 'No session' ); ?></code></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Session Status', 'byebyepw' ); ?></th>
				<td>
					<?php 
					$status = session_status();
					switch( $status ) {
						case PHP_SESSION_DISABLED:
							echo '<span style="color: red;">Disabled</span>';
							break;
						case PHP_SESSION_NONE:
							echo '<span style="color: orange;">None</span>';
							break;
						case PHP_SESSION_ACTIVE:
							echo '<span style="color: green;">Active</span>';
							break;
					}
					?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Session Challenge', 'byebyepw' ); ?></th>
				<td>
					<?php if ( isset( $_SESSION['webauthn_challenge'] ) ) : ?>
						<span class="dashicons dashicons-yes-alt" style="color: green;"></span>
						<?php esc_html_e( 'Present', 'byebyepw' ); ?>
						(<?php echo strlen( $_SESSION['webauthn_challenge'] ); ?> bytes)
					<?php else : ?>
						<span class="dashicons dashicons-dismiss" style="color: orange;"></span>
						<?php esc_html_e( 'Not set', 'byebyepw' ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Registration Transient', 'byebyepw' ); ?></th>
				<td>
					<?php 
					$reg_challenge = get_transient( 'byebyepw_reg_challenge_' . get_current_user_id() );
					if ( $reg_challenge ) : ?>
						<span class="dashicons dashicons-yes-alt" style="color: green;"></span>
						<?php esc_html_e( 'Present', 'byebyepw' ); ?>
						(<?php echo strlen( $reg_challenge ); ?> bytes base64)
						<br><small>Key: byebyepw_reg_challenge_<?php echo get_current_user_id(); ?></small>
					<?php else : ?>
						<span class="dashicons dashicons-dismiss" style="color: orange;"></span>
						<?php esc_html_e( 'Not set', 'byebyepw' ); ?>
						<br><small>Key: byebyepw_reg_challenge_<?php echo get_current_user_id(); ?></small>
					<?php endif; ?>
				</td>
			</tr>
		</table>
	</div>
	
	<!-- All Passkeys -->
	<div class="card">
		<h2><?php esc_html_e( 'All Registered Passkeys', 'byebyepw' ); ?></h2>
		
		<?php if ( ! empty( $passkeys ) ) : ?>
			<style>
				.byebyepw-passkeys-table {
					table-layout: auto !important;
				}
				.byebyepw-passkeys-table th,
				.byebyepw-passkeys-table td {
					padding: 8px 12px !important;
					white-space: nowrap;
				}
				.byebyepw-passkeys-table .col-id { width: 5%; }
				.byebyepw-passkeys-table .col-user { width: 15%; }
				.byebyepw-passkeys-table .col-name { width: 15%; }
				.byebyepw-passkeys-table .col-credential { width: 25%; }
				.byebyepw-passkeys-table .col-created { width: 15%; }
				.byebyepw-passkeys-table .col-lastused { width: 15%; }
				.byebyepw-passkeys-table .col-actions { width: 10%; }
			</style>
			<table class="wp-list-table widefat fixed striped byebyepw-passkeys-table">
				<thead>
					<tr>
						<th class="col-id"><?php esc_html_e( 'ID', 'byebyepw' ); ?></th>
						<th class="col-user"><?php esc_html_e( 'User', 'byebyepw' ); ?></th>
						<th class="col-name"><?php esc_html_e( 'Name', 'byebyepw' ); ?></th>
						<th class="col-credential"><?php esc_html_e( 'Credential ID', 'byebyepw' ); ?></th>
						<th class="col-created"><?php esc_html_e( 'Created', 'byebyepw' ); ?></th>
						<th class="col-lastused"><?php esc_html_e( 'Last Used', 'byebyepw' ); ?></th>
						<th class="col-actions"><?php esc_html_e( 'Actions', 'byebyepw' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $passkeys as $passkey ) : 
						$user = get_user_by( 'id', $passkey->user_id );
					?>
						<tr>
							<td><?php echo esc_html( $passkey->id ); ?></td>
							<td>
								<?php if ( $user ) : ?>
									<?php echo esc_html( $user->user_login ); ?>
									(ID: <?php echo esc_html( $passkey->user_id ); ?>)
								<?php else : ?>
									<em><?php esc_html_e( 'Deleted User', 'byebyepw' ); ?></em>
									(ID: <?php echo esc_html( $passkey->user_id ); ?>)
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $passkey->name ); ?></td>
							<td>
								<code title="<?php echo esc_attr( $passkey->credential_id ); ?>">
									<?php echo esc_html( substr( $passkey->credential_id, 0, 20 ) . '...' ); ?>
								</code>
							</td>
							<td><?php echo esc_html( $passkey->created_at ); ?></td>
							<td><?php echo esc_html( $passkey->last_used ?: __( 'Never', 'byebyepw' ) ); ?></td>
							<td>
								<?php if ( $passkey->user_id == get_current_user_id() || is_super_admin() ) : ?>
									<a href="<?php echo wp_nonce_url( 
										add_query_arg( array(
											'page' => 'byebyepw-debug',
											'action' => 'clean_user',
											'user_id' => $passkey->user_id
										), admin_url( 'admin.php' ) ),
										'byebyepw_clean_user'
									); ?>" 
									class="button button-small"
									onclick="return confirm('<?php esc_attr_e( 'Delete all passkeys for this user?', 'byebyepw' ); ?>');">
										<?php esc_html_e( 'Clean User', 'byebyepw' ); ?>
									</a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No passkeys registered.', 'byebyepw' ); ?></p>
		<?php endif; ?>
		
		<?php if ( ! empty( $passkeys ) ) : ?>
			<p class="description">
				<?php esc_html_e( 'Total passkeys:', 'byebyepw' ); ?> 
				<strong><?php echo count( $passkeys ); ?></strong>
			</p>
		<?php endif; ?>
	</div>
	
	<!-- Quick Actions -->
	<div class="card">
		<h2><?php esc_html_e( 'Quick Actions', 'byebyepw' ); ?></h2>
		
		<p>
			<a href="<?php echo wp_nonce_url( 
				add_query_arg( array(
					'page' => 'byebyepw-debug',
					'action' => 'clean_user',
					'user_id' => get_current_user_id()
				), admin_url( 'admin.php' ) ),
				'byebyepw_clean_user'
			); ?>" 
			class="button button-primary"
			onclick="return confirm('<?php esc_attr_e( 'This will delete all your passkeys and clear all challenges. Continue?', 'byebyepw' ); ?>');">
				<?php esc_html_e( 'Clean My Passkeys & Challenges', 'byebyepw' ); ?>
			</a>
		</p>
		
		<p class="description">
			<?php esc_html_e( 'This will remove all your passkeys from the database and clear any stored challenges. Use this if you\'re having issues with registration.', 'byebyepw' ); ?>
		</p>
	</div>
	
	<!-- Debug Logs -->
	<div class="card">
		<h2><?php esc_html_e( 'Recent Debug Logs', 'byebyepw' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Check your log files for entries starting with "ByeByePW:" to troubleshoot issues.', 'byebyepw' ); ?>
		</p>
		
		<?php
		// Try multiple log locations
		$possible_logs = array(
			WP_CONTENT_DIR . '/debug.log',
			dirname( ABSPATH, 2 ) . '/logs/php/error.log', // Local by Flywheel location
			ini_get( 'error_log' ), // PHP configured error log
		);
		
		$log_found = false;
		$log_path = '';
		
		foreach ( $possible_logs as $log ) {
			if ( $log && file_exists( $log ) && is_readable( $log ) ) {
				$log_path = $log;
				$log_found = true;
				break;
			}
		}
		
		if ( $log_found ) {
			echo '<p><small>' . esc_html__( 'Reading from:', 'byebyepw' ) . ' <code>' . esc_html( $log_path ) . '</code></small></p>';
			
			// Read last 1000 lines to find ByeByePW entries
			$logs = array();
			$handle = fopen( $log_path, 'r' );
			if ( $handle ) {
				// Get file size and read last portion
				$file_size = filesize( $log_path );
				$read_size = min( $file_size, 50000 ); // Read last 50KB max
				fseek( $handle, -$read_size, SEEK_END );
				$content = fread( $handle, $read_size );
				fclose( $handle );
				
				$lines = explode( "\n", $content );
				$byebyepw_logs = array_filter( $lines, function( $line ) {
					return strpos( $line, 'ByeByePW:' ) !== false;
				});
				
				// Get last 20 logs
				$recent_logs = array_slice( $byebyepw_logs, -20 );
				
				if ( ! empty( $recent_logs ) ) {
					echo '<pre style="background: #f1f1f1; padding: 10px; overflow-x: auto; max-height: 400px; font-size: 12px;">';
					foreach ( $recent_logs as $log ) {
						// Highlight important parts
						$log = str_replace( 'ByeByePW:', '<strong style="color: #0073aa;">ByeByePW:</strong>', $log );
						$log = str_replace( 'ERROR', '<span style="color: red;">ERROR</span>', $log );
						$log = str_replace( 'SUCCESS', '<span style="color: green;">SUCCESS</span>', $log );
						echo $log . "\n";
					}
					echo '</pre>';
					echo '<p class="description">' . sprintf( __( 'Showing last %d ByeByePW log entries', 'byebyepw' ), count( $recent_logs ) ) . '</p>';
				} else {
					echo '<p>' . esc_html__( 'No ByeByePW debug logs found in the log file.', 'byebyepw' ) . '</p>';
					echo '<p class="description">' . esc_html__( 'Try triggering an action (like registering a passkey) and refresh this page.', 'byebyepw' ) . '</p>';
				}
			} else {
				echo '<p>' . esc_html__( 'Could not open log file for reading.', 'byebyepw' ) . '</p>';
			}
		} else {
			echo '<p>' . esc_html__( 'No accessible log file found. Checked:', 'byebyepw' ) . '</p>';
			echo '<ul>';
			foreach ( $possible_logs as $log ) {
				if ( $log ) {
					echo '<li><code>' . esc_html( $log ) . '</code></li>';
				}
			}
			echo '</ul>';
			echo '<p class="description">' . esc_html__( 'Enable WP_DEBUG and WP_DEBUG_LOG in wp-config.php, or check your PHP error_log setting.', 'byebyepw' ) . '</p>';
		}
		?>
		
		<p style="margin-top: 20px;">
			<a href="<?php echo esc_url( add_query_arg( 'refresh_logs', '1' ) ); ?>" class="button">
				<?php esc_html_e( 'Refresh Logs', 'byebyepw' ); ?>
			</a>
		</p>
	</div>
</div>