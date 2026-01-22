# Análise de Integração com Google Workspace (Tasks + Calendar)

**Autor:** PRObst  
**Data:** 2026-01-19  
**Versão:** 2.0.0  
**Status:** Proposta de análise expandida  
**Atualização:** 2026-01-19 - Adicionada integração com Google Calendar  

## Sumário Executivo

Este documento analisa a viabilidade e benefícios de integrar o sistema DPS (desi.pet by PRObst) com **Google Tasks API** e **Google Calendar API**. A integração permitiria sincronizar atividades do sistema de gestão de pet shop com:
- **Google Tasks**: Tarefas administrativas (follow-ups, cobranças, lembretes)
- **Google Calendar**: Agendamentos de atendimentos (banho, tosa, etc.)

Esta combinação oferece visibilidade completa da operação do pet shop no ecossistema Google.

### Conclusão Rápida

✅ **VIÁVEL e ALTAMENTE RECOMENDADO** - A integração dupla (Tasks + Calendar) é tecnicamente viável e oferece benefícios complementares:

**Google Tasks** (Tarefas Administrativas):
- Lembretes e follow-ups de agendamentos
- Tarefas de gestão financeira (cobranças pendentes)
- Atividades de comunicação com clientes
- Gestão de estoque e tarefas operacionais

**Google Calendar** (Agendamentos Operacionais):
- Visualização visual de horários de atendimento
- Sincronização com calendário pessoal da equipe
- Notificações de compromissos iminentes
- Gestão de capacidade e disponibilidade

### Benefícios Principais

1. **Visibilidade Completa**: Calendário mostra QUANDO atender, Tasks mostra O QUE fazer
2. **Notificações Nativas**: Sistema de notificações do Google (mobile, desktop, email) para ambos
3. **Integração Total**: Calendar e Tasks já se comunicam nativamente no ecossistema Google
4. **Acessibilidade**: Ambos acessíveis de qualquer dispositivo com conta Google
5. **Sem Custo Adicional**: Ambas APIs são gratuitas (dentro de cotas generosas)
6. **Sincronização Bidirecional** (Calendar): Alterações no Google Calendar podem refletir no DPS

### Decisão Arquitetural: Onde Implementar?

**RECOMENDAÇÃO: Integrar no add-on Agenda existente (`desi-pet-shower-agenda`)**

**Justificativa:**
- ✅ **Coesão funcional**: Agenda já gerencia agendamentos, faz sentido ela sincronizar com Calendar
- ✅ **Menor complexidade**: Evita dependências circulares entre add-ons
- ✅ **Reutilização de código**: Agenda já formata dados de agendamentos
- ✅ **Experiência do usuário**: Configuração única em um só lugar (Agenda → Integrações Google)
- ✅ **Manutenção simplificada**: Um único add-on para manter e testar

**Estrutura proposta:**
```
desi-pet-shower-agenda/
├── includes/
│   ├── integrations/
│   │   ├── class-dps-google-auth.php          # OAuth compartilhado
│   │   ├── class-dps-google-calendar-sync.php # Sincronização Calendar
│   │   └── class-dps-google-tasks-sync.php    # Sincronização Tasks
│   └── ... (arquivos existentes)
```

---

## 1. Visão Geral da Google Tasks API

### 1.1. O que é Google Tasks?

Google Tasks é um gerenciador de tarefas integrado ao ecossistema Google, disponível em:
- Web (tasks.google.com, integrado ao Gmail e Calendar)
- Android (app Google Tasks)
- iOS (app Google Tasks)
- APIs REST para integração programática

### 1.2. Recursos Principais da API

| Recurso | Descrição | Relevância para DPS |
|---------|-----------|---------------------|
| **Task Lists** | Listas de tarefas (ex: "Pet Shop - Agendamentos") | ✅ Organizar tarefas por categoria |
| **Tasks** | Tarefas individuais com título, descrição, data | ✅ Criar lembretes de agendamentos, cobranças |
| **Due Dates** | Data de vencimento com lembretes automáticos | ✅ Sincronizar datas de agendamentos |
| **Subtasks** | Tarefas aninhadas (hierarquia) | ✅ Quebrar tarefas complexas |
| **Completion Status** | Marcar como concluída | ✅ Rastrear status de follow-ups |
| **Notes** | Campo de texto livre para detalhes | ✅ Adicionar contexto (cliente, pet, serviço) |

### 1.3. Limitações Conhecidas

| Limitação | Impacto | Mitigação |
|-----------|---------|-----------|
| Sem suporte a anexos | Não é possível enviar fotos de pets | Incluir link para o portal DPS na descrição |
| Sem campos customizados | Impossível adicionar metadados estruturados | Usar formato padronizado nas notas |
| Quota: 50,000 requisições/dia | Limite global por projeto | Implementar cache e batch operations |
| Sem notificações webhook | API não envia notificações de mudanças | Polling periódico ou sincronização unidirecional |

---

## 1B. Visão Geral da Google Calendar API

### 1B.1. O que é Google Calendar?

Google Calendar é o calendário online do Google, disponível em:
- Web (calendar.google.com)
- Android (app Google Calendar)
- iOS (app Google Calendar)
- Integrado nativamente em Gmail, Google Workspace
- APIs REST v3 para integração programática

### 1B.2. Recursos Principais da API

| Recurso | Descrição | Relevância para DPS |
|---------|-----------|---------------------|
| **Events** | Eventos de calendário com data/hora início e fim | ✅ Agendamentos de atendimento (banho, tosa) |
| **Attendees** | Participantes do evento (com email) | ✅ Adicionar groomer responsável |
| **Reminders** | Lembretes automáticos (popup, email) | ✅ Notificações antes do atendimento |
| **Color Coding** | Cor do evento (11 cores padrão) | ✅ Diferenciar tipos de serviço visualmente |
| **Recurrence** | Eventos recorrentes (RRULE) | ✅ Assinaturas com frequência semanal/quinzenal |
| **Extended Properties** | Metadados customizados | ✅ Armazenar ID do agendamento DPS |
| **Free/Busy** | Consultar disponibilidade | ✅ Verificar capacidade de horários |
| **Watch/Webhook** | Notificações push de mudanças | ✅ Sincronização bidirecional (Calendar → DPS) |

### 1B.3. Vantagens sobre Google Tasks para Agendamentos

