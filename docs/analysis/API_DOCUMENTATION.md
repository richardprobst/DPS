# Documentação das APIs do DPS

Este documento descreve todas as APIs implementadas no sistema DPS by PRObst (DPS), seus propósitos, métodos públicos, endpoints AJAX e endpoints REST.

---

## Índice

1. [Visão Geral](#visão-geral)
2. [APIs Públicas (Classes PHP)](#apis-públicas-classes-php)
   - [DPS_Communications_API](#1-dps_communications_api)
   - [DPS_Finance_API](#2-dps_finance_api)
   - [DPS_Services_API](#3-dps_services_api)
   - [DPS_Stats_API](#4-dps_stats_api)
3. [Endpoints AJAX](#endpoints-ajax)
4. [Endpoints REST](#endpoints-rest)
5. [Helpers Globais](#helpers-globais)
6. [Boas Práticas de Integração](#boas-práticas-de-integração)

---

## Visão Geral

O sistema DPS implementa uma arquitetura modular baseada em APIs centralizadas que permitem:

- **Reutilização de código**: Add-ons utilizam APIs centralizadas em vez de reimplementar lógica
- **Consistência**: Operações críticas (pagamentos, comunicações, cálculos) são padronizadas
- **Extensibilidade**: Hooks e filtros permitem customização sem modificar código core
- **Manutenibilidade**: Alterações em uma API propagam automaticamente para todos os consumidores

### Resumo das APIs

| API | Propósito | Localização |
|-----|-----------|-------------|
| `DPS_Communications_API` | Envio centralizado de WhatsApp, e-mail, SMS | `add-ons/desi-pet-shower-communications_addon/includes/class-dps-communications-api.php` |
| `DPS_Finance_API` | Operações financeiras (cobranças, transações) | `add-ons/desi-pet-shower-finance_addon/includes/class-dps-finance-api.php` |
| `DPS_Services_API` | Cálculo de preços, catálogo de serviços | `add-ons/desi-pet-shower-services_addon/dps_service/includes/class-dps-services-api.php` |
| `DPS_Stats_API` | Métricas, relatórios, estatísticas | `add-ons/desi-pet-shower-stats_addon/includes/class-dps-stats-api.php` |

---

## APIs Públicas (Classes PHP)

### 1. DPS_Communications_API

**Arquivo**: `add-ons/desi-pet-shower-communications_addon/includes/class-dps-communications-api.php`

**Propósito**: Centralizar todas as comunicações do sistema (WhatsApp, e-mail, SMS). Outros add-ons devem delegar envios para esta API ao invés de implementar lógica própria de comunicação.

**Padrão**: Singleton

#### Métodos Públicos

| Método | Descrição | Parâmetros | Retorno |
|--------|-----------|------------|---------|
| `get_instance()` | Obtém instância singleton | - | `DPS_Communications_API` |
| `send_whatsapp($to, $message, $context)` | Envia mensagem via WhatsApp | `$to`: telefone, `$message`: texto, `$context`: array opcional | `bool` |
| `send_email($to, $subject, $body, $context)` | Envia e-mail | `$to`: email, `$subject`: assunto, `$body`: corpo, `$context`: array opcional | `bool` |
| `send_appointment_reminder($appointment_id)` | Envia lembrete de agendamento | `$appointment_id`: int | `bool` |
| `send_payment_notification($client_id, $amount_cents, $context)` | Notifica cliente sobre pagamento | `$client_id`: int, `$amount_cents`: int, `$context`: array opcional | `bool` |
| `send_message_from_client($client_id, $message, $context)` | Envia mensagem do cliente para admin | `$client_id`: int, `$message`: string, `$context`: array opcional | `bool` |

#### Hooks Disparados

- `dps_comm_whatsapp_message` (filter): Filtra mensagem WhatsApp antes do envio
- `dps_comm_email_subject` (filter): Filtra assunto de e-mail
- `dps_comm_email_body` (filter): Filtra corpo de e-mail
- `dps_comm_email_headers` (filter): Filtra headers de e-mail
- `dps_comm_reminder_message` (filter): Filtra mensagem de lembrete
- `dps_comm_payment_notification_message` (filter): Filtra notificação de pagamento
- `dps_after_whatsapp_sent` (action): Após envio de WhatsApp
- `dps_after_email_sent` (action): Após envio de e-mail
- `dps_after_reminder_sent` (action): Após envio de lembrete

#### Exemplo de Uso

```php
// Enviar mensagem via WhatsApp
$api = DPS_Communications_API::get_instance();
$api->send_whatsapp(
    '11987654321',
    'Seu agendamento está confirmado!',
    ['appointment_id' => 123, 'type' => 'confirmation']
);

// Enviar e-mail
$api->send_email(
    'cliente@email.com',
    'Confirmação de agendamento',
    'Seu agendamento foi confirmado para...',
    ['appointment_id' => 123]
);
```

#### Configurações

Armazenadas em `wp_options` com chave `dps_comm_settings`:

| Chave | Descrição |
|-------|-----------|
| `whatsapp_api_url` | URL do gateway WhatsApp |
| `whatsapp_api_key` | Chave de API do gateway |
| `default_email_from` | E-mail remetente padrão |
| `template_reminder` | Template de lembrete (suporta placeholders: `{client_name}`, `{pet_name}`, `{date}`, `{time}`) |
| `template_confirmation` | Template de confirmação |
| `template_post_service` | Template pós-atendimento |

---

### 2. DPS_Finance_API

**Arquivo**: `add-ons/desi-pet-shower-finance_addon/includes/class-dps-finance-api.php`

**Propósito**: Centralizar operações financeiras. TODOS os add-ons que precisam criar, atualizar ou consultar transações financeiras devem usar esta API em vez de fazer queries diretas na tabela `dps_transacoes`.

**Padrão**: Métodos estáticos

#### Métodos Públicos

| Método | Descrição | Parâmetros | Retorno |
|--------|-----------|------------|---------|
| `create_or_update_charge($data)` | Cria ou atualiza cobrança vinculada a agendamento | `$data`: array (ver estrutura) | `int\|WP_Error` |
| `mark_as_paid($charge_id, $options)` | Marca cobrança como paga | `$charge_id`: int, `$options`: array opcional | `true\|WP_Error` |
| `mark_as_pending($charge_id)` | Marca cobrança como pendente | `$charge_id`: int | `true\|WP_Error` |
| `mark_as_cancelled($charge_id, $reason)` | Marca cobrança como cancelada | `$charge_id`: int, `$reason`: string opcional | `true\|WP_Error` |
| `get_charge($charge_id)` | Busca dados de uma cobrança | `$charge_id`: int | `object\|null` |
| `get_charges_by_appointment($appointment_id)` | Busca cobranças de um agendamento | `$appointment_id`: int | `array` |
| `delete_charges_by_appointment($appointment_id)` | Remove cobranças de um agendamento | `$appointment_id`: int | `int` (número removido) |
| `validate_charge_data($data)` | Valida dados antes de criar/atualizar | `$data`: array | `true\|WP_Error` |

#### Estrutura do Parâmetro `$data` para `create_or_update_charge`

```php
$data = [
    'appointment_id' => 123,        // Obrigatório: ID do agendamento
    'client_id'      => 456,        // Obrigatório: ID do cliente
    'value_cents'    => 5000,       // Obrigatório: Valor em centavos (R$ 50,00)
    'services'       => [1, 2, 3],  // Opcional: IDs de serviços (para descrição)
    'pet_id'         => 789,        // Opcional: ID do pet
    'status'         => 'pending',  // Opcional: 'pending', 'paid', 'cancelled'
    'date'           => '2025-01-15', // Opcional: Data Y-m-d
];
```

#### Hooks Disparados

- `dps_finance_charge_created` (action): Após criar nova cobrança
- `dps_finance_charge_updated` (action): Após atualizar cobrança existente
- `dps_finance_booking_paid` (action): Quando cobrança é marcada como paga
- `dps_finance_charges_deleted` (action): Após deletar cobranças de um agendamento

#### Exemplo de Uso

```php
// Criar cobrança
$result = DPS_Finance_API::create_or_update_charge([
    'appointment_id' => 123,
    'client_id'      => 456,
    'value_cents'    => 7500, // R$ 75,00
    'services'       => [1, 2],
    'status'         => 'pending',
]);

if ( is_wp_error( $result ) ) {
    echo 'Erro: ' . $result->get_error_message();
} else {
    echo 'Cobrança criada com ID: ' . $result;
}

// Marcar como paga
DPS_Finance_API::mark_as_paid( $charge_id );

// Buscar cobrança
$charge = DPS_Finance_API::get_charge( $charge_id );
echo 'Valor: R$ ' . number_format( $charge->value_cents / 100, 2, ',', '.' );
```

#### Mapeamento de Status

| Externo (API) | Interno (Banco) |
|---------------|-----------------|
| `pending` | `em_aberto` |
| `paid` | `pago` |
| `cancelled` | `cancelado` |

---

### 3. DPS_Services_API

**Arquivo**: `add-ons/desi-pet-shower-services_addon/dps_service/includes/class-dps-services-api.php`

**Propósito**: Centralizar toda a lógica de serviços, cálculo de preços e informações detalhadas. Outros add-ons (Agenda, Finance, Portal) devem usar esta API para cálculos de preços.

**Padrão**: Métodos estáticos

#### Métodos Públicos

| Método | Descrição | Parâmetros | Retorno |
|--------|-----------|------------|---------|
| `get_service($service_id)` | Obtém dados completos de um serviço | `$service_id`: int | `array\|null` |
| `calculate_price($service_id, $pet_size, $context)` | Calcula preço por porte | `$service_id`: int, `$pet_size`: string, `$context`: array | `float\|null` |
| `calculate_appointment_total($service_ids, $pet_ids, $context)` | Calcula total de agendamento | Arrays de IDs + contexto | `array` |
| `get_services_details($appointment_id)` | Obtém detalhes de serviços de um agendamento | `$appointment_id`: int | `array` |
| `calculate_package_price($package_id, $pet_size)` | Calcula preço de pacote promocional | `$package_id`: int, `$pet_size`: string | `float\|null` |
| `get_price_history($service_id)` | Obtém histórico de alterações de preço | `$service_id`: int | `array` |
| `get_public_services($args)` | Lista serviços ativos para exibição pública | `$args`: array de filtros | `array` |
| `get_portal_services($client_id, $args)` | Obtém serviços para Portal do Cliente | `$client_id`: int, `$args`: array | `array` |
| `duplicate_service($service_id)` | Duplica um serviço existente | `$service_id`: int | `int\|false` |
| `log_price_change($service_id, $price_type, $old_price, $new_price)` | Registra alteração de preço | Parâmetros de mudança | `bool` |
| `get_service_categories()` | Lista categorias de serviços | - | `array` |

#### Estrutura de Retorno de `get_service()`

```php
[
    'id'           => 1,
    'title'        => 'Banho Completo',
    'type'         => 'padrao',     // 'padrao', 'extra', 'package'
    'category'     => 'banho',
    'active'       => true,
    'description'  => 'Banho com secagem e perfume',
    'price'        => 50.00,        // Preço base
    'price_small'  => 40.00,        // Preço porte pequeno
    'price_medium' => 50.00,        // Preço porte médio
    'price_large'  => 65.00,        // Preço porte grande
]
```

#### Normalização de Porte

| Entrada aceita | Valor normalizado |
|----------------|-------------------|
| `pequeno`, `small` | `small` |
| `medio`, `médio`, `medium` | `medium` |
| `grande`, `large` | `large` |

#### Exemplo de Uso

```php
// Obter dados de um serviço
$service = DPS_Services_API::get_service( 123 );
echo 'Serviço: ' . $service['title'];
echo 'Preço base: R$ ' . number_format( $service['price'], 2, ',', '.' );

// Calcular preço por porte
$price = DPS_Services_API::calculate_price( 123, 'médio' );
echo 'Preço para porte médio: R$ ' . number_format( $price, 2, ',', '.' );

// Calcular total de agendamento
$total = DPS_Services_API::calculate_appointment_total(
    [1, 2, 3],  // IDs de serviços
    [456],      // IDs de pets
    [
        'extras'  => 10.00,
        'taxidog' => 25.00,
    ]
);
echo 'Total: R$ ' . number_format( $total['total'], 2, ',', '.' );
```

#### Hooks Disparados

- `dps_service_duplicated` (action): Após duplicar um serviço

---

### 4. DPS_Stats_API

**Arquivo**: `add-ons/desi-pet-shower-stats_addon/includes/class-dps-stats-api.php`

**Propósito**: Centralizar lógica de estatísticas e métricas para reutilização por outros add-ons e facilitar manutenção.

**Padrão**: Métodos estáticos

#### Métodos Públicos

| Método | Descrição | Parâmetros | Retorno |
|--------|-----------|------------|---------|
| `get_appointments_count($start, $end, $status)` | Conta atendimentos no período | Datas Y-m-d, status opcional | `int` |
| `get_revenue_total($start, $end)` | Total de receitas pagas | Datas Y-m-d | `float` |
| `get_expenses_total($start, $end)` | Total de despesas pagas | Datas Y-m-d | `float` |
| `get_financial_totals($start, $end)` | Totais financeiros combinados | Datas Y-m-d | `array` |
| `get_inactive_pets($days)` | Pets sem atendimento há X dias | `$days`: int (padrão 30) | `array` |
| `get_top_services($start, $end, $limit)` | Serviços mais solicitados | Datas + limite | `array` |
| `get_period_comparison($start, $end)` | Comparativo período atual vs anterior | Datas Y-m-d | `array` |
| `get_ticket_average($start, $end)` | Ticket médio do período | Datas Y-m-d | `float` |
| `get_cancellation_rate($start, $end)` | Taxa de cancelamento | Datas Y-m-d | `float` (%) |
| `get_new_clients_count($start, $end)` | Novos clientes cadastrados | Datas Y-m-d | `int` |
| `get_species_distribution($start, $end)` | Distribuição por espécie | Datas Y-m-d | `array` |
| `get_top_breeds($start, $end, $limit)` | Raças mais atendidas | Datas + limite | `array` |
| `export_inactive_pets_csv($days)` | Exporta pets inativos em CSV | `$days`: int | `string` |
| `export_metrics_csv($start, $end)` | Exporta métricas em CSV | Datas Y-m-d | `string` |

#### Sistema de Cache

A API usa transients para cache de resultados:

| Tipo de Dado | Duração do Cache |
|--------------|------------------|
| Métricas financeiras | 1 hora |
| Contagem de atendimentos | 1 hora |
| Pets inativos | 24 horas |
| Serviços mais solicitados | 1 hora |

#### Exemplo de Uso

```php
// Obter métricas do mês atual
$start = date( 'Y-m-01' );
$end   = date( 'Y-m-t' );

$appointments = DPS_Stats_API::get_appointments_count( $start, $end );
$revenue      = DPS_Stats_API::get_revenue_total( $start, $end );
$ticket       = DPS_Stats_API::get_ticket_average( $start, $end );

echo "Atendimentos: $appointments";
echo "Receita: R$ " . number_format( $revenue, 2, ',', '.' );
echo "Ticket médio: R$ " . number_format( $ticket, 2, ',', '.' );

// Comparativo de períodos
$comparison = DPS_Stats_API::get_period_comparison( $start, $end );
echo "Variação de receita: " . $comparison['variation']['revenue'] . "%";

// Pets inativos há 30 dias
$inactive = DPS_Stats_API::get_inactive_pets( 30 );
foreach ( $inactive as $item ) {
    echo $item['pet']->post_title . ' - ' . $item['client']->post_title;
}
```

---

## Endpoints AJAX

O sistema expõe diversos endpoints AJAX para interações assíncronas:

### Plugin Base

| Endpoint | Descrição | Autenticação |
|----------|-----------|--------------|
| `dps_get_available_times` | Obtém horários disponíveis para agendamento | Público (`nopriv`) |

### Finance Add-on

| Endpoint | Descrição | Autenticação |
|----------|-----------|--------------|
| `dps_get_partial_history` | Obtém histórico de pagamentos parciais | Admin |
| `dps_delete_partial` | Remove pagamento parcial | Admin |

### Agenda Add-on

| Endpoint | Descrição | Autenticação |
|----------|-----------|--------------|
| `dps_update_status` | Atualiza status de agendamento | Admin |
| `dps_get_services_details` | Obtém detalhes de serviços (**deprecated desde v1.1.0** - use `DPS_Services_API::get_services_details()`) | Admin |

### Client Portal Add-on

| Endpoint | Descrição | Autenticação |
|----------|-----------|--------------|
| `dps_generate_client_token` | Gera token de acesso para cliente | Admin |
| `dps_revoke_client_tokens` | Revoga tokens de um cliente | Admin |
| `dps_get_whatsapp_message` | Obtém mensagem formatada para WhatsApp | Admin |
| `dps_preview_email` | Pré-visualiza e-mail de acesso | Admin |
| `dps_send_email_with_token` | Envia e-mail com token de acesso | Admin |

### AI Add-on

| Endpoint | Descrição | Autenticação |
|----------|-----------|--------------|
| `dps_ai_portal_ask` | Processa pergunta no chat do Portal | Público (`nopriv`) |
| `dps_ai_suggest_whatsapp_message` | Gera sugestão de mensagem WhatsApp | Admin |
| `dps_ai_suggest_email_message` | Gera sugestão de e-mail | Admin |

### Push Add-on

| Endpoint | Descrição | Autenticação |
|----------|-----------|--------------|
| `dps_push_send_test` | Envia notificação de teste | Admin |
| `dps_push_test_telegram` | Testa conexão com Telegram | Admin |

### Backup Add-on

| Endpoint | Descrição | Autenticação |
|----------|-----------|--------------|
| `dps_compare_backup` | Compara backup com estado atual | Admin |
| `dps_delete_backup` | Remove arquivo de backup | Admin |
| `dps_download_backup` | Baixa arquivo de backup | Admin |
| `dps_restore_from_history` | Restaura backup do histórico | Admin |

---

## Endpoints REST

O plugin base expõe uma API REST limitada:

### `GET /wp-json/dps/v1/pets`

**Propósito**: Lista pets com suporte a paginação e busca.

**Parâmetros**:
- `page` (int, padrão: 1): Página de resultados
- `search` (string): Termo de busca
- `owner` (int): Filtrar por ID do dono

**Autenticação**: Requer capability `edit_posts`

**Resposta**:
```json
{
    "pets": [
        {
            "id": 123,
            "title": "Rex",
            "owner_id": 456,
            "species": "cao",
            "breed": "Labrador"
        }
    ],
    "total": 50,
    "pages": 5
}
```

---

## Helpers Globais

Além das APIs, o sistema oferece classes helper para operações comuns:

| Classe | Propósito | Localização |
|--------|-----------|-------------|
| `DPS_Money_Helper` | Conversão de valores monetários (BR ↔ centavos) | `base_plugin/includes/class-dps-money-helper.php` |
| `DPS_Phone_Helper` | Formatação de telefones (WhatsApp, exibição) | `base_plugin/includes/class-dps-phone-helper.php` |
| `DPS_WhatsApp_Helper` | Geração de links WhatsApp | `base_plugin/includes/class-dps-whatsapp-helper.php` |
| `DPS_URL_Builder` | Construção de URLs de ação | `base_plugin/includes/class-dps-url-builder.php` |
| `DPS_Query_Helper` | Queries WP otimizadas | `base_plugin/includes/class-dps-query-helper.php` |
| `DPS_Request_Validator` | Validação de nonces e sanitização | `base_plugin/includes/class-dps-request-validator.php` |
| `DPS_Message_Helper` | Mensagens de feedback visual | `base_plugin/includes/class-dps-message-helper.php` |
| `DPS_CPT_Helper` | Registro padronizado de CPTs | `base_plugin/includes/class-dps-cpt-helper.php` |
| `DPS_Logger` | Registro de logs do sistema | `base_plugin/includes/class-dps-logger.php` |

Consulte o arquivo `ANALYSIS.md` para documentação detalhada de cada helper.

---

## Boas Práticas de Integração

### 1. Sempre use as APIs centralizadas

```php
// ❌ Errado: Query direta ao banco
global $wpdb;
$result = $wpdb->insert( $wpdb->prefix . 'dps_transacoes', [...] );

// ✅ Correto: Usar Finance API
$result = DPS_Finance_API::create_or_update_charge([...]);
```

### 2. Valide dados antes de operações

```php
// Validar antes de criar cobrança
$validation = DPS_Finance_API::validate_charge_data( $data );
if ( is_wp_error( $validation ) ) {
    return $validation;
}
```

### 3. Use os hooks para extensibilidade

```php
// Filtrar mensagem antes do envio
add_filter( 'dps_comm_whatsapp_message', function( $message, $to, $context ) {
    if ( 'reminder' === $context['type'] ) {
        $message .= "\n\nVeja mais em: https://exemplo.com/portal";
    }
    return $message;
}, 10, 3 );
```

### 4. Prefira métodos estáticos quando disponíveis

```php
// Para Finance, Services, Stats - métodos estáticos
$price = DPS_Services_API::calculate_price( $service_id, 'medio' );

// Para Communications - singleton
$api = DPS_Communications_API::get_instance();
$api->send_whatsapp( $phone, $message );
```

### 5. Trate erros adequadamente

```php
$result = DPS_Finance_API::mark_as_paid( $charge_id );

if ( is_wp_error( $result ) ) {
    DPS_Logger::log( 'error', 'Falha ao marcar como pago', [
        'charge_id' => $charge_id,
        'error'     => $result->get_error_message(),
    ]);
    DPS_Message_Helper::add_error( $result->get_error_message() );
} else {
    DPS_Message_Helper::add_success( __( 'Pagamento registrado!', 'desi-pet-shower' ) );
}
```

---

## Changelog

| Versão | Data | Alterações |
|--------|------|------------|
| 1.0.0 | 2025-12-03 | Documentação inicial das APIs |

---

*Última atualização: Dezembro 2025*
