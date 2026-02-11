# Plano em fases para criação do Add-on FRONTEND

## 1) Contexto e objetivo

Este documento define um plano **amplo, incremental e seguro** para criar o novo add-on `desi-pet-shower-frontend`, consolidando experiências frontend hoje distribuídas entre plugin base e add-ons de cadastro (`desi-pet-shower-registration`) e agendamento (`desi-pet-shower-booking`).

A decisão deste plano é:
- **não remover código legado nesta etapa inicial**;
- construir o novo add-on com **compatibilidade retroativa**;
- preparar, desde já, a trilha de evidências para remoção futura sem risco.

### Documentos relacionados

| Documento | Propósito |
|-----------|-----------|
| `AGENT_ENGINEERING_PLAYBOOK.md` | Princípios de engenharia, DoD e processo de implementação |
| `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` | Instruções de design frontend M3 Expressive |
| `docs/visual/VISUAL_STYLE_GUIDE.md` | Tokens, paleta, tipografia e componentes CSS |
| `ANALYSIS.md` | Arquitetura, hooks e contratos do sistema |
| `AGENTS.md` | Regras globais (MUST / ASK BEFORE / PREFER) |

## 2) Resultado esperado ao final do programa

Ao concluir todas as fases deste roadmap, o projeto terá:
- add-on `FRONTEND` operacional com arquitetura modular;
- compatibilidade com shortcodes e fluxos atuais;
- documentação de contratos e integrações centralizada;
- rollout controlado por fases (feature flags e fallback);
- critérios objetivos para remoção futura de legado.

> **Importante:** este plano foi desenhado para reduzir risco de quebra em produção e manter a continuidade operacional dos clientes atuais.

---

## 3) Princípios de implementação

1. **Compatibilidade primeiro**
   - Toda migração deve preservar comportamento de shortcodes/hooks existentes durante a transição.

2. **Dual-run e rollback rápido**
   - Cada módulo migrado roda com fallback para legado via feature flag.

3. **Contratos explícitos**
   - APIs de integração (hooks, shortcodes, opções) devem ser documentadas antes e depois da migração.

4. **Mudanças pequenas e verificáveis**
   - Entregas incrementais com critérios de aceite e validação funcional por fase.

5. **Observabilidade desde o início**
   - Logs e métricas de uso para guiar decisões de estabilização e remoção futura.

---

## 4) Escopo funcional da consolidação FRONTEND

### 4.1 Em escopo nesta iniciativa
- Experiências frontend relacionadas a:
  - formulário de cadastro;
  - formulário de agendamento;
  - página de configurações frontend e abas associadas;
  - assets e templates correlatos dessas experiências.

### 4.2 Fora de escopo (por ora)
- Remoção de funções, classes ou add-ons legados.
- Alterações de schema em tabelas compartilhadas.
- Mudanças de contrato sem camada de compatibilidade.

---

## 5) Inventário inicial (baseline obrigatório)

Antes de codar, deve existir inventário consolidado com:

- **Shortcodes atuais** usados nos fluxos-alvo.
- **Hooks de integração** consumidos e expostos (incluindo legados/deprecados).
- **Options/metadados** persistidos por cada fluxo.
- **Entradas de segurança** (nonces, capabilities, sanitização, escapes).
- **Fluxos operacionais críticos** com passo a passo e resultado esperado.
- **Dependências cruzadas** entre base e add-ons envolvidos.

### 5.1 Entregável da fase de inventário
Criar matriz de contratos em formato tabular contendo, no mínimo:

| Tipo | Nome | Origem atual | Consumidores | Risco de quebra | Estratégia de compatibilidade |
|------|------|--------------|--------------|-----------------|-------------------------------|
| Shortcode | `...` | plugin X | páginas Y | Alto/Médio/Baixo | Alias/Wrapper/Manter |
| Hook | `...` | plugin X | addon Y | Alto/Médio/Baixo | Bridge/Deprecated |
| Option | `...` | plugin X | módulo Y | Alto/Médio/Baixo | Leitura dupla/Migração lazy |

---

## 6) Arquitetura-alvo do add-on FRONTEND

### 6.1 Estrutura recomendada

