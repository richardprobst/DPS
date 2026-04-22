# DocumentaÃ§Ã£o do desi.pet by PRObst (DPS)

Este diretÃ³rio contÃ©m a documentaÃ§Ã£o detalhada de UX, layout, refatoraÃ§Ã£o e planos de implementaÃ§Ã£o do sistema DPS.

## ðŸ“š DocumentaÃ§Ã£o Principal

> **ðŸŒŸ [GUIA_SISTEMA_DPS.md](GUIA_SISTEMA_DPS.md)** - Guia completo de apresentaÃ§Ã£o, instalaÃ§Ã£o, configuraÃ§Ã£o e uso do sistema. Documento principal para usuÃ¡rios e administradores.

> **ðŸš€ [FRONTEND_ADDON_GUIA_USUARIO.md](FRONTEND_ADDON_GUIA_USUARIO.md)** - **ðŸ†•** Guia completo do Frontend Add-on. InstalaÃ§Ã£o, configuraÃ§Ã£o, shortcodes, criaÃ§Ã£o de pÃ¡ginas e personalizaÃ§Ã£o visual. Documento essencial para usar o novo add-on modular de experiÃªncias frontend (cadastro, agendamento, configuraÃ§Ãµes).

> **ðŸ”§ [FUNCTIONS_REFERENCE.md](FUNCTIONS_REFERENCE.md)** - ReferÃªncia completa de TODAS as funÃ§Ãµes e mÃ©todos do DPS. Guia definitivo para desenvolvedores trabalhando com o sistema (8.233 linhas, 385+ funÃ§Ãµes/mÃ©todos documentados, cobrindo plugin base + 16 add-ons).

O guia inclui:
- ApresentaÃ§Ã£o do sistema e funcionalidades
- InstalaÃ§Ã£o do plugin base e add-ons
- ConfiguraÃ§Ã£o detalhada de cada componente
- InstruÃ§Ãµes de uso passo a passo
- **ðŸ†• Guia Passo a Passo do GitHub Updater** - Como atualizar o sistema de forma fÃ¡cil
- ResoluÃ§Ã£o de problemas comuns
- ReferÃªncia tÃ©cnica (shortcodes, roles, estrutura de dados)

**âš ï¸ Mantenha este documento atualizado** sempre que houver mudanÃ§as no sistema.

---

## Estrutura da DocumentaÃ§Ã£o

### ðŸ“ /docs/admin

AnÃ¡lises e planos relacionados Ã  interface administrativa do WordPress.

**Arquivos:**
- `ADMIN_CPT_INTERFACE_ANALYSIS.md` - AnÃ¡lise completa sobre habilitaÃ§Ã£o da interface admin nativa para CPTs
- `ADMIN_CPT_INTERFACE_SUMMARY.md` - Resumo executivo da anÃ¡lise de interface admin para CPTs
- `ADMIN_UI_MOCKUP.md` - Mockup da interface administrativa dos CPTs
- `CPT_UI_ENABLEMENT_SUMMARY.md` - Resumo da habilitaÃ§Ã£o da UI nativa para CPTs
- `RESULTADO_FINAL_CPT_UI.md` - Resultado final da habilitaÃ§Ã£o de UI para CPTs

### ðŸ“ /docs/analysis

AnÃ¡lises arquiteturais e de sistema.

**Arquivos:**
- `ADDONS_DETAILED_ANALYSIS.md` - AnÃ¡lise detalhada de todos os 15 add-ons
- `BACKEND_FRONTEND_MAPPING.md` - Mapeamento completo BACK-END vs FRONT-END
- `GOOGLE_TASKS_INTEGRATION_ANALYSIS.md` - **ðŸ†•** AnÃ¡lise completa de integraÃ§Ã£o com Google Tarefas (31KB, arquitetura, casos de uso, seguranÃ§a, estimativas)
- `GOOGLE_TASKS_INTEGRATION_SUMMARY.md` - **ðŸ†•** Resumo executivo da integraÃ§Ã£o Google Tarefas (recomendaÃ§Ã£o: viÃ¡vel e interessante)
- `STOCK_ADDON_ANALYSIS.md` - AnÃ¡lise profunda do Add-on Estoque (funcionalidade, integraÃ§Ã£o com ServiÃ§os, fluxo de uso)
- `SUBSCRIPTION_ADDON_ANALYSIS.md` - AnÃ¡lise profunda do Add-on Assinaturas (cÃ³digo, funcionalidades, layout, melhorias propostas)
- `SYSTEM_ANALYSIS_COMPLETE.md` - AnÃ¡lise profunda do sistema
- `SYSTEM_ANALYSIS_SUMMARY.md` - Resumo executivo da anÃ¡lise de sistema
- `WHITE_LABEL_ANALYSIS.md` - AnÃ¡lise completa de implementaÃ§Ã£o White Label (seguranÃ§a, funcionalidades, arquitetura proposta)

