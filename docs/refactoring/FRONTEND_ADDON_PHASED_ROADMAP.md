# Plano em fases para criaÃ§Ã£o do Add-on FRONTEND

## 1) Contexto e objetivo

Este documento define um plano **amplo, incremental e seguro** para criar o novo add-on `desi-pet-shower-frontend`, consolidando experiÃªncias frontend hoje distribuÃ­das entre plugin base e add-ons de cadastro (`desi-pet-shower-registration`) e agendamento (`desi-pet-shower-booking`).

A decisÃ£o deste plano Ã©:
- **nÃ£o remover cÃ³digo legado nesta etapa inicial**;
- construir o novo add-on com **compatibilidade retroativa**;
- preparar, desde jÃ¡, a trilha de evidÃªncias para remoÃ§Ã£o futura sem risco.

### Documentos relacionados

| Documento | PropÃ³sito |
|-----------|-----------|
| `AGENT_ENGINEERING_PLAYBOOK.md` | PrincÃ­pios de engenharia, DoD e processo de implementaÃ§Ã£o |
| `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` | InstruÃ§Ãµes de design frontend DPS Signature |
| `docs/visual/VISUAL_STYLE_GUIDE.md` | Tokens, paleta, tipografia e componentes CSS |
| `ANALYSIS.md` | Arquitetura, hooks e contratos do sistema |
| `AGENTS.md` | Regras globais (MUST / ASK BEFORE / PREFER) |

## 2) Resultado esperado ao final do programa

Ao concluir todas as fases deste roadmap, o projeto terÃ¡:
- add-on `FRONTEND` operacional com arquitetura modular;
- compatibilidade com shortcodes e fluxos atuais;
- documentaÃ§Ã£o de contratos e integraÃ§Ãµes centralizada;
- rollout controlado por fases (feature flags e fallback);
- critÃ©rios objetivos para remoÃ§Ã£o futura de legado.

> **Importante:** este plano foi desenhado para reduzir risco de quebra em produÃ§Ã£o e manter a continuidade operacional dos clientes atuais.

---

## 3) PrincÃ­pios de implementaÃ§Ã£o

1. **Compatibilidade primeiro**
   - Toda migraÃ§Ã£o deve preservar comportamento de shortcodes/hooks existentes durante a transiÃ§Ã£o.

2. **Dual-run e rollback rÃ¡pido**
   - Cada mÃ³dulo migrado roda com fallback para legado via feature flag.

3. **Contratos explÃ­citos**
   - APIs de integraÃ§Ã£o (hooks, shortcodes, opÃ§Ãµes) devem ser documentadas antes e depois da migraÃ§Ã£o.

4. **MudanÃ§as pequenas e verificÃ¡veis**
   - Entregas incrementais com critÃ©rios de aceite e validaÃ§Ã£o funcional por fase.

5. **Observabilidade desde o inÃ­cio**
   - Logs e mÃ©tricas de uso para guiar decisÃµes de estabilizaÃ§Ã£o e remoÃ§Ã£o futura.

---

## 4) Escopo funcional da consolidaÃ§Ã£o FRONTEND

### 4.1 Em escopo nesta iniciativa
- ExperiÃªncias frontend relacionadas a:
  - formulÃ¡rio de cadastro;
  - formulÃ¡rio de agendamento;
  - pÃ¡gina de configuraÃ§Ãµes frontend e abas associadas;
  - assets e templates correlatos dessas experiÃªncias.

### 4.2 Fora de escopo (por ora)
- RemoÃ§Ã£o de funÃ§Ãµes, classes ou add-ons legados.
- AlteraÃ§Ãµes de schema em tabelas compartilhadas.
- MudanÃ§as de contrato sem camada de compatibilidade.

---

## 5) InventÃ¡rio inicial (baseline obrigatÃ³rio)

Antes de codar, deve existir inventÃ¡rio consolidado com:

- **Shortcodes atuais** usados nos fluxos-alvo.
- **Hooks de integraÃ§Ã£o** consumidos e expostos (incluindo legados/deprecados).
- **Options/metadados** persistidos por cada fluxo.
- **Entradas de seguranÃ§a** (nonces, capabilities, sanitizaÃ§Ã£o, escapes).
- **Fluxos operacionais crÃ­ticos** com passo a passo e resultado esperado.
- **DependÃªncias cruzadas** entre base e add-ons envolvidos.

### 5.1 EntregÃ¡vel da fase de inventÃ¡rio
Criar matriz de contratos em formato tabular contendo, no mÃ­nimo:

