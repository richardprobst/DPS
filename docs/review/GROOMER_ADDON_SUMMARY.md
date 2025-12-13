# Groomers Add-on: Resumo Executivo

**Data**: 2025-12-13  
**Versão analisada**: 1.4.0  
**Autor da análise**: Copilot Coding Agent  
**Objetivo**: Análise profunda para expansão do add-on para suportar múltiplos tipos de profissionais (Groomers, Banhistas, Freelancers)

---

## 1. O que o add-on faz hoje

O **Groomers Add-on** é um módulo de gestão de profissionais de banho e tosa que oferece:

### 1.1 Funcionalidades Implementadas

| Funcionalidade | Status | Descrição |
|----------------|--------|-----------|
| Cadastro de groomers | ✅ | Via formulário, cria usuário WordPress com role `dps_groomer` |
| Listagem com ações | ✅ | Tabela com editar, excluir, toggle de status |
| Vinculação a agendamentos | ✅ | Select múltiplo no formulário de agendamento |
| Relatórios de produtividade | ✅ | Por período, com métricas e exportação CSV |
| Status ativo/inativo | ✅ | Groomers inativos não aparecem no select |
| Telefone e comissão | ✅ | Campos adicionais no cadastro/edição |
| Dashboard individual | ✅ | Shortcode `[dps_groomer_dashboard]` com gráficos |
| Agenda semanal | ✅ | Shortcode `[dps_groomer_agenda]` com navegação |
| Sistema de avaliações | ✅ | CPT `dps_groomer_review` com 5 estrelas |
| Portal do Groomer | ✅ | Acesso via magic link (token) |
| Gerenciamento de tokens | ✅ | Tokens temporários (30min) e permanentes |

### 1.2 Dados Armazenados

- **Role**: `dps_groomer` (WordPress)
- **Tabela**: `dps_groomer_tokens` (gerenciamento de magic links)
- **CPT**: `dps_groomer_review` (avaliações de clientes)
- **Meta em usuários**: `_dps_groomer_status`, `_dps_groomer_phone`, `_dps_groomer_commission_rate`
- **Meta em agendamentos**: `_dps_groomers` (array de IDs)

---

## 2. Onde o Groomer é usado

### 2.1 No Admin (Painel DPS)

| Local | Uso |
|-------|-----|
| Aba "Groomers" no shortcode `[dps_base]` | Cadastro, listagem, relatórios, comissões |
| Formulário de agendamento | Campo select múltiplo para escolher profissional |
| Aba "Logins de Groomers" em Configurações | Gerenciamento de tokens de acesso |

### 2.2 Na Agenda (add-on)

**Status atual**: ⚠️ **SEM INTEGRAÇÃO DIRETA**
- O add-on Agenda **não lê** o metadado `_dps_groomers`
- Não há filtros por groomer na visualização da agenda
- Não há validação de disponibilidade de groomer

### 2.3 Em Serviços (add-on)

**Status atual**: ⚠️ **SEM INTEGRAÇÃO**
- Não existe vínculo entre serviços e groomers
- Qualquer groomer pode ser selecionado para qualquer serviço

### 2.4 Em Finance (add-on)

**Status atual**: ⚡ **INTEGRAÇÃO PARCIAL**
- O Groomers Add-on **consulta** a tabela `dps_transacoes` para calcular receitas
- Usa `DPS_Finance_API::get_paid_total_for_appointments()` quando disponível
- **Não há lançamentos automáticos** de comissão ao concluir atendimentos

### 2.5 Em Stats (add-on)

**Status atual**: ❌ **SEM INTEGRAÇÃO**
- Não existem métricas por profissional no Stats Add-on
- Dashboard do Stats não considera dados de groomers

---

## 3. Pontos Fortes

1. **Código bem estruturado**: ~3000 linhas organizadas, DocBlocks completos
2. **Segurança implementada**: Nonces, capabilities, sanitização, escape
3. **Assets externos**: CSS e JS modulares seguindo padrão visual DPS
4. **Portal autônomo**: Sistema de magic links independente do WP login
5. **Múltiplos groomers por atendimento**: Suporte nativo
6. **Sistema de avaliações**: CPT próprio com média calculada
7. **Gráficos interativos**: Chart.js para dashboard
8. **Exportação CSV**: Relatórios exportáveis

---

## 4. Pontos Fracos

1. **Conceito limitado**: Apenas "Groomer", não suporta outros tipos de profissionais
2. **Sem flag de freelancer**: Não diferencia CLT de prestador de serviço
3. **Sem vínculo com serviços**: Qualquer profissional pode executar qualquer serviço
4. **Sem disponibilidade/turnos**: Groomers não têm horários configuráveis
5. **Sem integração com Agenda**: A visão da Agenda não filtra por groomer
6. **Comissão manual**: Relatório de comissões sem lançamento automático no Finance
7. **Arquivo único grande**: 3000+ linhas no arquivo principal
8. **Sem métricas no Stats**: Dashboard de estatísticas ignora dados de profissionais

