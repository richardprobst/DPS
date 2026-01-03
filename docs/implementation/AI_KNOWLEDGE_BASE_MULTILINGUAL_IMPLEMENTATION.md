# Implementação: Base de Conhecimento com Matching e Suporte Multiidioma

**Data:** 07/12/2024  
**Versão:** 1.6.2 (proposta)  
**Autor:** Agente de Implementação

---

## RESUMO EXECUTIVO

Esta implementação adiciona funcionalidade completa de **matching inteligente da base de conhecimento** e **suporte real a multiidioma** no AI Add-on do desi.pet by PRObst.

### O que foi implementado:

1. ✅ **Integração da base de conhecimento com matching por keywords** no fluxo de respostas
2. ✅ **Suporte multiidioma real** com instruções explícitas para a IA
3. ✅ **Compatibilidade retroativa** - não quebra funcionalidades existentes
4. ✅ **Aplicado em todos os contextos**: Portal, Chat Público e Assistente de Mensagens

### O que já existia (descoberta importante):

A infraestrutura para keywords e prioridade **JÁ ESTAVA IMPLEMENTADA** na classe `DPS_AI_Knowledge_Base`:
- Metaboxes para keywords e prioridade
- Função de matching `get_relevant_articles()`
- Função de formatação `format_articles_for_context()`

O que faltava era **conectar** essa infraestrutura ao fluxo de respostas da IA.

---

## ARQUITETURA DA SOLUÇÃO

### Fluxo de Integração da Base de Conhecimento

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. Usuário faz uma pergunta                                     │
└─────────────────────┬───────────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────────┐
│ 2. Sistema verifica se pergunta está no contexto permitido     │
└─────────────────────┬───────────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────────┐
│ 3. DPS_AI_Knowledge_Base::get_relevant_articles($question, 5)  │
│    - Busca artigos ativos com keywords que fazem match         │
│    - Ordena por prioridade (1-10)                              │
│    - Retorna até 5 artigos mais relevantes                     │
└─────────────────────┬───────────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────────┐
│ 4. DPS_AI_Knowledge_Base::format_articles_for_context()        │
│    - Formata artigos em texto estruturado                      │
│    - Adiciona cabeçalho "INFORMAÇÕES DA BASE DE CONHECIMENTO:" │
└─────────────────────┬───────────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────────┐
│ 5. Contexto montado e enviado para a IA:                       │
│    - System prompt base + instrução de idioma                  │
│    - Contexto do cliente/negócio                               │
│    - Artigos da base de conhecimento                           │
│    - Pergunta do usuário                                       │
└─────────────────────┬───────────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────────┐
│ 6. IA processa e responde no idioma configurado                │
└─────────────────────────────────────────────────────────────────┘
```

### Fluxo de Multiidioma

```
┌─────────────────────────────────────────────────────────────────┐
│ Configuração: dps_ai_settings['language']                      │
│ Valores: 'pt_BR', 'en_US', 'es_ES', 'auto'                    │
└─────────────────────┬───────────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────────┐
│ get_base_system_prompt_with_language($language)                │
│                                                                 │
│ Adiciona instrução explícita ao system prompt:                 │
│                                                                 │
│ pt_BR: "IMPORTANTE: Você DEVE responder SEMPRE em Português    │
│         do Brasil, mesmo que os artigos da base de              │
│         conhecimento estejam em outro idioma."                  │
│                                                                 │
│ en_US: "IMPORTANT: You MUST ALWAYS respond in English (US),    │
│         even if the knowledge base articles are in another      │
│         language."                                              │
│                                                                 │
│ es_ES: "IMPORTANTE: Usted DEBE responder SIEMPRE en Español,   │
│         incluso si los artículos están en otro idioma."         │
│                                                                 │
│ auto:  "IMPORTANTE: Detecte automaticamente o idioma da         │
│         pergunta do usuário e responda no mesmo idioma."        │
└─────────────────────────────────────────────────────────────────┘
```

---

## DETALHES TÉCNICOS

### 1. Metadados de Post Utilizados

Os seguintes metadados já existiam e estão sendo utilizados:

| Meta Key | Tipo | Valores | Descrição |
|----------|------|---------|-----------|
| `_dps_ai_keywords` | string | Texto separado por vírgulas | Keywords para matching de perguntas |
| `_dps_ai_priority` | int | 1-10 | Prioridade do artigo (maior = mais importante) |
| `_dps_ai_active` | string | '0' ou '1' | Se o artigo está ativo |

**Exemplo de uso:**

```php
// Ao editar artigo no admin, admin define:
Keywords: banho, preço, valor, quanto custa
Prioridade: 8
Ativo: Sim

