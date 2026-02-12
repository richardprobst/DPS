<?php
/**
 * Template: Registration V2 — Success
 *
 * Exibido após registro bem-sucedido com CTA para agendamento.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 *
 * @var int    $client_id     ID do cliente criado.
 * @var int[]  $pet_ids       IDs dos pets criados.
 * @var string $redirect_url  URL de redirecionamento (se configurada).
 * @var string $booking_url   URL da página de agendamento.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$client_id    = $client_id ?? 0;
$pet_ids      = $pet_ids ?? [];
$redirect_url = $redirect_url ?? '';
$booking_url  = $booking_url ?? '';
?>

<div class="dps-v2-registration dps-v2-registration--success">
    <div class="dps-v2-card dps-v2-card--elevated">
        <div class="dps-v2-card__content" style="text-align: center; padding: var(--dps-spacing-xl, 2rem);">

            <div class="dps-v2-registration__success-icon" aria-hidden="true">✓</div>

            <h2 class="dps-v2-typescale-headline-medium">
                <?php esc_html_e( 'Cadastro Realizado!', 'dps-frontend-addon' ); ?>
            </h2>

            <p class="dps-v2-typescale-body-large dps-v2-color-on-surface-variant">
                <?php
                printf(
                    /* translators: %d: number of pets */
                    esc_html( _n(
                        'Seu cadastro foi criado com sucesso com %d pet.',
                        'Seu cadastro foi criado com sucesso com %d pets.',
                        count( $pet_ids ),
                        'dps-frontend-addon'
                    ) ),
                    count( $pet_ids )
                );
                ?>
            </p>

            <?php if ( '' !== $booking_url ) : ?>
                <div class="dps-v2-form-actions" style="justify-content: center; margin-top: var(--dps-spacing-lg, 1.5rem);">
                    <a href="<?php echo esc_url( $booking_url ); ?>" class="dps-v2-button dps-v2-button--primary">
                        <span class="dps-v2-button__text"><?php esc_html_e( 'Agendar Serviço', 'dps-frontend-addon' ); ?></span>
                    </a>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>
