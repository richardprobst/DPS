<?php
/**
 * DPS Signature public access screen for the client portal.
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

<div class="dps-signature-shell dps-signature-shell--auth dps-portal-signature dps-client-portal-access-page" data-dps-access-root data-dps-default-mode="password">
    <section class="dps-portal-access" aria-labelledby="dps-portal-entry-title">
        <section class="dps-signature-hero dps-portal-access__hero">
            <div class="dps-portal-access__hero-main">
                <span class="dps-signature-hero__eyebrow"><?php esc_html_e( 'Portal do Cliente', 'dps-client-portal' ); ?></span>
                <h1 id="dps-portal-entry-title" class="dps-signature-hero__title"><?php esc_html_e( 'Acesse o acompanhamento do seu pet com clareza', 'dps-client-portal' ); ?></h1>
                <p class="dps-signature-hero__lead"><?php esc_html_e( 'Entre com sua senha recorrente ou use um link rapido enviado para o e-mail cadastrado do tutor.', 'dps-client-portal' ); ?></p>
            </div>

            <div class="dps-portal-access__hero-points" role="list" aria-label="<?php esc_attr_e( 'Destaques do acesso', 'dps-client-portal' ); ?>">
                <article class="dps-portal-access__hero-point" role="listitem">
                    <span class="dps-portal-access__hero-point-label"><?php esc_html_e( 'Senha propria', 'dps-client-portal' ); ?></span>
                    <strong class="dps-portal-access__hero-point-title"><?php esc_html_e( 'Ideal para retornos frequentes', 'dps-client-portal' ); ?></strong>
                    <p><?php esc_html_e( 'Entre mais rapido quando ja usa o portal no dia a dia.', 'dps-client-portal' ); ?></p>
                </article>

                <article class="dps-portal-access__hero-point" role="listitem">
                    <span class="dps-portal-access__hero-point-label"><?php esc_html_e( 'Link rapido', 'dps-client-portal' ); ?></span>
                    <strong class="dps-portal-access__hero-point-title"><?php esc_html_e( 'Sem senha, sem atrito', 'dps-client-portal' ); ?></strong>
                    <p><?php esc_html_e( 'Receba um acesso direto no e-mail quando precisar entrar agora.', 'dps-client-portal' ); ?></p>
                </article>

                <article class="dps-portal-access__hero-point" role="listitem">
                    <span class="dps-portal-access__hero-point-label"><?php esc_html_e( 'Suporte humano', 'dps-client-portal' ); ?></span>
                    <strong class="dps-portal-access__hero-point-title"><?php esc_html_e( 'Cadastro revisado pela equipe', 'dps-client-portal' ); ?></strong>
                    <p><?php esc_html_e( 'Se o e-mail mudou ou o cadastro nao foi localizado, fale com a equipe no WhatsApp.', 'dps-client-portal' ); ?></p>
                </article>
            </div>
        </section>

        <div class="dps-portal-access__layout">
            <div class="dps-portal-access__primary">
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

                <article class="dps-signature-panel dps-portal-auth-card">
                    <div class="dps-signature-panel__header">
                        <span class="dps-signature-hero__tag"><?php esc_html_e( 'Acesso principal', 'dps-client-portal' ); ?></span>
                        <h2 class="dps-signature-panel__title"><?php esc_html_e( 'Escolha como quer entrar', 'dps-client-portal' ); ?></h2>
                        <p class="dps-signature-panel__intro"><?php esc_html_e( 'Use sempre o mesmo e-mail cadastrado no tutor. Troque de metodo sem perder o que voce ja digitou.', 'dps-client-portal' ); ?></p>
                    </div>

                    <div class="dps-portal-auth-card__switch" role="tablist" aria-label="<?php esc_attr_e( 'Modos de acesso', 'dps-client-portal' ); ?>">
                        <button type="button" id="dps-auth-tab-password" class="dps-portal-auth-card__tab is-active" data-dps-auth-tab="password" role="tab" aria-selected="true" aria-controls="dps-auth-panel-password" tabindex="0">
                            <span class="dps-portal-auth-card__tab-title"><?php esc_html_e( 'Senha', 'dps-client-portal' ); ?></span>
                            <span class="dps-portal-auth-card__tab-copy"><?php esc_html_e( 'Retorno recorrente', 'dps-client-portal' ); ?></span>
                        </button>

                        <button type="button" id="dps-auth-tab-magic" class="dps-portal-auth-card__tab" data-dps-auth-tab="magic" role="tab" aria-selected="false" aria-controls="dps-auth-panel-magic" tabindex="-1">
                            <span class="dps-portal-auth-card__tab-title"><?php esc_html_e( 'Link rapido', 'dps-client-portal' ); ?></span>
                            <span class="dps-portal-auth-card__tab-copy"><?php esc_html_e( 'Acesso por e-mail', 'dps-client-portal' ); ?></span>
                        </button>
                    </div>

                    <section id="dps-auth-panel-password" class="dps-portal-auth-card__panel is-active" data-dps-auth-panel="password" role="tabpanel" aria-labelledby="dps-auth-tab-password">
                        <form method="post" action="<?php echo esc_url( $portal_url ); ?>" class="dps-signature-form dps-portal-auth-card__form" data-dps-password-login-form>
                            <?php wp_nonce_field( 'dps_portal_password_login', '_dps_portal_password_login_nonce' ); ?>
                            <input type="hidden" name="dps_portal_password_login" value="1" />

                            <div class="dps-signature-field">
                                <label class="dps-signature-field__label" for="dps-portal-password-email"><?php esc_html_e( 'E-mail cadastrado', 'dps-client-portal' ); ?></label>
                                <input id="dps-portal-password-email" type="email" name="dps_portal_email" autocomplete="email" placeholder="email@cliente.com" data-dps-access-email required />
                                <p class="dps-signature-field__helper"><?php esc_html_e( 'Use o e-mail principal do cadastro do tutor.', 'dps-client-portal' ); ?></p>
                            </div>

                            <div class="dps-signature-field">
                                <label class="dps-signature-field__label" for="dps-portal-password"><?php esc_html_e( 'Senha', 'dps-client-portal' ); ?></label>
                                <div class="dps-portal-access__password-wrap">
                                    <input id="dps-portal-password" type="password" name="dps_portal_password" autocomplete="current-password" placeholder="<?php echo esc_attr__( 'Digite sua senha', 'dps-client-portal' ); ?>" required />
                                    <button type="button" class="dps-portal-access__password-toggle" data-dps-password-toggle aria-controls="dps-portal-password" aria-pressed="false"><?php esc_html_e( 'Mostrar', 'dps-client-portal' ); ?></button>
                                </div>
                            </div>

                            <div class="dps-signature-field">
                                <label class="dps-signature-check" for="dps-portal-password-remember">
                                    <input id="dps-portal-password-remember" type="checkbox" name="dps_portal_remember" value="1" />
                                    <span><?php esc_html_e( 'Lembrar neste dispositivo', 'dps-client-portal' ); ?></span>
                                </label>
                            </div>

                            <div class="dps-portal-auth-card__actions">
                                <button type="submit" class="dps-signature-button">
                                    <span class="dps-signature-button__text"><?php esc_html_e( 'Entrar no portal', 'dps-client-portal' ); ?></span>
                                </button>

                                <button type="button" class="dps-signature-button dps-signature-button--secondary" data-dps-password-access-trigger data-loading-label="<?php echo esc_attr__( 'Enviando instrucoes...', 'dps-client-portal' ); ?>">
                                    <span class="dps-signature-button__text"><?php esc_html_e( 'Criar ou redefinir senha', 'dps-client-portal' ); ?></span>
                                </button>
                            </div>

                            <div class="dps-portal-entry__feedback dps-portal-signature__feedback" data-dps-password-feedback aria-live="polite" hidden></div>
                        </form>
                    </section>

                    <section id="dps-auth-panel-magic" class="dps-portal-auth-card__panel" data-dps-auth-panel="magic" role="tabpanel" aria-labelledby="dps-auth-tab-magic" hidden>
                        <form class="dps-signature-form dps-portal-auth-card__form" data-dps-access-form="magic-link">
                            <div class="dps-signature-field">
                                <label class="dps-signature-field__label" for="dps-portal-magic-email"><?php esc_html_e( 'E-mail cadastrado', 'dps-client-portal' ); ?></label>
                                <input id="dps-portal-magic-email" type="email" name="email" autocomplete="email" placeholder="email@cliente.com" data-dps-access-email required />
                                <p class="dps-signature-field__helper"><?php esc_html_e( 'Vamos enviar um link direto para esse e-mail.', 'dps-client-portal' ); ?></p>
                            </div>

                            <div class="dps-signature-field">
                                <label class="dps-signature-check" for="dps-portal-magic-remember">
                                    <input id="dps-portal-magic-remember" type="checkbox" name="remember_me" value="1" />
                                    <span><?php esc_html_e( 'Manter acesso neste dispositivo', 'dps-client-portal' ); ?></span>
                                </label>
                            </div>

                            <div class="dps-portal-auth-card__actions">
                                <button type="submit" class="dps-signature-button" data-loading-label="<?php echo esc_attr__( 'Enviando link...', 'dps-client-portal' ); ?>">
                                    <span class="dps-signature-button__text"><?php esc_html_e( 'Receber link de acesso', 'dps-client-portal' ); ?></span>
                                </button>
                            </div>

                            <div class="dps-portal-entry__feedback dps-portal-signature__feedback" data-dps-form-feedback aria-live="polite" hidden></div>
                        </form>
                    </section>
                </article>
            </div>

            <aside class="dps-portal-access__rail">
                <article class="dps-signature-panel dps-portal-access__method-card">
                    <div class="dps-signature-panel__header">
                        <span class="dps-signature-hero__tag"><?php esc_html_e( 'Como escolher', 'dps-client-portal' ); ?></span>
                        <h2 class="dps-signature-panel__title"><?php esc_html_e( 'Um metodo para cada momento', 'dps-client-portal' ); ?></h2>
                        <p class="dps-signature-panel__intro"><?php esc_html_e( 'A tela foi organizada para priorizar o acesso recorrente, sem perder a rota rapida por e-mail.', 'dps-client-portal' ); ?></p>
                    </div>

                    <div class="dps-portal-access__method-list">
                        <article class="dps-portal-access__method-item">
                            <h3><?php esc_html_e( 'Senha', 'dps-client-portal' ); ?></h3>
                            <p><?php esc_html_e( 'Melhor para quem entra com frequencia e quer abrir o portal com menos etapas.', 'dps-client-portal' ); ?></p>
                        </article>

                        <article class="dps-portal-access__method-item">
                            <h3><?php esc_html_e( 'Link rapido', 'dps-client-portal' ); ?></h3>
                            <p><?php esc_html_e( 'Use quando estiver sem a senha ou quando precisar de um acesso imediato no e-mail.', 'dps-client-portal' ); ?></p>
                        </article>
                    </div>
                </article>

                <aside class="dps-signature-panel dps-portal-access__support-card" data-dps-whatsapp-card>
                    <div class="dps-signature-panel__header">
                        <span class="dps-signature-hero__tag"><?php esc_html_e( 'Suporte', 'dps-client-portal' ); ?></span>
                        <h2 class="dps-signature-panel__title"><?php esc_html_e( 'Precisa revisar o cadastro?', 'dps-client-portal' ); ?></h2>
                        <p class="dps-signature-panel__intro"><?php esc_html_e( 'Acione a equipe quando o e-mail mudou, o cadastro nao foi localizado ou a senha ainda nao foi criada.', 'dps-client-portal' ); ?></p>
                    </div>

                    <ul class="dps-signature-support-list">
                        <li><?php esc_html_e( 'E-mail desatualizado', 'dps-client-portal' ); ?></li>
                        <li><?php esc_html_e( 'Cadastro nao localizado', 'dps-client-portal' ); ?></li>
                        <li><?php esc_html_e( 'Senha ainda nao criada', 'dps-client-portal' ); ?></li>
                    </ul>

                    <?php if ( '' !== $whatsapp_url ) : ?>
                        <a class="dps-signature-button dps-signature-button--ghost" href="<?php echo esc_url( $whatsapp_url ); ?>" target="_blank" rel="noopener noreferrer">
                            <span class="dps-signature-button__text"><?php esc_html_e( 'Falar com a equipe no WhatsApp', 'dps-client-portal' ); ?></span>
                        </a>
                    <?php else : ?>
                        <p class="dps-signature-empty"><?php esc_html_e( 'O WhatsApp da equipe ainda nao foi configurado no sistema.', 'dps-client-portal' ); ?></p>
                    <?php endif; ?>
                </aside>
            </aside>
        </div>
    </section>
</div>
