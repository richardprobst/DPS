<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe responsável pelo frontend do plugin base. Contém métodos para renderizar
 * formulários e listas e para salvar/editar/excluir registros.
 */
class DPS_Base_Frontend {

    /**
     * Verifica se a renderização deve ser ignorada (durante requisições REST/AJAX).
     * 
     * Previne o erro "Falha ao publicar. A resposta não é um JSON válido" no
     * Block Editor ao evitar renderização de shortcodes durante requisições REST.
     *
     * @since 1.1.1
     * @return bool True se a renderização deve ser ignorada.
     */
    private static function should_skip_rendering() {
        return ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || wp_doing_ajax();
    }

    /**
     * Verifica se o usuário atual possui permissão para gerenciar o painel.
     * 
     * Permite acesso a administradores (manage_options) ou usuários com qualquer
     * capacidade DPS específica (recepção, etc).
     * 
     * NOTA: O painel será visível para usuários com QUALQUER capability DPS, mas
     * cada ação (salvar cliente, salvar pet, salvar agendamento, etc) é protegida
     * individualmente pela capability específica. Isso permite que usuários vejam
     * o painel mas apenas executem ações permitidas pelo seu perfil.
     *
     * @return bool
     */
    private static function can_manage() {
        return current_user_can( 'manage_options' ) 
            || current_user_can( 'dps_manage_clients' )
            || current_user_can( 'dps_manage_pets' )
            || current_user_can( 'dps_manage_appointments' );
    }

    /**
     * Normaliza a chave de status do agendamento para uso consistente no sistema.
     *
     * @param string $status Status bruto do agendamento.
     * @return string Status normalizado.
     */
    public static function normalize_status_key( $status ) {
        $normalized = strtolower( str_replace( ' ', '_', (string) $status ) );
        
        // Mapeamento de status legados ou variações
        $status_map = [
            'finalizado_e_pago' => 'finalizado_pago',
            'finalizado e pago' => 'finalizado_pago',
        ];
        
        return $status_map[ $normalized ] ?? $normalized;
    }



    /**
     * Retorna dados agregados de agendamentos multi-pet para cobrança consolidada.
     *
     * @param int $appt_id ID do agendamento.
     *
     * @return array|null
     */
    public static function get_multi_pet_charge_data( $appt_id ) {
        static $cache = [];

        if ( array_key_exists( $appt_id, $cache ) ) {
            return $cache[ $appt_id ];
        }

        $pet_ids = get_post_meta( $appt_id, 'appointment_pet_ids', true );
        if ( ! is_array( $pet_ids ) || count( $pet_ids ) < 2 ) {
            $cache[ $appt_id ] = null;
            return null;
        }

        $client_id = (int) get_post_meta( $appt_id, 'appointment_client_id', true );
        $date      = get_post_meta( $appt_id, 'appointment_date', true );
        $time      = get_post_meta( $appt_id, 'appointment_time', true );

        $normalized = array_map( 'intval', $pet_ids );
        sort( $normalized );
        $signature = implode( '-', $normalized );

        $related = get_posts( [
            'post_type'      => 'dps_agendamento',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [ 'key' => 'appointment_client_id', 'value' => $client_id ],
                [ 'key' => 'appointment_date', 'value' => $date ],
                [ 'key' => 'appointment_time', 'value' => $time ],
            ],
        ] );

        if ( empty( $related ) ) {
            $cache[ $appt_id ] = null;
            return null;
        }

        $ids       = [];
        $pet_names = [];
        $total     = 0;

        foreach ( $related as $item ) {
            $group_meta = get_post_meta( $item->ID, 'appointment_pet_ids', true );
            if ( ! is_array( $group_meta ) ) {
                continue;
            }
            $candidate = array_map( 'intval', $group_meta );
            sort( $candidate );
            if ( implode( '-', $candidate ) !== $signature ) {
                continue;
            }
            $ids[] = $item->ID;
            $single_pet_id = (int) get_post_meta( $item->ID, 'appointment_pet_id', true );
            if ( $single_pet_id ) {
                $pet_post = get_post( $single_pet_id );
                if ( $pet_post ) {
                    $pet_names[] = $pet_post->post_title;
                }
            }
            $total += (float) get_post_meta( $item->ID, 'appointment_total_value', true );
        }

        $ids = array_map( 'intval', $ids );
        if ( count( $ids ) < 2 ) {
            $cache[ $appt_id ] = null;
            return null;
        }

        sort( $ids );

        $cache[ $appt_id ] = [
            'ids'       => $ids,
            'pet_names' => array_values( array_unique( $pet_names ) ),
            'total'     => $total,
            'client_id' => $client_id,
            'date'      => $date,
            'time'      => $time,
            'signature' => $signature,
        ];

