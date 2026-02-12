<?php
/**
 * Component: Select Field (M3 Expressive)
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 *
 * @var string                      $name     Campo name do select.
 * @var string                      $label    Label visível.
 * @var string                      $value    Valor selecionado.
 * @var array<string, string>       $options  Opções (value => label).
 * @var bool                        $required Se obrigatório.
 * @var string                      $error    Mensagem de erro.
 * @var string                      $placeholder Placeholder (primeira opção desabilitada).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$name        = $name ?? '';
$label       = $label ?? '';
$value       = $value ?? '';
$options     = $options ?? [];
$required    = $required ?? false;
$error       = $error ?? '';
$placeholder = $placeholder ?? __( 'Selecione...', 'dps-frontend-addon' );
$fieldId     = 'dps-v2-' . esc_attr( $name );
?>

<div class="dps-v2-field dps-v2-field--select <?php echo '' !== $error ? 'dps-v2-field--error' : ''; ?>">

    <label for="<?php echo esc_attr( $fieldId ); ?>" class="dps-v2-field__label">
        <?php echo esc_html( $label ); ?>
        <?php if ( $required ) : ?>
            <span class="dps-v2-field__required" aria-label="<?php esc_attr_e( 'Obrigatório', 'dps-frontend-addon' ); ?>">*</span>
        <?php endif; ?>
    </label>

    <select
        id="<?php echo esc_attr( $fieldId ); ?>"
        name="<?php echo esc_attr( $name ); ?>"
        class="dps-v2-field__select"
        <?php echo $required ? 'required' : ''; ?>
        <?php echo '' !== $error ? 'aria-invalid="true" aria-describedby="' . esc_attr( $fieldId ) . '-error"' : ''; ?>
    >
        <?php if ( '' !== $placeholder ) : ?>
            <option value="" disabled <?php selected( '', $value ); ?>><?php echo esc_html( $placeholder ); ?></option>
        <?php endif; ?>

        <?php foreach ( $options as $optValue => $optLabel ) : ?>
            <option value="<?php echo esc_attr( $optValue ); ?>" <?php selected( (string) $optValue, $value ); ?>>
                <?php echo esc_html( $optLabel ); ?>
            </option>
        <?php endforeach; ?>
    </select>

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
