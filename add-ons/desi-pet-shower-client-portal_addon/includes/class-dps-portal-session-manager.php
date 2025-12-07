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
 * 
 * Versão 2.4.0: Migrado de $_SESSION para transients + cookies
 * para compatibilidade com ambientes multi-servidor e cloud.
 */
final class DPS_Portal_Session_Manager {

    /**
     * Nome do cookie de sessão
     *
     * @var string
     */
    const COOKIE_NAME = 'dps_portal_session';

    /**
     * Prefixo para transients de sessão
     *
     * @var string
     */
    const TRANSIENT_PREFIX = 'dps_session_';

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
        // Valida sessão em cada requisição
        add_action( 'init', [ $this, 'validate_session' ], 5 );
    }

    /**
     * Autentica um cliente no portal usando transients + cookies
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

        // Gera token de sessão único e seguro
        $session_token = bin2hex( random_bytes( 16 ) );
        
        // Armazena dados da sessão em transient (compatível com object cache e multi-servidor)
        $session_data = [
            'client_id'  => $client_id,
            'login_time' => time(),
            'ip'         => $this->get_client_ip(),
            'user_agent' => $this->get_user_agent(),
        ];
        
        set_transient( 
            self::TRANSIENT_PREFIX . $session_token, 
            $session_data, 
            self::SESSION_LIFETIME 
        );
        
        // Define cookie seguro no navegador do cliente
        $cookie_options = [
            'expires'  => time() + self::SESSION_LIFETIME,
            'path'     => COOKIEPATH,
            'domain'   => COOKIE_DOMAIN,
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Strict',
        ];
        
        setcookie( self::COOKIE_NAME, $session_token, $cookie_options );

        return true;
    }

    /**
     * Retorna o ID do cliente autenticado
     *
     * @return int ID do cliente ou 0 se não autenticado
     */
    public function get_authenticated_client_id() {
        // Verifica se cookie existe
        if ( ! isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
            return 0;
        }
        
        $session_token = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
        
        // Busca dados da sessão no transient
        $session_data = get_transient( self::TRANSIENT_PREFIX . $session_token );
        
        if ( false === $session_data || ! is_array( $session_data ) ) {
            return 0;
        }
        
        // Extrai client_id
        $client_id = isset( $session_data['client_id'] ) ? absint( $session_data['client_id'] ) : 0;

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
        if ( ! isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
            return;
        }
        
        $session_token = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
        $session_data  = get_transient( self::TRANSIENT_PREFIX . $session_token );
        
        if ( false === $session_data ) {
            // Sessão expirou, limpa cookie
            $this->logout();
            return;
        }
        
        // Valida se o cliente ainda existe
        $client_id = isset( $session_data['client_id'] ) ? absint( $session_data['client_id'] ) : 0;
        if ( ! $client_id || 'dps_cliente' !== get_post_type( $client_id ) ) {
            $this->logout();
        }
    }

    /**
     * Faz logout do cliente
     */
    public function logout() {
        // Remove transient se existir cookie
        if ( isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
            $session_token = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
            delete_transient( self::TRANSIENT_PREFIX . $session_token );
        }
        
        // Remove cookie do navegador
        if ( isset( $_SERVER['HTTP_HOST'] ) ) {
            setcookie( self::COOKIE_NAME, '', [
                'expires'  => time() - 3600,
                'path'     => COOKIEPATH,
                'domain'   => COOKIE_DOMAIN,
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Strict',
            ] );
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
        $redirect_url = dps_get_portal_page_url();
        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Gera URL de logout
     *
     * @return string URL de logout com nonce
     */
    public function get_logout_url() {
        $base_url = dps_get_portal_page_url();

        return wp_nonce_url( 
            add_query_arg( 'dps_portal_logout', '1', $base_url ), 
            'dps_portal_logout' 
        );
    }

    /**
     * Método de compatibilidade - não mais necessário
     * Mantido para retrocompatibilidade mas não faz nada
     * 
     * @deprecated 2.4.0 Não mais necessário com sistema de transients
     */
    public function maybe_start_session() {
        // Não faz nada - mantido apenas para compatibilidade
    }

    /**
     * Obtém o IP do cliente de forma segura
     *
     * @return string IP do cliente
     */
    private function get_client_ip() {
        if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
            return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }
        return 'unknown';
    }

    /**
     * Obtém o User Agent de forma segura
     *
     * @return string User Agent
     */
    private function get_user_agent() {
        if ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
            return sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
        }
        return 'unknown';
    }
}

endif;
