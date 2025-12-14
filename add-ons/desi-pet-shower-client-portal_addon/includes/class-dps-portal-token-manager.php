<?php
/**
 * Gerenciador de tokens de acesso ao Portal do Cliente
 *
 * Esta classe gerencia a criação, validação, revogação e limpeza de tokens
 * de autenticação para o Portal do Cliente. Tokens são magic links que
 * permitem acesso sem senha.
 *
 * @package DPS_Client_Portal
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'DPS_Portal_Token_Manager' ) ) :

/**
 * Classe responsável pelo gerenciamento de tokens do portal
 * 
 * @since 3.0.0 Implementa DPS_Portal_Token_Manager_Interface
 */
final class DPS_Portal_Token_Manager implements DPS_Portal_Token_Manager_Interface {

    /**
     * Nome da tabela de tokens (sem prefixo)
     *
     * @var string
     */
    const TABLE_NAME = 'dps_portal_tokens';

    /**
     * Versão do schema da tabela
     *
     * @var string
     */
    const DB_VERSION = '1.0.0';

    /**
     * Tempo de expiração padrão em minutos
     *
     * @var int
     */
    const DEFAULT_EXPIRATION_MINUTES = 30;
    
    /**
     * Tempo de expiração para tokens permanentes em minutos (10 anos)
     *
     * @var int
     */
    const PERMANENT_EXPIRATION_MINUTES = 60 * 24 * 365 * 10;
    
    /**
     * Tamanho máximo do user agent armazenado no log de acesso
     *
     * @var int
     */
    const MAX_USER_AGENT_LENGTH = 255;

    /**
     * Única instância da classe
     *
     * @var DPS_Portal_Token_Manager|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única (singleton)
     *
     * @return DPS_Portal_Token_Manager
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
        // Registra hook para criar/atualizar tabela
        add_action( 'plugins_loaded', [ $this, 'maybe_create_table' ] );
        
        // Registra cron job para limpeza de tokens expirados
        add_action( 'dps_portal_cleanup_tokens', [ $this, 'cleanup_expired_tokens' ] );
        
        // Agenda cron job se não estiver agendado
        if ( ! wp_next_scheduled( 'dps_portal_cleanup_tokens' ) ) {
            wp_schedule_event( time(), 'hourly', 'dps_portal_cleanup_tokens' );
        }
    }

    /**
     * Retorna o nome completo da tabela com prefixo do WordPress
     *
     * @return string
     */
    private function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Cria ou atualiza a tabela de tokens se necessário
     */
    public function maybe_create_table() {
        $current_version = get_option( 'dps_portal_tokens_db_version', '0' );
        
        if ( version_compare( $current_version, self::DB_VERSION, '>=' ) ) {
            return;
        }

        $this->create_table();
        update_option( 'dps_portal_tokens_db_version', self::DB_VERSION );
    }

