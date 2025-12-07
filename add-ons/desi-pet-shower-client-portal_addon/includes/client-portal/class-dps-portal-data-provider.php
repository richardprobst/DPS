<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provedor de dados para o portal do cliente.
 * 
 * Esta classe é responsável por buscar e agregar dados necessários
 * para exibição no portal, otimizando queries e evitando N+1.
 * 
 * @since 3.0.0
 */
class DPS_Portal_Data_Provider {

    /**
     * Instância única da classe (singleton).
     *
     * @var DPS_Portal_Data_Provider|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única (singleton).
     *
     * @return DPS_Portal_Data_Provider
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
     * Conta mensagens não lidas do admin para o cliente.
     *
     * @since 2.3.0
     * @param int $client_id ID do cliente.
     * @return int Quantidade de mensagens não lidas.
     */
    public function get_unread_messages_count( $client_id ) {
        $messages = get_posts( [
            'post_type'      => 'dps_portal_message',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'   => 'message_client_id',
                    'value' => $client_id,
                ],
                [
                    'key'   => 'message_sender',
                    'value' => 'admin',
                ],
                [
                    'key'     => 'client_read_at',
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ] );

        return count( $messages );
    }

    /**
     * Conta agendamentos futuros do cliente (para badge da tab).
     *
     * @param int $client_id ID do cliente.
     * @return int Número de agendamentos futuros.
     * @since 2.4.0
     */
    public function count_upcoming_appointments( $client_id ) {
        $today = current_time( 'Y-m-d' );
        $args  = [
            'post_type'      => 'dps_agendamento',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => 'appointment_client_id',
                    'value'   => $client_id,
                    'compare' => '=',
                ],
                [
                    'key'     => 'appointment_date',
                    'value'   => $today,
                    'compare' => '>=',
                    'type'    => 'DATE',
                ],
            ],
        ];
        
        $appointments = get_posts( $args );
        $count = 0;
        
        // Filtra por status válidos
        foreach ( $appointments as $appt_id ) {
            $status = get_post_meta( $appt_id, 'appointment_status', true );
            if ( ! in_array( $status, [ 'finalizado', 'finalizado e pago', 'finalizado_pago', 'cancelado' ], true ) ) {
                $count++;
            }
        }
        
        return $count;
    }

    /**
     * Conta pendências financeiras do cliente (para badge da tab).
     *
     * @param int $client_id ID do cliente.
     * @return int Número de pendências.
     * @since 2.4.0
     */
    public function count_financial_pending( $client_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';
        
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE cliente_id = %d AND status IN ('em_aberto', 'pendente')",
            $client_id
        ) );
        
        return absint( $count );
    }

    /**
     * Busca sugestões de agendamento baseadas no histórico do cliente.
     *
     * @param int $client_id ID do cliente.
     * @return array Array de sugestões.
     */
    public function get_scheduling_suggestions( $client_id ) {
        // Busca pets do cliente
        $pets = get_posts( [
            'post_type'      => 'dps_pet',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_key'       => 'owner_id',
            'meta_value'     => $client_id,
            'fields'         => 'ids',
        ] );
        
        if ( empty( $pets ) ) {
            return []; // Sem pets, sem sugestões
        }
        
        // Busca último agendamento de cada pet
        $suggestions = [];
        $today = current_time( 'Y-m-d' );
        
        foreach ( $pets as $pet_id ) {
            $last_appointment = get_posts( [
                'post_type'      => 'dps_agendamento',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'meta_query'     => [
                    'relation' => 'AND',
                    [
                        'key'     => 'appointment_client_id',
                        'value'   => $client_id,
                        'compare' => '=',
                    ],
                    [
                        'key'     => 'appointment_pet_id',
                        'value'   => $pet_id,
                        'compare' => '=',
                    ],
                    [
                        'key'     => 'appointment_status',
                        'value'   => ['finalizado', 'finalizado e pago', 'finalizado_pago'],
                        'compare' => 'IN',
                    ],
                ],
                'orderby'        => 'meta_value',
                'meta_key'       => 'appointment_date',
                'order'          => 'DESC',
            ] );
            
            if ( ! empty( $last_appointment ) ) {
                $appt_id = $last_appointment[0]->ID;
                $appt_date = get_post_meta( $appt_id, 'appointment_date', true );
                $services = get_post_meta( $appt_id, 'appointment_services', true );
                
                if ( $appt_date ) {
                    $days_since = floor( ( strtotime( $today ) - strtotime( $appt_date ) ) / DAY_IN_SECONDS );
                    
                    // Sugestão se faz mais de 30 dias
                    if ( $days_since >= 30 ) {
                        $pet_name = get_the_title( $pet_id );
                        $service_name = is_array( $services ) && ! empty( $services ) ? $services[0] : __( 'banho', 'dps-client-portal' );
                        
                        $suggestions[] = [
                            'pet_name'     => $pet_name,
                            'days_since'   => $days_since,
                            'service_name' => $service_name,
                        ];
                    }
                }
            }
        }
        
        return $suggestions;
    }
}