### ðŸ“ /docs/compatibility

DocumentaÃ§Ã£o de compatibilidade com temas e page builders.

**Arquivos:**
- `COMPATIBILITY_ANALYSIS.md` - AnÃ¡lise geral de compatibilidade
- `EDITOR_SHORTCODE_GUIDE.md` - **ðŸ†•** Guia de como inserir shortcodes no editor WordPress (soluÃ§Ã£o para problema comum)
- `RESOLUTION_SUMMARY.md` - Resumo de resoluÃ§Ãµes de compatibilidade
- `YOOTHEME_COMPATIBILITY.md` - Guia de compatibilidade com YooTheme PRO
- `YOOTHEME_RESPOSTA_RAPIDA.md` - Resposta rÃ¡pida para problemas YooTheme

### ðŸ“ /docs/fixes

CorreÃ§Ãµes e diagnÃ³sticos.

**Arquivos:**
- `FINANCE_ACTIVATION_FIX_SUMMARY.md` - CorreÃ§Ã£o do activation hook do Finance Add-on
- `PORTAL_LAYOUT_FIX.md` - CorreÃ§Ãµes de layout do Portal do Cliente
- `TRANSLATION_LOADING_FIX_VERIFICATION.md` - VerificaÃ§Ã£o de correÃ§Ãµes de carregamento de traduÃ§Ãµes

### ðŸ“ /docs/forms

DocumentaÃ§Ã£o especÃ­fica de formulÃ¡rios e inputs.

**Arquivos:**
- `AGENDAMENTOS_RESPONSIVENESS_FIXES.md` - CorreÃ§Ãµes de responsividade em agendamentos
- `AGENDAMENTO_SERVICOS_MELHORIAS_IMPLEMENTADAS.md` - Melhorias implementadas
- `APPOINTMENT_FORM_FIXES_SUMMARY.md` - Resumo de correÃ§Ãµes do formulÃ¡rio
- `APPOINTMENT_FORM_LAYOUT_FIXES.md` - CorreÃ§Ãµes de layout
- `APPOINTMENT_FORM_VISUAL_COMPARISON.md` - ComparaÃ§Ã£o visual
- `EXECUTIVE_SUMMARY_RESPONSIVENESS_FIXES.md` - Resumo executivo de responsividade
- `INVESTIGACAO_COMPLETA_AGENDAMENTO_SERVICOS.md` - InvestigaÃ§Ã£o completa
- `SCHEDULING_FORM_IMPROVEMENTS_SUMMARY.md` - Melhorias implementadas
- `SCHEDULING_FORM_UX_ANALYSIS.md` - AnÃ¡lise de UX
- `agendamentos-responsive-test.html` - Arquivo de teste responsivo

### ðŸ“ /docs/implementation

Resumos de implementaÃ§Ã£o de features.

