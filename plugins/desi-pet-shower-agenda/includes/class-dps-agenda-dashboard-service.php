<?php
/**
 * Serviço para Dashboard Operacional da AGENDA.
 *
 * Centraliza a lógica de cálculo de KPIs e métricas para visão rápida do dia.
 * Foco em performance e dados agregados.
 *
 * @package DPS_Agenda_Addon
 * @since 1.3.0
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DPS_Agenda_Dashboard_Service {

    /**
     * Retorna todos os KPIs do dia em uma única chamada.
     *
     * @param string $date Data no formato Y-m-d. Default: hoje.
     * @return array KPIs consolidados.
     */
    public static function get_daily_kpis( $date = '' ) {
        if ( empty( $date ) ) {
            $date = current_time( 'Y-m-d' );
        }

        // Obtém todos os agendamentos do dia de uma vez
        $appointments = self::get_appointments_by_date( $date );

        return [
            'date' => $date,
            'total_counts' => self::calculate_total_counts( $appointments ),
            'confirmation_stats' => self::calculate_confirmation_stats( $appointments ),
            'execution_stats' => self::calculate_execution_stats( $appointments, $date ),
            'special_stats' => self::calculate_special_stats( $appointments ),
        ];
    }

    /**
     * Obtém agendamentos de uma data específica.
     *
     * @param string $date Data no formato Y-m-d.
     * @return array Lista de posts de agendamentos.
     */
    private static function get_appointments_by_date( $date ) {
        $appointments = get_posts( [
            'post_type'      => 'dps_agendamento',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => 'appointment_date',
                    'value'   => $date,
                    'compare' => '=',
                ],
            ],
            'orderby'        => 'meta_value',
            'meta_key'       => 'appointment_time',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ] );

        // Pre-carrega todos os metadados de uma vez (performance)
        if ( ! empty( $appointments ) ) {
            $appointment_ids = wp_list_pluck( $appointments, 'ID' );
            update_meta_cache( 'post', $appointment_ids );
        }

        return $appointments;
    }

    /**
     * Calcula contagens totais e por período.
     *
     * @param array $appointments Lista de posts.
     * @return array Estatísticas de contagem.
     */
    private static function calculate_total_counts( $appointments ) {
        $total = count( $appointments );
        $morning = 0; // 00:00 - 11:59
        $afternoon = 0; // 12:00 - 23:59

        foreach ( $appointments as $appt ) {
            $time = get_post_meta( $appt->ID, 'appointment_time', true );
            if ( empty( $time ) ) {
                continue;
            }

            $hour = intval( substr( $time, 0, 2 ) );

            if ( $hour < 12 ) {
                $morning++;
            } else {
                $afternoon++;
            }
        }

        return [
            'total'     => $total,
            'morning'   => $morning,
            'afternoon' => $afternoon,
        ];
    }

    /**
     * Calcula estatísticas de confirmação.
     *
     * @param array $appointments Lista de posts.
     * @return array Estatísticas de confirmação.
     */
    private static function calculate_confirmation_stats( $appointments ) {
        $confirmed = 0;
        $not_confirmed = 0;

        foreach ( $appointments as $appt ) {
            $confirmation_status = get_post_meta( $appt->ID, 'appointment_confirmation_status', true );

            if ( $confirmation_status === 'confirmed' ) {
                $confirmed++;
            } elseif ( in_array( $confirmation_status, [ 'not_sent', 'sent', 'no_answer' ], true ) ) {
                $not_confirmed++;
            } else {
                // Se não tem status definido, considera não confirmado
                $not_confirmed++;
            }
        }

        return [
            'confirmed'     => $confirmed,
            'not_confirmed' => $not_confirmed,
        ];
    }

    /**
     * Calcula estatísticas de execução.
     *
     * @param array  $appointments Lista de posts.
     * @param string $date Data de referência.
     * @return array Estatísticas de execução.
     */
    private static function calculate_execution_stats( $appointments, $date ) {
        $in_progress = 0; // Poderia ser um status futuro
        $completed = 0;
        $canceled = 0;
        $late = 0;

        $current_time = current_time( 'H:i' );
        $is_today = ( $date === current_time( 'Y-m-d' ) );

        foreach ( $appointments as $appt ) {
            $status = get_post_meta( $appt->ID, 'appointment_status', true );
            if ( empty( $status ) ) {
                $status = 'pendente';
            }

            // Contabiliza status
            if ( $status === 'finalizado' || $status === 'finalizado_pago' ) {
                $completed++;
            } elseif ( $status === 'cancelado' ) {
                $canceled++;
            }

            // Detecta atrasos (apenas para hoje)
            if ( $is_today && $status === 'pendente' ) {
                $time = get_post_meta( $appt->ID, 'appointment_time', true );
                if ( ! empty( $time ) && $time < $current_time ) {
                    $late++;
                }
            }
        }

        return [
            'in_progress' => $in_progress,
            'completed'   => $completed,
            'canceled'    => $canceled,
            'late'        => $late,
        ];
    }

    /**
     * Calcula estatísticas especiais (TaxiDog, cobrança pendente).
     *
     * @param array $appointments Lista de posts.
     * @return array Estatísticas especiais.
     */
    private static function calculate_special_stats( $appointments ) {
        $with_taxidog = 0;
        $pending_payment = 0;

        foreach ( $appointments as $appt ) {
            // TaxiDog
            $taxidog = get_post_meta( $appt->ID, 'appointment_taxidog', true );
            $taxidog_status = get_post_meta( $appt->ID, 'appointment_taxidog_status', true );

            if ( $taxidog === '1' || ! empty( $taxidog_status ) ) {
                $with_taxidog++;
            }

            // Cobrança pendente (usando helper se disponível)
            if ( class_exists( 'DPS_Agenda_Payment_Helper' ) ) {
                $payment_status = DPS_Agenda_Payment_Helper::get_payment_status( $appt->ID );
                if ( in_array( $payment_status, [ 'pending', 'error' ], true ) ) {
                    $pending_payment++;
                }
            }
        }

        return [
            'with_taxidog'    => $with_taxidog,
            'pending_payment' => $pending_payment,
        ];
    }

    /**
     * Retorna os próximos N atendimentos do dia.
     *
     * @param string $date  Data no formato Y-m-d.
     * @param int    $limit Número máximo de atendimentos a retornar.
     * @return array Lista de atendimentos formatados.
     */
    public static function get_next_appointments( $date = '', $limit = 10 ) {
        if ( empty( $date ) ) {
            $date = current_time( 'Y-m-d' );
        }

        $current_time = current_time( 'H:i' );
        $is_today = ( $date === current_time( 'Y-m-d' ) );

        // Obtém todos os agendamentos do dia
        $appointments = self::get_appointments_by_date( $date );

        $next_appointments = [];

        foreach ( $appointments as $appt ) {
            $time = get_post_meta( $appt->ID, 'appointment_time', true );
            $status = get_post_meta( $appt->ID, 'appointment_status', true );

            // Se for hoje, só mostra os que ainda não passaram (ou todos se não for hoje)
            if ( $is_today && ! empty( $time ) && $time < $current_time ) {
                continue;
            }

            // Coleta dados do atendimento
            $pet_id = get_post_meta( $appt->ID, 'appointment_pet_id', true );
            $client_id = get_post_meta( $appt->ID, 'appointment_client_id', true );

            $pet_name = '';
            $client_name = '';

            if ( $pet_id ) {
                $pet_post = get_post( $pet_id );
                $pet_name = $pet_post ? $pet_post->post_title : '';
            }

            if ( $client_id ) {
                $client_post = get_post( $client_id );
                $client_name = $client_post ? $client_post->post_title : '';
            }

            // Serviços
            $service_ids = get_post_meta( $appt->ID, 'appointment_services', true );
            $service_names = [];

            if ( is_array( $service_ids ) && ! empty( $service_ids ) ) {
                foreach ( $service_ids as $srv_id ) {
                    $srv_post = get_post( $srv_id );
                    if ( $srv_post ) {
                        $service_names[] = $srv_post->post_title;
                    }
                }
            }

            $next_appointments[] = [
                'id'           => $appt->ID,
                'time'         => $time,
                'pet_name'     => $pet_name,
                'client_name'  => $client_name,
                'services'     => implode( ', ', $service_names ),
                'status'       => $status ?: 'pendente',
            ];

            // Limita a quantidade
            if ( count( $next_appointments ) >= $limit ) {
                break;
            }
        }

        return $next_appointments;
    }

    /**
     * Renderiza um card de KPI.
     *
     * @param string $title Título do card.
     * @param mixed  $value Valor principal (número).
     * @param string $subtitle Legenda/descrição.
     * @param string $color Cor de destaque (opcional).
     * @return string HTML do card.
     */
    public static function render_kpi_card( $title, $value, $subtitle = '', $color = '#3b82f6' ) {
        ob_start();
        ?>
        <div class="dps-dashboard-card" style="border-left: 4px solid <?php echo esc_attr( $color ); ?>;">
            <div class="dps-dashboard-card__title"><?php echo esc_html( $title ); ?></div>
            <div class="dps-dashboard-card__value" style="color: <?php echo esc_attr( $color ); ?>;">
                <?php echo esc_html( $value ); ?>
            </div>
            <?php if ( ! empty( $subtitle ) ) : ?>
                <div class="dps-dashboard-card__subtitle"><?php echo esc_html( $subtitle ); ?></div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
