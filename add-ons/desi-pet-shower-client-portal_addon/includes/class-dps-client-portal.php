<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Carrega a classe somente se ainda não existir.
if ( ! class_exists( 'DPS_Client_Portal' ) ) :

/**
 * Classe responsável por fornecer o portal do cliente.  Implementa:
 * - Criação automática de usuário WordPress ao cadastrar cliente.
 * - Shortcode para renderizar a área do cliente com histórico, fotos, pendências e formulários.
 * - Geração de links de pagamento para pendências usando a API do Mercado Pago.
 * - Atualização de dados do cliente e dos pets a partir do portal.
 */
final class DPS_Client_Portal {

    /**
     * Única instância da classe.
     *
     * @var DPS_Client_Portal|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única (singleton).
     *
     * @return DPS_Client_Portal
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor. Registra ganchos necessários para o funcionamento do portal.
     */
    private function __construct() {
        // Inicia sessão para autenticar clientes sem utilizar o sistema de usuários do WordPress
        add_action( 'init', function() {
            // Evita avisos de cabeçalho já enviado e não interfere em requisições AJAX/REST.
            if ( headers_sent() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
                return;
            }

            if ( ! session_id() ) {
                // Start PHP session so we can track logged‑in clients independent of WP users.
                session_start();
            }
        }, 1 );

        // Cria login para novo cliente ao salvar post do tipo dps_cliente
        add_action( 'save_post_dps_cliente', [ $this, 'maybe_create_login_for_client' ], 10, 3 );

        // Adiciona shortcode para o portal
        add_shortcode( 'dps_client_portal', [ $this, 'render_portal_shortcode' ] );
        // Adiciona shortcode para o formulário de login
        add_shortcode( 'dps_client_login', [ $this, 'render_login_shortcode' ] );

        // Processa ações de atualização do portal e login/logout
        add_action( 'init', [ $this, 'handle_portal_actions' ] );

        // Registra tipos de dados e recursos do portal
        add_action( 'init', [ $this, 'register_message_post_type' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );

        // Metaboxes e salvamento das mensagens no admin
        add_action( 'add_meta_boxes_dps_portal_message', [ $this, 'add_message_meta_boxes' ] );
        add_action( 'save_post_dps_portal_message', [ $this, 'save_message_meta' ], 10, 3 );

        // --- MUDANÇAS AQUI ---
        // Remove o menu do admin e adiciona abas no front-end
        // add_action( 'admin_menu', [ $this, 'register_client_logins_page' ] ); // Comentamos ou removemos esta linha
        add_action( 'dps_settings_nav_tabs', [ $this, 'render_logins_tab' ], 20, 1 );
        add_action( 'dps_settings_sections', [ $this, 'render_logins_section' ], 20, 1 );
    }

    /**
     * Retorna o ID do cliente associado ao usuário logado.
     *
     * @return int
     */
    private function get_client_id_for_current_user() {
        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            return 0;
        }

        $client_id = absint( get_user_meta( $user_id, 'dps_client_id', true ) );

        if ( $client_id && 'dps_cliente' === get_post_type( $client_id ) ) {
            update_post_meta( $client_id, 'client_user_id', $user_id );
            return $client_id;
        }

        $user = get_userdata( $user_id );

        if ( $user && $user->user_email ) {
            $client_query = new WP_Query( [
                'post_type'      => 'dps_cliente',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'meta_query'     => [
                    [
                        'key'     => 'client_email',
                        'value'   => $user->user_email,
                        'compare' => '=',
                    ],
                ],
            ] );

            if ( $client_query->have_posts() ) {
                $client_id = absint( $client_query->posts[0]->ID );
                update_user_meta( $user_id, 'dps_client_id', $client_id );
                update_post_meta( $client_id, 'client_user_id', $user_id );
            }

            wp_reset_postdata();
        }

        return $client_id ? $client_id : 0;
    }

    /**
     * Cria um usuário WordPress para um cliente recém-cadastrado, se ainda não existir.
     * Este usuário é do tipo "assinante" e recebe login e senha enviados por email.
     *
     * @param int     $post_id ID do post do cliente.
     * @param WP_Post $post    Objeto de post.
     * @param bool    $update  Indica se é atualização (true) ou criação (false).
     */
    /**
     * Gera um login próprio para o cliente baseado no telefone informado no cadastro.
     * Ao criar um cliente (não atualização), se o cliente tiver telefone, é criada
     * uma senha aleatória, armazenada como hash no meta 'client_password_hash'.
     * Esta senha não é enviada automaticamente, mas pode ser consultada ou redefinida
     * pela administração. Também marca uma flag para indicar que o login já foi criado.
     *
     * @param int     $post_id ID do post do cliente.
     * @param WP_Post $post    Objeto de post.
     * @param bool    $update  Indica se é atualização (true) ou criação (false).
     */
    public function maybe_create_login_for_client( $post_id, $post, $update ) {
        if ( $update ) {
            return;
        }

        if ( wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
            return;
        }

        $existing_user = absint( get_post_meta( $post_id, 'client_user_id', true ) );
        if ( $existing_user && get_userdata( $existing_user ) ) {
            return;
        }

        $phone = sanitize_text_field( get_post_meta( $post_id, 'client_phone', true ) );
        $email = sanitize_email( get_post_meta( $post_id, 'client_email', true ) );

        if ( ! $phone && ! $email ) {
            return;
        }

        $username = $phone ? 'dps_cliente_' . preg_replace( '/\D+/', '', $phone ) : 'dps_cliente_' . $post_id;
        if ( username_exists( $username ) ) {
            $username .= '_' . $post_id;
        }

        $safe_email = $email && is_email( $email ) ? $email : $username . '@example.com';
        $password   = wp_generate_password( 12, true );

        $user_data = [
            'user_login'   => sanitize_user( $username, true ),
            'user_email'   => $safe_email,
            'user_pass'    => $password,
            'display_name' => get_the_title( $post_id ),
            'role'         => 'subscriber',
        ];

        $user_id = wp_insert_user( $user_data );

        if ( is_wp_error( $user_id ) ) {
            return;
        }

        update_user_meta( $user_id, 'dps_client_id', $post_id );
        update_post_meta( $post_id, 'client_user_id', $user_id );
        update_post_meta( $post_id, 'client_login_created_at', current_time( 'mysql' ) );

        set_transient( 'dps_client_pass_' . $post_id, $password, 30 * MINUTE_IN_SECONDS );
    }

    /**
     * Processa requisições de formulários enviados pelo portal do cliente.
     * Utiliza nonce para proteção CSRF e atualiza metas conforme necessário.
     */
    public function handle_portal_actions() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $client_id = $this->get_client_id_for_current_user();

        if ( ! $client_id ) {
            return;
        }

        if ( empty( $_POST['dps_client_portal_action'] ) ) {
            return;
        }
        // Verifica nonce de segurança
        if ( ! isset( $_POST['_dps_client_portal_nonce'] ) || ! wp_verify_nonce( $_POST['_dps_client_portal_nonce'], 'dps_client_portal_action' ) ) {
            return;
        }
        $action    = sanitize_key( $_POST['dps_client_portal_action'] );

        $referer      = wp_get_referer();
        $redirect_url = $referer ? remove_query_arg( 'portal_msg', $referer ) : remove_query_arg( 'portal_msg' );

        // Processa geração de link de pagamento para pendência
        if ( 'pay_transaction' === $action && isset( $_POST['trans_id'] ) ) {
            $trans_id = intval( $_POST['trans_id'] );
            $redirect = add_query_arg( 'portal_msg', 'error', $redirect_url );
            if ( ! $this->transaction_belongs_to_client( $trans_id, $client_id ) ) {
                wp_safe_redirect( $redirect );
                exit;
            }

            $link = $this->generate_payment_link_for_transaction( $trans_id );
            if ( $link ) {
                // Redireciona para o link de pagamento
                wp_safe_redirect( $link );
                exit;
            }
            wp_safe_redirect( $redirect );
            exit;
        }

