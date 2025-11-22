# Desi Pet Shower – Comunicações Add-on

Gerenciamento de comunicações automatizadas via WhatsApp, SMS e e-mail.

## Visão geral

O **Comunicações Add-on** centraliza o envio de notificações automáticas para clientes através de múltiplos canais (WhatsApp, SMS e e-mail). Este add-on permite configurar mensagens personalizadas para diferentes eventos do sistema, como confirmação de agendamento, lembretes antes do atendimento e mensagens pós-atendimento.

Funcionalidades principais:
- Envio de notificações via WhatsApp, SMS e e-mail
- Templates customizáveis para diferentes tipos de mensagem
- Disparo automático após eventos do sistema (agendamentos, conclusões)
- Cron jobs para lembretes agendados e follow-up pós-atendimento
- Integração com APIs de terceiros (ex.: Twilio para SMS, gateways de e-mail)

**Tipo**: Add-on (extensão do plugin base DPS)

## Localização e identificação

- **Diretório**: `add-ons/desi-pet-shower-communications_addon/`
- **Slug**: `dps-communications-addon`
- **Classe principal**: (verificar no arquivo principal)
- **Arquivo principal**: `desi-pet-shower-communications-addon.php`
- **Tipo**: Add-on (depende do plugin base)

## Dependências e compatibilidade

### Dependências obrigatórias
- **Desi Pet Shower Base**: v1.0.0 ou superior (obrigatório)
- **WordPress**: 6.0 ou superior
- **PHP**: 7.4 ou superior

### Versão
- **Introduzido em**: v0.1.0 (estimado)
- **Versão atual**: (verificar header do plugin)
- **Compatível com plugin base**: v1.0.0+

## Funcionalidades principais

### Canais de comunicação
- **WhatsApp**: envio via API do WhatsApp Business ou gateways compatíveis
- **SMS**: integração com provedores de SMS (ex.: Twilio)
- **E-mail**: envio via wp_mail() ou SMTP configurado

### Templates de mensagens
- **Confirmação de agendamento**: enviado automaticamente após salvar novo agendamento
- **Lembrete de atendimento**: enviado X horas/dias antes do agendamento
- **Pós-atendimento**: enviado Y dias após conclusão do atendimento
- **Personalização**: variáveis dinâmicas (nome do cliente, nome do pet, data, horário, etc.)

### Automações via cron jobs
- **Lembretes diários**: cron job executa uma vez por dia e envia lembretes agendados
- **Follow-up pós-atendimento**: cron job verifica atendimentos concluídos e envia mensagens de acompanhamento
- **Configuração flexível**: permite ajustar horário e frequência de execução

### Interface de configuração
- Aba "Comunicações" na tela de configurações do plugin base
- Formulários para configurar credenciais de APIs (tokens, chaves)
- Editores de templates com preview de variáveis disponíveis
- Opções para habilitar/desabilitar cada canal e tipo de mensagem

## Shortcodes, widgets e endpoints

### Shortcodes
Este add-on não expõe shortcodes próprios. Opera através de configurações e hooks.

## Hooks (actions e filters) relevantes

### Hooks CONSUMIDOS por este add-on

#### `dps_base_after_save_appointment` (action)
- **Propósito**: disparar envio de confirmação de agendamento
- **Parâmetros**: `$appointment_id` (int)
- **Implementação**: envia notificação automática via canais configurados

#### `dps_settings_nav_tabs` (action)
- **Propósito**: adicionar aba "Comunicações" à navegação de configurações
- **Parâmetros**: `$visitor_only` (bool)
- **Implementação**: renderiza tab na interface de configurações

#### `dps_settings_sections` (action)
- **Propósito**: renderizar conteúdo da seção de comunicações
- **Parâmetros**: `$active_tab` (string)
- **Implementação**: exibe formulários de configuração e templates

### Hooks DISPARADOS por este add-on

#### `dps_comm_send_appointment_reminder` (action - cron job)
- **Tipo**: Cron job configurável
- **Momento**: Executa em horário/frequência configurados
- **Parâmetros**: nenhum
- **Propósito**: Enviar lembretes de agendamentos próximos

#### `dps_comm_send_post_service` (action - cron job)
- **Tipo**: Cron job configurável
- **Momento**: Executa após período configurado pós-atendimento
- **Parâmetros**: nenhum
- **Propósito**: Enviar mensagens de follow-up após conclusão de atendimentos

## Dados armazenados (CPTs, tabelas, options)

### Custom Post Types
Este add-on NÃO cria CPTs próprios.

### Tabelas customizadas
Este add-on NÃO cria tabelas próprias.

### Options armazenadas

