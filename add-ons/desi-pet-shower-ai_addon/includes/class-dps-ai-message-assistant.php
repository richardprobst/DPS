<?php
/**
 * Assistente de IA para Comunicações do DPS.
 *
 * Este arquivo contém a classe responsável por gerar sugestões de mensagens
 * para WhatsApp e e-mail usando IA, sem nunca enviar automaticamente.
 *
 * REGRA ABSOLUTA: IA NUNCA envia mensagens. Apenas SUGERE textos.
 * O usuário humano SEMPRE revisa e confirma antes de qualquer envio.
 *
 * @package DPS_AI_Addon
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe Assistente de IA para Mensagens.
 *
 * Gera sugestões de comunicações (WhatsApp e e-mail) baseadas em contexto,
 * sem nunca enviar automaticamente. Usuário sempre revisa antes de enviar.
 */
class DPS_AI_Message_Assistant {

    /**
     * Tipos de mensagens suportados.
     *
     * @var array
     */
    const MESSAGE_TYPES = [
        'lembrete'        => 'Lembrete de agendamento',
        'confirmacao'     => 'Confirmação de agendamento',
        'pos_atendimento' => 'Pós-atendimento / Agradecimento',
        'cobranca_suave'  => 'Cobrança educada / Lembrete de pagamento',
        'cancelamento'    => 'Notificação de cancelamento',
        'reagendamento'   => 'Confirmação de reagendamento',
    ];

    /**
     * Gera sugestão de mensagem para WhatsApp.
     *
     * @param array $context Contexto da mensagem contendo:
     *                       - 'type' (string): Tipo de mensagem (lembrete, confirmacao, etc.)
     *                       - 'client_name' (string): Nome do cliente
     *                       - 'client_phone' (string): Telefone do cliente
     *                       - 'pet_name' (string): Nome do pet
     *                       - 'appointment_date' (string): Data do agendamento (formato legível)
     *                       - 'appointment_time' (string): Hora do agendamento
     *                       - 'services' (array): Lista de nomes de serviços
     *                       - 'groomer_name' (string, opcional): Nome do groomer
     *                       - 'amount' (string, opcional): Valor formatado (para cobranças)
     *                       - 'additional_info' (string, opcional): Informações adicionais
     *
     * @return array|null Array com ['text' => 'mensagem'] ou null em caso de erro.
     */
    public static function suggest_whatsapp_message( array $context ) {
        // Valida contexto mínimo
        if ( empty( $context['type'] ) ) {
            dps_ai_log_error( 'Message Assistant: Tipo de mensagem não especificado' );
            return null;
        }

        // Monta o prompt específico para a mensagem
        $system_prompt = self::build_message_system_prompt( 'whatsapp', $context['type'] );
        $user_prompt   = self::build_user_prompt_from_context( $context );

        if ( empty( $system_prompt ) || empty( $user_prompt ) ) {
            dps_ai_log_error( 'Message Assistant: Erro ao montar prompts' );
            return null;
        }

        // Monta array de mensagens
        $messages = [];

        // 1. System prompt base (regras de segurança e escopo)
        $messages[] = [
            'role'    => 'system',
            'content' => DPS_AI_Assistant::get_base_system_prompt(),
        ];

        // 2. Instruções adicionais do admin (se houver)
        $settings           = get_option( 'dps_ai_settings', [] );
        $extra_instructions = ! empty( $settings['additional_instructions'] ) ? trim( $settings['additional_instructions'] ) : '';

        if ( $extra_instructions !== '' ) {
            $messages[] = [
                'role'    => 'system',
                'content' => 'Instruções adicionais definidas pelo administrador do DPS by PRObst: ' . $extra_instructions,
            ];
        }

        // 3. System prompt específico para comunicações
        $messages[] = [
            'role'    => 'system',
            'content' => $system_prompt,
        ];

        // 4. Contexto e solicitação do usuário
        $messages[] = [
            'role'    => 'user',
            'content' => $user_prompt,
        ];

        // Opções específicas para mensagens (textos mais curtos)
        $options = [
            'max_tokens'  => 300, // Mensagens devem ser concisas
            'temperature' => 0.5, // Levemente mais criativo para tom amigável
        ];

        // Chama a API da OpenAI
        $response = DPS_AI_Client::chat( $messages, $options );

        // Se falhou, retorna null
        if ( null === $response ) {
            return null;
        }

        return [ 'text' => $response ];
    }