| Tipo | Nome | Origem atual | Consumidores | Risco de quebra | EstratÃ©gia de compatibilidade |
|------|------|--------------|--------------|-----------------|-------------------------------|
| Shortcode | `...` | plugin X | pÃ¡ginas Y | Alto/MÃ©dio/Baixo | Alias/Wrapper/Manter |
| Hook | `...` | plugin X | addon Y | Alto/MÃ©dio/Baixo | Bridge/Deprecated |
| Option | `...` | plugin X | mÃ³dulo Y | Alto/MÃ©dio/Baixo | Leitura dupla/MigraÃ§Ã£o lazy |

---

## 6) Arquitetura-alvo do add-on FRONTEND

### 6.1 Estrutura recomendada

```text
plugins/desi-pet-shower-frontend/
â”œâ”€â”€ desi-pet-shower-frontend-addon.php
â”œâ”€â”€ includes/
â”‚   â”œâ”€â”€ class-dps-frontend-addon.php
â”‚   â”œâ”€â”€ class-dps-frontend-module-registry.php
â”‚   â”œâ”€â”€ class-dps-frontend-compatibility.php
â”‚   â”œâ”€â”€ class-dps-frontend-feature-flags.php
â”‚   â”œâ”€â”€ modules/
â”‚   â”‚   â”œâ”€â”€ class-dps-frontend-registration-module.php
â”‚   â”‚   â”œâ”€â”€ class-dps-frontend-booking-module.php
â”‚   â”‚   â””â”€â”€ class-dps-frontend-settings-module.php
â”‚   â””â”€â”€ support/
â”‚       â”œâ”€â”€ class-dps-frontend-assets.php
â”‚       â”œâ”€â”€ class-dps-frontend-logger.php
â”‚       â””â”€â”€ class-dps-frontend-request-guard.php
â”œâ”€â”€ templates/
â””â”€â”€ assets/
```

### 6.2 CabeÃ§alho do plugin (padrÃ£o do projeto)

```php
/**
 * Plugin Name: desi.pet by PRObst â€“ Frontend Add-on
 * Plugin URI:  https://www.probst.pro
 * Description: Consolida experiÃªncias frontend (cadastro, agendamento, configuraÃ§Ãµes) em add-on modular.
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
- `Registry`: habilita/desabilita mÃ³dulos e conecta hooks.
- `Compatibility`: camada de aliases/wrappers para legado.
- `Feature Flags`: controle de rollout por mÃ³dulo/fluxo.
- `Modules/*`: regra de negÃ³cio e renderizaÃ§Ã£o por domÃ­nio.
- `Support/*`: seguranÃ§a, assets, logging e utilitÃ¡rios.

### 6.4 Arquitetura de assets e padrÃ£o visual DPS Signature

O add-on FRONTEND Ã© intrinsecamente visual e **deve** seguir o padrÃ£o **DPS Signature** do projeto desde a Fase 1. ReferÃªncias obrigatÃ³rias:

- `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` â€” metodologia, contextos de uso, checklist
- `docs/visual/VISUAL_STYLE_GUIDE.md` â€” tokens, paleta, componentes CSS

#### Estrutura de assets recomendada

```text
assets/
â”œâ”€â”€ css/
â”‚   â””â”€â”€ frontend-addon.css      /* Estilos do add-on â€” sem hex literais, via var(--dps-*) */
â””â”€â”€ js/
    â””â”€â”€ frontend-addon.js       /* Vanilla JS, IIFE, 'use strict' */
