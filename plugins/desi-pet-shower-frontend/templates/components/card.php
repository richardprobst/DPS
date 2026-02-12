<?php
/**
 * Component: Card (M3 Expressive)
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 *
 * @var string $title   Título do card (opcional).
 * @var string $content Conteúdo HTML do card.
 * @var string $variant Variante: elevated, filled, outlined (default: elevated).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$title   = $title ?? '';
$content = $content ?? '';
$variant = $variant ?? 'elevated';
?>

<div class="dps-v2-card dps-v2-card--<?php echo esc_attr( $variant ); ?>">
    <?php if ( '' !== $title ) : ?>
        <div class="dps-v2-card__header">
            <h3 class="dps-v2-card__title"><?php echo esc_html( $title ); ?></h3>
        </div>
    <?php endif; ?>

    <div class="dps-v2-card__content">
        <?php
        // Content is pre-escaped HTML from template engine
        echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        ?>
    </div>
</div>
