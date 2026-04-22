<?php
/**
 * Template: Signature registration form shell.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 *
 * @var DPS_Template_Engine      $engine
 * @var array<string, string>    $atts
 * @var string                   $theme
 * @var bool                     $show_pets
 * @var bool                     $show_marketing
 * @var bool                     $compact
 * @var string[]                 $errors
 * @var array<string, mixed>     $data
 * @var string                   $form_action
 * @var string                   $nonce_field
 * @var bool                     $success
 * @var bool                     $duplicate_warning
 * @var int                      $duplicate_client_id
 * @var array<string, string>    $field_errors
 * @var array<string, string[]>  $breed_data
 * @var bool                     $recaptcha_enabled
 * @var string                   $recaptcha_site_key
 * @var string                   $booking_url
 * @var bool                     $email_confirmation_enabled
 * @var array<string, string>|null $registration_notice
 * @var string                   $google_api_key
 * @var int                      $form_started_at
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$success                    = $success ?? false;
$duplicate_warning          = $duplicate_warning ?? false;
$duplicate_client_id        = $duplicate_client_id ?? 0;
$field_errors               = $field_errors ?? [];
$breed_data                 = $breed_data ?? [];
$recaptcha_enabled          = $recaptcha_enabled ?? false;
$recaptcha_site_key         = $recaptcha_site_key ?? '';
$booking_url                = $booking_url ?? '';
$email_confirmation_enabled = $email_confirmation_enabled ?? false;
$registration_notice        = $registration_notice ?? null;
$google_api_key             = $google_api_key ?? '';
$form_started_at            = $form_started_at ?? time();
$engine                     = $engine ?? null;
$submit_label               = $email_confirmation_enabled
    ? __( 'Cadastrar e confirmar e-mail', 'dps-frontend-addon' )
    : __( 'Cadastrar e continuar', 'dps-frontend-addon' );
?>

<?php if ( $success ) : ?>
    <?php
    if ( $engine ) {
        echo $engine->render(
            'registration/form-success.php',
            [
                'client_id'                  => $data['client_id'] ?? 0,
                'pet_ids'                    => $data['pet_ids'] ?? [],
                'redirect_url'               => $atts['redirect_url'] ?? '',
                'booking_url'                => $booking_url,
                'email_confirmation_enabled' => $email_confirmation_enabled,
                'email_confirmation_sent'    => ! empty( $data['email_confirmation_sent'] ),
            ]
        ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
    ?>
<?php else : ?>
<div class="dps-signature-shell dps-signature-shell--registration dps-registration-signature" data-theme="<?php echo esc_attr( $theme ?? 'light' ); ?>">
    <section class="dps-signature-panel dps-registration-signature__workspace" aria-labelledby="dps-registration-title">
        <header class="dps-registration-signature__masthead">
            <div class="dps-registration-signature__masthead-copy">
                <h1 id="dps-registration-title" class="dps-signature-panel__title"><?php esc_html_e( 'Cadastro de tutor e pets', 'dps-frontend-addon' ); ?></h1>
            </div>
        </header>

        <div class="dps-signature-form__notice-stack" aria-live="polite">
            <?php if ( $registration_notice && ! empty( $registration_notice['type'] ) ) : ?>
                <article class="dps-signature-notice dps-signature-notice--<?php echo esc_attr( $registration_notice['type'] ); ?>">
                    <?php if ( ! empty( $registration_notice['title'] ) ) : ?>
                        <h3 class="dps-signature-notice__title"><?php echo esc_html( $registration_notice['title'] ); ?></h3>
                    <?php endif; ?>
                    <?php if ( ! empty( $registration_notice['description'] ) ) : ?>
                        <p class="dps-signature-notice__text"><?php echo esc_html( $registration_notice['description'] ); ?></p>
                    <?php endif; ?>
                </article>
            <?php endif; ?>

            <?php
            if ( $engine ) {
                echo $engine->render( 'registration/form-error.php', [ 'errors' => $errors ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            ?>
        </div>

        <form
            method="post"
            action="<?php echo esc_url( $form_action ); ?>"
            class="dps-signature-form dps-registration-signature__form"
            id="dps-registration-signature-form"
            novalidate
            <?php echo $recaptcha_enabled ? 'data-recaptcha-site-key="' . esc_attr( $recaptcha_site_key ) . '"' : ''; ?>
        >
            <?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <input type="hidden" name="dps_reg_action" value="register_v2" />
            <input type="hidden" name="dps_form_started_at" value="<?php echo esc_attr( (string) $form_started_at ); ?>" />

            <div class="dps-signature-form__visually-hidden" aria-hidden="true">
                <label for="dps-registration-website"><?php esc_html_e( 'Website', 'dps-frontend-addon' ); ?></label>
                <input type="text" id="dps-registration-website" name="dps_website_url" value="" tabindex="-1" autocomplete="off" />
            </div>

            <?php
            if ( $engine ) {
                echo $engine->render(
                    'registration/form-client-data.php',
                    [
                        'data'            => $data,
                        'field_errors'    => $field_errors,
                        'google_api_key'  => $google_api_key,
                    ]
                ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            ?>

            <?php if ( $show_pets && $engine ) : ?>
                <?php
                echo $engine->render(
                    'registration/form-pet-data.php',
                    [
                        'data'         => $data,
                        'field_errors' => $field_errors,
                        'breed_data'   => $breed_data,
                    ]
                ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                ?>
            <?php endif; ?>

            <section class="dps-signature-section" id="dps-registration-section-submit">
                <div class="dps-signature-section__header">
                    <h2 class="dps-signature-section__title"><?php esc_html_e( 'Enviar', 'dps-frontend-addon' ); ?></h2>
                </div>

                <div class="dps-signature-grid dps-signature-grid--2">
                    <?php if ( $show_marketing ) : ?>
                        <div class="dps-signature-field dps-signature-field--full">
                            <label class="dps-signature-check" for="dps-registration-marketing">
                                <input type="hidden" name="marketing_optin" value="" />
                                <input
                                    type="checkbox"
                                    id="dps-registration-marketing"
                                    name="marketing_optin"
                                    value="1"
                                    <?php checked( ! empty( $data['marketing_optin'] ) ); ?>
                                />
                                <span><?php esc_html_e( 'Quero receber novidades, lembretes e condições especiais do pet shop.', 'dps-frontend-addon' ); ?></span>
                            </label>
                        </div>
                    <?php endif; ?>

                    <div class="dps-signature-field dps-signature-field--full">
                        <div class="dps-registration-signature__hook-surface">
                            <?php do_action( 'dps_registration_after_fields' ); ?>
                        </div>
                    </div>

                    <?php if ( $duplicate_warning && $engine ) : ?>
                        <div class="dps-signature-field dps-signature-field--full">
                            <?php
                            echo $engine->render(
                                'registration/form-duplicate-warning.php',
                                [
                                    'duplicate_client_id' => $duplicate_client_id,
                                    'confirmed'           => ! empty( $data['dps_confirm_duplicate'] ),
                                ]
                            ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ( $recaptcha_enabled ) : ?>
                    <input type="hidden" name="recaptcha_token" id="dps-registration-recaptcha-token" value="" />
                <?php endif; ?>

                <div class="dps-signature-actions">
                    <button
                        type="submit"
                        class="dps-signature-button dps-registration-signature__submit"
                        data-dps-submit-button
                        data-loading-label="<?php echo esc_attr__( 'Enviando cadastro…', 'dps-frontend-addon' ); ?>"
                    >
                        <span class="dps-signature-button__text"><?php echo esc_html( $submit_label ); ?></span>
                        <span class="dps-signature-button__loader" aria-hidden="true"></span>
                    </button>
                </div>
            </section>
        </form>
    </section>
</div>
<?php endif; ?>
