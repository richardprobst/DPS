<?php
/**
 * Template: Booking V2 — Step 5a: Optional Extras (TaxiDog + Tosa)
 *
 * Adicionais opcionais do agendamento.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 *
 * @var string   $appointment_type            Tipo: simple|subscription|past.
 * @var bool     $appointment_taxidog         Se TaxiDog está ativo.
 * @var string   $appointment_taxidog_price   Preço do TaxiDog.
 * @var bool     $appointment_tosa            Se Tosa está ativa.
 * @var string   $appointment_tosa_price      Preço da Tosa.
 * @var int      $appointment_tosa_occurrence Ocorrências da Tosa (1-10).
 * @var string[] $errors                      Erros de validação.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$appointment_type            = $appointment_type ?? 'simple';
$appointment_taxidog         = $appointment_taxidog ?? false;
$appointment_taxidog_price   = $appointment_taxidog_price ?? '0';
$appointment_tosa            = $appointment_tosa ?? false;
$appointment_tosa_price      = $appointment_tosa_price ?? '30';
$appointment_tosa_occurrence = $appointment_tosa_occurrence ?? 1;
$errors                      = $errors ?? [];
?>

<fieldset class="dps-v2-booking__section dps-v2-booking__step-content" data-step="5a">
    <legend class="dps-v2-typescale-title-large">
        <?php esc_html_e( 'Adicionais', 'dps-frontend-addon' ); ?>
    </legend>

    <p class="dps-v2-typescale-body-medium dps-v2-color-on-surface-variant">
        <?php esc_html_e( 'Selecione os serviços adicionais, se necessário.', 'dps-frontend-addon' ); ?>
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

        <?php // TaxiDog ?>
        <div class="dps-v2-card dps-v2-card--outlined dps-v2-booking__extra-card">
            <div class="dps-v2-card__content">
                <div class="dps-v2-field dps-v2-field--checkbox">
                    <label for="dps-v2-booking-taxidog" class="dps-v2-field__checkbox-label">
                        <input
                            type="checkbox"
                            id="dps-v2-booking-taxidog"
                            name="appointment_taxidog"
                            value="1"
                            class="dps-v2-field__checkbox dps-v2-booking__extra-toggle"
                            data-target="dps-v2-booking-taxidog-fields"
                            <?php checked( $appointment_taxidog ); ?>
                        />
                        <span class="dps-v2-field__checkbox-text dps-v2-typescale-title-medium">
                            <?php esc_html_e( 'TaxiDog', 'dps-frontend-addon' ); ?>
                        </span>
                    </label>
                </div>

                <div id="dps-v2-booking-taxidog-fields" class="dps-v2-booking__extra-fields" <?php echo ! $appointment_taxidog ? 'style="display: none;"' : ''; ?>>
                    <div class="dps-v2-field dps-v2-field--currency">
                        <label for="dps-v2-booking-taxidog-price" class="dps-v2-field__label">
                            <?php esc_html_e( 'Valor do TaxiDog (R$)', 'dps-frontend-addon' ); ?>
                        </label>
                        <input
                            type="text"
                            id="dps-v2-booking-taxidog-price"
                            name="appointment_taxidog_price"
                            value="<?php echo esc_attr( $appointment_taxidog_price ); ?>"
                            class="dps-v2-field__input"
                            inputmode="decimal"
                            placeholder="0,00"
                        />
                    </div>
                </div>
            </div>
        </div>

        <?php // Tosa (visível apenas para tipo assinatura) ?>
        <div
            id="dps-v2-booking-tosa-card"
            class="dps-v2-card dps-v2-card--outlined dps-v2-booking__extra-card dps-v2-booking__extra-card--tosa"
            <?php echo 'subscription' !== $appointment_type ? 'style="display: none;"' : ''; ?>
        >
            <div class="dps-v2-card__content">
                <div class="dps-v2-field dps-v2-field--checkbox">
                    <label for="dps-v2-booking-tosa" class="dps-v2-field__checkbox-label">
                        <input
                            type="checkbox"
                            id="dps-v2-booking-tosa"
                            name="appointment_tosa"
                            value="1"
                            class="dps-v2-field__checkbox dps-v2-booking__extra-toggle"
                            data-target="dps-v2-booking-tosa-fields"
                            <?php checked( $appointment_tosa ); ?>
                        />
                        <span class="dps-v2-field__checkbox-text dps-v2-typescale-title-medium">
                            <?php esc_html_e( 'Tosa', 'dps-frontend-addon' ); ?>
                        </span>
                    </label>
                </div>

                <div id="dps-v2-booking-tosa-fields" class="dps-v2-booking__extra-fields" <?php echo ! $appointment_tosa ? 'style="display: none;"' : ''; ?>>
                    <div class="dps-v2-booking__extra-fields-row">

                        <div class="dps-v2-field dps-v2-field--currency">
                            <label for="dps-v2-booking-tosa-price" class="dps-v2-field__label">
                                <?php esc_html_e( 'Valor da Tosa (R$)', 'dps-frontend-addon' ); ?>
                            </label>
                            <input
                                type="text"
                                id="dps-v2-booking-tosa-price"
                                name="appointment_tosa_price"
                                value="<?php echo esc_attr( $appointment_tosa_price ); ?>"
                                class="dps-v2-field__input"
                                inputmode="decimal"
                                placeholder="30,00"
                            />
                        </div>

                        <div class="dps-v2-field dps-v2-field--select">
                            <label for="dps-v2-booking-tosa-occurrence" class="dps-v2-field__label">
                                <?php esc_html_e( 'Ocorrências', 'dps-frontend-addon' ); ?>
                            </label>
                            <select
                                id="dps-v2-booking-tosa-occurrence"
                                name="appointment_tosa_occurrence"
                                class="dps-v2-field__select"
                            >
                                <?php for ( $i = 1; $i <= 10; $i++ ) : ?>
                                    <option value="<?php echo esc_attr( (string) $i ); ?>" <?php selected( (int) $appointment_tosa_occurrence, $i ); ?>>
                                        <?php echo esc_html( (string) $i ); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                    </div>
                </div>
            </div>
        </div>

    </div>

    <?php // Navegação ?>
    <div class="dps-v2-form-actions dps-v2-booking__nav">
        <button
            type="button"
            class="dps-v2-button dps-v2-button--secondary dps-v2-booking__prev"
            data-prev-step="4"
        >
            <span class="dps-v2-button__text"><?php esc_html_e( 'Anterior', 'dps-frontend-addon' ); ?></span>
        </button>
        <button
            type="button"
            class="dps-v2-button dps-v2-button--primary dps-v2-booking__next"
            data-next-step="5b"
        >
            <span class="dps-v2-button__text"><?php esc_html_e( 'Próximo', 'dps-frontend-addon' ); ?></span>
        </button>
    </div>

</fieldset>
