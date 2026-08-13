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

<h2><?php esc_html_e( 'Passkey sign-in', 'bye-bye-passwords' ); ?></h2>

<table class="form-table byebyepw-profile-table">
    <tr>
        <th><?php esc_html_e( 'Sign-in security', 'bye-bye-passwords' ); ?></th>
        <td>
            <div class="byebyepw-profile-summary">
                <div>
                    <span class="byebyepw-status <?php echo empty( $credentials ) ? 'is-needed' : 'is-ready'; ?>">
                        <?php
                        echo empty( $credentials )
                            ? esc_html__( 'No passkey', 'bye-bye-passwords' )
                            : esc_html(
                                sprintf(
                                    /* translators: %d: number of registered passkeys. */
                                    _n( '%d passkey', '%d passkeys', count( $credentials ), 'bye-bye-passwords' ),
                                    count( $credentials )
                                )
                            );
                        ?>
                    </span>
                    <span class="byebyepw-status <?php echo $has_recovery_codes ? 'is-ready' : 'is-needed'; ?>">
                        <?php
                        echo $has_recovery_codes
                            ? esc_html(
                                sprintf(
                                    /* translators: %d: number of unused recovery codes. */
                                    _n( '%d recovery code', '%d recovery codes', intval( $remaining_codes ), 'bye-bye-passwords' ),
                                    intval( $remaining_codes )
                                )
                            )
                            : esc_html__( 'No recovery codes', 'bye-bye-passwords' );
                        ?>
                    </span>
                </div>
                <p class="description"><?php esc_html_e( 'Passkeys and recovery codes are managed on their own security screen.', 'bye-bye-passwords' ); ?></p>
                <?php if ( ! empty( $credentials ) ) : ?>
                    <ul class="byebyepw-profile-passkeys">
                        <?php foreach ( $credentials as $byebyepw_credential ) : ?>
                            <li>
                                <strong><?php echo esc_html( $byebyepw_credential->name ?: __( 'Unnamed passkey', 'bye-bye-passwords' ) ); ?></strong>
                                <span><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $byebyepw_credential->created_at ) ) ); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=byebyepw' ) ); ?>" class="button">
                    <?php esc_html_e( 'Manage sign-in security', 'bye-bye-passwords' ); ?>
                </a>
            </div>
        </td>
    </tr>
</table>
