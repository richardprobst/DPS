# desi.pet by PRObst – Agenda Add-on

Gerenciamento de agenda de atendimentos e lembretes automáticos para clientes.

## Visão geral

O **Agenda Add-on** é uma extensão do desi.pet by PRObst Base que fornece uma interface dedicada para visualizar e gerenciar a agenda de atendimentos do dia. Este add-on permite aos recepcionistas e administradores acompanhar agendamentos em tempo real, atualizar status via AJAX e enviar lembretes automáticos diários aos clientes.

Funcionalidades principais:
- Visualização de agenda diária com filtros de data
- Atualização de status de agendamentos via interface AJAX
- Envio automático de lembretes diários via cron job
- Lista de cobranças pendentes (shortcode `[dps_charges_notes]`)
- Integração com add-on de Comunicações para envio de mensagens

**Tipo**: Add-on (extensão do plugin base DPS)

## Localização e identificação

- **Diretório**: `add-ons/desi-pet-shower-agenda_addon/`
- **Slug**: `dps-agenda-addon`
- **Classe principal**: `DPS_Agenda_Addon`
- **Arquivo principal**: `desi-pet-shower-agenda-addon.php`
- **Tipo**: Add-on (depende do plugin base)

## Dependências e compatibilidade

### Dependências obrigatórias
- **desi.pet by PRObst Base**: v1.0.0 ou superior (obrigatório)
- **WordPress**: 6.0 ou superior
- **PHP**: 7.4 ou superior

### Dependências opcionais
- **Communications Add-on**: para envio de lembretes via WhatsApp, SMS ou e-mail (opcional, mas recomendado)

### Versão
- **Introduzido em**: v0.1.0 (estimado)
- **Versão atual**: v1.0.0
- **Compatível com plugin base**: v1.0.0+

## Funcionalidades principais

### Agenda de atendimentos
- **Visualização diária**: lista completa de agendamentos do dia atual ou de data específica
- **Filtros de data**: navegação rápida entre dias através de seletor de data
- **Detalhes de agendamento**: exibe cliente, pets, horário, status e observações
- **Atualização de status**: botões AJAX para marcar agendamentos como "concluído" ou "cancelado" sem recarregar página

### Lembretes automáticos
- **Cron job diário**: executa automaticamente uma vez por dia
- **Envio inteligente**: dispara lembretes apenas para agendamentos do dia seguinte
- **Integração com Comunicações**: utiliza add-on de Comunicações se ativo, caso contrário não envia mensagens
- **Personalização**: mensagens configuráveis através do add-on de Comunicações

### Lista de cobranças pendentes
- **Shortcode dedicado**: `[dps_charges_notes]` para exibir cobranças em aberto
- **Integração financeira**: funciona em conjunto com Finance Add-on se instalado
- **Ações rápidas**: links diretos para WhatsApp com mensagem de cobrança

### Interface e usabilidade
- **AJAX completo**: atualizações sem recarregar página
- **Feedback visual**: indicadores de sucesso/erro nas ações
- **Responsivo**: interface otimizada para desktop e dispositivos móveis

## Shortcodes, widgets e endpoints

### Shortcodes

#### `[dps_agenda_page]`
Renderiza a página completa de agenda de atendimentos.

**Uso**:
```
[dps_agenda_page]
```

**Descrição**: Exibe interface de agenda com lista de agendamentos do dia, filtros de data e ações de atualização de status.

**Parâmetros**: Nenhum (data selecionável via interface).

**Permissões**: Usuário deve ter capability `dps_manage_appointments`.

**Exemplo de página**:
Este shortcode é automaticamente inserido na página "Agenda de Atendimentos" criada na ativação do plugin.

---

#### `[dps_charges_notes]`
Exibe lista de cobranças pendentes.

**Uso**:
```
[dps_charges_notes]
```

**Descrição**: Renderiza lista de agendamentos com cobranças em aberto, incluindo botões de ação para cobrança via WhatsApp.

**Parâmetros**: Nenhum.

**Permissões**: Usuário deve ter capability `dps_manage_appointments`.

**Observação**: Funcionalidade completa requer Finance Add-on instalado.

### Endpoints AJAX

- **`wp_ajax_dps_update_status`**: atualiza status de agendamento (concluído, cancelado)
  - **POST**: `appointment_id`, `status`, `nonce`
  - **Resposta**: JSON com sucesso/erro

- **`wp_ajax_dps_get_services_details`**: obtém detalhes de serviços vinculados a agendamento
  - **POST**: `appointment_id`, `nonce`
  - **Resposta**: JSON com lista de serviços

## Hooks (actions e filters) relevantes

### Hooks CONSUMIDOS por este add-on

Este add-on opera de forma independente através de shortcodes e não consome hooks do núcleo para renderização de abas (funciona em páginas próprias).

### Hooks DISPARADOS por este add-on

#### `dps_agenda_send_reminders` (action)
- **Tipo**: Cron job diário
- **Momento**: Uma vez por dia, em horário configurado pelo WordPress
- **Parâmetros**: nenhum
- **Propósito**: Enviar lembretes automáticos para agendamentos do dia seguinte
- **Implementação**: Método `send_reminders()` da classe principal
- **Observação**: Integra-se com Communications Add-on se disponível

## Dados armazenados (CPTs, tabelas, options)

### Custom Post Types
Este add-on NÃO cria CPTs próprios. Utiliza os CPTs do plugin base:
- **`dps_appointment`**: consome agendamentos do núcleo
- **`dps_client`**: busca dados de clientes para exibição
- **`dps_pet`**: busca dados de pets vinculados aos agendamentos

