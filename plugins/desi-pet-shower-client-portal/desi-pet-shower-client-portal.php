<?php
/**
 * Plugin Name:       desi.pet by PRObst – Client Portal Add-on
 * Plugin URI:        https://www.probst.pro
 * Description:       Portal de autoatendimento para clientes. Navegação por tabs, chat em tempo real, histórico, galeria de fotos, pendências financeiras e atualização de dados.
 * Version:           2.4.3
 * Author:            PRObst
 * Author URI:        https://www.probst.pro
 * Text Domain:       dps-client-portal
 * Domain Path:       /languages
 * Requires at least: 6.9
 * Requires PHP:      8.4
 * Update URI:        https://github.com/richardprobst/DPS
 *
 * Este add-on cria um portal de autoatendimento para clientes.  Ele registra
 * automaticamente um usuário WordPress para cada cliente cadastrado (quando
 * ainda não existir), associa o usuário ao cadastro de cliente e oferece um
 * shortcode [dps_client_portal] que renderiza a página com histórico, fotos,
 * pendências financeiras e formulários de atualização de dados.  O portal
 * utiliza apenas o login padrão do WordPress (usuário e senha), dispensando
 * CPF como credencial.  Pendências podem ser pagas via link de pagamento do
 * Mercado Pago, gerado na hora.
 */

// Bloqueia acesso direto aos arquivos.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Verifica se o plugin base desi.pet by PRObst está ativo.
 * Se não estiver, exibe aviso e interrompe carregamento do add-on.
 */
function dps_client_portal_check_base_plugin() {
    if ( ! class_exists( 'DPS_Base_Plugin' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__( 'O add-on requer o plugin base desi.pet by PRObst para funcionar.', 'dps-client-portal' );
            echo '</p></div>';
        } );
        return false;
    }
    return true;
}
add_action( 'plugins_loaded', function() {
    if ( ! dps_client_portal_check_base_plugin() ) {
        return;
    }
}, 1 );

