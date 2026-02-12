<?php
/**
 * Template: Booking V2 — Login Required
 *
 * Exibido quando usuário não autenticado tenta acessar o agendamento.
 * Redireciona para a página de login com return URL.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 *
 * @var string $login_url URL da página de login com return.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="dps-v2-booking dps-v2-booking--login-required">
    <div class="dps-v2-card dps-v2-card--outlined">
        <div class="dps-v2-card__content" style="text-align: center; padding: var(--dps-spacing-xl, 2rem);">
            <h2 class="dps-v2-typescale-headline-medium">
                <?php esc_html_e( 'Acesso Restrito', 'dps-frontend-addon' ); ?>
            </h2>
            <p class="dps-v2-typescale-body-large dps-v2-color-on-surface-variant">
                <?php esc_html_e( 'Você precisa estar logado para agendar um serviço.', 'dps-frontend-addon' ); ?>
            </p>
            <a href="<?php echo esc_url( $login_url ); ?>" class="dps-v2-button dps-v2-button--primary">
                <span class="dps-v2-button__text"><?php esc_html_e( 'Fazer Login', 'dps-frontend-addon' ); ?></span>
            </a>
        </div>
    </div>
</div>
