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
     * Retorna lista de tags HTML permitidas para o conteúdo do hub.
     * 
     * Extende wp_kses_post_tags para incluir elementos de formulário que são
     * necessários para as páginas de configuração funcionarem corretamente.
     *
     * @return array Lista de tags HTML permitidas com seus atributos.
     */
    private function get_allowed_form_tags() {
        // Começa com as tags permitidas pelo wp_kses_post
        $allowed_tags = wp_kses_allowed_html( 'post' );

        // Atributos comuns para elementos de formulário
        // Nota: wp_kses não suporta wildcards, então listamos atributos específicos
        $common_attrs = [
            'id'              => true,
            'class'           => true,
            'style'           => true,
            'title'           => true,
            'data-context'    => true,
            'data-default-color' => true,
            'data-nonce'      => true,
            'aria-label'      => true,
            'aria-describedby' => true,
            'aria-hidden'     => true,
        ];

        // Adiciona form
        $allowed_tags['form'] = array_merge( $common_attrs, [
            'action'      => true,
            'method'      => true,
            'enctype'     => true,
            'name'        => true,
            'target'      => true,
            'novalidate'  => true,
            'autocomplete' => true,
        ] );

        // Adiciona input
        $allowed_tags['input'] = array_merge( $common_attrs, [
            'type'        => true,
            'name'        => true,
            'value'       => true,
            'placeholder' => true,
            'checked'     => true,
            'disabled'    => true,
            'readonly'    => true,
            'required'    => true,
            'min'         => true,
            'max'         => true,
            'step'        => true,
            'size'        => true,
            'maxlength'   => true,
            'pattern'     => true,
            'autocomplete' => true,
            'autofocus'   => true,
        ] );

        // Adiciona select
        $allowed_tags['select'] = array_merge( $common_attrs, [
            'name'        => true,
            'disabled'    => true,
            'required'    => true,
            'multiple'    => true,
            'size'        => true,
            'autocomplete' => true,
        ] );

        // Adiciona option
        $allowed_tags['option'] = [
            'value'       => true,
            'selected'    => true,
            'disabled'    => true,
            'label'       => true,
        ];

        // Adiciona optgroup
        $allowed_tags['optgroup'] = [
            'label'       => true,
            'disabled'    => true,
        ];

        // Adiciona textarea
        $allowed_tags['textarea'] = array_merge( $common_attrs, [
            'name'        => true,
            'rows'        => true,
            'cols'        => true,
            'placeholder' => true,
            'disabled'    => true,
            'readonly'    => true,
            'required'    => true,
            'maxlength'   => true,
            'wrap'        => true,
            'autocomplete' => true,
        ] );

        // Adiciona button
        $allowed_tags['button'] = array_merge( $common_attrs, [
            'type'        => true,
            'name'        => true,
            'value'       => true,
            'disabled'    => true,
            'formaction'  => true,
            'formmethod'  => true,
        ] );

        // Adiciona label
        $allowed_tags['label'] = array_merge( $common_attrs, [
            'for'         => true,
        ] );

        // Adiciona fieldset e legend
        $allowed_tags['fieldset'] = array_merge( $common_attrs, [
            'disabled'    => true,
            'name'        => true,
        ] );
        $allowed_tags['legend'] = $common_attrs;

        // Adiciona canvas para gráficos (Chart.js)
        $allowed_tags['canvas'] = $common_attrs;

        // Adiciona script (necessário para inicialização inline)
        $allowed_tags['script'] = [
            'type'        => true,
            'src'         => true,
            'async'       => true,
            'defer'       => true,
        ];

        // Garante que links podem ter onclick (para JavaScript inline)
        if ( isset( $allowed_tags['a'] ) ) {
            $allowed_tags['a']['onclick'] = true;
        }

        return $allowed_tags;
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
            
            // SEGURANÇA: Usa wp_kses com lista personalizada de tags permitidas
            // que inclui elementos de formulário essenciais para as configurações.
            // O conteúdo original em render_admin_page() já aplica escape em valores de usuário.
            echo wp_kses( $content, $this->get_allowed_form_tags() );
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
            
            // SEGURANÇA: Usa wp_kses com lista personalizada de tags permitidas
            // que inclui elementos de formulário para filtros e exportação.
            echo wp_kses( $content, $this->get_allowed_form_tags() );
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
            
            // SEGURANÇA: Usa wp_kses com lista personalizada de tags permitidas
            // que inclui elementos de formulário para filtros.
            echo wp_kses( $content, $this->get_allowed_form_tags() );
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
            
            // SEGURANÇA: Usa wp_kses com lista personalizada de tags permitidas
            // que inclui elementos de formulário para gerenciar entradas.
            echo wp_kses( $content, $this->get_allowed_form_tags() );
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
            
            // SEGURANÇA: Usa wp_kses com lista personalizada de tags permitidas
            // que inclui elementos de formulário para testar perguntas.
            echo wp_kses( $content, $this->get_allowed_form_tags() );
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
            
            // SEGURANÇA: Usa wp_kses com lista personalizada de tags permitidas
            // que inclui elementos de formulário para a interface.
            echo wp_kses( $content, $this->get_allowed_form_tags() );
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
            
            // SEGURANÇA: Usa wp_kses com lista personalizada de tags permitidas
            // que inclui elementos de formulário.
            echo wp_kses( $content, $this->get_allowed_form_tags() );
        }
    }
}
