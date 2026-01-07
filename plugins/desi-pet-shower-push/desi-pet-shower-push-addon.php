<?php
/**
 * Plugin Name:       desi.pet by PRObst – Push Notifications Add-on
 * Plugin URI:        https://www.probst.pro
 * Description:       Notificações push e relatórios por email para administradores e equipe. Receba alertas em tempo real e relatórios diários/semanais automáticos.
 * Version:           1.3.0
 * Author:            PRObst
 * Author URI:        https://www.probst.pro
 * Text Domain:       dps-push-addon
 * Domain Path:       /languages
 * Requires at least: 6.9
 * Requires PHP:      8.4
 * Update URI:        https://github.com/richardprobst/DPS
 * License:           GPL-2.0+
 */

// Impede acesso direto.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Verifica se o plugin base desi.pet by PRObst está ativo.
 */
function dps_push_check_base_plugin() {
    if ( ! class_exists( 'DPS_Base_Plugin' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__( 'O add-on requer o plugin base desi.pet by PRObst para funcionar.', 'dps-push-addon' );
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

// Carrega a classe de Relatórios por Email
require_once __DIR__ . '/includes/class-dps-email-reports.php';

/**
 * Ativa os cron jobs do Email Reports e gera chaves VAPID na ativação do plugin.
 *
 * @since 1.3.1
 */
function dps_push_activate_plugin() {
    // Gerar chaves VAPID se não existirem.
    $vapid_keys = get_option( 'dps_push_vapid_keys' );
    if ( ! $vapid_keys || empty( $vapid_keys['public'] ) || empty( $vapid_keys['private'] ) ) {
        $keys = DPS_Push_API::generate_vapid_keys();
        update_option( 'dps_push_vapid_keys', $keys );
    }

    // O singleton DPS_Email_Reports é normalmente instanciado em `init`, mas durante
    // a ativação do plugin o hook `init` ainda não foi executado. Forçamos a criação
    // da instância aqui para garantir que os crons sejam agendados corretamente.
    $instance = DPS_Email_Reports::get_instance();
    if ( method_exists( $instance, 'activate' ) ) {
        $instance->activate();
    }
}
register_activation_hook( __FILE__, 'dps_push_activate_plugin' );

/**
 * Desativa os cron jobs do Email Reports na desativação do plugin.
 *
 * @since 1.3.1
 */
function dps_push_deactivate_plugin() {
    $instance = DPS_Email_Reports::get_instance();
    if ( method_exists( $instance, 'deactivate' ) ) {
        $instance->deactivate();
    }
}
register_deactivation_hook( __FILE__, 'dps_push_deactivate_plugin' );

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
     * Instância única (singleton).
     *
     * @var DPS_Push_Addon|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única.
     *
     * @return DPS_Push_Addon
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor.
     */
    public function __construct() {
        // Menu admin
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ], 20 );
        add_action( 'admin_init', [ $this, 'maybe_handle_save' ] );

        // Assets
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );

        // AJAX handlers
        add_action( 'wp_ajax_dps_push_subscribe', [ $this, 'subscribe_ajax' ] );
        add_action( 'wp_ajax_dps_push_unsubscribe', [ $this, 'unsubscribe_ajax' ] );
        add_action( 'wp_ajax_dps_push_test', [ $this, 'test_push_ajax' ] );
        add_action( 'wp_ajax_dps_push_test_report', [ $this, 'test_report_ajax' ] );
        add_action( 'wp_ajax_dps_push_test_telegram', [ $this, 'test_telegram_ajax' ] );

        // Hooks para enviar notificações
        add_action( 'dps_base_after_save_appointment', [ $this, 'notify_new_appointment' ], 20, 2 );
        add_action( 'dps_appointment_status_changed', [ $this, 'notify_status_change' ], 20, 4 );
        add_action( 'dps_appointment_rescheduled', [ $this, 'notify_rescheduled' ], 20, 5 );

        // Fallback: Gerar chaves VAPID se não existirem (caso ativação não tenha sido executada).
        add_action( 'admin_init', [ $this, 'maybe_generate_vapid_keys' ] );
    }

    /**
     * Gera chaves VAPID se não existirem (fallback).
     *
     * Este método garante que as chaves sejam geradas mesmo que o hook
     * de ativação não tenha sido executado corretamente.
     *
     * @since 1.3.1
     */
    public function maybe_generate_vapid_keys() {
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
     * 
     * Registra submenu sob o menu principal "desi.pet by PRObst".
     *
     * @since 1.0.0
     * @since 1.2.0 Menu agora visível sob "desi.pet by PRObst" (antes estava oculto).
     */
    public function register_admin_menu() {
        add_submenu_page(
            'desi-pet-shower', // Menu pai: desi.pet by PRObst.
            __( 'Notificações', 'dps-push-addon' ),
            __( 'Notificações', 'dps-push-addon' ),
            'manage_options',
            'dps-push-notifications',
            [ $this, 'render_admin_page' ]
        );
    }

    /**
     * Enfileira assets do admin.
     *
     * @since 1.0.0
     * @since 1.2.0 Melhorado para carregar apenas nas páginas relevantes.
     *
     * @param string $hook Hook da página atual.
     */
    public function enqueue_admin_assets( $hook ) {
        // Cast para string para compatibilidade com PHP 8.4+
        $hook = (string) $hook;

        // Carrega apenas na página de configurações do Push ou outras páginas DPS relevantes.
        $is_push_page = ( strpos( $hook, 'dps-push-notifications' ) !== false );
        $is_dps_page  = ( strpos( $hook, 'desi-pet-shower' ) !== false || strpos( $hook, 'dps-' ) !== false );

        // Carrega CSS/JS apenas na página de configurações do Push.
        // Se precisar em outras páginas, carregar apenas o botão de inscrição (minimalista).
        if ( ! $is_push_page && ! $is_dps_page ) {
            return;
        }

        $addon_url = plugin_dir_url( __FILE__ );
        $version   = '1.3.0';

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
                'subscribing'       => __( 'Ativando notificações...', 'dps-push-addon' ),
                'subscribed'        => __( 'Notificações ativadas!', 'dps-push-addon' ),
                'unsubscribed'      => __( 'Notificações desativadas.', 'dps-push-addon' ),
                'error'             => __( 'Erro ao ativar notificações.', 'dps-push-addon' ),
                'not_supported'     => __( 'Seu navegador não suporta notificações push.', 'dps-push-addon' ),
                'permission_denied' => __( 'Permissão negada. Habilite nas configurações do navegador.', 'dps-push-addon' ),
                'test_sent'         => __( 'Notificação de teste enviada!', 'dps-push-addon' ),
                'saving'            => __( 'Salvando...', 'dps-push-addon' ),
                'save_settings'     => __( 'Salvar Configurações', 'dps-push-addon' ),
                'sending'           => __( 'Enviando...', 'dps-push-addon' ),
                'testing'           => __( 'Testando...', 'dps-push-addon' ),
                'invalid_email'     => __( 'Email inválido: ', 'dps-push-addon' ),
                'invalid_token'     => __( 'Formato de token inválido. Exemplo: 123456789:ABCdefGHIjklMNOpqrSTUvwxYZ', 'dps-push-addon' ),
            ],
        ] );
    }

    /**
     * Enfileira assets no frontend (para página de agenda).
     *
     * @since 1.0.0
     */
    public function enqueue_frontend_assets() {
        // Verificar se está na página de agenda.
        global $post;
        if ( ! $post || ! has_shortcode( (string) $post->post_content, 'dps_agenda_page' ) ) {
            return;
        }

        // Somente para usuários logados com permissão.
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

        // Validar e sanitizar dados de inscrição.
        $subscription_raw = isset( $_POST['subscription'] ) ? sanitize_text_field( wp_unslash( $_POST['subscription'] ) ) : '';
        
        if ( empty( $subscription_raw ) ) {
            wp_send_json_error( [ 'message' => __( 'Dados de inscrição ausentes.', 'dps-push-addon' ) ] );
        }

        $subscription = json_decode( stripslashes( $subscription_raw ), true );

        // Verificar erro de JSON decode.
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            wp_send_json_error( [ 'message' => __( 'Dados de inscrição com formato inválido.', 'dps-push-addon' ) ] );
        }

        if ( ! is_array( $subscription ) || empty( $subscription['endpoint'] ) ) {
            wp_send_json_error( [ 'message' => __( 'Dados de inscrição inválidos.', 'dps-push-addon' ) ] );
        }

        // Validar que endpoint é uma URL válida de serviço push.
        $endpoint = esc_url_raw( $subscription['endpoint'] );
        if ( empty( $endpoint ) ) {
            wp_send_json_error( [ 'message' => __( 'Endpoint inválido.', 'dps-push-addon' ) ] );
        }

        // Verificar que o endpoint é de um serviço push conhecido.
        $allowed_hosts = [
            'fcm.googleapis.com',
            'updates.push.services.mozilla.com',
            'notify.windows.com',
            'web.push.apple.com',
        ];

        $parsed_url = wp_parse_url( $endpoint );
        $is_valid_host = false;
        foreach ( $allowed_hosts as $host ) {
            if ( isset( $parsed_url['host'] ) && ( $parsed_url['host'] === $host || str_ends_with( $parsed_url['host'], '.' . $host ) ) ) {
                $is_valid_host = true;
                break;
            }
        }

        if ( ! $is_valid_host ) {
            wp_send_json_error( [ 'message' => __( 'Serviço de push não reconhecido.', 'dps-push-addon' ) ] );
        }

        $user_id = get_current_user_id();
        
        // Armazenar inscrição do usuário
        $subscriptions = get_user_meta( $user_id, '_dps_push_subscriptions', true );
        if ( ! is_array( $subscriptions ) ) {
            $subscriptions = [];
        }

        // Evitar duplicatas baseado no endpoint
        $endpoint_hash = md5( $endpoint );
        
        // Sanitizar keys (p256dh e auth são base64url encoded)
        $keys = [];
        if ( isset( $subscription['keys'] ) && is_array( $subscription['keys'] ) ) {
            if ( isset( $subscription['keys']['p256dh'] ) ) {
                $keys['p256dh'] = preg_replace( '/[^A-Za-z0-9_-]/', '', $subscription['keys']['p256dh'] );
            }
            if ( isset( $subscription['keys']['auth'] ) ) {
                $keys['auth'] = preg_replace( '/[^A-Za-z0-9_-]/', '', $subscription['keys']['auth'] );
            }
        }

        $subscriptions[ $endpoint_hash ] = [
            'endpoint'   => $endpoint,
            'keys'       => $keys,
            'created'    => current_time( 'mysql' ),
            'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
        ];

        update_user_meta( $user_id, '_dps_push_subscriptions', $subscriptions );

        // Log (sem expor dados sensíveis).
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

        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissão negada.', 'dps-push-addon' ) ] );
        }

        $endpoint = isset( $_POST['endpoint'] ) ? esc_url_raw( wp_unslash( $_POST['endpoint'] ) ) : '';
        
        if ( empty( $endpoint ) ) {
            wp_send_json_error( [ 'message' => __( 'Endpoint inválido.', 'dps-push-addon' ) ] );
        }

        $user_id = get_current_user_id();
        $subscriptions = get_user_meta( $user_id, '_dps_push_subscriptions', true );
        
        if ( is_array( $subscriptions ) ) {
            $endpoint_hash = md5( $endpoint );
            unset( $subscriptions[ $endpoint_hash ] );
            update_user_meta( $user_id, '_dps_push_subscriptions', $subscriptions );

            // Log
            if ( class_exists( 'DPS_Logger' ) ) {
                DPS_Logger::info(
                    sprintf( 'Usuário #%d cancelou inscrição push', $user_id ),
                    [ 'endpoint_hash' => $endpoint_hash ],
                    'push'
                );
            }
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
     * AJAX: Enviar teste de relatório por email.
     *
     * @since 1.2.0
     */
    public function test_report_ajax() {
        check_ajax_referer( 'dps_push_test', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissão negada.', 'dps-push-addon' ) ] );
        }

        $type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';

        $valid_types = [ 'agenda', 'report', 'weekly' ];
        if ( ! in_array( $type, $valid_types, true ) ) {
            wp_send_json_error( [ 'message' => __( 'Tipo de relatório inválido.', 'dps-push-addon' ) ] );
        }

        $email_reports = DPS_Email_Reports::get_instance();
        $result = $email_reports->send_test( $type );

        if ( $result ) {
            wp_send_json_success( [ 
                'message' => __( 'Relatório de teste enviado com sucesso!', 'dps-push-addon' ),
            ] );
        } else {
            wp_send_json_error( [ 
                'message' => __( 'Erro ao enviar relatório de teste.', 'dps-push-addon' ),
            ] );
        }
    }

    /**
     * AJAX: Testar conexão com Telegram.
     *
     * @since 1.2.0
     */
    public function test_telegram_ajax() {
        check_ajax_referer( 'dps_push_test', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissão negada.', 'dps-push-addon' ) ] );
        }

        $token   = get_option( 'dps_push_telegram_token', '' );
        $chat_id = get_option( 'dps_push_telegram_chat', '' );

        if ( empty( $token ) || empty( $chat_id ) ) {
            wp_send_json_error( [ 
                'message' => __( 'Configure o Token do Bot e o Chat ID antes de testar.', 'dps-push-addon' ),
            ] );
        }

        // Validar formato do token (formato: 123456789:ABCdefGHI...).
        if ( ! preg_match( '/^\d{8,12}:[A-Za-z0-9_-]{30,50}$/', $token ) ) {
            wp_send_json_error( [ 
                'message' => __( 'Formato de token inválido. Verifique o token do bot.', 'dps-push-addon' ),
            ] );
        }

        // Validar chat_id (número ou número negativo para grupos).
        if ( ! preg_match( '/^-?\d+$/', $chat_id ) ) {
            wp_send_json_error( [ 
                'message' => __( 'Chat ID deve ser um número válido.', 'dps-push-addon' ),
            ] );
        }

        // Construir URL segura usando apenas o host fixo da API Telegram.
        $url = 'https://api.telegram.org/bot' . urlencode( $token ) . '/sendMessage';

        $test_message = sprintf(
            /* translators: %s: blog name */
            __( '🔔 Teste de conexão do desi.pet by PRObst (%s). Conexão funcionando!', 'dps-push-addon' ),
            get_bloginfo( 'name' )
        );

        $response = wp_remote_post( $url, [
            'body'    => [
                'chat_id'    => $chat_id,
                'text'       => $test_message,
                'parse_mode' => 'HTML',
            ],
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 
                'message' => sprintf( 
                    /* translators: %s: error message */
                    __( 'Erro de conexão: %s', 'dps-push-addon' ),
                    $response->get_error_message()
                ),
            ] );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( isset( $data['ok'] ) && $data['ok'] ) {
            wp_send_json_success( [ 
                'message' => __( 'Conexão com Telegram funcionando! Mensagem de teste enviada.', 'dps-push-addon' ),
            ] );
        } else {
            $error_desc = isset( $data['description'] ) ? sanitize_text_field( $data['description'] ) : __( 'Erro desconhecido.', 'dps-push-addon' );
            wp_send_json_error( [ 
                'message' => sprintf( 
                    /* translators: %s: error description */
                    __( 'Erro do Telegram: %s', 'dps-push-addon' ),
                    $error_desc
                ),
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

        // Verifica nonce e dá feedback adequado
        if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'dps_push_settings' ) ) {
            if ( function_exists( 'add_settings_error' ) ) {
                add_settings_error( 'dps_push', 'nonce_failed', __( 'Sessão expirada. Atualize a página e tente novamente.', 'dps-push-addon' ), 'error' );
            }
            return;
        }

        // Verifica permissão e dá feedback adequado
        if ( ! current_user_can( 'manage_options' ) ) {
            if ( function_exists( 'add_settings_error' ) ) {
                add_settings_error( 'dps_push', 'permission_denied', __( 'Você não tem permissão para alterar estas configurações.', 'dps-push-addon' ), 'error' );
            }
            return;
        }

        $settings = [
            'notify_new_appointment' => ! empty( $_POST['notify_new_appointment'] ),
            'notify_status_change'   => ! empty( $_POST['notify_status_change'] ),
            'notify_rescheduled'     => ! empty( $_POST['notify_rescheduled'] ),
        ];

        update_option( self::OPTION_KEY, $settings );

        // Salva configurações de relatórios por email.
        $emails_agenda_raw = isset( $_POST['dps_push_emails_agenda'] ) ? sanitize_textarea_field( wp_unslash( $_POST['dps_push_emails_agenda'] ) ) : '';
        $emails_report_raw = isset( $_POST['dps_push_emails_report'] ) ? sanitize_textarea_field( wp_unslash( $_POST['dps_push_emails_report'] ) ) : '';
        
        // Valida emails (lista separada por vírgula).
        $emails_agenda = $this->validate_email_list( $emails_agenda_raw );
        $emails_report = $this->validate_email_list( $emails_report_raw );
        
        // Valida horários (formato HH:MM).
        $agenda_time = $this->validate_time( isset( $_POST['dps_push_agenda_time'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_push_agenda_time'] ) ) : '08:00' );
        $report_time = $this->validate_time( isset( $_POST['dps_push_report_time'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_push_report_time'] ) ) : '19:00' );
        $weekly_time = $this->validate_time( isset( $_POST['dps_push_weekly_time'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_push_weekly_time'] ) ) : '08:00' );
        
        // Valida dia da semana (whitelist).
        $allowed_days = [ 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' ];
        $weekly_day_raw = isset( $_POST['dps_push_weekly_day'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_push_weekly_day'] ) ) : 'monday';
        $weekly_day = in_array( $weekly_day_raw, $allowed_days, true ) ? $weekly_day_raw : 'monday';
        
        $inactive_days = isset( $_POST['dps_push_inactive_days'] ) ? absint( $_POST['dps_push_inactive_days'] ) : 30;
        // Validar intervalo (mínimo 7, máximo 365 dias).
        if ( $inactive_days < 7 ) {
            $inactive_days = 7;
        } elseif ( $inactive_days > 365 ) {
            $inactive_days = 365;
        }
        $telegram_token = isset( $_POST['dps_push_telegram_token'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_push_telegram_token'] ) ) : '';
        $telegram_chat  = isset( $_POST['dps_push_telegram_chat'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_push_telegram_chat'] ) ) : '';

        update_option( 'dps_push_emails_agenda', $emails_agenda );
        update_option( 'dps_push_emails_report', $emails_report );
        update_option( 'dps_push_agenda_time', $agenda_time );
        update_option( 'dps_push_report_time', $report_time );
        update_option( 'dps_push_weekly_day', $weekly_day );
        update_option( 'dps_push_weekly_time', $weekly_time );
        update_option( 'dps_push_inactive_days', $inactive_days );
        update_option( 'dps_push_telegram_token', $telegram_token );
        update_option( 'dps_push_telegram_chat', $telegram_chat );

        update_option( 'dps_push_agenda_enabled', ! empty( $_POST['dps_push_agenda_enabled'] ) );
        update_option( 'dps_push_report_enabled', ! empty( $_POST['dps_push_report_enabled'] ) );
        update_option( 'dps_push_weekly_enabled', ! empty( $_POST['dps_push_weekly_enabled'] ) );

        if ( function_exists( 'add_settings_error' ) ) {
            add_settings_error( 'dps_push', 'settings_saved', __( 'Configurações salvas com sucesso.', 'dps-push-addon' ), 'success' );
        }
    }

    /**
     * Valida e filtra lista de emails separados por vírgula.
     *
     * @param string $input Lista de emails.
     * @return string Lista de emails válidos.
     */
    private function validate_email_list( $input ) {
        if ( empty( $input ) ) {
            return '';
        }
        $emails = array_map( 'trim', explode( ',', $input ) );
        $valid_emails = array_filter( $emails, 'is_email' );
        return implode( ', ', $valid_emails );
    }

    /**
     * Valida horário no formato HH:MM.
     *
     * @param string $time Horário.
     * @return string Horário válido ou padrão.
     */
    private function validate_time( $time ) {
        if ( preg_match( '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time ) ) {
            return $time;
        }
        return '08:00';
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

        // Configurações de relatórios por email.
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

        // Próximos envios agendados.
        $next_agenda = wp_next_scheduled( 'dps_send_agenda_notification' );
        $next_report = wp_next_scheduled( 'dps_send_daily_report' );
        $next_weekly = wp_next_scheduled( 'dps_send_weekly_inactive_report' );

        // Status do Telegram.
        $telegram_configured = ! empty( $telegram_token ) && ! empty( $telegram_chat );

        // Formatar emails para exibição no campo (converter array para string se necessário).
        $emails_agenda_display = is_array( $emails_agenda ) ? implode( ', ', $emails_agenda ) : $emails_agenda;
        $emails_report_display = is_array( $emails_report ) ? implode( ', ', $emails_report ) : $emails_report;

        ?>
        <div class="wrap dps-push-settings">
            <h1 class="dps-section-title">
                <span class="dps-section-title__icon">🔔</span>
                <?php echo esc_html__( 'Notificações e Relatórios', 'dps-push-addon' ); ?>
            </h1>
            <p class="dps-section-header__subtitle"><?php echo esc_html__( 'Configure notificações push do navegador, relatórios automáticos por email e integração com Telegram.', 'dps-push-addon' ); ?></p>

            <?php settings_errors( 'dps_push' ); ?>

            <form method="post" id="dps-push-settings-form">
                <?php wp_nonce_field( 'dps_push_settings' ); ?>

                <div class="dps-push-stacked">

                    <!-- Card: Notificações Push do Navegador -->
                    <div class="dps-surface dps-surface--info">
                        <div class="dps-surface__title">
                            <span>🖥️</span>
                            <?php echo esc_html__( 'Notificações Push do Navegador', 'dps-push-addon' ); ?>
                        </div>
                        <p class="dps-surface__description"><?php echo esc_html__( 'Receba alertas em tempo real diretamente no seu navegador, mesmo quando estiver em outra aba ou com o navegador minimizado.', 'dps-push-addon' ); ?></p>

                        <div class="dps-push-browser-section">
                            <div class="dps-push-status-row">
                                <div id="dps-push-status-indicator" class="dps-push-indicator dps-push-checking">
                                    <span class="dps-push-dot"></span>
                                    <span class="dps-push-status-text"><?php echo esc_html__( 'Verificando...', 'dps-push-addon' ); ?></span>
                                </div>
                                <span class="dps-push-devices">
                                    <?php 
                                    printf( 
                                        esc_html__( '(%d dispositivo(s) inscrito(s))', 'dps-push-addon' ), 
                                        $sub_count 
                                    ); 
                                    ?>
                                </span>
                            </div>

                            <div class="dps-push-actions">
                                <button type="button" id="dps-push-subscribe" class="button button-primary">
                                    <?php echo esc_html__( 'Ativar Notificações neste Dispositivo', 'dps-push-addon' ); ?>
                                </button>
                                <button type="button" id="dps-push-test" class="button" style="display: none;">
                                    <?php echo esc_html__( 'Enviar Notificação de Teste', 'dps-push-addon' ); ?>
                                </button>
                            </div>

                            <fieldset class="dps-push-events-fieldset">
                                <legend><?php echo esc_html__( 'Eventos que disparam notificações push:', 'dps-push-addon' ); ?></legend>
                                <label>
                                    <input type="checkbox" name="notify_new_appointment" value="1" 
                                           <?php checked( ! empty( $settings['notify_new_appointment'] ) ); ?>>
                                    <?php echo esc_html__( 'Novo agendamento criado', 'dps-push-addon' ); ?>
                                </label>
                                <label>
                                    <input type="checkbox" name="notify_status_change" value="1"
                                           <?php checked( ! empty( $settings['notify_status_change'] ) ); ?>>
                                    <?php echo esc_html__( 'Alteração de status do agendamento', 'dps-push-addon' ); ?>
                                </label>
                                <label>
                                    <input type="checkbox" name="notify_rescheduled" value="1"
                                           <?php checked( ! empty( $settings['notify_rescheduled'] ) ); ?>>
                                    <?php echo esc_html__( 'Agendamento reagendado', 'dps-push-addon' ); ?>
                                </label>
                            </fieldset>

                            <p class="description" style="margin-top: 16px;">
                                <?php echo esc_html__( 'Nota: Requer HTTPS e navegador compatível (Chrome, Firefox, Edge, Safari 16+). Ative em cada dispositivo que deseja receber notificações.', 'dps-push-addon' ); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Card: Relatório da Manhã (Agenda do Dia) -->
                    <div class="dps-surface dps-surface--neutral">
                        <div class="dps-surface__title">
                            <span>☀️</span>
                            <?php echo esc_html__( 'Relatório da Manhã – Agenda do Dia', 'dps-push-addon' ); ?>
                        </div>
                        <p class="dps-surface__description"><?php echo esc_html__( 'Receba no início do dia um resumo com todos os agendamentos programados, incluindo horários, pets e serviços.', 'dps-push-addon' ); ?></p>

                        <div class="dps-push-report-config">
                            <label class="dps-push-toggle-label">
                                <input type="checkbox" name="dps_push_agenda_enabled" value="1" <?php checked( $agenda_enabled ); ?>>
                                <strong><?php echo esc_html__( 'Ativar relatório da manhã', 'dps-push-addon' ); ?></strong>
                            </label>

                            <div class="dps-push-report-fields">
                                <div class="dps-push-field-row">
                                    <label for="dps_push_agenda_time"><?php echo esc_html__( 'Horário de envio:', 'dps-push-addon' ); ?></label>
                                    <input type="time" id="dps_push_agenda_time" name="dps_push_agenda_time" value="<?php echo esc_attr( $agenda_time ); ?>">
                                    <?php if ( $agenda_enabled && $next_agenda ) : ?>
                                        <span class="dps-schedule-badge dps-schedule-active">✓ <?php echo esc_html__( 'Próximo:', 'dps-push-addon' ); ?> <?php echo esc_html( date_i18n( 'd/m H:i', $next_agenda ) ); ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="dps-push-field-row">
                                    <label for="dps_push_emails_agenda"><?php echo esc_html__( 'Destinatários:', 'dps-push-addon' ); ?></label>
                                    <input type="text" id="dps_push_emails_agenda" name="dps_push_emails_agenda" class="regular-text" placeholder="email1@exemplo.com, email2@exemplo.com" value="<?php echo esc_attr( $emails_agenda_display ); ?>">
                                    <p class="description"><?php echo esc_html__( 'Separe múltiplos emails por vírgula. Deixe em branco para usar o email do administrador.', 'dps-push-addon' ); ?></p>
                                </div>

                                <button type="button" class="button dps-test-report-btn" data-type="agenda">
                                    📤 <?php echo esc_html__( 'Enviar Teste Agora', 'dps-push-addon' ); ?>
                                </button>
                                <span class="dps-test-result" data-type="agenda"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Card: Relatório do Final do Dia (Financeiro) -->
                    <div class="dps-surface dps-surface--neutral">
                        <div class="dps-surface__title">
                            <span>🌙</span>
                            <?php echo esc_html__( 'Relatório do Final do Dia – Resumo Financeiro', 'dps-push-addon' ); ?>
                        </div>
                        <p class="dps-surface__description"><?php echo esc_html__( 'Receba no final do expediente um balanço com receitas, despesas e atendimentos realizados no dia.', 'dps-push-addon' ); ?></p>

                        <div class="dps-push-report-config">
                            <label class="dps-push-toggle-label">
                                <input type="checkbox" name="dps_push_report_enabled" value="1" <?php checked( $report_enabled ); ?>>
                                <strong><?php echo esc_html__( 'Ativar relatório do final do dia', 'dps-push-addon' ); ?></strong>
                            </label>

                            <div class="dps-push-report-fields">
                                <div class="dps-push-field-row">
                                    <label for="dps_push_report_time"><?php echo esc_html__( 'Horário de envio:', 'dps-push-addon' ); ?></label>
                                    <input type="time" id="dps_push_report_time" name="dps_push_report_time" value="<?php echo esc_attr( $report_time ); ?>">
                                    <?php if ( $report_enabled && $next_report ) : ?>
                                        <span class="dps-schedule-badge dps-schedule-active">✓ <?php echo esc_html__( 'Próximo:', 'dps-push-addon' ); ?> <?php echo esc_html( date_i18n( 'd/m H:i', $next_report ) ); ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="dps-push-field-row">
                                    <label for="dps_push_emails_report"><?php echo esc_html__( 'Destinatários:', 'dps-push-addon' ); ?></label>
                                    <input type="text" id="dps_push_emails_report" name="dps_push_emails_report" class="regular-text" placeholder="email1@exemplo.com, email2@exemplo.com" value="<?php echo esc_attr( $emails_report_display ); ?>">
                                    <p class="description"><?php echo esc_html__( 'Separe múltiplos emails por vírgula. Deixe em branco para usar o email do administrador.', 'dps-push-addon' ); ?></p>
                                </div>

                                <button type="button" class="button dps-test-report-btn" data-type="report">
                                    📤 <?php echo esc_html__( 'Enviar Teste Agora', 'dps-push-addon' ); ?>
                                </button>
                                <span class="dps-test-result" data-type="report"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Card: Relatório Semanal (Pets Inativos) -->
                    <div class="dps-surface dps-surface--neutral">
                        <div class="dps-surface__title">
                            <span>🐾</span>
                            <?php echo esc_html__( 'Relatório Semanal – Pets Inativos', 'dps-push-addon' ); ?>
                        </div>
                        <p class="dps-surface__description"><?php echo esc_html__( 'Receba semanalmente uma lista de pets que não foram atendidos há muito tempo, ideal para ações de reengajamento.', 'dps-push-addon' ); ?></p>

                        <div class="dps-push-report-config">
                            <label class="dps-push-toggle-label">
                                <input type="checkbox" name="dps_push_weekly_enabled" value="1" <?php checked( $weekly_enabled ); ?>>
                                <strong><?php echo esc_html__( 'Ativar relatório semanal', 'dps-push-addon' ); ?></strong>
                            </label>

                            <div class="dps-push-report-fields">
                                <div class="dps-push-field-row">
                                    <label for="dps_push_weekly_day"><?php echo esc_html__( 'Dia da semana:', 'dps-push-addon' ); ?></label>
                                    <select id="dps_push_weekly_day" name="dps_push_weekly_day">
                                        <option value="monday" <?php selected( $weekly_day, 'monday' ); ?>><?php echo esc_html__( 'Segunda-feira', 'dps-push-addon' ); ?></option>
                                        <option value="tuesday" <?php selected( $weekly_day, 'tuesday' ); ?>><?php echo esc_html__( 'Terça-feira', 'dps-push-addon' ); ?></option>
                                        <option value="wednesday" <?php selected( $weekly_day, 'wednesday' ); ?>><?php echo esc_html__( 'Quarta-feira', 'dps-push-addon' ); ?></option>
                                        <option value="thursday" <?php selected( $weekly_day, 'thursday' ); ?>><?php echo esc_html__( 'Quinta-feira', 'dps-push-addon' ); ?></option>
                                        <option value="friday" <?php selected( $weekly_day, 'friday' ); ?>><?php echo esc_html__( 'Sexta-feira', 'dps-push-addon' ); ?></option>
                                        <option value="saturday" <?php selected( $weekly_day, 'saturday' ); ?>><?php echo esc_html__( 'Sábado', 'dps-push-addon' ); ?></option>
                                        <option value="sunday" <?php selected( $weekly_day, 'sunday' ); ?>><?php echo esc_html__( 'Domingo', 'dps-push-addon' ); ?></option>
                                    </select>
                                    <input type="time" id="dps_push_weekly_time" name="dps_push_weekly_time" value="<?php echo esc_attr( $weekly_time ); ?>">
                                    <?php if ( $weekly_enabled && $next_weekly ) : ?>
                                        <span class="dps-schedule-badge dps-schedule-active">✓ <?php echo esc_html__( 'Próximo:', 'dps-push-addon' ); ?> <?php echo esc_html( date_i18n( 'd/m H:i', $next_weekly ) ); ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="dps-push-field-row">
                                    <label for="dps_push_inactive_days"><?php echo esc_html__( 'Considerar inativo após:', 'dps-push-addon' ); ?></label>
                                    <input type="number" id="dps_push_inactive_days" name="dps_push_inactive_days" value="<?php echo esc_attr( $inactive_days ); ?>" min="7" max="365" style="width: 80px;">
                                    <span><?php echo esc_html__( 'dias sem atendimento', 'dps-push-addon' ); ?></span>
                                </div>

                                <button type="button" class="button dps-test-report-btn" data-type="weekly">
                                    📤 <?php echo esc_html__( 'Enviar Teste Agora', 'dps-push-addon' ); ?>
                                </button>
                                <span class="dps-test-result" data-type="weekly"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Card: Integração Telegram -->
                    <div class="dps-surface dps-surface--info">
                        <div class="dps-surface__title">
                            <span>📱</span>
                            <?php echo esc_html__( 'Integração com Telegram', 'dps-push-addon' ); ?>
                        </div>
                        <p class="dps-surface__description"><?php echo esc_html__( 'Receba os relatórios também via Telegram. Configure um bot e informe o Chat ID para envio automático.', 'dps-push-addon' ); ?></p>

                        <div class="dps-push-telegram-config">
                            <div class="dps-push-field-row">
                                <label for="dps_push_telegram_token"><?php echo esc_html__( 'Token do Bot:', 'dps-push-addon' ); ?></label>
                                <div class="dps-telegram-token-wrapper">
                                    <input type="password" id="dps_push_telegram_token" name="dps_push_telegram_token" value="<?php echo esc_attr( $telegram_token ); ?>" class="regular-text" placeholder="123456789:ABCdefGHIjklMNOpqrSTUvwxYZ" autocomplete="off">
                                    <button type="button" id="dps-toggle-token" class="button" aria-label="<?php echo esc_attr__( 'Mostrar/ocultar token', 'dps-push-addon' ); ?>">👁️</button>
                                </div>
                                <p class="description"><?php echo esc_html__( 'Crie um bot via @BotFather no Telegram para obter o token.', 'dps-push-addon' ); ?></p>
                            </div>

                            <div class="dps-push-field-row">
                                <label for="dps_push_telegram_chat"><?php echo esc_html__( 'Chat ID:', 'dps-push-addon' ); ?></label>
                                <input type="text" id="dps_push_telegram_chat" name="dps_push_telegram_chat" value="<?php echo esc_attr( $telegram_chat ); ?>" class="regular-text" placeholder="-1001234567890">
                                <p class="description"><?php echo esc_html__( 'ID do chat ou grupo. Use @userinfobot para descobrir o seu.', 'dps-push-addon' ); ?></p>
                            </div>

                            <div class="dps-push-telegram-status">
                                <?php if ( $telegram_configured ) : ?>
                                    <span class="dps-status-badge dps-status-badge--success">✓ <?php echo esc_html__( 'Configurado', 'dps-push-addon' ); ?></span>
                                <?php else : ?>
                                    <span class="dps-status-badge dps-status-badge--pending"><?php echo esc_html__( 'Não configurado', 'dps-push-addon' ); ?></span>
                                <?php endif; ?>
                                <button type="button" id="dps-test-telegram" class="button">
                                    🔗 <?php echo esc_html__( 'Testar Conexão', 'dps-push-addon' ); ?>
                                </button>
                                <span id="dps-telegram-result" class="dps-test-result"></span>
                            </div>
                        </div>
                    </div>

                </div><!-- .dps-push-stacked -->

                <p class="submit" style="margin-top: 24px;">
                    <button type="submit" name="dps_push_save" id="dps-push-save-btn" class="button button-primary button-hero">
                        💾 <?php echo esc_html__( 'Salvar Todas as Configurações', 'dps-push-addon' ); ?>
                    </button>
                    <span id="dps-push-save-spinner" class="spinner" style="float: none; vertical-align: middle;"></span>
                </p>
            </form>

        </div>
        <?php
    }
}

/**
 * Inicializa o Push Add-on.
 */
function dps_push_init_addon() {
    if ( class_exists( 'DPS_Push_Addon' ) ) {
        DPS_Push_Addon::get_instance();
    }
    // Inicializa os relatórios por email.
    if ( class_exists( 'DPS_Email_Reports' ) ) {
        try {
            DPS_Email_Reports::get_instance();
        } catch ( Exception $e ) {
            if ( class_exists( 'DPS_Logger' ) ) {
                DPS_Logger::error( 'Erro ao inicializar DPS_Email_Reports: ' . $e->getMessage(), [], 'push' );
            }
        }
    }
}
add_action( 'init', 'dps_push_init_addon', 5 );
