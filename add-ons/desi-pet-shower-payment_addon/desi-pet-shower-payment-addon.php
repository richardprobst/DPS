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
        // Segredo utilizado para validar notificações do Mercado Pago
        register_setting( 'dps_payment_options', 'dps_mercadopago_webhook_secret' );
    }

    /**
     * Adiciona uma página de configurações no menu principal "Desi Pet Shower".
     */
    public function add_settings_page() {
        add_submenu_page(
            'desi-pet-shower',
            __( 'Pagamentos', 'dps-payment-addon' ),
            __( 'Pagamentos', 'dps-payment-addon' ),
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
        // Campo para secret de webhook do Mercado Pago
        add_settings_field(
            'dps_mercadopago_webhook_secret',
            __( 'Webhook secret', 'dps-payment-addon' ),
            [ $this, 'render_webhook_secret_field' ],
            'dps-payment-settings',
            'dps_payment_section'
        );
        // Registra as opções para que possam ser salvas
        register_setting( 'dps_payment_options', 'dps_mercadopago_access_token' );
        register_setting( 'dps_payment_options', 'dps_pix_key' );
        register_setting( 'dps_payment_options', 'dps_mercadopago_webhook_secret' );

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
     * Renderiza o campo de secret do webhook para validar notificações.
     */
    public function render_webhook_secret_field() {
        $secret = esc_attr( get_option( 'dps_mercadopago_webhook_secret', '' ) );
        $site_url = home_url( '?secret=SUA_CHAVE_AQUI' );
        
        echo '<input type="password" name="dps_mercadopago_webhook_secret" value="' . $secret . '" style="width: 400px;" autocomplete="off" />';
        echo '<p class="description">';
        echo esc_html__( 'Chave de segurança para validar notificações do Mercado Pago. Gere uma senha forte (mínimo 20 caracteres) e configure no painel do Mercado Pago.', 'dps-payment-addon' );
        echo '<br><br>';
        
        // Instruções passo a passo inline
        echo '<strong>' . esc_html__( 'Como configurar:', 'dps-payment-addon' ) . '</strong><br>';
        echo '1️⃣ ' . esc_html__( 'Gere uma senha forte (exemplo: use um gerenciador de senhas)', 'dps-payment-addon' ) . '<br>';
        echo '2️⃣ ' . esc_html__( 'Cole a senha neste campo e salve', 'dps-payment-addon' ) . '<br>';
        echo '3️⃣ ' . esc_html__( 'No painel do Mercado Pago, configure a URL do webhook como:', 'dps-payment-addon' ) . '<br>';
        echo '<code style="background: #f0f0f0; padding: 4px 8px; display: inline-block; margin: 4px 0;">' . esc_html( $site_url ) . '</code><br>';
        echo '<small>' . esc_html__( '(Substitua SUA_CHAVE_AQUI pela senha que você definiu acima)', 'dps-payment-addon' ) . '</small><br><br>';
        
        // Link para documentação completa
        echo '📖 <strong>';
        echo '<a href="https://github.com/richardprobst/DPS/blob/main/add-ons/desi-pet-shower-payment_addon/WEBHOOK_CONFIGURATION.md" target="_blank">';
        echo esc_html__( 'Veja o guia completo de configuração', 'dps-payment-addon' );
        echo '</a></strong>';
        echo ' ' . esc_html__( '(abre em nova aba)', 'dps-payment-addon' );
        
        echo '</p>';
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
        $raw_body = file_get_contents( 'php://input' );
        $payload  = json_decode( $raw_body, true );

        // Ignora requisições comuns do site que não contenham pistas de notificação
        // do Mercado Pago para evitar respostas 401 em acessos legítimos.
        if ( ! $this->is_mp_notification_request( $payload ) ) {
            return;
        }

        $this->log_notification( 'Notificação do Mercado Pago recebida', [ 'raw' => $raw_body, 'get' => $_GET ] );
        if ( ! $this->validate_mp_webhook_request() ) {
            $this->log_notification( 'Falha na validação do webhook do Mercado Pago', [] );
            if ( ! headers_sent() ) {
                status_header( 401 );
            }
            echo 'Unauthorized';
            exit;
        }
        $payment_id = '';
        $topic      = '';
        $notification_id = '';
        $payload         = is_array( $payload ) ? $payload : [];

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
            if ( is_array( $payload ) ) {
                // Alguns webhooks enviam { "topic": "payment", "id": "123" }
                if ( isset( $payload['topic'] ) && isset( $payload['id'] ) ) {
                    $topic      = sanitize_text_field( $payload['topic'] );
                    $payment_id = sanitize_text_field( (string) $payload['id'] );
                }
                // Outros webhooks enviam { "data": { "id": "123" }, "type": "payment" }
                if ( ! $payment_id && isset( $payload['data'] ) && isset( $payload['data']['id'] ) ) {
                    $payment_id = sanitize_text_field( (string) $payload['data']['id'] );
                    // MP pode usar "action" ou "type" para o tópico
                    if ( isset( $payload['type'] ) ) {
                        $topic = sanitize_text_field( $payload['type'] );
                    } elseif ( isset( $payload['action'] ) ) {
                        $topic = sanitize_text_field( $payload['action'] );
                    }
                }
            }
        }
        // Se não for pagamento ou não tiver id, ignora
        if ( 'payment' !== strtolower( $topic ) || ! $payment_id ) {
            return;
        }
        $notification_id = $this->extract_notification_identifier( $payload, $topic, $payment_id );
        if ( $notification_id && $this->is_notification_processed( $notification_id ) ) {
            $this->log_notification( 'Notificação ignorada por idempotência', [ 'notification_id' => $notification_id ] );
            if ( ! headers_sent() ) {
                status_header( 200 );
            }
            echo 'OK';
            exit;
        }
        // Processa e responde
        $processed = $this->process_payment_notification( $payment_id, $notification_id );
        if ( $processed && $notification_id ) {
            $this->mark_notification_as_processed( $notification_id, $processed );
        }
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
     * @param string $notification_id ID único da notificação para idempotência.
     * @return array|false Dados do processamento ou falso em caso de falha.
     */
    private function process_payment_notification( $payment_id, $notification_id = '' ) {
        $token = trim( get_option( 'dps_mercadopago_access_token' ) );
        if ( ! $token ) {
            $this->log_notification( 'Token do Mercado Pago ausente; não é possível processar notificação.', [] );
            return false;
        }
        // Consulta a API de pagamentos do Mercado Pago
        $url      = 'https://api.mercadopago.com/v1/payments/' . rawurlencode( $payment_id ) . '?access_token=' . rawurlencode( $token );
        $response = wp_remote_get( $url );
        if ( is_wp_error( $response ) ) {
            $this->log_notification( 'Erro ao consultar pagamento no Mercado Pago', [ 'error' => $response->get_error_message() ] );
            return false;
        }
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $data ) ) {
            $this->log_notification( 'Resposta inválida do Mercado Pago', [ 'body' => wp_remote_retrieve_body( $response ) ] );
            return false;
        }
        $status = isset( $data['status'] ) ? strtolower( $data['status'] ) : '';
        // O campo external_reference deve estar presente para identificar o registro
        $external_reference = $data['external_reference'] ?? '';
        if ( ! $external_reference ) {
            $this->log_notification( 'Notificação sem external_reference; não é possível mapear agendamento/assinatura.', [ 'payment_id' => $payment_id ] );
            return false;
        }
        $result = [
            'success'      => false,
            'transacao_id' => 0,
        ];
        // Determina o tipo (agendamento ou assinatura)
        if ( 0 === strpos( $external_reference, 'dps_appointment_' ) ) {
            $parts   = explode( '_', $external_reference );
            // Espera formato dps_appointment_{ID}
            $appt_id = isset( $parts[2] ) ? intval( $parts[2] ) : 0;
            if ( $appt_id ) {
                $trans_id          = $this->mark_appointment_paid( $appt_id, $data, $status );
                $result['success'] = true;
                $result['transacao_id'] = $trans_id;
            }
        } elseif ( 0 === strpos( $external_reference, 'dps_subscription_' ) ) {
            $parts  = explode( '_', $external_reference );
            $sub_id = isset( $parts[2] ) ? intval( $parts[2] ) : 0;
            if ( $sub_id ) {
                $this->mark_subscription_paid( $sub_id, $data, $status );
                $result['success'] = true;
            }
        }
        if ( $result['success'] ) {
            $this->log_notification(
                'Atualização de pagamento do Mercado Pago aplicada',
                [
                    'status'           => $status,
                    'notification_id'  => $notification_id,
                    'external_reference' => $external_reference,
                ]
            );
            return $result;
        }
        return false;
    }

    /**
     * Marca um agendamento como pago e envia um email de notificação.
     * Apenas altera o status se o agendamento estiver marcado como finalizado (pendente).
     *
     * @param int   $appt_id      ID do agendamento.
     * @param array $payment_data Dados completos do pagamento retornado pela API.
     */
    private function mark_appointment_paid( $appt_id, $payment_data, $mp_status ) {
        $transaction_status = $this->map_mp_status_to_transaction_status( $mp_status );
        $appointment_status = $this->map_mp_status_to_appointment_status( $mp_status );
        if ( $appointment_status ) {
            update_post_meta( $appt_id, 'appointment_status', $appointment_status );
        }
        // Registra o status do pagamento no meta para referência futura
        update_post_meta( $appt_id, 'dps_payment_status', $payment_data['status'] ?? '' );
        // Atualiza ou cria a transação associada a este agendamento na tabela customizada
        global $wpdb;
        $table_name = $wpdb->prefix . 'dps_transacoes';
        // Verifica se já existe transação para este agendamento
        $existing_trans_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table_name} WHERE agendamento_id = %d", $appt_id ) );
        $trans_id          = 0;
        if ( $existing_trans_id ) {
            // Atualiza status de acordo com o retorno do Mercado Pago
            $wpdb->update( $table_name, [ 'status' => $transaction_status ], [ 'id' => $existing_trans_id ], [ '%s' ], [ '%d' ] );
            $trans_id = (int) $existing_trans_id;
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
                'status'         => $transaction_status,
                'descricao'      => $desc,
            ], [ '%d','%d','%d','%s','%f','%s','%s','%s','%s' ] );
            $trans_id = (int) $wpdb->insert_id;
        }
        if ( in_array( $mp_status, [ 'approved', 'success', 'authorized' ], true ) ) {
            // Envia notificação por email ao administrador somente para status aprovados
            $this->send_payment_notification_email( $appt_id, $payment_data );
        }
        $this->log_notification( 'Status do agendamento atualizado a partir do Mercado Pago', [ 'appointment_id' => $appt_id, 'status' => $mp_status, 'transaction_status' => $transaction_status ] );
        return $trans_id;
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
    private function mark_subscription_paid( $sub_id, $payment_data, $mp_status ) {
        $transaction_status = $this->map_mp_status_to_transaction_status( $mp_status );
        $appointment_status = $this->map_mp_status_to_appointment_status( $mp_status );
        // Atualiza meta de status de pagamento da assinatura
        update_post_meta( $sub_id, 'subscription_payment_status', $transaction_status );
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
                if ( $appointment_status ) {
                    update_post_meta( $appt->ID, 'appointment_status', $appointment_status );
                }
                // Atualiza ou cria a transação correspondente para refletir o status recebido
                global $wpdb;
                $table_name = $wpdb->prefix . 'dps_transacoes';
                $trans_id   = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table_name} WHERE agendamento_id = %d", $appt->ID ) );
                if ( $trans_id ) {
                    // Atualiza status e data de atualização
                    $wpdb->update( $table_name, [ 'status' => $transaction_status, 'data' => current_time( 'Y-m-d' ) ], [ 'id' => $trans_id ], [ '%s','%s' ], [ '%d' ] );
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
                        'status'         => $transaction_status,
                        'descricao'      => $desc,
                    ], [ '%d','%d','%d','%s','%f','%s','%s','%s','%s' ] );
                }
                // Atualiza meta de status de pagamento
                update_post_meta( $appt->ID, 'dps_payment_status', $payment_data['status'] ?? '' );
            }
        }
        // Atualiza ou cria a transação principal da assinatura (plano) para refletir o status
        global $wpdb;
        $table_name = $wpdb->prefix . 'dps_transacoes';
        $plan_trans_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table_name} WHERE plano_id = %d", $sub_id ) );
        if ( $plan_trans_id ) {
            $wpdb->update( $table_name, [ 'status' => $transaction_status, 'data' => current_time( 'Y-m-d' ) ], [ 'id' => $plan_trans_id ], [ '%s','%s' ], [ '%d' ] );
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
                'status'         => $transaction_status,
                'descricao'      => __( 'Pagamento de assinatura', 'dps-agenda-addon' ),
            ], [ '%d','%d','%d','%s','%f','%s','%s','%s','%s' ] );
        }
        if ( in_array( $mp_status, [ 'approved', 'success', 'authorized' ], true ) ) {
            // Envia email de notificação
            $this->send_subscription_payment_notification_email( $sub_id, $payment_data );
        }
        $this->log_notification( 'Status da assinatura atualizado a partir do Mercado Pago', [ 'subscription_id' => $sub_id, 'status' => $mp_status, 'transaction_status' => $transaction_status ] );
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

    /**
     * Verifica se a requisição atual contém dados característicos de notificações
     * do Mercado Pago, evitando interferir em acessos normais do site.
     *
     * @param array|null $payload Corpo JSON já decodificado, quando houver.
     * @return bool
     */
    private function is_mp_notification_request( $payload ) {
        $has_query_params = ( isset( $_GET['topic'] ) && isset( $_GET['id'] ) )
            || ( isset( $_GET['data.topic'] ) && isset( $_GET['data.id'] ) );

        $has_auth_markers = isset( $_GET['token'] ) || isset( $_GET['secret'] )
            || isset( $_SERVER['HTTP_X_WEBHOOK_SECRET'] )
            || ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) && stripos( sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) ), 'bearer ' ) === 0 );

        $has_payload_data = is_array( $payload ) && (
            ( isset( $payload['topic'] ) && isset( $payload['id'] ) ) ||
            ( isset( $payload['data'] ) && isset( $payload['data']['id'] ) ) ||
            ( isset( $payload['type'] ) && isset( $payload['data'] ) ) ||
            ( isset( $payload['action'] ) && isset( $payload['data'] ) )
        );

        return $has_query_params || $has_auth_markers || $has_payload_data;
    }

    /**
     * Valida a requisição do webhook utilizando um secret configurado.
     *
     * @return bool
     */
    private function validate_mp_webhook_request() {
        $expected = $this->get_webhook_secret();
        if ( ! $expected ) {
            return false;
        }
        $provided = '';
        if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) && stripos( sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) ), 'bearer ' ) === 0 ) {
            $provided = trim( substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) ), 7 ) );
        }
        if ( isset( $_SERVER['HTTP_X_WEBHOOK_SECRET'] ) ) {
            $provided = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WEBHOOK_SECRET'] ) );
        }
        if ( isset( $_GET['token'] ) ) {
            $provided = sanitize_text_field( wp_unslash( $_GET['token'] ) );
        }
        if ( isset( $_GET['secret'] ) ) {
            $provided = sanitize_text_field( wp_unslash( $_GET['secret'] ) );
        }
        return $provided && hash_equals( $expected, $provided );
    }

    /**
     * Recupera o secret configurado para validação do webhook.
     *
     * @return string
     */
    private function get_webhook_secret() {
        $secret = trim( (string) get_option( 'dps_mercadopago_webhook_secret', '' ) );
        if ( $secret ) {
            return $secret;
        }
        $token = trim( (string) get_option( 'dps_mercadopago_access_token', '' ) );
        return $token;
    }

    /**
     * Extrai um identificador único da notificação para garantir idempotência.
     *
     * @param array  $payload      Corpo JSON da notificação (se houver).
     * @param string $topic        Tópico recebido.
     * @param string $payment_id   ID do pagamento.
     * @return string
     */
    private function extract_notification_identifier( $payload, $topic, $payment_id ) {
        if ( isset( $_GET['notification_id'] ) ) {
            return sanitize_text_field( wp_unslash( $_GET['notification_id'] ) );
        }
        if ( isset( $_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ) ) {
            return sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ) );
        }
        if ( isset( $_SERVER['HTTP_X_NOTIFICATION_ID'] ) ) {
            return sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_NOTIFICATION_ID'] ) );
        }
        if ( is_array( $payload ) ) {
            if ( isset( $payload['notification_id'] ) ) {
                return sanitize_text_field( (string) $payload['notification_id'] );
            }
            if ( isset( $payload['event_id'] ) ) {
                return sanitize_text_field( (string) $payload['event_id'] );
            }
            if ( isset( $payload['id'] ) && ! empty( $payload['id'] ) && is_scalar( $payload['id'] ) ) {
                return sanitize_text_field( (string) $payload['id'] );
            }
        }
        if ( $payment_id ) {
            return sanitize_text_field( $topic . ':' . $payment_id );
        }
        return '';
    }

    /**
     * Converte o status do Mercado Pago para o status da transação.
     *
     * @param string $mp_status Status enviado pelo Mercado Pago.
     * @return string
     */
    private function map_mp_status_to_transaction_status( $mp_status ) {
        switch ( strtolower( (string) $mp_status ) ) {
            case 'approved':
            case 'success':
            case 'authorized':
                return 'pago';
            case 'pending':
            case 'in_process':
                return 'pendente';
            case 'cancelled':
            case 'rejected':
                return 'cancelado';
            case 'refunded':
            case 'charged_back':
                return 'reembolsado';
            default:
                return 'pendente';
        }
    }

    /**
     * Converte o status do Mercado Pago para o status do agendamento.
     *
     * @param string $mp_status Status enviado pelo Mercado Pago.
     * @return string
     */
    private function map_mp_status_to_appointment_status( $mp_status ) {
        switch ( strtolower( (string) $mp_status ) ) {
            case 'approved':
            case 'success':
            case 'authorized':
                return 'finalizado_pago';
            case 'pending':
            case 'in_process':
                return 'pagamento_pendente';
            case 'cancelled':
            case 'rejected':
                return 'cancelado';
            case 'refunded':
            case 'charged_back':
                return 'reembolsado';
            default:
                return '';
        }
    }

    /**
     * Retorna o nome da tabela de meta de transações.
     *
     * @return string
     */
    private function get_transactions_meta_table() {
        global $wpdb;
        return $wpdb->prefix . 'dps_transacoes_meta';
    }

    /**
     * Garante a criação da tabela de meta de transações para registrar notificações.
     */
    private function ensure_transactions_meta_table() {
        global $wpdb;
        $table = $this->get_transactions_meta_table();
        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( $table_exists === $table ) {
            return;
        }
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();
        $sql             = "CREATE TABLE {$table} (\n" .
            "meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n" .
            "transacao_id bigint(20) unsigned NOT NULL DEFAULT 0,\n" .
            "meta_key varchar(191) NOT NULL,\n" .
            "meta_value longtext NULL,\n" .
            "PRIMARY KEY  (meta_id),\n" .
            "KEY transacao_id (transacao_id),\n" .
            "KEY meta_key (meta_key)\n" .
            ") {$charset_collate};";
        maybe_create_table( $table, $sql );
    }

    /**
     * Verifica se uma notificação já foi processada.
     *
     * @param string $notification_id Identificador único da notificação.
     * @return bool
     */
    private function is_notification_processed( $notification_id ) {
        if ( ! $notification_id ) {
            return false;
        }
        $this->ensure_transactions_meta_table();
        global $wpdb;
        $table   = $this->get_transactions_meta_table();
        $meta_id = $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM {$table} WHERE meta_key = %s AND meta_value = %s LIMIT 1", 'mp_notification_id', $notification_id ) );
        return (bool) $meta_id;
    }

    /**
     * Registra uma notificação como processada para garantir idempotência.
     *
     * @param string $notification_id Identificador único.
     * @param array  $processed       Dados retornados do processamento.
     */
    private function mark_notification_as_processed( $notification_id, $processed ) {
        if ( ! $notification_id ) {
            return;
        }
        $this->ensure_transactions_meta_table();
        global $wpdb;
        $table    = $this->get_transactions_meta_table();
        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM {$table} WHERE meta_key = %s AND meta_value = %s LIMIT 1", 'mp_notification_id', $notification_id ) );
        if ( $existing ) {
            return;
        }
        $transacao_id = 0;
        if ( is_array( $processed ) && isset( $processed['transacao_id'] ) ) {
            $transacao_id = (int) $processed['transacao_id'];
        }
        $wpdb->insert(
            $table,
            [
                'transacao_id' => $transacao_id,
                'meta_key'     => 'mp_notification_id',
                'meta_value'   => $notification_id,
            ],
            [ '%d', '%s', '%s' ]
        );
    }

    /**
     * Registra logs simples para acompanhamento do fluxo de notificações.
     *
     * @param string $message Mensagem principal.
     * @param array  $context Dados adicionais para depuração.
     */
    private function log_notification( $message, $context ) {
        $prefix = '[DPS Pagamentos] ';
        if ( ! empty( $context ) ) {
            $message .= ' | ' . wp_json_encode( $context );
        }
        error_log( $prefix . $message );
    }
}

// Inicializa o add-on
new DPS_Payment_Addon();
