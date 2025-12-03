<?php
/**
 * Classe de configuração SMTP do White Label.
 *
 * @package DPS_WhiteLabel_Addon
 * @since 1.0.0
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Gerencia as configurações SMTP para envio de e-mails.
 *
 * @since 1.0.0
 */
class DPS_WhiteLabel_SMTP {

    /**
     * Nome da option onde as configurações são armazenadas.
     */
    const OPTION_NAME = 'dps_whitelabel_smtp';

    /**
     * Construtor da classe.
     */
    public function __construct() {
        add_action( 'admin_init', [ $this, 'handle_settings_save' ] );
        add_action( 'phpmailer_init', [ $this, 'configure_phpmailer' ], 1000 );
        add_action( 'wp_mail_failed', [ $this, 'log_email_failure' ] );
        
        // AJAX para teste de e-mail
        add_action( 'wp_ajax_dps_whitelabel_test_email', [ $this, 'ajax_test_email' ] );
    }

    /**
     * Retorna as configurações padrão.
     *
     * @return array Configurações padrão.
     */
    public static function get_defaults() {
        return [
            'smtp_enabled'     => false,
            'smtp_host'        => '',
            'smtp_port'        => 587,
            'smtp_encryption'  => 'tls',
            'smtp_auth'        => true,
            'smtp_username'    => '',
            'smtp_password'    => '',
            'return_path'      => '',
            'force_from_email' => false,
            'force_from_name'  => false,
            'log_emails'       => false,
        ];
    }

    /**
     * Obtém configurações atuais.
     *
     * @return array Configurações mescladas com padrões.
     */
    public static function get_settings() {
        $saved = get_option( self::OPTION_NAME, [] );
        return wp_parse_args( $saved, self::get_defaults() );
    }

    /**
     * Obtém valor de uma configuração específica.
     *
     * @param string $key     Nome da configuração.
     * @param mixed  $default Valor padrão se não existir.
     * @return mixed Valor da configuração.
     */
    public static function get( $key, $default = '' ) {
        $settings = self::get_settings();
        return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
    }