        if ( 'update_client_info' === $action ) {
            $phone = sanitize_text_field( $_POST['client_phone'] ?? '' );
            update_post_meta( $client_id, 'client_phone', $phone );

            $address = sanitize_textarea_field( $_POST['client_address'] ?? '' );
            update_post_meta( $client_id, 'client_address', $address );

            $insta = sanitize_text_field( $_POST['client_instagram'] ?? '' );
            update_post_meta( $client_id, 'client_instagram', $insta );

            $fb = sanitize_text_field( $_POST['client_facebook'] ?? '' );
            update_post_meta( $client_id, 'client_facebook', $fb );

            $email = sanitize_email( $_POST['client_email'] ?? '' );
            if ( $email && is_email( $email ) ) {
                update_post_meta( $client_id, 'client_email', $email );
            }

            $redirect_url = add_query_arg( 'portal_msg', 'updated', $redirect_url );
        } elseif ( 'update_pet' === $action && isset( $_POST['pet_id'] ) ) {
            $pet_id = intval( $_POST['pet_id'] );
            $owner_id = intval( get_post_meta( $pet_id, 'owner_id', true ) );

            if ( $owner_id === $client_id ) {
                $pet_name  = sanitize_text_field( $_POST['pet_name'] ?? '' );
                $species   = sanitize_text_field( $_POST['pet_species'] ?? '' );
                $breed     = sanitize_text_field( $_POST['pet_breed'] ?? '' );
                $size      = sanitize_text_field( $_POST['pet_size'] ?? '' );
                $weight    = sanitize_text_field( $_POST['pet_weight'] ?? '' );
                $coat      = sanitize_text_field( $_POST['pet_coat'] ?? '' );
                $color     = sanitize_text_field( $_POST['pet_color'] ?? '' );
                $birth     = sanitize_text_field( $_POST['pet_birth'] ?? '' );
                $sex       = sanitize_text_field( $_POST['pet_sex'] ?? '' );
                $vacc      = sanitize_textarea_field( $_POST['pet_vaccinations'] ?? '' );
                $allergies = sanitize_textarea_field( $_POST['pet_allergies'] ?? '' );
                $behavior  = sanitize_textarea_field( $_POST['pet_behavior'] ?? '' );

                if ( $pet_name ) {
                    wp_update_post( [ 'ID' => $pet_id, 'post_title' => $pet_name ] );
                }

                update_post_meta( $pet_id, 'pet_species', $species );
                update_post_meta( $pet_id, 'pet_breed', $breed );
                update_post_meta( $pet_id, 'pet_size', $size );
                update_post_meta( $pet_id, 'pet_weight', $weight );
                update_post_meta( $pet_id, 'pet_coat', $coat );
                update_post_meta( $pet_id, 'pet_color', $color );
                update_post_meta( $pet_id, 'pet_birth', $birth );
                update_post_meta( $pet_id, 'pet_sex', $sex );
                update_post_meta( $pet_id, 'pet_vaccinations', $vacc );
                update_post_meta( $pet_id, 'pet_allergies', $allergies );
                update_post_meta( $pet_id, 'pet_behavior', $behavior );

                if ( ! empty( $_FILES['pet_photo']['name'] ) ) {
                    $file = $_FILES['pet_photo'];
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    require_once ABSPATH . 'wp-admin/includes/image.php';

                    $upload = wp_handle_upload( $file, [ 'test_form' => false ] );
                    if ( ! isset( $upload['error'] ) && isset( $upload['file'] ) ) {
                        $file_path  = $upload['file'];
                        $file_name  = basename( $file_path );
                        $file_type  = wp_check_filetype( $file_name, null );
                        $attachment = [
                            'post_title'     => sanitize_file_name( $file_name ),
                            'post_mime_type' => $file_type['type'],
                            'post_status'    => 'inherit',
                        ];
                        $attach_id = wp_insert_attachment( $attachment, $file_path );
                        $attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
                        wp_update_attachment_metadata( $attach_id, $attach_data );
                        update_post_meta( $pet_id, 'pet_photo_id', $attach_id );
                    }
                }
            }

            $redirect_url = add_query_arg( 'portal_msg', 'updated', $redirect_url );
        } elseif ( 'send_message' === $action ) {
            $subject = isset( $_POST['message_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['message_subject'] ) ) : '';
            $content = isset( $_POST['message_body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message_body'] ) ) : '';

            if ( $content ) {
                $client_name = get_the_title( $client_id );
                $title       = $subject ? $subject : sprintf( __( 'Mensagem do cliente %s', 'dps-client-portal' ), $client_name );

                // Cria registro da mensagem no CPT
                $message_id = wp_insert_post( [
                    'post_type'    => 'dps_portal_message',
                    'post_status'  => 'publish',
                    'post_title'   => wp_strip_all_tags( $title ),
                    'post_content' => $content,
                ] );

                if ( ! is_wp_error( $message_id ) ) {
                    update_post_meta( $message_id, 'message_client_id', $client_id );
                    update_post_meta( $message_id, 'message_sender', 'client' );
                    update_post_meta( $message_id, 'message_status', 'open' );

                    // Envia notificação ao admin via Communications API
                    if ( class_exists( 'DPS_Communications_API' ) ) {
                        $api = DPS_Communications_API::get_instance();
                        $full_message = $subject ? $subject . "\n\n" . $content : $content;
                        $api->send_message_from_client( $client_id, $full_message, [
                            'message_id' => $message_id,
                            'subject'    => $subject,
                        ] );
                    } else {
                        // Fallback: envia diretamente via wp_mail (compatibilidade retroativa)
                        $admin_email = get_option( 'admin_email' );
                        if ( $admin_email ) {
                            $phone        = get_post_meta( $client_id, 'client_phone', true );
                            $email        = get_post_meta( $client_id, 'client_email', true );
                            $subject_line = sprintf( __( 'Nova mensagem do cliente %s', 'dps-client-portal' ), $client_name );
                            $body_lines   = [
                                sprintf( __( 'Cliente: %s (ID #%d)', 'dps-client-portal' ), $client_name, $client_id ),
                                $phone ? sprintf( __( 'Telefone: %s', 'dps-client-portal' ), $phone ) : '',
                                $email ? sprintf( __( 'Email: %s', 'dps-client-portal' ), $email ) : '',
                                $subject ? sprintf( __( 'Assunto: %s', 'dps-client-portal' ), $subject ) : '',
                                '',
                                __( 'Mensagem:', 'dps-client-portal' ),
                                $content,
                            ];
                            $body_lines = array_filter( $body_lines, 'strlen' );
                            wp_mail( $admin_email, $subject_line, implode( "\n", $body_lines ) );
                        }
                    }

                    $redirect_url = add_query_arg( 'portal_msg', 'message_sent', $redirect_url );
                } else {
                    $redirect_url = add_query_arg( 'portal_msg', 'message_error', $redirect_url );
                }
            } else {
                $redirect_url = add_query_arg( 'portal_msg', 'message_error', $redirect_url );
            }
        }

        wp_redirect( $redirect_url );
        exit;
    }

    /**
     * Registra estilos do portal no frontend.
     */
    public function register_assets() {
        if ( ! defined( 'DPS_CLIENT_PORTAL_ADDON_URL' ) ) {
            return;
        }

        $style_path = trailingslashit( DPS_CLIENT_PORTAL_ADDON_DIR ) . 'assets/css/client-portal.css';
        $style_url  = trailingslashit( DPS_CLIENT_PORTAL_ADDON_URL ) . 'assets/css/client-portal.css';
        $style_version = file_exists( $style_path ) ? filemtime( $style_path ) : '1.0.0';

        wp_register_style( 'dps-client-portal', $style_url, [], $style_version );
        
        $script_path = trailingslashit( DPS_CLIENT_PORTAL_ADDON_DIR ) . 'assets/js/client-portal.js';
        $script_url  = trailingslashit( DPS_CLIENT_PORTAL_ADDON_URL ) . 'assets/js/client-portal.js';
        $script_version = file_exists( $script_path ) ? filemtime( $script_path ) : '1.0.0';
        
        wp_register_script( 'dps-client-portal', $script_url, [], $script_version, true );
    }

    /**
     * Registra o tipo de post utilizado para armazenar mensagens do portal.
     */
    public function register_message_post_type() {
        $labels = [
            'name'               => __( 'Mensagens do Portal', 'dps-client-portal' ),
            'singular_name'      => __( 'Mensagem do Portal', 'dps-client-portal' ),
            'add_new'            => __( 'Adicionar nova', 'dps-client-portal' ),
            'add_new_item'       => __( 'Adicionar nova mensagem', 'dps-client-portal' ),
            'edit_item'          => __( 'Editar mensagem', 'dps-client-portal' ),
            'new_item'           => __( 'Nova mensagem', 'dps-client-portal' ),
            'view_item'          => __( 'Ver mensagem', 'dps-client-portal' ),
            'search_items'       => __( 'Buscar mensagens', 'dps-client-portal' ),
            'not_found'          => __( 'Nenhuma mensagem encontrada', 'dps-client-portal' ),
            'not_found_in_trash' => __( 'Nenhuma mensagem na lixeira', 'dps-client-portal' ),
            'all_items'          => __( 'Mensagens do Portal', 'dps-client-portal' ),
            'menu_name'          => __( 'Mensagens Portal', 'dps-client-portal' ),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'menu_position'      => 26,
            'menu_icon'          => 'dashicons-email-alt2',
            'supports'           => [ 'title', 'editor' ],
            'has_archive'        => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'map_meta_cap'       => true,
            'show_in_rest'       => false,
        ];

        register_post_type( 'dps_portal_message', $args );
    }

    /**
     * Adiciona metabox com os detalhes da mensagem no admin.
     */
    public function add_message_meta_boxes() {
        add_meta_box(
            'dps_portal_message_details',
            __( 'Detalhes da Mensagem', 'dps-client-portal' ),
            [ $this, 'render_message_meta_box' ],
            'dps_portal_message',
            'normal',
            'high'
        );
    }

