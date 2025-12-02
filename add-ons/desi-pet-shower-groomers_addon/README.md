# Desi Pet Shower – Groomers Add-on

Cadastro de profissionais, vinculação a atendimentos e relatórios de produtividade.

## Visão geral

O **Groomers Add-on** permite cadastrar e gerenciar profissionais de banho e tosa (groomers), vincular cada atendimento a um profissional específico e gerar relatórios de produtividade individual. É ideal para pet shops com múltiplos funcionários que precisam acompanhar desempenho e distribuição de trabalho.

### Funcionalidades principais
- ✅ Cadastro de profissionais via role customizada do WordPress
- ✅ Edição e exclusão de groomers via interface
- ✅ Status ativo/inativo para groomers
- ✅ Campo de telefone e percentual de comissão
- ✅ Vinculação de múltiplos groomers por atendimento
- ✅ Relatórios de produtividade por profissional
- ✅ Exportação de relatórios em CSV
- ✅ Métricas: total de atendimentos, receita, ticket médio, comissão
- ✅ Dashboard individual do groomer com gráficos
- ✅ Agenda semanal do groomer
- ✅ Relatório de comissões a pagar
- ✅ Sistema de avaliações de clientes
- ✅ CSS externo seguindo padrão visual minimalista do DPS

**Tipo**: Add-on (extensão do plugin base DPS)

**Versão atual**: 1.3.0

## Shortcodes disponíveis

| Shortcode | Descrição | Parâmetros |
|-----------|-----------|------------|
| `[dps_groomer_dashboard]` | Dashboard individual do groomer | - |
| `[dps_groomer_agenda]` | Agenda semanal do groomer | - |
| `[dps_groomer_review]` | Formulário de avaliação | `groomer_id`, `appointment_id` |
| `[dps_groomer_reviews]` | Lista de avaliações | `groomer_id`, `limit` |

## Localização e identificação

- **Diretório**: `add-ons/desi-pet-shower-groomers_addon/`
- **Slug**: `dps-groomers-addon`
- **Classe principal**: `DPS_Groomers_Addon`
- **Arquivo principal**: `desi-pet-shower-groomers-addon.php`

## Estrutura de arquivos

```
add-ons/desi-pet-shower-groomers_addon/
├── desi-pet-shower-groomers-addon.php   # Arquivo principal (~2400 linhas)
├── assets/
│   ├── css/
│   │   └── groomers-admin.css           # Estilos da interface (~1200 linhas)
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
- **Chart.js**: Carregado via CDN para gráficos de desempenho

## Dados armazenados

### Role customizada

#### `dps_groomer`
Role criada na ativação do plugin para identificar profissionais.

### CPT de avaliações

#### `dps_groomer_review`
Post type para armazenar avaliações de clientes.

### Metadados em usuários

| Meta Key | Tipo | Descrição |
|----------|------|-----------|
| `_dps_groomer_status` | string | Status: 'active' ou 'inactive' |
| `_dps_groomer_phone` | string | Telefone do groomer |
| `_dps_groomer_commission_rate` | float | Percentual de comissão (0-100) |

### Metadados em agendamentos

| Meta Key | Tipo | Descrição |
|----------|------|-----------|
| `_dps_groomers` | array | IDs dos groomers responsáveis pelo atendimento |

### Metadados em avaliações

| Meta Key | Tipo | Descrição |
|----------|------|-----------|
| `_dps_review_groomer_id` | int | ID do groomer avaliado |
| `_dps_review_rating` | int | Nota de 1 a 5 estrelas |
| `_dps_review_name` | string | Nome do avaliador (opcional) |
| `_dps_review_appointment_id` | int | ID do agendamento relacionado (opcional) |

## Changelog

### [1.3.0] - 2025-12-02

#### Added
- Campo de telefone no cadastro e edição de groomers
- Campo de percentual de comissão no cadastro e edição
- Status ativo/inativo com toggle clicável na tabela
- Groomers inativos não aparecem no select de agendamentos
- Shortcode `[dps_groomer_dashboard]` para dashboard individual
- Métricas pessoais: atendimentos, receita, comissão, ticket médio
- Contagem por status: realizados, pendentes, cancelados
- Gráficos de desempenho com Chart.js
- Gráfico de barras: atendimentos por dia
- Gráfico de linha: receita por dia
- Relatório de comissões a pagar de todos os groomers
- Shortcode `[dps_groomer_agenda]` para agenda semanal
- Visualização em grid de 7 dias com navegação
- Destaque visual para o dia atual
- CPT `dps_groomer_review` para avaliações
- Shortcode `[dps_groomer_review]` para formulário de avaliação
- Shortcode `[dps_groomer_reviews]` para exibição de avaliações
- Sistema de 5 estrelas com média calculada
- Método `get_groomer_rating()` para obter média de avaliações

### [1.2.0] - 2025-12-02

#### Added
- Coluna "Ações" na tabela de groomers com botões Editar e Excluir
- Modal de edição de groomer (nome e email)
- Confirmação de exclusão com aviso de agendamentos vinculados
- Botão "Exportar CSV" no relatório de produtividade
- Exportação CSV inclui: data, horário, cliente, pet, status, valor
- Linha de totais no final do CSV exportado

### [1.1.0] - 2025-12-02

#### Added
- Estrutura de assets: pasta `assets/css/` e `assets/js/`
- Cards de métricas visuais no relatório
- Fieldsets no formulário de cadastro

#### Fixed
- Corrigido `uninstall.php` para usar meta key correta `_dps_groomers`

### [1.0.0] - Versão inicial

- Cadastro de groomers via formulário
- Listagem de profissionais cadastrados
- Vinculação de múltiplos groomers a agendamentos
- Relatórios de produtividade por período

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
