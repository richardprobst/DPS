<?php
/**
 * Gerenciador de sessões do Portal do Groomer
 *
 * Esta classe gerencia a autenticação e sessão dos groomers no portal,
 * permitindo acesso via magic link sem necessidade de login tradicional.
 *
 * @package DPS_Groomers_Addon
 * @since 1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'DPS_Groomer_Session_Manager' ) ) :

/**
 * Classe responsável pelo gerenciamento de sessões do groomer
 */
final class DPS_Groomer_Session_Manager {

    /**
     * Nome da chave de sessão para o groomer_id
     *
     * @var string
     */
    const SESSION_KEY = 'dps_groomer_id';

    /**
     * Nome da chave de sessão para timestamp de login
     *
     * @var string
     */
    const SESSION_TIME_KEY = 'dps_groomer_login_time';

    /**
     * Tempo de vida da sessão em segundos (24 horas)
     *
     * @var int
     */
    const SESSION_LIFETIME = 86400;

    /**
     * Única instância da classe
     *
     * @var DPS_Groomer_Session_Manager|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única (singleton)
     *
     * @return DPS_Groomer_Session_Manager
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

        // SECURITY: Não iniciar sessão em requisições de cron
        if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
            return;
        }

        if ( ! session_id() ) {
            // SECURITY FIX: Configurar parâmetros seguros de sessão ANTES de iniciar
            // Essas configurações devem ser feitas antes do session_start()
            $session_options = [
                'cookie_httponly' => true,
                'cookie_secure'   => is_ssl(),
                'cookie_samesite' => 'Strict',
                'use_strict_mode' => true,
                'cookie_lifetime' => 0, // Session cookie (expires when browser closes)
                'gc_maxlifetime'  => self::SESSION_LIFETIME,
            ];
            
            session_start( $session_options );
        }
    }

    /**
     * Autentica um groomer no portal
     *
     * @param int $groomer_id ID do usuário groomer
     * @return bool True se autenticado com sucesso, false se erro
     */
    public function authenticate_groomer( $groomer_id ) {
        $groomer_id = absint( $groomer_id );
        
        // Valida se é um groomer válido
        $user = get_user_by( 'id', $groomer_id );
        if ( ! $user || ! in_array( 'dps_groomer', (array) $user->roles, true ) ) {
            return false;
        }

        // Regenera ID da sessão por segurança (proteção contra session fixation)
        if ( session_id() ) {
            session_regenerate_id( true );
        }

        // Armazena groomer_id na sessão
        $_SESSION[ self::SESSION_KEY ]      = $groomer_id;
        $_SESSION[ self::SESSION_TIME_KEY ] = time();

        return true;
    }

    /**
     * Retorna o ID do groomer autenticado via sessão
     *
     * @return int ID do groomer ou 0 se não autenticado
     */
    public function get_authenticated_groomer_id() {
        if ( ! isset( $_SESSION[ self::SESSION_KEY ] ) ) {
            return 0;
        }

        $groomer_id = absint( $_SESSION[ self::SESSION_KEY ] );

        // Valida se ainda é um groomer válido
        $user = get_user_by( 'id', $groomer_id );
        if ( ! $user || ! in_array( 'dps_groomer', (array) $user->roles, true ) ) {
            $this->logout();
            return 0;
        }

        return $groomer_id;
    }

    /**
     * Verifica se há um groomer autenticado
     *
     * @return bool
     */
    public function is_groomer_authenticated() {
        return $this->get_authenticated_groomer_id() > 0;
    }

    /**
     * Valida a sessão atual (expiração, etc)
     */
    public function validate_session() {
        if ( ! isset( $_SESSION[ self::SESSION_KEY ] ) ) {
            return;
        }

        // Verifica expiração
        if ( isset( $_SESSION[ self::SESSION_TIME_KEY ] ) ) {
            $login_time = (int) $_SESSION[ self::SESSION_TIME_KEY ];
            if ( time() - $login_time > self::SESSION_LIFETIME ) {
                $this->logout();
            }
        }
    }

    /**
     * Encerra a sessão do groomer
     */
    public function logout() {
        unset( $_SESSION[ self::SESSION_KEY ] );
        unset( $_SESSION[ self::SESSION_TIME_KEY ] );
    }

    /**
     * Processa requisição de logout
     */
    public function handle_logout_request() {
        // Verifica se é uma requisição de logout
        if ( ! isset( $_GET['dps_groomer_logout'] ) ) {
            return;
        }

        // Verifica nonce
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'dps_groomer_logout' ) ) {
            return;
        }

        $this->logout();

        // Redireciona para a página inicial ou URL especificada
        $redirect_url = isset( $_GET['redirect_to'] ) 
            ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) 
            : home_url();

        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Gera URL de logout com nonce
     *
     * @param string $redirect_to URL para redirecionar após logout
     * @return string URL de logout
     */
    public function get_logout_url( $redirect_to = '' ) {
        $args = [
            'dps_groomer_logout' => '1',
            '_wpnonce'           => wp_create_nonce( 'dps_groomer_logout' ),
        ];

        if ( ! empty( $redirect_to ) ) {
            $args['redirect_to'] = $redirect_to;
        }

        return add_query_arg( $args, home_url() );
    }

    /**
     * Obtém dados do groomer autenticado
     *
     * @return WP_User|false Objeto do usuário ou false se não autenticado
     */
    public function get_authenticated_groomer() {
        $groomer_id = $this->get_authenticated_groomer_id();
        if ( ! $groomer_id ) {
            return false;
        }

        return get_user_by( 'id', $groomer_id );
    }
}

endif;
