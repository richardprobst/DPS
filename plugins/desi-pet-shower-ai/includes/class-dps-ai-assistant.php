<?php
/**
 * Assistente de IA do DPS.
 *
 * Este arquivo contém a classe responsável por todas as regras de negócio
 * da IA, incluindo o system prompt restritivo e a montagem de contexto.
 *
 * @package DPS_AI_Addon
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe Assistente de IA.
 *
 * Concentra TODAS as regras de comportamento da IA, incluindo:
 * - System prompt restritivo focado em Banho e Tosa
 * - Montagem de contexto do cliente/pet
 * - Filtro preventivo de perguntas fora do contexto
 * - Integração com DPS_AI_Client
 */
class DPS_AI_Assistant {

    /**
     * Palavras-chave relacionadas ao contexto permitido.
     *
     * @var array
     */
    const CONTEXT_KEYWORDS = [
        'pet', 'pets', 'cachorro', 'cao', 'cão', 'cães', 'gato', 'gatos',
        'banho', 'tosa', 'grooming', 'tosador', 'tosadora',
        'agendamento', 'agendamentos', 'agenda', 'agendar', 'marcar', 'horario', 'horário',
        'servico', 'serviço', 'servicos', 'serviços',
        'pagamento', 'pagamentos', 'pagar', 'pendencia', 'pendência', 'pendências', 'cobranca', 'cobrança',
        'portal', 'sistema', 'dps', 'desi',
        'assinatura', 'assinaturas', 'plano', 'planos', 'mensalidade',
        'fidelidade', 'pontos', 'recompensa', 'recompensas',
        'vacina', 'vacinas', 'vacinacao', 'vacinação',
        'historico', 'histórico', 'atendimento', 'atendimentos',
        'cliente', 'cadastro', 'dados', 'telefone', 'email', 'endereco', 'endereço',
        'raca', 'raça', 'porte', 'idade', 'peso', 'pelagem',
        'higiene', 'limpeza', 'cuidado', 'cuidados', 'saude', 'saúde',
    ];

    /**
     * Tempo de expiração do cache de contexto em segundos (5 minutos).
     *
     * @var int
     */
    const CONTEXT_CACHE_EXPIRATION = 300;

