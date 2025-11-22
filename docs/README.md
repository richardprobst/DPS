# Documentação do Desi Pet Shower (DPS)

Este diretório contém a documentação detalhada de UX, layout, refatoração e planos de implementação do sistema DPS.

## Estrutura da Documentação

### 📁 /docs/layout

Documentação de análises e padrões de layout das interfaces do sistema:

#### `/docs/layout/admin`
Análises e padrões de layout das telas administrativas do WordPress.

**Arquivos:**
- `ADMIN_LAYOUT_ANALYSIS.md` - Análise detalhada do layout das telas administrativas

#### `/docs/layout/agenda`
Documentação visual e de UX da Agenda (add-on de agendamentos).

**Arquivos:**
- `AGENDA_EXECUTIVE_SUMMARY.md` - Sumário executivo da agenda
- `AGENDA_IMPLEMENTATION_SUMMARY.md` - Resumo de implementação
- `AGENDA_INDEX.md` - Índice da documentação da agenda
- `AGENDA_LAYOUT_ANALYSIS.md` - Análise detalhada do layout
- `AGENDA_VISUAL_COMPARISON.md` - Comparação visual de versões
- `AGENDA_VISUAL_SUMMARY.md` - Resumo visual da agenda

#### `/docs/layout/client-portal`
Documentação visual e de UX do Portal do Cliente.

**Arquivos:**
- `CLIENT_PORTAL_IMPLEMENTATION_SUMMARY.md` - Resumo de implementação
- `CLIENT_PORTAL_SUMMARY.md` - Sumário geral do portal
- `CLIENT_PORTAL_UX_ANALYSIS.md` - Análise detalhada de UX (800+ linhas)

#### `/docs/layout/forms`
Documentação dos formulários de cadastro (clientes, pets, etc.).

**Arquivos:**
- `FORMS_IMPROVEMENTS_SUMMARY.md` - Resumo de melhorias implementadas
- `FORMS_UX_ANALYSIS.md` - Análise de UX dos formulários

### 📁 /docs/forms

Documentação específica do formulário de agendamento.

**Arquivos:**
- `SCHEDULING_FORM_IMPROVEMENTS_SUMMARY.md` - Melhorias implementadas
- `SCHEDULING_FORM_UX_ANALYSIS.md` - Análise de UX do formulário

### 📁 /docs/refactoring

Análises e planos de refatoração de código.

**Arquivos:**
- `REFACTORING_ANALYSIS.md` - Análise detalhada de problemas de código e padrões de refatoração recomendados
- `REFACTORING_SUMMARY.md` - Resumo das refatorações planejadas e realizadas

### 📁 /docs/visual

Guia de estilo visual (cores, tipografia, componentes).

**Arquivos:**
- `VISUAL_STYLE_GUIDE.md` - Guia oficial de estilo visual minimalista
- `visual-comparison.html` - Comparação visual de componentes

### 📁 /docs/implementation

Planos e resumos de implementação de melhorias de UX/UI.

**Arquivos:**
- `IMPLEMENTATION_SUMMARY.md` - Resumo geral de implementações
- `UI_UX_IMPROVEMENTS_SUMMARY.md` - Resumo de melhorias de UI/UX

## Documentos Centrais na Raiz

Os seguintes documentos permanecem na raiz do repositório como **documentos centrais**:

- **`AGENTS.md`** - Regras e diretrizes para contribuidores (humanos e IAs)
- **`ANALYSIS.md`** - Visão geral de arquitetura, fluxos de integração e contratos entre núcleo e extensões
- **`CHANGELOG.md`** - Histórico de versões e lançamentos
- **`BACKEND_FRONTEND_MAPPING.md`** - 🆕 Mapeamento completo BACK-END vs FRONT-END (classificação CONFIG vs OPERAÇÃO)
- **`SYSTEM_ANALYSIS_COMPLETE.md`** - Análise profunda do sistema (duplicações, lógica espalhada, sugestões)
- **`SYSTEM_ANALYSIS_SUMMARY.md`** - Resumo executivo da análise com ações priorizadas

## Como Usar Esta Documentação

1. **Para entender a arquitetura geral**: comece com `ANALYSIS.md` na raiz
2. **Para contribuir com código**: leia `AGENTS.md` na raiz
3. **Para ver o histórico de mudanças**: consulte `CHANGELOG.md` na raiz
4. **Para mapeamento BACK-END vs FRONT-END**: veja `BACKEND_FRONTEND_MAPPING.md` na raiz ⚠️
5. **Para análise profunda do sistema**: consulte `SYSTEM_ANALYSIS_COMPLETE.md` ou `SYSTEM_ANALYSIS_SUMMARY.md`
6. **Para detalhes de UX/UI de um componente específico**: navegue até a subpasta correspondente em `/docs/layout`
7. **Para planos de refatoração**: consulte `/docs/refactoring`
8. **Para padrões visuais**: veja `/docs/visual/VISUAL_STYLE_GUIDE.md`

## Navegação Rápida

- [Voltar para raiz do repositório](../)
- [Plugin Base](../plugin/desi-pet-shower-base_plugin/)
- [Add-ons](../add-ons/)
- [AGENTS.md](../AGENTS.md) - Regras de desenvolvimento
- [ANALYSIS.md](../ANALYSIS.md) - Arquitetura
- [CHANGELOG.md](../CHANGELOG.md) - Histórico
- [BACKEND_FRONTEND_MAPPING.md](../BACKEND_FRONTEND_MAPPING.md) - 🆕 Mapeamento CONFIG vs OPERAÇÃO
- [SYSTEM_ANALYSIS_COMPLETE.md](../SYSTEM_ANALYSIS_COMPLETE.md) - Análise completa
- [SYSTEM_ANALYSIS_SUMMARY.md](../SYSTEM_ANALYSIS_SUMMARY.md) - Resumo executivo
