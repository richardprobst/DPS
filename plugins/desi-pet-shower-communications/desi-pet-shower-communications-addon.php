<?php
/**
 * Plugin Name:       desi.pet by PRObst – Communications Add-on
 * Plugin URI:        https://www.probst.pro
 * Description:       Comunicações integradas via WhatsApp, SMS e e-mail. Notifique clientes automaticamente sobre agendamentos e eventos.
 * Version:           0.3.0
 * Author:            PRObst
 * Author URI:        https://www.probst.pro
 * Text Domain:       dps-communications-addon
 * Domain Path:       /languages
 * Requires at least: 6.9
 * Requires PHP:      8.4
 * Update URI:        https://github.com/richardprobst/DPS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Verifica se o plugin base desi.pet by PRObst está ativo.
 * Se não estiver, exibe aviso e interrompe carregamento do add-on.
 */
function dps_communications_check_base_plugin() {
    if ( ! class_exists( 'DPS_Base_Plugin' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__( 'O add-on requer o plugin base desi.pet by PRObst para funcionar.', 'dps-communications-addon' );
            echo '</p></div>';
        } );
        return false;
    }
    return true;
}
add_action( 'plugins_loaded', function() {
    if ( ! dps_communications_check_base_plugin() ) {
        return;
    }
}, 1 );

/**
 * Carrega o text domain do Communications Add-on.
 * Usa prioridade 1 para garantir que rode antes da inicialização da classe (prioridade 5).
 */
