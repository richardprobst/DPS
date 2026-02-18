<?php
/**
 * Renderizador da seção de agendamentos.
 *
 * Extraído de DPS_Base_Frontend para reduzir o tamanho do monólito.
 * Contém a lógica de preparação de dados e renderização do formulário
 * e listagem de agendamentos.
 *
 * @package DesiPetShower
 * @since   1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe responsável pela renderização da seção de agendamentos.
 */
class DPS_Appointments_Section_Renderer {

    /**
     * Renderiza a seção completa de agendamentos.
     *
     * Orquestra a preparação de dados e a renderização da seção.
     *
     * @since 1.9.0
     * @param bool $visitor_only Se true, exibe apenas a listagem sem formulário.
     * @return string HTML da seção de agendamentos.
     */
    public static function render( $visitor_only = false ) {
        $data = self::prepare_data( $visitor_only );

        return self::render_section(
            $data,
            $visitor_only,
            [
                'include_list' => false,
            ]
        );
    }

    /**
     * Prepara os dados necessários para a seção de agendamentos.
     *
     * Centraliza toda a lógica de coleta e preparação de dados
     * para o formulário e listagem de agendamentos.
     *
     * @since 1.9.0
     * @param bool  $visitor_only Se true, modo visitante.
     * @param array $overrides    Valores para sobrescrever os padrões. {
     *     @type bool   $force_new    Se true, força modo de criação.
     *     @type int    $pref_client  Cliente pré-selecionado via URL.
     *     @type int    $pref_pet     Pet pré-selecionado via URL.
     *     @type string $base_url     URL base da página atual.
     *     @type string $current_url  URL completa atual.
     * }
     * @return array Dados estruturados para renderização.
     */
    public static function prepare_data( $visitor_only = false, array $overrides = [] ) {
        $clients    = DPS_Query_Helper::get_all_posts_by_type( 'dps_cliente' );
        $pets_query = DPS_Query_Helper::get_paginated_posts( 'dps_pet', 1, DPS_BASE_PETS_PER_PAGE );
        $pets       = $pets_query->posts;
        $pet_pages  = (int) max( 1, $pets_query->max_num_pages );

        // Detecta edição de agendamento.
        $edit_id = ( isset( $_GET['dps_edit'] ) && 'appointment' === $_GET['dps_edit'] && isset( $_GET['id'] ) )
            ? intval( $_GET['id'] )
            : 0;

        // Detecta duplicação de agendamento.
        $duplicate_id = ( isset( $_GET['dps_duplicate'] ) && 'appointment' === $_GET['dps_duplicate'] && isset( $_GET['id'] ) )
            ? intval( $_GET['id'] )
            : 0;

        $is_duplicate = false;
        $editing = null;
        $meta    = [];

        // Se está editando OU duplicando, carrega os metadados.
        $source_id = $edit_id ?: $duplicate_id;
        if ( $source_id ) {
            $editing = get_post( $source_id );
            if ( $editing ) {
                $meta = [
                    'client_id'                      => get_post_meta( $source_id, 'appointment_client_id', true ),
                    'pet_id'                         => get_post_meta( $source_id, 'appointment_pet_id', true ),
                    'date'                           => get_post_meta( $source_id, 'appointment_date', true ),
                    'time'                           => get_post_meta( $source_id, 'appointment_time', true ),
                    'notes'                          => get_post_meta( $source_id, 'appointment_notes', true ),
                    'appointment_type'               => get_post_meta( $source_id, 'appointment_type', true ),
                    'tosa'                           => get_post_meta( $source_id, 'appointment_tosa', true ),
                    'tosa_price'                     => get_post_meta( $source_id, 'appointment_tosa_price', true ),
                    'tosa_occurrence'                => get_post_meta( $source_id, 'appointment_tosa_occurrence', true ),
                    'taxidog'                        => get_post_meta( $source_id, 'appointment_taxidog', true ),
                    'taxidog_price'                  => get_post_meta( $source_id, 'appointment_taxidog_price', true ),
                    'extra_description'              => get_post_meta( $source_id, 'appointment_extra_description', true ),
                    'extra_value'                    => get_post_meta( $source_id, 'appointment_extra_value', true ),
                    'subscription_base_value'        => get_post_meta( $source_id, 'subscription_base_value', true ),
                    'subscription_total_value'       => get_post_meta( $source_id, 'subscription_total_value', true ),
                    'subscription_extra_description' => get_post_meta( $source_id, 'subscription_extra_description', true ),
                    'subscription_extra_value'       => get_post_meta( $source_id, 'subscription_extra_value', true ),
                    'past_payment_status'            => get_post_meta( $source_id, 'past_payment_status', true ),
                    'past_payment_value'             => get_post_meta( $source_id, 'past_payment_value', true ),
                    'appointment_total_value'        => get_post_meta( $source_id, 'appointment_total_value', true ),
                ];

                // Se está duplicando, limpa a data para forçar nova seleção.
                if ( $duplicate_id ) {
                    $is_duplicate = true;
                    $meta['date'] = ''; // Limpa data para que usuário escolha nova data
                    $editing = null;    // Não é edição, é criação
                    $edit_id = 0;       // Garante que não vai atualizar o agendamento original
                }
            }
        }

        // Pré-seleção de cliente e pet via URL.
        $pref_client = isset( $_GET['pref_client'] ) ? intval( $_GET['pref_client'] ) : 0;
        $pref_pet    = isset( $_GET['pref_pet'] ) ? intval( $_GET['pref_pet'] ) : 0;

        // Se está duplicando, usa o cliente do agendamento original como preferência.
        if ( $is_duplicate && ! empty( $meta['client_id'] ) ) {
            $pref_client = intval( $meta['client_id'] );
        }

        // Sobrescreve valores quando fornecidos explicitamente (ex.: modal).
        if ( isset( $overrides['force_new'] ) && $overrides['force_new'] ) {
            $edit_id      = 0;
            $duplicate_id = 0;
            $editing      = null;
            $meta         = [];
            $is_duplicate = false;
        }

        if ( isset( $overrides['pref_client'] ) ) {
            $pref_client = intval( $overrides['pref_client'] );
        }

        if ( isset( $overrides['pref_pet'] ) ) {
            $pref_pet = intval( $overrides['pref_pet'] );
        }

        $override_base_url    = isset( $overrides['base_url'] ) ? esc_url_raw( $overrides['base_url'] ) : '';
        $override_current_url = isset( $overrides['current_url'] ) ? esc_url_raw( $overrides['current_url'] ) : '';
        $base_url             = $override_base_url ? $override_base_url : DPS_URL_Builder::safe_get_permalink();
        $current_url          = $override_current_url ? $override_current_url : DPS_Base_Frontend::get_current_page_url();

        return [
            'clients'      => $clients,
            'pets'         => $pets,
            'pet_pages'    => $pet_pages,
            'edit_id'      => $edit_id,
            'editing'      => $editing,
            'meta'         => $meta,
            'pref_client'  => $pref_client,
            'pref_pet'     => $pref_pet,
            'base_url'     => $base_url,
            'current_url'  => $current_url,
            'is_duplicate' => $is_duplicate,
        ];
    }

