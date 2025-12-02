<?php
/**
 * Classe de exportação de backups.
 *
 * Suporta backup completo, seletivo e diferencial.
 *
 * @package    DesiPetShower
 * @subpackage DPS_Backup_Addon
 * @since      1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe DPS_Backup_Exporter
 *
 * @since 1.1.0
 */
class DPS_Backup_Exporter {

    /**
     * Versão do schema de backup.
     *
     * @var int
     */
    const SCHEMA_VERSION = 2;

    /**
     * Mapeamento de componentes para post types.
     *
     * @var array
     */
    private $component_post_types = [
        'clients'       => 'dps_cliente',
        'pets'          => 'dps_pet',
        'appointments'  => 'dps_agendamento',
        'services'      => 'dps_service',
        'subscriptions' => 'dps_subscription',
        'campaigns'     => 'dps_campaign',
    ];

    /**
     * Constrói o payload de backup completo.
     *
     * @since 1.1.0
     * @return array|WP_Error
     */
    public function build_complete_backup() {
        return $this->build_selective_backup( array_keys( DPS_Backup_Settings::get_available_components() ) );
    }

    /**
     * Constrói o payload de backup seletivo.
     *
     * @since 1.1.0
     * @param array $components Componentes a incluir.
     * @return array|WP_Error
     */
    public function build_selective_backup( $components = [] ) {
        global $wpdb;

        $payload = [
            'plugin'         => 'desi-pet-shower',
            'version'        => defined( 'DPS_BACKUP_VERSION' ) ? DPS_BACKUP_VERSION : '1.1.0',
            'schema_version' => self::SCHEMA_VERSION,
            'generated_at'   => gmdate( 'c' ),
            'site_url'       => home_url(),
            'db_prefix'      => $wpdb->prefix,
            'components'     => $components,
            'backup_type'    => 'selective',
        ];

        // Exportar entidades estruturadas
        foreach ( $this->component_post_types as $component => $post_type ) {
            if ( in_array( $component, $components, true ) ) {
                $payload[ $component ] = $this->export_entities_by_type( $post_type );
            }
        }

        // Exportar transações financeiras
        if ( in_array( 'transactions', $components, true ) ) {
            $payload['transactions'] = $this->export_transactions();
        }

        // Exportar options
        if ( in_array( 'options', $components, true ) ) {
            $payload['options'] = $this->export_options();
        }

        // Exportar tabelas customizadas
        if ( in_array( 'tables', $components, true ) ) {
            $tables = $this->export_custom_tables();
            if ( is_wp_error( $tables ) ) {
                return $tables;
            }
            $payload['tables'] = $tables;
        }

        // Exportar arquivos
        if ( in_array( 'files', $components, true ) ) {
            // Coletar IDs de posts para anexos
            $post_ids = [];
            foreach ( $this->component_post_types as $component => $post_type ) {
                if ( in_array( $component, $components, true ) && isset( $payload[ $component ] ) ) {
                    foreach ( $payload[ $component ] as $entity ) {
                        if ( isset( $entity['id'] ) ) {
                            $post_ids[] = $entity['id'];
                        }
                    }
                }
            }

            $meta = $this->get_all_postmeta_for_ids( $post_ids );
            $attachments = $this->export_attachments( $post_ids, $meta );
            if ( is_wp_error( $attachments ) ) {
                return $attachments;
            }
            $payload['attachments'] = $attachments;

            $files = $this->export_additional_files();
            if ( is_wp_error( $files ) ) {
                return $files;
            }
            $payload['files'] = $files;
        }

        // Para compatibilidade com schema v1, incluir posts e postmeta se necessário
        $payload['posts'] = $this->export_all_dps_posts();
        $payload['postmeta'] = $this->export_all_dps_postmeta();

        return $payload;
    }

