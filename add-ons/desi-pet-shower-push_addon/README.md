# Desi Pet Shower – Push Notifications Add-on

Notificações recorrentes automáticas via e-mail e Telegram para equipe administrativa.

## Visão geral

O **Push Notifications Add-on** envia notificações automáticas e recorrentes para a equipe do pet shop, incluindo agenda diária, relatórios financeiros, alertas de pets inativos e outras métricas relevantes. Utiliza e-mail e Telegram como canais de comunicação.

Funcionalidades principais:
- Envio de agenda diária para equipe administrativa
- Relatórios financeiros diários e semanais
- Alertas de pets inativos (sem atendimento há X dias)
- Filtros configuráveis de destinatários
- Integração com Telegram Bot API
- Cron jobs configuráveis para cada tipo de notificação

**Tipo**: Add-on (extensão do plugin base DPS)

## Localização e identificação

- **Diretório**: `add-ons/desi-pet-shower-push_addon/`
- **Slug**: `dps-push-addon`
- **Classe principal**: (verificar no arquivo principal)
- **Arquivo principal**: `desi-pet-shower-push-addon.php`
- **Tipo**: Add-on (depende do plugin base)

## Dependências e compatibilidade

### Dependências obrigatórias
- **Desi Pet Shower Base**: v1.0.0 ou superior
- **WordPress**: 6.0 ou superior
- **PHP**: 7.4 ou superior (com extensão cURL para Telegram)

### Versão
- **Introduzido em**: v0.1.0 (estimado)
- **Compatível com plugin base**: v1.0.0+

## Funcionalidades principais

### Notificações programadas
- **Agenda diária**: resumo de agendamentos do dia enviado toda manhã
- **Relatório financeiro diário**: receitas/despesas do dia anterior
- **Relatório semanal de pets inativos**: lista de clientes sem atendimento há X semanas
- **Alertas customizáveis**: criar notificações adicionais conforme necessidade

### Canais de comunicação
- **E-mail**: via wp_mail() ou SMTP configurado
- **Telegram**: integração com Telegram Bot API para mensagens em grupos/canais

### Configuração flexível
- **Destinatários**: definir quem recebe cada tipo de notificação
- **Frequência**: configurar horário e dias da semana para cada cron job
- **Conteúdo**: personalizar mensagens e métricas incluídas
- **Habilitar/desabilitar**: ligar/desligar tipos de notificação individualmente

## Shortcodes, widgets e endpoints

### Shortcodes
Este add-on não expõe shortcodes públicos.

## Hooks (actions e filters) relevantes

### Hooks CONSUMIDOS por este add-on

#### `dps_settings_nav_tabs` (action)
- **Propósito**: adicionar aba "Notificações" à tela de configurações
- **Parâmetros**: `$visitor_only` (bool)

#### `dps_settings_sections` (action)
- **Propósito**: renderizar configurações de notificações
- **Parâmetros**: `$active_tab` (string)

### Hooks DISPARADOS por este add-on

#### Cron jobs

- **`dps_push_daily_agenda`**: enviar agenda diária
- **`dps_push_daily_financial_report`**: enviar relatório financeiro diário
- **`dps_push_weekly_inactive_pets`**: enviar relatório semanal de pets inativos

#### Hooks customizados

- **`dps_send_push_notification`** (action)
  - **Parâmetros**: `$message` (string), `$channel` (string: 'email' ou 'telegram')
  - **Propósito**: permitir outros add-ons enviarem notificações push

## Dados armazenados (CPTs, tabelas, options)

### Custom Post Types
Este add-on NÃO cria CPTs próprios.

### Tabelas customizadas
Este add-on NÃO cria tabelas próprias.

### Options armazenadas

- **`dps_push_email_recipients`**: lista de e-mails que recebem notificações
- **`dps_push_telegram_bot_token`**: token do Telegram Bot
- **`dps_push_telegram_chat_id`**: ID do chat/grupo/canal Telegram
- **`dps_push_daily_agenda_enabled`**: habilitar agenda diária (bool)
- **`dps_push_daily_agenda_time`**: horário de envio (HH:MM)
- **`dps_push_financial_report_enabled`**: habilitar relatório financeiro (bool)
- **`dps_push_inactive_pets_threshold`**: dias sem atendimento para considerar inativo (int)

