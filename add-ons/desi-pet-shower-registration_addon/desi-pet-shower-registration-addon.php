<?php
/**
 * Plugin Name:       DPS by PRObst – Cadastro Add-on
 * Plugin URI:        https://www.probst.pro
 * Description:       Página pública de cadastro para clientes e pets. Envie o link e deixe o cliente preencher seus dados.
 * Version:           1.2.0
 * Author:            PRObst
 * Author URI:        https://www.probst.pro
 * Text Domain:       dps-registration-addon
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * License:           GPL-2.0+
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Verifica se o plugin base DPS by PRObst está ativo.
 * Se não estiver, exibe aviso e interrompe carregamento do add-on.
 */
function dps_registration_check_base_plugin() {
    if ( ! class_exists( 'DPS_Base_Plugin' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__( 'O add-on Cadastro requer o plugin base DPS by PRObst para funcionar.', 'dps-registration-addon' );
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

        // Enfileira assets CSS para responsividade
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        // Adiciona página de configurações para API do Google Maps
        add_action( 'admin_menu', [ $this, 'add_settings_page' ], 20 );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
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
        $post_content = $current_post ? $current_post->post_content : '';
        
        if ( ! is_page( $registration_page_id ) && ! has_shortcode( $post_content, 'dps_registration_form' ) ) {
            return;
        }

        $addon_url = plugin_dir_url( __FILE__ );
        $version   = '1.2.0';

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
     * Adiciona a página de configurações no menu principal "DPS by PRObst"
     * 
     * NOTA: A partir da v1.1.0, este menu está oculto (parent=null) para backward compatibility.
     * Use o novo hub unificado em dps-tools-hub para acessar via aba "Formulário de Cadastro".
     */
    public function add_settings_page() {
        add_submenu_page(
            null, // Oculto do menu, acessível apenas por URL direta
            __( 'Formulário de Cadastro', 'dps-registration-addon' ),
            __( 'Formulário de Cadastro', 'dps-registration-addon' ),
            'manage_options',
            'dps-registration-settings',
            [ $this, 'render_settings_page' ]
        );
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
    }

    /**
     * Renderiza o conteúdo da página de configurações
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Configurações de Cadastro DPS by PRObst', 'dps-registration-addon' ) . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields( 'dps_registration_settings' );
        do_settings_sections( 'dps_registration_settings' );
        $api_key = get_option( 'dps_google_api_key', '' );
        echo '<table class="form-table">';
        echo '<tr><th scope="row"><label for="dps_google_api_key">' . esc_html__( 'Google Maps API Key', 'dps-registration-addon' ) . '</label></th>';
        echo '<td><input type="text" id="dps_google_api_key" name="dps_google_api_key" value="' . esc_attr( $api_key ) . '" class="regular-text"></td></tr>';
        echo '</table>';
        submit_button();
        echo '</form>';
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
            $this->add_error( __( 'Muitas tentativas de cadastro. Por favor, aguarde alguns minutos antes de tentar novamente.', 'dps-registration-addon' ) );
            $this->redirect_with_error();
        }

        // F1.8: Hook para validações adicionais (ex.: reCAPTCHA)
        $spam_check = apply_filters( 'dps_registration_spam_check', true, $_POST );
        if ( true !== $spam_check ) {
            $this->add_error( __( 'Verificação de segurança falhou. Por favor, tente novamente.', 'dps-registration-addon' ) );
            $this->redirect_with_error();
        }

        // Sanitiza dados do cliente
        $client_name     = sanitize_text_field( $_POST['client_name'] ?? '' );
        $client_cpf_raw  = sanitize_text_field( $_POST['client_cpf'] ?? '' );
        $client_phone_raw = sanitize_text_field( $_POST['client_phone'] ?? '' );
        $client_email    = sanitize_email( $_POST['client_email'] ?? '' );
        $client_birth    = sanitize_text_field( $_POST['client_birth'] ?? '' );
        $client_instagram = sanitize_text_field( $_POST['client_instagram'] ?? '' );
        $client_facebook = sanitize_text_field( $_POST['client_facebook'] ?? '' );
        $client_photo_auth = isset( $_POST['client_photo_auth'] ) ? 1 : 0;
        $client_address  = sanitize_textarea_field( $_POST['client_address'] ?? '' );
        $client_referral = sanitize_text_field( $_POST['client_referral'] ?? '' );
        $referral_code   = sanitize_text_field( $_POST['dps_referral_code'] ?? '' );

        // Coordenadas de latitude e longitude (podem estar vazias)
        $client_lat  = sanitize_text_field( $_POST['client_lat'] ?? '' );
        $client_lng  = sanitize_text_field( $_POST['client_lng'] ?? '' );

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

        // Salva coordenadas se fornecidas
        if ( $client_lat !== '' && $client_lng !== '' ) {
            update_post_meta( $client_id, 'client_lat', $client_lat );
            update_post_meta( $client_id, 'client_lng', $client_lng );
        }

        if ( $client_email ) {
            $this->send_confirmation_email( $client_id, $client_email );
        }

        do_action( 'dps_registration_after_client_created', $referral_code, $client_id, $client_email, $client_phone );

        // Lê pets submetidos (campos em arrays)
        $pet_names      = $_POST['pet_name'] ?? [];
        $pet_species    = $_POST['pet_species'] ?? [];
        $pet_breeds     = $_POST['pet_breed'] ?? [];
        $pet_sizes      = $_POST['pet_size'] ?? [];
        $pet_weights    = $_POST['pet_weight'] ?? [];
        $pet_coats      = $_POST['pet_coat'] ?? [];
        $pet_colors     = $_POST['pet_color'] ?? [];
        $pet_births     = $_POST['pet_birth'] ?? [];
        $pet_sexes      = $_POST['pet_sex'] ?? [];
        $pet_cares      = $_POST['pet_care'] ?? [];
        $pet_aggs       = $_POST['pet_aggressive'] ?? [];

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
                // Cria pet
                $pet_id = wp_insert_post( [
                    'post_type'   => 'dps_pet',
                    'post_title'  => $pname,
                    'post_status' => 'publish',
                ] );
                if ( $pet_id ) {
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

        // Redireciona e indica sucesso
        wp_redirect( add_query_arg( 'registered', '1', $this->get_registration_page_url() ) );
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

        $redirect = add_query_arg( 'dps_email_confirmed', '1', $this->get_registration_page_url() );
        wp_safe_redirect( $redirect );
        exit;
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
        echo '<div class="dps-hp-field" aria-hidden="true" style="position:absolute; left:-9999px; width:1px; height:1px; overflow:hidden;">';
        echo '<label for="dps_hp_field">' . esc_html__( 'Deixe este campo vazio', 'desi-pet-shower' ) . '</label>';
        echo '<input type="text" name="dps_hp_field" id="dps_hp_field" tabindex="-1" autocomplete="off">';
        echo '</div>';
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
        echo '<p><label><input type="checkbox" name="client_photo_auth" value="1"> ' . esc_html__( 'Autorizo publicação da foto do pet nas redes sociais do DPS by PRObst', 'dps-registration-addon' ) . '</label></p>';
        // Endereço completo com id específico para ativar autocomplete do Google
        echo '<p style="flex:1 1 100%;"><label>' . esc_html__( 'Endereço completo', 'dps-registration-addon' ) . '<br><textarea name="client_address" id="dps-client-address" rows="2"></textarea></label></p>';
        echo '<p style="flex:1 1 100%;"><label>' . esc_html__( 'Como nos conheceu?', 'dps-registration-addon' ) . '<br><input type="text" name="client_referral"></label></p>';
        echo '</div>';
        echo '<h4>' . esc_html__( 'Dados dos Pets', 'dps-registration-addon' ) . '</h4>';
        echo '<div id="dps-pets-wrapper">';
        // Insere o primeiro conjunto de campos de pet
        echo $first_pet_html;
        echo '</div>';
        // Botão para adicionar outro pet
        echo '<p><button type="button" id="dps-add-pet" class="button">' . esc_html__( 'Adicionar outro pet', 'dps-registration-addon' ) . '</button></p>';
        // Botão de envio
        // Campos ocultos para coordenadas, preenchidos via script de autocomplete
        echo '<input type="hidden" name="client_lat" id="dps-client-lat" value="">';
        echo '<input type="hidden" name="client_lng" id="dps-client-lng" value="">';
        do_action( 'dps_registration_after_fields' );
        echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Enviar cadastro', 'dps-registration-addon' ) . '</button></p>';
        echo '</form>';
        // Lista de raças
        echo '<datalist id="dps-breed-list">';
        $breed_list = [
            // Cães
            'Affenpinscher',
            'Airedale Terrier',
            'Akita',
            'Basset Hound',
            'Beagle',
            'Bernese Mountain Dog (Boiadeiro Bernês)',
            'Bichon Frisé',
            'Bichon Havanês',
            'Bloodhound',
            'Boiadeiro Australiano',
            'Border Collie',
            'Borzói',
            'Boston Terrier',
            'Boxer',
            'Bulldog Americano',
            'Bulldog Francês',
            'Bulldog Inglês',
            'Bulldog Campeiro',
            'Bull Terrier',
            'Bullmastiff',
            'Cairn Terrier',
            'Cane Corso',
            'Cão Afegão',
            'Cão de Água Português',
            'Cão de Crista Chinês',
            'Cão de Pator Alemão (Pastor Alemão)',
            'Cão de Pastor Shetland',
            'Cavalier King Charles Spaniel',
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
            'Galgo Inglês',
            'Golden Retriever',
            'Husky Siberiano',
            'Jack Russell Terrier',
            'Labradoodle',
            'Labrador Retriever',
            'Lhasa Apso',
            'Lulu da Pomerânia (Spitz Alemão)',
            'Malamute do Alasca',
            'Maltês',
            'Papillon',
            'Pastor Australiano',
            'Pastor Belga Malinois',
            'Pastor de Shetland',
            'Pequinês',
            'Pinscher',
            'Pinscher Miniatura',
            'Pit Bull Terrier',
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
            'Staffordshire Bull Terrier',
            'Terra-Nova',
            'Vira-lata',
            'SRD (Sem Raça Definida)',
            'Weimaraner',
            'Whippet',
            'Yorkshire Terrier',
            // Gatos
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
            'Maine Coon',
            'Munchkin',
            'Oriental de Pelo Curto',
            'Persa',
            'Ragdoll',
            'Sagrado da Birmânia',
            'Savannah',
            'Scottish Fold',
            'Selkirk Rex',
            'Siamês',
            'Somali',
            'Sphynx',
            'Tonquinês'
        ];
        foreach ( $breed_list as $br ) {
            echo '<option value="' . esc_attr( $br ) . '"></option>';
        }
        echo '</datalist>';
        
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
        // F1.7: Salva timestamp para expiração
        update_post_meta( $client_id, 'dps_email_confirm_token_created', time() );

        $confirmation_link = add_query_arg( 'dps_confirm_email', $token, $this->get_registration_page_url() );

        $subject = __( 'Confirme seu email - DPS by PRObst', 'desi-pet-shower' );
        $message = sprintf(
            "%s\n\n%s\n\n%s\n\n%s",
            __( 'Olá! Recebemos seu cadastro no DPS by PRObst. Para ativar sua conta, confirme seu email clicando no link abaixo:', 'desi-pet-shower' ),
            esc_url_raw( $confirmation_link ),
            __( 'Este link é válido por 48 horas.', 'dps-registration-addon' ),
            __( 'Se você não fez este cadastro, ignore esta mensagem.', 'desi-pet-shower' )
        );

        wp_mail( $client_email, $subject, $message );
    }

    /**
     * Retorna a URL da página de cadastro configurada.
     *
     * @return string
     */
    protected function get_registration_page_url() {
        $page_id = (int) get_option( 'dps_registration_page_id' );
        if ( $page_id ) {
            $url = get_permalink( $page_id );
            if ( $url ) {
                return $url;
            }
        }

        return home_url( '/' );
    }

    /**
     * Gera o HTML de um conjunto de campos para um pet específico.
     *
     * @param int $index Índice do pet (1, 2, ...)
     * @return string HTML
     */
    public function get_pet_fieldset_html( $index ) {
        $i = intval( $index );
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
        echo '<p><label>' . esc_html__( 'Raça', 'dps-registration-addon' ) . '<br><input type="text" name="pet_breed[]" list="dps-breed-list"></label></p>';
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
        echo '<p><label>' . esc_html__( 'Raça', 'dps-registration-addon' ) . '<br><input type="text" name="pet_breed[]" list="dps-breed-list"></label></p>';
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