    /**
     * Constrói o payload de backup diferencial.
     *
     * @since 1.1.0
     * @param string $since Data ISO 8601 ou timestamp.
     * @return array|WP_Error
     */
    public function build_differential_backup( $since ) {
        global $wpdb;

        $since_date = is_numeric( $since ) ? gmdate( 'Y-m-d H:i:s', $since ) : gmdate( 'Y-m-d H:i:s', strtotime( $since ) );

        $payload = [
            'plugin'         => 'desi-pet-shower',
            'version'        => defined( 'DPS_BACKUP_VERSION' ) ? DPS_BACKUP_VERSION : '1.1.0',
            'schema_version' => self::SCHEMA_VERSION,
            'generated_at'   => gmdate( 'c' ),
            'site_url'       => home_url(),
            'db_prefix'      => $wpdb->prefix,
            'backup_type'    => 'differential',
            'since'          => $since_date,
        ];

        // Exportar apenas posts modificados desde a data
        foreach ( $this->component_post_types as $component => $post_type ) {
            $payload[ $component ] = $this->export_entities_modified_since( $post_type, $since_date );
        }

        // Exportar transações modificadas
        $payload['transactions'] = $this->export_transactions_modified_since( $since_date );

        return $payload;
    }

    /**
     * Exporta entidades (posts) por tipo com metadados.
     *
     * @since 1.1.0
     * @param string $post_type Tipo de post.
     * @return array
     */
    public function export_entities_by_type( $post_type ) {
        $items = get_posts( [
            'post_type'      => sanitize_key( $post_type ),
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ] );

        if ( empty( $items ) ) {
            return [];
        }

        // Pré-carregar cache de metadados
        $post_ids = wp_list_pluck( $items, 'ID' );
        update_meta_cache( 'post', $post_ids );

        $exported = [];
        foreach ( $items as $item ) {
            $exported[] = [
                'id'   => $item->ID,
                'post' => [
                    'post_title'        => $item->post_title,
                    'post_status'       => $item->post_status,
                    'post_content'      => $item->post_content,
                    'post_excerpt'      => $item->post_excerpt,
                    'post_date'         => $item->post_date,
                    'post_date_gmt'     => $item->post_date_gmt,
                    'post_modified'     => $item->post_modified,
                    'post_modified_gmt' => $item->post_modified_gmt,
                    'post_name'         => $item->post_name,
                    'post_author'       => $item->post_author,
                    'post_type'         => $post_type,
                ],
                'meta' => $this->collect_post_meta( $item->ID ),
            ];
        }

        return $exported;
    }

    /**
     * Exporta entidades modificadas desde uma data.
     *
     * @since 1.1.0
     * @param string $post_type  Tipo de post.
     * @param string $since_date Data no formato MySQL.
     * @return array
     */
    public function export_entities_modified_since( $post_type, $since_date ) {
        $items = get_posts( [
            'post_type'      => sanitize_key( $post_type ),
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'date_query'     => [
                [
                    'column' => 'post_modified',
                    'after'  => $since_date,
                ],
            ],
        ] );

        if ( empty( $items ) ) {
            return [];
        }

        $post_ids = wp_list_pluck( $items, 'ID' );
        update_meta_cache( 'post', $post_ids );

        $exported = [];
        foreach ( $items as $item ) {
            $exported[] = [
                'id'   => $item->ID,
                'post' => [
                    'post_title'        => $item->post_title,
                    'post_status'       => $item->post_status,
                    'post_content'      => $item->post_content,
                    'post_excerpt'      => $item->post_excerpt,
                    'post_date'         => $item->post_date,
                    'post_date_gmt'     => $item->post_date_gmt,
                    'post_modified'     => $item->post_modified,
                    'post_modified_gmt' => $item->post_modified_gmt,
                    'post_name'         => $item->post_name,
                    'post_author'       => $item->post_author,
                    'post_type'         => $post_type,
                ],
                'meta' => $this->collect_post_meta( $item->ID ),
            ];
        }

        return $exported;
    }

    /**
     * Exporta transações financeiras.
     *
     * @since 1.1.0
     * @return array
     */
    public function export_transactions() {
        global $wpdb;

        $table = $wpdb->prefix . 'dps_transacoes';
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( $exists !== $table ) {
            return [];
        }

        $rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id ASC", ARRAY_A );
        return is_array( $rows ) ? $rows : [];
    }

