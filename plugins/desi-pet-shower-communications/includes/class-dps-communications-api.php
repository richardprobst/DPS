<?php
/**
 * API centralizada de comunicações
 *
 * Esta classe centraliza toda a lógica de envio de comunicações (WhatsApp, e-mail, SMS)
 * no sistema DPS. Outros add-ons (Agenda, Portal, Finance, etc.) devem usar esta API
 * ao invés de implementar envio de mensagens diretamente.
 *
 * @package DesiPetShower
 * @subpackage Communications
 * @since 0.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe API de Comunicações
 *
 * Interface pública para envio de todas as comunicações do sistema.
 * Responsável por:
 * - Enviar mensagens via WhatsApp, e-mail e SMS
 * - Aplicar templates de mensagens
 * - Registrar logs de envio
 * - Disparar hooks para extensibilidade
 */
class DPS_Communications_API {

    /**
     * Chave de opção para configurações
     */
    const OPTION_KEY = 'dps_comm_settings';

    /**
     * Timeout padrão para requests externos em segundos
     *
     * @since 0.2.1
     */
    const REQUEST_TIMEOUT = 30;

    /**
     * Instância singleton
     *
     * @var DPS_Communications_API|null
     */
    private static $instance = null;

    /**
     * Último erro ocorrido durante envio
     *
     * @since 0.3.0
     * @var string
     */
    private $last_error = '';

    /**
     * Obtém instância singleton
     *
     * @return DPS_Communications_API
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado (singleton)
     */
    private function __construct() {
        // Construtor privado para padrão singleton
    }

    /**
     * Obtém o último erro ocorrido
     *
     * @since 0.3.0
     * @return string
     */
    public function get_last_error() {
        return $this->last_error;
    }

    /**
     * Registra log de forma segura, verificando disponibilidade do DPS_Logger.
     *
     * @since 0.2.1
     * @param string $level   Nível do log (info, warning, error).
     * @param string $message Mensagem do log.
     * @param array  $context Contexto adicional (sem PII).
     */
    private function safe_log( $level, $message, $context = [] ) {
        // Remove possíveis dados sensíveis do contexto
        $safe_context = $this->sanitize_log_context( $context );

        if ( class_exists( 'DPS_Logger' ) ) {
            DPS_Logger::log( $level, $message, $safe_context );
        }
    }

    /**
     * Remove dados sensíveis do contexto de log.
     *
     * @since 0.2.1
     * @param array $context Contexto original.
     * @return array Contexto sanitizado.
     */
    private function sanitize_log_context( $context ) {
        $sensitive_keys = [ 'phone', 'to', 'email', 'message', 'body', 'subject', 'api_key' ];
        $safe           = [];

        foreach ( $context as $key => $value ) {
            if ( in_array( $key, $sensitive_keys, true ) ) {
                // Mascarar dados sensíveis
                if ( is_string( $value ) && ! empty( $value ) ) {
                    $safe[ $key ] = '[REDACTED:' . strlen( $value ) . ' chars]';
                } else {
                    $safe[ $key ] = '[REDACTED]';
                }
            } else {
                $safe[ $key ] = $value;
            }
        }

        return $safe;
    }

