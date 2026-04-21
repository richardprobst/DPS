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
$pet_count                  = isset( $data['pets'] ) && is_array( $data['pets'] ) ? count( $data['pets'] ) : 1;
$submit_label               = $email_confirmation_enabled
    ? __( 'Cadastrar & Confirmar E-mail', 'dps-frontend-addon' )
    : __( 'Cadastrar & Continuar', 'dps-frontend-addon' );
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
    <div class="dps-signature-shell__layout">
        <section class="dps-signature-hero dps-registration-signature__hero" aria-labelledby="dps-registration-title">
            <div class="dps-signature-hero__grid">
                <div class="dps-registration-signature__hero-copy">
                    <span class="dps-signature-hero__eyebrow"><?php esc_html_e( 'DPS Signature', 'dps-frontend-addon' ); ?></span>
                    <h1 id="dps-registration-title" class="dps-signature-hero__title"><?php esc_html_e( 'Crie seu cadastro completo de tutor & pets em poucos minutos.', 'dps-frontend-addon' ); ?></h1>
                    <p class="dps-signature-hero__lead"><?php esc_html_e( 'O novo fluxo segue o padrão visual DPS Signature/M3: direto, responsivo e preparado para confirmar dados, prevenir duplicidades e iniciar seu atendimento com mais segurança.', 'dps-frontend-addon' ); ?></p>

                    <ul class="dps-signature-hero__list" aria-label="<?php esc_attr_e( 'O que você pode informar neste cadastro', 'dps-frontend-addon' ); ?>">
                        <li><?php esc_html_e( 'Dados do tutor, contato, redes sociais e endereço com autocomplete quando disponível.', 'dps-frontend-addon' ); ?></li>
                        <li><?php esc_html_e( 'Um ou mais pets com porte, comportamento, observações e cuidados especiais.', 'dps-frontend-addon' ); ?></li>
                        <li><?php esc_html_e( 'Validação acessível, foco visível e proteção anti-spam sem camadas de cache.', 'dps-frontend-addon' ); ?></li>
                    </ul>
                </div>

                <aside class="dps-signature-hero__aside" aria-label="<?php esc_attr_e( 'Resumo do cadastro', 'dps-frontend-addon' ); ?>">
                    <article class="dps-signature-metric-card">
                        <p class="dps-signature-metric-card__value"><?php echo esc_html( sprintf( _n( '%d pet pronto para cadastro', '%d pets prontos para cadastro', $pet_count, 'dps-frontend-addon' ), $pet_count ) ); ?></p>
                        <p class="dps-signature-metric-card__note"><?php esc_html_e( 'Você pode adicionar mais pets antes de enviar. O formulário mantém os dados preenchidos se houver alguma validação pendente.', 'dps-frontend-addon' ); ?></p>
                    </article>

                    <article class="dps-signature-support-card">
                        <h2 class="dps-signature-support-card__title"><?php esc_html_e( 'Entrega orientada por etapas', 'dps-frontend-addon' ); ?></h2>
                        <p class="dps-signature-hero__support"><?php esc_html_e( 'Use a navegação lateral para ir direto ao bloco que deseja revisar. O envio só acontece quando os campos essenciais estiverem válidos.', 'dps-frontend-addon' ); ?></p>
                    </article>
                </aside>
            </div>
        </section>

        <aside class="dps-signature-step-rail dps-registration-signature__steps" aria-label="<?php esc_attr_e( 'Etapas do cadastro', 'dps-frontend-addon' ); ?>">
            <p class="dps-signature-step-rail__title"><?php esc_html_e( 'Etapas', 'dps-frontend-addon' ); ?></p>
            <ol class="dps-signature-step-list">
                <li>
                    <button type="button" data-dps-registration-step="client" data-dps-registration-section="dps-registration-section-client">
                        <span class="dps-signature-step-list__index">1</span>
                        <span class="dps-signature-step-list__text">
                            <span class="dps-signature-step-list__label"><?php esc_html_e( 'Tutor', 'dps-frontend-addon' ); ?></span>
                            <span class="dps-signature-step-list__detail"><?php esc_html_e( 'Contato, endereço e preferências.', 'dps-frontend-addon' ); ?></span>
                        </span>
                    </button>
                </li>
                <?php if ( $show_pets ) : ?>
                    <li>
                        <button type="button" data-dps-registration-step="pets" data-dps-registration-section="dps-registration-section-pets">
                            <span class="dps-signature-step-list__index">2</span>
                            <span class="dps-signature-step-list__text">
                                <span class="dps-signature-step-list__label"><?php esc_html_e( 'Pets', 'dps-frontend-addon' ); ?></span>
                                <span class="dps-signature-step-list__detail"><?php esc_html_e( 'Dados físicos, comportamento e cuidados.', 'dps-frontend-addon' ); ?></span>
                            </span>
                        </button>
                    </li>
                <?php endif; ?>
                <li>
                    <button type="button" data-dps-registration-step="finish" data-dps-registration-section="dps-registration-section-submit">
                        <span class="dps-signature-step-list__index"><?php echo esc_html( $show_pets ? '3' : '2' ); ?></span>
                        <span class="dps-signature-step-list__text">
                            <span class="dps-signature-step-list__label"><?php esc_html_e( 'Revisão & envio', 'dps-frontend-addon' ); ?></span>
                            <span class="dps-signature-step-list__detail"><?php esc_html_e( 'Opções finais, confirmação e envio do cadastro.', 'dps-frontend-addon' ); ?></span>
                        </span>
                    </button>
                </li>
            </ol>
        </aside>
    </div>

    <section class="dps-signature-panel dps-registration-signature__panel" aria-labelledby="dps-registration-panel-title">
        <div class="dps-signature-panel__header">
            <span class="dps-signature-hero__tag"><?php esc_html_e( 'Cadastro público', 'dps-frontend-addon' ); ?></span>
            <h2 id="dps-registration-panel-title" class="dps-signature-panel__title"><?php esc_html_e( 'Preencha seu cadastro com o novo padrão DPS Signature.', 'dps-frontend-addon' ); ?></h2>
            <p class="dps-signature-panel__intro"><?php esc_html_e( 'Todos os campos seguem o padrão visual M3/DPS Signature, com feedback inline, teclados corretos no mobile e compatibilidade com os contratos já usados pelas integrações do sistema.', 'dps-frontend-addon' ); ?></p>
        </div>

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
                    <p class="dps-signature-section__eyebrow"><?php esc_html_e( 'Etapa final', 'dps-frontend-addon' ); ?></p>
                    <h3 class="dps-signature-section__title"><?php esc_html_e( 'Revise suas opções antes de enviar.', 'dps-frontend-addon' ); ?></h3>
                    <p class="dps-signature-section__description"><?php esc_html_e( 'Se o sistema identificar um telefone duplicado ou algum campo obrigatório pendente, o formulário aponta exatamente onde revisar antes do envio final.', 'dps-frontend-addon' ); ?></p>
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
                    <p class="dps-signature-actions__hint">
                        <?php
                        echo esc_html(
                            $email_confirmation_enabled
                                ? __( 'Após o envio, você receberá um link para confirmar seu e-mail antes de prosseguir.', 'dps-frontend-addon' )
                                : __( 'Após o envio, você poderá seguir direto para o próximo passo do atendimento.', 'dps-frontend-addon' )
                        );
                        ?>
                    </p>

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
