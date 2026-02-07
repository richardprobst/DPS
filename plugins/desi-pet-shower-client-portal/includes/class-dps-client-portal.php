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
        // Processa autenticação por token
        if ( did_action( 'init' ) ) {
            $this->handle_token_authentication();
            $this->handle_logout_request();
            $this->handle_portal_actions();
            $this->handle_portal_settings_save();
        } else {
            add_action( 'init', [ $this, 'handle_token_authentication' ], 5 );
            add_action( 'init', [ $this, 'handle_logout_request' ], 6 );
            add_action( 'init', [ $this, 'handle_portal_actions' ] );
            add_action( 'init', [ $this, 'handle_portal_settings_save' ] );
        }

        // Cria login para novo cliente ao salvar post do tipo dps_cliente
        add_action( 'save_post_dps_cliente', [ $this, 'maybe_create_login_for_client' ], 10, 3 );

        // Shortcodes do portal
        add_shortcode( 'dps_client_portal', [ $this, 'render_portal_shortcode' ] );
        add_shortcode( 'dps_client_login', [ $this, 'render_login_shortcode' ] );

        // Assets do frontend
        add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
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
        $client_id = $this->get_authenticated_client_id();
        
        if ( ! $client_id ) {
            return;
        }

        if ( empty( $_POST['dps_client_portal_action'] ) ) {
            return;
        }

        $nonce = isset( $_POST['_dps_client_portal_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_dps_client_portal_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'dps_client_portal_action' ) ) {
            $referer      = wp_get_referer();
            $redirect_url = $referer ? remove_query_arg( 'portal_msg', $referer ) : remove_query_arg( 'portal_msg' );
            $redirect_url = add_query_arg( 'portal_msg', 'session_expired', $redirect_url );
            wp_safe_redirect( $redirect_url );
            exit;
        }

        $action       = sanitize_key( wp_unslash( $_POST['dps_client_portal_action'] ) );
        $referer      = wp_get_referer();
        $redirect_url = $referer ? remove_query_arg( 'portal_msg', $referer ) : remove_query_arg( 'portal_msg' );
        $handler      = DPS_Portal_Actions_Handler::get_instance();

        switch ( $action ) {
            case 'pay_transaction':
                $trans_id = isset( $_POST['trans_id'] ) ? absint( wp_unslash( $_POST['trans_id'] ) ) : 0;
                if ( $trans_id ) {
                    $result = $handler->handle_pay_transaction( $client_id, $trans_id );
                    wp_safe_redirect( $result );
                    exit;
                }
                $redirect_url = add_query_arg( 'portal_msg', 'error', $redirect_url );
                break;

            case 'update_client_info':
                $redirect_url = $handler->handle_update_client_info( $client_id );
                break;

            case 'update_pet':
                $pet_id = isset( $_POST['pet_id'] ) ? absint( wp_unslash( $_POST['pet_id'] ) ) : 0;
                if ( $pet_id ) {
                    $redirect_url = $handler->handle_update_pet( $client_id, $pet_id );
                } else {
                    $redirect_url = add_query_arg( 'portal_msg', 'error', $redirect_url );
                }
                break;

            case 'send_message':
                $redirect_url = $handler->handle_send_message( $client_id );
                break;

            case 'update_client_preferences':
                $redirect_url = $handler->handle_update_client_preferences( $client_id );
                break;

            case 'update_pet_preferences':
                $pet_id = isset( $_POST['pet_id'] ) ? absint( wp_unslash( $_POST['pet_id'] ) ) : 0;
                if ( $pet_id ) {
                    $redirect_url = $handler->handle_update_pet_preferences( $client_id, $pet_id );
                } else {
                    $redirect_url = add_query_arg( 'portal_msg', 'error', $redirect_url );
                }
                break;

            case 'submit_internal_review':
                $redirect_url = $handler->handle_submit_internal_review( $client_id );
                break;
        }

        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Registra estilos do portal no frontend.
     */
    public function register_assets() {
        if ( ! defined( 'DPS_CLIENT_PORTAL_ADDON_URL' ) ) {
            return;
        }

        // Design tokens M3 Expressive (devem ser carregados antes de qualquer CSS do portal)
        $style_deps = [];
        if ( defined( 'DPS_BASE_URL' ) ) {
            wp_register_style(
                'dps-design-tokens',
                DPS_BASE_URL . 'assets/css/dps-design-tokens.css',
                [],
                defined( 'DPS_BASE_VERSION' ) ? DPS_BASE_VERSION : '2.0.0'
            );
            $style_deps[] = 'dps-design-tokens';
        }

        $style_path = trailingslashit( DPS_CLIENT_PORTAL_ADDON_DIR ) . 'assets/css/client-portal.css';
        $style_url  = trailingslashit( DPS_CLIENT_PORTAL_ADDON_URL ) . 'assets/css/client-portal.css';
        $style_version = file_exists( $style_path ) ? filemtime( $style_path ) : '1.0.0';

        wp_register_style( 'dps-client-portal', $style_url, $style_deps, $style_version );
        
        $script_path = trailingslashit( DPS_CLIENT_PORTAL_ADDON_DIR ) . 'assets/js/client-portal.js';
        $script_url  = trailingslashit( DPS_CLIENT_PORTAL_ADDON_URL ) . 'assets/js/client-portal.js';
        $script_version = file_exists( $script_path ) ? filemtime( $script_path ) : '1.0.0';
        
        wp_register_script( 'dps-client-portal', $script_url, [], $script_version, true );
    }

    /**
     * Renderiza a página do portal para o shortcode. Mostra tela de acesso se não autenticado.
     *
     * @return string Conteúdo HTML renderizado.
     */
    public function render_portal_shortcode() {
        // Desabilita cache da página para garantir dados sempre atualizados
        if ( class_exists( 'DPS_Cache_Control' ) ) {
            DPS_Cache_Control::force_no_cache();
        }

        // Hook: Antes de renderizar o portal (Fase 2.3)
        do_action( 'dps_portal_before_render' );
        
        wp_enqueue_style( 'dps-client-portal' );
        wp_enqueue_script( 'dps-client-portal' );
        
        // Verifica se é uma ação de atualização de perfil via token (Fase 5)
        $action = isset( $_GET['dps_action'] ) ? sanitize_text_field( wp_unslash( $_GET['dps_action'] ) ) : '';
        if ( 'profile_update' === $action && isset( $_GET['token'] ) ) {
            if ( class_exists( 'DPS_Portal_Profile_Update' ) ) {
                return DPS_Portal_Profile_Update::get_instance()->render_profile_update_shortcode( [] );
            }
        }
        
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
                'badge' => DPS_Portal_Data_Provider::get_instance()->get_unread_messages_count( $client_id ),
            ],
            'agendamentos' => [
                'icon'  => '📅',
                'label' => __( 'Agendamentos', 'dps-client-portal' ),
                'active' => false,
                'badge' => DPS_Portal_Data_Provider::get_instance()->count_upcoming_appointments( $client_id ),
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
        DPS_Portal_Renderer::get_instance()->render_next_appointment( $client_id );
        $this->render_pets_summary( $client_id );
        echo '</div>';
        
        // Coluna direita: Pendências e Sugestões
        echo '<div class="dps-inicio-col dps-inicio-col--secondary">';
        DPS_Portal_Renderer::get_instance()->render_financial_pending( $client_id );
        DPS_Portal_Renderer::get_instance()->render_recent_requests( $client_id ); // Fase 4: Solicitações recentes
        DPS_Portal_Renderer::get_instance()->render_contextual_suggestions( $client_id ); // Fase 2: Sugestões baseadas em histórico
        echo '</div>';
        
        echo '</div>'; // .dps-inicio-grid
        
        // Indicações (se ativo) - ocupa largura total
        if ( function_exists( 'dps_loyalty_get_referral_code' ) ) {
            DPS_Portal_Renderer::get_instance()->render_referrals_summary( $client_id );
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
        DPS_Portal_Renderer::get_instance()->render_message_center( $client_id );
        do_action( 'dps_portal_after_mensagens_content', $client_id );
        echo '</div>';
        
        // Panel: Agendamentos (Histórico completo)
        echo '<div id="panel-agendamentos" class="dps-portal-tab-panel" role="tabpanel" aria-hidden="true">';
        do_action( 'dps_portal_before_agendamentos_content', $client_id ); // Fase 2.3
        DPS_Portal_Renderer::get_instance()->render_appointment_history( $client_id );
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
        DPS_Portal_Renderer::get_instance()->render_pet_gallery( $client_id );
        do_action( 'dps_portal_after_galeria_content', $client_id ); // Fase 2.3
        echo '</div>';
        
        // Panel: Meus Dados
        echo '<div id="panel-dados" class="dps-portal-tab-panel" role="tabpanel" aria-hidden="true">';
        do_action( 'dps_portal_before_dados_content', $client_id ); // Fase 2.3
        DPS_Portal_Renderer::get_instance()->render_update_forms( $client_id );
        $this->render_client_preferences( $client_id );
        do_action( 'dps_portal_after_dados_content', $client_id ); // Fase 2.3
        echo '</div>';
        
        // Hook: Permite add-ons renderizarem panels customizados (Fase 2.3)
        do_action( 'dps_portal_custom_tab_panels', $client_id, $tabs );
        
        echo '</div>'; // .dps-portal-tab-content

        // Hook para add-ons adicionarem conteúdo ao final do portal (ex: AI Assistant)
        do_action( 'dps_client_portal_after_content', $client_id );

        echo '</div>'; // .dps-client-portal
        
        // Widget de Chat flutuante
        DPS_Portal_Renderer::get_instance()->render_chat_widget( $client_id );
        
        return ob_get_clean();
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
     * Conta mensagens não lidas do cliente.
     * Fase 4 - continuação: Central de mensagens
     *
     * @param int $client_id ID do cliente.
     * @return int Número de mensagens não lidas.
     */
    /**
     * Renderiza uma visão rápida (dashboard) com métricas do cliente.
     * Mostra resumo visual de agendamentos, pendências e fidelidade.
     *
     * @since 3.0.0
     * @param int $client_id ID do cliente.
     */
    private function render_quick_overview( $client_id ) {
        // Conta agendamentos futuros
        $upcoming = DPS_Portal_Data_Provider::get_instance()->count_upcoming_appointments( $client_id );
        
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
        $unread = DPS_Portal_Data_Provider::get_instance()->get_unread_messages_count( $client_id );
        
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
     * Verifica se o cliente já realizou uma avaliação interna.
     *
     * @param int $client_id ID do cliente.
     * @return bool True se já avaliou.
     */
    private function has_client_reviewed( $client_id ) {
        if ( ! post_type_exists( 'dps_groomer_review' ) ) {
            return false;
        }

        $existing = get_posts( [
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

        return ! empty( $existing );
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
        // Desabilita cache da página para garantir dados sempre atualizados
        if ( class_exists( 'DPS_Cache_Control' ) ) {
            DPS_Cache_Control::force_no_cache();
        }

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
     * Obtém o endereço IP do cliente de forma segura.
     *
     * @since 2.2.0
     * @deprecated 2.5.0 Use DPS_IP_Helper::get_ip() diretamente.
     *
     * @return string Endereço IP sanitizado ou 'unknown' se não disponível.
     */
    private function get_client_ip() {
        if ( class_exists( 'DPS_IP_Helper' ) ) {
            return DPS_IP_Helper::get_ip();
        }
        // Fallback para retrocompatibilidade
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