// Define constantes úteis do plugin.
if ( ! defined( 'DPS_CLIENT_PORTAL_ADDON_DIR' ) ) {
    define( 'DPS_CLIENT_PORTAL_ADDON_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'DPS_CLIENT_PORTAL_ADDON_URL' ) ) {
    define( 'DPS_CLIENT_PORTAL_ADDON_URL', plugin_dir_url( __FILE__ ) );
}

// Define título padrão da página do portal (tradução deve ser feita ao usar a constante)
if ( ! defined( 'DPS_CLIENT_PORTAL_PAGE_TITLE' ) ) {
    define( 'DPS_CLIENT_PORTAL_PAGE_TITLE', 'Portal do Cliente' );
}

/**
 * Carrega o text domain do Client Portal Add-on.
 * Usa prioridade 1 para garantir que rode antes da inicialização da classe (prioridade 5).
 */
function dps_client_portal_load_textdomain() {
    load_plugin_textdomain( 'dps-client-portal', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'dps_client_portal_load_textdomain', 1 );

// Inclui funções auxiliares
require_once DPS_CLIENT_PORTAL_ADDON_DIR . 'includes/functions-portal-helpers.php';

// Inclui interfaces (Fase 3.1 - Dependency Injection)
require_once DPS_CLIENT_PORTAL_ADDON_DIR . 'includes/client-portal/interfaces/interface-dps-portal-session-manager.php';
require_once DPS_CLIENT_PORTAL_ADDON_DIR . 'includes/client-portal/interfaces/interface-dps-portal-token-manager.php';

// Inclui classes principais (legacy)
require_once DPS_CLIENT_PORTAL_ADDON_DIR . 'includes/class-dps-portal-token-manager.php';
require_once DPS_CLIENT_PORTAL_ADDON_DIR . 'includes/class-dps-portal-session-manager.php';
require_once DPS_CLIENT_PORTAL_ADDON_DIR . 'includes/class-dps-portal-admin-actions.php';
require_once DPS_CLIENT_PORTAL_ADDON_DIR . 'includes/class-dps-portal-cache-helper.php'; // Fase 2.2
require_once DPS_CLIENT_PORTAL_ADDON_DIR . 'includes/class-dps-calendar-helper.php'; // Fase 2.8
require_once DPS_CLIENT_PORTAL_ADDON_DIR . 'includes/class-dps-portal-profile-update.php'; // Fase 5 - Atualização de perfil via link

// Inclui repositórios (Fase 3.1 - Repository Pattern)
require_once DPS_CLIENT_PORTAL_ADDON_DIR . 'includes/client-portal/repositories/class-dps-client-repository.php';
require_once DPS_CLIENT_PORTAL_ADDON_DIR . 'includes/client-portal/repositories/class-dps-pet-repository.php';
require_once DPS_CLIENT_PORTAL_ADDON_DIR . 'includes/client-portal/repositories/class-dps-appointment-repository.php';
require_once DPS_CLIENT_PORTAL_ADDON_DIR . 'includes/client-portal/repositories/class-dps-finance-repository.php';
require_once DPS_CLIENT_PORTAL_ADDON_DIR . 'includes/client-portal/repositories/class-dps-message-repository.php';
require_once DPS_CLIENT_PORTAL_ADDON_DIR . 'includes/client-portal/repositories/class-dps-appointment-request-repository.php'; // Fase 4

// Inclui classes refatoradas (Fase 3 - v3.0.0)
require_once DPS_CLIENT_PORTAL_ADDON_DIR . 'includes/client-portal/class-dps-portal-data-provider.php';
require_once DPS_CLIENT_PORTAL_ADDON_DIR . 'includes/client-portal/class-dps-portal-renderer.php';
require_once DPS_CLIENT_PORTAL_ADDON_DIR . 'includes/client-portal/class-dps-portal-actions-handler.php';
require_once DPS_CLIENT_PORTAL_ADDON_DIR . 'includes/client-portal/class-dps-portal-ajax-handler.php';
require_once DPS_CLIENT_PORTAL_ADDON_DIR . 'includes/client-portal/class-dps-portal-admin.php';
require_once DPS_CLIENT_PORTAL_ADDON_DIR . 'includes/client-portal/class-dps-portal-pet-history.php'; // Fase 4

// Hub centralizado do Portal (Fase 2 - Reorganização de Menus)
require_once DPS_CLIENT_PORTAL_ADDON_DIR . 'includes/class-dps-portal-hub.php';

// Inclui classe principal (coordenador)
require_once DPS_CLIENT_PORTAL_ADDON_DIR . 'includes/class-dps-client-portal.php';

/**
 * Inicializa o Client Portal Add-on após o hook 'init' para garantir que o text domain seja carregado primeiro.
 * Usa prioridade 5 para rodar após o carregamento do text domain (prioridade 1) mas antes
 * de outros registros (prioridade 10).
 */
function dps_client_portal_init_addon() {
    if ( class_exists( 'DPS_Client_Portal' ) ) {
        DPS_Client_Portal::get_instance();
    }
    
    // Inicializa gerenciadores de token e sessão
    if ( class_exists( 'DPS_Portal_Token_Manager' ) ) {
        DPS_Portal_Token_Manager::get_instance();
    }
    
    if ( class_exists( 'DPS_Portal_Session_Manager' ) ) {
        DPS_Portal_Session_Manager::get_instance();
    }
    
    // Inicializa gerenciador de ações administrativas
    if ( class_exists( 'DPS_Portal_Admin_Actions' ) ) {
        DPS_Portal_Admin_Actions::get_instance();
    }
    
    // Inicializa administração do portal (CPT dps_portal_message, menus, etc.)
    if ( class_exists( 'DPS_Portal_Admin' ) ) {
        DPS_Portal_Admin::get_instance();
    }
    
    // Inicializa handler AJAX (Fase 4)
    if ( class_exists( 'DPS_Portal_AJAX_Handler' ) ) {
        DPS_Portal_AJAX_Handler::get_instance();
    }
    
    // Inicializa o Hub centralizado do Portal (Fase 2 - Reorganização de Menus)
    if ( class_exists( 'DPS_Portal_Hub' ) ) {
        DPS_Portal_Hub::get_instance();
    }
    
    // Inicializa gerenciador de atualização de perfil via link (Fase 5)
    if ( class_exists( 'DPS_Portal_Profile_Update' ) ) {
        DPS_Portal_Profile_Update::get_instance();
    }

}
add_action( 'init', 'dps_client_portal_init_addon', 5 );

/**
 * Invalidação automática de cache (Fase 2.2)
 * 
 * Invalida cache quando dados relevantes são atualizados
 * 
 * @since 2.5.0
 */
function dps_client_portal_setup_cache_invalidation() {
    if ( ! class_exists( 'DPS_Portal_Cache_Helper' ) ) {
        return;
    }

    // Invalida cache ao salvar cliente
    add_action( 'save_post_dps_cliente', function( $post_id ) {
        DPS_Portal_Cache_Helper::invalidate_client_cache( $post_id );
    }, 10, 1 );

    // Invalida cache ao salvar pet
    add_action( 'save_post_dps_pet', function( $post_id ) {
        $owner_id = get_post_meta( $post_id, 'owner_id', true );
        if ( $owner_id ) {
            DPS_Portal_Cache_Helper::invalidate_client_cache( $owner_id, [ 'pets', 'gallery' ] );
        }
    }, 10, 1 );

    // Invalida cache ao salvar agendamento
    add_action( 'save_post_dps_agendamento', function( $post_id ) {
        $client_id = get_post_meta( $post_id, 'appointment_client_id', true );
        if ( $client_id ) {
            DPS_Portal_Cache_Helper::invalidate_client_cache( $client_id, [ 'next_appt', 'history' ] );
        }
    }, 10, 1 );

    // Invalida cache ao salvar transação (Finance Add-on)
    add_action( 'dps_finance_transaction_saved', function( $transaction_id, $client_id ) {
        if ( $client_id ) {
            DPS_Portal_Cache_Helper::invalidate_client_cache( $client_id, [ 'pending' ] );
        }
    }, 10, 2 );
}
add_action( 'init', 'dps_client_portal_setup_cache_invalidation', 20 );

/**
 * Verifica a integridade da configuração do portal no admin.
 * 
 * Exibe avisos no admin se:
 * - A página do portal não existir
 * - A página configurada não tiver o shortcode [dps_client_portal]
 * - A página configurada estiver em rascunho ou lixeira
 * 
 * @since 2.4.1
 */
function dps_client_portal_check_configuration() {
    // Só executa no admin
    if ( ! is_admin() ) {
        return;
    }
    
    // Só executa para usuários com permissão
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    
    $page_id = get_option( 'dps_portal_page_id', 0 );
    $messages = [];
    
    // Verifica se tem página configurada
    if ( ! $page_id ) {
        $messages[] = [
            'type'    => 'warning',
            'message' => sprintf(
                /* translators: %s: link para configurações */
                __( 'Portal do Cliente: Nenhuma página configurada. <a href="%s">Configure agora</a> ou crie uma página com o shortcode [dps_client_portal].', 'dps-client-portal' ),
                admin_url( 'admin.php?page=dps-client-portal-settings' )
            ),
        ];
    } else {
        $page = get_post( $page_id );
        
        // Verifica se a página existe
        if ( ! $page ) {
            $messages[] = [
                'type'    => 'error',
                'message' => sprintf(
                    /* translators: %s: link para configurações */
                    __( 'Portal do Cliente: A página configurada (ID #%d) não existe mais. <a href="%s">Configure uma nova página</a>.', 'dps-client-portal' ),
                    $page_id,
                    admin_url( 'admin.php?page=dps-client-portal-settings' )
                ),
            ];
        } else {
            // Verifica se a página está publicada
            if ( 'publish' !== $page->post_status ) {
                $messages[] = [
                    'type'    => 'warning',
                    'message' => sprintf(
                        /* translators: 1: título da página, 2: link para editar */
                        __( 'Portal do Cliente: A página "%1$s" não está publicada. <a href="%2$s">Publicar agora</a>.', 'dps-client-portal' ),
                        esc_html( $page->post_title ),
                        get_edit_post_link( $page_id )
                    ),
                ];
            }
            
            // Verifica se a página tem o shortcode
            if ( ! has_shortcode( (string) $page->post_content, 'dps_client_portal' ) ) {
                $messages[] = [
                    'type'    => 'error',
                    'message' => sprintf(
                        /* translators: 1: título da página, 2: link para editar */
                        __( 'Portal do Cliente: A página "%1$s" não contém o shortcode [dps_client_portal]. <a href="%2$s">Adicionar shortcode agora</a>.', 'dps-client-portal' ),
                        esc_html( $page->post_title ),
                        get_edit_post_link( $page_id )
                    ),
                ];
            }
        }
    }
    
    // Exibe avisos se houver problemas
    if ( ! empty( $messages ) ) {
        add_action( 'admin_notices', function() use ( $messages ) {
            foreach ( $messages as $msg ) {
                $class = 'notice notice-' . esc_attr( $msg['type'] ) . ' is-dismissible';
                printf( '<div class="%s"><p>%s</p></div>', $class, $msg['message'] );
            }
        } );
    }
}
add_action( 'admin_init', 'dps_client_portal_check_configuration' );

/**
 * Handler para download de arquivos .ics (Fase 2.8)
 * 
 * Permite clientes exportarem agendamentos para calendários
 * 
 * @since 2.5.0
 */
function dps_client_portal_handle_ics_download() {
    if ( ! isset( $_GET['dps_download_ics'] ) ) {
        return;
    }

    $appointment_id = absint( $_GET['dps_download_ics'] );
    
    // Verifica nonce
    $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'dps_download_ics_' . $appointment_id ) ) {
        wp_die( esc_html__( 'Link inválido ou expirado.', 'dps-client-portal' ) );
    }

    // Verifica se cliente está autenticado e se o agendamento pertence a ele
    if ( class_exists( 'DPS_Portal_Session_Manager' ) ) {
        $session    = DPS_Portal_Session_Manager::get_instance();
        $client_id  = $session->get_authenticated_client_id();
        
        if ( ! $client_id ) {
            wp_die( esc_html__( 'Você precisa estar autenticado para baixar este arquivo.', 'dps-client-portal' ) );
        }

        // Usa helper centralizado de validação de ownership (Fase 1.4)
        if ( ! dps_portal_assert_client_owns_resource( $client_id, $appointment_id, 'appointment' ) ) {
            wp_die( esc_html__( 'Você não tem permissão para baixar este arquivo.', 'dps-client-portal' ) );
        }
    }

    // Gera e envia arquivo .ics
    if ( class_exists( 'DPS_Calendar_Helper' ) ) {
        DPS_Calendar_Helper::download_ics( $appointment_id );
    }
}
add_action( 'init', 'dps_client_portal_handle_ics_download', 1 );

