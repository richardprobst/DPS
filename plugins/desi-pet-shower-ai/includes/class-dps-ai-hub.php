<?php
/**
 * Página Hub centralizada do Assistente de IA.
 *
 * Consolida todos os menus de IA em uma única página com abas:
 * - Configurações
 * - Analytics
 * - Conversas
 * - Base de Conhecimento
 * - Testar Base
 * - Modo Especialista
 * - Insights
 *
 * @package DPS_AI_Addon
 * @since 1.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe de Hub do Assistente de IA.
 */
class DPS_AI_Hub {

    /**
     * Instância única (singleton).
     *
     * @var DPS_AI_Hub|null
     */
    private static $instance = null;

    /**
     * Referências às instâncias de classes de abas.
     *
     * @var array
     */
    private $tab_instances = [];

    /**
     * Recupera a instância única.
     *
     * @return DPS_AI_Hub
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
        add_action( 'admin_menu', [ $this, 'register_hub_menu' ], 19 ); // Antes dos submenus antigos (20+)
    }

    /**
     * Registra o menu hub centralizado.
     */
    public function register_hub_menu() {
        add_submenu_page(
            'desi-pet-shower',
            __( 'Assistente de IA', 'dps-ai' ),
            __( 'Assistente de IA', 'dps-ai' ),
            'manage_options',
            'dps-ai-hub',
            [ $this, 'render_hub_page' ]
        );
    }

    /**
     * Renderiza a página hub com abas.
     */
    public function render_hub_page() {
        $tabs = [
            'config'        => __( 'Configurações', 'dps-ai' ),
            'analytics'     => __( 'Analytics', 'dps-ai' ),
            'conversations' => __( 'Conversas', 'dps-ai' ),
            'knowledge'     => __( 'Base de Conhecimento', 'dps-ai' ),
            'kb-tester'     => __( 'Testar Base', 'dps-ai' ),
            'specialist'    => __( 'Modo Especialista', 'dps-ai' ),
            'insights'      => __( 'Insights', 'dps-ai' ),
        ];

        $callbacks = [
            'config'        => [ $this, 'render_config_tab' ],
            'analytics'     => [ $this, 'render_analytics_tab' ],
            'conversations' => [ $this, 'render_conversations_tab' ],
            'knowledge'     => [ $this, 'render_knowledge_tab' ],
            'kb-tester'     => [ $this, 'render_kb_tester_tab' ],
            'specialist'    => [ $this, 'render_specialist_tab' ],
            'insights'      => [ $this, 'render_insights_tab' ],
        ];

        DPS_Admin_Tabs_Helper::render_tabbed_page(
            __( 'Assistente de IA', 'dps-ai' ),
            $tabs,
            $callbacks,
            'dps-ai-hub',
            'config'
        );
    }

    /**
     * Renderiza a aba de Configurações.
     */
    public function render_config_tab() {
        // Reutiliza o conteúdo da página antiga DPS_AI_Addon::render_admin_page()
        if ( class_exists( 'DPS_AI_Addon' ) ) {
            $addon = DPS_AI_Addon::get_instance();
            // Remove o wrapper <div class="wrap"> pois já está no helper
            remove_action( 'admin_notices', 'all' );
            ob_start();
            $addon->render_admin_page();
            $content = ob_get_clean();
            
            // Remove o <div class="wrap"> e </div> externos
            $content = preg_replace( '/^<div class="wrap">/i', '', $content );
            $content = preg_replace( '/<\/div>\s*$/i', '', $content );
            // Remove o H1 duplicado
            $content = preg_replace( '/<h1>.*?<\/h1>/i', '', $content, 1 );
            
            // SEGURANÇA: O conteúdo vem de render_admin_page() que já aplica escape adequado
            // em todos os valores de usuário. Não usamos wp_kses_post() aqui porque essa função
            // remove elementos de formulário (<input>, <select>, <textarea>, <form>, <button>)
            // que são essenciais para a página de configurações funcionar.
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Conteúdo gerado internamente com escape adequado
            echo $content;
        }
    }

    /**
     * Renderiza a aba de Analytics.
     */
    public function render_analytics_tab() {
        if ( class_exists( 'DPS_AI_Addon' ) ) {
            $addon = DPS_AI_Addon::get_instance();
            ob_start();
            $addon->render_analytics_page();
            $content = ob_get_clean();
            
            $content = preg_replace( '/^<div class="wrap">/i', '', $content );
            $content = preg_replace( '/<\/div>\s*$/i', '', $content );
            $content = preg_replace( '/<h1>.*?<\/h1>/i', '', $content, 1 );
            
            // SEGURANÇA: O conteúdo vem de render_analytics_page() que já aplica escape adequado
            // em todos os valores de usuário. Não usamos wp_kses_post() porque remove elementos
            // de formulário (<input>, <select>, <form>, <button>) necessários para filtros e exportação.
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Conteúdo gerado internamente com escape adequado
            echo $content;
        }
    }

