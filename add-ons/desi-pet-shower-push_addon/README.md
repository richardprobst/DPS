# DPS by PRObst – Push Notifications Add-on

Notificações recorrentes automáticas via e-mail e Telegram para equipe administrativa.

## Visão geral

O **Push Notifications Add-on** envia notificações automáticas e recorrentes para a equipe do pet shop, incluindo agenda diária, relatórios financeiros, alertas de pets inativos e outras métricas relevantes. Utiliza e-mail e Telegram como canais de comunicação.

Funcionalidades principais:
- Envio de agenda diária para equipe administrativa
- Relatórios financeiros diários (atendimentos + transações)
- Alertas de pets inativos (sem atendimento há 30 dias)
- Destinatários configuráveis por tipo de notificação
- Horários configuráveis para cada tipo de relatório
- Integração com Telegram Bot API

**Tipo**: Add-on (extensão do plugin base DPS)

## Localização e identificação

- **Diretório**: `add-ons/desi-pet-shower-push_addon/`
- **Slug**: `dps-push-addon`
- **Classe principal**: `DPS_Push_Notifications_Addon`
- **Arquivo principal**: `desi-pet-shower-push-addon.php`
- **Tipo**: Add-on (depende do plugin base)

## Dependências e compatibilidade

### Dependências obrigatórias
- **DPS by PRObst Base**: v1.0.0 ou superior
- **WordPress**: 6.0 ou superior
- **PHP**: 7.4 ou superior (com extensão cURL para Telegram)

### Dependências opcionais
- **Finance Add-on**: Para relatórios financeiros com dados de transações

### Versão
- **Versão atual**: 1.0.0
- **Introduzido em**: v0.1.0 (estimado)
- **Compatível com plugin base**: v1.0.0+

## Funcionalidades principais

### Notificações programadas

| Tipo | Frequência | Conteúdo |
|------|------------|----------|
| Agenda diária | Diário (horário configurável) | Lista de agendamentos do dia (horário, pet, cliente) |
| Relatório financeiro | Diário (horário configurável) | Atendimentos do dia + transações (pago/aberto) |
| Pets inativos | Semanal (dia/horário configurável) | Lista de pets sem atendimento há 30 dias |

### Canais de comunicação
- **E-mail**: via `wp_mail()` com formatação HTML
- **Telegram**: integração com Telegram Bot API para mensagens em grupos/canais

### Configuração

Acesse **DPS by PRObst > Notificações** para configurar:

1. **Resumo Diário de Agendamentos**
   - Destinatários (lista de emails separados por vírgula)
   - Horário de envio (formato 24h)

2. **Relatório Diário de Atendimentos e Financeiro**
   - Destinatários (lista de emails)
   - Horário de envio

3. **Relatório Semanal de Pets Inativos**
   - Dia da semana
   - Horário de envio

4. **Integração com Telegram**
   - Token do bot (obtido via @BotFather)
   - ID do chat/grupo

## Shortcodes, widgets e endpoints

### Shortcodes
Este add-on não expõe shortcodes públicos.

## Hooks (actions e filters) relevantes

### Hooks CONSUMIDOS por este add-on

Este add-on não consome hooks do sistema de configurações; usa menu administrativo próprio registrado via `add_submenu_page()` em `admin_menu` prioridade 20.

### Hooks DISPARADOS por este add-on

#### Cron jobs

| Hook | Frequência | Descrição |
|------|------------|-----------|
| `dps_send_agenda_notification` | Diário | Envia resumo de agendamentos do dia |
| `dps_send_daily_report` | Diário | Envia relatório de atendimentos e financeiro |
| `dps_send_weekly_inactive_report` | Semanal | Envia lista de pets inativos |

#### Actions customizadas

- **`dps_send_push_notification`** (action)
  - **Parâmetros**: `$message` (string), `$context` (mixed)
  - **Propósito**: permite que outros add-ons enviem notificações via Telegram
  - **Exemplo**: `do_action( 'dps_send_push_notification', 'Mensagem importante!', [] );`

#### Filters disponíveis

| Filter | Parâmetros | Descrição |
|--------|------------|-----------|
| `dps_push_notification_content` | `$content`, `$appointments` | Filtra conteúdo do email da agenda |
| `dps_push_notification_recipients` | `$recipients` | Filtra destinatários da agenda diária |
| `dps_daily_report_content` | `$content`, `$appointments`, `$trans` | Filtra texto do relatório |
| `dps_daily_report_html` | `$html`, `$appointments`, `$trans` | Filtra HTML do relatório |
| `dps_daily_report_recipients` | `$recipients` | Filtra destinatários do relatório |
| `dps_weekly_inactive_report_recipients` | `$recipients` | Filtra destinatários do relatório semanal |

## Dados armazenados (CPTs, tabelas, options)

### Custom Post Types
Este add-on NÃO cria CPTs próprios.

### Tabelas customizadas
Este add-on NÃO cria tabelas próprias.

### Options armazenadas

| Option | Tipo | Descrição |
|--------|------|-----------|
| `dps_push_emails_agenda` | array | Lista de emails para agenda diária |
| `dps_push_emails_report` | array | Lista de emails para relatório financeiro |
| `dps_push_agenda_time` | string | Horário do resumo (HH:MM) |
| `dps_push_report_time` | string | Horário do relatório financeiro (HH:MM) |
| `dps_push_weekly_day` | string | Dia da semana (monday, tuesday, etc.) |
| `dps_push_weekly_time` | string | Horário do relatório semanal (HH:MM) |
| `dps_push_telegram_token` | string | Token do bot do Telegram |
| `dps_push_telegram_chat` | string | ID do chat/grupo Telegram |

