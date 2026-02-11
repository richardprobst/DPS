# Política de Depreciação — Frontend Add-on

> **Versão**: 1.5.0 (Fase 6)
> **Última atualização**: 2026-02-11
> **Status**: Ativa

## 1. Objetivo

Esta política define **regras formais** para depreciação e eventual remoção dos add-ons legados cujas funcionalidades foram migradas para o `desi-pet-shower-frontend`. Nenhuma remoção será executada sem cumprir integralmente esta política.

---

## 2. Escopo

### 2.1 Add-ons cobertos por esta política

| Add-on legado | Módulo frontend correspondente | Status do módulo |
|---------------|-------------------------------|------------------|
| `desi-pet-shower-registration` | Registration (Fase 2) | Operacional (dual-run) |
| `desi-pet-shower-booking` | Booking (Fase 3) | Operacional (dual-run) |

### 2.2 Fora do escopo

- Plugin base (`desi-pet-shower-base`) — não é candidato a remoção.
- Outros add-ons — não possuem módulo correspondente no frontend.
- Hooks e contratos do sistema base — permanecem inalterados.

---

## 3. Janela mínima de depreciação

### 3.1 Regra geral

Toda depreciação segue o ciclo obrigatório:

```
[Módulo ativo em dual-run] → [Aviso de depreciação] → [Janela de observação] → [Remoção]
```

### 3.2 Durações mínimas

| Etapa | Duração mínima | Descrição |
|-------|----------------|-----------|
| Dual-run estável | 90 dias | Módulo frontend operando em produção sem incidentes P1/P2 |
| Aviso de depreciação | 60 dias | Comunicação formal publicada antes da remoção |
| Janela de observação pós-aviso | 30 dias | Período para feedback e ajustes finais |
| **Total mínimo** | **180 dias** | **6 meses entre ativação do dual-run e remoção** |

### 3.3 Extensões

- Se houver incidente P1/P2 durante qualquer etapa, o relógio **reinicia** a partir da resolução.
- Se houver feedback negativo fundamentado, estender a janela em 30 dias adicionais.

---

## 4. Processo de comunicação

### 4.1 Canais de comunicação

| Canal | Quando usar |
|-------|-------------|
| CHANGELOG.md (seção Deprecated) | Sempre — registro oficial de depreciação |
| ANALYSIS.md | Sempre — atualizar status do add-on |
| Release notes (GitHub) | Em cada release que avança depreciação |
| Aviso administrativo (admin_notices) | Opcional — para depreciações com impacto em admin |

### 4.2 Modelo de comunicação

Toda comunicação de depreciação **deve** incluir:

1. **O que está sendo depreciado**: nome do add-on, versão.
2. **O que substitui**: módulo do frontend add-on correspondente.
3. **Quando será removido**: data ou versão alvo.
4. **O que fazer**: instruções de migração (habilitar flag).
5. **Como reverter**: procedimento de rollback.

### 4.3 Template de depreciação para CHANGELOG.md

```markdown
#### Deprecated (Depreciado)
- **`desi-pet-shower-registration`**: Depreciado em favor do módulo Registration do `desi-pet-shower-frontend`. 
  Remoção prevista para vX.Y.Z (YYYY-MM-DD). 
  Migração: habilitar flag `registration` nas configurações do Frontend Add-on.
```

---

## 5. Critérios de aceite para remoção

A remoção de um add-on legado **só** pode prosseguir quando **todos** os critérios abaixo forem satisfeitos:

### 5.1 Critérios técnicos

| # | Critério | Verificação |
|---|----------|-------------|
| 1 | Módulo frontend operacional em produção por ≥ 90 dias | Log de ativação |
| 2 | Zero incidentes P1/P2 durante janela de observação | Relatório de incidentes |
| 3 | Todos os hooks do legado funcionais via módulo frontend | Teste funcional |
| 4 | Fluxo completo validado (cadastro ou agendamento) | Checklist de regressão |
| 5 | Sem referência direta ao add-on legado fora do frontend | grep no repositório |
| 6 | Telemetria confirma 100% do tráfego via módulo frontend | Contadores de uso |

### 5.2 Critérios de governança

| # | Critério | Verificação |
|---|----------|-------------|
| 1 | Aviso de depreciação publicado há ≥ 60 dias | CHANGELOG.md |
| 2 | Janela de observação pós-aviso completada (≥ 30 dias) | Calendário |
| 3 | Nenhum feedback negativo pendente | Issues/comunicações |
| 4 | Tag de backup criada | `git tag -l 'pre-removal-*'` |
| 5 | Runbook de rollback revisado | docs/implementation/FRONTEND_RUNBOOK.md |

### 5.3 Checklist consolidado

Ver `docs/qa/FRONTEND_REMOVAL_READINESS.md` para o checklist detalhado por módulo.

---

## 6. Procedimento formal de depreciação

### 6.1 Iniciar depreciação

1. Confirmar que o módulo frontend está operacional em produção por ≥ 90 dias.
2. Confirmar zero incidentes P1/P2 no período.
3. Adicionar entrada na seção **Deprecated** do CHANGELOG.md com data/versão alvo.
4. Atualizar ANALYSIS.md com status "Depreciado" para o add-on legado.
5. (Opcional) Adicionar `admin_notices` informativo no WordPress.

### 6.2 Durante a janela de depreciação (60 dias)

1. Monitorar feedback e incidentes.
2. Manter ambos os caminhos (dual-run) operacionais.
3. Resolver qualquer issue reportada antes de prosseguir.

### 6.3 Pré-remoção (último dia da janela)

1. Verificar **todos** os critérios da seção 5.
2. Criar tag de backup: `git tag -a pre-removal-{addon}-v{versão}`.
3. Comunicar decisão final.

### 6.4 Remoção

1. Seguir procedimento detalhado em `docs/qa/FRONTEND_REMOVAL_READINESS.md` seção 5.

### 6.5 Pós-remoção

1. Monitorar por 72h.
2. Verificar zero incidentes.
3. Atualizar documentação (ANALYSIS.md, CHANGELOG.md, addon-manager).
4. Comunicar conclusão.

---

## 7. Política de rollback

### 7.1 Durante dual-run (antes da remoção)

- Rollback instantâneo: desabilitar feature flag do módulo.
- Zero risco: legado reassume automaticamente.

### 7.2 Após remoção

- Rollback por restauração: reinstalar add-on legado a partir da tag de backup.
- Tempo estimado: < 5 minutos (instalação + ativação).
- Impacto: nenhum, pois os dados não foram migrados.

---

## 8. Exceções

- **Vulnerabilidade de segurança crítica no legado**: pode justificar remoção acelerada (mínimo 14 dias de aviso).
- **Pedido explícito do proprietário do sistema**: pode encurtar a janela de observação (mínimo 30 dias totais).
- Qualquer exceção **deve** ser documentada no CHANGELOG.md e ANALYSIS.md.

---

## 9. Responsabilidades

| Papel | Responsabilidade |
|-------|------------------|
| Mantenedor do Frontend Add-on | Propor depreciação, executar remoção |
| Mantenedor do Plugin Base | Aprovar remoção, verificar impacto |
| Proprietário do sistema | Decisão final, comunicação a stakeholders |

---

## 10. Revisão desta política

Esta política será revisada:
- Após cada remoção executada (lições aprendidas).
- A cada 6 meses (revisão periódica).
- Quando houver mudança significativa na arquitetura do sistema.