```text
plugins/desi-pet-shower-frontend/
├── desi-pet-shower-frontend-addon.php
├── includes/
│   ├── class-dps-frontend-addon.php
│   ├── class-dps-frontend-module-registry.php
│   ├── class-dps-frontend-compatibility.php
│   ├── class-dps-frontend-feature-flags.php
│   ├── modules/
│   │   ├── class-dps-frontend-registration-module.php
│   │   ├── class-dps-frontend-booking-module.php
│   │   └── class-dps-frontend-settings-module.php
│   └── support/
│       ├── class-dps-frontend-assets.php
│       ├── class-dps-frontend-logger.php
│       └── class-dps-frontend-request-guard.php
├── templates/
└── assets/
```

### 6.2 Cabeçalho do plugin (padrão do projeto)

```php
/**
 * Plugin Name: desi.pet by PRObst – Frontend Add-on
 * Plugin URI:  https://www.probst.pro
 * Description: Consolida experiências frontend (cadastro, agendamento, configurações) em add-on modular.
 * Version:     1.0.0
 * Author:      PRObst
 * Text Domain: dps-frontend-addon
 * Domain Path: /languages
 * Requires at least: 6.9
 * Requires PHP: 8.4
 * Update URI: https://github.com/richardprobst/DPS
 * License:     GPL-2.0+
 */
```

### 6.3 Responsabilidades macro
- `Addon`: bootstrap, i18n, ciclo de vida.
- `Registry`: habilita/desabilita módulos e conecta hooks.
- `Compatibility`: camada de aliases/wrappers para legado.
- `Feature Flags`: controle de rollout por módulo/fluxo.
- `Modules/*`: regra de negócio e renderização por domínio.
- `Support/*`: segurança, assets, logging e utilitários.

### 6.4 Arquitetura de assets e padrão visual M3

O add-on FRONTEND é intrinsecamente visual e **deve** seguir o padrão **Material 3 Expressive** do projeto desde a Fase 1. Referências obrigatórias:

- `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` — metodologia, contextos de uso, checklist
- `docs/visual/VISUAL_STYLE_GUIDE.md` — tokens, paleta, componentes CSS

#### Estrutura de assets recomendada

```text
assets/
├── css/
│   └── frontend-addon.css      /* Estilos do add-on — sem hex literais, via var(--dps-*) */
└── js/
    └── frontend-addon.js       /* Vanilla JS, IIFE, 'use strict' */
```

#### Requisitos M3 para o add-on

1. **Importar `dps-design-tokens.css`** como dependência (já fornecido pelo plugin base).
2. **Cores exclusivamente via tokens** — `var(--dps-color-*)`, sem hex/rgba literais.
3. **Formas via tokens** — `var(--dps-shape-*)`, sem `border-radius` literal.
4. **Tipografia** — escala M3 (`var(--dps-typescale-*)`), pesos 400 e 500 apenas.
5. **Botões pill** — classe `.dps-submit-btn` (M3 pill button) para ações primárias.
6. **Enqueue condicional** — assets carregados apenas nas páginas onde o add-on atua.
7. **`prefers-reduced-motion`** — respeitado automaticamente via tokens globais.
8. **Perfil por contexto** — Standard (admin) vs. Expressive (portal/público).

> Consultar o checklist completo em `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md`, seção 14.

---

## 7) Estratégia de compatibilidade

### 7.1 Shortcodes
- Manter shortcodes existentes funcionais na transição.
- Se houver shortcode canônico novo, publicar alias dos antigos para ele.
- Logar uso de shortcode legado para telemetria de desativação futura.

### 7.2 Hooks
- Não quebrar assinaturas atuais.
- Se necessário evoluir contrato, criar novo hook e manter bridge para o antigo.
- Documentar depreciação apenas quando houver substituto estável.

### 7.3 Persistência de dados
- Evitar migração destrutiva imediata.
- Preferir compatibilidade por leitura dupla (chave nova + chave antiga) até estabilização.

---

## 8) Plano de execução por fases

## Fase 0 — Governança, discovery e baseline

**Objetivo:** reduzir incerteza técnica e mapear riscos antes da implementação.

### Entregas
- Matriz de contratos e dependências.
- Catálogo de fluxos críticos com critérios de aceite.
- Matriz de risco por módulo.
- Estratégia de rollback por feature flag.

### Critério de saída
- Nenhuma dependência crítica “desconhecida”.
- Contratos públicos mapeados e revisados.

---

## Fase 1 — Fundação do add-on FRONTEND

**Objetivo:** criar esqueleto do add-on sem impacto funcional visível.

### Entregas
- Plugin principal com bootstrap e textdomain (cabeçalho conforme seção 6.2).
- Registry modular e feature flags.
- Infra de logging/diagnóstico.
- Camada de compatibilidade vazia preparada.
- Estrutura de assets com dependência de `dps-design-tokens.css` (padrão M3).
- Documentação da seção FRONTEND no `ANALYSIS.md`.

