<?php
/**
 * Plugin Name:       Desi Pet Shower – Client Portal Add-on
 * Plugin URI:        https://probst.pro/desi-pet-shower
 * Description:       Portal do Cliente para o sistema Desi Pet Shower. Permite que clientes consultem histórico de atendimentos, galeria de fotos, pendências financeiras, atualizem seus dados e efetuem pagamentos de forma prática.
 * Version:           1.0.0
 * Author:            PRObst
 * Author URI:        https://probst.pro
 * Text Domain:       dps-client-portal
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

// Define constantes úteis do plugin.
if ( ! defined( 'DPS_CLIENT_PORTAL_ADDON_DIR' ) ) {
    define( 'DPS_CLIENT_PORTAL_ADDON_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'DPS_CLIENT_PORTAL_ADDON_URL' ) ) {
    define( 'DPS_CLIENT_PORTAL_ADDON_URL', plugin_dir_url( __FILE__ ) );
}

// Inclui a classe principal.
require_once DPS_CLIENT_PORTAL_ADDON_DIR . 'includes/class-dps-client-portal.php';

// Inicializa o add-on de forma segura após todos os plugins serem carregados.
add_action( 'plugins_loaded', function () {
    if ( class_exists( 'DPS_Client_Portal' ) ) {
        DPS_Client_Portal::get_instance();
    }
} );