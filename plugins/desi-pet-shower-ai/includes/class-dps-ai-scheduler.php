<?php
/**
 * Integração de Agendamento via Chat do AI Add-on.
 *
 * Permite que clientes verifiquem disponibilidade e solicitem
 * agendamentos diretamente através do chat da IA.
 *
 * @package DPS_AI_Addon
 * @since 1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe de Agendamento via Chat.
 */
class DPS_AI_Scheduler {

    /**
     * Instância única (singleton).
     *
     * @var DPS_AI_Scheduler|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única.
     *
     * @return DPS_AI_Scheduler
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
        // Registra handlers AJAX
        add_action( 'wp_ajax_dps_ai_check_availability', [ $this, 'ajax_check_availability' ] );
        add_action( 'wp_ajax_nopriv_dps_ai_check_availability', [ $this, 'ajax_check_availability' ] );
        add_action( 'wp_ajax_dps_ai_request_appointment', [ $this, 'ajax_request_appointment' ] );
        add_action( 'wp_ajax_nopriv_dps_ai_request_appointment', [ $this, 'ajax_request_appointment' ] );
    }

    /**
     * Handler AJAX para verificar disponibilidade.
     */
    public function ajax_check_availability() {
        // Verifica nonce (não requer capability - chat público)
        if ( ! DPS_Request_Validator::verify_ajax_nonce( 'dps_ai_scheduler' ) ) {
            return;
        }

        $date = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';

        if ( empty( $date ) ) {
            wp_send_json_error( [ 'message' => __( 'Data não informada.', 'dps-ai' ) ] );
        }

        // Valida formato da data
        $date_obj = DateTime::createFromFormat( 'Y-m-d', $date );
        if ( ! $date_obj || $date_obj->format( 'Y-m-d' ) !== $date ) {
            wp_send_json_error( [ 'message' => __( 'Formato de data inválido.', 'dps-ai' ) ] );
        }

        // Verifica se é uma data futura
        $today = new DateTime( current_time( 'Y-m-d' ) );
        if ( $date_obj < $today ) {
            wp_send_json_error( [ 'message' => __( 'Não é possível verificar disponibilidade para datas passadas.', 'dps-ai' ) ] );
        }

        $slots = self::get_available_slots( $date );

        if ( empty( $slots ) ) {
            wp_send_json_success( [
                'available' => false,
                'message'   => __( 'Não há horários disponíveis nesta data. Tente outra data.', 'dps-ai' ),
                'slots'     => [],
            ] );
        }

        wp_send_json_success( [
            'available' => true,
            'message'   => sprintf( __( 'Encontramos %d horários disponíveis!', 'dps-ai' ), count( $slots ) ),
            'slots'     => $slots,
        ] );
    }

    /**
     * Handler AJAX para solicitar agendamento.
     */
    public function ajax_request_appointment() {
        // Verifica nonce (não requer capability - chat público)
        if ( ! DPS_Request_Validator::verify_ajax_nonce( 'dps_ai_scheduler' ) ) {
            return;
        }

        $settings        = get_option( 'dps_ai_settings', [] );
        $scheduling_mode = $settings['scheduling_mode'] ?? 'request';

        $date       = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
        $time       = isset( $_POST['time'] ) ? sanitize_text_field( wp_unslash( $_POST['time'] ) ) : '';
        $service_id = isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0;
        $pet_id     = isset( $_POST['pet_id'] ) ? absint( $_POST['pet_id'] ) : 0;
        $client_id  = isset( $_POST['client_id'] ) ? absint( $_POST['client_id'] ) : 0;

        // Validações
        if ( empty( $date ) || empty( $time ) || ! $service_id || ! $pet_id || ! $client_id ) {
            wp_send_json_error( [ 'message' => __( 'Dados incompletos. Por favor, informe data, horário, serviço e pet.', 'dps-ai' ) ] );
        }

        // Verifica se o slot ainda está disponível
        if ( ! self::is_slot_available( $date, $time ) ) {
            wp_send_json_error( [ 'message' => __( 'Este horário não está mais disponível. Por favor, escolha outro.', 'dps-ai' ) ] );
        }

        if ( 'direct' === $scheduling_mode ) {
            // Modo direto: cria o agendamento automaticamente
            $result = self::create_appointment( $client_id, $pet_id, $service_id, $date, $time );
            
            if ( $result['success'] ) {
                wp_send_json_success( [
                    'message'        => __( 'Agendamento confirmado! Você receberá uma confirmação por e-mail.', 'dps-ai' ),
                    'appointment_id' => $result['appointment_id'],
                    'mode'           => 'direct',
                ] );
            } else {
                wp_send_json_error( [ 'message' => $result['message'] ] );
            }
        } else {
            // Modo solicitação: cria pedido pendente
            $result = self::create_appointment_request( $client_id, $pet_id, $service_id, $date, $time );
            
            if ( $result['success'] ) {
                wp_send_json_success( [
                    'message'    => __( 'Solicitação de agendamento enviada! Nossa equipe entrará em contato para confirmar.', 'dps-ai' ),
                    'request_id' => $result['request_id'],
                    'mode'       => 'request',
                ] );
            } else {
                wp_send_json_error( [ 'message' => $result['message'] ] );
            }
        }
    }

