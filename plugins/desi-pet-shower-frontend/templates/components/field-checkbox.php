<?php
/**
 * Component: Checkbox Field (M3 Expressive)
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 *
 * @var string $name    Campo name do checkbox.
 * @var string $label   Label visível.
 * @var bool   $checked Se marcado.
 * @var string $error   Mensagem de erro.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$name    = $name ?? '';
$label   = $label ?? '';
$checked = $checked ?? false;
$error   = $error ?? '';
$fieldId = 'dps-v2-' . esc_attr( $name );
?>

<div class="dps-v2-field dps-v2-field--checkbox <?php echo '' !== $error ? 'dps-v2-field--error' : ''; ?>">

    <label for="<?php echo esc_attr( $fieldId ); ?>" class="dps-v2-field__checkbox-label">
        <input
            type="checkbox"
            id="<?php echo esc_attr( $fieldId ); ?>"
            name="<?php echo esc_attr( $name ); ?>"
            value="1"
            class="dps-v2-field__checkbox"
            <?php checked( $checked ); ?>
            <?php echo '' !== $error ? 'aria-invalid="true" aria-describedby="' . esc_attr( $fieldId ) . '-error"' : ''; ?>
        />
        <span class="dps-v2-field__checkbox-text"><?php echo esc_html( $label ); ?></span>
    </label>

    <?php if ( '' !== $error ) : ?>
        <span
            id="<?php echo esc_attr( $fieldId ); ?>-error"
            class="dps-v2-field__error"
            role="alert"
        >
            <?php echo esc_html( $error ); ?>
        </span>
    <?php endif; ?>

</div>
