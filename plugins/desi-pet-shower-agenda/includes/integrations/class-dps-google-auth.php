<?php
/**
 * Google OAuth 2.0 Authentication Handler
 *
 * Gerencia autenticação OAuth 2.0 compartilhada para Google Calendar e Google Tasks APIs.
 * 
 * @package    DPS_Agenda_Addon
 * @subpackage Integrations
 * @since      2.0.0
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe de autenticação OAuth 2.0 para Google APIs.
 *
 * Gerencia o fluxo completo de OAuth 2.0:
 * - Geração de URL de autorização
 * - Troca de authorization code por tokens
 * - Refresh de access tokens expirados
 * - Armazenamento seguro (criptografado) de tokens
 * - Verificação de status de conexão
 *
 * @since 2.0.0
 */
class DPS_Google_Auth {
    
    /**
     * Escopos OAuth necessários para Calendar e Tasks APIs.
     *
     * @since 2.0.0
     * @var array
     */
    const SCOPES = [
        'https://www.googleapis.com/auth/calendar',        // Google Calendar API
        'https://www.googleapis.com/auth/tasks',           // Google Tasks API
    ];
    
    /**
     * URL base para OAuth 2.0 do Google.
     *
     * @since 2.0.0
     * @var string
     */
    const OAUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    
    /**
     * URL para troca de tokens.
     *
     * @since 2.0.0
     * @var string
     */
    const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    
    /**
     * Nome da option que armazena as configurações.
     *
     * @since 2.0.0
     * @var string
     */
    const OPTION_NAME = 'dps_google_integrations_settings';
    
    /**
     * Gera URL de autorização OAuth 2.0.
     *
     * Redireciona o usuário para o Google para autorizar acesso
     * a Calendar e Tasks APIs.
     *
     * @since 2.0.0
     *
     * @return string URL de autorização ou string vazia se credenciais não configuradas.
     */
    public static function get_auth_url() {
        $client_id = self::get_client_id();
        
        if ( empty( $client_id ) ) {
            return '';
        }
        
        $redirect_uri = self::get_redirect_uri();
        $state = wp_create_nonce( 'dps_google_oauth' );
        
        $params = [
            'client_id'     => $client_id,
            'redirect_uri'  => $redirect_uri,
            'response_type' => 'code',
            'scope'         => implode( ' ', self::SCOPES ),
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => $state,
        ];
        
        return add_query_arg( $params, self::OAUTH_URL );
    }
    
    /**
     * Troca authorization code por access token e refresh token.
     *
     * @since 2.0.0
     *
     * @param string $code Authorization code recebido do Google.
     * @return array|WP_Error Array com tokens ou WP_Error em caso de falha.
     */
    public static function exchange_code_for_tokens( $code ) {
        $client_id     = self::get_client_id();
        $client_secret = self::get_client_secret();
        
        if ( empty( $client_id ) || empty( $client_secret ) ) {
            return new WP_Error(
                'missing_credentials',
                __( 'Credenciais do Google não configuradas.', 'dps-agenda-addon' )
            );
        }
        
        $body = [
            'code'          => $code,
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'redirect_uri'  => self::get_redirect_uri(),
            'grant_type'    => 'authorization_code',
        ];
        
        $response = wp_remote_post( self::TOKEN_URL, [
            'body'    => $body,
            'timeout' => 15,
        ] );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( ! isset( $data['access_token'] ) ) {
            return new WP_Error(
                'token_exchange_failed',
                __( 'Falha ao trocar authorization code por tokens.', 'dps-agenda-addon' ),
                $data
            );
        }
        
        // Armazena tokens de forma criptografada
        $settings = [
            'access_token'     => self::encrypt( $data['access_token'] ),
            'refresh_token'    => isset( $data['refresh_token'] ) ? self::encrypt( $data['refresh_token'] ) : '',
            'token_expires_at' => time() + (int) $data['expires_in'],
            'connected_at'     => time(),
        ];
        
        update_option( self::OPTION_NAME, $settings );
        
        /**
         * Disparado após conectar com sucesso ao Google.
         *
         * @since 2.0.0
         */
        do_action( 'dps_google_auth_connected' );
        
        return $data;
    }
    
    /**
     * Renova access token usando refresh token.
     *
     * @since 2.0.0
     *
     * @return array|WP_Error Array com novo access token ou WP_Error em caso de falha.
     */
    public static function refresh_access_token() {
        $settings = get_option( self::OPTION_NAME, [] );
        
        if ( empty( $settings['refresh_token'] ) ) {
            return new WP_Error(
                'no_refresh_token',
                __( 'Refresh token não encontrado. Reconecte com o Google.', 'dps-agenda-addon' )
            );
        }
        
        $client_id     = self::get_client_id();
        $client_secret = self::get_client_secret();
        $refresh_token = self::decrypt( $settings['refresh_token'] );
        
        $body = [
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'refresh_token' => $refresh_token,
            'grant_type'    => 'refresh_token',
        ];
        
        $response = wp_remote_post( self::TOKEN_URL, [
            'body'    => $body,
            'timeout' => 15,
        ] );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( ! isset( $data['access_token'] ) ) {
            return new WP_Error(
                'token_refresh_failed',
                __( 'Falha ao renovar access token.', 'dps-agenda-addon' ),
                $data
            );
        }
        
        // Atualiza apenas access token e expiration
        $settings['access_token']     = self::encrypt( $data['access_token'] );
        $settings['token_expires_at'] = time() + (int) $data['expires_in'];
        
        update_option( self::OPTION_NAME, $settings );
        
        return $data;
    }
    
