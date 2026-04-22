<?php
/**
 * Client create/edit form aligned to DPS Signature.
 *
 * Override path:
 * wp-content/themes/SEU_TEMA/dps-templates/forms/client-form.php
 *
 * @package DesiPetShower
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$edit_id    = isset( $edit_id ) ? (int) $edit_id : 0;
$editing    = isset( $editing ) ? $editing : null;
$meta       = isset( $meta ) && is_array( $meta ) ? $meta : [];
$api_key    = isset( $api_key ) ? (string) $api_key : '';
$name_value = $editing ? $editing->post_title : '';
$btn_text   = $edit_id ? esc_html__( 'Atualizar Cliente', 'desi-pet-shower' ) : esc_html__( 'Salvar Cliente', 'desi-pet-shower' );
?>

<form method="post" class="dps-form dps-signature-form">
    <input type="hidden" name="dps_action" value="save_client" />
    <?php wp_nonce_field( 'dps_action', 'dps_nonce_client_form' ); ?>
    <?php if ( $edit_id ) : ?>
        <input type="hidden" name="client_id" value="<?php echo esc_attr( (string) $edit_id ); ?>" />
    <?php endif; ?>

    <section class="dps-signature-section">
        <div class="dps-signature-section__header">
            <p class="dps-signature-section__eyebrow"><?php esc_html_e( 'Cadastro interno', 'desi-pet-shower' ); ?></p>
            <h2 class="dps-signature-section__title"><?php esc_html_e( 'Dados do tutor', 'desi-pet-shower' ); ?></h2>
        </div>

        <div class="dps-signature-grid dps-signature-grid--2">
            <div class="dps-signature-field dps-signature-field--full">
                <label class="dps-signature-field__label" for="dps-client-name">
                    <?php esc_html_e( 'Nome completo', 'desi-pet-shower' ); ?>
                    <span class="dps-signature-field__required" aria-hidden="true">*</span>
                </label>
                <input id="dps-client-name" type="text" name="client_name" value="<?php echo esc_attr( $name_value ); ?>" autocomplete="name" required />
            </div>

            <div class="dps-signature-field">
                <label class="dps-signature-field__label" for="dps-client-cpf"><?php esc_html_e( 'CPF', 'desi-pet-shower' ); ?></label>
                <input id="dps-client-cpf" type="text" name="client_cpf" value="<?php echo esc_attr( $meta['cpf'] ?? '' ); ?>" placeholder="<?php echo esc_attr__( '000.000.000-00', 'desi-pet-shower' ); ?>" inputmode="numeric" data-dps-mask="cpf" />
            </div>

            <div class="dps-signature-field">
                <label class="dps-signature-field__label" for="dps-client-birth"><?php esc_html_e( 'Data de nascimento', 'desi-pet-shower' ); ?></label>
                <input id="dps-client-birth" type="date" name="client_birth" value="<?php echo esc_attr( $meta['birth'] ?? '' ); ?>" autocomplete="bday" />
            </div>
        </div>
    </section>

    <section class="dps-signature-section">
        <div class="dps-signature-section__header">
            <p class="dps-signature-section__eyebrow"><?php esc_html_e( 'Contato', 'desi-pet-shower' ); ?></p>
            <h2 class="dps-signature-section__title"><?php esc_html_e( 'Canais de comunicação', 'desi-pet-shower' ); ?></h2>
        </div>

        <div class="dps-signature-grid dps-signature-grid--2">
            <div class="dps-signature-field">
                <label class="dps-signature-field__label" for="dps-client-phone">
                    <?php esc_html_e( 'Telefone / WhatsApp', 'desi-pet-shower' ); ?>
                    <span class="dps-signature-field__required" aria-hidden="true">*</span>
                </label>
                <input id="dps-client-phone" type="tel" name="client_phone" value="<?php echo esc_attr( $meta['phone'] ?? '' ); ?>" placeholder="<?php echo esc_attr__( '(00) 00000-0000', 'desi-pet-shower' ); ?>" autocomplete="tel" inputmode="tel" data-dps-mask="phone" required />
            </div>

            <div class="dps-signature-field">
                <label class="dps-signature-field__label" for="dps-client-email"><?php esc_html_e( 'E-mail', 'desi-pet-shower' ); ?></label>
                <input id="dps-client-email" type="email" name="client_email" value="<?php echo esc_attr( $meta['email'] ?? '' ); ?>" placeholder="<?php echo esc_attr__( 'seuemail@exemplo.com', 'desi-pet-shower' ); ?>" autocomplete="email" inputmode="email" />
            </div>

            <div class="dps-signature-field">
                <label class="dps-signature-field__label" for="dps-client-instagram"><?php esc_html_e( 'Instagram', 'desi-pet-shower' ); ?></label>
                <input id="dps-client-instagram" type="text" name="client_instagram" value="<?php echo esc_attr( $meta['instagram'] ?? '' ); ?>" placeholder="<?php echo esc_attr__( '@usuario', 'desi-pet-shower' ); ?>" autocomplete="off" />
            </div>

            <div class="dps-signature-field">
                <label class="dps-signature-field__label" for="dps-client-facebook"><?php esc_html_e( 'Facebook', 'desi-pet-shower' ); ?></label>
                <input id="dps-client-facebook" type="text" name="client_facebook" value="<?php echo esc_attr( $meta['facebook'] ?? '' ); ?>" placeholder="<?php echo esc_attr__( 'Nome do perfil', 'desi-pet-shower' ); ?>" autocomplete="off" />
            </div>
        </div>
    </section>

    <section class="dps-signature-section">
        <div class="dps-signature-section__header">
            <p class="dps-signature-section__eyebrow"><?php esc_html_e( 'Localização & preferências', 'desi-pet-shower' ); ?></p>
            <h2 class="dps-signature-section__title"><?php esc_html_e( 'Endereço e observações do cadastro', 'desi-pet-shower' ); ?></h2>
        </div>

        <div class="dps-signature-grid dps-signature-grid--2">
            <div class="dps-signature-field dps-signature-field--full">
                <label class="dps-signature-field__label" for="dps-client-address-admin"><?php esc_html_e( 'Endereço completo', 'desi-pet-shower' ); ?></label>
                <textarea
                    id="dps-client-address-admin"
                    name="client_address"
                    rows="3"
                    data-dps-address-autocomplete
                    data-dps-google-api-key="<?php echo esc_attr( $api_key ); ?>"
                    data-dps-lat-target="dps-client-lat-admin"
                    data-dps-lng-target="dps-client-lng-admin"
                    placeholder="<?php echo esc_attr__( 'Rua, número, bairro, cidade - UF', 'desi-pet-shower' ); ?>"
                ><?php echo esc_textarea( $meta['address'] ?? '' ); ?></textarea>
            </div>

            <div class="dps-signature-field dps-signature-field--full">
                <label class="dps-signature-field__label" for="dps-client-referral"><?php esc_html_e( 'Como nos conheceu?', 'desi-pet-shower' ); ?></label>
                <input id="dps-client-referral" type="text" name="client_referral" value="<?php echo esc_attr( $meta['referral'] ?? '' ); ?>" placeholder="<?php echo esc_attr__( 'Google, indicação, Instagram…', 'desi-pet-shower' ); ?>" autocomplete="off" />
            </div>

            <div class="dps-signature-field dps-signature-field--full">
                <label class="dps-signature-check" for="dps-client-photo-auth">
                    <input type="hidden" name="client_photo_auth" value="" />
                    <input id="dps-client-photo-auth" type="checkbox" name="client_photo_auth" value="1" <?php checked( ! empty( $meta['photo_auth'] ) ); ?> />
                    <span><?php esc_html_e( 'Autoriza a publicação da foto do pet nas redes sociais.', 'desi-pet-shower' ); ?></span>
                </label>
            </div>
        </div>
    </section>

    <input type="hidden" name="client_lat" id="dps-client-lat-admin" value="<?php echo esc_attr( $meta['lat'] ?? '' ); ?>" />
    <input type="hidden" name="client_lng" id="dps-client-lng-admin" value="<?php echo esc_attr( $meta['lng'] ?? '' ); ?>" />

    <div class="dps-signature-actions dps-signature-actions--end">
        <button type="submit" class="dps-submit-btn dps-signature-button">
            <span class="dps-signature-button__text"><?php echo esc_html( $btn_text ); ?></span>
        </button>
    </div>
</form>