## Como usar (visão funcional)

### Para administradores

1. **Configurar destinatários**:
   - Acesse **DPS by PRObst > Notificações**
   - Configure e-mails de destinatários (separados por vírgula)
   - Defina horários de envio para cada tipo de relatório

2. **Configurar Telegram (opcional)**:
   - Crie um bot via @BotFather no Telegram
   - Copie o token do bot
   - Adicione o bot ao grupo/canal desejado
   - Obtenha o ID do chat (use @userinfobot ou a API `getUpdates`)
   - Configure no add-on

3. **Testar configurações**:
   - Aguarde o próximo envio agendado ou
   - Use WP-CLI para forçar execução: `wp cron event run dps_send_agenda_notification`

### Exemplo de mensagens

**Agenda diária (e-mail)**:
```html
<h3>Agendamentos para hoje (21/11/2024):</h3>
<ul>
  <li>09:00 – Rex (João Silva)</li>
  <li>10:30 – Mimi (Maria Santos)</li>
  <li>14:00 – Bob (Carlos Oliveira)</li>
</ul>
```

**Relatório financeiro diário**:
```html
<h3>Relatório diário de 21/11/2024</h3>
<h4>Resumo de atendimentos:</h4>
<ul>
  <li>09:00 – Rex (João Silva)</li>
  <li>10:30 – Mimi (Maria Santos)</li>
</ul>
<h4>Resumo financeiro:</h4>
<p>Total recebido (pago): <strong>R$ 450,00</strong></p>
<p>Total em aberto: <strong>R$ 120,00</strong></p>
```

**Relatório semanal de pets inativos**:
```html
<h3>Relatório semanal de pets inativos (21/11/2024)</h3>
<p>Pets sem atendimento nos últimos 30 dias:</p>
<ul>
  <li>Thor – Pedro Costa (último: 15/10/2024)</li>
  <li>Luna – Ana Souza (último: Nunca)</li>
</ul>
```

## Notas para desenvolvimento

### Convenções e padrões

Este add-on segue as diretrizes do repositório DPS:
- **[AGENTS.md](../../AGENTS.md)**: regras de desenvolvimento, versionamento, segurança
- **[ANALYSIS.md](../../ANALYSIS.md)**: integração com sistema de configurações
- **[PUSH_ADDON_ANALYSIS.md](../../docs/analysis/PUSH_ADDON_ANALYSIS.md)**: análise detalhada e propostas de melhoria

### Fluxo obrigatório para mudanças

Ao modificar este add-on:

1. **Ler ANALYSIS.md** para entender hooks e dependências
2. **Implementar** seguindo políticas de segurança (validação de tokens, sanitização)
3. **Testar** cron jobs em ambiente de desenvolvimento
4. **Atualizar ANALYSIS.md** se criar novos tipos de notificação
5. **Atualizar CHANGELOG.md** antes de criar tags

### Políticas de segurança

- ✅ **Tokens sensíveis**: armazenar token do Telegram em options com prefixo `dps_push_`
- ✅ **Sanitização**: validar e-mails com `is_email()` antes de enviar
- ✅ **Rate limiting**: respeitar limites de APIs (Telegram: 30 msg/segundo por bot)
- ✅ **Nonces**: formulário de configurações protegido com nonce
- ✅ **Capabilities**: verificação de `manage_options` antes de renderizar/salvar

### Cron jobs e deactivation

**ATENÇÃO**: Este add-on implementa `register_deactivation_hook` corretamente para limpar cron jobs ao desativar.

Cron jobs registrados:
- `dps_send_agenda_notification` - limpo no deactivate
- `dps_send_daily_report` - limpo no deactivate
- `dps_send_weekly_inactive_report` - limpo no deactivate

### Integração com Telegram

**Passos para configurar Telegram Bot**:
1. Criar bot via @BotFather no Telegram
2. Obter token do bot
3. Adicionar bot a grupo/canal
4. Obter chat ID (usar bot @userinfobot ou API `getUpdates`)
5. Configurar no add-on

### Pontos de atenção

- **Cron reliability**: WordPress cron requer tráfego no site; considerar cron real do servidor
- **Timezone**: garantir que horários configurados respeitam timezone do WordPress
- **Formatação**: Telegram recebe mensagens com `parse_mode: HTML`
- **Logs de envio**: atualmente não usa `DPS_Logger` (melhoria futura)
- **Deactivation**: SEMPRE limpar cron jobs ao desativar

### Melhorias futuras sugeridas

Para lista completa de melhorias propostas, consulte `docs/analysis/PUSH_ADDON_ANALYSIS.md`.

Principais sugestões:
- Botão "Enviar Teste" para validar configurações
- Checkbox para habilitar/desabilitar cada tipo de relatório
- Threshold de inatividade configurável (atualmente fixo em 30 dias)
- Integração com `DPS_Logger` para registro de envios
- Integração com `DPS_Communications_API` para centralizar envios
- Histórico de notificações enviadas
- Templates customizáveis de mensagens
- Suporte a mais canais (Slack, Discord, WhatsApp)

## Histórico de mudanças (resumo)

### v1.0.0 (atual)
- Agenda diária de agendamentos
- Relatório financeiro diário
- Relatório semanal de pets inativos (30 dias sem atendimento)
- Integração com e-mail e Telegram
- Horários e destinatários configuráveis
- Deactivation hook para limpeza de cron jobs

### v0.1.0
- Lançamento inicial

Para o histórico completo de mudanças, consulte `CHANGELOG.md` na raiz do repositório.
