# Integrações Google - Fases 1, 2, 3 e 4 (COMPLETO)

**Status Fase 1:** ✅ Concluída  
**Status Fase 2:** ✅ Concluída  
**Status Fase 3:** ✅ Concluída  
**Status Fase 4:** ✅ Concluída  
**Data:** 2026-01-19  
**Versão:** 2.0.0-completo  

## O que foi implementado

### Estrutura de Arquivos

```
desi-pet-shower-agenda/includes/integrations/
├── class-dps-google-auth.php                    ✅ OAuth 2.0 Handler (Fase 1)
├── class-dps-google-integrations-settings.php   ✅ Interface Administrativa (Fase 1+2+3+4)
├── class-dps-google-calendar-client.php         ✅ Cliente Calendar API (Fase 2)
├── class-dps-google-calendar-sync.php           ✅ Sincronização Calendar (Fase 2)
├── class-dps-google-calendar-webhook.php        ✅ Webhook Handler (Fase 3)
├── class-dps-google-tasks-client.php            ✅ Cliente Tasks API (Fase 4)
├── class-dps-google-tasks-sync.php              ✅ Sincronização Tasks (Fase 4)
└── README.md                                    ✅ Esta documentação
```

## Fase 1: Infraestrutura OAuth 2.0 (Concluída)

### Funcionalidades Implementadas

#### 1. Autenticação OAuth 2.0 (`class-dps-google-auth.php`)

**Responsabilidades:**
- Gerar URL de autorização OAuth 2.0
- Trocar authorization code por access token e refresh token
- Renovar access token automaticamente quando expirado
- Armazenar tokens de forma segura (criptografia AES-256-CBC)
- Verificar status de conexão
- Desconectar e revogar tokens

**Métodos Públicos:**
- `get_auth_url()` - Gera URL para autorização
- `exchange_code_for_tokens($code)` - Troca code por tokens
- `refresh_access_token()` - Renova access token
- `get_access_token()` - Obtém token válido (renova se necessário)
- `is_connected()` - Verifica se está conectado
- `disconnect()` - Desconecta e remove tokens

**Segurança:**
- ✅ Tokens criptografados com AES-256-CBC antes de armazenar
- ✅ Chave de criptografia baseada em `DPS_ENCRYPTION_KEY` ou `AUTH_KEY`
- ✅ Verificação de nonce em fluxo OAuth
- ✅ Renovação automática de tokens expirados

#### 2. Interface Administrativa (`class-dps-google-integrations-settings.php`)

**Responsabilidades:**
- Adicionar aba "Integrações Google" no hub da Agenda
- Exibir status de conexão (conectado/desconectado)
- Processar fluxo OAuth (callback)
- Exibir instruções de configuração inicial
- Interface para futuras configurações de sincronização

**Fluxo de Conexão:**
1. Admin acessa Agenda → Integrações Google
2. Clica em "Conectar com Google"
3. É redirecionado para Google OAuth consent screen
4. Autoriza acesso a Calendar + Tasks
5. Google redireciona de volta com authorization code
6. Plugin troca code por tokens e armazena de forma criptografada
7. Exibe mensagem de sucesso

**Capabilities Necessárias:**
- `manage_options` - Para acessar configurações

### Configuração Necessária

#### 1. Criar Projeto no Google Cloud Console

1. Acesse https://console.cloud.google.com/
2. Crie novo projeto ou selecione existente
3. Ative as APIs:
   - Google Calendar API
   - Google Tasks API

#### 2. Configurar OAuth 2.0

1. Vá em "Credentials" → "Create Credentials" → "OAuth 2.0 Client ID"
2. Tipo: "Web application"
3. Adicione Authorized redirect URI:
   ```
   https://SEU_SITE.com.br/wp-admin/admin.php?page=dps-agenda-hub&tab=google-integrations&action=oauth_callback
   ```
4. Copie Client ID e Client Secret

#### 3. Adicionar Credenciais no wp-config.php

```php
// Google OAuth 2.0 Credentials
define( 'DPS_GOOGLE_CLIENT_ID', 'seu_client_id_aqui.apps.googleusercontent.com' );
define( 'DPS_GOOGLE_CLIENT_SECRET', 'seu_client_secret_aqui' );

// (Opcional) Chave de criptografia customizada
define( 'DPS_ENCRYPTION_KEY', 'sua_chave_aleatoria_64_caracteres' );
```

