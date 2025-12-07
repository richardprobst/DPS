<?php
/**
 * Plugin Name:       DPS by PRObst – Client Portal Add-on
 * Plugin URI:        https://www.probst.pro
 * Description:       Portal de autoatendimento para clientes. Navegação por tabs, chat em tempo real, histórico, galeria de fotos, pendências financeiras e atualização de dados.
 * Version:           2.4.0
 * Author:            PRObst
 * Author URI:        https://www.probst.pro
 * Text Domain:       dps-client-portal
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
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
 * Verifica se o plugin base DPS by PRObst está ativo.
 * Se não estiver, exibe aviso e interrompe carregamento do add-on.
 */
function dps_client_portal_check_base_plugin() {
    if ( ! class_exists( 'DPS_Base_Plugin' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__( 'O add-on Portal do Cliente requer o plugin base DPS by PRObst para funcionar.', 'dps-client-portal' );
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

// Inclui classes principais
require_once DPS_CLIENT_PORTAL_ADDON_DIR . 'includes/class-dps-portal-token-manager.php';
require_once DPS_CLIENT_PORTAL_ADDON_DIR . 'includes/class-dps-portal-session-manager.php';
require_once DPS_CLIENT_PORTAL_ADDON_DIR . 'includes/class-dps-portal-admin-actions.php';
require_once DPS_CLIENT_PORTAL_ADDON_DIR . 'includes/class-dps-portal-cache-helper.php'; // Fase 2.2
require_once DPS_CLIENT_PORTAL_ADDON_DIR . 'includes/class-dps-calendar-helper.php'; // Fase 2.8
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

// Hook de ativação para criar tabela de tokens
register_activation_hook( __FILE__, function() {
    if ( class_exists( 'DPS_Portal_Token_Manager' ) ) {
        $token_manager = DPS_Portal_Token_Manager::get_instance();
        $token_manager->maybe_create_table();
    }
} );

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