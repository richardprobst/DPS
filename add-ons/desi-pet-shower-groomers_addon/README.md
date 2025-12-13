# DPS by PRObst – Groomers Add-on

Cadastro de profissionais, vinculação a atendimentos e relatórios de produtividade.

## Visão geral

O **Groomers Add-on** permite cadastrar e gerenciar profissionais de banho e tosa (groomers, banhistas, auxiliares), vincular cada atendimento a um profissional específico e gerar relatórios de produtividade individual. É ideal para pet shops com múltiplos funcionários que precisam acompanhar desempenho e distribuição de trabalho.

### Funcionalidades principais
- ✅ Cadastro de profissionais via role customizada do WordPress
- ✅ **Múltiplos tipos de profissional: Groomer, Banhista, Auxiliar, Recepção** (v1.5.0)
- ✅ **Flag de Freelancer para profissionais autônomos** (v1.5.0)
- ✅ **Filtros na listagem: por tipo, freelancer e status** (v1.5.0)
- ✅ **Geração automática de comissões ao confirmar pagamento** (v1.6.0)
- ✅ **Configuração de disponibilidade/turnos por profissional** (v1.7.0)
- ✅ Edição e exclusão de profissionais via interface
- ✅ Status ativo/inativo para profissionais
- ✅ Campo de telefone e percentual de comissão
- ✅ Vinculação de múltiplos profissionais por atendimento
- ✅ Relatórios de produtividade por profissional
- ✅ Exportação de relatórios em CSV
- ✅ Métricas: total de atendimentos, receita, ticket médio, comissão
- ✅ Dashboard individual do profissional com gráficos
- ✅ Agenda semanal do profissional
- ✅ Relatório de comissões a pagar
- ✅ Sistema de avaliações de clientes
- ✅ **Portal do Groomer com acesso via token (magic link)**
- ✅ **Gerenciamento de tokens de acesso no admin**
- ✅ CSS externo seguindo padrão visual minimalista do DPS

**Tipo**: Add-on (extensão do plugin base DPS)

**Versão atual**: 1.7.0

## Shortcodes disponíveis

| Shortcode | Descrição | Parâmetros |
|-----------|-----------|------------|
| `[dps_groomer_portal]` | Portal completo do groomer (dashboard, agenda, avaliações) | - |
| `[dps_groomer_login]` | Página de login/acesso do groomer | - |
| `[dps_groomer_dashboard]` | Dashboard individual do groomer | `groomer_id` |
| `[dps_groomer_agenda]` | Agenda semanal do groomer | `groomer_id` |
| `[dps_groomer_review]` | Formulário de avaliação | `groomer_id`, `appointment_id` |
| `[dps_groomer_reviews]` | Lista de avaliações | `groomer_id`, `limit` |

## Sistema de Acesso via Token (Magic Link)

### Como funciona

O groomer pode acessar seu portal sem precisar de senha. O administrador gera um link de acesso (magic link) que autentica o profissional automaticamente.

### Tipos de tokens

| Tipo | Duração | Uso |
|------|---------|-----|
| **Temporário** | 30 minutos | Uso único, ideal para envio por WhatsApp/SMS |
| **Permanente** | 10 anos | Válido até revogação, ideal para bookmark |

### Fluxo de autenticação

1. Admin acessa **Configurações DPS > Logins de Groomers**
2. Seleciona o tipo de token (temporário ou permanente)
3. Clica em **Gerar Link**
4. Copia o link gerado e envia ao groomer
5. Groomer clica no link e é autenticado automaticamente
6. Sessão válida por 24 horas

### Gerenciamento de tokens

- **Gerar**: Cria novo token para o groomer
- **Revogar**: Invalida um token específico
- **Revogar Todos**: Invalida todos os tokens ativos do groomer
- **Estatísticas**: Total gerado, usado, ativos, último acesso

## Localização e identificação

- **Diretório**: `add-ons/desi-pet-shower-groomers_addon/`
- **Slug**: `dps-groomers-addon`
- **Classe principal**: `DPS_Groomers_Addon`
- **Arquivo principal**: `desi-pet-shower-groomers-addon.php`

## Estrutura de arquivos

```
add-ons/desi-pet-shower-groomers_addon/
├── desi-pet-shower-groomers-addon.php   # Arquivo principal (~3000 linhas)
├── includes/
│   ├── class-dps-groomer-token-manager.php  # Gerenciador de tokens
│   └── class-dps-groomer-session-manager.php # Gerenciador de sessões
├── assets/
│   ├── css/
│   │   └── groomers-admin.css           # Estilos da interface (~1500 linhas)
│   └── js/
│       └── groomers-admin.js            # Interatividade, modal e validações
├── README.md                             # Esta documentação
└── uninstall.php                         # Limpeza na desinstalação
```

## Dependências e compatibilidade

### Dependências obrigatórias
- **DPS by PRObst Base**: v1.0.0 ou superior (obrigatório)
- **WordPress**: 6.0 ou superior
- **PHP**: 7.4 ou superior