### Critério de saída
- Add-on ativa sem erro e sem interferir no fluxo atual.
- Assets M3 carregam condicionalmente sem conflito.

---

## Fase 2 — Migração do módulo Cadastro (piloto)

**Objetivo:** validar estratégia com módulo de risco controlado.

### Entregas
- Módulo `registration` no FRONTEND.
- Wrapper/alias para shortcode legado correspondente.
- Fallback para legado por flag.
- Testes funcionais do fluxo completo de cadastro.

### Critério de saída
- Paridade funcional comprovada em homologação.
- Rollback validado.

---

## Fase 3 — Migração do módulo Agendamento

**Objetivo:** transferir fluxo de agendamento com dual-run.

### Entregas
- Módulo `booking` no FRONTEND.
- Compatibilidade com shortcode e integrações atuais.
- Enqueue condicional de assets no novo módulo.
- Checklist de regressão do fluxo de agendamento.

### Critério de saída
- Fluxo de agendamento estável com flag ligada.
- Incidentes críticos = 0 durante janela de observação.

---

## Fase 4 — Migração do módulo Configurações frontend

**Objetivo:** consolidar integração com configurações mantendo contratos.

### Entregas
- Módulo `settings` integrado ao mecanismo oficial de abas.
- Compatibilidade com ações de salvamento existentes.
- Documentação de contratos (`register_tab`, callbacks, saves).

### Critério de saída
- Paridade de funcionalidades de configuração confirmada.

---

## Fase 5 — Consolidação, hardening e documentação final

**Objetivo:** estabilizar FRONTEND como camada principal de frontend.

### Entregas
- Guia operacional de rollout por ambiente.
- Matriz final de compatibilidade (base + add-ons).
- Runbook de incidentes/rollback.
- Checklist de prontidão para remoção futura de legado.
- Registro visual completo de todas as telas em `docs/screenshots/`.

### Critério de saída
- FRONTEND apto para padrão de uso recomendado.
- Conformidade visual M3 verificada em todos os módulos.

---

## Fase 6 — Preparação para depreciação e remoção futura (sem remover ainda)

**Objetivo:** deixar governança pronta para remoções seguras em releases futuros.

### Entregas
- Política de depreciação (janela mínima e comunicação).
- Lista de alvos de remoção com risco e plano de reversão.
- Evidências de uso real do legado (telemetria/observabilidade).

### Critério de saída
- Projeto pronto para iniciar remoções em lotes pequenos quando aprovado.

---

## 9) Critérios de aceite por módulo (DoD)

Cada módulo migrado só pode avançar se cumprir os critérios abaixo, alinhados com o `AGENT_ENGINEERING_PLAYBOOK.md`:

1. **Paridade funcional**
   - Mesmo comportamento para entradas válidas/inválidas e mensagens de feedback.

2. **Segurança**
   - Nonce, capability, sanitização e escape equivalentes ou superiores ao legado.
   - `$wpdb->prepare()` em toda query SQL.

3. **Compatibilidade**
   - Shortcodes/hooks legados ainda funcionam durante transição.

4. **Conformidade visual M3**
   - Templates e assets seguem `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md`.
   - Cores, formas, tipografia e motion via design tokens (`var(--dps-*)`).
   - Mudanças visuais documentadas com prints em `docs/screenshots/YYYY-MM-DD/`.

5. **Qualidade de código**
   - Passa em `php -l` e PHPCS (WordPress standards).
   - Funções pequenas (SRP), early returns, nomes descritivos.
   - Regra de negócio fora de callbacks de hooks/shortcodes.

6. **Observabilidade**
   - Logs de erro/uso disponíveis para monitorar adoção e anomalias.

7. **Rollback testado**
   - Retorno ao legado por flag sem perda operacional.

---

## 10) Estratégia de testes e validação

### 10.1 Testes mínimos por fase
- `php -l` em todos os arquivos PHP alterados.
- Verificação de diffs (`git diff --check`).
- Validação funcional manual dos fluxos impactados em WP local/homolog.

### 10.2 Validação visual M3 (obrigatória para fases com UI)
- Conformidade com `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` (checklist seção 14).
- Testar em viewports: 375px, 600px, 840px, 1200px.
- Contraste WCAG AA verificado (sistema de pareamento M3).
- Focus visible e navegação por teclado funcional.
- Touch targets ≥ 48×48px em mobile.
- Capturas das telas salvas em `docs/screenshots/YYYY-MM-DD/`.

