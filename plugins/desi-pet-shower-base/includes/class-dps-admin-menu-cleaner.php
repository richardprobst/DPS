<?php
/**
 * Limpeza e organização dos submenus do DPS.
 *
 * Remove entradas duplicadas quando já existe um hub dedicado
 * (Integrações, Sistema, Ferramentas, Agenda, IA, Portal),
 * mantendo as páginas acessíveis por URL ou pelas abas dos hubs.
 *
 * @package DPS_Base_Plugin
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe responsável por remover submenus redundantes do DPS.
 */
class DPS_Admin_Menu_Cleaner {

    /**
     * Instância única.
     *
     * @var DPS_Admin_Menu_Cleaner|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única.
     *
     * @return DPS_Admin_Menu_Cleaner
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Construtor privado.
     */
    private function __construct() {
        add_action( 'admin_menu', [ $this, 'cleanup_submenus' ], 99 );
    }

    /**
     * Remove submenus duplicados mantidos apenas para compatibilidade.
     *
     * Mantém o acesso às páginas pelas URLs originais e pelos hubs,
     * mas evita poluição visual no menu lateral.
     */
    public function cleanup_submenus() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $redundant_slugs = [
            // Integrações - já agrupadas no hub.
            'dps-communications',
            'dps-payment-settings',
            'dps-push-notifications',

            // Sistema - acessível pelo hub.
            'dps-backup',

            // Ferramentas - configuradas via hub.
            'dps-registration-settings',

            // Agenda - páginas replicadas no hub.
            'dps-agenda-dashboard',
            'dps-agenda-settings',

            // IA - todas as telas já consolidadas no hub de IA.
            'dps-ai-settings',
            'dps-ai-analytics',
            'dps-ai-knowledge-base',
            'dps-ai-kb-tester',
            'dps-ai-specialist',
            'dps-ai-insights',
            'dps-ai-conversations',

            // Portal do Cliente - navegação via hub com abas.
            'dps-client-portal-settings',
            'dps-client-logins',
        ];

        foreach ( $redundant_slugs as $slug ) {
            remove_submenu_page( 'desi-pet-shower', $slug );
        }
    }
}
