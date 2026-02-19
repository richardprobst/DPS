<?php
/**
 * Sistema centralizado de auditoria do desi.pet by PRObst
 *
 * Rastreia alterações em entidades (clientes, pets, agendamentos, portal, etc.)
 * para fins de auditoria e conformidade.
 *
 * @package    DesiPetShower
 * @subpackage Base_Plugin
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe utilitária para registro de auditoria
 */
class DPS_Audit_Logger {

    const TABLE_NAME  = 'dps_audit_log';
    const DB_VERSION  = '1.0.0';

    /**
     * Cria ou atualiza a tabela de auditoria apenas quando a versão não coincide.
     *
     * @since 1.0.0
     */
    public static function maybe_install() {
        $installed_version = get_option( 'dps_audit_log_db_version' );

        if ( self::DB_VERSION !== $installed_version ) {
            self::create_table();
            update_option( 'dps_audit_log_db_version', self::DB_VERSION );
        }
    }

    /**
     * Cria a tabela de auditoria utilizando dbDelta.
     *
     * @since 1.0.0
     */
    public static function create_table() {
        global $wpdb;

        $table_name      = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entity_type varchar(50) NOT NULL,
            entity_id bigint(20) unsigned NOT NULL DEFAULT 0,
            action varchar(50) NOT NULL,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            ip_address varchar(45) DEFAULT NULL,
            details longtext DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY entity_type (entity_type),
            KEY entity_id (entity_id),
            KEY action (action),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) {$charset_collate};";

