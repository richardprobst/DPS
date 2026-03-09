<?php
/**
 * Template da tela de criacao ou redefinicao de senha.
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
<div class="dps-client-portal-access-page">
    <section class="dps-portal-entry dps-portal-entry--reset" aria-labelledby="dps-portal-reset-title">
        <div class="dps-portal-entry__hero dps-portal-entry__hero--compact">
            <div class="dps-portal-entry__hero-copy">
                <span class="dps-portal-entry__eyebrow"><?php esc_html_e( 'Senha do portal', 'dps-client-portal' ); ?></span>
                <h1 id="dps-portal-reset-title" class="dps-portal-entry__title"><?php esc_html_e( 'Criar ou redefinir senha', 'dps-client-portal' ); ?></h1>
                <p class="dps-portal-entry__lead"><?php esc_html_e( 'Depois de salvar a nova senha, voce volta a acessar o portal usando sempre o e-mail cadastrado no cliente.', 'dps-client-portal' ); ?></p>
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

        <div class="dps-portal-entry__grid dps-portal-entry__grid--single">
            <article class="dps-portal-entry__panel">
                <span class="dps-portal-entry__panel-tag"><?php esc_html_e( 'Nova senha', 'dps-client-portal' ); ?></span>
                <?php if ( $portal_reset_valid ) : ?>
                    <h2 class="dps-portal-entry__panel-title"><?php esc_html_e( 'Defina sua senha de acesso', 'dps-client-portal' ); ?></h2>
                    <p class="dps-portal-entry__panel-text"><?php esc_html_e( 'Use pelo menos 8 caracteres. Este acesso fica vinculado ao e-mail cadastrado no cliente.', 'dps-client-portal' ); ?></p>

                    <div class="dps-portal-entry__identity"><?php echo esc_html( $reset_user_mail ); ?></div>

                    <form method="post" action="<?php echo esc_url( $portal_url ); ?>" class="dps-portal-entry__login-form">
                        <?php wp_nonce_field( 'dps_portal_password_reset', '_dps_portal_password_reset_nonce' ); ?>
                        <input type="hidden" name="dps_portal_password_reset_submit" value="1" />
                        <input type="hidden" name="rp_login" value="<?php echo esc_attr( $portal_password_login ); ?>" />
                        <input type="hidden" name="rp_key" value="<?php echo esc_attr( $portal_password_key ); ?>" />

                        <div class="dps-portal-entry__field-group">
                            <label class="dps-portal-entry__label" for="dps-portal-new-password"><?php esc_html_e( 'Nova senha', 'dps-client-portal' ); ?></label>
                            <input id="dps-portal-new-password" class="dps-portal-entry__input" type="password" name="dps_portal_new_password" autocomplete="new-password" required />
                        </div>

                        <div class="dps-portal-entry__field-group">
                            <label class="dps-portal-entry__label" for="dps-portal-confirm-password"><?php esc_html_e( 'Confirmar senha', 'dps-client-portal' ); ?></label>
                            <input id="dps-portal-confirm-password" class="dps-portal-entry__input" type="password" name="dps_portal_confirm_password" autocomplete="new-password" required />
                        </div>

                        <label class="dps-portal-entry__check">
                            <input type="checkbox" name="dps_portal_remember" value="1" />
                            <span><?php esc_html_e( 'Lembrar neste dispositivo apos entrar', 'dps-client-portal' ); ?></span>
                        </label>

                        <button type="submit" class="dps-portal-entry__button dps-portal-entry__button--primary">
                            <?php esc_html_e( 'Salvar senha e entrar', 'dps-client-portal' ); ?>
                        </button>
                    </form>
                <?php else : ?>
                    <h2 class="dps-portal-entry__panel-title"><?php esc_html_e( 'Solicite um novo e-mail', 'dps-client-portal' ); ?></h2>
                    <p class="dps-portal-entry__panel-text"><?php esc_html_e( 'Este link nao pode mais ser usado. Volte para a tela inicial do portal e solicite um novo envio.', 'dps-client-portal' ); ?></p>
                    <a class="dps-portal-entry__button dps-portal-entry__button--primary" href="<?php echo esc_url( $portal_url ); ?>">
                        <?php esc_html_e( 'Voltar para a tela de acesso', 'dps-client-portal' ); ?>
                    </a>
                <?php endif; ?>
            </article>
        </div>
    </section>
</div>
