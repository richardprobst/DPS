<?php
/**
 * Template: Booking V2 — Form Main (Wizard Container)
 *
 * Wrapper principal do wizard nativo de agendamento M3 Expressive.
 * Renderiza os steps do wizard baseado no estado atual.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 *
 * @var DPS_Template_Engine    $engine           Template engine para sub-renders.
 * @var array<string, string>  $atts             Atributos do shortcode.
 * @var string                 $theme            Tema: light|dark.
 * @var bool                   $show_progress    Se exibe barra de progresso.
 * @var bool                   $compact          Modo compacto.
 * @var string                 $appointment_type Tipo: simple|subscription|past.
 * @var int                    $current_step     Step atual (1-5).
 * @var int                    $total_steps      Total de steps.
 * @var string[]               $errors           Erros de validação.
 * @var array<string, mixed>   $data             Dados do wizard.
 * @var string                 $nonce_field      Campo nonce HTML.
 * @var bool                   $success          Se o agendamento foi bem-sucedido.
 * @var int                    $appointment_id   ID do agendamento criado (quando success).
 * @var array<string, mixed>   $appointment_data Dados do agendamento criado (quando success).
 * @var array<string, mixed>   $summary          Resumo para step de confirmação.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$success          = $success ?? false;
$engine           = $engine ?? null;
$appointment_id   = $appointment_id ?? 0;
$appointment_data = $appointment_data ?? [];
$summary          = $summary ?? [];
$data             = $data ?? [];

$stepLabels = [
    1 => __( 'Cliente', 'dps-frontend-addon' ),
    2 => __( 'Pet', 'dps-frontend-addon' ),
    3 => __( 'Serviço', 'dps-frontend-addon' ),
    4 => __( 'Data/Hora', 'dps-frontend-addon' ),
    5 => __( 'Confirmação', 'dps-frontend-addon' ),
];
?>

<?php if ( $success ) : ?>
    <?php
    if ( $engine ) {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template engine renders escaped content
        echo $engine->render( 'booking/form-success.php', [
            'appointment_id'   => $appointment_id,
            'appointment_data' => $appointment_data,
        ] );
    }
    ?>
<?php else : ?>

<div class="dps-v2-booking <?php echo ! empty( $compact ) ? 'dps-v2-booking--compact' : ''; ?>" data-theme="<?php echo esc_attr( $theme ?? 'light' ); ?>" data-step="<?php echo esc_attr( (string) $current_step ); ?>">

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

    <!-- Wizard Form -->
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

        <div class="dps-v2-booking__steps">

            <?php // Step 1: Client Selection ?>
            <div class="dps-v2-booking__step" data-step="1" <?php echo 1 !== $current_step ? 'style="display: none;"' : ''; ?>>
                <?php
                if ( $engine ) {
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo $engine->render( 'booking/step-client-selection.php', [
                        'client_id'   => $data['client_id'] ?? 0,
                        'client_data' => $data['client_data'] ?? [],
                        'errors'      => 1 === $current_step ? $errors : [],
                    ] );
                }
                ?>
            </div>

            <?php // Step 2: Pet Selection ?>
            <div class="dps-v2-booking__step" data-step="2" <?php echo 2 !== $current_step ? 'style="display: none;"' : ''; ?>>
                <?php
                if ( $engine ) {
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo $engine->render( 'booking/step-pet-selection.php', [
                        'pet_ids'   => $data['pet_ids'] ?? [],
                        'client_id' => $data['client_id'] ?? 0,
                        'errors'    => 2 === $current_step ? $errors : [],
                    ] );
                }
                ?>
            </div>

            <?php // Step 3: Service Selection ?>
            <div class="dps-v2-booking__step" data-step="3" <?php echo 3 !== $current_step ? 'style="display: none;"' : ''; ?>>
                <?php
                if ( $engine ) {
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo $engine->render( 'booking/step-service-selection.php', [
                        'service_ids' => $data['service_ids'] ?? [],
                        'errors'      => 3 === $current_step ? $errors : [],
                    ] );
                }
                ?>
            </div>

            <?php // Step 4: Date/Time Selection ?>
            <div class="dps-v2-booking__step" data-step="4" <?php echo 4 !== $current_step ? 'style="display: none;"' : ''; ?>>
                <?php
                if ( $engine ) {
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo $engine->render( 'booking/step-datetime-selection.php', [
                        'appointment_date' => $data['appointment_date'] ?? '',
                        'appointment_time' => $data['appointment_time'] ?? '',
                        'appointment_type' => $appointment_type,
                        'errors'           => 4 === $current_step ? $errors : [],
                    ] );
                }
                ?>
            </div>

            <?php // Step 5a: Extras (TaxiDog + Tosa) ?>
            <div class="dps-v2-booking__step" data-step="5a" <?php echo 5 !== $current_step ? 'style="display: none;"' : ''; ?>>
                <?php
                if ( $engine ) {
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo $engine->render( 'booking/step-extras.php', [
                        'appointment_type'            => $appointment_type,
                        'appointment_taxidog'         => $data['appointment_taxidog'] ?? false,
                        'appointment_taxidog_price'   => $data['appointment_taxidog_price'] ?? '0',
                        'appointment_tosa'            => $data['appointment_tosa'] ?? false,
                        'appointment_tosa_price'      => $data['appointment_tosa_price'] ?? '30',
                        'appointment_tosa_occurrence' => $data['appointment_tosa_occurrence'] ?? 1,
                        'errors'                      => 5 === $current_step ? $errors : [],
                    ] );
                }
                ?>
            </div>

            <?php // Step 5b: Confirmation ?>
            <div class="dps-v2-booking__step" data-step="5b" <?php echo 5 !== $current_step ? 'style="display: none;"' : ''; ?>>
                <?php
                if ( $engine ) {
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo $engine->render( 'booking/step-confirmation.php', [
                        'summary' => $summary,
                        'errors'  => 5 === $current_step ? $errors : [],
                    ] );
                }
                ?>
            </div>

        </div>

    </form>

</div>

<?php endif; ?>
