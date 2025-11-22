<?php
/**
 * Plugin Name:       Desi Pet Shower – Communications Add-on
 * Plugin URI:        https://probst.pro/desi-pet-shower
 * Description:       Add-on de comunicações para enviar mensagens via WhatsApp, SMS e e-mail nos eventos do Desi Pet Shower.
 * Version:           0.2.0
 * Author:            PRObst
 * Author URI:        https://probst.pro
 * Text Domain:       desi-pet-shower
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Carrega a API centralizada de comunicações
require_once __DIR__ . '/includes/class-dps-communications-api.php';

class DPS_Communications_Addon {

    const OPTION_KEY = 'dps_comm_settings';

    public function __construct() {
        add_action( 'dps_settings_nav_tabs', [ $this, 'add_settings_tab' ], 40, 1 );
        add_action( 'dps_settings_sections', [ $this, 'add_settings_section' ], 40, 1 );
        add_action( 'init', [ $this, 'maybe_handle_save' ] );

        add_action( 'dps_base_after_save_appointment', [ $this, 'handle_after_save_appointment' ], 10, 2 );
        add_action( 'dps_comm_send_appointment_reminder', [ $this, 'send_appointment_reminder' ], 10, 1 );
        add_action( 'dps_comm_send_post_service', [ $this, 'send_post_service_message' ], 10, 1 );
    }

    public function sanitize_settings( $input ) {
        $output = [];

        $output['whatsapp_api_key']      = isset( $input['whatsapp_api_key'] ) ? sanitize_text_field( $input['whatsapp_api_key'] ) : '';
        $output['whatsapp_api_url']      = isset( $input['whatsapp_api_url'] ) ? esc_url_raw( $input['whatsapp_api_url'] ) : '';
        $output['default_email_from']    = isset( $input['default_email_from'] ) ? sanitize_email( $input['default_email_from'] ) : '';
        $output['template_confirmation'] = isset( $input['template_confirmation'] ) ? sanitize_textarea_field( $input['template_confirmation'] ) : '';
        $output['template_reminder']     = isset( $input['template_reminder'] ) ? sanitize_textarea_field( $input['template_reminder'] ) : '';
        $output['template_post_service'] = isset( $input['template_post_service'] ) ? sanitize_textarea_field( $input['template_post_service'] ) : '';

        return $output;
    }

    public function add_settings_tab( $visitor_only ) {
        if ( $visitor_only ) {
            return;
        }

        if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
            echo '<li><a href="#" class="dps-tab-link" data-tab="comunicacoes">' . esc_html__( 'Comunicações', 'desi-pet-shower' ) . '</a></li>';
        }
    }

    public function add_settings_section( $visitor_only ) {
        if ( $visitor_only ) {
            return;
        }

        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            echo '<div class="dps-section" id="dps-section-comunicacoes"><p>' . esc_html__( 'Você não tem permissão para gerenciar comunicações.', 'desi-pet-shower' ) . '</p></div>';
            return;
        }

        echo $this->render_settings_section();
    }

    private function render_settings_section() {
        $options = get_option( self::OPTION_KEY, [] );
        $status  = isset( $_GET['updated'] ) ? sanitize_text_field( wp_unslash( $_GET['updated'] ) ) : '';

        ob_start();
        ?>
        <div class="dps-section" id="dps-section-comunicacoes">
            <h3><?php echo esc_html__( 'Comunicações', 'desi-pet-shower' ); ?></h3>
            <p><?php echo esc_html__( 'Defina integrações e mensagens automáticas para WhatsApp, SMS ou e-mail.', 'desi-pet-shower' ); ?></p>

            <?php if ( '1' === $status ) : ?>
                <div class="notice notice-success" style="margin: 10px 0;">
                    <p><?php echo esc_html__( 'Configurações salvas com sucesso.', 'desi-pet-shower' ); ?></p>
                </div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="dps_comm_action" value="save_settings" />
                <?php wp_nonce_field( 'dps_comm_save', 'dps_comm_nonce' ); ?>

                <p>
                    <label for="dps_comm_whatsapp_api_key"><?php echo esc_html__( 'Chave de API do WhatsApp', 'desi-pet-shower' ); ?></label><br />
                    <input type="text" id="dps_comm_whatsapp_api_key" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[whatsapp_api_key]" value="<?php echo esc_attr( $options['whatsapp_api_key'] ?? '' ); ?>" class="regular-text" />
                </p>

                <p>
                    <label for="dps_comm_whatsapp_api_url"><?php echo esc_html__( 'Endpoint/Base URL do WhatsApp', 'desi-pet-shower' ); ?></label><br />
                    <input type="text" id="dps_comm_whatsapp_api_url" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[whatsapp_api_url]" value="<?php echo esc_attr( $options['whatsapp_api_url'] ?? '' ); ?>" class="regular-text" />
                </p>

                <p>
                    <label for="dps_comm_default_email_from"><?php echo esc_html__( 'E-mail remetente padrão', 'desi-pet-shower' ); ?></label><br />
                    <input type="email" id="dps_comm_default_email_from" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[default_email_from]" value="<?php echo esc_attr( $options['default_email_from'] ?? '' ); ?>" class="regular-text" />
                </p>

                <p>
                    <label for="dps_comm_template_confirmation"><?php echo esc_html__( 'Template de confirmação de agendamento', 'desi-pet-shower' ); ?></label><br />
                    <textarea id="dps_comm_template_confirmation" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[template_confirmation]" rows="4" class="large-text"><?php echo esc_textarea( $options['template_confirmation'] ?? '' ); ?></textarea>
                </p>

                <p>
                    <label for="dps_comm_template_reminder"><?php echo esc_html__( 'Template de lembrete', 'desi-pet-shower' ); ?></label><br />
                    <textarea id="dps_comm_template_reminder" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[template_reminder]" rows="4" class="large-text"><?php echo esc_textarea( $options['template_reminder'] ?? '' ); ?></textarea>
                </p>

                <p>
                    <label for="dps_comm_template_post_service"><?php echo esc_html__( 'Template de pós-atendimento', 'desi-pet-shower' ); ?></label><br />
                    <textarea id="dps_comm_template_post_service" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[template_post_service]" rows="4" class="large-text"><?php echo esc_textarea( $options['template_post_service'] ?? '' ); ?></textarea>
                </p>

                <?php submit_button( __( 'Salvar configurações', 'desi-pet-shower' ) ); ?>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    public function maybe_handle_save() {
        if ( ! isset( $_POST['dps_comm_action'] ) || 'save_settings' !== $_POST['dps_comm_action'] ) {
            return;
        }

        if ( ! isset( $_POST['dps_comm_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dps_comm_nonce'] ) ), 'dps_comm_save' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $raw_settings = isset( $_POST[ self::OPTION_KEY ] ) ? (array) wp_unslash( $_POST[ self::OPTION_KEY ] ) : [];
        $settings     = $this->sanitize_settings( $raw_settings );

        update_option( self::OPTION_KEY, $settings );

        wp_redirect( add_query_arg( [ 'tab' => 'comunicacoes', 'updated' => '1' ], admin_url( $_SERVER['REQUEST_URI'] ) ) );
        exit;
    }

    /**
     * Handler para envio de confirmação após salvar agendamento
     *
     * NOTA: Este método é chamado pela Agenda ao criar novo agendamento.
     * A lógica de ENVIO está delegada à Communications API.
     *
     * @param int    $appointment_id ID do agendamento
     * @param string $type           Tipo de operação ('new' ou 'update')
     */
    public function handle_after_save_appointment( $appointment_id, $type ) {
        if ( 'new' !== $type ) {
            return;
        }

        $options  = get_option( self::OPTION_KEY, [] );
        $template = isset( $options['template_confirmation'] ) ? $options['template_confirmation'] : '';
        
        if ( empty( $template ) ) {
            return;
        }

        $message  = $this->prepare_message_from_template( $template, $appointment_id );
        $phone    = get_post_meta( $appointment_id, 'dps_client_phone', true );

        if ( ! empty( $phone ) && ! empty( $message ) ) {
            // Delega o envio para a API central
            $api = DPS_Communications_API::get_instance();
            $api->send_whatsapp( $phone, $message, [
                'appointment_id' => $appointment_id,
                'type'           => 'confirmation',
            ] );
        }

        $this->schedule_reminder( $appointment_id );
    }

    private function schedule_reminder( $appointment_id ) {
        $appointment_datetime = get_post_meta( $appointment_id, 'dps_appointment_datetime', true );
        $timestamp            = $appointment_datetime ? strtotime( $appointment_datetime ) : false;

        if ( ! $timestamp ) {
            return;
        }

        $reminder_time = $timestamp - DAY_IN_SECONDS;

        if ( $reminder_time <= time() ) {
            return;
        }

        wp_schedule_single_event( $reminder_time, 'dps_comm_send_appointment_reminder', [ $appointment_id ] );
    }

    /**
     * Envia lembrete de agendamento (via cron job)
     *
     * NOTA: Este método é chamado automaticamente pelo cron job agendado.
     * A lógica de ENVIO está delegada à Communications API.
     *
     * @param int $appointment_id ID do agendamento
     */
    public function send_appointment_reminder( $appointment_id ) {
        // Delega para a API central que já implementa toda a lógica
        $api = DPS_Communications_API::get_instance();
        $api->send_appointment_reminder( $appointment_id );
    }

    private function prepare_message_from_template( $template, $appointment_id ) {
        $appointment = get_post( $appointment_id );
        $replacements = [
            '{appointment_id}'   => $appointment_id,
            '{appointment_title}' => $appointment ? $appointment->post_title : '',
        ];

        return strtr( $template, $replacements );
    }

    /**
     * Envia mensagem pós-atendimento
     *
     * NOTA: A lógica de ENVIO está delegada à Communications API.
     *
     * @param int $appointment_id ID do agendamento
     */
    public function send_post_service_message( $appointment_id ) {
        $options  = get_option( self::OPTION_KEY, [] );
        $template = isset( $options['template_post_service'] ) ? $options['template_post_service'] : '';
        
        if ( empty( $template ) ) {
            return;
        }

        $message  = $this->prepare_message_from_template( $template, $appointment_id );
        $phone    = get_post_meta( $appointment_id, 'dps_client_phone', true );

        if ( ! empty( $phone ) && ! empty( $message ) ) {
            // Delega o envio para a API central
            $api = DPS_Communications_API::get_instance();
            $api->send_whatsapp( $phone, $message, [
                'appointment_id' => $appointment_id,
                'type'           => 'post_service',
            ] );
        }
    }
}

