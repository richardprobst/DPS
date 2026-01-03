# Groomers Add-on: Análise Profunda e Plano de Expansão

**Data**: 2025-12-13  
**Versão analisada**: 1.4.0  
**Autor da análise**: Copilot Coding Agent  
**Objetivo**: Expandir o add-on para suportar Groomers, Banhistas e Freelancers

---

## Índice

1. [Estrutura do Código](#1-estrutura-do-código)
2. [Modelo de Dados Atual](#2-modelo-de-dados-atual)
3. [Fluxos Atuais](#3-fluxos-atuais)
4. [Integrações Existentes](#4-integrações-existentes)
5. [Problemas e Dívidas Técnicas](#5-problemas-e-dívidas-técnicas)
6. [Proposta de Modelagem](#6-proposta-de-modelagem)
7. [Achados Detalhados](#7-achados-detalhados)
8. [Impacto nas Integrações](#8-impacto-nas-integrações)
9. [UX/UI Proposta](#9-uxui-proposta)
10. [Roadmap em Fases](#10-roadmap-em-fases)

---

## 1. Estrutura do Código

### 1.1 Arquivos e Classes

```
plugins/desi-pet-shower-groomers/
├── desi-pet-shower-groomers-addon.php   # 3087 linhas - Classe DPS_Groomers_Addon
├── includes/
│   ├── class-dps-groomer-token-manager.php  # 484 linhas - Gerenciamento de tokens
│   └── class-dps-groomer-session-manager.php # 247 linhas - Gerenciamento de sessões
├── assets/
│   ├── css/
│   │   └── groomers-admin.css           # 1509 linhas - Estilos completos
│   └── js/
│       └── groomers-admin.js            # Interatividade do modal e validações
├── README.md
└── uninstall.php                         # Limpeza na desinstalação
```

### 1.2 Métodos Principais da Classe DPS_Groomers_Addon

| Método | Linhas | Responsabilidade |
|--------|--------|------------------|
| `__construct()` | 70-114 | Registro de hooks (16 hooks) |
| `handle_token_authentication()` | 121-161 | Autenticação via magic link |
| `handle_groomer_actions()` | 801-828 | Dispatcher para CRUD |
| `handle_new_groomer_submission()` | 1240-1349 | Criação de novo groomer |
| `render_groomers_section()` | 1353-1478 | Seção principal no painel |
| `render_groomers_list()` | 1480-1595 | Tabela de listagem |
| `render_report_block()` | 1914-2125 | Relatório com filtros |
| `render_groomer_dashboard_shortcode()` | 2137-2499 | Dashboard individual |
| `render_groomer_agenda_shortcode()` | 2558-2769 | Agenda semanal |
| `render_groomer_portal_shortcode()` | 536-624 | Portal completo |
| `calculate_total_revenue()` | 2511-2546 | Integração com Finance API |

### 1.3 Hooks Consumidos

```php
// Navegação no painel base
add_action( 'dps_base_nav_tabs_after_history', ..., 15 );
add_action( 'dps_base_sections_after_history', ..., 15 );

// Integração com formulário de agendamento
add_action( 'dps_base_appointment_fields', ..., 10, 2 );
add_action( 'dps_base_after_save_appointment', ..., 10, 2 );

// Assets
add_action( 'wp_enqueue_scripts', ... );
add_action( 'admin_enqueue_scripts', ... );

// Configurações
add_action( 'dps_settings_nav_tabs', ..., 25 );
add_action( 'dps_settings_sections', ..., 25 );
```

### 1.4 Hooks Cron

```php
// Limpeza de tokens expirados (DPS_Groomer_Token_Manager)
add_action( 'dps_groomer_cleanup_tokens', [ $this, 'cleanup_expired_tokens' ] );
// Agendado: hourly
```

### 1.5 Shortcodes Expostos

| Shortcode | Método | Parâmetros |
|-----------|--------|------------|
| `[dps_groomer_portal]` | `render_groomer_portal_shortcode` | - |
| `[dps_groomer_login]` | `render_groomer_login_shortcode` | - |
| `[dps_groomer_dashboard]` | `render_groomer_dashboard_shortcode` | `groomer_id` |
| `[dps_groomer_agenda]` | `render_groomer_agenda_shortcode` | `groomer_id` |
| `[dps_groomer_review]` | `render_review_form_shortcode` | `groomer_id`, `appointment_id` |
| `[dps_groomer_reviews]` | `render_reviews_list_shortcode` | `groomer_id`, `limit` |

---

## 2. Modelo de Dados Atual

### 2.1 Role WordPress

```php
// Criada na ativação
add_role(
    'dps_groomer',
    __( 'Groomer DPS', 'dps-groomers-addon' ),
    [ 'read' => true ]
);
```

### 2.2 Tabela de Tokens

```sql
CREATE TABLE {prefix}dps_groomer_tokens (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    groomer_id bigint(20) unsigned NOT NULL,
    token_hash varchar(255) NOT NULL,
    type varchar(50) NOT NULL DEFAULT 'login',  -- 'login' ou 'permanent'
    created_at datetime NOT NULL,
    expires_at datetime NOT NULL,
    used_at datetime DEFAULT NULL,
    revoked_at datetime DEFAULT NULL,
    ip_created varchar(45) DEFAULT NULL,
    user_agent text DEFAULT NULL,
    PRIMARY KEY (id),
    KEY groomer_id (groomer_id),
    KEY token_hash (token_hash),
    KEY expires_at (expires_at),
    KEY type (type)
);
```

### 2.3 CPT de Avaliações

```php
register_post_type( 'dps_groomer_review', [
    'public'       => false,
    'show_ui'      => true,
    'show_in_menu' => false,
    'supports'     => [ 'title', 'editor' ],
] );
```

### 2.4 Metadados em Usuários (groomer)

| Meta Key | Tipo | Descrição | Uso |
|----------|------|-----------|-----|
| `_dps_groomer_status` | string | 'active' \| 'inactive' | Filtro no select |
| `_dps_groomer_phone` | string | Telefone do profissional | Contato |
| `_dps_groomer_commission_rate` | float | Percentual (0-100) | Relatório de comissões |

### 2.5 Metadados em Agendamentos

| Meta Key | Tipo | Descrição | Uso |
|----------|------|-----------|-----|
| `_dps_groomers` | array | IDs dos groomers responsáveis | Vínculo profissional-atendimento |

### 2.6 Metadados em Avaliações

| Meta Key | Tipo | Descrição |
|----------|------|-----------|
| `_dps_review_groomer_id` | int | ID do groomer avaliado |
| `_dps_review_rating` | int | Nota de 1 a 5 |
| `_dps_review_name` | string | Nome do avaliador (opcional) |
| `_dps_review_appointment_id` | int | ID do agendamento (opcional) |

---

## 3. Fluxos Atuais

### 3.1 Cadastro de Groomer

```
Admin abre aba "Groomers" → Preenche formulário →
  → Validação de nonce + capabilities →
  → Verificação de username/email únicos →
  → wp_insert_user() com role 'dps_groomer' →
  → Salva metas (_dps_groomer_phone, _dps_groomer_commission_rate) →
  → Mensagem de sucesso via DPS_Message_Helper
```

### 3.2 Vinculação a Agendamento

```
Admin cria/edita agendamento →
  → Hook 'dps_base_appointment_fields' renderiza select múltiplo →
  → Admin seleciona groomer(s) →
  → Hook 'dps_base_after_save_appointment' valida roles →
  → Salva array em meta '_dps_groomers'
```

### 3.3 Relatório de Produtividade

```
Admin seleciona groomer + período →
  → WP_Query com meta_query LIKE no '_dps_groomers' →
  → Calcula total via calculate_total_revenue() →
  → Exibe métricas (cards) + tabela de atendimentos →
  → Botão de exportação CSV
```

### 3.4 Acesso ao Portal do Groomer

```
Admin gera token → Envia link para groomer →
  → Groomer acessa URL com token →
  → handle_token_authentication() valida →
  → DPS_Groomer_Session_Manager::authenticate_groomer() →
  → Sessão PHP iniciada (24h de validade) →
  → Shortcode [dps_groomer_portal] renderiza dashboard
```

---

## 4. Integrações Existentes

### 4.1 Com Plugin Base

| Ponto de Integração | Tipo | Descrição |
|---------------------|------|-----------|
| `dps_base_nav_tabs_after_history` | Action | Adiciona aba "Groomers" |
| `dps_base_sections_after_history` | Action | Renderiza seção |
| `dps_base_appointment_fields` | Action | Adiciona select de groomers |
| `dps_base_after_save_appointment` | Action | Salva groomers selecionados |
| `dps_settings_nav_tabs` | Action | Adiciona aba "Logins de Groomers" |

### 4.2 Com Finance Add-on

```php
// Linha 2519-2522 de desi-pet-shower-groomers-addon.php
if ( class_exists( 'DPS_Finance_API' ) && method_exists( 'DPS_Finance_API', 'get_paid_total_for_appointments' ) ) {
    return (float) DPS_Finance_API::get_paid_total_for_appointments( $ids );
}
```

**Limitação**: Apenas leitura de dados. Não cria lançamentos de comissão.

### 4.3 Com Agenda Add-on

**Status**: ❌ SEM INTEGRAÇÃO

O add-on Agenda não possui:
- Filtro por groomer na visualização
- Leitura do meta `_dps_groomers`
- Validação de disponibilidade

### 4.4 Com Services Add-on

**Status**: ❌ SEM INTEGRAÇÃO

O add-on Services não possui:
- Vínculo serviço ↔ tipo de profissional
- Validação de habilitação

### 4.5 Com Stats Add-on

**Status**: ❌ SEM INTEGRAÇÃO

O add-on Stats não possui:
- Métricas por profissional
- Ranking de produtividade

---

## 5. Problemas e Dívidas Técnicas

### 5.1 Arquivo Principal Muito Grande

- **Local**: `desi-pet-shower-groomers-addon.php`
- **Linhas**: 3087
- **Problema**: Dificulta manutenção e navegação
- **Sugestão**: Modularizar em classes separadas:
  - `class-dps-groomer-admin.php` (CRUD)
  - `class-dps-groomer-reports.php` (Relatórios)
  - `class-dps-groomer-portal.php` (Shortcodes do portal)

### 5.2 Meta Query com LIKE

```php
// Linha 1936
[
    'key'     => '_dps_groomers',
    'value'   => '"' . $groomer_id . '"',
    'compare' => 'LIKE',
]
```

- **Problema**: Query não indexada, lenta em grandes volumes
- **Sugestão**: Considerar tabela relacional `dps_appointment_groomers`

### 5.3 Conceito Limitado a "Groomer"

- **Problema**: Não suporta outros tipos de profissionais
- **Impacto**: Pet shops com banhistas separados de groomers não conseguem diferenciar
- **Sugestão**: Introduzir meta `_dps_staff_type`

### 5.4 Sem Hook de Conclusão de Atendimento

- **Problema**: Não há ponto de extensão para lançar comissão automaticamente
- **Sugestão**: Consumir hook existente ou criar novo `dps_groomer_appointment_completed`

### 5.5 Cálculo de Comissão Manual

```php
// Linha 2224
$total_commission = $total_revenue * ( $commission_rate / 100 );
```

- **Problema**: Apenas exibe, não registra no Finance
- **Sugestão**: Integrar com `DPS_Finance_API` para criar lançamento

---

## 6. Proposta de Modelagem

### 6.1 Opção A: Manter Role + Adicionar Meta Type (RECOMENDADA)

**Implementação**:
- Manter role `dps_groomer` para todos os profissionais
- Adicionar meta `_dps_staff_type`: 'groomer' | 'banhista' | 'auxiliar' | 'recepcao'
- Adicionar meta `_dps_is_freelancer`: '0' | '1'

**Vantagens**:
- ✅ 100% compatível com dados existentes
- ✅ Migração simples: backfill com type='groomer', freelancer='0'
- ✅ Sem mudança de role ou capabilities
- ✅ Select pode agrupar por type

**Desvantagens**:
- ⚠️ Nome "groomer" permanece na role (visual ok se label mudar)

### 6.2 Opção B: Renomear Role para dps_staff

**Implementação**:
- Criar nova role `dps_staff`
- Migrar usuários de `dps_groomer` → `dps_staff`
- Remover role antiga
- Adicionar metas de type e freelancer

**Vantagens**:
- ✅ Nome semanticamente correto
- ✅ Preparado para futuras expansões

**Desvantagens**:
- ⚠️ Requer migração cuidadosa
- ⚠️ Pode quebrar código que verifica `in_array('dps_groomer', $roles)`
- ⚠️ Mais arriscado

### 6.3 Opção C: CPT de Colaboradores (NÃO RECOMENDADA)

**Implementação**:
- Criar CPT `dps_staff` em vez de usar usuários WordPress

**Vantagens**:
- ✅ Metadados mais flexíveis
- ✅ Sem limitações de user role

**Desvantagens**:
- ❌ Perde autenticação WordPress
- ❌ Perde painel de usuários
- ❌ Migração complexa
- ❌ Incompatível com portal via magic link

### 6.4 Recomendação Final

**Opção A** é a recomendada:
- Menor risco
- Menor esforço
- Maior compatibilidade
- Portal continua funcionando
- Basta adicionar campos e UI

---

## 7. Achados Detalhados

### Achado #1: Sem Validação de Tipo por Serviço

- **Título**: Qualquer profissional pode ser selecionado para qualquer serviço
- **Severidade**: Alta
- **Impacto**: Dono do negócio, equipe
- **Evidência**: `render_appointment_groomer_field()` linha 1595-1640
- **Sugestão**: 
  - Criar meta `_dps_staff_services` (array de service_ids que o profissional executa)
  - Filtrar select baseado em serviços selecionados
- **Risco de Regressão**: Médio (precisa validar UX)
- **Teste**: Criar agendamento de tosa, verificar se só groomers habilitados aparecem

### Achado #2: Meta Query LIKE Lenta

- **Título**: Consulta de agendamentos por groomer usa LIKE
- **Severidade**: Média
- **Impacto**: Performance em pet shops com alto volume
- **Evidência**: `get_groomer_appointments_count()` linha 945-963
- **Sugestão**: 
  - Tabela relacional `dps_appointment_groomers(appointment_id, groomer_id)`
  - Ou: índice customizado
- **Risco de Regressão**: Alto (requer migração de dados)
- **Teste**: Medir tempo de query com 10k agendamentos

### Achado #3: Sem Lançamento Automático de Comissão

- **Título**: Relatório de comissões é apenas visual, não cria transação
- **Severidade**: Média
- **Impacto**: Dono do negócio (controle financeiro manual)
- **Evidência**: `render_commissions_report()` linha 1780-1890
- **Sugestão**: 
  - Hook em `dps_appointment_status_changed` para status 'realizado'
  - Criar transação tipo 'despesa' com categoria 'comissao_groomer'
- **Risco de Regressão**: Baixo (nova funcionalidade)
- **Teste**: Finalizar atendimento, verificar lançamento no Finance

### Achado #4: Portal Não Verifica Status

- **Título**: Groomer inativo ainda acessa portal se tiver token válido
- **Severidade**: Baixa
- **Impacto**: Segurança
- **Evidência**: `get_authenticated_groomer_id()` linha 137-152 valida role, não status
- **Sugestão**: Adicionar verificação de `_dps_groomer_status`
- **Risco de Regressão**: Baixo
- **Teste**: Inativar groomer, tentar acessar portal

### Achado #5: Sem Integração com Agenda Add-on

- **Título**: Agenda não exibe nem filtra por groomer
- **Severidade**: Alta
- **Impacto**: Equipe operacional
- **Evidência**: `grep groomer agenda_addon` = sem resultados
- **Sugestão**: 
  - Adicionar filtro de groomer na visão da Agenda
  - Exibir nome do groomer na linha do atendimento
- **Risco de Regressão**: Baixo (nova funcionalidade)
- **Teste**: Abrir Agenda, verificar filtro e exibição

### Achado #6: Sem Vínculo Serviço ↔ Tipo de Profissional

- **Título**: Services Add-on não sabe quem pode executar cada serviço
- **Severidade**: Alta
- **Impacto**: UX, operação
- **Evidência**: Services Add-on não tem meta de staff_type por serviço
- **Sugestão**: 
  - Adicionar campo `required_staff_type` no serviço
  - Validar ao selecionar profissional
- **Risco de Regressão**: Médio
- **Teste**: Serviço de tosa exigir groomer

### Achado #7: Tokens Permanentes de 10 Anos

- **Título**: Tokens permanentes têm validade muito longa
- **Severidade**: Baixa
- **Impacto**: Segurança (se não revogado manualmente)
- **Evidência**: `PERMANENT_EXPIRATION_MINUTES = 60 * 24 * 365 * 10` linha 50
- **Sugestão**: 
  - Reduzir para 1 ano
  - Ou: adicionar renovação automática
- **Risco de Regressão**: Baixo
- **Teste**: Verificar se tokens antigos continuam funcionando

---

## 8. Impacto nas Integrações

### 8.1 Agenda Add-on

**Mudanças necessárias**:

1. **Leitura do meta `_dps_groomers`**:
   - Exibir nome do(s) profissional(is) na linha do atendimento
   - Tab 2 (Operação) ou Tab 3 (Detalhes)

2. **Filtro por profissional**:
   - Dropdown para filtrar agenda por groomer/banhista
   - Considerar filtro por tipo (todos, groomers, banhistas)

3. **Indicador de carga**:
   - Opcional: badge com número de atendimentos por profissional no dia

**Arquivos a modificar**:
- `trait-dps-agenda-renderer.php` (exibição)
- `desi-pet-shower-agenda-addon.php` (filtros)

### 8.2 Services Add-on

**Mudanças necessárias**:

1. **Campo no serviço: tipo de profissional requerido**:
   - Meta `_dps_service_required_staff_type`: 'any' | 'groomer' | 'banhista'
   - UI: select no formulário de edição de serviço

2. **Validação na API**:
   - `DPS_Services_API::can_staff_execute_service($staff_id, $service_id)`

**Arquivos a modificar**:
- `desi-pet-shower-services-addon.php` (formulário e API)

### 8.3 Finance Add-on

**Mudanças necessárias**:

1. **Lançamento automático de comissão**:
   - Hook: `dps_finance_booking_paid` ou novo hook de status
   - Criar transação tipo 'despesa', categoria 'comissao_profissional'
   - Campos: `groomer_id`, `appointment_id`, `valor`, `percentual`

2. **Relatório de repasse**:
   - View agrupada por profissional
   - Período selecionável
   - Exportação

**Arquivos a modificar**:
- `desi-pet-shower-finance-addon.php` (hooks e relatório)

### 8.4 Stats Add-on

**Mudanças necessárias**:

1. **Métricas por profissional**:
   - Atendimentos, receita, ticket médio por groomer/banhista
   - Comparativo entre profissionais

2. **Ranking**:
   - Top 5 profissionais por produtividade
   - Evolução mensal

**Arquivos a modificar**:
- `desi-pet-shower-stats-addon.php` (queries e renderização)

---

## 9. UX/UI Proposta

### 9.1 Formulário de Cadastro de Profissional

```
┌─────────────────────────────────────────────────────────────┐
│ ▶ Adicionar Novo Profissional                               │
├─────────────────────────────────────────────────────────────┤
│ ┌─ Dados de Acesso ───────────────────────────────────────┐ │
│ │ Usuário*        │ Email*                                │ │
│ │ [____________]  │ [______________________________]      │ │
│ │ Senha*          │ Telefone                              │ │
│ │ [____________]  │ [______________________________]      │ │
│ └─────────────────────────────────────────────────────────┘ │
│                                                             │
│ ┌─ Tipo e Vínculo ────────────────────────────────────────┐ │
│ │ Nome Completo*                                          │ │
│ │ [__________________________________________________]   │ │
│ │                                                         │ │
│ │ Tipo*                      │ Freelancer                 │ │
│ │ [▼ Groomer        ]        │ [ ] Sim, é freelancer      │ │
│ │                            │                            │ │
│ │ Comissão (%)               │                            │ │
│ │ [____] %                   │                            │ │
│ └─────────────────────────────────────────────────────────┘ │
│                                                             │
│ [ Criar Profissional ]                                      │
└─────────────────────────────────────────────────────────────┘
```

### 9.2 Tabela de Listagem

```
┌───────────┬───────────┬───────────┬────────────┬──────────┬────────────────┐
│ Nome      │ Tipo      │ Status    │ Freelancer │ Comissão │ Ações          │
├───────────┼───────────┼───────────┼────────────┼──────────┼────────────────┤
│ João Silva│ Groomer   │ ✓ Ativo   │ Não        │ 30%      │ ✏️ 🗑️ 📊      │
│ Maria     │ Banhista  │ ✓ Ativo   │ Sim        │ 25%      │ ✏️ 🗑️ 📊      │
│ Carlos    │ Groomer   │ ✗ Inativo │ Não        │ 30%      │ ✏️ 🗑️ 📊      │
└───────────┴───────────┴───────────┴────────────┴──────────┴────────────────┘

Filtros: [Tipo: Todos ▼] [Status: Ativos ▼] [Freelancer: Todos ▼]
```

### 9.3 Select no Agendamento

```
┌─ Profissionais Responsáveis ──────────────────────────────┐
│                                                           │
│  Profissional Principal*                                  │
│  [▼ Selecione...                              ]          │
│  ├── Groomers ─────────────────────────────────          │
│  │   ○ João Silva                                        │
│  │   ○ Pedro Santos                                      │
│  ├── Banhistas ────────────────────────────────          │
│  │   ○ Maria Costa                                       │
│  │   ○ Ana Paula                                         │
│  └────────────────────────────────────────────           │
│                                                           │
│  Profissional de Apoio (opcional)                        │
│  [▼ Nenhum                                    ]          │
│                                                           │
│  ⚠️ Serviço "Tosa Completa" requer Groomer habilitado    │
│                                                           │
└───────────────────────────────────────────────────────────┘
```

### 9.4 Validações de UX

| Cenário | Comportamento |
|---------|---------------|
| Serviço de tosa selecionado, nenhum groomer escolhido | Alerta: "Este serviço requer um Groomer" |
| Profissional inativo | Não aparece no select |
| Freelancer selecionado | Badge visual "Freelancer" exibido |
| Sem profissional cadastrado | Mensagem + link para cadastrar |

---

## 10. Roadmap em Fases

### Fase 1: Base de Dados + Compatibilidade

**Prioridade**: ALTA  
**Esforço**: P (1-2 dias)  
**Dependências**: Nenhuma

#### Itens

| Item | Descrição | Critério de Aceite |
|------|-----------|-------------------|
| F1.1 | Adicionar meta `_dps_staff_type` | Campo salvo/recuperado em cadastro/edição |
| F1.2 | Adicionar meta `_dps_is_freelancer` | Campo salvo/recuperado em cadastro/edição |
| F1.3 | Migração de dados existentes | Todos groomers atuais com type='groomer', freelancer='0' |
| F1.4 | UI no formulário de cadastro | Select de tipo, checkbox de freelancer |
| F1.5 | UI na tabela de listagem | Colunas de tipo e freelancer |
| F1.6 | Filtros na listagem | Filtro por tipo, status, freelancer |

**Benefícios**:
- Equipe: diferenciação clara de papéis
- Dono: visão de quem é CLT vs freelancer
- Sistema: base para fases seguintes

---

### Fase 2: Integração com Agenda/Serviços

**Prioridade**: ALTA  
**Esforço**: M (3-5 dias)  
**Dependências**: Fase 1

#### Itens

| Item | Descrição | Critério de Aceite |
|------|-----------|-------------------|
| F2.1 | Campo `required_staff_type` em serviços | Serviço pode exigir groomer/banhista/qualquer |
| F2.2 | Select agrupado por tipo no agendamento | Dropdown separado em seções |
| F2.3 | Validação de tipo x serviço | Alerta se serviço exige tipo não selecionado |
| F2.4 | Exibição de profissional na Agenda | Nome aparece na visualização |
| F2.5 | Filtro por profissional na Agenda | Dropdown para filtrar atendimentos |

**Benefícios**:
- Equipe: clareza de quem faz o quê
- Dono: menos erros de alocação
- UX: validação imediata

---

### Fase 3: Finance/Repasse

**Prioridade**: MÉDIA  
**Esforço**: M (3-5 dias)  
**Dependências**: Fase 1, Finance Add-on ativo

#### Itens

| Item | Descrição | Critério de Aceite |
|------|-----------|-------------------|
| F3.1 | Configuração de modelo de remuneração | % comissão, valor fixo, diária por profissional |
| F3.2 | Hook de conclusão de atendimento | Disparar quando status = 'realizado' |
| F3.3 | Lançamento automático de comissão | Transação criada no Finance |
| F3.4 | Diferenciação CLT x Freelancer | Regras diferentes de lançamento |
| F3.5 | Relatório de repasse | Agrupado por profissional, exportável |

**Benefícios**:
- Dono: controle financeiro automatizado
- Profissional: transparência de ganhos
- Contabilidade: dados estruturados

---

### Fase 4: Recursos Avançados

**Prioridade**: BAIXA  
**Esforço**: G (5-10 dias)  
**Dependências**: Fases 1, 2, 3, Stats Add-on (opcional)

#### Itens

| Item | Descrição | Critério de Aceite |
|------|-----------|-------------------|
| F4.1 | Disponibilidade/turnos por profissional | Configurar horários de trabalho |
| F4.2 | Bloqueios de agenda (férias/ausência) | Admin configura período de ausência |
| F4.3 | Métricas no Stats Add-on | Produtividade por profissional |
| F4.4 | Suporte a groomer + banhista no mesmo atendimento | Seleção de profissional principal e de apoio |
| F4.5 | Notificação ao profissional | WhatsApp/email de novos atendimentos |

**Benefícios**:
- Equipe: gestão de escala
- Dono: visão analítica
- Cliente: melhor experiência

---

## 11. Estimativas e Priorização

| Fase | Prioridade | Esforço | Dependências | Benefício |
|------|------------|---------|--------------|-----------|
| Fase 1 | Alta | P (1-2 dias) | Nenhuma | Base para expansão |
| Fase 2 | Alta | M (3-5 dias) | Fase 1 | UX e operação |
| Fase 3 | Média | M (3-5 dias) | Fase 1, Finance | Controle financeiro |
| Fase 4 | Baixa | G (5-10 dias) | Fases 1-3 | Funcionalidades avançadas |

**MVP Recomendado**: Fases 1 + 2 = 4-7 dias de desenvolvimento

---

## 12. Referências

- [GROOMER_ADDON_SUMMARY.md](./GROOMER_ADDON_SUMMARY.md) - Resumo executivo
- [ANALYSIS.md](../../ANALYSIS.md) - Arquitetura geral do DPS
- [GROOMERS_ADDON_ANALYSIS.md](../analysis/GROOMERS_ADDON_ANALYSIS.md) - Análise inicial (v1.0.0→v1.1.0)
- [AGENTS.md](../../AGENTS.md) - Diretrizes de desenvolvimento

### Evolução de Versões do Add-on

| Versão | Data | Principais Mudanças |
|--------|------|---------------------|
| v1.0.0 | - | Cadastro básico, vinculação a agendamentos, relatórios |
| v1.1.0 | 2025-12-02 | Assets externos, fieldsets, integração Finance API, corrigido uninstall.php |
| v1.2.0 | 2025-12-02 | Edição/exclusão de groomers, exportação CSV |
| v1.3.0 | 2025-12-02 | Dashboard individual, agenda semanal, avaliações, comissões, gráficos |
| v1.4.0 | 2025-12-02 | Portal do Groomer com magic links, gerenciamento de tokens, sessões independentes |

**Nota**: A análise anterior focou em melhorias de código e UX até v1.1.0. Esta análise foca na **expansão funcional** para suportar múltiplos tipos de profissionais.