**Arquivos:**
- `IMPLEMENTATION_SUMMARY.md` - Resumo geral de implementaÃ§Ãµes
- `IMPLEMENTATION_SUMMARY_CLIENT_PORTAL_MENUS.md` - ImplementaÃ§Ã£o de menus do Portal do Cliente
- `RESPOSTA_IMPLEMENTACAO_AI_COMUNICACOES.md` - ImplementaÃ§Ã£o de IA para comunicaÃ§Ãµes
- `SERVICES_AGENDA_INTEGRATION_SUMMARY.md` - IntegraÃ§Ã£o Services â‡„ Agenda
- `UI_UX_IMPROVEMENTS_SUMMARY.md` - Resumo de melhorias de UI/UX
- `WHATSAPP_IMPLEMENTATION_SUMMARY.md` - ImplementaÃ§Ã£o de integraÃ§Ã£o WhatsApp
- `FRONTEND_ROLLOUT_GUIDE.md` - **ðŸ†•** Guia operacional de rollout do Frontend Add-on (ativaÃ§Ã£o por ambiente, verificaÃ§Ã£o, monitoramento)
- `FRONTEND_RUNBOOK.md` - **ðŸ†•** Runbook de incidentes do Frontend Add-on (diagnÃ³stico, rollback, cenÃ¡rios)
- `PLANO_IMPLEMENTACAO_FASES.md` - **ðŸ†•** Plano completo de implementaÃ§Ã£o em 8 fases (SeguranÃ§a â†’ RefatoraÃ§Ã£o â†’ Performance â†’ UX â†’ Features â†’ Auditoria â†’ Testes â†’ IntegraÃ§Ãµes)

### ðŸ“ /docs/improvements

Melhorias gerais do sistema.

**Arquivos:**
- `AGENDA_RESPONSIVENESS_IMPROVEMENTS.md` - Melhorias de responsividade da Agenda

### ðŸ“ /docs/layout

DocumentaÃ§Ã£o de layout e UX, organizada em subpastas:

**Arquivos na raiz:**
- `RESPONSIVENESS_ANALYSIS.md` - **ðŸ†•** AnÃ¡lise completa de responsividade de todo o sistema (plugin base + 15 add-ons)

#### `/docs/layout/admin`
- `ADMIN_LAYOUT_ANALYSIS.md` - AnÃ¡lise detalhada do layout das telas administrativas

#### `/docs/layout/agenda`
- `AGENDA_EXECUTIVE_SUMMARY.md` - SumÃ¡rio executivo da agenda
- `AGENDA_IMPLEMENTATION_SUMMARY.md` - Resumo de implementaÃ§Ã£o
- `AGENDA_INDEX.md` - Ãndice da documentaÃ§Ã£o da agenda
- `AGENDA_LAYOUT_ANALYSIS.md` - AnÃ¡lise detalhada do layout
- `AGENDA_VISUAL_COMPARISON.md` - ComparaÃ§Ã£o visual de versÃµes
- `AGENDA_VISUAL_SUMMARY.md` - Resumo visual da agenda

#### `/docs/layout/client-portal`
- `CLIENT_PORTAL_IMPLEMENTATION_SUMMARY.md` - Resumo de implementaÃ§Ã£o
- `CLIENT_PORTAL_SUMMARY.md` - SumÃ¡rio geral do portal
- `CLIENT_PORTAL_UX_ANALYSIS.md` - AnÃ¡lise detalhada de UX
- `PORTAL_DEMO_README.md` - README da demo do portal
- `portal-cliente-demo.html` - Demo HTML do portal

#### `/docs/layout/forms`
- `FORMS_IMPROVEMENTS_SUMMARY.md` - Resumo de melhorias implementadas
- `FORMS_UX_ANALYSIS.md` - AnÃ¡lise de UX dos formulÃ¡rios

### ðŸ“ /docs/performance

AnÃ¡lises e otimizaÃ§Ãµes de performance.

**Arquivos:**
- `EXEMPLOS_PERFORMANCE_ADDONS.md` - Exemplos de otimizaÃ§Ã£o em add-ons
- `PERFORMANCE_REVIEW_ADDONS.md` - RevisÃ£o de performance dos add-ons

### ðŸ“ /docs/qa

Quality assurance e validaÃ§Ã£o funcional de add-ons.

**Arquivos:**
- `AI_ADDON_FUNCTIONAL_QA.md` - QA funcional do AI Add-on
- `CLIENT_PORTAL_FUNCTIONAL_VALIDATION.md` - ValidaÃ§Ã£o funcional do Portal do Cliente
- `FUNCTIONAL_VERIFICATION_REPORT.md` - RelatÃ³rio de verificaÃ§Ã£o funcional geral
- `GROOMERS_FUNCTIONAL_QA.md` - QA funcional do Groomers Add-on
- `FRONTEND_COMPATIBILITY_MATRIX.md` - **ðŸ†•** Matriz de compatibilidade do Frontend Add-on com todos os 18 add-ons
- `FRONTEND_REMOVAL_READINESS.md` - **ðŸ†•** Checklist de prontidÃ£o para remoÃ§Ã£o futura de legado