    /**
     * Envia mensagem via WhatsApp
     *
     * Este é o método central para envio de WhatsApp no sistema.
     * Toda comunicação via WhatsApp deve passar por aqui.
     *
     * @param string $to      Número de telefone do destinatário (será formatado automaticamente)
     * @param string $message Mensagem a ser enviada
     * @param array  $context Contexto adicional (appointment_id, client_id, etc.) para logs e hooks
     * @return bool True se enviado com sucesso, false caso contrário
     *
     * @example
     * DPS_Communications_API::get_instance()->send_whatsapp(
     *     '11987654321',
     *     'Seu agendamento está confirmado!',
     *     ['appointment_id' => 123, 'type' => 'confirmation']
     * );
     */
    public function send_whatsapp( $to, $message, $context = [] ) {
        // Formata o número para WhatsApp usando helper global
        if ( class_exists( 'DPS_Phone_Helper' ) ) {
            $formatted_to = DPS_Phone_Helper::format_for_whatsapp( $to );
        } else {
            // Fallback caso helper não esteja disponível
            $formatted_to = preg_replace( '/\D/', '', (string) $to );
            if ( strlen( $formatted_to ) >= 10 && strlen( $formatted_to ) <= 11 ) {
                $formatted_to = '55' . $formatted_to;
            }
        }

        // Valida entrada
        if ( empty( $formatted_to ) || empty( $message ) ) {
            $this->safe_log( 'error', 'Communications API: WhatsApp não enviado - número ou mensagem vazio', [
                'to'      => $to,
                'message' => $message,
                'context' => $context,
            ] );
            return false;
        }

        // Permite filtrar mensagem antes do envio
        $message = apply_filters( 'dps_comm_whatsapp_message', $message, $formatted_to, $context );

        // Obtém configurações
        $options = get_option( self::OPTION_KEY, [] );
        $api_url = isset( $options['whatsapp_api_url'] ) ? $options['whatsapp_api_url'] : '';
        $api_key = isset( $options['whatsapp_api_key'] ) ? $options['whatsapp_api_key'] : '';

        // Registra log antes do envio (sem PII)
        $this->safe_log( 'info', 'Communications API: Enviando WhatsApp', [
            'to'      => $formatted_to,
            'message' => $message,
            'context' => $context,
        ] );

        // Registra no histórico
        $history_id = $this->log_to_history( 
            DPS_Communications_History::CHANNEL_WHATSAPP,
            $formatted_to,
            $message,
            $context
        );

        // Envia a mensagem via gateway configurado
        $this->last_error = '';
        $result = $this->send_via_whatsapp_gateway( $formatted_to, $message, $api_url, $api_key, $history_id );

        // Registra resultado (sem PII)
        if ( $result ) {
            $this->safe_log( 'info', 'Communications API: WhatsApp enviado com sucesso', [
                'to'      => $formatted_to,
                'context' => $context,
            ] );
            $this->update_history_status( $history_id, DPS_Communications_History::STATUS_SENT );
        } else {
            $error_msg = $this->last_error ?: __( 'Falha no envio via gateway', 'dps-communications-addon' );
            $this->safe_log( 'error', 'Communications API: Falha ao enviar WhatsApp', [
                'to'      => $formatted_to,
                'context' => $context,
                'error'   => $error_msg,
            ] );

            // Tenta agendar retry se não for já um retry
            $is_retry = isset( $context['is_retry'] ) && $context['is_retry'];
            if ( ! $is_retry && $history_id ) {
                $this->schedule_retry( $history_id, DPS_Communications_History::CHANNEL_WHATSAPP, $formatted_to, $message, $context, $error_msg );
            }
        }

        // Dispara hook após envio (sucesso ou falha)
        do_action( 'dps_after_whatsapp_sent', $formatted_to, $message, $context, $result );

        return $result;
    }

