<?php
/**
 * Gerenciador de sessões do Portal do Cliente
 *
 * Esta classe gerencia a autenticação e sessão dos clientes no portal,
 * independente do sistema de usuários do WordPress.
 *
 * @package DPS_Client_Portal
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'DPS_Portal_Session_Manager' ) ) :

/**
 * Classe responsável pelo gerenciamento de sessões do portal
 */
final class DPS_Portal_Session_Manager {

    /**
     * Nome da chave de sessão para o client_id
     *
     * @var string
     */
    const SESSION_KEY = 'dps_portal_client_id';

    /**
     * Nome da chave de sessão para timestamp de login
     *
     * @var string
     */
    const SESSION_TIME_KEY = 'dps_portal_login_time';

    /**
     * Tempo de vida da sessão em segundos (24 horas)
     *
     * @var int
     */
    const SESSION_LIFETIME = 86400;

    /**
     * Única instância da classe
     *
     * @var DPS_Portal_Session_Manager|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única (singleton)
     *
     * @return DPS_Portal_Session_Manager
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado para singleton
     */
    private function __construct() {
        // Inicia sessão PHP se necessário
        add_action( 'init', [ $this, 'maybe_start_session' ], 1 );
        
        // Valida sessão em cada requisição
        add_action( 'init', [ $this, 'validate_session' ], 5 );
    }

    /**
     * Inicia sessão PHP se ainda não iniciada
     */
    public function maybe_start_session() {
        // Evita avisos de cabeçalho já enviado e não interfere em requisições AJAX/REST
        if ( headers_sent() ) {
            return;
        }

        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            return;
        }

        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            return;
        }

        if ( ! session_id() ) {
            // Configura parâmetros seguros de sessão
            ini_set( 'session.cookie_httponly', 1 );
            ini_set( 'session.cookie_secure', is_ssl() ? 1 : 0 );
            ini_set( 'session.cookie_samesite', 'Strict' );
            ini_set( 'session.use_strict_mode', 1 );
            
            session_start();
        }
    }

    /**
     * Autentica um cliente no portal
     *
     * @param int $client_id ID do cliente
     * @return bool True se autenticado com sucesso, false se erro
     */
    public function authenticate_client( $client_id ) {
        $client_id = absint( $client_id );
        
        // Valida se é um cliente válido
        if ( ! $client_id || 'dps_cliente' !== get_post_type( $client_id ) ) {
            return false;
        }

        // Regenera ID da sessão por segurança (proteção contra session fixation)
        if ( session_id() ) {
            session_regenerate_id( true );
        }

        // Armazena client_id na sessão
        $_SESSION[ self::SESSION_KEY ]      = $client_id;
        $_SESSION[ self::SESSION_TIME_KEY ] = time();

        return true;
    }

    /**
     * Retorna o ID do cliente autenticado
     *
     * @return int ID do cliente ou 0 se não autenticado
     */
    public function get_authenticated_client_id() {
        if ( ! isset( $_SESSION[ self::SESSION_KEY ] ) ) {
            return 0;
        }

        $client_id = absint( $_SESSION[ self::SESSION_KEY ] );

        // Valida se ainda é um cliente válido
        if ( ! $client_id || 'dps_cliente' !== get_post_type( $client_id ) ) {
            $this->logout();
            return 0;
        }

        return $client_id;
    }

    /**
     * Verifica se há um cliente autenticado
     *
     * @return bool True se autenticado, false caso contrário
     */
    public function is_authenticated() {
        return $this->get_authenticated_client_id() > 0;
    }

    /**
     * Valida a sessão atual
     * Remove sessões expiradas ou inválidas
     */
    public function validate_session() {
        if ( ! isset( $_SESSION[ self::SESSION_KEY ] ) ) {
            return;
        }

        // Verifica tempo de vida da sessão
        if ( isset( $_SESSION[ self::SESSION_TIME_KEY ] ) ) {
            $login_time = absint( $_SESSION[ self::SESSION_TIME_KEY ] );
            $elapsed    = time() - $login_time;

            if ( $elapsed > self::SESSION_LIFETIME ) {
                $this->logout();
                return;
            }
        }

        // Valida se o cliente ainda existe
        $client_id = absint( $_SESSION[ self::SESSION_KEY ] );
        if ( ! $client_id || 'dps_cliente' !== get_post_type( $client_id ) ) {
            $this->logout();
        }
    }

    /**
     * Faz logout do cliente
     */
    public function logout() {
        if ( isset( $_SESSION[ self::SESSION_KEY ] ) ) {
            unset( $_SESSION[ self::SESSION_KEY ] );
        }

        if ( isset( $_SESSION[ self::SESSION_TIME_KEY ] ) ) {
            unset( $_SESSION[ self::SESSION_TIME_KEY ] );
        }

        // Regenera ID da sessão por segurança
        if ( session_id() ) {
            session_regenerate_id( true );
        }
    }

    /**
     * Processa ação de logout via query parameter
     */
    public function handle_logout_request() {
        if ( ! isset( $_GET['dps_portal_logout'] ) ) {
            return;
        }

        // Verifica nonce
        $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'dps_portal_logout' ) ) {
            return;
        }

        $this->logout();

        // Redireciona para a tela de acesso
        $portal_page = get_page_by_title( 'Portal do Cliente' );
        if ( $portal_page ) {
            $redirect_url = get_permalink( $portal_page->ID );
        } else {
            $redirect_url = home_url( '/portal-cliente/' );
        }

        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Gera URL de logout
     *
     * @return string URL de logout com nonce
     */
    public function get_logout_url() {
        $portal_page = get_page_by_title( 'Portal do Cliente' );
        if ( $portal_page ) {
            $base_url = get_permalink( $portal_page->ID );
        } else {
            $base_url = home_url( '/portal-cliente/' );
        }

        return wp_nonce_url( 
            add_query_arg( 'dps_portal_logout', '1', $base_url ), 
            'dps_portal_logout' 
        );
    }
}

endif;
