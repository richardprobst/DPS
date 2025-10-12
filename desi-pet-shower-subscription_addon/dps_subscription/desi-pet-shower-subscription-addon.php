<?php
/*
 * Plugin Name: Desi Pet Shower – Assinaturas Add-on
 * Description: Add-on para o plugin base do Desi Pet Shower. Permite cadastrar pacotes mensais de banho com frequências semanal ou quinzenal. Gera automaticamente os agendamentos do mês, controla pagamento e permite renovação.
 * Version:     1.0.0
 * Author:      PRObst
 * License:     GPL-2.0+
 * Text Domain: dps-subscription-addon
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
        add_action( 'dps_base_nav_tabs', [ $this, 'add_subscriptions_tab' ], 10, 1 );
        add_action( 'dps_base_sections', [ $this, 'add_subscriptions_section' ], 10, 1 );
        // Manipular salvamento, exclusão e renovação
        add_action( 'init', [ $this, 'maybe_handle_subscription_request' ] );

        // Após tratar requisições de assinatura, sincroniza a assinatura com o
        // módulo financeiro. Esta ação é adicionada com prioridade 20 para
        // garantir que a assinatura já tenha sido salva/atualizada quando a
        // transação financeira for criada ou atualizada.
        add_action( 'init', [ $this, 'maybe_sync_finance_on_save' ], 20 );

        // Integrações com Financeiro: quando uma assinatura for salva ou atualizada,
        // cria ou atualiza a transação correspondente na tabela dps_transacoes. Se
        // o status de pagamento mudar ou a assinatura for renovada, o registro
        // financeiro será atualizado para refletir o novo status. As exclusões
        // também removem qualquer transação associada.

        // Este add-on também utiliza o pagamento automático: ao término de uma
        // assinatura, é possível gerar uma cobrança de renovação via WhatsApp.
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
     * Processa formulários e ações relacionados às assinaturas
     */
    public function maybe_handle_subscription_request() {
        // Salvar ou editar assinatura
        if ( isset( $_POST['dps_subscription_action'] ) && check_admin_referer( 'dps_subscription_action', 'dps_subscription_nonce' ) ) {
            $this->save_subscription();
        }
        // Cancelar assinatura: move para lixeira sem excluir transações
        if ( isset( $_GET['dps_cancel'] ) && 'subscription' === $_GET['dps_cancel'] && isset( $_GET['id'] ) ) {
            $sub_id = intval( $_GET['id'] );
            if ( $sub_id ) {
                wp_trash_post( $sub_id );
            }
            $base_url = get_permalink();
            $redirect_url = remove_query_arg( [ 'dps_cancel', 'id' ], $base_url );
            $redirect_url = add_query_arg( [ 'tab' => 'assinaturas' ], $redirect_url );
            wp_redirect( $redirect_url );
            exit;
        }
        // Restaurar assinatura cancelada
        if ( isset( $_GET['dps_restore'] ) && 'subscription' === $_GET['dps_restore'] && isset( $_GET['id'] ) ) {
            $sub_id = intval( $_GET['id'] );
            if ( $sub_id ) {
                wp_untrash_post( $sub_id );
            }
            $base_url = get_permalink();
            $redirect_url = remove_query_arg( [ 'dps_restore', 'id' ], $base_url );
            $redirect_url = add_query_arg( [ 'tab' => 'assinaturas' ], $redirect_url );
            wp_redirect( $redirect_url );
            exit;
        }
        // Excluir assinatura via GET (permanente)
        if ( isset( $_GET['dps_delete'] ) && 'subscription' === $_GET['dps_delete'] && isset( $_GET['id'] ) ) {
            $sub_id = intval( $_GET['id'] );
            // Exclui permanentemente
            wp_delete_post( $sub_id, true );
            // Remove quaisquer transações financeiras associadas a esta assinatura
            $this->delete_finance_records( $sub_id );
            $base_url     = get_permalink();
            $redirect_url = remove_query_arg( [ 'dps_delete', 'id' ], $base_url );
            $redirect_url = add_query_arg( [ 'tab' => 'assinaturas' ], $redirect_url );
            wp_redirect( $redirect_url );
            exit;
        }
        // Renovar assinatura
        if ( isset( $_GET['dps_renew'] ) && isset( $_GET['id'] ) ) {
            $sub_id = intval( $_GET['id'] );
            $this->renew_subscription( $sub_id );
            $base_url = get_permalink();
            // Remove o parâmetro de renovação para evitar redirecionamentos em loop
            $redirect_url = remove_query_arg( [ 'dps_renew', 'id' ], $base_url );
            $redirect_url = add_query_arg( [ 'tab' => 'assinaturas' ], $redirect_url );
            wp_redirect( $redirect_url );
            exit;
        }
        // Excluir todos os agendamentos vinculados a uma assinatura
        if ( isset( $_GET['dps_delete_appts'] ) && isset( $_GET['id'] ) ) {
            $sub_id = intval( $_GET['id'] );
            $this->delete_all_appointments( $sub_id );
            $base_url = get_permalink();
            $redirect_url = remove_query_arg( [ 'dps_delete_appts', 'id' ], $base_url );
            $redirect_url = add_query_arg( [ 'tab' => 'assinaturas' ], $redirect_url );
            wp_redirect( $redirect_url );
            exit;
        }
        // Atualizar status de pagamento
        if ( isset( $_POST['dps_update_payment'] ) && isset( $_POST['subscription_id'] ) ) {
            $sub_id = intval( $_POST['subscription_id'] );
            $status = sanitize_text_field( $_POST['payment_status'] );
            update_post_meta( $sub_id, 'subscription_payment_status', $status );
            // Se há primeira consulta deste ciclo, atualiza status do primeiro agendamento correspondente
            $this->update_first_appointment_status( $sub_id, $status );
            // Atualiza o status da transação financeira relacionada
            $this->create_or_update_finance_record( $sub_id );
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
        // Marca pagamento como pendente por padrão se não definido
        $pay_status = get_post_meta( $sub_id, 'subscription_payment_status', true );
        if ( ! $pay_status ) {
            update_post_meta( $sub_id, 'subscription_payment_status', 'pendente' );
        }
        // Gera agendamentos para o mês atual ou do start_date, se novo ou se start_date mudou
        $this->generate_monthly_appointments( $sub_id, $start_date );

        // Sincroniza com o financeiro: cria ou atualiza o registro de transação
        // da assinatura no módulo financeiro. Isso garante que cada assinatura
        // possua uma entrada na tabela dps_transacoes correspondente ao ciclo
        // vigente (data de início) e ao valor do pacote. O status da transação
        // refletirá o campo subscription_payment_status.
        $this->create_or_update_finance_record( $sub_id );
    }

    /**
     * Gera os agendamentos para uma assinatura para o mês da data inicial
     * @param int    $sub_id     ID da assinatura
     * @param string $date_start Data inicial (Y-m-d)
     */
    private function generate_monthly_appointments( $sub_id, $date_start ) {
        // Remove agendamentos existentes associados a esta assinatura na mesma data atual
        $pet_id    = get_post_meta( $sub_id, 'subscription_pet_id', true );
        $client_id = get_post_meta( $sub_id, 'subscription_client_id', true );
        $frequency = get_post_meta( $sub_id, 'subscription_frequency', true );
        $service   = get_post_meta( $sub_id, 'subscription_service', true );
        $start_time= get_post_meta( $sub_id, 'subscription_start_time', true );
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
        // Apaga todos os agendamentos existentes associados a esta assinatura (não apenas no mês)
        $existing = get_posts( [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [ 'key' => 'subscription_id', 'value' => $sub_id, 'compare' => '=' ],
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
                // Indica que o agendamento pertence a um pacote de assinatura
                update_post_meta( $appt_id, 'appointment_notes', __( 'Serviço de assinatura', 'dps-subscription-addon' ) );
            }
        }
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
     * como "pago"; caso contrário, permanecerá "em_aberto".
     *
     * @param int $sub_id ID da assinatura
     */
    private function create_or_update_finance_record( $sub_id ) {
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
        $date_db   = $start_date; // armazenamos somente a data (YYYY-mm-dd)
        $status    = ( 'pago' === $pay_status ) ? 'pago' : 'em_aberto';
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
        $this->generate_monthly_appointments( $sub_id, $new_date_start );

        // Cria novo registro financeiro para o novo ciclo. Como o
        // subscription_start_date foi atualizado, este método inserirá uma
        // nova transação para a data atualizada.
        $this->create_or_update_finance_record( $sub_id );
    }

    /**
     * Renderiza a seção de assinaturas: formulário e listagem
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
        ob_start();
        echo '<div class="dps-section" id="dps-section-assinaturas">';
        echo '<h3>' . esc_html__( 'Assinaturas Mensais', 'dps-subscription-addon' ) . '</h3>';
        // Se editando, mostra formulário de edição
        if ( $edit_id ) {
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
            $service_options = [
                'Banho'         => __( 'Banho', 'dps-subscription-addon' ),
                'Banho e Tosa' => __( 'Banho e Tosa', 'dps-subscription-addon' ),
            ];
            echo '<h4>' . esc_html__( 'Editar Assinatura', 'dps-subscription-addon' ) . '</h4>';
            echo '<form method="post" class="dps-form">';
            echo '<input type="hidden" name="dps_subscription_action" value="save_subscription">';
            wp_nonce_field( 'dps_subscription_action', 'dps_subscription_nonce' );
            echo '<input type="hidden" name="subscription_id" value="' . esc_attr( $edit_id ) . '">';
            // Cliente
            echo '<p><label>' . esc_html__( 'Cliente', 'dps-subscription-addon' ) . '<br><select name="subscription_client_id" required onchange="DPSSubscription.filterPetsByClient(this.value)"><option value="">' . esc_html__( 'Selecione...', 'dps-subscription-addon' ) . '</option>';
            foreach ( $clients as $cli ) {
                $sel = ( $meta['client_id'] ?? '' ) == $cli->ID ? 'selected' : '';
                echo '<option value="' . esc_attr( $cli->ID ) . '" ' . $sel . '>' . esc_html( $cli->post_title ) . '</option>';
            }
            echo '</select></label></p>';
            // Pet
            echo '<p><label>' . esc_html__( 'Pet', 'dps-subscription-addon' ) . '<br><select name="subscription_pet_id" id="dps-subscription-pet-select" required><option value="">' . esc_html__( 'Selecione...', 'dps-subscription-addon' ) . '</option>';
            foreach ( $pets as $pet ) {
                $owner_id = get_post_meta( $pet->ID, 'owner_id', true );
                $sel = ( $meta['pet_id'] ?? '' ) == $pet->ID ? 'selected' : '';
                echo '<option value="' . esc_attr( $pet->ID ) . '" data-owner="' . esc_attr( $owner_id ) . '" ' . $sel . '>' . esc_html( $pet->post_title ) . '</option>';
            }
            echo '</select></label></p>';
            // Serviço
            $srv_val = $meta['service'] ?? '';
            echo '<p><label>' . esc_html__( 'Serviço', 'dps-subscription-addon' ) . '<br><select name="subscription_service" required><option value="">' . esc_html__( 'Selecione...', 'dps-subscription-addon' ) . '</option>';
            foreach ( $service_options as $val => $label ) {
                $sel = ( $srv_val === $val ) ? 'selected' : '';
                echo '<option value="' . esc_attr( $val ) . '" ' . $sel . '>' . esc_html( $label ) . '</option>';
            }
            echo '</select></label></p>';
            // Frequência
            $freq_val = $meta['frequency'] ?? '';
            echo '<p><label>' . esc_html__( 'Frequência', 'dps-subscription-addon' ) . '<br><select name="subscription_frequency" required><option value="">' . esc_html__( 'Selecione...', 'dps-subscription-addon' ) . '</option>';
            foreach ( $freq_options as $val => $label ) {
                $sel = ( $freq_val === $val ) ? 'selected' : '';
                echo '<option value="' . esc_attr( $val ) . '" ' . $sel . '>' . esc_html( $label ) . '</option>';
            }
            echo '</select></label></p>';
            // Valor
            $price_val = $meta['price'] ?? '';
            echo '<p><label>' . esc_html__( 'Valor do pacote (R$)', 'dps-subscription-addon' ) . '<br><input type="number" step="0.01" min="0" name="subscription_price" value="' . esc_attr( $price_val ) . '" required></label></p>';
            // Data e hora
            $date_val = $meta['start_date'] ?? '';
            $time_val = $meta['start_time'] ?? '';
            echo '<p><label>' . esc_html__( 'Data de início', 'dps-subscription-addon' ) . '<br><input type="date" name="subscription_start_date" value="' . esc_attr( $date_val ) . '" required></label></p>';
            echo '<p><label>' . esc_html__( 'Horário', 'dps-subscription-addon' ) . '<br><input type="time" name="subscription_start_time" value="' . esc_attr( $time_val ) . '" required></label></p>';
            // Botão
            echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Atualizar Assinatura', 'dps-subscription-addon' ) . '</button></p>';
            echo '</form>';
            // Script para filtrar pets
            echo '<script>(function($){$(document).ready(function(){\
                window.DPSSubscription = window.DPSSubscription || {};\
                DPSSubscription.filterPetsByClient = function(ownerId){ var select = $("#dps-subscription-pet-select"); if(!ownerId){ select.val(""); select.closest("p").hide(); return; } select.closest("p").show(); select.find("option").each(function(){ var optOwner = $(this).data("owner"); if(!optOwner){ $(this).show(); } else { if(String(optOwner) === String(ownerId)){ $(this).show(); } else { $(this).hide(); } } }); if(select.find("option:selected").is(":hidden")){ select.val(""); } };\
                var initialClient = $("select[name=\'subscription_client_id\']").val(); if(initialClient){ DPSSubscription.filterPetsByClient(initialClient); } else { $("#dps-subscription-pet-select").closest("p").hide(); }\
            });})(jQuery);</script>';
        }
        // Exibe lista de assinaturas ativas
        echo '<h4>' . esc_html__( 'Assinaturas Ativas', 'dps-subscription-addon' ) . '</h4>';
        // Estilos para destacar status de pagamento nas linhas das assinaturas ativas
        echo '<style>
        table.dps-table tr.pay-status-pendente { background-color:#fff8e1; }
        table.dps-table tr.pay-status-pago { background-color:#e6ffed; }
        </style>';
        if ( $active_subs ) {
        echo '<table class="dps-table"><thead><tr><th>' . esc_html__( 'Cliente', 'dps-subscription-addon' ) . '</th><th>' . esc_html__( 'Pet', 'dps-subscription-addon' ) . '</th><th>' . esc_html__( 'Serviço', 'dps-subscription-addon' ) . '</th><th>' . esc_html__( 'Frequência', 'dps-subscription-addon' ) . '</th><th>' . esc_html__( 'Valor', 'dps-subscription-addon' ) . '</th><th>' . esc_html__( 'Início', 'dps-subscription-addon' ) . '</th><th>' . esc_html__( 'Próximo agendamento', 'dps-subscription-addon' ) . '</th><th>' . esc_html__( 'Atendimentos', 'dps-subscription-addon' ) . '</th><th>' . esc_html__( 'Pagamento', 'dps-subscription-addon' ) . '</th><th>' . esc_html__( 'Ações', 'dps-subscription-addon' ) . '</th></tr></thead><tbody>';
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
                    $next_appt = date_i18n( 'd-m-Y', strtotime( $adate ) ) . ' ' . $atime;
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
                // Adiciona classe de pagamento para colorir a linha
                echo '<tr class="pay-status-' . esc_attr( $pay ) . '">';
                echo '<td>' . esc_html( $client_post ? $client_post->post_title : '' ) . '</td>';
                echo '<td>' . esc_html( $pet_post ? $pet_post->post_title : '' ) . '</td>';
                echo '<td>' . esc_html( $srv ) . '</td>';
                echo '<td>' . esc_html( $freq_options[ $freq ] ?? $freq ) . '</td>';
                echo '<td>R$ ' . esc_html( number_format( (float) $price, 2, ',', '.' ) ) . '</td>';
                $date_fmt = $sdate ? date_i18n( 'd-m-Y', strtotime( $sdate ) ) : '';
                echo '<td>' . esc_html( $date_fmt . ' ' . $stime ) . '</td>';
                echo '<td>' . esc_html( $next_appt ) . '</td>';
                echo '<td>' . esc_html( $completed_count . ' / ' . $total_appts ) . '</td>';
                // Pagamento
                echo '<td><form method="post" style="display:inline;"><input type="hidden" name="dps_update_payment" value="1"><input type="hidden" name="subscription_id" value="' . esc_attr( $sub->ID ) . '"><select name="payment_status" onchange="this.form.submit()">';
                $pay_opts = [ 'pendente' => __( 'Pendente', 'dps-subscription-addon' ), 'pago' => __( 'Pago', 'dps-subscription-addon' ) ];
                foreach ( $pay_opts as $val => $label ) {
                    $sel = ( $pay === $val ) ? 'selected' : '';
                    echo '<option value="' . esc_attr( $val ) . '" ' . $sel . '>' . esc_html( $label ) . '</option>';
                }
                echo '</select></form></td>';
                // Ações: editar, cancelar, renovar e cobrar
                echo '<td>';
                $base_url = get_permalink();
                echo '<a href="' . esc_url( add_query_arg( [ 'tab' => 'assinaturas', 'dps_edit' => 'subscription', 'id' => $sub->ID ], $base_url ) ) . '">' . esc_html__( 'Editar', 'dps-subscription-addon' ) . '</a> | ';
                echo '<a href="' . esc_url( add_query_arg( [ 'tab' => 'assinaturas', 'dps_cancel' => 'subscription', 'id' => $sub->ID ], $base_url ) ) . '" onclick="return confirm(\'' . esc_js( __( 'Tem certeza que deseja cancelar esta assinatura?', 'dps-subscription-addon' ) ) . '\');">' . esc_html__( 'Cancelar', 'dps-subscription-addon' ) . '</a>';
                // O botão Renovar só aparece quando todos os atendimentos do ciclo foram realizados
                if ( $completed_count >= $total_appts ) {
                    // Link para renovar
                    echo ' | <a href="' . esc_url( add_query_arg( [ 'tab' => 'assinaturas', 'dps_renew' => '1', 'id' => $sub->ID ], $base_url ) ) . '">' . esc_html__( 'Renovar', 'dps-subscription-addon' ) . '</a>';
                    // Link de cobrança via WhatsApp
                    // Recupera telefone do cliente
                    $client_phone = $cid ? get_post_meta( $cid, 'client_phone', true ) : '';
                    if ( $client_phone ) {
                        $digits = preg_replace( '/\D+/', '', $client_phone );
                        if ( strlen( $digits ) >= 10 && substr( $digits, 0, 2 ) !== '55' ) {
                            $digits = '55' . $digits;
                        }
                        $payment_link = $this->get_subscription_payment_link( $sub->ID, $price );
                        $msg = $this->build_subscription_whatsapp_message( $sub, $payment_link );
                        $wa_link = 'https://wa.me/' . $digits . '?text=' . rawurlencode( $msg );
                        echo ' | <a href="' . esc_url( $wa_link ) . '" target="_blank">' . esc_html__( 'Cobrar', 'dps-subscription-addon' ) . '</a>';
                    }
                }
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>' . esc_html__( 'Nenhuma assinatura ativa.', 'dps-subscription-addon' ) . '</p>';
        }
        // Assinaturas canceladas
        echo '<h4 style="margin-top:20px;">' . esc_html__( 'Assinaturas Canceladas', 'dps-subscription-addon' ) . '</h4>';
        if ( $canceled_subs ) {
            echo '<table class="dps-table"><thead><tr><th>' . esc_html__( 'Cliente', 'dps-subscription-addon' ) . '</th><th>' . esc_html__( 'Pet', 'dps-subscription-addon' ) . '</th><th>' . esc_html__( 'Serviço', 'dps-subscription-addon' ) . '</th><th>' . esc_html__( 'Frequência', 'dps-subscription-addon' ) . '</th><th>' . esc_html__( 'Valor', 'dps-subscription-addon' ) . '</th><th>' . esc_html__( 'Início', 'dps-subscription-addon' ) . '</th><th>' . esc_html__( 'Pagamento', 'dps-subscription-addon' ) . '</th><th>' . esc_html__( 'Ações', 'dps-subscription-addon' ) . '</th></tr></thead><tbody>';
            foreach ( $canceled_subs as $sub ) {
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
                echo '<tr>';
                echo '<td>' . esc_html( $client_post ? $client_post->post_title : '' ) . '</td>';
                echo '<td>' . esc_html( $pet_post ? $pet_post->post_title : '' ) . '</td>';
                echo '<td>' . esc_html( $srv ) . '</td>';
                echo '<td>' . esc_html( $freq_options[ $freq ] ?? $freq ) . '</td>';
                echo '<td>R$ ' . esc_html( number_format( (float) $price, 2, ',', '.' ) ) . '</td>';
                $date_fmt = $sdate ? date_i18n( 'd-m-Y', strtotime( $sdate ) ) : '';
                echo '<td>' . esc_html( $date_fmt . ' ' . $stime ) . '</td>';
                echo '<td>' . esc_html( $pay ) . '</td>';
                // Ações: restaurar ou excluir permanentemente
                echo '<td>';
                $base_url = get_permalink();
                echo '<a href="' . esc_url( add_query_arg( [ 'tab' => 'assinaturas', 'dps_restore' => 'subscription', 'id' => $sub->ID ], $base_url ) ) . '">' . esc_html__( 'Restaurar', 'dps-subscription-addon' ) . '</a> | ';
                echo '<a href="' . esc_url( add_query_arg( [ 'tab' => 'assinaturas', 'dps_delete' => 'subscription', 'id' => $sub->ID ], $base_url ) ) . '" onclick="return confirm(\'' . esc_js( __( 'Tem certeza que deseja excluir permanentemente?', 'dps-subscription-addon' ) ) . '\');">' . esc_html__( 'Excluir', 'dps-subscription-addon' ) . '</a>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>' . esc_html__( 'Nenhuma assinatura cancelada.', 'dps-subscription-addon' ) . '</p>';
        }
        echo '</div>';
        return ob_get_clean();
    }
}

new DPS_Subscription_Addon();