        return $cache[ $appt_id ];
    }

    /**
     * Carrega os agendamentos finalizados de forma incremental, reutilizando cache de meta.
     *
     * @return array
     */
    public static function get_history_appointments_data() {
        $batch_size = (int) apply_filters( 'dps_history_batch_size', 200 );
        $batch_size = $batch_size > 0 ? $batch_size : 50;

        $appointments    = [];
        $total_amount    = 0;
        $total_count     = 0;
        $pending_count   = 0;
        $pending_amount  = 0;
        $paid_count      = 0;
        $paid_amount     = 0;
        $cancelled_count = 0;
        $paged           = 1;

        do {
            $query = new WP_Query(
                [
                    'post_type'      => 'dps_agendamento',
                    'post_status'    => 'publish',
                    'posts_per_page' => $batch_size,
                    'fields'         => 'ids',
                    'no_found_rows'  => true,
                    'paged'          => $paged,
                    'meta_query'     => [
                        [
                            'key'     => 'appointment_status',
                            'value'   => [ 'finalizado', 'finalizado e pago', 'finalizado_pago', 'cancelado' ],
                            'compare' => 'IN',
                        ],
                    ],
                ]
            );

            $batch_ids = $query->posts;
            if ( empty( $batch_ids ) ) {
                break;
            }

            update_meta_cache( 'post', $batch_ids );

            foreach ( $batch_ids as $appt_id ) {
                $status_meta = get_post_meta( $appt_id, 'appointment_status', true );
                $status_key  = self::normalize_status_key( $status_meta );
                $total_count++;
                $appt_value = (float) get_post_meta( $appt_id, 'appointment_total_value', true );

                if ( 'cancelado' === $status_key ) {
                    $cancelled_count++;
                } elseif ( 'finalizado_pago' === $status_key ) {
                    $paid_count++;
                    $paid_amount  += $appt_value;
                    $total_amount += $appt_value;
                } else {
                    // Finalizado (pendente de pagamento)
                    $pending_count++;
                    $pending_amount += $appt_value;
                    $total_amount   += $appt_value;
                }

                $appointments[] = (object) [ 'ID' => (int) $appt_id ];
            }

            $paged++;
        } while ( count( $batch_ids ) === $batch_size );

        if ( $appointments ) {
            usort( $appointments, [ self::class, 'compare_appointments_desc' ] );
        }

        return [
            'appointments'    => $appointments,
            'total_amount'    => $total_amount,
            'total_count'     => $total_count,
            'pending_count'   => $pending_count,
            'pending_amount'  => $pending_amount,
            'paid_count'      => $paid_count,
            'paid_amount'     => $paid_amount,
            'cancelled_count' => $cancelled_count,
        ];
    }

    /**
     * Agrupa agendamentos por proximidade temporal para a aba Histórico.
     *
     * @return array {
     *     @type array $groups Lista de grupos formatada para o template de agendamentos.
     *     @type array $counts Contadores por grupo para destaques visuais.
     * }
     */
    public static function get_history_timeline_groups() {
        $appointments = get_posts(
            [
                'post_type'      => 'dps_agendamento',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'orderby'        => 'meta_value',
                'meta_key'       => 'appointment_date',
                'order'          => 'ASC',
                'fields'         => 'ids',
            ]
        );

        $now_ts     = current_time( 'timestamp' );
        $today_date = wp_date( 'Y-m-d', $now_ts );

        $buckets = [
            'today'    => [],
            'upcoming' => [],
            'past'     => [],
        ];

        foreach ( $appointments as $appt_id ) {
            $date_value = get_post_meta( $appt_id, 'appointment_date', true );
            $time_value = get_post_meta( $appt_id, 'appointment_time', true );
            $datetime   = trim( $date_value . ' ' . ( $time_value ? $time_value : '00:00' ) );
            $appt_ts    = $date_value ? strtotime( $datetime ) : 0;

            if ( $date_value === $today_date ) {
                $buckets['today'][] = (object) [ 'ID' => (int) $appt_id ];
                continue;
            }

            if ( $appt_ts && $appt_ts > $now_ts ) {
                $buckets['upcoming'][] = (object) [ 'ID' => (int) $appt_id ];
                continue;
            }

            if ( ( $appt_ts && $appt_ts <= $now_ts ) || ( $date_value && $date_value < $today_date ) ) {
                $buckets['past'][] = (object) [ 'ID' => (int) $appt_id ];
                continue;
            }

            $buckets['upcoming'][] = (object) [ 'ID' => (int) $appt_id ];
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

        return [
            'groups' => [
                [
                    'items' => $sort_appointments( $buckets['today'] ),
                    'title' => __( 'Atendimentos do dia', 'desi-pet-shower' ),
                    'class' => 'dps-appointments-group--today',
                ],
                [
                    'items' => $sort_appointments( $buckets['upcoming'] ),
                    'title' => __( 'Agendamentos futuros', 'desi-pet-shower' ),
                    'class' => 'dps-appointments-group--upcoming',
                ],
                [
                    'items' => $sort_appointments( $buckets['past'] ),
                    'title' => __( 'Atendimentos que já passaram', 'desi-pet-shower' ),
                    'class' => 'dps-appointments-group--past',
                ],
            ],
            'counts' => [
                'today'    => count( $buckets['today'] ),
                'upcoming' => count( $buckets['upcoming'] ),
                'past'     => count( $buckets['past'] ),
            ],
        ];
    }

    /**
     * Retorna o rótulo amigável para um status de agendamento.
     *
     * @param string $status Status bruto.
     *
     * @return string
     */
    public static function get_status_label( $status ) {
        switch ( $status ) {
            case 'finalizado_pago':
            case 'finalizado e pago':
                return __( 'Finalizado e pago', 'desi-pet-shower' );
            case 'cancelado':
                return __( 'Cancelado', 'desi-pet-shower' );
            case 'finalizado':
                return __( 'Finalizado', 'desi-pet-shower' );
            default:
                return $status;
        }
    }

    /**
     * Monta os botões de cobrança via WhatsApp, incluindo opção agregada quando aplicável.
     *
     * @param int    $appt_id  ID do agendamento.
     * @param string $context  Contexto de uso (base, agenda, historico).
     * @param bool   $allow_group Incluir ou não cobrança agregada.
     *
     * @return string
     */
    public static function build_charge_html( $appt_id, $context = 'base', $allow_group = true ) {
        $client_id  = (int) get_post_meta( $appt_id, 'appointment_client_id', true );
        $status     = get_post_meta( $appt_id, 'appointment_status', true );
        $appt_type  = get_post_meta( $appt_id, 'appointment_type', true );
        if ( ! $client_id || 'finalizado' !== $status || 'subscription' === $appt_type ) {
            return '-';
        }

        $client_post  = get_post( $client_id );
        $client_phone = $client_post ? get_post_meta( $client_id, 'client_phone', true ) : '';
        $total_value  = (float) get_post_meta( $appt_id, 'appointment_total_value', true );
        if ( empty( $client_phone ) || $total_value <= 0 ) {
            return '-';
        }

        $number = DPS_Phone_Helper::format_for_whatsapp( $client_phone );
        if ( empty( $number ) ) {
            return '-';
        }

        $pet_names = [];
        $pet_id    = (int) get_post_meta( $appt_id, 'appointment_pet_id', true );
        if ( $pet_id ) {
            $pet_post = get_post( $pet_id );
            if ( $pet_post ) {
                $pet_names[] = $pet_post->post_title;
            }
        }

        $client_name = $client_post ? $client_post->post_title : '';
        $pets_label  = implode( ', ', $pet_names );
        $valor_formatado = number_format_i18n( $total_value, 2 );
        $payment_link = get_post_meta( $appt_id, 'dps_payment_link', true );
        $default_link = 'https://link.mercadopago.com.br/desipetshower';
        $link_to_use  = $payment_link ? $payment_link : $default_link;

        $message = sprintf(
            __( 'Olá %s, tudo bem? O serviço do pet %s foi finalizado e o pagamento de R$ %s ainda está pendente. Para sua comodidade, você pode pagar via PIX celular 15 99160‑6299 ou utilizar o link: %s. Obrigado pela confiança!', 'desi-pet-shower' ),
            $client_name,
            $pets_label,
            $valor_formatado,
            $link_to_use
        );
        $message = apply_filters( 'dps_base_whatsapp_charge_message', $message, $appt_id, $context );
        $base_link = 'https://wa.me/' . $number . '?text=' . rawurlencode( $message );
        $html      = '<a href="' . esc_url( $base_link ) . '" target="_blank">' . esc_html__( 'Cobrar via WhatsApp', 'desi-pet-shower' ) . '</a>';

        if ( $allow_group ) {
            $group_data = self::get_multi_pet_charge_data( $appt_id );
            if ( $group_data ) {
                $anchor_id = min( $group_data['ids'] );
                if ( (int) $appt_id === (int) $anchor_id ) {
                    $group_names = implode( ', ', $group_data['pet_names'] );
                    $valor_total = number_format_i18n( $group_data['total'], 2 );
                    $date_fmt    = $group_data['date'] ? date_i18n( 'd/m/Y', strtotime( $group_data['date'] ) ) : '';
                    $time_fmt    = $group_data['time'];
                    $group_message = sprintf(
                        __( 'Olá %s, tudo bem? Finalizamos os atendimentos dos pets %s em %s às %s. O valor total ficou em R$ %s. Você pode pagar via PIX celular 15 99160‑6299 ou utilizar o link: %s. Caso tenha dúvidas estamos à disposição!', 'desi-pet-shower' ),
                        $client_name,
                        $group_names,
                        $date_fmt,
                        $time_fmt,
                        $valor_total,
                        $link_to_use
                    );
                    $group_message = apply_filters( 'dps_base_whatsapp_group_charge_message', $group_message, $appt_id, $context, $group_data );
                    $group_link = 'https://wa.me/' . $number . '?text=' . rawurlencode( $group_message );
                    $html      .= '<br><a href="' . esc_url( $group_link ) . '" target="_blank" class="dps-whatsapp-group">' . esc_html__( 'Cobrança conjunta', 'desi-pet-shower' ) . '</a>';
                }
            }
        }

        return $html;
    }

    /**
     * Obtém a URL base para redirecionamentos após ações do formulário.
     *
     * @return string
     */
    public static function get_current_page_url() {
        if ( isset( $_SERVER['REQUEST_URI'] ) ) {
            $request_uri = wp_unslash( $_SERVER['REQUEST_URI'] );
            if ( is_string( $request_uri ) && '' !== $request_uri ) {
                return esc_url_raw( home_url( $request_uri ) );
            }
        }

        $queried_id = function_exists( 'get_queried_object_id' ) ? get_queried_object_id() : 0;
        if ( $queried_id ) {
            $permalink = get_permalink( $queried_id );
            if ( $permalink && is_string( $permalink ) ) {
                return $permalink;
            }
        }

        global $post;
        if ( isset( $post->ID ) ) {
            $permalink = get_permalink( $post->ID );
            if ( $permalink && is_string( $permalink ) ) {
                return $permalink;
            }
        }

        return home_url();
    }

    private static function get_redirect_base_url() {
        if ( isset( $_POST['dps_redirect_url'] ) ) {
            $raw_redirect = wp_unslash( $_POST['dps_redirect_url'] );
            if ( is_string( $raw_redirect ) ) {
                $raw_redirect = trim( $raw_redirect );
                if ( '' !== $raw_redirect ) {
                    $validated = wp_validate_redirect( $raw_redirect, false );
                    if ( $validated ) {
                        return esc_url_raw( $validated );
                    }
                    if ( 0 === strpos( $raw_redirect, '/' ) || 0 === strpos( $raw_redirect, '?' ) ) {
                        $candidate = home_url( $raw_redirect );
                        $candidate_validated = wp_validate_redirect( $candidate, false );
                        if ( $candidate_validated ) {
                            return esc_url_raw( $candidate_validated );
                        }
                    }
                }
            }
        }
        $referer = wp_get_referer();
        if ( $referer ) {
            $referer_validated = wp_validate_redirect( $referer, false );
            if ( $referer_validated ) {
                return esc_url_raw( $referer_validated );
            }
        }

        $queried_id = function_exists( 'get_queried_object_id' ) ? get_queried_object_id() : 0;
        if ( $queried_id ) {
            $permalink = get_permalink( $queried_id );
            if ( $permalink && is_string( $permalink ) ) {
                return $permalink;
            }
        }

        global $post;
        if ( isset( $post->ID ) ) {
            $permalink = get_permalink( $post->ID );
            if ( $permalink && is_string( $permalink ) ) {
                return $permalink;
            }
        }

        return home_url();
    }

    /**
     * Monta a URL final de redirecionamento com base na aba desejada.
     *
     * @param string $tab Aba que deve ficar ativa após o redirecionamento.
     *
     * @return string
     */
    public static function get_redirect_url( $tab = '' ) {
        $base = self::get_redirect_base_url();
        $base = remove_query_arg(
            [ 
                'dps_delete', 'id', 'dps_edit', 'dps_view', 'tab', 'dps_action',
                'dps_nonce', 'dps_nonce_client_form', 'dps_nonce_pets', 
                'dps_nonce_agendamentos', 'dps_nonce_agendamentos_status', 'dps_nonce_passwords'
            ],
            $base
        );

        if ( $tab ) {
            $base = add_query_arg( 'tab', $tab, $base );
        }

        return $base;
    }

    /**
     * Redireciona para a aba desejada exibindo aviso de pendências, se existirem.
     *
     * @param int    $client_id ID do cliente relacionado ao agendamento.
     * @param string $tab       Aba para a qual o usuário deve ser redirecionado.
     */
    private static function redirect_with_pending_notice( $client_id, $tab = 'agendas', $context = 'page' ) {
        $redirect = self::get_redirect_url( $tab );
        $client_id = (int) $client_id;
        $pending_notice = [];
        if ( $client_id ) {
            $pending = self::get_client_pending_transactions( $client_id );
            if ( ! empty( $pending ) ) {
                $notice_key  = 'dps_pending_notice_' . get_current_user_id();
                $client_post = get_post( $client_id );
                set_transient(
                    $notice_key,
                    [
                        'client_name'  => $client_post ? $client_post->post_title : '',
                        'transactions' => $pending,
                    ],
                    MINUTE_IN_SECONDS * 10
                );
                $redirect = add_query_arg( 'dps_notice', 'pending_payments', $redirect );
                $pending_notice = [
                    'client_name'  => $client_post ? $client_post->post_title : '',
                    'transactions' => $pending,
                ];
            }
        }
        if ( 'ajax' === $context || wp_doing_ajax() ) {
            return [
                'redirect'       => $redirect,
                'pending_notice' => $pending_notice,
            ];
        }
        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Recupera transações em aberto para um cliente.
     *
     * @param int $client_id ID do cliente.
     *
     * @return array Lista de transações em aberto.
     */
    private static function get_client_pending_transactions( $client_id ) {
        return DPS_Appointments_Section_Renderer::get_client_pending_transactions( $client_id );
    }

    /**
     * Processa submissões de formulários
     */
    public static function handle_request() {
        // Determina qual campo de nonce verificar com base na ação
        $action = isset( $_POST['dps_action'] ) ? sanitize_key( wp_unslash( $_POST['dps_action'] ) ) : '';
        
        // Mapeia ações para nomes de nonce
        $nonce_map = [
            'save_client'               => 'dps_nonce_client_form',
            'save_pet'                  => 'dps_nonce_pets',
            'save_appointment'          => 'dps_nonce_agendamentos',
            'update_appointment_status' => 'dps_nonce_agendamentos_status',
        ];
        
        $nonce_field = isset( $nonce_map[ $action ] ) ? $nonce_map[ $action ] : 'dps_nonce';
        
        // Verifica nonce usando helper (não morre em falha para manter compatibilidade)
        if ( ! DPS_Request_Validator::verify_request_nonce( $nonce_field, 'dps_action', 'POST', false ) ) {
            self::handle_invalid_nonce( $action );
            return;
        }
        
        // Capability check removida daqui - cada ação verifica sua própria capability específica
        
        switch ( $action ) {
            case 'save_client':
                if ( ! current_user_can( 'dps_manage_clients' ) ) {
                    wp_die( __( 'Acesso negado.', 'desi-pet-shower' ) );
                }
                self::save_client();
                break;
            case 'save_pet':
                if ( ! current_user_can( 'dps_manage_pets' ) ) {
                    wp_die( __( 'Acesso negado.', 'desi-pet-shower' ) );
                }
                self::save_pet();
                break;
            case 'save_appointment':
                if ( ! current_user_can( 'dps_manage_appointments' ) ) {
                    wp_die( __( 'Acesso negado.', 'desi-pet-shower' ) );
                }
                self::save_appointment();
                break;
            case 'update_appointment_status':
                if ( ! current_user_can( 'dps_manage_appointments' ) ) {
                    wp_die( __( 'Acesso negado.', 'desi-pet-shower' ) );
                }
                self::update_appointment_status();
                break;
            default:
                break;
        }
    }

    /**
     * Adiciona mensagem de erro e redireciona quando a verificação de nonce falha.
     *
     * @param string $action Ação submetida pelo formulário.
     */
    private static function handle_invalid_nonce( $action ) {
        $tabs = [
            'save_client'               => 'clientes',
            'save_pet'                  => 'pets',
            'save_appointment'          => 'agendas',
            'update_appointment_status' => 'agendas',
        ];

        DPS_Message_Helper::add_error( __( 'Não foi possível validar sua sessão. Atualize a página e tente novamente.', 'desi-pet-shower' ) );

        $redirect_tab = isset( $tabs[ $action ] ) ? $tabs[ $action ] : '';
        wp_safe_redirect( self::get_redirect_url( $redirect_tab ) );
        exit;
    }

    /**
     * Envia respostas JSON padronizadas para requisições AJAX do formulário.
     *
     * @param bool  $success      Define se a operação foi bem-sucedida.
     * @param array $data         Dados adicionais para o payload.
     * @param int   $status_code  Código HTTP a ser retornado.
     */
    private static function send_ajax_response( $success, array $data = [], $status_code = 200 ) {
        $messages_html = DPS_Message_Helper::display_messages();
        $payload = array_merge(
            [
                'messages_html' => $messages_html,
            ],
            $data
        );

        if ( $success ) {
            wp_send_json_success( $payload, $status_code );
        }

        wp_send_json_error( $payload, $status_code );
    }

    /**
     * Processa logout do painel DPS via query string.
     * 
     * Este método é chamado via hook 'init' antes da renderização do shortcode.
     * Remove cookies de role do usuário e redireciona para a URL limpa.
     * Requer nonce válido para proteção CSRF.
     * 
     * @since 1.0.2
     * @return void Redireciona e encerra execução se logout for processado, retorna void caso contrário.
     */
    public static function handle_logout() {
        // Verifica se parâmetro de logout está presente
        if ( ! isset( $_GET['dps_logout'] ) ) {
            return;
        }
        
        // Verifica nonce para proteção CSRF usando helper
        if ( ! DPS_Request_Validator::verify_admin_action( 'dps_logout', null, '_wpnonce', false ) ) {
            wp_die( __( 'Ação não autorizada.', 'desi-pet-shower' ) );
        }
        
        // Remove role cookies. Define caminho "/" para que os cookies sejam removidos em todo o site.
        setcookie( 'dps_base_role', '', time() - 3600, '/' );
        setcookie( 'dps_role', '', time() - 3600, '/' );
        
        // Redireciona removendo parâmetros da URL para evitar loops
        $current_url = ( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '' );
        $redirect_url = remove_query_arg( [ 'dps_logout', '_wpnonce', 'tab', 'dps_edit', 'id', 'dps_view' ], $current_url );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Processa exclusões de registros via query string
     */
    public static function handle_delete() {
        if ( ! self::can_manage() ) {
            return;
        }

        $type = isset( $_GET['dps_delete'] ) ? sanitize_key( wp_unslash( $_GET['dps_delete'] ) ) : '';
        $id   = isset( $_GET['id'] ) ? intval( wp_unslash( $_GET['id'] ) ) : 0;
        if ( ! $id ) {
            return;
        }
        $nonce_value = isset( $_GET['dps_nonce'] )
            ? sanitize_text_field( wp_unslash( $_GET['dps_nonce'] ) )
            : '';
        if ( ! wp_verify_nonce( $nonce_value, 'dps_delete' ) ) {
            wp_die( __( 'Ação não autorizada.', 'desi-pet-shower' ) );
        }
        // Verifica tipo e exclui
        switch ( $type ) {
            case 'client':
                if ( ! current_user_can( 'dps_manage_clients' ) ) {
                    wp_die( __( 'Acesso negado.', 'desi-pet-shower' ) );
                }
                wp_delete_post( $id, true );
                break;
            case 'pet':
                if ( ! current_user_can( 'dps_manage_pets' ) ) {
                    wp_die( __( 'Acesso negado.', 'desi-pet-shower' ) );
                }
                wp_delete_post( $id, true );
                break;
            case 'appointment':
                if ( ! current_user_can( 'dps_manage_appointments' ) ) {
                    wp_die( __( 'Acesso negado.', 'desi-pet-shower' ) );
                }
                // Exclui o agendamento
                wp_delete_post( $id, true );
                do_action( 'dps_finance_cleanup_for_appointment', $id );
                break;
            default:
                return;
        }
        // Redireciona para a aba apropriada após exclusão.
        // Remove parâmetros de exclusão da URL para evitar loops de redirecionamento.
        $tab           = ( $type === 'appointment' ) ? 'agendas' : ( $type === 'pet' ? 'pets' : 'clientes' );
        $redirect_url  = self::get_redirect_url( $tab );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Renderiza a aplicação no frontend (abas para clientes, pets e agendamentos)
     */
    public static function render_app() {
        // Evita renderizar o shortcode durante requisições REST API (Block Editor) ou AJAX
        // para prevenir o erro "Falha ao publicar. A resposta não é um JSON válido."
        if ( self::should_skip_rendering() ) {
            return '';
        }

        // Desabilita cache da página para garantir dados sempre atualizados
        if ( class_exists( 'DPS_Cache_Control' ) ) {
            DPS_Cache_Control::force_no_cache();
        }

        // Garante que o CSS/JS do painel estejam carregados mesmo em contextos
        // onde wp_enqueue_scripts não foi executado (ex.: shortcodes renderizados
        // em builders ou pré-visualizações).
        DPS_Base_Plugin::enqueue_frontend_assets();

        $can_manage = self::can_manage();

        // Verifica se há visualização específica (detalhes do cliente)
        // Verificação de permissão movida para ANTES da chamada para prevenir acesso não autorizado
        if ( isset( $_GET['dps_view'] ) && 'client' === $_GET['dps_view'] && isset( $_GET['id'] ) ) {
            if ( ! is_user_logged_in() || ! $can_manage ) {
                $login_url = wp_login_url( DPS_URL_Builder::safe_get_permalink() );
                return '<p>' . esc_html__( 'Você precisa estar logado com as permissões adequadas para acessar este painel.', 'desi-pet-shower' ) . ' <a href="' . esc_url( $login_url ) . '">' . esc_html__( 'Fazer login', 'desi-pet-shower' ) . '</a></p>';
            }
            $client_id = intval( $_GET['id'] );
            return self::render_client_page( $client_id );
        }

        // Verifica se o usuário atual está logado e possui permissão para gerenciar o painel
        if ( ! is_user_logged_in() || ! $can_manage ) {
            $login_url = wp_login_url( DPS_URL_Builder::safe_get_permalink() );
            return '<p>' . esc_html__( 'Você precisa estar logado com as permissões adequadas para acessar este painel.', 'desi-pet-shower' ) . ' <a href="' . esc_url( $login_url ) . '">' . esc_html__( 'Fazer login', 'desi-pet-shower' ) . '</a></p>';
        }
        
        // Sempre mostrar interface completa para usuários administradores
        ob_start();
        echo '<div class="dps-base-wrapper">';
        echo '<h1 class="dps-page-title">' . esc_html__( 'Painel de Gestão DPS', 'desi-pet-shower' ) . '</h1>';
        
        // Exibe mensagens de feedback (sucesso, erro, aviso)
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in DPS_Message_Helper
        echo DPS_Message_Helper::display_messages();
        
        // Container de navegação com toggle mobile
        echo '<nav class="dps-nav-container" aria-label="' . esc_attr__( 'Navegação do painel', 'desi-pet-shower' ) . '">';
        echo '<button type="button" class="dps-nav-mobile-toggle" aria-expanded="false" aria-controls="dps-main-nav">' . esc_html__( 'Selecionar seção', 'desi-pet-shower' ) . '</button>';
        echo '<ul class="dps-nav" id="dps-main-nav" role="tablist">';
        echo '<li role="presentation"><a href="#" class="dps-tab-link" data-tab="agendas" role="tab">' . esc_html__( 'Agendamentos', 'desi-pet-shower' ) . '</a></li>';
        echo '<li role="presentation"><a href="#" class="dps-tab-link" data-tab="clientes" role="tab">' . esc_html__( 'Clientes', 'desi-pet-shower' ) . '</a></li>';
        echo '<li role="presentation"><a href="#" class="dps-tab-link" data-tab="pets" role="tab">' . esc_html__( 'Pets', 'desi-pet-shower' ) . '</a></li>';
        // Permite que add-ons adicionem abas após os módulos principais
        do_action( 'dps_base_nav_tabs_after_pets', false );
        echo '<li role="presentation"><a href="#" class="dps-tab-link" data-tab="historico" role="tab">' . esc_html__( 'Histórico', 'desi-pet-shower' ) . '</a></li>';
        // Espaço para add-ons exibirem abas após o histórico
        do_action( 'dps_base_nav_tabs_after_history', false );
        echo '</ul>';
        echo '</nav>';
        // Seções principais na nova ordem
        echo self::section_agendas( false );
        echo self::section_clients();
        echo self::section_pets();
        // Seções adicionais posicionadas entre os módulos principais e o histórico
        do_action( 'dps_base_sections_after_pets', false );
        echo self::section_history();
        // Seções adicionadas após o histórico
        do_action( 'dps_base_sections_after_history', false );
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Renderiza o shortcode [dps_configuracoes].
     * 
     * Página de configurações do sistema DPS com sistema de abas extensível.
     * Permite que administradores gerenciem configurações diretamente no front-end.
     * 
     * @since 2.0.0 Reativado com sistema de abas e segurança aprimorada.
     * @return string HTML da página de configurações.
     */
    public static function render_settings() {
        // Evita renderizar o shortcode durante requisições REST API (Block Editor) ou AJAX
        // para prevenir o erro "Falha ao publicar. A resposta não é um JSON válido."
        if ( self::should_skip_rendering() ) {
            return '';
        }

        // Desabilita cache da página para garantir dados sempre atualizados
        if ( class_exists( 'DPS_Cache_Control' ) ) {
            DPS_Cache_Control::force_no_cache();
        }

        // Garante que o CSS/JS estejam carregados
        DPS_Base_Plugin::enqueue_frontend_assets();

        // Delega para a nova classe de configurações
        return DPS_Settings_Frontend::render();
    }

    /**
     * Obtém lista completa de clientes cadastrados.
     *
     * @return array Lista de posts do tipo dps_cliente.
     */
    public static function get_clients() {
        return DPS_Query_Helper::get_all_posts_by_type( 'dps_cliente' );
    }

    /**
     * Obtém lista paginada de pets.
     *
     * @param int $page Número da página (default: 1).
     * @return WP_Query Objeto de consulta com pets paginados.
     */
    public static function get_pets( $page = 1 ) {
        return DPS_Query_Helper::get_paginated_posts( 'dps_pet', $page, DPS_BASE_PETS_PER_PAGE );
    }

    /**
     * Seção de clientes: listagem e atalhos administrativos.
     *
     * @since 1.0.0
     * @since 2.1.0 Delegado para DPS_Clients_Section_Renderer.
     * @return string HTML da seção de clientes.
     */
    private static function section_clients() {
        return DPS_Clients_Section_Renderer::render();
    }

    /**
     * Prepara os dados necessários para a seção de clientes.
     *
     * @since 1.0.0
     * @since 2.1.0 Delegado para DPS_Clients_Section_Renderer.
     * @return array Dados estruturados para o template.
     */
    private static function prepare_clients_section_data() {
        return DPS_Clients_Section_Renderer::prepare_data();
    }

    /**
     * Pré-carrega metadados críticos dos clientes.
     *
     * @since 1.0.0
     * @since 2.1.0 Delegado para DPS_Clients_Section_Renderer.
     * @param array $clients Lista de posts de clientes.
     * @return array
     */
    private static function build_clients_meta( $clients ) {
        return DPS_Clients_Section_Renderer::build_clients_meta( $clients );
    }

    /**
     * Retorna contagem de pets para cada cliente informado.
     *
     * @since 1.0.0
     * @since 2.1.0 Delegado para DPS_Clients_Section_Renderer.
     * @param array $clients Lista de posts de clientes.
     * @return array
     */
    private static function get_clients_pets_counts( $clients ) {
        return DPS_Clients_Section_Renderer::get_clients_pets_counts( $clients );
    }

    /**
     * Calcula métricas administrativas da lista de clientes.
     *
     * @since 1.0.0
     * @since 2.1.0 Delegado para DPS_Clients_Section_Renderer.
     * @param array $clients     Lista de posts de clientes.
     * @param array $client_meta Metadados principais dos clientes.
     * @param array $pets_counts Contagem de pets por cliente.
     * @return array
     */
    private static function summarize_clients_data( $clients, $client_meta, $pets_counts ) {
        return DPS_Clients_Section_Renderer::summarize_clients_data( $clients, $client_meta, $pets_counts );
    }

    /**
     * Filtra lista de clientes conforme necessidade administrativa.
     *
     * @since 1.0.0
     * @since 2.1.0 Delegado para DPS_Clients_Section_Renderer.
     * @param array  $clients     Lista de posts de clientes.
     * @param array  $client_meta Metadados principais dos clientes.
     * @param array  $pets_counts Contagem de pets por cliente.
     * @param string $filter      Filtro ativo.
     * @return array
     */
    private static function filter_clients_list( $clients, $client_meta, $pets_counts, $filter ) {
        return DPS_Clients_Section_Renderer::filter_clients_list( $clients, $client_meta, $pets_counts, $filter );
    }

    /**
     * Renderiza a seção de clientes usando template.
     *
     * @since 1.0.0
     * @since 2.1.0 Delegado para DPS_Clients_Section_Renderer.
     * @param array $data Dados preparados para renderização.
     * @return string HTML da seção.
     */
    private static function render_clients_section( $data ) {
        return DPS_Clients_Section_Renderer::render_section( $data );
    }

    /**
     * Seção de pets: formulário e listagem.
     *
     * @since 1.0.0
     * @since 2.1.0 Delegado para DPS_Pets_Section_Renderer.
     * @return string HTML da seção de pets.
     */
    private static function section_pets() {
        return DPS_Pets_Section_Renderer::render();
    }

    /**
     * Prepara os dados necessários para a seção de pets.
     *
     * @since 1.0.4
     * @since 2.1.0 Delegado para DPS_Pets_Section_Renderer.
     * @return array Dados estruturados para o template.
     */
    private static function prepare_pets_section_data() {
        return DPS_Pets_Section_Renderer::prepare_data();
    }

    /**
     * Busca pets com filtro aplicado.
     *
     * @since 1.0.5
     * @since 2.1.0 Delegado para DPS_Pets_Section_Renderer.
     * @param int    $page   Número da página.
     * @param string $filter Filtro a ser aplicado.
     * @return WP_Query
     */
    private static function get_filtered_pets( $page, $filter ) {
        return DPS_Pets_Section_Renderer::get_filtered_pets( $page, $filter );
    }

    /**
     * Calcula estatísticas dos pets para o painel de resumo.
     *
     * @since 1.0.5
     * @since 2.1.0 Delegado para DPS_Pets_Section_Renderer.
     * @param array $pet_ids Lista de IDs de pets.
     * @return array
     */
    private static function build_pets_statistics( $pet_ids ) {
        return DPS_Pets_Section_Renderer::build_pets_statistics( $pet_ids );
    }

    /**
     * Busca estatísticas de agendamentos para cada pet.
     *
     * @since 1.0.5
     * @since 2.1.0 Delegado para DPS_Pets_Section_Renderer.
     * @param array $pet_ids Lista de IDs de pets.
     * @return array
     */
    private static function get_pets_appointments_stats( $pet_ids ) {
        return DPS_Pets_Section_Renderer::get_pets_appointments_stats( $pet_ids );
    }

    /**
     * Obtém dados do consentimento de tosa com máquina do cliente.
     *
     * @param int $client_id ID do cliente.
     * @return array{status:string,has_consent:bool,granted_at:string,revoked_at:string}
     */
    public static function get_client_tosa_consent_data( $client_id ) {
        $client_id = absint( $client_id );

        if ( ! $client_id ) {
            return [
                'status'      => 'missing',
                'has_consent' => false,
                'granted_at'  => '',
                'revoked_at'  => '',
            ];
        }

        $status     = get_post_meta( $client_id, 'dps_consent_tosa_maquina_status', true );
        $granted_at = get_post_meta( $client_id, 'dps_consent_tosa_maquina_granted_at', true );
        $revoked_at = get_post_meta( $client_id, 'dps_consent_tosa_maquina_revoked_at', true );

        $state = 'missing';
        if ( 'granted' === $status && empty( $revoked_at ) ) {
            $state = 'granted';
        } elseif ( ! empty( $revoked_at ) || 'revoked' === $status ) {
            $state = 'revoked';
        }

        return [
            'status'      => $state,
            'has_consent' => ( 'granted' === $state ),
            'granted_at'  => ( $granted_at && false !== strtotime( $granted_at ) ) ? date_i18n( 'd/m/Y', strtotime( $granted_at ) ) : '',
            'revoked_at'  => ( $revoked_at && false !== strtotime( $revoked_at ) ) ? date_i18n( 'd/m/Y', strtotime( $revoked_at ) ) : '',
        ];
    }
    
    /**
     * Renderiza a seção de pets usando template.
     *
     * @since 1.0.4
     * @since 2.1.0 Delegado para DPS_Pets_Section_Renderer.
     * @param array $data Dados preparados para renderização.
     * @return string HTML da seção.
     */
    private static function render_pets_section( $data ) {
        return DPS_Pets_Section_Renderer::render_section( $data );
    }

    /**
     * Seção de agendamentos: formulário e listagem.
     *
     * Delega para DPS_Appointments_Section_Renderer.
     *
     * @since 1.0.0
     * @since 1.9.0 Extraído para DPS_Appointments_Section_Renderer.
     * @param bool $visitor_only Se true, exibe apenas a listagem sem formulário.
     * @return string HTML da seção de agendamentos.
     */
    private static function section_agendas( $visitor_only = false ) {
        return DPS_Appointments_Section_Renderer::render( $visitor_only );
    }

    /**
     * Prepara os dados necessários para a seção de agendamentos.
     *
     * Delega para DPS_Appointments_Section_Renderer.
     *
     * @since 1.0.2
     * @since 1.9.0 Extraído para DPS_Appointments_Section_Renderer.
     * @param bool  $visitor_only Se true, não prepara dados do formulário.
     * @param array $overrides    Valores para sobrescrever os padrões.
     * @return array Dados estruturados para renderização.
     */
    public static function prepare_appointments_section_data( $visitor_only = false, array $overrides = [] ) {
        return DPS_Appointments_Section_Renderer::prepare_data( $visitor_only, $overrides );
    }

    /**
     * Renderiza a seção de agendamentos com os dados preparados.
     *
     * Delega para DPS_Appointments_Section_Renderer.
     *
     * @since 1.0.2
     * @since 1.9.0 Extraído para DPS_Appointments_Section_Renderer.
     * @param array $data         Dados preparados por prepare_appointments_section_data().
     * @param bool  $visitor_only Se true, exibe apenas a listagem sem formulário.
     * @param array $options      Opções de renderização.
     * @return string HTML da seção.
     */
    public static function render_appointments_section( array $data, $visitor_only = false, array $options = [] ) {
        return DPS_Appointments_Section_Renderer::render_section( $data, $visitor_only, $options );
    }

    /**
     * Seção dedicada ao histórico de atendimentos já realizados.
     */
    private static function section_history() {
        return DPS_History_Section_Renderer::render();
    }
    /**
     * Compara agendamentos pela data e hora em ordem decrescente.
     *
     * Delega para DPS_Appointments_Section_Renderer.
     *
     * @since 1.9.0 Extraído para DPS_Appointments_Section_Renderer.
     * @param object $first_appointment  Primeiro agendamento a comparar.
     * @param object $second_appointment Segundo agendamento a comparar.
     * @return int Resultado da comparação: -1, 0 ou 1.
     */
    private static function compare_appointments_desc( $first_appointment, $second_appointment ) {
        return DPS_Appointments_Section_Renderer::compare_desc( $first_appointment, $second_appointment );
    }

    /**
     * Renderiza o seletor de status em linha para os agendamentos.
     *
     * @param int   $appt_id        ID do agendamento.
     * @param string $current_status Status atual salvo na meta.
     * @param array  $status_labels  Rótulos disponíveis para exibição.
     * @param bool   $visitor_only   Indica se o usuário atual não pode gerenciar registros.
     *
     * @return string HTML do seletor ou do texto de status.
     */
    public static function render_status_selector( $appt_id, $current_status, $status_labels, $visitor_only ) {
        $status = $current_status ? $current_status : 'pendente';
        if ( 'finalizado e pago' === $status ) {
            $status = 'finalizado_pago';
        }
        if ( $visitor_only ) {
            $label = $status_labels[ $status ] ?? ucwords( str_replace( '_', ' ', $status ) );
            return esc_html( $label );
        }
        $nonce_field = wp_nonce_field( 'dps_action', 'dps_nonce_agendamentos_status', true, false );
        $html  = '<form method="post" class="dps-inline-status-form">';
        $html .= '<input type="hidden" name="dps_action" value="update_appointment_status">';
        $html .= $nonce_field;
        $html .= '<input type="hidden" name="appointment_id" value="' . esc_attr( $appt_id ) . '">';
        $html .= '<input type="hidden" name="dps_redirect_url" value="' . esc_attr( self::get_current_page_url() ) . '">';
        $html .= '<select name="appointment_status">';
        foreach ( $status_labels as $key => $label ) {
            $html .= '<option value="' . esc_attr( $key ) . '"' . selected( $status, $key, false ) . '>' . esc_html( $label ) . '</option>';
        }
        $html .= '</select>';
        $html .= '<noscript><button type="submit" class="button button-secondary button-small">' . esc_html__( 'Atualizar', 'desi-pet-shower' ) . '</button></noscript>';
        $html .= '</form>';
        return $html;
    }

    /**
     * Salva cliente (inserção ou atualização).
     * 
     * Delegado para DPS_Client_Handler (Fase 2.1).
     */
    private static function save_client() {
        DPS_Client_Handler::save_from_request( [ __CLASS__, 'get_redirect_url' ] );
    }

    /**
     * Salva pet (inserção ou atualização).
     * 
     * Delegado para DPS_Pet_Handler (Fase 2.1).
     */
    private static function save_pet() {
        DPS_Pet_Handler::save_from_request( [ __CLASS__, 'get_redirect_url' ] );
    }

    /**
     * Atualiza status de um agendamento existente.
     *
     * Delegado para DPS_Appointment_Handler (Fase 2.1).
     *
     * @since 1.0.0
     * @return void
     */
    private static function update_appointment_status() {
        $client_id = DPS_Appointment_Handler::update_status( [ __CLASS__, 'get_redirect_url' ] );
        self::redirect_with_pending_notice( $client_id );
    }

    /**
     * Salva agendamento (inserção ou atualização).
     *
     * Fachada que delega lógica de negócio para DPS_Appointment_Handler
     * e mantém a orquestração de respostas AJAX e redirecionamentos.
     *
     * @since 1.0.0
     * @since 2.0.0 Refatorado para delegar ao handler.
     * @return void
     */
    private static function save_appointment( $context = 'page' ) {
        $is_ajax = ( 'ajax' === $context ) || wp_doing_ajax();

        if ( ! current_user_can( 'dps_manage_appointments' ) ) {
            if ( $is_ajax ) {
                self::send_ajax_response(
                    false,
                    [
                        'message'  => __( 'Acesso negado.', 'desi-pet-shower' ),
                        'redirect' => self::get_redirect_url( 'agendas' ),
                    ],
                    403
                );
            }
            wp_die( __( 'Acesso negado.', 'desi-pet-shower' ) );
        }

        // Passo 1: Validar e sanitizar dados do formulário.
        $data = DPS_Appointment_Handler::validate_and_sanitize_data();
        if ( null === $data ) {
            if ( $is_ajax ) {
                self::send_ajax_response(
                    false,
                    [
                        'redirect' => self::get_redirect_url( 'agendas' ),
                    ],
                    400
                );
            }
            wp_safe_redirect( self::get_redirect_url( 'agendas' ) );
            exit;
        }

        if ( DPS_Appointment_Handler::requires_tosa_consent( $data ) ) {
            $consent_data = self::get_client_tosa_consent_data( $data['client_id'] );
            if ( empty( $consent_data['has_consent'] ) ) {
                DPS_Message_Helper::add_warning(
                    __( 'Consentimento de tosa com máquina pendente para este cliente. Gere o link antes de confirmar o atendimento.', 'desi-pet-shower' )
                );
            }
        }

        $appt_type = $data['appt_type'];
        $edit_id   = $data['edit_id'];
        $pet_ids   = $data['pet_ids'];

        // Passo 2: Decidir qual fluxo seguir e delegar para método especializado.
        $result = false;

        if ( ! $edit_id && 'subscription' === $appt_type ) {
            $result = DPS_Appointment_Handler::create_subscription_appointments( $data, $context );
        } elseif ( ! $edit_id && 'simple' === $appt_type && count( $pet_ids ) > 1 ) {
            $result = DPS_Appointment_Handler::create_multi_pet_appointments( $data, $context );
        } else {
            $result = DPS_Appointment_Handler::save_single_appointment( $data, $context );
        }

        if ( $is_ajax ) {
            if ( false === $result ) {
                self::send_ajax_response(
                    false,
                    [
                        'redirect' => self::get_redirect_url( 'agendas' ),
                    ],
                    400
                );
            }

            $redirect_data = self::redirect_with_pending_notice( $result['client_id'], 'agendas', 'ajax' );

            self::send_ajax_response(
                true,
                array_merge(
                    [
                        'appointment_id'   => isset( $result['appointment_id'] ) ? $result['appointment_id'] : 0,
                        'appointment_ids'  => isset( $result['appointment_ids'] ) ? $result['appointment_ids'] : [],
                        'appointment_type' => isset( $result['appointment_type'] ) ? $result['appointment_type'] : '',
                    ],
                    $redirect_data ? $redirect_data : []
                )
            );
            return;
        }

        if ( false === $result ) {
            wp_safe_redirect( self::get_redirect_url( 'agendas' ) );
            exit;
        }

        self::redirect_with_pending_notice( $result['client_id'] );
    }

    /**
     * Renderiza a página de detalhes do cliente.
     * Delega para DPS_Client_Page_Renderer (Fase 2.1).
     *
     * @since 1.0.0
     * @param int $client_id ID do cliente.
     * @return string HTML da página.
     */
    private static function render_client_page( $client_id ) {
        return DPS_Client_Page_Renderer::render( $client_id );
    }

    /**
     * Retorna dataset de raças por espécie, incluindo lista de populares.
     *
     * @since 1.0.0
     * @return array
     */
    private static function get_breed_dataset() {
        return DPS_Breed_Registry::get_dataset();
    }

    /**
     * Monta lista de opções de raças para a espécie selecionada.
     *
     * @since 1.0.0
     * @param string $species Código da espécie (cao/gato/outro).
     * @return array Lista ordenada com populares primeiro.
     */
    private static function get_breed_options_for_species( $species ) {
        return DPS_Breed_Registry::get_options_for_species( $species );
    }

    /**
     * Renderiza o formulário de agendamento em modo modal via AJAX.
     */
    public static function ajax_render_appointment_form() {
        check_ajax_referer( 'dps_modal_appointment', 'nonce' );

        // Aceita tanto a capability customizada quanto manage_options (admin)
        // para manter consistência com a verificação da página de agenda
        if ( ! current_user_can( 'dps_manage_appointments' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Acesso negado.', 'desi-pet-shower' ) ], 403 );
        }

        $pref_client  = isset( $_POST['pref_client'] ) ? absint( wp_unslash( $_POST['pref_client'] ) ) : 0;
        $pref_pet     = isset( $_POST['pref_pet'] ) ? absint( wp_unslash( $_POST['pref_pet'] ) ) : 0;
        $redirect_url = isset( $_POST['redirect_url'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_url'] ) ) : '';

        $overrides = [
            'pref_client' => $pref_client,
            'pref_pet'    => $pref_pet,
            'force_new'   => true,
        ];

        if ( $redirect_url ) {
            $overrides['base_url']    = $redirect_url;
            $overrides['current_url'] = $redirect_url;
        }

        $data = self::prepare_appointments_section_data( false, $overrides );

        $html = self::render_appointments_section(
            $data,
            false,
            [
                'context'      => 'modal',
                'include_list' => false,
            ]
        );

        wp_send_json_success(
            [
                'html' => $html,
            ]
        );
    }

    /**
     * Processa submissões do formulário de agendamento via modal (AJAX).
     */
    public static function ajax_save_appointment_modal() {
        check_ajax_referer( 'dps_modal_appointment', 'nonce' );

        // Verifica nonce secundário usando helper
        if ( ! DPS_Request_Validator::verify_request_nonce( 'dps_nonce_agendamentos', 'dps_action', 'POST', false ) ) {
            DPS_Message_Helper::add_error( __( 'Não foi possível validar sua sessão. Atualize a página e tente novamente.', 'desi-pet-shower' ) );
            self::send_ajax_response(
                false,
                [
                    'redirect' => self::get_redirect_url( 'agendas' ),
                ],
                400
            );
        }

        // Aceita tanto a capability customizada quanto manage_options (admin)
        // para manter consistência com a verificação da página de agenda
        if ( ! current_user_can( 'dps_manage_appointments' ) && ! current_user_can( 'manage_options' ) ) {
            self::send_ajax_response(
                false,
                [
                    'message'  => __( 'Acesso negado.', 'desi-pet-shower' ),
                    'redirect' => self::get_redirect_url( 'agendas' ),
                ],
                403
            );
        }

        $_POST['dps_action'] = 'save_appointment';
        self::save_appointment( 'ajax' );
    }

    /**
     * AJAX handler para buscar horários disponíveis para uma data específica
     * 
     * @since 1.0.0
     */
    public static function ajax_get_available_times() {
        // Validação de nonce e permissões
        check_ajax_referer( 'dps_action', 'nonce' );
        
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissão negada.', 'desi-pet-shower' ) ] );
        }
        
        // Sanitiza e valida a data recebida
        $date = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
        $appointment_id = isset( $_POST['appointment_id'] ) ? intval( $_POST['appointment_id'] ) : 0;
        
        if ( empty( $date ) ) {
            wp_send_json_error( [ 'message' => __( 'Data não fornecida.', 'desi-pet-shower' ) ] );
        }
        
        // Valida formato da data
        $date_obj = DateTime::createFromFormat( 'Y-m-d', $date );
        if ( ! $date_obj || $date_obj->format( 'Y-m-d' ) !== $date ) {
            wp_send_json_error( [ 'message' => __( 'Data inválida.', 'desi-pet-shower' ) ] );
        }
        
        // Busca agendamentos existentes nesta data
        $args = [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'     => 'appointment_date',
                    'value'   => $date,
                    'compare' => '=',
                ],
            ],
        ];
        
        $appointments = get_posts( $args );
        
        // Coleta horários já ocupados
        $occupied_times = [];
        foreach ( $appointments as $appt ) {
            // Ignora o agendamento atual se estiver editando
            if ( $appointment_id && $appt->ID === $appointment_id ) {
                continue;
            }
            
            $time = get_post_meta( $appt->ID, 'appointment_time', true );
            if ( $time ) {
                $occupied_times[] = $time;
            }
        }
        
        // Define horários de trabalho (8h às 18h, intervalos de 30 minutos)
        $all_times = [];
        for ( $hour = 8; $hour <= 18; $hour++ ) {
            foreach ( [ '00', '30' ] as $min ) {
                // Não adicionar 18:30
                if ( $hour === 18 && $min === '30' ) {
                    break;
                }
                
                $time = sprintf( '%02d:%s', $hour, $min );
                $is_occupied = in_array( $time, $occupied_times, true );
                
                $all_times[] = [
                    'value'     => $time,
                    'label'     => $time . ( $is_occupied ? ' - ' . __( 'Ocupado', 'desi-pet-shower' ) : '' ),
                    'available' => ! $is_occupied,
                ];
            }
        }
        
        wp_send_json_success( [
            'times' => $all_times,
            'date'  => $date,
        ] );
    }

    /**
     * AJAX: Salva notas internas do cliente.
     *
     * @since 1.3.0
     */
    public static function ajax_save_client_notes() {
        // Verifica nonce e capacidade usando helper
        $client_id = isset( $_POST['client_id'] ) ? absint( $_POST['client_id'] ) : 0;
        
        if ( ! $client_id ) {
            DPS_Request_Validator::send_json_error( __( 'Cliente não especificado.', 'desi-pet-shower' ), 'CLIENTE_NAO_ENCONTRADO', 400 );
        }

        // Verifica nonce dinâmico
        if ( ! DPS_Request_Validator::verify_dynamic_nonce( 'dps_save_client_notes_', $client_id, 'nonce', 'POST' ) ) {
            DPS_Request_Validator::send_json_error( __( 'Sessão expirada. Recarregue a página.', 'desi-pet-shower' ), 'NONCE_INVALIDO', 403 );
        }

        // Verifica permissão
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'dps_manage_clients' ) ) {
            DPS_Request_Validator::send_json_error( __( 'Você não tem permissão para realizar esta ação.', 'desi-pet-shower' ), 'SEM_PERMISSAO', 403 );
        }

        // Verifica se o cliente existe
        $client = get_post( $client_id );
        if ( ! $client || 'dps_cliente' !== $client->post_type ) {
            DPS_Request_Validator::send_json_error( __( 'Cliente não encontrado.', 'desi-pet-shower' ), 'CLIENTE_NAO_ENCONTRADO', 404 );
        }

        // Sanitiza e salva as notas
        $notes = isset( $_POST['internal_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['internal_notes'] ) ) : '';
        
        update_post_meta( $client_id, 'client_internal_notes', $notes );

        // Log da ação
        if ( class_exists( 'DPS_Logger' ) ) {
            DPS_Logger::log( 'client_notes_updated', sprintf(
                'Notas internas do cliente #%d atualizadas pelo usuário #%d',
                $client_id,
                get_current_user_id()
            ) );
        }

        DPS_Request_Validator::send_json_success(
            __( 'Notas salvas com sucesso.', 'desi-pet-shower' ),
            [ 'client_id' => $client_id ]
        );
    }
}
