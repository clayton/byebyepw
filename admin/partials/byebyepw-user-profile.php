<?php
/**
 * User profile passkey management
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

<h2><?php esc_html_e( 'Passkey Authentication', 'bye-bye-passwords' ); ?></h2>

<table class="form-table">
    <tr>
        <th><?php esc_html_e( 'Registered Passkeys', 'bye-bye-passwords' ); ?></th>
        <td>
            <?php if ( empty( $credentials ) ) : ?>
                <p><?php esc_html_e( 'No passkeys registered.', 'bye-bye-passwords' ); ?></p>
            <?php else : ?>
                <ul>
                    <?php foreach ( $credentials as $byebyepw_credential ) : ?>
                        <li>
                            <?php echo esc_html( $byebyepw_credential->name ?: __( 'Unnamed Passkey', 'bye-bye-passwords' ) ); ?>
                            - <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $byebyepw_credential->created_at ) ) ); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </td>
    </tr>
    
    <tr>
        <th><?php esc_html_e( 'Recovery Codes', 'bye-bye-passwords' ); ?></th>
        <td>
            <?php if ( $has_recovery_codes ) : ?>
                <p><?php 
                    // translators: %d is the number of remaining recovery codes
                    printf( esc_html__( '%d recovery codes remaining.', 'bye-bye-passwords' ), intval( $remaining_codes ) ); ?></p>
            <?php else : ?>
                <p><?php esc_html_e( 'No recovery codes generated.', 'bye-bye-passwords' ); ?></p>
            <?php endif; ?>
        </td>
    </tr>
</table>

<p>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=byebyepw' ) ); ?>" class="button">
        <?php esc_html_e( 'Manage Passkeys', 'bye-bye-passwords' ); ?>
    </a>
</p>