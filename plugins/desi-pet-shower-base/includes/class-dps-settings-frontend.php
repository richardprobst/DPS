<?php
/**
 * Página de Configurações do Frontend.
 *
 * Renderiza o shortcode [dps_configuracoes] com sistema de abas extensível
 * para gerenciamento de configurações do sistema DPS diretamente no front-end.
 *
 * @package DPS_Base_Plugin
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe responsável pelo frontend de configurações.
 */
class DPS_Settings_Frontend {

    /**
     * Slug do parâmetro de aba na URL.
     *
     * @var string
     */
    const TAB_PARAM = 'dps_settings_tab';

    /**
     * Nonce action para formulários de configuração.
     *
     * @var string
     */
    const NONCE_ACTION = 'dps_settings_save';

    /**
     * Nome do campo nonce.
     *
     * @var string
     */
    const NONCE_FIELD = 'dps_settings_nonce';

    /**
     * Abas registradas.
     *
     * @var array
     */
    private static $tabs = [];

    /**
     * Callbacks das abas registradas.
     *
     * @var array
     */
    private static $callbacks = [];

    /**
     * Verifica se a renderização deve ser ignorada (durante requisições REST/AJAX).
     * 
     * Previne o erro "Falha ao publicar. A resposta não é um JSON válido" no
     * Block Editor ao evitar renderização de shortcodes durante requisições REST.
     *
     * @since 2.0.0
     * @return bool True se a renderização deve ser ignorada.
     */
    private static function should_skip_rendering() {
        return ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || wp_doing_ajax();
    }

    /**
     * Inicializa o sistema de configurações.
     *
     * @return void
     */
    public static function init() {
        // Registra abas base do plugin core
        self::register_core_tabs();

        // Hook para add-ons registrarem suas abas
        add_action( 'dps_settings_register_tabs', [ __CLASS__, 'register_addon_tabs' ], 10 );

        // Handler para salvamento de configurações
        add_action( 'init', [ __CLASS__, 'maybe_handle_save' ], 20 );
    }

    /**
     * Registra as abas core do plugin base.
     *
     * @return void
     */
    private static function register_core_tabs() {
        // Aba Empresa - Configurações básicas do negócio
        self::register_tab(
            'empresa',
            __( '🏢 Empresa', 'desi-pet-shower' ),
            [ __CLASS__, 'render_tab_empresa' ],
            10
        );

        // Aba Segurança - Senhas e acessos
        self::register_tab(
            'seguranca',
            __( '🔐 Segurança', 'desi-pet-shower' ),
            [ __CLASS__, 'render_tab_seguranca' ],
            20
        );

        // ========================================
        // FASE 3: Abas de Add-ons Core
        // ========================================

        // Aba Portal do Cliente (se add-on ativo)
        if ( class_exists( 'DPS_Client_Portal' ) ) {
            self::register_tab(
                'portal',
                __( '📱 Portal do Cliente', 'desi-pet-shower' ),
                [ __CLASS__, 'render_tab_portal' ],
                30
            );

            // Aba Logins de Clientes (sempre disponível se Portal ativo)
            self::register_tab(
                'logins_clientes',
                __( '🔑 Logins de Clientes', 'desi-pet-shower' ),
                [ __CLASS__, 'render_tab_logins_clientes' ],
                31
            );
        }

        // Aba Comunicações (se add-on ativo)
        if ( class_exists( 'DPS_Communications_Addon' ) ) {
            self::register_tab(
                'comunicacoes',
                __( '💬 Comunicações', 'desi-pet-shower' ),
                [ __CLASS__, 'render_tab_comunicacoes' ],
                40
            );
        }

        // Aba Pagamentos (se add-on ativo)
        if ( class_exists( 'DPS_Payment_Addon' ) ) {
            self::register_tab(
                'pagamentos',
                __( '💳 Pagamentos', 'desi-pet-shower' ),
                [ __CLASS__, 'render_tab_pagamentos' ],
                50
            );
        }

        // ========================================
        // FASE 4: Abas de Automação
        // ========================================

        // Aba Relatórios Automáticos (se Push Add-on ativo)
        if ( class_exists( 'DPS_Push_Addon' ) ) {
            self::register_tab(
                'notificacoes',
                __( '📧 Relatórios Automáticos', 'desi-pet-shower' ),
                [ __CLASS__, 'render_tab_notificacoes' ],
                60
            );
        }

        // Aba Lembretes de Cobrança (se Finance Add-on ativo)
        if ( class_exists( 'DPS_Finance_Addon' ) ) {
            self::register_tab(
                'financeiro_lembretes',
                __( '💰 Lembretes de Cobrança', 'desi-pet-shower' ),
                [ __CLASS__, 'render_tab_financeiro_lembretes' ],
                70
            );
        }

        // ========================================
        // FASE 5: Abas Avançadas
        // ========================================

        // Aba Cadastro Público (se Registration Add-on ativo)
        if ( class_exists( 'DPS_Registration_Addon' ) ) {
            self::register_tab(
                'cadastro',
                __( '📝 Cadastro Público', 'desi-pet-shower' ),
                [ __CLASS__, 'render_tab_cadastro' ],
                80
            );
        }

        // Aba Assistente IA (se AI Add-on ativo)
        if ( class_exists( 'DPS_AI_Addon' ) ) {
            self::register_tab(
                'ia',
                __( '🤖 Assistente IA', 'desi-pet-shower' ),
                [ __CLASS__, 'render_tab_ia' ],
                90
            );
        }

        // Aba Fidelidade (se Loyalty Add-on ativo)
        if ( class_exists( 'DPS_Loyalty_Addon' ) ) {
            self::register_tab(
                'fidelidade',
                __( '🎁 Fidelidade', 'desi-pet-shower' ),
                [ __CLASS__, 'render_tab_fidelidade' ],
                100
            );
        }

        // ========================================
        // Aba Agenda (prioridade 35 - logo após Portal)
        // ========================================

        // Aba Agenda (se Agenda Add-on ativo)
        if ( class_exists( 'DPS_Agenda_Addon' ) ) {
            self::register_tab(
                'agenda',
                __( '⏰ Agenda', 'desi-pet-shower' ),
                [ __CLASS__, 'render_tab_agenda' ],
                35
            );
        }

        // ========================================
        // Aba Groomers (prioridade 105 - após Fidelidade)
        // ========================================

        // Aba Logins de Groomers (se Groomers Add-on ativo)
        if ( class_exists( 'DPS_Groomers_Addon' ) ) {
            self::register_tab(
                'groomers',
                __( '👤 Logins de Groomers', 'desi-pet-shower' ),
                [ __CLASS__, 'render_tab_groomers' ],
                105
            );
        }
    }

    /**
     * Registra abas de add-ons via hook.
     *
     * NOTA: O hook dps_settings_nav_tabs foi depreciado para renderização de HTML.
     * Add-ons agora devem registrar abas usando DPS_Settings_Frontend::register_tab()
     * ou o hook dps_settings_register_tabs para chamar register_tab() durante a inicialização.
     *
     * @deprecated 2.5.0 Use register_tab() em vez de hooks legados.
     * @return void
     */
    public static function register_addon_tabs() {
        /**
         * Hook depreciado - mantido para compatibilidade retroativa.
         *
         * Add-ons devem migrar para DPS_Settings_Frontend::register_tab()
         * que oferece melhor consistência visual e integração com o sistema moderno.
         *
         * @since 2.0.0
         * @deprecated 2.5.0
         * @param DPS_Settings_Frontend $instance Instância da classe.
         */
        do_action( 'dps_settings_nav_tabs' );
    }

    /**
     * Registra uma nova aba de configuração.
     *
     * @param string   $slug     Identificador único da aba.
     * @param string   $label    Rótulo exibido na navegação.
     * @param callable $callback Função que renderiza o conteúdo da aba.
     * @param int      $priority Ordem de exibição (menor = primeiro).
     * @return void
     */
    public static function register_tab( $slug, $label, $callback, $priority = 100 ) {
        if ( empty( $slug ) || empty( $label ) || ! is_callable( $callback ) ) {
            return;
        }

        self::$tabs[ $slug ] = [
            'label'    => $label,
            'priority' => $priority,
        ];

        self::$callbacks[ $slug ] = $callback;

        // Ordena abas por prioridade
        uasort( self::$tabs, function( $a, $b ) {
            return $a['priority'] <=> $b['priority'];
        } );
    }

    /**
     * Obtém a aba ativa da URL ou retorna a primeira registrada.
     *
     * @return string Slug da aba ativa.
     */
    public static function get_active_tab() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Parâmetro de navegação, sem modificação de dados
        $tab = isset( $_GET[ self::TAB_PARAM ] ) ? sanitize_key( wp_unslash( $_GET[ self::TAB_PARAM ] ) ) : '';

        // Valida se a aba existe
        if ( ! empty( $tab ) && isset( self::$tabs[ $tab ] ) ) {
            return $tab;
        }

