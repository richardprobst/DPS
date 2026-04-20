# Documentação do desi.pet by PRObst (DPS)

Este diretório contém a documentação detalhada de UX, layout, refatoração e planos de implementação do sistema DPS.

## 📚 Documentação Principal

> **🌟 [GUIA_SISTEMA_DPS.md](GUIA_SISTEMA_DPS.md)** - Guia completo de apresentação, instalação, configuração e uso do sistema. Documento principal para usuários e administradores.

> **🚀 [FRONTEND_ADDON_GUIA_USUARIO.md](FRONTEND_ADDON_GUIA_USUARIO.md)** - **🆕** Guia completo do Frontend Add-on. Instalação, configuração, shortcodes, criação de páginas e personalização visual. Documento essencial para usar o novo add-on modular de experiências frontend (cadastro, agendamento, configurações).

> **🔧 [FUNCTIONS_REFERENCE.md](FUNCTIONS_REFERENCE.md)** - Referência completa de TODAS as funções e métodos do DPS. Guia definitivo para desenvolvedores trabalhando com o sistema (8.233 linhas, 385+ funções/métodos documentados, cobrindo plugin base + 16 add-ons).

O guia inclui:
- Apresentação do sistema e funcionalidades
- Instalação do plugin base e add-ons
- Configuração detalhada de cada componente
- Instruções de uso passo a passo
- **🆕 Guia Passo a Passo do GitHub Updater** - Como atualizar o sistema de forma fácil
- Resolução de problemas comuns
- Referência técnica (shortcodes, roles, estrutura de dados)

**⚠️ Mantenha este documento atualizado** sempre que houver mudanças no sistema.

---

## Estrutura da Documentação

### 📁 /docs/admin

Análises e planos relacionados à interface administrativa do WordPress.

**Arquivos:**
- `ADMIN_CPT_INTERFACE_ANALYSIS.md` - Análise completa sobre habilitação da interface admin nativa para CPTs
- `ADMIN_CPT_INTERFACE_SUMMARY.md` - Resumo executivo da análise de interface admin para CPTs
- `ADMIN_UI_MOCKUP.md` - Mockup da interface administrativa dos CPTs
- `CPT_UI_ENABLEMENT_SUMMARY.md` - Resumo da habilitação da UI nativa para CPTs
- `RESULTADO_FINAL_CPT_UI.md` - Resultado final da habilitação de UI para CPTs

### 📁 /docs/analysis

Análises arquiteturais e de sistema.

**Arquivos:**
- `ADDONS_DETAILED_ANALYSIS.md` - Análise detalhada de todos os 15 add-ons
- `BACKEND_FRONTEND_MAPPING.md` - Mapeamento completo BACK-END vs FRONT-END
- `GOOGLE_TASKS_INTEGRATION_ANALYSIS.md` - **🆕** Análise completa de integração com Google Tarefas (31KB, arquitetura, casos de uso, segurança, estimativas)
- `GOOGLE_TASKS_INTEGRATION_SUMMARY.md` - **🆕** Resumo executivo da integração Google Tarefas (recomendação: viável e interessante)
- `STOCK_ADDON_ANALYSIS.md` - Análise profunda do Add-on Estoque (funcionalidade, integração com Serviços, fluxo de uso)
- `SUBSCRIPTION_ADDON_ANALYSIS.md` - Análise profunda do Add-on Assinaturas (código, funcionalidades, layout, melhorias propostas)
- `SYSTEM_ANALYSIS_COMPLETE.md` - Análise profunda do sistema
- `SYSTEM_ANALYSIS_SUMMARY.md` - Resumo executivo da análise de sistema
- `WHITE_LABEL_ANALYSIS.md` - Análise completa de implementação White Label (segurança, funcionalidades, arquitetura proposta)

### 📁 /docs/compatibility

Documentação de compatibilidade com temas e page builders.

**Arquivos:**
- `COMPATIBILITY_ANALYSIS.md` - Análise geral de compatibilidade
- `EDITOR_SHORTCODE_GUIDE.md` - **🆕** Guia de como inserir shortcodes no editor WordPress (solução para problema comum)
- `RESOLUTION_SUMMARY.md` - Resumo de resoluções de compatibilidade
- `YOOTHEME_COMPATIBILITY.md` - Guia de compatibilidade com YooTheme PRO
- `YOOTHEME_RESPOSTA_RAPIDA.md` - Resposta rápida para problemas YooTheme

