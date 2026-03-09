<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Carrega a classe somente se ainda nÃ£o existir.
if ( ! class_exists( 'DPS_Client_Portal' ) ) :

/**
 * Classe responsÃ¡vel por fornecer o portal do cliente.  Implementa:
 * - CriaÃ§Ã£o automÃ¡tica de usuÃ¡rio WordPress ao cadastrar cliente.
 * - Shortcode para renderizar a Ã¡rea do cliente com histÃ³rico, fotos, pendÃªncias e formulÃ¡rios.
 * - GeraÃ§Ã£o de links de pagamento para pendÃªncias usando a API do MercadoÂ Pago.
 * - AtualizaÃ§Ã£o de dados do cliente e dos pets a partir do portal.
 */
final class DPS_Client_Portal {

    /**
     * Status que indicam agendamento finalizado ou cancelado.
     * Usado para separar prÃ³ximos agendamentos do histÃ³rico.
     *
     * @since 3.1.0
     * @var array
     */
    private const COMPLETED_STATUSES = [
        'finalizado',
        'finalizado e pago',
        'finalizado_pago',
        'cancelado',
    ];

    /**
     * Ãšnica instÃ¢ncia da classe.
     *
     * @var DPS_Client_Portal|null
     */
    private static $instance = null;

    /**
     * ID do cliente autenticado na requisiÃ§Ã£o atual via token.
     * Usado para disponibilizar autenticaÃ§Ã£o imediatamente sem depender de cookies.
     *
     * @since 2.3.1
     * @var int
     */
    private $current_request_client_id = 0;

    /**
     * Chave da sessÃ£o pendente de 2FA (quando 2FA estÃ¡ ativo).
     *
     * @since 3.2.0
     * @var string
     */
    private $pending_2fa_session_key = '';

    /**
     * ID do cliente com 2FA pendente.
     *
     * @since 3.2.0
     * @var int
     */
    private $pending_2fa_client_id = 0;

    /**
     * Recupera a instÃ¢ncia Ãºnica (singleton).
     *
     * @return DPS_Client_Portal
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor. Registra ganchos necessÃ¡rios para o funcionamento do portal.
     */
    private function __construct() {
        // Processa autenticacao do portal.
        if ( did_action( 'init' ) ) {
            $this->handle_token_authentication();
            $this->handle_remember_cookie();
            $this->restore_portal_session_from_wordpress_user();
            $this->handle_password_login_request();
            $this->handle_password_reset_request();
            $this->handle_logout_request();
            $this->handle_portal_actions();
            $this->handle_portal_settings_save();
        } else {
            add_action( 'init', [ $this, 'handle_token_authentication' ], 5 );
            add_action( 'init', [ $this, 'handle_remember_cookie' ], 5 );
            add_action( 'init', [ $this, 'restore_portal_session_from_wordpress_user' ], 6 );
            add_action( 'init', [ $this, 'handle_password_login_request' ], 6 );
            add_action( 'init', [ $this, 'handle_password_reset_request' ], 6 );
            add_action( 'init', [ $this, 'handle_logout_request' ], 7 );
            add_action( 'init', [ $this, 'handle_portal_actions' ] );
            add_action( 'init', [ $this, 'handle_portal_settings_save' ] );
        }

        // Mantem o usuario WordPress do cliente sincronizado com o e-mail cadastrado.
        add_action( 'save_post_dps_cliente', [ $this, 'maybe_create_login_for_client' ], 10, 3 );
        add_action( 'wp_login', [ $this, 'handle_wordpress_user_login' ], 10, 2 );
        add_action( 'admin_init', [ $this, 'redirect_portal_clients_from_admin' ], 5 );
        add_filter( 'show_admin_bar', [ $this, 'filter_portal_client_admin_bar' ], 20 );
        add_filter( 'login_redirect', [ $this, 'filter_portal_login_redirect' ], 10, 3 );

        // Shortcodes do portal.
        add_shortcode( 'dps_client_portal', [ $this, 'render_portal_shortcode' ] );
        add_shortcode( 'dps_client_login', [ $this, 'render_login_shortcode' ] );

        // Assets do frontend.
        add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
    }

    /**
     * Processa autenticaÃ§Ã£o por token na URL
     */
    public function handle_token_authentication() {
        // Verifica se hÃ¡ um token na URL
        if ( ! isset( $_GET['dps_token'] ) ) {
            return;
        }

        $token_plain = sanitize_text_field( wp_unslash( $_GET['dps_token'] ) );
        
        if ( empty( $token_plain ) ) {
            return;
        }

        // ObtÃ©m IP para logging
        $ip_address = $this->get_client_ip();

        // Valida o token
        $token_manager = DPS_Portal_Token_Manager::get_instance();
        $token_data    = $token_manager->validate_token( $token_plain );

        if ( false === $token_data ) {
            // Token invÃ¡lido - registra tentativa e redireciona
            $this->log_security_event( 'token_invalid', [
                'ip' => $ip_address,
            ] );
            // F6.3: FASE 6 - Audit log de tentativa falhada
            if ( class_exists( 'DPS_Audit_Logger' ) ) {
                DPS_Audit_Logger::log_portal_event( 'token_validation_failed', 0, [ 'ip' => $ip_address ] );
            }
            $this->redirect_to_access_screen( 'invalid' );
            return;
        }

        // F6.4: FASE 6 - 2FA via e-mail
        // Se 2FA habilitado, interrompe fluxo normal e exige verificaÃ§Ã£o por cÃ³digo
        $twofa = DPS_Portal_2FA::get_instance();
        if ( $twofa->is_enabled() ) {
            // Marca token como usado para evitar reutilizaÃ§Ã£o
            if ( ! isset( $token_data['type'] ) || 'permanent' !== $token_data['type'] ) {
                $token_manager->mark_as_used( $token_data['id'] );
            }

            // Gera cÃ³digo e envia por e-mail
            $code = $twofa->generate_code( $token_data['client_id'] );
            $twofa->send_code_email( $token_data['client_id'], $code );

            // Cria sessÃ£o pendente (2FA nÃ£o concluÃ­do)
            $session_key = wp_generate_password( 32, false );
            $twofa->set_pending_2fa( $token_data['client_id'], $session_key );

            // Armazena remember flag para aplicar apÃ³s 2FA
            if ( isset( $_GET['dps_remember'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['dps_remember'] ) ) ) {
                set_transient( 'dps_2fa_remember_' . $session_key, '1', 600 );
            }

            // Sinaliza 2FA pendente para o shortcode renderizar o formulÃ¡rio
            $this->pending_2fa_session_key = $session_key;
            $this->pending_2fa_client_id   = $token_data['client_id'];

            // Log
            if ( class_exists( 'DPS_Audit_Logger' ) ) {
                DPS_Audit_Logger::log_portal_event( '2fa_code_sent', $token_data['client_id'], [ 'ip' => $ip_address ] );
            }

            return; // Interrompe â€” shortcode exibirÃ¡ formulÃ¡rio 2FA
        }

        // Token vÃ¡lido - autentica o cliente
        $session_manager = DPS_Portal_Session_Manager::get_instance();
        $authenticated   = $session_manager->authenticate_client( $token_data['client_id'] );

        if ( ! $authenticated ) {
            $this->log_security_event( 'session_auth_failed', [
                'client_id' => $token_data['client_id'],
                'ip'        => $ip_address,
            ] );
            $this->redirect_to_access_screen( 'invalid' );
            return;
        }

        // Marca token como usado apenas para tokens temporÃ¡rios
        if ( ! isset( $token_data['type'] ) || 'permanent' !== $token_data['type'] ) {
            $token_manager->mark_as_used( $token_data['id'] );
        }

        // F4.6: FASE 4 - "Manter acesso neste dispositivo"
        // Se dps_remember=1 na URL, cria token permanente e cookie de longa duraÃ§Ã£o
        $remember = isset( $_GET['dps_remember'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['dps_remember'] ) );
        if ( $remember ) {
            $permanent_token = $token_manager->generate_token( $token_data['client_id'], 'permanent' );
            if ( false !== $permanent_token ) {
                $cookie_expiry = time() + ( 90 * DAY_IN_SECONDS );
                setcookie(
                    'dps_portal_remember',
                    $permanent_token,
                    $cookie_expiry,
                    COOKIEPATH,
                    COOKIE_DOMAIN,
                    is_ssl(),
                    true // HttpOnly
                );
                // SameSite=Strict via header
                if ( ! headers_sent() ) {
                    header(
                        sprintf(
                            'Set-Cookie: dps_portal_remember=%s; Expires=%s; Path=%s; Domain=%s%s; HttpOnly; SameSite=Strict',
                            $permanent_token,
                            gmdate( 'D, d M Y H:i:s T', $cookie_expiry ),
                            COOKIEPATH,
                            COOKIE_DOMAIN,
                            is_ssl() ? '; Secure' : ''
                        ),
                        false
                    );
                }
            }
        }

        // Registra acesso bem-sucedido
        $this->log_security_event( 'token_auth_success', [
            'client_id' => $token_data['client_id'],
            'ip'        => $ip_address,
        ], DPS_Logger::LEVEL_INFO );
        // F6.3: FASE 6 - Audit log de login bem-sucedido
        if ( class_exists( 'DPS_Audit_Logger' ) ) {
            DPS_Audit_Logger::log_portal_event( 'login_success', $token_data['client_id'], [ 'ip' => $ip_address ] );
        }
        
        // Registra acesso no histÃ³rico para auditoria
        $user_agent = '';
        if ( isset( $_SERVER['HTTP_USER_AGENT'] ) && is_string( $_SERVER['HTTP_USER_AGENT'] ) ) {
            $user_agent = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
        }
        $token_manager->log_access( $token_data['client_id'], $token_data['id'], $ip_address, $user_agent );
        $this->record_magic_link_login( $token_data['client_id'] );

        // Armazena client_id para disponibilizar autenticaÃ§Ã£o imediatamente
        // sem depender de cookies que sÃ³ estarÃ£o disponÃ­veis na prÃ³xima requisiÃ§Ã£o
        $this->current_request_client_id = $token_data['client_id'];

        // Envia notificaÃ§Ã£o de acesso ao cliente (Fase 1.3 - SeguranÃ§a)
        $this->send_access_notification( $token_data['client_id'], $ip_address );

        // NÃƒO redireciona - permite que a pÃ¡gina atual carregue com o cliente autenticado
        // O JavaScript limparÃ¡ o token da URL por seguranÃ§a (ver assets/js/client-portal.js)
    }

    /**
     * F4.6: FASE 4 - Verifica cookie "manter acesso" para auto-autenticaÃ§Ã£o.
     *
     * Se o cliente nÃ£o tem sessÃ£o ativa mas tem um cookie dps_portal_remember com
     * um token permanente vÃ¡lido, re-autentica automaticamente.
     *
     * @since 2.6.0
     */
    public function handle_remember_cookie() {
        // Ignora se jÃ¡ existe token na URL (handle_token_authentication cuida)
        if ( isset( $_GET['dps_token'] ) ) {
            return;
        }

        // Ignora se jÃ¡ existe sessÃ£o ativa
        $session_manager = DPS_Portal_Session_Manager::get_instance();
        if ( $session_manager->get_authenticated_client_id() ) {
            return;
        }

        // Verifica cookie de acesso permanente
        if ( ! isset( $_COOKIE['dps_portal_remember'] ) ) {
            return;
        }

        $remember_token = sanitize_text_field( wp_unslash( $_COOKIE['dps_portal_remember'] ) );
        if ( empty( $remember_token ) ) {
            return;
        }

        // Valida o token permanente
        $token_manager = DPS_Portal_Token_Manager::get_instance();
        $token_data    = $token_manager->validate_token( $remember_token );

        if ( false === $token_data || ! isset( $token_data['type'] ) || 'permanent' !== $token_data['type'] ) {
            // Token invÃ¡lido ou nÃ£o permanente â€” remove cookie
            setcookie( 'dps_portal_remember', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN );
            return;
        }

        // Token permanente vÃ¡lido â€” autentica o cliente
        $authenticated = $session_manager->authenticate_client( $token_data['client_id'] );
        if ( ! $authenticated ) {
            return;
        }

        $ip_address = $this->get_client_ip();

        // Registra acesso via remember cookie
        $this->log_security_event( 'remember_cookie_auth', [
            'client_id' => $token_data['client_id'],
            'ip'        => $ip_address,
        ], DPS_Logger::LEVEL_INFO );

        $user_agent = '';
        if ( isset( $_SERVER['HTTP_USER_AGENT'] ) && is_string( $_SERVER['HTTP_USER_AGENT'] ) ) {
            $user_agent = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
        }
        $token_manager->log_access( $token_data['client_id'], $token_data['id'], $ip_address, $user_agent );
        $this->record_magic_link_login( $token_data['client_id'], 'remember_cookie' );

        $this->current_request_client_id = $token_data['client_id'];
    }

    /**
     * Processa requisiÃ§Ã£o de logout
     */
    public function handle_logout_request() {
        $session_manager = DPS_Portal_Session_Manager::get_instance();
        $session_manager->handle_logout_request();
    }

