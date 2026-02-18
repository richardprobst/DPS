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
    private static function normalize_status_key( $status ) {
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
    private static function get_history_appointments_data() {
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
    private static function get_history_timeline_groups() {
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
    private static function get_status_label( $status ) {
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
    private static function build_charge_html( $appt_id, $context = 'base', $allow_group = true ) {
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
    private static function get_current_page_url() {
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
    private static function get_clients() {
        return DPS_Query_Helper::get_all_posts_by_type( 'dps_cliente' );
    }

    /**
     * Obtém lista paginada de pets.
     *
     * @param int $page Número da página (default: 1).
     * @return WP_Query Objeto de consulta com pets paginados.
     */
    private static function get_pets( $page = 1 ) {
        return DPS_Query_Helper::get_paginated_posts( 'dps_pet', $page, DPS_BASE_PETS_PER_PAGE );
    }

    /**
     * Seção de clientes: listagem e atalhos administrativos.
     * 
     * REFATORADO: Separa preparação de dados da renderização.
     * A lógica de dados permanece aqui, a renderização foi movida para o template.
     * 
     * @since 1.0.0
     * @return string HTML da seção de clientes.
     */
    private static function section_clients() {
        // 1. Preparar dados (lógica de negócio)
        $data = self::prepare_clients_section_data();
        
        // 2. Renderizar usando template (apresentação)
        return self::render_clients_section( $data );
    }
    
    /**
     * Prepara os dados necessários para a seção de clientes.
     * 
     * @since 1.0.0
     * @return array {
     *     Dados estruturados para o template.
     *     
     *     @type array       $clients          Lista de posts de clientes (WP_Post[]).
     *     @type array       $client_meta      Metadados principais dos clientes.
     *     @type array       $pets_counts      Contagem de pets por cliente.
     *     @type array       $summary          Resumo de métricas da lista de clientes.
     *     @type string      $current_filter   Filtro ativo (all|without_pets|missing_contact).
     *     @type string      $registration_url URL da página dedicada de cadastro.
     *     @type string      $base_url         URL base da página atual.
     *     @type int         $edit_id          ID do cliente sendo editado (0 se não estiver editando).
     *     @type WP_Post|null $editing         Post do cliente sendo editado (null se não estiver editando).
     *     @type array       $edit_meta        Metadados do cliente sendo editado.
     *     @type string      $api_key          Chave da API do Google Maps.
     * }
     */
    private static function prepare_clients_section_data() {
        $clients = self::get_clients();
        $client_meta = self::build_clients_meta( $clients );
        $pets_counts = self::get_clients_pets_counts( $clients );
        $summary     = self::summarize_clients_data( $clients, $client_meta, $pets_counts );

        $filter = isset( $_GET['dps_clients_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['dps_clients_filter'] ) ) : 'all';
        $allowed_filters = [ 'all', 'without_pets', 'missing_contact' ];
        if ( ! in_array( $filter, $allowed_filters, true ) ) {
            $filter = 'all';
        }

        $registration_url = get_option( 'dps_clients_registration_url', '' );
        $registration_url = apply_filters( 'dps_clients_registration_url', $registration_url );

        // Detecta edição via parâmetros GET (com sanitização adequada)
        $edit_type = isset( $_GET['dps_edit'] ) ? sanitize_text_field( wp_unslash( $_GET['dps_edit'] ) ) : '';
        $edit_id   = ( 'client' === $edit_type && isset( $_GET['id'] ) )
                     ? absint( $_GET['id'] )
                     : 0;
        $editing   = null;
        $edit_meta = [];

        if ( $edit_id ) {
            $editing = get_post( $edit_id );
            if ( $editing && 'dps_cliente' === $editing->post_type ) {
                // Carrega metadados do cliente para edição
                $edit_meta = [
                    'cpf'        => get_post_meta( $edit_id, 'client_cpf', true ),
                    'phone'      => get_post_meta( $edit_id, 'client_phone', true ),
                    'email'      => get_post_meta( $edit_id, 'client_email', true ),
                    'birth'      => get_post_meta( $edit_id, 'client_birth', true ),
                    'instagram'  => get_post_meta( $edit_id, 'client_instagram', true ),
                    'facebook'   => get_post_meta( $edit_id, 'client_facebook', true ),
                    'photo_auth' => get_post_meta( $edit_id, 'client_photo_auth', true ),
                    'address'    => get_post_meta( $edit_id, 'client_address', true ),
                    'referral'   => get_post_meta( $edit_id, 'client_referral', true ),
                    'lat'        => get_post_meta( $edit_id, 'client_lat', true ),
                    'lng'        => get_post_meta( $edit_id, 'client_lng', true ),
                ];
            } else {
                // ID inválido ou post_type incorreto
                $edit_id = 0;
                $editing = null;
            }
        }

        return [
            'clients'          => self::filter_clients_list( $clients, $client_meta, $pets_counts, $filter ),
            'client_meta'      => $client_meta,
            'pets_counts'      => $pets_counts,
            'summary'          => $summary,
            'current_filter'   => $filter,
            'registration_url' => $registration_url,
            'base_url'         => DPS_URL_Builder::safe_get_permalink(),
            'edit_id'          => $edit_id,
            'editing'          => $editing,
            'edit_meta'        => $edit_meta,
            'api_key'          => get_option( 'dps_google_api_key', '' ),
        ];
    }

    /**
     * Pré-carrega metadados críticos dos clientes para evitar consultas repetidas.
     *
     * @since 1.0.0
     * @param array $clients Lista de posts de clientes.
     * @return array
     */
    private static function build_clients_meta( $clients ) {
        $meta = [];

        foreach ( $clients as $client ) {
            $id = (int) $client->ID;
            $meta[ $id ] = [
                'phone' => get_post_meta( $id, 'client_phone', true ),
                'email' => get_post_meta( $id, 'client_email', true ),
            ];
        }

        return $meta;
    }

    /**
     * Retorna contagem de pets para cada cliente informado.
     *
     * @since 1.0.0
     * @param array $clients Lista de posts de clientes.
     * @return array
     */
    private static function get_clients_pets_counts( $clients ) {
        $pets_counts = [];

        if ( empty( $clients ) ) {
            return $pets_counts;
        }

        $client_ids   = array_map( 'intval', wp_list_pluck( $clients, 'ID' ) );
        $placeholders = implode( ',', array_fill( 0, count( $client_ids ), '%d' ) );

        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta_value AS owner_id, COUNT(*) AS pet_count
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                 WHERE pm.meta_key = 'owner_id'
                 AND p.post_type = 'dps_pet'
                 AND p.post_status = 'publish'
                 AND pm.meta_value IN ($placeholders)
                 GROUP BY pm.meta_value",
                ...$client_ids
            ),
            ARRAY_A
        );

        foreach ( $results as $row ) {
            $pets_counts[ $row['owner_id'] ] = (int) $row['pet_count'];
        }

        return $pets_counts;
    }

    /**
     * Calcula métricas administrativas da lista de clientes.
     *
     * @since 1.0.0
     * @param array $clients     Lista de posts de clientes.
     * @param array $client_meta Metadados principais dos clientes.
     * @param array $pets_counts Contagem de pets por cliente.
     * @return array
     */
    private static function summarize_clients_data( $clients, $client_meta, $pets_counts ) {
        $missing_contact = 0;
        $without_pets    = 0;

        foreach ( $clients as $client ) {
            $id    = (int) $client->ID;
            $meta  = isset( $client_meta[ $id ] ) ? $client_meta[ $id ] : [ 'phone' => '', 'email' => '' ];
            $phone = isset( $meta['phone'] ) ? $meta['phone'] : '';
            $email = isset( $meta['email'] ) ? $meta['email'] : '';

            if ( empty( $phone ) && empty( $email ) ) {
                $missing_contact++;
            }

            $pets_for_client = isset( $pets_counts[ (string) $id ] ) ? (int) $pets_counts[ (string) $id ] : 0;
            if ( 0 === $pets_for_client ) {
                $without_pets++;
            }
        }

        return [
            'total'            => count( $clients ),
            'missing_contact'  => $missing_contact,
            'without_pets'     => $without_pets,
        ];
    }

    /**
     * Filtra lista de clientes conforme necessidade administrativa.
     *
     * @since 1.0.0
     * @param array  $clients     Lista de posts de clientes.
     * @param array  $client_meta Metadados principais dos clientes.
     * @param array  $pets_counts Contagem de pets por cliente.
     * @param string $filter      Filtro ativo.
     * @return array
     */
    private static function filter_clients_list( $clients, $client_meta, $pets_counts, $filter ) {
        if ( 'without_pets' === $filter ) {
            return array_values(
                array_filter(
                    $clients,
                    function( $client ) use ( $pets_counts ) {
                        $client_id = (string) $client->ID;
                        $count     = isset( $pets_counts[ $client_id ] ) ? (int) $pets_counts[ $client_id ] : 0;
                        return 0 === $count;
                    }
                )
            );
        }

        if ( 'missing_contact' === $filter ) {
            return array_values(
                array_filter(
                    $clients,
                    function( $client ) use ( $client_meta ) {
                        $client_id = (int) $client->ID;
                        $meta      = isset( $client_meta[ $client_id ] ) ? $client_meta[ $client_id ] : [ 'phone' => '', 'email' => '' ];
                        $phone     = isset( $meta['phone'] ) ? $meta['phone'] : '';
                        $email     = isset( $meta['email'] ) ? $meta['email'] : '';

                        return empty( $phone ) && empty( $email );
                    }
                )
            );
        }

        return $clients;
    }
    
    /**
     * Renderiza a seção de clientes usando template.
     * 
     * @since 1.0.0
     * @param array $data {
     *     Dados preparados para renderização.
     *     
     *     @type array       $clients          Lista de posts de clientes.
     *     @type array       $client_meta      Metadados principais dos clientes.
     *     @type array       $pets_counts      Contagem de pets por cliente.
     *     @type array       $summary          Resumo de métricas da lista de clientes.
     *     @type string      $current_filter   Filtro ativo (all|without_pets|missing_contact).
     *     @type string      $registration_url URL da página dedicada de cadastro.
     *     @type string      $base_url         URL base da página.
     * }
     * @return string HTML da seção.
     */
    private static function render_clients_section( $data ) {
        ob_start();
        dps_get_template( 'frontend/clients-section.php', $data );
        return ob_get_clean();
    }

    /**
     * Seção de pets: formulário e listagem
     * 
     * REFATORADO v1.0.4: Separa preparação de dados da renderização.
     * A lógica de dados permanece aqui, a renderização foi movida para templates.
     * 
     * @since 1.0.0
     * @since 1.0.4 Refatorado para usar templates.
     * @return string HTML da seção de pets.
     */
    private static function section_pets() {
        // 1. Preparar dados (lógica de negócio)
        $data = self::prepare_pets_section_data();
        
        // 2. Renderizar usando template (apresentação)
        return self::render_pets_section( $data );
    }
    
    /**
     * Prepara os dados necessários para a seção de pets.
     * 
     * @since 1.0.4
     * @return array {
     *     Dados estruturados para o template.
     *     
     *     @type array       $pets          Lista de posts de pets.
     *     @type int         $pets_page     Página atual da paginação.
     *     @type int         $pets_pages    Total de páginas.
     *     @type array       $clients       Lista de clientes disponíveis.
     *     @type int         $edit_id       ID do pet sendo editado (0 se novo).
     *     @type WP_Post|null $editing      Post do pet em edição (null se novo).
     *     @type array       $meta          Metadados do pet.
     *     @type array       $breed_options Lista de raças disponíveis.
     *     @type array       $breed_data    Dataset completo de raças por espécie.
     *     @type string      $base_url      URL base da página.
     * }
     */
    private static function prepare_pets_section_data() {
        $clients    = self::get_clients();
        
        // Busca todos os pets para estatísticas (sem paginação)
        $all_pets_query = new WP_Query( [
            'post_type'      => 'dps_pet',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ] );
        $all_pet_ids = $all_pets_query->posts;
        
        // Pré-carrega metadados dos pets
        if ( ! empty( $all_pet_ids ) ) {
            update_meta_cache( 'post', $all_pet_ids );
        }
        
        // Coleta estatísticas dos pets
        $pets_stats = self::build_pets_statistics( $all_pet_ids );
        
        // Detecta filtro via parâmetros GET
        $filter = isset( $_GET['dps_pets_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['dps_pets_filter'] ) ) : 'all';
        $allowed_filters = [ 'all', 'aggressive', 'without_owner', 'cao', 'gato', 'outro' ];
        if ( ! in_array( $filter, $allowed_filters, true ) ) {
            $filter = 'all';
        }
        
        // Paginação
        $pets_page  = isset( $_GET['dps_pets_page'] ) ? max( 1, intval( $_GET['dps_pets_page'] ) ) : 1;
        
        // Aplica filtro na busca
        $filtered_pets_query = self::get_filtered_pets( $pets_page, $filter );
        $pets       = $filtered_pets_query->posts;
        $pets_pages = (int) max( 1, $filtered_pets_query->max_num_pages );
        
        // Pré-carrega contagem de agendamentos e última data de atendimento para cada pet
        $pet_ids_in_page = wp_list_pluck( $pets, 'ID' );
        $appointments_stats = self::get_pets_appointments_stats( $pet_ids_in_page );
        
        // Detecta edição via parâmetros GET
        $edit_id = ( isset( $_GET['dps_edit'] ) && 'pet' === $_GET['dps_edit'] && isset( $_GET['id'] ) ) 
                   ? intval( $_GET['id'] ) 
                   : 0;
        
        $editing = null;
        $meta    = [];
        
        if ( $edit_id ) {
            $editing = get_post( $edit_id );
            if ( $editing ) {
                // Carrega metadados do pet para edição
                $meta = [
                    'owner_id'             => get_post_meta( $edit_id, 'owner_id', true ),
                    'species'              => get_post_meta( $edit_id, 'pet_species', true ),
                    'breed'                => get_post_meta( $edit_id, 'pet_breed', true ),
                    'size'                 => get_post_meta( $edit_id, 'pet_size', true ),
                    'weight'               => get_post_meta( $edit_id, 'pet_weight', true ),
                    'coat'                 => get_post_meta( $edit_id, 'pet_coat', true ),
                    'color'                => get_post_meta( $edit_id, 'pet_color', true ),
                    'birth'                => get_post_meta( $edit_id, 'pet_birth', true ),
                    'sex'                  => get_post_meta( $edit_id, 'pet_sex', true ),
                    'care'                 => get_post_meta( $edit_id, 'pet_care', true ),
                    'aggressive'           => get_post_meta( $edit_id, 'pet_aggressive', true ),
                    'vaccinations'         => get_post_meta( $edit_id, 'pet_vaccinations', true ),
                    'allergies'            => get_post_meta( $edit_id, 'pet_allergies', true ),
                    'behavior'             => get_post_meta( $edit_id, 'pet_behavior', true ),
                    'photo_id'             => get_post_meta( $edit_id, 'pet_photo_id', true ),
                    // Preferências de produtos
                    'shampoo_pref'         => get_post_meta( $edit_id, 'pet_shampoo_pref', true ),
                    'perfume_pref'         => get_post_meta( $edit_id, 'pet_perfume_pref', true ),
                    'accessories_pref'     => get_post_meta( $edit_id, 'pet_accessories_pref', true ),
                    'product_restrictions' => get_post_meta( $edit_id, 'pet_product_restrictions', true ),
                ];
            }
        }
        
        // Detecta pref_owner para pré-selecionar cliente no formulário
        $pref_owner = isset( $_GET['pref_owner'] ) ? absint( $_GET['pref_owner'] ) : 0;
        if ( $pref_owner && empty( $meta['owner_id'] ) ) {
            $meta['owner_id'] = $pref_owner;
        }
        
        $species_val   = $meta['species'] ?? '';
        $breed_data    = self::get_breed_dataset();
        $breed_options = self::get_breed_options_for_species( $species_val );
        
        return [
            'pets'               => $pets,
            'pets_page'          => $pets_page,
            'pets_pages'         => $pets_pages,
            'pets_total'         => count( $all_pet_ids ),
            'clients'            => $clients,
            'edit_id'            => $edit_id,
            'editing'            => $editing,
            'meta'               => $meta,
            'breed_options'      => $breed_options,
            'breed_data'         => $breed_data,
            'base_url'           => DPS_URL_Builder::safe_get_permalink(),
            'current_filter'     => $filter,
            'summary'            => $pets_stats,
            'appointments_stats' => $appointments_stats,
        ];
    }
    
    /**
     * Busca pets com filtro aplicado.
     *
     * @since 1.0.5
     * @param int    $page   Número da página.
     * @param string $filter Filtro a ser aplicado.
     * @return WP_Query
     */
    private static function get_filtered_pets( $page, $filter ) {
        $args = [
            'post_type'      => 'dps_pet',
            'posts_per_page' => DPS_BASE_PETS_PER_PAGE,
            'post_status'    => 'publish',
            'paged'          => $page,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];
        
        $meta_query = [];
        
        switch ( $filter ) {
            case 'aggressive':
                $meta_query[] = [
                    'key'     => 'pet_aggressive',
                    'value'   => '1',
                    'compare' => '=',
                ];
                break;
            case 'without_owner':
                $meta_query[] = [
                    'relation' => 'OR',
                    [
                        'key'     => 'owner_id',
                        'compare' => 'NOT EXISTS',
                    ],
                    [
                        'key'     => 'owner_id',
                        'value'   => '',
                        'compare' => '=',
                    ],
                    [
                        'key'     => 'owner_id',
                        'value'   => '0',
                        'compare' => '=',
                    ],
                ];
                break;
            case 'cao':
            case 'gato':
            case 'outro':
                $meta_query[] = [
                    'key'     => 'pet_species',
                    'value'   => $filter,
                    'compare' => '=',
                ];
                break;
        }
        
        if ( ! empty( $meta_query ) ) {
            $args['meta_query'] = $meta_query;
        }
        
        return new WP_Query( $args );
    }
    
    /**
     * Calcula estatísticas dos pets para o painel de resumo.
     *
     * @since 1.0.5
     * @param array $pet_ids Lista de IDs de pets.
     * @return array
     */
    private static function build_pets_statistics( $pet_ids ) {
        $stats = [
            'total'         => count( $pet_ids ),
            'aggressive'    => 0,
            'without_owner' => 0,
            'dogs'          => 0,
            'cats'          => 0,
            'others'        => 0,
        ];
        
        if ( empty( $pet_ids ) ) {
            return $stats;
        }
        
        foreach ( $pet_ids as $pet_id ) {
            $aggressive = get_post_meta( $pet_id, 'pet_aggressive', true );
            $owner_id   = get_post_meta( $pet_id, 'owner_id', true );
            $species    = get_post_meta( $pet_id, 'pet_species', true );
            
            if ( $aggressive ) {
                $stats['aggressive']++;
            }
            
            if ( empty( $owner_id ) ) {
                $stats['without_owner']++;
            }
            
            switch ( $species ) {
                case 'cao':
                    $stats['dogs']++;
                    break;
                case 'gato':
                    $stats['cats']++;
                    break;
                default:
                    $stats['others']++;
                    break;
            }
        }
        
        return $stats;
    }
    
    /**
     * Busca estatísticas de agendamentos para cada pet.
     *
     * @since 1.0.5
     * @param array $pet_ids Lista de IDs de pets.
     * @return array Array com contagem de agendamentos e última data por pet_id.
     */
    private static function get_pets_appointments_stats( $pet_ids ) {
        $stats = [];
        
        if ( empty( $pet_ids ) ) {
            return $stats;
        }
        
        global $wpdb;
        
        $pet_ids = array_map( 'intval', $pet_ids );
        $placeholders = implode( ',', array_fill( 0, count( $pet_ids ), '%s' ) );
        
        // Busca contagem de agendamentos e última data para cada pet
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT pm.meta_value AS pet_id, 
                        COUNT(DISTINCT pm.post_id) AS appointment_count,
                        MAX(pm2.meta_value) AS last_appointment_date
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                 LEFT JOIN {$wpdb->postmeta} pm2 ON pm.post_id = pm2.post_id AND pm2.meta_key = 'appointment_date'
                 WHERE pm.meta_key = 'appointment_pet_id'
                 AND p.post_type = 'dps_agendamento'
                 AND p.post_status = 'publish'
                 AND pm.meta_value IN ($placeholders)
                 GROUP BY pm.meta_value",
                ...$pet_ids
            ),
            ARRAY_A
        );
        
        foreach ( $results as $row ) {
            $stats[ $row['pet_id'] ] = [
                'count'     => (int) $row['appointment_count'],
                'last_date' => $row['last_appointment_date'],
            ];
        }
        
        // Preenche pets sem agendamentos
        foreach ( $pet_ids as $pet_id ) {
            if ( ! isset( $stats[ (string) $pet_id ] ) ) {
                $stats[ (string) $pet_id ] = [
                    'count'     => 0,
                    'last_date' => null,
                ];
            }
        }
        
        return $stats;
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
     * @param array $data Dados preparados para renderização.
     * @return string HTML da seção.
     */
    private static function render_pets_section( $data ) {
        ob_start();
        dps_get_template( 'frontend/pets-section.php', $data );
        return ob_get_clean();
    }

    /**
     * Seção de agendamentos: formulário e listagem.
     *
     * REFATORAÇÃO: Este método foi reorganizado para separar a preparação de dados
     * da renderização. Segue o mesmo padrão usado em section_clients().
     * Em uma fase futura, o formulário pode ser movido para um template dedicado.
     *
     * @since 1.0.0
     * @since 1.0.2 Documentação de refatoração adicionada.
     * @param bool $visitor_only Se true, exibe apenas a listagem sem formulário.
     * @return string HTML da seção de agendamentos.
     */
    private static function section_agendas( $visitor_only = false ) {
        // Passo 1: Preparar dados necessários para a seção.
        $data = self::prepare_appointments_section_data( $visitor_only );

        // Passo 2: Renderizar usando os dados preparados.
        return self::render_appointments_section(
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
     * Este método centraliza toda a lógica de coleta e preparação de dados
     * para o formulário e listagem de agendamentos. Separa a lógica de negócio
     * da renderização para facilitar testes e manutenção.
     *
     * REFATORAÇÃO: Este método foi extraído de section_agendas() como parte
     * de uma refatoração gradual para melhorar a organização do código.
     *
     * @since 1.0.2
     * @since 1.0.3 Adicionado suporte a duplicação de agendamentos.
     * @param bool $visitor_only Se true, não prepara dados do formulário.
     * @return array {
     *     Dados estruturados para renderização.
     *
     *     @type array       $clients      Lista de clientes disponíveis.
     *     @type array       $pets         Lista de pets disponíveis.
     *     @type int         $pet_pages    Total de páginas de pets.
     *     @type int         $edit_id      ID do agendamento sendo editado (0 se novo).
     *     @type WP_Post|null $editing     Post do agendamento em edição.
     *     @type array       $meta         Metadados do agendamento.
     *     @type int         $pref_client  Cliente pré-selecionado via URL.
     *     @type int         $pref_pet     Pet pré-selecionado via URL.
     *     @type string      $base_url     URL base da página atual.
     *     @type string      $current_url  URL completa atual.
     *     @type bool        $is_duplicate Se true, está duplicando um agendamento.
     * }
     */
    private static function prepare_appointments_section_data( $visitor_only = false, array $overrides = [] ) {
        $clients    = self::get_clients();
        $pets_query = self::get_pets();
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
        $current_url          = $override_current_url ? $override_current_url : self::get_current_page_url();

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
     * Este método contém toda a lógica de renderização do formulário e listagem.
     * Em uma fase futura de refatoração, o formulário pode ser movido para
     * um template dedicado em templates/frontend/appointments-section.php.
     *
     * REFATORAÇÃO: Este método foi extraído de section_agendas() para separar
     * a preparação de dados da renderização. O HTML ainda é gerado inline,
     * mas está organizado de forma mais clara.
     *
     * @since 1.0.2
     * @param array $data         Dados preparados por prepare_appointments_section_data().
     * @param bool  $visitor_only Se true, exibe apenas a listagem sem formulário.
     * @return string HTML da seção.
     */
    private static function render_appointments_section( array $data, $visitor_only = false, array $options = [] ) {
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
        $current_url  = isset( $data['current_url'] ) ? $data['current_url'] : self::get_current_page_url();

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
                $consent_data = self::get_client_tosa_consent_data( $client->ID );
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

            $initial_consent_data = $sel_client ? self::get_client_tosa_consent_data( $sel_client ) : [ 'status' => 'missing', 'has_consent' => false, 'granted_at' => '', 'revoked_at' => '' ];
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
                return self::render_status_selector( $appt_id, $status, $status_labels, $visitor_only );
            };

            $charge_renderer = function( $appt_id ) {
                return self::build_charge_html( $appt_id, 'agendas' );
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
     * Seção dedicada ao histórico de atendimentos já realizados.
     */
    private static function section_history() {
        if ( ! self::can_manage() ) {
            return '';
        }

        $history_data   = self::get_history_appointments_data();
        $appointments   = $history_data['appointments'];
        $base_url       = DPS_URL_Builder::safe_get_permalink();
        $timeline_data   = self::get_history_timeline_groups();
        $timeline_groups = $timeline_data['groups'];
        $timeline_counts = $timeline_data['counts'];

        $clients = self::get_clients();
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
            return self::render_status_selector( $appt_id, $status, $status_labels, false );
        };

        $history_charge_renderer = function( $appt_id ) {
            return self::build_charge_html( $appt_id, 'historico' );
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
                $status_key   = self::normalize_status_key( $status_meta );
                $status_label = self::get_status_label( $status_meta );
                $pet_display  = '-';
                $pet_ids_attr = '';
                $group_data   = self::get_multi_pet_charge_data( $appt->ID );
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
                echo '<td class="hide-mobile">' . self::build_charge_html( $appt->ID, 'historico' ) . '</td>';

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
     * Compara agendamentos pela data e hora em ordem decrescente.
     *
     * @param WP_Post $a Primeiro agendamento.
     * @param WP_Post $b Segundo agendamento.
     * Compara dois agendamentos por data e hora de forma descendente.
     *
     * Ordena agendamentos do mais recente para o mais antigo. Em caso de
     * data/hora iguais, ordena por ID (do maior para o menor).
     *
     * @param object $first_appointment Primeiro agendamento a comparar.
     * @param object $second_appointment Segundo agendamento a comparar.
     * @return int Resultado da comparação: -1, 0 ou 1.
     */
    private static function compare_appointments_desc( $first_appointment, $second_appointment ) {
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
     * Renderiza o seletor de status em linha para os agendamentos.
     *
     * @param int   $appt_id        ID do agendamento.
     * @param string $current_status Status atual salvo na meta.
     * @param array  $status_labels  Rótulos disponíveis para exibição.
     * @param bool   $visitor_only   Indica se o usuário atual não pode gerenciar registros.
     *
     * @return string HTML do seletor ou do texto de status.
     */
    private static function render_status_selector( $appt_id, $current_status, $status_labels, $visitor_only ) {
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
