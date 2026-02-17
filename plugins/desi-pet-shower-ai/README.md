# desi.pet by PRObst – AI Add-on

**Autor:** PRObst  
**Site:** [www.probst.pro](https://www.probst.pro)

## Visão Geral

O **AI Add-on** implementa recursos de inteligência artificial para o ecossistema desi.pet by PRObst com foco em:

- Assistente no Portal do Cliente (contexto de cliente/pet)
- Chat público para visitantes via shortcode
- Sugestões assistidas de comunicação (ex.: mensagens)
- Base de conhecimento, métricas e painéis de acompanhamento

O comportamento é orientado por prompts de sistema e regras de domínio para manter respostas alinhadas ao contexto de Banho e Tosa e operação do DPS.

## Tecnologia

- **API**: OpenAI Chat Completions
- **Integração**: WordPress `wp_remote_post()` para chamadas HTTP
- **Segurança**: nonces, sanitização e validações de permissão/capability
- **Interface**: componentes frontend dedicados no Portal e em chat público

## Estrutura de Arquivos

```text
desi-pet-shower-ai/
├── desi-pet-shower-ai-addon.php
├── includes/
│   ├── class-dps-ai-client.php
│   ├── class-dps-ai-assistant.php
│   ├── class-dps-ai-integration-portal.php
│   ├── class-dps-ai-public-chat.php
│   ├── class-dps-ai-analytics.php
│   └── ...
├── assets/
│   ├── js/
│   └── css/
├── prompts/
│   ├── system-portal.txt
│   ├── system-public.txt
│   ├── system-email.txt
│   └── system-whatsapp.txt
└── README.md
```

## Configuração Básica

1. Ative o add-on no WordPress.
2. Acesse **desi.pet by PRObst > Assistente de IA**.
3. Habilite o assistente e informe a API key da OpenAI.
4. Selecione o modelo e ajuste parâmetros opcionais (`temperature`, `timeout`, `max_tokens`).
5. Salve as configurações.

## Modelos Suportados (configuração atual)

- `gpt-4o-mini` (recomendado para custo/benefício)
- `gpt-4o`
- `gpt-4-turbo`
- `gpt-3.5-turbo` (legado)

> Observação: disponibilidade, nomenclatura e preços podem variar de acordo com a OpenAI.

## Funcionalidades Principais

### Portal do Cliente

- Atendimento contextual com base nos dados disponíveis do cliente/pet
- Regras de domínio para restringir temas fora do escopo do sistema
- Respostas de fallback quando a API não estiver disponível

### Chat Público para Visitantes

Shortcode:

```text
[dps_ai_public_chat]
```

Atributos principais:

- `mode`: `inline` | `floating`
- `theme`: `light` | `dark`
- `position`: `bottom-right` | `bottom-left` (modo flutuante)
- `title`, `subtitle`, `placeholder`
- `show_faqs`: `true` | `false`
- `primary_color`: cor hexadecimal

Exemplo:

```text
[dps_ai_public_chat mode="floating" position="bottom-left" theme="dark"]
```

## Segurança

- API key tratada no backend (não exposta diretamente no frontend)
- Verificação de nonce nas ações AJAX aplicáveis
- Sanitização de entradas e escape de saídas
- Tratamento de timeout/erros de comunicação com mensagem amigável ao usuário

## Requisitos

- **WordPress:** 6.9+
- **PHP:** 8.4+
- **Plugin base:** desi.pet by PRObst Base ativo
- **Add-on recomendado:** Client Portal ativo para recursos do portal

## Observações Operacionais

- Logs e diagnósticos podem ser inspecionados pelo ambiente PHP/WordPress.
- Para detalhes técnicos adicionais, consulte os documentos auxiliares deste diretório (ex.: relatórios e sumários de implementação).