function dps_communications_load_textdomain() {
    load_plugin_textdomain( 'dps-communications-addon', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'dps_communications_load_textdomain', 1 );

// Carrega as classes do add-on
require_once __DIR__ . '/includes/class-dps-communications-api.php';
require_once __DIR__ . '/includes/class-dps-communications-history.php';
require_once __DIR__ . '/includes/class-dps-communications-retry.php';
require_once __DIR__ . '/includes/class-dps-communications-webhook.php';

class DPS_Communications_Addon {

    const OPTION_KEY = 'dps_comm_settings';

    /**
     * Instância única (singleton).
     *
     * @var DPS_Communications_Addon|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única.
     *
     * @return DPS_Communications_Addon
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Registra menu admin para comunicações
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ], 20 );
        add_action( 'init', [ $this, 'maybe_handle_save' ] );

        // Enfileira assets CSS para responsividade
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

        add_action( 'dps_base_after_save_appointment', [ $this, 'handle_after_save_appointment' ], 10, 2 );
        add_action( 'dps_comm_send_appointment_reminder', [ $this, 'send_appointment_reminder' ], 10, 1 );
        add_action( 'dps_comm_send_post_service', [ $this, 'send_post_service_message' ], 10, 1 );

        // Inicializa componentes adicionais
        $this->init_components();
    }

    /**
     * Inicializa componentes adicionais (Histórico, Retry, Webhook)
     *
     * @since 0.3.0
     */
    private function init_components() {
        // Inicializa histórico de comunicações
        if ( class_exists( 'DPS_Communications_History' ) ) {
            DPS_Communications_History::get_instance();
        }

        // Inicializa sistema de retry
        if ( class_exists( 'DPS_Communications_Retry' ) ) {
            DPS_Communications_Retry::get_instance();
        }

        // Inicializa webhooks
        if ( class_exists( 'DPS_Communications_Webhook' ) ) {
            DPS_Communications_Webhook::get_instance();
        }
    }

    /**
     * Enfileira CSS e JS do add-on na página de configurações.
     *
     * @since 1.0.0
     * @param string $hook Hook da página atual.
     */
    public function enqueue_admin_assets( $hook ) {
        // Cast para string para compatibilidade com PHP 8.1+
        $hook = (string) $hook;

        // Carrega apenas na página de configurações de comunicações
        if ( 'desi-pet-shower_page_dps-communications' !== $hook ) {
            return;
        }

        $addon_url = plugin_dir_url( __FILE__ );
        $version   = '0.3.0';

        // CSS
        wp_enqueue_style(
            'dps-communications-addon',
            $addon_url . 'assets/css/communications-addon.css',
            [],
            $version
        );

        // JavaScript
        wp_enqueue_script(
            'dps-communications-addon',
            $addon_url . 'assets/js/communications-addon.js',
            [ 'jquery' ],
            $version,
            true // In footer
        );

        // Strings localizadas para JS
        wp_localize_script(
            'dps-communications-addon',
            'dpsCommL10n',
            [
                'saving'       => __( 'Salvando...', 'dps-communications-addon' ),
                'invalidEmail' => __( 'Por favor, insira um e-mail válido.', 'dps-communications-addon' ),
                'invalidUrl'   => __( 'A URL deve usar HTTPS para segurança.', 'dps-communications-addon' ),
            ]
        );
    }

    /**
     * Registra submenu admin para comunicações.
     * 
     * NOTA: Menu exibido como submenu de "desi.pet by PRObst" para alinhamento com a navegação unificada.
     * Também acessível pelo hub em dps-integrations-hub (aba "Comunicações").
     */
    public function register_admin_menu() {
        add_submenu_page(
            'desi-pet-shower',
            __( 'Comunicações', 'dps-communications-addon' ),
            __( 'Comunicações', 'dps-communications-addon' ),
            'manage_options',
            'dps-communications',
            [ $this, 'render_admin_page' ]
        );
    }

    /**
     * Renderiza a página admin de comunicações.
     */
    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'dps-communications-addon' ) );
        }

        $options = get_option( self::OPTION_KEY, [] );
        $status  = isset( $_GET['updated'] ) ? sanitize_text_field( wp_unslash( $_GET['updated'] ) ) : '';

        ?>
        <div class="wrap dps-communications-wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <p><?php echo esc_html__( 'Configure integrações e mensagens automáticas para WhatsApp, SMS ou e-mail.', 'dps-communications-addon' ); ?></p>

            <?php
            // Exibe mensagens de erro persistidas via transient
            $transient_error = get_transient( 'dps_comm_settings_error' );
            if ( $transient_error ) {
                delete_transient( 'dps_comm_settings_error' );
                ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php echo esc_html( $transient_error ); ?></p>
                </div>
                <?php
            }
            
            // Exibe mensagens de erro de settings (fallback)
            settings_errors( 'dps_communications' );
            ?>

            <?php if ( '1' === $status ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html__( 'Configurações salvas com sucesso.', 'dps-communications-addon' ); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="" id="dps-comm-settings-form">
                <input type="hidden" name="dps_comm_action" value="save_settings" />
                <?php wp_nonce_field( 'dps_comm_save', 'dps_comm_nonce' ); ?>

                <h2><?php esc_html_e( 'Configurações do WhatsApp', 'dps-communications-addon' ); ?></h2>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="dps_whatsapp_number"><?php echo esc_html__( 'Número do WhatsApp da Equipe', 'dps-communications-addon' ); ?></label>
                            </th>
                            <td>
                                <input type="text" id="dps_whatsapp_number" name="dps_whatsapp_number" value="<?php echo esc_attr( get_option( 'dps_whatsapp_number', '+55 15 99160-6299' ) ); ?>" class="regular-text" />
                                <p class="description"><?php esc_html_e( 'Número de telefone da equipe desi.pet by PRObst (formato: +55 15 99160-6299). Este número será usado em todos os botões que permitem o cliente entrar em contato com a equipe.', 'dps-communications-addon' ); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="dps_comm_whatsapp_api_key"><?php echo esc_html__( 'Chave de API do WhatsApp', 'dps-communications-addon' ); ?></label>
                            </th>
                            <td>
                                <input type="password" id="dps_comm_whatsapp_api_key" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[whatsapp_api_key]" value="<?php echo esc_attr( $options['whatsapp_api_key'] ?? '' ); ?>" class="regular-text" autocomplete="off" />
                                <p class="description"><?php esc_html_e( 'Token de autenticação para o gateway de WhatsApp (Evolution API, etc.).', 'dps-communications-addon' ); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="dps_comm_whatsapp_api_url"><?php echo esc_html__( 'Endpoint/Base URL do WhatsApp', 'dps-communications-addon' ); ?></label>
                            </th>
                            <td>
                                <input type="url" id="dps_comm_whatsapp_api_url" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[whatsapp_api_url]" value="<?php echo esc_attr( $options['whatsapp_api_url'] ?? '' ); ?>" class="regular-text" />
                                <p class="description"><?php esc_html_e( 'URL base da API de envio de mensagens WhatsApp.', 'dps-communications-addon' ); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <h2><?php esc_html_e( 'Configurações de E-mail', 'dps-communications-addon' ); ?></h2>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="dps_comm_default_email_from"><?php echo esc_html__( 'E-mail remetente padrão', 'dps-communications-addon' ); ?></label>
                            </th>
                            <td>
                                <input type="email" id="dps_comm_default_email_from" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[default_email_from]" value="<?php echo esc_attr( $options['default_email_from'] ?? '' ); ?>" class="regular-text" />
                                <p class="description"><?php esc_html_e( 'Endereço de e-mail usado como remetente nas mensagens automáticas.', 'dps-communications-addon' ); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <h2><?php esc_html_e( 'Templates de Mensagens', 'dps-communications-addon' ); ?></h2>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="dps_comm_template_confirmation"><?php echo esc_html__( 'Template de confirmação de agendamento', 'dps-communications-addon' ); ?></label>
                            </th>
                            <td>
                                <textarea id="dps_comm_template_confirmation" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[template_confirmation]" rows="4" class="large-text code"><?php echo esc_textarea( $options['template_confirmation'] ?? '' ); ?></textarea>
                                <p class="description"><?php esc_html_e( 'Variáveis disponíveis: {cliente}, {pet}, {data}, {hora}, {servico}', 'dps-communications-addon' ); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="dps_comm_template_reminder"><?php echo esc_html__( 'Template de lembrete', 'dps-communications-addon' ); ?></label>
                            </th>
                            <td>
                                <textarea id="dps_comm_template_reminder" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[template_reminder]" rows="4" class="large-text code"><?php echo esc_textarea( $options['template_reminder'] ?? '' ); ?></textarea>
                                <p class="description"><?php esc_html_e( 'Variáveis disponíveis: {cliente}, {pet}, {data}, {hora}, {servico}', 'dps-communications-addon' ); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="dps_comm_template_post_service"><?php echo esc_html__( 'Template de pós-atendimento', 'dps-communications-addon' ); ?></label>
                            </th>
                            <td>
                                <textarea id="dps_comm_template_post_service" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[template_post_service]" rows="4" class="large-text code"><?php echo esc_textarea( $options['template_post_service'] ?? '' ); ?></textarea>
                                <p class="description"><?php esc_html_e( 'Variáveis disponíveis: {cliente}, {pet}, {data}, {servico}', 'dps-communications-addon' ); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button( __( 'Salvar configurações', 'dps-communications-addon' ) ); ?>
            </form>

            <?php 
            $this->render_statistics_section();
            $this->render_webhook_info_section(); 
            ?>
        </div>
        <?php
    }

    /**
     * Renderiza seção de estatísticas de comunicações
     *
     * @since 0.3.0
     */
    private function render_statistics_section() {
        // Só exibe se a classe de histórico existir
        if ( ! class_exists( 'DPS_Communications_History' ) ) {
            return;
        }

        $history = DPS_Communications_History::get_instance();
        $stats   = $history->get_stats();

        if ( empty( $stats ) ) {
            return;
        }
        ?>
        <h2><?php esc_html_e( 'Estatísticas de Comunicações', 'dps-communications-addon' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Resumo das mensagens enviadas pelo sistema.', 'dps-communications-addon' ); ?></p>

        <div class="dps-comm-stats-grid">
            <?php
            $status_labels = [
                'pending'   => [ 'label' => __( 'Pendentes', 'dps-communications-addon' ), 'icon' => '⏳', 'color' => '#f59e0b' ],
                'sent'      => [ 'label' => __( 'Enviadas', 'dps-communications-addon' ), 'icon' => '📤', 'color' => '#3b82f6' ],
                'delivered' => [ 'label' => __( 'Entregues', 'dps-communications-addon' ), 'icon' => '✅', 'color' => '#10b981' ],
                'read'      => [ 'label' => __( 'Lidas', 'dps-communications-addon' ), 'icon' => '👁️', 'color' => '#8b5cf6' ],
                'failed'    => [ 'label' => __( 'Falhas', 'dps-communications-addon' ), 'icon' => '❌', 'color' => '#ef4444' ],
                'retrying'  => [ 'label' => __( 'Reenviando', 'dps-communications-addon' ), 'icon' => '🔄', 'color' => '#f97316' ],
            ];

            foreach ( $stats as $status => $count ) {
                if ( ! isset( $status_labels[ $status ] ) ) {
                    continue;
                }
                $info = $status_labels[ $status ];
                ?>
                <div class="dps-comm-stat-card" style="border-left-color: <?php echo esc_attr( $info['color'] ); ?>;">
                    <span class="dps-comm-stat-icon"><?php echo esc_html( $info['icon'] ); ?></span>
                    <div class="dps-comm-stat-content">
                        <span class="dps-comm-stat-count"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
                        <span class="dps-comm-stat-label"><?php echo esc_html( $info['label'] ); ?></span>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
        <?php
    }

    /**
     * Renderiza seção de informações de webhook
     *
     * @since 0.3.0
     */
    private function render_webhook_info_section() {
        // Só exibe se a classe de webhook existir
        if ( ! class_exists( 'DPS_Communications_Webhook' ) ) {
            return;
        }

        $webhook_secret = DPS_Communications_Webhook::get_secret();
        ?>
        <h2><?php esc_html_e( 'Configuração de Webhooks', 'dps-communications-addon' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Use estas informações para configurar webhooks de status de entrega em seu gateway de mensagens.', 'dps-communications-addon' ); ?></p>

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><?php esc_html_e( 'URL do Webhook (Evolution API)', 'dps-communications-addon' ); ?></th>
                    <td>
                        <code><?php echo esc_html( DPS_Communications_Webhook::get_webhook_url( 'evolution' ) ); ?></code>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'URL do Webhook (Twilio)', 'dps-communications-addon' ); ?></th>
                    <td>
                        <code><?php echo esc_html( DPS_Communications_Webhook::get_webhook_url( 'twilio' ) ); ?></code>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'URL do Webhook (Genérico)', 'dps-communications-addon' ); ?></th>
                    <td>
                        <code><?php echo esc_html( DPS_Communications_Webhook::get_webhook_url( 'generic' ) ); ?></code>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Secret de Autenticação', 'dps-communications-addon' ); ?></th>
                    <td>
                        <div class="dps-secret-wrapper">
                            <input type="password" id="dps_webhook_secret" value="<?php echo esc_attr( $webhook_secret ); ?>" class="regular-text" readonly />
                            <button type="button" class="button button-secondary" id="dps-toggle-secret" aria-label="<?php esc_attr_e( 'Mostrar/ocultar secret', 'dps-communications-addon' ); ?>">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                            <button type="button" class="button button-secondary" id="dps-copy-secret" aria-label="<?php esc_attr_e( 'Copiar secret', 'dps-communications-addon' ); ?>">
                                <span class="dashicons dashicons-clipboard"></span>
                            </button>
                        </div>
                        <p class="description"><?php esc_html_e( 'Use no header: Authorization: Bearer <secret> ou X-Webhook-Secret: <secret>', 'dps-communications-addon' ); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    public function sanitize_settings( $input ) {
        $output = [];

        $output['whatsapp_api_key']      = isset( $input['whatsapp_api_key'] ) ? sanitize_text_field( $input['whatsapp_api_key'] ) : '';
        $output['whatsapp_api_url']      = isset( $input['whatsapp_api_url'] ) ? $this->sanitize_api_url( $input['whatsapp_api_url'] ) : '';
        $output['default_email_from']    = isset( $input['default_email_from'] ) ? sanitize_email( $input['default_email_from'] ) : '';
        $output['template_confirmation'] = isset( $input['template_confirmation'] ) ? sanitize_textarea_field( $input['template_confirmation'] ) : '';
        $output['template_reminder']     = isset( $input['template_reminder'] ) ? sanitize_textarea_field( $input['template_reminder'] ) : '';
        $output['template_post_service'] = isset( $input['template_post_service'] ) ? sanitize_textarea_field( $input['template_post_service'] ) : '';

        return $output;
    }

    /**
     * Sanitiza e valida URL da API do WhatsApp para prevenção de SSRF.
     *
     * @since 0.2.1
     * @param string $url URL a validar.
     * @return string URL sanitizada ou vazio se inválida.
     */
    private function sanitize_api_url( $url ) {
        $url = esc_url_raw( $url );
        
        if ( empty( $url ) ) {
            return '';
        }

        // Parse URL para validação de host
        $parsed = wp_parse_url( $url );
        
        // Só aceita HTTPS em produção
        if ( ! isset( $parsed['scheme'] ) || 'https' !== $parsed['scheme'] ) {
            // Permite HTTP apenas em ambiente de desenvolvimento
            if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || 'http' !== ( $parsed['scheme'] ?? '' ) ) {
                return '';
            }
        }

        // Bloqueia endereços internos (SSRF prevention)
        if ( isset( $parsed['host'] ) ) {
            $host = strtolower( $parsed['host'] );
            
            // Bloqueia localhost, IPs privados e metadados de cloud
            $blocked_patterns = [
                'localhost',
                '127.0.0.1',
                '0.0.0.0',
                '::1',
                '169.254.',      // Link-local
                '10.',           // Private Class A
                '172.16.',       // Private Class B
                '172.17.',
                '172.18.',
                '172.19.',
                '172.20.',
                '172.21.',
                '172.22.',
                '172.23.',
                '172.24.',
                '172.25.',
                '172.26.',
                '172.27.',
                '172.28.',
                '172.29.',
                '172.30.',
                '172.31.',
                '192.168.',      // Private Class C
                'metadata.google.internal',
                'metadata.google',
                '169.254.169.254', // AWS/GCP metadata
            ];

            foreach ( $blocked_patterns as $pattern ) {
                if ( str_starts_with( $host, $pattern ) ) {
                    return '';
                }
            }
        }

        return $url;
    }

    public function maybe_handle_save() {
        if ( ! isset( $_POST['dps_comm_action'] ) || 'save_settings' !== $_POST['dps_comm_action'] ) {
            return;
        }

        // Verifica nonce e dá feedback adequado usando helper
        if ( ! DPS_Request_Validator::verify_request_nonce( 'dps_comm_nonce', 'dps_comm_save', 'POST', false ) ) {
            // Usa transient para persistir mensagem de erro entre redirects
            set_transient( 'dps_comm_settings_error', __( 'Sessão expirada. Atualize a página e tente novamente.', 'dps-communications-addon' ), 30 );
            wp_safe_redirect( add_query_arg( [ 'page' => 'dps-communications' ], admin_url( 'admin.php' ) ) );
            exit;
        }

        // Verifica permissão e dá feedback adequado
        if ( ! current_user_can( 'manage_options' ) ) {
            set_transient( 'dps_comm_settings_error', __( 'Você não tem permissão para alterar estas configurações.', 'dps-communications-addon' ), 30 );
            wp_safe_redirect( add_query_arg( [ 'page' => 'dps-communications' ], admin_url( 'admin.php' ) ) );
            exit;
        }

        $raw_settings = isset( $_POST[ self::OPTION_KEY ] ) ? (array) wp_unslash( $_POST[ self::OPTION_KEY ] ) : [];
        $settings     = $this->sanitize_settings( $raw_settings );

        update_option( self::OPTION_KEY, $settings );

        // Salva o número do WhatsApp da equipe separadamente (com validação)
        if ( isset( $_POST['dps_whatsapp_number'] ) ) {
            $whatsapp_number = sanitize_text_field( wp_unslash( $_POST['dps_whatsapp_number'] ) );
            // Remove caracteres não numéricos exceto +, espaços, hífens e parênteses
            $whatsapp_number = preg_replace( '/[^0-9\+\s\-\(\)]/', '', $whatsapp_number );
            update_option( 'dps_whatsapp_number', $whatsapp_number );
        }

        wp_safe_redirect( add_query_arg( [ 'page' => 'dps-communications', 'updated' => '1' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Handler para envio de confirmação após salvar agendamento
     *
     * NOTA: Este método é chamado pela Agenda ao criar novo agendamento.
     * A lógica de ENVIO está delegada à Communications API.
     *
     * @param int    $appointment_id ID do agendamento
     * @param string $type           Tipo de operação ('new' ou 'update')
     */
    public function handle_after_save_appointment( $appointment_id, $type ) {
        if ( 'new' !== $type ) {
            return;
        }

        $options  = get_option( self::OPTION_KEY, [] );
        $template = isset( $options['template_confirmation'] ) ? $options['template_confirmation'] : '';
        
        if ( empty( $template ) ) {
            return;
        }

        $message  = $this->prepare_message_from_template( $template, $appointment_id );
        $phone    = get_post_meta( $appointment_id, 'dps_client_phone', true );

        if ( ! empty( $phone ) && ! empty( $message ) ) {
            // Delega o envio para a API central
            $api = DPS_Communications_API::get_instance();
            $api->send_whatsapp( $phone, $message, [
                'appointment_id' => $appointment_id,
                'type'           => 'confirmation',
            ] );
        }

        $this->schedule_reminder( $appointment_id );
    }

    private function schedule_reminder( $appointment_id ) {
        $appointment_datetime = get_post_meta( $appointment_id, 'dps_appointment_datetime', true );
        $timestamp            = $appointment_datetime ? strtotime( $appointment_datetime ) : false;

        if ( ! $timestamp ) {
            return;
        }

        $reminder_time = $timestamp - DAY_IN_SECONDS;

        if ( $reminder_time <= time() ) {
            return;
        }

        wp_schedule_single_event( $reminder_time, 'dps_comm_send_appointment_reminder', [ $appointment_id ] );
    }

    /**
     * Envia lembrete de agendamento (via cron job)
     *
     * NOTA: Este método é chamado automaticamente pelo cron job agendado.
     * A lógica de ENVIO está delegada à Communications API.
     *
     * @param int $appointment_id ID do agendamento
     */
    public function send_appointment_reminder( $appointment_id ) {
        // Delega para a API central que já implementa toda a lógica
        $api = DPS_Communications_API::get_instance();
        $api->send_appointment_reminder( $appointment_id );
    }

    private function prepare_message_from_template( $template, $appointment_id ) {
        $appointment = get_post( $appointment_id );
        $replacements = [
            '{appointment_id}'   => $appointment_id,
            '{appointment_title}' => $appointment ? $appointment->post_title : '',
        ];

        return strtr( $template, $replacements );
    }

    /**
     * Envia mensagem pós-atendimento
     *
     * NOTA: A lógica de ENVIO está delegada à Communications API.
     *
     * @param int $appointment_id ID do agendamento
     */
    public function send_post_service_message( $appointment_id ) {
        $options  = get_option( self::OPTION_KEY, [] );
        $template = isset( $options['template_post_service'] ) ? $options['template_post_service'] : '';
        
        if ( empty( $template ) ) {
            return;
        }

        $message  = $this->prepare_message_from_template( $template, $appointment_id );
        $phone    = get_post_meta( $appointment_id, 'dps_client_phone', true );

        if ( ! empty( $phone ) && ! empty( $message ) ) {
            // Delega o envio para a API central
            $api = DPS_Communications_API::get_instance();
            $api->send_whatsapp( $phone, $message, [
                'appointment_id' => $appointment_id,
                'type'           => 'post_service',
            ] );
        }
    }
}

if ( ! function_exists( 'dps_comm_init' ) ) {
    /**
     * Inicializa o Communications Add-on após o hook 'init' para garantir que o text domain seja carregado primeiro.
     * Usa prioridade 5 para rodar após o carregamento do text domain (prioridade 1) mas antes
     * de outros registros (prioridade 10).
     */
    function dps_comm_init() {
        if ( class_exists( 'DPS_Communications_Addon' ) ) {
            return DPS_Communications_Addon::get_instance();
        }
        return null;
    }
}

add_action( 'init', 'dps_comm_init', 5 );

/**
 * Funções helper para compatibilidade retroativa
 *
 * NOTA: Estas funções delegam para a Communications API.
 * Outros add-ons DEVEM usar DPS_Communications_API::get_instance() diretamente.
 */

if ( ! function_exists( 'dps_comm_send_whatsapp' ) ) {
    /**
     * Envia mensagem via WhatsApp
     *
     * @deprecated 0.2.0 Use DPS_Communications_API::get_instance()->send_whatsapp()
     *
     * @param string $phone   Telefone do destinatário
     * @param string $message Mensagem a enviar
     * @return bool True se enviado, false caso contrário
     */
    function dps_comm_send_whatsapp( $phone, $message ) {
        if ( class_exists( 'DPS_Communications_API' ) ) {
            $api = DPS_Communications_API::get_instance();
            return $api->send_whatsapp( $phone, $message, [ 'source' => 'legacy_function' ] );
        }

        // Fallback: log apenas indicação de envio, sem expor dados pessoais (PII)
        // @codingStandardsIgnoreLine
        error_log( 'DPS Communications: tentativa de envio WhatsApp via função legada (API não disponível)' );
        return true;
    }
}

if ( ! function_exists( 'dps_comm_send_email' ) ) {
    /**
     * Envia e-mail
     *
     * @deprecated 0.2.0 Use DPS_Communications_API::get_instance()->send_email()
     *
     * @param string $email   E-mail do destinatário
     * @param string $subject Assunto
     * @param string $message Corpo da mensagem
     * @return bool True se enviado, false caso contrário
     */
    function dps_comm_send_email( $email, $subject, $message ) {
        if ( class_exists( 'DPS_Communications_API' ) ) {
            $api = DPS_Communications_API::get_instance();
            return $api->send_email( $email, $subject, $message, [ 'source' => 'legacy_function' ] );
        }

        // Fallback se API não estiver disponível
        return wp_mail( $email, $subject, $message );
    }
}

if ( ! function_exists( 'dps_comm_send_sms' ) ) {
    /**
     * Envia SMS
     *
     * @deprecated 0.2.0 Funcionalidade não implementada
     *
     * @param string $phone   Telefone
     * @param string $message Mensagem
     * @return bool
     */
    function dps_comm_send_sms( $phone, $message ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClassAfterLastUsed
        // Log apenas indicação de funcionalidade não implementada, sem expor PII
        // @codingStandardsIgnoreLine
        error_log( 'DPS Communications: tentativa de envio SMS (funcionalidade não implementada)' );
        return false;
    }
}
