<?php
/**
 * Component: Loader (M3 Expressive)
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 *
 * @var string $size    Tamanho: small, medium, large (default: medium).
 * @var string $message Mensagem de carregamento (acessibilidade).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$size    = $size ?? 'medium';
$message = $message ?? __( 'Carregando...', 'dps-frontend-addon' );
?>

<div class="dps-v2-loader dps-v2-loader--<?php echo esc_attr( $size ); ?>" role="status">
    <span class="dps-v2-loader__spinner" aria-hidden="true"></span>
    <span class="dps-v2-loader__text screen-reader-text"><?php echo esc_html( $message ); ?></span>
</div>
