<?php
/**
 * Template da tela inicial de acesso ao portal.
 *
 * @package DPS_Client_Portal
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$messages     = isset( $portal_access_context['messages'] ) && is_array( $portal_access_context['messages'] ) ? $portal_access_context['messages'] : [];
$portal_url   = isset( $portal_access_context['portal_url'] ) && is_string( $portal_access_context['portal_url'] ) ? $portal_access_context['portal_url'] : home_url( '/portal-cliente/' );
$whatsapp_url = isset( $portal_access_context['whatsapp_url'] ) && is_string( $portal_access_context['whatsapp_url'] ) ? $portal_access_context['whatsapp_url'] : '';
?>
<div class="dps-client-portal-access-page">
    <section class="dps-portal-entry" aria-labelledby="dps-portal-entry-title">
        <div class="dps-portal-entry__hero">
            <div class="dps-portal-entry__hero-copy">
                <span class="dps-portal-entry__eyebrow"><?php esc_html_e( 'Portal do Cliente', 'dps-client-portal' ); ?></span>
                <h1 id="dps-portal-entry-title" class="dps-portal-entry__title"><?php esc_html_e( 'Escolha como voce quer entrar', 'dps-client-portal' ); ?></h1>
                <p class="dps-portal-entry__lead"><?php esc_html_e( 'Mantenha o acesso por link direto e adicione tambem o login com e-mail e senha. O usuario do portal sempre e o e-mail cadastrado no cliente.', 'dps-client-portal' ); ?></p>
                <ul class="dps-portal-entry__feature-list" aria-label="<?php esc_attr_e( 'Recursos do portal', 'dps-client-portal' ); ?>">
                    <li><?php esc_html_e( 'Agendamentos, historico e fotos do pet em um so lugar.', 'dps-client-portal' ); ?></li>
                    <li><?php esc_html_e( 'Link direto para quem prefere acesso rapido e imediato.', 'dps-client-portal' ); ?></li>
                    <li><?php esc_html_e( 'Login com e-mail e senha para uso recorrente e seguro.', 'dps-client-portal' ); ?></li>
                </ul>
            </div>
            <div class="dps-portal-entry__hero-card">
                <span class="dps-portal-entry__hero-badge"><?php esc_html_e( 'Acesso hibrido', 'dps-client-portal' ); ?></span>
                <p class="dps-portal-entry__hero-metric"><?php esc_html_e( 'Link direto + E-mail e senha', 'dps-client-portal' ); ?></p>
                <p class="dps-portal-entry__hero-note"><?php esc_html_e( 'Se for seu primeiro acesso, voce pode pedir o e-mail para criar ou redefinir a senha sem sair desta tela.', 'dps-client-portal' ); ?></p>
            </div>
        </div>

        <?php if ( ! empty( $messages ) ) : ?>
            <div class="dps-portal-entry__notices" aria-live="polite">
                <?php foreach ( $messages as $message ) : ?>
                    <?php
                    $message_type = isset( $message['type'] ) ? sanitize_html_class( $message['type'] ) : 'info';
                    $message_title = isset( $message['title'] ) ? (string) $message['title'] : '';
                    $message_description = isset( $message['description'] ) ? (string) $message['description'] : '';
                    ?>
                    <article class="dps-portal-entry__notice dps-portal-entry__notice--<?php echo esc_attr( $message_type ); ?>">
                        <?php if ( '' !== $message_title ) : ?>
                            <h2 class="dps-portal-entry__notice-title"><?php echo esc_html( $message_title ); ?></h2>
                        <?php endif; ?>
                        <?php if ( '' !== $message_description ) : ?>
                            <p class="dps-portal-entry__notice-text"><?php echo esc_html( $message_description ); ?></p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="dps-portal-entry__grid">
            <article class="dps-portal-entry__panel">
                <span class="dps-portal-entry__panel-tag"><?php esc_html_e( 'Link direto', 'dps-client-portal' ); ?></span>
                <h2 class="dps-portal-entry__panel-title"><?php esc_html_e( 'Receber link por e-mail', 'dps-client-portal' ); ?></h2>
                <p class="dps-portal-entry__panel-text"><?php esc_html_e( 'Digite o e-mail cadastrado para receber um link de acesso imediato. Este fluxo continua disponivel como antes.', 'dps-client-portal' ); ?></p>

                <form class="dps-portal-entry__async-form" data-dps-access-form="magic-link">
                    <div class="dps-portal-entry__field-group">
                        <label class="dps-portal-entry__label" for="dps-portal-magic-email"><?php esc_html_e( 'E-mail cadastrado', 'dps-client-portal' ); ?></label>
                        <input id="dps-portal-magic-email" class="dps-portal-entry__input" type="email" name="email" autocomplete="email" placeholder="email@cliente.com" required />
                    </div>

                    <label class="dps-portal-entry__check">
                        <input type="checkbox" name="remember_me" value="1" />
                        <span><?php esc_html_e( 'Manter acesso neste dispositivo', 'dps-client-portal' ); ?></span>
                    </label>

                    <button type="submit" class="dps-portal-entry__button dps-portal-entry__button--primary" data-loading-label="<?php echo esc_attr__( 'Enviando link...', 'dps-client-portal' ); ?>">
                        <?php esc_html_e( 'Enviar link de acesso', 'dps-client-portal' ); ?>
                    </button>

                    <div class="dps-portal-entry__feedback" data-dps-form-feedback aria-live="polite"></div>
                </form>
            </article>

            <article class="dps-portal-entry__panel">
                <span class="dps-portal-entry__panel-tag"><?php esc_html_e( 'E-mail e senha', 'dps-client-portal' ); ?></span>
                <h2 class="dps-portal-entry__panel-title"><?php esc_html_e( 'Entrar com sua senha', 'dps-client-portal' ); ?></h2>
                <p class="dps-portal-entry__panel-text"><?php esc_html_e( 'O login sempre usa o e-mail cadastrado no cliente. Se voce ja criou a senha, entre por aqui.', 'dps-client-portal' ); ?></p>

                <form method="post" action="<?php echo esc_url( $portal_url ); ?>" class="dps-portal-entry__login-form" data-dps-password-login-form>
                    <?php wp_nonce_field( 'dps_portal_password_login', '_dps_portal_password_login_nonce' ); ?>
                    <input type="hidden" name="dps_portal_password_login" value="1" />

                    <div class="dps-portal-entry__field-group">
                        <label class="dps-portal-entry__label" for="dps-portal-password-email"><?php esc_html_e( 'E-mail cadastrado', 'dps-client-portal' ); ?></label>
                        <input id="dps-portal-password-email" class="dps-portal-entry__input" type="email" name="dps_portal_email" autocomplete="email" placeholder="email@cliente.com" required />
                    </div>

                    <div class="dps-portal-entry__field-group">
                        <label class="dps-portal-entry__label" for="dps-portal-password"><?php esc_html_e( 'Senha', 'dps-client-portal' ); ?></label>
                        <input id="dps-portal-password" class="dps-portal-entry__input" type="password" name="dps_portal_password" autocomplete="current-password" placeholder="<?php echo esc_attr__( 'Digite sua senha', 'dps-client-portal' ); ?>" required />
                    </div>

                    <label class="dps-portal-entry__check">
                        <input type="checkbox" name="dps_portal_remember" value="1" />
                        <span><?php esc_html_e( 'Lembrar neste dispositivo', 'dps-client-portal' ); ?></span>
                    </label>

                    <button type="submit" class="dps-portal-entry__button dps-portal-entry__button--primary">
                        <?php esc_html_e( 'Entrar no portal', 'dps-client-portal' ); ?>
                    </button>
                </form>

                <div class="dps-portal-entry__secondary-action">
                    <p class="dps-portal-entry__secondary-text"><?php esc_html_e( 'Primeiro acesso ou esqueceu a senha?', 'dps-client-portal' ); ?></p>
                    <button type="button" class="dps-portal-entry__button dps-portal-entry__button--secondary" data-dps-password-access-trigger data-loading-label="<?php echo esc_attr__( 'Enviando instrucoes...', 'dps-client-portal' ); ?>">
                        <?php esc_html_e( 'Criar ou redefinir senha por e-mail', 'dps-client-portal' ); ?>
                    </button>
                    <div class="dps-portal-entry__feedback" data-dps-password-feedback aria-live="polite"></div>
                </div>
            </article>

            <aside class="dps-portal-entry__panel dps-portal-entry__panel--support" data-dps-whatsapp-card>
                <span class="dps-portal-entry__panel-tag"><?php esc_html_e( 'Suporte', 'dps-client-portal' ); ?></span>
                <h2 class="dps-portal-entry__panel-title"><?php esc_html_e( 'Precisa revisar seus dados?', 'dps-client-portal' ); ?></h2>
                <p class="dps-portal-entry__panel-text"><?php esc_html_e( 'Se o e-mail ainda nao estiver cadastrado, fale com a equipe para atualizar o cadastro do cliente e liberar os dois tipos de acesso.', 'dps-client-portal' ); ?></p>
                <ul class="dps-portal-entry__support-list">
                    <li><?php esc_html_e( 'Confirme com a equipe qual e-mail esta salvo no cliente.', 'dps-client-portal' ); ?></li>
                    <li><?php esc_html_e( 'O login por senha so funciona com um e-mail valido no cadastro.', 'dps-client-portal' ); ?></li>
                    <li><?php esc_html_e( 'O link direto continua disponivel para acessos rapidos.', 'dps-client-portal' ); ?></li>
                </ul>

                <?php if ( '' !== $whatsapp_url ) : ?>
                    <a class="dps-portal-entry__button dps-portal-entry__button--ghost" href="<?php echo esc_url( $whatsapp_url ); ?>" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e( 'Falar com a equipe no WhatsApp', 'dps-client-portal' ); ?>
                    </a>
                <?php else : ?>
                    <p class="dps-portal-entry__support-inline"><?php esc_html_e( 'O WhatsApp da equipe ainda nao foi configurado no sistema.', 'dps-client-portal' ); ?></p>
                <?php endif; ?>
            </aside>
        </div>
    </section>
</div>
