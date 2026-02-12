<?php
/**
 * Template: Registration V2 — Duplicate Warning
 *
 * Exibido quando telefone duplicado detectado e admin pode fazer override.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 *
 * @var int $duplicate_client_id ID do cliente duplicado.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$duplicate_client_id = $duplicate_client_id ?? 0;
?>

<div class="dps-v2-alert dps-v2-alert--warning" role="alert">
    <div class="dps-v2-alert__content">
        <p>
            <strong><?php esc_html_e( 'Atenção:', 'dps-frontend-addon' ); ?></strong>
            <?php
            printf(
                /* translators: %d: existing client ID */
                esc_html__( 'Já existe um cliente com este telefone (ID #%d). Marque a opção abaixo para confirmar o cadastro de um novo registro.', 'dps-frontend-addon' ),
                $duplicate_client_id
            );
            ?>
        </p>
    </div>
</div>

<div class="dps-v2-field dps-v2-field--checkbox">
    <label for="dps-v2-confirm_duplicate" class="dps-v2-field__checkbox-label">
        <input
            type="checkbox"
            id="dps-v2-confirm_duplicate"
            name="dps_confirm_duplicate"
            value="1"
            class="dps-v2-field__checkbox"
        />
        <span class="dps-v2-field__checkbox-text">
            <?php esc_html_e( 'Confirmo que desejo criar um novo cadastro mesmo com telefone duplicado.', 'dps-frontend-addon' ); ?>
        </span>
    </label>
</div>
