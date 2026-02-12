<?php
/**
 * Template: Registration V2 — Form Main
 *
 * Wrapper principal do formulário nativo de cadastro M3 Expressive.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 *
 * @var DPS_Template_Engine    $engine           Template engine para sub-renders.
 * @var array<string, string>  $atts             Atributos do shortcode.
 * @var string                 $theme            Tema: light|dark.
 * @var bool                   $show_pets        Se exibe seção de pets.
 * @var bool                   $show_marketing   Se exibe opt-in de marketing.
 * @var bool                   $compact          Modo compacto.
 * @var string[]               $errors           Erros de validação.
 * @var array<string, mixed>   $data             Dados preenchidos (sticky form).
 * @var string                 $form_action      URL do action do form.
 * @var string                 $nonce_field      Campo nonce HTML.
 * @var bool                   $success          Se o registro foi bem-sucedido.
 * @var bool                   $duplicate_warning Se deve exibir aviso de duplicata.
 * @var int                    $duplicate_client_id ID do cliente duplicado.
 * @var array<string, string>  $field_errors     Erros por campo.
 * @var array<string, string[]> $breed_data      Dataset de raças por espécie.
 * @var bool                   $recaptcha_enabled Se reCAPTCHA está ativo.
 * @var string                 $recaptcha_site_key Site key do reCAPTCHA.
 * @var string                 $booking_url      URL da página de agendamento.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$success             = $success ?? false;
$duplicate_warning   = $duplicate_warning ?? false;
$duplicate_client_id = $duplicate_client_id ?? 0;
$field_errors        = $field_errors ?? [];
$breed_data          = $breed_data ?? [];
$recaptcha_enabled   = $recaptcha_enabled ?? false;
$recaptcha_site_key  = $recaptcha_site_key ?? '';
$booking_url         = $booking_url ?? '';
$engine              = $engine ?? null;
?>

<?php if ( $success ) : ?>
    <?php
    if ( $engine ) {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template engine renders escaped content
        echo $engine->render( 'registration/form-success.php', [
            'client_id'    => $data['client_id'] ?? 0,
            'pet_ids'      => $data['pet_ids'] ?? [],
            'redirect_url' => $atts['redirect_url'] ?? '',
            'booking_url'  => $booking_url,
        ] );
    }
    ?>
<?php else : ?>

<div class="dps-v2-registration <?php echo $compact ? 'dps-v2-registration--compact' : ''; ?>" data-theme="<?php echo esc_attr( $theme ?? 'light' ); ?>">

    <!-- Header -->
    <div class="dps-v2-registration__header">
        <h2 class="dps-v2-typescale-headline-large">
            <?php esc_html_e( 'Cadastre-se', 'dps-frontend-addon' ); ?>
        </h2>
        <p class="dps-v2-typescale-body-large dps-v2-color-on-surface-variant">
            <?php esc_html_e( 'Preencha os dados abaixo para criar sua conta', 'dps-frontend-addon' ); ?>
        </p>
    </div>

    <!-- Errors -->
    <?php if ( ! empty( $errors ) ) : ?>
        <div class="dps-v2-registration__errors">
            <?php
            if ( $engine ) {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo $engine->render( 'registration/form-error.php', [ 'errors' => $errors ] );
            }
            ?>
        </div>
    <?php endif; ?>

    <!-- Form -->
    <form
        method="post"
        action="<?php echo esc_url( $form_action ); ?>"
        class="dps-v2-registration__form"
        novalidate
        <?php echo $recaptcha_enabled ? 'data-recaptcha-site-key="' . esc_attr( $recaptcha_site_key ) . '"' : ''; ?>
    >
        <?php
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_nonce_field() output
        echo $nonce_field;
        ?>
        <input type="hidden" name="dps_reg_action" value="register_v2" />

        <?php // Honeypot anti-spam ?>
        <div style="display:none !important" aria-hidden="true">
            <input type="text" name="dps_website_url" value="" tabindex="-1" autocomplete="off" />
        </div>

        <?php // Seção Cliente ?>
        <?php
        if ( $engine ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $engine->render( 'registration/form-client-data.php', [
                'data'         => $data,
                'field_errors' => $field_errors,
            ] );
        }
        ?>

        <?php // Seção Pet (condicional) ?>
        <?php if ( $show_pets ) : ?>
            <?php
            if ( $engine ) {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo $engine->render( 'registration/form-pet-data.php', [
                    'data'         => $data,
                    'field_errors' => $field_errors,
                    'breed_data'   => $breed_data,
                ] );
            }
            ?>
        <?php endif; ?>

        <?php // Aviso de duplicata (admin) ?>
        <?php if ( $duplicate_warning ) : ?>
            <?php
            if ( $engine ) {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo $engine->render( 'registration/form-duplicate-warning.php', [
                    'duplicate_client_id' => $duplicate_client_id,
                ] );
            }
            ?>
        <?php endif; ?>

        <?php // Marketing Opt-in ?>
        <?php if ( $show_marketing ) : ?>
            <div class="dps-v2-field dps-v2-field--checkbox">
                <label for="dps-v2-marketing_optin" class="dps-v2-field__checkbox-label">
                    <input
                        type="checkbox"
                        id="dps-v2-marketing_optin"
                        name="marketing_optin"
                        value="1"
                        class="dps-v2-field__checkbox"
                        <?php checked( ! empty( $data['marketing_optin'] ) ); ?>
                    />
                    <span class="dps-v2-field__checkbox-text">
                        <?php esc_html_e( 'Desejo receber novidades e promoções', 'dps-frontend-addon' ); ?>
                    </span>
                </label>
            </div>
        <?php endif; ?>

        <?php // Campos extras via hook (Loyalty referral code) ?>
        <?php do_action( 'dps_registration_after_fields' ); ?>

        <?php // reCAPTCHA token ?>
        <?php if ( $recaptcha_enabled ) : ?>
            <input type="hidden" name="recaptcha_token" id="dps-v2-recaptcha-token" value="" />
        <?php endif; ?>

        <?php // Submit ?>
        <div class="dps-v2-form-actions">
            <button
                type="submit"
                class="dps-v2-button dps-v2-button--primary dps-v2-button--loading"
                data-loading="true"
            >
                <span class="dps-v2-button__text"><?php esc_html_e( 'Cadastrar', 'dps-frontend-addon' ); ?></span>
                <span class="dps-v2-button__loader" aria-hidden="true"></span>
            </button>
        </div>

    </form>

</div>

<?php endif; ?>
