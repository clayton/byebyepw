<?php
/**
 * Settings page display
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

$byebyepw_statuses = $this->get_admin_security_statuses();
$byebyepw_all_ready = $this->are_all_admins_ready( $byebyepw_statuses );
$byebyepw_options = get_option( 'byebyepw_settings', array() );
$byebyepw_password_configured = ! empty( $byebyepw_options['password_login_disabled'] );
$byebyepw_password_disabled = $byebyepw_password_configured && $byebyepw_all_ready;
?>

<div class="wrap byebyepw-wrap">
	<div class="byebyepw-page-header">
		<div>
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p><?php esc_html_e( 'Choose when this site should stop accepting WordPress passwords.', 'bye-bye-passwords' ); ?></p>
		</div>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=byebyepw' ) ); ?>">
			<?php esc_html_e( 'Manage passkeys', 'bye-bye-passwords' ); ?>
		</a>
	</div>

	<?php settings_errors( 'byebyepw_settings' ); ?>

	<div class="byebyepw-settings-layout">
		<main>
			<form class="byebyepw-panel byebyepw-settings-form" method="post" action="options.php">
				<?php settings_fields( 'byebyepw_settings' ); ?>
				<div class="byebyepw-panel-heading">
					<div>
						<span class="dashicons dashicons-lock" aria-hidden="true"></span>
						<div>
							<h2><?php esc_html_e( 'Password sign-in', 'bye-bye-passwords' ); ?></h2>
							<p><?php esc_html_e( 'Keep passwords available until every administrator has a tested backup path.', 'bye-bye-passwords' ); ?></p>
						</div>
					</div>
					<span class="byebyepw-status <?php echo $byebyepw_password_disabled ? 'is-ready' : 'is-neutral'; ?>">
						<?php
						if ( $byebyepw_password_disabled ) {
							esc_html_e( 'Passwords off', 'bye-bye-passwords' );
						} elseif ( $byebyepw_password_configured ) {
							esc_html_e( 'Passwords temporarily on', 'bye-bye-passwords' );
						} else {
							esc_html_e( 'Passwords on', 'bye-bye-passwords' );
						}
						?>
					</span>
				</div>

				<div class="byebyepw-setting-box <?php echo $byebyepw_all_ready ? 'is-unlocked' : 'is-locked'; ?>">
					<?php $this->render_password_login_field(); ?>
				</div>

				<?php submit_button( __( 'Save login settings', 'bye-bye-passwords' ), 'primary', 'submit', false ); ?>
			</form>
		</main>

		<aside class="byebyepw-panel byebyepw-readiness" aria-labelledby="byebyepw-readiness-title">
			<div class="byebyepw-panel-heading">
				<div>
					<span class="dashicons dashicons-groups" aria-hidden="true"></span>
					<div>
						<h2 id="byebyepw-readiness-title"><?php esc_html_e( 'Administrator readiness', 'bye-bye-passwords' ); ?></h2>
						<p><?php esc_html_e( 'Every administrator must complete all three checks.', 'bye-bye-passwords' ); ?></p>
					</div>
				</div>
			</div>

			<div class="byebyepw-admin-readiness-list">
				<?php foreach ( $byebyepw_statuses as $byebyepw_status ) : ?>
					<section class="byebyepw-admin-readiness <?php echo $byebyepw_status['ready'] ? 'is-ready' : ''; ?>">
						<div class="byebyepw-admin-identity">
							<span class="dashicons dashicons-admin-users" aria-hidden="true"></span>
							<div>
								<strong><?php echo esc_html( $byebyepw_status['user']->display_name ); ?></strong>
								<span><?php echo esc_html( $byebyepw_status['user']->user_login ); ?></span>
							</div>
						</div>
						<ul class="byebyepw-check-list">
							<li class="<?php echo $byebyepw_status['has_passkey'] ? 'is-complete' : ''; ?>"><span aria-hidden="true"><?php echo $byebyepw_status['has_passkey'] ? '&#10003;' : '&ndash;'; ?></span><?php esc_html_e( 'Passkey added', 'bye-bye-passwords' ); ?></li>
							<li class="<?php echo $byebyepw_status['has_recovery_codes'] ? 'is-complete' : ''; ?>"><span aria-hidden="true"><?php echo $byebyepw_status['has_recovery_codes'] ? '&#10003;' : '&ndash;'; ?></span><?php esc_html_e( 'Recovery codes saved', 'bye-bye-passwords' ); ?></li>
							<li class="<?php echo $byebyepw_status['has_tested_passkey'] ? 'is-complete' : ''; ?>"><span aria-hidden="true"><?php echo $byebyepw_status['has_tested_passkey'] ? '&#10003;' : '&ndash;'; ?></span><?php esc_html_e( 'Passkey sign-in tested', 'bye-bye-passwords' ); ?></li>
						</ul>
						<?php if ( get_current_user_id() === $byebyepw_status['user']->ID && ! $byebyepw_status['ready'] ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=byebyepw' ) ); ?>"><?php esc_html_e( 'Finish your setup', 'bye-bye-passwords' ); ?></a>
						<?php endif; ?>
					</section>
				<?php endforeach; ?>
			</div>
		</aside>
	</div>
</div>
