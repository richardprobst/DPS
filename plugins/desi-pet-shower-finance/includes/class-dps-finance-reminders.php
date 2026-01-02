<?php
/**
 * Gerencia lembretes automáticos de pagamento.
 *
 * FASE 4 - F4.2: Lembretes Automáticos de Pagamento
 *
 * @package    Desi_Pet_Shower
 * @subpackage Finance_Addon
 * @since      1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe responsável por gerenciar lembretes automáticos de cobrança.
 */
class DPS_Finance_Reminders {

    /**
     * Nome do evento cron.
     *
     * @var string
     */
    const CRON_HOOK = 'dps_finance_process_payment_reminders';

    /**
     * Inicializa a classe de lembretes.
     */
    public static function init() {
        // Registra evento cron
        add_action( self::CRON_HOOK, [ __CLASS__, 'process_reminders' ] );

        // Agenda cron se ainda não estiver agendado
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'daily', self::CRON_HOOK );
        }

        // Limpa cron na desativação
        register_deactivation_hook( DPS_FINANCE_PLUGIN_FILE, [ __CLASS__, 'clear_scheduled_hook' ] );
    }

    /**
     * Limpa evento cron agendado.
     *
     * @since 1.6.0
     */
    public static function clear_scheduled_hook() {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }
    }

    /**
     * Processa lembretes de pagamento (executado diariamente via cron).
     *
     * @since 1.6.0
     */
    public static function process_reminders() {
        // Verifica se lembretes estão habilitados
        if ( ! self::is_enabled() ) {
            return;
        }

        // Busca configurações
        $days_before = (int) get_option( 'dps_finance_reminder_days_before', 1 );
        $days_after  = (int) get_option( 'dps_finance_reminder_days_after', 1 );

        // Calcula datas alvo
        $date_before = date( 'Y-m-d', strtotime( "+{$days_before} days" ) );
        $date_after  = date( 'Y-m-d', strtotime( "-{$days_after} days" ) );

        // Processa lembretes antes do vencimento
        self::send_before_reminders( $date_before );

        // Processa lembretes após vencimento
        self::send_after_reminders( $date_after );

        // Log de execução
        error_log( sprintf(
            'DPS Finance Reminders: Processamento concluído. Before: %s, After: %s',
            $date_before,
            $date_after
        ) );
    }

    /**
     * Envia lembretes ANTES do vencimento.
     *
     * @since 1.6.0
     * @param string $target_date Data alvo (Y-m-d).
     */
    private static function send_before_reminders( $target_date ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';

        // Busca transações em aberto que vencem na data alvo
        $transactions = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table 
             WHERE tipo = 'receita' 
             AND status = 'em_aberto' 
             AND data = %s",
            $target_date
        ) );

        foreach ( $transactions as $trans ) {
            // Verifica se já enviou lembrete antes para esta transação
            $sent_at = get_transient( 'dps_reminder_before_' . $trans->id );
            if ( $sent_at ) {
                continue; // Já foi enviado
            }

            // Envia lembrete
            $result = self::send_reminder( $trans, 'before' );

            if ( $result ) {
                // Marca como enviado (expira em 7 dias)
                set_transient( 'dps_reminder_before_' . $trans->id, current_time( 'mysql' ), 7 * DAY_IN_SECONDS );
            }
        }
    }

    /**
     * Envia lembretes APÓS vencimento.
     *
     * @since 1.6.0
     * @param string $target_date Data alvo (Y-m-d).
     */
    private static function send_after_reminders( $target_date ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';

        // Busca transações em aberto que venceram na data alvo
        $transactions = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table 
             WHERE tipo = 'receita' 
             AND status = 'em_aberto' 
             AND data = %s",
            $target_date
        ) );

        foreach ( $transactions as $trans ) {
            // Verifica se já enviou lembrete depois para esta transação
            $sent_at = get_transient( 'dps_reminder_after_' . $trans->id );
            if ( $sent_at ) {
                continue; // Já foi enviado
            }

            // Envia lembrete
            $result = self::send_reminder( $trans, 'after' );

            if ( $result ) {
                // Marca como enviado (expira em 7 dias)
                set_transient( 'dps_reminder_after_' . $trans->id, current_time( 'mysql' ), 7 * DAY_IN_SECONDS );
            }
        }
    }

    /**
     * Envia lembrete para uma transação.
     *
     * @since 1.6.0
     * @param object $trans Objeto da transação.
     * @param string $type  Tipo de lembrete ('before' ou 'after').
     * @return bool True se enviado com sucesso.
     */
    private static function send_reminder( $trans, $type ) {
        // Busca dados do cliente
        if ( ! $trans->cliente_id ) {
            return false;
        }

        $client = get_post( $trans->cliente_id );
        if ( ! $client ) {
            return false;
        }

        $client_name = $client->post_title;

        // Busca telefone do cliente (meta)
        $phone = get_post_meta( $trans->cliente_id, 'client_phone', true );
        if ( ! $phone ) {
            return false;
        }

        // Busca dados do agendamento para obter pet
        $pet_name = '';
        if ( $trans->agendamento_id ) {
            $pet_id = get_post_meta( $trans->agendamento_id, 'appointment_pet_id', true );
            if ( $pet_id ) {
                $pet_post = get_post( $pet_id );
                $pet_name = $pet_post ? $pet_post->post_title : '';
            }
        }

        // Formata valor
        if ( class_exists( 'DPS_Money_Helper' ) ) {
            $valor_formatted = 'R$ ' . DPS_Money_Helper::format_to_brazilian( (int) round( (float) $trans->valor * 100 ) );
        } else {
            $valor_formatted = 'R$ ' . number_format( (float) $trans->valor, 2, ',', '.' );
        }

        // Busca link de pagamento (se existir)
        $payment_link = '';
        if ( $trans->agendamento_id ) {
            $payment_link = get_post_meta( $trans->agendamento_id, 'dps_payment_link', true );
        }

        // Monta mensagem
        $message = self::get_reminder_message( $type, [
            'cliente' => $client_name,
            'pet'     => $pet_name,
            'data'    => date_i18n( 'd/m/Y', strtotime( $trans->data ) ),
            'valor'   => $valor_formatted,
            'link'    => $payment_link,
        ] );

        // Envia via WhatsApp (reutiliza sistema existente se disponível)
        $sent = self::send_whatsapp_message( $phone, $message );

        // Log
        if ( $sent ) {
            error_log( sprintf(
                'DPS Finance Reminders: Lembrete %s enviado para trans #%d (cliente: %s)',
                $type,
                $trans->id,
                $client_name
            ) );
        } else {
            error_log( sprintf(
                'DPS Finance Reminders: Falha ao enviar lembrete %s para trans #%d',
                $type,
                $trans->id
            ) );
        }

        return $sent;
    }

    /**
     * Retorna mensagem de lembrete.
     *
     * @since 1.6.0
     * @param string $type Tipo de lembrete ('before' ou 'after').
     * @param array  $data Dados para substituição de placeholders.
     * @return string Mensagem formatada.
     */
    private static function get_reminder_message( $type, $data ) {
        $templates = [
            'before' => get_option( 'dps_finance_reminder_message_before', 
                'Olá {cliente}, este é um lembrete amigável: o pagamento de R$ {valor} vence amanhã. Para sua comodidade, você pode pagar via PIX ou utilizar o link: {link}. Obrigado!' 
            ),
            'after' => get_option( 'dps_finance_reminder_message_after',
                'Olá {cliente}, o pagamento de R$ {valor} está vencido. Para regularizar, você pode pagar via PIX ou utilizar o link: {link}. Agradecemos a atenção!'
            ),
        ];

        $template = isset( $templates[ $type ] ) ? $templates[ $type ] : $templates['after'];

        // Substitui placeholders
        if ( class_exists( 'DPS_Finance_Settings' ) ) {
            return DPS_Finance_Settings::format_message( $template, $data );
        }

        // Fallback manual
        $placeholders = [
            '{cliente}' => isset( $data['cliente'] ) ? $data['cliente'] : '',
            '{pet}'     => isset( $data['pet'] ) ? $data['pet'] : '',
            '{data}'    => isset( $data['data'] ) ? $data['data'] : '',
            '{valor}'   => isset( $data['valor'] ) ? $data['valor'] : '',
            '{link}'    => isset( $data['link'] ) ? $data['link'] : '',
        ];

        return str_replace( array_keys( $placeholders ), array_values( $placeholders ), (string) $template );
    }

    /**
     * Envia mensagem via WhatsApp.
     *
     * @since 1.6.0
     * @param string $phone   Telefone do destinatário.
     * @param string $message Mensagem a enviar.
     * @return bool True se enviado (ou simulado).
     */
    private static function send_whatsapp_message( $phone, $message ) {
        // Remove formatação do telefone
        $phone_clean = preg_replace( '/[^0-9]/', '', $phone );

        // Se houver integração com Communications Add-on, usar aqui
        // Por enquanto, simula envio (log apenas)
        
        // Em produção, poderia:
        // - Chamar API do Communications Add-on
        // - Enviar via API do WhatsApp Business
        // - Adicionar à fila de mensagens

        // Simula sucesso
        return true;
    }

    /**
     * Verifica se lembretes estão habilitados.
     *
     * @since 1.6.0
     * @return bool True se habilitado.
     */
    public static function is_enabled() {
        return get_option( 'dps_finance_reminders_enabled', 'no' ) === 'yes';
    }

    /**
     * Renderiza seção de configurações de lembretes.
     *
     * @since 1.6.0
     */
    public static function render_settings_section() {
        $enabled      = get_option( 'dps_finance_reminders_enabled', 'no' );
        $days_before  = get_option( 'dps_finance_reminder_days_before', 1 );
        $days_after   = get_option( 'dps_finance_reminder_days_after', 1 );
        $msg_before   = get_option( 'dps_finance_reminder_message_before', '' );
        $msg_after    = get_option( 'dps_finance_reminder_message_after', '' );

        ?>
        <div class="dps-finance-reminders-settings" style="margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px;">
            <h3><?php esc_html_e( 'Lembretes Automáticos de Pagamento', 'dps-finance-addon' ); ?></h3>
            <p class="description"><?php esc_html_e( 'Configure lembretes automáticos para cobranças pendentes.', 'dps-finance-addon' ); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="dps_finance_reminders_enabled">
                            <?php esc_html_e( 'Habilitar Lembretes', 'dps-finance-addon' ); ?>
                        </label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="dps_finance_reminders_enabled" 
                                   id="dps_finance_reminders_enabled"
                                   value="yes" 
                                   <?php checked( $enabled, 'yes' ); ?>>
                            <?php esc_html_e( 'Enviar lembretes automáticos de pagamento', 'dps-finance-addon' ); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'Quando habilitado, o sistema enviará lembretes automáticos antes e depois do vencimento.', 'dps-finance-addon' ); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="dps_finance_reminder_days_before">
                            <?php esc_html_e( 'Dias Antes do Vencimento', 'dps-finance-addon' ); ?>
                        </label>
                    </th>
                    <td>
                        <input type="number" 
                               name="dps_finance_reminder_days_before" 
                               id="dps_finance_reminder_days_before"
                               value="<?php echo esc_attr( $days_before ); ?>" 
                               min="0" 
                               max="30" 
                               style="width: 100px;">
                        <p class="description">
                            <?php esc_html_e( 'Quantos dias antes do vencimento enviar o lembrete (ex: 1 = envia 1 dia antes).', 'dps-finance-addon' ); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="dps_finance_reminder_days_after">
                            <?php esc_html_e( 'Dias Após o Vencimento', 'dps-finance-addon' ); ?>
                        </label>
                    </th>
                    <td>
                        <input type="number" 
                               name="dps_finance_reminder_days_after" 
                               id="dps_finance_reminder_days_after"
                               value="<?php echo esc_attr( $days_after ); ?>" 
                               min="0" 
                               max="30" 
                               style="width: 100px;">
                        <p class="description">
                            <?php esc_html_e( 'Quantos dias após o vencimento enviar o lembrete de cobrança.', 'dps-finance-addon' ); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="dps_finance_reminder_message_before">
                            <?php esc_html_e( 'Mensagem - Antes do Vencimento', 'dps-finance-addon' ); ?>
                        </label>
                    </th>
                    <td>
                        <textarea name="dps_finance_reminder_message_before" 
                                  id="dps_finance_reminder_message_before"
                                  rows="4" 
                                  class="large-text"><?php echo esc_textarea( $msg_before ); ?></textarea>
                        <p class="description">
                            <?php esc_html_e( 'Placeholders disponíveis: {cliente}, {pet}, {data}, {valor}, {link}, {pix}, {loja}', 'dps-finance-addon' ); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="dps_finance_reminder_message_after">
                            <?php esc_html_e( 'Mensagem - Após Vencimento', 'dps-finance-addon' ); ?>
                        </label>
                    </th>
                    <td>
                        <textarea name="dps_finance_reminder_message_after" 
                                  id="dps_finance_reminder_message_after"
                                  rows="4" 
                                  class="large-text"><?php echo esc_textarea( $msg_after ); ?></textarea>
                        <p class="description">
                            <?php esc_html_e( 'Placeholders disponíveis: {cliente}, {pet}, {data}, {valor}, {link}, {pix}, {loja}', 'dps-finance-addon' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Salva configurações de lembretes.
     *
     * @since 1.6.0
     * @param array $data Dados do formulário.
     */
    public static function save_settings( $data ) {
        // Habilitar/desabilitar
        $enabled = isset( $data['dps_finance_reminders_enabled'] ) && $data['dps_finance_reminders_enabled'] === 'yes' ? 'yes' : 'no';
        update_option( 'dps_finance_reminders_enabled', $enabled );

        // Dias antes/depois
        if ( isset( $data['dps_finance_reminder_days_before'] ) ) {
            $days_before = max( 0, min( 30, intval( $data['dps_finance_reminder_days_before'] ) ) );
            update_option( 'dps_finance_reminder_days_before', $days_before );
        }

        if ( isset( $data['dps_finance_reminder_days_after'] ) ) {
            $days_after = max( 0, min( 30, intval( $data['dps_finance_reminder_days_after'] ) ) );
            update_option( 'dps_finance_reminder_days_after', $days_after );
        }

        // Mensagens
        if ( isset( $data['dps_finance_reminder_message_before'] ) ) {
            update_option( 'dps_finance_reminder_message_before', sanitize_textarea_field( $data['dps_finance_reminder_message_before'] ) );
        }

        if ( isset( $data['dps_finance_reminder_message_after'] ) ) {
            update_option( 'dps_finance_reminder_message_after', sanitize_textarea_field( $data['dps_finance_reminder_message_after'] ) );
        }
    }
}

// Inicializa
DPS_Finance_Reminders::init();
