<?php
/**
 * Repositório de Conversas e Mensagens do AI Add-on.
 *
 * Responsável por:
 * - Criar e gerenciar conversas (chat, WhatsApp, portal, admin)
 * - Adicionar mensagens a conversas
 * - Recuperar histórico de conversas
 * - Suportar múltiplos canais (web_chat, portal, whatsapp, admin_specialist)
 *
 * @package DPS_AI_Addon
 * @since 1.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe de repositório para conversas de IA.
 */
class DPS_AI_Conversations_Repository {

    /**
     * Nome da tabela de conversas.
     *
     * @var string
     */
    const CONVERSATIONS_TABLE = 'dps_ai_conversations';

    /**
     * Nome da tabela de mensagens.
     *
     * @var string
     */
    const MESSAGES_TABLE = 'dps_ai_messages';

    /**
     * Canais válidos para conversas.
     *
     * @var array
     */
    const VALID_CHANNELS = [
        'web_chat',         // Chat público para visitantes
        'portal',           // Portal do cliente autenticado
        'whatsapp',         // WhatsApp Business (futuro)
        'admin_specialist', // Modo especialista para admin (futuro)
    ];

    /**
     * Tipos de remetente válidos.
     *
     * @var array
     */
    const VALID_SENDER_TYPES = [
        'user',      // Mensagem do cliente/visitante
        'assistant', // Resposta da IA
        'system',    // Mensagem do sistema (ex: "Conversa iniciada")
    ];

    /**
     * Instância única (singleton).
     *
     * @var DPS_AI_Conversations_Repository|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única.
     *
     * @return DPS_AI_Conversations_Repository
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado.
     */
    private function __construct() {
        // Nenhum hook necessário por enquanto
    }

    /**
     * Cria as tabelas de conversas e mensagens se não existirem.
     *
     * Esta função deve ser chamada durante ativação/upgrade do plugin.
     */
    public static function maybe_create_tables() {
        global $wpdb;

        $charset_collate     = $wpdb->get_charset_collate();
        $conversations_table = $wpdb->prefix . self::CONVERSATIONS_TABLE;
        $messages_table      = $wpdb->prefix . self::MESSAGES_TABLE;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // IMPORTANTE: dbDelta() do WordPress tem requisitos estritos de formatação SQL:
        // - Exatamente 2 espaços entre 'PRIMARY KEY' e '(' (não 1)
        // - Usar 'KEY' em vez de 'INDEX' para índices secundários
        // - Um espaço após cada vírgula na definição de colunas
        // Ref: https://developer.wordpress.org/reference/functions/dbdelta/

        // Tabela de conversas
        $sql_conversations = "CREATE TABLE IF NOT EXISTS {$conversations_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id BIGINT(20) UNSIGNED DEFAULT NULL COMMENT 'ID do cliente (NULL para visitantes não identificados)',
            channel VARCHAR(50) NOT NULL DEFAULT 'web_chat' COMMENT 'Canal da conversa: web_chat, portal, whatsapp, admin_specialist',
            session_identifier VARCHAR(255) DEFAULT NULL COMMENT 'Identificador de sessão para visitantes não logados',
            started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_activity_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(20) NOT NULL DEFAULT 'open' COMMENT 'Status: open, closed',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY customer_idx (customer_id),
            KEY channel_idx (channel),
            KEY session_idx (session_identifier),
            KEY status_idx (status),
            KEY last_activity_idx (last_activity_at)
        ) {$charset_collate};";

