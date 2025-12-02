# Desi Pet Shower – Groomers Add-on

Cadastro de profissionais, vinculação a atendimentos e relatórios de produtividade.

## Visão geral

O **Groomers Add-on** permite cadastrar e gerenciar profissionais de banho e tosa (groomers), vincular cada atendimento a um profissional específico e gerar relatórios de produtividade individual. É ideal para pet shops com múltiplos funcionários que precisam acompanhar desempenho e distribuição de trabalho.

### Funcionalidades principais
- ✅ Cadastro de profissionais via role customizada do WordPress
- ✅ Edição e exclusão de groomers via interface
- ✅ Vinculação de múltiplos groomers por atendimento
- ✅ Relatórios de produtividade por profissional
- ✅ Exportação de relatórios em CSV
- ✅ Métricas: total de atendimentos, receita, ticket médio
- ✅ Listagem de atendimentos por groomer com detalhes de cliente e pet
- ✅ Interface integrada ao painel principal do sistema
- ✅ CSS externo seguindo padrão visual minimalista do DPS
- ✅ Formulários com fieldsets e indicadores de campos obrigatórios
- ✅ Modal de edição com validação
- ✅ Confirmação de exclusão com aviso de agendamentos vinculados

**Tipo**: Add-on (extensão do plugin base DPS)

**Versão atual**: 1.2.0

## Localização e identificação

- **Diretório**: `add-ons/desi-pet-shower-groomers_addon/`
- **Slug**: `dps-groomers-addon`
- **Classe principal**: `DPS_Groomers_Addon`
- **Arquivo principal**: `desi-pet-shower-groomers-addon.php`

## Estrutura de arquivos

```
add-ons/desi-pet-shower-groomers_addon/
├── desi-pet-shower-groomers-addon.php   # Arquivo principal
├── assets/
│   ├── css/
│   │   └── groomers-admin.css           # Estilos da interface (~600 linhas)
│   └── js/
│       └── groomers-admin.js            # Interatividade, modal e validações
├── README.md                             # Esta documentação
└── uninstall.php                         # Limpeza na desinstalação
```

## Dependências e compatibilidade

### Dependências obrigatórias
- **Desi Pet Shower Base**: v1.0.0 ou superior (obrigatório)
- **WordPress**: 6.0 ou superior
- **PHP**: 7.4 ou superior

### Integrações opcionais
- **Finance Add-on**: Para cálculo automático de receitas nos relatórios
- **Stats Add-on**: Para métricas consolidadas (futuro)

## Funcionalidades principais

### Gestão de groomers
- **Cadastro via formulário**: cria usuários WordPress com role `dps_groomer`
- **Campos**: usuário, email, nome completo, senha
- **Listagem**: visualize todos os groomers cadastrados
- **Validação**: verificação de email e usuário já existentes

### Vinculação a atendimentos
- **Campo de seleção múltipla**: permite associar vários groomers por atendimento
- **Metadado `_dps_groomers`**: array de IDs armazenado no agendamento
- **Validação de role**: apenas usuários com role `dps_groomer` são aceitos

### Relatórios de produtividade
- **Filtros**: groomer específico + período (data inicial e final)
- **Métricas exibidas**:
  - Total de atendimentos no período
  - Receita total (soma de transações pagas)
  - Ticket médio
- **Detalhamento**: tabela com data, horário, cliente, pet e status
- **Limite**: 500 atendimentos por consulta (aviso exibido se atingido)

## Dados armazenados

### Role customizada

#### `dps_groomer`
Role criada na ativação do plugin para identificar profissionais.

**Capabilities**:
- `read`: true (acesso básico ao painel WordPress)

### Metadados em agendamentos

| Meta Key | Tipo | Descrição |
|----------|------|-----------|
| `_dps_groomers` | array | IDs dos groomers responsáveis pelo atendimento |

## Hooks consumidos

| Hook | Prioridade | Propósito |
|------|------------|-----------|
| `dps_base_nav_tabs_after_history` | 15 | Adiciona aba "Groomers" |
| `dps_base_sections_after_history` | 15 | Renderiza seção de groomers |
| `dps_base_appointment_fields` | 10 | Campo de seleção no form de agendamento |
| `dps_base_after_save_appointment` | 10 | Salva groomers selecionados |
| `wp_enqueue_scripts` | default | Carrega assets no frontend |
| `admin_enqueue_scripts` | default | Carrega assets no admin |

## Como usar

### Para administradores

1. **Cadastrar groomer**:
   - Acesse a aba "Groomers" no Painel de Gestão DPS
   - Preencha o formulário "Adicionar novo groomer"
   - Clique em "Criar groomer"

