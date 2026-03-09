<?php
/**
 * Template das configuracoes do Portal do Cliente.
 *
 * @package DPS_Client_Portal
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="dps-portal-admin-shell dps-portal-settings-screen">
    <header class="dps-portal-admin-shell__header">
        <h2 class="dps-portal-admin-shell__title"><?php esc_html_e( 'Portal do Cliente: Configuracoes', 'dps-client-portal' ); ?></h2>
        <p class="dps-portal-admin-shell__description"><?php esc_html_e( 'Defina a pagina oficial do portal e revise o modelo de acesso hibrido: magic link mais login por e-mail e senha.', 'dps-client-portal' ); ?></p>
    </header>

    <?php if ( ! empty( $feedback_messages ) ) : ?>
        <div class="dps-portal-admin-shell__feedback">
            <?php foreach ( $feedback_messages as $feedback ) : ?>
                <div class="dps-portal-admin-shell__notice dps-portal-admin-shell__notice--<?php echo esc_attr( 'success' === $feedback['type'] ? 'success' : 'error' ); ?>">
                    <?php echo esc_html( $feedback['text'] ); ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <section class="dps-portal-admin-shell__summary" aria-label="<?php esc_attr_e( 'Resumo do portal', 'dps-client-portal' ); ?>">
        <article class="dps-admin-summary-card">
            <span class="dps-admin-summary-card__label"><?php esc_html_e( 'Pagina configurada', 'dps-client-portal' ); ?></span>
            <strong class="dps-admin-summary-card__value"><?php echo $portal_page_id > 0 ? esc_html( get_the_title( $portal_page_id ) ) : esc_html__( 'Nao definida', 'dps-client-portal' ); ?></strong>
        </article>
        <article class="dps-admin-summary-card">
            <span class="dps-admin-summary-card__label"><?php esc_html_e( 'Modo de acesso', 'dps-client-portal' ); ?></span>
            <strong class="dps-admin-summary-card__value"><?php esc_html_e( 'Link direto + E-mail e senha', 'dps-client-portal' ); ?></strong>
        </article>
        <article class="dps-admin-summary-card">
            <span class="dps-admin-summary-card__label"><?php esc_html_e( 'URL atual do portal', 'dps-client-portal' ); ?></span>
            <strong class="dps-admin-summary-card__value dps-admin-summary-card__value--small"><?php echo $portal_url ? esc_html( $portal_url ) : esc_html__( 'Aguardando configuracao', 'dps-client-portal' ); ?></strong>
        </article>
    </section>

    <form method="post" action="" class="dps-portal-settings-form">
        <?php wp_nonce_field( 'dps_save_portal_settings', '_dps_portal_settings_nonce' ); ?>

        <section class="dps-portal-settings-card">
            <div class="dps-portal-settings-card__header">
                <h3><?php esc_html_e( 'Pagina oficial do portal', 'dps-client-portal' ); ?></h3>
                <p><?php esc_html_e( 'Selecione a pagina que possui o shortcode [dps_client_portal]. Ela sera usada para magic link, login por senha e redefinicao de senha.', 'dps-client-portal' ); ?></p>
            </div>
            <div class="dps-admin-field">
                <label for="dps_portal_page_id"><?php esc_html_e( 'Pagina do Portal do Cliente', 'dps-client-portal' ); ?></label>
                <select name="dps_portal_page_id" id="dps_portal_page_id" class="widefat">
                    <option value="0"><?php esc_html_e( 'Selecione uma pagina', 'dps-client-portal' ); ?></option>
                    <?php foreach ( $pages as $page ) : ?>
                        <option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( $portal_page_id, $page->ID ); ?>><?php echo esc_html( $page->post_title ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="dps-admin-field">
                <label for="dps-portal-current-url"><?php esc_html_e( 'URL atual do portal', 'dps-client-portal' ); ?></label>
                <div class="dps-admin-copy-field">
                    <input id="dps-portal-current-url" type="text" class="widefat" readonly value="<?php echo esc_attr( $portal_url ); ?>" />
                    <button type="button" class="button button-secondary" data-copy-text="<?php echo esc_attr( $portal_url ); ?>"><?php esc_html_e( 'Copiar URL', 'dps-client-portal' ); ?></button>
                </div>
            </div>
        </section>

        <section class="dps-portal-settings-card">
            <div class="dps-portal-settings-card__header">
                <h3><?php esc_html_e( 'Seguranca do acesso', 'dps-client-portal' ); ?></h3>
                <p><?php esc_html_e( 'As opcoes abaixo se aplicam aos acessos por magic link e ajudam a fortalecer o uso recorrente do portal.', 'dps-client-portal' ); ?></p>
            </div>
            <label class="dps-admin-toggle">
                <input type="checkbox" name="dps_portal_access_notification_enabled" value="1" <?php checked( (int) get_option( 'dps_portal_access_notification_enabled', 0 ), 1 ); ?> />
                <span>
                    <strong><?php esc_html_e( 'Notificar o cliente a cada acesso', 'dps-client-portal' ); ?></strong>
                    <small><?php esc_html_e( 'Envia um e-mail quando o portal e acessado por link direto.', 'dps-client-portal' ); ?></small>
                </span>
            </label>
            <label class="dps-admin-toggle">
                <input type="checkbox" name="dps_portal_2fa_enabled" value="1" <?php checked( (int) get_option( 'dps_portal_2fa_enabled', 0 ), 1 ); ?> />
                <span>
                    <strong><?php esc_html_e( 'Exigir codigo por e-mail no magic link', 'dps-client-portal' ); ?></strong>
                    <small><?php esc_html_e( 'Ativa a verificacao em duas etapas para quem entra por link.', 'dps-client-portal' ); ?></small>
                </span>
            </label>
        </section>

        <section class="dps-portal-settings-card">
            <div class="dps-portal-settings-card__header">
                <h3><?php esc_html_e( 'Como usar o acesso hibrido', 'dps-client-portal' ); ?></h3>
                <p><?php esc_html_e( 'Fluxo recomendado para a equipe e para os clientes.', 'dps-client-portal' ); ?></p>
            </div>
            <ol class="dps-portal-settings-card__steps">
                <li><?php esc_html_e( 'Mantenha a pagina do portal publicada com o shortcode [dps_client_portal].', 'dps-client-portal' ); ?></li>
                <li><?php esc_html_e( 'Cadastre um e-mail valido em cada cliente para liberar magic link por e-mail e login por senha.', 'dps-client-portal' ); ?></li>
                <li><?php esc_html_e( 'Na tela de Logins, gere links quando precisar enviar acesso imediato ou recorrente.', 'dps-client-portal' ); ?></li>
                <li><?php esc_html_e( 'Use a acao de envio de senha para permitir que o proprio cliente crie ou redefina o acesso recorrente.', 'dps-client-portal' ); ?></li>
                <li><?php esc_html_e( 'Se houver clientes antigos sem usuario sincronizado, use a acao de sincronizacao no admin.', 'dps-client-portal' ); ?></li>
            </ol>
        </section>

        <div class="dps-portal-settings-form__actions">
            <button type="submit" name="dps_save_portal_settings" class="button button-primary"><?php esc_html_e( 'Salvar configuracoes', 'dps-client-portal' ); ?></button>
        </div>
    </form>
</div>
