<?php
/**
 * Template: Booking V2 — Form Main (Wizard Container)
 *
 * Wrapper principal do wizard nativo de agendamento M3 Expressive.
 * Será expandido nas próximas subfases (7.3) com steps completos.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 *
 * @var array<string, string> $atts             Atributos do shortcode.
 * @var string                $theme            Tema: light|dark.
 * @var bool                  $show_progress    Se exibe barra de progresso.
 * @var bool                  $compact          Modo compacto.
 * @var string                $appointment_type Tipo: simple|subscription|past.
 * @var int                   $current_step     Step atual (1-5).
 * @var int                   $total_steps      Total de steps.
 * @var string[]              $errors           Erros de validação.
 * @var array<string, mixed>  $data             Dados do wizard.
 * @var string                $nonce_field      Campo nonce HTML.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$stepLabels = [
    1 => __( 'Cliente', 'dps-frontend-addon' ),
    2 => __( 'Pet', 'dps-frontend-addon' ),
    3 => __( 'Serviço', 'dps-frontend-addon' ),
    4 => __( 'Data/Hora', 'dps-frontend-addon' ),
    5 => __( 'Confirmação', 'dps-frontend-addon' ),
];
?>

<div class="dps-v2-booking" data-theme="<?php echo esc_attr( $theme ?? 'light' ); ?>" data-step="<?php echo esc_attr( (string) $current_step ); ?>">

    <!-- Header -->
    <div class="dps-v2-booking__header">
        <h2 class="dps-v2-typescale-headline-large">
            <?php esc_html_e( 'Agendar Serviço', 'dps-frontend-addon' ); ?>
        </h2>
    </div>

    <!-- Progress Bar -->
    <?php if ( $show_progress ) : ?>
        <nav class="dps-v2-booking__progress" aria-label="<?php esc_attr_e( 'Progresso do agendamento', 'dps-frontend-addon' ); ?>">
            <ol class="dps-v2-wizard-steps">
                <?php foreach ( $stepLabels as $stepNum => $stepLabel ) : ?>
                    <li class="dps-v2-wizard-steps__item <?php
                        echo $stepNum < $current_step ? 'dps-v2-wizard-steps__item--completed' : '';
                        echo $stepNum === $current_step ? 'dps-v2-wizard-steps__item--active' : '';
                    ?>" aria-current="<?php echo $stepNum === $current_step ? 'step' : 'false'; ?>">
                        <span class="dps-v2-wizard-steps__number"><?php echo esc_html( (string) $stepNum ); ?></span>
                        <span class="dps-v2-wizard-steps__label"><?php echo esc_html( $stepLabel ); ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>
    <?php endif; ?>

    <!-- Errors -->
    <?php if ( ! empty( $errors ) ) : ?>
        <div class="dps-v2-booking__errors">
            <div class="dps-v2-alert dps-v2-alert--error" role="alert">
                <div class="dps-v2-alert__content">
                    <?php foreach ( $errors as $err ) : ?>
                        <p><?php echo esc_html( $err ); ?></p>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Wizard Form (placeholder — será expandido na Fase 7.3) -->
    <form
        method="post"
        action=""
        class="dps-v2-booking__form"
        novalidate
    >
        <?php
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_nonce_field() output
        echo $nonce_field;
        ?>
        <input type="hidden" name="dps_booking_step" value="<?php echo esc_attr( (string) $current_step ); ?>" />
        <input type="hidden" name="dps_appointment_type" value="<?php echo esc_attr( $appointment_type ); ?>" />

        <p class="dps-v2-typescale-body-medium dps-v2-color-on-surface-variant">
            <?php esc_html_e( 'Wizard nativo V2 em construção. Ative a flag booking_v2 para testar.', 'dps-frontend-addon' ); ?>
        </p>

    </form>

</div>