    /**
     * Responde a uma pergunta feita pelo cliente no Portal.
     *
     * SEGURANÇA (Isolamento de Dados):
     * - O $client_id é obtido via autenticação do portal (DPS_Client_Portal::get_current_client_id)
     * - Os $pet_ids são buscados filtrando por pet_client_id = $client_id
     * - O contexto é construído usando apenas dados do cliente autenticado
     * - Agendamentos são filtrados por appointment_client_id no banco de dados
     * - Transações são filtradas por cliente_id na tabela dps_transacoes
     * - Pontos de fidelidade são filtrados por loyalty_client_id
     *
     * Isso garante que o assistente de IA não tem acesso a dados de outros clientes.
     *
     * @param int    $client_id     ID do cliente autenticado no portal.
     * @param array  $pet_ids       IDs dos pets do cliente (validados como pertencentes ao cliente).
     * @param string $user_question Pergunta do usuário.
     *
     * @return string|null Resposta da IA ou null em caso de erro/indisponibilidade.
     */
    public static function answer_portal_question( $client_id, array $pet_ids, $user_question ) {
        // Sanitiza a pergunta
        $user_question = sanitize_text_field( $user_question );

        if ( empty( $user_question ) ) {
            return null;
        }

        // Filtro preventivo: verifica se a pergunta contém palavras-chave relacionadas ao contexto
        if ( ! self::is_question_in_context( $user_question ) ) {
            return __( 'Sou um assistente focado em ajudar com informações sobre o seu pet e os serviços do desi.pet by PRObst. Tente perguntar algo sobre seus agendamentos, serviços, histórico ou funcionalidades do portal.', 'dps-ai' );
        }

        // Monta contexto do cliente e pets (com cache)
        $context = self::get_cached_client_context( $client_id, $pet_ids );

        // Busca artigos relevantes da base de conhecimento
        $kb_context = '';
        if ( class_exists( 'DPS_AI_Knowledge_Base' ) ) {
            $relevant_articles = DPS_AI_Knowledge_Base::get_relevant_articles( $user_question, 5 );
            $kb_context = DPS_AI_Knowledge_Base::format_articles_for_context( $relevant_articles );
        }

        // Array de mensagens - começa com o prompt base
        $messages = [];
        
        // 1. Adiciona o system prompt base com instrução de idioma (sempre primeiro)
        $settings = get_option( 'dps_ai_settings', [] );
        $language = ! empty( $settings['language'] ) ? $settings['language'] : 'pt_BR';
        
        $messages[] = [
            'role'    => 'system',
            'content' => self::get_base_system_prompt_with_language( $language ),
        ];

        // 2. Verifica se há instruções adicionais configuradas
        $extra_instructions = ! empty( $settings['additional_instructions'] ) ? trim( $settings['additional_instructions'] ) : '';
        
        if ( $extra_instructions !== '' ) {
            $messages[] = [
                'role'    => 'system',
                'content' => 'Instruções adicionais definidas pelo administrador do desi.pet by PRObst: ' . $extra_instructions,
            ];
        }

        // 3. Adiciona a pergunta do usuário com contexto do cliente e base de conhecimento
        $user_content = $context;
        if ( ! empty( $kb_context ) ) {
            $user_content .= $kb_context;
        }
        $user_content .= "\n\nPergunta do cliente: " . $user_question;
        
        $messages[] = [
            'role'    => 'user',
            'content' => $user_content,
        ];

        // Chama a API da OpenAI
        $response = DPS_AI_Client::chat( $messages );

        // Se falhou, retorna null
        if ( null === $response ) {
            return null;
        }

        return $response;
    }

