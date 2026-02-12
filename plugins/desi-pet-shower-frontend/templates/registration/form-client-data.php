<?php
/**
 * Template: Registration V2 — Client Data Section
 *
 * Campos do cliente: nome, email, telefone, CPF, endereço.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 *
 * @var DPS_Template_Engine    $engine  Template engine para sub-renders.
 * @var array<string, mixed>   $data    Dados preenchidos (sticky form).
 * @var array<string, string>  $field_errors Erros por campo.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$data         = $data ?? [];
$field_errors = $field_errors ?? [];
?>

<fieldset class="dps-v2-registration__section">
    <legend class="dps-v2-typescale-title-large">
        <?php esc_html_e( 'Dados Pessoais', 'dps-frontend-addon' ); ?>
    </legend>

    <div class="dps-v2-registration__fields">

        <?php // Nome (obrigatório) ?>
        <div class="dps-v2-field dps-v2-field--text <?php echo ! empty( $field_errors['client_name'] ) ? 'dps-v2-field--error' : ''; ?>">
            <label for="dps-v2-client_name" class="dps-v2-field__label">
                <?php esc_html_e( 'Nome completo', 'dps-frontend-addon' ); ?>
                <span class="dps-v2-field__required" aria-label="<?php esc_attr_e( 'Obrigatório', 'dps-frontend-addon' ); ?>">*</span>
            </label>
            <input
                type="text"
                id="dps-v2-client_name"
                name="client_name"
                value="<?php echo esc_attr( $data['client_name'] ?? '' ); ?>"
                class="dps-v2-field__input"
                required
                autocomplete="name"
                <?php echo ! empty( $field_errors['client_name'] ) ? 'aria-invalid="true" aria-describedby="dps-v2-client_name-error"' : ''; ?>
            />
            <?php if ( ! empty( $field_errors['client_name'] ) ) : ?>
                <span id="dps-v2-client_name-error" class="dps-v2-field__error" role="alert"><?php echo esc_html( $field_errors['client_name'] ); ?></span>
            <?php endif; ?>
        </div>

        <?php // Email (obrigatório) ?>
        <div class="dps-v2-field dps-v2-field--email <?php echo ! empty( $field_errors['client_email'] ) ? 'dps-v2-field--error' : ''; ?>">
            <label for="dps-v2-client_email" class="dps-v2-field__label">
                <?php esc_html_e( 'Email', 'dps-frontend-addon' ); ?>
                <span class="dps-v2-field__required" aria-label="<?php esc_attr_e( 'Obrigatório', 'dps-frontend-addon' ); ?>">*</span>
            </label>
            <input
                type="email"
                id="dps-v2-client_email"
                name="client_email"
                value="<?php echo esc_attr( $data['client_email'] ?? '' ); ?>"
                class="dps-v2-field__input"
                required
                autocomplete="email"
                inputmode="email"
                <?php echo ! empty( $field_errors['client_email'] ) ? 'aria-invalid="true" aria-describedby="dps-v2-client_email-error"' : ''; ?>
            />
            <?php if ( ! empty( $field_errors['client_email'] ) ) : ?>
                <span id="dps-v2-client_email-error" class="dps-v2-field__error" role="alert"><?php echo esc_html( $field_errors['client_email'] ); ?></span>
            <?php endif; ?>
        </div>

        <?php // Telefone (obrigatório) ?>
        <div class="dps-v2-field dps-v2-field--phone <?php echo ! empty( $field_errors['client_phone'] ) ? 'dps-v2-field--error' : ''; ?>">
            <label for="dps-v2-client_phone" class="dps-v2-field__label">
                <?php esc_html_e( 'Telefone', 'dps-frontend-addon' ); ?>
                <span class="dps-v2-field__required" aria-label="<?php esc_attr_e( 'Obrigatório', 'dps-frontend-addon' ); ?>">*</span>
            </label>
            <input
                type="tel"
                id="dps-v2-client_phone"
                name="client_phone"
                value="<?php echo esc_attr( $data['client_phone'] ?? '' ); ?>"
                placeholder="<?php echo esc_attr__( '(11) 99999-9999', 'dps-frontend-addon' ); ?>"
                class="dps-v2-field__input"
                required
                autocomplete="tel"
                inputmode="tel"
                <?php echo ! empty( $field_errors['client_phone'] ) ? 'aria-invalid="true" aria-describedby="dps-v2-client_phone-error"' : ''; ?>
            />
            <?php if ( ! empty( $field_errors['client_phone'] ) ) : ?>
                <span id="dps-v2-client_phone-error" class="dps-v2-field__error" role="alert"><?php echo esc_html( $field_errors['client_phone'] ); ?></span>
            <?php endif; ?>
        </div>

        <?php // CPF (opcional) ?>
        <div class="dps-v2-field dps-v2-field--text <?php echo ! empty( $field_errors['client_cpf'] ) ? 'dps-v2-field--error' : ''; ?>">
            <label for="dps-v2-client_cpf" class="dps-v2-field__label">
                <?php esc_html_e( 'CPF', 'dps-frontend-addon' ); ?>
            </label>
            <input
                type="text"
                id="dps-v2-client_cpf"
                name="client_cpf"
                value="<?php echo esc_attr( $data['client_cpf'] ?? '' ); ?>"
                placeholder="000.000.000-00"
                class="dps-v2-field__input"
                inputmode="numeric"
                data-mask="cpf"
                <?php echo ! empty( $field_errors['client_cpf'] ) ? 'aria-invalid="true" aria-describedby="dps-v2-client_cpf-error"' : ''; ?>
            />
            <?php if ( ! empty( $field_errors['client_cpf'] ) ) : ?>
                <span id="dps-v2-client_cpf-error" class="dps-v2-field__error" role="alert"><?php echo esc_html( $field_errors['client_cpf'] ); ?></span>
            <?php else : ?>
                <span class="dps-v2-field__helper"><?php esc_html_e( 'Opcional', 'dps-frontend-addon' ); ?></span>
            <?php endif; ?>
        </div>

        <?php // Endereço (opcional) ?>
        <div class="dps-v2-field dps-v2-field--text">
            <label for="dps-v2-client_address" class="dps-v2-field__label">
                <?php esc_html_e( 'Endereço', 'dps-frontend-addon' ); ?>
            </label>
            <input
                type="text"
                id="dps-v2-client_address"
                name="client_address"
                value="<?php echo esc_attr( $data['client_address'] ?? '' ); ?>"
                class="dps-v2-field__input"
                autocomplete="street-address"
            />
            <input type="hidden" name="client_lat" value="<?php echo esc_attr( $data['client_lat'] ?? '' ); ?>" />
            <input type="hidden" name="client_lng" value="<?php echo esc_attr( $data['client_lng'] ?? '' ); ?>" />
        </div>

    </div>
</fieldset>