    /**
     * Gera sugestão de e-mail (assunto e corpo).
     *
     * @param array $context Contexto da mensagem (mesmos campos do WhatsApp).
     *
     * @return array|null Array com ['subject' => 'assunto', 'body' => 'corpo'] ou null em caso de erro.
     */
    public static function suggest_email_message( array $context ) {
        // Valida contexto mínimo
        if ( empty( $context['type'] ) ) {
            dps_ai_log_error( 'Message Assistant: Tipo de mensagem não especificado' );
            return null;
        }

        // Monta o prompt específico para e-mail
        $system_prompt = self::build_message_system_prompt( 'email', $context['type'] );
        $user_prompt   = self::build_user_prompt_from_context( $context );

        if ( empty( $system_prompt ) || empty( $user_prompt ) ) {
            dps_ai_log_error( 'Message Assistant: Erro ao montar prompts' );
            return null;
        }

        // Monta array de mensagens
        $messages = [];

        // 1. System prompt base
        $messages[] = [
            'role'    => 'system',
            'content' => DPS_AI_Assistant::get_base_system_prompt(),
        ];

        // 2. Instruções adicionais do admin (se houver)
        $settings           = get_option( 'dps_ai_settings', [] );
        $extra_instructions = ! empty( $settings['additional_instructions'] ) ? trim( $settings['additional_instructions'] ) : '';

        if ( $extra_instructions !== '' ) {
            $messages[] = [
                'role'    => 'system',
                'content' => 'Instruções adicionais definidas pelo administrador do DPS by PRObst: ' . $extra_instructions,
            ];
        }

        // 3. System prompt específico para e-mail
        $messages[] = [
            'role'    => 'system',
            'content' => $system_prompt,
        ];

        // 4. Contexto e solicitação do usuário
        $messages[] = [
            'role'    => 'user',
            'content' => $user_prompt,
        ];

        // Opções específicas para e-mails (podem ser um pouco mais longos)
        $options = [
            'max_tokens'  => 500, // E-mails podem ter mais contexto
            'temperature' => 0.5,
        ];

        // Chama a API da OpenAI
        $response = DPS_AI_Client::chat( $messages, $options );

        // Se falhou, retorna null
        if ( null === $response ) {
            return null;
        }

        // Parse da resposta usando parser robusto
        // NOVO: Usa DPS_AI_Email_Parser para parsing defensivo
        $parsed = DPS_AI_Email_Parser::parse(
            $response,
            [
                'default_subject'    => 'Comunicado do DPS by PRObst',
                'max_subject_length' => 200,
                'strip_html'         => false,
                'format_hint'        => 'labeled', // IA deve retornar com rótulos
            ]
        );

        if ( null === $parsed ) {
            dps_ai_log_error( 'Message Assistant: Erro crítico ao fazer parse da resposta de e-mail' );
            return null;
        }

        // Log de estatísticas do parse para monitoramento
        $stats = DPS_AI_Email_Parser::get_parse_stats( $parsed );
        dps_ai_log_info( 'Message Assistant: Email parsed successfully', $stats );

        return [
            'subject' => $parsed['subject'],
            'body'    => $parsed['body'],
        ];
    }