if ( ! function_exists( 'dps_comm_init' ) ) {
    function dps_comm_init() {
        static $instance = null;

        if ( null === $instance ) {
            $instance = new DPS_Communications_Addon();
        }

        return $instance;
    }
}

add_action( 'plugins_loaded', 'dps_comm_init' );

/**
 * Funções helper para compatibilidade retroativa
 *
 * NOTA: Estas funções delegam para a Communications API.
 * Outros add-ons DEVEM usar DPS_Communications_API::get_instance() diretamente.
 */

if ( ! function_exists( 'dps_comm_send_whatsapp' ) ) {
    /**
     * Envia mensagem via WhatsApp
     *
     * @deprecated 0.2.0 Use DPS_Communications_API::get_instance()->send_whatsapp()
     *
     * @param string $phone   Telefone do destinatário
     * @param string $message Mensagem a enviar
     * @return bool True se enviado, false caso contrário
     */
    function dps_comm_send_whatsapp( $phone, $message ) {
        if ( class_exists( 'DPS_Communications_API' ) ) {
            $api = DPS_Communications_API::get_instance();
            return $api->send_whatsapp( $phone, $message, [ 'source' => 'legacy_function' ] );
        }

        // Fallback se API não estiver disponível
        $log_message = sprintf( 'DPS Communications: enviar WhatsApp para %s com mensagem: %s', $phone, $message );
        error_log( $log_message );
        return true;
    }
}

