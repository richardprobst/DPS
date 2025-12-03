<?php
/**
 * Trait com métodos de renderização para a Agenda.
 *
 * Este trait contém métodos auxiliares extraídos do método principal
 * render_agenda_shortcode() para melhorar a manutenibilidade do código.
 *
 * @package DPS_Agenda_Addon
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Trait DPS_Agenda_Renderer
 *
 * Métodos de renderização extraídos da classe principal.
 */
trait DPS_Agenda_Renderer {

    /**
     * Renderiza mensagem de acesso negado.
     *
     * @since 1.3.0
     * @return string HTML da mensagem de acesso negado.
     */
    private function render_access_denied() {
        $login_url = wp_login_url( get_permalink() );
        return '<p>' . esc_html__( 'Você precisa estar logado como administrador para acessar a agenda.', 'dps-agenda-addon' ) . ' <a href="' . esc_url( $login_url ) . '">' . esc_html__( 'Fazer login', 'dps-agenda-addon' ) . '</a></p>';
    }

    /**
     * Processa e sanitiza parâmetros da requisição.
     *
     * @since 1.3.0
     * @return array Parâmetros sanitizados.
     */
    private function parse_request_params() {
        $selected_date = isset( $_GET['dps_date'] ) ? sanitize_text_field( $_GET['dps_date'] ) : '';
        if ( ! $selected_date ) {
            $selected_date = current_time( 'Y-m-d' );
        }

        $view = isset( $_GET['view'] ) ? sanitize_text_field( $_GET['view'] ) : 'day';
        $is_week_view = ( $view === 'week' || $view === 'calendar' );
        $show_all = isset( $_GET['show_all'] ) ? sanitize_text_field( $_GET['show_all'] ) : '';
        $group_by_client = isset( $_GET['group_by_client'] ) && $_GET['group_by_client'] === '1';

        // Filtros
        $filter_client = isset( $_GET['filter_client'] ) ? intval( $_GET['filter_client'] ) : 0;
        $filter_status = isset( $_GET['filter_status'] ) ? sanitize_text_field( $_GET['filter_status'] ) : '';
        $filter_service = isset( $_GET['filter_service'] ) ? intval( $_GET['filter_service'] ) : 0;

        // Paginação
        $paged = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;

        return [
            'selected_date'    => $selected_date,
            'view'             => $view,
            'is_week_view'     => $is_week_view,
            'show_all'         => $show_all,
            'group_by_client'  => $group_by_client,
            'filter_client'    => $filter_client,
            'filter_status'    => $filter_status,
            'filter_service'   => $filter_service,
            'paged'            => $paged,
        ];
    }

    /**
     * Obtém labels das colunas da tabela.
     *
     * @since 1.3.0
     * @return array Labels das colunas.
     */
    private function get_column_labels() {
        return [
            'date'         => __( 'Data', 'dps-agenda-addon' ),
            'time'         => __( 'Hora', 'dps-agenda-addon' ),
            'pet'          => __( 'Pet (Cliente)', 'dps-agenda-addon' ),
            'service'      => __( 'Serviço', 'dps-agenda-addon' ),
            'status'       => __( 'Status', 'dps-agenda-addon' ),
            'map'          => __( 'Mapa', 'dps-agenda-addon' ),
            'confirmation' => __( 'Confirmação', 'dps-agenda-addon' ),
            'charge'       => __( 'Cobrança', 'dps-agenda-addon' ),
        ];
    }

    /**
     * Obtém opções de status para o filtro.
     *
     * @since 1.3.0
     * @return array Opções de status.
     */
    private function get_status_options() {
        return [
            ''                => __( 'Todos os status', 'dps-agenda-addon' ),
            'pendente'        => __( 'Pendente', 'dps-agenda-addon' ),
            'finalizado'      => __( 'Finalizado', 'dps-agenda-addon' ),
            'finalizado_pago' => __( 'Finalizado e pago', 'dps-agenda-addon' ),
            'cancelado'       => __( 'Cancelado', 'dps-agenda-addon' ),
        ];
    }

    /**
     * Obtém opções de status para o dropdown na tabela.
     *
     * @since 1.3.0
     * @return array Opções de status.
     */
    private function get_table_status_options() {
        return [
            'pendente'        => __( 'Pendente', 'dps-agenda-addon' ),
            'finalizado'      => __( 'Finalizado', 'dps-agenda-addon' ),
            'finalizado_pago' => __( 'Finalizado e pago', 'dps-agenda-addon' ),
            'cancelado'       => __( 'Cancelado', 'dps-agenda-addon' ),
        ];
    }

    /**
     * Calcula datas de navegação (anterior/próximo).
     *
     * @since 1.3.0
     * @param string $selected_date Data selecionada.
     * @param bool   $is_week_view  Se é visualização semanal.
     * @return array ['prev' => string, 'next' => string]
     */
    private function calculate_nav_dates( $selected_date, $is_week_view ) {
        $date_obj = DateTime::createFromFormat( 'Y-m-d', $selected_date );
        
        if ( $is_week_view ) {
            $prev_date = $date_obj ? $date_obj->modify( '-7 days' )->format( 'Y-m-d' ) : '';
            $date_obj  = DateTime::createFromFormat( 'Y-m-d', $selected_date );
            $next_date = $date_obj ? $date_obj->modify( '+7 days' )->format( 'Y-m-d' ) : '';
        } else {
            $prev_date = $date_obj ? $date_obj->modify( '-1 day' )->format( 'Y-m-d' ) : '';
            $date_obj  = DateTime::createFromFormat( 'Y-m-d', $selected_date );
            $next_date = $date_obj ? $date_obj->modify( '+1 day' )->format( 'Y-m-d' ) : '';
        }

        return [
            'prev' => $prev_date,
            'next' => $next_date,
        ];
    }

