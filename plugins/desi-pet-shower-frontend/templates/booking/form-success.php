<?php
/**
 * Template: Booking V2 — Success
 *
 * Exibido após agendamento criado com sucesso.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 *
 * @var int                  $appointment_id   ID do agendamento criado.
 * @var array<string, mixed> $appointment_data Dados do agendamento (date, time, pets, services).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$appointment_id   = $appointment_id ?? 0;
$appointment_data = $appointment_data ?? [];
?>

<div class="dps-v2-booking dps-v2-booking--success">
    <div class="dps-v2-card dps-v2-card--elevated">
        <div class="dps-v2-card__content" style="text-align: center; padding: var(--dps-spacing-xl, 2rem);">

            <div class="dps-v2-booking__success-icon" aria-hidden="true">✓</div>

            <h2 class="dps-v2-typescale-headline-medium">
                <?php esc_html_e( 'Agendamento Confirmado!', 'dps-frontend-addon' ); ?>
            </h2>

            <p class="dps-v2-typescale-body-large dps-v2-color-on-surface-variant">
                <?php esc_html_e( 'Seu agendamento foi realizado com sucesso.', 'dps-frontend-addon' ); ?>
            </p>

            <?php // Detalhes do agendamento ?>
            <?php if ( ! empty( $appointment_data ) ) : ?>
                <div class="dps-v2-booking__success-details" style="text-align: left; margin-top: var(--dps-spacing-lg, 1.5rem);">
                    <div class="dps-v2-card dps-v2-card--outlined">
                        <div class="dps-v2-card__content">

                            <?php if ( ! empty( $appointment_data['date'] ) ) : ?>
                                <p class="dps-v2-typescale-body-medium">
                                    <strong><?php esc_html_e( 'Data:', 'dps-frontend-addon' ); ?></strong>
                                    <?php echo esc_html( $appointment_data['date'] ); ?>
                                </p>
                            <?php endif; ?>

                            <?php if ( ! empty( $appointment_data['time'] ) ) : ?>
                                <p class="dps-v2-typescale-body-medium">
                                    <strong><?php esc_html_e( 'Horário:', 'dps-frontend-addon' ); ?></strong>
                                    <?php echo esc_html( $appointment_data['time'] ); ?>
                                </p>
                            <?php endif; ?>

                            <?php if ( ! empty( $appointment_data['pets'] ) ) : ?>
                                <p class="dps-v2-typescale-body-medium">
                                    <strong><?php esc_html_e( 'Pets:', 'dps-frontend-addon' ); ?></strong>
                                    <?php echo esc_html( implode( ', ', $appointment_data['pets'] ) ); ?>
                                </p>
                            <?php endif; ?>

                            <?php if ( ! empty( $appointment_data['services'] ) ) : ?>
                                <p class="dps-v2-typescale-body-medium">
                                    <strong><?php esc_html_e( 'Serviços:', 'dps-frontend-addon' ); ?></strong>
                                    <?php echo esc_html( implode( ', ', $appointment_data['services'] ) ); ?>
                                </p>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php // CTAs ?>
            <div class="dps-v2-form-actions" style="justify-content: center; margin-top: var(--dps-spacing-lg, 1.5rem); gap: var(--dps-spacing-md, 1rem);">
                <?php
                $appointments_url = apply_filters( 'dps_booking_appointments_url', home_url( '/meus-agendamentos/' ) );
                ?>
                <a href="<?php echo esc_url( $appointments_url ); ?>" class="dps-v2-button dps-v2-button--primary">
                    <span class="dps-v2-button__text"><?php esc_html_e( 'Ver Meus Agendamentos', 'dps-frontend-addon' ); ?></span>
                </a>
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="dps-v2-button dps-v2-button--secondary">
                    <span class="dps-v2-button__text"><?php esc_html_e( 'Ir para Início', 'dps-frontend-addon' ); ?></span>
                </a>
            </div>

        </div>
    </div>
</div>