    /**
     * Monta o system prompt específico para comunicações.
     *
     * IMPORTANTE: Agora utiliza DPS_AI_Prompts::get() como base e adiciona
     * instruções específicas do tipo de mensagem.
     *
     * @param string $channel Canal de comunicação ('whatsapp' ou 'email').
     * @param string $type    Tipo de mensagem.
     *
     * @return string System prompt.
     */
    private static function build_message_system_prompt( $channel, $type ) {
        // Carrega o prompt base do contexto (whatsapp ou email)
        $base_prompt = DPS_AI_Prompts::get( $channel, [ 'type' => $type ] );
        
        $type_label = self::MESSAGE_TYPES[ $type ] ?? 'Comunicação genérica';

        $prompt = $base_prompt . "\n\n";
        $prompt .= "CONTEXTO ATUAL:\n";
        $prompt .= "Você está ajudando a criar uma mensagem de {$type_label} para um cliente.\n\n";

        // Orientações específicas por tipo de mensagem
        switch ( $type ) {
            case 'lembrete':
                $prompt .= "ORIENTAÇÕES PARA LEMBRETE:\n";
                $prompt .= "- Relembre data e hora do agendamento.\n";
                $prompt .= "- Mencione o nome do pet e serviços agendados.\n";
                $prompt .= "- Seja amigável e prestativo.\n";
                $prompt .= "- Ofereça ajuda para reagendar se necessário.\n";
                break;

            case 'confirmacao':
                $prompt .= "ORIENTAÇÕES PARA CONFIRMAÇÃO:\n";
                $prompt .= "- Confirme que o agendamento foi registrado com sucesso.\n";
                $prompt .= "- Inclua data, hora, pet e serviços.\n";
                $prompt .= "- Agradeça pela preferência.\n";
                $prompt .= "- Informe como entrar em contato se precisar.\n";
                break;

            case 'pos_atendimento':
                $prompt .= "ORIENTAÇÕES PARA PÓS-ATENDIMENTO:\n";
                $prompt .= "- Agradeça pela visita.\n";
                $prompt .= "- Pergunte se ficou satisfeito com o serviço.\n";
                $prompt .= "- Convide para retornar.\n";
                $prompt .= "- Seja caloroso e genuíno.\n";
                break;

            case 'cobranca_suave':
                $prompt .= "ORIENTAÇÕES PARA COBRANÇA:\n";
                $prompt .= "- Seja educado e respeitoso.\n";
                $prompt .= "- Informe que há um valor pendente.\n";
                $prompt .= "- Mencione o valor e o serviço relacionado.\n";
                $prompt .= "- Ofereça formas de pagamento.\n";
                $prompt .= "- Evite tom acusatório ou agressivo.\n";
                break;

            case 'cancelamento':
                $prompt .= "ORIENTAÇÕES PARA CANCELAMENTO:\n";
                $prompt .= "- Confirme o cancelamento do agendamento.\n";
                $prompt .= "- Seja empático e compreensivo.\n";
                $prompt .= "- Convide para reagendar quando for conveniente.\n";
                $prompt .= "- Mantenha tom positivo.\n";
                break;

            case 'reagendamento':
                $prompt .= "ORIENTAÇÕES PARA REAGENDAMENTO:\n";
                $prompt .= "- Confirme a nova data e hora.\n";
                $prompt .= "- Mencione pet e serviços.\n";
                $prompt .= "- Agradeça pela compreensão.\n";
                $prompt .= "- Reforce que estamos à disposição.\n";
                break;
        }

        return $prompt;
    }

    /**
     * Monta o prompt do usuário a partir do contexto fornecido.
     *
     * @param array $context Contexto da mensagem.
     *
     * @return string User prompt.
     */
    private static function build_user_prompt_from_context( array $context ) {
        $prompt = "Por favor, gere a mensagem com base nas seguintes informações:\n\n";

        if ( ! empty( $context['client_name'] ) ) {
            $prompt .= "Cliente: " . $context['client_name'] . "\n";
        }

        if ( ! empty( $context['pet_name'] ) ) {
            $prompt .= "Pet: " . $context['pet_name'] . "\n";
        }

        if ( ! empty( $context['appointment_date'] ) ) {
            $prompt .= "Data: " . $context['appointment_date'] . "\n";
        }

        if ( ! empty( $context['appointment_time'] ) ) {
            $prompt .= "Hora: " . $context['appointment_time'] . "\n";
        }

        if ( ! empty( $context['services'] ) && is_array( $context['services'] ) ) {
            $prompt .= "Serviços: " . implode( ', ', $context['services'] ) . "\n";
        }

        if ( ! empty( $context['groomer_name'] ) ) {
            $prompt .= "Tosador/Groomer: " . $context['groomer_name'] . "\n";
        }

        if ( ! empty( $context['amount'] ) ) {
            $prompt .= "Valor: " . $context['amount'] . "\n";
        }

        if ( ! empty( $context['additional_info'] ) ) {
            $prompt .= "\nInformações adicionais: " . $context['additional_info'] . "\n";
        }

        return $prompt;
    }

    /**
     * Faz parse da resposta de e-mail para extrair assunto e corpo.
     *
     * @deprecated 1.6.1 Use DPS_AI_Email_Parser::parse() em vez disso.
     * @param string $response Resposta da IA.
     *
     * @return array|null Array com ['subject' => '...', 'body' => '...'] ou null.
     */
    private static function parse_email_response( $response ) {
        // DEPRECATED: Mantido apenas para retrocompatibilidade
        // Redireciona para o novo parser robusto
        dps_ai_log_warning( 'Message Assistant: Usando método parse_email_response() depreciado. Use DPS_AI_Email_Parser::parse()' );

        $parsed = DPS_AI_Email_Parser::parse( $response );

        if ( null === $parsed ) {
            return null;
        }

        return [
            'subject' => $parsed['subject'],
            'body'    => $parsed['body'],
        ];
    }
}