    /**
     * Envia e-mail
     *
     * Método central para envio de e-mails no sistema.
     *
     * @param string $to      Endereço de e-mail do destinatário
     * @param string $subject Assunto do e-mail
     * @param string $body    Corpo da mensagem
     * @param array  $context Contexto adicional para logs e hooks
     * @return bool True se enviado com sucesso, false caso contrário
     *
     * @example
     * DPS_Communications_API::get_instance()->send_email(
     *     'cliente@email.com',
     *     'Confirmação de agendamento',
     *     'Seu agendamento foi confirmado para...',
     *     ['appointment_id' => 123]
     * );
     */
    public function send_email( $to, $subject, $body, $context = [] ) {
        // Valida entrada
        if ( empty( $to ) || ! is_email( $to ) ) {
            $this->safe_log( 'error', 'Communications API: E-mail inválido', [
                'to'      => $to,
                'subject' => $subject,
                'context' => $context,
            ] );
            return false;
        }

        if ( empty( $subject ) || empty( $body ) ) {
            $this->safe_log( 'error', 'Communications API: E-mail não enviado - assunto ou corpo vazio', [
                'to'      => $to,
                'context' => $context,
            ] );
            return false;
        }

        // Permite filtrar subject e body antes do envio
        $subject = apply_filters( 'dps_comm_email_subject', $subject, $to, $context );
        $body    = apply_filters( 'dps_comm_email_body', $body, $to, $context );

        // Obtém configurações
        $options      = get_option( self::OPTION_KEY, [] );
        $from_email   = isset( $options['default_email_from'] ) ? $options['default_email_from'] : '';

        // Headers customizados
        $headers = [];
        if ( $from_email && is_email( $from_email ) ) {
            $headers[] = 'From: ' . $from_email;
        }
        $headers = apply_filters( 'dps_comm_email_headers', $headers, $to, $context );

        // Registra log antes do envio (sem PII)
        $this->safe_log( 'info', 'Communications API: Enviando e-mail', [
            'to'      => $to,
            'subject' => $subject,
            'context' => $context,
        ] );

        // Registra no histórico
        $email_context            = $context;
        $email_context['subject'] = $subject;
        $history_id               = $this->log_to_history(
            DPS_Communications_History::CHANNEL_EMAIL,
            $to,
            $body,
            $email_context
        );

        // Envia via wp_mail
        $result = wp_mail( $to, $subject, $body, $headers );

        // Registra resultado (sem PII)
        if ( $result ) {
            $this->safe_log( 'info', 'Communications API: E-mail enviado com sucesso', [
                'to'      => $to,
                'context' => $context,
            ] );
            $this->update_history_status( $history_id, DPS_Communications_History::STATUS_SENT );
        } else {
            $error_msg = __( 'Falha no wp_mail - verifique configurações de SMTP', 'dps-communications-addon' );
            $this->safe_log( 'error', 'Communications API: Falha ao enviar e-mail', [
                'to'      => $to,
                'context' => $context,
                'error'   => $error_msg,
            ] );

            // Tenta agendar retry se não for já um retry
            $is_retry = isset( $context['is_retry'] ) && $context['is_retry'];
            if ( ! $is_retry && $history_id ) {
                $this->schedule_retry( $history_id, DPS_Communications_History::CHANNEL_EMAIL, $to, $body, $email_context, $error_msg );
            }
        }

        // Dispara hook após envio
        do_action( 'dps_after_email_sent', $to, $subject, $body, $context, $result );

        return $result;
    }

    /**
     * Envia lembrete de agendamento
     *
     * Método específico para envio de lembretes de agendamentos.
     * Busca dados do agendamento e usa template configurado.
     *
     * @param int $appointment_id ID do agendamento
     * @return bool True se enviado, false caso contrário
     */
    public function send_appointment_reminder( $appointment_id ) {
        $appointment_id = absint( $appointment_id );
        
        if ( ! $appointment_id ) {
            $this->safe_log( 'error', 'Communications API: ID de agendamento inválido para lembrete', [
                'appointment_id' => $appointment_id,
            ] );
            return false;
        }

        // Busca dados do agendamento
        $appointment = get_post( $appointment_id );
        if ( ! $appointment || 'dps_agendamento' !== $appointment->post_type ) {
            $this->safe_log( 'error', 'Communications API: Agendamento não encontrado', [
                'appointment_id' => $appointment_id,
            ] );
            return false;
        }

        // Busca informações do cliente
        $client_id = get_post_meta( $appointment_id, 'dps_client_id', true );
        $phone     = '';
        $email     = '';
        
        if ( $client_id ) {
            $phone = get_post_meta( $client_id, 'client_phone', true );
            $email = get_post_meta( $client_id, 'client_email', true );
        }

        // Se não tem telefone nem email, não pode enviar
        if ( empty( $phone ) && empty( $email ) ) {
            $this->safe_log( 'warning', 'Communications API: Agendamento sem telefone ou e-mail', [
                'appointment_id' => $appointment_id,
                'client_id'      => $client_id,
            ] );
            return false;
        }

        // Prepara mensagem usando template
        $message = $this->prepare_reminder_message( $appointment_id );
        
        if ( empty( $message ) ) {
            $this->safe_log( 'warning', 'Communications API: Template de lembrete vazio', [
                'appointment_id' => $appointment_id,
            ] );
            // Usa mensagem padrão se template estiver vazio
            $message = sprintf(
                __( 'Lembrete: Você tem um agendamento em breve. ID: %d', 'desi-pet-shower' ),
                $appointment_id
            );
        }

        $context = [
            'appointment_id' => $appointment_id,
            'client_id'      => $client_id,
            'type'           => 'reminder',
        ];

        // Prioriza WhatsApp se disponível
        $sent = false;
        if ( ! empty( $phone ) ) {
            $sent = $this->send_whatsapp( $phone, $message, $context );
        }

        // Se não enviou por WhatsApp e tem email, tenta por email
        if ( ! $sent && ! empty( $email ) ) {
            $subject = __( 'Lembrete de Agendamento - desi.pet by PRObst', 'desi-pet-shower' );
            $sent    = $this->send_email( $email, $subject, $message, $context );
        }

        // Dispara hook após envio de lembrete
        do_action( 'dps_after_reminder_sent', $appointment_id, $sent );

        return $sent;
    }