        // Retorna a primeira aba como padrão
        return array_key_first( self::$tabs ) ?: 'empresa';
    }

    /**
     * Renderiza a página completa de configurações.
     *
     * @return string HTML da página de configurações.
     */
    public static function render() {
        // Evita renderizar durante requisições REST API (Block Editor) ou AJAX
        // para prevenir o erro "Falha ao publicar. A resposta não é um JSON válido."
        if ( self::should_skip_rendering() ) {
            return '';
        }

        // Inicializa abas se ainda não foram registradas
        if ( empty( self::$tabs ) ) {
            self::init();
        }

        // Dispara hook para add-ons registrarem suas abas
        do_action( 'dps_settings_register_tabs' );

        // Verifica permissão de administrador
        if ( ! current_user_can( 'manage_options' ) ) {
            return self::render_access_denied();
        }

        $active_tab = self::get_active_tab();

        ob_start();
        ?>
        <div class="dps-base-wrapper dps-settings-wrapper">
            <h1 class="dps-page-title">
                <span class="dashicons dashicons-admin-settings" style="font-size: 28px; width: 28px; height: 28px; margin-right: 8px; vertical-align: middle; color: #0ea5e9;"></span>
                <?php esc_html_e( 'Configurações do Sistema', 'desi-pet-shower' ); ?>
            </h1>

            <?php 
            // Exibe mensagens de feedback
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado em DPS_Message_Helper
            echo DPS_Message_Helper::display_messages();
            ?>

            <nav class="dps-nav-container dps-settings-nav" aria-label="<?php esc_attr_e( 'Navegação de configurações', 'desi-pet-shower' ); ?>">
                <button type="button" class="dps-nav-mobile-toggle" aria-expanded="false" aria-controls="dps-settings-nav">
                    <?php esc_html_e( 'Selecionar categoria', 'desi-pet-shower' ); ?>
                </button>
                <ul class="dps-nav" id="dps-settings-nav" role="tablist">
                    <?php self::render_nav_tabs( $active_tab ); ?>
                </ul>
            </nav>

            <div class="dps-settings-content">
                <?php self::render_tab_content( $active_tab ); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza a navegação por abas.
     *
     * @param string $active_tab Aba atualmente ativa.
     * @return void
     */
    private static function render_nav_tabs( $active_tab ) {
        $base_url = self::get_settings_page_url();

        foreach ( self::$tabs as $slug => $tab ) {
            $url         = add_query_arg( self::TAB_PARAM, $slug, $base_url );
            $is_active   = ( $active_tab === $slug );
            $active_attr = $is_active ? 'active' : '';

            printf(
                '<li role="presentation"><a href="%s" class="dps-tab-link %s" data-tab="%s" role="tab" aria-selected="%s">%s</a></li>',
                esc_url( $url ),
                esc_attr( $active_attr ),
                esc_attr( $slug ),
                $is_active ? 'true' : 'false',
                esc_html( $tab['label'] )
            );
        }

        // REMOVIDO: Hook legado dps_settings_nav_tabs
        // Os add-ons agora devem usar o sistema moderno de registro via register_tab()
        // ou via hook dps_settings_register_tabs para chamar register_tab().
        // O hook legado causava abas duplicadas e HTML inconsistente na interface.
    }

    /**
     * Renderiza o conteúdo da aba ativa.
     *
     * @param string $active_tab Aba atualmente ativa.
     * @return void
     */
    private static function render_tab_content( $active_tab ) {
        // Renderiza seções base
        foreach ( self::$tabs as $slug => $tab ) {
            $is_active = ( $active_tab === $slug );
            $display   = $is_active ? 'block' : 'none';

            echo '<div id="dps-settings-' . esc_attr( $slug ) . '" class="dps-section dps-settings-section' . ( $is_active ? ' active' : '' ) . '" style="display: ' . esc_attr( $display ) . ';" role="tabpanel">';

            if ( isset( self::$callbacks[ $slug ] ) && is_callable( self::$callbacks[ $slug ] ) ) {
                call_user_func( self::$callbacks[ $slug ] );
            }

            echo '</div>';
        }

        // REMOVIDO: Hook legado dps_settings_sections
        // Os add-ons agora devem usar o sistema moderno de registro via register_tab()
        // que renderiza o conteúdo automaticamente via callbacks.
        // O hook legado causava seções duplicadas e conflitos com o sistema moderno.
    }

    /**
     * Renderiza mensagem de acesso negado.
     *
     * @return string HTML da mensagem.
     */
    private static function render_access_denied() {
        ob_start();
        ?>
        <div class="dps-base-wrapper dps-settings-access-denied" style="max-width: 600px; margin: 40px auto; padding: 30px; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; text-align: center;">
            <div style="font-size: 48px; color: #ef4444; margin-bottom: 20px;">
                <span class="dashicons dashicons-lock" style="font-size: 48px; width: 48px; height: 48px;"></span>
            </div>
            <h2 style="color: #374151; margin-bottom: 16px;"><?php esc_html_e( 'Acesso Restrito', 'desi-pet-shower' ); ?></h2>
            <p style="font-size: 16px; color: #6b7280; line-height: 1.6;">
                <?php esc_html_e( 'Você precisa de permissões de administrador para acessar as configurações do sistema.', 'desi-pet-shower' ); ?>
            </p>
            <?php if ( ! is_user_logged_in() ) : ?>
                <p style="margin-top: 20px;">
                    <a href="<?php echo esc_url( wp_login_url( DPS_URL_Builder::safe_get_permalink() ) ); ?>" class="button button-primary" style="padding: 10px 24px;">
                        <?php esc_html_e( 'Fazer Login', 'desi-pet-shower' ); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Obtém a URL base da página de configurações.
     *
     * @return string URL da página atual sem parâmetros de aba.
     */
    public static function get_settings_page_url() {
        return DPS_URL_Builder::safe_get_permalink();
    }

    /**
     * Gera campo nonce para formulários.
     *
     * @return void
     */
    public static function nonce_field() {
        wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
    }

    /**
     * Verifica o nonce de uma submissão de formulário.
     *
     * @return bool True se válido, false caso contrário.
     */
    public static function verify_nonce() {
        $nonce = isset( $_POST[ self::NONCE_FIELD ] ) 
            ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ) 
            : '';

        return wp_verify_nonce( $nonce, self::NONCE_ACTION );
    }

    /**
     * Processa salvamento de configurações se houver submissão.
     *
     * @return void
     */
    public static function maybe_handle_save() {
        // Verifica se é uma submissão de formulário de configurações
        if ( ! isset( $_POST['dps_settings_action'] ) ) {
            return;
        }

        // Verifica permissão
        if ( ! current_user_can( 'manage_options' ) ) {
            DPS_Message_Helper::add_error( __( 'Você não tem permissão para modificar estas configurações.', 'desi-pet-shower' ) );
            return;
        }

        // Verifica nonce
        if ( ! self::verify_nonce() ) {
            DPS_Message_Helper::add_error( __( 'Sessão expirada. Por favor, recarregue a página e tente novamente.', 'desi-pet-shower' ) );
            return;
        }

        $action = sanitize_key( wp_unslash( $_POST['dps_settings_action'] ) );

        // Processa ações específicas
        switch ( $action ) {
            case 'save_empresa':
                self::handle_save_empresa();
                break;
            case 'save_seguranca':
                self::handle_save_seguranca();
                break;
            case 'save_portal':
                self::handle_save_portal();
                break;
            case 'save_comunicacoes':
                self::handle_save_comunicacoes();
                break;
            case 'save_pagamentos':
                self::handle_save_pagamentos();
                break;
            // ========================================
            // FASE 4: Handlers de Automação
            // ========================================
            case 'save_notificacoes':
                self::handle_save_notificacoes();
                break;
            case 'save_financeiro_lembretes':
                self::handle_save_financeiro_lembretes();
                break;
            // ========================================
            // FASE 5: Handlers de Abas Avançadas
            // ========================================
            case 'save_cadastro':
                self::handle_save_cadastro();
                break;
            case 'save_ia':
                self::handle_save_ia();
                break;
            case 'save_fidelidade':
                self::handle_save_fidelidade();
                break;
            // ========================================
            // FASE 6: Handler da Aba Agenda
            // ========================================
            case 'save_agenda':
                self::handle_save_agenda();
                break;
            default:
                /**
                 * Hook para add-ons processarem salvamento de suas configurações.
                 *
                 * @since 2.0.0
                 * @param string $action Ação de salvamento.
                 */
                do_action( 'dps_settings_save_' . $action );
                break;
        }

        // Redireciona para evitar resubmissão
        $redirect_url = add_query_arg( self::TAB_PARAM, self::get_active_tab(), self::get_settings_page_url() );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Renderiza a aba Empresa.
     *
     * @return void
     */
    public static function render_tab_empresa() {
        $shop_name        = get_option( 'dps_shop_name', get_bloginfo( 'name' ) );
        $shop_address     = get_option( 'dps_shop_address', '' );
        $business_address = get_option( 'dps_business_address', '' );
        $google_api_key   = get_option( 'dps_google_api_key', '' );
        $logger_min_level = get_option( 'dps_logger_min_level', DPS_Logger::LEVEL_INFO );

        $log_levels = [
            DPS_Logger::LEVEL_DEBUG   => __( 'Debug (todos os logs)', 'desi-pet-shower' ),
            DPS_Logger::LEVEL_INFO    => __( 'Info (padrão)', 'desi-pet-shower' ),
            DPS_Logger::LEVEL_WARNING => __( 'Warning (avisos e erros)', 'desi-pet-shower' ),
            DPS_Logger::LEVEL_ERROR   => __( 'Error (apenas erros)', 'desi-pet-shower' ),
        ];
        ?>
        <form method="post" action="" class="dps-settings-form">
            <?php self::nonce_field(); ?>
            <input type="hidden" name="dps_settings_action" value="save_empresa">

            <div class="dps-surface dps-surface--info">
                <h3 class="dps-surface__title">
                    <span class="dashicons dashicons-store"></span>
                    <?php esc_html_e( 'Dados da Empresa', 'desi-pet-shower' ); ?>
                </h3>
                <p class="dps-surface__description">
                    <?php esc_html_e( 'Informações básicas do seu pet shop exibidas em comunicações e no portal do cliente.', 'desi-pet-shower' ); ?>
                </p>

                <fieldset class="dps-fieldset">
                    <legend><?php esc_html_e( 'Identificação', 'desi-pet-shower' ); ?></legend>
                    
                    <div class="dps-form-row">
                        <label for="dps_shop_name"><?php esc_html_e( 'Nome do Petshop', 'desi-pet-shower' ); ?></label>
                        <input type="text" id="dps_shop_name" name="dps_shop_name" value="<?php echo esc_attr( $shop_name ); ?>" class="regular-text" />
                        <p class="description"><?php esc_html_e( 'Nome exibido em mensagens e documentos.', 'desi-pet-shower' ); ?></p>
                    </div>
                </fieldset>

                <fieldset class="dps-fieldset">
                    <legend><?php esc_html_e( 'Localização', 'desi-pet-shower' ); ?></legend>
                    
                    <div class="dps-form-row">
                        <label for="dps_shop_address"><?php esc_html_e( 'Endereço do Petshop', 'desi-pet-shower' ); ?></label>
                        <textarea id="dps_shop_address" name="dps_shop_address" rows="2" class="large-text"><?php echo esc_textarea( $shop_address ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Endereço completo para GPS e navegação.', 'desi-pet-shower' ); ?></p>
                    </div>

                    <div class="dps-form-row">
                        <label for="dps_business_address"><?php esc_html_e( 'Endereço Comercial', 'desi-pet-shower' ); ?></label>
                        <textarea id="dps_business_address" name="dps_business_address" rows="2" class="large-text"><?php echo esc_textarea( $business_address ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Endereço para convites de calendário e documentos formais.', 'desi-pet-shower' ); ?></p>
                    </div>
                </fieldset>

                <fieldset class="dps-fieldset">
                    <legend><?php esc_html_e( 'Integrações', 'desi-pet-shower' ); ?></legend>
                    
                    <div class="dps-form-row">
                        <label for="dps_google_api_key"><?php esc_html_e( 'Chave API Google Maps', 'desi-pet-shower' ); ?></label>
                        <input type="text" id="dps_google_api_key" name="dps_google_api_key" value="<?php echo esc_attr( self::mask_sensitive_value( $google_api_key ) ); ?>" class="regular-text" />
                        <p class="description"><?php esc_html_e( 'Chave para autocompletar endereços e exibir mapas.', 'desi-pet-shower' ); ?></p>
                    </div>
                </fieldset>

                <fieldset class="dps-fieldset">
                    <legend><?php esc_html_e( 'Sistema', 'desi-pet-shower' ); ?></legend>
                    
                    <div class="dps-form-row">
                        <label for="dps_logger_min_level"><?php esc_html_e( 'Nível de Log', 'desi-pet-shower' ); ?></label>
                        <select id="dps_logger_min_level" name="dps_logger_min_level" class="regular-text">
                            <?php foreach ( $log_levels as $level => $label ) : ?>
                                <option value="<?php echo esc_attr( $level ); ?>" <?php selected( $logger_min_level, $level ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'Define o nível mínimo de eventos registrados no log do sistema.', 'desi-pet-shower' ); ?></p>
                    </div>
                </fieldset>
            </div>

            <div class="dps-form-actions">
                <button type="submit" class="button button-primary button-large">
                    <span class="dashicons dashicons-saved" style="margin-top: 3px;"></span>
                    <?php esc_html_e( 'Salvar Configurações', 'desi-pet-shower' ); ?>
                </button>
            </div>
        </form>
        <?php
    }

    /**
     * Renderiza a aba Segurança.
     *
     * @return void
     */
    public static function render_tab_seguranca() {
        $base_password   = get_option( 'dps_base_password', '' );
        $agenda_password = get_option( 'dps_agenda_password', '' );
        ?>
        <form method="post" action="" class="dps-settings-form">
            <?php self::nonce_field(); ?>
            <input type="hidden" name="dps_settings_action" value="save_seguranca">

            <div class="dps-surface dps-surface--warning">
                <h3 class="dps-surface__title">
                    <span class="dashicons dashicons-shield"></span>
                    <?php esc_html_e( 'Senhas de Acesso', 'desi-pet-shower' ); ?>
                </h3>
                <p class="dps-surface__description">
                    <?php esc_html_e( 'Senhas utilizadas para controlar acesso ao painel de gestão e à agenda.', 'desi-pet-shower' ); ?>
                </p>

                <div class="dps-notice dps-notice--info" style="margin-bottom: 20px;">
                    <span class="dashicons dashicons-info"></span>
                    <?php esc_html_e( 'Estas senhas são usadas para autenticação simples em páginas protegidas. Para segurança avançada, utilize o controle de acesso nativo do WordPress.', 'desi-pet-shower' ); ?>
                </div>

                <fieldset class="dps-fieldset">
                    <legend><?php esc_html_e( 'Controle de Acesso', 'desi-pet-shower' ); ?></legend>
                    
                    <div class="dps-form-row">
                        <label for="dps_base_password"><?php esc_html_e( 'Senha do Painel Base', 'desi-pet-shower' ); ?></label>
                        <input type="password" id="dps_base_password" name="dps_base_password" value="<?php echo esc_attr( $base_password ); ?>" class="regular-text" autocomplete="new-password" />
                        <p class="description"><?php esc_html_e( 'Senha para acessar o painel de gestão principal.', 'desi-pet-shower' ); ?></p>
                    </div>

                    <div class="dps-form-row">
                        <label for="dps_agenda_password"><?php esc_html_e( 'Senha da Agenda', 'desi-pet-shower' ); ?></label>
                        <input type="password" id="dps_agenda_password" name="dps_agenda_password" value="<?php echo esc_attr( $agenda_password ); ?>" class="regular-text" autocomplete="new-password" />
                        <p class="description"><?php esc_html_e( 'Senha para acessar a página de agenda.', 'desi-pet-shower' ); ?></p>
                    </div>
                </fieldset>
            </div>

            <div class="dps-form-actions">
                <button type="submit" class="button button-primary button-large">
                    <span class="dashicons dashicons-saved" style="margin-top: 3px;"></span>
                    <?php esc_html_e( 'Salvar Configurações', 'desi-pet-shower' ); ?>
                </button>
            </div>
        </form>
        <?php
    }

    /**
     * Processa salvamento da aba Empresa.
     *
     * @return void
     */
    private static function handle_save_empresa() {
        $fields = [
            'dps_shop_name'        => 'sanitize_text_field',
            'dps_shop_address'     => 'sanitize_textarea_field',
            'dps_business_address' => 'sanitize_textarea_field',
            'dps_google_api_key'   => 'sanitize_text_field',
            'dps_logger_min_level' => 'sanitize_key',
        ];

        foreach ( $fields as $option => $sanitize ) {
            if ( isset( $_POST[ $option ] ) ) {
                $value = wp_unslash( $_POST[ $option ] );
                
                // Para API key, só atualiza se não for o valor mascarado
                if ( 'dps_google_api_key' === $option && self::is_masked_value( $value ) ) {
                    continue;
                }

                $sanitized = is_callable( $sanitize ) ? call_user_func( $sanitize, $value ) : sanitize_text_field( $value );
                update_option( $option, $sanitized );
            }
        }

        // Log de auditoria
        DPS_Logger::log(
            sprintf(
                /* translators: %d: User ID who modified settings */
                __( 'Configurações da empresa atualizadas pelo usuário ID %d', 'desi-pet-shower' ),
                get_current_user_id()
            ),
            DPS_Logger::LEVEL_INFO,
            'settings_updated'
        );

        DPS_Message_Helper::add_success( __( 'Configurações da empresa salvas com sucesso!', 'desi-pet-shower' ) );
    }

    /**
     * Processa salvamento da aba Segurança.
     *
     * @return void
     */
    private static function handle_save_seguranca() {
        $fields = [
            'dps_base_password'   => 'sanitize_text_field',
            'dps_agenda_password' => 'sanitize_text_field',
        ];

        foreach ( $fields as $option => $sanitize ) {
            if ( isset( $_POST[ $option ] ) ) {
                $value     = wp_unslash( $_POST[ $option ] );
                $sanitized = call_user_func( $sanitize, $value );
                
                // Só atualiza se não estiver vazio (permite manter senha atual)
                if ( ! empty( $sanitized ) ) {
                    update_option( $option, $sanitized );
                }
            }
        }

        // Log de auditoria (sem expor as senhas)
        DPS_Logger::log(
            sprintf(
                /* translators: %d: User ID who modified settings */
                __( 'Configurações de segurança atualizadas pelo usuário ID %d', 'desi-pet-shower' ),
                get_current_user_id()
            ),
            DPS_Logger::LEVEL_WARNING,
            'security_settings_updated'
        );

        DPS_Message_Helper::add_success( __( 'Configurações de segurança salvas com sucesso!', 'desi-pet-shower' ) );
    }

    /**
     * Sanitiza número de telefone.
     *
     * @param string $phone Telefone bruto.
     * @return string Telefone sanitizado.
     */
    private static function sanitize_phone( $phone ) {
        // Remove caracteres não numéricos exceto + no início
        $sanitized = preg_replace( '/[^0-9+]/', '', $phone );
        
        // Garante que + só aparece no início
        if ( strpos( $sanitized, '+' ) > 0 ) {
            $sanitized = str_replace( '+', '', $sanitized );
        }

        return $sanitized;
    }

    /**
     * Mascara valor sensível para exibição.
     *
     * @param string $value Valor original.
     * @return string Valor mascarado (últimos 4 caracteres visíveis).
     */
    private static function mask_sensitive_value( $value ) {
        if ( empty( $value ) || strlen( $value ) < 8 ) {
            return $value;
        }

        $visible_chars = 4;
        $masked_length = strlen( $value ) - $visible_chars;
        
        return str_repeat( '•', $masked_length ) . substr( $value, -$visible_chars );
    }

    /**
     * Verifica se um valor está mascarado.
     *
     * @param string $value Valor a verificar.
     * @return bool True se contém caracteres de máscara.
     */
    private static function is_masked_value( $value ) {
        return strpos( $value, '•' ) !== false;
    }

    // ========================================
    // FASE 3: Abas de Add-ons Core
    // ========================================

    /**
     * Renderiza a aba Portal do Cliente.
     *
     * @return void
     */
    public static function render_tab_portal() {
        if ( ! class_exists( 'DPS_Client_Portal' ) ) {
            echo '<p>' . esc_html__( 'O add-on Portal do Cliente não está ativo.', 'desi-pet-shower' ) . '</p>';
            return;
        }

        $portal_page_id       = (int) get_option( 'dps_portal_page_id', 0 );
        $logo_id              = get_option( 'dps_portal_logo_id', '' );
        $primary_color        = get_option( 'dps_portal_primary_color', '#0ea5e9' );
        $hero_id              = get_option( 'dps_portal_hero_id', '' );
        $review_url           = get_option( 'dps_portal_review_url', '' );
        $access_notification  = get_option( 'dps_portal_access_notification_enabled', false );

        // Obtém lista de páginas para o selector
        $pages = get_pages( [ 'post_status' => 'publish' ] );
        ?>
        <form method="post" action="" class="dps-settings-form" enctype="multipart/form-data">
            <?php self::nonce_field(); ?>
            <input type="hidden" name="dps_settings_action" value="save_portal">

            <div class="dps-surface dps-surface--info">
                <h3 class="dps-surface__title">
                    <span class="dashicons dashicons-smartphone"></span>
                    <?php esc_html_e( 'Portal do Cliente', 'desi-pet-shower' ); ?>
                </h3>
                <p class="dps-surface__description">
                    <?php esc_html_e( 'Configure a página e aparência do portal de autoatendimento dos clientes.', 'desi-pet-shower' ); ?>
                </p>

                <fieldset class="dps-fieldset">
                    <legend><?php esc_html_e( 'Página do Portal', 'desi-pet-shower' ); ?></legend>
                    
                    <div class="dps-form-row">
                        <label for="dps_portal_page_id"><?php esc_html_e( 'Página do Portal', 'desi-pet-shower' ); ?></label>
                        <select id="dps_portal_page_id" name="dps_portal_page_id" class="regular-text">
                            <option value=""><?php esc_html_e( '— Selecione uma página —', 'desi-pet-shower' ); ?></option>
                            <?php foreach ( $pages as $page ) : ?>
                                <option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( $portal_page_id, $page->ID ); ?>>
                                    <?php echo esc_html( $page->post_title ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'Página onde o shortcode [dps_portal] está inserido.', 'desi-pet-shower' ); ?></p>
                    </div>
                </fieldset>

                <fieldset class="dps-fieldset">
                    <legend><?php esc_html_e( 'Personalização Visual', 'desi-pet-shower' ); ?></legend>
                    
                    <div class="dps-form-row">
                        <label for="dps_portal_primary_color"><?php esc_html_e( 'Cor Primária', 'desi-pet-shower' ); ?></label>
                        <input type="color" id="dps_portal_primary_color" name="dps_portal_primary_color" value="<?php echo esc_attr( $primary_color ); ?>" style="width: 60px; height: 40px; padding: 2px;" />
                        <span style="margin-left: 10px; color: #6b7280;"><?php echo esc_html( $primary_color ); ?></span>
                        <p class="description"><?php esc_html_e( 'Cor principal usada em botões e destaques do portal.', 'desi-pet-shower' ); ?></p>
                    </div>

                    <div class="dps-form-row">
                        <label for="dps_portal_logo_id"><?php esc_html_e( 'Logo do Portal', 'desi-pet-shower' ); ?></label>
                        <input type="text" id="dps_portal_logo_id" name="dps_portal_logo_id" value="<?php echo esc_attr( $logo_id ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'ID do anexo ou URL', 'desi-pet-shower' ); ?>" />
                        <?php if ( $logo_id && is_numeric( $logo_id ) ) : ?>
                            <div style="margin-top: 10px;">
                                <?php echo wp_get_attachment_image( (int) $logo_id, 'thumbnail', false, [ 'style' => 'max-height: 60px; width: auto;' ] ); ?>
                            </div>
                        <?php endif; ?>
                        <p class="description"><?php esc_html_e( 'ID do anexo da logo na biblioteca de mídia.', 'desi-pet-shower' ); ?></p>
                    </div>

                    <div class="dps-form-row">
                        <label for="dps_portal_hero_id"><?php esc_html_e( 'Imagem Hero', 'desi-pet-shower' ); ?></label>
                        <input type="text" id="dps_portal_hero_id" name="dps_portal_hero_id" value="<?php echo esc_attr( $hero_id ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'ID do anexo ou URL', 'desi-pet-shower' ); ?>" />
                        <?php if ( $hero_id && is_numeric( $hero_id ) ) : ?>
                            <div style="margin-top: 10px;">
                                <?php echo wp_get_attachment_image( (int) $hero_id, 'medium', false, [ 'style' => 'max-height: 100px; width: auto;' ] ); ?>
                            </div>
                        <?php endif; ?>
                        <p class="description"><?php esc_html_e( 'Imagem de destaque exibida no topo do portal.', 'desi-pet-shower' ); ?></p>
                    </div>
                </fieldset>

                <fieldset class="dps-fieldset">
                    <legend><?php esc_html_e( 'Configurações Adicionais', 'desi-pet-shower' ); ?></legend>
                    
                    <div class="dps-form-row">
                        <label for="dps_portal_review_url"><?php esc_html_e( 'URL de Avaliação', 'desi-pet-shower' ); ?></label>
                        <input type="url" id="dps_portal_review_url" name="dps_portal_review_url" value="<?php echo esc_url( $review_url ); ?>" class="regular-text" placeholder="https://g.page/r/..." />
                        <p class="description"><?php esc_html_e( 'Link para avaliação no Google ou outra plataforma (exibido após atendimento).', 'desi-pet-shower' ); ?></p>
                    </div>

                    <div class="dps-form-row">
                        <label class="dps-checkbox-label">
                            <input type="checkbox" id="dps_portal_access_notification_enabled" name="dps_portal_access_notification_enabled" value="1" <?php checked( $access_notification ); ?> />
                            <?php esc_html_e( 'Notificar administradores sobre acessos ao portal', 'desi-pet-shower' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Envia notificação quando um cliente acessa o portal.', 'desi-pet-shower' ); ?></p>
                    </div>
                </fieldset>
            </div>

            <div class="dps-form-actions">
                <button type="submit" class="button button-primary button-large">
                    <span class="dashicons dashicons-saved" style="margin-top: 3px;"></span>
                    <?php esc_html_e( 'Salvar Configurações', 'desi-pet-shower' ); ?>
                </button>
            </div>
        </form>
        <?php
    }

    /**
     * Renderiza a aba Comunicações.
     *
     * @return void
     */
    public static function render_tab_comunicacoes() {
        if ( ! class_exists( 'DPS_Communications_Addon' ) ) {
            echo '<p>' . esc_html__( 'O add-on Comunicações não está ativo.', 'desi-pet-shower' ) . '</p>';
            return;
        }

        $whatsapp_number = get_option( 'dps_whatsapp_number', '' );
        
        // Carrega configurações do add-on se disponível
        $comm_settings = get_option( 'dps_comm_settings', [] );
        $api_url       = $comm_settings['whatsapp_api_url'] ?? '';
        $api_token     = $comm_settings['whatsapp_api_token'] ?? '';
        ?>
        <form method="post" action="" class="dps-settings-form">
            <?php self::nonce_field(); ?>
            <input type="hidden" name="dps_settings_action" value="save_comunicacoes">

            <div class="dps-surface dps-surface--success">
                <h3 class="dps-surface__title">
                    <span class="dashicons dashicons-format-chat"></span>
                    <?php esc_html_e( 'Comunicações', 'desi-pet-shower' ); ?>
                </h3>
                <p class="dps-surface__description">
                    <?php esc_html_e( 'Configure integrações de WhatsApp e canais de comunicação com clientes.', 'desi-pet-shower' ); ?>
                </p>

                <fieldset class="dps-fieldset">
                    <legend><?php esc_html_e( 'WhatsApp', 'desi-pet-shower' ); ?></legend>
                    
                    <div class="dps-form-row">
                        <label for="dps_whatsapp_number"><?php esc_html_e( 'Número WhatsApp', 'desi-pet-shower' ); ?></label>
                        <input type="text" id="dps_whatsapp_number" name="dps_whatsapp_number" value="<?php echo esc_attr( $whatsapp_number ); ?>" class="regular-text" placeholder="+55 11 99999-9999" />
                        <p class="description"><?php esc_html_e( 'Número principal para comunicação via WhatsApp. Use formato internacional.', 'desi-pet-shower' ); ?></p>
                    </div>
                </fieldset>

                <fieldset class="dps-fieldset">
                    <legend><?php esc_html_e( 'API WhatsApp (Avançado)', 'desi-pet-shower' ); ?></legend>
                    
                    <div class="dps-notice dps-notice--warning" style="margin-bottom: 16px;">
                        <span class="dashicons dashicons-warning"></span>
                        <?php esc_html_e( 'Configure apenas se você possui uma API de WhatsApp Business. Deixe em branco para usar links padrão.', 'desi-pet-shower' ); ?>
                    </div>

                    <div class="dps-form-row">
                        <label for="dps_comm_whatsapp_api_url"><?php esc_html_e( 'URL da API', 'desi-pet-shower' ); ?></label>
                        <input type="url" id="dps_comm_whatsapp_api_url" name="dps_comm_whatsapp_api_url" value="<?php echo esc_url( $api_url ); ?>" class="regular-text" placeholder="https://api.whatsapp.com/..." />
                        <p class="description"><?php esc_html_e( 'Endpoint da API de envio de mensagens.', 'desi-pet-shower' ); ?></p>
                    </div>

                    <div class="dps-form-row">
                        <label for="dps_comm_whatsapp_api_token"><?php esc_html_e( 'Token da API', 'desi-pet-shower' ); ?></label>
                        <input type="password" id="dps_comm_whatsapp_api_token" name="dps_comm_whatsapp_api_token" value="<?php echo esc_attr( self::mask_sensitive_value( $api_token ) ); ?>" class="regular-text" autocomplete="new-password" />
                        <p class="description"><?php esc_html_e( 'Token de autenticação da API.', 'desi-pet-shower' ); ?></p>
                    </div>
                </fieldset>
            </div>

            <div class="dps-form-actions">
                <button type="submit" class="button button-primary button-large">
                    <span class="dashicons dashicons-saved" style="margin-top: 3px;"></span>
                    <?php esc_html_e( 'Salvar Configurações', 'desi-pet-shower' ); ?>
                </button>
            </div>
        </form>
        <?php
    }

    /**
     * Renderiza a aba Pagamentos.
     *
     * @return void
     */
    public static function render_tab_pagamentos() {
        if ( ! class_exists( 'DPS_Payment_Addon' ) ) {
            echo '<p>' . esc_html__( 'O add-on Pagamentos não está ativo.', 'desi-pet-shower' ) . '</p>';
            return;
        }

        $access_token    = get_option( 'dps_mercadopago_access_token', '' );
        $public_key      = get_option( 'dps_mercadopago_public_key', '' );
        $webhook_secret  = get_option( 'dps_mercadopago_webhook_secret', '' );
        $pix_key         = get_option( 'dps_pix_key', '' );
        ?>
        <form method="post" action="" class="dps-settings-form">
            <?php self::nonce_field(); ?>
            <input type="hidden" name="dps_settings_action" value="save_pagamentos">

            <div class="dps-surface dps-surface--success">
                <h3 class="dps-surface__title">
                    <span class="dashicons dashicons-money-alt"></span>
                    <?php esc_html_e( 'Pagamentos', 'desi-pet-shower' ); ?>
                </h3>
                <p class="dps-surface__description">
                    <?php esc_html_e( 'Configure integração com Mercado Pago e chaves de pagamento.', 'desi-pet-shower' ); ?>
                </p>

                <div class="dps-notice dps-notice--warning" style="margin-bottom: 20px;">
                    <span class="dashicons dashicons-shield"></span>
                    <?php esc_html_e( 'Atenção: Estas são credenciais sensíveis. Mantenha-as em segurança e nunca compartilhe.', 'desi-pet-shower' ); ?>
                </div>

                <fieldset class="dps-fieldset">
                    <legend><?php esc_html_e( 'Mercado Pago', 'desi-pet-shower' ); ?></legend>
                    
                    <div class="dps-form-row">
                        <label for="dps_mercadopago_access_token"><?php esc_html_e( 'Access Token', 'desi-pet-shower' ); ?></label>
                        <input type="password" id="dps_mercadopago_access_token" name="dps_mercadopago_access_token" value="<?php echo esc_attr( self::mask_sensitive_value( $access_token ) ); ?>" class="regular-text" autocomplete="new-password" />
                        <p class="description"><?php esc_html_e( 'Token de acesso da sua conta Mercado Pago (começa com APP_USR-).', 'desi-pet-shower' ); ?></p>
                    </div>

                    <div class="dps-form-row">
                        <label for="dps_mercadopago_public_key"><?php esc_html_e( 'Chave Pública', 'desi-pet-shower' ); ?></label>
                        <input type="text" id="dps_mercadopago_public_key" name="dps_mercadopago_public_key" value="<?php echo esc_attr( self::mask_sensitive_value( $public_key ) ); ?>" class="regular-text" />
                        <p class="description"><?php esc_html_e( 'Chave pública para integrações no frontend (começa com APP_USR-).', 'desi-pet-shower' ); ?></p>
                    </div>

                    <div class="dps-form-row">
                        <label for="dps_mercadopago_webhook_secret"><?php esc_html_e( 'Webhook Secret', 'desi-pet-shower' ); ?></label>
                        <input type="password" id="dps_mercadopago_webhook_secret" name="dps_mercadopago_webhook_secret" value="<?php echo esc_attr( self::mask_sensitive_value( $webhook_secret ) ); ?>" class="regular-text" autocomplete="new-password" />
                        <p class="description"><?php esc_html_e( 'Chave secreta para validação de webhooks do Mercado Pago.', 'desi-pet-shower' ); ?></p>
                    </div>
                </fieldset>

                <fieldset class="dps-fieldset">
                    <legend><?php esc_html_e( 'PIX', 'desi-pet-shower' ); ?></legend>
                    
                    <div class="dps-form-row">
                        <label for="dps_pix_key"><?php esc_html_e( 'Chave PIX', 'desi-pet-shower' ); ?></label>
                        <input type="text" id="dps_pix_key" name="dps_pix_key" value="<?php echo esc_attr( $pix_key ); ?>" class="regular-text" placeholder="email@exemplo.com ou CPF/CNPJ" />
                        <p class="description"><?php esc_html_e( 'Chave PIX para recebimentos (email, CPF, CNPJ ou chave aleatória).', 'desi-pet-shower' ); ?></p>
                    </div>
                </fieldset>
            </div>

            <div class="dps-form-actions">
                <button type="submit" class="button button-primary button-large">
                    <span class="dashicons dashicons-saved" style="margin-top: 3px;"></span>
                    <?php esc_html_e( 'Salvar Configurações', 'desi-pet-shower' ); ?>
                </button>
            </div>
        </form>
        <?php
    }

    /**
     * Processa salvamento da aba Portal.
     *
     * @return void
     */
    private static function handle_save_portal() {
        // Página do portal
        if ( isset( $_POST['dps_portal_page_id'] ) ) {
            $page_id = absint( wp_unslash( $_POST['dps_portal_page_id'] ) );
            update_option( 'dps_portal_page_id', $page_id );
        }

        // Cor primária
        if ( isset( $_POST['dps_portal_primary_color'] ) ) {
            $color = sanitize_hex_color( wp_unslash( $_POST['dps_portal_primary_color'] ) );
            if ( $color ) {
                update_option( 'dps_portal_primary_color', $color );
            }
        }

        // Logo ID
        if ( isset( $_POST['dps_portal_logo_id'] ) ) {
            $logo_id = sanitize_text_field( wp_unslash( $_POST['dps_portal_logo_id'] ) );
            update_option( 'dps_portal_logo_id', $logo_id );
        }

        // Hero ID
        if ( isset( $_POST['dps_portal_hero_id'] ) ) {
            $hero_id = sanitize_text_field( wp_unslash( $_POST['dps_portal_hero_id'] ) );
            update_option( 'dps_portal_hero_id', $hero_id );
        }

        // URL de avaliação
        if ( isset( $_POST['dps_portal_review_url'] ) ) {
            $review_url = esc_url_raw( wp_unslash( $_POST['dps_portal_review_url'] ) );
            update_option( 'dps_portal_review_url', $review_url );
        }

        // Notificação de acesso
        $access_notification = ! empty( $_POST['dps_portal_access_notification_enabled'] );
        update_option( 'dps_portal_access_notification_enabled', $access_notification );

        // Log de auditoria
        DPS_Logger::log(
            sprintf(
                /* translators: %d: User ID who modified settings */
                __( 'Configurações do Portal atualizadas pelo usuário ID %d', 'desi-pet-shower' ),
                get_current_user_id()
            ),
            DPS_Logger::LEVEL_INFO,
            'portal_settings_updated'
        );

        DPS_Message_Helper::add_success( __( 'Configurações do Portal salvas com sucesso!', 'desi-pet-shower' ) );
    }

    /**
     * Processa salvamento da aba Comunicações.
     *
     * @return void
     */
    private static function handle_save_comunicacoes() {
        // Número WhatsApp
        if ( isset( $_POST['dps_whatsapp_number'] ) ) {
            $whatsapp = self::sanitize_phone( wp_unslash( $_POST['dps_whatsapp_number'] ) );
            update_option( 'dps_whatsapp_number', $whatsapp );
        }

        // Configurações da API (se fornecidas)
        $comm_settings = get_option( 'dps_comm_settings', [] );
        
        if ( isset( $_POST['dps_comm_whatsapp_api_url'] ) ) {
            $api_url = esc_url_raw( wp_unslash( $_POST['dps_comm_whatsapp_api_url'] ) );
            $comm_settings['whatsapp_api_url'] = $api_url;
        }

        if ( isset( $_POST['dps_comm_whatsapp_api_token'] ) ) {
            $api_token = sanitize_text_field( wp_unslash( $_POST['dps_comm_whatsapp_api_token'] ) );
            // Só atualiza se não for valor mascarado
            if ( ! self::is_masked_value( $api_token ) && ! empty( $api_token ) ) {
                $comm_settings['whatsapp_api_token'] = $api_token;
            }
        }

        update_option( 'dps_comm_settings', $comm_settings );

        // Log de auditoria
        DPS_Logger::log(
            sprintf(
                /* translators: %d: User ID who modified settings */
                __( 'Configurações de Comunicações atualizadas pelo usuário ID %d', 'desi-pet-shower' ),
                get_current_user_id()
            ),
            DPS_Logger::LEVEL_INFO,
            'communications_settings_updated'
        );

        DPS_Message_Helper::add_success( __( 'Configurações de Comunicações salvas com sucesso!', 'desi-pet-shower' ) );
    }

    /**
     * Processa salvamento da aba Pagamentos.
     *
     * @return void
     */
    private static function handle_save_pagamentos() {
        $sensitive_fields = [
            'dps_mercadopago_access_token',
            'dps_mercadopago_public_key',
            'dps_mercadopago_webhook_secret',
        ];

        foreach ( $sensitive_fields as $field ) {
            if ( isset( $_POST[ $field ] ) ) {
                $value = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
                // Só atualiza se não for valor mascarado e não estiver vazio
                if ( ! self::is_masked_value( $value ) && ! empty( $value ) ) {
                    update_option( $field, $value );
                }
            }
        }

        // Chave PIX (não é sensível da mesma forma)
        if ( isset( $_POST['dps_pix_key'] ) ) {
            $pix_key = sanitize_text_field( wp_unslash( $_POST['dps_pix_key'] ) );
            update_option( 'dps_pix_key', $pix_key );
        }

        // Log de auditoria
        DPS_Logger::log(
            sprintf(
                /* translators: %d: User ID who modified settings */
                __( 'Configurações de Pagamentos atualizadas pelo usuário ID %d', 'desi-pet-shower' ),
                get_current_user_id()
            ),
            DPS_Logger::LEVEL_WARNING,
            'payment_settings_updated'
        );

        DPS_Message_Helper::add_success( __( 'Configurações de Pagamentos salvas com sucesso!', 'desi-pet-shower' ) );
    }

    // ========================================
    // FASE 4: Abas de Automação
    // ========================================

    /**
     * Renderiza a aba Notificações (Push Add-on).
     *
     * @return void
     */
    public static function render_tab_notificacoes() {
        if ( ! class_exists( 'DPS_Push_Addon' ) ) {
            echo '<p>' . esc_html__( 'O add-on Notificações não está ativo.', 'desi-pet-shower' ) . '</p>';
            return;
        }

        // Carrega configurações existentes
        $emails_agenda   = get_option( 'dps_push_emails_agenda', get_option( 'admin_email' ) );
        $emails_report   = get_option( 'dps_push_emails_report', get_option( 'admin_email' ) );
        $agenda_time     = get_option( 'dps_push_agenda_time', '08:00' );
        $report_time     = get_option( 'dps_push_report_time', '19:00' );
        $weekly_day      = get_option( 'dps_push_weekly_day', 'monday' );
        $weekly_time     = get_option( 'dps_push_weekly_time', '08:00' );
        $inactive_days   = get_option( 'dps_push_inactive_days', 30 );
        $telegram_token  = get_option( 'dps_push_telegram_token', '' );
        $telegram_chat   = get_option( 'dps_push_telegram_chat', '' );
        $agenda_enabled  = get_option( 'dps_push_agenda_enabled', true );
        $report_enabled  = get_option( 'dps_push_report_enabled', true );
        $weekly_enabled  = get_option( 'dps_push_weekly_enabled', true );

        // Formata emails para exibição
        $emails_agenda_display = is_array( $emails_agenda ) ? implode( ', ', $emails_agenda ) : $emails_agenda;
        $emails_report_display = is_array( $emails_report ) ? implode( ', ', $emails_report ) : $emails_report;

        // Próximos envios agendados
        $next_agenda = wp_next_scheduled( 'dps_send_agenda_notification' );
        $next_report = wp_next_scheduled( 'dps_send_daily_report' );
        $next_weekly = wp_next_scheduled( 'dps_send_weekly_inactive_report' );

        // Dias da semana para select
        $weekdays = [
            'monday'    => __( 'Segunda-feira', 'desi-pet-shower' ),
            'tuesday'   => __( 'Terça-feira', 'desi-pet-shower' ),
            'wednesday' => __( 'Quarta-feira', 'desi-pet-shower' ),
            'thursday'  => __( 'Quinta-feira', 'desi-pet-shower' ),
            'friday'    => __( 'Sexta-feira', 'desi-pet-shower' ),
            'saturday'  => __( 'Sábado', 'desi-pet-shower' ),
            'sunday'    => __( 'Domingo', 'desi-pet-shower' ),
        ];
        ?>
        <form method="post" action="" class="dps-settings-form">
            <?php self::nonce_field(); ?>
            <input type="hidden" name="dps_settings_action" value="save_notificacoes">

            <!-- Relatório da Manhã -->
            <div class="dps-surface dps-surface--info">
                <h3 class="dps-surface__title">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <?php esc_html_e( 'Relatório da Manhã – Agenda do Dia', 'desi-pet-shower' ); ?>
                </h3>
                <p class="dps-surface__description">
                    <?php esc_html_e( 'Receba no início do dia um resumo com todos os agendamentos programados.', 'desi-pet-shower' ); ?>
                </p>

                <fieldset class="dps-fieldset">
                    <legend><?php esc_html_e( 'Configurações', 'desi-pet-shower' ); ?></legend>

                    <div class="dps-form-row">
                        <label class="dps-checkbox-label">
                            <input type="checkbox" name="dps_push_agenda_enabled" value="1" <?php checked( $agenda_enabled ); ?> />
                            <strong><?php esc_html_e( 'Ativar relatório da manhã', 'desi-pet-shower' ); ?></strong>
                        </label>
                    </div>

                    <div class="dps-form-row">
                        <label for="dps_push_agenda_time"><?php esc_html_e( 'Horário de envio', 'desi-pet-shower' ); ?></label>
                        <input type="time" id="dps_push_agenda_time" name="dps_push_agenda_time" value="<?php echo esc_attr( $agenda_time ); ?>" class="regular-text" style="width: 120px;" />
                        <?php if ( $agenda_enabled && $next_agenda ) : ?>
                            <span class="dps-next-schedule">
                                ✓ <?php esc_html_e( 'Próximo:', 'desi-pet-shower' ); ?> <?php echo esc_html( date_i18n( 'd/m H:i', $next_agenda ) ); ?>
                            </span>
                        <?php endif; ?>
                        <p class="description"><?php esc_html_e( 'Horário em que o relatório será enviado diariamente.', 'desi-pet-shower' ); ?></p>
                    </div>

                    <div class="dps-form-row">
                        <label for="dps_push_emails_agenda"><?php esc_html_e( 'Destinatários', 'desi-pet-shower' ); ?></label>
                        <input type="text" id="dps_push_emails_agenda" name="dps_push_emails_agenda" value="<?php echo esc_attr( $emails_agenda_display ); ?>" class="regular-text" placeholder="email1@exemplo.com, email2@exemplo.com" />
                        <p class="description"><?php esc_html_e( 'Separe múltiplos emails por vírgula.', 'desi-pet-shower' ); ?></p>
                    </div>
                </fieldset>
            </div>

            <!-- Relatório do Final do Dia -->
            <div class="dps-surface dps-surface--neutral" style="margin-top: 20px;">
                <h3 class="dps-surface__title">
                    <span class="dashicons dashicons-chart-bar"></span>
                    <?php esc_html_e( 'Relatório do Final do Dia – Resumo Financeiro', 'desi-pet-shower' ); ?>
                </h3>
                <p class="dps-surface__description">
                    <?php esc_html_e( 'Receba no final do expediente um balanço com receitas, despesas e atendimentos.', 'desi-pet-shower' ); ?>
                </p>

                <fieldset class="dps-fieldset">
                    <legend><?php esc_html_e( 'Configurações', 'desi-pet-shower' ); ?></legend>

                    <div class="dps-form-row">
                        <label class="dps-checkbox-label">
                            <input type="checkbox" name="dps_push_report_enabled" value="1" <?php checked( $report_enabled ); ?> />
                            <strong><?php esc_html_e( 'Ativar relatório do final do dia', 'desi-pet-shower' ); ?></strong>
                        </label>
                    </div>

                    <div class="dps-form-row">
                        <label for="dps_push_report_time"><?php esc_html_e( 'Horário de envio', 'desi-pet-shower' ); ?></label>
                        <input type="time" id="dps_push_report_time" name="dps_push_report_time" value="<?php echo esc_attr( $report_time ); ?>" class="regular-text" style="width: 120px;" />
                        <?php if ( $report_enabled && $next_report ) : ?>
                            <span class="dps-next-schedule">
                                ✓ <?php esc_html_e( 'Próximo:', 'desi-pet-shower' ); ?> <?php echo esc_html( date_i18n( 'd/m H:i', $next_report ) ); ?>
                            </span>
                        <?php endif; ?>
                        <p class="description"><?php esc_html_e( 'Horário em que o relatório será enviado diariamente.', 'desi-pet-shower' ); ?></p>
                    </div>

                    <div class="dps-form-row">
                        <label for="dps_push_emails_report"><?php esc_html_e( 'Destinatários', 'desi-pet-shower' ); ?></label>
                        <input type="text" id="dps_push_emails_report" name="dps_push_emails_report" value="<?php echo esc_attr( $emails_report_display ); ?>" class="regular-text" placeholder="email1@exemplo.com, email2@exemplo.com" />
                        <p class="description"><?php esc_html_e( 'Separe múltiplos emails por vírgula.', 'desi-pet-shower' ); ?></p>
                    </div>
                </fieldset>
            </div>

            <!-- Relatório Semanal -->
            <div class="dps-surface dps-surface--neutral" style="margin-top: 20px;">
                <h3 class="dps-surface__title">
                    <span class="dashicons dashicons-groups"></span>
                    <?php esc_html_e( 'Relatório Semanal – Pets Inativos', 'desi-pet-shower' ); ?>
                </h3>
                <p class="dps-surface__description">
                    <?php esc_html_e( 'Receba semanalmente uma lista de pets que não foram atendidos há muito tempo.', 'desi-pet-shower' ); ?>
                </p>

                <fieldset class="dps-fieldset">
                    <legend><?php esc_html_e( 'Configurações', 'desi-pet-shower' ); ?></legend>

                    <div class="dps-form-row">
                        <label class="dps-checkbox-label">
                            <input type="checkbox" name="dps_push_weekly_enabled" value="1" <?php checked( $weekly_enabled ); ?> />
                            <strong><?php esc_html_e( 'Ativar relatório semanal', 'desi-pet-shower' ); ?></strong>
                        </label>
                    </div>

                    <div class="dps-form-row">
                        <label for="dps_push_weekly_day"><?php esc_html_e( 'Dia da semana', 'desi-pet-shower' ); ?></label>
                        <select id="dps_push_weekly_day" name="dps_push_weekly_day" class="regular-text" style="width: auto;">
                            <?php foreach ( $weekdays as $value => $label ) : ?>
                                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $weekly_day, $value ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="dps-form-row">
                        <label for="dps_push_weekly_time"><?php esc_html_e( 'Horário de envio', 'desi-pet-shower' ); ?></label>
                        <input type="time" id="dps_push_weekly_time" name="dps_push_weekly_time" value="<?php echo esc_attr( $weekly_time ); ?>" class="regular-text" style="width: 120px;" />
                        <?php if ( $weekly_enabled && $next_weekly ) : ?>
                            <span class="dps-next-schedule">
                                ✓ <?php esc_html_e( 'Próximo:', 'desi-pet-shower' ); ?> <?php echo esc_html( date_i18n( 'd/m H:i', $next_weekly ) ); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="dps-form-row">
                        <label for="dps_push_inactive_days"><?php esc_html_e( 'Considerar inativo após', 'desi-pet-shower' ); ?></label>
                        <input type="number" id="dps_push_inactive_days" name="dps_push_inactive_days" value="<?php echo esc_attr( $inactive_days ); ?>" min="7" max="365" class="regular-text" style="width: 100px;" />
                        <span style="margin-left: 8px;"><?php esc_html_e( 'dias sem atendimento', 'desi-pet-shower' ); ?></span>
                    </div>
                </fieldset>
            </div>

            <!-- Integração Telegram -->
            <div class="dps-surface dps-surface--info" style="margin-top: 20px;">
                <h3 class="dps-surface__title">
                    <span class="dashicons dashicons-format-status"></span>
                    <?php esc_html_e( 'Integração com Telegram', 'desi-pet-shower' ); ?>
                </h3>
                <p class="dps-surface__description">
                    <?php esc_html_e( 'Receba os relatórios também via Telegram. Configure um bot e informe o Chat ID.', 'desi-pet-shower' ); ?>
                </p>

                <fieldset class="dps-fieldset">
                    <legend><?php esc_html_e( 'Credenciais do Bot', 'desi-pet-shower' ); ?></legend>

                    <div class="dps-form-row">
                        <label for="dps_push_telegram_token"><?php esc_html_e( 'Token do Bot', 'desi-pet-shower' ); ?></label>
                        <input type="password" id="dps_push_telegram_token" name="dps_push_telegram_token" value="<?php echo esc_attr( self::mask_sensitive_value( $telegram_token ) ); ?>" class="regular-text" autocomplete="new-password" placeholder="123456789:ABCdefGHIjklMNOpqrSTUvwxYZ" />
                        <p class="description"><?php esc_html_e( 'Crie um bot via @BotFather no Telegram para obter o token.', 'desi-pet-shower' ); ?></p>
                    </div>

                    <div class="dps-form-row">
                        <label for="dps_push_telegram_chat"><?php esc_html_e( 'Chat ID', 'desi-pet-shower' ); ?></label>
                        <input type="text" id="dps_push_telegram_chat" name="dps_push_telegram_chat" value="<?php echo esc_attr( $telegram_chat ); ?>" class="regular-text" placeholder="-1001234567890" />
                        <p class="description"><?php esc_html_e( 'ID do chat ou grupo. Use @userinfobot para descobrir.', 'desi-pet-shower' ); ?></p>
                    </div>
                </fieldset>
            </div>

            <div class="dps-form-actions">
                <button type="submit" class="button button-primary button-large">
                    <span class="dashicons dashicons-saved" style="margin-top: 3px;"></span>
                    <?php esc_html_e( 'Salvar Configurações', 'desi-pet-shower' ); ?>
                </button>
            </div>
        </form>
        <?php
    }

    /**
     * Renderiza a aba Financeiro - Lembretes (Finance Add-on).
     *
     * @return void
     */
    public static function render_tab_financeiro_lembretes() {
        if ( ! class_exists( 'DPS_Finance_Addon' ) ) {
            echo '<p>' . esc_html__( 'O add-on Financeiro não está ativo.', 'desi-pet-shower' ) . '</p>';
            return;
        }

        // Carrega configurações existentes
        $enabled      = get_option( 'dps_finance_reminders_enabled', 'no' );
        $days_before  = get_option( 'dps_finance_reminder_days_before', 1 );
        $days_after   = get_option( 'dps_finance_reminder_days_after', 1 );
        $msg_before   = get_option( 'dps_finance_reminder_message_before', '' );
        $msg_after    = get_option( 'dps_finance_reminder_message_after', '' );

        // Mensagens padrão se vazias
        $default_msg_before = __( 'Olá {cliente}, este é um lembrete amigável: o pagamento de R$ {valor} vence amanhã. Para sua comodidade, você pode pagar via PIX ou utilizar o link: {link}. Obrigado!', 'desi-pet-shower' );
        $default_msg_after  = __( 'Olá {cliente}, o pagamento de R$ {valor} está vencido. Para regularizar, você pode pagar via PIX ou utilizar o link: {link}. Agradecemos a atenção!', 'desi-pet-shower' );

        if ( empty( $msg_before ) ) {
            $msg_before = $default_msg_before;
        }
        if ( empty( $msg_after ) ) {
            $msg_after = $default_msg_after;
        }
        ?>
        <form method="post" action="" class="dps-settings-form">
            <?php self::nonce_field(); ?>
            <input type="hidden" name="dps_settings_action" value="save_financeiro_lembretes">

            <div class="dps-surface dps-surface--warning">
                <h3 class="dps-surface__title">
                    <span class="dashicons dashicons-bell"></span>
                    <?php esc_html_e( 'Lembretes Automáticos de Pagamento', 'desi-pet-shower' ); ?>
                </h3>
                <p class="dps-surface__description">
                    <?php esc_html_e( 'Configure lembretes automáticos para cobranças pendentes. O sistema enviará mensagens antes e depois do vencimento.', 'desi-pet-shower' ); ?>
                </p>

                <fieldset class="dps-fieldset">
                    <legend><?php esc_html_e( 'Ativação', 'desi-pet-shower' ); ?></legend>

                    <div class="dps-form-row">
                        <label class="dps-checkbox-label">
                            <input type="checkbox" name="dps_finance_reminders_enabled" value="yes" <?php checked( $enabled, 'yes' ); ?> />
                            <strong><?php esc_html_e( 'Habilitar lembretes automáticos de pagamento', 'desi-pet-shower' ); ?></strong>
                        </label>
                        <p class="description"><?php esc_html_e( 'Quando habilitado, o sistema enviará lembretes automáticos antes e depois do vencimento.', 'desi-pet-shower' ); ?></p>
                    </div>
                </fieldset>

                <fieldset class="dps-fieldset">
                    <legend><?php esc_html_e( 'Timing dos Lembretes', 'desi-pet-shower' ); ?></legend>

                    <div class="dps-form-row">
                        <label for="dps_finance_reminder_days_before"><?php esc_html_e( 'Dias antes do vencimento', 'desi-pet-shower' ); ?></label>
                        <input type="number" id="dps_finance_reminder_days_before" name="dps_finance_reminder_days_before" value="<?php echo esc_attr( $days_before ); ?>" min="0" max="30" class="regular-text" style="width: 100px;" />
                        <p class="description"><?php esc_html_e( 'Quantos dias antes do vencimento enviar o primeiro lembrete (ex: 1 = envia 1 dia antes).', 'desi-pet-shower' ); ?></p>
                    </div>

                    <div class="dps-form-row">
                        <label for="dps_finance_reminder_days_after"><?php esc_html_e( 'Dias após o vencimento', 'desi-pet-shower' ); ?></label>
                        <input type="number" id="dps_finance_reminder_days_after" name="dps_finance_reminder_days_after" value="<?php echo esc_attr( $days_after ); ?>" min="0" max="30" class="regular-text" style="width: 100px;" />
                        <p class="description"><?php esc_html_e( 'Quantos dias após o vencimento enviar o lembrete de cobrança.', 'desi-pet-shower' ); ?></p>
                    </div>
                </fieldset>

                <fieldset class="dps-fieldset">
                    <legend><?php esc_html_e( 'Templates de Mensagem', 'desi-pet-shower' ); ?></legend>

                    <div class="dps-notice dps-notice--info" style="margin-bottom: 16px;">
                        <span class="dashicons dashicons-info"></span>
                        <?php esc_html_e( 'Placeholders disponíveis: {cliente}, {pet}, {data}, {valor}, {link}, {pix}, {loja}', 'desi-pet-shower' ); ?>
                    </div>

                    <div class="dps-form-row">
                        <label for="dps_finance_reminder_message_before"><?php esc_html_e( 'Mensagem - Antes do Vencimento', 'desi-pet-shower' ); ?></label>
                        <textarea id="dps_finance_reminder_message_before" name="dps_finance_reminder_message_before" rows="4" class="large-text"><?php echo esc_textarea( $msg_before ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Mensagem enviada antes do vencimento do pagamento.', 'desi-pet-shower' ); ?></p>
                    </div>

                    <div class="dps-form-row">
                        <label for="dps_finance_reminder_message_after"><?php esc_html_e( 'Mensagem - Após Vencimento', 'desi-pet-shower' ); ?></label>
                        <textarea id="dps_finance_reminder_message_after" name="dps_finance_reminder_message_after" rows="4" class="large-text"><?php echo esc_textarea( $msg_after ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Mensagem enviada após o vencimento do pagamento.', 'desi-pet-shower' ); ?></p>
                    </div>
                </fieldset>
            </div>

            <div class="dps-form-actions">
                <button type="submit" class="button button-primary button-large">
                    <span class="dashicons dashicons-saved" style="margin-top: 3px;"></span>
                    <?php esc_html_e( 'Salvar Configurações', 'desi-pet-shower' ); ?>
                </button>
            </div>
        </form>
        <?php
    }

    /**
     * Processa salvamento da aba Notificações.
     *
     * @return void
     */
    private static function handle_save_notificacoes() {
        // Valida e filtra lista de emails
        $emails_agenda = '';
        if ( isset( $_POST['dps_push_emails_agenda'] ) ) {
            $emails_agenda = self::validate_email_list( sanitize_textarea_field( wp_unslash( $_POST['dps_push_emails_agenda'] ) ) );
        }

        $emails_report = '';
        if ( isset( $_POST['dps_push_emails_report'] ) ) {
            $emails_report = self::validate_email_list( sanitize_textarea_field( wp_unslash( $_POST['dps_push_emails_report'] ) ) );
        }

        // Valida horários
        $agenda_time = self::validate_time( isset( $_POST['dps_push_agenda_time'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_push_agenda_time'] ) ) : '08:00' );
        $report_time = self::validate_time( isset( $_POST['dps_push_report_time'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_push_report_time'] ) ) : '19:00' );
        $weekly_time = self::validate_time( isset( $_POST['dps_push_weekly_time'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_push_weekly_time'] ) ) : '08:00' );

        // Valida dia da semana
        $allowed_days   = [ 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' ];
        $weekly_day_raw = isset( $_POST['dps_push_weekly_day'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_push_weekly_day'] ) ) : 'monday';
        $weekly_day     = in_array( $weekly_day_raw, $allowed_days, true ) ? $weekly_day_raw : 'monday';

        // Valida dias de inatividade
        $inactive_days = isset( $_POST['dps_push_inactive_days'] ) ? absint( $_POST['dps_push_inactive_days'] ) : 30;
        if ( $inactive_days < 7 ) {
            $inactive_days = 7;
        } elseif ( $inactive_days > 365 ) {
            $inactive_days = 365;
        }

        // Token e chat Telegram
        $telegram_token = isset( $_POST['dps_push_telegram_token'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_push_telegram_token'] ) ) : '';
        $telegram_chat  = isset( $_POST['dps_push_telegram_chat'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_push_telegram_chat'] ) ) : '';

        // Atualiza opções
        update_option( 'dps_push_emails_agenda', $emails_agenda );
        update_option( 'dps_push_emails_report', $emails_report );
        update_option( 'dps_push_agenda_time', $agenda_time );
        update_option( 'dps_push_report_time', $report_time );
        update_option( 'dps_push_weekly_day', $weekly_day );
        update_option( 'dps_push_weekly_time', $weekly_time );
        update_option( 'dps_push_inactive_days', $inactive_days );

        // Token do Telegram - só atualiza se não for mascarado
        if ( ! self::is_masked_value( $telegram_token ) && ! empty( $telegram_token ) ) {
            update_option( 'dps_push_telegram_token', $telegram_token );
        }
        update_option( 'dps_push_telegram_chat', $telegram_chat );

        // Checkboxes de ativação
        update_option( 'dps_push_agenda_enabled', ! empty( $_POST['dps_push_agenda_enabled'] ) );
        update_option( 'dps_push_report_enabled', ! empty( $_POST['dps_push_report_enabled'] ) );
        update_option( 'dps_push_weekly_enabled', ! empty( $_POST['dps_push_weekly_enabled'] ) );

        // Reagenda crons se a classe existir
        if ( class_exists( 'DPS_Email_Reports' ) ) {
            $email_reports = DPS_Email_Reports::get_instance();
            if ( method_exists( $email_reports, 'reschedule_all_crons' ) ) {
                $email_reports->reschedule_all_crons();
            }
        }

        // Log de auditoria
        DPS_Logger::log(
            sprintf(
                /* translators: %d: User ID who modified settings */
                __( 'Configurações de Notificações atualizadas pelo usuário ID %d', 'desi-pet-shower' ),
                get_current_user_id()
            ),
            DPS_Logger::LEVEL_INFO,
            'notifications_settings_updated'
        );

        DPS_Message_Helper::add_success( __( 'Configurações de Notificações salvas com sucesso!', 'desi-pet-shower' ) );
    }

    /**
     * Processa salvamento da aba Financeiro - Lembretes.
     *
     * @return void
     */
    private static function handle_save_financeiro_lembretes() {
        // Habilitar/desabilitar
        $enabled = isset( $_POST['dps_finance_reminders_enabled'] ) && $_POST['dps_finance_reminders_enabled'] === 'yes' ? 'yes' : 'no';
        update_option( 'dps_finance_reminders_enabled', $enabled );

        // Dias antes/depois
        if ( isset( $_POST['dps_finance_reminder_days_before'] ) ) {
            $days_before = max( 0, min( 30, intval( $_POST['dps_finance_reminder_days_before'] ) ) );
            update_option( 'dps_finance_reminder_days_before', $days_before );
        }

        if ( isset( $_POST['dps_finance_reminder_days_after'] ) ) {
            $days_after = max( 0, min( 30, intval( $_POST['dps_finance_reminder_days_after'] ) ) );
            update_option( 'dps_finance_reminder_days_after', $days_after );
        }

        // Mensagens
        if ( isset( $_POST['dps_finance_reminder_message_before'] ) ) {
            update_option( 'dps_finance_reminder_message_before', sanitize_textarea_field( wp_unslash( $_POST['dps_finance_reminder_message_before'] ) ) );
        }

        if ( isset( $_POST['dps_finance_reminder_message_after'] ) ) {
            update_option( 'dps_finance_reminder_message_after', sanitize_textarea_field( wp_unslash( $_POST['dps_finance_reminder_message_after'] ) ) );
        }

        // Log de auditoria
        DPS_Logger::log(
            sprintf(
                /* translators: %d: User ID who modified settings */
                __( 'Configurações de Lembretes Financeiros atualizadas pelo usuário ID %d', 'desi-pet-shower' ),
                get_current_user_id()
            ),
            DPS_Logger::LEVEL_INFO,
            'finance_reminders_settings_updated'
        );

        DPS_Message_Helper::add_success( __( 'Configurações de Lembretes Financeiros salvas com sucesso!', 'desi-pet-shower' ) );
    }

    /**
     * Valida e filtra lista de emails separados por vírgula.
     *
     * @param string $input Lista de emails.
     * @return string Lista de emails válidos.
     */
    private static function validate_email_list( $input ) {
        if ( empty( $input ) ) {
            return '';
        }
        $emails       = array_map( 'trim', explode( ',', $input ) );
        $valid_emails = array_filter( $emails, 'is_email' );
        return implode( ', ', $valid_emails );
    }

    /**
     * Valida horário no formato HH:MM.
     *
     * @param string $time Horário.
     * @return string Horário válido ou padrão.
     */
    private static function validate_time( $time ) {
        if ( preg_match( '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time ) ) {
            return $time;
        }
        return '08:00';
    }

    // ========================================
    // FASE 5: Abas Avançadas
    // ========================================

    /**
     * Renderiza a aba Cadastro Público (Registration Add-on).
     *
     * @return void
     */
    public static function render_tab_cadastro() {
        if ( ! class_exists( 'DPS_Registration_Addon' ) ) {
            echo '<p>' . esc_html__( 'O add-on Cadastro Público não está ativo.', 'desi-pet-shower' ) . '</p>';
            return;
        }

        // Carrega configurações existentes
        $registration_page_id    = (int) get_option( 'dps_registration_page_id', 0 );
        $api_enabled             = get_option( 'dps_registration_api_enabled', false );
        $api_rate_key            = (int) get_option( 'dps_registration_api_rate_key_per_hour', 60 );
        $api_rate_ip             = (int) get_option( 'dps_registration_api_rate_ip_per_hour', 30 );
        $recaptcha_enabled       = get_option( 'dps_registration_recaptcha_enabled', false );
        $recaptcha_site_key      = get_option( 'dps_registration_recaptcha_site_key', '' );
        $recaptcha_secret_key    = get_option( 'dps_registration_recaptcha_secret_key', '' );
        $recaptcha_threshold     = (float) get_option( 'dps_registration_recaptcha_threshold', 0.5 );
        $confirm_email_subject   = get_option( 'dps_registration_confirm_email_subject', '' );
        $confirm_email_body      = get_option( 'dps_registration_confirm_email_body', '' );

        // Obtém lista de páginas para o selector
        $pages = get_pages( [ 'post_status' => 'publish' ] );
        ?>
        <form method="post" action="" class="dps-settings-form">
            <?php self::nonce_field(); ?>
            <input type="hidden" name="dps_settings_action" value="save_cadastro">

            <div class="dps-surface dps-surface--info">
                <h3 class="dps-surface__title">
                    <span class="dashicons dashicons-admin-users"></span>
                    <?php esc_html_e( 'Cadastro Público de Clientes', 'desi-pet-shower' ); ?>
                </h3>
                <p class="dps-surface__description">
                    <?php esc_html_e( 'Configure o formulário de cadastro público para novos clientes via front-end.', 'desi-pet-shower' ); ?>
                </p>

                <fieldset class="dps-fieldset">
                    <legend><?php esc_html_e( 'Página de Cadastro', 'desi-pet-shower' ); ?></legend>
                    
                    <div class="dps-form-row">
                        <label for="dps_registration_page_id"><?php esc_html_e( 'Página do Formulário', 'desi-pet-shower' ); ?></label>
                        <select id="dps_registration_page_id" name="dps_registration_page_id" class="regular-text">
                            <option value=""><?php esc_html_e( '— Selecione uma página —', 'desi-pet-shower' ); ?></option>
                            <?php foreach ( $pages as $page ) : ?>
                                <option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( $registration_page_id, $page->ID ); ?>>
                                    <?php echo esc_html( $page->post_title ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'Página onde o shortcode [dps_registro] está inserido.', 'desi-pet-shower' ); ?></p>
                    </div>
                </fieldset>

                <fieldset class="dps-fieldset">
                    <legend><?php esc_html_e( 'Proteção reCAPTCHA', 'desi-pet-shower' ); ?></legend>
                    
                    <div class="dps-form-row">
                        <label class="dps-checkbox-label">
                            <input type="checkbox" name="dps_registration_recaptcha_enabled" value="1" <?php checked( $recaptcha_enabled ); ?> />
                            <strong><?php esc_html_e( 'Ativar proteção reCAPTCHA v3', 'desi-pet-shower' ); ?></strong>
                        </label>
                        <p class="description"><?php esc_html_e( 'Protege o formulário contra bots usando Google reCAPTCHA v3 (invisível).', 'desi-pet-shower' ); ?></p>
                    </div>

                    <div class="dps-form-row">
                        <label for="dps_registration_recaptcha_site_key"><?php esc_html_e( 'Site Key', 'desi-pet-shower' ); ?></label>
                        <input type="text" id="dps_registration_recaptcha_site_key" name="dps_registration_recaptcha_site_key" value="<?php echo esc_attr( self::mask_sensitive_value( $recaptcha_site_key ) ); ?>" class="regular-text" />
                        <p class="description"><?php esc_html_e( 'Chave do site obtida no console do Google reCAPTCHA.', 'desi-pet-shower' ); ?></p>
                    </div>

                    <div class="dps-form-row">
                        <label for="dps_registration_recaptcha_secret_key"><?php esc_html_e( 'Secret Key', 'desi-pet-shower' ); ?></label>
                        <input type="password" id="dps_registration_recaptcha_secret_key" name="dps_registration_recaptcha_secret_key" value="<?php echo esc_attr( self::mask_sensitive_value( $recaptcha_secret_key ) ); ?>" class="regular-text" autocomplete="new-password" />
                        <p class="description"><?php esc_html_e( 'Chave secreta obtida no console do Google reCAPTCHA.', 'desi-pet-shower' ); ?></p>
                    </div>

                    <div class="dps-form-row">
                        <label for="dps_registration_recaptcha_threshold"><?php esc_html_e( 'Threshold de Confiança', 'desi-pet-shower' ); ?></label>
                        <input type="number" id="dps_registration_recaptcha_threshold" name="dps_registration_recaptcha_threshold" value="<?php echo esc_attr( $recaptcha_threshold ); ?>" min="0" max="1" step="0.1" class="regular-text" style="width: 100px;" />
                        <p class="description"><?php esc_html_e( 'Score mínimo para considerar humano (0.0 a 1.0). Padrão: 0.5', 'desi-pet-shower' ); ?></p>
                    </div>
                </fieldset>

                <fieldset class="dps-fieldset">
                    <legend><?php esc_html_e( 'API REST', 'desi-pet-shower' ); ?></legend>
                    
                    <div class="dps-notice dps-notice--info" style="margin-bottom: 16px;">
                        <span class="dashicons dashicons-info"></span>
                        <?php esc_html_e( 'A API REST permite cadastros programáticos via integração externa.', 'desi-pet-shower' ); ?>
                    </div>

                    <div class="dps-form-row">
                        <label class="dps-checkbox-label">
                            <input type="checkbox" name="dps_registration_api_enabled" value="1" <?php checked( $api_enabled ); ?> />
                            <strong><?php esc_html_e( 'Habilitar API REST de cadastro', 'desi-pet-shower' ); ?></strong>
                        </label>
                        <p class="description"><?php esc_html_e( 'Permite cadastros via endpoint REST autenticado.', 'desi-pet-shower' ); ?></p>
                    </div>

                    <div class="dps-form-row">
                        <label for="dps_registration_api_rate_key"><?php esc_html_e( 'Rate Limit por API Key', 'desi-pet-shower' ); ?></label>
                        <input type="number" id="dps_registration_api_rate_key" name="dps_registration_api_rate_key_per_hour" value="<?php echo esc_attr( $api_rate_key ); ?>" min="1" max="1000" class="regular-text" style="width: 100px;" />
                        <span style="margin-left: 8px;"><?php esc_html_e( 'requisições/hora', 'desi-pet-shower' ); ?></span>
                        <p class="description"><?php esc_html_e( 'Limite de requisições por hora para cada API key.', 'desi-pet-shower' ); ?></p>
                    </div>

                    <div class="dps-form-row">
                        <label for="dps_registration_api_rate_ip"><?php esc_html_e( 'Rate Limit por IP', 'desi-pet-shower' ); ?></label>
                        <input type="number" id="dps_registration_api_rate_ip" name="dps_registration_api_rate_ip_per_hour" value="<?php echo esc_attr( $api_rate_ip ); ?>" min="1" max="1000" class="regular-text" style="width: 100px;" />
                        <span style="margin-left: 8px;"><?php esc_html_e( 'requisições/hora', 'desi-pet-shower' ); ?></span>
                        <p class="description"><?php esc_html_e( 'Limite de requisições por hora para cada endereço IP.', 'desi-pet-shower' ); ?></p>
                    </div>
                </fieldset>

                <fieldset class="dps-fieldset">
                    <legend><?php esc_html_e( 'Email de Confirmação', 'desi-pet-shower' ); ?></legend>
                    
                    <div class="dps-notice dps-notice--info" style="margin-bottom: 16px;">
                        <span class="dashicons dashicons-info"></span>
                        <?php esc_html_e( 'Placeholders disponíveis: {nome}, {email}, {telefone}, {loja}', 'desi-pet-shower' ); ?>
                    </div>

                    <div class="dps-form-row">
                        <label for="dps_registration_confirm_email_subject"><?php esc_html_e( 'Assunto do Email', 'desi-pet-shower' ); ?></label>
                        <input type="text" id="dps_registration_confirm_email_subject" name="dps_registration_confirm_email_subject" value="<?php echo esc_attr( $confirm_email_subject ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Bem-vindo à {loja}!', 'desi-pet-shower' ); ?>" />
                    </div>

                    <div class="dps-form-row">
                        <label for="dps_registration_confirm_email_body"><?php esc_html_e( 'Corpo do Email', 'desi-pet-shower' ); ?></label>
                        <textarea id="dps_registration_confirm_email_body" name="dps_registration_confirm_email_body" rows="6" class="large-text"><?php echo esc_textarea( $confirm_email_body ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Mensagem enviada para o cliente após cadastro bem-sucedido.', 'desi-pet-shower' ); ?></p>
                    </div>
                </fieldset>
            </div>

            <div class="dps-form-actions">
                <button type="submit" class="button button-primary button-large">
                    <span class="dashicons dashicons-saved" style="margin-top: 3px;"></span>
                    <?php esc_html_e( 'Salvar Configurações', 'desi-pet-shower' ); ?>
                </button>
            </div>
        </form>
        <?php
    }

    /**
     * Renderiza a aba Assistente IA (AI Add-on).
     *
     * @return void
     */
    public static function render_tab_ia() {
        if ( ! class_exists( 'DPS_AI_Addon' ) ) {
            echo '<p>' . esc_html__( 'O add-on Assistente IA não está ativo.', 'desi-pet-shower' ) . '</p>';
            return;
        }

        // Carrega configurações existentes
        $ai_settings = get_option( 'dps_ai_settings', [] );
        
        $enabled                  = ! empty( $ai_settings['enabled'] );
        $api_key                  = $ai_settings['api_key'] ?? '';
        $model                    = $ai_settings['model'] ?? 'gpt-4o-mini';
        $temperature              = $ai_settings['temperature'] ?? 0.4;
        $timeout                  = $ai_settings['timeout'] ?? 10;
        $max_tokens               = $ai_settings['max_tokens'] ?? 500;
        $additional_instructions  = $ai_settings['additional_instructions'] ?? '';
        $widget_mode              = $ai_settings['widget_mode'] ?? 'inline';
        $scheduling_mode          = $ai_settings['scheduling_mode'] ?? 'disabled';
        $enable_feedback          = ! empty( $ai_settings['enable_feedback'] );
        $public_chat_enabled      = ! empty( $ai_settings['public_chat_enabled'] );

        // Modelos disponíveis
        $models = [
            'gpt-4o-mini'   => 'GPT-4o Mini (Recomendado - Rápido e econômico)',
            'gpt-4o'        => 'GPT-4o (Mais preciso, custo médio)',
            'gpt-4-turbo'   => 'GPT-4 Turbo (Alta precisão)',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo (Legado - Mais barato)',
        ];

        // Modos de widget
        $widget_modes = [
            'inline'   => __( 'Integrado (no topo do portal)', 'desi-pet-shower' ),
            'floating' => __( 'Flutuante (botão no canto)', 'desi-pet-shower' ),
        ];

        // Modos de agendamento
        $scheduling_modes = [
            'disabled' => __( 'Desabilitado', 'desi-pet-shower' ),
            'request'  => __( 'Solicitar confirmação (equipe confirma)', 'desi-pet-shower' ),
            'direct'   => __( 'Agendamento direto (confirmação automática)', 'desi-pet-shower' ),
        ];
        ?>
        <form method="post" action="" class="dps-settings-form">
            <?php self::nonce_field(); ?>
            <input type="hidden" name="dps_settings_action" value="save_ia">

            <div class="dps-surface dps-surface--info">
                <h3 class="dps-surface__title">
                    <span class="dashicons dashicons-format-status"></span>
                    <?php esc_html_e( 'Assistente Virtual Inteligente', 'desi-pet-shower' ); ?>
                </h3>
                <p class="dps-surface__description">
                    <?php esc_html_e( 'Configure o assistente de IA para atendimento automatizado no Portal do Cliente.', 'desi-pet-shower' ); ?>
                </p>

                <fieldset class="dps-fieldset">
                    <legend><?php esc_html_e( 'Ativação', 'desi-pet-shower' ); ?></legend>
                    
                    <div class="dps-form-row">
                        <label class="dps-checkbox-label">
                            <input type="checkbox" name="dps_ai_enabled" value="1" <?php checked( $enabled ); ?> />
                            <strong><?php esc_html_e( 'Habilitar Assistente de IA', 'desi-pet-shower' ); ?></strong>
                        </label>
                        <p class="description"><?php esc_html_e( 'Quando desativado, o widget de IA não aparece no portal.', 'desi-pet-shower' ); ?></p>
                    </div>

                    <div class="dps-form-row">
                        <label class="dps-checkbox-label">
                            <input type="checkbox" name="dps_ai_public_chat_enabled" value="1" <?php checked( $public_chat_enabled ); ?> />
                            <?php esc_html_e( 'Habilitar Chat Público para visitantes', 'desi-pet-shower' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Permite que visitantes não logados usem o assistente via shortcode [dps_ai_public_chat].', 'desi-pet-shower' ); ?></p>
                    </div>
                </fieldset>

                <fieldset class="dps-fieldset">
                    <legend><?php esc_html_e( 'Credenciais OpenAI', 'desi-pet-shower' ); ?></legend>
                    
                    <div class="dps-notice dps-notice--warning" style="margin-bottom: 16px;">
                        <span class="dashicons dashicons-shield"></span>
                        <?php esc_html_e( 'Sua API key é sensível. Nunca compartilhe ou exponha publicamente.', 'desi-pet-shower' ); ?>
                    </div>

                    <div class="dps-form-row">
                        <label for="dps_ai_api_key"><?php esc_html_e( 'Chave de API da OpenAI', 'desi-pet-shower' ); ?></label>
                        <input type="password" id="dps_ai_api_key" name="dps_ai_api_key" value="<?php echo esc_attr( self::mask_sensitive_value( $api_key ) ); ?>" class="regular-text" autocomplete="new-password" placeholder="sk-..." />
                        <p class="description"><?php esc_html_e( 'Token de autenticação da API da OpenAI (sk-...). Mantenha em segredo.', 'desi-pet-shower' ); ?></p>
                    </div>
                </fieldset>

                <fieldset class="dps-fieldset">
                    <legend><?php esc_html_e( 'Modelo e Parâmetros', 'desi-pet-shower' ); ?></legend>
                    
                    <div class="dps-form-row">
                        <label for="dps_ai_model"><?php esc_html_e( 'Modelo GPT', 'desi-pet-shower' ); ?></label>
                        <select id="dps_ai_model" name="dps_ai_model" class="regular-text">
                            <?php foreach ( $models as $value => $label ) : ?>
                                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $model, $value ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'Modelo de linguagem a ser utilizado. GPT-4o Mini é recomendado para melhor custo/benefício.', 'desi-pet-shower' ); ?></p>
                    </div>

                    <div class="dps-form-row">
                        <label for="dps_ai_temperature"><?php esc_html_e( 'Temperatura', 'desi-pet-shower' ); ?></label>
                        <input type="number" id="dps_ai_temperature" name="dps_ai_temperature" value="<?php echo esc_attr( $temperature ); ?>" min="0" max="1" step="0.1" class="regular-text" style="width: 100px;" />
                        <p class="description"><?php esc_html_e( 'Controla a criatividade das respostas (0 = mais focado, 1 = mais criativo). Recomendado: 0.4', 'desi-pet-shower' ); ?></p>
                    </div>

                    <div class="dps-form-row">
                        <label for="dps_ai_timeout"><?php esc_html_e( 'Timeout (segundos)', 'desi-pet-shower' ); ?></label>
                        <input type="number" id="dps_ai_timeout" name="dps_ai_timeout" value="<?php echo esc_attr( $timeout ); ?>" min="5" max="60" class="regular-text" style="width: 100px;" />
                        <p class="description"><?php esc_html_e( 'Tempo máximo de espera pela resposta da API. Recomendado: 10', 'desi-pet-shower' ); ?></p>
                    </div>

                    <div class="dps-form-row">
                        <label for="dps_ai_max_tokens"><?php esc_html_e( 'Máximo de Tokens', 'desi-pet-shower' ); ?></label>
                        <input type="number" id="dps_ai_max_tokens" name="dps_ai_max_tokens" value="<?php echo esc_attr( $max_tokens ); ?>" min="100" max="2000" class="regular-text" style="width: 100px;" />
                        <p class="description"><?php esc_html_e( 'Limite de tokens na resposta (afeta custo e tamanho). Recomendado: 500', 'desi-pet-shower' ); ?></p>
                    </div>
                </fieldset>

                <fieldset class="dps-fieldset">
                    <legend><?php esc_html_e( 'Comportamento do Widget', 'desi-pet-shower' ); ?></legend>
                    
                    <div class="dps-form-row">
                        <label for="dps_ai_widget_mode"><?php esc_html_e( 'Modo do Widget', 'desi-pet-shower' ); ?></label>
                        <select id="dps_ai_widget_mode" name="dps_ai_widget_mode" class="regular-text">
                            <?php foreach ( $widget_modes as $value => $label ) : ?>
                                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $widget_mode, $value ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="dps-form-row">
                        <label for="dps_ai_scheduling_mode"><?php esc_html_e( 'Agendamento via Chat', 'desi-pet-shower' ); ?></label>
                        <select id="dps_ai_scheduling_mode" name="dps_ai_scheduling_mode" class="regular-text">
                            <?php foreach ( $scheduling_modes as $value => $label ) : ?>
                                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $scheduling_mode, $value ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'Define como os agendamentos solicitados via chat são processados.', 'desi-pet-shower' ); ?></p>
                    </div>

                    <div class="dps-form-row">
                        <label class="dps-checkbox-label">
                            <input type="checkbox" name="dps_ai_enable_feedback" value="1" <?php checked( $enable_feedback ); ?> />
                            <?php esc_html_e( 'Habilitar feedback (👍/👎)', 'desi-pet-shower' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Permite que clientes avaliem as respostas da IA.', 'desi-pet-shower' ); ?></p>
                    </div>
                </fieldset>

                <fieldset class="dps-fieldset">
                    <legend><?php esc_html_e( 'Personalização', 'desi-pet-shower' ); ?></legend>
                    
                    <div class="dps-form-row">
                        <label for="dps_ai_additional_instructions"><?php esc_html_e( 'Instruções Adicionais', 'desi-pet-shower' ); ?></label>
                        <textarea id="dps_ai_additional_instructions" name="dps_ai_additional_instructions" rows="5" class="large-text" maxlength="2000"><?php echo esc_textarea( $additional_instructions ); ?></textarea>
                        <p class="description">
                            <?php esc_html_e( 'Regras complementares sobre tom de voz, estilo de atendimento e orientações da marca. Máximo: 2000 caracteres.', 'desi-pet-shower' ); ?>
                        </p>
                    </div>
                </fieldset>
            </div>

            <div class="dps-form-actions">
                <button type="submit" class="button button-primary button-large">
                    <span class="dashicons dashicons-saved" style="margin-top: 3px;"></span>
                    <?php esc_html_e( 'Salvar Configurações', 'desi-pet-shower' ); ?>
                </button>
            </div>
        </form>
        <?php
    }

    /**
     * Renderiza a aba Fidelidade (Loyalty Add-on).
     *
     * @return void
     */
    public static function render_tab_fidelidade() {
        if ( ! class_exists( 'DPS_Loyalty_Addon' ) ) {
            echo '<p>' . esc_html__( 'O add-on Fidelidade não está ativo.', 'desi-pet-shower' ) . '</p>';
            return;
        }

        // Carrega configurações existentes
        $loyalty_settings = get_option( 'dps_loyalty_settings', [] );
        
        $brl_per_point              = isset( $loyalty_settings['brl_per_point'] ) && $loyalty_settings['brl_per_point'] > 0 ? (float) $loyalty_settings['brl_per_point'] : 10.0;
        $referrals_enabled          = ! empty( $loyalty_settings['referrals_enabled'] );
        $referrer_reward_type       = $loyalty_settings['referrer_reward_type'] ?? 'none';
        $referrer_reward_value      = $loyalty_settings['referrer_reward_value'] ?? 0;
        $referee_reward_type        = $loyalty_settings['referee_reward_type'] ?? 'none';
        $referee_reward_value       = $loyalty_settings['referee_reward_value'] ?? 0;
        $enable_portal_redemption   = ! empty( $loyalty_settings['enable_portal_redemption'] );
        $portal_min_points          = isset( $loyalty_settings['portal_min_points_to_redeem'] ) ? absint( $loyalty_settings['portal_min_points_to_redeem'] ) : 0;
        $portal_points_per_real     = isset( $loyalty_settings['portal_points_per_real'] ) ? absint( $loyalty_settings['portal_points_per_real'] ) : 100;

        // Tipos de recompensa
        $reward_types = [
            'none'    => __( 'Sem recompensa', 'desi-pet-shower' ),
            'points'  => __( 'Pontos de fidelidade', 'desi-pet-shower' ),
            'fixed'   => __( 'Crédito fixo (R$)', 'desi-pet-shower' ),
            'percent' => __( 'Crédito percentual', 'desi-pet-shower' ),
        ];
        ?>
        <form method="post" action="" class="dps-settings-form">
            <?php self::nonce_field(); ?>
            <input type="hidden" name="dps_settings_action" value="save_fidelidade">

            <div class="dps-surface dps-surface--success">
                <h3 class="dps-surface__title">
                    <span class="dashicons dashicons-star-filled"></span>
                    <?php esc_html_e( 'Programa de Fidelidade', 'desi-pet-shower' ); ?>
                </h3>
                <p class="dps-surface__description">
                    <?php esc_html_e( 'Configure regras de acúmulo de pontos, recompensas e programa de indicações.', 'desi-pet-shower' ); ?>
                </p>

                <fieldset class="dps-fieldset">
                    <legend><?php esc_html_e( 'Regras de Pontos', 'desi-pet-shower' ); ?></legend>
                    
                    <div class="dps-form-row">
                        <label for="dps_loyalty_brl_per_point"><?php esc_html_e( 'Conversão BRL → Pontos', 'desi-pet-shower' ); ?></label>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span><?php esc_html_e( '1 ponto a cada', 'desi-pet-shower' ); ?></span>
                            <input type="number" id="dps_loyalty_brl_per_point" name="dps_loyalty_brl_per_point" value="<?php echo esc_attr( $brl_per_point ); ?>" min="0.01" step="0.01" class="regular-text" style="width: 100px;" />
                            <span><?php esc_html_e( 'reais faturados', 'desi-pet-shower' ); ?></span>
                        </div>
                        <p class="description"><?php esc_html_e( 'Exemplo: Se definido como 10, o cliente ganha 1 ponto a cada R$ 10,00 gastos.', 'desi-pet-shower' ); ?></p>
                    </div>
                </fieldset>

                <fieldset class="dps-fieldset">
                    <legend><?php esc_html_e( 'Resgate de Pontos no Portal', 'desi-pet-shower' ); ?></legend>
                    
                    <div class="dps-form-row">
                        <label class="dps-checkbox-label">
                            <input type="checkbox" name="dps_loyalty_enable_portal_redemption" value="1" <?php checked( $enable_portal_redemption ); ?> />
                            <strong><?php esc_html_e( 'Permitir resgate de pontos pelo Portal do Cliente', 'desi-pet-shower' ); ?></strong>
                        </label>
                        <p class="description"><?php esc_html_e( 'Quando ativado, clientes podem trocar pontos por créditos diretamente no portal.', 'desi-pet-shower' ); ?></p>
                    </div>

                    <div class="dps-form-row">
                        <label for="dps_loyalty_portal_min_points"><?php esc_html_e( 'Mínimo de pontos para resgate', 'desi-pet-shower' ); ?></label>
                        <input type="number" id="dps_loyalty_portal_min_points" name="dps_loyalty_portal_min_points" value="<?php echo esc_attr( $portal_min_points ); ?>" min="0" class="regular-text" style="width: 100px;" />
                        <p class="description"><?php esc_html_e( 'Quantidade mínima de pontos necessária para solicitar resgate.', 'desi-pet-shower' ); ?></p>
                    </div>

                    <div class="dps-form-row">
                        <label for="dps_loyalty_portal_points_per_real"><?php esc_html_e( 'Pontos por R$ 1,00', 'desi-pet-shower' ); ?></label>
                        <input type="number" id="dps_loyalty_portal_points_per_real" name="dps_loyalty_portal_points_per_real" value="<?php echo esc_attr( $portal_points_per_real ); ?>" min="1" class="regular-text" style="width: 100px;" />
                        <p class="description"><?php esc_html_e( 'Quantos pontos equivalem a R$ 1,00 de crédito. Ex: 100 pontos = R$ 1,00.', 'desi-pet-shower' ); ?></p>
                    </div>
                </fieldset>

                <fieldset class="dps-fieldset">
                    <legend><?php esc_html_e( 'Programa de Indicações', 'desi-pet-shower' ); ?></legend>
                    
                    <div class="dps-form-row">
                        <label class="dps-checkbox-label">
                            <input type="checkbox" name="dps_loyalty_referrals_enabled" value="1" <?php checked( $referrals_enabled ); ?> />
                            <strong><?php esc_html_e( 'Ativar programa "Indique e Ganhe"', 'desi-pet-shower' ); ?></strong>
                        </label>
                        <p class="description"><?php esc_html_e( 'Permite que clientes ganhem recompensas por indicar novos clientes.', 'desi-pet-shower' ); ?></p>
                    </div>

                    <div class="dps-form-row">
                        <label for="dps_loyalty_referrer_reward_type"><?php esc_html_e( 'Recompensa do Indicador', 'desi-pet-shower' ); ?></label>
                        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                            <select id="dps_loyalty_referrer_reward_type" name="dps_loyalty_referrer_reward_type" class="regular-text" style="width: auto;">
                                <?php foreach ( $reward_types as $value => $label ) : ?>
                                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $referrer_reward_type, $value ); ?>>
                                        <?php echo esc_html( $label ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="dps_loyalty_referrer_reward_value" value="<?php echo esc_attr( $referrer_reward_value ); ?>" class="regular-text" style="width: 100px;" placeholder="<?php esc_attr_e( 'Valor', 'desi-pet-shower' ); ?>" />
                        </div>
                        <p class="description"><?php esc_html_e( 'Recompensa que o cliente indicador recebe quando a indicação é confirmada.', 'desi-pet-shower' ); ?></p>
                    </div>

                    <div class="dps-form-row">
                        <label for="dps_loyalty_referee_reward_type"><?php esc_html_e( 'Recompensa do Indicado', 'desi-pet-shower' ); ?></label>
                        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                            <select id="dps_loyalty_referee_reward_type" name="dps_loyalty_referee_reward_type" class="regular-text" style="width: auto;">
                                <?php foreach ( $reward_types as $value => $label ) : ?>
                                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $referee_reward_type, $value ); ?>>
                                        <?php echo esc_html( $label ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="dps_loyalty_referee_reward_value" value="<?php echo esc_attr( $referee_reward_value ); ?>" class="regular-text" style="width: 100px;" placeholder="<?php esc_attr_e( 'Valor', 'desi-pet-shower' ); ?>" />
                        </div>
                        <p class="description"><?php esc_html_e( 'Recompensa que o novo cliente indicado recebe.', 'desi-pet-shower' ); ?></p>
                    </div>
                </fieldset>
            </div>

            <div class="dps-form-actions">
                <button type="submit" class="button button-primary button-large">
                    <span class="dashicons dashicons-saved" style="margin-top: 3px;"></span>
                    <?php esc_html_e( 'Salvar Configurações', 'desi-pet-shower' ); ?>
                </button>
            </div>
        </form>
        <?php
    }

    /**
     * Processa salvamento da aba Cadastro Público.
     *
     * @return void
     */
    private static function handle_save_cadastro() {
        // Página de cadastro
        if ( isset( $_POST['dps_registration_page_id'] ) ) {
            $page_id = absint( wp_unslash( $_POST['dps_registration_page_id'] ) );
            update_option( 'dps_registration_page_id', $page_id );
        }

        // reCAPTCHA
        $recaptcha_enabled = ! empty( $_POST['dps_registration_recaptcha_enabled'] );
        update_option( 'dps_registration_recaptcha_enabled', $recaptcha_enabled );

        if ( isset( $_POST['dps_registration_recaptcha_site_key'] ) ) {
            $site_key = sanitize_text_field( wp_unslash( $_POST['dps_registration_recaptcha_site_key'] ) );
            if ( ! self::is_masked_value( $site_key ) && ! empty( $site_key ) ) {
                update_option( 'dps_registration_recaptcha_site_key', $site_key );
            }
        }

        if ( isset( $_POST['dps_registration_recaptcha_secret_key'] ) ) {
            $secret_key = sanitize_text_field( wp_unslash( $_POST['dps_registration_recaptcha_secret_key'] ) );
            if ( ! self::is_masked_value( $secret_key ) && ! empty( $secret_key ) ) {
                update_option( 'dps_registration_recaptcha_secret_key', $secret_key );
            }
        }

        if ( isset( $_POST['dps_registration_recaptcha_threshold'] ) ) {
            $threshold = floatval( $_POST['dps_registration_recaptcha_threshold'] );
            $threshold = max( 0, min( 1, $threshold ) );
            update_option( 'dps_registration_recaptcha_threshold', $threshold );
        }

        // API REST
        $api_enabled = ! empty( $_POST['dps_registration_api_enabled'] );
        update_option( 'dps_registration_api_enabled', $api_enabled );

        if ( isset( $_POST['dps_registration_api_rate_key_per_hour'] ) ) {
            $rate_key = max( 1, min( 1000, absint( $_POST['dps_registration_api_rate_key_per_hour'] ) ) );
            update_option( 'dps_registration_api_rate_key_per_hour', $rate_key );
        }

        if ( isset( $_POST['dps_registration_api_rate_ip_per_hour'] ) ) {
            $rate_ip = max( 1, min( 1000, absint( $_POST['dps_registration_api_rate_ip_per_hour'] ) ) );
            update_option( 'dps_registration_api_rate_ip_per_hour', $rate_ip );
        }

        // Email de confirmação
        if ( isset( $_POST['dps_registration_confirm_email_subject'] ) ) {
            $subject = sanitize_text_field( wp_unslash( $_POST['dps_registration_confirm_email_subject'] ) );
            update_option( 'dps_registration_confirm_email_subject', $subject );
        }

        if ( isset( $_POST['dps_registration_confirm_email_body'] ) ) {
            $body = sanitize_textarea_field( wp_unslash( $_POST['dps_registration_confirm_email_body'] ) );
            update_option( 'dps_registration_confirm_email_body', $body );
        }

        // Log de auditoria
        DPS_Logger::log(
            sprintf(
                /* translators: %d: User ID who modified settings */
                __( 'Configurações de Cadastro Público atualizadas pelo usuário ID %d', 'desi-pet-shower' ),
                get_current_user_id()
            ),
            DPS_Logger::LEVEL_INFO,
            'registration_settings_updated'
        );

        DPS_Message_Helper::add_success( __( 'Configurações de Cadastro Público salvas com sucesso!', 'desi-pet-shower' ) );
    }

    /**
     * Processa salvamento da aba Assistente IA.
     *
     * @return void
     */
    private static function handle_save_ia() {
        // Carrega configurações existentes para preservar campos não modificados
        $ai_settings = get_option( 'dps_ai_settings', [] );

        // Atualiza campos
        $ai_settings['enabled'] = ! empty( $_POST['dps_ai_enabled'] );
        $ai_settings['public_chat_enabled'] = ! empty( $_POST['dps_ai_public_chat_enabled'] );
        $ai_settings['enable_feedback'] = ! empty( $_POST['dps_ai_enable_feedback'] );

        // API Key - só atualiza se não for mascarado
        if ( isset( $_POST['dps_ai_api_key'] ) ) {
            $api_key = sanitize_text_field( wp_unslash( $_POST['dps_ai_api_key'] ) );
            if ( ! self::is_masked_value( $api_key ) && ! empty( $api_key ) ) {
                $ai_settings['api_key'] = $api_key;
            }
        }

        // Modelo
        if ( isset( $_POST['dps_ai_model'] ) ) {
            $allowed_models = [ 'gpt-4o-mini', 'gpt-4o', 'gpt-4-turbo', 'gpt-3.5-turbo' ];
            $model = sanitize_text_field( wp_unslash( $_POST['dps_ai_model'] ) );
            if ( in_array( $model, $allowed_models, true ) ) {
                $ai_settings['model'] = $model;
            }
        }

        // Temperatura
        if ( isset( $_POST['dps_ai_temperature'] ) ) {
            $temperature = floatval( $_POST['dps_ai_temperature'] );
            $ai_settings['temperature'] = max( 0, min( 1, $temperature ) );
        }

        // Timeout
        if ( isset( $_POST['dps_ai_timeout'] ) ) {
            $timeout = absint( $_POST['dps_ai_timeout'] );
            $ai_settings['timeout'] = max( 5, min( 60, $timeout ) );
        }

        // Max tokens
        if ( isset( $_POST['dps_ai_max_tokens'] ) ) {
            $max_tokens = absint( $_POST['dps_ai_max_tokens'] );
            $ai_settings['max_tokens'] = max( 100, min( 2000, $max_tokens ) );
        }

        // Modo do widget
        if ( isset( $_POST['dps_ai_widget_mode'] ) ) {
            $allowed_modes = [ 'inline', 'floating' ];
            $widget_mode = sanitize_text_field( wp_unslash( $_POST['dps_ai_widget_mode'] ) );
            if ( in_array( $widget_mode, $allowed_modes, true ) ) {
                $ai_settings['widget_mode'] = $widget_mode;
            }
        }

        // Modo de agendamento
        if ( isset( $_POST['dps_ai_scheduling_mode'] ) ) {
            $allowed_scheduling = [ 'disabled', 'request', 'direct' ];
            $scheduling_mode = sanitize_text_field( wp_unslash( $_POST['dps_ai_scheduling_mode'] ) );
            if ( in_array( $scheduling_mode, $allowed_scheduling, true ) ) {
                $ai_settings['scheduling_mode'] = $scheduling_mode;
            }
        }

        // Instruções adicionais
        if ( isset( $_POST['dps_ai_additional_instructions'] ) ) {
            $instructions = sanitize_textarea_field( wp_unslash( $_POST['dps_ai_additional_instructions'] ) );
            // Limita a 2000 caracteres
            if ( mb_strlen( $instructions ) > 2000 ) {
                $instructions = mb_substr( $instructions, 0, 2000 );
            }
            $ai_settings['additional_instructions'] = $instructions;
        }

        update_option( 'dps_ai_settings', $ai_settings );

        // Log de auditoria
        DPS_Logger::log(
            sprintf(
                /* translators: %d: User ID who modified settings */
                __( 'Configurações do Assistente IA atualizadas pelo usuário ID %d', 'desi-pet-shower' ),
                get_current_user_id()
            ),
            DPS_Logger::LEVEL_WARNING,
            'ai_settings_updated'
        );

        DPS_Message_Helper::add_success( __( 'Configurações do Assistente IA salvas com sucesso!', 'desi-pet-shower' ) );
    }

    /**
     * Processa salvamento da aba Fidelidade.
     *
     * @return void
     */
    private static function handle_save_fidelidade() {
        // Carrega configurações existentes para preservar campos não modificados
        $loyalty_settings = get_option( 'dps_loyalty_settings', [] );

        // BRL por ponto
        if ( isset( $_POST['dps_loyalty_brl_per_point'] ) ) {
            $brl_per_point = floatval( $_POST['dps_loyalty_brl_per_point'] );
            $loyalty_settings['brl_per_point'] = max( 0.01, $brl_per_point );
        }

        // Resgate no portal
        $loyalty_settings['enable_portal_redemption'] = ! empty( $_POST['dps_loyalty_enable_portal_redemption'] );

        if ( isset( $_POST['dps_loyalty_portal_min_points'] ) ) {
            $loyalty_settings['portal_min_points_to_redeem'] = absint( $_POST['dps_loyalty_portal_min_points'] );
        }

        if ( isset( $_POST['dps_loyalty_portal_points_per_real'] ) ) {
            $loyalty_settings['portal_points_per_real'] = max( 1, absint( $_POST['dps_loyalty_portal_points_per_real'] ) );
        }

        // Programa de indicações
        $loyalty_settings['referrals_enabled'] = ! empty( $_POST['dps_loyalty_referrals_enabled'] );

        if ( isset( $_POST['dps_loyalty_referrer_reward_type'] ) ) {
            $allowed_types = [ 'none', 'points', 'fixed', 'percent' ];
            $type = sanitize_text_field( wp_unslash( $_POST['dps_loyalty_referrer_reward_type'] ) );
            if ( in_array( $type, $allowed_types, true ) ) {
                $loyalty_settings['referrer_reward_type'] = $type;
            }
        }

        if ( isset( $_POST['dps_loyalty_referrer_reward_value'] ) ) {
            $loyalty_settings['referrer_reward_value'] = sanitize_text_field( wp_unslash( $_POST['dps_loyalty_referrer_reward_value'] ) );
        }

        if ( isset( $_POST['dps_loyalty_referee_reward_type'] ) ) {
            $allowed_types = [ 'none', 'points', 'fixed', 'percent' ];
            $type = sanitize_text_field( wp_unslash( $_POST['dps_loyalty_referee_reward_type'] ) );
            if ( in_array( $type, $allowed_types, true ) ) {
                $loyalty_settings['referee_reward_type'] = $type;
            }
        }

        if ( isset( $_POST['dps_loyalty_referee_reward_value'] ) ) {
            $loyalty_settings['referee_reward_value'] = sanitize_text_field( wp_unslash( $_POST['dps_loyalty_referee_reward_value'] ) );
        }

        update_option( 'dps_loyalty_settings', $loyalty_settings );

        // Log de auditoria
        DPS_Logger::log(
            sprintf(
                /* translators: %d: User ID who modified settings */
                __( 'Configurações de Fidelidade atualizadas pelo usuário ID %d', 'desi-pet-shower' ),
                get_current_user_id()
            ),
            DPS_Logger::LEVEL_INFO,
            'loyalty_settings_updated'
        );

        DPS_Message_Helper::add_success( __( 'Configurações de Fidelidade salvas com sucesso!', 'desi-pet-shower' ) );
    }

    // ========================================
    // FASE 6: Aba Agenda
    // ========================================

    /**
     * Renderiza a aba Agenda (Agenda Add-on).
     *
     * @return void
     */
    public static function render_tab_agenda() {
        if ( ! class_exists( 'DPS_Agenda_Addon' ) ) {
            echo '<p>' . esc_html__( 'O add-on Agenda não está ativo.', 'desi-pet-shower' ) . '</p>';
            return;
        }

        // Carrega configurações existentes
        $agenda_page_id  = (int) get_option( 'dps_agenda_page_id', 0 );
        $shop_address    = get_option( 'dps_shop_address', '' );
        
        // Carrega configuração de capacidade usando o helper se disponível
        $capacity_config = [
            'morning'   => 10,
            'afternoon' => 10,
        ];
        if ( class_exists( 'DPS_Agenda_Capacity_Helper' ) ) {
            $capacity_config = DPS_Agenda_Capacity_Helper::get_capacity_config();
        }

        // Obtém lista de páginas para o selector (otimizado: apenas campos necessários)
        $pages = get_pages( [
            'post_status' => 'publish',
            'sort_column' => 'post_title',
            'sort_order'  => 'ASC',
        ] );
        ?>
        <form method="post" action="" class="dps-settings-form">
            <?php self::nonce_field(); ?>
            <input type="hidden" name="dps_settings_action" value="save_agenda">

            <div class="dps-surface dps-surface--info">
                <h3 class="dps-surface__title">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <?php esc_html_e( 'Configurações da Agenda', 'desi-pet-shower' ); ?>
                </h3>
                <p class="dps-surface__description">
                    <?php esc_html_e( 'Configure a página da agenda e os limites de capacidade de atendimento.', 'desi-pet-shower' ); ?>
                </p>

                <fieldset class="dps-fieldset">
                    <legend><?php esc_html_e( 'Página da Agenda', 'desi-pet-shower' ); ?></legend>
                    
                    <div class="dps-form-row">
                        <label for="dps_agenda_page_id"><?php esc_html_e( 'Página da Agenda', 'desi-pet-shower' ); ?></label>
                        <select id="dps_agenda_page_id" name="dps_agenda_page_id" class="regular-text">
                            <option value=""><?php esc_html_e( '— Selecione uma página —', 'desi-pet-shower' ); ?></option>
                            <?php foreach ( $pages as $page ) : ?>
                                <option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( $agenda_page_id, $page->ID ); ?>>
                                    <?php echo esc_html( $page->post_title ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'Página onde o shortcode [dps_agenda_page] está inserido.', 'desi-pet-shower' ); ?></p>
                    </div>
                </fieldset>

                <fieldset class="dps-fieldset">
                    <legend><?php esc_html_e( 'Capacidade de Atendimento', 'desi-pet-shower' ); ?></legend>
                    
                    <div class="dps-notice dps-notice--info" style="margin-bottom: 16px;">
                        <span class="dashicons dashicons-info"></span>
                        <?php esc_html_e( 'Defina a capacidade máxima de atendimentos por período. Isso é utilizado para calcular a lotação no heatmap de capacidade.', 'desi-pet-shower' ); ?>
                    </div>

                    <div class="dps-form-row">
                        <label for="dps_agenda_capacity_morning"><?php esc_html_e( 'Capacidade da Manhã (08:00 - 11:59)', 'desi-pet-shower' ); ?></label>
                        <input type="number" id="dps_agenda_capacity_morning" name="dps_agenda_capacity_morning" value="<?php echo esc_attr( $capacity_config['morning'] ); ?>" min="1" max="100" class="regular-text" style="width: 100px;" />
                        <span style="margin-left: 8px;"><?php esc_html_e( 'atendimentos', 'desi-pet-shower' ); ?></span>
                        <p class="description"><?php esc_html_e( 'Número máximo de atendimentos no período da manhã.', 'desi-pet-shower' ); ?></p>
                    </div>

                    <div class="dps-form-row">
                        <label for="dps_agenda_capacity_afternoon"><?php esc_html_e( 'Capacidade da Tarde (12:00 - 17:59)', 'desi-pet-shower' ); ?></label>
                        <input type="number" id="dps_agenda_capacity_afternoon" name="dps_agenda_capacity_afternoon" value="<?php echo esc_attr( $capacity_config['afternoon'] ); ?>" min="1" max="100" class="regular-text" style="width: 100px;" />
                        <span style="margin-left: 8px;"><?php esc_html_e( 'atendimentos', 'desi-pet-shower' ); ?></span>
                        <p class="description"><?php esc_html_e( 'Número máximo de atendimentos no período da tarde.', 'desi-pet-shower' ); ?></p>
                    </div>
                </fieldset>

                <fieldset class="dps-fieldset">
                    <legend><?php esc_html_e( 'Localização do Petshop', 'desi-pet-shower' ); ?></legend>
                    
                    <div class="dps-notice dps-notice--info" style="margin-bottom: 16px;">
                        <span class="dashicons dashicons-location"></span>
                        <?php esc_html_e( 'O endereço é usado para GPS, navegação e convites de calendário. Este campo também é gerenciado na aba "Empresa".', 'desi-pet-shower' ); ?>
                    </div>

                    <div class="dps-form-row">
                        <label for="dps_shop_address"><?php esc_html_e( 'Endereço do Petshop', 'desi-pet-shower' ); ?></label>
                        <textarea id="dps_shop_address" name="dps_shop_address" rows="2" class="large-text"><?php echo esc_textarea( $shop_address ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Endereço completo utilizado para cálculos de GPS e rotas.', 'desi-pet-shower' ); ?></p>
                    </div>
                </fieldset>
            </div>

            <div class="dps-form-actions">
                <button type="submit" class="button button-primary button-large">
                    <span class="dashicons dashicons-saved" style="margin-top: 3px;"></span>
                    <?php esc_html_e( 'Salvar Configurações', 'desi-pet-shower' ); ?>
                </button>
            </div>
        </form>
        <?php
    }

    /**
     * Processa salvamento da aba Agenda.
     *
     * @return void
     */
    private static function handle_save_agenda() {
        // Página da agenda
        if ( isset( $_POST['dps_agenda_page_id'] ) ) {
            $page_id = absint( wp_unslash( $_POST['dps_agenda_page_id'] ) );
            update_option( 'dps_agenda_page_id', $page_id );
        }

        // Capacidade por período
        $capacity_morning   = isset( $_POST['dps_agenda_capacity_morning'] ) ? absint( $_POST['dps_agenda_capacity_morning'] ) : 10;
        $capacity_afternoon = isset( $_POST['dps_agenda_capacity_afternoon'] ) ? absint( $_POST['dps_agenda_capacity_afternoon'] ) : 10;

        // Valida limites (mínimo 1, máximo 100)
        $capacity_morning   = max( 1, min( 100, $capacity_morning ) );
        $capacity_afternoon = max( 1, min( 100, $capacity_afternoon ) );

        // Salva usando o helper se disponível, caso contrário diretamente na option
        if ( class_exists( 'DPS_Agenda_Capacity_Helper' ) ) {
            DPS_Agenda_Capacity_Helper::save_capacity_config( [
                'morning'   => $capacity_morning,
                'afternoon' => $capacity_afternoon,
            ] );
        } else {
            update_option( 'dps_agenda_capacity_config', [
                'morning'   => $capacity_morning,
                'afternoon' => $capacity_afternoon,
            ] );
        }

        // Endereço do petshop
        if ( isset( $_POST['dps_shop_address'] ) ) {
            $address = sanitize_textarea_field( wp_unslash( $_POST['dps_shop_address'] ) );
            update_option( 'dps_shop_address', $address );
        }

        // Log de auditoria
        DPS_Logger::log(
            sprintf(
                /* translators: %d: User ID who modified settings */
                __( 'Configurações da Agenda atualizadas pelo usuário ID %d', 'desi-pet-shower' ),
                get_current_user_id()
            ),
            DPS_Logger::LEVEL_INFO,
            'agenda_settings_updated'
        );

        DPS_Message_Helper::add_success( __( 'Configurações da Agenda salvas com sucesso!', 'desi-pet-shower' ) );
    }

    // ========================================
    // FASE 6.5: Aba Logins de Clientes
    // ========================================

    /**
     * Renderiza a aba Logins de Clientes (Client Portal Add-on).
     *
     * @return void
     */
    public static function render_tab_logins_clientes() {
        if ( ! class_exists( 'DPS_Client_Portal' ) ) {
            echo '<p>' . esc_html__( 'O add-on Portal do Cliente não está ativo.', 'desi-pet-shower' ) . '</p>';
            return;
        }

        // Delega para o DPS_Portal_Admin que tem os métodos de gerenciamento de logins
        if ( class_exists( 'DPS_Portal_Admin' ) ) {
            $portal_admin = DPS_Portal_Admin::get_instance();
            if ( method_exists( $portal_admin, 'render_client_logins_page' ) ) {
                echo '<div class="dps-surface dps-surface--info">';
                echo '<h3 class="dps-surface__title">';
                echo '<span class="dashicons dashicons-admin-network"></span> ';
                esc_html_e( 'Gerenciamento de Logins de Clientes', 'desi-pet-shower' );
                echo '</h3>';
                echo '<p class="dps-surface__description">';
                esc_html_e( 'Gerencie os links de acesso (magic links) para que os clientes acessem o portal sem precisar de senha.', 'desi-pet-shower' );
                echo '</p>';
                $portal_admin->render_client_logins_page( 'frontend', '' );
                echo '</div>';
                return;
            }
        }

        // Fallback se DPS_Portal_Admin não estiver disponível
        echo '<div class="dps-notice dps-notice--warning">';
        echo '<span class="dashicons dashicons-info"></span> ';
        esc_html_e( 'Gerenciador de logins de clientes não disponível. Verifique se o add-on Portal do Cliente está instalado corretamente.', 'desi-pet-shower' );
        echo '</div>';
    }

    // ========================================
    // FASE 7: Aba Logins de Groomers
    // ========================================

    /**
     * Renderiza a aba Logins de Groomers (Groomers Add-on).
     *
     * @return void
     */
    public static function render_tab_groomers() {
        if ( ! class_exists( 'DPS_Groomers_Addon' ) ) {
            echo '<p>' . esc_html__( 'O add-on Groomers não está ativo.', 'desi-pet-shower' ) . '</p>';
            return;
        }

        // Usa a instância global do add-on se disponível
        $groomers_addon = function_exists( 'dps_groomers_get_instance' ) ? dps_groomers_get_instance() : null;
        
        // Fallback: cria nova instância se a global não estiver disponível
        if ( ! $groomers_addon ) {
            $groomers_addon = new DPS_Groomers_Addon();
        }
        
        if ( method_exists( $groomers_addon, 'render_groomer_tokens_content' ) ) {
            $groomers_addon->render_groomer_tokens_content();
        } else {
            // Fallback: renderiza versão simplificada se o método não existir
            self::render_groomers_fallback();
        }
    }

    /**
     * Renderiza conteúdo de fallback para aba Groomers.
     *
     * @return void
     */
    private static function render_groomers_fallback() {
        if ( ! class_exists( 'DPS_Groomer_Token_Manager' ) ) {
            echo '<p>' . esc_html__( 'Gerenciador de tokens não disponível.', 'desi-pet-shower' ) . '</p>';
            return;
        }

        $groomers = get_users( [ 'role' => 'dps_groomer' ] );
        $token_manager = DPS_Groomer_Token_Manager::get_instance();
        ?>
        <div class="dps-surface dps-surface--info">
            <h3 class="dps-surface__title">
                <span class="dashicons dashicons-admin-users"></span>
                <?php esc_html_e( 'Gerenciamento de Logins de Groomers', 'desi-pet-shower' ); ?>
            </h3>
            <p class="dps-surface__description">
                <?php esc_html_e( 'Gere links de acesso (magic links) para que os groomers acessem seu portal sem precisar de senha.', 'desi-pet-shower' ); ?>
            </p>

            <?php if ( empty( $groomers ) ) : ?>
                <div class="dps-notice dps-notice--warning">
                    <span class="dashicons dashicons-info"></span>
                    <?php esc_html_e( 'Nenhum groomer cadastrado. Cadastre groomers através do painel administrativo.', 'desi-pet-shower' ); ?>
                </div>
            <?php else : ?>
                <table class="dps-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="text-align: left; padding: 10px; border-bottom: 2px solid #e5e7eb;"><?php esc_html_e( 'Groomer', 'desi-pet-shower' ); ?></th>
                            <th style="text-align: left; padding: 10px; border-bottom: 2px solid #e5e7eb;"><?php esc_html_e( 'Email', 'desi-pet-shower' ); ?></th>
                            <th style="text-align: center; padding: 10px; border-bottom: 2px solid #e5e7eb;"><?php esc_html_e( 'Tokens Ativos', 'desi-pet-shower' ); ?></th>
                            <th style="text-align: left; padding: 10px; border-bottom: 2px solid #e5e7eb;"><?php esc_html_e( 'Último Acesso', 'desi-pet-shower' ); ?></th>
                            <th style="text-align: right; padding: 10px; border-bottom: 2px solid #e5e7eb;"><?php esc_html_e( 'Ações', 'desi-pet-shower' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $groomers as $groomer ) : 
                            $stats = $token_manager->get_groomer_stats( $groomer->ID );
                            $token_url = get_transient( 'dps_groomer_token_url_' . $groomer->ID );
                            
                            if ( $token_url ) {
                                delete_transient( 'dps_groomer_token_url_' . $groomer->ID );
                            }
                        ?>
                            <tr style="border-bottom: 1px solid #e5e7eb;">
                                <td style="padding: 12px 10px;">
                                    <strong><?php echo esc_html( $groomer->display_name ); ?></strong>
                                </td>
                                <td style="padding: 12px 10px; color: #6b7280;"><?php echo esc_html( $groomer->user_email ); ?></td>
                                <td style="padding: 12px 10px; text-align: center;">
                                    <span style="display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 13px; font-weight: 500; background: <?php echo $stats['active_tokens'] > 0 ? '#d1fae5' : '#f3f4f6'; ?>; color: <?php echo $stats['active_tokens'] > 0 ? '#065f46' : '#6b7280'; ?>;">
                                        <?php echo esc_html( $stats['active_tokens'] ); ?>
                                    </span>
                                </td>
                                <td style="padding: 12px 10px;">
                                    <?php 
                                    if ( $stats['last_used_at'] ) {
                                        echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $stats['last_used_at'] ) ) );
                                    } else {
                                        echo '<span style="color: #9ca3af;">' . esc_html__( 'Nunca', 'desi-pet-shower' ) . '</span>';
                                    }
                                    ?>
                                </td>
                                <td style="padding: 12px 10px; text-align: right;">
                                    <form method="post" style="display: inline-block;">
                                        <?php wp_nonce_field( 'dps_generate_groomer_token_' . $groomer->ID ); ?>
                                        <input type="hidden" name="dps_groomer_token_action" value="generate" />
                                        <input type="hidden" name="groomer_id" value="<?php echo esc_attr( $groomer->ID ); ?>" />
                                        <select name="token_type" style="padding: 4px 8px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 13px;">
                                            <option value="login"><?php esc_html_e( 'Temporário (30min)', 'desi-pet-shower' ); ?></option>
                                            <option value="permanent"><?php esc_html_e( 'Permanente', 'desi-pet-shower' ); ?></option>
                                        </select>
                                        <button type="submit" class="button button-primary" style="padding: 4px 12px; font-size: 13px;">
                                            <?php esc_html_e( 'Gerar Link', 'desi-pet-shower' ); ?>
                                        </button>
                                    </form>
                                    
                                    <?php if ( $stats['active_tokens'] > 0 ) : ?>
                                        <a href="<?php echo esc_url( wp_nonce_url( 
                                            add_query_arg( [
                                                'dps_groomer_token_action' => 'revoke_all',
                                                'groomer_id'               => $groomer->ID,
                                            ] ),
                                            'dps_revoke_all_groomer_tokens_' . $groomer->ID
                                        ) ); ?>" 
                                           class="button" 
                                           style="padding: 4px 12px; font-size: 13px; color: #dc2626; border-color: #dc2626;"
                                           onclick="return confirm('<?php echo esc_js( __( 'Revogar todos os tokens deste groomer?', 'desi-pet-shower' ) ); ?>');">
                                            <?php esc_html_e( 'Revogar Todos', 'desi-pet-shower' ); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            
                            <?php if ( $token_url ) : ?>
                            <tr style="background: #f0f9ff;">
                                <td colspan="5" style="padding: 12px 10px;">
                                    <div style="background: #fff; border: 1px solid #0ea5e9; border-radius: 6px; padding: 12px;">
                                        <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #0c4a6e;">
                                            <?php esc_html_e( 'Link de acesso gerado (copie agora, não será exibido novamente):', 'desi-pet-shower' ); ?>
                                        </label>
                                        <div style="display: flex; gap: 8px;">
                                            <input type="text" 
                                                   value="<?php echo esc_url( $token_url ); ?>" 
                                                   readonly 
                                                   onclick="this.select();" 
                                                   style="flex: 1; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 13px;" />
                                            <button type="button" 
                                                    class="button" 
                                                    onclick="navigator.clipboard.writeText('<?php echo esc_js( $token_url ); ?>'); this.textContent='✓ Copiado!';"
                                                    style="padding: 8px 16px;">
                                                📋 <?php esc_html_e( 'Copiar', 'desi-pet-shower' ); ?>
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
}