**IMPORTANTE:** Nunca commitar credenciais no código!

## Fase 2: Sincronização Google Calendar (Concluída)

### Funcionalidades Implementadas

#### 1. Cliente HTTP Calendar API (`class-dps-google-calendar-client.php`)

**Responsabilidades:**
- Comunicação com Google Calendar API v3
- Criar eventos no calendário
- Atualizar eventos existentes
- Deletar eventos
- Obter eventos

**Métodos Públicos:**
- `create_event($event_data)` - Cria evento no Calendar
- `update_event($event_id, $event_data)` - Atualiza evento
- `delete_event($event_id)` - Deleta evento
- `get_event($event_id)` - Obtém dados do evento
- `format_datetime($date, $time, $timezone)` - Formata data/hora para RFC3339

**Cores Disponíveis:**
- Azul (`blue`): Serviço padrão
- Azul claro (`lightblue`): Variação
- Roxo (`purple`): Múltiplos serviços
- Verde (`green`): Finalizado/Pago
- Amarelo (`yellow`): Aviso
- Vermelho (`red`): Cancelado
- Cinza (`gray`): Neutro

#### 2. Sincronização Automática (`class-dps-google-calendar-sync.php`)

**Responsabilidades:**
- Sincronização unidirecional (DPS → Google Calendar)
- Formatar agendamentos como eventos
- Gerenciar ciclo de vida dos eventos
- Log de erros de sincronização

**Hooks Utilizados:**
- `dps_base_after_save_appointment` - Sincroniza após salvar agendamento
- `before_delete_post` - Deleta evento ao deletar agendamento
- `untrashed_post` - Recria evento ao restaurar da lixeira

**Fluxo de Sincronização:**
```
1. Agendamento salvo no DPS
   ↓
2. Hook dps_base_after_save_appointment disparado
   ↓
3. Verifica se sincronização está habilitada
   ↓
4. Formata agendamento como evento Calendar
   ↓
5. Cria/Atualiza evento via Calendar API
   ↓
6. Armazena event_id em _google_calendar_event_id
   ↓
7. Marca _google_calendar_synced_at com timestamp
```

**Metadados Adicionados:**
- `_google_calendar_event_id` - ID do evento no Google Calendar
- `_google_calendar_synced_at` - Timestamp da última sincronização
- `_google_calendar_last_error` - Último erro de sincronização (se houver)

**Formato do Evento:**
```
Título: 🐾 [Serviços] - [Pet] ([Cliente])
Exemplo: 🐾 Banho, Tosa - Rex (João Silva)

Descrição:
  Cliente: João Silva
  Pet: Rex (Labrador, Grande)
  Serviços: Banho, Tosa
  Profissional: Maria Santos
  
  🔗 Ver no DPS: [link admin]

Início: [Data/hora do agendamento]
Fim: [Data/hora + duração estimada]
Cor: Baseada no status (pendente=azul, finalizado=verde, etc)
Lembretes: 1h antes + 15min antes
```

### 3. Interface Atualizada

**Checkbox de Configuração:**
- ✅ "Sincronizar agendamentos com Google Calendar" (habilitado)
- Botão "Salvar Configurações"
- Mensagem de status: "Fase 2 concluída"

## Fase 3: Sincronização Bidirecional (Concluída)

### Funcionalidades Implementadas

#### 1. Webhook Handler (`class-dps-google-calendar-webhook.php`)

**Responsabilidades:**
- Registrar webhook no Google Calendar (watch channel)
- Receber notificações push quando eventos mudam
- Processar mudanças e atualizar agendamentos no DPS
- Renovar webhook automaticamente (7 dias)
- Parar webhook ao desconectar

**Fluxo do Webhook:**
```
1. Conectar ao Google → Registra webhook
   ↓
2. Google Calendar: Admin reagenda evento
   ↓
3. Google envia notificação push
   ↓
4. Endpoint REST: /wp-json/dps/v1/google-calendar-webhook
   ↓
5. Valida token secreto
   ↓
6. Agenda processamento em background
   ↓
7. Busca eventos atualizados (updatedMin)
   ↓
8. Identifica evento via extendedProperties.dps_appointment_id
   ↓
9. Atualiza appointment_date e appointment_time no DPS
   ↓
10. Marca _dps_syncing_from_google (previne loop)
```

