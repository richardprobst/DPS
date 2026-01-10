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
}