    /**
     * Cria a tabela de tokens
     */
    private function create_table() {
        global $wpdb;
        
        $table_name      = $this->get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            client_id bigint(20) unsigned NOT NULL,
            token_hash varchar(255) NOT NULL,
            type varchar(50) NOT NULL DEFAULT 'login',
            created_at datetime NOT NULL,
            expires_at datetime NOT NULL,
            used_at datetime DEFAULT NULL,
            revoked_at datetime DEFAULT NULL,
            ip_created varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY client_id (client_id),
            KEY token_hash (token_hash),
            KEY expires_at (expires_at),
            KEY type (type)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Gera um novo token de acesso para um cliente
     *
     * @param int    $client_id ID do cliente
     * @param string $type      Tipo do token ('login' ou 'first_access')
     * @param int    $expiration_minutes Minutos até expiração (padrão: 30)
     * @return string|false Token em texto plano ou false em caso de erro
     */
    public function generate_token( $client_id, $type = 'login', $expiration_minutes = null ) {
        global $wpdb;

        // Valida o client_id
        $client_id = absint( $client_id );
        if ( ! $client_id || 'dps_cliente' !== get_post_type( $client_id ) ) {
            return false;
        }

        // Valida o tipo
        $allowed_types = [ 'login', 'first_access', 'permanent' ];
        if ( ! in_array( $type, $allowed_types, true ) ) {
            $type = 'login';
        }

        // Define expiração
        // Tokens permanentes recebem data de expiração muito distante (10 anos)
        // Para revogá-los, usa-se a coluna revoked_at
        if ( 'permanent' === $type ) {
            $expiration_minutes = self::PERMANENT_EXPIRATION_MINUTES;
        } elseif ( null === $expiration_minutes ) {
            $expiration_minutes = self::DEFAULT_EXPIRATION_MINUTES;
        } else {
            $expiration_minutes = absint( $expiration_minutes );
            if ( $expiration_minutes < 1 ) {
                $expiration_minutes = self::DEFAULT_EXPIRATION_MINUTES;
            }
        }

        // Gera token aleatório seguro (32 bytes = 64 caracteres hex)
        $token_plain = bin2hex( random_bytes( 32 ) );
        
        // Hash do token para armazenamento seguro
        $token_hash = password_hash( $token_plain, PASSWORD_DEFAULT );

        // Prepara dados para inserção
        $now        = current_time( 'mysql' );
        $expires_at = date( 'Y-m-d H:i:s', strtotime( $now ) + ( $expiration_minutes * 60 ) );
        
        // Captura IP e User Agent (com suporte a proxy e IPv6)
        $ip_address = $this->get_client_ip_with_proxy_support();
        $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) 
            ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) 
            : '';

        // Insere token no banco
        $result = $wpdb->insert(
            $this->get_table_name(),
            [
                'client_id'  => $client_id,
                'token_hash' => $token_hash,
                'type'       => $type,
                'created_at' => $now,
                'expires_at' => $expires_at,
                'ip_created' => $ip_address,
                'user_agent' => $user_agent,
            ],
            [
                '%d', // client_id
                '%s', // token_hash
                '%s', // type
                '%s', // created_at
                '%s', // expires_at
                '%s', // ip_created
                '%s', // user_agent
            ]
        );

        if ( false === $result ) {
            return false;
        }