// Quando cliente pergunta: "Quanto custa um banho?"
// Sistema encontra artigo porque contém keyword "banho" e "quanto custa"
// Artigo é incluído no contexto por ter alta prioridade (8)
```

### 2. Funções Principais

#### `DPS_AI_Knowledge_Base::get_relevant_articles($question, $limit = 3)`

**Localização:** `includes/class-dps-ai-knowledge-base.php:265`

**Responsabilidade:** Busca artigos relevantes para uma pergunta

**Algoritmo:**
1. Converte pergunta para lowercase
2. Busca todos artigos ativos (`_dps_ai_active = '1'`)
3. Para cada artigo, verifica se alguma keyword está na pergunta
4. Ordena artigos encontrados por prioridade (DESC)
5. Retorna até `$limit` artigos

**Retorno:**
```php
[
    [
        'priority' => 8,
        'title'    => 'Preços de Banho',
        'content'  => 'Banho básico custa R$ 50...',
    ],
    // ...
]
```

#### `DPS_AI_Knowledge_Base::format_articles_for_context($articles)`

**Localização:** `includes/class-dps-ai-knowledge-base.php:328`

**Responsabilidade:** Formata artigos para inclusão no prompt da IA

**Exemplo de saída:**
```
INFORMAÇÕES DA BASE DE CONHECIMENTO:

--- Preços de Banho ---
Banho básico custa R$ 50. Inclui banho, secagem e perfume.
Banho especial custa R$ 80. Inclui hidratação e condicionador.

--- Horário de Funcionamento ---
Segunda a sexta: 8h às 18h
Sábados: 8h às 14h
```

#### `DPS_AI_Assistant::get_base_system_prompt_with_language($language)`

**Localização:** `includes/class-dps-ai-assistant.php:169`

**Responsabilidade:** Retorna system prompt com instrução de idioma

**Parâmetros:**
- `$language` (string): Código de idioma ('pt_BR', 'en_US', 'es_ES', 'auto')

**Retorno:** String com prompt base + instrução de idioma

**Exemplo:**
```php
$prompt = DPS_AI_Assistant::get_base_system_prompt_with_language('pt_BR');
// Retorna: "[System prompt base]\n\nIMPORTANTE: Você DEVE responder SEMPRE em Português..."
```

### 3. Arquivos Modificados

#### `class-dps-ai-assistant.php`

**Mudanças:**

1. **Método `answer_portal_question()` (linhas 63-114):**
   - Adicionado: Busca de artigos relevantes via `get_relevant_articles()`
   - Adicionado: Formatação de artigos via `format_articles_for_context()`
   - Modificado: Uso de `get_base_system_prompt_with_language()` ao invés de `get_base_system_prompt()`
   - Modificado: Contexto do usuário agora inclui artigos da base

2. **Novo método `get_base_system_prompt_with_language()` (linhas 169-200):**
   - Carrega prompt base
   - Adiciona instrução de idioma conforme configuração
   - Suporta pt_BR, en_US, es_ES e auto

**Código relevante:**
```php
// Busca artigos relevantes da base de conhecimento
$kb_context = '';
if ( class_exists( 'DPS_AI_Knowledge_Base' ) ) {
    $relevant_articles = DPS_AI_Knowledge_Base::get_relevant_articles( $user_question, 5 );
    $kb_context = DPS_AI_Knowledge_Base::format_articles_for_context( $relevant_articles );
}

// System prompt com idioma
$language = ! empty( $settings['language'] ) ? $settings['language'] : 'pt_BR';
$messages[] = [
    'role'    => 'system',
    'content' => self::get_base_system_prompt_with_language( $language ),
];

