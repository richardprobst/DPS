# Integrações Google - Fases 1 e 2

**Status Fase 1:** ✅ Concluída  
**Status Fase 2:** ✅ Concluída  
**Data:** 2026-01-19  
**Versão:** 2.0.0-fase2  

## O que foi implementado

### Estrutura de Arquivos

```
desi-pet-shower-agenda/includes/integrations/
├── class-dps-google-auth.php                    ✅ OAuth 2.0 Handler (Fase 1)
├── class-dps-google-integrations-settings.php   ✅ Interface Administrativa (Fase 1+2)
├── class-dps-google-calendar-client.php         ✅ Cliente Calendar API (Fase 2)
├── class-dps-google-calendar-sync.php           ✅ Sincronização Calendar (Fase 2)
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

## O que NÃO foi implementado (Próximas Fases)

❌ Webhook Calendar → DPS (sincronização bidirecional) - Fase 3  
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

### Fase 1+2: Testar Desconexão

1. Clique em "Desconectar"
2. Confirme no alerta
3. Status deve voltar para "Não Conectado"
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

## Próximos Passos (Fase 2)

1. Criar `class-dps-google-calendar-client.php`
   - Cliente HTTP para Calendar API v3
   - Métodos: `create_event()`, `update_event()`, `delete_event()`

2. Criar `class-dps-google-calendar-sync.php`
   - Sincronização unidirecional (DPS → Calendar)
   - Hook em `save_post_dps_agendamento`
   - Formatação de agendamentos como eventos

3. Adicionar metadados:
   - `_google_calendar_event_id`
   - `_google_calendar_synced_at`

4. Habilitar checkbox na UI:
   - "Sincronizar agendamentos com Google Calendar"

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
