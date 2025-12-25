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
            <?php esc_html_e( 'Before disabling password login, make sure you have:', 'bye-bye-passwords' ); ?>
        </p>
        <ul class="byebyepw-notice-list">
            <li><?php esc_html_e( 'At least one passkey registered', 'bye-bye-passwords' ); ?></li>
            <li><?php esc_html_e( 'Recovery codes generated and saved in a secure location', 'bye-bye-passwords' ); ?></li>
            <li><?php esc_html_e( 'Tested passkey login successfully', 'bye-bye-passwords' ); ?></li>
        </ul>
        <p>
            <strong><?php esc_html_e( 'Disabling password login without proper passkey setup will lock you out of your site!', 'bye-bye-passwords' ); ?></strong>
        </p>
    </div>
</div>