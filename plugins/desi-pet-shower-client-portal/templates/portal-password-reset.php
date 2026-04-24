<?php
/**
 * DPS Signature password reset screen for the client portal.
 *
 * @package DPS_Client_Portal
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$messages         = isset( $portal_access_context['messages'] ) && is_array( $portal_access_context['messages'] ) ? $portal_access_context['messages'] : [];
$portal_url       = isset( $portal_access_context['portal_url'] ) && is_string( $portal_access_context['portal_url'] ) ? $portal_access_context['portal_url'] : home_url( '/portal-cliente/' );
$reset_user_mail  = $portal_reset_valid && $portal_reset_user instanceof WP_User ? $portal_reset_user->user_email : $portal_password_login;
$is_expired_reset = isset( $portal_reset_error_code ) && 'expired_key' === $portal_reset_error_code;
$reset_tag        = $is_expired_reset
    ? __( 'Link expirado', 'dps-client-portal' )
    : __( 'Link invalido', 'dps-client-portal' );
$reset_title      = $is_expired_reset
    ? __( 'Solicite um novo e-mail para continuar', 'dps-client-portal' )
    : __( 'Solicite um novo link para continuar', 'dps-client-portal' );
$reset_intro      = $is_expired_reset
    ? __( 'Este link passou do prazo de uso. Volte para a tela inicial do portal e gere um novo acesso.', 'dps-client-portal' )
    : __( 'Este link nao esta mais disponivel. Volte para a tela inicial do portal e gere um novo acesso.', 'dps-client-portal' );
?>

<div class="dps-signature-shell dps-signature-shell--auth dps-portal-signature dps-client-portal-access-page dps-client-portal-access-page--reset" data-dps-access-root>
    <section class="dps-portal-access dps-portal-access--reset" aria-labelledby="dps-portal-reset-title">
        <section class="dps-signature-hero dps-portal-access__hero">
            <div class="dps-portal-access__hero-main">
                <span class="dps-signature-hero__eyebrow"><?php esc_html_e( 'Senha do portal', 'dps-client-portal' ); ?></span>
                <h1 id="dps-portal-reset-title" class="dps-signature-hero__title"><?php esc_html_e( 'Criar ou redefinir senha com seguranca', 'dps-client-portal' ); ?></h1>
                <p class="dps-signature-hero__lead"><?php esc_html_e( 'Defina sua nova senha para seguir acessando o portal com o mesmo e-mail cadastrado.', 'dps-client-portal' ); ?></p>
            </div>

            <div class="dps-portal-access__hero-points" role="list" aria-label="<?php esc_attr_e( 'Orientacoes para redefinicao de senha', 'dps-client-portal' ); ?>">
                <article class="dps-portal-access__hero-point" role="listitem">
                    <span class="dps-portal-access__hero-point-label"><?php esc_html_e( 'Mesmo cadastro', 'dps-client-portal' ); ?></span>
                    <strong class="dps-portal-access__hero-point-title"><?php esc_html_e( 'O e-mail continua o mesmo', 'dps-client-portal' ); ?></strong>
                    <p><?php esc_html_e( 'A nova senha vale para o e-mail ja vinculado ao cliente.', 'dps-client-portal' ); ?></p>
                </article>

                <article class="dps-portal-access__hero-point" role="listitem">
                    <span class="dps-portal-access__hero-point-label"><?php esc_html_e( 'Acesso recorrente', 'dps-client-portal' ); ?></span>
                    <strong class="dps-portal-access__hero-point-title"><?php esc_html_e( 'Entre sem depender do link rapido', 'dps-client-portal' ); ?></strong>
                    <p><?php esc_html_e( 'Depois de salvar a senha, voce pode voltar ao portal com menos etapas.', 'dps-client-portal' ); ?></p>
                </article>

                <article class="dps-portal-access__hero-point" role="listitem">
                    <span class="dps-portal-access__hero-point-label"><?php esc_html_e( 'Suporte', 'dps-client-portal' ); ?></span>
                    <strong class="dps-portal-access__hero-point-title"><?php esc_html_e( 'Peca um novo e-mail se precisar', 'dps-client-portal' ); ?></strong>
                    <p><?php esc_html_e( 'Se este link nao funcionar mais, volte para a tela inicial e solicite outro.', 'dps-client-portal' ); ?></p>
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

                <article class="dps-signature-panel dps-portal-reset-card">
                    <?php if ( $portal_reset_valid ) : ?>
                        <div class="dps-signature-panel__header">
                            <span class="dps-signature-hero__tag"><?php esc_html_e( 'Nova senha', 'dps-client-portal' ); ?></span>
                            <h2 class="dps-signature-panel__title"><?php esc_html_e( 'Defina sua nova credencial de acesso', 'dps-client-portal' ); ?></h2>
                            <p class="dps-signature-panel__intro"><?php esc_html_e( 'Use uma senha com pelo menos oito caracteres para manter o acesso recorrente ao portal.', 'dps-client-portal' ); ?></p>
                        </div>

                        <ul class="dps-signature-meta-list dps-portal-reset-card__meta">
                            <li>
                                <span><?php esc_html_e( 'E-mail deste acesso', 'dps-client-portal' ); ?></span>
                                <strong><?php echo esc_html( $reset_user_mail ); ?></strong>
                            </li>
                        </ul>

                        <form method="post" action="<?php echo esc_url( $portal_url ); ?>" class="dps-signature-form dps-portal-reset-card__form">
                            <?php wp_nonce_field( 'dps_portal_password_reset', '_dps_portal_password_reset_nonce' ); ?>
                            <input type="hidden" name="dps_portal_password_reset_submit" value="1" />
                            <input type="hidden" name="rp_login" value="<?php echo esc_attr( $portal_password_login ); ?>" />
                            <input type="hidden" name="rp_key" value="<?php echo esc_attr( $portal_password_key ); ?>" />

                            <div class="dps-signature-grid dps-signature-grid--2">
                                <div class="dps-signature-field">
                                    <label class="dps-signature-field__label" for="dps-portal-new-password"><?php esc_html_e( 'Nova senha', 'dps-client-portal' ); ?></label>
                                    <div class="dps-portal-access__password-wrap">
                                        <input id="dps-portal-new-password" type="password" name="dps_portal_new_password" autocomplete="new-password" aria-describedby="dps-portal-password-strength-value dps-portal-password-strength-tips" required />
                                        <button type="button" class="dps-portal-access__password-toggle" data-dps-password-toggle aria-controls="dps-portal-new-password" aria-pressed="false"><?php esc_html_e( 'Mostrar', 'dps-client-portal' ); ?></button>
                                    </div>
                                </div>

                                <div class="dps-signature-field">
                                    <label class="dps-signature-field__label" for="dps-portal-confirm-password"><?php esc_html_e( 'Confirmar senha', 'dps-client-portal' ); ?></label>
                                    <div class="dps-portal-access__password-wrap">
                                        <input id="dps-portal-confirm-password" type="password" name="dps_portal_confirm_password" autocomplete="new-password" aria-describedby="dps-portal-password-match" required />
                                        <button type="button" class="dps-portal-access__password-toggle" data-dps-password-toggle aria-controls="dps-portal-confirm-password" aria-pressed="false"><?php esc_html_e( 'Mostrar', 'dps-client-portal' ); ?></button>
                                    </div>
                                </div>

                                <div
                                    class="dps-signature-field dps-signature-field--full dps-portal-password-strength"
                                    data-dps-password-strength
                                    data-input="dps-portal-new-password"
                                    data-confirm="dps-portal-confirm-password"
                                    data-label-empty="<?php echo esc_attr__( 'Digite a nova senha para ver a forca.', 'dps-client-portal' ); ?>"
                                    data-label-weak="<?php echo esc_attr__( 'Senha fraca', 'dps-client-portal' ); ?>"
                                    data-label-fair="<?php echo esc_attr__( 'Senha em construcao', 'dps-client-portal' ); ?>"
                                    data-label-good="<?php echo esc_attr__( 'Senha boa', 'dps-client-portal' ); ?>"
                                    data-label-strong="<?php echo esc_attr__( 'Senha forte', 'dps-client-portal' ); ?>"
                                    data-label-match="<?php echo esc_attr__( 'As senhas conferem.', 'dps-client-portal' ); ?>"
                                    data-label-mismatch="<?php echo esc_attr__( 'As senhas ainda nao conferem.', 'dps-client-portal' ); ?>"
                                >
                                    <div class="dps-portal-password-strength__header">
                                        <span><?php esc_html_e( 'Forca da senha', 'dps-client-portal' ); ?></span>
                                        <strong id="dps-portal-password-strength-value" data-dps-password-strength-value aria-live="polite"><?php esc_html_e( 'Digite a nova senha para ver a forca.', 'dps-client-portal' ); ?></strong>
                                    </div>
                                    <div class="dps-portal-password-strength__meter" aria-hidden="true">
                                        <span data-dps-password-strength-bar></span>
                                    </div>
                                    <ul id="dps-portal-password-strength-tips" class="dps-portal-password-strength__tips" aria-label="<?php esc_attr_e( 'Dicas de composicao da senha', 'dps-client-portal' ); ?>">
                                        <li data-dps-password-tip="length"><?php esc_html_e( 'Use pelo menos 8 caracteres.', 'dps-client-portal' ); ?></li>
                                        <li data-dps-password-tip="case"><?php esc_html_e( 'Misture letras maiusculas e minusculas.', 'dps-client-portal' ); ?></li>
                                        <li data-dps-password-tip="number"><?php esc_html_e( 'Inclua ao menos um numero.', 'dps-client-portal' ); ?></li>
                                        <li data-dps-password-tip="symbol"><?php esc_html_e( 'Adicione um simbolo ou use uma frase mais longa.', 'dps-client-portal' ); ?></li>
                                    </ul>
                                    <p id="dps-portal-password-match" class="dps-portal-password-strength__match" data-dps-password-match aria-live="polite"></p>
                                </div>

                                <div class="dps-signature-field dps-signature-field--full">
                                    <label class="dps-signature-check" for="dps-portal-reset-remember">
                                        <input id="dps-portal-reset-remember" type="checkbox" name="dps_portal_remember" value="1" />
                                        <span><?php esc_html_e( 'Lembrar neste dispositivo apos entrar', 'dps-client-portal' ); ?></span>
                                    </label>
                                </div>
                            </div>

                            <div class="dps-portal-auth-card__actions">
                                <button type="submit" class="dps-signature-button">
                                    <span class="dps-signature-button__text"><?php esc_html_e( 'Salvar senha e entrar', 'dps-client-portal' ); ?></span>
                                </button>
                            </div>
                        </form>
                    <?php else : ?>
                        <div class="dps-signature-panel__header">
                            <span class="dps-signature-hero__tag"><?php echo esc_html( $reset_tag ); ?></span>
                            <h2 class="dps-signature-panel__title"><?php echo esc_html( $reset_title ); ?></h2>
                            <p class="dps-signature-panel__intro"><?php echo esc_html( $reset_intro ); ?></p>
                        </div>

                        <div class="dps-portal-auth-card__actions">
                            <a class="dps-signature-button" href="<?php echo esc_url( $portal_url ); ?>">
                                <span class="dps-signature-button__text"><?php esc_html_e( 'Voltar para a tela de acesso', 'dps-client-portal' ); ?></span>
                            </a>
                        </div>
                    <?php endif; ?>
                </article>
            </div>

            <aside class="dps-portal-access__rail">
                <article class="dps-signature-panel dps-portal-access__method-card">
                    <div class="dps-signature-panel__header">
                        <span class="dps-signature-hero__tag"><?php esc_html_e( 'Boas praticas', 'dps-client-portal' ); ?></span>
                        <h2 class="dps-signature-panel__title"><?php esc_html_e( 'Use uma senha facil de lembrar e dificil de adivinhar', 'dps-client-portal' ); ?></h2>
                    </div>

                    <div class="dps-portal-access__method-list">
                        <article class="dps-portal-access__method-item">
                            <h3><?php esc_html_e( 'No minimo 8 caracteres', 'dps-client-portal' ); ?></h3>
                            <p><?php esc_html_e( 'Combine letras, numeros e elementos que facam sentido para voce.', 'dps-client-portal' ); ?></p>
                        </article>

                        <article class="dps-portal-access__method-item">
                            <h3><?php esc_html_e( 'Recupere pelo e-mail', 'dps-client-portal' ); ?></h3>
                            <p><?php esc_html_e( 'Se esquecer a senha depois, a mesma tela inicial permite gerar um novo link de redefinicao.', 'dps-client-portal' ); ?></p>
                        </article>
                    </div>
                </article>

                <article class="dps-signature-panel dps-portal-access__support-card">
                    <div class="dps-signature-panel__header">
                        <span class="dps-signature-hero__tag"><?php esc_html_e( 'Proximo passo', 'dps-client-portal' ); ?></span>
                        <h2 class="dps-signature-panel__title"><?php esc_html_e( 'Depois de salvar, voce volta para o portal com sua propria senha', 'dps-client-portal' ); ?></h2>
                        <p class="dps-signature-panel__intro"><?php esc_html_e( 'O fluxo permanece no mesmo portal e continua ligado ao cadastro atual do tutor.', 'dps-client-portal' ); ?></p>
                    </div>

                    <div class="dps-portal-auth-card__actions">
                        <a class="dps-signature-button dps-signature-button--ghost" href="<?php echo esc_url( $portal_url ); ?>">
                            <span class="dps-signature-button__text"><?php esc_html_e( 'Voltar para a tela inicial', 'dps-client-portal' ); ?></span>
                        </a>
                    </div>
                </article>
            </aside>
        </div>
    </section>
</div>