    /**
     * Exporta transações modificadas desde uma data.
     *
     * @since 1.1.0
     * @param string $since_date Data no formato MySQL.
     * @return array
     */
    public function export_transactions_modified_since( $since_date ) {
        global $wpdb;

        $table = $wpdb->prefix . 'dps_transacoes';
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( $exists !== $table ) {
            return [];
        }

        // Verificar se a tabela tem coluna updated_at ou created_at
        $columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
        
        if ( in_array( 'updated_at', $columns, true ) ) {
            $rows = $wpdb->get_results(
                $wpdb->prepare( "SELECT * FROM {$table} WHERE updated_at >= %s ORDER BY id ASC", $since_date ),
                ARRAY_A
            );
        } elseif ( in_array( 'created_at', $columns, true ) ) {
            $rows = $wpdb->get_results(
                $wpdb->prepare( "SELECT * FROM {$table} WHERE created_at >= %s ORDER BY id ASC", $since_date ),
                ARRAY_A
            );
        } else {
            // Sem coluna de data, exportar tudo
            $rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id ASC", ARRAY_A );
        }

        return is_array( $rows ) ? $rows : [];
    }

    /**
     * Exporta options do plugin.
     *
     * @since 1.1.0
     * @return array
     */
    public function export_options() {
        global $wpdb;
        $options_table = $wpdb->options;
        $options = $wpdb->get_results(
            "SELECT option_name, option_value, autoload FROM {$options_table} WHERE option_name LIKE 'dps\\_%' ESCAPE '\\' ORDER BY option_name",
            ARRAY_A
        );
        return is_array( $options ) ? $options : [];
    }

    /**
     * Exporta tabelas customizadas.
     *
     * @since 1.1.0
     * @return array|WP_Error
     */
    public function export_custom_tables() {
        global $wpdb;

        $prefix = $wpdb->prefix . 'dps_';
        $like = $wpdb->esc_like( $prefix ) . '%';
        $tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
        
        if ( ! is_array( $tables ) ) {
            return [];
        }

        $result = [];
        foreach ( $tables as $table ) {
            $name = substr( $table, strlen( $wpdb->prefix ) );
            $create = $wpdb->get_row( "SHOW CREATE TABLE `{$table}`", ARRAY_A );
            if ( empty( $create['Create Table'] ) ) {
                return new WP_Error(
                    'dps_backup_table',
                    sprintf( __( 'Não foi possível ler a estrutura da tabela %s.', 'dps-backup-addon' ), $table )
                );
            }
            $rows = $wpdb->get_results( "SELECT * FROM `{$table}`", ARRAY_A );
            $result[] = [
                'name'   => $name,
                'schema' => str_replace( $wpdb->prefix, '{prefix}', $create['Create Table'] ),
                'rows'   => $rows,
            ];
        }

        return $result;
    }