## Como usar (visão funcional)

### Para administradores

1. **Configurar canais**:
   - Acesse configurações > aba "Notificações"
   - Configure e-mails de destinatários
   - Insira token do Telegram Bot (obtido via @BotFather)
   - Insira ID do chat/grupo Telegram

2. **Configurar notificações**:
   - Habilite tipos de notificação desejados
   - Defina horários de envio para cada tipo
   - Configure threshold de inatividade (para alertas de pets)
   - Salve configurações

3. **Testar envios**:
   - Use botão "Enviar Teste" para validar configurações
   - Verifique recebimento em e-mail e Telegram

4. **Acompanhar cron jobs**:
   - Verifique logs via `DPS_Logger` para auditar envios
   - Use WP-CLI ou plugins de cron para forçar execuções manuais

### Exemplo de mensagens

**Agenda diária (e-mail/Telegram)**:
```
🐾 Agenda DPS - 21/11/2024

📅 Atendimentos do dia:
- 09:00 - Rex (Golden Retriever) - Banho e Tosa
- 10:30 - Mimi (Gato Persa) - Banho
- 14:00 - Bob (Poodle) - Tosa

Total: 3 agendamentos
```

**Relatório financeiro diário**:
```
💰 Relatório Financeiro - 20/11/2024

Receitas: R$ 450,00
Despesas: R$ 120,00
Saldo: R$ 330,00

Cobranças pendentes: R$ 890,00
```

## Notas para desenvolvimento

### Convenções e padrões

Este add-on segue as diretrizes do repositório DPS:
- **[AGENTS.md](../../AGENTS.md)**: regras de desenvolvimento, versionamento, segurança
- **[ANALYSIS.md](../../ANALYSIS.md)**: integração com sistema de configurações

### Fluxo obrigatório para mudanças

Ao modificar este add-on:

1. **Ler ANALYSIS.md** para entender hooks de configurações
2. **Implementar** seguindo políticas de segurança (validação de tokens, sanitização)
3. **Testar** cron jobs em ambiente de desenvolvimento
4. **Atualizar ANALYSIS.md** se criar novos tipos de notificação
5. **Atualizar CHANGELOG.md** antes de criar tags

### Políticas de segurança

- ✅ **Tokens sensíveis**: armazenar token do Telegram em options com prefixo `dps_push_`
- ✅ **Sanitização**: validar e-mails e IDs de chat antes de salvar
- ✅ **Rate limiting**: respeitar limites de APIs (Telegram: 30 msg/segundo por bot)
- ✅ **Validação**: verificar formato de e-mails e token antes de enviar
- ⚠️ **Exposição de dados**: não incluir informações sensíveis em notificações

### Cron jobs e deactivation

**ATENÇÃO**: Este add-on implementa `register_deactivation_hook` corretamente para limpar cron jobs ao desativar.

Ao adicionar novos cron jobs:
1. Registrar evento com `wp_schedule_event()`
2. Adicionar limpeza no método `deactivate()` usando `wp_clear_scheduled_hook()`

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
- **Formatação**: usar markdown no Telegram para formatação de mensagens
- **Logs de envio**: registrar sucessos/falhas via `DPS_Logger`
- **Deactivation**: SEMPRE limpar cron jobs ao desativar

### Melhorias futuras sugeridas

- Suporte a mais canais (Slack, Discord, SMS)
- Interface para histórico de notificações enviadas
- Retry automático para envios falhados
- Templates customizáveis de mensagens
- Notificações baseadas em eventos (não apenas cron)

## Histórico de mudanças (resumo)

### Principais marcos

- **v0.1.0**: Lançamento inicial com agenda diária, relatórios financeiros, alertas de pets inativos, integração e-mail/Telegram
- Implementação correta de deactivation hook para limpeza de cron jobs

Para o histórico completo de mudanças, consulte `CHANGELOG.md` na raiz do repositório.
