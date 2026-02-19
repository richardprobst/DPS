<?php
/**
 * History Section Renderer — renderização da seção de histórico de atendimentos.
 *
 * Extraído de class-dps-base-frontend.php para Single Responsibility.
 * Responsável por renderizar toda a seção "Histórico de Atendimentos" no frontend,
 * incluindo métricas, linha do tempo, filtros e tabela de dados.
 *
 * @package DesiPetShower
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DPS_History_Section_Renderer {

    /**
     * Renderiza a seção de histórico de atendimentos.
     *
     * Requer que o usuário tenha permissão de gerenciamento.
     *
     * @since 2.0.0
     * @return string HTML da seção de histórico.
     */
    public static function render() {
        if ( ! self::can_manage() ) {
            return '';
        }

        $history_data   = DPS_Base_Frontend::get_history_appointments_data();
        $appointments   = $history_data['appointments'];
        $base_url       = DPS_URL_Builder::safe_get_permalink();
        $timeline_data   = DPS_Base_Frontend::get_history_timeline_groups();
        $timeline_groups = $timeline_data['groups'];
        $timeline_counts = $timeline_data['counts'];

        $clients = DPS_Query_Helper::get_all_posts_by_type( 'dps_cliente' );
        $client_options = [];
        foreach ( $clients as $client ) {
            $client_options[ $client->ID ] = $client->post_title;
        }

        // Coletar lista de pets para o filtro (limitado para performance)
        $pets_limit = (int) apply_filters( 'dps_history_pets_filter_limit', 200 );
        $pets = get_posts( [
            'post_type'      => 'dps_pet',
            'posts_per_page' => $pets_limit,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );
        $pet_options = [];
        foreach ( $pets as $pet ) {
            $pet_options[ $pet->ID ] = $pet->post_title;
        }

        $status_labels = [
            'pendente'        => __( 'Pendente', 'desi-pet-shower' ),
            'finalizado'      => __( 'Finalizado', 'desi-pet-shower' ),
            'finalizado_pago' => __( 'Finalizado e pago', 'desi-pet-shower' ),
            'cancelado'       => __( 'Cancelado', 'desi-pet-shower' ),
        ];
        $status_filters = [
            'finalizado'      => __( 'Finalizado', 'desi-pet-shower' ),
            'finalizado_pago' => __( 'Finalizado e pago', 'desi-pet-shower' ),
            'cancelado'       => __( 'Cancelado', 'desi-pet-shower' ),
        ];

        $total_count     = $history_data['total_count'];
        $total_amount    = $history_data['total_amount'];
        $pending_count   = $history_data['pending_count'];
        $pending_amount  = $history_data['pending_amount'];
        $paid_count      = $history_data['paid_count'];
        $paid_amount     = $history_data['paid_amount'];
        $cancelled_count = $history_data['cancelled_count'];
        $summary_value   = number_format_i18n( $total_amount, 2 );

        ob_start();
        echo '<div class="dps-section" id="dps-section-historico">';

        // Cabeçalho da seção
        echo '<div class="dps-history-header">';
        echo '<h2 class="dps-section-title"><span class="dps-section-title__icon">📚</span>' . esc_html__( 'Histórico de Atendimentos', 'desi-pet-shower' ) . '</h2>';
        echo '<p class="dps-section-header__subtitle">' . esc_html__( 'Visualize, filtre e exporte todos os atendimentos registrados no sistema.', 'desi-pet-shower' ) . '</p>';
        echo '</div>';

        // Cards de métricas - grid de 5 cards
        echo '<div class="dps-history-metrics">';
        echo '<div class="dps-history-cards dps-history-cards--five">';

        // Card: Atendimentos Hoje
        echo '<div class="dps-history-card dps-history-card--today">';
        echo '<span class="dps-history-card__icon" aria-hidden="true">📅</span>';
        echo '<div class="dps-history-card__content">';
        echo '<strong class="dps-history-card__value">' . esc_html( number_format_i18n( $timeline_counts['today'] ?? 0 ) ) . '</strong>';
        echo '<span class="dps-history-card__label">' . esc_html__( 'Hoje', 'desi-pet-shower' ) . '</span>';
        echo '</div>';
        echo '</div>';

        // Card: Agendamentos Futuros
        echo '<div class="dps-history-card dps-history-card--upcoming">';
        echo '<span class="dps-history-card__icon" aria-hidden="true">🗓️</span>';
        echo '<div class="dps-history-card__content">';
        echo '<strong class="dps-history-card__value">' . esc_html( number_format_i18n( $timeline_counts['upcoming'] ?? 0 ) ) . '</strong>';
        echo '<span class="dps-history-card__label">' . esc_html__( 'Futuros', 'desi-pet-shower' ) . '</span>';
        echo '</div>';
        echo '</div>';

        // Card: Recebido (Pago)
        echo '<div class="dps-history-card dps-history-card--paid">';
        echo '<span class="dps-history-card__icon" aria-hidden="true">✓</span>';
        echo '<div class="dps-history-card__content">';
        echo '<strong class="dps-history-card__value">R$ ' . esc_html( number_format_i18n( $paid_amount, 2 ) ) . '</strong>';
        echo '<span class="dps-history-card__label">' . sprintf( esc_html__( '%s pagos', 'desi-pet-shower' ), number_format_i18n( $paid_count ) ) . '</span>';
        echo '</div>';
        echo '</div>';

        // Card: A Receber (Pendente)
        echo '<div class="dps-history-card dps-history-card--pending">';
        echo '<span class="dps-history-card__icon" aria-hidden="true">⏳</span>';
        echo '<div class="dps-history-card__content">';
        echo '<strong class="dps-history-card__value">R$ ' . esc_html( number_format_i18n( $pending_amount, 2 ) ) . '</strong>';
        echo '<span class="dps-history-card__label">' . sprintf( esc_html__( '%s pendentes', 'desi-pet-shower' ), number_format_i18n( $pending_count ) ) . '</span>';
        echo '</div>';
        echo '</div>';

        // Card: Receita Total
        echo '<div class="dps-history-card dps-history-card--total">';
        echo '<span class="dps-history-card__icon" aria-hidden="true">💰</span>';
        echo '<div class="dps-history-card__content">';
        echo '<strong class="dps-history-card__value">R$ ' . esc_html( $summary_value ) . '</strong>';
        echo '<span class="dps-history-card__label">' . esc_html__( 'Receita total', 'desi-pet-shower' ) . '</span>';
        echo '</div>';
        echo '</div>';

        echo '</div>'; // .dps-history-cards
        echo '</div>'; // .dps-history-metrics

        echo '<div class="dps-section-grid">';

        $timeline_status_selector = function( $appt_id, $status ) use ( $status_labels ) {
            return DPS_Base_Frontend::render_status_selector( $appt_id, $status, $status_labels, false );
        };

        $history_charge_renderer = function( $appt_id ) {
            return DPS_Base_Frontend::build_charge_html( $appt_id, 'historico' );
        };

        // Seção: Linha do tempo de agendamentos
        echo '<div class="dps-surface dps-surface--info dps-history-timeline-section">';
        echo '<div class="dps-history-timeline-header">';
        echo '<h3 class="dps-history-timeline-title"><span class="dps-section-title__icon">📆</span>' . esc_html__( 'Visão Geral dos Agendamentos', 'desi-pet-shower' ) . '</h3>';
        echo '<p class="dps-history-timeline-description">' . esc_html__( 'Agendamentos organizados por data: hoje, futuros e passados.', 'desi-pet-shower' ) . '</p>';
        echo '</div>';
        dps_get_template(
            'appointments-list.php',
            [
                'groups'           => $timeline_groups,
                'base_url'         => $base_url,
                'visitor_only'     => false,
                'status_labels'    => $status_labels,
                'status_selector'  => $timeline_status_selector,
                'charge_renderer'  => $history_charge_renderer,
                'list_title'       => '',
            ]
        );
        echo '</div>';

        // Toolbar de filtros reorganizada
        echo '<div class="dps-surface dps-surface--neutral">';
        echo '<div class="dps-history-toolbar">';
        
        // Cabeçalho da seção de tabela com título e ações
        echo '<div class="dps-history-toolbar__header">';
        echo '<h3 class="dps-history-toolbar__title"><span class="dps-section-title__icon">📋</span>' . esc_html__( 'Tabela de Atendimentos Finalizados', 'desi-pet-shower' ) . '</h3>';
        echo '<div class="dps-history-toolbar__actions">';
        echo '<button type="button" class="dps-submit-btn dps-submit-btn--secondary" id="dps-history-clear">';
        echo '<span aria-hidden="true">🔄</span> ' . esc_html__( 'Limpar filtros', 'desi-pet-shower' );
        echo '</button>';
        echo '<button type="button" class="dps-submit-btn" id="dps-history-export">';
        echo '<span aria-hidden="true">📥</span> ' . esc_html__( 'Exportar CSV', 'desi-pet-shower' );
        echo '</button>';
        echo '</div>';
        echo '</div>';

        // Resumo dinâmico dos filtros aplicados (atualizado via JavaScript)
        $summary_attrs = sprintf(
            'data-total-records="%s" data-total-value="%s" data-pending-count="%s" data-pending-amount="%s"',
            esc_attr( $total_count ),
            esc_attr( $total_amount ),
            esc_attr( $pending_count ),
            esc_attr( $pending_amount )
        );
        echo '<div id="dps-history-summary" class="dps-history-summary" ' . $summary_attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        if ( $total_count ) {
            echo '<div class="dps-history-summary__content">';
            echo '<span class="dps-history-summary__count">';
            echo '<strong>' . esc_html( number_format_i18n( $total_count ) ) . '</strong> ';
            echo esc_html__( 'atendimentos', 'desi-pet-shower' );
            echo '</span>';
            echo '<span class="dps-history-summary__separator">•</span>';
            echo '<span class="dps-history-summary__total"><strong>R$ ' . esc_html( $summary_value ) . '</strong></span>';
            if ( $pending_count > 0 ) {
                echo '<span class="dps-history-summary__separator">•</span>';
                echo '<span class="dps-history-summary__pending">';
                printf(
                    /* translators: %s: number of pending appointments */
                    esc_html__( '%s pendente(s) de pagamento', 'desi-pet-shower' ),
                    '<strong>' . esc_html( number_format_i18n( $pending_count ) ) . '</strong>'
                );
                echo '</span>';
            }
            echo '</div>';
            echo '<div class="dps-history-summary__filtered" style="display: none;">';
            echo '<span class="dps-history-summary__filtered-badge">🔍 ' . esc_html__( 'Filtrado', 'desi-pet-shower' ) . '</span>';
            echo '</div>';
        } else {
            echo '<strong>' . esc_html__( 'Nenhum atendimento registrado.', 'desi-pet-shower' ) . '</strong>';
        }
        echo '</div>';

        // Botões de período rápido
        echo '<div class="dps-history-quick-filters">';
        echo '<span class="dps-history-quick-label">' . esc_html__( 'Período:', 'desi-pet-shower' ) . '</span>';
        echo '<button type="button" class="button button-small dps-history-quick-btn" data-period="today">';
        echo esc_html__( 'Hoje', 'desi-pet-shower' ) . '</button>';
        echo '<button type="button" class="button button-small dps-history-quick-btn" data-period="7days">';
        echo esc_html__( '7 dias', 'desi-pet-shower' ) . '</button>';
        echo '<button type="button" class="button button-small dps-history-quick-btn" data-period="30days">';
        echo esc_html__( '30 dias', 'desi-pet-shower' ) . '</button>';
        echo '<button type="button" class="button button-small dps-history-quick-btn" data-period="month">';
        echo esc_html__( 'Este mês', 'desi-pet-shower' ) . '</button>';
        echo '</div>';

        // Filtros organizados em grid
        echo '<div class="dps-history-filters">';
        
        // Campo de busca (largura total)
        echo '<div class="dps-history-filter dps-history-filter--search">';
        echo '<label for="dps-history-search">' . esc_html__( 'Buscar', 'desi-pet-shower' ) . '</label>';
        echo '<input type="search" id="dps-history-search" placeholder="' . esc_attr__( 'Filtrar por cliente, pet ou serviço...', 'desi-pet-shower' ) . '">';
        echo '</div>';
        
        // Linha com selects lado a lado
        echo '<div class="dps-history-filters__row">';
        
        echo '<div class="dps-history-filter">';
        echo '<label for="dps-history-client">' . esc_html__( 'Cliente', 'desi-pet-shower' ) . '</label>';
        echo '<select id="dps-history-client"><option value="">' . esc_html__( 'Todos', 'desi-pet-shower' ) . '</option>';
        foreach ( $client_options as $id => $name ) {
            echo '<option value="' . esc_attr( $id ) . '">' . esc_html( $name ) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        
        echo '<div class="dps-history-filter">';
        echo '<label for="dps-history-pet">' . esc_html__( 'Pet', 'desi-pet-shower' ) . '</label>';
        echo '<select id="dps-history-pet"><option value="">' . esc_html__( 'Todos', 'desi-pet-shower' ) . '</option>';
        foreach ( $pet_options as $id => $name ) {
            echo '<option value="' . esc_attr( $id ) . '">' . esc_html( $name ) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        
        echo '<div class="dps-history-filter">';
        echo '<label for="dps-history-status">' . esc_html__( 'Status', 'desi-pet-shower' ) . '</label>';
        echo '<select id="dps-history-status"><option value="">' . esc_html__( 'Todos', 'desi-pet-shower' ) . '</option>';
        foreach ( $status_filters as $key => $label ) {
            echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        
        echo '</div>'; // .dps-history-filters__row
        
        // Linha com datas
        echo '<div class="dps-history-filters__row">';
        
        echo '<div class="dps-history-filter">';
        echo '<label for="dps-history-start">' . esc_html__( 'Data inicial', 'desi-pet-shower' ) . '</label>';
        echo '<input type="date" id="dps-history-start">';
        echo '</div>';
        
        echo '<div class="dps-history-filter">';
        echo '<label for="dps-history-end">' . esc_html__( 'Data final', 'desi-pet-shower' ) . '</label>';
        echo '<input type="date" id="dps-history-end">';
        echo '</div>';
        
        echo '<div class="dps-history-filter dps-history-filter--checkbox">';
        echo '<label class="dps-checkbox-label">';
        echo '<input type="checkbox" id="dps-history-pending">';
        echo '<span>' . esc_html__( 'Somente pendentes de pagamento', 'desi-pet-shower' ) . '</span>';
        echo '</label>';
        echo '</div>';
        
        echo '</div>'; // .dps-history-filters__row
        echo '</div>'; // .dps-history-filters
        
        echo '</div>'; // .dps-history-toolbar

        if ( $appointments ) {
            echo '<div class="dps-table-wrapper">';
            echo '<table class="dps-table dps-table-sortable" id="dps-history-table"><thead><tr>';
            echo '<th class="dps-sortable" data-sort="date">' . esc_html__( 'Data', 'desi-pet-shower' ) . ' <span class="dps-sort-icon">⇅</span></th>';
            echo '<th class="dps-sortable" data-sort="time">' . esc_html__( 'Horário', 'desi-pet-shower' ) . ' <span class="dps-sort-icon">⇅</span></th>';
            echo '<th class="dps-sortable" data-sort="client">' . esc_html__( 'Cliente', 'desi-pet-shower' ) . ' <span class="dps-sort-icon">⇅</span></th>';
            echo '<th>' . esc_html__( 'Pets', 'desi-pet-shower' ) . '</th>';
            echo '<th class="hide-mobile">' . esc_html__( 'Serviços', 'desi-pet-shower' ) . '</th>';
            echo '<th class="dps-sortable" data-sort="value">' . esc_html__( 'Valor', 'desi-pet-shower' ) . ' <span class="dps-sort-icon">⇅</span></th>';
            echo '<th class="dps-sortable" data-sort="status">' . esc_html__( 'Status', 'desi-pet-shower' ) . ' <span class="dps-sort-icon">⇅</span></th>';
            echo '<th class="hide-mobile">' . esc_html__( 'Operacional', 'desi-pet-shower' ) . '</th>';
            echo '<th class="hide-mobile">' . esc_html__( 'Cobrança', 'desi-pet-shower' ) . '</th>';
            echo '<th>' . esc_html__( 'Ações', 'desi-pet-shower' ) . '</th>';
            echo '</tr></thead><tbody>';
            $clients_cache   = [];
            $pets_cache      = [];
            $services_cache  = [];

            foreach ( $appointments as $appt ) {
                $date      = get_post_meta( $appt->ID, 'appointment_date', true );
                $time      = get_post_meta( $appt->ID, 'appointment_time', true );
                $client_id = get_post_meta( $appt->ID, 'appointment_client_id', true );

                if ( $client_id && ! array_key_exists( $client_id, $clients_cache ) ) {
                    $clients_cache[ $client_id ] = get_post( $client_id );
                }
                $client_post = $client_id ? ( $clients_cache[ $client_id ] ?? null ) : null;
                $client_name = $client_post ? $client_post->post_title : '-';

                $status_meta  = get_post_meta( $appt->ID, 'appointment_status', true );
                $status_key   = DPS_Base_Frontend::normalize_status_key( $status_meta );
                $status_label = DPS_Base_Frontend::get_status_label( $status_meta );
                $pet_display  = '-';
                $pet_ids_attr = '';
                $group_data   = DPS_Base_Frontend::get_multi_pet_charge_data( $appt->ID );
                if ( $group_data ) {
                    $pet_display = implode( ', ', $group_data['pet_names'] );
                    // Para múltiplos pets, armazena IDs separados por vírgula
                    $pet_ids_attr = isset( $group_data['pet_ids'] ) ? implode( ',', $group_data['pet_ids'] ) : '';
                } else {
                    $pet_id = get_post_meta( $appt->ID, 'appointment_pet_id', true );
                    if ( $pet_id && ! array_key_exists( $pet_id, $pets_cache ) ) {
                        $pets_cache[ $pet_id ] = get_post( $pet_id );
                    }
                    if ( $pet_id && isset( $pets_cache[ $pet_id ] ) ) {
                        $pet_display = $pets_cache[ $pet_id ]->post_title;
                        $pet_ids_attr = $pet_id;
                    }
                }

                $services      = get_post_meta( $appt->ID, 'appointment_services', true );
                $services_text = '-';
                if ( is_array( $services ) && ! empty( $services ) ) {
                    $names = [];
                    foreach ( $services as $srv_id ) {
                        if ( ! array_key_exists( $srv_id, $services_cache ) ) {
                            $services_cache[ $srv_id ] = get_post( $srv_id );
                        }
                        if ( isset( $services_cache[ $srv_id ] ) ) {
                            $names[] = $services_cache[ $srv_id ]->post_title;
                        }
                    }
                    if ( $names ) {
                        $services_text = implode( ', ', $names );
                    }
                }

                $total_val = (float) get_post_meta( $appt->ID, 'appointment_total_value', true );
                $total_display = $total_val > 0 ? 'R$ ' . number_format_i18n( $total_val, 2 ) : '—';
                $paid_flag = ( 'finalizado' === $status_key ) ? '0' : '1';
                $date_attr = $date ? $date : '';

                // Determinar classe do badge de status
                $badge_class = 'dps-status-badge--pending';
                if ( 'finalizado_pago' === $status_key ) {
                    $badge_class = 'dps-status-badge--paid';
                } elseif ( 'cancelado' === $status_key ) {
                    $badge_class = 'dps-status-badge--cancelled';
                }

                // Montar atributos data-* da linha
                $row_attrs = sprintf(
                    'data-date="%s" data-status="%s" data-client="%s" data-pet="%s" data-total="%s" data-paid="%s"',
                    esc_attr( $date_attr ),
                    esc_attr( $status_key ),
                    esc_attr( $client_id ),
                    esc_attr( $pet_ids_attr ),
                    esc_attr( $total_val ),
                    esc_attr( $paid_flag )
                );

                echo '<tr ' . $row_attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                $date_fmt = $date ? date_i18n( 'd/m/Y', strtotime( $date ) ) : '';
                echo '<td>' . esc_html( $date_fmt ) . '</td>';
                echo '<td>' . esc_html( $time ) . '</td>';
                echo '<td>' . esc_html( $client_name ) . '</td>';
                echo '<td>' . esc_html( $pet_display ) . '</td>';
                echo '<td class="hide-mobile">' . esc_html( $services_text ) . '</td>';
                echo '<td class="dps-history-value">' . esc_html( $total_display ) . '</td>';
                echo '<td><span class="dps-status-badge ' . esc_attr( $badge_class ) . '">' . esc_html( $status_label ) . '</span></td>';
                // Coluna Operacional (Checklist + Check-in/Check-out)
                echo '<td class="hide-mobile">';
                if ( class_exists( 'DPS_Agenda_Addon' ) ) {
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML seguro retornado pelo render helper
                    echo DPS_Agenda_Addon::render_checkin_checklist_summary( $appt->ID );
                }
                echo '</td>';
                echo '<td class="hide-mobile">' . DPS_Base_Frontend::build_charge_html( $appt->ID, 'historico' ) . '</td>';

                // URLs de ações
                $edit_url      = add_query_arg( [ 'tab' => 'agendas', 'dps_edit' => 'appointment', 'id' => $appt->ID ], $base_url );
                $duplicate_url = add_query_arg( [ 'tab' => 'agendas', 'dps_duplicate' => 'appointment', 'id' => $appt->ID ], $base_url );
                $delete_url    = wp_nonce_url(
                    add_query_arg( [ 'tab' => 'agendas', 'dps_delete' => 'appointment', 'id' => $appt->ID ], $base_url ),
                    'dps_delete',
                    'dps_nonce'
                );

                // Textos de ações (para i18n)
                $edit_title      = esc_attr__( 'Editar agendamento', 'desi-pet-shower' );
                $edit_text       = esc_html__( 'Editar', 'desi-pet-shower' );
                $duplicate_title = esc_attr__( 'Duplicar agendamento', 'desi-pet-shower' );
                $duplicate_text  = esc_html__( 'Duplicar', 'desi-pet-shower' );
                $delete_title    = esc_attr__( 'Excluir agendamento', 'desi-pet-shower' );
                $delete_text     = esc_html__( 'Excluir', 'desi-pet-shower' );
                $delete_confirm  = esc_js( __( 'Tem certeza de que deseja excluir?', 'desi-pet-shower' ) );

                echo '<td class="dps-history-actions">';
                printf(
                    '<a href="%s" class="dps-action-link dps-action-link--edit" title="%s"><span class="dps-action-icon" aria-hidden="true">✏️</span><span class="dps-action-text">%s</span></a>',
                    esc_url( $edit_url ),
                    $edit_title,
                    $edit_text
                );
                printf(
                    '<a href="%s" class="dps-action-link dps-action-link--duplicate" title="%s"><span class="dps-action-icon" aria-hidden="true">📋</span><span class="dps-action-text">%s</span></a>',
                    esc_url( $duplicate_url ),
                    $duplicate_title,
                    $duplicate_text
                );
                printf(
                    '<a href="%s" class="dps-action-link dps-action-link--delete" onclick="return confirm(\'%s\');" title="%s"><span class="dps-action-icon" aria-hidden="true">🗑️</span><span class="dps-action-text">%s</span></a>',
                    esc_url( $delete_url ),
                    $delete_confirm,
                    $delete_title,
                    $delete_text
                );
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '</div>';
        } else {
            echo '<div class="dps-empty-state">';
            echo '<span class="dps-empty-state__icon">📋</span>';
            echo '<h4 class="dps-empty-state__title">' . esc_html__( 'Nenhum atendimento finalizado', 'desi-pet-shower' ) . '</h4>';
            echo '<p class="dps-empty-state__description">' . esc_html__( 'Quando você finalizar atendimentos, eles aparecerão aqui com todos os detalhes.', 'desi-pet-shower' ) . '</p>';
            echo '</div>';
        }

        echo '</div>'; // dps-surface--neutral (toolbar + tabela)
        echo '</div>'; // dps-section-grid
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Verifica se o usuário atual possui permissão de gerenciamento.
     *
     * @since 2.0.0
     * @return bool
     */
    private static function can_manage() {
        return current_user_can( 'manage_options' )
            || current_user_can( 'dps_manage_clients' )
            || current_user_can( 'dps_manage_pets' )
            || current_user_can( 'dps_manage_appointments' );
    }
}
