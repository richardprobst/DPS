<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Gerenciador de interface administrativa do portal do cliente.
 * 
 * Esta classe é responsável por todas as funcionalidades administrativas do portal:
 * - Registro de CPT de mensagens
 * - Metaboxes e colunas customizadas
 * - Páginas administrativas de configuração
 * - Gestão de logins/tokens
 * 
 * @since 3.0.0
 */
class DPS_Portal_Admin {

    /**
     * Instância única da classe (singleton).
     *
     * @var DPS_Portal_Admin|null
     */
    private static $instance = null;

    /**
     * Repositório de clientes.
     *
     * @var DPS_Client_Repository
     */
    private $client_repository;

    /**
     * Recupera a instância única (singleton).
     *
     * @return DPS_Portal_Admin
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado (singleton).
     */
    private function __construct() {
        $this->client_repository = DPS_Client_Repository::get_instance();
        
        // Registra hooks administrativos
        // NOTA: Se o hook 'init' já executou, chamamos diretamente
        if ( did_action( 'init' ) ) {
            $this->register_message_post_type();
        } else {
            add_action( 'init', [ $this, 'register_message_post_type' ] );
        }
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ], 20 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        
        // Metaboxes e colunas para mensagens
        add_action( 'add_meta_boxes_dps_portal_message', [ $this, 'add_message_meta_boxes' ] );
        add_action( 'save_post_dps_portal_message', [ $this, 'save_message_meta' ], 10, 3 );
        add_filter( 'manage_dps_portal_message_posts_columns', [ $this, 'add_message_columns' ] );
        add_action( 'manage_dps_portal_message_posts_custom_column', [ $this, 'render_message_column' ], 10, 2 );
        add_filter( 'manage_edit-dps_portal_message_sortable_columns', [ $this, 'make_message_columns_sortable' ] );
        
