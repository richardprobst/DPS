<?php
/**
 * Helper para gerenciamento de capacidade e lotação da AGENDA.
 *
 * Fornece funcionalidades para:
 * - Configurar capacidade máxima por faixa horária
 * - Calcular ocupação/lotação
 * - Gerar dados para heatmap de capacidade
 *
 * @package DPS_Agenda_Addon
 * @since 1.4.0
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DPS_Agenda_Capacity_Helper {

    /**
     * Retorna a configuração de capacidade padrão.
     *
     * @return array Configuração de capacidade.
     */
    public static function get_default_capacity_config() {
        return [
            'morning'   => 10, // 08:00 - 11:59
            'afternoon' => 10, // 12:00 - 17:59
        ];
    }

    /**
     * Obtém a configuração de capacidade atual.
     *
     * @return array Configuração de capacidade.
     */
    public static function get_capacity_config() {
        $config = get_option( 'dps_agenda_capacity_config', self::get_default_capacity_config() );

        // Garante valores mínimos
        if ( ! isset( $config['morning'] ) || $config['morning'] < 1 ) {
            $config['morning'] = 10;
        }
        if ( ! isset( $config['afternoon'] ) || $config['afternoon'] < 1 ) {
            $config['afternoon'] = 10;
        }

        return $config;
    }

    /**
     * Salva a configuração de capacidade.
     *
     * @param array $config Configuração de capacidade.
     * @return bool True se salvo com sucesso.
     */
    public static function save_capacity_config( $config ) {
        // Valida e sanitiza
        $sanitized = [
            'morning'   => max( 1, intval( $config['morning'] ?? 10 ) ),
            'afternoon' => max( 1, intval( $config['afternoon'] ?? 10 ) ),
        ];

        return update_option( 'dps_agenda_capacity_config', $sanitized );
    }

    /**
     * Retorna a capacidade para um slot específico.
     *
     * @param string $period 'morning' ou 'afternoon'.
     * @return int Capacidade máxima.
     */
    public static function get_capacity_for_period( $period ) {
        $config = self::get_capacity_config();
        return isset( $config[ $period ] ) ? intval( $config[ $period ] ) : 10;
    }

    /**
     * Determina o período (morning/afternoon) baseado em um horário.
     *
     * @param string $time Horário no formato H:i.
     * @return string 'morning' ou 'afternoon'.
     */
    public static function get_period_from_time( $time ) {
        $hour = intval( substr( $time, 0, 2 ) );
        return ( $hour < 12 ) ? 'morning' : 'afternoon';
    }

    /**
     * Retorna dados de heatmap de capacidade para um intervalo de datas.
     *
     * @param string $start_date Data inicial no formato Y-m-d.
     * @param string $end_date   Data final no formato Y-m-d.
     * @return array Dados do heatmap.
     */
    public static function get_capacity_heatmap_data( $start_date, $end_date ) {
        $capacity_config = self::get_capacity_config();
        $heatmap_data = [];

        // Itera sobre cada dia no intervalo
        $current_date = $start_date;
        while ( $current_date <= $end_date ) {
            // Obtém agendamentos do dia
            $appointments = self::get_appointments_for_date( $current_date );

            // Inicializa contadores por período
            $periods = [
                'morning'   => 0,
                'afternoon' => 0,
            ];

            // Conta atendimentos por período
            foreach ( $appointments as $appt ) {
                $time = get_post_meta( $appt->ID, 'appointment_time', true );
                if ( empty( $time ) ) {
                    continue;
                }

                $period = self::get_period_from_time( $time );
                $periods[ $period ]++;
            }

            // Calcula ocupação
            $heatmap_data[ $current_date ] = [
                'morning' => [
                    'scheduled' => $periods['morning'],
                    'capacity'  => $capacity_config['morning'],
                    'occupancy' => $capacity_config['morning'] > 0 ? ( $periods['morning'] / $capacity_config['morning'] ) : 0,
                ],
                'afternoon' => [
                    'scheduled' => $periods['afternoon'],
                    'capacity'  => $capacity_config['afternoon'],
                    'occupancy' => $capacity_config['afternoon'] > 0 ? ( $periods['afternoon'] / $capacity_config['afternoon'] ) : 0,
                ],
            ];

            // Próximo dia
            $current_date = date( 'Y-m-d', strtotime( $current_date . ' +1 day' ) );
        }

        return $heatmap_data;
    }

    /**
     * Obtém agendamentos de uma data específica (apenas não cancelados).
     *
     * @param string $date Data no formato Y-m-d.
     * @return array Lista de posts.
     */
    private static function get_appointments_for_date( $date ) {
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
            'no_found_rows'  => true,
        ] );

        // Filtra cancelados
        $filtered = [];
        foreach ( $appointments as $appt ) {
            $status = get_post_meta( $appt->ID, 'appointment_status', true );
            if ( $status !== 'cancelado' ) {
                $filtered[] = $appt;
            }
        }

        return $filtered;
    }

    /**
     * Retorna a cor CSS baseada na ocupação.
     *
     * @param float $occupancy Ocupação (0 a 1+).
     * @return string Cor CSS.
     */
    public static function get_occupancy_color( $occupancy ) {
        if ( $occupancy <= 0.5 ) {
            // 0-50%: verde
            return '#d1fae5';
        } elseif ( $occupancy <= 0.8 ) {
            // 51-80%: amarelo
            return '#fef3c7';
        } else {
            // >80%: vermelho
            return '#fee2e2';
        }
    }

    /**
     * Retorna a cor do texto baseada na ocupação.
     *
     * @param float $occupancy Ocupação (0 a 1+).
     * @return string Cor CSS do texto.
     */
    public static function get_occupancy_text_color( $occupancy ) {
        if ( $occupancy <= 0.5 ) {
            return '#10b981';
        } elseif ( $occupancy <= 0.8 ) {
            return '#f59e0b';
        } else {
            return '#ef4444';
        }
    }

    /**
     * Retorna datas de início e fim da semana atual.
     *
     * @param string $reference_date Data de referência (Y-m-d). Default: hoje.
     * @return array ['start' => 'Y-m-d', 'end' => 'Y-m-d']
     */
    public static function get_week_dates( $reference_date = '' ) {
        if ( empty( $reference_date ) ) {
            $reference_date = current_time( 'Y-m-d' );
        }

        $timestamp = strtotime( $reference_date );
        $day_of_week = date( 'N', $timestamp ); // 1 (segunda) a 7 (domingo)

        // Calcula início da semana (segunda-feira)
        $start_timestamp = strtotime( '-' . ( $day_of_week - 1 ) . ' days', $timestamp );
        $start_date = date( 'Y-m-d', $start_timestamp );

        // Calcula fim da semana (domingo)
        $end_timestamp = strtotime( '+' . ( 7 - $day_of_week ) . ' days', $timestamp );
        $end_date = date( 'Y-m-d', $end_timestamp );

        return [
            'start' => $start_date,
            'end'   => $end_date,
        ];
    }

    /**
     * Renderiza o heatmap de capacidade.
     *
     * @param string $start_date Data inicial.
     * @param string $end_date   Data final.
     * @return string HTML do heatmap.
     */
    public static function render_capacity_heatmap( $start_date, $end_date ) {
        $heatmap_data = self::get_capacity_heatmap_data( $start_date, $end_date );

        ob_start();
        ?>
        <div class="dps-capacity-heatmap">
            <table class="dps-heatmap-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Período', 'dps-agenda-addon' ); ?></th>
                        <?php
                        // Cabeçalhos de dias da semana
                        $current_date = $start_date;
                        while ( $current_date <= $end_date ) :
                            $timestamp = strtotime( $current_date );
                            $day_name = date_i18n( 'D', $timestamp );
                            $day_num = date( 'd/m', $timestamp );
                            ?>
                            <th>
                                <div class="dps-heatmap-day-header">
                                    <div class="dps-heatmap-day-name"><?php echo esc_html( $day_name ); ?></div>
                                    <div class="dps-heatmap-day-num"><?php echo esc_html( $day_num ); ?></div>
                                </div>
                            </th>
                            <?php
                            $current_date = date( 'Y-m-d', strtotime( $current_date . ' +1 day' ) );
                        endwhile;
                        ?>
                    </tr>
                </thead>
                <tbody>
                    <!-- Manhã -->
                    <tr>
                        <td class="dps-heatmap-period-label">
                            <strong><?php esc_html_e( 'Manhã', 'dps-agenda-addon' ); ?></strong>
                            <br><small>08:00 - 11:59</small>
                        </td>
                        <?php
                        $current_date = $start_date;
                        while ( $current_date <= $end_date ) :
                            $data = $heatmap_data[ $current_date ]['morning'];
                            $bg_color = self::get_occupancy_color( $data['occupancy'] );
                            $text_color = self::get_occupancy_text_color( $data['occupancy'] );
                            $percentage = round( $data['occupancy'] * 100 );
                            ?>
                            <td style="background-color: <?php echo esc_attr( $bg_color ); ?>; color: <?php echo esc_attr( $text_color ); ?>;">
                                <div class="dps-heatmap-cell">
                                    <div class="dps-heatmap-ratio">
                                        <?php echo esc_html( $data['scheduled'] . '/' . $data['capacity'] ); ?>
                                    </div>
                                    <div class="dps-heatmap-percentage">
                                        <?php echo esc_html( $percentage . '%' ); ?>
                                    </div>
                                </div>
                            </td>
                            <?php
                            $current_date = date( 'Y-m-d', strtotime( $current_date . ' +1 day' ) );
                        endwhile;
                        ?>
                    </tr>
                    
                    <!-- Tarde -->
                    <tr>
                        <td class="dps-heatmap-period-label">
                            <strong><?php esc_html_e( 'Tarde', 'dps-agenda-addon' ); ?></strong>
                            <br><small>12:00 - 17:59</small>
                        </td>
                        <?php
                        $current_date = $start_date;
                        while ( $current_date <= $end_date ) :
                            $data = $heatmap_data[ $current_date ]['afternoon'];
                            $bg_color = self::get_occupancy_color( $data['occupancy'] );
                            $text_color = self::get_occupancy_text_color( $data['occupancy'] );
                            $percentage = round( $data['occupancy'] * 100 );
                            ?>
                            <td style="background-color: <?php echo esc_attr( $bg_color ); ?>; color: <?php echo esc_attr( $text_color ); ?>;">
                                <div class="dps-heatmap-cell">
                                    <div class="dps-heatmap-ratio">
                                        <?php echo esc_html( $data['scheduled'] . '/' . $data['capacity'] ); ?>
                                    </div>
                                    <div class="dps-heatmap-percentage">
                                        <?php echo esc_html( $percentage . '%' ); ?>
                                    </div>
                                </div>
                            </td>
                            <?php
                            $current_date = date( 'Y-m-d', strtotime( $current_date . ' +1 day' ) );
                        endwhile;
                        ?>
                    </tr>
                </tbody>
            </table>
            
            <!-- Legenda -->
            <div class="dps-heatmap-legend">
                <div class="dps-heatmap-legend-item">
                    <span class="dps-heatmap-legend-color" style="background-color: #d1fae5;"></span>
                    <span><?php esc_html_e( '0-50% (Disponível)', 'dps-agenda-addon' ); ?></span>
                </div>
                <div class="dps-heatmap-legend-item">
                    <span class="dps-heatmap-legend-color" style="background-color: #fef3c7;"></span>
                    <span><?php esc_html_e( '51-80% (Ocupado)', 'dps-agenda-addon' ); ?></span>
                </div>
                <div class="dps-heatmap-legend-item">
                    <span class="dps-heatmap-legend-color" style="background-color: #fee2e2;"></span>
                    <span><?php esc_html_e( '>80% (Lotado)', 'dps-agenda-addon' ); ?></span>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
