<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Repositório para operações de dados relacionadas a agendamentos.
 * 
 * Centraliza todas as consultas de dados de agendamentos (CPT dps_agendamento),
 * seguindo o padrão Repository para isolar lógica de acesso a dados.
 * 
 * @since 3.0.0
 */
class DPS_Appointment_Repository {

    /**
     * Instância única da classe (singleton).
     *
     * @var DPS_Appointment_Repository|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única (singleton).
     *
     * @return DPS_Appointment_Repository
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
     * Busca o próximo agendamento de um cliente.
     *
     * @param int $client_id ID do cliente.
     * @return WP_Post|null Próximo agendamento ou null se não houver.
     */
    public function get_next_appointment_for_client( $client_id ) {
        $appointments = $this->get_future_appointments_for_client( $client_id );

        return ! empty( $appointments ) ? $appointments[0] : null;
    }

    /**
     * Busca agendamentos futuros de um cliente.
     *
     * @param int $client_id ID do cliente.
     * @return array Array de agendamentos futuros.
     */
    public function get_future_appointments_for_client( $client_id ) {
        $today = current_time( 'Y-m-d' );

        $appointments = get_posts( [
            'post_type'      => 'dps_agendamento',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'orderby'        => 'meta_value',
            'meta_key'       => 'appointment_date',
            'order'          => 'ASC',
            'no_found_rows'  => true,
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
        ] );

        $valid_appointments = [];
        foreach ( $appointments as $appointment_id ) {
            $status = get_post_meta( $appointment_id, 'appointment_status', true );

            if ( in_array( $status, [ 'finalizado', 'finalizado e pago', 'finalizado_pago', 'cancelado' ], true ) ) {
                continue;
            }

            $appointment = get_post( $appointment_id );
            if ( $appointment instanceof WP_Post ) {
                $valid_appointments[] = $appointment;
            }
        }

        usort(
            $valid_appointments,
            static function ( $appointment_a, $appointment_b ) {
                $date_a = (string) get_post_meta( $appointment_a->ID, 'appointment_date', true );
                $time_a = (string) get_post_meta( $appointment_a->ID, 'appointment_time', true );
                $date_b = (string) get_post_meta( $appointment_b->ID, 'appointment_date', true );
                $time_b = (string) get_post_meta( $appointment_b->ID, 'appointment_time', true );

                $timestamp_a = strtotime( trim( $date_a . ' ' . ( $time_a ?: '00:00' ) ) );
                $timestamp_b = strtotime( trim( $date_b . ' ' . ( $time_b ?: '00:00' ) ) );

                if ( false === $timestamp_a || false === $timestamp_b ) {
                    return 0;
                }

                return $timestamp_a <=> $timestamp_b;
            }
        );

        return $valid_appointments;
    }

    /**
     * Busca agendamentos passados de um cliente.
     *
     * @param int $client_id ID do cliente.
     * @param int $limit     Limite de resultados (padrão: -1 = todos).
     * @return array Array de agendamentos passados.
     */
    public function get_past_appointments_for_client( $client_id, $limit = -1 ) {
        $today = current_time( 'Y-m-d' );

        return get_posts( [
            'post_type'      => 'dps_agendamento',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => 'appointment_client_id',
                    'value'   => $client_id,
                    'compare' => '=',
                ],
                [
                    'key'     => 'appointment_date',
                    'value'   => $today,
                    'compare' => '<',
                    'type'    => 'DATE',
                ],
            ],
            'orderby'        => 'meta_value',
            'meta_key'       => 'appointment_date',
            'order'          => 'DESC',
        ] );
    }

    /**
     * Busca último agendamento finalizado de um pet.
     *
     * @param int $client_id ID do cliente.
     * @param int $pet_id    ID do pet.
     * @return WP_Post|null Último agendamento finalizado ou null.
     */
    public function get_last_finished_appointment_for_pet( $client_id, $pet_id ) {
        $appointments = get_posts( [
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
                    'value'   => [ 'finalizado', 'finalizado e pago', 'finalizado_pago' ],
                    'compare' => 'IN',
                ],
            ],
            'orderby'        => 'meta_value',
            'meta_key'       => 'appointment_date',
            'order'          => 'DESC',
        ] );

        return ! empty( $appointments ) ? $appointments[0] : null;
    }

    /**
     * Conta agendamentos futuros de um cliente.
     *
     * @param int $client_id ID do cliente.
     * @return int Número de agendamentos futuros.
     */
    public function count_upcoming_appointments( $client_id ) {
        $appointments = $this->get_future_appointments_for_client( $client_id );
        return count( $appointments );
    }
}
