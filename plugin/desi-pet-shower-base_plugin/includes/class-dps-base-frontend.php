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

        $appointments = [];
        $total_amount = 0;
        $total_count  = 0;
        $paged        = 1;

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
                $total_count++;

                if ( 'cancelado' !== $status_meta ) {
                    $total_amount += (float) get_post_meta( $appt_id, 'appointment_total_value', true );
                }

                $appointments[] = (object) [ 'ID' => (int) $appt_id ];
            }

            $paged++;
        } while ( count( $batch_ids ) === $batch_size );

        if ( $appointments ) {
            usort( $appointments, [ self::class, 'compare_appointments_desc' ] );
        }

        return [
            'appointments' => $appointments,
            'total_amount' => $total_amount,
            'total_count'  => $total_count,
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
            return get_permalink( $queried_id );
        }

        global $post;
        if ( isset( $post->ID ) ) {
            return get_permalink( $post->ID );
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
            return get_permalink( $queried_id );
        }

        global $post;
        if ( isset( $post->ID ) ) {
            return get_permalink( $post->ID );
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
    private static function get_redirect_url( $tab = '' ) {
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
            'save_passwords'            => 'dps_nonce_passwords',
        ];
        
        $nonce_field = isset( $nonce_map[ $action ] ) ? $nonce_map[ $action ] : 'dps_nonce';
        
        // Verifica nonce
        if ( ! isset( $_POST[ $nonce_field ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) ), 'dps_action' ) ) {
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
            'save_passwords'            => 'senhas',
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
        
        // Verifica nonce para proteção CSRF
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'dps_logout' ) ) {
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
        // Garante que o CSS/JS do painel estejam carregados mesmo em contextos
        // onde wp_enqueue_scripts não foi executado (ex.: shortcodes renderizados
        // em builders ou pré-visualizações).
        DPS_Base_Plugin::enqueue_frontend_assets();

        // Verifica se há visualização específica (detalhes do cliente)
        if ( isset( $_GET['dps_view'] ) && 'client' === $_GET['dps_view'] && isset( $_GET['id'] ) ) {
            $client_id = intval( $_GET['id'] );
            return self::render_client_page( $client_id );
        }
        
        $can_manage = self::can_manage();

        // Verifica se o usuário atual está logado e possui permissão para gerenciar o painel
        if ( ! is_user_logged_in() || ! $can_manage ) {
            $login_url = wp_login_url( get_permalink() );
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
     * Renderiza a página de configurações avançadas (shortcode dps_configuracoes).
     *
     * @return string
     */
    /**
     * Renderiza o shortcode [dps_configuracoes].
     * 
     * DEPRECATED: Este shortcode foi movido para o painel administrativo do WordPress.
     * Mantido apenas para retrocompatibilidade, mas não deve mais ser usado.
     * 
     * @return string Mensagem de depreciação com link para o admin.
     */
    public static function render_settings() {
        // Log de depreciação para administradores
        if ( current_user_can( 'manage_options' ) ) {
            DPS_Logger::log(
                __( 'O shortcode [dps_configuracoes] está deprecated e será removido em versões futuras. Use o menu admin "DPS by PRObst".', 'desi-pet-shower' ),
                DPS_Logger::LEVEL_WARNING,
                'shortcode_deprecated'
            );
        }

        $admin_url = admin_url( 'admin.php?page=desi-pet-shower' );
        
        ob_start();
        ?>
        <div class="dps-base-wrapper dps-settings-deprecated" style="max-width: 800px; margin: 40px auto; padding: 30px; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; text-align: center;">
            <div style="font-size: 48px; color: #0ea5e9; margin-bottom: 20px;">
                <span class="dashicons dashicons-admin-settings" style="font-size: 48px; width: 48px; height: 48px;"></span>
            </div>
            <h2 style="color: #374151; margin-bottom: 16px;"><?php esc_html_e( 'Configurações Movidas para o Admin', 'desi-pet-shower' ); ?></h2>
            <p style="font-size: 16px; color: #6b7280; margin-bottom: 24px; line-height: 1.6;">
                <?php esc_html_e( 'As configurações do sistema foram movidas para o painel administrativo do WordPress por questões de segurança e organização.', 'desi-pet-shower' ); ?>
            </p>
            <p style="font-size: 16px; color: #6b7280; margin-bottom: 32px; line-height: 1.6;">
                <?php esc_html_e( 'Para acessar Backup, Comunicações, Notificações e outras configurações, utilize o menu "DPS by PRObst" no painel admin.', 'desi-pet-shower' ); ?>
            </p>
            <?php if ( current_user_can( 'manage_options' ) ) : ?>
                <a href="<?php echo esc_url( $admin_url ); ?>" class="button button-primary button-hero" style="padding: 12px 32px; height: auto; font-size: 16px;">
                    <?php esc_html_e( 'Acessar Configurações no Admin', 'desi-pet-shower' ); ?>
                </a>
            <?php else : ?>
                <p style="color: #ef4444; font-weight: 600;">
                    <?php esc_html_e( 'Você precisa de permissões de administrador para acessar as configurações.', 'desi-pet-shower' ); ?>
                </p>
            <?php endif; ?>
            
            <div style="margin-top: 32px; padding-top: 24px; border-top: 1px solid #e5e7eb;">
                <p style="font-size: 14px; color: #9ca3af;">
                    <strong><?php esc_html_e( 'Nota:', 'desi-pet-shower' ); ?></strong>
                    <?php esc_html_e( 'Este shortcode [dps_configuracoes] está deprecated e será removido em versões futuras.', 'desi-pet-shower' ); ?>
                </p>
            </div>
        </div>
        <?php
        return ob_get_clean();
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
            'base_url'         => get_permalink(),
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
                    'owner_id'     => get_post_meta( $edit_id, 'owner_id', true ),
                    'species'      => get_post_meta( $edit_id, 'pet_species', true ),
                    'breed'        => get_post_meta( $edit_id, 'pet_breed', true ),
                    'size'         => get_post_meta( $edit_id, 'pet_size', true ),
                    'weight'       => get_post_meta( $edit_id, 'pet_weight', true ),
                    'coat'         => get_post_meta( $edit_id, 'pet_coat', true ),
                    'color'        => get_post_meta( $edit_id, 'pet_color', true ),
                    'birth'        => get_post_meta( $edit_id, 'pet_birth', true ),
                    'sex'          => get_post_meta( $edit_id, 'pet_sex', true ),
                    'care'         => get_post_meta( $edit_id, 'pet_care', true ),
                    'aggressive'   => get_post_meta( $edit_id, 'pet_aggressive', true ),
                    'vaccinations' => get_post_meta( $edit_id, 'pet_vaccinations', true ),
                    'allergies'    => get_post_meta( $edit_id, 'pet_allergies', true ),
                    'behavior'     => get_post_meta( $edit_id, 'pet_behavior', true ),
                    'photo_id'     => get_post_meta( $edit_id, 'pet_photo_id', true ),
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
            'base_url'           => get_permalink(),
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
        $base_url             = $override_base_url ? $override_base_url : get_permalink();
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
        $base_url     = isset( $data['base_url'] ) ? $data['base_url'] : get_permalink();
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
            echo '<p class="dps-surface__description">' . esc_html( $form_title ) . ' — ' . esc_html__( 'Preencha os dados do agendamento nos campos abaixo.', 'desi-pet-shower' ) . '</p>';
            
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
                $option_attrs .= $pending_attr;
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
            echo '<p class="dps-field-hint">' . esc_html__( 'Selecione os pets do cliente escolhido. É possível marcar mais de um.', 'desi-pet-shower' ) . '</p>';
            echo '<p id="dps-pet-select-client" class="dps-field-hint">' . esc_html__( 'Escolha um cliente para visualizar os pets disponíveis.', 'desi-pet-shower' ) . '</p>';
            echo '<p class="dps-pet-search"><label class="screen-reader-text" for="dps-pet-search">' . esc_html__( 'Buscar pets', 'desi-pet-shower' ) . '</label>';
            echo '<input type="search" id="dps-pet-search" placeholder="' . esc_attr__( 'Buscar pets por nome, tutor ou raça', 'desi-pet-shower' ) . '" aria-label="' . esc_attr__( 'Buscar pets', 'desi-pet-shower' ) . '"></p>';
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
                if ( $owner_name ) {
                    echo '<span class="dps-pet-owner"> (' . esc_html( $owner_name ) . ')</span>';
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
            
            // FIELDSET 4: Serviços e Extras
            echo '<fieldset class="dps-fieldset">';
            echo '<legend class="dps-fieldset__legend">' . esc_html__( 'Serviços e Extras', 'desi-pet-shower' ) . '</legend>';
            
            // Campo: indicativo de necessidade de tosa (apenas para assinaturas)
            $tosa       = $meta['tosa'] ?? '';
            $tosa_price = $meta['tosa_price'] ?? '';
            $tosa_occ   = $meta['tosa_occurrence'] ?? '1';
            $tosa_display = ( '1' === $tosa ) ? 'block' : 'none';
            echo '<div id="dps-tosa-wrapper" class="dps-conditional-field" style="display:none;">';
            echo '<label class="dps-checkbox-label">';
            echo '<input type="checkbox" id="dps-tosa-toggle" name="appointment_tosa" value="1" ' . checked( $tosa, '1', false ) . '>';
            echo '<span class="dps-checkbox-text">';
            echo esc_html__( 'Precisa de tosa?', 'desi-pet-shower' );
            echo ' <span class="dps-tooltip" data-tooltip="' . esc_attr__( 'Adicione um serviço de tosa à assinatura', 'desi-pet-shower' ) . '">ℹ️</span>';
            echo '</span>';
            echo '</label>';
            
            echo '<div id="dps-tosa-fields" class="dps-conditional-field" style="display:' . esc_attr( $tosa_display ) . ';">';
            // Preço da tosa com valor padrão 30 se não definido
            $tosa_price_val = $tosa_price !== '' ? $tosa_price : '30';
            echo '<label for="dps-tosa-price">' . esc_html__( 'Preço da tosa (R$)', 'desi-pet-shower' ) . '</label>';
            echo '<input type="number" step="0.01" min="0" id="dps-tosa-price" name="appointment_tosa_price" value="' . esc_attr( $tosa_price_val ) . '" class="dps-input-money">';
            // Ocorrência da tosa (selecionada via JS conforme frequência)
            echo '<label for="appointment_tosa_occurrence" style="margin-left:20px;">' . esc_html__( 'Ocorrência da tosa', 'desi-pet-shower' ) . '</label>';
            echo '<select name="appointment_tosa_occurrence" id="appointment_tosa_occurrence" data-current="' . esc_attr( $tosa_occ ) . '"></select>';
            echo '</div>';
            echo '</div>';

            // Campo: escolha de TaxiDog (melhorado com feedback visual)
            $taxidog = $meta['taxidog'] ?? '';
            $taxidog_price_val = $meta['taxidog_price'] ?? '';
            $taxidog_has_value = ( $taxidog_price_val !== '' && floatval( $taxidog_price_val ) > 0 );
            
            echo '<div class="dps-taxidog-section">';
            echo '<label class="dps-checkbox-label dps-taxidog-toggle-label">';
            echo '<input type="checkbox" id="dps-taxidog-toggle" name="appointment_taxidog" value="1" ' . checked( $taxidog, '1', false ) . '>';
            echo '<span class="dps-checkbox-text">';
            echo '<span class="dps-taxidog-icon">🚗</span> ';
            echo esc_html__( 'Solicitar TaxiDog?', 'desi-pet-shower' );
            echo ' <span class="dps-tooltip" data-tooltip="' . esc_attr__( 'Serviço de transporte do pet', 'desi-pet-shower' ) . '">ℹ️</span>';
            echo '</span>';
            echo '</label>';
            
            // Área de preço do TaxiDog com feedback visual melhorado
            echo '<div id="dps-taxidog-extra" class="dps-taxidog-value-section" style="display:' . ( $taxidog ? 'block' : 'none' ) . ';">';
            echo '<div class="dps-taxidog-value-wrapper">';
            echo '<label for="dps-taxidog-price">' . esc_html__( 'Valor TaxiDog', 'desi-pet-shower' ) . '</label>';
            echo '<div class="dps-input-with-prefix">';
            echo '<span class="dps-input-prefix">R$</span>';
            echo '<input type="number" id="dps-taxidog-price" name="appointment_taxidog_price" step="0.01" min="0" value="' . esc_attr( $taxidog_price_val ) . '" class="dps-input-money dps-taxidog-price-input" placeholder="0,00">';
            echo '</div>';
            // Indicador visual do valor preenchido
            echo '<span id="dps-taxidog-value-indicator" class="dps-value-indicator' . ( $taxidog_has_value ? ' dps-value-filled' : '' ) . '">';
            if ( $taxidog_has_value ) {
                echo '<span class="dps-value-badge">✓ R$ ' . esc_html( number_format( floatval( $taxidog_price_val ), 2, ',', '.' ) ) . '</span>';
            }
            echo '</span>';
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
        $base_url       = get_permalink();
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

        $total_count  = $history_data['total_count'];
        $total_amount = $history_data['total_amount'];
        $summary_value = number_format_i18n( $total_amount, 2 );

        ob_start();
        echo '<div class="dps-section" id="dps-section-historico">';
        echo '<div class="dps-section-grid">';

        echo '<div class="dps-surface dps-surface--neutral">';
        echo '<div class="dps-history-header">';
        echo '<h2 class="dps-section-title"><span class="dps-section-title__icon">📚</span>' . esc_html__( 'Histórico de Atendimentos', 'desi-pet-shower' ) . '</h2>';
        echo '<p class="dps-history-header__description">' . esc_html__( 'Visualize, filtre e exporte todos os atendimentos registrados no sistema.', 'desi-pet-shower' ) . '</p>';
        echo '</div>';

        // Cards de métricas rápidas
        echo '<div class="dps-history-overview">';
        echo '<div class="dps-history-cards">';
        echo '<div class="dps-history-card dps-history-card--today">';
        echo '<span class="dps-history-card__icon" aria-hidden="true">📅</span>';
        echo '<div class="dps-history-card__content">';
        echo '<strong class="dps-history-card__value">' . esc_html( number_format_i18n( $timeline_counts['today'] ?? 0 ) ) . '</strong>';
        echo '<span class="dps-history-card__label">' . esc_html__( 'Hoje', 'desi-pet-shower' ) . '</span>';
        echo '</div>';
        echo '</div>';
        echo '<div class="dps-history-card dps-history-card--upcoming">';
        echo '<span class="dps-history-card__icon" aria-hidden="true">🗓️</span>';
        echo '<div class="dps-history-card__content">';
        echo '<strong class="dps-history-card__value">' . esc_html( number_format_i18n( $timeline_counts['upcoming'] ?? 0 ) ) . '</strong>';
        echo '<span class="dps-history-card__label">' . esc_html__( 'Futuros', 'desi-pet-shower' ) . '</span>';
        echo '</div>';
        echo '</div>';
        echo '<div class="dps-history-card dps-history-card--past">';
        echo '<span class="dps-history-card__icon" aria-hidden="true">✓</span>';
        echo '<div class="dps-history-card__content">';
        echo '<strong class="dps-history-card__value">' . esc_html( number_format_i18n( $timeline_counts['past'] ?? 0 ) ) . '</strong>';
        echo '<span class="dps-history-card__label">' . esc_html__( 'Passados', 'desi-pet-shower' ) . '</span>';
        echo '</div>';
        echo '</div>';
        echo '<div class="dps-history-card dps-history-card--total">';
        echo '<span class="dps-history-card__icon" aria-hidden="true">💰</span>';
        echo '<div class="dps-history-card__content">';
        echo '<strong class="dps-history-card__value">R$ ' . esc_html( $summary_value ) . '</strong>';
        echo '<span class="dps-history-card__label">' . esc_html__( 'Receita', 'desi-pet-shower' ) . '</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        $timeline_status_selector = function( $appt_id, $status ) use ( $status_labels ) {
            return self::render_status_selector( $appt_id, $status, $status_labels, false );
        };

        $history_charge_renderer = function( $appt_id ) {
            return self::build_charge_html( $appt_id, 'historico' );
        };

        echo '<div class="dps-surface dps-surface--info">';
        dps_get_template(
            'appointments-list.php',
            [
                'groups'           => $timeline_groups,
                'base_url'         => $base_url,
                'visitor_only'     => false,
                'status_labels'    => $status_labels,
                'status_selector'  => $timeline_status_selector,
                'charge_renderer'  => $history_charge_renderer,
                'list_title'       => __( 'Linha do tempo de agendamentos', 'desi-pet-shower' ),
            ]
        );
        echo '</div>';

        // Toolbar de filtros reorganizada
        echo '<div class="dps-surface dps-surface--neutral">';
        echo '<div class="dps-history-toolbar">';
        
        // Cabeçalho da seção de tabela com título e ações
        echo '<div class="dps-history-toolbar__header">';
        echo '<h3 class="dps-history-toolbar__title">' . esc_html__( 'Tabela de Atendimentos Finalizados', 'desi-pet-shower' ) . '</h3>';
        echo '<div class="dps-history-toolbar__actions">';
        echo '<button type="button" class="button button-secondary" id="dps-history-clear">' . esc_html__( 'Limpar', 'desi-pet-shower' ) . '</button>';
        echo '<button type="button" class="button button-primary" id="dps-history-export">' . esc_html__( 'Exportar CSV', 'desi-pet-shower' ) . '</button>';
        echo '</div>';
        echo '</div>';

        // Resumo dinâmico dos filtros aplicados (atualizado via JavaScript)
        echo '<div id="dps-history-summary" class="dps-history-summary" data-total-records="' . esc_attr( $total_count ) . '" data-total-value="' . esc_attr( $total_amount ) . '">';
        if ( $total_count ) {
            echo '<strong>' . sprintf( esc_html__( '%1$s atendimentos • R$ %2$s', 'desi-pet-shower' ), number_format_i18n( $total_count ), $summary_value ) . '</strong>';
        } else {
            echo '<strong>' . esc_html__( 'Nenhum atendimento registrado.', 'desi-pet-shower' ) . '</strong>';
        }
        echo '</div>';

        // Botões de período rápido
        echo '<div class="dps-history-quick-filters">';
        echo '<span class="dps-history-quick-label">' . esc_html__( 'Período:', 'desi-pet-shower' ) . '</span>';
        echo '<button type="button" class="button button-small dps-history-quick-btn" data-period="today">' . esc_html__( 'Hoje', 'desi-pet-shower' ) . '</button>';
        echo '<button type="button" class="button button-small dps-history-quick-btn" data-period="7days">' . esc_html__( '7 dias', 'desi-pet-shower' ) . '</button>';
        echo '<button type="button" class="button button-small dps-history-quick-btn" data-period="30days">' . esc_html__( '30 dias', 'desi-pet-shower' ) . '</button>';
        echo '<button type="button" class="button button-small dps-history-quick-btn" data-period="month">' . esc_html__( 'Este mês', 'desi-pet-shower' ) . '</button>';
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

                $status_meta = get_post_meta( $appt->ID, 'appointment_status', true );
                $status_key  = strtolower( str_replace( ' ', '_', (string) $status_meta ) );
                if ( 'finalizado_e_pago' === $status_key ) {
                    $status_key = 'finalizado_pago';
                }
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
                echo '<tr data-date="' . esc_attr( $date_attr ) . '" data-status="' . esc_attr( $status_key ) . '" data-client="' . esc_attr( $client_id ) . '" data-pet="' . esc_attr( $pet_ids_attr ) . '" data-total="' . esc_attr( $total_val ) . '" data-paid="' . esc_attr( $paid_flag ) . '">';
                $date_fmt = $date ? date_i18n( 'd/m/Y', strtotime( $date ) ) : '';
                echo '<td>' . esc_html( $date_fmt ) . '</td>';
                echo '<td>' . esc_html( $time ) . '</td>';
                echo '<td>' . esc_html( $client_name ) . '</td>';
                echo '<td>' . esc_html( $pet_display ) . '</td>';
                echo '<td class="hide-mobile">' . esc_html( $services_text ) . '</td>';
                echo '<td>' . esc_html( $total_display ) . '</td>';
                echo '<td>' . esc_html( $status_label ) . '</td>';
                echo '<td class="hide-mobile">' . self::build_charge_html( $appt->ID, 'historico' ) . '</td>';
                $edit_url   = add_query_arg( [ 'tab' => 'agendas', 'dps_edit' => 'appointment', 'id' => $appt->ID ], $base_url );
                $duplicate_url = add_query_arg( [ 'tab' => 'agendas', 'dps_duplicate' => 'appointment', 'id' => $appt->ID ], $base_url );
                $delete_url = add_query_arg( [ 'tab' => 'agendas', 'dps_delete' => 'appointment', 'id' => $appt->ID ], $base_url );
                echo '<td><a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Editar', 'desi-pet-shower' ) . '</a> | <a href="' . esc_url( $duplicate_url ) . '" title="' . esc_attr__( 'Duplicar agendamento', 'desi-pet-shower' ) . '">' . esc_html__( 'Duplicar', 'desi-pet-shower' ) . '</a> | <a href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Tem certeza de que deseja excluir?', 'desi-pet-shower' ) ) . '\');">' . esc_html__( 'Excluir', 'desi-pet-shower' ) . '</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '</div>';
        } else {
            echo '<p>' . esc_html__( 'Nenhum atendimento finalizado foi encontrado.', 'desi-pet-shower' ) . '</p>';
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
     * Seção de senhas: permite que o administrador altere as senhas de acesso do
     * plugin base e dos add‑ons (como agenda). As senhas são armazenadas em
     * opções do WordPress. Esta seção é exibida apenas para usuários
     * autenticados.
     */
    private static function section_passwords() {
        // Obtém valores atuais das senhas
        $base_pass   = get_option( 'dps_base_password', 'DPS2025' );
        $agenda_pass = get_option( 'dps_agenda_password', 'agendaDPS' );
        ob_start();
        echo '<div class="dps-section" id="dps-section-senhas">';
        echo '<h3>' . esc_html__( 'Configuração de Senhas', 'desi-pet-shower' ) . '</h3>';
        echo '<form method="post" class="dps-form">';
        echo '<input type="hidden" name="dps_action" value="save_passwords">';
        wp_nonce_field( 'dps_action', 'dps_nonce_passwords' );
        // Senha do plugin base (admin)
        echo '<p><label>' . esc_html__( 'Senha do painel principal', 'desi-pet-shower' ) . '<br><input type="password" name="base_password" value="' . esc_attr( $base_pass ) . '" required></label></p>';
        // Senha da agenda
        echo '<p><label>' . esc_html__( 'Senha da agenda pública', 'desi-pet-shower' ) . '<br><input type="password" name="agenda_password" value="' . esc_attr( $agenda_pass ) . '" required></label></p>';
        // Permite add‑ons adicionarem seus próprios campos de senha
        do_action( 'dps_base_password_fields' );
        echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Salvar Senhas', 'desi-pet-shower' ) . '</button></p>';
        echo '</form>';
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Salva cliente (inserção ou atualização)
     */
    private static function save_client() {
        if ( ! current_user_can( 'dps_manage_clients' ) ) {
            wp_die( __( 'Acesso negado.', 'desi-pet-shower' ) );
        }
        $name      = isset( $_POST['client_name'] ) ? sanitize_text_field( wp_unslash( $_POST['client_name'] ) ) : '';
        $cpf       = isset( $_POST['client_cpf'] ) ? sanitize_text_field( wp_unslash( $_POST['client_cpf'] ) ) : '';
        $phone     = isset( $_POST['client_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['client_phone'] ) ) : '';
        $email     = isset( $_POST['client_email'] ) ? sanitize_email( wp_unslash( $_POST['client_email'] ) ) : '';
        $birth     = isset( $_POST['client_birth'] ) ? sanitize_text_field( wp_unslash( $_POST['client_birth'] ) ) : '';
        $insta     = isset( $_POST['client_instagram'] ) ? sanitize_text_field( wp_unslash( $_POST['client_instagram'] ) ) : '';
        $facebook  = isset( $_POST['client_facebook'] ) ? sanitize_text_field( wp_unslash( $_POST['client_facebook'] ) ) : '';
        $photo_auth= isset( $_POST['client_photo_auth'] ) ? 1 : 0;
        $address   = isset( $_POST['client_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['client_address'] ) ) : '';
        $referral  = isset( $_POST['client_referral'] ) ? sanitize_text_field( wp_unslash( $_POST['client_referral'] ) ) : '';
        // Coordenadas (podem estar vazias se não selecionadas)
        $lat       = isset( $_POST['client_lat'] ) ? sanitize_text_field( wp_unslash( $_POST['client_lat'] ) ) : '';
        $lng       = isset( $_POST['client_lng'] ) ? sanitize_text_field( wp_unslash( $_POST['client_lng'] ) ) : '';
        
        // Validação de campos obrigatórios
        $errors = [];
        if ( empty( $name ) ) {
            $errors[] = __( 'O campo Nome é obrigatório.', 'desi-pet-shower' );
        }
        if ( empty( $phone ) ) {
            $errors[] = __( 'O campo Telefone é obrigatório.', 'desi-pet-shower' );
        }
        
        if ( ! empty( $errors ) ) {
            foreach ( $errors as $error ) {
                DPS_Message_Helper::add_error( $error );
            }
            wp_safe_redirect( self::get_redirect_url( 'clientes' ) );
            exit;
        }
        $client_id = isset( $_POST['client_id'] ) ? intval( wp_unslash( $_POST['client_id'] ) ) : 0;
        if ( $client_id ) {
            // Atualiza
            $client_id = wp_update_post( [
                'ID'         => $client_id,
                'post_title' => $name,
            ], true );
        } else {
            $client_id = wp_insert_post( [
                'post_type'   => 'dps_cliente',
                'post_title'  => $name,
                'post_status' => 'publish',
            ], true );
        }

        if ( is_wp_error( $client_id ) || ! $client_id ) {
            DPS_Message_Helper::add_error( __( 'Não foi possível salvar o cliente. Tente novamente.', 'desi-pet-shower' ) );
            if ( is_wp_error( $client_id ) ) {
                DPS_Message_Helper::add_error( $client_id->get_error_message() );
            }
            wp_safe_redirect( self::get_redirect_url( 'clientes' ) );
            exit;
        }

        update_post_meta( $client_id, 'client_cpf', $cpf );
        update_post_meta( $client_id, 'client_phone', $phone );
        update_post_meta( $client_id, 'client_email', $email );
        update_post_meta( $client_id, 'client_birth', $birth );
        update_post_meta( $client_id, 'client_instagram', $insta );
        update_post_meta( $client_id, 'client_facebook', $facebook );
        update_post_meta( $client_id, 'client_photo_auth', $photo_auth );
        update_post_meta( $client_id, 'client_address', $address );
        update_post_meta( $client_id, 'client_referral', $referral );
        // Salva coordenadas se fornecidas
        if ( $lat !== '' && $lng !== '' ) {
            update_post_meta( $client_id, 'client_lat', $lat );
            update_post_meta( $client_id, 'client_lng', $lng );
        }

        DPS_Message_Helper::add_success( __( 'Cliente salvo com sucesso!', 'desi-pet-shower' ) );
        // Redireciona para a aba de clientes
        wp_safe_redirect( self::get_redirect_url( 'clientes' ) );
        exit;
    }

    /**
     * Salva pet (inserção ou atualização)
     */
    private static function save_pet() {
        if ( ! current_user_can( 'dps_manage_pets' ) ) {
            wp_die( __( 'Acesso negado.', 'desi-pet-shower' ) );
        }
        $owner_id  = isset( $_POST['owner_id'] ) ? intval( wp_unslash( $_POST['owner_id'] ) ) : 0;
        $name      = isset( $_POST['pet_name'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_name'] ) ) : '';
        $species   = isset( $_POST['pet_species'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_species'] ) ) : '';
        $breed     = isset( $_POST['pet_breed'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_breed'] ) ) : '';
        $size      = isset( $_POST['pet_size'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_size'] ) ) : '';
        $weight    = isset( $_POST['pet_weight'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_weight'] ) ) : '';
        $coat      = isset( $_POST['pet_coat'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_coat'] ) ) : '';
        $color     = isset( $_POST['pet_color'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_color'] ) ) : '';
        $birth     = isset( $_POST['pet_birth'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_birth'] ) ) : '';
        $sex       = isset( $_POST['pet_sex'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_sex'] ) ) : '';
        $care      = isset( $_POST['pet_care'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pet_care'] ) ) : '';
        $aggressive= isset( $_POST['pet_aggressive'] ) ? 1 : 0;
        // Campos adicionais para pets
        $vaccinations = isset( $_POST['pet_vaccinations'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pet_vaccinations'] ) ) : '';
        $allergies    = isset( $_POST['pet_allergies'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pet_allergies'] ) ) : '';
        $behavior     = isset( $_POST['pet_behavior'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pet_behavior'] ) ) : '';
        
        // Validação de campos obrigatórios
        $errors = [];
        if ( empty( $name ) ) {
            $errors[] = __( 'O campo Nome do Pet é obrigatório.', 'desi-pet-shower' );
        }
        if ( empty( $owner_id ) ) {
            $errors[] = __( 'O campo Cliente (Tutor) é obrigatório.', 'desi-pet-shower' );
        }
        if ( empty( $species ) ) {
            $errors[] = __( 'O campo Espécie é obrigatório.', 'desi-pet-shower' );
        }
        if ( empty( $size ) ) {
            $errors[] = __( 'O campo Tamanho é obrigatório.', 'desi-pet-shower' );
        }
        if ( empty( $sex ) ) {
            $errors[] = __( 'O campo Sexo é obrigatório.', 'desi-pet-shower' );
        }
        
        if ( ! empty( $errors ) ) {
            foreach ( $errors as $error ) {
                DPS_Message_Helper::add_error( $error );
            }
            wp_safe_redirect( self::get_redirect_url( 'pets' ) );
            exit;
        }
        $pet_id = isset( $_POST['pet_id'] ) ? intval( wp_unslash( $_POST['pet_id'] ) ) : 0;
        if ( $pet_id ) {
            // Update
            $pet_id = wp_update_post( [
                'ID'         => $pet_id,
                'post_title' => $name,
            ], true );
        } else {
            $pet_id = wp_insert_post( [
                'post_type'   => 'dps_pet',
                'post_title'  => $name,
                'post_status' => 'publish',
            ], true );
        }

        if ( is_wp_error( $pet_id ) || ! $pet_id ) {
            DPS_Message_Helper::add_error( __( 'Não foi possível salvar o pet. Tente novamente.', 'desi-pet-shower' ) );
            if ( is_wp_error( $pet_id ) ) {
                DPS_Message_Helper::add_error( $pet_id->get_error_message() );
            }
            wp_safe_redirect( self::get_redirect_url( 'pets' ) );
            exit;
        }

        update_post_meta( $pet_id, 'owner_id', $owner_id );
        update_post_meta( $pet_id, 'pet_species', $species );
        update_post_meta( $pet_id, 'pet_breed', $breed );
        update_post_meta( $pet_id, 'pet_size', $size );
        update_post_meta( $pet_id, 'pet_weight', $weight );
        update_post_meta( $pet_id, 'pet_coat', $coat );
        update_post_meta( $pet_id, 'pet_color', $color );
        update_post_meta( $pet_id, 'pet_birth', $birth );
        update_post_meta( $pet_id, 'pet_sex', $sex );
        update_post_meta( $pet_id, 'pet_care', $care );
        update_post_meta( $pet_id, 'pet_aggressive', $aggressive );
        update_post_meta( $pet_id, 'pet_vaccinations', $vaccinations );
        update_post_meta( $pet_id, 'pet_allergies', $allergies );
        update_post_meta( $pet_id, 'pet_behavior', $behavior );
        // Lida com upload da foto do pet, se houver
        if ( isset( $_FILES['pet_photo'] ) && ! empty( $_FILES['pet_photo']['name'] ) ) {
            $file = $_FILES['pet_photo'];
            // Carrega funções de upload do WordPress
            if ( ! function_exists( 'wp_handle_upload' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            if ( ! function_exists( 'wp_check_filetype' ) ) {
                require_once ABSPATH . 'wp-includes/functions.php';
            }
            $overrides = [ 'test_form' => false ];
            $uploaded  = wp_handle_upload( $file, $overrides );
            if ( isset( $uploaded['file'] ) && isset( $uploaded['type'] ) && empty( $uploaded['error'] ) ) {
                $filetype = wp_check_filetype( basename( $uploaded['file'] ), null );
                $attachment = [
                    'post_mime_type' => $filetype['type'],
                    'post_title'     => sanitize_file_name( basename( $uploaded['file'] ) ),
                    'post_content'   => '',
                    'post_status'    => 'inherit',
                ];
                $attach_id = wp_insert_attachment( $attachment, $uploaded['file'] );
                if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
                    require_once ABSPATH . 'wp-admin/includes/image.php';
                }
                $attach_data = wp_generate_attachment_metadata( $attach_id, $uploaded['file'] );
                wp_update_attachment_metadata( $attach_id, $attach_data );
                update_post_meta( $pet_id, 'pet_photo_id', $attach_id );
            }
        }
        // Adiciona mensagem de sucesso
        if ( $pet_id ) {
            DPS_Message_Helper::add_success( __( 'Pet salvo com sucesso!', 'desi-pet-shower' ) );
        }
        // Redireciona para aba pets
        wp_safe_redirect( self::get_redirect_url( 'pets' ) );
        exit;
    }

    /**
     * Atualiza status de um agendamento existente.
     *
     * Este método é chamado via handle_request() quando o usuário altera o status
     * de um agendamento usando o seletor inline. Valida o status contra lista de
     * valores permitidos e dispara hooks pós-salvamento quando finalizado.
     *
     * @since 1.0.0
     * @return void
     */
    private static function update_appointment_status() {
        if ( ! current_user_can( 'dps_manage_appointments' ) ) {
            wp_die( __( 'Acesso negado.', 'desi-pet-shower' ) );
        }

        $redirect_url = isset( $_POST['dps_redirect_url'] ) ? esc_url_raw( wp_unslash( $_POST['dps_redirect_url'] ) ) : '';
        $appt_id      = isset( $_POST['appointment_id'] ) ? intval( wp_unslash( $_POST['appointment_id'] ) ) : 0;
        $status       = isset( $_POST['appointment_status'] ) ? sanitize_text_field( wp_unslash( $_POST['appointment_status'] ) ) : '';
        $valid        = [ 'pendente', 'finalizado', 'finalizado_pago', 'cancelado' ];

        if ( ! $appt_id ) {
            DPS_Message_Helper::add_error( __( 'Agendamento inválido. Selecione um registro para atualizar.', 'desi-pet-shower' ) );
            wp_safe_redirect( $redirect_url ? $redirect_url : self::get_redirect_url( 'agendamentos' ) );
            exit;
        }

        if ( ! in_array( $status, $valid, true ) ) {
            DPS_Message_Helper::add_error( __( 'Selecione um status válido para o agendamento.', 'desi-pet-shower' ) );
            wp_safe_redirect( $redirect_url ? $redirect_url : self::get_redirect_url( 'agendamentos' ) );
            exit;
        }

        update_post_meta( $appt_id, 'appointment_status', $status );
        $appt_type = get_post_meta( $appt_id, 'appointment_type', true );
        if ( ! $appt_type ) {
            $appt_type = 'simple';
        }
        if ( in_array( $status, [ 'finalizado', 'finalizado_pago' ], true ) ) {
            do_action( 'dps_base_after_save_appointment', $appt_id, $appt_type );
        }
        $client_id = (int) get_post_meta( $appt_id, 'appointment_client_id', true );
        self::redirect_with_pending_notice( $client_id );
    }

    /**
     * Salva agendamento (inserção ou atualização).
     *
     * Este método é o ponto de entrada para salvar agendamentos. Ele:
     * 1. Valida e sanitiza os dados via validate_and_sanitize_appointment_data().
     * 2. Decide qual fluxo seguir (assinatura, multi-pet, ou agendamento único).
     * 3. Delega para os métodos especializados correspondentes.
     *
     * REFATORAÇÃO: Este método foi reorganizado para melhorar legibilidade.
     * A lógica de negócio foi preservada, apenas a estrutura foi modularizada
     * em métodos menores com responsabilidades claras.
     *
     * @since 1.0.0
     * @since 1.0.2 Refatorado para usar métodos auxiliares.
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
        $data = self::validate_and_sanitize_appointment_data();
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
            // Redireciona para exibir mensagens de erro
            wp_safe_redirect( self::get_redirect_url( 'agendas' ) );
            exit;
        }

        $appt_type = $data['appt_type'];
        $edit_id   = $data['edit_id'];
        $pet_ids   = $data['pet_ids'];

        // Passo 2: Decidir qual fluxo seguir e delegar para método especializado.
        $result = false;

        // Fluxo 1: Nova assinatura (cria múltiplos agendamentos recorrentes).
        if ( ! $edit_id && 'subscription' === $appt_type ) {
            $result = self::create_subscription_appointments( $data, $context );
        } elseif ( ! $edit_id && 'simple' === $appt_type && count( $pet_ids ) > 1 ) {
            // Fluxo 2: Agendamento simples com múltiplos pets (cria um agendamento por pet).
            $result = self::create_multi_pet_appointments( $data, $context );
        } else {
            // Fluxo 3: Agendamento único (novo ou edição de qualquer tipo).
            $result = self::save_single_appointment( $data, $context );
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
     * Valida e sanitiza os dados de agendamento recebidos via POST.
     *
     * Este método centraliza toda a lógica de validação e sanitização dos campos
     * do formulário de agendamento. Extrai e processa dados de $_POST, aplicando
     * sanitização apropriada para cada tipo de campo.
     *
     * REFATORAÇÃO: Este método foi extraído de save_appointment() para melhorar
     * legibilidade e facilitar testes. É o primeiro passo de uma refatoração
     * gradual para quebrar métodos monolíticos em blocos menores.
     *
     * @since 1.0.2
     * @return array|null Array com dados validados e sanitizados, ou null se validação falhar.
     *                    Estrutura do array:
     *                    - client_id: int
     *                    - pet_ids: array<int>
     *                    - pet_id: int (primeiro pet da lista)
     *                    - date: string (Y-m-d)
     *                    - time: string (H:i)
     *                    - notes: string
     *                    - appt_type: string ('simple'|'subscription'|'past')
     *                    - appt_freq: string ('semanal'|'quinzenal')
     *                    - tosa: string ('0'|'1')
     *                    - tosa_price: float
     *                    - tosa_occurrence: int
     *                    - taxidog: string ('0'|'1')
     *                    - taxi_price: float
     *                    - extra_description: string
     *                    - extra_value: float
     *                    - subscription_base_value: float
     *                    - subscription_total_value: float
     *                    - subscription_extra_description: string
     *                    - subscription_extra_value: float
     *                    - edit_id: int (ID do agendamento sendo editado, ou 0 se novo)
     */
    private static function validate_and_sanitize_appointment_data() {
        $client_id = isset( $_POST['appointment_client_id'] ) ? intval( wp_unslash( $_POST['appointment_client_id'] ) ) : 0;

        // Recebe lista de pets (multi-seleção). Pode ser array ou valor único.
        $raw_pets = isset( $_POST['appointment_pet_ids'] ) ? (array) wp_unslash( $_POST['appointment_pet_ids'] ) : [];
        $pet_ids  = [];
        foreach ( $raw_pets as $pid_raw ) {
            $pid = intval( $pid_raw );
            if ( $pid ) {
                $pet_ids[] = $pid;
            }
        }
        $pet_ids = array_values( array_unique( $pet_ids ) );
        $pet_id  = ! empty( $pet_ids ) ? $pet_ids[0] : 0;

        $date  = isset( $_POST['appointment_date'] ) ? sanitize_text_field( wp_unslash( $_POST['appointment_date'] ) ) : '';
        $time  = isset( $_POST['appointment_time'] ) ? sanitize_text_field( wp_unslash( $_POST['appointment_time'] ) ) : '';
        $notes = isset( $_POST['appointment_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['appointment_notes'] ) ) : '';

        $appt_type = isset( $_POST['appointment_type'] ) ? sanitize_text_field( wp_unslash( $_POST['appointment_type'] ) ) : 'simple';
        $appt_freq = isset( $_POST['appointment_frequency'] ) ? sanitize_text_field( wp_unslash( $_POST['appointment_frequency'] ) ) : '';

        $tosa            = isset( $_POST['appointment_tosa'] ) ? '1' : '0';
        $tosa_price      = 0;
        if ( isset( $_POST['appointment_tosa_price'] ) ) {
            $tosa_price = floatval( str_replace( ',', '.', wp_unslash( $_POST['appointment_tosa_price'] ) ) );
            if ( $tosa_price < 0 ) {
                $tosa_price = 0;
            }
        }
        $tosa_occurrence = isset( $_POST['appointment_tosa_occurrence'] ) ? intval( wp_unslash( $_POST['appointment_tosa_occurrence'] ) ) : 1;

        $taxidog    = isset( $_POST['appointment_taxidog'] ) ? '1' : '0';
        $taxi_price = 0;
        if ( 'simple' === $appt_type && $taxidog && isset( $_POST['appointment_taxidog_price'] ) ) {
            $taxi_price = floatval( str_replace( ',', '.', wp_unslash( $_POST['appointment_taxidog_price'] ) ) );
            if ( $taxi_price < 0 ) {
                $taxi_price = 0;
            }
        }

        $extra_description = isset( $_POST['appointment_extra_description'] ) ? sanitize_text_field( wp_unslash( $_POST['appointment_extra_description'] ) ) : '';
        $extra_value       = isset( $_POST['appointment_extra_value'] ) ? floatval( str_replace( ',', '.', wp_unslash( $_POST['appointment_extra_value'] ) ) ) : 0;
        if ( $extra_value < 0 ) {
            $extra_value = 0;
        }

        $subscription_base_value        = isset( $_POST['subscription_base_value'] ) ? floatval( str_replace( ',', '.', wp_unslash( $_POST['subscription_base_value'] ) ) ) : 0;
        $subscription_total_value       = isset( $_POST['subscription_total_value'] ) ? floatval( str_replace( ',', '.', wp_unslash( $_POST['subscription_total_value'] ) ) ) : 0;
        $subscription_extra_description = isset( $_POST['subscription_extra_description'] ) ? sanitize_text_field( wp_unslash( $_POST['subscription_extra_description'] ) ) : '';
        $subscription_extra_value       = isset( $_POST['subscription_extra_value'] ) ? floatval( str_replace( ',', '.', wp_unslash( $_POST['subscription_extra_value'] ) ) ) : 0;
        if ( $subscription_extra_value < 0 ) {
            $subscription_extra_value = 0;
        }

        // Validação de campos obrigatórios com mensagens específicas.
        $errors = [];
        if ( empty( $client_id ) ) {
            $errors[] = __( 'O campo Cliente é obrigatório.', 'desi-pet-shower' );
        }
        if ( empty( $pet_ids ) ) {
            $errors[] = __( 'Selecione pelo menos um pet para o agendamento.', 'desi-pet-shower' );
        }
        if ( empty( $date ) ) {
            $errors[] = __( 'O campo Data é obrigatório.', 'desi-pet-shower' );
        }
        if ( empty( $time ) ) {
            $errors[] = __( 'O campo Horário é obrigatório.', 'desi-pet-shower' );
        }
        
        if ( ! empty( $errors ) ) {
            DPS_Logger::warning(
                __( 'Tentativa de salvar agendamento com dados incompletos', 'desi-pet-shower' ),
                [
                    'client_id' => $client_id,
                    'pet_ids'   => $pet_ids,
                    'date'      => $date,
                    'time'      => $time,
                    'user_id'   => get_current_user_id(),
                ],
                'appointments'
            );
            foreach ( $errors as $error ) {
                DPS_Message_Helper::add_error( $error );
            }
            return null;
        }

        $edit_id = isset( $_POST['appointment_id'] ) ? intval( wp_unslash( $_POST['appointment_id'] ) ) : 0;

        return [
            'client_id'                      => $client_id,
            'pet_ids'                        => $pet_ids,
            'pet_id'                         => $pet_id,
            'date'                           => $date,
            'time'                           => $time,
            'notes'                          => $notes,
            'appt_type'                      => $appt_type,
            'appt_freq'                      => $appt_freq,
            'tosa'                           => $tosa,
            'tosa_price'                     => $tosa_price,
            'tosa_occurrence'                => $tosa_occurrence,
            'taxidog'                        => $taxidog,
            'taxi_price'                     => $taxi_price,
            'extra_description'              => $extra_description,
            'extra_value'                    => $extra_value,
            'subscription_base_value'        => $subscription_base_value,
            'subscription_total_value'       => $subscription_total_value,
            'subscription_extra_description' => $subscription_extra_description,
            'subscription_extra_value'       => $subscription_extra_value,
            'edit_id'                        => $edit_id,
        ];
    }

    /**
     * Cria agendamentos recorrentes para uma nova assinatura.
     *
     * Este método é responsável por criar a assinatura (post dps_subscription)
     * e todos os agendamentos individuais (posts dps_agendamento) para cada
     * pet e cada ocorrência no ciclo (semanal ou quinzenal).
     *
     * REFATORAÇÃO: Este método foi extraído de save_appointment() para isolar
     * a lógica específica de assinaturas, melhorando legibilidade e manutenção.
     *
     * @since 1.0.2
     * @param array $data Dados validados do formulário (de validate_and_sanitize_appointment_data).
     * @param string $context Contexto de execução (page|ajax).
     * @return array|false Dados do resultado ou false em caso de erro.
     */
    private static function create_subscription_appointments( array $data, $context = 'page' ) {
        $client_id                      = $data['client_id'];
        $pet_ids                        = $data['pet_ids'];
        $date                           = $data['date'];
        $time                           = $data['time'];
        $appt_freq                      = $data['appt_freq'];
        $tosa                           = $data['tosa'];
        $tosa_price                     = $data['tosa_price'];
        $tosa_occurrence                = $data['tosa_occurrence'];
        $taxidog                        = $data['taxidog'];
        $subscription_base_value        = $data['subscription_base_value'];
        $subscription_total_value       = $data['subscription_total_value'];
        $subscription_extra_description = $data['subscription_extra_description'];
        $subscription_extra_value       = $data['subscription_extra_value'];

        // Define serviços padrão: Tosa higiênica e Hidratação.
        $service_names = [ 'Tosa higienica', 'Hidratação' ];
        $service_ids   = [];
        $prices        = [];
        foreach ( $service_names as $sname ) {
            $srv = get_posts( [
                'post_type'      => 'dps_service',
                'posts_per_page' => 1,
                'post_status'    => 'publish',
                'title'          => $sname,
            ] );
            if ( $srv ) {
                $srv_id            = $srv[0]->ID;
                $service_ids[]     = $srv_id;
                $base_price        = (float) get_post_meta( $srv_id, 'service_price', true );
                $prices[ $srv_id ] = $base_price;
            }
        }

        // Calcula preço base do evento.
        $base_event_price = 0;
        foreach ( $prices as $p ) {
            $base_event_price += (float) $p;
        }

        // Define número de ocorrências no ciclo.
        $count_events     = ( 'quinzenal' === $appt_freq ) ? 2 : 4;
        $base_cycle_value = ( $subscription_base_value > 0 ) ? $subscription_base_value : ( $base_event_price * $count_events );
        $extra_cycle_value = ( $subscription_extra_value > 0 ) ? $subscription_extra_value : 0;
        $package_per_pet   = $base_cycle_value + ( ( '1' === $tosa ) ? $tosa_price : 0 ) + $extra_cycle_value;
        $total_package     = $package_per_pet * count( $pet_ids );

        if ( $subscription_total_value > 0 ) {
            $total_package = $subscription_total_value;
            if ( count( $pet_ids ) > 0 ) {
                $package_per_pet = $total_package / count( $pet_ids );
            }
        }

        // Cria post da assinatura.
        $sub_id = wp_insert_post( [
            'post_type'   => 'dps_subscription',
            'post_title'  => $date . ' ' . $time . ' - ' . __( 'Assinatura', 'desi-pet-shower' ),
            'post_status' => 'publish',
        ] );

        if ( ! $sub_id ) {
            DPS_Message_Helper::add_error( __( 'Erro ao criar assinatura.', 'desi-pet-shower' ) );
            return false;
        }

        // Salva metadados da assinatura.
        update_post_meta( $sub_id, 'subscription_client_id', $client_id );
        update_post_meta( $sub_id, 'subscription_pet_id', $pet_ids[0] );
        update_post_meta( $sub_id, 'subscription_pet_ids', $pet_ids );
        update_post_meta( $sub_id, 'subscription_service', 'Assinatura' );
        update_post_meta( $sub_id, 'subscription_frequency', $appt_freq ?: 'semanal' );
        update_post_meta( $sub_id, 'subscription_price', $total_package );

        if ( $subscription_base_value > 0 ) {
            update_post_meta( $sub_id, 'subscription_base_value', $subscription_base_value );
        }
        if ( $subscription_total_value > 0 ) {
            update_post_meta( $sub_id, 'subscription_total_value', $subscription_total_value );
        }
        if ( '' !== $subscription_extra_description || $subscription_extra_value > 0 ) {
            update_post_meta( $sub_id, 'subscription_extra_description', $subscription_extra_description );
            update_post_meta( $sub_id, 'subscription_extra_value', $subscription_extra_value );
        } else {
            delete_post_meta( $sub_id, 'subscription_extra_description' );
            delete_post_meta( $sub_id, 'subscription_extra_value' );
        }

        update_post_meta( $sub_id, 'subscription_tosa', $tosa );
        update_post_meta( $sub_id, 'subscription_tosa_price', $tosa_price );
        update_post_meta( $sub_id, 'subscription_tosa_occurrence', $tosa_occurrence );
        update_post_meta( $sub_id, 'subscription_start_date', $date );
        update_post_meta( $sub_id, 'subscription_start_time', $time );
        update_post_meta( $sub_id, 'subscription_payment_status', 'pendente' );

        // Cria agendamentos individuais.
        $interval_days = ( 'quinzenal' === $appt_freq ) ? 14 : 7;
        $count_events  = ( 'quinzenal' === $appt_freq ) ? 2 : 4;

        $created_ids = [];
        foreach ( $pet_ids as $p_id_each ) {
            $current_dt = DateTime::createFromFormat( 'Y-m-d', $date );
            if ( ! $current_dt ) {
                $current_dt = date_create( $date );
            }
            if ( ! $current_dt ) {
                continue;
            }

            for ( $i = 0; $i < $count_events; $i++ ) {
                $date_i   = $current_dt->format( 'Y-m-d' );
                $appt_new = wp_insert_post( [
                    'post_type'   => 'dps_agendamento',
                    'post_title'  => $date_i . ' ' . $time,
                    'post_status' => 'publish',
                ] );

                if ( $appt_new ) {
                    $created_ids[] = (int) $appt_new;
                    update_post_meta( $appt_new, 'appointment_client_id', $client_id );
                    update_post_meta( $appt_new, 'appointment_pet_id', $p_id_each );
                    update_post_meta( $appt_new, 'appointment_pet_ids', [ $p_id_each ] );
                    update_post_meta( $appt_new, 'appointment_date', $date_i );
                    update_post_meta( $appt_new, 'appointment_time', $time );
                    update_post_meta( $appt_new, 'appointment_notes', __( 'Serviço de assinatura', 'desi-pet-shower' ) );
                    update_post_meta( $appt_new, 'appointment_type', 'subscription' );

                    $is_tosa_event = ( '1' === $tosa && ( $i + 1 ) == $tosa_occurrence );
                    update_post_meta( $appt_new, 'appointment_tosa', $is_tosa_event ? '1' : '0' );
                    update_post_meta( $appt_new, 'appointment_tosa_price', $is_tosa_event ? $tosa_price : 0 );
                    update_post_meta( $appt_new, 'appointment_tosa_occurrence', $tosa_occurrence );
                    update_post_meta( $appt_new, 'appointment_taxidog', $taxidog );
                    update_post_meta( $appt_new, 'appointment_taxidog_price', 0 );
                    update_post_meta( $appt_new, 'appointment_services', $service_ids );
                    update_post_meta( $appt_new, 'appointment_service_prices', $prices );

                    $total_single = $base_event_price + ( $is_tosa_event ? $tosa_price : 0 );
                    update_post_meta( $appt_new, 'appointment_total_value', $total_single );
                    update_post_meta( $appt_new, 'appointment_status', 'pendente' );
                    update_post_meta( $appt_new, 'subscription_id', $sub_id );

                    do_action( 'dps_base_after_save_appointment', $appt_new, 'subscription' );
                }
                $current_dt->modify( '+' . $interval_days . ' days' );
            }
        }

        // Registra transação financeira.
        global $wpdb;
        $table      = $wpdb->prefix . 'dps_transacoes';
        $status_fin = 'em_aberto';
        $desc_fin   = sprintf( __( 'Assinatura: %s (%s)', 'desi-pet-shower' ), 'Assinatura', ( $appt_freq ?: 'semanal' ) );
        $existing_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE plano_id = %d AND data = %s", $sub_id, $date ) );

        if ( $existing_id ) {
            $wpdb->update(
                $table,
                [
                    'cliente_id' => $client_id ?: null,
                    'valor'      => (float) $total_package,
                    'status'     => $status_fin,
                    'categoria'  => __( 'Assinatura', 'desi-pet-shower' ),
                    'tipo'       => 'receita',
                    'descricao'  => $desc_fin,
                ],
                [ 'id' => $existing_id ]
            );
        } else {
            $wpdb->insert(
                $table,
                [
                    'cliente_id'     => $client_id ?: null,
                    'agendamento_id' => null,
                    'plano_id'       => $sub_id,
                    'data'           => $date,
                    'valor'          => (float) $total_package,
                    'categoria'      => __( 'Assinatura', 'desi-pet-shower' ),
                    'tipo'           => 'receita',
                    'status'         => $status_fin,
                    'descricao'      => $desc_fin,
                ]
            );
        }

        DPS_Message_Helper::add_success( __( 'Agendamento de assinatura salvo com sucesso!', 'desi-pet-shower' ) );
        return [
            'client_id'        => $client_id,
            'subscription_id'  => $sub_id,
            'appointment_ids'  => $created_ids,
            'appointment_type' => 'subscription',
        ];
    }

    /**
     * Cria agendamentos para múltiplos pets em um atendimento simples.
     *
     * Quando o usuário seleciona mais de um pet para um agendamento simples
     * (não assinatura), este método cria um agendamento individual para cada pet.
     *
     * REFATORAÇÃO: Este método foi extraído de save_appointment() para isolar
     * a lógica de multi-pets, melhorando legibilidade e manutenção.
     *
     * @since 1.0.2
     * @param array  $data    Dados validados do formulário (de validate_and_sanitize_appointment_data).
     * @param string $context Contexto de execução (page|ajax).
     * @return array|false Dados do resultado ou false em caso de erro.
     */
    private static function create_multi_pet_appointments( array $data, $context = 'page' ) {
        $client_id         = $data['client_id'];
        $pet_ids           = $data['pet_ids'];
        $date              = $data['date'];
        $time              = $data['time'];
        $notes             = $data['notes'];
        $appt_type         = $data['appt_type'];
        $tosa              = $data['tosa'];
        $taxidog           = $data['taxidog'];
        $taxi_price        = $data['taxi_price'];
        $extra_description = $data['extra_description'];
        $extra_value       = $data['extra_value'];

        $posted_total = isset( $_POST['appointment_total'] ) ? floatval( str_replace( ',', '.', wp_unslash( $_POST['appointment_total'] ) ) ) : 0;

        $created = [];
        foreach ( $pet_ids as $p_id_each ) {
            $new_appt = wp_insert_post( [
                'post_type'   => 'dps_agendamento',
                'post_title'  => $date . ' ' . $time,
                'post_status' => 'publish',
            ] );

            if ( $new_appt ) {
                $created[] = (int) $new_appt;
                update_post_meta( $new_appt, 'appointment_client_id', $client_id );
                update_post_meta( $new_appt, 'appointment_pet_id', $p_id_each );
                update_post_meta( $new_appt, 'appointment_pet_ids', $pet_ids );
                update_post_meta( $new_appt, 'appointment_date', $date );
                update_post_meta( $new_appt, 'appointment_time', $time );
                update_post_meta( $new_appt, 'appointment_notes', $notes );
                update_post_meta( $new_appt, 'appointment_type', $appt_type );
                update_post_meta( $new_appt, 'appointment_tosa', $tosa );
                update_post_meta( $new_appt, 'appointment_taxidog', $taxidog );
                update_post_meta( $new_appt, 'appointment_taxidog_price', $taxi_price );
                update_post_meta( $new_appt, 'appointment_total_value', $posted_total );

                if ( '' !== $extra_description || $extra_value > 0 ) {
                    update_post_meta( $new_appt, 'appointment_extra_description', $extra_description );
                    update_post_meta( $new_appt, 'appointment_extra_value', $extra_value );
                }

                update_post_meta( $new_appt, 'appointment_status', 'pendente' );
                do_action( 'dps_base_after_save_appointment', $new_appt, 'simple' );
            }
        }

        if ( empty( $created ) ) {
            DPS_Message_Helper::add_error( __( 'Erro ao salvar agendamento.', 'desi-pet-shower' ) );
            return false;
        }

        DPS_Message_Helper::add_success( __( 'Agendamentos salvos com sucesso!', 'desi-pet-shower' ) );
        return [
            'client_id'        => $client_id,
            'appointment_ids'  => $created,
            'appointment_type' => $appt_type,
        ];
    }

    /**
     * Salva ou atualiza um agendamento único (simple, subscription edit, ou past).
     *
     * Este método lida com a criação ou atualização de agendamentos individuais.
     * É usado para:
     * - Agendamentos simples de um único pet.
     * - Edição de agendamentos existentes (de qualquer tipo).
     * - Agendamentos passados (registro de atendimentos já realizados).
     *
     * REFATORAÇÃO: Este método foi extraído de save_appointment() para isolar
     * a lógica de agendamentos únicos, melhorando legibilidade e manutenção.
     *
     * @since 1.0.2
     * @param array  $data    Dados validados do formulário (de validate_and_sanitize_appointment_data).
     * @param string $context Contexto de execução (page|ajax).
     * @return array|false Dados do resultado ou false em caso de erro.
     */
    private static function save_single_appointment( array $data, $context = 'page' ) {
        $client_id                      = $data['client_id'];
        $pet_id                         = $data['pet_id'];
        $date                           = $data['date'];
        $time                           = $data['time'];
        $notes                          = $data['notes'];
        $appt_type                      = $data['appt_type'];
        $tosa                           = $data['tosa'];
        $tosa_price                     = $data['tosa_price'];
        $taxidog                        = $data['taxidog'];
        $taxi_price                     = $data['taxi_price'];
        $extra_description              = $data['extra_description'];
        $extra_value                    = $data['extra_value'];
        $subscription_base_value        = $data['subscription_base_value'];
        $subscription_total_value       = $data['subscription_total_value'];
        $subscription_extra_description = $data['subscription_extra_description'];
        $subscription_extra_value       = $data['subscription_extra_value'];
        $appt_id                        = $data['edit_id'];

        // Cria ou atualiza o post do agendamento.
        if ( $appt_id ) {
            wp_update_post( [
                'ID'         => $appt_id,
                'post_title' => $date . ' ' . $time,
            ] );
        } else {
            $appt_id = wp_insert_post( [
                'post_type'   => 'dps_agendamento',
                'post_title'  => $date . ' ' . $time,
                'post_status' => 'publish',
            ] );
        }

        if ( ! $appt_id ) {
            DPS_Message_Helper::add_error( __( 'Erro ao salvar agendamento.', 'desi-pet-shower' ) );
            return false;
        }

        // Salva metadados básicos.
        update_post_meta( $appt_id, 'appointment_client_id', $client_id );
        update_post_meta( $appt_id, 'appointment_pet_id', $pet_id );
        update_post_meta( $appt_id, 'appointment_date', $date );
        update_post_meta( $appt_id, 'appointment_time', $time );
        update_post_meta( $appt_id, 'appointment_notes', $notes );
        update_post_meta( $appt_id, 'appointment_type', $appt_type );
        update_post_meta( $appt_id, 'appointment_tosa', $tosa );
        update_post_meta( $appt_id, 'appointment_taxidog', $taxidog );

        if ( 'simple' === $appt_type ) {
            update_post_meta( $appt_id, 'appointment_taxidog_price', $taxi_price );
        } else {
            update_post_meta( $appt_id, 'appointment_taxidog_price', 0 );
        }

        // Lógica específica por tipo de agendamento.
        if ( 'subscription' === $appt_type ) {
            self::save_subscription_appointment_meta( $appt_id, $data );
        } else {
            self::save_simple_or_past_appointment_meta( $appt_id, $data );
        }

        DPS_Message_Helper::add_success( __( 'Agendamento salvo com sucesso!', 'desi-pet-shower' ) );
        return [
            'client_id'        => $client_id,
            'appointment_id'   => $appt_id,
            'appointment_type' => $appt_type,
        ];
    }

    /**
     * Salva metadados específicos de agendamento tipo assinatura.
     *
     * Este método auxiliar é chamado por save_single_appointment() para
     * processar os campos específicos de assinaturas.
     *
     * @since 1.0.2
     * @param int   $appt_id ID do agendamento.
     * @param array $data    Dados validados do formulário.
     * @return void
     */
    private static function save_subscription_appointment_meta( $appt_id, array $data ) {
        $client_id                      = $data['client_id'];
        $pet_id                         = $data['pet_id'];
        $tosa                           = $data['tosa'];
        $tosa_price                     = $data['tosa_price'];
        $subscription_base_value        = $data['subscription_base_value'];
        $subscription_total_value       = $data['subscription_total_value'];
        $subscription_extra_description = $data['subscription_extra_description'];
        $subscription_extra_value       = $data['subscription_extra_value'];

        // Serviços padrão para assinaturas.
        $service_names = [ 'Tosa higienica', 'Hidratação' ];
        $service_ids   = [];
        $prices        = [];

        foreach ( $service_names as $sname ) {
            $srv = get_posts( [
                'post_type'      => 'dps_service',
                'posts_per_page' => 1,
                'post_status'    => 'publish',
                'title'          => $sname,
            ] );
            if ( $srv ) {
                $srv_id            = $srv[0]->ID;
                $service_ids[]     = $srv_id;
                $base_price        = (float) get_post_meta( $srv_id, 'service_price', true );
                $prices[ $srv_id ] = $base_price;
            }
        }

        update_post_meta( $appt_id, 'appointment_services', $service_ids );
        update_post_meta( $appt_id, 'appointment_service_prices', $prices );

        $base_total = 0;
        foreach ( $prices as $p ) {
            $base_total += (float) $p;
        }

        $calculated_total = $base_total;
        if ( '1' === $tosa ) {
            $calculated_total += $tosa_price;
            update_post_meta( $appt_id, 'appointment_tosa_price', $tosa_price );
            update_post_meta( $appt_id, 'appointment_tosa_occurrence', 1 );
        } else {
            update_post_meta( $appt_id, 'appointment_tosa_price', 0 );
            update_post_meta( $appt_id, 'appointment_tosa_occurrence', 0 );
        }

        if ( $subscription_extra_value > 0 ) {
            $calculated_total += $subscription_extra_value;
        }

        $final_subscription_total = $subscription_total_value > 0 ? $subscription_total_value : $calculated_total;
        update_post_meta( $appt_id, 'appointment_total_value', $final_subscription_total );

        if ( $subscription_base_value > 0 ) {
            update_post_meta( $appt_id, 'subscription_base_value', $subscription_base_value );
        } elseif ( $base_total > 0 ) {
            update_post_meta( $appt_id, 'subscription_base_value', $base_total );
        }

        if ( $subscription_total_value > 0 ) {
            update_post_meta( $appt_id, 'subscription_total_value', $subscription_total_value );
        } else {
            update_post_meta( $appt_id, 'subscription_total_value', $final_subscription_total );
        }

        if ( '' !== $subscription_extra_description || $subscription_extra_value > 0 ) {
            update_post_meta( $appt_id, 'subscription_extra_description', $subscription_extra_description );
            update_post_meta( $appt_id, 'subscription_extra_value', $subscription_extra_value );
        } else {
            delete_post_meta( $appt_id, 'subscription_extra_description' );
            delete_post_meta( $appt_id, 'subscription_extra_value' );
        }

        // Vincula assinatura existente, se houver.
        $subs = get_posts( [
            'post_type'      => 'dps_subscription',
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [ 'key' => 'subscription_client_id', 'value' => $client_id, 'compare' => '=' ],
                [ 'key' => 'subscription_pet_id', 'value' => $pet_id, 'compare' => '=' ],
            ],
        ] );
        if ( $subs ) {
            update_post_meta( $appt_id, 'subscription_id', $subs[0]->ID );
        }
    }

    /**
     * Salva metadados específicos de agendamento simples ou passado.
     *
     * Este método auxiliar é chamado por save_single_appointment() para
     * processar campos de agendamentos simples e passados.
     *
     * @since 1.0.2
     * @param int   $appt_id ID do agendamento.
     * @param array $data    Dados validados do formulário.
     * @return void
     */
    private static function save_simple_or_past_appointment_meta( $appt_id, array $data ) {
        $appt_type         = $data['appt_type'];
        $extra_description = $data['extra_description'];
        $extra_value       = $data['extra_value'];

        $posted_total = isset( $_POST['appointment_total'] ) ? floatval( str_replace( ',', '.', wp_unslash( $_POST['appointment_total'] ) ) ) : 0;
        update_post_meta( $appt_id, 'appointment_total_value', $posted_total );

        if ( '' !== $extra_description || $extra_value > 0 ) {
            update_post_meta( $appt_id, 'appointment_extra_description', $extra_description );
            update_post_meta( $appt_id, 'appointment_extra_value', $extra_value );
        } else {
            delete_post_meta( $appt_id, 'appointment_extra_description' );
            delete_post_meta( $appt_id, 'appointment_extra_value' );
        }

        // Lógica específica para agendamentos passados.
        if ( 'past' === $appt_type ) {
            $past_payment_status = isset( $_POST['past_payment_status'] ) ? sanitize_text_field( wp_unslash( $_POST['past_payment_status'] ) ) : '';
            $past_payment_value  = isset( $_POST['past_payment_value'] ) ? max( 0, floatval( str_replace( ',', '.', wp_unslash( $_POST['past_payment_value'] ) ) ) ) : 0;

            update_post_meta( $appt_id, 'past_payment_status', $past_payment_status );

            if ( 'pending' === $past_payment_status ) {
                update_post_meta( $appt_id, 'past_payment_value', $past_payment_value );
            } else {
                delete_post_meta( $appt_id, 'past_payment_value' );
            }

            update_post_meta( $appt_id, 'appointment_status', 'realizado' );
        }
    }

    /**
     * Salva as senhas do plugin base, agenda e outros add‑ons.
     *
     * Este método atualiza opções do WordPress com as novas senhas fornecidas.
     * Espera os campos 'base_password', 'agenda_password' e possivelmente outros
     * via $_POST. É executado somente por usuários autenticados (já verificado
     * em handle_request).
     */
    private static function save_passwords() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Acesso negado.', 'desi-pet-shower' ) );
        }

        $base_pass     = isset( $_POST['base_password'] ) ? sanitize_text_field( wp_unslash( $_POST['base_password'] ) ) : '';
        $agenda_pass   = isset( $_POST['agenda_password'] ) ? sanitize_text_field( wp_unslash( $_POST['agenda_password'] ) ) : '';
        $redirect_url  = self::get_redirect_url( 'senhas' );
        $provided_base = '' !== $base_pass;
        $provided_agenda = '' !== $agenda_pass;

        if ( ! $provided_base && ! $provided_agenda ) {
            DPS_Message_Helper::add_error( __( 'Informe pelo menos uma senha para salvar.', 'desi-pet-shower' ) );
            wp_safe_redirect( $redirect_url );
            exit;
        }

        $errors   = [];
        $min_size = 4;

        if ( $provided_base && strlen( $base_pass ) < $min_size ) {
            $errors[] = sprintf( __( 'A senha do painel principal deve ter pelo menos %d caracteres.', 'desi-pet-shower' ), $min_size );
        }

        if ( $provided_agenda && strlen( $agenda_pass ) < $min_size ) {
            $errors[] = sprintf( __( 'A senha da agenda pública deve ter pelo menos %d caracteres.', 'desi-pet-shower' ), $min_size );
        }

        if ( ! empty( $errors ) ) {
            foreach ( $errors as $error ) {
                DPS_Message_Helper::add_error( $error );
            }
            wp_safe_redirect( $redirect_url );
            exit;
        }

        if ( $provided_base ) {
            update_option( 'dps_base_password', $base_pass );
        }
        if ( $provided_agenda ) {
            update_option( 'dps_agenda_password', $agenda_pass );
        }

        do_action( 'dps_base_save_passwords', wp_unslash( $_POST ) );
        DPS_Message_Helper::add_success( __( 'Senhas salvas com sucesso.', 'desi-pet-shower' ) );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Exibe o formulário de acesso ao painel.
     *
     * Este formulário solicita apenas a senha de administração. Ao ser informada
     * corretamente, o usuário recebe permissão total de gerenciamento. Não há
     * opção de visitante: todos os acessos utilizam a mesma senha definida.
     *
     * @param string $error Mensagem de erro opcional
     * @return string HTML do formulário
     */
    private static function render_login_form( $error = '' ) {
        ob_start();
        echo '<div class="dps-login-wrapper">';
        echo '<h3>' . esc_html__( 'Acesso ao DPS by PRObst', 'desi-pet-shower' ) . '</h3>';
        if ( $error ) {
            echo '<p class="dps-error" style="color:red;">' . esc_html( $error ) . '</p>';
        }
        echo '<form method="post" class="dps-login-form">';
        echo '<p><label>' . esc_html__( 'Senha', 'desi-pet-shower' ) . '<br><input type="password" name="dps_admin_pass" required></label></p>';
        echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Entrar', 'desi-pet-shower' ) . '</button></p>';
        echo '</form>';
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Exibe a página de detalhes de um cliente com layout melhorado e ações de gerenciamento.
     *
     * Esta página mostra:
     * - Cards de resumo com métricas do cliente
     * - Dados pessoais organizados em seções
     * - Lista de pets em formato de cards
     * - Histórico de atendimentos com status e ações
     * - Botões de ação para gerenciamento rápido
     *
     * @since 1.0.0
     * @param int $client_id ID do cliente.
     * @return string HTML da página.
     */
    private static function render_client_page( $client_id ) {
        $client = get_post( $client_id );
        if ( ! $client || 'dps_cliente' !== $client->post_type ) {
            return '<p>' . esc_html__( 'Cliente não encontrado.', 'desi-pet-shower' ) . '</p>';
        }

        // Processar ações antes de renderizar
        self::handle_client_page_actions( $client_id );

        // Coletar dados do cliente
        $data = self::prepare_client_page_data( $client_id, $client );

        ob_start();

        // Mensagens de feedback
        self::render_client_page_notices( $client_id );

        echo '<div class="dps-client-detail">';

        // Header com título e ações
        self::render_client_page_header( $client, $data['base_url'], $client_id );

        // Cards de resumo/métricas
        self::render_client_summary_cards( $data['appointments'], $data['pending_amount'] );

        // Seção: Dados Pessoais
        self::render_client_personal_section( $data['meta'] );

        // Seção: Contato e Redes
        self::render_client_contact_section( $data['meta'] );

        // Seção: Endereço
        self::render_client_address_section( $data['meta'] );

        // Seção: Pets
        self::render_client_pets_section( $data['pets'], $data['base_url'], $client_id );

        // Seção: Histórico de Atendimentos
        self::render_client_appointments_section( $data['appointments'], $data['base_url'], $client_id );

        echo '</div>';

        // Script para envio de histórico por email
        self::render_client_page_scripts();

        return ob_get_clean();
    }

    /**
     * Processa ações da página de detalhes do cliente (gerar histórico, enviar email, etc).
     *
     * @since 1.0.0
     * @param int $client_id ID do cliente.
     */
    private static function handle_client_page_actions( $client_id ) {
        // 1. Gerar histórico HTML
        if ( isset( $_GET['dps_client_history'] ) && '1' === $_GET['dps_client_history'] ) {
            $doc_url = self::generate_client_history_doc( $client_id );
            if ( $doc_url ) {
                // Envio por email se solicitado
                if ( isset( $_GET['send_email'] ) && '1' === $_GET['send_email'] ) {
                    $to_email = isset( $_GET['to_email'] ) && is_email( sanitize_email( $_GET['to_email'] ) ) ? sanitize_email( $_GET['to_email'] ) : '';
                    self::send_client_history_email( $client_id, $doc_url, $to_email );
                    $redirect = add_query_arg( [ 'dps_view' => 'client', 'id' => $client_id, 'sent' => '1' ], remove_query_arg( [ 'dps_client_history', 'send_email', 'to_email', 'sent' ] ) );
                    wp_redirect( $redirect );
                    exit;
                }
                $file_name = basename( $doc_url );
                $redirect  = add_query_arg( [ 'dps_view' => 'client', 'id' => $client_id, 'history_file' => $file_name ], remove_query_arg( [ 'dps_client_history', 'send_email', 'to_email', 'history_file' ] ) );
                wp_redirect( $redirect );
                exit;
            }
        }

        // 2. Exclusão de documentos
        if ( isset( $_GET['dps_delete_doc'] ) && '1' === $_GET['dps_delete_doc'] && isset( $_GET['file'] ) ) {
            $file = sanitize_file_name( wp_unslash( $_GET['file'] ) );
            self::delete_document( $file );
            $redirect = add_query_arg( [ 'dps_view' => 'client', 'id' => $client_id ], remove_query_arg( [ 'dps_delete_doc', 'file' ] ) );
            wp_redirect( $redirect );
            exit;
        }
    }

    /**
     * Prepara todos os dados necessários para a página de detalhes do cliente.
     *
     * @since 1.0.0
     * @param int     $client_id ID do cliente.
     * @param WP_Post $client    Post do cliente.
     * @return array Dados preparados.
     */
    private static function prepare_client_page_data( $client_id, $client ) {
        // Metadados do cliente
        $meta = [
            'cpf'        => get_post_meta( $client_id, 'client_cpf', true ),
            'phone'      => get_post_meta( $client_id, 'client_phone', true ),
            'email'      => get_post_meta( $client_id, 'client_email', true ),
            'birth'      => get_post_meta( $client_id, 'client_birth', true ),
            'instagram'  => get_post_meta( $client_id, 'client_instagram', true ),
            'facebook'   => get_post_meta( $client_id, 'client_facebook', true ),
            'photo_auth' => get_post_meta( $client_id, 'client_photo_auth', true ),
            'address'    => get_post_meta( $client_id, 'client_address', true ),
            'referral'   => get_post_meta( $client_id, 'client_referral', true ),
        ];

        // Lista de pets
        $pets = get_posts( [
            'post_type'      => 'dps_pet',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_key'       => 'owner_id',
            'meta_value'     => $client_id,
        ] );

        // Pré-carregar metadados dos pets
        if ( $pets ) {
            $pet_ids = wp_list_pluck( $pets, 'ID' );
            update_meta_cache( 'post', $pet_ids );
        }

        // Lista de agendamentos ordenada por data (mais recente primeiro para exibição)
        $appointments = get_posts( [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_key'       => 'appointment_date',
            'orderby'        => 'meta_value',
            'order'          => 'DESC',
            'meta_query'     => [
                [ 'key' => 'appointment_client_id', 'value' => $client_id, 'compare' => '=' ],
            ],
        ] );

        // Pré-carregar metadados dos agendamentos
        if ( $appointments ) {
            $appt_ids = wp_list_pluck( $appointments, 'ID' );
            update_meta_cache( 'post', $appt_ids );
        }

        // Calcular pendências financeiras
        $pending_amount = self::calculate_client_pending_amount( $client_id );

        return [
            'meta'           => $meta,
            'pets'           => $pets,
            'appointments'   => $appointments,
            'pending_amount' => $pending_amount,
            'base_url'       => get_permalink(),
        ];
    }

    /**
     * Calcula o valor total de pendências financeiras do cliente.
     *
     * @since 1.0.0
     * @param int $client_id ID do cliente.
     * @return float Valor total pendente.
     */
    private static function calculate_client_pending_amount( $client_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';

        // Verifica se a tabela existe (usa cache estático para evitar verificação repetida)
        static $table_exists = null;
        if ( null === $table_exists ) {
            $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
        }
        if ( ! $table_exists ) {
            return 0.0;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is prefixed and safe
        $pending = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(valor) FROM {$table} WHERE cliente_id = %d AND status = %s",
                $client_id,
                'em_aberto'
            )
        );

        return $pending ? (float) $pending : 0.0;
    }

    /**
     * Renderiza mensagens de feedback na página de detalhes do cliente.
     *
     * @since 1.0.0
     * @param int $client_id ID do cliente.
     */
    private static function render_client_page_notices( $client_id ) {
        // Histórico gerado com sucesso
        if ( isset( $_GET['history_file'] ) ) {
            $file    = sanitize_file_name( wp_unslash( $_GET['history_file'] ) );
            $uploads = wp_upload_dir();
            $url     = trailingslashit( $uploads['baseurl'] ) . 'dps_docs/' . $file;
            echo '<div class="dps-alert dps-alert--success">';
            echo '<strong>' . esc_html__( 'Histórico gerado com sucesso!', 'desi-pet-shower' ) . '</strong> ';
            echo '<a href="' . esc_url( $url ) . '" target="_blank">' . esc_html__( 'Clique aqui para abrir', 'desi-pet-shower' ) . '</a>';
            echo '</div>';
        }

        // Histórico enviado por email
        if ( isset( $_GET['sent'] ) && '1' === $_GET['sent'] ) {
            echo '<div class="dps-alert dps-alert--success">';
            echo esc_html__( 'Histórico enviado por email com sucesso.', 'desi-pet-shower' );
            echo '</div>';
        }
    }

    /**
     * Renderiza o header da página de detalhes do cliente.
     *
     * @since 1.0.0
     * @param WP_Post $client    Post do cliente.
     * @param string  $base_url  URL base da página.
     * @param int     $client_id ID do cliente.
     */
    private static function render_client_page_header( $client, $base_url, $client_id ) {
        $back_url     = remove_query_arg( [ 'dps_view', 'id', 'tab' ] );
        $edit_url     = add_query_arg( [ 'tab' => 'clientes', 'dps_edit' => 'client', 'id' => $client_id ], $base_url );
        $schedule_url = add_query_arg( [ 'tab' => 'agendas', 'pref_client' => $client_id ], $base_url );

        echo '<div class="dps-client-header">';
        echo '<a href="' . esc_url( $back_url ) . '" class="dps-client-header__back">← ' . esc_html__( 'Voltar', 'desi-pet-shower' ) . '</a>';
        echo '<h2 class="dps-client-header__title">' . esc_html( $client->post_title ) . '</h2>';
        echo '<div class="dps-client-header__actions">';
        echo '<a href="' . esc_url( $edit_url ) . '" class="dps-btn-action">';
        echo '✏️ ' . esc_html__( 'Editar', 'desi-pet-shower' );
        echo '</a>';
        echo '<a href="' . esc_url( $schedule_url ) . '" class="dps-btn-action dps-btn-action--primary">';
        echo '📅 ' . esc_html__( 'Novo Agendamento', 'desi-pet-shower' );
        echo '</a>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Renderiza os cards de resumo/métricas do cliente.
     *
     * @since 1.0.0
     * @param array $appointments    Lista de agendamentos.
     * @param float $pending_amount  Valor pendente.
     */
    private static function render_client_summary_cards( $appointments, $pending_amount ) {
        $total_appointments = count( $appointments );
        $last_appointment   = '';
        $total_spent        = 0.0;

        foreach ( $appointments as $appt ) {
            $status = get_post_meta( $appt->ID, 'appointment_status', true );
            $value  = (float) get_post_meta( $appt->ID, 'appointment_total_value', true );

            // Soma apenas atendimentos finalizados e pagos
            if ( in_array( $status, [ 'finalizado_pago', 'finalizado e pago' ], true ) ) {
                $total_spent += $value;
            }

            // Pega a data do último atendimento (primeiro da lista que está ordenada DESC)
            if ( empty( $last_appointment ) ) {
                $date = get_post_meta( $appt->ID, 'appointment_date', true );
                if ( $date ) {
                    $last_appointment = date_i18n( 'd/m/Y', strtotime( $date ) );
                }
            }
        }

        echo '<div class="dps-client-summary">';

        // Total de atendimentos
        echo '<div class="dps-summary-card dps-summary-card--highlight">';
        echo '<span class="dps-summary-card__icon">📋</span>';
        echo '<span class="dps-summary-card__value">' . esc_html( $total_appointments ) . '</span>';
        echo '<span class="dps-summary-card__label">' . esc_html__( 'Total de Atendimentos', 'desi-pet-shower' ) . '</span>';
        echo '</div>';

        // Total gasto
        echo '<div class="dps-summary-card dps-summary-card--success">';
        echo '<span class="dps-summary-card__icon">💰</span>';
        echo '<span class="dps-summary-card__value">R$ ' . esc_html( number_format_i18n( $total_spent, 2 ) ) . '</span>';
        echo '<span class="dps-summary-card__label">' . esc_html__( 'Total Gasto', 'desi-pet-shower' ) . '</span>';
        echo '</div>';

        // Último atendimento
        echo '<div class="dps-summary-card">';
        echo '<span class="dps-summary-card__icon">📅</span>';
        echo '<span class="dps-summary-card__value">' . esc_html( $last_appointment ?: '-' ) . '</span>';
        echo '<span class="dps-summary-card__label">' . esc_html__( 'Último Atendimento', 'desi-pet-shower' ) . '</span>';
        echo '</div>';

        // Pendências
        $pending_class = $pending_amount > 0 ? 'dps-summary-card--warning' : '';
        echo '<div class="dps-summary-card ' . esc_attr( $pending_class ) . '">';
        echo '<span class="dps-summary-card__icon">' . ( $pending_amount > 0 ? '⚠️' : '✅' ) . '</span>';
        echo '<span class="dps-summary-card__value">R$ ' . esc_html( number_format_i18n( $pending_amount, 2 ) ) . '</span>';
        echo '<span class="dps-summary-card__label">' . esc_html__( 'Pendências', 'desi-pet-shower' ) . '</span>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * Renderiza a seção de dados pessoais do cliente.
     *
     * @since 1.0.0
     * @param array $meta Metadados do cliente.
     */
    private static function render_client_personal_section( $meta ) {
        echo '<div class="dps-client-section">';
        echo '<div class="dps-client-section__header">';
        echo '<h3 class="dps-client-section__title">👤 ' . esc_html__( 'Dados Pessoais', 'desi-pet-shower' ) . '</h3>';
        echo '</div>';
        echo '<div class="dps-client-section__content">';
        echo '<div class="dps-info-grid">';

        // CPF
        $has_cpf = ! empty( $meta['cpf'] );
        echo '<div class="dps-info-item' . ( $has_cpf ? '' : ' dps-info-item--empty' ) . '">';
        echo '<span class="dps-info-item__label">' . esc_html__( 'CPF', 'desi-pet-shower' ) . '</span>';
        echo '<span class="dps-info-item__value">' . esc_html( $has_cpf ? $meta['cpf'] : __( 'Não informado', 'desi-pet-shower' ) ) . '</span>';
        echo '</div>';

        // Data de nascimento
        $has_birth = ! empty( $meta['birth'] );
        $birth_fmt = $has_birth ? date_i18n( 'd/m/Y', strtotime( $meta['birth'] ) ) : '';
        echo '<div class="dps-info-item' . ( $has_birth ? '' : ' dps-info-item--empty' ) . '">';
        echo '<span class="dps-info-item__label">' . esc_html__( 'Data de Nascimento', 'desi-pet-shower' ) . '</span>';
        echo '<span class="dps-info-item__value">' . esc_html( $has_birth ? $birth_fmt : __( 'Não informado', 'desi-pet-shower' ) ) . '</span>';
        echo '</div>';

        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Renderiza a seção de contato e redes sociais do cliente.
     *
     * @since 1.0.0
     * @param array $meta Metadados do cliente.
     */
    private static function render_client_contact_section( $meta ) {
        echo '<div class="dps-client-section">';
        echo '<div class="dps-client-section__header">';
        echo '<h3 class="dps-client-section__title">📞 ' . esc_html__( 'Contato e Redes Sociais', 'desi-pet-shower' ) . '</h3>';
        echo '</div>';
        echo '<div class="dps-client-section__content">';
        echo '<div class="dps-info-grid">';

        // Telefone/WhatsApp
        $has_phone = ! empty( $meta['phone'] );
        echo '<div class="dps-info-item' . ( $has_phone ? '' : ' dps-info-item--empty' ) . '">';
        echo '<span class="dps-info-item__label">' . esc_html__( 'Telefone / WhatsApp', 'desi-pet-shower' ) . '</span>';
        if ( $has_phone ) {
            // Usa helper centralizado se disponível, senão faz fallback com código do Brasil
            if ( class_exists( 'DPS_WhatsApp_Helper' ) ) {
                $wa_url = DPS_WhatsApp_Helper::get_link_to_client( $meta['phone'] );
            } else {
                // Fallback: remove não-dígitos e adiciona código do Brasil se necessário
                $phone_digits = preg_replace( '/\D+/', '', $meta['phone'] );
                // Adiciona código do Brasil (55) se o número não começar com ele
                if ( strlen( $phone_digits ) <= 11 && '55' !== substr( $phone_digits, 0, 2 ) ) {
                    $phone_digits = '55' . $phone_digits;
                }
                $wa_url = 'https://wa.me/' . $phone_digits;
            }
            echo '<span class="dps-info-item__value"><a href="' . esc_url( $wa_url ) . '" target="_blank">' . esc_html( $meta['phone'] ) . ' 📱</a></span>';
        } else {
            echo '<span class="dps-info-item__value">' . esc_html__( 'Não informado', 'desi-pet-shower' ) . '</span>';
        }
        echo '</div>';

        // Email
        $has_email = ! empty( $meta['email'] );
        echo '<div class="dps-info-item' . ( $has_email ? '' : ' dps-info-item--empty' ) . '">';
        echo '<span class="dps-info-item__label">' . esc_html__( 'Email', 'desi-pet-shower' ) . '</span>';
        if ( $has_email ) {
            echo '<span class="dps-info-item__value"><a href="mailto:' . esc_attr( $meta['email'] ) . '">' . esc_html( $meta['email'] ) . '</a></span>';
        } else {
            echo '<span class="dps-info-item__value">' . esc_html__( 'Não informado', 'desi-pet-shower' ) . '</span>';
        }
        echo '</div>';

        // Instagram
        $has_instagram = ! empty( $meta['instagram'] );
        echo '<div class="dps-info-item' . ( $has_instagram ? '' : ' dps-info-item--empty' ) . '">';
        echo '<span class="dps-info-item__label">Instagram</span>';
        if ( $has_instagram ) {
            $ig_handle = ltrim( $meta['instagram'], '@' );
            echo '<span class="dps-info-item__value"><a href="https://instagram.com/' . esc_attr( $ig_handle ) . '" target="_blank">@' . esc_html( $ig_handle ) . '</a></span>';
        } else {
            echo '<span class="dps-info-item__value">' . esc_html__( 'Não informado', 'desi-pet-shower' ) . '</span>';
        }
        echo '</div>';

        // Facebook
        $has_facebook = ! empty( $meta['facebook'] );
        echo '<div class="dps-info-item' . ( $has_facebook ? '' : ' dps-info-item--empty' ) . '">';
        echo '<span class="dps-info-item__label">Facebook</span>';
        echo '<span class="dps-info-item__value">' . esc_html( $has_facebook ? $meta['facebook'] : __( 'Não informado', 'desi-pet-shower' ) ) . '</span>';
        echo '</div>';

        // Autorização de fotos
        $photo_auth_val = $meta['photo_auth'];
        $photo_label    = '';
        if ( '' !== $photo_auth_val && null !== $photo_auth_val ) {
            $photo_label = $photo_auth_val ? __( 'Sim', 'desi-pet-shower' ) : __( 'Não', 'desi-pet-shower' );
        }
        echo '<div class="dps-info-item' . ( $photo_label ? '' : ' dps-info-item--empty' ) . '">';
        echo '<span class="dps-info-item__label">' . esc_html__( 'Autorização para Fotos', 'desi-pet-shower' ) . '</span>';
        echo '<span class="dps-info-item__value">' . esc_html( $photo_label ?: __( 'Não informado', 'desi-pet-shower' ) ) . '</span>';
        echo '</div>';

        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Renderiza a seção de endereço do cliente.
     *
     * @since 1.0.0
     * @param array $meta Metadados do cliente.
     */
    private static function render_client_address_section( $meta ) {
        echo '<div class="dps-client-section">';
        echo '<div class="dps-client-section__header">';
        echo '<h3 class="dps-client-section__title">📍 ' . esc_html__( 'Endereço e Indicação', 'desi-pet-shower' ) . '</h3>';
        echo '</div>';
        echo '<div class="dps-client-section__content">';
        echo '<div class="dps-info-grid">';

        // Endereço
        $has_address = ! empty( $meta['address'] );
        echo '<div class="dps-info-item' . ( $has_address ? '' : ' dps-info-item--empty' ) . '">';
        echo '<span class="dps-info-item__label">' . esc_html__( 'Endereço Completo', 'desi-pet-shower' ) . '</span>';
        echo '<span class="dps-info-item__value">' . esc_html( $has_address ? $meta['address'] : __( 'Não informado', 'desi-pet-shower' ) ) . '</span>';
        echo '</div>';

        // Como nos conheceu
        $has_referral = ! empty( $meta['referral'] );
        echo '<div class="dps-info-item' . ( $has_referral ? '' : ' dps-info-item--empty' ) . '">';
        echo '<span class="dps-info-item__label">' . esc_html__( 'Como nos Conheceu', 'desi-pet-shower' ) . '</span>';
        echo '<span class="dps-info-item__value">' . esc_html( $has_referral ? $meta['referral'] : __( 'Não informado', 'desi-pet-shower' ) ) . '</span>';
        echo '</div>';

        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Renderiza a seção de pets do cliente.
     *
     * @since 1.0.0
     * @param array  $pets      Lista de pets.
     * @param string $base_url  URL base da página.
     * @param int    $client_id ID do cliente.
     */
    private static function render_client_pets_section( $pets, $base_url, $client_id ) {
        $pet_count   = count( $pets );
        $add_pet_url = add_query_arg( [ 'tab' => 'pets', 'pref_owner' => $client_id ], $base_url );

        echo '<div class="dps-client-section">';
        echo '<div class="dps-client-section__header">';
        echo '<h3 class="dps-client-section__title">';
        echo '🐾 ' . esc_html__( 'Pets', 'desi-pet-shower' );
        echo '<span class="dps-client-section__count">' . esc_html( $pet_count ) . '</span>';
        echo '</h3>';
        echo '<div class="dps-client-section__actions">';
        echo '<a href="' . esc_url( $add_pet_url ) . '" class="button button-secondary">+ ' . esc_html__( 'Adicionar Pet', 'desi-pet-shower' ) . '</a>';
        echo '</div>';
        echo '</div>';
        echo '<div class="dps-client-section__content">';

        if ( $pets ) {
            echo '<div class="dps-pet-cards">';

            foreach ( $pets as $pet ) {
                self::render_pet_card( $pet, $base_url, $client_id );
            }

            echo '</div>';
        } else {
            echo '<div class="dps-empty-state">';
            echo '<span class="dps-empty-state__icon">🐕</span>';
            echo '<h4 class="dps-empty-state__title">' . esc_html__( 'Nenhum pet cadastrado', 'desi-pet-shower' ) . '</h4>';
            echo '<p class="dps-empty-state__description">' . esc_html__( 'Este cliente ainda não possui pets cadastrados. Clique no botão acima para adicionar.', 'desi-pet-shower' ) . '</p>';
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';
    }

    /**
     * Renderiza um card individual de pet.
     *
     * @since 1.0.0
     * @param WP_Post $pet       Post do pet.
     * @param string  $base_url  URL base da página.
     * @param int     $client_id ID do cliente.
     */
    private static function render_pet_card( $pet, $base_url, $client_id ) {
        // Metadados do pet
        $photo_id   = get_post_meta( $pet->ID, 'pet_photo_id', true );
        $species    = get_post_meta( $pet->ID, 'pet_species', true );
        $breed      = get_post_meta( $pet->ID, 'pet_breed', true );
        $size       = get_post_meta( $pet->ID, 'pet_size', true );
        $weight     = get_post_meta( $pet->ID, 'pet_weight', true );
        $coat       = get_post_meta( $pet->ID, 'pet_coat', true );
        $color      = get_post_meta( $pet->ID, 'pet_color', true );
        $birth      = get_post_meta( $pet->ID, 'pet_birth', true );
        $sex        = get_post_meta( $pet->ID, 'pet_sex', true );
        $care       = get_post_meta( $pet->ID, 'pet_care', true );
        $aggressive = get_post_meta( $pet->ID, 'pet_aggressive', true );

        // Traduzir labels
        $species_label = self::get_pet_species_label( $species );
        $size_label    = self::get_pet_size_label( $size );
        $sex_label     = self::get_pet_sex_label( $sex );

        // URLs de ação
        $edit_url     = add_query_arg( [ 'tab' => 'pets', 'dps_edit' => 'pet', 'id' => $pet->ID ], $base_url );
        $schedule_url = add_query_arg( [ 'tab' => 'agendas', 'pref_client' => $client_id, 'pref_pet' => $pet->ID ], $base_url );

        // Classes do card
        $card_class = 'dps-pet-card';
        if ( $aggressive ) {
            $card_class .= ' dps-pet-card--aggressive';
        }

        // Ícone da espécie
        $species_icon = '🐾';
        if ( 'cao' === $species ) {
            $species_icon = '🐕';
        } elseif ( 'gato' === $species ) {
            $species_icon = '🐈';
        }

        echo '<div class="' . esc_attr( $card_class ) . '">';

        // Header do card
        echo '<div class="dps-pet-card__header">';
        if ( $photo_id ) {
            $img_url = wp_get_attachment_image_url( $photo_id, 'thumbnail' );
            if ( $img_url ) {
                echo '<img src="' . esc_url( $img_url ) . '" alt="' . esc_attr( $pet->post_title ) . '" class="dps-pet-card__photo">';
            } else {
                echo '<div class="dps-pet-card__photo dps-pet-card__photo--placeholder">' . $species_icon . '</div>';
            }
        } else {
            echo '<div class="dps-pet-card__photo dps-pet-card__photo--placeholder">' . $species_icon . '</div>';
        }
        echo '<div class="dps-pet-card__title">';
        echo '<h4 class="dps-pet-card__name">' . esc_html( $pet->post_title ) . '</h4>';
        echo '<p class="dps-pet-card__subtitle">' . esc_html( $species_label ) . ( $breed ? ' • ' . esc_html( $breed ) : '' ) . '</p>';
        echo '</div>';
        if ( $aggressive ) {
            echo '<span class="dps-pet-card__badge">⚠️ ' . esc_html__( 'Agressivo', 'desi-pet-shower' ) . '</span>';
        }
        echo '</div>';

        // Body do card
        echo '<div class="dps-pet-card__body">';
        echo '<div class="dps-pet-card__info">';

        // Porte
        echo '<div class="dps-pet-card__info-item">';
        echo '<span class="dps-pet-card__info-label">' . esc_html__( 'Porte', 'desi-pet-shower' ) . '</span>';
        echo '<span class="dps-pet-card__info-value">' . esc_html( $size_label ?: '-' ) . '</span>';
        echo '</div>';

        // Peso
        echo '<div class="dps-pet-card__info-item">';
        echo '<span class="dps-pet-card__info-label">' . esc_html__( 'Peso', 'desi-pet-shower' ) . '</span>';
        echo '<span class="dps-pet-card__info-value">' . ( $weight ? esc_html( $weight ) . ' kg' : '-' ) . '</span>';
        echo '</div>';

        // Sexo
        echo '<div class="dps-pet-card__info-item">';
        echo '<span class="dps-pet-card__info-label">' . esc_html__( 'Sexo', 'desi-pet-shower' ) . '</span>';
        echo '<span class="dps-pet-card__info-value">' . esc_html( $sex_label ?: '-' ) . '</span>';
        echo '</div>';

        // Nascimento
        echo '<div class="dps-pet-card__info-item">';
        echo '<span class="dps-pet-card__info-label">' . esc_html__( 'Nascimento', 'desi-pet-shower' ) . '</span>';
        echo '<span class="dps-pet-card__info-value">' . ( $birth ? esc_html( date_i18n( 'd/m/Y', strtotime( $birth ) ) ) : '-' ) . '</span>';
        echo '</div>';

        // Pelagem
        if ( $coat || $color ) {
            echo '<div class="dps-pet-card__info-item">';
            echo '<span class="dps-pet-card__info-label">' . esc_html__( 'Pelagem', 'desi-pet-shower' ) . '</span>';
            $pelagem = [];
            if ( $coat ) {
                $pelagem[] = $coat;
            }
            if ( $color ) {
                $pelagem[] = $color;
            }
            echo '<span class="dps-pet-card__info-value">' . esc_html( implode( ', ', $pelagem ) ) . '</span>';
            echo '</div>';
        }

        echo '</div>';

        // Cuidados especiais (se houver)
        if ( $care ) {
            echo '<div class="dps-pet-card__notes">' . esc_html( $care ) . '</div>';
        }

        // Ações
        echo '<div class="dps-pet-card__actions">';
        echo '<a href="' . esc_url( $edit_url ) . '" class="button button-secondary">' . esc_html__( 'Editar', 'desi-pet-shower' ) . '</a>';
        echo '<a href="' . esc_url( $schedule_url ) . '" class="button button-primary">' . esc_html__( 'Agendar', 'desi-pet-shower' ) . '</a>';
        echo '</div>';

        echo '</div>';
        echo '</div>';
    }

    /**
     * Renderiza a seção de histórico de atendimentos do cliente.
     *
     * @since 1.0.0
     * @param array  $appointments Lista de agendamentos.
     * @param string $base_url     URL base da página.
     * @param int    $client_id    ID do cliente.
     */
    private static function render_client_appointments_section( $appointments, $base_url, $client_id ) {
        $appt_count   = count( $appointments );
        $history_link = add_query_arg( [ 'dps_view' => 'client', 'id' => $client_id, 'dps_client_history' => '1' ], remove_query_arg( [ 'dps_client_history', 'send_email', 'to_email' ] ) );
        $email_base   = add_query_arg( [ 'dps_view' => 'client', 'id' => $client_id, 'dps_client_history' => '1', 'send_email' => '1' ], remove_query_arg( [ 'dps_client_history', 'send_email', 'to_email' ] ) );

        echo '<div class="dps-client-section">';
        echo '<div class="dps-client-section__header">';
        echo '<h3 class="dps-client-section__title">';
        echo '📋 ' . esc_html__( 'Histórico de Atendimentos', 'desi-pet-shower' );
        echo '<span class="dps-client-section__count">' . esc_html( $appt_count ) . '</span>';
        echo '</h3>';
        echo '<div class="dps-client-section__actions">';
        echo '<button type="button" class="button button-secondary" id="dps-client-export-csv">' . esc_html__( 'Exportar CSV', 'desi-pet-shower' ) . '</button>';
        echo '<a href="' . esc_url( $history_link ) . '" class="button button-secondary">' . esc_html__( 'Gerar Relatório', 'desi-pet-shower' ) . '</a>';
        echo '<a href="#" class="button button-secondary dps-send-history-email" data-base="' . esc_url( $email_base ) . '">' . esc_html__( 'Enviar por Email', 'desi-pet-shower' ) . '</a>';
        echo '</div>';
        echo '</div>';
        echo '<div class="dps-client-section__content">';

        if ( $appointments ) {
            echo '<div class="dps-table-wrapper">';
            echo '<table class="dps-table" id="dps-client-history-table">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__( 'Data', 'desi-pet-shower' ) . '</th>';
            echo '<th>' . esc_html__( 'Horário', 'desi-pet-shower' ) . '</th>';
            echo '<th>' . esc_html__( 'Pet', 'desi-pet-shower' ) . '</th>';
            echo '<th class="hide-mobile">' . esc_html__( 'Serviços', 'desi-pet-shower' ) . '</th>';
            echo '<th>' . esc_html__( 'Valor', 'desi-pet-shower' ) . '</th>';
            echo '<th>' . esc_html__( 'Status', 'desi-pet-shower' ) . '</th>';
            echo '<th class="hide-mobile">' . esc_html__( 'Observações', 'desi-pet-shower' ) . '</th>';
            echo '<th class="dps-no-export">' . esc_html__( 'Ações', 'desi-pet-shower' ) . '</th>';
            echo '</tr></thead>';
            echo '<tbody>';

            // Caches para evitar múltiplas queries
            $pet_cache      = [];
            $services_cache = [];

            foreach ( $appointments as $appt ) {
                $date        = get_post_meta( $appt->ID, 'appointment_date', true );
                $time        = get_post_meta( $appt->ID, 'appointment_time', true );
                $pet_id      = get_post_meta( $appt->ID, 'appointment_pet_id', true );
                $notes       = get_post_meta( $appt->ID, 'appointment_notes', true );
                $status_meta = get_post_meta( $appt->ID, 'appointment_status', true );
                $total_value = (float) get_post_meta( $appt->ID, 'appointment_total_value', true );
                $services    = get_post_meta( $appt->ID, 'appointment_services', true );

                // Obter nome do pet (com cache)
                $pet_name = '-';
                if ( $pet_id ) {
                    if ( ! isset( $pet_cache[ $pet_id ] ) ) {
                        $pet = get_post( $pet_id );
                        $pet_cache[ $pet_id ] = $pet ? $pet->post_title : '-';
                    }
                    $pet_name = $pet_cache[ $pet_id ];
                }

                // Obter nomes dos serviços (com cache)
                $services_text = '-';
                if ( is_array( $services ) && ! empty( $services ) ) {
                    $names = [];
                    foreach ( $services as $srv_id ) {
                        if ( ! array_key_exists( $srv_id, $services_cache ) ) {
                            $srv = get_post( $srv_id );
                            $services_cache[ $srv_id ] = $srv ? $srv->post_title : '';
                        }
                        if ( ! empty( $services_cache[ $srv_id ] ) ) {
                            $names[] = $services_cache[ $srv_id ];
                        }
                    }
                    if ( $names ) {
                        $services_text = implode( ', ', $names );
                    }
                }

                // Status badge
                $status_info = self::get_appointment_status_info( $status_meta );

                $date_fmt = $date ? date_i18n( 'd/m/Y', strtotime( $date ) ) : '-';

                // Limite de palavras para observações na tabela
                $notes_word_limit = apply_filters( 'dps_client_history_notes_word_limit', 10 );

                // URLs de ação
                $edit_url      = add_query_arg( [ 'tab' => 'agendas', 'dps_edit' => 'appointment', 'id' => $appt->ID ], $base_url );
                $duplicate_url = add_query_arg( [ 'tab' => 'agendas', 'dps_duplicate' => 'appointment', 'id' => $appt->ID ], $base_url );

                echo '<tr>';
                echo '<td>' . esc_html( $date_fmt ) . '</td>';
                echo '<td>' . esc_html( $time ?: '-' ) . '</td>';
                echo '<td>' . esc_html( $pet_name ) . '</td>';
                echo '<td class="hide-mobile">' . esc_html( $services_text ) . '</td>';
                echo '<td>R$ ' . esc_html( number_format_i18n( $total_value, 2 ) ) . '</td>';
                echo '<td><span class="dps-status-badge ' . esc_attr( $status_info['class'] ) . '">' . esc_html( $status_info['label'] ) . '</span></td>';
                echo '<td class="hide-mobile">' . esc_html( $notes ? wp_trim_words( $notes, $notes_word_limit, '...' ) : '-' ) . '</td>';
                echo '<td class="dps-actions-cell dps-no-export">';
                echo '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Editar', 'desi-pet-shower' ) . '</a>';
                echo '<span class="dps-action-separator" aria-hidden="true"> | </span>';
                echo '<a href="' . esc_url( $duplicate_url ) . '" title="' . esc_attr__( 'Duplicar agendamento', 'desi-pet-shower' ) . '">' . esc_html__( 'Duplicar', 'desi-pet-shower' ) . '</a>';
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        } else {
            echo '<div class="dps-empty-state">';
            echo '<span class="dps-empty-state__icon">📅</span>';
            echo '<h4 class="dps-empty-state__title">' . esc_html__( 'Nenhum atendimento encontrado', 'desi-pet-shower' ) . '</h4>';
            echo '<p class="dps-empty-state__description">' . esc_html__( 'Este cliente ainda não possui atendimentos registrados.', 'desi-pet-shower' ) . '</p>';
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';
    }

    /**
     * Renderiza scripts JavaScript da página de detalhes do cliente.
     *
     * @since 1.0.0
     */
    private static function render_client_page_scripts() {
        ?>
        <script>
        (function($){
            $(document).on('click', '.dps-send-history-email', function(e){
                e.preventDefault();
                var base = $(this).data('base');
                var email = prompt('<?php echo esc_js( __( 'Para qual email deseja enviar? Deixe em branco para usar o email cadastrado.', 'desi-pet-shower' ) ); ?>');
                if (email === null) {
                    return;
                }
                email = email.trim();
                var url = base;
                if (email) {
                    url += '&to_email=' + encodeURIComponent(email);
                }
                window.location.href = url;
            });

            // Exportar CSV do histórico do cliente
            $(document).on('click', '#dps-client-export-csv', function(e){
                e.preventDefault();
                var $table = $('#dps-client-history-table');
                if (!$table.length) {
                    alert('<?php echo esc_js( __( 'Nenhum atendimento para exportar.', 'desi-pet-shower' ) ); ?>');
                    return;
                }
                var headers = [];
                $table.find('thead th:not(.dps-no-export)').each(function(){
                    headers.push($(this).text().trim());
                });
                var csvLines = [];
                csvLines.push(headers.map(function(text){
                    return '"' + text.replace(/"/g, '""') + '"';
                }).join(';'));
                $table.find('tbody tr').each(function(){
                    var columns = [];
                    $(this).find('td:not(.dps-no-export)').each(function(){
                        var value = $(this).text().replace(/\s+/g, ' ').trim();
                        columns.push('"' + value.replace(/"/g, '""') + '"');
                    });
                    csvLines.push(columns.join(';'));
                });
                var blob = new Blob(['\ufeff' + csvLines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
                var url = URL.createObjectURL(blob);
                var anchor = document.createElement('a');
                anchor.href = url;
                anchor.download = 'historico-cliente-' + new Date().toISOString().split('T')[0] + '.csv';
                document.body.appendChild(anchor);
                anchor.click();
                document.body.removeChild(anchor);
                URL.revokeObjectURL(url);
            });
        })(jQuery);
        </script>
        <?php
    }

    /**
     * Retorna dataset de raças por espécie, incluindo lista de populares.
     *
     * @since 1.0.0
     * @return array
     */
    private static function get_breed_dataset() {
        static $dataset = null;

        if ( null !== $dataset ) {
            return $dataset;
        }

        $dog_breeds = [
            'SRD (Sem Raça Definida)',
            'Affenpinscher',
            'Afghan Hound',
            'Airedale Terrier',
            'Akita',
            'Alaskan Malamute',
            'American Bulldog',
            'American Cocker Spaniel',
            'American Pit Bull Terrier',
            'American Staffordshire Terrier',
            'Basenji',
            'Basset Hound',
            'Beagle',
            'Bearded Collie',
            'Belgian Malinois',
            'Bernese Mountain Dog (Boiadeiro Bernês)',
            'Bichon Frisé',
            'Bichon Havanês',
            'Bloodhound',
            'Boiadeiro Australiano',
            'Border Collie',
            'Borzói',
            'Boston Terrier',
            'Boxer',
            'Bulldog',
            'Bulldog Americano',
            'Bulldog Campeiro',
            'Bulldog Francês',
            'Bulldog Inglês',
            'Bull Terrier',
            'Bullmastiff',
            'Cairn Terrier',
            'Cane Corso',
            'Cão Afegão',
            'Cão de Água Português',
            'Cão de Crista Chinês',
            'Cão de Pastor Alemão (Pastor Alemão)',
            'Cão de Pastor Shetland',
            'Cavalier King Charles Spaniel',
            'Chesapeake Bay Retriever',
            'Chihuahua',
            'Chow Chow',
            'Cocker Spaniel',
            'Collie',
            'Coton de Tulear',
            'Dachshund (Teckel)',
            'Dálmata',
            'Dobermann',
            'Dogo Argentino',
            'Dogue Alemão',
            'Fila Brasileiro',
            'Fox Paulistinha',
            'Galgo Inglês',
            'Golden Retriever',
            'Greyhound',
            'Husky Siberiano',
            'Irish Setter',
            'Irish Wolfhound',
            'Jack Russell Terrier',
            'Kelpie Australiano',
            'Kerry Blue Terrier',
            'Labradoodle',
            'Labrador Retriever',
            'Lhasa Apso',
            'Lulu da Pomerânia (Spitz Alemão)',
            'Malamute do Alasca',
            'Maltês',
            'Mastiff Inglês',
            'Mastim Tibetano',
            'Old English Sheepdog (Bobtail)',
            'Papillon',
            'Pastor Australiano',
            'Pastor Belga Malinois',
            'Pastor de Shetland',
            'Pequinês',
            'Pinscher',
            'Pinscher Miniatura',
            'Pit Bull Terrier',
            'Podengo Português',
            'Poodle',
            'Poodle Toy',
            'Pug',
            'Rottweiler',
            'Samoieda',
            'Schnauzer',
            'Scottish Terrier',
            'Serra da Estrela',
            'Shar Pei',
            'Shiba Inu',
            'Shih Tzu',
            'Spitz Japonês',
            'Springer Spaniel Inglês',
            'Staffordshire Bull Terrier',
            'Terra-Nova',
            'Vira-lata',
            'Weimaraner',
            'Welsh Corgi Pembroke',
            'Whippet',
            'Yorkshire Terrier',
        ];

        $cat_breeds = [
            'SRD (Sem Raça Definida)',
            'Abissínio',
            'Angorá Turco',
            'Azul Russo',
            'Bengal',
            'Birmanês',
            'British Shorthair',
            'Chartreux',
            'Cornish Rex',
            'Devon Rex',
            'Exótico de Pelo Curto',
            'Himalaio',
            'LaPerm',
            'Maine Coon',
            'Manx',
            'Munchkin',
            'Norueguês da Floresta',
            'Ocicat',
            'Oriental de Pelo Curto',
            'Persa',
            'Ragdoll',
            'Sagrado da Birmânia',
            'Savannah',
            'Scottish Fold',
            'Selkirk Rex',
            'Siamês',
            'Siberiano',
            'Singapura',
            'Somali',
            'Sphynx',
            'Tonquinês',
            'Toyger',
            'Van Turco',
        ];

        $dataset = [
            'cao'  => [
                'popular' => [ 'SRD (Sem Raça Definida)', 'Shih Tzu', 'Poodle', 'Labrador Retriever', 'Golden Retriever' ],
                'all'     => $dog_breeds,
            ],
            'gato' => [
                'popular' => [ 'SRD (Sem Raça Definida)', 'Siamês', 'Persa', 'Maine Coon', 'Ragdoll' ],
                'all'     => $cat_breeds,
            ],
        ];

        $dataset['all'] = [
            'popular' => array_values( array_unique( array_merge( $dataset['cao']['popular'], $dataset['gato']['popular'] ) ) ),
            'all'     => array_values( array_unique( array_merge( $dog_breeds, $cat_breeds ) ) ),
        ];

        return $dataset;
    }

    /**
     * Monta lista de opções de raças para a espécie selecionada.
     *
     * @since 1.0.0
     * @param string $species Código da espécie (cao/gato/outro).
     * @return array Lista ordenada com populares primeiro.
     */
    private static function get_breed_options_for_species( $species ) {
        $dataset  = self::get_breed_dataset();
        $selected = isset( $dataset[ $species ] ) ? $dataset[ $species ] : $dataset['all'];
        $merged   = array_merge( $selected['popular'], $selected['all'] );

        return array_values( array_unique( $merged ) );
    }

    /**
     * Retorna o label traduzido para a espécie do pet.
     *
     * @since 1.0.0
     * @param string $species Código da espécie.
     * @return string Label traduzido.
     */
    private static function get_pet_species_label( $species ) {
        $labels = [
            'cao'   => __( 'Cachorro', 'desi-pet-shower' ),
            'gato'  => __( 'Gato', 'desi-pet-shower' ),
            'outro' => __( 'Outro', 'desi-pet-shower' ),
        ];

        return isset( $labels[ $species ] ) ? $labels[ $species ] : $species;
    }

    /**
     * Retorna o label traduzido para o tamanho do pet.
     *
     * @since 1.0.0
     * @param string $size Código do tamanho.
     * @return string Label traduzido.
     */
    private static function get_pet_size_label( $size ) {
        $labels = [
            'pequeno' => __( 'Pequeno', 'desi-pet-shower' ),
            'medio'   => __( 'Médio', 'desi-pet-shower' ),
            'grande'  => __( 'Grande', 'desi-pet-shower' ),
        ];

        return isset( $labels[ $size ] ) ? $labels[ $size ] : $size;
    }

    /**
     * Retorna o label traduzido para o sexo do pet.
     *
     * @since 1.0.0
     * @param string $sex Código do sexo.
     * @return string Label traduzido.
     */
    private static function get_pet_sex_label( $sex ) {
        $labels = [
            'macho' => __( 'Macho', 'desi-pet-shower' ),
            'femea' => __( 'Fêmea', 'desi-pet-shower' ),
        ];

        return isset( $labels[ $sex ] ) ? $labels[ $sex ] : $sex;
    }

    /**
     * Retorna informações de status do agendamento (label e classe CSS).
     *
     * @since 1.0.0
     * @param string $status Status bruto do agendamento.
     * @return array Array com 'label' e 'class'.
     */
    private static function get_appointment_status_info( $status ) {
        switch ( $status ) {
            case 'finalizado_pago':
            case 'finalizado e pago':
                return [
                    'label' => __( 'Pago', 'desi-pet-shower' ),
                    'class' => 'dps-status-badge--paid',
                ];
            case 'finalizado':
                return [
                    'label' => __( 'Finalizado', 'desi-pet-shower' ),
                    'class' => 'dps-status-badge--pending',
                ];
            case 'cancelado':
                return [
                    'label' => __( 'Cancelado', 'desi-pet-shower' ),
                    'class' => 'dps-status-badge--cancelled',
                ];
            case 'pendente':
            default:
                return [
                    'label' => __( 'Agendado', 'desi-pet-shower' ),
                    'class' => 'dps-status-badge--scheduled',
                ];
        }
    }

    /**
     * Gera um arquivo HTML contendo o histórico de todos os atendimentos de um cliente.
     * O arquivo é salvo na pasta uploads/dps_docs e retorna a URL pública. Se já existir
     * um documento gerado recentemente (nas últimas 24 horas) ele será reutilizado.
     *
     * @param int $client_id
     * @return string|false URL do arquivo gerado ou false em caso de erro
     */
    private static function generate_client_history_doc( $client_id ) {
        // Busca appointments deste cliente
        $appointments = get_posts( [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_key'       => 'appointment_date',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'meta_query'     => [
                [ 'key' => 'appointment_client_id', 'value' => $client_id, 'compare' => '=' ],
            ],
        ] );
        // Caminhos de upload
        $uploads = wp_upload_dir();
        $dir     = trailingslashit( $uploads['basedir'] ) . 'dps_docs';
        if ( ! file_exists( $dir ) ) {
            wp_mkdir_p( $dir );
        }
        // Gera um nome de arquivo seguindo o padrão Historico_NOMEDOCLIENTE_NOMEDOPET_DATA.html
        $timestamp = current_time( 'timestamp' );
        // Obtém dados do cliente para formar o slug
        $client    = get_post( $client_id );
        $client_name  = $client ? $client->post_title : '';
        $client_slug  = sanitize_title( $client_name );
        $client_slug  = str_replace( '-', '_', $client_slug );
        // Obtém primeiro pet do cliente para incluir no nome, se existir
        $first_pet_slug = 'todos';
        $client_pets = get_posts( [
            'post_type'      => 'dps_pet',
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'meta_key'       => 'owner_id',
            'meta_value'     => $client_id,
        ] );
        if ( $client_pets ) {
            $first_pet_name = $client_pets[0]->post_title;
            $pet_slug       = sanitize_title( $first_pet_name );
            $pet_slug       = str_replace( '-', '_', $pet_slug );
            $first_pet_slug = $pet_slug;
        }
        $date_str = date_i18n( 'Y-m-d', $timestamp );
        $filename  = 'Historico_' . $client_slug . '_' . $first_pet_slug . '_' . $date_str . '.html';
        $filepath  = trailingslashit( $dir ) . $filename;
        $url       = trailingslashit( $uploads['baseurl'] ) . 'dps_docs/' . $filename;
        // O nome e o objeto do cliente já foram obtidos anteriormente para o slug.
        $client_email = get_post_meta( $client_id, 'client_email', true );
        // Construir HTML
        $html  = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Histórico de Atendimentos</title>';
        $html .= '<style>body{font-family:Arial,sans-serif;font-size:14px;line-height:1.4;color:#333;padding:20px;}';
        $html .= '.header{display:flex;align-items:center;margin-bottom:20px;}';
        $html .= '.header img{max-height:80px;margin-right:15px;}';
        $html .= '.header h2{margin:0;}';
        $html .= 'table{width:100%;border-collapse:collapse;margin-top:10px;}';
        $html .= 'th,td{border:1px solid #ccc;padding:8px;text-align:left;}';
        $html .= 'th{background:#f0f0f0;}';
        $html .= '</style></head><body>';
        // Cabeçalho com logo ou nome do site
        $html .= '<div class="header">';
        // Logo do tema se existir
        $logo_id = get_theme_mod( 'custom_logo' );
        if ( $logo_id ) {
            $logo_url_arr = wp_get_attachment_image_src( $logo_id, 'full' );
            if ( $logo_url_arr ) {
                $html .= '<img src="' . esc_url( $logo_url_arr[0] ) . '" alt="Logo">';
            }
        }
        $html .= '<div><h2>Histórico de Atendimentos</h2><p>Cliente: ' . esc_html( $client_name ) . '</p>';
        if ( $client_email ) {
            $html .= '<p>Email: ' . esc_html( $client_email ) . '</p>';
        }
        $html .= '<p>Data de geração: ' . date_i18n( 'd-m-Y H:i', $timestamp ) . '</p></div></div>';
        // Tabela de atendimentos
        $html .= '<table><thead><tr><th>Data</th><th>Horário</th><th>Pet</th><th>Serviços</th><th>Total (R$)</th><th>Status</th></tr></thead><tbody>';
        if ( $appointments ) {
            foreach ( $appointments as $appt ) {
                $date   = get_post_meta( $appt->ID, 'appointment_date', true );
                $time   = get_post_meta( $appt->ID, 'appointment_time', true );
                $pet_id = get_post_meta( $appt->ID, 'appointment_pet_id', true );
                $pet    = $pet_id ? get_post( $pet_id ) : null;
                $services = get_post_meta( $appt->ID, 'appointment_services', true );
                $prices   = get_post_meta( $appt->ID, 'appointment_service_prices', true );
                if ( ! is_array( $prices ) ) {
                    $prices = [];
                }
                // Monta lista de serviços e calcula total
                $service_lines = [];
                $total = 0.0;
                if ( is_array( $services ) ) {
                    foreach ( $services as $idx => $srv_id ) {
                        $srv = get_post( $srv_id );
                        $srv_name  = $srv ? $srv->post_title : '';
                        $price_val = isset( $prices[ $idx ] ) ? floatval( $prices[ $idx ] ) : 0.0;
                        $total    += $price_val;
                        $price_fmt = number_format( $price_val, 2, ',', '.' );
                        $service_lines[] = $srv_name . ' (R$ ' . $price_fmt . ')';
                    }
                }
                $services_str = $service_lines ? implode( ', ', $service_lines ) : '-';
                $total_fmt    = number_format( $total, 2, ',', '.' );
                // Status
                $status_meta = get_post_meta( $appt->ID, 'appointment_status', true );
                $status_label = '';
                if ( $status_meta === 'finalizado_pago' || $status_meta === 'finalizado e pago' ) {
                    $status_label = 'Pago';
                } elseif ( $status_meta === 'finalizado' ) {
                    $status_label = 'Pendente';
                } elseif ( $status_meta === 'cancelado' ) {
                    $status_label = 'Cancelado';
                } else {
                    $status_label = 'Pendente';
                }
                $date_fmt = $date ? date_i18n( 'd-m-Y', strtotime( $date ) ) : '';
                $html .= '<tr><td>' . esc_html( $date_fmt ) . '</td><td>' . esc_html( $time ) . '</td><td>' . esc_html( $pet ? $pet->post_title : '-' ) . '</td><td>' . esc_html( $services_str ) . '</td><td>' . esc_html( $total_fmt ) . '</td><td>' . esc_html( $status_label ) . '</td></tr>';
            }
        } else {
            $html .= '<tr><td colspan="6">Nenhum atendimento encontrado.</td></tr>';
        }
        $html .= '</tbody></table>';
        // Rodapé com dados da loja (informações fixas conforme solicitado)
        $html .= '<p style="margin-top:30px;font-size:12px;">Banho e Tosa Desi Pet Shower – Rua Agua Marinha, 45 – Residencial Galo de Ouro, Cerquilho, SP<br>Whatsapp: 15 9 9160-6299<br>Email: contato@desi.pet</p>';
        $html .= '</body></html>';

        // Valida que o caminho do arquivo está dentro do diretório permitido (uploads/dps_docs)
        // Usa $dir que já foi criado no início da função
        $real_allowed_dir = realpath( $dir );
        $file_dir = dirname( $filepath );
        $real_file_dir = realpath( $file_dir );

        // Se o diretório permitido não existe ou não foi resolvido, há problema na configuração
        if ( false === $real_allowed_dir ) {
            DPS_Logger::error(
                __( 'Diretório de documentos não existe', 'desi-pet-shower' ),
                [
                    'dir'        => $dir,
                    'filepath'   => $filepath,
                    'client_id'  => $client_id,
                ],
                'documents'
            );
            return false;
        }

        // Se o diretório do arquivo não foi resolvido ou não está dentro do diretório permitido
        if ( false === $real_file_dir || 0 !== strpos( $real_file_dir, $real_allowed_dir ) ) {
            DPS_Logger::error(
                __( 'Tentativa de escrita fora do diretório permitido', 'desi-pet-shower' ),
                [
                    'filepath'    => $filepath,
                    'allowed_dir' => $dir,
                ],
                'security'
            );
            return false;
        }

        // Salva arquivo com tratamento de erro
        $written = file_put_contents( $filepath, $html );
        if ( false === $written ) {
            $last_error = error_get_last();
            DPS_Logger::error(
                __( 'Erro ao gerar documento de histórico', 'desi-pet-shower' ),
                [
                    'filepath'   => $filepath,
                    'client_id'  => $client_id,
                    'php_error'  => $last_error ? $last_error['message'] : '',
                ],
                'documents'
            );
            return false;
        }

        return $url;
    }

    /**
     * Envia o histórico de atendimentos de um cliente por email, anexando o arquivo gerado
     * e incluindo um link para visualização.
     *
     * @param int    $client_id
     * @param string $doc_url URL do documento previamente gerado
     * @return void
     */
    private static function send_client_history_email( $client_id, $doc_url, $custom_email = '' ) {
        $client = get_post( $client_id );
        if ( ! $client ) {
            return;
        }
        // Determina email de destino: custom_email se fornecido e válido; caso contrário, email do cliente
        $default_to = get_post_meta( $client_id, 'client_email', true );
        $to = '';
        if ( $custom_email && is_email( $custom_email ) ) {
            $to = $custom_email;
        } elseif ( $default_to && is_email( $default_to ) ) {
            $to = $default_to;
        } else {
            return;
        }
        $name    = $client->post_title;
        $subject = 'Histórico de Atendimentos - ' . get_bloginfo( 'name' );
        // Lê conteúdo do documento para incorporar ao corpo do email
        $uploads  = wp_upload_dir();
        $file_path = str_replace( $uploads['baseurl'], $uploads['basedir'], $doc_url );
        $body_html = '';

        // Valida que o caminho do arquivo está dentro do diretório permitido (uploads/dps_docs)
        $allowed_dir = trailingslashit( $uploads['basedir'] ) . 'dps_docs';
        $real_allowed_dir = realpath( $allowed_dir );

        // Se o diretório permitido não existe, não há como validar o caminho seguramente
        $is_allowed_path = false;
        if ( false !== $real_allowed_dir && file_exists( $file_path ) ) {
            $real_file_path = realpath( $file_path );
            $is_allowed_path = ( false !== $real_file_path && 0 === strpos( $real_file_path, $real_allowed_dir ) );
        }

        if ( $is_allowed_path ) {
            $content = file_get_contents( $file_path );
            if ( false !== $content ) {
                $body_html = $content;
            } else {
                $last_error = error_get_last();
                DPS_Logger::warning(
                    __( 'Falha ao ler conteúdo do documento de histórico', 'desi-pet-shower' ),
                    [
                        'file_path'  => $file_path,
                        'client_id'  => $client_id,
                        'php_error'  => $last_error ? $last_error['message'] : '',
                    ],
                    'documents'
                );
            }
        } elseif ( file_exists( $file_path ) ) {
            // Arquivo existe mas não está no caminho permitido
            DPS_Logger::error(
                __( 'Tentativa de leitura fora do diretório permitido', 'desi-pet-shower' ),
                [
                    'file_path'   => $file_path,
                    'allowed_dir' => $allowed_dir,
                ],
                'security'
            );
        }

        // Monta corpo com saudação e dados da loja
        $message  = '<p>Olá ' . esc_html( $name ) . ',</p>';
        $message .= '<p>Segue abaixo o histórico de atendimentos do seu pet:</p>';
        if ( $body_html ) {
            $message .= '<div style="border:1px solid #ddd;padding:10px;margin-bottom:20px;">' . $body_html . '</div>';
        } else {
            $message .= '<p><a href="' . esc_url( $doc_url ) . '">Clique aqui para visualizar o histórico</a></p>';
        }
        // Dados da loja conforme solicitado
        $message .= '<p>Atenciosamente,<br>Banho e Tosa Desi Pet Shower<br>Rua Agua Marinha, 45 – Residencial Galo de Ouro, Cerquilho, SP<br>Whatsapp: 15 9 9160-6299<br>Email: contato@desi.pet</p>';
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        // Anexa arquivo HTML (apenas se caminho for permitido)
        // Nota: $is_allowed_path só é true se file_exists() for verdadeiro
        $attachments = [];
        if ( $is_allowed_path ) {
            $attachments[] = $file_path;
        }
        @wp_mail( $to, $subject, $message, $headers, $attachments );
    }

    /**
     * Exclui um documento (arquivo .html) da pasta dps_docs. Também remove quaisquer
     * opções que referenciem este arquivo (documentos financeiros ou históricos).
     *
     * @param string $filename Nome do arquivo a ser removido
     */
    private static function delete_document( $filename ) {
        if ( ! $filename ) {
            return;
        }
        $uploads = wp_upload_dir();
        $doc_dir = trailingslashit( $uploads['basedir'] ) . 'dps_docs';
        $file_path = $doc_dir . '/' . basename( $filename );
        if ( file_exists( $file_path ) ) {
            @unlink( $file_path );
        }
        // Remover opções que apontam para este arquivo
        // Financeiro armazena URL em dps_fin_doc_{id} e base armazena nada específico, então busca geral
        // Verifica se alguma opção coincide com a URL
        $file_url = trailingslashit( $uploads['baseurl'] ) . 'dps_docs/' . basename( $filename );
        global $wpdb;
        // Busca opções que comecem com dps_fin_doc_
        $options = $wpdb->get_results( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'dps_fin_doc_%'" );
        if ( $options ) {
            foreach ( $options as $opt ) {
                $opt_val = get_option( $opt->option_name );
                if ( $opt_val === $file_url ) {
                    delete_option( $opt->option_name );
                }
            }
        }
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

        if ( ! isset( $_POST['dps_nonce_agendamentos'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dps_nonce_agendamentos'] ) ), 'dps_action' ) ) {
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
}