        dbDelta( $sql );
    }

    /**
     * Verifica se a tabela de auditoria existe.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public static function table_exists() {
        global $wpdb;

        $table_name = self::get_table_name();

        return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
    }

    /**
     * Retorna o nome completo da tabela de auditoria.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;

        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Registra um evento de auditoria.
     *
     * @since 1.0.0
     *
     * @param string $entity_type Tipo da entidade (client, pet, appointment, etc.).
     * @param int    $entity_id   ID da entidade (post ID) ou 0 para eventos genéricos.
     * @param string $action      Ação realizada (create, update, delete, etc.).
     * @param array  $details     Dados adicionais de contexto (armazenados como JSON).
     *
     * @return int|false ID do registro inserido ou false em caso de erro.
     */
    public static function log( $entity_type, $entity_id, $action, $details = array() ) {
        global $wpdb;

        $entity_type = sanitize_text_field( $entity_type );
        $action      = sanitize_text_field( $action );
        $entity_id   = absint( $entity_id );
        $user_id     = get_current_user_id();
        $ip_address  = DPS_IP_Helper::get_ip();
        $created_at  = current_time( 'mysql', true );

        $details_json = null;
        if ( ! empty( $details ) ) {
            $details_json = wp_json_encode( $details, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR );
        }

        $inserted = $wpdb->insert(
            self::get_table_name(),
            array(
                'entity_type' => substr( $entity_type, 0, 50 ),
                'entity_id'   => $entity_id,
                'action'      => substr( $action, 0, 50 ),
                'user_id'     => $user_id,
                'ip_address'  => $ip_address,
                'details'     => $details_json,
                'created_at'  => $created_at,
            ),
            array( '%s', '%d', '%s', '%d', '%s', '%s', '%s' )
        );

        if ( false === $inserted ) {
            error_log( sprintf( 'DPS_Audit_Logger: falha ao inserir registro — %s', $wpdb->last_error ) );
            return false;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Registra alteração em cliente.
     *
     * @since 1.0.0
     *
     * @param int    $client_id ID do cliente.
     * @param string $action    Ação realizada.
     * @param array  $details   Dados adicionais.
     *
     * @return int|false
     */
    public static function log_client_change( $client_id, $action, $details = array() ) {
        return self::log( 'client', $client_id, $action, $details );
    }

    /**
     * Registra alteração em pet.
     *
     * @since 1.0.0
     *
     * @param int    $pet_id  ID do pet.
     * @param string $action  Ação realizada.
     * @param array  $details Dados adicionais.
     *
     * @return int|false
     */
    public static function log_pet_change( $pet_id, $action, $details = array() ) {
        return self::log( 'pet', $pet_id, $action, $details );
    }

    /**
     * Registra alteração em agendamento.
     *
     * @since 1.0.0
     *
     * @param int    $appointment_id ID do agendamento.
     * @param string $action         Ação realizada.
     * @param array  $details        Dados adicionais.
     *
     * @return int|false
     */
    public static function log_appointment_change( $appointment_id, $action, $details = array() ) {
        return self::log( 'appointment', $appointment_id, $action, $details );
    }

    /**
     * Registra evento do portal do cliente.
     *
     * @since 1.0.0
     *
     * @param int    $client_id ID do cliente.
     * @param string $action    Ação realizada (login, logout, etc.).
     * @param array  $details   Dados adicionais.
     *
     * @return int|false
     */
    public static function log_portal_event( $client_id, $action, $details = array() ) {
        return self::log( 'portal', $client_id, $action, $details );
    }

    /**
     * Consulta registros de auditoria com filtros opcionais.
     *
     * @since 1.0.0
     *
     * @param array $args {
     *     Argumentos opcionais de filtro.
     *
     *     @type string $entity_type Tipo da entidade.
     *     @type int    $entity_id   ID da entidade.
     *     @type string $action      Ação realizada.
     *     @type int    $user_id     ID do usuário.
     *     @type string $date_from   Data inicial (Y-m-d).
     *     @type string $date_to     Data final (Y-m-d).
     *     @type int    $limit       Limite de resultados (padrão 50).
     *     @type int    $offset      Deslocamento para paginação (padrão 0).
     * }
     *
     * @return array Lista de objetos com os registros encontrados.
     */
    public static function get_logs( $args = array() ) {
        global $wpdb;

        $table_name = self::get_table_name();

        $where  = array();
        $values = array();

        if ( ! empty( $args['entity_type'] ) ) {
            $where[]  = 'entity_type = %s';
            $values[] = sanitize_text_field( $args['entity_type'] );
        }

        if ( isset( $args['entity_id'] ) ) {
            $where[]  = 'entity_id = %d';
            $values[] = absint( $args['entity_id'] );
        }

        if ( ! empty( $args['action'] ) ) {
            $where[]  = 'action = %s';
            $values[] = sanitize_text_field( $args['action'] );
        }

        if ( isset( $args['user_id'] ) ) {
            $where[]  = 'user_id = %d';
            $values[] = absint( $args['user_id'] );
        }

        if ( ! empty( $args['date_from'] ) ) {
            $where[]  = 'created_at >= %s';
            $values[] = sanitize_text_field( $args['date_from'] ) . ' 00:00:00';
        }

        if ( ! empty( $args['date_to'] ) ) {
            $where[]  = 'created_at <= %s';
            $values[] = sanitize_text_field( $args['date_to'] ) . ' 23:59:59';
        }

        $limit  = isset( $args['limit'] ) ? absint( $args['limit'] ) : 50;
        $offset = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;

        $sql = "SELECT * FROM {$table_name}";

        if ( ! empty( $where ) ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where );
        }

        $sql .= ' ORDER BY created_at DESC LIMIT %d OFFSET %d';

        $values[] = $limit;
        $values[] = $offset;

        return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
    }

    /**
     * Conta registros de auditoria com filtros opcionais.
     *
     * @since 1.0.0
     *
     * @param array $args Mesmos argumentos de filtro de get_logs() (exceto limit/offset).
     *
     * @return int Total de registros encontrados.
     */
    public static function count_logs( $args = array() ) {
        global $wpdb;

        $table_name = self::get_table_name();

        $where  = array();
        $values = array();

        if ( ! empty( $args['entity_type'] ) ) {
            $where[]  = 'entity_type = %s';
            $values[] = sanitize_text_field( $args['entity_type'] );
        }

        if ( isset( $args['entity_id'] ) ) {
            $where[]  = 'entity_id = %d';
            $values[] = absint( $args['entity_id'] );
        }

        if ( ! empty( $args['action'] ) ) {
            $where[]  = 'action = %s';
            $values[] = sanitize_text_field( $args['action'] );
        }

        if ( isset( $args['user_id'] ) ) {
            $where[]  = 'user_id = %d';
            $values[] = absint( $args['user_id'] );
        }

        if ( ! empty( $args['date_from'] ) ) {
            $where[]  = 'created_at >= %s';
            $values[] = sanitize_text_field( $args['date_from'] ) . ' 00:00:00';
        }

        if ( ! empty( $args['date_to'] ) ) {
            $where[]  = 'created_at <= %s';
            $values[] = sanitize_text_field( $args['date_to'] ) . ' 23:59:59';
        }

        $sql = "SELECT COUNT(*) FROM {$table_name}";

        if ( ! empty( $where ) ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where );
        }

        if ( ! empty( $values ) ) {
            return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) );
        }

        return (int) $wpdb->get_var( $sql );
    }

    /**
     * Retorna os tipos de entidade suportados.
     *
     * @since 1.0.0
     *
     * @return array
     */
    public static function get_entity_types() {
        return array( 'client', 'pet', 'appointment', 'portal', 'finance', 'subscription', 'system' );
    }

    /**
     * Retorna os tipos de ação suportados.
     *
     * @since 1.0.0
     *
     * @return array
     */
    public static function get_action_types() {
        return array(
            'create',
            'update',
            'delete',
            'status_change',
            'login',
            'login_failed',
            'logout',
            'token_generated',
            'token_revoked',
        );
    }

    /**
     * Retorna o rótulo em português para um tipo de ação.
     *
     * @since 1.0.0
     *
     * @param string $action Tipo da ação.
     *
     * @return string Rótulo legível.
     */
    public static function get_action_label( $action ) {
        $labels = array(
            'create'          => 'Criação',
            'update'          => 'Atualização',
            'delete'          => 'Exclusão',
            'status_change'   => 'Mudança de Status',
            'login'           => 'Login',
            'login_failed'    => 'Tentativa de Login',
            'logout'          => 'Logout',
            'token_generated' => 'Token Gerado',
            'token_revoked'   => 'Token Revogado',
        );

        return isset( $labels[ $action ] ) ? $labels[ $action ] : ucfirst( $action );
    }

    /**
     * Retorna o rótulo em português para um tipo de entidade.
     *
     * @since 1.0.0
     *
     * @param string $entity_type Tipo da entidade.
     *
     * @return string Rótulo legível.
     */
    public static function get_entity_label( $entity_type ) {
        $labels = array(
            'client'       => 'Cliente',
            'pet'          => 'Pet',
            'appointment'  => 'Agendamento',
            'portal'       => 'Portal',
            'finance'      => 'Financeiro',
            'subscription' => 'Assinatura',
            'system'       => 'Sistema',
        );

        return isset( $labels[ $entity_type ] ) ? $labels[ $entity_type ] : ucfirst( $entity_type );
    }

    /**
     * Remove registros de auditoria anteriores ao número de dias informado.
     *
     * @since 1.0.0
     *
     * @param int $days Número de dias a manter (padrão 90).
     *
     * @return int Número de registros removidos.
     */
    public static function cleanup( $days = 90 ) {
        global $wpdb;

        $days       = absint( $days );
        $table_name = self::get_table_name();

        return (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
    }
}
