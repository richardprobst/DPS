<?php
/**
 * Gerenciador de histórico de comunicações
 *
 * Esta classe gerencia a tabela de histórico de comunicações,
 * registrando todas as mensagens enviadas (WhatsApp, e-mail, SMS).
 *
 * @package DesiPetShower
 * @subpackage Communications
 * @since 0.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe de histórico de comunicações
 */
class DPS_Communications_History {

    /**
     * Versão do banco de dados
     */
    const DB_VERSION = '1.0.0';

    /**
     * Option key para versão do banco
     */
    const DB_VERSION_OPTION = 'dps_comm_history_db_version';

    /**
     * Status possíveis de uma comunicação
     */
    const STATUS_PENDING   = 'pending';
    const STATUS_SENT      = 'sent';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_READ      = 'read';
    const STATUS_FAILED    = 'failed';
    const STATUS_RETRYING  = 'retrying';

    /**
     * Canais de comunicação
     */
    const CHANNEL_WHATSAPP = 'whatsapp';
    const CHANNEL_EMAIL    = 'email';
    const CHANNEL_SMS      = 'sms';

    /**
     * Instância singleton
     *
     * @var DPS_Communications_History|null
     */
    private static $instance = null;

    /**
     * Obtém instância singleton
     *
     * @return DPS_Communications_History
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor
     */
    private function __construct() {
        // Verifica e cria tabela se necessário
        add_action( 'plugins_loaded', [ $this, 'maybe_create_table' ], 5 );
    }

    /**
     * Retorna o nome da tabela de histórico
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'dps_comm_history';
    }

    /**
     * Verifica se a tabela existe
     *
     * @return bool
     */
    public static function table_exists() {
        global $wpdb;
        $table_name = self::get_table_name();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
    }

    /**
     * Cria ou atualiza a tabela de histórico
     */
    public function maybe_create_table() {
        $installed_version = get_option( self::DB_VERSION_OPTION, '0' );

        if ( version_compare( $installed_version, self::DB_VERSION, '>=' ) ) {
            return;
        }

        global $wpdb;

        $table_name      = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            channel varchar(20) NOT NULL,
            recipient varchar(255) NOT NULL,
            subject varchar(500) DEFAULT NULL,
            message_preview varchar(500) DEFAULT NULL,
            message_type varchar(50) DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            external_id varchar(255) DEFAULT NULL,
            client_id bigint(20) DEFAULT NULL,
            appointment_id bigint(20) DEFAULT NULL,
            retry_count int(11) DEFAULT 0,
            last_error text DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            delivered_at datetime DEFAULT NULL,
            read_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY channel (channel),
            KEY status (status),
            KEY client_id (client_id),
            KEY appointment_id (appointment_id),
            KEY external_id (external_id),
            KEY created_at (created_at)
        ) {$charset_collate};";