**Métodos Principais:**
- `register_webhook()` - Registra webhook no Google
- `stop_webhook()` - Para webhook
- `renew_webhook()` - Renova webhook (cron 5 dias antes)
- `handle_webhook_notification()` - Endpoint REST
- `process_calendar_changes()` - Processa mudanças
- `fetch_updated_events()` - Busca eventos atualizados
- `sync_event_to_dps()` - Atualiza agendamento

**Segurança:**
- Token secreto único por webhook
- Validação via header `x-goog-channel-token`
- Ignora notificações de sincronização (apenas mudanças reais)
- Previne loops infinitos (_dps_syncing_from_google)

**Metadados Adicionados:**
- `_google_calendar_synced_from_calendar_at` - Timestamp da sincronização do Calendar
- `_google_calendar_deleted` - Flag se evento foi deletado no Calendar

**Options WordPress:**
- `dps_google_calendar_webhook` - Dados do webhook (id, resource_id, token, expiration)
- `dps_google_calendar_last_sync` - Timestamp da última sincronização

**Cron Jobs:**
- `dps_google_webhook_renew` - Renovação automática (5 dias antes de expirar)
- `dps_google_calendar_process_changes` - Processamento de mudanças

#### 2. Hooks e Actions Adicionados

**Actions Disparadas:**
- `dps_google_auth_connected` - Após conectar (registra webhook)
- `dps_google_auth_disconnected` - Antes de desconectar (para webhook)
- `dps_google_calendar_synced_from_calendar` - Após sincronizar do Calendar para DPS
- `dps_google_calendar_webhook_error` - Após erro no webhook

**Actions Consumidas:**
- `dps_google_webhook_renew` - Cron para renovar webhook
- `dps_google_calendar_process_changes` - Processar mudanças em background

#### 3. Interface Atualizada

**Status do Webhook:**
- Exibe "✅ Sincronização bidirecional ativa (Calendar ⇄ DPS)"
- Mostra data de renovação automática
- Mensagem: "Fase 3 concluída"

## O que NÃO foi implementado (Próximas Fases)

❌ Sincronização com Google Tasks - Fase 4  
❌ Interface de logs de sincronização - Fase 5  

## Como Testar

### Fase 1: Autenticação OAuth

#### 1. Verificar Interface Administrativa

1. Acesse WordPress Admin
2. Vá em `desi.pet by PRObst → Agenda`
3. Clique na aba **Integrações Google** 🔗
4. Deve exibir:
   - Status: "Não Conectado" (⚠️)
   - Instruções de configuração inicial

#### 2. Configurar Credenciais

1. Adicione credenciais no `wp-config.php` (veja seção acima)
2. Recarregue a página
3. Deve exibir:
   - Botão "Conectar com Google" (azul)

#### 3. Testar Fluxo OAuth

1. Clique em "Conectar com Google"
2. Autorize acesso na tela do Google
3. Deve ser redirecionado de volta
4. Status deve mudar para "Conectado" (✅)
5. Deve exibir:
   - Data/hora de conexão
   - Botão "Desconectar" (vermelho)
   - Checkbox "Sincronizar agendamentos com Google Calendar"

### Fase 2: Sincronização Google Calendar

#### 1. Habilitar Sincronização

1. Conecte-se ao Google (veja Fase 1)
2. Marque checkbox "Sincronizar agendamentos com Google Calendar"
3. Clique em "Salvar Configurações"
4. Deve exibir mensagem de sucesso

#### 2. Testar Criação de Evento

1. Acesse `desi.pet by PRObst → Painel`
2. Crie um novo agendamento:
   - Selecione cliente e pet
   - Escolha data e hora
   - Selecione serviços
   - Salve o agendamento
3. Aguarde alguns segundos
4. Abra Google Calendar em outra aba
5. Deve ver novo evento criado:
   - Título: "🐾 [Serviços] - [Pet] ([Cliente])"
   - Data/hora corretas
   - Descrição com detalhes do agendamento
   - Cor azul (status pendente)

#### 3. Testar Atualização de Evento

1. Edite o agendamento criado
2. Altere data/hora ou serviços
3. Salve
4. Recarregue Google Calendar
5. Evento deve estar atualizado

#### 4. Testar Deleção de Evento

1. Delete o agendamento no DPS
2. Recarregue Google Calendar
3. Evento deve ter sido removido

