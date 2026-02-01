<?php
/**
 * Template: Consentimento de Tosa com Máquina
 *
 * Variáveis disponíveis:
 * @var WP_Post $client
 * @var array   $pets
 * @var array   $consent_status
 * @var string  $token
 * @var int     $client_id
 *
 * @package DesiPetShower
 * @since 1.1.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$client_id    = isset( $client_id ) ? (int) $client_id : ( $client ? $client->ID : 0 );
$client_name  = $client ? $client->post_title : '';
$client_phone = $client_id ? get_post_meta( $client_id, 'client_phone', true ) : '';
$client_email = $client_id ? get_post_meta( $client_id, 'client_email', true ) : '';
$client_cpf   = $client_id ? get_post_meta( $client_id, 'client_cpf', true ) : '';
$site_name    = get_bloginfo( 'name' );
$is_granted   = isset( $consent_status['status'] ) && 'granted' === $consent_status['status'];
?>

<div class="dps-consent-page">
    <div class="dps-consent-container">
        <header class="dps-consent-header">
            <div class="dps-consent-header__icon">✂️</div>
            <h1 class="dps-consent-title"><?php echo esc_html__( 'Consentimento de Tosa com Máquina', 'desi-pet-shower' ); ?></h1>
            <p class="dps-consent-subtitle">
                <?php
                printf(
                    /* translators: %s: nome do cliente */
                    esc_html__( 'Olá, %s! Precisamos do seu aceite para continuar.', 'desi-pet-shower' ),
                    esc_html( $client_name )
                );
                ?>
            </p>
        </header>

        <?php
        if ( class_exists( 'DPS_Message_Helper' ) ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in DPS_Message_Helper
            echo DPS_Message_Helper::display_messages();
        }
        ?>

        <?php if ( $is_granted ) : ?>
            <div class="dps-alert dps-alert--success">
                <?php
                printf(
                    /* translators: %s: data */
                    esc_html__( 'Consentimento já registrado em %s. Caso deseje atualizar os dados, reenvie o formulário.', 'desi-pet-shower' ),
                    esc_html( $consent_status['granted_at'] ?? '' )
                );
                ?>
            </div>
        <?php endif; ?>

        <form method="post" class="dps-consent-form">
            <input type="hidden" name="dps_tosa_consent_token" value="<?php echo esc_attr( $token ); ?>">
            <input type="hidden" name="dps_tosa_consent_client_id" value="<?php echo esc_attr( $client_id ); ?>">
            <?php wp_nonce_field( 'dps_tosa_consent_' . $client_id, 'dps_tosa_consent_nonce' ); ?>

            <section class="dps-consent-card">
                <h2><?php echo esc_html__( 'Dados do Responsável', 'desi-pet-shower' ); ?></h2>
                <div class="dps-consent-grid">
                    <div class="dps-consent-field">
                        <label for="dps_consent_signature_name"><?php echo esc_html__( 'Nome completo', 'desi-pet-shower' ); ?> <span class="dps-required">*</span></label>
                        <input type="text" id="dps_consent_signature_name" name="dps_consent_signature_name" value="<?php echo esc_attr( $client_name ); ?>" required>
                    </div>
                    <div class="dps-consent-field">
                        <label for="dps_consent_signature_document"><?php echo esc_html__( 'CPF', 'desi-pet-shower' ); ?></label>
                        <input type="text" id="dps_consent_signature_document" name="dps_consent_signature_document" value="<?php echo esc_attr( $client_cpf ); ?>" placeholder="000.000.000-00">
                    </div>
                    <div class="dps-consent-field">
                        <label for="dps_consent_signature_phone"><?php echo esc_html__( 'Telefone/WhatsApp', 'desi-pet-shower' ); ?></label>
                        <input type="tel" id="dps_consent_signature_phone" name="dps_consent_signature_phone" value="<?php echo esc_attr( $client_phone ); ?>" placeholder="(00) 00000-0000">
                    </div>
                    <div class="dps-consent-field">
                        <label for="dps_consent_signature_email"><?php echo esc_html__( 'E-mail', 'desi-pet-shower' ); ?></label>
                        <input type="email" id="dps_consent_signature_email" name="dps_consent_signature_email" value="<?php echo esc_attr( $client_email ); ?>" placeholder="seu@email.com">
                    </div>
                    <div class="dps-consent-field dps-consent-field--full">
                        <label for="dps_consent_relationship"><?php echo esc_html__( 'Relação com o pet', 'desi-pet-shower' ); ?></label>
                        <input type="text" id="dps_consent_relationship" name="dps_consent_relationship" value="<?php echo esc_attr__( 'Tutor(a)', 'desi-pet-shower' ); ?>">
                    </div>
                </div>
            </section>

            <section class="dps-consent-card">
                <h2><?php echo esc_html__( 'Pets vinculados', 'desi-pet-shower' ); ?></h2>
                <?php if ( ! empty( $pets ) ) : ?>
                    <ul class="dps-consent-pets">
                        <?php foreach ( $pets as $pet ) : ?>
                            <li><?php echo esc_html( $pet->post_title ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <p class="dps-text-muted"><?php echo esc_html__( 'Nenhum pet cadastrado para este cliente.', 'desi-pet-shower' ); ?></p>
                <?php endif; ?>
            </section>

            <section class="dps-consent-card">
                <h2><?php echo esc_html__( 'Termos do consentimento', 'desi-pet-shower' ); ?></h2>
                <p><?php echo esc_html__( 'Ao autorizar a tosa com máquina, você declara estar ciente de que:', 'desi-pet-shower' ); ?></p>
                <ul class="dps-consent-list">
                    <li><?php echo esc_html__( 'A tosa com máquina pode expor pequenas imperfeições, nódulos ou sensibilidades já existentes.', 'desi-pet-shower' ); ?></li>
                    <li><?php echo esc_html__( 'Pelagens muito embaraçadas podem exigir uso de máquina para preservar o bem-estar do pet.', 'desi-pet-shower' ); ?></li>
                    <li><?php echo esc_html__( 'Nossa equipe prioriza segurança, conforto e pausas quando necessário.', 'desi-pet-shower' ); ?></li>
                    <li><?php echo esc_html__( 'Você pode revogar este consentimento a qualquer momento, solicitando ao administrador.', 'desi-pet-shower' ); ?></li>
                </ul>
                <label class="dps-consent-check">
                    <input type="checkbox" name="dps_tosa_consent_accept" value="1" required>
                    <span><?php echo esc_html__( 'Li e aceito os termos acima.', 'desi-pet-shower' ); ?></span>
                </label>
            </section>

            <section class="dps-consent-card">
                <h2><?php echo esc_html__( 'Assinatura', 'desi-pet-shower' ); ?></h2>
                <p class="dps-text-muted">
                    <?php
                    printf(
                        /* translators: %s: nome do estabelecimento */
                        esc_html__( 'Confirmo que autorizo a equipe do %s a realizar a tosa com máquina quando necessário.', 'desi-pet-shower' ),
                        esc_html( $site_name )
                    );
                    ?>
                </p>
                <div class="dps-consent-signature">
                    <strong><?php echo esc_html__( 'Assinante:', 'desi-pet-shower' ); ?></strong>
                    <?php echo esc_html( $client_name ); ?>
                </div>
            </section>

            <button type="submit" class="dps-btn dps-btn--primary dps-consent-submit">
                ✅ <?php echo esc_html__( 'Registrar consentimento', 'desi-pet-shower' ); ?>
            </button>
        </form>
    </div>
</div>

<style>
.dps-consent-page {
    background: #f9fafb;
    padding: 32px 16px;
}
.dps-consent-container {
    max-width: 860px;
    margin: 0 auto;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    padding: 32px;
}
.dps-consent-header {
    text-align: center;
    margin-bottom: 24px;
}
.dps-consent-header__icon {
    font-size: 32px;
}
.dps-consent-title {
    font-size: 24px;
    margin: 8px 0;
    color: #374151;
}
.dps-consent-subtitle {
    color: #6b7280;
}
.dps-consent-card {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 24px;
}
.dps-consent-card h2 {
    font-size: 18px;
    margin: 0 0 16px;
    color: #374151;
}
.dps-consent-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}
.dps-consent-field label {
    display: block;
    font-weight: 600;
    color: #374151;
    margin-bottom: 6px;
}
.dps-consent-field input {
    width: 100%;
    padding: 12px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    font-size: 14px;
}
.dps-consent-field--full {
    grid-column: 1 / -1;
}
.dps-consent-pets {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 8px;
    margin: 0;
    padding-left: 20px;
}
.dps-consent-list {
    margin: 12px 0 16px;
    padding-left: 20px;
    color: #4b5563;
}
.dps-consent-check {
    display: flex;
    gap: 8px;
    align-items: center;
    font-weight: 600;
    color: #374151;
}
.dps-consent-submit {
    width: 100%;
    padding: 14px 20px;
}
.dps-consent-signature {
    font-size: 16px;
    color: #374151;
    margin-top: 12px;
}
.dps-text-muted {
    color: #6b7280;
}
@media (max-width: 768px) {
    .dps-consent-container {
        padding: 24px;
    }
    .dps-consent-grid {
        grid-template-columns: 1fr;
    }
}
</style>