    /**
     * Obtém access token válido (renova se necessário).
     *
     * @since 2.0.0
     *
     * @return string|WP_Error Access token descriptografado ou WP_Error.
     */
    public static function get_access_token() {
        $settings = get_option( self::OPTION_NAME, [] );
        
        if ( empty( $settings['access_token'] ) ) {
            return new WP_Error(
                'not_connected',
                __( 'Não conectado ao Google. Por favor, conecte primeiro.', 'dps-agenda-addon' )
            );
        }
        
        // Verifica se token expirou (com 5 minutos de margem)
        $expires_at = isset( $settings['token_expires_at'] ) ? (int) $settings['token_expires_at'] : 0;
        if ( $expires_at < ( time() + 300 ) ) {
            $result = self::refresh_access_token();
            if ( is_wp_error( $result ) ) {
                return $result;
            }
            // Re-fetch settings após refresh
            $settings = get_option( self::OPTION_NAME, [] );
        }
        
        return self::decrypt( $settings['access_token'] );
    }
    
    /**
     * Verifica se está conectado ao Google.
     *
     * @since 2.0.0
     *
     * @return bool True se conectado, false caso contrário.
     */
    public static function is_connected() {
        $settings = get_option( self::OPTION_NAME, [] );
        return ! empty( $settings['access_token'] ) && ! empty( $settings['refresh_token'] );
    }
    
    /**
     * Desconecta e remove todos os tokens armazenados.
     *
     * @since 2.0.0
     *
     * @return bool True em caso de sucesso.
     */
    public static function disconnect() {
        /**
         * Disparado antes de desconectar do Google.
         * Permite que outros componentes limpem seus dados.
         *
         * @since 2.0.0
         */
        do_action( 'dps_google_auth_disconnected' );
        
        delete_option( self::OPTION_NAME );
        
        /**
         * Disparado após desconectar do Google.
         *
         * @since 2.0.0
         */
        do_action( 'dps_google_disconnected' );
        
        return true;
    }
    
    /**
     * Obtém Client ID da constante ou option.
     *
     * @since 2.0.0
     *
     * @return string Client ID ou string vazia.
     */
    private static function get_client_id() {
        if ( defined( 'DPS_GOOGLE_CLIENT_ID' ) ) {
            return DPS_GOOGLE_CLIENT_ID;
        }
        
        $settings = get_option( self::OPTION_NAME, [] );
        return isset( $settings['client_id'] ) ? $settings['client_id'] : '';
    }
    
    /**
     * Obtém Client Secret da constante ou option.
     *
     * @since 2.0.0
     *
     * @return string Client Secret ou string vazia.
     */
    private static function get_client_secret() {
        if ( defined( 'DPS_GOOGLE_CLIENT_SECRET' ) ) {
            return DPS_GOOGLE_CLIENT_SECRET;
        }
        
        $settings = get_option( self::OPTION_NAME, [] );
        return isset( $settings['client_secret'] ) ? $settings['client_secret'] : '';
    }
    
    /**
     * Obtém URI de redirecionamento OAuth.
     *
     * @since 2.0.0
     *
     * @return string URL de redirecionamento.
     */
    private static function get_redirect_uri() {
        return admin_url( 'admin.php?page=dps-agenda-hub&tab=google-integrations&action=oauth_callback' );
    }
    
    /**
     * Criptografa string usando AES-256-CBC.
     *
     * @since 2.0.0
     *
     * @param string $plaintext Texto a criptografar.
     * @return string Texto criptografado (base64).
     */
    private static function encrypt( $plaintext ) {
        if ( empty( $plaintext ) ) {
            return '';
        }
        
        $key    = self::get_encryption_key();
        $cipher = 'aes-256-cbc';
        $iv_len = openssl_cipher_iv_length( $cipher );
        $iv     = openssl_random_pseudo_bytes( $iv_len );
        
        $ciphertext = openssl_encrypt( $plaintext, $cipher, $key, 0, $iv );
        
        return base64_encode( $iv . $ciphertext );
    }
    
    /**
     * Descriptografa string usando AES-256-CBC.
     *
     * @since 2.0.0
     *
     * @param string $encrypted Texto criptografado (base64).
     * @return string Texto descriptografado.
     */
    private static function decrypt( $encrypted ) {
        if ( empty( $encrypted ) ) {
            return '';
        }
        
        $key     = self::get_encryption_key();
        $cipher  = 'aes-256-cbc';
        $iv_len  = openssl_cipher_iv_length( $cipher );
        $decoded = base64_decode( $encrypted );
        
        $iv         = substr( $decoded, 0, $iv_len );
        $ciphertext = substr( $decoded, $iv_len );
        
        return openssl_decrypt( $ciphertext, $cipher, $key, 0, $iv );
    }
    
    /**
     * Obtém chave de criptografia.
     *
     * @since 2.0.0
     *
     * @return string Chave de criptografia.
     */
    private static function get_encryption_key() {
        if ( defined( 'DPS_ENCRYPTION_KEY' ) ) {
            return DPS_ENCRYPTION_KEY;
        }
        
        // Fallback: usa hash de AUTH_KEY do WordPress
        if ( defined( 'AUTH_KEY' ) && AUTH_KEY !== 'put your unique phrase here' ) {
            return hash( 'sha256', AUTH_KEY . 'dps_google_integrations' );
        }
        
        // Último recurso: gera chave única por instalação e persiste
        $stored_key = get_option( 'dps_encryption_key_fallback' );
        if ( ! $stored_key ) {
            $stored_key = wp_generate_password( 64, true, true );
            update_option( 'dps_encryption_key_fallback', $stored_key, false );
        }
        return hash( 'sha256', $stored_key );
    }
}
