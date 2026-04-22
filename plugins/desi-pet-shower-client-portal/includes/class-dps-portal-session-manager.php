<?php
/**
 * Gerenciador de sessoes do Portal do Cliente.
 *
 * Esta classe gerencia autenticacao e sessao dos clientes no portal,
 * independente do sistema de usuarios do WordPress.
 *
 * @package DPS_Client_Portal
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'DPS_Portal_Session_Manager' ) ) :

/**
 * Classe responsavel pelo gerenciamento de sessoes do portal.
 *
 * @since 3.0.0 Implementa DPS_Portal_Session_Manager_Interface
 */
final class DPS_Portal_Session_Manager implements DPS_Portal_Session_Manager_Interface {

    /**
     * Nome do cookie de sessao.
     *
     * @var string
     */
    const COOKIE_NAME = 'dps_portal_session';

    /**
     * Option que armazena sessoes ativas do portal.
     *
     * @var string
     */
    const SESSION_STORAGE_OPTION = 'dps_portal_sessions';

    /**
     * Tempo de vida da sessao em segundos (24 horas).
     *
     * @var int
     */
    const SESSION_LIFETIME = 86400;

    /**
     * Unica instancia da classe.
     *
     * @var DPS_Portal_Session_Manager|null
     */
    private static $instance = null;

    /**
     * Recupera a instancia unica (singleton).
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
     * Construtor privado para singleton.
     */
    private function __construct() {
        // Prioridade 10 para executar apos handle_token_authentication (prioridade 5).
        if ( did_action( 'init' ) ) {
            $this->validate_session();
        } else {
            add_action( 'init', [ $this, 'validate_session' ], 10 );
        }
    }

    /**
     * Autentica um cliente no portal usando cookie + armazenamento persistente.
     *
     * @param int $client_id ID do cliente.
     * @return bool True se autenticado com sucesso, false se erro.
     */
    public function authenticate_client( $client_id ) {
        $client_id = absint( $client_id );

        if ( ! $client_id || 'dps_cliente' !== get_post_type( $client_id ) ) {
            return false;
        }

        $session_token = bin2hex( random_bytes( 16 ) );
        $session_data  = [
            'client_id'  => $client_id,
            'login_time' => time(),
            'ip'         => $this->get_client_ip(),
            'user_agent' => $this->get_user_agent(),
        ];

        $this->store_session_data( $session_token, $session_data );

        $expires  = time() + self::SESSION_LIFETIME;
        $path     = COOKIEPATH;
        $domain   = COOKIE_DOMAIN;
        $secure   = is_ssl();
        $httponly = true;

        setcookie(
            self::COOKIE_NAME,
            $session_token,
            $expires,
            $path,
            $domain,
            $secure,
            $httponly
        );

        if ( ! headers_sent() ) {
            header(
                sprintf(
                    'Set-Cookie: %s=%s; Expires=%s; Path=%s; Domain=%s%s%s; SameSite=Strict',
                    self::COOKIE_NAME,
                    $session_token,
                    gmdate( 'D, d M Y H:i:s T', $expires ),
                    $path,
                    $domain,
                    $secure ? '; Secure' : '',
                    $httponly ? '; HttpOnly' : ''
                ),
                false
            );
        }

        return true;
    }

    /**
     * Retorna o ID do cliente autenticado.
     *
     * @return int ID do cliente ou 0 se nao autenticado.
     */
    public function get_authenticated_client_id() {
        if ( ! isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
            return 0;
        }

        $session_token = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
        $session_data  = $this->get_session_data( $session_token );

        if ( false === $session_data || ! is_array( $session_data ) ) {
            return 0;
        }

        $client_id = isset( $session_data['client_id'] ) ? absint( $session_data['client_id'] ) : 0;

        if ( ! $client_id || 'dps_cliente' !== get_post_type( $client_id ) ) {
            $this->logout();
            return 0;
        }

        return $client_id;
    }

