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
    ? __( 'Enviamos um link de confirmação para o e-mail informado. Depois de confirmar, você poderá seguir para o agendamento com mais segurança.', 'dps-frontend-addon' )
    : __( 'Seus dados foram salvos no novo fluxo DPS Signature. Agora você já pode seguir para o próximo passo do atendimento.', 'dps-frontend-addon' );
?>

<div class="dps-signature-shell dps-signature-shell--registration dps-registration-signature dps-registration-signature--success">
    <section class="dps-signature-panel dps-registration-signature__success-panel">
        <div class="dps-registration-signature__success-mark" aria-hidden="true">✓</div>
        <span class="dps-signature-hero__tag"><?php esc_html_e( 'Cadastro concluído', 'dps-frontend-addon' ); ?></span>
        <h1 class="dps-signature-panel__title"><?php echo esc_html( $primary_message ); ?></h1>
        <p class="dps-signature-panel__intro"><?php echo esc_html( $secondary_message ); ?></p>

        <ul class="dps-signature-meta-list">
            <li>
                <span><?php esc_html_e( 'Pets vinculados neste envio', 'dps-frontend-addon' ); ?></span>
                <strong><?php echo esc_html( (string) count( $pet_ids ) ); ?></strong>
            </li>
            <li>
                <span><?php esc_html_e( 'Fluxo utilizado', 'dps-frontend-addon' ); ?></span>
                <strong><?php esc_html_e( 'DPS Signature', 'dps-frontend-addon' ); ?></strong>
            </li>
        </ul>

        <div class="dps-signature-actions dps-signature-actions--end">
            <?php if ( ! $email_confirmation_enabled && '' !== $booking_url ) : ?>
                <a href="<?php echo esc_url( $booking_url ); ?>" class="dps-signature-button">
                    <span class="dps-signature-button__text"><?php esc_html_e( 'Seguir para o agendamento', 'dps-frontend-addon' ); ?></span>
                </a>
            <?php endif; ?>
        </div>
    </section>
</div>
