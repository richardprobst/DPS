# Communications Add-on - API Documentation

## Visão Geral

O Communications Add-on fornece uma **API centralizada de comunicações** para todo o sistema DPS. Toda comunicação via WhatsApp, e-mail ou SMS deve passar por esta API.

## Arquitetura

### Conceito: Camada de Comunicação Centralizada

```
┌─────────────────────────────────────────────────────┐
│  INTERFACES (Gatilhos de Comunicação)               │
├─────────────────────────────────────────────────────┤
│  • Agenda Add-on (botões, lembretes automáticos)    │
│  • Client Portal (mensagens de clientes)            │
│  • Finance Add-on (notificações de pagamento)       │
│  • Outros add-ons                                   │
└────────────────┬────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────┐
│  COMMUNICATIONS API (Camada Central)                │
├─────────────────────────────────────────────────────┤
│  • DPS_Communications_API::get_instance()           │
│  • send_whatsapp($to, $message, $context)           │
│  • send_email($to, $subject, $body, $context)       │
│  • send_appointment_reminder($appointment_id)       │
│  • send_payment_notification($client_id, $amount)   │
│  • send_message_from_client($client_id, $message)   │
├─────────────────────────────────────────────────────┤
│  • Aplica templates de mensagens                    │
│  • Registra logs de envio (DPS_Logger)              │
│  • Formata telefones (DPS_Phone_Helper)             │
│  • Dispara hooks para extensibilidade               │
└────────────────┬────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────┐
│  GATEWAYS (Envio Efetivo)                           │
├─────────────────────────────────────────────────────┤
│  • Gateway WhatsApp (configurável)                  │
│  • wp_mail (e-mail nativo WordPress)                │
│  • SMS (futuro)                                     │
└─────────────────────────────────────────────────────┘
```

### Vantagens desta Arquitetura

1. **Centralização**: Toda lógica de envio em um único lugar
2. **Rastreabilidade**: Logs automáticos de todas as comunicações
3. **Consistência**: Templates e formatação padronizados
4. **Extensibilidade**: Hooks para personalização por outros add-ons
5. **Manutenibilidade**: Alterações no gateway não afetam outros add-ons

## API Pública

### Instância Singleton

```php
$api = DPS_Communications_API::get_instance();
```

### Métodos Principais

#### 1. send_whatsapp()

Envia mensagem via WhatsApp.

```php
$api->send_whatsapp( string $to, string $message, array $context = [] ): bool
```

**Parâmetros:**
- `$to` (string): Número de telefone (será formatado automaticamente)
- `$message` (string): Mensagem a enviar
- `$context` (array): Contexto adicional para logs e hooks

**Retorno:** `bool` - true se enviado com sucesso

**Exemplo:**
```php
$api = DPS_Communications_API::get_instance();
$api->send_whatsapp(
    '11987654321',
    'Seu agendamento está confirmado!',
    [
        'appointment_id' => 123,
        'type'           => 'confirmation'
    ]
);
```

#### 2. send_email()

Envia e-mail.

```php
$api->send_email( string $to, string $subject, string $body, array $context = [] ): bool
```

**Parâmetros:**
- `$to` (string): Endereço de e-mail do destinatário
- `$subject` (string): Assunto do e-mail
- `$body` (string): Corpo da mensagem
- `$context` (array): Contexto adicional para logs e hooks

**Retorno:** `bool` - true se enviado com sucesso

**Exemplo:**
```php
$api->send_email(
    'cliente@email.com',
    'Confirmação de agendamento',
    'Seu agendamento foi confirmado para...',
    [ 'appointment_id' => 123 ]
);
```

#### 3. send_appointment_reminder()

Envia lembrete de agendamento (WhatsApp ou e-mail).

```php
$api->send_appointment_reminder( int $appointment_id ): bool
```

**Parâmetros:**
- `$appointment_id` (int): ID do agendamento

**Retorno:** `bool` - true se enviado

