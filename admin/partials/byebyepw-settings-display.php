<?php
/**
 * Settings page display
 *
 * @link       https://labountylabs.com
 * @since      1.0.0
 *
 * @package    Byebyepw
 * @subpackage Byebyepw/admin/partials
 */
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
        <h2><?php _e( 'Important Security Notice', 'byebyepw' ); ?></h2>
        <p class="notice notice-warning">
            <?php _e( 'Before disabling password login, make sure you have:', 'byebyepw' ); ?>
        </p>
        <ul style="list-style-type: disc; margin-left: 20px;">
            <li><?php _e( 'At least one passkey registered', 'byebyepw' ); ?></li>
            <li><?php _e( 'Recovery codes generated and saved in a secure location', 'byebyepw' ); ?></li>
            <li><?php _e( 'Tested passkey login successfully', 'byebyepw' ); ?></li>
        </ul>
        <p>
            <strong><?php _e( 'Disabling password login without proper passkey setup will lock you out of your site!', 'byebyepw' ); ?></strong>
        </p>
    </div>
</div>