    /**
     * Exporta anexos relacionados aos posts.
     *
     * @since 1.1.0
     * @param array $post_ids IDs dos posts.
     * @param array $meta     Metadados.
     * @return array|WP_Error
     */
    public function export_attachments( $post_ids, $meta ) {
        global $wpdb;

        $posts_table = $wpdb->posts;
        $postmeta_table = $wpdb->postmeta;

        $attachment_ids = [];
        if ( $post_ids ) {
            $ids_in = implode( ',', array_map( 'intval', $post_ids ) );
            $by_parent = $wpdb->get_col( "SELECT ID FROM {$posts_table} WHERE post_type = 'attachment' AND post_parent IN ( {$ids_in} )" );
            $attachment_ids = array_merge( $attachment_ids, array_map( 'intval', $by_parent ) );
        }

        if ( $meta ) {
            foreach ( $meta as $meta_row ) {
                if ( isset( $meta_row['meta_key'] ) && 'pet_photo_id' === $meta_row['meta_key'] ) {
                    $attachment_ids[] = (int) $meta_row['meta_value'];
                }
            }
        }

        $attachment_ids = array_filter( array_unique( $attachment_ids ) );

        if ( empty( $attachment_ids ) ) {
            return [];
        }

        $ids_in = implode( ',', array_map( 'intval', $attachment_ids ) );
        $formats = $this->get_post_formats();

        $posts_rows = $wpdb->get_results( "SELECT * FROM {$posts_table} WHERE ID IN ( {$ids_in} )", ARRAY_A );
        $posts_map = [];
        foreach ( $posts_rows as $row ) {
            $posts_map[ (int) $row['ID'] ] = $row;
        }

        $meta_rows = $wpdb->get_results( "SELECT * FROM {$postmeta_table} WHERE post_id IN ( {$ids_in} )", ARRAY_A );
        $meta_map = [];
        foreach ( $meta_rows as $meta_row ) {
            $post_id = (int) $meta_row['post_id'];
            if ( ! isset( $meta_map[ $post_id ] ) ) {
                $meta_map[ $post_id ] = [];
            }
            $meta_map[ $post_id ][] = $meta_row;
        }

        $attachments = [];
        foreach ( $attachment_ids as $attachment_id ) {
            $post = $posts_map[ $attachment_id ] ?? null;
            if ( ! $post ) {
                continue;
            }
            $meta_rows = $meta_map[ $attachment_id ] ?? [];

            $file_path = get_attached_file( $attachment_id );
            $file = null;
            if ( $file_path && file_exists( $file_path ) ) {
                $uploads = wp_upload_dir();
                $relative = str_replace( '\\', '/', $file_path );
                $base = str_replace( '\\', '/', $uploads['basedir'] );
                if ( 0 === strpos( $relative, $base ) ) {
                    $relative = ltrim( substr( $relative, strlen( $base ) ), '/' );
                } else {
                    $relative = basename( $file_path );
                }
                $contents = file_get_contents( $file_path );
                if ( false === $contents ) {
                    return new WP_Error(
                        'dps_backup_attachment',
                        sprintf( __( 'Não foi possível ler o arquivo do anexo %d.', 'dps-backup-addon' ), $attachment_id )
                    );
                }
                $file = [
                    'path'    => $relative,
                    'content' => base64_encode( $contents ),
                ];
            }

            $attachments[] = [
                'post' => array_intersect_key( $post, $formats ),
                'meta' => $meta_rows,
                'file' => $file,
            ];
        }

        return $attachments;
    }

    /**
     * Exporta arquivos adicionais.
     *
     * @since 1.1.0
     * @return array|WP_Error
     */
    public function export_additional_files() {
        $uploads = wp_upload_dir();
        $dir = trailingslashit( $uploads['basedir'] ) . 'dps_docs';

        if ( ! is_dir( $dir ) ) {
            return [];
        }

        if ( ! class_exists( 'DirectoryIterator' ) ) {
            return [];
        }

        $files = [];
        $iterator = new DirectoryIterator( $dir );
        foreach ( $iterator as $fileinfo ) {
            if ( $fileinfo->isDot() || ! $fileinfo->isFile() ) {
                continue;
            }
            $path = 'dps_docs/' . $fileinfo->getFilename();
            $contents = file_get_contents( $fileinfo->getPathname() );
            if ( false === $contents ) {
                return new WP_Error(
                    'dps_backup_file',
                    sprintf( __( 'Não foi possível ler o arquivo %s para o backup.', 'dps-backup-addon' ), $fileinfo->getFilename() )
                );
            }
            $files[] = [
                'path'    => $path,
                'content' => base64_encode( $contents ),
            ];
        }

        return $files;
    }

    /**
     * Obtém estatísticas do backup.
     *
     * @since 1.1.0
     * @param array $payload Payload do backup.
     * @return array
     */
    public function get_backup_stats( $payload ) {
        $stats = [];

        foreach ( [ 'clients', 'pets', 'appointments', 'services', 'subscriptions', 'campaigns' ] as $key ) {
            if ( isset( $payload[ $key ] ) && is_array( $payload[ $key ] ) ) {
                $stats[ $key ] = count( $payload[ $key ] );
            }
        }

        if ( isset( $payload['transactions'] ) && is_array( $payload['transactions'] ) ) {
            $stats['transactions'] = count( $payload['transactions'] );
        }

        if ( isset( $payload['options'] ) && is_array( $payload['options'] ) ) {
            $stats['options'] = count( $payload['options'] );
        }

        if ( isset( $payload['tables'] ) && is_array( $payload['tables'] ) ) {
            $stats['tables'] = count( $payload['tables'] );
        }

        if ( isset( $payload['attachments'] ) && is_array( $payload['attachments'] ) ) {
            $stats['attachments'] = count( $payload['attachments'] );
        }

        if ( isset( $payload['files'] ) && is_array( $payload['files'] ) ) {
            $stats['files'] = count( $payload['files'] );
        }

        return $stats;
    }