        dbDelta( $sql );
        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
    }

    /**
     * Registra uma nova comunicação no histórico
     *
     * @param string $channel       Canal (whatsapp, email, sms)
     * @param string $recipient     Destinatário (telefone ou e-mail)
     * @param string $message       Mensagem enviada
     * @param array  $context       Contexto adicional
     * @return int|false            ID do registro ou false em caso de erro
     */
    public function log_communication( $channel, $recipient, $message, $context = [] ) {
        global $wpdb;

        if ( ! self::table_exists() ) {
            return false;
        }

        $now = current_time( 'mysql', true );

        // Extrai dados do contexto
        $client_id      = isset( $context['client_id'] ) ? absint( $context['client_id'] ) : null;
        $appointment_id = isset( $context['appointment_id'] ) ? absint( $context['appointment_id'] ) : null;
        $message_type   = isset( $context['type'] ) ? sanitize_text_field( $context['type'] ) : null;
        $subject        = isset( $context['subject'] ) ? sanitize_text_field( $context['subject'] ) : null;

        // Preview da mensagem (primeiros 500 caracteres, sem dados sensíveis)
        $message_preview = mb_substr( $message, 0, 500 );

        // Metadados adicionais (sem dados sensíveis)
        $safe_metadata = $this->sanitize_metadata( $context );

        $data = [
            'channel'         => sanitize_text_field( $channel ),
            'recipient'       => sanitize_text_field( $recipient ),
            'subject'         => $subject,
            'message_preview' => $message_preview,
            'message_type'    => $message_type,
            'status'          => self::STATUS_PENDING,
            'client_id'       => $client_id,
            'appointment_id'  => $appointment_id,
            'retry_count'     => 0,
            'metadata'        => wp_json_encode( $safe_metadata ),
            'created_at'      => $now,
            'updated_at'      => $now,
        ];

        $formats = [
            '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s',
        ];

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->insert( self::get_table_name(), $data, $formats );

        if ( false === $result ) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Atualiza o status de uma comunicação
     *
     * @param int    $history_id  ID do registro
     * @param string $status      Novo status
     * @param array  $extra_data  Dados extras para atualizar
     * @return bool
     */
    public function update_status( $history_id, $status, $extra_data = [] ) {
        global $wpdb;

        if ( ! self::table_exists() ) {
            return false;
        }

        $history_id = absint( $history_id );
        if ( ! $history_id ) {
            return false;
        }

        // Valida status
        $valid_statuses = [
            self::STATUS_PENDING,
            self::STATUS_SENT,
            self::STATUS_DELIVERED,
            self::STATUS_READ,
            self::STATUS_FAILED,
            self::STATUS_RETRYING,
        ];

        if ( ! in_array( $status, $valid_statuses, true ) ) {
            return false;
        }

        $data    = [ 'status' => $status, 'updated_at' => current_time( 'mysql', true ) ];
        $formats = [ '%s', '%s' ];

        // Adiciona dados extras
        if ( isset( $extra_data['external_id'] ) ) {
            $data['external_id'] = sanitize_text_field( $extra_data['external_id'] );
            $formats[]           = '%s';
        }

        if ( isset( $extra_data['last_error'] ) ) {
            $data['last_error'] = sanitize_text_field( $extra_data['last_error'] );
            $formats[]          = '%s';
        }

        if ( isset( $extra_data['retry_count'] ) ) {
            $data['retry_count'] = absint( $extra_data['retry_count'] );
            $formats[]           = '%d';
        }

        // Atualiza timestamps de entrega/leitura
        if ( self::STATUS_DELIVERED === $status && ! isset( $extra_data['delivered_at'] ) ) {
            $data['delivered_at'] = current_time( 'mysql', true );
            $formats[]            = '%s';
        }

        if ( self::STATUS_READ === $status && ! isset( $extra_data['read_at'] ) ) {
            $data['read_at'] = current_time( 'mysql', true );
            $formats[]       = '%s';
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->update(
            self::get_table_name(),
            $data,
            [ 'id' => $history_id ],
            $formats,
            [ '%d' ]
        );

        return false !== $result;
    }

    /**
     * Busca comunicação por external_id (ID do gateway)
     *
     * @param string $external_id ID externo
     * @return object|null
     */
    public function get_by_external_id( $external_id ) {
        global $wpdb;

        if ( ! self::table_exists() ) {
            return null;
        }

        $table = self::get_table_name();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE external_id = %s",
                sanitize_text_field( $external_id )
            )
        );
    }

    /**
     * Busca comunicações por cliente
     *
     * @param int   $client_id ID do cliente
     * @param int   $limit     Limite de resultados
     * @param int   $offset    Offset para paginação
     * @return array
     */
    public function get_by_client( $client_id, $limit = 20, $offset = 0 ) {
        global $wpdb;

        if ( ! self::table_exists() ) {
            return [];
        }

        $table = self::get_table_name();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE client_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
                absint( $client_id ),
                absint( $limit ),
                absint( $offset )
            )
        );
    }

    /**
     * Busca comunicações recentes
     *
     * @param int    $limit   Limite de resultados
     * @param string $channel Filtrar por canal (opcional)
     * @param string $status  Filtrar por status (opcional)
     * @return array
     */
    public function get_recent( $limit = 50, $channel = null, $status = null ) {
        global $wpdb;

        if ( ! self::table_exists() ) {
            return [];
        }

        $table = self::get_table_name();

        // Constrói condições de forma segura
        $conditions = [ '1=1' ];
        $args       = [];

        if ( $channel ) {
            $conditions[] = 'channel = %s';
            $args[]       = sanitize_text_field( $channel );
        }

        if ( $status ) {
            $conditions[] = 'status = %s';
            $args[]       = sanitize_text_field( $status );
        }

        $args[] = absint( $limit );
        $where  = implode( ' AND ', $conditions );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d",
                ...$args
            )
        );
    }

    /**
     * Conta comunicações por status
     *
     * @return array Contagem por status
     */
    public function get_stats() {
        global $wpdb;

        if ( ! self::table_exists() ) {
            return [];
        }

        $table = self::get_table_name();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$table} GROUP BY status"
        );

        $stats = [];
        foreach ( $results as $row ) {
            $stats[ $row->status ] = (int) $row->count;
        }

        return $stats;
    }

    /**
     * Remove dados sensíveis do metadata
     *
     * @param array $context Contexto original
     * @return array Contexto sanitizado
     */
    private function sanitize_metadata( $context ) {
        $sensitive_keys = [ 'phone', 'to', 'email', 'message', 'body', 'subject', 'api_key' ];
        $safe           = [];

        foreach ( $context as $key => $value ) {
            if ( in_array( $key, $sensitive_keys, true ) ) {
                continue; // Remove dados sensíveis
            }
            $safe[ $key ] = $value;
        }

        return $safe;
    }

    /**
     * Limpa registros antigos (mais de X dias)
     *
     * @param int $days Dias para manter
     * @return int Número de registros removidos
     */
    public function cleanup_old_records( $days = 90 ) {
        global $wpdb;

        if ( ! self::table_exists() ) {
            return 0;
        }

        $table    = self::get_table_name();
        $cutoff   = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < %s",
                $cutoff
            )
        );

        return $deleted !== false ? $deleted : 0;
    }
}