if ( ! function_exists( 'dps_comm_send_email' ) ) {
    /**
     * Envia e-mail
     *
     * @deprecated 0.2.0 Use DPS_Communications_API::get_instance()->send_email()
     *
     * @param string $email   E-mail do destinatário
     * @param string $subject Assunto
     * @param string $message Corpo da mensagem
     * @return bool True se enviado, false caso contrário
     */
    function dps_comm_send_email( $email, $subject, $message ) {
        if ( class_exists( 'DPS_Communications_API' ) ) {
            $api = DPS_Communications_API::get_instance();
            return $api->send_email( $email, $subject, $message, [ 'source' => 'legacy_function' ] );
        }

        // Fallback se API não estiver disponível
        return wp_mail( $email, $subject, $message );
    }
}

if ( ! function_exists( 'dps_comm_send_sms' ) ) {
    /**
     * Envia SMS
     *
     * @deprecated 0.2.0 Funcionalidade não implementada
     *
     * @param string $phone   Telefone
     * @param string $message Mensagem
     * @return bool
     */
    function dps_comm_send_sms( $phone, $message ) {
        $log_message = sprintf( 'DPS Communications: SMS não implementado. Telefone: %s, Mensagem: %s', $phone, $message );
        error_log( $log_message );
        return false;
    }
}