    /**
     * Processa salvamento de configurações.
     */
    public function handle_settings_save() {
        if ( ! isset( $_POST['dps_whitelabel_save_smtp'] ) ) {
            return;
        }

        if ( ! isset( $_POST['dps_whitelabel_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dps_whitelabel_nonce'] ) ), 'dps_whitelabel_settings' ) ) {
            add_settings_error(
                'dps_whitelabel',
                'invalid_nonce',
                __( 'Erro de segurança. Por favor, tente novamente.', 'dps-whitelabel-addon' ),
                'error'
            );
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            add_settings_error(
                'dps_whitelabel',
                'no_permission',
                __( 'Você não tem permissão para alterar estas configurações.', 'dps-whitelabel-addon' ),
                'error'
            );
            return;
        }

        $old_settings = self::get_settings();
        
        // Obter nova senha ou manter a antiga
        $new_password = '';
        if ( ! empty( $_POST['smtp_password'] ) ) {
            $new_password = $this->encrypt_password( sanitize_text_field( wp_unslash( $_POST['smtp_password'] ) ) );
        } elseif ( ! empty( $old_settings['smtp_password'] ) ) {
            $new_password = $old_settings['smtp_password'];
        }

        $new_settings = [
            'smtp_enabled'     => isset( $_POST['smtp_enabled'] ),
            'smtp_host'        => sanitize_text_field( wp_unslash( $_POST['smtp_host'] ?? '' ) ),
            'smtp_port'        => absint( $_POST['smtp_port'] ?? 587 ),
            'smtp_encryption'  => sanitize_key( $_POST['smtp_encryption'] ?? 'tls' ),
            'smtp_auth'        => isset( $_POST['smtp_auth'] ),
            'smtp_username'    => sanitize_text_field( wp_unslash( $_POST['smtp_username'] ?? '' ) ),
            'smtp_password'    => $new_password,
            'return_path'      => sanitize_email( wp_unslash( $_POST['return_path'] ?? '' ) ),
            'force_from_email' => isset( $_POST['force_from_email'] ),
            'force_from_name'  => isset( $_POST['force_from_name'] ),
            'log_emails'       => isset( $_POST['log_emails'] ),
        ];

        update_option( self::OPTION_NAME, $new_settings );

        add_settings_error(
            'dps_whitelabel',
            'settings_saved',
            __( 'Configurações SMTP salvas com sucesso!', 'dps-whitelabel-addon' ),
            'success'
        );
    }

    /**
     * Configura PHPMailer com as opções SMTP.
     *
     * @param PHPMailer\PHPMailer\PHPMailer $phpmailer Instância do PHPMailer.
     */
    public function configure_phpmailer( $phpmailer ) {
        $smtp = self::get_settings();
        
        // Verifica se WP Mail SMTP está ativo - se sim, não interferir
        if ( class_exists( 'WPMailSMTP\Core' ) ) {
            return;
        }
        
        if ( empty( $smtp['smtp_enabled'] ) ) {
            return;
        }
        
        if ( empty( $smtp['smtp_host'] ) ) {
            return;
        }
        
        // Configura SMTP
        $phpmailer->isSMTP();
        $phpmailer->Host       = sanitize_text_field( $smtp['smtp_host'] );
        $phpmailer->Port       = absint( $smtp['smtp_port'] );
        $phpmailer->SMTPAuth   = ! empty( $smtp['smtp_auth'] );
        
        if ( $phpmailer->SMTPAuth ) {
            $phpmailer->Username = sanitize_text_field( $smtp['smtp_username'] );
            $phpmailer->Password = $this->decrypt_password( $smtp['smtp_password'] );
        }
        
        // Encriptação
        $encryption = $smtp['smtp_encryption'] ?? 'tls';
        if ( 'tls' === $encryption ) {
            $phpmailer->SMTPSecure = 'tls';
        } elseif ( 'ssl' === $encryption ) {
            $phpmailer->SMTPSecure = 'ssl';
        } else {
            $phpmailer->SMTPSecure = '';
        }
        
        // Return-Path personalizado
        if ( ! empty( $smtp['return_path'] ) ) {
            $phpmailer->Sender = sanitize_email( $smtp['return_path'] );
        }
        
        // Timeout
        $phpmailer->Timeout = 30;
        
        // Permite que outros plugins modifiquem
        $phpmailer = apply_filters( 'dps_whitelabel_smtp_phpmailer', $phpmailer );
    }

    /**
     * Registra falha de envio de e-mail.
     *
     * @param WP_Error $error Erro do wp_mail.
     */
    public function log_email_failure( $error ) {
        $smtp = self::get_settings();
        
        if ( empty( $smtp['log_emails'] ) ) {
            return;
        }
        
        if ( class_exists( 'DPS_Logger' ) ) {
            DPS_Logger::error(
                sprintf(
                    /* translators: %s: Mensagem de erro */
                    __( 'Falha no envio de e-mail: %s', 'dps-whitelabel-addon' ),
                    $error->get_error_message()
                ),
                'whitelabel-smtp'
            );
        }
        
        do_action( 'dps_whitelabel_email_failed', $error );
    }

    /**
     * Encripta senha SMTP antes de salvar.
     *
     * @param string $password Senha em texto plano.
     * @return string Senha encriptada.
     */
    public function encrypt_password( $password ) {
        if ( empty( $password ) ) {
            return '';
        }
        
        // Usa OPENSSL para encriptação
        $key = $this->get_encryption_key();
        $iv  = openssl_random_pseudo_bytes( 16 );
        
        $encrypted = openssl_encrypt(
            $password,
            'AES-256-CBC',
            $key,
            0,
            $iv
        );
        
        if ( false === $encrypted ) {
            return '';
        }
        
        return base64_encode( $iv . $encrypted );
    }

    /**
     * Decripta senha SMTP.
     *
     * @param string $encrypted Senha encriptada.
     * @return string Senha em texto plano.
     */
    public function decrypt_password( $encrypted ) {
        if ( empty( $encrypted ) ) {
            return '';
        }
        
        $key  = $this->get_encryption_key();
        $data = base64_decode( $encrypted );
        
        if ( false === $data || strlen( $data ) < 16 ) {
            return '';
        }
        
        $iv  = substr( $data, 0, 16 );
        $enc = substr( $data, 16 );
        
        $decrypted = openssl_decrypt( $enc, 'AES-256-CBC', $key, 0, $iv );
        
        if ( false === $decrypted ) {
            return '';
        }
        
        return $decrypted;
    }

    /**
     * Retorna a chave de encriptação.
     *
     * @return string Chave de encriptação.
     */
    private function get_encryption_key() {
        // Usa o auth salt do WordPress como base
        $key = wp_salt( 'auth' );
        
        // Gera hash de 32 bytes
        return hash( 'sha256', $key, true );
    }

    /**
     * AJAX: Envia e-mail de teste.
     */
    public function ajax_test_email() {
        check_ajax_referer( 'dps_whitelabel_ajax', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissão negada.', 'dps-whitelabel-addon' ) ] );
        }
        
        $to = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        
        if ( empty( $to ) ) {
            wp_send_json_error( [ 'message' => __( 'E-mail de destino inválido.', 'dps-whitelabel-addon' ) ] );
        }
        
        $result = self::send_test_email( $to );
        
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }
        
        wp_send_json_success( [ 'message' => __( 'E-mail de teste enviado com sucesso!', 'dps-whitelabel-addon' ) ] );
    }

    /**
     * Envia e-mail de teste.
     *
     * @param string $to Endereço de destino.
     * @return bool|WP_Error True em sucesso ou WP_Error.
     */
    public static function send_test_email( $to ) {
        $brand_name = DPS_WhiteLabel_Branding::get_brand_name();
        
        $subject = sprintf(
            /* translators: %s: Nome da marca */
            __( '[%s] E-mail de Teste SMTP', 'dps-whitelabel-addon' ),
            $brand_name
        );
        
        $message = sprintf(
            /* translators: %1$s: Nome da marca, %2$s: Data/hora, %3$s: URL do site */
            __(
                "Este é um e-mail de teste enviado pelo %1\$s.\n\n" .
                "Se você está lendo isso, a configuração SMTP está funcionando corretamente!\n\n" .
                "Data/hora do envio: %2\$s\n" .
                "Servidor: %3\$s",
                'dps-whitelabel-addon'
            ),
            $brand_name,
            current_time( 'mysql' ),
            home_url()
        );
        
        $sent = wp_mail( $to, $subject, $message );
        
        if ( ! $sent ) {
            global $phpmailer;
            $error_message = '';
            
            if ( isset( $phpmailer ) && isset( $phpmailer->ErrorInfo ) ) {
                $error_message = $phpmailer->ErrorInfo;
            }
            
            return new WP_Error(
                'smtp_test_failed',
                ! empty( $error_message ) 
                    ? $error_message 
                    : __( 'Falha desconhecida no envio.', 'dps-whitelabel-addon' )
            );
        }
        
        return true;
    }

    /**
     * Verifica se a encriptação OpenSSL está disponível.
     *
     * @return bool True se disponível.
     */
    public static function is_encryption_available() {
        return extension_loaded( 'openssl' );
    }
}
