<?php
/**
 * Plugin Name:       Desi Pet Shower – AI Add-on
 * Plugin URI:        https://probst.pro/desi-pet-shower
 * Description:       Assistente virtual focado em Banho e Tosa para o Portal do Cliente do Desi Pet Shower. Responde perguntas sobre agendamentos, serviços, histórico e funcionalidades do sistema usando OpenAI.
 * Version:           1.1.0
 * Author:            PRObst
 * Author URI:        https://probst.pro
 * Text Domain:       dps-ai
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
 */

// Bloqueia acesso direto aos arquivos.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define constantes úteis do add-on.
if ( ! defined( 'DPS_AI_ADDON_DIR' ) ) {
    define( 'DPS_AI_ADDON_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'DPS_AI_ADDON_URL' ) ) {
    define( 'DPS_AI_ADDON_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'DPS_AI_VERSION' ) ) {
    define( 'DPS_AI_VERSION', '1.1.0' );
}

// Inclui as classes principais.
require_once DPS_AI_ADDON_DIR . 'includes/class-dps-ai-client.php';
require_once DPS_AI_ADDON_DIR . 'includes/class-dps-ai-assistant.php';
require_once DPS_AI_ADDON_DIR . 'includes/class-dps-ai-integration-portal.php';

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
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );

        // Processa salvamento de configurações
        add_action( 'init', [ $this, 'maybe_handle_save' ] );

        // Inicializa integração com Portal do Cliente
        add_action( 'plugins_loaded', [ $this, 'init_portal_integration' ], 20 );
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
                                <input type="password" id="dps_ai_api_key" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[api_key]" value="<?php echo esc_attr( $options['api_key'] ?? '' ); ?>" class="regular-text" />
                                <p class="description"><?php esc_html_e( 'Token de autenticação da API da OpenAI (sk-...). Mantenha em segredo.', 'dps-ai' ); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="dps_ai_model"><?php echo esc_html__( 'Modelo GPT', 'dps-ai' ); ?></label>
                            </th>
                            <td>
                                <select id="dps_ai_model" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[model]">
                                    <option value="gpt-3.5-turbo" <?php selected( $options['model'] ?? 'gpt-3.5-turbo', 'gpt-3.5-turbo' ); ?>>GPT-3.5 Turbo (Mais rápido e econômico)</option>
                                    <option value="gpt-4" <?php selected( $options['model'] ?? '', 'gpt-4' ); ?>>GPT-4 (Mais preciso, mais caro)</option>
                                    <option value="gpt-4-turbo-preview" <?php selected( $options['model'] ?? '', 'gpt-4-turbo-preview' ); ?>>GPT-4 Turbo (Balanceado)</option>
                                </select>
                                <p class="description"><?php esc_html_e( 'Modelo de linguagem a ser utilizado. GPT-3.5 é recomendado para custo/benefício.', 'dps-ai' ); ?></p>
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
                                    <?php esc_html_e( 'Use este campo para adicionar regras específicas de como a IA deve se comunicar com os clientes, dentro do contexto de Banho e Tosa e do Desi Pet Shower.', 'dps-ai' ); ?>
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
        </div>
        <?php
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

        $settings = [
            'enabled'                 => ! empty( $raw_settings['enabled'] ),
            'api_key'                 => isset( $raw_settings['api_key'] ) ? sanitize_text_field( $raw_settings['api_key'] ) : '',
            'model'                   => isset( $raw_settings['model'] ) ? sanitize_text_field( $raw_settings['model'] ) : 'gpt-3.5-turbo',
            'temperature'             => isset( $raw_settings['temperature'] ) ? floatval( $raw_settings['temperature'] ) : 0.4,
            'timeout'                 => isset( $raw_settings['timeout'] ) ? absint( $raw_settings['timeout'] ) : 10,
            'max_tokens'              => isset( $raw_settings['max_tokens'] ) ? absint( $raw_settings['max_tokens'] ) : 500,
            'additional_instructions' => $additional_instructions,
        ];

        update_option( self::OPTION_KEY, $settings );

        $redirect_args = [ 'updated' => '1' ];
        if ( $was_truncated ) {
            $redirect_args['truncated'] = '1';
        }

        wp_safe_redirect( add_query_arg( $redirect_args, wp_get_referer() ) );
        exit;
    }
}

// Inicializa o add-on após todos os plugins serem carregados.
add_action( 'plugins_loaded', function () {
    if ( class_exists( 'DPS_AI_Addon' ) ) {
        DPS_AI_Addon::get_instance();
    }
} );
