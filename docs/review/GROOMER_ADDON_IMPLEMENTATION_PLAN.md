# Groomers Add-on: Plano de Implementação por Fases

**Documento criado**: 2025-12-13  
**Objetivo**: Organizar a implementação das melhorias do Groomers Add-on em PRs incrementais

---

## Status das Fases

| Fase | Status | PR | Descrição |
|------|--------|-----|-----------|
| Fase 1 | ✅ **IMPLEMENTADA** | Este PR | Base de dados + UI para tipos e freelancer |
| Fase 2 | ⏳ Próxima | - | Integração com Agenda/Serviços |
| Fase 3 | 📋 Planejada | - | Finance/Repasse automático |
| Fase 4 | 📋 Planejada | - | Recursos avançados |

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

## Fase 2: Integração com Agenda/Serviços ⏳ PRÓXIMA

**Versão alvo**: 1.6.0  
**Esforço estimado**: 3-5 dias  
**Dependências**: Fase 1 (✅ completa)

### Itens Planejados

| Item | Descrição | Add-on Afetado |
|------|-----------|----------------|
| F2.1 | Campo `required_staff_type` em serviços | Services Add-on |
| F2.2 | Select agrupado por tipo no agendamento | Groomers Add-on |
| F2.3 | Validação de tipo x serviço | Groomers Add-on |
| F2.4 | Exibição de profissional na Agenda | Agenda Add-on |
| F2.5 | Filtro por profissional na Agenda | Agenda Add-on |

### Critérios de Aceite

1. Serviços podem exigir tipo específico de profissional (groomer/banhista/qualquer)
2. Select de profissional no agendamento agrupa por tipo
3. Alerta exibido se serviço requer tipo não selecionado
4. Nome do profissional aparece na visualização da Agenda
5. Filtro por profissional funciona na Agenda

### Benefícios

- **Equipe**: Clareza de quem faz o quê
- **Dono**: Menos erros de alocação
- **UX**: Validação imediata

---

## Fase 3: Finance/Repasse 📋 PLANEJADA

**Versão alvo**: 1.7.0  
**Esforço estimado**: 3-5 dias  
**Dependências**: Fase 1 (✅), Finance Add-on ativo

### Itens Planejados

| Item | Descrição | Add-on Afetado |
|------|-----------|----------------|
| F3.1 | Configuração de modelo de remuneração | Groomers Add-on |
| F3.2 | Hook de conclusão de atendimento | Groomers Add-on |
| F3.3 | Lançamento automático de comissão | Finance Add-on |
| F3.4 | Diferenciação CLT x Freelancer | Groomers/Finance |
| F3.5 | Relatório de repasse exportável | Groomers Add-on |

### Critérios de Aceite

1. Profissional pode ter modelo de remuneração: % comissão, valor fixo, diária
2. Ao concluir atendimento (status='realizado'), comissão é lançada automaticamente
3. Freelancers podem ter regras diferentes de lançamento
4. Relatório de repasse agrupado por profissional e exportável

### Benefícios

- **Dono**: Controle financeiro automatizado
- **Profissional**: Transparência de ganhos
- **Contabilidade**: Dados estruturados

---

## Fase 4: Recursos Avançados 📋 PLANEJADA

**Versão alvo**: 1.8.0+  
**Esforço estimado**: 5-10 dias  
**Dependências**: Fases 1, 2, 3, Stats Add-on (opcional)

### Itens Planejados

| Item | Descrição | Add-on Afetado |
|------|-----------|----------------|
| F4.1 | Disponibilidade/turnos por profissional | Groomers Add-on |
| F4.2 | Bloqueios de agenda (férias/ausência) | Groomers Add-on |
| F4.3 | Métricas no Stats Add-on | Stats Add-on |
| F4.4 | Suporte a múltiplos profissionais por atendimento | Groomers Add-on |
| F4.5 | Notificação ao profissional | Push/Communications |

### Critérios de Aceite

1. Admin pode configurar horários de trabalho por profissional
2. Admin pode bloquear períodos de ausência (férias, folgas)
3. Stats exibe métricas de produtividade por profissional
4. Agendamento pode ter profissional principal + apoio
5. Profissional recebe notificação de novo atendimento

### Benefícios

- **Equipe**: Gestão de escala
- **Dono**: Visão analítica
- **Cliente**: Melhor experiência

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