        // Abas no front-end via shortcode [dps_configuracoes]
        add_action( 'dps_settings_nav_tabs', [ $this, 'render_portal_settings_tab' ], 15, 1 );
        add_action( 'dps_settings_sections', [ $this, 'render_portal_settings_section' ], 15, 1 );
        add_action( 'dps_settings_nav_tabs', [ $this, 'render_logins_tab' ], 20, 1 );
        add_action( 'dps_settings_sections', [ $this, 'render_logins_section' ], 20, 1 );
        add_action( 'dps_settings_nav_tabs', [ $this, 'render_branding_tab' ], 25, 1 ); // Fase 4 - branding
        add_action( 'dps_settings_sections', [ $this, 'render_branding_section' ], 25, 1 ); // Fase 4 - branding
    }

    /**
     * Registra o Custom Post Type para mensagens do portal.
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
     * Registra menu administrativo.
     * 
     * NOTA: Menus exibidos como submenus de "desi.pet by PRObst" para alinhamento com a navegação unificada.
     * Também acessíveis pelo hub em dps-portal-hub via abas.
     */
    public function register_admin_menu() {
        // Submenu: Portal do Cliente - Configurações (oculto)
        add_submenu_page(
            'desi-pet-shower',
            __( 'Portal do Cliente - Configurações', 'dps-client-portal' ),
            __( 'Portal do Cliente', 'dps-client-portal' ),
            'manage_options',
            'dps-client-portal-settings',
            [ $this, 'render_portal_settings_admin_page' ]
        );
        
        // Submenu: Logins de Clientes (oculto)
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
     * Enfileira assets administrativos.
     *
     * @param string $hook_suffix Sufixo do hook da página atual.
     */
    public function enqueue_admin_assets( $hook_suffix ) {
        // Carrega CSS específico na listagem de mensagens
        if ( 'edit.php' === $hook_suffix && isset( $_GET['post_type'] ) && 'dps_portal_message' === $_GET['post_type'] ) {
            $this->enqueue_message_list_styles();
        }
    }

    /**
     * Enfileira estilos para a listagem de mensagens.
     */
    private function enqueue_message_list_styles() {
        wp_add_inline_style( 'wp-admin', '
            .dps-message-sender--client { color: #0073aa; font-weight: 500; }
            .dps-message-sender--admin { color: #00a32a; font-weight: 500; }
            .dps-message-status { padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: 500; }
            .dps-message-status--open { background: #fff3cd; color: #856404; }
            .dps-message-status--answered { background: #d1ecf1; color: #0c5460; }
            .dps-message-status--closed { background: #d4edda; color: #155724; }
        ' );
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

        // Usa repositório para buscar clientes
        $clients = $this->client_repository->get_clients();

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
                $this->render_client_column( $post_id );
                break;

            case 'message_sender':
                $this->render_sender_column( $post_id );
                break;

            case 'message_status':
                $this->render_status_column( $post_id );
                break;
        }
    }

    /**
     * Renderiza coluna de cliente.
     *
     * @param int $post_id ID do post.
     */
    private function render_client_column( $post_id ) {
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
    }

    /**
     * Renderiza coluna de remetente.
     *
     * @param int $post_id ID do post.
     */
    private function render_sender_column( $post_id ) {
        $sender = get_post_meta( $post_id, 'message_sender', true );
        if ( 'client' === $sender ) {
            echo '<span class="dps-message-sender dps-message-sender--client">📤 ' . esc_html__( 'Cliente', 'dps-client-portal' ) . '</span>';
        } else {
            echo '<span class="dps-message-sender dps-message-sender--admin">📥 ' . esc_html__( 'Equipe', 'dps-client-portal' ) . '</span>';
        }
    }

    /**
     * Renderiza coluna de status.
     *
     * @param int $post_id ID do post.
     */
    private function render_status_column( $post_id ) {
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
        if ( ! $update && 'admin' === $sender && $client_id ) {
            $this->notify_client_of_admin_message( $post_id, $client_id, $post );
        }
    }

    /**
     * Notifica o cliente quando a equipe envia uma mensagem via admin.
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
        $subject = __( 'Nova mensagem da equipe desi.pet by PRObst para você', 'dps-client-portal' );
        $body    = sprintf(
            __( "Olá %s,\n\nVocê recebeu uma nova mensagem da equipe desi.pet by PRObst:\n\n%s\n\nAcesse seu portal para responder:\n%s", 'dps-client-portal' ),
            $client_name,
            wp_strip_all_tags( $post->post_content ),
            dps_get_portal_page_url()
        );

        wp_mail( $client_email, $subject, $body );
    }

    /**
     * Renderiza a página administrativa de configurações do portal.
     */
    public function render_portal_settings_admin_page() {
        echo '<div class="wrap">';
        $base_url = menu_page_url( 'dps-client-portal-settings', false );
        $this->render_portal_settings_page( $base_url );
        echo '</div>';
    }

    /**
     * Renderiza a página administrativa de logins.
     */
    public function render_client_logins_admin_page() {
        $this->render_client_logins_page( 'admin', '' );
    }

    /**
     * Renderiza a página de administração dos logins de clientes.
     *
     * @param string $context  Contexto de renderização ('admin' ou 'frontend').
     * @param string $base_url URL base para ações.
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
        $feedback_messages = $this->get_feedback_messages();

        // Busca clientes
        $search = isset( $_GET['dps_search'] ) ? sanitize_text_field( wp_unslash( $_GET['dps_search'] ) ) : '';
        $clients = $this->get_clients_with_token_stats( $search );

        // Carrega template
        $template_path = DPS_CLIENT_PORTAL_ADDON_DIR . 'templates/admin-logins.php';
        
        if ( file_exists( $template_path ) ) {
            include $template_path;
        } else {
            echo '<p>' . esc_html__( 'Template não encontrado.', 'dps-client-portal' ) . '</p>';
        }
    }

    /**
     * Obtém mensagens de feedback da URL.
     *
     * @return array Array de mensagens de feedback.
     */
    private function get_feedback_messages() {
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

        return $feedback_messages;
    }

    /**
     * Busca clientes com estatísticas de tokens.
     *
     * @param string $search Termo de busca.
     * @return array Array de clientes com dados.
     */
    private function get_clients_with_token_stats( $search = '' ) {
        // Usa repositório para buscar clientes
        $clients_posts = $this->client_repository->get_clients( [
            'search' => $search,
        ] );
        
        $token_manager = DPS_Portal_Token_Manager::get_instance();
        $clients       = [];

        foreach ( $clients_posts as $client ) {
            $clients[] = [
                'id'          => $client->ID,
                'name'        => get_the_title( $client->ID ),
                'phone'       => get_post_meta( $client->ID, 'client_phone', true ),
                'email'       => get_post_meta( $client->ID, 'client_email', true ),
                'token_stats' => $token_manager->get_client_stats( $client->ID ),
            ];
        }

        return $clients;
    }

    /**
     * Renderiza a aba "Portal" na navegação do front-end.
     *
     * @param bool $visitor_only Se deve exibir apenas para visitantes.
     */
    public function render_portal_settings_tab( $visitor_only = false ) {
        if ( $visitor_only ) {
            return;
        }
        echo '<li><a href="#" class="dps-tab-link" data-tab="portal">' . esc_html__( 'Portal', 'dps-client-portal' ) . '</a></li>';
    }

    /**
     * Renderiza o conteúdo da seção "Portal" no front-end.
     *
     * @param bool $visitor_only Se deve exibir apenas para visitantes.
     */
    public function render_portal_settings_section( $visitor_only = false ) {
        if ( $visitor_only || ! current_user_can( 'manage_options' ) ) {
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
     * Renderiza a aba "Logins" na navegação do front-end.
     *
     * @param bool $visitor_only Se deve exibir apenas para visitantes.
     */
    public function render_logins_tab( $visitor_only = false ) {
        if ( $visitor_only ) {
            return;
        }
        echo '<li><a href="#" class="dps-tab-link" data-tab="logins">' . esc_html__( 'Logins', 'dps-client-portal' ) . '</a></li>';
    }

    /**
     * Renderiza o conteúdo da seção "Logins" no front-end.
     *
     * @param bool $visitor_only Se deve exibir apenas para visitantes.
     */
    public function render_logins_section( $visitor_only = false ) {
        if ( $visitor_only || ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        echo '<div class="dps-section" id="dps-section-logins">';
        $this->render_client_logins_page( 'frontend', '' );
        echo '</div>';
    }

    /**
     * Renderiza a página de configurações do portal.
     *
     * @param string $base_url URL base da página.
     */
    public function render_portal_settings_page( $base_url = '' ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            echo '<div class="dps-portal-settings-restricted">';
            echo '<p>' . esc_html__( 'Você não tem permissão para alterar as configurações do portal.', 'dps-client-portal' ) . '</p>';
            echo '</div>';
            return;
        }

        $feedback_messages = [];
        $saved_param       = isset( $_GET['dps_portal_settings_saved'] ) ? sanitize_text_field( wp_unslash( $_GET['dps_portal_settings_saved'] ) ) : '';

        if ( '1' === $saved_param ) {
            $feedback_messages[] = [
                'type' => 'success',
                'text' => __( 'Configurações do portal salvas com sucesso!', 'dps-client-portal' ),
            ];
        }

        $base_url = $base_url ? $base_url : menu_page_url( 'dps-client-portal-settings', false );

        $portal_page_id = (int) get_option( 'dps_portal_page_id', 0 );
        $portal_url     = dps_get_portal_page_url();

        if ( ! is_string( $portal_url ) ) {
            $portal_url = '';
        }

        $pages = get_pages(
            [
                'post_status' => 'publish',
                'sort_column' => 'post_title',
            ]
        );

        if ( ! is_array( $pages ) ) {
            $pages = [];
        }

        // Carrega template
        $template_path = DPS_CLIENT_PORTAL_ADDON_DIR . 'templates/portal-settings.php';

        if ( file_exists( $template_path ) ) {
            include $template_path;
        } else {
            echo '<p>' . esc_html__( 'Template não encontrado.', 'dps-client-portal' ) . '</p>';
        }
    }

    /**
     * Renderiza aba de Branding na navegação (Fase 4 - continuação).
     *
     * @param bool $visitor_only Se deve exibir apenas para visitantes.
     */
    public function render_branding_tab( $visitor_only = false ) {
        if ( $visitor_only ) {
            return;
        }
        echo '<li><a href="#" class="dps-tab-link" data-tab="branding">🎨 ' . esc_html__( 'Branding', 'dps-client-portal' ) . '</a></li>';
    }

    /**
     * Renderiza seção de Branding (Fase 4 - continuação).
     *
     * @param bool $visitor_only Se deve exibir apenas para visitantes.
     */
    public function render_branding_section( $visitor_only = false ) {
        if ( $visitor_only || ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        // Processa salvamento se houver POST
        if ( isset( $_POST['dps_branding_save'] ) && check_admin_referer( 'dps_branding_settings', 'dps_branding_nonce' ) ) {
            $this->save_branding_settings();
        }
        
        // Busca configurações atuais
        $logo_id       = get_option( 'dps_portal_logo_id', '' );
        $primary_color = get_option( 'dps_portal_primary_color', '#0ea5e9' );
        $hero_id       = get_option( 'dps_portal_hero_id', '' );
        
        echo '<div class="dps-section" id="dps-section-branding">';
        echo '<h2>🎨 ' . esc_html__( 'Personalização Visual do Portal', 'dps-client-portal' ) . '</h2>';
        echo '<p class="dps-section__description">' . esc_html__( 'Customize a aparência do Portal do Cliente com a identidade visual do seu Banho e Tosa', 'dps-client-portal' ) . '</p>';
        
        echo '<form method="post" enctype="multipart/form-data" class="dps-branding-form">';
        wp_nonce_field( 'dps_branding_settings', 'dps_branding_nonce' );
        
        // Logo
        echo '<div class="dps-form-field">';
        echo '<label class="dps-form-label"><strong>' . esc_html__( 'Logo do Banho e Tosa', 'dps-client-portal' ) . '</strong></label>';
        echo '<p class="description">' . esc_html__( 'Aparecerá no cabeçalho do portal. Tamanho recomendado: 200x80px', 'dps-client-portal' ) . '</p>';
        
        if ( $logo_id ) {
            $logo_url = wp_get_attachment_image_url( $logo_id, 'medium' );
            if ( $logo_url ) {
                echo '<div class="dps-branding-preview">';
                echo '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr__( 'Logo atual', 'dps-client-portal' ) . '" style="max-width: 200px; height: auto;">';
                echo '</div>';
            }
        }
        
        echo '<input type="file" name="portal_logo" id="portal_logo" accept="image/*">';
        if ( $logo_id ) {
            echo '<br><label><input type="checkbox" name="remove_logo" value="1"> ' . esc_html__( 'Remover logo', 'dps-client-portal' ) . '</label>';
        }
        echo '</div>';
        
        // Cor primária
        echo '<div class="dps-form-field">';
        echo '<label class="dps-form-label" for="primary_color"><strong>' . esc_html__( 'Cor Primária', 'dps-client-portal' ) . '</strong></label>';
        echo '<p class="description">' . esc_html__( 'Cor usada em botões, links e destaques. Certifique-se de que tenha bom contraste com branco.', 'dps-client-portal' ) . '</p>';
        echo '<input type="color" name="primary_color" id="primary_color" value="' . esc_attr( $primary_color ) . '">';
        echo '<span class="dps-color-preview" style="background-color: ' . esc_attr( $primary_color ) . '; display: inline-block; width: 40px; height: 40px; border: 1px solid #ddd; margin-left: 10px; vertical-align: middle;"></span>';
        echo '</div>';
        
        // Imagem hero
        echo '<div class="dps-form-field">';
        echo '<label class="dps-form-label"><strong>' . esc_html__( 'Imagem de Destaque (opcional)', 'dps-client-portal' ) . '</strong></label>';
        echo '<p class="description">' . esc_html__( 'Imagem de fundo exibida no topo do portal. Tamanho recomendado: 1200x200px', 'dps-client-portal' ) . '</p>';
        
        if ( $hero_id ) {
            $hero_url = wp_get_attachment_image_url( $hero_id, 'large' );
            if ( $hero_url ) {
                echo '<div class="dps-branding-preview">';
                echo '<img src="' . esc_url( $hero_url ) . '" alt="' . esc_attr__( 'Hero atual', 'dps-client-portal' ) . '" style="max-width: 100%; height: auto;">';
                echo '</div>';
            }
        }
        
        echo '<input type="file" name="portal_hero" id="portal_hero" accept="image/*">';
        if ( $hero_id ) {
            echo '<br><label><input type="checkbox" name="remove_hero" value="1"> ' . esc_html__( 'Remover imagem de destaque', 'dps-client-portal' ) . '</label>';
        }
        echo '</div>';
        
        echo '<div class="dps-form-actions">';
        echo '<button type="submit" name="dps_branding_save" class="button button-primary">' . esc_html__( 'Salvar Configurações de Branding', 'dps-client-portal' ) . '</button>';
        echo '</div>';
        
        echo '</form>';
        echo '</div>';
    }

    /**
     * Salva configurações de branding.
     * Fase 4 - continuação
     *
     * @since 2.4.0
     */
    private function save_branding_settings() {
        // Cor primária
        if ( isset( $_POST['primary_color'] ) ) {
            $color = sanitize_hex_color( $_POST['primary_color'] );
            if ( $color ) {
                update_option( 'dps_portal_primary_color', $color );
            }
        }
        
        // Remover logo
        if ( isset( $_POST['remove_logo'] ) ) {
            delete_option( 'dps_portal_logo_id' );
        }
        
        // Upload de logo
        if ( ! empty( $_FILES['portal_logo']['name'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            
            $upload = wp_handle_upload( $_FILES['portal_logo'], [ 'test_form' => false ] );
            
            if ( ! isset( $upload['error'] ) && isset( $upload['file'] ) ) {
                $attachment = [
                    'post_title'     => 'Portal Logo',
                    'post_mime_type' => $upload['type'],
                    'post_status'    => 'inherit',
                ];
                $attach_id = wp_insert_attachment( $attachment, $upload['file'] );
                if ( ! is_wp_error( $attach_id ) ) {
                    $attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
                    wp_update_attachment_metadata( $attach_id, $attach_data );
                    update_option( 'dps_portal_logo_id', $attach_id );
                }
            }
        }
        
        // Remover hero
        if ( isset( $_POST['remove_hero'] ) ) {
            delete_option( 'dps_portal_hero_id' );
        }
        
        // Upload de hero
        if ( ! empty( $_FILES['portal_hero']['name'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            
            $upload = wp_handle_upload( $_FILES['portal_hero'], [ 'test_form' => false ] );
            
            if ( ! isset( $upload['error'] ) && isset( $upload['file'] ) ) {
                $attachment = [
                    'post_title'     => 'Portal Hero Image',
                    'post_mime_type' => $upload['type'],
                    'post_status'    => 'inherit',
                ];
                $attach_id = wp_insert_attachment( $attachment, $upload['file'] );
                if ( ! is_wp_error( $attach_id ) ) {
                    $attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
                    wp_update_attachment_metadata( $attach_id, $attach_data );
                    update_option( 'dps_portal_hero_id', $attach_id );
                }
            }
        }
        
        // Mensagem de sucesso
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . esc_html__( 'Configurações de branding salvas com sucesso!', 'dps-client-portal' ) . '</p>';
            echo '</div>';
        } );
    }
}
