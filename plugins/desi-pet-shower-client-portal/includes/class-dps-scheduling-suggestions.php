<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Sugestões Inteligentes de Agendamento (Fase 8.1).
 *
 * Analisa o histórico de agendamentos do pet para sugerir:
 * - Próxima data provável (baseada na frequência média de serviços)
 * - Serviços mais utilizados pelo pet
 * - Tempo desde o último atendimento
 *
 * Usa apenas dados locais (sem IA). A versão com IA pode ser adicionada
 * como segunda iteração se o add-on AI estiver ativo.
 *
 * @package DPS_Client_Portal
 * @since   3.2.0
 */
class DPS_Scheduling_Suggestions {

    /**
     * Instância singleton.
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Recupera instância singleton.
     *
     * @return self
     */
    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado.
     */
    private function __construct() {}

    /**
     * Gera sugestões de agendamento para cada pet do cliente.
     *
     * @param int   $client_id ID do cliente.
     * @param array $pets      Lista de posts WP_Post dos pets do cliente.
     * @return array Sugestões indexadas por pet_id.
     */
    public function get_suggestions_for_client( int $client_id, array $pets ): array {
        $suggestions = [];

        foreach ( $pets as $pet ) {
            $pet_id = $pet->ID;
            $suggestion = $this->analyze_pet_history( $client_id, $pet_id );

            if ( ! empty( $suggestion ) ) {
                $suggestion['pet_name'] = $pet->post_title;
                $suggestions[ $pet_id ] = $suggestion;
            }
        }

        return $suggestions;
    }

    /**
     * Analisa o histórico de atendimentos de um pet.
     *
     * @param int $client_id ID do cliente.
     * @param int $pet_id    ID do pet.
     * @return array {
     *     @type string   $suggested_date  Data sugerida (Y-m-d) ou vazio.
     *     @type int      $avg_interval    Intervalo médio em dias entre atendimentos.
     *     @type int      $days_since_last Dias desde o último atendimento.
     *     @type string   $last_date       Data do último atendimento (Y-m-d).
     *     @type string[] $top_services    Serviços mais frequentes (max 3).
     *     @type string   $urgency         'overdue' | 'soon' | 'normal' | ''.
     * }
     */
    private function analyze_pet_history( int $client_id, int $pet_id ): array {
        // Busca agendamentos finalizados do pet
        $appointments = get_posts( [
            'post_type'      => 'dps_agendamento',
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'no_found_rows'  => true,
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
            'orderby'  => 'meta_value',
            'meta_key' => 'appointment_date',
            'order'    => 'DESC',
        ] );

        if ( empty( $appointments ) ) {
            return [];
        }

        // Coleta datas e serviços
        $dates    = [];
        $services = [];

        foreach ( $appointments as $appt ) {
            $date = get_post_meta( $appt->ID, 'appointment_date', true );
            if ( $date ) {
                $dates[] = $date;
            }

            // Coleta serviços — pode ser texto direto ou IDs
            $service_ids = get_post_meta( $appt->ID, 'appointment_service_ids', true );
            if ( ! empty( $service_ids ) && is_array( $service_ids ) ) {
                foreach ( $service_ids as $sid ) {
                    $service_name = get_the_title( $sid );
                    if ( $service_name ) {
                        $services[] = $service_name;
                    }
                }
            } else {
                $service_text = get_post_meta( $appt->ID, 'appointment_services', true );
                if ( ! empty( $service_text ) ) {
                    $services[] = $service_text;
                }
            }
        }

        if ( count( $dates ) < 1 ) {
            return [];
        }

        // Calcula intervalo médio entre atendimentos
        $avg_interval    = 0;
        $suggested_date  = '';
        $days_since_last = 0;
        $urgency         = '';
        $last_date       = $dates[0];

        // Dias desde o último atendimento
        $last_timestamp  = strtotime( $last_date );
        $today_timestamp = strtotime( current_time( 'Y-m-d' ) );
        $days_since_last = max( 0, (int) round( ( $today_timestamp - $last_timestamp ) / DAY_IN_SECONDS ) );

        if ( count( $dates ) >= 2 ) {
            // Calcula intervalos entre atendimentos consecutivos
            $intervals = [];
            for ( $i = 0; $i < count( $dates ) - 1; $i++ ) {
                $diff = abs( strtotime( $dates[ $i ] ) - strtotime( $dates[ $i + 1 ] ) );
                $interval_days = (int) round( $diff / DAY_IN_SECONDS );
                if ( $interval_days > 0 && $interval_days < 365 ) {
                    $intervals[] = $interval_days;
                }
            }

            if ( ! empty( $intervals ) ) {
                $avg_interval = (int) round( array_sum( $intervals ) / count( $intervals ) );

                // Sugere próxima data = último atendimento + intervalo médio
                $suggested_timestamp = $last_timestamp + ( $avg_interval * DAY_IN_SECONDS );

                // Se a data sugerida já passou, sugere amanhã
                $tomorrow = $today_timestamp + DAY_IN_SECONDS;
                if ( $suggested_timestamp < $tomorrow ) {
                    $suggested_date = gmdate( 'Y-m-d', $tomorrow );
                } else {
                    $suggested_date = gmdate( 'Y-m-d', $suggested_timestamp );
                }

                // Determina urgência
                if ( $avg_interval > 0 ) {
                    $ratio = $days_since_last / $avg_interval;
                    if ( $ratio >= 1.3 ) {
                        $urgency = 'overdue';
                    } elseif ( $ratio >= 0.8 ) {
                        $urgency = 'soon';
                    } else {
                        $urgency = 'normal';
                    }
                }
            }
        }

        // Top serviços (mais frequentes, max 3)
        $service_counts = array_count_values( $services );
        arsort( $service_counts );
        $top_services = array_slice( array_keys( $service_counts ), 0, 3 );

        return [
            'suggested_date'  => $suggested_date,
            'avg_interval'    => $avg_interval,
            'days_since_last' => $days_since_last,
            'last_date'       => $last_date,
            'top_services'    => $top_services,
            'urgency'         => $urgency,
        ];
    }
}