2. **Editar groomer**:
   - Na tabela de groomers, clique em "✏️ Editar"
   - Modifique o nome ou email no modal
   - Clique em "Salvar alterações"

3. **Excluir groomer**:
   - Na tabela de groomers, clique em "🗑️ Excluir"
   - Confirme a exclusão (será informado quantos agendamentos estão vinculados)
   - Agendamentos vinculados são mantidos sem groomer associado

4. **Vincular groomer a atendimento**:
   - Ao criar/editar agendamento
   - Localize o campo "Groomers responsáveis"
   - Selecione um ou mais profissionais (Ctrl+clique para múltiplos)
   - Salve o agendamento

5. **Visualizar relatórios**:
   - Na aba "Groomers", role até "Relatório por Groomer"
   - Selecione o profissional
   - Defina período (data inicial e final)
   - Clique em "Gerar relatório"
   - Visualize métricas e lista de atendimentos

6. **Exportar relatório**:
   - Após gerar o relatório, clique em "📊 Exportar CSV"
   - O arquivo inclui: data, horário, cliente, pet, status, valor
   - Linha de totais no final do arquivo

### Para recepcionistas

- Ao criar agendamentos, selecione os groomers disponíveis
- Sistema valida se os usuários selecionados têm a role correta

## Changelog

### [1.2.0] - 2025-12-02

#### Added
- Coluna "Ações" na tabela de groomers com botões Editar e Excluir
- Modal de edição de groomer (nome e email)
- Confirmação de exclusão com aviso de agendamentos vinculados
- Botão "Exportar CSV" no relatório de produtividade
- Exportação CSV inclui: data, horário, cliente, pet, status, valor
- Linha de totais no final do CSV exportado
- Handlers seguros com nonces para todas as ações
- Método `get_groomer_appointments_count()` para contagem de vínculos
- Método `get_appointment_value()` para obter valor de agendamentos
- CSS para modal responsivo com animação
- Estilos para ações na tabela e botão de exportação

### [1.1.0] - 2025-12-02

#### Added
- Estrutura de assets: pasta `assets/css/` e `assets/js/`
- Arquivo CSS externo `groomers-admin.css` com ~400 linhas de estilos
- Arquivo JS externo `groomers-admin.js` com validações e interatividade
- Método `calculate_total_revenue()` com integração à Finance API
- Enqueue de assets no frontend e admin
- Cards de métricas visuais no relatório (profissional, atendimentos, receita, ticket médio)
- Coluna "Pet" na tabela de resultados do relatório
- Formatação de data no padrão brasileiro (dd/mm/yyyy)
- Badges de status com cores semânticas
- Fieldsets no formulário de cadastro (Dados de Acesso, Informações Pessoais)
- Indicadores de campos obrigatórios (asterisco vermelho)
- Placeholders descritivos em todos os campos

#### Changed
- Removidos estilos inline, substituídos por classes CSS
- Layout responsivo com flexbox e grid
- Formulário reorganizado com fieldsets semânticos
- Tabela de groomers com classes CSS customizadas
- Seção de relatórios com design minimalista
- Integração com Finance API (quando disponível) para cálculo de receitas

#### Fixed
- Corrigido `uninstall.php` para usar meta key correta `_dps_groomers`
- Mensagem de empty state mais descritiva na tabela de groomers

### [1.0.0] - Versão inicial

- Cadastro de groomers via formulário
- Listagem de profissionais cadastrados
- Vinculação de múltiplos groomers a agendamentos
- Relatórios de produtividade por período
- Integração com hooks do plugin base

## Melhorias futuras sugeridas

Consulte o documento de análise completa em `docs/analysis/GROOMERS_ADDON_ANALYSIS.md` para:
- Plano de refatoração modular
- Novas funcionalidades propostas
- Melhorias de UX detalhadas
- Estimativas de esforço

### Funcionalidades planejadas
- [ ] Edição e exclusão de groomers via interface
- [ ] Status ativo/inativo para groomers
- [ ] Exportação de relatórios em CSV
- [ ] Campo de telefone do groomer
- [ ] Dashboard individual do groomer
- [ ] Sistema de comissões
- [ ] Gráficos de desempenho

## Notas para desenvolvimento

### Convenções e padrões

Este add-on segue as diretrizes do repositório DPS:
- **[AGENTS.md](../../AGENTS.md)**: regras de desenvolvimento, versionamento, segurança
- **[ANALYSIS.md](../../ANALYSIS.md)**: arquitetura, hooks consumidos

### Políticas de segurança

- ✅ Nonces em todos os formulários
- ✅ Verificação de `manage_options` capability
- ✅ Sanitização de todos os inputs
- ✅ Escape de todos os outputs
- ✅ Validação de role antes de salvar groomers em agendamentos