// Pergunta do usuário com contexto + base de conhecimento
$user_content = $context;
if ( ! empty( $kb_context ) ) {
    $user_content .= $kb_context;
}
$user_content .= "\n\nPergunta do cliente: " . $user_question;
```

#### `class-dps-ai-public-chat.php`

**Mudanças:**

1. **Método `get_ai_response()` (linhas 355-414):**
   - Adicionado: Busca de artigos relevantes via `get_relevant_articles()`
   - Adicionado: Formatação de artigos via `format_articles_for_context()`
   - Modificado: Uso de `get_public_system_prompt_with_language()`
   - Modificado: Pergunta do visitante agora inclui contexto da base

2. **Novo método `get_public_system_prompt_with_language()` (linhas 429-462):**
   - Similar ao do Assistant, mas para contexto público
   - Suporta os mesmos idiomas

**Código relevante:**
```php
// Busca artigos relevantes da base de conhecimento
$kb_context = '';
if ( class_exists( 'DPS_AI_Knowledge_Base' ) ) {
    $relevant_articles = DPS_AI_Knowledge_Base::get_relevant_articles( $question, 5 );
    $kb_context = DPS_AI_Knowledge_Base::format_articles_for_context( $relevant_articles );
}

// System prompt com idioma
$language = ! empty( $settings['language'] ) ? $settings['language'] : 'pt_BR';
$messages[] = [
    'role'    => 'system',
    'content' => $this->get_public_system_prompt_with_language( $language ),
];

// Pergunta com contexto da base
$user_content = $question;
if ( ! empty( $kb_context ) ) {
    $user_content = $kb_context . "\n\nPergunta do visitante: " . $question;
}
```

#### `class-dps-ai-message-assistant.php`

**Mudanças:**

1. **Método `suggest_whatsapp_message()` (linhas 65-104):**
   - Modificado: Usa `get_base_system_prompt_with_language()` ao invés de `get_base_system_prompt()`

2. **Método `suggest_email_message()` (linhas 140-179):**
   - Modificado: Usa `get_base_system_prompt_with_language()` ao invés de `get_base_system_prompt()`

**Código relevante:**
```php
// Obtém configurações incluindo idioma
$settings = get_option( 'dps_ai_settings', [] );
$language = ! empty( $settings['language'] ) ? $settings['language'] : 'pt_BR';

// System prompt base com instrução de idioma
$messages[] = [
    'role'    => 'system',
    'content' => DPS_AI_Assistant::get_base_system_prompt_with_language( $language ),
];
```

---

## COMO USAR

### Para Administradores

#### 1. Configurar Idioma

1. Acesse **DPS > Configurações IA**
2. Localize campo **"Idioma das Respostas"**
3. Escolha:
   - **Português (Brasil)** - Respostas sempre em PT-BR
   - **English (US)** - Respostas sempre em inglês
   - **Español** - Respostas sempre em espanhol
   - **Automático (detectar)** - IA detecta idioma da pergunta
4. Salve as configurações

#### 2. Criar Artigos da Base de Conhecimento

1. Acesse **DPS > Conhecimento IA**
2. Clique em **Adicionar Novo**
3. Preencha:
   - **Título:** Nome descritivo (ex: "Preços de Banho")
   - **Conteúdo:** Informação completa sobre o assunto
   - **Palavras-chave:** Lista separada por vírgula (ex: `banho, preço, valor, quanto custa`)
   - **Prioridade:** 1-10 (quanto maior, mais importante)
   - **Artigo ativo:** Marque para ativar
4. Publique

#### 3. Testar Matching

**Exemplo prático:**

**Artigo criado:**
- Título: Preços de Banho
- Conteúdo: "Banho básico: R$ 50. Banho especial: R$ 80."
- Keywords: `banho, preço, valor, quanto custa`
- Prioridade: 8

**Perguntas que farão match:**
- ✅ "Quanto custa um banho?" (contém "quanto custa" e "banho")
- ✅ "Qual o valor do banho?" (contém "valor" e "banho")
- ✅ "Preço do banho básico?" (contém "preço" e "banho")
- ❌ "Qual o horário?" (não contém nenhuma keyword)

**Comportamento esperado:**

Quando cliente perguntar "Quanto custa um banho?":
1. Sistema busca artigos com keywords relevantes
2. Encontra "Preços de Banho" (keywords: banho, quanto custa)
3. Inclui conteúdo do artigo no contexto
4. IA usa informação do artigo para responder
5. Resposta: "O banho básico custa R$ 50 e o banho especial R$ 80."

### Para Desenvolvedores

#### Adicionar Keywords Programaticamente

```php
// Criar artigo
$post_id = wp_insert_post([
    'post_type'   => 'dps_ai_knowledge',
    'post_title'  => 'Preços de Banho',
    'post_content' => 'Banho básico: R$ 50...',
    'post_status' => 'publish',
]);

