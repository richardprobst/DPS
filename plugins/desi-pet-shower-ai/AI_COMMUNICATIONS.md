# Assistente de IA para Comunicações

## Visão Geral

O AI Add-on v1.2.0 agora inclui um assistente inteligente para gerar sugestões de mensagens para WhatsApp e e-mail.

**IMPORTANTE:** A IA NUNCA envia mensagens automaticamente. Ela apenas SUGERE textos que o usuário humano revisa e confirma antes de enviar.

## Funcionalidades

### 1. Sugestões de WhatsApp
- Gera mensagens objetivas e amigáveis
- Usa tom conversacional adequado para WhatsApp
- Suporta emojis com moderação
- Preenche campo de texto para revisão do usuário

### 2. Sugestões de E-mail
- Gera assunto e corpo de e-mail
- Modal de pré-visualização antes de inserir nos campos
- Tom profissional mas amigável
- Permite edição antes de inserir no formulário

## Tipos de Mensagens Suportados

- **Lembrete**: Relembrar agendamento próximo
- **Confirmação**: Confirmar agendamento registrado
- **Pós-atendimento**: Agradecer e pedir feedback
- **Cobrança suave**: Lembrete educado de pagamento pendente
- **Cancelamento**: Confirmar cancelamento de agendamento
- **Reagendamento**: Confirmar nova data/hora

## Como Usar

### Exemplo: Botão de Sugestão para WhatsApp

```html
<!-- Campo de mensagem -->
<textarea id="whatsapp-message" rows="4"></textarea>

<!-- Botão de sugestão -->
<button 
    class="button dps-ai-suggest-whatsapp"
    data-target="#whatsapp-message"
    data-type="lembrete"
    data-client-name="João Silva"
    data-pet-name="Rex"
    data-appointment-date="15/12/2024"
    data-appointment-time="14:00"
    data-services='["Banho", "Tosa"]'
>
    Sugerir com IA
</button>
```

### Exemplo: Botão de Sugestão para E-mail

```html
<!-- Campos de e-mail -->
<input type="text" id="email-subject" />
<textarea id="email-body" rows="8"></textarea>

<!-- Botão de sugestão -->
<button 
    class="button dps-ai-suggest-email"
    data-target-subject="#email-subject"
    data-target-body="#email-body"
    data-type="pos_atendimento"
    data-client-name="Maria Santos"
    data-pet-name="Mel"
    data-appointment-date="10/12/2024"
    data-services='["Banho", "Hidratação"]'
>
    Sugerir E-mail com IA
</button>
```

## Atributos de Dados (data-*)

### Obrigatórios

- `data-type`: Tipo de mensagem (lembrete, confirmacao, pos_atendimento, cobranca_suave, cancelamento, reagendamento)

### Para WhatsApp

- `data-target`: Seletor CSS do campo textarea que receberá a sugestão

### Para E-mail

- `data-target-subject`: Seletor CSS do campo de assunto
- `data-target-body`: Seletor CSS do campo de corpo

### Opcionais (contexto)

- `data-client-name`: Nome do cliente
- `data-pet-name`: Nome do pet
- `data-appointment-date`: Data do agendamento (formato legível)
- `data-appointment-time`: Hora do agendamento
- `data-services`: Lista de serviços (JSON array ou string separada por vírgula)
- `data-groomer-name`: Nome do groomer/tosador
- `data-amount`: Valor formatado (ex: "R$ 150,00")
- `data-additional-info`: Informações adicionais

## Fluxo de Funcionamento

### WhatsApp

1. Usuário clica em "Sugerir com IA"
2. Sistema coleta contexto dos atributos data-*
3. Chamada AJAX para `dps_ai_suggest_whatsapp_message`
4. IA gera sugestão de mensagem
5. Texto é **preenchido no campo** (não enviado)
6. Usuário **revisa e edita** se necessário
7. Usuário clica em "Abrir WhatsApp" para enviar

### E-mail

