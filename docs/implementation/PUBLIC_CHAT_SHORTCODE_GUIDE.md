# Guia de Uso do Shortcode [dps_ai_public_chat]

## Descrição
O shortcode `[dps_ai_public_chat]` permite adicionar um chat de IA público em qualquer página do WordPress. Este chat é voltado para visitantes não logados e fornece informações sobre serviços de Banho e Tosa.

## Requisitos
1. **AI Add-on** instalado e ativado (v1.6.0+)
2. **API Key da OpenAI** configurada em DPS > Assistente de IA
3. **Chat Público habilitado** nas configurações do AI Add-on

## Como Inserir

### No Editor de Blocos (Gutenberg)
1. Adicione um bloco **Shortcode** (não use bloco de Código ou Parágrafo)
2. Digite: `[dps_ai_public_chat]`
3. Publique ou atualize a página

**⚠️ IMPORTANTE**: Use sempre o bloco "Shortcode" do Gutenberg. Não insira o shortcode em blocos de Código, Parágrafo ou HTML customizado.

### No Editor Clássico
1. Digite `[dps_ai_public_chat]` diretamente no conteúdo
2. Publique ou atualize a página

## Opções Disponíveis

### Exemplos Básicos

```
[dps_ai_public_chat]
```
Chat em modo inline com tema claro (padrões)

```
[dps_ai_public_chat mode="floating" position="bottom-right"]
```
Chat flutuante no canto inferior direito

```
[dps_ai_public_chat theme="dark"]
```
Chat com tema escuro

### Todas as Opções

| Atributo | Valores Possíveis | Padrão | Descrição |
|----------|-------------------|--------|-----------|
| `mode` | `inline`, `floating` | `inline` | Modo de exibição do chat |
| `theme` | `light`, `dark` | `light` | Tema visual |
| `position` | `bottom-right`, `bottom-left` | `bottom-right` | Posição do botão flutuante |
| `title` | Qualquer texto | "Tire suas dúvidas" | Título do chat |
| `subtitle` | Qualquer texto | Texto padrão | Subtítulo do chat |
| `placeholder` | Qualquer texto | Texto padrão | Placeholder do campo de texto |
| `primary_color` | Cor hex (#RRGGBB) | Cor padrão | Cor primária customizada |
| `show_faqs` | `true`, `false` | `true` | Exibir FAQs sugeridas |

### Exemplos Avançados

**Chat flutuante personalizado:**
```
[dps_ai_public_chat 
  mode="floating" 
  position="bottom-left" 
  theme="dark" 
  title="Precisa de ajuda?" 
  primary_color="#0ea5e9"
]
```

**Chat inline sem FAQs:**
```
[dps_ai_public_chat 
  mode="inline" 
  show_faqs="false"
]
```

## Configurações Administrativas

Acesse **DPS by PRObst > Assistente de IA** para configurar:

1. **Habilitar Chat Público**: Ativar/desativar o recurso
2. **FAQs Personalizadas**: Uma pergunta por linha (máximo 5 serão exibidas)
3. **Informações do Negócio**: Contexto que a IA usará nas respostas
   - Horários de funcionamento
   - Endereço e telefone
   - Formas de pagamento aceitas
   - Diferenciais do negócio
4. **Instruções Adicionais**: Personalização do tom e estilo das respostas

## Limitações e Proteções

- **Rate Limiting**: 10 perguntas por minuto e 60 por hora por IP
- **Contexto**: A IA responde apenas sobre serviços de Banho e Tosa
- **Privacidade**: Não acessa dados pessoais de clientes cadastrados
- **Segurança**: Todas as requisições são validadas com nonce e sanitizadas

## Troubleshooting

### Shortcode aparece como texto `[dps_ai_public_chat]`
**Problema**: O shortcode não está sendo processado.

**Causas possíveis**:
1. Inserido em bloco de Código no Gutenberg → Use bloco "Shortcode"
2. AI Add-on não está ativado → Ative em Plugins
3. Versão antiga do AI Add-on → Atualize para v1.6.0+
4. Cache do site → Limpe o cache e recarregue

### Chat aparece mas não responde
**Problema**: Chat carrega mas não processa perguntas.

**Causas possíveis**:
1. API Key não configurada → Configure em DPS > Assistente de IA
2. Chat público não habilitado → Marque a opção "Habilitar Chat Público"
3. Rate limit atingido → Aguarde alguns minutos
4. Erro na API OpenAI → Verifique o console do navegador

### Erro: "O chat não está disponível no momento"
**Problema**: Mensagem de erro ao tentar usar o chat.

**Causas possíveis**:
1. IA está desabilitada nas configurações
2. API Key inválida ou expirada
3. Créditos da OpenAI esgotados
4. Chat público desabilitado

## Exemplos de Uso

### Página de Contato
```
<h2>Entre em contato</h2>
<p>Tem dúvidas? Use nosso chat inteligente abaixo:</p>

[dps_ai_public_chat]
```

### Landing Page
```
[dps_ai_public_chat 
  mode="floating" 
  position="bottom-right" 
  title="Como podemos ajudar?" 
  primary_color="#10b981"
]
```

### Página de Serviços
```
<h2>Conheça nossos serviços</h2>

[dps_ai_public_chat 
  title="Tire suas dúvidas sobre nossos serviços" 
  show_faqs="true"
]
```

## Relacionado

- `docs/compatibility/EDITOR_SHORTCODE_GUIDE.md` - Guia geral sobre uso de shortcodes
- `add-ons/desi-pet-shower-ai_addon/README.md` - Documentação completa do AI Add-on
- `ANALYSIS.md` - Seção "Assistente de IA" com detalhes técnicos
