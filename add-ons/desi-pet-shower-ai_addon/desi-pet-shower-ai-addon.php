<?php
/**
 * Plugin Name:       DPS by PRObst – AI Add-on
 * Plugin URI:        https://www.probst.pro
 * Description:       Assistente virtual inteligente para o Portal do Cliente e chat público para visitantes. Responde sobre agendamentos, serviços e histórico. Sugere mensagens para WhatsApp e e-mail. Inclui FAQs, feedback, analytics, agendamento via chat e chat público via shortcode.
 * Version:           1.6.1
 * Author:            PRObst
 * Author URI:        https://www.probst.pro
 * Text Domain:       dps-ai
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 *
 * Este add-on implementa um assistente virtual no Portal do Cliente com foco
 * EXCLUSIVO em assuntos relacionados a:
 * - Banho e Tosa
 * - Serviços do pet shop
 * - Dados do cliente e pets cadastrados
 * - Funcionalidades do sistema DPS (agenda, histórico, fidelidade, pagamentos, assinaturas)
 *
 * O assistente NÃO responde sobre assuntos aleatórios fora desse contexto
 * (política, religião, finanças pessoais, etc.).
 *
 * NOVO v1.6.0: Chat público para visitantes via shortcode [dps_ai_public_chat].
 * Permite que visitantes tirem dúvidas sobre serviços, preços e funcionamento
 * sem necessidade de login. Inclui rate limiting, temas claro/escuro, modo
 * inline e flutuante, FAQs customizáveis e integração com base de conhecimento.
 *
 * NOVO v1.5.0: FAQs sugeridas, feedback positivo/negativo, métricas de uso,
 * base de conhecimento, widget flutuante, multi-idiomas, agendamento via chat
 * e dashboard de analytics.
 *
 * NOVO v1.4.0: Interface modernizada, modelos GPT atualizados, teste de conexão,
 * histórico de conversas persistente na sessão.
 *
 * NOVO v1.2.0: Assistente de comunicações - gera sugestões de mensagens para
 * WhatsApp e e-mail. NUNCA envia automaticamente. Apenas sugere textos que
 * o usuário humano revisa e confirma antes de enviar.
 */

// Bloqueia acesso direto aos arquivos.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Verifica se o plugin base DPS by PRObst está ativo.
 * Se não estiver, exibe aviso e interrompe carregamento do add-on.
 */
function dps_ai_check_base_plugin() {
    if ( ! class_exists( 'DPS_Base_Plugin' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__( 'O add-on AI requer o plugin base DPS by PRObst para funcionar.', 'dps-ai' );
            echo '</p></div>';
        } );
        return false;
    }
    return true;
}
add_action( 'plugins_loaded', function() {
    if ( ! dps_ai_check_base_plugin() ) {
        return;
    }
}, 1 );

