<?php
/**
 * Template: Booking V2 — Step 3: Service Selection
 *
 * Seleção de serviços com preços e total dinâmico via AJAX.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 *
 * @var int[]    $service_ids  IDs dos serviços selecionados (sticky).
 * @var string[] $errors       Erros de validação.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$service_ids = $service_ids ?? [];
$errors      = $errors ?? [];
?>

<fieldset class="dps-v2-booking__section dps-v2-booking__step-content" data-step="3">
    <legend class="dps-v2-typescale-title-large">
        <?php esc_html_e( 'Selecionar Serviços', 'dps-frontend-addon' ); ?>
    </legend>

    <p class="dps-v2-typescale-body-medium dps-v2-color-on-surface-variant">
        <?php esc_html_e( 'Escolha os serviços desejados.', 'dps-frontend-addon' ); ?>
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

        <?php // Lista de serviços (preenchida via AJAX) ?>
        <div
            id="dps-v2-booking-service-list"
            class="dps-v2-booking__service-list"
            role="group"
            aria-label="<?php esc_attr_e( 'Lista de serviços', 'dps-frontend-addon' ); ?>"
            data-action="dps_booking_get_services"
        >
            <?php // Cards de serviços serão inseridos aqui via AJAX.
            // Cada card segue o template:
            // <label class="dps-v2-card dps-v2-card--outlined dps-v2-booking__service-card">
            //     <input type="checkbox" name="service_ids[]" value="{id}" class="dps-v2-booking__service-checkbox"
            //            data-price="{price}" />
            //     <input type="hidden" name="service_prices[]" value="{price}" disabled />
            //     <div class="dps-v2-card__content">
            //         <span class="dps-v2-typescale-body-large">{nome}</span>
            //         <span class="dps-v2-typescale-body-medium dps-v2-color-on-surface-variant">R$ {preço}</span>
            //     </div>
            // </label>
            ?>
            <p class="dps-v2-booking__service-list-empty dps-v2-typescale-body-medium dps-v2-color-on-surface-variant">
                <?php esc_html_e( 'Carregando serviços…', 'dps-frontend-addon' ); ?>
            </p>
        </div>

        <?php // Total dinâmico ?>
        <div class="dps-v2-booking__service-total dps-v2-card dps-v2-card--elevated">
            <div class="dps-v2-card__content">
                <span class="dps-v2-typescale-title-medium">
                    <?php esc_html_e( 'Total:', 'dps-frontend-addon' ); ?>
                </span>
                <span id="dps-v2-booking-service-total-value" class="dps-v2-typescale-headline-small">
                    <?php echo esc_html( 'R$ 0,00' ); ?>
                </span>
            </div>
        </div>

    </div>

    <?php // Navegação ?>
    <div class="dps-v2-form-actions dps-v2-booking__nav">
        <button
            type="button"
            class="dps-v2-button dps-v2-button--secondary dps-v2-booking__prev"
            data-prev-step="2"
        >
            <span class="dps-v2-button__text"><?php esc_html_e( 'Anterior', 'dps-frontend-addon' ); ?></span>
        </button>
        <button
            type="button"
            class="dps-v2-button dps-v2-button--primary dps-v2-booking__next"
            data-next-step="4"
        >
            <span class="dps-v2-button__text"><?php esc_html_e( 'Próximo', 'dps-frontend-addon' ); ?></span>
        </button>
    </div>

</fieldset>
