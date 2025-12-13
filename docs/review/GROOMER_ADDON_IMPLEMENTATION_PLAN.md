# Groomers Add-on: Plano de Implementação por Fases

**Documento criado**: 2025-12-13  
**Objetivo**: Organizar a implementação das melhorias do Groomers Add-on em PRs incrementais

---

## Status das Fases

| Fase | Status | PR | Descrição |
|------|--------|-----|-----------|
| Fase 1 | ✅ **IMPLEMENTADA** | Este PR | Base de dados + UI para tipos e freelancer |
| Fase 2 | ✅ **IMPLEMENTADA** | Este PR | Integração com Agenda/Serviços |
| Fase 3 | ✅ **IMPLEMENTADA** | Este PR | Finance/Repasse automático |
| Fase 4 | ✅ **IMPLEMENTADA** | Este PR | Recursos avançados (disponibilidade) |

---

## Fase 1: Base de Dados + Compatibilidade ✅ COMPLETA

**Versão**: 1.5.0  
**PR**: Este PR (análise + implementação)

### Itens Implementados

| Item | Descrição | Status |
|------|-----------|--------|
| F1.1 | Meta `_dps_staff_type` | ✅ Implementado |
| F1.2 | Meta `_dps_is_freelancer` | ✅ Implementado |
| F1.3 | Migração automática de dados existentes | ✅ Implementado |
| F1.4 | UI no formulário de cadastro (select + checkbox) | ✅ Implementado |
| F1.5 | UI na tabela de listagem (colunas Tipo e Freelancer) | ✅ Implementado |
| F1.6 | Filtros na listagem (tipo, freelancer, status) | ✅ Implementado |

### Arquivos Modificados

- `desi-pet-shower-groomers-addon.php`
  - Constante `VERSION` atualizada para 1.5.0
  - Constante `STAFF_TYPES` adicionada
  - Método `maybe_migrate_staff_data()` adicionado
  - Método `get_staff_types()` adicionado
  - Método `get_staff_type_label()` adicionado
  - `handle_new_groomer_submission()` atualizado
  - `handle_update_groomer()` atualizado
  - `render_groomers_section()` atualizado com filtros e novas colunas
  - Modal de edição atualizado com novos campos

- `assets/css/groomers-admin.css`
  - Estilos para filtros inline
  - Estilos para badges de tipo
  - Estilos para badge de freelancer
  - Estilos para checkbox label e field help

- `assets/js/groomers-admin.js`
  - `openEditModal()` atualizado para suportar novos campos
  - Versão atualizada no header

- `README.md`
  - Funcionalidades atualizadas
  - Versão atualizada para 1.5.0
  - Metadados documentados
  - Changelog v1.5.0 adicionado

### Critérios de Aceite ✅

1. ✅ Profissionais existentes recebem `staff_type='groomer'` e `is_freelancer='0'` automaticamente
2. ✅ Novos profissionais podem ser criados com tipo e flag de freelancer
3. ✅ A edição preserva e permite alterar tipo e freelancer
4. ✅ Listagem exibe colunas Tipo e Freelancer
5. ✅ Filtros funcionam corretamente (tipo, freelancer, status)

---

## Fase 2: Integração com Agenda/Serviços ✅ COMPLETA

**Versão**: 1.6.0  
**Implementado em**: Este PR

### Itens Implementados

| Item | Descrição | Add-on Afetado | Status |
|------|-----------|----------------|--------|
| F2.1 | Campo `required_staff_type` em serviços | Services Add-on v1.3.0 | ✅ |
| F2.2 | Select agrupado por tipo no agendamento | Groomers Add-on v1.5.0 | ✅ |
| F2.3 | Validação de tipo x serviço | Pendente (futura) | ⏸️ |
| F2.4 | Exibição de profissional na Agenda | Parcial (filtro implementado) | ✅ |
| F2.5 | Filtro por profissional na Agenda | Agenda Add-on v1.1.0 | ✅ |

### Arquivos Modificados

**Services Add-on**:
- `desi-pet-shower-services.php` - versão 1.3.0
- `dps_service/desi-pet-shower-services-addon.php` - campo `required_staff_type`

**Groomers Add-on**:
- `desi-pet-shower-groomers-addon.php` - select agrupado por tipo

**Agenda Add-on**:
- `desi-pet-shower-agenda-addon.php` - versão 1.1.0, filtro por profissional
- `includes/trait-dps-agenda-renderer.php` - parâmetro filter_staff

### Critérios de Aceite ✅