### Integrações opcionais
- **Finance Add-on**: Para cálculo automático de receitas nos relatórios
- **Chart.js**: Carregado via CDN para gráficos de desempenho

## Dados armazenados

### Tabela de tokens

#### `{prefix}dps_groomer_tokens`
Armazena tokens de acesso dos groomers.

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| `id` | bigint | ID do token |
| `groomer_id` | bigint | ID do usuário groomer |
| `token_hash` | varchar(255) | Hash do token (password_hash) |
| `type` | varchar(50) | Tipo: 'login' ou 'permanent' |
| `created_at` | datetime | Data de criação |
| `expires_at` | datetime | Data de expiração |
| `used_at` | datetime | Data de uso (tokens temporários) |
| `revoked_at` | datetime | Data de revogação |
| `ip_created` | varchar(45) | IP de criação |
| `user_agent` | text | User agent de criação |

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
| `_dps_groomer_phone` | string | Telefone do profissional |
| `_dps_groomer_commission_rate` | float | Percentual de comissão (0-100) |
| `_dps_staff_type` | string | Tipo: 'groomer', 'banhista', 'auxiliar', 'recepcao' (v1.5.0) |
| `_dps_is_freelancer` | string | '1' se freelancer, '0' se não (v1.5.0) |
| `_dps_work_start` | string | Horário de início, ex: '08:00' (v1.7.0) |
| `_dps_work_end` | string | Horário de término, ex: '18:00' (v1.7.0) |
| `_dps_work_days` | array | Dias de trabalho: ['mon','tue','wed',...] (v1.7.0) |

### Metadados em agendamentos

| Meta Key | Tipo | Descrição |
|----------|------|-----------|
| `_dps_groomers` | array | IDs dos profissionais responsáveis pelo atendimento |
| `_dps_staff_commissions` | array | Dados das comissões geradas (v1.6.0) |
| `_dps_commission_generated` | bool | Flag de comissão já processada (v1.6.0) |
| `_dps_commission_date` | string | Data/hora da geração da comissão (v1.6.0) |

### Metadados em avaliações

| Meta Key | Tipo | Descrição |
|----------|------|-----------|
| `_dps_review_groomer_id` | int | ID do profissional avaliado |
| `_dps_review_rating` | int | Nota de 1 a 5 estrelas |
| `_dps_review_name` | string | Nome do avaliador (opcional) |
| `_dps_review_appointment_id` | int | ID do agendamento relacionado (opcional) |

## Changelog

### [1.7.0] - 2025-12-13

#### Added
- **Configuração de disponibilidade**: Horário de início/término e dias de trabalho por profissional
- Novos campos `_dps_work_start`, `_dps_work_end`, `_dps_work_days`
- Fieldset "Disponibilidade" no formulário de cadastro
- Grid visual de checkboxes para dias da semana
- CSS para componentes de disponibilidade

### [1.6.0] - 2025-12-13

#### Added
- **Geração automática de comissões**: Hook `dps_finance_booking_paid` consumido
- Método `generate_staff_commission()` para cálculo proporcional
- Metas `_dps_staff_commissions`, `_dps_commission_generated`, `_dps_commission_date`
- Hook `dps_groomers_commission_generated` para extensões
- Suporte a múltiplos profissionais com divisão proporcional

### [1.5.0] - 2025-12-13

#### Added
- **Múltiplos tipos de profissional**: Groomer, Banhista, Auxiliar, Recepção
- **Flag de Freelancer** para identificar profissionais autônomos
- **Filtros na listagem** por tipo, freelancer e status
- Migração automática de dados existentes para novos campos
- Badges visuais para tipo de profissional e freelancer
- Novos campos no formulário de cadastro e modal de edição
- Método estático `get_staff_types()` para reutilização
- Método estático `get_staff_type_label()` para labels traduzidos

#### Changed
- Renomeado "Groomer" para "Profissional" em labels genéricos
- Atualizado CSS com estilos para badges e filtros
- Atualizado JS para suportar novos campos no modal

### [1.4.0] - 2025-12-02

#### Added
- **Portal do Groomer** com shortcode `[dps_groomer_portal]`
- Acesso via magic link (token) sem necessidade de senha
- Gerenciador de tokens (`DPS_Groomer_Token_Manager`)
- Gerenciador de sessões (`DPS_Groomer_Session_Manager`)
- Tokens temporários (30min) e permanentes (10 anos)
- Aba "Logins de Groomers" no admin para gerenciamento
- Geração, revogação e listagem de tokens ativos
- Shortcode `[dps_groomer_login]` para página de acesso
- Navegação por abas no portal (Dashboard, Agenda, Avaliações)
- Suporte a autenticação via sessão independente do WP
- Tabela `dps_groomer_tokens` para armazenamento seguro

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
- ✅ Tokens armazenados como hash (password_hash)
- ✅ Sessões com proteção contra session fixation
- ✅ Cron job para limpeza de tokens expirados
