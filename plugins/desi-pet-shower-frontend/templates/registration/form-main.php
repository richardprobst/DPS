<?php
/**
 * Template: Signature registration form shell.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 *
 * @var DPS_Template_Engine        $engine
 * @var array<string, string>      $atts
 * @var string                     $theme
 * @var bool                       $show_pets
 * @var bool                       $show_marketing
 * @var bool                       $compact
 * @var string[]                   $errors
 * @var array<string, mixed>       $data
 * @var string                     $form_action
 * @var string                     $nonce_field
 * @var bool                       $success
 * @var bool                       $duplicate_warning
 * @var int                        $duplicate_client_id
 * @var array<string, string>      $field_errors
 * @var array<string, string[]>    $breed_data
 * @var bool                       $recaptcha_enabled
 * @var string                     $recaptcha_site_key
 * @var string                     $booking_url
 * @var bool                       $email_confirmation_enabled
 * @var array<string, string>|null $registration_notice
 * @var string                     $google_api_key
 * @var int                        $form_started_at
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
$show_pets                  = $show_pets ?? true;
$show_marketing             = $show_marketing ?? true;
$final_preferences_count    = ! empty( $data['marketing_optin'] ) ? 1 : 0;
$final_preferences_summary  = sprintf(
    _n( '%d preenchido', '%d preenchidos', $final_preferences_count, 'dps-frontend-addon' ),
    $final_preferences_count
);
$submit_label               = $email_confirmation_enabled
    ? __( 'Cadastrar e confirmar e-mail', 'dps-frontend-addon' )
    : __( 'Cadastrar e continuar', 'dps-frontend-addon' );
$has_referral_value         = ! empty( $data['dps_referral_code'] );

ob_start();
do_action( 'dps_registration_after_fields' );
$after_fields_markup = trim( (string) ob_get_clean() );
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
<div class="dps-registration" data-theme="<?php echo esc_attr( $theme ?? 'light' ); ?>">
    <section class="dps-registration__hero" aria-labelledby="dps-registration-title">
        <div class="dps-registration__hero-copy">
            <h1 id="dps-registration-title" class="dps-registration__hero-title"><?php esc_html_e( 'Cadastro de tutor e pets', 'dps-frontend-addon' ); ?></h1>
        </div>
    </section>

    <section class="dps-registration__stage">
        <div class="dps-registration__notice-stack" aria-live="polite">
            <?php if ( $registration_notice && ! empty( $registration_notice['type'] ) ) : ?>
                <article class="dps-registration-notice dps-registration-notice--<?php echo esc_attr( $registration_notice['type'] ); ?>">
                    <?php if ( ! empty( $registration_notice['title'] ) ) : ?>
                        <h3 class="dps-registration-notice__title"><?php echo esc_html( $registration_notice['title'] ); ?></h3>
                    <?php endif; ?>
                    <?php if ( ! empty( $registration_notice['description'] ) ) : ?>
                        <p class="dps-registration-notice__text"><?php echo esc_html( $registration_notice['description'] ); ?></p>
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
            class="dps-registration-form"
            id="dps-registration-form"
            novalidate
            <?php echo $recaptcha_enabled ? 'data-recaptcha-site-key="' . esc_attr( $recaptcha_site_key ) . '"' : ''; ?>
        >
            <?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <input type="hidden" name="dps_reg_action" value="register_v2" />
            <input type="hidden" name="dps_form_started_at" value="<?php echo esc_attr( (string) $form_started_at ); ?>" />

            <div class="dps-registration__visually-hidden" aria-hidden="true">
                <label for="dps-registration-website"><?php esc_html_e( 'Website', 'dps-frontend-addon' ); ?></label>
                <input type="text" id="dps-registration-website" name="dps_website_url" value="" tabindex="-1" autocomplete="off" />
            </div>

            <?php
            if ( $engine ) {
                echo $engine->render(
                    'registration/form-client-data.php',
                    [
                        'data'           => $data,
                        'field_errors'   => $field_errors,
                        'google_api_key' => $google_api_key,
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

            <section class="dps-registration-section" id="dps-registration-section-submit">
                <?php if ( $duplicate_warning && $engine ) : ?>
                    <?php
                    echo $engine->render(
                        'registration/form-duplicate-warning.php',
                        [
                            'duplicate_client_id' => $duplicate_client_id,
                            'confirmed'           => ! empty( $data['dps_confirm_duplicate'] ),
                        ]
                    ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    ?>
                <?php endif; ?>

                <?php if ( $show_marketing || '' !== $after_fields_markup ) : ?>
                    <details class="dps-registration-disclosure dps-registration-disclosure--final" <?php echo ( $duplicate_warning || $has_referral_value ) ? 'open' : ''; ?>>
                        <summary>
                            <span class="dps-registration-disclosure__label"><?php esc_html_e( 'Preferências', 'dps-frontend-addon' ); ?></span>
                            <span class="dps-registration-disclosure__meta"><?php echo esc_html( $final_preferences_summary ); ?></span>
                        </summary>

                        <div class="dps-registration-disclosure__body">
                            <?php if ( $show_marketing ) : ?>
                                <div class="dps-registration-field dps-registration-field--full">
                                    <label class="dps-registration-check" for="dps-registration-marketing">
                                        <input type="hidden" name="marketing_optin" value="" />
                                        <input
                                            type="checkbox"
                                            id="dps-registration-marketing"
                                            name="marketing_optin"
                                            value="1"
                                            <?php checked( ! empty( $data['marketing_optin'] ) ); ?>
                                        />
                                        <span><?php esc_html_e( 'Quero receber lembretes, novidades e condições especiais do pet shop.', 'dps-frontend-addon' ); ?></span>
                                    </label>
                                </div>
                            <?php endif; ?>

                            <?php if ( '' !== $after_fields_markup ) : ?>
                                <div class="dps-registration__hook-surface">
                                    <?php echo $after_fields_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </details>
                <?php endif; ?>

                <?php if ( $recaptcha_enabled ) : ?>
                    <input type="hidden" name="recaptcha_token" id="dps-registration-recaptcha-token" value="" />
                <?php endif; ?>

                <div class="dps-registration__footer">
                    <button
                        type="submit"
                        class="dps-registration-button dps-registration-button--primary"
                        data-dps-submit-button
                        data-loading-label="<?php echo esc_attr__( 'Enviando cadastro…', 'dps-frontend-addon' ); ?>"
                    >
                        <span class="dps-registration-button__text"><?php echo esc_html( $submit_label ); ?></span>
                        <span class="dps-registration-button__loader" aria-hidden="true"></span>
                    </button>
                </div>
            </section>
        </form>
    </section>
</div>
<?php endif; ?>