    /**
     * Renderiza a seção de agendamentos com os dados preparados.
     *
     * Contém toda a lógica de renderização do formulário e listagem.
     *
     * @since 1.9.0
     * @param array $data         Dados preparados por prepare_data().
     * @param bool  $visitor_only Se true, exibe apenas a listagem sem formulário.
     * @param array $options      Opções de renderização.
     * @return string HTML da seção.
     */
    public static function render_section( array $data, $visitor_only = false, array $options = [] ) {
        // Extrai variáveis do array de dados.
        $clients      = $data['clients'];
        $pets         = $data['pets'];
        $pet_pages    = $data['pet_pages'];
        $edit_id      = $data['edit_id'];
        $editing      = $data['editing'];
        $meta         = $data['meta'];
        $pref_client  = $data['pref_client'];
        $pref_pet     = $data['pref_pet'];
        $is_duplicate = isset( $data['is_duplicate'] ) ? $data['is_duplicate'] : false;
        $base_url     = isset( $data['base_url'] ) ? $data['base_url'] : DPS_URL_Builder::safe_get_permalink();
        $current_url  = isset( $data['current_url'] ) ? $data['current_url'] : DPS_Base_Frontend::get_current_page_url();

        $options      = wp_parse_args(
            $options,
            [
                'context'      => 'page',
                'include_list' => true,
            ]
        );

        $is_modal     = ( 'modal' === $options['context'] );
        $include_list = (bool) $options['include_list'];
        $section_id   = $is_modal ? 'dps-section-agendas-modal' : 'dps-section-agendas';
        $section_classes = [ 'dps-section' ];
        if ( $is_modal ) {
            $section_classes[] = 'dps-section--modal';
            $section_classes[] = 'active'; // Garante exibição dentro do modal (base oculta se não estiver ativo).
        }

        ob_start();
        echo '<div class="' . esc_attr( implode( ' ', $section_classes ) ) . '" id="' . esc_attr( $section_id ) . '">';
        
        // Título da seção (aparece para todos os usuários)
        echo '<h2 class="dps-section-title">';
        echo '<span class="dps-section-title__icon">📅</span>';
        echo esc_html__( 'Agendamento de Serviços', 'desi-pet-shower' );
        echo '</h2>';
        
        // Formulário de agendamento com estrutura Surface (mesmo padrão da aba CLIENTES)
        if ( ! $visitor_only ) {
            // Título do formulário: Novo ou Editar
            $form_title = $edit_id
                ? esc_html__( 'Editar Agendamento', 'desi-pet-shower' )
                : esc_html__( 'Novo Agendamento', 'desi-pet-shower' );
            
            echo '<div class="dps-surface dps-surface--info">';
            echo '<div class="dps-surface__title">';
            echo '<span>📝</span>';
            echo esc_html__( 'Agendar serviço', 'desi-pet-shower' );
            echo '</div>';
            
            // Mensagem de duplicação
            if ( $is_duplicate ) {
                echo '<div class="dps-alert dps-alert--info" role="status" aria-live="polite">';
                echo '<strong>' . esc_html__( 'Duplicando agendamento', 'desi-pet-shower' ) . '</strong><br>';
                echo esc_html__( 'Os dados do agendamento anterior foram copiados. Selecione uma nova data e horário, então salve para criar o novo agendamento.', 'desi-pet-shower' );
                echo '</div>';
            }
            
            if ( isset( $_GET['dps_notice'] ) && 'pending_payments' === $_GET['dps_notice'] ) {
                $notice_key  = 'dps_pending_notice_' . get_current_user_id();
                $notice_data = get_transient( $notice_key );
                if ( $notice_data && ! empty( $notice_data['transactions'] ) ) {
                    echo '<div class="dps-alert dps-alert--danger">';
                    $client_label = ! empty( $notice_data['client_name'] ) ? $notice_data['client_name'] : __( 'o cliente selecionado', 'desi-pet-shower' );
                    echo '<strong>' . sprintf( esc_html__( 'Pagamentos em aberto para %s.', 'desi-pet-shower' ), esc_html( $client_label ) ) . '</strong>';
                    echo '<ul>';
                    foreach ( $notice_data['transactions'] as $row ) {
                        $date_fmt  = ! empty( $row['data'] ) ? date_i18n( 'd/m/Y', strtotime( $row['data'] ) ) : '';
                        $value_fmt = number_format_i18n( (float) $row['valor'], 2 );
                        $desc      = ! empty( $row['descricao'] ) ? $row['descricao'] : __( 'Serviço', 'desi-pet-shower' );
                        $message   = trim( sprintf( '%s: R$ %s – %s', $date_fmt, $value_fmt, $desc ) );
                        echo '<li>' . esc_html( $message ) . '</li>';
                    }
                    echo '</ul>';
                    echo '</div>';
                }
                delete_transient( $notice_key );
            }
            
            echo '<form method="post" class="dps-form">';
            echo '<input type="hidden" name="dps_action" value="save_appointment">';
            wp_nonce_field( 'dps_action', 'dps_nonce_agendamentos' );
            echo '<input type="hidden" name="dps_redirect_url" value="' . esc_attr( $current_url ) . '">';
            if ( $edit_id ) {
                echo '<input type="hidden" name="appointment_id" value="' . esc_attr( $edit_id ) . '">';
            }
            // FIELDSET 1: Tipo de Agendamento
            echo '<fieldset class="dps-fieldset">';
            echo '<legend class="dps-fieldset__legend">' . esc_html__( 'Tipo de Agendamento', 'desi-pet-shower' ) . '</legend>';
            
            // Campo: tipo de agendamento (simples, assinatura ou passado)
            $appt_type = isset( $meta['appointment_type'] ) ? $meta['appointment_type'] : 'simple';
            echo '<div class="dps-radio-group">';
            echo '<label class="dps-radio-option">';
            echo '<input type="radio" name="appointment_type" value="simple" ' . checked( $appt_type, 'simple', false ) . '>';
            echo '<div class="dps-radio-label">';
            echo '<strong>' . esc_html__( 'Agendamento Simples', 'desi-pet-shower' ) . '</strong>';
            echo '<p>' . esc_html__( 'Atendimento único, sem recorrência', 'desi-pet-shower' ) . '</p>';
            echo '</div>';
            echo '</label>';
            
            echo '<label class="dps-radio-option">';
            echo '<input type="radio" name="appointment_type" value="subscription" ' . checked( $appt_type, 'subscription', false ) . '>';
            echo '<div class="dps-radio-label">';
            echo '<strong>' . esc_html__( 'Agendamento de Assinatura', 'desi-pet-shower' ) . '</strong>';
            echo '<p>' . esc_html__( 'Atendimentos recorrentes (semanal ou quinzenal)', 'desi-pet-shower' ) . '</p>';
            echo '</div>';
            echo '</label>';
            
            echo '<label class="dps-radio-option">';
            echo '<input type="radio" name="appointment_type" value="past" ' . checked( $appt_type, 'past', false ) . '>';
            echo '<div class="dps-radio-label">';
            echo '<strong>' . esc_html__( 'Agendamento Passado', 'desi-pet-shower' ) . '</strong>';
            echo '<p>' . esc_html__( 'Registrar atendimento já realizado anteriormente', 'desi-pet-shower' ) . '</p>';
            echo '</div>';
            echo '</label>';
            echo '</div>';
            
            // Campo: frequência para assinaturas (semanal ou quinzenal)
            $freq_val = isset( $_POST['appointment_frequency'] ) ? sanitize_text_field( wp_unslash( $_POST['appointment_frequency'] ) ) : '';
            if ( $edit_id ) {
                // Se estiver editando, tenta obter frequência via subscription_id
                $sub_id_edit = get_post_meta( $edit_id, 'subscription_id', true );
                if ( $sub_id_edit ) {
                    $freq_val = get_post_meta( $sub_id_edit, 'subscription_frequency', true );
                }
            }
            $freq_display = ( $appt_type === 'subscription' ) ? 'block' : 'none';
            echo '<div id="dps-appointment-frequency-wrapper" class="dps-conditional-field" style="display:' . esc_attr( $freq_display ) . ';">';
            echo '<label for="dps-appointment-frequency">' . esc_html__( 'Frequência', 'desi-pet-shower' ) . ' <span class="dps-required">*</span></label>';
            echo '<select name="appointment_frequency" id="dps-appointment-frequency">';
            echo '<option value="semanal" ' . selected( $freq_val, 'semanal', false ) . '>' . esc_html__( 'Semanal', 'desi-pet-shower' ) . '</option>';
            echo '<option value="quinzenal" ' . selected( $freq_val, 'quinzenal', false ) . '>' . esc_html__( 'Quinzenal', 'desi-pet-shower' ) . '</option>';
            echo '</select>';
            echo '</div>';
            
            echo '</fieldset>';
            
            // FIELDSET 2: Cliente e Pet(s)
            echo '<fieldset class="dps-fieldset">';
            echo '<legend class="dps-fieldset__legend">' . esc_html__( 'Cliente e Pet(s)', 'desi-pet-shower' ) . '</legend>';
            
            // Cliente
            // Preenchimento: se não editando, usa pref_client se disponível
            if ( ! $edit_id && $pref_client ) {
                $meta['client_id'] = $pref_client;
            }
            $sel_client = $meta['client_id'] ?? '';
            echo '<div class="dps-form-field">';
            echo '<label for="dps-appointment-cliente">' . esc_html__( 'Cliente', 'desi-pet-shower' ) . ' <span class="dps-required">*</span></label>';
            echo '<select name="appointment_client_id" id="dps-appointment-cliente" class="dps-client-select" required>';
            echo '<option value="">' . esc_html__( 'Selecione...', 'desi-pet-shower' ) . '</option>';
            $pending_cache = [];
            foreach ( $clients as $client ) {
                if ( ! array_key_exists( $client->ID, $pending_cache ) ) {
                    $pending_cache[ $client->ID ] = self::get_client_pending_transactions( $client->ID );
                }
                $pending_rows = $pending_cache[ $client->ID ];
                $pending_attr = ' data-has-pending="' . ( $pending_rows ? '1' : '0' ) . '"';
                $consent_data = DPS_Base_Frontend::get_client_tosa_consent_data( $client->ID );
                $consent_attr = ' data-consent-status="' . esc_attr( $consent_data['status'] ) . '"';
                $consent_attr .= ' data-consent-date="' . esc_attr( $consent_data['granted_at'] ?: $consent_data['revoked_at'] ) . '"';
                if ( $pending_rows ) {
                    $payload = [];
                    foreach ( $pending_rows as $row ) {
                        $payload[] = [
                            'date'        => ! empty( $row['data'] ) ? date_i18n( 'd/m/Y', strtotime( $row['data'] ) ) : '',
                            'value'       => number_format_i18n( (float) $row['valor'], 2 ),
                            'description' => ! empty( $row['descricao'] ) ? wp_strip_all_tags( $row['descricao'] ) : __( 'Serviço', 'desi-pet-shower' ),
                        ];
                    }
                    $pending_attr .= ' data-pending-info=\'' . esc_attr( wp_json_encode( $payload ) ) . '\'';
                }
                $option_attrs  = ' value="' . esc_attr( $client->ID ) . '"';
                if ( (string) $client->ID === (string) $sel_client ) {
                    $option_attrs .= ' selected';
                }
                $option_attrs .= $pending_attr . $consent_attr;
                echo '<option' . $option_attrs . '>' . esc_html( $client->post_title ) . '</option>';
            }
            echo '</select>';
            echo '</div>';
            
            // Alerta de pendências financeiras
            $initial_pending_rows = [];
            if ( $sel_client && isset( $pending_cache[ $sel_client ] ) ) {
                $initial_pending_rows = $pending_cache[ $sel_client ];
            }
            $initial_alert_html = '';
            if ( $initial_pending_rows ) {
                $client_post = get_post( (int) $sel_client );
                $client_name = $client_post ? $client_post->post_title : '';
                if ( $client_name ) {
                    $initial_alert_html .= '<strong>' . sprintf( esc_html__( 'Pagamentos em aberto para %s.', 'desi-pet-shower' ), esc_html( $client_name ) ) . '</strong>';
                } else {
                    $initial_alert_html .= '<strong>' . esc_html__( 'Este cliente possui pagamentos pendentes.', 'desi-pet-shower' ) . '</strong>';
                }
                $initial_alert_html .= '<ul>';
                foreach ( $initial_pending_rows as $row ) {
                    $date_fmt  = ! empty( $row['data'] ) ? date_i18n( 'd/m/Y', strtotime( $row['data'] ) ) : '';
                    $value_fmt = number_format_i18n( (float) $row['valor'], 2 );
                    $desc      = ! empty( $row['descricao'] ) ? $row['descricao'] : __( 'Serviço', 'desi-pet-shower' );
                    if ( $date_fmt ) {
                        $message = sprintf( __( '%1$s: R$ %2$s – %3$s', 'desi-pet-shower' ), $date_fmt, $value_fmt, $desc );
                    } else {
                        $message = sprintf( __( 'R$ %1$s – %2$s', 'desi-pet-shower' ), $value_fmt, $desc );
                    }
                    $initial_alert_html .= '<li>' . esc_html( $message ) . '</li>';
                }
                $initial_alert_html .= '</ul>';
            }
            $alert_attrs = ' id="dps-client-pending-alert" class="dps-alert dps-alert--danger dps-alert--pending" role="status" aria-live="polite"';
            if ( $initial_alert_html ) {
                $alert_attrs .= ' aria-hidden="false"';
            } else {
                $alert_attrs .= ' aria-hidden="true" style="display:none;"';
            }
            echo '<div' . $alert_attrs . '>' . $initial_alert_html . '</div>';

            $initial_consent_data = $sel_client ? DPS_Base_Frontend::get_client_tosa_consent_data( $sel_client ) : [ 'status' => 'missing', 'has_consent' => false, 'granted_at' => '', 'revoked_at' => '' ];
            $consent_status = $initial_consent_data['status'];
            $consent_label  = __( 'Consentimento tosa máquina pendente', 'desi-pet-shower' );
            $consent_note   = '';
            $consent_class  = 'dps-consent-badge--missing';
            if ( 'granted' === $consent_status ) {
                $consent_label = __( 'Consentimento tosa máquina ativo', 'desi-pet-shower' );
                $consent_class = 'dps-consent-badge--ok';
                if ( $initial_consent_data['granted_at'] ) {
                    $consent_note = sprintf(
                        /* translators: %s: data */
                        __( 'Assinado em %s', 'desi-pet-shower' ),
                        esc_html( $initial_consent_data['granted_at'] )
                    );
                }
            } elseif ( 'revoked' === $consent_status ) {
                $consent_label = __( 'Consentimento tosa máquina revogado', 'desi-pet-shower' );
                $consent_class = 'dps-consent-badge--danger';
                if ( $initial_consent_data['revoked_at'] ) {
                    $consent_note = sprintf(
                        /* translators: %s: data */
                        __( 'Revogado em %s', 'desi-pet-shower' ),
                        esc_html( $initial_consent_data['revoked_at'] )
                    );
                }
            }
            $consent_attrs = ' id="dps-client-consent-status" class="dps-consent-status" data-consent-status="' . esc_attr( $consent_status ) . '"';
            $consent_attrs .= ' data-consent-date="' . esc_attr( $initial_consent_data['granted_at'] ?: $initial_consent_data['revoked_at'] ) . '"';
            if ( $sel_client ) {
                $consent_attrs .= ' aria-hidden="false"';
            } else {
                $consent_attrs .= ' aria-hidden="true" style="display:none;"';
            }
            echo '<div' . $consent_attrs . '>';
            echo '<span class="dps-consent-badge ' . esc_attr( $consent_class ) . '">' . esc_html( $consent_label ) . '</span>';
            if ( $consent_note ) {
                echo '<span class="dps-consent-status__note">' . esc_html( $consent_note ) . '</span>';
            }
            echo '</div>';

            $warning_attrs = ' id="dps-consent-warning" class="dps-alert dps-alert--warning dps-consent-warning" role="status" aria-live="polite"';
            $warning_attrs .= ' aria-hidden="true" style="display:none;"';
            echo '<div' . $warning_attrs . '>';
            echo esc_html__( 'Este cliente ainda não possui consentimento de tosa com máquina. Gere o link antes de confirmar o atendimento.', 'desi-pet-shower' );
            echo '</div>';
            // Pets (permite múltiplos)
            // Se não editando, utiliza pref_pet como pré‑seleção única
            if ( ! $edit_id && $pref_pet ) {
                $meta['pet_id'] = $pref_pet;
            }
            // Obtém lista de pets selecionados. Para edições, meta['pet_id'] pode ser ID único.
            $sel_pets = [];
            if ( isset( $meta['pet_id'] ) && $meta['pet_id'] ) {
                $sel_pets[] = (string) $meta['pet_id'];
            }
            // Caso tenhamos meta appointment_pet_ids (quando multi‑pets são salvos), utiliza essa lista
            $multi_meta = get_post_meta( $edit_id, 'appointment_pet_ids', true );
            if ( $multi_meta && is_array( $multi_meta ) ) {
                $sel_pets = array_map( 'strval', $multi_meta );
            }
            $pet_wrapper_attrs = ' id="dps-appointment-pet-wrapper" class="dps-pet-picker"';
            $pet_wrapper_attrs .= ' data-current-page="1" data-total-pages="' . esc_attr( $pet_pages ) . '"';
            echo '<div' . $pet_wrapper_attrs . '>';
            echo '<p id="dps-pet-selector-label"><strong>' . esc_html__( 'Pet(s)', 'desi-pet-shower' ) . ' <span class="dps-required">*</span></strong><span id="dps-pet-counter" class="dps-selection-counter" style="display:none;">0 ' . esc_html__( 'selecionados', 'desi-pet-shower' ) . '</span></p>';
            echo '<div class="dps-pet-picker-actions">';
            echo '<button type="button" class="button button-secondary dps-pet-toggle" data-action="select">' . esc_html__( 'Selecionar todos', 'desi-pet-shower' ) . '</button> ';
            echo '<button type="button" class="button button-secondary dps-pet-toggle" data-action="clear">' . esc_html__( 'Limpar seleção', 'desi-pet-shower' ) . '</button>';
            echo '</div>';
            echo '<div id="dps-appointment-pet-list" class="dps-pet-list" role="group" aria-labelledby="dps-pet-selector-label">';
            foreach ( $pets as $pet ) {
                $owner_id   = get_post_meta( $pet->ID, 'owner_id', true );
                $owner_post = $owner_id ? get_post( $owner_id ) : null;
                $owner_name = $owner_post ? $owner_post->post_title : '';
                $size       = get_post_meta( $pet->ID, 'pet_size', true );
                $breed      = get_post_meta( $pet->ID, 'pet_breed', true );
                $sel        = in_array( (string) $pet->ID, $sel_pets, true ) ? 'checked' : '';
                $size_attr  = $size ? ' data-size="' . esc_attr( strtolower( $size ) ) . '"' : '';
                $owner_attr = $owner_id ? ' data-owner="' . esc_attr( $owner_id ) . '"' : '';
                $search_blob = strtolower( $pet->post_title . ' ' . $breed . ' ' . $owner_name );
                echo '<label class="dps-pet-option"' . $owner_attr . $size_attr . ' data-search="' . esc_attr( $search_blob ) . '">';
                echo '<input type="checkbox" class="dps-pet-checkbox" name="appointment_pet_ids[]" value="' . esc_attr( $pet->ID ) . '" ' . $sel . '>';
                echo '<span class="dps-pet-name">' . esc_html( $pet->post_title ) . '</span>';
                if ( $breed ) {
                    echo '<span class="dps-pet-breed"> – ' . esc_html( $breed ) . '</span>';
                }
                if ( $size ) {
                    echo '<span class="dps-pet-size"> · ' . esc_html( ucfirst( $size ) ) . '</span>';
                }
                echo '</label>';
            }
            echo '</div>';
            if ( $pet_pages > 1 ) {
                echo '<p><button type="button" class="button dps-pet-load-more" data-next-page="2" data-loading="false">' . esc_html__( 'Carregar mais pets', 'desi-pet-shower' ) . '</button></p>';
            }
            echo '<p id="dps-pet-summary" class="dps-field-hint" style="display:none;"></p>';
            echo '<p id="dps-no-pets-message" class="dps-field-hint" style="display:none;">' . esc_html__( 'Nenhum pet disponível para o cliente selecionado.', 'desi-pet-shower' ) . '</p>';
            echo '</div>';
            
            echo '</fieldset>';
            
            // FIELDSET 3: Data e Horário
            echo '<fieldset class="dps-fieldset">';
            echo '<legend class="dps-fieldset__legend">' . esc_html__( 'Data e Horário', 'desi-pet-shower' ) . '</legend>';
            
            // Data e Horário em grid 2 colunas
            $date_val = $meta['date'] ?? '';
            $time_val = $meta['time'] ?? '';
            echo '<div class="dps-form-row dps-form-row--2col">';
            echo '<div class="dps-form-field">';
            echo '<label for="appointment_date">' . esc_html__( 'Data', 'desi-pet-shower' ) . ' <span class="dps-required">*</span></label>';
            echo '<input type="date" id="appointment_date" name="appointment_date" value="' . esc_attr( $date_val ) . '" required>';
            echo '<p class="dps-field-hint">' . esc_html__( 'Horários disponíveis serão carregados após escolher a data', 'desi-pet-shower' ) . '</p>';
            echo '</div>';
            echo '<div class="dps-form-field">';
            echo '<label for="appointment_time">' . esc_html__( 'Horário', 'desi-pet-shower' ) . ' <span class="dps-required">*</span></label>';
            // Usar select em vez de input time para carregar horários disponíveis via AJAX
            echo '<select id="appointment_time" name="appointment_time" required>';
            if ( $time_val ) {
                // Se editando, mantém o horário atual como opção
                echo '<option value="' . esc_attr( $time_val ) . '" selected>' . esc_html( $time_val ) . '</option>';
            } else {
                echo '<option value="">' . esc_html__( 'Escolha uma data primeiro', 'desi-pet-shower' ) . '</option>';
            }
            echo '</select>';
            echo '</div>';
            echo '</div>';
            
            echo '</fieldset>';
            
            // SEÇÃO INDEPENDENTE: TaxiDog (fora do fieldset de Serviços)
            $taxidog = $meta['taxidog'] ?? '';
            $taxidog_price_val = $meta['taxidog_price'] ?? '';
            
            echo '<div class="dps-taxidog-section">';
            echo '<div class="dps-taxidog-card" data-taxidog-active="' . ( $taxidog ? '1' : '0' ) . '">';
            echo '<div class="dps-taxidog-card__header">';
            echo '<div class="dps-taxidog-card__icon-title">';
            echo '<span class="dps-taxidog-icon" aria-hidden="true">🚗</span>';
            echo '<span class="dps-taxidog-title">' . esc_html__( 'Solicitar TaxiDog?', 'desi-pet-shower' ) . '</span>';
            echo '</div>';
            echo '<label class="dps-toggle-switch">';
            echo '<input type="checkbox" id="dps-taxidog-toggle" name="appointment_taxidog" value="1" ' . checked( $taxidog, '1', false ) . '>';
            echo '<span class="dps-toggle-slider"></span>';
            echo '</label>';
            echo '</div>';
            echo '<p class="dps-taxidog-description">' . esc_html__( 'Serviço de transporte para buscar e/ou levar o pet', 'desi-pet-shower' ) . '</p>';
            
            // Área de preço do TaxiDog
            echo '<div id="dps-taxidog-extra" class="dps-taxidog-card__value" style="display:' . ( $taxidog ? 'flex' : 'none' ) . ';">';
            echo '<label for="dps-taxidog-price" class="dps-taxidog-value-label">' . esc_html__( 'Valor do serviço:', 'desi-pet-shower' ) . '</label>';
            echo '<div class="dps-input-with-prefix">';
            echo '<span class="dps-input-prefix">R$</span>';
            echo '<input type="number" id="dps-taxidog-price" name="appointment_taxidog_price" step="0.01" min="0" value="' . esc_attr( $taxidog_price_val ) . '" class="dps-input-money dps-taxidog-price-input" placeholder="0,00">';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            
            // FIELDSET 4: Serviços e Extras
            echo '<fieldset class="dps-fieldset">';
            echo '<legend class="dps-fieldset__legend">' . esc_html__( 'Serviços e Extras', 'desi-pet-shower' ) . '</legend>';
            
            // Campo: indicativo de necessidade de tosa (apenas para assinaturas)
            // Card de tosa com design similar ao TaxiDog para melhor UX
            $tosa       = $meta['tosa'] ?? '';
            $tosa_price = $meta['tosa_price'] ?? '';
            $tosa_occ   = $meta['tosa_occurrence'] ?? '1';
            $tosa_price_val = $tosa_price !== '' ? $tosa_price : '30';
            
            echo '<div id="dps-tosa-wrapper" class="dps-tosa-section" style="display:none;">';
            echo '<div class="dps-tosa-card" data-tosa-active="' . ( '1' === $tosa ? '1' : '0' ) . '">';
            echo '<div class="dps-tosa-card__header">';
            echo '<div class="dps-tosa-card__icon-title">';
            echo '<span class="dps-tosa-icon" aria-hidden="true">✂️</span>';
            echo '<span class="dps-tosa-title">' . esc_html__( 'Adicionar tosa?', 'desi-pet-shower' ) . '</span>';
            echo '</div>';
            echo '<label class="dps-toggle-switch">';
            echo '<input type="checkbox" id="dps-tosa-toggle" name="appointment_tosa" value="1" ' . checked( $tosa, '1', false ) . '>';
            echo '<span class="dps-toggle-slider"></span>';
            echo '</label>';
            echo '</div>';
            echo '<p class="dps-tosa-description">' . esc_html__( 'Serviço de tosa adicional em um dos atendimentos da assinatura', 'desi-pet-shower' ) . '</p>';
            
            // Campos de configuração da tosa (visíveis quando ativo)
            echo '<div id="dps-tosa-fields" class="dps-tosa-card__fields" style="display:' . ( '1' === $tosa ? 'grid' : 'none' ) . ';">';
            
            // Preço da tosa
            echo '<div class="dps-tosa-field">';
            echo '<label for="dps-tosa-price" class="dps-tosa-field-label">' . esc_html__( 'Valor da tosa:', 'desi-pet-shower' ) . '</label>';
            echo '<div class="dps-input-with-prefix">';
            echo '<span class="dps-input-prefix">R$</span>';
            echo '<input type="number" step="0.01" min="0" id="dps-tosa-price" name="appointment_tosa_price" value="' . esc_attr( $tosa_price_val ) . '" class="dps-input-money dps-tosa-price-input" placeholder="30,00">';
            echo '</div>';
            echo '</div>';
            
            // Ocorrência da tosa (selecionada via JS conforme frequência)
            echo '<div class="dps-tosa-field">';
            echo '<label for="appointment_tosa_occurrence" class="dps-tosa-field-label">' . esc_html__( 'Em qual atendimento:', 'desi-pet-shower' ) . '</label>';
            echo '<select name="appointment_tosa_occurrence" id="appointment_tosa_occurrence" class="dps-tosa-occurrence-select" data-current="' . esc_attr( $tosa_occ ) . '"></select>';
            echo '<p class="dps-tosa-field-hint">' . esc_html__( 'Escolha o atendimento em que a tosa será realizada', 'desi-pet-shower' ) . '</p>';
            echo '</div>';
            
            echo '</div>';
            echo '</div>';
            echo '</div>';
            
            // Hook para add-ons injetarem campos extras (ex.: serviços)
            /**
             * Permite que add‑ons adicionem campos extras ao formulário de agendamento.
             *
             * @param int   $edit_id ID do agendamento em edição ou 0 se novo
             * @param array $meta    Meta dados do agendamento
             */
            do_action( 'dps_base_appointment_fields', $edit_id, $meta );
            
            echo '</fieldset>';
            
            // FIELDSET 5: Atribuição (Profissionais responsáveis)
            // Hook para add-ons injetarem campo de profissionais
            $has_assignment_content = has_action( 'dps_base_appointment_assignment_fields' );
            if ( $has_assignment_content ) {
                echo '<fieldset class="dps-fieldset dps-assignment-fieldset">';
                echo '<legend class="dps-fieldset__legend">' . esc_html__( 'Atribuição', 'desi-pet-shower' ) . '</legend>';
                
                /**
                 * Permite que add‑ons adicionem campos de atribuição de profissionais.
                 *
                 * @since 1.8.0
                 *
                 * @param int   $edit_id ID do agendamento em edição ou 0 se novo
                 * @param array $meta    Meta dados do agendamento
                 */
                do_action( 'dps_base_appointment_assignment_fields', $edit_id, $meta );
                
                echo '</fieldset>';
            }
            
            // FIELDSET 6: Informações de Pagamento (apenas para agendamentos passados)
            $past_payment_status = isset( $meta['past_payment_status'] ) ? $meta['past_payment_status'] : '';
            $past_payment_value  = isset( $meta['past_payment_value'] ) ? $meta['past_payment_value'] : '';
            $past_display = ( $appt_type === 'past' ) ? 'block' : 'none';
            echo '<fieldset id="dps-past-payment-wrapper" class="dps-fieldset dps-conditional-field" style="display:' . esc_attr( $past_display ) . ';">';
            echo '<legend class="dps-fieldset__legend">' . esc_html__( 'Informações de Pagamento', 'desi-pet-shower' ) . '</legend>';
            
            // Campo: status do pagamento
            echo '<div class="dps-form-field">';
            echo '<label for="past_payment_status">' . esc_html__( 'Status do Pagamento', 'desi-pet-shower' ) . ' <span class="dps-required">*</span></label>';
            echo '<select name="past_payment_status" id="past_payment_status">';
            echo '<option value="">' . esc_html__( 'Selecione...', 'desi-pet-shower' ) . '</option>';
            echo '<option value="paid" ' . selected( $past_payment_status, 'paid', false ) . '>' . esc_html__( 'Pago', 'desi-pet-shower' ) . '</option>';
            echo '<option value="pending" ' . selected( $past_payment_status, 'pending', false ) . '>' . esc_html__( 'Pendente', 'desi-pet-shower' ) . '</option>';
            echo '</select>';
            echo '<p class="dps-field-hint">' . esc_html__( 'Informe se o pagamento deste atendimento já foi realizado ou está pendente', 'desi-pet-shower' ) . '</p>';
            echo '</div>';
            
            // Campo: valor do pagamento pendente (condicional)
            $payment_value_display = ( $past_payment_status === 'pending' ) ? 'block' : 'none';
            echo '<div id="dps-past-payment-value-wrapper" class="dps-form-field dps-conditional-field" style="display:' . esc_attr( $payment_value_display ) . ';">';
            echo '<label for="past_payment_value">' . esc_html__( 'Valor Pendente (R$)', 'desi-pet-shower' ) . ' <span class="dps-required">*</span></label>';
            echo '<input type="number" step="0.01" min="0" id="past_payment_value" name="past_payment_value" value="' . esc_attr( $past_payment_value ) . '" class="dps-input-money" placeholder="0,00">';
            echo '<p class="dps-field-hint">' . esc_html__( 'Informe o valor que ainda está pendente de pagamento', 'desi-pet-shower' ) . '</p>';
            echo '</div>';
            
            echo '</fieldset>';
            
            // FIELDSET 6: Observações
            echo '<fieldset class="dps-fieldset">';
            echo '<legend class="dps-fieldset__legend">' . esc_html__( 'Observações e Notas', 'desi-pet-shower' ) . '</legend>';
            
            // Observações
            $notes_val = $meta['notes'] ?? '';
            echo '<label for="appointment_notes">' . esc_html__( 'Observações', 'desi-pet-shower' ) . '</label>';
            echo '<textarea id="appointment_notes" name="appointment_notes" rows="3" placeholder="' . esc_attr__( 'Instruções especiais, preferências do cliente, etc.', 'desi-pet-shower' ) . '">' . esc_textarea( $notes_val ) . '</textarea>';
            echo '<p class="dps-field-hint">' . esc_html__( 'Opcional - use este campo para anotações internas', 'desi-pet-shower' ) . '</p>';
            
            echo '</fieldset>';
            
            // Resumo dinâmico do agendamento (FASE 2)
            echo '<div class="dps-appointment-summary" aria-live="polite">';
            echo '<h3><span class="dps-appointment-summary__icon" aria-hidden="true">📋</span>' . esc_html__( 'Resumo do agendamento', 'desi-pet-shower' ) . '</h3>';
            echo '<p class="dps-appointment-summary__empty">';
            echo esc_html__( 'Preencha cliente, pet, data e horário para ver o resumo aqui.', 'desi-pet-shower' );
            echo '</p>';
            echo '<ul class="dps-appointment-summary__list" hidden>';
            echo '<li><strong>' . esc_html__( 'Cliente:', 'desi-pet-shower' ) . '</strong> <span data-summary="client">-</span></li>';
            echo '<li><strong>' . esc_html__( 'Pets:', 'desi-pet-shower' ) . '</strong> <span data-summary="pets">-</span></li>';
            echo '<li><strong>' . esc_html__( 'Data:', 'desi-pet-shower' ) . '</strong> <span data-summary="date">-</span></li>';
            echo '<li><strong>' . esc_html__( 'Horário:', 'desi-pet-shower' ) . '</strong> <span data-summary="time">-</span></li>';
            echo '<li><strong>' . esc_html__( 'Serviços:', 'desi-pet-shower' ) . '</strong> <span data-summary="services">-</span></li>';
            echo '<li class="dps-appointment-summary__extras" style="display:none;"><strong>' . esc_html__( 'Extras:', 'desi-pet-shower' ) . '</strong> <span data-summary="extras">-</span></li>';
            echo '<li><strong>' . esc_html__( 'Valor estimado:', 'desi-pet-shower' ) . '</strong> <span data-summary="price">R$ 0,00</span></li>';
            echo '<li class="dps-appointment-summary__notes"><strong>' . esc_html__( 'Observações:', 'desi-pet-shower' ) . '</strong> <span data-summary="notes">-</span></li>';
            echo '</ul>';
            echo '</div>';
            
            // Campos hidden para valores calculados pelo JavaScript
            // Estes campos serão populados automaticamente pelo JS ao atualizar o resumo
            $total_value_current = isset( $meta['appointment_total_value'] ) ? floatval( $meta['appointment_total_value'] ) : 0;
            $sub_base_current    = isset( $meta['subscription_base_value'] ) ? floatval( $meta['subscription_base_value'] ) : 0;
            $sub_total_current   = isset( $meta['subscription_total_value'] ) ? floatval( $meta['subscription_total_value'] ) : 0;
            $sub_extra_current   = isset( $meta['subscription_extra_value'] ) ? floatval( $meta['subscription_extra_value'] ) : 0;
            
            echo '<input type="hidden" id="appointment_total" name="appointment_total" value="' . esc_attr( $total_value_current ) . '">';
            echo '<input type="hidden" id="subscription_base_value" name="subscription_base_value" value="' . esc_attr( $sub_base_current ) . '">';
            echo '<input type="hidden" id="subscription_total_value" name="subscription_total_value" value="' . esc_attr( $sub_total_current ) . '">';
            echo '<input type="hidden" id="subscription_extra_value" name="subscription_extra_value" value="' . esc_attr( $sub_extra_current ) . '">';
            
            // Botões de ação
            $btn_text = $edit_id ? esc_html__( 'Atualizar Agendamento', 'desi-pet-shower' ) : esc_html__( 'Salvar Agendamento', 'desi-pet-shower' );
            echo '<div class="dps-form-actions">';
            echo '<button type="submit" class="dps-btn dps-btn--primary dps-submit-btn dps-appointment-submit">✓ ' . $btn_text . '</button>';
            $cancel_url = remove_query_arg( [ 'dps_edit', 'id' ] );
            if ( $edit_id ) {
                echo '<a href="' . esc_url( $cancel_url ) . '" class="dps-btn dps-btn--secondary">' . esc_html__( 'Cancelar', 'desi-pet-shower' ) . '</a>';
            }
            echo '</div>';
            
            // Bloco de erros de validação (FASE 2)
            echo '<div class="dps-form-error" role="alert" aria-live="assertive" hidden></div>';
            
            // Script inline REMOVED - agora em dps-appointment-form.js
            echo '</form>';
            
            echo '</div>'; // .dps-surface
        }
        
        // Listagem de agendamentos organizados por status
        if ( $include_list ) {
            $args = [
                'post_type'      => 'dps_agendamento',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'orderby'        => 'meta_value',
                'meta_key'       => 'appointment_date',
                'order'          => 'ASC',
            ];
            $appointments   = get_posts( $args );
            $status_labels  = [
                'pendente'        => __( 'Pendente', 'desi-pet-shower' ),
                'finalizado'      => __( 'Finalizado', 'desi-pet-shower' ),
                'finalizado_pago' => __( 'Finalizado e pago', 'desi-pet-shower' ),
                'cancelado'       => __( 'Cancelado', 'desi-pet-shower' ),
            ];
            $overdue        = [];
            $finalized_today = [];
            $upcoming       = [];
            $now_ts         = current_time( 'timestamp' );
            $today_date     = wp_date( 'Y-m-d', $now_ts );

            if ( $appointments ) {
                foreach ( $appointments as $appt ) {
                    $status_meta = get_post_meta( $appt->ID, 'appointment_status', true );
                    if ( ! $status_meta ) {
                        $status_meta = 'pendente';
                    }
                    if ( 'finalizado e pago' === $status_meta ) {
                        $status_meta = 'finalizado_pago';
                    }
                    $date_value = get_post_meta( $appt->ID, 'appointment_date', true );
                    $time_value = get_post_meta( $appt->ID, 'appointment_time', true );
                    $datetime   = trim( $date_value . ' ' . ( $time_value ? $time_value : '00:00' ) );
                    $appt_ts    = $date_value ? strtotime( $datetime ) : 0;

                    if ( in_array( $status_meta, [ 'finalizado_pago', 'cancelado' ], true ) ) {
                        continue;
                    }

                    if ( 'pendente' === $status_meta ) {
                        if ( $appt_ts && $appt_ts < $now_ts ) {
                            $overdue[] = $appt;
                            continue;
                        }
                        if ( ! $appt_ts && $date_value && $date_value < $today_date ) {
                            $overdue[] = $appt;
                            continue;
                        }
                    }

                    if ( 'finalizado' === $status_meta ) {
                        if ( $date_value === $today_date ) {
                            $finalized_today[] = $appt;
                        }
                        continue;
                    }

                    if ( $appt_ts && $appt_ts >= $now_ts ) {
                        $upcoming[] = $appt;
                        continue;
                    }

                    if ( 'pendente' === $status_meta && $date_value && $date_value >= $today_date ) {
                        $upcoming[] = $appt;
                    }
                }
            }

            $sort_appointments = function( $items ) {
                if ( empty( $items ) ) {
                    return [];
                }
                usort(
                    $items,
                    function( $a, $b ) {
                        $date_a = get_post_meta( $a->ID, 'appointment_date', true );
                        $time_a = get_post_meta( $a->ID, 'appointment_time', true );
                        $date_b = get_post_meta( $b->ID, 'appointment_date', true );
                        $time_b = get_post_meta( $b->ID, 'appointment_time', true );
                        $dt_a   = $date_a ? strtotime( trim( $date_a . ' ' . ( $time_a ? $time_a : '00:00' ) ) ) : 0;
                        $dt_b   = $date_b ? strtotime( trim( $date_b . ' ' . ( $time_b ? $time_b : '00:00' ) ) ) : 0;
                        $dt_a   = $dt_a ? $dt_a : 0;
                        $dt_b   = $dt_b ? $dt_b : 0;
                        if ( $dt_a === $dt_b ) {
                            return $b->ID <=> $a->ID;
                        }
                        return $dt_b <=> $dt_a;
                    }
                );
                return $items;
            };

            $appointments_groups = [
                [
                    'items' => $sort_appointments( $overdue ),
                    'title' => __( 'Agendamentos pendentes (dias anteriores)', 'desi-pet-shower' ),
                    'class' => 'dps-appointments-group--overdue',
                ],
                [
                    'items' => $sort_appointments( $finalized_today ),
                    'title' => __( 'Atendimentos finalizados hoje', 'desi-pet-shower' ),
                    'class' => 'dps-appointments-group--finalized',
                ],
                [
                    'items' => $sort_appointments( $upcoming ),
                    'title' => __( 'Próximos atendimentos', 'desi-pet-shower' ),
                    'class' => 'dps-appointments-group--upcoming',
                ],
            ];

            $status_selector = function( $appt_id, $status ) use ( $status_labels, $visitor_only ) {
                return DPS_Base_Frontend::render_status_selector( $appt_id, $status, $status_labels, $visitor_only );
            };

            $charge_renderer = function( $appt_id ) {
                return DPS_Base_Frontend::build_charge_html( $appt_id, 'agendas' );
            };

            dps_get_template(
                'appointments-list.php',
                [
                    'groups'           => $appointments_groups,
                    'base_url'         => $base_url,
                    'visitor_only'     => $visitor_only,
                    'status_labels'    => $status_labels,
                    'status_selector'  => $status_selector,
                    'charge_renderer'  => $charge_renderer,
                ]
            );
        }

        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Compara agendamentos pela data e hora em ordem decrescente.
     *
     * Ordena agendamentos do mais recente para o mais antigo. Em caso de
     * data/hora iguais, ordena por ID (do maior para o menor).
     *
     * @since 1.9.0
     * @param object $first_appointment  Primeiro agendamento a comparar.
     * @param object $second_appointment Segundo agendamento a comparar.
     * @return int Resultado da comparação: -1, 0 ou 1.
     */
    public static function compare_desc( $first_appointment, $second_appointment ) {
        $first_date = get_post_meta( $first_appointment->ID, 'appointment_date', true );
        $first_time = get_post_meta( $first_appointment->ID, 'appointment_time', true );
        $second_date = get_post_meta( $second_appointment->ID, 'appointment_date', true );
        $second_time = get_post_meta( $second_appointment->ID, 'appointment_time', true );

        $first_datetime_timestamp = strtotime( trim( $first_date . ' ' . $first_time ) );
        $second_datetime_timestamp = strtotime( trim( $second_date . ' ' . $second_time ) );

        if ( $first_datetime_timestamp === $second_datetime_timestamp ) {
            return $second_appointment->ID <=> $first_appointment->ID;
        }

        return $second_datetime_timestamp <=> $first_datetime_timestamp;
    }

    /**
     * Recupera transações em aberto para um cliente.
     *
     * @since 1.9.0
     * @param int $client_id ID do cliente.
     * @return array Lista de transações em aberto.
     */
    public static function get_client_pending_transactions( $client_id ) {
        global $wpdb;
        $client_id = (int) $client_id;
        if ( ! $client_id ) {
            return [];
        }
        $table = $wpdb->prefix . 'dps_transacoes';
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( $exists !== $table ) {
            return [];
        }
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT data, descricao, valor, status FROM {$table} WHERE cliente_id = %d AND status = %s",
                $client_id,
                'em_aberto'
            )
        );
        if ( empty( $rows ) ) {
            return [];
        }
        $mapped = [];
        foreach ( $rows as $row ) {
            $mapped[] = [
                'data'      => $row->data,
                'descricao' => $row->descricao,
                'valor'     => isset( $row->valor ) ? (float) $row->valor : 0,
                'status'    => $row->status,
            ];
        }
        return $mapped;
    }
}