#### 5. Verificar Metadados

1. No editor de agendamento, veja o código fonte
2. Deve ter metadados:
   ```
   _google_calendar_event_id: evento123abc
   _google_calendar_synced_at: 1234567890
   ```

### Fase 3: Sincronização Bidirecional (Calendar → DPS)

#### 1. Verificar Status do Webhook

1. Após conectar, verifique interface
2. Deve exibir:
   - "✅ Sincronização bidirecional ativa (Calendar ⇄ DPS)"
   - Data de renovação automática

#### 2. Testar Reagendamento no Calendar

1. Crie agendamento no DPS:
   - Cliente: João Silva
   - Pet: Rex
   - Data: Amanhã às 14:00
2. Aguarde evento aparecer no Google Calendar
3. No Google Calendar, **arraste o evento** para outro dia/horário
   - Ex: Mude de 14:00 para 16:00
4. Aguarde ~30 segundos
5. Recarregue página do DPS
6. ✅ Agendamento deve estar atualizado com novo horário!

#### 3. Testar Deleção no Calendar

1. Crie agendamento no DPS
2. Aguarde evento aparecer no Calendar
3. Delete o evento no Google Calendar
4. Aguarde ~30 segundos
5. Verifique agendamento no DPS
6. Deve ter metadado `_google_calendar_deleted = true`
7. (Evento não é deletado do DPS, apenas marcado)

#### 4. Verificar Webhook Registrado

1. Verifique option no banco:
   ```sql
   SELECT * FROM wp_options WHERE option_name = 'dps_google_calendar_webhook';
   ```
2. Deve ter:
   - `id`: dps-calendar-{uuid}
   - `resource_id`: ID do Google
   - `token`: Token secreto
   - `expiration`: Timestamp em milissegundos

#### 5. Testar Renovação Automática

1. Webhook renova automaticamente 5 dias antes de expirar
2. Verifique cron agendado:
   ```php
   wp_next_scheduled('dps_google_webhook_renew');
   ```
3. Deve retornar timestamp futuro

#### 6. Verificar Previne Loop Infinito

1. Reagende no Calendar
2. DPS recebe notificação
3. DPS atualiza agendamento
4. Marca `_dps_syncing_from_google = true`
5. Hook `dps_base_after_save_appointment` dispara
6. Sync verifica flag e ignora (previne enviar de volta para Calendar)
7. Flag é removida após sync

### Fase 1+2+3: Testar Desconexão

1. Clique em "Desconectar"
2. Confirme no alerta
3. Webhook é parado no Google
4. Cron de renovação é limpo
5. Status volta para "Não Conectado"
4. Criar novo agendamento NÃO deve sincronizar

### 4. Testar Desconexão

1. Clique em "Desconectar"
2. Confirme no alerta
3. Status deve voltar para "Não Conectado"

## Segurança

### Proteções Implementadas

- ✅ **Nonce verification** em todas as ações
- ✅ **Capability check** (`manage_options`)
- ✅ **Criptografia AES-256** de tokens
- ✅ **Escape de output** (`esc_html`, `esc_url`, `esc_attr`)
- ✅ **Sanitização de input** (`sanitize_text_field`)
- ✅ **Confirmação** antes de desconectar

### Dados Armazenados

Opção: `dps_google_integrations_settings`

```php
[
    'access_token'     => 'ENCRYPTED_TOKEN',
    'refresh_token'    => 'ENCRYPTED_TOKEN',
    'token_expires_at' => 1234567890,
    'connected_at'     => 1234567890,
]
```

## Impacto no Sistema Existente

✅ **ZERO IMPACTO** - Código é completamente isolado:
- Novas classes em diretório separado (`/integrations/`)
- Carregamento condicional (apenas se OpenSSL disponível)
- Apenas adiciona nova aba no hub da Agenda
- Não modifica nenhuma funcionalidade existente
- Não adiciona queries em páginas existentes

## Próximos Passos

✅ **Todas as 4 fases foram implementadas!**

Integração completa com Google Workspace (Calendar + Tasks):
- ✅ Fase 1: Infraestrutura OAuth 2.0
- ✅ Fase 2: Sincronização Google Calendar (DPS → Calendar)
- ✅ Fase 3: Sincronização bidirecional (Calendar ⇄ DPS)
- ✅ Fase 4: Google Tasks (tarefas administrativas)

