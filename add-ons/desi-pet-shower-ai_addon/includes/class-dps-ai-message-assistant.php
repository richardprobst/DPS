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

        // Parse da resposta para separar assunto e corpo
        // Esperamos formato: "ASSUNTO: texto\n\nCORPO: texto"
        $parsed = self::parse_email_response( $response );

        if ( null === $parsed ) {
            dps_ai_log_error( 'Message Assistant: Erro ao fazer parse da resposta de e-mail' );
            return null;
        }

        return $parsed;
    }

    /**
     * Monta o system prompt específico para comunicações.
     *
     * @param string $channel Canal de comunicação ('whatsapp' ou 'email').
     * @param string $type    Tipo de mensagem.
     *
     * @return string System prompt.
     */
    private static function build_message_system_prompt( $channel, $type ) {
        $type_label = self::MESSAGE_TYPES[ $type ] ?? 'Comunicação genérica';

        $prompt = "Você está ajudando a criar uma mensagem de {$type_label} para um cliente do DPS by PRObst.\n\n";

        if ( 'whatsapp' === $channel ) {
            $prompt .= "IMPORTANTE SOBRE O FORMATO:\n";
            $prompt .= "- Gere APENAS o texto da mensagem, sem remetente, cabeçalho ou rodapé.\n";
            $prompt .= "- Seja objetivo, amigável e direto.\n";
            $prompt .= "- Use emojis com moderação (1-2 no máximo, se apropriado).\n";
            $prompt .= "- Máximo de 2-3 parágrafos curtos.\n";
            $prompt .= "- Evite saudações muito formais.\n";
            $prompt .= "- Use tom conversacional adequado para WhatsApp.\n\n";
        } else {
            $prompt .= "IMPORTANTE SOBRE O FORMATO:\n";
            $prompt .= "- Gere o e-mail no formato: ASSUNTO: [texto do assunto]\n\nCORPO: [texto do corpo]\n";
            $prompt .= "- O assunto deve ser curto e objetivo (máximo 60 caracteres).\n";
            $prompt .= "- O corpo pode ter mais detalhes, mas seja conciso.\n";
            $prompt .= "- Use tom profissional mas amigável.\n";
            $prompt .= "- Inclua saudação inicial e despedida.\n";
            $prompt .= "- Não use emojis no e-mail.\n\n";
        }

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
     * @param string $response Resposta da IA.
     *
     * @return array|null Array com ['subject' => '...', 'body' => '...'] ou null.
     */
    private static function parse_email_response( $response ) {
        // Tenta fazer parse no formato esperado: "ASSUNTO: ...\n\nCORPO: ..."
        $subject = '';
        $body    = '';

        // Pattern 1: ASSUNTO: ... CORPO: ...
        if ( preg_match( '/ASSUNTO:\s*(.+?)[\r\n]+.*?CORPO:\s*(.+)/is', $response, $matches ) ) {
            $subject = trim( $matches[1] );
            $body    = trim( $matches[2] );
        }
        // Pattern 2: Subject: ... Body: ... (inglês)
        elseif ( preg_match( '/Subject:\s*(.+?)[\r\n]+.*?Body:\s*(.+)/is', $response, $matches ) ) {
            $subject = trim( $matches[1] );
            $body    = trim( $matches[2] );
        }
        // Fallback: se não encontrou o formato esperado, tenta dividir por linhas vazias
        else {
            $lines = preg_split( '/\r?\n\r?\n/', $response, 2 );
            if ( count( $lines ) >= 2 ) {
                // Primeira linha/parágrafo como assunto
                $subject = trim( strip_tags( $lines[0] ) );
                // Remove "ASSUNTO:" ou "Subject:" se estiver lá
                $subject = preg_replace( '/^(ASSUNTO|Subject):\s*/i', '', $subject );
                // Resto como corpo
                $body = trim( $lines[1] );
                // Remove "CORPO:" ou "Body:" se estiver lá
                $body = preg_replace( '/^(CORPO|Body):\s*/i', '', $body );
            } else {
                // Se não conseguiu dividir, usa tudo como corpo e gera assunto genérico
                $subject = 'Mensagem do DPS by PRObst';
                $body    = trim( $response );
            }
        }

        // Valida que temos conteúdo
        if ( empty( $subject ) && empty( $body ) ) {
            return null;
        }

        // Se só temos body, gera subject genérico
        if ( empty( $subject ) ) {
            $subject = 'Comunicado do DPS by PRObst';
        }

        return [
            'subject' => $subject,
            'body'    => $body,
        ];
    }
}