    /**
     * Verifica se a pergunta contém palavras-chave do contexto permitido.
     *
     * @param string $question Pergunta do usuário.
     *
     * @return bool True se a pergunta está no contexto, false caso contrário.
     */
    private static function is_question_in_context( $question ) {
        // Cast para string para compatibilidade com PHP 8.1+
        $question_lower = mb_strtolower( (string) $question, 'UTF-8' );

        foreach ( self::CONTEXT_KEYWORDS as $keyword ) {
            if ( false !== mb_strpos( $question_lower, $keyword ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retorna o prompt base do sistema.
     * 
     * IMPORTANTE: Este método agora utiliza DPS_AI_Prompts::get() para carregar
     * o prompt de arquivo e aplicar filtros, permitindo customização.
     * 
     * Mantido por retrocompatibilidade com código existente.
     *
     * @return string Conteúdo do prompt base do sistema.
     */
    public static function get_base_system_prompt() {
        // Usa a nova classe centralizada de prompts
        // Contexto 'portal' porque este método é usado principalmente no chat do portal
        return DPS_AI_Prompts::get( 'portal' );
    }

    /**
     * Retorna o prompt base do sistema com instrução de idioma.
     * 
     * Adiciona instrução explícita para que a IA responda no idioma configurado.
     *
     * @param string $language Código do idioma (pt_BR, en_US, es_ES, auto).
     * 
     * @return string Conteúdo do prompt base do sistema com instrução de idioma.
     */
    public static function get_base_system_prompt_with_language( $language = 'pt_BR' ) {
        $base_prompt = self::get_base_system_prompt();
        
        // Mapeia códigos de idioma para instruções claras
        $language_instructions = [
            'pt_BR' => 'IMPORTANTE: Você DEVE responder SEMPRE em Português do Brasil, mesmo que os artigos da base de conhecimento estejam em outro idioma. Adapte e traduza o conteúdo conforme necessário.',
            'en_US' => 'IMPORTANT: You MUST ALWAYS respond in English (US), even if the knowledge base articles are in another language. Adapt and translate the content as needed.',
            'es_ES' => 'IMPORTANTE: Usted DEBE responder SIEMPRE en Español, incluso si los artículos de la base de conocimiento están en otro idioma. Adapte y traduzca el contenido según sea necesario.',
            'auto'  => 'IMPORTANTE: Detecte automaticamente o idioma da pergunta do usuário e responda no mesmo idioma. Se artigos da base de conhecimento estiverem em outro idioma, traduza e adapte o conteúdo.',
        ];
        
        $instruction = isset( $language_instructions[ $language ] ) 
            ? $language_instructions[ $language ] 
            : $language_instructions['pt_BR'];
        
        return $base_prompt . "\n\n" . $instruction;
    }

    /**
     * Obtém contexto do cliente com cache via Transients.
     *
     * Cacheia o contexto por 5 minutos para evitar reconstrução repetitiva
     * a cada pergunta do mesmo cliente.
     *
     * @param int   $client_id ID do cliente.
     * @param array $pet_ids   IDs dos pets.
     *
     * @return string Contexto formatado (do cache ou recém-construído).
     */
    private static function get_cached_client_context( $client_id, array $pet_ids ) {
        // Gera chave única baseada no cliente e pets usando wp_hash para melhor unicidade
        $pets_string = implode( ',', array_map( 'absint', $pet_ids ) );
        $cache_key   = 'dps_ai_ctx_' . absint( $client_id ) . '_' . substr( wp_hash( $pets_string ), 0, 12 );

        // Tenta obter do cache (se não estiver desabilitado)
        if ( ! dps_is_cache_disabled() ) {
            $cached_context = get_transient( $cache_key );
            if ( false !== $cached_context ) {
                return $cached_context;
            }
        }

        // Cache miss: reconstrói contexto
        $context = self::build_client_context( $client_id, $pet_ids );

        // Salva no cache (se não estiver desabilitado)
        if ( ! dps_is_cache_disabled() ) {
            set_transient( $cache_key, $context, self::CONTEXT_CACHE_EXPIRATION );
        }

        return $context;
    }

    /**
     * Invalida o cache de contexto de um cliente.
     *
     * Deve ser chamado quando dados do cliente, pets ou agendamentos são alterados.
     *
     * @param int   $client_id ID do cliente.
     * @param array $pet_ids   IDs dos pets (opcional, se vazio limpa todos os caches do cliente).
     */
    public static function invalidate_context_cache( $client_id, array $pet_ids = [] ) {
        if ( ! empty( $pet_ids ) ) {
            // Invalida cache específico
            $cache_key = 'dps_ai_ctx_' . $client_id . '_' . md5( implode( ',', $pet_ids ) );
            delete_transient( $cache_key );
        }

        // Sempre tenta invalidar caches antigos via pattern (limpeza)
        // Nota: Transients com pattern são limpos na desinstalação via uninstall.php
    }

    /**
     * Monta o contexto do cliente e pets para incluir na pergunta.
     *
     * SEGURANÇA: Este método constrói o contexto apenas com dados do cliente autenticado.
     * Todos os métodos auxiliares (get_client_data, get_pets_data, get_appointments_data,
     * get_pending_charges, get_loyalty_points) filtram por $client_id ou $pet_ids
     * para garantir isolamento de dados entre clientes.
     *
     * Agora refatorado para usar métodos auxiliares especializados.
     *
     * @param int   $client_id ID do cliente autenticado.
     * @param array $pet_ids   IDs dos pets do cliente (já validados como pertencentes ao cliente).
     *
     * @return string Contexto formatado.
     */
    private static function build_client_context( $client_id, array $pet_ids ) {
        $context = "CONTEXTO DO SISTEMA:\n\n";

        // Dados do cliente
        $client_data = self::get_client_data( $client_id );
        if ( ! empty( $client_data ) ) {
            $context .= $client_data;
        }

        // Dados dos pets
        $pets_data = self::get_pets_data( $pet_ids );
        if ( ! empty( $pets_data ) ) {
            $context .= "\nPets cadastrados:\n" . $pets_data;
        }

        // Últimos agendamentos (limitado a 5 mais recentes)
        // SEGURANÇA: Passa $client_id para garantir filtragem no banco de dados
        $appointments_data = self::get_appointments_data( $client_id, $pet_ids, 5 );
        if ( ! empty( $appointments_data ) ) {
            $context .= "\nÚltimos agendamentos:\n" . $appointments_data;
        }

        // Pendências financeiras (se Finance add-on estiver ativo)
        $pending_charges = self::get_pending_charges( $client_id, $pet_ids );
        if ( ! empty( $pending_charges ) ) {
            $context .= "\nPendências financeiras:\n{$pending_charges}\n";
        }

        // Pontos de fidelidade (se Loyalty add-on estiver ativo)
        $loyalty_points = self::get_loyalty_points( $client_id );
        if ( null !== $loyalty_points ) {
            $context .= "\nPontos de fidelidade: {$loyalty_points}\n";
        }

        return $context;
    }

    /**
     * Obtém dados formatados do cliente.
     *
     * @param int $client_id ID do cliente.
     *
     * @return string Dados do cliente formatados ou string vazia.
     */
    private static function get_client_data( $client_id ) {
        if ( ! $client_id || 'dps_cliente' !== get_post_type( $client_id ) ) {
            return '';
        }

        $result = '';

        $client_name  = get_the_title( $client_id );
        $client_phone = get_post_meta( $client_id, 'client_phone', true );
        $client_email = get_post_meta( $client_id, 'client_email', true );

        if ( $client_name && ! empty( $client_name ) ) {
            $result .= "Cliente: {$client_name}\n";
        }
        if ( $client_phone ) {
            $result .= "Telefone: {$client_phone}\n";
        }
        if ( $client_email ) {
            $result .= "E-mail: {$client_email}\n";
        }

        return $result;
    }

    /**
     * Obtém dados formatados dos pets.
     *
     * @param array $pet_ids IDs dos pets.
     *
     * @return string Dados dos pets formatados ou string vazia.
     */
    private static function get_pets_data( array $pet_ids ) {
        if ( empty( $pet_ids ) ) {
            return '';
        }

        $valid_pets = [];

        foreach ( $pet_ids as $pet_id ) {
            if ( ! $pet_id || 'dps_pet' !== get_post_type( $pet_id ) ) {
                continue;
            }

            $pet_name = get_the_title( $pet_id );
            if ( ! $pet_name || empty( $pet_name ) ) {
                continue;
            }

            $pet_breed = get_post_meta( $pet_id, 'pet_breed', true );
            $pet_size  = get_post_meta( $pet_id, 'pet_size', true );
            $pet_age   = get_post_meta( $pet_id, 'pet_age', true );

            $pet_desc = "- {$pet_name}";
            if ( $pet_breed ) {
                $pet_desc .= " (Raça: {$pet_breed})";
            }
            if ( $pet_size ) {
                $pet_desc .= " (Porte: {$pet_size})";
            }
            if ( $pet_age ) {
                $pet_desc .= " (Idade: {$pet_age})";
            }

            $valid_pets[] = $pet_desc;
        }

        if ( empty( $valid_pets ) ) {
            return '';
        }

        return implode( "\n", $valid_pets ) . "\n";
    }

    /**
     * Obtém dados formatados dos agendamentos recentes.
     *
     * SEGURANÇA: Agora recebe $client_id para garantir filtragem no banco de dados,
     * evitando carregar dados de outros clientes em memória.
     *
     * @param int   $client_id ID do cliente autenticado.
     * @param array $pet_ids   IDs dos pets do cliente.
     * @param int   $limit     Limite de agendamentos.
     *
     * @return string Dados dos agendamentos formatados ou string vazia.
     */
    private static function get_appointments_data( $client_id, array $pet_ids, $limit = 5 ) {
        $appointments = self::get_recent_appointments( $client_id, $pet_ids, $limit );

        if ( empty( $appointments ) ) {
            return '';
        }

        $result = '';
        foreach ( $appointments as $appointment ) {
            $result .= $appointment . "\n";
        }

        return $result;
    }

    /**
     * Busca os agendamentos mais recentes dos pets do cliente.
     *
     * SEGURANÇA: Filtra por appointment_client_id no banco de dados para garantir
     * que apenas agendamentos do cliente autenticado sejam retornados.
     * Isso evita carregar dados de outros clientes em memória.
     *
     * @param int   $client_id ID do cliente autenticado.
     * @param array $pet_ids   IDs dos pets do cliente.
     * @param int   $limit     Limite de agendamentos a retornar.
     *
     * @return array Array de strings descrevendo cada agendamento.
     */
    private static function get_recent_appointments( $client_id, array $pet_ids, $limit = 5 ) {
        // Valida parâmetros de entrada
        $client_id = absint( $client_id );
        if ( ! $client_id ) {
            return [];
        }

        if ( empty( $pet_ids ) ) {
            return [];
        }

        // Busca agendamentos filtrando por appointment_client_id no banco de dados
        // Isso garante que apenas agendamentos do cliente autenticado sejam carregados
        $query = new WP_Query( [
            'post_type'      => 'dps_agendamento',
            'post_status'    => 'publish',
            'posts_per_page' => $limit * 2, // Busca um pouco mais para compensar possíveis filtros adicionais
            'orderby'        => 'meta_value',
            'order'          => 'DESC',
            'meta_key'       => 'appointment_date',
            'meta_query'     => [
                [
                    'key'     => 'appointment_client_id',
                    'value'   => $client_id,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ],
            ],
        ] );

        $appointments = [];
        $count        = 0;

        if ( $query->have_posts() ) {
            while ( $query->have_posts() && $count < $limit ) {
                $query->the_post();
                $appointment_id   = get_the_ID();
                $appointment_pets = get_post_meta( $appointment_id, 'appointment_pets', true );

                // Verifica se algum dos pets do cliente está neste agendamento
                // Esta verificação adicional garante consistência entre pet_ids e appointment_pets
                if ( ! empty( $appointment_pets ) ) {
                    // appointment_pets pode ser array ou string serializada
                    if ( is_string( $appointment_pets ) ) {
                        $appointment_pets = maybe_unserialize( $appointment_pets );
                    }

                    if ( is_array( $appointment_pets ) ) {
                        // Converte para inteiros e verifica interseção
                        $appointment_pets = array_map( 'intval', $appointment_pets );
                        $pet_ids_int      = array_map( 'intval', $pet_ids );

                        if ( empty( array_intersect( $appointment_pets, $pet_ids_int ) ) ) {
                            continue;
                        }
                    }
                }

                // Este agendamento pertence ao cliente autenticado
                $appointment_date   = get_post_meta( $appointment_id, 'appointment_date', true );
                $appointment_status = get_post_meta( $appointment_id, 'appointment_status', true );
                $services_raw       = get_post_meta( $appointment_id, 'appointment_services', true );
                $services           = ! empty( $services_raw ) ? explode( ',', $services_raw ) : [];

                $status_label = '';
                switch ( $appointment_status ) {
                    case 'scheduled':
                        $status_label = 'Agendado';
                        break;
                    case 'completed':
                        $status_label = 'Concluído';
                        break;
                    case 'cancelled':
                        $status_label = 'Cancelado';
                        break;
                    default:
                        $status_label = ucfirst( (string) $appointment_status );
                }

                $appointment_desc = "Data: {$appointment_date}, Status: {$status_label}";
                if ( ! empty( $services ) ) {
                    $service_names = self::get_service_names_batch( $services );
                    if ( ! empty( $service_names ) ) {
                        $appointment_desc .= ', Serviços: ' . implode( ', ', $service_names );
                    }
                }

                $appointments[] = $appointment_desc;
                $count++;
            }
            wp_reset_postdata();
        }

        return $appointments;
    }

    /**
     * Busca nomes de serviços em batch (otimizado).
     *
     * Em vez de fazer N queries individuais (uma por serviço),
     * faz uma única query usando post__in para buscar todos de uma vez.
     *
     * @param array $service_ids Array de IDs de serviços.
     *
     * @return array Array de nomes de serviços.
     */
    private static function get_service_names_batch( array $service_ids ) {
        if ( empty( $service_ids ) ) {
            return [];
        }

        // Converte para inteiros e remove zeros/inválidos
        $service_ids_int = array_filter( array_map( 'absint', $service_ids ) );

        if ( empty( $service_ids_int ) ) {
            return [];
        }

        // Busca todos os serviços de uma vez
        $service_posts = get_posts( [
            'post_type'      => 'dps_servico',
            'post__in'       => $service_ids_int,
            'posts_per_page' => count( $service_ids_int ),
            'post_status'    => 'publish',
            'orderby'        => 'post__in', // Mantém ordem original
            'no_found_rows'  => true, // Otimização: não precisa contar total
        ] );

        if ( empty( $service_posts ) ) {
            return [];
        }

        // Extrai apenas os títulos
        return wp_list_pluck( $service_posts, 'post_title' );
    }

    /**
     * Busca pendências financeiras do cliente.
     *
     * @param int   $client_id ID do cliente.
     * @param array $pet_ids   IDs dos pets.
     *
     * @return string|null Descrição das pendências ou null se não houver.
     */
    private static function get_pending_charges( $client_id, array $pet_ids ) {
        // Verifica se o Finance add-on está ativo
        if ( ! class_exists( 'DPS_Finance_API' ) ) {
            return null;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'dps_transacoes';

        // Busca cobranças pendentes do cliente - usando prepare para segurança
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Query necessária para dados financeiros não cacheáveis
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely constructed with wpdb prefix
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT valor_cents, descricao FROM {$table_name} WHERE cliente_id = %d AND status = %s ORDER BY data ASC",
                $client_id,
                'pendente'
            )
        );

        if ( empty( $results ) ) {
            return null;
        }

        $total_pending = 0;
        $count         = count( $results );

        foreach ( $results as $charge ) {
            $total_pending += intval( $charge->valor_cents );
        }

        // Formata valor total usando helper se disponível
        if ( class_exists( 'DPS_Money_Helper' ) ) {
            $formatted_total = 'R$ ' . DPS_Money_Helper::format_to_brazilian( $total_pending );
        } else {
            $formatted_total = 'R$ ' . number_format( $total_pending / 100, 2, ',', '.' );
        }

        return "{$count} cobrança(s) pendente(s), total: {$formatted_total}";
    }

    /**
     * Busca pontos de fidelidade do cliente.
     *
     * @param int $client_id ID do cliente.
     *
     * @return int|null Pontos de fidelidade ou null se Loyalty add-on não estiver ativo.
     */
    private static function get_loyalty_points( $client_id ) {
        // Verifica se o Loyalty add-on está ativo
        $loyalty_post = get_posts( [
            'post_type'      => 'dps_loyalty',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'     => 'loyalty_client_id',
                    'value'   => $client_id,
                    'compare' => '=',
                ],
            ],
        ] );

        if ( empty( $loyalty_post ) ) {
            return null;
        }

        return absint( get_post_meta( $loyalty_post[0]->ID, 'loyalty_points', true ) );
    }
}
