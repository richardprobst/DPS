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
     * @return bool
     */
    private static function can_manage() {
        return current_user_can( 'manage_options' );
    }

    /**
     * Normaliza números de telefone para uso no WhatsApp.
     *
     * @param string $raw_phone Telefone original.
     *
     * @return string
     */
    private static function format_whatsapp_number( $raw_phone ) {
        $digits = preg_replace( '/\D+/', '', (string) $raw_phone );
        if ( strlen( $digits ) >= 10 && substr( $digits, 0, 2 ) !== '55' ) {
            $digits = '55' . $digits;
        }
        return $digits;
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

        $number = self::format_whatsapp_number( $client_phone );
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
            'Olá %s, tudo bem? O serviço do pet %s foi finalizado e o pagamento de R$ %s ainda está pendente. Para sua comodidade, você pode pagar via PIX celular 15 99160‑6299 ou utilizar o link: %s. Obrigado pela confiança!',
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
                        'Olá %s, tudo bem? Finalizamos os atendimentos dos pets %s em %s às %s. O valor total ficou em R$ %s. Você pode pagar via PIX celular 15 99160‑6299 ou utilizar o link: %s. Caso tenha dúvidas estamos à disposição!',
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
    private static function redirect_with_pending_notice( $client_id, $tab = 'agendas' ) {
        $redirect = self::get_redirect_url( $tab );
        $client_id = (int) $client_id;
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
            }
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
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Acesso negado.', 'desi-pet-shower' ) );
        }
        
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
        if ( ! isset( $_GET['dps_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_GET['dps_nonce'] ), 'dps_delete' ) ) {
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
        // Verifica se há visualização específica (detalhes do cliente)
        if ( isset( $_GET['dps_view'] ) && 'client' === $_GET['dps_view'] && isset( $_GET['id'] ) ) {
            $client_id = intval( $_GET['id'] );
            return self::render_client_page( $client_id );
        }
        
        $can_manage = self::can_manage();

        // Verifica se o usuário atual está logado e possui permissão para gerenciar o painel
        if ( ! is_user_logged_in() || ! $can_manage ) {
            $login_url = wp_login_url( get_permalink() );
            return '<p>' . esc_html__( 'Você precisa estar logado como administrador para acessar este painel.', 'desi-pet-shower' ) . ' <a href="' . esc_url( $login_url ) . '">' . esc_html__( 'Fazer login', 'desi-pet-shower' ) . '</a></p>';
        }
        
        // Sempre mostrar interface completa para usuários administradores
        ob_start();
        echo '<div class="dps-base-wrapper">';
        echo '<h1 class="dps-page-title">' . esc_html__( 'Painel de Gestão DPS', 'desi-pet-shower' ) . '</h1>';
        echo '<ul class="dps-nav">';
        echo '<li><a href="#" class="dps-tab-link" data-tab="agendas">' . esc_html__( 'Agendamentos', 'desi-pet-shower' ) . '</a></li>';
        echo '<li><a href="#" class="dps-tab-link" data-tab="clientes">' . esc_html__( 'Clientes', 'desi-pet-shower' ) . '</a></li>';
        echo '<li><a href="#" class="dps-tab-link" data-tab="pets">' . esc_html__( 'Pets', 'desi-pet-shower' ) . '</a></li>';
        // Permite que add-ons adicionem abas após os módulos principais
        do_action( 'dps_base_nav_tabs_after_pets', false );
        echo '<li><a href="#" class="dps-tab-link" data-tab="historico">' . esc_html__( 'Histórico', 'desi-pet-shower' ) . '</a></li>';
        // Espaço para add-ons exibirem abas após o histórico
        do_action( 'dps_base_nav_tabs_after_history', false );
        echo '</ul>';
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
                'O shortcode [dps_configuracoes] está deprecated e será removido em versões futuras. Use o menu admin "Desi Pet Shower".',
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
                <?php esc_html_e( 'Para acessar Backup, Comunicações, Notificações e outras configurações, utilize o menu "Desi Pet Shower" no painel admin.', 'desi-pet-shower' ); ?>
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
     * Seção de clientes: formulário e listagem
     */
    /**
     * Seção de clientes: formulário e listagem.
     * 
     * REFATORADO: Separa preparação de dados da renderização.
     * A lógica de dados permanece aqui, a renderização foi movida para template.
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
     * @return array Dados estruturados para o template.
     */
    private static function prepare_clients_section_data() {
        $clients = self::get_clients();
        
        // Detecta edição via parâmetros GET
        $edit_id = ( isset( $_GET['dps_edit'] ) && 'client' === $_GET['dps_edit'] && isset( $_GET['id'] ) ) 
                   ? intval( $_GET['id'] ) 
                   : 0;
        
        $editing = null;
        $meta    = [];
        
        if ( $edit_id ) {
            $editing = get_post( $edit_id );
            if ( $editing ) {
                // Carrega metadados do cliente para edição
                $meta = [
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
            }
        }
        
        return [
            'clients'  => $clients,
            'edit_id'  => $edit_id,
            'editing'  => $editing,
            'meta'     => $meta,
            'api_key'  => get_option( 'dps_google_api_key', '' ),
            'base_url' => get_permalink(),
        ];
    }
    
    /**
     * Renderiza a seção de clientes usando template.
     * 
     * @param array $data Dados preparados para renderização.
     * @return string HTML da seção.
     */
    private static function render_clients_section( $data ) {
        ob_start();
        dps_get_template( 'frontend/clients-section.php', $data );
        return ob_get_clean();
    }

    /**
     * Seção de pets: formulário e listagem
     */
    private static function section_pets() {
        $clients    = self::get_clients();
        $pets_page  = isset( $_GET['dps_pets_page'] ) ? max( 1, intval( $_GET['dps_pets_page'] ) ) : 1;
        $pets_query = self::get_pets( $pets_page );
        $pets       = $pets_query->posts;
        $pets_pages = (int) max( 1, $pets_query->max_num_pages );
        // Detecta edição
        $edit_id    = ( isset( $_GET['dps_edit'] ) && 'pet' === $_GET['dps_edit'] && isset( $_GET['id'] ) ) ? intval( $_GET['id'] ) : 0;
        $editing    = null;
        $meta       = [];
        if ( $edit_id ) {
            $editing = get_post( $edit_id );
            if ( $editing ) {
                $meta = [
                    'owner_id'  => get_post_meta( $edit_id, 'owner_id', true ),
                    'species'   => get_post_meta( $edit_id, 'pet_species', true ),
                    'breed'     => get_post_meta( $edit_id, 'pet_breed', true ),
                    'size'      => get_post_meta( $edit_id, 'pet_size', true ),
                    'weight'    => get_post_meta( $edit_id, 'pet_weight', true ),
                    'coat'      => get_post_meta( $edit_id, 'pet_coat', true ),
                    'color'     => get_post_meta( $edit_id, 'pet_color', true ),
                    'birth'     => get_post_meta( $edit_id, 'pet_birth', true ),
                    'sex'       => get_post_meta( $edit_id, 'pet_sex', true ),
                    'care'      => get_post_meta( $edit_id, 'pet_care', true ),
                    'aggressive'=> get_post_meta( $edit_id, 'pet_aggressive', true ),
                    // Campos adicionais de saúde, comportamento e foto para pets
                    'vaccinations' => get_post_meta( $edit_id, 'pet_vaccinations', true ),
                    'allergies'    => get_post_meta( $edit_id, 'pet_allergies', true ),
                    'behavior'     => get_post_meta( $edit_id, 'pet_behavior', true ),
                    'photo_id'     => get_post_meta( $edit_id, 'pet_photo_id', true ),
                ];
            }
        }
        // Prepara lista de raças para datalist (inclui várias raças de cães e gatos)
        $breeds = [
            'Affenpinscher', 'Afghan Hound', 'Airedale Terrier', 'Akita', 'Alaskan Malamute',
            'American Bulldog', 'Australian Shepherd', 'Basset Hound', 'Beagle', 'Belgian Malinois',
            'Bernese Mountain Dog', 'Bichon Frise', 'Border Collie', 'Boxer', 'Bulldog',
            'Bull Terrier', 'Cavalier King Charles Spaniel', 'Chihuahua', 'Chow Chow', 'Cocker Spaniel',
            'Collie', 'Dachshund', 'Dalmatian', 'Doberman Pinscher', 'French Bulldog',
            'German Shepherd', 'Golden Retriever', 'Great Dane', 'Greyhound', 'Jack Russell Terrier',
            'Labrador Retriever', 'Maltese', 'Miniature Pinscher', 'Newfoundland', 'Pomeranian',
            'Poodle', 'Portuguese Water Dog', 'Rottweiler', 'Samoyed', 'Schnauzer',
            'Shih Tzu', 'Siberian Husky', 'Staffordshire Bull Terrier', 'Weimaraner', 'Whippet',
            'Yorkshire Terrier', 'Siamese', 'Persian', 'Maine Coon', 'Ragdoll',
            'British Shorthair', 'Bengal', 'Abyssinian', 'Scottish Fold', 'Sphynx',
            'Birman', 'Oriental Shorthair', 'Russian Blue', 'Turkish Angora', 'Somali',
            'Burmese', 'Himalayan', 'Tonkinese', 'Munchkin', 'Chartreux',
            'Cornish Rex', 'Devon Rex', 'Norwegian Forest', 'Savannah', 'Selkirk Rex'
        ];
        ob_start();
        echo '<div class="dps-section" id="dps-section-pets">';
        echo '<h2 style="margin-bottom: 20px; color: #374151;">' . esc_html__( 'Cadastro de Pets', 'desi-pet-shower' ) . '</h2>';
        // Define enctype multipart/form-data para permitir upload de foto
        echo '<form method="post" enctype="multipart/form-data" class="dps-form dps-form--pet">';
        echo '<input type="hidden" name="dps_action" value="save_pet">';
        wp_nonce_field( 'dps_action', 'dps_nonce_pets' );
        if ( $edit_id ) {
            echo '<input type="hidden" name="pet_id" value="' . esc_attr( $edit_id ) . '">';
        }
        
        // Fieldset 1: Dados Básicos
        echo '<fieldset class="dps-fieldset">';
        echo '<legend class="dps-fieldset__legend">' . esc_html__( 'Dados Básicos', 'desi-pet-shower' ) . '</legend>';
        
        // Nome do pet e Cliente em grid
        echo '<div class="dps-form-row dps-form-row--2col">';
        $pet_name = $editing ? $editing->post_title : '';
        echo '<p class="dps-form-col"><label>' . esc_html__( 'Nome do Pet', 'desi-pet-shower' ) . ' <span class="dps-required">*</span><br><input type="text" name="pet_name" value="' . esc_attr( $pet_name ) . '" required></label></p>';
        
        // Cliente (tutor)
        $owner_selected = $meta['owner_id'] ?? '';
        echo '<p class="dps-form-col"><label>' . esc_html__( 'Cliente (Tutor)', 'desi-pet-shower' ) . ' <span class="dps-required">*</span><br><select name="owner_id" id="dps-pet-owner" required>';
        echo '<option value="">' . esc_html__( 'Selecione...', 'desi-pet-shower' ) . '</option>';
        foreach ( $clients as $client ) {
            $sel = (string) $client->ID === (string) $owner_selected ? 'selected' : '';
            echo '<option value="' . esc_attr( $client->ID ) . '" ' . $sel . '>' . esc_html( $client->post_title ) . '</option>';
        }
        echo '</select></label></p>';
        echo '</div>';
        
        // Espécie, Raça e Sexo em grid de 3 colunas
        echo '<div class="dps-form-row dps-form-row--3col">';
        // Espécie
        $species_val = $meta['species'] ?? '';
        echo '<p class="dps-form-col"><label>' . esc_html__( 'Espécie', 'desi-pet-shower' ) . ' <span class="dps-required">*</span><br><select name="pet_species" required>';
        $species_options = [
            ''      => __( 'Selecione...', 'desi-pet-shower' ),
            'cao'   => __( 'Cachorro', 'desi-pet-shower' ),
            'gato'  => __( 'Gato', 'desi-pet-shower' ),
            'outro' => __( 'Outro', 'desi-pet-shower' ),
        ];
        foreach ( $species_options as $val => $label ) {
            $sel = ( $species_val === $val ) ? 'selected' : '';
            echo '<option value="' . esc_attr( $val ) . '" ' . $sel . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></label></p>';
        
        // Raça (com datalist)
        $breed_val = $meta['breed'] ?? '';
        echo '<p class="dps-form-col"><label>' . esc_html__( 'Raça', 'desi-pet-shower' ) . '<br>';
        echo '<input type="text" name="pet_breed" list="dps-breed-list" value="' . esc_attr( $breed_val ) . '" placeholder="Digite ou selecione">';
        echo '</label></p>';
        
        // Sexo
        $sex_val = $meta['sex'] ?? '';
        echo '<p class="dps-form-col"><label>' . esc_html__( 'Sexo', 'desi-pet-shower' ) . ' <span class="dps-required">*</span><br><select name="pet_sex" required>';
        $sexes = [ '' => __( 'Selecione...', 'desi-pet-shower' ), 'macho' => __( 'Macho', 'desi-pet-shower' ), 'femea' => __( 'Fêmea', 'desi-pet-shower' ) ];
        foreach ( $sexes as $val => $lab ) {
            $sel = ( $sex_val === $val ) ? 'selected' : '';
            echo '<option value="' . esc_attr( $val ) . '" ' . $sel . '>' . esc_html( $lab ) . '</option>';
        }
        echo '</select></label></p>';
        echo '</div>';
        
        // Datalist definition (fora do fieldset mas após uso)
        echo '<datalist id="dps-breed-list">';
        foreach ( $breeds as $breed ) {
            echo '<option value="' . esc_attr( $breed ) . '">';
        }
        echo '</datalist>';
        
        echo '</fieldset>';
        
        // Fieldset 2: Características Físicas
        echo '<fieldset class="dps-fieldset">';
        echo '<legend class="dps-fieldset__legend">' . esc_html__( 'Características Físicas', 'desi-pet-shower' ) . '</legend>';
        
        // Tamanho, Peso e Data de Nascimento em grid
        echo '<div class="dps-form-row dps-form-row--3col">';
        $size_val = $meta['size'] ?? '';
        echo '<p class="dps-form-col"><label>' . esc_html__( 'Tamanho', 'desi-pet-shower' ) . ' <span class="dps-required">*</span><br><select name="pet_size" required>';
        $sizes = [ '' => __( 'Selecione...', 'desi-pet-shower' ), 'pequeno' => __( 'Pequeno', 'desi-pet-shower' ), 'medio' => __( 'Médio', 'desi-pet-shower' ), 'grande' => __( 'Grande', 'desi-pet-shower' ) ];
        foreach ( $sizes as $val => $lab ) {
            $sel = ( $size_val === $val ) ? 'selected' : '';
            echo '<option value="' . esc_attr( $val ) . '" ' . $sel . '>' . esc_html( $lab ) . '</option>';
        }
        echo '</select></label></p>';
        
        // Peso
        $weight_val = $meta['weight'] ?? '';
        echo '<p class="dps-form-col"><label>' . esc_html__( 'Peso (kg)', 'desi-pet-shower' ) . '<br><input type="number" step="0.1" min="0.1" max="100" name="pet_weight" value="' . esc_attr( $weight_val ) . '" placeholder="5.5"></label></p>';
        
        // Data de nascimento
        $birth_val = $meta['birth'] ?? '';
        echo '<p class="dps-form-col"><label>' . esc_html__( 'Data de nascimento', 'desi-pet-shower' ) . '<br><input type="date" name="pet_birth" value="' . esc_attr( $birth_val ) . '"></label></p>';
        echo '</div>';
        
        // Tipo de pelo e Cor em grid
        echo '<div class="dps-form-row dps-form-row--2col">';
        $coat_val = $meta['coat'] ?? '';
        echo '<p class="dps-form-col"><label>' . esc_html__( 'Tipo de pelo', 'desi-pet-shower' ) . '<br><input type="text" name="pet_coat" value="' . esc_attr( $coat_val ) . '" placeholder="Curto, longo, encaracolado..."></label></p>';
        
        $color_val = $meta['color'] ?? '';
        echo '<p class="dps-form-col"><label>' . esc_html__( 'Cor predominante', 'desi-pet-shower' ) . '<br><input type="text" name="pet_color" value="' . esc_attr( $color_val ) . '" placeholder="Branco, preto, caramelo..."></label></p>';
        echo '</div>';
        
        echo '</fieldset>';
        
        // Fieldset 3: Saúde e Comportamento
        echo '<fieldset class="dps-fieldset">';
        echo '<legend class="dps-fieldset__legend">' . esc_html__( 'Saúde e Comportamento', 'desi-pet-shower' ) . '</legend>';
        
        // Vacinas / Saúde
        $vaccinations_val = $meta['vaccinations'] ?? '';
        echo '<p><label>' . esc_html__( 'Vacinas / Saúde', 'desi-pet-shower' ) . '<br><textarea name="pet_vaccinations" rows="2" placeholder="Liste vacinas, condições médicas...">' . esc_textarea( $vaccinations_val ) . '</textarea></label></p>';
        
        // Alergias / Restrições
        $allergies_val = $meta['allergies'] ?? '';
        echo '<p><label>' . esc_html__( 'Alergias / Restrições', 'desi-pet-shower' ) . '<br><textarea name="pet_allergies" rows="2" placeholder="Alergias a alimentos, medicamentos...">' . esc_textarea( $allergies_val ) . '</textarea></label></p>';
        
        // Cuidados especiais/restrições
        $care_val = $meta['care'] ?? '';
        echo '<p><label>' . esc_html__( 'Cuidados especiais', 'desi-pet-shower' ) . '<br><textarea name="pet_care" rows="2" placeholder="Necessita cuidados especiais durante o banho?">' . esc_textarea( $care_val ) . '</textarea></label></p>';
        
        // Notas de Comportamento
        $behavior_val = $meta['behavior'] ?? '';
        echo '<p><label>' . esc_html__( 'Notas de comportamento', 'desi-pet-shower' ) . '<br><textarea name="pet_behavior" rows="2" placeholder="Como o pet costuma se comportar?">' . esc_textarea( $behavior_val ) . '</textarea></label></p>';
        
        // Agressivo (checkbox melhorado)
        $aggressive = $meta['aggressive'] ?? '';
        $checked_ag = $aggressive ? 'checked' : '';
        echo '<p><label class="dps-checkbox-label"><input type="checkbox" name="pet_aggressive" value="1" ' . $checked_ag . '> <span class="dps-checkbox-text">⚠️ ' . esc_html__( 'Cão agressivo (requer cuidado especial)', 'desi-pet-shower' ) . '</span></label></p>';
        
        echo '</fieldset>';
        
        // Fieldset 4: Foto do Pet
        echo '<fieldset class="dps-fieldset">';
        echo '<legend class="dps-fieldset__legend">' . esc_html__( 'Foto do Pet', 'desi-pet-shower' ) . '</legend>';
        
        $photo_id  = $meta['photo_id'] ?? '';
        $photo_url = '';
        if ( $photo_id ) {
            $photo_url = wp_get_attachment_image_url( $photo_id, 'thumbnail' );
        }
        
        echo '<div class="dps-file-upload">';
        echo '<label class="dps-file-upload__label">';
        echo '<input type="file" name="pet_photo" accept="image/*" class="dps-file-upload__input">';
        echo '<span class="dps-file-upload__text">📷 ' . esc_html__( 'Escolher foto', 'desi-pet-shower' ) . '</span>';
        echo '</label>';
        if ( $photo_url ) {
            echo '<div class="dps-file-upload__preview"><img src="' . esc_url( $photo_url ) . '" alt="' . esc_attr( $pet_name ) . '"></div>';
        }
        echo '</div>';
        
        echo '</fieldset>';
        
        // Botão
        $btn_text = $edit_id ? esc_html__( 'Atualizar Pet', 'desi-pet-shower' ) : esc_html__( 'Salvar Pet', 'desi-pet-shower' );
        echo '<p><button type="submit" class="button button-primary dps-submit-btn">' . $btn_text . '</button></p>';
        echo '</form>';
        // Listagem de pets
        echo '<h3 style="margin-top: 40px; padding-top: 24px; border-top: 1px solid #e5e7eb; color: #374151;">' . esc_html__( 'Pets Cadastrados', 'desi-pet-shower' ) . '</h3>';
        echo '<p><input type="text" class="dps-search" placeholder="' . esc_attr__( 'Buscar...', 'desi-pet-shower' ) . '"></p>';
        if ( ! empty( $pets ) ) {
            $base_url = get_permalink();
            echo '<table class="dps-table"><thead><tr><th>' . esc_html__( 'Nome', 'desi-pet-shower' ) . '</th><th>' . esc_html__( 'Cliente', 'desi-pet-shower' ) . '</th><th>' . esc_html__( 'Espécie', 'desi-pet-shower' ) . '</th><th>' . esc_html__( 'Raça', 'desi-pet-shower' ) . '</th><th>' . esc_html__( 'Ações', 'desi-pet-shower' ) . '</th></tr></thead><tbody>';
            foreach ( $pets as $pet ) {
                $owner_id = get_post_meta( $pet->ID, 'owner_id', true );
                $owner    = $owner_id ? get_post( $owner_id ) : null;
                $species  = get_post_meta( $pet->ID, 'pet_species', true );
                $breed    = get_post_meta( $pet->ID, 'pet_breed', true );
                $edit_url   = add_query_arg( [ 'tab' => 'pets', 'dps_edit' => 'pet', 'id' => $pet->ID ], $base_url );
                $delete_url = add_query_arg( [ 'tab' => 'pets', 'dps_delete' => 'pet', 'id' => $pet->ID ], $base_url );
                // Mapping species codes to labels
                $species_label = '';
                switch ( $species ) {
                    case 'cao':
                        $species_label = __( 'Cachorro', 'desi-pet-shower' );
                        break;
                    case 'gato':
                        $species_label = __( 'Gato', 'desi-pet-shower' );
                        break;
                    case 'outro':
                        $species_label = __( 'Outro', 'desi-pet-shower' );
                        break;
                    default:
                        $species_label = $species;
                        break;
                }
                // Link owner name to client page
                $owner_display = $owner ? '<a href="' . esc_url( add_query_arg( [ 'dps_view' => 'client', 'id' => $owner->ID ], $base_url ) ) . '">' . esc_html( $owner->post_title ) . '</a>' : '-';
                // Link to schedule service for this pet
                $schedule_url = add_query_arg( [ 'tab' => 'agendas', 'pref_client' => $owner_id, 'pref_pet' => $pet->ID ], $base_url );
                echo '<tr>';
                echo '<td>' . esc_html( $pet->post_title ) . '</td>';
                echo '<td>' . $owner_display . '</td>';
                echo '<td>' . esc_html( $species_label ) . '</td>';
                echo '<td>' . esc_html( $breed ) . '</td>';
                echo '<td><a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Editar', 'desi-pet-shower' ) . '</a> | <a href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Tem certeza de que deseja excluir?', 'desi-pet-shower' ) ) . '\');">' . esc_html__( 'Excluir', 'desi-pet-shower' ) . '</a> | <a href="' . esc_url( $schedule_url ) . '">' . esc_html__( 'Agendar', 'desi-pet-shower' ) . '</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            if ( $pets_pages > 1 ) {
                $pagination = paginate_links( [
                    'base'      => add_query_arg( 'dps_pets_page', '%#%' ),
                    'format'    => '',
                    'prev_text' => __( '&laquo; Anterior', 'desi-pet-shower' ),
                    'next_text' => __( 'Próxima &raquo;', 'desi-pet-shower' ),
                    'current'   => $pets_page,
                    'total'     => $pets_pages,
                ] );

                if ( $pagination ) {
                    echo '<div class="dps-pagination">' . $pagination . '</div>';
                }
            }
        } else {
            echo '<p>' . esc_html__( 'Nenhum pet cadastrado.', 'desi-pet-shower' ) . '</p>';
        }
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Seção de agendamentos: formulário e listagem
     */
    private static function section_agendas( $visitor_only = false ) {
        $clients    = self::get_clients();
        $pets_query = self::get_pets();
        $pets       = $pets_query->posts;
        $pet_pages  = (int) max( 1, $pets_query->max_num_pages );
        // Detecta edição de agendamento
        $edit_id    = ( isset( $_GET['dps_edit'] ) && 'appointment' === $_GET['dps_edit'] && isset( $_GET['id'] ) ) ? intval( $_GET['id'] ) : 0;
        $editing    = null;
        $meta       = [];
        if ( $edit_id ) {
            $editing = get_post( $edit_id );
            if ( $editing ) {
                $meta = [
                    'client_id' => get_post_meta( $edit_id, 'appointment_client_id', true ),
                    'pet_id'    => get_post_meta( $edit_id, 'appointment_pet_id', true ),
                    'date'      => get_post_meta( $edit_id, 'appointment_date', true ),
                    'time'      => get_post_meta( $edit_id, 'appointment_time', true ),
                    'notes'     => get_post_meta( $edit_id, 'appointment_notes', true ),
                    'appointment_type' => get_post_meta( $edit_id, 'appointment_type', true ),
                    'tosa'      => get_post_meta( $edit_id, 'appointment_tosa', true ),
                    // Recupera preço e ocorrência da tosa para pré-preenchimento (caso existam)
                    'tosa_price'     => get_post_meta( $edit_id, 'appointment_tosa_price', true ),
                    'tosa_occurrence' => get_post_meta( $edit_id, 'appointment_tosa_occurrence', true ),
                    'taxidog'   => get_post_meta( $edit_id, 'appointment_taxidog', true ),
                    'taxidog_price' => get_post_meta( $edit_id, 'appointment_taxidog_price', true ),
                    'extra_description' => get_post_meta( $edit_id, 'appointment_extra_description', true ),
                    'extra_value'       => get_post_meta( $edit_id, 'appointment_extra_value', true ),
                    'subscription_base_value'  => get_post_meta( $edit_id, 'subscription_base_value', true ),
                    'subscription_total_value' => get_post_meta( $edit_id, 'subscription_total_value', true ),
                    'subscription_extra_description' => get_post_meta( $edit_id, 'subscription_extra_description', true ),
                    'subscription_extra_value'       => get_post_meta( $edit_id, 'subscription_extra_value', true ),
                ];
            }
        }
        // Pré‑seleção de cliente e pet se não estiver editando
        $pref_client = isset( $_GET['pref_client'] ) ? intval( $_GET['pref_client'] ) : 0;
        $pref_pet    = isset( $_GET['pref_pet'] ) ? intval( $_GET['pref_pet'] ) : 0;
        ob_start();
        echo '<div class="dps-section" id="dps-section-agendas">';
        echo '<h2 style="margin-bottom: 20px; color: #374151;">' . esc_html__( 'Agendamento de Serviços', 'desi-pet-shower' ) . '</h2>';
        if ( isset( $_GET['dps_notice'] ) && 'pending_payments' === $_GET['dps_notice'] && ! $visitor_only ) {
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
        // Formulário de agendamento
        if ( ! $visitor_only ) {
            echo '<form method="post" class="dps-form">';
            echo '<input type="hidden" name="dps_action" value="save_appointment">';
            wp_nonce_field( 'dps_action', 'dps_nonce_agendamentos' );
            echo '<input type="hidden" name="dps_redirect_url" value="' . esc_attr( self::get_current_page_url() ) . '">';
            if ( $edit_id ) {
                echo '<input type="hidden" name="appointment_id" value="' . esc_attr( $edit_id ) . '">';
            }
            // FIELDSET 1: Tipo de Agendamento
            echo '<fieldset class="dps-fieldset">';
            echo '<legend class="dps-fieldset__legend">' . esc_html__( 'Tipo de Agendamento', 'desi-pet-shower' ) . '</legend>';
            
            // Campo: tipo de agendamento (simples ou assinatura)
            $appt_type = isset( $meta['appointment_type'] ) ? $meta['appointment_type'] : 'simple';
            echo '<div class="dps-radio-group">';
            echo '<label class="dps-radio-option">';
            echo '<input type="radio" name="appointment_type" value="simple" ' . checked( $appt_type, 'simple', false ) . checked( $appt_type, 'subscription', false ) . '>';
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
            echo '<input type="number" step="0.01" min="0" id="dps-tosa-price" name="appointment_tosa_price" value="' . esc_attr( $tosa_price_val ) . '" style="width:120px;">';
            // Ocorrência da tosa (selecionada via JS conforme frequência)
            echo '<label for="appointment_tosa_occurrence" style="margin-left:20px;">' . esc_html__( 'Ocorrência da tosa', 'desi-pet-shower' ) . '</label>';
            echo '<select name="appointment_tosa_occurrence" id="appointment_tosa_occurrence" data-current="' . esc_attr( $tosa_occ ) . '"></select>';
            echo '</div>';
            echo '</div>';

            // Campo: escolha de TaxiDog
            $taxidog = $meta['taxidog'] ?? '';
            echo '<label class="dps-checkbox-label">';
            echo '<input type="checkbox" id="dps-taxidog-toggle" name="appointment_taxidog" value="1" ' . checked( $taxidog, '1', false ) . '>';
            echo '<span class="dps-checkbox-text">';
            echo esc_html__( 'Solicitar TaxiDog?', 'desi-pet-shower' );
            echo ' <span class="dps-tooltip" data-tooltip="' . esc_attr__( 'Serviço de transporte do pet', 'desi-pet-shower' ) . '">ℹ️</span>';
            echo '</span>';
            echo '</label>';
            echo '<div id="dps-taxidog-extra" class="dps-conditional-field" style="display:' . ( $taxidog ? 'block' : 'none' ) . ';">';
            echo '<label for="dps-taxidog-price">' . esc_html__( 'Valor TaxiDog (R$)', 'desi-pet-shower' ) . '</label> ';
            echo '<input type="number" id="dps-taxidog-price" name="appointment_taxidog_price" step="0.01" min="0" value="' . esc_attr( $meta['taxidog_price'] ?? '' ) . '" style="width:120px;">';
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
            
            // FIELDSET 5: Observações
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
            echo '<h3>' . esc_html__( 'Resumo do agendamento', 'desi-pet-shower' ) . '</h3>';
            echo '<p class="dps-appointment-summary__empty">';
            echo esc_html__( 'Preencha cliente, pet, data e horário para ver o resumo aqui.', 'desi-pet-shower' );
            echo '</p>';
            echo '<ul class="dps-appointment-summary__list" hidden>';
            echo '<li><strong>' . esc_html__( 'Cliente:', 'desi-pet-shower' ) . '</strong> <span data-summary="client">-</span></li>';
            echo '<li><strong>' . esc_html__( 'Pets:', 'desi-pet-shower' ) . '</strong> <span data-summary="pets">-</span></li>';
            echo '<li><strong>' . esc_html__( 'Data:', 'desi-pet-shower' ) . '</strong> <span data-summary="date">-</span></li>';
            echo '<li><strong>' . esc_html__( 'Horário:', 'desi-pet-shower' ) . '</strong> <span data-summary="time">-</span></li>';
            echo '<li><strong>' . esc_html__( 'Serviços:', 'desi-pet-shower' ) . '</strong> <span data-summary="services">-</span></li>';
            echo '<li><strong>' . esc_html__( 'Valor estimado:', 'desi-pet-shower' ) . '</strong> <span data-summary="price">R$ 0,00</span></li>';
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
        }
        // Listagem de agendamentos organizados por status
        $args = [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'meta_value',
            'meta_key'       => 'appointment_date',
            'order'          => 'ASC',
        ];
        $appointments   = get_posts( $args );
        $base_url       = get_permalink();
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

        $history_data = self::get_history_appointments_data();
        $appointments = $history_data['appointments'];

        $clients = self::get_clients();
        $client_options = [];
        foreach ( $clients as $client ) {
            $client_options[ $client->ID ] = $client->post_title;
        }

        $status_filters = [
            'finalizado'      => __( 'Finalizado', 'desi-pet-shower' ),
            'finalizado_pago' => __( 'Finalizado e pago', 'desi-pet-shower' ),
            'cancelado'       => __( 'Cancelado', 'desi-pet-shower' ),
        ];

        $total_count  = $history_data['total_count'];
        $total_amount = $history_data['total_amount'];

        ob_start();
        echo '<div class="dps-section" id="dps-section-historico">';
        echo '<h2 style="margin-bottom: 20px; color: #374151;">' . esc_html__( 'Histórico de Atendimentos', 'desi-pet-shower' ) . '</h2>';
        echo '<p class="description">' . esc_html__( 'Visualize, filtre e exporte o histórico completo de atendimentos finalizados, pagos ou cancelados.', 'desi-pet-shower' ) . '</p>';

        echo '<div class="dps-history-toolbar">';
        echo '<div class="dps-history-filters">';
        echo '<div class="dps-history-filter"><label>' . esc_html__( 'Buscar', 'desi-pet-shower' ) . '<br><input type="search" id="dps-history-search" placeholder="' . esc_attr__( 'Filtrar por cliente, pet ou serviço...', 'desi-pet-shower' ) . '"></label></div>';
        echo '<div class="dps-history-filter"><label>' . esc_html__( 'Cliente', 'desi-pet-shower' ) . '<br><select id="dps-history-client"><option value="">' . esc_html__( 'Todos', 'desi-pet-shower' ) . '</option>';
        foreach ( $client_options as $id => $name ) {
            echo '<option value="' . esc_attr( $id ) . '">' . esc_html( $name ) . '</option>';
        }
        echo '</select></label></div>';
        echo '<div class="dps-history-filter"><label>' . esc_html__( 'Status', 'desi-pet-shower' ) . '<br><select id="dps-history-status"><option value="">' . esc_html__( 'Todos', 'desi-pet-shower' ) . '</option>';
        foreach ( $status_filters as $key => $label ) {
            echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>';
        }
        echo '</select></label></div>';
        echo '<div class="dps-history-filter"><label>' . esc_html__( 'Data inicial', 'desi-pet-shower' ) . '<br><input type="date" id="dps-history-start"></label></div>';
        echo '<div class="dps-history-filter"><label>' . esc_html__( 'Data final', 'desi-pet-shower' ) . '<br><input type="date" id="dps-history-end"></label></div>';
        echo '<div class="dps-history-filter"><label><input type="checkbox" id="dps-history-pending"> ' . esc_html__( 'Somente pendentes de pagamento', 'desi-pet-shower' ) . '</label></div>';
        echo '</div>';
        echo '<div class="dps-history-actions">';
        echo '<button type="button" class="button button-secondary" id="dps-history-clear">' . esc_html__( 'Limpar filtros', 'desi-pet-shower' ) . '</button> ';
        echo '<button type="button" class="button button-primary" id="dps-history-export">' . esc_html__( 'Exportar CSV', 'desi-pet-shower' ) . '</button>';
        echo '</div>';
        echo '</div>';

        $summary_value = number_format_i18n( $total_amount, 2 );
        echo '<div id="dps-history-summary" class="dps-history-summary" data-total-records="' . esc_attr( $total_count ) . '" data-total-value="' . esc_attr( $total_amount ) . '">';
        if ( $total_count ) {
            echo '<strong>' . sprintf( esc_html__( '%1$s atendimentos registrados. Receita acumulada: R$ %2$s.', 'desi-pet-shower' ), number_format_i18n( $total_count ), $summary_value ) . '</strong>';
        } else {
            echo '<strong>' . esc_html__( 'Nenhum atendimento registrado até o momento.', 'desi-pet-shower' ) . '</strong>';
        }
        echo '<p class="description">' . esc_html__( 'Os totais são atualizados automaticamente conforme os filtros são aplicados.', 'desi-pet-shower' ) . '</p>';
        echo '</div>';

        if ( $appointments ) {
            echo '<div class="dps-table-wrapper">';
            echo '<table class="dps-table" id="dps-history-table"><thead><tr>';
            echo '<th>' . esc_html__( 'Data', 'desi-pet-shower' ) . '</th>';
            echo '<th>' . esc_html__( 'Horário', 'desi-pet-shower' ) . '</th>';
            echo '<th>' . esc_html__( 'Cliente', 'desi-pet-shower' ) . '</th>';
            echo '<th>' . esc_html__( 'Pets', 'desi-pet-shower' ) . '</th>';
            echo '<th class="hide-mobile">' . esc_html__( 'Serviços', 'desi-pet-shower' ) . '</th>';
            echo '<th>' . esc_html__( 'Valor', 'desi-pet-shower' ) . '</th>';
            echo '<th>' . esc_html__( 'Status', 'desi-pet-shower' ) . '</th>';
            echo '<th class="hide-mobile">' . esc_html__( 'Cobrança', 'desi-pet-shower' ) . '</th>';
            echo '<th>' . esc_html__( 'Ações', 'desi-pet-shower' ) . '</th>';
            echo '</tr></thead><tbody>';
            $base_url        = get_permalink();
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
                $status_key  = strtolower( str_replace( ' ', '_', $status_meta ) );
                if ( 'finalizado_e_pago' === $status_key ) {
                    $status_key = 'finalizado_pago';
                }
                $status_label = self::get_status_label( $status_meta );
                $pet_display  = '-';
                $group_data   = self::get_multi_pet_charge_data( $appt->ID );
                if ( $group_data ) {
                    $pet_display = implode( ', ', $group_data['pet_names'] );
                } else {
                    $pet_id = get_post_meta( $appt->ID, 'appointment_pet_id', true );
                    if ( $pet_id && ! array_key_exists( $pet_id, $pets_cache ) ) {
                        $pets_cache[ $pet_id ] = get_post( $pet_id );
                    }
                    if ( $pet_id && isset( $pets_cache[ $pet_id ] ) ) {
                        $pet_display = $pets_cache[ $pet_id ]->post_title;
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
                echo '<tr data-date="' . esc_attr( $date_attr ) . '" data-status="' . esc_attr( $status_key ) . '" data-client="' . esc_attr( $client_id ) . '" data-total="' . esc_attr( $total_val ) . '" data-paid="' . esc_attr( $paid_flag ) . '">';
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
                $delete_url = add_query_arg( [ 'tab' => 'agendas', 'dps_delete' => 'appointment', 'id' => $appt->ID ], $base_url );
                echo '<td><a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Editar', 'desi-pet-shower' ) . '</a> | <a href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Tem certeza de que deseja excluir?', 'desi-pet-shower' ) ) . '\');">' . esc_html__( 'Excluir', 'desi-pet-shower' ) . '</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '</div>';
        } else {
            echo '<p>' . esc_html__( 'Nenhum atendimento finalizado foi encontrado.', 'desi-pet-shower' ) . '</p>';
        }

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
        if ( empty( $name ) ) {
            return;
        }
        $client_id = isset( $_POST['client_id'] ) ? intval( wp_unslash( $_POST['client_id'] ) ) : 0;
        if ( $client_id ) {
            // Atualiza
            wp_update_post( [
                'ID'         => $client_id,
                'post_title' => $name,
            ] );
        } else {
            $client_id = wp_insert_post( [
                'post_type'   => 'dps_cliente',
                'post_title'  => $name,
                'post_status' => 'publish',
            ] );
        }
        if ( $client_id ) {
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
        }
        // Adiciona mensagem de sucesso
        if ( $client_id ) {
            DPS_Message_Helper::add_success( __( 'Cliente salvo com sucesso!', 'desi-pet-shower' ) );
        }
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
        if ( empty( $owner_id ) || empty( $name ) ) {
            return;
        }
        $pet_id = isset( $_POST['pet_id'] ) ? intval( wp_unslash( $_POST['pet_id'] ) ) : 0;
        if ( $pet_id ) {
            // Update
            wp_update_post( [
                'ID'         => $pet_id,
                'post_title' => $name,
            ] );
        } else {
            $pet_id = wp_insert_post( [
                'post_type'   => 'dps_pet',
                'post_title'  => $name,
                'post_status' => 'publish',
            ] );
        }
        if ( $pet_id ) {
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
        }
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
     * Salva agendamento (inserção ou atualização)
     */
    private static function update_appointment_status() {
        if ( ! current_user_can( 'dps_manage_appointments' ) ) {
            wp_die( __( 'Acesso negado.', 'desi-pet-shower' ) );
        }
        $appt_id = isset( $_POST['appointment_id'] ) ? intval( wp_unslash( $_POST['appointment_id'] ) ) : 0;
        $status  = isset( $_POST['appointment_status'] ) ? sanitize_text_field( wp_unslash( $_POST['appointment_status'] ) ) : '';
        $valid   = [ 'pendente', 'finalizado', 'finalizado_pago', 'cancelado' ];
        if ( ! $appt_id || ! in_array( $status, $valid, true ) ) {
            return;
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

    private static function save_appointment() {
        if ( ! current_user_can( 'dps_manage_appointments' ) ) {
            wp_die( __( 'Acesso negado.', 'desi-pet-shower' ) );
        }
        $client_id = isset( $_POST['appointment_client_id'] ) ? intval( wp_unslash( $_POST['appointment_client_id'] ) ) : 0;
        // Recebe lista de pets (multi‑seleção). Pode ser array ou valor único.
        $raw_pets = isset( $_POST['appointment_pet_ids'] ) ? (array) wp_unslash( $_POST['appointment_pet_ids'] ) : [];
        $pet_ids  = [];
        foreach ( $raw_pets as $pid_raw ) {
            $pid = intval( $pid_raw );
            if ( $pid ) {
                $pet_ids[] = $pid;
            }
        }
        // Remove duplicados
        $pet_ids = array_values( array_unique( $pet_ids ) );

        // Define pet_id como o primeiro ID da lista para compatibilidade com lógica existente
        $pet_id = ! empty( $pet_ids ) ? $pet_ids[0] : 0;
        $date      = isset( $_POST['appointment_date'] ) ? sanitize_text_field( wp_unslash( $_POST['appointment_date'] ) ) : '';
        $time      = isset( $_POST['appointment_time'] ) ? sanitize_text_field( wp_unslash( $_POST['appointment_time'] ) ) : '';
        $notes     = isset( $_POST['appointment_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['appointment_notes'] ) ) : '';
        // Novo: tipo de agendamento (simple ou subscription)
        $appt_type = isset( $_POST['appointment_type'] ) ? sanitize_text_field( wp_unslash( $_POST['appointment_type'] ) ) : 'simple';
        // Frequência (apenas para assinaturas)
        $appt_freq = isset( $_POST['appointment_frequency'] ) ? sanitize_text_field( wp_unslash( $_POST['appointment_frequency'] ) ) : '';
        $tosa      = isset( $_POST['appointment_tosa'] ) ? '1' : '0';
        // Preço e ocorrência da tosa: somente relevantes para assinaturas, mas definimos aqui para ter valores padrão
        $tosa_price      = 0;
        if ( isset( $_POST['appointment_tosa_price'] ) ) {
            $tosa_price = floatval( str_replace( ',', '.', wp_unslash( $_POST['appointment_tosa_price'] ) ) );
            if ( $tosa_price < 0 ) {
                $tosa_price = 0;
            }
        }
        $tosa_occurrence = isset( $_POST['appointment_tosa_occurrence'] ) ? intval( wp_unslash( $_POST['appointment_tosa_occurrence'] ) ) : 1;
        $taxidog   = isset( $_POST['appointment_taxidog'] ) ? '1' : '0';
        // Valor do TaxiDog somente para agendamento simples; se vazio ou não numérico, trata como 0
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
        $subscription_base_value  = isset( $_POST['subscription_base_value'] ) ? floatval( str_replace( ',', '.', wp_unslash( $_POST['subscription_base_value'] ) ) ) : 0;
        $subscription_total_value = isset( $_POST['subscription_total_value'] ) ? floatval( str_replace( ',', '.', wp_unslash( $_POST['subscription_total_value'] ) ) ) : 0;
        $subscription_extra_description = isset( $_POST['subscription_extra_description'] ) ? sanitize_text_field( wp_unslash( $_POST['subscription_extra_description'] ) ) : '';
        $subscription_extra_value       = isset( $_POST['subscription_extra_value'] ) ? floatval( str_replace( ',', '.', wp_unslash( $_POST['subscription_extra_value'] ) ) ) : 0;
        if ( $subscription_extra_value < 0 ) {
            $subscription_extra_value = 0;
        }
        if ( empty( $client_id ) || empty( $pet_ids ) || empty( $date ) || empty( $time ) ) {
            return;
        }
        $appt_id = isset( $_POST['appointment_id'] ) ? intval( wp_unslash( $_POST['appointment_id'] ) ) : 0;

        /*
         * Caso seja uma nova assinatura e não esteja editando, cria uma assinatura para um ou mais pets.
         */
        if ( ! $appt_id && 'subscription' === $appt_type ) {
            // Define serviços padrão: Tosa higienica e Hidratação
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
                    $srv_id = $srv[0]->ID;
                    $service_ids[] = $srv_id;
                    $base_price    = (float) get_post_meta( $srv_id, 'service_price', true );
                    $prices[ $srv_id ] = $base_price;
                }
            }
            // Calcula preços de serviços base (valor de cada evento)
            $base_event_price = 0;
            foreach ( $prices as $p ) {
                $base_event_price += (float) $p;
            }
            // Define número de ocorrências no ciclo com base na frequência
            $count_events  = ( $appt_freq === 'quinzenal' ) ? 2 : 4;
            // Calcula valor do pacote por pet permitindo valores personalizados
            $base_cycle_value   = ( $subscription_base_value > 0 ) ? $subscription_base_value : ( $base_event_price * $count_events );
            $extra_cycle_value  = ( $subscription_extra_value > 0 ) ? $subscription_extra_value : 0;
            $package_per_pet    = $base_cycle_value + ( ( '1' === $tosa ) ? $tosa_price : 0 ) + $extra_cycle_value;
            $total_package      = $package_per_pet * count( $pet_ids );
            if ( $subscription_total_value > 0 ) {
                $total_package = $subscription_total_value;
                if ( count( $pet_ids ) > 0 ) {
                    $package_per_pet = $total_package / count( $pet_ids );
                }
            }
            // Cria post da assinatura
            $sub_id = wp_insert_post( [
                'post_type'   => 'dps_subscription',
                'post_title'  => $date . ' ' . $time . ' - ' . __( 'Assinatura', 'desi-pet-shower' ),
                'post_status' => 'publish',
            ] );
            if ( $sub_id ) {
                update_post_meta( $sub_id, 'subscription_client_id', $client_id );
                // Armazena o primeiro pet em subscription_pet_id para compatibilidade antiga
                update_post_meta( $sub_id, 'subscription_pet_id', $pet_ids[0] );
                // Novo: armazena lista de pets atendidos na assinatura
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
                // Salva informações de tosa na assinatura
                update_post_meta( $sub_id, 'subscription_tosa', $tosa );
                update_post_meta( $sub_id, 'subscription_tosa_price', $tosa_price );
                update_post_meta( $sub_id, 'subscription_tosa_occurrence', $tosa_occurrence );
                update_post_meta( $sub_id, 'subscription_start_date', $date );
                update_post_meta( $sub_id, 'subscription_start_time', $time );
                update_post_meta( $sub_id, 'subscription_payment_status', 'pendente' );
                // Define quantas ocorrências no período (2 para quinzenal, 4 para semanal)
                $interval_days = ( $appt_freq === 'quinzenal' ) ? 14 : 7;
                $count_events  = ( $appt_freq === 'quinzenal' ) ? 2 : 4;
                // Para cada pet e para cada ocorrência, cria agendamento
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
                            update_post_meta( $appt_new, 'appointment_client_id', $client_id );
                            update_post_meta( $appt_new, 'appointment_pet_id', $p_id_each );
                            update_post_meta( $appt_new, 'appointment_pet_ids', [ $p_id_each ] );
                            update_post_meta( $appt_new, 'appointment_date', $date_i );
                            update_post_meta( $appt_new, 'appointment_time', $time );
                            update_post_meta( $appt_new, 'appointment_notes', __( 'Serviço de assinatura', 'desi-pet-shower' ) );
                            update_post_meta( $appt_new, 'appointment_type', 'subscription' );
                            // Determina se este agendamento inclui tosa (somente uma vez por ciclo)
                            $is_tosa_event = ( '1' === $tosa && ( $i + 1 ) == $tosa_occurrence );
                            update_post_meta( $appt_new, 'appointment_tosa', $is_tosa_event ? '1' : '0' );
                            update_post_meta( $appt_new, 'appointment_tosa_price', $is_tosa_event ? $tosa_price : 0 );
                            update_post_meta( $appt_new, 'appointment_tosa_occurrence', $tosa_occurrence );
                            update_post_meta( $appt_new, 'appointment_taxidog', $taxidog );
                            update_post_meta( $appt_new, 'appointment_taxidog_price', 0 );
                            update_post_meta( $appt_new, 'appointment_services', $service_ids );
                            update_post_meta( $appt_new, 'appointment_service_prices', $prices );
                            // Define valor total individual: preço de serviços base + preço da tosa apenas na ocorrência definida
                            $total_single = $base_event_price + ( $is_tosa_event ? $tosa_price : 0 );
                            update_post_meta( $appt_new, 'appointment_total_value', $total_single );
                            update_post_meta( $appt_new, 'appointment_status', 'pendente' );
                            update_post_meta( $appt_new, 'subscription_id', $sub_id );
                            // Dispara gancho pós‑salvamento para cada agendamento
                            do_action( 'dps_base_after_save_appointment', $appt_new, 'subscription' );
                        }
                        $current_dt->modify( '+' . $interval_days . ' days' );
                    }
                }
                // Registra transação financeira única para a assinatura (valor total de todos os pets)
                global $wpdb;
                $table     = $wpdb->prefix . 'dps_transacoes';
                $status_fin = 'em_aberto';
                $desc_fin  = sprintf( __( 'Assinatura: %s (%s)', 'desi-pet-shower' ), 'Assinatura', ( $appt_freq ?: 'semanal' ) );
                $existing_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE plano_id = %d AND data = %s", $sub_id, $date ) );
                if ( $existing_id ) {
                    $wpdb->update( $table, [
                        'cliente_id' => $client_id ?: null,
                        'valor'      => (float) $total_package,
                        'status'     => $status_fin,
                        'categoria'  => __( 'Assinatura', 'desi-pet-shower' ),
                        'tipo'       => 'receita',
                        'descricao'  => $desc_fin,
                    ], [ 'id' => $existing_id ] );
                } else {
                    $wpdb->insert( $table, [
                        'cliente_id'     => $client_id ?: null,
                        'agendamento_id' => null,
                        'plano_id'       => $sub_id,
                        'data'           => $date,
                        'valor'          => (float) $total_package,
                        'categoria'      => __( 'Assinatura', 'desi-pet-shower' ),
                        'tipo'           => 'receita',
                        'status'         => $status_fin,
                        'descricao'      => $desc_fin,
                    ] );
                }
            }
            // Adiciona mensagem de sucesso
            DPS_Message_Helper::add_success( __( 'Agendamento de assinatura salvo com sucesso!', 'desi-pet-shower' ) );
            // Redireciona após salvar assinatura
            self::redirect_with_pending_notice( $client_id );
        }
        // Para agendamentos simples de múltiplos pets (novo), cria um agendamento para cada pet
        if ( ! $appt_id && 'simple' === $appt_type && count( $pet_ids ) > 1 ) {
            foreach ( $pet_ids as $p_id_each ) {
                $new_appt = wp_insert_post( [
                    'post_type'   => 'dps_agendamento',
                    'post_title'  => $date . ' ' . $time,
                    'post_status' => 'publish',
                ] );
                if ( $new_appt ) {
                    // Meta básicos
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
                    // Serviços e total são definidos pelo add‑on de serviços; recuperamos total postado
                    $posted_total = isset( $_POST['appointment_total'] ) ? floatval( str_replace( ',', '.', wp_unslash( $_POST['appointment_total'] ) ) ) : 0;
                    update_post_meta( $new_appt, 'appointment_total_value', $posted_total );
                    if ( '' !== $extra_description || $extra_value > 0 ) {
                        update_post_meta( $new_appt, 'appointment_extra_description', $extra_description );
                        update_post_meta( $new_appt, 'appointment_extra_value', $extra_value );
                    }
                    // Status inicial
                    update_post_meta( $new_appt, 'appointment_status', 'pendente' );
                    // Dispara gancho pós‑salvamento
                    do_action( 'dps_base_after_save_appointment', $new_appt, 'simple' );
                }
            }
            // Adiciona mensagem de sucesso
            DPS_Message_Helper::add_success( __( 'Agendamentos salvos com sucesso!', 'desi-pet-shower' ) );
            // Após criar todos os agendamentos, redireciona
            self::redirect_with_pending_notice( $client_id );
        }

        // Para agendamentos simples ou edição de qualquer tipo (único pet) continua com a lógica padrão
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

        if ( $appt_id ) {
            update_post_meta( $appt_id, 'appointment_client_id', $client_id );
            update_post_meta( $appt_id, 'appointment_pet_id', $pet_id );
            update_post_meta( $appt_id, 'appointment_date', $date );
            update_post_meta( $appt_id, 'appointment_time', $time );
            update_post_meta( $appt_id, 'appointment_notes', $notes );
            // Salva tipo e flags adicionais
            update_post_meta( $appt_id, 'appointment_type', $appt_type );
            update_post_meta( $appt_id, 'appointment_tosa', $tosa );
            update_post_meta( $appt_id, 'appointment_taxidog', $taxidog );
            if ( 'simple' === $appt_type ) {
                update_post_meta( $appt_id, 'appointment_taxidog_price', $taxi_price );
            } else {
                // Nas assinaturas, valor do TaxiDog é cortesia
                update_post_meta( $appt_id, 'appointment_taxidog_price', 0 );
            }
        }
        // Lógica específica para agendamentos do tipo assinatura: define serviços padrão e total
        if ( $appt_id ) {
            if ( 'subscription' === $appt_type ) {
                // Serviços padrão: Tosa higienica (nome pode variar em maiúsculas/minúsculas) e Hidratação
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
                        $srv_id = $srv[0]->ID;
                        $service_ids[] = $srv_id;
                        $base_price    = (float) get_post_meta( $srv_id, 'service_price', true );
                        $prices[ $srv_id ] = $base_price;
                    }
                }
                // Salva serviços selecionados e preços
                update_post_meta( $appt_id, 'appointment_services', $service_ids );
                update_post_meta( $appt_id, 'appointment_service_prices', $prices );
                // Define valor total: soma preços base dos serviços e adiciona o valor da tosa somente se marcada
                $base_total = 0;
                foreach ( $prices as $p ) {
                    $base_total += (float) $p;
                }
                $calculated_total = $base_total;
                if ( '1' === $tosa ) {
                    $calculated_total += $tosa_price;
                    // Registra preço da tosa para esta ocorrência
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
                // Vincula a assinatura se existir para este cliente/pet
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
            } else {
                // Agendamento simples: soma valor total dos serviços selecionados mais valor do TaxiDog
                // dps_service add-on salva appointment_total via POST; recupera
                $posted_total = isset( $_POST['appointment_total'] ) ? floatval( str_replace( ',', '.', wp_unslash( $_POST['appointment_total'] ) ) ) : 0;
                update_post_meta( $appt_id, 'appointment_total_value', $posted_total );
                if ( '' !== $extra_description || $extra_value > 0 ) {
                    update_post_meta( $appt_id, 'appointment_extra_description', $extra_description );
                    update_post_meta( $appt_id, 'appointment_extra_value', $extra_value );
                } else {
                    delete_post_meta( $appt_id, 'appointment_extra_description' );
                    delete_post_meta( $appt_id, 'appointment_extra_value' );
                }
            }
        }
        // Adiciona mensagem de sucesso
        DPS_Message_Helper::add_success( __( 'Agendamento salvo com sucesso!', 'desi-pet-shower' ) );
        // Redireciona para aba agendas
        self::redirect_with_pending_notice( $client_id );
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
        // Recebe dados
        $base_pass   = isset( $_POST['base_password'] ) ? sanitize_text_field( wp_unslash( $_POST['base_password'] ) ) : '';
        $agenda_pass = isset( $_POST['agenda_password'] ) ? sanitize_text_field( wp_unslash( $_POST['agenda_password'] ) ) : '';
        // Atualiza opções apenas se valores não vazios
        if ( $base_pass ) {
            update_option( 'dps_base_password', $base_pass );
        }
        if ( $agenda_pass ) {
            update_option( 'dps_agenda_password', $agenda_pass );
        }
        // Gancho para outras senhas de add‑ons (a função pode ser estendida)
        do_action( 'dps_base_save_passwords', wp_unslash( $_POST ) );
        // Redireciona para aba de senhas
        wp_safe_redirect( self::get_redirect_url( 'senhas' ) );
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
        echo '<h3>' . esc_html__( 'Acesso ao Desi Pet Shower', 'desi-pet-shower' ) . '</h3>';
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
     * Exibe a página de detalhes de um cliente e seu histórico de agendamentos
     * @param int $client_id ID do cliente
     * @return string HTML
     */
    private static function render_client_page( $client_id ) {
        $client = get_post( $client_id );
        if ( ! $client || $client->post_type !== 'dps_cliente' ) {
            return '<p>' . esc_html__( 'Cliente não encontrado.', 'desi-pet-shower' ) . '</p>';
        }

        // Mostra aviso se um histórico foi gerado e disponível para download
        if ( isset( $_GET['history_file'] ) ) {
            $file = sanitize_file_name( wp_unslash( $_GET['history_file'] ) );
            $uploads = wp_upload_dir();
            $url  = trailingslashit( $uploads['baseurl'] ) . 'dps_docs/' . $file;
            echo '<div class="notice notice-success" style="padding:10px;margin-bottom:15px;border:1px solid #d4edda;background:#d4edda;color:#155724;">' . sprintf( esc_html__( 'Histórico gerado com sucesso. %sClique aqui para abrir%s.', 'desi-pet-shower' ), '<a href="' . esc_url( $url ) . '" target="_blank" style="font-weight:bold;">', '</a>' ) . '</div>';
        }

        // Antes de montar a página, trata requisições de geração, envio e exclusão de documentos.
        // 1. Gerar histórico: dps_client_history=1 cria um documento HTML do histórico. Se send_email=1,
        // ele será enviado ao email especificado (parâmetro to_email) ou ao email cadastrado do cliente.
        if ( isset( $_GET['dps_client_history'] ) && '1' === $_GET['dps_client_history'] ) {
            $doc_url = self::generate_client_history_doc( $client_id );
            if ( $doc_url ) {
                // Envio por email se solicitado
                if ( isset( $_GET['send_email'] ) && '1' === $_GET['send_email'] ) {
                    $to_email = isset( $_GET['to_email'] ) && is_email( sanitize_email( $_GET['to_email'] ) ) ? sanitize_email( $_GET['to_email'] ) : '';
                    self::send_client_history_email( $client_id, $doc_url, $to_email );
                    // Redireciona de volta com indicador de sucesso
                    $redirect = add_query_arg( [ 'dps_view' => 'client', 'id' => $client_id, 'sent' => '1' ], remove_query_arg( [ 'dps_client_history', 'send_email', 'to_email', 'sent' ] ) );
                    wp_redirect( $redirect );
                    exit;
                }
                // Caso contrário, redireciona para a própria página com parâmetro history_file para exibir aviso
                $file_name = basename( $doc_url );
                $redirect  = add_query_arg( [ 'dps_view' => 'client', 'id' => $client_id, 'history_file' => $file_name ], remove_query_arg( [ 'dps_client_history', 'send_email', 'to_email', 'history_file' ] ) );
                wp_redirect( $redirect );
                exit;
            }
        }
        // 2. Exclusão de documentos: dps_delete_doc=1 com parâmetro file remove o arquivo específico.
        if ( isset( $_GET['dps_delete_doc'] ) && '1' === $_GET['dps_delete_doc'] && isset( $_GET['file'] ) ) {
            $file = sanitize_file_name( wp_unslash( $_GET['file'] ) );
            self::delete_document( $file );
            // Redireciona sem os parâmetros
            $redirect = add_query_arg( [ 'dps_view' => 'client', 'id' => $client_id ], remove_query_arg( [ 'dps_delete_doc', 'file' ] ) );
            wp_redirect( $redirect );
            exit;
        }
        // Obter metadados
        $meta = [
            'cpf'       => get_post_meta( $client_id, 'client_cpf', true ),
            'phone'     => get_post_meta( $client_id, 'client_phone', true ),
            'email'     => get_post_meta( $client_id, 'client_email', true ),
            'birth'     => get_post_meta( $client_id, 'client_birth', true ),
            'instagram' => get_post_meta( $client_id, 'client_instagram', true ),
            'facebook'  => get_post_meta( $client_id, 'client_facebook', true ),
            'photo_auth'=> get_post_meta( $client_id, 'client_photo_auth', true ),
            'address'   => get_post_meta( $client_id, 'client_address', true ),
            'referral'  => get_post_meta( $client_id, 'client_referral', true ),
        ];
        // Lista de pets deste cliente
        $pets = get_posts( [
            'post_type'      => 'dps_pet',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_key'       => 'owner_id',
            'meta_value'     => $client_id,
        ] );
        // Lista de agendamentos deste cliente, ordenado por data e hora
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
        $base_url = get_permalink();
        ob_start();
        // Exibe mensagem de sucesso se enviada via email
        if ( isset( $_GET['sent'] ) && '1' === $_GET['sent'] ) {
            echo '<div class="dps-notice" style="padding:10px;background:#dff0d8;border:1px solid #d6e9c6;margin-bottom:10px;">Histórico enviado por email com sucesso.</div>';
        }
        echo '<div class="dps-client-detail">';
        echo '<p><a href="' . esc_url( remove_query_arg( [ 'dps_view', 'id', 'tab' ] ) ) . '">' . esc_html__( '← Voltar', 'desi-pet-shower' ) . '</a></p>';
        echo '<h3>' . esc_html( $client->post_title ) . '</h3>';
        echo '<ul class="dps-client-info">';
        // CPF
        echo '<li><strong>' . esc_html__( 'CPF:', 'desi-pet-shower' ) . '</strong> ' . ( $meta['cpf'] ? esc_html( $meta['cpf'] ) : '-' ) . '</li>';
        // Telefone/WhatsApp
        if ( $meta['phone'] ) {
            $phone_digits = preg_replace( '/\D+/', '', $meta['phone'] );
            $wa_url       = 'https://wa.me/' . $phone_digits;
            echo '<li><strong>' . esc_html__( 'Telefone:', 'desi-pet-shower' ) . '</strong> <a href="' . esc_url( $wa_url ) . '" target="_blank">' . esc_html( $meta['phone'] ) . '</a></li>';
        } else {
            echo '<li><strong>' . esc_html__( 'Telefone:', 'desi-pet-shower' ) . '</strong> -</li>';
        }
        // Email
        echo '<li><strong>Email:</strong> ' . ( $meta['email'] ? esc_html( $meta['email'] ) : '-' ) . '</li>';
        // Data de nascimento
        if ( $meta['birth'] ) {
            $birth_fmt = date_i18n( 'd-m-Y', strtotime( $meta['birth'] ) );
            echo '<li><strong>' . esc_html__( 'Nascimento:', 'desi-pet-shower' ) . '</strong> ' . esc_html( $birth_fmt ) . '</li>';
        } else {
            echo '<li><strong>' . esc_html__( 'Nascimento:', 'desi-pet-shower' ) . '</strong> -</li>';
        }
        // Instagram
        echo '<li><strong>Instagram:</strong> ' . ( $meta['instagram'] ? esc_html( $meta['instagram'] ) : '-' ) . '</li>';
        // Facebook
        echo '<li><strong>Facebook:</strong> ' . ( $meta['facebook'] ? esc_html( $meta['facebook'] ) : '-' ) . '</li>';
        // Autorização de publicação nas redes sociais
        $photo_auth_val = $meta['photo_auth'];
        $photo_label    = '';
        if ( '' !== $photo_auth_val && null !== $photo_auth_val ) {
            $photo_label = $photo_auth_val ? __( 'Sim', 'desi-pet-shower' ) : __( 'Não', 'desi-pet-shower' );
        }
        echo '<li><strong>' . esc_html__( 'Autorização de publicação nas redes sociais:', 'desi-pet-shower' ) . '</strong> ' . esc_html( $photo_label !== '' ? $photo_label : '-' ) . '</li>';
        // Endereço completo
        echo '<li><strong>' . esc_html__( 'Endereço:', 'desi-pet-shower' ) . '</strong> ' . esc_html( $meta['address'] ? $meta['address'] : '-' ) . '</li>';
        // Como nos conheceu
        echo '<li><strong>' . esc_html__( 'Como nos conheceu:', 'desi-pet-shower' ) . '</strong> ' . esc_html( $meta['referral'] ? $meta['referral'] : '-' ) . '</li>';
        echo '</ul>';
        // Pets do cliente
        echo '<h4>' . esc_html__( 'Pets', 'desi-pet-shower' ) . '</h4>';
        if ( $pets ) {
            // Tabela de pets com detalhes
            echo '<table class="dps-table"><thead><tr>';
            echo '<th>' . esc_html__( 'Foto', 'desi-pet-shower' ) . '</th>';
            echo '<th>' . esc_html__( 'Nome', 'desi-pet-shower' ) . '</th>';
            echo '<th>' . esc_html__( 'Espécie', 'desi-pet-shower' ) . '</th>';
            echo '<th>' . esc_html__( 'Raça', 'desi-pet-shower' ) . '</th>';
            echo '<th>' . esc_html__( 'Porte', 'desi-pet-shower' ) . '</th>';
            echo '<th>' . esc_html__( 'Peso', 'desi-pet-shower' ) . '</th>';
            echo '<th>' . esc_html__( 'Pelagem', 'desi-pet-shower' ) . '</th>';
            echo '<th>' . esc_html__( 'Cor', 'desi-pet-shower' ) . '</th>';
            echo '<th>' . esc_html__( 'Nascimento', 'desi-pet-shower' ) . '</th>';
            echo '<th>' . esc_html__( 'Sexo', 'desi-pet-shower' ) . '</th>';
            echo '<th>' . esc_html__( 'Cuidados', 'desi-pet-shower' ) . '</th>';
            echo '<th>' . esc_html__( 'Agressivo', 'desi-pet-shower' ) . '</th>';
            echo '</tr></thead><tbody>';
            foreach ( $pets as $pet ) {
                // Foto do pet
                $photo_id  = get_post_meta( $pet->ID, 'pet_photo_id', true );
                $photo_html = '';
                if ( $photo_id ) {
                    // Obtém a URL da imagem em miniatura
                    $img_url = wp_get_attachment_image_url( $photo_id, 'thumbnail' );
                    if ( $img_url ) {
                        $photo_html = '<img src="' . esc_url( $img_url ) . '" alt="' . esc_attr( $pet->post_title ) . '" style="max-width:60px;height:auto;" />';
                    }
                }
                $species  = get_post_meta( $pet->ID, 'pet_species', true );
                $breed    = get_post_meta( $pet->ID, 'pet_breed', true );
                $size     = get_post_meta( $pet->ID, 'pet_size', true );
                $weight   = get_post_meta( $pet->ID, 'pet_weight', true );
                $coat     = get_post_meta( $pet->ID, 'pet_coat', true );
                $color    = get_post_meta( $pet->ID, 'pet_color', true );
                $birth    = get_post_meta( $pet->ID, 'pet_birth', true );
                $sex      = get_post_meta( $pet->ID, 'pet_sex', true );
                $care     = get_post_meta( $pet->ID, 'pet_care', true );
                $aggr     = get_post_meta( $pet->ID, 'pet_aggressive', true );
                // Translate codes
                switch ( $species ) {
                    case 'cao':
                        $species_label = __( 'Cachorro', 'desi-pet-shower' );
                        break;
                    case 'gato':
                        $species_label = __( 'Gato', 'desi-pet-shower' );
                        break;
                    case 'outro':
                        $species_label = __( 'Outro', 'desi-pet-shower' );
                        break;
                    default:
                        $species_label = $species;
                        break;
                }
                switch ( $size ) {
                    case 'pequeno':
                        $size_label = __( 'Pequeno', 'desi-pet-shower' );
                        break;
                    case 'medio':
                        $size_label = __( 'Médio', 'desi-pet-shower' );
                        break;
                    case 'grande':
                        $size_label = __( 'Grande', 'desi-pet-shower' );
                        break;
                    default:
                        $size_label = $size;
                        break;
                }
                switch ( $sex ) {
                    case 'macho':
                        $sex_label = __( 'Macho', 'desi-pet-shower' );
                        break;
                    case 'femea':
                        $sex_label = __( 'Fêmea', 'desi-pet-shower' );
                        break;
                    default:
                        $sex_label = $sex;
                        break;
                }
                $birth_formatted = $birth ? date_i18n( 'd-m-Y', strtotime( $birth ) ) : '';
                $aggr_label = $aggr ? __( 'Sim', 'desi-pet-shower' ) : __( 'Não', 'desi-pet-shower' );
                echo '<tr>';
                // Exibe foto ou marcador vazio
                echo '<td>' . ( $photo_html ? $photo_html : '-' ) . '</td>';
                echo '<td>' . esc_html( $pet->post_title ) . '</td>';
                echo '<td>' . esc_html( $species_label ) . '</td>';
                echo '<td>' . esc_html( $breed ) . '</td>';
                echo '<td>' . esc_html( $size_label ) . '</td>';
                echo '<td>' . esc_html( $weight ) . '</td>';
                echo '<td>' . esc_html( $coat ) . '</td>';
                echo '<td>' . esc_html( $color ) . '</td>';
                echo '<td>' . esc_html( $birth_formatted ) . '</td>';
                echo '<td>' . esc_html( $sex_label ) . '</td>';
                echo '<td>' . esc_html( $care ) . '</td>';
                echo '<td>' . esc_html( $aggr_label ) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>' . esc_html__( 'Nenhum pet cadastrado.', 'desi-pet-shower' ) . '</p>';
        }
        // Link para gerar PDF/relatório do histórico
        $history_link = add_query_arg( [ 'dps_view' => 'client', 'id' => $client_id, 'dps_client_history' => '1' ], remove_query_arg( [ 'dps_client_history', 'send_email', 'to_email' ] ) );
        $email_base   = add_query_arg( [ 'dps_view' => 'client', 'id' => $client_id, 'dps_client_history' => '1', 'send_email' => '1' ], remove_query_arg( [ 'dps_client_history', 'send_email', 'to_email' ] ) );
        echo '<p style="margin-top:15px;"><a href="' . esc_url( $history_link ) . '" class="button">' . esc_html__( 'Gerar histórico', 'desi-pet-shower' ) . '</a> ';
        // Botão de envio com prompt para email personalizado
        echo '<a href="#" class="button dps-send-history-email" data-base="' . esc_url( $email_base ) . '">' . esc_html__( 'Enviar histórico por email', 'desi-pet-shower' ) . '</a></p>';
        // Script para solicitar email e redirecionar
        echo '<script>(function($){$(document).on("click", ".dps-send-history-email", function(e){e.preventDefault();var base=$(this).data("base");var email=prompt("Para qual email deseja enviar? Deixe em branco para usar o email cadastrado.");if(email===null){return;}email=email.trim();var url=base; if(email){url += "&to_email=" + encodeURIComponent(email);} window.location.href=url;});})(jQuery);</script>';
        // Histórico de agendamentos
        echo '<h4>' . esc_html__( 'Histórico de Atendimentos', 'desi-pet-shower' ) . '</h4>';
        if ( $appointments ) {
            echo '<table class="dps-table"><thead><tr><th>' . esc_html__( 'Data', 'desi-pet-shower' ) . '</th><th>' . esc_html__( 'Horário', 'desi-pet-shower' ) . '</th><th>' . esc_html__( 'Pet', 'desi-pet-shower' ) . '</th><th>' . esc_html__( 'Pagamento', 'desi-pet-shower' ) . '</th><th>' . esc_html__( 'Observações', 'desi-pet-shower' ) . '</th></tr></thead><tbody>';
            foreach ( $appointments as $appt ) {
                $date  = get_post_meta( $appt->ID, 'appointment_date', true );
                $time  = get_post_meta( $appt->ID, 'appointment_time', true );
                $pet_id= get_post_meta( $appt->ID, 'appointment_pet_id', true );
                $pet   = $pet_id ? get_post( $pet_id ) : null;
                $notes = get_post_meta( $appt->ID, 'appointment_notes', true );
                $status_meta = get_post_meta( $appt->ID, 'appointment_status', true );
                // Determina status: pago ou pendente
                $status_label = '';
                if ( $status_meta === 'finalizado_pago' || $status_meta === 'finalizado e pago' ) {
                    $status_label = __( 'Pago', 'desi-pet-shower' );
                } elseif ( $status_meta === 'finalizado' ) {
                    $status_label = __( 'Pendente', 'desi-pet-shower' );
                } elseif ( $status_meta === 'cancelado' ) {
                    $status_label = __( 'Cancelado', 'desi-pet-shower' );
                } else {
                    // default
                    $status_label = __( 'Pendente', 'desi-pet-shower' );
                }
                $date_fmt = $date ? date_i18n( 'd-m-Y', strtotime( $date ) ) : '';
                echo '<tr>';
                echo '<td>' . esc_html( $date_fmt ) . '</td>';
                echo '<td>' . esc_html( $time ) . '</td>';
                echo '<td>' . esc_html( $pet ? $pet->post_title : '-' ) . '</td>';
                echo '<td>' . esc_html( $status_label ) . '</td>';
                echo '<td>' . esc_html( $notes ) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>' . esc_html__( 'Nenhum atendimento encontrado.', 'desi-pet-shower' ) . '</p>';
        }
        echo '</div>';
        return ob_get_clean();
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
        // Salva arquivo
        file_put_contents( $filepath, $html );
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
        if ( file_exists( $file_path ) ) {
            $body_html = file_get_contents( $file_path );
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
        // Anexa arquivo HTML
        $attachments = [];
        if ( file_exists( $file_path ) ) {
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