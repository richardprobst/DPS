<?php
/**
 * Signature password reset screen.
 *
 * @package DPS_Client_Portal
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$messages        = isset( $portal_access_context['messages'] ) && is_array( $portal_access_context['messages'] ) ? $portal_access_context['messages'] : [];
$portal_url      = isset( $portal_access_context['portal_url'] ) && is_string( $portal_access_context['portal_url'] ) ? $portal_access_context['portal_url'] : home_url( '/portal-cliente/' );
$reset_user_mail = $portal_reset_valid && $portal_reset_user instanceof WP_User ? $portal_reset_user->user_email : $portal_password_login;
?>

<div class="dps-signature-shell dps-signature-shell--auth dps-portal-signature dps-client-portal-access-page">
    <section class="dps-portal-entry dps-portal-entry--reset" aria-labelledby="dps-portal-reset-title">
        <section class="dps-signature-hero dps-portal-signature__hero dps-portal-signature__hero--compact">
            <div class="dps-portal-signature__hero-copy">
                <span class="dps-signature-hero__eyebrow"><?php esc_html_e( 'Senha do portal', 'dps-client-portal' ); ?></span>
                <h1 id="dps-portal-reset-title" class="dps-signature-hero__title"><?php esc_html_e( 'Criar ou redefinir senha', 'dps-client-portal' ); ?></h1>
                <p class="dps-signature-hero__lead"><?php esc_html_e( 'Salve uma nova senha para entrar com seu e-mail.', 'dps-client-portal' ); ?></p>
            </div>
        </section>

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

        <div class="dps-portal-entry__grid dps-portal-entry__grid--single">
            <article class="dps-portal-entry__panel dps-signature-panel">
                <?php if ( $portal_reset_valid ) : ?>
                    <div class="dps-signature-panel__header">
                        <span class="dps-signature-hero__tag"><?php esc_html_e( 'Nova senha', 'dps-client-portal' ); ?></span>
                        <h2 class="dps-signature-panel__title"><?php esc_html_e( 'Defina sua nova senha de acesso', 'dps-client-portal' ); ?></h2>
                    </div>

                    <ul class="dps-signature-meta-list">
                        <li>
                            <span><?php esc_html_e( 'E-mail deste acesso', 'dps-client-portal' ); ?></span>
                            <strong><?php echo esc_html( $reset_user_mail ); ?></strong>
                        </li>
                    </ul>

                    <form method="post" action="<?php echo esc_url( $portal_url ); ?>" class="dps-signature-form">
                        <?php wp_nonce_field( 'dps_portal_password_reset', '_dps_portal_password_reset_nonce' ); ?>
                        <input type="hidden" name="dps_portal_password_reset_submit" value="1" />
                        <input type="hidden" name="rp_login" value="<?php echo esc_attr( $portal_password_login ); ?>" />
                        <input type="hidden" name="rp_key" value="<?php echo esc_attr( $portal_password_key ); ?>" />

                        <div class="dps-signature-grid dps-signature-grid--2">
                            <div class="dps-signature-field">
                                <label class="dps-signature-field__label" for="dps-portal-new-password"><?php esc_html_e( 'Nova senha', 'dps-client-portal' ); ?></label>
                                <input id="dps-portal-new-password" type="password" name="dps_portal_new_password" autocomplete="new-password" required />
                            </div>

                            <div class="dps-signature-field">
                                <label class="dps-signature-field__label" for="dps-portal-confirm-password"><?php esc_html_e( 'Confirmar senha', 'dps-client-portal' ); ?></label>
                                <input id="dps-portal-confirm-password" type="password" name="dps_portal_confirm_password" autocomplete="new-password" required />
                            </div>

                            <div class="dps-signature-field dps-signature-field--full">
                                <label class="dps-signature-check" for="dps-portal-reset-remember">
                                    <input id="dps-portal-reset-remember" type="checkbox" name="dps_portal_remember" value="1" />
                                    <span><?php esc_html_e( 'Lembrar neste dispositivo após entrar', 'dps-client-portal' ); ?></span>
                                </label>
                            </div>
                        </div>

                        <div class="dps-signature-actions dps-signature-actions--end">
                            <button type="submit" class="dps-signature-button">
                                <span class="dps-signature-button__text"><?php esc_html_e( 'Salvar senha e entrar', 'dps-client-portal' ); ?></span>
                            </button>
                        </div>
                    </form>
                <?php else : ?>
                    <div class="dps-signature-panel__header">
                        <span class="dps-signature-hero__tag"><?php esc_html_e( 'Link inválido', 'dps-client-portal' ); ?></span>
                        <h2 class="dps-signature-panel__title"><?php esc_html_e( 'Solicite um novo e-mail para redefinir a senha', 'dps-client-portal' ); ?></h2>
                    </div>

                    <div class="dps-signature-actions dps-signature-actions--end">
                        <a class="dps-signature-button" href="<?php echo esc_url( $portal_url ); ?>">
                            <span class="dps-signature-button__text"><?php esc_html_e( 'Voltar para a tela de acesso', 'dps-client-portal' ); ?></span>
                        </a>
                    </div>
                <?php endif; ?>
            </article>
        </div>
    </section>
</div>
