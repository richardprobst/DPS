<?php
/**
 * Plugin Name:       desi.pet by PRObst – Cadastro Add-on
 * Plugin URI:        https://www.probst.pro
 * Description:       Página pública de cadastro para clientes e pets. Envie o link e deixe o cliente preencher seus dados.
 * Version:           1.2.3
 * Author:            PRObst
 * Author URI:        https://www.probst.pro
 * Text Domain:       dps-registration-addon
 * Domain Path:       /languages
 * Requires at least: 6.9
 * Requires PHP:      8.4
 * Update URI:        https://github.com/richardprobst/DPS
 * License:           GPL-2.0+
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Verifica se o plugin base desi.pet by PRObst está ativo.
 * Se não estiver, exibe aviso e interrompe carregamento do add-on.
 */
function dps_registration_check_base_plugin() {
    if ( ! class_exists( 'DPS_Base_Plugin' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__( 'O add-on requer o plugin base desi.pet by PRObst para funcionar.', 'dps-registration-addon' );
            echo '</p></div>';
        } );
        return false;
    }
    return true;
}
add_action( 'plugins_loaded', function() {
    if ( ! dps_registration_check_base_plugin() ) {
        return;
    }
}, 1 );

/**
 * Carrega o text domain do Registration Add-on.
 * Usa prioridade 1 para garantir que rode antes da inicialização da classe (prioridade 5).
 */