### Metadados utilizados (não criados por este add-on)
- **`appointment_date`**: data do agendamento (YYYY-MM-DD)
- **`appointment_time`**: horário do agendamento (HH:MM)
- **`appointment_status`**: status atual (agendado, concluído, cancelado)
- **`appointment_client_id`**: ID do cliente relacionado
- **`appointment_pet_ids`**: array de IDs de pets
- **`appointment_version`**: versionamento para controle de conflitos de escrita

### Tabelas customizadas
Este add-on NÃO cria tabelas próprias.

### Options armazenadas
- **`dps_agenda_page_id`**: ID da página "Agenda de Atendimentos" criada na ativação
- **`dps_charges_page_id`**: (legado) anteriormente usado para página de cobranças
- **Cron schedule**: armazenado internamente pelo WordPress para job `dps_agenda_send_reminders`

## Como usar (visão funcional)

### Para administradores e recepcionistas

1. **Acessar a agenda**:
   - Após ativar o plugin, acesse a página "Agenda de Atendimentos" criada automaticamente
   - Ou insira o shortcode `[dps_agenda_page]` em qualquer página WordPress

2. **Visualizar agendamentos do dia**:
   - A página exibe automaticamente os agendamentos do dia atual
   - Use o seletor de data para navegar para outros dias
   - Informações exibidas: cliente, pets, horário, status, observações

3. **Atualizar status de agendamentos**:
   - Clique no botão "Concluir" para marcar atendimento como finalizado
   - Clique no botão "Cancelar" para marcar como cancelado
   - A interface atualiza via AJAX sem recarregar a página
   - Feedback visual confirma sucesso da operação

4. **Configurar lembretes automáticos**:
   - Os lembretes são enviados automaticamente uma vez por dia
   - Para personalizar mensagens, configure o Communications Add-on
   - O cron job executa em segundo plano, sem intervenção manual

5. **Visualizar cobranças pendentes** (requer Finance Add-on):
   - Insira `[dps_charges_notes]` em uma página
   - Lista exibe agendamentos com valores em aberto
   - Clique em ações de cobrança para enviar mensagens via WhatsApp

### Fluxo típico de uso

```
1. Recepcionista acessa página de Agenda
2. Visualiza agendamentos do dia
3. Cliente chega para atendimento
4. Recepcionista clica em "Concluir"
5. Status atualiza para "concluído" via AJAX
6. Agendamento move para histórico (gerenciado pelo plugin base)
```

## Notas para desenvolvimento

### Convenções e padrões

Este add-on segue as diretrizes do repositório DPS:
- **[AGENTS.md](../../AGENTS.md)**: regras de desenvolvimento, versionamento, segurança, git-flow
- **[ANALYSIS.md](../../ANALYSIS.md)**: arquitetura, hooks, integrações com outros add-ons

### Fluxo obrigatório para mudanças

Ao modificar este add-on:

1. **Ler ANALYSIS.md** para entender integrações com Communications Add-on
2. **Implementar** seguindo políticas de segurança (nonces, sanitização, escape)
3. **Atualizar ANALYSIS.md** se criar novos hooks ou mudar fluxo de integração
4. **Atualizar CHANGELOG.md** antes de criar tags
5. **Validar** consistência entre documentação e código

### Políticas de segurança

- ✅ **AJAX endpoints**: validam nonce obrigatório (`dps_nonce`)
- ✅ **Capabilities**: verificam `dps_manage_appointments` antes de ações
- ✅ **Sanitização**: todos os inputs sanitizados com `sanitize_text_field`
- ✅ **Escape**: saída escapada com `esc_html`, `esc_attr`, `esc_url`

### Cron jobs e performance

- **Deactivation hook**: implementado corretamente para limpar cron job `dps_agenda_send_reminders` ao desativar
- **Agendamento único**: verifica se job já está agendado antes de criar novo
- **Performance**: queries otimizadas com meta_query para filtrar por data

### Integração com outros add-ons

#### Communications Add-on (opcional)
- Se ativo: lembretes enviados via canais configurados (WhatsApp, SMS, e-mail)
- Se inativo: cron executa mas não envia mensagens
- **Verificação**: `function_exists()` ou `class_exists()` antes de chamar funções do Communications

#### Finance Add-on (opcional)
- Shortcode `[dps_charges_notes]` funciona melhor com Finance Add-on
- Lista cobranças pendentes da tabela `dps_transacoes`
- Funciona parcialmente sem Finance (exibe agendamentos sem valores)

### Assets e scripts

- **`agenda-addon.js`**: funções AJAX para atualização de status
- **`agenda.js`**: (legado) considerar refatoração/mesclagem
- **Carregamento condicional**: assets carregados apenas nas páginas com shortcodes do add-on

### Pontos de atenção

- **Versionamento de agendamentos**: meta `appointment_version` incrementa a cada salvamento para evitar conflitos de escrita simultânea
- **Páginas criadas automaticamente**: plugin cria página "Agenda de Atendimentos" na ativação; não excluir manualmente
- **Cron cleanup**: SEMPRE limpar jobs na desativação para não deixar tarefas órfãs

## Histórico de mudanças (resumo)

### Principais marcos

- **v1.0.0**: Implementação de deactivation hook para limpeza de cron jobs ao desativar
- **v0.1.0**: Lançamento inicial com agenda diária, atualização AJAX de status e lembretes automáticos

### Melhorias recentes
- Adicionado cleanup automático de cron jobs na desativação
- Documentação expandida em ANALYSIS.md sobre padrão de deactivation hooks

Para o histórico completo de mudanças, consulte `CHANGELOG.md` na raiz do repositório.
