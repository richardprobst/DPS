<?php
/**
 * Component: Textarea Field (M3 Expressive)
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 *
 * @var string $name        Campo name do textarea.
 * @var string $label       Label visível.
 * @var string $value       Valor atual.
 * @var string $placeholder Placeholder.
 * @var bool   $required    Se obrigatório.
 * @var string $error       Mensagem de erro.
 * @var int    $rows        Número de linhas (default: 4).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$name        = $name ?? '';
$label       = $label ?? '';
$value       = $value ?? '';
$placeholder = $placeholder ?? '';
$required    = $required ?? false;
$error       = $error ?? '';
$rows        = $rows ?? 4;
$fieldId     = 'dps-v2-' . esc_attr( $name );
?>

<div class="dps-v2-field dps-v2-field--textarea <?php echo '' !== $error ? 'dps-v2-field--error' : ''; ?>">

    <label for="<?php echo esc_attr( $fieldId ); ?>" class="dps-v2-field__label">
        <?php echo esc_html( $label ); ?>
        <?php if ( $required ) : ?>
            <span class="dps-v2-field__required" aria-label="<?php esc_attr_e( 'Obrigatório', 'dps-frontend-addon' ); ?>">*</span>
        <?php endif; ?>
    </label>

    <textarea
        id="<?php echo esc_attr( $fieldId ); ?>"
        name="<?php echo esc_attr( $name ); ?>"
        placeholder="<?php echo esc_attr( $placeholder ); ?>"
        class="dps-v2-field__textarea"
        rows="<?php echo esc_attr( (string) $rows ); ?>"
        <?php echo $required ? 'required' : ''; ?>
        <?php echo '' !== $error ? 'aria-invalid="true" aria-describedby="' . esc_attr( $fieldId ) . '-error"' : ''; ?>
    ><?php echo esc_textarea( $value ); ?></textarea>

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
