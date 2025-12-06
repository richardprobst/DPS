# DPS by PRObst – AI Add-on

**Autor:** PRObst  
**Site:** [www.probst.pro](https://www.probst.pro)

## Visão Geral

O **AI Add-on** implementa um assistente virtual inteligente no Portal do Cliente do DPS by PRObst, focado EXCLUSIVAMENTE em responder perguntas sobre:

- Serviços de Banho e Tosa
- Agendamentos e histórico de atendimentos
- Dados do cliente e pets cadastrados
- Funcionalidades do sistema DPS (fidelidade, pagamentos, assinaturas)
- Cuidados gerais e básicos com pets

O assistente **NÃO responde** sobre assuntos aleatórios fora desse contexto (política, religião, tecnologia, etc.).

## Tecnologia

- **API**: OpenAI Chat Completions (GPT-3.5 Turbo / GPT-4)
- **Integração**: WordPress `wp_remote_post()` para chamadas HTTP
- **Segurança**: Nonces, sanitização, validação de permissões
- **Interface**: Widget responsivo e minimalista seguindo padrões visuais do DPS

## Estrutura de Arquivos

```
desi-pet-shower-ai_addon/
├── desi-pet-shower-ai-addon.php          # Plugin principal
├── includes/
│   ├── class-dps-ai-client.php           # Cliente da API OpenAI
│   ├── class-dps-ai-assistant.php        # Lógica do assistente (system prompt, contexto)
│   └── class-dps-ai-integration-portal.php # Integração com Portal do Cliente
├── assets/
│   ├── js/
│   │   └── dps-ai-portal.js              # JavaScript do widget de chat
│   └── css/
│       └── dps-ai-portal.css             # Estilos do widget
└── README.md
```

## Configuração

### 1. Ativar o Add-on

Ative o plugin no WordPress em **Plugins > Plugins Instalados**.

### 2. Obter API Key da OpenAI

1. Acesse [platform.openai.com](https://platform.openai.com/)
2. Crie uma conta ou faça login
3. Navegue até **API Keys** e crie uma nova chave
4. Copie a chave (formato: `sk-...`)

### 3. Configurar o Assistente

1. No WordPress, vá em **DPS by PRObst > Assistente de IA**
2. Marque **"Ativar Assistente de IA"**
3. Cole a **Chave de API da OpenAI**
4. Escolha o **Modelo GPT**:
   - **GPT-3.5 Turbo**: Mais rápido e econômico (recomendado)
   - **GPT-4**: Mais preciso, porém mais caro
   - **GPT-4 Turbo**: Balanceado
5. Ajuste os parâmetros opcionais:
   - **Temperatura**: 0.4 (recomendado) - controla criatividade
   - **Timeout**: 10 segundos (recomendado)
   - **Máximo de Tokens**: 500 (recomendado)
6. Clique em **Salvar Configurações**

## Como Funciona

### Para o Cliente no Portal

1. Cliente acessa o Portal do Cliente (shortcode `[dps_client_portal]`)
2. Widget do assistente aparece na parte inferior da página
3. Cliente clica para expandir o chat
4. Cliente digita uma pergunta e clica em "Perguntar"
5. Assistente responde com base nos dados do cliente/pet e sistema

### Fluxo Interno

1. **Validação**: Pergunta passa por filtro de palavras-chave
2. **Contexto**: Sistema monta contexto com dados do cliente, pets, agendamentos, pendências
3. **System Prompt**: Define regras rígidas de comportamento da IA
4. **API Call**: Chama OpenAI via `wp_remote_post()`
5. **Resposta**: Exibe resposta no chat ou mensagem de erro se API falhar

### Filtro Preventivo

Antes de chamar a API, o sistema verifica se a pergunta contém pelo menos uma palavra-chave relacionada ao contexto permitido. Exemplos:

- ✅ "Quando foi o último banho do meu cachorro?"
- ✅ "Quanto custa um banho e tosa para gato?"
- ✅ "Tenho alguma pendência de pagamento?"
- ❌ "Qual o melhor investimento para 2024?" → Resposta padrão sem chamar API

## System Prompt (Regras da IA)

O assistente possui um **system prompt restritivo** que:

- Define o domínio permitido (banho/tosa, pet shop, sistema DPS)
- Proíbe explicitamente assuntos fora do contexto
- Instrui a IA a recusar educadamente perguntas inadequadas
- Recomenda procurar veterinário para problemas de saúde graves
- Proíbe inventar descontos ou promoções não existentes
- Exige honestidade quando não encontrar dados no sistema

## Comportamento em Diferentes Cenários

### ✅ IA Ativa e Funcionando

- Widget aparece no Portal do Cliente
- Perguntas são processadas normalmente
- Respostas aparecem em segundos

### 🔴 IA Sem Chave Configurada

- Widget NÃO aparece no Portal
- Portal funciona normalmente sem a IA
- Nenhum erro visível para o cliente

### ⚠️ Falha na Chamada de API

- Widget aparece normalmente
- Cliente faz pergunta
- Sistema retorna mensagem amigável: *"No momento não foi possível gerar uma resposta automática. Por favor, fale diretamente com a equipe."*
- Portal continua funcionando normalmente

## Segurança

- ✅ API Key NUNCA exposta no JavaScript (server-side only)
- ✅ Nonces em todas as requisições AJAX
- ✅ Sanitização de entrada do usuário
- ✅ Validação de permissões (cliente logado)
- ✅ Timeout configurável para evitar requisições travadas
- ✅ Logs de erro apenas no server (error_log)

## Integração com Outros Add-ons

O assistente busca dados automaticamente se os add-ons estiverem ativos:

- **Finance Add-on**: Pendências financeiras do cliente
- **Loyalty Add-on**: Pontos de fidelidade acumulados
- **Services Add-on**: Detalhes de serviços em agendamentos

Se um add-on não estiver ativo, o assistente simplesmente não inclui esse dado no contexto.

## Custos Estimados (OpenAI)

Os custos variam conforme o modelo escolhido:

| Modelo          | Custo por 1M tokens | Estimativa por pergunta* |
|-----------------|---------------------|--------------------------|
| GPT-3.5 Turbo   | ~$0.50 - $1.50      | ~$0.001 - $0.003         |
| GPT-4           | ~$30 - $60          | ~$0.05 - $0.10           |
| GPT-4 Turbo     | ~$10 - $30          | ~$0.015 - $0.045         |

\* Estimativa baseada em ~1.000 tokens por interação (contexto + pergunta + resposta)

**Recomendação**: Use GPT-3.5 Turbo para custo/benefício ideal.

## Manutenção

### Logs de Erro

Erros são registrados via `error_log()` do PHP. Para visualizar:

```bash
tail -f /var/log/php/error.log | grep "DPS AI"
```

Exemplos de erros logados:
- API key não configurada
- Timeout na chamada
- Resposta HTTP != 200
- JSON inválido da API

### Teste de Conexão

Para testar a API key sem usar o Portal, use o método auxiliar:

```php
$result = DPS_AI_Client::test_connection();
if ( $result['success'] ) {
    echo $result['message']; // "Conexão estabelecida com sucesso!"
} else {
    echo $result['message']; // Mensagem de erro
}
```

## Requisitos

- **WordPress**: 6.0+
- **PHP**: 7.4+
- **Plugin Base**: DPS by PRObst Base Plugin ativo
- **Add-on**: Client Portal ativo
- **Conta OpenAI**: Com créditos e API key válida

## Dependências de Add-ons

- **Obrigatório**: Client Portal (fornece shortcode `[dps_client_portal]` e autenticação)
- **Opcional**: Finance, Loyalty, Services (melhoram contexto disponível)

## Chat Público para Visitantes

### Descrição

O Chat Público é uma funcionalidade que permite visitantes do site (não logados) tirarem dúvidas sobre os serviços de Banho e Tosa através de um assistente de IA.

**Diferenças do chat do Portal do Cliente:**

| Característica | Chat do Portal | Chat Público |
|----------------|----------------|--------------|
| Requer login | Sim | Não |
| Acessa dados do cliente | Sim | Não |
| Contexto personalizado | Sim (dados pessoais, pets, histórico) | Não (informações gerais) |
| Público alvo | Clientes cadastrados | Visitantes interessados |
| Rate limiting | Por cliente | Por IP |

### Uso do Shortcode

```
[dps_ai_public_chat]
```

### Atributos Disponíveis

| Atributo | Valores | Padrão | Descrição |
|----------|---------|--------|-----------|
| `mode` | `inline`, `floating` | `inline` | Modo de exibição |
| `theme` | `light`, `dark` | `light` | Tema visual |
| `position` | `bottom-right`, `bottom-left` | `bottom-right` | Posição (modo flutuante) |
| `title` | Texto | "Tire suas dúvidas" | Título personalizado |
| `subtitle` | Texto | Descrição padrão | Subtítulo personalizado |
| `placeholder` | Texto | "Digite sua pergunta..." | Placeholder do input |
| `show_faqs` | `true`, `false` | `true` | Mostrar botões de FAQs |
| `primary_color` | Cor hexadecimal | `#0ea5e9` | Cor principal customizada |

### Exemplos

**Chat inline padrão:**
```
[dps_ai_public_chat]
```

**Chat flutuante no canto inferior esquerdo:**
```
[dps_ai_public_chat mode="floating" position="bottom-left"]
```

**Chat com tema escuro e cor customizada:**
```
[dps_ai_public_chat theme="dark" primary_color="#8b5cf6"]
```

**Chat com título e FAQs ocultos:**
```
[dps_ai_public_chat title="Fale conosco" show_faqs="false"]
```

### Configuração

1. Acesse **DPS by PRObst > Assistente de IA**
2. Na seção **"Chat Público para Visitantes"**, marque "Habilitar Chat Público"
3. Configure as FAQs personalizadas (uma por linha)
4. Adicione informações do seu negócio (horários, endereço, formas de pagamento)
5. Opcionalmente, adicione instruções adicionais para o comportamento da IA
6. Clique em **Salvar Configurações**

### Segurança

- **Rate Limiting**: Limite de 10 perguntas/minuto e 60 perguntas/hora por IP
- **Validação de contexto**: Perguntas fora do escopo são recusadas educadamente
- **Nonces**: Todas as requisições AJAX são protegidas
- **Sanitização**: Todas as entradas são sanitizadas

## Hooks Disponíveis

### Actions

- `dps_client_portal_after_content`: Usado para renderizar o widget (prioridade padrão)

### Filters

Nenhum filtro exposto atualmente. Sistema é autocontido.

## Changelog

### [1.6.0] - 2024-12-05

#### Added
- **Chat Público para Visitantes**: Novo shortcode `[dps_ai_public_chat]`
  - Permite visitantes não logados tirarem dúvidas sobre serviços
  - Modo inline e flutuante
  - Temas claro e escuro
  - FAQs personalizáveis
  - Rate limiting por IP (10/min, 60/hora)
  - Cores customizáveis via atributo do shortcode
  - Integração com base de conhecimento
  - Registro de métricas e feedback
- Configurações administrativas para o chat público
- CSS e JavaScript dedicados para o chat público

### [1.0.0] - 2024-11-22

#### Added
- Implementação inicial do assistente de IA
- Cliente OpenAI (`DPS_AI_Client`)
- Assistente com system prompt restritivo (`DPS_AI_Assistant`)
- Integração com Portal do Cliente (`DPS_AI_Integration_Portal`)
- Widget de chat responsivo com estilos DPS
- Filtro preventivo de palavras-chave
- Interface administrativa de configuração
- Suporte a GPT-3.5 Turbo, GPT-4 e GPT-4 Turbo
- Documentação completa

## Autor

**PRObst** - [probst.pro](https://probst.pro)

## Licença

Proprietário. Uso restrito ao sistema DPS by PRObst.