**Comportamento:**
- Busca dados do agendamento (cliente, pet, data, hora)
- Usa template configurado em "Comunicações" → "Template de lembrete"
- Prioriza WhatsApp se disponível, fallback para e-mail
- Dispara hook `dps_after_reminder_sent`

**Exemplo:**
```php
$api->send_appointment_reminder( 123 );
```

#### 4. send_payment_notification()

Envia notificação de pagamento ao cliente.

```php
$api->send_payment_notification( int $client_id, int $amount_cents, array $context = [] ): bool
```

**Parâmetros:**
- `$client_id` (int): ID do cliente
- `$amount_cents` (int): Valor em centavos
- `$context` (array): Contexto adicional (appointment_id, transaction_id, etc.)

**Retorno:** `bool` - true se enviado

**Exemplo:**
```php
$api->send_payment_notification(
    456,
    5000, // R$ 50,00
    [
        'appointment_id'  => 123,
        'transaction_id'  => 789
    ]
);
```

#### 5. send_message_from_client()

Envia mensagem de um cliente para o admin (via Portal).

```php
$api->send_message_from_client( int $client_id, string $message, array $context = [] ): bool
```

**Parâmetros:**
- `$client_id` (int): ID do cliente que está enviando
- `$message` (string): Mensagem do cliente
- `$context` (array): Contexto adicional

**Retorno:** `bool` - true se enviado

**Exemplo:**
```php
$api->send_message_from_client(
    456,
    'Preciso remarcar o agendamento de amanhã',
    [ 'message_id' => 789 ]
);
```

## Hooks

### Actions (após envio)

#### dps_after_whatsapp_sent
Disparado após tentativa de envio de WhatsApp.

```php
do_action( 'dps_after_whatsapp_sent', string $to, string $message, array $context, bool $result );
```

**Parâmetros:**
- `$to`: Número formatado do destinatário
- `$message`: Mensagem enviada
- `$context`: Contexto fornecido na chamada
- `$result`: true se enviado com sucesso, false caso contrário

**Exemplo:**
```php
add_action( 'dps_after_whatsapp_sent', function( $to, $message, $context, $result ) {
    if ( $result && isset( $context['appointment_id'] ) ) {
        update_post_meta( $context['appointment_id'], 'whatsapp_sent', current_time( 'mysql' ) );
    }
}, 10, 4 );
```

#### dps_after_email_sent
Disparado após tentativa de envio de e-mail.

```php
do_action( 'dps_after_email_sent', string $to, string $subject, string $body, array $context, bool $result );
```

#### dps_after_reminder_sent
Disparado após envio de lembrete de agendamento.

```php
do_action( 'dps_after_reminder_sent', int $appointment_id, bool $sent );
```

### Filters (antes do envio)

#### dps_comm_whatsapp_message
Filtra mensagem de WhatsApp antes do envio.

```php
apply_filters( 'dps_comm_whatsapp_message', string $message, string $to, array $context ): string
```

**Exemplo:**
```php
add_filter( 'dps_comm_whatsapp_message', function( $message, $to, $context ) {
    // Adiciona assinatura a todas as mensagens
    return $message . "\n\n--\nDesi Pet Shower";
}, 10, 3 );
```

#### dps_comm_email_subject
Filtra assunto do e-mail antes do envio.

```php
apply_filters( 'dps_comm_email_subject', string $subject, string $to, array $context ): string
```

#### dps_comm_email_body
Filtra corpo do e-mail antes do envio.

```php
apply_filters( 'dps_comm_email_body', string $body, string $to, array $context ): string
```

#### dps_comm_email_headers
Filtra headers do e-mail.

```php
apply_filters( 'dps_comm_email_headers', array $headers, string $to, array $context ): array
```

#### dps_comm_reminder_message
Filtra mensagem de lembrete após aplicar template.

```php
apply_filters( 'dps_comm_reminder_message', string $message, int $appointment_id ): string
```

