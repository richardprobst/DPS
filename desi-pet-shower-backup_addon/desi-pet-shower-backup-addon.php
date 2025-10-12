<?php
/*
 * Plugin Name: Desi Pet Shower – Backup Add-on
 * Description: Add-on para o Desi Pet Shower que permite gerar um backup completo de dados e restaurá-lo em outra instalação.
 * Version:     1.0.0
 * Author:      PRObst
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'DPS_Backup_Addon' ) ) {

class DPS_Backup_Addon {

    const EXPORT_NONCE = 'dps_backup_export';
    const IMPORT_NONCE = 'dps_backup_import';

    /**
     * Mapeamento de metadados que armazenam IDs de outros objetos.
     *
     * @var array<string, array<string, string>>
     */
    private $meta_relations = [
        'dps_pet' => [
            'owner_id' => 'dps_cliente',
        ],
        'dps_subscription' => [
            'subscription_client_id' => 'dps_cliente',
            'subscription_pet_id'    => 'dps_pet',
            'subscription_pet_ids'   => 'dps_pet',
        ],
        'dps_agendamento' => [
            'appointment_client_id'   => 'dps_cliente',
            'appointment_pet_id'      => 'dps_pet',
            'appointment_pet_ids'     => 'dps_pet',
            'subscription_id'         => 'dps_subscription',
            'appointment_services'    => 'dps_service',
            'appointment_service_prices' => 'dps_service',
        ],
        'dps_service' => [
            'service_package_items' => 'dps_service',
        ],
    ];

    public function __construct() {
        add_action( 'dps_base_nav_tabs', [ $this, 'add_backup_tab' ], 10, 1 );
        add_action( 'dps_base_sections', [ $this, 'add_backup_section' ], 10, 1 );
        add_action( 'init', [ $this, 'maybe_handle_backup_actions' ] );
    }

    /**
     * Adiciona a aba de Backup no menu principal do plugin base.
     *
     * @param bool $visitor_only Indica se a visualização é restrita a visitantes.
     */
    public function add_backup_tab( $visitor_only ) {
        if ( $visitor_only || ! $this->current_user_can_manage() ) {
            return;
        }
        echo '<li><a href="#" class="dps-tab-link" data-tab="backup">' . esc_html__( 'Backup', 'dps-backup-addon' ) . '</a></li>';
    }

    /**
     * Adiciona a seção de Backup e Restauração à interface do plugin base.
     *
     * @param bool $visitor_only Indica se a visualização é restrita a visitantes.
     */
    public function add_backup_section( $visitor_only ) {
        if ( $visitor_only || ! $this->current_user_can_manage() ) {
            return;
        }
        echo $this->render_backup_section();
    }

    /**
     * Processa solicitações de exportação ou importação de backup.
     */
    public function maybe_handle_backup_actions() {
        if ( ! is_user_logged_in() || ! $this->current_user_can_manage() ) {
            return;
        }

        if ( isset( $_POST['dps_backup_action'] ) ) {
            $action = sanitize_text_field( wp_unslash( $_POST['dps_backup_action'] ) );
            if ( 'export' === $action ) {
                $this->handle_export_request();
            } elseif ( 'import' === $action ) {
                $this->handle_import_request();
            }
        }
    }

    /**
     * Verifica se o usuário logado pode gerenciar o plugin.
     *
     * @return bool
     */
    private function current_user_can_manage() {
        return current_user_can( 'manage_options' );
    }

    /**
     * Renderiza a interface de backup/restauração.
     *
     * @return string
     */
    private function render_backup_section() {
        $status  = isset( $_GET['dps_backup_status'] ) ? sanitize_text_field( wp_unslash( $_GET['dps_backup_status'] ) ) : '';
        $message = isset( $_GET['dps_backup_message'] ) ? sanitize_text_field( wp_unslash( $_GET['dps_backup_message'] ) ) : '';

        ob_start();
        echo '<div class="dps-section" id="dps-section-backup">';
        echo '<h3>' . esc_html__( 'Backup e Restauração', 'dps-backup-addon' ) . '</h3>';
        if ( $status ) {
            $class = 'dps-notice';
            if ( 'success' === $status ) {
                $class .= ' dps-notice-success';
                if ( ! $message ) {
                    $message = __( 'Backup restaurado com sucesso.', 'dps-backup-addon' );
                }
            } else {
                $class .= ' dps-notice-error';
                if ( ! $message ) {
                    $message = __( 'Ocorreu um erro ao processar a solicitação de backup.', 'dps-backup-addon' );
                }
            }
            echo '<div class="' . esc_attr( $class ) . '">' . esc_html( $message ) . '</div>';
        }
        echo '<p>' . esc_html__( 'Gere um arquivo com todos os dados para restaurar o sistema em outra instalação ou manter uma cópia de segurança.', 'dps-backup-addon' ) . '</p>';

        echo '<div class="dps-backup-actions">';
        echo '<form method="post">';
        wp_nonce_field( self::EXPORT_NONCE, 'dps_backup_nonce' );
        echo '<input type="hidden" name="dps_backup_action" value="export">';
        echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Baixar backup completo', 'dps-backup-addon' ) . '</button></p>';
        echo '</form>';

        echo '<hr>';
        echo '<h4>' . esc_html__( 'Restaurar dados a partir de um backup', 'dps-backup-addon' ) . '</h4>';
        echo '<p>' . esc_html__( 'A restauração substitui todos os dados atuais (clientes, pets, serviços, agendamentos, assinaturas e registros financeiros). Recomenda-se realizar um novo backup antes de continuar.', 'dps-backup-addon' ) . '</p>';
        echo '<form method="post" enctype="multipart/form-data">';
        wp_nonce_field( self::IMPORT_NONCE, 'dps_backup_import_nonce' );
        echo '<input type="hidden" name="dps_backup_action" value="import">';
        echo '<p><label>' . esc_html__( 'Arquivo de backup (.json)', 'dps-backup-addon' ) . '<br><input type="file" name="dps_backup_file" accept="application/json" required></label></p>';
        $confirm_message = esc_js( __( 'Tem certeza que deseja restaurar o backup? Esta ação não pode ser desfeita.', 'dps-backup-addon' ) );
        echo '<p><button type="submit" class="button button-secondary" onclick="return confirm(\'' . $confirm_message . '\');">' . esc_html__( 'Restaurar backup', 'dps-backup-addon' ) . '</button></p>';
        echo '</form>';
        echo '</div>';

        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Gera o arquivo de backup e envia ao navegador.
     */
    private function handle_export_request() {
        if ( ! isset( $_POST['dps_backup_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dps_backup_nonce'] ) ), self::EXPORT_NONCE ) ) {
            wp_die( esc_html__( 'Falha na verificação de segurança do backup.', 'dps-backup-addon' ) );
        }

        $data = $this->collect_backup_data();
        $json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
        if ( false === $json ) {
            wp_die( esc_html__( 'Não foi possível gerar o arquivo de backup.', 'dps-backup-addon' ) );
        }

        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="dps-backup-' . gmdate( 'Ymd-His' ) . '.json"' );
        echo $json;
        exit;
    }

    /**
     * Processa o upload e restauração do arquivo de backup.
     */
    private function handle_import_request() {
        if ( ! isset( $_POST['dps_backup_import_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dps_backup_import_nonce'] ) ), self::IMPORT_NONCE ) ) {
            wp_die( esc_html__( 'Falha na verificação de segurança da restauração.', 'dps-backup-addon' ) );
        }

        if ( empty( $_FILES['dps_backup_file'] ) || ! isset( $_FILES['dps_backup_file']['tmp_name'] ) ) {
            $this->redirect_with_status( 'error', __( 'Nenhum arquivo de backup foi enviado.', 'dps-backup-addon' ) );
        }

        $file = $_FILES['dps_backup_file'];
        if ( ! empty( $file['error'] ) ) {
            $this->redirect_with_status( 'error', __( 'Falha ao enviar o arquivo de backup.', 'dps-backup-addon' ) );
        }

        $contents = file_get_contents( $file['tmp_name'] );
        if ( false === $contents ) {
            $this->redirect_with_status( 'error', __( 'Não foi possível ler o arquivo de backup enviado.', 'dps-backup-addon' ) );
        }

        $data = json_decode( $contents, true );
        if ( null === $data || ! is_array( $data ) ) {
            $this->redirect_with_status( 'error', __( 'O arquivo informado não é um backup válido.', 'dps-backup-addon' ) );
        }

        $result = $this->restore_from_backup( $data );
        if ( is_wp_error( $result ) ) {
            $this->redirect_with_status( 'error', $result->get_error_message() );
        }

        $this->redirect_with_status( 'success', __( 'Backup restaurado com sucesso.', 'dps-backup-addon' ) );
    }

    /**
     * Redireciona mantendo a aba de backup ativa e exibindo mensagens.
     *
     * @param string $status
     * @param string $message
     */
    private function redirect_with_status( $status, $message = '' ) {
        $base = wp_get_referer();
        if ( ! $base ) {
            $base = home_url();
        }
        $base = remove_query_arg( [ 'dps_backup_status', 'dps_backup_message', 'tab' ], $base );
        $args = [
            'tab'               => 'backup',
            'dps_backup_status' => $status,
        ];
        if ( $message ) {
            $args['dps_backup_message'] = sanitize_text_field( $message );
        }
        $redirect = add_query_arg( $args, $base );
        wp_redirect( $redirect );
        exit;
    }

    /**
     * Coleta todos os dados do sistema para geração do backup.
     *
     * @return array
     */
    private function collect_backup_data() {
        $post_types = $this->get_managed_post_types();
        $posts_data = [];
        foreach ( $post_types as $post_type ) {
            $posts = get_posts( [
                'post_type'      => $post_type,
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'orderby'        => 'ID',
                'order'          => 'ASC',
            ] );
            $posts_data[ $post_type ] = [];
            foreach ( $posts as $post ) {
                if ( in_array( $post->post_status, [ 'auto-draft', 'inherit' ], true ) ) {
                    continue;
                }
                $posts_data[ $post_type ][] = [
                    'original_id' => $post->ID,
                    'post'        => $this->extract_post_fields( $post ),
                    'meta'        => $this->extract_post_meta( $post->ID ),
                ];
            }
        }

        return [
            'generated_at' => current_time( 'mysql' ),
            'site_url'     => home_url(),
            'post_types'   => $posts_data,
            'options'      => $this->collect_dps_options(),
            'tables'       => $this->collect_custom_tables(),
        ];
    }

    /**
     * Retorna a lista de post types gerenciados (prefixo dps_).
     *
     * @return array
     */
    private function get_managed_post_types() {
        $all_types = get_post_types( [], 'names' );
        $managed   = [];
        foreach ( $all_types as $type ) {
            if ( 0 === strpos( $type, 'dps_' ) ) {
                $managed[] = $type;
            }
        }
        return $managed;
    }

    /**
     * Extrai os campos relevantes de um post para o backup.
     *
     * @param WP_Post $post
     * @return array
     */
    private function extract_post_fields( $post ) {
        return [
            'post_author'       => (int) $post->post_author,
            'post_date'         => $post->post_date,
            'post_date_gmt'     => $post->post_date_gmt,
            'post_content'      => $post->post_content,
            'post_title'        => $post->post_title,
            'post_excerpt'      => $post->post_excerpt,
            'post_status'       => $post->post_status,
            'comment_status'    => $post->comment_status,
            'ping_status'       => $post->ping_status,
            'post_password'     => $post->post_password,
            'post_name'         => $post->post_name,
            'to_ping'           => $post->to_ping,
            'pinged'            => $post->pinged,
            'post_modified'     => $post->post_modified,
            'post_modified_gmt' => $post->post_modified_gmt,
            'post_content_filtered' => $post->post_content_filtered,
            'post_parent'       => (int) $post->post_parent,
            'menu_order'        => (int) $post->menu_order,
            'post_mime_type'    => $post->post_mime_type,
        ];
    }

    /**
     * Extrai toda a meta de um post para o backup.
     *
     * @param int $post_id
     * @return array
     */
    private function extract_post_meta( $post_id ) {
        $meta = get_post_meta( $post_id );
        $prepared = [];
        foreach ( $meta as $key => $values ) {
            $prepared[ $key ] = array_map( [ $this, 'prepare_meta_value' ], $values );
        }
        return $prepared;
    }

    /**
     * Normaliza valores de meta (deserializa quando necessário).
     *
     * @param mixed $value
     * @return mixed
     */
    private function prepare_meta_value( $value ) {
        if ( is_serialized( $value ) ) {
            $value = maybe_unserialize( $value );
        }
        return $value;
    }

    /**
     * Coleta todas as opções do WordPress que pertencem ao sistema (prefixo dps_).
     *
     * @return array
     */
    private function collect_dps_options() {
        global $wpdb;
        $pattern = $wpdb->esc_like( 'dps_' ) . '%';
        $option_names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern ) );
        $options = [];
        foreach ( $option_names as $name ) {
            $options[ $name ] = get_option( $name );
        }
        return $options;
    }

    /**
     * Coleta dados das tabelas personalizadas do sistema (prefixed com dps_).
     *
     * @return array
     */
    private function collect_custom_tables() {
        global $wpdb;
        $tables = [];
        $pattern = $wpdb->esc_like( $wpdb->prefix . 'dps_' ) . '%';
        $table_names = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $pattern ) );
        foreach ( $table_names as $table_name ) {
            $rows = $wpdb->get_results( "SELECT * FROM {$table_name}", ARRAY_A );
            $short = substr( $table_name, strlen( $wpdb->prefix ) );
            $tables[ $short ] = $rows;
        }
        return $tables;
    }

    /**
     * Restaura os dados a partir da estrutura decodificada do backup.
     *
     * @param array $data
     * @return true|WP_Error
     */
    private function restore_from_backup( array $data ) {
        $finance_instance = isset( $GLOBALS['dps_finance_addon'] ) ? $GLOBALS['dps_finance_addon'] : null;
        if ( $finance_instance && is_object( $finance_instance ) ) {
            remove_action( 'updated_post_meta', [ $finance_instance, 'sync_status_to_finance' ], 10 );
            remove_action( 'added_post_meta', [ $finance_instance, 'sync_status_to_finance' ], 10 );
        }

        $post_mapping = [];
        $meta_queue   = [];

        if ( isset( $data['post_types'] ) && is_array( $data['post_types'] ) ) {
            foreach ( $data['post_types'] as $post_type => $items ) {
                if ( ! post_type_exists( $post_type ) || ! is_array( $items ) ) {
                    continue;
                }
                $this->delete_existing_posts( $post_type );
                foreach ( $items as $entry ) {
                    if ( ! isset( $entry['post'] ) || ! is_array( $entry['post'] ) ) {
                        continue;
                    }
                    $old_id   = isset( $entry['original_id'] ) ? intval( $entry['original_id'] ) : 0;
                    $post_arr = $this->prepare_post_for_import( $post_type, $entry['post'] );
                    $new_id   = wp_insert_post( $post_arr, true );
                    if ( is_wp_error( $new_id ) ) {
                        if ( $finance_instance && is_object( $finance_instance ) ) {
                            add_action( 'updated_post_meta', [ $finance_instance, 'sync_status_to_finance' ], 10, 4 );
                            add_action( 'added_post_meta', [ $finance_instance, 'sync_status_to_finance' ], 10, 4 );
                        }
                        return new WP_Error( 'dps_backup_insert_post', sprintf( __( 'Falha ao importar o post do tipo %1$s (ID original %2$d).', 'dps-backup-addon' ), esc_html( $post_type ), $old_id ) );
                    }
                    if ( $old_id ) {
                        $post_mapping[ $post_type ][ $old_id ] = $new_id;
                    }
                    $meta_queue[] = [
                        'post_type' => $post_type,
                        'post_id'   => $new_id,
                        'meta'      => isset( $entry['meta'] ) && is_array( $entry['meta'] ) ? $entry['meta'] : [],
                    ];
                }
            }
        }

        foreach ( $meta_queue as $item ) {
            $this->restore_post_meta( $item['post_type'], $item['post_id'], $item['meta'], $post_mapping );
        }

        if ( isset( $data['options'] ) && is_array( $data['options'] ) ) {
            foreach ( $data['options'] as $name => $value ) {
                update_option( $name, $value );
            }
        }

        if ( isset( $data['tables'] ) && is_array( $data['tables'] ) ) {
            $this->restore_custom_tables( $data['tables'], $post_mapping );
        }

        if ( $finance_instance && is_object( $finance_instance ) ) {
            add_action( 'updated_post_meta', [ $finance_instance, 'sync_status_to_finance' ], 10, 4 );
            add_action( 'added_post_meta', [ $finance_instance, 'sync_status_to_finance' ], 10, 4 );
        }

        return true;
    }

    /**
     * Remove posts existentes do tipo informado (utilizado antes de restaurar).
     *
     * @param string $post_type
     */
    private function delete_existing_posts( $post_type ) {
        $existing = get_posts( [
            'post_type'      => $post_type,
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );
        foreach ( $existing as $post_id ) {
            wp_delete_post( $post_id, true );
        }
    }

    /**
     * Prepara o array de dados para inserção de um post restaurado.
     *
     * @param string $post_type
     * @param array  $post_data
     * @return array
     */
    private function prepare_post_for_import( $post_type, array $post_data ) {
        $author = isset( $post_data['post_author'] ) ? intval( $post_data['post_author'] ) : get_current_user_id();
        if ( $author && ! get_userdata( $author ) ) {
            $author = get_current_user_id();
        }
        $post_arr = [
            'post_type'             => $post_type,
            'post_title'            => $post_data['post_title'] ?? '',
            'post_content'          => $post_data['post_content'] ?? '',
            'post_excerpt'          => $post_data['post_excerpt'] ?? '',
            'post_status'           => $post_data['post_status'] ?? 'publish',
            'post_author'           => $author,
            'post_name'             => $post_data['post_name'] ?? '',
            'post_password'         => $post_data['post_password'] ?? '',
            'menu_order'            => isset( $post_data['menu_order'] ) ? intval( $post_data['menu_order'] ) : 0,
            'comment_status'        => $post_data['comment_status'] ?? 'closed',
            'ping_status'           => $post_data['ping_status'] ?? 'closed',
            'post_date'             => $post_data['post_date'] ?? current_time( 'mysql' ),
            'post_date_gmt'         => $post_data['post_date_gmt'] ?? gmdate( 'Y-m-d H:i:s' ),
            'post_modified'         => $post_data['post_modified'] ?? current_time( 'mysql' ),
            'post_modified_gmt'     => $post_data['post_modified_gmt'] ?? gmdate( 'Y-m-d H:i:s' ),
            'post_parent'           => isset( $post_data['post_parent'] ) ? intval( $post_data['post_parent'] ) : 0,
            'post_content_filtered' => $post_data['post_content_filtered'] ?? '',
            'post_mime_type'        => $post_data['post_mime_type'] ?? '',
            'to_ping'               => $post_data['to_ping'] ?? '',
            'pinged'                => $post_data['pinged'] ?? '',
        ];
        if ( 'trash' === $post_arr['post_status'] ) {
            $post_arr['post_status'] = 'draft';
        }
        return $post_arr;
    }

    /**
     * Restaura os metadados de um post, realizando o mapeamento de IDs conforme necessário.
     *
     * @param string $post_type
     * @param int    $post_id
     * @param array  $meta
     * @param array  $post_mapping
     */
    private function restore_post_meta( $post_type, $post_id, array $meta, array $post_mapping ) {
        foreach ( $meta as $key => $values ) {
            delete_post_meta( $post_id, $key );
            foreach ( (array) $values as $value ) {
                $remapped = $this->remap_meta_value( $post_type, $key, $value, $post_mapping );
                update_post_meta( $post_id, $key, $remapped );
            }
        }
    }

    /**
     * Ajusta valores de meta que referenciam outros objetos.
     *
     * @param string $post_type
     * @param string $meta_key
     * @param mixed  $value
     * @param array  $post_mapping
     * @return mixed
     */
    private function remap_meta_value( $post_type, $meta_key, $value, array $post_mapping ) {
        if ( isset( $this->meta_relations[ $post_type ][ $meta_key ] ) ) {
            $target_type = $this->meta_relations[ $post_type ][ $meta_key ];
            if ( 'appointment_service_prices' === $meta_key && is_array( $value ) ) {
                $remapped = [];
                foreach ( $value as $srv_id => $price ) {
                    $new_key = $this->map_single_id( $srv_id, $target_type, $post_mapping );
                    $remapped[ $new_key ] = $price;
                }
                return $remapped;
            }
            return $this->map_value_recursive( $value, $target_type, $post_mapping );
        }
        return $value;
    }

    /**
     * Aplica o mapeamento de IDs de forma recursiva em arrays aninhados.
     *
     * @param mixed  $value
     * @param string $target_type
     * @param array  $post_mapping
     * @return mixed
     */
    private function map_value_recursive( $value, $target_type, array $post_mapping ) {
        if ( is_array( $value ) ) {
            $result = [];
            foreach ( $value as $item_key => $item_value ) {
                $result[ $item_key ] = $this->map_value_recursive( $item_value, $target_type, $post_mapping );
            }
            return $result;
        }
        return $this->map_single_id( $value, $target_type, $post_mapping );
    }

    /**
     * Converte um único valor para o ID correspondente na nova instalação.
     *
     * @param mixed  $value
     * @param string $target_type
     * @param array  $post_mapping
     * @return mixed
     */
    private function map_single_id( $value, $target_type, array $post_mapping ) {
        if ( is_array( $value ) ) {
            return $this->map_value_recursive( $value, $target_type, $post_mapping );
        }
        if ( is_numeric( $value ) ) {
            $old_id = (int) $value;
            if ( isset( $post_mapping[ $target_type ][ $old_id ] ) ) {
                return $post_mapping[ $target_type ][ $old_id ];
            }
        }
        return $value;
    }

    /**
     * Restaura os dados das tabelas personalizadas.
     *
     * @param array $tables
     * @param array $post_mapping
     */
    private function restore_custom_tables( array $tables, array $post_mapping ) {
        global $wpdb;

        $finance_instance = isset( $GLOBALS['dps_finance_addon'] ) ? $GLOBALS['dps_finance_addon'] : null;
        if ( $finance_instance && is_object( $finance_instance ) ) {
            if ( method_exists( $finance_instance, 'maybe_create_transacoes_table' ) ) {
                $finance_instance->maybe_create_transacoes_table();
            }
            if ( method_exists( $finance_instance, 'maybe_create_parcelas_table' ) ) {
                $finance_instance->maybe_create_parcelas_table();
            }
        }

        $trans_map = [];
        foreach ( $tables as $short_name => $rows ) {
            $table_name = $wpdb->prefix . $short_name;
            $pattern    = $wpdb->esc_like( $table_name );
            $exists     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $pattern ) );
            if ( $exists !== $table_name ) {
                continue;
            }
            if ( empty( $rows ) ) {
                $wpdb->query( "DELETE FROM {$table_name}" );
                continue;
            }
            $wpdb->query( "DELETE FROM {$table_name}" );
            foreach ( $rows as $row ) {
                if ( 'dps_transacoes' === $short_name ) {
                    $old_id = isset( $row['id'] ) ? (int) $row['id'] : 0;
                    if ( isset( $row['cliente_id'] ) ) {
                        $row['cliente_id'] = $this->map_single_id( $row['cliente_id'], 'dps_cliente', $post_mapping ) ?: null;
                    }
                    if ( isset( $row['agendamento_id'] ) ) {
                        $row['agendamento_id'] = $this->map_single_id( $row['agendamento_id'], 'dps_agendamento', $post_mapping ) ?: null;
                    }
                    if ( isset( $row['plano_id'] ) ) {
                        $row['plano_id'] = $this->map_single_id( $row['plano_id'], 'dps_subscription', $post_mapping ) ?: null;
                    }
                    $wpdb->insert( $table_name, $row );
                    if ( $old_id ) {
                        $inserted_id         = (int) $wpdb->insert_id;
                        $trans_map[ $old_id ] = $inserted_id ? $inserted_id : ( isset( $row['id'] ) ? (int) $row['id'] : $old_id );
                    }
                } elseif ( 'dps_parcelas' === $short_name ) {
                    if ( isset( $row['trans_id'] ) && $row['trans_id'] ) {
                        $mapped = isset( $trans_map[ (int) $row['trans_id'] ] ) ? $trans_map[ (int) $row['trans_id'] ] : (int) $row['trans_id'];
                        $row['trans_id'] = $mapped;
                    }
                    $wpdb->insert( $table_name, $row );
                } else {
                    $wpdb->insert( $table_name, $row );
                }
            }
        }
    }
}

$GLOBALS['dps_backup_addon'] = new DPS_Backup_Addon();

}
