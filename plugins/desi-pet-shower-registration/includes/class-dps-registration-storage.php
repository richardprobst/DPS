<?php
/**
 * Persistent storage helpers for the Registration add-on.
 *
 * @package DPS_Registration_Addon
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Stores short-lived security events and form feedback without WordPress cache APIs.
 */
class DPS_Registration_Storage {

    /**
     * Current schema version for the add-on event table.
     *
     * @var string
     */
    const DB_VERSION = '1.0.0';

    /**
     * Option storing installed schema version.
     *
     * @var string
     */
    const OPTION_DB_VERSION = 'dps_registration_events_db_version';

    /**
     * Returns the event table name.
     *
     * @return string
     */
    public static function table_name() {
        global $wpdb;

        return $wpdb->prefix . 'dps_registration_events';
    }

    /**
     * Creates or updates the persistent event table when needed.
     *
     * @return void
     */
    public static function maybe_create_tables() {
        $installed = get_option( self::OPTION_DB_VERSION, '' );

        if ( self::DB_VERSION === $installed && self::table_exists() ) {
            self::delete_expired_records();
            return;
        }

        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table_name      = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type VARCHAR(40) NOT NULL,
            bucket_hash CHAR(64) NOT NULL,
            payload LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            consumed_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY event_bucket_created (event_type, bucket_hash, created_at),
            KEY expires_at (expires_at),
            KEY consumed_at (consumed_at)
        ) {$charset_collate};";

        dbDelta( $sql );
        update_option( self::OPTION_DB_VERSION, self::DB_VERSION, false );
        self::delete_expired_records();
    }

    /**
     * Checks whether the event table exists.
     *
     * @return bool
     */
    public static function table_exists() {
        global $wpdb;

        $table_name = self::table_name();

        return $table_name === $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
        );
    }

    /**
     * Records a rate-limit hit and returns whether the request is still allowed.
     *
     * @param string $context        Logical rate-limit context.
     * @param string $bucket_hash    Privacy-preserving bucket identifier.
     * @param int    $limit          Maximum allowed hits inside the window.
     * @param int    $window_seconds Rolling window length in seconds.
     * @return bool
     */
    public static function bump_rate_limit( $context, $bucket_hash, $limit, $window_seconds ) {
        global $wpdb;

        self::maybe_create_tables();

        $context        = sanitize_key( $context );
        $bucket_hash    = self::normalize_hash( $bucket_hash );
        $limit          = max( 1, absint( $limit ) );
        $window_seconds = max( 60, absint( $window_seconds ) );
        $now            = time();
        $now_mysql      = gmdate( 'Y-m-d H:i:s', $now );
        $window_start   = gmdate( 'Y-m-d H:i:s', $now - $window_seconds );
        $expires_at     = gmdate( 'Y-m-d H:i:s', $now + $window_seconds );

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM " . self::table_name() . "
                 WHERE event_type = %s
                   AND bucket_hash = %s
                   AND consumed_at IS NULL
                   AND created_at >= %s
                   AND expires_at > %s",
                'rate_' . $context,
                $bucket_hash,
                $window_start,
                $now_mysql
            )
        );

        if ( $count >= $limit ) {
            return false;
        }

        $wpdb->insert(
            self::table_name(),
            array(
                'event_type'  => 'rate_' . $context,
                'bucket_hash' => $bucket_hash,
                'payload'     => null,
                'created_at'  => $now_mysql,
                'expires_at'  => $expires_at,
                'consumed_at' => null,
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        return true;
    }

    /**
     * Stores a short-lived message for the current request bucket.
     *
     * @param string $bucket_hash Bucket identifier.
     * @param string $type        Message type.
     * @param string $text        Message text.
     * @param int    $ttl         Retention in seconds.
     * @return void
     */
    public static function add_message( $bucket_hash, $type, $text, $ttl ) {
        global $wpdb;

        self::maybe_create_tables();

        $payload = wp_json_encode(
            array(
                'type' => sanitize_key( $type ),
                'text' => sanitize_text_field( $text ),
            )
        );

        $now = time();

        $wpdb->insert(
            self::table_name(),
            array(
                'event_type'  => 'message',
                'bucket_hash' => self::normalize_hash( $bucket_hash ),
                'payload'     => $payload,
                'created_at'  => gmdate( 'Y-m-d H:i:s', $now ),
                'expires_at'  => gmdate( 'Y-m-d H:i:s', $now + max( 30, absint( $ttl ) ) ),
                'consumed_at' => null,
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s' )
        );
    }

    /**
     * Returns and consumes pending messages for a bucket.
     *
     * @param string $bucket_hash Bucket identifier.
     * @return array<int,array{type:string,text:string}>
     */
    public static function consume_messages( $bucket_hash ) {
        global $wpdb;

        self::maybe_create_tables();

        $now_mysql   = gmdate( 'Y-m-d H:i:s' );
        $bucket_hash = self::normalize_hash( $bucket_hash );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, payload FROM " . self::table_name() . "
                 WHERE event_type = %s
                   AND bucket_hash = %s
                   AND consumed_at IS NULL
                   AND expires_at > %s
                 ORDER BY id ASC",
                'message',
                $bucket_hash,
                $now_mysql
            ),
            ARRAY_A
        );

        if ( empty( $rows ) ) {
            return array();
        }

        $messages = array();
        $ids      = array();

        foreach ( $rows as $row ) {
            $ids[] = absint( $row['id'] );
            $data  = json_decode( (string) $row['payload'], true );

            if ( ! is_array( $data ) || empty( $data['text'] ) ) {
                continue;
            }

            $messages[] = array(
                'type' => sanitize_key( $data['type'] ?? 'error' ),
                'text' => sanitize_text_field( $data['text'] ),
            );
        }

        if ( ! empty( $ids ) ) {
            $id_placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE " . self::table_name() . " SET consumed_at = %s WHERE id IN ({$id_placeholders})",
                    array_merge( array( $now_mysql ), $ids )
                )
            );
        }

        return $messages;
    }

    /**
     * Removes expired records.
     *
     * @return void
     */
    public static function delete_expired_records() {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM " . self::table_name() . " WHERE expires_at <= %s",
                gmdate( 'Y-m-d H:i:s' )
            )
        );
    }

    /**
     * Drops the persistent event table on uninstall.
     *
     * @return void
     */
    public static function drop_tables() {
        global $wpdb;

        $wpdb->query( 'DROP TABLE IF EXISTS ' . self::table_name() );
        delete_option( self::OPTION_DB_VERSION );
    }

    /**
     * Normalizes arbitrary values into a stable SHA-256 hash.
     *
     * @param string $value Raw value.
     * @return string
     */
    private static function normalize_hash( $value ) {
        $value = (string) $value;

        if ( preg_match( '/^[a-f0-9]{64}$/', $value ) ) {
            return $value;
        }

        return hash( 'sha256', $value );
    }
}
