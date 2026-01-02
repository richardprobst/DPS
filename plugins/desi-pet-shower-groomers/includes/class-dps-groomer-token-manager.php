<?php
/**
 * Gerenciador de tokens de acesso ao Portal do Groomer
 *
 * Esta classe gerencia a criação, validação, revogação e limpeza de tokens
 * de autenticação para o Portal do Groomer. Tokens são magic links que
 * permitem acesso sem senha.
 *
 * @package DPS_Groomers_Addon
 * @since 1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'DPS_Groomer_Token_Manager' ) ) :

/**
 * Classe responsável pelo gerenciamento de tokens do groomer
 */
final class DPS_Groomer_Token_Manager {

    /**
     * Nome da tabela de tokens (sem prefixo)
     *
     * @var string
     */
    const TABLE_NAME = 'dps_groomer_tokens';

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
     * Única instância da classe
     *
     * @var DPS_Groomer_Token_Manager|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única (singleton)
     *
     * @return DPS_Groomer_Token_Manager
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
        add_action( 'dps_groomer_cleanup_tokens', [ $this, 'cleanup_expired_tokens' ] );
        
        // Agenda cron job se não estiver agendado
        if ( ! wp_next_scheduled( 'dps_groomer_cleanup_tokens' ) ) {
            wp_schedule_event( time(), 'hourly', 'dps_groomer_cleanup_tokens' );
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
        $current_version = get_option( 'dps_groomer_tokens_db_version', '0' );
        
        if ( version_compare( $current_version, self::DB_VERSION, '>=' ) ) {
            return;
        }

        $this->create_table();
        update_option( 'dps_groomer_tokens_db_version', self::DB_VERSION );
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
            groomer_id bigint(20) unsigned NOT NULL,
            token_hash varchar(255) NOT NULL,
            type varchar(50) NOT NULL DEFAULT 'login',
            created_at datetime NOT NULL,
            expires_at datetime NOT NULL,
            used_at datetime DEFAULT NULL,
            revoked_at datetime DEFAULT NULL,
            ip_created varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY groomer_id (groomer_id),
            KEY token_hash (token_hash),
            KEY expires_at (expires_at),
            KEY type (type)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Gera um novo token de acesso para um groomer
     *
     * @param int    $groomer_id ID do usuário groomer
     * @param string $type       Tipo do token ('login' ou 'permanent')
     * @param int    $expiration_minutes Minutos até expiração (padrão: 30)
     * @return string|false Token em texto plano ou false em caso de erro
     */
    public function generate_token( $groomer_id, $type = 'login', $expiration_minutes = null ) {
        global $wpdb;

        // Valida o groomer_id
        $groomer_id = absint( $groomer_id );
        $user       = get_user_by( 'id', $groomer_id );
        
        if ( ! $user || ! in_array( 'dps_groomer', (array) $user->roles, true ) ) {
            return false;
        }

        // Valida o tipo
        $allowed_types = [ 'login', 'permanent' ];
        if ( ! in_array( $type, $allowed_types, true ) ) {
            $type = 'login';
        }

        // Define expiração
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
        // Usa current_time consistentemente para evitar problemas de timezone
        $now        = current_time( 'mysql' );
        $expires_at = date( 'Y-m-d H:i:s', strtotime( $now ) + ( $expiration_minutes * 60 ) );
        
        // Captura IP e User Agent
        $ip_address = isset( $_SERVER['REMOTE_ADDR'] ) 
            ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) 
            : '';
        $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) 
            ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) 
            : '';

        // Insere token no banco
        $result = $wpdb->insert(
            $this->get_table_name(),
            [
                'groomer_id' => $groomer_id,
                'token_hash' => $token_hash,
                'type'       => $type,
                'created_at' => $now,
                'expires_at' => $expires_at,
                'ip_created' => $ip_address,
                'user_agent' => $user_agent,
            ],
            [
                '%d', // groomer_id
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
     * @param string $token_plain Token em texto plano
     * @return array|false Dados do token se válido, false se inválido
     */
    public function validate_token( $token_plain ) {
        global $wpdb;

        if ( empty( $token_plain ) || ! is_string( $token_plain ) ) {
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
            return false;
        }

        // Verifica cada token com password_verify
        foreach ( $tokens as $token_data ) {
            if ( password_verify( $token_plain, $token_data['token_hash'] ) ) {
                // Token válido encontrado
                return $token_data;
            }
        }

        return false;
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
     * Revoga todos os tokens ativos de um groomer
     *
     * @param int $groomer_id ID do groomer
     * @return int|false Número de tokens revogados ou false em caso de erro
     */
    public function revoke_tokens( $groomer_id ) {
        global $wpdb;

        $groomer_id = absint( $groomer_id );
        if ( ! $groomer_id ) {
            return false;
        }

        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->get_table_name()} 
                SET revoked_at = %s 
                WHERE groomer_id = %d 
                AND used_at IS NULL 
                AND revoked_at IS NULL",
                current_time( 'mysql' ),
                $groomer_id
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

        $threshold = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );

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
     * Obtém estatísticas de tokens de um groomer
     *
     * @param int $groomer_id ID do groomer
     * @return array Estatísticas do groomer
     */
    public function get_groomer_stats( $groomer_id ) {
        global $wpdb;

        $groomer_id = absint( $groomer_id );
        if ( ! $groomer_id ) {
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
                "SELECT COUNT(*) FROM {$table_name} WHERE groomer_id = %d",
                $groomer_id
            )
        );

        // Total usados
        $total_used = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} 
                WHERE groomer_id = %d AND used_at IS NOT NULL",
                $groomer_id
            )
        );

        // Tokens ativos
        $active_tokens = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} 
                WHERE groomer_id = %d 
                AND expires_at > %s 
                AND used_at IS NULL 
                AND revoked_at IS NULL",
                $groomer_id,
                $now
            )
        );

        // Último uso
        $last_used_at = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT used_at FROM {$table_name} 
                WHERE groomer_id = %d AND used_at IS NOT NULL 
                ORDER BY used_at DESC LIMIT 1",
                $groomer_id
            )
        );

        return [
            'total_generated' => (int) $total_generated,
            'total_used'      => (int) $total_used,
            'active_tokens'   => (int) $active_tokens,
            'last_used_at'    => $last_used_at,
        ];
    }

    /**
     * Lista todos os tokens ativos de um groomer
     *
     * @param int $groomer_id ID do groomer
     * @return array Lista de tokens ativos
     */
    public function get_active_tokens( $groomer_id ) {
        global $wpdb;

        $groomer_id = absint( $groomer_id );
        if ( ! $groomer_id ) {
            return [];
        }

        $table_name = $this->get_table_name();
        $now        = current_time( 'mysql' );

        $tokens = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, type, created_at, expires_at, ip_created 
                FROM {$table_name} 
                WHERE groomer_id = %d 
                AND expires_at > %s 
                AND used_at IS NULL 
                AND revoked_at IS NULL 
                ORDER BY created_at DESC",
                $groomer_id,
                $now
            ),
            ARRAY_A
        );

        return $tokens ? $tokens : [];
    }

    /**
     * Revoga um token específico
     *
     * @param int $token_id   ID do token
     * @param int $groomer_id ID do groomer (para validação)
     * @return bool True se revogado com sucesso
     */
    public function revoke_single_token( $token_id, $groomer_id ) {
        global $wpdb;

        $token_id   = absint( $token_id );
        $groomer_id = absint( $groomer_id );

        if ( ! $token_id || ! $groomer_id ) {
            return false;
        }

        $result = $wpdb->update(
            $this->get_table_name(),
            [ 'revoked_at' => current_time( 'mysql' ) ],
            [
                'id'         => $token_id,
                'groomer_id' => $groomer_id,
            ],
            [ '%s' ],
            [ '%d', '%d' ]
        );

        return false !== $result && $result > 0;
    }
}

endif;
