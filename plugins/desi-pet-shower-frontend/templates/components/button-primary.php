<?php
/**
 * Component: Primary Button (M3 Expressive)
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 *
 * @var string $text    Texto do botão.
 * @var string $type    Tipo: submit, button, reset (default: submit).
 * @var bool   $loading Se mostra loader ao clicar.
 * @var bool   $disabled Se desabilitado.
 * @var string $icon    Classe de ícone (opcional).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$text     = $text ?? __( 'Enviar', 'dps-frontend-addon' );
$type     = $type ?? 'submit';
$loading  = $loading ?? false;
$disabled = $disabled ?? false;
$icon     = $icon ?? '';
?>

<button
    type="<?php echo esc_attr( $type ); ?>"
    class="dps-v2-button dps-v2-button--primary <?php echo $loading ? 'dps-v2-button--loading' : ''; ?>"
    <?php echo $disabled ? 'disabled' : ''; ?>
    <?php echo $loading ? 'data-loading="true"' : ''; ?>
>
    <?php if ( '' !== $icon ) : ?>
        <span class="dps-v2-button__icon" aria-hidden="true"><?php echo esc_html( $icon ); ?></span>
    <?php endif; ?>
    <span class="dps-v2-button__text"><?php echo esc_html( $text ); ?></span>
    <?php if ( $loading ) : ?>
        <span class="dps-v2-button__loader" aria-hidden="true"></span>
    <?php endif; ?>
</button>
