<?php
/**
 * Template: Booking V2 — Step 1: Client Selection
 *
 * Busca e seleção de cliente por telefone via AJAX.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 *
 * @var int                   $client_id   ID do cliente selecionado (sticky).
 * @var array<string, mixed>  $client_data Dados do cliente selecionado.
 * @var string[]              $errors      Erros de validação.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$client_id   = $client_id ?? 0;
$client_data = $client_data ?? [];
$errors      = $errors ?? [];
?>

<fieldset class="dps-v2-booking__section dps-v2-booking__step-content" data-step="1">
    <legend class="dps-v2-typescale-title-large">
        <?php esc_html_e( 'Selecionar Cliente', 'dps-frontend-addon' ); ?>
    </legend>

    <p class="dps-v2-typescale-body-medium dps-v2-color-on-surface-variant">
        <?php esc_html_e( 'Busque o cliente pelo número de telefone.', 'dps-frontend-addon' ); ?>
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

        <?php // Busca por telefone ?>
        <div class="dps-v2-field dps-v2-field--phone">
            <label for="dps-v2-booking-search-phone" class="dps-v2-field__label">
                <?php esc_html_e( 'Telefone do cliente', 'dps-frontend-addon' ); ?>
                <span class="dps-v2-field__required" aria-label="<?php esc_attr_e( 'Obrigatório', 'dps-frontend-addon' ); ?>">*</span>
            </label>
            <div class="dps-v2-field__input-group">
                <input
                    type="tel"
                    id="dps-v2-booking-search-phone"
                    name="search_phone"
                    value=""
                    placeholder="<?php echo esc_attr__( '(11) 99999-9999', 'dps-frontend-addon' ); ?>"
                    class="dps-v2-field__input"
                    autocomplete="tel"
                    inputmode="tel"
                    aria-describedby="dps-v2-booking-search-phone-help"
                />
                <button
                    type="button"
                    id="dps-v2-booking-search-btn"
                    class="dps-v2-button dps-v2-button--secondary"
                    data-action="dps_booking_search_client"
                    aria-label="<?php esc_attr_e( 'Buscar cliente', 'dps-frontend-addon' ); ?>"
                >
                    <span class="dps-v2-button__text"><?php esc_html_e( 'Buscar', 'dps-frontend-addon' ); ?></span>
                    <span class="dps-v2-button__loader" aria-hidden="true"></span>
                </button>
            </div>
            <span id="dps-v2-booking-search-phone-help" class="dps-v2-field__helper">
                <?php esc_html_e( 'Digite o telefone e clique em Buscar', 'dps-frontend-addon' ); ?>
            </span>
        </div>

        <?php // Resultados da busca (preenchido via JS) ?>
        <div id="dps-v2-booking-search-results" class="dps-v2-booking__search-results" role="listbox" aria-label="<?php esc_attr_e( 'Resultados da busca', 'dps-frontend-addon' ); ?>" style="display: none;">
            <?php // Cards de clientes serão inseridos aqui via AJAX ?>
        </div>

        <?php // Cliente selecionado ?>
        <input type="hidden" id="dps-v2-booking-client-id" name="client_id" value="<?php echo esc_attr( (string) $client_id ); ?>" />

        <div id="dps-v2-booking-selected-client" class="dps-v2-booking__selected-client" <?php echo 0 === $client_id ? 'style="display: none;"' : ''; ?>>
            <div class="dps-v2-card dps-v2-card--outlined">
                <div class="dps-v2-card__content">
                    <span class="dps-v2-typescale-label-small dps-v2-color-on-surface-variant">
                        <?php esc_html_e( 'Cliente selecionado', 'dps-frontend-addon' ); ?>
                    </span>
                    <p class="dps-v2-typescale-body-large" id="dps-v2-booking-client-name">
                        <?php echo esc_html( $client_data['name'] ?? '' ); ?>
                    </p>
                    <p class="dps-v2-typescale-body-medium dps-v2-color-on-surface-variant" id="dps-v2-booking-client-phone">
                        <?php echo esc_html( $client_data['phone'] ?? '' ); ?>
                    </p>
                    <p class="dps-v2-typescale-body-medium dps-v2-color-on-surface-variant" id="dps-v2-booking-client-email">
                        <?php echo esc_html( $client_data['email'] ?? '' ); ?>
                    </p>
                </div>
            </div>
        </div>

    </div>

    <?php // Navegação ?>
    <div class="dps-v2-form-actions dps-v2-booking__nav">
        <button
            type="button"
            class="dps-v2-button dps-v2-button--primary dps-v2-booking__next"
            data-next-step="2"
        >
            <span class="dps-v2-button__text"><?php esc_html_e( 'Próximo', 'dps-frontend-addon' ); ?></span>
        </button>
    </div>

</fieldset>