    /**
     * Obtém horários disponíveis para uma data.
     *
     * @param string $date Data no formato Y-m-d.
     *
     * @return array Lista de horários disponíveis.
     */
    public static function get_available_slots( $date ) {
        $settings   = get_option( 'dps_ai_settings', [] );
        $start_hour = $settings['business_start_hour'] ?? 8;
        $end_hour   = $settings['business_end_hour'] ?? 18;
        $interval   = $settings['slot_interval'] ?? 30; // minutos

        $all_slots  = [];
        $start_time = strtotime( $date . ' ' . str_pad( $start_hour, 2, '0', STR_PAD_LEFT ) . ':00:00' );
        $end_time   = strtotime( $date . ' ' . str_pad( $end_hour, 2, '0', STR_PAD_LEFT ) . ':00:00' );

        // Gera todos os slots possíveis
        for ( $time = $start_time; $time < $end_time; $time += $interval * 60 ) {
            $all_slots[] = gmdate( 'H:i', $time );
        }

        // Busca agendamentos existentes
        $existing = get_posts( [
            'post_type'      => 'dps_agendamento',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => 'appointment_date',
                    'value'   => $date,
                    'compare' => '=',
                ],
                [
                    'key'     => 'appointment_status',
                    'value'   => [ 'scheduled', 'pending' ],
                    'compare' => 'IN',
                ],
            ],
        ] );

        // Remove slots já ocupados
        $occupied = [];
        foreach ( $existing as $appointment ) {
            $time = get_post_meta( $appointment->ID, 'appointment_time', true );
            if ( $time ) {
                $occupied[] = $time;
            }
        }

        $available = array_diff( $all_slots, $occupied );

        // Se for hoje, remove horários passados
        if ( $date === current_time( 'Y-m-d' ) ) {
            $now = current_time( 'H:i' );
            $available = array_filter( $available, function( $slot ) use ( $now ) {
                return $slot > $now;
            } );
        }

        return array_values( $available );
    }

    /**
     * Verifica se um slot específico está disponível.
     *
     * @param string $date Data no formato Y-m-d.
     * @param string $time Horário no formato H:i.
     *
     * @return bool True se disponível.
     */
    public static function is_slot_available( $date, $time ) {
        $available_slots = self::get_available_slots( $date );
        return in_array( $time, $available_slots, true );
    }

    /**
     * Cria um agendamento diretamente.
     *
     * @param int    $client_id  ID do cliente.
     * @param int    $pet_id     ID do pet.
     * @param int    $service_id ID do serviço.
     * @param string $date       Data do agendamento.
     * @param string $time       Horário do agendamento.
     *
     * @return array Resultado com success, message e appointment_id.
     */
    public static function create_appointment( $client_id, $pet_id, $service_id, $date, $time ) {
        // Verifica se os IDs são válidos
        if ( 'dps_cliente' !== get_post_type( $client_id ) ) {
            return [ 'success' => false, 'message' => __( 'Cliente inválido.', 'dps-ai' ) ];
        }
        if ( 'dps_pet' !== get_post_type( $pet_id ) ) {
            return [ 'success' => false, 'message' => __( 'Pet inválido.', 'dps-ai' ) ];
        }
        if ( 'dps_servico' !== get_post_type( $service_id ) ) {
            return [ 'success' => false, 'message' => __( 'Serviço inválido.', 'dps-ai' ) ];
        }

        $client_name  = get_the_title( $client_id );
        $pet_name     = get_the_title( $pet_id );
        $service_name = get_the_title( $service_id );

        // Cria o post de agendamento
        $appointment_id = wp_insert_post( [
            'post_type'   => 'dps_agendamento',
            'post_status' => 'publish',
            'post_title'  => sprintf( '%s - %s - %s %s', $client_name, $pet_name, $date, $time ),
        ] );

        if ( is_wp_error( $appointment_id ) ) {
            return [ 'success' => false, 'message' => __( 'Erro ao criar agendamento.', 'dps-ai' ) ];
        }

        // Salva metadados
        update_post_meta( $appointment_id, 'appointment_client_id', $client_id );
        update_post_meta( $appointment_id, 'appointment_pets', [ $pet_id ] );
        update_post_meta( $appointment_id, 'appointment_services', $service_id );
        update_post_meta( $appointment_id, 'appointment_date', $date );
        update_post_meta( $appointment_id, 'appointment_time', $time );
        update_post_meta( $appointment_id, 'appointment_status', 'scheduled' );
        update_post_meta( $appointment_id, 'appointment_source', 'ai_chat' );

        // Dispara hook para notificações
        do_action( 'dps_ai_appointment_created', $appointment_id, $client_id, $pet_id, $service_id );

        return [
            'success'        => true,
            'message'        => __( 'Agendamento criado com sucesso.', 'dps-ai' ),
            'appointment_id' => $appointment_id,
        ];
    }

    /**
     * Cria uma solicitação de agendamento (pendente de aprovação).
     *
     * @param int    $client_id  ID do cliente.
     * @param int    $pet_id     ID do pet.
     * @param int    $service_id ID do serviço.
     * @param string $date       Data desejada.
     * @param string $time       Horário desejado.
     *
     * @return array Resultado com success, message e request_id.
     */
    public static function create_appointment_request( $client_id, $pet_id, $service_id, $date, $time ) {
        // Verifica se os IDs são válidos
        if ( 'dps_cliente' !== get_post_type( $client_id ) ) {
            return [ 'success' => false, 'message' => __( 'Cliente inválido.', 'dps-ai' ) ];
        }

        $client_name  = get_the_title( $client_id );
        $pet_name     = get_the_title( $pet_id );
        $service_name = get_the_title( $service_id );

        // Cria o post de agendamento com status pendente
        $appointment_id = wp_insert_post( [
            'post_type'   => 'dps_agendamento',
            'post_status' => 'publish',
            'post_title'  => sprintf( '[SOLICITAÇÃO] %s - %s - %s %s', $client_name, $pet_name, $date, $time ),
        ] );

        if ( is_wp_error( $appointment_id ) ) {
            return [ 'success' => false, 'message' => __( 'Erro ao criar solicitação.', 'dps-ai' ) ];
        }

        // Salva metadados
        update_post_meta( $appointment_id, 'appointment_client_id', $client_id );
        update_post_meta( $appointment_id, 'appointment_pets', [ $pet_id ] );
        update_post_meta( $appointment_id, 'appointment_services', $service_id );
        update_post_meta( $appointment_id, 'appointment_date', $date );
        update_post_meta( $appointment_id, 'appointment_time', $time );
        update_post_meta( $appointment_id, 'appointment_status', 'pending' );
        update_post_meta( $appointment_id, 'appointment_source', 'ai_chat' );
        update_post_meta( $appointment_id, 'appointment_needs_confirmation', '1' );

        // Dispara hook para notificações
        do_action( 'dps_ai_appointment_request_created', $appointment_id, $client_id, $pet_id, $service_id );

        // Notifica administradores
        self::notify_admins_new_request( $appointment_id, $client_name, $pet_name, $service_name, $date, $time );

        return [
            'success'    => true,
            'message'    => __( 'Solicitação criada com sucesso.', 'dps-ai' ),
            'request_id' => $appointment_id,
        ];
    }

    /**
     * Notifica administradores sobre nova solicitação.
     *
     * @param int    $appointment_id ID do agendamento.
     * @param string $client_name    Nome do cliente.
     * @param string $pet_name       Nome do pet.
     * @param string $service_name   Nome do serviço.
     * @param string $date           Data.
     * @param string $time           Horário.
     */
    private static function notify_admins_new_request( $appointment_id, $client_name, $pet_name, $service_name, $date, $time ) {
        $admin_email = get_option( 'admin_email' );
        $subject     = sprintf( __( '[DPS] Nova solicitação de agendamento via IA - %s', 'dps-ai' ), $client_name );
        
        $message = sprintf(
            __( "Nova solicitação de agendamento recebida via chat da IA:\n\nCliente: %s\nPet: %s\nServiço: %s\nData: %s\nHorário: %s\n\nAcesse o painel para confirmar ou recusar.", 'dps-ai' ),
            $client_name,
            $pet_name,
            $service_name,
            $date,
            $time
        );

        wp_mail( $admin_email, $subject, $message );
    }

    /**
     * Obtém lista de serviços disponíveis para agendamento.
     *
     * @return array Lista de serviços [id => nome].
     */
    public static function get_available_services() {
        $services = get_posts( [
            'post_type'      => 'dps_servico',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );

        $result = [];
        foreach ( $services as $service ) {
            $result[ $service->ID ] = $service->post_title;
        }

        return $result;
    }

    /**
     * Gera prompt de contexto para agendamento.
     *
     * @return string Contexto adicional para a IA.
     */
    public static function get_scheduling_context() {
        $settings        = get_option( 'dps_ai_settings', [] );
        $scheduling_mode = $settings['scheduling_mode'] ?? 'request';

        $context = "\n\nINFORMAÇÕES SOBRE AGENDAMENTO VIA CHAT:\n";
        
        if ( 'direct' === $scheduling_mode ) {
            $context .= "O cliente pode agendar diretamente pelo chat. Os agendamentos são confirmados automaticamente.\n";
        } else {
            $context .= "O cliente pode solicitar agendamento pelo chat. A equipe entrará em contato para confirmar.\n";
        }

        // Adiciona informações de horário de funcionamento
        $start_hour = $settings['business_start_hour'] ?? 8;
        $end_hour   = $settings['business_end_hour'] ?? 18;
        $context   .= sprintf( "Horário de funcionamento: %s:00 às %s:00.\n", $start_hour, $end_hour );

        // Lista serviços disponíveis
        $services = self::get_available_services();
        if ( ! empty( $services ) ) {
            $context .= "Serviços disponíveis para agendamento: " . implode( ', ', $services ) . ".\n";
        }

        return $context;
    }
}
