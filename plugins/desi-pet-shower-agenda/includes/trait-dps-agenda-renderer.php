<?php
/**
 * Trait com mÃ©todos de renderizaÃ§Ã£o para a Agenda.
 *
 * Este trait contÃ©m mÃ©todos auxiliares extraÃ­dos do mÃ©todo principal
 * render_agenda_shortcode() para melhorar a manutenibilidade do cÃ³digo.
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
 * MÃ©todos de renderizaÃ§Ã£o extraÃ­dos da classe principal.
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
        return '<p>' . esc_html__( 'VocÃª precisa estar logado como administrador para acessar a agenda.', 'dps-agenda-addon' ) . ' <a href="' . esc_url( $login_url ) . '">' . esc_html__( 'Fazer login', 'dps-agenda-addon' ) . '</a></p>';
    }

    /**
     * Renderiza o botÃ£o de reagendamento.
     *
     * MÃ©todo helper para evitar duplicaÃ§Ã£o de cÃ³digo nas diferentes abas.
     *
     * @since 1.1.0
     * @param int    $appt_id ID do agendamento.
     * @param string $date    Data do agendamento (Y-m-d).
     * @param string $time    Hora do agendamento (H:i).
     * @return string HTML do botÃ£o de reagendamento.
     */
    private function render_reschedule_button( $appt_id, $date, $time ) {
        return '<a href="#" class="dps-quick-action dps-quick-reschedule" data-appt-id="' . esc_attr( $appt_id ) . '" data-date="' . esc_attr( $date ) . '" data-time="' . esc_attr( $time ) . '" title="' . esc_attr__( 'Reagendar', 'dps-agenda-addon' ) . '">ðŸ“… ' . esc_html__( 'Reagendar', 'dps-agenda-addon' ) . '</a>';
    }

    /**
     * Verifica se o pet possui restriÃ§Ãµes de produtos e retorna badge HTML.
     *
     * @since 1.5.0
     * @param int|WP_Post $pet Pet ID ou objeto.
     * @return string HTML do badge de restriÃ§Ã£o ou string vazia.
     */
    private function get_pet_product_restrictions_badge( $pet ) {
        $pet_id = is_object( $pet ) ? $pet->ID : absint( $pet );
        if ( ! $pet_id ) {
            return '';
        }

        $has_restrictions = false;
        $restrictions_items = [];

        // Verifica preferÃªncia de shampoo especial
        $shampoo = get_post_meta( $pet_id, 'pet_shampoo_pref', true );
        if ( $shampoo && '' !== $shampoo ) {
            $has_restrictions = true;
            $shampoo_labels = [
                'hipoalergenico' => __( 'Shampoo hipoalergÃªnico', 'dps-agenda-addon' ),
                'antisseptico'   => __( 'Shampoo antissÃ©ptico', 'dps-agenda-addon' ),
                'pelagem_branca' => __( 'Shampoo p/ pelagem branca', 'dps-agenda-addon' ),
                'pelagem_escura' => __( 'Shampoo p/ pelagem escura', 'dps-agenda-addon' ),
                'antipulgas'     => __( 'Shampoo antipulgas', 'dps-agenda-addon' ),
                'hidratante'     => __( 'Shampoo hidratante', 'dps-agenda-addon' ),
                'outro'          => __( 'Shampoo especial', 'dps-agenda-addon' ),
            ];
            $restrictions_items[] = isset( $shampoo_labels[ $shampoo ] ) ? $shampoo_labels[ $shampoo ] : $shampoo;
        }

        // Verifica proibiÃ§Ã£o de perfume
        $perfume = get_post_meta( $pet_id, 'pet_perfume_pref', true );
        if ( 'sem_perfume' === $perfume ) {
            $has_restrictions = true;
            $restrictions_items[] = __( 'âŒ SEM PERFUME', 'dps-agenda-addon' );
        } elseif ( 'hipoalergenico' === $perfume ) {
            $has_restrictions = true;
            $restrictions_items[] = __( 'Perfume hipoalergÃªnico', 'dps-agenda-addon' );
        }

        // Verifica preferÃªncia de adereÃ§os
        $accessories = get_post_meta( $pet_id, 'pet_accessories_pref', true );
        if ( 'sem_aderecos' === $accessories ) {
            $has_restrictions = true;
            $restrictions_items[] = __( 'Sem adereÃ§os', 'dps-agenda-addon' );
        } elseif ( $accessories && '' !== $accessories ) {
            $accessories_labels = [
                'lacinho' => __( 'Usar lacinho', 'dps-agenda-addon' ),
                'gravata' => __( 'Usar gravata', 'dps-agenda-addon' ),
                'lenco'   => __( 'Usar lenÃ§o', 'dps-agenda-addon' ),
                'bandana' => __( 'Usar bandana', 'dps-agenda-addon' ),
            ];
            if ( isset( $accessories_labels[ $accessories ] ) ) {
                $restrictions_items[] = $accessories_labels[ $accessories ];
            }
        }

        // Verifica outras restriÃ§Ãµes
        $other = get_post_meta( $pet_id, 'pet_product_restrictions', true );
        if ( $other && '' !== trim( $other ) ) {
            $has_restrictions = true;
            $restrictions_items[] = esc_html( $other );
        }

        if ( ! $has_restrictions ) {
            return '';
        }

        $tooltip = implode( ' | ', $restrictions_items );
        return ' <span class="dps-pet-badge dps-pet-badge--restrictions" title="' . esc_attr( $tooltip ) . '">ðŸ§´</span>';
    }

    /**
     * ObtÃ©m labels das colunas da tabela.
     *
     * @since 1.3.0
     * @return array Labels das colunas.
     */
    private function get_column_labels() {
        return [
            'date'         => __( 'Data', 'dps-agenda-addon' ),
            'time'         => __( 'Hora', 'dps-agenda-addon' ),
            'pet'          => __( 'Pet e tutor', 'dps-agenda-addon' ),
            'service'      => __( 'ServiÃ§o', 'dps-agenda-addon' ),
            'status'       => __( 'Status', 'dps-agenda-addon' ),
            'payment'      => __( 'Pagamento', 'dps-agenda-addon' ),
            'map'          => __( 'Mapa', 'dps-agenda-addon' ),
            'confirmation' => __( 'ConfirmaÃ§Ã£o', 'dps-agenda-addon' ),
            'charge'       => __( 'CobranÃ§a', 'dps-agenda-addon' ),
        ];
    }

    /**
     * Calcula datas de navegaÃ§Ã£o (anterior/prÃ³ximo).
     *
     * @since 1.3.0
     * @param string $selected_date Data selecionada.
     * @param bool   $is_week_view  Se Ã© visualizaÃ§Ã£o semanal.
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
     * Build a short scope label for the current agenda view.
     *
     * @param string $selected_date Selected date.
     * @param string $view Current view.
     * @param bool   $show_all Whether the full agenda is active.
     * @return string
     */
    private function get_agenda_scope_label( $selected_date, $view, $show_all ) {
        if ( $show_all ) {
            return __( 'Todos os atendimentos futuros', 'dps-agenda-addon' );
        }

        $timestamp = strtotime( $selected_date );
        if ( ! $timestamp ) {
            return __( 'Visao diaria', 'dps-agenda-addon' );
        }

        if ( 'week' === $view ) {
            $date = DateTime::createFromFormat( 'Y-m-d', $selected_date );
            if ( ! $date ) {
                return __( 'Visao semanal', 'dps-agenda-addon' );
            }

            $start = clone $date;
            $end   = clone $date;
            $start->modify( 'monday this week' );
            $end->modify( 'sunday this week' );

            return sprintf(
                __( 'Semana de %1$s a %2$s', 'dps-agenda-addon' ),
                date_i18n( 'd/m', $start->getTimestamp() ),
                date_i18n( 'd/m', $end->getTimestamp() )
            );
        }

        if ( 'calendar' === $view ) {
            return date_i18n( 'F Y', $timestamp );
        }

        return sprintf( __( 'Dia %s', 'dps-agenda-addon' ), date_i18n( 'd/m/Y', $timestamp ) );
    }
    /**
     * Summarize the filtered appointment set for the agenda overview.
     *
     * @param array $appointments Filtered appointments.
     * @return array<string,int>
     */
    private function get_agenda_overview_stats( $appointments ) {
        $stats = [
            'total'           => 0,
            'pending'         => 0,
            'completed'       => 0,
            'canceled'        => 0,
            'late'            => 0,
            'pending_payment' => 0,
            'taxidog'         => 0,
        ];

        if ( empty( $appointments ) ) {
            return $stats;
        }

        foreach ( $appointments as $appointment ) {
            $stats['total']++;

            $status = get_post_meta( $appointment->ID, 'appointment_status', true );
            $status = $status ? $status : 'pendente';

            if ( 'pendente' === $status ) {
                $stats['pending']++;
            } elseif ( 'cancelado' === $status ) {
                $stats['canceled']++;
            } else {
                $stats['completed']++;
            }

            $date = get_post_meta( $appointment->ID, 'appointment_date', true );
            $time = get_post_meta( $appointment->ID, 'appointment_time', true );
            if ( $this->is_appointment_late( $date, $time, $status ) ) {
                $stats['late']++;
            }

            if ( class_exists( 'DPS_Agenda_Payment_Helper' ) && DPS_Agenda_Payment_Helper::has_pending_payment( $appointment->ID ) ) {
                $stats['pending_payment']++;
            }

            $taxidog = get_post_meta( $appointment->ID, 'appointment_taxidog', true );
            $taxidog_status = get_post_meta( $appointment->ID, 'appointment_taxidog_status', true );
            if ( '1' === $taxidog || ! empty( $taxidog_status ) ) {
                $stats['taxidog']++;
            }
        }

        return $stats;
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
     * FunÃ§Ã£o reutilizÃ¡vel para renderizar o HTML de uma linha de atendimento,
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

        // Detecta se o atendimento estÃ¡ atrasado (UX-3)
        $is_late = $this->is_appointment_late( $date, $time, $status );
        $row_classes = [ 'status-' . $status ];
        if ( $is_late ) {
            $row_classes[] = 'is-late';
        }

        ob_start();

        // Cada linha recebe classes de status e um data attribute para permitir manipulaÃ§Ã£o via JS.
        echo '<tr data-appt-id="' . esc_attr( $appt->ID ) . '" class="' . esc_attr( implode( ' ', $row_classes ) ) . '">';

        // Mostra a data no formato dia-mÃªs-ano
        echo '<td data-label="' . esc_attr( ! empty( $column_labels['date'] ) ? __( 'Data', 'dps-agenda-addon' ) : '' ) . '">' . esc_html( date_i18n( 'd-m-Y', strtotime( $date ) ) ) . '</td>';
        echo '<td data-label="' . esc_attr( ! empty( $column_labels['time'] ) ? __( 'Hora', 'dps-agenda-addon' ) : '' ) . '">' . esc_html( $time ) . '</td>';

        // Nome do pet com flags operacionais e consentimento quando aplicÃ¡vel.
        $pet_name    = $pet_post ? $pet_post->post_title : '';
        $aggr_flag   = '';
        $restrictions_badge = '';
        $consent_badge = '';
        $group_data = null;
        if ( $pet_post ) {
            $aggr = get_post_meta( $pet_post->ID, 'pet_aggressive', true );
            if ( $aggr ) {
                $aggr_flag = ' <span class="dps-pet-badge dps-pet-badge--aggressive" title="' . esc_attr__( 'Pet agressivo - cuidado no manejo', 'dps-agenda-addon' ) . '">' . esc_html__( 'Agressivo', 'dps-agenda-addon' ) . '</span>';
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
        echo '<td data-label="' . esc_attr( ! empty( $column_labels['pet'] ) ? __( 'Pet (Cliente)', 'dps-agenda-addon' ) : '' ) . '">' . esc_html( $pet_name ) . $aggr_flag . $restrictions_badge . $consent_badge . '</td>';

        // ServiÃ§os e assinatura
        echo '<td data-label="' . esc_attr( ! empty( $column_labels['service'] ) ? __( 'ServiÃ§o', 'dps-agenda-addon' ) : '' ) . '">';
        $sub_id_meta = get_post_meta( $appt->ID, 'subscription_id', true );
        if ( $sub_id_meta ) {
            echo '<span class="dps-subscription-flag" style="font-weight:bold; color:#0073aa;">' . esc_html__( 'Assinatura', 'dps-agenda-addon' ) . '</span> ';
        }
        $service_ids = get_post_meta( $appt->ID, 'appointment_services', true );
        if ( is_array( $service_ids ) && ! empty( $service_ids ) ) {
            // Link com Ã­cone para abrir modal de serviÃ§os (FASE 2)
            echo '<a href="#" class="dps-services-link" data-appt-id="' . esc_attr( $appt->ID ) . '" title="' . esc_attr__( 'Ver detalhes dos serviÃ§os', 'dps-agenda-addon' ) . '">';
            echo esc_html__( 'Ver serviÃ§os', 'dps-agenda-addon' ) . ' â†—';
            echo '</a>';
        } else {
            echo '-';
        }
        echo '</td>';

        // Status (editable if admin)
        echo '<td data-label="' . esc_attr( ! empty( $column_labels['status'] ) ? __( 'Status', 'dps-agenda-addon' ) : '' ) . '">';
        $can_edit = is_user_logged_in() && current_user_can( 'manage_options' );

        // Define lista de status padrÃ£o
        $statuses = [
            'pendente'        => __( 'Pendente', 'dps-agenda-addon' ),
            'finalizado'      => __( 'Finalizado', 'dps-agenda-addon' ),
            'finalizado_pago' => __( 'Finalizado e pago', 'dps-agenda-addon' ),
            'cancelado'       => __( 'Cancelado', 'dps-agenda-addon' ),
        ];

        // Para agendamentos de assinatura, nÃ£o hÃ¡ necessidade de usar o status "finalizado e pago"
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
            echo esc_html( isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status );
        }
        echo '</td>';

        // FASE 3: Coluna de Pagamento
        echo '<td data-label="' . esc_attr( ! empty( $column_labels['payment'] ) ? __( 'Pagamento', 'dps-agenda-addon' ) : '' ) . '">';
        echo DPS_Agenda_Payment_Helper::render_payment_badge( $appt->ID );
        echo DPS_Agenda_Payment_Helper::render_payment_tooltip( $appt->ID );
        echo DPS_Agenda_Payment_Helper::render_resend_button( $appt->ID );
        echo '</td>';

        // FASE 3: Mapa + TaxiDog + GPS
        echo '<td data-label="' . esc_attr( ! empty( $column_labels['map'] ) ? __( 'Mapa', 'dps-agenda-addon' ) : '' ) . '">';

        // Renderiza badge de TaxiDog se aplicÃ¡vel
        $taxidog_badge = DPS_Agenda_TaxiDog_Helper::render_taxidog_badge( $appt->ID );
        if ( ! empty( $taxidog_badge ) ) {
            echo $taxidog_badge;
            echo '<br>';
        }

        // Link simples de mapa (mantÃ©m funcionalidade existente)
        $map_link = DPS_Agenda_GPS_Helper::render_map_link( $appt->ID );
        if ( ! empty( $map_link ) ) {
            echo $map_link;
        }

        // BotÃ£o "Abrir rota" (sempre Loja â†’ Cliente)
        $route_button = DPS_Agenda_GPS_Helper::render_route_button( $appt->ID );
        if ( ! empty( $route_button ) ) {
            echo '<br>';
            echo $route_button;
        }

        // AÃ§Ãµes rÃ¡pidas de TaxiDog
        $taxidog_actions = DPS_Agenda_TaxiDog_Helper::render_taxidog_quick_actions( $appt->ID );
        if ( ! empty( $taxidog_actions ) ) {
            echo $taxidog_actions;
        }

        // Se nÃ£o tem nada para mostrar
        if ( empty( $taxidog_badge ) && empty( $map_link ) && empty( $route_button ) ) {
            echo '-';
        }

        echo '</td>';

        // CONF-2/CONF-3: ConfirmaÃ§Ã£o de atendimento (badge + botÃµes)
        echo '<td data-label="' . esc_attr( ! empty( $column_labels['confirmation'] ) ? __( 'ConfirmaÃ§Ã£o', 'dps-agenda-addon' ) : '' ) . '">';

        // ObtÃ©m status de confirmaÃ§Ã£o
        $confirmation_status = $this->get_confirmation_status( $appt->ID );

        // Renderiza badge de confirmaÃ§Ã£o
        echo '<div class="dps-confirmation-wrapper">';
        echo $this->render_confirmation_badge( $confirmation_status );

        // CONF-2: BotÃµes de confirmaÃ§Ã£o (apenas para admins)
        if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
            echo '<div class="dps-confirmation-actions">';

            // BotÃ£o "Confirmado"
            echo '<button class="dps-confirmation-btn dps-confirmation-btn--confirmed" data-appt-id="' . esc_attr( $appt->ID ) . '" data-action="confirmed" title="' . esc_attr__( 'Marcar como confirmado', 'dps-agenda-addon' ) . '">âœ…</button>';

            // BotÃ£o "NÃ£o atendeu"
            echo '<button class="dps-confirmation-btn dps-confirmation-btn--no-answer" data-appt-id="' . esc_attr( $appt->ID ) . '" data-action="no_answer" title="' . esc_attr__( 'NÃ£o atendeu', 'dps-agenda-addon' ) . '">âš ï¸</button>';

            // BotÃ£o "Cancelado/Desmarcou"
            echo '<button class="dps-confirmation-btn dps-confirmation-btn--denied" data-appt-id="' . esc_attr( $appt->ID ) . '" data-action="denied" title="' . esc_attr__( 'Cliente cancelou', 'dps-agenda-addon' ) . '">âŒ</button>';

            // BotÃ£o "Limpar" (reset para not_sent)
            if ( $confirmation_status !== 'not_sent' ) {
                echo '<button class="dps-confirmation-btn dps-confirmation-btn--clear" data-appt-id="' . esc_attr( $appt->ID ) . '" data-action="not_sent" title="' . esc_attr__( 'Limpar status', 'dps-agenda-addon' ) . '">ðŸ”„</button>';
            }

            echo '</div>';
        }

        // Link para WhatsApp (mantÃ©m funcionalidade existente)
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
                    'OlÃ¡ %s, tudo bem? Poderia confirmar o atendimento do(s) pet(s) %s agendado para %s Ã s %s%s? Caso precise reagendar Ã© sÃ³ responder esta mensagem. Obrigado!',
                    $client_name,
                    implode( ', ', $pet_names ),
                    $date_fmt,
                    $time,
                    $services_txt
                );
                $message = apply_filters( 'dps_agenda_confirmation_message', $message, $appt );
                // Link de confirmaÃ§Ã£o com Ã­cone e tooltip usando helper centralizado
                if ( class_exists( 'DPS_WhatsApp_Helper' ) ) {
                    $wa_url = DPS_WhatsApp_Helper::get_link_to_client( $whatsapp, $message );
                } else {
                    // Fallback
                    $wa_url = 'https://wa.me/' . $whatsapp . '?text=' . rawurlencode( $message );
                }
                echo '<div class="dps-confirmation-whatsapp">';
                echo '<a href="' . esc_url( $wa_url ) . '" target="_blank" class="dps-whatsapp-link" title="' . esc_attr__( 'Enviar mensagem de confirmaÃ§Ã£o via WhatsApp', 'dps-agenda-addon' ) . '">ðŸ’¬ ' . esc_html__( 'Enviar WhatsApp', 'dps-agenda-addon' ) . '</a>';
                echo '</div>';
            }
        }

        echo '</div>';
        echo '</td>';

        // CobranÃ§a via WhatsApp
        echo '<td data-label="' . esc_attr( ! empty( $column_labels['charge'] ) ? __( 'CobranÃ§a', 'dps-agenda-addon' ) : '' ) . '">';
        // Mostra link de cobranÃ§a apenas para atendimentos finalizados (nÃ£o assinaturas)
        $sub_meta = get_post_meta( $appt->ID, 'subscription_id', true );
        if ( $status === 'finalizado' && empty( $sub_meta ) ) {
            // Verifica se houve erro ao gerar link de pagamento
            $payment_link_status = get_post_meta( $appt->ID, '_dps_payment_link_status', true );

            if ( $payment_link_status === 'error' ) {
                // Exibe aviso de erro com tooltip
                echo '<span style="color: #ef4444; font-size: 14px;" title="' . esc_attr__( 'Houve erro ao gerar o link de pagamento. Tente novamente ou verifique o log.', 'dps-agenda-addon' ) . '">âš ï¸ ' . esc_html__( 'Erro ao gerar link', 'dps-agenda-addon' ) . '</span>';

                // Mostra detalhes do erro se disponÃ­veis (somente para admins)
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
                // Comportamento normal: exibe links de cobranÃ§a
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
                    $msg          = sprintf( 'OlÃ¡ %s, tudo bem? O serviÃ§o do pet %s foi finalizado e o pagamento de R$ %s ainda estÃ¡ pendente. Para sua comodidade, vocÃª pode pagar via PIX celular 15 99160â€‘6299 ou utilizar o link: %s. Obrigado pela confianÃ§a!', $client_name, implode( ', ', array_filter( $pet_names ) ), $valor_fmt, $link_to_use );
                    $msg          = apply_filters( 'dps_agenda_whatsapp_message', $msg, $appt, 'agenda' );
                    $links        = [];
                    // Link de cobranÃ§a com Ã­cone e tooltip usando helper centralizado
                    if ( class_exists( 'DPS_WhatsApp_Helper' ) ) {
                        $wa_url = DPS_WhatsApp_Helper::get_link_to_client( $client_phone, $msg );
                    } else {
                        // Fallback
                        $wa_url = 'https://wa.me/' . $digits . '?text=' . rawurlencode( $msg );
                    }
                    $links[]      = '<a href="' . esc_url( $wa_url ) . '" target="_blank" title="' . esc_attr__( 'Enviar cobranÃ§a via WhatsApp', 'dps-agenda-addon' ) . '">ðŸ’° ' . esc_html__( 'Cobrar', 'dps-agenda-addon' ) . '</a>';
                    if ( ! empty( $group_data['ids'] ) && (int) $appt->ID === (int) min( $group_data['ids'] ) ) {
                        $group_total = number_format_i18n( $group_data['total'], 2 );
                        $date_fmt    = $group_data['date'] ? date_i18n( 'd/m/Y', strtotime( $group_data['date'] ) ) : '';
                        $group_msg   = sprintf( 'OlÃ¡ %s, tudo bem? Finalizamos os atendimentos dos pets %s em %s Ã s %s. O valor total ficou em R$ %s. VocÃª pode pagar via PIX celular 15 99160â€‘6299 ou utilizar o link: %s. Caso tenha dÃºvidas estamos Ã  disposiÃ§Ã£o!', $client_name, implode( ', ', $group_data['pet_names'] ), $date_fmt, $group_data['time'], $group_total, $link_to_use );
                        $group_msg   = apply_filters( 'dps_agenda_whatsapp_group_message', $group_msg, $appt, $group_data );
                        // Link de cobranÃ§a conjunta com Ã­cone usando helper centralizado
                        if ( class_exists( 'DPS_WhatsApp_Helper' ) ) {
                            $wa_url_group = DPS_WhatsApp_Helper::get_link_to_client( $client_phone, $group_msg );
                        } else {
                            // Fallback
                            $wa_url_group = 'https://wa.me/' . $digits . '?text=' . rawurlencode( $group_msg );
                        }
                        $links[]     = '<a href="' . esc_url( $wa_url_group ) . '" target="_blank" title="' . esc_attr__( 'Enviar cobranÃ§a conjunta via WhatsApp', 'dps-agenda-addon' ) . '">ðŸ’°ðŸ’° ' . esc_html__( 'CobranÃ§a conjunta', 'dps-agenda-addon' ) . '</a>';
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

        // FASE 5: Coluna de aÃ§Ãµes (reagendar, histÃ³rico, aÃ§Ãµes rÃ¡pidas)
        echo '<td data-label="' . esc_attr__( 'AÃ§Ãµes', 'dps-agenda-addon' ) . '">';

        // UX-1: BotÃµes de aÃ§Ã£o rÃ¡pida de status
        if ( $can_edit && ! $is_subscription ) {
            echo '<div class="dps-quick-actions">';

            // Mostrar botÃµes diferentes dependendo do status atual
            if ( $status === 'pendente' ) {
                echo '<button class="dps-quick-action-btn dps-quick-finish" data-appt-id="' . esc_attr( $appt->ID ) . '" data-action="finish" title="' . esc_attr__( 'Finalizar atendimento', 'dps-agenda-addon' ) . '">âœ… ' . esc_html__( 'Finalizar', 'dps-agenda-addon' ) . '</button>';
                echo '<button class="dps-quick-action-btn dps-quick-paid" data-appt-id="' . esc_attr( $appt->ID ) . '" data-action="finish_and_paid" title="' . esc_attr__( 'Finalizar e marcar como pago', 'dps-agenda-addon' ) . '">ðŸ’° ' . esc_html__( 'Pago', 'dps-agenda-addon' ) . '</button>';
                echo '<button class="dps-quick-action-btn dps-quick-cancel" data-appt-id="' . esc_attr( $appt->ID ) . '" data-action="cancel" title="' . esc_attr__( 'Cancelar atendimento', 'dps-agenda-addon' ) . '">âŒ ' . esc_html__( 'Cancelar', 'dps-agenda-addon' ) . '</button>';
            } elseif ( $status === 'finalizado' ) {
                echo '<button class="dps-quick-action-btn dps-quick-paid" data-appt-id="' . esc_attr( $appt->ID ) . '" data-action="mark_paid" title="' . esc_attr__( 'Marcar como pago', 'dps-agenda-addon' ) . '">ðŸ’° ' . esc_html__( 'Marcar pago', 'dps-agenda-addon' ) . '</button>';
            }

            echo '</div>';
        }

        // BotÃ£o de reagendamento rÃ¡pido
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML seguro retornado pelo helper
        echo $this->render_reschedule_button( $appt->ID, $date, $time );

        // Indicador de histÃ³rico
        $history = get_post_meta( $appt->ID, '_dps_appointment_history', true );
        if ( is_array( $history ) && ! empty( $history ) ) {
            echo ' <span class="dps-history-indicator" data-appt-id="' . esc_attr( $appt->ID ) . '" title="' . esc_attr__( 'Ver histÃ³rico', 'dps-agenda-addon' ) . '">ðŸ“œ ' . count( $history ) . '</span>';
        }

        echo '</td>';
        echo '</tr>';

        return ob_get_clean();
    }

    /**
     * Verifica se um atendimento estÃ¡ atrasado.
     *
     * @since 1.1.0
     * @param string $date Data do agendamento (Y-m-d).
     * @param string $time Hora do agendamento (H:i).
     * @param string $status Status do agendamento.
     * @return bool True se atrasado, false caso contrÃ¡rio.
     */
    private function is_appointment_late( $date, $time, $status ) {
        // SÃ³ considera atrasado se ainda estiver pendente ou confirmado
        if ( ! in_array( $status, [ 'pendente', 'confirmado' ], true ) ) {
            return false;
        }

        $appointment_timestamp = strtotime( $date . ' ' . $time );
        $current_timestamp = current_time( 'timestamp' );

        return $appointment_timestamp < $current_timestamp;
    }

    /**
     * CONF-1: ObtÃ©m o status de confirmaÃ§Ã£o de um agendamento.
     *
     * @since 1.2.0
     * @param int $appointment_id ID do agendamento.
     * @return string Status de confirmaÃ§Ã£o: 'not_sent', 'sent', 'confirmed', 'denied', 'no_answer'.
     */
    private function get_confirmation_status( $appointment_id ) {
        $status = get_post_meta( $appointment_id, 'appointment_confirmation_status', true );

        // Default para 'not_sent' se nÃ£o houver valor
        if ( empty( $status ) ) {
            $status = 'not_sent';
        }

        return $status;
    }

    /**
     * CONF-1: Define o status de confirmaÃ§Ã£o de um agendamento.
     *
     * @since 1.2.0
     * @param int    $appointment_id ID do agendamento.
     * @param string $status Status: 'not_sent', 'sent', 'confirmed', 'denied', 'no_answer'.
     * @param int    $user_id ID do usuÃ¡rio que realizou a aÃ§Ã£o (opcional).
     * @return bool True se atualizado com sucesso, false caso contrÃ¡rio.
     */
    private function set_confirmation_status( $appointment_id, $status, $user_id = 0 ) {
        // Valida status
        $valid_statuses = [ 'not_sent', 'sent', 'confirmed', 'denied', 'no_answer' ];
        if ( ! in_array( $status, $valid_statuses, true ) ) {
            return false;
        }

        // Atualiza status
        update_post_meta( $appointment_id, 'appointment_confirmation_status', $status );

        // Atualiza data/hora da Ãºltima alteraÃ§Ã£o
        update_post_meta( $appointment_id, 'appointment_confirmation_date', current_time( 'mysql' ) );

        // Atualiza usuÃ¡rio que realizou a aÃ§Ã£o
        if ( $user_id > 0 ) {
            update_post_meta( $appointment_id, 'appointment_confirmation_sent_by', $user_id );
        } elseif ( is_user_logged_in() ) {
            update_post_meta( $appointment_id, 'appointment_confirmation_sent_by', get_current_user_id() );
        }

        return true;
    }

    /**
     * CONF-3: Renderiza badge de confirmaÃ§Ã£o para a interface.
     *
     * @since 1.2.0
     * @param string $confirmation_status Status de confirmaÃ§Ã£o.
     * @return string HTML do badge.
     */
    private function render_confirmation_badge( $confirmation_status ) {
        $badges = [
            'not_sent'  => [
                'class' => 'status-confirmation-not-sent',
                'text'  => __( 'NÃ£o confirmado', 'dps-agenda-addon' ),
                'icon'  => 'âšª',
            ],
            'sent'      => [
                'class' => 'status-confirmation-sent',
                'text'  => __( 'Enviado', 'dps-agenda-addon' ),
                'icon'  => 'ðŸ“¤',
            ],
            'confirmed' => [
                'class' => 'status-confirmation-confirmed',
                'text'  => __( 'Confirmado', 'dps-agenda-addon' ),
                'icon'  => 'âœ…',
            ],
            'denied'    => [
                'class' => 'status-confirmation-denied',
                'text'  => __( 'Cancelado', 'dps-agenda-addon' ),
                'icon'  => 'âŒ',
            ],
            'no_answer' => [
                'class' => 'status-confirmation-no-answer',
                'text'  => __( 'NÃ£o atendeu', 'dps-agenda-addon' ),
                'icon'  => 'âš ï¸',
            ],
        ];

        $badge = isset( $badges[ $confirmation_status ] ) ? $badges[ $confirmation_status ] : $badges['not_sent'];

        return '<span class="dps-confirmation-badge ' . esc_attr( $badge['class'] ) . '" title="' . esc_attr( $badge['text'] ) . '">' . $badge['icon'] . ' ' . esc_html( $badge['text'] ) . '</span>';
    }

    /**
     * Renderiza botÃ£o do pet com dados para modal de perfil Pet + Tutor.
     *
     * @param WP_Post|null $pet_post    Post do pet.
     * @param WP_Post|null $client_post Post do tutor.
     * @return string
     */
    private function render_pet_profile_trigger( $pet_post, $client_post ) {
        $pet_name = $pet_post ? $pet_post->post_title : '';

        if ( ! $pet_post ) {
            return '<span class="dps-pet-name-text">' . esc_html( $pet_name ?: 'â€”' ) . '</span>';
        }

        $pet_species = get_post_meta( $pet_post->ID, 'pet_species', true );
        $pet_breed   = get_post_meta( $pet_post->ID, 'pet_breed', true );
        $pet_size    = get_post_meta( $pet_post->ID, 'pet_size', true );
        $pet_weight  = get_post_meta( $pet_post->ID, 'pet_weight', true );
        $pet_sex     = get_post_meta( $pet_post->ID, 'pet_sex', true );

        $client_name    = $client_post ? $client_post->post_title : '';
        $client_phone   = $client_post ? get_post_meta( $client_post->ID, 'client_phone', true ) : '';
        $client_email   = $client_post ? get_post_meta( $client_post->ID, 'client_email', true ) : '';
        $client_address = $client_post ? get_post_meta( $client_post->ID, 'client_address', true ) : '';
        $aria_label     = sprintf(
            /* translators: 1: pet name, 2: tutor name. */
            __( 'Abrir perfil rÃ¡pido de %1$s e %2$s', 'dps-agenda-addon' ),
            $pet_name ? $pet_name : __( 'pet', 'dps-agenda-addon' ),
            $client_name ? $client_name : __( 'tutor', 'dps-agenda-addon' )
        );

        $button  = '<button type="button" class="dps-pet-profile-trigger"';
        $button .= ' data-pet-name="' . esc_attr( $pet_name ) . '"';
        $button .= ' data-pet-species="' . esc_attr( $pet_species ) . '"';
        $button .= ' data-pet-breed="' . esc_attr( $pet_breed ) . '"';
        $button .= ' data-pet-size="' . esc_attr( $pet_size ) . '"';
        $button .= ' data-pet-weight="' . esc_attr( $pet_weight ) . '"';
        $button .= ' data-pet-sex="' . esc_attr( $pet_sex ) . '"';
        $button .= ' data-client-name="' . esc_attr( $client_name ) . '"';
        $button .= ' data-client-phone="' . esc_attr( $client_phone ) . '"';
        $button .= ' data-client-email="' . esc_attr( $client_email ) . '"';
        $button .= ' data-client-address="' . esc_attr( $client_address ) . '"';
        $button .= ' title="' . esc_attr__( 'Ver perfil rÃ¡pido do pet e tutor', 'dps-agenda-addon' ) . '"';
        $button .= ' aria-label="' . esc_attr( $aria_label ) . '" aria-haspopup="dialog" aria-expanded="false">';
        $button .= '<span class="dps-pet-profile-trigger__name">' . esc_html( $pet_name ) . '</span>';
        $button .= '<span class="dps-pet-profile-trigger__icon" aria-hidden="true">&gt;</span>';
        $button .= '</button>';

        return $button;
    }

    /**
     * Renderiza a celula compacta de horario com destaque para atrasos.
     *
     * @param string $time    Hora do atendimento.
     * @param bool   $is_late Se o atendimento esta atrasado.
     * @return string
     */
    private function render_agenda_time_cell( $time, $is_late ) {
        $html  = '<td class="dps-agenda-cell dps-agenda-cell--time" data-label="' . esc_attr__( 'Horario', 'dps-agenda-addon' ) . '">';
        $html .= '<div class="dps-agenda-time-block">';
        $html .= '<strong class="dps-agenda-time-block__value">' . esc_html( $time ) . '</strong>';
        if ( $is_late ) {
            $html .= '<span class="dps-agenda-time-block__meta">' . esc_html__( 'Atrasado', 'dps-agenda-addon' ) . '</span>';
        }
        $html .= '</div>';
        $html .= '</td>';

        return $html;
    }

    /**
     * Renderiza a celula de pet/tutor com hierarquia unica entre as abas.
     *
     * @param WP_Post|null $pet_post    Post do pet.
     * @param WP_Post|null $client_post Post do tutor.
     * @return string
     */
    private function render_agenda_pet_cell( $pet_post, $client_post ) {
        $aggr_badge = '';
        $restrictions_badge = '';
        $client_name = $client_post ? $client_post->post_title : '';
        if ( $pet_post ) {
            $aggr = get_post_meta( $pet_post->ID, 'pet_aggressive', true );
            if ( $aggr ) {
                $aggr_badge = ' <span class="dps-pet-badge dps-pet-badge--aggressive" title="' . esc_attr__( 'Pet agressivo - cuidado no manejo', 'dps-agenda-addon' ) . '">' . esc_html__( 'Agressivo', 'dps-agenda-addon' ) . '</span>';
            }
            $restrictions_badge = $this->get_pet_product_restrictions_badge( $pet_post );
        }

        $html  = '<td class="dps-agenda-cell dps-agenda-cell--pet" data-label="' . esc_attr__( 'Pet e tutor', 'dps-agenda-addon' ) . '">';
        $html .= '<div class="dps-agenda-pet-stack">';
        $html .= $this->render_pet_profile_trigger( $pet_post, $client_post );
        if ( $client_name ) {
            $html .= '<p class="dps-agenda-pet-owner" title="' . esc_attr( $client_name ) . '">';
            $html .= '<span class="dps-agenda-pet-owner__label">' . esc_html__( 'Tutor', 'dps-agenda-addon' ) . '</span>';
            $html .= '<span class="dps-agenda-pet-owner__value">' . esc_html( $client_name ) . '</span>';
            $html .= '</p>';
        }
        if ( $aggr_badge || $restrictions_badge ) {
            $html .= '<div class="dps-agenda-pet-flags">' . $aggr_badge . $restrictions_badge . '</div>';
        }
        $html .= '</div>';
        $html .= '</td>';

        return $html;
    }

    /**
     * Renderiza a celula de logistica da aba de detalhes.
     *
     * @param int $appointment_id ID do agendamento.
     * @return string
     */
    private function render_agenda_logistics_cell( $appointment_id ) {
        $client_address = DPS_Agenda_GPS_Helper::get_client_address( $appointment_id );
        $map_url        = $client_address ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode( $client_address ) : '';
        $route_url      = DPS_Agenda_GPS_Helper::get_route_url( $appointment_id );

        $html  = '<td class="dps-agenda-cell dps-agenda-cell--logistics" data-label="' . esc_attr__( 'LogÃ­stica', 'dps-agenda-addon' ) . '">';
        $html .= '<div class="dps-agenda-logistics">';

        if ( $client_address ) {
            $html .= '<p class="dps-agenda-logistics__address">' . esc_html( $client_address ) . '</p>';

            if ( $map_url || $route_url ) {
                $html .= '<div class="dps-agenda-logistics__actions">';

                if ( $map_url ) {
                    $html .= '<a href="' . esc_url( $map_url ) . '" target="_blank" rel="noopener noreferrer" class="dps-agenda-logistics-link dps-agenda-logistics-link--map">' . esc_html__( 'Abrir mapa', 'dps-agenda-addon' ) . '</a>';
                }

                if ( $route_url ) {
                    $html .= '<a href="' . esc_url( $route_url ) . '" target="_blank" rel="noopener noreferrer" class="dps-agenda-logistics-link dps-agenda-logistics-link--route">' . esc_html__( 'Abrir rota', 'dps-agenda-addon' ) . '</a>';
                }

                $html .= '</div>';
            }
        } else {
            $html .= '<span class="dps-agenda-logistics__empty">' . esc_html__( 'Sem endereco para logistica', 'dps-agenda-addon' ) . '</span>';
        }

        $html .= '</div>';
        $html .= '</td>';

        return $html;
    }

    /**
     * Renderiza CTA secundario padronizado para reagendamento.
     *
     * @param int    $appt_id ID do agendamento.
     * @param string $date    Data atual.
     * @param string $time    Hora atual.
     * @return string
     */
    private function render_agenda_reschedule_cta( $appt_id, $date, $time ) {
        return '<button type="button" class="dps-agenda-action-link dps-agenda-action-link--secondary dps-quick-reschedule" data-appt-id="' . esc_attr( $appt_id ) . '" data-date="' . esc_attr( $date ) . '" data-time="' . esc_attr( $time ) . '" title="' . esc_attr__( 'Reagendar atendimento', 'dps-agenda-addon' ) . '"><span class="dps-agenda-action-link__label">' . esc_html__( 'Reagendar', 'dps-agenda-addon' ) . '</span></button>';
    }

    /**
     * Linha redesenhada da aba Visao Rapida.
     *
     * @param WP_Post $appt          Agendamento.
     * @param array   $column_labels Labels das colunas.
     * @return string
     */
    public function render_appointment_row_tab1_m3( $appt, $column_labels ) {
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

        $is_late     = $this->is_appointment_late( $date, $time, $status );
        $row_classes = [ 'status-' . $status ];
        if ( $is_late ) {
            $row_classes[] = 'is-late';
        }

        $service_ids          = get_post_meta( $appt->ID, 'appointment_services', true );
        $confirmation_status  = $this->get_confirmation_status( $appt->ID );
        $confirmation_classes = [
            'confirmed' => 'confirmed',
            'denied'    => 'cancelled',
            'not_sent'  => 'not-confirmed',
            'sent'      => 'not-confirmed',
            'no_answer' => 'not-confirmed',
        ];
        $confirmation_class   = isset( $confirmation_classes[ $confirmation_status ] ) ? $confirmation_classes[ $confirmation_status ] : 'not-confirmed';
        $is_not_confirmed     = in_array( $confirmation_status, [ 'not_sent', 'sent', 'no_answer' ], true );

        ob_start();

        echo '<tr data-appt-id="' . esc_attr( $appt->ID ) . '" class="' . esc_attr( implode( ' ', $row_classes ) ) . '">';
        echo $this->render_agenda_time_cell( $time, $is_late );
        echo $this->render_agenda_pet_cell( $pet_post, $client_post );

        echo '<td class="dps-agenda-cell dps-agenda-cell--service dps-col-service" data-label="' . esc_attr( ! empty( $column_labels['service'] ) ? __( 'ServiÃ§os', 'dps-agenda-addon' ) : '' ) . '">';
        if ( is_array( $service_ids ) && ! empty( $service_ids ) ) {
            $service_count      = count( $service_ids );
            $service_label      = sprintf( _n( '%d serviÃ§o', '%d serviÃ§os', $service_count, 'dps-agenda-addon' ), $service_count );
            $service_aria_label = sprintf(
                /* translators: %s: quantidade de servicos. */
                __( 'Abrir modal de serviÃ§os do atendimento: %s', 'dps-agenda-addon' ),
                $service_label
            );
            echo '<button type="button" class="dps-services-popup-btn" data-appt-id="' . esc_attr( $appt->ID ) . '" title="' . esc_attr__( 'Ver serviÃ§os e observaÃ§Ãµes', 'dps-agenda-addon' ) . '" aria-label="' . esc_attr( $service_aria_label ) . '" aria-haspopup="dialog" aria-expanded="false">';
            echo '<span class="dps-services-popup-btn__text">' . esc_html__( 'Ver serviÃ§os', 'dps-agenda-addon' ) . '</span>';
            echo '</button>';
        } else {
            echo '<span class="dps-no-services">' . esc_html__( 'Sem serviÃ§os', 'dps-agenda-addon' ) . '</span>';
        }
        echo '</td>';

        echo '<td class="dps-agenda-cell dps-agenda-cell--confirmation" data-label="' . esc_attr( ! empty( $column_labels['confirmation'] ) ? __( 'ConfirmaÃ§Ã£o', 'dps-agenda-addon' ) : '' ) . '">';
        echo '<div class="dps-confirmation-dropdown-wrapper">';
        echo '<select class="dps-confirmation-dropdown dps-dropdown--' . esc_attr( $confirmation_class ) . '" data-appt-id="' . esc_attr( $appt->ID ) . '">';
        echo '<option value="confirmed"' . selected( $confirmation_status, 'confirmed', false ) . '>' . esc_html__( 'Confirmado', 'dps-agenda-addon' ) . '</option>';
        echo '<option value="not_sent"' . selected( $is_not_confirmed, true, false ) . '>' . esc_html__( 'NÃ£o confirmado', 'dps-agenda-addon' ) . '</option>';
        echo '<option value="denied"' . selected( $confirmation_status, 'denied', false ) . '>' . esc_html__( 'Cancelado', 'dps-agenda-addon' ) . '</option>';
        echo '</select>';
        echo '</div>';
        echo '</td>';

        echo '<td class="dps-agenda-cell dps-agenda-cell--actions" data-label="' . esc_attr__( 'AÃ§Ãµes', 'dps-agenda-addon' ) . '">';
        echo '<div class="dps-agenda-row-actions">';
        echo $this->render_agenda_reschedule_cta( $appt->ID, $date, $time );
        echo '</div>';
        echo '</td>';

        echo '</tr>';

        return ob_get_clean();
    }

    /**
     * Linha redesenhada da aba Operacao.
     *
     * @param WP_Post $appt          Agendamento.
     * @param array   $column_labels Labels das colunas.
     * @return string
     */
    public function render_appointment_row_tab2_m3( $appt, $column_labels ) {
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

        $sub_id_meta      = get_post_meta( $appt->ID, 'subscription_id', true );
        $is_subscription  = ! empty( $sub_id_meta );
        $is_late          = $this->is_appointment_late( $date, $time, $status );
        $row_classes      = [ 'status-' . $status ];
        $status_config    = [
            'pendente'        => [ 'label' => __( 'Pendente', 'dps-agenda-addon' ), 'class' => 'pending' ],
            'finalizado'      => [ 'label' => __( 'Finalizado', 'dps-agenda-addon' ), 'class' => 'finished' ],
            'finalizado_pago' => [ 'label' => __( 'Pago', 'dps-agenda-addon' ), 'class' => 'paid' ],
            'cancelado'       => [ 'label' => __( 'Cancelado', 'dps-agenda-addon' ), 'class' => 'cancelled' ],
        ];
        if ( $is_late ) {
            $row_classes[] = 'is-late';
        }
        if ( $is_subscription ) {
            unset( $status_config['finalizado_pago'] );
            if ( 'finalizado_pago' === $status ) {
                $status = 'finalizado';
                update_post_meta( $appt->ID, 'appointment_status', $status );
            }
        }

        $current_status     = isset( $status_config[ $status ] ) ? $status_config[ $status ] : $status_config['pendente'];
        $client_name        = $client_post ? $client_post->post_title : '';
        $pet_name           = $pet_post ? $pet_post->post_title : '';
        $checkin_data       = DPS_Agenda_Checkin_Service::get_checkin( $appt->ID );
        $checkout_data      = DPS_Agenda_Checkin_Service::get_checkout( $appt->ID );
        $has_checkin        = (bool) $checkin_data;
        $has_checkout       = (bool) $checkout_data;
        $summary            = DPS_Agenda_Checkin_Service::get_safety_summary( $appt->ID );
        $checklist_progress = DPS_Agenda_Checklist_Service::get_progress( $appt->ID );
        $rework_count       = DPS_Agenda_Checklist_Service::count_reworks( $appt->ID );

        if ( 100 === $checklist_progress ) {
            $checklist_btn_label = __( 'Checklist concluÃ­do', 'dps-agenda-addon' );
            $checklist_btn_short = __( 'Checklist', 'dps-agenda-addon' );
            $checklist_btn_class = 'dps-expand-panels-btn--done';
        } elseif ( $checklist_progress > 0 ) {
            $checklist_btn_label = __( 'Editar checklist', 'dps-agenda-addon' );
            $checklist_btn_short = __( 'Checklist', 'dps-agenda-addon' );
            $checklist_btn_class = 'dps-expand-panels-btn--checkin';
        } else {
            $checklist_btn_label = __( 'Abrir checklist', 'dps-agenda-addon' );
            $checklist_btn_short = __( 'Checklist', 'dps-agenda-addon' );
            $checklist_btn_class = 'dps-expand-panels-btn--pending';
        }

        if ( $has_checkin && $has_checkout ) {
            $ops_btn_icon  = 'âœŽ';
            $ops_btn_label = __( 'Editar check-in / check-out', 'dps-agenda-addon' );
            $ops_btn_short = __( 'Editar', 'dps-agenda-addon' );
            $ops_btn_class = 'dps-operation-action-btn--done';
        } elseif ( $has_checkin ) {
            $ops_btn_icon  = 'ðŸ“¤';
            $ops_btn_label = __( 'Registrar check-out', 'dps-agenda-addon' );
            $ops_btn_short = __( 'Check-out', 'dps-agenda-addon' );
            $ops_btn_class = 'dps-operation-action-btn--checkout';
        } else {
            $ops_btn_icon  = 'ðŸ“¥';
            $ops_btn_label = __( 'Registrar check-in', 'dps-agenda-addon' );
            $ops_btn_short = __( 'Check-in', 'dps-agenda-addon' );
            $ops_btn_class = 'dps-operation-action-btn--checkin';
        }

        ob_start();

        echo '<tr data-appt-id="' . esc_attr( $appt->ID ) . '" class="' . esc_attr( implode( ' ', $row_classes ) ) . '">';
        echo $this->render_agenda_time_cell( $time, $is_late );
        echo $this->render_agenda_pet_cell( $pet_post, $client_post );

        echo '<td class="dps-agenda-cell dps-agenda-cell--status" data-label="' . esc_attr( ! empty( $column_labels['status'] ) ? __( 'Status do serviÃ§o', 'dps-agenda-addon' ) : '' ) . '">';
        if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
            echo '<div class="dps-status-dropdown-wrapper">';
            echo '<select class="dps-status-dropdown dps-dropdown--' . esc_attr( $current_status['class'] ) . '" data-appt-id="' . esc_attr( $appt->ID ) . '" data-current-status="' . esc_attr( $status ) . '" data-appt-version="' . esc_attr( $appt_version ) . '">';
            foreach ( $status_config as $value => $config ) {
                echo '<option value="' . esc_attr( $value ) . '"' . selected( $status, $value, false ) . '>' . esc_html( $config['label'] ) . '</option>';
            }
            echo '</select>';
            echo '</div>';
        } else {
            echo '<span class="dps-status-badge dps-status-badge--' . esc_attr( $current_status['class'] ) . '">' . esc_html( $current_status['label'] ) . '</span>';
        }
        echo '</td>';

        echo '<td class="dps-agenda-cell dps-agenda-cell--payment" data-label="' . esc_attr( ! empty( $column_labels['payment'] ) ? __( 'Pagamento', 'dps-agenda-addon' ) : '' ) . '">';
        if ( 'finalizado' === $status && ! $is_subscription ) {
            $payment_link  = get_post_meta( $appt->ID, 'dps_payment_link', true );
            $default_link  = 'https://link.mercadopago.com.br/desipetshower';
            $link_to_use   = $payment_link ? $payment_link : $default_link;
            $total_val     = (float) get_post_meta( $appt->ID, 'appointment_total_value', true );
            $client_phone  = $client_post ? get_post_meta( $client_post->ID, 'client_phone', true ) : '';
            $valor_fmt     = number_format_i18n( $total_val, 2 );
            $whatsapp_msg  = sprintf(
                __( 'OlÃ¡ %1$s! O atendimento do pet %2$s foi finalizado. Valor: R$ %3$s. Link para pagamento: %4$s', 'dps-agenda-addon' ),
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
            echo esc_html__( 'Cobrar cliente', 'dps-agenda-addon' );
            echo '</button>';
        } elseif ( 'finalizado_pago' === $status ) {
            echo '<span class="dps-payment-status dps-payment-status--paid">' . esc_html__( 'Pago', 'dps-agenda-addon' ) . '</span>';
        } elseif ( 'cancelado' === $status ) {
            echo '<span class="dps-payment-status dps-payment-status--cancelled">' . esc_html__( 'Sem cobranÃ§a', 'dps-agenda-addon' ) . '</span>';
        } else {
            echo '<span class="dps-payment-status dps-payment-status--pending">' . esc_html__( 'Em aberto', 'dps-agenda-addon' ) . '</span>';
        }
        echo '</td>';

        echo '<td class="dps-agenda-cell dps-agenda-cell--operational" data-label="' . esc_attr__( 'OperaÃ§Ã£o', 'dps-agenda-addon' ) . '">';
        echo '<div class="dps-operational-stack">';
        echo '<div class="dps-operational-summary">';
        echo '<span class="dps-operational-pill dps-operational-pill--checklist" title="' . esc_attr__( 'Progresso do checklist operacional', 'dps-agenda-addon' ) . '">';
        echo '<span class="dps-operational-pill__label">' . esc_html__( 'Checklist', 'dps-agenda-addon' ) . '</span>';
        echo '<strong class="dps-operational-pill__value">' . esc_html( $checklist_progress ) . '%</strong>';
        echo '</span>';

        if ( $rework_count > 0 ) {
            echo '<span class="dps-operational-pill dps-operational-pill--rework" title="' . esc_attr__( 'Retrabalhos registrados', 'dps-agenda-addon' ) . '">';
            echo '<span class="dps-operational-pill__label">' . esc_html__( 'Retrabalho', 'dps-agenda-addon' ) . '</span>';
            echo '<strong class="dps-operational-pill__value">' . esc_html( $rework_count ) . '</strong>';
            echo '</span>';
        }

        if ( $has_checkin ) {
            echo '<span class="dps-operational-pill dps-operational-pill--checkin">';
            echo '<span class="dps-operational-pill__label">' . esc_html__( 'Check-in', 'dps-agenda-addon' ) . '</span>';
            echo '<strong class="dps-operational-pill__value">' . esc_html( mysql2date( 'H:i', $checkin_data['time'] ) ) . '</strong>';
            echo '</span>';
        } else {
            echo '<span class="dps-operational-pill dps-operational-pill--pending">';
            echo '<span class="dps-operational-pill__label">' . esc_html__( 'Fluxo', 'dps-agenda-addon' ) . '</span>';
            echo '<strong class="dps-operational-pill__value">' . esc_html__( 'Pendente', 'dps-agenda-addon' ) . '</strong>';
            echo '</span>';
        }

        if ( $has_checkout ) {
            echo '<span class="dps-operational-pill dps-operational-pill--checkout">';
            echo '<span class="dps-operational-pill__label">' . esc_html__( 'Check-out', 'dps-agenda-addon' ) . '</span>';
            echo '<strong class="dps-operational-pill__value">' . esc_html( mysql2date( 'H:i', $checkout_data['time'] ) ) . '</strong>';
            echo '</span>';
        }

        $visible_summary = array_slice( array_values( $summary ), 0, 2 );
        foreach ( $visible_summary as $item ) {
            echo '<span class="dps-safety-tag dps-safety-tag--' . esc_attr( $item['severity'] ) . '" title="' . esc_attr( $item['label'] ) . '">' . esc_html( $item['icon'] . ' ' . $item['label'] ) . '</span>';
        }

        if ( count( $summary ) > count( $visible_summary ) ) {
            echo '<span class="dps-operational-pill dps-operational-pill--summary">';
            echo '<span class="dps-operational-pill__label">' . esc_html__( 'Alertas', 'dps-agenda-addon' ) . '</span>';
            echo '<strong class="dps-operational-pill__value">+' . esc_html( count( $summary ) - count( $visible_summary ) ) . '</strong>';
            echo '</span>';
        }
        echo '</div>';

        echo '<div class="dps-operational-actions">';
        echo '<button type="button" class="dps-expand-panels-btn dps-operation-modal-btn ' . esc_attr( $checklist_btn_class ) . '" data-appt-id="' . esc_attr( $appt->ID ) . '" data-operation-focus="checklist" title="' . esc_attr__( 'Abrir checklist operacional em modal', 'dps-agenda-addon' ) . '" aria-haspopup="dialog">';
        echo '<span class="dps-expand-panels-btn__icon">ðŸ“‹</span>';
        echo '<span class="dps-expand-panels-btn__label">' . esc_html( $checklist_btn_label ) . '</span>';
        echo '<span class="dps-expand-panels-btn__label-short">' . esc_html( $checklist_btn_short ) . '</span>';
        echo '</button>';

        $ops_focus_target = $has_checkin && ! $has_checkout ? 'checkout' : 'checkin';
        echo '<button type="button" class="dps-operation-action-btn ' . esc_attr( $ops_btn_class ) . '" data-appt-id="' . esc_attr( $appt->ID ) . '" data-operation-focus="' . esc_attr( $ops_focus_target ) . '" title="' . esc_attr__( 'Abrir check-in e check-out em modal', 'dps-agenda-addon' ) . '" aria-haspopup="dialog">';
        echo '<span class="dps-operation-action-btn__icon">' . esc_html( $ops_btn_icon ) . '</span>';
        echo '<span class="dps-operation-action-btn__label">' . esc_html( $ops_btn_label ) . '</span>';
        echo '<span class="dps-operation-action-btn__label-short">' . esc_html( $ops_btn_short ) . '</span>';
        echo '</button>';
        echo '</div>';
        echo '</div>';
        echo '</td>';

        echo '<td class="dps-agenda-cell dps-agenda-cell--actions" data-label="' . esc_attr__( 'AÃ§Ãµes', 'dps-agenda-addon' ) . '">';
        echo '<div class="dps-agenda-row-actions">';
        echo $this->render_agenda_reschedule_cta( $appt->ID, $date, $time );
        echo '</div>';
        echo '</td>';
        echo '</tr>';

        return ob_get_clean();
    }

    /**
     * Linha redesenhada da aba Detalhes.
     *
     * @param WP_Post $appt          Agendamento.
     * @param array   $column_labels Labels das colunas.
     * @return string
     */
    public function render_appointment_row_tab3_m3( $appt, $column_labels ) {
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

        $is_late     = $this->is_appointment_late( $date, $time, $status );
        $row_classes = [ 'status-' . $status ];
        if ( $is_late ) {
            $row_classes[] = 'is-late';
        }

        $taxidog_requested = get_post_meta( $appt->ID, 'appointment_taxidog', true );
        $appointment_notes = get_post_meta( $appt->ID, 'appointment_notes', true );

        ob_start();

        echo '<tr data-appt-id="' . esc_attr( $appt->ID ) . '" class="' . esc_attr( implode( ' ', $row_classes ) ) . '">';
        echo $this->render_agenda_time_cell( $time, $is_late );
        echo $this->render_agenda_pet_cell( $pet_post, $client_post );

        echo '<td class="dps-agenda-cell dps-agenda-cell--taxidog" data-label="TaxiDog">';
        echo '<div class="dps-taxidog-wrapper">';
        if ( $taxidog_requested ) {
            echo '<span class="dps-taxidog-label dps-taxidog-label--requested">' . esc_html__( 'Solicitado', 'dps-agenda-addon' ) . '</span>';
        } else {
            echo '<span class="dps-taxidog-label dps-taxidog-label--not-requested">' . esc_html__( 'NÃ£o solicitado', 'dps-agenda-addon' ) . '</span>';
        }
        echo '</div>';
        echo '</td>';

        echo '<td class="dps-agenda-cell dps-agenda-cell--notes" data-label="' . esc_attr__( 'ObservaÃ§Ãµes', 'dps-agenda-addon' ) . '">';
        if ( ! empty( $appointment_notes ) ) {
            $word_count = str_word_count( wp_strip_all_tags( $appointment_notes ) );
            $max_words  = 10;
            if ( $word_count > $max_words ) {
                echo '<span class="dps-notes-preview" title="' . esc_attr( $appointment_notes ) . '">' . esc_html( wp_trim_words( $appointment_notes, $max_words, '...' ) ) . '</span>';
            } else {
                echo '<span class="dps-notes-text">' . esc_html( $appointment_notes ) . '</span>';
            }
        } else {
            echo '<span class="dps-notes-empty">' . esc_html__( 'Sem observaÃ§Ãµes', 'dps-agenda-addon' ) . '</span>';
        }
        echo '</td>';

        echo $this->render_agenda_logistics_cell( $appt->ID );

        echo '<td class="dps-agenda-cell dps-agenda-cell--actions" data-label="' . esc_attr__( 'AÃ§Ãµes', 'dps-agenda-addon' ) . '">';
        echo '<div class="dps-agenda-row-actions">';
        echo $this->render_agenda_reschedule_cta( $appt->ID, $date, $time );
        echo '</div>';
        echo '</td>';
        echo '</tr>';

        return ob_get_clean();
    }

    /**
     * Renderiza a linha de um agendamento para a Aba 1 (VisÃ£o RÃ¡pida).
     *
     * Colunas: HorÃ¡rio, Pet (com badge agressivo + perfil rÃ¡pido), ServiÃ§os (popup), ConfirmaÃ§Ã£o (dropdown)
     *
     * @since 1.4.0
     * @since 1.4.2 Modificado: ServiÃ§os com botÃ£o popup, ConfirmaÃ§Ã£o como dropdown elegante
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

        // Detecta se o atendimento estÃ¡ atrasado
        $is_late = $this->is_appointment_late( $date, $time, $status );
        $row_classes = [ 'status-' . $status ];
        if ( $is_late ) {
            $row_classes[] = 'is-late';
        }

        ob_start();

        echo '<tr data-appt-id="' . esc_attr( $appt->ID ) . '" class="' . esc_attr( implode( ' ', $row_classes ) ) . '">';

        // HorÃ¡rio
        echo '<td data-label="' . esc_attr__( 'HorÃ¡rio', 'dps-agenda-addon' ) . '">' . esc_html( $time ) . '</td>';

        // Pet com flag de agressividade e badge
        $pet_name = $pet_post ? $pet_post->post_title : '';
        $aggr_badge = '';
        $restrictions_badge = '';
        if ( $pet_post ) {
            $aggr = get_post_meta( $pet_post->ID, 'pet_aggressive', true );
            if ( $aggr ) {
                $aggr_badge = ' <span class="dps-pet-badge dps-pet-badge--aggressive" title="' . esc_attr__( 'Pet agressivo - cuidado no manejo', 'dps-agenda-addon' ) . '">' . esc_html__( 'Agressivo', 'dps-agenda-addon' ) . '</span>';
            }
            $restrictions_badge = $this->get_pet_product_restrictions_badge( $pet_post );
        }
        echo '<td data-label="' . esc_attr__( 'Pet', 'dps-agenda-addon' ) . '">';
        echo $this->render_pet_profile_trigger( $pet_post, $client_post );
        echo $aggr_badge . $restrictions_badge;
        echo '</td>';

        // ServiÃ§os (botÃ£o que abre popup)
        echo '<td class="dps-col-service" data-label="' . esc_attr( ! empty( $column_labels['service'] ) ? __( 'ServiÃ§os', 'dps-agenda-addon' ) : '' ) . '">';
        $service_ids = get_post_meta( $appt->ID, 'appointment_services', true );
        if ( is_array( $service_ids ) && ! empty( $service_ids ) ) {
            // Conta quantos serviÃ§os
            $service_count = count( $service_ids );
            $service_label = sprintf( _n( '%d serviÃ§o', '%d serviÃ§os', $service_count, 'dps-agenda-addon' ), $service_count );
            $service_aria_label = sprintf(
                /* translators: %s: quantidade de serviÃ§os. */
                __( 'Abrir modal de serviÃ§os do atendimento: %s', 'dps-agenda-addon' ),
                $service_label
            );
            echo '<button type="button" class="dps-services-popup-btn" data-appt-id="' . esc_attr( $appt->ID ) . '" title="' . esc_attr__( 'Ver serviÃ§os e observaÃ§Ãµes', 'dps-agenda-addon' ) . '" aria-label="' . esc_attr( $service_aria_label ) . '" aria-haspopup="dialog" aria-expanded="false">';
            echo '<span class="dps-services-popup-btn__text">' . esc_html__( 'Ver serviÃ§os', 'dps-agenda-addon' ) . '</span>';
            echo '</button>';
        } else {
            echo '<span class="dps-no-services">â€“</span>';
        }
        echo '</td>';

        // ConfirmaÃ§Ã£o (dropdown elegante)
        echo '<td data-label="' . esc_attr( ! empty( $column_labels['confirmation'] ) ? __( 'ConfirmaÃ§Ã£o', 'dps-agenda-addon' ) : '' ) . '">';
        $confirmation_status = $this->get_confirmation_status( $appt->ID );

        // Config de status de confirmaÃ§Ã£o com Ã­cones
        $confirmation_config = [
            'not_sent'  => [ 'icon' => 'âšª', 'label' => __( 'NÃƒO CONFIRMADO', 'dps-agenda-addon' ), 'class' => 'not-confirmed' ],
            'confirmed' => [ 'icon' => 'âœ…', 'label' => __( 'CONFIRMADO', 'dps-agenda-addon' ), 'class' => 'confirmed' ],
            'denied'    => [ 'icon' => 'âŒ', 'label' => __( 'CANCELADO', 'dps-agenda-addon' ), 'class' => 'cancelled' ],
            'no_answer' => [ 'icon' => 'âš ï¸', 'label' => __( 'NÃƒO CONFIRMADO', 'dps-agenda-addon' ), 'class' => 'not-confirmed' ],
            'sent'      => [ 'icon' => 'ðŸ“¤', 'label' => __( 'NÃƒO CONFIRMADO', 'dps-agenda-addon' ), 'class' => 'not-confirmed' ],
        ];

        $current_conf = isset( $confirmation_config[ $confirmation_status ] ) ? $confirmation_config[ $confirmation_status ] : $confirmation_config['not_sent'];

        // Status nÃ£o confirmados sÃ£o agrupados sob "NÃƒO CONFIRMADO"
        $is_not_confirmed = in_array( $confirmation_status, [ 'not_sent', 'sent', 'no_answer' ], true );

        echo '<div class="dps-confirmation-dropdown-wrapper">';
        echo '<select class="dps-confirmation-dropdown dps-dropdown--' . esc_attr( $current_conf['class'] ) . '" data-appt-id="' . esc_attr( $appt->ID ) . '">';
        echo '<option value="confirmed"' . selected( $confirmation_status, 'confirmed', false ) . '>âœ… ' . esc_html__( 'CONFIRMADO', 'dps-agenda-addon' ) . '</option>';
        echo '<option value="not_sent"' . selected( $is_not_confirmed, true, false ) . '>âšª ' . esc_html__( 'NÃƒO CONFIRMADO', 'dps-agenda-addon' ) . '</option>';
        echo '<option value="denied"' . selected( $confirmation_status, 'denied', false ) . '>âŒ ' . esc_html__( 'CANCELADO', 'dps-agenda-addon' ) . '</option>';
        echo '</select>';
        echo '</div>';
        echo '</td>';

        // Coluna de AÃ§Ãµes (reagendar)
        echo '<td data-label="' . esc_attr__( 'AÃ§Ãµes', 'dps-agenda-addon' ) . '">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML seguro retornado pelo helper
        echo $this->render_reschedule_button( $appt->ID, $date, $time );
        echo '</td>';

        echo '</tr>';

        return ob_get_clean();
    }

    /**
     * Renderiza a linha de um agendamento para a Aba 2 (OperaÃ§Ã£o).
     *
     * Colunas: HorÃ¡rio, Pet (com badge agressivo + perfil rÃ¡pido), Status (dropdown com Ã­cones), Pagamento (popup)
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

        // HorÃ¡rio
        echo '<td data-label="' . esc_attr__( 'HorÃ¡rio', 'dps-agenda-addon' ) . '">' . esc_html( $time ) . '</td>';

        // Pet com flag de agressividade e badge
        $pet_name = $pet_post ? $pet_post->post_title : '';
        $aggr_badge = '';
        $restrictions_badge = '';
        if ( $pet_post ) {
            $aggr = get_post_meta( $pet_post->ID, 'pet_aggressive', true );
            if ( $aggr ) {
                $aggr_badge = ' <span class="dps-pet-badge dps-pet-badge--aggressive" title="' . esc_attr__( 'Pet agressivo - cuidado no manejo', 'dps-agenda-addon' ) . '">' . esc_html__( 'Agressivo', 'dps-agenda-addon' ) . '</span>';
            }
            $restrictions_badge = $this->get_pet_product_restrictions_badge( $pet_post );
        }
        $client_name = $client_post ? $client_post->post_title : '';
        echo '<td data-label="' . esc_attr__( 'Pet', 'dps-agenda-addon' ) . '">';
        echo $this->render_pet_profile_trigger( $pet_post, $client_post );
        echo $aggr_badge . $restrictions_badge;
        echo '</td>';

        // Status do serviÃ§o (dropdown elegante com Ã­cones)
        echo '<td data-label="' . esc_attr( ! empty( $column_labels['status'] ) ? __( 'Status do ServiÃ§o', 'dps-agenda-addon' ) : '' ) . '">';
        $can_edit = is_user_logged_in() && current_user_can( 'manage_options' );

        // Config de status com Ã­cones
        $status_config = [
            'pendente'        => [ 'icon' => 'â³', 'label' => __( 'PENDENTE', 'dps-agenda-addon' ), 'class' => 'pending' ],
            'finalizado'      => [ 'icon' => 'âœ…', 'label' => __( 'FINALIZADO', 'dps-agenda-addon' ), 'class' => 'finished' ],
            'finalizado_pago' => [ 'icon' => 'ðŸ’°', 'label' => __( 'FINALIZADO & PAGO', 'dps-agenda-addon' ), 'class' => 'paid' ],
            'cancelado'       => [ 'icon' => 'âŒ', 'label' => __( 'CANCELADO', 'dps-agenda-addon' ), 'class' => 'cancelled' ],
        ];

        // Verifica se Ã© assinatura
        $sub_id_meta = get_post_meta( $appt->ID, 'subscription_id', true );
        $is_subscription = ! empty( $sub_id_meta );
        if ( $is_subscription ) {
            unset( $status_config['finalizado_pago'] );
            if ( $status === 'finalizado_pago' ) {
                $status = 'finalizado';
                update_post_meta( $appt->ID, 'appointment_status', $status );
            }
        }

        $current_status = isset( $status_config[ $status ] ) ? $status_config[ $status ] : $status_config['pendente'];

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

        // Pagamento (botÃ£o com popup quando FINALIZADO)
        echo '<td data-label="' . esc_attr( ! empty( $column_labels['payment'] ) ? __( 'Pagamento', 'dps-agenda-addon' ) : '' ) . '">';

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
                __( 'OlÃ¡ %s! O atendimento do pet %s foi finalizado. Valor: R$ %s. Link para pagamento: %s', 'dps-agenda-addon' ),
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
            echo 'ðŸ’³ ' . esc_html__( 'Enviar Link', 'dps-agenda-addon' );
            echo '</button>';
        } elseif ( $status === 'finalizado_pago' ) {
            echo '<span class="dps-payment-status dps-payment-status--paid">ðŸ’° ' . esc_html__( 'Pago', 'dps-agenda-addon' ) . '</span>';
        } elseif ( $status === 'cancelado' ) {
            echo '<span class="dps-payment-status dps-payment-status--cancelled">â€“</span>';
        } else {
            echo '<span class="dps-payment-status dps-payment-status--pending">â³ ' . esc_html__( 'Aguardando', 'dps-agenda-addon' ) . '</span>';
        }
        echo '</td>';

        // Coluna Check-in / Check-out (botÃ£o claro com estado + indicadores de seguranÃ§a)
        echo '<td class="dps-operational-cell" data-label="' . esc_attr__( 'Check-in / Check-out', 'dps-agenda-addon' ) . '">';
        echo '<div class="dps-operational-indicators">';
        $has_checkin  = DPS_Agenda_Checkin_Service::has_checkin( $appt->ID );
        $has_checkout = DPS_Agenda_Checkin_Service::has_checkout( $appt->ID );
        $summary      = DPS_Agenda_Checkin_Service::get_safety_summary( $appt->ID );

        // Determina estado visual do botÃ£o
        if ( $has_checkout ) {
            $btn_icon  = 'âœ…';
            $btn_label = __( 'ConcluÃ­do', 'dps-agenda-addon' );
            $btn_label_short = __( 'ConcluÃ­do', 'dps-agenda-addon' );
            $btn_class = 'dps-expand-panels-btn--done';
        } elseif ( $has_checkin ) {
            $btn_icon  = 'ðŸ“¥';
            $btn_label = __( 'Fazer Check-out', 'dps-agenda-addon' );
            $btn_label_short = __( 'Check-out', 'dps-agenda-addon' );
            $btn_class = 'dps-expand-panels-btn--checkin';
        } else {
            $btn_icon  = 'ðŸ¥';
            $btn_label = __( 'Fazer Check-in', 'dps-agenda-addon' );
            $btn_label_short = __( 'Check-in', 'dps-agenda-addon' );
            $btn_class = 'dps-expand-panels-btn--pending';
        }

        echo '<button type="button" class="dps-expand-panels-btn ' . esc_attr( $btn_class ) . '" data-appt-id="' . esc_attr( $appt->ID ) . '" title="' . esc_attr__( 'Abrir painel de Check-in / Check-out', 'dps-agenda-addon' ) . '" aria-expanded="false">';
        echo '<span class="dps-expand-panels-btn__icon">' . esc_html( $btn_icon ) . '</span>';
        echo '<span class="dps-expand-panels-btn__label">' . esc_html( $btn_label ) . '</span>';
        echo '<span class="dps-expand-panels-btn__label-short">' . esc_html( $btn_label_short ) . '</span>';
        echo '<span class="dps-expand-panels-btn__arrow">â–¼</span>';
        echo '</button>';

        foreach ( $summary as $item ) :
            echo '<span class="dps-safety-tag dps-safety-tag--' . esc_attr( $item['severity'] ) . '" title="' . esc_attr( $item['label'] ) . '">';
            echo esc_html( $item['icon'] );
            echo '</span>';
        endforeach;

        echo '</div>';
        echo '</td>';

        // Coluna de AÃ§Ãµes (reagendar)
        echo '<td data-label="' . esc_attr__( 'AÃ§Ãµes', 'dps-agenda-addon' ) . '">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML seguro retornado pelo helper
        echo $this->render_reschedule_button( $appt->ID, $date, $time );
        echo '</td>';

        echo '</tr>';

        // Linha expansÃ­vel com painel de Check-in / Check-out
        $total_cols = 6; // HorÃ¡rio + Pet + Status + Pagamento + Check-in/Check-out + AÃ§Ãµes
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
     * Colunas: HorÃ¡rio, Pet (com badge agressivo + perfil rÃ¡pido), TaxiDog (com dropdown condicional)
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

        // HorÃ¡rio
        echo '<td data-label="' . esc_attr__( 'HorÃ¡rio', 'dps-agenda-addon' ) . '">' . esc_html( $time ) . '</td>';

        // Pet com flag de agressividade e badge
        $pet_name = $pet_post ? $pet_post->post_title : '';
        $aggr_badge = '';
        $restrictions_badge = '';
        if ( $pet_post ) {
            $aggr = get_post_meta( $pet_post->ID, 'pet_aggressive', true );
            if ( $aggr ) {
                $aggr_badge = ' <span class="dps-pet-badge dps-pet-badge--aggressive" title="' . esc_attr__( 'Pet agressivo - cuidado no manejo', 'dps-agenda-addon' ) . '">' . esc_html__( 'Agressivo', 'dps-agenda-addon' ) . '</span>';
            }
            $restrictions_badge = $this->get_pet_product_restrictions_badge( $pet_post );
        }
        echo '<td data-label="' . esc_attr__( 'Pet', 'dps-agenda-addon' ) . '">';
        echo $this->render_pet_profile_trigger( $pet_post, $client_post );
        echo $aggr_badge . $restrictions_badge;
        echo '</td>';

        // TaxiDog (exibe informaÃ§Ã£o simples com botÃ£o para Google Maps quando solicitado)
        echo '<td data-label="TaxiDog">';

        // Verifica se TaxiDog foi solicitado no agendamento
        $taxidog_requested = get_post_meta( $appt->ID, 'appointment_taxidog', true );

        if ( $taxidog_requested ) {
            // TaxiDog foi solicitado - exibe label e botÃ£o do Google Maps
            echo '<div class="dps-taxidog-wrapper">';

            // ObtÃ©m endereÃ§o do cliente para o Google Maps
            $client_address = DPS_Agenda_GPS_Helper::get_client_address( $appt->ID );

            if ( ! empty( $client_address ) ) {
                // URL de busca no Google Maps (abre direto no endereÃ§o)
                $map_url = 'https://www.google.com/maps/search/?api=1&query=' . urlencode( $client_address );
                echo '<a href="' . esc_url( $map_url ) . '" target="_blank" class="dps-taxidog-map-btn" title="' . esc_attr__( 'Abrir endereÃ§o no Google Maps', 'dps-agenda-addon' ) . '">';
                echo 'ðŸš ' . esc_html__( 'TAXIDOG SOLICITADO', 'dps-agenda-addon' ) . ' ðŸ“';
                echo '</a>';
            } else {
                // Sem endereÃ§o cadastrado - mostra apenas o label
                echo '<span class="dps-taxidog-label dps-taxidog-label--requested">ðŸš ' . esc_html__( 'TAXIDOG SOLICITADO', 'dps-agenda-addon' ) . '</span>';
            }
            echo '</div>';
        } else {
            // TaxiDog NÃƒO foi solicitado
            echo '<div class="dps-taxidog-wrapper">';
            echo '<span class="dps-taxidog-label dps-taxidog-label--not-requested">âšª ' . esc_html__( 'NÃƒO SOLICITADO', 'dps-agenda-addon' ) . '</span>';
            echo '</div>';
        }
        echo '</td>';

        // Coluna de ObservaÃ§Ãµes (notas do agendamento)
        echo '<td data-label="' . esc_attr__( 'ObservaÃ§Ãµes', 'dps-agenda-addon' ) . '">';
        $appointment_notes = get_post_meta( $appt->ID, 'appointment_notes', true );
        if ( ! empty( $appointment_notes ) ) {
            // Trunca observaÃ§Ãµes longas com tooltip para o texto completo
            $word_count = str_word_count( $appointment_notes );
            $max_words = 8;
            $has_more = $word_count > $max_words;

            if ( $has_more ) {
                $notes_preview = wp_trim_words( $appointment_notes, $max_words, '...' );
                echo '<span class="dps-notes-preview" title="' . esc_attr( $appointment_notes ) . '">';
                echo 'ðŸ“ ' . esc_html( $notes_preview );
                echo '</span>';
            } else {
                echo '<span class="dps-notes-text">ðŸ“ ' . esc_html( $appointment_notes ) . '</span>';
            }
        } else {
            echo '<span class="dps-notes-empty">â€”</span>';
        }
        echo '</td>';

        // Coluna Operacional (Checklist + Check-in/Check-out)
        echo '<td data-label="' . esc_attr__( 'Operacional', 'dps-agenda-addon' ) . '">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML seguro retornado pelo render helper
        echo DPS_Agenda_Addon::render_checkin_checklist_summary( $appt->ID );
        echo '</td>';

        // Coluna de AÃ§Ãµes (reagendar)
        echo '<td data-label="' . esc_attr__( 'AÃ§Ãµes', 'dps-agenda-addon' ) . '">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML seguro retornado pelo helper
        echo $this->render_reschedule_button( $appt->ID, $date, $time );
        echo '</td>';

        echo '</tr>';

        return ob_get_clean();
    }
}