/**
 * Registra CPT para pedidos de agendamento (Fase 4)
 * 
 * @since 2.4.0
 */
function dps_client_portal_register_appointment_request_cpt() {
    $labels = [
        'name'               => __( 'Pedidos de Agendamento', 'dps-client-portal' ),
        'singular_name'      => __( 'Pedido de Agendamento', 'dps-client-portal' ),
        'menu_name'          => __( 'Pedidos Portal', 'dps-client-portal' ),
        'add_new'            => __( 'Adicionar Novo', 'dps-client-portal' ),
        'add_new_item'       => __( 'Adicionar Novo Pedido', 'dps-client-portal' ),
        'edit_item'          => __( 'Editar Pedido', 'dps-client-portal' ),
        'new_item'           => __( 'Novo Pedido', 'dps-client-portal' ),
        'view_item'          => __( 'Ver Pedido', 'dps-client-portal' ),
        'search_items'       => __( 'Buscar Pedidos', 'dps-client-portal' ),
        'not_found'          => __( 'Nenhum pedido encontrado', 'dps-client-portal' ),
        'not_found_in_trash' => __( 'Nenhum pedido na lixeira', 'dps-client-portal' ),
    ];

    $args = [
        'labels'              => $labels,
        'public'              => false,
        'publicly_queryable'  => false,
        'show_ui'             => true,
        'show_in_menu'        => 'desi-pet-shower', // Agrupa no menu principal DPS
        'query_var'           => false,
        'capability_type'     => 'post',
        'has_archive'         => false,
        'hierarchical'        => false,
        'menu_position'       => null,
        'supports'            => [ 'title' ],
        'menu_icon'           => 'dashicons-calendar-alt',
    ];

    register_post_type( 'dps_appt_request', $args );
}
add_action( 'init', 'dps_client_portal_register_appointment_request_cpt' );

