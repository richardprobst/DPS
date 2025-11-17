<?php
/**
 * Plugin Name:       Desi Pet Shower – Communications Add-on
 * Plugin URI:        https://probst.pro/desi-pet-shower
 * Description:       Add-on de comunicações para enviar mensagens via WhatsApp, SMS e e-mail nos eventos do Desi Pet Shower.
 * Version:           0.1.0
 * Author:            PRObst
 * Author URI:        https://probst.pro
 * Text Domain:       desi-pet-shower
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DPS_Communications_Addon {

    const OPTION_KEY = 'dps_comm_settings';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );

        add_action( 'dps_base_after_save_appointment', [ $this, 'handle_after_save_appointment' ], 10, 2 );
        add_action( 'dps_comm_send_appointment_reminder', [ $this, 'send_appointment_reminder' ], 10, 1 );
        add_action( 'dps_comm_send_post_service', [ $this, 'send_post_service_message' ], 10, 1 );
    }

    public function register_menu() {
        if ( ! isset( $GLOBALS['admin_page_hooks']['desi-pet-shower'] ) ) {
            add_menu_page(
                __( 'Desi Pet Shower', 'desi-pet-shower' ),
                __( 'Desi Pet Shower', 'desi-pet-shower' ),
                'manage_options',
                'desi-pet-shower',
                '__return_null',
                'dashicons-pets'
            );
        }

        add_submenu_page(
            'desi-pet-shower',
            __( 'Comunicações', 'desi-pet-shower' ),
            __( 'Comunicações', 'desi-pet-shower' ),
            'manage_options',
            'dps-communications',
            [ $this, 'render_settings_page' ]
        );
    }

    public function register_settings() {
        register_setting( 'dps_comm_settings_group', self::OPTION_KEY, [ $this, 'sanitize_settings' ] );

        add_settings_section(
            'dps_comm_main_section',
            __( 'Configurações de Comunicações', 'desi-pet-shower' ),
            '__return_false',
            'dps_comm_settings_page'
        );

        add_settings_field(
            'dps_comm_whatsapp_api_key',
            __( 'Chave de API do WhatsApp', 'desi-pet-shower' ),
            [ $this, 'render_text_field' ],
            'dps_comm_settings_page',
            'dps_comm_main_section',
            [ 'label_for' => 'dps_comm_whatsapp_api_key', 'option_key' => 'whatsapp_api_key' ]
        );

        add_settings_field(
            'dps_comm_whatsapp_api_url',
            __( 'Endpoint/Base URL do WhatsApp', 'desi-pet-shower' ),
            [ $this, 'render_text_field' ],
            'dps_comm_settings_page',
            'dps_comm_main_section',
            [ 'label_for' => 'dps_comm_whatsapp_api_url', 'option_key' => 'whatsapp_api_url' ]
        );

        add_settings_field(
            'dps_comm_default_email_from',
            __( 'E-mail remetente padrão', 'desi-pet-shower' ),
            [ $this, 'render_text_field' ],
            'dps_comm_settings_page',
            'dps_comm_main_section',
            [ 'label_for' => 'dps_comm_default_email_from', 'option_key' => 'default_email_from' ]
        );

        add_settings_field(
            'dps_comm_template_confirmation',
            __( 'Template de confirmação de agendamento', 'desi-pet-shower' ),
            [ $this, 'render_textarea_field' ],
            'dps_comm_settings_page',
            'dps_comm_main_section',
            [ 'label_for' => 'dps_comm_template_confirmation', 'option_key' => 'template_confirmation' ]
        );

        add_settings_field(
            'dps_comm_template_reminder',
            __( 'Template de lembrete', 'desi-pet-shower' ),
            [ $this, 'render_textarea_field' ],
            'dps_comm_settings_page',
            'dps_comm_main_section',
            [ 'label_for' => 'dps_comm_template_reminder', 'option_key' => 'template_reminder' ]
        );

        add_settings_field(
            'dps_comm_template_post_service',
            __( 'Template de pós-atendimento', 'desi-pet-shower' ),
            [ $this, 'render_textarea_field' ],
            'dps_comm_settings_page',
            'dps_comm_main_section',
            [ 'label_for' => 'dps_comm_template_post_service', 'option_key' => 'template_post_service' ]
        );
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

    public function render_text_field( $args ) {
        $options   = get_option( self::OPTION_KEY, [] );
        $option_id = isset( $args['label_for'] ) ? $args['label_for'] : '';
        $key       = isset( $args['option_key'] ) ? $args['option_key'] : '';
        $value     = isset( $options[ $key ] ) ? $options[ $key ] : '';

        printf(
            '<input type="text" id="%1$s" name="%2$s[%3$s]" value="%4$s" class="regular-text" />',
            esc_attr( $option_id ),
            esc_attr( self::OPTION_KEY ),
            esc_attr( $key ),
            esc_attr( $value )
        );
    }

    public function render_textarea_field( $args ) {
        $options   = get_option( self::OPTION_KEY, [] );
        $option_id = isset( $args['label_for'] ) ? $args['label_for'] : '';
        $key       = isset( $args['option_key'] ) ? $args['option_key'] : '';
        $value     = isset( $options[ $key ] ) ? $options[ $key ] : '';

        printf(
            '<textarea id="%1$s" name="%2$s[%3$s]" rows="4" class="large-text">%4$s</textarea>',
            esc_attr( $option_id ),
            esc_attr( self::OPTION_KEY ),
            esc_attr( $key ),
            esc_textarea( $value )
        );
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'Comunicações', 'desi-pet-shower' ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'dps_comm_settings_group' );
                do_settings_sections( 'dps_comm_settings_page' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function handle_after_save_appointment( $appointment_id, $type ) {
        if ( 'new' !== $type ) {
            return;
        }

        $options  = get_option( self::OPTION_KEY, [] );
        $template = isset( $options['template_confirmation'] ) ? $options['template_confirmation'] : '';
        $message  = $this->prepare_message_from_template( $template, $appointment_id );
        $phone    = get_post_meta( $appointment_id, 'dps_client_phone', true );

        if ( ! empty( $phone ) && ! empty( $message ) ) {
            dps_comm_send_whatsapp( $phone, $message );
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

    public function send_appointment_reminder( $appointment_id ) {
        $options  = get_option( self::OPTION_KEY, [] );
        $template = isset( $options['template_reminder'] ) ? $options['template_reminder'] : '';
        $message  = $this->prepare_message_from_template( $template, $appointment_id );
        $phone    = get_post_meta( $appointment_id, 'dps_client_phone', true );

        if ( ! empty( $phone ) && ! empty( $message ) ) {
            dps_comm_send_whatsapp( $phone, $message );
        }
    }

    private function prepare_message_from_template( $template, $appointment_id ) {
        $appointment = get_post( $appointment_id );
        $replacements = [
            '{appointment_id}'   => $appointment_id,
            '{appointment_title}' => $appointment ? $appointment->post_title : '',
        ];

        return strtr( $template, $replacements );
    }

    public function send_post_service_message( $appointment_id ) {
        $options  = get_option( self::OPTION_KEY, [] );
        $template = isset( $options['template_post_service'] ) ? $options['template_post_service'] : '';
        $message  = $this->prepare_message_from_template( $template, $appointment_id );
        $phone    = get_post_meta( $appointment_id, 'dps_client_phone', true );

        if ( ! empty( $phone ) && ! empty( $message ) ) {
            dps_comm_send_whatsapp( $phone, $message );
        }
    }
}

dps_comm_init();

function dps_comm_init() {
    static $instance = null;

    if ( null === $instance ) {
        $instance = new DPS_Communications_Addon();
    }

    return $instance;
}

function dps_comm_send_whatsapp( $phone, $message ) {
    $log_message = sprintf( 'DPS Communications: enviar WhatsApp para %s com mensagem: %s', $phone, $message );
    error_log( $log_message );

    return true;
}

function dps_comm_send_email( $email, $subject, $message ) {
    return wp_mail( $email, $subject, $message );
}

function dps_comm_send_sms( $phone, $message ) {
    $log_message = sprintf( 'DPS Communications: enviar SMS para %s com mensagem: %s', $phone, $message );
    error_log( $log_message );

    return true;
}
