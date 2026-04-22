<?php
/**
 * Template: Signature registration success state.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 *
 * @var int    $client_id
 * @var int[]  $pet_ids
 * @var string $redirect_url
 * @var string $booking_url
 * @var bool   $email_confirmation_enabled
 * @var bool   $email_confirmation_sent
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$pet_ids                    = $pet_ids ?? [];
$booking_url                = $booking_url ?? '';
$email_confirmation_enabled = $email_confirmation_enabled ?? false;
$email_confirmation_sent    = $email_confirmation_sent ?? false;
$primary_message            = $email_confirmation_enabled && $email_confirmation_sent
    ? __( 'Cadastro recebido. Agora confirme seu e-mail para ativar o acesso.', 'dps-frontend-addon' )
    : __( 'Cadastro concluído com sucesso.', 'dps-frontend-addon' );
$secondary_message          = $email_confirmation_enabled && $email_confirmation_sent
    ? __( 'Enviamos um link de confirmação para o e-mail informado. Depois disso, siga para o agendamento.', 'dps-frontend-addon' )
    : __( 'Seus dados já estão salvos.', 'dps-frontend-addon' );
?>

<div class="dps-registration dps-registration--success">
    <section class="dps-registration__hero dps-registration__hero--success">
        <div class="dps-registration__success-mark" aria-hidden="true">✓</div>
        <div class="dps-registration__hero-copy">
            <h1 class="dps-registration__hero-title"><?php echo esc_html( $primary_message ); ?></h1>
            <p class="dps-registration__hero-lead"><?php echo esc_html( $secondary_message ); ?></p>
        </div>
    </section>

    <section class="dps-registration__stage dps-registration__stage--success">
        <div class="dps-registration-metrics">
            <article class="dps-registration-metric">
                <span class="dps-registration-metric__label"><?php esc_html_e( 'Pets neste envio', 'dps-frontend-addon' ); ?></span>
                <strong class="dps-registration-metric__value"><?php echo esc_html( (string) count( $pet_ids ) ); ?></strong>
            </article>
        </div>

        <div class="dps-registration__footer">
            <?php if ( ! $email_confirmation_enabled && '' !== $booking_url ) : ?>
                <a href="<?php echo esc_url( $booking_url ); ?>" class="dps-registration-button dps-registration-button--primary">
                    <span class="dps-registration-button__text"><?php esc_html_e( 'Seguir para o agendamento', 'dps-frontend-addon' ); ?></span>
                </a>
            <?php endif; ?>
        </div>
    </section>
</div>