### 📁 /docs/fixes

Correções e diagnósticos.

**Arquivos:**
- `FINANCE_ACTIVATION_FIX_SUMMARY.md` - Correção do activation hook do Finance Add-on
- `PORTAL_LAYOUT_FIX.md` - Correções de layout do Portal do Cliente
- `TRANSLATION_LOADING_FIX_VERIFICATION.md` - Verificação de correções de carregamento de traduções

### 📁 /docs/forms

Documentação específica de formulários e inputs.

**Arquivos:**
- `AGENDAMENTOS_RESPONSIVENESS_FIXES.md` - Correções de responsividade em agendamentos
- `AGENDAMENTO_SERVICOS_MELHORIAS_IMPLEMENTADAS.md` - Melhorias implementadas
- `APPOINTMENT_FORM_FIXES_SUMMARY.md` - Resumo de correções do formulário
- `APPOINTMENT_FORM_LAYOUT_FIXES.md` - Correções de layout
- `APPOINTMENT_FORM_VISUAL_COMPARISON.md` - Comparação visual
- `EXECUTIVE_SUMMARY_RESPONSIVENESS_FIXES.md` - Resumo executivo de responsividade
- `INVESTIGACAO_COMPLETA_AGENDAMENTO_SERVICOS.md` - Investigação completa
- `SCHEDULING_FORM_IMPROVEMENTS_SUMMARY.md` - Melhorias implementadas
- `SCHEDULING_FORM_UX_ANALYSIS.md` - Análise de UX
- `agendamentos-responsive-test.html` - Arquivo de teste responsivo

### 📁 /docs/implementation

Resumos de implementação de features.

**Arquivos:**
- `IMPLEMENTATION_SUMMARY.md` - Resumo geral de implementações
- `IMPLEMENTATION_SUMMARY_CLIENT_PORTAL_MENUS.md` - Implementação de menus do Portal do Cliente
- `RESPOSTA_IMPLEMENTACAO_AI_COMUNICACOES.md` - Implementação de IA para comunicações
- `SERVICES_AGENDA_INTEGRATION_SUMMARY.md` - Integração Services ⇄ Agenda
- `UI_UX_IMPROVEMENTS_SUMMARY.md` - Resumo de melhorias de UI/UX
- `WHATSAPP_IMPLEMENTATION_SUMMARY.md` - Implementação de integração WhatsApp
- `FRONTEND_ROLLOUT_GUIDE.md` - **🆕** Guia operacional de rollout do Frontend Add-on (ativação por ambiente, verificação, monitoramento)
- `FRONTEND_RUNBOOK.md` - **🆕** Runbook de incidentes do Frontend Add-on (diagnóstico, rollback, cenários)
- `PLANO_IMPLEMENTACAO_FASES.md` - **🆕** Plano completo de implementação em 8 fases (Segurança → Refatoração → Performance → UX → Features → Auditoria → Testes → Integrações)

### 📁 /docs/improvements

Melhorias gerais do sistema.

**Arquivos:**
- `AGENDA_RESPONSIVENESS_IMPROVEMENTS.md` - Melhorias de responsividade da Agenda

### 📁 /docs/layout

Documentação de layout e UX, organizada em subpastas:

**Arquivos na raiz:**
- `RESPONSIVENESS_ANALYSIS.md` - **🆕** Análise completa de responsividade de todo o sistema (plugin base + 15 add-ons)

#### `/docs/layout/admin`
- `ADMIN_LAYOUT_ANALYSIS.md` - Análise detalhada do layout das telas administrativas

#### `/docs/layout/agenda`
- `AGENDA_EXECUTIVE_SUMMARY.md` - Sumário executivo da agenda
- `AGENDA_IMPLEMENTATION_SUMMARY.md` - Resumo de implementação
- `AGENDA_INDEX.md` - Índice da documentação da agenda
- `AGENDA_LAYOUT_ANALYSIS.md` - Análise detalhada do layout
- `AGENDA_VISUAL_COMPARISON.md` - Comparação visual de versões
- `AGENDA_VISUAL_SUMMARY.md` - Resumo visual da agenda

