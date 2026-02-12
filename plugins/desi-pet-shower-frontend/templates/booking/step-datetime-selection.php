<?php
/**
 * Template: Booking V2 — Step 4: Date/Time Selection
 *
 * Seleção de data, horário (via AJAX de slots) e tipo de agendamento.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 *
 * @var string   $appointment_date  Data selecionada (Y-m-d, sticky).
 * @var string   $appointment_time  Horário selecionado (H:i, sticky).
 * @var string   $appointment_type  Tipo: simple|subscription|past.
 * @var string[] $errors            Erros de validação.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$appointment_date = $appointment_date ?? '';
$appointment_time = $appointment_time ?? '';
$appointment_type = $appointment_type ?? 'simple';
$errors           = $errors ?? [];

$today = wp_date( 'Y-m-d' );

$typeOptions = [
    'simple'       => __( 'Agendamento Simples', 'dps-frontend-addon' ),
    'subscription' => __( 'Assinatura', 'dps-frontend-addon' ),
    'past'         => __( 'Registro Retroativo', 'dps-frontend-addon' ),
];
?>

<fieldset class="dps-v2-booking__section dps-v2-booking__step-content" data-step="4">
    <legend class="dps-v2-typescale-title-large">
        <?php esc_html_e( 'Data e Horário', 'dps-frontend-addon' ); ?>
    </legend>

    <p class="dps-v2-typescale-body-medium dps-v2-color-on-surface-variant">
        <?php esc_html_e( 'Escolha a data, horário e tipo de agendamento.', 'dps-frontend-addon' ); ?>
    </p>

    <?php // Erros ?>
    <?php if ( ! empty( $errors ) ) : ?>
        <div class="dps-v2-alert dps-v2-alert--error" role="alert">
            <div class="dps-v2-alert__content">
                <?php foreach ( $errors as $err ) : ?>
                    <p><?php echo esc_html( $err ); ?></p>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="dps-v2-booking__fields">

        <?php // Tipo de agendamento ?>
        <div class="dps-v2-field dps-v2-field--radio-group">
            <span class="dps-v2-field__label" id="dps-v2-booking-type-label">
                <?php esc_html_e( 'Tipo de agendamento', 'dps-frontend-addon' ); ?>
                <span class="dps-v2-field__required" aria-label="<?php esc_attr_e( 'Obrigatório', 'dps-frontend-addon' ); ?>">*</span>
            </span>
            <div class="dps-v2-booking__type-options" role="radiogroup" aria-labelledby="dps-v2-booking-type-label">
                <?php foreach ( $typeOptions as $val => $label ) : ?>
                    <label class="dps-v2-field__radio-label">
                        <input
                            type="radio"
                            name="appointment_type"
                            value="<?php echo esc_attr( $val ); ?>"
                            class="dps-v2-field__radio dps-v2-booking__type-radio"
                            <?php checked( $appointment_type, $val ); ?>
                        />
                        <span class="dps-v2-field__radio-text"><?php echo esc_html( $label ); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <?php // Data ?>
        <div class="dps-v2-field dps-v2-field--date">
            <label for="dps-v2-booking-date" class="dps-v2-field__label">
                <?php esc_html_e( 'Data', 'dps-frontend-addon' ); ?>
                <span class="dps-v2-field__required" aria-label="<?php esc_attr_e( 'Obrigatório', 'dps-frontend-addon' ); ?>">*</span>
            </label>
            <input
                type="date"
                id="dps-v2-booking-date"
                name="appointment_date"
                value="<?php echo esc_attr( $appointment_date ); ?>"
                class="dps-v2-field__input dps-v2-booking__date-input"
                required
                min="<?php echo 'past' !== $appointment_type ? esc_attr( $today ) : ''; ?>"
                data-today="<?php echo esc_attr( $today ); ?>"
            />
        </div>

        <?php // Slots de horário (preenchido via AJAX) ?>
        <div class="dps-v2-field dps-v2-field--slots">
            <span class="dps-v2-field__label" id="dps-v2-booking-time-label">
                <?php esc_html_e( 'Horário', 'dps-frontend-addon' ); ?>
                <span class="dps-v2-field__required" aria-label="<?php esc_attr_e( 'Obrigatório', 'dps-frontend-addon' ); ?>">*</span>
            </span>
            <div
                id="dps-v2-booking-time-slots"
                class="dps-v2-booking__time-slots"
                role="radiogroup"
                aria-labelledby="dps-v2-booking-time-label"
                data-action="dps_booking_get_slots"
            >
                <?php // Slots de horário inseridos via AJAX.
                // Cada slot segue o template:
                // <label class="dps-v2-booking__time-slot">
                //     <input type="radio" name="appointment_time_slot" value="{HH:MM}"
                //            class="dps-v2-booking__slot-radio" disabled="{se indisponível}" />
                //     <span class="dps-v2-booking__slot-label">{HH:MM}</span>
                // </label>
                ?>
                <p class="dps-v2-booking__time-slots-empty dps-v2-typescale-body-medium dps-v2-color-on-surface-variant">
                    <?php esc_html_e( 'Selecione uma data para ver os horários disponíveis.', 'dps-frontend-addon' ); ?>
                </p>
            </div>
            <input type="hidden" id="dps-v2-booking-time" name="appointment_time" value="<?php echo esc_attr( $appointment_time ); ?>" />
        </div>

        <?php // Observações ?>
        <div class="dps-v2-field dps-v2-field--textarea dps-v2-field--full-width">
            <label for="dps-v2-booking-notes" class="dps-v2-field__label">
                <?php esc_html_e( 'Observações', 'dps-frontend-addon' ); ?>
            </label>
            <textarea
                id="dps-v2-booking-notes"
                name="appointment_notes"
                class="dps-v2-field__textarea"
                rows="3"
                placeholder="<?php echo esc_attr__( 'Informações adicionais sobre o atendimento…', 'dps-frontend-addon' ); ?>"
            ></textarea>
        </div>

    </div>

    <?php // Navegação ?>
    <div class="dps-v2-form-actions dps-v2-booking__nav">
        <button
            type="button"
            class="dps-v2-button dps-v2-button--secondary dps-v2-booking__prev"
            data-prev-step="3"
        >
            <span class="dps-v2-button__text"><?php esc_html_e( 'Anterior', 'dps-frontend-addon' ); ?></span>
        </button>
        <button
            type="button"
            class="dps-v2-button dps-v2-button--primary dps-v2-booking__next"
            data-next-step="5"
        >
            <span class="dps-v2-button__text"><?php esc_html_e( 'Próximo', 'dps-frontend-addon' ); ?></span>
        </button>
    </div>

</fieldset>
