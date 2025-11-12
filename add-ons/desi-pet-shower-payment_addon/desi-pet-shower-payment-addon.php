<?php
/**
 * Plugin Name:       Desi Pet Shower – Pagamentos Add-on
 * Plugin URI:        https://probst.pro/desi-pet-shower
 * Description:       Gera links de pagamento via Mercado Pago para atender clientes de forma prática e envia por WhatsApp. Cria a URL de checkout e adiciona ao link de cobrança para atendimentos finalizados.
 * Version:           1.0.0
 * Author:            PRObst
 * Author URI:        https://probst.pro
 * Text Domain:       dps-payment-addon
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * License:           GPL-2.0+
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe principal do add-on de pagamentos.
 */
class DPS_Payment_Addon {

    /**
     * Construtor. Registra hooks necessários.
     */
    public function __construct() {
        // Gera link de pagamento sempre que um agendamento é salvo.
        // A ação dps_base_after_save_appointment é disparada pelo plugin base
        // imediatamente após salvar um agendamento. Utilizamos prioridade 10 (padrão)
        // para garantir que metadados como appointment_total_value já estejam disponíveis.
        add_action( 'dps_base_after_save_appointment', [ $this, 'maybe_generate_payment_link' ], 10, 2 );
        // Adiciona link de pagamento na mensagem de WhatsApp com alta prioridade
        // A prioridade 999 garante que nosso filtro seja aplicado após outros filtros,
        // evitando duplicidade de mensagens.
        add_filter( 'dps_agenda_whatsapp_message', [ $this, 'inject_payment_link_in_message' ], 999, 3 );
        // Registra nossas opções na área de administração.
        add_action( 'admin_init', [ $this, 'register_settings' ] );

        // Adiciona página de configurações no painel para definir o Access Token do Mercado Pago
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );

        // Registra seção e campos das configurações. Também registra o manipulador do webhook
        add_action( 'admin_init', [ $this, 'register_settings_fields' ] );