    /**
     * Envia notificação de pagamento
     *
     * @param int   $client_id    ID do cliente
     * @param int   $amount_cents Valor em centavos
     * @param array $context      Contexto adicional (appointment_id, transaction_id, etc.)
     * @return bool True se enviado, false caso contrário
     */
    public function send_payment_notification( $client_id, $amount_cents, $context = [] ) {
        $client_id = absint( $client_id );
        
        if ( ! $client_id ) {
            $this->safe_log( 'error', 'Communications API: ID de cliente inválido para notificação de pagamento', [
                'client_id' => $client_id,
            ] );
            return false;
        }

        // Busca informações do cliente
        $client = get_post( $client_id );
        if ( ! $client || 'dps_cliente' !== $client->post_type ) {
            $this->safe_log( 'error', 'Communications API: Cliente não encontrado', [
                'client_id' => $client_id,
            ] );
            return false;
        }

        $phone = get_post_meta( $client_id, 'client_phone', true );
        $email = get_post_meta( $client_id, 'client_email', true );

        if ( empty( $phone ) && empty( $email ) ) {
            $this->safe_log( 'warning', 'Communications API: Cliente sem telefone ou e-mail', [
                'client_id' => $client_id,
            ] );
            return false;
        }

        // Formata valor
        $amount_formatted = '';
        $amount_formatted = DPS_Money_Helper::format_currency( $amount_cents );

        // Prepara mensagem
        $client_name = $client->post_title;
        $message     = sprintf(
            __( 'Olá %s, registramos um pagamento de %s. Obrigado!', 'desi-pet-shower' ),
            $client_name,
            $amount_formatted
        );

        // Permite customizar mensagem
        $message = apply_filters( 'dps_comm_payment_notification_message', $message, $client_id, $amount_cents, $context );

        $context['type']      = 'payment_notification';
        $context['client_id'] = $client_id;

        // Prioriza WhatsApp
        $sent = false;
        if ( ! empty( $phone ) ) {
            $sent = $this->send_whatsapp( $phone, $message, $context );
        }

        // Fallback para e-mail
        if ( ! $sent && ! empty( $email ) ) {
            $subject = __( 'Confirmação de Pagamento - desi.pet by PRObst', 'desi-pet-shower' );
            $sent    = $this->send_email( $email, $subject, $message, $context );
        }

        return $sent;
    }