**Documentação para usuários finais:**
- Guia completo passo a passo: `docs/implementation/GOOGLE_WORKSPACE_USER_GUIDE.md`
- Análise técnica: `docs/analysis/GOOGLE_TASKS_INTEGRATION_ANALYSIS.md`
- Resumo executivo: `docs/analysis/GOOGLE_TASKS_INTEGRATION_SUMMARY.md`

## Fase 4: Google Tasks (Concluída)

### Funcionalidades Implementadas

#### 1. Cliente Google Tasks API (`class-dps-google-tasks-client.php`)

**Responsabilidades:**
- Criar tarefas no Google Tasks
- Atualizar tarefas existentes
- Deletar tarefas
- Obter detalhes de tarefa
- Formatar datas (RFC 3339)

**Métodos Públicos:**
- `create_task($task_list_id, $task_data)` - Cria tarefa
- `update_task($task_list_id, $task_id, $task_data)` - Atualiza tarefa
- `delete_task($task_list_id, $task_id)` - Deleta tarefa
- `get_task($task_list_id, $task_id)` - Obtém tarefa
- `format_due_date($date)` - Formata data para Google

**Endpoint API:**
- Base URL: `https://www.googleapis.com/tasks/v1`

#### 2. Sincronização Automática (`class-dps-google-tasks-sync.php`)

**Responsabilidades:**
- Criar tarefas de follow-up após agendamento finalizado
- Criar tarefas de cobrança para pagamentos pendentes
- Criar tarefas para mensagens do portal do cliente
- Atualizar tarefas quando status mudar

**Hooks Consumidos:**
- `dps_appointment_status_changed` - Follow-ups pós-atendimento
- `dps_finance_charge_created` - Cobranças pendentes
- `dps_finance_charge_updated` - Atualiza status da tarefa
- `dps_client_message_received` - Mensagens do portal

**Tipos de Tarefas Criadas:**

##### a) Follow-up pós-atendimento
**Quando:** Agendamento mudou para status "finalizado"
**Título:** `📞 Follow-up: Rex - Banho, Tosa`
**Vencimento:** 2 dias após atendimento
**Descrição:**
```
Cliente: João Silva
Pet: Rex
Serviços: Banho, Tosa

✅ Atendimento finalizado - fazer contato para avaliar satisfação e agendar retorno.

🔗 Ver agendamento no DPS: [link]
```

##### b) Cobrança pendente
**Quando:** Nova transação pendente criada
**Título:** `💰 Cobrança: João Silva - R$ 150,00`
**Vencimento:** 1 dia antes da data de vencimento
**Descrição:**
```
Cliente: João Silva
Valor: R$ 150,00
Vencimento: 25/01/2026
Descrição: Pagamento de serviços

⚠️ Cobrança pendente - entrar em contato para solicitar pagamento.

🔗 Ver agendamento no DPS: [link]
```
**Atualização automática:** Quando transação é paga, a tarefa é marcada como "completed"

##### c) Mensagem do portal
**Quando:** Cliente envia mensagem pelo portal
**Título:** `💬 Responder: João Silva - Solicitação`
**Vencimento:** 1 dia após recebimento
**Descrição:**
```
Cliente: João Silva
Assunto: Dúvida sobre horários

Mensagem:
Olá, gostaria de saber se vocês atendem aos sábados...

📱 Responder no Portal: [link]
```

**Actions Disparadas:**
- `dps_google_task_followup_created` - Após criar follow-up
- `dps_google_task_payment_created` - Após criar tarefa de cobrança
- `dps_google_task_payment_completed` - Quando pagamento é feito
- `dps_google_task_message_created` - Após criar tarefa de mensagem
- `dps_google_tasks_sync_error` - Após erro de sincronização

**Filters Disponíveis:**
- `dps_google_tasks_followup_data` - Modificar dados da tarefa de follow-up
- `dps_google_tasks_payment_data` - Modificar dados da tarefa de cobrança
- `dps_google_tasks_message_data` - Modificar dados da tarefa de mensagem

**Metadados Armazenados:**
- `_google_task_followup_id` - ID da tarefa de follow-up no Google
- `_google_task_followup_created_at` - Data/hora de criação
- `_google_task_followup_error` - Log de erro (se houver)
- `_google_task_payment_id_{charge_id}` - ID da tarefa de cobrança
- `_google_task_payment_created_at_{charge_id}` - Data/hora de criação
- `_google_task_payment_completed_at_{charge_id}` - Data/hora de conclusão
- `_google_task_payment_error_{charge_id}` - Log de erro (se houver)