#### `/docs/layout/site`
- `README.md` - Guia da área de páginas públicas para WordPress + Flatsome
- `site-pages.manifest.json` - Manifesto central com mapeamento URL -> slug -> arquivo HTML local
- `flatsome/flatsome-additional-css.css` - CSS compartilhado para colar no CSS adicional do tema
- `pages/` - Páginas públicas editáveis organizadas por slug, cada uma com `page-content.html` pronto para colar no WordPress

#### `/docs/layout/client-portal`
- `CLIENT_PORTAL_IMPLEMENTATION_SUMMARY.md` - Resumo de implementação
- `CLIENT_PORTAL_SUMMARY.md` - Sumário geral do portal
- `CLIENT_PORTAL_UX_ANALYSIS.md` - Análise detalhada de UX
- `PORTAL_DEMO_README.md` - README da demo do portal
- `portal-cliente-demo.html` - Demo HTML do portal

#### `/docs/layout/forms`
- `FORMS_IMPROVEMENTS_SUMMARY.md` - Resumo de melhorias implementadas
- `FORMS_UX_ANALYSIS.md` - Análise de UX dos formulários

### 📁 /docs/performance

Análises e otimizações de performance.

**Arquivos:**
- `EXEMPLOS_PERFORMANCE_ADDONS.md` - Exemplos de otimização em add-ons
- `PERFORMANCE_REVIEW_ADDONS.md` - Revisão de performance dos add-ons

### 📁 /docs/qa

Quality assurance e validação funcional de add-ons.

**Arquivos:**
- `AI_ADDON_FUNCTIONAL_QA.md` - QA funcional do AI Add-on
- `CLIENT_PORTAL_FUNCTIONAL_VALIDATION.md` - Validação funcional do Portal do Cliente
- `FUNCTIONAL_VERIFICATION_REPORT.md` - Relatório de verificação funcional geral
- `GROOMERS_FUNCTIONAL_QA.md` - QA funcional do Groomers Add-on
- `FRONTEND_COMPATIBILITY_MATRIX.md` - **🆕** Matriz de compatibilidade do Frontend Add-on com todos os 18 add-ons
- `FRONTEND_REMOVAL_READINESS.md` - **🆕** Checklist de prontidão para remoção futura de legado

### 📁 /docs/refactoring

Análises e planos de refatoração de código.

**Arquivos:**
- `AGENT_ENGINEERING_PLAYBOOK.md` - Playbook complementar para agentes com princípios, arquitetura e DoD de implementação
- `FRONTEND_ADDON_PHASED_ROADMAP.md` - Plano amplo e faseado para criação do add-on FRONTEND com compatibilidade, rollout e preparação para remoção futura de legado (Fase 1-6 concluídas)
- `FRONTEND_DEPRECATION_POLICY.md` - Política formal de depreciação (janela mínima, comunicação, critérios de aceite, procedimento)
- `FRONTEND_REMOVAL_TARGETS.md` - Lista de alvos de remoção com dependências, risco, esforço e plano de reversão
- `FRONTEND_NATIVE_IMPLEMENTATION_PLAN.md` - **🆕 FASE 7:** Plano completo para implementação nativa (from-scratch) com páginas 100% novas, substituindo estratégia dual-run por código nativo da fase visual anterior

### 📁 /docs/review

Revisões de código e PRs.

**Arquivos:**
- `PLUGIN_BASE_CODE_REVIEW.md` - Revisão de código do plugin base

#### `/docs/review/pr-161`
Verificação completa do PR #161 (alinhamento de preços de serviços):
- `INDEX_PR_161_VERIFICATION.md` - Índice da verificação
- `PR_161_CORRECTED_CSS.css` - CSS corrigido
- `PR_161_EXECUTIVE_SUMMARY.md` - Resumo executivo
- `PR_161_SIDE_BY_SIDE_COMPARISON.md` - Comparação lado a lado
- `PR_161_VERIFICATION.md` - Verificação detalhada
- `README_VERIFICATION.md` - README da verificação

### 📁 /docs/settings

Documentação de configurações do sistema.

**Arquivos:**
- `FRONTEND_SETTINGS_IMPLEMENTATION_PLAN.md` - **🆕** Plano detalhado para implementação da página de configurações front-end (`[dps_configuracoes]`)
- `GROOMERS_SETTINGS_VERIFICATION.md` - Verificação das configurações do Groomers Add-on

