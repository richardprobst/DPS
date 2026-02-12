<?php
/**
 * Template: Booking V2 — Step 5b: Confirmation / Review
 *
 * Resumo final do agendamento com todos os dados para confirmação.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 *
 * @var array<string, mixed> $summary  Resumo com chaves: client, pets, services, datetime, extras, total.
 * @var string[]             $errors   Erros de validação.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$summary = $summary ?? [];
$errors  = $errors ?? [];

$client   = $summary['client'] ?? [];
$pets     = $summary['pets'] ?? [];
$services = $summary['services'] ?? [];
$datetime = $summary['datetime'] ?? [];
$extras   = $summary['extras'] ?? [];
$total    = $summary['total'] ?? '0,00';
?>

<fieldset class="dps-v2-booking__section dps-v2-booking__step-content" data-step="5b">
    <legend class="dps-v2-typescale-title-large">
        <?php esc_html_e( 'Confirmação', 'dps-frontend-addon' ); ?>
    </legend>

    <p class="dps-v2-typescale-body-medium dps-v2-color-on-surface-variant">
        <?php esc_html_e( 'Revise os dados do agendamento antes de confirmar.', 'dps-frontend-addon' ); ?>
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

    <div class="dps-v2-booking__summary">

        <?php // Cliente ?>
        <div class="dps-v2-card dps-v2-card--outlined dps-v2-booking__summary-section">
            <div class="dps-v2-card__content">
                <h3 class="dps-v2-typescale-title-medium"><?php esc_html_e( 'Cliente', 'dps-frontend-addon' ); ?></h3>
                <p class="dps-v2-typescale-body-large" id="dps-v2-booking-confirm-client-name">
                    <?php echo esc_html( $client['name'] ?? '' ); ?>
                </p>
                <p class="dps-v2-typescale-body-medium dps-v2-color-on-surface-variant" id="dps-v2-booking-confirm-client-phone">
                    <?php echo esc_html( $client['phone'] ?? '' ); ?>
                </p>
            </div>
        </div>

        <?php // Pets ?>
        <div class="dps-v2-card dps-v2-card--outlined dps-v2-booking__summary-section">
            <div class="dps-v2-card__content">
                <h3 class="dps-v2-typescale-title-medium"><?php esc_html_e( 'Pets', 'dps-frontend-addon' ); ?></h3>
                <ul class="dps-v2-booking__summary-list" id="dps-v2-booking-confirm-pets">
                    <?php if ( ! empty( $pets ) ) : ?>
                        <?php foreach ( $pets as $pet ) : ?>
                            <li class="dps-v2-typescale-body-medium"><?php echo esc_html( $pet['name'] ?? '' ); ?></li>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <li class="dps-v2-typescale-body-medium dps-v2-color-on-surface-variant">
                            <?php esc_html_e( 'Nenhum pet selecionado', 'dps-frontend-addon' ); ?>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <?php // Serviços ?>
        <div class="dps-v2-card dps-v2-card--outlined dps-v2-booking__summary-section">
            <div class="dps-v2-card__content">
                <h3 class="dps-v2-typescale-title-medium"><?php esc_html_e( 'Serviços', 'dps-frontend-addon' ); ?></h3>
                <ul class="dps-v2-booking__summary-list" id="dps-v2-booking-confirm-services">
                    <?php if ( ! empty( $services ) ) : ?>
                        <?php foreach ( $services as $service ) : ?>
                            <li class="dps-v2-typescale-body-medium">
                                <?php echo esc_html( $service['name'] ?? '' ); ?>
                                <span class="dps-v2-color-on-surface-variant">
                                    — <?php
                                    printf(
                                        /* translators: %s: service price */
                                        esc_html__( 'R$ %s', 'dps-frontend-addon' ),
                                        esc_html( $service['price'] ?? '0,00' )
                                    );
                                    ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <li class="dps-v2-typescale-body-medium dps-v2-color-on-surface-variant">
                            <?php esc_html_e( 'Nenhum serviço selecionado', 'dps-frontend-addon' ); ?>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <?php // Data e Horário ?>
        <div class="dps-v2-card dps-v2-card--outlined dps-v2-booking__summary-section">
            <div class="dps-v2-card__content">
                <h3 class="dps-v2-typescale-title-medium"><?php esc_html_e( 'Data e Horário', 'dps-frontend-addon' ); ?></h3>
                <p class="dps-v2-typescale-body-large" id="dps-v2-booking-confirm-date">
                    <?php echo esc_html( $datetime['date'] ?? '' ); ?>
                </p>
                <p class="dps-v2-typescale-body-medium" id="dps-v2-booking-confirm-time">
                    <?php echo esc_html( $datetime['time'] ?? '' ); ?>
                </p>
                <?php if ( ! empty( $datetime['type_label'] ) ) : ?>
                    <p class="dps-v2-typescale-body-medium dps-v2-color-on-surface-variant" id="dps-v2-booking-confirm-type">
                        <?php echo esc_html( $datetime['type_label'] ); ?>
                    </p>
                <?php endif; ?>
                <?php if ( ! empty( $datetime['notes'] ) ) : ?>
                    <p class="dps-v2-typescale-body-medium dps-v2-color-on-surface-variant">
                        <strong><?php esc_html_e( 'Obs:', 'dps-frontend-addon' ); ?></strong>
                        <?php echo esc_html( $datetime['notes'] ); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <?php // Extras (TaxiDog / Tosa) ?>
        <?php if ( ! empty( $extras['taxidog'] ) || ! empty( $extras['tosa'] ) ) : ?>
            <div class="dps-v2-card dps-v2-card--outlined dps-v2-booking__summary-section">
                <div class="dps-v2-card__content">
                    <h3 class="dps-v2-typescale-title-medium"><?php esc_html_e( 'Adicionais', 'dps-frontend-addon' ); ?></h3>
                    <ul class="dps-v2-booking__summary-list" id="dps-v2-booking-confirm-extras">
                        <?php if ( ! empty( $extras['taxidog'] ) ) : ?>
                            <li class="dps-v2-typescale-body-medium">
                                <?php
                                printf(
                                    /* translators: %s: taxidog price */
                                    esc_html__( 'TaxiDog — R$ %s', 'dps-frontend-addon' ),
                                    esc_html( $extras['taxidog']['price'] ?? '0,00' )
                                );
                                ?>
                            </li>
                        <?php endif; ?>
                        <?php if ( ! empty( $extras['tosa'] ) ) : ?>
                            <li class="dps-v2-typescale-body-medium">
                                <?php
                                printf(
                                    /* translators: 1: tosa price, 2: tosa occurrences */
                                    esc_html__( 'Tosa — R$ %1$s (%2$dx)', 'dps-frontend-addon' ),
                                    esc_html( $extras['tosa']['price'] ?? '30,00' ),
                                    (int) ( $extras['tosa']['occurrence'] ?? 1 )
                                );
                                ?>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <?php // Total ?>
        <div class="dps-v2-card dps-v2-card--elevated dps-v2-booking__summary-total">
            <div class="dps-v2-card__content">
                <span class="dps-v2-typescale-title-medium"><?php esc_html_e( 'Total', 'dps-frontend-addon' ); ?></span>
                <span class="dps-v2-typescale-headline-medium" id="dps-v2-booking-confirm-total">
                    <?php
                    printf(
                        /* translators: %s: total value */
                        esc_html__( 'R$ %s', 'dps-frontend-addon' ),
                        esc_html( $total )
                    );
                    ?>
                </span>
            </div>
        </div>

    </div>

    <?php // Hidden inputs com dados de todos os steps ?>
    <div class="dps-v2-booking__confirm-hidden-fields">
        <input type="hidden" name="confirm_client_id" value="<?php echo esc_attr( (string) ( $client['id'] ?? 0 ) ); ?>" />
        <?php if ( ! empty( $pets ) ) : ?>
            <?php foreach ( $pets as $pet ) : ?>
                <input type="hidden" name="confirm_pet_ids[]" value="<?php echo esc_attr( (string) ( $pet['id'] ?? 0 ) ); ?>" />
            <?php endforeach; ?>
        <?php endif; ?>
        <?php if ( ! empty( $services ) ) : ?>
            <?php foreach ( $services as $service ) : ?>
                <input type="hidden" name="confirm_service_ids[]" value="<?php echo esc_attr( (string) ( $service['id'] ?? 0 ) ); ?>" />
                <input type="hidden" name="confirm_service_prices[]" value="<?php echo esc_attr( (string) ( $service['price'] ?? '0' ) ); ?>" />
            <?php endforeach; ?>
        <?php endif; ?>
        <input type="hidden" name="confirm_appointment_date" value="<?php echo esc_attr( $datetime['date_raw'] ?? '' ); ?>" />
        <input type="hidden" name="confirm_appointment_time" value="<?php echo esc_attr( $datetime['time_raw'] ?? '' ); ?>" />
        <input type="hidden" name="confirm_appointment_type" value="<?php echo esc_attr( $datetime['type'] ?? 'simple' ); ?>" />
        <input type="hidden" name="confirm_appointment_notes" value="<?php echo esc_attr( $datetime['notes'] ?? '' ); ?>" />
        <input type="hidden" name="confirm_taxidog" value="<?php echo esc_attr( ! empty( $extras['taxidog'] ) ? '1' : '0' ); ?>" />
        <input type="hidden" name="confirm_taxidog_price" value="<?php echo esc_attr( (string) ( $extras['taxidog']['price'] ?? '0' ) ); ?>" />
        <input type="hidden" name="confirm_tosa" value="<?php echo esc_attr( ! empty( $extras['tosa'] ) ? '1' : '0' ); ?>" />
        <input type="hidden" name="confirm_tosa_price" value="<?php echo esc_attr( (string) ( $extras['tosa']['price'] ?? '0' ) ); ?>" />
        <input type="hidden" name="confirm_tosa_occurrence" value="<?php echo esc_attr( (string) ( $extras['tosa']['occurrence'] ?? 1 ) ); ?>" />
        <input type="hidden" name="confirm_total" value="<?php echo esc_attr( (string) $total ); ?>" />
    </div>

    <?php // Navegação ?>
    <div class="dps-v2-form-actions dps-v2-booking__nav">
        <button
            type="button"
            class="dps-v2-button dps-v2-button--secondary dps-v2-booking__prev"
            data-prev-step="5a"
        >
            <span class="dps-v2-button__text"><?php esc_html_e( 'Anterior', 'dps-frontend-addon' ); ?></span>
        </button>
        <button
            type="submit"
            class="dps-v2-button dps-v2-button--primary dps-v2-button--loading"
            data-loading="true"
            name="dps_booking_confirm"
            value="1"
        >
            <span class="dps-v2-button__text"><?php esc_html_e( 'Confirmar Agendamento', 'dps-frontend-addon' ); ?></span>
            <span class="dps-v2-button__loader" aria-hidden="true"></span>
        </button>
    </div>

</fieldset>