/**
 * Hook de ativação para criar tabela de tokens e página do portal.
 *
 * @since 2.0.0
 * @since 2.4.1 Adicionada criação automática da página do portal
 */
function dps_client_portal_activate() {
    // Cria tabela de tokens
    if ( class_exists( 'DPS_Portal_Token_Manager' ) ) {
        $token_manager = DPS_Portal_Token_Manager::get_instance();
        $token_manager->maybe_create_table();
    }
    
    // Cria página do portal se não existir
    dps_client_portal_maybe_create_page();
}
register_activation_hook( __FILE__, 'dps_client_portal_activate' );

/**
 * Cria a página do Portal do Cliente automaticamente se não existir.
 * 
 * Esta função verifica se já existe uma página configurada ou com o título
 * "Portal do Cliente". Se não existir, cria uma nova página com:
 * - Título: "Portal do Cliente"
 * - Slug: "portal-do-cliente"
 * - Conteúdo: shortcode [dps_client_portal]
 * - Status: publicada
 * 
 * Após criar a página, armazena o ID em dps_portal_page_id para referência futura.
 * 
 * @since 2.4.1
 * @return int|false ID da página criada ou existente, ou false em caso de erro
 */
function dps_client_portal_maybe_create_page() {
    // Verifica se já existe uma página configurada
    $existing_page_id = get_option( 'dps_portal_page_id', 0 );
    if ( $existing_page_id && get_post_status( $existing_page_id ) === 'publish' ) {
        // Verifica se a página tem o shortcode
        $page = get_post( $existing_page_id );
        if ( $page && has_shortcode( (string) $page->post_content, 'dps_client_portal' ) ) {
            return $existing_page_id;
        }
        
        // Página existe mas não tem o shortcode - adiciona o shortcode
        if ( $page ) {
            wp_update_post( [
                'ID'           => $existing_page_id,
                'post_content' => $page->post_content . "\n\n[dps_client_portal]",
            ] );
            return $existing_page_id;
        }
    }
    
    // Busca página existente por título (usa título não traduzido para consistência entre idiomas)
    $page_title = DPS_CLIENT_PORTAL_PAGE_TITLE;
    
    if ( function_exists( 'dps_get_page_by_title_compat' ) ) {
        $portal_page = dps_get_page_by_title_compat( $page_title );
    } else {
        // Fallback se helper não estiver carregado ainda
        global $wpdb;
        $post_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = 'page' AND post_status = 'publish' LIMIT 1",
                $page_title
            )
        );
        $portal_page = $post_id ? get_post( $post_id ) : null;
    }
    
    if ( $portal_page ) {
        // Página existe - verifica se tem o shortcode
        if ( ! has_shortcode( (string) $portal_page->post_content, 'dps_client_portal' ) ) {
            // Adiciona o shortcode ao conteúdo existente
            wp_update_post( [
                'ID'           => $portal_page->ID,
                'post_content' => $portal_page->post_content . "\n\n[dps_client_portal]",
            ] );
        }
        
        // Armazena o ID da página
        update_option( 'dps_portal_page_id', $portal_page->ID );
        return $portal_page->ID;
    }
    
    // Nenhuma página encontrada - cria uma nova (usa título traduzido se disponível)
    $translated_title = __( DPS_CLIENT_PORTAL_PAGE_TITLE, 'dps-client-portal' );
    $page_id = wp_insert_post( [
        'post_title'     => $translated_title,
        'post_name'      => 'portal-do-cliente',
        'post_content'   => '[dps_client_portal]',
        'post_status'    => 'publish',
        'post_type'      => 'page',
        'post_author'    => 1,
        'comment_status' => 'closed',
        'ping_status'    => 'closed',
    ] );
    
    if ( ! is_wp_error( $page_id ) && $page_id > 0 ) {
        // Armazena o ID da página criada
        update_option( 'dps_portal_page_id', $page_id );
        return $page_id;
    }
    
    return false;
}

/**
 * Hook de desativação para limpar cron jobs.
 *
 * @since 2.0.0
 */
function dps_client_portal_deactivate() {
    // Limpa cron job de limpeza de tokens
    wp_clear_scheduled_hook( 'dps_portal_cleanup_tokens' );
}
register_deactivation_hook( __FILE__, 'dps_client_portal_deactivate' );
