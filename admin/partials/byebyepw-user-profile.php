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
?>

<h2><?php esc_html_e( 'Passkey Authentication', 'byebyepw' ); ?></h2>

<table class="form-table">
    <tr>
        <th><?php esc_html_e( 'Registered Passkeys', 'byebyepw' ); ?></th>
        <td>
            <?php if ( empty( $credentials ) ) : ?>
                <p><?php esc_html_e( 'No passkeys registered.', 'byebyepw' ); ?></p>
            <?php else : ?>
                <ul>
                    <?php foreach ( $credentials as $credential ) : ?>
                        <li>
                            <?php echo esc_html( $credential->friendly_name ?: __( 'Unnamed Passkey', 'byebyepw' ) ); ?>
                            - <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $credential->created_at ) ) ); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </td>
    </tr>
    
    <tr>
        <th><?php esc_html_e( 'Recovery Codes', 'byebyepw' ); ?></th>
        <td>
            <?php if ( $has_recovery_codes ) : ?>
                <p><?php 
                    // translators: %d is the number of remaining recovery codes
                    printf( esc_html__( '%d recovery codes remaining.', 'byebyepw' ), intval( $remaining_codes ) ); ?></p>
            <?php else : ?>
                <p><?php esc_html_e( 'No recovery codes generated.', 'byebyepw' ); ?></p>
            <?php endif; ?>
        </td>
    </tr>
</table>

<p>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=byebyepw' ) ); ?>" class="button">
        <?php esc_html_e( 'Manage Passkeys', 'byebyepw' ); ?>
    </a>
</p>