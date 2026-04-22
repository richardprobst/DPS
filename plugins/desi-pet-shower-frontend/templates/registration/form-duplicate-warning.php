<?php
/**
 * Template: Signature duplicate warning.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 *
 * @var int  $duplicate_client_id
 * @var bool $confirmed
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$duplicate_client_id = $duplicate_client_id ?? 0;
$confirmed           = $confirmed ?? false;
?>

<div class="dps-registration__duplicate-stack">
    <article class="dps-registration-notice dps-registration-notice--warning" role="alert">
        <h3 class="dps-registration-notice__title"><?php esc_html_e( 'Telefone já cadastrado', 'dps-frontend-addon' ); ?></h3>
        <p class="dps-registration-notice__text">
            <?php
            printf(
                /* translators: %d: existing client ID */
                esc_html__( 'Encontramos este telefone na base (ID #%d). Continue apenas se o novo cadastro precisar ser separado.', 'dps-frontend-addon' ),
                $duplicate_client_id
            );
            ?>
        </p>
    </article>

    <label class="dps-registration-check" for="dps-registration-confirm-duplicate">
        <input type="hidden" name="dps_confirm_duplicate" value="" />
        <input
            id="dps-registration-confirm-duplicate"
            type="checkbox"
            name="dps_confirm_duplicate"
            value="1"
            <?php checked( $confirmed ); ?>
        />
        <span><?php esc_html_e( 'Confirmo que desejo seguir mesmo com telefone duplicado.', 'dps-frontend-addon' ); ?></span>
    </label>
</div>