    /**
     * Renderiza campos extras da mensagem no painel administrativo.
     *
     * @param WP_Post $post Post atual.
     */
    public function render_message_meta_box( $post ) {
        wp_nonce_field( 'dps_portal_message_meta', 'dps_portal_message_meta_nonce' );

        $client_id = (int) get_post_meta( $post->ID, 'message_client_id', true );
        $sender    = get_post_meta( $post->ID, 'message_sender', true );
        $status    = get_post_meta( $post->ID, 'message_status', true );

        $clients = get_posts( [
            'post_type'      => 'dps_cliente',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );

        echo '<p><label for="dps_portal_message_client">' . esc_html__( 'Cliente vinculado', 'dps-client-portal' ) . '</label><br />';
        echo '<select name="dps_portal_message_client" id="dps_portal_message_client" style="max-width:100%;">';
        echo '<option value="">' . esc_html__( 'Selecione um cliente', 'dps-client-portal' ) . '</option>';
        if ( $clients ) {
            foreach ( $clients as $client ) {
                $selected = selected( $client_id, $client->ID, false );
                echo '<option value="' . esc_attr( $client->ID ) . '" ' . $selected . '>' . esc_html( $client->post_title ) . '</option>';
            }
        }
        echo '</select></p>';

        $sender_options = [
            'admin'  => __( 'Equipe / Administração', 'dps-client-portal' ),
            'client' => __( 'Cliente', 'dps-client-portal' ),
        ];
        echo '<p><label for="dps_portal_message_sender">' . esc_html__( 'Origem da mensagem', 'dps-client-portal' ) . '</label><br />';
        echo '<select name="dps_portal_message_sender" id="dps_portal_message_sender">';
        foreach ( $sender_options as $value => $label ) {
            $selected = selected( $sender ? $sender : 'admin', $value, false );
            echo '<option value="' . esc_attr( $value ) . '" ' . $selected . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></p>';

        $status_options = [
            'open'     => __( 'Em aberto', 'dps-client-portal' ),
            'answered' => __( 'Respondida', 'dps-client-portal' ),
            'closed'   => __( 'Concluída', 'dps-client-portal' ),
        ];
        echo '<p><label for="dps_portal_message_status">' . esc_html__( 'Status da conversa', 'dps-client-portal' ) . '</label><br />';
        echo '<select name="dps_portal_message_status" id="dps_portal_message_status">';
        foreach ( $status_options as $value => $label ) {
            $selected = selected( $status ? $status : 'open', $value, false );
            echo '<option value="' . esc_attr( $value ) . '" ' . $selected . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></p>';

        $read_at = get_post_meta( $post->ID, 'client_read_at', true );
        if ( $read_at ) {
            echo '<p><em>' . esc_html( sprintf( __( 'Visualizada pelo cliente em %s', 'dps-client-portal' ), mysql2date( 'd/m/Y H:i', $read_at ) ) ) . '</em></p>';
        }
    }

    /**
     * Salva metadados da mensagem no admin.
     *
     * @param int     $post_id ID da mensagem.
     * @param WP_Post $post    Objeto do post.
     * @param bool    $update  Indica se é atualização.
     */
    public function save_message_meta( $post_id, $post, $update ) {
        if ( ! isset( $_POST['dps_portal_message_meta_nonce'] ) || ! wp_verify_nonce( $_POST['dps_portal_message_meta_nonce'], 'dps_portal_message_meta' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $client_id = isset( $_POST['dps_portal_message_client'] ) ? intval( $_POST['dps_portal_message_client'] ) : 0;
        if ( $client_id ) {
            update_post_meta( $post_id, 'message_client_id', $client_id );
        } else {
            delete_post_meta( $post_id, 'message_client_id' );
        }

        $sender = isset( $_POST['dps_portal_message_sender'] ) ? sanitize_key( $_POST['dps_portal_message_sender'] ) : 'admin';
        if ( ! in_array( $sender, [ 'admin', 'client' ], true ) ) {
            $sender = 'admin';
        }
        update_post_meta( $post_id, 'message_sender', $sender );

        $status = isset( $_POST['dps_portal_message_status'] ) ? sanitize_key( $_POST['dps_portal_message_status'] ) : 'open';
        if ( ! in_array( $status, [ 'open', 'answered', 'closed' ], true ) ) {
            $status = 'open';
        }
        update_post_meta( $post_id, 'message_status', $status );
    }

    /**
     * Renderiza a página do portal para o shortcode.  Mostra login form se usuário não estiver logado.
     *
     * @return string Conteúdo HTML renderizado.
     */
    public function render_portal_shortcode() {
        ob_start();
        wp_enqueue_style( 'dps-client-portal' );
        wp_enqueue_script( 'dps-client-portal' );
        // Se o usuário não estiver autenticado, exibe o formulário de login do WordPress
        if ( ! is_user_logged_in() ) {
            echo '<div class="dps-client-portal-login">';
            echo '<h3>' . esc_html__( 'Acesso ao Portal do Cliente', 'dps-client-portal' ) . '</h3>';
            echo do_shortcode( '[dps_client_login]' );
            echo '</div>';
            return ob_get_clean();
        }

        $client_id = $this->get_client_id_for_current_user();

        if ( ! $client_id ) {
            echo '<p>' . esc_html__( 'Nenhum cadastro de cliente foi encontrado para sua conta.', 'dps-client-portal' ) . '</p>';
            return ob_get_clean();
        }
        // Filtro de mensagens de retorno
        if ( isset( $_GET['portal_msg'] ) ) {
            $msg = sanitize_text_field( $_GET['portal_msg'] );
            if ( 'updated' === $msg ) {
                echo '<div class="dps-portal-notice dps-portal-notice--success">' . esc_html__( 'Dados atualizados com sucesso.', 'dps-client-portal' ) . '</div>';
            } elseif ( 'error' === $msg ) {
                echo '<div class="dps-portal-notice dps-portal-notice--error">' . esc_html__( 'Ocorreu um erro ao processar sua solicitação.', 'dps-client-portal' ) . '</div>';
            } elseif ( 'message_sent' === $msg ) {
                echo '<div class="dps-portal-notice dps-portal-notice--success">' . esc_html__( 'Mensagem enviada para a equipe. Responderemos em breve!', 'dps-client-portal' ) . '</div>';
            } elseif ( 'message_error' === $msg ) {
                echo '<div class="dps-portal-notice dps-portal-notice--error">' . esc_html__( 'Não foi possível enviar sua mensagem. Verifique o conteúdo e tente novamente.', 'dps-client-portal' ) . '</div>';
            }
        }
        echo '<div class="dps-client-portal">';
        echo '<h1 class="dps-portal-title">' . esc_html__( 'Bem-vindo ao Portal do Cliente', 'dps-client-portal' ) . '</h1>';
        
        // Menu de navegação interna
        echo '<nav class="dps-portal-nav">';
        echo '<a href="#proximos" class="dps-portal-nav__link">' . esc_html__( 'Próximos', 'dps-client-portal' ) . '</a>';
        echo '<a href="#historico" class="dps-portal-nav__link">' . esc_html__( 'Histórico', 'dps-client-portal' ) . '</a>';
        echo '<a href="#galeria" class="dps-portal-nav__link">' . esc_html__( 'Galeria', 'dps-client-portal' ) . '</a>';
        echo '<a href="#mensagens" class="dps-portal-nav__link">' . esc_html__( 'Mensagens', 'dps-client-portal' ) . '</a>';
        echo '<a href="#dados" class="dps-portal-nav__link">' . esc_html__( 'Meus Dados', 'dps-client-portal' ) . '</a>';
        echo '</nav>';
        // Renderiza seções utilizando o ID do cliente
        $this->render_next_appointment( $client_id );
        $this->render_financial_pending( $client_id );
        $this->render_appointment_history( $client_id );
        $this->render_pet_gallery( $client_id );
        $this->render_message_center( $client_id );
        if ( function_exists( 'dps_loyalty_get_referral_code' ) ) {
            $this->render_referrals_summary( $client_id );
        }
        $this->render_update_forms( $client_id );

        // Hook para add-ons adicionarem conteúdo ao final do portal (ex: AI Assistant)
        do_action( 'dps_client_portal_after_content', $client_id );

        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Renderiza seção do próximo agendamento.
     *
     * @param int $client_id ID do cliente.
     */
    private function render_next_appointment( $client_id ) {
        echo '<section id="proximos" class="dps-portal-section dps-portal-next">';
        echo '<h2>' . esc_html__( 'Próximo Agendamento', 'dps-client-portal' ) . '</h2>';
        $today     = current_time( 'Y-m-d' );
        $args      = [
            'post_type'      => 'dps_agendamento',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => 'appointment_client_id',
                    'value'   => $client_id,
                    'compare' => '=',
                ],
            ],
            'orderby'        => 'meta_value',
            'meta_key'       => 'appointment_date',
            'order'          => 'ASC',
        ];
        $appointments = get_posts( $args );
        $next         = null;
        if ( $appointments ) {
            foreach ( $appointments as $appt ) {
                $date   = get_post_meta( $appt->ID, 'appointment_date', true );
                $status = get_post_meta( $appt->ID, 'appointment_status', true );
                // Considera apenas status pendentes e datas futuras ou hoje
                if ( $date && strtotime( $date ) >= strtotime( $today ) && ! in_array( $status, [ 'finalizado', 'finalizado e pago', 'finalizado_pago', 'cancelado' ], true ) ) {
                    $next = $appt;
                    break;
                }
            }
        }
        if ( $next ) {
            $pet_id    = get_post_meta( $next->ID, 'appointment_pet_id', true );
            $pet_name  = $pet_id ? get_the_title( $pet_id ) : '';
            $services  = get_post_meta( $next->ID, 'appointment_services', true );
            $services  = is_array( $services ) ? implode( ', ', array_map( 'esc_html', $services ) ) : '';
            $date      = get_post_meta( $next->ID, 'appointment_date', true );
            $time      = get_post_meta( $next->ID, 'appointment_time', true );
            $status    = get_post_meta( $next->ID, 'appointment_status', true );
            
            // Card de destaque para próximo agendamento
            echo '<div class="dps-appointment-card">';
            echo '<div class="dps-appointment-card__date">';
            echo '<span class="dps-appointment-card__day">' . esc_html( date_i18n( 'd', strtotime( $date ) ) ) . '</span>';
            echo '<span class="dps-appointment-card__month">' . esc_html( date_i18n( 'M', strtotime( $date ) ) ) . '</span>';
            echo '</div>';
            echo '<div class="dps-appointment-card__details">';
            echo '<div class="dps-appointment-card__time">⏰ ' . esc_html( $time ) . '</div>';
            if ( $pet_name ) {
                echo '<div class="dps-appointment-card__pet">🐾 ' . esc_html( $pet_name ) . '</div>';
            }
            if ( $services ) {
                echo '<div class="dps-appointment-card__services">✂️ ' . $services . '</div>';
            }
            if ( $status ) {
                echo '<div class="dps-appointment-card__status">' . esc_html( ucfirst( $status ) ) . '</div>';
            }
            // Link para mapa
            $address = get_post_meta( $client_id, 'client_address', true );
            if ( $address ) {
                $query = urlencode( $address );
                $url   = 'https://www.google.com/maps/search/?api=1&query=' . $query;
                echo '<a href="' . esc_url( $url ) . '" target="_blank" class="dps-appointment-card__action">📍 ' . esc_html__( 'Ver no mapa', 'dps-client-portal' ) . '</a>';
            }
            echo '</div>';
            echo '</div>';
        } else {
            // Estado vazio amigável
            echo '<div class="dps-empty-state">';
            echo '<div class="dps-empty-state__icon">📅</div>';
            echo '<div class="dps-empty-state__message">' . esc_html__( 'Você não tem agendamentos futuros.', 'dps-client-portal' ) . '</div>';
            $whatsapp_number = '5551999999999'; // TODO: configurar número do WhatsApp
            $whatsapp_text = urlencode( 'Olá! Gostaria de agendar um serviço.' );
            $whatsapp_url = 'https://wa.me/' . $whatsapp_number . '?text=' . $whatsapp_text;
            echo '<a href="' . esc_url( $whatsapp_url ) . '" target="_blank" class="dps-empty-state__action button button-primary">💬 ' . esc_html__( 'Agendar via WhatsApp', 'dps-client-portal' ) . '</a>';
            echo '</div>';
        }
        echo '</section>';
    }

    /**
     * Renderiza a seção de pendências financeiras do cliente.
     *
     * @param int $client_id ID do cliente.
     */
    private function render_financial_pending( $client_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';
        // Busca transações com status em aberto
        $pendings = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE cliente_id = %d AND status IN ('em_aberto', 'pendente')", $client_id ) );
        echo '<section id="pendencias" class="dps-portal-section dps-portal-finances">';
        echo '<h2>' . esc_html__( 'Pendências Financeiras', 'dps-client-portal' ) . '</h2>';
        
        if ( $pendings ) {
            // Calcula total de pendências
            $total = 0;
            foreach ( $pendings as $trans ) {
                $total += (float) $trans->valor;
            }
            
            // Alert de pendências
            echo '<div class="dps-alert dps-alert--warning">';
            echo '<div class="dps-alert__content">';
            echo '⚠️ ' . esc_html( sprintf( 
                _n( 'Você tem %d pendência totalizando R$ %s.', 'Você tem %d pendências totalizando R$ %s.', count( $pendings ), 'dps-client-portal' ),
                count( $pendings ),
                number_format( $total, 2, ',', '.' )
            ) );
            echo '</div>';
            echo '</div>';
            
            echo '<table class="dps-table"><thead><tr>';
            echo '<th>' . esc_html__( 'Data', 'dps-client-portal' ) . '</th>';
            echo '<th>' . esc_html__( 'Descrição', 'dps-client-portal' ) . '</th>';
            echo '<th>' . esc_html__( 'Valor', 'dps-client-portal' ) . '</th>';
            echo '<th>' . esc_html__( 'Ação', 'dps-client-portal' ) . '</th>';
            echo '</tr></thead><tbody>';
            foreach ( $pendings as $trans ) {
                $date = $trans->data;
                $desc = $trans->descricao ? $trans->descricao : __( 'Serviço', 'dps-client-portal' );
                $valor = number_format( (float) $trans->valor, 2, ',', '.' );
                echo '<tr>';
                echo '<td data-label="' . esc_attr__( 'Data', 'dps-client-portal' ) . '">' . esc_html( date_i18n( 'd-m-Y', strtotime( $date ) ) ) . '</td>';
                echo '<td data-label="' . esc_attr__( 'Descrição', 'dps-client-portal' ) . '">' . esc_html( $desc ) . '</td>';
                echo '<td data-label="' . esc_attr__( 'Valor', 'dps-client-portal' ) . '">R$ ' . esc_html( $valor ) . '</td>';
                // Gera link de pagamento via formulário
                echo '<td data-label="' . esc_attr__( 'Ação', 'dps-client-portal' ) . '">';
                echo '<form method="post" style="display:inline;">';
                wp_nonce_field( 'dps_client_portal_action', '_dps_client_portal_nonce' );
                echo '<input type="hidden" name="dps_client_portal_action" value="pay_transaction">';
                echo '<input type="hidden" name="trans_id" value="' . esc_attr( $trans->id ) . '">';
                echo '<button type="submit" class="button button-secondary dps-btn-pay">' . esc_html__( 'Pagar', 'dps-client-portal' ) . '</button>';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            // Estado vazio positivo
            echo '<div class="dps-alert dps-alert--success">';
            echo '<div class="dps-alert__content">';
            echo '✅ ' . esc_html__( 'Parabéns! Você está em dia com seus pagamentos.', 'dps-client-portal' );
            echo '</div>';
            echo '</div>';
        }
        echo '</section>';
    }

    /**
     * Renderiza a seção de histórico de agendamentos do cliente.
     *
     * @param int $client_id ID do cliente.
     */
    private function render_appointment_history( $client_id ) {
        $args = [
            'post_type'      => 'dps_agendamento',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => 'appointment_client_id',
                    'value'   => $client_id,
                    'compare' => '=',
                ],
            ],
            'orderby'        => 'meta_value',
            'meta_key'       => 'appointment_date',
            'order'          => 'DESC',
        ];
        $appointments = get_posts( $args );
        echo '<section id="historico" class="dps-portal-section dps-portal-history">';
        echo '<h2>' . esc_html__( 'Histórico de Atendimentos', 'dps-client-portal' ) . '</h2>';
        if ( $appointments ) {
            echo '<table class="dps-table"><thead><tr>';
            echo '<th>' . esc_html__( 'Data', 'dps-client-portal' ) . '</th>';
            echo '<th>' . esc_html__( 'Horário', 'dps-client-portal' ) . '</th>';
            echo '<th>' . esc_html__( 'Pet', 'dps-client-portal' ) . '</th>';
            echo '<th>' . esc_html__( 'Serviços', 'dps-client-portal' ) . '</th>';
            echo '<th>' . esc_html__( 'Status', 'dps-client-portal' ) . '</th>';
            echo '</tr></thead><tbody>';
            foreach ( $appointments as $appt ) {
                $date   = get_post_meta( $appt->ID, 'appointment_date', true );
                $time   = get_post_meta( $appt->ID, 'appointment_time', true );
                $status = get_post_meta( $appt->ID, 'appointment_status', true );
                $pet_id = get_post_meta( $appt->ID, 'appointment_pet_id', true );
                $pet_name = $pet_id ? get_the_title( $pet_id ) : '';
                $services = get_post_meta( $appt->ID, 'appointment_services', true );
                if ( is_array( $services ) ) {
                    $services = implode( ', ', array_map( 'esc_html', $services ) );
                } else {
                    $services = '';
                }
                echo '<tr>';
                echo '<td data-label="' . esc_attr__( 'Data', 'dps-client-portal' ) . '">' . esc_html( $date ? date_i18n( 'd-m-Y', strtotime( $date ) ) : '' ) . '</td>';
                echo '<td data-label="' . esc_attr__( 'Horário', 'dps-client-portal' ) . '">' . esc_html( $time ) . '</td>';
                echo '<td data-label="' . esc_attr__( 'Pet', 'dps-client-portal' ) . '">' . esc_html( $pet_name ) . '</td>';
                echo '<td data-label="' . esc_attr__( 'Serviços', 'dps-client-portal' ) . '">' . $services . '</td>';
                echo '<td data-label="' . esc_attr__( 'Status', 'dps-client-portal' ) . '">' . esc_html( ucfirst( $status ) ) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>' . esc_html__( 'Nenhum atendimento encontrado.', 'dps-client-portal' ) . '</p>';
        }
        echo '</section>';
    }

    /**
     * Renderiza a seção de galeria de fotos dos pets do cliente.
     *
     * @param int $client_id ID do cliente.
     */
    private function render_pet_gallery( $client_id ) {
        // Obtém pets do cliente
        $pets = get_posts( [
            'post_type'      => 'dps_pet',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_key'       => 'owner_id',
            'meta_value'     => $client_id,
        ] );
        echo '<section id="galeria" class="dps-portal-section dps-portal-gallery">';
        echo '<h2>' . esc_html__( 'Galeria de Fotos', 'dps-client-portal' ) . '</h2>';
        if ( $pets ) {
            echo '<div class="dps-portal-gallery-grid">';
            foreach ( $pets as $pet ) {
                $photo_id = get_post_meta( $pet->ID, 'pet_photo_id', true );
                $pet_name = $pet->post_title;
                echo '<div class="dps-portal-photo-item">';
                echo '<h4>' . esc_html( $pet_name ) . '</h4>';
                if ( $photo_id ) {
                    $img_url = wp_get_attachment_image_url( $photo_id, 'medium' );
                    if ( $img_url ) {
                        // Link para compartilhar via WhatsApp
                        $wa_text = urlencode( sprintf( __( 'Olha que fofo estou após o banho/tosa no Desi Pet Shower! %s', 'dps-client-portal' ), $img_url ) );
                        $wa_link = 'https://wa.me/?text=' . $wa_text;
                        echo '<a href="' . esc_url( $img_url ) . '" target="_blank"><img src="' . esc_url( $img_url ) . '" alt="' . esc_attr( $pet_name ) . '" style="max-width:100%;height:auto;" /></a><br>';
                        echo '<a href="' . esc_url( $wa_link ) . '" target="_blank" class="dps-share-whatsapp">' . esc_html__( 'Compartilhar via WhatsApp', 'dps-client-portal' ) . '</a>';
                    } else {
                        echo '<p>' . esc_html__( 'Sem foto disponível.', 'dps-client-portal' ) . '</p>';
                    }
                } else {
                    echo '<p>' . esc_html__( 'Sem foto disponível.', 'dps-client-portal' ) . '</p>';
                }
                echo '</div>';
            }
            echo '</div>';
        } else {
            echo '<p>' . esc_html__( 'Nenhum pet cadastrado.', 'dps-client-portal' ) . '</p>';
        }
        echo '</section>';
    }

    /**
     * Renderiza o centro de mensagens entre cliente e administração.
     *
     * @param int $client_id ID do cliente.
     */
    private function render_message_center( $client_id ) {
        echo '<section id="mensagens" class="dps-portal-section dps-portal-messages">';
        echo '<h2>' . esc_html__( 'Mensagens com a equipe', 'dps-client-portal' ) . '</h2>';

        $messages = get_posts( [
            'post_type'      => 'dps_portal_message',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'ASC',
            'meta_key'       => 'message_client_id',
            'meta_value'     => $client_id,
        ] );

        if ( $messages ) {
            echo '<div class="dps-portal-messages__list">';
            foreach ( $messages as $message ) {
                $sender  = get_post_meta( $message->ID, 'message_sender', true );
                $status  = get_post_meta( $message->ID, 'message_status', true );
                $classes = [ 'dps-portal-message' ];
                $classes[] = ( 'client' === $sender ) ? 'dps-portal-message--client' : 'dps-portal-message--admin';
                echo '<article class="' . esc_attr( implode( ' ', $classes ) ) . '">';

                $author_label = ( 'client' === $sender )
                    ? esc_html__( 'Você', 'dps-client-portal' )
                    : esc_html__( 'Equipe Desi Pet Shower', 'dps-client-portal' );
                $date_display = get_post_time( 'd/m/Y H:i', false, $message, true );

                echo '<div class="dps-portal-message__meta">';
                echo '<span class="dps-portal-message__author">' . esc_html( $author_label ) . '</span>';
                echo '<span class="dps-portal-message__date">' . esc_html( $date_display ) . '</span>';
                echo '</div>';

                if ( $status ) {
                    echo '<div class="dps-portal-message__status">' . esc_html( $this->get_message_status_label( $status ) ) . '</div>';
                }

                $content = $message->post_content ? wpautop( esc_html( $message->post_content ) ) : '';
                echo '<div class="dps-portal-message__content">' . $content . '</div>';
                echo '</article>';

                if ( 'admin' === $sender && ! get_post_meta( $message->ID, 'client_read_at', true ) ) {
                    update_post_meta( $message->ID, 'client_read_at', current_time( 'mysql' ) );
                }
            }
            echo '</div>';
        } else {
            echo '<p>' . esc_html__( 'Ainda não há mensagens no seu histórico.', 'dps-client-portal' ) . '</p>';
        }

        echo '<div class="dps-portal-messages__form">';
        echo '<h3>' . esc_html__( 'Enviar nova mensagem', 'dps-client-portal' ) . '</h3>';
        echo '<form method="post" class="dps-portal-form">';
        wp_nonce_field( 'dps_client_portal_action', '_dps_client_portal_nonce' );
        echo '<input type="hidden" name="dps_client_portal_action" value="send_message">';
        echo '<p><label>' . esc_html__( 'Assunto (opcional)', 'dps-client-portal' ) . '<br><input type="text" name="message_subject"></label></p>';
        echo '<p><label>' . esc_html__( 'Mensagem', 'dps-client-portal' ) . '<br><textarea name="message_body" required></textarea></label></p>';
        echo '<p><button type="submit" class="button button-primary dps-submit-btn">' . esc_html__( 'Enviar para a equipe', 'dps-client-portal' ) . '</button></p>';
        echo '</form>';
        echo '</div>';

        echo '</section>';
    }

    /**
     * Recupera o rótulo legível para o status da mensagem.
     *
     * @param string $status Status salvo na mensagem.
     * @return string
     */
    private function get_message_status_label( $status ) {
        $labels = [
            'open'     => __( 'Em aberto', 'dps-client-portal' ),
            'answered' => __( 'Respondida', 'dps-client-portal' ),
            'closed'   => __( 'Concluída', 'dps-client-portal' ),
        ];

        return $labels[ $status ] ?? '';
    }

    /**
     * Renderiza um resumo do programa Indique e Ganhe no portal do cliente.
     *
     * @param int $client_id ID do cliente.
     */
    private function render_referrals_summary( $client_id ) {
        $code = dps_loyalty_get_referral_code( $client_id );
        if ( ! $code ) {
            return;
        }

        $base_url  = '';
        $page_id   = (int) get_option( 'dps_registration_page_id', 0 );
        $base_url  = $page_id ? get_permalink( $page_id ) : site_url( '/cadastro/' );
        $share_url = add_query_arg( 'ref', rawurlencode( $code ), $base_url );

        global $wpdb;
        $table        = $wpdb->prefix . 'dps_referrals';
        $rewarded_cnt = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE referrer_client_id = %d AND status = %s", $client_id, 'rewarded' ) );
        $points       = function_exists( 'dps_loyalty_get_points' ) ? dps_loyalty_get_points( $client_id ) : 0;
        $credit       = function_exists( 'dps_loyalty_get_credit' ) ? dps_loyalty_get_credit( $client_id ) : 0;

        echo '<section class="dps-portal-section dps-portal-referrals">';
        echo '<h2>' . esc_html__( 'Indique e Ganhe', 'desi-pet-shower' ) . '</h2>';
        echo '<p>' . esc_html__( 'Compartilhe seu código e acompanhe as recompensas.', 'desi-pet-shower' ) . '</p>';
        echo '<p class="dps-referral-code"><strong>' . esc_html__( 'Seu código:', 'desi-pet-shower' ) . '</strong> ' . esc_html( $code ) . '</p>';
        echo '<p class="dps-referral-link"><strong>' . esc_html__( 'Seu link:', 'desi-pet-shower' ) . '</strong> <a href="' . esc_url( $share_url ) . '" target="_blank" rel="noopener">' . esc_html( $share_url ) . '</a></p>';
        echo '<ul class="dps-referral-stats">';
        echo '<li><strong>' . esc_html__( 'Indicações com recompensa:', 'desi-pet-shower' ) . '</strong> ' . esc_html( (int) $rewarded_cnt ) . '</li>';
        echo '<li><strong>' . esc_html__( 'Pontos acumulados:', 'desi-pet-shower' ) . '</strong> ' . esc_html( $points ) . '</li>';
        if ( $credit ) {
            $formatted_credit = function_exists( 'dps_format_money_br' ) ? dps_format_money_br( $credit ) : $credit;
            echo '<li><strong>' . esc_html__( 'Créditos disponíveis:', 'desi-pet-shower' ) . '</strong> R$ ' . esc_html( $formatted_credit ) . '</li>';
        }
        echo '</ul>';
        echo '</section>';
    }

    /**
     * Renderiza formulários para atualização de dados pessoais e dos pets.
     *
     * @param int $client_id ID do cliente.
     */
    private function render_update_forms( $client_id ) {
        // Recupera metadados do cliente
        $meta = [
            'phone'     => get_post_meta( $client_id, 'client_phone', true ),
            'email'     => get_post_meta( $client_id, 'client_email', true ),
            'address'   => get_post_meta( $client_id, 'client_address', true ),
            'instagram' => get_post_meta( $client_id, 'client_instagram', true ),
            'facebook'  => get_post_meta( $client_id, 'client_facebook', true ),
        ];
        echo '<section id="dados" class="dps-portal-section dps-portal-update">';
        echo '<h2>' . esc_html__( 'Atualizar Dados Pessoais', 'dps-client-portal' ) . '</h2>';
        echo '<form method="post" class="dps-portal-form">';
        wp_nonce_field( 'dps_client_portal_action', '_dps_client_portal_nonce' );
        echo '<input type="hidden" name="dps_client_portal_action" value="update_client_info">';
        
        // Fieldset: Dados de Contato
        echo '<fieldset class="dps-fieldset">';
        echo '<legend class="dps-fieldset__legend">' . esc_html__( 'Dados de Contato', 'dps-client-portal' ) . '</legend>';
        echo '<p><label>' . esc_html__( 'Telefone / WhatsApp', 'dps-client-portal' ) . '<br>';
        echo '<input type="tel" name="client_phone" value="' . esc_attr( $meta['phone'] ) . '" autocomplete="tel" placeholder="(00) 00000-0000" style="font-size: 16px;"></label></p>';
        echo '<p><label>' . esc_html__( 'Email', 'dps-client-portal' ) . '<br>';
        echo '<input type="email" name="client_email" value="' . esc_attr( $meta['email'] ) . '" autocomplete="email" placeholder="seuemail@exemplo.com" style="font-size: 16px;"></label></p>';
        echo '</fieldset>';
        
        // Fieldset: Endereço
        echo '<fieldset class="dps-fieldset">';
        echo '<legend class="dps-fieldset__legend">' . esc_html__( 'Endereço', 'dps-client-portal' ) . '</legend>';
        echo '<p><label>' . esc_html__( 'Endereço completo', 'dps-client-portal' ) . '<br>';
        echo '<textarea name="client_address" rows="2" autocomplete="street-address" placeholder="Rua, Número, Bairro, Cidade - UF" style="font-size: 16px;">' . esc_textarea( $meta['address'] ) . '</textarea></label></p>';
        echo '</fieldset>';
        
        // Fieldset: Redes Sociais (Opcional) - Grid 2 colunas
        echo '<fieldset class="dps-fieldset">';
        echo '<legend class="dps-fieldset__legend">' . esc_html__( 'Redes Sociais (Opcional)', 'dps-client-portal' ) . '</legend>';
        echo '<div class="dps-form-row dps-form-row--2col">';
        echo '<p class="dps-form-col"><label>Instagram<br><input type="text" name="client_instagram" value="' . esc_attr( $meta['instagram'] ) . '" placeholder="@usuario" style="font-size: 16px;"></label></p>';
        echo '<p class="dps-form-col"><label>Facebook<br><input type="text" name="client_facebook" value="' . esc_attr( $meta['facebook'] ) . '" placeholder="Nome do perfil" style="font-size: 16px;"></label></p>';
        echo '</div>';
        echo '</fieldset>';
        
        echo '<p><button type="submit" class="button button-primary dps-submit-btn">' . esc_html__( 'Salvar Dados', 'dps-client-portal' ) . '</button></p>';
        echo '</form>';
        
        // Lista pets para edição
        $pets = get_posts( [
            'post_type'      => 'dps_pet',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_key'       => 'owner_id',
            'meta_value'     => $client_id,
        ] );
        if ( $pets ) {
            echo '<h3>' . esc_html__( 'Atualizar Pets', 'dps-client-portal' ) . '</h3>';
            foreach ( $pets as $pet ) {
                $pet_id = $pet->ID;
                $meta_pet = [
                    'species'   => get_post_meta( $pet_id, 'pet_species', true ),
                    'breed'     => get_post_meta( $pet_id, 'pet_breed', true ),
                    'size'      => get_post_meta( $pet_id, 'pet_size', true ),
                    'weight'    => get_post_meta( $pet_id, 'pet_weight', true ),
                    'coat'      => get_post_meta( $pet_id, 'pet_coat', true ),
                    'color'     => get_post_meta( $pet_id, 'pet_color', true ),
                    'birth'     => get_post_meta( $pet_id, 'pet_birth', true ),
                    'sex'       => get_post_meta( $pet_id, 'pet_sex', true ),
                    'vaccinations' => get_post_meta( $pet_id, 'pet_vaccinations', true ),
                    'allergies'    => get_post_meta( $pet_id, 'pet_allergies', true ),
                    'behavior'     => get_post_meta( $pet_id, 'pet_behavior', true ),
                ];
                echo '<div class="dps-pet-update-form">';
                echo '<h4>' . esc_html( $pet->post_title ) . '</h4>';
                echo '<form method="post" enctype="multipart/form-data" class="dps-portal-form">';
                wp_nonce_field( 'dps_client_portal_action', '_dps_client_portal_nonce' );
                echo '<input type="hidden" name="dps_client_portal_action" value="update_pet">';
                echo '<input type="hidden" name="pet_id" value="' . esc_attr( $pet_id ) . '">';
                
                // Fieldset: Dados Básicos - reorganizado em grid
                echo '<fieldset class="dps-fieldset">';
                echo '<legend class="dps-fieldset__legend">' . esc_html__( 'Dados Básicos', 'dps-client-portal' ) . '</legend>';
                echo '<p><label>' . esc_html__( 'Nome', 'dps-client-portal' ) . '<br><input type="text" name="pet_name" value="' . esc_attr( $pet->post_title ) . '" placeholder="Nome do pet" style="font-size: 16px;"></label></p>';
                
                echo '<div class="dps-form-row dps-form-row--2col">';
                echo '<p class="dps-form-col"><label>' . esc_html__( 'Espécie', 'dps-client-portal' ) . '<br><input type="text" name="pet_species" value="' . esc_attr( $meta_pet['species'] ) . '" placeholder="Cachorro, Gato..." style="font-size: 16px;"></label></p>';
                echo '<p class="dps-form-col"><label>' . esc_html__( 'Raça', 'dps-client-portal' ) . '<br><input type="text" name="pet_breed" value="' . esc_attr( $meta_pet['breed'] ) . '" placeholder="Raça do pet" style="font-size: 16px;"></label></p>';
                echo '</div>';
                
                echo '<div class="dps-form-row dps-form-row--3col">';
                echo '<p class="dps-form-col"><label>' . esc_html__( 'Tamanho', 'dps-client-portal' ) . '<br><input type="text" name="pet_size" value="' . esc_attr( $meta_pet['size'] ) . '" placeholder="Pequeno/Médio/Grande" style="font-size: 16px;"></label></p>';
                echo '<p class="dps-form-col"><label>' . esc_html__( 'Peso (kg)', 'dps-client-portal' ) . '<br><input type="text" name="pet_weight" value="' . esc_attr( $meta_pet['weight'] ) . '" placeholder="5.5" style="font-size: 16px;"></label></p>';
                echo '<p class="dps-form-col"><label>' . esc_html__( 'Sexo', 'dps-client-portal' ) . '<br><select name="pet_sex" style="font-size: 16px;"><option value="">' . esc_html__( 'Selecione...', 'dps-client-portal' ) . '</option>';
                $sex_opts = [ 'M' => 'Macho', 'F' => 'Fêmea' ];
                foreach ( $sex_opts as $val => $label ) {
                    $sel = ( $meta_pet['sex'] === $val ) ? 'selected' : '';
                    echo '<option value="' . esc_attr( $val ) . '" ' . $sel . '>' . esc_html( $label ) . '</option>';
                }
                echo '</select></label></p>';
                echo '</div>';
                
                echo '<div class="dps-form-row dps-form-row--2col">';
                echo '<p class="dps-form-col"><label>' . esc_html__( 'Tipo de pelo', 'dps-client-portal' ) . '<br><input type="text" name="pet_coat" value="' . esc_attr( $meta_pet['coat'] ) . '" placeholder="Curto, longo..." style="font-size: 16px;"></label></p>';
                echo '<p class="dps-form-col"><label>' . esc_html__( 'Cor predominante', 'dps-client-portal' ) . '<br><input type="text" name="pet_color" value="' . esc_attr( $meta_pet['color'] ) . '" placeholder="Branco, preto..." style="font-size: 16px;"></label></p>';
                echo '</div>';
                
                echo '<p><label>' . esc_html__( 'Data de nascimento', 'dps-client-portal' ) . '<br><input type="date" name="pet_birth" value="' . esc_attr( $meta_pet['birth'] ) . '" style="font-size: 16px;"></label></p>';
                echo '</fieldset>';
                
                // Fieldset: Saúde e Comportamento
                echo '<fieldset class="dps-fieldset">';
                echo '<legend class="dps-fieldset__legend">' . esc_html__( 'Saúde e Comportamento', 'dps-client-portal' ) . '</legend>';
                echo '<p><label>' . esc_html__( 'Vacinas / Saúde', 'dps-client-portal' ) . '<br><textarea name="pet_vaccinations" rows="2" placeholder="Liste vacinas e condições de saúde..." style="font-size: 16px;">' . esc_textarea( $meta_pet['vaccinations'] ) . '</textarea></label></p>';
                echo '<p><label>' . esc_html__( 'Alergias / Restrições', 'dps-client-portal' ) . '<br><textarea name="pet_allergies" rows="2" placeholder="Alergias a alimentos, medicamentos..." style="font-size: 16px;">' . esc_textarea( $meta_pet['allergies'] ) . '</textarea></label></p>';
                echo '<p><label>' . esc_html__( 'Notas de Comportamento', 'dps-client-portal' ) . '<br><textarea name="pet_behavior" rows="2" placeholder="Como o pet costuma se comportar?" style="font-size: 16px;">' . esc_textarea( $meta_pet['behavior'] ) . '</textarea></label></p>';
                echo '</fieldset>';
                
                // Foto do Pet
                echo '<fieldset class="dps-fieldset">';
                echo '<legend class="dps-fieldset__legend">' . esc_html__( 'Foto do Pet', 'dps-client-portal' ) . '</legend>';
                echo '<div class="dps-file-upload">';
                echo '<label class="dps-file-upload__label">';
                echo '<input type="file" name="pet_photo" accept="image/*" class="dps-file-upload__input">';
                echo '<span class="dps-file-upload__text">📷 ' . esc_html__( 'Atualizar foto', 'dps-client-portal' ) . '</span>';
                echo '</label>';
                echo '<div class="dps-file-upload__preview"></div>';
                echo '</div>';
                echo '</fieldset>';
                
                echo '<p><button type="submit" class="button dps-submit-btn">' . esc_html__( 'Salvar Pet', 'dps-client-portal' ) . '</button></p>';
                echo '</form>';
                echo '</div>';
            }
        }
        // Link para avaliação no Google
        echo '<h3>' . esc_html__( 'Avalie nosso serviço', 'dps-client-portal' ) . '</h3>';
        $review_url = 'https://g.page/r/CUPivNuiAGwnEAE/review';
        echo '<p><a class="button" href="' . esc_url( $review_url ) . '" target="_blank">' . esc_html__( 'Deixar uma Avaliação', 'dps-client-portal' ) . '</a></p>';
        echo '</section>';
    }

    /**
     * Garante que a transação pertence ao cliente logado.
     *
     * @param int $trans_id  ID da transação.
     * @param int $client_id ID do cliente.
     * @return bool
     */
    private function transaction_belongs_to_client( $trans_id, $client_id ) {
        global $wpdb;

        $table = $wpdb->prefix . 'dps_transacoes';
        $found = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(1) FROM {$table} WHERE id = %d AND cliente_id = %d", $trans_id, $client_id ) );

        return (bool) $found;
    }

    /**
     * Gera um link de pagamento do Mercado Pago para uma transação específica.  Se
     * ocorrer algum erro, retorna false.
     *
     * @param int $trans_id ID da transação na tabela dps_transacoes.
     * @return string|false URL do checkout ou false em caso de falha.
     */
    private function generate_payment_link_for_transaction( $trans_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';
        $trans = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $trans_id ) );
        if ( ! $trans ) {
            return false;
        }
        // Necessita de token do Mercado Pago
        $token = trim( get_option( 'dps_mercadopago_access_token' ) );
        if ( ! $token ) {
            return false;
        }
        $amount      = (float) $trans->valor;
        $desc        = $trans->descricao ? $trans->descricao : 'Pagamento de serviços';
        $reference   = 'dps_transaction_' . $trans->id;
        $notification_url = home_url( '/?topic=payment' );
        $body = [
            'items' => [
                [
                    'title'       => $desc,
                    'quantity'    => 1,
                    'unit_price'  => $amount,
                    'currency_id' => 'BRL',
                ],
            ],
            'external_reference' => $reference,
            'notification_url'   => $notification_url,
        ];
        $args = [
            'headers' => [
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( $body ),
            'timeout' => 30,
        ];
        // Faz requisição à API do Mercado Pago
        $url = 'https://api.mercadopago.com/checkout/preferences?access_token=' . rawurlencode( $token );
        $response = wp_remote_post( $url, $args );
        if ( is_wp_error( $response ) ) {
            return false;
        }
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( isset( $data['init_point'] ) ) {
            return esc_url_raw( $data['init_point'] );
        }
        return false;
    }

    /**
     * Adiciona a página de administração para gerenciar logins de clientes.
     * Coloca a página como submenu do post type dps_cliente.
     */
    public function register_client_logins_page() {
        add_submenu_page(
            'edit.php?post_type=dps_cliente',
            __( 'Logins de Clientes', 'dps-client-portal' ),
            __( 'Logins', 'dps-client-portal' ),
            'manage_options',
            'dps-client-logins',
            [ $this, 'render_client_logins_page' ]
        );
    }

    /**
     * Renderiza a página de administração dos logins de clientes.
     * Lista todos os clientes e possibilita a redefinição de senha.
     */
    public function render_client_logins_page( $context = 'admin', $base_url = '' ) {
        $context         = in_array( $context, [ 'admin', 'frontend' ], true ) ? $context : 'admin';
        $user_can_manage = current_user_can( 'manage_options' );

        if ( ! $user_can_manage && 'admin' === $context ) {
            return;
        }

        if ( ! $user_can_manage && 'frontend' === $context ) {
            echo '<div class="dps-client-logins dps-client-logins--restricted">';
            echo '<p>' . esc_html__( 'Você não tem permissão para visualizar os logins dos clientes.', 'dps-client-portal' ) . '</p>';
            echo '</div>';
            return;
        }

        $feedback_messages = [];

        if ( isset( $_GET['reset_pass'], $_GET['client_id'] ) ) {
            $cid   = absint( $_GET['client_id'] );
            $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
            if ( $cid && wp_verify_nonce( $nonce, 'dps_reset_pass_' . $cid ) ) {
                $plain = wp_generate_password( 8, false, false );
                $hash  = wp_hash_password( $plain );
                update_post_meta( $cid, 'client_password_hash', $hash );
                set_transient( 'dps_client_pass_' . $cid, $plain, 30 * MINUTE_IN_SECONDS );
                $feedback_messages[] = [
                    'type' => 'success',
                    'text' => esc_html__( 'Senha redefinida com sucesso.', 'dps-client-portal' ),
                ];
            } else {
                $feedback_messages[] = [
                    'type' => 'error',
                    'text' => esc_html__( 'Falha ao validar a solicitação.', 'dps-client-portal' ),
                ];
            }
        } elseif ( isset( $_GET['send_pass'], $_GET['client_id'] ) ) {
            $cid   = absint( $_GET['client_id'] );
            $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
            if ( $cid && wp_verify_nonce( $nonce, 'dps_send_pass_' . $cid ) ) {
                $plain = wp_generate_password( 8, false, false );
                $hash  = wp_hash_password( $plain );
                update_post_meta( $cid, 'client_password_hash', $hash );
                set_transient( 'dps_client_pass_' . $cid, $plain, 30 * MINUTE_IN_SECONDS );
                $phone       = get_post_meta( $cid, 'client_phone', true );
                $clean_phone = preg_replace( '/[^0-9]/', '', (string) $phone );
                $portal_page = get_page_by_title( 'Portal do Cliente' );
                $portal_link = $portal_page ? get_permalink( $portal_page->ID ) : home_url();
                $message     = sprintf(
                    __( 'Olá! Aqui estão seus dados de acesso ao portal Desi Pet Shower:\nTelefone: %s\nSenha: %s\nAcesse: %s', 'dps-client-portal' ),
                    $phone,
                    $plain,
                    $portal_link
                );
                $wa_text = urlencode( $message );
                $wa_link = 'https://wa.me/' . $clean_phone . '?text=' . $wa_text;
                wp_redirect( $wa_link );
                exit;
            } else {
                $feedback_messages[] = [
                    'type' => 'error',
                    'text' => esc_html__( 'Falha ao validar a solicitação.', 'dps-client-portal' ),
                ];
            }
        }

        if ( ! $base_url ) {
            if ( 'admin' === $context ) {
                $base_url = menu_page_url( 'dps-client-logins', false );
            } else {
                $page_id  = get_queried_object_id();
                $base_url = $page_id ? get_permalink( $page_id ) : home_url();
            }
        }

        $base_url = remove_query_arg( [ 'reset_pass', 'send_pass', 'client_id', '_wpnonce' ], $base_url );

        if ( 'frontend' === $context ) {
            $base_url = add_query_arg( 'tab', 'logins', $base_url );
        }

        $search      = isset( $_GET['dps_client_search'] ) ? sanitize_text_field( wp_unslash( $_GET['dps_client_search'] ) ) : '';
        $filter_temp = isset( $_GET['dps_filter_temp'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['dps_filter_temp'] ) );

        $normalized_search        = $search ? strtolower( remove_accents( $search ) ) : '';
        $normalized_search_digits  = $search ? preg_replace( '/\D+/', '', $search ) : '';
        $normalized_search_digits  = is_string( $normalized_search_digits ) ? $normalized_search_digits : '';

        $clients = get_posts(
            [
                'post_type'      => 'dps_cliente',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
            ]
        );

        $total_clients        = count( $clients );
        $total_with_temp      = 0;
        $displayed_clients    = [];
        $displayed_temp_count = 0;

        foreach ( $clients as $client ) {
            $cid   = $client->ID;
            $phone = get_post_meta( $cid, 'client_phone', true );
            $temp  = get_transient( 'dps_client_pass_' . $cid );

            if ( $temp ) {
                $total_with_temp++;
            }

            $matches_search = true;

            if ( $search ) {
                $client_name_normalized = strtolower( remove_accents( $client->post_title ) );
                $matches_search         = false !== strpos( $client_name_normalized, $normalized_search );

                if ( ! $matches_search && $normalized_search_digits ) {
                    $phone_digits  = preg_replace( '/\D+/', '', (string) $phone );
                    $matches_search = false !== strpos( (string) $phone_digits, $normalized_search_digits );
                }
            }

            if ( ! $matches_search ) {
                continue;
            }

            if ( $filter_temp && ! $temp ) {
                continue;
            }

            if ( $temp ) {
                $displayed_temp_count++;
            }

            $displayed_clients[] = [
                'id'        => $cid,
                'name'      => $client->post_title,
                'phone'     => $phone,
                'temp'      => $temp,
                'edit_link' => get_edit_post_link( $cid ),
            ];
        }

        $title_tag = ( 'admin' === $context ) ? 'h1' : 'h2';

        if ( 'admin' === $context ) {
            echo '<div class="wrap">';
        }

        echo '<div class="dps-client-logins">';
        echo '<' . esc_attr( $title_tag ) . ' class="dps-client-logins__title">' . esc_html__( 'Logins de Clientes', 'dps-client-portal' ) . '</' . esc_attr( $title_tag ) . '>';

        if ( $feedback_messages ) {
            echo '<div class="dps-client-logins__feedback">';
            foreach ( $feedback_messages as $feedback ) {
                $notice_class = 'success' === $feedback['type'] ? 'dps-client-logins__notice--success' : 'dps-client-logins__notice--error';
                echo '<div class="dps-client-logins__notice ' . esc_attr( $notice_class ) . '"><p>' . esc_html( $feedback['text'] ) . '</p></div>';
            }
            echo '</div>';
        }

        $summary_items = [];
        $summary_items[] = sprintf(
            /* translators: %s: quantidade de clientes. */
            esc_html__( 'Total de clientes: %s', 'dps-client-portal' ),
            '<strong>' . esc_html( number_format_i18n( $total_clients ) ) . '</strong>'
        );

        $summary_items[] = sprintf(
            /* translators: %s: quantidade de senhas temporárias ativas. */
            esc_html__( 'Com senha temporária ativa: %s', 'dps-client-portal' ),
            '<strong>' . esc_html( number_format_i18n( $total_with_temp ) ) . '</strong>'
        );

        $summary_items[] = sprintf(
            /* translators: %s: quantidade de senhas temporárias exibidas na tabela. */
            esc_html__( 'Senhas temporárias exibidas: %s', 'dps-client-portal' ),
            '<strong>' . esc_html( number_format_i18n( $displayed_temp_count ) ) . '</strong>'
        );

        $summary_items[] = sprintf(
            /* translators: 1: quantidade exibida, 2: quantidade total */
            esc_html__( 'Exibindo %1$s de %2$s clientes', 'dps-client-portal' ),
            '<strong>' . esc_html( number_format_i18n( count( $displayed_clients ) ) ) . '</strong>',
            '<strong>' . esc_html( number_format_i18n( $total_clients ) ) . '</strong>'
        );

        echo '<ul class="dps-client-logins__summary">';
        foreach ( $summary_items as $item ) {
            echo '<li class="dps-client-logins__summary-item">' . wp_kses_post( $item ) . '</li>';
        }
        echo '</ul>';

        echo '<form method="get" action="' . esc_url( $base_url ) . '" class="dps-client-logins__filters">';
        if ( 'frontend' === $context ) {
            echo '<input type="hidden" name="tab" value="logins" />';
        }
        echo '<div class="dps-client-logins__field">';
        echo '<label class="dps-client-logins__label" for="dps-client-logins-search">' . esc_html__( 'Buscar por nome ou telefone', 'dps-client-portal' ) . '</label>';
        echo '<input type="search" id="dps-client-logins-search" name="dps_client_search" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Ex.: Maria ou 11999999999', 'dps-client-portal' ) . '" />';
        echo '</div>';
        echo '<div class="dps-client-logins__field dps-client-logins__field--checkbox">';
        echo '<input type="checkbox" id="dps-client-logins-temp" name="dps_filter_temp" value="1" ' . checked( $filter_temp, true, false ) . ' />';
        echo '<label for="dps-client-logins-temp">' . esc_html__( 'Mostrar apenas clientes com senha temporária ativa', 'dps-client-portal' ) . '</label>';
        echo '</div>';
        echo '<div class="dps-client-logins__actions">';
        echo '<button type="submit" class="button button-primary dps-client-logins__submit">' . esc_html__( 'Aplicar filtros', 'dps-client-portal' ) . '</button>';
        $reset_filters_url = remove_query_arg( [ 'dps_client_search', 'dps_filter_temp' ], $base_url );
        if ( $search || $filter_temp ) {
            echo '<a class="button button-secondary dps-client-logins__reset" href="' . esc_url( $reset_filters_url ) . '">' . esc_html__( 'Limpar filtros', 'dps-client-portal' ) . '</a>';
        }
        echo '</div>';
        echo '</form>';

        echo '<div class="dps-client-logins__table-wrapper">';

        if ( $displayed_clients ) {
            echo '<table class="dps-client-logins__table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th scope="col">' . esc_html__( 'ID', 'dps-client-portal' ) . '</th>';
            echo '<th scope="col">' . esc_html__( 'Cliente', 'dps-client-portal' ) . '</th>';
            echo '<th scope="col">' . esc_html__( 'Telefone', 'dps-client-portal' ) . '</th>';
            echo '<th scope="col">' . esc_html__( 'Senha Temporária', 'dps-client-portal' ) . '</th>';
            echo '<th scope="col">' . esc_html__( 'Ações', 'dps-client-portal' ) . '</th>';
            echo '</tr></thead><tbody>';

            foreach ( $displayed_clients as $client_data ) {
                $cid       = $client_data['id'];
                $temp      = $client_data['temp'];
                $has_temp  = ! empty( $temp );
                $temp_text = $has_temp ? esc_html__( 'Ativa', 'dps-client-portal' ) : esc_html__( 'Indisponível', 'dps-client-portal' );
                $temp_hint = $has_temp
                    ? esc_html__( 'Senha válida por 30 minutos após a geração.', 'dps-client-portal' )
                    : esc_html__( 'Nenhuma senha temporária ativa. Gere uma nova para o cliente.', 'dps-client-portal' );

                $common_args = [];
                if ( $search ) {
                    $common_args['dps_client_search'] = $search;
                }
                if ( $filter_temp ) {
                    $common_args['dps_filter_temp'] = '1';
                }

                $reset_url = wp_nonce_url(
                    add_query_arg(
                        array_merge(
                            $common_args,
                            [
                                'reset_pass' => 1,
                                'client_id'  => $cid,
                            ]
                        ),
                        $base_url
                    ),
                    'dps_reset_pass_' . $cid
                );

                $send_url = wp_nonce_url(
                    add_query_arg(
                        array_merge(
                            $common_args,
                            [
                                'send_pass' => 1,
                                'client_id' => $cid,
                            ]
                        ),
                        $base_url
                    ),
                    'dps_send_pass_' . $cid
                );

                echo '<tr>';
                echo '<td data-title="' . esc_attr__( 'ID', 'dps-client-portal' ) . '"><span class="dps-client-logins__cell-id">#' . esc_html( $cid ) . '</span></td>';
                echo '<td data-title="' . esc_attr__( 'Cliente', 'dps-client-portal' ) . '"><strong>' . esc_html( $client_data['name'] ) . '</strong></td>';
                echo '<td data-title="' . esc_attr__( 'Telefone', 'dps-client-portal' ) . '">' . ( $client_data['phone'] ? esc_html( $client_data['phone'] ) : '&mdash;' ) . '</td>';
                echo '<td data-title="' . esc_attr__( 'Senha Temporária', 'dps-client-portal' ) . '">';
                $badge_class = $has_temp ? 'dps-client-logins__badge--active' : 'dps-client-logins__badge--inactive';
                echo '<span class="dps-client-logins__badge ' . esc_attr( $badge_class ) . '">' . $temp_text . '</span>';
                if ( $has_temp ) {
                    echo '<code class="dps-client-logins__code">' . esc_html( $temp ) . '</code>';
                }
                echo '<small class="dps-client-logins__hint">' . esc_html( $temp_hint ) . '</small>';
                echo '</td>';
                echo '<td data-title="' . esc_attr__( 'Ações', 'dps-client-portal' ) . '" class="dps-client-logins__actions-cell">';
                echo '<a class="button button-secondary dps-client-logins__action" href="' . esc_url( $reset_url ) . '">' . esc_html__( 'Redefinir senha', 'dps-client-portal' ) . '</a>';
                echo '<a class="button button-secondary dps-client-logins__action" href="' . esc_url( $send_url ) . '">' . esc_html__( 'Enviar via WhatsApp', 'dps-client-portal' ) . '</a>';
                if ( $client_data['edit_link'] ) {
                    echo '<a class="button dps-client-logins__action" href="' . esc_url( $client_data['edit_link'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Ver cliente', 'dps-client-portal' ) . '</a>';
                }
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        } else {
            echo '<p class="dps-client-logins__empty">' . esc_html__( 'Nenhum cliente encontrado com os filtros informados.', 'dps-client-portal' ) . '</p>';
        }

        echo '</div>';
        echo '</div>';

        if ( 'admin' === $context ) {
            echo '</div>';
        }
    }

    /**
     * Exibe formulário de login e processa autenticação usando o fluxo padrão do WordPress.
     * Quando autenticado, redireciona para a página contendo o portal.
     *
     * @return string HTML do formulário de login
     */
    public function render_login_shortcode() {
        if ( is_user_logged_in() ) {
            $portal_page = get_page_by_title( 'Portal do Cliente' );

            if ( $portal_page ) {
                wp_safe_redirect( get_permalink( $portal_page->ID ) );
                exit;
            }

            return '';
        }

        $feedback    = '';
        $ip_address  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
        $attempt_key = $ip_address ? 'dps_client_login_attempts_' . md5( $ip_address ) : '';
        $attempts    = $attempt_key ? (int) get_transient( $attempt_key ) : 0;
        $max_attempt = 5;
        $lock_time   = 15 * MINUTE_IN_SECONDS;

        if ( $attempts >= $max_attempt ) {
            $feedback = esc_html__( 'Muitas tentativas de login. Tente novamente em alguns minutos.', 'dps-client-portal' );
        }

        if ( isset( $_POST['dps_client_login_action'] ) && ! $feedback ) {
            if ( ! isset( $_POST['_dps_client_login_nonce'] ) || ! wp_verify_nonce( $_POST['_dps_client_login_nonce'], 'dps_client_login_action' ) ) {
                $feedback = esc_html__( 'Falha na verificação do formulário.', 'dps-client-portal' );
            } else {
                $login    = sanitize_text_field( $_POST['dps_client_login'] ?? '' );
                $password = sanitize_text_field( $_POST['dps_client_password'] ?? '' );

                $creds = [
                    'user_login'    => $login,
                    'user_password' => $password,
                    'remember'      => true,
                ];

                $user = wp_signon( $creds, false );

                if ( is_wp_error( $user ) ) {
                    $feedback = esc_html__( 'Não foi possível acessar. Verifique seus dados e tente novamente.', 'dps-client-portal' );

                    if ( $attempt_key ) {
                        $attempts++;
                        set_transient( $attempt_key, $attempts, $lock_time );
                    }
                } else {
                    if ( $attempt_key ) {
                        delete_transient( $attempt_key );
                    }

                    wp_set_current_user( $user->ID );
                    wp_set_auth_cookie( $user->ID, true );

                    $portal_page = get_page_by_title( 'Portal do Cliente' );

                    if ( $portal_page ) {
                        wp_safe_redirect( get_permalink( $portal_page->ID ) );
                    } else {
                        wp_safe_redirect( home_url() );
                    }

                    exit;
                }
            }
        }

        ob_start();

        if ( $feedback ) {
            echo '<div class="notice notice-error"><p>' . esc_html( $feedback ) . '</p></div>';
        }

        echo '<form method="post" class="dps-client-login-form">';
        wp_nonce_field( 'dps_client_login_action', '_dps_client_login_nonce' );
        echo '<p><label>' . esc_html__( 'Usuário ou e-mail', 'dps-client-portal' ) . '<br />';
        echo '<input type="text" name="dps_client_login" value="" autocomplete="username" required></label></p>';
        echo '<p><label>' . esc_html__( 'Senha', 'dps-client-portal' ) . '<br />';
        echo '<input type="password" name="dps_client_password" value="" autocomplete="current-password" required></label></p>';
        echo '<p><button type="submit" name="dps_client_login_action" class="button button-primary">' . esc_html__( 'Entrar', 'dps-client-portal' ) . '</button></p>';
        echo '</form>';

        return ob_get_clean();
    }
    
    /**
     * Renderiza a aba "Logins" na navegação do front-end.
     */
    public function render_logins_tab( $visitor_only = false ) {
        if ( $visitor_only ) {
            return;
        }
        echo '<li><a href="#" class="dps-tab-link" data-tab="logins">' . esc_html__( 'Logins de Clientes', 'dps-client-portal' ) . '</a></li>';
    }

    /**
     * Renderiza o conteúdo da seção "Logins" no front-end.
     */
    public function render_logins_section( $visitor_only = false ) {
        if ( $visitor_only ) {
            return;
        }
        echo '<div class="dps-section" id="dps-section-logins">';
        $page_id   = get_queried_object_id();
        $page_link = $page_id ? get_permalink( $page_id ) : home_url();
        $page_link = add_query_arg( 'tab', 'logins', $page_link );
        $this->render_client_logins_page( 'frontend', $page_link );
        echo '</div>';
    }
}
endif;
