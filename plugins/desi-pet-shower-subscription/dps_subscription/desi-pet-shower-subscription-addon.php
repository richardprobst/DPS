<?php
/**
 * Arquivo principal do add-on de Assinaturas.
 * 
 * Este arquivo contém a implementação completa do add-on de Assinaturas.
 * É incluído pelo arquivo wrapper 'desi-pet-shower-subscription.php' na raiz do add-on.
 * 
 * IMPORTANTE: Este arquivo NÃO deve ter cabeçalho de plugin WordPress para
 * evitar que o add-on apareça duplicado na lista de plugins.
 * O arquivo de plugin principal é 'desi-pet-shower-subscription.php'.
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DPS_Subscription_Addon {
    public function __construct() {
        // Registrar CPT para assinaturas
        add_action( 'init', [ $this, 'register_subscription_cpt' ] );
        // Registrar abas e seções no plugin base
        add_action( 'dps_base_nav_tabs_after_pets', [ $this, 'add_subscriptions_tab' ], 20, 1 );
        add_action( 'dps_base_sections_after_pets', [ $this, 'add_subscriptions_section' ], 20, 1 );
        // Manipular salvamento, exclusão e renovação
        add_action( 'init', [ $this, 'maybe_handle_subscription_request' ] );

        // Enfileirar assets (CSS e JS) no front-end e admin
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        // Após tratar requisições de assinatura, sincroniza a assinatura com o
        // módulo financeiro. Esta ação é adicionada com prioridade 20 para
        // garantir que a assinatura já tenha sido salva/atualizada quando a
        // transação financeira for criada ou atualizada.
        add_action( 'init', [ $this, 'maybe_sync_finance_on_save' ], 20 );

        // Exemplo de integração com gateway de pagamento: qualquer add-on de
        // pagamentos pode disparar esta ação para atualizar o status do ciclo
        // da assinatura. O terceiro parâmetro aceita valores como "paid" ou
        // "failed". O segundo parâmetro deve conter a string do ciclo no
        // formato Y-m (ex.: 2025-11). Quando omitido, o ciclo atual é
        // inferido pela data de início da assinatura.
        add_action( 'dps_subscription_payment_status', [ $this, 'handle_subscription_payment_status' ], 10, 3 );

        // Integrações com Financeiro: quando uma assinatura for salva ou atualizada,
        // cria ou atualiza a transação correspondente na tabela dps_transacoes. Se
        // o status de pagamento mudar ou a assinatura for renovada, o registro
        // financeiro será atualizado para refletir o novo status. As exclusões
        // também removem qualquer transação associada.

        // Este add-on também utiliza o pagamento automático: ao término de uma
        // assinatura, é possível gerar uma cobrança de renovação via WhatsApp.
    }

    /**
     * Enfileira CSS e JS do add-on de assinaturas.
     * Carrega apenas quando necessário (aba de assinaturas ativa).
     */
    public function enqueue_assets() {
        // Obtém o diretório do add-on (usa __DIR__ em vez de dirname(__FILE__) para modernidade)
        $addon_url = plugin_dir_url( __DIR__ );
        $version   = '1.2.1';

        // CSS - carrega sempre que disponível (leve e necessário para tabelas)
        wp_enqueue_style(
            'dps-subscription-addon',
            $addon_url . 'assets/css/subscription-addon.css',
            [],
            $version
        );

        // JS - carrega para funcionalidade de filtro de pets
        wp_enqueue_script(
            'dps-subscription-addon',
            $addon_url . 'assets/js/subscription-addon.js',
            [ 'jquery' ],
            $version,
            true
        );
    }

    /**
     * Gera (ou recupera) um link de pagamento para a renovação da assinatura.
     * Utiliza o Access Token configurado no add-on de pagamentos e cria uma
     * preferência no Mercado Pago. O link é salvo no meta da assinatura para
     * evitar chamadas repetidas.
     *
     * @param int $sub_id ID da assinatura
     * @param float $amount Valor a cobrar
     * @return string URL de pagamento
     */
    private function get_subscription_payment_link( $sub_id, $amount ) {
        $link = get_post_meta( $sub_id, 'dps_subscription_payment_link', true );
        if ( ! empty( $link ) ) {
            return $link;
        }
        $token = trim( get_option( 'dps_mercadopago_access_token' ) );
        if ( ! $token || ! $amount ) {
            // Retorna link padrão caso não haja token ou valor
            return 'https://link.mercadopago.com.br/desipetshower';
        }
        $description = 'Renovação assinatura ID ' . $sub_id;
        // Monta payload conforme a API de preferências do Mercado Pago
        $body = [
            'items' => [
                [
                    'title'       => sanitize_text_field( $description ),
                    'quantity'    => 1,
                    'unit_price'  => (float) $amount,
                    'currency_id' => 'BRL',
                ],
            ],
            // Define uma referência externa previsível para rastreamento do pagamento.
            // Usamos apenas o ID da assinatura para que o webhook possa localizar a
            // assinatura correspondente sem depender de timestamps. O plugin de
            // pagamentos interpreta este formato para atualizar o status.
            'external_reference' => 'dps_subscription_' . $sub_id,
        ];
        $args = [
            'body'    => wp_json_encode( $body ),
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'timeout' => 20,
        ];
        $response = wp_remote_post( 'https://api.mercadopago.com/checkout/preferences', $args );
        if ( is_wp_error( $response ) ) {
            return 'https://link.mercadopago.com.br/desipetshower';
        }
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( isset( $data['init_point'] ) ) {
            $link = esc_url_raw( $data['init_point'] );
            update_post_meta( $sub_id, 'dps_subscription_payment_link', $link );
            return $link;
        }
        return 'https://link.mercadopago.com.br/desipetshower';
    }

    /**
     * Cria a mensagem de cobrança para a renovação da assinatura.
     *
     * @param WP_Post $sub Objeto da assinatura
     * @param string $payment_link Link de pagamento a ser usado
     * @return string Mensagem formatada
     */
    private function build_subscription_whatsapp_message( $sub, $payment_link ) {
        $cid   = get_post_meta( $sub->ID, 'subscription_client_id', true );
        $pid   = get_post_meta( $sub->ID, 'subscription_pet_id', true );
        $price = get_post_meta( $sub->ID, 'subscription_price', true );
        $client_post = $cid ? get_post( $cid ) : null;
        $pet_post    = $pid ? get_post( $pid ) : null;
        $client_name = $client_post ? $client_post->post_title : '';
        $pet_name    = $pet_post ? $pet_post->post_title : '';
        $valor_fmt   = $price ? number_format( (float) $price, 2, ',', '.' ) : '';
        // Obtém a chave PIX configurada ou utiliza padrão. Reaproveita a opção do módulo de pagamentos
        $pix_option  = get_option( 'dps_pix_key', '' );
        $pix_display = $pix_option ? $pix_option : '15 99160‑6299';
        // Mensagem padrão para renovação da assinatura
        $msg = sprintf(
            'Olá %s! A assinatura do pet %s foi concluída. O valor da renovação de R$ %s está pendente. Você pode pagar via PIX (%s) ou pelo link: %s. Obrigado!',
            $client_name,
            $pet_name,
            $valor_fmt,
            $pix_display,
            $payment_link
        );
        // Permite customização via filtro
        return apply_filters( 'dps_subscription_whatsapp_message', $msg, $sub, $payment_link );
    }

    /**
     * Retorna a chave de ciclo (Y-m) com base em uma data informada ou na data atual.
     *
     * @param string $date_start Data inicial do ciclo (Y-m-d).
     * @return string Chave do ciclo no formato Y-m.
     */
    private function get_cycle_key( $date_start = '' ) {
        try {
            $timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
            $dt       = $date_start ? new DateTime( $date_start, $timezone ) : new DateTime( 'now', $timezone );
        } catch ( Exception $e ) {
            return '';
        }
        return $dt->format( 'Y-m' );
    }

    /**
     * Verifica se o ciclo já foi processado para a assinatura.
     *
     * @param int    $sub_id    ID da assinatura.
     * @param string $cycle_key Ciclo no formato Y-m.
     * @return bool
     */
    private function has_generated_cycle( $sub_id, $cycle_key ) {
        if ( ! $cycle_key ) {
            return false;
        }
        $meta_key = 'dps_generated_cycle_' . $cycle_key;
        return (bool) get_post_meta( $sub_id, $meta_key, true );
    }

    /**
     * Marca o ciclo como gerado, evitando recriação futura.
     *
     * @param int    $sub_id    ID da assinatura.
     * @param string $cycle_key Ciclo no formato Y-m.
     */
    private function mark_cycle_generated( $sub_id, $cycle_key ) {
        if ( ! $cycle_key ) {
            return;
        }
        $meta_key = 'dps_generated_cycle_' . $cycle_key;
        update_post_meta( $sub_id, $meta_key, 1 );
    }

    /**
     * Atualiza o status de pagamento de um ciclo.
     *
     * @param int    $sub_id    ID da assinatura.
     * @param string $cycle_key Ciclo no formato Y-m.
     * @param string $status    Status a registrar (pago|em_atraso|pendente).
     */
    private function mark_cycle_status( $sub_id, $cycle_key, $status ) {
        if ( ! $cycle_key ) {
            return;
        }
        $meta_key = 'dps_cycle_status_' . $cycle_key;
        update_post_meta( $sub_id, $meta_key, sanitize_text_field( $status ) );
    }

    /**
     * Registra o tipo de post personalizado para assinaturas
     */
    public function register_subscription_cpt() {
        $labels = [
            'name'          => __( 'Assinaturas', 'dps-subscription-addon' ),
            'singular_name' => __( 'Assinatura', 'dps-subscription-addon' ),
        ];
        $args = [
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => false,
            'supports'           => [ 'title' ],
            'hierarchical'       => false,
        ];
        register_post_type( 'dps_subscription', $args );
    }

    /**
     * Adiciona uma nova aba de navegação ao plugin base
     *
     * @param bool $visitor_only Se verdadeiro, visitante não vê a aba
     */
    public function add_subscriptions_tab( $visitor_only ) {
        if ( $visitor_only ) {
            return;
        }
        echo '<li><a href="#" class="dps-tab-link" data-tab="assinaturas">' . esc_html__( 'Assinaturas', 'dps-subscription-addon' ) . '</a></li>';
    }

    /**
     * Adiciona a seção de assinaturas ao plugin base
     *
     * @param bool $visitor_only Se verdadeiro, visitante não vê a seção
     */
    public function add_subscriptions_section( $visitor_only ) {
        if ( $visitor_only ) {
            return;
        }
        echo $this->section_subscriptions();
    }

    /**
     * Verifica se o usuário atual pode gerenciar assinaturas.
     * Aceita usuários com manage_options OU qualquer capability DPS específica.
     *
     * @return bool
     */
    private function can_manage_subscriptions() {
        return current_user_can( 'manage_options' ) 
            || current_user_can( 'dps_manage_clients' ) 
            || current_user_can( 'dps_manage_pets' ) 
            || current_user_can( 'dps_manage_appointments' );
    }

    /**
     * Processa formulários e ações relacionados às assinaturas.
     * Todas as ações requerem verificação de nonce e capability.
     */
    public function maybe_handle_subscription_request() {
        // Verificar se usuário pode gerenciar assinaturas
        if ( ! $this->can_manage_subscriptions() ) {
            return;
        }

        // Salvar ou editar assinatura
        if ( isset( $_POST['dps_subscription_action'] ) && check_admin_referer( 'dps_subscription_action', 'dps_subscription_nonce' ) ) {
            $this->save_subscription();
        }

        // Cancelar assinatura: move para lixeira sem excluir transações
        if ( isset( $_GET['dps_cancel'] ) && 'subscription' === $_GET['dps_cancel'] && isset( $_GET['id'] ) ) {
            $sub_id = intval( $_GET['id'] );
            // Verificar nonce para proteção CSRF
            if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'dps_cancel_subscription_' . $sub_id ) ) {
                wp_die( esc_html__( 'Ação não autorizada. Link expirado ou inválido.', 'dps-subscription-addon' ) );
            }
            if ( $sub_id ) {
                wp_trash_post( $sub_id );
            }
            $base_url = get_permalink();
            $redirect_url = remove_query_arg( [ 'dps_cancel', 'id', '_wpnonce' ], $base_url );
            $redirect_url = add_query_arg( [ 'tab' => 'assinaturas' ], $redirect_url );
            wp_redirect( $redirect_url );
            exit;
        }

        // Restaurar assinatura cancelada
        if ( isset( $_GET['dps_restore'] ) && 'subscription' === $_GET['dps_restore'] && isset( $_GET['id'] ) ) {
            $sub_id = intval( $_GET['id'] );
            // Verificar nonce para proteção CSRF
            if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'dps_restore_subscription_' . $sub_id ) ) {
                wp_die( esc_html__( 'Ação não autorizada. Link expirado ou inválido.', 'dps-subscription-addon' ) );
            }
            if ( $sub_id ) {
                wp_untrash_post( $sub_id );
            }
            $base_url = get_permalink();
            $redirect_url = remove_query_arg( [ 'dps_restore', 'id', '_wpnonce' ], $base_url );
            $redirect_url = add_query_arg( [ 'tab' => 'assinaturas' ], $redirect_url );
            wp_redirect( $redirect_url );
            exit;
        }

        // Excluir assinatura via GET (permanente)
        if ( isset( $_GET['dps_delete'] ) && 'subscription' === $_GET['dps_delete'] && isset( $_GET['id'] ) ) {
            $sub_id = intval( $_GET['id'] );
            // Verificar nonce para proteção CSRF
            if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'dps_delete_subscription_' . $sub_id ) ) {
                wp_die( esc_html__( 'Ação não autorizada. Link expirado ou inválido.', 'dps-subscription-addon' ) );
            }
            // Exclui permanentemente
            wp_delete_post( $sub_id, true );
            // Remove quaisquer transações financeiras associadas a esta assinatura
            $this->delete_finance_records( $sub_id );
            $base_url     = get_permalink();
            $redirect_url = remove_query_arg( [ 'dps_delete', 'id', '_wpnonce' ], $base_url );
            $redirect_url = add_query_arg( [ 'tab' => 'assinaturas' ], $redirect_url );
            wp_redirect( $redirect_url );
            exit;
        }

        // Renovar assinatura
        if ( isset( $_GET['dps_renew'] ) && isset( $_GET['id'] ) ) {
            $sub_id = intval( $_GET['id'] );
            // Verificar nonce para proteção CSRF
            if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'dps_renew_subscription_' . $sub_id ) ) {
                wp_die( esc_html__( 'Ação não autorizada. Link expirado ou inválido.', 'dps-subscription-addon' ) );
            }
            $this->renew_subscription( $sub_id );
            $base_url = get_permalink();
            // Remove o parâmetro de renovação para evitar redirecionamentos em loop
            $redirect_url = remove_query_arg( [ 'dps_renew', 'id', '_wpnonce' ], $base_url );
            $redirect_url = add_query_arg( [ 'tab' => 'assinaturas' ], $redirect_url );
            wp_redirect( $redirect_url );
            exit;
        }

        // Excluir todos os agendamentos vinculados a uma assinatura
        if ( isset( $_GET['dps_delete_appts'] ) && isset( $_GET['id'] ) ) {
            $sub_id = intval( $_GET['id'] );
            // Verificar nonce para proteção CSRF
            if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'dps_delete_appts_subscription_' . $sub_id ) ) {
                wp_die( esc_html__( 'Ação não autorizada. Link expirado ou inválido.', 'dps-subscription-addon' ) );
            }
            $this->delete_all_appointments( $sub_id );
            $base_url = get_permalink();
            $redirect_url = remove_query_arg( [ 'dps_delete_appts', 'id', '_wpnonce' ], $base_url );
            $redirect_url = add_query_arg( [ 'tab' => 'assinaturas' ], $redirect_url );
            wp_redirect( $redirect_url );
            exit;
        }

        // Atualizar status de pagamento
        if ( isset( $_POST['dps_update_payment'] ) && isset( $_POST['subscription_id'] ) ) {
            $sub_id = intval( $_POST['subscription_id'] );
            // Verificar nonce para proteção CSRF
            if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'dps_update_payment_' . $sub_id ) ) {
                wp_die( esc_html__( 'Ação não autorizada.', 'dps-subscription-addon' ) );
            }
            $status = sanitize_text_field( $_POST['payment_status'] );
            update_post_meta( $sub_id, 'subscription_payment_status', $status );
            // Se há primeira consulta deste ciclo, atualiza status do primeiro agendamento correspondente
            $this->update_first_appointment_status( $sub_id, $status );
            // Atualiza o status da transação financeira relacionada
            $cycle_key = $this->get_cycle_key( get_post_meta( $sub_id, 'subscription_start_date', true ) );
            $this->mark_cycle_status( $sub_id, $cycle_key, $status );
            $this->create_or_update_finance_record( $sub_id, $cycle_key );
            // Redireciona sem parametros
            $base_url = get_permalink();
            wp_redirect( add_query_arg( [ 'tab' => 'assinaturas' ], $base_url ) );
            exit;
        }
    }

    /**
     * Salva ou atualiza uma assinatura
     */
    private function save_subscription() {
        $client_id    = intval( $_POST['subscription_client_id'] ?? 0 );
        $pet_id       = intval( $_POST['subscription_pet_id'] ?? 0 );
        $service      = sanitize_text_field( $_POST['subscription_service'] ?? '' );
        $frequency    = sanitize_text_field( $_POST['subscription_frequency'] ?? '' );
        $price        = floatval( str_replace( ',', '.', $_POST['subscription_price'] ?? '0' ) );
        $start_date   = sanitize_text_field( $_POST['subscription_start_date'] ?? '' );
        $start_time   = sanitize_text_field( $_POST['subscription_start_time'] ?? '' );
        $notes        = isset( $_POST['subscription_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['subscription_notes'] ) ) : '';
        $assignee     = isset( $_POST['subscription_assignee'] ) ? sanitize_text_field( wp_unslash( $_POST['subscription_assignee'] ) ) : '';
        $extras_desc  = isset( $_POST['subscription_extras_descriptions'] ) ? (array) wp_unslash( $_POST['subscription_extras_descriptions'] ) : [];
        $extras_val   = isset( $_POST['subscription_extras_values'] ) ? (array) wp_unslash( $_POST['subscription_extras_values'] ) : [];
        $extras_list  = $this->parse_extras_from_request( $extras_desc, $extras_val );
        if ( ! $client_id || ! $pet_id || ! $service || ! $frequency || ! $start_date || ! $start_time ) {
            return;
        }
        $sub_id = isset( $_POST['subscription_id'] ) ? intval( $_POST['subscription_id'] ) : 0;
        $title  = $start_date . ' ' . $start_time . ' - ' . $service;
        if ( $sub_id ) {
            wp_update_post( [
                'ID'         => $sub_id,
                'post_title' => $title,
            ] );
        } else {
            $sub_id = wp_insert_post( [
                'post_type'   => 'dps_subscription',
                'post_title'  => $title,
                'post_status' => 'publish',
            ] );
        }
        if ( ! $sub_id ) {
            return;
        }
        update_post_meta( $sub_id, 'subscription_client_id', $client_id );
        update_post_meta( $sub_id, 'subscription_pet_id', $pet_id );
        update_post_meta( $sub_id, 'subscription_service', $service );
        update_post_meta( $sub_id, 'subscription_frequency', $frequency );
        update_post_meta( $sub_id, 'subscription_price', $price );
        update_post_meta( $sub_id, 'subscription_start_date', $start_date );
        update_post_meta( $sub_id, 'subscription_start_time', $start_time );
        if ( $notes ) {
            update_post_meta( $sub_id, 'subscription_notes', $notes );
        } else {
            delete_post_meta( $sub_id, 'subscription_notes' );
        }
        if ( $assignee ) {
            update_post_meta( $sub_id, 'subscription_assignee', $assignee );
        } else {
            delete_post_meta( $sub_id, 'subscription_assignee' );
        }
        if ( ! empty( $extras_list ) ) {
            update_post_meta( $sub_id, 'subscription_extras_list', $extras_list );
            update_post_meta( $sub_id, 'subscription_extra_description', $extras_list[0]['description'] );
            update_post_meta( $sub_id, 'subscription_extra_value', $extras_list[0]['value'] );
        } else {
            delete_post_meta( $sub_id, 'subscription_extras_list' );
            delete_post_meta( $sub_id, 'subscription_extra_description' );
            delete_post_meta( $sub_id, 'subscription_extra_value' );
        }
        // Marca pagamento como pendente por padrão se não definido
        $pay_status = get_post_meta( $sub_id, 'subscription_payment_status', true );
        if ( ! $pay_status ) {
            update_post_meta( $sub_id, 'subscription_payment_status', 'pendente' );
        }
        // Define o ciclo corrente a partir da data de início
        $cycle_key = $this->get_cycle_key( $start_date );

        // Gera agendamentos apenas quando o ciclo ainda não foi criado
        $this->generate_monthly_appointments( $sub_id, $start_date, $cycle_key );

        // Sincroniza com o financeiro: cria ou atualiza o registro de transação
        // da assinatura no módulo financeiro. Isso garante que cada assinatura
        // possua uma entrada na tabela dps_transacoes correspondente ao ciclo
        // vigente (data de início) e ao valor do pacote. O status da transação
        // refletirá o campo subscription_payment_status.
        $this->create_or_update_finance_record( $sub_id, $cycle_key );
    }

    /**
     * Gera os agendamentos para uma assinatura para o mês da data inicial
     * @param int    $sub_id     ID da assinatura
     * @param string $date_start Data inicial (Y-m-d)
     * @param string $cycle_key  Ciclo (Y-m) calculado previamente
     */
    private function generate_monthly_appointments( $sub_id, $date_start, $cycle_key = '' ) {
        $cycle_key = $cycle_key ? $cycle_key : $this->get_cycle_key( $date_start );
        if ( ! $cycle_key || $this->has_generated_cycle( $sub_id, $cycle_key ) ) {
            return;
        }
        $pet_id    = get_post_meta( $sub_id, 'subscription_pet_id', true );
        $client_id = get_post_meta( $sub_id, 'subscription_client_id', true );
        $frequency = get_post_meta( $sub_id, 'subscription_frequency', true );
        $service   = get_post_meta( $sub_id, 'subscription_service', true );
        $start_time= get_post_meta( $sub_id, 'subscription_start_time', true );
        $notes     = get_post_meta( $sub_id, 'subscription_notes', true );
        $extras    = get_post_meta( $sub_id, 'subscription_extras_list', true );
        if ( ! is_array( $extras ) || empty( $extras ) ) {
            $legacy_desc = get_post_meta( $sub_id, 'subscription_extra_description', true );
            $legacy_val  = get_post_meta( $sub_id, 'subscription_extra_value', true );
            if ( '' !== $legacy_desc || '' !== $legacy_val ) {
                $extras = [
                    [
                        'description' => sanitize_text_field( $legacy_desc ),
                        'value'       => floatval( $legacy_val ),
                    ],
                ];
            } else {
                $extras = [];
            }
        }
        if ( ! $pet_id || ! $client_id || ! $frequency || ! $date_start ) {
            return;
        }
        // Parse date
        try {
            $start_dt = new DateTime( $date_start );
        } catch ( Exception $e ) {
            return;
        }
        $year  = intval( $start_dt->format( 'Y' ) );
        $month = intval( $start_dt->format( 'm' ) );
        // Determine increment in days
        $interval_days = ( $frequency === 'quinzenal' ) ? 14 : 7;
        /*
         * A assinatura gera um número fixo de atendimentos por ciclo: 4 para semanal e 2 para quinzenal.
         * Em vez de limitar os agendamentos ao mês de início, geramos a quantidade necessária de datas
         * a partir da data inicial, mesmo que atravesse meses. Isso atende ao requisito de sempre ter
         * quatro ou duas consultas conforme a frequência escolhida.
         */
        $dates   = [];
        $count   = ( $frequency === 'quinzenal' ) ? 2 : 4;
        $current_dt = clone $start_dt;
        for ( $i = 0; $i < $count; $i++ ) {
            $dates[]   = $current_dt->format( 'Y-m-d' );
            $current_dt->modify( '+' . $interval_days . ' days' );
        }
        // Remove agendamentos já criados para o mesmo ciclo para evitar duplicidade
        $existing = get_posts( [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [ 'key' => 'subscription_id', 'value' => $sub_id, 'compare' => '=' ],
                [ 'key' => 'subscription_cycle', 'value' => $cycle_key, 'compare' => '=' ],
            ],
        ] );
        foreach ( $existing as $appt ) {
            wp_delete_post( $appt->ID, true );
        }
        // Cria novos agendamentos com as datas calculadas
        foreach ( $dates as $date ) {
            $appt_id = wp_insert_post( [
                'post_type'   => 'dps_agendamento',
                'post_title'  => $date . ' ' . $start_time,
                'post_status' => 'publish',
            ] );
            if ( $appt_id ) {
                update_post_meta( $appt_id, 'appointment_client_id', $client_id );
                update_post_meta( $appt_id, 'appointment_pet_id', $pet_id );
                update_post_meta( $appt_id, 'appointment_date', $date );
                update_post_meta( $appt_id, 'appointment_time', $start_time );
                update_post_meta( $appt_id, 'appointment_services', [] );
                update_post_meta( $appt_id, 'appointment_service_prices', [] );
                update_post_meta( $appt_id, 'appointment_total_value', 0 );
                update_post_meta( $appt_id, 'appointment_status', 'pendente' );
                update_post_meta( $appt_id, 'subscription_id', $sub_id );
                update_post_meta( $appt_id, 'subscription_cycle', $cycle_key );
                // Indica que o agendamento pertence a um pacote de assinatura
                $appt_note = __( 'Serviço de assinatura', 'dps-subscription-addon' );
                if ( $notes ) {
                    $appt_note .= ' — ' . $notes;
                }
                update_post_meta( $appt_id, 'appointment_notes', $appt_note );
                if ( ! empty( $extras ) ) {
                    update_post_meta( $appt_id, 'subscription_extras_list', $extras );
                    update_post_meta( $appt_id, 'subscription_extra_description', $extras[0]['description'] );
                    update_post_meta( $appt_id, 'subscription_extra_value', $extras[0]['value'] );
                }
            }
        }
        $this->mark_cycle_generated( $sub_id, $cycle_key );
        $this->mark_cycle_status( $sub_id, $cycle_key, 'pendente' );
    }

    /**
     * Normaliza serviços extras enviados pelo formulário.
     *
     * @param array $descriptions Lista de descrições.
     * @param array $values       Lista de valores (strings ou floats).
     * @return array Lista sanitizada de extras.
     */
    private function parse_extras_from_request( $descriptions, $values ) {
        $extras = [];
        if ( ! is_array( $descriptions ) || ! is_array( $values ) ) {
            return $extras;
        }
        foreach ( $descriptions as $idx => $raw_desc ) {
            $desc  = sanitize_text_field( $raw_desc );
            $value = 0;
            if ( isset( $values[ $idx ] ) ) {
                $value = floatval( str_replace( ',', '.', $values[ $idx ] ) );
            }
            if ( '' !== $desc || $value > 0 ) {
                $extras[] = [
                    'description' => $desc,
                    'value'       => max( 0, $value ),
                ];
            }
        }
        return $extras;
    }

    /**
     * Atualiza o status do primeiro agendamento do ciclo quando o pagamento é alterado
     *
     * @param int    $sub_id ID da assinatura
     * @param string $payment_status novo status de pagamento ('pago' ou 'pendente')
     */
    private function update_first_appointment_status( $sub_id, $payment_status ) {
        // Encontra o agendamento mais antigo associado à assinatura (no mês atual)
        $first_appt = get_posts( [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'meta_key'       => 'appointment_date',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'meta_query'     => [
                [ 'key' => 'subscription_id', 'value' => $sub_id, 'compare' => '=' ],
            ],
        ] );
        if ( $first_appt ) {
            $appt = $first_appt[0];
            if ( 'pago' === $payment_status ) {
                // Marca status de agendamento como finalizado e pago se já estiver finalizado, senão apenas pago
                update_post_meta( $appt->ID, 'appointment_status', 'finalizado_pago' );
            } else {
                // Marca como finalizado (pendente) somente se estiver pago previamente
                $current_status = get_post_meta( $appt->ID, 'appointment_status', true );
                if ( $current_status === 'finalizado_pago' ) {
                    update_post_meta( $appt->ID, 'appointment_status', 'finalizado' );
                }
            }
        }
    }

    /**
     * Cria ou atualiza um registro na tabela financeira para a assinatura
     * fornecida. Cada ciclo de assinatura (definido pela data de início)
     * corresponde a uma transação no módulo financeiro. Se uma transação
     * já existir para o mesmo plano e data, ela será atualizada; caso
     * contrário, um novo registro será inserido. O status financeiro será
     * "pago" quando o campo subscription_payment_status estiver definido
     * como "pago"; "em_atraso" quando o pagamento falhar e "em_aberto" para
     * estados pendentes.
     *
     * @param int    $sub_id    ID da assinatura
     * @param string $cycle_key Ciclo (Y-m) relacionado à transação
     */
    private function create_or_update_finance_record( $sub_id, $cycle_key = '' ) {
        if ( ! $sub_id ) {
            return;
        }
        // Recupera dados da assinatura
        $client_id  = get_post_meta( $sub_id, 'subscription_client_id', true );
        $price      = get_post_meta( $sub_id, 'subscription_price', true );
        $start_date = get_post_meta( $sub_id, 'subscription_start_date', true );
        $pay_status = get_post_meta( $sub_id, 'subscription_payment_status', true );
        $service    = get_post_meta( $sub_id, 'subscription_service', true );
        $freq       = get_post_meta( $sub_id, 'subscription_frequency', true );
        if ( ! $start_date || '' === $start_date ) {
            return;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';
        // Prepara valores
        $date_db   = $cycle_key ? $cycle_key . '-01' : $start_date; // armazenamos somente a data (YYYY-mm-dd)
        $status_map = [
            'pago'      => 'pago',
            'em_atraso' => 'em_atraso',
            'pendente'  => 'em_aberto',
        ];
        $status    = $status_map[ $pay_status ] ?? 'em_aberto';
        $category  = __( 'Assinatura', 'dps-subscription-addon' );
        $type      = 'receita';
        $desc      = sprintf( __( 'Assinatura: %s (%s)', 'dps-subscription-addon' ), $service, $freq );
        // Verifica se já existe transação para este plano e data
        $existing_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE plano_id = %d AND data = %s", $sub_id, $date_db ) );
        if ( $existing_id ) {
            // Atualiza valores principais e status
            $wpdb->update( $table, [
                'cliente_id' => $client_id ?: null,
                'valor'      => (float) $price,
                'status'     => $status,
                'categoria'  => $category,
                'tipo'       => $type,
                'descricao'  => $desc,
            ], [ 'id' => $existing_id ] );
        } else {
            // Insere nova transação
            $wpdb->insert( $table, [
                'cliente_id'     => $client_id ?: null,
                'agendamento_id' => null,
                'plano_id'       => $sub_id,
                'data'           => $date_db,
                'valor'          => (float) $price,
                'categoria'      => $category,
                'tipo'           => $type,
                'status'         => $status,
                'descricao'      => $desc,
            ] );
        }
    }

    /**
     * Integração fictícia de status de pagamento proveniente de um gateway externo.
     * Use a ação dps_subscription_payment_status para informar o resultado do pagamento
     * do ciclo atual. Exemplo de uso: do_action( 'dps_subscription_payment_status', $sub_id, '2025-11', 'paid' );
     *
     * @param int    $sub_id         ID da assinatura.
     * @param string $cycle_key      Ciclo (Y-m). Se vazio, usa o ciclo da data de início.
     * @param string $payment_status Status recebido do gateway (paid|failed|pending).
     */
    public function handle_subscription_payment_status( $sub_id, $cycle_key = '', $payment_status = '' ) {
        $sub_id = intval( $sub_id );
        if ( ! $sub_id ) {
            return;
        }
        $start_date = get_post_meta( $sub_id, 'subscription_start_date', true );
        $cycle_key  = $cycle_key ? $cycle_key : $this->get_cycle_key( $start_date );
        if ( ! $cycle_key ) {
            return;
        }

        $normalized = strtolower( sanitize_text_field( $payment_status ) );
        if ( in_array( $normalized, [ 'paid', 'approved', 'success' ], true ) ) {
            update_post_meta( $sub_id, 'subscription_payment_status', 'pago' );
            $this->mark_cycle_status( $sub_id, $cycle_key, 'pago' );
            $this->update_first_appointment_status( $sub_id, 'pago' );
        } elseif ( in_array( $normalized, [ 'failed', 'rejected', 'refused' ], true ) ) {
            update_post_meta( $sub_id, 'subscription_payment_status', 'em_atraso' );
            $this->mark_cycle_status( $sub_id, $cycle_key, 'em_atraso' );
        } else {
            update_post_meta( $sub_id, 'subscription_payment_status', 'pendente' );
            $this->mark_cycle_status( $sub_id, $cycle_key, 'pendente' );
        }

        $this->create_or_update_finance_record( $sub_id, $cycle_key );
    }

    /**
     * Remove todos os registros financeiros relacionados a uma assinatura
     * específica. Deve ser utilizado ao excluir uma assinatura.
     *
     * @param int $sub_id ID da assinatura
     */
    private function delete_finance_records( $sub_id ) {
        if ( ! $sub_id ) {
            return;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';
        // Obtém todas as transações para este plano e remove também opções de documento
        $trans_rows = $wpdb->get_results( $wpdb->prepare( "SELECT id FROM $table WHERE plano_id = %d", $sub_id ) );
        if ( $trans_rows ) {
            foreach ( $trans_rows as $row ) {
                // Apaga arquivo de documento associado, se existir
                $opt_key = 'dps_fin_doc_' . $row->id;
                $doc_url = get_option( $opt_key );
                if ( $doc_url ) {
                    delete_option( $opt_key );
                    // Remove arquivo físico
                    $file_path = str_replace( home_url( '/' ), ABSPATH, $doc_url );
                    if ( file_exists( $file_path ) ) {
                        @unlink( $file_path );
                    }
                }
            }
        }
        // Exclui as transações do banco
        $wpdb->delete( $table, [ 'plano_id' => $sub_id ] );
    }

    /**
     * Sincroniza financeiramente após o salvamento de assinatura via init. Esta
     * função é executada com prioridade 20 após a maioria das operações do
     * ciclo de vida da assinatura, mas actua apenas quando foi disparada uma
     * ação de salvar assinatura. Ela verifica a presença de parâmetros no
     * request e chama a criação/atualização da transação. Se a assinatura
     * estiver sendo renovada ou o status de pagamento for alterado, outras
     * funções já cuidam da atualização.
     */
    public function maybe_sync_finance_on_save() {
        // Se estivermos salvando ou atualizando uma assinatura
        if ( isset( $_POST['dps_subscription_action'] ) && check_admin_referer( 'dps_subscription_action', 'dps_subscription_nonce' ) ) {
            $sub_id = isset( $_POST['subscription_id'] ) ? intval( $_POST['subscription_id'] ) : 0;
            // Durante a inserção $sub_id ainda não existirá. Como o save_subscription
            // chama create_or_update_finance_record ao final, não precisamos
            // duplicar a chamada aqui. Esta função permanece para compatibilidade.
            return;
        }
    }

    /**
     * Exclui todos os agendamentos associados a uma assinatura
     *
     * @param int $sub_id ID da assinatura
     */
    private function delete_all_appointments( $sub_id ) {
        if ( ! $sub_id ) {
            return;
        }
        $appointments = get_posts( [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [ 'key' => 'subscription_id', 'value' => $sub_id, 'compare' => '=' ],
            ],
        ] );
        foreach ( $appointments as $appt ) {
            wp_delete_post( $appt->ID, true );
        }
    }

    /**
     * Renova uma assinatura gerando agendamentos para o próximo mês
     *
     * @param int $sub_id ID da assinatura
     */
    private function renew_subscription( $sub_id ) {
        $start_date = get_post_meta( $sub_id, 'subscription_start_date', true );
        $start_time = get_post_meta( $sub_id, 'subscription_start_time', true );
        if ( ! $start_date || ! $start_time ) {
            return;
        }
        try {
            $dt = new DateTime( $start_date );
        } catch ( Exception $e ) {
            return;
        }
        // Avança um mês para a data inicial da próxima renovação, mantendo o dia
        $dt->modify( 'first day of next month' );
        // Ajusta a data para o mesmo dia da semana e proximidade
        $orig_day_of_week = date( 'N', strtotime( $start_date ) );
        // Move dt to same weekday as original day
        while ( intval( $dt->format( 'N' ) ) != $orig_day_of_week ) {
            $dt->modify( '+1 day' );
        }
        $new_date_start = $dt->format( 'Y-m-d' );
        // Atualiza meta de início para novo ciclo
        update_post_meta( $sub_id, 'subscription_start_date', $new_date_start );
        // Reinicia status de pagamento para pendente
        update_post_meta( $sub_id, 'subscription_payment_status', 'pendente' );
        // Gera os novos agendamentos para o novo mês
        $cycle_key = $this->get_cycle_key( $new_date_start );
        $this->generate_monthly_appointments( $sub_id, $new_date_start, $cycle_key );

        // Cria novo registro financeiro para o novo ciclo. Como o
        // subscription_start_date foi atualizado, este método inserirá uma
        // nova transação para a data atualizada.
        $this->create_or_update_finance_record( $sub_id, $cycle_key );
    }

    /**
     * Renderiza a seção de assinaturas: listagem e formulário de edição
     * 
     * O formulário de criação de nova assinatura foi removido pois a criação
     * de assinaturas é feita na aba Agendamentos, selecionando o tipo "Assinatura".
     * Esta aba agora foca apenas na gestão e acompanhamento das assinaturas existentes.
     */
    private function section_subscriptions() {
        // Detecta se estamos em modo de edição
        $edit_id = ( isset( $_GET['dps_edit'] ) && 'subscription' === $_GET['dps_edit'] && isset( $_GET['id'] ) ) ? intval( $_GET['id'] ) : 0;
        $meta    = [];
        if ( $edit_id ) {
            $meta = [
                'client_id'  => get_post_meta( $edit_id, 'subscription_client_id', true ),
                'pet_id'     => get_post_meta( $edit_id, 'subscription_pet_id', true ),
                'service'    => get_post_meta( $edit_id, 'subscription_service', true ),
                'frequency'  => get_post_meta( $edit_id, 'subscription_frequency', true ),
                'price'      => get_post_meta( $edit_id, 'subscription_price', true ),
                'start_date' => get_post_meta( $edit_id, 'subscription_start_date', true ),
                'start_time' => get_post_meta( $edit_id, 'subscription_start_time', true ),
                'notes'      => get_post_meta( $edit_id, 'subscription_notes', true ),
                'assignee'   => get_post_meta( $edit_id, 'subscription_assignee', true ),
                'extras'     => get_post_meta( $edit_id, 'subscription_extras_list', true ),
            ];
        }
        // Opções de frequência
        $freq_options = [
            'semanal'   => __( 'Semanal', 'dps-subscription-addon' ),
            'quinzenal' => __( 'Quinzenal', 'dps-subscription-addon' ),
        ];
        // Recupera assinaturas ativas e canceladas
        $active_subs = get_posts( [
            'post_type'      => 'dps_subscription',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );
        $canceled_subs = get_posts( [
            'post_type'      => 'dps_subscription',
            'posts_per_page' => -1,
            'post_status'    => 'trash',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );
        $base_url = get_permalink();
        $list_url = add_query_arg( [ 'tab' => 'assinaturas' ], $base_url );
        ob_start();
        
        echo '<div class="dps-section dps-subscription-wrapper" id="dps-section-assinaturas">';
        
        // Header padronizado como nas outras abas
        echo '<h2 class="dps-section-title">';
        echo '<span class="dps-section-title__icon">📋</span>';
        echo esc_html__( 'Gestão de Assinaturas', 'dps-subscription-addon' );
        echo '</h2>';
        
        // Layout empilhado verticalmente (como na aba Serviços)
        echo '<div class="dps-subscriptions-stacked">';
        
        // Exibe formulário APENAS se estiver editando
        if ( $edit_id ) {
            $this->render_subscription_edit_form( $edit_id, $meta, $freq_options, $list_url );
        } else {
            // Calcular métricas
            $total_active    = count( $active_subs );
            $total_canceled  = count( $canceled_subs );
            $pending_payment = 0;
            $monthly_revenue = 0;
            
            foreach ( $active_subs as $sub ) {
                $pay_status = get_post_meta( $sub->ID, 'subscription_payment_status', true );
                $price      = (float) get_post_meta( $sub->ID, 'subscription_price', true );
                
                if ( 'pendente' === $pay_status || 'em_atraso' === $pay_status ) {
                    $pending_payment++;
                }
                $monthly_revenue += $price;
            }
            
            // Card de Informações (padrão das outras abas)
            echo '<div class="dps-surface dps-surface--info dps-subscription-status-card">';
            echo '<div class="dps-surface__title">';
            echo '<span>📊</span>';
            echo esc_html__( 'Informações', 'dps-subscription-addon' );
            echo '</div>';
            
            // Painel de estatísticas
            echo '<ul class="dps-inline-stats dps-inline-stats--panel">';
            echo '<li>';
            echo '<div class="dps-inline-stats__label">';
            echo '<span class="dps-status-badge dps-status-badge--scheduled">' . esc_html__( 'Assinaturas Ativas', 'dps-subscription-addon' ) . '</span>';
            echo '<small>' . esc_html__( 'Planos recorrentes ativos', 'dps-subscription-addon' ) . '</small>';
            echo '</div>';
            echo '<strong class="dps-inline-stats__value">' . esc_html( (string) $total_active ) . '</strong>';
            echo '</li>';
            echo '<li>';
            echo '<div class="dps-inline-stats__label">';
            echo '<span class="dps-status-badge dps-status-badge--paid">' . esc_html__( 'Valor dos Pacotes', 'dps-subscription-addon' ) . '</span>';
            echo '<small>' . esc_html__( 'Receita mensal estimada', 'dps-subscription-addon' ) . '</small>';
            echo '</div>';
            echo '<strong class="dps-inline-stats__value">R$ ' . esc_html( number_format( $monthly_revenue, 2, ',', '.' ) ) . '</strong>';
            echo '</li>';
            echo '<li>';
            echo '<div class="dps-inline-stats__label">';
            if ( $pending_payment > 0 ) {
                echo '<span class="dps-status-badge dps-status-badge--pending">' . esc_html__( 'Pagamentos Pendentes', 'dps-subscription-addon' ) . '</span>';
            } else {
                echo '<span class="dps-status-badge dps-status-badge--paid">' . esc_html__( 'Pagamentos Pendentes', 'dps-subscription-addon' ) . '</span>';
            }
            echo '<small>' . esc_html__( 'Aguardando confirmação', 'dps-subscription-addon' ) . '</small>';
            echo '</div>';
            echo '<strong class="dps-inline-stats__value">' . esc_html( (string) $pending_payment ) . '</strong>';
            echo '</li>';
            echo '<li>';
            echo '<div class="dps-inline-stats__label">';
            echo '<span class="dps-status-badge dps-status-badge--cancelled">' . esc_html__( 'Canceladas', 'dps-subscription-addon' ) . '</span>';
            echo '<small>' . esc_html__( 'Assinaturas inativas', 'dps-subscription-addon' ) . '</small>';
            echo '</div>';
            echo '<strong class="dps-inline-stats__value">' . esc_html( (string) $total_canceled ) . '</strong>';
            echo '</li>';
            echo '</ul>';
            
            echo '</div>'; // .dps-subscription-status-card
            
            // Card de Lista de Assinaturas Ativas
            echo '<div class="dps-surface dps-surface--neutral dps-subscription-list-card">';
            echo '<div class="dps-surface__title">';
            echo '<span>📋</span>';
            echo esc_html__( 'Assinaturas Ativas', 'dps-subscription-addon' );
            echo '</div>';
        // Os estilos de status de pagamento agora são carregados via assets/css/subscription-addon.css
        if ( $active_subs ) {
            // Labels para data-label em mobile
            $lbl_cliente      = esc_attr__( 'Cliente', 'dps-subscription-addon' );
            $lbl_pet          = esc_attr__( 'Pet', 'dps-subscription-addon' );
            $lbl_servico      = esc_attr__( 'Serviço', 'dps-subscription-addon' );
            $lbl_frequencia   = esc_attr__( 'Frequência', 'dps-subscription-addon' );
            $lbl_valor        = esc_attr__( 'Valor', 'dps-subscription-addon' );
            $lbl_proximo      = esc_attr__( 'Próximo', 'dps-subscription-addon' );
            $lbl_progresso    = esc_attr__( 'Progresso', 'dps-subscription-addon' );
            $lbl_pagamento    = esc_attr__( 'Pagamento', 'dps-subscription-addon' );
            $lbl_acoes        = esc_attr__( 'Ações', 'dps-subscription-addon' );
            
            echo '<div class="dps-subscription-table-wrapper">';
            echo '<table class="dps-table dps-subscription-table">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__( 'Cliente / Pet', 'dps-subscription-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Serviço', 'dps-subscription-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Frequência', 'dps-subscription-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Valor', 'dps-subscription-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Próximo', 'dps-subscription-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Progresso', 'dps-subscription-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Pagamento', 'dps-subscription-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Ações', 'dps-subscription-addon' ) . '</th>';
            echo '</tr></thead><tbody>';
            
            foreach ( $active_subs as $sub ) {
                $cid   = get_post_meta( $sub->ID, 'subscription_client_id', true );
                $pid   = get_post_meta( $sub->ID, 'subscription_pet_id', true );
                $srv   = get_post_meta( $sub->ID, 'subscription_service', true );
                $freq  = get_post_meta( $sub->ID, 'subscription_frequency', true );
                $price = get_post_meta( $sub->ID, 'subscription_price', true );
                $sdate = get_post_meta( $sub->ID, 'subscription_start_date', true );
                $stime = get_post_meta( $sub->ID, 'subscription_start_time', true );
                $pay   = get_post_meta( $sub->ID, 'subscription_payment_status', true );
                $client_post = $cid ? get_post( $cid ) : null;
                $pet_post    = $pid ? get_post( $pid ) : null;
                
                // Próximo agendamento
                $next_appt = '';
                $appts = get_posts( [
                    'post_type'      => 'dps_agendamento',
                    'posts_per_page' => 1,
                    'post_status'    => 'publish',
                    'meta_query'     => [
                        [ 'key' => 'subscription_id', 'value' => $sub->ID, 'compare' => '=' ],
                        [ 'key' => 'appointment_date', 'value' => current_time( 'Y-m-d' ), 'compare' => '>=', 'type' => 'DATE' ],
                    ],
                    'orderby'        => 'meta_value',
                    'meta_key'       => 'appointment_date',
                    'order'          => 'ASC',
                ] );
                if ( $appts ) {
                    $adate = get_post_meta( $appts[0]->ID, 'appointment_date', true );
                    $atime = get_post_meta( $appts[0]->ID, 'appointment_time', true );
                    $next_appt = date_i18n( 'd/m', strtotime( $adate ) ) . ' ' . $atime;
                }
                
                // Contagem de atendimentos realizados e totais
                $total_appts = count( get_posts( [
                    'post_type'      => 'dps_agendamento',
                    'posts_per_page' => -1,
                    'post_status'    => 'publish',
                    'meta_query'     => [ [ 'key' => 'subscription_id', 'value' => $sub->ID, 'compare' => '=' ] ],
                    'fields'         => 'ids',
                ] ) );
                $completed_count = count( get_posts( [
                    'post_type'      => 'dps_agendamento',
                    'posts_per_page' => -1,
                    'post_status'    => 'publish',
                    'meta_query'     => [
                        [ 'key' => 'subscription_id', 'value' => $sub->ID, 'compare' => '=' ],
                        [ 'key' => 'appointment_status', 'value' => [ 'finalizado', 'finalizado_pago' ], 'compare' => 'IN' ],
                    ],
                    'fields'         => 'ids',
                ] ) );
                
                // Calcula porcentagem para barra de progresso
                $progress_pct = $total_appts > 0 ? round( ( $completed_count / $total_appts ) * 100 ) : 0;
                
                // Adiciona classe de pagamento para colorir a linha
                echo '<tr class="pay-status-' . esc_attr( $pay ) . '">';
                
                // Cliente / Pet (combinados para reduzir colunas)
                $client_name = $client_post ? $client_post->post_title : '—';
                $pet_name    = $pet_post ? $pet_post->post_title : '—';
                echo '<td data-label="' . $lbl_cliente . '"><strong>' . esc_html( $client_name ) . '</strong><br><span class="dps-text-secondary">🐾 ' . esc_html( $pet_name ) . '</span></td>';
                
                // Serviço
                echo '<td data-label="' . $lbl_servico . '">' . esc_html( $srv ) . '</td>';
                
                // Frequência
                echo '<td data-label="' . $lbl_frequencia . '">' . esc_html( $freq_options[ $freq ] ?? $freq ) . '</td>';
                
                // Valor
                echo '<td data-label="' . $lbl_valor . '"><strong>R$ ' . esc_html( number_format( (float) $price, 2, ',', '.' ) ) . '</strong></td>';
                
                // Próximo agendamento
                echo '<td data-label="' . $lbl_proximo . '">' . ( $next_appt ? esc_html( $next_appt ) : '<span class="dps-text-muted">—</span>' ) . '</td>';
                
                // Progresso com barra visual
                echo '<td data-label="' . $lbl_progresso . '">';
                if ( $total_appts > 0 ) {
                    echo '<div class="dps-progress-bar" title="' . esc_attr( $completed_count . ' de ' . $total_appts . ' atendimentos realizados' ) . '">';
                    echo '<div class="dps-progress-fill" style="width: ' . esc_attr( $progress_pct ) . '%;"></div>';
                    echo '<span class="dps-progress-text">' . esc_html( $completed_count . '/' . $total_appts ) . '</span>';
                    echo '</div>';
                } else {
                    echo '<span class="dps-text-muted" title="' . esc_attr__( 'Nenhum agendamento gerado ainda', 'dps-subscription-addon' ) . '">—</span>';
                }
                echo '</td>';
                
                // Pagamento (com nonce para proteção CSRF)
                echo '<td data-label="' . $lbl_pagamento . '">';
                echo '<form method="post" class="dps-payment-form">';
                echo '<input type="hidden" name="dps_update_payment" value="1">';
                echo '<input type="hidden" name="subscription_id" value="' . esc_attr( $sub->ID ) . '">';
                wp_nonce_field( 'dps_update_payment_' . $sub->ID, '_wpnonce', false );
                echo '<select name="payment_status" class="dps-select-payment" onchange="this.form.submit()">';
                $pay_opts = [ 'pendente' => __( 'Pendente', 'dps-subscription-addon' ), 'pago' => __( 'Pago', 'dps-subscription-addon' ) ];
                foreach ( $pay_opts as $val => $label ) {
                    $sel = ( $pay === $val ) ? 'selected' : '';
                    echo '<option value="' . esc_attr( $val ) . '" ' . $sel . '>' . esc_html( $label ) . '</option>';
                }
                echo '</select>';
                echo '</form>';
                echo '</td>';
                
                // Ações: editar, cancelar, renovar e cobrar
                echo '<td data-label="' . $lbl_acoes . '" class="dps-col-actions">';
                echo '<div class="dps-action-buttons">';
                
                // Editar
                echo '<a href="' . esc_url( add_query_arg( [ 'tab' => 'assinaturas', 'dps_edit' => 'subscription', 'id' => $sub->ID ], $base_url ) ) . '" class="dps-action-btn" title="' . esc_attr__( 'Editar assinatura', 'dps-subscription-addon' ) . '">✏️</a>';
                
                // Cancelar com nonce
                $cancel_url = wp_nonce_url(
                    add_query_arg( [ 'tab' => 'assinaturas', 'dps_cancel' => 'subscription', 'id' => $sub->ID ], $base_url ),
                    'dps_cancel_subscription_' . $sub->ID
                );
                echo '<a href="' . esc_url( $cancel_url ) . '" class="dps-action-btn" onclick="return confirm(\'' . esc_js( __( 'Tem certeza que deseja cancelar esta assinatura?', 'dps-subscription-addon' ) ) . '\');" title="' . esc_attr__( 'Cancelar assinatura', 'dps-subscription-addon' ) . '">❌</a>';
                
                // O botão Renovar só aparece quando todos os atendimentos do ciclo foram realizados
                if ( $completed_count >= $total_appts && $total_appts > 0 ) {
                    // Link para renovar com nonce
                    $renew_url = wp_nonce_url(
                        add_query_arg( [ 'tab' => 'assinaturas', 'dps_renew' => '1', 'id' => $sub->ID ], $base_url ),
                        'dps_renew_subscription_' . $sub->ID
                    );
                    echo '<a href="' . esc_url( $renew_url ) . '" class="dps-action-btn dps-action-renew" title="' . esc_attr__( 'Renovar assinatura para próximo ciclo', 'dps-subscription-addon' ) . '">🔄</a>';
                    
                    // Link de cobrança via WhatsApp usando helper centralizado
                    $client_phone = $cid ? get_post_meta( $cid, 'client_phone', true ) : '';
                    if ( $client_phone ) {
                        $payment_link = $this->get_subscription_payment_link( $sub->ID, $price );
                        $msg = $this->build_subscription_whatsapp_message( $sub, $payment_link );
                        if ( class_exists( 'DPS_WhatsApp_Helper' ) ) {
                            $wa_link = DPS_WhatsApp_Helper::get_link_to_client( $client_phone, $msg );
                        } else {
                            // Fallback
                            $digits = preg_replace( '/\D+/', '', $client_phone );
                            if ( strlen( $digits ) >= 10 && substr( $digits, 0, 2 ) !== '55' ) {
                                $digits = '55' . $digits;
                            }
                            $wa_link = 'https://wa.me/' . $digits . '?text=' . rawurlencode( $msg );
                        }
                        echo '<a href="' . esc_url( $wa_link ) . '" target="_blank" class="dps-action-btn dps-action-charge" title="' . esc_attr__( 'Cobrar via WhatsApp', 'dps-subscription-addon' ) . '">💰</a>';
                    }
                }
                echo '</div>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '</div>'; // .dps-subscription-table-wrapper
        } else {
            // Empty state moderno
            echo '<div class="dps-empty-state">';
            echo '<span class="dps-empty-state__icon">📋</span>';
            echo '<h4 class="dps-empty-state__title">' . esc_html__( 'Nenhuma assinatura ativa', 'dps-subscription-addon' ) . '</h4>';
            echo '</div>';
        }
        echo '</div>'; // .dps-subscription-list-card
        
        // Card de Assinaturas Canceladas (se houver)
        if ( $canceled_subs ) {
            echo '<div class="dps-surface dps-surface--neutral dps-subscription-canceled-card">';
            echo '<div class="dps-surface__title">';
            echo '<span>🗑️</span>';
            echo esc_html__( 'Assinaturas Canceladas', 'dps-subscription-addon' );
            echo '</div>';
            echo '<p class="dps-surface__description">';
            echo esc_html__( 'Assinaturas inativas que podem ser restauradas ou excluídas permanentemente.', 'dps-subscription-addon' );
            echo '</p>';
            
            // Labels para data-label em mobile
            $lbl_cliente_pet  = esc_attr__( 'Cliente / Pet', 'dps-subscription-addon' );
            $lbl_servico      = esc_attr__( 'Serviço', 'dps-subscription-addon' );
            $lbl_valor        = esc_attr__( 'Valor', 'dps-subscription-addon' );
            $lbl_pagamento    = esc_attr__( 'Status', 'dps-subscription-addon' );
            $lbl_acoes        = esc_attr__( 'Ações', 'dps-subscription-addon' );
            
            echo '<div class="dps-subscription-table-wrapper dps-canceled-table">';
            echo '<table class="dps-table dps-subscription-table">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__( 'Cliente / Pet', 'dps-subscription-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Serviço', 'dps-subscription-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Valor', 'dps-subscription-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Status', 'dps-subscription-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Ações', 'dps-subscription-addon' ) . '</th>';
            echo '</tr></thead><tbody>';
            
            foreach ( $canceled_subs as $sub ) {
                $cid   = get_post_meta( $sub->ID, 'subscription_client_id', true );
                $pid   = get_post_meta( $sub->ID, 'subscription_pet_id', true );
                $srv   = get_post_meta( $sub->ID, 'subscription_service', true );
                $freq  = get_post_meta( $sub->ID, 'subscription_frequency', true );
                $price = get_post_meta( $sub->ID, 'subscription_price', true );
                $pay   = get_post_meta( $sub->ID, 'subscription_payment_status', true );
                $client_post = $cid ? get_post( $cid ) : null;
                $pet_post    = $pid ? get_post( $pid ) : null;
                
                echo '<tr class="dps-canceled-row">';
                
                // Cliente / Pet
                $client_name = $client_post ? $client_post->post_title : '—';
                $pet_name    = $pet_post ? $pet_post->post_title : '—';
                echo '<td data-label="' . $lbl_cliente_pet . '"><strong>' . esc_html( $client_name ) . '</strong><br><span class="dps-text-secondary">🐾 ' . esc_html( $pet_name ) . '</span></td>';
                
                // Serviço + Frequência
                echo '<td data-label="' . $lbl_servico . '">' . esc_html( $srv ) . ' <span class="dps-text-muted">(' . esc_html( $freq_options[ $freq ] ?? $freq ) . ')</span></td>';
                
                // Valor
                echo '<td data-label="' . $lbl_valor . '">R$ ' . esc_html( number_format( (float) $price, 2, ',', '.' ) ) . '</td>';
                
                // Status de pagamento
                $pay_label = $pay === 'pago' ? __( 'Pago', 'dps-subscription-addon' ) : __( 'Pendente', 'dps-subscription-addon' );
                echo '<td data-label="' . $lbl_pagamento . '"><span class="dps-status-badge dps-status-' . esc_attr( $pay ) . '">' . esc_html( $pay_label ) . '</span></td>';
                
                // Ações: restaurar ou excluir permanentemente
                echo '<td data-label="' . $lbl_acoes . '" class="dps-col-actions">';
                echo '<div class="dps-action-buttons">';
                
                // Restaurar com nonce
                $restore_url = wp_nonce_url(
                    add_query_arg( [ 'tab' => 'assinaturas', 'dps_restore' => 'subscription', 'id' => $sub->ID ], $base_url ),
                    'dps_restore_subscription_' . $sub->ID
                );
                echo '<a href="' . esc_url( $restore_url ) . '" class="dps-action-btn dps-action-restore" title="' . esc_attr__( 'Restaurar assinatura', 'dps-subscription-addon' ) . '">♻️</a>';
                
                // Excluir permanentemente com nonce
                $delete_url = wp_nonce_url(
                    add_query_arg( [ 'tab' => 'assinaturas', 'dps_delete' => 'subscription', 'id' => $sub->ID ], $base_url ),
                    'dps_delete_subscription_' . $sub->ID
                );
                echo '<a href="' . esc_url( $delete_url ) . '" class="dps-action-btn dps-action-delete" onclick="return confirm(\'' . esc_js( __( 'Tem certeza que deseja excluir permanentemente? Esta ação não pode ser desfeita.', 'dps-subscription-addon' ) ) . '\');" title="' . esc_attr__( 'Excluir permanentemente', 'dps-subscription-addon' ) . '">🗑️</a>';
                
                echo '</div>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '</div>'; // .dps-subscription-table-wrapper
            echo '</div>'; // .dps-subscription-canceled-card
        }
        }
        
        echo '</div>'; // .dps-subscriptions-stacked
        echo '</div>'; // .dps-section
        return ob_get_clean();
    }
    
    /**
     * Renderiza o formulário de edição de assinatura.
     *
     * @param int    $edit_id      ID da assinatura sendo editada.
     * @param array  $meta         Metadados da assinatura.
     * @param array  $freq_options Opções de frequência disponíveis.
     * @param string $list_url     URL de retorno para a listagem.
     */
    private function render_subscription_edit_form( $edit_id, $meta, $freq_options, $list_url ) {
        // Carrega listas de clientes e pets
        $clients = get_posts( [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );
        $pets = get_posts( [
            'post_type'      => 'dps_pet',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );
        $service_options = [];
        $service_posts   = get_posts( [
            'post_type'      => 'dps_service',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
            'meta_query'     => [
                [
                    'key'     => 'service_active',
                    'value'   => '1',
                    'compare' => '=',
                ],
            ],
        ] );
        if ( $service_posts ) {
            foreach ( $service_posts as $srv ) {
                $service_options[ $srv->post_title ] = $srv->post_title;
            }
        } else {
            $service_options = [
                'Banho'        => __( 'Banho', 'dps-subscription-addon' ),
                'Banho e Tosa' => __( 'Banho e Tosa', 'dps-subscription-addon' ),
            ];
        }

        $extras_list = [];
        if ( ! empty( $meta['extras'] ) && is_array( $meta['extras'] ) ) {
            $extras_list = $meta['extras'];
        }
        if ( empty( $extras_list ) ) {
            $legacy_desc = get_post_meta( $edit_id, 'subscription_extra_description', true );
            $legacy_val  = get_post_meta( $edit_id, 'subscription_extra_value', true );
            if ( '' !== $legacy_desc || '' !== $legacy_val ) {
                $extras_list[] = [
                    'description' => $legacy_desc,
                    'value'       => floatval( $legacy_val ),
                ];
            }
        }
        $has_extras = ! empty( $extras_list );

        // Card de edição
        echo '<div class="dps-surface dps-surface--info dps-subscription-edit-card">';
        echo '<div class="dps-surface__title">';
        echo '<span>✏️</span>';
        echo esc_html__( 'Editar Assinatura', 'dps-subscription-addon' );
        echo '</div>';
        echo '<p class="dps-surface__description">';
        echo esc_html__( 'Altere os dados da assinatura abaixo.', 'dps-subscription-addon' );
        echo ' <a href="' . esc_url( $list_url ) . '" class="dps-cancel-edit">';
        echo esc_html__( 'Cancelar edição', 'dps-subscription-addon' );
        echo '</a>';
        echo '</p>';

        echo '<form method="post" class="dps-form dps-subscription-form">';
        echo '<input type="hidden" name="dps_subscription_action" value="save_subscription">';
        wp_nonce_field( 'dps_subscription_action', 'dps_subscription_nonce' );
        echo '<input type="hidden" name="subscription_id" value="' . esc_attr( $edit_id ) . '">';

        // Fieldset: Frequência
        echo '<fieldset class="dps-fieldset">';
        echo '<legend>' . esc_html__( 'Configuração', 'dps-subscription-addon' ) . '</legend>';
        $freq_val = $meta['frequency'] ?? '';
        echo '<div class="dps-form-row dps-form-row--2col">';
        echo '<div class="dps-form-field">';
        echo '<label for="subscription_frequency">' . esc_html__( 'Frequência', 'dps-subscription-addon' ) . ' <span class="dps-required">*</span></label>';
        echo '<select name="subscription_frequency" id="subscription_frequency" required>';
        echo '<option value="">' . esc_html__( 'Selecione...', 'dps-subscription-addon' ) . '</option>';
        foreach ( $freq_options as $val => $label ) {
            $sel = ( $freq_val === $val ) ? 'selected' : '';
            echo '<option value="' . esc_attr( $val ) . '" ' . $sel . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        echo '<div class="dps-form-field">';
        echo '<label>' . esc_html__( 'Tipo', 'dps-subscription-addon' ) . '</label>';
        echo '<p class="dps-badge dps-badge--primary" style="margin:8px 0 0;">' . esc_html__( 'Assinatura recorrente', 'dps-subscription-addon' ) . '</p>';
        echo '</div>';
        echo '</div>';
        echo '</fieldset>';

        // Fieldset: Cliente e Pet
        echo '<fieldset class="dps-fieldset">';
        echo '<legend>' . esc_html__( 'Cliente e Pet', 'dps-subscription-addon' ) . '</legend>';
        echo '<div class="dps-form-row dps-form-row--2col">';
        echo '<div class="dps-form-field">';
        echo '<label for="subscription_client_id">' . esc_html__( 'Cliente', 'dps-subscription-addon' ) . ' <span class="dps-required">*</span></label>';
        echo '<select name="subscription_client_id" id="subscription_client_id" required onchange="DPSSubscription.filterPetsByClient(this.value)">';
        echo '<option value="">' . esc_html__( 'Selecione...', 'dps-subscription-addon' ) . '</option>';
        foreach ( $clients as $cli ) {
            $sel = ( $meta['client_id'] ?? '' ) == $cli->ID ? 'selected' : '';
            echo '<option value="' . esc_attr( $cli->ID ) . '" ' . $sel . '>' . esc_html( $cli->post_title ) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        echo '<div class="dps-form-field">';
        echo '<label for="dps-subscription-pet-select">' . esc_html__( 'Pet', 'dps-subscription-addon' ) . ' <span class="dps-required">*</span></label>';
        echo '<select name="subscription_pet_id" id="dps-subscription-pet-select" required>';
        echo '<option value="">' . esc_html__( 'Selecione...', 'dps-subscription-addon' ) . '</option>';
        foreach ( $pets as $pet ) {
            $owner_id = get_post_meta( $pet->ID, 'owner_id', true );
            $sel = ( $meta['pet_id'] ?? '' ) == $pet->ID ? 'selected' : '';
            echo '<option value="' . esc_attr( $pet->ID ) . '" data-owner="' . esc_attr( $owner_id ) . '" ' . $sel . '>' . esc_html( $pet->post_title ) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        echo '</div>';
        echo '</fieldset>';

        // Fieldset: Data e Horário
        echo '<fieldset class="dps-fieldset">';
        echo '<legend>' . esc_html__( 'Data e Horário', 'dps-subscription-addon' ) . '</legend>';
        echo '<div class="dps-form-row dps-form-row--2col">';
        $date_val = $meta['start_date'] ?? '';
        echo '<div class="dps-form-field">';
        echo '<label for="subscription_start_date">' . esc_html__( 'Data de início', 'dps-subscription-addon' ) . ' <span class="dps-required">*</span></label>';
        echo '<input type="date" name="subscription_start_date" id="subscription_start_date" value="' . esc_attr( $date_val ) . '" required>';
        echo '</div>';
        $time_val = $meta['start_time'] ?? '';
        echo '<div class="dps-form-field">';
        echo '<label for="subscription_start_time">' . esc_html__( 'Horário', 'dps-subscription-addon' ) . ' <span class="dps-required">*</span></label>';
        echo '<input type="time" name="subscription_start_time" id="subscription_start_time" value="' . esc_attr( $time_val ) . '" required>';
        echo '</div>';
        echo '</div>';
        echo '</fieldset>';

        // Fieldset: Serviços e Valor
        echo '<fieldset class="dps-fieldset">';
        echo '<legend>' . esc_html__( 'Serviço e Valor', 'dps-subscription-addon' ) . '</legend>';
        echo '<div class="dps-form-row dps-form-row--2col">';
        $srv_val = $meta['service'] ?? '';
        echo '<div class="dps-form-field">';
        echo '<label for="subscription_service">' . esc_html__( 'Serviço', 'dps-subscription-addon' ) . ' <span class="dps-required">*</span></label>';
        echo '<select name="subscription_service" id="subscription_service" required>';
        echo '<option value="">' . esc_html__( 'Selecione...', 'dps-subscription-addon' ) . '</option>';
        foreach ( $service_options as $val => $label ) {
            $sel = ( $srv_val === $val ) ? 'selected' : '';
            echo '<option value="' . esc_attr( $val ) . '" ' . $sel . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        $price_val = $meta['price'] ?? '';
        echo '<div class="dps-form-field">';
        echo '<label for="subscription_price">' . esc_html__( 'Valor (R$)', 'dps-subscription-addon' ) . ' <span class="dps-required">*</span></label>';
        echo '<input type="number" step="0.01" min="0" name="subscription_price" id="subscription_price" value="' . esc_attr( $price_val ) . '" required placeholder="0,00">';
        echo '</div>';
        echo '</div>';

        // Extras
        echo '<div class="dps-extras-section">';
        echo '<div class="dps-extras-header">';
        echo '<h5 class="dps-extras-title">' . esc_html__( 'Serviços extras', 'dps-subscription-addon' ) . '</h5>';
        echo '</div>';
        echo '<div id="dps-subscription-extras-list" class="dps-extras-list" ' . ( $has_extras ? '' : 'data-empty="true"' ) . '>';
        if ( $extras_list ) {
            foreach ( $extras_list as $idx => $extra_item ) {
                $desc  = isset( $extra_item['description'] ) ? $extra_item['description'] : '';
                $value = isset( $extra_item['value'] ) ? $extra_item['value'] : '';
                echo '<div class="dps-extra-row" data-index="' . esc_attr( $idx ) . '">';
                echo '<div class="dps-extra-row-fields">';
                echo '<div class="dps-extra-description-field">';
                echo '<input type="text" name="subscription_extras_descriptions[]" value="' . esc_attr( $desc ) . '" placeholder="' . esc_attr__( 'Descrição', 'dps-subscription-addon' ) . '" class="dps-extra-description-input">';
                echo '</div>';
                echo '<div class="dps-extra-value-field">';
                echo '<div class="dps-input-with-prefix">';
                echo '<span class="dps-input-prefix">R$</span>';
                echo '<input type="number" step="0.01" min="0" name="subscription_extras_values[]" value="' . esc_attr( $value ) . '" placeholder="0,00" class="dps-extra-value-input">';
                echo '</div>';
                echo '</div>';
                echo '<button type="button" class="dps-btn dps-btn--icon dps-remove-extra-btn" title="' . esc_attr__( 'Remover', 'dps-subscription-addon' ) . '"><span>✕</span></button>';
                echo '</div>';
                echo '</div>';
            }
        }
        echo '</div>';
        echo '<button type="button" class="dps-btn dps-btn--outline dps-add-extra-btn" data-list="#dps-subscription-extras-list">';
        echo '<span class="dps-btn-icon">➕</span>';
        echo '<span>' . esc_html__( 'Adicionar extra', 'dps-subscription-addon' ) . '</span>';
        echo '</button>';
        echo '</div>';
        echo '</fieldset>';

        // Fieldset: Atribuição e Notas
        echo '<fieldset class="dps-fieldset">';
        echo '<legend>' . esc_html__( 'Detalhes adicionais', 'dps-subscription-addon' ) . '</legend>';
        $assignee_val = $meta['assignee'] ?? '';
        echo '<div class="dps-form-field">';
        echo '<label for="subscription_assignee">' . esc_html__( 'Responsável', 'dps-subscription-addon' ) . '</label>';
        echo '<input type="text" name="subscription_assignee" id="subscription_assignee" value="' . esc_attr( $assignee_val ) . '" placeholder="' . esc_attr__( 'Nome do profissional', 'dps-subscription-addon' ) . '">';
        echo '</div>';
        $notes_val = $meta['notes'] ?? '';
        echo '<div class="dps-form-field">';
        echo '<label for="subscription_notes">' . esc_html__( 'Observações', 'dps-subscription-addon' ) . '</label>';
        echo '<textarea id="subscription_notes" name="subscription_notes" rows="3" placeholder="' . esc_attr__( 'Instruções especiais...', 'dps-subscription-addon' ) . '">' . esc_textarea( $notes_val ) . '</textarea>';
        echo '</div>';
        echo '</fieldset>';

        // Botões de ação
        echo '<div class="dps-form-actions">';
        echo '<button type="submit" class="dps-btn-submit">';
        echo '<span class="dps-btn-icon">💾</span>';
        echo '<span>' . esc_html__( 'Salvar Alterações', 'dps-subscription-addon' ) . '</span>';
        echo '</button>';
        echo '<a href="' . esc_url( $list_url ) . '" class="dps-btn-cancel">';
        echo '<span class="dps-btn-icon">✕</span>';
        echo '<span>' . esc_html__( 'Cancelar', 'dps-subscription-addon' ) . '</span>';
        echo '</a>';
        echo '</div>';

        echo '</form>';
        echo '</div>'; // .dps-subscription-edit-card
    }
}

/**
 * Inicializa o Subscription Add-on após o hook 'init' para garantir que o text domain seja carregado primeiro.
 * Usa prioridade 5 para rodar após o carregamento do text domain (prioridade 1) mas antes
 * de outros registros (prioridade 10).
 */
function dps_subscription_init_addon() {
    if ( class_exists( 'DPS_Subscription_Addon' ) ) {
        new DPS_Subscription_Addon();
    }
}
add_action( 'init', 'dps_subscription_init_addon', 5 );
