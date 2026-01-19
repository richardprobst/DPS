<?php
/**
 * Sincronização DPS → Google Tasks
 *
 * Cria tarefas administrativas no Google Tasks baseadas em eventos do sistema:
 * - Follow-ups pós-atendimento (agendamento finalizado)
 * - Cobranças pendentes (transações vencendo)
 * - Mensagens do portal do cliente (resposta necessária)
 *
 * @package DPS_Google_Integrations
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe de sincronização de tarefas administrativas
 */
class DPS_Google_Tasks_Sync {

    /**
     * Instância do cliente Google Tasks
     *
     * @var DPS_Google_Tasks_Client
     */
    private $client;

    /**
     * Instância de autenticação
     *
     * @var DPS_Google_Auth
     */
    private $auth;

    /**
     * Construtor
     *
     * @param DPS_Google_Auth $auth Instância de autenticação.
     */
    public function __construct( $auth ) {
        $this->auth   = $auth;
        $this->client = new DPS_Google_Tasks_Client( $auth );

        $this->init_hooks();
    }

    /**
     * Inicializa hooks do WordPress
     */
    private function init_hooks() {
        // Follow-up após agendamento finalizado
        add_action( 'dps_appointment_status_changed', [ $this, 'maybe_create_followup_task' ], 10, 4 );

        // Cobrança pendente (hook do Finance addon)
        add_action( 'dps_finance_charge_created', [ $this, 'maybe_create_payment_task' ], 10, 2 );
        add_action( 'dps_finance_charge_updated', [ $this, 'maybe_update_payment_task' ], 10, 2 );

        // Mensagens do portal (se addon Communications estiver ativo)
        add_action( 'dps_client_message_received', [ $this, 'maybe_create_message_task' ], 10, 1 );
    }

    /**
     * Cria tarefa de follow-up após agendamento finalizado
     *
     * @param int    $appt_id ID do agendamento.
     * @param string $old_status Status anterior.
     * @param string $new_status Novo status.
     * @param array  $appt_data Dados do agendamento.
     */
    public function maybe_create_followup_task( $appt_id, $old_status, $new_status, $appt_data ) {
        // Só cria follow-up se mudou para 'finalizado' e sync tasks está habilitado
        if ( $new_status !== 'finalizado' ) {
            return;
        }

        $settings = get_option( 'dps_google_integrations_settings', [] );
        if ( empty( $settings['sync_tasks'] ) || ! $this->auth->is_connected() ) {
            return;
        }

        // Verifica se já tem task criada
        $existing_task_id = get_post_meta( $appt_id, '_google_task_followup_id', true );
        if ( $existing_task_id ) {
            return;
        }

        // Formata dados da tarefa
        $client_name = get_post_meta( $appt_id, 'client_name', true );
        $pet_name    = get_post_meta( $appt_id, 'pet_name', true );
        $services    = get_post_meta( $appt_id, 'services', true );

        if ( is_array( $services ) && ! empty( $services ) ) {
            $services_text = implode( ', ', $services );
        } else {
            $services_text = __( 'Serviços', 'desi-pet-shower' );
        }

        $admin_url = admin_url( 'post.php?post=' . $appt_id . '&action=edit' );

        $task_data = [
            'title'  => sprintf(
                '📞 Follow-up: %s - %s',
                esc_html( $pet_name ?: __( 'Pet', 'desi-pet-shower' ) ),
                esc_html( $services_text )
            ),
            'notes'  => sprintf(
                "Cliente: %s\nPet: %s\nServiços: %s\n\n✅ Atendimento finalizado - fazer contato para avaliar satisfação e agendar retorno.\n\n🔗 Ver agendamento no DPS: %s",
                esc_html( $client_name ?: __( 'Cliente', 'desi-pet-shower' ) ),
                esc_html( $pet_name ?: __( 'Pet', 'desi-pet-shower' ) ),
                esc_html( $services_text ),
                esc_url( $admin_url )
            ),
            'due'    => $this->client->format_due_date( strtotime( '+2 days' ) ), // Follow-up 2 dias depois
            'status' => 'needsAction',
        ];

        /**
         * Filtra dados da tarefa de follow-up antes de enviar
         *
         * @param array $task_data Dados da tarefa.
         * @param int   $appt_id ID do agendamento.
         */
        $task_data = apply_filters( 'dps_google_tasks_followup_data', $task_data, $appt_id );

        // Cria tarefa no Google Tasks
        $result = $this->client->create_task( '@default', $task_data );

        if ( is_wp_error( $result ) ) {
            update_post_meta( $appt_id, '_google_task_followup_error', $result->get_error_message() );
            do_action( 'dps_google_tasks_sync_error', $result, 'followup', $appt_id );
            return;
        }

        // Armazena ID da tarefa no agendamento
        update_post_meta( $appt_id, '_google_task_followup_id', $result['id'] );
        update_post_meta( $appt_id, '_google_task_followup_created_at', current_time( 'mysql' ) );

        do_action( 'dps_google_task_followup_created', $appt_id, $result['id'] );
    }

