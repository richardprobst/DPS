<?php
/**
 * Component: Secondary Button (M3 Expressive)
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 *
 * @var string $text     Texto do botão.
 * @var string $type     Tipo: submit, button, reset (default: button).
 * @var bool   $disabled Se desabilitado.
 * @var string $icon     Classe de ícone (opcional).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$text     = $text ?? __( 'Cancelar', 'dps-frontend-addon' );
$type     = $type ?? 'button';
$disabled = $disabled ?? false;
$icon     = $icon ?? '';
?>

<button
    type="<?php echo esc_attr( $type ); ?>"
    class="dps-v2-button dps-v2-button--secondary"
    <?php echo $disabled ? 'disabled' : ''; ?>
>
    <?php if ( '' !== $icon ) : ?>
        <span class="dps-v2-button__icon" aria-hidden="true"><?php echo esc_html( $icon ); ?></span>
    <?php endif; ?>
    <span class="dps-v2-button__text"><?php echo esc_html( $text ); ?></span>
</button>