```

#### Requisitos DPS Signature para o add-on

1. **Importar `dps-design-tokens.css`** como dependÃªncia (jÃ¡ fornecido pelo plugin base).
2. **Cores exclusivamente via tokens** â€” `var(--dps-color-*)`, sem hex/rgba literais.
3. **Formas via tokens** â€” `var(--dps-shape-*)`, sem `border-radius` literal.
4. **Tipografia** â€” escala DPS Signature (`var(--dps-typescale-*)`), pesos 400 e 500 apenas.
5. **BotÃµes pill** â€” classe `.dps-submit-btn` (DPS Signature pill button) para aÃ§Ãµes primÃ¡rias.
6. **Enqueue condicional** â€” assets carregados apenas nas pÃ¡ginas onde o add-on atua.
7. **`prefers-reduced-motion`** â€” respeitado automaticamente via tokens globais.
8. **Perfil por contexto** â€” mais contido no admin e mais aberto no portal/pÃºblico.

> Consultar o checklist completo em `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md`, seÃ§Ã£o 14.

---

## 7) EstratÃ©gia de compatibilidade

### 7.1 Shortcodes
- Manter shortcodes existentes funcionais na transiÃ§Ã£o.
- Se houver shortcode canÃ´nico novo, publicar alias dos antigos para ele.
- Logar uso de shortcode legado para telemetria de desativaÃ§Ã£o futura.

### 7.2 Hooks
- NÃ£o quebrar assinaturas atuais.
- Se necessÃ¡rio evoluir contrato, criar novo hook e manter bridge para o antigo.
- Documentar depreciaÃ§Ã£o apenas quando houver substituto estÃ¡vel.

### 7.3 PersistÃªncia de dados
- Evitar migraÃ§Ã£o destrutiva imediata.
- Preferir compatibilidade por leitura dupla (chave nova + chave antiga) atÃ© estabilizaÃ§Ã£o.

---

## 8) Plano de execuÃ§Ã£o por fases

## Fase 0 â€” GovernanÃ§a, discovery e baseline

**Objetivo:** reduzir incerteza tÃ©cnica e mapear riscos antes da implementaÃ§Ã£o.

### Entregas
- Matriz de contratos e dependÃªncias.
- CatÃ¡logo de fluxos crÃ­ticos com critÃ©rios de aceite.
- Matriz de risco por mÃ³dulo.
- EstratÃ©gia de rollback por feature flag.

### CritÃ©rio de saÃ­da
- Nenhuma dependÃªncia crÃ­tica â€œdesconhecidaâ€.
- Contratos pÃºblicos mapeados e revisados.

---

## Fase 1 â€” FundaÃ§Ã£o do add-on FRONTEND

**Objetivo:** criar esqueleto do add-on sem impacto funcional visÃ­vel.

### Entregas
- Plugin principal com bootstrap e textdomain (cabeÃ§alho conforme seÃ§Ã£o 6.2).
- Registry modular e feature flags.
- Infra de logging/diagnÃ³stico.
- Camada de compatibilidade vazia preparada.
- Estrutura de assets com dependÃªncia de `dps-design-tokens.css` (padrÃ£o DPS Signature).
- DocumentaÃ§Ã£o da seÃ§Ã£o FRONTEND no `ANALYSIS.md`.

### CritÃ©rio de saÃ­da
- Add-on ativa sem erro e sem interferir no fluxo atual.
- Assets DPS Signature carregam condicionalmente sem conflito.

---

## Fase 2 â€” MigraÃ§Ã£o do mÃ³dulo Cadastro (piloto)

**Objetivo:** validar estratÃ©gia com mÃ³dulo de risco controlado.

### Entregas
- MÃ³dulo `registration` no FRONTEND.
- Wrapper/alias para shortcode legado correspondente.
- Fallback para legado por flag.
- Testes funcionais do fluxo completo de cadastro.

### CritÃ©rio de saÃ­da
- Paridade funcional comprovada em homologaÃ§Ã£o.
- Rollback validado.

---

## Fase 3 â€” MigraÃ§Ã£o do mÃ³dulo Agendamento

**Objetivo:** transferir fluxo de agendamento com dual-run.

### Entregas
- MÃ³dulo `booking` no FRONTEND.
- Compatibilidade com shortcode e integraÃ§Ãµes atuais.
- Enqueue condicional de assets no novo mÃ³dulo.
- Checklist de regressÃ£o do fluxo de agendamento.

### CritÃ©rio de saÃ­da
- Fluxo de agendamento estÃ¡vel com flag ligada.
- Incidentes crÃ­ticos = 0 durante janela de observaÃ§Ã£o.

---

## Fase 4 â€” MigraÃ§Ã£o do mÃ³dulo ConfiguraÃ§Ãµes frontend

**Objetivo:** consolidar integraÃ§Ã£o com configuraÃ§Ãµes mantendo contratos.

### Entregas
- MÃ³dulo `settings` integrado ao mecanismo oficial de abas.
- Compatibilidade com aÃ§Ãµes de salvamento existentes.
- DocumentaÃ§Ã£o de contratos (`register_tab`, callbacks, saves).

### CritÃ©rio de saÃ­da
- Paridade de funcionalidades de configuraÃ§Ã£o confirmada.

---

## Fase 5 â€” ConsolidaÃ§Ã£o, hardening e documentaÃ§Ã£o final

**Objetivo:** estabilizar FRONTEND como camada principal de frontend.

### Entregas
- Guia operacional de rollout por ambiente.
- Matriz final de compatibilidade (base + add-ons).
- Runbook de incidentes/rollback.
- Checklist de prontidÃ£o para remoÃ§Ã£o futura de legado.
- Registro visual completo de todas as telas em `docs/screenshots/`.

### CritÃ©rio de saÃ­da
- FRONTEND apto para padrÃ£o de uso recomendado.
- Conformidade visual DPS Signature verificada em todos os mÃ³dulos.

---

## Fase 6 â€” PreparaÃ§Ã£o para depreciaÃ§Ã£o e remoÃ§Ã£o futura (sem remover ainda)

**Objetivo:** deixar governanÃ§a pronta para remoÃ§Ãµes seguras em releases futuros.

### Entregas
- PolÃ­tica de depreciaÃ§Ã£o (janela mÃ­nima e comunicaÃ§Ã£o).
- Lista de alvos de remoÃ§Ã£o com risco e plano de reversÃ£o.
- EvidÃªncias de uso real do legado (telemetria/observabilidade).

### CritÃ©rio de saÃ­da
- Projeto pronto para iniciar remoÃ§Ãµes em lotes pequenos quando aprovado.

---

## 9) CritÃ©rios de aceite por mÃ³dulo (DoD)

Cada mÃ³dulo migrado sÃ³ pode avanÃ§ar se cumprir os critÃ©rios abaixo, alinhados com o `AGENT_ENGINEERING_PLAYBOOK.md`:

1. **Paridade funcional**
   - Mesmo comportamento para entradas vÃ¡lidas/invÃ¡lidas e mensagens de feedback.

2. **SeguranÃ§a**
   - Nonce, capability, sanitizaÃ§Ã£o e escape equivalentes ou superiores ao legado.
   - `$wpdb->prepare()` em toda query SQL.

3. **Compatibilidade**
   - Shortcodes/hooks legados ainda funcionam durante transiÃ§Ã£o.

4. **Conformidade visual DPS Signature**
   - Templates e assets seguem `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md`.
   - Cores, formas, tipografia e motion via design tokens (`var(--dps-*)`).
   - MudanÃ§as visuais documentadas com prints em `docs/screenshots/YYYY-MM-DD/`.

5. **Qualidade de cÃ³digo**
   - Passa em `php -l` e PHPCS (WordPress standards).
   - FunÃ§Ãµes pequenas (SRP), early returns, nomes descritivos.
   - Regra de negÃ³cio fora de callbacks de hooks/shortcodes.

6. **Observabilidade**
   - Logs de erro/uso disponÃ­veis para monitorar adoÃ§Ã£o e anomalias.

7. **Rollback testado**
   - Retorno ao legado por flag sem perda operacional.

---

## 10) EstratÃ©gia de testes e validaÃ§Ã£o

### 10.1 Testes mÃ­nimos por fase
- `php -l` em todos os arquivos PHP alterados.
- VerificaÃ§Ã£o de diffs (`git diff --check`).
- ValidaÃ§Ã£o funcional manual dos fluxos impactados em WP local/homolog.

### 10.2 ValidaÃ§Ã£o visual DPS Signature (obrigatÃ³ria para fases com UI)
- Conformidade com `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` (checklist seÃ§Ã£o 14).
- Testar em viewports: 375px, 600px, 840px, 1200px.
- Contraste WCAG AA verificado (sistema de pareamento DPS Signature).
- Focus visible e navegaÃ§Ã£o por teclado funcional.
- Touch targets â‰¥ 48Ã—48px em mobile.
- Capturas das telas salvas em `docs/screenshots/YYYY-MM-DD/`.

### 10.3 Suite de regressÃ£o funcional recomendada
- Cadastro: envio com sucesso, validaÃ§Ã£o de campos, erros esperados.
- Agendamento: seleÃ§Ã£o de serviÃ§o/horÃ¡rio, persistÃªncia e feedback.
- ConfiguraÃ§Ãµes: abertura de abas, salvamento por aÃ§Ã£o, feedback de sucesso/erro.
- Compatibilidade: pÃ¡ginas existentes com shortcodes legados.

### 10.4 EvidÃªncias obrigatÃ³rias
- Registro dos comandos executados e resultado.
- Checklist de fluxos validada por mÃ³dulo.
- Logs de execuÃ§Ã£o para incidentes e fallback.

---

## 11) Observabilidade e mÃ©tricas de decisÃ£o

MÃ©tricas recomendadas para orientar avanÃ§o e futura remoÃ§Ã£o:
- Taxa de uso de shortcodes legados vs novos.
- Taxa de erro por mÃ³dulo (antes/depois da migraÃ§Ã£o).
- Incidentes por release relacionados a frontend.
- Tempo mÃ©dio de rollback (quando executado).

Essas mÃ©tricas devem alimentar o comitÃª de decisÃ£o de descontinuaÃ§Ã£o do legado.

---

## 12) Plano de rollback

### 12.1 Rollback tÃ©cnico
- Desligar feature flag do mÃ³dulo afetado.
- Restaurar roteamento para implementaÃ§Ã£o legada.
- Manter dados preservados (sem migraÃ§Ã£o destrutiva).

### 12.2 Rollback operacional
- Comunicar equipe interna sobre retorno temporÃ¡rio.
- Registrar causa raiz e aÃ§Ã£o corretiva para nova tentativa.

---

## 13) Riscos principais e mitigaÃ§Ã£o

| Risco | Probabilidade | Impacto | MitigaÃ§Ã£o |
|------|---------------|---------|-----------|
| Quebra de shortcode em pÃ¡ginas ativas | MÃ©dia | Alto | Alias + fallback + validaÃ§Ã£o prÃ©via |
| DivergÃªncia de comportamento entre legado e novo | MÃ©dia | Alto | Dual-run + checklist de paridade |
| Acoplamento oculto entre add-ons | Alta | MÃ©dio/Alto | InventÃ¡rio detalhado + rollout faseado |
| RegressÃ£o em seguranÃ§a | Baixa/MÃ©dia | Alto | Guard central de request + revisÃ£o de seguranÃ§a |
| InconsistÃªncia visual DPS Signature entre mÃ³dulos | MÃ©dia | MÃ©dio | Design tokens obrigatÃ³rios + checklist DPS Signature por fase |
| Dificuldade de remover legado no futuro | MÃ©dia | MÃ©dio | Telemetria + critÃ©rios objetivos de depreciaÃ§Ã£o |

---

## 14) DocumentaÃ§Ã£o obrigatÃ³ria durante a execuÃ§Ã£o

Para garantir remoÃ§Ã£o futura sem interferÃªncia, manter os artefatos abaixo atualizados:

1. **Arquitetura e contratos**
   - Atualizar `ANALYSIS.md` com seÃ§Ã£o do add-on FRONTEND e contratos pÃºblicos.

2. **Planejamento de execuÃ§Ã£o**
   - Atualizar este roadmap a cada fase concluÃ­da (status, decisÃµes e riscos).

3. **ImplementaÃ§Ã£o e rollout**
   - Registrar em `docs/implementation/` o passo a passo de ativaÃ§Ã£o por ambiente.

4. **QA e aceitaÃ§Ã£o**
   - Registrar em `docs/qa/` os resultados de regressÃ£o por mÃ³dulo.

5. **Registro visual**
   - Capturas de telas alteradas em `docs/screenshots/YYYY-MM-DD/`, conforme `docs/screenshots/README.md`.

6. **DepreciaÃ§Ã£o futura**
   - Manter checklist de â€œpronto para removerâ€ com evidÃªncias.

---

## 15) Cronograma sugerido (exemplo inicial)

- **Release A:** Fase 0 + Fase 1
- **Release B:** Fase 2 (Cadastro)
- **Release C:** Fase 3 (Agendamento)
- **Release D:** Fase 4 (ConfiguraÃ§Ãµes)
- **Release E:** Fase 5 + preparaÃ§Ã£o da Fase 6

> Ajustar cronograma conforme capacidade da equipe e janela de homologaÃ§Ã£o.

---

## 16) Checklists operacionais

### 16.1 Checklist de entrada de fase
- [ ] Contratos do mÃ³dulo mapeados
- [ ] Riscos registrados
- [ ] Plano de rollback definido
- [ ] CritÃ©rios de aceite aprovados

### 16.2 Checklist de saÃ­da de fase
- [ ] Paridade funcional validada
- [ ] SeguranÃ§a validada
- [ ] Compatibilidade legada preservada
- [ ] Conformidade visual DPS Signature verificada (para fases com UI)
- [ ] Logs/mÃ©tricas coletados
- [ ] DocumentaÃ§Ã£o atualizada
- [ ] Capturas visuais registradas em `docs/screenshots/` (para fases com UI)

---

## 17) DecisÃµes para esta etapa (sem remoÃ§Ãµes)

1. O add-on FRONTEND serÃ¡ criado com foco em **adicionalidade**, nÃ£o substituiÃ§Ã£o imediata.
2. O legado serÃ¡ mantido durante toda a migraÃ§Ã£o inicial, com fallback ativo.
3. RemoÃ§Ã£o de cÃ³digo antigo serÃ¡ tratada em programa posterior, com base em evidÃªncia operacional e critÃ©rios formais.