### 📁 /docs/security

Documentação de segurança e auditoria.

**Arquivos:**
- `AUDIT_FASE1.md` - Auditoria de segurança completa — Fase 1 do Plano de Implementação
- `EXEMPLOS_ANTES_DEPOIS_SEGURANCA.md` - Exemplos antes/depois de correções de segurança
- `SECURITY_FIXES_FINANCE_SUMMARY.md` - Resumo de correções de segurança do Finance Add-on

### 📁 /docs/screenshots

**🆕 Documentação visual e capturas de tela do sistema.**

**Padrão obrigatório para mudanças visuais:**
- registrar documentação da alteração (contexto, antes/depois, telas impactadas e arquivos alterados);
- salvar as capturas em subpastas por data: `docs/screenshots/YYYY-MM-DD/`;
- incluir capturas **completas** das telas modificadas para histórico e auditoria visual.

**Arquivos:**
- `README.md` - Índice + processo obrigatório de registro visual por data
- `AGENDA_REBRANDING_SCREENSHOTS.md` - **📸 Registro visual do rebranding da Agenda** com capturas por viewport

### 📁 /docs/visual

Sistema visual proprietário do DPS: identidade, tokens, componentes e instruções completas de design frontend.

**Arquivos:**
- `FRONTEND_DESIGN_INSTRUCTIONS.md` - **🆕 Instruções completas do sistema visual `DPS Signature`** (metodologia, contextos de uso, composição, tipografia, cor, motion, acessibilidade, performance e checklist)
- `VISUAL_STYLE_GUIDE.md` - Guia oficial de estilo visual do `DPS Signature` com tokens, componentes e exemplos
- `registro-rebranding.md` - Registro da transição da linguagem visual do sistema

## Documentos Centrais na Raiz do Repositório

Os seguintes documentos permanecem na raiz do repositório como **documentos centrais**:

- **`README.md`** - Introdução e visão geral do projeto
- **`AGENTS.md`** - Regras e diretrizes para contribuidores (humanos e IAs)
- **`ANALYSIS.md`** - Visão geral de arquitetura, fluxos de integração e contratos
- **`CHANGELOG.md`** - Histórico de versões e lançamentos

> ⚠️ **Importante**: Novos arquivos de documentação **NÃO** devem ser criados na raiz do repositório. Use as subpastas apropriadas conforme a tabela em `AGENTS.md`.

## Como Usar Esta Documentação

1. **🌟 Para usuários e administradores**: comece com [GUIA_SISTEMA_DPS.md](GUIA_SISTEMA_DPS.md) - guia completo do sistema
2. **Para entender a arquitetura geral**: comece com `ANALYSIS.md` na raiz
3. **Para contribuir com código**: leia `AGENTS.md` na raiz
4. **Para ver o histórico de mudanças**: consulte `CHANGELOG.md` na raiz
5. **Para análise de sistema**: veja `docs/analysis/SYSTEM_ANALYSIS_COMPLETE.md`
6. **Para mapeamento BACK-END vs FRONT-END**: veja `docs/analysis/BACKEND_FRONTEND_MAPPING.md`
7. **Para implementação White Label**: veja `docs/analysis/WHITE_LABEL_ANALYSIS.md`
8. **Para detalhes de UX/UI**: navegue até a subpasta correspondente em `/docs/layout`
9. **Para planos de refatoração**: consulte `/docs/refactoring`
10. **Para padrões visuais**: veja `/docs/visual/VISUAL_STYLE_GUIDE.md`
11. **Para criar/modificar frontends**: veja `/docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md`
12. **Para página de configurações front-end**: veja `docs/settings/FRONTEND_SETTINGS_IMPLEMENTATION_PLAN.md`
13. **Para registrar mudanças visuais**: siga `docs/screenshots/README.md` e salve em `docs/screenshots/YYYY-MM-DD/`

## Navegação Rápida

- [Voltar para raiz do repositório](../)
- [Plugin Base](../plugins/desi-pet-shower-base/)
- [Add-ons](../plugins/)
- [AGENTS.md](../AGENTS.md) - Regras de desenvolvimento
- [ANALYSIS.md](../ANALYSIS.md) - Arquitetura
- [CHANGELOG.md](../CHANGELOG.md) - Histórico