    /**
     * Cria tarefa de cobrança pendente
     *
     * @param int $charge_id ID da cobrança (row ID na tabela dps_transacoes).
     * @param int $appt_id ID do agendamento relacionado.
     */
    public function maybe_create_payment_task( $charge_id, $appt_id ) {
        $settings = get_option( 'dps_google_integrations_settings', [] );
        if ( empty( $settings['sync_tasks'] ) || ! $this->auth->is_connected() ) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'dps_transacoes';

        // Busca dados da transação
        $transaction = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d",
                $charge_id
            ),
            ARRAY_A
        );

        if ( ! $transaction || $transaction['tipo'] !== 'receita' || $transaction['status'] !== 'pendente' ) {
            return;
        }

        // Não cria se já tiver task
        $existing_task_id = get_post_meta( $appt_id, '_google_task_payment_id_' . $charge_id, true );
        if ( $existing_task_id ) {
            return;
        }

        // Formata dados
        $client_name = get_post_meta( $appt_id, 'client_name', true );
        $valor_cents = (int) $transaction['valor_cents'];
        $valor_real  = $valor_cents / 100;

        // Usa DPS_Money_Helper se disponível
        if ( class_exists( 'DPS_Money_Helper' ) ) {
            $valor_formatado = DPS_Money_Helper::format_for_display( $valor_cents );
        } else {
            $valor_formatado = 'R$ ' . number_format( $valor_real, 2, ',', '.' );
        }

        $due_date  = $transaction['data_vencimento'];
        $admin_url = admin_url( 'post.php?post=' . $appt_id . '&action=edit' );

        $task_data = [
            'title'  => sprintf(
                '💰 Cobrança: %s - %s',
                esc_html( $client_name ?: __( 'Cliente', 'desi-pet-shower' ) ),
                esc_html( $valor_formatado )
            ),
            'notes'  => sprintf(
                "Cliente: %s\nValor: %s\nVencimento: %s\nDescrição: %s\n\n⚠️ Cobrança pendente - entrar em contato para solicitar pagamento.\n\n🔗 Ver agendamento no DPS: %s",
                esc_html( $client_name ?: __( 'Cliente', 'desi-pet-shower' ) ),
                esc_html( $valor_formatado ),
                esc_html( date_i18n( 'd/m/Y', strtotime( $due_date ) ) ),
                esc_html( $transaction['descricao'] ?: __( 'Pagamento de serviços', 'desi-pet-shower' ) ),
                esc_url( $admin_url )
            ),
            'due'    => $this->client->format_due_date( strtotime( $due_date . ' -1 day' ) ), // 1 dia antes do vencimento
            'status' => 'needsAction',
        ];

        /**
         * Filtra dados da tarefa de cobrança antes de enviar
         *
         * @param array $task_data Dados da tarefa.
         * @param int   $charge_id ID da cobrança.
         * @param int   $appt_id ID do agendamento.
         */
        $task_data = apply_filters( 'dps_google_tasks_payment_data', $task_data, $charge_id, $appt_id );

        // Cria tarefa no Google Tasks
        $result = $this->client->create_task( '@default', $task_data );

        if ( is_wp_error( $result ) ) {
            update_post_meta( $appt_id, '_google_task_payment_error_' . $charge_id, $result->get_error_message() );
            do_action( 'dps_google_tasks_sync_error', $result, 'payment', $appt_id );
            return;
        }

        // Armazena ID da tarefa
        update_post_meta( $appt_id, '_google_task_payment_id_' . $charge_id, $result['id'] );
        update_post_meta( $appt_id, '_google_task_payment_created_at_' . $charge_id, current_time( 'mysql' ) );

        do_action( 'dps_google_task_payment_created', $appt_id, $charge_id, $result['id'] );
    }

    /**
     * Atualiza tarefa de cobrança quando status mudar
     *
     * @param int $charge_id ID da cobrança.
     * @param int $appt_id ID do agendamento.
     */
    public function maybe_update_payment_task( $charge_id, $appt_id ) {
        $settings = get_option( 'dps_google_integrations_settings', [] );
        if ( empty( $settings['sync_tasks'] ) || ! $this->auth->is_connected() ) {
            return;
        }

        $task_id = get_post_meta( $appt_id, '_google_task_payment_id_' . $charge_id, true );
        if ( ! $task_id ) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'dps_transacoes';

        $transaction = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT status FROM {$table_name} WHERE id = %d",
                $charge_id
            ),
            ARRAY_A
        );

        // Se foi paga, marca tarefa como concluída
        if ( $transaction && $transaction['status'] === 'pago' ) {
            $update_data = [
                'status'    => 'completed',
                'completed' => $this->client->format_due_date( time() ),
            ];

            $result = $this->client->update_task( '@default', $task_id, $update_data );

            if ( ! is_wp_error( $result ) ) {
                update_post_meta( $appt_id, '_google_task_payment_completed_at_' . $charge_id, current_time( 'mysql' ) );
                do_action( 'dps_google_task_payment_completed', $appt_id, $charge_id, $task_id );
            }
        }
    }

    /**
     * Cria tarefa quando mensagem do cliente é recebida
     *
     * @param array $message_data Dados da mensagem.
     */
    public function maybe_create_message_task( $message_data ) {
        $settings = get_option( 'dps_google_integrations_settings', [] );
        if ( empty( $settings['sync_tasks'] ) || ! $this->auth->is_connected() ) {
            return;
        }

        // Extrai dados relevantes
        $client_name = isset( $message_data['client_name'] ) ? $message_data['client_name'] : __( 'Cliente', 'desi-pet-shower' );
        $subject     = isset( $message_data['subject'] ) ? $message_data['subject'] : __( 'Solicitação', 'desi-pet-shower' );
        $message     = isset( $message_data['message'] ) ? $message_data['message'] : '';
        $message_id  = isset( $message_data['id'] ) ? $message_data['id'] : 0;

        // URL do portal (se disponível)
        $portal_url = home_url( '/portal-cliente/' );

        $task_data = [
            'title'  => sprintf(
                '💬 Responder: %s - %s',
                esc_html( $client_name ),
                esc_html( $subject )
            ),
            'notes'  => sprintf(
                "Cliente: %s\nAssunto: %s\n\nMensagem:\n%s\n\n📱 Responder no Portal: %s",
                esc_html( $client_name ),
                esc_html( $subject ),
                esc_html( wp_trim_words( $message, 50 ) ),
                esc_url( $portal_url )
            ),
            'due'    => $this->client->format_due_date( strtotime( '+1 day' ) ), // Responder em 1 dia
            'status' => 'needsAction',
        ];

        /**
         * Filtra dados da tarefa de mensagem antes de enviar
         *
         * @param array $task_data Dados da tarefa.
         * @param array $message_data Dados da mensagem.
         */
        $task_data = apply_filters( 'dps_google_tasks_message_data', $task_data, $message_data );

        // Cria tarefa no Google Tasks
        $result = $this->client->create_task( '@default', $task_data );

        if ( ! is_wp_error( $result ) ) {
            do_action( 'dps_google_task_message_created', $message_id, $result['id'] );
        }
    }
}