        // Retorna o token em texto plano (somente agora, não será mais recuperável)
        return $token_plain;
    }

    /**
     * Valida um token e retorna os dados se válido
     *
     * Implementa rate limiting para prevenir brute force:
     * - 5 tentativas por hora por IP
     * - Cache negativo de tokens inválidos (5 min)
     * - Logging de tentativas inválidas
     *
     * @param string $token_plain Token em texto plano
     * @return array|false Dados do token se válido, false se inválido
     */
    public function validate_token( $token_plain ) {
        global $wpdb;

        if ( empty( $token_plain ) || ! is_string( $token_plain ) ) {
            return false;
        }

        // SECURITY: Rate limiting (5 tentativas/hora por IP)
        $ip = $this->get_client_ip_with_proxy_support();
        $rate_limit_key = 'dps_token_attempts_' . md5( $ip );
        $attempts = get_transient( $rate_limit_key );
        
        if ( false === $attempts ) {
            $attempts = 0;
        }
        
        // Bloqueia se excedeu o limite
        if ( $attempts >= 5 ) {
            // Log da tentativa bloqueada
            do_action( 'dps_portal_rate_limit_exceeded', $ip, $token_plain );
            return false;
        }
        
        // SECURITY: Cache negativo para tokens claramente inválidos
        $token_cache_key = 'dps_invalid_token_' . md5( $token_plain );
        if ( get_transient( $token_cache_key ) ) {
            // Token já foi validado e é inválido, não tentar novamente
            $this->increment_rate_limit( $rate_limit_key, $attempts );
            return false;
        }

        $table_name = $this->get_table_name();
        $now        = current_time( 'mysql' );

        // Busca todos os tokens não expirados, não usados e não revogados
        $query = $wpdb->prepare(
            "SELECT * FROM {$table_name} 
            WHERE expires_at > %s 
            AND used_at IS NULL 
            AND revoked_at IS NULL 
            ORDER BY created_at DESC",
            $now
        );

        $tokens = $wpdb->get_results( $query, ARRAY_A );

        if ( empty( $tokens ) ) {
            // Nenhum token ativo encontrado
            $this->log_invalid_attempt( $token_plain, $ip, 'no_active_tokens' );
            $this->increment_rate_limit( $rate_limit_key, $attempts );
            set_transient( $token_cache_key, 1, 5 * MINUTE_IN_SECONDS );
            return false;
        }

        // Verifica cada token com password_verify
        foreach ( $tokens as $token_data ) {
            if ( password_verify( $token_plain, $token_data['token_hash'] ) ) {
                // Token válido encontrado - reseta contador de rate limit
                delete_transient( $rate_limit_key );
                delete_transient( $token_cache_key );
                return $token_data;
            }
        }

        // Token não encontrado
        $this->log_invalid_attempt( $token_plain, $ip, 'token_not_found' );
        $this->increment_rate_limit( $rate_limit_key, $attempts );
        set_transient( $token_cache_key, 1, 5 * MINUTE_IN_SECONDS );
        return false;
    }
    
    /**
     * Incrementa o contador de rate limiting
     *
     * @param string $key Chave do transient
     * @param int    $current_attempts Tentativas atuais
     */
    private function increment_rate_limit( $key, $current_attempts ) {
        set_transient( $key, $current_attempts + 1, HOUR_IN_SECONDS );
    }
    
    /**
     * Registra tentativa inválida de acesso com token
     *
     * @param string $token_plain Token tentado
     * @param string $ip          IP do cliente
     * @param string $reason      Razão da falha
     */
    private function log_invalid_attempt( $token_plain, $ip, $reason ) {
        $log_data = [
            'ip'           => $ip,
            'token_prefix' => substr( $token_plain, 0, 8 ) . '...',
            'reason'       => $reason,
            'timestamp'    => current_time( 'mysql' ),
            'user_agent'   => isset( $_SERVER['HTTP_USER_AGENT'] ) 
                ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) 
                : '',
        ];
        
        // Hook para extensibilidade (pode salvar em CPT, enviar alertas, etc)
        do_action( 'dps_portal_invalid_token_attempt', $log_data );
        
        // Salva log em transient (retenção de 30 dias)
        $log_key = 'dps_token_invalid_log_' . md5( $ip . $token_plain );
        set_transient( $log_key, $log_data, 30 * DAY_IN_SECONDS );
    }
    
    /**
     * Obtém o IP do cliente com suporte a proxies e IPv6
     *
     * Verifica headers de proxy (Cloudflare, AWS, Nginx) e valida IPv4/IPv6
     *
     * @return string IP do cliente ou string vazia
     */
    private function get_client_ip_with_proxy_support() {
        // Headers a verificar, em ordem de prioridade
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_REAL_IP',        // Nginx proxy
            'HTTP_X_FORWARDED_FOR',  // Proxy padrão
            'REMOTE_ADDR',           // Direto
        ];
        
        foreach ( $headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $ip_list = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
                
                // X-Forwarded-For pode ter múltiplos IPs (client, proxy1, proxy2)
                // Pega o primeiro (cliente real)
                if ( strpos( $ip_list, ',' ) !== false ) {
                    $ips = explode( ',', $ip_list );
                    $ip_list = trim( $ips[0] );
                }
                
                // Valida IPv4 ou IPv6
                if ( filter_var( $ip_list, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
                    return $ip_list;
                }
                
                if ( filter_var( $ip_list, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
                    return $ip_list;
                }
            }
        }
        
        return '';
    }

    /**
     * Marca um token como usado
     *
     * @param int $token_id ID do token
     * @return bool True se sucesso, false se erro
     */
    public function mark_as_used( $token_id ) {
        global $wpdb;

        $token_id = absint( $token_id );
        if ( ! $token_id ) {
            return false;
        }

        $result = $wpdb->update(
            $this->get_table_name(),
            [ 'used_at' => current_time( 'mysql' ) ],
            [ 'id' => $token_id ],
            [ '%s' ],
            [ '%d' ]
        );

        return false !== $result;
    }

    /**
     * Revoga todos os tokens ativos de um cliente
     *
     * @param int $client_id ID do cliente
     * @return int|false Número de tokens revogados ou false em caso de erro
     */
    public function revoke_tokens( $client_id ) {
        global $wpdb;

        $client_id = absint( $client_id );
        if ( ! $client_id ) {
            return false;
        }

        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->get_table_name()} 
                SET revoked_at = %s 
                WHERE client_id = %d 
                AND used_at IS NULL 
                AND revoked_at IS NULL",
                current_time( 'mysql' ),
                $client_id
            )
        );

        return $result;
    }

    /**
     * Remove tokens expirados há mais de 30 dias
     *
     * @return int|false Número de tokens removidos ou false em caso de erro
     */
    public function cleanup_expired_tokens() {
        global $wpdb;

        $threshold = date( 'Y-m-d H:i:s', strtotime( '-30 days' ) );

        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->get_table_name()} 
                WHERE expires_at < %s",
                $threshold
            )
        );

        return $result;
    }

    /**
     * Obtém estatísticas de tokens de um cliente
     *
     * @param int $client_id ID do cliente
     * @return array Estatísticas do cliente
     */
    public function get_client_stats( $client_id ) {
        global $wpdb;

        $client_id = absint( $client_id );
        if ( ! $client_id ) {
            return [
                'total_generated' => 0,
                'total_used'      => 0,
                'active_tokens'   => 0,
                'last_used_at'    => null,
            ];
        }

        $table_name = $this->get_table_name();
        $now        = current_time( 'mysql' );

        // Total gerados
        $total_generated = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE client_id = %d",
                $client_id
            )
        );

        // Total usados
        $total_used = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} 
                WHERE client_id = %d AND used_at IS NOT NULL",
                $client_id
            )
        );

        // Tokens ativos
        $active_tokens = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} 
                WHERE client_id = %d 
                AND expires_at > %s 
                AND used_at IS NULL 
                AND revoked_at IS NULL",
                $client_id,
                $now
            )
        );

        // Último uso
        $last_used_at = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT used_at FROM {$table_name} 
                WHERE client_id = %d AND used_at IS NOT NULL 
                ORDER BY used_at DESC LIMIT 1",
                $client_id
            )
        );

        return [
            'total_generated' => absint( $total_generated ),
            'total_used'      => absint( $total_used ),
            'active_tokens'   => absint( $active_tokens ),
            'last_used_at'    => $last_used_at,
        ];
    }

    /**
     * Gera URL de acesso com token
     *
     * @param string $token_plain Token em texto plano
     * @return string URL completa com token
     */
    public function generate_access_url( $token_plain ) {
        $portal_url = dps_get_portal_page_url();
        return add_query_arg( 'dps_token', $token_plain, $portal_url );
    }

    /**
     * Obtém tokens permanentes ativos de um cliente
     *
     * @param int $client_id ID do cliente
     * @return array Lista de tokens permanentes ativos com suas URLs
     */
    public function get_active_permanent_tokens( $client_id ) {
        global $wpdb;

        $client_id = absint( $client_id );
        if ( ! $client_id ) {
            return [];
        }

        $table_name = $this->get_table_name();
        $now        = current_time( 'mysql' );

        $tokens = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, created_at, expires_at, used_at, ip_created
                 FROM {$table_name} 
                 WHERE client_id = %d 
                   AND type = 'permanent'
                   AND expires_at > %s 
                   AND revoked_at IS NULL
                 ORDER BY created_at DESC",
                $client_id,
                $now
            ),
            ARRAY_A
        );

        return $tokens ? $tokens : [];
    }

    /**
     * Registra acesso ao portal na tabela de histórico
     * 
     * @since 2.5.0
     * @param int    $client_id ID do cliente
     * @param int    $token_id  ID do token usado (opcional)
     * @param string $ip        Endereço IP
     * @param string $user_agent User agent do navegador
     * @return bool True se sucesso
     */
    public function log_access( $client_id, $token_id = 0, $ip = '', $user_agent = '' ) {
        global $wpdb;

        $client_id = absint( $client_id );
        if ( ! $client_id ) {
            return false;
        }

        // Obtém IP e user agent se não fornecidos
        if ( empty( $ip ) ) {
            $ip = $this->get_client_ip_with_proxy_support();
        }
        if ( empty( $user_agent ) && isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
            // Sanitiza e trunca user agent para evitar dados muito longos
            $raw_user_agent = wp_unslash( $_SERVER['HTTP_USER_AGENT'] );
            // Valida que é string antes de processar
            if ( is_string( $raw_user_agent ) ) {
                $user_agent = sanitize_text_field( $raw_user_agent );
            } else {
                $user_agent = '';
            }
        }

        // Armazena no meta do cliente como histórico simples
        $access_log = get_post_meta( $client_id, '_dps_portal_access_log', true );
        if ( ! is_array( $access_log ) ) {
            $access_log = [];
        }

        // Adiciona novo registro (trunca user agent com a constante)
        $access_log[] = [
            'timestamp'  => current_time( 'mysql' ),
            'ip'         => $ip,
            'user_agent' => substr( $user_agent, 0, self::MAX_USER_AGENT_LENGTH ),
            'token_id'   => $token_id,
        ];

        // Mantém apenas últimos 50 acessos
        if ( count( $access_log ) > 50 ) {
            $access_log = array_slice( $access_log, -50 );
        }

        update_post_meta( $client_id, '_dps_portal_access_log', $access_log );
        
        // Atualiza timestamp de último login
        update_post_meta( $client_id, '_dps_portal_last_login', current_time( 'mysql' ) );
        update_post_meta( $client_id, '_dps_portal_last_login_ip', $ip );

        return true;
    }

    /**
     * Obtém histórico de acessos de um cliente
     * 
     * @since 2.5.0
     * @param int $client_id ID do cliente
     * @param int $limit     Limite de registros (padrão: 20)
     * @return array Lista de acessos
     */
    public function get_access_history( $client_id, $limit = 20 ) {
        $client_id = absint( $client_id );
        if ( ! $client_id ) {
            return [];
        }

        $access_log = get_post_meta( $client_id, '_dps_portal_access_log', true );
        if ( ! is_array( $access_log ) ) {
            return [];
        }

        // Retorna em ordem reversa (mais recente primeiro)
        $access_log = array_reverse( $access_log );
        
        return array_slice( $access_log, 0, $limit );
    }

    /**
     * Obtém o último login de um cliente
     * 
     * @since 2.5.0
     * @param int $client_id ID do cliente
     * @return array|null Dados do último login ou null
     */
    public function get_last_login( $client_id ) {
        $client_id = absint( $client_id );
        if ( ! $client_id ) {
            return null;
        }

        $last_login    = get_post_meta( $client_id, '_dps_portal_last_login', true );
        $last_login_ip = get_post_meta( $client_id, '_dps_portal_last_login_ip', true );

        if ( ! $last_login ) {
            return null;
        }

        return [
            'timestamp' => $last_login,
            'ip'        => $last_login_ip,
        ];
    }
}

endif;