| Aspecto | Google Tasks | Google Calendar | Vencedor |
|---------|--------------|-----------------|----------|
| **Visualização temporal** | Lista simples | Grid visual por dia/semana/mês | ✅ Calendar |
| **Horário início/fim** | Apenas data de vencimento | Horário exato de início e fim | ✅ Calendar |
| **Lembretes** | Limitados | Múltiplos lembretes (popup, email, SMS) | ✅ Calendar |
| **Participantes** | Não suporta | Email de participantes (groomer) | ✅ Calendar |
| **Sincronização bidirecional** | Não (sem webhook) | Sim (webhook push) | ✅ Calendar |
| **Capacidade/Lotação** | Não | Free/Busy API | ✅ Calendar |
| **Cores visuais** | Não | 11 cores padrão | ✅ Calendar |
| **Eventos recorrentes** | Subtarefas manuais | RRULE nativo | ✅ Calendar |

**Conclusão:** Google Calendar é SUPERIOR para agendamentos operacionais (quando atender), enquanto Google Tasks é melhor para tarefas administrativas (o que fazer).

### 1B.4. Limitações Conhecidas

| Limitação | Impacto | Mitigação |
|-----------|---------|-----------|
| Quota: 1,000,000 requisições/dia (gratuito) | Muito generosa | Improvável atingir |
| Requer email para participantes | Groomer sem email não pode ser adicionado | Adicionar apenas na descrição |
| Webhook expira após 1 semana | Precisa renovar | Cron job semanal para renovar |
| Sincronização bidirecional complexa | Risco de loops infinitos | Usar flags de controle (`_synced_from_google`) |

---

## 2. Funcionalidades do DPS Compatíveis com Google Calendar + Tasks

### 🔵 Divisão Estratégica: Calendar vs Tasks

**Google Calendar** → Agendamentos operacionais (QUANDO atender)
- Atendimentos de banho e tosa
- Compromissos com hora de início e fim
- Visualização temporal da equipe

**Google Tasks** → Tarefas administrativas (O QUE fazer)
- Follow-ups pós-atendimento
- Cobranças pendentes
- Lembretes e ações internas

---

### 2.1. Agendamentos Operacionais → **GOOGLE CALENDAR** (Alta Prioridade)

**Add-on:** `desi-pet-shower-agenda`

#### 2.1.1. Evento de Atendimento no Calendário

**Exemplo de evento:**
```
📅 GOOGLE CALENDAR EVENT

Título: 🐾 Banho e Tosa - Rex (João Silva)

Início: 15/12/2024 14:00
Fim:    15/12/2024 15:30

Descrição:
  Cliente: João Silva
  Telefone: (11) 98765-4321
  Pet: Rex (Labrador, Grande)
  Serviços: Banho, Tosa
  Valor: R$ 150,00
  
  🔗 Ver no DPS: https://petshop.com.br/admin/agendamento/123

Participantes:
  - maria@petshop.com.br (Groomer Maria Santos)

Cor: Azul (serviço Tosa)
Lembrete: 1 hora antes (popup), 15 minutos antes (email)

Extended Properties:
  dps_appointment_id: 123
  dps_client_id: 456
  dps_pet_id: 789
```

**Trigger:** Ao salvar novo agendamento com status "pendente"  
**Ação no Google Calendar:** Criar evento no horário exato do atendimento  
**Sincronização bidirecional:**
- DPS → Calendar: Criar/atualizar/deletar evento
- Calendar → DPS: Reagendar no DPS se admin alterar horário no Calendar (webhook)
**Marcação como concluída:** Quando agendamento muda para status "realizado", cor do evento muda para verde

#### 2.1.2. Códigos de Cores por Tipo de Serviço

