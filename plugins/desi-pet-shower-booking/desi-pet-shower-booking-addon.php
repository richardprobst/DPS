<?php
/**
 * Plugin Name:       desi.pet by PRObst – Booking Add-on
 * Plugin URI:        https://www.probst.pro
 * Description:       Página pública de agendamentos de serviços para clientes. Formulário moderno e responsivo.
 * Version:           1.0.0
 * Author:            PRObst
 * Author URI:        https://www.probst.pro
 * Text Domain:       dps-booking-addon
 * Domain Path:       /languages
 * Requires at least: 6.9
 * Requires PHP:      8.4
 * Update URI:        https://github.com/richardprobst/DPS
 * License:           GPL-2.0+
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Verifica se o plugin base desi.pet by PRObst está ativo.
 * Se não estiver, exibe aviso e interrompe carregamento do add-on.
 */
function dps_booking_check_base_plugin() {
    if ( ! class_exists( 'DPS_Base_Plugin' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__( 'O add-on requer o plugin base desi.pet by PRObst para funcionar.', 'dps-booking-addon' );
            echo '</p></div>';
        } );
        return false;
    }
    return true;
}
add_action( 'plugins_loaded', function() {
    if ( ! dps_booking_check_base_plugin() ) {
        return;
    }
}, 1 );

/**
 * Carrega o text domain do Booking Add-on.
 * Usa prioridade 1 para garantir que rode antes da inicialização da classe (prioridade 5).
 */