    /**
     * Verifica se ha um cliente autenticado.
     *
     * @return bool True se autenticado, false caso contrario.
     */
    public function is_authenticated() {
        return $this->get_authenticated_client_id() > 0;
    }

    /**
     * Valida a sessao atual.
     *
     * @return void
     */
    public function validate_session() {
        if ( ! isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
            return;
        }

        $session_token = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
        $session_data  = $this->get_session_data( $session_token );

        if ( false === $session_data ) {
            $this->logout();
            return;
        }

        $client_id = isset( $session_data['client_id'] ) ? absint( $session_data['client_id'] ) : 0;
        if ( ! $client_id || 'dps_cliente' !== get_post_type( $client_id ) ) {
            $this->logout();
        }
    }

    /**
     * Faz logout do cliente.
     *
     * @return void
     */
    public function logout() {
        $should_logout_wp_user = false;
        if ( is_user_logged_in() && ! current_user_can( 'manage_options' ) ) {
            $current_user = wp_get_current_user();
            if ( ! class_exists( 'DPS_Portal_User_Manager' ) || DPS_Portal_User_Manager::get_instance()->is_client_portal_user( $current_user ) ) {
                $should_logout_wp_user = true;
            }
        }

        if ( isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
            $session_token = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
            $this->delete_session_data( $session_token );
        }

        $expires  = time() - 3600;
        $path     = COOKIEPATH;
        $domain   = COOKIE_DOMAIN;
        $secure   = is_ssl();
        $httponly = true;

        setcookie(
            self::COOKIE_NAME,
            '',
            $expires,
            $path,
            $domain,
            $secure,
            $httponly
        );

        if ( ! headers_sent() ) {
            header(
                sprintf(
                    'Set-Cookie: %s=; Expires=%s; Path=%s; Domain=%s%s%s; SameSite=Strict',
                    self::COOKIE_NAME,
                    gmdate( 'D, d M Y H:i:s T', $expires ),
                    $path,
                    $domain,
                    $secure ? '; Secure' : '',
                    $httponly ? '; HttpOnly' : ''
                ),
                false
            );
        }

        if ( isset( $_COOKIE['dps_portal_remember'] ) ) {
            setcookie( 'dps_portal_remember', '', $expires, $path, $domain, $secure, $httponly );
            if ( ! headers_sent() ) {
                header(
                    sprintf(
                        'Set-Cookie: dps_portal_remember=; Expires=%s; Path=%s; Domain=%s%s%s; SameSite=Strict',
                        gmdate( 'D, d M Y H:i:s T', $expires ),
                        $path,
                        $domain,
                        $secure ? '; Secure' : '',
                        $httponly ? '; HttpOnly' : ''
                    ),
                    false
                );
            }
        }

        if ( $should_logout_wp_user ) {
            wp_logout();
        }
    }

