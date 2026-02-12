<?php
/**
 * AJAX endpoints do Booking V2 (Fase 7.3).
 *
 * Registra e processa todas as requisições AJAX do wizard de
 * agendamento: busca de clientes, pets, serviços, horários
 * disponíveis e validação de steps.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DPS_Booking_Ajax {

    public function __construct(
        private readonly DPS_Appointment_Service $appointmentService,
        private readonly DPS_Booking_Validator   $validator,
        private readonly DPS_Booking_Hook_Bridge $hookBridge,
        private readonly DPS_Frontend_Logger     $logger,
    ) {}

    /**
     * Registra os hooks AJAX do Booking V2.
     */
    public function register(): void {
        add_action( 'wp_ajax_dps_booking_search_client', [ $this, 'handleSearchClient' ] );
        add_action( 'wp_ajax_dps_booking_get_pets', [ $this, 'handleGetPets' ] );
        add_action( 'wp_ajax_dps_booking_get_services', [ $this, 'handleGetServices' ] );
        add_action( 'wp_ajax_dps_booking_get_slots', [ $this, 'handleGetSlots' ] );
        add_action( 'wp_ajax_dps_booking_validate_step', [ $this, 'handleValidateStep' ] );
    }

    /**
     * Busca clientes por telefone.
     *
     * Retorna array de clientes correspondentes à busca.
     */
    public function handleSearchClient(): void {
        check_ajax_referer( 'dps_booking_v2', 'nonce' );

        if ( ! $this->hasCapability() ) {
            wp_send_json_error( [ 'message' => __( 'Permissão insuficiente.', 'dps-frontend-addon' ) ] );
            return;
        }

        $phone = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );

        if ( '' === $phone ) {
            wp_send_json_error( [ 'message' => __( 'Informe um telefone para buscar.', 'dps-frontend-addon' ) ] );
            return;
        }

        $digits = preg_replace( '/\D/', '', $phone );

        $query = new WP_Query( [
            'post_type'      => 'dps_cliente',
            'post_status'    => 'publish',
            'posts_per_page' => 10,
            'no_found_rows'  => true,
            'meta_query'     => [
                [
                    'key'     => 'client_phone',
                    'value'   => $digits,
                    'compare' => 'LIKE',
                ],
            ],
        ] );

        $clients = [];

        foreach ( $query->posts as $post ) {
            $clients[] = [
                'id'    => $post->ID,
                'name'  => $post->post_title,
                'phone' => get_post_meta( $post->ID, 'client_phone', true ),
                'email' => get_post_meta( $post->ID, 'client_email', true ),
            ];
        }

        wp_reset_postdata();

        wp_send_json_success( [ 'clients' => $clients ] );
    }

    /**
     * Retorna pets de um cliente.
     *
     * Suporta paginação via parâmetros page e per_page.
     */
    public function handleGetPets(): void {
        check_ajax_referer( 'dps_booking_v2', 'nonce' );

        if ( ! $this->hasCapability() ) {
            wp_send_json_error( [ 'message' => __( 'Permissão insuficiente.', 'dps-frontend-addon' ) ] );
            return;
        }

        $clientId = absint( $_POST['client_id'] ?? 0 );

        if ( $clientId < 1 ) {
            wp_send_json_error( [ 'message' => __( 'ID de cliente inválido.', 'dps-frontend-addon' ) ] );
            return;
        }

        $page    = max( 1, absint( $_POST['page'] ?? 1 ) );
        $perPage = min( 100, max( 1, absint( $_POST['per_page'] ?? 20 ) ) );

        $query = new WP_Query( [
            'post_type'      => 'dps_pet',
            'post_status'    => 'publish',
            'posts_per_page' => $perPage,
            'paged'          => $page,
            'meta_key'       => 'owner_id',
            'meta_value'     => $clientId,
            'meta_type'      => 'NUMERIC',
            'no_found_rows'  => false,
        ] );

        $pets = [];

        foreach ( $query->posts as $post ) {
            $pets[] = [
                'id'      => $post->ID,
                'name'    => $post->post_title,
                'species' => get_post_meta( $post->ID, 'pet_species', true ),
                'breed'   => get_post_meta( $post->ID, 'pet_breed', true ),
                'size'    => get_post_meta( $post->ID, 'pet_size', true ),
            ];
        }

        wp_reset_postdata();

        wp_send_json_success( [
            'pets'       => $pets,
            'total'      => (int) $query->found_posts,
            'page'       => $page,
            'total_pages' => (int) $query->max_num_pages,
        ] );
    }

    /**
     * Retorna serviços ativos.
     */
    public function handleGetServices(): void {
        check_ajax_referer( 'dps_booking_v2', 'nonce' );

        if ( ! $this->hasCapability() ) {
            wp_send_json_error( [ 'message' => __( 'Permissão insuficiente.', 'dps-frontend-addon' ) ] );
            return;
        }

        $query = new WP_Query( [
            'post_type'      => 'dps_servico',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
            'meta_query'     => [
                [
                    'key'   => 'service_active',
                    'value' => '1',
                ],
            ],
        ] );

        $services = [];

        foreach ( $query->posts as $post ) {
            $services[] = [
                'id'           => $post->ID,
                'name'         => $post->post_title,
                'price'        => (float) get_post_meta( $post->ID, 'service_price', true ),
                'price_small'  => (float) get_post_meta( $post->ID, 'service_price_small', true ),
                'price_medium' => (float) get_post_meta( $post->ID, 'service_price_medium', true ),
                'price_large'  => (float) get_post_meta( $post->ID, 'service_price_large', true ),
                'category'     => get_post_meta( $post->ID, 'service_category', true ),
            ];
        }

        wp_reset_postdata();

        wp_send_json_success( [ 'services' => $services ] );
    }

    /**
     * Retorna horários disponíveis para uma data.
     *
     * Gera slots de 30 minutos entre 08:00 e 18:00,
     * verificando conflitos com agendamentos existentes.
     */
    public function handleGetSlots(): void {
        check_ajax_referer( 'dps_booking_v2', 'nonce' );

        if ( ! $this->hasCapability() ) {
            wp_send_json_error( [ 'message' => __( 'Permissão insuficiente.', 'dps-frontend-addon' ) ] );
            return;
        }

        $date = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) );

        if ( '' === $date || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            wp_send_json_error( [ 'message' => __( 'Data inválida.', 'dps-frontend-addon' ) ] );
            return;
        }

        $slots = [];

        // Gerar slots de 30 min entre 08:00 e 18:00.
        $startHour = 8;
        $endHour   = 18;

        for ( $hour = $startHour; $hour < $endHour; $hour++ ) {
            foreach ( [ '00', '30' ] as $minutes ) {
                $time      = sprintf( '%02d:%s', $hour, $minutes );
                $available = ! $this->appointmentService->checkConflict( $date, $time );

                $slots[] = [
                    'time'      => $time,
                    'available' => $available,
                ];
            }
        }

        wp_send_json_success( [ 'slots' => $slots ] );
    }

    /**
     * Valida um step específico do wizard.
     */
    public function handleValidateStep(): void {
        check_ajax_referer( 'dps_booking_v2', 'nonce' );

        if ( ! $this->hasCapability() ) {
            wp_send_json_error( [ 'message' => __( 'Permissão insuficiente.', 'dps-frontend-addon' ) ] );
            return;
        }

        $step = absint( $_POST['step'] ?? 0 );

        if ( $step < 1 || $step > 5 ) {
            wp_send_json_error( [ 'message' => __( 'Step inválido.', 'dps-frontend-addon' ) ] );
            return;
        }

        // Sanitizar dados do step conforme contexto.
        $data = $this->sanitizeStepData( $step );

        $result = $this->validator->validateStep( $step, $data );

        if ( true === $result ) {
            wp_send_json_success( [
                'valid'  => true,
                'errors' => [],
            ] );
        } else {
            wp_send_json_success( [
                'valid'  => false,
                'errors' => $result,
            ] );
        }
    }

    /**
     * Verifica se o usuário atual possui capability necessária.
     *
     * @return bool True se autorizado.
     */
    private function hasCapability(): bool {
        return current_user_can( 'manage_options' )
            || current_user_can( 'dps_manage_clients' )
            || current_user_can( 'dps_manage_pets' )
            || current_user_can( 'dps_manage_appointments' );
    }

    /**
     * Sanitiza dados do step recebido via POST.
     *
     * @param int $step Número do step.
     * @return array<string, mixed> Dados sanitizados.
     */
    private function sanitizeStepData( int $step ): array {
        $data = [];

        switch ( $step ) {
            case 1:
                $data['client_id'] = absint( $_POST['client_id'] ?? 0 );
                break;

            case 2:
                $rawPetIds       = $_POST['pet_ids'] ?? [];
                $data['pet_ids'] = is_array( $rawPetIds ) ? array_map( 'absint', $rawPetIds ) : [];
                break;

            case 3:
                $rawServiceIds       = $_POST['service_ids'] ?? [];
                $data['service_ids'] = is_array( $rawServiceIds ) ? array_map( 'absint', $rawServiceIds ) : [];
                break;

            case 4:
                $data['appointment_date'] = sanitize_text_field( wp_unslash( $_POST['appointment_date'] ?? '' ) );
                $data['appointment_time'] = sanitize_text_field( wp_unslash( $_POST['appointment_time'] ?? '' ) );
                $data['appointment_type'] = sanitize_text_field( wp_unslash( $_POST['appointment_type'] ?? 'simple' ) );
                break;

            case 5:
                // Confirmação: sem dados adicionais.
                break;
        }

        return $data;
    }
}
