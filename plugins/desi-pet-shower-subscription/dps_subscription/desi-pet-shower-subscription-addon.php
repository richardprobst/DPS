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
     * Adiciona uma mensagem de feedback ao usuário.
     * Usa DPS_Message_Helper se disponível, caso contrário usa transient.
     *
     * @param string $message Mensagem a exibir.
     * @param string $type    Tipo: 'success', 'error', 'warning', 'info'.
     */
    private function add_user_message( $message, $type = 'info' ) {
        if ( class_exists( 'DPS_Message_Helper' ) ) {
            switch ( $type ) {
                case 'error':
                    DPS_Message_Helper::add_error( $message );
                    break;
                case 'success':
                    DPS_Message_Helper::add_success( $message );
                    break;
                case 'warning':
                    DPS_Message_Helper::add_warning( $message );
                    break;
                default:
                    DPS_Message_Helper::add_info( $message );
                    break;
            }
        } else {
            // Fallback: armazena em transient para exibição posterior
            $messages = get_transient( 'dps_subscription_messages' );
            if ( ! is_array( $messages ) ) {
                $messages = [];
            }
            $messages[] = [
                'message' => $message,
                'type'    => $type,
            ];
            set_transient( 'dps_subscription_messages', $messages, 60 );
        }
    }

    /**
     * Enfileira CSS e JS do add-on de assinaturas.
     * Carrega apenas quando necessário (aba de assinaturas ativa).
     */
    public function enqueue_assets() {
        // Obtém o diretório do add-on (usa __DIR__ em vez de dirname(__FILE__) para modernidade)
        $addon_url = plugin_dir_url( __DIR__ );
        $version   = '1.3.0';

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
        
        // Localiza strings para JavaScript (i18n)
        wp_localize_script( 'dps-subscription-addon', 'dpsSubscriptionStrings', [
            'description'        => __( 'Descrição do serviço', 'dps-subscription-addon' ),
            'remove'             => __( 'Remover', 'dps-subscription-addon' ),
            'saving'             => __( 'Salvando...', 'dps-subscription-addon' ),
            'save_changes'       => __( 'Salvar Alterações', 'dps-subscription-addon' ),
            'confirm_cancel'     => __( 'Tem certeza que deseja cancelar esta assinatura?', 'dps-subscription-addon' ),
            'confirm_delete'     => __( 'Tem certeza que deseja excluir permanentemente? Esta ação não pode ser desfeita.', 'dps-subscription-addon' ),
            'required_fields'    => __( 'Por favor, preencha todos os campos obrigatórios.', 'dps-subscription-addon' ),
            'invalid_date'       => __( 'Por favor, insira uma data válida.', 'dps-subscription-addon' ),
            'invalid_time'       => __( 'Por favor, insira um horário válido.', 'dps-subscription-addon' ),
            'updating_status'    => __( 'Atualizando...', 'dps-subscription-addon' ),
        ] );
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
        $sub_id = absint( $sub_id );
        $amount = floatval( $amount );
        
        if ( ! $sub_id || $amount <= 0 ) {
            return 'https://link.mercadopago.com.br/desipetshower';
        }
        
        $link = get_post_meta( $sub_id, 'dps_subscription_payment_link', true );
        if ( ! empty( $link ) ) {
            return esc_url( $link );
        }
        
        // Obtém token de forma segura (pode ser constante ou option)
        $token = '';
        if ( defined( 'DPS_MERCADOPAGO_ACCESS_TOKEN' ) ) {
            $token = DPS_MERCADOPAGO_ACCESS_TOKEN;
        } else {
            $token = get_option( 'dps_mercadopago_access_token', '' );
        }
        $token = trim( (string) $token );
        
        if ( ! $token ) {
            // Retorna link padrão caso não haja token configurado
            return 'https://link.mercadopago.com.br/desipetshower';
        }
        
        $description = sprintf(
            /* translators: %d: subscription ID */
            __( 'Renovação assinatura ID %d', 'dps-subscription-addon' ),
            $sub_id
        );
        
        // Monta payload conforme a API de preferências do Mercado Pago
        $body = [
            'items' => [
                [
                    'title'       => sanitize_text_field( $description ),
                    'quantity'    => 1,
                    'unit_price'  => $amount,
                    'currency_id' => 'BRL',
                ],
            ],
            // Define uma referência externa previsível para rastreamento do pagamento.
            // Usamos apenas o ID da assinatura para que o webhook possa localizar a
            // assinatura correspondente sem depender de timestamps.
            'external_reference' => 'dps_subscription_' . $sub_id,
        ];
        
        $args = [
            'body'    => wp_json_encode( $body ),
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'timeout' => 30,
            'sslverify' => true,
        ];
        
        $response = wp_remote_post( 'https://api.mercadopago.com/checkout/preferences', $args );
        
        if ( is_wp_error( $response ) ) {
            // Log do erro sem expor token
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[DPS Subscription] Erro ao criar preferência Mercado Pago: ' . $response->get_error_message() );
            }
            return 'https://link.mercadopago.com.br/desipetshower';
        }
        
        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code < 200 || $response_code >= 300 ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[DPS Subscription] Resposta inesperada da API Mercado Pago. Código: ' . $response_code );
            }
            return 'https://link.mercadopago.com.br/desipetshower';
        }
        
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( isset( $data['init_point'] ) && filter_var( $data['init_point'], FILTER_VALIDATE_URL ) ) {
            $link = esc_url_raw( $data['init_point'] );
            update_post_meta( $sub_id, 'dps_subscription_payment_link', $link );
            return $link;
        }
        
        return 'https://link.mercadopago.com.br/desipetshower';
    }

    /**
     * Cria a mensagem de cobrança para a renovação da assinatura.
     * Suporta múltiplos pets na mensagem.
     *
     * @param WP_Post $sub Objeto da assinatura
     * @param string $payment_link Link de pagamento a ser usado
     * @return string Mensagem formatada
     */
    private function build_subscription_whatsapp_message( $sub, $payment_link ) {
        $cid   = get_post_meta( $sub->ID, 'subscription_client_id', true );
        $price = get_post_meta( $sub->ID, 'subscription_price', true );
        $client_post = $cid ? get_post( absint( $cid ) ) : null;
        $client_name = $client_post ? sanitize_text_field( $client_post->post_title ) : '';
        $valor_fmt   = $price ? DPS_Money_Helper::format_decimal_to_brazilian( floatval( $price ) ) : '0,00';
        
        // Suporta múltiplos pets
        $pet_ids_raw = get_post_meta( $sub->ID, 'subscription_pet_ids', true );
        $pet_id_single = get_post_meta( $sub->ID, 'subscription_pet_id', true );
        
        if ( ! empty( $pet_ids_raw ) && is_array( $pet_ids_raw ) ) {
            $pet_ids = array_map( 'absint', $pet_ids_raw );
        } elseif ( $pet_id_single ) {
            $pet_ids = [ absint( $pet_id_single ) ];
        } else {
            $pet_ids = [];
        }
        
        // Coleta nomes de todos os pets
        $pet_names = [];
        foreach ( $pet_ids as $pid ) {
            $pet_post = get_post( $pid );
            if ( $pet_post ) {
                $pet_names[] = sanitize_text_field( $pet_post->post_title );
            }
        }
        
        // Formata lista de pets
        if ( count( $pet_names ) > 1 ) {
            $last_pet = array_pop( $pet_names );
            $pets_display = implode( ', ', $pet_names ) . ' e ' . $last_pet;
        } elseif ( count( $pet_names ) === 1 ) {
            $pets_display = $pet_names[0];
        } else {
            $pets_display = '';
        }
        
        // Obtém a chave PIX configurada ou utiliza padrão
        // Reaproveita a opção do módulo de pagamentos
        $pix_option  = get_option( 'dps_pix_key', '' );
        $pix_display = $pix_option ? sanitize_text_field( $pix_option ) : '15 99160-6299';
        
        // O $payment_link já vem escapado de get_subscription_payment_link()
        // Não fazer escape adicional para evitar duplo escape
        
        // Mensagem padrão para renovação da assinatura (ajusta singular/plural)
        if ( count( $pet_ids ) > 1 ) {
            $msg = sprintf(
                /* translators: %1$s: client name, %2$s: pet names, %3$s: formatted value, %4$s: PIX key, %5$s: payment link */
                __( 'Olá %1$s! A assinatura dos pets %2$s foi concluída. O valor da renovação de R$ %3$s está pendente. Você pode pagar via PIX (%4$s) ou pelo link: %5$s. Obrigado!', 'dps-subscription-addon' ),
                $client_name,
                $pets_display,
                $valor_fmt,
                $pix_display,
                $payment_link
            );
        } else {
            $msg = sprintf(
                /* translators: %1$s: client name, %2$s: pet name, %3$s: formatted value, %4$s: PIX key, %5$s: payment link */
                __( 'Olá %1$s! A assinatura do pet %2$s foi concluída. O valor da renovação de R$ %3$s está pendente. Você pode pagar via PIX (%4$s) ou pelo link: %5$s. Obrigado!', 'dps-subscription-addon' ),
                $client_name,
                $pets_display,
                $valor_fmt,
                $pix_display,
                $payment_link
            );
        }
        
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
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- section_subscriptions() escapa internamente
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
            $sub_id = absint( $_GET['id'] );
            // Verificar nonce para proteção CSRF
            $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
            if ( ! wp_verify_nonce( $nonce, 'dps_cancel_subscription_' . $sub_id ) ) {
                wp_die( esc_html__( 'Ação não autorizada. Link expirado ou inválido.', 'dps-subscription-addon' ) );
            }
            // Valida que é uma assinatura válida
            $subscription = get_post( $sub_id );
            if ( $sub_id && $subscription && 'dps_subscription' === $subscription->post_type ) {
                wp_trash_post( $sub_id );
            }
            $base_url = DPS_URL_Builder::safe_get_permalink();
            $redirect_url = remove_query_arg( [ 'dps_cancel', 'id', '_wpnonce' ], $base_url );
            $redirect_url = add_query_arg( [ 'tab' => 'assinaturas' ], $redirect_url );
            wp_safe_redirect( $redirect_url );
            exit;
        }

        // Restaurar assinatura cancelada
        if ( isset( $_GET['dps_restore'] ) && 'subscription' === $_GET['dps_restore'] && isset( $_GET['id'] ) ) {
            $sub_id = absint( $_GET['id'] );
            // Verificar nonce para proteção CSRF
            $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
            if ( ! wp_verify_nonce( $nonce, 'dps_restore_subscription_' . $sub_id ) ) {
                wp_die( esc_html__( 'Ação não autorizada. Link expirado ou inválido.', 'dps-subscription-addon' ) );
            }
            // Valida que é uma assinatura válida
            $subscription = get_post( $sub_id );
            if ( $sub_id && $subscription && 'dps_subscription' === $subscription->post_type ) {
                wp_untrash_post( $sub_id );
            }
            $base_url = DPS_URL_Builder::safe_get_permalink();
            $redirect_url = remove_query_arg( [ 'dps_restore', 'id', '_wpnonce' ], $base_url );
            $redirect_url = add_query_arg( [ 'tab' => 'assinaturas' ], $redirect_url );
            wp_safe_redirect( $redirect_url );
            exit;
        }

        // Excluir assinatura via GET (permanente)
        if ( isset( $_GET['dps_delete'] ) && 'subscription' === $_GET['dps_delete'] && isset( $_GET['id'] ) ) {
            $sub_id = absint( $_GET['id'] );
            // Verificar nonce para proteção CSRF
            $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
            if ( ! wp_verify_nonce( $nonce, 'dps_delete_subscription_' . $sub_id ) ) {
                wp_die( esc_html__( 'Ação não autorizada. Link expirado ou inválido.', 'dps-subscription-addon' ) );
            }
            // Valida que é uma assinatura válida antes de excluir
            $subscription = get_post( $sub_id );
            if ( $sub_id && $subscription && 'dps_subscription' === $subscription->post_type ) {
                // Exclui permanentemente
                wp_delete_post( $sub_id, true );
                // Remove quaisquer transações financeiras associadas a esta assinatura
                $this->delete_finance_records( $sub_id );
            }
            $base_url     = DPS_URL_Builder::safe_get_permalink();
            $redirect_url = remove_query_arg( [ 'dps_delete', 'id', '_wpnonce' ], $base_url );
            $redirect_url = add_query_arg( [ 'tab' => 'assinaturas' ], $redirect_url );
            wp_safe_redirect( $redirect_url );
            exit;
        }

        // Renovar assinatura
        if ( isset( $_GET['dps_renew'] ) && isset( $_GET['id'] ) ) {
            $sub_id = absint( $_GET['id'] );
            // Verificar nonce para proteção CSRF
            $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
            if ( ! wp_verify_nonce( $nonce, 'dps_renew_subscription_' . $sub_id ) ) {
                wp_die( esc_html__( 'Ação não autorizada. Link expirado ou inválido.', 'dps-subscription-addon' ) );
            }
            // Valida que é uma assinatura válida
            $subscription = get_post( $sub_id );
            if ( $sub_id && $subscription && 'dps_subscription' === $subscription->post_type ) {
                $this->renew_subscription( $sub_id );
            }
            $base_url = DPS_URL_Builder::safe_get_permalink();
            // Remove o parâmetro de renovação para evitar redirecionamentos em loop
            $redirect_url = remove_query_arg( [ 'dps_renew', 'id', '_wpnonce' ], $base_url );
            $redirect_url = add_query_arg( [ 'tab' => 'assinaturas' ], $redirect_url );
            wp_safe_redirect( $redirect_url );
            exit;
        }

        // Excluir todos os agendamentos vinculados a uma assinatura
        if ( isset( $_GET['dps_delete_appts'] ) && isset( $_GET['id'] ) ) {
            $sub_id = absint( $_GET['id'] );
            // Verificar nonce para proteção CSRF
            $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
            if ( ! wp_verify_nonce( $nonce, 'dps_delete_appts_subscription_' . $sub_id ) ) {
                wp_die( esc_html__( 'Ação não autorizada. Link expirado ou inválido.', 'dps-subscription-addon' ) );
            }
            // Valida que é uma assinatura válida
            $subscription = get_post( $sub_id );
            if ( $sub_id && $subscription && 'dps_subscription' === $subscription->post_type ) {
                $this->delete_all_appointments( $sub_id );
            }
            $base_url = DPS_URL_Builder::safe_get_permalink();
            $redirect_url = remove_query_arg( [ 'dps_delete_appts', 'id', '_wpnonce' ], $base_url );
            $redirect_url = add_query_arg( [ 'tab' => 'assinaturas' ], $redirect_url );
            wp_safe_redirect( $redirect_url );
            exit;
        }

        // Atualizar status de pagamento
        if ( isset( $_POST['dps_update_payment'] ) && isset( $_POST['subscription_id'] ) ) {
            $sub_id = absint( $_POST['subscription_id'] );
            // Verificar nonce para proteção CSRF
            $nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
            if ( ! wp_verify_nonce( $nonce, 'dps_update_payment_' . $sub_id ) ) {
                wp_die( esc_html__( 'Ação não autorizada.', 'dps-subscription-addon' ) );
            }
            // Valida que é uma assinatura válida
            $subscription = get_post( $sub_id );
            if ( ! $subscription || 'dps_subscription' !== $subscription->post_type ) {
                wp_die( esc_html__( 'Assinatura inválida.', 'dps-subscription-addon' ) );
            }
            // Valida status permitido
            $allowed_statuses = [ 'pendente', 'pago', 'em_atraso' ];
            $status = isset( $_POST['payment_status'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_status'] ) ) : '';
            if ( ! in_array( $status, $allowed_statuses, true ) ) {
                $status = 'pendente';
            }
            update_post_meta( $sub_id, 'subscription_payment_status', $status );
            // Se há primeira consulta deste ciclo, atualiza status do primeiro agendamento correspondente
            $this->update_first_appointment_status( $sub_id, $status );
            // Atualiza o status da transação financeira relacionada
            $cycle_key = $this->get_cycle_key( get_post_meta( $sub_id, 'subscription_start_date', true ) );
            $this->mark_cycle_status( $sub_id, $cycle_key, $status );
            $this->create_or_update_finance_record( $sub_id, $cycle_key );
            // Redireciona sem parametros
            $base_url = DPS_URL_Builder::safe_get_permalink();
            wp_safe_redirect( add_query_arg( [ 'tab' => 'assinaturas' ], $base_url ) );
            exit;
        }
    }

    /**
     * Salva ou atualiza uma assinatura
     */
    private function save_subscription() {
        // Sanitiza e valida todos os campos de entrada
        $client_id    = isset( $_POST['subscription_client_id'] ) ? absint( $_POST['subscription_client_id'] ) : 0;
        
        // Suporta múltiplos pets (novo) ou pet único (legado)
        $pet_ids_raw  = isset( $_POST['subscription_pet_ids'] ) ? (array) wp_unslash( $_POST['subscription_pet_ids'] ) : [];
        $pet_ids      = array_filter( array_map( 'absint', $pet_ids_raw ) );
        
        // Fallback para campo de pet único (compatibilidade com formulário legado)
        if ( empty( $pet_ids ) && isset( $_POST['subscription_pet_id'] ) ) {
            $pet_id_single = absint( $_POST['subscription_pet_id'] );
            if ( $pet_id_single ) {
                $pet_ids = [ $pet_id_single ];
            }
        }
        
        $service      = isset( $_POST['subscription_service'] ) ? sanitize_text_field( wp_unslash( $_POST['subscription_service'] ) ) : '';
        $frequency    = isset( $_POST['subscription_frequency'] ) ? sanitize_text_field( wp_unslash( $_POST['subscription_frequency'] ) ) : '';
        $price_raw    = isset( $_POST['subscription_price'] ) ? sanitize_text_field( wp_unslash( $_POST['subscription_price'] ) ) : '0';
        $price        = floatval( str_replace( ',', '.', $price_raw ) );
        $start_date   = isset( $_POST['subscription_start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['subscription_start_date'] ) ) : '';
        $start_time   = isset( $_POST['subscription_start_time'] ) ? sanitize_text_field( wp_unslash( $_POST['subscription_start_time'] ) ) : '';
        $notes        = isset( $_POST['subscription_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['subscription_notes'] ) ) : '';
        $assignee     = isset( $_POST['subscription_assignee'] ) ? sanitize_text_field( wp_unslash( $_POST['subscription_assignee'] ) ) : '';
        $extras_desc  = isset( $_POST['subscription_extras_descriptions'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['subscription_extras_descriptions'] ) ) : [];
        $extras_val   = isset( $_POST['subscription_extras_values'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['subscription_extras_values'] ) ) : [];
        $extras_list  = $this->parse_extras_from_request( $extras_desc, $extras_val );
        
        // Campos adicionais de tosa (compatibilidade com criação via plugin base)
        $tosa            = isset( $_POST['subscription_tosa'] ) ? sanitize_text_field( wp_unslash( $_POST['subscription_tosa'] ) ) : '';
        $tosa_price_raw  = isset( $_POST['subscription_tosa_price'] ) ? sanitize_text_field( wp_unslash( $_POST['subscription_tosa_price'] ) ) : '0';
        $tosa_price      = floatval( str_replace( ',', '.', $tosa_price_raw ) );
        $tosa_occurrence = isset( $_POST['subscription_tosa_occurrence'] ) ? absint( $_POST['subscription_tosa_occurrence'] ) : 0;
        
        // Valida campos obrigatórios com feedback ao usuário
        if ( ! $client_id || empty( $pet_ids ) || ! $service || ! $frequency || ! $start_date || ! $start_time ) {
            $this->add_user_message( __( 'Por favor, preencha todos os campos obrigatórios.', 'dps-subscription-addon' ), 'error' );
            return;
        }
        
        // Valida frequência permitida
        $allowed_frequencies = [ 'semanal', 'quinzenal' ];
        if ( ! in_array( $frequency, $allowed_frequencies, true ) ) {
            $this->add_user_message( __( 'Frequência inválida. Selecione Semanal ou Quinzenal.', 'dps-subscription-addon' ), 'error' );
            return;
        }
        
        // Valida formato de data (Y-m-d)
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start_date ) ) {
            $this->add_user_message( __( 'Formato de data inválido. Use o formato AAAA-MM-DD.', 'dps-subscription-addon' ), 'error' );
            return;
        }
        
        // Valida que a data é válida
        $date_parts = explode( '-', $start_date );
        if ( ! checkdate( (int) $date_parts[1], (int) $date_parts[2], (int) $date_parts[0] ) ) {
            $this->add_user_message( __( 'Data inválida. Verifique o dia, mês e ano.', 'dps-subscription-addon' ), 'error' );
            return;
        }
        
        // Valida formato de horário (H:i)
        if ( ! preg_match( '/^\d{2}:\d{2}$/', $start_time ) ) {
            $this->add_user_message( __( 'Formato de horário inválido. Use o formato HH:MM.', 'dps-subscription-addon' ), 'error' );
            return;
        }
        
        // Valida que cliente existe e é do tipo correto
        $client_post = get_post( $client_id );
        if ( ! $client_post || 'dps_cliente' !== $client_post->post_type ) {
            $this->add_user_message( __( 'Cliente selecionado não existe ou é inválido.', 'dps-subscription-addon' ), 'error' );
            return;
        }
        
        // Valida que todos os pets existem e são do tipo correto
        foreach ( $pet_ids as $pet_id ) {
            $pet_post = get_post( $pet_id );
            if ( ! $pet_post || 'dps_pet' !== $pet_post->post_type ) {
                $this->add_user_message( __( 'Um ou mais pets selecionados não existem ou são inválidos.', 'dps-subscription-addon' ), 'error' );
                return;
            }
        }
        
        // Valida preço positivo
        if ( $price < 0 ) {
            $price = 0;
        }
        
        $sub_id = isset( $_POST['subscription_id'] ) ? absint( $_POST['subscription_id'] ) : 0;
        
        // Se editando, valida que a assinatura existe
        if ( $sub_id ) {
            $existing = get_post( $sub_id );
            if ( ! $existing || 'dps_subscription' !== $existing->post_type ) {
                $this->add_user_message( __( 'Assinatura não encontrada para edição.', 'dps-subscription-addon' ), 'error' );
                return;
            }
        }
        
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
        if ( ! $sub_id || is_wp_error( $sub_id ) ) {
            return;
        }
        
        // Salva metadados da assinatura
        update_post_meta( $sub_id, 'subscription_client_id', $client_id );
        // Legado: primeiro pet (protegido contra array vazio, já validado acima mas reforçado)
        $first_pet_id = reset( $pet_ids );
        if ( $first_pet_id ) {
            update_post_meta( $sub_id, 'subscription_pet_id', $first_pet_id );
        }
        update_post_meta( $sub_id, 'subscription_pet_ids', $pet_ids );          // Novo: todos os pets
        update_post_meta( $sub_id, 'subscription_service', $service );
        update_post_meta( $sub_id, 'subscription_frequency', $frequency );
        update_post_meta( $sub_id, 'subscription_price', $price );
        update_post_meta( $sub_id, 'subscription_start_date', $start_date );
        update_post_meta( $sub_id, 'subscription_start_time', $start_time );
        
        // Salva campos de tosa (compatibilidade com plugin base)
        if ( $tosa ) {
            update_post_meta( $sub_id, 'subscription_tosa', $tosa );
            update_post_meta( $sub_id, 'subscription_tosa_price', $tosa_price );
            update_post_meta( $sub_id, 'subscription_tosa_occurrence', $tosa_occurrence );
        } else {
            delete_post_meta( $sub_id, 'subscription_tosa' );
            delete_post_meta( $sub_id, 'subscription_tosa_price' );
            delete_post_meta( $sub_id, 'subscription_tosa_occurrence' );
        }
        
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
        
        // Mensagem de sucesso
        $this->add_user_message( __( 'Assinatura salva com sucesso!', 'dps-subscription-addon' ), 'success' );
    }

    /**
     * Gera os agendamentos para uma assinatura para o mês da data inicial.
     * Suporta múltiplos pets (subscription_pet_ids) criando agendamentos
     * para cada pet em cada data do ciclo.
     *
     * @param int    $sub_id     ID da assinatura
     * @param string $date_start Data inicial (Y-m-d)
     * @param string $cycle_key  Ciclo (Y-m) calculado previamente
     */
    private function generate_monthly_appointments( $sub_id, $date_start, $cycle_key = '' ) {
        $cycle_key = $cycle_key ? $cycle_key : $this->get_cycle_key( $date_start );
        if ( ! $cycle_key || $this->has_generated_cycle( $sub_id, $cycle_key ) ) {
            return;
        }
        
        // Suporta múltiplos pets (novo) ou pet único (legado)
        $pet_ids_raw = get_post_meta( $sub_id, 'subscription_pet_ids', true );
        $pet_id_single = get_post_meta( $sub_id, 'subscription_pet_id', true );
        
        if ( ! empty( $pet_ids_raw ) && is_array( $pet_ids_raw ) ) {
            $pet_ids = array_map( 'absint', $pet_ids_raw );
        } elseif ( $pet_id_single ) {
            $pet_ids = [ absint( $pet_id_single ) ];
        } else {
            $pet_ids = [];
        }
        
        $client_id     = get_post_meta( $sub_id, 'subscription_client_id', true );
        $frequency     = get_post_meta( $sub_id, 'subscription_frequency', true );
        $service       = get_post_meta( $sub_id, 'subscription_service', true );
        $start_time    = get_post_meta( $sub_id, 'subscription_start_time', true );
        $notes         = get_post_meta( $sub_id, 'subscription_notes', true );
        $extras        = get_post_meta( $sub_id, 'subscription_extras_list', true );
        
        // Campos de tosa (compatibilidade com plugin base)
        $tosa            = get_post_meta( $sub_id, 'subscription_tosa', true );
        $tosa_price      = (float) get_post_meta( $sub_id, 'subscription_tosa_price', true );
        $tosa_occurrence = absint( get_post_meta( $sub_id, 'subscription_tosa_occurrence', true ) );
        
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
        
        if ( empty( $pet_ids ) || ! $client_id || ! $frequency || ! $date_start ) {
            return;
        }
        
        // Parse date
        try {
            $start_dt = new DateTime( $date_start );
        } catch ( Exception $e ) {
            return;
        }
        
        // Determine increment in days
        $interval_days = ( $frequency === 'quinzenal' ) ? 14 : 7;
        
        /*
         * A assinatura gera um número fixo de atendimentos por ciclo: 4 para semanal e 2 para quinzenal.
         * Para cada pet, geramos a quantidade necessária de datas a partir da data inicial.
         */
        $dates = [];
        $count = ( $frequency === 'quinzenal' ) ? 2 : 4;
        $current_dt = clone $start_dt;
        for ( $i = 0; $i < $count; $i++ ) {
            $dates[] = $current_dt->format( 'Y-m-d' );
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
        
        // Valida tosa_occurrence dentro do range esperado
        $max_occurrence = ( $frequency === 'quinzenal' ) ? 2 : 4;
        if ( $tosa_occurrence < 1 || $tosa_occurrence > $max_occurrence ) {
            $tosa_occurrence = 1; // Default para primeiro atendimento se inválido
        }
        
        // Cria agendamentos para CADA PET em CADA DATA do ciclo
        foreach ( $pet_ids as $pet_id ) {
            $event_index = 0;
            foreach ( $dates as $date ) {
                $event_index++;
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
                    update_post_meta( $appt_id, 'appointment_type', 'subscription' );
                    update_post_meta( $appt_id, 'subscription_id', $sub_id );
                    update_post_meta( $appt_id, 'subscription_cycle', $cycle_key );
                    
                    // Tosa: verifica se este evento é o de tosa (dentro do range validado)
                    $is_tosa_event = ( '1' === $tosa && $event_index === $tosa_occurrence );
                    update_post_meta( $appt_id, 'appointment_tosa', $is_tosa_event ? '1' : '0' );
                    update_post_meta( $appt_id, 'appointment_tosa_price', $is_tosa_event ? $tosa_price : 0 );
                    update_post_meta( $appt_id, 'appointment_tosa_occurrence', $tosa_occurrence );
                    
                    // Nota do agendamento
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
        
        // Verifica se a tabela existe antes de operações SQL
        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( ! $table_exists ) {
            return;
        }
        
        // Prepara valores
        $date_db   = $cycle_key ? sanitize_text_field( $cycle_key ) . '-01' : $start_date; // armazenamos somente a data (YYYY-mm-dd)
        $status_map = [
            'pago'      => 'pago',
            'em_atraso' => 'em_atraso',
            'pendente'  => 'em_aberto',
        ];
        $status    = $status_map[ $pay_status ] ?? 'em_aberto';
        $category  = __( 'Assinatura', 'dps-subscription-addon' );
        $type      = 'receita';
        $desc      = sprintf( __( 'Assinatura: %s (%s)', 'dps-subscription-addon' ), sanitize_text_field( $service ), sanitize_text_field( $freq ) );
        // Verifica se já existe transação para este plano e data
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table usa $wpdb->prefix seguro
        $existing_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE plano_id = %d AND data = %s", $sub_id, $date_db ) );
        if ( $existing_id ) {
            // Atualiza valores principais e status
            $wpdb->update(
                $table,
                [
                    'cliente_id' => $client_id ? absint( $client_id ) : null,
                    'valor'      => (float) $price,
                    'status'     => $status,
                    'categoria'  => $category,
                    'tipo'       => $type,
                    'descricao'  => $desc,
                ],
                [ 'id' => $existing_id ],
                [ '%d', '%f', '%s', '%s', '%s', '%s' ],
                [ '%d' ]
            );
        } else {
            // Insere nova transação
            $wpdb->insert(
                $table,
                [
                    'cliente_id'     => $client_id ? absint( $client_id ) : null,
                    'agendamento_id' => null,
                    'plano_id'       => $sub_id,
                    'data'           => $date_db,
                    'valor'          => (float) $price,
                    'categoria'      => $category,
                    'tipo'           => $type,
                    'status'         => $status,
                    'descricao'      => $desc,
                ],
                [ '%d', '%d', '%d', '%s', '%f', '%s', '%s', '%s', '%s' ]
            );
        }
    }

    /**
     * Integração de status de pagamento proveniente de um gateway externo.
     * Use a ação dps_subscription_payment_status para informar o resultado do pagamento
     * do ciclo atual. Exemplo de uso: do_action( 'dps_subscription_payment_status', $sub_id, '2025-11', 'paid' );
     *
     * NOTA DE SEGURANÇA: Este hook é chamado por integrações de gateway de pagamento.
     * A validação de autenticidade deve ser feita pelo add-on de pagamentos antes de disparar este hook.
     * Este método valida apenas a existência da assinatura e o formato dos parâmetros.
     *
     * @param int    $sub_id         ID da assinatura.
     * @param string $cycle_key      Ciclo (Y-m). Se vazio, usa o ciclo da data de início.
     * @param string $payment_status Status recebido do gateway (paid|failed|pending).
     */
    public function handle_subscription_payment_status( $sub_id, $cycle_key = '', $payment_status = '' ) {
        $sub_id = absint( $sub_id );
        if ( ! $sub_id ) {
            return;
        }
        
        // Valida que a assinatura existe e é do tipo correto
        $subscription = get_post( $sub_id );
        if ( ! $subscription || 'dps_subscription' !== $subscription->post_type ) {
            return;
        }
        
        $start_date = get_post_meta( $sub_id, 'subscription_start_date', true );
        $cycle_key  = $cycle_key ? sanitize_text_field( $cycle_key ) : $this->get_cycle_key( $start_date );
        if ( ! $cycle_key ) {
            return;
        }
        
        // Valida formato do cycle_key (deve ser Y-m)
        if ( ! preg_match( '/^\d{4}-\d{2}$/', $cycle_key ) ) {
            return;
        }

        $normalized = strtolower( sanitize_text_field( (string) $payment_status ) );
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
        
        // Verifica se a tabela existe antes de operações SQL
        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( ! $table_exists ) {
            return;
        }
        
        // Obtém todas as transações para este plano e remove também opções de documento
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table usa $wpdb->prefix seguro
        $trans_rows = $wpdb->get_results( $wpdb->prepare( "SELECT id FROM {$table} WHERE plano_id = %d", $sub_id ) );
        if ( $trans_rows ) {
            // Define o diretório de uploads como base segura para exclusão de arquivos
            $upload_dir = wp_upload_dir();
            $base_path  = realpath( $upload_dir['basedir'] );
            
            foreach ( $trans_rows as $row ) {
                // Apaga arquivo de documento associado, se existir
                $opt_key = 'dps_fin_doc_' . absint( $row->id );
                $doc_url = get_option( $opt_key );
                if ( $doc_url ) {
                    delete_option( $opt_key );
                    // Remove arquivo físico com validação de path traversal
                    // Converte URL para caminho absoluto e valida que está dentro do diretório de uploads
                    $file_path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $doc_url );
                    $real_path = realpath( $file_path );
                    // Segurança: só exclui se o arquivo está dentro do diretório de uploads
                    if ( $real_path && $base_path && strpos( $real_path, $base_path ) === 0 && file_exists( $real_path ) ) {
                        wp_delete_file( $real_path );
                    }
                }
            }
        }
        // Exclui as transações do banco
        $wpdb->delete( $table, [ 'plano_id' => $sub_id ], [ '%d' ] );
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
            // Carrega todos os metadados da assinatura, incluindo campos de múltiplos pets
            $pet_ids_raw = get_post_meta( $edit_id, 'subscription_pet_ids', true );
            $pet_id_single = get_post_meta( $edit_id, 'subscription_pet_id', true );
            
            // Suporta ambos os formatos: array de IDs (novo) e ID único (legado)
            if ( ! empty( $pet_ids_raw ) && is_array( $pet_ids_raw ) ) {
                $pet_ids = array_map( 'absint', $pet_ids_raw );
            } elseif ( $pet_id_single ) {
                $pet_ids = [ absint( $pet_id_single ) ];
            } else {
                $pet_ids = [];
            }
            
            $meta = [
                'client_id'       => get_post_meta( $edit_id, 'subscription_client_id', true ),
                'pet_id'          => $pet_id_single, // Legado para compatibilidade
                'pet_ids'         => $pet_ids,       // Novo: array de pet IDs
                'service'         => get_post_meta( $edit_id, 'subscription_service', true ),
                'frequency'       => get_post_meta( $edit_id, 'subscription_frequency', true ),
                'price'           => get_post_meta( $edit_id, 'subscription_price', true ),
                'start_date'      => get_post_meta( $edit_id, 'subscription_start_date', true ),
                'start_time'      => get_post_meta( $edit_id, 'subscription_start_time', true ),
                'notes'           => get_post_meta( $edit_id, 'subscription_notes', true ),
                'assignee'        => get_post_meta( $edit_id, 'subscription_assignee', true ),
                'extras'          => get_post_meta( $edit_id, 'subscription_extras_list', true ),
                // Campos adicionais de tosa (compatibilidade com criação via plugin base)
                'tosa'            => get_post_meta( $edit_id, 'subscription_tosa', true ),
                'tosa_price'      => get_post_meta( $edit_id, 'subscription_tosa_price', true ),
                'tosa_occurrence' => get_post_meta( $edit_id, 'subscription_tosa_occurrence', true ),
                // Campos de valores (compatibilidade com criação via plugin base)
                'base_value'      => get_post_meta( $edit_id, 'subscription_base_value', true ),
                'total_value'     => get_post_meta( $edit_id, 'subscription_total_value', true ),
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
        $base_url = DPS_URL_Builder::safe_get_permalink();
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
            echo '<strong class="dps-inline-stats__value">' . esc_html( DPS_Money_Helper::format_currency_from_decimal( $monthly_revenue ) ) . '</strong>';
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
                
                // Suporta múltiplos pets (subscription_pet_ids) ou único (subscription_pet_id)
                $pet_ids_raw = get_post_meta( $sub->ID, 'subscription_pet_ids', true );
                $pet_id_single = get_post_meta( $sub->ID, 'subscription_pet_id', true );
                
                if ( ! empty( $pet_ids_raw ) && is_array( $pet_ids_raw ) ) {
                    $pet_ids = array_map( 'absint', $pet_ids_raw );
                } elseif ( $pet_id_single ) {
                    $pet_ids = [ absint( $pet_id_single ) ];
                } else {
                    $pet_ids = [];
                }
                
                $srv   = get_post_meta( $sub->ID, 'subscription_service', true );
                $freq  = get_post_meta( $sub->ID, 'subscription_frequency', true );
                $price = get_post_meta( $sub->ID, 'subscription_price', true );
                $sdate = get_post_meta( $sub->ID, 'subscription_start_date', true );
                $stime = get_post_meta( $sub->ID, 'subscription_start_time', true );
                $pay   = get_post_meta( $sub->ID, 'subscription_payment_status', true );
                $client_post = $cid ? get_post( $cid ) : null;
                
                // Coleta nomes de todos os pets
                $pet_names = [];
                foreach ( $pet_ids as $pid ) {
                    $pet_post = get_post( $pid );
                    if ( $pet_post ) {
                        $pet_names[] = $pet_post->post_title;
                    }
                }
                
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
                
                // Cliente / Pets (combinados para reduzir colunas)
                $client_name = $client_post ? $client_post->post_title : '—';
                $pets_display = ! empty( $pet_names ) ? implode( ', ', $pet_names ) : '—';
                $pet_count = count( $pet_names );
                $pet_icon = $pet_count > 1 ? '🐾🐾' : '🐾';
                echo '<td data-label="' . $lbl_cliente . '"><strong>' . esc_html( $client_name ) . '</strong><br><span class="dps-text-secondary">' . $pet_icon . ' ' . esc_html( $pets_display ) . '</span></td>';
                
                // Serviço
                echo '<td data-label="' . $lbl_servico . '">' . esc_html( $srv ) . '</td>';
                
                // Frequência
                echo '<td data-label="' . $lbl_frequencia . '">' . esc_html( $freq_options[ $freq ] ?? $freq ) . '</td>';
                
                // Valor
                echo '<td data-label="' . $lbl_valor . '"><strong>' . esc_html( DPS_Money_Helper::format_currency_from_decimal( (float) $price ) ) . '</strong></td>';
                
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
                
                // O botão Renovar fica sempre visível, mas só habilitado quando todos os atendimentos foram concluídos
                $all_completed = ( $completed_count >= $total_appts && $total_appts > 0 );
                $renew_url = wp_nonce_url(
                    add_query_arg( [ 'tab' => 'assinaturas', 'dps_renew' => '1', 'id' => $sub->ID ], $base_url ),
                    'dps_renew_subscription_' . $sub->ID
                );
                
                if ( $all_completed ) {
                    // Botão habilitado - todos os atendimentos concluídos
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
                } else {
                    // Botão desabilitado - aguardando conclusão de todos os atendimentos
                    $remaining = $total_appts - $completed_count;
                    $tooltip = sprintf(
                        /* translators: %d: number of remaining appointments */
                        _n( 'Aguarde a conclusão de %d atendimento restante para renovar', 'Aguarde a conclusão de %d atendimentos restantes para renovar', $remaining, 'dps-subscription-addon' ),
                        $remaining
                    );
                    echo '<span class="dps-action-btn dps-action-renew dps-action-disabled" title="' . esc_attr( $tooltip ) . '">🔄</span>';
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
                
                // Suporta múltiplos pets (subscription_pet_ids) ou único (subscription_pet_id)
                $pet_ids_raw = get_post_meta( $sub->ID, 'subscription_pet_ids', true );
                $pet_id_single = get_post_meta( $sub->ID, 'subscription_pet_id', true );
                
                if ( ! empty( $pet_ids_raw ) && is_array( $pet_ids_raw ) ) {
                    $pet_ids = array_map( 'absint', $pet_ids_raw );
                } elseif ( $pet_id_single ) {
                    $pet_ids = [ absint( $pet_id_single ) ];
                } else {
                    $pet_ids = [];
                }
                
                $srv   = get_post_meta( $sub->ID, 'subscription_service', true );
                $freq  = get_post_meta( $sub->ID, 'subscription_frequency', true );
                $price = get_post_meta( $sub->ID, 'subscription_price', true );
                $pay   = get_post_meta( $sub->ID, 'subscription_payment_status', true );
                $client_post = $cid ? get_post( $cid ) : null;
                
                // Coleta nomes de todos os pets
                $pet_names = [];
                foreach ( $pet_ids as $pid ) {
                    $pet_post = get_post( $pid );
                    if ( $pet_post ) {
                        $pet_names[] = $pet_post->post_title;
                    }
                }
                
                echo '<tr class="dps-canceled-row">';
                
                // Cliente / Pets
                $client_name = $client_post ? $client_post->post_title : '—';
                $pets_display = ! empty( $pet_names ) ? implode( ', ', $pet_names ) : '—';
                $pet_count = count( $pet_names );
                $pet_icon = $pet_count > 1 ? '🐾🐾' : '🐾';
                echo '<td data-label="' . $lbl_cliente_pet . '"><strong>' . esc_html( $client_name ) . '</strong><br><span class="dps-text-secondary">' . $pet_icon . ' ' . esc_html( $pets_display ) . '</span></td>';
                
                // Serviço + Frequência
                echo '<td data-label="' . $lbl_servico . '">' . esc_html( $srv ) . ' <span class="dps-text-muted">(' . esc_html( $freq_options[ $freq ] ?? $freq ) . ')</span></td>';
                
                // Valor
                echo '<td data-label="' . $lbl_valor . '">' . esc_html( DPS_Money_Helper::format_currency_from_decimal( (float) $price ) ) . '</td>';
                
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

        // Fieldset: Cliente e Pets
        // Obtém IDs de pets selecionados (suporta múltiplos)
        $selected_pet_ids = $meta['pet_ids'] ?? [];
        if ( empty( $selected_pet_ids ) && ! empty( $meta['pet_id'] ) ) {
            $selected_pet_ids = [ absint( $meta['pet_id'] ) ];
        }
        
        echo '<fieldset class="dps-fieldset">';
        echo '<legend>' . esc_html__( 'Cliente e Pets', 'dps-subscription-addon' ) . '</legend>';
        
        // Cliente
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
        
        // Pets (checkboxes para seleção múltipla)
        echo '<div class="dps-form-field">';
        echo '<label>' . esc_html__( 'Pets', 'dps-subscription-addon' ) . ' <span class="dps-required">*</span></label>';
        echo '<p class="dps-field-hint">' . esc_html__( 'Selecione um ou mais pets para esta assinatura.', 'dps-subscription-addon' ) . '</p>';
        echo '<div id="dps-subscription-pets-list" class="dps-checkbox-grid">';
        foreach ( $pets as $pet ) {
            $owner_id = get_post_meta( $pet->ID, 'owner_id', true );
            $is_checked = in_array( (int) $pet->ID, array_map( 'intval', $selected_pet_ids ), true ) ? 'checked' : '';
            // Oculta pets de outros clientes via data-owner
            echo '<label class="dps-checkbox-item" data-owner="' . esc_attr( $owner_id ) . '">';
            echo '<input type="checkbox" name="subscription_pet_ids[]" value="' . esc_attr( $pet->ID ) . '" ' . $is_checked . '>';
            echo '<span class="dps-checkbox-label">🐾 ' . esc_html( $pet->post_title ) . '</span>';
            echo '</label>';
        }
        echo '</div>';
        echo '<p class="dps-pets-count"><span id="dps-selected-pets-count">' . count( $selected_pet_ids ) . '</span> ' . esc_html__( 'pet(s) selecionado(s)', 'dps-subscription-addon' ) . '</p>';
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

        // Seção de Tosa (compatibilidade com criação via plugin base)
        $tosa_val = $meta['tosa'] ?? '';
        $tosa_price_val = $meta['tosa_price'] ?? '';
        $tosa_occurrence_val = $meta['tosa_occurrence'] ?? '';
        $freq_val = $meta['frequency'] ?? 'semanal';
        $max_occurrence = ( 'quinzenal' === $freq_val ) ? 2 : 4;
        
        echo '<div class="dps-tosa-section">';
        echo '<h5 class="dps-extras-title">' . esc_html__( 'Tosa opcional', 'dps-subscription-addon' ) . '</h5>';
        echo '<div class="dps-form-row dps-form-row--3col">';
        
        // Checkbox de tosa
        echo '<div class="dps-form-field">';
        echo '<label class="dps-checkbox-inline">';
        echo '<input type="checkbox" name="subscription_tosa" value="1" ' . checked( '1', $tosa_val, false ) . ' id="subscription_tosa" onchange="DPSSubscription.toggleTosaFields()">';
        echo '<span>' . esc_html__( 'Incluir tosa', 'dps-subscription-addon' ) . '</span>';
        echo '</label>';
        echo '</div>';
        
        // Valor da tosa
        echo '<div class="dps-form-field dps-tosa-conditional" ' . ( '1' !== $tosa_val ? 'style="display:none;"' : '' ) . '>';
        echo '<label for="subscription_tosa_price">' . esc_html__( 'Valor da tosa', 'dps-subscription-addon' ) . '</label>';
        echo '<div class="dps-input-with-prefix">';
        echo '<span class="dps-input-prefix">R$</span>';
        echo '<input type="number" step="0.01" min="0" name="subscription_tosa_price" id="subscription_tosa_price" value="' . esc_attr( $tosa_price_val ) . '" placeholder="0,00">';
        echo '</div>';
        echo '</div>';
        
        // Ocorrência da tosa
        echo '<div class="dps-form-field dps-tosa-conditional" ' . ( '1' !== $tosa_val ? 'style="display:none;"' : '' ) . '>';
        echo '<label for="subscription_tosa_occurrence">' . esc_html__( 'Em qual atendimento?', 'dps-subscription-addon' ) . '</label>';
        echo '<select name="subscription_tosa_occurrence" id="subscription_tosa_occurrence">';
        for ( $i = 1; $i <= $max_occurrence; $i++ ) {
            $sel = ( (int) $tosa_occurrence_val === $i ) ? 'selected' : '';
            echo '<option value="' . esc_attr( $i ) . '" ' . $sel . '>' . esc_html( $i ) . 'º</option>';
        }
        echo '</select>';
        echo '</div>';
        
        echo '</div>'; // .dps-form-row
        echo '</div>'; // .dps-tosa-section

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
