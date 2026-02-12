<?php
/**
 * Component: Text Field (M3 Expressive)
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 *
 * @var string $name         Campo name do input.
 * @var string $label        Label visível.
 * @var string $value        Valor atual.
 * @var string $placeholder  Placeholder.
 * @var bool   $required     Se obrigatório.
 * @var string $autocomplete Valor do atributo autocomplete.
 * @var string $type         Tipo do input (default: text).
 * @var string $error        Mensagem de erro (vazio se sem erro).
 * @var string $helper       Texto auxiliar (opcional).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$name         = $name ?? '';
$label        = $label ?? '';
$value        = $value ?? '';
$placeholder  = $placeholder ?? '';
$required     = $required ?? false;
$autocomplete = $autocomplete ?? '';
$type         = $type ?? 'text';
$error        = $error ?? '';
$helper       = $helper ?? '';
$fieldId      = 'dps-v2-' . esc_attr( $name );
?>

<div class="dps-v2-field dps-v2-field--text <?php echo '' !== $error ? 'dps-v2-field--error' : ''; ?>">

    <label for="<?php echo esc_attr( $fieldId ); ?>" class="dps-v2-field__label">
        <?php echo esc_html( $label ); ?>
        <?php if ( $required ) : ?>
            <span class="dps-v2-field__required" aria-label="<?php esc_attr_e( 'Obrigatório', 'dps-frontend-addon' ); ?>">*</span>
        <?php endif; ?>
    </label>

    <input
        type="<?php echo esc_attr( $type ); ?>"
        id="<?php echo esc_attr( $fieldId ); ?>"
        name="<?php echo esc_attr( $name ); ?>"
        value="<?php echo esc_attr( $value ); ?>"
        placeholder="<?php echo esc_attr( $placeholder ); ?>"
        class="dps-v2-field__input"
        <?php echo $required ? 'required' : ''; ?>
        <?php echo '' !== $autocomplete ? 'autocomplete="' . esc_attr( $autocomplete ) . '"' : ''; ?>
        <?php echo '' !== $error ? 'aria-invalid="true"' : ''; ?>
        <?php echo '' !== $error ? 'aria-describedby="' . esc_attr( $fieldId ) . '-error"' : ''; ?>
        <?php echo '' !== $helper && '' === $error ? 'aria-describedby="' . esc_attr( $fieldId ) . '-helper"' : ''; ?>
    />

    <?php if ( '' !== $error ) : ?>
        <span
            id="<?php echo esc_attr( $fieldId ); ?>-error"
            class="dps-v2-field__error"
            role="alert"
        >
            <?php echo esc_html( $error ); ?>
        </span>
    <?php elseif ( '' !== $helper ) : ?>
        <span
            id="<?php echo esc_attr( $fieldId ); ?>-helper"
            class="dps-v2-field__helper"
        >
            <?php echo esc_html( $helper ); ?>
        </span>
    <?php endif; ?>

</div>
