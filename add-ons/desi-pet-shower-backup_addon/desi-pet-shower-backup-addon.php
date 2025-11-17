<?php
/**
 * Plugin Name:       Desi Pet Shower – Backup & Restauração Add-on
 * Plugin URI:        https://probst.pro/desi-pet-shower
 * Description:       Add-on para gerar backups completos dos dados do Desi Pet Shower e restaurá-los em outro ambiente.
 * Version:           1.0.0
 * Author:            PRObst
 * Author URI:        https://probst.pro
 * Text Domain:       dps-backup-addon
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'DPS_Backup_Addon' ) ) {
    class DPS_Backup_Addon {

        const VERSION       = '1.0.0';
        const ACTION_EXPORT = 'dps_backup_export';
        const ACTION_IMPORT = 'dps_backup_import';

        /**
         * Registra os hooks do add-on.
         */
        public function __construct() {
            add_action( 'dps_settings_nav_tabs', [ $this, 'add_backup_tab' ], 10, 1 );
            add_action( 'dps_settings_sections', [ $this, 'add_backup_section' ], 10, 1 );

            add_action( 'admin_post_' . self::ACTION_EXPORT, [ $this, 'handle_export' ] );
            add_action( 'admin_post_nopriv_' . self::ACTION_EXPORT, [ $this, 'deny_anonymous' ] );

            add_action( 'admin_post_' . self::ACTION_IMPORT, [ $this, 'handle_import' ] );
            add_action( 'admin_post_nopriv_' . self::ACTION_IMPORT, [ $this, 'deny_anonymous' ] );
        }

        /**
         * Adiciona a aba de backup ao menu principal do plugin base.
         *
         * @param bool $visitor_only Indica se o modo visitante está ativo.
         */
        public function add_backup_tab( $visitor_only ) {
            if ( $visitor_only ) {
                return;
            }

            echo '<li><a href="#" class="dps-tab-link" data-tab="backup">' . esc_html__( 'Backup & Restauração', 'dps-backup-addon' ) . '</a></li>';
        }

        /**
         * Adiciona a seção de backup na interface principal.
         *
         * @param bool $visitor_only Indica se o modo visitante está ativo.
         */
        public function add_backup_section( $visitor_only ) {
            if ( $visitor_only ) {
                return;
            }

            if ( ! $this->can_manage() ) {
                echo '<div class="dps-section" id="dps-section-backup"><p>' . esc_html__( 'Apenas administradores podem acessar as ferramentas de backup.', 'dps-backup-addon' ) . '</p></div>';
                return;
            }

            echo $this->render_section();
        }

        /**
         * Renderiza o conteúdo da seção de backup.
         *
         * @return string
         */
        private function render_section() {
            $redirect_url = $this->get_redirect_url();
            $status       = isset( $_GET['dps_backup_status'] ) ? sanitize_key( wp_unslash( $_GET['dps_backup_status'] ) ) : '';
            $raw_message  = isset( $_GET['dps_backup_message'] ) ? wp_unslash( $_GET['dps_backup_message'] ) : '';
            $message      = $raw_message ? sanitize_text_field( urldecode( $raw_message ) ) : '';

            ob_start();
            ?>
            <div class="dps-section" id="dps-section-backup">
                <h3><?php esc_html_e( 'Backup e Restauração Completa', 'dps-backup-addon' ); ?></h3>
                <p><?php esc_html_e( 'Gere um arquivo JSON contendo todos os dados do sistema e restaure-o em outro ambiente para migrar ou recuperar informações.', 'dps-backup-addon' ); ?></p>
                <p><?php esc_html_e( 'O backup inclui clientes, pets, agendamentos, serviços, assinaturas, opções do plugin, tabelas personalizadas e arquivos enviados (como fotos dos pets e documentos financeiros).', 'dps-backup-addon' ); ?></p>
                <p><?php esc_html_e( 'A restauração substitui completamente os dados existentes do Desi Pet Shower. Recomenda-se executar em um ambiente vazio ou após realizar um backup completo do site.', 'dps-backup-addon' ); ?></p>
                <?php if ( $status && $message ) : ?>
                    <div class="notice notice-<?php echo ( 'success' === $status ) ? 'success' : 'error'; ?>" style="margin:20px 0; padding:15px; border-left:4px solid <?php echo ( 'success' === $status ) ? '#46b450' : '#dc3232'; ?>; background:#fff;">
                        <p><strong><?php echo ( 'success' === $status ) ? esc_html__( 'Sucesso:', 'dps-backup-addon' ) : esc_html__( 'Erro:', 'dps-backup-addon' ); ?></strong> <?php echo esc_html( $message ); ?></p>
                    </div>
                <?php endif; ?>
                <div class="dps-backup-actions" style="display:flex; gap:40px; flex-wrap:wrap;">
                    <div class="dps-backup-box" style="flex:1 1 280px; background:#f7f7f7; padding:20px; border-radius:8px;">
                        <h4><?php esc_html_e( 'Gerar backup', 'dps-backup-addon' ); ?></h4>
                        <p><?php esc_html_e( 'Clique no botão abaixo para baixar um arquivo JSON com todos os dados do sistema.', 'dps-backup-addon' ); ?></p>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_EXPORT ); ?>">
                            <input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_url ); ?>">
                            <?php wp_nonce_field( self::ACTION_EXPORT, 'dps_backup_nonce' ); ?>
                            <button type="submit" class="button button-primary" style="margin-top:10px;">
                                <?php esc_html_e( 'Baixar backup completo', 'dps-backup-addon' ); ?>
                            </button>
                        </form>
                    </div>
                    <div class="dps-backup-box" style="flex:1 1 280px; background:#f7f7f7; padding:20px; border-radius:8px;">
                        <h4><?php esc_html_e( 'Restaurar backup', 'dps-backup-addon' ); ?></h4>
                        <p><?php esc_html_e( 'Selecione um arquivo JSON gerado anteriormente para restaurar todos os dados.', 'dps-backup-addon' ); ?></p>
                        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_IMPORT ); ?>">
                            <input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_url ); ?>">
                            <?php wp_nonce_field( self::ACTION_IMPORT, 'dps_backup_nonce' ); ?>
                            <input type="file" name="dps_backup_file" accept="application/json" required style="margin:10px 0;" />
                            <p style="font-size:12px; color:#555;">
                                <?php esc_html_e( 'O processo substituirá os dados atuais do Desi Pet Shower. Todos os registros existentes serão removidos antes da restauração.', 'dps-backup-addon' ); ?>
                            </p>
                            <button type="submit" class="button button-secondary">
                                <?php esc_html_e( 'Restaurar dados', 'dps-backup-addon' ); ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        /**
         * Tratamento para requisições anônimas.
         */
        public function deny_anonymous() {
            wp_die( esc_html__( 'Você precisa estar autenticado como administrador para executar esta ação.', 'dps-backup-addon' ), esc_html__( 'Acesso negado', 'dps-backup-addon' ), [ 'response' => 403 ] );
        }

        /**
         * Processa a ação de exportação.
         */
        public function handle_export() {
            if ( ! $this->can_manage() ) {
                $this->deny_anonymous();
            }

            if ( ! isset( $_POST['dps_backup_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dps_backup_nonce'] ) ), self::ACTION_EXPORT ) ) {
                $this->redirect_with_message( $this->get_post_redirect(), 'error', __( 'Nonce inválido para exportação.', 'dps-backup-addon' ) );
            }

            $payload = $this->build_backup_payload();
            if ( is_wp_error( $payload ) ) {
                $this->redirect_with_message( $this->get_post_redirect(), 'error', $payload->get_error_message() );
            }

            $filename = 'dps-backup-' . gmdate( 'Ymd-His' ) . '.json';

            nocache_headers();
            header( 'Content-Type: application/json; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
            echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
            exit;
        }

        /**
         * Processa a ação de importação.
         */
        public function handle_import() {
            if ( ! $this->can_manage() ) {
                $this->deny_anonymous();
            }

            if ( ! isset( $_POST['dps_backup_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dps_backup_nonce'] ) ), self::ACTION_IMPORT ) ) {
                $this->redirect_with_message( $this->get_post_redirect(), 'error', __( 'Nonce inválido para importação.', 'dps-backup-addon' ) );
            }

            if ( empty( $_FILES['dps_backup_file']['tmp_name'] ) || UPLOAD_ERR_OK !== $_FILES['dps_backup_file']['error'] ) {
                $this->redirect_with_message( $this->get_post_redirect(), 'error', __( 'Arquivo de backup não recebido ou inválido.', 'dps-backup-addon' ) );
            }

            $file_contents = file_get_contents( $_FILES['dps_backup_file']['tmp_name'] );
            if ( false === $file_contents ) {
                $this->redirect_with_message( $this->get_post_redirect(), 'error', __( 'Não foi possível ler o arquivo enviado.', 'dps-backup-addon' ) );
            }

            $data = json_decode( $file_contents, true );
            if ( null === $data || ! is_array( $data ) ) {
                $this->redirect_with_message( $this->get_post_redirect(), 'error', __( 'O arquivo informado não é um JSON válido.', 'dps-backup-addon' ) );
            }

            $validated = $this->validate_import_payload( $data );
            if ( is_wp_error( $validated ) ) {
                $this->redirect_with_message( $this->get_post_redirect(), 'error', $validated->get_error_message() );
            }

            $result = $this->restore_backup_payload( $validated );
            if ( is_wp_error( $result ) ) {
                $this->redirect_with_message( $this->get_post_redirect(), 'error', $result->get_error_message() );
            }

            $this->redirect_with_message( $this->get_post_redirect(), 'success', __( 'Backup restaurado com sucesso.', 'dps-backup-addon' ) );
        }

        /**
         * Constrói os dados do backup.
         *
         * @return array|WP_Error
         */
        private function build_backup_payload() {
            global $wpdb;

            $posts_table    = $wpdb->posts;
            $postmeta_table = $wpdb->postmeta;
            $options_table  = $wpdb->options;

            $posts = $wpdb->get_results( "SELECT * FROM {$posts_table} WHERE post_type LIKE 'dps\\_%' ESCAPE '\\' ORDER BY ID ASC", ARRAY_A );
            $post_ids = array_map( 'intval', wp_list_pluck( $posts, 'ID' ) );

            $meta = [];
            if ( $post_ids ) {
                $ids_in = implode( ',', array_map( 'intval', $post_ids ) );
                $meta   = $wpdb->get_results( "SELECT * FROM {$postmeta_table} WHERE post_id IN ( {$ids_in} ) ORDER BY meta_id ASC", ARRAY_A );
            }

            $options = $wpdb->get_results( "SELECT option_name, option_value, autoload FROM {$options_table} WHERE option_name LIKE 'dps\\_%' ESCAPE '\\' ORDER BY option_name", ARRAY_A );

            $tables = $this->gather_custom_tables();
            if ( is_wp_error( $tables ) ) {
                return $tables;
            }

            $attachments = $this->gather_attachments( $post_ids, $meta );
            if ( is_wp_error( $attachments ) ) {
                return $attachments;
            }

            $files = $this->gather_additional_files();
            if ( is_wp_error( $files ) ) {
                return $files;
            }

            return [
                'plugin'        => 'desi-pet-shower',
                'version'       => self::VERSION,
                'schema_version' => 1,
                'generated_at'  => gmdate( 'c' ),
                'site_url'      => home_url(),
                'db_prefix'     => $wpdb->prefix,
                'clients'       => $this->export_entities_by_type( 'dps_cliente' ),
                'pets'          => $this->export_entities_by_type( 'dps_pet' ),
                'appointments'  => $this->export_entities_by_type( 'dps_agendamento' ),
                'transactions'  => $this->export_transactions(),
                'posts'         => $posts,
                'postmeta'      => $meta,
                'attachments'   => $attachments,
                'options'       => $options,
                'tables'        => $tables,
                'files'         => $files,
            ];
        }

        /**
         * Valida o payload recebido antes da restauração.
         *
         * Estrutura esperada para schema_version 1:
         * {
         *   "plugin": "desi-pet-shower",
         *   "schema_version": 1,
         *   "clients": [ { "id": 1, "post": {"post_title": "Maria"}, "meta": {"client_phone": "..."} } ],
         *   "pets": [ { "id": 2, "post": {"post_title": "Rex"}, "meta": {"owner_id": 1} } ],
         *   "appointments": [ { "id": 3, "post": {"post_title": "Banho"}, "meta": {"appointment_client_id": 1, "appointment_pet_ids": [2]} } ],
         *   "transactions": [ { "id": 10, "cliente_id": 1, "agendamento_id": 3, "valor": 120, "status": "pago" } ]
         * }
         *
         * @param array $data Dados decodificados do JSON.
         *
         * @return array|WP_Error
         */
        private function validate_import_payload( $data ) {
            if ( empty( $data['plugin'] ) || 'desi-pet-shower' !== $data['plugin'] ) {
                return new WP_Error( 'dps_backup_plugin', __( 'O arquivo não parece ser um backup do Desi Pet Shower.', 'dps-backup-addon' ) );
            }

            $schema_version = isset( $data['schema_version'] ) ? absint( $data['schema_version'] ) : 0;
            if ( 1 !== $schema_version ) {
                return new WP_Error( 'dps_backup_schema', __( 'Versão de esquema do backup ausente ou não suportada.', 'dps-backup-addon' ) );
            }

            $required_blocks = [ 'clients', 'pets', 'appointments', 'transactions' ];
            foreach ( $required_blocks as $block ) {
                if ( ! isset( $data[ $block ] ) || ! is_array( $data[ $block ] ) ) {
                    return new WP_Error( 'dps_backup_block', sprintf( __( 'O backup está incompleto: bloco %s ausente ou inválido.', 'dps-backup-addon' ), $block ) );
                }
            }

            return $data;
        }

        /**
         * Exporta entidades (posts) agrupadas por tipo com seus metadados.
         *
         * @param string $post_type Tipo de post a ser exportado.
         *
         * @return array
         */
        private function export_entities_by_type( $post_type ) {
            $items = get_posts(
                [
                    'post_type'      => $post_type,
                    'post_status'    => 'any',
                    'posts_per_page' => -1,
                    'orderby'        => 'ID',
                    'order'          => 'ASC',
                ]
            );

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
         * Exporta as transações financeiras mantendo os identificadores originais.
         *
         * @return array
         */
        private function export_transactions() {
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
         * Obtém e normaliza metadados de um post.
         *
         * @param int $post_id ID do post.
         *
         * @return array
         */
        private function collect_post_meta( $post_id ) {
            $raw  = get_post_meta( $post_id );
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
         * Restaura entidades estruturadas e constrói mapas de IDs antigos x novos.
         *
         * @param array $payload Dados validados do backup.
         */
        private function restore_structured_entities( $payload ) {
            $client_map      = [];
            $pet_map         = [];
            $appointment_map = [];

            if ( ! empty( $payload['clients'] ) ) {
                foreach ( $payload['clients'] as $client ) {
                    $old_id = isset( $client['id'] ) ? (int) $client['id'] : 0;
                    $new_id = $this->create_entity_post( $client, 'dps_cliente' );
                    $client_map[ $old_id ] = $new_id;
                }
            }

            if ( ! empty( $payload['pets'] ) ) {
                foreach ( $payload['pets'] as $pet ) {
                    $old_id = isset( $pet['id'] ) ? (int) $pet['id'] : 0;
                    $meta   = $pet['meta'] ?? [];
                    if ( isset( $meta['owner_id'] ) && isset( $client_map[ (int) $meta['owner_id'] ] ) ) {
                        $meta['owner_id'] = $client_map[ (int) $meta['owner_id'] ];
                        $pet['meta']      = $meta;
                    }
                    $new_id = $this->create_entity_post( $pet, 'dps_pet' );
                    $pet_map[ $old_id ] = $new_id;
                }
            }

            if ( ! empty( $payload['appointments'] ) ) {
                foreach ( $payload['appointments'] as $appointment ) {
                    $old_id = isset( $appointment['id'] ) ? (int) $appointment['id'] : 0;
                    $meta   = $appointment['meta'] ?? [];

                    if ( isset( $meta['appointment_client_id'] ) && isset( $client_map[ (int) $meta['appointment_client_id'] ] ) ) {
                        $meta['appointment_client_id'] = $client_map[ (int) $meta['appointment_client_id'] ];
                    }

                    if ( isset( $meta['appointment_pet_id'] ) && isset( $pet_map[ (int) $meta['appointment_pet_id'] ] ) ) {
                        $meta['appointment_pet_id'] = $pet_map[ (int) $meta['appointment_pet_id'] ];
                    }

                    if ( isset( $meta['appointment_pet_ids'] ) && is_array( $meta['appointment_pet_ids'] ) ) {
                        $mapped_pets = [];
                        foreach ( $meta['appointment_pet_ids'] as $pet_id ) {
                            $pet_id = (int) $pet_id;
                            if ( isset( $pet_map[ $pet_id ] ) ) {
                                $mapped_pets[] = $pet_map[ $pet_id ];
                            }
                        }
                        if ( $mapped_pets ) {
                            $meta['appointment_pet_ids'] = $mapped_pets;
                        }
                    }

                    $appointment['meta'] = $meta;
                    $new_id               = $this->create_entity_post( $appointment, 'dps_agendamento' );
                    $appointment_map[ $old_id ] = $new_id;
                }
            }

            if ( ! empty( $payload['transactions'] ) ) {
                $this->restore_transactions_with_mapping( $payload['transactions'], $client_map, $appointment_map );
            }
        }

        /**
         * Cria um post do tipo informado e aplica metadados.
         *
         * @param array  $entity    Dados do post (post + meta).
         * @param string $post_type Tipo de post.
         *
         * @return int Novo ID do post.
         */
        private function create_entity_post( $entity, $post_type ) {
            $post_data = $entity['post'] ?? [];

            $prepared = [
                'post_title'    => isset( $post_data['post_title'] ) ? wp_strip_all_tags( $post_data['post_title'] ) : '',
                'post_status'   => isset( $post_data['post_status'] ) ? $post_data['post_status'] : 'publish',
                'post_content'  => $post_data['post_content'] ?? '',
                'post_excerpt'  => $post_data['post_excerpt'] ?? '',
                'post_date'     => $post_data['post_date'] ?? '',
                'post_date_gmt' => $post_data['post_date_gmt'] ?? '',
                'post_name'     => $post_data['post_name'] ?? '',
                'post_type'     => $post_type,
            ];

            $prepared = array_filter( $prepared, static function( $value ) {
                return '' !== $value && null !== $value;
            } );

            $new_id = wp_insert_post( $prepared, true );
            if ( is_wp_error( $new_id ) || ! $new_id ) {
                throw new Exception( __( 'Falha ao criar registro durante a restauração.', 'dps-backup-addon' ) );
            }

            if ( ! empty( $entity['meta'] ) && is_array( $entity['meta'] ) ) {
                foreach ( $entity['meta'] as $key => $value ) {
                    update_post_meta( $new_id, $key, $value );
                }
            }

            return (int) $new_id;
        }

        /**
         * Restaura transações financeiras aplicando os novos IDs mapeados.
         *
         * @param array $transactions     Linhas exportadas da tabela.
         * @param array $client_map       Mapa de clientes antigos => novos.
         * @param array $appointment_map  Mapa de agendamentos antigos => novos.
         */
        private function restore_transactions_with_mapping( $transactions, $client_map, $appointment_map ) {
            global $wpdb;

            if ( empty( $transactions ) ) {
                return;
            }

            $table = $wpdb->prefix . 'dps_transacoes';
            $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
            if ( $exists !== $table ) {
                throw new Exception( __( 'Tabela de transações não encontrada para restauração.', 'dps-backup-addon' ) );
            }

            $wpdb->query( "TRUNCATE TABLE `{$table}`" );

            foreach ( $transactions as $row ) {
                unset( $row['id'] );

                if ( isset( $row['cliente_id'] ) && isset( $client_map[ (int) $row['cliente_id'] ] ) ) {
                    $row['cliente_id'] = $client_map[ (int) $row['cliente_id'] ];
                }

                if ( isset( $row['agendamento_id'] ) && isset( $appointment_map[ (int) $row['agendamento_id'] ] ) ) {
                    $row['agendamento_id'] = $appointment_map[ (int) $row['agendamento_id'] ];
                }

                $result = $wpdb->insert( $table, $row );
                if ( false === $result ) {
                    throw new Exception( __( 'Falha ao restaurar transações financeiras.', 'dps-backup-addon' ) );
                }
            }
        }

        /**
         * Restaura os dados a partir do payload informado.
         *
         * @param array $payload Dados do backup.
         *
         * @return true|WP_Error
         */
        private function restore_backup_payload( $payload ) {
            global $wpdb;

            $wpdb->query( 'SET autocommit = 0' );
            $wpdb->query( 'START TRANSACTION' );

            try {
                $this->wipe_existing_data();

                $this->restore_structured_entities( $payload );

                $this->restore_options( $payload['options'] ?? [] );
                $this->restore_tables( $payload['tables'] ?? [] );
                $this->restore_attachments( $payload['attachments'] ?? [] );
                $this->restore_additional_files( $payload['files'] ?? [] );

                $wpdb->query( 'COMMIT' );
                $wpdb->query( 'SET autocommit = 1' );
            } catch ( Exception $e ) {
                $wpdb->query( 'ROLLBACK' );
                $wpdb->query( 'SET autocommit = 1' );
                return new WP_Error( 'dps_backup_restore', $e->getMessage() );
            }

            return true;
        }

        /**
         * Remove dados existentes do plugin antes da restauração.
         */
        private function wipe_existing_data() {
            global $wpdb;

            $posts_table    = $wpdb->posts;
            $postmeta_table = $wpdb->postmeta;

            $attachment_meta_ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT meta_value FROM {$postmeta_table} WHERE meta_key = %s", 'pet_photo_id' ) );
            $attachment_meta_ids = array_map( 'intval', $attachment_meta_ids );
            $attachment_meta_ids = array_filter( $attachment_meta_ids );

            $existing_posts = $wpdb->get_col( "SELECT ID FROM {$posts_table} WHERE post_type LIKE 'dps\\_%' ESCAPE '\\'" );
            $attachment_by_parent = [];
            if ( $existing_posts ) {
                $ids_in              = implode( ',', array_map( 'intval', $existing_posts ) );
                $attachment_by_parent = $wpdb->get_col( "SELECT ID FROM {$posts_table} WHERE post_type = 'attachment' AND post_parent IN ( {$ids_in} )" );
            }

            $all_attachments = array_unique( array_merge( $attachment_meta_ids, array_map( 'intval', $attachment_by_parent ) ) );

            if ( $existing_posts ) {
                $ids_in = implode( ',', array_map( 'intval', $existing_posts ) );
                $meta_delete = $wpdb->query( "DELETE FROM {$postmeta_table} WHERE post_id IN ( {$ids_in} )" );
                if ( false === $meta_delete ) {
                    throw new Exception( __( 'Falha ao limpar metadados existentes antes da restauração.', 'dps-backup-addon' ) );
                }
                $post_delete = $wpdb->query( "DELETE FROM {$posts_table} WHERE ID IN ( {$ids_in} )" );
                if ( false === $post_delete ) {
                    throw new Exception( __( 'Falha ao remover posts existentes antes da restauração.', 'dps-backup-addon' ) );
                }
            }

            if ( $all_attachments ) {
                $attach_in = implode( ',', array_map( 'intval', $all_attachments ) );
                $attach_meta = $wpdb->query( "DELETE FROM {$postmeta_table} WHERE post_id IN ( {$attach_in} )" );
                if ( false === $attach_meta ) {
                    throw new Exception( __( 'Falha ao remover metadados de anexos antigos.', 'dps-backup-addon' ) );
                }
                $attach_posts = $wpdb->query( "DELETE FROM {$posts_table} WHERE ID IN ( {$attach_in} )" );
                if ( false === $attach_posts ) {
                    throw new Exception( __( 'Falha ao remover anexos antigos.', 'dps-backup-addon' ) );
                }
            }

            // Limpa tabelas personalizadas do plugin
            $tables = $this->gather_custom_tables_names();
            foreach ( $tables as $table ) {
                $result = $wpdb->query( "TRUNCATE TABLE `{$table}`" );
                if ( false === $result ) {
                    throw new Exception( sprintf( __( 'Não foi possível limpar a tabela personalizada %s.', 'dps-backup-addon' ), $table ) );
                }
            }

            $this->clear_finance_documents();
        }

        /**
         * Restaura posts do plugin diretamente na tabela wp_posts.
         *
         * @param array $posts Linhas exportadas da tabela wp_posts.
         */
        private function restore_posts( $posts ) {
            global $wpdb;

            if ( empty( $posts ) ) {
                return;
            }

            $posts_table = $wpdb->posts;
            $formats     = $this->get_post_formats();

            foreach ( $posts as $post ) {
                $row = array_intersect_key( $post, $formats );
                $row_formats = [];
                foreach ( $row as $key => $value ) {
                    if ( isset( $formats[ $key ] ) && '%d' === $formats[ $key ] ) {
                        $row[ $key ] = ( '' === $value || null === $value ) ? 0 : (int) $value;
                    }
                    if ( isset( $formats[ $key ] ) ) {
                        $row_formats[] = $formats[ $key ];
                    }
                }
                $result = $wpdb->insert( $posts_table, $row, $row_formats );
                if ( false === $result ) {
                    throw new Exception( sprintf( __( 'Falha ao restaurar o post %d: %s', 'dps-backup-addon' ), $row['ID'], $wpdb->last_error ) );
                }
                if ( isset( $row['ID'] ) ) {
                    clean_post_cache( (int) $row['ID'] );
                }
            }
        }

        /**
         * Restaura metadados dos posts do plugin.
         *
         * @param array $meta Linhas exportadas da tabela wp_postmeta.
         */
        private function restore_postmeta( $meta ) {
            global $wpdb;

            if ( empty( $meta ) ) {
                return;
            }

            $postmeta_table = $wpdb->postmeta;

            foreach ( $meta as $row ) {
                unset( $row['meta_id'] );
                $row['post_id'] = isset( $row['post_id'] ) ? (int) $row['post_id'] : 0;
                $result = $wpdb->insert( $postmeta_table, $row, [ '%d', '%s', '%s' ] );
                if ( false === $result ) {
                    throw new Exception( sprintf( __( 'Falha ao restaurar metadados do post %d: %s', 'dps-backup-addon' ), $row['post_id'], $wpdb->last_error ) );
                }
            }
        }

        /**
         * Restaura opções do plugin.
         *
         * @param array $options Lista de opções.
         */
        private function restore_options( $options ) {
            if ( empty( $options ) ) {
                return;
            }

            foreach ( $options as $option ) {
                if ( empty( $option['option_name'] ) ) {
                    continue;
                }
                $autoload = isset( $option['autoload'] ) && 'no' === $option['autoload'] ? 'no' : 'yes';
                $value    = maybe_unserialize( $option['option_value'] ?? '' );
                update_option( $option['option_name'], $value, 'yes' === $autoload );
            }
        }

        /**
         * Restaura tabelas personalizadas do plugin.
         *
         * @param array $tables Lista de tabelas exportadas.
         */
        private function restore_tables( $tables ) {
            global $wpdb;

            if ( empty( $tables ) ) {
                return;
            }

            foreach ( $tables as $table ) {
                if ( empty( $table['name'] ) ) {
                    continue;
                }
                $full_name = $wpdb->prefix . $table['name'];

                $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $full_name ) );
                if ( ! $exists && ! empty( $table['schema'] ) ) {
                    $schema = str_replace( '{prefix}', $wpdb->prefix, $table['schema'] );
                    $result = $wpdb->query( $schema );
                    if ( false === $result ) {
                        throw new Exception( sprintf( __( 'Não foi possível recriar a tabela %s: %s', 'dps-backup-addon' ), $full_name, $wpdb->last_error ) );
                    }
                } elseif ( ! $exists ) {
                    continue;
                }

                $truncate = $wpdb->query( "TRUNCATE TABLE `{$full_name}`" );
                if ( false === $truncate ) {
                    throw new Exception( sprintf( __( 'Falha ao limpar a tabela %s: %s', 'dps-backup-addon' ), $full_name, $wpdb->last_error ) );
                }

                if ( empty( $table['rows'] ) || ! is_array( $table['rows'] ) ) {
                    continue;
                }

                foreach ( $table['rows'] as $row ) {
                    $formats = array_fill( 0, count( $row ), '%s' );
                    $result = $wpdb->insert( $full_name, $row, $formats );
                    if ( false === $result ) {
                        throw new Exception( sprintf( __( 'Falha ao restaurar dados da tabela %s: %s', 'dps-backup-addon' ), $full_name, $wpdb->last_error ) );
                    }
                }
            }
        }

        /**
         * Restaura anexos exportados.
         *
         * @param array $attachments Dados dos anexos.
         */
        private function restore_attachments( $attachments ) {
            global $wpdb;

            if ( empty( $attachments ) ) {
                return;
            }

            $posts_table    = $wpdb->posts;
            $postmeta_table = $wpdb->postmeta;
            $formats        = $this->get_post_formats();

            foreach ( $attachments as $attachment ) {
                if ( empty( $attachment['post'] ) ) {
                    continue;
                }
                $post_row = array_intersect_key( $attachment['post'], $formats );
                $post_row['post_type'] = 'attachment';
                $post_formats = [];
                foreach ( $post_row as $key => $value ) {
                    if ( isset( $formats[ $key ] ) && '%d' === $formats[ $key ] ) {
                        $post_row[ $key ] = ( '' === $value || null === $value ) ? 0 : (int) $value;
                    }
                    if ( isset( $formats[ $key ] ) ) {
                        $post_formats[] = $formats[ $key ];
                    }
                }
                $result = $wpdb->insert( $posts_table, $post_row, $post_formats );
                if ( false === $result ) {
                    throw new Exception( sprintf( __( 'Falha ao restaurar o anexo %d: %s', 'dps-backup-addon' ), $post_row['ID'], $wpdb->last_error ) );
                }

                if ( ! empty( $attachment['meta'] ) && is_array( $attachment['meta'] ) ) {
                    foreach ( $attachment['meta'] as $meta_row ) {
                        unset( $meta_row['meta_id'] );
                        $meta_row['post_id'] = isset( $meta_row['post_id'] ) ? (int) $meta_row['post_id'] : 0;
                        $meta_result = $wpdb->insert( $postmeta_table, $meta_row, [ '%d', '%s', '%s' ] );
                        if ( false === $meta_result ) {
                            throw new Exception( sprintf( __( 'Falha ao restaurar metadados do anexo %d: %s', 'dps-backup-addon' ), $post_row['ID'], $wpdb->last_error ) );
                        }
                    }
                }

                if ( ! empty( $attachment['file'] ) && ! empty( $attachment['file']['path'] ) && isset( $attachment['file']['content'] ) ) {
                    $this->write_upload_file( $attachment['file']['path'], $attachment['file']['content'] );
                }
            }
        }

        /**
         * Restaura arquivos adicionais (como documentos financeiros).
         *
         * @param array $files Lista de arquivos.
         */
        private function restore_additional_files( $files ) {
            if ( empty( $files ) ) {
                return;
            }

            foreach ( $files as $file ) {
                if ( empty( $file['path'] ) ) {
                    continue;
                }
                $this->write_upload_file( $file['path'], $file['content'] ?? '' );
            }
        }

        /**
         * Grava um arquivo no diretório de uploads com base64.
         *
         * @param string $relative_path Caminho relativo em relação ao diretório de uploads.
         * @param string $content_base64 Conteúdo codificado em base64.
         */
        private function write_upload_file( $relative_path, $content_base64 ) {
            $uploads = wp_upload_dir();
            $path    = ltrim( str_replace( '\\', '/', $relative_path ), '/' );
            $target  = trailingslashit( $uploads['basedir'] ) . $path;
            $dir     = dirname( $target );

            if ( ! wp_mkdir_p( $dir ) ) {
                throw new Exception( sprintf( __( 'Não foi possível criar o diretório %s para armazenar arquivos do backup.', 'dps-backup-addon' ), $dir ) );
            }

            $data = base64_decode( $content_base64, true );
            if ( false === $data ) {
                throw new Exception( __( 'Falha ao decodificar o conteúdo de um arquivo do backup.', 'dps-backup-addon' ) );
            }

            if ( false === file_put_contents( $target, $data ) ) {
                throw new Exception( sprintf( __( 'Não foi possível gravar o arquivo %s durante a restauração.', 'dps-backup-addon' ), $target ) );
            }
        }

        /**
         * Obtém as tabelas personalizadas utilizadas pelos add-ons.
         *
         * @return array|WP_Error
         */
        private function gather_custom_tables() {
            global $wpdb;

            $prefix = $wpdb->prefix . 'dps_';
            $like   = $wpdb->esc_like( $prefix ) . '%';
            $tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
            if ( ! is_array( $tables ) ) {
                $tables = [];
            }

            $result = [];
            foreach ( $tables as $table ) {
                $name = substr( $table, strlen( $wpdb->prefix ) );
                $create = $wpdb->get_row( "SHOW CREATE TABLE `{$table}`", ARRAY_A );
                if ( empty( $create['Create Table'] ) ) {
                    return new WP_Error( 'dps_backup_table', sprintf( __( 'Não foi possível ler a estrutura da tabela %s.', 'dps-backup-addon' ), $table ) );
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
         * Obtém apenas os nomes das tabelas personalizadas.
         *
         * @return array
         */
        private function gather_custom_tables_names() {
            global $wpdb;

            $prefix = $wpdb->prefix . 'dps_';
            $like   = $wpdb->esc_like( $prefix ) . '%';

            $tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
            return is_array( $tables ) ? $tables : [];
        }

        /**
         * Reúne anexos relacionados ao plugin.
         *
         * @param array $post_ids IDs dos posts do plugin.
         * @param array $meta     Metadados exportados.
         *
         * @return array|WP_Error
         */
        private function gather_attachments( $post_ids, $meta ) {
            global $wpdb;

            $posts_table    = $wpdb->posts;
            $postmeta_table = $wpdb->postmeta;

            $attachment_ids = [];
            if ( $post_ids ) {
                $ids_in         = implode( ',', array_map( 'intval', $post_ids ) );
                $by_parent      = $wpdb->get_col( "SELECT ID FROM {$posts_table} WHERE post_type = 'attachment' AND post_parent IN ( {$ids_in} )" );
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

            $attachments = [];
            $formats     = $this->get_post_formats();

            foreach ( $attachment_ids as $attachment_id ) {
                $post = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$posts_table} WHERE ID = %d", $attachment_id ), ARRAY_A );
                if ( ! $post ) {
                    continue;
                }
                $meta_rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$postmeta_table} WHERE post_id = %d", $attachment_id ), ARRAY_A );

                $file_path = get_attached_file( $attachment_id );
                $file      = null;
                if ( $file_path && file_exists( $file_path ) ) {
                    $uploads  = wp_upload_dir();
                    $relative = str_replace( '\\', '/', $file_path );
                    $base     = str_replace( '\\', '/', $uploads['basedir'] );
                    if ( 0 === strpos( $relative, $base ) ) {
                        $relative = ltrim( substr( $relative, strlen( $base ) ), '/' );
                    } else {
                        $relative = basename( $file_path );
                    }
                    $contents = file_get_contents( $file_path );
                    if ( false === $contents ) {
                        return new WP_Error( 'dps_backup_attachment', sprintf( __( 'Não foi possível ler o arquivo do anexo %d.', 'dps-backup-addon' ), $attachment_id ) );
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
         * Reúne arquivos adicionais que devem acompanhar o backup (ex: documentos financeiros).
         *
         * @return array|WP_Error
         */
        private function gather_additional_files() {
            $uploads = wp_upload_dir();
            $dir     = trailingslashit( $uploads['basedir'] ) . 'dps_docs';

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
                $path     = 'dps_docs/' . $fileinfo->getFilename();
                $contents = file_get_contents( $fileinfo->getPathname() );
                if ( false === $contents ) {
                    return new WP_Error( 'dps_backup_file', sprintf( __( 'Não foi possível ler o arquivo %s para o backup.', 'dps-backup-addon' ), $fileinfo->getFilename() ) );
                }
                $files[] = [
                    'path'    => $path,
                    'content' => base64_encode( $contents ),
                ];
            }

            return $files;
        }

        /**
         * Remove documentos financeiros existentes.
         */
        private function clear_finance_documents() {
            $uploads = wp_upload_dir();
            $dir     = trailingslashit( $uploads['basedir'] ) . 'dps_docs';

            if ( ! is_dir( $dir ) ) {
                return;
            }

            if ( ! class_exists( 'DirectoryIterator' ) ) {
                return;
            }

            $iterator = new DirectoryIterator( $dir );
            foreach ( $iterator as $fileinfo ) {
                if ( $fileinfo->isDot() || ! $fileinfo->isFile() ) {
                    continue;
                }
                @unlink( $fileinfo->getPathname() );
            }
        }

        /**
         * Retorna os formatos das colunas da tabela wp_posts.
         *
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

        /**
         * Verifica se o usuário atual pode gerenciar as opções.
         *
         * @return bool
         */
        private function can_manage() {
            return current_user_can( 'manage_options' );
        }

        /**
         * Calcula a URL de redirecionamento utilizada após ações.
         *
         * @return string
         */
        private function get_redirect_url() {
            $current = add_query_arg( null, null );
            $current = remove_query_arg( [ 'dps_backup_status', 'dps_backup_message' ], $current );
            $current = add_query_arg( 'tab', 'backup', $current );
            return $current;
        }

        /**
         * Obtém a URL informada no formulário ou calcula uma alternativa.
         *
         * @return string
         */
        private function get_post_redirect() {
            $redirect = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';
            if ( ! $redirect ) {
                $redirect = $this->get_redirect_url();
            }
            return wp_validate_redirect( $redirect, $this->get_redirect_url() );
        }

        /**
         * Redireciona com uma mensagem status.
         *
         * @param string $redirect URL de destino.
         * @param string $status   success|error.
         * @param string $message  Mensagem a apresentar.
         */
        private function redirect_with_message( $redirect, $status, $message ) {
            $location = add_query_arg(
                [
                    'dps_backup_status'  => $status,
                    'dps_backup_message' => rawurlencode( $message ),
                    'tab'                => 'backup',
                ],
                $redirect
            );
            wp_safe_redirect( $location );
            exit;
        }
    }

    new DPS_Backup_Addon();
}
