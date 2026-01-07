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
     * Status que indicam agendamento finalizado ou cancelado.
     * Usado para separar próximos agendamentos do histórico.
     *
     * @since 3.1.0
     * @var array
     */
    private const COMPLETED_STATUSES = [
        'finalizado',
        'finalizado e pago',
        'finalizado_pago',
        'cancelado',
    ];

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
        // IMPORTANTE: Se o hook 'init' já está em execução ou já passou, precisamos chamar
        // o método diretamente, pois add_action() não executará callbacks para hooks que
        // já foram processados neste request.
        if ( did_action( 'init' ) ) {
            // Hook 'init' já executou - chamar diretamente
            $this->handle_token_authentication();
            $this->handle_logout_request();
            $this->handle_portal_actions();
            $this->handle_portal_settings_save();
        } else {
            // Hook 'init' ainda não executou - registrar normalmente
            add_action( 'init', [ $this, 'handle_token_authentication' ], 5 );
            add_action( 'init', [ $this, 'handle_logout_request' ], 6 );
            add_action( 'init', [ $this, 'handle_portal_actions' ] );
            add_action( 'init', [ $this, 'handle_portal_settings_save' ] );
        }

        // Cria login para novo cliente ao salvar post do tipo dps_cliente
        add_action( 'save_post_dps_cliente', [ $this, 'maybe_create_login_for_client' ], 10, 3 );

        // Adiciona shortcode para o portal
        add_shortcode( 'dps_client_portal', [ $this, 'render_portal_shortcode' ] );
        // Adiciona shortcode para o formulário de login
        add_shortcode( 'dps_client_login', [ $this, 'render_login_shortcode' ] );

        // Registra tipos de dados e recursos do portal
        // NOTA: CPT dps_portal_message agora registrado por DPS_Portal_Admin (evita conflito de menu)
        // add_action( 'init', [ $this, 'register_message_post_type' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

        // Metaboxes e salvamento das mensagens no admin
        add_action( 'add_meta_boxes_dps_portal_message', [ $this, 'add_message_meta_boxes' ] );
        add_action( 'save_post_dps_portal_message', [ $this, 'save_message_meta' ], 10, 3 );

        // Colunas customizadas para listagem de mensagens no admin
        add_filter( 'manage_dps_portal_message_posts_columns', [ $this, 'add_message_columns' ] );
        add_action( 'manage_dps_portal_message_posts_custom_column', [ $this, 'render_message_column' ], 10, 2 );
        add_filter( 'manage_edit-dps_portal_message_sortable_columns', [ $this, 'make_message_columns_sortable' ] );

        // Registra menu administrativo delegado para DPS_Portal_Admin (evita duplicação)
        // add_action( 'admin_menu', [ $this, 'register_admin_menu' ], 20 ); // REMOVIDO - já está em class-dps-portal-admin.php
        
        // Adiciona abas no front-end via shortcode [dps_configuracoes]
        add_action( 'dps_settings_nav_tabs', [ $this, 'render_portal_settings_tab' ], 15, 1 );
        add_action( 'dps_settings_sections', [ $this, 'render_portal_settings_section' ], 15, 1 );
        add_action( 'dps_settings_nav_tabs', [ $this, 'render_logins_tab' ], 20, 1 );
        add_action( 'dps_settings_sections', [ $this, 'render_logins_section' ], 20, 1 );
        
        // AJAX handlers para o chat do portal
        add_action( 'wp_ajax_dps_chat_get_messages', [ $this, 'ajax_get_chat_messages' ] );
        add_action( 'wp_ajax_nopriv_dps_chat_get_messages', [ $this, 'ajax_get_chat_messages' ] );
        add_action( 'wp_ajax_dps_chat_send_message', [ $this, 'ajax_send_chat_message' ] );
        add_action( 'wp_ajax_nopriv_dps_chat_send_message', [ $this, 'ajax_send_chat_message' ] );
        add_action( 'wp_ajax_dps_chat_mark_read', [ $this, 'ajax_mark_messages_read' ] );
        add_action( 'wp_ajax_nopriv_dps_chat_mark_read', [ $this, 'ajax_mark_messages_read' ] );
        
        // AJAX handler para notificação de solicitação de acesso (Fase 1.4)
        add_action( 'wp_ajax_dps_request_portal_access', [ $this, 'ajax_request_portal_access' ] );
        add_action( 'wp_ajax_nopriv_dps_request_portal_access', [ $this, 'ajax_request_portal_access' ] );
        
        // AJAX handler para auto-envio de link de acesso por email
        add_action( 'wp_ajax_dps_request_access_link_by_email', [ $this, 'ajax_request_access_link_by_email' ] );
        add_action( 'wp_ajax_nopriv_dps_request_access_link_by_email', [ $this, 'ajax_request_access_link_by_email' ] );

        // AJAX handler para export PDF do histórico do pet (Funcionalidade 3)
        add_action( 'wp_ajax_dps_export_pet_history_pdf', [ $this, 'ajax_export_pet_history_pdf' ] );
        add_action( 'wp_ajax_nopriv_dps_export_pet_history_pdf', [ $this, 'ajax_export_pet_history_pdf' ] );
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
        
        // Registra acesso no histórico para auditoria
        $user_agent = '';
        if ( isset( $_SERVER['HTTP_USER_AGENT'] ) && is_string( $_SERVER['HTTP_USER_AGENT'] ) ) {
            $user_agent = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
        }
        $token_manager->log_access( $token_data['client_id'], $token_data['id'], $ip_address, $user_agent );

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
        
        $redirect_url = '';
        if ( $portal_page_id ) {
            $permalink = get_permalink( $portal_page_id );
            if ( $permalink && is_string( $permalink ) ) {
                $redirect_url = $permalink;
            }
        }
        
        if ( empty( $redirect_url ) ) {
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
            // Redireciona com mensagem de erro quando a validação de sessão falha.
            // Isso pode ocorrer se a sessão expirou ou se há problemas de cache.
            $referer      = wp_get_referer();
            $redirect_url = $referer ? remove_query_arg( 'portal_msg', $referer ) : remove_query_arg( 'portal_msg' );
            $redirect_url = add_query_arg( 'portal_msg', 'session_expired', $redirect_url );
            wp_safe_redirect( $redirect_url );
            exit;
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
            // Passa apenas dados sanitizados para evitar vazamento de dados sensíveis
            $sanitized_data = [
                'phone'     => $phone,
                'address'   => $address,
                'instagram' => $insta,
                'facebook'  => $fb,
                'email'     => $email,
            ];
            do_action( 'dps_portal_after_update_client', $client_id, $sanitized_data );
            
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
            
            // Fase 4 - continuação: Preferências do pet
            $behavior_notes    = isset( $_POST['pet_behavior_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pet_behavior_notes'] ) ) : '';
            $grooming_pref     = isset( $_POST['pet_grooming_preference'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pet_grooming_preference'] ) ) : '';
            $product_notes     = isset( $_POST['pet_product_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pet_product_notes'] ) ) : '';

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
            
            // Salva preferências (Fase 4 - continuação)
            update_post_meta( $pet_id, 'pet_behavior_notes', $behavior_notes );
            update_post_meta( $pet_id, 'pet_grooming_preference', $grooming_pref );
            update_post_meta( $pet_id, 'pet_product_notes', $product_notes );

            if ( ! empty( $_FILES['pet_photo']['name'] ) ) {
                $file = $_FILES['pet_photo'];
                
                // Valida que o upload foi bem-sucedido
                if ( ! isset( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) || UPLOAD_ERR_OK !== $file['error'] ) {
                    $redirect_url = add_query_arg( 'portal_msg', 'upload_error', $redirect_url );
                } else {
                
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
                        // Validação adicional de MIME type real usando getimagesize()
                        // Isso previne uploads de arquivos maliciosos com extensão de imagem
                        // Nota: Validação de is_uploaded_file() já foi feita acima
                        // @ para suprimir warnings em arquivos corrompidos (evita vazamento de info do servidor)
                        $image_info = @getimagesize( $file['tmp_name'] );
                        if ( false === $image_info || ! isset( $image_info['mime'] ) || ! in_array( $image_info['mime'], $allowed_mimes, true ) ) {
                            $redirect_url = add_query_arg( 'portal_msg', 'invalid_file_type', $redirect_url );
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
        } elseif ( 'update_client_preferences' === $action ) {
            // Fase 4 - continuação: Handler de preferências do cliente
            $contact_pref = isset( $_POST['contact_preference'] ) ? sanitize_key( wp_unslash( $_POST['contact_preference'] ) ) : '';
            $period_pref  = isset( $_POST['period_preference'] ) ? sanitize_key( wp_unslash( $_POST['period_preference'] ) ) : '';
            
            update_post_meta( $client_id, 'client_contact_preference', $contact_pref );
            update_post_meta( $client_id, 'client_period_preference', $period_pref );
            
            // Hook: Após atualizar preferências
            do_action( 'dps_portal_after_update_preferences', $client_id, $_POST );
            
            $redirect_url = add_query_arg( 'portal_msg', 'preferences_updated', $redirect_url );
        } elseif ( 'update_pet_preferences' === $action && isset( $_POST['pet_id'] ) ) {
            // Fase 4 - continuação: Handler de preferências do pet
            $pet_id = absint( wp_unslash( $_POST['pet_id'] ) );
            
            // Validação de ownership
            if ( ! dps_portal_assert_client_owns_resource( $client_id, $pet_id, 'pet' ) ) {
                $redirect_url = add_query_arg( 'portal_msg', 'error', $redirect_url );
                wp_safe_redirect( $redirect_url );
                exit;
            }
            
            $behavior_notes = isset( $_POST['pet_behavior_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pet_behavior_notes'] ) ) : '';
            $grooming_pref  = isset( $_POST['pet_grooming_preference'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pet_grooming_preference'] ) ) : '';
            $product_notes  = isset( $_POST['pet_product_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pet_product_notes'] ) ) : '';
            
            update_post_meta( $pet_id, 'pet_behavior_notes', $behavior_notes );
            update_post_meta( $pet_id, 'pet_grooming_preference', $grooming_pref );
            update_post_meta( $pet_id, 'pet_product_notes', $product_notes );
            
            // Hook: Após atualizar preferências do pet
            do_action( 'dps_portal_after_update_pet_preferences', $pet_id, $client_id, $_POST );
            
            $redirect_url = add_query_arg( 'portal_msg', 'pet_preferences_updated', $redirect_url );
        } elseif ( 'submit_internal_review' === $action ) {
            // Handler para avaliação interna rápida
            $rating  = isset( $_POST['review_rating'] ) ? absint( wp_unslash( $_POST['review_rating'] ) ) : 0;
            $comment = isset( $_POST['review_comment'] ) ? sanitize_textarea_field( wp_unslash( $_POST['review_comment'] ) ) : '';

            // Valida rating (1-5)
            if ( $rating < 1 || $rating > 5 ) {
                $redirect_url = add_query_arg( 'portal_msg', 'review_invalid', $redirect_url );
            } elseif ( $this->has_client_reviewed( $client_id ) ) {
                // Cliente já avaliou
                $redirect_url = add_query_arg( 'portal_msg', 'review_already', $redirect_url );
            } else {
                // Cria a avaliação como CPT dps_groomer_review (se existir)
                if ( post_type_exists( 'dps_groomer_review' ) ) {
                    $client_name = get_the_title( $client_id );
                    $review_title = sprintf(
                        /* translators: %s: client name */
                        __( 'Avaliação de %s', 'dps-client-portal' ),
                        $client_name
                    );

                    $review_id = wp_insert_post( [
                        'post_type'    => 'dps_groomer_review',
                        'post_status'  => 'publish',
                        'post_title'   => wp_strip_all_tags( $review_title ),
                        'post_content' => $comment,
                    ] );

                    if ( ! is_wp_error( $review_id ) && $review_id > 0 ) {
                        update_post_meta( $review_id, '_dps_review_rating', $rating );
                        update_post_meta( $review_id, '_dps_review_name', $client_name );
                        update_post_meta( $review_id, '_dps_review_client_id', $client_id );
                        update_post_meta( $review_id, '_dps_review_source', 'portal' );

                        // Invalida cache de satisfação
                        delete_transient( 'dps_satisfaction_rate' );

                        // Hook: Após enviar avaliação interna
                        do_action( 'dps_portal_after_internal_review', $review_id, $client_id, $rating, $comment );

                        $redirect_url = add_query_arg( 'portal_msg', 'review_submitted', $redirect_url );
                    } else {
                        $redirect_url = add_query_arg( 'portal_msg', 'review_error', $redirect_url );
                    }
                } else {
                    // CPT não existe, salva como mensagem de fallback
                    $redirect_url = add_query_arg( 'portal_msg', 'review_submitted', $redirect_url );
                }
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
        if ( isset( $_GET['page'] ) && false !== strpos( (string) $_GET['page'], 'dps' ) ) {
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
            __( 'Nova mensagem da equipe desi.pet by PRObst para você', 'dps-client-portal' )
        );

        $portal_url = dps_get_portal_page_url();

        $body = sprintf(
            __( "Olá, %s!\n\nA equipe desi.pet by PRObst enviou uma nova mensagem para você.\n\nAssunto: %s\n\nPara ver a mensagem completa, acesse seu portal:\n%s\n\nEquipe desi.pet by PRObst", 'dps-client-portal' ),
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
        
        // Localiza script com dados do chat e appointment requests (Fase 4)
        wp_localize_script( 'dps-client-portal', 'dpsPortal', [
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'chatNonce' => wp_create_nonce( 'dps_portal_chat' ),
            'requestNonce' => wp_create_nonce( 'dps_portal_appointment_request' ),
            'exportPdfNonce' => wp_create_nonce( 'dps_portal_export_pdf' ),
            'clientId' => $client_id,
            'loyalty' => [
                'nonce' => wp_create_nonce( 'dps_portal_loyalty' ),
                'historyLimit' => 5,
                'i18n' => [
                    'loading' => __( 'Carregando...', 'dps-client-portal' ),
                    'redeemSuccess' => __( 'Resgate realizado com sucesso!', 'dps-client-portal' ),
                    'redeemError' => __( 'Não foi possível concluir o resgate.', 'dps-client-portal' ),
                ],
            ],
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
            } elseif ( 'session_expired' === $msg ) {
                echo '<div class="dps-portal-notice dps-portal-notice--error">' . esc_html__( 'Sua sessão expirou ou é inválida. Por favor, atualize a página e tente novamente.', 'dps-client-portal' ) . '</div>';
            } elseif ( 'pet_updated' === $msg ) {
                echo '<div class="dps-portal-notice dps-portal-notice--success">' . esc_html__( 'Dados do pet atualizados com sucesso.', 'dps-client-portal' ) . '</div>';
            } elseif ( 'preferences_updated' === $msg ) {
                echo '<div class="dps-portal-notice dps-portal-notice--success">' . esc_html__( 'Preferências atualizadas com sucesso.', 'dps-client-portal' ) . '</div>';
            } elseif ( 'pet_preferences_updated' === $msg ) {
                echo '<div class="dps-portal-notice dps-portal-notice--success">' . esc_html__( 'Preferências do pet atualizadas com sucesso.', 'dps-client-portal' ) . '</div>';
            } elseif ( 'review_submitted' === $msg ) {
                echo '<div class="dps-portal-notice dps-portal-notice--success">' . esc_html__( '🎉 Obrigado pela sua avaliação! Sua opinião é muito importante para nós.', 'dps-client-portal' ) . '</div>';
            } elseif ( 'review_already' === $msg ) {
                echo '<div class="dps-portal-notice dps-portal-notice--info">' . esc_html__( 'Você já fez uma avaliação. Obrigado pelo feedback!', 'dps-client-portal' ) . '</div>';
            } elseif ( 'review_invalid' === $msg ) {
                echo '<div class="dps-portal-notice dps-portal-notice--error">' . esc_html__( 'Por favor, selecione uma nota de 1 a 5 estrelas.', 'dps-client-portal' ) . '</div>';
            } elseif ( 'review_error' === $msg ) {
                echo '<div class="dps-portal-notice dps-portal-notice--error">' . esc_html__( 'Não foi possível registrar sua avaliação. Tente novamente.', 'dps-client-portal' ) . '</div>';
            }
        }
        
        // Fase 4 - Branding: buscar configurações
        $logo_id       = get_option( 'dps_portal_logo_id', '' );
        $primary_color = get_option( 'dps_portal_primary_color', '#0ea5e9' );
        $hero_id       = get_option( 'dps_portal_hero_id', '' );
        
        // Aplica classe de branding se houver customizações
        $portal_classes = [ 'dps-client-portal' ];
        if ( $logo_id || $primary_color !== '#0ea5e9' || $hero_id ) {
            $portal_classes[] = 'dps-portal-branded';
        }
        
        // Inline CSS para cor primária customizada
        if ( $primary_color && $primary_color !== '#0ea5e9' ) {
            echo '<style>.dps-portal-branded { --dps-custom-primary: ' . esc_attr( $primary_color ) . '; --dps-custom-primary-hover: ' . esc_attr( $this->adjust_brightness( $primary_color, -20 ) ) . '; }</style>';
        }
        
        echo '<div class="' . esc_attr( implode( ' ', $portal_classes ) ) . '">';
        
        // Header com título e botão de logout
        echo '<div class="dps-portal-header dps-portal-header--branded">';
        
        // Imagem hero (se configurada)
        if ( $hero_id ) {
            $hero_url = wp_get_attachment_image_url( $hero_id, 'full' );
            if ( $hero_url ) {
                echo '<div class="dps-portal-hero" style="background-image: url(' . esc_url( $hero_url ) . ');"></div>';
            }
        }
        
        // Logo (se configurado)
        if ( $logo_id ) {
            $logo_url = wp_get_attachment_image_url( $logo_id, 'medium' );
            if ( $logo_url ) {
                echo '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '" class="dps-portal-logo">';
            }
        }
        
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
        
        // Link de avaliação discreto no canto superior (movido da seção de dados)
        $review_url = $this->get_review_url();
        if ( $review_url ) {
            echo '<a href="' . esc_url( $review_url ) . '" target="_blank" rel="noopener" class="dps-portal-review-link" title="' . esc_attr__( 'Avalie nosso serviço', 'dps-client-portal' ) . '">';
            echo '<span class="dps-portal-review-icon">⭐</span>';
            echo '<span class="dps-portal-review-text">' . esc_html__( 'Avalie-nos', 'dps-client-portal' ) . '</span>';
            echo '</a>';
        }
        echo '</nav>';
        
        // Define tabs padrão (Fase 2.3)
        $default_tabs = [
            'inicio' => [
                'icon'  => '🏠',
                'label' => __( 'Início', 'dps-client-portal' ),
                'active' => true,
                'badge' => 0,
            ],
            'fidelidade' => [
                'icon'  => '🏆',
                'label' => __( 'Fidelidade', 'dps-client-portal' ),
                'active' => false,
                'badge' => 0,
            ],
            'avaliacoes' => [
                'icon'  => '⭐',
                'label' => __( 'Avaliações', 'dps-client-portal' ),
                'active' => false,
                'badge' => 0,
            ],
            'mensagens' => [
                'icon'  => '💬',
                'label' => __( 'Mensagens', 'dps-client-portal' ),
                'active' => false,
                'badge' => $this->get_unread_messages_count( $client_id ),
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
        
        // Panel: Início (Layout modernizado)
        echo '<div id="panel-inicio" class="dps-portal-tab-panel is-active" role="tabpanel" aria-hidden="false">';
        do_action( 'dps_portal_before_inicio_content', $client_id ); // Fase 2.3
        
        // Novo: Dashboard com métricas rápidas
        $this->render_quick_overview( $client_id );
        
        // Novo: Ações rápidas
        $this->render_quick_actions( $client_id );
        
        // Grid de conteúdo principal
        echo '<div class="dps-inicio-grid">';
        
        // Coluna esquerda: Próximo agendamento e Pets
        echo '<div class="dps-inicio-col dps-inicio-col--primary">';
        $this->render_next_appointment( $client_id );
        $this->render_pets_summary( $client_id );
        echo '</div>';
        
        // Coluna direita: Pendências e Sugestões
        echo '<div class="dps-inicio-col dps-inicio-col--secondary">';
        $this->render_financial_pending( $client_id );
        $this->render_recent_requests( $client_id ); // Fase 4: Solicitações recentes
        $this->render_contextual_suggestions( $client_id ); // Fase 2: Sugestões baseadas em histórico
        echo '</div>';
        
        echo '</div>'; // .dps-inicio-grid
        
        // Indicações (se ativo) - ocupa largura total
        if ( function_exists( 'dps_loyalty_get_referral_code' ) ) {
            $this->render_referrals_summary( $client_id );
        }
        
        do_action( 'dps_portal_after_inicio_content', $client_id ); // Fase 2.3
        echo '</div>';

        // Panel: Fidelidade
        echo '<div id="panel-fidelidade" class="dps-portal-tab-panel" role="tabpanel" aria-hidden="true">';
        do_action( 'dps_portal_before_fidelidade_content', $client_id );
        $this->render_loyalty_panel( $client_id );
        do_action( 'dps_portal_after_fidelidade_content', $client_id );
        echo '</div>';

        // Panel: Avaliações (CTA + prova social)
        echo '<div id="panel-avaliacoes" class="dps-portal-tab-panel" role="tabpanel" aria-hidden="true">';
        do_action( 'dps_portal_before_reviews_content', $client_id );
        $this->render_reviews_hub( $client_id );
        do_action( 'dps_portal_after_reviews_content', $client_id );
        echo '</div>';
        
        // Panel: Mensagens (Fase 4 - continuação)
        echo '<div id="panel-mensagens" class="dps-portal-tab-panel" role="tabpanel" aria-hidden="true">';
        do_action( 'dps_portal_before_mensagens_content', $client_id );
        $this->render_message_center( $client_id );
        do_action( 'dps_portal_after_mensagens_content', $client_id );
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
     * Obtém a URL configurada para avaliações no Google.
     *
     * @return string
     */
    private function get_review_url() {
        $default_review_url = defined( 'DPS_PORTAL_REVIEW_URL' ) ? DPS_PORTAL_REVIEW_URL : 'https://g.page/r/CUPivNuiAGwnEAE/review';
        $review_url         = get_option( 'dps_portal_review_url', $default_review_url );

        /**
         * Permite customizar o link de avaliação exibido no portal.
         *
         * @param string $review_url URL configurada.
         */
        $review_url = apply_filters( 'dps_portal_review_url', $review_url );

        return $review_url ? (string) $review_url : '';
    }

    /**
     * Busca avaliações internas para exibir prova social no portal.
     *
     * @param int $limit Quantidade de avaliações a exibir.
     * @return array
     */
    private function get_reviews_summary( $limit = 3 ) {
        if ( ! post_type_exists( 'dps_groomer_review' ) ) {
            return [
                'average' => 0,
                'count'   => 0,
                'items'   => [],
            ];
        }

        $reviews = get_posts( [
            'post_type'      => 'dps_groomer_review',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );

        $total_rating = 0;
        $rated_count  = 0;
        $items        = [];

        foreach ( $reviews as $review ) {
            $rating = (int) get_post_meta( $review->ID, '_dps_review_rating', true );
            $rating = max( 0, min( 5, $rating ) );

            if ( $rating > 0 ) {
                $total_rating += $rating;
                $rated_count++;
            }

            $items[] = [
                'rating'  => $rating,
                'author'  => get_post_meta( $review->ID, '_dps_review_name', true ),
                'date'    => get_the_date( get_option( 'date_format', 'd/m/Y' ), $review ),
                'content' => $review->post_content,
            ];
        }

        $average = $rated_count > 0 ? round( $total_rating / $rated_count, 1 ) : 0;

        return [
            'average' => $average,
            'count'   => $rated_count,
            'items'   => $items,
        ];
    }

    /**
     * Retorna marcação de estrelas acessível.
     *
     * @param float  $rating     Nota de 0 a 5.
     * @param string $aria_label Texto descritivo para leitores de tela.
     * @return string HTML das estrelas.
     */
    private function render_star_icons( $rating, $aria_label = '' ) {
        $rounded = max( 0, min( 5, (int) round( $rating ) ) );
        $filled  = str_repeat( '★', $rounded );
        $empty   = str_repeat( '☆', 5 - $rounded );
        $label   = $aria_label ? ' aria-label="' . esc_attr( $aria_label ) . '"' : '';

        return '<span class="dps-stars"' . $label . '>' . esc_html( $filled . $empty ) . '</span>';
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
     * Conta mensagens não lidas do cliente.
     * Fase 4 - continuação: Central de mensagens
     *
     * @param int $client_id ID do cliente.
     * @return int Número de mensagens não lidas.
     */
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
            
            // Calcula dias restantes
            $days_until = floor( ( strtotime( $date ) - strtotime( $today ) ) / DAY_IN_SECONDS );
            
            // Card de destaque para próximo agendamento
            echo '<div class="dps-appointment-card">';
            echo '<div class="dps-appointment-card__date">';
            echo '<span class="dps-appointment-card__day">' . esc_html( date_i18n( 'd', strtotime( $date ) ) ) . '</span>';
            echo '<span class="dps-appointment-card__month">' . esc_html( date_i18n( 'M', strtotime( $date ) ) ) . '</span>';
            echo '<span class="dps-appointment-card__weekday">' . esc_html( date_i18n( 'D', strtotime( $date ) ) ) . '</span>';
            echo '</div>';
            echo '<div class="dps-appointment-card__details">';
            
            // Status badge (se for hoje ou amanhã)
            if ( $days_until === 0 ) {
                echo '<span class="dps-appointment-card__badge dps-appointment-card__badge--today">' . esc_html__( 'Hoje!', 'dps-client-portal' ) . '</span>';
            } elseif ( $days_until === 1 ) {
                echo '<span class="dps-appointment-card__badge dps-appointment-card__badge--tomorrow">' . esc_html__( 'Amanhã', 'dps-client-portal' ) . '</span>';
            }
            
            echo '<div class="dps-appointment-card__time">⏰ ' . esc_html( $time ) . '</div>';
            if ( $pet_name ) {
                echo '<div class="dps-appointment-card__pet">🐾 ' . esc_html( $pet_name ) . '</div>';
            }
            if ( $services ) {
                echo '<div class="dps-appointment-card__services">✂️ ' . $services . '</div>';
            }
            if ( $status ) {
                $status_class = 'dps-appointment-card__status--' . sanitize_title( $status );
                echo '<div class="dps-appointment-card__status ' . esc_attr( $status_class ) . '">' . esc_html( ucfirst( $status ) ) . '</div>';
            }
            
            // Ações do agendamento
            echo '<div class="dps-appointment-card__actions">';
            
            // Link para mapa
            $address = get_post_meta( $client_id, 'client_address', true );
            if ( $address ) {
                $query = urlencode( $address );
                $url   = 'https://www.google.com/maps/search/?api=1&query=' . $query;
                echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer" class="dps-appointment-card__action-btn">📍 ' . esc_html__( 'Ver no mapa', 'dps-client-portal' ) . '</a>';
            }
            
            // Link para adicionar ao calendário
            if ( class_exists( 'DPS_Calendar_Helper' ) ) {
                $google_url = DPS_Calendar_Helper::get_google_calendar_url( $next->ID );
                if ( $google_url ) {
                    echo '<a href="' . esc_url( $google_url ) . '" target="_blank" rel="noopener" class="dps-appointment-card__action-btn">📆 ' . esc_html__( 'Adicionar ao calendário', 'dps-client-portal' ) . '</a>';
                }
            }
            
            echo '</div>'; // .dps-appointment-card__actions
            
            echo '</div>'; // .dps-appointment-card__details
            echo '</div>'; // .dps-appointment-card
        } else {
            // Estado vazio amigável com melhor UX
            echo '<div class="dps-empty-state dps-empty-state--appointment">';
            echo '<div class="dps-empty-state__icon">📅</div>';
            echo '<h3 class="dps-empty-state__title">' . esc_html__( 'Nenhum agendamento futuro', 'dps-client-portal' ) . '</h3>';
            echo '<p class="dps-empty-state__message">' . esc_html__( 'Você ainda não tem horários marcados. Que tal agendar um banho ou tosa?', 'dps-client-portal' ) . '</p>';
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
     * Renderiza uma visão rápida (dashboard) com métricas do cliente.
     * Mostra resumo visual de agendamentos, pendências e fidelidade.
     *
     * @since 3.0.0
     * @param int $client_id ID do cliente.
     */
    private function render_quick_overview( $client_id ) {
        // Conta agendamentos futuros
        $upcoming = $this->count_upcoming_appointments( $client_id );
        
        // Conta pets
        $pets = get_posts( [
            'post_type'      => 'dps_pet',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_key'       => 'owner_id',
            'meta_value'     => $client_id,
            'fields'         => 'ids',
        ] );
        $pets_count = count( $pets );
        
        // Conta mensagens não lidas
        $unread = $this->get_unread_messages_count( $client_id );
        
        // Pontos de fidelidade (se disponível)
        $points = 0;
        if ( function_exists( 'dps_loyalty_get_points' ) ) {
            $points = dps_loyalty_get_points( $client_id );
        }
        
        echo '<section class="dps-portal-overview">';
        echo '<h2 class="sr-only">' . esc_html__( 'Resumo Rápido', 'dps-client-portal' ) . '</h2>';
        
        echo '<div class="dps-overview-cards">';
        
        // Card: Agendamentos
        echo '<div class="dps-overview-card dps-overview-card--appointments">';
        echo '<div class="dps-overview-card__icon">📅</div>';
        echo '<div class="dps-overview-card__content">';
        echo '<span class="dps-overview-card__value">' . esc_html( $upcoming ) . '</span>';
        echo '<span class="dps-overview-card__label">' . esc_html( _n( 'Agendamento', 'Agendamentos', $upcoming, 'dps-client-portal' ) ) . '</span>';
        echo '</div>';
        echo '</div>';
        
        // Card: Pets
        echo '<div class="dps-overview-card dps-overview-card--pets">';
        echo '<div class="dps-overview-card__icon">🐾</div>';
        echo '<div class="dps-overview-card__content">';
        echo '<span class="dps-overview-card__value">' . esc_html( $pets_count ) . '</span>';
        echo '<span class="dps-overview-card__label">' . esc_html( _n( 'Pet', 'Pets', $pets_count, 'dps-client-portal' ) ) . '</span>';
        echo '</div>';
        echo '</div>';
        
        // Card: Mensagens
        echo '<div class="dps-overview-card dps-overview-card--messages' . ( $unread > 0 ? ' dps-overview-card--has-badge' : '' ) . '">';
        echo '<div class="dps-overview-card__icon">💬</div>';
        echo '<div class="dps-overview-card__content">';
        echo '<span class="dps-overview-card__value">' . esc_html( $unread ) . '</span>';
        echo '<span class="dps-overview-card__label">' . esc_html( _n( 'Nova Mensagem', 'Novas Mensagens', $unread, 'dps-client-portal' ) ) . '</span>';
        echo '</div>';
        echo '</div>';
        
        // Card: Pontos (se fidelidade ativo)
        if ( function_exists( 'dps_loyalty_get_points' ) ) {
            echo '<div class="dps-overview-card dps-overview-card--loyalty">';
            echo '<div class="dps-overview-card__icon">⭐</div>';
            echo '<div class="dps-overview-card__content">';
            echo '<span class="dps-overview-card__value">' . esc_html( number_format( $points, 0, ',', '.' ) ) . '</span>';
            echo '<span class="dps-overview-card__label">' . esc_html__( 'Pontos', 'dps-client-portal' ) . '</span>';
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>'; // .dps-overview-cards
        echo '</section>';
    }

    /**
     * Renderiza ações rápidas para o cliente.
     * Atalhos para as ações mais comuns do portal.
     *
     * @since 3.0.0
     * @param int $client_id ID do cliente.
     */
    private function render_quick_actions( $client_id ) {
        // Gera link de agendamento via WhatsApp
        if ( class_exists( 'DPS_WhatsApp_Helper' ) ) {
            $whatsapp_message = __( 'Olá! Gostaria de agendar um serviço.', 'dps-client-portal' );
            $whatsapp_url = DPS_WhatsApp_Helper::get_link_to_team( $whatsapp_message );
        } else {
            // Usa número configurado nas opções do sistema (sem fallback hardcoded)
            $whatsapp_number = get_option( 'dps_whatsapp_number', '' );
            if ( empty( $whatsapp_number ) ) {
                $whatsapp_url = '';
            } else {
                if ( class_exists( 'DPS_Phone_Helper' ) ) {
                    $whatsapp_number = DPS_Phone_Helper::format_for_whatsapp( $whatsapp_number );
                }
                $whatsapp_text = urlencode( __( 'Olá! Gostaria de agendar um serviço.', 'dps-client-portal' ) );
                $whatsapp_url = 'https://wa.me/' . $whatsapp_number . '?text=' . $whatsapp_text;
            }
        }
        
        // Link de avaliação
        $review_url = $this->get_review_url();
        
        echo '<section class="dps-portal-quick-actions">';
        echo '<h2 class="sr-only">' . esc_html__( 'Ações Rápidas', 'dps-client-portal' ) . '</h2>';
        
        echo '<div class="dps-quick-actions">';
        
        // Botão: Agendar Serviço (apenas se WhatsApp configurado)
        if ( ! empty( $whatsapp_url ) ) {
            echo '<a href="' . esc_url( $whatsapp_url ) . '" target="_blank" rel="noopener noreferrer" class="dps-quick-action dps-quick-action--primary">';
            echo '<span class="dps-quick-action__icon">📅</span>';
            echo '<span class="dps-quick-action__text">' . esc_html__( 'Agendar Serviço', 'dps-client-portal' ) . '</span>';
            echo '</a>';
        }
        
        // Botão: Falar Conosco
        echo '<button type="button" class="dps-quick-action dps-quick-action--chat" data-action="open-chat">';
        echo '<span class="dps-quick-action__icon">💬</span>';
        echo '<span class="dps-quick-action__text">' . esc_html__( 'Falar Conosco', 'dps-client-portal' ) . '</span>';
        echo '</button>';
        
        // Botão: Avaliar (se configurado)
        if ( $review_url ) {
            echo '<a href="' . esc_url( $review_url ) . '" target="_blank" rel="noopener noreferrer" class="dps-quick-action">';
            echo '<span class="dps-quick-action__icon">⭐</span>';
            echo '<span class="dps-quick-action__text">' . esc_html__( 'Avaliar Atendimento', 'dps-client-portal' ) . '</span>';
            echo '</a>';
        }
        
        // Botão: Meus Dados
        echo '<button type="button" class="dps-quick-action" data-tab="dados">';
        echo '<span class="dps-quick-action__icon">⚙️</span>';
        echo '<span class="dps-quick-action__text">' . esc_html__( 'Meus Dados', 'dps-client-portal' ) . '</span>';
        echo '</button>';
        
        echo '</div>'; // .dps-quick-actions
        echo '</section>';
    }

    /**
     * Número máximo de pets exibidos no resumo da aba Início.
     *
     * @var int
     */
    const PETS_SUMMARY_LIMIT = 6;

    /**
     * Renderiza um resumo visual dos pets do cliente.
     * Mostra foto, nome e próximo agendamento de cada pet.
     *
     * @since 3.0.0
     * @param int $client_id ID do cliente.
     */
    private function render_pets_summary( $client_id ) {
        // Busca pets do cliente
        $pets = get_posts( [
            'post_type'      => 'dps_pet',
            'post_status'    => 'publish',
            'posts_per_page' => self::PETS_SUMMARY_LIMIT,
            'meta_key'       => 'owner_id',
            'meta_value'     => $client_id,
        ] );
        
        if ( empty( $pets ) ) {
            return;
        }
        
        // Pre-load meta cache
        $pet_ids = wp_list_pluck( $pets, 'ID' );
        update_meta_cache( 'post', $pet_ids );
        
        echo '<section class="dps-portal-section dps-portal-pets-summary">';
        echo '<div class="dps-section-header">';
        echo '<h2>🐾 ' . esc_html__( 'Meus Pets', 'dps-client-portal' ) . '</h2>';
        echo '<button type="button" class="dps-link-button" data-tab="historico-pets">' . esc_html__( 'Ver Histórico Completo →', 'dps-client-portal' ) . '</button>';
        echo '</div>';
        
        echo '<div class="dps-pets-cards">';
        
        foreach ( $pets as $pet ) {
            $photo_id = get_post_meta( $pet->ID, 'pet_photo_id', true );
            $species  = get_post_meta( $pet->ID, 'pet_species', true );
            $breed    = get_post_meta( $pet->ID, 'pet_breed', true );
            
            // Busca último atendimento do pet
            $last_appointment = get_posts( [
                'post_type'      => 'dps_agendamento',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'meta_query'     => [
                    'relation' => 'AND',
                    [
                        'key'   => 'appointment_pet_id',
                        'value' => $pet->ID,
                    ],
                    [
                        'key'     => 'appointment_status',
                        'value'   => [ 'finalizado', 'finalizado e pago', 'finalizado_pago' ],
                        'compare' => 'IN',
                    ],
                ],
                'orderby'  => 'meta_value',
                'meta_key' => 'appointment_date',
                'order'    => 'DESC',
                'fields'   => 'ids',
            ] );
            
            $last_date = '';
            if ( ! empty( $last_appointment ) ) {
                $last_date = get_post_meta( $last_appointment[0], 'appointment_date', true );
            }
            
            echo '<div class="dps-pet-card">';
            
            // Foto do pet
            echo '<div class="dps-pet-card__photo">';
            if ( $photo_id ) {
                $photo_url = wp_get_attachment_image_url( $photo_id, 'thumbnail' );
                if ( $photo_url ) {
                    echo '<img src="' . esc_url( $photo_url ) . '" alt="' . esc_attr( $pet->post_title ) . '" loading="lazy">';
                } else {
                    echo '<span class="dps-pet-card__placeholder">🐾</span>';
                }
            } else {
                echo '<span class="dps-pet-card__placeholder">🐾</span>';
            }
            echo '</div>';
            
            // Informações do pet
            echo '<div class="dps-pet-card__info">';
            echo '<h3 class="dps-pet-card__name">' . esc_html( $pet->post_title ) . '</h3>';
            
            if ( $breed || $species ) {
                echo '<span class="dps-pet-card__breed">';
                if ( $breed ) {
                    echo esc_html( $breed );
                }
                if ( $breed && $species ) {
                    echo ' • ';
                }
                if ( $species ) {
                    echo esc_html( ucfirst( $species ) );
                }
                echo '</span>';
            }
            
            if ( $last_date ) {
                $days_ago = floor( ( time() - strtotime( $last_date ) ) / DAY_IN_SECONDS );
                $date_text = sprintf(
                    /* translators: %s: formatted date */
                    __( 'Último atendimento: %s', 'dps-client-portal' ),
                    date_i18n( 'd/m', strtotime( $last_date ) )
                );
                if ( $days_ago <= 1 ) {
                    $date_text = __( 'Último atendimento: Hoje', 'dps-client-portal' );
                } elseif ( $days_ago <= 7 ) {
                    $date_text = sprintf(
                        /* translators: %d: number of days */
                        _n( 'Último atendimento: %d dia atrás', 'Último atendimento: %d dias atrás', $days_ago, 'dps-client-portal' ),
                        $days_ago
                    );
                }
                echo '<span class="dps-pet-card__last-service">' . esc_html( $date_text ) . '</span>';
            } else {
                echo '<span class="dps-pet-card__last-service dps-pet-card__last-service--empty">' . esc_html__( 'Ainda não atendido', 'dps-client-portal' ) . '</span>';
            }
            
            echo '</div>'; // .dps-pet-card__info
            echo '</div>'; // .dps-pet-card
        }
        
        echo '</div>'; // .dps-pets-cards
        echo '</section>';
    }

    /**
     * Renderiza a seção de histórico de agendamentos do cliente.
     * 
     * Layout moderno com:
     * - Métricas rápidas no topo
     * - Seção de próximos agendamentos em cards
     * - Histórico em tabela responsiva
     * - Estados vazios orientadores
     *
     * @param int $client_id ID do cliente.
     * @since 3.1.0 Refatorado para layout moderno
     */
    private function render_appointment_history( $client_id ) {
        $today = current_time( 'Y-m-d' );
        
        // Busca todos os agendamentos do cliente
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
            'order'          => 'ASC', // ASC para separar futuros corretamente
        ];
        $all_appointments = get_posts( $args );
        
        // Separa agendamentos futuros e passados
        $upcoming = [];
        $history  = [];
        
        if ( $all_appointments ) {
            // OTIMIZAÇÃO: Pre-load meta cache para evitar N+1 queries
            $appt_ids = wp_list_pluck( $all_appointments, 'ID' );
            update_meta_cache( 'post', $appt_ids );
            
            // OTIMIZAÇÃO: Batch load de pets para evitar queries individuais
            $pet_ids = [];
            foreach ( $all_appointments as $appt ) {
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
                    'fields'         => 'ids',
                ] );
                
                foreach ( $pets as $pet_id ) {
                    $pets_cache[ $pet_id ] = get_the_title( $pet_id );
                }
            }
            
            // Separa por data
            foreach ( $all_appointments as $appt ) {
                $date   = get_post_meta( $appt->ID, 'appointment_date', true );
                $status = get_post_meta( $appt->ID, 'appointment_status', true );
                
                // Agendamentos futuros: data >= hoje E status não finalizado/cancelado
                if ( $date && strtotime( $date ) >= strtotime( $today ) && 
                     ! in_array( $status, self::COMPLETED_STATUSES, true ) ) {
                    $upcoming[] = $appt;
                } else {
                    $history[] = $appt;
                }
            }
            
            // Reordena histórico DESC (mais recentes primeiro)
            usort( $history, function( $a, $b ) {
                $date_a = get_post_meta( $a->ID, 'appointment_date', true );
                $date_b = get_post_meta( $b->ID, 'appointment_date', true );
                return strtotime( $date_b ) - strtotime( $date_a );
            } );
        }
        
        // ========================================
        // MÉTRICAS RÁPIDAS
        // ========================================
        $this->render_appointments_metrics( count( $upcoming ), count( $history ) );
        
        // ========================================
        // PRÓXIMOS AGENDAMENTOS (Cards)
        // ========================================
        $this->render_upcoming_appointments_section( $upcoming, $pets_cache ?? [] );
        
        // ========================================
        // HISTÓRICO (Tabela)
        // ========================================
        $this->render_history_section( $history, $pets_cache ?? [] );
    }
    
    /**
     * Renderiza métricas de agendamentos.
     *
     * @since 3.1.0
     * @param int $upcoming_count Contagem de próximos agendamentos.
     * @param int $history_count  Contagem de histórico.
     */
    private function render_appointments_metrics( $upcoming_count, $history_count ) {
        echo '<section class="dps-portal-section dps-appointments-metrics">';
        
        echo '<div class="dps-metrics-grid dps-metrics-grid--appointments">';
        
        // Card: Próximos Agendamentos
        $upcoming_class = $upcoming_count > 0 ? 'dps-metric-card--highlight' : '';
        echo '<div class="dps-metric-card ' . esc_attr( $upcoming_class ) . '">';
        echo '<span class="dps-metric-card__icon">📅</span>';
        echo '<div class="dps-metric-card__content">';
        echo '<span class="dps-metric-card__value">' . esc_html( $upcoming_count ) . '</span>';
        echo '<span class="dps-metric-card__label">' . esc_html( _n( 'Próximo Agendamento', 'Próximos Agendamentos', $upcoming_count, 'dps-client-portal' ) ) . '</span>';
        echo '</div>';
        echo '</div>';
        
        // Card: Total de Atendimentos
        echo '<div class="dps-metric-card">';
        echo '<span class="dps-metric-card__icon">✅</span>';
        echo '<div class="dps-metric-card__content">';
        echo '<span class="dps-metric-card__value">' . esc_html( $history_count ) . '</span>';
        echo '<span class="dps-metric-card__label">' . esc_html( _n( 'Atendimento Realizado', 'Atendimentos Realizados', $history_count, 'dps-client-portal' ) ) . '</span>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>'; // .dps-metrics-grid
        echo '</section>';
    }
    
    /**
     * Renderiza seção de próximos agendamentos em cards.
     *
     * @since 3.1.0
     * @param array $upcoming   Array de agendamentos futuros.
     * @param array $pets_cache Cache de nomes de pets.
     */
    private function render_upcoming_appointments_section( $upcoming, $pets_cache ) {
        echo '<section id="proximos-agendamentos" class="dps-portal-section dps-portal-upcoming">';
        echo '<h2>' . esc_html__( '📅 Próximos Agendamentos', 'dps-client-portal' ) . '</h2>';
        
        if ( ! empty( $upcoming ) ) {
            echo '<div class="dps-upcoming-cards">';
            foreach ( $upcoming as $appt ) {
                $this->render_upcoming_appointment_card( $appt, $pets_cache );
            }
            echo '</div>';
        } else {
            $this->render_no_upcoming_state();
        }
        
        echo '</section>';
    }
    
    /**
     * Renderiza um card de próximo agendamento.
     *
     * @since 3.1.0
     * @param WP_Post $appt       Objeto do agendamento.
     * @param array   $pets_cache Cache de nomes de pets.
     */
    private function render_upcoming_appointment_card( $appt, $pets_cache ) {
        $today    = current_time( 'Y-m-d' );
        $tomorrow = date( 'Y-m-d', strtotime( '+1 day', strtotime( $today ) ) );
        
        $date     = get_post_meta( $appt->ID, 'appointment_date', true );
        $time     = get_post_meta( $appt->ID, 'appointment_time', true );
        $status   = get_post_meta( $appt->ID, 'appointment_status', true );
        $pet_id   = get_post_meta( $appt->ID, 'appointment_pet_id', true );
        $services = get_post_meta( $appt->ID, 'appointment_services', true );
        
        $pet_name      = isset( $pets_cache[ $pet_id ] ) ? $pets_cache[ $pet_id ] : '';
        $services_text = $this->format_services_text( $services );
        
        // Determina badge de urgência
        $badge_class = '';
        $badge_text  = '';
        if ( $date === $today ) {
            $badge_class = 'dps-appointment-card__badge--today';
            $badge_text  = __( 'Hoje!', 'dps-client-portal' );
        } elseif ( $date === $tomorrow ) {
            $badge_class = 'dps-appointment-card__badge--tomorrow';
            $badge_text  = __( 'Amanhã', 'dps-client-portal' );
        }
        
        // Status class
        $status_class = $this->get_status_class( $status );
        
        echo '<div class="dps-appointment-card">';
        
        // Data visual
        echo '<div class="dps-appointment-card__date">';
        if ( $badge_text ) {
            echo '<span class="dps-appointment-card__badge ' . esc_attr( $badge_class ) . '">' . esc_html( $badge_text ) . '</span>';
        }
        echo '<span class="dps-appointment-card__day">' . esc_html( date_i18n( 'd', strtotime( $date ) ) ) . '</span>';
        echo '<span class="dps-appointment-card__month">' . esc_html( date_i18n( 'M', strtotime( $date ) ) ) . '</span>';
        echo '<span class="dps-appointment-card__weekday">' . esc_html( date_i18n( 'D', strtotime( $date ) ) ) . '</span>';
        echo '</div>';
        
        // Detalhes
        echo '<div class="dps-appointment-card__details">';
        echo '<div class="dps-appointment-card__time">⏰ ' . esc_html( $time ) . '</div>';
        if ( $pet_name ) {
            echo '<div class="dps-appointment-card__pet">🐾 ' . esc_html( $pet_name ) . '</div>';
        }
        if ( $services_text ) {
            // services_text já está escapado via format_services_text()
            echo '<div class="dps-appointment-card__services">✂️ ' . $services_text . '</div>';
        }
        if ( $status ) {
            echo '<div class="dps-appointment-card__status ' . esc_attr( $status_class ) . '">' . esc_html( ucfirst( $status ) ) . '</div>';
        }
        
        // Ações
        echo '<div class="dps-appointment-card__actions">';
        
        // Adicionar ao calendário
        $ics_url = wp_nonce_url(
            add_query_arg( 'dps_download_ics', $appt->ID, home_url( '/' ) ),
            'dps_download_ics_' . $appt->ID
        );
        echo '<a href="' . esc_url( $ics_url ) . '" class="dps-appointment-card__action-btn" title="' . esc_attr__( 'Baixar arquivo .ics', 'dps-client-portal' ) . '">';
        echo '📅 ' . esc_html__( 'Calendário', 'dps-client-portal' );
        echo '</a>';
        
        // Google Calendar
        if ( class_exists( 'DPS_Calendar_Helper' ) ) {
            $google_url = DPS_Calendar_Helper::get_google_calendar_url( $appt->ID );
            if ( $google_url ) {
                echo '<a href="' . esc_url( $google_url ) . '" class="dps-appointment-card__action-btn" target="_blank" rel="noopener">';
                echo '📆 ' . esc_html__( 'Google', 'dps-client-portal' );
                echo '</a>';
            }
        }
        
        echo '</div>'; // .dps-appointment-card__actions
        echo '</div>'; // .dps-appointment-card__details
        echo '</div>'; // .dps-appointment-card
    }
    
    /**
     * Renderiza estado vazio para próximos agendamentos.
     *
     * @since 3.1.0
     */
    private function render_no_upcoming_state() {
        echo '<div class="dps-empty-state dps-empty-state--appointment">';
        echo '<div class="dps-empty-state__icon">📅</div>';
        echo '<p class="dps-empty-state__message">' . esc_html__( 'Você não tem agendamentos futuros no momento.', 'dps-client-portal' ) . '</p>';
        
        // CTA para agendar
        if ( class_exists( 'DPS_WhatsApp_Helper' ) ) {
            $whatsapp_message = __( 'Olá! Gostaria de agendar um serviço.', 'dps-client-portal' );
            $whatsapp_url = DPS_WhatsApp_Helper::get_link_to_team( $whatsapp_message );
        } else {
            $whatsapp_number = get_option( 'dps_whatsapp_number', '' );
            if ( ! empty( $whatsapp_number ) && class_exists( 'DPS_Phone_Helper' ) ) {
                $whatsapp_number = DPS_Phone_Helper::format_for_whatsapp( $whatsapp_number );
            }
            $whatsapp_text = rawurlencode( __( 'Olá! Gostaria de agendar um serviço.', 'dps-client-portal' ) );
            $whatsapp_url = ! empty( $whatsapp_number ) ? 'https://wa.me/' . $whatsapp_number . '?text=' . $whatsapp_text : '';
        }
        
        if ( ! empty( $whatsapp_url ) ) {
            echo '<a href="' . esc_url( $whatsapp_url ) . '" target="_blank" class="dps-empty-state__action">';
            echo '💬 ' . esc_html__( 'Agendar via WhatsApp', 'dps-client-portal' );
            echo '</a>';
        }
        
        echo '</div>';
    }
    
    /**
     * Renderiza seção de histórico de atendimentos.
     *
     * @since 3.1.0
     * @param array $history    Array de agendamentos passados.
     * @param array $pets_cache Cache de nomes de pets.
     */
    private function render_history_section( $history, $pets_cache ) {
        echo '<section id="historico" class="dps-portal-section dps-portal-history">';
        echo '<h2>' . esc_html__( '📋 Histórico de Atendimentos', 'dps-client-portal' ) . '</h2>';
        
        if ( ! empty( $history ) ) {
            echo '<div class="dps-history-table-wrapper">';
            echo '<table class="dps-table dps-history-table"><thead><tr>';
            echo '<th>' . esc_html__( 'Data', 'dps-client-portal' ) . '</th>';
            echo '<th>' . esc_html__( 'Horário', 'dps-client-portal' ) . '</th>';
            echo '<th>' . esc_html__( 'Pet', 'dps-client-portal' ) . '</th>';
            echo '<th>' . esc_html__( 'Serviços', 'dps-client-portal' ) . '</th>';
            echo '<th>' . esc_html__( 'Status', 'dps-client-portal' ) . '</th>';
            echo '<th>' . esc_html__( 'Ações', 'dps-client-portal' ) . '</th>';
            echo '</tr></thead><tbody>';
            
            foreach ( $history as $appt ) {
                $this->render_history_row( $appt, $pets_cache );
            }
            
            echo '</tbody></table>';
            echo '</div>'; // .dps-history-table-wrapper
        } else {
            $this->render_no_history_state();
        }
        
        echo '</section>';
    }
    
    /**
     * Renderiza uma linha do histórico de atendimentos.
     *
     * @since 3.1.0
     * @param WP_Post $appt       Objeto do agendamento.
     * @param array   $pets_cache Cache de nomes de pets.
     */
    private function render_history_row( $appt, $pets_cache ) {
        $date     = get_post_meta( $appt->ID, 'appointment_date', true );
        $time     = get_post_meta( $appt->ID, 'appointment_time', true );
        $status   = get_post_meta( $appt->ID, 'appointment_status', true );
        $pet_id   = get_post_meta( $appt->ID, 'appointment_pet_id', true );
        $services = get_post_meta( $appt->ID, 'appointment_services', true );
        
        $pet_name      = isset( $pets_cache[ $pet_id ] ) ? $pets_cache[ $pet_id ] : '';
        $services_text = $this->format_services_text( $services );
        
        $status_class = $this->get_status_class( $status );
        
        echo '<tr>';
        echo '<td data-label="' . esc_attr__( 'Data', 'dps-client-portal' ) . '">' . esc_html( $date ? date_i18n( 'd/m/Y', strtotime( $date ) ) : '' ) . '</td>';
        echo '<td data-label="' . esc_attr__( 'Horário', 'dps-client-portal' ) . '">' . esc_html( $time ) . '</td>';
        echo '<td data-label="' . esc_attr__( 'Pet', 'dps-client-portal' ) . '">' . esc_html( $pet_name ) . '</td>';
        // services_text já está escapado via format_services_text()
        echo '<td data-label="' . esc_attr__( 'Serviços', 'dps-client-portal' ) . '">' . $services_text . '</td>';
        echo '<td data-label="' . esc_attr__( 'Status', 'dps-client-portal' ) . '"><span class="dps-status-badge ' . esc_attr( $status_class ) . '">' . esc_html( ucfirst( $status ) ) . '</span></td>';
        
        // Ações
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
    
    /**
     * Renderiza estado vazio para histórico.
     *
     * @since 3.1.0
     */
    private function render_no_history_state() {
        echo '<div class="dps-empty-state">';
        echo '<div class="dps-empty-state__icon">📋</div>';
        echo '<p class="dps-empty-state__title">' . esc_html__( 'Sem histórico ainda', 'dps-client-portal' ) . '</p>';
        echo '<p class="dps-empty-state__message">' . esc_html__( 'Seu histórico de atendimentos aparecerá aqui após realizar seu primeiro serviço conosco.', 'dps-client-portal' ) . '</p>';
        echo '</div>';
    }
    
    /**
     * Retorna a classe CSS para o status do agendamento.
     *
     * @since 3.1.0
     * @param string $status Status do agendamento.
     * @return string Classe CSS do status.
     */
    private function get_status_class( $status ) {
        $status_lower = strtolower( $status );
        
        $status_map = [
            'agendado'           => 'dps-status-badge--scheduled',
            'confirmado'         => 'dps-status-badge--confirmed',
            'em andamento'       => 'dps-status-badge--in-progress',
            'finalizado'         => 'dps-status-badge--completed',
            'finalizado e pago'  => 'dps-status-badge--paid',
            'finalizado_pago'    => 'dps-status-badge--paid',
            'cancelado'          => 'dps-status-badge--cancelled',
            'pendente'           => 'dps-status-badge--pending',
        ];
        
        return isset( $status_map[ $status_lower ] ) ? $status_map[ $status_lower ] : 'dps-status-badge--default';
    }
    
    /**
     * Formata lista de serviços para exibição.
     *
     * @since 3.1.0
     * @param mixed $services Array de serviços ou string vazia.
     * @return string Serviços formatados e escapados.
     */
    private function format_services_text( $services ) {
        if ( is_array( $services ) && ! empty( $services ) ) {
            return implode( ', ', array_map( 'esc_html', $services ) );
        }
        return '';
    }

    /**
     * Renderiza a seção de galeria de fotos dos pets do cliente.
     * Layout moderno seguindo padrão das abas Avaliações, Fidelidade e Mensagens.
     * Exibe fotos de perfil dos pets e fotos de atendimentos (banho/tosa).
     *
     * @since 3.2.0
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
        
        // Conta fotos disponíveis (perfil + atendimentos)
        $total_profile_photos = 0;
        $total_grooming_photos = 0;
        $pets_with_photos = [];
        
        foreach ( $pets as $pet ) {
            $photo_id = get_post_meta( $pet->ID, 'pet_photo_id', true );
            $grooming_photos = get_post_meta( $pet->ID, 'pet_grooming_photos', true );
            
            $pet_data = [
                'id'              => $pet->ID,
                'name'            => $pet->post_title,
                'profile_photo'   => $photo_id ? wp_get_attachment_image_url( $photo_id, 'medium' ) : '',
                'profile_photo_lg' => $photo_id ? wp_get_attachment_image_url( $photo_id, 'large' ) : '',
                'grooming_photos' => [],
            ];
            
            if ( $pet_data['profile_photo'] ) {
                $total_profile_photos++;
            }
            
            // Processa fotos de atendimentos (array de IDs ou array de objetos com data)
            if ( ! empty( $grooming_photos ) && is_array( $grooming_photos ) ) {
                foreach ( $grooming_photos as $gp ) {
                    $photo_info = $this->parse_grooming_photo( $gp );
                    if ( $photo_info ) {
                        $pet_data['grooming_photos'][] = $photo_info;
                        $total_grooming_photos++;
                    }
                }
            }
            
            $pets_with_photos[] = $pet_data;
        }
        
        $total_photos = $total_profile_photos + $total_grooming_photos;
        $pets_count = count( $pets );
        
        echo '<section id="galeria" class="dps-portal-section dps-portal-gallery">';
        
        // Header moderno com ícone + título + subtítulo (padrão das outras abas)
        echo '<div class="dps-gallery-header">';
        echo '<h2 class="dps-section-title"><span class="dps-section-title__icon">📸</span>' . esc_html__( 'Galeria de Fotos', 'dps-client-portal' ) . '</h2>';
        echo '<p class="dps-section-subtitle">' . esc_html__( 'Veja as fotos dos seus pets antes e depois dos atendimentos', 'dps-client-portal' ) . '</p>';
        echo '</div>';
        
        // Cards de métricas (padrão das outras abas)
        $this->render_gallery_metrics( $pets_count, $total_profile_photos, $total_grooming_photos );
        
        // Conteúdo principal
        if ( $pets ) {
            // Navegação por pets (filtro)
            if ( count( $pets ) > 1 ) {
                $this->render_gallery_pet_filter( $pets_with_photos );
            }
            
            // Grid de fotos por pet
            echo '<div class="dps-gallery-content">';
            foreach ( $pets_with_photos as $index => $pet_data ) {
                $this->render_pet_gallery_card( $pet_data, $index );
            }
            echo '</div>';
            
            // Nota informativa sobre envio de fotos
            echo '<div class="dps-gallery-info">';
            echo '<p class="dps-gallery-info__text">';
            echo '<span class="dps-gallery-info__icon">💡</span> ';
            echo esc_html__( 'Novas fotos são adicionadas pela equipe após cada atendimento. Fique de olho!', 'dps-client-portal' );
            echo '</p>';
            echo '</div>';
            
        } else {
            // Estado vazio orientador
            $this->render_gallery_empty_state();
        }
        
        echo '</section>';
    }

    /**
     * Renderiza métricas da galeria de fotos.
     *
     * @since 3.2.0
     * @param int $pets_count          Total de pets.
     * @param int $profile_photos      Total de fotos de perfil.
     * @param int $grooming_photos     Total de fotos de atendimentos.
     */
    private function render_gallery_metrics( $pets_count, $profile_photos, $grooming_photos ) {
        $total_photos = $profile_photos + $grooming_photos;
        
        echo '<div class="dps-gallery-metrics">';
        
        // Card: Total de Pets
        echo '<div class="dps-gallery-metric-card">';
        echo '<div class="dps-gallery-metric-card__icon">🐾</div>';
        echo '<div class="dps-gallery-metric-card__content">';
        echo '<span class="dps-gallery-metric-card__value">' . esc_html( $pets_count ) . '</span>';
        echo '<span class="dps-gallery-metric-card__label">' . esc_html( _n( 'Pet', 'Pets', $pets_count, 'dps-client-portal' ) ) . '</span>';
        echo '</div>';
        echo '</div>';
        
        // Card: Fotos de Perfil
        echo '<div class="dps-gallery-metric-card">';
        echo '<div class="dps-gallery-metric-card__icon">👤</div>';
        echo '<div class="dps-gallery-metric-card__content">';
        echo '<span class="dps-gallery-metric-card__value">' . esc_html( $profile_photos ) . '</span>';
        echo '<span class="dps-gallery-metric-card__label">' . esc_html( _n( 'Foto de Perfil', 'Fotos de Perfil', $profile_photos, 'dps-client-portal' ) ) . '</span>';
        echo '</div>';
        echo '</div>';
        
        // Card: Fotos de Atendimentos (destaque se houver)
        $highlight_class = $grooming_photos > 0 ? ' dps-gallery-metric-card--highlight' : '';
        echo '<div class="dps-gallery-metric-card' . esc_attr( $highlight_class ) . '">';
        echo '<div class="dps-gallery-metric-card__icon">✨</div>';
        echo '<div class="dps-gallery-metric-card__content">';
        echo '<span class="dps-gallery-metric-card__value">' . esc_html( $grooming_photos ) . '</span>';
        echo '<span class="dps-gallery-metric-card__label">' . esc_html( _n( 'Foto de Atendimento', 'Fotos de Atendimentos', $grooming_photos, 'dps-client-portal' ) ) . '</span>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>'; // .dps-gallery-metrics
    }

    /**
     * Renderiza filtro por pet na galeria.
     *
     * @since 3.2.0
     * @param array $pets_with_photos Array de pets com dados de fotos.
     */
    private function render_gallery_pet_filter( $pets_with_photos ) {
        echo '<div class="dps-gallery-filter">';
        echo '<span class="dps-gallery-filter__label">' . esc_html__( 'Filtrar por pet:', 'dps-client-portal' ) . '</span>';
        echo '<div class="dps-gallery-filter__buttons">';
        
        // Botão "Todos"
        echo '<button type="button" class="dps-gallery-filter__btn is-active" data-filter="all">';
        echo esc_html__( 'Todos', 'dps-client-portal' );
        echo '</button>';
        
        // Botões por pet
        foreach ( $pets_with_photos as $pet_data ) {
            echo '<button type="button" class="dps-gallery-filter__btn" data-filter="pet-' . esc_attr( $pet_data['id'] ) . '">';
            echo esc_html( $pet_data['name'] );
            echo '</button>';
        }
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Renderiza card de um pet na galeria.
     *
     * @since 3.2.0
     * @param array $pet_data Dados do pet com fotos.
     * @param int   $index    Índice do pet na lista.
     */
    private function render_pet_gallery_card( $pet_data, $index ) {
        $has_any_photo = ! empty( $pet_data['profile_photo'] ) || ! empty( $pet_data['grooming_photos'] );
        
        echo '<div class="dps-gallery-pet-card" data-pet-id="pet-' . esc_attr( $pet_data['id'] ) . '">';
        
        // Header do card com nome do pet
        echo '<div class="dps-gallery-pet-card__header">';
        echo '<h3 class="dps-gallery-pet-card__name">';
        echo '<span class="dps-gallery-pet-card__icon">🐾</span> ';
        echo esc_html( $pet_data['name'] );
        echo '</h3>';
        echo '</div>';
        
        // Conteúdo do card
        echo '<div class="dps-gallery-pet-card__content">';
        
        if ( $has_any_photo ) {
            // Grid de fotos
            echo '<div class="dps-gallery-photo-grid">';
            
            // Foto de perfil
            if ( ! empty( $pet_data['profile_photo'] ) ) {
                $this->render_gallery_photo_item( 
                    $pet_data['profile_photo'], 
                    $pet_data['profile_photo_lg'] ?: $pet_data['profile_photo'],
                    $pet_data['name'],
                    $pet_data['id'],
                    __( 'Foto de Perfil', 'dps-client-portal' ),
                    'profile'
                );
            }
            
            // Fotos de atendimentos
            foreach ( $pet_data['grooming_photos'] as $gp ) {
                // Valida data antes de usar strtotime (evita falso em strings inválidas)
                $date_label = __( 'Foto de Atendimento', 'dps-client-portal' );
                if ( ! empty( $gp['date'] ) ) {
                    $timestamp = strtotime( $gp['date'] );
                    if ( false !== $timestamp ) {
                        $date_label = sprintf( __( 'Atendimento em %s', 'dps-client-portal' ), date_i18n( 'd/m/Y', $timestamp ) );
                    }
                }
                
                $this->render_gallery_photo_item( 
                    $gp['url'], 
                    $gp['url_lg'] ?: $gp['url'],
                    $pet_data['name'],
                    $pet_data['id'],
                    $date_label,
                    'grooming'
                );
            }
            
            echo '</div>'; // .dps-gallery-photo-grid
            
        } else {
            // Pet sem fotos
            echo '<div class="dps-gallery-pet-card__empty">';
            echo '<span class="dps-gallery-pet-card__empty-icon">📷</span>';
            echo '<p class="dps-gallery-pet-card__empty-text">' . esc_html__( 'Ainda não há fotos deste pet.', 'dps-client-portal' ) . '</p>';
            echo '<p class="dps-gallery-pet-card__empty-hint">' . esc_html__( 'As fotos serão adicionadas após os próximos atendimentos.', 'dps-client-portal' ) . '</p>';
            echo '</div>';
        }
        
        echo '</div>'; // .dps-gallery-pet-card__content
        echo '</div>'; // .dps-gallery-pet-card
    }

    /**
     * Renderiza um item de foto na galeria.
     *
     * @since 3.2.0
     * @param string $url       URL da imagem (tamanho médio).
     * @param string $url_lg    URL da imagem grande (para lightbox).
     * @param string $pet_name  Nome do pet.
     * @param int    $pet_id    ID do pet (para garantir unicidade no data-lightbox).
     * @param string $label     Label da foto (ex: "Foto de Perfil", "Atendimento em 01/01/2026").
     * @param string $type      Tipo de foto: 'profile' ou 'grooming'.
     */
    private function render_gallery_photo_item( $url, $url_lg, $pet_name, $pet_id, $label, $type = 'grooming' ) {
        $type_class = 'profile' === $type ? 'dps-gallery-photo--profile' : 'dps-gallery-photo--grooming';
        
        // Monta mensagem de compartilhamento
        $share_message = sprintf( 
            __( 'Olha que lindo(a) o(a) %s ficou após o banho/tosa! 🐾✨', 'dps-client-portal' ), 
            $pet_name 
        );
        
        if ( class_exists( 'DPS_WhatsApp_Helper' ) ) {
            $wa_link = DPS_WhatsApp_Helper::get_share_link( $share_message . ' ' . $url_lg );
        } else {
            $wa_text = urlencode( $share_message . ' ' . $url_lg );
            $wa_link = 'https://wa.me/?text=' . $wa_text;
        }
        
        echo '<div class="dps-gallery-photo ' . esc_attr( $type_class ) . '">';
        
        // Imagem com link para lightbox (usa ID do pet para garantir unicidade)
        $lightbox_group = 'gallery-' . absint( $pet_id );
        echo '<a href="' . esc_url( $url_lg ) . '" class="dps-gallery-photo__link" data-lightbox="' . esc_attr( $lightbox_group ) . '" title="' . esc_attr( $pet_name . ' - ' . $label ) . '">';
        echo '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $pet_name ) . '" class="dps-gallery-photo__img" loading="lazy">';
        echo '<span class="dps-gallery-photo__overlay">';
        echo '<span class="dps-gallery-photo__zoom">🔍</span>';
        echo '</span>';
        echo '</a>';
        
        // Info e ações
        echo '<div class="dps-gallery-photo__info">';
        echo '<span class="dps-gallery-photo__label">' . esc_html( $label ) . '</span>';
        echo '<div class="dps-gallery-photo__actions">';
        
        // Botão de compartilhamento
        echo '<a href="' . esc_url( $wa_link ) . '" target="_blank" rel="noopener noreferrer" class="dps-gallery-photo__action dps-gallery-photo__action--whatsapp" title="' . esc_attr__( 'Compartilhar via WhatsApp', 'dps-client-portal' ) . '">';
        echo '<span class="dps-gallery-photo__action-icon">💬</span>';
        echo '</a>';
        
        // Botão de download
        echo '<a href="' . esc_url( $url_lg ) . '" download class="dps-gallery-photo__action dps-gallery-photo__action--download" title="' . esc_attr__( 'Baixar foto', 'dps-client-portal' ) . '">';
        echo '<span class="dps-gallery-photo__action-icon">⬇️</span>';
        echo '</a>';
        
        echo '</div>'; // .dps-gallery-photo__actions
        echo '</div>'; // .dps-gallery-photo__info
        echo '</div>'; // .dps-gallery-photo
    }

    /**
     * Renderiza estado vazio da galeria.
     *
     * @since 3.2.0
     */
    private function render_gallery_empty_state() {
        echo '<div class="dps-gallery-empty-state">';
        echo '<div class="dps-gallery-empty-state__icon">📷</div>';
        echo '<h3 class="dps-gallery-empty-state__title">' . esc_html__( 'Nenhum pet cadastrado ainda', 'dps-client-portal' ) . '</h3>';
        echo '<p class="dps-gallery-empty-state__message">' . esc_html__( 'Quando você tiver pets cadastrados, as fotos deles aparecerão aqui.', 'dps-client-portal' ) . '</p>';
        echo '<p class="dps-gallery-empty-state__hint">' . esc_html__( 'Entre em contato conosco para agendar o primeiro atendimento!', 'dps-client-portal' ) . '</p>';
        
        // Botão de ação
        if ( class_exists( 'DPS_WhatsApp_Helper' ) ) {
            $wa_link = DPS_WhatsApp_Helper::get_link_to_team( __( 'Olá! Gostaria de cadastrar meu pet e agendar um atendimento.', 'dps-client-portal' ) );
        } else {
            $whatsapp_number = get_option( 'dps_whatsapp_number', '' );
            // Valida formato básico do número (apenas dígitos e mínimo 10 caracteres)
            $whatsapp_number = preg_replace( '/[^0-9]/', '', $whatsapp_number );
            if ( strlen( $whatsapp_number ) >= 10 ) {
                if ( class_exists( 'DPS_Phone_Helper' ) ) {
                    $whatsapp_number = DPS_Phone_Helper::format_for_whatsapp( $whatsapp_number );
                }
                $wa_text = urlencode( __( 'Olá! Gostaria de cadastrar meu pet e agendar um atendimento.', 'dps-client-portal' ) );
                $wa_link = 'https://wa.me/' . $whatsapp_number . '?text=' . $wa_text;
            } else {
                $wa_link = '';
            }
        }
        
        if ( ! empty( $wa_link ) ) {
            echo '<a href="' . esc_url( $wa_link ) . '" target="_blank" rel="noopener noreferrer" class="dps-gallery-empty-state__action">';
            echo '💬 ' . esc_html__( 'Falar com a Equipe', 'dps-client-portal' );
            echo '</a>';
        }
        
        echo '</div>';
    }

    /**
     * Processa dados de uma foto de atendimento.
     * Pode receber um ID de attachment ou um array com dados adicionais.
     *
     * @since 3.2.0
     * @param mixed $photo_data ID do attachment ou array com 'id', 'date', etc.
     * @return array|null Array com dados da foto ou null se inválido.
     */
    private function parse_grooming_photo( $photo_data ) {
        $photo_id = 0;
        $date = '';
        $service = '';
        
        if ( is_numeric( $photo_data ) ) {
            $photo_id = (int) $photo_data;
        } elseif ( is_array( $photo_data ) ) {
            $photo_id = isset( $photo_data['id'] ) ? (int) $photo_data['id'] : 0;
            $date = isset( $photo_data['date'] ) ? sanitize_text_field( $photo_data['date'] ) : '';
            $service = isset( $photo_data['service'] ) ? sanitize_text_field( $photo_data['service'] ) : '';
        }
        
        if ( ! $photo_id ) {
            return null;
        }
        
        $url = wp_get_attachment_image_url( $photo_id, 'medium' );
        if ( ! $url ) {
            return null;
        }
        
        return [
            'id'      => $photo_id,
            'url'     => $url,
            'url_lg'  => wp_get_attachment_image_url( $photo_id, 'large' ) ?: $url,
            'date'    => $date,
            'service' => $service,
        ];
    }

    /**
     * Renderiza o centro de mensagens entre cliente e administração.
     * Fase 4 - continuação: melhorias na central de mensagens
     * Layout moderno seguindo padrão das abas Avaliações e Fidelidade
     *
     * @param int $client_id ID do cliente.
     */
    private function render_message_center( $client_id ) {
        echo '<section id="mensagens" class="dps-portal-section dps-portal-messages">';
        
        // Header moderno com ícone + título + subtítulo (padrão das outras abas)
        echo '<h2 class="dps-section-title"><span class="dps-section-title__icon">💬</span>' . esc_html__( 'Central de Mensagens', 'dps-client-portal' ) . '</h2>';
        echo '<p class="dps-section-subtitle">' . esc_html__( 'Comunicação direta com a equipe do Banho e Tosa', 'dps-client-portal' ) . '</p>';

        // Busca mensagens do cliente
        $messages = get_posts( [
            'post_type'      => 'dps_portal_message',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_key'       => 'message_client_id',
            'meta_value'     => $client_id,
        ] );

        // Calcula estatísticas
        $total_messages = count( $messages );
        $unread_count   = 0;
        $last_response  = null;

        foreach ( $messages as $msg ) {
            $sender    = get_post_meta( $msg->ID, 'message_sender', true );
            $is_unread = ( 'admin' === $sender && ! get_post_meta( $msg->ID, 'client_read_at', true ) );
            if ( $is_unread ) {
                $unread_count++;
            }
            // Última resposta da equipe
            if ( 'admin' === $sender && ! $last_response ) {
                $last_response = $msg;
            }
        }

        // Cards de métricas de mensagens (layout moderno)
        echo '<div class="dps-messages-metrics">';
        
        // Card: Novas mensagens
        echo '<div class="dps-messages-metric-card' . ( $unread_count > 0 ? ' dps-messages-metric-card--highlight' : '' ) . '">';
        echo '<div class="dps-messages-metric-card__icon">' . ( $unread_count > 0 ? '🔔' : '📨' ) . '</div>';
        echo '<div class="dps-messages-metric-card__content">';
        echo '<span class="dps-messages-metric-card__value">' . esc_html( $unread_count ) . '</span>';
        echo '<span class="dps-messages-metric-card__label">' . esc_html( _n( 'Nova mensagem', 'Novas mensagens', $unread_count, 'dps-client-portal' ) ) . '</span>';
        echo '</div>';
        echo '</div>';
        
        // Card: Total de mensagens
        echo '<div class="dps-messages-metric-card">';
        echo '<div class="dps-messages-metric-card__icon">📋</div>';
        echo '<div class="dps-messages-metric-card__content">';
        echo '<span class="dps-messages-metric-card__value">' . esc_html( $total_messages ) . '</span>';
        echo '<span class="dps-messages-metric-card__label">' . esc_html__( 'Total na conversa', 'dps-client-portal' ) . '</span>';
        echo '</div>';
        echo '</div>';
        
        // Card: Última resposta da equipe
        echo '<div class="dps-messages-metric-card">';
        echo '<div class="dps-messages-metric-card__icon">⏱️</div>';
        echo '<div class="dps-messages-metric-card__content">';
        if ( $last_response ) {
            $last_date = get_post_time( 'd/m/Y', false, $last_response, true );
            echo '<span class="dps-messages-metric-card__value">' . esc_html( $last_date ) . '</span>';
            echo '<span class="dps-messages-metric-card__label">' . esc_html__( 'Última resposta', 'dps-client-portal' ) . '</span>';
        } else {
            echo '<span class="dps-messages-metric-card__value">—</span>';
            echo '<span class="dps-messages-metric-card__label">' . esc_html__( 'Nenhuma resposta', 'dps-client-portal' ) . '</span>';
        }
        echo '</div>';
        echo '</div>';
        
        echo '</div>'; // .dps-messages-metrics

        // Layout em grid: Histórico + Formulário
        echo '<div class="dps-messages-grid">';

        // Coluna 1: Histórico de mensagens (Caixa de Entrada)
        echo '<div class="dps-messages-inbox">';
        echo '<div class="dps-messages-inbox__header">';
        echo '<div class="dps-messages-inbox__icon">📥</div>';
        echo '<div>';
        echo '<h3 class="dps-messages-inbox__title">' . esc_html__( 'Histórico de Mensagens', 'dps-client-portal' ) . '</h3>';
        echo '<p class="dps-messages-inbox__subtitle">' . esc_html__( 'Conversas com a equipe do Banho e Tosa', 'dps-client-portal' ) . '</p>';
        echo '</div>';
        echo '</div>';

        if ( $messages ) {
            echo '<div class="dps-portal-messages__list">';
            foreach ( $messages as $message ) {
                $sender     = get_post_meta( $message->ID, 'message_sender', true );
                $status     = get_post_meta( $message->ID, 'message_status', true );
                $msg_type   = get_post_meta( $message->ID, 'message_type', true );
                $is_unread  = ( 'admin' === $sender && ! get_post_meta( $message->ID, 'client_read_at', true ) );
                $appt_id    = get_post_meta( $message->ID, 'related_appointment_id', true );
                
                $classes = [ 'dps-portal-message' ];
                $classes[] = ( 'client' === $sender ) ? 'dps-portal-message--client' : 'dps-portal-message--admin';
                if ( $is_unread ) {
                    $classes[] = 'dps-portal-message--unread';
                }
                
                echo '<article class="' . esc_attr( implode( ' ', $classes ) ) . '">';

                // Badge de não lida
                if ( $is_unread ) {
                    echo '<span class="dps-portal-message__unread-badge">' . esc_html__( 'Nova', 'dps-client-portal' ) . '</span>';
                }

                // Avatar do remetente
                $avatar_icon = ( 'client' === $sender ) ? '👤' : '🐾';
                echo '<div class="dps-portal-message__avatar">' . esc_html( $avatar_icon ) . '</div>';

                echo '<div class="dps-portal-message__body">';

                $author_label = ( 'client' === $sender )
                    ? esc_html__( 'Você', 'dps-client-portal' )
                    : esc_html__( 'Equipe do Banho e Tosa', 'dps-client-portal' );
                $date_display = get_post_time( 'd/m/Y H:i', false, $message, true );

                echo '<div class="dps-portal-message__meta">';
                echo '<span class="dps-portal-message__author">' . esc_html( $author_label ) . '</span>';
                echo '<span class="dps-portal-message__date">' . esc_html( $date_display ) . '</span>';
                echo '</div>';

                // Tipo de mensagem com ícone
                if ( $msg_type ) {
                    $type_config = [
                        'appointment_confirmation' => [ 'icon' => '✅', 'label' => __( 'Confirmação de Agendamento', 'dps-client-portal' ) ],
                        'appointment_reminder'     => [ 'icon' => '🔔', 'label' => __( 'Lembrete de Agendamento', 'dps-client-portal' ) ],
                        'appointment_change'       => [ 'icon' => '🔄', 'label' => __( 'Mudança de Agendamento', 'dps-client-portal' ) ],
                        'general'                  => [ 'icon' => '💬', 'label' => __( 'Mensagem Geral', 'dps-client-portal' ) ],
                        'access_request'           => [ 'icon' => '🔑', 'label' => __( 'Solicitação de Acesso', 'dps-client-portal' ) ],
                    ];
                    if ( isset( $type_config[ $msg_type ] ) ) {
                        echo '<div class="dps-portal-message__type">';
                        echo '<span class="dps-portal-message__type-icon">' . esc_html( $type_config[ $msg_type ]['icon'] ) . '</span>';
                        echo '<span>' . esc_html( $type_config[ $msg_type ]['label'] ) . '</span>';
                        echo '</div>';
                    }
                }

                // Título da mensagem
                if ( $message->post_title ) {
                    echo '<h4 class="dps-portal-message__title">' . esc_html( $message->post_title ) . '</h4>';
                }

                $content = $message->post_content ? wpautop( esc_html( $message->post_content ) ) : '';
                echo '<div class="dps-portal-message__content">' . $content . '</div>';
                
                // Link para agendamento relacionado
                if ( $appt_id && get_post_status( $appt_id ) ) {
                    echo '<div class="dps-portal-message__action">';
                    echo '<a href="#" class="dps-portal-message__link" data-tab="agendamentos">';
                    echo '📅 ' . esc_html__( 'Ver detalhes do agendamento', 'dps-client-portal' );
                    echo '</a>';
                    echo '</div>';
                }
                
                echo '</div>'; // .dps-portal-message__body
                echo '</article>';

                // Marcar como lida
                if ( $is_unread ) {
                    update_post_meta( $message->ID, 'client_read_at', current_time( 'mysql' ) );
                }
            }
            echo '</div>'; // .dps-portal-messages__list
        } else {
            echo '<div class="dps-messages-empty">';
            echo '<div class="dps-messages-empty__icon">💬</div>';
            echo '<div class="dps-messages-empty__message">' . esc_html__( 'Nenhuma mensagem ainda', 'dps-client-portal' ) . '</div>';
            echo '<p class="dps-messages-empty__hint">' . esc_html__( 'Aqui você receberá confirmações de agendamento, lembretes e respostas da equipe.', 'dps-client-portal' ) . '</p>';
            echo '</div>';
        }

        echo '</div>'; // .dps-messages-inbox

        // Coluna 2: Formulário de nova mensagem
        echo '<div class="dps-messages-compose">';
        echo '<div class="dps-messages-compose__header">';
        echo '<div class="dps-messages-compose__icon">✉️</div>';
        echo '<div>';
        echo '<h3 class="dps-messages-compose__title">' . esc_html__( 'Nova Mensagem', 'dps-client-portal' ) . '</h3>';
        echo '<p class="dps-messages-compose__subtitle">' . esc_html__( 'Envie uma mensagem para a equipe', 'dps-client-portal' ) . '</p>';
        echo '</div>';
        echo '</div>';

        echo '<div class="dps-messages-compose__content">';
        echo '<form method="post" class="dps-portal-form">';
        wp_nonce_field( 'dps_client_portal_action', '_dps_client_portal_nonce' );
        echo '<input type="hidden" name="dps_client_portal_action" value="send_message">';
        
        // Fieldset com instruções
        echo '<fieldset class="dps-messages-compose__fieldset">';
        echo '<legend class="dps-messages-compose__legend">' . esc_html__( 'Detalhes da mensagem', 'dps-client-portal' ) . '</legend>';
        
        echo '<p class="dps-messages-compose__instructions">';
        echo esc_html__( 'Dúvidas, sugestões ou precisa de ajuda? Escreva sua mensagem e responderemos o mais rápido possível.', 'dps-client-portal' );
        echo '</p>';
        
        echo '<div class="dps-form-field">';
        echo '<label for="message_subject">' . esc_html__( 'Assunto', 'dps-client-portal' ) . ' <span class="dps-form-optional">(' . esc_html__( 'opcional', 'dps-client-portal' ) . ')</span></label>';
        echo '<input type="text" id="message_subject" name="message_subject" class="dps-form-control" placeholder="' . esc_attr__( 'Ex: Dúvida sobre agendamento', 'dps-client-portal' ) . '">';
        echo '</div>';
        
        echo '<div class="dps-form-field">';
        echo '<label for="message_body">' . esc_html__( 'Mensagem', 'dps-client-portal' ) . ' <span class="required">*</span></label>';
        echo '<textarea id="message_body" name="message_body" class="dps-form-control" rows="6" required placeholder="' . esc_attr__( 'Escreva sua mensagem aqui...', 'dps-client-portal' ) . '"></textarea>';
        echo '<p class="dps-form-hint">' . esc_html__( 'Respondemos em até 24 horas úteis.', 'dps-client-portal' ) . '</p>';
        echo '</div>';
        
        echo '</fieldset>';
        
        echo '<div class="dps-messages-compose__actions">';
        echo '<button type="submit" class="button button-primary dps-messages-compose__submit">';
        echo '<span class="dps-messages-compose__submit-icon">📤</span>';
        echo '<span>' . esc_html__( 'Enviar mensagem', 'dps-client-portal' ) . '</span>';
        echo '</button>';
        echo '</div>';
        
        echo '</form>';
        echo '</div>'; // .dps-messages-compose__content

        echo '</div>'; // .dps-messages-compose

        echo '</div>'; // .dps-messages-grid

        echo '</section>';
    }

    /**
     * Renderiza a central de avaliações (CTA + prova social).
     *
     * Layout moderno com:
     * - Seção de destaque com métricas visuais
     * - Formulário de avaliação rápida interna
     * - CTA para Google Reviews
     * - Galeria de avaliações com prova social
     *
     * @param int $client_id ID do cliente autenticado.
     */
    private function render_reviews_hub( $client_id ) {
        $review_url      = $this->get_review_url();
        $summary         = $this->get_reviews_summary( 6 );
        $client_reviewed = $this->has_client_reviewed( $client_id );
        $client_name     = get_the_title( $client_id );

        echo '<section class="dps-portal-section dps-portal-reviews">';
        echo '<h2 class="dps-section-title"><span class="dps-section-title__icon">⭐</span>' . esc_html__( 'Central de Avaliações', 'dps-client-portal' ) . '</h2>';
        echo '<p class="dps-section-subtitle">' . esc_html__( 'Sua opinião nos ajuda a melhorar cada vez mais!', 'dps-client-portal' ) . '</p>';

        // Card de métricas resumidas
        echo '<div class="dps-reviews-metrics">';
        $this->render_reviews_metrics_cards( $summary );
        echo '</div>';

        // Layout em duas colunas: formulário + CTA Google
        echo '<div class="dps-reviews-grid">';

        // Coluna 1: Formulário de avaliação rápida interna
        echo '<div class="dps-review-form-card">';
        echo '<div class="dps-review-form-card__header">';
        echo '<div class="dps-review-form-card__icon">💬</div>';
        echo '<div>';
        echo '<h3 class="dps-review-form-card__title">' . esc_html__( 'Avaliação Rápida', 'dps-client-portal' ) . '</h3>';
        echo '<p class="dps-review-form-card__subtitle">' . esc_html__( 'Conte como foi a experiência do seu pet', 'dps-client-portal' ) . '</p>';
        echo '</div>';
        echo '</div>';

        if ( $client_reviewed ) {
            echo '<div class="dps-review-form-card__thanks">';
            echo '<div class="dps-review-form-card__thanks-icon">🎉</div>';
            echo '<p class="dps-review-form-card__thanks-text">' . esc_html__( 'Obrigado por sua avaliação!', 'dps-client-portal' ) . '</p>';
            echo '<p class="dps-review-form-card__thanks-hint">' . esc_html__( 'Sua opinião é muito importante para nós.', 'dps-client-portal' ) . '</p>';
            echo '</div>';
        } else {
            $this->render_internal_review_form( $client_id, $client_name );
        }

        echo '</div>';

        // Coluna 2: CTA para Google Reviews
        echo '<div class="dps-review-google-card">';
        echo '<div class="dps-review-google-card__header">';
        echo '<div class="dps-review-google-card__logo">';
        echo '<span class="dps-google-g">G</span>';
        echo '</div>';
        echo '<div>';
        echo '<h3 class="dps-review-google-card__title">' . esc_html__( 'Avalie no Google', 'dps-client-portal' ) . '</h3>';
        echo '<p class="dps-review-google-card__subtitle">' . esc_html__( 'Ajude outros tutores a nos conhecer', 'dps-client-portal' ) . '</p>';
        echo '</div>';
        echo '</div>';

        echo '<div class="dps-review-google-card__content">';
        echo '<p class="dps-review-google-card__text">' . esc_html__( 'Suas 5 estrelas no Google ajudam outros clientes a confiar em nós e nos mostram onde podemos melhorar.', 'dps-client-portal' ) . '</p>';

        echo '<div class="dps-review-google-card__steps">';
        echo '<div class="dps-review-google-card__step"><span class="dps-step-num">1</span>' . esc_html__( 'Clique no botão', 'dps-client-portal' ) . '</div>';
        echo '<div class="dps-review-google-card__step"><span class="dps-step-num">2</span>' . esc_html__( 'Escolha 1-5 estrelas', 'dps-client-portal' ) . '</div>';
        echo '<div class="dps-review-google-card__step"><span class="dps-step-num">3</span>' . esc_html__( 'Comente (opcional)', 'dps-client-portal' ) . '</div>';
        echo '</div>';
        echo '</div>';

        echo '<div class="dps-review-google-card__actions">';
        if ( $review_url ) {
            echo '<a class="dps-review-google-btn" href="' . esc_url( $review_url ) . '" target="_blank" rel="noopener noreferrer">';
            echo '<span class="dps-review-google-btn__icon">⭐</span>';
            echo '<span class="dps-review-google-btn__text">' . esc_html__( 'Avaliar no Google', 'dps-client-portal' ) . '</span>';
            echo '</a>';
            echo '<p class="dps-review-google-card__hint">' . esc_html__( 'Abre em nova aba • Leva menos de 1 minuto', 'dps-client-portal' ) . '</p>';
        } else {
            echo '<div class="dps-portal-notice dps-portal-notice--info">';
            echo '<p>' . esc_html__( 'Em breve você poderá nos avaliar no Google também!', 'dps-client-portal' ) . '</p>';
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';

        echo '</div>'; // .dps-reviews-grid

        // Seção de prova social - o que outros clientes dizem
        echo '<div class="dps-review-social">';
        echo '<div class="dps-review-social__header">';
        echo '<h3 class="dps-review-social__title">' . esc_html__( 'O que nossos clientes dizem', 'dps-client-portal' ) . '</h3>';
        if ( $summary['count'] > 0 ) {
            $average_label = sprintf(
                /* translators: %s: average rating */
                __( '%s de 5 estrelas', 'dps-client-portal' ),
                number_format_i18n( $summary['average'], 1 )
            );
            echo '<div class="dps-review-social__badge">';
            echo $this->render_star_icons( $summary['average'], $average_label );
            echo '<span class="dps-review-social__badge-text">' . esc_html( number_format_i18n( $summary['average'], 1 ) ) . '</span>';
            echo '</div>';
        }
        echo '</div>';

        if ( ! empty( $summary['items'] ) ) {
            echo '<div class="dps-review-list">';
            foreach ( $summary['items'] as $item ) {
                echo '<article class="dps-review-card">';
                echo '<div class="dps-review-card__stars">';
                $label = sprintf(
                    /* translators: %s: star rating */
                    __( '%s de 5 estrelas', 'dps-client-portal' ),
                    number_format_i18n( $item['rating'], 1 )
                );
                echo $this->render_star_icons( $item['rating'], $label );
                echo '</div>';
                if ( $item['author'] ) {
                    echo '<p class="dps-review-card__author">' . esc_html( $item['author'] ) . '</p>';
                }
                if ( $item['content'] ) {
                    echo '<blockquote class="dps-review-card__quote">"' . esc_html( $item['content'] ) . '"</blockquote>';
                }
                if ( $item['date'] ) {
                    echo '<time class="dps-review-card__date">' . esc_html( $item['date'] ) . '</time>';
                }
                echo '</article>';
            }
            echo '</div>';
        } else {
            echo '<div class="dps-empty-state dps-empty-state--compact">';
            echo '<div class="dps-empty-state__icon">💭</div>';
            echo '<div class="dps-empty-state__message">' . esc_html__( 'Seja o primeiro a deixar uma avaliação!', 'dps-client-portal' ) . '</div>';
            echo '<p class="dps-empty-state__hint">' . esc_html__( 'Sua opinião vai ajudar outros tutores a conhecer nosso trabalho.', 'dps-client-portal' ) . '</p>';
            echo '</div>';
        }

        echo '</div>'; // .dps-review-social
        echo '</section>';
    }

    /**
     * Renderiza cards de métricas das avaliações.
     *
     * @param array $summary Dados resumidos das avaliações.
     */
    private function render_reviews_metrics_cards( $summary ) {
        $average = $summary['average'];
        $count   = $summary['count'];

        echo '<div class="dps-metrics-grid">';

        // Card: Nota Média
        echo '<div class="dps-metric-card dps-metric-card--highlight">';
        echo '<div class="dps-metric-card__icon">⭐</div>';
        echo '<div class="dps-metric-card__content">';
        echo '<span class="dps-metric-card__value">' . esc_html( $count > 0 ? number_format_i18n( $average, 1 ) : '—' ) . '</span>';
        echo '<span class="dps-metric-card__label">' . esc_html__( 'Nota Média', 'dps-client-portal' ) . '</span>';
        echo '</div>';
        echo '</div>';

        // Card: Total de Avaliações
        echo '<div class="dps-metric-card">';
        echo '<div class="dps-metric-card__icon">📝</div>';
        echo '<div class="dps-metric-card__content">';
        echo '<span class="dps-metric-card__value">' . esc_html( number_format_i18n( $count ) ) . '</span>';
        echo '<span class="dps-metric-card__label">' . esc_html( _n( 'Avaliação', 'Avaliações', $count, 'dps-client-portal' ) ) . '</span>';
        echo '</div>';
        echo '</div>';

        // Card: Satisfação (baseado em avaliações 4-5 estrelas)
        $satisfaction = $this->calculate_satisfaction_rate();
        echo '<div class="dps-metric-card">';
        echo '<div class="dps-metric-card__icon">😊</div>';
        echo '<div class="dps-metric-card__content">';
        echo '<span class="dps-metric-card__value">' . esc_html( $satisfaction > 0 ? $satisfaction . '%' : '—' ) . '</span>';
        echo '<span class="dps-metric-card__label">' . esc_html__( 'Satisfação', 'dps-client-portal' ) . '</span>';
        echo '</div>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * Renderiza formulário de avaliação interna rápida.
     *
     * @param int    $client_id   ID do cliente.
     * @param string $client_name Nome do cliente.
     */
    private function render_internal_review_form( $client_id, $client_name ) {
        echo '<form method="post" class="dps-review-internal-form" id="dps-review-internal-form">';
        wp_nonce_field( 'dps_client_portal_action', '_dps_client_portal_nonce' );
        echo '<input type="hidden" name="dps_client_portal_action" value="submit_internal_review">';

        // Seletor de estrelas interativo
        echo '<div class="dps-star-rating-input">';
        echo '<label class="dps-star-rating-label">' . esc_html__( 'Como foi a experiência?', 'dps-client-portal' ) . '</label>';
        echo '<div class="dps-star-rating-selector" role="radiogroup" aria-label="' . esc_attr__( 'Selecione uma nota de 1 a 5 estrelas', 'dps-client-portal' ) . '">';
        for ( $i = 1; $i <= 5; $i++ ) {
            echo '<input type="radio" name="review_rating" value="' . esc_attr( $i ) . '" id="star-' . esc_attr( $i ) . '" class="dps-star-input" required>';
            echo '<label for="star-' . esc_attr( $i ) . '" class="dps-star-label" title="' . esc_attr( sprintf( __( '%d estrela(s)', 'dps-client-portal' ), $i ) ) . '">★</label>';
        }
        echo '</div>';
        echo '<div class="dps-star-rating-hint">' . esc_html__( 'Clique nas estrelas para avaliar', 'dps-client-portal' ) . '</div>';
        echo '</div>';

        // Campo de comentário (opcional)
        echo '<div class="dps-review-comment-field">';
        echo '<label for="review_comment">' . esc_html__( 'Comentário (opcional)', 'dps-client-portal' ) . '</label>';
        echo '<textarea name="review_comment" id="review_comment" rows="3" placeholder="' . esc_attr__( 'Conte como foi a experiência do seu pet...', 'dps-client-portal' ) . '" maxlength="500" class="dps-form-control"></textarea>';
        echo '<span class="dps-char-counter"><span id="char-count">0</span>/500</span>';
        echo '</div>';

        // Botão de envio
        echo '<button type="submit" class="dps-btn-submit-review">';
        echo '<span class="dps-btn-icon">✓</span>';
        echo '<span class="dps-btn-text">' . esc_html__( 'Enviar Avaliação', 'dps-client-portal' ) . '</span>';
        echo '</button>';

        echo '</form>';
    }

    /**
     * Verifica se o cliente já fez uma avaliação interna.
     *
     * @param int $client_id ID do cliente.
     * @return bool True se já avaliou.
     */
    private function has_client_reviewed( $client_id ) {
        if ( ! post_type_exists( 'dps_groomer_review' ) ) {
            return false;
        }

        $reviews = get_posts( [
            'post_type'      => 'dps_groomer_review',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => '_dps_review_client_id',
                    'value' => $client_id,
                ],
            ],
        ] );

        return ! empty( $reviews );
    }

    /**
     * Calcula taxa de satisfação (avaliações 4-5 estrelas).
     *
     * Utiliza cache via transient para evitar recálculos frequentes.
     * Cache é invalidado a cada 10 minutos ou quando uma nova avaliação é adicionada.
     *
     * @return int Percentual de satisfação (0-100).
     */
    private function calculate_satisfaction_rate() {
        if ( ! post_type_exists( 'dps_groomer_review' ) ) {
            return 0;
        }

        // Tenta obter do cache
        $cache_key = 'dps_satisfaction_rate';
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            return (int) $cached;
        }

        $total_reviews = get_posts( [
            'post_type'      => 'dps_groomer_review',
            'post_status'    => 'publish',
            'posts_per_page' => 200, // Limite razoável para performance
            'fields'         => 'ids',
        ] );

        if ( empty( $total_reviews ) ) {
            set_transient( $cache_key, 0, 10 * MINUTE_IN_SECONDS );
            return 0;
        }

        $positive_count = 0;
        foreach ( $total_reviews as $review_id ) {
            $rating_raw = get_post_meta( $review_id, '_dps_review_rating', true );
            // Trata casos onde meta pode ser vazio ou inválido
            $rating = is_numeric( $rating_raw ) ? (int) $rating_raw : 0;
            if ( $rating >= 4 ) {
                $positive_count++;
            }
        }

        $satisfaction = (int) round( ( $positive_count / count( $total_reviews ) ) * 100 );

        // Armazena em cache por 10 minutos
        set_transient( $cache_key, $satisfaction, 10 * MINUTE_IN_SECONDS );

        return $satisfaction;
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
     * Renderiza painel de fidelidade no portal.
     *
     * @param int $client_id ID do cliente autenticado.
     */
    private function render_loyalty_panel( $client_id ) {
        echo '<section class="dps-portal-section dps-portal-loyalty">';

        // Título da seção com ícone para consistência visual
        echo '<h2>🏆 ' . esc_html__( 'Programa de Fidelidade', 'dps-client-portal' ) . '</h2>';

        if ( ! class_exists( 'DPS_Loyalty_API' ) ) {
            echo '<div class="dps-loyalty-inactive">';
            echo '<div class="dps-loyalty-inactive__icon">🎁</div>';
            echo '<p class="dps-loyalty-inactive__message">' . esc_html__( 'O programa de fidelidade não está ativo no momento.', 'dps-client-portal' ) . '</p>';
            echo '<p class="dps-loyalty-inactive__hint">' . esc_html__( 'Em breve você poderá acumular pontos e ganhar recompensas!', 'dps-client-portal' ) . '</p>';
            echo '</div>';
            echo '</section>';
            return;
        }

        $client_name   = get_the_title( $client_id );
        $points        = DPS_Loyalty_API::get_points( $client_id );
        $credit        = DPS_Loyalty_API::get_credit( $client_id );
        $tier          = DPS_Loyalty_API::get_loyalty_tier( $client_id );
        $referral_code = DPS_Loyalty_API::get_referral_code( $client_id );
        $referral_url  = DPS_Loyalty_API::get_referral_url( $client_id );
        $history_limit = 5;
        $history       = DPS_Loyalty_API::get_points_history( $client_id, [ 'limit' => $history_limit, 'offset' => 0 ] );
        $total_logs    = count( get_post_meta( $client_id, 'dps_loyalty_points_log' ) );
        $has_more      = $total_logs > $history_limit;
        $settings      = get_option( 'dps_loyalty_settings', [] );
        $achievement_definitions = DPS_Loyalty_Achievements::get_achievements_definitions();
        $unlocked_achievements  = DPS_Loyalty_Achievements::get_client_achievements( $client_id );

        $credit_display = class_exists( 'DPS_Money_Helper' ) ? 'R$ ' . DPS_Money_Helper::format_to_brazilian( $credit ) : 'R$ ' . number_format( $credit / 100, 2, ',', '.' );
        $progress       = isset( $tier['progress'] ) ? (int) $tier['progress'] : 0;
        $next_points    = isset( $tier['next_points'] ) ? (int) $tier['next_points'] : null;
        $loyalty_nonce  = wp_create_nonce( 'dps_portal_loyalty' );

        $portal_enabled       = ! empty( $settings['enable_portal_redemption'] );
        $portal_min_points    = isset( $settings['portal_min_points_to_redeem'] ) ? absint( $settings['portal_min_points_to_redeem'] ) : 0;
        $points_per_real      = isset( $settings['portal_points_per_real'] ) ? max( 1, absint( $settings['portal_points_per_real'] ) ) : 100;
        $max_discount_cents   = isset( $settings['portal_max_discount_amount'] ) ? (int) $settings['portal_max_discount_amount'] : 0;
        $max_points_by_cap    = $max_discount_cents > 0 ? (int) floor( ( $max_discount_cents / 100 ) * $points_per_real ) : $points;
        $max_points_available = min( $points, $max_points_by_cap );
        $max_discount_display = class_exists( 'DPS_Money_Helper' ) ? DPS_Money_Helper::format_to_brazilian( $max_discount_cents ) : number_format( $max_discount_cents / 100, 2, ',', '.' );

        // Hero Section - Tier e Progresso
        echo '<div class="dps-loyalty-hero" data-loyalty-nonce="' . esc_attr( $loyalty_nonce ) . '" data-history-limit="' . esc_attr( $history_limit ) . '">';
        echo '<div class="dps-loyalty-hero__content">';
        echo '<div class="dps-loyalty-tier">';
        echo '<span class="dps-loyalty-tier__icon">' . esc_html( $tier['icon'] ?? '🏆' ) . '</span>';
        echo '<div class="dps-loyalty-tier__labels">';
        echo '<span class="dps-loyalty-tier__level">' . esc_html__( 'Seu nível atual', 'dps-client-portal' ) . '</span>';
        echo '<span class="dps-loyalty-tier__name">' . esc_html( $tier['label'] ?? __( 'Bronze', 'dps-client-portal' ) ) . '</span>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="dps-loyalty-progress">';
        if ( $next_points ) {
            $remaining = max( 0, $next_points - $points );
            echo '<div class="dps-loyalty-progress__info">';
            echo '<span class="dps-loyalty-progress__current">' . esc_html( number_format( $points, 0, ',', '.' ) ) . ' pts</span>';
            echo '<span class="dps-loyalty-progress__next">' . esc_html( number_format( $next_points, 0, ',', '.' ) ) . ' pts</span>';
            echo '</div>';
            echo '<div class="dps-loyalty-progress__bar"><span style="width: ' . esc_attr( $progress ) . '%"></span></div>';
            echo '<p class="dps-loyalty-progress__hint">' . esc_html( sprintf( __( 'Faltam %s pontos para o próximo nível! 🚀', 'dps-client-portal' ), number_format( $remaining, 0, ',', '.' ) ) ) . '</p>';
        } else {
            echo '<div class="dps-loyalty-progress__info">';
            echo '<span class="dps-loyalty-progress__current">' . esc_html( number_format( $points, 0, ',', '.' ) ) . ' pts</span>';
            echo '</div>';
            echo '<div class="dps-loyalty-progress__bar"><span style="width: 100%"></span></div>';
            echo '<p class="dps-loyalty-progress__hint">' . esc_html__( 'Você está no nível máximo! 🎉', 'dps-client-portal' ) . '</p>';
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // Cards de Estatísticas
        echo '<div class="dps-loyalty-stats">';
        
        // Card: Pontos
        echo '<div class="dps-loyalty-card dps-loyalty-card--points">';
        echo '<div class="dps-loyalty-card__header">';
        echo '<span class="dps-loyalty-card__icon">🎯</span>';
        echo '<p class="dps-loyalty-card__label">' . esc_html__( 'Meus Pontos', 'dps-client-portal' ) . '</p>';
        echo '</div>';
        echo '<p class="dps-loyalty-card__value" data-loyalty-points>' . esc_html( number_format( $points, 0, ',', '.' ) ) . '</p>';
        echo '<button class="dps-button-link dps-loyalty-history-trigger" type="button">' . esc_html__( '📋 Ver histórico', 'dps-client-portal' ) . '</button>';
        echo '</div>';

        // Card: Créditos
        echo '<div class="dps-loyalty-card dps-loyalty-card--credits">';
        echo '<div class="dps-loyalty-card__header">';
        echo '<span class="dps-loyalty-card__icon">💰</span>';
        echo '<p class="dps-loyalty-card__label">' . esc_html__( 'Créditos Disponíveis', 'dps-client-portal' ) . '</p>';
        echo '</div>';
        echo '<p class="dps-loyalty-card__value" data-loyalty-credit>' . esc_html( $credit_display ) . '</p>';
        echo '<small class="dps-loyalty-card__hint">' . esc_html__( 'Use para descontos no próximo atendimento', 'dps-client-portal' ) . '</small>';
        echo '</div>';

        // Card: Indique e Ganhe
        echo '<div class="dps-loyalty-card dps-loyalty-card--referral">';
        echo '<div class="dps-loyalty-card__header">';
        echo '<span class="dps-loyalty-card__icon">🎁</span>';
        echo '<p class="dps-loyalty-card__label">' . esc_html__( 'Indique e Ganhe', 'dps-client-portal' ) . '</p>';
        echo '</div>';
        echo '<p class="dps-loyalty-card__code">' . esc_html( $referral_code ) . '</p>';
        if ( $referral_url ) {
            echo '<div class="dps-loyalty-card__actions">';
            echo '<input type="text" readonly value="' . esc_attr( $referral_url ) . '" class="dps-loyalty-referral-input" aria-label="' . esc_attr__( 'Link de indicação', 'dps-client-portal' ) . '" />';
            echo '<button class="dps-button-link dps-portal-copy" type="button" data-copy-target="' . esc_attr( $referral_url ) . '">📋 ' . esc_html__( 'Copiar', 'dps-client-portal' ) . '</button>';
            echo '</div>';
            echo '<small class="dps-loyalty-card__hint">' . esc_html__( 'Compartilhe e ganhe pontos quando indicados agendarem!', 'dps-client-portal' ) . '</small>';
        }
        echo '</div>';
        echo '</div>';

        // Seção: Como Funciona (educacional)
        echo '<div class="dps-loyalty-how-it-works">';
        echo '<h3>💡 ' . esc_html__( 'Como Funciona', 'dps-client-portal' ) . '</h3>';
        echo '<div class="dps-loyalty-how-it-works__grid">';
        
        echo '<div class="dps-loyalty-step">';
        echo '<span class="dps-loyalty-step__number">1</span>';
        echo '<div class="dps-loyalty-step__content">';
        echo '<strong>' . esc_html__( 'Agende Serviços', 'dps-client-portal' ) . '</strong>';
        echo '<p>' . esc_html__( 'A cada banho ou tosa você acumula pontos automaticamente.', 'dps-client-portal' ) . '</p>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="dps-loyalty-step">';
        echo '<span class="dps-loyalty-step__number">2</span>';
        echo '<div class="dps-loyalty-step__content">';
        echo '<strong>' . esc_html__( 'Suba de Nível', 'dps-client-portal' ) . '</strong>';
        echo '<p>' . esc_html__( 'Quanto mais pontos, maior seu nível e multiplicador de bônus.', 'dps-client-portal' ) . '</p>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="dps-loyalty-step">';
        echo '<span class="dps-loyalty-step__number">3</span>';
        echo '<div class="dps-loyalty-step__content">';
        echo '<strong>' . esc_html__( 'Troque por Créditos', 'dps-client-portal' ) . '</strong>';
        echo '<p>' . esc_html__( 'Converta pontos em créditos e use como desconto.', 'dps-client-portal' ) . '</p>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';

        // Seção: Conquistas
        echo '<div class="dps-loyalty-achievements">';
        echo '<h3>🏅 ' . esc_html__( 'Minhas Conquistas', 'dps-client-portal' ) . '</h3>';
        $unlocked_count = count( $unlocked_achievements );
        $total_achievements = count( $achievement_definitions );
        echo '<p class="dps-loyalty-achievements__summary">';
        echo esc_html( sprintf( __( 'Você desbloqueou %d de %d conquistas', 'dps-client-portal' ), $unlocked_count, $total_achievements ) );
        echo '</p>';
        echo '<div class="dps-loyalty-achievements__grid">';
        foreach ( $achievement_definitions as $key => $achievement ) {
            $unlocked = in_array( $key, $unlocked_achievements, true );
            $card_class = $unlocked ? 'is-unlocked' : 'is-locked';
            echo '<div class="dps-loyalty-achievement ' . esc_attr( $card_class ) . '">';
            echo '<div class="dps-loyalty-achievement__icon">' . ( $unlocked ? '🏅' : '🔒' ) . '</div>';
            echo '<div class="dps-loyalty-achievement__text">';
            echo '<p class="dps-loyalty-achievement__title">' . esc_html( $achievement['label'] ) . '</p>';
            echo '<p class="dps-loyalty-achievement__desc">' . esc_html( $achievement['description'] ) . '</p>';
            echo '<span class="dps-loyalty-achievement__status">' . esc_html( $unlocked ? __( 'Conquistado ✓', 'dps-client-portal' ) : __( 'Em progresso...', 'dps-client-portal' ) ) . '</span>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';

        // Seção: Histórico de Movimentações
        echo '<div class="dps-loyalty-history" data-total="' . esc_attr( $total_logs ) . '" data-limit="' . esc_attr( $history_limit ) . '">';
        echo '<div class="dps-loyalty-history__header">';
        echo '<h3>📊 ' . esc_html__( 'Histórico de Movimentações', 'dps-client-portal' ) . '</h3>';
        echo '</div>';
        if ( ! empty( $history ) ) {
            echo '<ul class="dps-loyalty-history__list">';
            foreach ( $history as $entry ) {
                $context_label = DPS_Loyalty_API::get_context_label( $entry['context'] );
                $formatted_date = date_i18n( 'd/m/Y H:i', strtotime( $entry['date'] ) );
                $sign = ( 'add' === $entry['action'] || 'credit_add' === $entry['action'] ) ? '+' : '-';
                $action_class = ( 'add' === $entry['action'] || 'credit_add' === $entry['action'] ) ? 'add' : 'redeem';
                echo '<li class="dps-loyalty-history__item">';
                echo '<div class="dps-loyalty-history__info">';
                echo '<p class="dps-loyalty-history__context">' . esc_html( $context_label ) . '</p>';
                echo '<span class="dps-loyalty-history__date">' . esc_html( $formatted_date ) . '</span>';
                echo '</div>';
                echo '<span class="dps-loyalty-history__points dps-loyalty-history__points--' . esc_attr( $action_class ) . '">' . esc_html( $sign . number_format( $entry['points'], 0, ',', '.' ) ) . '</span>';
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<div class="dps-loyalty-history__empty">';
            echo '<span class="dps-loyalty-history__empty-icon">📭</span>';
            echo '<p>' . esc_html__( 'Nenhuma movimentação ainda. Agende um serviço para começar a acumular pontos!', 'dps-client-portal' ) . '</p>';
            echo '</div>';
        }

        if ( $has_more ) {
            echo '<button type="button" class="dps-button-secondary dps-loyalty-history-more" data-offset="' . esc_attr( $history_limit ) . '" data-limit="' . esc_attr( $history_limit ) . '" data-nonce="' . esc_attr( $loyalty_nonce ) . '">' . esc_html__( 'Carregar mais', 'dps-client-portal' ) . '</button>';
        }
        echo '</div>';

        // Seção: Resgatar Pontos
        if ( $portal_enabled ) {
            echo '<div class="dps-loyalty-redemption">';
            echo '<h3>🎁 ' . esc_html__( 'Resgatar Pontos', 'dps-client-portal' ) . '</h3>';
            echo '<p class="dps-loyalty-redemption__info">';
            echo esc_html( sprintf( __( 'Conversão: %d pontos = R$ 1,00', 'dps-client-portal' ), $points_per_real ) );
            if ( $max_discount_cents > 0 ) {
                echo ' • ' . esc_html( sprintf( __( 'Máximo por resgate: R$ %s', 'dps-client-portal' ), $max_discount_display ) );
            }
            echo '</p>';

            if ( $max_points_available < max( $portal_min_points, 1 ) ) {
                echo '<div class="dps-loyalty-redemption__unavailable">';
                echo '<span class="dps-loyalty-redemption__unavailable-icon">⏳</span>';
                echo '<p>' . esc_html( sprintf( __( 'Você precisa de pelo menos %d pontos para resgatar. Continue acumulando!', 'dps-client-portal' ), $portal_min_points ) ) . '</p>';
                echo '</div>';
            } else {
                $default_value = max( $portal_min_points, 1 );
                $default_value = min( $default_value, $max_points_available );
                echo '<form class="dps-loyalty-redemption-form" data-nonce="' . esc_attr( $loyalty_nonce ) . '" data-rate="' . esc_attr( $points_per_real ) . '" data-max-cents="' . esc_attr( $max_discount_cents ) . '" data-min-points="' . esc_attr( $portal_min_points ) . '" data-current-points="' . esc_attr( $points ) . '">';
                echo '<div class="dps-loyalty-redemption__form-group">';
                echo '<label for="dps-loyalty-points-input">' . esc_html__( 'Quantidade de pontos para converter', 'dps-client-portal' ) . '</label>';
                echo '<input type="number" id="dps-loyalty-points-input" name="points_to_redeem" min="' . esc_attr( max( $portal_min_points, 1 ) ) . '" max="' . esc_attr( $max_points_available ) . '" step="1" value="' . esc_attr( $default_value ) . '" />';
                echo '<small class="dps-loyalty-redemption__balance">' . esc_html__( 'Saldo disponível: ', 'dps-client-portal' ) . '<strong data-loyalty-points>' . esc_html( number_format( $points, 0, ',', '.' ) ) . '</strong> ' . esc_html__( 'pontos', 'dps-client-portal' ) . '</small>';
                echo '</div>';
                echo '<button type="submit" class="dps-button-primary dps-loyalty-redeem-btn">🎁 ' . esc_html__( 'Resgatar Agora', 'dps-client-portal' ) . '</button>';
                echo '<div class="dps-loyalty-redemption__feedback" aria-live="polite"></div>';
                echo '</form>';
            }
            echo '</div>';
        } else {
            echo '<div class="dps-loyalty-redemption--disabled">';
            echo '<span class="dps-loyalty-redemption--disabled-icon">ℹ️</span>';
            echo '<p>' . esc_html__( 'O resgate de pontos pelo portal está temporariamente indisponível. Entre em contato com nossa equipe para utilizar seus pontos.', 'dps-client-portal' ) . '</p>';
            echo '</div>';
        }

        echo '</section>';
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
            $base_url = '';
            if ( $page_id > 0 ) {
                $permalink = get_permalink( $page_id );
                if ( $permalink && is_string( $permalink ) ) {
                    $base_url = $permalink;
                }
            }
            if ( empty( $base_url ) ) {
                $base_url = site_url( '/cadastro/' );
            }
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
     * Delega para DPS_Portal_Renderer para usar o layout moderno da PR #433.
     *
     * @param int $client_id ID do cliente.
     */
    private function render_update_forms( $client_id ) {
        // Usa o método refatorado do DPS_Portal_Renderer (PR #433)
        DPS_Portal_Renderer::get_instance()->render_update_forms( $client_id );
        
        // Seção de Preferências do Cliente (Fase 4)
        // Renderizado após os formulários principais pois não foi migrado para DPS_Portal_Renderer
        $this->render_client_preferences( $client_id );
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
        $token = trim( (string) get_option( 'dps_mercadopago_access_token', '' ) );
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
     * REMOVIDO: Duplicado com class-dps-portal-admin.php
     * Método mantido apenas para compatibilidade (não é mais chamado)
     * 
     * @deprecated Use DPS_Portal_Admin::register_admin_menu() instead
     */
    public function register_admin_menu() {
        // Não faz nada - menu registrado em DPS_Portal_Admin
    }
    
    /**
     * REMOVIDO: Duplicado com class-dps-portal-admin.php
     * 
     * @deprecated Use DPS_Portal_Admin::render_portal_settings_admin_page() instead
     */
    public function render_portal_settings_admin_page() {
        // Não faz nada - renderização delegada para DPS_Portal_Admin
    }
    
    /**
     * REMOVIDO: Duplicado com class-dps-portal-admin.php
     * 
     * @deprecated Use DPS_Portal_Admin::render_client_logins_admin_page() instead
     */
    public function render_client_logins_admin_page() {
        // Não faz nada - renderização delegada para DPS_Portal_Admin
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
                if ( $page_id ) {
                    $permalink = get_permalink( $page_id );
                    $base_url = ( $permalink && is_string( $permalink ) ) ? $permalink : home_url();
                } else {
                    $base_url = home_url();
                }
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
        $page_link = home_url();
        if ( $page_id ) {
            $permalink = get_permalink( $page_id );
            if ( $permalink && is_string( $permalink ) ) {
                $page_link = $permalink;
            }
        }
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
        ], wp_get_referer() ?: admin_url( 'admin.php?page=desi-pet-shower' ) );
        
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
        $page_link = home_url();
        if ( $page_id ) {
            $permalink = get_permalink( $page_id );
            if ( $permalink && is_string( $permalink ) ) {
                $page_link = $permalink;
            }
        }
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
     * AJAX handler para solicitação de link de acesso por email (auto-envio)
     * 
     * Permite que clientes com email cadastrado solicitem o link de acesso
     * automaticamente. Para clientes sem email, orienta a usar WhatsApp.
     * 
     * Rate limiting: 3 solicitações por hora por IP ou email
     * 
     * @since 2.4.3
     */
    public function ajax_request_access_link_by_email() {
        // Verifica nonce para proteção CSRF
        $nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'dps_request_access_link' ) ) {
            wp_send_json_error( [ 
                'message' => __( 'Sessão expirada. Por favor, recarregue a página e tente novamente.', 'dps-client-portal' ) 
            ] );
        }
        
        // Captura e valida email
        $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        
        if ( empty( $email ) || ! is_email( $email ) ) {
            wp_send_json_error( [ 
                'message' => __( 'Por favor, informe um e-mail válido.', 'dps-client-portal' ) 
            ] );
        }
        
        // Rate limiting por IP (usa método com suporte a proxy)
        $ip = $this->get_client_ip_with_proxy_support();
        $rate_key_ip = 'dps_access_link_ip_' . md5( $ip );
        $rate_key_email = 'dps_access_link_email_' . md5( $email );
        
        // Verifica rate limit por IP (3 solicitações por hora)
        $ip_count = get_transient( $rate_key_ip );
        if ( false === $ip_count ) {
            $ip_count = 0;
        }
        
        if ( $ip_count >= 3 ) {
            wp_send_json_error( [ 
                'message' => __( 'Você já solicitou o link várias vezes. Aguarde alguns minutos antes de tentar novamente.', 'dps-client-portal' ) 
            ] );
        }
        
        // Verifica rate limit por email (3 solicitações por hora)
        $email_count = get_transient( $rate_key_email );
        if ( false === $email_count ) {
            $email_count = 0;
        }
        
        if ( $email_count >= 3 ) {
            wp_send_json_error( [ 
                'message' => __( 'Você já solicitou o link várias vezes para este e-mail. Verifique sua caixa de entrada (e spam).', 'dps-client-portal' ) 
            ] );
        }
        
        // Busca cliente pelo email
        $clients = get_posts( [
            'post_type'      => 'dps_cliente',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => 'client_email',
                    'value'   => $email,
                    'compare' => '=',
                ],
            ],
        ] );
        
        // Incrementa contadores de rate limit antes de verificar resultado
        // (evita brute force para descobrir emails cadastrados)
        set_transient( $rate_key_ip, $ip_count + 1, HOUR_IN_SECONDS );
        set_transient( $rate_key_email, $email_count + 1, HOUR_IN_SECONDS );
        
        if ( empty( $clients ) ) {
            // Não revelar se email existe ou não por segurança
            // Mensagem genérica que orienta para WhatsApp
            wp_send_json_error( [ 
                'message' => __( 'Não encontramos um cadastro com este e-mail. Por favor, entre em contato via WhatsApp para solicitar acesso.', 'dps-client-portal' ),
                'show_whatsapp' => true
            ] );
        }
        
        $client_id = $clients[0];
        $client_name = get_the_title( $client_id );
        
        // Gera token de acesso
        $token_manager = DPS_Portal_Token_Manager::get_instance();
        $token_plain   = $token_manager->generate_token( $client_id, 'login' );
        
        if ( false === $token_plain ) {
            wp_send_json_error( [ 
                'message' => __( 'Não foi possível gerar o link de acesso. Por favor, tente novamente ou entre em contato via WhatsApp.', 'dps-client-portal' ),
                'show_whatsapp' => true
            ] );
        }
        
        // Gera URL de acesso
        $access_url = $token_manager->generate_access_url( $token_plain );
        
        // Monta email HTML moderno
        $safe_client_name = wp_strip_all_tags( $client_name );
        $subject = __( 'Seu link de acesso ao Portal do Cliente - desi.pet by PRObst', 'dps-client-portal' );
        
        $site_name = get_bloginfo( 'name' );
        
        // Template HTML do email
        $body = $this->get_access_link_email_html( $safe_client_name, $access_url, $site_name );
        
        // Envia email
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        
        if ( class_exists( 'DPS_Communications_API' ) ) {
            $comm_api = DPS_Communications_API::get_instance();
            $sent     = $comm_api->send_email( 
                $email, 
                $subject, 
                $body, 
                [
                    'type'      => 'portal_access_link',
                    'client_id' => $client_id,
                ]
            );
        } else {
            $sent = wp_mail( $email, $subject, $body, $headers );
        }
        
        // Registra em log
        if ( function_exists( 'dps_log' ) ) {
            dps_log( 'Portal access link sent via email', [
                'client_id' => $client_id,
                'email'     => $email,
                'ip'        => $ip,
                'sent'      => $sent,
            ], 'info', 'client-portal' );
        }
        
        if ( ! $sent ) {
            wp_send_json_error( [ 
                'message' => __( 'Não foi possível enviar o e-mail. Por favor, tente novamente ou entre em contato via WhatsApp.', 'dps-client-portal' ),
                'show_whatsapp' => true
            ] );
        }
        
        wp_send_json_success( [ 
            'message' => __( 'Link enviado com sucesso! Verifique sua caixa de entrada (e a pasta de spam).', 'dps-client-portal' ) 
        ] );
    }

    /**
     * Gera o HTML do email de link de acesso ao portal.
     *
     * Template responsivo e moderno para email com link de acesso.
     *
     * @since 2.4.4
     * @param string $client_name Nome do cliente (já sanitizado)
     * @param string $access_url  URL de acesso com token
     * @param string $site_name   Nome do site
     * @return string HTML do email
     */
    private function get_access_link_email_html( $client_name, $access_url, $site_name ) {
        $escaped_url = esc_url( $access_url );
        $escaped_name = esc_html( $client_name );
        $escaped_site = esc_html( $site_name );
        
        $current_year = date( 'Y' );
        $validity_text = esc_html__( 'Este link é válido por 30 minutos e pode ser usado apenas uma vez.', 'dps-client-portal' );
        $access_button_text = esc_html__( 'Acessar Meu Portal', 'dps-client-portal' );
        $security_note = esc_html__( 'Se você não solicitou este acesso, ignore este e-mail.', 'dps-client-portal' );
        $greeting = sprintf( esc_html__( 'Olá, %s!', 'dps-client-portal' ), $escaped_name );
        $intro_text = esc_html__( 'Você solicitou acesso ao Portal do Cliente. Clique no botão abaixo para acessar:', 'dps-client-portal' );
        $alt_link_text = esc_html__( 'Se o botão não funcionar, copie e cole este link no navegador:', 'dps-client-portal' );
        $footer_text = sprintf( esc_html__( 'Equipe %s', 'dps-client-portal' ), $escaped_site );
        
        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{$escaped_site}</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f3f4f6; -webkit-font-smoothing: antialiased;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" width="100%" style="max-width: 600px; margin: 0 auto;">
        <tr>
            <td style="padding: 40px 20px;">
                <!-- Card Principal -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: #ffffff; border-radius: 16px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);">
                    <!-- Header com Logo -->
                    <tr>
                        <td style="padding: 40px 40px 24px; text-align: center;">
                            <div style="font-size: 48px; margin-bottom: 16px;">🐾</div>
                            <h1 style="margin: 0; font-size: 24px; font-weight: 600; color: #1f2937;">{$escaped_site}</h1>
                        </td>
                    </tr>
                    
                    <!-- Conteúdo -->
                    <tr>
                        <td style="padding: 0 40px 32px;">
                            <p style="margin: 0 0 16px; font-size: 18px; font-weight: 600; color: #374151;">{$greeting}</p>
                            <p style="margin: 0 0 24px; font-size: 16px; line-height: 1.6; color: #6b7280;">{$intro_text}</p>
                            
                            <!-- Botão CTA -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" width="100%">
                                <tr>
                                    <td style="text-align: center; padding: 8px 0 24px;">
                                        <a href="{$escaped_url}" target="_blank" style="display: inline-block; background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 600; padding: 16px 48px; border-radius: 12px; box-shadow: 0 4px 12px rgba(14, 165, 233, 0.35);">{$access_button_text}</a>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Aviso de Validade -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: #fef3c7; border-radius: 8px; border-left: 4px solid #f59e0b;">
                                <tr>
                                    <td style="padding: 16px;">
                                        <p style="margin: 0; font-size: 14px; color: #92400e; line-height: 1.5;">
                                            <strong>⏱️ Atenção:</strong> {$validity_text}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Link alternativo -->
                    <tr>
                        <td style="padding: 0 40px 32px;">
                            <p style="margin: 0 0 8px; font-size: 13px; color: #9ca3af;">{$alt_link_text}</p>
                            <p style="margin: 0; font-size: 12px; color: #0ea5e9; word-break: break-all;">
                                <a href="{$escaped_url}" style="color: #0ea5e9; text-decoration: underline;">{$escaped_url}</a>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Aviso de Segurança -->
                    <tr>
                        <td style="padding: 0 40px 32px;">
                            <p style="margin: 0; font-size: 13px; color: #9ca3af; text-align: center;">
                                🔒 {$security_note}
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 24px 40px; background: #f9fafb; border-radius: 0 0 16px 16px; text-align: center;">
                            <p style="margin: 0; font-size: 14px; color: #6b7280;">{$footer_text}</p>
                            <p style="margin: 8px 0 0; font-size: 12px; color: #9ca3af;">© {$current_year} {$escaped_site}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    /**
     * AJAX handler para exportar histórico do pet em formato para impressão/PDF.
     * Funcionalidade 3: Export para PDF
     *
     * @since 2.5.0
     */
    public function ajax_export_pet_history_pdf() {
        // Verifica nonce
        if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'dps_portal_export_pdf' ) ) {
            wp_die( esc_html__( 'Erro de segurança. Por favor, recarregue a página e tente novamente.', 'dps-client-portal' ), 403 );
        }

        // Obtém IDs
        $pet_id    = isset( $_GET['pet_id'] ) ? absint( $_GET['pet_id'] ) : 0;
        $client_id = isset( $_GET['client_id'] ) ? absint( $_GET['client_id'] ) : 0;

        if ( 0 === $pet_id || 0 === $client_id ) {
            wp_die( esc_html__( 'Parâmetros inválidos.', 'dps-client-portal' ), 400 );
        }

        // Verifica se o pet pertence ao cliente (segurança)
        $pet_client_id = get_post_meta( $pet_id, 'pet_client_id', true );
        if ( absint( $pet_client_id ) !== $client_id ) {
            wp_die( esc_html__( 'Acesso não autorizado.', 'dps-client-portal' ), 403 );
        }

        // Renderiza página de impressão
        $renderer = DPS_Portal_Renderer::get_instance();
        $renderer->render_pet_history_print_page( $pet_id, $client_id );
        exit;
    }
    
    /**
     * Obtém o IP do cliente com suporte a proxies
     *
     * Verifica headers de proxy (Cloudflare, AWS, Nginx) e valida IPv4/IPv6
     *
     * @since 2.4.3
     * @return string IP do cliente ou 'unknown'
     */
    private function get_client_ip_with_proxy_support() {
        // Headers a verificar, em ordem de prioridade
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_REAL_IP',        // Nginx proxy
            'HTTP_X_FORWARDED_FOR',  // Proxy padrão
            'REMOTE_ADDR',           // Direto
        ];
        
        foreach ( $headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $raw_ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
                
                // X-Forwarded-For pode ter múltiplos IPs (client, proxy1, proxy2)
                // Pega o primeiro (cliente real)
                if ( strpos( (string) $raw_ip, ',' ) !== false ) {
                    $ips = explode( ',', $raw_ip );
                    $client_ip = trim( $ips[0] );
                } else {
                    $client_ip = $raw_ip;
                }
                
                // Valida IPv4 ou IPv6
                if ( filter_var( $client_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
                    return $client_ip;
                }
                
                if ( filter_var( $client_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
                    return $client_ip;
                }
            }
        }
        
        return 'unknown';
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
     * Renderiza seção de preferências do cliente.
     * Fase 4 - continuação: Preferências
     *
     * @since 2.4.0
     * @param int $client_id ID do cliente.
     */
    private function render_client_preferences( $client_id ) {
        // Busca preferências salvas
        $contact_preference = get_post_meta( $client_id, 'client_contact_preference', true );
        $period_preference  = get_post_meta( $client_id, 'client_period_preference', true );

        echo '<div class="dps-preferences-section">';
        echo '<h3>⚙️ ' . esc_html__( 'Minhas Preferências', 'dps-client-portal' ) . '</h3>';
        echo '<p class="dps-preferences-section__description">' . esc_html__( 'Personalize sua experiência no Banho e Tosa', 'dps-client-portal' ) . '</p>';
        
        echo '<form method="post" class="dps-portal-form">';
        wp_nonce_field( 'dps_client_portal_action', '_dps_client_portal_nonce' );
        echo '<input type="hidden" name="dps_client_portal_action" value="update_client_preferences">';
        
        echo '<div class="dps-preferences-grid">';
        
        // Canal de contato preferido
        echo '<div class="dps-preference-item">';
        echo '<label class="dps-preference-item__label" for="contact_preference">';
        echo esc_html__( 'Como prefere ser contatado?', 'dps-client-portal' );
        echo '</label>';
        echo '<select id="contact_preference" name="contact_preference" class="dps-form-control">';
        echo '<option value="">' . esc_html__( 'Sem preferência', 'dps-client-portal' ) . '</option>';
        echo '<option value="whatsapp"' . selected( $contact_preference, 'whatsapp', false ) . '>' . esc_html__( 'WhatsApp', 'dps-client-portal' ) . '</option>';
        echo '<option value="phone"' . selected( $contact_preference, 'phone', false ) . '>' . esc_html__( 'Telefone', 'dps-client-portal' ) . '</option>';
        echo '<option value="email"' . selected( $contact_preference, 'email', false ) . '>' . esc_html__( 'E-mail', 'dps-client-portal' ) . '</option>';
        echo '</select>';
        echo '</div>';
        
        // Período preferido
        echo '<div class="dps-preference-item">';
        echo '<label class="dps-preference-item__label" for="period_preference">';
        echo esc_html__( 'Período preferido para banho/tosa', 'dps-client-portal' );
        echo '</label>';
        echo '<select id="period_preference" name="period_preference" class="dps-form-control">';
        echo '<option value="">' . esc_html__( 'Sem preferência', 'dps-client-portal' ) . '</option>';
        echo '<option value="morning"' . selected( $period_preference, 'morning', false ) . '>' . esc_html__( 'Manhã', 'dps-client-portal' ) . '</option>';
        echo '<option value="afternoon"' . selected( $period_preference, 'afternoon', false ) . '>' . esc_html__( 'Tarde', 'dps-client-portal' ) . '</option>';
        echo '<option value="flexible"' . selected( $period_preference, 'flexible', false ) . '>' . esc_html__( 'Indiferente', 'dps-client-portal' ) . '</option>';
        echo '</select>';
        echo '</div>';
        
        echo '</div>'; // .dps-preferences-grid
        
        echo '<div class="dps-preferences-actions">';
        echo '<button type="submit" class="button button-primary">';
        echo esc_html__( 'Salvar Preferências', 'dps-client-portal' );
        echo '</button>';
        echo '</div>';
        
        echo '</form>';
        echo '</div>'; // .dps-preferences-section
    }


    /**
     * Renderiza timeline de serviços por pet.
     * Fase 4: Timeline de Serviços
     * Revisão completa do layout: Janeiro 2026
     *
     * @since 2.4.0
     * @param int $client_id ID do cliente.
     */
    private function render_pets_timeline( $client_id ) {
        $pet_repo = DPS_Pet_Repository::get_instance();
        $pets     = $pet_repo->get_pets_by_client( $client_id );
        $renderer = DPS_Portal_Renderer::get_instance();

        // Renderiza cabeçalho da aba com métricas globais
        $renderer->render_pet_history_header( $client_id, $pets );

        if ( empty( $pets ) ) {
            echo '<section class="dps-portal-section dps-portal-pet-history-empty">';
            echo '<div class="dps-empty-state dps-empty-state--large">';
            echo '<div class="dps-empty-state__illustration">🐾</div>';
            echo '<h3 class="dps-empty-state__title">' . esc_html__( 'Nenhum pet cadastrado ainda', 'dps-client-portal' ) . '</h3>';
            echo '<p class="dps-empty-state__message">' . esc_html__( 'Cadastre seus pets para acompanhar o histórico de serviços realizados.', 'dps-client-portal' ) . '</p>';
            // CTA para contato
            if ( class_exists( 'DPS_WhatsApp_Helper' ) ) {
                $whatsapp_url = DPS_WhatsApp_Helper::get_link_to_team( __( 'Olá! Gostaria de cadastrar meu pet.', 'dps-client-portal' ) );
            } else {
                $whatsapp_number = get_option( 'dps_whatsapp_number', '5515991606299' );
                if ( class_exists( 'DPS_Phone_Helper' ) ) {
                    $whatsapp_number = DPS_Phone_Helper::format_for_whatsapp( $whatsapp_number );
                }
                $whatsapp_url = 'https://wa.me/' . $whatsapp_number . '?text=' . urlencode( 'Olá! Gostaria de cadastrar meu pet.' );
            }
            echo '<a href="' . esc_url( $whatsapp_url ) . '" target="_blank" class="dps-empty-state__action button button-primary">';
            echo '💬 ' . esc_html__( 'Falar com a Equipe', 'dps-client-portal' );
            echo '</a>';
            echo '</div>';
            echo '</section>';
            return;
        }

        // Renderiza navegação por abas quando há múltiplos pets
        if ( count( $pets ) > 1 ) {
            $renderer->render_pet_tabs_navigation( $pets );
        }

        // Container principal com timelines dos pets
        echo '<div class="dps-pet-timelines-container">';
        
        foreach ( $pets as $index => $pet ) {
            $is_first = ( 0 === $index );
            $renderer->render_pet_service_timeline( $pet->ID, $client_id, 10, $is_first, count( $pets ) > 1 );
        }
        
        echo '</div>';
    }

    /**
     * Ajusta o brilho de uma cor hexadecimal.
     * Fase 4 - Branding: Helper para cores
     *
     * @param string $hex   Cor hexadecimal (#RRGGBB).
     * @param int    $steps Quantidade de brilho a ajustar (negativo escurece, positivo clareia).
     * @return string Cor ajustada em hexadecimal.
     */
    private function adjust_brightness( $hex, $steps ) {
        // Convert hex to RGB (cast to string for PHP 8.1+ compatibility)
        $hex = str_replace( '#', '', (string) $hex );
        if ( strlen( $hex ) === 3 ) {
            $hex = str_repeat( substr( $hex, 0, 1 ), 2 ) . str_repeat( substr( $hex, 1, 1 ), 2 ) . str_repeat( substr( $hex, 2, 1 ), 2 );
        }
        
        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );
        
        // Ajusta
        $r = max( 0, min( 255, $r + $steps ) );
        $g = max( 0, min( 255, $g + $steps ) );
        $b = max( 0, min( 255, $b + $steps ) );
        
        // Converte de volta para hex
        return '#' . str_pad( dechex( $r ), 2, '0', STR_PAD_LEFT ) . str_pad( dechex( $g ), 2, '0', STR_PAD_LEFT ) . str_pad( dechex( $b ), 2, '0', STR_PAD_LEFT );
    }
}
endif;
