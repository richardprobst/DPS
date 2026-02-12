<?php
/**
 * Component: Alert (M3 Expressive)
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 *
 * @var string $type        Tipo: success, error, warning, info (default: info).
 * @var string $message     Mensagem do alerta.
 * @var bool   $dismissible Se pode ser fechado (default: false).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$type        = $type ?? 'info';
$message     = $message ?? '';
$dismissible = $dismissible ?? false;

$roleMap = [
    'error'   => 'alert',
    'warning' => 'alert',
    'success' => 'status',
    'info'    => 'status',
];
$role = $roleMap[ $type ] ?? 'status';
?>

<div
    class="dps-v2-alert dps-v2-alert--<?php echo esc_attr( $type ); ?>"
    role="<?php echo esc_attr( $role ); ?>"
    <?php echo 'status' === $role ? 'aria-live="polite"' : ''; ?>
>
    <div class="dps-v2-alert__content">
        <?php echo wp_kses_post( $message ); ?>
    </div>

    <?php if ( $dismissible ) : ?>
        <button
            type="button"
            class="dps-v2-alert__dismiss"
            aria-label="<?php esc_attr_e( 'Fechar', 'dps-frontend-addon' ); ?>"
        >
            <span aria-hidden="true">&times;</span>
        </button>
    <?php endif; ?>
</div>
