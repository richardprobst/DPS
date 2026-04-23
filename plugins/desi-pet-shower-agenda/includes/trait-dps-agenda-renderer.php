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
        return '<a href="#" class="dps-quick-action dps-quick-reschedule" data-appt-id="' . esc_attr( $appt_id ) . '" data-date="' . esc_attr( $date ) . '" data-time="' . esc_attr( $time ) . '" title="' . esc_attr__( 'Reagendar', 'dps-agenda-addon' ) . '">' . esc_html__( 'Reagendar', 'dps-agenda-addon' ) . '</a>';
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
            $restrictions_items[] = isset( $shampoo_labels[ $shampoo ] ) ? $shampoo_labels[ $shampoo ] : $shampoo;
        }

        // Verifica proibição de perfume
        $perfume = get_post_meta( $pet_id, 'pet_perfume_pref', true );
        if ( 'sem_perfume' === $perfume ) {
            $has_restrictions = true;
            $restrictions_items[] = __( ' SEM PERFUME', 'dps-agenda-addon' );
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
     * Obtém labels das colunas da tabela.
     *
     * @since 1.3.0
     * @return array Labels das colunas.
     */
    private function get_column_labels() {
        return [
            'date'         => __( 'Data', 'dps-agenda-addon' ),
            'time'         => __( 'Hora', 'dps-agenda-addon' ),
            'pet'          => __( 'Pet e tutor', 'dps-agenda-addon' ),
            'service'      => __( 'Serviço', 'dps-agenda-addon' ),
            'status'       => __( 'Status', 'dps-agenda-addon' ),
            'payment'      => __( 'Pagamento', 'dps-agenda-addon' ),
            'map'          => __( 'Mapa', 'dps-agenda-addon' ),
            'confirmation' => __( 'Confirmação', 'dps-agenda-addon' ),
            'charge'       => __( 'Cobrança', 'dps-agenda-addon' ),
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

        $appt_version = max( 1, intval( get_post_meta( $appt->ID, '_dps_appointment_version', true ) ) );

        $sub_id_meta      = get_post_meta( $appt->ID, 'subscription_id', true );
        $is_subscription  = ! empty( $sub_id_meta );
        $display_status   = ( $is_subscription && 'finalizado_pago' === $status ) ? 'finalizado' : $status;

        // Detecta se o atendimento está atrasado (UX-3)
        $is_late = $this->is_appointment_late( $date, $time, $display_status );
        $row_classes = [ 'status-' . $display_status ];
        if ( $is_late ) {
            $row_classes[] = 'is-late';
        }

        ob_start();

        // Cada linha recebe classes de status e um data attribute para permitir manipulação via JS.
        echo '<tr data-appt-id="' . esc_attr( $appt->ID ) . '" class="' . esc_attr( implode( ' ', $row_classes ) ) . '">';

        // Mostra a data no formato dia-mês-ano
        echo '<td data-label="' . esc_attr( ! empty( $column_labels['date'] ) ? __( 'Data', 'dps-agenda-addon' ) : '' ) . '">' . esc_html( date_i18n( 'd-m-Y', strtotime( $date ) ) ) . '</td>';
        echo '<td data-label="' . esc_attr( ! empty( $column_labels['time'] ) ? __( 'Hora', 'dps-agenda-addon' ) : '' ) . '">' . esc_html( $time ) . '</td>';

        // Nome do pet com flags operacionais e consentimento quando aplicável.
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

        // Serviços e assinatura
        echo '<td data-label="' . esc_attr( ! empty( $column_labels['service'] ) ? __( 'Serviço', 'dps-agenda-addon' ) : '' ) . '">';
        if ( $sub_id_meta ) {
            echo '<span class="dps-subscription-flag" style="font-weight:bold; color:#0073aa;">' . esc_html__( 'Assinatura', 'dps-agenda-addon' ) . '</span> ';
        }
        $service_ids = get_post_meta( $appt->ID, 'appointment_services', true );
        if ( is_array( $service_ids ) && ! empty( $service_ids ) ) {
            // Link com ícone para abrir modal de serviços (FASE 2)
            echo '<a href="#" class="dps-services-link" data-appt-id="' . esc_attr( $appt->ID ) . '" title="' . esc_attr__( 'Ver detalhes dos serviços', 'dps-agenda-addon' ) . '">';
            echo esc_html__( 'Ver servicos', 'dps-agenda-addon' );
            echo '</a>';
        } else {
            echo '-';
        }
        echo '</td>';

        // Status (editable if admin)
        echo '<td data-label="' . esc_attr( ! empty( $column_labels['status'] ) ? __( 'Status', 'dps-agenda-addon' ) : '' ) . '">';
        $can_edit = is_user_logged_in() && current_user_can( 'manage_options' );

        // Define lista de status padrão
        $statuses = [
            'pendente'        => __( 'Pendente', 'dps-agenda-addon' ),
            'finalizado'      => __( 'Finalizado', 'dps-agenda-addon' ),
            'finalizado_pago' => __( 'Finalizado e pago', 'dps-agenda-addon' ),
            'cancelado'       => __( 'Cancelado', 'dps-agenda-addon' ),
        ];

        // Para agendamentos de assinatura, não há necessidade de usar o status "finalizado e pago"
        if ( $is_subscription ) {
            unset( $statuses['finalizado_pago'] );
        }

        if ( $can_edit ) {
            echo '<select class="dps-status-select" data-appt-id="' . esc_attr( $appt->ID ) . '" data-current-status="' . esc_attr( $display_status ) . '" data-appt-version="' . esc_attr( $appt_version ) . '" aria-label="' . esc_attr__( 'Alterar status do agendamento', 'dps-agenda-addon' ) . '">';
            foreach ( $statuses as $value => $label ) {
                echo '<option value="' . esc_attr( $value ) . '"' . selected( $display_status, $value, false ) . '>' . esc_html( $label ) . '</option>';
            }
            echo '</select>';
        } else {
            echo esc_html( isset( $statuses[ $display_status ] ) ? $statuses[ $display_status ] : $display_status );
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
        echo '<td data-label="' . esc_attr( ! empty( $column_labels['confirmation'] ) ? __( 'Confirmação', 'dps-agenda-addon' ) : '' ) . '">';

        // Obtém status de confirmação
        $confirmation_status = $this->get_confirmation_status( $appt->ID );

        // Renderiza badge de confirmação
        echo '<div class="dps-confirmation-wrapper">';
        echo $this->render_confirmation_badge( $confirmation_status );

        // CONF-2: Botões de confirmação (apenas para admins)
        if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
            echo '<div class="dps-confirmation-actions">';

            // Botão "Confirmado"
            echo '<button class="dps-confirmation-btn dps-confirmation-btn--confirmed" data-appt-id="' . esc_attr( $appt->ID ) . '" data-action="confirmed" title="' . esc_attr__( 'Marcar como confirmado', 'dps-agenda-addon' ) . '">OK</button>';

            // Botão "Não atendeu"
            echo '<button class="dps-confirmation-btn dps-confirmation-btn--no-answer" data-appt-id="' . esc_attr( $appt->ID ) . '" data-action="no_answer" title="' . esc_attr__( 'Nao atendeu', 'dps-agenda-addon' ) . '">NA</button>';

            // Botão "Cancelado/Desmarcou"
            echo '<button class="dps-confirmation-btn dps-confirmation-btn--denied" data-appt-id="' . esc_attr( $appt->ID ) . '" data-action="denied" title="' . esc_attr__( 'Cliente cancelou', 'dps-agenda-addon' ) . '"></button>';

            // Botão "Limpar" (reset para not_sent)
            if ( $confirmation_status !== 'not_sent' ) {
                echo '<button class="dps-confirmation-btn dps-confirmation-btn--clear" data-appt-id="' . esc_attr( $appt->ID ) . '" data-action="not_sent" title="' . esc_attr__( 'Limpar status', 'dps-agenda-addon' ) . '">CL</button>';
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
                echo '<a href="' . esc_url( $wa_url ) . '" target="_blank" class="dps-whatsapp-link" title="' . esc_attr__( 'Enviar mensagem de confirmacao via WhatsApp', 'dps-agenda-addon' ) . '">' . esc_html__( 'Enviar WhatsApp', 'dps-agenda-addon' ) . '</a>';
                echo '</div>';
            }
        }

        echo '</div>';
        echo '</td>';

        // Cobrança via WhatsApp
        echo '<td data-label="' . esc_attr( ! empty( $column_labels['charge'] ) ? __( 'Cobrança', 'dps-agenda-addon' ) : '' ) . '">';
        // Mostra link de cobrança apenas para atendimentos finalizados (não assinaturas)
        $sub_meta = get_post_meta( $appt->ID, 'subscription_id', true );
        if ( $status === 'finalizado' && empty( $sub_meta ) ) {
            // Verifica se houve erro ao gerar link de pagamento
            $payment_link_status = get_post_meta( $appt->ID, '_dps_payment_link_status', true );

            if ( $payment_link_status === 'error' ) {
                // Exibe aviso de erro com tooltip
                echo '<span style="color: #ef4444; font-size: 14px;" title="' . esc_attr__( 'Houve erro ao gerar o link de pagamento. Tente novamente ou verifique o log.', 'dps-agenda-addon' ) . '">' . esc_html__( 'Erro ao gerar link', 'dps-agenda-addon' ) . '</span>';

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
                    $links[]      = '<a href="' . esc_url( $wa_url ) . '" target="_blank" title="' . esc_attr__( 'Enviar cobranca via WhatsApp', 'dps-agenda-addon' ) . '">' . esc_html__( 'Cobrar', 'dps-agenda-addon' ) . '</a>';
                    if ( ! empty( $group_data['ids'] ) && (int) $appt->ID === (int) min( $group_data['ids'] ) ) {
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
                        $links[]     = '<a href="' . esc_url( $wa_url_group ) . '" target="_blank" title="' . esc_attr__( 'Enviar cobranca conjunta via WhatsApp', 'dps-agenda-addon' ) . '">' . esc_html__( 'Cobranca conjunta', 'dps-agenda-addon' ) . '</a>';
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
                echo '<button class="dps-quick-action-btn dps-quick-finish" data-appt-id="' . esc_attr( $appt->ID ) . '" data-action="finish" title="' . esc_attr__( 'Finalizar atendimento', 'dps-agenda-addon' ) . '">' . esc_html__( 'Finalizar', 'dps-agenda-addon' ) . '</button>';
                echo '<button class="dps-quick-action-btn dps-quick-paid" data-appt-id="' . esc_attr( $appt->ID ) . '" data-action="finish_and_paid" title="' . esc_attr__( 'Finalizar e marcar como pago', 'dps-agenda-addon' ) . '">' . esc_html__( 'Pago', 'dps-agenda-addon' ) . '</button>';
                echo '<button class="dps-quick-action-btn dps-quick-cancel" data-appt-id="' . esc_attr( $appt->ID ) . '" data-action="cancel" title="' . esc_attr__( 'Cancelar atendimento', 'dps-agenda-addon' ) . '"> ' . esc_html__( 'Cancelar', 'dps-agenda-addon' ) . '</button>';
            } elseif ( $status === 'finalizado' ) {
                echo '<button class="dps-quick-action-btn dps-quick-paid" data-appt-id="' . esc_attr( $appt->ID ) . '" data-action="mark_paid" title="' . esc_attr__( 'Marcar como pago', 'dps-agenda-addon' ) . '">' . esc_html__( 'Marcar pago', 'dps-agenda-addon' ) . '</button>';
            }

            echo '</div>';
        }

        // Botão de reagendamento rápido
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML seguro retornado pelo helper
        echo $this->render_reschedule_button( $appt->ID, $date, $time );

        // Indicador de histórico
        $history = get_post_meta( $appt->ID, '_dps_appointment_history', true );
        if ( is_array( $history ) && ! empty( $history ) ) {
            echo ' <span class="dps-history-indicator" data-appt-id="' . esc_attr( $appt->ID ) . '" title="' . esc_attr__( 'Ver historico', 'dps-agenda-addon' ) . '">LOG ' . count( $history ) . '</span>';
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
                'icon'  => 'NC',
            ],
            'sent'      => [
                'class' => 'status-confirmation-sent',
                'text'  => __( 'Enviado', 'dps-agenda-addon' ),
                'icon'  => 'EN',
            ],
            'confirmed' => [
                'class' => 'status-confirmation-confirmed',
                'text'  => __( 'Confirmado', 'dps-agenda-addon' ),
                'icon'  => 'OK',
            ],
            'denied'    => [
                'class' => 'status-confirmation-denied',
                'text'  => __( 'Cancelado', 'dps-agenda-addon' ),
                'icon'  => '',
            ],
            'no_answer' => [
                'class' => 'status-confirmation-no-answer',
                'text'  => __( 'Não atendeu', 'dps-agenda-addon' ),
                'icon'  => 'NA',
            ],
        ];

        $badge = isset( $badges[ $confirmation_status ] ) ? $badges[ $confirmation_status ] : $badges['not_sent'];

        return '<span class="dps-confirmation-badge ' . esc_attr( $badge['class'] ) . '" title="' . esc_attr( $badge['text'] ) . '">' . $badge['icon'] . ' ' . esc_html( $badge['text'] ) . '</span>';
    }

    /**
     * Renderiza botão do pet com dados para modal de perfil Pet + Tutor.
     *
     * @param WP_Post|null $pet_post    Post do pet.
     * @param WP_Post|null $client_post Post do tutor.
     * @return string
     */
    private function render_pet_profile_trigger( $pet_post, $client_post ) {
        $pet_name = $pet_post ? $pet_post->post_title : '';

        if ( ! $pet_post ) {
            return '<span class="dps-pet-name-text">' . esc_html( $pet_name ?: '—' ) . '</span>';
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
            __( 'Abrir perfil rápido de %1$s e %2$s', 'dps-agenda-addon' ),
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
        $button .= ' title="' . esc_attr__( 'Ver perfil rápido do pet e tutor', 'dps-agenda-addon' ) . '"';
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

        $html  = '<td class="dps-agenda-cell dps-agenda-cell--logistics" data-label="' . esc_attr__( 'Logística', 'dps-agenda-addon' ) . '">';
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
     * Prepara os dados de uma linha operacional sem alterar metadados durante o render.
     *
     * @param WP_Post $appt Agendamento.
     * @return array
     */
    private function get_operational_appointment_data( $appt ) {
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

        $sub_id_meta      = get_post_meta( $appt->ID, 'subscription_id', true );
        $is_subscription  = ! empty( $sub_id_meta );
        $display_status   = ( $is_subscription && 'finalizado_pago' === $status ) ? 'finalizado' : $status;
        $appt_version     = max( 1, intval( get_post_meta( $appt->ID, '_dps_appointment_version', true ) ) );
        $is_late          = $this->is_appointment_late( $date, $time, $display_status );
        $service_ids      = get_post_meta( $appt->ID, 'appointment_services', true );
        $service_count    = is_array( $service_ids ) ? count( $service_ids ) : ( ! empty( $service_ids ) ? 1 : 0 );
        $service_label    = $service_count > 0 ? sprintf( _n( '%d serviço', '%d serviços', $service_count, 'dps-agenda-addon' ), $service_count ) : __( 'Sem serviços', 'dps-agenda-addon' );
        $total_val        = (float) get_post_meta( $appt->ID, 'appointment_total_value', true );
        $total_fmt        = number_format_i18n( $total_val, 2 );
        $notes            = get_post_meta( $appt->ID, 'appointment_notes', true );
        $taxidog          = get_post_meta( $appt->ID, 'appointment_taxidog', true );
        $client_name      = $client_post ? $client_post->post_title : '';
        $pet_name         = $pet_post ? $pet_post->post_title : '';
        $client_phone     = $client_post ? get_post_meta( $client_post->ID, 'client_phone', true ) : '';
        $confirmation     = $this->get_confirmation_status( $appt->ID );
        $checkin_data     = DPS_Agenda_Checkin_Service::get_checkin( $appt->ID );
        $checkout_data    = DPS_Agenda_Checkin_Service::get_checkout( $appt->ID );
        $has_checkin      = (bool) $checkin_data;
        $has_checkout     = (bool) $checkout_data;
        $progress         = DPS_Agenda_Checklist_Service::get_progress( $appt->ID );
        $rework_count     = DPS_Agenda_Checklist_Service::count_reworks( $appt->ID );
        $summary          = DPS_Agenda_Checkin_Service::get_safety_summary( $appt->ID );
        $client_address   = DPS_Agenda_GPS_Helper::get_client_address( $appt->ID );
        $route_url        = DPS_Agenda_GPS_Helper::get_route_url( $appt->ID );
        $map_url          = $client_address ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode( $client_address ) : '';
        $history          = get_post_meta( $appt->ID, '_dps_appointment_history', true );
        $history          = is_array( $history ) ? array_slice( array_reverse( $history ), 0, 3 ) : [];

        $stage_label = __( 'A confirmar', 'dps-agenda-addon' );
        $stage_class = 'confirm';
        if ( 'cancelado' === $display_status ) {
            $stage_label = __( 'Cancelado', 'dps-agenda-addon' );
            $stage_class = 'danger';
        } elseif ( in_array( $display_status, [ 'finalizado', 'finalizado_pago' ], true ) ) {
            $stage_label = 'finalizado_pago' === $display_status ? __( 'Pago', 'dps-agenda-addon' ) : __( 'Finalizado', 'dps-agenda-addon' );
            $stage_class = 'done';
        } elseif ( 'confirmed' !== $confirmation ) {
            $stage_label = __( 'A confirmar', 'dps-agenda-addon' );
            $stage_class = 'confirm';
        } elseif ( ! $has_checkin ) {
            $stage_label = __( 'Check-in', 'dps-agenda-addon' );
            $stage_class = 'ready';
        } elseif ( ! $has_checkout ) {
            $stage_label = __( 'Em atendimento', 'dps-agenda-addon' );
            $stage_class = 'service';
        } else {
            $stage_label = __( 'Pronto para finalizar', 'dps-agenda-addon' );
            $stage_class = 'ready';
        }

        $status_config = [
            'pendente'        => [ 'label' => __( 'Pendente', 'dps-agenda-addon' ), 'class' => 'pending' ],
            'finalizado'      => [ 'label' => __( 'Finalizado', 'dps-agenda-addon' ), 'class' => 'finished' ],
            'finalizado_pago' => [ 'label' => __( 'Pago', 'dps-agenda-addon' ), 'class' => 'paid' ],
            'cancelado'       => [ 'label' => __( 'Cancelado', 'dps-agenda-addon' ), 'class' => 'cancelled' ],
        ];
        if ( $is_subscription ) {
            unset( $status_config['finalizado_pago'] );
        }
        $current_status = isset( $status_config[ $display_status ] ) ? $status_config[ $display_status ] : $status_config['pendente'];

        return [
            'id'              => $appt->ID,
            'date'            => $date,
            'time'            => $time,
            'client_post'     => $client_post,
            'pet_post'        => $pet_post,
            'client_name'     => $client_name,
            'pet_name'        => $pet_name,
            'client_phone'    => $client_phone,
            'status'          => $display_status,
            'status_config'   => $status_config,
            'current_status'  => $current_status,
            'version'         => $appt_version,
            'is_late'         => $is_late,
            'is_subscription' => $is_subscription,
            'service_count'   => $service_count,
            'service_label'   => $service_label,
            'total_value'     => $total_val,
            'total_fmt'       => $total_fmt,
            'notes'           => $notes,
            'taxidog'         => $taxidog,
            'confirmation'    => $confirmation,
            'has_checkin'     => $has_checkin,
            'has_checkout'    => $has_checkout,
            'checkin_data'    => $checkin_data,
            'checkout_data'   => $checkout_data,
            'progress'        => $progress,
            'rework_count'    => $rework_count,
            'summary'         => $summary,
            'address'         => $client_address,
            'route_url'       => $route_url,
            'map_url'         => $map_url,
            'history'         => $history,
            'stage_label'     => $stage_label,
            'stage_class'     => $stage_class,
        ];
    }

    /**
     * Renderiza atributos de dados usados pelo inspetor operacional.
     *
     * @param array $data Dados preparados do atendimento.
     * @return string
     */
    private function render_operational_data_attrs( $data ) {
        $attrs = [
            'data-appt-id'       => $data['id'],
            'data-dps-pet'       => $data['pet_name'] ? $data['pet_name'] : __( 'Pet não definido', 'dps-agenda-addon' ),
            'data-dps-tutor'     => $data['client_name'] ? $data['client_name'] : __( 'Tutor não definido', 'dps-agenda-addon' ),
            'data-dps-date'      => $data['date'],
            'data-dps-time'      => $data['time'],
            'data-dps-stage'     => $data['stage_label'],
            'data-dps-service'   => $data['service_label'],
            'data-dps-payment'   => $this->get_operational_payment_label( $data ),
            'data-dps-logistics' => $data['address'] ? $data['address'] : ( $data['taxidog'] ? __( 'TaxiDog solicitado', 'dps-agenda-addon' ) : __( 'Sem endereço para logística', 'dps-agenda-addon' ) ),
            'data-dps-notes'     => $data['notes'] ? wp_strip_all_tags( $data['notes'] ) : __( 'Sem observações registradas.', 'dps-agenda-addon' ),
            'data-dps-progress'  => $data['progress'],
            'data-dps-taxidog'   => $data['taxidog'] ? '1' : '0',
            'data-dps-late'      => $data['is_late'] ? '1' : '0',
        ];

        $html = '';
        foreach ( $attrs as $name => $value ) {
            $html .= ' ' . $name . '="' . esc_attr( $value ) . '"';
        }

        return $html;
    }

    /**
     * Retorna o label financeiro operacional do atendimento.
     *
     * @param array $data Dados preparados do atendimento.
     * @return string
     */
    private function get_operational_payment_label( $data ) {
        if ( 'finalizado_pago' === $data['status'] ) {
            return __( 'Pago', 'dps-agenda-addon' );
        }

        if ( 'cancelado' === $data['status'] ) {
            return __( 'Sem cobrança', 'dps-agenda-addon' );
        }

        if ( 'finalizado' === $data['status'] && ! $data['is_subscription'] ) {
            return sprintf( __( 'Cobrar R$ %s', 'dps-agenda-addon' ), $data['total_fmt'] );
        }

        return sprintf( __( 'Em aberto R$ %s', 'dps-agenda-addon' ), $data['total_fmt'] );
    }

    /**
     * Renderiza o bloco de pagamento reutilizado pela linha canônica.
     *
     * @param array $data Dados preparados do atendimento.
     * @return string
     */
    private function render_operational_payment_content( $data ) {
        $label = $this->get_operational_payment_label( $data );

        if ( 'finalizado' === $data['status'] && ! $data['is_subscription'] ) {
            return '<span class="dps-payment-status dps-payment-status--pending">' . esc_html( $label ) . '</span>';
        }

        $class = 'pending';
        if ( 'finalizado_pago' === $data['status'] ) {
            $class = 'paid';
        } elseif ( 'cancelado' === $data['status'] ) {
            $class = 'cancelled';
        }

        return '<span class="dps-payment-status dps-payment-status--' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span>';
    }

    /**
     * Renderiza a ação primária da linha canônica.
     *
     * @param array $data Dados preparados do atendimento.
     * @return string
     */
    private function render_operational_payment_action( $data ) {
        $payment_link = get_post_meta( $data['id'], 'dps_payment_link', true );
        $default_link = 'https://link.mercadopago.com.br/desipetshower';
        $link_to_use  = $payment_link ? $payment_link : $default_link;
        $message      = sprintf(
            __( 'Olá %1$s! O atendimento do pet %2$s foi finalizado. Valor: R$ %3$s. Link para pagamento: %4$s', 'dps-agenda-addon' ),
            $data['client_name'],
            $data['pet_name'],
            $data['total_fmt'],
            $link_to_use
        );

        $html  = '<button type="button" class="dps-payment-popup-btn dps-agenda-primary-action dps-agenda-primary-action--payment" data-appt-id="' . esc_attr( $data['id'] ) . '"';
        $html .= ' data-payment-link="' . esc_attr( $link_to_use ) . '"';
        $html .= ' data-client-phone="' . esc_attr( $data['client_phone'] ) . '"';
        $html .= ' data-whatsapp-msg="' . esc_attr( $message ) . '"';
        $html .= ' data-client-name="' . esc_attr( $data['client_name'] ) . '"';
        $html .= ' data-pet-name="' . esc_attr( $data['pet_name'] ) . '"';
        $html .= ' data-total-value="' . esc_attr( $data['total_fmt'] ) . '">';
        $html .= esc_html__( 'Cobrar cliente', 'dps-agenda-addon' );
        $html .= '</button>';

        return $html;
    }

    private function render_operational_primary_action( $data ) {
        if ( 'cancelado' === $data['status'] ) {
            return $this->render_agenda_reschedule_cta( $data['id'], $data['date'], $data['time'] );
        }

        if ( 'confirmed' !== $data['confirmation'] ) {
            return '<button type="button" class="dps-agenda-primary-action dps-agenda-primary-action--confirm dps-agenda-primary-confirm" data-appt-id="' . esc_attr( $data['id'] ) . '">' . esc_html__( 'Confirmar', 'dps-agenda-addon' ) . '</button>';
        }

        if ( ! $data['has_checkin'] ) {
            return '<button type="button" class="dps-agenda-primary-action dps-operation-action-btn dps-operation-action-btn--checkin" data-appt-id="' . esc_attr( $data['id'] ) . '" data-operation-focus="checkin" aria-haspopup="dialog">' . esc_html__( 'Iniciar check-in', 'dps-agenda-addon' ) . '</button>';
        }

        if ( ! in_array( $data['status'], [ 'finalizado', 'finalizado_pago' ], true ) ) {
            return '<button type="button" class="dps-agenda-primary-action dps-agenda-primary-action--finish dps-agenda-finalize-btn" data-appt-id="' . esc_attr( $data['id'] ) . '" data-appt-version="' . esc_attr( $data['version'] ) . '">' . esc_html__( 'Finalizar', 'dps-agenda-addon' ) . '</button>';
        }

        if ( 'finalizado' === $data['status'] && ! $data['is_subscription'] ) {
            return $this->render_operational_payment_action( $data );
        }

        return '<button type="button" class="dps-agenda-primary-action dps-operation-modal-btn dps-expand-panels-btn--done" data-appt-id="' . esc_attr( $data['id'] ) . '" data-operation-focus="checklist" aria-haspopup="dialog">' . esc_html__( 'Revisar operação', 'dps-agenda-addon' ) . '</button>';
    }

    /**
     * Renderiza a linha canônica da Agenda operacional.
     *
     * @param WP_Post $appt          Agendamento.
     * @param array   $column_labels Labels das colunas.
     * @return string
     */
    public function render_appointment_row_operational_signature( $appt, $column_labels ) {
        $data              = $this->get_operational_appointment_data( $appt );
        $row_classes       = [ 'dps-operational-row', 'status-' . $data['status'] ];
        $confirmation_map  = [
            'confirmed' => 'confirmed',
            'denied'    => 'cancelled',
            'not_sent'  => 'not-confirmed',
            'sent'      => 'not-confirmed',
            'no_answer' => 'not-confirmed',
        ];
        $confirmation_class = isset( $confirmation_map[ $data['confirmation'] ] ) ? $confirmation_map[ $data['confirmation'] ] : 'not-confirmed';

        if ( $data['is_late'] ) {
            $row_classes[] = 'is-late';
        }

        ob_start();

        echo '<tr class="' . esc_attr( implode( ' ', $row_classes ) ) . '"' . $this->render_operational_data_attrs( $data ) . '>';
        echo $this->render_agenda_time_cell( $data['time'], $data['is_late'] );
        echo $this->render_agenda_pet_cell( $data['pet_post'], $data['client_post'] );

        echo '<td class="dps-agenda-cell dps-agenda-cell--service dps-col-service" data-label="' . esc_attr__( 'Serviços', 'dps-agenda-addon' ) . '">';
        if ( $data['service_count'] > 0 ) {
            echo '<button type="button" class="dps-services-popup-btn dps-services-popup-btn--compact" data-appt-id="' . esc_attr( $data['id'] ) . '" title="' . esc_attr__( 'Ver serviços e observações', 'dps-agenda-addon' ) . '" aria-label="' . esc_attr__( 'Abrir modal de serviços do atendimento', 'dps-agenda-addon' ) . '" aria-haspopup="dialog" aria-expanded="false">';
            echo '<span class="dps-services-popup-btn__text">' . esc_html( $data['service_label'] ) . '</span>';
            echo '</button>';
        } else {
            echo '<span class="dps-no-services">' . esc_html( $data['service_label'] ) . '</span>';
        }
        echo '<span class="dps-operational-muted">' . esc_html( $data['notes'] ? wp_trim_words( $data['notes'], 8, '...' ) : __( 'Sem observações', 'dps-agenda-addon' ) ) . '</span>';
        echo '</td>';

        echo '<td class="dps-agenda-cell dps-agenda-cell--stage" data-label="' . esc_attr__( 'Etapa', 'dps-agenda-addon' ) . '">';
        echo '<span class="dps-operational-stage dps-operational-stage--' . esc_attr( $data['stage_class'] ) . '">' . esc_html( $data['stage_label'] ) . '</span>';
        echo '<div class="dps-operational-selects">';
        echo '<select class="dps-confirmation-dropdown dps-dropdown--' . esc_attr( $confirmation_class ) . '" data-appt-id="' . esc_attr( $data['id'] ) . '" aria-label="' . esc_attr__( 'Status de confirmação', 'dps-agenda-addon' ) . '">';
        echo '<option value="confirmed"' . selected( $data['confirmation'], 'confirmed', false ) . '>' . esc_html__( 'Confirmado', 'dps-agenda-addon' ) . '</option>';
        echo '<option value="not_sent"' . selected( in_array( $data['confirmation'], [ 'not_sent', 'sent', 'no_answer' ], true ), true, false ) . '>' . esc_html__( 'Não confirmado', 'dps-agenda-addon' ) . '</option>';
        echo '<option value="denied"' . selected( $data['confirmation'], 'denied', false ) . '>' . esc_html__( 'Cancelado', 'dps-agenda-addon' ) . '</option>';
        echo '</select>';

        if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
            echo '<select class="dps-status-dropdown dps-dropdown--' . esc_attr( $data['current_status']['class'] ) . '" data-appt-id="' . esc_attr( $data['id'] ) . '" data-current-status="' . esc_attr( $data['status'] ) . '" data-appt-version="' . esc_attr( $data['version'] ) . '" aria-label="' . esc_attr__( 'Status do serviço', 'dps-agenda-addon' ) . '">';
            foreach ( $data['status_config'] as $value => $config ) {
                echo '<option value="' . esc_attr( $value ) . '"' . selected( $data['status'], $value, false ) . '>' . esc_html( $config['label'] ) . '</option>';
            }
            echo '</select>';
        } else {
            echo '<span class="dps-status-badge dps-status-badge--' . esc_attr( $data['current_status']['class'] ) . '">' . esc_html( $data['current_status']['label'] ) . '</span>';
        }
        echo '</div>';
        echo '</td>';

        echo '<td class="dps-agenda-cell dps-agenda-cell--payment" data-label="' . esc_attr__( 'Financeiro', 'dps-agenda-addon' ) . '">';
        echo $this->render_operational_payment_content( $data );
        echo '</td>';

        echo '<td class="dps-agenda-cell dps-agenda-cell--operational" data-label="' . esc_attr__( 'Operação', 'dps-agenda-addon' ) . '">';
        echo '<div class="dps-operational-stack">';
        echo '<div class="dps-operational-progress" aria-label="' . esc_attr__( 'Progresso do checklist', 'dps-agenda-addon' ) . '">';
        echo '<span>' . esc_html__( 'Checklist', 'dps-agenda-addon' ) . ' ' . esc_html( $data['progress'] ) . '%</span>';
        echo '<div class="dps-operational-progress__bar"><span style="width:' . esc_attr( $data['progress'] ) . '%"></span></div>';
        echo '</div>';
        echo '<div class="dps-operational-summary">';
        echo '<span class="dps-operational-inline-meta">' . esc_html( $data['has_checkin'] ? sprintf( __( 'Check-in %s', 'dps-agenda-addon' ), mysql2date( 'H:i', $data['checkin_data']['time'] ) ) : __( 'Check-in pendente', 'dps-agenda-addon' ) ) . '</span>';
        echo '<span class="dps-operational-inline-meta">' . esc_html( $data['has_checkout'] ? sprintf( __( 'Check-out %s', 'dps-agenda-addon' ), mysql2date( 'H:i', $data['checkout_data']['time'] ) ) : __( 'Check-out pendente', 'dps-agenda-addon' ) ) . '</span>';
        if ( $data['rework_count'] > 0 ) {
            echo '<span class="dps-operational-inline-meta">' . esc_html( sprintf( _n( '%d retrabalho', '%d retrabalhos', $data['rework_count'], 'dps-agenda-addon' ), $data['rework_count'] ) ) . '</span>';
        }
        echo '</div>';
        echo '</div>';
        echo '</td>';

        echo '<td class="dps-agenda-cell dps-agenda-cell--logistics" data-label="' . esc_attr__( 'Logística', 'dps-agenda-addon' ) . '">';
        echo '<div class="dps-agenda-logistics">';
        echo '<span class="dps-taxidog-label ' . ( $data['taxidog'] ? 'dps-taxidog-label--requested' : 'dps-taxidog-label--not-requested' ) . '">' . esc_html( $data['taxidog'] ? __( 'TaxiDog', 'dps-agenda-addon' ) : __( 'Sem TaxiDog', 'dps-agenda-addon' ) ) . '</span>';
        if ( $data['address'] ) {
            echo '<span class="dps-operational-inline-meta">' . esc_html__( 'Mapa e rota disponíveis', 'dps-agenda-addon' ) . '</span>';
            echo '<div class="dps-agenda-logistics__actions">';
            if ( $data['map_url'] ) {
                echo '<a href="' . esc_url( $data['map_url'] ) . '" target="_blank" rel="noopener noreferrer" class="dps-agenda-logistics-link dps-agenda-logistics-link--map">' . esc_html__( 'Mapa', 'dps-agenda-addon' ) . '</a>';
            }
            if ( $data['route_url'] ) {
                echo '<a href="' . esc_url( $data['route_url'] ) . '" target="_blank" rel="noopener noreferrer" class="dps-agenda-logistics-link dps-agenda-logistics-link--route">' . esc_html__( 'Rota', 'dps-agenda-addon' ) . '</a>';
            }
            echo '</div>';
        } else {
            echo '<span class="dps-agenda-logistics__empty">' . esc_html__( 'Sem logística', 'dps-agenda-addon' ) . '</span>';
        }
        echo '</div>';
        echo '</td>';

        echo '<td class="dps-agenda-cell dps-agenda-cell--actions" data-label="' . esc_attr__( 'Ações', 'dps-agenda-addon' ) . '">';
        echo '<div class="dps-agenda-row-actions dps-agenda-row-actions--canonical">';
        echo $this->render_operational_primary_action( $data );
        echo '<button type="button" class="dps-agenda-action-link dps-agenda-action-link--secondary dps-agenda-secondary-actions" data-appt-id="' . esc_attr( $data['id'] ) . '" aria-haspopup="dialog">' . esc_html__( 'Mais', 'dps-agenda-addon' ) . '</button>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';

        return ob_get_clean();
    }

    /**
     * Renderiza o card mobile equivalente a linha canônica.
     *
     * @param WP_Post $appt          Agendamento.
     * @param array   $column_labels Labels das colunas.
     * @return string
     */
    public function render_appointment_card_operational_signature( $appt, $column_labels ) {
        $data = $this->get_operational_appointment_data( $appt );

        ob_start();

        echo '<article class="dps-operational-card status-' . esc_attr( $data['status'] ) . ( $data['is_late'] ? ' is-late' : '' ) . '"' . $this->render_operational_data_attrs( $data ) . '>';
        echo '<header class="dps-operational-card__header">';
        echo '<div class="dps-agenda-time-block"><strong class="dps-agenda-time-block__value">' . esc_html( $data['time'] ) . '</strong>';
        if ( $data['is_late'] ) {
            echo '<span class="dps-agenda-time-block__meta">' . esc_html__( 'Atrasado', 'dps-agenda-addon' ) . '</span>';
        }
        echo '</div>';
        echo '<span class="dps-operational-stage dps-operational-card__stage dps-operational-stage--' . esc_attr( $data['stage_class'] ) . '">' . esc_html( $data['stage_label'] ) . '</span>';
        echo '</header>';

        echo '<div class="dps-operational-card__body">';
        echo '<div class="dps-agenda-pet-stack">';
        echo $this->render_pet_profile_trigger( $data['pet_post'], $data['client_post'] );
        if ( $data['client_name'] ) {
            echo '<p class="dps-agenda-pet-owner"><span class="dps-agenda-pet-owner__label">' . esc_html__( 'Tutor', 'dps-agenda-addon' ) . '</span><span class="dps-agenda-pet-owner__value">' . esc_html( $data['client_name'] ) . '</span></p>';
        }
        echo '</div>';
        echo '<div class="dps-operational-card__meta">';
        echo '<span class="dps-operational-card__meta-item"><small>' . esc_html__( 'Serviços', 'dps-agenda-addon' ) . '</small><strong>' . esc_html( $data['service_label'] ) . '</strong></span>';
        echo '<span class="dps-operational-card__meta-item"><small>' . esc_html__( 'Financeiro', 'dps-agenda-addon' ) . '</small><strong>' . esc_html( $this->get_operational_payment_label( $data ) ) . '</strong></span>';
        echo '<span class="dps-operational-card__meta-item"><small>' . esc_html__( 'Logística', 'dps-agenda-addon' ) . '</small><strong>' . esc_html( $data['taxidog'] ? __( 'TaxiDog solicitado', 'dps-agenda-addon' ) : __( 'Sem TaxiDog', 'dps-agenda-addon' ) ) . '</strong></span>';
        echo '</div>';
        echo '<div class="dps-operational-progress"><span>' . esc_html__( 'Checklist', 'dps-agenda-addon' ) . ' ' . esc_html( $data['progress'] ) . '%</span><div class="dps-operational-progress__bar"><span style="width:' . esc_attr( $data['progress'] ) . '%"></span></div></div>';
        echo '</div>';

        echo '<footer class="dps-operational-card__actions">';
        echo $this->render_operational_primary_action( $data );
        echo '<button type="button" class="dps-operation-action-btn dps-operation-action-btn--edit" data-appt-id="' . esc_attr( $data['id'] ) . '" data-operation-focus="checklist" aria-haspopup="dialog">' . esc_html__( 'Operação', 'dps-agenda-addon' ) . '</button>';
        echo '<button type="button" class="dps-agenda-action-link dps-agenda-action-link--secondary dps-agenda-secondary-actions" data-appt-id="' . esc_attr( $data['id'] ) . '" aria-haspopup="dialog">' . esc_html__( 'Mais', 'dps-agenda-addon' ) . '</button>';
        echo '</footer>';
        echo '</article>';

        return ob_get_clean();
    }

    /**
     * Renderiza o inspetor contextual inicial.
     *
     * @param WP_Post|null $appt Agendamento selecionado.
     * @return string
     */
    public function render_operational_inspector_signature( $appt ) {
        if ( ! $appt instanceof WP_Post ) {
            return '<aside class="dps-operational-inspector" aria-live="polite"><div class="dps-operational-inspector__empty">' . esc_html__( 'Selecione um atendimento para revisar o fluxo.', 'dps-agenda-addon' ) . '</div></aside>';
        }

        $data = $this->get_operational_appointment_data( $appt );

        ob_start();

        echo '<aside class="dps-operational-inspector" aria-live="polite">';
        echo '<header class="dps-operational-inspector__header">';
        echo '<span>' . esc_html__( 'Atendimento selecionado', 'dps-agenda-addon' ) . '</span>';
        echo '<h3 data-inspector-field="pet">' . esc_html( $data['pet_name'] ? $data['pet_name'] : __( 'Pet não definido', 'dps-agenda-addon' ) ) . '</h3>';
        echo '<p data-inspector-field="tutor">' . esc_html( $data['client_name'] ? $data['client_name'] : __( 'Tutor não definido', 'dps-agenda-addon' ) ) . '</p>';
        echo '</header>';
        echo '<div class="dps-operational-inspector__body">';
        echo '<section class="dps-operational-inspector__section"><span>' . esc_html__( 'Etapa', 'dps-agenda-addon' ) . '</span><strong data-inspector-field="stage">' . esc_html( $data['stage_label'] ) . '</strong></section>';
        echo '<section class="dps-operational-inspector__section"><span>' . esc_html__( 'Serviços', 'dps-agenda-addon' ) . '</span><strong data-inspector-field="service">' . esc_html( $data['service_label'] ) . '</strong></section>';
        echo '<section class="dps-operational-inspector__section"><span>' . esc_html__( 'Financeiro', 'dps-agenda-addon' ) . '</span><strong data-inspector-field="payment">' . esc_html( $this->get_operational_payment_label( $data ) ) . '</strong></section>';
        echo '<section class="dps-operational-inspector__section"><span>' . esc_html__( 'Logística', 'dps-agenda-addon' ) . '</span><strong data-inspector-field="logistics">' . esc_html( $data['address'] ? $data['address'] : ( $data['taxidog'] ? __( 'TaxiDog solicitado', 'dps-agenda-addon' ) : __( 'Sem endereço para logística', 'dps-agenda-addon' ) ) ) . '</strong></section>';
        echo '<section class="dps-operational-inspector__section"><span>' . esc_html__( 'Checklist', 'dps-agenda-addon' ) . '</span><div class="dps-operational-progress"><span data-inspector-field="progress">' . esc_html( $data['progress'] ) . '%</span><div class="dps-operational-progress__bar"><span data-inspector-progress-bar style="width:' . esc_attr( $data['progress'] ) . '%"></span></div></div></section>';
        echo '<section class="dps-operational-inspector__section"><span>' . esc_html__( 'Observações', 'dps-agenda-addon' ) . '</span><p data-inspector-field="notes">' . esc_html( $data['notes'] ? $data['notes'] : __( 'Sem observações registradas.', 'dps-agenda-addon' ) ) . '</p></section>';
        echo '<section class="dps-operational-inspector__section"><span>' . esc_html__( 'Últimos logs', 'dps-agenda-addon' ) . '</span>';
        if ( ! empty( $data['history'] ) ) {
            echo '<ul class="dps-operational-log">';
            foreach ( $data['history'] as $entry ) {
                $label = isset( $entry['action'] ) ? $entry['action'] : __( 'Registro', 'dps-agenda-addon' );
                $time  = isset( $entry['timestamp'] ) ? $entry['timestamp'] : '';
                echo '<li>' . esc_html( trim( $label . ' ' . $time ) ) . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p class="dps-operational-muted">' . esc_html__( 'Sem histórico operacional ainda.', 'dps-agenda-addon' ) . '</p>';
        }
        echo '</section>';
        echo '</div>';
        echo '</aside>';

        return ob_get_clean();
    }
}
