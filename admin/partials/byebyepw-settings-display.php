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
?>

<div class="wrap byebyepw-wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    <?php settings_errors( 'byebyepw_settings' ); ?>

    <form method="post" action="options.php">
        <?php
        settings_fields( 'byebyepw_settings' );
        do_settings_sections( 'byebyepw-settings' );
        submit_button();
        ?>
    </form>
    
    <div class="card">
        <h2><?php esc_html_e( 'Important Security Notice', 'bye-bye-passwords' ); ?></h2>
        <p class="notice notice-warning">
            <?php esc_html_e( 'Before disabling password login, make sure all administrators have:', 'bye-bye-passwords' ); ?>
        </p>
        <ul class="byebyepw-notice-list">
            <li><?php esc_html_e( 'At least one passkey registered', 'bye-bye-passwords' ); ?></li>
            <li><?php esc_html_e( 'Recovery codes generated and saved in a secure location', 'bye-bye-passwords' ); ?></li>
            <li><?php esc_html_e( 'Tested passkey login successfully', 'bye-bye-passwords' ); ?></li>
        </ul>
        <?php
        $byebyepw_recovery_codes = new Byebyepw_Recovery_Codes();
        $byebyepw_admins = get_users( array( 'role' => 'administrator' ) );
        $byebyepw_admins_without_codes = array();
        foreach ( $byebyepw_admins as $byebyepw_admin ) {
            if ( ! $byebyepw_recovery_codes->has_recovery_codes( $byebyepw_admin->ID ) ) {
                $byebyepw_admins_without_codes[] = $byebyepw_admin;
            }
        }
        if ( ! empty( $byebyepw_admins_without_codes ) ) : ?>
            <div class="notice notice-error inline" style="margin-top: 10px;">
                <p>
                    <strong><?php esc_html_e( 'The following administrators have not yet generated recovery codes:', 'bye-bye-passwords' ); ?></strong>
                </p>
                <ul style="list-style: disc; margin-left: 20px;">
                    <?php foreach ( $byebyepw_admins_without_codes as $byebyepw_admin ) : ?>
                        <li><?php echo esc_html( $byebyepw_admin->display_name ); ?> (<?php echo esc_html( $byebyepw_admin->user_login ); ?>)</li>
                    <?php endforeach; ?>
                </ul>
                <p><?php esc_html_e( 'Password login cannot be disabled until all administrators have recovery codes.', 'bye-bye-passwords' ); ?></p>
            </div>
        <?php else : ?>
            <div class="notice notice-success inline" style="margin-top: 10px;">
                <p><?php esc_html_e( 'All administrators have generated recovery codes.', 'bye-bye-passwords' ); ?></p>
            </div>
        <?php endif; ?>
        <p>
            <strong><?php esc_html_e( 'Disabling password login without proper passkey setup will lock you out of your site!', 'bye-bye-passwords' ); ?></strong>
        </p>
    </div>
</div>