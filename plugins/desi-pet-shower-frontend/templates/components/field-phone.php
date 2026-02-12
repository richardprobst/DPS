<?php
/**
 * Component: Phone Field (M3 Expressive)
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 *
 * @var string $name     Campo name do input.
 * @var string $label    Label visível.
 * @var string $value    Valor atual.
 * @var bool   $required Se obrigatório.
 * @var string $error    Mensagem de erro.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$name     = $name ?? 'phone';
$label    = $label ?? __( 'Telefone', 'dps-frontend-addon' );
$value    = $value ?? '';
$required = $required ?? false;
$error    = $error ?? '';
$fieldId  = 'dps-v2-' . esc_attr( $name );
?>

<div class="dps-v2-field dps-v2-field--phone <?php echo '' !== $error ? 'dps-v2-field--error' : ''; ?>">

    <label for="<?php echo esc_attr( $fieldId ); ?>" class="dps-v2-field__label">
        <?php echo esc_html( $label ); ?>
        <?php if ( $required ) : ?>
            <span class="dps-v2-field__required" aria-label="<?php esc_attr_e( 'Obrigatório', 'dps-frontend-addon' ); ?>">*</span>
        <?php endif; ?>
    </label>

    <input
        type="tel"
        id="<?php echo esc_attr( $fieldId ); ?>"
        name="<?php echo esc_attr( $name ); ?>"
        value="<?php echo esc_attr( $value ); ?>"
        placeholder="<?php echo esc_attr__( '(11) 99999-9999', 'dps-frontend-addon' ); ?>"
        class="dps-v2-field__input"
        autocomplete="tel"
        inputmode="tel"
        <?php echo $required ? 'required' : ''; ?>
        <?php echo '' !== $error ? 'aria-invalid="true" aria-describedby="' . esc_attr( $fieldId ) . '-error"' : ''; ?>
    />

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
