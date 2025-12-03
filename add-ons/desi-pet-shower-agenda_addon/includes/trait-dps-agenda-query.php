<?php
/**
 * Trait com métodos de query para a Agenda.
 *
 * Este trait contém métodos de consulta ao banco de dados
 * extraídos para melhorar a manutenibilidade do código.
 *
 * @package DPS_Agenda_Addon
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Trait DPS_Agenda_Query
 *
 * Métodos de consulta extraídos da classe principal.
 */
trait DPS_Agenda_Query {

    /**
     * Busca agendamentos do dia.
     *
     * @since 1.3.0
     * @param string $date Data no formato Y-m-d.
     * @return array Lista de agendamentos.
     */
    private function query_appointments_for_date( $date ) {
        $daily_limit = apply_filters( 'dps_agenda_daily_limit', DPS_Agenda_Addon::DAILY_APPOINTMENTS_LIMIT );
        
        return get_posts( [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => $daily_limit,
            'meta_key'       => 'appointment_date',
            'meta_value'     => $date,
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'post_status'    => 'publish',
            'no_found_rows'  => true,
        ] );
    }

    /**
     * Busca agendamentos da semana.
     *
     * @since 1.3.0
     * @param string $start_date Data inicial no formato Y-m-d.
     * @return array Agendamentos agrupados por data.
     */
    private function query_appointments_for_week( $start_date ) {
        $daily_limit = apply_filters( 'dps_agenda_daily_limit', DPS_Agenda_Addon::DAILY_APPOINTMENTS_LIMIT );
        $appointments_by_day = [];
        
        $start = DateTime::createFromFormat( 'Y-m-d', $start_date );
        if ( ! $start ) {
            return $appointments_by_day;
        }
        
        for ( $i = 0; $i < 7; $i++ ) {
            $day_date = $start->format( 'Y-m-d' );
            $appointments_by_day[ $day_date ] = get_posts( [
                'post_type'      => 'dps_agendamento',
                'posts_per_page' => $daily_limit,
                'meta_key'       => 'appointment_date',
                'meta_value'     => $day_date,
                'orderby'        => 'meta_value',
                'order'          => 'ASC',
                'post_status'    => 'publish',
                'no_found_rows'  => true,
            ] );
            $start->modify( '+1 day' );
        }
        
        return $appointments_by_day;
    }

    /**
     * Busca todos os agendamentos futuros paginados.
     *
     * @since 1.3.0
     * @param int $paged Número da página.
     * @return array Lista de agendamentos.
     */
    private function query_all_appointments( $paged = 1 ) {
        return get_posts( [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => DPS_Agenda_Addon::APPOINTMENTS_PER_PAGE,
            'paged'          => $paged,
            'orderby'        => 'meta_value',
            'meta_key'       => 'appointment_date',
            'order'          => 'ASC',
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'     => 'appointment_date',
                    'value'   => current_time( 'Y-m-d' ),
                    'compare' => '>=',
                    'type'    => 'DATE',
                ],
            ],
        ] );
    }

    /**
     * Busca agendamentos para exportação.
     *
     * @since 1.3.0
     * @param string $date Data ou vazio para todos.
     * @param string $view Tipo de visualização ('day', 'week', ou vazio).
     * @return array Lista de agendamentos.
     */
    private function query_appointments_for_export( $date = '', $view = 'day' ) {
        $args = [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'meta_value',
            'meta_key'       => 'appointment_date',
            'order'          => 'ASC',
        ];
        
        if ( ! empty( $date ) ) {
            if ( $view === 'week' ) {
                $end_date = date( 'Y-m-d', strtotime( $date . ' +6 days' ) );
                $args['meta_query'] = [
                    [
                        'key'     => 'appointment_date',
                        'value'   => [ $date, $end_date ],
                        'compare' => 'BETWEEN',
                        'type'    => 'DATE',
                    ],
                ];
            } else {
                $args['meta_query'] = [
                    [
                        'key'   => 'appointment_date',
                        'value' => $date,
                    ],
                ];
            }
        }
        
        return get_posts( $args );
    }

    /**
     * Obtém dados de agrupamento por cliente/data para cobranças.
     *
     * @since 1.3.0
     * @param int    $client_id ID do cliente.
     * @param string $date      Data do agendamento.
     * @return array|null Dados do grupo ou null se não houver grupo.
     */
    private function get_client_group_data( $client_id, $date ) {
        if ( ! $client_id ) {
            return null;
        }
        
        // Busca outros agendamentos do mesmo cliente na mesma data
        $group_appts = get_posts( [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => 20,
            'post_status'    => 'publish',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'   => 'appointment_client_id',
                    'value' => $client_id,
                ],
                [
                    'key'   => 'appointment_date',
                    'value' => $date,
                ],
            ],
        ] );
        
        if ( count( $group_appts ) < 2 ) {
            return null;
        }
        
        $ids = [];
        $pet_names = [];
        $total = 0;
        $time = '';
        
        foreach ( $group_appts as $g ) {
            $ids[] = $g->ID;
            $pet_id = get_post_meta( $g->ID, 'appointment_pet_id', true );
            $pet = $pet_id ? get_post( $pet_id ) : null;
            if ( $pet ) {
                $pet_names[] = $pet->post_title;
            }
            
            // Soma valores
            $service_ids = get_post_meta( $g->ID, 'appointment_services', true );
            if ( is_array( $service_ids ) && class_exists( 'DPS_Services_API' ) ) {
                $pet_size = $pet_id ? get_post_meta( $pet_id, 'pet_size', true ) : 'medium';
                foreach ( $service_ids as $sid ) {
                    $price = DPS_Services_API::calculate_price( $sid, $pet_size );
                    if ( $price !== null ) {
                        $total += $price;
                    }
                }
            }
            
            if ( ! $time ) {
                $time = get_post_meta( $g->ID, 'appointment_time', true );
            }
        }
        
        return [
            'ids'       => $ids,
            'pet_names' => array_unique( $pet_names ),
            'total'     => $total,
            'date'      => $date,
            'time'      => $time,
        ];
    }
}