### 10.3 Suite de regressão funcional recomendada
- Cadastro: envio com sucesso, validação de campos, erros esperados.
- Agendamento: seleção de serviço/horário, persistência e feedback.
- Configurações: abertura de abas, salvamento por ação, feedback de sucesso/erro.
- Compatibilidade: páginas existentes com shortcodes legados.

### 10.4 Evidências obrigatórias
- Registro dos comandos executados e resultado.
- Checklist de fluxos validada por módulo.
- Logs de execução para incidentes e fallback.

---

## 11) Observabilidade e métricas de decisão

Métricas recomendadas para orientar avanço e futura remoção:
- Taxa de uso de shortcodes legados vs novos.
- Taxa de erro por módulo (antes/depois da migração).
- Incidentes por release relacionados a frontend.
- Tempo médio de rollback (quando executado).

Essas métricas devem alimentar o comitê de decisão de descontinuação do legado.

---

## 12) Plano de rollback

### 12.1 Rollback técnico
- Desligar feature flag do módulo afetado.
- Restaurar roteamento para implementação legada.
- Manter dados preservados (sem migração destrutiva).

### 12.2 Rollback operacional
- Comunicar equipe interna sobre retorno temporário.
- Registrar causa raiz e ação corretiva para nova tentativa.

---

## 13) Riscos principais e mitigação

| Risco | Probabilidade | Impacto | Mitigação |
|------|---------------|---------|-----------|
| Quebra de shortcode em páginas ativas | Média | Alto | Alias + fallback + validação prévia |
| Divergência de comportamento entre legado e novo | Média | Alto | Dual-run + checklist de paridade |
| Acoplamento oculto entre add-ons | Alta | Médio/Alto | Inventário detalhado + rollout faseado |
| Regressão em segurança | Baixa/Média | Alto | Guard central de request + revisão de segurança |
| Inconsistência visual M3 entre módulos | Média | Médio | Design tokens obrigatórios + checklist M3 por fase |
| Dificuldade de remover legado no futuro | Média | Médio | Telemetria + critérios objetivos de depreciação |

---

## 14) Documentação obrigatória durante a execução

Para garantir remoção futura sem interferência, manter os artefatos abaixo atualizados:

1. **Arquitetura e contratos**
   - Atualizar `ANALYSIS.md` com seção do add-on FRONTEND e contratos públicos.

2. **Planejamento de execução**
   - Atualizar este roadmap a cada fase concluída (status, decisões e riscos).

3. **Implementação e rollout**
   - Registrar em `docs/implementation/` o passo a passo de ativação por ambiente.

4. **QA e aceitação**
   - Registrar em `docs/qa/` os resultados de regressão por módulo.

5. **Registro visual**
   - Capturas de telas alteradas em `docs/screenshots/YYYY-MM-DD/`, conforme `docs/screenshots/README.md`.

6. **Depreciação futura**
   - Manter checklist de “pronto para remover” com evidências.

---

## 15) Cronograma sugerido (exemplo inicial)

- **Release A:** Fase 0 + Fase 1
- **Release B:** Fase 2 (Cadastro)
- **Release C:** Fase 3 (Agendamento)
- **Release D:** Fase 4 (Configurações)
- **Release E:** Fase 5 + preparação da Fase 6

> Ajustar cronograma conforme capacidade da equipe e janela de homologação.

---

## 16) Checklists operacionais

### 16.1 Checklist de entrada de fase
- [ ] Contratos do módulo mapeados
- [ ] Riscos registrados
- [ ] Plano de rollback definido
- [ ] Critérios de aceite aprovados

### 16.2 Checklist de saída de fase
- [ ] Paridade funcional validada
- [ ] Segurança validada
- [ ] Compatibilidade legada preservada
- [ ] Conformidade visual M3 verificada (para fases com UI)
- [ ] Logs/métricas coletados
- [ ] Documentação atualizada
- [ ] Capturas visuais registradas em `docs/screenshots/` (para fases com UI)

---

## 17) Decisões para esta etapa (sem remoções)

1. O add-on FRONTEND será criado com foco em **adicionalidade**, não substituição imediata.
2. O legado será mantido durante toda a migração inicial, com fallback ativo.
3. Remoção de código antigo será tratada em programa posterior, com base em evidência operacional e critérios formais.

