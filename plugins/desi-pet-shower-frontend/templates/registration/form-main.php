<?php
/**
 * Template: Registration V2 — Form Main
 *
 * Wrapper principal do formulário nativo de cadastro M3 Expressive.
 * Será expandido nas próximas subfases (7.2) com seções de cliente e pet.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 *
 * @var array<string, string> $atts           Atributos do shortcode.
 * @var string                $theme          Tema: light|dark.
 * @var bool                  $show_pets      Se exibe seção de pets.
 * @var bool                  $show_marketing Se exibe opt-in de marketing.
 * @var bool                  $compact        Modo compacto.
 * @var string[]              $errors         Erros de validação.
 * @var array<string, mixed>  $data           Dados preenchidos (sticky form).
 * @var string                $form_action    URL do action do form.
 * @var string                $nonce_field    Campo nonce HTML.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="dps-v2-registration" data-theme="<?php echo esc_attr( $theme ?? 'light' ); ?>">

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
            <div class="dps-v2-alert dps-v2-alert--error" role="alert">
                <div class="dps-v2-alert__content">
                    <?php foreach ( $errors as $err ) : ?>
                        <p><?php echo esc_html( $err ); ?></p>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Form (placeholder — será expandido na Fase 7.2) -->
    <form
        method="post"
        action="<?php echo esc_url( $form_action ); ?>"
        class="dps-v2-registration__form"
        novalidate
    >
        <?php
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_nonce_field() output
        echo $nonce_field;
        ?>

        <p class="dps-v2-typescale-body-medium dps-v2-color-on-surface-variant">
            <?php esc_html_e( 'Formulário nativo V2 em construção. Ative a flag registration_v2 para testar.', 'dps-frontend-addon' ); ?>
        </p>

    </form>

</div>