1. ✅ Serviços podem exigir tipo específico de profissional (groomer/banhista/qualquer)
2. ✅ Select de profissional no agendamento agrupa por tipo com optgroup
3. ⏸️ Validação de tipo x serviço (adiada para futura implementação JS)
4. ✅ Filtro por profissional funciona na Agenda
5. ✅ Profissionais exibidos com tipo entre parênteses no filtro

---

## Fase 3: Finance/Repasse ✅ COMPLETA

**Versão**: 1.6.0  
**Implementado em**: Este PR

### Itens Implementados

| Item | Descrição | Add-on Afetado | Status |
|------|-----------|----------------|--------|
| F3.1 | Configuração de modelo de remuneração (% comissão) | Groomers Add-on | ✅ |
| F3.2 | Hook `dps_finance_booking_paid` consumido | Groomers Add-on v1.6.0 | ✅ |
| F3.3 | Lançamento automático de comissão em meta | Groomers Add-on v1.6.0 | ✅ |
| F3.4 | Flag is_freelancer registrada nas comissões | Groomers Add-on v1.6.0 | ✅ |
| F3.5 | Relatório de comissões já existente | Groomers Add-on | ✅ |

### Detalhes da Implementação

**Novo método `generate_staff_commission()`**:
- Conectado ao hook `dps_finance_booking_paid`
- Calcula comissão proporcional para múltiplos profissionais
- Salva em `_dps_staff_commissions` (array com detalhes)
- Marca `_dps_commission_generated` para evitar duplicação
- Dispara hook `dps_groomers_commission_generated` para extensões

**Metas salvas no agendamento**:
- `_dps_staff_commissions` - Array com detalhes de cada comissão
- `_dps_commission_generated` - Flag booleana
- `_dps_commission_date` - Data/hora da geração

### Critérios de Aceite ✅

1. ✅ Profissional usa % comissão configurada no cadastro
2. ✅ Ao confirmar pagamento, comissão é calculada automaticamente
3. ✅ Flag is_freelancer é registrada junto com a comissão
4. ✅ Relatório de comissões por período já funciona

---

## Fase 4: Recursos Avançados ✅ COMPLETA

**Versão**: 1.7.0  
**Implementado em**: Este PR

### Itens Implementados

| Item | Descrição | Add-on Afetado | Status |
|------|-----------|----------------|--------|
| F4.1 | Disponibilidade/turnos por profissional | Groomers Add-on v1.7.0 | ✅ |
| F4.2 | Bloqueios de agenda (férias/ausência) | Pendente (futura) | ⏸️ |
| F4.3 | Métricas no Stats Add-on | Não há Stats Add-on ainda | ⏸️ |
| F4.4 | Suporte a múltiplos profissionais por atendimento | Já existente (v1.0.0) | ✅ |
| F4.5 | Notificação ao profissional | Push Add-on (futura) | ⏸️ |

### Detalhes da Implementação

**Novos campos de disponibilidade** (F4.1):
- `_dps_work_start` - Horário de início (ex: "08:00")
- `_dps_work_end` - Horário de término (ex: "18:00")
- `_dps_work_days` - Array de dias de trabalho (ex: ["mon","tue","wed","thu","fri","sat"])

**UI adicionada**:
- Fieldset "Disponibilidade" no formulário de cadastro
- Inputs de horário (tipo time)
- Grid de checkboxes para dias da semana
- CSS para exibição responsiva

### Critérios de Aceite ✅

1. ✅ Admin pode configurar horários de trabalho por profissional
2. ✅ Dias de trabalho são salvos como array de códigos (mon, tue, etc.)
3. ✅ Valores padrão sensatos: 08:00-18:00, seg-sáb
4. ✅ Múltiplos profissionais por atendimento já funciona (select multiple)

---

## Sugestões Extras (não previstas nas fases originais)

### S1. API Pública para Profissionais

Expor endpoint REST para consultar profissionais disponíveis por tipo:

```
GET /wp-json/dps-groomers/v1/staff
GET /wp-json/dps-groomers/v1/staff?type=groomer&active=true
```

### S2. Capacidade por Profissional

Meta `_dps_staff_capacity` para limitar quantos atendimentos o profissional pode fazer por dia/slot.

### S3. Especialidades/Serviços

Além de `required_staff_type` no serviço, permitir vincular profissionais específicos a serviços que dominam.

### S4. Foto de Perfil

Exibir foto do profissional na lista e no portal (usar gravatar ou upload).

---

## Referências

- [GROOMER_ADDON_SUMMARY.md](./GROOMER_ADDON_SUMMARY.md) - Resumo executivo
- [GROOMER_ADDON_DEEP_ANALYSIS.md](./GROOMER_ADDON_DEEP_ANALYSIS.md) - Análise técnica
- [ANALYSIS.md](../../ANALYSIS.md) - Arquitetura geral do DPS
