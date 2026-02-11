# Checklist de Prontidão para Remoção Futura — Frontend Add-on

> **Versão**: 1.3.0 (Fase 5)
> **Última atualização**: 2026-02-11
> **Status**: ⏳ Em preparação (nenhuma remoção nesta etapa)

## 1. Objetivo

Este documento define **critérios objetivos** para que a remoção futura dos add-ons legados (registration, booking) possa ser feita de forma segura. Nenhuma remoção será executada nesta fase — apenas a preparação.

---

## 2. Critérios por módulo

### 2.1 Módulo Registration → remoção de `desi-pet-shower-registration`

| # | Critério | Status | Evidência necessária |
|---|----------|--------|---------------------|
| 1 | Módulo frontend operacional com flag habilitado em produção | ⬜ Pendente | Log de ativação + período de observação |
| 2 | Zero incidentes P1/P2 durante janela de observação (mínimo 30 dias) | ⬜ Pendente | Relatório de incidentes |
| 3 | Hooks `dps_registration_after_fields` e `dps_registration_after_client_created` funcionais | ⬜ Pendente | Teste funcional: cadastro com indicação Loyalty |
| 4 | Fluxo completo validado: cadastro → email welcome → confirmação | ⬜ Pendente | Checklist de regressão |
| 5 | Sem uso direto de `DPS_Registration_Addon` fora do módulo frontend | ⬜ Pendente | `grep -rn 'DPS_Registration_Addon' plugins/` (exceto frontend) |
| 6 | Telemetria confirma 100% do tráfego via módulo frontend | ⬜ Pendente | Logs de uso |
| 7 | Migração do processamento de forms para módulo frontend (pós dual-run) | ⬜ Pendente | Código do módulo processa forms diretamente |
| 8 | Settings de cadastro migradas para módulo frontend | ⬜ Pendente | Aba de configurações com campos do cadastro |

### 2.2 Módulo Booking → remoção de `desi-pet-shower-booking`

| # | Critério | Status | Evidência necessária |
|---|----------|--------|---------------------|
| 1 | Módulo frontend operacional com flag habilitado em produção | ⬜ Pendente | Log de ativação + período de observação |
| 2 | Zero incidentes P1/P2 durante janela de observação (mínimo 30 dias) | ⬜ Pendente | Relatório de incidentes |
| 3 | Hook `dps_base_after_save_appointment` funcional com 7+ consumidores | ⬜ Pendente | Teste funcional por consumidor |
| 4 | Fluxo completo validado: agendamento → confirmação → hooks | ⬜ Pendente | Checklist de regressão |
| 5 | Sem uso direto de `DPS_Booking_Addon` fora do módulo frontend | ⬜ Pendente | `grep -rn 'DPS_Booking_Addon' plugins/` (exceto frontend) |
| 6 | Telemetria confirma 100% do tráfego via módulo frontend | ⬜ Pendente | Logs de uso |
| 7 | Migração do processamento de agendamento para módulo frontend (pós dual-run) | ⬜ Pendente | Código do módulo processa agendamento diretamente |
| 8 | Tela de confirmação migrada para módulo frontend | ⬜ Pendente | Template de confirmação no frontend add-on |

---

## 3. Critérios transversais (obrigatórios antes de qualquer remoção)

| # | Critério | Status | Evidência |
|---|----------|--------|-----------|
| 1 | Fase 5 completa (consolidação e documentação) | ✅ Completo | Este documento |
| 2 | Fase 6 completa (política de depreciação) | ⬜ Pendente | `FRONTEND_DEPRECATION_POLICY.md` |
| 3 | Guia de rollout documentado e testado | ✅ Completo | `docs/implementation/FRONTEND_ROLLOUT_GUIDE.md` |
| 4 | Runbook de incidentes documentado | ✅ Completo | `docs/implementation/FRONTEND_RUNBOOK.md` |
| 5 | Matriz de compatibilidade validada | ✅ Completo | `docs/qa/FRONTEND_COMPATIBILITY_MATRIX.md` |
| 6 | Comunicação formal de depreciação enviada | ⬜ Pendente | Registro de comunicação |
| 7 | Janela mínima de depreciação respeitada (TBD) | ⬜ Pendente | Definir na Fase 6 |

---

## 4. Riscos de remoção e mitigação

| Risco | Probabilidade | Impacto | Mitigação |
|-------|---------------|---------|-----------|
| Código que referencia legado diretamente (não via hooks) | Média | Alto | Inventário completo com grep antes de remover |
| Add-ons de terceiros dependendo do legado | Baixa | Alto | Comunicação prévia + janela de depreciação |
| Perda de funcionalidade não mapeada | Média | Alto | Teste de regressão completo por fluxo |
| Rollback não possível após remoção | Alta | Alto | Manter tag/branch de backup do legado |
| Quebra de configurações persistidas | Baixa | Médio | Migração lazy de options + leitura dupla |

---

## 5. Procedimento de remoção (quando aprovado)

> **IMPORTANTE**: Não executar até que todos os critérios acima estejam ✅.

### 5.1 Pré-remoção

1. Criar tag de backup: `git tag -a pre-removal-registration-v1.X.X`
2. Confirmar que todos os critérios do módulo estão ✅
3. Comunicar equipe sobre a remoção planejada
4. Preparar rollback plan (restaurar plugin do tag)

### 5.2 Remoção

1. Remover diretório do add-on legado
2. Atualizar `ANALYSIS.md` (marcar como removido)
3. Atualizar `CHANGELOG.md` (seção Removed)
4. Atualizar addon-manager (remover registro)
5. Validar que o frontend add-on opera independentemente
6. Rodar suite de regressão completa

### 5.3 Pós-remoção

1. Monitorar logs por 72h
2. Verificar zero incidentes
3. Comunicar confirmação de remoção
4. Atualizar este checklist com data e resultado

---

## 6. Decisão atual

**Nenhuma remoção será feita nesta etapa.** Este checklist existe para:
- Documentar o que é necessário antes de remover.
- Guiar decisões futuras com base em evidência.
- Evitar remoções precipitadas sem critérios objetivos.

A remoção será tratada em programa posterior (Fase 6+), com base nos critérios e evidências aqui definidos.