#### dps_comm_payment_notification_message
Filtra mensagem de notificação de pagamento.

```php
apply_filters( 'dps_comm_payment_notification_message', string $message, int $client_id, int $amount_cents, array $context ): string
```

## Templates de Mensagens

Templates são configurados em `[dps_configuracoes]` → aba "Comunicações".

### Placeholders Disponíveis

Para templates de agendamento:
- `{appointment_id}` - ID do agendamento
- `{appointment_title}` - Título do agendamento
- `{client_name}` - Nome do cliente
- `{pet_name}` - Nome do pet
- `{date}` - Data formatada (dd/mm/yyyy)
- `{time}` - Hora formatada (HH:mm)

**Exemplo de template de lembrete:**
```
Olá {client_name}, lembrete: você tem agendamento para {pet_name} em {date} às {time}. Te esperamos!
```

## Helpers Relacionados

### DPS_Phone_Helper

Helper global para formatação de telefones (carregado pelo plugin base).

```php
// Formata para WhatsApp (adiciona código do país se necessário)
$formatted = DPS_Phone_Helper::format_for_whatsapp( '11987654321' );
// Retorna: '5511987654321'

// Formata para exibição brasileira
$display = DPS_Phone_Helper::format_for_display( '5511987654321' );
// Retorna: '(11) 98765-4321'

// Valida telefone brasileiro
$valid = DPS_Phone_Helper::is_valid_brazilian_phone( '11987654321' );
// Retorna: true
```

## Integração com Outros Add-ons

### Agenda Add-on

A Agenda **mantém** suas interfaces operacionais (botões de confirmação, cobrança via WhatsApp), mas delega o envio automático para a API:

- **Lembretes diários**: usa `send_appointment_reminder()`
- **Notificações de status**: usa `send_whatsapp()` quando status muda para "finalizado"

Os **links wa.me** (botões clicáveis) **permanecem na interface** - não são envios automáticos.

### Client Portal Add-on

O Portal **mantém** o formulário de mensagens, mas delega o envio para a API:

- **Mensagens de clientes**: usa `send_message_from_client()`

### Finance Add-on

Pode usar a API para notificações de pagamento:

```php
// Após confirmar pagamento
if ( class_exists( 'DPS_Communications_API' ) ) {
    $api = DPS_Communications_API::get_instance();
    $api->send_payment_notification( $client_id, $amount_cents, [
        'transaction_id' => $transaction_id
    ] );
}
```

## Logs

Todas as comunicações são registradas via `DPS_Logger`:

- **Nível INFO**: Envios bem-sucedidos
- **Nível ERROR**: Falhas de envio
- **Nível WARNING**: Problemas não-críticos (ex.: cliente sem telefone)

Logs podem ser visualizados em "DPS Logs" no admin do WordPress.

## Configuração

### Opções em `dps_comm_settings`

- `whatsapp_api_key`: Chave de API do gateway WhatsApp
- `whatsapp_api_url`: URL base do gateway WhatsApp
- `default_email_from`: E-mail remetente padrão
- `template_confirmation`: Template de confirmação de agendamento
- `template_reminder`: Template de lembrete
- `template_post_service`: Template de pós-atendimento

## Roadmap

### v0.3.0 (Futuro)
- Integração real com gateway WhatsApp (Evolution API, etc.)
- Suporte a SMS
- Histórico de comunicações no painel admin
- Retry automático para falhas
- Templates avançados com condicionais

## Changelog

### v0.2.0 (2025-11-22)
- ✨ Criada API centralizada `DPS_Communications_API`
- ✨ Adicionado `DPS_Phone_Helper` no núcleo
- ♻️ Refatorado Communications Add-on para usar API
- ♻️ Refatorado Agenda Add-on para delegar envios
- ♻️ Refatorado Client Portal para delegar envios
- 🔧 Implementados hooks para extensibilidade
- 📝 Logs automáticos de todas as comunicações

### v0.1.0
- Versão inicial com funcionalidades básicas
