<?php
/**
 * Plugin Name:       desi.pet by PRObst - Booking Add-on
 * Plugin URI:        https://www.probst.pro
 * Description:       Página dedicada de agendamento para a operação DPS, reaproveitando o renderer canônico do núcleo.
 * Version:           1.4.1
 * Author:            PRObst
 * Author URI:        https://www.probst.pro
 * Text Domain:       dps-booking-addon
 * Domain Path:       /languages
 * Requires at least: 6.9
 * Requires PHP:      8.4
 * Update URI:        https://github.com/richardprobst/DPS
 * License:           GPL-2.0+
 *
 * @package DesiPetShowerBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Verifica se o plugin base está ativo antes de inicializar o add-on.
 *
 * @return bool
 */
function dps_booking_check_base_plugin() {
    if ( ! class_exists( 'DPS_Base_Plugin' ) ) {
        add_action(
            'admin_notices',
            function() {
                echo '<div class="notice notice-error"><p>';
                echo esc_html__( 'O add-on requer o plugin base desi.pet by PRObst para funcionar.', 'dps-booking-addon' );
                echo '</p></div>';
            }
        );
        return false;
    }

    return true;
}

add_action(
    'plugins_loaded',
    function() {
        dps_booking_check_base_plugin();
    },
    1
);

/**
 * Carrega o text domain antes da inicialização da classe principal.
 */