### ðŸ“ /docs/refactoring

AnÃ¡lises e planos de refatoraÃ§Ã£o de cÃ³digo.

**Arquivos:**
- `AGENT_ENGINEERING_PLAYBOOK.md` - Playbook complementar para agentes com princÃ­pios, arquitetura e DoD de implementaÃ§Ã£o
- `FRONTEND_ADDON_PHASED_ROADMAP.md` - Plano amplo e faseado para criaÃ§Ã£o do add-on FRONTEND com compatibilidade, rollout e preparaÃ§Ã£o para remoÃ§Ã£o futura de legado (Fase 1-6 concluÃ­das)
- `FRONTEND_DEPRECATION_POLICY.md` - PolÃ­tica formal de depreciaÃ§Ã£o (janela mÃ­nima, comunicaÃ§Ã£o, critÃ©rios de aceite, procedimento)
- `FRONTEND_REMOVAL_TARGETS.md` - Lista de alvos de remoÃ§Ã£o com dependÃªncias, risco, esforÃ§o e plano de reversÃ£o
- `FRONTEND_NATIVE_IMPLEMENTATION_PLAN.md` - **ðŸ†• FASE 7:** Plano completo para implementaÃ§Ã£o nativa (from-scratch). Documento histÃ³rico de uma fase anterior; a orientaÃ§Ã£o visual vigente hoje estÃ¡ exclusivamente em `docs/visual/` e segue o padrÃ£o DPS Signature.

### ðŸ“ /docs/review

RevisÃµes de cÃ³digo e PRs.

**Arquivos:**
- `PLUGIN_BASE_CODE_REVIEW.md` - RevisÃ£o de cÃ³digo do plugin base

#### `/docs/review/pr-161`
VerificaÃ§Ã£o completa do PR #161 (alinhamento de preÃ§os de serviÃ§os):
- `INDEX_PR_161_VERIFICATION.md` - Ãndice da verificaÃ§Ã£o
- `PR_161_CORRECTED_CSS.css` - CSS corrigido
- `PR_161_EXECUTIVE_SUMMARY.md` - Resumo executivo
- `PR_161_SIDE_BY_SIDE_COMPARISON.md` - ComparaÃ§Ã£o lado a lado
- `PR_161_VERIFICATION.md` - VerificaÃ§Ã£o detalhada
- `README_VERIFICATION.md` - README da verificaÃ§Ã£o

### ðŸ“ /docs/settings

DocumentaÃ§Ã£o de configuraÃ§Ãµes do sistema.

**Arquivos:**
- `FRONTEND_SETTINGS_IMPLEMENTATION_PLAN.md` - **ðŸ†•** Plano detalhado para implementaÃ§Ã£o da pÃ¡gina de configuraÃ§Ãµes front-end (`[dps_configuracoes]`)
- `GROOMERS_SETTINGS_VERIFICATION.md` - VerificaÃ§Ã£o das configuraÃ§Ãµes do Groomers Add-on

### ðŸ“ /docs/security

DocumentaÃ§Ã£o de seguranÃ§a e auditoria.

**Arquivos:**
- `AUDIT_FASE1.md` - Auditoria de seguranÃ§a completa â€” Fase 1 do Plano de ImplementaÃ§Ã£o
- `EXEMPLOS_ANTES_DEPOIS_SEGURANCA.md` - Exemplos antes/depois de correÃ§Ãµes de seguranÃ§a
- `SECURITY_FIXES_FINANCE_SUMMARY.md` - Resumo de correÃ§Ãµes de seguranÃ§a do Finance Add-on

### ðŸ“ /docs/screenshots

**ðŸ†• DocumentaÃ§Ã£o visual e capturas de tela do sistema.**

