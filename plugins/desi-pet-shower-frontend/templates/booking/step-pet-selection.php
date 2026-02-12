<?php
/**
 * Template: Booking V2 — Step 2: Pet Selection
 *
 * Seleção de pets do cliente via AJAX. Suporte a múltipla seleção.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 *
 * @var int[]    $pet_ids    IDs dos pets selecionados (sticky).
 * @var int      $client_id  ID do cliente selecionado.
 * @var string[] $errors     Erros de validação.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$pet_ids   = $pet_ids ?? [];
$client_id = $client_id ?? 0;
$errors    = $errors ?? [];
?>

<fieldset class="dps-v2-booking__section dps-v2-booking__step-content" data-step="2">
    <legend class="dps-v2-typescale-title-large">
        <?php esc_html_e( 'Selecionar Pets', 'dps-frontend-addon' ); ?>
    </legend>

    <p class="dps-v2-typescale-body-medium dps-v2-color-on-surface-variant">
        <?php esc_html_e( 'Selecione os pets que serão atendidos.', 'dps-frontend-addon' ); ?>
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

        <?php // Lista de pets (preenchida via AJAX) ?>
        <div
            id="dps-v2-booking-pet-list"
            class="dps-v2-booking__pet-list"
            role="group"
            aria-label="<?php esc_attr_e( 'Lista de pets', 'dps-frontend-addon' ); ?>"
            data-action="dps_booking_get_pets"
            data-client-id="<?php echo esc_attr( (string) $client_id ); ?>"
        >
            <?php // Cards de pets serão inseridos aqui via AJAX.
            // Cada card segue o template:
            // <label class="dps-v2-card dps-v2-card--outlined dps-v2-booking__pet-card">
            //     <input type="checkbox" name="pet_ids[]" value="{id}" class="dps-v2-booking__pet-checkbox" />
            //     <div class="dps-v2-card__content">
            //         <span class="dps-v2-typescale-body-large">{nome}</span>
            //         <span class="dps-v2-typescale-body-medium dps-v2-color-on-surface-variant">{espécie} · {raça} · {porte}</span>
            //     </div>
            // </label>
            ?>
            <p class="dps-v2-booking__pet-list-empty dps-v2-typescale-body-medium dps-v2-color-on-surface-variant">
                <?php esc_html_e( 'Carregando pets do cliente…', 'dps-frontend-addon' ); ?>
            </p>
        </div>

        <?php // Paginação ?>
        <div id="dps-v2-booking-pet-pagination" class="dps-v2-booking__pagination" style="display: none;">
            <button
                type="button"
                class="dps-v2-button dps-v2-button--secondary dps-v2-booking__load-more"
                data-target="pet-list"
            >
                <span class="dps-v2-button__text"><?php esc_html_e( 'Carregar mais', 'dps-frontend-addon' ); ?></span>
                <span class="dps-v2-button__loader" aria-hidden="true"></span>
            </button>
        </div>

    </div>

    <?php // Navegação ?>
    <div class="dps-v2-form-actions dps-v2-booking__nav">
        <button
            type="button"
            class="dps-v2-button dps-v2-button--secondary dps-v2-booking__prev"
            data-prev-step="1"
        >
            <span class="dps-v2-button__text"><?php esc_html_e( 'Anterior', 'dps-frontend-addon' ); ?></span>
        </button>
        <button
            type="button"
            class="dps-v2-button dps-v2-button--primary dps-v2-booking__next"
            data-next-step="3"
        >
            <span class="dps-v2-button__text"><?php esc_html_e( 'Próximo', 'dps-frontend-addon' ); ?></span>
        </button>
    </div>

</fieldset>