---

## 5. Riscos Identificados

### 5.1 Riscos de Dados

| Risco | Severidade | Impacto |
|-------|------------|---------|
| Renomear "groomer" para "staff" | Média | Requer migração de role e metas |
| Adicionar campo `staff_type` | Baixa | Backfill de dados existentes |
| Múltiplos profissionais no mesmo atendimento | Baixa | Já suportado nativamente |

### 5.2 Riscos de Segurança

| Risco | Severidade | Impacto |
|-------|------------|---------|
| Tokens permanentes válidos por 10 anos | Média | Acesso prolongado se não revogado |
| Sessão PHP independente do WP | Baixa | Já tem proteção contra session fixation |
| Dados de telefone/comissão sem LGPD | Média | Considerar consentimento explícito |

### 5.3 Riscos de UX

| Risco | Severidade | Impacto |
|-------|------------|---------|
| Select de groomers sem agrupamento por tipo | Média | Confusão em petshops grandes |
| Sem validação de serviço x tipo de profissional | Alta | Banhista selecionado para tosa |
| Relatório de comissões sem detalhamento | Baixa | Dificuldade de conferência |

### 5.4 Riscos de Performance

| Risco | Severidade | Impacto |
|-------|------------|---------|
| Meta query com LIKE em `_dps_groomers` | Média | Lentidão com muitos agendamentos |
| Gráficos com muitos pontos | Baixa | Performance do Chart.js |

---

## 6. Oportunidades de Melhoria

### 6.1 Expansão de Conceito

- Renomear internamente para "Staff" ou "Colaborador"
- Introduzir `staff_type`: groomer, banhista, auxiliar, recepção
- Adicionar flag `is_freelancer` para controle diferenciado

### 6.2 Integração com Serviços

- Vincular profissional a tipos de serviço que executa
- Validar na seleção: "serviço X exige profissional habilitado"
- Dropdown agrupado por tipo no formulário de agendamento

### 6.3 Integração com Agenda

- Filtrar visualização da agenda por profissional
- Mostrar carga de trabalho por dia/profissional
- Bloquear horários quando profissional já alocado

### 6.4 Integração com Finance

- Lançamento automático de comissão ao concluir atendimento
- Modelos de remuneração: % comissão, valor fixo por serviço, diária
- Relatório de repasse por período exportável

### 6.5 Integração com Stats

- Métricas por profissional no dashboard de estatísticas
- Ranking de produtividade
- Ticket médio por profissional

---

## 7. Resumo das Fases Propostas

### Fase 1: Base de Dados + Compatibilidade (ALTA PRIORIDADE)
- Introduzir `staff_type` (groomer/banhista/outros)
- Introduzir `is_freelancer` (boolean)
- Migrar dados existentes sem quebrar compatibilidade
- UI mínima para novos campos

**Esforço**: P (1-2 dias)  
**Dependências**: Nenhuma

### Fase 2: Integração com Agenda/Serviços (ALTA PRIORIDADE)
- Vincular profissionais a tipos de serviço
- Validação no select de agendamento
- Agrupamento de profissionais por tipo
- Filtro por profissional na visualização da Agenda

**Esforço**: M (3-5 dias)  
**Dependências**: Fase 1

### Fase 3: Finance/Repasse (MÉDIA PRIORIDADE)
- Configuração de modelo de remuneração por profissional
- Lançamento automático de comissão ao concluir atendimento
- Relatório de repasse com exportação

**Esforço**: M (3-5 dias)  
**Dependências**: Fase 1, Finance Add-on

### Fase 4: Recursos Avançados (BAIXA PRIORIDADE)
- Disponibilidade/turnos/férias
- Métricas no Stats Add-on
- Suporte a múltiplos profissionais com roles diferentes (groomer + banhista)
- Notificações e relatórios avançados

**Esforço**: G (5-10 dias)  
**Dependências**: Fases 1, 2, 3, Stats Add-on

---

## 8. Próximos Passos Recomendados

1. **Revisar GROOMER_ADDON_DEEP_ANALYSIS.md** para detalhes técnicos e achados específicos
2. **Decidir sobre nomenclatura**: manter "groomer" ou renomear para "staff/colaborador"
3. **Priorizar fases** conforme necessidade do negócio
4. **Criar PRs separados** por fase para revisão incremental

---

## Referências

- [GROOMER_ADDON_DEEP_ANALYSIS.md](./GROOMER_ADDON_DEEP_ANALYSIS.md) - Análise técnica detalhada
- [ANALYSIS.md](../../ANALYSIS.md) - Arquitetura geral do DPS
- [GROOMERS_ADDON_ANALYSIS.md](../analysis/GROOMERS_ADDON_ANALYSIS.md) - Análise anterior (v1.1.0)