        // Tabela de mensagens
        $sql_messages = "CREATE TABLE IF NOT EXISTS {$messages_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            conversation_id BIGINT(20) UNSIGNED NOT NULL,
            sender_type VARCHAR(20) NOT NULL COMMENT 'Tipo: user, assistant, system',
            sender_identifier VARCHAR(255) DEFAULT NULL COMMENT 'ID do usuário, telefone, IP, etc',
            message_text TEXT NOT NULL,
            message_metadata TEXT DEFAULT NULL COMMENT 'JSON com metadados: tokens, custo, modelo, etc',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY conversation_idx (conversation_id),
            KEY sender_type_idx (sender_type),
            KEY created_at_idx (created_at)
        ) {$charset_collate};";

        dbDelta( $sql_conversations );
        dbDelta( $sql_messages );
    }

    /**
     * Cria uma nova conversa.
     *
     * @param array $params {
     *     Parâmetros da conversa.
     *
     *     @type int|null    $customer_id        ID do cliente (NULL para visitantes).
     *     @type string      $channel            Canal: web_chat, portal, whatsapp, admin_specialist.
     *     @type string|null $session_identifier Identificador de sessão (para visitantes).
     *     @type string      $status             Status: open, closed. Default: open.
     * }
     *
     * @return int|false ID da conversa criada ou false em caso de erro.
     */
    public function create_conversation( $params ) {
        global $wpdb;

        // Validação de parâmetros
        $channel = isset( $params['channel'] ) ? sanitize_text_field( $params['channel'] ) : 'web_chat';
        if ( ! in_array( $channel, self::VALID_CHANNELS, true ) ) {
            dps_ai_log( "Canal inválido ao criar conversa: {$channel}", 'error' );
            return false;
        }

        $customer_id        = isset( $params['customer_id'] ) ? absint( $params['customer_id'] ) : null;
        $session_identifier = isset( $params['session_identifier'] ) ? sanitize_text_field( $params['session_identifier'] ) : null;
        $status             = isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : 'open';

        // Validação: se não tem customer_id, deve ter session_identifier
        if ( ! $customer_id && ! $session_identifier ) {
            dps_ai_log( 'Erro ao criar conversa: nem customer_id nem session_identifier foram fornecidos', 'error' );
            return false;
        }

        $table_name = $wpdb->prefix . self::CONVERSATIONS_TABLE;
        $now        = current_time( 'mysql' );

        // Insere conversa
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $inserted = $wpdb->insert(
            $table_name,
            [
                'customer_id'        => $customer_id,
                'channel'            => $channel,
                'session_identifier' => $session_identifier,
                'started_at'         => $now,
                'last_activity_at'   => $now,
                'status'             => $status,
            ],
            [
                '%d', // customer_id
                '%s', // channel
                '%s', // session_identifier
                '%s', // started_at
                '%s', // last_activity_at
                '%s', // status
            ]
        );

        if ( false === $inserted ) {
            dps_ai_log( 'Erro ao inserir conversa no banco de dados: ' . $wpdb->last_error, 'error' );
            return false;
        }

        $conversation_id = $wpdb->insert_id;
        dps_ai_log( "Conversa criada com sucesso: ID {$conversation_id}, canal {$channel}" );

        return $conversation_id;
    }

    /**
     * Adiciona uma mensagem a uma conversa.
     *
     * @param int   $conversation_id ID da conversa.
     * @param array $data {
     *     Dados da mensagem.
     *
     *     @type string      $sender_type       Tipo: user, assistant, system.
     *     @type string|null $sender_identifier Identificador do remetente (opcional).
     *     @type string      $message_text      Texto da mensagem.
     *     @type array|null  $metadata          Array com metadados (será convertido para JSON).
     * }
     *
     * @return int|false ID da mensagem criada ou false em caso de erro.
     */
    public function add_message( $conversation_id, $data ) {
        global $wpdb;

        // Validação
        $conversation_id = absint( $conversation_id );
        if ( ! $conversation_id ) {
            dps_ai_log( 'ID de conversa inválido ao adicionar mensagem', 'error' );
            return false;
        }

        $sender_type = isset( $data['sender_type'] ) ? sanitize_text_field( $data['sender_type'] ) : 'user';
        if ( ! in_array( $sender_type, self::VALID_SENDER_TYPES, true ) ) {
            dps_ai_log( "Tipo de remetente inválido: {$sender_type}", 'error' );
            return false;
        }

        $message_text = isset( $data['message_text'] ) ? $data['message_text'] : '';
        if ( empty( $message_text ) ) {
            dps_ai_log( 'Texto de mensagem vazio ao adicionar mensagem', 'error' );
            return false;
        }

        $sender_identifier = isset( $data['sender_identifier'] ) ? sanitize_text_field( $data['sender_identifier'] ) : null;
        $metadata          = isset( $data['metadata'] ) && is_array( $data['metadata'] ) ? wp_json_encode( $data['metadata'] ) : null;

        $messages_table = $wpdb->prefix . self::MESSAGES_TABLE;

        // Insere mensagem
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $inserted = $wpdb->insert(
            $messages_table,
            [
                'conversation_id'   => $conversation_id,
                'sender_type'       => $sender_type,
                'sender_identifier' => $sender_identifier,
                'message_text'      => $message_text,
                'message_metadata'  => $metadata,
            ],
            [
                '%d', // conversation_id
                '%s', // sender_type
                '%s', // sender_identifier
                '%s', // message_text
                '%s', // message_metadata
            ]
        );

        if ( false === $inserted ) {
            dps_ai_log( 'Erro ao inserir mensagem no banco de dados: ' . $wpdb->last_error, 'error' );
            return false;
        }

        $message_id = $wpdb->insert_id;

        // Atualiza last_activity_at da conversa
        $this->update_conversation_activity( $conversation_id );

        return $message_id;
    }

    /**
     * Atualiza o timestamp de última atividade de uma conversa.
     *
     * @param int $conversation_id ID da conversa.
     *
     * @return bool True se atualizado com sucesso, false caso contrário.
     */
    private function update_conversation_activity( $conversation_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::CONVERSATIONS_TABLE;
        $now        = current_time( 'mysql' );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = $wpdb->update(
            $table_name,
            [ 'last_activity_at' => $now ],
            [ 'id' => $conversation_id ],
            [ '%s' ],
            [ '%d' ]
        );

        return false !== $updated;
    }

    /**
     * Recupera uma conversa por ID.
     *
     * @param int $conversation_id ID da conversa.
     *
     * @return object|null Objeto com dados da conversa ou null se não encontrada.
     */
    public function get_conversation( $conversation_id ) {
        global $wpdb;

        $table_name      = $wpdb->prefix . self::CONVERSATIONS_TABLE;
        $conversation_id = absint( $conversation_id );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $conversation = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d",
                $conversation_id
            )
        );

        return $conversation;
    }

    /**
     * Recupera conversas de um cliente.
     *
     * @param int    $customer_id ID do cliente.
     * @param string $channel     Canal específico (opcional).
     * @param int    $limit       Limite de resultados (padrão: 10).
     *
     * @return array Array de objetos de conversas.
     */
    public function get_conversations_by_customer( $customer_id, $channel = '', $limit = 10 ) {
        global $wpdb;

        $table_name  = $wpdb->prefix . self::CONVERSATIONS_TABLE;
        $customer_id = absint( $customer_id );
        $limit       = absint( $limit );

        if ( ! $customer_id ) {
            return [];
        }

        $sql = "SELECT * FROM {$table_name} WHERE customer_id = %d";
        $args = [ $customer_id ];

        if ( ! empty( $channel ) && in_array( $channel, self::VALID_CHANNELS, true ) ) {
            $sql .= " AND channel = %s";
            $args[] = $channel;
        }

        $sql .= " ORDER BY last_activity_at DESC LIMIT %d";
        $args[] = $limit;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
        $conversations = $wpdb->get_results(
            $wpdb->prepare( $sql, $args )
        );

        return $conversations;
    }

    /**
     * Recupera conversa ativa por session_identifier.
     *
     * Útil para continuar conversa de visitantes não logados.
     *
     * @param string $session_identifier Identificador de sessão.
     * @param string $channel            Canal.
     *
     * @return object|null Objeto da conversa ou null se não encontrada.
     */
    public function get_active_conversation_by_session( $session_identifier, $channel = 'web_chat' ) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::CONVERSATIONS_TABLE;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $conversation = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} 
                WHERE session_identifier = %s 
                AND channel = %s 
                AND status = 'open'
                ORDER BY last_activity_at DESC 
                LIMIT 1",
                $session_identifier,
                $channel
            )
        );

        return $conversation;
    }

    /**
     * Recupera mensagens de uma conversa.
     *
     * @param int    $conversation_id ID da conversa.
     * @param string $order           Ordem: ASC (mais antigas primeiro) ou DESC (mais recentes primeiro). Default: ASC.
     * @param int    $limit           Limite de mensagens (0 = sem limite). Default: 0.
     *
     * @return array Array de objetos de mensagens.
     */
    public function get_messages( $conversation_id, $order = 'ASC', $limit = 0 ) {
        global $wpdb;

        $messages_table  = $wpdb->prefix . self::MESSAGES_TABLE;
        $conversation_id = absint( $conversation_id );
        $order           = ( 'DESC' === strtoupper( $order ) ) ? 'DESC' : 'ASC';
        $limit           = absint( $limit );

        if ( ! $conversation_id ) {
            return [];
        }

        $sql = $wpdb->prepare(
            "SELECT * FROM {$messages_table} WHERE conversation_id = %d ORDER BY created_at {$order}",
            $conversation_id
        );

        if ( $limit > 0 ) {
            $sql .= $wpdb->prepare( ' LIMIT %d', $limit );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
        $messages = $wpdb->get_results( $sql );

        // Decodifica metadata JSON em cada mensagem
        foreach ( $messages as &$message ) {
            if ( ! empty( $message->message_metadata ) ) {
                $message->metadata_decoded = json_decode( $message->message_metadata, true );
            }
        }

        return $messages;
    }

    /**
     * Lista conversas com filtros (para admin).
     *
     * @param array $args {
     *     Argumentos de filtro.
     *
     *     @type string $channel    Canal específico (opcional).
     *     @type string $status     Status (open, closed).
     *     @type string $start_date Data de início (Y-m-d).
     *     @type string $end_date   Data de fim (Y-m-d).
     *     @type int    $limit      Limite de resultados. Default: 50.
     *     @type int    $offset     Offset para paginação. Default: 0.
     * }
     *
     * @return array Array de objetos de conversas.
     */
    public function list_conversations( $args = [] ) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::CONVERSATIONS_TABLE;

        $defaults = [
            'channel'    => '',
            'status'     => '',
            'start_date' => '',
            'end_date'   => '',
            'limit'      => 50,
            'offset'     => 0,
        ];

        $args = wp_parse_args( $args, $defaults );

        $where = [];
        $prepare_args = [];

        // Filtro por canal
        if ( ! empty( $args['channel'] ) && in_array( $args['channel'], self::VALID_CHANNELS, true ) ) {
            $where[] = 'channel = %s';
            $prepare_args[] = $args['channel'];
        }

        // Filtro por status
        if ( ! empty( $args['status'] ) ) {
            $where[] = 'status = %s';
            $prepare_args[] = $args['status'];
        }

        // Filtro por data de início
        if ( ! empty( $args['start_date'] ) ) {
            $where[] = 'started_at >= %s';
            $prepare_args[] = $args['start_date'] . ' 00:00:00';
        }

        // Filtro por data de fim
        if ( ! empty( $args['end_date'] ) ) {
            $where[] = 'started_at <= %s';
            $prepare_args[] = $args['end_date'] . ' 23:59:59';
        }

        $where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

        $sql = "SELECT * FROM {$table_name} {$where_clause} ORDER BY last_activity_at DESC";

        // Adiciona limite e offset
        $prepare_args[] = absint( $args['limit'] );
        $prepare_args[] = absint( $args['offset'] );
        $sql .= ' LIMIT %d OFFSET %d';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
        $conversations = $wpdb->get_results(
            empty( $prepare_args ) ? $sql : $wpdb->prepare( $sql, $prepare_args )
        );

        return $conversations;
    }

    /**
     * Conta total de conversas (para paginação).
     *
     * @param array $args Mesmos filtros de list_conversations().
     *
     * @return int Total de conversas.
     */
    public function count_conversations( $args = [] ) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::CONVERSATIONS_TABLE;

        $where = [];
        $prepare_args = [];

        // Filtro por canal
        if ( ! empty( $args['channel'] ) && in_array( $args['channel'], self::VALID_CHANNELS, true ) ) {
            $where[] = 'channel = %s';
            $prepare_args[] = $args['channel'];
        }

        // Filtro por status
        if ( ! empty( $args['status'] ) ) {
            $where[] = 'status = %s';
            $prepare_args[] = $args['status'];
        }

        // Filtro por data de início
        if ( ! empty( $args['start_date'] ) ) {
            $where[] = 'started_at >= %s';
            $prepare_args[] = $args['start_date'] . ' 00:00:00';
        }

        // Filtro por data de fim
        if ( ! empty( $args['end_date'] ) ) {
            $where[] = 'started_at <= %s';
            $prepare_args[] = $args['end_date'] . ' 23:59:59';
        }

        $where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

        $sql = "SELECT COUNT(*) FROM {$table_name} {$where_clause}";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
        $total = $wpdb->get_var(
            empty( $prepare_args ) ? $sql : $wpdb->prepare( $sql, $prepare_args )
        );

        return (int) $total;
    }

    /**
     * Fecha uma conversa (atualiza status para 'closed').
     *
     * @param int $conversation_id ID da conversa.
     *
     * @return bool True se fechada com sucesso, false caso contrário.
     */
    public function close_conversation( $conversation_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::CONVERSATIONS_TABLE;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = $wpdb->update(
            $table_name,
            [ 'status' => 'closed' ],
            [ 'id' => absint( $conversation_id ) ],
            [ '%s' ],
            [ '%d' ]
        );

        return false !== $updated;
    }
}