function dps_booking_load_textdomain() {
    load_plugin_textdomain( 'dps-booking-addon', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'dps_booking_load_textdomain', 1 );

/**
 * Classe principal do Booking Add-on.
 */
class DPS_Booking_Addon {

    /**
     * Versão do add-on.
     */
    const VERSION = '1.4.1';

    /**
     * Instância única.
     *
     * @var DPS_Booking_Addon|null
     */
    private static $instance = null;

    /**
     * Shortcodes públicos do add-on.
     *
     * @var array<int,string>
     */
    private $shortcodes = [ 'dps_booking_form', 'dps_booking_v2' ];

    /**
     * Recupera a instância única.
     *
     * @return DPS_Booking_Addon
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
        foreach ( $this->shortcodes as $shortcode ) {
            add_shortcode( $shortcode, [ $this, 'render_booking_form' ] );
        }

        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_filter( 'dps_base_appointment_redirect_url', [ $this, 'filter_booking_redirect_url' ], 10, 6 );

        if ( class_exists( 'DPS_Cache_Control' ) ) {
            foreach ( $this->shortcodes as $shortcode ) {
                DPS_Cache_Control::register_shortcode( $shortcode );
            }
        }
    }

    /**
     * Callback estático de ativação.
     */
    public static function activate_plugin() {
        self::get_instance()->activate();
    }

    /**
     * Cria ou registra a página padrão de agendamento.
     */
    public function activate() {
        $existing_id = $this->find_booking_page_id();
        if ( $existing_id ) {
            update_option( 'dps_booking_page_id', $existing_id );
            return;
        }

        $title = __( 'Agendamento', 'dps-booking-addon' );
        $page_id = wp_insert_post(
            [
                'post_title'   => $title,
                'post_name'    => 'agendamento',
                'post_content' => '[dps_booking_form]',
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ]
        );

        if ( $page_id && ! is_wp_error( $page_id ) ) {
            update_option( 'dps_booking_page_id', (int) $page_id );
        }
    }

    /**
     * Enfileira os assets do add-on somente na página de Booking.
     */
    public function enqueue_assets() {
        if ( ! $this->is_booking_screen() ) {
            return;
        }

        if ( class_exists( 'DPS_Base_Plugin' ) && method_exists( 'DPS_Base_Plugin', 'enqueue_frontend_assets' ) ) {
            DPS_Base_Plugin::enqueue_frontend_assets();
        }

        $addon_url = plugin_dir_url( __FILE__ );
        $deps      = [ 'dps-base-style' ];

        if ( defined( 'DPS_BASE_URL' ) && defined( 'DPS_BASE_VERSION' ) ) {
            wp_enqueue_style(
                'dps-design-tokens',
                DPS_BASE_URL . 'assets/css/dps-design-tokens.css',
                [],
                DPS_BASE_VERSION
            );
            $deps[] = 'dps-design-tokens';
        }

        wp_enqueue_style(
            'dps-booking-addon',
            $addon_url . 'assets/css/booking-addon.css',
            $deps,
            self::VERSION
        );
    }

    /**
     * Renderiza o shortcode principal.
     *
     * @return string
     */
    public function render_booking_form() {
        if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || wp_doing_ajax() ) {
            return '';
        }

        if ( class_exists( 'DPS_Cache_Control' ) ) {
            DPS_Cache_Control::force_no_cache();
        }

        if ( ! is_user_logged_in() ) {
            return $this->render_access_message(
                __( 'Acesso autenticado necessário', 'dps-booking-addon' ),
                __( 'Entre com uma conta autorizada para carregar a página de agendamento.', 'dps-booking-addon' ),
                wp_login_url( $this->get_current_page_url() ),
                __( 'Entrar', 'dps-booking-addon' )
            );
        }

        if ( ! $this->can_access() ) {
            return $this->render_access_message(
                __( 'Acesso restrito', 'dps-booking-addon' ),
                __( 'Esta área é restrita a administradores e usuários autorizados do DPS.', 'dps-booking-addon' )
            );
        }

        if ( ! $this->can_manage_bookings() ) {
            return $this->render_access_message(
                __( 'Permissão insuficiente', 'dps-booking-addon' ),
                __( 'A sua conta acessa o painel, mas não possui permissão para gerenciar agendamentos.', 'dps-booking-addon' )
            );
        }

        $confirmed_appointment_id = $this->get_confirmed_appointment_id_from_request();

        ob_start();
        echo '<div class="dps-booking-wrapper dps-booking-signature">';
        echo '<header class="dps-booking-header">';
        echo '<p class="dps-booking-kicker">' . esc_html__( 'Operação DPS', 'dps-booking-addon' ) . '</p>';
        echo '<h1>' . esc_html__( 'Agendamento', 'dps-booking-addon' ) . '</h1>';
        echo '<p>' . esc_html__( 'Banho, tosa, TaxiDog e assinatura em uma tela operacional.', 'dps-booking-addon' ) . '</p>';
        echo '</header>';

        if ( class_exists( 'DPS_Message_Helper' ) ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- DPS_Message_Helper::display_messages() retorna HTML já escapado pelo helper.
            echo DPS_Message_Helper::display_messages();
        }

        if ( $confirmed_appointment_id ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_confirmation_page() retorna HTML escapado.
            echo $this->render_confirmation_page( $confirmed_appointment_id );
        } elseif ( class_exists( 'DPS_Appointments_Section_Renderer' ) ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_appointments_section() retorna HTML escapado pelo renderer base.
            echo $this->render_appointments_section();
        } else {
            echo '<div class="dps-booking-notice dps-booking-notice--error">';
            echo '<h2>' . esc_html__( 'Renderer indisponível', 'dps-booking-addon' ) . '</h2>';
            echo '<p>' . esc_html__( 'O plugin base não carregou o renderer de agendamentos.', 'dps-booking-addon' ) . '</p>';
            echo '</div>';
        }

        echo '</div>';

        return ob_get_clean();
    }

    /**
     * Renderiza o formulário canônico de agendamento no contexto do Booking.
     *
     * @return string
     */
    private function render_appointments_section() {
        $edit_id      = $this->get_requested_appointment_id( 'dps_edit' );
        $duplicate_id = $this->get_requested_appointment_id( 'dps_duplicate' );

        if ( $edit_id && ! $this->can_edit_appointment( $edit_id ) ) {
            return $this->render_access_message(
                __( 'Edição bloqueada', 'dps-booking-addon' ),
                __( 'A sua conta não possui permissão para editar este agendamento.', 'dps-booking-addon' )
            );
        }

        if ( $duplicate_id && ! $this->can_edit_appointment( $duplicate_id ) ) {
            return $this->render_access_message(
                __( 'Duplicação bloqueada', 'dps-booking-addon' ),
                __( 'A sua conta não possui permissão para duplicar este agendamento.', 'dps-booking-addon' )
            );
        }

        $pref_client = $this->get_int_query_arg( [ 'pref_client', 'client_id' ] );
        $pref_pet    = $this->get_int_query_arg( [ 'pref_pet', 'pet_id' ] );
        $base_url    = $this->get_booking_page_url();

        $data = DPS_Appointments_Section_Renderer::prepare_data(
            false,
            [
                'pref_client' => $pref_client,
                'pref_pet'    => $pref_pet,
                'base_url'    => $base_url,
                'current_url' => $base_url,
            ]
        );

        return DPS_Appointments_Section_Renderer::render_section(
            $data,
            false,
            [
                'context'        => 'booking',
                'include_list'   => false,
                'section_id'     => 'dps-section-booking-agendamento',
                'section_classes' => [ 'dps-section--booking' ],
                'section_title'  => '',
                'surface_title'  => $edit_id ? __( 'Editar agendamento', 'dps-booking-addon' ) : __( 'Novo agendamento', 'dps-booking-addon' ),
                'hidden_fields'  => [
                    'dps_booking_context' => '1',
                ],
            ]
        );
    }

    /**
     * Ajusta a URL pós-save para a página de confirmação do Booking.
     *
     * @param string $redirect       URL calculada pelo núcleo.
     * @param array  $result         Dados retornados pelo handler.
     * @param int    $client_id      ID do cliente.
     * @param string $tab            Aba de destino do núcleo.
     * @param string $context        Contexto de execução.
     * @param array  $pending_notice Dados de pendências.
     * @return string
     */
    public function filter_booking_redirect_url( $redirect, $result, $client_id, $tab, $context, $pending_notice ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
        if ( ! $this->is_booking_submission( $redirect ) ) {
            return $redirect;
        }

        $appointment_id = 0;
        if ( ! empty( $result['appointment_id'] ) ) {
            $appointment_id = (int) $result['appointment_id'];
        } elseif ( ! empty( $result['appointment_ids'] ) && is_array( $result['appointment_ids'] ) ) {
            $appointment_id = (int) reset( $result['appointment_ids'] );
        }

        if ( ! $appointment_id ) {
            return $redirect;
        }

        $clean_redirect = remove_query_arg(
            [ 'tab', 'dps_edit', 'dps_duplicate', 'id', 'dps_booking_confirmed', 'dps_booking_nonce', 'dps_booking_type' ],
            $redirect
        );

        return add_query_arg(
            [
                'dps_booking_confirmed' => $appointment_id,
                'dps_booking_nonce'     => wp_create_nonce( 'dps_booking_confirmation_' . $appointment_id . '_' . get_current_user_id() ),
                'dps_booking_type'      => ! empty( $result['appointment_type'] ) ? sanitize_key( $result['appointment_type'] ) : 'simple',
            ],
            $clean_redirect
        );
    }

    /**
     * Método legado mantido sem persistência temporária.
     *
     * @param int    $appointment_id   ID do agendamento salvo.
     * @param string $appointment_type Tipo do agendamento.
     * @return void
     */
    public function capture_saved_appointment( $appointment_id, $appointment_type ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
        return;
    }

    /**
     * Renderiza a confirmação de agendamento assinada por nonce.
     *
     * @param int $appointment_id ID do agendamento.
     * @return string
     */
    private function render_confirmation_page( $appointment_id ) {
        $appointment = get_post( $appointment_id );
        if ( ! $appointment || 'dps_agendamento' !== $appointment->post_type ) {
            return $this->render_access_message(
                __( 'Agendamento indisponível', 'dps-booking-addon' ),
                __( 'Não foi possível carregar os detalhes do agendamento confirmado.', 'dps-booking-addon' ),
                $this->get_booking_page_url(),
                __( 'Criar novo agendamento', 'dps-booking-addon' )
            );
        }

        $client_id   = (int) get_post_meta( $appointment_id, 'appointment_client_id', true );
        $pet_id      = (int) get_post_meta( $appointment_id, 'appointment_pet_id', true );
        $pet_ids     = get_post_meta( $appointment_id, 'appointment_pet_ids', true );
        $date        = get_post_meta( $appointment_id, 'appointment_date', true );
        $time        = get_post_meta( $appointment_id, 'appointment_time', true );
        $type        = get_post_meta( $appointment_id, 'appointment_type', true );
        $notes       = get_post_meta( $appointment_id, 'appointment_notes', true );
        $status      = get_post_meta( $appointment_id, 'appointment_status', true );
        $total_value = (float) get_post_meta( $appointment_id, 'appointment_total_value', true );

        if ( empty( $pet_ids ) && $pet_id ) {
            $pet_ids = [ $pet_id ];
        }
        $pet_ids = is_array( $pet_ids ) ? array_map( 'absint', $pet_ids ) : [];

        $client_name = $client_id ? get_the_title( $client_id ) : __( 'Cliente não encontrado', 'dps-booking-addon' );
        $pet_names   = [];
        foreach ( $pet_ids as $current_pet_id ) {
            $pet_title = get_the_title( $current_pet_id );
            if ( $pet_title ) {
                $pet_names[] = $pet_title;
            }
        }

        $date_obj       = $date ? DateTime::createFromFormat( 'Y-m-d', $date ) : false;
        $date_formatted = $date_obj ? $date_obj->format( 'd/m/Y' ) : $date;
        $type_labels    = [
            'simple'       => __( 'Agendamento simples', 'dps-booking-addon' ),
            'subscription' => __( 'Agendamento de assinatura', 'dps-booking-addon' ),
            'past'         => __( 'Agendamento passado', 'dps-booking-addon' ),
        ];
        $status_labels  = [
            'pendente'        => __( 'Pendente', 'dps-booking-addon' ),
            'finalizado'      => __( 'Finalizado', 'dps-booking-addon' ),
            'finalizado_pago' => __( 'Finalizado e pago', 'dps-booking-addon' ),
            'cancelado'       => __( 'Cancelado', 'dps-booking-addon' ),
        ];

        if ( 'subscription' === $type ) {
            $total_value = (float) get_post_meta( $appointment_id, 'subscription_total_value', true );
        }

        ob_start();
        echo '<section class="dps-booking-confirmation" aria-labelledby="dps-booking-confirmation-title">';
        echo '<header class="dps-confirmation-header">';
        echo '<p class="dps-confirmation-eyebrow">' . esc_html__( 'Registro salvo', 'dps-booking-addon' ) . '</p>';
        echo '<h2 id="dps-booking-confirmation-title" class="dps-confirmation-title">' . esc_html__( 'Agendamento confirmado', 'dps-booking-addon' ) . '</h2>';
        echo '<p class="dps-confirmation-subtitle">' . esc_html__( 'Os dados abaixo foram gravados no sistema.', 'dps-booking-addon' ) . '</p>';
        echo '</header>';

        echo '<dl class="dps-confirmation-list">';
        $this->render_confirmation_row( __( 'Tipo', 'dps-booking-addon' ), isset( $type_labels[ $type ] ) ? $type_labels[ $type ] : $type );
        $this->render_confirmation_row( __( 'Cliente', 'dps-booking-addon' ), $client_name );
        $this->render_confirmation_row( __( 'Pet(s)', 'dps-booking-addon' ), ! empty( $pet_names ) ? implode( ', ', $pet_names ) : __( 'Pet não encontrado', 'dps-booking-addon' ) );
        $this->render_confirmation_row( __( 'Data', 'dps-booking-addon' ), $date_formatted );
        $this->render_confirmation_row( __( 'Horário', 'dps-booking-addon' ), $time );
        $this->render_confirmation_row( __( 'Status', 'dps-booking-addon' ), isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : $status );
        if ( $total_value > 0 ) {
            $value = class_exists( 'DPS_Money_Helper' )
                ? DPS_Money_Helper::format_currency_from_decimal( $total_value )
                : 'R$ ' . number_format_i18n( $total_value, 2 );
            $this->render_confirmation_row( __( 'Valor', 'dps-booking-addon' ), $value );
        }
        if ( $notes ) {
            $this->render_confirmation_row( __( 'Observações', 'dps-booking-addon' ), $notes );
        }
        echo '</dl>';

        echo '<div class="dps-confirmation-actions">';
        echo '<a href="' . esc_url( $this->get_booking_page_url() ) . '" class="dps-btn dps-btn--primary">' . esc_html__( 'Novo agendamento', 'dps-booking-addon' ) . '</a>';
        $panel_url = (int) get_option( 'dps_panel_page_id' ) ? get_permalink( (int) get_option( 'dps_panel_page_id' ) ) : '';
        if ( $panel_url ) {
            echo '<a href="' . esc_url( add_query_arg( 'tab', 'agendas', $panel_url ) ) . '" class="dps-btn dps-btn--secondary">' . esc_html__( 'Ver painel de agenda', 'dps-booking-addon' ) . '</a>';
        }
        echo '</div>';
        echo '</section>';

        return ob_get_clean();
    }

    /**
     * Renderiza uma linha de dados da confirmação.
     *
     * @param string $label Rótulo.
     * @param string $value Valor.
     */
    private function render_confirmation_row( $label, $value ) {
        echo '<div class="dps-confirmation-row">';
        echo '<dt>' . esc_html( $label ) . '</dt>';
        echo '<dd>' . esc_html( $value ) . '</dd>';
        echo '</div>';
    }

    /**
     * Renderiza mensagens de acesso.
     *
     * @param string $title       Título.
     * @param string $message     Mensagem.
     * @param string $action_url  URL de ação.
     * @param string $action_text Texto da ação.
     * @return string
     */
    private function render_access_message( $title, $message, $action_url = '', $action_text = '' ) {
        ob_start();
        echo '<div class="dps-booking-wrapper dps-booking-signature">';
        echo '<section class="dps-booking-notice" role="status">';
        echo '<h1>' . esc_html( $title ) . '</h1>';
        echo '<p>' . esc_html( $message ) . '</p>';
        if ( $action_url && $action_text ) {
            echo '<a class="dps-btn dps-btn--primary" href="' . esc_url( $action_url ) . '">' . esc_html( $action_text ) . '</a>';
        }
        echo '</section>';
        echo '</div>';

        return ob_get_clean();
    }

    /**
     * Verifica acesso amplo à página.
     *
     * @return bool
     */
    private function can_access() {
        return current_user_can( 'manage_options' )
            || current_user_can( 'dps_manage_clients' )
            || current_user_can( 'dps_manage_pets' )
            || current_user_can( 'dps_manage_appointments' );
    }

    /**
     * Verifica permissão de criação/edição.
     *
     * @return bool
     */
    private function can_manage_bookings() {
        return current_user_can( 'manage_options' ) || current_user_can( 'dps_manage_appointments' );
    }

    /**
     * Verifica se a conta pode editar ou duplicar um agendamento.
     *
     * @param int $appointment_id ID do agendamento.
     * @return bool
     */
    private function can_edit_appointment( $appointment_id ) {
        if ( ! $this->can_manage_bookings() ) {
            return false;
        }

        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        $appointment = get_post( $appointment_id );
        if ( ! $appointment || 'dps_agendamento' !== $appointment->post_type ) {
            return false;
        }

        return (int) $appointment->post_author === get_current_user_id();
    }

    /**
     * Extrai ID de agendamento de uma ação de query string.
     *
     * @param string $action_arg Nome do parâmetro de ação.
     * @return int
     */
    private function get_requested_appointment_id( $action_arg ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Leitura de query string validada por capability.
        $action = isset( $_GET[ $action_arg ] ) ? sanitize_key( wp_unslash( $_GET[ $action_arg ] ) ) : '';
        if ( 'appointment' !== $action ) {
            return 0;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Leitura de query string validada por capability.
        return isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
    }

    /**
     * Lê o primeiro parâmetro inteiro disponível na query string.
     *
     * @param array<int,string> $names Nomes aceitos.
     * @return int
     */
    private function get_int_query_arg( array $names ) {
        foreach ( $names as $name ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Preferência opcional de UI.
            if ( isset( $_GET[ $name ] ) ) {
                return absint( wp_unslash( $_GET[ $name ] ) );
            }
        }

        return 0;
    }

    /**
     * Resolve a URL canônica do Booking.
     *
     * @return string
     */
    private function get_booking_page_url() {
        $page_id = $this->find_booking_page_id();
        if ( $page_id ) {
            $url = get_permalink( $page_id );
            if ( $url ) {
                return $url;
            }
        }

        return $this->get_current_page_url();
    }

    /**
     * Resolve a URL da página atual sem depender de REQUEST_URI bruto.
     *
     * @return string
     */
    private function get_current_page_url() {
        $queried_id = function_exists( 'get_queried_object_id' ) ? get_queried_object_id() : 0;
        if ( $queried_id ) {
            $url = get_permalink( $queried_id );
            if ( $url ) {
                return $url;
            }
        }

        global $post;
        if ( $post instanceof WP_Post ) {
            $url = get_permalink( $post->ID );
            if ( $url ) {
                return $url;
            }
        }

        return home_url( '/agendamento/' );
    }

    /**
     * Encontra a página que contém o shortcode de Booking.
     *
     * @return int
     */
    private function find_booking_page_id() {
        $configured_id = (int) get_option( 'dps_booking_page_id' );
        if ( $configured_id && $this->page_contains_booking_shortcode( $configured_id ) ) {
            return $configured_id;
        }

        $agendamento = get_page_by_path( 'agendamento' );
        if ( $agendamento instanceof WP_Post && $this->page_contains_booking_shortcode( $agendamento->ID ) ) {
            if ( $configured_id !== (int) $agendamento->ID ) {
                update_option( 'dps_booking_page_id', (int) $agendamento->ID );
            }
            return (int) $agendamento->ID;
        }

        $pages = get_posts(
            [
                'post_type'      => 'page',
                'post_status'    => [ 'publish', 'private' ],
                'posts_per_page' => 20,
                's'              => 'dps_booking',
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ]
        );

        foreach ( $pages as $page_id ) {
            if ( $this->page_contains_booking_shortcode( $page_id ) ) {
                update_option( 'dps_booking_page_id', (int) $page_id );
                return (int) $page_id;
            }
        }

        return 0;
    }

    /**
     * Verifica conteúdo e metadados comuns de page builders.
     *
     * @param int $page_id ID da página.
     * @return bool
     */
    private function page_contains_booking_shortcode( $page_id ) {
        $post = get_post( $page_id );
        if ( ! $post instanceof WP_Post ) {
            return false;
        }

        foreach ( $this->shortcodes as $shortcode ) {
            if ( has_shortcode( $post->post_content, $shortcode ) || false !== strpos( $post->post_content, '[' . $shortcode ) ) {
                return true;
            }
        }

        foreach ( [ '_elementor_data', '_yootheme_source', '_fl_builder_data' ] as $meta_key ) {
            $meta_value = get_post_meta( $page_id, $meta_key, true );
            if ( ! is_string( $meta_value ) || '' === $meta_value ) {
                continue;
            }
            foreach ( $this->shortcodes as $shortcode ) {
                if ( false !== strpos( $meta_value, '[' . $shortcode ) ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Verifica se a tela atual deve receber os assets do Booking.
     *
     * @return bool
     */
    private function is_booking_screen() {
        $page_id = $this->find_booking_page_id();
        if ( $page_id && is_page( $page_id ) ) {
            return true;
        }

        $queried_id = function_exists( 'get_queried_object_id' ) ? get_queried_object_id() : 0;
        return $queried_id ? $this->page_contains_booking_shortcode( $queried_id ) : false;
    }

    /**
     * Valida confirmação assinada via query string.
     *
     * @return int
     */
    private function get_confirmed_appointment_id_from_request() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce assinado é validado logo abaixo.
        $appointment_id = isset( $_GET['dps_booking_confirmed'] ) ? absint( wp_unslash( $_GET['dps_booking_confirmed'] ) ) : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce assinado é validado logo abaixo.
        $nonce = isset( $_GET['dps_booking_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['dps_booking_nonce'] ) ) : '';

        if ( ! $appointment_id || ! $nonce ) {
            return 0;
        }

        if ( ! wp_verify_nonce( $nonce, 'dps_booking_confirmation_' . $appointment_id . '_' . get_current_user_id() ) ) {
            return 0;
        }

        return $this->can_edit_appointment( $appointment_id ) ? $appointment_id : 0;
    }

    /**
     * Verifica se o POST atual veio do Booking.
     *
     * @param string $redirect URL calculada.
     * @return bool
     */
    private function is_booking_submission( $redirect ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- O nonce principal já foi verificado pelo núcleo antes deste filtro.
        if ( isset( $_POST['dps_booking_context'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['dps_booking_context'] ) ) ) {
            return true;
        }

        $booking_path  = wp_parse_url( $this->get_booking_page_url(), PHP_URL_PATH );
        $redirect_path = wp_parse_url( $redirect, PHP_URL_PATH );

        return $booking_path && $redirect_path && untrailingslashit( $booking_path ) === untrailingslashit( $redirect_path );
    }
}

register_activation_hook( __FILE__, [ 'DPS_Booking_Addon', 'activate_plugin' ] );

/**
 * Inicializa o Booking Add-on após text domain e núcleo.
 */
function dps_booking_init_addon() {
    if ( ! dps_booking_check_base_plugin() ) {
        return;
    }

    DPS_Booking_Addon::get_instance();
}
add_action( 'init', 'dps_booking_init_addon', 5 );
