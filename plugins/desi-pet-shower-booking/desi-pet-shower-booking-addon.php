<?php
/**
 * Plugin Name:       desi.pet by PRObst – Booking Add-on
 * Plugin URI:        https://www.probst.pro
 * Description:       Página dedicada de agendamentos para administradores. Mesma funcionalidade da aba Agendamentos do Painel de Gestão DPS.
 * Version:           1.2.1
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
 * Fornece uma página dedicada de agendamentos para administradores,
 * com a mesma funcionalidade da aba Agendamentos do Painel de Gestão DPS.
 *
 * @since 1.0.0
 * @since 1.1.0 Refatorado para usar a funcionalidade do Painel de Gestão DPS.
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

        // Cria a página automaticamente ao ativar
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        
        // Hook para capturar agendamento salvo e armazenar para confirmação
        add_action( 'dps_base_after_save_appointment', [ $this, 'capture_saved_appointment' ], 5, 2 );
    }

    /**
     * Executado na ativação do plugin. Cria a página de agendamento.
     *
     * @since 1.0.0
     */
    public function activate() {
        $title = __( 'Agendamento de Serviços', 'dps-booking-addon' );
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

        // Carrega assets do plugin base (necessários para o formulário de agendamento)
        if ( class_exists( 'DPS_Base_Plugin' ) && method_exists( 'DPS_Base_Plugin', 'enqueue_frontend_assets' ) ) {
            DPS_Base_Plugin::enqueue_frontend_assets();
        }

        // CSS adicional do add-on para ajustes de layout na página dedicada
        $addon_url = plugin_dir_url( __FILE__ );
        $version   = '1.2.1';

        wp_enqueue_style(
            'dps-booking-addon',
            $addon_url . 'assets/css/booking-addon.css',
            [ 'dps-base' ],
            $version
        );
    }

    /**
     * Verifica se o usuário atual possui permissão para acessar a página de agendamentos.
     *
     * @since 1.1.0
     * @return bool
     */
    private function can_access() {
        return current_user_can( 'manage_options' ) 
            || current_user_can( 'dps_manage_clients' )
            || current_user_can( 'dps_manage_pets' )
            || current_user_can( 'dps_manage_appointments' );
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
     * Renderiza a página de agendamentos.
     *
     * Reutiliza a funcionalidade do Painel de Gestão DPS (aba Agendamentos).
     *
     * @since 1.0.0
     * @since 1.1.0 Refatorado para usar a funcionalidade do Painel de Gestão DPS.
     * @since 1.2.0 Adicionado suporte para página de confirmação.
     * @return string HTML do formulário.
     */
    public function render_booking_form() {
        // Evita renderizar durante requisições REST API ou AJAX
        if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || wp_doing_ajax() ) {
            return '';
        }

        // Desabilita cache da página
        if ( class_exists( 'DPS_Cache_Control' ) ) {
            DPS_Cache_Control::force_no_cache();
        }

        // Verifica permissões
        if ( ! is_user_logged_in() ) {
            $login_url = wp_login_url( $this->get_booking_page_url() );
            return '<div class="dps-booking-access-denied">' .
                   '<p>' . esc_html__( 'Você precisa estar logado para acessar esta página.', 'dps-booking-addon' ) . '</p>' .
                   '<p><a href="' . esc_url( $login_url ) . '" class="button">' . esc_html__( 'Fazer login', 'dps-booking-addon' ) . '</a></p>' .
                   '</div>';
        }

        if ( ! $this->can_access() ) {
            return '<div class="dps-booking-access-denied">' .
                   '<p>' . esc_html__( 'Você não tem permissão para acessar esta página.', 'dps-booking-addon' ) . '</p>' .
                   '<p>' . esc_html__( 'Esta funcionalidade é restrita a administradores e usuários autorizados.', 'dps-booking-addon' ) . '</p>' .
                   '</div>';
        }

        // Verifica se há confirmação de agendamento a exibir
        $transient_key = 'dps_booking_confirmation_' . get_current_user_id();
        $confirmation_data = get_transient( $transient_key );
        
        if ( $confirmation_data && ! empty( $confirmation_data['appointment_id'] ) ) {
            // Remove o transient para não exibir novamente em refresh
            delete_transient( $transient_key );
            
            ob_start();
            echo '<div class="dps-booking-wrapper dps-panel">';
            
            // Exibe mensagens de feedback
            if ( class_exists( 'DPS_Message_Helper' ) ) {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- DPS_Message_Helper::display_messages() returns pre-escaped safe HTML
                echo DPS_Message_Helper::display_messages();
            }
            
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_confirmation_page() returns pre-escaped safe HTML
            echo $this->render_confirmation_page( $confirmation_data );
            echo '</div>';
            return ob_get_clean();
        }

        ob_start();

        echo '<div class="dps-booking-wrapper dps-panel">';

        // Exibe mensagens de feedback
        if ( class_exists( 'DPS_Message_Helper' ) ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- DPS_Message_Helper::display_messages() returns pre-escaped safe HTML
            echo DPS_Message_Helper::display_messages();
        }

        // Renderiza a seção de agendamentos usando o método do plugin base
        if ( class_exists( 'DPS_Base_Frontend' ) ) {
            // Usa reflection para acessar o método privado section_agendas
            // Alternativa: invocar os métodos públicos necessários
            echo $this->render_appointments_section();
        } else {
            echo '<div class="dps-notice dps-notice--error">';
            echo '<p>' . esc_html__( 'Erro: O plugin base não está carregado corretamente.', 'dps-booking-addon' ) . '</p>';
            echo '</div>';
        }

        echo '</div>';

        return ob_get_clean();
    }

    /**
     * Renderiza a seção de agendamentos replicando a funcionalidade do Painel de Gestão DPS.
     *
     * @since 1.1.0
     * @return string HTML da seção de agendamentos.
     */
    private function render_appointments_section() {
        // Prepara dados necessários para o formulário
        $clients    = $this->get_clients();
        $pets_query = $this->get_pets();
        $pets       = $pets_query->posts;
        $pet_pages  = (int) max( 1, $pets_query->max_num_pages );

        // Detecta edição de agendamento
        $edit_id = 0;
        $dps_edit_param = isset( $_GET['dps_edit'] ) ? sanitize_text_field( wp_unslash( $_GET['dps_edit'] ) ) : '';
        if ( 'appointment' === $dps_edit_param && isset( $_GET['id'] ) ) {
            $edit_id = absint( $_GET['id'] );
        }

        // Detecta duplicação de agendamento
        $duplicate_id = 0;
        $is_duplicate = false;
        $dps_duplicate_param = isset( $_GET['dps_duplicate'] ) ? sanitize_text_field( wp_unslash( $_GET['dps_duplicate'] ) ) : '';
        if ( 'appointment' === $dps_duplicate_param && isset( $_GET['id'] ) ) {
            $duplicate_id = absint( $_GET['id'] );
            $is_duplicate = true;
        }

        // Carrega dados do agendamento em edição ou duplicação
        $editing = null;
        $meta    = [];
        $source_id = $edit_id ? $edit_id : $duplicate_id;

        if ( $source_id ) {
            $editing = get_post( $source_id );
            if ( $editing && 'dps_agendamento' === $editing->post_type ) {
                $meta = [
                    'client_id'          => get_post_meta( $source_id, 'appointment_client_id', true ),
                    'pet_id'             => get_post_meta( $source_id, 'appointment_pet', true ),
                    'date'               => $is_duplicate ? '' : get_post_meta( $source_id, 'appointment_date', true ),
                    'time'               => $is_duplicate ? '' : get_post_meta( $source_id, 'appointment_time', true ),
                    'notes'              => get_post_meta( $source_id, 'appointment_notes', true ),
                    'taxidog'            => get_post_meta( $source_id, 'appointment_taxidog', true ),
                    'taxidog_price'      => get_post_meta( $source_id, 'appointment_taxidog_price', true ),
                    'tosa'               => get_post_meta( $source_id, 'appointment_tosa', true ),
                    'tosa_price'         => get_post_meta( $source_id, 'appointment_tosa_price', true ),
                    'tosa_occurrence'    => get_post_meta( $source_id, 'appointment_tosa_occurrence', true ),
                    'past_payment_status'=> get_post_meta( $source_id, 'past_payment_status', true ),
                    'past_payment_value' => get_post_meta( $source_id, 'past_payment_value', true ),
                ];
                $appt_type = get_post_meta( $source_id, 'appointment_type', true );
                $meta['appointment_type'] = $appt_type ?: 'simple';
            }
        }

        // Cliente e pet pré-selecionados via URL
        $pref_client = isset( $_GET['client_id'] ) ? absint( $_GET['client_id'] ) : 0;
        $pref_pet    = isset( $_GET['pet_id'] ) ? absint( $_GET['pet_id'] ) : 0;

        // URLs - use safe URL building instead of raw REQUEST_URI
        $base_url    = $this->get_booking_page_url();
        $current_url = $base_url;
        
        // Build current URL from known safe components
        $current_args = [];
        if ( $edit_id ) {
            $current_args['dps_edit'] = 'appointment';
            $current_args['id'] = $edit_id;
        } elseif ( $duplicate_id ) {
            $current_args['dps_duplicate'] = 'appointment';
            $current_args['id'] = $duplicate_id;
        }
        if ( $pref_client ) {
            $current_args['client_id'] = $pref_client;
        }
        if ( $pref_pet ) {
            $current_args['pet_id'] = $pref_pet;
        }
        if ( ! empty( $current_args ) ) {
            $current_url = add_query_arg( $current_args, $base_url );
        }

        ob_start();

        // Início da seção
        echo '<div class="dps-section active" id="dps-section-agendas">';

        // Formulário
        echo '<div class="dps-surface dps-surface--info">';
        echo '<div class="dps-surface__title">';
        echo '<span>📝</span>';
        echo $edit_id ? esc_html__( 'Editar Agendamento', 'dps-booking-addon' ) : esc_html__( 'Novo Agendamento', 'dps-booking-addon' );
        echo '</div>';

        // Mensagem de duplicação
        if ( $is_duplicate ) {
            echo '<div class="dps-alert dps-alert--info" role="status" aria-live="polite">';
            echo '<strong>' . esc_html__( 'Duplicando agendamento', 'dps-booking-addon' ) . '</strong><br>';
            echo esc_html__( 'Os dados do agendamento anterior foram copiados. Selecione uma nova data e horário, então salve para criar o novo agendamento.', 'dps-booking-addon' );
            echo '</div>';
        }

        echo '<form method="post" class="dps-form">';
        echo '<input type="hidden" name="dps_action" value="save_appointment">';
        wp_nonce_field( 'dps_action', 'dps_nonce_agendamentos' );
        echo '<input type="hidden" name="dps_redirect_url" value="' . esc_attr( $current_url ) . '">';
        if ( $edit_id ) {
            echo '<input type="hidden" name="appointment_id" value="' . esc_attr( $edit_id ) . '">';
        }

        // FIELDSET 1: Tipo de Agendamento
        echo '<fieldset class="dps-fieldset">';
        echo '<legend class="dps-fieldset__legend">' . esc_html__( 'Tipo de Agendamento', 'dps-booking-addon' ) . '</legend>';
        
        $appt_type = isset( $meta['appointment_type'] ) ? $meta['appointment_type'] : 'simple';
        echo '<div class="dps-radio-group">';
        
        echo '<label class="dps-radio-option">';
        echo '<input type="radio" name="appointment_type" value="simple" ' . checked( $appt_type, 'simple', false ) . '>';
        echo '<div class="dps-radio-label">';
        echo '<strong>' . esc_html__( 'Agendamento Simples', 'dps-booking-addon' ) . '</strong>';
        echo '<p>' . esc_html__( 'Atendimento único, sem recorrência', 'dps-booking-addon' ) . '</p>';
        echo '</div>';
        echo '</label>';
        
        echo '<label class="dps-radio-option">';
        echo '<input type="radio" name="appointment_type" value="subscription" ' . checked( $appt_type, 'subscription', false ) . '>';
        echo '<div class="dps-radio-label">';
        echo '<strong>' . esc_html__( 'Agendamento de Assinatura', 'dps-booking-addon' ) . '</strong>';
        echo '<p>' . esc_html__( 'Atendimentos recorrentes (semanal ou quinzenal)', 'dps-booking-addon' ) . '</p>';
        echo '</div>';
        echo '</label>';
        
        echo '<label class="dps-radio-option">';
        echo '<input type="radio" name="appointment_type" value="past" ' . checked( $appt_type, 'past', false ) . '>';
        echo '<div class="dps-radio-label">';
        echo '<strong>' . esc_html__( 'Agendamento Passado', 'dps-booking-addon' ) . '</strong>';
        echo '<p>' . esc_html__( 'Registrar atendimento já realizado anteriormente', 'dps-booking-addon' ) . '</p>';
        echo '</div>';
        echo '</label>';
        
        echo '</div>';
        
        // Campo: frequência para assinaturas
        $freq_display = ( $appt_type === 'subscription' ) ? 'block' : 'none';
        echo '<div id="dps-appointment-frequency-wrapper" class="dps-conditional-field" style="display:' . esc_attr( $freq_display ) . ';">';
        echo '<label for="dps-appointment-frequency">' . esc_html__( 'Frequência', 'dps-booking-addon' ) . ' <span class="dps-required">*</span></label>';
        echo '<select name="appointment_frequency" id="dps-appointment-frequency">';
        echo '<option value="semanal">' . esc_html__( 'Semanal', 'dps-booking-addon' ) . '</option>';
        echo '<option value="quinzenal">' . esc_html__( 'Quinzenal', 'dps-booking-addon' ) . '</option>';
        echo '</select>';
        echo '</div>';
        
        echo '</fieldset>';

        // FIELDSET 2: Cliente e Pet(s)
        echo '<fieldset class="dps-fieldset">';
        echo '<legend class="dps-fieldset__legend">' . esc_html__( 'Cliente e Pet(s)', 'dps-booking-addon' ) . '</legend>';
        
        // Cliente
        if ( ! $edit_id && $pref_client ) {
            $meta['client_id'] = $pref_client;
        }
        $sel_client = isset( $meta['client_id'] ) ? $meta['client_id'] : '';
        
        echo '<div class="dps-form-field">';
        echo '<label for="dps-appointment-cliente">' . esc_html__( 'Cliente', 'dps-booking-addon' ) . ' <span class="dps-required">*</span></label>';
        echo '<select name="appointment_client_id" id="dps-appointment-cliente" class="dps-client-select" required>';
        echo '<option value="">' . esc_html__( 'Selecione...', 'dps-booking-addon' ) . '</option>';
        foreach ( $clients as $client ) {
            $selected = ( (string) $client->ID === (string) $sel_client ) ? ' selected' : '';
            echo '<option value="' . esc_attr( $client->ID ) . '"' . $selected . '>' . esc_html( $client->post_title ) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        
        // Pets
        if ( ! $edit_id && $pref_pet ) {
            $meta['pet_id'] = $pref_pet;
        }
        $sel_pets = [];
        if ( isset( $meta['pet_id'] ) && $meta['pet_id'] ) {
            $sel_pets[] = (string) $meta['pet_id'];
        }
        if ( $edit_id ) {
            $multi_meta = get_post_meta( $edit_id, 'appointment_pet_ids', true );
            if ( $multi_meta && is_array( $multi_meta ) ) {
                $sel_pets = array_map( 'strval', $multi_meta );
            }
        }
        
        echo '<div id="dps-appointment-pet-wrapper" class="dps-pet-picker" data-current-page="1" data-total-pages="' . esc_attr( $pet_pages ) . '">';
        echo '<p id="dps-pet-selector-label"><strong>' . esc_html__( 'Pet(s)', 'dps-booking-addon' ) . ' <span class="dps-required">*</span></strong>';
        echo '<span id="dps-pet-counter" class="dps-selection-counter" style="display:none;">0 ' . esc_html__( 'selecionados', 'dps-booking-addon' ) . '</span></p>';
        
        echo '<div class="dps-pet-picker-actions">';
        echo '<button type="button" class="button button-secondary dps-pet-toggle" data-action="select">' . esc_html__( 'Selecionar todos', 'dps-booking-addon' ) . '</button> ';
        echo '<button type="button" class="button button-secondary dps-pet-toggle" data-action="clear">' . esc_html__( 'Limpar seleção', 'dps-booking-addon' ) . '</button>';
        echo '</div>';
        
        echo '<div id="dps-appointment-pet-list" class="dps-pet-list" role="group" aria-labelledby="dps-pet-selector-label">';
        foreach ( $pets as $pet ) {
            $owner_id   = get_post_meta( $pet->ID, 'owner_id', true );
            $owner_post = $owner_id ? get_post( $owner_id ) : null;
            $owner_name = $owner_post ? $owner_post->post_title : '';
            $size       = get_post_meta( $pet->ID, 'pet_size', true );
            $breed      = get_post_meta( $pet->ID, 'pet_breed', true );
            $sel        = in_array( (string) $pet->ID, $sel_pets, true ) ? 'checked' : '';
            $size_attr  = $size ? ' data-size="' . esc_attr( strtolower( $size ) ) . '"' : '';
            $owner_attr = $owner_id ? ' data-owner="' . esc_attr( $owner_id ) . '"' : '';
            $search_blob = strtolower( $pet->post_title . ' ' . $breed . ' ' . $owner_name );
            
            echo '<label class="dps-pet-option"' . $owner_attr . $size_attr . ' data-search="' . esc_attr( $search_blob ) . '">';
            echo '<input type="checkbox" class="dps-pet-checkbox" name="appointment_pet_ids[]" value="' . esc_attr( $pet->ID ) . '" ' . $sel . '>';
            echo '<span class="dps-pet-name">' . esc_html( $pet->post_title ) . '</span>';
            if ( $breed ) {
                echo '<span class="dps-pet-breed"> – ' . esc_html( $breed ) . '</span>';
            }
            if ( $size ) {
                echo '<span class="dps-pet-size"> · ' . esc_html( ucfirst( $size ) ) . '</span>';
            }
            echo '</label>';
        }
        echo '</div>';
        
        if ( $pet_pages > 1 ) {
            echo '<p><button type="button" class="button dps-pet-load-more" data-next-page="2" data-loading="false">' . esc_html__( 'Carregar mais pets', 'dps-booking-addon' ) . '</button></p>';
        }
        
        echo '<p id="dps-pet-summary" class="dps-field-hint" style="display:none;"></p>';
        echo '<p id="dps-no-pets-message" class="dps-field-hint" style="display:none;">' . esc_html__( 'Nenhum pet disponível para o cliente selecionado.', 'dps-booking-addon' ) . '</p>';
        echo '</div>';
        
        echo '</fieldset>';

        // FIELDSET 3: Data e Horário
        echo '<fieldset class="dps-fieldset">';
        echo '<legend class="dps-fieldset__legend">' . esc_html__( 'Data e Horário', 'dps-booking-addon' ) . '</legend>';
        
        $date_val = isset( $meta['date'] ) ? $meta['date'] : '';
        $time_val = isset( $meta['time'] ) ? $meta['time'] : '';
        
        echo '<div class="dps-form-row dps-form-row--2col">';
        echo '<div class="dps-form-field">';
        echo '<label for="appointment_date">' . esc_html__( 'Data', 'dps-booking-addon' ) . ' <span class="dps-required">*</span></label>';
        echo '<input type="date" id="appointment_date" name="appointment_date" value="' . esc_attr( $date_val ) . '" required>';
        echo '<p class="dps-field-hint">' . esc_html__( 'Horários disponíveis serão carregados após escolher a data', 'dps-booking-addon' ) . '</p>';
        echo '</div>';
        
        echo '<div class="dps-form-field">';
        echo '<label for="appointment_time">' . esc_html__( 'Horário', 'dps-booking-addon' ) . ' <span class="dps-required">*</span></label>';
        echo '<select id="appointment_time" name="appointment_time" required>';
        if ( $time_val ) {
            echo '<option value="' . esc_attr( $time_val ) . '" selected>' . esc_html( $time_val ) . '</option>';
        } else {
            echo '<option value="">' . esc_html__( 'Escolha uma data primeiro', 'dps-booking-addon' ) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        echo '</div>';
        
        echo '</fieldset>';

        // SEÇÃO: TaxiDog
        $taxidog = isset( $meta['taxidog'] ) ? $meta['taxidog'] : '';
        $taxidog_price_val = isset( $meta['taxidog_price'] ) ? $meta['taxidog_price'] : '';
        
        echo '<div class="dps-taxidog-section">';
        echo '<div class="dps-taxidog-card" data-taxidog-active="' . ( $taxidog ? '1' : '0' ) . '">';
        echo '<div class="dps-taxidog-card__header">';
        echo '<div class="dps-taxidog-card__icon-title">';
        echo '<span class="dps-taxidog-icon" aria-hidden="true">🚗</span>';
        echo '<span class="dps-taxidog-title">' . esc_html__( 'Solicitar TaxiDog?', 'dps-booking-addon' ) . '</span>';
        echo '</div>';
        echo '<label class="dps-toggle-switch">';
        echo '<input type="checkbox" id="dps-taxidog-toggle" name="appointment_taxidog" value="1" ' . checked( $taxidog, '1', false ) . '>';
        echo '<span class="dps-toggle-slider"></span>';
        echo '</label>';
        echo '</div>';
        echo '<p class="dps-taxidog-description">' . esc_html__( 'Serviço de transporte para buscar e/ou levar o pet', 'dps-booking-addon' ) . '</p>';
        
        echo '<div id="dps-taxidog-extra" class="dps-taxidog-card__value" style="display:' . ( $taxidog ? 'flex' : 'none' ) . ';">';
        echo '<label for="dps-taxidog-price" class="dps-taxidog-value-label">' . esc_html__( 'Valor do serviço:', 'dps-booking-addon' ) . '</label>';
        echo '<div class="dps-input-with-prefix">';
        echo '<span class="dps-input-prefix">R$</span>';
        echo '<input type="number" id="dps-taxidog-price" name="appointment_taxidog_price" step="0.01" min="0" value="' . esc_attr( $taxidog_price_val ) . '" class="dps-input-money dps-taxidog-price-input" placeholder="0,00">';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // FIELDSET 4: Serviços e Extras
        echo '<fieldset class="dps-fieldset">';
        echo '<legend class="dps-fieldset__legend">' . esc_html__( 'Serviços e Extras', 'dps-booking-addon' ) . '</legend>';
        
        // Campo: indicativo de necessidade de tosa (apenas para assinaturas)
        // Card de tosa com design similar ao TaxiDog para melhor UX
        $tosa       = $meta['tosa'] ?? '';
        $tosa_price = $meta['tosa_price'] ?? '';
        $tosa_occ   = $meta['tosa_occurrence'] ?? '1';
        $tosa_price_val = $tosa_price !== '' ? $tosa_price : '30';
        
        echo '<div id="dps-tosa-wrapper" class="dps-tosa-section" style="display:none;">';
        echo '<div class="dps-tosa-card" data-tosa-active="' . ( '1' === $tosa ? '1' : '0' ) . '">';
        echo '<div class="dps-tosa-card__header">';
        echo '<div class="dps-tosa-card__icon-title">';
        echo '<span class="dps-tosa-icon" aria-hidden="true">✂️</span>';
        echo '<span class="dps-tosa-title">' . esc_html__( 'Adicionar tosa?', 'dps-booking-addon' ) . '</span>';
        echo '</div>';
        echo '<label class="dps-toggle-switch">';
        echo '<input type="checkbox" id="dps-tosa-toggle" name="appointment_tosa" value="1" ' . checked( $tosa, '1', false ) . '>';
        echo '<span class="dps-toggle-slider"></span>';
        echo '</label>';
        echo '</div>';
        echo '<p class="dps-tosa-description">' . esc_html__( 'Serviço de tosa adicional em um dos atendimentos da assinatura', 'dps-booking-addon' ) . '</p>';
        
        // Campos de configuração da tosa (visíveis quando ativo)
        echo '<div id="dps-tosa-fields" class="dps-tosa-card__fields" style="display:' . ( '1' === $tosa ? 'grid' : 'none' ) . ';">';
        
        // Preço da tosa
        echo '<div class="dps-tosa-field">';
        echo '<label for="dps-tosa-price" class="dps-tosa-field-label">' . esc_html__( 'Valor da tosa:', 'dps-booking-addon' ) . '</label>';
        echo '<div class="dps-input-with-prefix">';
        echo '<span class="dps-input-prefix">R$</span>';
        echo '<input type="number" step="0.01" min="0" id="dps-tosa-price" name="appointment_tosa_price" value="' . esc_attr( $tosa_price_val ) . '" class="dps-input-money dps-tosa-price-input" placeholder="30,00">';
        echo '</div>';
        echo '</div>';
        
        // Ocorrência da tosa (selecionada via JS conforme frequência)
        echo '<div class="dps-tosa-field">';
        echo '<label for="appointment_tosa_occurrence" class="dps-tosa-field-label">' . esc_html__( 'Em qual atendimento:', 'dps-booking-addon' ) . '</label>';
        echo '<select name="appointment_tosa_occurrence" id="appointment_tosa_occurrence" class="dps-tosa-occurrence-select" data-current="' . esc_attr( $tosa_occ ) . '"></select>';
        echo '<p class="dps-tosa-field-hint">' . esc_html__( 'Escolha o atendimento em que a tosa será realizada', 'dps-booking-addon' ) . '</p>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Hook para add-ons injetarem campos extras (ex.: serviços)
        do_action( 'dps_base_appointment_fields', $edit_id, $meta );
        
        echo '</fieldset>';

        // FIELDSET 5: Atribuição (se houver hook)
        $has_assignment = has_action( 'dps_base_appointment_assignment_fields' );
        if ( $has_assignment ) {
            echo '<fieldset class="dps-fieldset dps-assignment-fieldset">';
            echo '<legend class="dps-fieldset__legend">' . esc_html__( 'Atribuição', 'dps-booking-addon' ) . '</legend>';
            do_action( 'dps_base_appointment_assignment_fields', $edit_id, $meta );
            echo '</fieldset>';
        }

        // FIELDSET 6: Informações de Pagamento (apenas para agendamentos passados)
        $past_payment_status = isset( $meta['past_payment_status'] ) ? $meta['past_payment_status'] : '';
        $past_payment_value  = isset( $meta['past_payment_value'] ) ? $meta['past_payment_value'] : '';
        $past_display = ( $appt_type === 'past' ) ? 'block' : 'none';
        
        echo '<fieldset id="dps-past-payment-wrapper" class="dps-fieldset dps-conditional-field" style="display:' . esc_attr( $past_display ) . ';">';
        echo '<legend class="dps-fieldset__legend">' . esc_html__( 'Informações de Pagamento', 'dps-booking-addon' ) . '</legend>';
        
        echo '<div class="dps-form-field">';
        echo '<label for="past_payment_status">' . esc_html__( 'Status do Pagamento', 'dps-booking-addon' ) . ' <span class="dps-required">*</span></label>';
        echo '<select name="past_payment_status" id="past_payment_status">';
        echo '<option value="">' . esc_html__( 'Selecione...', 'dps-booking-addon' ) . '</option>';
        echo '<option value="paid" ' . selected( $past_payment_status, 'paid', false ) . '>' . esc_html__( 'Pago', 'dps-booking-addon' ) . '</option>';
        echo '<option value="pending" ' . selected( $past_payment_status, 'pending', false ) . '>' . esc_html__( 'Pendente', 'dps-booking-addon' ) . '</option>';
        echo '</select>';
        echo '</div>';
        
        $payment_value_display = ( $past_payment_status === 'pending' ) ? 'block' : 'none';
        echo '<div id="dps-past-payment-value-wrapper" class="dps-form-field dps-conditional-field" style="display:' . esc_attr( $payment_value_display ) . ';">';
        echo '<label for="past_payment_value">' . esc_html__( 'Valor Pendente (R$)', 'dps-booking-addon' ) . ' <span class="dps-required">*</span></label>';
        echo '<input type="number" step="0.01" min="0" id="past_payment_value" name="past_payment_value" value="' . esc_attr( $past_payment_value ) . '" class="dps-input-money" placeholder="0,00">';
        echo '</div>';
        
        echo '</fieldset>';

        // FIELDSET 7: Observações
        echo '<fieldset class="dps-fieldset">';
        echo '<legend class="dps-fieldset__legend">' . esc_html__( 'Observações e Notas', 'dps-booking-addon' ) . '</legend>';
        
        $notes_val = isset( $meta['notes'] ) ? $meta['notes'] : '';
        echo '<label for="appointment_notes">' . esc_html__( 'Observações', 'dps-booking-addon' ) . '</label>';
        echo '<textarea id="appointment_notes" name="appointment_notes" rows="3" placeholder="' . esc_attr__( 'Instruções especiais, preferências do cliente, etc.', 'dps-booking-addon' ) . '">' . esc_textarea( $notes_val ) . '</textarea>';
        echo '<p class="dps-field-hint">' . esc_html__( 'Opcional - use este campo para anotações internas', 'dps-booking-addon' ) . '</p>';
        
        echo '</fieldset>';

        // Resumo do agendamento
        echo '<div class="dps-appointment-summary" aria-live="polite">';
        echo '<h3><span class="dps-appointment-summary__icon" aria-hidden="true">📋</span>' . esc_html__( 'Resumo do agendamento', 'dps-booking-addon' ) . '</h3>';
        echo '<p class="dps-appointment-summary__empty">' . esc_html__( 'Preencha cliente, pet, data e horário para ver o resumo aqui.', 'dps-booking-addon' ) . '</p>';
        echo '<ul class="dps-appointment-summary__list" hidden>';
        echo '<li><strong>' . esc_html__( 'Cliente:', 'dps-booking-addon' ) . '</strong> <span data-summary="client">-</span></li>';
        echo '<li><strong>' . esc_html__( 'Pets:', 'dps-booking-addon' ) . '</strong> <span data-summary="pets">-</span></li>';
        echo '<li><strong>' . esc_html__( 'Data:', 'dps-booking-addon' ) . '</strong> <span data-summary="date">-</span></li>';
        echo '<li><strong>' . esc_html__( 'Horário:', 'dps-booking-addon' ) . '</strong> <span data-summary="time">-</span></li>';
        echo '<li class="dps-appointment-summary__subscription-info" style="display:none;"><strong>' . esc_html__( 'Frequência:', 'dps-booking-addon' ) . '</strong> <span data-summary="frequency">-</span></li>';
        echo '<li class="dps-appointment-summary__subscription-info" style="display:none;"><strong>' . esc_html__( 'Próximas datas:', 'dps-booking-addon' ) . '</strong> <span data-summary="future-dates">-</span></li>';
        echo '<li><strong>' . esc_html__( 'Serviços:', 'dps-booking-addon' ) . '</strong> <span data-summary="services">-</span></li>';
        echo '<li class="dps-appointment-summary__extras" style="display:none;"><strong>' . esc_html__( 'Extras:', 'dps-booking-addon' ) . '</strong> <span data-summary="extras">-</span></li>';
        echo '<li><strong>' . esc_html__( 'Valor estimado:', 'dps-booking-addon' ) . '</strong> <span data-summary="price">R$ 0,00</span></li>';
        echo '<li class="dps-appointment-summary__notes"><strong>' . esc_html__( 'Observações:', 'dps-booking-addon' ) . '</strong> <span data-summary="notes">-</span></li>';
        echo '</ul>';
        echo '</div>';

        // Botões de ação
        $btn_text = $edit_id ? esc_html__( 'Atualizar Agendamento', 'dps-booking-addon' ) : esc_html__( 'Salvar Agendamento', 'dps-booking-addon' );
        echo '<div class="dps-form-actions">';
        echo '<button type="submit" class="dps-btn dps-btn--primary dps-submit-btn dps-appointment-submit">✓ ' . $btn_text . '</button>';
        if ( $edit_id ) {
            $cancel_url = remove_query_arg( [ 'dps_edit', 'id' ], $current_url );
            echo '<a href="' . esc_url( $cancel_url ) . '" class="dps-btn dps-btn--secondary">' . esc_html__( 'Cancelar', 'dps-booking-addon' ) . '</a>';
        }
        echo '</div>';

        // Bloco de erros de validação
        echo '<div class="dps-form-error" role="alert" aria-live="assertive" hidden></div>';

        echo '</form>';
        echo '</div>'; // .dps-surface

        echo '</div>'; // .dps-section

        return ob_get_clean();
    }

    /**
     * Obtém lista completa de clientes cadastrados.
     *
     * @since 1.1.0
     * @return array Lista de posts do tipo dps_cliente.
     */
    private function get_clients() {
        return get_posts( [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );
    }

    /**
     * Obtém lista paginada de pets.
     *
     * @since 1.1.0
     * @param int $page Número da página.
     * @return WP_Query Objeto de consulta com pets paginados.
     */
    private function get_pets( $page = 1 ) {
        $per_page = defined( 'DPS_BASE_PETS_PER_PAGE' ) ? absint( DPS_BASE_PETS_PER_PAGE ) : 50;
        // Validate per_page to prevent excessive memory usage
        $per_page = min( max( $per_page, 1 ), 200 );
        
        return new WP_Query( [
            'post_type'      => 'dps_pet',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );
    }

    /**
     * Captura dados do agendamento salvo para exibição na página de confirmação.
     *
     * Note: This hook is called after nonce verification in save_appointment(),
     * so we don't need to verify nonce again here. The $_POST access is only
     * to check the redirect URL destination.
     *
     * @since 1.2.0
     * @param int    $appointment_id ID do agendamento salvo.
     * @param string $appointment_type Tipo do agendamento.
     */
    public function capture_saved_appointment( $appointment_id, $appointment_type ) {
        // Verifica se estamos na página de agendamento
        $booking_page_id = (int) get_option( 'dps_booking_page_id' );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified in save_appointment()
        $redirect_url = isset( $_POST['dps_redirect_url'] ) ? esc_url_raw( wp_unslash( $_POST['dps_redirect_url'] ) ) : '';
        
        // Se o redirect contém a página de booking, armazena para confirmação
        $booking_permalink = $booking_page_id ? get_permalink( $booking_page_id ) : '';
        if ( $booking_page_id && $booking_permalink && strpos( $redirect_url, $booking_permalink ) !== false ) {
            $transient_key = 'dps_booking_confirmation_' . get_current_user_id();
            $data = [
                'appointment_id'   => $appointment_id,
                'appointment_type' => $appointment_type,
                'timestamp'        => time(),
            ];
            set_transient( $transient_key, $data, 5 * MINUTE_IN_SECONDS );
        }
    }

    /**
     * Renderiza a página de confirmação de agendamento.
     *
     * @since 1.2.0
     * @param array $confirmation_data Dados da confirmação.
     * @return string HTML da página de confirmação, ou mensagem de erro se inválido.
     */
    private function render_confirmation_page( $confirmation_data ) {
        $appointment_id = (int) $confirmation_data['appointment_id'];
        $appointment = get_post( $appointment_id );
        
        if ( ! $appointment || 'dps_agendamento' !== $appointment->post_type ) {
            return '<div class="dps-notice dps-notice--warning"><p>' . 
                   esc_html__( 'Não foi possível carregar os detalhes do agendamento.', 'dps-booking-addon' ) . 
                   ' <a href="' . esc_url( $this->get_booking_page_url() ) . '">' . 
                   esc_html__( 'Criar novo agendamento', 'dps-booking-addon' ) . '</a></p></div>';
        }
        
        // Coleta dados do agendamento
        $client_id = (int) get_post_meta( $appointment_id, 'appointment_client_id', true );
        $pet_id    = (int) get_post_meta( $appointment_id, 'appointment_pet_id', true );
        $date      = get_post_meta( $appointment_id, 'appointment_date', true );
        $time      = get_post_meta( $appointment_id, 'appointment_time', true );
        $type      = get_post_meta( $appointment_id, 'appointment_type', true ) ?: 'simple';
        $notes     = get_post_meta( $appointment_id, 'appointment_notes', true );
        $status    = get_post_meta( $appointment_id, 'appointment_status', true ) ?: 'pendente';
        
        // Dados do cliente
        $client = get_post( $client_id );
        $client_name = $client ? $client->post_title : __( 'Cliente não encontrado', 'dps-booking-addon' );
        
        // Dados do pet (pode ser múltiplos)
        $pet_ids = get_post_meta( $appointment_id, 'appointment_pet_ids', true );
        if ( empty( $pet_ids ) && $pet_id ) {
            $pet_ids = [ $pet_id ];
        }
        $pet_names = [];
        if ( is_array( $pet_ids ) ) {
            foreach ( $pet_ids as $pid ) {
                $pet = get_post( $pid );
                if ( $pet ) {
                    $pet_names[] = $pet->post_title;
                }
            }
        }
        $pets_display = ! empty( $pet_names ) ? implode( ', ', $pet_names ) : __( 'Pet não encontrado', 'dps-booking-addon' );
        
        // Formata data
        $date_obj = DateTime::createFromFormat( 'Y-m-d', $date );
        $date_formatted = ( $date_obj !== false ) ? $date_obj->format( 'd/m/Y' ) : $date;
        
        // Tipo de agendamento em texto
        $type_labels = [
            'simple'       => __( 'Agendamento Simples', 'dps-booking-addon' ),
            'subscription' => __( 'Agendamento de Assinatura', 'dps-booking-addon' ),
            'past'         => __( 'Agendamento Passado', 'dps-booking-addon' ),
        ];
        $type_label = isset( $type_labels[ $type ] ) ? $type_labels[ $type ] : $type;
        
        // Status em texto
        $status_labels = [
            'pendente'        => __( 'Pendente', 'dps-booking-addon' ),
            'finalizado'      => __( 'Finalizado', 'dps-booking-addon' ),
            'finalizado_pago' => __( 'Finalizado e Pago', 'dps-booking-addon' ),
            'cancelado'       => __( 'Cancelado', 'dps-booking-addon' ),
        ];
        $status_label = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : $status;
        
        // Valor total (se disponível)
        $total_value = 0;
        if ( 'subscription' === $type ) {
            $total_value = (float) get_post_meta( $appointment_id, 'subscription_total_value', true );
        } else {
            $total_value = (float) get_post_meta( $appointment_id, 'appointment_total_value', true );
        }
        
        ob_start();
        
        echo '<div class="dps-booking-confirmation">';
        
        // Ícone de sucesso
        echo '<div class="dps-confirmation-header">';
        echo '<span class="dps-confirmation-icon" aria-hidden="true">✅</span>';
        echo '<h2 class="dps-confirmation-title">' . esc_html__( 'Agendamento Confirmado!', 'dps-booking-addon' ) . '</h2>';
        echo '<p class="dps-confirmation-subtitle">' . esc_html__( 'Seu agendamento foi salvo com sucesso.', 'dps-booking-addon' ) . '</p>';
        echo '</div>';
        
        // Dados do agendamento
        echo '<div class="dps-confirmation-details">';
        echo '<h3>' . esc_html__( 'Detalhes do Agendamento', 'dps-booking-addon' ) . '</h3>';
        echo '<dl class="dps-confirmation-list">';
        
        echo '<dt>' . esc_html__( 'Tipo:', 'dps-booking-addon' ) . '</dt>';
        echo '<dd>' . esc_html( $type_label ) . '</dd>';
        
        echo '<dt>' . esc_html__( 'Cliente:', 'dps-booking-addon' ) . '</dt>';
        echo '<dd>' . esc_html( $client_name ) . '</dd>';
        
        echo '<dt>' . esc_html__( 'Pet(s):', 'dps-booking-addon' ) . '</dt>';
        echo '<dd>' . esc_html( $pets_display ) . '</dd>';
        
        echo '<dt>' . esc_html__( 'Data:', 'dps-booking-addon' ) . '</dt>';
        echo '<dd>' . esc_html( $date_formatted ) . '</dd>';
        
        echo '<dt>' . esc_html__( 'Horário:', 'dps-booking-addon' ) . '</dt>';
        echo '<dd>' . esc_html( $time ) . '</dd>';
        
        echo '<dt>' . esc_html__( 'Status:', 'dps-booking-addon' ) . '</dt>';
        echo '<dd><span class="dps-status dps-status--' . esc_attr( $status ) . '">' . esc_html( $status_label ) . '</span></dd>';
        
        if ( $total_value > 0 ) {
            echo '<dt>' . esc_html__( 'Valor:', 'dps-booking-addon' ) . '</dt>';
            echo '<dd><strong>R$ ' . esc_html( number_format( $total_value, 2, ',', '.' ) ) . '</strong></dd>';
        }
        
        if ( $notes ) {
            echo '<dt>' . esc_html__( 'Observações:', 'dps-booking-addon' ) . '</dt>';
            echo '<dd>' . esc_html( $notes ) . '</dd>';
        }
        
        echo '</dl>';
        echo '</div>';
        
        // Botões de ação
        echo '<div class="dps-confirmation-actions">';
        
        // Novo agendamento
        echo '<a href="' . esc_url( $this->get_booking_page_url() ) . '" class="dps-btn dps-btn--primary">';
        echo '<span>➕</span> ' . esc_html__( 'Novo Agendamento', 'dps-booking-addon' );
        echo '</a>';
        
        // Ver cliente (se disponível painel de gestão)
        if ( $client_id && class_exists( 'DPS_Base_Frontend' ) ) {
            $panel_url = get_option( 'dps_panel_page_id' ) ? get_permalink( get_option( 'dps_panel_page_id' ) ) : '';
            if ( $panel_url ) {
                $client_url = add_query_arg( [
                    'dps_tab' => 'clientes',
                    'cliente' => $client_id,
                ], $panel_url );
                echo '<a href="' . esc_url( $client_url ) . '" class="dps-btn dps-btn--secondary">';
                echo '<span>👤</span> ' . esc_html__( 'Ver Cliente', 'dps-booking-addon' );
                echo '</a>';
            }
        }
        
        // Ver agendamentos
        if ( class_exists( 'DPS_Base_Frontend' ) ) {
            $panel_url = get_option( 'dps_panel_page_id' ) ? get_permalink( get_option( 'dps_panel_page_id' ) ) : '';
            if ( $panel_url ) {
                $agenda_url = add_query_arg( 'dps_tab', 'agendas', $panel_url );
                echo '<a href="' . esc_url( $agenda_url ) . '" class="dps-btn dps-btn--outline">';
                echo '<span>📅</span> ' . esc_html__( 'Ver Agenda', 'dps-booking-addon' );
                echo '</a>';
            }
        }
        
        echo '</div>';
        
        echo '</div>';
        
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
