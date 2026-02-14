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
        $login_url = wp_login_url( DPS_URL_Builder::safe_get_permalink() );
        return '<p>' . esc_html__( 'Você precisa estar logado como administrador para acessar a agenda.', 'dps-agenda-addon' ) . ' <a href="' . esc_url( $login_url ) . '">' . esc_html__( 'Fazer login', 'dps-agenda-addon' ) . '</a></p>';
    }

    /**
     * Renderiza o botão de reagendamento.
     *
     * Método helper para evitar duplicação de código nas diferentes abas.
     *
     * @since 1.1.0
     * @param int    $appt_id ID do agendamento.
     * @param string $date    Data do agendamento (Y-m-d).
     * @param string $time    Hora do agendamento (H:i).
     * @return string HTML do botão de reagendamento.
     */
    private function render_reschedule_button( $appt_id, $date, $time ) {
        return '<a href="#" class="dps-quick-action dps-quick-reschedule" data-appt-id="' . esc_attr( $appt_id ) . '" data-date="' . esc_attr( $date ) . '" data-time="' . esc_attr( $time ) . '" title="' . esc_attr__( 'Reagendar', 'dps-agenda-addon' ) . '">📅 ' . esc_html__( 'Reagendar', 'dps-agenda-addon' ) . '</a>';
    }

    /**
     * Verifica se o pet possui restrições de produtos e retorna badge HTML.
     *
     * @since 1.5.0
     * @param int|WP_Post $pet Pet ID ou objeto.
     * @return string HTML do badge de restrição ou string vazia.
     */
    private function get_pet_product_restrictions_badge( $pet ) {
        $pet_id = is_object( $pet ) ? $pet->ID : absint( $pet );
        if ( ! $pet_id ) {
            return '';
        }
        
        $has_restrictions = false;
        $restrictions_items = [];
        
        // Verifica preferência de shampoo especial
        $shampoo = get_post_meta( $pet_id, 'pet_shampoo_pref', true );
        if ( $shampoo && '' !== $shampoo ) {
            $has_restrictions = true;
            $shampoo_labels = [
                'hipoalergenico' => __( 'Shampoo hipoalergênico', 'dps-agenda-addon' ),
                'antisseptico'   => __( 'Shampoo antisséptico', 'dps-agenda-addon' ),
                'pelagem_branca' => __( 'Shampoo p/ pelagem branca', 'dps-agenda-addon' ),
                'pelagem_escura' => __( 'Shampoo p/ pelagem escura', 'dps-agenda-addon' ),
                'antipulgas'     => __( 'Shampoo antipulgas', 'dps-agenda-addon' ),
                'hidratante'     => __( 'Shampoo hidratante', 'dps-agenda-addon' ),
                'outro'          => __( 'Shampoo especial', 'dps-agenda-addon' ),
            ];
            $restrictions_items[] = $shampoo_labels[ $shampoo ] ?? $shampoo;
        }
        
        // Verifica proibição de perfume
        $perfume = get_post_meta( $pet_id, 'pet_perfume_pref', true );
        if ( 'sem_perfume' === $perfume ) {
            $has_restrictions = true;
            $restrictions_items[] = __( '❌ SEM PERFUME', 'dps-agenda-addon' );
        } elseif ( 'hipoalergenico' === $perfume ) {
            $has_restrictions = true;
            $restrictions_items[] = __( 'Perfume hipoalergênico', 'dps-agenda-addon' );
        }
        
        // Verifica preferência de adereços
        $accessories = get_post_meta( $pet_id, 'pet_accessories_pref', true );
        if ( 'sem_aderecos' === $accessories ) {
            $has_restrictions = true;
            $restrictions_items[] = __( 'Sem adereços', 'dps-agenda-addon' );
        } elseif ( $accessories && '' !== $accessories ) {
            $accessories_labels = [
                'lacinho' => __( 'Usar lacinho', 'dps-agenda-addon' ),
                'gravata' => __( 'Usar gravata', 'dps-agenda-addon' ),
                'lenco'   => __( 'Usar lenço', 'dps-agenda-addon' ),
                'bandana' => __( 'Usar bandana', 'dps-agenda-addon' ),
            ];
            if ( isset( $accessories_labels[ $accessories ] ) ) {
                $restrictions_items[] = $accessories_labels[ $accessories ];
            }
        }
        
        // Verifica outras restrições
        $other = get_post_meta( $pet_id, 'pet_product_restrictions', true );
        if ( $other && '' !== trim( $other ) ) {
            $has_restrictions = true;
            $restrictions_items[] = esc_html( $other );
        }
        
        if ( ! $has_restrictions ) {
            return '';
        }
        
        $tooltip = implode( ' | ', $restrictions_items );
        return ' <span class="dps-pet-badge dps-pet-badge--restrictions" title="' . esc_attr( $tooltip ) . '">🧴</span>';
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
        $filter_staff = isset( $_GET['filter_staff'] ) ? intval( $_GET['filter_staff'] ) : 0;

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
            'filter_staff'     => $filter_staff,
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
            'payment'      => __( 'Pagamento', 'dps-agenda-addon' ),
            'map'          => __( 'Mapa', 'dps-agenda-addon' ),
            'confirmation' => __( 'Confirmação', 'dps-agenda-addon' ),
            'charge'       => __( 'Cobrança', 'dps-agenda-addon' ),
        ];
    }

    /**
     * Cache para configuração de status.
     *
     * @since 1.3.1
     * @var array|null
     */
    private $status_config_cache = null;

    /**
     * Obtém opções de status para o filtro.
     * Usa constantes centralizadas da classe principal.
     *
     * @since 1.3.0
     * @since 1.3.1 Refatorado para usar constantes centralizadas com cache.
     * @return array Opções de status.
     */
    private function get_status_options() {
        if ( null === $this->status_config_cache ) {
            $this->status_config_cache = DPS_Agenda_Addon::get_status_config();
        }
        $options = [ '' => __( 'Todos os status', 'dps-agenda-addon' ) ];
        foreach ( $this->status_config_cache as $key => $data ) {
            $options[ $key ] = $data['label'];
        }
        return $options;
    }

    /**
     * Obtém opções de status para o dropdown na tabela.
     * Usa constantes centralizadas da classe principal.
     *
     * @since 1.3.0
     * @since 1.3.1 Refatorado para usar constantes centralizadas com cache.
     * @return array Opções de status.
     */
    private function get_table_status_options() {
        if ( null === $this->status_config_cache ) {
            $this->status_config_cache = DPS_Agenda_Addon::get_status_config();
        }
        $options = [];
        foreach ( $this->status_config_cache as $key => $data ) {
            $options[ $key ] = $data['label'];
        }
        return $options;
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
     * @since 1.5.0 Adicionado filtro por profissional (staff).
     * @param array $appointments Lista de agendamentos.
     * @param int   $filter_client ID do cliente para filtrar.
     * @param string $filter_status Status para filtrar.
     * @param int   $filter_service ID do serviço para filtrar.
     * @param int   $filter_staff ID do profissional para filtrar (opcional).
     * @return array Agendamentos filtrados.
     */
    private function apply_filters_to_appointments( $appointments, $filter_client, $filter_status, $filter_service, $filter_staff = 0 ) {
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
            
            // Filtro por profissional (integração com Groomers Add-on)
            if ( $filter_staff ) {
                $staff_ids = get_post_meta( $appt->ID, '_dps_groomers', true );
                if ( ! is_array( $staff_ids ) || ! in_array( $filter_staff, array_map( 'intval', $staff_ids ) ) ) {
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

    /**
     * Renderiza uma linha da tabela de agendamentos.
     * 
     * Função reutilizável para renderizar o HTML de uma linha de atendimento,
     * usada tanto na montagem inicial quanto nas respostas AJAX.
     * 
     * @since 1.1.0
     * @param WP_Post $appt Post do agendamento.
     * @param array   $column_labels Labels das colunas.
     * @return string HTML da linha <tr>.
     */
    public function render_appointment_row( $appt, $column_labels ) {
        $date      = get_post_meta( $appt->ID, 'appointment_date', true );
        $time      = get_post_meta( $appt->ID, 'appointment_time', true );
        $client_id = get_post_meta( $appt->ID, 'appointment_client_id', true );
        $pet_id    = get_post_meta( $appt->ID, 'appointment_pet_id', true );
        
        $client_post = $client_id ? get_post( $client_id ) : null;
        $pet_post    = $pet_id ? get_post( $pet_id ) : null;
        
        $status = get_post_meta( $appt->ID, 'appointment_status', true );
        if ( ! $status ) {
            $status = 'pendente';
        }
        
        $appt_version = intval( get_post_meta( $appt->ID, '_dps_appointment_version', true ) );
        if ( $appt_version < 1 ) {
            $appt_version = 1;
            update_post_meta( $appt->ID, '_dps_appointment_version', $appt_version );
        }
        
        // Detecta se o atendimento está atrasado (UX-3)
        $is_late = $this->is_appointment_late( $date, $time, $status );
        $row_classes = [ 'status-' . $status ];
        if ( $is_late ) {
            $row_classes[] = 'is-late';
        }
        
        ob_start();
        
        // Cada linha recebe classes de status e um data attribute para permitir manipulação via JS.
        echo '<tr data-appt-id="' . esc_attr( $appt->ID ) . '" class="' . esc_attr( implode( ' ', $row_classes ) ) . '">';
        
        // Mostra a data no formato dia-mês-ano
        echo '<td data-label="' . esc_attr( $column_labels['date'] ?? __( 'Data', 'dps-agenda-addon' ) ) . '">' . esc_html( date_i18n( 'd-m-Y', strtotime( $date ) ) ) . '</td>';
        echo '<td data-label="' . esc_attr( $column_labels['time'] ?? __( 'Hora', 'dps-agenda-addon' ) ) . '">' . esc_html( $time ) . '</td>';
        
        // Nome do pet e cliente com flag de agressividade melhorada (FASE 2)
        $pet_name    = $pet_post ? $pet_post->post_title : '';
        $client_name = $client_post ? $client_post->post_title : '';
        $aggr_flag   = '';
        $restrictions_badge = '';
        $consent_badge = '';
        if ( $pet_post ) {
            $aggr = get_post_meta( $pet_post->ID, 'pet_aggressive', true );
            if ( $aggr ) {
                // Flag melhorada com emoji e tooltip
                $aggr_flag = ' <span class="dps-aggressive-flag" title="' . esc_attr__( 'Pet agressivo - cuidado no manejo', 'dps-agenda-addon' ) . '">⚠️</span>';
            }
            $restrictions_badge = $this->get_pet_product_restrictions_badge( $pet_post );
        }
        if ( $client_id && class_exists( 'DPS_Base_Frontend' ) ) {
            $consent_data = DPS_Base_Frontend::get_client_tosa_consent_data( $client_id );
            $badge_class  = 'dps-consent-badge--missing';
            $badge_text   = __( 'Consentimento pendente', 'dps-agenda-addon' );
            if ( 'granted' === $consent_data['status'] ) {
                $badge_class = 'dps-consent-badge--ok';
                $badge_text  = __( 'Consentimento OK', 'dps-agenda-addon' );
            } elseif ( 'revoked' === $consent_data['status'] ) {
                $badge_class = 'dps-consent-badge--danger';
                $badge_text  = __( 'Consentimento revogado', 'dps-agenda-addon' );
            }
            $consent_badge = '<div class="dps-consent-status"><span class="dps-consent-badge ' . esc_attr( $badge_class ) . '">' . esc_html( $badge_text ) . '</span></div>';
        }
        echo '<td data-label="' . esc_attr( $column_labels['pet'] ?? __( 'Pet (Cliente)', 'dps-agenda-addon' ) ) . '">' . esc_html( $pet_name . ( $client_name ? ' (' . $client_name . ')' : '' ) ) . $aggr_flag . $restrictions_badge . $consent_badge . '</td>';
        
        // Serviços e assinatura
        echo '<td data-label="' . esc_attr( $column_labels['service'] ?? __( 'Serviço', 'dps-agenda-addon' ) ) . '">';
        $sub_id_meta = get_post_meta( $appt->ID, 'subscription_id', true );
        if ( $sub_id_meta ) {
            echo '<span class="dps-subscription-flag" style="font-weight:bold; color:#0073aa;">' . esc_html__( 'Assinatura', 'dps-agenda-addon' ) . '</span> ';
        }
        $service_ids = get_post_meta( $appt->ID, 'appointment_services', true );
        if ( is_array( $service_ids ) && ! empty( $service_ids ) ) {
            // Link com ícone para abrir modal de serviços (FASE 2)
            echo '<a href="#" class="dps-services-link" data-appt-id="' . esc_attr( $appt->ID ) . '" title="' . esc_attr__( 'Ver detalhes dos serviços', 'dps-agenda-addon' ) . '">';
            echo esc_html__( 'Ver serviços', 'dps-agenda-addon' ) . ' ↗';
            echo '</a>';
        } else {
            echo '-';
        }
        echo '</td>';
        
        // Status (editable if admin)
        echo '<td data-label="' . esc_attr( $column_labels['status'] ?? __( 'Status', 'dps-agenda-addon' ) ) . '">';
        $can_edit = is_user_logged_in() && current_user_can( 'manage_options' );
        
        // Define lista de status padrão
        $statuses = [
            'pendente'        => __( 'Pendente', 'dps-agenda-addon' ),
            'finalizado'      => __( 'Finalizado', 'dps-agenda-addon' ),
            'finalizado_pago' => __( 'Finalizado e pago', 'dps-agenda-addon' ),
            'cancelado'       => __( 'Cancelado', 'dps-agenda-addon' ),
        ];
        
        // Para agendamentos de assinatura, não há necessidade de usar o status "finalizado e pago"
        $is_subscription = ! empty( $sub_id_meta );
        if ( $is_subscription ) {
            unset( $statuses['finalizado_pago'] );
            // Se o status atual for finalizado_pago, normaliza para finalizado
            if ( $status === 'finalizado_pago' ) {
                $status = 'finalizado';
                update_post_meta( $appt->ID, 'appointment_status', $status );
            }
        }
        
        if ( $can_edit ) {
            echo '<select class="dps-status-select" data-appt-id="' . esc_attr( $appt->ID ) . '" data-current-status="' . esc_attr( $status ) . '" data-appt-version="' . esc_attr( $appt_version ) . '" aria-label="' . esc_attr__( 'Alterar status do agendamento', 'dps-agenda-addon' ) . '">';
            foreach ( $statuses as $value => $label ) {
                echo '<option value="' . esc_attr( $value ) . '"' . selected( $status, $value, false ) . '>' . esc_html( $label ) . '</option>';
            }
            echo '</select>';
        } else {
            echo esc_html( $statuses[ $status ] ?? $status );
        }
        echo '</td>';
        
        // FASE 3: Coluna de Pagamento
        echo '<td data-label="' . esc_attr( $column_labels['payment'] ?? __( 'Pagamento', 'dps-agenda-addon' ) ) . '">';
        echo DPS_Agenda_Payment_Helper::render_payment_badge( $appt->ID );
        echo DPS_Agenda_Payment_Helper::render_payment_tooltip( $appt->ID );
        echo DPS_Agenda_Payment_Helper::render_resend_button( $appt->ID );
        echo '</td>';
        
        // FASE 3: Mapa + TaxiDog + GPS
        echo '<td data-label="' . esc_attr( $column_labels['map'] ?? __( 'Mapa', 'dps-agenda-addon' ) ) . '">';
        
        // Renderiza badge de TaxiDog se aplicável
        $taxidog_badge = DPS_Agenda_TaxiDog_Helper::render_taxidog_badge( $appt->ID );
        if ( ! empty( $taxidog_badge ) ) {
            echo $taxidog_badge;
            echo '<br>';
        }
        
        // Link simples de mapa (mantém funcionalidade existente)
        $map_link = DPS_Agenda_GPS_Helper::render_map_link( $appt->ID );
        if ( ! empty( $map_link ) ) {
            echo $map_link;
        }
        
        // Botão "Abrir rota" (sempre Loja → Cliente)
        $route_button = DPS_Agenda_GPS_Helper::render_route_button( $appt->ID );
        if ( ! empty( $route_button ) ) {
            echo '<br>';
            echo $route_button;
        }
        
        // Ações rápidas de TaxiDog
        $taxidog_actions = DPS_Agenda_TaxiDog_Helper::render_taxidog_quick_actions( $appt->ID );
        if ( ! empty( $taxidog_actions ) ) {
            echo $taxidog_actions;
        }
        
        // Se não tem nada para mostrar
        if ( empty( $taxidog_badge ) && empty( $map_link ) && empty( $route_button ) ) {
            echo '-';
        }
        
        echo '</td>';
        
        // CONF-2/CONF-3: Confirmação de atendimento (badge + botões)
        echo '<td data-label="' . esc_attr( $column_labels['confirmation'] ?? __( 'Confirmação', 'dps-agenda-addon' ) ) . '">';
        
        // Obtém status de confirmação
        $confirmation_status = $this->get_confirmation_status( $appt->ID );
        
        // Renderiza badge de confirmação
        echo '<div class="dps-confirmation-wrapper">';
        echo $this->render_confirmation_badge( $confirmation_status );
        
        // CONF-2: Botões de confirmação (apenas para admins)
        if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
            echo '<div class="dps-confirmation-actions">';
            
            // Botão "Confirmado"
            echo '<button class="dps-confirmation-btn dps-confirmation-btn--confirmed" data-appt-id="' . esc_attr( $appt->ID ) . '" data-action="confirmed" title="' . esc_attr__( 'Marcar como confirmado', 'dps-agenda-addon' ) . '">✅</button>';
            
            // Botão "Não atendeu"
            echo '<button class="dps-confirmation-btn dps-confirmation-btn--no-answer" data-appt-id="' . esc_attr( $appt->ID ) . '" data-action="no_answer" title="' . esc_attr__( 'Não atendeu', 'dps-agenda-addon' ) . '">⚠️</button>';
            
            // Botão "Cancelado/Desmarcou"
            echo '<button class="dps-confirmation-btn dps-confirmation-btn--denied" data-appt-id="' . esc_attr( $appt->ID ) . '" data-action="denied" title="' . esc_attr__( 'Cliente cancelou', 'dps-agenda-addon' ) . '">❌</button>';
            
            // Botão "Limpar" (reset para not_sent)
            if ( $confirmation_status !== 'not_sent' ) {
                echo '<button class="dps-confirmation-btn dps-confirmation-btn--clear" data-appt-id="' . esc_attr( $appt->ID ) . '" data-action="not_sent" title="' . esc_attr__( 'Limpar status', 'dps-agenda-addon' ) . '">🔄</button>';
            }
            
            echo '</div>';
        }
        
        // Link para WhatsApp (mantém funcionalidade existente)
        if ( $status === 'pendente' && $client_post ) {
            $raw_phone = get_post_meta( $client_post->ID, 'client_phone', true );
            $whatsapp  = DPS_Phone_Helper::format_for_whatsapp( $raw_phone );
            if ( $whatsapp ) {
                $client_name = $client_post->post_title;
                $pet_names   = [];
                if ( class_exists( 'DPS_Base_Frontend' ) && method_exists( 'DPS_Base_Frontend', 'get_multi_pet_charge_data' ) ) {
                    $group_data = DPS_Base_Frontend::get_multi_pet_charge_data( $appt->ID );
                    if ( $group_data && ! empty( $group_data['pet_names'] ) ) {
                        $pet_names = $group_data['pet_names'];
                    }
                }
                if ( empty( $pet_names ) ) {
                    $pet_names[] = $pet_name ? $pet_name : __( 'Pet', 'dps-agenda-addon' );
                }
                $services_ids = get_post_meta( $appt->ID, 'appointment_services', true );
                $services_txt = '';
                if ( is_array( $services_ids ) && ! empty( $services_ids ) ) {
                    $service_names = [];
                    foreach ( $services_ids as $srv_id ) {
                        $srv_post = get_post( $srv_id );
                        if ( $srv_post ) {
                            $service_names[] = $srv_post->post_title;
                        }
                    }
                    if ( $service_names ) {
                        $services_txt = ' (' . implode( ', ', $service_names ) . ')';
                    }
                }
                $date_fmt = $date ? date_i18n( 'd/m/Y', strtotime( $date ) ) : '';
                $message = sprintf(
                    'Olá %s, tudo bem? Poderia confirmar o atendimento do(s) pet(s) %s agendado para %s às %s%s? Caso precise reagendar é só responder esta mensagem. Obrigado!',
                    $client_name,
                    implode( ', ', $pet_names ),
                    $date_fmt,
                    $time,
                    $services_txt
                );
                $message = apply_filters( 'dps_agenda_confirmation_message', $message, $appt );
                // Link de confirmação com ícone e tooltip usando helper centralizado
                if ( class_exists( 'DPS_WhatsApp_Helper' ) ) {
                    $wa_url = DPS_WhatsApp_Helper::get_link_to_client( $whatsapp, $message );
                } else {
                    // Fallback
                    $wa_url = 'https://wa.me/' . $whatsapp . '?text=' . rawurlencode( $message );
                }
                echo '<div class="dps-confirmation-whatsapp">';
                echo '<a href="' . esc_url( $wa_url ) . '" target="_blank" class="dps-whatsapp-link" title="' . esc_attr__( 'Enviar mensagem de confirmação via WhatsApp', 'dps-agenda-addon' ) . '">💬 ' . esc_html__( 'Enviar WhatsApp', 'dps-agenda-addon' ) . '</a>';
                echo '</div>';
            }
        }
        
        echo '</div>';
        echo '</td>';
        
        // Cobrança via WhatsApp
        echo '<td data-label="' . esc_attr( $column_labels['charge'] ?? __( 'Cobrança', 'dps-agenda-addon' ) ) . '">';
        // Mostra link de cobrança apenas para atendimentos finalizados (não assinaturas)
        $sub_meta = get_post_meta( $appt->ID, 'subscription_id', true );
        if ( $status === 'finalizado' && empty( $sub_meta ) ) {
            // Verifica se houve erro ao gerar link de pagamento
            $payment_link_status = get_post_meta( $appt->ID, '_dps_payment_link_status', true );
            
            if ( $payment_link_status === 'error' ) {
                // Exibe aviso de erro com tooltip
                echo '<span style="color: #ef4444; font-size: 14px;" title="' . esc_attr__( 'Houve erro ao gerar o link de pagamento. Tente novamente ou verifique o log.', 'dps-agenda-addon' ) . '">⚠️ ' . esc_html__( 'Erro ao gerar link', 'dps-agenda-addon' ) . '</span>';
                
                // Mostra detalhes do erro se disponíveis (somente para admins)
                if ( current_user_can( 'manage_options' ) ) {
                    $last_error = get_post_meta( $appt->ID, '_dps_payment_last_error', true );
                    if ( $last_error && is_array( $last_error ) ) {
                        $error_msg = isset( $last_error['message'] ) ? $last_error['message'] : __( 'Erro desconhecido', 'dps-agenda-addon' );
                        $error_time = isset( $last_error['timestamp'] ) ? $last_error['timestamp'] : '';
                        echo '<br><small style="color: #6b7280;">' . esc_html( $error_msg );
                        if ( $error_time ) {
                            echo '<br>' . esc_html( sprintf( __( 'Em: %s', 'dps-agenda-addon' ), $error_time ) );
                        }
                        echo '</small>';
                    }
                }
            } else {
                // Comportamento normal: exibe links de cobrança
                $client_phone = $client_post ? get_post_meta( $client_post->ID, 'client_phone', true ) : '';
                $total_val    = (float) get_post_meta( $appt->ID, 'appointment_total_value', true );
                $digits       = DPS_Phone_Helper::format_for_whatsapp( $client_phone );
                if ( $digits && $total_val > 0 ) {
                    $client_name = $client_post ? $client_post->post_title : '';
                    $pet_names   = [];
                    if ( class_exists( 'DPS_Base_Frontend' ) && method_exists( 'DPS_Base_Frontend', 'get_multi_pet_charge_data' ) ) {
                        $group_data = DPS_Base_Frontend::get_multi_pet_charge_data( $appt->ID );
                        if ( $group_data && ! empty( $group_data['pet_names'] ) ) {
                            $pet_names = $group_data['pet_names'];
                        }
                    }
                    if ( empty( $pet_names ) ) {
                        $pet_names[] = $pet_post ? $pet_post->post_title : '';
                    }
                    $valor_fmt    = number_format_i18n( $total_val, 2 );
                    $payment_link = get_post_meta( $appt->ID, 'dps_payment_link', true );
                    $default_link = 'https://link.mercadopago.com.br/desipetshower';
                    $link_to_use  = $payment_link ? $payment_link : $default_link;
                    $msg          = sprintf( 'Olá %s, tudo bem? O serviço do pet %s foi finalizado e o pagamento de R$ %s ainda está pendente. Para sua comodidade, você pode pagar via PIX celular 15 99160‑6299 ou utilizar o link: %s. Obrigado pela confiança!', $client_name, implode( ', ', array_filter( $pet_names ) ), $valor_fmt, $link_to_use );
                    $msg          = apply_filters( 'dps_agenda_whatsapp_message', $msg, $appt, 'agenda' );
                    $links        = [];
                    // Link de cobrança com ícone e tooltip usando helper centralizado
                    if ( class_exists( 'DPS_WhatsApp_Helper' ) ) {
                        $wa_url = DPS_WhatsApp_Helper::get_link_to_client( $client_phone, $msg );
                    } else {
                        // Fallback
                        $wa_url = 'https://wa.me/' . $digits . '?text=' . rawurlencode( $msg );
                    }
                    $links[]      = '<a href="' . esc_url( $wa_url ) . '" target="_blank" title="' . esc_attr__( 'Enviar cobrança via WhatsApp', 'dps-agenda-addon' ) . '">💰 ' . esc_html__( 'Cobrar', 'dps-agenda-addon' ) . '</a>';
                    if ( ! empty( $group_data ) && (int) $appt->ID === (int) min( $group_data['ids'] ) ) {
                        $group_total = number_format_i18n( $group_data['total'], 2 );
                        $date_fmt    = $group_data['date'] ? date_i18n( 'd/m/Y', strtotime( $group_data['date'] ) ) : '';
                        $group_msg   = sprintf( 'Olá %s, tudo bem? Finalizamos os atendimentos dos pets %s em %s às %s. O valor total ficou em R$ %s. Você pode pagar via PIX celular 15 99160‑6299 ou utilizar o link: %s. Caso tenha dúvidas estamos à disposição!', $client_name, implode( ', ', $group_data['pet_names'] ), $date_fmt, $group_data['time'], $group_total, $link_to_use );
                        $group_msg   = apply_filters( 'dps_agenda_whatsapp_group_message', $group_msg, $appt, $group_data );
                        // Link de cobrança conjunta com ícone usando helper centralizado
                        if ( class_exists( 'DPS_WhatsApp_Helper' ) ) {
                            $wa_url_group = DPS_WhatsApp_Helper::get_link_to_client( $client_phone, $group_msg );
                        } else {
                            // Fallback
                            $wa_url_group = 'https://wa.me/' . $digits . '?text=' . rawurlencode( $group_msg );
                        }
                        $links[]     = '<a href="' . esc_url( $wa_url_group ) . '" target="_blank" title="' . esc_attr__( 'Enviar cobrança conjunta via WhatsApp', 'dps-agenda-addon' ) . '">💰💰 ' . esc_html__( 'Cobrança conjunta', 'dps-agenda-addon' ) . '</a>';
                    }
                    echo implode( '<br>', $links );
                } else {
                    echo '-';
                }
            }
        } else {
            echo '-';
        }
        echo '</td>';
        
        // FASE 5: Coluna de ações (reagendar, histórico, ações rápidas)
        echo '<td data-label="' . esc_attr__( 'Ações', 'dps-agenda-addon' ) . '">';
        
        // UX-1: Botões de ação rápida de status
        if ( $can_edit && ! $is_subscription ) {
            echo '<div class="dps-quick-actions">';
            
            // Mostrar botões diferentes dependendo do status atual
            if ( $status === 'pendente' ) {
                echo '<button class="dps-quick-action-btn dps-quick-finish" data-appt-id="' . esc_attr( $appt->ID ) . '" data-action="finish" title="' . esc_attr__( 'Finalizar atendimento', 'dps-agenda-addon' ) . '">✅ ' . esc_html__( 'Finalizar', 'dps-agenda-addon' ) . '</button>';
                echo '<button class="dps-quick-action-btn dps-quick-paid" data-appt-id="' . esc_attr( $appt->ID ) . '" data-action="finish_and_paid" title="' . esc_attr__( 'Finalizar e marcar como pago', 'dps-agenda-addon' ) . '">💰 ' . esc_html__( 'Pago', 'dps-agenda-addon' ) . '</button>';
                echo '<button class="dps-quick-action-btn dps-quick-cancel" data-appt-id="' . esc_attr( $appt->ID ) . '" data-action="cancel" title="' . esc_attr__( 'Cancelar atendimento', 'dps-agenda-addon' ) . '">❌ ' . esc_html__( 'Cancelar', 'dps-agenda-addon' ) . '</button>';
            } elseif ( $status === 'finalizado' ) {
                echo '<button class="dps-quick-action-btn dps-quick-paid" data-appt-id="' . esc_attr( $appt->ID ) . '" data-action="mark_paid" title="' . esc_attr__( 'Marcar como pago', 'dps-agenda-addon' ) . '">💰 ' . esc_html__( 'Marcar pago', 'dps-agenda-addon' ) . '</button>';
            }
            
            echo '</div>';
        }
        
        // Botão de reagendamento rápido
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML seguro retornado pelo helper
        echo $this->render_reschedule_button( $appt->ID, $date, $time );
        
        // Indicador de histórico
        $history = get_post_meta( $appt->ID, '_dps_appointment_history', true );
        if ( is_array( $history ) && ! empty( $history ) ) {
            echo ' <span class="dps-history-indicator" data-appt-id="' . esc_attr( $appt->ID ) . '" title="' . esc_attr__( 'Ver histórico', 'dps-agenda-addon' ) . '">📜 ' . count( $history ) . '</span>';
        }
        
        echo '</td>';
        echo '</tr>';
        
        return ob_get_clean();
    }

    /**
     * Verifica se um atendimento está atrasado.
     * 
     * @since 1.1.0
     * @param string $date Data do agendamento (Y-m-d).
     * @param string $time Hora do agendamento (H:i).
     * @param string $status Status do agendamento.
     * @return bool True se atrasado, false caso contrário.
     */
    private function is_appointment_late( $date, $time, $status ) {
        // Só considera atrasado se ainda estiver pendente ou confirmado
        if ( ! in_array( $status, [ 'pendente', 'confirmado' ], true ) ) {
            return false;
        }
        
        $appointment_timestamp = strtotime( $date . ' ' . $time );
        $current_timestamp = current_time( 'timestamp' );
        
        return $appointment_timestamp < $current_timestamp;
    }

    /**
     * CONF-1: Obtém o status de confirmação de um agendamento.
     * 
     * @since 1.2.0
     * @param int $appointment_id ID do agendamento.
     * @return string Status de confirmação: 'not_sent', 'sent', 'confirmed', 'denied', 'no_answer'.
     */
    private function get_confirmation_status( $appointment_id ) {
        $status = get_post_meta( $appointment_id, 'appointment_confirmation_status', true );
        
        // Default para 'not_sent' se não houver valor
        if ( empty( $status ) ) {
            $status = 'not_sent';
        }
        
        return $status;
    }

    /**
     * CONF-1: Define o status de confirmação de um agendamento.
     * 
     * @since 1.2.0
     * @param int    $appointment_id ID do agendamento.
     * @param string $status Status: 'not_sent', 'sent', 'confirmed', 'denied', 'no_answer'.
     * @param int    $user_id ID do usuário que realizou a ação (opcional).
     * @return bool True se atualizado com sucesso, false caso contrário.
     */
    private function set_confirmation_status( $appointment_id, $status, $user_id = 0 ) {
        // Valida status
        $valid_statuses = [ 'not_sent', 'sent', 'confirmed', 'denied', 'no_answer' ];
        if ( ! in_array( $status, $valid_statuses, true ) ) {
            return false;
        }
        
        // Atualiza status
        update_post_meta( $appointment_id, 'appointment_confirmation_status', $status );
        
        // Atualiza data/hora da última alteração
        update_post_meta( $appointment_id, 'appointment_confirmation_date', current_time( 'mysql' ) );
        
        // Atualiza usuário que realizou a ação
        if ( $user_id > 0 ) {
            update_post_meta( $appointment_id, 'appointment_confirmation_sent_by', $user_id );
        } elseif ( is_user_logged_in() ) {
            update_post_meta( $appointment_id, 'appointment_confirmation_sent_by', get_current_user_id() );
        }
        
        return true;
    }

    /**
     * CONF-3: Renderiza badge de confirmação para a interface.
     * 
     * @since 1.2.0
     * @param string $confirmation_status Status de confirmação.
     * @return string HTML do badge.
     */
    private function render_confirmation_badge( $confirmation_status ) {
        $badges = [
            'not_sent'  => [
                'class' => 'status-confirmation-not-sent',
                'text'  => __( 'Não confirmado', 'dps-agenda-addon' ),
                'icon'  => '⚪',
            ],
            'sent'      => [
                'class' => 'status-confirmation-sent',
                'text'  => __( 'Enviado', 'dps-agenda-addon' ),
                'icon'  => '📤',
            ],
            'confirmed' => [
                'class' => 'status-confirmation-confirmed',
                'text'  => __( 'Confirmado', 'dps-agenda-addon' ),
                'icon'  => '✅',
            ],
            'denied'    => [
                'class' => 'status-confirmation-denied',
                'text'  => __( 'Cancelado', 'dps-agenda-addon' ),
                'icon'  => '❌',
            ],
            'no_answer' => [
                'class' => 'status-confirmation-no-answer',
                'text'  => __( 'Não atendeu', 'dps-agenda-addon' ),
                'icon'  => '⚠️',
            ],
        ];
        
        $badge = $badges[ $confirmation_status ] ?? $badges['not_sent'];
        
        return '<span class="dps-confirmation-badge ' . esc_attr( $badge['class'] ) . '" title="' . esc_attr( $badge['text'] ) . '">' . $badge['icon'] . ' ' . esc_html( $badge['text'] ) . '</span>';
    }

    /**
     * Renderiza a linha de um agendamento para a Aba 1 (Visão Rápida).
     * 
     * Colunas: Checkbox, Horário, Pet (com badge agressivo), Tutor, Serviços (popup), Confirmação (dropdown)
     * 
     * @since 1.4.0
     * @since 1.4.2 Modificado: Serviços com botão popup, Confirmação como dropdown elegante
     * @param WP_Post $appt Agendamento.
     * @param array   $column_labels Labels das colunas.
     * @return string HTML da linha.
     */
    public function render_appointment_row_tab1( $appt, $column_labels ) {
        $date      = get_post_meta( $appt->ID, 'appointment_date', true );
        $time      = get_post_meta( $appt->ID, 'appointment_time', true );
        $client_id = get_post_meta( $appt->ID, 'appointment_client_id', true );
        $pet_id    = get_post_meta( $appt->ID, 'appointment_pet_id', true );
        
        $client_post = $client_id ? get_post( $client_id ) : null;
        $pet_post    = $pet_id ? get_post( $pet_id ) : null;
        
        $status = get_post_meta( $appt->ID, 'appointment_status', true );
        if ( ! $status ) {
            $status = 'pendente';
        }
        
        // Detecta se o atendimento está atrasado
        $is_late = $this->is_appointment_late( $date, $time, $status );
        $row_classes = [ 'status-' . $status ];
        if ( $is_late ) {
            $row_classes[] = 'is-late';
        }
        
        ob_start();
        
        echo '<tr data-appt-id="' . esc_attr( $appt->ID ) . '" class="' . esc_attr( implode( ' ', $row_classes ) ) . '">';
        
        // Horário
        echo '<td data-label="' . esc_attr__( 'Horário', 'dps-agenda-addon' ) . '">' . esc_html( $time ) . '</td>';
        
        // Pet com flag de agressividade e badge
        $pet_name = $pet_post ? $pet_post->post_title : '';
        $aggr_badge = '';
        $restrictions_badge = '';
        if ( $pet_post ) {
            $aggr = get_post_meta( $pet_post->ID, 'pet_aggressive', true );
            if ( $aggr ) {
                $aggr_badge = ' <span class="dps-pet-badge dps-pet-badge--aggressive" title="' . esc_attr__( 'Pet agressivo - cuidado no manejo', 'dps-agenda-addon' ) . '">⚠️ AGRESSIVO</span>';
            }
            $restrictions_badge = $this->get_pet_product_restrictions_badge( $pet_post );
        }
        echo '<td data-label="' . esc_attr__( 'Pet', 'dps-agenda-addon' ) . '">' . esc_html( $pet_name ) . $aggr_badge . $restrictions_badge . '</td>';
        
        // Tutor
        $client_name = $client_post ? $client_post->post_title : '';
        echo '<td data-label="' . esc_attr__( 'Tutor', 'dps-agenda-addon' ) . '">' . esc_html( $client_name ) . '</td>';
        
        // Serviços (botão que abre popup)
        echo '<td data-label="' . esc_attr( $column_labels['service'] ?? __( 'Serviços', 'dps-agenda-addon' ) ) . '">';
        $service_ids = get_post_meta( $appt->ID, 'appointment_services', true );
        if ( is_array( $service_ids ) && ! empty( $service_ids ) ) {
            // Conta quantos serviços
            $service_count = count( $service_ids );
            echo '<button type="button" class="dps-services-popup-btn" data-appt-id="' . esc_attr( $appt->ID ) . '" title="' . esc_attr__( 'Ver serviços e observações', 'dps-agenda-addon' ) . '">';
            echo '📋 ' . sprintf( _n( '%d serviço', '%d serviços', $service_count, 'dps-agenda-addon' ), $service_count );
            echo '</button>';
        } else {
            echo '<span class="dps-no-services">–</span>';
        }
        echo '</td>';
        
        // Confirmação (dropdown elegante)
        echo '<td data-label="' . esc_attr( $column_labels['confirmation'] ?? __( 'Confirmação', 'dps-agenda-addon' ) ) . '">';
        $confirmation_status = $this->get_confirmation_status( $appt->ID );
        
        // Config de status de confirmação com ícones
        $confirmation_config = [
            'not_sent'  => [ 'icon' => '⚪', 'label' => __( 'NÃO CONFIRMADO', 'dps-agenda-addon' ), 'class' => 'not-confirmed' ],
            'confirmed' => [ 'icon' => '✅', 'label' => __( 'CONFIRMADO', 'dps-agenda-addon' ), 'class' => 'confirmed' ],
            'denied'    => [ 'icon' => '❌', 'label' => __( 'CANCELADO', 'dps-agenda-addon' ), 'class' => 'cancelled' ],
            'no_answer' => [ 'icon' => '⚠️', 'label' => __( 'NÃO CONFIRMADO', 'dps-agenda-addon' ), 'class' => 'not-confirmed' ],
            'sent'      => [ 'icon' => '📤', 'label' => __( 'NÃO CONFIRMADO', 'dps-agenda-addon' ), 'class' => 'not-confirmed' ],
        ];
        
        $current_conf = $confirmation_config[ $confirmation_status ] ?? $confirmation_config['not_sent'];
        
        // Status não confirmados são agrupados sob "NÃO CONFIRMADO"
        $is_not_confirmed = in_array( $confirmation_status, [ 'not_sent', 'sent', 'no_answer' ], true );
        
        echo '<div class="dps-confirmation-dropdown-wrapper">';
        echo '<select class="dps-confirmation-dropdown dps-dropdown--' . esc_attr( $current_conf['class'] ) . '" data-appt-id="' . esc_attr( $appt->ID ) . '">';
        echo '<option value="confirmed"' . selected( $confirmation_status, 'confirmed', false ) . '>✅ ' . esc_html__( 'CONFIRMADO', 'dps-agenda-addon' ) . '</option>';
        echo '<option value="not_sent"' . selected( $is_not_confirmed, true, false ) . '>⚪ ' . esc_html__( 'NÃO CONFIRMADO', 'dps-agenda-addon' ) . '</option>';
        echo '<option value="denied"' . selected( $confirmation_status, 'denied', false ) . '>❌ ' . esc_html__( 'CANCELADO', 'dps-agenda-addon' ) . '</option>';
        echo '</select>';
        echo '</div>';
        echo '</td>';
        
        // Coluna de Ações (reagendar)
        echo '<td data-label="' . esc_attr__( 'Ações', 'dps-agenda-addon' ) . '">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML seguro retornado pelo helper
        echo $this->render_reschedule_button( $appt->ID, $date, $time );
        echo '</td>';
        
        echo '</tr>';
        
        return ob_get_clean();
    }

    /**
     * Renderiza a linha de um agendamento para a Aba 2 (Operação).
     * 
     * Colunas: Checkbox, Horário, Pet (com badge agressivo), Tutor, Status (dropdown com ícones), Pagamento (popup)
     * 
     * @since 1.4.0
     * @since 1.4.2 Modificado: Estrutura simplificada com dropdown de status elegante e popup de pagamento
     * @param WP_Post $appt Agendamento.
     * @param array   $column_labels Labels das colunas.
     * @return string HTML da linha.
     */
    public function render_appointment_row_tab2( $appt, $column_labels ) {
        $date      = get_post_meta( $appt->ID, 'appointment_date', true );
        $time      = get_post_meta( $appt->ID, 'appointment_time', true );
        $client_id = get_post_meta( $appt->ID, 'appointment_client_id', true );
        $pet_id    = get_post_meta( $appt->ID, 'appointment_pet_id', true );
        
        $client_post = $client_id ? get_post( $client_id ) : null;
        $pet_post    = $pet_id ? get_post( $pet_id ) : null;
        
        $status = get_post_meta( $appt->ID, 'appointment_status', true );
        if ( ! $status ) {
            $status = 'pendente';
        }
        
        $appt_version = intval( get_post_meta( $appt->ID, '_dps_appointment_version', true ) );
        if ( $appt_version < 1 ) {
            $appt_version = 1;
            update_post_meta( $appt->ID, '_dps_appointment_version', $appt_version );
        }
        
        $is_late = $this->is_appointment_late( $date, $time, $status );
        $row_classes = [ 'status-' . $status ];
        if ( $is_late ) {
            $row_classes[] = 'is-late';
        }
        
        ob_start();
        
        echo '<tr data-appt-id="' . esc_attr( $appt->ID ) . '" class="' . esc_attr( implode( ' ', $row_classes ) ) . '">';
        
        // Horário
        echo '<td data-label="' . esc_attr__( 'Horário', 'dps-agenda-addon' ) . '">' . esc_html( $time ) . '</td>';
        
        // Pet com flag de agressividade e badge
        $pet_name = $pet_post ? $pet_post->post_title : '';
        $aggr_badge = '';
        $restrictions_badge = '';
        if ( $pet_post ) {
            $aggr = get_post_meta( $pet_post->ID, 'pet_aggressive', true );
            if ( $aggr ) {
                $aggr_badge = ' <span class="dps-pet-badge dps-pet-badge--aggressive" title="' . esc_attr__( 'Pet agressivo - cuidado no manejo', 'dps-agenda-addon' ) . '">⚠️ AGRESSIVO</span>';
            }
            $restrictions_badge = $this->get_pet_product_restrictions_badge( $pet_post );
        }
        echo '<td data-label="' . esc_attr__( 'Pet', 'dps-agenda-addon' ) . '">' . esc_html( $pet_name ) . $aggr_badge . $restrictions_badge . '</td>';
        
        // Tutor
        $client_name = $client_post ? $client_post->post_title : '';
        echo '<td data-label="' . esc_attr__( 'Tutor', 'dps-agenda-addon' ) . '">' . esc_html( $client_name ) . '</td>';
        
        // Status do serviço (dropdown elegante com ícones)
        echo '<td data-label="' . esc_attr( $column_labels['status'] ?? __( 'Status do Serviço', 'dps-agenda-addon' ) ) . '">';
        $can_edit = is_user_logged_in() && current_user_can( 'manage_options' );
        
        // Config de status com ícones
        $status_config = [
            'pendente'        => [ 'icon' => '⏳', 'label' => __( 'PENDENTE', 'dps-agenda-addon' ), 'class' => 'pending' ],
            'finalizado'      => [ 'icon' => '✅', 'label' => __( 'FINALIZADO', 'dps-agenda-addon' ), 'class' => 'finished' ],
            'finalizado_pago' => [ 'icon' => '💰', 'label' => __( 'FINALIZADO & PAGO', 'dps-agenda-addon' ), 'class' => 'paid' ],
            'cancelado'       => [ 'icon' => '❌', 'label' => __( 'CANCELADO', 'dps-agenda-addon' ), 'class' => 'cancelled' ],
        ];
        
        // Verifica se é assinatura
        $sub_id_meta = get_post_meta( $appt->ID, 'subscription_id', true );
        $is_subscription = ! empty( $sub_id_meta );
        if ( $is_subscription ) {
            unset( $status_config['finalizado_pago'] );
            if ( $status === 'finalizado_pago' ) {
                $status = 'finalizado';
                update_post_meta( $appt->ID, 'appointment_status', $status );
            }
        }
        
        $current_status = $status_config[ $status ] ?? $status_config['pendente'];
        
        if ( $can_edit ) {
            echo '<div class="dps-status-dropdown-wrapper">';
            echo '<select class="dps-status-dropdown dps-dropdown--' . esc_attr( $current_status['class'] ) . '" data-appt-id="' . esc_attr( $appt->ID ) . '" data-current-status="' . esc_attr( $status ) . '" data-appt-version="' . esc_attr( $appt_version ) . '">';
            foreach ( $status_config as $value => $config ) {
                echo '<option value="' . esc_attr( $value ) . '"' . selected( $status, $value, false ) . '>' . esc_html( $config['icon'] . ' ' . $config['label'] ) . '</option>';
            }
            echo '</select>';
            echo '</div>';
        } else {
            echo '<span class="dps-status-badge dps-status-badge--' . esc_attr( $current_status['class'] ) . '">' . esc_html( $current_status['icon'] . ' ' . $current_status['label'] ) . '</span>';
        }
        echo '</td>';
        
        // Pagamento (botão com popup quando FINALIZADO)
        echo '<td data-label="' . esc_attr( $column_labels['payment'] ?? __( 'Pagamento', 'dps-agenda-addon' ) ) . '">';
        
        if ( $status === 'finalizado' && ! $is_subscription ) {
            // Prepara dados para o popup de pagamento
            $payment_link = get_post_meta( $appt->ID, 'dps_payment_link', true );
            $default_link = 'https://link.mercadopago.com.br/desipetshower';
            $link_to_use = $payment_link ? $payment_link : $default_link;
            
            // Prepara mensagem de WhatsApp
            $total_val = (float) get_post_meta( $appt->ID, 'appointment_total_value', true );
            $client_phone = $client_post ? get_post_meta( $client_post->ID, 'client_phone', true ) : '';
            $valor_fmt = number_format_i18n( $total_val, 2 );
            
            $whatsapp_msg = sprintf(
                __( 'Olá %s! O atendimento do pet %s foi finalizado. Valor: R$ %s. Link para pagamento: %s', 'dps-agenda-addon' ),
                $client_name,
                $pet_name,
                $valor_fmt,
                $link_to_use
            );
            
            echo '<button type="button" class="dps-payment-popup-btn" data-appt-id="' . esc_attr( $appt->ID ) . '" ';
            echo 'data-payment-link="' . esc_attr( $link_to_use ) . '" ';
            echo 'data-client-phone="' . esc_attr( $client_phone ) . '" ';
            echo 'data-whatsapp-msg="' . esc_attr( $whatsapp_msg ) . '" ';
            echo 'data-client-name="' . esc_attr( $client_name ) . '" ';
            echo 'data-pet-name="' . esc_attr( $pet_name ) . '" ';
            echo 'data-total-value="' . esc_attr( $valor_fmt ) . '">';
            echo '💳 ' . esc_html__( 'Enviar Link', 'dps-agenda-addon' );
            echo '</button>';
        } elseif ( $status === 'finalizado_pago' ) {
            echo '<span class="dps-payment-status dps-payment-status--paid">💰 ' . esc_html__( 'Pago', 'dps-agenda-addon' ) . '</span>';
        } elseif ( $status === 'cancelado' ) {
            echo '<span class="dps-payment-status dps-payment-status--cancelled">–</span>';
        } else {
            echo '<span class="dps-payment-status dps-payment-status--pending">⏳ ' . esc_html__( 'Aguardando', 'dps-agenda-addon' ) . '</span>';
        }
        echo '</td>';

        // Coluna Check-in / Check-out (botão claro com estado + indicadores de segurança)
        echo '<td data-label="' . esc_attr__( 'Check-in / Check-out', 'dps-agenda-addon' ) . '">';
        echo '<div class="dps-operational-indicators">';
        $has_checkin  = DPS_Agenda_Checkin_Service::has_checkin( $appt->ID );
        $has_checkout = DPS_Agenda_Checkin_Service::has_checkout( $appt->ID );
        $summary      = DPS_Agenda_Checkin_Service::get_safety_summary( $appt->ID );

        // Determina estado visual do botão
        if ( $has_checkout ) {
            $btn_icon  = '✅';
            $btn_label = __( 'Concluído', 'dps-agenda-addon' );
            $btn_class = 'dps-expand-panels-btn--done';
        } elseif ( $has_checkin ) {
            $btn_icon  = '📥';
            $btn_label = __( 'Fazer Check-out', 'dps-agenda-addon' );
            $btn_class = 'dps-expand-panels-btn--checkin';
        } else {
            $btn_icon  = '🏥';
            $btn_label = __( 'Fazer Check-in', 'dps-agenda-addon' );
            $btn_class = 'dps-expand-panels-btn--pending';
        }

        echo '<button type="button" class="dps-expand-panels-btn ' . esc_attr( $btn_class ) . '" data-appt-id="' . esc_attr( $appt->ID ) . '" title="' . esc_attr__( 'Abrir painel de Check-in / Check-out', 'dps-agenda-addon' ) . '" aria-expanded="false">';
        echo '<span class="dps-expand-panels-btn__icon">' . esc_html( $btn_icon ) . '</span>';
        echo '<span class="dps-expand-panels-btn__label">' . esc_html( $btn_label ) . '</span>';
        echo '<span class="dps-expand-panels-btn__arrow">▼</span>';
        echo '</button>';

        foreach ( $summary as $item ) :
            echo '<span class="dps-safety-tag dps-safety-tag--' . esc_attr( $item['severity'] ) . '" title="' . esc_attr( $item['label'] ) . '">';
            echo esc_html( $item['icon'] );
            echo '</span>';
        endforeach;

        echo '</div>';
        echo '</td>';

        // Coluna de Ações (reagendar)
        echo '<td data-label="' . esc_attr__( 'Ações', 'dps-agenda-addon' ) . '">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML seguro retornado pelo helper
        echo $this->render_reschedule_button( $appt->ID, $date, $time );
        echo '</td>';
        
        echo '</tr>';

        // Linha expansível com painel de Check-in / Check-out
        $total_cols = 7; // Horário + Pet + Tutor + Status + Pagamento + Check-in/Check-out + Ações
        echo '<tr class="dps-detail-row" data-appt-id="' . esc_attr( $appt->ID ) . '" style="display: none;">';
        echo '<td colspan="' . esc_attr( $total_cols ) . '">';
        echo '<div class="dps-detail-panels">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML seguro retornado por render_checkin_panel
        echo DPS_Agenda_Addon::render_checkin_panel( $appt->ID );
        echo '</div>';
        echo '</td>';
        echo '</tr>';
        
        return ob_get_clean();
    }

    /**
     * Renderiza a linha de um agendamento para a Aba 3 (Detalhes).
     * 
     * Colunas: Checkbox, Horário, Pet (com badge agressivo), Tutor, TaxiDog (com dropdown condicional)
     * 
     * @since 1.4.0
     * @since 1.4.2 Modificado: Estrutura simplificada com TaxiDog condicional
     * @param WP_Post $appt Agendamento.
     * @param array   $column_labels Labels das colunas.
     * @return string HTML da linha.
     */
    public function render_appointment_row_tab3( $appt, $column_labels ) {
        $date      = get_post_meta( $appt->ID, 'appointment_date', true );
        $time      = get_post_meta( $appt->ID, 'appointment_time', true );
        $client_id = get_post_meta( $appt->ID, 'appointment_client_id', true );
        $pet_id    = get_post_meta( $appt->ID, 'appointment_pet_id', true );
        
        $client_post = $client_id ? get_post( $client_id ) : null;
        $pet_post    = $pet_id ? get_post( $pet_id ) : null;
        
        $status = get_post_meta( $appt->ID, 'appointment_status', true );
        if ( ! $status ) {
            $status = 'pendente';
        }
        
        $is_late = $this->is_appointment_late( $date, $time, $status );
        $row_classes = [ 'status-' . $status ];
        if ( $is_late ) {
            $row_classes[] = 'is-late';
        }
        
        ob_start();
        
        echo '<tr data-appt-id="' . esc_attr( $appt->ID ) . '" class="' . esc_attr( implode( ' ', $row_classes ) ) . '">';
        
        // Horário
        echo '<td data-label="' . esc_attr__( 'Horário', 'dps-agenda-addon' ) . '">' . esc_html( $time ) . '</td>';
        
        // Pet com flag de agressividade e badge
        $pet_name = $pet_post ? $pet_post->post_title : '';
        $aggr_badge = '';
        $restrictions_badge = '';
        if ( $pet_post ) {
            $aggr = get_post_meta( $pet_post->ID, 'pet_aggressive', true );
            if ( $aggr ) {
                $aggr_badge = ' <span class="dps-pet-badge dps-pet-badge--aggressive" title="' . esc_attr__( 'Pet agressivo - cuidado no manejo', 'dps-agenda-addon' ) . '">⚠️ AGRESSIVO</span>';
            }
            $restrictions_badge = $this->get_pet_product_restrictions_badge( $pet_post );
        }
        echo '<td data-label="' . esc_attr__( 'Pet', 'dps-agenda-addon' ) . '">' . esc_html( $pet_name ) . $aggr_badge . $restrictions_badge . '</td>';
        
        // Tutor
        $client_name = $client_post ? $client_post->post_title : '';
        echo '<td data-label="' . esc_attr__( 'Tutor', 'dps-agenda-addon' ) . '">' . esc_html( $client_name ) . '</td>';
        
        // TaxiDog (exibe informação simples com botão para Google Maps quando solicitado)
        echo '<td data-label="TaxiDog">';
        
        // Verifica se TaxiDog foi solicitado no agendamento
        $taxidog_requested = get_post_meta( $appt->ID, 'appointment_taxidog', true );
        
        if ( $taxidog_requested ) {
            // TaxiDog foi solicitado - exibe label e botão do Google Maps
            echo '<div class="dps-taxidog-wrapper">';
            
            // Obtém endereço do cliente para o Google Maps
            $client_address = DPS_Agenda_GPS_Helper::get_client_address( $appt->ID );
            
            if ( ! empty( $client_address ) ) {
                // URL de busca no Google Maps (abre direto no endereço)
                $map_url = 'https://www.google.com/maps/search/?api=1&query=' . urlencode( $client_address );
                echo '<a href="' . esc_url( $map_url ) . '" target="_blank" class="dps-taxidog-map-btn" title="' . esc_attr__( 'Abrir endereço no Google Maps', 'dps-agenda-addon' ) . '">';
                echo '🚐 ' . esc_html__( 'TAXIDOG SOLICITADO', 'dps-agenda-addon' ) . ' 📍';
                echo '</a>';
            } else {
                // Sem endereço cadastrado - mostra apenas o label
                echo '<span class="dps-taxidog-label dps-taxidog-label--requested">🚐 ' . esc_html__( 'TAXIDOG SOLICITADO', 'dps-agenda-addon' ) . '</span>';
            }
            echo '</div>';
        } else {
            // TaxiDog NÃO foi solicitado
            echo '<div class="dps-taxidog-wrapper">';
            echo '<span class="dps-taxidog-label dps-taxidog-label--not-requested">⚪ ' . esc_html__( 'NÃO SOLICITADO', 'dps-agenda-addon' ) . '</span>';
            echo '</div>';
        }
        echo '</td>';
        
        // Coluna de Observações (notas do agendamento)
        echo '<td data-label="' . esc_attr__( 'Observações', 'dps-agenda-addon' ) . '">';
        $appointment_notes = get_post_meta( $appt->ID, 'appointment_notes', true );
        if ( ! empty( $appointment_notes ) ) {
            // Trunca observações longas com tooltip para o texto completo
            $word_count = str_word_count( $appointment_notes );
            $max_words = 8;
            $has_more = $word_count > $max_words;
            
            if ( $has_more ) {
                $notes_preview = wp_trim_words( $appointment_notes, $max_words, '...' );
                echo '<span class="dps-notes-preview" title="' . esc_attr( $appointment_notes ) . '">';
                echo '📝 ' . esc_html( $notes_preview );
                echo '</span>';
            } else {
                echo '<span class="dps-notes-text">📝 ' . esc_html( $appointment_notes ) . '</span>';
            }
        } else {
            echo '<span class="dps-notes-empty">—</span>';
        }
        echo '</td>';
        
        // Coluna Operacional (Checklist + Check-in/Check-out)
        echo '<td data-label="' . esc_attr__( 'Operacional', 'dps-agenda-addon' ) . '">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML seguro retornado pelo render helper
        echo DPS_Agenda_Addon::render_checkin_checklist_summary( $appt->ID );
        echo '</td>';
        
        // Coluna de Ações (reagendar)
        echo '<td data-label="' . esc_attr__( 'Ações', 'dps-agenda-addon' ) . '">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML seguro retornado pelo helper
        echo $this->render_reschedule_button( $appt->ID, $date, $time );
        echo '</td>';
        
        echo '</tr>';
        
        return ob_get_clean();
    }
}
