<?php
/**
 * Plugin Name:       desi.pet by PRObst – Frontend Add-on
 * Plugin URI:        https://www.probst.pro
 * Description:       Consolida experiências frontend (cadastro, agendamento, configurações) em add-on modular.
 * Version:           1.0.0
 * Author:            PRObst
 * Author URI:        https://www.probst.pro
 * Text Domain:       dps-frontend-addon
 * Domain Path:       /languages
 * Requires at least: 6.9
 * Requires PHP:      8.4
 * Update URI:        https://github.com/richardprobst/DPS
 * License:           GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'DPS_FRONTEND_VERSION', '1.0.0' );
define( 'DPS_FRONTEND_DIR', plugin_dir_path( __FILE__ ) );
define( 'DPS_FRONTEND_URL', plugin_dir_url( __FILE__ ) );

/*
|--------------------------------------------------------------------------
| Base dependency gate
|--------------------------------------------------------------------------
| O add-on só carrega se o plugin base estiver ativo. Em caso de ausência,
| exibe aviso administrativo e interrompe.
*/
add_action( 'plugins_loaded', static function (): void {
    if ( class_exists( 'DPS_Base_Plugin' ) ) {
        return;
    }

    add_action( 'admin_notices', static function (): void {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html__( 'O add-on Frontend requer o plugin base desi.pet by PRObst.', 'dps-frontend-addon' )
        );
    } );
}, 1 );

/*
|--------------------------------------------------------------------------
| Text domain — prioridade 1 (antes do bootstrap na prioridade 5)
|--------------------------------------------------------------------------
*/
add_action( 'init', static function (): void {
    load_plugin_textdomain(
        'dps-frontend-addon',
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/languages'
    );
}, 1 );

/*
|--------------------------------------------------------------------------
| Autoload & bootstrap — prioridade 5
|--------------------------------------------------------------------------
| Carrega classes, monta o grafo de dependências via construtor e
| inicializa o add-on. Nenhum singleton; objetos vivem no escopo
| do callback de init.
*/
add_action( 'init', static function (): void {
    if ( ! class_exists( 'DPS_Base_Plugin' ) ) {
        return;
    }

    $dir = DPS_FRONTEND_DIR . 'includes/';

    // Suporte (sem dependências entre si)
    require_once $dir . 'support/class-dps-frontend-logger.php';
    require_once $dir . 'support/class-dps-frontend-request-guard.php';
    require_once $dir . 'support/class-dps-frontend-assets.php';

    // Infraestrutura
    require_once $dir . 'class-dps-frontend-feature-flags.php';
    require_once $dir . 'class-dps-frontend-module-registry.php';
    require_once $dir . 'class-dps-frontend-compatibility.php';
    require_once $dir . 'class-dps-template-engine.php';

    // Classes abstratas (Fase 7)
    require_once $dir . 'abstracts/class-dps-abstract-module-v2.php';
    require_once $dir . 'abstracts/class-dps-abstract-handler.php';
    require_once $dir . 'abstracts/class-dps-abstract-service.php';
    require_once $dir . 'abstracts/class-dps-abstract-validator.php';

    // Hook Bridges (Fase 7)
    require_once $dir . 'bridges/class-dps-registration-hook-bridge.php';
    require_once $dir . 'bridges/class-dps-booking-hook-bridge.php';

    // Módulos (v1 dual-run)
    require_once $dir . 'modules/class-dps-frontend-registration-module.php';
    require_once $dir . 'modules/class-dps-frontend-booking-module.php';
    require_once $dir . 'modules/class-dps-frontend-settings-module.php';

    // Módulos V2 nativos (Fase 7)
    require_once $dir . 'modules/class-dps-frontend-registration-v2-module.php';
    require_once $dir . 'modules/class-dps-frontend-booking-v2-module.php';

    // Orquestrador
    require_once $dir . 'class-dps-frontend-addon.php';

    // Monta e inicializa
    $logger = new DPS_Frontend_Logger();
    $flags  = new DPS_Frontend_Feature_Flags();
    $assets = new DPS_Frontend_Assets( $flags );

    $templateEngine    = new DPS_Template_Engine( DPS_FRONTEND_DIR );
    $registrationBridge = new DPS_Registration_Hook_Bridge( $logger );
    $bookingBridge      = new DPS_Booking_Hook_Bridge( $logger );

    $registry = new DPS_Frontend_Module_Registry( $flags, $logger );
    $registry->add( 'registration',    new DPS_Frontend_Registration_Module( $logger ) );
    $registry->add( 'booking',         new DPS_Frontend_Booking_Module( $logger ) );
    $registry->add( 'settings',        new DPS_Frontend_Settings_Module( $logger, $flags ) );
    $registry->add( 'registration_v2', new DPS_Frontend_Registration_V2_Module( $logger, $templateEngine, $registrationBridge ) );
    $registry->add( 'booking_v2',      new DPS_Frontend_Booking_V2_Module( $logger, $templateEngine, $bookingBridge ) );

    $compatibility = new DPS_Frontend_Compatibility( $flags, $logger );

    $addon = new DPS_Frontend_Addon( $registry, $assets, $compatibility, $logger );
    $addon->boot();
}, 5 );