function dps_booking_load_textdomain() {
    load_plugin_textdomain( 'dps-booking-addon', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'dps_booking_load_textdomain', 1 );

/**
 * Classe principal do Booking Add-on.
 *
 * Fornece um formulário público de agendamento de serviços para clientes.
 *
 * @since 1.0.0
 */
class DPS_Booking_Addon {

    /**
     * Instância única (singleton).
     *
     * @since 1.0.0
     * @var DPS_Booking_Addon|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única.
     *
     * @since 1.0.0
     * @return DPS_Booking_Addon
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado (singleton).
     *
     * @since 1.0.0
     */
    private function __construct() {
        // Registra shortcode
        add_shortcode( 'dps_booking_form', [ $this, 'render_booking_form' ] );

        // Enfileira assets
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        // Processa formulário de agendamento
        add_action( 'init', [ $this, 'maybe_handle_booking' ] );

        // Cria a página automaticamente ao ativar
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
    }

    /**
     * Executado na ativação do plugin. Cria a página de agendamento.
     *
     * @since 1.0.0
     */
    public function activate() {
        $title = __( 'Agendar Serviços', 'dps-booking-addon' );
        $slug  = sanitize_title( $title );
        $page  = get_page_by_path( $slug );
        if ( ! $page ) {
            $page_id = wp_insert_post( [
                'post_title'   => $title,
                'post_name'    => $slug,
                'post_content' => '[dps_booking_form]',
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ] );
            if ( $page_id ) {
                update_option( 'dps_booking_page_id', $page_id );
            }
        } else {
            update_option( 'dps_booking_page_id', $page->ID );
        }
    }

    /**
     * Enfileira CSS e JS do add-on.
     *
     * @since 1.0.0
     */
    public function enqueue_assets() {
        // Carrega apenas na página de agendamento ou onde houver o shortcode
        $booking_page_id = get_option( 'dps_booking_page_id' );
        $current_post = get_post();
        $post_content = $current_post ? (string) $current_post->post_content : '';

        if ( ! is_page( $booking_page_id ) && ! has_shortcode( $post_content, 'dps_booking_form' ) ) {
            return;
        }

        $addon_url = plugin_dir_url( __FILE__ );
        $version   = '1.0.0';

        // CSS responsivo
        wp_enqueue_style(
            'dps-booking-addon',
            $addon_url . 'assets/css/booking-addon.css',
            [],
            $version
        );

        // JS de interações
        wp_enqueue_script(
            'dps-booking-addon',
            $addon_url . 'assets/js/booking-addon.js',
            [],
            $version,
            true
        );

        // Localiza dados para JavaScript
        wp_localize_script(
            'dps-booking-addon',
            'dpsBookingData',
            [
                'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
                'nonce'       => wp_create_nonce( 'dps_booking_nonce' ),
                'i18n'        => [
                    'selectService'   => __( 'Selecione um serviço', 'dps-booking-addon' ),
                    'selectDate'      => __( 'Selecione uma data', 'dps-booking-addon' ),
                    'selectTime'      => __( 'Selecione um horário', 'dps-booking-addon' ),
                    'confirmBooking'  => __( 'Confirmar agendamento', 'dps-booking-addon' ),
                    'loading'         => __( 'Carregando...', 'dps-booking-addon' ),
                    'success'         => __( 'Agendamento realizado com sucesso!', 'dps-booking-addon' ),
                    'error'           => __( 'Erro ao processar agendamento. Tente novamente.', 'dps-booking-addon' ),
                    'required'        => __( 'Este campo é obrigatório', 'dps-booking-addon' ),
                    'invalidPhone'    => __( 'Telefone inválido', 'dps-booking-addon' ),
                    'invalidEmail'    => __( 'Email inválido', 'dps-booking-addon' ),
                ],
            ]
        );
    }

    /**
     * Processa o formulário de agendamento se enviado.
     *
     * @since 1.0.0
     */
    public function maybe_handle_booking() {
        if ( ! isset( $_POST['dps_booking_action'] ) || 'save_booking' !== $_POST['dps_booking_action'] ) {
            return;
        }

        // Verifica nonce
        if ( ! isset( $_POST['dps_booking_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dps_booking_nonce'] ) ), 'dps_booking_nonce' ) ) {
            $this->add_error( __( 'Sessão expirada. Por favor, recarregue a página e tente novamente.', 'dps-booking-addon' ) );
            $this->redirect_with_error();
            return;
        }

        // Honeypot check
        if ( ! empty( $_POST['dps_hp_field'] ) ) {
            $this->add_error( __( 'Ocorreu um erro no envio. Tente novamente.', 'dps-booking-addon' ) );
            $this->redirect_with_error();
            return;
        }

        // Sanitiza dados do formulário
        $client_name  = isset( $_POST['client_name'] ) ? sanitize_text_field( wp_unslash( $_POST['client_name'] ) ) : '';
        $client_phone = isset( $_POST['client_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['client_phone'] ) ) : '';
        $client_email = isset( $_POST['client_email'] ) ? sanitize_email( wp_unslash( $_POST['client_email'] ) ) : '';
        $pet_name     = isset( $_POST['pet_name'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_name'] ) ) : '';
        $pet_breed    = isset( $_POST['pet_breed'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_breed'] ) ) : '';
        $pet_size     = isset( $_POST['pet_size'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_size'] ) ) : '';
        $service_id   = isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0;
        $extras       = isset( $_POST['extras'] ) && is_array( $_POST['extras'] ) ? array_map( 'absint', $_POST['extras'] ) : [];
        $appt_date    = isset( $_POST['appointment_date'] ) ? sanitize_text_field( wp_unslash( $_POST['appointment_date'] ) ) : '';
        $appt_time    = isset( $_POST['appointment_time'] ) ? sanitize_text_field( wp_unslash( $_POST['appointment_time'] ) ) : '';
        $observations = isset( $_POST['observations'] ) ? sanitize_textarea_field( wp_unslash( $_POST['observations'] ) ) : '';

        // Validações
        $errors = [];

        if ( empty( $client_name ) ) {
            $errors[] = __( 'O nome é obrigatório.', 'dps-booking-addon' );
        }

        if ( empty( $client_phone ) ) {
            $errors[] = __( 'O telefone é obrigatório.', 'dps-booking-addon' );
        } elseif ( ! $this->validate_phone( $client_phone ) ) {
            $errors[] = __( 'Telefone inválido.', 'dps-booking-addon' );
        }

        if ( ! empty( $client_email ) && ! is_email( $client_email ) ) {
            $errors[] = __( 'Email inválido.', 'dps-booking-addon' );
        }

        if ( empty( $pet_name ) ) {
            $errors[] = __( 'O nome do pet é obrigatório.', 'dps-booking-addon' );
        }

        if ( empty( $service_id ) ) {
            $errors[] = __( 'Selecione um serviço.', 'dps-booking-addon' );
        }

        if ( empty( $appt_date ) ) {
            $errors[] = __( 'Selecione uma data para o agendamento.', 'dps-booking-addon' );
        }

        if ( empty( $appt_time ) ) {
            $errors[] = __( 'Selecione um horário para o agendamento.', 'dps-booking-addon' );
        }

        // Valida data (não pode ser no passado)
        if ( ! empty( $appt_date ) ) {
            $today = current_time( 'Y-m-d' );
            if ( $appt_date < $today ) {
                $errors[] = __( 'A data do agendamento não pode ser no passado.', 'dps-booking-addon' );
            }
        }

        if ( ! empty( $errors ) ) {
            foreach ( $errors as $error ) {
                $this->add_error( $error );
            }
            $this->redirect_with_error();
            return;
        }

        // Normaliza telefone
        $client_phone = $this->normalize_phone( $client_phone );

        // Busca ou cria cliente
        $client_id = $this->find_or_create_client( $client_name, $client_phone, $client_email );
        if ( ! $client_id ) {
            $this->add_error( __( 'Erro ao criar cliente. Tente novamente.', 'dps-booking-addon' ) );
            $this->redirect_with_error();
            return;
        }

        // Busca ou cria pet
        $pet_id = $this->find_or_create_pet( $client_id, $pet_name, $pet_breed, $pet_size );
        if ( ! $pet_id ) {
            $this->add_error( __( 'Erro ao criar pet. Tente novamente.', 'dps-booking-addon' ) );
            $this->redirect_with_error();
            return;
        }

        // Cria agendamento
        $appointment_id = $this->create_appointment( $client_id, $pet_id, $service_id, $extras, $appt_date, $appt_time, $observations );
        if ( ! $appointment_id ) {
            $this->add_error( __( 'Erro ao criar agendamento. Tente novamente.', 'dps-booking-addon' ) );
            $this->redirect_with_error();
            return;
        }

        // Sucesso - redireciona com mensagem de sucesso
        $redirect_url = add_query_arg( 'booking_success', '1', $this->get_booking_page_url() );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Valida telefone brasileiro.
     *
     * @since 1.0.0
     * @param string $phone Telefone a validar.
     * @return bool
     */
    private function validate_phone( $phone ) {
        if ( class_exists( 'DPS_Phone_Helper' ) && method_exists( 'DPS_Phone_Helper', 'is_valid_brazilian_phone' ) ) {
            return DPS_Phone_Helper::is_valid_brazilian_phone( $phone );
        }

        $digits = preg_replace( '/\D/', '', (string) $phone );
        $length = strlen( $digits );

        return ( $length === 10 || $length === 11 );
    }

    /**
     * Normaliza telefone para apenas dígitos.
     *
     * @since 1.0.0
     * @param string $phone Telefone bruto.
     * @return string Apenas dígitos.
     */
    private function normalize_phone( $phone ) {
        $digits = preg_replace( '/\D/', '', (string) $phone );

        // Remove código do país se presente
        $length = strlen( $digits );
        if ( ( $length === 12 || $length === 13 ) && substr( $digits, 0, 2 ) === '55' ) {
            $digits = substr( $digits, 2 );
        }

        return $digits;
    }

    /**
     * Busca cliente existente por telefone ou cria novo.
     *
     * @since 1.0.0
     * @param string $name  Nome do cliente.
     * @param string $phone Telefone normalizado.
     * @param string $email Email do cliente.
     * @return int|false ID do cliente ou false.
     */
    private function find_or_create_client( $name, $phone, $email ) {
        // Busca cliente existente por telefone
        $existing = get_posts( [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => 'client_phone',
                    'value' => $phone,
                ],
            ],
        ] );

        if ( ! empty( $existing ) ) {
            return (int) $existing[0];
        }

        // Cria novo cliente
        $client_id = wp_insert_post( [
            'post_type'   => 'dps_cliente',
            'post_title'  => $name,
            'post_status' => 'publish',
        ] );

        if ( $client_id && ! is_wp_error( $client_id ) ) {
            update_post_meta( $client_id, 'client_phone', $phone );
            if ( ! empty( $email ) ) {
                update_post_meta( $client_id, 'client_email', $email );
            }
            return $client_id;
        }

        return false;
    }

    /**
     * Busca pet existente ou cria novo.
     *
     * @since 1.0.0
     * @param int    $client_id ID do cliente.
     * @param string $name      Nome do pet.
     * @param string $breed     Raça do pet.
     * @param string $size      Porte do pet.
     * @return int|false ID do pet ou false.
     */
    private function find_or_create_pet( $client_id, $name, $breed, $size ) {
        // Busca pet existente do mesmo cliente com mesmo nome
        $existing = get_posts( [
            'post_type'      => 'dps_pet',
            'posts_per_page' => 50,
            'fields'         => 'ids',
            'post_status'    => 'publish',
            'no_found_rows'  => true,
            'meta_query'     => [
                [
                    'key'   => 'owner_id',
                    'value' => $client_id,
                ],
            ],
        ] );

        // Filter by title manually (WP deprecated 'title' parameter in get_posts)
        if ( ! empty( $existing ) ) {
            foreach ( $existing as $pet_id ) {
                $pet_post = get_post( $pet_id );
                if ( $pet_post && strtolower( $pet_post->post_title ) === strtolower( $name ) ) {
                    return (int) $pet_id;
                }
            }
        }

        // Cria novo pet
        $pet_id = wp_insert_post( [
            'post_type'   => 'dps_pet',
            'post_title'  => $name,
            'post_status' => 'publish',
        ] );

        if ( $pet_id && ! is_wp_error( $pet_id ) ) {
            update_post_meta( $pet_id, 'owner_id', $client_id );
            if ( ! empty( $breed ) ) {
                update_post_meta( $pet_id, 'pet_breed', $breed );
            }
            if ( ! empty( $size ) ) {
                update_post_meta( $pet_id, 'pet_size', $size );
            }
            return $pet_id;
        }

        return false;
    }

    /**
     * Cria um novo agendamento.
     *
     * @since 1.0.0
     * @param int    $client_id    ID do cliente.
     * @param int    $pet_id       ID do pet.
     * @param int    $service_id   ID do serviço principal.
     * @param array  $extras       IDs dos extras selecionados.
     * @param string $date         Data do agendamento (Y-m-d).
     * @param string $time         Hora do agendamento (H:i).
     * @param string $observations Observações.
     * @return int|false ID do agendamento ou false.
     */
    private function create_appointment( $client_id, $pet_id, $service_id, $extras, $date, $time, $observations ) {
        $pet = get_post( $pet_id );
        $pet_name = $pet ? $pet->post_title : __( 'Pet', 'dps-booking-addon' );

        $title = sprintf(
            __( 'Agendamento - %s - %s às %s', 'dps-booking-addon' ),
            $pet_name,
            date_i18n( 'd/m/Y', strtotime( $date ) ),
            $time
        );

        $appointment_id = wp_insert_post( [
            'post_type'   => 'dps_agendamento',
            'post_title'  => $title,
            'post_status' => 'publish',
        ] );

        if ( ! $appointment_id || is_wp_error( $appointment_id ) ) {
            return false;
        }

        // Salva metadados
        update_post_meta( $appointment_id, 'appointment_client', $client_id );
        update_post_meta( $appointment_id, 'appointment_pet', $pet_id );
        update_post_meta( $appointment_id, 'appointment_date', $date );
        update_post_meta( $appointment_id, 'appointment_time', $time );
        update_post_meta( $appointment_id, 'appointment_status', 'pendente' );
        update_post_meta( $appointment_id, 'appointment_type', 'simple' );

        // Serviços
        $all_services = [ $service_id ];
        if ( ! empty( $extras ) ) {
            $all_services = array_merge( $all_services, $extras );
        }
        update_post_meta( $appointment_id, 'appointment_services', $all_services );

        // Observações
        if ( ! empty( $observations ) ) {
            update_post_meta( $appointment_id, 'appointment_observations', $observations );
        }

        // Marca como agendamento online
        update_post_meta( $appointment_id, 'booking_source', 'online' );

        // Dispara hook para integrações (notificações, etc.)
        do_action( 'dps_booking_after_appointment_created', $appointment_id, $client_id, $pet_id );

        return $appointment_id;
    }

    /**
     * Adiciona mensagem de erro.
     *
     * @since 1.0.0
     * @param string $message Mensagem de erro.
     */
    private function add_error( $message ) {
        if ( class_exists( 'DPS_Message_Helper' ) ) {
            DPS_Message_Helper::add_error( $message );
            return;
        }

        // Fallback: usa transient
        $transient_key = 'dps_booking_errors_' . $this->get_session_id();
        $errors = get_transient( $transient_key );
        if ( ! is_array( $errors ) ) {
            $errors = [];
        }
        $errors[] = $message;
        set_transient( $transient_key, $errors, 60 );
    }

    /**
     * Obtém mensagens de erro.
     *
     * @since 1.0.0
     * @return array
     */
    private function get_errors() {
        $transient_key = 'dps_booking_errors_' . $this->get_session_id();
        $errors = get_transient( $transient_key );
        delete_transient( $transient_key );
        return is_array( $errors ) ? $errors : [];
    }

    /**
     * Obtém um identificador de sessão baseado no IP.
     *
     * @since 1.0.0
     * @return string
     */
    private function get_session_id() {
        $ip = '';
        if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }
        return substr( hash( 'sha256', 'dps_booking_' . $ip ), 0, 16 );
    }

    /**
     * Redireciona com flag de erro.
     *
     * @since 1.0.0
     */
    private function redirect_with_error() {
        wp_safe_redirect( add_query_arg( 'booking_error', '1', $this->get_booking_page_url() ) );
        exit;
    }

    /**
     * Obtém URL da página de agendamento.
     *
     * @since 1.0.0
     * @return string
     */
    private function get_booking_page_url() {
        $page_id = (int) get_option( 'dps_booking_page_id' );
        if ( $page_id ) {
            $url = get_permalink( $page_id );
            if ( $url ) {
                return $url;
            }
        }
        return home_url( '/' );
    }

    /**
     * Obtém serviços disponíveis.
     *
     * @since 1.0.0
     * @return array
     */
    private function get_available_services() {
        $services = get_posts( [
            'post_type'      => 'dps_service',
            'posts_per_page' => 100,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );

        $result = [];
        foreach ( $services as $service ) {
            $price = get_post_meta( $service->ID, 'service_price', true );
            $is_extra = get_post_meta( $service->ID, 'service_is_extra', true );

            $result[] = [
                'id'       => $service->ID,
                'name'     => $service->post_title,
                'price'    => $price ? (float) $price : 0,
                'is_extra' => ! empty( $is_extra ),
            ];
        }

        return $result;
    }

    /**
     * Obtém horários disponíveis para uma data.
     *
     * @since 1.0.0
     * @param string $date Data no formato Y-m-d.
     * @return array
     */
    private function get_available_times( $date = '' ) {
        // Horários padrão de funcionamento
        $default_times = [
            '08:00', '08:30', '09:00', '09:30', '10:00', '10:30',
            '11:00', '11:30', '13:00', '13:30', '14:00', '14:30',
            '15:00', '15:30', '16:00', '16:30', '17:00', '17:30',
        ];

        // Permite filtrar horários
        $times = apply_filters( 'dps_booking_available_times', $default_times, $date );

        return $times;
    }

    /**
     * Renderiza o formulário de agendamento.
     *
     * @since 1.0.0
     * @return string HTML do formulário.
     */
    public function render_booking_form() {
        // Desabilita cache da página
        if ( class_exists( 'DPS_Cache_Control' ) ) {
            DPS_Cache_Control::force_no_cache();
        }

        $success = isset( $_GET['booking_success'] ) && '1' === $_GET['booking_success'];
        $services = $this->get_available_services();
        $main_services = array_filter( $services, function( $s ) {
            return ! $s['is_extra'];
        } );
        $extra_services = array_filter( $services, function( $s ) {
            return $s['is_extra'];
        } );
        $available_times = $this->get_available_times();

        ob_start();
        ?>
        <div class="dps-booking-form">
            <?php
            // Display messages (DPS_Message_Helper returns pre-escaped HTML)
            if ( class_exists( 'DPS_Message_Helper' ) ) {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- DPS_Message_Helper::display_messages() returns pre-escaped safe HTML
                echo DPS_Message_Helper::display_messages();
            } else {
                $errors = $this->get_errors();
                foreach ( $errors as $error ) {
                    echo '<div class="dps-booking-message dps-booking-message--error" role="alert">';
                    echo '<span class="dps-booking-message__icon">⚠️</span>';
                    echo '<span>' . esc_html( $error ) . '</span>';
                    echo '</div>';
                }
            }

            // Mensagem de sucesso
            if ( $success ) {
                ?>
                <div class="dps-booking-success" role="status">
                    <div class="dps-booking-success__icon">✓</div>
                    <h4 class="dps-booking-success__title"><?php esc_html_e( 'Agendamento confirmado!', 'dps-booking-addon' ); ?></h4>
                    <p class="dps-booking-success__text"><?php esc_html_e( 'Seu agendamento foi realizado com sucesso. Em breve você receberá uma confirmação por WhatsApp.', 'dps-booking-addon' ); ?></p>
                    <div class="dps-booking-success__actions">
                        <a href="<?php echo esc_url( $this->get_booking_page_url() ); ?>" class="dps-booking-btn dps-booking-btn--primary">
                            <?php esc_html_e( 'Agendar outro serviço', 'dps-booking-addon' ); ?>
                        </a>
                    </div>
                </div>
                <?php
                echo '</div>';
                return ob_get_clean();
            }
            ?>

            <!-- Header do formulário -->
            <header class="dps-booking-header">
                <h2 class="dps-booking-header__title">
                    <span class="dps-booking-header__icon">📅</span>
                    <?php esc_html_e( 'Agendar Serviço', 'dps-booking-addon' ); ?>
                </h2>
                <p class="dps-booking-header__subtitle">
                    <?php esc_html_e( 'Preencha os dados abaixo para agendar banho, tosa ou outros serviços para seu pet.', 'dps-booking-addon' ); ?>
                </p>
            </header>

            <!-- Progress indicator -->
            <div class="dps-booking-progress" aria-live="polite">
                <div class="dps-booking-progress__steps">
                    <div class="dps-booking-progress__step dps-booking-progress__step--active" data-step="1">
                        <span class="dps-booking-progress__number">1</span>
                        <span class="dps-booking-progress__label"><?php esc_html_e( 'Seus Dados', 'dps-booking-addon' ); ?></span>
                    </div>
                    <div class="dps-booking-progress__connector"></div>
                    <div class="dps-booking-progress__step" data-step="2">
                        <span class="dps-booking-progress__number">2</span>
                        <span class="dps-booking-progress__label"><?php esc_html_e( 'Pet', 'dps-booking-addon' ); ?></span>
                    </div>
                    <div class="dps-booking-progress__connector"></div>
                    <div class="dps-booking-progress__step" data-step="3">
                        <span class="dps-booking-progress__number">3</span>
                        <span class="dps-booking-progress__label"><?php esc_html_e( 'Serviço', 'dps-booking-addon' ); ?></span>
                    </div>
                    <div class="dps-booking-progress__connector"></div>
                    <div class="dps-booking-progress__step" data-step="4">
                        <span class="dps-booking-progress__number">4</span>
                        <span class="dps-booking-progress__label"><?php esc_html_e( 'Data e Hora', 'dps-booking-addon' ); ?></span>
                    </div>
                </div>
            </div>

            <form method="post" id="dps-booking-form-element" class="dps-booking-form__form">
                <input type="hidden" name="dps_booking_action" value="save_booking">
                <?php wp_nonce_field( 'dps_booking_nonce', 'dps_booking_nonce' ); ?>

                <!-- Honeypot -->
                <div class="dps-booking-hp" aria-hidden="true">
                    <label for="dps_hp_field"><?php esc_html_e( 'Deixe este campo vazio', 'dps-booking-addon' ); ?></label>
                    <input type="text" name="dps_hp_field" id="dps_hp_field" tabindex="-1" autocomplete="off">
                </div>

                <!-- Step 1: Dados do Cliente -->
                <div class="dps-booking-step dps-booking-step--active" data-step="1">
                    <h3 class="dps-booking-step__title">
                        <span class="dps-booking-step__icon">👤</span>
                        <?php esc_html_e( 'Seus Dados', 'dps-booking-addon' ); ?>
                    </h3>

                    <div class="dps-booking-fields">
                        <div class="dps-booking-field">
                            <label for="client_name" class="dps-booking-label">
                                <?php esc_html_e( 'Nome completo', 'dps-booking-addon' ); ?>
                                <span class="dps-booking-required">*</span>
                            </label>
                            <input type="text" id="client_name" name="client_name" class="dps-booking-input" required 
                                   placeholder="<?php esc_attr_e( 'Digite seu nome', 'dps-booking-addon' ); ?>">
                        </div>

                        <div class="dps-booking-field">
                            <label for="client_phone" class="dps-booking-label">
                                <?php esc_html_e( 'WhatsApp', 'dps-booking-addon' ); ?>
                                <span class="dps-booking-required">*</span>
                            </label>
                            <input type="tel" id="client_phone" name="client_phone" class="dps-booking-input" required
                                   placeholder="<?php esc_attr_e( '(00) 00000-0000', 'dps-booking-addon' ); ?>">
                            <span class="dps-booking-help"><?php esc_html_e( 'Usaremos para confirmar o agendamento', 'dps-booking-addon' ); ?></span>
                        </div>

                        <div class="dps-booking-field dps-booking-field--full">
                            <label for="client_email" class="dps-booking-label">
                                <?php esc_html_e( 'Email', 'dps-booking-addon' ); ?>
                                <span class="dps-booking-optional"><?php esc_html_e( '(opcional)', 'dps-booking-addon' ); ?></span>
                            </label>
                            <input type="email" id="client_email" name="client_email" class="dps-booking-input"
                                   placeholder="<?php esc_attr_e( 'seu@email.com', 'dps-booking-addon' ); ?>">
                        </div>
                    </div>

                    <div class="dps-booking-actions">
                        <button type="button" class="dps-booking-btn dps-booking-btn--primary dps-booking-btn--next" data-next="2">
                            <?php esc_html_e( 'Continuar', 'dps-booking-addon' ); ?>
                            <span class="dps-booking-btn__icon">→</span>
                        </button>
                    </div>
                </div>

                <!-- Step 2: Dados do Pet -->
                <div class="dps-booking-step" data-step="2">
                    <h3 class="dps-booking-step__title">
                        <span class="dps-booking-step__icon">🐾</span>
                        <?php esc_html_e( 'Dados do Pet', 'dps-booking-addon' ); ?>
                    </h3>

                    <div class="dps-booking-fields">
                        <div class="dps-booking-field">
                            <label for="pet_name" class="dps-booking-label">
                                <?php esc_html_e( 'Nome do pet', 'dps-booking-addon' ); ?>
                                <span class="dps-booking-required">*</span>
                            </label>
                            <input type="text" id="pet_name" name="pet_name" class="dps-booking-input" required
                                   placeholder="<?php esc_attr_e( 'Nome do seu pet', 'dps-booking-addon' ); ?>">
                        </div>

                        <div class="dps-booking-field">
                            <label for="pet_breed" class="dps-booking-label">
                                <?php esc_html_e( 'Raça', 'dps-booking-addon' ); ?>
                            </label>
                            <input type="text" id="pet_breed" name="pet_breed" class="dps-booking-input"
                                   placeholder="<?php esc_attr_e( 'Ex: Golden Retriever', 'dps-booking-addon' ); ?>">
                        </div>

                        <div class="dps-booking-field dps-booking-field--full">
                            <label class="dps-booking-label">
                                <?php esc_html_e( 'Porte', 'dps-booking-addon' ); ?>
                            </label>
                            <div class="dps-booking-options">
                                <label class="dps-booking-option">
                                    <input type="radio" name="pet_size" value="pequeno">
                                    <span class="dps-booking-option__card">
                                        <span class="dps-booking-option__icon">🐕</span>
                                        <span class="dps-booking-option__label"><?php esc_html_e( 'Pequeno', 'dps-booking-addon' ); ?></span>
                                        <span class="dps-booking-option__desc"><?php esc_html_e( 'Até 10kg', 'dps-booking-addon' ); ?></span>
                                    </span>
                                </label>
                                <label class="dps-booking-option">
                                    <input type="radio" name="pet_size" value="medio" checked>
                                    <span class="dps-booking-option__card">
                                        <span class="dps-booking-option__icon">🐕‍🦺</span>
                                        <span class="dps-booking-option__label"><?php esc_html_e( 'Médio', 'dps-booking-addon' ); ?></span>
                                        <span class="dps-booking-option__desc"><?php esc_html_e( '10 a 25kg', 'dps-booking-addon' ); ?></span>
                                    </span>
                                </label>
                                <label class="dps-booking-option">
                                    <input type="radio" name="pet_size" value="grande">
                                    <span class="dps-booking-option__card">
                                        <span class="dps-booking-option__icon">🦮</span>
                                        <span class="dps-booking-option__label"><?php esc_html_e( 'Grande', 'dps-booking-addon' ); ?></span>
                                        <span class="dps-booking-option__desc"><?php esc_html_e( 'Acima de 25kg', 'dps-booking-addon' ); ?></span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="dps-booking-actions">
                        <button type="button" class="dps-booking-btn dps-booking-btn--secondary dps-booking-btn--prev" data-prev="1">
                            <span class="dps-booking-btn__icon">←</span>
                            <?php esc_html_e( 'Voltar', 'dps-booking-addon' ); ?>
                        </button>
                        <button type="button" class="dps-booking-btn dps-booking-btn--primary dps-booking-btn--next" data-next="3">
                            <?php esc_html_e( 'Continuar', 'dps-booking-addon' ); ?>
                            <span class="dps-booking-btn__icon">→</span>
                        </button>
                    </div>
                </div>

                <!-- Step 3: Serviço -->
                <div class="dps-booking-step" data-step="3">
                    <h3 class="dps-booking-step__title">
                        <span class="dps-booking-step__icon">✨</span>
                        <?php esc_html_e( 'Escolha o Serviço', 'dps-booking-addon' ); ?>
                    </h3>

                    <?php if ( ! empty( $main_services ) ) : ?>
                    <div class="dps-booking-services">
                        <h4 class="dps-booking-services__title"><?php esc_html_e( 'Serviços Principais', 'dps-booking-addon' ); ?></h4>
                        <div class="dps-booking-services__list">
                            <?php foreach ( $main_services as $service ) : ?>
                            <label class="dps-booking-service">
                                <input type="radio" name="service_id" value="<?php echo esc_attr( $service['id'] ); ?>" required>
                                <span class="dps-booking-service__card">
                                    <span class="dps-booking-service__name"><?php echo esc_html( $service['name'] ); ?></span>
                                    <?php if ( $service['price'] > 0 ) : ?>
                                    <span class="dps-booking-service__price">
                                        <?php 
                                        if ( class_exists( 'DPS_Money_Helper' ) ) {
                                            echo 'R$ ' . esc_html( DPS_Money_Helper::format_to_brazilian( (int) ( $service['price'] * 100 ) ) );
                                        } else {
                                            echo 'R$ ' . esc_html( number_format( $service['price'], 2, ',', '.' ) );
                                        }
                                        ?>
                                    </span>
                                    <?php endif; ?>
                                    <span class="dps-booking-service__check">✓</span>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php else : ?>
                    <div class="dps-booking-empty">
                        <p><?php esc_html_e( 'Nenhum serviço disponível no momento.', 'dps-booking-addon' ); ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if ( ! empty( $extra_services ) ) : ?>
                    <div class="dps-booking-extras">
                        <h4 class="dps-booking-extras__title"><?php esc_html_e( 'Adicionar Extras', 'dps-booking-addon' ); ?></h4>
                        <p class="dps-booking-extras__subtitle"><?php esc_html_e( 'Selecione serviços adicionais se desejar', 'dps-booking-addon' ); ?></p>
                        <div class="dps-booking-extras__list">
                            <?php foreach ( $extra_services as $extra ) : ?>
                            <label class="dps-booking-extra">
                                <input type="checkbox" name="extras[]" value="<?php echo esc_attr( $extra['id'] ); ?>">
                                <span class="dps-booking-extra__card">
                                    <span class="dps-booking-extra__check"></span>
                                    <span class="dps-booking-extra__info">
                                        <span class="dps-booking-extra__name"><?php echo esc_html( $extra['name'] ); ?></span>
                                        <?php if ( $extra['price'] > 0 ) : ?>
                                        <span class="dps-booking-extra__price">
                                            + R$ <?php 
                                            if ( class_exists( 'DPS_Money_Helper' ) ) {
                                                echo esc_html( DPS_Money_Helper::format_to_brazilian( (int) ( $extra['price'] * 100 ) ) );
                                            } else {
                                                echo esc_html( number_format( $extra['price'], 2, ',', '.' ) );
                                            }
                                            ?>
                                        </span>
                                        <?php endif; ?>
                                    </span>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="dps-booking-actions">
                        <button type="button" class="dps-booking-btn dps-booking-btn--secondary dps-booking-btn--prev" data-prev="2">
                            <span class="dps-booking-btn__icon">←</span>
                            <?php esc_html_e( 'Voltar', 'dps-booking-addon' ); ?>
                        </button>
                        <button type="button" class="dps-booking-btn dps-booking-btn--primary dps-booking-btn--next" data-next="4">
                            <?php esc_html_e( 'Continuar', 'dps-booking-addon' ); ?>
                            <span class="dps-booking-btn__icon">→</span>
                        </button>
                    </div>
                </div>

                <!-- Step 4: Data e Hora -->
                <div class="dps-booking-step" data-step="4">
                    <h3 class="dps-booking-step__title">
                        <span class="dps-booking-step__icon">📅</span>
                        <?php esc_html_e( 'Escolha Data e Horário', 'dps-booking-addon' ); ?>
                    </h3>

                    <div class="dps-booking-fields">
                        <div class="dps-booking-field">
                            <label for="appointment_date" class="dps-booking-label">
                                <?php esc_html_e( 'Data', 'dps-booking-addon' ); ?>
                                <span class="dps-booking-required">*</span>
                            </label>
                            <input type="date" id="appointment_date" name="appointment_date" class="dps-booking-input" required
                                   min="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>">
                        </div>

                        <div class="dps-booking-field">
                            <label for="appointment_time" class="dps-booking-label">
                                <?php esc_html_e( 'Horário', 'dps-booking-addon' ); ?>
                                <span class="dps-booking-required">*</span>
                            </label>
                            <select id="appointment_time" name="appointment_time" class="dps-booking-input dps-booking-select" required>
                                <option value=""><?php esc_html_e( 'Selecione um horário', 'dps-booking-addon' ); ?></option>
                                <?php foreach ( $available_times as $time ) : ?>
                                <option value="<?php echo esc_attr( $time ); ?>"><?php echo esc_html( $time ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="dps-booking-field dps-booking-field--full">
                            <label for="observations" class="dps-booking-label">
                                <?php esc_html_e( 'Observações', 'dps-booking-addon' ); ?>
                                <span class="dps-booking-optional"><?php esc_html_e( '(opcional)', 'dps-booking-addon' ); ?></span>
                            </label>
                            <textarea id="observations" name="observations" class="dps-booking-input dps-booking-textarea" rows="3"
                                      placeholder="<?php esc_attr_e( 'Alguma informação importante sobre seu pet? Cuidados especiais, preferências, etc.', 'dps-booking-addon' ); ?>"></textarea>
                        </div>
                    </div>

                    <!-- Resumo do agendamento -->
                    <div class="dps-booking-summary" id="booking-summary">
                        <h4 class="dps-booking-summary__title">
                            <span>📋</span>
                            <?php esc_html_e( 'Resumo do Agendamento', 'dps-booking-addon' ); ?>
                        </h4>
                        <div class="dps-booking-summary__content" id="summary-content">
                            <!-- Preenchido via JavaScript -->
                        </div>
                    </div>

                    <div class="dps-booking-actions">
                        <button type="button" class="dps-booking-btn dps-booking-btn--secondary dps-booking-btn--prev" data-prev="3">
                            <span class="dps-booking-btn__icon">←</span>
                            <?php esc_html_e( 'Voltar', 'dps-booking-addon' ); ?>
                        </button>
                        <button type="submit" class="dps-booking-btn dps-booking-btn--success dps-booking-btn--submit">
                            <span class="dps-booking-btn__icon">✓</span>
                            <?php esc_html_e( 'Confirmar Agendamento', 'dps-booking-addon' ); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}

/**
 * Inicializa o Booking Add-on.
 */
function dps_booking_init_addon() {
    if ( class_exists( 'DPS_Booking_Addon' ) ) {
        DPS_Booking_Addon::get_instance();
    }
}
add_action( 'init', 'dps_booking_init_addon', 5 );
