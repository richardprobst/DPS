<?php
/**
 * UI helpers for the Registration public form.
 *
 * @package DPS_Registration_Addon
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Small render helpers for DPS Signature registration UI blocks.
 */
class DPS_Registration_UX {

    /**
     * Renders the compact public intro block.
     *
     * @return string
     */
    public static function render_intro() {
        ob_start();
        ?>
        <section class="dps-registration-intro" aria-labelledby="dps-registration-intro-title">
            <p class="dps-registration-intro__eyebrow"><?php esc_html_e( 'Cadastro DESI PET SHOWER', 'dps-registration-addon' ); ?></p>
            <h3 id="dps-registration-intro-title"><?php esc_html_e( 'Tutor e pets em um único cadastro', 'dps-registration-addon' ); ?></h3>
            <p><?php esc_html_e( 'Informe os dados principais para que a equipe encontre seu cadastro, entenda os cuidados de cada pet e agilize o primeiro atendimento.', 'dps-registration-addon' ); ?></p>
        </section>
        <?php
        return ob_get_clean();
    }

    /**
     * Renders the mandatory fields note.
     *
     * @return string
     */
    public static function render_required_legend() {
        return '<p class="dps-required-legend"><span class="dps-required">*</span> ' . esc_html__( 'Campos obrigatórios', 'dps-registration-addon' ) . '</p>';
    }

    /**
     * Renders the live region used by JavaScript.
     *
     * @return string
     */
    public static function render_live_region() {
        return '<div id="dps-registration-live-region" class="dps-registration-live-region" aria-live="polite" aria-atomic="true"></div>';
    }

    /**
     * Opens a field group.
     *
     * @param string $title       Group title.
     * @param string $description Group description.
     * @param string $modifier    Optional class modifier.
     * @return string
     */
    public static function open_field_group( $title, $description = '', $modifier = '' ) {
        $classes = 'dps-field-group';
        if ( $modifier ) {
            $classes .= ' dps-field-group--' . sanitize_html_class( $modifier );
        }

        ob_start();
        ?>
        <section class="<?php echo esc_attr( $classes ); ?>">
            <div class="dps-field-group__header">
                <h5><?php echo esc_html( $title ); ?></h5>
                <?php if ( $description ) : ?>
                    <p><?php echo esc_html( $description ); ?></p>
                <?php endif; ?>
            </div>
            <div class="dps-field-group__body">
        <?php
        return ob_get_clean();
    }

    /**
     * Closes a field group.
     *
     * @return string
     */
    public static function close_field_group() {
        return '</div></section>';
    }

    /**
     * Opens an optional details disclosure.
     *
     * @param string $id          Disclosure ID.
     * @param string $title       Summary title.
     * @param string $description Short description.
     * @return string
     */
    public static function open_optional_details( $id, $title, $description = '' ) {
        ob_start();
        ?>
        <details class="dps-optional-details" id="<?php echo esc_attr( $id ); ?>">
            <summary>
                <span><?php echo esc_html( $title ); ?></span>
                <?php if ( $description ) : ?>
                    <small><?php echo esc_html( $description ); ?></small>
                <?php endif; ?>
            </summary>
            <div class="dps-optional-details__body">
        <?php
        return ob_get_clean();
    }

    /**
     * Closes an optional details disclosure.
     *
     * @return string
     */
    public static function close_optional_details() {
        return '</div></details>';
    }

    /**
     * Renders the draft controls shell.
     *
     * @param bool $has_draft Whether a persisted draft exists.
     * @return string
     */
    public static function render_draft_panel( $has_draft ) {
        ob_start();
        ?>
        <section class="dps-registration-draft" data-dps-draft-panel>
            <?php if ( $has_draft ) : ?>
                <div class="dps-registration-draft__restore" data-dps-draft-restore-panel>
                    <p><?php esc_html_e( 'Existe um rascunho salvo deste cadastro.', 'dps-registration-addon' ); ?></p>
                    <div class="dps-registration-draft__actions">
                        <button type="button" class="dps-button-secondary" data-dps-draft-restore><?php esc_html_e( 'Restaurar rascunho', 'dps-registration-addon' ); ?></button>
                        <button type="button" class="dps-button-secondary" data-dps-draft-discard><?php esc_html_e( 'Descartar', 'dps-registration-addon' ); ?></button>
                    </div>
                </div>
            <?php endif; ?>
            <label class="dps-registration-draft__optin">
                <input type="checkbox" name="dps_registration_draft_optin" value="1" data-dps-draft-optin>
                <?php esc_html_e( 'Salvar rascunho por 7 dias para continuar depois', 'dps-registration-addon' ); ?>
            </label>
            <p class="dps-registration-draft__status" data-dps-draft-status aria-live="polite"></p>
        </section>
        <?php
        return ob_get_clean();
    }
}
