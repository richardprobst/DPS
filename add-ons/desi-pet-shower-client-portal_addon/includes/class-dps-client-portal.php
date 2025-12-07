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
     * ID do cliente autenticado na requisição atual via token.
     * Usado para disponibilizar autenticação imediatamente sem depender de cookies.
     *
     * @since 2.3.1
     * @var int
     */
    private $current_request_client_id = 0;

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
        // REMOVED: session_start() deprecated - Now using transients + cookies (DPS_Portal_Session_Manager)
        // Migration completed in Phase 1 (commit ab6deda)
        
        // Processa autenticação por token
        add_action( 'init', [ $this, 'handle_token_authentication' ], 5 );
        
        // Processa logout
        add_action( 'init', [ $this, 'handle_logout_request' ], 6 );

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
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

        // Metaboxes e salvamento das mensagens no admin
        add_action( 'add_meta_boxes_dps_portal_message', [ $this, 'add_message_meta_boxes' ] );
        add_action( 'save_post_dps_portal_message', [ $this, 'save_message_meta' ], 10, 3 );

        // Colunas customizadas para listagem de mensagens no admin
        add_filter( 'manage_dps_portal_message_posts_columns', [ $this, 'add_message_columns' ] );
        add_action( 'manage_dps_portal_message_posts_custom_column', [ $this, 'render_message_column' ], 10, 2 );
        add_filter( 'manage_edit-dps_portal_message_sortable_columns', [ $this, 'make_message_columns_sortable' ] );

        // Registra menu administrativo seguindo padrão DPS
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ], 20 );
        
        // Adiciona abas no front-end via shortcode [dps_configuracoes]
        add_action( 'dps_settings_nav_tabs', [ $this, 'render_portal_settings_tab' ], 15, 1 );
        add_action( 'dps_settings_sections', [ $this, 'render_portal_settings_section' ], 15, 1 );
        add_action( 'dps_settings_nav_tabs', [ $this, 'render_logins_tab' ], 20, 1 );
        add_action( 'dps_settings_sections', [ $this, 'render_logins_section' ], 20, 1 );
        
        // Processa salvamento das configurações do portal
        add_action( 'init', [ $this, 'handle_portal_settings_save' ] );
        
        // AJAX handlers para o chat do portal
        add_action( 'wp_ajax_dps_chat_get_messages', [ $this, 'ajax_get_chat_messages' ] );
        add_action( 'wp_ajax_nopriv_dps_chat_get_messages', [ $this, 'ajax_get_chat_messages' ] );
        add_action( 'wp_ajax_dps_chat_send_message', [ $this, 'ajax_send_chat_message' ] );
        add_action( 'wp_ajax_nopriv_dps_chat_send_message', [ $this, 'ajax_send_chat_message' ] );
        add_action( 'wp_ajax_dps_chat_mark_read', [ $this, 'ajax_mark_messages_read' ] );
        add_action( 'wp_ajax_nopriv_dps_chat_mark_read', [ $this, 'ajax_mark_messages_read' ] );
        
        // AJAX handler para notificação de solicitação de acesso (Fase 1.4)
        add_action( 'wp_ajax_nopriv_dps_request_portal_access', [ $this, 'ajax_request_portal_access' ] );
    }

    /**
     * Processa autenticação por token na URL
     */
    public function handle_token_authentication() {
        // Verifica se há um token na URL
        if ( ! isset( $_GET['dps_token'] ) ) {
            return;
        }

        $token_plain = sanitize_text_field( wp_unslash( $_GET['dps_token'] ) );
        
        if ( empty( $token_plain ) ) {
            return;
        }

        // Obtém IP para logging
        $ip_address = $this->get_client_ip();

        // Valida o token
        $token_manager = DPS_Portal_Token_Manager::get_instance();
        $token_data    = $token_manager->validate_token( $token_plain );

        if ( false === $token_data ) {
            // Token inválido - registra tentativa e redireciona
            $this->log_security_event( 'token_invalid', [
                'ip' => $ip_address,
            ] );
            $this->redirect_to_access_screen( 'invalid' );
            return;
        }

        // Token válido - autentica o cliente
        $session_manager = DPS_Portal_Session_Manager::get_instance();
        $authenticated   = $session_manager->authenticate_client( $token_data['client_id'] );

        if ( ! $authenticated ) {
            $this->log_security_event( 'session_auth_failed', [
                'client_id' => $token_data['client_id'],
                'ip'        => $ip_address,
            ] );
            $this->redirect_to_access_screen( 'invalid' );
            return;
        }

        // Marca token como usado apenas para tokens temporários
        if ( ! isset( $token_data['type'] ) || 'permanent' !== $token_data['type'] ) {
            $token_manager->mark_as_used( $token_data['id'] );
        }

        // Registra acesso bem-sucedido
        $this->log_security_event( 'token_auth_success', [
            'client_id' => $token_data['client_id'],
            'ip'        => $ip_address,
        ], DPS_Logger::LEVEL_INFO );

        // Armazena client_id para disponibilizar autenticação imediatamente
        // sem depender de cookies que só estarão disponíveis na próxima requisição
        $this->current_request_client_id = $token_data['client_id'];

        // Envia notificação de acesso ao cliente (Fase 1.3 - Segurança)
        $this->send_access_notification( $token_data['client_id'], $ip_address );

        // NÃO redireciona - permite que a página atual carregue com o cliente autenticado
        // O JavaScript limpará o token da URL por segurança (ver assets/js/client-portal.js)
    }

    /**
     * Processa requisição de logout
     */
    public function handle_logout_request() {
        $session_manager = DPS_Portal_Session_Manager::get_instance();
        $session_manager->handle_logout_request();
    }

    /**
     * Redireciona para a tela de acesso com mensagem de erro
     *
     * @param string $error_type Tipo do erro (invalid, expired, used)
     */
    private function redirect_to_access_screen( $error_type = 'invalid' ) {
        $portal_page_id = dps_get_portal_page_id();
        
        if ( $portal_page_id ) {
            $redirect_url = get_permalink( $portal_page_id );
        } else {
            $redirect_url = home_url( '/portal-cliente/' );
        }

        $redirect_url = add_query_arg( 'token_error', $error_type, $redirect_url );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Retorna o ID do cliente autenticado via sessão ou usuário WP (compatibilidade)
     *
     * @return int
     */
    private function get_authenticated_client_id() {
        // PRIORITY 1: Cliente autenticado na requisição atual via token
        // Isso permite autenticação imediata sem depender de cookies que só estarão
        // disponíveis na próxima requisição
        if ( $this->current_request_client_id > 0 ) {
            return $this->current_request_client_id;
        }

        // PRIORITY 2: Tenta obter do sistema novo de sessão (cookies + transients)
        $session_manager = DPS_Portal_Session_Manager::get_instance();
        $client_id       = $session_manager->get_authenticated_client_id();

        if ( $client_id > 0 ) {
            return $client_id;
        }

        // PRIORITY 3: Fallback para o sistema antigo de usuários WP
        return $this->get_client_id_for_current_user();
    }

    /**
     * Método público para obter o ID do cliente autenticado.
     * Permite que add-ons acessem o cliente logado no portal.
     *
     * @return int ID do cliente autenticado ou 0 se não autenticado
     */
    public function get_current_client_id() {
        return $this->get_authenticated_client_id();
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
     *
     * Suporta autenticação via:
     * - Sistema de tokens/sessão (preferencial para clientes via magic link)
     * - Usuários WordPress logados (retrocompatibilidade)
     */
    public function handle_portal_actions() {
        // Tenta obter cliente do sistema de sessão/token primeiro
        $client_id = $this->get_authenticated_client_id();
        
        // Se não encontrou, não está autenticado em nenhum sistema
        if ( ! $client_id ) {
            return;
        }

        if ( empty( $_POST['dps_client_portal_action'] ) ) {
            return;
        }
        // Verifica nonce de segurança
        $nonce = isset( $_POST['_dps_client_portal_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_dps_client_portal_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'dps_client_portal_action' ) ) {
            return;
        }
        $action    = sanitize_key( wp_unslash( $_POST['dps_client_portal_action'] ) );

        $referer      = wp_get_referer();
        $redirect_url = $referer ? remove_query_arg( 'portal_msg', $referer ) : remove_query_arg( 'portal_msg' );

        // Processa geração de link de pagamento para pendência
        if ( 'pay_transaction' === $action && isset( $_POST['trans_id'] ) ) {
            $trans_id = absint( wp_unslash( $_POST['trans_id'] ) );
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
            $phone = isset( $_POST['client_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['client_phone'] ) ) : '';
            update_post_meta( $client_id, 'client_phone', $phone );

            $address = isset( $_POST['client_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['client_address'] ) ) : '';
            update_post_meta( $client_id, 'client_address', $address );

            $insta = isset( $_POST['client_instagram'] ) ? sanitize_text_field( wp_unslash( $_POST['client_instagram'] ) ) : '';
            update_post_meta( $client_id, 'client_instagram', $insta );

            $fb = isset( $_POST['client_facebook'] ) ? sanitize_text_field( wp_unslash( $_POST['client_facebook'] ) ) : '';
            update_post_meta( $client_id, 'client_facebook', $fb );

            $email = isset( $_POST['client_email'] ) ? sanitize_email( wp_unslash( $_POST['client_email'] ) ) : '';
            if ( $email && is_email( $email ) ) {
                update_post_meta( $client_id, 'client_email', $email );
            }

            // Hook: Após atualizar dados do cliente (Fase 2.3)
            do_action( 'dps_portal_after_update_client', $client_id, $_POST );
            
            $redirect_url = add_query_arg( 'portal_msg', 'updated', $redirect_url );
        } elseif ( 'update_pet' === $action && isset( $_POST['pet_id'] ) ) {
            $pet_id = absint( wp_unslash( $_POST['pet_id'] ) );
            
            // Validação de ownership usando helper centralizado (Fase 1.4)
            if ( ! dps_portal_assert_client_owns_resource( $client_id, $pet_id, 'pet' ) ) {
                // Log de tentativa de acesso indevido já feito pelo helper
                $redirect_url = add_query_arg( 'portal_msg', 'error', $redirect_url );
                wp_safe_redirect( $redirect_url );
                exit;
            }

            $pet_name  = isset( $_POST['pet_name'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_name'] ) ) : '';
            $species   = isset( $_POST['pet_species'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_species'] ) ) : '';
            $breed     = isset( $_POST['pet_breed'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_breed'] ) ) : '';
            $size      = isset( $_POST['pet_size'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_size'] ) ) : '';
            $weight    = isset( $_POST['pet_weight'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_weight'] ) ) : '';
            $coat      = isset( $_POST['pet_coat'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_coat'] ) ) : '';
            $color     = isset( $_POST['pet_color'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_color'] ) ) : '';
            $birth     = isset( $_POST['pet_birth'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_birth'] ) ) : '';
            $sex       = isset( $_POST['pet_sex'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_sex'] ) ) : '';
            $vacc      = isset( $_POST['pet_vaccinations'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pet_vaccinations'] ) ) : '';
            $allergies = isset( $_POST['pet_allergies'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pet_allergies'] ) ) : '';
            $behavior  = isset( $_POST['pet_behavior'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pet_behavior'] ) ) : '';

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
                
                // Valida tipos MIME permitidos para imagens
                $allowed_mimes = [
                    'jpg|jpeg|jpe' => 'image/jpeg',
                    'gif'          => 'image/gif',
                    'png'          => 'image/png',
                    'webp'         => 'image/webp',
                ];
                
                // Extrai extensões permitidas dos MIME types (single source of truth)
                $allowed_exts = [];
                foreach ( array_keys( $allowed_mimes ) as $ext_pattern ) {
                    $exts = explode( '|', $ext_pattern );
                    $allowed_exts = array_merge( $allowed_exts, $exts );
                }
                
                // Verifica extensão do arquivo
                $file_ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
                
                if ( ! in_array( $file_ext, $allowed_exts, true ) ) {
                    // Extensão não permitida, não processa upload
                    $redirect_url = add_query_arg( 'portal_msg', 'invalid_file_type', $redirect_url );
                } else {
                    // Usa limite de upload do WordPress (respeita configuração do servidor)
                    $max_size = min( wp_max_upload_size(), 5 * MB_IN_BYTES );
                    if ( $file['size'] > $max_size ) {
                        $redirect_url = add_query_arg( 'portal_msg', 'file_too_large', $redirect_url );
                    } else {
                        require_once ABSPATH . 'wp-admin/includes/file.php';
                        require_once ABSPATH . 'wp-admin/includes/image.php';

                        $upload = wp_handle_upload( $file, [ 
                            'test_form' => false,
                            'mimes'     => $allowed_mimes,
                        ] );
                        
                        if ( ! isset( $upload['error'] ) && isset( $upload['file'] ) ) {
                            $file_path  = $upload['file'];
                            $file_name  = basename( $file_path );
                            $file_type  = wp_check_filetype( $file_name, $allowed_mimes );
                            
                            // Valida MIME type real do arquivo
                            if ( ! empty( $file_type['type'] ) && 0 === strpos( $file_type['type'], 'image/' ) ) {
                                $attachment = [
                                    'post_title'     => sanitize_file_name( $file_name ),
                                    'post_mime_type' => $file_type['type'],
                                    'post_status'    => 'inherit',
                                ];
                                $attach_id = wp_insert_attachment( $attachment, $file_path );
                                if ( ! is_wp_error( $attach_id ) ) {
                                    $attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
                                    wp_update_attachment_metadata( $attach_id, $attach_data );
                                    update_post_meta( $pet_id, 'pet_photo_id', $attach_id );
                                }
                            }
                        }
                    }
                }
            }

            $redirect_url = add_query_arg( 'portal_msg', 'pet_updated', $redirect_url );
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
     * Enqueue admin assets para a tela de gerenciamento de logins
     */
    public function enqueue_admin_assets( $hook_suffix ) {
        // Verifica se estamos na página de configurações onde a aba de logins pode aparecer
        // ou em páginas que usam o shortcode [dps_base] com tab=logins
        $should_load = false;

        // Carrega se estiver na tela de configurações do DPS
        if ( isset( $_GET['page'] ) && false !== strpos( $_GET['page'], 'dps' ) ) {
            $should_load = true;
        }

        // Carrega se estiver visualizando uma página que pode conter o shortcode
        if ( function_exists( 'get_current_screen' ) ) {
            $screen = get_current_screen();
            if ( $screen && 'post' === $screen->base ) {
                $should_load = true;
            }
        }

        if ( ! $should_load ) {
            return;
        }

        $script_path = trailingslashit( DPS_CLIENT_PORTAL_ADDON_DIR ) . 'assets/js/portal-admin.js';
        $script_url  = trailingslashit( DPS_CLIENT_PORTAL_ADDON_URL ) . 'assets/js/portal-admin.js';
        $script_version = file_exists( $script_path ) ? filemtime( $script_path ) : '1.0.0';

        wp_enqueue_script( 'dps-portal-admin', $script_url, [ 'jquery' ], $script_version, true );

        // Localiza script com nonce para AJAX
        wp_localize_script( 'dps-portal-admin', 'dpsPortalAdmin', [
            'nonce' => wp_create_nonce( 'dps_portal_admin_actions' ),
        ] );

        // CSS para colunas da listagem de mensagens
        $this->enqueue_message_list_styles();
    }

    /**
     * Adiciona estilos CSS para a listagem de mensagens no admin.
     */
    private function enqueue_message_list_styles() {
        $screen = get_current_screen();
        
        // Apenas na listagem de mensagens do portal
        if ( ! $screen || 'edit-dps_portal_message' !== $screen->id ) {
            return;
        }

        $custom_css = '
            .dps-message-sender { font-weight: 500; }
            .dps-message-sender--client { color: #0ea5e9; }
            .dps-message-sender--admin { color: #10b981; }
            .dps-message-status { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; color: #fff; }
            .dps-message-status--open { background-color: #f59e0b; }
            .dps-message-status--answered { background-color: #0ea5e9; }
            .dps-message-status--closed { background-color: #10b981; }
        ';

        wp_add_inline_style( 'wp-admin', $custom_css );
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
            'show_in_menu'       => 'desi-pet-shower', // Agrupa no menu principal DPS
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
     * Adiciona colunas customizadas na listagem de mensagens no admin.
     *
     * @param array $columns Colunas existentes.
     * @return array Colunas modificadas.
     */
    public function add_message_columns( $columns ) {
        $new_columns = [];

        foreach ( $columns as $key => $label ) {
            $new_columns[ $key ] = $label;

            // Insere após o título
            if ( 'title' === $key ) {
                $new_columns['message_client']  = __( 'Cliente', 'dps-client-portal' );
                $new_columns['message_sender']  = __( 'Origem', 'dps-client-portal' );
                $new_columns['message_status']  = __( 'Status', 'dps-client-portal' );
            }
        }

        return $new_columns;
    }

    /**
     * Renderiza conteúdo das colunas customizadas.
     *
     * @param string $column  Nome da coluna.
     * @param int    $post_id ID do post.
     */
    public function render_message_column( $column, $post_id ) {
        switch ( $column ) {
            case 'message_client':
                $client_id = (int) get_post_meta( $post_id, 'message_client_id', true );
                if ( $client_id ) {
                    $client = get_post( $client_id );
                    if ( $client ) {
                        echo '<a href="' . esc_url( get_edit_post_link( $client_id ) ) . '">';
                        echo esc_html( $client->post_title );
                        echo '</a>';
                    } else {
                        echo '<em>' . esc_html__( 'Cliente não encontrado', 'dps-client-portal' ) . '</em>';
                    }
                } else {
                    echo '<em>' . esc_html__( 'Não vinculado', 'dps-client-portal' ) . '</em>';
                }
                break;

            case 'message_sender':
                $sender = get_post_meta( $post_id, 'message_sender', true );
                if ( 'client' === $sender ) {
                    echo '<span class="dps-message-sender dps-message-sender--client">📤 ' . esc_html__( 'Cliente', 'dps-client-portal' ) . '</span>';
                } else {
                    echo '<span class="dps-message-sender dps-message-sender--admin">📥 ' . esc_html__( 'Equipe', 'dps-client-portal' ) . '</span>';
                }
                break;

            case 'message_status':
                $status = get_post_meta( $post_id, 'message_status', true );
                $status_classes = [
                    'open'     => 'dps-message-status--open',
                    'answered' => 'dps-message-status--answered',
                    'closed'   => 'dps-message-status--closed',
                ];
                $status_labels = [
                    'open'     => __( 'Em aberto', 'dps-client-portal' ),
                    'answered' => __( 'Respondida', 'dps-client-portal' ),
                    'closed'   => __( 'Concluída', 'dps-client-portal' ),
                ];

                if ( isset( $status_labels[ $status ] ) ) {
                    $class = isset( $status_classes[ $status ] ) ? $status_classes[ $status ] : '';
                    echo '<span class="dps-message-status ' . esc_attr( $class ) . '">';
                    echo esc_html( $status_labels[ $status ] );
                    echo '</span>';
                } else {
                    echo '—';
                }
                break;
        }
    }

    /**
     * Define quais colunas são ordenáveis.
     *
     * @param array $columns Colunas ordenáveis.
     * @return array Colunas modificadas.
     */
    public function make_message_columns_sortable( $columns ) {
        $columns['message_client'] = 'message_client';
        $columns['message_status'] = 'message_status';
        $columns['message_sender'] = 'message_sender';
        return $columns;
    }

    /**
     * Salva metadados da mensagem no admin.
     *
     * @param int     $post_id ID da mensagem.
     * @param WP_Post $post    Objeto do post.
     * @param bool    $update  Indica se é atualização.
     */
    public function save_message_meta( $post_id, $post, $update ) {
        $nonce = isset( $_POST['dps_portal_message_meta_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_portal_message_meta_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'dps_portal_message_meta' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $client_id = isset( $_POST['dps_portal_message_client'] ) ? absint( wp_unslash( $_POST['dps_portal_message_client'] ) ) : 0;
        if ( $client_id ) {
            update_post_meta( $post_id, 'message_client_id', $client_id );
        } else {
            delete_post_meta( $post_id, 'message_client_id' );
        }

        $sender = isset( $_POST['dps_portal_message_sender'] ) ? sanitize_key( wp_unslash( $_POST['dps_portal_message_sender'] ) ) : 'admin';
        if ( ! in_array( $sender, [ 'admin', 'client' ], true ) ) {
            $sender = 'admin';
        }
        update_post_meta( $post_id, 'message_sender', $sender );

        $status = isset( $_POST['dps_portal_message_status'] ) ? sanitize_key( wp_unslash( $_POST['dps_portal_message_status'] ) ) : 'open';
        if ( ! in_array( $status, [ 'open', 'answered', 'closed' ], true ) ) {
            $status = 'open';
        }
        update_post_meta( $post_id, 'message_status', $status );

        // Notifica o cliente quando o admin cria uma nova mensagem
        // Condições: não é atualização, sender é 'admin' (padrão para novas mensagens via admin),
        // e há um cliente vinculado. Isso garante que apenas mensagens novas da equipe
        // disparem notificação ao cliente.
        if ( ! $update && 'admin' === $sender && $client_id ) {
            $this->notify_client_of_admin_message( $post_id, $client_id, $post );
        }
    }

    /**
     * Notifica o cliente quando a equipe envia uma mensagem via admin.
     *
     * Utiliza a Communications API para enviar e-mail de notificação ao cliente.
     *
     * @param int     $message_id ID da mensagem.
     * @param int     $client_id  ID do cliente.
     * @param WP_Post $post       Objeto da mensagem.
     */
    private function notify_client_of_admin_message( $message_id, $client_id, $post ) {
        // Busca e-mail do cliente
        $client_email = get_post_meta( $client_id, 'client_email', true );
        $client_name  = get_the_title( $client_id );

        if ( ! $client_email || ! is_email( $client_email ) ) {
            return;
        }

        // Monta assunto e corpo do e-mail
        $subject = sprintf(
            __( 'Nova mensagem da equipe DPS by PRObst para você', 'dps-client-portal' )
        );

        $portal_url = dps_get_portal_page_url();

        $body = sprintf(
            __( "Olá, %s!\n\nA equipe DPS by PRObst enviou uma nova mensagem para você.\n\nAssunto: %s\n\nPara ver a mensagem completa, acesse seu portal:\n%s\n\nEquipe DPS by PRObst", 'dps-client-portal' ),
            $client_name,
            $post->post_title,
            $portal_url
        );

        // Envia via Communications API se disponível
        if ( class_exists( 'DPS_Communications_API' ) ) {
            $api = DPS_Communications_API::get_instance();
            $api->send_email( $client_email, $subject, $body, [
                'type'       => 'portal_message_notification',
                'message_id' => $message_id,
                'client_id'  => $client_id,
            ] );
        } else {
            // Fallback direto via wp_mail
            wp_mail( $client_email, $subject, $body );
        }
    }

    /**
     * Renderiza a página do portal para o shortcode. Mostra tela de acesso se não autenticado.
     *
     * @return string Conteúdo HTML renderizado.
     */
    public function render_portal_shortcode() {
        // Hook: Antes de renderizar o portal (Fase 2.3)
        do_action( 'dps_portal_before_render' );
        
        wp_enqueue_style( 'dps-client-portal' );
        wp_enqueue_script( 'dps-client-portal' );
        
        // Verifica autenticação pelo novo sistema
        $client_id = $this->get_authenticated_client_id();
        
        // Hook: Após verificar autenticação (Fase 2.3)
        do_action( 'dps_portal_after_auth_check', $client_id );
        
        // Se não autenticado, exibe tela de acesso
        if ( ! $client_id ) {
            // Hook: Antes de renderizar tela de login (Fase 2.3)
            do_action( 'dps_portal_before_login_screen' );
            
            // Carrega template de acesso
            $template_path = DPS_CLIENT_PORTAL_ADDON_DIR . 'templates/portal-access.php';
            
            if ( file_exists( $template_path ) ) {
                ob_start();
                include $template_path;
                $output = ob_get_clean();
                
                // Filtro: Permite modificar tela de login (Fase 2.3)
                return apply_filters( 'dps_portal_login_screen', $output );
            }
            
            // Fallback se template não existir
            ob_start();
            echo '<div class="dps-client-portal-login">';
            echo '<h3>' . esc_html__( 'Acesso ao Portal do Cliente', 'dps-client-portal' ) . '</h3>';
            echo '<p>' . esc_html__( 'Para acessar o portal, solicite seu link exclusivo à nossa equipe.', 'dps-client-portal' ) . '</p>';
            echo '</div>';
            return ob_get_clean();
        }
        
        // Hook: Cliente autenticado (Fase 2.3)
        do_action( 'dps_portal_client_authenticated', $client_id );
        
        // Localiza script com dados do chat
        wp_localize_script( 'dps-client-portal', 'dpsPortalChat', [
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'dps_portal_chat' ),
            'clientId' => $client_id,
        ] );
        
        ob_start();
        // Filtro de mensagens de retorno
        if ( isset( $_GET['portal_msg'] ) ) {
            $msg = sanitize_text_field( wp_unslash( $_GET['portal_msg'] ) );
            if ( 'updated' === $msg ) {
                echo '<div class="dps-portal-notice dps-portal-notice--success">' . esc_html__( 'Dados atualizados com sucesso.', 'dps-client-portal' ) . '</div>';
            } elseif ( 'error' === $msg ) {
                echo '<div class="dps-portal-notice dps-portal-notice--error">' . esc_html__( 'Ocorreu um erro ao processar sua solicitação.', 'dps-client-portal' ) . '</div>';
            } elseif ( 'message_sent' === $msg ) {
                echo '<div class="dps-portal-notice dps-portal-notice--success">' . esc_html__( 'Mensagem enviada para a equipe. Responderemos em breve!', 'dps-client-portal' ) . '</div>';
            } elseif ( 'message_error' === $msg ) {
                echo '<div class="dps-portal-notice dps-portal-notice--error">' . esc_html__( 'Não foi possível enviar sua mensagem. Verifique o conteúdo e tente novamente.', 'dps-client-portal' ) . '</div>';
            } elseif ( 'invalid_file_type' === $msg ) {
                echo '<div class="dps-portal-notice dps-portal-notice--error">' . esc_html__( 'Tipo de arquivo não permitido. Apenas imagens JPG, PNG, GIF e WebP são aceitas.', 'dps-client-portal' ) . '</div>';
            } elseif ( 'file_too_large' === $msg ) {
                echo '<div class="dps-portal-notice dps-portal-notice--error">' . esc_html__( 'O arquivo é muito grande. O tamanho máximo permitido é 5MB.', 'dps-client-portal' ) . '</div>';
            }
        }
        echo '<div class="dps-client-portal">';
        
        // Header com título e botão de logout
        echo '<div class="dps-portal-header">';
        
        // Saudação personalizada com nome do cliente
        $client_name = get_the_title( $client_id );
        if ( $client_name ) {
            echo '<h1 class="dps-portal-title">';
            echo esc_html( sprintf( __( 'Olá, %s 👋', 'dps-client-portal' ), $client_name ) );
            echo '</h1>';
        } else {
            echo '<h1 class="dps-portal-title">' . esc_html__( 'Portal do Cliente', 'dps-client-portal' ) . '</h1>';
        }
        
        // Botão de logout
        $session_manager = DPS_Portal_Session_Manager::get_instance();
        $logout_url      = $session_manager->get_logout_url();
        echo '<a href="' . esc_url( $logout_url ) . '" class="dps-portal-logout">' . esc_html__( 'Sair', 'dps-client-portal' ) . '</a>';
        echo '</div>';
        
        // Hook para add-ons adicionarem conteúdo no topo do portal (ex: AI Assistant)
        do_action( 'dps_client_portal_before_content', $client_id );
        
        // Breadcrumb simples para contexto
        echo '<nav class="dps-portal-breadcrumb" aria-label="' . esc_attr__( 'Navegação', 'dps-client-portal' ) . '">';
        echo '<span class="dps-portal-breadcrumb__item">' . esc_html__( 'Portal do Cliente', 'dps-client-portal' ) . '</span>';
        echo '<span class="dps-portal-breadcrumb__separator">›</span>';
        echo '<span class="dps-portal-breadcrumb__item dps-portal-breadcrumb__item--active">' . esc_html__( 'Início', 'dps-client-portal' ) . '</span>';
        echo '</nav>';
        
        // Define tabs padrão (Fase 2.3)
        $default_tabs = [
            'inicio' => [
                'icon'  => '🏠',
                'label' => __( 'Início', 'dps-client-portal' ),
                'active' => true,
                'badge' => 0,
            ],
            'agendamentos' => [
                'icon'  => '📅',
                'label' => __( 'Agendamentos', 'dps-client-portal' ),
                'active' => false,
                'badge' => $this->count_upcoming_appointments( $client_id ),
            ],
            'historico-pets' => [
                'icon'  => '🐾',
                'label' => __( 'Histórico dos Pets', 'dps-client-portal' ),
                'active' => false,
                'badge' => 0,
            ],
            'galeria' => [
                'icon'  => '📸',
                'label' => __( 'Galeria', 'dps-client-portal' ),
                'active' => false,
                'badge' => 0,
            ],
            'dados' => [
                'icon'  => '⚙️',
                'label' => __( 'Meus Dados', 'dps-client-portal' ),
                'active' => false,
                'badge' => 0,
            ],
        ];
        
        // Filtro: Permite add-ons modificarem tabs (Fase 2.3)
        $tabs = apply_filters( 'dps_portal_tabs', $default_tabs, $client_id );
        
        // Navegação por Tabs com badges
        echo '<nav class="dps-portal-tabs" role="tablist">';
        foreach ( $tabs as $tab_id => $tab ) {
            $is_active = isset( $tab['active'] ) && $tab['active'];
            $class = 'dps-portal-tabs__link' . ( $is_active ? ' is-active' : '' );
            $badge_count = isset( $tab['badge'] ) ? absint( $tab['badge'] ) : 0;
            
            echo '<div class="dps-portal-tabs__item">';
            echo '<button class="' . esc_attr( $class ) . '" data-tab="' . esc_attr( $tab_id ) . '" role="tab" aria-selected="' . ( $is_active ? 'true' : 'false' ) . '" aria-controls="panel-' . esc_attr( $tab_id ) . '">';
            if ( isset( $tab['icon'] ) ) {
                echo '<span class="dps-portal-tabs__icon">' . esc_html( $tab['icon'] ) . '</span>';
            }
            echo '<span class="dps-portal-tabs__text">' . esc_html( $tab['label'] ) . '</span>';
            
            // Badge de notificação
            if ( $badge_count > 0 ) {
                echo '<span class="dps-portal-tabs__badge" aria-label="' . esc_attr( sprintf( _n( '%d item', '%d itens', $badge_count, 'dps-client-portal' ), $badge_count ) ) . '">';
                echo esc_html( $badge_count > 9 ? '9+' : $badge_count );
                echo '</span>';
            }
            
            echo '</button>';
            echo '</div>';
        }
        echo '</nav>';
        
        // Hook: Antes de renderizar conteúdo das tabs (Fase 2.3)
        do_action( 'dps_portal_before_tab_content', $client_id );
        
        // Container de conteúdo das tabs
        echo '<div class="dps-portal-tab-content">';
        
        // Panel: Início (Próximo agendamento + Pendências + Fidelidade)
        echo '<div id="panel-inicio" class="dps-portal-tab-panel is-active" role="tabpanel" aria-hidden="false">';
        do_action( 'dps_portal_before_inicio_content', $client_id ); // Fase 2.3
        $this->render_next_appointment( $client_id );
        $this->render_recent_requests( $client_id ); // Fase 4: Solicitações recentes
        $this->render_financial_pending( $client_id );
        $this->render_contextual_suggestions( $client_id ); // Fase 2: Sugestões baseadas em histórico
        if ( function_exists( 'dps_loyalty_get_referral_code' ) ) {
            $this->render_referrals_summary( $client_id );
        }
        do_action( 'dps_portal_after_inicio_content', $client_id ); // Fase 2.3
        echo '</div>';
        
        // Panel: Agendamentos (Histórico completo)
        echo '<div id="panel-agendamentos" class="dps-portal-tab-panel" role="tabpanel" aria-hidden="true">';
        do_action( 'dps_portal_before_agendamentos_content', $client_id ); // Fase 2.3
        $this->render_appointment_history( $client_id );
        do_action( 'dps_portal_after_agendamentos_content', $client_id ); // Fase 2.3
        echo '</div>';
        
        // Panel: Histórico dos Pets (Fase 4)
        echo '<div id="panel-historico-pets" class="dps-portal-tab-panel" role="tabpanel" aria-hidden="true">';
        do_action( 'dps_portal_before_pet_history_content', $client_id );
        $this->render_pets_timeline( $client_id );
        do_action( 'dps_portal_after_pet_history_content', $client_id );
        echo '</div>';
        
        // Panel: Galeria
        echo '<div id="panel-galeria" class="dps-portal-tab-panel" role="tabpanel" aria-hidden="true">';
        do_action( 'dps_portal_before_galeria_content', $client_id ); // Fase 2.3
        $this->render_pet_gallery( $client_id );
        do_action( 'dps_portal_after_galeria_content', $client_id ); // Fase 2.3
        echo '</div>';
        
        // Panel: Meus Dados
        echo '<div id="panel-dados" class="dps-portal-tab-panel" role="tabpanel" aria-hidden="true">';
        do_action( 'dps_portal_before_dados_content', $client_id ); // Fase 2.3
        $this->render_update_forms( $client_id );
        do_action( 'dps_portal_after_dados_content', $client_id ); // Fase 2.3
        echo '</div>';
        
        // Hook: Permite add-ons renderizarem panels customizados (Fase 2.3)
        do_action( 'dps_portal_custom_tab_panels', $client_id, $tabs );
        
        echo '</div>'; // .dps-portal-tab-content

        // Hook para add-ons adicionarem conteúdo ao final do portal (ex: AI Assistant)
        do_action( 'dps_client_portal_after_content', $client_id );

        echo '</div>'; // .dps-client-portal
        
        // Widget de Chat flutuante
        $this->render_chat_widget( $client_id );
        
        return ob_get_clean();
    }

    /**
     * Renderiza o widget de chat flutuante.
     *
     * @since 2.3.0
     * @param int $client_id ID do cliente autenticado.
     */
    private function render_chat_widget( $client_id ) {
        // Conta mensagens não lidas
        $unread_count = $this->get_unread_messages_count( $client_id );
        
        echo '<div class="dps-chat-widget" data-client-id="' . esc_attr( $client_id ) . '">';
        
        // Botão toggle
        echo '<button class="dps-chat-toggle" aria-label="' . esc_attr__( 'Abrir chat', 'dps-client-portal' ) . '">';
        echo '<span class="dps-chat-toggle__icon">💬</span>';
        if ( $unread_count > 0 ) {
            echo '<span class="dps-chat-badge">' . esc_html( $unread_count > 99 ? '99+' : $unread_count ) . '</span>';
        } else {
            echo '<span class="dps-chat-badge"></span>';
        }
        echo '</button>';
        
        // Janela do chat
        echo '<div class="dps-chat-window" aria-hidden="true">';
        
        // Header
        echo '<div class="dps-chat-header">';
        echo '<div class="dps-chat-header__info">';
        echo '<div class="dps-chat-header__avatar">🐾</div>';
        echo '<div>';
        echo '<h4 class="dps-chat-header__title">' . esc_html__( 'Chat DPS', 'dps-client-portal' ) . '</h4>';
        echo '<div class="dps-chat-header__status">' . esc_html__( 'Online', 'dps-client-portal' ) . '</div>';
        echo '</div>';
        echo '</div>';
        echo '<button class="dps-chat-header__close" aria-label="' . esc_attr__( 'Fechar chat', 'dps-client-portal' ) . '">✕</button>';
        echo '</div>';
        
        // Área de mensagens
        echo '<div class="dps-chat-messages">';
        echo '<div class="dps-chat-loading"><div class="dps-chat-loading__spinner"></div></div>';
        echo '</div>';
        
        // Input de mensagem
        echo '<div class="dps-chat-input">';
        echo '<form class="dps-chat-input__form">';
        echo '<input type="text" class="dps-chat-input__field" placeholder="' . esc_attr__( 'Digite sua mensagem...', 'dps-client-portal' ) . '" maxlength="1000">';
        echo '<button type="submit" class="dps-chat-input__send" aria-label="' . esc_attr__( 'Enviar', 'dps-client-portal' ) . '">📤</button>';
        echo '</form>';
        echo '</div>';
        
        echo '</div>'; // .dps-chat-window
        echo '</div>'; // .dps-chat-widget
    }

    /**
     * Conta mensagens não lidas do admin para o cliente.
     *
     * @since 2.3.0
     * @param int $client_id ID do cliente.
     * @return int Quantidade de mensagens não lidas.
     */
    private function get_unread_messages_count( $client_id ) {
        $messages = get_posts( [
            'post_type'      => 'dps_portal_message',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'   => 'message_client_id',
                    'value' => $client_id,
                ],
                [
                    'key'   => 'message_sender',
                    'value' => 'admin',
                ],
                [
                    'key'     => 'client_read_at',
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ] );

        return count( $messages );
    }

    /**
     * Conta agendamentos futuros do cliente (para badge da tab)
     *
     * @param int $client_id ID do cliente.
     * @return int Número de agendamentos futuros.
     * @since 2.4.0
     */
    private function count_upcoming_appointments( $client_id ) {
        $today = current_time( 'Y-m-d' );
        $args  = [
            'post_type'      => 'dps_agendamento',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => 'appointment_client_id',
                    'value'   => $client_id,
                    'compare' => '=',
                ],
                [
                    'key'     => 'appointment_date',
                    'value'   => $today,
                    'compare' => '>=',
                    'type'    => 'DATE',
                ],
            ],
        ];
        
        $appointments = get_posts( $args );
        $count = 0;
        
        // Filtra por status válidos
        foreach ( $appointments as $appt_id ) {
            $status = get_post_meta( $appt_id, 'appointment_status', true );
            if ( ! in_array( $status, [ 'finalizado', 'finalizado e pago', 'finalizado_pago', 'cancelado' ], true ) ) {
                $count++;
            }
        }
        
        return $count;
    }

    /**
     * Conta pendências financeiras do cliente (para badge da tab)
     *
     * @param int $client_id ID do cliente.
     * @return int Número de pendências.
     * @since 2.4.0
     */
    private function count_financial_pending( $client_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';
        
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE cliente_id = %d AND status IN ('em_aberto', 'pendente')",
            $client_id
        ) );
        
        return absint( $count );
    }

    /**
     * Renderiza seção do próximo agendamento.
     *
     * @param int $client_id ID do cliente.
     */
    private function render_next_appointment( $client_id ) {
        echo '<section id="proximos" class="dps-portal-section dps-portal-next">';
        echo '<h2>' . esc_html__( '📅 Seu Próximo Horário', 'dps-client-portal' ) . '</h2>';
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
            // Gera link para agendar via WhatsApp usando helper centralizado
            if ( class_exists( 'DPS_WhatsApp_Helper' ) ) {
                $whatsapp_message = __( 'Olá! Gostaria de agendar um serviço.', 'dps-client-portal' );
                $whatsapp_url = DPS_WhatsApp_Helper::get_link_to_team( $whatsapp_message );
            } else {
                // Fallback
                $whatsapp_number = get_option( 'dps_whatsapp_number', '5515991606299' );
                if ( class_exists( 'DPS_Phone_Helper' ) ) {
                    $whatsapp_number = DPS_Phone_Helper::format_for_whatsapp( $whatsapp_number );
                }
                $whatsapp_text = urlencode( 'Olá! Gostaria de agendar um serviço.' );
                $whatsapp_url = 'https://wa.me/' . $whatsapp_number . '?text=' . $whatsapp_text;
            }
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
        echo '<h2>' . esc_html__( '💳 Pagamentos Pendentes', 'dps-client-portal' ) . '</h2>';
        
        if ( $pendings ) {
            // Calcula total de pendências
            $total = 0;
            foreach ( $pendings as $trans ) {
                $total += (float) $trans->valor;
            }
            
            // Card de resumo de pendências com destaque
            echo '<div class="dps-financial-summary">';
            echo '<div class="dps-financial-summary__icon">⚠️</div>';
            echo '<div class="dps-financial-summary__content">';
            echo '<div class="dps-financial-summary__title">' . esc_html( sprintf( 
                _n( '%d Pendência', '%d Pendências', count( $pendings ), 'dps-client-portal' ),
                count( $pendings )
            ) ) . '</div>';
            echo '<div class="dps-financial-summary__amount">R$ ' . esc_html( number_format( $total, 2, ',', '.' ) ) . '</div>';
            echo '</div>';
            echo '<div class="dps-financial-summary__action">';
            echo '<button class="button button-primary dps-btn-toggle-details" data-target="financial-details">';
            echo esc_html__( 'Ver Detalhes', 'dps-client-portal' );
            echo '</button>';
            echo '</div>';
            echo '</div>';
            
            // Tabela de detalhes (inicialmente oculta em mobile)
            echo '<div id="financial-details" class="dps-financial-details">';
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
                echo '<button type="submit" class="button button-secondary dps-btn-pay">' . esc_html__( 'Pagar Agora', 'dps-client-portal' ) . '</button>';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '</div>'; // .dps-financial-details
        } else {
            // Estado "em dia" positivo
            echo '<div class="dps-financial-summary dps-financial-summary--positive">';
            echo '<div class="dps-financial-summary__icon">😊</div>';
            echo '<div class="dps-financial-summary__content">';
            echo '<div class="dps-financial-summary__title">' . esc_html__( 'Tudo em Dia!', 'dps-client-portal' ) . '</div>';
            echo '<div class="dps-financial-summary__message">' . esc_html__( 'Você não tem pagamentos pendentes', 'dps-client-portal' ) . '</div>';
            echo '</div>';
            echo '</div>';
        }
        echo '</section>';
    }

    /**
     * Renderiza sugestões contextuais baseadas no histórico do cliente.
     * Fase 2: Personalização da experiência
     *
     * @param int $client_id ID do cliente.
     * @since 2.4.0
     */
    private function render_contextual_suggestions( $client_id ) {
        // Busca pets do cliente
        $pets = get_posts( [
            'post_type'      => 'dps_pet',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_key'       => 'owner_id',
            'meta_value'     => $client_id,
            'fields'         => 'ids',
        ] );
        
        if ( empty( $pets ) ) {
            return; // Sem pets, sem sugestões
        }
        
        // Busca último agendamento de cada pet
        $suggestions = [];
        $today = current_time( 'Y-m-d' );
        
        foreach ( $pets as $pet_id ) {
            $last_appointment = get_posts( [
                'post_type'      => 'dps_agendamento',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'meta_query'     => [
                    'relation' => 'AND',
                    [
                        'key'     => 'appointment_client_id',
                        'value'   => $client_id,
                        'compare' => '=',
                    ],
                    [
                        'key'     => 'appointment_pet_id',
                        'value'   => $pet_id,
                        'compare' => '=',
                    ],
                    [
                        'key'     => 'appointment_status',
                        'value'   => ['finalizado', 'finalizado e pago', 'finalizado_pago'],
                        'compare' => 'IN',
                    ],
                ],
                'orderby'        => 'meta_value',
                'meta_key'       => 'appointment_date',
                'order'          => 'DESC',
            ] );
            
            if ( ! empty( $last_appointment ) ) {
                $appt_id = $last_appointment[0]->ID;
                $appt_date = get_post_meta( $appt_id, 'appointment_date', true );
                $services = get_post_meta( $appt_id, 'appointment_services', true );
                
                if ( $appt_date ) {
                    $days_since = floor( ( strtotime( $today ) - strtotime( $appt_date ) ) / DAY_IN_SECONDS );
                    
                    // Sugestão se faz mais de 30 dias
                    if ( $days_since >= 30 ) {
                        $pet_name = get_the_title( $pet_id );
                        $service_name = is_array( $services ) && ! empty( $services ) ? $services[0] : __( 'banho', 'dps-client-portal' );
                        
                        $suggestions[] = [
                            'pet_name'     => $pet_name,
                            'days_since'   => $days_since,
                            'service_name' => $service_name,
                        ];
                    }
                }
            }
        }
        
        // Renderiza sugestões se houver
        if ( ! empty( $suggestions ) ) {
            echo '<section class="dps-portal-section dps-portal-suggestions">';
            echo '<h2>💡 ' . esc_html__( 'Sugestões para Você', 'dps-client-portal' ) . '</h2>';
            
            foreach ( $suggestions as $suggestion ) {
                echo '<div class="dps-suggestion-card">';
                echo '<div class="dps-suggestion-card__icon">🐾</div>';
                echo '<div class="dps-suggestion-card__content">';
                echo '<p class="dps-suggestion-card__message">';
                echo esc_html( sprintf(
                    _n( 
                        'Já faz %d dia desde o último %s do %s.',
                        'Já faz %d dias desde o último %s do %s.',
                        $suggestion['days_since'],
                        'dps-client-portal'
                    ),
                    $suggestion['days_since'],
                    $suggestion['service_name'],
                    $suggestion['pet_name']
                ) );
                echo '</p>';
                echo '<p class="dps-suggestion-card__cta">';
                
                // Link para agendar via WhatsApp
                if ( class_exists( 'DPS_WhatsApp_Helper' ) ) {
                    $message = sprintf( __( 'Olá! Gostaria de agendar %s para o %s.', 'dps-client-portal' ), $suggestion['service_name'], $suggestion['pet_name'] );
                    $whatsapp_url = DPS_WhatsApp_Helper::get_link_to_team( $message );
                } else {
                    $whatsapp_number = get_option( 'dps_whatsapp_number', '5515991606299' );
                    if ( class_exists( 'DPS_Phone_Helper' ) ) {
                        $whatsapp_number = DPS_Phone_Helper::format_for_whatsapp( $whatsapp_number );
                    }
                    $message_text = urlencode( sprintf( 'Olá! Gostaria de agendar %s para o %s.', $suggestion['service_name'], $suggestion['pet_name'] ) );
                    $whatsapp_url = 'https://wa.me/' . $whatsapp_number . '?text=' . $message_text;
                }
                
                echo '<a href="' . esc_url( $whatsapp_url ) . '" target="_blank" class="dps-suggestion-card__button">';
                echo '📅 ' . esc_html__( 'Agendar Agora', 'dps-client-portal' );
                echo '</a>';
                echo '</p>';
                echo '</div>';
                echo '</div>';
            }
            
            echo '</section>';
        }
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
        
        // OTIMIZAÇÃO: Pre-load meta cache para evitar N+1 queries
        if ( $appointments ) {
            $appt_ids = wp_list_pluck( $appointments, 'ID' );
            update_meta_cache( 'post', $appt_ids );
            
            // OTIMIZAÇÃO: Batch load de pets para evitar queries individuais
            $pet_ids = [];
            foreach ( $appointments as $appt ) {
                $pet_id = get_post_meta( $appt->ID, 'appointment_pet_id', true );
                if ( $pet_id ) {
                    $pet_ids[] = $pet_id;
                }
            }
            
            $pets_cache = [];
            if ( ! empty( $pet_ids ) ) {
                $pets = get_posts( [
                    'post_type'      => 'dps_pet',
                    'post__in'       => array_unique( $pet_ids ),
                    'posts_per_page' => -1,
                    'fields'         => 'ids', // Apenas IDs, mais eficiente
                ] );
                
                // Cria cache de nomes de pets
                foreach ( $pets as $pet_id ) {
                    $pets_cache[ $pet_id ] = get_the_title( $pet_id );
                }
            }
        }
        
        echo '<section id="historico" class="dps-portal-section dps-portal-history">';
        echo '<h2>' . esc_html__( '📋 Histórico de Serviços', 'dps-client-portal' ) . '</h2>';
        if ( $appointments ) {
            echo '<table class="dps-table"><thead><tr>';
            echo '<th>' . esc_html__( 'Data', 'dps-client-portal' ) . '</th>';
            echo '<th>' . esc_html__( 'Horário', 'dps-client-portal' ) . '</th>';
            echo '<th>' . esc_html__( 'Pet', 'dps-client-portal' ) . '</th>';
            echo '<th>' . esc_html__( 'Serviços', 'dps-client-portal' ) . '</th>';
            echo '<th>' . esc_html__( 'Status', 'dps-client-portal' ) . '</th>';
            echo '<th>' . esc_html__( 'Ações', 'dps-client-portal' ) . '</th>'; // Fase 2.8
            echo '</tr></thead><tbody>';
            foreach ( $appointments as $appt ) {
                // Meta já em cache, sem queries adicionais
                $date     = get_post_meta( $appt->ID, 'appointment_date', true );
                $time     = get_post_meta( $appt->ID, 'appointment_time', true );
                $status   = get_post_meta( $appt->ID, 'appointment_status', true );
                $pet_id   = get_post_meta( $appt->ID, 'appointment_pet_id', true );
                $services = get_post_meta( $appt->ID, 'appointment_services', true );
                
                // Usa cache de pets ao invés de get_the_title()
                $pet_name = isset( $pets_cache[ $pet_id ] ) ? $pets_cache[ $pet_id ] : '';
                
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
                
                // Ações - Adicionar ao Calendário (Fase 2.8)
                echo '<td data-label="' . esc_attr__( 'Ações', 'dps-client-portal' ) . '" class="dps-table-actions">';
                
                // Link para download .ics
                $ics_url = wp_nonce_url(
                    add_query_arg( 'dps_download_ics', $appt->ID, home_url( '/' ) ),
                    'dps_download_ics_' . $appt->ID
                );
                
                echo '<a href="' . esc_url( $ics_url ) . '" class="dps-btn dps-btn--small" title="' . esc_attr__( 'Baixar arquivo .ics', 'dps-client-portal' ) . '">';
                echo '📅 ' . esc_html__( '.ics', 'dps-client-portal' );
                echo '</a> ';
                
                // Link para Google Calendar
                if ( class_exists( 'DPS_Calendar_Helper' ) ) {
                    $google_url = DPS_Calendar_Helper::get_google_calendar_url( $appt->ID );
                    if ( $google_url ) {
                        echo '<a href="' . esc_url( $google_url ) . '" class="dps-btn dps-btn--small" target="_blank" rel="noopener" title="' . esc_attr__( 'Adicionar ao Google Calendar', 'dps-client-portal' ) . '">';
                        echo '📆 ' . esc_html__( 'Google', 'dps-client-portal' );
                        echo '</a>';
                    }
                }
                
                echo '</td>';
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
        
        // OTIMIZAÇÃO: Pre-load meta cache para evitar N+1 queries
        if ( $pets ) {
            $pet_ids = wp_list_pluck( $pets, 'ID' );
            update_meta_cache( 'post', $pet_ids );
        }
        
        echo '<section id="galeria" class="dps-portal-section dps-portal-gallery">';
        echo '<h2>' . esc_html__( 'Galeria de Fotos', 'dps-client-portal' ) . '</h2>';
        if ( $pets ) {
            echo '<div class="dps-portal-gallery-grid">';
            foreach ( $pets as $pet ) {
                // Meta já em cache, sem queries adicionais
                $photo_id = get_post_meta( $pet->ID, 'pet_photo_id', true );
                $pet_name = $pet->post_title;
                echo '<div class="dps-portal-photo-item">';
                echo '<h4>' . esc_html( $pet_name ) . '</h4>';
                if ( $photo_id ) {
                    $img_url = wp_get_attachment_image_url( $photo_id, 'medium' );
                    if ( $img_url ) {
                        // Link para compartilhar via WhatsApp usando helper centralizado
                        $share_message = sprintf( __( 'Olha que fofo estou após o banho/tosa no DPS by PRObst! %s', 'dps-client-portal' ), $img_url );
                        if ( class_exists( 'DPS_WhatsApp_Helper' ) ) {
                            $wa_link = DPS_WhatsApp_Helper::get_share_link( $share_message );
                        } else {
                            // Fallback
                            $wa_text = urlencode( $share_message );
                            $wa_link = 'https://wa.me/?text=' . $wa_text;
                        }
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
                    : esc_html__( 'Equipe DPS by PRObst', 'dps-client-portal' );
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

        // Usa a API centralizada para obter a URL de indicação
        $share_url = '';
        if ( class_exists( 'DPS_Loyalty_API' ) ) {
            $share_url = DPS_Loyalty_API::get_referral_url( $client_id );
        } else {
            // Fallback se a API não estiver disponível
            $page_id  = (int) get_option( 'dps_registration_page_id', 0 );
            $base_url = $page_id ? get_permalink( $page_id ) : site_url( '/cadastro/' );
            $share_url = add_query_arg( 'ref', rawurlencode( $code ), $base_url );
        }

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
            $formatted_credit = class_exists( 'DPS_Money_Helper' ) ? DPS_Money_Helper::format_to_brazilian( $credit ) : $credit;
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
     * Registra menu administrativo seguindo padrão DPS
     * Todos os add-ons devem usar prioridade 20 e registrar sob 'desi-pet-shower'
     */
    public function register_admin_menu() {
        // Submenu: Portal do Cliente - Configurações
        add_submenu_page(
            'desi-pet-shower',
            __( 'Portal do Cliente - Configurações', 'dps-client-portal' ),
            __( 'Portal do Cliente', 'dps-client-portal' ),
            'manage_options',
            'dps-client-portal-settings',
            [ $this, 'render_portal_settings_admin_page' ]
        );
        
        // Submenu: Logins de Clientes
        add_submenu_page(
            'desi-pet-shower',
            __( 'Portal do Cliente - Logins', 'dps-client-portal' ),
            __( 'Logins de Clientes', 'dps-client-portal' ),
            'manage_options',
            'dps-client-logins',
            [ $this, 'render_client_logins_admin_page' ]
        );
    }
    
    /**
     * Renderiza a página administrativa de configurações do portal
     */
    public function render_portal_settings_admin_page() {
        echo '<div class="wrap">';
        $base_url = menu_page_url( 'dps-client-portal-settings', false );
        $this->render_portal_settings_page( $base_url );
        echo '</div>';
    }
    
    /**
     * Renderiza a página administrativa de logins
     */
    public function render_client_logins_admin_page() {
        $this->render_client_logins_page( 'admin', '' );
    }

    /**
     * Renderiza a página de administração dos logins de clientes.
     * Usa o novo sistema baseado em tokens de acesso (magic links).
     *
     * @param string $context  Contexto de renderização ('admin' ou 'frontend')
     * @param string $base_url URL base para ações
     */
    public function render_client_logins_page( $context = 'admin', $base_url = '' ) {
        $context         = in_array( $context, [ 'admin', 'frontend' ], true ) ? $context : 'admin';
        $user_can_manage = current_user_can( 'manage_options' );

        if ( ! $user_can_manage ) {
            echo '<div class="dps-portal-logins-restricted">';
            echo '<p>' . esc_html__( 'Você não tem permissão para visualizar os logins dos clientes.', 'dps-client-portal' ) . '</p>';
            echo '</div>';
            return;
        }

        // Determina URL base
        if ( ! $base_url ) {
            if ( 'admin' === $context ) {
                $base_url = menu_page_url( 'dps-client-logins', false );
            } else {
                $page_id  = get_queried_object_id();
                $base_url = $page_id ? get_permalink( $page_id ) : home_url();
            }
        }

        if ( 'frontend' === $context ) {
            $base_url = add_query_arg( 'tab', 'logins', $base_url );
        }

        // Processa feedback
        $feedback_messages = [];

        if ( isset( $_GET['dps_token_generated'], $_GET['client_id'] ) ) {
            $client_name = get_the_title( absint( $_GET['client_id'] ) );
            $feedback_messages[] = [
                'type' => 'success',
                'text' => sprintf( __( 'Link de acesso gerado com sucesso para %s.', 'dps-client-portal' ), $client_name ),
            ];
        }

        if ( isset( $_GET['dps_tokens_revoked'], $_GET['client_id'] ) ) {
            $count = absint( $_GET['dps_tokens_revoked'] );
            $feedback_messages[] = [
                'type' => 'success',
                'text' => sprintf( _n( '%d link foi revogado.', '%d links foram revogados.', $count, 'dps-client-portal' ), $count ),
            ];
        }

        // Busca clientes
        $search = isset( $_GET['dps_search'] ) ? sanitize_text_field( wp_unslash( $_GET['dps_search'] ) ) : '';

        $query_args = [
            'post_type'      => 'dps_cliente',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];

        if ( $search ) {
            $query_args['s'] = $search;
        }

        $clients_query = new WP_Query( $query_args );
        $token_manager = DPS_Portal_Token_Manager::get_instance();
        $clients       = [];

        if ( $clients_query->have_posts() ) {
            while ( $clients_query->have_posts() ) {
                $clients_query->the_post();
                $client_id = get_the_ID();

                $clients[] = [
                    'id'          => $client_id,
                    'name'        => get_the_title(),
                    'phone'       => get_post_meta( $client_id, 'client_phone', true ),
                    'email'       => get_post_meta( $client_id, 'client_email', true ),
                    'token_stats' => $token_manager->get_client_stats( $client_id ),
                ];
            }
            wp_reset_postdata();
        }

        // Carrega template
        $template_path = DPS_CLIENT_PORTAL_ADDON_DIR . 'templates/admin-logins.php';
        
        if ( file_exists( $template_path ) ) {
            include $template_path;
        } else {
            echo '<p>' . esc_html__( 'Template não encontrado.', 'dps-client-portal' ) . '</p>';
        }
    }

    /**
     * Renderiza o shortcode de login do portal do cliente.
     *
     * @return string Conteúdo HTML renderizado.
     */
    /**
     * Renderiza shortcode de login (DEPRECIADO)
     * 
     * ESTE SHORTCODE FOI DESCONTINUADO EM FAVOR DO LOGIN EXCLUSIVO POR TOKEN (MAGIC LINK)
     * 
     * O login por usuário/senha do Cliente Portal foi removido por questões de segurança
     * e usabilidade. O sistema agora utiliza EXCLUSIVAMENTE autenticação por token via
     * link único (magic link) enviado por WhatsApp ou e-mail.
     * 
     * Para obter acesso ao portal, o cliente deve:
     * 1. Acessar a página do portal
     * 2. Clicar em "Quero acesso ao meu portal"
     * 3. Aguardar a equipe enviar o link de acesso
     * 4. Clicar no link recebido para autenticar
     * 
     * @deprecated 2.4.0 Use apenas autenticação por token via [dps_client_portal]
     * @return string Mensagem de depreciação
     */
    public function render_login_shortcode() {
        // Log de uso do shortcode depreciado
        $this->log_security_event( 'deprecated_login_shortcode_used', [
            'ip'  => $this->get_client_ip(),
            'url' => isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
        ] );
        
        ob_start();
        ?>
        <div class="dps-deprecated-notice" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; margin: 20px 0;">
            <h3 style="margin-top: 0; color: #856404;">
                <?php esc_html_e( 'Método de Login Descontinuado', 'dps-client-portal' ); ?>
            </h3>
            <p style="margin-bottom: 15px;">
                <?php esc_html_e( 'O login por usuário e senha não está mais disponível no Portal do Cliente.', 'dps-client-portal' ); ?>
            </p>
            <p style="margin-bottom: 15px;">
                <strong><?php esc_html_e( 'Como acessar o portal agora:', 'dps-client-portal' ); ?></strong>
            </p>
            <ol style="margin-left: 20px;">
                <li><?php esc_html_e( 'Acesse a página do Portal do Cliente', 'dps-client-portal' ); ?></li>
                <li><?php esc_html_e( 'Clique no botão "Quero acesso ao meu portal"', 'dps-client-portal' ); ?></li>
                <li><?php esc_html_e( 'Aguarde nossa equipe enviar seu link exclusivo de acesso', 'dps-client-portal' ); ?></li>
                <li><?php esc_html_e( 'Clique no link recebido para acessar automaticamente', 'dps-client-portal' ); ?></li>
            </ol>
            <?php 
            $portal_url = dps_get_portal_page_url();
            if ( $portal_url ) : 
            ?>
                <p style="margin-top: 20px;">
                    <a href="<?php echo esc_url( $portal_url ); ?>" class="button button-primary">
                        <?php esc_html_e( 'Ir para o Portal do Cliente', 'dps-client-portal' ); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Renderiza a aba "Portal" na navegação do front-end.
     */
    public function render_portal_settings_tab( $visitor_only = false ) {
        if ( $visitor_only ) {
            return;
        }
        echo '<li><a href="#" class="dps-tab-link" data-tab="portal">' . esc_html__( 'Portal', 'dps-client-portal' ) . '</a></li>';
    }

    /**
     * Renderiza o conteúdo da seção "Portal" no front-end.
     */
    public function render_portal_settings_section( $visitor_only = false ) {
        if ( $visitor_only ) {
            return;
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        echo '<div class="dps-section" id="dps-section-portal">';
        $page_id   = get_queried_object_id();
        $page_link = $page_id ? get_permalink( $page_id ) : home_url();
        $page_link = add_query_arg( 'tab', 'portal', $page_link );
        $this->render_portal_settings_page( $page_link );
        echo '</div>';
    }
    
    /**
     * Renderiza a página de configurações do portal
     * 
     * @param string $base_url URL base da página
     */
    public function render_portal_settings_page( $base_url = '' ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            echo '<div class="dps-portal-settings-restricted">';
            echo '<p>' . esc_html__( 'Você não tem permissão para visualizar as configurações do portal.', 'dps-client-portal' ) . '</p>';
            echo '</div>';
            return;
        }
        
        // Feedback de salvamento
        $feedback_messages = [];
        $saved_param = isset( $_GET['dps_portal_settings_saved'] ) ? sanitize_text_field( wp_unslash( $_GET['dps_portal_settings_saved'] ) ) : '';
        if ( $saved_param === '1' ) {
            $feedback_messages[] = [
                'type' => 'success',
                'text' => __( 'Configurações do portal salvas com sucesso!', 'dps-client-portal' ),
            ];
        }
        
        // Obtém configurações atuais
        $portal_page_id = (int) get_option( 'dps_portal_page_id', 0 );
        $portal_url     = dps_get_portal_page_url();
        
        // Busca todas as páginas publicadas
        $pages = get_pages( [
            'post_status' => 'publish',
            'sort_column' => 'post_title',
        ] );
        
        // Template
        $template_path = DPS_CLIENT_PORTAL_ADDON_DIR . 'templates/portal-settings.php';
        
        if ( file_exists( $template_path ) ) {
            include $template_path;
        } else {
            echo '<p>' . esc_html__( 'Template de configurações não encontrado.', 'dps-client-portal' ) . '</p>';
        }
    }
    
    /**
     * Processa salvamento das configurações do portal.
     *
     * Verifica nonce, capability e salva as opções do portal.
     *
     * @since 2.1.0
     */
    public function handle_portal_settings_save() {
        if ( ! isset( $_POST['dps_save_portal_settings'] ) ) {
            return;
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        $nonce = isset( $_POST['_dps_portal_settings_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_dps_portal_settings_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'dps_save_portal_settings' ) ) {
            return;
        }
        
        // Salva ID da página do portal
        if ( isset( $_POST['dps_portal_page_id'] ) ) {
            $page_id = absint( wp_unslash( $_POST['dps_portal_page_id'] ) );
            update_option( 'dps_portal_page_id', $page_id );
        }
        
        // Salva configuração de notificação de acesso (Fase 1.3)
        $access_notification = isset( $_POST['dps_portal_access_notification_enabled'] ) ? 1 : 0;
        update_option( 'dps_portal_access_notification_enabled', $access_notification );
        
        // Redireciona com mensagem de sucesso
        $redirect_url = add_query_arg( [
            'tab'                       => 'portal',
            'dps_portal_settings_saved' => '1',
        ], wp_get_referer() );
        
        wp_safe_redirect( $redirect_url );
        exit;
    }
    
    /**
     * Renderiza a aba "Logins" na navegação do front-end.
     *
     * @since 2.1.0
     *
     * @param bool $visitor_only Se true, não renderiza para visitantes.
     */
    public function render_logins_tab( $visitor_only = false ) {
        if ( $visitor_only ) {
            return;
        }
        echo '<li><a href="#" class="dps-tab-link" data-tab="logins">' . esc_html__( 'Logins de Clientes', 'dps-client-portal' ) . '</a></li>';
    }

    /**
     * Renderiza o conteúdo da seção "Logins" no front-end.
     *
     * @since 2.1.0
     *
     * @param bool $visitor_only Se true, não renderiza para visitantes.
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

    /**
     * Obtém o endereço IP do cliente de forma segura.
     *
     * @since 2.2.0
     *
     * @return string Endereço IP sanitizado ou 'unknown' se não disponível.
     */
    private function get_client_ip() {
        if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
            return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }
        return 'unknown';
    }

    /**
     * Registra evento de segurança no sistema de logs.
     *
     * Utiliza DPS_Logger para registrar eventos relacionados a autenticação,
     * tentativas de login e outras ações de segurança do portal.
     *
     * IMPORTANTE: Nunca registra senhas ou tokens completos para evitar
     * exposição de dados sensíveis. Usa allowlist de campos seguros.
     *
     * @since 2.2.0
     *
     * @param string $event   Nome do evento (ex: 'login_failed', 'token_auth_success').
     * @param array  $context Dados do contexto (IP, client_id, etc.). Não incluir senhas.
     * @param string $level   Nível do log (padrão: warning). Use DPS_Logger::LEVEL_* constants.
     */
    private function log_security_event( $event, $context = [], $level = null ) {
        // Verifica se DPS_Logger existe (plugin base ativo)
        if ( ! class_exists( 'DPS_Logger' ) ) {
            return;
        }

        // Define nível padrão como warning para eventos de segurança
        if ( null === $level ) {
            $level = DPS_Logger::LEVEL_WARNING;
        }

        // Allowlist de campos seguros para evitar exposição de dados sensíveis
        $allowed_fields = [ 'ip', 'client_id', 'user_id', 'attempts', 'event_type', 'timestamp' ];
        $safe_context   = array_intersect_key( $context, array_flip( $allowed_fields ) );

        $message = sprintf( 'Portal security event: %s', $event );
        DPS_Logger::log( $level, $message, $safe_context, 'client-portal' );
    }

    /**
     * Envia notificação de acesso ao portal para o cliente
     * 
     * Notifica o cliente via e-mail quando ocorre um acesso bem-sucedido ao portal.
     * Aumenta a segurança e transparência, permitindo que o cliente identifique
     * acessos não autorizados.
     * 
     * CONFIGURAÇÃO:
     * A notificação pode ser ativada/desativada via option 'dps_portal_access_notification_enabled'
     * 
     * CONTEÚDO DO E-MAIL:
     * - Data e hora do acesso
     * - Endereço IP (primeiros 3 octetos ofuscados para privacidade)
     * - Mensagem de segurança: "Se não foi você, entre em contato imediatamente"
     * 
     * @param int    $client_id  ID do cliente que acessou o portal
     * @param string $ip_address IP do acesso
     * @return void
     * 
     * @since 2.4.0
     */
    private function send_access_notification( $client_id, $ip_address ) {
        // Verifica se notificações estão habilitadas
        $notifications_enabled = get_option( 'dps_portal_access_notification_enabled', false );
        
        // Permite filtro para controle por add-ons
        $notifications_enabled = apply_filters( 'dps_portal_access_notification_enabled', $notifications_enabled, $client_id );
        
        if ( ! $notifications_enabled ) {
            return;
        }
        
        // Obtém dados do cliente
        $client_email = get_post_meta( $client_id, 'client_email', true );
        $client_name  = get_the_title( $client_id );
        
        if ( empty( $client_email ) || ! is_email( $client_email ) ) {
            DPS_Logger::log( 'warning', 'Portal: notificação de acesso não enviada - e-mail inválido', [
                'client_id' => $client_id,
            ] );
            return;
        }
        
        // Formata data/hora do acesso
        $access_time = current_time( 'mysql' );
        $access_date = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $access_time ) );
        
        // Ofusca IP parcialmente para privacidade (mantém apenas primeiros 2 octetos)
        // Nota: Implementação atual suporta apenas IPv4
        $ip_parts      = explode( '.', $ip_address );
        $ip_obfuscated = isset( $ip_parts[0], $ip_parts[1] ) && count( $ip_parts ) === 4
            ? $ip_parts[0] . '.' . $ip_parts[1] . '.***' 
            : 'desconhecido';
        
        // Monta o corpo do e-mail
        $subject = sprintf(
            /* translators: %s: Nome do site */
            __( 'Acesso ao Portal - %s', 'dps-client-portal' ),
            get_bloginfo( 'name' )
        );
        
        $body = sprintf(
            /* translators: 1: Nome do cliente, 2: Data/hora do acesso, 3: IP ofuscado */
            __( 'Olá %1$s,

Detectamos um acesso ao seu Portal do Cliente.

Data/Hora: %2$s
IP: %3$s

Se você reconhece este acesso, pode ignorar esta mensagem. Ela é apenas uma notificação de segurança para mantê-lo informado.

⚠️ IMPORTANTE: Se você NÃO realizou este acesso, entre em contato com nossa equipe IMEDIATAMENTE. Pode ser que alguém tenha obtido seu link de acesso indevidamente.

Atenciosamente,
Equipe %4$s', 'dps-client-portal' ),
            $client_name,
            $access_date,
            $ip_obfuscated,
            get_bloginfo( 'name' )
        );
        
        // Permite filtrar assunto e corpo
        $subject = apply_filters( 'dps_portal_access_notification_subject', $subject, $client_id );
        $body    = apply_filters( 'dps_portal_access_notification_body', $body, $client_id, $access_date, $ip_obfuscated );
        
        // Tenta usar Communications API se disponível
        if ( class_exists( 'DPS_Communications_API' ) ) {
            $comm_api = DPS_Communications_API::get_instance();
            $sent     = $comm_api->send_email( 
                $client_email, 
                $subject, 
                $body, 
                [
                    'client_id' => $client_id,
                    'type'      => 'portal_access_notification',
                ] 
            );
        } else {
            // Fallback para wp_mail
            $sent = wp_mail( $client_email, $subject, $body );
        }
        
        if ( ! $sent ) {
            DPS_Logger::log( 'error', 'Portal: falha ao enviar notificação de acesso', [
                'client_id' => $client_id,
                'email'     => $client_email,
            ] );
        }
        
        // Hook para extensões (ex: enviar também via WhatsApp)
        do_action( 'dps_portal_access_notification_sent', $client_id, $sent, $access_date, $ip_address );
    }

    /**
     * Valida requisições AJAX do chat.
     *
     * Verifica nonce, client_id e autenticação. Retorna o client_id validado
     * ou termina a requisição com erro JSON.
     *
     * @since 2.3.0
     * @return int Client ID validado e autenticado.
     */
    private function validate_chat_request() {
        // Verifica nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'dps_portal_chat' ) ) {
            wp_send_json_error( [ 'message' => __( 'Nonce inválido', 'dps-client-portal' ) ] );
        }

        $client_id = isset( $_POST['client_id'] ) ? absint( $_POST['client_id'] ) : 0;
        
        // Verifica se o cliente é válido
        if ( ! $client_id || 'dps_cliente' !== get_post_type( $client_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Cliente inválido', 'dps-client-portal' ) ] );
        }

        // Verifica autenticação
        $authenticated_id = $this->get_authenticated_client_id();
        if ( $authenticated_id !== $client_id ) {
            wp_send_json_error( [ 'message' => __( 'Não autorizado', 'dps-client-portal' ) ] );
        }

        return $client_id;
    }

    /**
     * AJAX handler para obter mensagens do chat.
     *
     * @since 2.3.0
     */
    public function ajax_get_chat_messages() {
        $client_id = $this->validate_chat_request();

        // Busca mensagens do cliente
        $messages = get_posts( [
            'post_type'      => 'dps_portal_message',
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'orderby'        => 'date',
            'order'          => 'ASC',
            'meta_key'       => 'message_client_id',
            'meta_value'     => $client_id,
        ] );

        $formatted_messages = [];
        foreach ( $messages as $msg ) {
            $formatted_messages[] = [
                'id'      => $msg->ID,
                'content' => wp_strip_all_tags( $msg->post_content ),
                'sender'  => get_post_meta( $msg->ID, 'message_sender', true ),
                'time'    => get_post_time( get_option( 'date_format' ) . ' H:i', false, $msg, true ),
                'status'  => get_post_meta( $msg->ID, 'message_status', true ),
            ];
        }

        // Conta não lidas
        $unread_count = $this->get_unread_messages_count( $client_id );

        wp_send_json_success( [
            'messages'     => $formatted_messages,
            'unread_count' => $unread_count,
        ] );
    }

    /**
     * AJAX handler para enviar mensagem do chat.
     *
     * @since 2.3.0
     */
    public function ajax_send_chat_message() {
        $client_id = $this->validate_chat_request();
        
        $message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

        if ( empty( $message ) ) {
            wp_send_json_error( [ 'message' => __( 'Mensagem vazia', 'dps-client-portal' ) ] );
        }

        if ( strlen( $message ) > 1000 ) {
            wp_send_json_error( [ 'message' => __( 'Mensagem muito longa', 'dps-client-portal' ) ] );
        }

        // Rate limiting simples (máximo 10 mensagens por minuto)
        $rate_key  = 'dps_chat_rate_' . $client_id;
        $rate_data = get_transient( $rate_key );
        if ( $rate_data && $rate_data >= 10 ) {
            wp_send_json_error( [ 'message' => __( 'Muitas mensagens. Aguarde um momento.', 'dps-client-portal' ) ] );
        }
        set_transient( $rate_key, ( $rate_data ? $rate_data + 1 : 1 ), 60 );

        // Cria a mensagem
        $client_name = get_the_title( $client_id );
        $title       = sprintf( __( 'Mensagem via Chat - %s', 'dps-client-portal' ), $client_name );

        $message_id = wp_insert_post( [
            'post_type'    => 'dps_portal_message',
            'post_status'  => 'publish',
            'post_title'   => wp_strip_all_tags( $title ),
            'post_content' => $message,
        ] );

        if ( is_wp_error( $message_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Erro ao salvar mensagem', 'dps-client-portal' ) ] );
        }

        update_post_meta( $message_id, 'message_client_id', $client_id );
        update_post_meta( $message_id, 'message_sender', 'client' );
        update_post_meta( $message_id, 'message_status', 'open' );

        // Notifica admin via Communications API se disponível
        if ( class_exists( 'DPS_Communications_API' ) ) {
            $api = DPS_Communications_API::get_instance();
            $api->send_message_from_client( $client_id, $message, [
                'message_id' => $message_id,
                'source'     => 'chat',
            ] );
        }

        wp_send_json_success( [
            'message_id' => $message_id,
            'time'       => current_time( 'd/m H:i' ),
        ] );
    }

    /**
     * AJAX handler para marcar mensagens como lidas.
     *
     * @since 2.3.0
     */
    public function ajax_mark_messages_read() {
        $client_id = $this->validate_chat_request();

        // Busca mensagens não lidas do admin
        $messages = get_posts( [
            'post_type'      => 'dps_portal_message',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'   => 'message_client_id',
                    'value' => $client_id,
                ],
                [
                    'key'   => 'message_sender',
                    'value' => 'admin',
                ],
                [
                    'key'     => 'client_read_at',
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ] );

        // Marca como lidas
        $now = current_time( 'mysql' );
        foreach ( $messages as $msg_id ) {
            update_post_meta( $msg_id, 'client_read_at', $now );
        }

        wp_send_json_success( [ 'marked' => count( $messages ) ] );
    }

    /**
     * AJAX handler para notificação de solicitação de acesso ao portal (Fase 1.4)
     * 
     * Quando cliente clica em "Quero acesso ao meu portal", registra solicitação
     * e notifica admin via Communications API se disponível.
     * 
     * Rate limiting: 5 solicitações por hora por IP
     * 
     * @since 2.4.0
     */
    public function ajax_request_portal_access() {
        // Valida IP para rate limiting
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
        $rate_key = 'dps_access_request_' . md5( $ip );
        
        // Verifica se já solicitou recentemente (rate limiting: 5 solicitações por hora)
        $request_count = get_transient( $rate_key );
        if ( false === $request_count ) {
            $request_count = 0;
        }
        
        if ( $request_count >= 5 ) {
            wp_send_json_error( [ 
                'message' => __( 'Você já solicitou acesso várias vezes. Aguarde um momento antes de solicitar novamente.', 'dps-client-portal' ) 
            ] );
        }
        
        // Incrementa contador
        set_transient( $rate_key, $request_count + 1, HOUR_IN_SECONDS );
        
        // Captura dados do cliente (opcional, pode vir do formulário)
        $client_name = isset( $_POST['client_name'] ) ? sanitize_text_field( wp_unslash( $_POST['client_name'] ) ) : '';
        $client_phone = isset( $_POST['client_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['client_phone'] ) ) : '';
        
        // Tenta encontrar cliente por telefone se fornecido
        $client_id = 0;
        if ( $client_phone ) {
            $clients = get_posts( [
                'post_type'      => 'dps_cliente',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_query'     => [
                    [
                        'key'     => 'client_phone',
                        'value'   => $client_phone,
                        'compare' => 'LIKE',
                    ],
                ],
            ] );
            
            if ( ! empty( $clients ) ) {
                $client_id = $clients[0];
                if ( empty( $client_name ) ) {
                    $client_name = get_the_title( $client_id );
                }
            }
        }
        
        // Registra solicitação em log
        if ( function_exists( 'dps_log' ) ) {
            dps_log( 'Portal access requested', [
                'client_id'   => $client_id,
                'client_name' => $client_name,
                'client_phone' => $client_phone,
                'ip'          => $ip,
            ], 'info', 'client-portal' );
        }
        
        // Notifica admin via Communications API se disponível
        if ( class_exists( 'DPS_Communications_API' ) && method_exists( 'DPS_Communications_API', 'notify_admin_portal_access_requested' ) ) {
            DPS_Communications_API::notify_admin_portal_access_requested( $client_id, $client_name, $client_phone );
        } else {
            // Fallback: cria uma mensagem no portal para o admin ver
            $message_title = sprintf(
                __( 'Solicitação de Acesso ao Portal - %s', 'dps-client-portal' ),
                $client_name ? $client_name : __( 'Cliente não identificado', 'dps-client-portal' )
            );
            
            $message_content = sprintf(
                __( "Nova solicitação de acesso ao portal:\n\nNome: %s\nTelefone: %s\nIP: %s\nData: %s", 'dps-client-portal' ),
                $client_name ? $client_name : __( 'Não informado', 'dps-client-portal' ),
                $client_phone ? $client_phone : __( 'Não informado', 'dps-client-portal' ),
                $ip,
                current_time( 'mysql' )
            );
            
            // Cria post de notificação para admin
            wp_insert_post( [
                'post_type'    => 'dps_portal_message',
                'post_title'   => $message_title,
                'post_content' => $message_content,
                'post_status'  => 'publish',
                'meta_input'   => [
                    'message_client_id' => $client_id ? $client_id : 0,
                    'message_sender'    => 'system',
                    'message_type'      => 'access_request',
                ],
            ] );
        }
        
        wp_send_json_success( [ 
            'message' => __( 'Sua solicitação foi registrada! Nossa equipe entrará em contato em breve.', 'dps-client-portal' ) 
        ] );
    }

    /**
     * Renderiza seção de solicitações recentes.
     * Fase 4: Dashboard de Solicitações
     *
     * @since 2.4.0
     * @param int $client_id ID do cliente.
     */
    private function render_recent_requests( $client_id ) {
        $renderer = DPS_Portal_Renderer::get_instance();
        $renderer->render_recent_requests( $client_id );
    }

    /**
     * Renderiza timeline de serviços por pet.
     * Fase 4: Timeline de Serviços
     *
     * @since 2.4.0
     * @param int $client_id ID do cliente.
     */
    private function render_pets_timeline( $client_id ) {
        $pet_repo = DPS_Pet_Repository::get_instance();
        $pets     = $pet_repo->get_pets_by_client( $client_id );

        if ( empty( $pets ) ) {
            echo '<section class="dps-portal-section">';
            echo '<div class="dps-empty-state">';
            echo '<div class="dps-empty-state__icon">🐾</div>';
            echo '<div class="dps-empty-state__message">' . esc_html__( 'Nenhum pet cadastrado ainda.', 'dps-client-portal' ) . '</div>';
            echo '</div>';
            echo '</section>';
            return;
        }

        $renderer = DPS_Portal_Renderer::get_instance();

        foreach ( $pets as $pet ) {
            $renderer->render_pet_service_timeline( $pet->ID, $client_id, 10 );
        }
    }
}
endif;
