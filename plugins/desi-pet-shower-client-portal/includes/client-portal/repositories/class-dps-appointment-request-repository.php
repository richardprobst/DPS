<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Repositório para operações de pedidos de agendamento.
 * 
 * Gerencia pedidos criados pelo cliente para agendar, reagendar ou cancelar serviços.
 * Todos os pedidos ficam "pendentes" até confirmação da equipe do Banho e Tosa.
 * 
 * @since 2.4.0 (Fase 4)
 */
class DPS_Appointment_Request_Repository {

    /**
     * Instância única da classe (singleton).
     *
     * @var DPS_Appointment_Request_Repository|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única (singleton).
     *
     * @return DPS_Appointment_Request_Repository
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado (singleton).
     */
    private function __construct() {
        // Nada a inicializar por enquanto
    }

    /**
     * Cria um novo pedido de agendamento.
     *
     * @param array $data Dados do pedido.
     *   - client_id (int): ID do cliente.
     *   - pet_id (int): ID do pet.
     *   - request_type (string): 'new', 'reschedule', 'cancel'.
     *   - desired_date (string): Data desejada (Y-m-d).
     *   - desired_period (string): 'morning' ou 'afternoon'.
     *   - services (array): Array de serviços desejados.
     *   - original_appointment_id (int): ID do agendamento original (para reschedule/cancel).
     *   - notes (string): Observações do cliente.
     * @return int|false ID do pedido criado ou false em caso de erro.
     */
    public function create_request( $data ) {
        $defaults = [
            'client_id'               => 0,
            'pet_id'                  => 0,
            'request_type'            => 'new',
            'desired_date'            => '',
            'desired_period'          => '',
            'services'                => [],
            'original_appointment_id' => 0,
            'notes'                   => '',
        ];

        $data = wp_parse_args( $data, $defaults );

        // Validações básicas
        if ( empty( $data['client_id'] ) ) {
            return false;
        }

        // Cria o post do tipo pedido de agendamento
        $post_data = [
            'post_type'   => 'dps_appt_request',
            'post_status' => 'publish',
            'post_title'  => sprintf(
                __( 'Pedido de %s - Cliente %d', 'dps-client-portal' ),
                $this->get_request_type_label( $data['request_type'] ),
                $data['client_id']
            ),
        ];

        $request_id = wp_insert_post( $post_data );

        if ( is_wp_error( $request_id ) || ! $request_id ) {
            return false;
        }

        // Salva metadados
        update_post_meta( $request_id, 'request_client_id', absint( $data['client_id'] ) );
        update_post_meta( $request_id, 'request_pet_id', absint( $data['pet_id'] ) );
        update_post_meta( $request_id, 'request_type', sanitize_key( $data['request_type'] ) );
        update_post_meta( $request_id, 'request_desired_date', sanitize_text_field( $data['desired_date'] ) );
        update_post_meta( $request_id, 'request_desired_period', sanitize_key( $data['desired_period'] ) );
        update_post_meta( $request_id, 'request_services', $data['services'] );
        update_post_meta( $request_id, 'request_original_appointment_id', absint( $data['original_appointment_id'] ) );
        update_post_meta( $request_id, 'request_notes', sanitize_textarea_field( $data['notes'] ) );
        update_post_meta( $request_id, 'request_status', 'pending' );
        update_post_meta( $request_id, 'request_created_at', current_time( 'mysql' ) );

        return $request_id;
    }

    /**
     * Busca pedidos de agendamento de um cliente.
     *
     * @param int    $client_id ID do cliente.
     * @param string $status    Filtrar por status (opcional).
     * @param int    $limit     Limite de resultados (padrão: -1 = todos).
     * @return array Array de posts de pedidos.
     */
    public function get_requests_by_client( $client_id, $status = '', $limit = -1 ) {
        $meta_query = [
            [
                'key'     => 'request_client_id',
                'value'   => $client_id,
                'compare' => '=',
            ],
        ];

        if ( ! empty( $status ) ) {
            $meta_query[] = [
                'key'     => 'request_status',
                'value'   => $status,
                'compare' => '=',
            ];
        }

        return get_posts( [
            'post_type'      => 'dps_appt_request',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'meta_query'     => $meta_query,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );
    }

    /**
     * Atualiza o status de um pedido.
     *
     * @param int    $request_id ID do pedido.
     * @param string $status     Novo status: 'pending', 'confirmed', 'rejected', 'adjusted'.
     * @param array  $meta       Metadados adicionais (ex: confirmed_date, confirmed_time).
     * @return bool True em caso de sucesso.
     */
    public function update_request_status( $request_id, $status, $meta = [] ) {
        $valid_statuses = [ 'pending', 'confirmed', 'rejected', 'adjusted' ];
        
        if ( ! in_array( $status, $valid_statuses, true ) ) {
            return false;
        }

        update_post_meta( $request_id, 'request_status', $status );
        update_post_meta( $request_id, 'request_updated_at', current_time( 'mysql' ) );

        // Salva metadados adicionais
        foreach ( $meta as $key => $value ) {
            update_post_meta( $request_id, 'request_' . $key, $value );
        }

        return true;
    }

    /**
     * Verifica se um pedido pertence a um cliente.
     *
     * @param int $request_id ID do pedido.
     * @param int $client_id  ID do cliente.
     * @return bool True se o pedido pertence ao cliente.
     */
    public function request_belongs_to_client( $request_id, $client_id ) {
        $request_client_id = absint( get_post_meta( $request_id, 'request_client_id', true ) );
        return $request_client_id === absint( $client_id );
    }

    /**
     * Retorna dados completos de um pedido.
     *
     * @param int $request_id ID do pedido.
     * @return array|false Array com dados do pedido ou false se não encontrado.
     */
    public function get_request_data( $request_id ) {
        $post = get_post( $request_id );

        if ( ! $post || 'dps_appt_request' !== $post->post_type ) {
            return false;
        }

        return [
            'id'                      => $request_id,
            'client_id'               => absint( get_post_meta( $request_id, 'request_client_id', true ) ),
            'pet_id'                  => absint( get_post_meta( $request_id, 'request_pet_id', true ) ),
            'type'                    => get_post_meta( $request_id, 'request_type', true ),
            'desired_date'            => get_post_meta( $request_id, 'request_desired_date', true ),
            'desired_period'          => get_post_meta( $request_id, 'request_desired_period', true ),
            'services'                => get_post_meta( $request_id, 'request_services', true ),
            'original_appointment_id' => absint( get_post_meta( $request_id, 'request_original_appointment_id', true ) ),
            'notes'                   => get_post_meta( $request_id, 'request_notes', true ),
            'status'                  => get_post_meta( $request_id, 'request_status', true ),
            'created_at'              => get_post_meta( $request_id, 'request_created_at', true ),
            'updated_at'              => get_post_meta( $request_id, 'request_updated_at', true ),
            'confirmed_date'          => get_post_meta( $request_id, 'request_confirmed_date', true ),
            'confirmed_time'          => get_post_meta( $request_id, 'request_confirmed_time', true ),
        ];
    }

    /**
     * Retorna label traduzida para tipo de pedido.
     *
     * @param string $type Tipo do pedido.
     * @return string Label traduzida.
     */
    private function get_request_type_label( $type ) {
        $labels = [
            'new'        => __( 'Agendamento', 'dps-client-portal' ),
            'reschedule' => __( 'Reagendamento', 'dps-client-portal' ),
            'cancel'     => __( 'Cancelamento', 'dps-client-portal' ),
        ];

        return isset( $labels[ $type ] ) ? $labels[ $type ] : $type;
    }
}
