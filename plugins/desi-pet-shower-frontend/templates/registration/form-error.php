<?php
/**
 * Template: Signature registration error stack.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 *
 * @var string[] $errors
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$errors = $errors ?? [];
?>

<?php if ( ! empty( $errors ) ) : ?>
    <article class="dps-signature-notice dps-signature-notice--error" role="alert">
        <h3 class="dps-signature-notice__title"><?php esc_html_e( 'Revise os campos destacados para continuar.', 'dps-frontend-addon' ); ?></h3>
        <?php if ( 1 === count( $errors ) ) : ?>
            <p class="dps-signature-notice__text"><?php echo esc_html( $errors[0] ); ?></p>
        <?php else : ?>
            <ul>
                <?php foreach ( $errors as $error ) : ?>
                    <li><?php echo esc_html( $error ); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </article>
<?php endif; ?>