function dps_registration_load_textdomain() {
    load_plugin_textdomain( 'dps-registration-addon', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'dps_registration_load_textdomain', 1 );

class DPS_Registration_Addon {

    /**
     * Ação padrão usada no reCAPTCHA.
     *
     * @since 1.5.0
     * @var string
     */
    const RECAPTCHA_ACTION = 'dps_registration';

    /**
     * Instância única (singleton).
     *
     * @since 1.0.1
     * @var DPS_Registration_Addon|null
     */
    private static $instance = null;

    /**
     * TTL para mensagens de erro em segundos.
     *
     * @since 1.1.0
     * @var int
     */
    const ERROR_MESSAGE_TTL = 60;

    /**
     * TTL para expiração de token de confirmação em segundos (48 horas).
     *
     * @since 1.1.0
     * @var int
     */
    const TOKEN_EXPIRATION_SECONDS = 172800; // 48 * 60 * 60

    /**
     * Nome do hook do cron para lembretes de confirmação de email.
     *
     * @since 1.4.0
     * @var string
     */
    const CONFIRMATION_REMINDER_CRON = 'dps_registration_confirmation_reminder';

    /**
     * Nome da meta que registra se o lembrete de confirmação já foi enviado.
     *
     * @since 1.4.0
     * @var string
     */
    const REMINDER_SENT_META = 'dps_reg_reminder_sent';

    /**
     * Registra eventos com DPS_Logger quando disponível ou error_log como fallback.
     * Evita PII em claro usando apenas hashes ou indicadores booleanos.
     *
     * @since 1.3.0
     *
     * @param string $level   Nível (info|warning|error).
     * @param string $message Mensagem principal.
     * @param array  $context Dados adicionais sem PII.
     */
    private function log_event( $level, $message, $context = array() ) {
        if ( class_exists( 'DPS_Logger' ) ) {
            DPS_Logger::log( $level, $message, $context, 'registration' );
            return;
        }

        $payload = wp_json_encode( $context );
        error_log( '[DPS Registration] ' . $message . ( $payload ? ' ' . $payload : '' ) );
    }

    /**
     * Gera hash seguro para valores sensíveis.
     *
     * @since 1.3.0
     *
     * @param string $value Valor bruto.
     * @return string Hash SHA-256 com prefixo.
     */
    private function get_safe_hash( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        return hash( 'sha256', 'dps_reg_' . $value );
    }

    /**
     * Recupera a instância única.
     *
     * @since 1.0.1
     * @return DPS_Registration_Addon
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado.
     * 
     * @since 1.0.1
     */
    private function __construct() {
        // Processa o envio do formulário
        add_action( 'init', [ $this, 'maybe_handle_registration' ] );
        // Confirmação de email
        add_action( 'init', [ $this, 'maybe_handle_email_confirmation' ] );
        // Shortcode para exibir o formulário
        add_shortcode( 'dps_registration_form', [ $this, 'render_registration_form' ] );
        // Cria a página automaticamente ao ativar
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        register_deactivation_hook( __FILE__, [ 'DPS_Registration_Addon', 'deactivate' ] );

        // Enfileira assets CSS para responsividade
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        // Adiciona página de configurações para API do Google Maps
        add_action( 'admin_menu', [ $this, 'add_settings_page' ], 20 );
        add_action( 'admin_init', [ $this, 'register_settings' ] );

        // F3.4: Agendamento do lembrete de confirmação
        add_action( 'init', [ $this, 'maybe_schedule_confirmation_reminders' ] );
        add_action( self::CONFIRMATION_REMINDER_CRON, [ $this, 'send_confirmation_reminders' ] );

        // F3.7: Tela de pendentes no admin
        add_action( 'admin_menu', [ $this, 'register_pending_clients_page' ], 30 );

        // F4.2: API segura de cadastro
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
    }

    // =========================================================================
    // F1.6 - Rate Limiting Helpers
    // =========================================================================

    /**
     * Obtém o IP do cliente de forma segura.
     *
     * @since 1.1.0
     * @return string IP do cliente (hash para privacidade)
     */
    private function get_client_ip_hash() {
        $ip = '';
        
        // Prioriza REMOTE_ADDR por segurança
        if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }
        
        // Fallback para X-Forwarded-For (primeiro IP apenas) se REMOTE_ADDR for localhost/proxy
        if ( ( empty( $ip ) || in_array( $ip, [ '127.0.0.1', '::1' ], true ) ) && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $forwarded = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
            $ips = explode( ',', $forwarded );
            $ip = trim( $ips[0] );
        }
        
        // Hash para não armazenar IP diretamente (sha256 mais seguro que md5)
        return hash( 'sha256', 'dps_reg_' . $ip );
    }

    /**
     * Recupera hash da API key enviada no header.
     *
     * @since 1.6.0
     *
     * @param WP_REST_Request $request Objeto da requisição.
     * @return string Hash SHA-256 ou string vazia.
     */
    private function get_request_api_key_hash( WP_REST_Request $request ) {
        $api_key = $request->get_header( 'X-DPS-Registration-Key' );
        if ( empty( $api_key ) ) {
            return '';
        }

        return hash( 'sha256', sanitize_text_field( $api_key ) );
    }

    /**
     * Sanitiza valores numéricos de rate limit (mínimo 1).
     *
     * @since 1.6.0
     *
     * @param mixed $value Valor recebido.
     * @return int Valor sanitizado.
     */
    public function sanitize_rate_limit_value( $value ) {
        $value = absint( $value );
        if ( $value < 1 ) {
            return 1;
        }

        return $value;
    }

    /**
     * Sanitiza e hasheia a API key antes de salvar.
     *
     * @since 1.6.0
     *
     * @param string $value Valor recebido.
     * @return string Hash persistido.
     */
    public function sanitize_api_key( $value ) {
        $value = sanitize_text_field( $value );

        if ( '' === $value ) {
            return get_option( 'dps_registration_api_key_hash', '' );
        }

        return hash( 'sha256', $value );
    }

    /**
     * Verifica rate limit para o IP atual.
     *
     * @since 1.1.0
     * @return bool True se permitido, false se bloqueado
     */
    private function check_rate_limit() {
        $ip_hash = $this->get_client_ip_hash();
        $transient_key = 'dps_reg_rate_' . substr( $ip_hash, 0, 32 ); // Limita tamanho da chave
        
        $data = get_transient( $transient_key );
        
        if ( false === $data ) {
            // Primeira tentativa - salva count e timestamp de início
            set_transient( $transient_key, [
                'count' => 1,
                'start' => time(),
            ], HOUR_IN_SECONDS );
            return true;
        }
        
        // Verifica formato antigo (apenas count) ou novo (array)
        if ( is_array( $data ) ) {
            $count = (int) $data['count'];
            $start = (int) $data['start'];
        } else {
            $count = (int) $data;
            $start = time();
        }

        if ( $count >= 3 ) {
            // Limite atingido
            return false;
        }
        
        // Incrementa contador mantendo expiração original (calcula tempo restante)
        $elapsed = time() - $start;
        $remaining = max( HOUR_IN_SECONDS - $elapsed, 60 ); // Mínimo 60 segundos
        
        set_transient( $transient_key, [
            'count' => $count + 1,
            'start' => $start,
        ], $remaining );
        
        return true;
    }

    // =========================================================================
    // F1.2 - CPF Validation
    // =========================================================================

    /**
     * Normaliza CPF para apenas dígitos.
     *
     * @since 1.1.0
     * @param string $cpf CPF bruto
     * @return string Apenas dígitos
     */
    private function normalize_cpf( $cpf ) {
        return preg_replace( '/\D/', '', (string) $cpf );
    }

    /**
     * Valida CPF com algoritmo mod 11.
     *
     * @since 1.1.0
     * @param string $cpf CPF a validar (pode conter pontuação)
     * @return bool True se válido, false caso contrário
     */
    private function validate_cpf( $cpf ) {
        $cpf = $this->normalize_cpf( $cpf );
        
        // CPF deve ter 11 dígitos
        if ( strlen( $cpf ) !== 11 ) {
            return false;
        }
        
        // Rejeita sequências conhecidas de dígitos repetidos
        if ( preg_match( '/^(\d)\1{10}$/', $cpf ) ) {
            return false;
        }
        
        // Calcula primeiro dígito verificador
        $sum = 0;
        for ( $i = 0; $i < 9; $i++ ) {
            $sum += (int) $cpf[ $i ] * ( 10 - $i );
        }
        $remainder = $sum % 11;
        $digit1 = ( $remainder < 2 ) ? 0 : ( 11 - $remainder );
        
        if ( (int) $cpf[9] !== $digit1 ) {
            return false;
        }
        
        // Calcula segundo dígito verificador
        $sum = 0;
        for ( $i = 0; $i < 10; $i++ ) {
            $sum += (int) $cpf[ $i ] * ( 11 - $i );
        }
        $remainder = $sum % 11;
        $digit2 = ( $remainder < 2 ) ? 0 : ( 11 - $remainder );
        
        return (int) $cpf[10] === $digit2;
    }

    // =========================================================================
    // F1.3/F1.9 - Phone Validation and Normalization
    // =========================================================================

    /**
     * Normaliza telefone para apenas dígitos.
     *
     * @since 1.1.0
     * @param string $phone Telefone bruto
     * @return string Apenas dígitos (sem +55 se tinha)
     */
    private function normalize_phone( $phone ) {
        $digits = preg_replace( '/\D/', '', (string) $phone );
        
        // Remove código do país (55) apenas se tiver 12 ou 13 dígitos (formato internacional completo)
        // 12 dígitos = 55 + DDD(2) + fixo(8), 13 dígitos = 55 + DDD(2) + celular(9)
        $length = strlen( $digits );
        if ( ( $length === 12 || $length === 13 ) && substr( $digits, 0, 2 ) === '55' ) {
            $digits = substr( $digits, 2 );
        }
        
        return $digits;
    }

    /**
     * Valida telefone brasileiro.
     *
     * @since 1.1.0
     * @param string $phone Telefone a validar
     * @return bool True se válido, false caso contrário
     */
    private function validate_phone( $phone ) {
        // Usa helper do core se disponível
        if ( class_exists( 'DPS_Phone_Helper' ) && method_exists( 'DPS_Phone_Helper', 'is_valid_brazilian_phone' ) ) {
            return DPS_Phone_Helper::is_valid_brazilian_phone( $phone );
        }
        
        // Fallback: validação própria
        $digits = $this->normalize_phone( $phone );
        $length = strlen( $digits );
        
        // Telefone válido deve ter 10 (fixo) ou 11 (celular) dígitos
        if ( $length !== 10 && $length !== 11 ) {
            return false;
        }
        
        // DDD deve estar entre 11 e 99
        $ddd = (int) substr( $digits, 0, 2 );
        if ( $ddd < 11 || $ddd > 99 ) {
            return false;
        }
        
        return true;
    }

    // =========================================================================
    // F1.5 - Duplicate Detection
    // =========================================================================

    /**
     * Busca cliente existente por email, telefone ou CPF.
     *
     * @since 1.1.0
     * @param string $email Email normalizado
     * @param string $phone Telefone normalizado (apenas dígitos)
     * @param string $cpf CPF normalizado (apenas dígitos)
     * @return int ID do cliente encontrado ou 0
     */
    private function find_duplicate_client( $email, $phone, $cpf ) {
        $meta_query = [
            'relation' => 'OR',
        ];
        
        // Adiciona critérios apenas se preenchidos
        if ( ! empty( $email ) ) {
            $meta_query[] = [
                'key'   => 'client_email',
                'value' => $email,
            ];
        }
        
        if ( ! empty( $phone ) ) {
            $meta_query[] = [
                'key'   => 'client_phone',
                'value' => $phone,
            ];
        }
        
        if ( ! empty( $cpf ) ) {
            $meta_query[] = [
                'key'   => 'client_cpf',
                'value' => $cpf,
            ];
        }
        
        // Se nenhum critério, não há duplicata
        if ( count( $meta_query ) <= 1 ) {
            return 0;
        }
        
        $clients = get_posts( [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => $meta_query,
        ] );

        return ! empty( $clients ) ? (int) $clients[0] : 0;
    }

    /**
     * Valida dados enviados via API reutilizando regras do formulário público.
     *
     * @since 1.6.0
     *
     * @param string $client_name  Nome do cliente.
     * @param string $client_phone Telefone informado (pode conter caracteres diversos).
     * @param string $client_email Email informado.
     * @param string $client_cpf   CPF informado.
     * @return array|WP_Error Dados normalizados ou erro.
     */
    private function validate_api_client_data( $client_name, $client_phone, $client_email, $client_cpf ) {
        $client_name  = sanitize_text_field( $client_name );
        $phone_raw    = sanitize_text_field( $client_phone );
        $email_clean  = sanitize_email( $client_email );
        $cpf_raw      = sanitize_text_field( $client_cpf );

        $errors = array();

        if ( empty( $client_name ) ) {
            $errors['missing_name'] = true;
        }

        if ( empty( $phone_raw ) ) {
            $errors['missing_phone'] = true;
        } elseif ( ! $this->validate_phone( $phone_raw ) ) {
            $errors['invalid_phone'] = true;
        }

        $normalized_phone = ! empty( $phone_raw ) ? $this->normalize_phone( $phone_raw ) : '';

        if ( ! empty( $email_clean ) && ! is_email( $email_clean ) ) {
            $errors['invalid_email'] = true;
        }

        $normalized_cpf = '';
        if ( ! empty( $cpf_raw ) ) {
            if ( ! $this->validate_cpf( $cpf_raw ) ) {
                $errors['invalid_cpf'] = true;
            } else {
                $normalized_cpf = $this->normalize_cpf( $cpf_raw );
            }
        }

        if ( ! empty( $errors ) ) {
            $code = array_key_first( $errors );
            return new WP_Error( $code, __( 'Não foi possível validar os dados enviados.', 'dps-registration-addon' ), array( 'status' => 400 ) );
        }

        $duplicate_id = $this->find_duplicate_client( $email_clean, $normalized_phone, $normalized_cpf );
        if ( $duplicate_id > 0 ) {
            $this->log_event( 'warning', 'Cadastro bloqueado por duplicata (API)', array(
                'duplicate_id' => $duplicate_id,
                'email_hash'   => $this->get_safe_hash( $email_clean ),
                'phone_hash'   => $this->get_safe_hash( $normalized_phone ),
                'cpf_hash'     => $this->get_safe_hash( $normalized_cpf ),
                'ip_hash'      => $this->get_client_ip_hash(),
            ) );

            return new WP_Error( 'duplicate_client', __( 'Não foi possível concluir o cadastro. Verifique os dados informados ou contate o suporte.', 'dps-registration-addon' ), array( 'status' => 400 ) );
        }

        return array(
            'client_name'  => $client_name,
            'client_phone' => $normalized_phone,
            'client_email' => $email_clean,
            'client_cpf'   => $normalized_cpf,
        );
    }

    // =========================================================================
    // F1.8 - Error Feedback
    // =========================================================================

    /**
     * Adiciona mensagem de erro para exibição no formulário.
     *
     * @since 1.1.0
     * @param string $message Mensagem de erro
     */
    private function add_error( $message ) {
        if ( class_exists( 'DPS_Message_Helper' ) ) {
            DPS_Message_Helper::add_error( $message );
            return;
        }
        
        // Fallback: usa transient baseado em IP
        $ip_hash = $this->get_client_ip_hash();
        $transient_key = 'dps_reg_msg_' . $ip_hash;
        
        $messages = get_transient( $transient_key );
        if ( ! is_array( $messages ) ) {
            $messages = [];
        }
        
        $messages[] = [
            'type' => 'error',
            'text' => $message,
        ];
        
        set_transient( $transient_key, $messages, self::ERROR_MESSAGE_TTL );
    }

    /**
     * Exibe mensagens de erro/sucesso armazenadas.
     *
     * @since 1.1.0
     * @return string HTML das mensagens
     */
    private function display_messages() {
        if ( class_exists( 'DPS_Message_Helper' ) ) {
            return DPS_Message_Helper::display_messages();
        }
        
        // Fallback: usa transient baseado em IP
        $ip_hash = $this->get_client_ip_hash();
        $transient_key = 'dps_reg_msg_' . $ip_hash;
        
        $messages = get_transient( $transient_key );
        if ( ! is_array( $messages ) || empty( $messages ) ) {
            return '';
        }
        
        $html = '';
        foreach ( $messages as $msg ) {
            $class = 'dps-reg-message';
            $style = 'padding: 12px 16px; margin-bottom: 16px; border-radius: 4px; ';
            
            if ( $msg['type'] === 'error' ) {
                $style .= 'background-color: #fef2f2; border: 1px solid #ef4444; color: #991b1b;';
            } elseif ( $msg['type'] === 'success' ) {
                $style .= 'background-color: #f0fdf4; border: 1px solid #22c55e; color: #166534;';
            }
            
            $html .= '<div class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '" role="alert">';
            $html .= esc_html( $msg['text'] );
            $html .= '</div>';
        }
        
        delete_transient( $transient_key );
        
        return $html;
    }

    /**
     * Redireciona de volta ao formulário com flag de erro.
     *
     * @since 1.1.0
     */
    private function redirect_with_error() {
        wp_safe_redirect( add_query_arg( 'dps_reg_error', '1', $this->get_registration_page_url() ) );
        exit;
    }

    /**
     * Enfileira CSS e JS do add-on de cadastro.
     *
     * @since 1.1.0
     * @since 1.2.0 Adicionado JS externo com validação e máscaras.
     */
    public function enqueue_assets() {
        // Carrega apenas na página de cadastro
        $registration_page_id = get_option( 'dps_registration_page_id' );
        $current_post = get_post();
        $post_content = $current_post ? (string) $current_post->post_content : '';
        
        if ( ! is_page( $registration_page_id ) && ! has_shortcode( $post_content, 'dps_registration_form' ) ) {
            return;
        }

        $addon_url = plugin_dir_url( __FILE__ );
        $version   = '1.2.3';

        $recaptcha_settings = $this->get_recaptcha_settings();
        $should_load_recaptcha = $recaptcha_settings['enabled'] && ! empty( $recaptcha_settings['site_key'] );

        if ( $should_load_recaptcha ) {
            wp_enqueue_script(
                'google-recaptcha',
                'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $recaptcha_settings['site_key'] ),
                array(),
                null,
                true
            );
        }

        // CSS responsivo
        wp_enqueue_style(
            'dps-registration-addon',
            $addon_url . 'assets/css/registration-addon.css',
            [],
            $version
        );

        // F2.5: JS externo com validação client-side e máscaras
        wp_enqueue_script(
            'dps-registration',
            $addon_url . 'assets/js/dps-registration.js',
            [],
            $version,
            true // Load in footer
        );

        wp_localize_script(
            'dps-registration',
            'dpsRegistrationData',
            array(
                'breeds'   => $this->get_breed_dataset(),
                'recaptcha' => array(
                    'enabled'            => $should_load_recaptcha,
                    'siteKey'            => $recaptcha_settings['site_key'],
                    'action'             => self::RECAPTCHA_ACTION,
                    'errorMessage'       => __( 'Não foi possível validar o anti-spam. Tente novamente.', 'dps-registration-addon' ),
                    'unavailableMessage' => __( 'Não foi possível carregar o verificador anti-spam. Recarregue a página e tente novamente.', 'dps-registration-addon' ),
                ),
            )
        );
    }

    /**
     * Executado na ativação do plugin. Cria a página de cadastro, se ainda não existir, contendo o shortcode.
     */
    public function activate() {
        $title = __( 'Cadastro de Clientes e Pets', 'dps-registration-addon' );
        $slug  = sanitize_title( $title );
        $page  = get_page_by_path( $slug );
        if ( ! $page ) {
            $page_id = wp_insert_post( [
                'post_title'   => $title,
                'post_name'    => $slug,
                'post_content' => '[dps_registration_form]',
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ] );
            if ( $page_id ) {
                update_option( 'dps_registration_page_id', $page_id );
            }
        } else {
            update_option( 'dps_registration_page_id', $page->ID );
        }
    }

    /**
     * Remove eventos agendados ao desativar o plugin.
     *
     * @since 1.4.0
     */
    public static function deactivate() {
        wp_clear_scheduled_hook( self::CONFIRMATION_REMINDER_CRON );
    }

    /**
     * Adiciona a página de configurações no menu principal "desi.pet by PRObst"
     * 
     * NOTA: Menu exibido como submenu de "desi.pet by PRObst" para alinhamento com a navegação unificada.
     * Também acessível pelo hub em dps-tools-hub (aba "Formulário de Cadastro").
     */
    public function add_settings_page() {
        add_submenu_page(
            'desi-pet-shower',
            __( 'Formulário de Cadastro', 'dps-registration-addon' ),
            __( 'Formulário de Cadastro', 'dps-registration-addon' ),
            'manage_options',
            'dps-registration-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    /**
     * Registra submenu para listar cadastros pendentes.
     *
     * @since 1.4.0
     */
    public function register_pending_clients_page() {
        add_submenu_page(
            'desi-pet-shower',
            __( 'Cadastros Pendentes', 'dps-registration-addon' ),
            __( 'Cadastros Pendentes', 'dps-registration-addon' ),
            'manage_options',
            'dps-registration-pending',
            [ $this, 'render_pending_clients_page' ]
        );
    }

    // =========================================================================
    // F4.2 - REST API Segura
    // =========================================================================

    /**
     * Registra rotas REST do add-on.
     *
     * @since 1.6.0
     */
    public function register_rest_routes() {
        register_rest_route(
            'dps/v1',
            '/register',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'handle_rest_register' ],
                'permission_callback' => [ $this, 'rest_register_permission_check' ],
            )
        );
    }

    /**
     * Valida permissão para uso da rota protegida.
     *
     * @since 1.6.0
     *
     * @param WP_REST_Request $request Requisição recebida.
     * @return true|WP_Error
     */
    public function rest_register_permission_check( WP_REST_Request $request ) {
        $api_enabled = (bool) get_option( 'dps_registration_api_enabled', 0 );
        if ( ! $api_enabled ) {
            return new WP_Error( 'dps_registration_api_disabled', __( 'API de cadastro desativada.', 'dps-registration-addon' ), array( 'status' => 403 ) );
        }

        $stored_hash    = get_option( 'dps_registration_api_key_hash', '' );
        $provided_hash  = $this->get_request_api_key_hash( $request );

        if ( empty( $stored_hash ) || empty( $provided_hash ) || ! hash_equals( (string) $stored_hash, $provided_hash ) ) {
            $this->log_event( 'warning', 'Tentativa de autenticação REST sem sucesso', array(
                'ip_hash'  => $this->get_client_ip_hash(),
                'key_hash' => $provided_hash ? substr( sha1( $provided_hash ), 0, 16 ) : '',
            ) );

            return new WP_Error( 'rest_forbidden', __( 'Não autorizado.', 'dps-registration-addon' ), array( 'status' => 401 ) );
        }

        return true;
    }

    /**
     * Incrementa contadores de rate limit.
     *
     * @since 1.6.0
     *
     * @param string $transient_key Chave do transient.
     * @param int    $limit         Limite máximo permitido.
     * @return bool True se permitido, false se bloqueado.
     */
    private function bump_api_rate_counter( $transient_key, $limit ) {
        $data = get_transient( $transient_key );

        if ( false === $data ) {
            set_transient( $transient_key, array(
                'count' => 1,
                'start' => time(),
            ), HOUR_IN_SECONDS );
            return true;
        }

        $count = isset( $data['count'] ) ? (int) $data['count'] : (int) $data;
        $start = isset( $data['start'] ) ? (int) $data['start'] : time();

        if ( $count >= $limit ) {
            return false;
        }

        $elapsed   = time() - $start;
        $remaining = max( HOUR_IN_SECONDS - $elapsed, 60 );

        set_transient( $transient_key, array(
            'count' => $count + 1,
            'start' => $start,
        ), $remaining );

        return true;
    }

    /**
     * Aplica rate limit por chave e por IP.
     *
     * @since 1.6.0
     *
     * @param string $api_key_hash Hash da chave recebida.
     * @return true|WP_Error
     */
    private function enforce_api_rate_limits( $api_key_hash ) {
        $key_limit = $this->sanitize_rate_limit_value( get_option( 'dps_registration_api_rate_key_per_hour', 60 ) );
        $ip_limit  = $this->sanitize_rate_limit_value( get_option( 'dps_registration_api_rate_ip_per_hour', 20 ) );

        $ip_hash   = $this->get_client_ip_hash();
        $key_token = 'dps_reg_api_k_' . sha1( $api_key_hash );
        $ip_token  = 'dps_reg_api_ip_' . sha1( $ip_hash );

        if ( ! $this->bump_api_rate_counter( $key_token, $key_limit ) ) {
            $this->log_event( 'warning', 'Rate limit excedido para chave da API', array(
                'key_hash' => substr( sha1( $api_key_hash ), 0, 16 ),
                'ip_hash'  => $ip_hash,
            ) );

            return $this->create_rate_limit_error();
        }

        if ( ! $this->bump_api_rate_counter( $ip_token, $ip_limit ) ) {
            $this->log_event( 'warning', 'Rate limit excedido para IP', array(
                'key_hash' => substr( sha1( $api_key_hash ), 0, 16 ),
                'ip_hash'  => $ip_hash,
            ) );

            return $this->create_rate_limit_error();
        }

        return true;
    }

    /**
     * Cria erro de rate limit com header Retry-After.
     *
     * @since 1.6.0
     *
     * @return WP_Error
     */
    private function create_rate_limit_error() {
        // Adiciona header Retry-After apenas uma vez
        static $header_added = false;
        if ( ! $header_added ) {
            add_filter( 'rest_post_dispatch', array( $this, 'add_retry_after_header' ), 10, 1 );
            $header_added = true;
        }

        return new WP_Error(
            'rate_limited',
            __( 'Muitas requisições. Tente novamente em breve.', 'dps-registration-addon' ),
            array(
                'status'      => 429,
                'retry_after' => HOUR_IN_SECONDS,
            )
        );
    }

    /**
     * Callback para adicionar header Retry-After na resposta REST.
     *
     * @since 1.6.0
     *
     * @param WP_REST_Response $result Resposta da API.
     * @return WP_REST_Response
     */
    public function add_retry_after_header( $result ) {
        if ( $result instanceof WP_REST_Response ) {
            $result->header( 'Retry-After', HOUR_IN_SECONDS );
        }
        return $result;
    }

    /**
     * Handler do endpoint REST de cadastro.
     *
     * @since 1.6.0
     *
     * @param WP_REST_Request $request Requisição JSON.
     * @return WP_REST_Response|WP_Error
     */
    public function handle_rest_register( WP_REST_Request $request ) {
        $api_key_hash = $this->get_request_api_key_hash( $request );

        $rate_check = $this->enforce_api_rate_limits( $api_key_hash );
        if ( is_wp_error( $rate_check ) ) {
            return $rate_check;
        }

        $params = $request->get_json_params();
        if ( empty( $params ) ) {
            $params = $request->get_params();
        }

        $client_name  = $params['client_name'] ?? '';
        $client_phone = $params['client_phone'] ?? '';
        $client_email = array_key_exists( 'client_email', $params ) ? $params['client_email'] : '';
        $client_cpf   = array_key_exists( 'client_cpf', $params ) ? $params['client_cpf'] : '';

        $validation = $this->validate_api_client_data( $client_name, $client_phone, $client_email, $client_cpf );
        if ( is_wp_error( $validation ) ) {
            $this->log_event( 'warning', 'Cadastro via API rejeitado por validação', array(
                'code'       => $validation->get_error_code(),
                'ip_hash'    => $this->get_client_ip_hash(),
                'email_hash' => $this->get_safe_hash( $client_email ),
                'phone_hash' => $this->get_safe_hash( $client_phone ),
            ) );

            return $validation;
        }

        $client_id = wp_insert_post( array(
            'post_type'   => 'dps_cliente',
            'post_title'  => $validation['client_name'],
            'post_status' => 'publish',
        ) );

        if ( ! $client_id || is_wp_error( $client_id ) ) {
            $this->log_event( 'error', 'Falha ao criar cliente via API', array(
                'ip_hash'  => $this->get_client_ip_hash(),
                'key_hash' => substr( sha1( $api_key_hash ), 0, 16 ),
            ) );

            return new WP_Error( 'registration_failed', __( 'Não foi possível criar o cadastro no momento.', 'dps-registration-addon' ), array( 'status' => 500 ) );
        }

        update_post_meta( $client_id, 'client_cpf', $validation['client_cpf'] );
        update_post_meta( $client_id, 'client_phone', $validation['client_phone'] );
        update_post_meta( $client_id, 'client_email', $validation['client_email'] );
        update_post_meta( $client_id, 'dps_email_confirmed', 0 );
        update_post_meta( $client_id, 'dps_is_active', 0 );
        update_post_meta( $client_id, 'dps_registration_source', 'api' );

        if ( ! empty( $validation['client_email'] ) ) {
            $this->send_confirmation_email( $client_id, $validation['client_email'] );
        }

        do_action( 'dps_registration_after_client_created', '', $client_id, $validation['client_email'], $validation['client_phone'] );

        $pets_created = 0;
        $pets_payload = array();
        if ( isset( $params['pets'] ) && is_array( $params['pets'] ) ) {
            $pets_payload = $params['pets'];
        }

        foreach ( $pets_payload as $pet_data ) {
            if ( is_object( $pet_data ) ) {
                $pet_data = (array) $pet_data;
            }

            $pet_name = sanitize_text_field( $pet_data['pet_name'] ?? '' );
            if ( '' === $pet_name ) {
                continue;
            }

            $pet_breed         = sanitize_text_field( $pet_data['pet_breed'] ?? '' );
            $pet_size          = sanitize_text_field( $pet_data['pet_size'] ?? '' );
            $pet_observations  = sanitize_textarea_field( $pet_data['pet_observations'] ?? '' );

            $pet_id = wp_insert_post( array(
                'post_type'   => 'dps_pet',
                'post_title'  => $pet_name,
                'post_status' => 'publish',
            ) );

            if ( $pet_id ) {
                $pets_created++;
                update_post_meta( $pet_id, 'owner_id', $client_id );
                update_post_meta( $pet_id, 'pet_breed', $pet_breed );
                update_post_meta( $pet_id, 'pet_size', $pet_size );
                if ( '' !== $pet_observations ) {
                    update_post_meta( $pet_id, 'pet_care', $pet_observations );
                }
            }
        }

        $this->send_admin_notification( $client_id, $validation['client_name'], $validation['client_phone'] );
        $this->send_welcome_messages( $validation['client_phone'], $validation['client_email'], $validation['client_name'] );

        $this->log_event( 'info', 'Cadastro criado via API', array(
            'client_id'    => $client_id,
            'pets_created' => $pets_created,
            'ip_hash'      => $this->get_client_ip_hash(),
            'email_hash'   => $this->get_safe_hash( $validation['client_email'] ),
            'phone_hash'   => $this->get_safe_hash( $validation['client_phone'] ),
        ) );

        return rest_ensure_response( array(
            'success'      => true,
            'client_id'    => $client_id,
            'pets_created' => $pets_created,
        ) );
    }

    /**
     * Registra as configurações utilizadas pelo plugin
     */
    public function register_settings() {
        register_setting( 'dps_registration_settings', 'dps_google_api_key', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ] );

        register_setting( 'dps_registration_settings', 'dps_registration_recaptcha_enabled', [
            'type'              => 'boolean',
            'sanitize_callback' => [ $this, 'sanitize_checkbox' ],
            'default'           => 0,
        ] );

        register_setting( 'dps_registration_settings', 'dps_registration_recaptcha_site_key', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ] );

        register_setting( 'dps_registration_settings', 'dps_registration_recaptcha_secret_key', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ] );

        register_setting( 'dps_registration_settings', 'dps_registration_recaptcha_threshold', [
            'type'              => 'number',
            'sanitize_callback' => [ $this, 'sanitize_recaptcha_threshold' ],
            'default'           => 0.5,
        ] );

        register_setting( 'dps_registration_settings', 'dps_registration_confirm_email_subject', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ] );

        register_setting( 'dps_registration_settings', 'dps_registration_confirm_email_body', [
            'type'              => 'string',
            'sanitize_callback' => 'wp_kses_post',
            'default'           => '',
        ] );

        register_setting( 'dps_registration_settings', 'dps_registration_api_enabled', [
            'type'              => 'boolean',
            'sanitize_callback' => [ $this, 'sanitize_checkbox' ],
            'default'           => 0,
        ] );

        register_setting( 'dps_registration_settings', 'dps_registration_api_key_hash', [
            'type'              => 'string',
            'sanitize_callback' => [ $this, 'sanitize_api_key' ],
            'default'           => '',
        ] );

        register_setting( 'dps_registration_settings', 'dps_registration_api_rate_key_per_hour', [
            'type'              => 'number',
            'sanitize_callback' => [ $this, 'sanitize_rate_limit_value' ],
            'default'           => 60,
        ] );

        register_setting( 'dps_registration_settings', 'dps_registration_api_rate_ip_per_hour', [
            'type'              => 'number',
            'sanitize_callback' => [ $this, 'sanitize_rate_limit_value' ],
            'default'           => 20,
        ] );
    }

    /**
     * Sanitiza checkboxes de configurações.
     *
     * @since 1.5.0
     *
     * @param mixed $value Valor recebido.
     * @return int 1 ou 0
     */
    public function sanitize_checkbox( $value ) {
        return ! empty( $value ) ? 1 : 0;
    }

    /**
     * Sanitiza o threshold do reCAPTCHA garantindo faixa entre 0 e 1.
     *
     * @since 1.5.0
     *
     * @param mixed $value Valor recebido.
     * @return float Valor clamped.
     */
    public function sanitize_recaptcha_threshold( $value ) {
        $value = floatval( $value );
        if ( $value < 0 ) {
            $value = 0;
        }

        if ( $value > 1 ) {
            $value = 1;
        }

        return $value;
    }

    /**
     * Retorna configurações sanitizadas do reCAPTCHA.
     *
     * @since 1.5.0
     *
     * @return array
     */
    private function get_recaptcha_settings() {
        return array(
            'enabled'    => (bool) get_option( 'dps_registration_recaptcha_enabled', 0 ),
            'site_key'   => sanitize_text_field( get_option( 'dps_registration_recaptcha_site_key', '' ) ),
            'secret_key' => sanitize_text_field( get_option( 'dps_registration_recaptcha_secret_key', '' ) ),
            'threshold'  => $this->sanitize_recaptcha_threshold( get_option( 'dps_registration_recaptcha_threshold', 0.5 ) ),
        );
    }

    /**
     * Retorna dataset de raças separadas por espécie, incluindo lista de populares.
     *
     * @since 1.2.2
     * @return array
     */
    private function get_breed_dataset() {
        static $dataset = null;

        if ( null !== $dataset ) {
            return $dataset;
        }

        $dog_breeds = array(
            'SRD (Sem Raça Definida)',
            'Affenpinscher',
            'Afghan Hound',
            'Airedale Terrier',
            'Akita',
            'Alaskan Malamute',
            'American Bulldog',
            'American Cocker Spaniel',
            'American Pit Bull Terrier',
            'American Staffordshire Terrier',
            'Basenji',
            'Basset Hound',
            'Beagle',
            'Bearded Collie',
            'Belgian Malinois',
            'Bernese Mountain Dog (Boiadeiro Bernês)',
            'Bichon Frisé',
            'Bichon Havanês',
            'Bloodhound',
            'Boiadeiro Australiano',
            'Border Collie',
            'Borzói',
            'Boston Terrier',
            'Boxer',
            'Bulldog',
            'Bulldog Americano',
            'Bulldog Campeiro',
            'Bulldog Francês',
            'Bulldog Inglês',
            'Bull Terrier',
            'Bullmastiff',
            'Cairn Terrier',
            'Cane Corso',
            'Cão Afegão',
            'Cão de Água Português',
            'Cão de Crista Chinês',
            'Cão de Pastor Alemão (Pastor Alemão)',
            'Cão de Pastor Shetland',
            'Cavalier King Charles Spaniel',
            'Chesapeake Bay Retriever',
            'Chihuahua',
            'Chow Chow',
            'Cocker Spaniel',
            'Collie',
            'Coton de Tulear',
            'Dachshund (Teckel)',
            'Dálmata',
            'Dobermann',
            'Dogo Argentino',
            'Dogue Alemão',
            'Fila Brasileiro',
            'Fox Paulistinha',
            'Galgo Inglês',
            'Golden Retriever',
            'Greyhound',
            'Husky Siberiano',
            'Irish Setter',
            'Irish Wolfhound',
            'Jack Russell Terrier',
            'Kelpie Australiano',
            'Kerry Blue Terrier',
            'Labradoodle',
            'Labrador Retriever',
            'Lhasa Apso',
            'Lulu da Pomerânia (Spitz Alemão)',
            'Malamute do Alasca',
            'Maltês',
            'Mastiff Inglês',
            'Mastim Tibetano',
            'Old English Sheepdog (Bobtail)',
            'Papillon',
            'Pastor Australiano',
            'Pastor Belga Malinois',
            'Pastor de Shetland',
            'Pequinês',
            'Pinscher',
            'Pinscher Miniatura',
            'Pit Bull Terrier',
            'Podengo Português',
            'Poodle',
            'Poodle Toy',
            'Pug',
            'Rottweiler',
            'Samoieda',
            'Schnauzer',
            'Scottish Terrier',
            'Serra da Estrela',
            'Shar Pei',
            'Shiba Inu',
            'Shih Tzu',
            'Spitz Japonês',
            'Springer Spaniel Inglês',
            'Staffordshire Bull Terrier',
            'Terra-Nova',
            'Vira-lata',
            'Weimaraner',
            'Welsh Corgi Pembroke',
            'Whippet',
            'Yorkshire Terrier',
        );

        $cat_breeds = array(
            'SRD (Sem Raça Definida)',
            'Abissínio',
            'Angorá Turco',
            'Azul Russo',
            'Bengal',
            'Birmanês',
            'British Shorthair',
            'Chartreux',
            'Cornish Rex',
            'Devon Rex',
            'Exótico de Pelo Curto',
            'Himalaio',
            'LaPerm',
            'Maine Coon',
            'Manx',
            'Munchkin',
            'Norueguês da Floresta',
            'Ocicat',
            'Oriental de Pelo Curto',
            'Persa',
            'Ragdoll',
            'Sagrado da Birmânia',
            'Savannah',
            'Scottish Fold',
            'Selkirk Rex',
            'Siamês',
            'Siberiano',
            'Singapura',
            'Somali',
            'Sphynx',
            'Tonquinês',
            'Toyger',
            'Van Turco',
        );

        $dataset = array(
            'cao'  => array(
                'popular' => array( 'SRD (Sem Raça Definida)', 'Shih Tzu', 'Poodle', 'Labrador Retriever', 'Golden Retriever' ),
                'all'     => $dog_breeds,
            ),
            'gato' => array(
                'popular' => array( 'SRD (Sem Raça Definida)', 'Siamês', 'Persa', 'Maine Coon', 'Ragdoll' ),
                'all'     => $cat_breeds,
            ),
        );

        $dataset['all'] = array(
            'popular' => array_values( array_unique( array_merge( $dataset['cao']['popular'], $dataset['gato']['popular'] ) ) ),
            'all'     => array_values( array_unique( array_merge( $dog_breeds, $cat_breeds ) ) ),
        );

        return $dataset;
    }

    /**
     * Retorna lista de raças para a espécie informada, com populares primeiro.
     *
     * @since 1.2.2
     * @param string $species Código da espécie (cao/gato/outro).
     * @return array
     */
    private function get_breed_options_for_species( $species ) {
        $dataset  = $this->get_breed_dataset();
        $selected = isset( $dataset[ $species ] ) ? $dataset[ $species ] : $dataset['all'];
        $merged   = array_merge( $selected['popular'], $selected['all'] );

        return array_values( array_unique( $merged ) );
    }

    /**
     * Renderiza o conteúdo da página de configurações
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Configurações de Cadastro desi.pet by PRObst', 'dps-registration-addon' ) . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields( 'dps_registration_settings' );
        do_settings_sections( 'dps_registration_settings' );
        $api_key = get_option( 'dps_google_api_key', '' );
        $recaptcha_enabled    = (bool) get_option( 'dps_registration_recaptcha_enabled', 0 );
        $recaptcha_site_key   = get_option( 'dps_registration_recaptcha_site_key', '' );
        $recaptcha_secret_key = get_option( 'dps_registration_recaptcha_secret_key', '' );
        $recaptcha_threshold  = get_option( 'dps_registration_recaptcha_threshold', 0.5 );
        $email_subject        = get_option( 'dps_registration_confirm_email_subject', '' );
        $email_body           = get_option( 'dps_registration_confirm_email_body', '' );
        $api_enabled          = (bool) get_option( 'dps_registration_api_enabled', 0 );
        $rate_key_limit       = get_option( 'dps_registration_api_rate_key_per_hour', 60 );
        $rate_ip_limit        = get_option( 'dps_registration_api_rate_ip_per_hour', 20 );
        echo '<table class="form-table">';
        echo '<tr><th scope="row"><label for="dps_google_api_key">' . esc_html__( 'Google Maps API Key', 'dps-registration-addon' ) . '</label></th>';
        echo '<td><input type="text" id="dps_google_api_key" name="dps_google_api_key" value="' . esc_attr( $api_key ) . '" class="regular-text"></td></tr>';

        echo '<tr><th scope="row">' . esc_html__( 'Ativar reCAPTCHA v3', 'dps-registration-addon' ) . '</th>';
        echo '<td><label><input type="checkbox" name="dps_registration_recaptcha_enabled" value="1" ' . checked( $recaptcha_enabled, true, false ) . '> ' . esc_html__( 'Habilitar verificação anti-spam com reCAPTCHA v3', 'dps-registration-addon' ) . '</label></td></tr>';

        echo '<tr><th scope="row"><label for="dps_registration_recaptcha_site_key">' . esc_html__( 'Site Key (reCAPTCHA v3)', 'dps-registration-addon' ) . '</label></th>';
        echo '<td><input type="text" id="dps_registration_recaptcha_site_key" name="dps_registration_recaptcha_site_key" value="' . esc_attr( $recaptcha_site_key ) . '" class="regular-text" autocomplete="off"></td></tr>';

        echo '<tr><th scope="row"><label for="dps_registration_recaptcha_secret_key">' . esc_html__( 'Secret Key (reCAPTCHA v3)', 'dps-registration-addon' ) . '</label></th>';
        echo '<td><input type="password" id="dps_registration_recaptcha_secret_key" name="dps_registration_recaptcha_secret_key" value="' . esc_attr( $recaptcha_secret_key ) . '" class="regular-text" autocomplete="new-password"></td></tr>';

        echo '<tr><th scope="row"><label for="dps_registration_recaptcha_threshold">' . esc_html__( 'Score mínimo (0-1)', 'dps-registration-addon' ) . '</label></th>';
        echo '<td><input type="number" id="dps_registration_recaptcha_threshold" name="dps_registration_recaptcha_threshold" value="' . esc_attr( $recaptcha_threshold ) . '" min="0" max="1" step="0.1">';
        echo '<p class="description">' . esc_html__( 'Cadastros com score abaixo deste valor serão bloqueados.', 'dps-registration-addon' ) . '</p></td></tr>';

        echo '<tr><th scope="row"><label for="dps_registration_confirm_email_subject">' . esc_html__( 'Assunto do email de confirmação', 'dps-registration-addon' ) . '</label></th>';
        echo '<td><input type="text" id="dps_registration_confirm_email_subject" name="dps_registration_confirm_email_subject" value="' . esc_attr( $email_subject ) . '" class="regular-text">';
        echo '<p class="description">' . esc_html__( 'Deixe vazio para usar o padrão.', 'dps-registration-addon' ) . '</p></td></tr>';

        echo '<tr><th scope="row"><label for="dps_registration_confirm_email_body">' . esc_html__( 'Corpo do email de confirmação', 'dps-registration-addon' ) . '</label></th>';
        echo '<td><textarea id="dps_registration_confirm_email_body" name="dps_registration_confirm_email_body" rows="6" class="large-text code">' . esc_textarea( $email_body ) . '</textarea>';
        echo '<p class="description">' . esc_html__( 'Suporta HTML básico e os placeholders {client_name}, {confirm_url}, {registration_url}, {portal_url}, {business_name}. Deixe vazio para usar o modelo padrão.', 'dps-registration-addon' ) . '</p></td></tr>';
        echo '</table>';

        echo '<h2>' . esc_html__( 'API de Cadastro (Fase 4)', 'dps-registration-addon' ) . '</h2>';
        echo '<table class="form-table">';
        echo '<tr><th scope="row">' . esc_html__( 'Ativar API', 'dps-registration-addon' ) . '</th>';
        echo '<td><label><input type="checkbox" name="dps_registration_api_enabled" value="1" ' . checked( $api_enabled, true, false ) . '> ' . esc_html__( 'Habilitar endpoint protegido para cadastros via API', 'dps-registration-addon' ) . '</label></td></tr>';

        echo '<tr><th scope="row"><label for="dps_registration_api_key_hash">' . esc_html__( 'API Key', 'dps-registration-addon' ) . '</label></th>';
        echo '<td><input type="text" id="dps_registration_api_key_hash" name="dps_registration_api_key_hash" value="" class="regular-text" autocomplete="off"> ';
        echo '<button type="button" class="button" id="dps_registration_generate_api_key">' . esc_html__( 'Gerar chave', 'dps-registration-addon' ) . '</button>';
        echo '<p class="description">' . esc_html__( 'A chave será mostrada apenas uma vez. Insira ou gere uma nova chave para substituir a atual.', 'dps-registration-addon' ) . '</p>';
        echo '<p class="description">' . esc_html__( 'O valor salvo será armazenado apenas como hash (sha256). Deixe em branco para manter a chave atual.', 'dps-registration-addon' ) . '</p></td></tr>';

        echo '<tr><th scope="row"><label for="dps_registration_api_rate_key_per_hour">' . esc_html__( 'Limite por chave/hora', 'dps-registration-addon' ) . '</label></th>';
        echo '<td><input type="number" id="dps_registration_api_rate_key_per_hour" name="dps_registration_api_rate_key_per_hour" value="' . esc_attr( $rate_key_limit ) . '" min="1" step="1">';
        echo '<p class="description">' . esc_html__( 'Número máximo de requisições por chave em 1 hora.', 'dps-registration-addon' ) . '</p></td></tr>';

        echo '<tr><th scope="row"><label for="dps_registration_api_rate_ip_per_hour">' . esc_html__( 'Limite por IP/hora', 'dps-registration-addon' ) . '</label></th>';
        echo '<td><input type="number" id="dps_registration_api_rate_ip_per_hour" name="dps_registration_api_rate_ip_per_hour" value="' . esc_attr( $rate_ip_limit ) . '" min="1" step="1">';
        echo '<p class="description">' . esc_html__( 'Número máximo de requisições por IP em 1 hora.', 'dps-registration-addon' ) . '</p></td></tr>';
        echo '</table>';

        echo '<script type="text/javascript">(function(){
            const btn = document.getElementById("dps_registration_generate_api_key");
            const input = document.getElementById("dps_registration_api_key_hash");
            const form = document.querySelector(".wrap form");
            const submitBtn = form ? form.querySelector("input[type=submit], button[type=submit]") : null;

            // Gera API key
            if(btn && input){
                btn.addEventListener("click", function(){
                    const cryptoObj = window.crypto || window.msCrypto;
                    if(!cryptoObj || !cryptoObj.getRandomValues){
                        input.value = Math.random().toString(36).slice(2) + Date.now().toString(36);
                        return;
                    }
                    const randomKey = Array.from(cryptoObj.getRandomValues(new Uint32Array(8)), function(v){ return v.toString(16); }).join("");
                    input.value = randomKey;
                });
            }

            // Prevenção de duplo clique e estado de loading no submit
            if(form && submitBtn){
                form.addEventListener("submit", function(e){
                    if(submitBtn.disabled){
                        e.preventDefault();
                        return false;
                    }
                    submitBtn.disabled = true;
                    submitBtn.value = "' . esc_js( __( 'Salvando...', 'dps-registration-addon' ) ) . '";
                    submitBtn.style.opacity = "0.7";
                    submitBtn.style.cursor = "wait";
                });
            }
        })();</script>';

        submit_button();
        echo '</form>';
        echo '</div>';
    }

    /**
     * Renderiza a lista de cadastros pendentes de confirmação.
     *
     * @since 1.4.0
     */
    public function render_pending_clients_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $paged       = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
        $search_term = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        // Remove caracteres especiais de LIKE para prevenir wildcard injection
        $phone_term  = preg_replace( '/\D+/', '', $search_term );
        $phone_term  = $this->escape_like_wildcards( $phone_term );

        $meta_query = [
            [
                'key'     => 'dps_email_confirmed',
                'value'   => 0,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ],
        ];

        if ( ! empty( $phone_term ) ) {
            $meta_query[] = [
                'key'     => 'client_phone',
                'value'   => $phone_term,
                'compare' => 'LIKE',
            ];
        }

        $query_args = [
            'post_type'           => 'dps_cliente',
            'post_status'         => 'publish',
            'posts_per_page'      => 20,
            'paged'               => $paged,
            's'                   => $search_term,
            'orderby'             => 'date',
            'order'               => 'DESC',
            'meta_query'          => $meta_query,
            'update_post_meta_cache' => true,
            'update_post_term_cache' => false,
        ];

        $clients_query = new WP_Query( $query_args );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Cadastros Pendentes', 'dps-registration-addon' ) . '</h1>';
        echo '<p>' . esc_html__( 'Clientes que ainda não confirmaram o e-mail aparecem aqui. Você pode abrir o cadastro para editar ou complementar dados.', 'dps-registration-addon' ) . '</p>';

        $search_url = add_query_arg( [ 'page' => 'dps-registration-pending' ], admin_url( 'admin.php' ) );
        echo '<form method="get" action="' . esc_url( $search_url ) . '" style="margin-bottom: 16px;">';
        echo '<input type="hidden" name="page" value="dps-registration-pending" />';
        echo '<label class="screen-reader-text" for="dps-registration-search">' . esc_html__( 'Buscar por nome ou telefone', 'dps-registration-addon' ) . '</label>';
        echo '<input type="search" id="dps-registration-search" name="s" value="' . esc_attr( $search_term ) . '" placeholder="' . esc_attr__( 'Buscar por nome ou telefone', 'dps-registration-addon' ) . '" /> ';
        submit_button( __( 'Buscar', 'dps-registration-addon' ), 'primary', '', false );
        echo '</form>';

        echo '<h2 class="nav-tab-wrapper" style="margin-bottom: 10px;">';
        echo '<span class="nav-tab nav-tab-active">' . esc_html__( 'Pendentes', 'dps-registration-addon' ) . '</span>';
        echo '</h2>';

        if ( $clients_query->have_posts() ) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th scope="col">' . esc_html__( 'Nome', 'dps-registration-addon' ) . '</th>';
            echo '<th scope="col">' . esc_html__( 'Telefone', 'dps-registration-addon' ) . '</th>';
            echo '<th scope="col">' . esc_html__( 'Email', 'dps-registration-addon' ) . '</th>';
            echo '<th scope="col">' . esc_html__( 'Data', 'dps-registration-addon' ) . '</th>';
            echo '<th scope="col">' . esc_html__( 'Ações', 'dps-registration-addon' ) . '</th>';
            echo '</tr></thead>';
            echo '<tbody>';

            while ( $clients_query->have_posts() ) {
                $clients_query->the_post();
                $client_id    = get_the_ID();
                $client_phone = get_post_meta( $client_id, 'client_phone', true );
                $client_email = get_post_meta( $client_id, 'client_email', true );

                echo '<tr>';
                echo '<td>' . esc_html( get_the_title() ) . '</td>';
                echo '<td>' . esc_html( $client_phone ) . '</td>';
                echo '<td>' . esc_html( $client_email ) . '</td>';
                echo '<td>' . esc_html( get_the_date() ) . '</td>';

                $edit_link = get_edit_post_link( $client_id );
                echo '<td>';
                if ( $edit_link ) {
                    echo '<a class="button" href="' . esc_url( $edit_link ) . '">' . esc_html__( 'Editar cadastro', 'dps-registration-addon' ) . '</a>';
                } else {
                    echo esc_html__( 'Sem ações disponíveis', 'dps-registration-addon' );
                }
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';

            $total_pages = $clients_query->max_num_pages;
            if ( $total_pages > 1 ) {
                $pagination_links = paginate_links( [
                    'base'      => add_query_arg( [ 'paged' => '%#%', 'page' => 'dps-registration-pending', 's' => $search_term ], admin_url( 'admin.php' ) ),
                    'format'    => '',
                    'current'   => $paged,
                    'total'     => $total_pages,
                    'prev_text' => __( '« Anterior', 'dps-registration-addon' ),
                    'next_text' => __( 'Próximo »', 'dps-registration-addon' ),
                ] );

                if ( $pagination_links ) {
                    echo '<div class="tablenav"><div class="tablenav-pages" style="margin-top: 10px;">' . wp_kses_post( $pagination_links ) . '</div></div>';
                }
            }
        } else {
            echo '<div class="notice notice-info inline" style="margin-top: 10px;"><p>' . esc_html__( 'Nenhum cadastro pendente encontrado.', 'dps-registration-addon' ) . '</p></div>';
        }

        wp_reset_postdata();
        echo '</div>';
    }

    /**
     * Processa o formulário de cadastro quando enviado via POST. Cria um novo cliente e um ou mais pets
     * associados. Após o processamento, define uma mensagem de sucesso para ser exibida.
     *
     * @since 1.0.0
     * @since 1.1.0 Adicionadas validações de CPF, telefone, email, duplicatas e rate limiting.
     */
    public function maybe_handle_registration() {
        if ( ! isset( $_POST['dps_reg_action'] ) || 'save_registration' !== $_POST['dps_reg_action'] ) {
            return;
        }

        // F1.8: Verifica nonce com feedback de erro
        if ( ! isset( $_POST['dps_reg_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dps_reg_nonce'] ) ), 'dps_reg_action' ) ) {
            $this->add_error( __( 'Erro de segurança. Por favor, recarregue a página e tente novamente.', 'dps-registration-addon' ) );
            $this->redirect_with_error();
        }

        // F1.8: Honeypot para bots com feedback silencioso (não revela para bots)
        if ( ! empty( $_POST['dps_hp_field'] ) ) {
            $this->add_error( __( 'Erro ao processar o formulário. Por favor, tente novamente.', 'dps-registration-addon' ) );
            $this->redirect_with_error();
        }

        // F1.6: Rate limiting
        if ( ! $this->check_rate_limit() ) {
            $this->log_event( 'warning', 'Cadastro bloqueado por rate limit', array(
                'ip_hash' => $this->get_client_ip_hash(),
            ) );
            $this->add_error( __( 'Muitas tentativas de cadastro. Por favor, aguarde alguns minutos antes de tentar novamente.', 'dps-registration-addon' ) );
            $this->redirect_with_error();
        }

        $recaptcha_settings = $this->get_recaptcha_settings();
        if ( $recaptcha_settings['enabled'] ) {
            $recaptcha_token  = isset( $_POST['dps_recaptcha_token'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_recaptcha_token'] ) ) : '';
            $recaptcha_action = isset( $_POST['dps_recaptcha_action'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_recaptcha_action'] ) ) : '';

            if ( empty( $recaptcha_token ) ) {
                $this->add_error( __( 'Não foi possível validar o anti-spam. Tente novamente.', 'dps-registration-addon' ) );
                $this->redirect_with_error();
            }

            if ( self::RECAPTCHA_ACTION !== $recaptcha_action ) {
                $this->add_error( __( 'Não foi possível validar o anti-spam. Tente novamente.', 'dps-registration-addon' ) );
                $this->redirect_with_error();
            }

            $recaptcha_result = $this->verify_recaptcha_token( $recaptcha_token, $recaptcha_settings );

            if ( is_wp_error( $recaptcha_result ) || true !== $recaptcha_result ) {
                $message = is_wp_error( $recaptcha_result ) ? $recaptcha_result->get_error_message() : __( 'Não foi possível validar o anti-spam. Tente novamente.', 'dps-registration-addon' );
                $this->add_error( $message );
                $this->redirect_with_error();
            }
        }

        // F1.8: Hook para validações adicionais (ex.: reCAPTCHA)
        // Nota: o filtro recebe uma cópia sanitizada de campos selecionados, NÃO o $_POST bruto
        $sanitized_context = array(
            'client_name'  => isset( $_POST['client_name'] ) ? sanitize_text_field( wp_unslash( $_POST['client_name'] ) ) : '',
            'client_email' => isset( $_POST['client_email'] ) ? sanitize_email( wp_unslash( $_POST['client_email'] ) ) : '',
            'ip_hash'      => $this->get_client_ip_hash(),
        );
        $spam_check = apply_filters( 'dps_registration_spam_check', true, $sanitized_context );
        if ( true !== $spam_check ) {
            $this->add_error( __( 'Verificação de segurança falhou. Por favor, tente novamente.', 'dps-registration-addon' ) );
            $this->redirect_with_error();
        }

        // Sanitiza dados do cliente - wp_unslash antes de sanitize para tratar magic quotes
        $client_name     = isset( $_POST['client_name'] ) ? sanitize_text_field( wp_unslash( $_POST['client_name'] ) ) : '';
        $client_cpf_raw  = isset( $_POST['client_cpf'] ) ? sanitize_text_field( wp_unslash( $_POST['client_cpf'] ) ) : '';
        $client_phone_raw = isset( $_POST['client_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['client_phone'] ) ) : '';
        $client_email    = isset( $_POST['client_email'] ) ? sanitize_email( wp_unslash( $_POST['client_email'] ) ) : '';
        $client_birth    = isset( $_POST['client_birth'] ) ? sanitize_text_field( wp_unslash( $_POST['client_birth'] ) ) : '';
        $client_instagram = isset( $_POST['client_instagram'] ) ? sanitize_text_field( wp_unslash( $_POST['client_instagram'] ) ) : '';
        $client_facebook = isset( $_POST['client_facebook'] ) ? sanitize_text_field( wp_unslash( $_POST['client_facebook'] ) ) : '';
        $client_photo_auth = isset( $_POST['client_photo_auth'] ) ? 1 : 0;
        $client_address  = isset( $_POST['client_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['client_address'] ) ) : '';
        $client_referral = isset( $_POST['client_referral'] ) ? sanitize_text_field( wp_unslash( $_POST['client_referral'] ) ) : '';
        $referral_code   = isset( $_POST['dps_referral_code'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_referral_code'] ) ) : '';

        // Coordenadas de latitude e longitude (podem estar vazias) - validar formato numérico
        $client_lat_raw  = isset( $_POST['client_lat'] ) ? sanitize_text_field( wp_unslash( $_POST['client_lat'] ) ) : '';
        $client_lng_raw  = isset( $_POST['client_lng'] ) ? sanitize_text_field( wp_unslash( $_POST['client_lng'] ) ) : '';
        // Validar que são coordenadas numéricas válidas (latitude: -90 a 90, longitude: -180 a 180)
        $client_lat = '';
        $client_lng = '';
        if ( '' !== $client_lat_raw && is_numeric( $client_lat_raw ) ) {
            $lat_val = (float) $client_lat_raw;
            if ( $lat_val >= -90 && $lat_val <= 90 ) {
                $client_lat = (string) $lat_val;
            }
        }
        if ( '' !== $client_lng_raw && is_numeric( $client_lng_raw ) ) {
            $lng_val = (float) $client_lng_raw;
            if ( $lng_val >= -180 && $lng_val <= 180 ) {
                $client_lng = (string) $lng_val;
            }
        }

        // =====================================================================
        // F1.1: Validação de campos obrigatórios no backend
        // =====================================================================
        $validation_errors = [];

        if ( empty( $client_name ) ) {
            $validation_errors[] = __( 'O campo Nome é obrigatório.', 'dps-registration-addon' );
        }

        if ( empty( $client_phone_raw ) ) {
            $validation_errors[] = __( 'O campo Telefone / WhatsApp é obrigatório.', 'dps-registration-addon' );
        }

        // =====================================================================
        // F1.3/F1.9: Normalização e validação de telefone
        // =====================================================================
        $client_phone = $this->normalize_phone( $client_phone_raw );

        if ( ! empty( $client_phone_raw ) && ! $this->validate_phone( $client_phone_raw ) ) {
            $validation_errors[] = __( 'O telefone informado não é válido. Use o formato (11) 98765-4321.', 'dps-registration-addon' );
        }

        // =====================================================================
        // F1.2: Validação de CPF (se preenchido)
        // =====================================================================
        $client_cpf = $this->normalize_cpf( $client_cpf_raw );

        if ( ! empty( $client_cpf_raw ) && ! $this->validate_cpf( $client_cpf_raw ) ) {
            $validation_errors[] = __( 'O CPF informado não é válido. Verifique os dígitos.', 'dps-registration-addon' );
        }

        // =====================================================================
        // F1.4: Validação de email (se preenchido)
        // =====================================================================
        if ( ! empty( $client_email ) && ! is_email( $client_email ) ) {
            $validation_errors[] = __( 'O email informado não é válido.', 'dps-registration-addon' );
        }

        // Se houver erros de validação, redireciona
        if ( ! empty( $validation_errors ) ) {
            foreach ( $validation_errors as $error ) {
                $this->add_error( $error );
            }
            $this->redirect_with_error();
        }

        // =====================================================================
        // F1.5: Detecção de duplicatas
        // =====================================================================
        $duplicate_id = $this->find_duplicate_client( $client_email, $client_phone, $client_cpf );
        if ( $duplicate_id > 0 ) {
            $this->log_event( 'warning', 'Cadastro bloqueado por duplicata', array(
                'duplicate_id' => $duplicate_id,
                'email_hash'   => $this->get_safe_hash( $client_email ),
                'phone_hash'   => $this->get_safe_hash( $client_phone ),
                'cpf_hash'     => $this->get_safe_hash( $client_cpf ),
                'ip_hash'      => $this->get_client_ip_hash(),
            ) );
            $this->add_error( __( 'Já encontramos um cadastro com esses dados. Se você já se cadastrou, verifique seu e-mail (se informado) ou fale com a equipe do pet shop.', 'dps-registration-addon' ) );
            $this->redirect_with_error();
        }

        // =====================================================================
        // Cria cliente (todas as validações passaram)
        // =====================================================================
        $client_id = wp_insert_post( [
            'post_type'   => 'dps_cliente',
            'post_title'  => $client_name,
            'post_status' => 'publish',
        ] );

        if ( ! $client_id || is_wp_error( $client_id ) ) {
            $this->add_error( __( 'Erro ao criar cadastro. Por favor, tente novamente.', 'dps-registration-addon' ) );
            $this->redirect_with_error();
        }

        // F1.9: Telefone é salvo normalizado (apenas dígitos)
        update_post_meta( $client_id, 'client_cpf', $client_cpf );
        update_post_meta( $client_id, 'client_phone', $client_phone );
        update_post_meta( $client_id, 'client_email', $client_email );
        update_post_meta( $client_id, 'client_birth', $client_birth );
        update_post_meta( $client_id, 'client_instagram', $client_instagram );
        update_post_meta( $client_id, 'client_facebook', $client_facebook );
        update_post_meta( $client_id, 'client_photo_auth', $client_photo_auth );
        update_post_meta( $client_id, 'client_address', $client_address );
        update_post_meta( $client_id, 'client_referral', $client_referral );
        update_post_meta( $client_id, 'dps_email_confirmed', 0 );
        update_post_meta( $client_id, 'dps_is_active', 0 );
        update_post_meta( $client_id, 'dps_registration_source', 'public' );
        if ( ! empty( $referral_code ) ) {
            update_post_meta( $client_id, 'dps_registration_ref', $referral_code );
        }

        // Salva coordenadas se fornecidas
        if ( $client_lat !== '' && $client_lng !== '' ) {
            update_post_meta( $client_id, 'client_lat', $client_lat );
            update_post_meta( $client_id, 'client_lng', $client_lng );
        }

        if ( $client_email ) {
            $this->send_confirmation_email( $client_id, $client_email );
        }

        do_action( 'dps_registration_after_client_created', $referral_code, $client_id, $client_email, $client_phone );

        $pets_created = 0;

        // Lê pets submetidos (campos em arrays) - aplica wp_unslash em arrays
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitização aplicada individualmente
        $pet_names      = isset( $_POST['pet_name'] ) && is_array( $_POST['pet_name'] ) ? array_map( 'wp_unslash', $_POST['pet_name'] ) : [];
        $pet_species    = isset( $_POST['pet_species'] ) && is_array( $_POST['pet_species'] ) ? array_map( 'wp_unslash', $_POST['pet_species'] ) : [];
        $pet_breeds     = isset( $_POST['pet_breed'] ) && is_array( $_POST['pet_breed'] ) ? array_map( 'wp_unslash', $_POST['pet_breed'] ) : [];
        $pet_sizes      = isset( $_POST['pet_size'] ) && is_array( $_POST['pet_size'] ) ? array_map( 'wp_unslash', $_POST['pet_size'] ) : [];
        $pet_weights    = isset( $_POST['pet_weight'] ) && is_array( $_POST['pet_weight'] ) ? array_map( 'wp_unslash', $_POST['pet_weight'] ) : [];
        $pet_coats      = isset( $_POST['pet_coat'] ) && is_array( $_POST['pet_coat'] ) ? array_map( 'wp_unslash', $_POST['pet_coat'] ) : [];
        $pet_colors     = isset( $_POST['pet_color'] ) && is_array( $_POST['pet_color'] ) ? array_map( 'wp_unslash', $_POST['pet_color'] ) : [];
        $pet_births     = isset( $_POST['pet_birth'] ) && is_array( $_POST['pet_birth'] ) ? array_map( 'wp_unslash', $_POST['pet_birth'] ) : [];
        $pet_sexes      = isset( $_POST['pet_sex'] ) && is_array( $_POST['pet_sex'] ) ? array_map( 'wp_unslash', $_POST['pet_sex'] ) : [];
        $pet_cares      = isset( $_POST['pet_care'] ) && is_array( $_POST['pet_care'] ) ? array_map( 'wp_unslash', $_POST['pet_care'] ) : [];
        // pet_aggressive é checkbox - valor é só "1", não precisa wp_unslash mas aplicamos por consistência
        $pet_aggs       = isset( $_POST['pet_aggressive'] ) && is_array( $_POST['pet_aggressive'] ) ? array_map( 'wp_unslash', $_POST['pet_aggressive'] ) : [];
        // phpcs:enable

        if ( is_array( $pet_names ) ) {
            foreach ( $pet_names as $index => $pname ) {
                $pname = sanitize_text_field( $pname );
                if ( ! $pname ) {
                    continue;
                }
                // Coleta campos do pet
                $species  = is_array( $pet_species ) && isset( $pet_species[ $index ] ) ? sanitize_text_field( $pet_species[ $index ] ) : '';
                $breed    = is_array( $pet_breeds )  && isset( $pet_breeds[ $index ] )  ? sanitize_text_field( $pet_breeds[ $index ] )  : '';
                $size     = is_array( $pet_sizes )   && isset( $pet_sizes[ $index ] )   ? sanitize_text_field( $pet_sizes[ $index ] )   : '';
                $weight   = is_array( $pet_weights ) && isset( $pet_weights[ $index ] ) ? sanitize_text_field( $pet_weights[ $index ] ) : '';
                $coat     = is_array( $pet_coats )   && isset( $pet_coats[ $index ] )   ? sanitize_text_field( $pet_coats[ $index ] )   : '';
                $color    = is_array( $pet_colors )  && isset( $pet_colors[ $index ] )  ? sanitize_text_field( $pet_colors[ $index ] )  : '';
                $birth    = is_array( $pet_births )  && isset( $pet_births[ $index ] )  ? sanitize_text_field( $pet_births[ $index ] )  : '';
                $sex      = is_array( $pet_sexes )   && isset( $pet_sexes[ $index ] )   ? sanitize_text_field( $pet_sexes[ $index ] )   : '';
                $care     = is_array( $pet_cares )   && isset( $pet_cares[ $index ] )   ? sanitize_textarea_field( $pet_cares[ $index ] )   : '';
                $agg      = is_array( $pet_aggs )    && isset( $pet_aggs[ $index ] )    ? 1 : 0;

                // Whitelist validation para campos com valores predefinidos
                $valid_species = array( 'cao', 'gato', 'outro' );
                $valid_sizes   = array( 'pequeno', 'medio', 'grande' );
                $valid_sexes   = array( 'macho', 'femea' );

                if ( ! in_array( $species, $valid_species, true ) ) {
                    $species = '';
                }
                if ( ! in_array( $size, $valid_sizes, true ) ) {
                    $size = '';
                }
                if ( ! in_array( $sex, $valid_sexes, true ) ) {
                    $sex = '';
                }

                // Validar peso como número positivo (se preenchido)
                // Aceita apenas dígitos, vírgula e ponto para prevenir input malformado
                if ( '' !== $weight ) {
                    if ( ! preg_match( '/^[\d.,]+$/', $weight ) ) {
                        $weight = '';
                    } else {
                        $weight_float = (float) str_replace( ',', '.', $weight );
                        if ( $weight_float <= 0 || $weight_float > 500 ) {
                            $weight = ''; // Valor inválido ou implausível
                        } else {
                            $weight = (string) $weight_float;
                        }
                    }
                }

                // Validar data de nascimento (formato Y-m-d, não futura)
                if ( '' !== $birth ) {
                    $birth_time = strtotime( $birth );
                    if ( false === $birth_time || $birth_time > time() ) {
                        $birth = ''; // Data inválida ou futura
                    }
                }

                // Cria pet
                $pet_id = wp_insert_post( [
                    'post_type'   => 'dps_pet',
                    'post_title'  => $pname,
                    'post_status' => 'publish',
                ] );
                if ( $pet_id ) {
                    $pets_created++;
                    update_post_meta( $pet_id, 'owner_id', $client_id );
                    update_post_meta( $pet_id, 'pet_species', $species );
                    update_post_meta( $pet_id, 'pet_breed', $breed );
                    update_post_meta( $pet_id, 'pet_size', $size );
                    update_post_meta( $pet_id, 'pet_weight', $weight );
                    update_post_meta( $pet_id, 'pet_coat', $coat );
                    update_post_meta( $pet_id, 'pet_color', $color );
                    update_post_meta( $pet_id, 'pet_birth', $birth );
                    update_post_meta( $pet_id, 'pet_sex', $sex );
                    update_post_meta( $pet_id, 'pet_care', $care );
                    update_post_meta( $pet_id, 'pet_aggressive', $agg );
                }
            }
        }

        $this->send_admin_notification( $client_id, $client_name, $client_phone_raw );
        $this->send_welcome_messages( $client_phone, $client_email, $client_name );

        $this->log_event( 'info', 'Cadastro criado com sucesso', array(
            'client_id' => $client_id,
            'pets'      => $pets_created,
            'has_email' => ! empty( $client_email ),
            'has_cpf'   => ! empty( $client_cpf ),
            'ip_hash'   => $this->get_client_ip_hash(),
        ) );

        // Redireciona e indica sucesso - usa wp_safe_redirect para segurança
        wp_safe_redirect( add_query_arg( 'registered', '1', $this->get_registration_page_url() ) );
        exit;
    }

    /**
     * Processa a confirmação de email via token presente na URL.
     *
     * @since 1.0.0
     * @since 1.1.0 Adicionada validação de expiração do token (48h).
     */
    public function maybe_handle_email_confirmation() {
        if ( empty( $_GET['dps_confirm_email'] ) ) {
            return;
        }

        $token = sanitize_text_field( wp_unslash( $_GET['dps_confirm_email'] ) );
        $client = get_posts( [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => 'dps_email_confirm_token',
                    'value' => $token,
                ],
            ],
        ] );

        if ( empty( $client ) ) {
            // Token não encontrado - pode ter sido usado ou expirado
            $this->log_event( 'warning', 'Confirmação de email inválida', array(
                'token_hash' => $this->get_safe_hash( $token ),
            ) );
            $this->add_error( __( 'Link de confirmação inválido ou já utilizado. Se você já confirmou seu email, seu cadastro está ativo. Caso contrário, tente realizar um novo cadastro ou entre em contato com a equipe.', 'dps-registration-addon' ) );
            $this->redirect_with_error();
        }

        $client_id = absint( $client[0] );

        // F1.7: Verificar expiração do token (48h)
        $token_created = get_post_meta( $client_id, 'dps_email_confirm_token_created', true );
        if ( $token_created ) {
            // Valida que o timestamp é um inteiro válido
            $created_timestamp = (int) $token_created;
            if ( $created_timestamp > 0 && $created_timestamp <= time() ) {
                $token_age = time() - $created_timestamp;
                
                if ( $token_age > self::TOKEN_EXPIRATION_SECONDS ) {
                    // Token expirado - limpa e mostra erro
                    delete_post_meta( $client_id, 'dps_email_confirm_token' );
                    delete_post_meta( $client_id, 'dps_email_confirm_token_created' );

                    $this->log_event( 'warning', 'Confirmação de email expirada', array(
                        'client_id'  => $client_id,
                        'token_age'  => $token_age,
                        'token_hash' => $this->get_safe_hash( $token ),
                    ) );
                    $this->add_error( __( 'O link de confirmação expirou. Por favor, realize um novo cadastro ou entre em contato com a equipe do pet shop.', 'dps-registration-addon' ) );
                    $this->redirect_with_error();
                }
            }
        }

        // Token válido - confirma email e ativa cadastro
        update_post_meta( $client_id, 'dps_email_confirmed', 1 );
        update_post_meta( $client_id, 'dps_is_active', 1 );
        delete_post_meta( $client_id, 'dps_email_confirm_token' );
        delete_post_meta( $client_id, 'dps_email_confirm_token_created' );

        $this->log_event( 'info', 'Confirmação de email concluída', array(
            'client_id' => $client_id,
            'ip_hash'   => $this->get_client_ip_hash(),
        ) );
        $redirect = add_query_arg( 'dps_email_confirmed', '1', $this->get_registration_page_url() );
        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Valida token do reCAPTCHA v3 com o endpoint oficial.
     *
     * @since 1.5.0
     *
     * @param string $token              Token retornado pelo reCAPTCHA.
     * @param array  $recaptcha_settings Configurações sanitizadas.
     * @return true|WP_Error
     */
    private function verify_recaptcha_token( $token, $recaptcha_settings ) {
        if ( empty( $recaptcha_settings['secret_key'] ) ) {
            $this->log_event( 'error', 'reCAPTCHA habilitado sem secret key', array(
                'ip_hash'     => $this->get_client_ip_hash(),
                'token_hash'  => $this->get_safe_hash( $token ),
                'has_sitekey' => ! empty( $recaptcha_settings['site_key'] ),
            ) );
            return new WP_Error( 'recaptcha_misconfigured', __( 'Não foi possível validar o anti-spam. Tente novamente.', 'dps-registration-addon' ) );
        }

        $remote_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

        $response = wp_remote_post(
            'https://www.google.com/recaptcha/api/siteverify',
            array(
                'timeout' => 10,
                'body'    => array(
                    'secret'   => $recaptcha_settings['secret_key'],
                    'response' => $token,
                    'remoteip' => $remote_ip,
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            $this->log_event( 'error', 'Erro ao consultar reCAPTCHA', array(
                'error'      => $response->get_error_message(),
                'ip_hash'    => $this->get_client_ip_hash(),
                'token_hash' => $this->get_safe_hash( $token ),
            ) );
            return new WP_Error( 'recaptcha_unavailable', __( 'Não foi possível validar o anti-spam. Tente novamente.', 'dps-registration-addon' ) );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = wp_remote_retrieve_body( $response );
        $data        = json_decode( $body, true );

        if ( 200 !== (int) $status_code || ! is_array( $data ) ) {
            $this->log_event( 'error', 'Resposta inválida do reCAPTCHA', array(
                'status'     => $status_code,
                'ip_hash'    => $this->get_client_ip_hash(),
                'token_hash' => $this->get_safe_hash( $token ),
            ) );
            return new WP_Error( 'recaptcha_unavailable', __( 'Não foi possível validar o anti-spam. Tente novamente.', 'dps-registration-addon' ) );
        }

        if ( empty( $data['success'] ) ) {
            $this->log_event( 'warning', 'reCAPTCHA falhou', array(
                'error_codes' => isset( $data['error-codes'] ) ? $data['error-codes'] : array(),
                'ip_hash'     => $this->get_client_ip_hash(),
                'token_hash'  => $this->get_safe_hash( $token ),
            ) );
            return new WP_Error( 'recaptcha_failed', __( 'Validação anti-spam reprovada. Por favor, tente novamente.', 'dps-registration-addon' ) );
        }

        if ( isset( $data['action'] ) && self::RECAPTCHA_ACTION !== $data['action'] ) {
            $this->log_event( 'warning', 'Ação do reCAPTCHA divergente', array(
                'action'     => $data['action'],
                'ip_hash'    => $this->get_client_ip_hash(),
                'token_hash' => $this->get_safe_hash( $token ),
            ) );
            return new WP_Error( 'recaptcha_failed', __( 'Validação anti-spam reprovada. Por favor, tente novamente.', 'dps-registration-addon' ) );
        }

        if ( isset( $data['score'] ) && floatval( $data['score'] ) < $recaptcha_settings['threshold'] ) {
            $this->log_event( 'warning', 'Score do reCAPTCHA abaixo do mínimo', array(
                'score'      => $data['score'],
                'threshold'  => $recaptcha_settings['threshold'],
                'ip_hash'    => $this->get_client_ip_hash(),
                'token_hash' => $this->get_safe_hash( $token ),
            ) );
            return new WP_Error( 'recaptcha_low_score', __( 'Validação anti-spam reprovada. Por favor, tente novamente.', 'dps-registration-addon' ) );
        }

        return true;
    }

    /**
     * Envia notificação para o admin sobre novo cadastro.
     *
     * @since 1.3.0
     *
     * @param int    $client_id   ID do cliente criado.
     * @param string $client_name Nome do tutor.
     * @param string $client_phone Telefone informado.
     */
    private function send_admin_notification( $client_id, $client_name, $client_phone ) {
        $admin_email = get_option( 'admin_email' );
        if ( ! $admin_email || ! is_email( $admin_email ) ) {
            return;
        }

        $subject = __( 'Novo cadastro recebido (DPS)', 'dps-registration-addon' );

        $edit_link = get_edit_post_link( $client_id );
        $body_parts = array();
        $body_parts[] = sprintf( '%s: %s', __( 'Nome', 'dps-registration-addon' ), $client_name );
        if ( ! empty( $client_phone ) ) {
            $body_parts[] = sprintf( '%s: %s', __( 'Telefone', 'dps-registration-addon' ), $client_phone );
        }
        if ( $edit_link ) {
            $body_parts[] = sprintf( '%s: %s', __( 'Editar cadastro', 'dps-registration-addon' ), esc_url_raw( $edit_link ) );
        }

        $body = implode( "\n", $body_parts );
        wp_mail( $admin_email, $subject, $body );
    }

    /**
     * Dispara mensagens de boas-vindas via Communications (WhatsApp/email) quando disponível.
     *
     * @since 1.3.0
     *
     * @param string $client_phone Telefone normalizado (apenas dígitos).
     * @param string $client_email Email validado.
     * @param string $client_name  Nome do tutor.
     */
    private function send_welcome_messages( $client_phone, $client_email, $client_name ) {
        if ( ! class_exists( 'DPS_Communications_API' ) ) {
            return;
        }

        $communications = DPS_Communications_API::get_instance();
        if ( ! $communications ) {
            return;
        }

        $context = array( 'source' => 'registration' );
        $welcome_message = sprintf(
            '%s %s',
            __( 'Olá! Recebemos seu cadastro. Em breve nossa equipe entrará em contato para os próximos passos.', 'dps-registration-addon' ),
            __( 'Enquanto isso, você pode confirmar seu email (se informou) e deixar seus pets prontos para agendar.', 'dps-registration-addon' )
        );

        if ( ! empty( $client_phone ) && method_exists( $communications, 'send_whatsapp' ) ) {
            $communications->send_whatsapp( $client_phone, $welcome_message, $context );
        }

        if ( ! empty( $client_email ) && is_email( $client_email ) && method_exists( $communications, 'send_email' ) ) {
            $communications->send_email(
                $client_email,
                __( 'Bem-vindo ao desi.pet by PRObst', 'dps-registration-addon' ),
                $welcome_message,
                $context
            );
        }
    }

    /**
     * Renderiza o formulário de cadastro de cliente e pets. Mostra mensagem de sucesso se necessário.
     *
     * @return string HTML
     *
     * @since 1.0.0
     * @since 1.2.0 Removido session_start (F2.9), melhoradas mensagens de sucesso (F2.3/F2.8).
     */
    public function render_registration_form() {
        // F2.9: Removido session_start() - não é mais necessário pois usamos transients/cookies
        
        $success = false;
        if ( isset( $_GET['registered'] ) && '1' === $_GET['registered'] ) {
            $success = true;
        }
        $ref_param = isset( $_GET['ref'] ) ? sanitize_text_field( wp_unslash( $_GET['ref'] ) ) : '';
        // Pré-renderiza o primeiro conjunto de campos de pet e o template de clonagem
        $first_pet_html   = $this->get_pet_fieldset_html( 1 );
        $placeholder_html = $this->get_pet_fieldset_html_placeholder();
        // Codifica o HTML do template em JSON para uso seguro em JavaScript (preserva < >)
        $placeholder_json = wp_json_encode( $placeholder_html );
        ob_start();
        echo '<div class="dps-registration-form">';
        
        // F1.8: Exibe mensagens de erro/sucesso armazenadas
        $messages_html = $this->display_messages();
        if ( ! empty( $messages_html ) ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML já escapado no método
            echo $messages_html;
        }
        
        // F2.3/F2.8: Mensagem de sucesso melhorada com próximo passo
        if ( $success ) {
            echo '<div class="dps-success-box" style="padding: 16px 20px; margin-bottom: 20px; border-radius: 6px; background-color: #f0fdf4; border: 1px solid #22c55e; color: #166534;" role="status">';
            echo '<h4 style="margin: 0 0 12px 0; color: #166534;">' . esc_html__( '✓ Cadastro realizado com sucesso!', 'dps-registration-addon' ) . '</h4>';
            echo '<p style="margin: 0 0 8px 0;">' . esc_html__( 'Seus dados foram recebidos. Você já pode agendar banho e tosa para seus pets!', 'dps-registration-addon' ) . '</p>';
            echo '<p style="margin: 0 0 8px 0;"><strong>' . esc_html__( 'Próximo passo:', 'dps-registration-addon' ) . '</strong> ';
            echo esc_html__( 'Entre em contato conosco por WhatsApp ou telefone para agendar o primeiro atendimento.', 'dps-registration-addon' ) . '</p>';
            echo '<p style="margin: 0; font-size: 0.9em; color: #15803d;">' . esc_html__( 'Se você informou um email, verifique sua caixa de entrada para confirmar o cadastro e receber novidades.', 'dps-registration-addon' ) . '</p>';
            $agenda_url = $this->get_agenda_cta_url();
            if ( $agenda_url ) {
                echo '<p style="margin: 12px 0 0 0;">';
                echo '<a class="button button-primary" href="' . esc_url( $agenda_url ) . '" style="background:#16a34a; border-color:#16a34a; text-decoration:none;">' . esc_html__( 'Agendar meu primeiro atendimento', 'dps-registration-addon' ) . '</a>';
                echo '</p>';
            }
            echo '</div>';
            // Não exibir formulário novamente após sucesso
            echo '</div>';
            return ob_get_clean();
        }
        if ( isset( $_GET['dps_email_confirmed'] ) && '1' === $_GET['dps_email_confirmed'] ) {
            echo '<div class="dps-success-box" style="padding: 16px 20px; margin-bottom: 20px; border-radius: 6px; background-color: #f0fdf4; border: 1px solid #22c55e; color: #166534;" role="status">';
            echo '<h4 style="margin: 0 0 12px 0; color: #166534;">' . esc_html__( '✓ Email confirmado com sucesso!', 'dps-registration-addon' ) . '</h4>';
            echo '<p style="margin: 0;">' . esc_html__( 'Seu cadastro está ativo. Agora você pode agendar banho e tosa para seus pets e receber novidades por email.', 'dps-registration-addon' ) . '</p>';
            echo '</div>';
        }
        echo '<form method="post" id="dps-reg-form">';
        echo '<input type="hidden" name="dps_reg_action" value="save_registration">';
        wp_nonce_field( 'dps_reg_action', 'dps_reg_nonce' );
        $recaptcha = $this->get_recaptcha_settings();
        if ( $recaptcha['enabled'] && ! empty( $recaptcha['site_key'] ) ) {
            echo '<input type="hidden" name="dps_recaptcha_token" value="">';
            echo '<input type="hidden" name="dps_recaptcha_action" value="' . esc_attr( self::RECAPTCHA_ACTION ) . '">';
        }
        if ( $ref_param ) {
            echo '<input type="hidden" name="dps_referral_code" value="' . esc_attr( $ref_param ) . '">';
        }
        echo '<div class="dps-hp-field" aria-hidden="true" style="position:absolute; left:-9999px; width:1px; height:1px; overflow:hidden;">';
        echo '<label for="dps_hp_field">' . esc_html__( 'Deixe este campo vazio', 'desi-pet-shower' ) . '</label>';
        echo '<input type="text" name="dps_hp_field" id="dps_hp_field" tabindex="-1" autocomplete="off">';
        echo '</div>';

        echo '<div class="dps-progress" aria-live="polite">';
        echo '<div class="dps-progress-top">';
        echo '<span id="dps-step-label">' . esc_html__( 'Passo 1 de 2', 'dps-registration-addon' ) . '</span>';
        echo '<span id="dps-step-counter">1/2</span>';
        echo '</div>';
        echo '<div class="dps-progress-bar" role="progressbar" aria-valuemin="1" aria-valuemax="2" aria-valuenow="1">';
        echo '<span id="dps-progress-bar-fill"></span>';
        echo '</div>';
        echo '</div>';

        echo '<div class="dps-steps">';
        echo '<div class="dps-step dps-step-active" data-step="1">';
        echo '<h4>' . esc_html__( 'Dados do Cliente', 'dps-registration-addon' ) . '</h4>';
        // Campos do cliente agrupados para melhor distribuição
        echo '<div class="dps-client-fields">';
        echo '<p><label>' . esc_html__( 'Nome', 'dps-registration-addon' ) . '<br><input type="text" name="client_name" id="dps-client-name" required></label></p>';
        echo '<p><label>CPF<br><input type="text" name="client_cpf"></label></p>';
        echo '<p><label>' . esc_html__( 'Telefone / WhatsApp', 'dps-registration-addon' ) . '<br><input type="text" name="client_phone" required></label></p>';
        echo '<p><label>Email<br><input type="email" name="client_email"></label></p>';
        echo '<p><label>' . esc_html__( 'Data de nascimento', 'dps-registration-addon' ) . '<br><input type="date" name="client_birth"></label></p>';
        echo '<p><label>Instagram<br><input type="text" name="client_instagram" placeholder="@usuario"></label></p>';
        echo '<p><label>Facebook<br><input type="text" name="client_facebook"></label></p>';
        echo '<p><label><input type="checkbox" name="client_photo_auth" value="1"> ' . esc_html__( 'Autorizo publicação da foto do pet nas redes sociais do desi.pet by PRObst', 'dps-registration-addon' ) . '</label></p>';
        // Endereço completo com id específico para ativar autocomplete do Google
        echo '<p style="flex:1 1 100%;"><label>' . esc_html__( 'Endereço completo', 'dps-registration-addon' ) . '<br><textarea name="client_address" id="dps-client-address" rows="2"></textarea></label></p>';
        echo '<p style="flex:1 1 100%;"><label>' . esc_html__( 'Como nos conheceu?', 'dps-registration-addon' ) . '<br><input type="text" name="client_referral"></label></p>';
        echo '</div>';
        echo '<div class="dps-step-actions">';
        echo '<button type="button" id="dps-next-step" class="button button-primary dps-button-next">' . esc_html__( 'Próximo', 'dps-registration-addon' ) . '</button>';
        echo '</div>';
        echo '</div>';

        echo '<div class="dps-step" data-step="2">';
        echo '<h4>' . esc_html__( 'Dados dos Pets', 'dps-registration-addon' ) . '</h4>';
        echo '<div id="dps-pets-wrapper">';
        // Insere o primeiro conjunto de campos de pet
        echo $first_pet_html;
        echo '</div>';
        // Botão para adicionar outro pet
        echo '<p><button type="button" id="dps-add-pet" class="button">' . esc_html__( 'Adicionar outro pet', 'dps-registration-addon' ) . '</button></p>';
        echo '<div class="dps-summary-box" id="dps-summary-box">';
        echo '<div class="dps-summary-header">';
        echo '<h4>' . esc_html__( 'Resumo antes de enviar', 'dps-registration-addon' ) . '</h4>';
        echo '<p class="dps-summary-subtitle">' . esc_html__( 'Revise os dados do tutor e de todos os pets antes de confirmar.', 'dps-registration-addon' ) . '</p>';
        echo '</div>';
        echo '<div id="dps-summary-content" class="dps-summary-content"></div>';
        echo '<label class="dps-summary-confirm"><input type="checkbox" id="dps-summary-confirm" name="dps_summary_confirm" value="1"> ' . esc_html__( 'Confirmo que os dados estão corretos', 'dps-registration-addon' ) . '</label>';
        echo '</div>';
        // Campos ocultos para coordenadas, preenchidos via script de autocomplete
        echo '<input type="hidden" name="client_lat" id="dps-client-lat" value="">';
        echo '<input type="hidden" name="client_lng" id="dps-client-lng" value="">';
        do_action( 'dps_registration_after_fields' );
        echo '<div class="dps-step-actions">';
        echo '<button type="button" id="dps-back-step" class="button dps-button-secondary">' . esc_html__( 'Voltar', 'dps-registration-addon' ) . '</button>';
        echo '<button type="submit" class="button button-primary" disabled>' . esc_html__( 'Enviar cadastro', 'dps-registration-addon' ) . '</button>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</form>';
        // CSS para melhorar a distribuição dos campos do formulário
        echo '<style>';
        echo '.dps-registration-form .dps-pet-fieldset, .dps-registration-form .dps-client-fields { display:flex; flex-wrap:wrap; gap:15px; }';
        echo '.dps-registration-form .dps-pet-fieldset p, .dps-registration-form .dps-client-fields p { flex:1 1 calc(50% - 15px); margin:0; }';
        echo '.dps-registration-form .dps-client-fields p[style*="100%"], .dps-registration-form .dps-client-fields textarea, .dps-registration-form .dps-pet-fieldset textarea { flex:1 1 100%; width:100%; }';
        echo '</style>';
        
        // F2.5: Template de pet para JS externo (via elemento script type="text/template")
        echo '<script type="text/template" id="dps-pet-template">' . $placeholder_json . '</script>';

        // Se houver uma API key do Google Maps configurada, inclui o script de Places
        // O callback dpsGoogleMapsReady é uma função global que aguarda o DPSRegistration estar disponível
        $api_key = get_option( 'dps_google_api_key', '' );
        if ( $api_key ) {
            echo '<script type="text/javascript">';
            echo 'function dpsGoogleMapsReady() {';
            echo '  if (typeof DPSRegistration !== "undefined" && DPSRegistration.initGooglePlaces) {';
            echo '    DPSRegistration.initGooglePlaces();';
            echo '  }';
            echo '}';
            echo '</script>';
            echo '<script src="https://maps.googleapis.com/maps/api/js?key=' . esc_attr( $api_key ) . '&libraries=places&callback=dpsGoogleMapsReady" async defer></script>';
        }
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Obtém URL do Portal do Cliente se configurado/ativo.
     *
     * @since 1.3.0
     *
     * @return string URL ou string vazia.
     */
    private function get_portal_url() {
        if ( function_exists( 'dps_get_portal_page_url' ) ) {
            $url = dps_get_portal_page_url();
            if ( $url ) {
                return $url;
            }
        }

        $portal_page_id = (int) get_option( 'dps_portal_page_id', 0 );
        if ( $portal_page_id ) {
            $url = get_permalink( $portal_page_id );
            if ( $url ) {
                return $url;
            }
        }

        return '';
    }

    /**
     * Retorna URL para CTA de agendamento.
     *
     * @since 1.3.0
     *
     * @return string URL resolvida ou vazia.
     */
    private function get_agenda_cta_url() {
        $agenda_page_id = (int) get_option( 'dps_agenda_page_id', 0 );
        if ( $agenda_page_id ) {
            $agenda_url = get_permalink( $agenda_page_id );
            if ( $agenda_url ) {
                return $agenda_url;
            }
        }

        $fallback = home_url( '/' );
        /**
         * Permite sobrescrever a URL de agendamento padrão.
         *
         * @since 1.3.0
         */
        $fallback = apply_filters( 'dps_registration_agenda_url', $fallback );

        return $fallback ? $fallback : '';
    }

    /**
     * Monta template de email de confirmação com placeholders resolvidos.
     *
     * @since 1.5.0
     *
     * @param int    $client_id          ID do cliente.
     * @param string $confirmation_link  URL de confirmação.
     * @return array
     */
    private function get_confirmation_email_template( $client_id, $confirmation_link ) {
        $client_name     = get_the_title( $client_id );
        $registration_url = $this->get_registration_page_url();
        $portal_url      = $this->get_portal_url();
        $business_name   = get_bloginfo( 'name' );

        // Escapar valores que serão inseridos em HTML para prevenir XSS
        $placeholders = array(
            '{client_name}'     => esc_html( $client_name ),
            '{confirm_url}'     => esc_url( $confirmation_link ),
            '{registration_url}' => esc_url( $registration_url ),
            '{portal_url}'      => $portal_url ? esc_url( $portal_url ) : '',
            '{business_name}'   => esc_html( $business_name ),
        );

        $subject_option = get_option( 'dps_registration_confirm_email_subject', '' );
        $body_option    = get_option( 'dps_registration_confirm_email_body', '' );

        // Subject não precisa de escape HTML pois é texto puro
        $subject_placeholders = array(
            '{client_name}'     => $client_name,
            '{business_name}'   => $business_name,
        );
        $subject = $subject_option
            ? $this->replace_placeholders( $subject_option, $subject_placeholders )
            : __( 'Confirme seu email - desi.pet by PRObst', 'desi-pet-shower' );

        if ( $body_option ) {
            $body = $this->replace_placeholders( $body_option, $placeholders );
        } else {
            $body_parts = array();
            $body_parts[] = '<p>' . esc_html__( 'Olá! Recebemos seu cadastro no desi.pet by PRObst. Para ativar sua conta, confirme seu email clicando no link abaixo:', 'desi-pet-shower' ) . '</p>';
            $body_parts[] = '<p><a href="' . esc_url( $confirmation_link ) . '">' . esc_html( $confirmation_link ) . '</a></p>';
            $body_parts[] = '<p>' . esc_html__( 'Este link é válido por 48 horas.', 'dps-registration-addon' ) . '</p>';
            $body_parts[] = '<p>' . esc_html__( 'Se você não fez este cadastro, ignore esta mensagem.', 'desi-pet-shower' ) . '</p>';

            if ( $portal_url ) {
                $body_parts[] = '<p>' . sprintf(
                    '%s <a href="%s">%s</a>',
                    esc_html__( 'Após confirmar, acesse o Portal por aqui:', 'dps-registration-addon' ),
                    esc_url( $portal_url ),
                    esc_html( $portal_url )
                ) . '</p>';
            }

            $body = implode( '', $body_parts );
        }

        return array(
            'subject' => $subject,
            'body'    => $body,
        );
    }

    /**
     * Substitui placeholders suportados no template.
     *
     * @since 1.5.0
     *
     * @param string $template Texto com placeholders.
     * @param array  $replacements Valores para substituir.
     * @return string
     */
    private function replace_placeholders( $template, $replacements ) {
        return strtr( $template, $replacements );
    }

    /**
     * Escapa caracteres especiais de LIKE (%, _) para prevenir wildcard injection.
     *
     * @since 1.2.3
     *
     * @param string $value Valor a escapar.
     * @return string Valor com wildcards escapados.
     */
    private function escape_like_wildcards( $value ) {
        return addcslashes( (string) $value, '%_' );
    }

    /**
     * Envia email com token de confirmação para o cliente.
     *
     * @param int    $client_id    ID do post do cliente.
     * @param string $client_email Email do cliente.
     *
     * @since 1.0.0
     * @since 1.1.0 Adicionado timestamp para expiração do token.
     */
    protected function send_confirmation_email( $client_id, $client_email ) {
        $token = wp_generate_uuid4();
        update_post_meta( $client_id, 'dps_email_confirm_token', $token );
        // F1.7/F3.4: Salva timestamp para expiração e lembrete (reutilizando se já existir)
        $created_at = get_post_meta( $client_id, 'dps_email_confirm_token_created', true );
        if ( empty( $created_at ) ) {
            $created_at = time();
            update_post_meta( $client_id, 'dps_email_confirm_token_created', $created_at );
        }

        if ( ! get_post_meta( $client_id, self::REMINDER_SENT_META, true ) ) {
            update_post_meta( $client_id, self::REMINDER_SENT_META, 0 );
        }

        $confirmation_link = add_query_arg( 'dps_confirm_email', $token, $this->get_registration_page_url() );
        $email_content = $this->get_confirmation_email_template( $client_id, $confirmation_link );
        $headers       = array( 'Content-Type: text/html; charset=UTF-8' );

        $sent = false;

        if ( class_exists( 'DPS_Communications_API' ) ) {
            $communications = DPS_Communications_API::get_instance();
            if ( $communications && method_exists( $communications, 'send_email' ) ) {
                $context = array( 'source' => 'registration', 'type' => 'confirmation' );
                $sent    = $communications->send_email( $client_email, $email_content['subject'], $email_content['body'], $context );
            }
        }

        if ( ! $sent ) {
            $sent = wp_mail( $client_email, $email_content['subject'], $email_content['body'], $headers );
        }

        if ( ! $sent ) {
            $this->log_event( 'warning', 'Falha ao enviar email de confirmação', array(
                'client_id'  => $client_id,
                'email_hash' => $this->get_safe_hash( $client_email ),
            ) );
        }
    }

    /**
     * Retorna a URL da página de cadastro configurada.
     *
     * @return string
     */
    protected function get_registration_page_url() {
        // 1. Tenta usar o ID salvo na option
        $page_id = (int) get_option( 'dps_registration_page_id' );
        if ( $page_id ) {
            $page = get_post( $page_id );
            // Verifica se a página existe e está publicada
            if ( $page && 'publish' === $page->post_status && 'page' === $page->post_type ) {
                $url = get_permalink( $page_id );
                if ( $url ) {
                    return $url;
                }
            }
        }

        // 2. Fallback: tenta encontrar a página pelo slug traduzido
        $default_slug = sanitize_title( __( 'Cadastro de Clientes e Pets', 'dps-registration-addon' ) );
        $page_by_slug = get_page_by_path( $default_slug );
        if ( $page_by_slug && 'publish' === $page_by_slug->post_status ) {
            // Verifica se contém o shortcode antes de salvar
            if ( has_shortcode( (string) $page_by_slug->post_content, 'dps_registration_form' ) ) {
                update_option( 'dps_registration_page_id', $page_by_slug->ID );
                $url = get_permalink( $page_by_slug->ID );
                if ( $url ) {
                    return $url;
                }
            }
        }

        // 2b. Fallback: tenta com slug em português fixo (caso locale seja diferente)
        if ( 'cadastro-de-clientes-e-pets' !== $default_slug ) {
            $page_by_fixed_slug = get_page_by_path( 'cadastro-de-clientes-e-pets' );
            if ( $page_by_fixed_slug && 'publish' === $page_by_fixed_slug->post_status ) {
                if ( has_shortcode( (string) $page_by_fixed_slug->post_content, 'dps_registration_form' ) ) {
                    update_option( 'dps_registration_page_id', $page_by_fixed_slug->ID );
                    $url = get_permalink( $page_by_fixed_slug->ID );
                    if ( $url ) {
                        return $url;
                    }
                }
            }
        }

        // 3. Fallback: busca qualquer página com o shortcode [dps_registration_form]
        global $wpdb;
        $found_page_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s AND post_content LIKE %s LIMIT 1",
                'page',
                'publish',
                '%[dps_registration_form%'
            )
        );
        if ( $found_page_id ) {
            $found_page_id = (int) $found_page_id;
            update_option( 'dps_registration_page_id', $found_page_id );
            $url = get_permalink( $found_page_id );
            if ( $url ) {
                return $url;
            }
        }

        return home_url( '/' );
    }

    /**
     * Agenda evento de cron para lembretes de confirmação de email.
     *
     * @since 1.4.0
     */
    public function maybe_schedule_confirmation_reminders() {
        if ( ! wp_next_scheduled( self::CONFIRMATION_REMINDER_CRON ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::CONFIRMATION_REMINDER_CRON );
        }
    }

    /**
     * Envia lembretes de confirmação para cadastros não confirmados após 24h.
     *
     * @since 1.4.0
     */
    public function send_confirmation_reminders() {
        if ( ! class_exists( 'DPS_Communications_API' ) ) {
            $this->log_event( 'info', 'Communications inativo - lembretes não enviados' );
            return;
        }

        $communications = DPS_Communications_API::get_instance();
        if ( ! $communications ) {
            $this->log_event( 'warning', 'Communications API indisponível - abortando lembretes' );
            return;
        }

        $offset     = 0;
        $batch_size = 50;
        $now        = time();
        $oldest     = $now - self::TOKEN_EXPIRATION_SECONDS;
        $newest     = $now - DAY_IN_SECONDS;

        do {
            $pending_clients = new WP_Query( [
                'post_type'              => 'dps_cliente',
                'post_status'            => 'publish',
                'posts_per_page'         => $batch_size,
                'offset'                 => $offset,
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'update_post_term_cache' => false,
                'update_post_meta_cache' => false,
                'meta_query'             => [
                    'relation' => 'AND',
                    [
                        'key'     => 'dps_email_confirmed',
                        'value'   => 0,
                        'compare' => '=',
                        'type'    => 'NUMERIC',
                    ],
                    [
                        'key'     => 'dps_email_confirm_token',
                        'compare' => 'EXISTS',
                    ],
                    [
                        'key'     => 'dps_email_confirm_token_created',
                        'value'   => [ $oldest, $newest ],
                        'compare' => 'BETWEEN',
                        'type'    => 'NUMERIC',
                    ],
                    [
                        'relation' => 'OR',
                        [
                            'key'     => self::REMINDER_SENT_META,
                            'compare' => 'NOT EXISTS',
                        ],
                        [
                            'key'     => self::REMINDER_SENT_META,
                            'value'   => 0,
                            'compare' => '=',
                            'type'    => 'NUMERIC',
                        ],
                    ],
                ],
            ] );

            $client_ids = $pending_clients->posts;
            if ( empty( $client_ids ) ) {
                break;
            }

            foreach ( $client_ids as $client_id ) {
                $this->send_confirmation_reminder_to_client( $client_id, $communications );
            }

            $offset += $batch_size;
        } while ( count( $client_ids ) === $batch_size );
    }

    /**
     * Envia o lembrete para um cliente específico.
     *
     * @since 1.4.0
     *
     * @param int                    $client_id      ID do cliente.
     * @param DPS_Communications_API $communications Instância da API de comunicações.
     */
    private function send_confirmation_reminder_to_client( $client_id, $communications ) {
        $token        = get_post_meta( $client_id, 'dps_email_confirm_token', true );
        $created_at   = (int) get_post_meta( $client_id, 'dps_email_confirm_token_created', true );
        $reminder_log = [
            'client_id' => $client_id,
            'token_set' => ! empty( $token ),
        ];

        if ( empty( $token ) || empty( $created_at ) ) {
            $this->log_event( 'warning', 'Lembrete ignorado por dados faltando', $reminder_log );
            return;
        }

        $client_email = get_post_meta( $client_id, 'client_email', true );
        $client_phone = get_post_meta( $client_id, 'client_phone', true );
        $client_name  = get_the_title( $client_id );
        $confirm_url  = add_query_arg( 'dps_confirm_email', $token, $this->get_registration_page_url() );

        $greeting = $client_name
            ? sprintf( __( 'Olá %s!', 'dps-registration-addon' ), $client_name )
            : __( 'Olá!', 'dps-registration-addon' );

        $message = sprintf(
            "%s %s %s\n%s",
            $greeting,
            __( 'Lembrete: confirme seu cadastro no desi.pet by PRObst para ativar sua conta.', 'dps-registration-addon' ),
            __( 'Use o link abaixo para finalizar a confirmação.', 'dps-registration-addon' ),
            esc_url_raw( $confirm_url )
        );

        $sent = false;
        $context = [
            'source'       => 'registration',
            'type'         => 'confirmation_reminder',
            'client_id'    => $client_id,
            'token_hash'   => $this->get_safe_hash( $token ),
            'token_age'    => time() - $created_at,
        ];

        if ( ! empty( $client_phone ) && method_exists( $communications, 'send_whatsapp' ) ) {
            $sent = $communications->send_whatsapp( $client_phone, $message, $context ) || $sent;
        }

        if ( ! empty( $client_email ) && is_email( $client_email ) && method_exists( $communications, 'send_email' ) ) {
            $email_message = $message . "\n\n" . __( 'Se você já confirmou, pode ignorar este lembrete.', 'dps-registration-addon' );
            $sent          = $communications->send_email(
                $client_email,
                __( 'Lembrete: confirme seu cadastro', 'dps-registration-addon' ),
                $email_message,
                $context
            ) || $sent;
        }

        if ( $sent ) {
            update_post_meta( $client_id, self::REMINDER_SENT_META, time() );
            $this->log_event( 'info', 'Lembrete de confirmação enviado', $context );
        } else {
            $this->log_event( 'warning', 'Nenhum canal disponível para lembrete', $context );
        }
    }

    /**
     * Gera o HTML de um conjunto de campos para um pet específico.
     *
     * @param int $index Índice do pet (1, 2, ...)
     * @return string HTML
     */
    public function get_pet_fieldset_html( $index ) {
        $i = intval( $index );
        $breed_options = $this->get_breed_options_for_species( '' );
        $datalist_id   = 'dps-breed-list-' . $i;
        ob_start();
        echo '<fieldset class="dps-pet-fieldset" style="border:1px solid #ddd; padding:10px; margin-bottom:10px;">';
        echo '<legend>' . sprintf( __( 'Pet %d', 'dps-registration-addon' ), $i ) . '</legend>';
        // Nome do pet
        echo '<p><label>' . esc_html__( 'Nome do Pet', 'dps-registration-addon' ) . '<br><input type="text" name="pet_name[]" class="dps-pet-name"></label></p>';
        // Nome do cliente (readonly)
        echo '<p><label>' . esc_html__( 'Cliente', 'dps-registration-addon' ) . '<br><input type="text" class="dps-owner-name" readonly></label></p>';
        // Espécie
        echo '<p><label>' . esc_html__( 'Espécie', 'dps-registration-addon' ) . '<br><select name="pet_species[]" required>';
        $species_opts = [ '' => __( 'Selecione...', 'dps-registration-addon' ), 'cao' => __( 'Cachorro', 'dps-registration-addon' ), 'gato' => __( 'Gato', 'dps-registration-addon' ), 'outro' => __( 'Outro', 'dps-registration-addon' ) ];
        foreach ( $species_opts as $val => $label ) {
            echo '<option value="' . esc_attr( $val ) . '">' . esc_html( $label ) . '</option>';
        }
        echo '</select></label></p>';
        // Raça com datalist
        echo '<p><label>' . esc_html__( 'Raça', 'dps-registration-addon' ) . '<br><input type="text" name="pet_breed[]" list="' . esc_attr( $datalist_id ) . '"></label></p>';
        echo '<datalist id="' . esc_attr( $datalist_id ) . '">';
        foreach ( $breed_options as $breed ) {
            echo '<option value="' . esc_attr( $breed ) . '"></option>';
        }
        echo '</datalist>';
        // Porte
        echo '<p><label>' . esc_html__( 'Porte', 'dps-registration-addon' ) . '<br><select name="pet_size[]" required>';
        $sizes = [ '' => __( 'Selecione...', 'dps-registration-addon' ), 'pequeno' => __( 'Pequeno', 'dps-registration-addon' ), 'medio' => __( 'Médio', 'dps-registration-addon' ), 'grande' => __( 'Grande', 'dps-registration-addon' ) ];
        foreach ( $sizes as $val => $lab ) {
            echo '<option value="' . esc_attr( $val ) . '">' . esc_html( $lab ) . '</option>';
        }
        echo '</select></label></p>';
        // Peso
        echo '<p><label>' . esc_html__( 'Peso (kg)', 'dps-registration-addon' ) . '<br><input type="number" step="0.01" name="pet_weight[]"></label></p>';
        // Pelagem
        echo '<p><label>' . esc_html__( 'Pelagem', 'dps-registration-addon' ) . '<br><input type="text" name="pet_coat[]"></label></p>';
        // Cor
        echo '<p><label>' . esc_html__( 'Cor', 'dps-registration-addon' ) . '<br><input type="text" name="pet_color[]"></label></p>';
        // Data de nascimento
        echo '<p><label>' . esc_html__( 'Data de nascimento', 'dps-registration-addon' ) . '<br><input type="date" name="pet_birth[]"></label></p>';
        // Sexo
        echo '<p><label>' . esc_html__( 'Sexo', 'dps-registration-addon' ) . '<br><select name="pet_sex[]" required>';
        $sexes = [ '' => __( 'Selecione...', 'dps-registration-addon' ), 'macho' => __( 'Macho', 'dps-registration-addon' ), 'femea' => __( 'Fêmea', 'dps-registration-addon' ) ];
        foreach ( $sexes as $val => $lab ) {
            echo '<option value="' . esc_attr( $val ) . '">' . esc_html( $lab ) . '</option>';
        }
        echo '</select></label></p>';
        // Cuidados especiais
        echo '<p><label>' . esc_html__( 'Algum cuidado especial ou restrição?', 'dps-registration-addon' ) . '<br><textarea name="pet_care[]" rows="2"></textarea></label></p>';
        // Agressivo
        echo '<p><label><input type="checkbox" name="pet_aggressive[' . ( $i - 1 ) . ']" value="1"> ' . esc_html__( 'Cão agressivo', 'dps-registration-addon' ) . '</label></p>';
        echo '</fieldset>';
        return ob_get_clean();
    }

    /**
     * Retorna um conjunto de campos de pet com marcadores de substituição de índice. Usado para clonagem via JS.
     * O texto '__INDEX__' será substituído por JS com o número real do pet.
     *
     * @return string
     */
    public function get_pet_fieldset_html_placeholder() {
        ob_start();
        echo '<fieldset class="dps-pet-fieldset" style="border:1px solid #ddd; padding:10px; margin-bottom:10px;">';
        echo '<legend>' . __( 'Pet __INDEX__', 'dps-registration-addon' ) . '</legend>';
        echo '<p><label>' . esc_html__( 'Nome do Pet', 'dps-registration-addon' ) . '<br><input type="text" name="pet_name[]" class="dps-pet-name"></label></p>';
        echo '<p><label>' . esc_html__( 'Cliente', 'dps-registration-addon' ) . '<br><input type="text" class="dps-owner-name" readonly></label></p>';
        // Espécie
        echo '<p><label>' . esc_html__( 'Espécie', 'dps-registration-addon' ) . '<br><select name="pet_species[]" required>';
        $species_opts = [ '' => __( 'Selecione...', 'dps-registration-addon' ), 'cao' => __( 'Cachorro', 'dps-registration-addon' ), 'gato' => __( 'Gato', 'dps-registration-addon' ), 'outro' => __( 'Outro', 'dps-registration-addon' ) ];
        foreach ( $species_opts as $val => $label ) {
            echo '<option value="' . esc_attr( $val ) . '">' . esc_html( $label ) . '</option>';
        }
        echo '</select></label></p>';
        // Raça
        echo '<p><label>' . esc_html__( 'Raça', 'dps-registration-addon' ) . '<br><input type="text" name="pet_breed[]" list="dps-breed-list-__INDEX__"></label></p>';
        echo '<datalist id="dps-breed-list-__INDEX__">';
        $breed_options = $this->get_breed_options_for_species( '' );
        foreach ( $breed_options as $breed ) {
            echo '<option value="' . esc_attr( $breed ) . '"></option>';
        }
        echo '</datalist>';
        // Porte
        echo '<p><label>' . esc_html__( 'Porte', 'dps-registration-addon' ) . '<br><select name="pet_size[]" required>';
        $sizes = [ '' => __( 'Selecione...', 'dps-registration-addon' ), 'pequeno' => __( 'Pequeno', 'dps-registration-addon' ), 'medio' => __( 'Médio', 'dps-registration-addon' ), 'grande' => __( 'Grande', 'dps-registration-addon' ) ];
        foreach ( $sizes as $val => $lab ) {
            echo '<option value="' . esc_attr( $val ) . '">' . esc_html( $lab ) . '</option>';
        }
        echo '</select></label></p>';
        // Peso
        echo '<p><label>' . esc_html__( 'Peso (kg)', 'dps-registration-addon' ) . '<br><input type="number" step="0.01" name="pet_weight[]"></label></p>';
        // Pelagem
        echo '<p><label>' . esc_html__( 'Pelagem', 'dps-registration-addon' ) . '<br><input type="text" name="pet_coat[]"></label></p>';
        // Cor
        echo '<p><label>' . esc_html__( 'Cor', 'dps-registration-addon' ) . '<br><input type="text" name="pet_color[]"></label></p>';
        // Data de nascimento
        echo '<p><label>' . esc_html__( 'Data de nascimento', 'dps-registration-addon' ) . '<br><input type="date" name="pet_birth[]"></label></p>';
        // Sexo
        echo '<p><label>' . esc_html__( 'Sexo', 'dps-registration-addon' ) . '<br><select name="pet_sex[]" required>';
        $sexes = [ '' => __( 'Selecione...', 'dps-registration-addon' ), 'macho' => __( 'Macho', 'dps-registration-addon' ), 'femea' => __( 'Fêmea', 'dps-registration-addon' ) ];
        foreach ( $sexes as $val => $lab ) {
            echo '<option value="' . esc_attr( $val ) . '">' . esc_html( $lab ) . '</option>';
        }
        echo '</select></label></p>';
        // Cuidados especiais
        echo '<p><label>' . esc_html__( 'Algum cuidado especial ou restrição?', 'dps-registration-addon' ) . '<br><textarea name="pet_care[]" rows="2"></textarea></label></p>';
        // Agressivo
        echo '<p><label><input type="checkbox" name="pet_aggressive[__INDEX__]" value="1"> ' . esc_html__( 'Cão agressivo', 'dps-registration-addon' ) . '</label></p>';
        echo '</fieldset>';
        return ob_get_clean();
    }
}

/**
 * Inicializa o Registration Add-on após o hook 'init' para garantir que o text domain seja carregado primeiro.
 * Usa prioridade 5 para rodar após o carregamento do text domain (prioridade 1) mas antes
 * de outros registros (prioridade 10).
 */
function dps_registration_init_addon() {
    if ( class_exists( 'DPS_Registration_Addon' ) ) {
        DPS_Registration_Addon::get_instance();
    }
}
add_action( 'init', 'dps_registration_init_addon', 5 );
