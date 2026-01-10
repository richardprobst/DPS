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

        // Aba Notificações (se Push Add-on ativo)
        if ( class_exists( 'DPS_Push_Addon' ) ) {
            self::register_tab(
                'notificacoes',
                __( '🔔 Notificações', 'desi-pet-shower' ),
                [ __CLASS__, 'render_tab_notificacoes' ],
                60
            );
        }

        // Aba Financeiro - Lembretes (se Finance Add-on ativo)
        if ( class_exists( 'DPS_Finance_Addon' ) ) {
            self::register_tab(
                'financeiro_lembretes',
                __( '💰 Financeiro', 'desi-pet-shower' ),
                [ __CLASS__, 'render_tab_financeiro_lembretes' ],
                70
            );
        }
    }

    /**
     * Registra abas de add-ons via hook.
     *
     * @return void
     */
    public static function register_addon_tabs() {
        /**
         * Hook para add-ons registrarem suas abas de configuração.
         *
         * @since 2.0.0
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

        // Hook para add-ons adicionarem abas via método legado
        do_action( 'dps_settings_nav_tabs' );
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

        // Hook para add-ons renderizarem suas seções (método legado)
        do_action( 'dps_settings_sections' );
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
        $whatsapp_number  = get_option( 'dps_whatsapp_number', '' );
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

                    <div class="dps-form-row">
                        <label for="dps_whatsapp_number"><?php esc_html_e( 'WhatsApp da Equipe', 'desi-pet-shower' ); ?></label>
                        <input type="text" id="dps_whatsapp_number" name="dps_whatsapp_number" value="<?php echo esc_attr( $whatsapp_number ); ?>" class="regular-text" placeholder="+55 11 99999-9999" />
                        <p class="description"><?php esc_html_e( 'Número para contato via WhatsApp. Use formato internacional.', 'desi-pet-shower' ); ?></p>
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
            'dps_whatsapp_number'  => [ __CLASS__, 'sanitize_phone' ],
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
}
