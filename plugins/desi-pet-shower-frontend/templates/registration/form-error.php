<?php
/**
 * Template: Registration V2 — Error
 *
 * Exibido quando ocorre erro no processamento do registro.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 *
 * @var string[] $errors Lista de mensagens de erro.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$errors = $errors ?? [];
?>

<?php if ( ! empty( $errors ) ) : ?>
    <div class="dps-v2-alert dps-v2-alert--error" role="alert">
        <div class="dps-v2-alert__content">
            <?php if ( 1 === count( $errors ) ) : ?>
                <p><?php echo esc_html( $errors[0] ); ?></p>
            <?php else : ?>
                <ul>
                    <?php foreach ( $errors as $err ) : ?>
                        <li><?php echo esc_html( $err ); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