    /**
     * Redireciona para a tela de acesso com mensagem de erro
     *
     * @param string $error_type Tipo do erro (invalid, expired, used)
     */
    private function redirect_to_access_screen( $error_type = 'invalid', $query_arg = 'token_error' ) {
        $redirect_url = dps_get_portal_page_url();

        if ( ! $redirect_url || ! is_string( $redirect_url ) ) {
            $redirect_url = home_url( '/portal-cliente/' );
        }

        $redirect_url = remove_query_arg( [ 'token_error', 'portal_auth_error', 'portal_notice', 'dps_action', 'key', 'login' ], $redirect_url );
        $redirect_url = add_query_arg( sanitize_key( $query_arg ), sanitize_key( $error_type ), $redirect_url );

        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Retorna o ID do cliente autenticado via sessao ou usuario WP (compatibilidade)
     *
     * @return int
     */
    private function get_authenticated_client_id() {
        if ( $this->current_request_client_id > 0 ) {
            return $this->current_request_client_id;
        }

        $session_manager = DPS_Portal_Session_Manager::get_instance();
        $client_id       = $session_manager->get_authenticated_client_id();

        if ( $client_id > 0 ) {
            return $client_id;
        }

        return $this->get_client_id_for_current_user();
    }

    /**
     * Metodo publico para obter o ID do cliente autenticado.
     *
     * @return int
     */
    public function get_current_client_id() {
        return $this->get_authenticated_client_id();
    }

    /**
     * Retorna o ID do cliente associado ao usuario logado.
     *
     * @return int
     */
    private function get_client_id_for_current_user() {
        $current_user = wp_get_current_user();
        if ( ! $current_user instanceof WP_User || ! $current_user->exists() ) {
            return 0;
        }

        if ( current_user_can( 'manage_options' ) ) {
            return 0;
        }

        $user_manager = DPS_Portal_User_Manager::get_instance();
        $client_id    = $user_manager->get_client_id_for_user( $current_user );

        if ( $client_id > 0 ) {
            update_user_meta( $current_user->ID, 'dps_client_id', $client_id );
            update_post_meta( $client_id, 'client_user_id', $current_user->ID );
        }

        return $client_id;
    }

    /**
     * Reidrata a sessao customizada do portal quando o cliente ja esta autenticado no WordPress.
     *
     * @return void
     */
    public function restore_portal_session_from_wordpress_user() {
        if ( $this->current_request_client_id > 0 ) {
            return;
        }

        $current_user = wp_get_current_user();
        if ( ! $current_user instanceof WP_User || ! $current_user->exists() ) {
            return;
        }

        if ( current_user_can( 'manage_options' ) ) {
            return;
        }

        $user_manager = DPS_Portal_User_Manager::get_instance();
        if ( ! $user_manager->is_client_portal_user( $current_user ) ) {
            return;
        }

        $session_manager = DPS_Portal_Session_Manager::get_instance();
        if ( $session_manager->get_authenticated_client_id() ) {
            return;
        }

        $client_id = $user_manager->get_client_id_for_user( $current_user );
        if ( $client_id > 0 && $session_manager->authenticate_client( $client_id ) ) {
            $this->current_request_client_id = $client_id;
        }
    }

    /**
     * Processa login por e-mail e senha dentro do portal.
     *
     * @return void
     */
    public function handle_password_login_request() {
        if ( empty( $_POST['dps_portal_password_login'] ) ) {
            return;
        }

        $nonce = isset( $_POST['_dps_portal_password_login_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_dps_portal_password_login_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'dps_portal_password_login' ) ) {
            $this->redirect_to_access_screen( 'session_expired', 'portal_auth_error' );
        }

        $email    = isset( $_POST['dps_portal_email'] ) ? sanitize_email( wp_unslash( $_POST['dps_portal_email'] ) ) : '';
        $password = isset( $_POST['dps_portal_password'] ) ? (string) wp_unslash( $_POST['dps_portal_password'] ) : '';
        $remember = isset( $_POST['dps_portal_remember'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['dps_portal_remember'] ) );

        if ( ! is_email( $email ) || '' === trim( $password ) ) {
            $this->redirect_to_access_screen( 'invalid_credentials', 'portal_auth_error' );
        }

        $rate_limiter = DPS_Portal_Rate_Limiter::get_instance();
        $ip_address   = $this->get_client_ip();

        if ( $rate_limiter->is_limited( 'portal_password_login_ip', $ip_address, 5 ) || $rate_limiter->is_limited( 'portal_password_login_email', $email, 5 ) ) {
            $this->redirect_to_access_screen( 'too_many_attempts', 'portal_auth_error' );
        }

        $user_manager = DPS_Portal_User_Manager::get_instance();
        $wp_user      = get_user_by( 'email', $email );

        if ( $wp_user instanceof WP_User && ! $user_manager->is_client_portal_user( $wp_user ) ) {
            $rate_limiter->hit( 'portal_password_login_ip', $ip_address, 15 * MINUTE_IN_SECONDS );
            $this->redirect_to_access_screen( 'invalid_credentials', 'portal_auth_error' );
        }

        $authenticated_user = wp_signon(
            [
                'user_login'    => $email,
                'user_password' => $password,
                'remember'      => $remember,
            ],
            is_ssl()
        );

        if ( is_wp_error( $authenticated_user ) || ! $authenticated_user instanceof WP_User || ! $user_manager->is_client_portal_user( $authenticated_user ) ) {
            $rate_limiter->hit( 'portal_password_login_ip', $ip_address, 15 * MINUTE_IN_SECONDS );
            $rate_limiter->hit( 'portal_password_login_email', $email, 15 * MINUTE_IN_SECONDS );
            $this->log_security_event(
                'password_login_failed',
                [
                    'email' => $email,
                    'ip'    => $ip_address,
                ]
            );
            $this->redirect_to_access_screen( 'invalid_credentials', 'portal_auth_error' );
        }

        $rate_limiter->clear( 'portal_password_login_ip', $ip_address );
        $rate_limiter->clear( 'portal_password_login_email', $email );

        $portal_url = dps_get_portal_page_url();
        if ( ! $portal_url || ! is_string( $portal_url ) ) {
            $portal_url = home_url( '/portal-cliente/' );
        }

        wp_safe_redirect( remove_query_arg( [ 'portal_auth_error', 'token_error', 'portal_notice', 'dps_action', 'key', 'login' ], $portal_url ) );
        exit;
    }

    /**
     * Processa redefinicao de senha dentro do portal.
     *
     * @return void
     */
    public function handle_password_reset_request() {
        if ( empty( $_POST['dps_portal_password_reset_submit'] ) ) {
            return;
        }

        $nonce = isset( $_POST['_dps_portal_password_reset_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_dps_portal_password_reset_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'dps_portal_password_reset' ) ) {
            $this->redirect_to_access_screen( 'reset_link_invalid', 'portal_auth_error' );
        }

        $login    = isset( $_POST['rp_login'] ) ? sanitize_text_field( wp_unslash( $_POST['rp_login'] ) ) : '';
        $key      = isset( $_POST['rp_key'] ) ? sanitize_text_field( wp_unslash( $_POST['rp_key'] ) ) : '';
        $password = isset( $_POST['dps_portal_new_password'] ) ? (string) wp_unslash( $_POST['dps_portal_new_password'] ) : '';
        $confirm  = isset( $_POST['dps_portal_confirm_password'] ) ? (string) wp_unslash( $_POST['dps_portal_confirm_password'] ) : '';
        $remember = isset( $_POST['dps_portal_remember'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['dps_portal_remember'] ) );

        $portal_url = dps_get_portal_page_url();
        if ( ! $portal_url || ! is_string( $portal_url ) ) {
            $portal_url = home_url( '/portal-cliente/' );
        }

        $reset_redirect = add_query_arg(
            [
                'dps_action' => 'portal_password_reset',
                'login'      => rawurlencode( $login ),
                'key'        => rawurlencode( $key ),
            ],
            $portal_url
        );

        $user = check_password_reset_key( $key, $login );
        if ( is_wp_error( $user ) || ! $user instanceof WP_User ) {
            wp_safe_redirect( add_query_arg( 'portal_auth_error', 'reset_link_invalid', $portal_url ) );
            exit;
        }

        if ( '' === trim( $password ) || '' === trim( $confirm ) ) {
            wp_safe_redirect( add_query_arg( 'portal_auth_error', 'password_required', $reset_redirect ) );
            exit;
        }

        if ( $password !== $confirm ) {
            wp_safe_redirect( add_query_arg( 'portal_auth_error', 'password_mismatch', $reset_redirect ) );
            exit;
        }

        if ( strlen( $password ) < 8 ) {
            wp_safe_redirect( add_query_arg( 'portal_auth_error', 'password_short', $reset_redirect ) );
            exit;
        }

        reset_password( $user, $password );
        wp_set_current_user( $user->ID );
        wp_set_auth_cookie( $user->ID, $remember, is_ssl() );
        do_action( 'wp_login', $user->user_login, $user );

        wp_safe_redirect( add_query_arg( 'portal_notice', 'password_reset_success', $portal_url ) );
        exit;
    }

    /**
     * Mantem a sessao customizada sincronizada quando o cliente faz login WordPress.
     *
     * @param string  $user_login Login do usuario.
     * @param WP_User $user Usuario autenticado.
     * @return void
     */
    public function handle_wordpress_user_login( $user_login, $user ) {
        if ( ! $user instanceof WP_User ) {
            return;
        }

        $user_manager = DPS_Portal_User_Manager::get_instance();
        if ( ! $user_manager->is_client_portal_user( $user ) ) {
            return;
        }

        $client_id = $user_manager->get_client_id_for_user( $user );
        if ( ! $client_id ) {
            return;
        }

        update_user_meta( $user->ID, 'dps_client_id', $client_id );
        update_post_meta( $client_id, 'client_user_id', $user->ID );

        if ( DPS_Portal_Session_Manager::get_instance()->authenticate_client( $client_id ) ) {
            $this->current_request_client_id = $client_id;
        }

        $user_manager->record_password_login( $user );
    }

    /**
     * Redireciona clientes do portal para fora do wp-admin.
     *
     * @return void
     */
    public function redirect_portal_clients_from_admin() {
        if ( ! is_admin() || wp_doing_ajax() || current_user_can( 'manage_options' ) ) {
            return;
        }

        $current_user = wp_get_current_user();
        if ( ! $current_user instanceof WP_User || ! $current_user->exists() ) {
            return;
        }

        $user_manager = DPS_Portal_User_Manager::get_instance();
        if ( ! $user_manager->is_client_portal_user( $current_user ) ) {
            return;
        }

        $portal_url = dps_get_portal_page_url();
        if ( ! $portal_url || ! is_string( $portal_url ) ) {
            $portal_url = home_url( '/portal-cliente/' );
        }

        wp_safe_redirect( $portal_url );
        exit;
    }

    /**
     * Oculta a admin bar para clientes do portal.
     *
     * @param bool $show_admin_bar Estado atual.
     * @return bool
     */
    public function filter_portal_client_admin_bar( $show_admin_bar ) {
        if ( current_user_can( 'manage_options' ) ) {
            return $show_admin_bar;
        }

        $current_user = wp_get_current_user();
        if ( ! $current_user instanceof WP_User || ! $current_user->exists() ) {
            return $show_admin_bar;
        }

        return DPS_Portal_User_Manager::get_instance()->is_client_portal_user( $current_user ) ? false : $show_admin_bar;
    }

    /**
     * Redireciona logins do WordPress para o portal quando o usuario e cliente.
     *
     * @param string           $redirect_to URL final.
     * @param string           $requested_redirect_to URL solicitada.
     * @param WP_User|WP_Error $user Usuario autenticado.
     * @return string
     */
    public function filter_portal_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
        if ( is_wp_error( $user ) || ! $user instanceof WP_User ) {
            return $redirect_to;
        }

        if ( user_can( $user, 'manage_options' ) ) {
            return $redirect_to;
        }

        $portal_url = dps_get_portal_page_url();
        if ( ! $portal_url || ! is_string( $portal_url ) ) {
            $portal_url = home_url( '/portal-cliente/' );
        }

        return DPS_Portal_User_Manager::get_instance()->is_client_portal_user( $user ) ? $portal_url : $redirect_to;
    }

    /**
     * Atualiza metadados do ultimo login via magic link.
     *
     * @param int    $client_id ID do cliente.
     * @param string $method Metodo registrado.
     * @return void
     */
    private function record_magic_link_login( $client_id, $method = 'magic_link' ) {
        $client_id = absint( $client_id );
        if ( ! $client_id ) {
            return;
        }

        update_post_meta( $client_id, 'dps_portal_last_magic_link_login_at', current_time( 'mysql' ) );
        update_post_meta( $client_id, 'dps_portal_last_login_method', sanitize_key( $method ) );
    }

    /**
     * Sincroniza a conta WordPress vinculada ao cliente usando o e-mail cadastrado.
     *
     * @param int     $post_id ID do post do cliente.
     * @param WP_Post $post Objeto do post salvo.
     * @param bool    $update Indica se a operacao e atualizacao.
     * @return void
     */
    public function maybe_create_login_for_client( $post_id, $post, $update ) {
        if ( wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
            return;
        }

        if ( ! $post instanceof WP_Post || 'dps_cliente' !== $post->post_type ) {
            return;
        }

        $email = DPS_Portal_User_Manager::get_instance()->get_client_email( $post_id );
        if ( ! $email ) {
            return;
        }

        $result = DPS_Portal_User_Manager::get_instance()->ensure_user_for_client( $post_id );
        if ( is_wp_error( $result ) ) {
            $this->log_security_event(
                'client_user_sync_failed',
                [
                    'client_id' => $post_id,
                    'reason'    => $result->get_error_code(),
                ]
            );
        }
    }

    /**
     * Processa requisicoes de formularios enviados pelo portal do cliente.
     * Utiliza nonce para protecao CSRF e atualiza metas conforme necessario.
     *
     * Suporta autenticacao via:
     * - Sistema de tokens/sessao (preferencial para clientes via magic link)
     * - Usuarios WordPress logados (retrocompatibilidade)
     */
    public function handle_portal_actions() {
        $client_id = $this->get_authenticated_client_id();
        
        if ( ! $client_id ) {
            return;
        }

        if ( empty( $_POST['dps_client_portal_action'] ) ) {
            return;
        }

        $nonce = isset( $_POST['_dps_client_portal_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_dps_client_portal_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'dps_client_portal_action' ) ) {
            $referer      = wp_get_referer();
            $redirect_url = $referer ? remove_query_arg( 'portal_msg', $referer ) : remove_query_arg( 'portal_msg' );
            $redirect_url = add_query_arg( 'portal_msg', 'session_expired', $redirect_url );
            wp_safe_redirect( $redirect_url );
            exit;
        }

        $action       = sanitize_key( wp_unslash( $_POST['dps_client_portal_action'] ) );
        $referer      = wp_get_referer();
        $redirect_url = $referer ? remove_query_arg( 'portal_msg', $referer ) : remove_query_arg( 'portal_msg' );
        $handler      = DPS_Portal_Actions_Handler::get_instance();

        switch ( $action ) {
            case 'pay_transaction':
                $trans_id = isset( $_POST['trans_id'] ) ? absint( wp_unslash( $_POST['trans_id'] ) ) : 0;
                if ( $trans_id ) {
                    $result = $handler->handle_pay_transaction( $client_id, $trans_id );
                    wp_safe_redirect( $result );
                    exit;
                }
                $redirect_url = add_query_arg( 'portal_msg', 'error', $redirect_url );
                break;

            case 'update_client_info':
                $redirect_url = $handler->handle_update_client_info( $client_id );
                break;

            case 'update_pet':
                $pet_id = isset( $_POST['pet_id'] ) ? absint( wp_unslash( $_POST['pet_id'] ) ) : 0;
                if ( $pet_id ) {
                    $redirect_url = $handler->handle_update_pet( $client_id, $pet_id );
                } else {
                    $redirect_url = add_query_arg( 'portal_msg', 'error', $redirect_url );
                }
                break;

            case 'send_message':
                $redirect_url = $handler->handle_send_message( $client_id );
                break;

            case 'update_client_preferences':
                $redirect_url = $handler->handle_update_client_preferences( $client_id );
                break;

            case 'update_pet_preferences':
                $pet_id = isset( $_POST['pet_id'] ) ? absint( wp_unslash( $_POST['pet_id'] ) ) : 0;
                if ( $pet_id ) {
                    $redirect_url = $handler->handle_update_pet_preferences( $client_id, $pet_id );
                } else {
                    $redirect_url = add_query_arg( 'portal_msg', 'error', $redirect_url );
                }
                break;

            case 'submit_internal_review':
                $redirect_url = $handler->handle_submit_internal_review( $client_id );
                break;
        }

        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Registra estilos do portal no frontend.
     */
    public function register_assets() {
        if ( ! defined( 'DPS_CLIENT_PORTAL_ADDON_URL' ) ) {
            return;
        }

        // Design tokens M3 Expressive (devem ser carregados antes de qualquer CSS do portal)
        $style_deps = [];
        if ( defined( 'DPS_BASE_URL' ) ) {
            wp_register_style(
                'dps-design-tokens',
                DPS_BASE_URL . 'assets/css/dps-design-tokens.css',
                [],
                defined( 'DPS_BASE_VERSION' ) ? DPS_BASE_VERSION : '2.0.0'
            );
            $style_deps[] = 'dps-design-tokens';
        }

        $style_path = trailingslashit( DPS_CLIENT_PORTAL_ADDON_DIR ) . 'assets/css/client-portal.css';
        $style_url  = trailingslashit( DPS_CLIENT_PORTAL_ADDON_URL ) . 'assets/css/client-portal.css';
        $style_version = file_exists( $style_path ) ? filemtime( $style_path ) : '1.0.0';

        wp_register_style( 'dps-client-portal', $style_url, $style_deps, $style_version );
        
        $script_path = trailingslashit( DPS_CLIENT_PORTAL_ADDON_DIR ) . 'assets/js/client-portal.js';
        $script_url  = trailingslashit( DPS_CLIENT_PORTAL_ADDON_URL ) . 'assets/js/client-portal.js';
        $script_version = file_exists( $script_path ) ? filemtime( $script_path ) : '1.0.0';
        
        wp_register_script( 'dps-client-portal', $script_url, [], $script_version, true );
    }

    /**
     * Localiza o script publico do portal para telas autenticadas e nao autenticadas.
     *
     * @param int   $client_id ID do cliente autenticado.
     * @param array $client_pets_data Lista de pets do cliente.
     * @param array $scheduling_suggestions Sugestoes de agendamento.
     * @return void
     */
    private function localize_portal_script( $client_id = 0, array $client_pets_data = [], array $scheduling_suggestions = [] ) {
        $data = [
            'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
            'clientId'        => absint( $client_id ),
            'isAuthenticated' => $client_id > 0,
            'access'          => [
                'magicLinkAction' => 'dps_request_access_link_by_email',
                'passwordAction'  => 'dps_request_portal_password_access',
                'requestNonce'    => wp_create_nonce( 'dps_request_access_link' ),
                'passwordNonce'   => wp_create_nonce( 'dps_request_password_access' ),
                'i18n'            => [
                    'requestingLink'     => __( 'Enviando link...', 'dps-client-portal' ),
                    'requestingPassword' => __( 'Enviando instrucoes...', 'dps-client-portal' ),
                    'genericError'       => __( 'Nao foi possivel concluir sua solicitacao agora. Tente novamente em alguns instantes.', 'dps-client-portal' ),
                    'emailRequired'      => __( 'Informe um e-mail valido para continuar.', 'dps-client-portal' ),
                    'copySuccess'        => __( 'Link copiado com sucesso.', 'dps-client-portal' ),
                ],
            ],
        ];

        if ( $client_id > 0 ) {
            $data['chatNonce']             = wp_create_nonce( 'dps_portal_chat' );
            $data['requestNonce']          = wp_create_nonce( 'dps_portal_appointment_request' );
            $data['exportPdfNonce']        = wp_create_nonce( 'dps_portal_export_pdf' );
            $data['petHistoryNonce']       = wp_create_nonce( 'dps_portal_pet_history' );
            $data['clientPets']            = $client_pets_data;
            $data['schedulingSuggestions'] = $scheduling_suggestions;
            $data['whatsappNumber']        = $this->get_portal_whatsapp_number();
            $data['loyalty']               = [
                'nonce'        => wp_create_nonce( 'dps_portal_loyalty' ),
                'historyLimit' => 5,
                'i18n'         => [
                    'loading'       => __( 'Carregando...', 'dps-client-portal' ),
                    'redeemSuccess' => __( 'Resgate realizado com sucesso!', 'dps-client-portal' ),
                    'redeemError'   => __( 'Nao foi possivel concluir o resgate.', 'dps-client-portal' ),
                ],
            ];
            $data['game']                  = [
                'enabled'   => class_exists( 'DPS_Game_Progress_Service' ),
                'nonce'     => wp_create_nonce( 'dps_game_progress' ),
                'endpoints' => [
                    'progress' => esc_url_raw( rest_url( 'dps-game/v1/progress' ) ),
                ],
                'i18n'      => [
                    'loading'        => __( 'Carregando progresso do jogo...', 'dps-client-portal' ),
                    'empty'          => __( 'Jogue uma run para comecar seu historico sincronizado.', 'dps-client-portal' ),
                    'error'          => __( 'Nao foi possivel carregar o progresso do jogo agora.', 'dps-client-portal' ),
                    'missionDone'    => __( 'Missao concluida hoje.', 'dps-client-portal' ),
                    'missionPending' => __( 'Falta pouco para concluir a meta.', 'dps-client-portal' ),
                ],
            ];
        }

        wp_localize_script( 'dps-client-portal', 'dpsPortal', $data );
    }

    /**
     * Retorna o numero de WhatsApp configurado para o portal.
     *
     * @return string
     */
    private function get_portal_whatsapp_number() {
        $whatsapp_number = get_option( 'dps_whatsapp_number', '' );
        if ( ! $whatsapp_number ) {
            return '';
        }

        if ( class_exists( 'DPS_Phone_Helper' ) ) {
            $whatsapp_number = DPS_Phone_Helper::format_for_whatsapp( $whatsapp_number );
        } else {
            $whatsapp_number = preg_replace( '/\D+/', '', (string) $whatsapp_number );
        }

        return is_string( $whatsapp_number ) ? trim( $whatsapp_number ) : '';
    }

    /**
     * Retorna o link de WhatsApp para o cliente falar com a equipe.
     *
     * @return string
     */
    private function get_portal_whatsapp_url() {
        if ( class_exists( 'DPS_WhatsApp_Helper' ) ) {
            $message = DPS_WhatsApp_Helper::get_portal_access_request_message();
            return (string) DPS_WhatsApp_Helper::get_link_to_team( $message );
        }

        return $this->get_portal_whatsapp_link( __( 'Ola! Gostaria de receber acesso ao Portal do Cliente.', 'dps-client-portal' ) );
    }

    /**
     * Monta uma URL de WhatsApp para o portal com mensagem customizada.
     *
     * @param string $message Mensagem inicial.
     * @return string
     */
    private function get_portal_whatsapp_link( $message ) {
        if ( class_exists( 'DPS_WhatsApp_Helper' ) ) {
            return (string) DPS_WhatsApp_Helper::get_link_to_team( (string) $message );
        }

        $whatsapp_number = $this->get_portal_whatsapp_number();
        if ( ! $whatsapp_number ) {
            return '';
        }

        return 'https://wa.me/' . $whatsapp_number . '?text=' . rawurlencode( (string) $message );
    }
    /**
     * Monta o contexto usado pela tela de acesso.
     *
     * @return array<string, mixed>
     */
    private function get_access_screen_context() {
        $message_map = [
            'token_error' => [
                'invalid'        => [ 'type' => 'error', 'title' => __( 'Link invalido', 'dps-client-portal' ), 'description' => __( 'Esse link nao e mais valido. Solicite um novo acesso.', 'dps-client-portal' ) ],
                'expired'        => [ 'type' => 'warning', 'title' => __( 'Link expirado', 'dps-client-portal' ), 'description' => __( 'Esse link expirou. Solicite um novo link direto de acesso.', 'dps-client-portal' ) ],
                'used'           => [ 'type' => 'warning', 'title' => __( 'Link ja utilizado', 'dps-client-portal' ), 'description' => __( 'Esse link ja foi usado. Gere ou solicite outro para entrar novamente.', 'dps-client-portal' ) ],
                'page_not_found' => [ 'type' => 'error', 'title' => __( 'Portal nao configurado', 'dps-client-portal' ), 'description' => __( 'A pagina do portal ainda nao foi configurada corretamente.', 'dps-client-portal' ) ],
            ],
            'portal_auth_error' => [
                'invalid_credentials' => [ 'type' => 'error', 'title' => __( 'E-mail ou senha incorretos', 'dps-client-portal' ), 'description' => __( 'Use o e-mail cadastrado no cliente e confira a senha informada.', 'dps-client-portal' ) ],
                'too_many_attempts'   => [ 'type' => 'warning', 'title' => __( 'Muitas tentativas', 'dps-client-portal' ), 'description' => __( 'Aguarde alguns minutos antes de tentar novamente.', 'dps-client-portal' ) ],
                'session_expired'     => [ 'type' => 'warning', 'title' => __( 'Sessao expirada', 'dps-client-portal' ), 'description' => __( 'Atualize a pagina e tente novamente.', 'dps-client-portal' ) ],
                'reset_link_invalid'  => [ 'type' => 'error', 'title' => __( 'Link de senha invalido', 'dps-client-portal' ), 'description' => __( 'Solicite um novo link para criar ou redefinir sua senha.', 'dps-client-portal' ) ],
                'password_required'   => [ 'type' => 'warning', 'title' => __( 'Informe uma senha', 'dps-client-portal' ), 'description' => __( 'Preencha a nova senha e a confirmacao para continuar.', 'dps-client-portal' ) ],
                'password_mismatch'   => [ 'type' => 'warning', 'title' => __( 'As senhas nao conferem', 'dps-client-portal' ), 'description' => __( 'Digite a mesma senha nos dois campos.', 'dps-client-portal' ) ],
                'password_short'      => [ 'type' => 'warning', 'title' => __( 'Senha muito curta', 'dps-client-portal' ), 'description' => __( 'Use pelo menos 8 caracteres na nova senha.', 'dps-client-portal' ) ],
            ],
            'portal_notice' => [
                'password_reset_success' => [ 'type' => 'success', 'title' => __( 'Senha atualizada', 'dps-client-portal' ), 'description' => __( 'Sua senha foi criada com sucesso. Voce ja pode entrar no portal.', 'dps-client-portal' ) ],
                'password_email_sent'    => [ 'type' => 'success', 'title' => __( 'Instrucoes enviadas', 'dps-client-portal' ), 'description' => __( 'Verifique seu e-mail para criar ou redefinir a senha.', 'dps-client-portal' ) ],
            ],
        ];

        $messages = [];
        foreach ( $message_map as $query_arg => $options ) {
            $value = isset( $_GET[ $query_arg ] ) ? sanitize_key( wp_unslash( $_GET[ $query_arg ] ) ) : '';
            if ( $value && isset( $options[ $value ] ) ) {
                $messages[] = $options[ $value ];
            }
        }

        $portal_url = dps_get_portal_page_url();
        if ( ! $portal_url || ! is_string( $portal_url ) ) {
            $portal_url = home_url( '/portal-cliente/' );
        }

        return [
            'messages'     => $messages,
            'portal_url'   => $portal_url,
            'whatsapp_url' => $this->get_portal_whatsapp_url(),
        ];
    }

    /**
     * Renderiza a tela principal de acesso do portal.
     *
     * @return string
     */
    private function render_access_screen() {
        do_action( 'dps_portal_before_login_screen' );

        $portal_access_context = $this->get_access_screen_context();
        $template_path         = DPS_CLIENT_PORTAL_ADDON_DIR . 'templates/portal-access.php';

        if ( file_exists( $template_path ) ) {
            ob_start();
            include $template_path;
            $output = ob_get_clean();

            return apply_filters( 'dps_portal_login_screen', $output, $portal_access_context );
        }

        ob_start();
        echo '<div class="dps-client-portal-login">';
        echo '<h3>' . esc_html__( 'Acesso ao Portal do Cliente', 'dps-client-portal' ) . '</h3>';
        echo '<p>' . esc_html__( 'Solicite um link direto ou entre com o e-mail cadastrado e sua senha.', 'dps-client-portal' ) . '</p>';
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Renderiza a tela de criacao ou redefinicao de senha.
     *
     * @return string
     */
    private function render_password_reset_screen() {
        $portal_access_context = $this->get_access_screen_context();
        $portal_password_login = isset( $_GET['login'] ) ? sanitize_text_field( wp_unslash( $_GET['login'] ) ) : '';
        $portal_password_key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
        $portal_reset_user     = ( $portal_password_login && $portal_password_key ) ? check_password_reset_key( $portal_password_key, $portal_password_login ) : new WP_Error( 'invalid_key', __( 'Link invalido.', 'dps-client-portal' ) );
        $portal_reset_valid    = $portal_reset_user instanceof WP_User && ! is_wp_error( $portal_reset_user );

        if ( ! $portal_reset_valid ) {
            $portal_access_context['messages'][] = [
                'type'        => 'error',
                'title'       => __( 'Link de senha invalido', 'dps-client-portal' ),
                'description' => __( 'Solicite um novo e-mail para criar ou redefinir sua senha.', 'dps-client-portal' ),
            ];
        }

        $template_path = DPS_CLIENT_PORTAL_ADDON_DIR . 'templates/portal-password-reset.php';
        if ( file_exists( $template_path ) ) {
            ob_start();
            include $template_path;
            return ob_get_clean();
        }

        return $this->render_access_screen();
    }

    /**
     * Renderiza a pagina do portal para o shortcode. Mostra tela de acesso se nao autenticado.
     *
     * @return string Conteudo HTML renderizado.
     */
    public function render_portal_shortcode() {
        // Desabilita cache da pÃ¡gina para garantir dados sempre atualizados
        if ( class_exists( 'DPS_Cache_Control' ) ) {
            DPS_Cache_Control::force_no_cache();
        }

        // Hook: Antes de renderizar o portal (Fase 2.3)
        do_action( 'dps_portal_before_render' );
        
        wp_enqueue_style( 'dps-client-portal' );
        wp_enqueue_script( 'dps-client-portal' );
        
        // Verifica se ? uma a??o de atualiza??o de perfil via token (Fase 5)
        $action = isset( $_GET['dps_action'] ) ? sanitize_text_field( wp_unslash( $_GET['dps_action'] ) ) : '';
        if ( 'profile_update' === $action && isset( $_GET['token'] ) ) {
            if ( class_exists( 'DPS_Portal_Profile_Update' ) ) {
                return DPS_Portal_Profile_Update::get_instance()->render_profile_update_shortcode( [] );
            }
        }

        if ( 'portal_password_reset' === $action ) {
            $this->localize_portal_script();
            return $this->render_password_reset_screen();
        }

        // Verifica autentica??o pelo novo sistema
        $client_id = $this->get_authenticated_client_id();

        // F6.4: Se h? 2FA pendente, renderiza formul?rio de verifica??o
        if ( ! $client_id && ! empty( $this->pending_2fa_session_key ) ) {
            $email = get_post_meta( $this->pending_2fa_client_id, 'client_email', true );
            $twofa = DPS_Portal_2FA::get_instance();
            return $twofa->render_verification_form( $this->pending_2fa_session_key, $email );
        }

        // Hook: Ap?s verificar autentica??o (Fase 2.3)
        do_action( 'dps_portal_after_auth_check', $client_id );

        // Se n?o autenticado, exibe tela de acesso
        if ( ! $client_id ) {
            $this->localize_portal_script();
            return $this->render_access_screen();
        }

        // Hook: Cliente autenticado (Fase 2.3)
        do_action( 'dps_portal_client_authenticated', $client_id );
        
        // Localiza script com dados do chat e appointment requests (Fase 4)
        // Phase 5.3: Incluir lista de pets do cliente para seletor rÃ¡pido no modal
        $client_pets_data = [];
        $pet_repo = DPS_Pet_Repository::get_instance();
        $client_pets = $pet_repo->get_pets_by_client( $client_id );
        foreach ( $client_pets as $pet ) {
            $species = get_post_meta( $pet->ID, 'pet_species', true );
            $species_icons = [
                'Cachorro' => 'ðŸ¶',
                'Gato'     => 'ðŸ±',
            ];
            $client_pets_data[] = [
                'id'      => $pet->ID,
                'name'    => $pet->post_title,
                'species' => $species,
                'icon'    => isset( $species_icons[ $species ] ) ? $species_icons[ $species ] : 'ðŸ¾',
            ];
        }

        // Phase 8.1: Gera sugestÃµes inteligentes de agendamento baseadas no histÃ³rico
        $scheduling_suggestions = [];
        if ( ! empty( $client_pets ) ) {
            $suggestions_service = DPS_Scheduling_Suggestions::get_instance();
            $scheduling_suggestions = $suggestions_service->get_suggestions_for_client( $client_id, $client_pets );
        }

        $this->localize_portal_script( $client_id, $client_pets_data, $scheduling_suggestions );
        
        ob_start();
        // Filtro de mensagens de retorno
        if ( isset( $_GET['portal_msg'] ) ) {
            $msg = sanitize_text_field( wp_unslash( $_GET['portal_msg'] ) );
            if ( 'updated' === $msg ) {
                echo '<div class="dps-portal-notice dps-portal-notice--success">' . esc_html__( 'Dados atualizados com sucesso.', 'dps-client-portal' ) . '</div>';
            } elseif ( 'error' === $msg ) {
                echo '<div class="dps-portal-notice dps-portal-notice--error">' . esc_html__( 'Ocorreu um erro ao processar sua solicitaÃ§Ã£o.', 'dps-client-portal' ) . '</div>';
            } elseif ( 'message_sent' === $msg ) {
                echo '<div class="dps-portal-notice dps-portal-notice--success">' . esc_html__( 'Mensagem enviada para a equipe. Responderemos em breve!', 'dps-client-portal' ) . '</div>';
            } elseif ( 'message_error' === $msg ) {
                echo '<div class="dps-portal-notice dps-portal-notice--error">' . esc_html__( 'NÃ£o foi possÃ­vel enviar sua mensagem. Verifique o conteÃºdo e tente novamente.', 'dps-client-portal' ) . '</div>';
            } elseif ( 'invalid_file_type' === $msg ) {
                echo '<div class="dps-portal-notice dps-portal-notice--error">' . esc_html__( 'Tipo de arquivo nÃ£o permitido. Apenas imagens JPG, PNG, GIF e WebP sÃ£o aceitas.', 'dps-client-portal' ) . '</div>';
            } elseif ( 'file_too_large' === $msg ) {
                echo '<div class="dps-portal-notice dps-portal-notice--error">' . esc_html__( 'O arquivo Ã© muito grande. O tamanho mÃ¡ximo permitido Ã© 5MB.', 'dps-client-portal' ) . '</div>';
            } elseif ( 'session_expired' === $msg ) {
                echo '<div class="dps-portal-notice dps-portal-notice--error">' . esc_html__( 'Sua sessÃ£o expirou ou Ã© invÃ¡lida. Por favor, atualize a pÃ¡gina e tente novamente.', 'dps-client-portal' ) . '</div>';
            } elseif ( 'pet_updated' === $msg ) {
                echo '<div class="dps-portal-notice dps-portal-notice--success">' . esc_html__( 'Dados do pet atualizados com sucesso.', 'dps-client-portal' ) . '</div>';
            } elseif ( 'preferences_updated' === $msg ) {
                echo '<div class="dps-portal-notice dps-portal-notice--success">' . esc_html__( 'PreferÃªncias atualizadas com sucesso.', 'dps-client-portal' ) . '</div>';
            } elseif ( 'pet_preferences_updated' === $msg ) {
                echo '<div class="dps-portal-notice dps-portal-notice--success">' . esc_html__( 'PreferÃªncias do pet atualizadas com sucesso.', 'dps-client-portal' ) . '</div>';
            } elseif ( 'review_submitted' === $msg ) {
                echo '<div class="dps-portal-notice dps-portal-notice--success">' . esc_html__( 'ðŸŽ‰ Obrigado pela sua avaliaÃ§Ã£o! Sua opiniÃ£o Ã© muito importante para nÃ³s.', 'dps-client-portal' ) . '</div>';
            } elseif ( 'review_already' === $msg ) {
                echo '<div class="dps-portal-notice dps-portal-notice--info">' . esc_html__( 'VocÃª jÃ¡ fez uma avaliaÃ§Ã£o. Obrigado pelo feedback!', 'dps-client-portal' ) . '</div>';
            } elseif ( 'review_invalid' === $msg ) {
                echo '<div class="dps-portal-notice dps-portal-notice--error">' . esc_html__( 'Por favor, selecione uma nota de 1 a 5 estrelas.', 'dps-client-portal' ) . '</div>';
            } elseif ( 'review_error' === $msg ) {
                echo '<div class="dps-portal-notice dps-portal-notice--error">' . esc_html__( 'NÃ£o foi possÃ­vel registrar sua avaliaÃ§Ã£o. Tente novamente.', 'dps-client-portal' ) . '</div>';
            }
        }
        
        // Fase 4 - Branding: buscar configuraÃ§Ãµes
        $logo_id       = get_option( 'dps_portal_logo_id', '' );
        $primary_color = get_option( 'dps_portal_primary_color', '#0ea5e9' );
        $hero_id       = get_option( 'dps_portal_hero_id', '' );
        
        // Aplica classe de branding se houver customizaÃ§Ãµes
        $portal_classes = [ 'dps-client-portal' ];
        if ( $logo_id || $primary_color !== '#0ea5e9' || $hero_id ) {
            $portal_classes[] = 'dps-portal-branded';
        }
        
        // Inline CSS para cor primÃ¡ria customizada
        if ( $primary_color && $primary_color !== '#0ea5e9' ) {
            echo '<style>.dps-portal-branded { --dps-custom-primary: ' . esc_attr( $primary_color ) . '; --dps-custom-primary-hover: ' . esc_attr( $this->adjust_brightness( $primary_color, -20 ) ) . '; }</style>';
        }
        
        echo '<div class="' . esc_attr( implode( ' ', $portal_classes ) ) . '">';
        
        // Header com tÃ­tulo e botÃ£o de logout
        echo '<div class="dps-portal-header dps-portal-header--branded">';
        echo '<div class="dps-portal-header__main">';
        
        // Imagem hero (se configurada)
        if ( $hero_id ) {
            $hero_url = wp_get_attachment_image_url( $hero_id, 'full' );
            if ( $hero_url ) {
                echo '<div class="dps-portal-hero" style="background-image: url(' . esc_url( $hero_url ) . ');"></div>';
            }
        }
        
        // Logo (se configurado)
        if ( $logo_id ) {
            $logo_url = wp_get_attachment_image_url( $logo_id, 'medium' );
            if ( $logo_url ) {
                echo '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '" class="dps-portal-logo">';
            }
        }
        
        // SaudaÃ§Ã£o personalizada com nome do cliente
        $client_name        = get_the_title( $client_id );
        $dashboard_snapshot = $this->get_portal_dashboard_snapshot( $client_id );
        if ( $client_name ) {
            echo '<h1 class="dps-portal-title">';
            echo esc_html( sprintf( __( 'OlÃ¡, %s ðŸ‘‹', 'dps-client-portal' ), $client_name ) );
            echo '</h1>';
        } else {
            echo '<h1 class="dps-portal-title">' . esc_html__( 'Portal do Cliente', 'dps-client-portal' ) . '</h1>';
        }
        
        echo '</div>';
        echo '<div class="dps-portal-header__actions">';

        // Link de avaliaÃ§Ã£o discreto no topo do portal
        $review_url = $this->get_review_url();
        if ( $review_url ) {
            echo '<a href="' . esc_url( $review_url ) . '" target="_blank" rel="noopener" class="dps-portal-review-link" title="' . esc_attr__( 'Avalie nosso serviÃ§o', 'dps-client-portal' ) . '">';
            echo '<span class="dps-portal-review-icon">â­</span>';
            echo '<span class="dps-portal-review-text">' . esc_html__( 'Avalie-nos', 'dps-client-portal' ) . '</span>';
            echo '</a>';
        }

        // BotÃ£o de logout
        $session_manager = DPS_Portal_Session_Manager::get_instance();
        $logout_url      = $session_manager->get_logout_url();
        echo '<a href="' . esc_url( $logout_url ) . '" class="dps-portal-logout">' . esc_html__( 'Sair', 'dps-client-portal' ) . '</a>';
        echo '</div>';
        echo '</div>';
        
        // Hook para add-ons adicionarem conteÃºdo no topo do portal (ex: AI Assistant)
        do_action( 'dps_client_portal_before_content', $client_id );
        
        // Breadcrumb simples para contexto
        echo '<nav class="dps-portal-breadcrumb" aria-label="' . esc_attr__( 'NavegaÃ§Ã£o', 'dps-client-portal' ) . '">';
        echo '<span class="dps-portal-breadcrumb__item">' . esc_html__( 'Portal do Cliente', 'dps-client-portal' ) . '</span>';
        echo '<span class="dps-portal-breadcrumb__separator" aria-hidden="true">â€º</span>';
        echo '<span class="dps-portal-breadcrumb__item dps-portal-breadcrumb__item--active" data-breadcrumb-active>' . esc_html__( 'InÃ­cio', 'dps-client-portal' ) . '</span>';
        
        echo '</nav>';
        
        // Define tabs padrÃ£o (Fase 2.3)
        $loyalty_badge = ! empty( $dashboard_snapshot['loyalty_points'] ) ? (int) $dashboard_snapshot['loyalty_points'] : 0;
        $default_tabs = [
            'inicio' => [
                'icon'  => 'ðŸ ',
                'label' => __( 'InÃ­cio', 'dps-client-portal' ),
                'active' => true,
                'badge' => 0,
            ],
            'fidelidade' => [
                'icon'  => 'ðŸ†',
                'label' => __( 'Fidelidade', 'dps-client-portal' ),
                'active' => false,
                'badge' => $loyalty_badge,
            ],
            'avaliacoes' => [
                'icon'  => 'â­',
                'label' => __( 'AvaliaÃ§Ãµes', 'dps-client-portal' ),
                'active' => false,
                'badge' => 0,
            ],
            'mensagens' => [
                'icon'  => 'ðŸ’¬',
                'label' => __( 'Mensagens', 'dps-client-portal' ),
                'active' => false,
                'badge' => (int) $dashboard_snapshot['unread_count'],
            ],
            'agendamentos' => [
                'icon'  => 'ðŸ“…',
                'label' => __( 'Agendamentos', 'dps-client-portal' ),
                'active' => false,
                'badge' => (int) $dashboard_snapshot['upcoming_count'],
            ],
            'pagamentos' => [
                'icon'  => 'ðŸ’³',
                'label' => __( 'Pagamentos', 'dps-client-portal' ),
                'active' => false,
                'badge' => (int) $dashboard_snapshot['pending_count'],
            ],
            'historico-pets' => [
                'icon'  => 'ðŸ¾',
                'label' => __( 'HistÃ³rico dos Pets', 'dps-client-portal' ),
                'active' => false,
                'badge' => 0,
            ],
            'galeria' => [
                'icon'  => 'ðŸ“¸',
                'label' => __( 'Galeria', 'dps-client-portal' ),
                'active' => false,
                'badge' => 0,
            ],
            'dados' => [
                'icon'  => 'âš™ï¸',
                'label' => __( 'Meus Dados', 'dps-client-portal' ),
                'active' => false,
                'badge' => 0,
            ],
        ];
        
        // Filtro: Permite add-ons modificarem tabs (Fase 2.3)
        $tabs = apply_filters( 'dps_portal_tabs', $default_tabs, $client_id );
        
        // NavegaÃ§Ã£o por Tabs com badges
        echo '<div class="dps-portal-tabs-wrapper">';
        echo '<nav class="dps-portal-tabs" role="tablist" aria-label="' . esc_attr__( 'SeÃ§Ãµes do portal', 'dps-client-portal' ) . '">';
        foreach ( $tabs as $tab_id => $tab ) {
            $is_active   = isset( $tab['active'] ) && $tab['active'];
            $is_disabled = ! empty( $tab['disabled'] );
            if ( $is_disabled ) {
                $is_active = false;
            }
            $class         = 'dps-portal-tabs__link' . ( $is_active ? ' is-active' : '' ) . ( $is_disabled ? ' is-disabled' : '' );
            $badge_count   = isset( $tab['badge'] ) ? absint( $tab['badge'] ) : 0;
            $tab_button_id = 'dps-portal-tab-' . sanitize_key( $tab_id );
            
            echo '<div class="dps-portal-tabs__item">';
            echo '<button type="button" id="' . esc_attr( $tab_button_id ) . '" class="' . esc_attr( $class ) . '" data-tab="' . esc_attr( $tab_id ) . '" role="tab" aria-selected="' . ( $is_active ? 'true' : 'false' ) . '" aria-controls="panel-' . esc_attr( $tab_id ) . '" aria-disabled="' . ( $is_disabled ? 'true' : 'false' ) . '" tabindex="' . ( $is_active ? '0' : '-1' ) . '"' . ( $is_disabled ? ' disabled' : '' ) . '>';
            if ( isset( $tab['icon'] ) ) {
                echo '<span class="dps-portal-tabs__icon">' . esc_html( $tab['icon'] ) . '</span>';
            }
            echo '<span class="dps-portal-tabs__text">' . esc_html( $tab['label'] ) . '</span>';
            
            // Badge de notificaÃ§Ã£o
            if ( $badge_count > 0 ) {
                $badge_class = 'dps-portal-tabs__badge';
                $badge_text  = $badge_count > 9 ? '9+' : (string) $badge_count;
                
                // Badge especial para fidelidade (pontos)
                if ( 'fidelidade' === $tab_id ) {
                    $badge_class .= ' dps-portal-tabs__badge--loyalty';
                    $badge_text   = $badge_count > 999 ? number_format( $badge_count / 1000, 1, ',', '' ) . 'k' : number_format( $badge_count, 0, ',', '.' );
                }
                
                // aria-label sempre com valor completo para acessibilidade
                $aria_label = ( 'fidelidade' === $tab_id )
                    ? sprintf( __( '%s pontos de fidelidade', 'dps-client-portal' ), number_format( $badge_count, 0, ',', '.' ) )
                    : sprintf( _n( '%d item', '%d itens', $badge_count, 'dps-client-portal' ), $badge_count );
                
                echo '<span class="' . esc_attr( $badge_class ) . '" aria-label="' . esc_attr( $aria_label ) . '">';
                echo esc_html( $badge_text );
                echo '</span>';
            }
            
            echo '</button>';
            echo '</div>';
        }
        echo '<span class="dps-portal-tabs__loading" aria-live="polite" aria-atomic="true"></span>';
        echo '</nav>';
        echo '</div>'; // .dps-portal-tabs-wrapper
        
        // Hook: Antes de renderizar conteÃºdo das tabs (Fase 2.3)
        do_action( 'dps_portal_before_tab_content', $client_id );
        
        // Container de conteÃºdo das tabs
        echo '<div class="dps-portal-tab-content">';
        
        // Panel: InÃ­cio (Layout modernizado)
        echo '<div id="panel-inicio" class="dps-portal-tab-panel is-active" role="tabpanel" aria-labelledby="dps-portal-tab-inicio" aria-hidden="false" tabindex="-1">';
        do_action( 'dps_portal_before_inicio_content', $client_id ); // Fase 2.3
        
        // Novo: Dashboard com mÃ©tricas rÃ¡pidas
        $this->render_portal_home_hero( $client_id, $dashboard_snapshot );
        $this->render_quick_overview( $client_id, $dashboard_snapshot );

        if ( class_exists( 'DPS_Game_Progress_Service' ) ) {
            echo '<section class="dps-portal-section dps-portal-game-summary" data-game-summary-state="idle">';
            echo '<div class="dps-portal-game-summary__header">';
            echo '<div>';
            echo '<h2>' . esc_html__( 'Space Groomers', 'dps-client-portal' ) . '</h2>';
            echo '<p class="dps-portal-game-summary__intro">' . esc_html__( 'Seu progresso sincronizado aparece aqui entre uma run e outra.', 'dps-client-portal' ) . '</p>';
            echo '</div>';
            echo '<span class="dps-portal-game-summary__pill">' . esc_html__( 'Sync ativo', 'dps-client-portal' ) . '</span>';
            echo '</div>';
            echo '<div class="dps-portal-game-summary__status">' . esc_html__( 'Carregando progresso do jogo...','dps-client-portal' ) . '</div>';
            echo '<div class="dps-portal-game-summary__grid">';
            echo '<article class="dps-portal-game-summary__card"><span class="dps-portal-game-summary__label">' . esc_html__( 'Missao atual', 'dps-client-portal' ) . '</span><strong class="dps-portal-game-summary__value" data-game-field="mission-title">-</strong><small data-game-field="mission-progress">-</small></article>';
            echo '<article class="dps-portal-game-summary__card"><span class="dps-portal-game-summary__label">' . esc_html__( 'Streak', 'dps-client-portal' ) . '</span><strong class="dps-portal-game-summary__value" data-game-field="streak">0</strong><small data-game-field="streak-note">-</small></article>';
            echo '<article class="dps-portal-game-summary__card"><span class="dps-portal-game-summary__label">' . esc_html__( 'Recorde', 'dps-client-portal' ) . '</span><strong class="dps-portal-game-summary__value" data-game-field="highscore">0</strong><small data-game-field="record-note">-</small></article>';
            echo '<article class="dps-portal-game-summary__card"><span class="dps-portal-game-summary__label">' . esc_html__( 'Badges', 'dps-client-portal' ) . '</span><strong class="dps-portal-game-summary__value" data-game-field="badges-count">0</strong><small data-game-field="badges-note">-</small></article>';
            echo '</div>';
            echo '<div class="dps-portal-game-summary__footer">';
            echo '<span data-game-field="last-run">-</span>';
            echo '<a href="#" class="dps-portal-game-summary__cta dps-link-button" data-portal-nav-target="inicio">' . esc_html__( 'Voltar ao jogo', 'dps-client-portal' ) . '</a>';
            echo '</div>';
            echo '</section>';
        }
        
        // Novo: AÃ§Ãµes rÃ¡pidas
        $this->render_quick_actions( $client_id, $dashboard_snapshot );
        
        // ConteÃºdo principal â€” layout vertical empilhado (single-column)
        echo '<div class="dps-inicio-stack">';
        
        // PrÃ³ximo agendamento (prioridade mÃ¡xima)
        DPS_Portal_Renderer::get_instance()->render_next_appointment( $client_id );
        
        // PendÃªncias financeiras (aÃ§Ã£o necessÃ¡ria)
        DPS_Portal_Renderer::get_instance()->render_financial_pending( $client_id );
        
        // SolicitaÃ§Ãµes recentes
        DPS_Portal_Renderer::get_instance()->render_recent_requests( $client_id );
        
        // Pets do cliente
        $this->render_pets_summary( $client_id );
        
        // SugestÃµes contextuais
        DPS_Portal_Renderer::get_instance()->render_contextual_suggestions( $client_id );
        
        echo '</div>'; // .dps-inicio-stack
        
        do_action( 'dps_portal_after_inicio_content', $client_id ); // Fase 2.3
        echo '</div>';

        // Panel: Fidelidade
        echo '<div id="panel-fidelidade" class="dps-portal-tab-panel" role="tabpanel" aria-labelledby="dps-portal-tab-fidelidade" aria-hidden="true" tabindex="-1">';
        do_action( 'dps_portal_before_fidelidade_content', $client_id );
        $this->render_loyalty_panel( $client_id );
        do_action( 'dps_portal_after_fidelidade_content', $client_id );
        echo '</div>';

        // Panel: AvaliaÃ§Ãµes (CTA + prova social)
        echo '<div id="panel-avaliacoes" class="dps-portal-tab-panel" role="tabpanel" aria-labelledby="dps-portal-tab-avaliacoes" aria-hidden="true" tabindex="-1">';
        do_action( 'dps_portal_before_reviews_content', $client_id );
        $this->render_reviews_hub( $client_id );
        do_action( 'dps_portal_after_reviews_content', $client_id );
        echo '</div>';
        
        // Panel: Mensagens (Fase 4 - continuaÃ§Ã£o)
        echo '<div id="panel-mensagens" class="dps-portal-tab-panel" role="tabpanel" aria-labelledby="dps-portal-tab-mensagens" aria-hidden="true" tabindex="-1">';
        do_action( 'dps_portal_before_mensagens_content', $client_id );
        DPS_Portal_Renderer::get_instance()->render_message_center( $client_id );
        do_action( 'dps_portal_after_mensagens_content', $client_id );
        echo '</div>';
        
        // Panel: Agendamentos (HistÃ³rico completo)
        echo '<div id="panel-agendamentos" class="dps-portal-tab-panel" role="tabpanel" aria-labelledby="dps-portal-tab-agendamentos" aria-hidden="true" tabindex="-1">';
        do_action( 'dps_portal_before_agendamentos_content', $client_id ); // Fase 2.3
        DPS_Portal_Renderer::get_instance()->render_appointment_history( $client_id );
        do_action( 'dps_portal_after_agendamentos_content', $client_id ); // Fase 2.3
        echo '</div>';
        
        // Panel: Pagamentos (Fase 5.5)
        echo '<div id="panel-pagamentos" class="dps-portal-tab-panel" role="tabpanel" aria-labelledby="dps-portal-tab-pagamentos" aria-hidden="true" tabindex="-1">';
        do_action( 'dps_portal_before_pagamentos_content', $client_id );
        DPS_Portal_Renderer::get_instance()->render_payments_tab( $client_id );
        do_action( 'dps_portal_after_pagamentos_content', $client_id );
        echo '</div>';
        
        // Panel: HistÃ³rico dos Pets (Fase 4)
        echo '<div id="panel-historico-pets" class="dps-portal-tab-panel" role="tabpanel" aria-labelledby="dps-portal-tab-historico-pets" aria-hidden="true" tabindex="-1">';
        do_action( 'dps_portal_before_pet_history_content', $client_id );
        $this->render_pets_timeline( $client_id );
        do_action( 'dps_portal_after_pet_history_content', $client_id );
        echo '</div>';
        
        // Panel: Galeria
        echo '<div id="panel-galeria" class="dps-portal-tab-panel" role="tabpanel" aria-labelledby="dps-portal-tab-galeria" aria-hidden="true" tabindex="-1">';
        do_action( 'dps_portal_before_galeria_content', $client_id ); // Fase 2.3
        DPS_Portal_Renderer::get_instance()->render_pet_gallery( $client_id );
        do_action( 'dps_portal_after_galeria_content', $client_id ); // Fase 2.3
        echo '</div>';
        
        // Panel: Meus Dados
        echo '<div id="panel-dados" class="dps-portal-tab-panel" role="tabpanel" aria-labelledby="dps-portal-tab-dados" aria-hidden="true" tabindex="-1">';
        do_action( 'dps_portal_before_dados_content', $client_id ); // Fase 2.3
        DPS_Portal_Renderer::get_instance()->render_update_forms( $client_id );
        $this->render_client_preferences( $client_id );
        do_action( 'dps_portal_after_dados_content', $client_id ); // Fase 2.3
        echo '</div>';
        
        // Hook: Permite add-ons renderizarem panels customizados (Fase 2.3)
        do_action( 'dps_portal_custom_tab_panels', $client_id, $tabs );
        
        echo '</div>'; // .dps-portal-tab-content

        // Hook para add-ons adicionarem conteÃºdo ao final do portal (ex: AI Assistant)
        do_action( 'dps_client_portal_after_content', $client_id );

        echo '</div>'; // .dps-client-portal
        
        // Widget de Chat flutuante
        DPS_Portal_Renderer::get_instance()->render_chat_widget( $client_id );
        
        return ob_get_clean();
    }

    /**
     * ObtÃ©m a URL configurada para avaliaÃ§Ãµes no Google.
     *
     * @return string
     */
    private function get_review_url() {
        $default_review_url = defined( 'DPS_PORTAL_REVIEW_URL' ) ? DPS_PORTAL_REVIEW_URL : '';
        $review_url         = get_option( 'dps_portal_review_url', $default_review_url );

        /**
         * Permite customizar o link de avaliaÃ§Ã£o exibido no portal.
         *
         * @param string $review_url URL configurada.
         */
        $review_url = apply_filters( 'dps_portal_review_url', $review_url );

        return $review_url ? (string) $review_url : '';
    }

    /**
     * Busca avaliaÃ§Ãµes internas para exibir prova social no portal.
     *
     * @param int $limit Quantidade de avaliaÃ§Ãµes a exibir.
     * @return array
     */
    private function get_reviews_summary( $limit = 3 ) {
        if ( ! post_type_exists( 'dps_groomer_review' ) ) {
            return [
                'average' => 0,
                'count'   => 0,
                'items'   => [],
            ];
        }

        // Busca contagem e mÃ©dia reais de todas as avaliaÃ§Ãµes
        $all_ids = get_posts( [
            'post_type'      => 'dps_groomer_review',
            'post_status'    => 'publish',
            'posts_per_page' => 200,
            'fields'         => 'ids',
        ] );

        $total_rating = 0;
        $rated_count  = 0;

        foreach ( $all_ids as $review_id ) {
            $rating = (int) get_post_meta( $review_id, '_dps_review_rating', true );
            $rating = max( 0, min( 5, $rating ) );
            if ( $rating > 0 ) {
                $total_rating += $rating;
                $rated_count++;
            }
        }

        $average = $rated_count > 0 ? round( $total_rating / $rated_count, 1 ) : 0;

        // Busca os itens mais recentes para exibiÃ§Ã£o
        $reviews = get_posts( [
            'post_type'      => 'dps_groomer_review',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );

        $items = [];

        foreach ( $reviews as $review ) {
            $item_rating = (int) get_post_meta( $review->ID, '_dps_review_rating', true );
            $item_rating = max( 0, min( 5, $item_rating ) );

            $items[] = [
                'rating'  => $item_rating,
                'author'  => get_post_meta( $review->ID, '_dps_review_name', true ),
                'date'    => get_the_date( get_option( 'date_format', 'd/m/Y' ), $review ),
                'content' => $review->post_content,
            ];
        }

        return [
            'average' => $average,
            'count'   => $rated_count,
            'items'   => $items,
        ];
    }

    /**
     * Retorna marcaÃ§Ã£o de estrelas acessÃ­vel.
     *
     * @param float  $rating     Nota de 0 a 5.
     * @param string $aria_label Texto descritivo para leitores de tela.
     * @return string HTML das estrelas.
     */
    private function render_star_icons( $rating, $aria_label = '' ) {
        $rounded = max( 0, min( 5, (int) round( $rating ) ) );
        $filled  = str_repeat( 'â˜…', $rounded );
        $empty   = str_repeat( 'â˜†', 5 - $rounded );
        $label   = $aria_label ? ' aria-label="' . esc_attr( $aria_label ) . '"' : '';

        return '<span class="dps-stars"' . $label . '>' . esc_html( $filled . $empty ) . '</span>';
    }

    /**
     * Monta um snapshot do dashboard para a home do portal.
     *
     * @param int $client_id ID do cliente.
     * @return array<string, mixed>
     */

    private function get_portal_dashboard_snapshot( $client_id ) {
        $data_provider          = DPS_Portal_Data_Provider::get_instance();
        $appointment_repository = DPS_Appointment_Repository::get_instance();
        $finance_repository     = DPS_Finance_Repository::get_instance();
        $pet_repository         = DPS_Pet_Repository::get_instance();
        $pets                   = $pet_repository->get_pets_by_client( $client_id );
        $next_appointment       = $appointment_repository->get_next_appointment_for_client( $client_id );
        $financial_summary      = $finance_repository->get_client_financial_summary( $client_id );
        $pet_names              = [];

        foreach ( array_slice( $pets, 0, 3 ) as $pet ) {
            if ( $pet instanceof WP_Post ) {
                $pet_names[] = $pet->post_title;
            }
        }

        return [
            'upcoming_count'   => (int) $data_provider->count_upcoming_appointments( $client_id ),
            'pets_count'       => count( $pets ),
            'pet_names'        => $pet_names,
            'unread_count'     => (int) $data_provider->get_unread_messages_count( $client_id ),
            'pending_count'    => (int) $data_provider->count_financial_pending( $client_id ),
            'pending_total'    => is_array( $financial_summary ) && isset( $financial_summary['total_pending'] ) ? (float) $financial_summary['total_pending'] : 0.0,
            'next_appointment' => $next_appointment,
            'next_summary'     => $this->get_portal_next_appointment_summary( $next_appointment ),
            'loyalty_enabled'  => function_exists( 'dps_loyalty_get_points' ),
            'loyalty_points'   => function_exists( 'dps_loyalty_get_points' ) ? max( 0, (int) dps_loyalty_get_points( $client_id ) ) : 0,
        ];
    }

    /**
     * Resume o proximo agendamento para a home do portal.
     *
     * @param WP_Post|null $appointment Agendamento futuro.
     * @return array<string, string>
     */
    private function get_portal_next_appointment_summary( $appointment ) {
        if ( ! $appointment instanceof WP_Post ) {
            return [
                'title'    => __( 'Nenhum atendimento reservado', 'dps-client-portal' ),
                'detail'   => __( 'Use os atalhos abaixo para pedir um novo horario.', 'dps-client-portal' ),
                'pet_name' => '',
                'service'  => '',
            ];
        }

        $date     = (string) get_post_meta( $appointment->ID, 'appointment_date', true );
        $time     = (string) get_post_meta( $appointment->ID, 'appointment_time', true );
        $pet_id   = (int) get_post_meta( $appointment->ID, 'appointment_pet_id', true );
        $pet_name = $pet_id > 0 ? get_the_title( $pet_id ) : '';
        $services = get_post_meta( $appointment->ID, 'appointment_services', true );
        $service  = __( 'Banho e tosa', 'dps-client-portal' );

        if ( is_array( $services ) && ! empty( $services ) ) {
            $service = implode( ', ', array_map( 'sanitize_text_field', $services ) );
        } elseif ( is_string( $services ) && '' !== trim( $services ) ) {
            $service = sanitize_text_field( $services );
        }

        $detail_parts = [];
        if ( $date ) {
            $detail_parts[] = $this->get_portal_relative_date_label( $date );
        }

        if ( $time ) {
            $detail_parts[] = sprintf( __( 'as %s', 'dps-client-portal' ), $time );
        }

        if ( $pet_name ) {
            $detail_parts[] = $pet_name;
        }

        return [
            'title'    => $pet_name ? sprintf( __( 'Proximo cuidado de %s', 'dps-client-portal' ), $pet_name ) : __( 'Proximo cuidado', 'dps-client-portal' ),
            'detail'   => ! empty( $detail_parts ) ? implode( ' - ', $detail_parts ) : __( 'Data a confirmar', 'dps-client-portal' ),
            'pet_name' => $pet_name,
            'service'  => $service,
        ];
    }

    /**
     * Converte uma data em um rotulo relativo curto.
     *
     * @param string $date Data no formato Y-m-d.
     * @return string
     */
    private function get_portal_relative_date_label( $date ) {
        $target_timestamp = strtotime( $date . ' 00:00:00' );
        if ( false === $target_timestamp ) {
            return __( 'Data a confirmar', 'dps-client-portal' );
        }

        $current_timestamp = current_time( 'timestamp' );
        $today_timestamp   = strtotime( wp_date( 'Y-m-d 00:00:00', $current_timestamp ) );
        $diff_days         = (int) round( ( $target_timestamp - $today_timestamp ) / DAY_IN_SECONDS );

        if ( 0 === $diff_days ) {
            return __( 'Hoje', 'dps-client-portal' );
        }

        if ( 1 === $diff_days ) {
            return __( 'Amanha', 'dps-client-portal' );
        }

        if ( $diff_days > 1 && $diff_days <= 7 ) {
            return sprintf( __( 'Em %d dias', 'dps-client-portal' ), $diff_days );
        }

        return wp_date( get_option( 'date_format' ), $target_timestamp );
    }

    /**
     * Renderiza o hero contextual da home do portal.
     *
     * @param int   $client_id ID do cliente.
     * @param array $snapshot  Snapshot do dashboard.
     * @return void
     */
    private function render_portal_home_hero( $client_id, array $snapshot ) {
        $client_name         = get_the_title( $client_id );
        $next_summary        = isset( $snapshot['next_summary'] ) && is_array( $snapshot['next_summary'] ) ? $snapshot['next_summary'] : [];
        $has_upcoming        = ! empty( $snapshot['upcoming_count'] );
        $has_pending         = ! empty( $snapshot['pending_count'] );
        $has_unread          = ! empty( $snapshot['unread_count'] );
        $pending_total_label = DPS_Money_Helper::format_currency_from_decimal( (float) $snapshot['pending_total'] );
        $whatsapp_url        = $this->get_portal_whatsapp_link( __( 'Ola! Gostaria de agendar um servico.', 'dps-client-portal' ) );

        if ( $has_upcoming ) {
            $hero_title = __( 'Seu proximo cuidado esta organizado', 'dps-client-portal' );
            $hero_lead  = sprintf( __( '%1$s esta previsto para %2$s. Use esta area para acompanhar ajustes, pagamentos e mensagens em um unico lugar.', 'dps-client-portal' ), $next_summary['service'] ?? __( 'O atendimento', 'dps-client-portal' ), $next_summary['detail'] ?? __( 'um novo horario', 'dps-client-portal' ) );
        } elseif ( ! empty( $snapshot['pets_count'] ) ) {
            $hero_title = __( 'Tudo pronto para o proximo atendimento', 'dps-client-portal' );
            $hero_lead  = __( 'Com o portal voce revisa agenda, conversa com a equipe e atualiza dados sem depender do WhatsApp para tudo.', 'dps-client-portal' );
        } else {
            $hero_title = __( 'Vamos preparar o primeiro cuidado do seu pet', 'dps-client-portal' );
            $hero_lead  = __( 'Assim que o cadastro estiver completo, o portal vira sua base para agenda, historico, mensagens e pagamentos.', 'dps-client-portal' );
        }

        if ( $has_unread ) {
            $hero_lead .= ' ' . sprintf( _n( 'Ha %d mensagem nova aguardando voce.', 'Ha %d mensagens novas aguardando voce.', (int) $snapshot['unread_count'], 'dps-client-portal' ), (int) $snapshot['unread_count'] );
        } elseif ( $has_pending ) {
            $hero_lead .= ' ' . sprintf( __( 'Existe %s em aberto para revisar.', 'dps-client-portal' ), $pending_total_label );
        }

        echo '<section class="dps-portal-home-hero" aria-labelledby="dps-portal-home-title">';
        echo '<div class="dps-portal-home-hero__main">';
        echo '<span class="dps-portal-home-hero__eyebrow">' . esc_html__( 'Central do cliente', 'dps-client-portal' ) . '</span>';
        echo '<h2 id="dps-portal-home-title" class="dps-portal-home-hero__title">' . esc_html( $hero_title ) . '</h2>';
        echo '<p class="dps-portal-home-hero__lead">' . esc_html( $hero_lead ) . '</p>';

        echo '<div class="dps-portal-home-hero__chips">';
        echo '<span class="dps-portal-home-hero__chip">' . esc_html( sprintf( _n( '%d pet cadastrado', '%d pets cadastrados', (int) $snapshot['pets_count'], 'dps-client-portal' ), (int) $snapshot['pets_count'] ) ) . '</span>';
        echo '<span class="dps-portal-home-hero__chip' . ( $has_upcoming ? ' is-info' : '' ) . '">' . esc_html( $has_upcoming ? ( $next_summary['detail'] ?? __( 'Horario em breve', 'dps-client-portal' ) ) : __( 'Sem agenda futura', 'dps-client-portal' ) ) . '</span>';
        echo '<span class="dps-portal-home-hero__chip' . ( $has_pending ? ' is-warning' : ' is-success' ) . '">' . esc_html( $has_pending ? sprintf( __( '%s em aberto', 'dps-client-portal' ), $pending_total_label ) : __( 'Financeiro em dia', 'dps-client-portal' ) ) . '</span>';
        echo '<span class="dps-portal-home-hero__chip' . ( $has_unread ? ' is-info' : '' ) . '">' . esc_html( $has_unread ? sprintf( _n( '%d mensagem nova', '%d mensagens novas', (int) $snapshot['unread_count'], 'dps-client-portal' ), (int) $snapshot['unread_count'] ) : __( 'Canal aberto com a equipe', 'dps-client-portal' ) ) . '</span>';
        if ( ! empty( $snapshot['loyalty_enabled'] ) ) {
            echo '<span class="dps-portal-home-hero__chip is-primary">' . esc_html( sprintf( __( '%s pontos de fidelidade', 'dps-client-portal' ), number_format( (int) $snapshot['loyalty_points'], 0, ',', '.' ) ) ) . '</span>';
        }
        echo '</div>';

        echo '<div class="dps-portal-home-hero__actions">';
        if ( $has_upcoming ) {
            echo '<button type="button" class="dps-portal-home-hero__action dps-portal-home-hero__action--primary" data-portal-nav-target="agendamentos">' . esc_html__( 'Ver proximo atendimento', 'dps-client-portal' ) . '</button>';
        } elseif ( ! empty( $whatsapp_url ) ) {
            echo '<a href="' . esc_url( $whatsapp_url ) . '" target="_blank" rel="noopener noreferrer" class="dps-portal-home-hero__action dps-portal-home-hero__action--primary">' . esc_html__( 'Agendar banho ou tosa', 'dps-client-portal' ) . '</a>';
        } else {
            echo '<button type="button" class="dps-portal-home-hero__action dps-portal-home-hero__action--primary" data-action="open-chat">' . esc_html__( 'Falar com a equipe', 'dps-client-portal' ) . '</button>';
        }

        if ( $has_unread ) {
            echo '<button type="button" class="dps-portal-home-hero__action" data-portal-nav-target="mensagens">' . esc_html__( 'Abrir mensagens', 'dps-client-portal' ) . '</button>';
        } else {
            echo '<button type="button" class="dps-portal-home-hero__action" data-portal-nav-target="dados">' . esc_html__( 'Atualizar meus dados', 'dps-client-portal' ) . '</button>';
        }
        echo '</div>';
        echo '</div>';

        echo '<aside class="dps-portal-home-hero__aside" aria-label="' . esc_attr__( 'Status rapido do portal', 'dps-client-portal' ) . '">';
        echo '<span class="dps-portal-home-hero__eyebrow">' . esc_html__( 'Hoje no portal', 'dps-client-portal' ) . '</span>';
        echo '<div class="dps-portal-home-status-list">';
        $this->render_portal_home_status_item( __( 'Agenda', 'dps-client-portal' ), $has_upcoming ? ( $next_summary['detail'] ?? __( 'Em acompanhamento', 'dps-client-portal' ) ) : __( 'Sem horario reservado', 'dps-client-portal' ), $has_upcoming ? 'info' : 'neutral' );
        $this->render_portal_home_status_item( __( 'Pagamentos', 'dps-client-portal' ), $has_pending ? sprintf( __( '%s em aberto', 'dps-client-portal' ), $pending_total_label ) : __( 'Nenhuma pendencia', 'dps-client-portal' ), $has_pending ? 'warning' : 'success' );
        $this->render_portal_home_status_item( __( 'Mensagens', 'dps-client-portal' ), $has_unread ? sprintf( _n( '%d nova conversa', '%d novas conversas', (int) $snapshot['unread_count'], 'dps-client-portal' ), (int) $snapshot['unread_count'] ) : __( 'Sem novas mensagens', 'dps-client-portal' ), $has_unread ? 'info' : 'success' );
        $this->render_portal_home_status_item( __( 'Perfil', 'dps-client-portal' ), $client_name ? sprintf( __( 'Conta ativa de %s', 'dps-client-portal' ), $client_name ) : __( 'Conta pronta para uso', 'dps-client-portal' ), 'neutral' );
        echo '</div>';
        echo '</aside>';
        echo '</section>';
    }

    /**
     * Renderiza um item da lista lateral de status.
     *
     * @param string $label Rotulo.
     * @param string $value Valor.
     * @param string $tone  Tom visual.
     * @return void
     */
    private function render_portal_home_status_item( $label, $value, $tone = 'neutral' ) {
        $tone_label_map = [
            'success' => __( 'Em dia', 'dps-client-portal' ),
            'warning' => __( 'Revisar', 'dps-client-portal' ),
            'info'    => __( 'Acompanhar', 'dps-client-portal' ),
            'neutral' => __( 'Disponivel', 'dps-client-portal' ),
        ];
        $tone_class = isset( $tone_label_map[ $tone ] ) ? $tone : 'neutral';

        echo '<article class="dps-portal-home-status dps-portal-home-status--' . esc_attr( $tone_class ) . '">';
        echo '<div class="dps-portal-home-status__copy">';
        echo '<span class="dps-portal-home-status__label">' . esc_html( $label ) . '</span>';
        echo '<strong class="dps-portal-home-status__value">' . esc_html( $value ) . '</strong>';
        echo '</div>';
        echo '<span class="dps-portal-home-status__tone dps-portal-home-status__tone--' . esc_attr( $tone_class ) . '">' . esc_html( $tone_label_map[ $tone_class ] ) . '</span>';
        echo '</article>';
    }

    /**
     * Renderiza cards acionaveis de overview para a home do portal.
     *
     * @param int   $client_id ID do cliente.
     * @param array $snapshot  Snapshot do dashboard.
     * @return void
     */
    private function render_quick_overview( $client_id, array $snapshot = [] ) {
        if ( empty( $snapshot ) ) {
            $snapshot = $this->get_portal_dashboard_snapshot( $client_id );
        }

        $next_summary    = isset( $snapshot['next_summary'] ) && is_array( $snapshot['next_summary'] ) ? $snapshot['next_summary'] : [];
        $pets_support    = ! empty( $snapshot['pet_names'] ) ? implode( ', ', $snapshot['pet_names'] ) : __( 'Cadastre o primeiro pet para liberar historico e agenda.', 'dps-client-portal' );
        $message_support = ! empty( $snapshot['unread_count'] ) ? __( 'Abra a central para acompanhar respostas da equipe.', 'dps-client-portal' ) : __( 'Sem novidades. O chat continua disponivel quando voce precisar.', 'dps-client-portal' );
        $payment_support = ! empty( $snapshot['pending_count'] ) ? sprintf( __( '%s aguardando revisao.', 'dps-client-portal' ), DPS_Money_Helper::format_currency_from_decimal( (float) $snapshot['pending_total'] ) ) : __( 'Tudo em dia no financeiro.', 'dps-client-portal' );

        echo '<section class="dps-portal-overview" aria-labelledby="dps-portal-overview-title">';
        echo '<div class="dps-portal-overview__header">';
        echo '<div>';
        echo '<h2 id="dps-portal-overview-title" class="dps-portal-overview__title">' . esc_html__( 'Visao rapida do portal', 'dps-client-portal' ) . '</h2>';
        echo '<p class="dps-portal-overview__description">' . esc_html__( 'Cards acionaveis para chegar mais rapido na area certa.', 'dps-client-portal' ) . '</p>';
        echo '</div>';
        echo '</div>';

        echo '<div class="dps-overview-cards">';
        $this->render_overview_card( [
            'modifier'    => 'appointments',
            'icon'        => '&#128197;',
            'value'       => number_format( (int) $snapshot['upcoming_count'], 0, ',', '.' ),
            'label'       => __( 'Agendamentos ativos', 'dps-client-portal' ),
            'support'     => ! empty( $snapshot['upcoming_count'] ) ? ( $next_summary['detail'] ?? __( 'Proximo atendimento em breve.', 'dps-client-portal' ) ) : __( 'Nenhum horario reservado no momento.', 'dps-client-portal' ),
            'target'      => 'agendamentos',
            'extra_class' => '',
        ] );
        $this->render_overview_card( [
            'modifier'    => 'pets',
            'icon'        => '&#128062;',
            'value'       => number_format( (int) $snapshot['pets_count'], 0, ',', '.' ),
            'label'       => __( 'Pets cadastrados', 'dps-client-portal' ),
            'support'     => $pets_support,
            'target'      => 'historico-pets',
            'extra_class' => '',
        ] );
        $this->render_overview_card( [
            'modifier'    => 'messages',
            'icon'        => '&#128172;',
            'value'       => number_format( (int) $snapshot['unread_count'], 0, ',', '.' ),
            'label'       => __( 'Mensagens novas', 'dps-client-portal' ),
            'support'     => $message_support,
            'target'      => 'mensagens',
            'extra_class' => ! empty( $snapshot['unread_count'] ) ? 'dps-overview-card--has-badge' : '',
        ] );
        $this->render_overview_card( [
            'modifier'    => 'payments',
            'icon'        => '&#128179;',
            'value'       => ! empty( $snapshot['pending_count'] ) ? number_format( (int) $snapshot['pending_count'], 0, ',', '.' ) : __( 'OK', 'dps-client-portal' ),
            'label'       => __( 'Pendencias financeiras', 'dps-client-portal' ),
            'support'     => $payment_support,
            'target'      => 'pagamentos',
            'extra_class' => ! empty( $snapshot['pending_count'] ) ? 'dps-overview-card--alert' : '',
        ] );

        if ( ! empty( $snapshot['loyalty_enabled'] ) ) {
            $this->render_overview_card( [
                'modifier'    => 'loyalty',
                'icon'        => '&#127942;',
                'value'       => number_format( (int) $snapshot['loyalty_points'], 0, ',', '.' ),
                'label'       => __( 'Pontos de fidelidade', 'dps-client-portal' ),
                'support'     => __( 'Abra o programa para acompanhar beneficios, indicacoes e resgates.', 'dps-client-portal' ),
                'target'      => 'fidelidade',
                'extra_class' => '',
            ] );
        }

        echo '</div>';
        echo '</section>';
    }

    /**
     * Renderiza um card acionavel da home do portal.
     *
     * @param array<string, string> $args Dados do card.
     * @return void
     */
    private function render_overview_card( array $args ) {
        $modifier    = isset( $args['modifier'] ) ? sanitize_html_class( (string) $args['modifier'] ) : 'generic';
        $icon        = isset( $args['icon'] ) ? (string) $args['icon'] : '&#8226;';
        $value       = isset( $args['value'] ) ? (string) $args['value'] : '0';
        $label       = isset( $args['label'] ) ? (string) $args['label'] : '';
        $support     = isset( $args['support'] ) ? (string) $args['support'] : '';
        $target      = isset( $args['target'] ) ? (string) $args['target'] : 'inicio';
        $extra_class = isset( $args['extra_class'] ) ? (string) $args['extra_class'] : '';
        $class       = trim( 'dps-overview-card dps-overview-card--' . $modifier . ' ' . $extra_class );

        echo '<button type="button" class="' . esc_attr( $class ) . '" data-portal-nav-target="' . esc_attr( $target ) . '" aria-label="' . esc_attr( $label ) . '">';
        echo '<span class="dps-overview-card__icon" aria-hidden="true">' . wp_kses_post( $icon ) . '</span>';
        echo '<span class="dps-overview-card__content">';
        echo '<span class="dps-overview-card__value">' . esc_html( $value ) . '</span>';
        echo '<span class="dps-overview-card__label">' . esc_html( $label ) . '</span>';
        echo '<span class="dps-overview-card__support">' . esc_html( $support ) . '</span>';
        echo '</span>';
        echo '</button>';
    }

    /**
     * Renderiza atalhos rapidos para o cliente.
     *
     * @since 3.0.0
     * @param int   $client_id ID do cliente.
     * @param array $snapshot  Snapshot do dashboard.
     */
    private function render_quick_actions( $client_id, array $snapshot = [] ) {
        if ( empty( $snapshot ) ) {
            $snapshot = $this->get_portal_dashboard_snapshot( $client_id );
        }

        $whatsapp_url = $this->get_portal_whatsapp_link( __( 'Ola! Gostaria de agendar um servico.', 'dps-client-portal' ) );
        $review_url   = $this->get_review_url();
        $description  = ! empty( $snapshot['upcoming_count'] )
            ? __( 'Ajuste o atendimento, fale com a equipe e mantenha o cadastro em ordem com poucos toques.', 'dps-client-portal' )
            : __( 'Escolha o atalho mais rapido para continuar sua jornada no portal.', 'dps-client-portal' );

        echo '<section class="dps-portal-quick-actions" aria-labelledby="dps-portal-quick-actions-title">';
        echo '<div class="dps-portal-quick-actions__header">';
        echo '<h2 id="dps-portal-quick-actions-title" class="dps-portal-quick-actions__title">' . esc_html__( 'Atalhos rapidos', 'dps-client-portal' ) . '</h2>';
        echo '<p class="dps-portal-quick-actions__description">' . esc_html( $description ) . '</p>';
        echo '</div>';

        echo '<div class="dps-quick-actions">';
        if ( ! empty( $whatsapp_url ) ) {
            echo '<a href="' . esc_url( $whatsapp_url ) . '" target="_blank" rel="noopener noreferrer" class="dps-quick-action dps-quick-action--primary">';
            echo '<span class="dps-quick-action__icon">&#128197;</span>';
            echo '<span class="dps-quick-action__text">' . esc_html__( 'Agendar servico', 'dps-client-portal' ) . '</span>';
            echo '</a>';
        }

        echo '<button type="button" class="dps-quick-action dps-quick-action--chat" data-action="open-chat">';
        echo '<span class="dps-quick-action__icon">&#128172;</span>';
        echo '<span class="dps-quick-action__text">' . esc_html__( 'Falar conosco', 'dps-client-portal' ) . '</span>';
        echo '</button>';

        if ( ! empty( $snapshot['unread_count'] ) ) {
            echo '<button type="button" class="dps-quick-action" data-portal-nav-target="mensagens">';
            echo '<span class="dps-quick-action__icon">&#9993;</span>';
            echo '<span class="dps-quick-action__text">' . esc_html__( 'Abrir mensagens', 'dps-client-portal' ) . '</span>';
            echo '</button>';
        }

        if ( $review_url ) {
            echo '<a href="' . esc_url( $review_url ) . '" target="_blank" rel="noopener noreferrer" class="dps-quick-action">';
            echo '<span class="dps-quick-action__icon">&#11088;</span>';
            echo '<span class="dps-quick-action__text">' . esc_html__( 'Avaliar atendimento', 'dps-client-portal' ) . '</span>';
            echo '</a>';
        }

        if ( function_exists( 'dps_loyalty_get_referral_code' ) ) {
            echo '<button type="button" class="dps-quick-action dps-quick-action--referral" data-portal-nav-target="fidelidade">';
            echo '<span class="dps-quick-action__icon">&#127873;</span>';
            echo '<span class="dps-quick-action__text">' . esc_html__( 'Indique e ganhe', 'dps-client-portal' ) . '</span>';
            echo '</button>';
        }

        echo '<button type="button" class="dps-quick-action" data-portal-nav-target="dados">';
        echo '<span class="dps-quick-action__icon">&#9881;</span>';
        echo '<span class="dps-quick-action__text">' . esc_html__( 'Meus dados', 'dps-client-portal' ) . '</span>';
        echo '</button>';
        echo '</div>';
        echo '</section>';
    }
    const PETS_SUMMARY_LIMIT = 6;

    /**
     * Renderiza um resumo visual dos pets do cliente.
     * Mostra foto, nome e prÃ³ximo agendamento de cada pet.
     *
     * @since 3.0.0
     * @param int $client_id ID do cliente.
     */
    private function render_pets_summary( $client_id ) {
        // Busca pets do cliente
        $pets = get_posts( [
            'post_type'      => 'dps_pet',
            'post_status'    => 'publish',
            'posts_per_page' => self::PETS_SUMMARY_LIMIT,
            'meta_key'       => 'owner_id',
            'meta_value'     => $client_id,
        ] );
        
        if ( empty( $pets ) ) {
            return;
        }
        
        // Pre-load meta cache
        $pet_ids = wp_list_pluck( $pets, 'ID' );
        update_meta_cache( 'post', $pet_ids );
        
        // Pre-fetch prÃ³ximos agendamentos de todos os pets em uma Ãºnica query
        $today = current_time( 'Y-m-d' );
        $all_future_appts = get_posts( [
            'post_type'      => 'dps_agendamento',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => 'appointment_pet_id',
                    'value'   => $pet_ids,
                    'compare' => 'IN',
                ],
                [
                    'key'     => 'appointment_date',
                    'value'   => $today,
                    'compare' => '>=',
                    'type'    => 'DATE',
                ],
            ],
            'orderby'  => 'meta_value',
            'meta_key' => 'appointment_date',
            'order'    => 'ASC',
        ] );
        
        // Pre-fetch Ãºltimos agendamentos finalizados de todos os pets
        $all_past_appts = get_posts( [
            'post_type'      => 'dps_agendamento',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => 'appointment_pet_id',
                    'value'   => $pet_ids,
                    'compare' => 'IN',
                ],
                [
                    'key'     => 'appointment_status',
                    'value'   => [ 'finalizado', 'finalizado e pago', 'finalizado_pago' ],
                    'compare' => 'IN',
                ],
            ],
            'orderby'  => 'meta_value',
            'meta_key' => 'appointment_date',
            'order'    => 'DESC',
        ] );
        
        // Indexar por pet_id para acesso rÃ¡pido
        $next_by_pet = [];
        $cancelled = [ 'finalizado', 'finalizado e pago', 'finalizado_pago', 'cancelado' ];
        foreach ( $all_future_appts as $appt ) {
            $appt_pet_id = get_post_meta( $appt->ID, 'appointment_pet_id', true );
            $appt_status = get_post_meta( $appt->ID, 'appointment_status', true );
            if ( ! isset( $next_by_pet[ $appt_pet_id ] ) && ! in_array( $appt_status, $cancelled, true ) ) {
                $next_by_pet[ $appt_pet_id ] = get_post_meta( $appt->ID, 'appointment_date', true );
            }
        }
        
        $last_by_pet = [];
        foreach ( $all_past_appts as $appt ) {
            $appt_pet_id = get_post_meta( $appt->ID, 'appointment_pet_id', true );
            if ( ! isset( $last_by_pet[ $appt_pet_id ] ) ) {
                $last_by_pet[ $appt_pet_id ] = get_post_meta( $appt->ID, 'appointment_date', true );
            }
        }
        
        echo '<section class="dps-portal-section dps-portal-pets-summary">';
        echo '<div class="dps-section-header">';
        echo '<h2>ðŸ¾ ' . esc_html__( 'Meus Pets', 'dps-client-portal' ) . '</h2>';
        echo '<button type="button" class="dps-link-button" data-tab="historico-pets">' . esc_html__( 'Ver HistÃ³rico Completo â†’', 'dps-client-portal' ) . '</button>';
        echo '</div>';
        
        echo '<div class="dps-pets-cards">';
        
        foreach ( $pets as $pet ) {
            $photo_id = get_post_meta( $pet->ID, 'pet_photo_id', true );
            $species  = get_post_meta( $pet->ID, 'pet_species', true );
            $breed    = get_post_meta( $pet->ID, 'pet_breed', true );
            
            // Usa dados prÃ©-carregados
            $next_date = isset( $next_by_pet[ $pet->ID ] ) ? $next_by_pet[ $pet->ID ] : '';
            $last_date = isset( $last_by_pet[ $pet->ID ] ) ? $last_by_pet[ $pet->ID ] : '';
            
            echo '<div class="dps-pet-card">';
            
            // Foto do pet (sÃ³ renderiza se houver foto)
            if ( $photo_id ) {
                $photo_url = wp_get_attachment_image_url( $photo_id, 'thumbnail' );
                if ( $photo_url ) {
                    echo '<div class="dps-pet-card__photo">';
                    echo '<img src="' . esc_url( $photo_url ) . '" alt="' . esc_attr( $pet->post_title ) . '" loading="lazy">';
                    echo '</div>';
                }
            }
            
            // InformaÃ§Ãµes do pet
            echo '<div class="dps-pet-card__info">';
            echo '<h3 class="dps-pet-card__name">' . esc_html( $pet->post_title ) . '</h3>';
            
            if ( $breed || $species ) {
                echo '<span class="dps-pet-card__breed">';
                if ( $breed ) {
                    echo esc_html( $breed );
                }
                if ( $breed && $species ) {
                    echo ' â€¢ ';
                }
                if ( $species ) {
                    echo esc_html( ucfirst( $species ) );
                }
                echo '</span>';
            }
            
            // PrÃ³ximo agendamento do pet
            if ( $next_date ) {
                echo '<span class="dps-pet-card__next-appointment">ðŸ“… ';
                echo esc_html( sprintf(
                    /* translators: %s: formatted date */
                    __( 'PrÃ³ximo: %s', 'dps-client-portal' ),
                    date_i18n( 'd/m', strtotime( $next_date ) )
                ) );
                echo '</span>';
            } elseif ( $last_date ) {
                $days_ago = floor( ( time() - strtotime( $last_date ) ) / DAY_IN_SECONDS );
                if ( $days_ago <= 1 ) {
                    $date_text = __( 'Ãšltimo: Hoje', 'dps-client-portal' );
                } elseif ( $days_ago <= 7 ) {
                    $date_text = sprintf(
                        /* translators: %d: number of days */
                        _n( 'Ãšltimo: %d dia atrÃ¡s', 'Ãšltimo: %d dias atrÃ¡s', $days_ago, 'dps-client-portal' ),
                        $days_ago
                    );
                } else {
                    $date_text = sprintf(
                        /* translators: %s: formatted date */
                        __( 'Ãšltimo: %s', 'dps-client-portal' ),
                        date_i18n( 'd/m', strtotime( $last_date ) )
                    );
                }
                echo '<span class="dps-pet-card__last-service">' . esc_html( $date_text ) . '</span>';
            } else {
                echo '<span class="dps-pet-card__last-service dps-pet-card__last-service--empty">' . esc_html__( 'Ainda nÃ£o atendido', 'dps-client-portal' ) . '</span>';
            }
            
            echo '</div>'; // .dps-pet-card__info
            
            // AÃ§Ãµes rÃ¡pidas do pet
            echo '<div class="dps-pet-card__actions">';
            echo '<button type="button" class="dps-pet-card__action-btn" data-tab="historico-pets" aria-label="' . esc_attr__( 'Ver histÃ³rico', 'dps-client-portal' ) . '" title="' . esc_attr__( 'Ver histÃ³rico', 'dps-client-portal' ) . '"><span aria-hidden="true">ðŸ“‹</span></button>';
            echo '</div>';
            
            echo '</div>'; // .dps-pet-card
        }
        
        echo '</div>'; // .dps-pets-cards
        echo '</section>';
    }

    /**
     * Renderiza a central de avaliaÃ§Ãµes (CTA + prova social).
     *
     * Layout moderno com:
     * - SeÃ§Ã£o de destaque com mÃ©tricas visuais
     * - FormulÃ¡rio de avaliaÃ§Ã£o rÃ¡pida interna
     * - CTA para Google Reviews
     * - Galeria de avaliaÃ§Ãµes com prova social
     *
     * @param int $client_id ID do cliente autenticado.
     */
    private function render_reviews_hub( $client_id ) {
        $review_url      = $this->get_review_url();
        $summary         = $this->get_reviews_summary( 6 );
        $client_reviewed = $this->has_client_reviewed( $client_id );
        $client_name     = get_the_title( $client_id );

        echo '<section class="dps-portal-section dps-portal-reviews">';
        echo '<h2 class="dps-section-title"><span class="dps-section-title__icon">â­</span>' . esc_html__( 'Central de AvaliaÃ§Ãµes', 'dps-client-portal' ) . '</h2>';
        echo '<p class="dps-section-subtitle">' . esc_html__( 'Sua opiniÃ£o nos ajuda a melhorar cada vez mais!', 'dps-client-portal' ) . '</p>';

        // Card de mÃ©tricas resumidas
        echo '<div class="dps-reviews-metrics">';
        $this->render_reviews_metrics_cards( $summary );
        echo '</div>';

        // Layout em duas colunas: formulÃ¡rio + CTA Google
        echo '<div class="dps-reviews-grid">';

        // Coluna 1: FormulÃ¡rio de avaliaÃ§Ã£o rÃ¡pida interna
        echo '<div class="dps-review-form-card">';
        echo '<div class="dps-review-form-card__header">';
        echo '<div class="dps-review-form-card__icon">ðŸ’¬</div>';
        echo '<div>';
        echo '<h3 class="dps-review-form-card__title">' . esc_html__( 'AvaliaÃ§Ã£o RÃ¡pida', 'dps-client-portal' ) . '</h3>';
        echo '<p class="dps-review-form-card__subtitle">' . esc_html__( 'Conte como foi a experiÃªncia do seu pet', 'dps-client-portal' ) . '</p>';
        echo '</div>';
        echo '</div>';

        if ( $client_reviewed ) {
            echo '<div class="dps-review-form-card__thanks">';
            echo '<div class="dps-review-form-card__thanks-icon">ðŸŽ‰</div>';
            echo '<p class="dps-review-form-card__thanks-text">' . esc_html__( 'Obrigado por sua avaliaÃ§Ã£o!', 'dps-client-portal' ) . '</p>';
            echo '<p class="dps-review-form-card__thanks-hint">' . esc_html__( 'Sua opiniÃ£o Ã© muito importante para nÃ³s.', 'dps-client-portal' ) . '</p>';
            echo '</div>';
        } else {
            $this->render_internal_review_form( $client_id, $client_name );
        }

        echo '</div>';

        // Coluna 2: CTA para Google Reviews
        echo '<div class="dps-review-google-card">';
        echo '<div class="dps-review-google-card__header">';
        echo '<div class="dps-review-google-card__logo">';
        echo '<span class="dps-google-g">G</span>';
        echo '</div>';
        echo '<div>';
        echo '<h3 class="dps-review-google-card__title">' . esc_html__( 'Avalie no Google', 'dps-client-portal' ) . '</h3>';
        echo '<p class="dps-review-google-card__subtitle">' . esc_html__( 'Ajude outros tutores a nos conhecer', 'dps-client-portal' ) . '</p>';
        echo '</div>';
        echo '</div>';

        echo '<div class="dps-review-google-card__content">';
        echo '<p class="dps-review-google-card__text">' . esc_html__( 'Suas 5 estrelas no Google ajudam outros clientes a confiar em nÃ³s e nos mostram onde podemos melhorar.', 'dps-client-portal' ) . '</p>';

        echo '<div class="dps-review-google-card__steps">';
        echo '<div class="dps-review-google-card__step"><span class="dps-step-num">1</span>' . esc_html__( 'Clique no botÃ£o', 'dps-client-portal' ) . '</div>';
        echo '<div class="dps-review-google-card__step"><span class="dps-step-num">2</span>' . esc_html__( 'Escolha 1-5 estrelas', 'dps-client-portal' ) . '</div>';
        echo '<div class="dps-review-google-card__step"><span class="dps-step-num">3</span>' . esc_html__( 'Comente (opcional)', 'dps-client-portal' ) . '</div>';
        echo '</div>';
        echo '</div>';

        echo '<div class="dps-review-google-card__actions">';
        if ( $review_url ) {
            echo '<a class="dps-review-google-btn" href="' . esc_url( $review_url ) . '" target="_blank" rel="noopener noreferrer">';
            echo '<span class="dps-review-google-btn__icon">â­</span>';
            echo '<span class="dps-review-google-btn__text">' . esc_html__( 'Avaliar no Google', 'dps-client-portal' ) . '</span>';
            echo '</a>';
            echo '<p class="dps-review-google-card__hint">' . esc_html__( 'Abre em nova aba â€¢ Leva menos de 1 minuto', 'dps-client-portal' ) . '</p>';
        } else {
            echo '<div class="dps-portal-notice dps-portal-notice--info">';
            echo '<p>' . esc_html__( 'Em breve vocÃª poderÃ¡ nos avaliar no Google tambÃ©m!', 'dps-client-portal' ) . '</p>';
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';

        echo '</div>'; // .dps-reviews-grid

        // SeÃ§Ã£o de prova social - o que outros clientes dizem
        echo '<div class="dps-review-social">';
        echo '<div class="dps-review-social__header">';
        echo '<h3 class="dps-review-social__title">' . esc_html__( 'O que nossos clientes dizem', 'dps-client-portal' ) . '</h3>';
        if ( $summary['count'] > 0 ) {
            $average_label = sprintf(
                /* translators: %s: average rating */
                __( '%s de 5 estrelas', 'dps-client-portal' ),
                number_format_i18n( $summary['average'], 1 )
            );
            echo '<div class="dps-review-social__badge">';
            echo $this->render_star_icons( $summary['average'], $average_label );
            echo '<span class="dps-review-social__badge-text">' . esc_html( number_format_i18n( $summary['average'], 1 ) ) . '</span>';
            echo '</div>';
        }
        echo '</div>';

        if ( ! empty( $summary['items'] ) ) {
            echo '<div class="dps-review-list">';
            foreach ( $summary['items'] as $item ) {
                echo '<article class="dps-review-card">';
                echo '<div class="dps-review-card__stars">';
                $label = sprintf(
                    /* translators: %s: star rating */
                    __( '%s de 5 estrelas', 'dps-client-portal' ),
                    number_format_i18n( $item['rating'], 1 )
                );
                echo $this->render_star_icons( $item['rating'], $label );
                echo '</div>';
                if ( $item['author'] ) {
                    echo '<p class="dps-review-card__author">' . esc_html( $item['author'] ) . '</p>';
                }
                if ( $item['content'] ) {
                    echo '<blockquote class="dps-review-card__quote">"' . esc_html( $item['content'] ) . '"</blockquote>';
                }
                if ( $item['date'] ) {
                    echo '<time class="dps-review-card__date">' . esc_html( $item['date'] ) . '</time>';
                }
                echo '</article>';
            }
            echo '</div>';
        } else {
            echo '<div class="dps-empty-state dps-empty-state--compact">';
            echo '<div class="dps-empty-state__icon">ðŸ’­</div>';
            echo '<div class="dps-empty-state__message">' . esc_html__( 'Seja o primeiro a deixar uma avaliaÃ§Ã£o!', 'dps-client-portal' ) . '</div>';
            echo '<p class="dps-empty-state__hint">' . esc_html__( 'Sua opiniÃ£o vai ajudar outros tutores a conhecer nosso trabalho.', 'dps-client-portal' ) . '</p>';
            echo '</div>';
        }

        echo '</div>'; // .dps-review-social
        echo '</section>';
    }

    /**
     * Renderiza cards de mÃ©tricas das avaliaÃ§Ãµes.
     *
     * @param array $summary Dados resumidos das avaliaÃ§Ãµes.
     */
    private function render_reviews_metrics_cards( $summary ) {
        $average = $summary['average'];
        $count   = $summary['count'];

        echo '<div class="dps-metrics-grid">';

        // Card: Nota MÃ©dia
        echo '<div class="dps-metric-card dps-metric-card--highlight">';
        echo '<div class="dps-metric-card__icon">â­</div>';
        echo '<div class="dps-metric-card__content">';
        echo '<span class="dps-metric-card__value">' . esc_html( $count > 0 ? number_format_i18n( $average, 1 ) : 'â€”' ) . '</span>';
        echo '<span class="dps-metric-card__label">' . esc_html__( 'Nota MÃ©dia', 'dps-client-portal' ) . '</span>';
        echo '</div>';
        echo '</div>';

        // Card: Total de AvaliaÃ§Ãµes
        echo '<div class="dps-metric-card">';
        echo '<div class="dps-metric-card__icon">ðŸ“</div>';
        echo '<div class="dps-metric-card__content">';
        echo '<span class="dps-metric-card__value">' . esc_html( number_format_i18n( $count ) ) . '</span>';
        echo '<span class="dps-metric-card__label">' . esc_html( _n( 'AvaliaÃ§Ã£o', 'AvaliaÃ§Ãµes', $count, 'dps-client-portal' ) ) . '</span>';
        echo '</div>';
        echo '</div>';

        // Card: SatisfaÃ§Ã£o (baseado em avaliaÃ§Ãµes 4-5 estrelas)
        $satisfaction = $this->calculate_satisfaction_rate();
        echo '<div class="dps-metric-card">';
        echo '<div class="dps-metric-card__icon">ðŸ˜Š</div>';
        echo '<div class="dps-metric-card__content">';
        echo '<span class="dps-metric-card__value">' . esc_html( $satisfaction > 0 ? $satisfaction . '%' : 'â€”' ) . '</span>';
        echo '<span class="dps-metric-card__label">' . esc_html__( 'SatisfaÃ§Ã£o', 'dps-client-portal' ) . '</span>';
        echo '</div>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * Renderiza formulÃ¡rio de avaliaÃ§Ã£o interna rÃ¡pida.
     *
     * @param int    $client_id   ID do cliente.
     * @param string $client_name Nome do cliente.
     */
    private function render_internal_review_form( $client_id, $client_name ) {
        echo '<form method="post" class="dps-review-internal-form" id="dps-review-internal-form">';
        wp_nonce_field( 'dps_client_portal_action', '_dps_client_portal_nonce' );
        echo '<input type="hidden" name="dps_client_portal_action" value="submit_internal_review">';

        // Seletor de estrelas interativo
        echo '<div class="dps-star-rating-input">';
        echo '<label class="dps-star-rating-label">' . esc_html__( 'Como foi a experiÃªncia?', 'dps-client-portal' ) . '</label>';
        echo '<div class="dps-star-rating-selector" role="radiogroup" aria-label="' . esc_attr__( 'Selecione uma nota de 1 a 5 estrelas', 'dps-client-portal' ) . '">';
        for ( $i = 1; $i <= 5; $i++ ) {
            echo '<input type="radio" name="review_rating" value="' . esc_attr( $i ) . '" id="star-' . esc_attr( $i ) . '" class="dps-star-input" required>';
            echo '<label for="star-' . esc_attr( $i ) . '" class="dps-star-label" title="' . esc_attr( sprintf( __( '%d estrela(s)', 'dps-client-portal' ), $i ) ) . '">â˜…</label>';
        }
        echo '</div>';
        echo '<div class="dps-star-rating-hint">' . esc_html__( 'Clique nas estrelas para avaliar', 'dps-client-portal' ) . '</div>';
        echo '</div>';

        // Campo de comentÃ¡rio (opcional)
        echo '<div class="dps-review-comment-field">';
        echo '<label for="review_comment">' . esc_html__( 'ComentÃ¡rio (opcional)', 'dps-client-portal' ) . '</label>';
        echo '<textarea name="review_comment" id="review_comment" rows="3" placeholder="' . esc_attr__( 'Conte como foi a experiÃªncia do seu pet...', 'dps-client-portal' ) . '" maxlength="500" class="dps-form-control"></textarea>';
        echo '<span class="dps-char-counter"><span id="char-count">0</span>/500</span>';
        echo '</div>';

        // BotÃ£o de envio
        echo '<button type="submit" class="dps-btn-submit-review">';
        echo '<span class="dps-btn-icon">âœ“</span>';
        echo '<span class="dps-btn-text">' . esc_html__( 'Enviar AvaliaÃ§Ã£o', 'dps-client-portal' ) . '</span>';
        echo '</button>';

        echo '</form>';
    }

    /**
     * Calcula taxa de satisfaÃ§Ã£o (avaliaÃ§Ãµes 4-5 estrelas).
     *
     * @return int Percentual de satisfaÃ§Ã£o (0-100).
     */
    private function calculate_satisfaction_rate() {
        if ( ! post_type_exists( 'dps_groomer_review' ) ) {
            return 0;
        }

        $total_reviews = get_posts( [
            'post_type'      => 'dps_groomer_review',
            'post_status'    => 'publish',
            'posts_per_page' => 200,
            'fields'         => 'ids',
        ] );

        if ( empty( $total_reviews ) ) {
            return 0;
        }

        $positive_count = 0;
        foreach ( $total_reviews as $review_id ) {
            $rating_raw = get_post_meta( $review_id, '_dps_review_rating', true );
            $rating = is_numeric( $rating_raw ) ? (int) $rating_raw : 0;
            if ( $rating >= 4 ) {
                $positive_count++;
            }
        }

        return (int) round( ( $positive_count / count( $total_reviews ) ) * 100 );
    }

    /**
     * Verifica se o cliente jÃ¡ realizou uma avaliaÃ§Ã£o interna.
     *
     * @param int $client_id ID do cliente.
     * @return bool True se jÃ¡ avaliou.
     */
    private function has_client_reviewed( $client_id ) {
        if ( ! post_type_exists( 'dps_groomer_review' ) ) {
            return false;
        }

        $existing = get_posts( [
            'post_type'      => 'dps_groomer_review',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => '_dps_review_client_id',
                    'value' => $client_id,
                ],
            ],
        ] );

        return ! empty( $existing );
    }

    /**
     * Renderiza painel de fidelidade no portal.
     *
     * @param int $client_id ID do cliente autenticado.
     */
    private function render_loyalty_panel( $client_id ) {
        echo '<section class="dps-portal-section dps-portal-loyalty">';

        // TÃ­tulo da seÃ§Ã£o com Ã­cone para consistÃªncia visual
        echo '<h2>ðŸ† ' . esc_html__( 'Programa de Fidelidade', 'dps-client-portal' ) . '</h2>';

        if ( ! class_exists( 'DPS_Loyalty_API' ) ) {
            echo '<div class="dps-loyalty-inactive">';
            echo '<div class="dps-loyalty-inactive__icon">ðŸŽ</div>';
            echo '<p class="dps-loyalty-inactive__message">' . esc_html__( 'O programa de fidelidade nÃ£o estÃ¡ ativo no momento.', 'dps-client-portal' ) . '</p>';
            echo '<p class="dps-loyalty-inactive__hint">' . esc_html__( 'Em breve vocÃª poderÃ¡ acumular pontos e ganhar recompensas!', 'dps-client-portal' ) . '</p>';
            echo '</div>';
            echo '</section>';
            return;
        }

        $client_name   = get_the_title( $client_id );
        $points        = DPS_Loyalty_API::get_points( $client_id );
        $credit        = DPS_Loyalty_API::get_credit( $client_id );
        $tier          = DPS_Loyalty_API::get_loyalty_tier( $client_id );
        $referral_code = DPS_Loyalty_API::get_referral_code( $client_id );
        $referral_url  = DPS_Loyalty_API::get_referral_url( $client_id );
        $history_limit = 5;
        $history       = DPS_Loyalty_API::get_points_history( $client_id, [ 'limit' => $history_limit, 'offset' => 0 ] );
        $total_logs    = count( get_post_meta( $client_id, 'dps_loyalty_points_log' ) );
        $has_more      = $total_logs > $history_limit;
        $settings      = get_option( 'dps_loyalty_settings', [] );
        $achievement_definitions = DPS_Loyalty_Achievements::get_achievements_definitions();
        $unlocked_achievements  = DPS_Loyalty_Achievements::get_client_achievements( $client_id );

        $credit_display = DPS_Money_Helper::format_currency( $credit );
        $progress       = isset( $tier['progress'] ) ? (int) $tier['progress'] : 0;
        $next_points    = isset( $tier['next_points'] ) ? (int) $tier['next_points'] : null;
        $loyalty_nonce  = wp_create_nonce( 'dps_portal_loyalty' );

        $portal_enabled       = ! empty( $settings['enable_portal_redemption'] );
        $portal_min_points    = isset( $settings['portal_min_points_to_redeem'] ) ? absint( $settings['portal_min_points_to_redeem'] ) : 0;
        $points_per_real      = isset( $settings['portal_points_per_real'] ) ? max( 1, absint( $settings['portal_points_per_real'] ) ) : 100;
        $max_discount_cents   = isset( $settings['portal_max_discount_amount'] ) ? (int) $settings['portal_max_discount_amount'] : 0;
        $max_points_by_cap    = $max_discount_cents > 0 ? (int) floor( ( $max_discount_cents / 100 ) * $points_per_real ) : $points;
        $max_points_available = min( $points, $max_points_by_cap );
        $max_discount_display = DPS_Money_Helper::format_to_brazilian( $max_discount_cents );

        // Hero Section - Tier e Progresso
        echo '<div class="dps-loyalty-hero" data-loyalty-nonce="' . esc_attr( $loyalty_nonce ) . '" data-history-limit="' . esc_attr( $history_limit ) . '">';
        echo '<div class="dps-loyalty-hero__content">';
        echo '<div class="dps-loyalty-tier">';
        echo '<span class="dps-loyalty-tier__icon">' . esc_html( $tier['icon'] ?? 'ðŸ†' ) . '</span>';
        echo '<div class="dps-loyalty-tier__labels">';
        echo '<span class="dps-loyalty-tier__level">' . esc_html__( 'Seu nÃ­vel atual', 'dps-client-portal' ) . '</span>';
        echo '<span class="dps-loyalty-tier__name">' . esc_html( $tier['label'] ?? __( 'Bronze', 'dps-client-portal' ) ) . '</span>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="dps-loyalty-progress">';
        if ( $next_points ) {
            $remaining = max( 0, $next_points - $points );
            echo '<div class="dps-loyalty-progress__info">';
            echo '<span class="dps-loyalty-progress__current">' . esc_html( number_format( $points, 0, ',', '.' ) ) . ' pts</span>';
            echo '<span class="dps-loyalty-progress__next">' . esc_html( number_format( $next_points, 0, ',', '.' ) ) . ' pts</span>';
            echo '</div>';
            echo '<div class="dps-loyalty-progress__bar" role="progressbar" aria-valuenow="' . esc_attr( $progress ) . '" aria-valuemin="0" aria-valuemax="100" aria-label="' . esc_attr__( 'Progresso do nÃ­vel', 'dps-client-portal' ) . '"><span style="width: ' . esc_attr( $progress ) . '%"></span></div>';
            echo '<p class="dps-loyalty-progress__hint">' . esc_html( sprintf( __( 'Faltam %s pontos para o prÃ³ximo nÃ­vel! ðŸš€', 'dps-client-portal' ), number_format( $remaining, 0, ',', '.' ) ) ) . '</p>';
        } else {
            echo '<div class="dps-loyalty-progress__info">';
            echo '<span class="dps-loyalty-progress__current">' . esc_html( number_format( $points, 0, ',', '.' ) ) . ' pts</span>';
            echo '</div>';
            echo '<div class="dps-loyalty-progress__bar" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" aria-label="' . esc_attr__( 'Progresso do nÃ­vel', 'dps-client-portal' ) . '"><span style="width: 100%"></span></div>';
            echo '<p class="dps-loyalty-progress__hint">' . esc_html__( 'VocÃª estÃ¡ no nÃ­vel mÃ¡ximo! ðŸŽ‰', 'dps-client-portal' ) . '</p>';
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // Cards de EstatÃ­sticas
        echo '<div class="dps-loyalty-stats">';
        
        // Card: Pontos
        echo '<div class="dps-loyalty-card dps-loyalty-card--points">';
        echo '<div class="dps-loyalty-card__header">';
        echo '<span class="dps-loyalty-card__icon">ðŸŽ¯</span>';
        echo '<p class="dps-loyalty-card__label">' . esc_html__( 'Meus Pontos', 'dps-client-portal' ) . '</p>';
        echo '</div>';
        echo '<p class="dps-loyalty-card__value" data-loyalty-points>' . esc_html( number_format( $points, 0, ',', '.' ) ) . '</p>';
        echo '<button class="dps-button-link dps-loyalty-history-trigger" type="button">' . esc_html__( 'ðŸ“‹ Ver histÃ³rico', 'dps-client-portal' ) . '</button>';
        echo '</div>';

        // Card: CrÃ©ditos
        echo '<div class="dps-loyalty-card dps-loyalty-card--credits">';
        echo '<div class="dps-loyalty-card__header">';
        echo '<span class="dps-loyalty-card__icon">ðŸ’°</span>';
        echo '<p class="dps-loyalty-card__label">' . esc_html__( 'CrÃ©ditos DisponÃ­veis', 'dps-client-portal' ) . '</p>';
        echo '</div>';
        echo '<p class="dps-loyalty-card__value" data-loyalty-credit>' . esc_html( $credit_display ) . '</p>';
        echo '<small class="dps-loyalty-card__hint">' . esc_html__( 'Use para descontos no prÃ³ximo atendimento', 'dps-client-portal' ) . '</small>';
        echo '</div>';

        // Card: Indique e Ganhe
        echo '<div class="dps-loyalty-card dps-loyalty-card--referral">';
        echo '<div class="dps-loyalty-card__header">';
        echo '<span class="dps-loyalty-card__icon">ðŸŽ</span>';
        echo '<p class="dps-loyalty-card__label">' . esc_html__( 'Indique e Ganhe', 'dps-client-portal' ) . '</p>';
        echo '</div>';
        echo '<p class="dps-loyalty-card__code">' . esc_html( $referral_code ) . '</p>';
        if ( $referral_url ) {
            echo '<div class="dps-loyalty-card__actions">';
            echo '<input type="text" readonly value="' . esc_attr( $referral_url ) . '" class="dps-loyalty-referral-input" aria-label="' . esc_attr__( 'Link de indicaÃ§Ã£o', 'dps-client-portal' ) . '" />';
            echo '<button class="dps-button-link dps-portal-copy" type="button" data-copy-target="' . esc_attr( $referral_url ) . '">ðŸ“‹ ' . esc_html__( 'Copiar', 'dps-client-portal' ) . '</button>';
            echo '</div>';
            echo '<small class="dps-loyalty-card__hint">' . esc_html__( 'Compartilhe e ganhe pontos quando indicados agendarem!', 'dps-client-portal' ) . '</small>';
        }
        echo '</div>';
        echo '</div>';

        // SeÃ§Ã£o: Como Funciona (educacional)
        echo '<div class="dps-loyalty-how-it-works">';
        echo '<h3>ðŸ’¡ ' . esc_html__( 'Como Funciona', 'dps-client-portal' ) . '</h3>';
        echo '<div class="dps-loyalty-how-it-works__grid">';
        
        echo '<div class="dps-loyalty-step">';
        echo '<span class="dps-loyalty-step__number">1</span>';
        echo '<div class="dps-loyalty-step__content">';
        echo '<strong>' . esc_html__( 'Agende ServiÃ§os', 'dps-client-portal' ) . '</strong>';
        echo '<p>' . esc_html__( 'A cada banho ou tosa vocÃª acumula pontos automaticamente.', 'dps-client-portal' ) . '</p>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="dps-loyalty-step">';
        echo '<span class="dps-loyalty-step__number">2</span>';
        echo '<div class="dps-loyalty-step__content">';
        echo '<strong>' . esc_html__( 'Suba de NÃ­vel', 'dps-client-portal' ) . '</strong>';
        echo '<p>' . esc_html__( 'Quanto mais pontos, maior seu nÃ­vel e multiplicador de bÃ´nus.', 'dps-client-portal' ) . '</p>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="dps-loyalty-step">';
        echo '<span class="dps-loyalty-step__number">3</span>';
        echo '<div class="dps-loyalty-step__content">';
        echo '<strong>' . esc_html__( 'Troque por CrÃ©ditos', 'dps-client-portal' ) . '</strong>';
        echo '<p>' . esc_html__( 'Converta pontos em crÃ©ditos e use como desconto.', 'dps-client-portal' ) . '</p>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';

        // SeÃ§Ã£o: Conquistas
        echo '<div class="dps-loyalty-achievements">';
        echo '<h3>ðŸ… ' . esc_html__( 'Minhas Conquistas', 'dps-client-portal' ) . '</h3>';
        $unlocked_count = count( $unlocked_achievements );
        $total_achievements = count( $achievement_definitions );
        echo '<p class="dps-loyalty-achievements__summary">';
        echo esc_html( sprintf( __( 'VocÃª desbloqueou %d de %d conquistas', 'dps-client-portal' ), $unlocked_count, $total_achievements ) );
        echo '</p>';
        echo '<div class="dps-loyalty-achievements__grid">';
        foreach ( $achievement_definitions as $key => $achievement ) {
            $unlocked = in_array( $key, $unlocked_achievements, true );
            $card_class = $unlocked ? 'is-unlocked' : 'is-locked';
            echo '<div class="dps-loyalty-achievement ' . esc_attr( $card_class ) . '">';
            echo '<div class="dps-loyalty-achievement__icon">' . ( $unlocked ? 'ðŸ…' : 'ðŸ”’' ) . '</div>';
            echo '<div class="dps-loyalty-achievement__text">';
            echo '<p class="dps-loyalty-achievement__title">' . esc_html( $achievement['label'] ) . '</p>';
            echo '<p class="dps-loyalty-achievement__desc">' . esc_html( $achievement['description'] ) . '</p>';
            echo '<span class="dps-loyalty-achievement__status">' . esc_html( $unlocked ? __( 'Conquistado âœ“', 'dps-client-portal' ) : __( 'Em progresso...', 'dps-client-portal' ) ) . '</span>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';

        // SeÃ§Ã£o: HistÃ³rico de MovimentaÃ§Ãµes
        echo '<div class="dps-loyalty-history" data-total="' . esc_attr( $total_logs ) . '" data-limit="' . esc_attr( $history_limit ) . '">';
        echo '<div class="dps-loyalty-history__header">';
        echo '<h3>ðŸ“Š ' . esc_html__( 'HistÃ³rico de MovimentaÃ§Ãµes', 'dps-client-portal' ) . '</h3>';
        echo '</div>';
        if ( ! empty( $history ) ) {
            echo '<ul class="dps-loyalty-history__list">';
            foreach ( $history as $entry ) {
                $context_label = DPS_Loyalty_API::get_context_label( $entry['context'] );
                $formatted_date = date_i18n( 'd/m/Y H:i', strtotime( $entry['date'] ) );
                $sign = ( 'add' === $entry['action'] || 'credit_add' === $entry['action'] ) ? '+' : '-';
                $action_class = ( 'add' === $entry['action'] || 'credit_add' === $entry['action'] ) ? 'add' : 'redeem';
                echo '<li class="dps-loyalty-history__item">';
                echo '<div class="dps-loyalty-history__info">';
                echo '<p class="dps-loyalty-history__context">' . esc_html( $context_label ) . '</p>';
                echo '<span class="dps-loyalty-history__date">' . esc_html( $formatted_date ) . '</span>';
                echo '</div>';
                echo '<span class="dps-loyalty-history__points dps-loyalty-history__points--' . esc_attr( $action_class ) . '">' . esc_html( $sign . number_format( $entry['points'], 0, ',', '.' ) ) . '</span>';
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<div class="dps-loyalty-history__empty">';
            echo '<span class="dps-loyalty-history__empty-icon">ðŸ“­</span>';
            echo '<p>' . esc_html__( 'Nenhuma movimentaÃ§Ã£o ainda. Agende um serviÃ§o para comeÃ§ar a acumular pontos!', 'dps-client-portal' ) . '</p>';
            echo '</div>';
        }

        if ( $has_more ) {
            echo '<button type="button" class="dps-button-secondary dps-loyalty-history-more" data-offset="' . esc_attr( $history_limit ) . '" data-limit="' . esc_attr( $history_limit ) . '" data-nonce="' . esc_attr( $loyalty_nonce ) . '">' . esc_html__( 'Carregar mais', 'dps-client-portal' ) . '</button>';
        }
        echo '</div>';

        // SeÃ§Ã£o: Resgatar Pontos
        if ( $portal_enabled ) {
            echo '<div class="dps-loyalty-redemption">';
            echo '<h3>ðŸŽ ' . esc_html__( 'Resgatar Pontos', 'dps-client-portal' ) . '</h3>';
            echo '<p class="dps-loyalty-redemption__info">';
            echo esc_html( sprintf( __( 'ConversÃ£o: %d pontos = R$ 1,00', 'dps-client-portal' ), $points_per_real ) );
            if ( $max_discount_cents > 0 ) {
                echo ' â€¢ ' . esc_html( sprintf( __( 'MÃ¡ximo por resgate: R$ %s', 'dps-client-portal' ), $max_discount_display ) );
            }
            echo '</p>';

            if ( $max_points_available < max( $portal_min_points, 1 ) ) {
                echo '<div class="dps-loyalty-redemption__unavailable">';
                echo '<span class="dps-loyalty-redemption__unavailable-icon">â³</span>';
                echo '<p>' . esc_html( sprintf( __( 'VocÃª precisa de pelo menos %d pontos para resgatar. Continue acumulando!', 'dps-client-portal' ), $portal_min_points ) ) . '</p>';
                echo '</div>';
            } else {
                $default_value = max( $portal_min_points, 1 );
                $default_value = min( $default_value, $max_points_available );
                echo '<form class="dps-loyalty-redemption-form" data-nonce="' . esc_attr( $loyalty_nonce ) . '" data-rate="' . esc_attr( $points_per_real ) . '" data-max-cents="' . esc_attr( $max_discount_cents ) . '" data-min-points="' . esc_attr( $portal_min_points ) . '" data-current-points="' . esc_attr( $points ) . '">';
                echo '<div class="dps-loyalty-redemption__form-group">';
                echo '<label for="dps-loyalty-points-input">' . esc_html__( 'Quantidade de pontos para converter', 'dps-client-portal' ) . '</label>';
                echo '<input type="number" id="dps-loyalty-points-input" name="points_to_redeem" min="' . esc_attr( max( $portal_min_points, 1 ) ) . '" max="' . esc_attr( $max_points_available ) . '" step="1" value="' . esc_attr( $default_value ) . '" />';
                echo '<small class="dps-loyalty-redemption__balance">' . esc_html__( 'Saldo disponÃ­vel: ', 'dps-client-portal' ) . '<strong data-loyalty-points>' . esc_html( number_format( $points, 0, ',', '.' ) ) . '</strong> ' . esc_html__( 'pontos', 'dps-client-portal' ) . '</small>';
                echo '</div>';
                echo '<button type="submit" class="dps-button-primary dps-loyalty-redeem-btn">ðŸŽ ' . esc_html__( 'Resgatar Agora', 'dps-client-portal' ) . '</button>';
                echo '<div class="dps-loyalty-redemption__feedback" aria-live="polite"></div>';
                echo '</form>';
            }
            echo '</div>';
        } else {
            echo '<div class="dps-loyalty-redemption--disabled">';
            echo '<span class="dps-loyalty-redemption--disabled-icon">â„¹ï¸</span>';
            echo '<p>' . esc_html__( 'O resgate de pontos pelo portal estÃ¡ temporariamente indisponÃ­vel. Entre em contato com nossa equipe para utilizar seus pontos.', 'dps-client-portal' ) . '</p>';
            echo '</div>';
        }

        echo '</section>';
    }

    /**
     * Renderiza o shortcode de acesso do portal.
     *
     * @return string
     */
    public function render_login_shortcode() {
        if ( class_exists( 'DPS_Cache_Control' ) ) {
            DPS_Cache_Control::force_no_cache();
        }

        wp_enqueue_style( 'dps-client-portal' );
        wp_enqueue_script( 'dps-client-portal' );
        $this->localize_portal_script();

        return $this->render_access_screen();
    }

    /**
     * Processa salvamento das configuraÃ§Ãµes do portal.
     *
     * Verifica nonce, capability e salva as opÃ§Ãµes do portal.
     *
     * @since 2.1.0
     */
    public function handle_portal_settings_save() {
        if ( ! isset( $_POST['dps_save_portal_settings'] ) ) {
            return;
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        $nonce = isset( $_POST['_dps_portal_settings_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_dps_portal_settings_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'dps_save_portal_settings' ) ) {
            return;
        }
        
        // Salva ID da pÃ¡gina do portal
        if ( isset( $_POST['dps_portal_page_id'] ) ) {
            $page_id = absint( wp_unslash( $_POST['dps_portal_page_id'] ) );
            update_option( 'dps_portal_page_id', $page_id );
        }
        
        // Salva configuraÃ§Ã£o de notificaÃ§Ã£o de acesso (Fase 1.3)
        $access_notification = isset( $_POST['dps_portal_access_notification_enabled'] ) ? 1 : 0;
        update_option( 'dps_portal_access_notification_enabled', $access_notification );
        
        // Salva configuraÃ§Ã£o de 2FA (Fase 6.4)
        $twofa_enabled = isset( $_POST['dps_portal_2fa_enabled'] ) ? 1 : 0;
        update_option( 'dps_portal_2fa_enabled', $twofa_enabled );
        
        // Redireciona com mensagem de sucesso
        $redirect_url = add_query_arg( [
            'tab'                       => 'portal',
            'dps_portal_settings_saved' => '1',
        ], wp_get_referer() ?: admin_url( 'admin.php?page=desi-pet-shower' ) );
        
        wp_safe_redirect( $redirect_url );
        exit;
    }
    
    /**
     * ObtÃ©m o endereÃ§o IP do cliente de forma segura.
     *
     * @since 2.2.0
     * @deprecated 2.5.0 Use DPS_IP_Helper::get_ip() diretamente.
     *
     * @return string EndereÃ§o IP sanitizado ou 'unknown' se nÃ£o disponÃ­vel.
     */
    private function get_client_ip() {
        if ( class_exists( 'DPS_IP_Helper' ) ) {
            return DPS_IP_Helper::get_ip();
        }
        // Fallback para retrocompatibilidade
        if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
            return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }
        return 'unknown';
    }

    /**
     * Registra evento de seguranÃ§a no sistema de logs.
     *
     * Utiliza DPS_Logger para registrar eventos relacionados a autenticaÃ§Ã£o,
     * tentativas de login e outras aÃ§Ãµes de seguranÃ§a do portal.
     *
     * IMPORTANTE: Nunca registra senhas ou tokens completos para evitar
     * exposiÃ§Ã£o de dados sensÃ­veis. Usa allowlist de campos seguros.
     *
     * @since 2.2.0
     *
     * @param string $event   Nome do evento (ex: 'login_failed', 'token_auth_success').
     * @param array  $context Dados do contexto (IP, client_id, etc.). NÃ£o incluir senhas.
     * @param string $level   NÃ­vel do log (padrÃ£o: warning). Use DPS_Logger::LEVEL_* constants.
     */
    private function log_security_event( $event, $context = [], $level = null ) {
        // Verifica se DPS_Logger existe (plugin base ativo)
        if ( ! class_exists( 'DPS_Logger' ) ) {
            return;
        }

        // Define nÃ­vel padrÃ£o como warning para eventos de seguranÃ§a
        if ( null === $level ) {
            $level = DPS_Logger::LEVEL_WARNING;
        }

        // Allowlist de campos seguros para evitar exposiÃ§Ã£o de dados sensÃ­veis
        $allowed_fields = [ 'ip', 'client_id', 'user_id', 'attempts', 'event_type', 'timestamp' ];
        $safe_context   = array_intersect_key( $context, array_flip( $allowed_fields ) );

        $message = sprintf( 'Portal security event: %s', $event );
        DPS_Logger::log( $level, $message, $safe_context, 'client-portal' );
    }

    /**
     * Envia notificaÃ§Ã£o de acesso ao portal para o cliente
     * 
     * Notifica o cliente via e-mail quando ocorre um acesso bem-sucedido ao portal.
     * Aumenta a seguranÃ§a e transparÃªncia, permitindo que o cliente identifique
     * acessos nÃ£o autorizados.
     * 
     * CONFIGURAÃ‡ÃƒO:
     * A notificaÃ§Ã£o pode ser ativada/desativada via option 'dps_portal_access_notification_enabled'
     * 
     * CONTEÃšDO DO E-MAIL:
     * - Data e hora do acesso
     * - EndereÃ§o IP (primeiros 3 octetos ofuscados para privacidade)
     * - Mensagem de seguranÃ§a: "Se nÃ£o foi vocÃª, entre em contato imediatamente"
     * 
     * @param int    $client_id  ID do cliente que acessou o portal
     * @param string $ip_address IP do acesso
     * @return void
     * 
     * @since 2.4.0
     */
    private function send_access_notification( $client_id, $ip_address ) {
        // Verifica se notificaÃ§Ãµes estÃ£o habilitadas
        $notifications_enabled = get_option( 'dps_portal_access_notification_enabled', false );
        
        // Permite filtro para controle por add-ons
        $notifications_enabled = apply_filters( 'dps_portal_access_notification_enabled', $notifications_enabled, $client_id );
        
        if ( ! $notifications_enabled ) {
            return;
        }
        
        // ObtÃ©m dados do cliente
        $client_email = get_post_meta( $client_id, 'client_email', true );
        $client_name  = get_the_title( $client_id );
        
        if ( empty( $client_email ) || ! is_email( $client_email ) ) {
            DPS_Logger::log( 'warning', 'Portal: notificaÃ§Ã£o de acesso nÃ£o enviada - e-mail invÃ¡lido', [
                'client_id' => $client_id,
            ] );
            return;
        }
        
        // Formata data/hora do acesso
        $access_time = current_time( 'mysql' );
        $access_date = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $access_time ) );
        
        // Ofusca IP parcialmente para privacidade (mantÃ©m apenas primeiros 2 octetos)
        // Nota: ImplementaÃ§Ã£o atual suporta apenas IPv4
        $ip_parts      = explode( '.', $ip_address );
        $ip_obfuscated = isset( $ip_parts[0], $ip_parts[1] ) && count( $ip_parts ) === 4
            ? $ip_parts[0] . '.' . $ip_parts[1] . '.***' 
            : 'desconhecido';
        
        // Monta o corpo do e-mail
        $subject = sprintf(
            /* translators: %s: Nome do site */
            __( 'Acesso ao Portal - %s', 'dps-client-portal' ),
            get_bloginfo( 'name' )
        );
        
        $body = sprintf(
            /* translators: 1: Nome do cliente, 2: Data/hora do acesso, 3: IP ofuscado */
            __( 'OlÃ¡ %1$s,

Detectamos um acesso ao seu Portal do Cliente.

Data/Hora: %2$s
IP: %3$s

Se vocÃª reconhece este acesso, pode ignorar esta mensagem. Ela Ã© apenas uma notificaÃ§Ã£o de seguranÃ§a para mantÃª-lo informado.

âš ï¸ IMPORTANTE: Se vocÃª NÃƒO realizou este acesso, entre em contato com nossa equipe IMEDIATAMENTE. Pode ser que alguÃ©m tenha obtido seu link de acesso indevidamente.

Atenciosamente,
Equipe %4$s', 'dps-client-portal' ),
            $client_name,
            $access_date,
            $ip_obfuscated,
            get_bloginfo( 'name' )
        );
        
        // Permite filtrar assunto e corpo
        $subject = apply_filters( 'dps_portal_access_notification_subject', $subject, $client_id );
        $body    = apply_filters( 'dps_portal_access_notification_body', $body, $client_id, $access_date, $ip_obfuscated );
        
        // Tenta usar Communications API se disponÃ­vel
        if ( class_exists( 'DPS_Communications_API' ) ) {
            $comm_api = DPS_Communications_API::get_instance();
            $sent     = $comm_api->send_email( 
                $client_email, 
                $subject, 
                $body, 
                [
                    'client_id' => $client_id,
                    'type'      => 'portal_access_notification',
                ] 
            );
        } else {
            // Fallback para wp_mail
            $sent = wp_mail( $client_email, $subject, $body );
        }
        
        if ( ! $sent ) {
            DPS_Logger::log( 'error', 'Portal: falha ao enviar notificaÃ§Ã£o de acesso', [
                'client_id' => $client_id,
                'email'     => $client_email,
            ] );
        }
        
        // Hook para extensÃµes (ex: enviar tambÃ©m via WhatsApp)
        do_action( 'dps_portal_access_notification_sent', $client_id, $sent, $access_date, $ip_address );
    }

    /**
     * Renderiza seÃ§Ã£o de preferÃªncias do cliente.
     * Fase 4 - continuaÃ§Ã£o: PreferÃªncias
     *
     * @since 2.4.0
     * @param int $client_id ID do cliente.
     */
    private function render_client_preferences( $client_id ) {
        // Busca preferÃªncias salvas
        $contact_preference = get_post_meta( $client_id, 'client_contact_preference', true );
        $period_preference  = get_post_meta( $client_id, 'client_period_preference', true );

        // PreferÃªncias de notificaÃ§Ã£o
        $notif_reminders  = get_post_meta( $client_id, 'client_notification_reminders', true );
        $notif_payments   = get_post_meta( $client_id, 'client_notification_payments', true );
        $notif_promotions = get_post_meta( $client_id, 'client_notification_promotions', true );
        $notif_updates    = get_post_meta( $client_id, 'client_notification_updates', true );

        // Default: ligado para reminders/payments, desligado para promotions
        if ( $notif_reminders === '' ) {
            $notif_reminders = '1';
        }
        if ( $notif_payments === '' ) {
            $notif_payments = '1';
        }

        echo '<div class="dps-surface dps-surface--neutral dps-meus-dados-card dps-preferences-section">';
        echo '<div class="dps-surface__title">';
        echo '<span>âš™ï¸</span>';
        echo esc_html__( 'Minhas PreferÃªncias', 'dps-client-portal' );
        echo '</div>';
        echo '<p class="dps-surface__description">' . esc_html__( 'Personalize como e quando prefere ser atendido.', 'dps-client-portal' ) . '</p>';
        
        echo '<form method="post" class="dps-portal-form dps-meus-dados-form">';
        wp_nonce_field( 'dps_client_portal_action', '_dps_client_portal_nonce' );
        echo '<input type="hidden" name="dps_client_portal_action" value="update_client_preferences">';
        
        echo '<fieldset class="dps-fieldset">';
        echo '<legend class="dps-fieldset__legend">' . esc_html__( 'ðŸ“ž ComunicaÃ§Ã£o', 'dps-client-portal' ) . '</legend>';
        
        echo '<div class="dps-form-row dps-form-row--2col">';
        
        // Canal de contato preferido
        echo '<div class="dps-form-col">';
        echo '<label class="dps-form-label" for="contact_preference">';
        echo esc_html__( 'Como prefere ser contatado?', 'dps-client-portal' );
        echo '</label>';
        echo '<select id="contact_preference" name="contact_preference" class="dps-form-control">';
        echo '<option value="">' . esc_html__( 'Sem preferÃªncia', 'dps-client-portal' ) . '</option>';
        echo '<option value="whatsapp"' . selected( $contact_preference, 'whatsapp', false ) . '>' . esc_html__( 'WhatsApp', 'dps-client-portal' ) . '</option>';
        echo '<option value="phone"' . selected( $contact_preference, 'phone', false ) . '>' . esc_html__( 'Telefone', 'dps-client-portal' ) . '</option>';
        echo '<option value="email"' . selected( $contact_preference, 'email', false ) . '>' . esc_html__( 'E-mail', 'dps-client-portal' ) . '</option>';
        echo '</select>';
        echo '</div>';
        
        // PerÃ­odo preferido
        echo '<div class="dps-form-col">';
        echo '<label class="dps-form-label" for="period_preference">';
        echo esc_html__( 'PerÃ­odo preferido para banho/tosa', 'dps-client-portal' );
        echo '</label>';
        echo '<select id="period_preference" name="period_preference" class="dps-form-control">';
        echo '<option value="">' . esc_html__( 'Sem preferÃªncia', 'dps-client-portal' ) . '</option>';
        echo '<option value="morning"' . selected( $period_preference, 'morning', false ) . '>' . esc_html__( 'ManhÃ£', 'dps-client-portal' ) . '</option>';
        echo '<option value="afternoon"' . selected( $period_preference, 'afternoon', false ) . '>' . esc_html__( 'Tarde', 'dps-client-portal' ) . '</option>';
        echo '<option value="flexible"' . selected( $period_preference, 'flexible', false ) . '>' . esc_html__( 'Indiferente', 'dps-client-portal' ) . '</option>';
        echo '</select>';
        echo '</div>';
        
        echo '</div>'; // .dps-form-row
        echo '</fieldset>';

        // PreferÃªncias de notificaÃ§Ã£o
        echo '<fieldset class="dps-fieldset">';
        echo '<legend class="dps-fieldset__legend">' . esc_html__( 'ðŸ”” NotificaÃ§Ãµes', 'dps-client-portal' ) . '</legend>';
        echo '<p class="dps-fieldset__description">' . esc_html__( 'Escolha quais notificaÃ§Ãµes deseja receber.', 'dps-client-portal' ) . '</p>';

        echo '<div class="dps-notification-toggles">';

        // Lembretes de agendamento
        echo '<label class="dps-toggle-row">';
        echo '<span class="dps-toggle-row__label">';
        echo '<span class="dps-toggle-row__icon">ðŸ“…</span>';
        echo esc_html__( 'Lembretes de agendamento', 'dps-client-portal' );
        echo '</span>';
        echo '<span class="dps-toggle-row__hint">' . esc_html__( 'Receba avisos antes do horÃ¡rio marcado', 'dps-client-portal' ) . '</span>';
        echo '<input type="hidden" name="notification_reminders" value="0">';
        echo '<input type="checkbox" name="notification_reminders" value="1" class="dps-toggle-input"' . checked( $notif_reminders, '1', false ) . '>';
        echo '<span class="dps-toggle-switch" aria-hidden="true"></span>';
        echo '</label>';

        // NotificaÃ§Ãµes de pagamento
        echo '<label class="dps-toggle-row">';
        echo '<span class="dps-toggle-row__label">';
        echo '<span class="dps-toggle-row__icon">ðŸ’°</span>';
        echo esc_html__( 'Avisos de pagamento', 'dps-client-portal' );
        echo '</span>';
        echo '<span class="dps-toggle-row__hint">' . esc_html__( 'Receba lembretes de parcelas e cobranÃ§as', 'dps-client-portal' ) . '</span>';
        echo '<input type="hidden" name="notification_payments" value="0">';
        echo '<input type="checkbox" name="notification_payments" value="1" class="dps-toggle-input"' . checked( $notif_payments, '1', false ) . '>';
        echo '<span class="dps-toggle-switch" aria-hidden="true"></span>';
        echo '</label>';

        // PromoÃ§Ãµes
        echo '<label class="dps-toggle-row">';
        echo '<span class="dps-toggle-row__label">';
        echo '<span class="dps-toggle-row__icon">ðŸŽ</span>';
        echo esc_html__( 'PromoÃ§Ãµes e ofertas', 'dps-client-portal' );
        echo '</span>';
        echo '<span class="dps-toggle-row__hint">' . esc_html__( 'Fique por dentro de descontos e novidades', 'dps-client-portal' ) . '</span>';
        echo '<input type="hidden" name="notification_promotions" value="0">';
        echo '<input type="checkbox" name="notification_promotions" value="1" class="dps-toggle-input"' . checked( $notif_promotions, '1', false ) . '>';
        echo '<span class="dps-toggle-switch" aria-hidden="true"></span>';
        echo '</label>';

        // AtualizaÃ§Ãµes do pet
        echo '<label class="dps-toggle-row">';
        echo '<span class="dps-toggle-row__label">';
        echo '<span class="dps-toggle-row__icon">ðŸ¾</span>';
        echo esc_html__( 'AtualizaÃ§Ãµes do pet', 'dps-client-portal' );
        echo '</span>';
        echo '<span class="dps-toggle-row__hint">' . esc_html__( 'Novas fotos, observaÃ§Ãµes e relatÃ³rios', 'dps-client-portal' ) . '</span>';
        echo '<input type="hidden" name="notification_updates" value="0">';
        echo '<input type="checkbox" name="notification_updates" value="1" class="dps-toggle-input"' . checked( $notif_updates, '1', false ) . '>';
        echo '<span class="dps-toggle-switch" aria-hidden="true"></span>';
        echo '</label>';

        echo '</div>'; // .dps-notification-toggles
        echo '</fieldset>';
        
        echo '<div class="dps-form-actions">';
        echo '<button type="submit" class="button button-primary dps-btn-submit">';
        echo '<span>ðŸ’¾</span> ' . esc_html__( 'Salvar PreferÃªncias', 'dps-client-portal' );
        echo '</button>';
        echo '</div>';
        
        echo '</form>';
        echo '</div>'; // .dps-surface
    }


    /**
     * Renderiza timeline de serviÃ§os por pet.
     * Fase 4: Timeline de ServiÃ§os
     * RevisÃ£o completa do layout: Janeiro 2026
     *
     * @since 2.4.0
     * @param int $client_id ID do cliente.
     */
    private function render_pets_timeline( $client_id ) {
        $pet_repo = DPS_Pet_Repository::get_instance();
        $pets     = $pet_repo->get_pets_by_client( $client_id );
        $renderer = DPS_Portal_Renderer::get_instance();

        // Renderiza cabeÃ§alho da aba com mÃ©tricas globais
        $renderer->render_pet_history_header( $client_id, $pets );

        if ( empty( $pets ) ) {
            echo '<section class="dps-portal-section dps-portal-pet-history-empty">';
            echo '<div class="dps-empty-state dps-empty-state--large">';
            echo '<div class="dps-empty-state__illustration">ðŸ¾</div>';
            echo '<h3 class="dps-empty-state__title">' . esc_html__( 'Nenhum pet cadastrado ainda', 'dps-client-portal' ) . '</h3>';
            echo '<p class="dps-empty-state__message">' . esc_html__( 'Cadastre seus pets para acompanhar o histÃ³rico de serviÃ§os realizados.', 'dps-client-portal' ) . '</p>';
            // CTA para contato
            if ( class_exists( 'DPS_WhatsApp_Helper' ) ) {
                $whatsapp_url = DPS_WhatsApp_Helper::get_link_to_team( __( 'OlÃ¡! Gostaria de cadastrar meu pet.', 'dps-client-portal' ) );
            } else {
                $whatsapp_number = get_option( 'dps_whatsapp_number', '5515991606299' );
                if ( class_exists( 'DPS_Phone_Helper' ) ) {
                    $whatsapp_number = DPS_Phone_Helper::format_for_whatsapp( $whatsapp_number );
                }
                $whatsapp_url = 'https://wa.me/' . $whatsapp_number . '?text=' . urlencode( 'OlÃ¡! Gostaria de cadastrar meu pet.' );
            }
            echo '<a href="' . esc_url( $whatsapp_url ) . '" target="_blank" class="dps-empty-state__action button button-primary">';
            echo 'ðŸ’¬ ' . esc_html__( 'Falar com a Equipe', 'dps-client-portal' );
            echo '</a>';
            echo '</div>';
            echo '</section>';
            return;
        }

        // Renderiza navegaÃ§Ã£o por abas quando hÃ¡ mÃºltiplos pets
        if ( count( $pets ) > 1 ) {
            $renderer->render_pet_tabs_navigation( $pets );
        }

        // Container principal com timelines dos pets
        echo '<div class="dps-pet-timelines-container">';
        
        foreach ( $pets as $index => $pet ) {
            $is_first = ( 0 === $index );
            $renderer->render_pet_service_timeline( $pet->ID, $client_id, 10, $is_first, count( $pets ) > 1 );
        }
        
        echo '</div>';
    }

    /**
     * Ajusta o brilho de uma cor hexadecimal.
     * Fase 4 - Branding: Helper para cores
     *
     * @param string $hex   Cor hexadecimal (#RRGGBB).
     * @param int    $steps Quantidade de brilho a ajustar (negativo escurece, positivo clareia).
     * @return string Cor ajustada em hexadecimal.
     */
    private function adjust_brightness( $hex, $steps ) {
        // Convert hex to RGB (cast to string for PHP 8.1+ compatibility)
        $hex = str_replace( '#', '', (string) $hex );
        if ( strlen( $hex ) === 3 ) {
            $hex = str_repeat( substr( $hex, 0, 1 ), 2 ) . str_repeat( substr( $hex, 1, 1 ), 2 ) . str_repeat( substr( $hex, 2, 1 ), 2 );
        }
        
        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );
        
        // Ajusta
        $r = max( 0, min( 255, $r + $steps ) );
        $g = max( 0, min( 255, $g + $steps ) );
        $b = max( 0, min( 255, $b + $steps ) );
        
        // Converte de volta para hex
        return '#' . str_pad( dechex( $r ), 2, '0', STR_PAD_LEFT ) . str_pad( dechex( $g ), 2, '0', STR_PAD_LEFT ) . str_pad( dechex( $b ), 2, '0', STR_PAD_LEFT );
    }
}
endif;