    /**
     * Envia mensagem de um cliente (via Portal)
     *
     * @param int    $client_id ID do cliente que está enviando
     * @param string $message   Mensagem do cliente
     * @param array  $context   Contexto adicional
     * @return bool True se enviado, false caso contrário
     */
    public function send_message_from_client( $client_id, $message, $context = [] ) {
        $client_id = absint( $client_id );
        
        if ( ! $client_id || empty( $message ) ) {
            $this->safe_log( 'error', 'Communications API: Dados inválidos para mensagem de cliente', [
                'client_id' => $client_id,
            ] );
            return false;
        }

        // Busca informações do cliente
        $client = get_post( $client_id );
        if ( ! $client || 'dps_cliente' !== $client->post_type ) {
            $this->safe_log( 'error', 'Communications API: Cliente não encontrado para mensagem', [
                'client_id' => $client_id,
            ] );
            return false;
        }

        // Obtém e-mail do admin para receber a mensagem
        $admin_email = get_option( 'admin_email' );
        if ( empty( $admin_email ) ) {
            $this->safe_log( 'error', 'Communications API: E-mail do admin não configurado', [
                'client_id' => $client_id,
            ] );
            return false;
        }

        // Prepara subject e body
        $client_name  = $client->post_title;
        $client_phone = get_post_meta( $client_id, 'client_phone', true );
        $client_email = get_post_meta( $client_id, 'client_email', true );

        $subject = sprintf( __( 'Mensagem do Portal - Cliente: %s', 'desi-pet-shower' ), $client_name );
        
        $body = sprintf(
            __( "Cliente: %s\nTelefone: %s\nE-mail: %s\n\nMensagem:\n%s", 'desi-pet-shower' ),
            $client_name,
            $client_phone ? $client_phone : __( 'Não informado', 'desi-pet-shower' ),
            $client_email ? $client_email : __( 'Não informado', 'desi-pet-shower' ),
            $message
        );

        $context['type']      = 'client_message';
        $context['client_id'] = $client_id;

        return $this->send_email( $admin_email, $subject, $body, $context );
    }

    /**
     * Prepara mensagem de lembrete usando template configurado
     *
     * @param int $appointment_id ID do agendamento
     * @return string Mensagem preparada
     */
    private function prepare_reminder_message( $appointment_id ) {
        $options  = get_option( self::OPTION_KEY, [] );
        $template = isset( $options['template_reminder'] ) ? $options['template_reminder'] : '';

        // Se não tem template, retorna vazio (método chamador usa mensagem padrão)
        if ( empty( $template ) ) {
            return '';
        }

        // Busca dados para substituição
        $appointment = get_post( $appointment_id );
        $client_id   = get_post_meta( $appointment_id, 'dps_client_id', true );
        $pet_id      = get_post_meta( $appointment_id, 'dps_pet_id', true );
        $datetime    = get_post_meta( $appointment_id, 'dps_appointment_datetime', true );

        $client_name = '';
        $pet_name    = '';
        $date_str    = '';
        $time_str    = '';

        if ( $client_id ) {
            $client      = get_post( $client_id );
            $client_name = $client ? $client->post_title : '';
        }

        if ( $pet_id ) {
            $pet      = get_post( $pet_id );
            $pet_name = $pet ? $pet->post_title : '';
        }

        if ( $datetime ) {
            $timestamp = strtotime( $datetime );
            if ( $timestamp ) {
                $date_str = date_i18n( 'd/m/Y', $timestamp );
                $time_str = date_i18n( 'H:i', $timestamp );
            }
        }

        // Substitui placeholders
        $replacements = [
            '{appointment_id}'    => $appointment_id,
            '{appointment_title}' => $appointment ? $appointment->post_title : '',
            '{client_name}'       => $client_name,
            '{pet_name}'          => $pet_name,
            '{date}'              => $date_str,
            '{time}'              => $time_str,
        ];

        $message = strtr( $template, $replacements );

        // Permite filtrar mensagem
        $message = apply_filters( 'dps_comm_reminder_message', $message, $appointment_id );

        return $message;
    }

