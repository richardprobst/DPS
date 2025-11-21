<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Helper centralizado para logs do Desi Pet Shower.
 */
class DPS_Logger {

    const DB_VERSION    = '1.0.0';
    const LEVEL_INFO    = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR   = 'error';

    /**
     * Cria a tabela de logs utilizando dbDelta.
     */
    public static function create_table() {
        global $wpdb;

        $table_name      = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            date_time datetime NOT NULL,
            level varchar(20) NOT NULL,
            source varchar(50) NOT NULL,
            message text NOT NULL,
            context longtext NULL,
            PRIMARY KEY  (id),
            KEY level (level),
            KEY source (source),
            KEY date_time (date_time)
        ) {$charset_collate};";

        dbDelta( $sql );
    }

    /**
     * Cria ou atualiza a tabela de logs apenas quando a versão não coincide.
     */
    public static function maybe_install() {
        $installed_version = get_option( 'dps_logger_db_version' );

        if ( self::DB_VERSION !== $installed_version ) {
            self::create_table();
            update_option( 'dps_logger_db_version', self::DB_VERSION );
        }
    }

    /**
     * Retorna o nome da tabela de logs.
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;

        return $wpdb->prefix . 'dps_logs';
    }

    /**
     * Verifica se a tabela de logs existe.
     *
     * @return bool
     */
    public static function table_exists() {
        global $wpdb;

        $table_name = self::get_table_name();

        return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
    }

    /**
     * Níveis suportados.
     *
     * @return array
     */
    public static function get_levels() {
        return [ self::LEVEL_INFO, self::LEVEL_WARNING, self::LEVEL_ERROR ];
    }

    /**
     * Registra log de informação.
     *
     * @param string $message Texto principal.
     * @param array  $context Dados complementares.
     * @param string $source  Origem (base, finance, payment, etc.).
     */
    public static function info( $message, $context = array(), $source = 'base' ) {
        self::write( self::LEVEL_INFO, $message, $context, $source );
    }

    /**
     * Registra log de aviso.
     *
     * @param string $message Texto principal.
     * @param array  $context Dados complementares.
     * @param string $source  Origem (base, finance, payment, etc.).
     */
    public static function warning( $message, $context = array(), $source = 'base' ) {
        self::write( self::LEVEL_WARNING, $message, $context, $source );
    }

    /**
     * Registra log de erro.
     *
     * @param string $message Texto principal.
     * @param array  $context Dados complementares.
     * @param string $source  Origem (base, finance, payment, etc.).
     */
    public static function error( $message, $context = array(), $source = 'base' ) {
        self::write( self::LEVEL_ERROR, $message, $context, $source );
    }

    /**
     * Avalia se o log deve ser salvo respeitando o nível mínimo configurado.
     *
     * @param string $level Nível solicitado.
     *
     * @return bool
     */
    private static function should_log( $level ) {
        $order = [
            self::LEVEL_INFO    => 0,
            self::LEVEL_WARNING => 1,
            self::LEVEL_ERROR   => 2,
        ];

        $min_level = get_option( 'dps_logger_min_level', self::LEVEL_INFO );
        $min_level = array_key_exists( $min_level, $order ) ? $min_level : self::LEVEL_INFO;
        $level     = array_key_exists( $level, $order ) ? $level : self::LEVEL_INFO;

        $min_value = $order[ $min_level ];
        $value     = $order[ $level ];

        return $value >= $min_value;
    }

    /**
     * Persiste o log na tabela ou fallback para arquivo caso necessário.
     *
     * @param string $level   Nível do log.
     * @param mixed  $message Mensagem principal.
     * @param array  $context Dados adicionais.
     * @param string $source  Origem do evento.
     */
    private static function write( $level, $message, $context, $source ) {
        if ( ! self::should_log( $level ) ) {
            return;
        }

        global $wpdb;

        $normalized_message = is_scalar( $message ) ? (string) $message : wp_json_encode( $message );
        $normalized_context = self::normalize_context( $context );
        $normalized_source  = substr( sanitize_text_field( $source ), 0, 50 );
        $date_time          = current_time( 'mysql', true );
        $table_name         = self::get_table_name();

        $inserted = $wpdb->insert(
            $table_name,
            array(
                'date_time' => $date_time,
                'level'     => $level,
                'source'    => $normalized_source,
                'message'   => $normalized_message,
                'context'   => $normalized_context,
            ),
            array( '%s', '%s', '%s', '%s', '%s' )
        );

        if ( false === $inserted ) {
            self::log_to_file( $date_time, $level, $normalized_source, $normalized_message, $normalized_context );
        }
    }

    /**
     * Normaliza o contexto em JSON.
     *
     * @param array|string $context Dados complementares.
     *
     * @return string|null
     */
    private static function normalize_context( $context ) {
        if ( empty( $context ) ) {
            return null;
        }

        if ( ! is_array( $context ) && ! is_object( $context ) ) {
            $context = array( 'value' => $context );
        }

        return wp_json_encode( $context, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR );
    }

    /**
     * Fallback para arquivo em wp-content/uploads/dps-logs/dps.log.
     *
     * @param string      $date_time Data e hora do evento.
     * @param string      $level     Nível do log.
     * @param string      $source    Origem do evento.
     * @param string      $message   Mensagem principal.
     * @param string|null $context   JSON com contexto.
     */
    private static function log_to_file( $date_time, $level, $source, $message, $context ) {
        $uploads = wp_upload_dir();
        if ( ! empty( $uploads['error'] ) ) {
            error_log( sprintf( 'DPS logger fallback error: %s', $uploads['error'] ) );
            return;
        }

        $dir = trailingslashit( $uploads['basedir'] ) . 'dps-logs';
        wp_mkdir_p( $dir );

        $file_path = trailingslashit( $dir ) . 'dps.log';
        $line      = sprintf(
            '[%s] %s.%s: %s | %s%s',
            $date_time,
            strtoupper( $level ),
            $source,
            $message,
            $context ? $context : 'no-context',
            PHP_EOL
        );

        file_put_contents( $file_path, $line, FILE_APPEND );
    }
}
