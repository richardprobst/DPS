<?php
/**
 * Signature portal access screen.
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

<div class="dps-signature-shell dps-signature-shell--auth dps-portal-signature dps-client-portal-access-page">
    <section class="dps-portal-entry" aria-labelledby="dps-portal-entry-title">
        <div class="dps-signature-shell__layout">
            <section class="dps-signature-hero dps-portal-signature__hero">
                <div class="dps-signature-hero__grid">
                    <div class="dps-portal-signature__hero-copy">
                        <span class="dps-signature-hero__eyebrow"><?php esc_html_e( 'Portal do Cliente', 'dps-client-portal' ); ?></span>
                        <h1 id="dps-portal-entry-title" class="dps-signature-hero__title"><?php esc_html_e( 'Escolha como deseja entrar no seu espaço do cliente.', 'dps-client-portal' ); ?></h1>
                        <p class="dps-signature-hero__lead"><?php esc_html_e( 'O acesso agora segue o padrão DPS Signature/M3: visual mais claro, duas rotas de entrada e feedback imediato para solicitar link, criar senha ou revisar o e-mail cadastrado.', 'dps-client-portal' ); ?></p>

                        <ul class="dps-signature-hero__list" aria-label="<?php esc_attr_e( 'Benefícios do portal', 'dps-client-portal' ); ?>">
                            <li><?php esc_html_e( 'Link direto para acessos rápidos e temporários.', 'dps-client-portal' ); ?></li>
                            <li><?php esc_html_e( 'Login com e-mail e senha para uso recorrente e mais previsível.', 'dps-client-portal' ); ?></li>
                            <li><?php esc_html_e( 'Suporte orientado quando o cadastro ainda precisa de ajuste interno.', 'dps-client-portal' ); ?></li>
                        </ul>
                    </div>

                    <aside class="dps-signature-hero__aside" aria-label="<?php esc_attr_e( 'Resumo do acesso', 'dps-client-portal' ); ?>">
                        <article class="dps-signature-metric-card">
                            <p class="dps-signature-metric-card__value"><?php esc_html_e( 'Link direto + e-mail e senha', 'dps-client-portal' ); ?></p>
                            <p class="dps-signature-metric-card__note"><?php esc_html_e( 'Os dois caminhos convivem no mesmo fluxo. O e-mail usado no login é sempre o e-mail salvo no cadastro do cliente.', 'dps-client-portal' ); ?></p>
                        </article>
                    </aside>
                </div>
            </section>

            <aside class="dps-signature-step-rail dps-portal-signature__steps" aria-label="<?php esc_attr_e( 'Etapas do acesso', 'dps-client-portal' ); ?>">
                <p class="dps-signature-step-rail__title"><?php esc_html_e( 'Fluxo de acesso', 'dps-client-portal' ); ?></p>
                <ol class="dps-signature-step-list">
                    <li>
                        <button type="button">
                            <span class="dps-signature-step-list__index">1</span>
                            <span class="dps-signature-step-list__text">
                                <span class="dps-signature-step-list__label"><?php esc_html_e( 'Escolha a rota', 'dps-client-portal' ); ?></span>
                                <span class="dps-signature-step-list__detail"><?php esc_html_e( 'Link direto, senha ou ajuda da equipe.', 'dps-client-portal' ); ?></span>
                            </span>
                        </button>
                    </li>
                    <li>
                        <button type="button">
                            <span class="dps-signature-step-list__index">2</span>
                            <span class="dps-signature-step-list__text">
                                <span class="dps-signature-step-list__label"><?php esc_html_e( 'Confirme o e-mail', 'dps-client-portal' ); ?></span>
                                <span class="dps-signature-step-list__detail"><?php esc_html_e( 'O sistema valida o cadastro salvo e responde com o próximo passo.', 'dps-client-portal' ); ?></span>
                            </span>
                        </button>
                    </li>
                </ol>
            </aside>
        </div>

        <?php if ( ! empty( $messages ) ) : ?>
            <div class="dps-signature-form__notice-stack" aria-live="polite">
                <?php foreach ( $messages as $message ) : ?>
                    <?php
                    $message_type        = isset( $message['type'] ) ? sanitize_html_class( $message['type'] ) : 'info';
                    $message_title       = isset( $message['title'] ) ? (string) $message['title'] : '';
                    $message_description = isset( $message['description'] ) ? (string) $message['description'] : '';
                    ?>
                    <article class="dps-signature-notice dps-signature-notice--<?php echo esc_attr( $message_type ); ?>">
                        <?php if ( '' !== $message_title ) : ?>
                            <h2 class="dps-signature-notice__title"><?php echo esc_html( $message_title ); ?></h2>
                        <?php endif; ?>
                        <?php if ( '' !== $message_description ) : ?>
                            <p class="dps-signature-notice__text"><?php echo esc_html( $message_description ); ?></p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="dps-portal-entry__grid dps-portal-signature__grid">
            <article class="dps-portal-entry__panel dps-signature-panel">
                <div class="dps-signature-panel__header">
                    <span class="dps-signature-hero__tag"><?php esc_html_e( 'Link direto', 'dps-client-portal' ); ?></span>
                    <h2 class="dps-signature-panel__title"><?php esc_html_e( 'Receber link por e-mail', 'dps-client-portal' ); ?></h2>
                    <p class="dps-signature-panel__intro"><?php esc_html_e( 'Digite o e-mail cadastrado para receber um link de acesso imediato. Este fluxo é útil para acessos rápidos sem precisar lembrar a senha.', 'dps-client-portal' ); ?></p>
                </div>

                <form class="dps-signature-form dps-portal-entry__async-form" data-dps-access-form="magic-link">
                    <div class="dps-signature-field">
                        <label class="dps-signature-field__label" for="dps-portal-magic-email"><?php esc_html_e( 'E-mail cadastrado', 'dps-client-portal' ); ?></label>
                        <input id="dps-portal-magic-email" type="email" name="email" autocomplete="email" placeholder="email@cliente.com" required />
                    </div>

                    <div class="dps-signature-field">
                        <label class="dps-signature-check" for="dps-portal-magic-remember">
                            <input id="dps-portal-magic-remember" type="checkbox" name="remember_me" value="1" />
                            <span><?php esc_html_e( 'Manter acesso neste dispositivo', 'dps-client-portal' ); ?></span>
                        </label>
                    </div>

                    <div class="dps-signature-actions dps-signature-actions--end">
                        <button type="submit" class="dps-signature-button" data-loading-label="<?php echo esc_attr__( 'Enviando link...', 'dps-client-portal' ); ?>">
                            <span class="dps-signature-button__text"><?php esc_html_e( 'Enviar link de acesso', 'dps-client-portal' ); ?></span>
                        </button>
                    </div>

                    <div class="dps-portal-entry__feedback dps-portal-signature__feedback" data-dps-form-feedback aria-live="polite" hidden></div>
                </form>
            </article>

            <article class="dps-portal-entry__panel dps-signature-panel">
                <div class="dps-signature-panel__header">
                    <span class="dps-signature-hero__tag"><?php esc_html_e( 'E-mail e senha', 'dps-client-portal' ); ?></span>
                    <h2 class="dps-signature-panel__title"><?php esc_html_e( 'Entrar com sua senha', 'dps-client-portal' ); ?></h2>
                    <p class="dps-signature-panel__intro"><?php esc_html_e( 'Se você já criou sua senha, entre por aqui usando sempre o e-mail salvo no cadastro do cliente.', 'dps-client-portal' ); ?></p>
                </div>

                <form method="post" action="<?php echo esc_url( $portal_url ); ?>" class="dps-signature-form dps-portal-entry__login-form" data-dps-password-login-form>
                    <?php wp_nonce_field( 'dps_portal_password_login', '_dps_portal_password_login_nonce' ); ?>
                    <input type="hidden" name="dps_portal_password_login" value="1" />

                    <div class="dps-signature-field">
                        <label class="dps-signature-field__label" for="dps-portal-password-email"><?php esc_html_e( 'E-mail cadastrado', 'dps-client-portal' ); ?></label>
                        <input id="dps-portal-password-email" type="email" name="dps_portal_email" autocomplete="email" placeholder="email@cliente.com" required />
                    </div>

                    <div class="dps-signature-field">
                        <label class="dps-signature-field__label" for="dps-portal-password"><?php esc_html_e( 'Senha', 'dps-client-portal' ); ?></label>
                        <input id="dps-portal-password" type="password" name="dps_portal_password" autocomplete="current-password" placeholder="<?php echo esc_attr__( 'Digite sua senha', 'dps-client-portal' ); ?>" required />
                    </div>

                    <div class="dps-signature-field">
                        <label class="dps-signature-check" for="dps-portal-password-remember">
                            <input id="dps-portal-password-remember" type="checkbox" name="dps_portal_remember" value="1" />
                            <span><?php esc_html_e( 'Lembrar neste dispositivo', 'dps-client-portal' ); ?></span>
                        </label>
                    </div>

                    <div class="dps-signature-actions">
                        <button type="submit" class="dps-signature-button">
                            <span class="dps-signature-button__text"><?php esc_html_e( 'Entrar no portal', 'dps-client-portal' ); ?></span>
                        </button>
                        <button type="button" class="dps-signature-button dps-signature-button--secondary" data-dps-password-access-trigger data-loading-label="<?php echo esc_attr__( 'Enviando instruções...', 'dps-client-portal' ); ?>">
                            <span class="dps-signature-button__text"><?php esc_html_e( 'Criar ou redefinir senha', 'dps-client-portal' ); ?></span>
                        </button>
                    </div>

                    <div class="dps-portal-entry__feedback dps-portal-signature__feedback" data-dps-password-feedback aria-live="polite" hidden></div>
                </form>
            </article>

            <aside class="dps-portal-entry__panel dps-signature-panel dps-portal-entry__panel--support" data-dps-whatsapp-card>
                <div class="dps-signature-panel__header">
                    <span class="dps-signature-hero__tag"><?php esc_html_e( 'Suporte', 'dps-client-portal' ); ?></span>
                    <h2 class="dps-signature-panel__title"><?php esc_html_e( 'Precisa revisar seus dados?', 'dps-client-portal' ); ?></h2>
                    <p class="dps-signature-panel__intro"><?php esc_html_e( 'Se o e-mail ainda não estiver cadastrado ou estiver desatualizado, fale com a equipe para revisar o cadastro do cliente antes de tentar entrar novamente.', 'dps-client-portal' ); ?></p>
                </div>

                <ul class="dps-signature-support-list">
                    <li><?php esc_html_e( 'Confirme qual e-mail está salvo no cliente antes de solicitar o link.', 'dps-client-portal' ); ?></li>
                    <li><?php esc_html_e( 'O login com senha só funciona quando o cadastro do cliente tem um e-mail válido.', 'dps-client-portal' ); ?></li>
                    <li><?php esc_html_e( 'Se necessário, a equipe pode ajustar o cadastro e reenviar o fluxo.', 'dps-client-portal' ); ?></li>
                </ul>

                <?php if ( '' !== $whatsapp_url ) : ?>
                    <a class="dps-signature-button dps-signature-button--ghost" href="<?php echo esc_url( $whatsapp_url ); ?>" target="_blank" rel="noopener noreferrer">
                        <span class="dps-signature-button__text"><?php esc_html_e( 'Falar com a equipe no WhatsApp', 'dps-client-portal' ); ?></span>
                    </a>
                <?php else : ?>
                    <p class="dps-signature-empty"><?php esc_html_e( 'O WhatsApp da equipe ainda não foi configurado no sistema.', 'dps-client-portal' ); ?></p>
                <?php endif; ?>
            </aside>
        </div>
    </section>
</div>