#### Configurações de canais
- **`dps_comm_whatsapp_enabled`**: (bool) habilitar WhatsApp
- **`dps_comm_whatsapp_token`**: token de acesso API WhatsApp
- **`dps_comm_sms_enabled`**: (bool) habilitar SMS
- **`dps_comm_sms_provider`**: provedor de SMS (ex.: "twilio")
- **`dps_comm_sms_credentials`**: credenciais do provedor (serializado)
- **`dps_comm_email_enabled`**: (bool) habilitar e-mail
- **`dps_comm_email_from`**: endereço de e-mail remetente

#### Templates de mensagens
- **`dps_comm_template_appointment_confirmation`**: template de confirmação
- **`dps_comm_template_appointment_reminder`**: template de lembrete
- **`dps_comm_template_post_service`**: template pós-atendimento

#### Configurações de automação
- **`dps_comm_reminder_days_before`**: quantos dias antes enviar lembrete
- **`dps_comm_post_service_days_after`**: quantos dias depois enviar follow-up

## Como usar (visão funcional)

### Para administradores

1. **Configurar canais**:
   - Acesse a tela de configurações (`[dps_configuracoes]`)
   - Clique na aba "Comunicações"
   - Habilite os canais desejados (WhatsApp, SMS, e-mail)
   - Insira credenciais de APIs conforme necessário

2. **Personalizar templates**:
   - Na mesma aba, localize editores de templates
   - Edite mensagens usando variáveis disponíveis (ex.: `{cliente_nome}`, `{pet_nome}`, `{data}`, `{horario}`)
   - Salve alterações

3. **Configurar automações**:
   - Defina quantos dias antes do agendamento enviar lembretes
   - Defina quantos dias após conclusão enviar follow-up
   - Ative/desative tipos de mensagem conforme necessário

4. **Testar envios**:
   - Crie um agendamento de teste
   - Verifique se confirmação foi enviada
   - Aguarde execução de cron jobs ou force execução manual via WP-CLI

### Variáveis disponíveis em templates

- **`{cliente_nome}`**: nome do cliente
- **`{cliente_telefone}`**: telefone do cliente
- **`{pet_nome}`**: nome do pet (ou lista de pets)
- **`{data}`**: data do agendamento (formato brasileiro)
- **`{horario}`**: horário do agendamento
- **`{status}`**: status do agendamento
- **`{observacoes}`**: observações do agendamento

## Notas para desenvolvimento

### Convenções e padrões

Este add-on segue as diretrizes do repositório DPS:
- **[AGENTS.md](../../AGENTS.md)**: regras de desenvolvimento, versionamento, segurança
- **[ANALYSIS.md](../../ANALYSIS.md)**: arquitetura, hooks consumidos/disparados

### Fluxo obrigatório para mudanças

Ao modificar este add-on:

1. **Ler ANALYSIS.md** para entender hooks do plugin base e integrações
2. **Implementar** seguindo políticas de segurança (sanitização de tokens, escape)
3. **Atualizar ANALYSIS.md** se criar novos hooks ou tipos de mensagem
4. **Atualizar CHANGELOG.md** antes de criar tags
5. **Testar** envios em ambiente de desenvolvimento antes de produção

### Políticas de segurança

- ✅ **Tokens sensíveis**: armazenar em options com prefixo `dps_comm_`
- ✅ **Sanitização**: validar templates e credenciais antes de salvar
- ✅ **Escape**: escapar variáveis ao renderizar templates
- ✅ **Capabilities**: verificar `manage_options` antes de salvar configurações
- ⚠️ **APIs de terceiros**: validar respostas e tratar erros adequadamente

### Integração com outros add-ons

#### Agenda Add-on (opcional)
- Agenda consome funções do Communications para enviar lembretes
- Verificar `function_exists()` antes de chamar

#### Payment Add-on (opcional)
- Pode usar Communications para enviar links de pagamento via WhatsApp
- Integração via hooks ou chamadas diretas

### Pontos de atenção

- **Rate limiting**: respeitar limites de APIs de terceiros (ex.: WhatsApp Business)
- **Fila de envios**: considerar implementar fila para envios em massa
- **Logs de envio**: registrar sucessos/falhas via `DPS_Logger`
- **Opt-out**: permitir clientes descadastrarem de comunicações automáticas (LGPD)
- **Cron cleanup**: limpar jobs ao desativar plugin (implementar deactivation hook)

### Melhorias futuras sugeridas

- Interface para histórico de mensagens enviadas
- Relatório de taxa de entrega/abertura
- Segmentação de público (enviar apenas para clientes ativos, etc.)
- Suporte a anexos (fotos, PDFs) em e-mails
- Integração com mais provedores de SMS e WhatsApp

## Histórico de mudanças (resumo)

### Principais marcos

- **v0.1.0**: Lançamento inicial com suporte a WhatsApp, SMS e e-mail, templates customizáveis e cron jobs de lembretes

Para o histórico completo de mudanças, consulte `CHANGELOG.md` na raiz do repositório.