| Serviço | Cor Google Calendar | Código |
|---------|---------------------|--------|
| Banho | Azul claro (#a4bdfc) | 1 |
| Tosa | Azul (#5484ed) | 9 |
| Banho + Tosa | Roxo (#b99aff) | 3 |
| Consulta Veterinária | Verde (#51b749) | 10 |
| TaxiDog | Amarelo (#fbd75b) | 5 |
| Emergência | Vermelho (#dc2127) | 11 |

#### 2.1.3. Assinaturas Recorrentes

**Para assinaturas semanais/quinzenais:**
```
Recorrência (RRULE):
  FREQ=WEEKLY;INTERVAL=1;BYDAY=MO;COUNT=4
  (Toda segunda-feira, 4 ocorrências)
```

**Vantagem:** Google Calendar exibe série inteira visualmente, facilitando visualização de capacidade futura.

---

### 2.2. Tarefas Administrativas → **GOOGLE TASKS** (Alta Prioridade)

**Add-on:** `desi-pet-shower-agenda` (tarefas relacionadas a agendamentos)

#### 2.2.1. Follow-up Pós-Atendimento
```
✅ GOOGLE TASKS

Título: 📞 Follow-up: Rex (João Silva) - Pós-Atendimento
Descrição:
  Agendamento realizado em: 15/12/2024
  Serviços prestados: Banho, Tosa
  Ação: Ligar para verificar satisfação
  
Data de Vencimento: 17/12/2024 (2 dias após)
```

**Trigger:** Agendamento marcado como "realizado"  
**Ação no Google Tasks:** Criar tarefa de follow-up para 2 dias depois  

---

### 2.3. Financeiro → **GOOGLE TASKS** (Alta Prioridade)

**Add-on:** `desi-pet-shower-finance`

**Casos de Uso:**

#### 2.3.1. Cobrança Pendente
```
Título: 💰 Cobrança: João Silva - R$ 150,00 (Venc. 20/12/2024)
Descrição:
  Cliente: João Silva (11) 98765-4321
  Valor: R$ 150,00
  Referência: Agendamento #123 - Banho e Tosa Rex
  Status: Pendente
  
  Ações:
  - [ ] Enviar lembrete via WhatsApp
  - [ ] Gerar link de pagamento Mercado Pago
  
  Link: https://petshop.com.br/admin/financeiro/transacao/456
Data de Vencimento: 20/12/2024
```

**Trigger:** Transação criada com status "pendente"  
**Ação no Google Tasks:** Criar tarefa 1 dia antes do vencimento  
**Marcação como concluída:** Quando transação muda para status "pago"  

#### 2.3.2. Renovação de Assinatura
```
Título: 🔄 Renovação Assinatura: Maria Santos - Pacote Mensal
Descrição:
  Cliente: Maria Santos
  Pet: Mel (Poodle)
  Pacote: Banho Semanal
  Valor: R$ 200,00/mês
  Vencimento: 01/01/2025
  
  Link: https://petshop.com.br/admin/assinaturas/789
Data de Vencimento: 27/12/2024 (5 dias antes)
```

**Trigger:** 5 dias antes do vencimento de ciclo de assinatura  
**Ação no Google Tasks:** Criar tarefa de renovação  

---

### 2.3. Comunicações (Média Prioridade)

**Add-on:** `desi-pet-shower-communications`

**Casos de Uso:**

#### 2.3.1. Responder Mensagem do Portal
```
Título: 💬 Responder: João Silva - Solicitação de Agendamento
Descrição:
  Cliente: João Silva
  Mensagem recebida em: 14/12/2024 10:30
  
  "Olá! Gostaria de agendar um banho para o Rex 
   na próxima semana. Quais horários disponíveis?"
  
  Link: https://petshop.com.br/admin/portal/mensagens/321
Data de Vencimento: 14/12/2024 (mesmo dia)
```

**Trigger:** Nova mensagem recebida no Portal do Cliente  
**Ação no Google Tasks:** Criar tarefa imediata  
**Marcação como concluída:** Quando mensagem recebe resposta  

---

### 2.4. Estoque (Média Prioridade)

**Add-on:** `desi-pet-shower-stock`

**Casos de Uso:**

#### 2.4.1. Alerta de Estoque Baixo
```
Título: 📦 Repor Estoque: Shampoo Hipoalergênico
Descrição:
  Item: Shampoo Hipoalergênico 5L
  Quantidade Atual: 2 unidades
  Quantidade Mínima: 5 unidades
  Fornecedor: Pet Supply LTDA
  
  Link: https://petshop.com.br/admin/estoque/item/55
Data de Vencimento: 16/12/2024 (em 2 dias)
```

**Trigger:** Estoque atinge quantidade mínima configurada  
**Ação no Google Tasks:** Criar tarefa de reposição  

---

### 2.5. Campanhas & Fidelidade (Baixa Prioridade)

**Add-on:** `desi-pet-shower-loyalty`

**Casos de Uso:**

#### 2.5.1. Executar Campanha
```
Título: 📣 Campanha: Natal 2024 - Enviar Cupons
Descrição:
  Campanha: Natal 2024 - 20% OFF
  Público-Alvo: 150 clientes ativos
  
  Ações:
  - [ ] Gerar cupons de desconto
  - [ ] Enviar via WhatsApp
  - [ ] Postar nas redes sociais
  
  Link: https://petshop.com.br/admin/campanhas/10
Data de Vencimento: 20/12/2024
```

**Trigger:** Data de início da campanha se aproxima (5 dias antes)  

---

## 3. Arquitetura Proposta

### 3.1. Decisão: Integrar no Add-on Agenda Existente

**RECOMENDAÇÃO FINAL: Expandir `desi-pet-shower-agenda` com módulo de integrações Google**

**Justificativa detalhada:**

| Critério | Add-on Novo | Integrar na Agenda | Vencedor |
|----------|-------------|-------------------|----------|
| **Coesão funcional** | Baixa (lógica de agendamentos espalhada) | Alta (tudo relacionado a agenda em um lugar) | ✅ Agenda |
| **Reutilização de código** | Precisa duplicar formatação de agendamentos | Reutiliza lógica existente | ✅ Agenda |
| **Complexidade de manutenção** | 2 add-ons para manter | 1 add-on com módulos | ✅ Agenda |
| **Dependências** | Agenda depende do novo add-on | Sem dependências circulares | ✅ Agenda |
| **Experiência do usuário** | 2 páginas de configuração | 1 página com abas | ✅ Agenda |
| **Testabilidade** | Precisa testar integração entre add-ons | Testa módulos isolados | ✅ Agenda |
| **Evolução futura** | Difícil adicionar Google Drive, Sheets, etc. | Fácil: adiciona novo módulo de integração | ✅ Agenda |

**Estrutura Proposta:**
```
plugins/desi-pet-shower-agenda/
├── desi-pet-shower-agenda-addon.php
├── includes/
│   ├── integrations/                                # NOVO: Módulo de integrações
│   │   ├── class-dps-google-auth.php               # OAuth 2.0 compartilhado
│   │   ├── class-dps-google-calendar-client.php    # Cliente HTTP Calendar API
│   │   ├── class-dps-google-calendar-sync.php      # Sincronização Calendar
│   │   ├── class-dps-google-tasks-client.php       # Cliente HTTP Tasks API
│   │   ├── class-dps-google-tasks-sync.php         # Sincronização Tasks
│   │   └── class-dps-google-integrations-settings.php # UI configuração
│   ├── class-dps-agenda-hub.php                     # EXISTENTE
│   ├── class-dps-agenda-payment-helper.php          # EXISTENTE
│   └── ... (demais arquivos existentes)
├── assets/
│   ├── css/
│   │   └── google-integrations.css                  # NOVO
│   └── js/
│       └── google-integrations.js                   # NOVO
└── README.md
```

### 3.2. Fluxo de Autenticação OAuth 2.0 Compartilhado

```
1. Admin acessa: Agenda → Integrações Google
2. Clica em "Conectar com Google"
3. Redirecionado para tela de consentimento Google OAuth
4. Autoriza acesso a Calendar + Tasks
5. Google redireciona de volta com authorization code
6. Plugin troca code por access_token + refresh_token
7. Tokens armazenados em wp_options (criptografados)
8. Sincronização ativada para ambos (Calendar + Tasks)
```

**Permissões OAuth necessárias:**
- `https://www.googleapis.com/auth/calendar` (leitura e escrita de eventos)
- `https://www.googleapis.com/auth/tasks` (leitura e escrita de tarefas)

**Classe `DPS_Google_Auth` (compartilhada):**
```php
class DPS_Google_Auth {
    const SCOPES = [
        'https://www.googleapis.com/auth/calendar',
        'https://www.googleapis.com/auth/tasks',
    ];
    
    public function get_auth_url() { /* ... */ }
    public function exchange_code_for_tokens( $code ) { /* ... */ }
    public function refresh_access_token() { /* ... */ }
    public function is_connected() { /* ... */ }
    public function disconnect() { /* ... */ }
}
```

### 3.3. Fluxo de Sincronização - Google Calendar

```mermaid
sequenceDiagram
    participant DPS as DPS Sistema
    participant Hook as WordPress Hooks
    participant Sync as DPS_Google_Calendar_Sync
    participant API as Google Calendar API
    
    DPS->>Hook: dps_base_after_save_appointment
    Hook->>Sync: handle_appointment_saved($appointment_id)
    Sync->>Sync: format_appointment_as_event($appointment_id)
    Sync->>API: POST /calendar/v3/calendars/{calendarId}/events
    API-->>Sync: event_id
    Sync->>DPS: update_post_meta($appointment_id, '_google_calendar_event_id', $event_id)
```

**Sincronização bidirecional (Calendar → DPS):**
```mermaid
sequenceDiagram
    participant Calendar as Google Calendar
    participant Webhook as DPS Webhook Endpoint
    participant DPS as DPS Sistema
    
    Calendar->>Webhook: POST /wp-json/dps/v1/google-calendar-webhook
    Webhook->>Webhook: Verificar assinatura + sync token
    Webhook->>DPS: get_post_by_meta('_google_calendar_event_id', $event_id)
    Webhook->>DPS: update_post_meta($appointment_id, 'appointment_date', $new_date)
    Webhook->>DPS: update_post_meta($appointment_id, '_synced_from_google', true)
```

### 3.4. Fluxo de Sincronização - Google Tasks

```mermaid
sequenceDiagram
    participant DPS as DPS Sistema
    participant Hook as WordPress Hooks
    participant Sync as DPS_Google_Tasks_Sync
    participant API as Google Tasks API
    
    DPS->>Hook: dps_finance_transaction_created
    Hook->>Sync: handle_transaction_created($transaction_id)
    Sync->>Sync: format_transaction_as_task($transaction_id)
    Sync->>API: POST /tasks/v1/lists/{listId}/tasks
    API-->>Sync: task_id
    Sync->>DPS: update_post_meta($transaction_id, 'google_task_id', $task_id)
```

### 3.5. Mapeamento de Entidades

| Entidade DPS | Google Calendar | Google Tasks | Prioridade |
|--------------|-----------------|--------------|------------|
| Agendamento (pendente) | ✅ Event com horário | ❌ (apenas evento) | Alta |
| Agendamento (realizado) | ✅ Event (cor verde) | ❌ | Alta |
| Follow-up pós-atendimento | ❌ | ✅ Task | Alta |
| Transação (pendente) | ❌ | ✅ Task | Alta |
| Transação (paga) | ❌ | ✅ Task (concluída) | Alta |
| Mensagem do Portal | ❌ | ✅ Task | Média |
| Alerta de Estoque | ❌ | ✅ Task | Baixa |
| Alerta de Estoque | Task (lista "Estoque") | Unidirecional (DPS → Google) |

**Decisão de Design:** Sincronização **unidirecional** (DPS → Google Tasks)
- Tarefas criadas no Google Tasks **não** criam agendamentos no DPS
- Marcar tarefa como concluída no Google Tasks **não** atualiza DPS
- DPS é a "fonte da verdade" (single source of truth)
- Google Tasks é uma **visualização auxiliar** para follow-up

---

## 4. Estrutura de Dados

### 4.1. Novas Tabelas

Nenhuma tabela customizada necessária. Usar metadados de posts existentes.

### 4.2. Metadados Adicionados

#### Em `dps_agendamento` (post meta):
- `_google_task_id` (string): ID da tarefa no Google Tasks
- `_google_task_synced_at` (datetime): Timestamp da última sincronização
- `_google_task_list_id` (string): ID da lista onde tarefa foi criada

#### Em `dps_transacoes` (post meta ou coluna):
- `google_task_id` (string): ID da tarefa no Google Tasks
- `google_task_synced_at` (datetime): Timestamp da última sincronização

### 4.3. Opções do WordPress

```php
[
    'dps_google_tasks_settings' => [
        'enabled'               => bool,    // Habilita/desabilita sincronização
        'access_token'          => string,  // Token de acesso (criptografado)
        'refresh_token'         => string,  // Token de atualização (criptografado)
        'token_expires_at'      => int,     // Timestamp de expiração do access_token
        'default_list_id'       => string,  // ID da lista padrão "Pet Shop - DPS"
        'sync_appointments'     => bool,    // Sincronizar agendamentos
        'sync_finances'         => bool,    // Sincronizar financeiro
        'sync_communications'   => bool,    // Sincronizar mensagens do portal
        'sync_stock'            => bool,    // Sincronizar alertas de estoque
        'appointment_lead_days' => int,     // Dias de antecedência para lembrete (padrão: 1)
        'finance_lead_days'     => int,     // Dias de antecedência para cobrança (padrão: 1)
    ],
    
    'dps_google_tasks_lists' => [
        'appointments'    => 'task_list_id_123',  // Lista "Agendamentos"
        'finances'        => 'task_list_id_456',  // Lista "Financeiro"
        'communications'  => 'task_list_id_789',  // Lista "Comunicações"
        'stock'           => 'task_list_id_012',  // Lista "Estoque"
    ],
]
```

---

## 5. Hooks do Sistema

### 5.1. Hooks Consumidos (do DPS)

```php
// Agendamentos
add_action( 'dps_base_after_save_appointment', [ $this, 'sync_appointment_created' ], 10, 1 );
add_action( 'dps_base_appointment_status_changed', [ $this, 'sync_appointment_status' ], 10, 2 );

// Financeiro
add_action( 'dps_finance_transaction_created', [ $this, 'sync_finance_task' ], 10, 1 );
add_action( 'dps_finance_booking_paid', [ $this, 'complete_finance_task' ], 10, 2 );

// Portal do Cliente (mensagens)
add_action( 'dps_client_portal_message_received', [ $this, 'sync_portal_message' ], 10, 1 );

// Estoque
add_action( 'dps_stock_low_alert', [ $this, 'sync_stock_alert' ], 10, 1 );
```

**NOTA:** Alguns desses hooks ainda não existem no sistema atual. Será necessário:
1. Adicionar hooks no DPS Base e add-ons relevantes
2. OU usar abordagem alternativa com `save_post_{post_type}` e verificação de mudanças

### 5.2. Hooks Expostos (pelo add-on)

```php
/**
 * Permite customizar tarefa antes de sincronizar.
 *
 * @param array  $task_data Dados formatados da tarefa
 * @param string $context   Contexto (appointment, finance, message, stock)
 * @param int    $entity_id ID da entidade DPS
 */
$task_data = apply_filters( 'dps_google_tasks_before_sync', $task_data, $context, $entity_id );

/**
 * Disparado após sincronização bem-sucedida.
 *
 * @param string $task_id   ID da tarefa criada no Google Tasks
 * @param string $context   Contexto da tarefa
 * @param int    $entity_id ID da entidade DPS
 */
do_action( 'dps_google_tasks_synced', $task_id, $context, $entity_id );

/**
 * Disparado quando sincronização falha.
 *
 * @param WP_Error $error     Erro detalhado
 * @param string   $context   Contexto
 * @param int      $entity_id ID da entidade DPS
 */
do_action( 'dps_google_tasks_sync_failed', $error, $context, $entity_id );
```

---

## 6. Interface Administrativa

### 6.1. Menu e Configurações

**Localização:** desi.pet by PRObst → Google Tasks

**Abas:**

#### 6.1.1. Conexão
- Status de conexão (conectado / desconectado)
- Botão "Conectar com Google" (inicia OAuth flow)
- Informações da conta conectada (email, nome)
- Botão "Desconectar" (revoga tokens)
- Botão "Reconectar" (renova autorização)

#### 6.1.2. Configurações de Sincronização
- ✅ Sincronizar Agendamentos (checkbox)
  - Dias de antecedência para lembrete (número)
  - Lista de destino (dropdown com listas do usuário)
- ✅ Sincronizar Financeiro (checkbox)
  - Dias de antecedência para cobrança (número)
  - Lista de destino (dropdown)
- ✅ Sincronizar Mensagens do Portal (checkbox)
  - Lista de destino (dropdown)
- ✅ Sincronizar Alertas de Estoque (checkbox)
  - Lista de destino (dropdown)

#### 6.1.3. Sincronização Manual
- Botão "Sincronizar Agora" (força sincronização de pendências)
- Tabela com últimas sincronizações:
  - Data/Hora
  - Tipo (agendamento, financeiro, etc.)
  - Entidade (nome do cliente, pet, valor)
  - Status (sucesso, erro)
  - Ações (ver tarefa no Google Tasks, retentar)

#### 6.1.4. Logs
- Histórico de sincronizações (últimos 100 registros)
- Filtros: tipo, status, data
- Exportar logs (CSV)

### 6.2. Indicadores Visuais

**Na lista de agendamentos:**
- Ícone do Google Tasks (✅) ao lado de agendamentos sincronizados
- Link "Ver no Google Tasks" (abre tarefa em nova aba)

**Na lista de transações:**
- Badge "Sincronizado" em transações com tarefa criada
- Link direto para tarefa no Google Tasks

---

## 7. Segurança

### 7.1. Autenticação OAuth 2.0

**Fluxo:**
1. Plugin registrado no Google Cloud Console
2. Client ID e Client Secret armazenados em constantes (`DPS_GOOGLE_TASKS_CLIENT_ID`, `DPS_GOOGLE_TASKS_CLIENT_SECRET`)
3. **NUNCA** commitar credenciais no código
4. Tokens OAuth criptografados antes de armazenar em `wp_options`
5. Refresh token usado para renovar access token expirado

**Classe de Criptografia:**
```php
class DPS_Google_Tasks_Encryption {
    /**
     * Criptografa string usando AES-256-CBC.
     * 
     * @param string $plaintext Texto a criptografar
     * @return string Texto criptografado (base64)
     */
    public static function encrypt( $plaintext ) {
        $key = self::get_encryption_key();
        $iv = openssl_random_pseudo_bytes( openssl_cipher_iv_length( 'aes-256-cbc' ) );
        $ciphertext = openssl_encrypt( $plaintext, 'aes-256-cbc', $key, 0, $iv );
        return base64_encode( $iv . $ciphertext );
    }
    
    private static function get_encryption_key() {
        if ( defined( 'DPS_ENCRYPTION_KEY' ) ) {
            return DPS_ENCRYPTION_KEY;
        }
        // Fallback: gerar e armazenar key baseada em AUTH_KEY
        return hash( 'sha256', AUTH_KEY . 'dps_google_tasks' );
    }
}
```

### 7.2. Validações

- ✅ Nonce em todas as ações admin (`dps_google_tasks_nonce`)
- ✅ Capability `manage_options` para acesso às configurações
- ✅ Sanitização de inputs (URLs de callback, IDs de listas)
- ✅ Validação de respostas da Google Tasks API
- ✅ Rate limiting interno (máx. 1 requisição/segundo)
- ✅ Logs de erro apenas server-side (não expostos ao front-end)

### 7.3. LGPD / GDPR

**Dados enviados ao Google:**
- Nome do cliente (apenas primeiro nome, se configurado)
- Nome do pet
- Data/hora do agendamento
- Valor da transação (opcional)
- Link de volta para o DPS (não contém dados sensíveis)

**Dados NÃO enviados:**
- CPF, RG, endereço completo
- Telefone completo (apenas se admin autorizar)
- E-mail (apenas se admin autorizar)
- Histórico médico do pet

**Configurações de Privacidade:**
```php
'privacy_settings' => [
    'include_client_full_name' => false,  // Enviar apenas primeiro nome
    'include_client_phone'     => false,  // Incluir telefone na descrição
    'include_client_email'     => false,  // Incluir e-mail na descrição
    'include_financial_value'  => false,  // Incluir valor de transações
]
```

---

## 8. Requisitos Técnicos

### 8.1. Dependências do PHP

```json
{
    "require": {
        "php": ">=8.4",
        "ext-openssl": "*",
        "ext-json": "*",
        "ext-curl": "*"
    }
}
```

**Bibliotecas PHP (via Composer):**
- `google/apiclient`: Cliente oficial do Google para PHP (^2.15)
- OU implementação HTTP nativa com `wp_remote_*` (preferível para reduzir dependências)

### 8.2. APIs Externas

**Google Tasks API v1:**
- Base URL: `https://tasks.googleapis.com/tasks/v1`
- Documentação: https://developers.google.com/tasks/reference/rest
- Quota: 50,000 requisições/dia (gratuito)

**Endpoints Utilizados:**
```
GET  /users/@me/lists                 # Listar listas de tarefas
POST /users/@me/lists                 # Criar nova lista
GET  /lists/{listId}/tasks            # Listar tarefas de uma lista
POST /lists/{listId}/tasks            # Criar tarefa
PATCH /lists/{listId}/tasks/{taskId}  # Atualizar tarefa
DELETE /lists/{listId}/tasks/{taskId} # Deletar tarefa
```

### 8.3. Configuração no Google Cloud Console

**Passos:**
1. Criar projeto no Google Cloud Console
2. Ativar Google Tasks API
3. Criar credenciais OAuth 2.0 (tipo: Web application)
4. Configurar URIs de redirecionamento autorizados:
   - `https://SITE_DO_CLIENTE/wp-admin/admin.php?page=dps-google-tasks&action=oauth_callback`
5. Obter Client ID e Client Secret
6. Definir constantes em `wp-config.php`:
   ```php
   define( 'DPS_GOOGLE_TASKS_CLIENT_ID', 'seu_client_id.apps.googleusercontent.com' );
   define( 'DPS_GOOGLE_TASKS_CLIENT_SECRET', 'seu_client_secret' );
   ```

---

## 9. Estimativa de Esforço (Revisada com Google Calendar)

### 9.1. Breakdown de Tarefas

| Tarefa | Esforço | Prioridade |
|--------|---------|------------|
| **Fase 1: Infraestrutura Compartilhada** | | |
| 1.1. Criar estrutura de integração em Agenda add-on | 3h | Alta |
| 1.2. Implementar cliente HTTP para Google Calendar API | 8h | Alta |
| 1.3. Implementar cliente HTTP para Google Tasks API | 6h | Alta |
| 1.4. Implementar OAuth 2.0 compartilhado (Calendar + Tasks) | 10h | Alta |
| 1.5. Implementar criptografia de tokens | 3h | Alta |
| 1.6. Criar interface administrativa (aba "Integrações Google") | 8h | Alta |
| **Subtotal Fase 1** | **38h** | |
| | | |
| **Fase 2: Sincronização Google Calendar** | | |
| 2.1. Classe de formatação de eventos (agendamentos → eventos) | 5h | Alta |
| 2.2. Sincronização DPS → Calendar (criar/atualizar/deletar) | 8h | Alta |
| 2.3. Webhook handler Calendar → DPS (reagendamento) | 6h | Alta |
| 2.4. Sistema de cores por tipo de serviço | 2h | Média |
| 2.5. Suporte a eventos recorrentes (assinaturas) | 4h | Média |
| 2.6. Criar hook `dps_base_appointment_status_changed` no Base | 2h | Alta |
| 2.7. Indicadores visuais na lista de agendamentos | 3h | Média |
| **Subtotal Fase 2** | **30h** | |
| | | |
| **Fase 3: Sincronização Google Tasks** | | |
| 3.1. Classe de formatação de tarefas (follow-ups, cobranças) | 4h | Alta |
| 3.2. Sincronização de follow-ups pós-atendimento | 4h | Alta |
| 3.3. Sincronização de transações financeiras pendentes | 5h | Alta |
| 3.4. Atualização de status (pago → task concluída) | 2h | Alta |
| 3.5. Criar hook `dps_finance_transaction_created` no Finance | 2h | Média |
| 3.6. Indicadores visuais na lista de transações | 2h | Baixa |
| **Subtotal Fase 3** | **19h** | |
| | | |
| **Fase 4: Funcionalidades Extras** | | |
| 4.1. Sincronização de mensagens do Portal (Tasks) | 4h | Média |
| 4.2. Sincronização de alertas de estoque (Tasks) | 3h | Baixa |
| 4.3. Sincronização manual (botão "Sincronizar Agora") | 4h | Média |
| 4.4. Logs de sincronização (Calendar + Tasks) | 5h | Média |
| 4.5. Resolver conflitos de sincronização bidirecional | 6h | Alta |
| **Subtotal Fase 4** | **22h** | |
| | | |
| **Fase 5: Testes e Documentação** | | |
| 5.1. Testes unitários (PHPUnit) | 10h | Média |
| 5.2. Testes de integração com APIs reais (Calendar + Tasks) | 8h | Alta |
| 5.3. Testes de sincronização bidirecional (webhooks) | 6h | Alta |
| 5.4. Documentação técnica (README, ANALYSIS.md) | 5h | Alta |
| 5.5. Guia de configuração para usuários finais | 4h | Alta |
| **Subtotal Fase 5** | **33h** | |
| | | |
| **TOTAL GERAL** | **142h** | |
| **(~18 dias úteis)** | | |

### 9.2. Roadmap Sugerido (Revisado)

**v1.0.0 - MVP Calendar** - 68h (~8.5 dias)
- OAuth 2.0 compartilhado funcionando
- Sincronização DPS → Google Calendar (criar/atualizar/deletar eventos)
- Webhook Calendar → DPS (reagendamento)
- Interface administrativa básica
- Documentação de instalação

**v1.1.0 - Tasks Administrativas** - +19h (~2.5 dias)
- Sincronização de follow-ups pós-atendimento
- Sincronização de transações financeiras pendentes
- Integração Tasks com Finance add-on

**v1.2.0 - Features Completas** - +22h (~3 dias)
- Mensagens do Portal sincronizadas com Tasks
- Alertas de Estoque sincronizados com Tasks
- Sincronização manual (botão admin)
- Logs detalhados de todas as operações
- Resolução de conflitos bidirecional

**v1.3.0 - Estabilização** - +33h (~4 dias)
- Cobertura completa de testes unitários
- Testes de integração com APIs reais
- Testes de sincronização bidirecional
- Documentação completa (técnica + usuário final)
- Otimizações de performance

### 9.3. Comparação: Esforço Original vs Revisado

| Aspecto | Original (só Tasks) | Revisado (Calendar + Tasks) | Diferença |
|---------|---------------------|----------------------------|-----------|
| **Infraestrutura** | 25h | 38h | +13h |
| **Sincronização principal** | 17h | 30h (Calendar) + 19h (Tasks) | +32h |
| **Features extras** | 14h | 22h | +8h |
| **Testes e docs** | 21h | 33h | +12h |
| **TOTAL** | **87h (~11 dias)** | **142h (~18 dias)** | **+55h (+7 dias)** |

**Justificativa do aumento:**
- ✅ Sincronização bidirecional (Calendar → DPS) adiciona complexidade (webhooks, conflitos)
- ✅ Dois clientes HTTP (Calendar + Tasks) em vez de um
- ✅ Eventos recorrentes (assinaturas) requerem lógica RRULE
- ✅ Sistema de cores por tipo de serviço
- ✅ Testes de integração mais complexos (2 APIs)
- ✅ **PORÉM**: Benefício é MUITO maior - visualização completa da operação

**ROI ainda é POSITIVO**: Mesmo com +7 dias, a integração dupla oferece muito mais valor (Calendar visual + Tasks administrativas)

---

## 10. Alternativas Consideradas

### 10.1. Microsoft To Do

**Prós:**
- Integração com Outlook e Microsoft 365
- API similar à do Google Tasks

**Contras:**
- Menos popular no Brasil
- Requer conta Microsoft (menos pessoas têm)
- API menos documentada

**Decisão:** Não priorizar no MVP, considerar para v2.0

### 10.2. Todoist

**Prós:**
- Aplicativo dedicado com muitos recursos
- API robusta com webhooks

**Contras:**
- Requer assinatura paga para features avançadas
- Menor integração com ecossistema Google/Microsoft

**Decisão:** Não priorizar

### 10.3. Solução Interna (Custom Task Manager)

**Prós:**
- Controle total sobre funcionalidades
- Sem dependência de APIs externas
- Integração perfeita com DPS

**Contras:**
- Esforço de desenvolvimento muito maior (200+ horas)
- Necessidade de desenvolver app mobile
- Competir com apps consolidados (Google Tasks, Microsoft To Do)

**Decisão:** Inviável para escopo atual

---

## 11. Riscos e Mitigações

| Risco | Impacto | Probabilidade | Mitigação |
|-------|---------|---------------|-----------|
| **Mudanças na API do Google** | Alto | Baixa | Monitorar changelog oficial, implementar versionamento |
| **Revogação de tokens** | Médio | Média | Detectar erros 401, notificar admin, facilitar reconexão |
| **Limite de quota atingido** | Alto | Baixa | Implementar rate limiting, batch operations, cache local |
| **Dados sensíveis vazados** | Alto | Baixa | Criptografia de tokens, configurações de privacidade granulares |
| **Sincronização inconsistente** | Médio | Média | Logs detalhados, sincronização manual, retry automático |
| **Usuário desconecta conta Google** | Baixo | Alta | Graceful degradation, notificação clara, não quebrar DPS |

---

## 12. Casos de Uso Detalhados

### 12.1. Caso de Uso 1: Groomer Verifica Agenda do Dia

**Ator:** Maria (Groomer)

**Cenário:**
1. Maria acorda às 7h e abre o app Google Tasks no celular
2. Vê lista "Pet Shop - Agendamentos" com 4 tarefas para hoje:
   - 09:00 - Rex (João Silva) - Banho
   - 11:00 - Mel (Maria Santos) - Tosa
   - 14:00 - Thor (Carlos Lima) - Banho e Tosa
   - 16:00 - Princesa (Ana Souza) - Banho
3. Marca primeira tarefa como concluída após atender Rex
4. Google Tasks envia notificação 15min antes do próximo agendamento
5. No fim do dia, todas as tarefas estão concluídas

**Benefício:** Maria gerencia agenda sem precisar abrir o sistema DPS constantemente

### 12.2. Caso de Uso 2: Administrativo Acompanha Cobranças

**Ator:** José (Administrativo)

**Cenário:**
1. José abre Google Tasks no desktop (integrado ao Gmail)
2. Vê lista "Pet Shop - Financeiro" com 3 cobranças pendentes:
   - João Silva - R$ 150,00 (vence amanhã)
   - Maria Santos - R$ 200,00 (vence em 3 dias)
   - Carlos Lima - R$ 120,00 (vence em 5 dias)
3. Clica na tarefa de João Silva
4. Descrição contém telefone e link para transação no DPS
5. José envia lembrete via WhatsApp usando template do DPS
6. João paga via PIX
7. Sistema DPS marca tarefa no Google Tasks como concluída automaticamente
8. José vê visualmente que cobrança foi resolvida

**Benefício:** José não perde cobranças de vista, acompanha status em tempo real

### 12.3. Caso de Uso 3: Dono do Pet Shop Gerencia Follow-ups

**Ator:** Ricardo (Proprietário)

**Cenário:**
1. Ricardo usa Google Tasks há anos para gerenciar tarefas pessoais
2. Agora também vê tarefas do pet shop nas mesmas listas
3. Recebe notificação: "Follow-up: Rex (João Silva) - Pós-Atendimento"
4. Liga para João 2 dias após o banho
5. João relata que Rex ficou ótimo, está muito satisfeito
6. Ricardo marca tarefa como concluída
7. Adiciona comentário: "Cliente satisfeito, possível indicação"

**Benefício:** Ricardo centraliza gestão pessoal e profissional em uma ferramenta que já domina

---

## 13. Métricas de Sucesso

### 13.1. KPIs Técnicos

| Métrica | Meta | Como Medir |
|---------|------|------------|
| Taxa de sincronização bem-sucedida | > 99% | Logs de sincronização |
| Tempo médio de sincronização | < 2s | Timestamp antes/depois de API call |
| Uptime da conexão OAuth | > 99.5% | Monitorar erros 401 (token inválido) |
| Cobertura de testes | > 80% | PHPUnit coverage report |

### 13.2. KPIs de Negócio

| Métrica | Meta | Como Medir |
|---------|------|------------|
| Adoção pelos usuários | > 60% dos admins conectam conta Google | Option `dps_google_tasks_settings['enabled']` |
| Tarefas sincronizadas/dia | > 20 | Contagem em logs |
| Redução de agendamentos esquecidos | -30% | Comparar no-shows antes/depois |
| Satisfação do usuário | > 4.5/5 | Survey após 30 dias de uso |

---

## 14. Considerações de Implementação

### 14.1. Compatibilidade com Add-ons Existentes

**Add-ons Impactados:**
- ✅ `desi-pet-shower-agenda` - Precisa expor hook `dps_base_appointment_status_changed`
- ✅ `desi-pet-shower-finance` - Precisa expor hook `dps_finance_transaction_created`
- ⚠️  `desi-pet-shower-client-portal` - Opcional: hook para mensagens recebidas
- ⚠️  `desi-pet-shower-stock` - Opcional: hook para alertas de estoque

**Mudanças Necessárias no Core:**
```php
// Em desi-pet-shower-base/includes/class-dps-base-frontend.php

// Adicionar hook após mudar status de agendamento
public function update_appointment_status() {
    // ... código existente ...
    
    $old_status = get_post_meta( $appointment_id, 'appointment_status', true );
    update_post_meta( $appointment_id, 'appointment_status', $new_status );
    
    // Novo hook para add-ons reagirem a mudança de status
    do_action( 'dps_base_appointment_status_changed', $appointment_id, $new_status, $old_status );
}
```

### 14.2. Estratégia de Rollout

**Fase Beta (1 mês):**
1. Implementar v1.0.0 (MVP) com apenas agendamentos
2. Instalar em 3-5 pet shops piloto
3. Coletar feedback semanal via Google Forms
4. Ajustar bugs e melhorias UX

**Fase v1.1.0 (2 semanas):**
1. Adicionar sincronização financeira
2. Expandir para 10 pet shops
3. Monitorar métricas de performance

**Fase v1.2.0 (lançamento geral):**
1. Features completas (mensagens, estoque, logs)
2. Documentação final
3. Disponibilizar para todos os clientes DPS

### 14.3. Suporte e Manutenção

**Documentação Necessária:**
- README.md do add-on (português)
- Guia de instalação e configuração (passo a passo com screenshots)
- FAQ de troubleshooting
- Vídeo tutorial (5-10min)

**Suporte:**
- Canal dedicado no suporte DPS
- Checklist de diagnóstico para problemas comuns:
  - Token expirado
  - Quota excedida
  - Conectividade com Google
  - Permissões OAuth revogadas

---

## 15. Conclusão

### 15.1. Recomendação

✅ **RECOMENDA-SE IMPLEMENTAR** a integração com Google Tasks como novo add-on do DPS.

**Justificativa:**
1. **Viabilidade Técnica:** API bem documentada, OAuth 2.0 seguro, sem custos adicionais
2. **Benefício Real:** Melhora organização da equipe, reduz tarefas esquecidas, centraliza gestão
3. **Baixo Risco:** Sincronização unidirecional não afeta dados do DPS, falhas degradam gracefully
4. **ROI Positivo:** Esforço de 87h (~11 dias) com benefício contínuo para todos os clientes
5. **Escalabilidade:** Base sólida para futuras integrações (Microsoft To Do, Trello, etc.)

### 15.2. Priorização

**ALTA PRIORIDADE (Implementar no Q1 2026):**
- Fase 1: Infraestrutura e autenticação
- Fase 2: Sincronização de agendamentos

**MÉDIA PRIORIDADE (Implementar no Q2 2026):**
- Fase 3: Sincronização financeira
- Fase 4: Mensagens do Portal

**BAIXA PRIORIDADE (Avaliar demanda):**
- Alertas de estoque
- Sincronização bidirecional (marcar tarefa no Google → atualiza DPS)
- Integração com Microsoft To Do
- App mobile dedicado

### 15.3. Próximos Passos

1. **Aprovar proposta** com stakeholders (proprietário do DPS, equipe de desenvolvimento)
2. **Criar projeto no Google Cloud Console** e obter credenciais OAuth
3. **Prototipar MVP** (Fase 1 + 2) em ambiente de desenvolvimento
4. **Testar com beta testers** (3-5 pet shops)
5. **Iterar baseado em feedback** antes do lançamento geral
6. **Documentar e lançar** para todos os clientes DPS

---

## Anexos

### A. Exemplo de Tarefa Sincronizada (JSON)

```json
{
  "kind": "tasks#task",
  "id": "MTY4NjE2NzY4NzAwMDAwMDA",
  "title": "🐾 Agendamento: Rex (João Silva) - 15/12/2024 14:00",
  "notes": "Cliente: João Silva (11) 98765-4321\nPet: Rex (Labrador, Grande)\nServiços: Banho, Tosa\nGroomer: Maria Santos\n\nLink: https://petshop.com.br/admin/agendamento/123",
  "status": "needsAction",
  "due": "2024-12-15T14:00:00.000Z",
  "updated": "2024-12-14T10:30:00.000Z",
  "selfLink": "https://www.googleapis.com/tasks/v1/lists/MTY4NjE2NzY4NzAwMDAwMDA/tasks/MTY4NjE2NzY4NzAwMDAwMDA"
}
```

### B. Fluxograma de Autenticação OAuth 2.0

```
[Admin clica "Conectar"]
        ↓
[Redireciona para Google OAuth Consent]
        ↓
[Usuário autoriza acesso]
        ↓
[Google redireciona com code]
        ↓
[Plugin troca code por tokens]
        ↓
[Criptografa e armazena tokens]
        ↓
[Busca listas existentes do usuário]
        ↓
[Cria listas "Pet Shop - *" se não existem]
        ↓
[Salva IDs das listas]
        ↓
[Habilita sincronização]
        ↓
[Exibe "Conectado com sucesso"]
```

### C. Exemplo de Configuração em wp-config.php

```php
/**
 * Google Tasks API - Credenciais OAuth 2.0
 * 
 * Obtenha estas credenciais no Google Cloud Console:
 * https://console.cloud.google.com/apis/credentials
 */
define( 'DPS_GOOGLE_TASKS_CLIENT_ID', '123456789-abcdef.apps.googleusercontent.com' );
define( 'DPS_GOOGLE_TASKS_CLIENT_SECRET', 'GOCSPX-abcdefghijklmnop' );

/**
 * Chave de criptografia para tokens OAuth
 * Gere uma chave aleatória segura: https://api.wordpress.org/secret-key/1.1/salt/
 */
define( 'DPS_ENCRYPTION_KEY', 'sua-chave-aleatoria-de-64-caracteres-aqui' );
```

### D. Referências

- Google Tasks API Documentation: https://developers.google.com/tasks
- OAuth 2.0 for Web Server Applications: https://developers.google.com/identity/protocols/oauth2/web-server
- WordPress HTTP API: https://developer.wordpress.org/plugins/http-api/
- WordPress REST API: https://developer.wordpress.org/rest-api/

---

**Documento criado por:** Agente Copilot  
**Revisão necessária por:** Equipe de desenvolvimento DPS  
**Status:** Aguardando aprovação  
**Última atualização:** 2026-01-19
