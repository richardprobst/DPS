<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Helper para buscar e organizar histórico de serviços por pet.
 * 
 * Esta classe centraliza a lógica de busca de histórico de serviços
 * realizados em pets, facilitando a exibição de timelines e sugestões.
 * 
 * @since 2.4.0 (Fase 4)
 */
class DPS_Portal_Pet_History {

    /**
     * Instância única da classe (singleton).
     *
     * @var DPS_Portal_Pet_History|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única (singleton).
     *
     * @return DPS_Portal_Pet_History
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
     * Busca histórico de serviços realizados para um pet específico.
     * 
     * Retorna serviços concluídos em ordem cronológica inversa (mais recentes primeiro).
     *
     * @param int $pet_id ID do pet.
     * @param int $limit  Limite de resultados (padrão: -1 = todos).
     * @return array Array de arrays com dados dos serviços.
     */
    public function get_pet_service_history( $pet_id, $limit = -1 ) {
        // Busca por pet único (appointment_pet_id)
        $single_pet_appointments = get_posts( [
            'post_type'      => 'dps_agendamento',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => 'appointment_pet_id',
                    'value'   => $pet_id,
                    'compare' => '=',
                ],
            ],
        ] );

        // Busca por múltiplos pets (appointment_pet_ids)
        // Nota: LIKE com serialização é potencialmente impreciso, mas é o método
        // padrão do WordPress quando não há tabela separada de relacionamento
        $multi_pet_appointments = get_posts( [
            'post_type'      => 'dps_agendamento',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => 'appointment_pet_ids',
                    'value'   => sprintf( ':%d;', $pet_id ), // Formato serializado mais específico
                    'compare' => 'LIKE',
                ],
            ],
        ] );

        // Combina e remove duplicatas
        $all_appointment_ids = array_unique( array_merge( $single_pet_appointments, $multi_pet_appointments ) );

        if ( empty( $all_appointment_ids ) ) {
            return [];
        }

        // Busca posts completos com ordenação
        $appointments = get_posts( [
            'post_type'      => 'dps_agendamento',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'post__in'       => $all_appointment_ids,
            'orderby'        => 'meta_value',
            'meta_key'       => 'appointment_date',
            'order'          => 'DESC',
        ] );

        $history = [];
        $count   = 0;

        foreach ( $appointments as $appt ) {
            // Aplica limite se especificado
            if ( $limit > 0 && $count >= $limit ) {
                break;
            }

            $status = get_post_meta( $appt->ID, 'appointment_status', true );
            
            // Inclui apenas serviços finalizados
            if ( ! in_array( $status, [ 'finalizado', 'finalizado e pago', 'finalizado_pago' ], true ) ) {
                continue;
            }

            // Validação extra: verifica se o pet realmente está no agendamento
            $pet_ids_meta = get_post_meta( $appt->ID, 'appointment_pet_ids', true );
            if ( is_array( $pet_ids_meta ) && ! in_array( $pet_id, $pet_ids_meta, true ) ) {
                // Pet não está na lista, pula
                continue;
            }

            $date        = get_post_meta( $appt->ID, 'appointment_date', true );
            $time        = get_post_meta( $appt->ID, 'appointment_time', true );
            $services    = get_post_meta( $appt->ID, 'appointment_services', true );
            $observations = get_post_meta( $appt->ID, 'appointment_observations', true );
            $professional = get_post_meta( $appt->ID, 'appointment_professional', true );

            // Formata serviços como string legível
            $service_names = $this->format_services( $services );

            $history[] = [
                'appointment_id' => $appt->ID,
                'date'           => $date,
                'time'           => $time,
                'services'       => $service_names,
                'services_array' => is_array( $services ) ? $services : [],
                'observations'   => $observations,
                'professional'   => $professional,
                'status'         => $status,
            ];

            $count++;
        }

        return $history;
    }

    /**
     * Busca histórico de serviços de todos os pets de um cliente.
     * 
     * Agrupa serviços por pet e retorna estrutura organizada.
     *
     * @param int $client_id ID do cliente.
     * @param int $limit     Limite de resultados por pet (padrão: 5).
     * @return array Array associativo [pet_id => array de serviços].
     */
    public function get_client_service_history( $client_id, $limit = 5 ) {
        // Busca pets do cliente
        $pets = get_posts( [
            'post_type'      => 'dps_pet',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_key'       => 'owner_id',
            'meta_value'     => $client_id,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );

        if ( empty( $pets ) ) {
            return [];
        }

        $history_by_pet = [];
        foreach ( $pets as $pet ) {
            $pet_history = $this->get_pet_service_history( $pet->ID, $limit );
            if ( ! empty( $pet_history ) ) {
                $history_by_pet[ $pet->ID ] = [
                    'pet_name' => get_the_title( $pet->ID ),
                    'services' => $pet_history,
                ];
            }
        }

        return $history_by_pet;
    }

    /**
     * Formata array de serviços em string legível.
     *
     * @param mixed $services Array de serviços ou string.
     * @return string String formatada com nomes dos serviços.
     */
    private function format_services( $services ) {
        if ( empty( $services ) ) {
            return __( 'Serviço não especificado', 'dps-client-portal' );
        }

        if ( is_string( $services ) ) {
            return $services;
        }

        if ( is_array( $services ) ) {
            return implode( ', ', array_map( 'esc_html', $services ) );
        }

        return __( 'Serviço não especificado', 'dps-client-portal' );
    }
}