    /**
     * Processa acao de logout via query parameter.
     *
     * @return void
     */
    public function handle_logout_request() {
        if ( ! isset( $_GET['dps_portal_logout'] ) ) {
            return;
        }

        $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'dps_portal_logout' ) ) {
            return;
        }

        $this->logout();

        $redirect_url = dps_get_portal_page_url();
        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Gera URL de logout.
     *
     * @return string URL de logout com nonce.
     */
    public function get_logout_url() {
        $base_url = dps_get_portal_page_url();

        return wp_nonce_url(
            add_query_arg( 'dps_portal_logout', '1', $base_url ),
            'dps_portal_logout'
        );
    }

    /**
     * Metodo de compatibilidade mantido para retrocompatibilidade.
     *
     * @deprecated 2.4.0 Nao e mais necessario com o armazenamento atual.
     * @return void
     */
    public function maybe_start_session() {
        // Mantido apenas para compatibilidade com chamadas antigas.
    }

    /**
     * Armazena dados de sessao.
     *
     * @param string $session_token Token enviado ao navegador.
     * @param array  $session_data  Dados da sessao.
     * @return void
     */
    private function store_session_data( $session_token, array $session_data ) {
        $sessions = $this->get_sessions();
        $now      = time();

        $this->purge_expired_sessions( $sessions, $now );

        $session_data['expires_at'] = $now + self::SESSION_LIFETIME;
        $sessions[ $this->get_session_storage_key( $session_token ) ] = $session_data;

        $this->save_sessions( $sessions );
    }

    /**
     * Recupera dados de sessao.
     *
     * @param string $session_token Token enviado ao navegador.
     * @return array|false
     */
    private function get_session_data( $session_token ) {
        $sessions = $this->get_sessions();
        $key      = $this->get_session_storage_key( $session_token );
        $now      = time();

        $this->purge_expired_sessions( $sessions, $now );

        if ( ! isset( $sessions[ $key ] ) || ! is_array( $sessions[ $key ] ) ) {
            $this->save_sessions( $sessions );
            return false;
        }

        if ( $this->is_session_expired( $sessions[ $key ], $now ) ) {
            unset( $sessions[ $key ] );
            $this->save_sessions( $sessions );
            return false;
        }

        $this->save_sessions( $sessions );
        return $sessions[ $key ];
    }

    /**
     * Remove uma sessao.
     *
     * @param string $session_token Token enviado ao navegador.
     * @return void
     */
    private function delete_session_data( $session_token ) {
        $sessions = $this->get_sessions();
        $key      = $this->get_session_storage_key( $session_token );

        if ( isset( $sessions[ $key ] ) ) {
            unset( $sessions[ $key ] );
            $this->save_sessions( $sessions );
        }
    }

    /**
     * Recupera sessoes persistidas.
     *
     * @return array<string, array<string, mixed>>
     */
    private function get_sessions() {
        $sessions = get_option( self::SESSION_STORAGE_OPTION, [] );

        return is_array( $sessions ) ? $sessions : [];
    }

    /**
     * Persiste sessoes.
     *
     * @param array<string, array<string, mixed>> $sessions Sessoes ativas.
     * @return void
     */
    private function save_sessions( array $sessions ) {
        update_option( self::SESSION_STORAGE_OPTION, $sessions, false );
    }

    /**
     * Remove sessoes expiradas.
     *
     * @param array<string, array<string, mixed>> $sessions Sessoes por referencia.
     * @param int                                 $now      Timestamp atual.
     * @return void
     */
    private function purge_expired_sessions( array &$sessions, $now ) {
        foreach ( $sessions as $key => $session_data ) {
            if ( ! is_array( $session_data ) || $this->is_session_expired( $session_data, $now ) ) {
                unset( $sessions[ $key ] );
            }
        }
    }

    /**
     * Determina se uma sessao expirou.
     *
     * @param array<string, mixed> $session_data Dados da sessao.
     * @param int                  $now          Timestamp atual.
     * @return bool
     */
    private function is_session_expired( array $session_data, $now ) {
        return empty( $session_data['expires_at'] ) || (int) $session_data['expires_at'] <= (int) $now;
    }

    /**
     * Gera chave interna sem persistir o token bruto.
     *
     * @param string $session_token Token enviado ao navegador.
     * @return string
     */
    private function get_session_storage_key( $session_token ) {
        return hash( 'sha256', (string) $session_token );
    }

    /**
     * Obtem o IP do cliente de forma segura.
     *
     * @deprecated 2.5.0 Use DPS_IP_Helper::get_ip() diretamente.
     *
     * @return string IP do cliente.
     */
    private function get_client_ip() {
        if ( class_exists( 'DPS_IP_Helper' ) ) {
            return DPS_IP_Helper::get_ip();
        }
        if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
            return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }
        return 'unknown';
    }

    /**
     * Obtem o User Agent de forma segura.
     *
     * @return string User Agent.
     */
    private function get_user_agent() {
        if ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
            return sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
        }
        return 'unknown';
    }
}

endif;
