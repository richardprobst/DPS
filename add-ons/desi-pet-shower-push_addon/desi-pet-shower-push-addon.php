<?php
/**
 * Plugin Name:       DPS by PRObst – Push Notifications Add-on
 * Plugin URI:        https://www.probst.pro
 * Description:       Notificações push para administradores e equipe. Receba alertas em tempo real sobre novos agendamentos e mudanças de status.
 * Version:           1.0.0
 * Author:            PRObst
 * Author URI:        https://www.probst.pro
 * Text Domain:       dps-push-addon
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * License:           GPL-2.0+
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Verifica se o plugin base DPS by PRObst está ativo.
 */
function dps_push_check_base_plugin() {
    if ( ! class_exists( 'DPS_Base_Plugin' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__( 'O add-on Push Notifications requer o plugin base DPS by PRObst para funcionar.', 'dps-push-addon' );
            echo '</p></div>';
        } );
        return false;
    }
    return true;
}
add_action( 'plugins_loaded', function() {
    if ( ! dps_push_check_base_plugin() ) {
        return;
    }
}, 1 );

/**
 * Carrega o text domain do Push Add-on.
 */
function dps_push_load_textdomain() {
    load_plugin_textdomain( 'dps-push-addon', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'dps_push_load_textdomain', 1 );

// Carrega a API de Push Notifications
require_once __DIR__ . '/includes/class-dps-push-api.php';

/**
 * Classe principal do Push Notifications Add-on.
 *
 * Implementa Web Push API nativo do navegador para envio de notificações
 * push para administradores e equipe do petshop.
 *
 * @since 1.0.0
 */
class DPS_Push_Addon {

    /**
     * Chave da option para configurações.
     */
    const OPTION_KEY = 'dps_push_settings';

    /**
     * Chave da option para chaves VAPID.
     */
    const VAPID_KEY = 'dps_push_vapid_keys';

    /**
     * Construtor.
     */
    public function __construct() {
        // Menu admin
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ], 20 );
        add_action( 'init', [ $this, 'maybe_handle_save' ] );

        // Assets
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );

        // AJAX handlers
        add_action( 'wp_ajax_dps_push_subscribe', [ $this, 'subscribe_ajax' ] );
        add_action( 'wp_ajax_dps_push_unsubscribe', [ $this, 'unsubscribe_ajax' ] );
        add_action( 'wp_ajax_dps_push_test', [ $this, 'test_push_ajax' ] );

        // Hooks para enviar notificações
        add_action( 'dps_base_after_save_appointment', [ $this, 'notify_new_appointment' ], 20, 2 );
        add_action( 'dps_appointment_status_changed', [ $this, 'notify_status_change' ], 20, 4 );
        add_action( 'dps_appointment_rescheduled', [ $this, 'notify_rescheduled' ], 20, 5 );

        // Gerar chaves VAPID na ativação
        register_activation_hook( __FILE__, [ $this, 'generate_vapid_keys' ] );
    }

    /**
     * Gera chaves VAPID na ativação do plugin.
     *
     * @since 1.0.0
     */
    public function generate_vapid_keys() {
        $existing = get_option( self::VAPID_KEY );
        if ( $existing && ! empty( $existing['public'] ) && ! empty( $existing['private'] ) ) {
            return;
        }

        // Gerar chaves VAPID usando curva P-256
        $keys = DPS_Push_API::generate_vapid_keys();
        
        update_option( self::VAPID_KEY, $keys );
    }

    /**
     * Registra submenu admin para Push Notifications.
     */
    public function register_admin_menu() {
        add_submenu_page(
            'desi-pet-shower',
            __( 'Notificações Push', 'dps-push-addon' ),
            __( 'Notificações Push', 'dps-push-addon' ),
            'manage_options',
            'dps-push-notifications',
            [ $this, 'render_admin_page' ]
        );
    }

    /**
     * Enfileira assets do admin.
     *
     * @param string $hook Hook da página atual.
     */
    public function enqueue_admin_assets( $hook ) {
        // Carrega em todas as páginas admin do DPS para permitir inscrição
        if ( strpos( $hook, 'desi-pet-shower' ) === false && strpos( $hook, 'dps-' ) === false ) {
            // Verifica se é página do DPS ou agenda
            if ( ! is_admin() ) {
                return;
            }
        }

        $addon_url = plugin_dir_url( __FILE__ );
        $version   = '1.0.0';

        wp_enqueue_style(
            'dps-push-addon',
            $addon_url . 'assets/css/push-addon.css',
            [],
            $version
        );

        wp_enqueue_script(
            'dps-push-addon',
            $addon_url . 'assets/js/push-addon.js',
            [ 'jquery' ],
            $version,
            true
        );

        $vapid_keys = get_option( self::VAPID_KEY, [] );

        wp_localize_script( 'dps-push-addon', 'DPS_Push', [
            'ajax_url'        => admin_url( 'admin-ajax.php' ),
            'nonce_subscribe' => wp_create_nonce( 'dps_push_subscribe' ),
            'nonce_test'      => wp_create_nonce( 'dps_push_test' ),
            'vapid_public'    => $vapid_keys['public'] ?? '',
            'sw_url'          => $addon_url . 'assets/js/push-sw.js',
            'messages'        => [
                'subscribing'     => __( 'Ativando notificações...', 'dps-push-addon' ),
                'subscribed'      => __( 'Notificações ativadas!', 'dps-push-addon' ),
                'unsubscribed'    => __( 'Notificações desativadas.', 'dps-push-addon' ),
                'error'           => __( 'Erro ao ativar notificações.', 'dps-push-addon' ),
                'not_supported'   => __( 'Seu navegador não suporta notificações push.', 'dps-push-addon' ),
                'permission_denied' => __( 'Permissão negada. Habilite nas configurações do navegador.', 'dps-push-addon' ),
                'test_sent'       => __( 'Notificação de teste enviada!', 'dps-push-addon' ),
            ],
        ] );
    }

    /**
     * Enfileira assets no frontend (para página de agenda).
     */
    public function enqueue_frontend_assets() {
        // Verificar se está na página de agenda
        global $post;
        if ( ! $post || ! has_shortcode( $post->post_content, 'dps_agenda_page' ) ) {
            return;
        }

        // Somente para usuários logados com permissão
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $this->enqueue_admin_assets( 'dps-agenda' );
    }

    /**
     * AJAX: Inscrever para notificações push.
     */
    public function subscribe_ajax() {
        check_ajax_referer( 'dps_push_subscribe', 'nonce' );

        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissão negada.', 'dps-push-addon' ) ] );
        }

        $subscription = isset( $_POST['subscription'] ) ? json_decode( stripslashes( $_POST['subscription'] ), true ) : null;

        if ( ! $subscription || empty( $subscription['endpoint'] ) ) {
            wp_send_json_error( [ 'message' => __( 'Dados de inscrição inválidos.', 'dps-push-addon' ) ] );
        }

        $user_id = get_current_user_id();
        
        // Armazenar inscrição do usuário
        $subscriptions = get_user_meta( $user_id, '_dps_push_subscriptions', true );
        if ( ! is_array( $subscriptions ) ) {
            $subscriptions = [];
        }

        // Evitar duplicatas baseado no endpoint
        $endpoint_hash = md5( $subscription['endpoint'] );
        $subscriptions[ $endpoint_hash ] = [
            'endpoint' => $subscription['endpoint'],
            'keys'     => $subscription['keys'] ?? [],
            'created'  => current_time( 'mysql' ),
            'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
        ];

        update_user_meta( $user_id, '_dps_push_subscriptions', $subscriptions );

        // Log
        if ( class_exists( 'DPS_Logger' ) ) {
            DPS_Logger::info(
                sprintf( 'Usuário #%d inscrito para notificações push', $user_id ),
                [ 'endpoint_hash' => $endpoint_hash ],
                'push'
            );
        }

        wp_send_json_success( [ 'message' => __( 'Inscrição realizada com sucesso.', 'dps-push-addon' ) ] );
    }

    /**
     * AJAX: Cancelar inscrição de notificações push.
     */
    public function unsubscribe_ajax() {
        check_ajax_referer( 'dps_push_subscribe', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'Permissão negada.', 'dps-push-addon' ) ] );
        }

        $endpoint = isset( $_POST['endpoint'] ) ? sanitize_text_field( wp_unslash( $_POST['endpoint'] ) ) : '';
        
        if ( ! $endpoint ) {
            wp_send_json_error( [ 'message' => __( 'Endpoint inválido.', 'dps-push-addon' ) ] );
        }

        $user_id = get_current_user_id();
        $subscriptions = get_user_meta( $user_id, '_dps_push_subscriptions', true );
        
        if ( is_array( $subscriptions ) ) {
            $endpoint_hash = md5( $endpoint );
            unset( $subscriptions[ $endpoint_hash ] );
            update_user_meta( $user_id, '_dps_push_subscriptions', $subscriptions );
        }

        wp_send_json_success( [ 'message' => __( 'Inscrição cancelada.', 'dps-push-addon' ) ] );
    }

    /**
     * AJAX: Enviar notificação de teste.
     */
    public function test_push_ajax() {
        check_ajax_referer( 'dps_push_test', 'nonce' );

        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissão negada.', 'dps-push-addon' ) ] );
        }

        $user_id = get_current_user_id();
        
        $result = DPS_Push_API::send_to_user( $user_id, [
            'title' => __( '🔔 Teste de Notificação', 'dps-push-addon' ),
            'body'  => __( 'As notificações push estão funcionando corretamente!', 'dps-push-addon' ),
            'icon'  => plugin_dir_url( __FILE__ ) . 'assets/images/icon-192.png',
            'tag'   => 'dps-test-' . time(),
        ] );

        if ( $result['success'] > 0 ) {
            wp_send_json_success( [ 
                'message' => sprintf( 
                    __( 'Notificação enviada para %d dispositivo(s).', 'dps-push-addon' ),
                    $result['success']
                ),
            ] );
        } else {
            wp_send_json_error( [ 
                'message' => __( 'Nenhum dispositivo inscrito ou erro ao enviar.', 'dps-push-addon' ),
            ] );
        }
    }

    /**
     * Notifica sobre novo agendamento.
     *
     * @param int    $appt_id ID do agendamento.
     * @param string $mode    Modo do agendamento.
     */
    public function notify_new_appointment( $appt_id, $mode ) {
        $settings = get_option( self::OPTION_KEY, [] );
        
        if ( empty( $settings['notify_new_appointment'] ) ) {
            return;
        }

        $client_id = get_post_meta( $appt_id, 'appointment_client_id', true );
        $pet_id    = get_post_meta( $appt_id, 'appointment_pet_id', true );
        $date      = get_post_meta( $appt_id, 'appointment_date', true );
        $time      = get_post_meta( $appt_id, 'appointment_time', true );

        $client = get_post( $client_id );
        $pet    = get_post( $pet_id );

        $title = __( '📅 Novo Agendamento!', 'dps-push-addon' );
        $body  = sprintf(
            __( '%s (%s) - %s às %s', 'dps-push-addon' ),
            $pet ? $pet->post_title : __( 'Pet', 'dps-push-addon' ),
            $client ? $client->post_title : __( 'Cliente', 'dps-push-addon' ),
            date_i18n( 'd/m/Y', strtotime( $date ) ),
            $time
        );

        DPS_Push_API::send_to_all_admins( [
            'title' => $title,
            'body'  => $body,
            'icon'  => plugin_dir_url( __FILE__ ) . 'assets/images/icon-192.png',
            'tag'   => 'dps-new-appointment-' . $appt_id,
            'data'  => [
                'type'    => 'new_appointment',
                'appt_id' => $appt_id,
                'url'     => admin_url( 'admin.php?page=desi-pet-shower' ),
            ],
        ] );
    }

    /**
     * Notifica sobre mudança de status.
     *
     * @param int    $appt_id    ID do agendamento.
     * @param string $old_status Status anterior.
     * @param string $new_status Novo status.
     * @param int    $user_id    ID do usuário que alterou.
     */
    public function notify_status_change( $appt_id, $old_status, $new_status, $user_id ) {
        $settings = get_option( self::OPTION_KEY, [] );
        
        if ( empty( $settings['notify_status_change'] ) ) {
            return;
        }

        // Não notificar se o próprio usuário fez a alteração
        $current_user_id = get_current_user_id();
        if ( $user_id === $current_user_id ) {
            return;
        }

        $status_labels = [
            'pendente'        => __( 'Pendente', 'dps-push-addon' ),
            'finalizado'      => __( 'Finalizado', 'dps-push-addon' ),
            'finalizado_pago' => __( 'Finalizado e Pago', 'dps-push-addon' ),
            'cancelado'       => __( 'Cancelado', 'dps-push-addon' ),
        ];

        $pet_id = get_post_meta( $appt_id, 'appointment_pet_id', true );
        $pet    = get_post( $pet_id );
        $user   = get_userdata( $user_id );

        $title = __( '🔄 Status Alterado', 'dps-push-addon' );
        $body  = sprintf(
            __( '%s: %s → %s (por %s)', 'dps-push-addon' ),
            $pet ? $pet->post_title : '#' . $appt_id,
            $status_labels[ $old_status ] ?? $old_status,
            $status_labels[ $new_status ] ?? $new_status,
            $user ? $user->display_name : __( 'Sistema', 'dps-push-addon' )
        );

        // Enviar para todos os admins exceto quem alterou
        DPS_Push_API::send_to_all_admins( [
            'title' => $title,
            'body'  => $body,
            'icon'  => plugin_dir_url( __FILE__ ) . 'assets/images/icon-192.png',
            'tag'   => 'dps-status-change-' . $appt_id,
            'data'  => [
                'type'    => 'status_change',
                'appt_id' => $appt_id,
            ],
        ], [ $user_id ] );
    }

    /**
     * Notifica sobre reagendamento.
     *
     * @param int    $appt_id  ID do agendamento.
     * @param string $new_date Nova data.
     * @param string $new_time Novo horário.
     * @param string $old_date Data anterior.
     * @param string $old_time Horário anterior.
     */
    public function notify_rescheduled( $appt_id, $new_date, $new_time, $old_date, $old_time ) {
        $settings = get_option( self::OPTION_KEY, [] );
        
        if ( empty( $settings['notify_rescheduled'] ) ) {
            return;
        }

        $pet_id = get_post_meta( $appt_id, 'appointment_pet_id', true );
        $pet    = get_post( $pet_id );

        $title = __( '📅 Agendamento Reagendado', 'dps-push-addon' );
        $body  = sprintf(
            __( '%s: %s %s → %s %s', 'dps-push-addon' ),
            $pet ? $pet->post_title : '#' . $appt_id,
            date_i18n( 'd/m', strtotime( $old_date ) ),
            $old_time,
            date_i18n( 'd/m', strtotime( $new_date ) ),
            $new_time
        );

        DPS_Push_API::send_to_all_admins( [
            'title' => $title,
            'body'  => $body,
            'icon'  => plugin_dir_url( __FILE__ ) . 'assets/images/icon-192.png',
            'tag'   => 'dps-rescheduled-' . $appt_id,
            'data'  => [
                'type'    => 'rescheduled',
                'appt_id' => $appt_id,
            ],
        ], [ get_current_user_id() ] );
    }

    /**
     * Processa salvamento de configurações.
     */
    public function maybe_handle_save() {
        if ( ! isset( $_POST['dps_push_save'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'dps_push_settings' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings = [
            'notify_new_appointment' => ! empty( $_POST['notify_new_appointment'] ),
            'notify_status_change'   => ! empty( $_POST['notify_status_change'] ),
            'notify_rescheduled'     => ! empty( $_POST['notify_rescheduled'] ),
        ];

        update_option( self::OPTION_KEY, $settings );

        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html__( 'Configurações salvas com sucesso.', 'dps-push-addon' );
            echo '</p></div>';
        } );
    }

    /**
     * Renderiza página de configurações.
     */
    public function render_admin_page() {
        $settings = get_option( self::OPTION_KEY, [] );
        $vapid_keys = get_option( self::VAPID_KEY, [] );
        $user_id = get_current_user_id();
        $subscriptions = get_user_meta( $user_id, '_dps_push_subscriptions', true );
        $sub_count = is_array( $subscriptions ) ? count( $subscriptions ) : 0;

        ?>
        <div class="wrap dps-push-settings">
            <h1><?php echo esc_html__( 'Notificações Push', 'dps-push-addon' ); ?></h1>

            <div class="dps-push-container">
                <!-- Status Card -->
                <div class="dps-push-card dps-push-status-card">
                    <h2>🔔 <?php echo esc_html__( 'Seu Status', 'dps-push-addon' ); ?></h2>
                    
                    <div class="dps-push-status">
                        <div id="dps-push-status-indicator" class="dps-push-indicator dps-push-checking">
                            <span class="dps-push-dot"></span>
                            <span class="dps-push-status-text"><?php echo esc_html__( 'Verificando...', 'dps-push-addon' ); ?></span>
                        </div>
                        
                        <p class="dps-push-devices">
                            <?php 
                            printf( 
                                esc_html__( '%d dispositivo(s) inscrito(s) na sua conta', 'dps-push-addon' ), 
                                $sub_count 
                            ); 
                            ?>
                        </p>
                    </div>

                    <div class="dps-push-actions">
                        <button type="button" id="dps-push-subscribe" class="button button-primary">
                            <?php echo esc_html__( 'Ativar Notificações', 'dps-push-addon' ); ?>
                        </button>
                        <button type="button" id="dps-push-test" class="button" style="display: none;">
                            <?php echo esc_html__( 'Testar Notificação', 'dps-push-addon' ); ?>
                        </button>
                    </div>
                </div>

                <!-- Settings Card -->
                <div class="dps-push-card">
                    <h2>⚙️ <?php echo esc_html__( 'Configurações', 'dps-push-addon' ); ?></h2>

                    <form method="post">
                        <?php wp_nonce_field( 'dps_push_settings' ); ?>

                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php echo esc_html__( 'Notificar sobre', 'dps-push-addon' ); ?></th>
                                <td>
                                    <fieldset>
                                        <label>
                                            <input type="checkbox" name="notify_new_appointment" value="1" 
                                                   <?php checked( ! empty( $settings['notify_new_appointment'] ) ); ?>>
                                            <?php echo esc_html__( 'Novos agendamentos', 'dps-push-addon' ); ?>
                                        </label>
                                        <br>
                                        <label>
                                            <input type="checkbox" name="notify_status_change" value="1"
                                                   <?php checked( ! empty( $settings['notify_status_change'] ) ); ?>>
                                            <?php echo esc_html__( 'Mudanças de status', 'dps-push-addon' ); ?>
                                        </label>
                                        <br>
                                        <label>
                                            <input type="checkbox" name="notify_rescheduled" value="1"
                                                   <?php checked( ! empty( $settings['notify_rescheduled'] ) ); ?>>
                                            <?php echo esc_html__( 'Reagendamentos', 'dps-push-addon' ); ?>
                                        </label>
                                    </fieldset>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <button type="submit" name="dps_push_save" class="button button-primary">
                                <?php echo esc_html__( 'Salvar Configurações', 'dps-push-addon' ); ?>
                            </button>
                        </p>
                    </form>
                </div>

                <!-- Info Card -->
                <div class="dps-push-card dps-push-info-card">
                    <h2>ℹ️ <?php echo esc_html__( 'Como Funciona', 'dps-push-addon' ); ?></h2>
                    
                    <ol>
                        <li><?php echo esc_html__( 'Clique em "Ativar Notificações" e permita no navegador', 'dps-push-addon' ); ?></li>
                        <li><?php echo esc_html__( 'Repita em cada dispositivo que deseja receber notificações', 'dps-push-addon' ); ?></li>
                        <li><?php echo esc_html__( 'Configure quais eventos devem gerar notificações', 'dps-push-addon' ); ?></li>
                        <li><?php echo esc_html__( 'Receba alertas em tempo real, mesmo com o navegador fechado!', 'dps-push-addon' ); ?></li>
                    </ol>

                    <p class="description">
                        <?php echo esc_html__( 'Nota: Requer HTTPS e navegador compatível (Chrome, Firefox, Edge, Safari 16+).', 'dps-push-addon' ); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }
}

/**
 * Inicializa o Push Add-on.
 */
function dps_push_init_addon() {
    if ( class_exists( 'DPS_Push_Addon' ) ) {
        new DPS_Push_Addon();
    }
}
add_action( 'init', 'dps_push_init_addon', 5 );