    /**
     * Obtém lista de clientes para o filtro.
     *
     * @since 1.3.0
     * @return array Lista de posts de clientes.
     */
    private function get_clients_for_filter() {
        $clients_limit = apply_filters( 'dps_agenda_clients_limit', DPS_Agenda_Addon::CLIENTS_LIST_LIMIT );
        
        return get_posts( [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => $clients_limit,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post_status'    => 'publish',
        ] );
    }

    /**
     * Obtém lista de serviços para o filtro.
     *
     * @since 1.3.0
     * @return array Lista de posts de serviços.
     */
    private function get_services_for_filter() {
        $services_limit = apply_filters( 'dps_agenda_services_limit', DPS_Agenda_Addon::SERVICES_LIST_LIMIT );
        
        return get_posts( [
            'post_type'      => 'dps_service',
            'posts_per_page' => $services_limit,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post_status'    => 'publish',
        ] );
    }

    /**
     * Aplica filtros aos agendamentos.
     *
     * @since 1.3.0
     * @param array $appointments Lista de agendamentos.
     * @param int   $filter_client ID do cliente para filtrar.
     * @param string $filter_status Status para filtrar.
     * @param int   $filter_service ID do serviço para filtrar.
     * @return array Agendamentos filtrados.
     */
    private function apply_filters_to_appointments( $appointments, $filter_client, $filter_status, $filter_service ) {
        $filtered = [];
        
        foreach ( $appointments as $appt ) {
            $match = true;
            
            // Filtro por cliente
            if ( $filter_client ) {
                $cid = get_post_meta( $appt->ID, 'appointment_client_id', true );
                if ( intval( $cid ) !== $filter_client ) {
                    $match = false;
                }
            }
            
            // Filtro por status
            if ( $filter_status ) {
                $st_val = get_post_meta( $appt->ID, 'appointment_status', true );
                if ( ! $st_val ) {
                    $st_val = 'pendente';
                }
                if ( $st_val !== $filter_status ) {
                    $match = false;
                }
            }
            
            // Filtro por serviço
            if ( $filter_service ) {
                $service_ids_meta = get_post_meta( $appt->ID, 'appointment_services', true );
                if ( ! is_array( $service_ids_meta ) || ! in_array( $filter_service, $service_ids_meta ) ) {
                    $match = false;
                }
            }
            
            if ( $match ) {
                $filtered[] = $appt;
            }
        }
        
        return $filtered;
    }

    /**
     * Separa agendamentos em pendentes e finalizados.
     *
     * @since 1.3.0
     * @param array $appointments Lista de agendamentos.
     * @return array ['upcoming' => array, 'completed' => array]
     */
    private function separate_appointments_by_status( $appointments ) {
        $upcoming  = [];
        $completed = [];
        
        foreach ( $appointments as $appt ) {
            $st = get_post_meta( $appt->ID, 'appointment_status', true );
            if ( ! $st ) {
                $st = 'pendente';
            }
            if ( $st === 'pendente' ) {
                $upcoming[] = $appt;
            } else {
                $completed[] = $appt;
            }
        }
        
        return [
            'upcoming'  => $upcoming,
            'completed' => $completed,
        ];
    }

    /**
     * Ordena agendamentos por data/hora.
     *
     * @since 1.3.0
     * @param array $appointments Lista de agendamentos.
     * @param string $order 'ASC' ou 'DESC'.
     * @return array Agendamentos ordenados.
     */
    private function sort_appointments_by_datetime( $appointments, $order = 'DESC' ) {
        usort( $appointments, function( $a, $b ) use ( $order ) {
            $date_a = get_post_meta( $a->ID, 'appointment_date', true );
            $time_a = get_post_meta( $a->ID, 'appointment_time', true );
            $date_b = get_post_meta( $b->ID, 'appointment_date', true );
            $time_b = get_post_meta( $b->ID, 'appointment_time', true );
            $dt_a   = strtotime( trim( $date_a . ' ' . $time_a ) );
            $dt_b   = strtotime( trim( $date_b . ' ' . $time_b ) );
            
            if ( $dt_a === $dt_b ) {
                return $order === 'DESC' ? ( $b->ID <=> $a->ID ) : ( $a->ID <=> $b->ID );
            }
            
            return $order === 'DESC' ? ( $dt_b <=> $dt_a ) : ( $dt_a <=> $dt_b );
        } );
        
        return $appointments;
    }

    /**
     * Pre-carrega posts e metadados relacionados para performance.
     *
     * @since 1.3.0
     * @param array $appointments Lista de agendamentos.
     */
    private function prime_related_caches( $appointments ) {
        if ( empty( $appointments ) ) {
            return;
        }
        
        $client_ids = [];
        $pet_ids    = [];
        
        foreach ( $appointments as $appt ) {
            $cid = get_post_meta( $appt->ID, 'appointment_client_id', true );
            $pid = get_post_meta( $appt->ID, 'appointment_pet_id', true );
            if ( $cid ) {
                $client_ids[] = (int) $cid;
            }
            if ( $pid ) {
                $pet_ids[] = (int) $pid;
            }
        }
        
        $related_ids = array_unique( array_merge( $client_ids, $pet_ids ) );
        if ( ! empty( $related_ids ) ) {
            _prime_post_caches( $related_ids, false, false );
            update_meta_cache( 'post', $related_ids );
        }
    }
}