// Define constantes úteis do add-on.
if ( ! defined( 'DPS_AI_ADDON_DIR' ) ) {
    define( 'DPS_AI_ADDON_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'DPS_AI_ADDON_URL' ) ) {
    define( 'DPS_AI_ADDON_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'DPS_AI_VERSION' ) ) {
    define( 'DPS_AI_VERSION', '1.6.1' );
}

/**
 * Capability específica para uso do assistente de IA.
 *
 * @var string
 */
if ( ! defined( 'DPS_AI_CAPABILITY' ) ) {
    define( 'DPS_AI_CAPABILITY', 'dps_use_ai_assistant' );
}

/**
 * Versão do schema de banco de dados do AI Add-on.
 * Incrementar quando houver mudanças nas tabelas.
 * 
 * IMPORTANTE: Esta versão rastreia o schema do banco de dados, não a versão do plugin.
 * - Plugin version (DPS_AI_VERSION): rastreia releases de funcionalidades (ex: 1.6.0)
 * - DB schema version (DPS_AI_DB_VERSION): rastreia mudanças de estrutura de dados (ex: 1.5.0)
 * 
 * O schema DB pode permanecer estável por várias versões de plugin se não houver
 * mudanças nas tabelas. Use versão semântica: MAJOR.MINOR.PATCH.
 *
 * @var string
 */
if ( ! defined( 'DPS_AI_DB_VERSION' ) ) {
    define( 'DPS_AI_DB_VERSION', '1.5.0' );
}

/**
 * Carrega o text domain do AI Add-on.
 * Usa prioridade 1 para garantir que rode antes da inicialização da classe (prioridade 5).
 */
function dps_ai_load_textdomain() {
    load_plugin_textdomain( 'dps-ai', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'dps_ai_load_textdomain', 1 );

// Inclui as classes principais ANTES dos hooks de ativação/desativação.
require_once DPS_AI_ADDON_DIR . 'includes/dps-ai-logger.php';
require_once DPS_AI_ADDON_DIR . 'includes/class-dps-ai-client.php';
require_once DPS_AI_ADDON_DIR . 'includes/class-dps-ai-assistant.php';
require_once DPS_AI_ADDON_DIR . 'includes/class-dps-ai-integration-portal.php';
require_once DPS_AI_ADDON_DIR . 'includes/class-dps-ai-message-assistant.php';
require_once DPS_AI_ADDON_DIR . 'includes/class-dps-ai-analytics.php';
require_once DPS_AI_ADDON_DIR . 'includes/class-dps-ai-knowledge-base.php';
require_once DPS_AI_ADDON_DIR . 'includes/class-dps-ai-scheduler.php';
require_once DPS_AI_ADDON_DIR . 'includes/class-dps-ai-public-chat.php';
require_once DPS_AI_ADDON_DIR . 'includes/class-dps-ai-maintenance.php';

/**
 * Verifica e atualiza o schema do banco de dados quando necessário.
 * 
 * Esta função roda em 'plugins_loaded' para garantir que as tabelas sejam criadas
 * mesmo quando o plugin é atualizado (não apenas na ativação).
 * 
 * HISTÓRICO:
 * - v1.5.0: Introduzidas tabelas dps_ai_metrics e dps_ai_feedback
 * - Fix: Usuários que atualizaram de v1.4.0 para v1.5.0+ sem desativar/reativar
 *   não tinham as tabelas criadas, causando erros na página de analytics.
 *
 * @since 1.6.1
 */
function dps_ai_maybe_upgrade_database() {
    // Verifica versão instalada do schema
    $installed_version = get_option( 'dps_ai_db_version', '0' );
    
    // Se já está na versão mais recente, não faz nada
    if ( version_compare( $installed_version, DPS_AI_DB_VERSION, '>=' ) ) {
        return;
    }
    
    // Cria ou atualiza tabelas quando necessário
    // 
    // PADRÃO DE MIGRAÇÕES:
    // Cada bloco de migração verifica contra a versão onde foi introduzida.
    // Ao final de cada migração, atualiza para DPS_AI_DB_VERSION (versão atual do schema).
    // 
    // EXEMPLO: Se DPS_AI_DB_VERSION for '1.6.0' e houver uma nova migração v1.6.0,
    // adicione um novo bloco ANTES deste:
    //
    //   if ( version_compare( $installed_version, '1.6.0', '<' ) ) {
    //       // Executar migração v1.6.0 (ex: adicionar nova coluna)
    //       update_option( 'dps_ai_db_version', '1.6.0' );
    //   }
    //
    // Se o usuário estiver em v1.4.0, ambas as migrações executarão em sequência,
    // atualizando primeiro para 1.5.0, depois para 1.6.0.
    
    if ( version_compare( $installed_version, '1.5.0', '<' ) ) {
        // v1.5.0: Criar tabelas de analytics e feedback
        if ( class_exists( 'DPS_AI_Analytics' ) ) {
            DPS_AI_Analytics::maybe_create_tables();
        }
        
        // Atualiza para versão 1.5.0 especificamente
        update_option( 'dps_ai_db_version', '1.5.0' );
    }
    
    // Futuras migrações devem ser adicionadas aqui com version_compare seguindo o padrão acima
}

/**
 * Hook para executar upgrade do banco de dados.
 * Prioridade 10: executa depois da verificação do plugin base (prioridade 1).
 */
add_action( 'plugins_loaded', 'dps_ai_maybe_upgrade_database', 10 );

/**
 * Ativação do plugin: adiciona capabilities e cria tabelas.
 */
function dps_ai_activate() {
    // Adiciona capability aos roles que podem editar posts
    $roles_with_capability = [ 'administrator', 'editor' ];

    foreach ( $roles_with_capability as $role_name ) {
        $role = get_role( $role_name );
        if ( $role ) {
            $role->add_cap( DPS_AI_CAPABILITY );
        }
    }

    // Cria tabelas de analytics
    if ( class_exists( 'DPS_AI_Analytics' ) ) {
        DPS_AI_Analytics::maybe_create_tables();
    }
    
    // Define versão do schema após criar tabelas
    update_option( 'dps_ai_db_version', DPS_AI_DB_VERSION );

    // Limpa rewrite rules
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'dps_ai_activate' );

/**
 * Desativação do plugin: limpa transients temporários e desagenda cron jobs.
 */
function dps_ai_deactivate() {
    // Desagenda evento de limpeza automática
    if ( class_exists( 'DPS_AI_Maintenance' ) ) {
        DPS_AI_Maintenance::unschedule_cleanup();
    }
    
    // Limpa transients de contexto (são temporários, não precisam persistir)
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Necessário para limpeza de transients na desativação
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            '_transient_dps_ai_ctx_%',
            '_transient_timeout_dps_ai_ctx_%'
        )
    );

    // Limpa cache
    if ( function_exists( 'wp_cache_flush' ) ) {
        wp_cache_flush();
    }
}
register_deactivation_hook( __FILE__, 'dps_ai_deactivate' );

/**
 * Classe principal do add-on de IA.
 * Gerencia configurações, integração com admin e inicialização dos componentes.
 */
class DPS_AI_Addon {

    /**
     * Chave da opção no banco de dados.
     *
     * @var string
     */
    const OPTION_KEY = 'dps_ai_settings';

    /**
     * Instância única (singleton).
     *
     * @var DPS_AI_Addon|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única.
     *
     * @return DPS_AI_Addon
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado. Registra hooks necessários.
     */
    private function __construct() {
        // Registra menu admin
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ], 20 );

        // Processa salvamento de configurações
        add_action( 'init', [ $this, 'maybe_handle_save' ] );

        // Inicializa integração com Portal do Cliente
        // Prioridade 20: executa após text domain (prioridade 1) e addon init (prioridade 5)
        add_action( 'init', [ $this, 'init_portal_integration' ], 20 );

        // Inicializa componentes v1.5.0
        // Prioridade 21: executa após portal integration para garantir ordem de inicialização
        add_action( 'init', [ $this, 'init_components' ], 21 );

        // Registra handlers AJAX para sugestões de mensagens
        add_action( 'wp_ajax_dps_ai_suggest_whatsapp_message', [ $this, 'ajax_suggest_whatsapp_message' ] );
        add_action( 'wp_ajax_dps_ai_suggest_email_message', [ $this, 'ajax_suggest_email_message' ] );
        add_action( 'wp_ajax_dps_ai_test_connection', [ $this, 'ajax_test_connection' ] );

        // Registra assets admin
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

        // Registra handlers de exportação CSV
        add_action( 'admin_post_dps_ai_export_metrics', [ $this, 'handle_export_metrics' ] );
        add_action( 'admin_post_dps_ai_export_feedback', [ $this, 'handle_export_feedback' ] );
    }

    /**
     * Inicializa a integração com o Portal do Cliente.
     */
    public function init_portal_integration() {
        if ( class_exists( 'DPS_AI_Integration_Portal' ) ) {
            DPS_AI_Integration_Portal::get_instance();
        }
    }

    /**
     * Inicializa componentes adicionais (v1.5.0+).
     */
    public function init_components() {
        // Analytics e métricas
        if ( class_exists( 'DPS_AI_Analytics' ) ) {
            DPS_AI_Analytics::get_instance();
        }

        // Base de conhecimento
        if ( class_exists( 'DPS_AI_Knowledge_Base' ) ) {
            DPS_AI_Knowledge_Base::get_instance();
        }

        // Agendamento via chat
        if ( class_exists( 'DPS_AI_Scheduler' ) ) {
            DPS_AI_Scheduler::get_instance();
        }

        // Chat público para visitantes (v1.6.0+)
        if ( class_exists( 'DPS_AI_Public_Chat' ) ) {
            DPS_AI_Public_Chat::get_instance();
        }

        // Manutenção automática (v1.6.1+)
        if ( class_exists( 'DPS_AI_Maintenance' ) ) {
            DPS_AI_Maintenance::get_instance();
        }
    }

    /**
     * Registra submenu admin para configurações de IA.
     */
    public function register_admin_menu() {
        add_submenu_page(
            'desi-pet-shower',
            __( 'Assistente de IA', 'dps-ai' ),
            __( 'Assistente de IA', 'dps-ai' ),
            'manage_options',
            'dps-ai-settings',
            [ $this, 'render_admin_page' ]
        );

        // Página de Analytics
        add_submenu_page(
            'desi-pet-shower',
            __( 'Analytics de IA', 'dps-ai' ),
            __( 'Analytics de IA', 'dps-ai' ),
            'manage_options',
            'dps-ai-analytics',
            [ $this, 'render_analytics_page' ]
        );
    }

    /**
     * Renderiza a página admin de configurações.
     */
    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'dps-ai' ) );
        }

        $options = get_option( self::OPTION_KEY, [] );
        $status  = isset( $_GET['updated'] ) ? sanitize_text_field( wp_unslash( $_GET['updated'] ) ) : '';
        $truncated = isset( $_GET['truncated'] ) ? sanitize_text_field( wp_unslash( $_GET['truncated'] ) ) : '';

        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <p><?php echo esc_html__( 'Configure o assistente virtual de IA para o Portal do Cliente. O assistente responde APENAS sobre serviços de Banho e Tosa, agendamentos, histórico e funcionalidades do DPS.', 'dps-ai' ); ?></p>

            <?php if ( '1' === $status ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html__( 'Configurações salvas com sucesso.', 'dps-ai' ); ?></p>
                </div>
            <?php endif; ?>

            <?php if ( '1' === $truncated ) : ?>
                <div class="notice notice-warning is-dismissible">
                    <p><?php echo esc_html__( 'Atenção: As instruções adicionais foram reduzidas para 2000 caracteres (limite máximo). Revise o texto salvo.', 'dps-ai' ); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <input type="hidden" name="dps_ai_action" value="save_settings" />
                <?php wp_nonce_field( 'dps_ai_save', 'dps_ai_nonce' ); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="dps_ai_enabled"><?php echo esc_html__( 'Ativar Assistente de IA', 'dps-ai' ); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="dps_ai_enabled" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enabled]" value="1" <?php checked( ! empty( $options['enabled'] ) ); ?> />
                                    <?php esc_html_e( 'Habilitar assistente virtual no Portal do Cliente', 'dps-ai' ); ?>
                                </label>
                                <p class="description"><?php esc_html_e( 'Quando desativado, o widget de IA não aparece no portal.', 'dps-ai' ); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="dps_ai_api_key"><?php echo esc_html__( 'Chave de API da OpenAI', 'dps-ai' ); ?></label>
                            </th>
                            <td>
                                <div style="display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                    <div style="position: relative; display: inline-block;">
                                        <input type="password" id="dps_ai_api_key" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[api_key]" value="<?php echo esc_attr( $options['api_key'] ?? '' ); ?>" class="regular-text" style="padding-right: 40px;" />
                                        <button type="button" id="dps_ai_toggle_api_key" class="button" style="position: absolute; right: 2px; top: 50%; transform: translateY(-50%); padding: 0; width: 32px; height: 28px; border: none; background: transparent; cursor: pointer;" title="<?php esc_attr_e( 'Mostrar/Ocultar API Key', 'dps-ai' ); ?>">
                                            <span class="dashicons dashicons-visibility" style="line-height: 28px; width: 32px; height: 28px; font-size: 18px; color: #666;"></span>
                                        </button>
                                    </div>
                                    <button type="button" id="dps_ai_test_connection" class="button">
                                        <?php esc_html_e( 'Testar Conexão', 'dps-ai' ); ?>
                                    </button>
                                    <span id="dps_ai_test_result" style="display: none;"></span>
                                </div>
                                <p class="description"><?php esc_html_e( 'Token de autenticação da API da OpenAI (sk-...). Mantenha em segredo.', 'dps-ai' ); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="dps_ai_model"><?php echo esc_html__( 'Modelo GPT', 'dps-ai' ); ?></label>
                            </th>
                            <td>
                                <select id="dps_ai_model" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[model]">
                                    <option value="gpt-4o-mini" <?php selected( $options['model'] ?? 'gpt-4o-mini', 'gpt-4o-mini' ); ?>>GPT-4o Mini (Recomendado - Rápido e econômico)</option>
                                    <option value="gpt-4o" <?php selected( $options['model'] ?? '', 'gpt-4o' ); ?>>GPT-4o (Mais preciso, custo médio)</option>
                                    <option value="gpt-4-turbo" <?php selected( $options['model'] ?? '', 'gpt-4-turbo' ); ?>>GPT-4 Turbo (Alta precisão)</option>
                                    <option value="gpt-3.5-turbo" <?php selected( $options['model'] ?? '', 'gpt-3.5-turbo' ); ?>>GPT-3.5 Turbo (Legado - Mais barato)</option>
                                </select>
                                <p class="description"><?php esc_html_e( 'Modelo de linguagem a ser utilizado. GPT-4o Mini é recomendado para melhor custo/benefício em 2024+.', 'dps-ai' ); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="dps_ai_temperature"><?php echo esc_html__( 'Temperatura', 'dps-ai' ); ?></label>
                            </th>
                            <td>
                                <input type="number" id="dps_ai_temperature" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[temperature]" value="<?php echo esc_attr( $options['temperature'] ?? '0.4' ); ?>" min="0" max="1" step="0.1" class="small-text" />
                                <p class="description"><?php esc_html_e( 'Controla a criatividade das respostas (0 = mais preciso/focado, 1 = mais criativo). Recomendado: 0.4', 'dps-ai' ); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="dps_ai_timeout"><?php echo esc_html__( 'Timeout (segundos)', 'dps-ai' ); ?></label>
                            </th>
                            <td>
                                <input type="number" id="dps_ai_timeout" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[timeout]" value="<?php echo esc_attr( $options['timeout'] ?? '10' ); ?>" min="5" max="60" class="small-text" />
                                <p class="description"><?php esc_html_e( 'Tempo máximo de espera pela resposta da API (segundos). Recomendado: 10', 'dps-ai' ); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="dps_ai_max_tokens"><?php echo esc_html__( 'Máximo de Tokens', 'dps-ai' ); ?></label>
                            </th>
                            <td>
                                <input type="number" id="dps_ai_max_tokens" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[max_tokens]" value="<?php echo esc_attr( $options['max_tokens'] ?? '500' ); ?>" min="100" max="2000" class="small-text" />
                                <p class="description"><?php esc_html_e( 'Limite de tokens na resposta (afeta custo e tamanho da resposta). Recomendado: 500', 'dps-ai' ); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="dps_ai_additional_instructions"><?php echo esc_html__( 'Instruções adicionais para a IA (opcional)', 'dps-ai' ); ?></label>
                            </th>
                            <td>
                                <textarea id="dps_ai_additional_instructions" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[additional_instructions]" rows="6" class="large-text" maxlength="2000"><?php echo esc_textarea( $options['additional_instructions'] ?? '' ); ?></textarea>
                                <p class="description">
                                    <?php esc_html_e( 'Use este campo para adicionar regras específicas de como a IA deve se comunicar com os clientes, dentro do contexto de Banho e Tosa e do DPS by PRObst.', 'dps-ai' ); ?>
                                    <br />
                                    <strong><?php esc_html_e( 'Importante:', 'dps-ai' ); ?></strong>
                                    <?php esc_html_e( 'As regras principais de segurança e escopo do sistema já são aplicadas automaticamente. Não remova ou contradiga essas regras.', 'dps-ai' ); ?>
                                    <br />
                                    <?php esc_html_e( 'Use este campo apenas para complementar (ex.: tom de voz, expressões, estilo de atendimento, orientações da marca). Máximo: 2000 caracteres.', 'dps-ai' ); ?>
                                    <br />
                                    <span id="dps_ai_char_count" style="font-weight: 600; color: #666;">
                                        <?php 
                                        $current_length = isset( $options['additional_instructions'] ) ? mb_strlen( $options['additional_instructions'] ) : 0;
                                        echo esc_html( sprintf( __( 'Caracteres: %d / 2000', 'dps-ai' ), $current_length ) );
                                        ?>
                                    </span>
                                </p>
                                <script>
                                    (function() {
                                        var textarea = document.getElementById('dps_ai_additional_instructions');
                                        var counter = document.getElementById('dps_ai_char_count');
                                        if (textarea && counter) {
                                            textarea.addEventListener('input', function() {
                                                var length = textarea.value.length;
                                                counter.textContent = 'Caracteres: ' + length + ' / 2000';
                                                counter.style.color = length > 2000 ? '#d63638' : (length > 1800 ? '#dba617' : '#666');
                                            });
                                        }
                                    })();
                                </script>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <h2><?php esc_html_e( 'Configurações Avançadas (v1.5.0)', 'dps-ai' ); ?></h2>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="dps_ai_widget_mode"><?php echo esc_html__( 'Modo do Widget', 'dps-ai' ); ?></label>
                            </th>
                            <td>
                                <select id="dps_ai_widget_mode" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[widget_mode]">
                                    <option value="inline" <?php selected( $options['widget_mode'] ?? 'inline', 'inline' ); ?>><?php esc_html_e( 'Integrado (no topo do portal)', 'dps-ai' ); ?></option>
                                    <option value="floating" <?php selected( $options['widget_mode'] ?? '', 'floating' ); ?>><?php esc_html_e( 'Flutuante (botão no canto)', 'dps-ai' ); ?></option>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="dps_ai_floating_position"><?php echo esc_html__( 'Posição do Widget Flutuante', 'dps-ai' ); ?></label>
                            </th>
                            <td>
                                <select id="dps_ai_floating_position" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[floating_position]">
                                    <option value="bottom-right" <?php selected( $options['floating_position'] ?? 'bottom-right', 'bottom-right' ); ?>><?php esc_html_e( 'Inferior direito', 'dps-ai' ); ?></option>
                                    <option value="bottom-left" <?php selected( $options['floating_position'] ?? '', 'bottom-left' ); ?>><?php esc_html_e( 'Inferior esquerdo', 'dps-ai' ); ?></option>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="dps_ai_scheduling_mode"><?php echo esc_html__( 'Modo de Agendamento via Chat', 'dps-ai' ); ?></label>
                            </th>
                            <td>
                                <select id="dps_ai_scheduling_mode" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[scheduling_mode]">
                                    <option value="disabled" <?php selected( $options['scheduling_mode'] ?? 'disabled', 'disabled' ); ?>><?php esc_html_e( 'Desabilitado', 'dps-ai' ); ?></option>
                                    <option value="request" <?php selected( $options['scheduling_mode'] ?? '', 'request' ); ?>><?php esc_html_e( 'Solicitar confirmação (equipe confirma)', 'dps-ai' ); ?></option>
                                    <option value="direct" <?php selected( $options['scheduling_mode'] ?? '', 'direct' ); ?>><?php esc_html_e( 'Agendamento direto (confirmação automática)', 'dps-ai' ); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e( 'Define como os agendamentos solicitados via chat são processados.', 'dps-ai' ); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="dps_ai_language"><?php echo esc_html__( 'Idioma das Respostas', 'dps-ai' ); ?></label>
                            </th>
                            <td>
                                <select id="dps_ai_language" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[language]">
                                    <option value="pt_BR" <?php selected( $options['language'] ?? 'pt_BR', 'pt_BR' ); ?>><?php esc_html_e( 'Português (Brasil)', 'dps-ai' ); ?></option>
                                    <option value="en_US" <?php selected( $options['language'] ?? '', 'en_US' ); ?>><?php esc_html_e( 'English (US)', 'dps-ai' ); ?></option>
                                    <option value="es_ES" <?php selected( $options['language'] ?? '', 'es_ES' ); ?>><?php esc_html_e( 'Español', 'dps-ai' ); ?></option>
                                    <option value="auto" <?php selected( $options['language'] ?? '', 'auto' ); ?>><?php esc_html_e( 'Automático (detectar)', 'dps-ai' ); ?></option>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="dps_ai_faq_suggestions"><?php echo esc_html__( 'Sugestões de Perguntas (FAQs)', 'dps-ai' ); ?></label>
                            </th>
                            <td>
                                <textarea id="dps_ai_faq_suggestions" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[faq_suggestions]" rows="5" class="large-text"><?php echo esc_textarea( $options['faq_suggestions'] ?? '' ); ?></textarea>
                                <p class="description">
                                    <?php esc_html_e( 'Uma pergunta por linha. Estas serão exibidas como botões clicáveis no widget.', 'dps-ai' ); ?>
                                    <br />
                                    <?php esc_html_e( 'Deixe em branco para usar as perguntas padrão.', 'dps-ai' ); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_feedback]" value="1" <?php checked( ! empty( $options['enable_feedback'] ) ); ?> />
                                    <?php esc_html_e( 'Habilitar feedback (👍/👎)', 'dps-ai' ); ?>
                                </label>
                            </th>
                            <td>
                                <p class="description"><?php esc_html_e( 'Permite que clientes avaliem as respostas da IA.', 'dps-ai' ); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_analytics]" value="1" <?php checked( $options['enable_analytics'] ?? '1', '1' ); ?> />
                                    <?php esc_html_e( 'Habilitar coleta de métricas', 'dps-ai' ); ?>
                                </label>
                            </th>
                            <td>
                                <p class="description"><?php esc_html_e( 'Registra uso da IA para análise no dashboard de analytics.', 'dps-ai' ); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <h2><?php esc_html_e( 'Chat Público para Visitantes (v1.6.0)', 'dps-ai' ); ?></h2>
                <p><?php esc_html_e( 'Configure o chat de IA aberto para visitantes do site. Diferente do chat do Portal, este não requer login e é focado em informações gerais sobre os serviços.', 'dps-ai' ); ?></p>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[public_chat_enabled]" value="1" <?php checked( ! empty( $options['public_chat_enabled'] ) ); ?> />
                                    <?php esc_html_e( 'Habilitar Chat Público', 'dps-ai' ); ?>
                                </label>
                            </th>
                            <td>
                                <p class="description"><?php esc_html_e( 'Permite que visitantes não logados usem o assistente via shortcode [dps_ai_public_chat].', 'dps-ai' ); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="dps_ai_public_chat_faqs"><?php echo esc_html__( 'Perguntas Frequentes (FAQs)', 'dps-ai' ); ?></label>
                            </th>
                            <td>
                                <textarea id="dps_ai_public_chat_faqs" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[public_chat_faqs]" rows="4" class="large-text"><?php echo esc_textarea( $options['public_chat_faqs'] ?? '' ); ?></textarea>
                                <p class="description">
                                    <?php esc_html_e( 'Uma pergunta por linha. Serão exibidas como botões clicáveis no chat público.', 'dps-ai' ); ?>
                                    <br />
                                    <?php esc_html_e( 'Deixe em branco para usar as perguntas padrão.', 'dps-ai' ); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="dps_ai_public_chat_business_info"><?php echo esc_html__( 'Informações do Negócio', 'dps-ai' ); ?></label>
                            </th>
                            <td>
                                <textarea id="dps_ai_public_chat_business_info" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[public_chat_business_info]" rows="6" class="large-text" maxlength="2000"><?php echo esc_textarea( $options['public_chat_business_info'] ?? '' ); ?></textarea>
                                <p class="description">
                                    <?php esc_html_e( 'Informações sobre seu negócio que a IA pode usar para responder (horários, endereço, formas de pagamento, diferenciais, etc.).', 'dps-ai' ); ?>
                                    <br />
                                    <?php esc_html_e( 'Exemplo: "Funcionamos de segunda a sábado, das 8h às 18h. Aceitamos PIX, cartão e dinheiro. Temos estacionamento próprio."', 'dps-ai' ); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="dps_ai_public_chat_instructions"><?php echo esc_html__( 'Instruções Adicionais', 'dps-ai' ); ?></label>
                            </th>
                            <td>
                                <textarea id="dps_ai_public_chat_instructions" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[public_chat_instructions]" rows="4" class="large-text" maxlength="1000"><?php echo esc_textarea( $options['public_chat_instructions'] ?? '' ); ?></textarea>
                                <p class="description">
                                    <?php esc_html_e( 'Instruções adicionais para o comportamento do chat público (tom de voz, estilo de atendimento, etc.).', 'dps-ai' ); ?>
                                    <br />
                                    <?php esc_html_e( 'Máximo: 1000 caracteres.', 'dps-ai' ); ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Seção: Manutenção e Logs (v1.6.1+) -->
                <h2><?php esc_html_e( 'Manutenção e Logs', 'dps-ai' ); ?></h2>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="dps_ai_data_retention_days"><?php echo esc_html__( 'Período de Retenção de Dados', 'dps-ai' ); ?></label>
                            </th>
                            <td>
                                <input type="number" id="dps_ai_data_retention_days" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[data_retention_days]" value="<?php echo esc_attr( $options['data_retention_days'] ?? '365' ); ?>" min="30" max="3650" step="1" class="small-text" />
                                <span><?php esc_html_e( 'dias', 'dps-ai' ); ?></span>
                                <p class="description">
                                    <?php esc_html_e( 'Dados de métricas e feedback mais antigos que este período serão automaticamente deletados.', 'dps-ai' ); ?>
                                    <br />
                                    <?php esc_html_e( 'Padrão: 365 dias (1 ano). Mínimo: 30 dias. Máximo: 3650 dias (10 anos).', 'dps-ai' ); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="dps_ai_debug_logging"><?php echo esc_html__( 'Habilitar Logs Detalhados', 'dps-ai' ); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="dps_ai_debug_logging" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[debug_logging]" value="1" <?php checked( ! empty( $options['debug_logging'] ) ); ?> />
                                    <?php esc_html_e( 'Ativar logging detalhado em ambiente de produção', 'dps-ai' ); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e( 'Quando desativado, apenas erros críticos são registrados em produção (WP_DEBUG desabilitado).', 'dps-ai' ); ?>
                                    <br />
                                    <?php esc_html_e( 'Habilite temporariamente para diagnosticar problemas. Logs excessivos podem consumir espaço em disco.', 'dps-ai' ); ?>
                                    <?php if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) : ?>
                                        <br />
                                        <strong style="color: #d97706;"><?php esc_html_e( 'WP_DEBUG está habilitado. Logs detalhados sempre estarão ativos.', 'dps-ai' ); ?></strong>
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="dps_ai_usd_to_brl"><?php echo esc_html__( 'Taxa de Conversão USD → BRL', 'dps-ai' ); ?></label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="dps_ai_usd_to_brl" 
                                       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[usd_to_brl_rate]" 
                                       value="<?php echo esc_attr( $options['usd_to_brl_rate'] ?? '' ); ?>" 
                                       min="0.01" 
                                       max="100" 
                                       step="0.01" 
                                       class="small-text" 
                                       placeholder="5.00" />
                                <p class="description">
                                    <?php esc_html_e( 'Taxa para converter custos de USD para BRL no dashboard de analytics.', 'dps-ai' ); ?>
                                    <br />
                                    <?php esc_html_e( 'Exemplo: Se 1 USD = 5.20 BRL, insira "5.20". Se não configurado, apenas valores em USD serão exibidos.', 'dps-ai' ); ?>
                                    <br />
                                    <span style="color: #6b7280; font-size: 12px;">
                                        <?php 
                                        if ( ! empty( $options['usd_to_brl_rate'] ) ) {
                                            printf(
                                                /* translators: %s: taxa atual */
                                                esc_html__( 'Taxa atual: 1 USD = %s BRL', 'dps-ai' ),
                                                '<strong>' . esc_html( number_format( (float) $options['usd_to_brl_rate'], 2, ',', '.' ) ) . '</strong>'
                                            );
                                        } else {
                                            esc_html_e( 'Nenhuma taxa configurada. Defina uma taxa para ver custos em BRL.', 'dps-ai' );
                                        }
                                        ?>
                                    </span>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <?php echo esc_html__( 'Limpeza Manual', 'dps-ai' ); ?>
                            </th>
                            <td>
                                <button type="button" id="dps_ai_manual_cleanup" class="button">
                                    <?php esc_html_e( 'Executar Limpeza Agora', 'dps-ai' ); ?>
                                </button>
                                <span id="dps_ai_cleanup_result" style="margin-left: 10px; display: none;"></span>
                                <p class="description">
                                    <?php esc_html_e( 'Remove dados antigos, transients expirados e otimiza o banco de dados.', 'dps-ai' ); ?>
                                    <br />
                                    <?php
                                    $stats = DPS_AI_Maintenance::get_storage_stats();
                                    printf(
                                        /* translators: 1: número de métricas, 2: número de feedbacks, 3: data mais antiga métrica, 4: data mais antiga feedback */
                                        esc_html__( 'Atualmente: %1$s métricas, %2$s feedbacks. Dados mais antigos: %3$s (métricas), %4$s (feedback).', 'dps-ai' ),
                                        '<strong>' . esc_html( number_format_i18n( $stats['metrics_count'] ) ) . '</strong>',
                                        '<strong>' . esc_html( number_format_i18n( $stats['feedback_count'] ) ) . '</strong>',
                                        '<strong>' . esc_html( $stats['oldest_metric'] ) . '</strong>',
                                        '<strong>' . esc_html( $stats['oldest_feedback'] ) . '</strong>'
                                    );
                                    ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div style="background: #f0f9ff; padding: 20px; border-radius: 8px; border-left: 4px solid #0ea5e9; margin: 20px 0;">
                    <h3 style="margin: 0 0 10px 0; font-size: 16px; color: #0284c7;"><?php esc_html_e( 'Como usar o Chat Público', 'dps-ai' ); ?></h3>
                    <p style="margin: 0 0 10px 0;"><?php esc_html_e( 'Adicione o shortcode em qualquer página do seu site:', 'dps-ai' ); ?></p>
                    <code style="background: #fff; padding: 8px 12px; border-radius: 4px; display: inline-block;">[dps_ai_public_chat]</code>
                    <p style="margin: 15px 0 0 0; font-size: 13px; color: #6b7280;">
                        <strong><?php esc_html_e( 'Opções disponíveis:', 'dps-ai' ); ?></strong><br>
                        <code>mode="inline"</code> <?php esc_html_e( 'ou', 'dps-ai' ); ?> <code>mode="floating"</code> - <?php esc_html_e( 'Modo de exibição', 'dps-ai' ); ?><br>
                        <code>theme="light"</code> <?php esc_html_e( 'ou', 'dps-ai' ); ?> <code>theme="dark"</code> - <?php esc_html_e( 'Tema visual', 'dps-ai' ); ?><br>
                        <code>position="bottom-right"</code> <?php esc_html_e( 'ou', 'dps-ai' ); ?> <code>position="bottom-left"</code> - <?php esc_html_e( 'Posição (modo flutuante)', 'dps-ai' ); ?><br>
                        <code>title="Seu título"</code> - <?php esc_html_e( 'Título personalizado', 'dps-ai' ); ?><br>
                        <code>show_faqs="true"</code> <?php esc_html_e( 'ou', 'dps-ai' ); ?> <code>show_faqs="false"</code> - <?php esc_html_e( 'Mostrar FAQs', 'dps-ai' ); ?>
                    </p>
                </div>

                <?php submit_button( __( 'Salvar Configurações', 'dps-ai' ) ); ?>
            </form>

            <hr />

            <h2><?php esc_html_e( 'Comportamento do Assistente', 'dps-ai' ); ?></h2>
            <p><?php esc_html_e( 'O assistente virtual foi configurado para responder APENAS sobre:', 'dps-ai' ); ?></p>
            <ul style="list-style-type: disc; margin-left: 20px;">
                <li><?php esc_html_e( 'Serviços de Banho e Tosa', 'dps-ai' ); ?></li>
                <li><?php esc_html_e( 'Agendamentos e histórico de atendimentos', 'dps-ai' ); ?></li>
                <li><?php esc_html_e( 'Dados do cliente e pets cadastrados', 'dps-ai' ); ?></li>
                <li><?php esc_html_e( 'Funcionalidades do Portal do Cliente (fidelidade, pagamentos, assinaturas)', 'dps-ai' ); ?></li>
                <li><?php esc_html_e( 'Cuidados gerais e básicos com pets (de forma genérica e responsável)', 'dps-ai' ); ?></li>
            </ul>
            <p><?php esc_html_e( 'Perguntas fora desse contexto (política, religião, finanças, tecnologia, etc.) serão educadamente recusadas.', 'dps-ai' ); ?></p>

            <hr />

            <h2><?php esc_html_e( 'Custos Estimados (OpenAI)', 'dps-ai' ); ?></h2>
            <?php
            // Obtém o modelo atualmente selecionado
            $selected_model = $options['model'] ?? 'gpt-4o-mini';
            ?>
            <table class="widefat" style="max-width: 700px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Modelo', 'dps-ai' ); ?></th>
                        <th><?php esc_html_e( 'Custo Aprox. por Pergunta', 'dps-ai' ); ?></th>
                        <th><?php esc_html_e( 'Recomendação', 'dps-ai' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'dps-ai' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr<?php echo ( 'gpt-4o-mini' === $selected_model ) ? ' style="background-color: #e0f2fe; border-left: 4px solid #0ea5e9;"' : ''; ?>>
                        <td><strong>GPT-4o Mini</strong></td>
                        <td>~$0.0003</td>
                        <td><strong><?php esc_html_e( 'Recomendado', 'dps-ai' ); ?></strong></td>
                        <td>
                            <?php if ( 'gpt-4o-mini' === $selected_model ) : ?>
                                <span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; background: #0ea5e9; color: #fff; border-radius: 3px; font-size: 11px; font-weight: 600;">
                                    <span class="dashicons dashicons-yes-alt" style="font-size: 14px; width: 14px; height: 14px; line-height: 14px;"></span>
                                    <?php esc_html_e( 'Modelo Ativo', 'dps-ai' ); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr<?php echo ( 'gpt-4o' === $selected_model ) ? ' style="background-color: #e0f2fe; border-left: 4px solid #0ea5e9;"' : ''; ?>>
                        <td><strong>GPT-4o</strong></td>
                        <td>~$0.005</td>
                        <td><?php esc_html_e( 'Alta precisão', 'dps-ai' ); ?></td>
                        <td>
                            <?php if ( 'gpt-4o' === $selected_model ) : ?>
                                <span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; background: #0ea5e9; color: #fff; border-radius: 3px; font-size: 11px; font-weight: 600;">
                                    <span class="dashicons dashicons-yes-alt" style="font-size: 14px; width: 14px; height: 14px; line-height: 14px;"></span>
                                    <?php esc_html_e( 'Modelo Ativo', 'dps-ai' ); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr<?php echo ( 'gpt-4-turbo' === $selected_model ) ? ' style="background-color: #e0f2fe; border-left: 4px solid #0ea5e9;"' : ''; ?>>
                        <td><strong>GPT-4 Turbo</strong></td>
                        <td>~$0.01</td>
                        <td><?php esc_html_e( 'Máxima precisão', 'dps-ai' ); ?></td>
                        <td>
                            <?php if ( 'gpt-4-turbo' === $selected_model ) : ?>
                                <span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; background: #0ea5e9; color: #fff; border-radius: 3px; font-size: 11px; font-weight: 600;">
                                    <span class="dashicons dashicons-yes-alt" style="font-size: 14px; width: 14px; height: 14px; line-height: 14px;"></span>
                                    <?php esc_html_e( 'Modelo Ativo', 'dps-ai' ); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr<?php echo ( 'gpt-3.5-turbo' === $selected_model ) ? ' style="background-color: #e0f2fe; border-left: 4px solid #0ea5e9;"' : ''; ?>>
                        <td><strong>GPT-3.5 Turbo</strong></td>
                        <td>~$0.001</td>
                        <td><?php esc_html_e( 'Legado', 'dps-ai' ); ?></td>
                        <td>
                            <?php if ( 'gpt-3.5-turbo' === $selected_model ) : ?>
                                <span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; background: #0ea5e9; color: #fff; border-radius: 3px; font-size: 11px; font-weight: 600;">
                                    <span class="dashicons dashicons-yes-alt" style="font-size: 14px; width: 14px; height: 14px; line-height: 14px;"></span>
                                    <?php esc_html_e( 'Modelo Ativo', 'dps-ai' ); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="description"><?php esc_html_e( 'Valores estimados baseados em ~1000 tokens por pergunta. Consulte a documentação da OpenAI para preços atualizados.', 'dps-ai' ); ?></p>
        </div>

        <script>
        (function($) {
            // Toggle API Key visibility
            $('#dps_ai_toggle_api_key').on('click', function(e) {
                e.preventDefault();
                
                var $input = $('#dps_ai_api_key');
                var $icon = $(this).find('.dashicons');
                
                if ($input.attr('type') === 'password') {
                    $input.attr('type', 'text');
                    $icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
                    $(this).attr('title', '<?php echo esc_js( __( 'Ocultar API Key', 'dps-ai' ) ); ?>');
                } else {
                    $input.attr('type', 'password');
                    $icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
                    $(this).attr('title', '<?php echo esc_js( __( 'Mostrar API Key', 'dps-ai' ) ); ?>');
                }
            });

            // Test connection button
            $('#dps_ai_test_connection').on('click', function(e) {
                e.preventDefault();
                
                var $button = $(this);
                var $result = $('#dps_ai_test_result');
                var originalText = $button.text();
                
                $button.prop('disabled', true).text('<?php echo esc_js( __( 'Testando...', 'dps-ai' ) ); ?>');
                $result.hide();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dps_ai_test_connection',
                        nonce: '<?php echo esc_js( wp_create_nonce( 'dps_ai_test_nonce' ) ); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.html('<span style="color: #10b981;">✓ ' + response.data.message + '</span>').show();
                        } else {
                            $result.html('<span style="color: #ef4444;">✗ ' + response.data.message + '</span>').show();
                        }
                    },
                    error: function() {
                        $result.html('<span style="color: #ef4444;">✗ <?php echo esc_js( __( 'Erro de rede ao testar conexão.', 'dps-ai' ) ); ?></span>').show();
                    },
                    complete: function() {
                        $button.prop('disabled', false).text(originalText);
                    }
                });
            });

            $('#dps_ai_manual_cleanup').on('click', function(e) {
                e.preventDefault();
                
                if (!confirm('<?php echo esc_js( __( 'Tem certeza que deseja executar a limpeza de dados antigos? Esta ação não pode ser desfeita.', 'dps-ai' ) ); ?>')) {
                    return;
                }
                
                var $button = $(this);
                var $result = $('#dps_ai_cleanup_result');
                var originalText = $button.text();
                
                $button.prop('disabled', true).text('<?php echo esc_js( __( 'Limpando...', 'dps-ai' ) ); ?>');
                $result.hide();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dps_ai_manual_cleanup',
                        nonce: '<?php echo esc_js( wp_create_nonce( 'dps_ai_manual_cleanup' ) ); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.html('<span style="color: #10b981;">✓ ' + response.data.message + '</span>').show();
                            // Recarrega a página após 2 segundos para atualizar estatísticas
                            setTimeout(function() {
                                window.location.reload();
                            }, 2000);
                        } else {
                            $result.html('<span style="color: #ef4444;">✗ ' + response.data.message + '</span>').show();
                        }
                    },
                    error: function() {
                        $result.html('<span style="color: #ef4444;">✗ <?php echo esc_js( __( 'Erro de rede ao executar limpeza.', 'dps-ai' ) ); ?></span>').show();
                    },
                    complete: function() {
                        $button.prop('disabled', false).text(originalText);
                    }
                });
            });
        })(jQuery);
        </script>
        <?php
    }

    /**
     * Renderiza a página de Analytics.
     */
    public function render_analytics_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'dps-ai' ) );
        }

        // Obtém período dos parâmetros GET ou usa últimos 30 dias
        $start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : gmdate( 'Y-m-d', strtotime( '-30 days' ) );
        $end_date   = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : current_time( 'Y-m-d' );

        // Obtém estatísticas
        $stats = DPS_AI_Analytics::get_stats( $start_date, $end_date );

        // Calcula custo estimado
        $settings = get_option( self::OPTION_KEY, [] );
        $model    = $settings['model'] ?? 'gpt-4o-mini';
        $cost     = DPS_AI_Analytics::estimate_cost(
            $stats['summary']['total_tokens_input'],
            $stats['summary']['total_tokens_output'],
            $model
        );

        // Obtém feedback recente com paginação
        $per_page = 20; // Número de feedbacks por página
        $feedback_paged = isset( $_GET['feedback_paged'] ) ? max( 1, absint( $_GET['feedback_paged'] ) ) : 1;
        $feedback_offset = ( $feedback_paged - 1 ) * $per_page;
        $recent_feedback = DPS_AI_Analytics::get_recent_feedback( $per_page, 'all', $feedback_offset );
        $total_feedback = DPS_AI_Analytics::count_feedback( 'all' );
        $total_pages = ceil( $total_feedback / $per_page );

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Analytics de IA', 'dps-ai' ); ?></h1>

            <!-- Aviso de taxa de conversão -->
            <?php if ( ! empty( $settings['usd_to_brl_rate'] ) ) : ?>
                <div style="background: #dbeafe; padding: 12px 16px; border-radius: 6px; border-left: 4px solid #0ea5e9; margin-bottom: 20px;">
                    <p style="margin: 0; color: #0c4a6e; font-size: 13px;">
                        <strong><?php esc_html_e( 'Taxa de conversão:', 'dps-ai' ); ?></strong>
                        <?php 
                        printf(
                            /* translators: %s: taxa de conversão */
                            esc_html__( '1 USD = %s BRL', 'dps-ai' ),
                            '<strong>' . esc_html( number_format( floatval( $settings['usd_to_brl_rate'] ), 2, ',', '.' ) ) . '</strong>'
                        );
                        ?>
                        &nbsp;•&nbsp;
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=dps-ai-settings' ) ); ?>" style="color: #0ea5e9; text-decoration: none;">
                            <?php esc_html_e( 'Alterar nas configurações', 'dps-ai' ); ?>
                        </a>
                    </p>
                </div>
            <?php elseif ( $cost > 0 ) : ?>
                <div style="background: #fef3c7; padding: 12px 16px; border-radius: 6px; border-left: 4px solid #f59e0b; margin-bottom: 20px;">
                    <p style="margin: 0; color: #78350f; font-size: 13px;">
                        <strong><?php esc_html_e( 'Dica:', 'dps-ai' ); ?></strong>
                        <?php esc_html_e( 'Configure a taxa de conversão USD → BRL nas configurações para ver custos em reais.', 'dps-ai' ); ?>
                        &nbsp;
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=dps-ai-settings' ) ); ?>" style="color: #f59e0b; text-decoration: none;">
                            <?php esc_html_e( 'Configurar agora', 'dps-ai' ); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Filtro de período -->
            <form method="get" style="margin-bottom: 20px;">
                <input type="hidden" name="page" value="dps-ai-analytics" />
                <label>
                    <?php esc_html_e( 'De:', 'dps-ai' ); ?>
                    <input type="date" name="start_date" value="<?php echo esc_attr( $start_date ); ?>" />
                </label>
                <label style="margin-left: 10px;">
                    <?php esc_html_e( 'Até:', 'dps-ai' ); ?>
                    <input type="date" name="end_date" value="<?php echo esc_attr( $end_date ); ?>" />
                </label>
                <button type="submit" class="button" style="margin-left: 10px;"><?php esc_html_e( 'Filtrar', 'dps-ai' ); ?></button>
            </form>

            <!-- Botão de exportação CSV -->
            <div style="margin-bottom: 20px;">
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
                    <input type="hidden" name="action" value="dps_ai_export_metrics" />
                    <input type="hidden" name="start_date" value="<?php echo esc_attr( $start_date ); ?>" />
                    <input type="hidden" name="end_date" value="<?php echo esc_attr( $end_date ); ?>" />
                    <?php wp_nonce_field( 'dps_ai_export_metrics', 'dps_ai_export_metrics_nonce' ); ?>
                    <button type="submit" class="button button-secondary">
                        <span class="dashicons dashicons-chart-bar" style="vertical-align: middle; margin-right: 5px;"></span>
                        <?php esc_html_e( 'Exportar CSV', 'dps-ai' ); ?>
                    </button>
                </form>
            </div>

            <!-- Cards de resumo -->
            <div style="display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 30px;">
                <div style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb; flex: 1; min-width: 200px;">
                    <h3 style="margin: 0 0 10px 0; color: #6b7280; font-size: 14px;"><?php esc_html_e( 'Total de Perguntas', 'dps-ai' ); ?></h3>
                    <p style="margin: 0; font-size: 32px; font-weight: 700; color: #0ea5e9;"><?php echo esc_html( number_format( $stats['summary']['total_questions'] ) ); ?></p>
                </div>
                <div style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb; flex: 1; min-width: 200px;">
                    <h3 style="margin: 0 0 10px 0; color: #6b7280; font-size: 14px;"><?php esc_html_e( 'Clientes Únicos', 'dps-ai' ); ?></h3>
                    <p style="margin: 0; font-size: 32px; font-weight: 700; color: #10b981;"><?php echo esc_html( number_format( $stats['summary']['unique_clients'] ) ); ?></p>
                </div>
                <div style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb; flex: 1; min-width: 200px;">
                    <h3 style="margin: 0 0 10px 0; color: #6b7280; font-size: 14px;"><?php esc_html_e( 'Tokens Consumidos', 'dps-ai' ); ?></h3>
                    <p style="margin: 0; font-size: 32px; font-weight: 700; color: #f59e0b;">
                        <?php echo esc_html( number_format( $stats['summary']['total_tokens_input'] + $stats['summary']['total_tokens_output'] ) ); ?>
                    </p>
                </div>
                <div style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb; flex: 1; min-width: 200px;">
                    <h3 style="margin: 0 0 10px 0; color: #6b7280; font-size: 14px;"><?php esc_html_e( 'Custo Estimado', 'dps-ai' ); ?></h3>
                    <p style="margin: 0; font-size: 32px; font-weight: 700; color: #ef4444;">$<?php echo esc_html( number_format( $cost, 4 ) ); ?></p>
                    <?php
                    // Exibe valor em BRL se taxa configurada
                    if ( ! empty( $settings['usd_to_brl_rate'] ) ) {
                        $cost_brl = $cost * floatval( $settings['usd_to_brl_rate'] );
                        ?>
                        <p style="margin: 5px 0 0 0; font-size: 16px; color: #6b7280;">
                            (~R$ <?php echo esc_html( number_format( $cost_brl, 2, ',', '.' ) ); ?>)
                        </p>
                    <?php } ?>
                </div>
            </div>

            <!-- Segunda linha de cards -->
            <div style="display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 30px;">
                <div style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb; flex: 1; min-width: 200px;">
                    <h3 style="margin: 0 0 10px 0; color: #6b7280; font-size: 14px;"><?php esc_html_e( 'Tempo Médio de Resposta', 'dps-ai' ); ?></h3>
                    <p style="margin: 0; font-size: 32px; font-weight: 700; color: #8b5cf6;"><?php echo esc_html( $stats['summary']['avg_response_time'] ); ?>s</p>
                </div>
                <div style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb; flex: 1; min-width: 200px;">
                    <h3 style="margin: 0 0 10px 0; color: #6b7280; font-size: 14px;"><?php esc_html_e( 'Erros', 'dps-ai' ); ?></h3>
                    <p style="margin: 0; font-size: 32px; font-weight: 700; color: #ef4444;"><?php echo esc_html( number_format( $stats['summary']['total_errors'] ) ); ?></p>
                </div>
                <div style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb; flex: 1; min-width: 200px;">
                    <h3 style="margin: 0 0 10px 0; color: #6b7280; font-size: 14px;"><?php esc_html_e( 'Feedback Positivo', 'dps-ai' ); ?></h3>
                    <p style="margin: 0; font-size: 32px; font-weight: 700; color: #10b981;">👍 <?php echo esc_html( number_format( $stats['summary']['positive_feedback'] ) ); ?></p>
                </div>
                <div style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb; flex: 1; min-width: 200px;">
                    <h3 style="margin: 0 0 10px 0; color: #6b7280; font-size: 14px;"><?php esc_html_e( 'Feedback Negativo', 'dps-ai' ); ?></h3>
                    <p style="margin: 0; font-size: 32px; font-weight: 700; color: #ef4444;">👎 <?php echo esc_html( number_format( $stats['summary']['negative_feedback'] ) ); ?></p>
                </div>
            </div>

            <!-- Gráficos com Chart.js -->
            <?php if ( ! empty( $stats['daily'] ) ) : ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin-bottom: 30px;">
                    <!-- Gráfico de Uso de Tokens -->
                    <div style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb;">
                        <h3 style="margin: 0 0 15px 0; color: #374151; font-size: 16px;"><?php esc_html_e( 'Uso de Tokens ao Longo do Tempo', 'dps-ai' ); ?></h3>
                        <canvas id="tokensChart" style="max-height: 300px;"></canvas>
                    </div>

                    <!-- Gráfico de Requisições -->
                    <div style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb;">
                        <h3 style="margin: 0 0 15px 0; color: #374151; font-size: 16px;"><?php esc_html_e( 'Requisições por Dia', 'dps-ai' ); ?></h3>
                        <canvas id="requestsChart" style="max-height: 300px;"></canvas>
                    </div>
                </div>

                <!-- Gráfico de Custo Acumulado -->
                <div style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb; margin-bottom: 30px;">
                    <h3 style="margin: 0 0 15px 0; color: #374151; font-size: 16px;">
                        <?php esc_html_e( 'Custo Acumulado no Período', 'dps-ai' ); ?>
                        <?php if ( ! empty( $settings['usd_to_brl_rate'] ) ) : ?>
                            <span style="font-size: 13px; color: #6b7280; font-weight: normal;">
                                (<?php esc_html_e( 'USD e BRL', 'dps-ai' ); ?>)
                            </span>
                        <?php endif; ?>
                    </h3>
                    <canvas id="costChart" style="max-height: 300px;"></canvas>
                </div>
            <?php endif; ?>

            <!-- Feedback Recente -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 style="margin: 0;"><?php esc_html_e( 'Feedback Recente', 'dps-ai' ); ?></h2>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 0;">
                    <input type="hidden" name="action" value="dps_ai_export_feedback" />
                    <?php wp_nonce_field( 'dps_ai_export_feedback', 'dps_ai_export_feedback_nonce' ); ?>
                    <button type="submit" class="button button-secondary">
                        <span class="dashicons dashicons-format-chat" style="vertical-align: middle; margin-right: 5px;"></span>
                        <?php esc_html_e( 'Exportar Feedbacks CSV', 'dps-ai' ); ?>
                    </button>
                </form>
            </div>
            <?php if ( empty( $recent_feedback ) ) : ?>
                <p><?php esc_html_e( 'Nenhum feedback registrado ainda.', 'dps-ai' ); ?></p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Data', 'dps-ai' ); ?></th>
                            <th><?php esc_html_e( 'Pergunta', 'dps-ai' ); ?></th>
                            <th><?php esc_html_e( 'Feedback', 'dps-ai' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $recent_feedback as $fb ) : ?>
                            <tr>
                                <td><?php echo esc_html( gmdate( 'd/m/Y H:i', strtotime( $fb->created_at ) ) ); ?></td>
                                <td><?php echo esc_html( mb_substr( $fb->question, 0, 100 ) ); ?><?php echo mb_strlen( $fb->question ) > 100 ? '...' : ''; ?></td>
                                <td>
                                    <?php if ( 'positive' === $fb->feedback ) : ?>
                                        <span style="color: #10b981;">👍 <?php esc_html_e( 'Positivo', 'dps-ai' ); ?></span>
                                    <?php else : ?>
                                        <span style="color: #ef4444;">👎 <?php esc_html_e( 'Negativo', 'dps-ai' ); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Paginação -->
                <?php if ( $total_pages > 1 ) : ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <span class="displaying-num">
                                <?php
                                printf(
                                    /* translators: %s: número total de feedbacks */
                                    esc_html( _n( '%s item', '%s itens', $total_feedback, 'dps-ai' ) ),
                                    '<strong>' . number_format_i18n( $total_feedback ) . '</strong>'
                                );
                                ?>
                            </span>
                            <span class="pagination-links">
                                <?php
                                $base_url = add_query_arg(
                                    array(
                                        'page'       => 'dps-ai-analytics',
                                        'start_date' => $start_date,
                                        'end_date'   => $end_date,
                                    ),
                                    admin_url( 'admin.php' )
                                );

                                // Botão "Primeira página"
                                if ( $feedback_paged > 1 ) {
                                    printf(
                                        '<a class="first-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a> ',
                                        esc_url( add_query_arg( 'feedback_paged', 1, $base_url ) ),
                                        esc_html__( 'Primeira página', 'dps-ai' ),
                                        '&laquo;'
                                    );
                                } else {
                                    printf(
                                        '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">%s</span> ',
                                        '&laquo;'
                                    );
                                }

                                // Botão "Página anterior"
                                if ( $feedback_paged > 1 ) {
                                    printf(
                                        '<a class="prev-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a> ',
                                        esc_url( add_query_arg( 'feedback_paged', $feedback_paged - 1, $base_url ) ),
                                        esc_html__( 'Página anterior', 'dps-ai' ),
                                        '&lsaquo;'
                                    );
                                } else {
                                    printf(
                                        '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">%s</span> ',
                                        '&lsaquo;'
                                    );
                                }

                                // Números de página
                                printf(
                                    '<span class="paging-input"><label for="current-page-selector-feedback" class="screen-reader-text">%s</label><input class="current-page" id="current-page-selector-feedback" type="text" name="paged" value="%s" size="3" aria-describedby="table-paging-feedback" /><span class="tablenav-paging-text"> %s <span class="total-pages">%s</span></span></span> ',
                                    esc_html__( 'Página atual', 'dps-ai' ),
                                    esc_attr( $feedback_paged ),
                                    esc_html__( 'de', 'dps-ai' ),
                                    number_format_i18n( $total_pages )
                                );

                                // Botão "Próxima página"
                                if ( $feedback_paged < $total_pages ) {
                                    printf(
                                        '<a class="next-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a> ',
                                        esc_url( add_query_arg( 'feedback_paged', $feedback_paged + 1, $base_url ) ),
                                        esc_html__( 'Próxima página', 'dps-ai' ),
                                        '&rsaquo;'
                                    );
                                } else {
                                    printf(
                                        '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">%s</span> ',
                                        '&rsaquo;'
                                    );
                                }

                                // Botão "Última página"
                                if ( $feedback_paged < $total_pages ) {
                                    printf(
                                        '<a class="last-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a>',
                                        esc_url( add_query_arg( 'feedback_paged', $total_pages, $base_url ) ),
                                        esc_html__( 'Última página', 'dps-ai' ),
                                        '&raquo;'
                                    );
                                } else {
                                    printf(
                                        '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">%s</span>',
                                        '&raquo;'
                                    );
                                }
                                ?>
                            </span>
                        </div>
                    </div>

                    <!-- JavaScript para navegação por input de página -->
                    <script type="text/javascript">
                    jQuery(document).ready(function($) {
                        $('#current-page-selector-feedback').on('keypress', function(e) {
                            if (e.which === 13) { // Enter
                                e.preventDefault();
                                var page = parseInt($(this).val());
                                var maxPages = <?php echo (int) $total_pages; ?>;
                                
                                if (page >= 1 && page <= maxPages) {
                                    var url = '<?php echo esc_js( $base_url ); ?>&feedback_paged=' + page;
                                    window.location.href = url;
                                } else {
                                    alert('<?php echo esc_js( __( 'Número de página inválido.', 'dps-ai' ) ); ?>');
                                    $(this).val(<?php echo (int) $feedback_paged; ?>);
                                }
                            }
                        });
                    });
                    </script>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Uso Diário -->
            <?php if ( ! empty( $stats['daily'] ) ) : ?>
                <h2 style="margin-top: 30px;"><?php esc_html_e( 'Uso Diário', 'dps-ai' ); ?></h2>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Data', 'dps-ai' ); ?></th>
                            <th><?php esc_html_e( 'Perguntas', 'dps-ai' ); ?></th>
                            <th><?php esc_html_e( 'Tokens', 'dps-ai' ); ?></th>
                            <th><?php esc_html_e( 'Erros', 'dps-ai' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( array_reverse( $stats['daily'] ) as $day ) : ?>
                            <tr>
                                <td><?php echo esc_html( gmdate( 'd/m/Y', strtotime( $day->date ) ) ); ?></td>
                                <td><?php echo esc_html( number_format( $day->questions ) ); ?></td>
                                <td><?php echo esc_html( number_format( $day->tokens ) ); ?></td>
                                <td><?php echo esc_html( number_format( $day->errors ) ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <?php
        // Enfileira Chart.js e script de inicialização
        if ( ! empty( $stats['daily'] ) ) {
            $this->enqueue_charts_scripts( $stats, $model, $settings );
        }
        ?>
        <?php
    }

    /**
     * Enfileira Chart.js e inicializa gráficos do analytics.
     *
     * @param array  $stats    Estatísticas do analytics.
     * @param string $model    Modelo GPT usado.
     * @param array  $settings Configurações do plugin.
     */
    private function enqueue_charts_scripts( $stats, $model, $settings ) {
        // Enfileira Chart.js via CDN
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
            [],
            '4.4.0',
            true
        );

        // Prepara dados para os gráficos
        $labels = [];
        $tokens_data = [];
        $requests_data = [];
        $cost_usd_data = [];
        $cost_brl_data = [];
        $cumulative_cost_usd = 0;
        $cumulative_cost_brl = 0;

        foreach ( $stats['daily'] as $day ) {
            $labels[] = gmdate( 'd/m', strtotime( $day->date ) );
            $tokens_data[] = intval( $day->tokens );
            $requests_data[] = intval( $day->questions );
            
            // Calcula custo do dia
            $daily_cost = DPS_AI_Analytics::estimate_cost(
                intval( $day->tokens ) / 2, // Aproximação: metade input
                intval( $day->tokens ) / 2, // Aproximação: metade output
                $model
            );
            
            $cumulative_cost_usd += $daily_cost;
            $cost_usd_data[] = round( $cumulative_cost_usd, 4 );
            
            // Se tem taxa BRL, calcula também
            if ( ! empty( $settings['usd_to_brl_rate'] ) ) {
                $cumulative_cost_brl += $daily_cost * floatval( $settings['usd_to_brl_rate'] );
                $cost_brl_data[] = round( $cumulative_cost_brl, 2 );
            }
        }

        // Localiza dados para JavaScript
        wp_localize_script(
            'chartjs',
            'dpsAIChartsData',
            [
                'labels'       => $labels,
                'tokens'       => $tokens_data,
                'requests'     => $requests_data,
                'costUSD'      => $cost_usd_data,
                'costBRL'      => $cost_brl_data,
                'hasBRLRate'   => ! empty( $settings['usd_to_brl_rate'] ),
                'i18n'         => [
                    'tokens'       => __( 'Tokens', 'dps-ai' ),
                    'requests'     => __( 'Requisições', 'dps-ai' ),
                    'costUSD'      => __( 'Custo (USD)', 'dps-ai' ),
                    'costBRL'      => __( 'Custo (BRL)', 'dps-ai' ),
                ],
            ]
        );

        // Script inline para inicializar gráficos
        wp_add_inline_script(
            'chartjs',
            "
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof Chart === 'undefined' || !window.dpsAIChartsData) return;

                const data = window.dpsAIChartsData;
                
                // Configuração comum
                const commonOptions = {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                };

                // Gráfico de Tokens (Linha)
                const tokensCtx = document.getElementById('tokensChart');
                if (tokensCtx) {
                    new Chart(tokensCtx, {
                        type: 'line',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                label: data.i18n.tokens,
                                data: data.tokens,
                                borderColor: '#f59e0b',
                                backgroundColor: 'rgba(245, 158, 11, 0.1)',
                                fill: true,
                                tension: 0.4
                            }]
                        },
                        options: commonOptions
                    });
                }

                // Gráfico de Requisições (Barras)
                const requestsCtx = document.getElementById('requestsChart');
                if (requestsCtx) {
                    new Chart(requestsCtx, {
                        type: 'bar',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                label: data.i18n.requests,
                                data: data.requests,
                                backgroundColor: '#0ea5e9',
                                borderColor: '#0284c7',
                                borderWidth: 1
                            }]
                        },
                        options: commonOptions
                    });
                }

                // Gráfico de Custo Acumulado (Área)
                const costCtx = document.getElementById('costChart');
                if (costCtx) {
                    const datasets = [
                        {
                            label: data.i18n.costUSD,
                            data: data.costUSD,
                            borderColor: '#ef4444',
                            backgroundColor: 'rgba(239, 68, 68, 0.1)',
                            fill: true,
                            tension: 0.4,
                            yAxisID: 'yUSD'
                        }
                    ];

                    // Se tem taxa BRL, adiciona segunda linha
                    if (data.hasBRLRate) {
                        datasets.push({
                            label: data.i18n.costBRL,
                            data: data.costBRL,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            fill: true,
                            tension: 0.4,
                            yAxisID: 'yBRL'
                        });
                    }

                    const scales = {
                        yUSD: {
                            type: 'linear',
                            position: 'left',
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'USD ($)'
                            }
                        }
                    };

                    // Se tem BRL, adiciona eixo Y secundário
                    if (data.hasBRLRate) {
                        scales.yBRL = {
                            type: 'linear',
                            position: 'right',
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'BRL (R$)'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        };
                    }

                    new Chart(costCtx, {
                        type: 'line',
                        data: {
                            labels: data.labels,
                            datasets: datasets
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: true,
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'top'
                                }
                            },
                            scales: scales
                        }
                    });
                }
            });
            "
        );
    }

    /**
     * Handler para exportação de métricas em CSV.
     * 
     * Endpoint: admin-post.php?action=dps_ai_export_metrics
     * Segurança: Requer capability manage_options + nonce
     */
    public function handle_export_metrics() {
        // Verifica permissões
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Você não tem permissão para exportar dados.', 'dps-ai' ) );
        }

        // Verifica nonce
        if ( ! isset( $_POST['dps_ai_export_metrics_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dps_ai_export_metrics_nonce'] ) ), 'dps_ai_export_metrics' ) ) {
            wp_die( esc_html__( 'Requisição inválida (nonce).', 'dps-ai' ) );
        }

        // Obtém período
        $start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : gmdate( 'Y-m-d', strtotime( '-30 days' ) );
        $end_date   = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : current_time( 'Y-m-d' );

        // Obtém estatísticas do período
        $stats = DPS_AI_Analytics::get_stats( $start_date, $end_date );

        if ( empty( $stats['daily'] ) ) {
            wp_die( esc_html__( 'Nenhum dado disponível para exportação.', 'dps-ai' ) );
        }

        // Obtém configurações para calcular custo
        $settings       = get_option( self::OPTION_KEY, [] );
        $model          = $settings['model'] ?? 'gpt-4o-mini';
        $exchange_rate  = ! empty( $settings['usd_to_brl_rate'] ) ? floatval( $settings['usd_to_brl_rate'] ) : 0;

        // Prepara dados para CSV
        $headers = [
            'Data',
            'Perguntas',
            'Tokens Entrada',
            'Tokens Saída',
            'Total Tokens',
            'Custo USD',
        ];

        if ( $exchange_rate > 0 ) {
            $headers[] = 'Custo BRL';
        }

        $headers[] = 'Tempo Médio (s)';
        $headers[] = 'Erros';
        $headers[] = 'Modelo';

        $rows = [];
        foreach ( $stats['daily'] as $day ) {
            $total_tokens = intval( $day->tokens_input ) + intval( $day->tokens_output );
            $cost_usd     = DPS_AI_Analytics::estimate_cost( intval( $day->tokens_input ), intval( $day->tokens_output ), $model );

            $row = [
                gmdate( 'd/m/Y', strtotime( $day->date ) ),
                $day->questions_count,
                $day->tokens_input,
                $day->tokens_output,
                $total_tokens,
                '$' . number_format( $cost_usd, 4 ),
            ];

            if ( $exchange_rate > 0 ) {
                $cost_brl = $cost_usd * $exchange_rate;
                $row[]    = 'R$ ' . number_format( $cost_brl, 2, ',', '.' );
            }

            $row[] = number_format( floatval( $day->avg_response_time ), 2 );
            $row[] = $day->errors_count;
            $row[] = ! empty( $day->model ) ? $day->model : $model;

            $rows[] = $row;
        }

        // Gera e envia CSV
        $filename = 'ai-metrics-' . gmdate( 'Y-m-d' ) . '.csv';
        $this->generate_csv( $filename, $headers, $rows );
    }

    /**
     * Handler para exportação de feedbacks em CSV.
     * 
     * Endpoint: admin-post.php?action=dps_ai_export_feedback
     * Segurança: Requer capability manage_options + nonce
     */
    public function handle_export_feedback() {
        // Verifica permissões
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Você não tem permissão para exportar dados.', 'dps-ai' ) );
        }

        // Verifica nonce
        if ( ! isset( $_POST['dps_ai_export_feedback_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dps_ai_export_feedback_nonce'] ) ), 'dps_ai_export_feedback' ) ) {
            wp_die( esc_html__( 'Requisição inválida (nonce).', 'dps-ai' ) );
        }

        // Obtém feedbacks (últimos 1000)
        $feedbacks = DPS_AI_Analytics::get_recent_feedback( 1000, 'all' );

        if ( empty( $feedbacks ) ) {
            wp_die( esc_html__( 'Nenhum feedback disponível para exportação.', 'dps-ai' ) );
        }

        // Prepara dados para CSV
        $headers = [
            'Data/Hora',
            'Cliente ID',
            'Pergunta',
            'Resposta',
            'Feedback',
            'Comentário',
            'Modelo',
        ];

        $rows = [];
        foreach ( $feedbacks as $fb ) {
            $feedback_type = ( 'positive' === $fb->feedback ) ? 'Positivo' : 'Negativo';

            // Trunca resposta para CSV (max 200 caracteres)
            $answer = ! empty( $fb->answer ) ? mb_substr( $fb->answer, 0, 200 ) : '';
            if ( mb_strlen( $fb->answer ) > 200 ) {
                $answer .= '...';
            }

            $rows[] = [
                gmdate( 'd/m/Y H:i', strtotime( $fb->created_at ) ),
                $fb->client_id,
                $fb->question,
                $answer,
                $feedback_type,
                ! empty( $fb->comment ) ? $fb->comment : '',
                '', // Modelo não está disponível diretamente no feedback, deixar vazio
            ];
        }

        // Gera e envia CSV
        $filename = 'ai-feedback-' . gmdate( 'Y-m-d' ) . '.csv';
        $this->generate_csv( $filename, $headers, $rows );
    }

    /**
     * Gera arquivo CSV e envia para download.
     *
     * @param string $filename Nome do arquivo CSV.
     * @param array  $headers  Array com cabeçalhos das colunas.
     * @param array  $rows     Array bidimensional com linhas de dados.
     */
    private function generate_csv( $filename, $headers, $rows ) {
        // Limpa qualquer output anterior
        if ( ob_get_level() ) {
            ob_end_clean();
        }

        // Headers HTTP para download
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        // Abre output stream
        $output = fopen( 'php://output', 'w' );

        // UTF-8 BOM para Excel reconhecer acentos
        fprintf( $output, chr(0xEF) . chr(0xBB) . chr(0xBF) );

        // Escreve cabeçalho
        fputcsv( $output, $headers, ';' );

        // Escreve linhas de dados
        foreach ( $rows as $row ) {
            // Remove quebras de linha dos valores para evitar quebra de CSV
            $cleaned_row = array_map( function( $value ) {
                return str_replace( [ "\r", "\n" ], ' ', $value );
            }, $row );

            fputcsv( $output, $cleaned_row, ';' );
        }

        fclose( $output );
        exit;
    }

    /**
     * Processa o salvamento das configurações.
     */
    public function maybe_handle_save() {
        if ( ! isset( $_POST['dps_ai_action'] ) || 'save_settings' !== $_POST['dps_ai_action'] ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Você não tem permissão para realizar esta ação.', 'dps-ai' ) );
        }

        if ( ! isset( $_POST['dps_ai_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dps_ai_nonce'] ) ), 'dps_ai_save' ) ) {
            wp_die( esc_html__( 'Falha na verificação de segurança.', 'dps-ai' ) );
        }

        // Sanitiza todo o array POST antes de processar
        $raw_settings = isset( $_POST[ self::OPTION_KEY ] ) ? wp_unslash( $_POST[ self::OPTION_KEY ] ) : [];

        // Processa instruções adicionais com limite de 2000 caracteres
        $additional_instructions = '';
        $was_truncated = false;
        if ( ! empty( $raw_settings['additional_instructions'] ) ) {
            $additional_instructions = sanitize_textarea_field( $raw_settings['additional_instructions'] );
            $original_length = mb_strlen( $additional_instructions );
            // Limita a 2000 caracteres
            if ( $original_length > 2000 ) {
                $additional_instructions = mb_substr( $additional_instructions, 0, 2000 );
                $was_truncated = true;
            }
        }

        // Processa instruções do chat público com limite de 1000 caracteres
        $public_chat_instructions = '';
        if ( ! empty( $raw_settings['public_chat_instructions'] ) ) {
            $public_chat_instructions = sanitize_textarea_field( $raw_settings['public_chat_instructions'] );
            if ( mb_strlen( $public_chat_instructions ) > 1000 ) {
                $public_chat_instructions = mb_substr( $public_chat_instructions, 0, 1000 );
            }
        }

        // Processa informações do negócio com limite de 2000 caracteres
        $public_chat_business_info = '';
        if ( ! empty( $raw_settings['public_chat_business_info'] ) ) {
            $public_chat_business_info = sanitize_textarea_field( $raw_settings['public_chat_business_info'] );
            if ( mb_strlen( $public_chat_business_info ) > 2000 ) {
                $public_chat_business_info = mb_substr( $public_chat_business_info, 0, 2000 );
            }
        }

        $settings = [
            'enabled'                    => ! empty( $raw_settings['enabled'] ),
            'api_key'                    => isset( $raw_settings['api_key'] ) ? sanitize_text_field( $raw_settings['api_key'] ) : '',
            'model'                      => isset( $raw_settings['model'] ) ? sanitize_text_field( $raw_settings['model'] ) : 'gpt-4o-mini',
            'temperature'                => isset( $raw_settings['temperature'] ) ? floatval( $raw_settings['temperature'] ) : 0.4,
            'timeout'                    => isset( $raw_settings['timeout'] ) ? absint( $raw_settings['timeout'] ) : 10,
            'max_tokens'                 => isset( $raw_settings['max_tokens'] ) ? absint( $raw_settings['max_tokens'] ) : 500,
            'additional_instructions'    => $additional_instructions,
            // v1.5.0 settings
            'widget_mode'                => isset( $raw_settings['widget_mode'] ) ? sanitize_text_field( $raw_settings['widget_mode'] ) : 'inline',
            'floating_position'          => isset( $raw_settings['floating_position'] ) ? sanitize_text_field( $raw_settings['floating_position'] ) : 'bottom-right',
            'scheduling_mode'            => isset( $raw_settings['scheduling_mode'] ) ? sanitize_text_field( $raw_settings['scheduling_mode'] ) : 'disabled',
            'language'                   => isset( $raw_settings['language'] ) ? sanitize_text_field( $raw_settings['language'] ) : 'pt_BR',
            'faq_suggestions'            => isset( $raw_settings['faq_suggestions'] ) ? sanitize_textarea_field( $raw_settings['faq_suggestions'] ) : '',
            'enable_feedback'            => ! empty( $raw_settings['enable_feedback'] ),
            'enable_analytics'           => isset( $raw_settings['enable_analytics'] ) ? ! empty( $raw_settings['enable_analytics'] ) : true,
            // v1.6.0 settings - Chat Público
            'public_chat_enabled'        => ! empty( $raw_settings['public_chat_enabled'] ),
            'public_chat_faqs'           => isset( $raw_settings['public_chat_faqs'] ) ? sanitize_textarea_field( $raw_settings['public_chat_faqs'] ) : '',
            'public_chat_business_info'  => $public_chat_business_info,
            'public_chat_instructions'   => $public_chat_instructions,
            // v1.6.1 settings - Manutenção, Logs e Analytics
            'data_retention_days'        => isset( $raw_settings['data_retention_days'] ) ? max( 30, min( 3650, absint( $raw_settings['data_retention_days'] ) ) ) : 365,
            'debug_logging'              => ! empty( $raw_settings['debug_logging'] ),
            'usd_to_brl_rate'            => isset( $raw_settings['usd_to_brl_rate'] ) && ! empty( $raw_settings['usd_to_brl_rate'] ) ? max( 0.01, min( 100, floatval( $raw_settings['usd_to_brl_rate'] ) ) ) : '',
        ];

        update_option( self::OPTION_KEY, $settings );

        $redirect_args = [ 'updated' => '1' ];
        if ( $was_truncated ) {
            $redirect_args['truncated'] = '1';
        }

        wp_safe_redirect( add_query_arg( $redirect_args, wp_get_referer() ) );
        exit;
    }

    /**
     * Handler AJAX para sugestão de mensagem de WhatsApp.
     *
     * Espera $_POST com:
     * - action: 'dps_ai_suggest_whatsapp_message'
     * - nonce: 'dps_ai_comm_nonce'
     * - context: array com dados da mensagem (type, client_name, pet_name, etc.)
     */
    public function ajax_suggest_whatsapp_message() {
        // Verifica nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'dps_ai_comm_nonce' ) ) {
            wp_send_json_error( [
                'message' => __( 'Falha na verificação de segurança.', 'dps-ai' ),
            ] );
        }

        // Verifica permissão usando capability específica com fallback
        if ( ! $this->user_can_use_ai() ) {
            wp_send_json_error( [
                'message' => __( 'Você não tem permissão para usar esta funcionalidade.', 'dps-ai' ),
            ] );
        }

        // Obtém e sanitiza contexto
        $raw_context = isset( $_POST['context'] ) ? wp_unslash( $_POST['context'] ) : [];
        $context     = $this->sanitize_message_context( $raw_context );

        if ( empty( $context ) || empty( $context['type'] ) ) {
            wp_send_json_error( [
                'message' => __( 'Contexto inválido ou incompleto.', 'dps-ai' ),
            ] );
        }

        // Chama o assistente de mensagens
        $result = DPS_AI_Message_Assistant::suggest_whatsapp_message( $context );

        if ( null === $result ) {
            wp_send_json_error( [
                'message' => __( 'Não foi possível gerar sugestão automática. A IA pode estar desativada ou houve um erro na API. Escreva a mensagem manualmente.', 'dps-ai' ),
            ] );
        }

        // Retorna sucesso com o texto sugerido
        wp_send_json_success( [
            'text' => $result['text'],
        ] );
    }

    /**
     * Handler AJAX para sugestão de e-mail.
     *
     * Espera $_POST com:
     * - action: 'dps_ai_suggest_email_message'
     * - nonce: 'dps_ai_comm_nonce'
     * - context: array com dados da mensagem
     */
    public function ajax_suggest_email_message() {
        // Verifica nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'dps_ai_comm_nonce' ) ) {
            wp_send_json_error( [
                'message' => __( 'Falha na verificação de segurança.', 'dps-ai' ),
            ] );
        }

        // Verifica permissão usando capability específica com fallback
        if ( ! $this->user_can_use_ai() ) {
            wp_send_json_error( [
                'message' => __( 'Você não tem permissão para usar esta funcionalidade.', 'dps-ai' ),
            ] );
        }

        // Obtém e sanitiza contexto
        $raw_context = isset( $_POST['context'] ) ? wp_unslash( $_POST['context'] ) : [];
        $context     = $this->sanitize_message_context( $raw_context );

        if ( empty( $context ) || empty( $context['type'] ) ) {
            wp_send_json_error( [
                'message' => __( 'Contexto inválido ou incompleto.', 'dps-ai' ),
            ] );
        }

        // Chama o assistente de mensagens
        $result = DPS_AI_Message_Assistant::suggest_email_message( $context );

        if ( null === $result ) {
            wp_send_json_error( [
                'message' => __( 'Não foi possível gerar sugestão de e-mail automaticamente. A IA pode estar desativada ou houve um erro na API. Edite ou escreva o texto manualmente.', 'dps-ai' ),
            ] );
        }

        // Retorna sucesso com assunto e corpo
        wp_send_json_success( [
            'subject' => $result['subject'],
            'body'    => $result['body'],
        ] );
    }

    /**
     * Sanitiza o contexto de mensagem recebido via AJAX.
     *
     * @param array $raw_context Contexto não sanitizado.
     *
     * @return array Contexto sanitizado.
     */
    private function sanitize_message_context( $raw_context ) {
        if ( ! is_array( $raw_context ) ) {
            return [];
        }

        $context = [];

        // Campos de texto simples
        $text_fields = [
            'type',
            'client_name',
            'client_phone',
            'pet_name',
            'appointment_date',
            'appointment_time',
            'groomer_name',
            'amount',
            'additional_info',
        ];

        foreach ( $text_fields as $field ) {
            if ( isset( $raw_context[ $field ] ) ) {
                $context[ $field ] = sanitize_text_field( $raw_context[ $field ] );
            }
        }

        // Campo services é array
        if ( isset( $raw_context['services'] ) && is_array( $raw_context['services'] ) ) {
            $context['services'] = array_map( 'sanitize_text_field', $raw_context['services'] );
        }

        return $context;
    }

    /**
     * Handler AJAX para testar conexão com a API da OpenAI.
     *
     * Verifica se a API key está configurada e testa a conexão.
     */
    public function ajax_test_connection() {
        // Verifica nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'dps_ai_test_nonce' ) ) {
            wp_send_json_error( [
                'message' => __( 'Falha na verificação de segurança.', 'dps-ai' ),
            ] );
        }

        // Verifica permissão
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [
                'message' => __( 'Você não tem permissão para realizar esta ação.', 'dps-ai' ),
            ] );
        }

        // Testa conexão
        $result = DPS_AI_Client::test_connection();

        if ( $result['success'] ) {
            wp_send_json_success( [
                'message' => $result['message'],
            ] );
        } else {
            wp_send_json_error( [
                'message' => $result['message'],
            ] );
        }
    }

    /**
     * Verifica se o usuário atual pode usar o assistente de IA.
     *
     * Usa a capability específica DPS_AI_CAPABILITY com fallback para
     * 'edit_posts' para retrocompatibilidade.
     *
     * @return bool True se o usuário pode usar a IA, false caso contrário.
     */
    private function user_can_use_ai() {
        // Verifica capability específica primeiro
        if ( current_user_can( DPS_AI_CAPABILITY ) ) {
            return true;
        }

        // Fallback: admin sempre pode
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        // Fallback para retrocompatibilidade: quem pode editar posts
        // Isso será removido em versão futura após migração completa
        if ( current_user_can( 'edit_posts' ) ) {
            return true;
        }

        return false;
    }

    /**
     * Enfileira assets admin (JavaScript e CSS).
     *
     * Carrega apenas em páginas relevantes do DPS onde os botões de sugestão
     * de IA podem ser utilizados (agenda, clientes, pets, configurações).
     *
     * @param string $hook Hook da página atual.
     */
    public function enqueue_admin_assets( $hook ) {
        // Verifica se estamos em uma página relevante do DPS
        $screen = get_current_screen();
        if ( ! $screen ) {
            return;
        }

        // Lista de post types e páginas do DPS onde os assets são necessários
        $dps_post_types = [
            'dps_agendamento',
            'dps_cliente',
            'dps_pet',
            'dps_servico',
        ];

        $dps_pages = [
            'toplevel_page_desi-pet-shower',
            'desi-pet-shower_page_dps-ai-settings',
        ];

        // Verifica se é um post type do DPS (comparação estrita)
        $is_dps_post_type = in_array( $screen->post_type, $dps_post_types, true );

        // Verifica se é uma página administrativa do DPS
        // Usa comparação estrita para páginas conhecidas e strpos para submenus dinâmicos
        // pois add-ons podem registrar submenus com slugs como 'desi-pet-shower_page_dps-*'
        $is_dps_page = in_array( $hook, $dps_pages, true );
        if ( ! $is_dps_page && is_string( $hook ) ) {
            $is_dps_page = strpos( $hook, 'desi-pet-shower' ) !== false;
        }

        if ( ! $is_dps_post_type && ! $is_dps_page ) {
            return;
        }

        // Enfileira CSS
        wp_enqueue_style(
            'dps-ai-communications',
            DPS_AI_ADDON_URL . 'assets/css/dps-ai-communications.css',
            [],
            DPS_AI_VERSION
        );

        // Enfileira JavaScript
        wp_enqueue_script(
            'dps-ai-communications',
            DPS_AI_ADDON_URL . 'assets/js/dps-ai-communications.js',
            [ 'jquery' ],
            DPS_AI_VERSION,
            true
        );

        // Passa dados para JavaScript
        wp_localize_script(
            'dps-ai-communications',
            'dpsAiComm',
            [
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'dps_ai_comm_nonce' ),
                'i18n'    => [
                    'generating'        => __( 'Gerando sugestão...', 'dps-ai' ),
                    'error'             => __( 'Erro ao gerar sugestão', 'dps-ai' ),
                    'insert'            => __( 'Inserir', 'dps-ai' ),
                    'cancel'            => __( 'Cancelar', 'dps-ai' ),
                    'emailPreviewTitle' => __( 'Pré-visualização do E-mail', 'dps-ai' ),
                    'subject'           => __( 'Assunto', 'dps-ai' ),
                    'body'              => __( 'Mensagem', 'dps-ai' ),
                ],
            ]
        );
    }
}

/**
 * Inicializa o AI Add-on após o hook 'init' para garantir que o text domain seja carregado primeiro.
 * Usa prioridade 5 para rodar após o carregamento do text domain (prioridade 1) mas antes
 * de outros registros (prioridade 10).
 */
function dps_ai_init_addon() {
    if ( class_exists( 'DPS_AI_Addon' ) ) {
        DPS_AI_Addon::get_instance();
    }
}
add_action( 'init', 'dps_ai_init_addon', 5 );