// Adicionar metadados
update_post_meta( $post_id, '_dps_ai_keywords', 'banho, preço, valor, quanto custa' );
update_post_meta( $post_id, '_dps_ai_priority', 8 );
update_post_meta( $post_id, '_dps_ai_active', '1' );
```

#### Buscar Artigos Relevantes

```php
// Buscar artigos para uma pergunta
$question = "Quanto custa um banho?";
$articles = DPS_AI_Knowledge_Base::get_relevant_articles( $question, 5 );

// $articles = [
//     [
//         'priority' => 8,
//         'title'    => 'Preços de Banho',
//         'content'  => 'Banho básico: R$ 50...',
//     ],
//     // ...
// ]

// Formatar para contexto
$context = DPS_AI_Knowledge_Base::format_articles_for_context( $articles );
```

#### Customizar Prompt com Idioma

```php
// Obter prompt com idioma específico
$prompt_pt = DPS_AI_Assistant::get_base_system_prompt_with_language( 'pt_BR' );
$prompt_en = DPS_AI_Assistant::get_base_system_prompt_with_language( 'en_US' );
$prompt_es = DPS_AI_Assistant::get_base_system_prompt_with_language( 'es_ES' );
$prompt_auto = DPS_AI_Assistant::get_base_system_prompt_with_language( 'auto' );
```

#### Filtrar Prompts

```php
// Customizar instrução de idioma via filtro
add_filter( 'dps_ai_system_prompt', function( $prompt, $context, $metadata ) {
    if ( $context === 'portal' ) {
        $prompt .= "\n\nINSTRUÇÃO CUSTOMIZADA: Seja sempre muito educado.";
    }
    return $prompt;
}, 10, 3 );
```

---

## TESTES RECOMENDADOS

### Teste 1: Matching de Keywords

1. Criar artigo com keywords: `banho, preço, valor`
2. Fazer perguntas:
   - "Quanto custa banho?" → Deve incluir artigo
   - "Qual o valor?" → Deve incluir artigo
   - "Horário de funcionamento?" → NÃO deve incluir artigo

### Teste 2: Prioridade

1. Criar dois artigos sobre "banho":
   - Artigo A: Prioridade 5
   - Artigo B: Prioridade 9
2. Perguntar "Quanto custa banho?"
3. Verificar que Artigo B aparece primeiro no contexto

### Teste 3: Multiidioma

1. Configurar idioma como "Português (Brasil)"
2. Criar artigo em inglês: "Dog bath costs $50"
3. Perguntar: "Quanto custa banho de cachorro?"
4. IA deve responder em português: "O banho de cachorro custa R$ 50" (traduzindo)

### Teste 4: Modo Automático

1. Configurar idioma como "Automático (detectar)"
2. Perguntar em inglês: "How much is a dog bath?"
3. IA deve responder em inglês
4. Perguntar em português: "Quanto custa banho?"
5. IA deve responder em português

### Teste 5: Chat Público

1. Inserir shortcode `[dps_ai_public_chat]` em uma página
2. Fazer pergunta que tenha keywords de artigo
3. Verificar que artigo é incluído na resposta
4. Verificar que idioma configurado é respeitado

---

## COMPATIBILIDADE

### Retrocompatibilidade

✅ **100% retrocompatível**
- Não quebra funcionalidades existentes
- Se não houver artigos, sistema funciona normalmente
- Se idioma não estiver configurado, usa pt_BR como padrão
- Métodos antigos (`get_base_system_prompt()`) continuam funcionando

### WordPress

- **Mínimo:** WordPress 6.0+
- **PHP:** 7.4+
- **Dependências:** DPS Base Plugin

### Add-ons

Compatível com todos os add-ons existentes:
- Finance Add-on
- Loyalty Add-on
- Subscriptions Add-on
- Client Portal Add-on

---

## LIMITAÇÕES E CONSIDERAÇÕES

### Limitações de Tokens

- Cada artigo da base consome tokens do contexto
- Limite de 5 artigos por pergunta para evitar estouro
- Artigos muito longos (>1000 palavras) podem causar problemas
- **Recomendação:** Artigos concisos (200-500 palavras)

### Matching Simples

- Matching é feito por **substring** (não semântico)
- Não detecta sinônimos automaticamente
- **Solução:** Incluir variações nas keywords
- Exemplo: `banho, bath, higiene, limpeza`

### Performance

- Busca em `get_relevant_articles()` executa query sem cache
- Com muitos artigos (100+), pode haver lentidão
- **Otimização futura:** Implementar cache de queries

### Idioma dos Artigos

- Artigos podem estar em qualquer idioma
- IA traduz conforme instrução, mas qualidade varia
- **Recomendação:** Artigos no idioma principal do negócio

---

## PRÓXIMOS PASSOS

### Melhorias Futuras Sugeridas

1. **Embedding Semântico**
   - Substituir matching por substring por embeddings
   - Detectar perguntas semanticamente similares
   - Exemplo: "preço" e "valor" seriam detectados como similares

2. **Cache de Queries**
   - Cachear resultado de `get_relevant_articles()`
   - Invalidar cache ao criar/editar artigos
   - Melhoria de performance

3. **Análise de Relevância**
   - Dashboard mostrando quais artigos são mais usados
   - Identificar gaps (perguntas sem artigos relevantes)
   - Sugestões de novos artigos

4. **Validação de Tamanho**
   - Alertar admin se artigo for muito longo
   - Calcular tokens aproximados
   - Limitar caracteres automaticamente

5. **Keywords Sugeridas**
   - IA analisa artigo e sugere keywords
   - Admin revisa e confirma
   - Melhora consistência

---

## SUPORTE E TROUBLESHOOTING

### Artigo não aparece nas respostas

**Checklist:**
- [ ] Artigo está publicado?
- [ ] Campo "Artigo ativo" está marcado?
- [ ] Keywords estão preenchidas?
- [ ] Pergunta contém alguma keyword?
- [ ] Prioridade está configurada?

**Debug:**
```php
// Testar matching manualmente
$question = "Quanto custa banho?";
$articles = DPS_AI_Knowledge_Base::get_relevant_articles( $question, 10 );
var_dump( $articles ); // Verificar se artigo aparece
```

### IA não responde no idioma configurado

**Checklist:**
- [ ] Campo "Idioma das Respostas" está preenchido?
- [ ] Configurações foram salvas?
- [ ] Cache do browser foi limpo?

**Debug:**
```php
// Verificar idioma configurado
$settings = get_option( 'dps_ai_settings', [] );
var_dump( $settings['language'] ); // Deve ser 'pt_BR', 'en_US', etc.
```

### Erro ao salvar keywords

**Solução:** Verificar permissões de edição de post

```php
// Verificar se usuário pode editar post
if ( ! current_user_can( 'edit_post', $post_id ) ) {
    // Erro de permissão
}
```

---

## CHANGELOG

### v1.6.2 (Proposta)

**Added:**
- Integração real da base de conhecimento com matching por keywords
- Suporte multiidioma com instruções explícitas no system prompt
- Método `get_base_system_prompt_with_language()` em Assistant e Public Chat
- Método `get_relevant_articles()` integrado nos fluxos de resposta
- Suporte a 4 idiomas: pt_BR, en_US, es_ES, auto

**Changed:**
- `answer_portal_question()` agora busca e inclui artigos relevantes
- `get_ai_response()` (Public Chat) agora busca e inclui artigos relevantes
- `suggest_whatsapp_message()` e `suggest_email_message()` agora usam idioma configurado

**Fixed:**
- Base de conhecimento não era utilizada nas respostas
- Idioma configurado era ignorado nas instruções da IA
- Artigos da base não influenciavam respostas

---

**Fim da Documentação**

*Documento gerado em: 07 de Dezembro de 2024*  
*Última atualização: 07/12/2024*