    /**
     * Renderiza a aba de Conversas.
     */
    public function render_conversations_tab() {
        if ( class_exists( 'DPS_AI_Conversations_Admin' ) ) {
            $admin = DPS_AI_Conversations_Admin::get_instance();
            ob_start();
            $admin->render_conversations_list_page(); // Nome correto do método
            $content = ob_get_clean();
            
            $content = preg_replace( '/^<div class="wrap">/i', '', $content );
            $content = preg_replace( '/<\/div>\s*$/i', '', $content );
            $content = preg_replace( '/<h1>.*?<\/h1>/i', '', $content, 1 );
            
            // SEGURANÇA: O conteúdo vem de render_conversations_list_page() que já aplica escape adequado
            // em todos os valores de usuário. Não usamos wp_kses_post() porque remove elementos
            // de formulário (<input>, <select>, <form>, <button>) necessários para filtros.
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Conteúdo gerado internamente com escape adequado
            echo $content;
        }
    }

    /**
     * Renderiza a aba de Base de Conhecimento.
     */
    public function render_knowledge_tab() {
        if ( class_exists( 'DPS_AI_Knowledge_Base_Admin' ) ) {
            $admin = DPS_AI_Knowledge_Base_Admin::get_instance();
            ob_start();
            $admin->render_admin_page();
            $content = ob_get_clean();
            
            $content = preg_replace( '/^<div class="wrap">/i', '', $content );
            $content = preg_replace( '/<\/div>\s*$/i', '', $content );
            $content = preg_replace( '/<h1>.*?<\/h1>/i', '', $content, 1 );
            
            // SEGURANÇA: O conteúdo vem de render_admin_page() que já aplica escape adequado
            // em todos os valores de usuário. Não usamos wp_kses_post() porque remove elementos
            // de formulário (<input>, <textarea>, <form>, <button>) necessários para gerenciar entradas.
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Conteúdo gerado internamente com escape adequado
            echo $content;
        }
    }

    /**
     * Renderiza a aba de Testar Base.
     */
    public function render_kb_tester_tab() {
        if ( class_exists( 'DPS_AI_Knowledge_Base_Tester' ) ) {
            $tester = DPS_AI_Knowledge_Base_Tester::get_instance();
            ob_start();
            $tester->render_admin_page(); // Nome correto do método
            $content = ob_get_clean();
            
            $content = preg_replace( '/^<div class="wrap">/i', '', $content );
            $content = preg_replace( '/<\/div>\s*$/i', '', $content );
            $content = preg_replace( '/<h1>.*?<\/h1>/i', '', $content, 1 );
            
            // SEGURANÇA: O conteúdo vem de render_admin_page() que já aplica escape adequado
            // em todos os valores de usuário. Não usamos wp_kses_post() porque remove elementos
            // de formulário (<input>, <textarea>, <form>, <button>) necessários para testar perguntas.
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Conteúdo gerado internamente com escape adequado
            echo $content;
        }
    }

    /**
     * Renderiza a aba de Modo Especialista.
     */
    public function render_specialist_tab() {
        if ( class_exists( 'DPS_AI_Specialist_Mode' ) ) {
            $specialist = DPS_AI_Specialist_Mode::get_instance();
            ob_start();
            $specialist->render_interface();
            $content = ob_get_clean();
            
            $content = preg_replace( '/^<div class="wrap">/i', '', $content );
            $content = preg_replace( '/<\/div>\s*$/i', '', $content );
            $content = preg_replace( '/<h1>.*?<\/h1>/i', '', $content, 1 );
            
            // SEGURANÇA: O conteúdo vem de render_interface() que já aplica escape adequado
            // em todos os valores de usuário. Não usamos wp_kses_post() porque remove elementos
            // de formulário (<input>, <textarea>, <form>, <button>) necessários para a interface.
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Conteúdo gerado internamente com escape adequado
            echo $content;
        }
    }

    /**
     * Renderiza a aba de Insights.
     */
    public function render_insights_tab() {
        if ( class_exists( 'DPS_AI_Insights_Dashboard' ) ) {
            $insights = DPS_AI_Insights_Dashboard::get_instance();
            ob_start();
            $insights->render_dashboard();
            $content = ob_get_clean();
            
            $content = preg_replace( '/^<div class="wrap">/i', '', $content );
            $content = preg_replace( '/<\/div>\s*$/i', '', $content );
            $content = preg_replace( '/<h1>.*?<\/h1>/i', '', $content, 1 );
            
            // SEGURANÇA: O conteúdo vem de render_dashboard() que já aplica escape adequado
            // em todos os valores de usuário. Não usamos wp_kses_post() porque remove elementos
            // de formulário (<select>, <form>, <button>) que podem ser necessários.
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Conteúdo gerado internamente com escape adequado
            echo $content;
        }
    }
}