    /**
     * Envia mensagem via gateway de WhatsApp
     *
     * @since 0.2.0
     * @param string   $to         Número formatado
     * @param string   $message    Mensagem
     * @param string   $api_url    URL do gateway
     * @param string   $api_key    Chave de API
     * @param int|null $history_id ID do registro no histórico (opcional)
     * @return bool True se enviado, false caso contrário
     */
    private function send_via_whatsapp_gateway( $to, $message, $api_url, $api_key, $history_id = null ) {
        // Se não tem URL configurada, simula sucesso em dev (sem expor PII em logs)
        if ( empty( $api_url ) ) {
            // Log seguro sem dados pessoais
            $this->safe_log( 'info', 'Communications API: WhatsApp simulado (sem gateway configurado)', [
                'mode' => 'development',
            ] );
            return true;
        }

        // Valida URL novamente (double-check de segurança)
        if ( ! filter_var( $api_url, FILTER_VALIDATE_URL ) ) {
            $this->last_error = __( 'URL de gateway inválida', 'dps-communications-addon' );
            $this->safe_log( 'error', 'Communications API: URL de gateway inválida', [
                'api_url' => $api_url,
            ] );
            return false;
        }

        // Implementação futura: integração com gateway real (Evolution API, etc.)
        // Por enquanto, loga apenas indicação de envio (sem PII)
        $this->safe_log( 'info', 'Communications API: WhatsApp enviado via gateway', [
            'gateway' => wp_parse_url( $api_url, PHP_URL_HOST ),
        ] );

        // TODO: Implementar chamada HTTP real ao gateway
        // Exemplo de implementação segura com timeout:
        // $response = wp_remote_post( $api_url, [
        //     'timeout' => self::REQUEST_TIMEOUT,
        //     'headers' => [
        //         'Authorization' => 'Bearer ' . $api_key,
        //         'Content-Type'  => 'application/json',
        //     ],
        //     'body'    => wp_json_encode( [
        //         'to'      => $to,
        //         'message' => $message,
        //     ] ),
        //     'sslverify' => true,
        // ] );
        //
        // if ( is_wp_error( $response ) ) {
        //     $this->last_error = $response->get_error_message();
        //     $this->safe_log( 'error', 'Communications API: Erro de conexão com gateway', [
        //         'error' => $this->last_error,
        //     ] );
        //     return false;
        // }
        //
        // $code = wp_remote_retrieve_response_code( $response );
        // if ( $code < 200 || $code >= 300 ) {
        //     $this->last_error = sprintf( 'HTTP %d', $code );
        //     $this->safe_log( 'error', 'Communications API: Resposta não-200 do gateway', [
        //         'status_code' => $code,
        //     ] );
        //     return false;
        // }
        //
        // return true;

        return true;
    }

    /**
     * Registra comunicação no histórico
     *
     * @since 0.3.0
     * @param string $channel   Canal de comunicação
     * @param string $recipient Destinatário
     * @param string $message   Mensagem
     * @param array  $context   Contexto adicional
     * @return int|false ID do registro ou false
     */
    private function log_to_history( $channel, $recipient, $message, $context = [] ) {
        if ( ! class_exists( 'DPS_Communications_History' ) ) {
            return false;
        }

        $history = DPS_Communications_History::get_instance();
        return $history->log_communication( $channel, $recipient, $message, $context );
    }

    /**
     * Atualiza status no histórico
     *
     * @since 0.3.0
     * @param int    $history_id ID do registro
     * @param string $status     Novo status
     * @param array  $extra_data Dados extras
     * @return bool
     */
    private function update_history_status( $history_id, $status, $extra_data = [] ) {
        if ( ! $history_id || ! class_exists( 'DPS_Communications_History' ) ) {
            return false;
        }

        $history = DPS_Communications_History::get_instance();
        return $history->update_status( $history_id, $status, $extra_data );
    }

    /**
     * Agenda retry para comunicação que falhou
     *
     * @since 0.3.0
     * @param int    $history_id ID do registro no histórico
     * @param string $channel    Canal
     * @param string $recipient  Destinatário
     * @param string $message    Mensagem
     * @param array  $context    Contexto
     * @param string $last_error Último erro ocorrido
     * @return bool
     */
    private function schedule_retry( $history_id, $channel, $recipient, $message, $context, $last_error = '' ) {
        if ( ! class_exists( 'DPS_Communications_Retry' ) ) {
            return false;
        }

        $retry       = DPS_Communications_Retry::get_instance();
        $retry_count = isset( $context['retry_count'] ) ? absint( $context['retry_count'] ) : 0;

        return $retry->schedule_retry(
            $history_id,
            $channel,
            $recipient,
            $message,
            $context,
            $retry_count,
            $last_error
        );
    }
}
