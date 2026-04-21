<?php
/**
 * Template: Signature registration client data.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 *
 * @var array<string, mixed>  $data
 * @var array<string, string> $field_errors
 * @var string                $google_api_key
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$data           = $data ?? [];
$field_errors   = $field_errors ?? [];
$google_api_key = $google_api_key ?? '';
?>

<section class="dps-signature-section" id="dps-registration-section-client">
    <div class="dps-signature-section__header">
        <p class="dps-signature-section__eyebrow"><?php esc_html_e( 'Etapa 1', 'dps-frontend-addon' ); ?></p>
        <h2 class="dps-signature-section__title"><?php esc_html_e( 'Dados do tutor', 'dps-frontend-addon' ); ?></h2>
        <p class="dps-signature-section__description"><?php esc_html_e( 'Informe o contato principal do tutor. O sistema usa estes dados para confirmar cadastro, agendamento e comunicações do atendimento.', 'dps-frontend-addon' ); ?></p>
    </div>

    <div class="dps-signature-grid dps-signature-grid--2">
        <div class="dps-signature-field dps-signature-field--full">
            <label class="dps-signature-field__label" for="dps-registration-client-name">
                <?php esc_html_e( 'Nome completo', 'dps-frontend-addon' ); ?>
                <span class="dps-signature-field__required" aria-hidden="true">*</span>
            </label>
            <input
                id="dps-registration-client-name"
                type="text"
                name="client_name"
                value="<?php echo esc_attr( $data['client_name'] ?? '' ); ?>"
                autocomplete="name"
                required
                aria-required="true"
                <?php echo ! empty( $field_errors['client_name'] ) ? 'aria-invalid="true" aria-describedby="dps-registration-client-name-error"' : ''; ?>
            />
            <?php if ( ! empty( $field_errors['client_name'] ) ) : ?>
                <p id="dps-registration-client-name-error" class="dps-signature-field__error" role="alert"><?php echo esc_html( $field_errors['client_name'] ); ?></p>
            <?php endif; ?>
        </div>

        <div class="dps-signature-field">
            <label class="dps-signature-field__label" for="dps-registration-client-email">
                <?php esc_html_e( 'E-mail', 'dps-frontend-addon' ); ?>
                <span class="dps-signature-field__required" aria-hidden="true">*</span>
            </label>
            <input
                id="dps-registration-client-email"
                type="email"
                name="client_email"
                value="<?php echo esc_attr( $data['client_email'] ?? '' ); ?>"
                autocomplete="email"
                inputmode="email"
                spellcheck="false"
                required
                aria-required="true"
                <?php echo ! empty( $field_errors['client_email'] ) ? 'aria-invalid="true" aria-describedby="dps-registration-client-email-error"' : ''; ?>
            />
            <?php if ( ! empty( $field_errors['client_email'] ) ) : ?>
                <p id="dps-registration-client-email-error" class="dps-signature-field__error" role="alert"><?php echo esc_html( $field_errors['client_email'] ); ?></p>
            <?php else : ?>
                <p class="dps-signature-field__helper"><?php esc_html_e( 'Usado para confirmação e comunicações do atendimento.', 'dps-frontend-addon' ); ?></p>
            <?php endif; ?>
        </div>

        <div class="dps-signature-field">
            <label class="dps-signature-field__label" for="dps-registration-client-phone">
                <?php esc_html_e( 'Telefone / WhatsApp', 'dps-frontend-addon' ); ?>
                <span class="dps-signature-field__required" aria-hidden="true">*</span>
            </label>
            <input
                id="dps-registration-client-phone"
                type="tel"
                name="client_phone"
                value="<?php echo esc_attr( $data['client_phone'] ?? '' ); ?>"
                placeholder="<?php echo esc_attr__( '(11) 99999-9999…', 'dps-frontend-addon' ); ?>"
                autocomplete="tel"
                inputmode="tel"
                data-dps-mask="phone"
                required
                aria-required="true"
                <?php echo ! empty( $field_errors['client_phone'] ) ? 'aria-invalid="true" aria-describedby="dps-registration-client-phone-error"' : ''; ?>
            />
            <?php if ( ! empty( $field_errors['client_phone'] ) ) : ?>
                <p id="dps-registration-client-phone-error" class="dps-signature-field__error" role="alert"><?php echo esc_html( $field_errors['client_phone'] ); ?></p>
            <?php endif; ?>
        </div>

        <div class="dps-signature-field">
            <label class="dps-signature-field__label" for="dps-registration-client-cpf"><?php esc_html_e( 'CPF', 'dps-frontend-addon' ); ?></label>
            <input
                id="dps-registration-client-cpf"
                type="text"
                name="client_cpf"
                value="<?php echo esc_attr( $data['client_cpf'] ?? '' ); ?>"
                placeholder="<?php echo esc_attr__( '000.000.000-00…', 'dps-frontend-addon' ); ?>"
                inputmode="numeric"
                autocomplete="off"
                data-dps-mask="cpf"
                <?php echo ! empty( $field_errors['client_cpf'] ) ? 'aria-invalid="true" aria-describedby="dps-registration-client-cpf-error"' : ''; ?>
            />
            <?php if ( ! empty( $field_errors['client_cpf'] ) ) : ?>
                <p id="dps-registration-client-cpf-error" class="dps-signature-field__error" role="alert"><?php echo esc_html( $field_errors['client_cpf'] ); ?></p>
            <?php else : ?>
                <p class="dps-signature-field__helper"><?php esc_html_e( 'Opcional, mas recomendado para evitar duplicidades.', 'dps-frontend-addon' ); ?></p>
            <?php endif; ?>
        </div>

        <div class="dps-signature-field">
            <label class="dps-signature-field__label" for="dps-registration-client-birth"><?php esc_html_e( 'Data de nascimento', 'dps-frontend-addon' ); ?></label>
            <input
                id="dps-registration-client-birth"
                type="date"
                name="client_birth"
                value="<?php echo esc_attr( $data['client_birth'] ?? '' ); ?>"
                autocomplete="bday"
            />
        </div>

        <div class="dps-signature-field">
            <label class="dps-signature-field__label" for="dps-registration-client-instagram"><?php esc_html_e( 'Instagram', 'dps-frontend-addon' ); ?></label>
            <input
                id="dps-registration-client-instagram"
                type="text"
                name="client_instagram"
                value="<?php echo esc_attr( $data['client_instagram'] ?? '' ); ?>"
                placeholder="<?php echo esc_attr__( '@seuusuario…', 'dps-frontend-addon' ); ?>"
                autocomplete="off"
                spellcheck="false"
            />
        </div>

        <div class="dps-signature-field">
            <label class="dps-signature-field__label" for="dps-registration-client-facebook"><?php esc_html_e( 'Facebook', 'dps-frontend-addon' ); ?></label>
            <input
                id="dps-registration-client-facebook"
                type="text"
                name="client_facebook"
                value="<?php echo esc_attr( $data['client_facebook'] ?? '' ); ?>"
                placeholder="<?php echo esc_attr__( 'Nome do perfil…', 'dps-frontend-addon' ); ?>"
                autocomplete="off"
            />
        </div>

        <div class="dps-signature-field dps-signature-field--full">
            <label class="dps-signature-field__label" for="dps-registration-client-address"><?php esc_html_e( 'Endereço completo', 'dps-frontend-addon' ); ?></label>
            <textarea
                id="dps-registration-client-address"
                name="client_address"
                rows="3"
                autocomplete="street-address"
                data-dps-address-autocomplete
                data-dps-google-api-key="<?php echo esc_attr( $google_api_key ); ?>"
                data-dps-lat-target="dps-registration-client-lat"
                data-dps-lng-target="dps-registration-client-lng"
                placeholder="<?php echo esc_attr__( 'Rua, número, bairro, cidade - UF…', 'dps-frontend-addon' ); ?>"
            ><?php echo esc_textarea( $data['client_address'] ?? '' ); ?></textarea>
            <p class="dps-signature-field__helper"><?php esc_html_e( 'Se o autocomplete estiver disponível, escolha o endereço sugerido para preencher melhor a localização.', 'dps-frontend-addon' ); ?></p>
            <input type="hidden" name="client_lat" id="dps-registration-client-lat" value="<?php echo esc_attr( $data['client_lat'] ?? '' ); ?>" />
            <input type="hidden" name="client_lng" id="dps-registration-client-lng" value="<?php echo esc_attr( $data['client_lng'] ?? '' ); ?>" />
        </div>

        <div class="dps-signature-field dps-signature-field--full">
            <label class="dps-signature-field__label" for="dps-registration-client-referral"><?php esc_html_e( 'Como conheceu o pet shop?', 'dps-frontend-addon' ); ?></label>
            <input
                id="dps-registration-client-referral"
                type="text"
                name="client_referral"
                value="<?php echo esc_attr( $data['client_referral'] ?? '' ); ?>"
                placeholder="<?php echo esc_attr__( 'Google, indicação, Instagram…', 'dps-frontend-addon' ); ?>"
                autocomplete="off"
            />
        </div>

        <div class="dps-signature-field dps-signature-field--full">
            <label class="dps-signature-check" for="dps-registration-client-photo-auth">
                <input type="hidden" name="client_photo_auth" value="" />
                <input
                    id="dps-registration-client-photo-auth"
                    type="checkbox"
                    name="client_photo_auth"
                    value="1"
                    <?php checked( ! empty( $data['client_photo_auth'] ) ); ?>
                />
                <span><?php esc_html_e( 'Autorizo a publicação de fotos do pet nas redes sociais do estabelecimento.', 'dps-frontend-addon' ); ?></span>
            </label>
        </div>
    </div>
</section>