        // Registra o manipulador de notificação do Mercado Pago cedo no ciclo de
        // inicialização. Isso garante que as notificações de pagamento sejam
        // capturadas independentemente de o painel administrativo estar ativo ou não.
        // Usamos prioridade 1 para executar antes de outros handlers que possam
        // consumir a requisição.
        add_action( 'init', [ $this, 'maybe_handle_mp_notification' ], 1 );
    }

    /**
     * Registra uma opção para armazenar o token do Mercado Pago.
     */
    public function register_settings() {
        register_setting( 'dps_payment_options', 'dps_mercadopago_access_token' );
        // Também armazena a chave PIX utilizada nas mensagens de cobrança
        register_setting( 'dps_payment_options', 'dps_pix_key' );
    }

    /**
     * Adiciona uma página de configurações no menu Configurações do WordPress.
     */
    public function add_settings_page() {
        add_options_page(
            __( 'Desi Pet Shower - Pagamentos', 'dps-payment-addon' ),
            __( 'DPS Pagamentos', 'dps-payment-addon' ),
            'manage_options',
            'dps-payment-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    /**
     * Registra seção e campo para o token do Mercado Pago.
     */
    public function register_settings_fields() {
        add_settings_section(
            'dps_payment_section',
            __( 'Configurações do Mercado Pago', 'dps-payment-addon' ),
            null,
            'dps-payment-settings'
        );
        // Campo para Access Token do Mercado Pago
        add_settings_field(
            'dps_mercadopago_access_token',
            __( 'Access Token', 'dps-payment-addon' ),
            [ $this, 'render_access_token_field' ],
            'dps-payment-settings',
            'dps_payment_section'
        );
        // Campo para chave PIX utilizada nas mensagens de cobrança
        add_settings_field(
            'dps_pix_key',
            __( 'Chave PIX', 'dps-payment-addon' ),
            [ $this, 'render_pix_key_field' ],
            'dps-payment-settings',
            'dps_payment_section'
        );
        // Registra as opções para que possam ser salvas
        register_setting( 'dps_payment_options', 'dps_mercadopago_access_token' );
        register_setting( 'dps_payment_options', 'dps_pix_key' );

        // Não registramos o manipulador do webhook aqui. Ele é registrado
        // globalmente no construtor para garantir que as notificações sejam
        // processadas em qualquer contexto.
    }

    /**
     * Renderiza o campo de token de acesso.
     */
    public function render_access_token_field() {
        $token = esc_attr( get_option( 'dps_mercadopago_access_token', '' ) );
        echo '<input type="text" name="dps_mercadopago_access_token" value="' . $token . '" style="width: 400px;" />';
        echo '<p class="description">' . __( 'Cole aqui o Access Token gerado em sua conta do Mercado Pago. Este valor é utilizado para criar links de pagamento automaticamente.', 'dps-payment-addon' ) . '</p>';
    }

    /**
     * Renderiza o campo de configuração da chave PIX.
     * A chave PIX pode ser um telefone, CPF/CNPJ ou chave aleatória. Ela será
     * usada para compor a mensagem de cobrança enviada ao cliente quando o
     * agendamento estiver finalizado. Caso não seja definida, será utilizado
     * um valor padrão configurado no código.
     */
    public function render_pix_key_field() {
        $pix = esc_attr( get_option( 'dps_pix_key', '' ) );
        echo '<input type="text" name="dps_pix_key" value="' . $pix . '" style="width: 400px;" />';
        echo '<p class="description">' . esc_html__( 'Informe sua chave PIX (telefone, CPF ou chave aleatória) para incluir nas mensagens de pagamento.', 'dps-payment-addon' ) . '</p>';
    }

    /**
     * Renderiza a página de configurações.
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Configurações de Pagamentos - Desi Pet Shower', 'dps-payment-addon' ) . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields( 'dps_payment_options' );
        do_settings_sections( 'dps-payment-settings' );
        submit_button();
        echo '</form>';
        echo '</div>';
    }

    /**
     * Verifica condições e gera link de pagamento para agendamentos finalizados.
     *
     * @param int    $appt_id  ID do post de agendamento.
     * @param string $appt_type Tipo de agendamento (simple ou subscription).
     */
    public function maybe_generate_payment_link( $appt_id, $appt_type ) {
        // Recupera status e verifica se é finalizado
        $status = get_post_meta( $appt_id, 'appointment_status', true );
        // Verifica se é assinatura pelo meta subscription_id
        $sub_id = get_post_meta( $appt_id, 'subscription_id', true );
        // Gera link apenas se não for assinatura, status for finalizado e não houver link salvo
        if ( $status === 'finalizado' && empty( $sub_id ) && ! get_post_meta( $appt_id, 'dps_payment_link', true ) ) {
            $total = get_post_meta( $appt_id, 'appointment_total_value', true );
            if ( $total ) {
                // Descrição básica: pode ser customizada conforme necessidade
                $pet_name  = get_post_meta( $appt_id, 'appointment_pet_name', true );
                $service_desc = 'Serviço pet ' . ( $pet_name ? $pet_name : '' );
                // Cria uma preferência de pagamento com referência baseada no ID do agendamento.
                // Esta referência é utilizada para identificar o agendamento quando a
                // notificação de pagamento retornar do Mercado Pago. O formato
                // dps_appointment_{ID} é interpretado em process_payment_notification().
                $reference = 'dps_appointment_' . (int) $appt_id;
                $link = $this->create_payment_preference( $total, $service_desc, $reference );
                if ( $link ) {
                    update_post_meta( $appt_id, 'dps_payment_link', esc_url_raw( $link ) );
                }
            }
        }
    }

    /**
     * Insere o link de pagamento na mensagem do WhatsApp se existir.
     *
     * @param string $message Mensagem original.
     * @param WP_Post $appt   Objeto de agendamento.
     * @param string $context Contexto de uso (agenda ou outro).
     * @return string Mensagem modificada.
     */
    public function inject_payment_link_in_message( $message, $appt, $context ) {
        /*
         * Esta função substitui completamente a mensagem de cobrança gerada pela agenda
         * por um texto único e profissional. Ela garante que não haja duplicação
         * de mensagens ou links, gerando o link de pagamento caso ainda não exista
         * e utilizando um link de fallback caso a integração com o Mercado Pago
         * não esteja configurada. Para assinaturas, a mensagem original é preservada.
         */
        // Somente processa mensagens da agenda e quando há um agendamento válido
        if ( $context !== 'agenda' || ! $appt ) {
            return $message;
        }
        // Não altera mensagens de assinaturas
        $sub_id = get_post_meta( $appt->ID, 'subscription_id', true );
        if ( ! empty( $sub_id ) ) {
            return $message;
        }
        // Recupera status e valor
        $status = get_post_meta( $appt->ID, 'appointment_status', true );
        $total  = get_post_meta( $appt->ID, 'appointment_total_value', true );
        // Apenas processa atendimentos finalizados com valor definido
        if ( $status !== 'finalizado' || ! $total ) {
            return $message;
        }
        // Recupera ou cria link de pagamento
        $payment_link = get_post_meta( $appt->ID, 'dps_payment_link', true );
        if ( empty( $payment_link ) ) {
            $pet_name_meta = get_post_meta( $appt->ID, 'appointment_pet_name', true );
            $desc         = 'Serviço pet ' . ( $pet_name_meta ? $pet_name_meta : '' );
            // Define uma referência baseada no ID do agendamento para rastreamento
            $reference    = 'dps_appointment_' . (int) $appt->ID;
            $generated    = $this->create_payment_preference( $total, $desc, $reference );
            if ( $generated ) {
                $payment_link = $generated;
                update_post_meta( $appt->ID, 'dps_payment_link', esc_url_raw( $payment_link ) );
            }
        }
        // Recupera dados do cliente e pet para personalizar a mensagem
        $client_id    = get_post_meta( $appt->ID, 'appointment_client_id', true );
        $client_post  = $client_id ? get_post( $client_id ) : null;
        $client_name  = $client_post ? $client_post->post_title : '';
        $pet_id       = get_post_meta( $appt->ID, 'appointment_pet_id', true );
        $pet_post     = $pet_id ? get_post( $pet_id ) : null;
        $pet_name     = $pet_post ? $pet_post->post_title : '';
        $valor_fmt    = number_format( (float) $total, 2, ',', '.' );
        // Define link de fallback
        $fallback     = 'https://link.mercadopago.com.br/desipetshower';
        $link_to_use  = $payment_link ? $payment_link : $fallback;
        // Obtém a chave PIX configurada ou utiliza um valor padrão
        $pix_option   = get_option( 'dps_pix_key', '' );
        $pix_display  = $pix_option ? $pix_option : '15 99160‑6299';
        // Monta a mensagem final. Não inclui a mensagem original para evitar duplicações
        $new_message  = sprintf(
            'Olá %s! O serviço do pet %s foi finalizado e o pagamento de R$ %s está pendente. Você pode pagar via PIX (%s) ou pelo link: %s. Obrigado!',
            $client_name,
            $pet_name,
            $valor_fmt,
            $pix_display,
            $link_to_use
        );
        return $new_message;
    }

    /**
     * Cria uma preferência de pagamento no Mercado Pago e retorna a URL de checkout (init_point).
     *
     * @param float  $amount      Valor da cobrança (em reais).
     * @param string $description Descrição do serviço/produto.
     * @return string URL de checkout ou string vazia em caso de erro.
     */
    /**
     * Cria uma preferência de pagamento no Mercado Pago e retorna a URL de checkout (init_point).
     *
     * A referência externa (external_reference) é utilizada para associar o pagamento a um
     * agendamento ou assinatura. Quando fornecida, ela deve seguir o formato
     * "dps_appointment_{ID}" ou "dps_subscription_{ID}" para que o webhook possa
     * localizar o registro correspondente. Caso nenhuma referência seja informada, será
     * gerado um identificador genérico baseado no timestamp.
     *
     * @param float  $amount      Valor da cobrança (em reais).
     * @param string $description Descrição do serviço/produto.
     * @param string $reference   Referência externa opcional (dps_appointment_{id} ou dps_subscription_{id}).
     * @return string URL de checkout ou string vazia em caso de erro.
     */
    private function create_payment_preference( $amount, $description, $reference = '' ) {
        $token = trim( get_option( 'dps_mercadopago_access_token' ) );
        if ( ! $token || ! $amount ) {
            return '';
        }
        // Prepara payload de acordo com a API do Mercado Pago
        $body = [
            'items' => [
                [
                    'title'       => sanitize_text_field( $description ),
                    'quantity'    => 1,
                    'unit_price'  => (float) $amount,
                    'currency_id' => 'BRL',
                ],
            ],
        ];
        // Se uma referência foi informada, inclui no payload para rastreamento. Caso contrário,
        // utiliza um identificador genérico baseado no timestamp.
        if ( $reference ) {
            $body['external_reference'] = sanitize_key( $reference );
        } else {
            $body['external_reference'] = 'dps_payment_' . time();
        }
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
            return '';
        }
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( isset( $data['init_point'] ) ) {
            return esc_url_raw( $data['init_point'] );
        }
        return '';
    }

    /**
     * Verifica se a requisição atual é uma notificação de pagamento do Mercado Pago e,
     * em caso afirmativo, processa o pagamento. Este método aceita tanto
     * notificações via query string (IPN) quanto via POST (webhook). É executado
     * cedo no ciclo de inicialização do WordPress para garantir que a resposta
     * seja devolvida rapidamente.
     */
    public function maybe_handle_mp_notification() {
        // Só processa em contexto público (não no admin) para evitar interferência
        if ( is_admin() ) {
            return;
        }
        $payment_id = '';
        $topic      = '';
        // 1. IPN padrão: ?topic=payment&id=123
        if ( isset( $_GET['topic'] ) && isset( $_GET['id'] ) ) {
            $topic      = sanitize_text_field( wp_unslash( $_GET['topic'] ) );
            $payment_id = sanitize_text_field( wp_unslash( $_GET['id'] ) );
        }
        // 2. Webhook com parâmetros data.topic e data.id
        if ( ! $payment_id && isset( $_GET['data.topic'] ) && isset( $_GET['data.id'] ) ) {
            $topic      = sanitize_text_field( wp_unslash( $_GET['data.topic'] ) );
            $payment_id = sanitize_text_field( wp_unslash( $_GET['data.id'] ) );
        }
        // 3. Webhook via POST JSON
        if ( ! $payment_id && 'POST' === strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
            $raw_body = file_get_contents( 'php://input' );
            $json     = json_decode( $raw_body, true );
            if ( is_array( $json ) ) {
                // Alguns webhooks enviam { "topic": "payment", "id": "123" }
                if ( isset( $json['topic'] ) && isset( $json['id'] ) ) {
                    $topic      = sanitize_text_field( $json['topic'] );
                    $payment_id = sanitize_text_field( (string) $json['id'] );
                }
                // Outros webhooks enviam { "data": { "id": "123" }, "type": "payment" }
                if ( ! $payment_id && isset( $json['data'] ) && isset( $json['data']['id'] ) ) {
                    $payment_id = sanitize_text_field( (string) $json['data']['id'] );
                    // MP pode usar "action" ou "type" para o tópico
                    if ( isset( $json['type'] ) ) {
                        $topic = sanitize_text_field( $json['type'] );
                    } elseif ( isset( $json['action'] ) ) {
                        $topic = sanitize_text_field( $json['action'] );
                    }
                }
            }
        }
        // Se não for pagamento ou não tiver id, ignora
        if ( 'payment' !== strtolower( $topic ) || ! $payment_id ) {
            return;
        }
        // Processa e responde
        $this->process_payment_notification( $payment_id );
        // Responde imediatamente
        if ( ! headers_sent() ) {
            status_header( 200 );
        }
        echo 'OK';
        exit;
    }

    /**
     * Consulta os detalhes do pagamento na API do Mercado Pago e, se aprovado,
     * identifica o agendamento ou assinatura utilizando a external_reference e
     * atualiza o status. Também envia um email de notificação para o administrador.
     *
     * @param string $payment_id ID do pagamento retornado pelo webhook.
     * @return void
     */
    private function process_payment_notification( $payment_id ) {
        $token = trim( get_option( 'dps_mercadopago_access_token' ) );
        if ( ! $token ) {
            return;
        }
        // Consulta a API de pagamentos do Mercado Pago
        $url      = 'https://api.mercadopago.com/v1/payments/' . rawurlencode( $payment_id ) . '?access_token=' . rawurlencode( $token );
        $response = wp_remote_get( $url );
        if ( is_wp_error( $response ) ) {
            return;
        }
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $data ) ) {
            return;
        }
        // Apenas pagamentos aprovados ou sucesso devem alterar o status
        $status = $data['status'] ?? '';
        if ( 'approved' !== $status && 'success' !== $status ) {
            return;
        }
        // O campo external_reference deve estar presente para identificar o registro
        $external_reference = $data['external_reference'] ?? '';
        if ( ! $external_reference ) {
            return;
        }
        // Determina o tipo (agendamento ou assinatura)
        if ( 0 === strpos( $external_reference, 'dps_appointment_' ) ) {
            $parts   = explode( '_', $external_reference );
            // Espera formato dps_appointment_{ID}
            $appt_id = isset( $parts[2] ) ? intval( $parts[2] ) : 0;
            if ( $appt_id ) {
                $this->mark_appointment_paid( $appt_id, $data );
            }
        } elseif ( 0 === strpos( $external_reference, 'dps_subscription_' ) ) {
            $parts  = explode( '_', $external_reference );
            $sub_id = isset( $parts[2] ) ? intval( $parts[2] ) : 0;
            if ( $sub_id ) {
                $this->mark_subscription_paid( $sub_id, $data );
            }
        }
    }

    /**
     * Marca um agendamento como pago e envia um email de notificação.
     * Apenas altera o status se o agendamento estiver marcado como finalizado (pendente).
     *
     * @param int   $appt_id      ID do agendamento.
     * @param array $payment_data Dados completos do pagamento retornado pela API.
     */
    private function mark_appointment_paid( $appt_id, $payment_data ) {
        $current_status = get_post_meta( $appt_id, 'appointment_status', true );
        // Se já estiver pago ou não finalizado, não altera
        // Alguns usuários podem utilizar diferentes rótulos para o status pago ("finalizado e pago", "finalizado_pago").
        // Só interrompe se o status já indica que o agendamento está pago. Caso contrário, prossegue com a marcação.
        if ( in_array( $current_status, [ 'finalizado e pago', 'finalizado_pago' ], true ) ) {
            // Já está marcado como pago; ainda assim podemos garantir que a transação fique com status pago.
        } else {
            // Atualiza o status para a forma padronizada "finalizado_pago".
            update_post_meta( $appt_id, 'appointment_status', 'finalizado_pago' );
        }
        // Registra o status do pagamento no meta para referência futura
        update_post_meta( $appt_id, 'dps_payment_status', $payment_data['status'] ?? '' );
        // Atualiza ou cria a transação associada a este agendamento na tabela customizada para status pago
        global $wpdb;
        $table_name = $wpdb->prefix . 'dps_transacoes';
        // Verifica se já existe transação para este agendamento
        $existing_trans_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table_name} WHERE agendamento_id = %d", $appt_id ) );
        if ( $existing_trans_id ) {
            // Atualiza status para pago
            $wpdb->update( $table_name, [ 'status' => 'pago' ], [ 'id' => $existing_trans_id ], [ '%s' ], [ '%d' ] );
        } else {
            // Não há transação criada (talvez o status não tenha sido alterado manualmente). Cria agora com os detalhes do agendamento.
            $client_id  = get_post_meta( $appt_id, 'appointment_client_id', true );
            // Define a data da transação como a data em que o pagamento foi confirmado, e não a data do atendimento.
            // Assim, a receita aparece corretamente nos relatórios de estatísticas e finanças para o dia do pagamento.
            $date       = current_time( 'Y-m-d' );
            $pet_id     = get_post_meta( $appt_id, 'appointment_pet_id', true );
            $valor_meta = get_post_meta( $appt_id, 'appointment_total_value', true );
            $valor      = $valor_meta ? (float) $valor_meta : 0;
            // Descrição: lista de serviços e pet
            $desc_parts = [];
            $service_ids = get_post_meta( $appt_id, 'appointment_services', true );
            if ( is_array( $service_ids ) && ! empty( $service_ids ) ) {
                foreach ( $service_ids as $sid ) {
                    $srv = get_post( $sid );
                    if ( $srv ) {
                        $desc_parts[] = $srv->post_title;
                    }
                }
            }
            $pet_post = $pet_id ? get_post( $pet_id ) : null;
            if ( $pet_post ) {
                $desc_parts[] = $pet_post->post_title;
            }
            $desc = implode( ' - ', $desc_parts );
            $wpdb->insert( $table_name, [
                'cliente_id'     => $client_id ?: null,
                'agendamento_id' => $appt_id,
                'plano_id'       => null,
                'data'           => $date,
                'valor'          => $valor,
                'categoria'      => __( 'Serviço', 'dps-agenda-addon' ),
                'tipo'           => 'receita',
                'status'         => 'pago',
                'descricao'      => $desc,
            ], [ '%d','%d','%d','%s','%f','%s','%s','%s','%s' ] );
        }
        // Envia notificação por email ao administrador
        $this->send_payment_notification_email( $appt_id, $payment_data );
    }

    /**
     * Marca uma assinatura como paga e atualiza os agendamentos associados.
     * Também envia uma notificação por email. Esta implementação marca todos os
     * agendamentos da assinatura que estejam com status finalizado para
     * finalizado_pago.
     *
     * @param int   $sub_id       ID da assinatura.
     * @param array $payment_data Dados do pagamento.
     */
    private function mark_subscription_paid( $sub_id, $payment_data ) {
        // Atualiza meta de status de pagamento da assinatura
        update_post_meta( $sub_id, 'subscription_payment_status', 'pago' );
        // Recupera agendamentos da assinatura e atualiza status
        $appointments = get_posts( [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [ 'key' => 'subscription_id', 'value' => $sub_id, 'compare' => '=' ],
            ],
        ] );
        if ( $appointments ) {
            foreach ( $appointments as $appt ) {
                $st = get_post_meta( $appt->ID, 'appointment_status', true );
                // Atualiza status para a forma padronizada se ainda não estiver pago
                if ( ! in_array( $st, [ 'finalizado e pago', 'finalizado_pago' ], true ) ) {
                    update_post_meta( $appt->ID, 'appointment_status', 'finalizado_pago' );
                }
                // Atualiza ou cria a transação correspondente para pago
                global $wpdb;
                $table_name = $wpdb->prefix . 'dps_transacoes';
                $trans_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table_name} WHERE agendamento_id = %d", $appt->ID ) );
                if ( $trans_id ) {
                    // Atualiza status para pago e define data como data de pagamento
                    $wpdb->update( $table_name, [ 'status' => 'pago', 'data' => current_time( 'Y-m-d' ) ], [ 'id' => $trans_id ], [ '%s','%s' ], [ '%d' ] );
                } else {
                    // Cria transação se não existir (para garantir que receita apareça nas estatísticas). Usa dados do agendamento.
                    $client_id  = get_post_meta( $appt->ID, 'appointment_client_id', true );
                    $valor_meta = get_post_meta( $appt->ID, 'appointment_total_value', true );
                    $valor      = $valor_meta ? (float) $valor_meta : 0;
                    $pet_id     = get_post_meta( $appt->ID, 'appointment_pet_id', true );
                    $desc_parts = [];
                    $service_ids = get_post_meta( $appt->ID, 'appointment_services', true );
                    if ( is_array( $service_ids ) && ! empty( $service_ids ) ) {
                        foreach ( $service_ids as $sid ) {
                            $srv = get_post( $sid );
                            if ( $srv ) {
                                $desc_parts[] = $srv->post_title;
                            }
                        }
                    }
                    $pet_post = $pet_id ? get_post( $pet_id ) : null;
                    if ( $pet_post ) {
                        $desc_parts[] = $pet_post->post_title;
                    }
                    $desc = implode( ' - ', $desc_parts );
                    $wpdb->insert( $table_name, [
                        'cliente_id'     => $client_id ?: null,
                        'agendamento_id' => $appt->ID,
                        'plano_id'       => null,
                        'data'           => current_time( 'Y-m-d' ),
                        'valor'          => $valor,
                        'categoria'      => __( 'Serviço', 'dps-agenda-addon' ),
                        'tipo'           => 'receita',
                        'status'         => 'pago',
                        'descricao'      => $desc,
                    ], [ '%d','%d','%d','%s','%f','%s','%s','%s','%s' ] );
                }
            }
        }
        // Atualiza ou cria a transação principal da assinatura (plano) para pago
        global $wpdb;
        $table_name = $wpdb->prefix . 'dps_transacoes';
        $plan_trans_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table_name} WHERE plano_id = %d", $sub_id ) );
        if ( $plan_trans_id ) {
            $wpdb->update( $table_name, [ 'status' => 'pago', 'data' => current_time( 'Y-m-d' ) ], [ 'id' => $plan_trans_id ], [ '%s','%s' ], [ '%d' ] );
        } else {
            // Caso não exista transação do plano (pode ocorrer em integrações antigas), cria uma nova.
            $client_id = get_post_meta( $sub_id, 'subscription_client_id', true );
            $price     = get_post_meta( $sub_id, 'subscription_price', true );
            $wpdb->insert( $table_name, [
                'cliente_id'     => $client_id ?: null,
                'agendamento_id' => null,
                'plano_id'       => $sub_id,
                'data'           => current_time( 'Y-m-d' ),
                'valor'          => $price ? (float) $price : 0,
                'categoria'      => __( 'Assinatura', 'dps-agenda-addon' ),
                'tipo'           => 'receita',
                'status'         => 'pago',
                'descricao'      => __( 'Pagamento de assinatura', 'dps-agenda-addon' ),
            ], [ '%d','%d','%d','%s','%f','%s','%s','%s','%s' ] );
        }
        // Envia email de notificação
        $this->send_subscription_payment_notification_email( $sub_id, $payment_data );
    }

    /**
     * Envia uma notificação por email informando que o pagamento de um
     * agendamento foi concluído.
     *
     * @param int   $appt_id      ID do agendamento.
     * @param array $payment_data Dados do pagamento.
     */
    private function send_payment_notification_email( $appt_id, $payment_data ) {
        // Determina o email do administrador. Utiliza o mesmo email configurado
        // para receber relatórios de agendamentos, se existir. Caso contrário,
        // utiliza o admin_email padrão do WordPress.
        $notify_email = get_option( 'dps_agenda_report_email' );
        if ( ! $notify_email || ! is_email( $notify_email ) ) {
            $notify_email = get_option( 'admin_email' );
        }
        if ( ! $notify_email || ! is_email( $notify_email ) ) {
            return;
        }
        // Recupera dados do cliente e do pet
        $client_id   = get_post_meta( $appt_id, 'appointment_client_id', true );
        $client_post = $client_id ? get_post( $client_id ) : null;
        $client_name = $client_post ? $client_post->post_title : '';
        $pet_id      = get_post_meta( $appt_id, 'appointment_pet_id', true );
        $pet_post    = $pet_id ? get_post( $pet_id ) : null;
        $pet_name    = $pet_post ? $pet_post->post_title : '';
        $amount      = get_post_meta( $appt_id, 'appointment_total_value', true );
        $amount_fmt  = $amount ? number_format( (float) $amount, 2, ',', '.' ) : '';
        $subject     = sprintf( 'Pagamento confirmado: Agendamento #%d', $appt_id );
        $message     = sprintf( "O pagamento do agendamento #%d foi confirmado.\n", $appt_id );
        $message    .= $client_name ? sprintf( "Cliente: %s\n", $client_name ) : '';
        $message    .= $pet_name ? sprintf( "Pet: %s\n", $pet_name ) : '';
        $message    .= $amount_fmt ? sprintf( "Valor: R$ %s\n", $amount_fmt ) : '';
        $message    .= sprintf( "Status do pagamento: %s\n", $payment_data['status'] ?? '' );
        $message    .= sprintf( "ID do pagamento no Mercado Pago: %s\n", $payment_data['id'] ?? '' );
        wp_mail( $notify_email, $subject, $message );
    }

    /**
     * Envia uma notificação por email informando que o pagamento de uma
     * assinatura foi concluído.
     *
     * @param int   $sub_id       ID da assinatura.
     * @param array $payment_data Dados do pagamento.
     */
    private function send_subscription_payment_notification_email( $sub_id, $payment_data ) {
        $notify_email = get_option( 'dps_agenda_report_email' );
        if ( ! $notify_email || ! is_email( $notify_email ) ) {
            $notify_email = get_option( 'admin_email' );
        }
        if ( ! $notify_email || ! is_email( $notify_email ) ) {
            return;
        }
        $client_id   = get_post_meta( $sub_id, 'subscription_client_id', true );
        $client_post = $client_id ? get_post( $client_id ) : null;
        $client_name = $client_post ? $client_post->post_title : '';
        $pet_id      = get_post_meta( $sub_id, 'subscription_pet_id', true );
        $pet_post    = $pet_id ? get_post( $pet_id ) : null;
        $pet_name    = $pet_post ? $pet_post->post_title : '';
        $price       = get_post_meta( $sub_id, 'subscription_price', true );
        $price_fmt   = $price ? number_format( (float) $price, 2, ',', '.' ) : '';
        $subject     = sprintf( 'Pagamento confirmado: Assinatura #%d', $sub_id );
        $message     = sprintf( "O pagamento da assinatura #%d foi confirmado.\n", $sub_id );
        $message    .= $client_name ? sprintf( "Cliente: %s\n", $client_name ) : '';
        $message    .= $pet_name ? sprintf( "Pet: %s\n", $pet_name ) : '';
        $message    .= $price_fmt ? sprintf( "Valor: R$ %s\n", $price_fmt ) : '';
        $message    .= sprintf( "Status do pagamento: %s\n", $payment_data['status'] ?? '' );
        $message    .= sprintf( "ID do pagamento no Mercado Pago: %s\n", $payment_data['id'] ?? '' );
        wp_mail( $notify_email, $subject, $message );
    }
}

// Inicializa o add-on
new DPS_Payment_Addon();