**PadrÃ£o obrigatÃ³rio para mudanÃ§as visuais:**
- registrar documentaÃ§Ã£o da alteraÃ§Ã£o (contexto, antes/depois, telas impactadas e arquivos alterados);
- salvar as capturas em subpastas por data: `docs/screenshots/YYYY-MM-DD/`;
- incluir capturas **completas** das telas modificadas para histÃ³rico e auditoria visual.

**Arquivos:**
- `README.md` - Ãndice + processo obrigatÃ³rio de registro visual por data
- `AGENDA_REBRANDING_SCREENSHOTS.md` - **ðŸ“¸ Registro visual do rebranding da Agenda** com capturas por viewport

### ðŸ“ /docs/visual

Guia de estilo visual, regras de composiÃ§Ã£o e instruÃ§Ãµes de design frontend do padrÃ£o **DPS Signature**. Esta Ã© a fonte de verdade visual ativa do sistema.

**Arquivos:**
- `FRONTEND_DESIGN_INSTRUCTIONS.md` - **ðŸ†• InstruÃ§Ãµes completas de design frontend** (metodologia, contextos de uso, tipografia, motion, acessibilidade, performance, checklists)
- `VISUAL_STYLE_GUIDE.md` - Guia oficial de estilo visual minimalista
- `registro-rebranding.md` - Registro do rebranding visual do sistema

## Documentos Centrais na Raiz do RepositÃ³rio

Os seguintes documentos permanecem na raiz do repositÃ³rio como **documentos centrais**:

- **`README.md`** - IntroduÃ§Ã£o e visÃ£o geral do projeto
- **`AGENTS.md`** - Regras e diretrizes para contribuidores (humanos e IAs)
- **`ANALYSIS.md`** - VisÃ£o geral de arquitetura, fluxos de integraÃ§Ã£o e contratos
- **`CHANGELOG.md`** - HistÃ³rico de versÃµes e lanÃ§amentos

> âš ï¸ **Importante**: Novos arquivos de documentaÃ§Ã£o **NÃƒO** devem ser criados na raiz do repositÃ³rio. Use as subpastas apropriadas conforme a tabela em `AGENTS.md`.

## Como Usar Esta DocumentaÃ§Ã£o

1. **ðŸŒŸ Para usuÃ¡rios e administradores**: comece com [GUIA_SISTEMA_DPS.md](GUIA_SISTEMA_DPS.md) - guia completo do sistema
2. **Para entender a arquitetura geral**: comece com `ANALYSIS.md` na raiz
3. **Para contribuir com cÃ³digo**: leia `AGENTS.md` na raiz
4. **Para ver o histÃ³rico de mudanÃ§as**: consulte `CHANGELOG.md` na raiz
5. **Para anÃ¡lise de sistema**: veja `docs/analysis/SYSTEM_ANALYSIS_COMPLETE.md`
6. **Para mapeamento BACK-END vs FRONT-END**: veja `docs/analysis/BACKEND_FRONTEND_MAPPING.md`
7. **Para implementaÃ§Ã£o White Label**: veja `docs/analysis/WHITE_LABEL_ANALYSIS.md`
8. **Para detalhes de UX/UI**: navegue atÃ© a subpasta correspondente em `/docs/layout`
9. **Para planos de refatoraÃ§Ã£o**: consulte `/docs/refactoring`
10. **Para padrÃµes visuais**: veja `/docs/visual/VISUAL_STYLE_GUIDE.md`
11. **Para criar/modificar frontends**: veja `/docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md`
12. **Para pÃ¡gina de configuraÃ§Ãµes front-end**: veja `docs/settings/FRONTEND_SETTINGS_IMPLEMENTATION_PLAN.md`
13. **Para registrar mudanÃ§as visuais**: siga `docs/screenshots/README.md` e salve em `docs/screenshots/YYYY-MM-DD/`

## NavegaÃ§Ã£o RÃ¡pida

- [Voltar para raiz do repositÃ³rio](../)
- [Plugin Base](../plugins/desi-pet-shower-base/)
- [Add-ons](../plugins/)
- [AGENTS.md](../AGENTS.md) - Regras de desenvolvimento
- [ANALYSIS.md](../ANALYSIS.md) - Arquitetura
- [CHANGELOG.md](../CHANGELOG.md) - HistÃ³rico
