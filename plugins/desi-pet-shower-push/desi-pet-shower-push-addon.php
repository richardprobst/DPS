<?php
/**
 * Plugin Name:       desi.pet by PRObst – Push Notifications Add-on
 * Plugin URI:        https://www.probst.pro
 * Description:       Notificações push e relatórios por email para administradores e equipe. Receba alertas em tempo real e relatórios diários/semanais automáticos.
 * Version:           2.0.0
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

// Carrega classes do add-on.
require_once __DIR__ . '/includes/class-dps-push-api.php';
require_once __DIR__ . '/includes/class-dps-push-telegram.php';
require_once __DIR__ . '/includes/class-dps-push-notifications.php';
require_once __DIR__ . '/includes/class-dps-push-settings.php';
require_once __DIR__ . '/includes/class-dps-push-admin.php';
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
        if ( $keys ) {
            update_option( 'dps_push_vapid_keys', $keys );
        }
    }

    // Agendar crons de relatórios.
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
 * Orquestra as dependências e conecta os módulos do add-on
 * (admin, settings, notifications, telegram, email reports).
 *
 * @since 1.0.0
 * @since 2.0.0 Refatorado para arquitetura modular.
 */
class DPS_Push_Addon {

    /**
     * Chave da option para configurações de notificação.
     *
     * @var string
     */
    const OPTION_KEY = 'dps_push_settings';

    /**
     * Chave da option para chaves VAPID.
     *
     * @var string
     */
    const VAPID_KEY = 'dps_push_vapid_keys';

    /**
     * Instância única (singleton).
     *
     * @var DPS_Push_Addon|null
     */
    private static $instance = null;

    /**
     * Módulo admin.
     *
     * @var DPS_Push_Admin
     */
    private $admin;

    /**
     * Módulo de configurações.
     *
     * @var DPS_Push_Settings
     */
    private $settings;

    /**
     * Módulo de notificações.
     *
     * @var DPS_Push_Notifications
     */
    private $notifications;

    /**
     * Módulo Telegram.
     *
     * @var DPS_Push_Telegram
     */
    private $telegram;

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

    /** Impede clonagem do singleton. */
    private function __clone() {}

    /** Impede desserialização do singleton. */
    public function __wakeup() {
        throw new \RuntimeException( 'Não é possível desserializar singleton.' );
    }

    /**
     * Construtor — inicializa módulos e registra hooks.
     */
    private function __construct() {
        $this->admin         = new DPS_Push_Admin();
        $this->settings      = new DPS_Push_Settings();
        $this->notifications = new DPS_Push_Notifications();
        $this->telegram      = new DPS_Push_Telegram();

        // Admin menu e assets.
        add_action( 'admin_menu', [ $this->admin, 'register_admin_menu' ], 20 );
        add_action( 'admin_enqueue_scripts', [ $this->admin, 'enqueue_admin_assets' ] );
        add_action( 'wp_enqueue_scripts', [ $this->admin, 'enqueue_frontend_assets' ] );

        // Configurações (save).
        add_action( 'admin_init', [ $this->settings, 'maybe_handle_save' ] );

        // Notificações push (hooks de eventos).
        $this->notifications->register_hooks();

        // AJAX handlers.
        add_action( 'wp_ajax_dps_push_subscribe', [ $this, 'subscribe_ajax' ] );
        add_action( 'wp_ajax_dps_push_unsubscribe', [ $this, 'unsubscribe_ajax' ] );
        add_action( 'wp_ajax_dps_push_test', [ $this, 'test_push_ajax' ] );
        add_action( 'wp_ajax_dps_push_test_report', [ $this, 'test_report_ajax' ] );
        add_action( 'wp_ajax_dps_push_test_telegram', [ $this, 'test_telegram_ajax' ] );

        // Fallback: gerar chaves VAPID se não existirem.
        add_action( 'admin_init', [ $this, 'maybe_generate_vapid_keys' ] );
    }

    /**
     * Gera chaves VAPID se não existirem (fallback).
     *
     * @since 1.3.1
     */
    public function maybe_generate_vapid_keys() {
        $existing = get_option( self::VAPID_KEY );
        if ( $existing && ! empty( $existing['public'] ) && ! empty( $existing['private'] ) ) {
            return;
        }

        $keys = DPS_Push_API::generate_vapid_keys();
        if ( $keys ) {
            update_option( self::VAPID_KEY, $keys );
        }
    }

    /**
     * Renderiza a página de administração do Push.
     *
     * Proxy para o módulo admin (DPS_Push_Admin).
     *
     * @since 2.0.1
     */
    public function render_admin_page() {
        $this->admin->render_admin_page();
    }

    // ------------------------------------------------------------------
    // AJAX Handlers
    // ------------------------------------------------------------------

    /**
     * AJAX: Inscrever para notificações push.
     *
     * @since 1.0.0
     */
    public function subscribe_ajax() {
        check_ajax_referer( 'dps_push_subscribe', 'nonce' );

        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissão negada.', 'dps-push-addon' ) ] );
        }

        $subscription_raw = isset( $_POST['subscription'] ) ? sanitize_text_field( wp_unslash( $_POST['subscription'] ) ) : '';

        if ( empty( $subscription_raw ) ) {
            wp_send_json_error( [ 'message' => __( 'Dados de inscrição ausentes.', 'dps-push-addon' ) ] );
        }

        $subscription = json_decode( stripslashes( $subscription_raw ), true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            wp_send_json_error( [ 'message' => __( 'Dados de inscrição com formato inválido.', 'dps-push-addon' ) ] );
        }

        if ( ! is_array( $subscription ) || empty( $subscription['endpoint'] ) ) {
            wp_send_json_error( [ 'message' => __( 'Dados de inscrição inválidos.', 'dps-push-addon' ) ] );
        }

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

        $parsed_url    = wp_parse_url( $endpoint );
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

        $subscriptions = get_user_meta( $user_id, '_dps_push_subscriptions', true );
        if ( ! is_array( $subscriptions ) ) {
            $subscriptions = [];
        }

        $endpoint_hash = md5( $endpoint );

        // Sanitizar keys (p256dh e auth são base64url encoded).
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
     *
     * @since 1.0.0
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

        $user_id       = get_current_user_id();
        $subscriptions = get_user_meta( $user_id, '_dps_push_subscriptions', true );

        if ( is_array( $subscriptions ) ) {
            $endpoint_hash = md5( $endpoint );
            unset( $subscriptions[ $endpoint_hash ] );
            update_user_meta( $user_id, '_dps_push_subscriptions', $subscriptions );

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
     *
     * @since 1.0.0
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
        $result        = $email_reports->send_test( $type );

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

        $result = $this->telegram->test_connection();

        if ( $result['success'] ) {
            wp_send_json_success( [ 'message' => $result['message'] ] );
        } else {
            wp_send_json_error( [ 'message' => $result['message'] ] );
        }
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