#### 3. Interface Administrativa Atualizada

**Checkbox habilitado:**
- ✅ "Sincronizar tarefas administrativas com Google Tasks"
- Descrição: "Cria tarefas no Google Tasks para follow-ups pós-atendimento, cobranças pendentes e mensagens do portal."

**Mensagem de Status:**
```
✅ Fase 4 concluída: Integração completa com Google Calendar + Google Tasks implementada!
• Sincronização bidirecional de agendamentos (Calendar ⇄ DPS)
• Tarefas administrativas automáticas (follow-ups, cobranças, mensagens)
```

### Fluxo Completo de Sincronização (Fase 4)

```
DPS: Agendamento finalizado
  ↓
Hook: dps_appointment_status_changed($appt_id, $old, 'finalizado', $data)
  ↓
DPS_Google_Tasks_Sync::maybe_create_followup_task()
  ├─ Verifica se sync_tasks habilitado
  ├─ Verifica se já tem task criada (evita duplicação)
  ├─ Formata dados da tarefa
  └─ Chama DPS_Google_Tasks_Client::create_task()
  ↓
API: POST https://www.googleapis.com/tasks/v1/lists/@default/tasks
  ↓
Response: { "id": "abc123", "title": "...", ... }
  ↓
Meta: update_post_meta($appt_id, '_google_task_followup_id', 'abc123')
  ↓
✅ Tarefa criada no Google Tasks!
  ↓
Usuário recebe notificação no Google Tasks (mobile/desktop/email)
```

### Como Testar Fase 4

#### Teste 1: Follow-up pós-atendimento

1. Crie agendamento no DPS
2. Marque status como "Finalizado"
3. Aguarde ~2 segundos
4. Abra Google Tasks (tasks.google.com ou app)
5. ✅ Deve aparecer tarefa "📞 Follow-up: [Pet] - [Serviços]"
6. Vencimento deve ser daqui a 2 dias

#### Teste 2: Cobrança pendente

1. Crie transação pendente no Finance addon
2. Aguarde ~2 segundos
3. Abra Google Tasks
4. ✅ Deve aparecer tarefa "💰 Cobrança: [Cliente] - [Valor]"
5. Vencimento deve ser 1 dia antes da data de vencimento
6. Marque transação como "paga" no DPS
7. Aguarde ~2 segundos
8. ✅ Tarefa deve ser marcada como "concluída" automaticamente

#### Teste 3: Mensagem do portal

1. Simule mensagem do cliente (se addon Communications ativo)
2. Aguarde ~2 segundos
3. Abra Google Tasks
4. ✅ Deve aparecer tarefa "💬 Responder: [Cliente] - [Assunto]"
5. Vencimento deve ser daqui a 1 dia

## Troubleshooting

### Erro: "Credenciais do Google não configuradas"
- Verifique se definiu `DPS_GOOGLE_CLIENT_ID` e `DPS_GOOGLE_CLIENT_SECRET` no `wp-config.php`

### Erro: "Falha ao trocar authorization code por tokens"
- Verifique se a Redirect URI no Google Cloud Console está correta
- Confirme que as APIs (Calendar + Tasks) estão ativadas

### Erro: "OpenSSL extension not loaded"
- Instale/ative extensão OpenSSL do PHP
- Necessária para criptografia de tokens

### Botão "Conectar" não aparece
- Verifique logs do PHP
- Confirme que credenciais estão definidas corretamente

## Logs e Debug

Para ativar logs detalhados (desenvolvimento apenas):

```php
// No wp-config.php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

Logs estarão em `wp-content/debug.log`

## Referências

- [Google Calendar API Documentation](https://developers.google.com/calendar/api/v3/reference)
- [Google Tasks API Documentation](https://developers.google.com/tasks/reference/rest)
- [OAuth 2.0 for Web Server Applications](https://developers.google.com/identity/protocols/oauth2/web-server)
- Análise completa: `docs/analysis/GOOGLE_TASKS_INTEGRATION_ANALYSIS.md`

---

**Desenvolvido por:** Agente Copilot  
**Revisão:** Pendente  
**Última atualização:** 2026-01-19