1. Usuário clica em "Sugerir E-mail com IA"
2. Sistema coleta contexto dos atributos data-*
3. Chamada AJAX para `dps_ai_suggest_email_message`
4. IA gera assunto e corpo
5. **Modal de pré-visualização** abre
6. Usuário **revisa e edita** no modal
7. Usuário clica em "Inserir" para preencher campos do formulário
8. Usuário clica em botão de envio de e-mail (com confirmação)

## Tratamento de Erros

### IA Desativada ou Sem API Key

Se a IA estiver desativada nas configurações ou não houver API key configurada:

```
Mensagem: "Não foi possível gerar sugestão automática. A IA pode estar desativada ou houve um erro na API. Escreva a mensagem manualmente."
```

O campo de mensagem **não é alterado**, permitindo que o usuário escreva manualmente.

### Erro na API da OpenAI

Se houver timeout, erro de rede ou resposta inválida:

```
Mensagem: "Erro ao gerar sugestão"
```

O sistema **não quebra** e o usuário pode escrever a mensagem manualmente.

## Integração Programática

### PHP - Gerar Sugestão de WhatsApp

```php
$context = [
    'type'              => 'lembrete',
    'client_name'       => 'João Silva',
    'pet_name'          => 'Rex',
    'appointment_date'  => '15/12/2024',
    'appointment_time'  => '14:00',
    'services'          => ['Banho', 'Tosa'],
];

$result = DPS_AI_Message_Assistant::suggest_whatsapp_message( $context );

if ( null !== $result ) {
    $message_text = $result['text'];
    // Usar $message_text...
} else {
    // Erro - usar mensagem padrão ou pedir ao usuário para escrever
}
```

### PHP - Gerar Sugestão de E-mail

```php
$context = [
    'type'              => 'pos_atendimento',
    'client_name'       => 'Maria Santos',
    'pet_name'          => 'Mel',
    'appointment_date'  => '10/12/2024',
    'services'          => ['Banho', 'Hidratação'],
];

$result = DPS_AI_Message_Assistant::suggest_email_message( $context );

if ( null !== $result ) {
    $subject = $result['subject'];
    $body    = $result['body'];
    // Usar $subject e $body...
} else {
    // Erro - usar template padrão
}
```

## Segurança

- ✅ Validação de nonce em todas as chamadas AJAX
- ✅ Verificação de capabilities (`edit_posts`)
- ✅ Sanitização de todos os inputs recebidos
- ✅ Uso do `DPS_AI_Client` existente (já validado)
- ✅ Reutilização do system prompt base de segurança
- ✅ **NUNCA envia automaticamente** - apenas preenche campos

## Privacidade

- As mensagens geradas são baseadas apenas em dados do próprio sistema DPS
- Nenhum dado externo é consultado
- A API da OpenAI recebe apenas:
  - Nome do cliente e pet
  - Data/hora de agendamento
  - Serviços contratados
  - Tipo de mensagem desejada

## Configurações

As mesmas configurações de IA do Portal do Cliente se aplicam:

- **Ativar IA**: Habilita/desabilita o assistente
- **API Key**: Chave da OpenAI
- **Modelo**: GPT-3.5 Turbo (recomendado), GPT-4, etc.
- **Temperatura**: 0.5 (padrão para mensagens)
- **Max Tokens**: 300 (WhatsApp), 500 (e-mail)
- **Instruções Adicionais**: Complementam o comportamento (tom de voz, estilo)

## Limitações

- Sugestões dependem da qualidade dos dados fornecidos no contexto
- Requer conexão com internet e API key válida
- Custo por chamada à API da OpenAI
- Não substitui a revisão humana - sempre revise antes de enviar

## Changelog

### v1.2.0 (2024-12-XX)
- ✨ Adicionado assistente de mensagens para WhatsApp
- ✨ Adicionado assistente de mensagens para e-mail
- ✨ Modal de pré-visualização para e-mails
- ✨ Handlers AJAX seguros
- ✨ Interface JavaScript completa
- ✨ Suporte a 6 tipos de mensagens
- ✅ Nunca envia automaticamente - apenas sugere