    /**
     * Obtém contagem de registros por componente.
     *
     * @since 1.1.0
     * @return array
     */
    public function get_component_counts() {
        global $wpdb;

        $counts = [];

        foreach ( $this->component_post_types as $component => $post_type ) {
            $counts[ $component ] = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s",
                    $post_type
                )
            );
        }

        // Transações
        $table = $wpdb->prefix . 'dps_transacoes';
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( $exists === $table ) {
            $counts['transactions'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        } else {
            $counts['transactions'] = 0;
        }

        // Options
        $counts['options'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'dps\\_%' ESCAPE '\\'"
        );

        // Tabelas customizadas
        $prefix = $wpdb->prefix . 'dps_';
        $like = $wpdb->esc_like( $prefix ) . '%';
        $counts['tables'] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE %s', $like ) );

        return $counts;
    }

    /**
     * Coleta metadados de um post.
     *
     * @since 1.1.0
     * @param int $post_id ID do post.
     * @return array
     */
    private function collect_post_meta( $post_id ) {
        $raw = get_post_meta( $post_id );
        $meta = [];

        foreach ( $raw as $key => $values ) {
            if ( empty( $values ) || ! is_array( $values ) ) {
                continue;
            }
            $meta[ $key ] = maybe_unserialize( $values[0] );
        }

        return $meta;
    }

    /**
     * Obtém todos os posts DPS.
     *
     * @since 1.1.0
     * @return array
     */
    private function export_all_dps_posts() {
        global $wpdb;
        $posts = $wpdb->get_results(
            "SELECT * FROM {$wpdb->posts} WHERE post_type LIKE 'dps\\_%' ESCAPE '\\' ORDER BY ID ASC",
            ARRAY_A
        );
        return is_array( $posts ) ? $posts : [];
    }

    /**
     * Obtém todos os postmeta DPS.
     *
     * @since 1.1.0
     * @return array
     */
    private function export_all_dps_postmeta() {
        global $wpdb;

        $posts = $wpdb->get_col(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type LIKE 'dps\\_%' ESCAPE '\\'"
        );

        if ( empty( $posts ) ) {
            return [];
        }

        $ids_in = implode( ',', array_map( 'intval', $posts ) );
        $meta = $wpdb->get_results(
            "SELECT * FROM {$wpdb->postmeta} WHERE post_id IN ( {$ids_in} ) ORDER BY meta_id ASC",
            ARRAY_A
        );

        return is_array( $meta ) ? $meta : [];
    }

    /**
     * Obtém todos os postmeta para IDs específicos.
     *
     * @since 1.1.0
     * @param array $post_ids IDs dos posts.
     * @return array
     */
    private function get_all_postmeta_for_ids( $post_ids ) {
        global $wpdb;

        if ( empty( $post_ids ) ) {
            return [];
        }

        $ids_in = implode( ',', array_map( 'intval', $post_ids ) );
        $meta = $wpdb->get_results(
            "SELECT * FROM {$wpdb->postmeta} WHERE post_id IN ( {$ids_in} ) ORDER BY meta_id ASC",
            ARRAY_A
        );

        return is_array( $meta ) ? $meta : [];
    }

    /**
     * Retorna formatos das colunas da tabela posts.
     *
     * @since 1.1.0
     * @return array
     */
    private function get_post_formats() {
        return [
            'ID'                    => '%d',
            'post_author'           => '%d',
            'post_date'             => '%s',
            'post_date_gmt'         => '%s',
            'post_content'          => '%s',
            'post_title'            => '%s',
            'post_excerpt'          => '%s',
            'post_status'           => '%s',
            'comment_status'        => '%s',
            'ping_status'           => '%s',
            'post_password'         => '%s',
            'post_name'             => '%s',
            'to_ping'               => '%s',
            'pinged'                => '%s',
            'post_modified'         => '%s',
            'post_modified_gmt'     => '%s',
            'post_content_filtered' => '%s',
            'post_parent'           => '%d',
            'guid'                  => '%s',
            'menu_order'            => '%d',
            'post_type'             => '%s',
            'post_mime_type'        => '%s',
            'comment_count'         => '%d',
        ];
    }
}
