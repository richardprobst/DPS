# Diretrizes para agentes do desi.pet by PRObst

**Autor:** PRObst  
**Site:** <a href="https://www.probst.pro">www.probst.pro</a>

## Filosofia (guardrails, não algemas)

Este documento existe para **proteger o sistema** (segurança, compatibilidade, contratos entre plugins) sem engessar a criatividade.
- **Default = autonomia:** se algo não estiver em **MUST** ou **ASK BEFORE**, o agente pode escolher a melhor abordagem.
- **Preferir pragmatismo:** entregue valor com o menor risco. Refatore quando fizer sentido, mas evite “refactors por esporte”.
- **Quando houver trade-off:** escolha uma opção e deixe **2–3 linhas** registrando a alternativa e por que não foi usada.
- **Contexto e aplicabilidade:** se uma diretriz não se aplica ao caso concreto, registre em 1–2 linhas o motivo.

## Como usar este documento (trilhas)

Antes de começar, classifique a mudança:

### Trilha A — Mudança pequena (rápida) ✅ (padrão)
Use quando a mudança for local e **não** mexer em contratos, schema compartilhado, autenticação, ou UX ampla.
- Faça a implementação.
- Respeite **MUST** (principalmente segurança).
- Rode validações aplicáveis (ver “Setup & validação”).
- Atualize docs **somente se** algum gatilho abaixo for acionado.

### Trilha B — Mudança estrutural 🧱
Use quando acionar **qualquer** gatilho:
- Alteração de **schema/tabelas compartilhadas** (ex.: `dps_transacoes`, `dps_parcelas`).
- Mudança em **assinaturas/contratos de hooks**, ou comportamento consumido por add-ons.
- Mudança relevante de **menus/admin**, flags (`show_ui`, `show_in_menu`), rotas REST/AJAX, autenticação.
- Nova **dependência externa** (API/SDK) ou fluxo sensível (pagamentos, webhooks).
- Mudança grande de UX (navegação, telas principais, fluxos do cliente).

Na Trilha B:
- Consulte as seções relevantes do **ANALYSIS.md** antes de codar.
- Documente impacto em **ANALYSIS.md** (e **CHANGELOG.md** quando user-facing).
- Se houver risco de quebra, aplique **ASK BEFORE**.

---


## Guia complementar de engenharia para agentes

Para manter este arquivo mais enxuto, as diretrizes operacionais de engenharia (arquitetura de código, DoD e processo de execução) ficam centralizadas em:
- `docs/refactoring/AGENT_ENGINEERING_PLAYBOOK.md`

Use esse playbook quando a tarefa envolver implementação/refatoração de código no core ou add-ons.
Ele complementa este AGENTS.md sem substituir as regras de **MUST** e **ASK BEFORE**.

## Uso de agentes especializados (quando disponível)

- Sempre que possível, priorize um **agente especializado/skill** para executar tarefas de implementação.
- Para mudanças de **código** (backend, arquitetura, integrações), prefira agente focado em engenharia/código.
- Para mudanças de **frontend/UI/UX/layout**, prefira agente focado em frontend/design e mantenha conformidade com `docs/visual/`.
- Se não houver agente especializado disponível no ambiente atual, siga com a implementação padrão e registre em 1–2 linhas o motivo.

---

## Escopo
Estas orientações cobrem todo o repositório desi.pet by PRObst, incluindo todos os plugins em `plugins/`.
Se existir um `AGENTS.md` mais específico em subdiretórios, **ele prevalece** para arquivos dentro de seu escopo.

## Estrutura do repositório
- **plugins/**: pasta única contendo todos os plugins (base + add-ons), cada um em sua própria subpasta:
  - `desi-pet-shower-base/` — plugin núcleo com ponto de entrada, includes e assets compartilhados.
  - `desi-pet-shower-*` — add-ons opcionais, cada um com arquivo principal próprio e subpastas por funcionalidade.
- **docs/**: documentação detalhada de UX, layout, refatoração e planos de implementação (veja `/docs/README.md` para índice completo).
- **ANALYSIS.md**: visão arquitetural, fluxos de integração e contratos entre núcleo e extensões.
- **CHANGELOG.md**: histórico de versões e lançamentos (atualizar a cada release).
- **plugins/desi-pet-shower-base/includes/refactoring-examples.php**: exemplos práticos de uso correto das classes helper globais.

> **Dica (monorepo):** quando necessário, crie `AGENTS.md` dentro de plugins para comandos/contratos locais. Mantenha o root como “constituição” e os específicos como “manual do módulo”.

## Organização de arquivos

### Arquivos permitidos na raiz do repositório
Apenas os seguintes arquivos devem permanecer na raiz:
- `README.md` — introdução e visão geral do projeto
- `AGENTS.md` — diretrizes para agentes (humanos e IA)
- `ANALYSIS.md` — visão arquitetural do sistema
- `CHANGELOG.md` — histórico de versões
- `.gitignore` — configuração do Git

### Estrutura da pasta docs/
Toda documentação adicional deve ser organizada nas seguintes subpastas:

| Pasta | Propósito | Exemplos |
|-------|-----------|----------|
| `docs/admin/` | Interface administrativa, CPTs, menus | Análises de UI admin, mockups, habilitação de CPTs |
| `docs/analysis/` | Análises arquiteturais e de sistema | Análises de add-ons, mapeamentos backend/frontend |
| `docs/compatibility/` | Compatibilidade com temas e plugins | YooTheme, Elementor, page builders |
| `docs/fixes/` | Correções e diagnósticos | Fixes de ativação, correções de layout |
| `docs/forms/` | Formulários e inputs | Análises de UX, melhorias de campos |
| `docs/implementation/` | Resumos de implementação | Sumários de features implementadas |
| `docs/improvements/` | Melhorias gerais | Propostas e análises de melhoria |
| `docs/layout/` | Layout e UX (com subpastas) | `admin/`, `agenda/`, `client-portal/`, `forms/` |
| `docs/performance/` | Otimizações de performance | Análises e guias de performance |
| `docs/refactoring/` | Refatoração de código | Planos, análises, diagramas |
| `docs/review/` | Revisões de código e PRs | Verificações de PRs (ex: `pr-161/`) |
| `docs/security/` | Segurança e auditoria | Correções de segurança, exemplos de vulnerabilidades |
| `docs/settings/` | Configurações do sistema | Planos de implementação, verificações de configurações |
| `docs/screenshots/` | Documentação visual e capturas | Screenshots, guias visuais de componentes |
| `docs/visual/` | Estilo visual e design | Guias de estilo, comparações visuais |
| `docs/qa/` | Quality assurance e validação funcional | Relatórios de QA, validações de add-ons |

### Regras para novos arquivos de documentação
1. **NUNCA** criar arquivos `.md` soltos na raiz (exceto os listados acima)
2. Escolha a categoria mais apropriada na tabela acima
3. Se nenhuma categoria existir, crie nova subpasta em `docs/` e documente-a aqui
4. Revisões de PRs: `docs/review/pr-XXX/` (XXX = número do PR)
5. Demos HTML devem acompanhar a doc relacionada
6. Mantenha `docs/README.md` atualizado ao adicionar novas pastas/categorias

### Validação dos apontamentos (evitar links quebrados)
Sempre que atualizar documentação estrutural (`AGENTS.md`, `docs/README.md`, índices e guias), valide se os caminhos citados existem no repositório atual.

Comandos úteis:
- `find docs -maxdepth 3 -type f | sort` (inventário rápido da documentação)
- `rg -n "docs/" AGENTS.md docs/README.md docs/screenshots/README.md` (checagem de apontamentos)

Se um caminho citado não existir mais, atualize o apontamento na mesma entrega.


## Setup & validação

Antes de finalizar qualquer tarefa, execute validações proporcionais ao impacto:
- **Documentação apenas:** `git diff --check` + conferência dos caminhos citados.
- **PHP alterado:** `php -l` nos arquivos modificados.
- **Mudança funcional relevante:** validação local no WordPress dos fluxos afetados.
- **Mudança visual/layout:** seguir `docs/visual/` (padrão M3) + validar responsividade nos breakpoints definidos pelo sistema + registrar screenshots conforme `docs/screenshots/README.md`.

Sempre registrar no fechamento os comandos executados e o status (passou/falhou/limitação de ambiente).

---

## Versionamento e git-flow (leve e prático)
- Utilize SemVer (MAJOR.MINOR.PATCH) para o plugin base e para cada add-on.
- Branches (sugestão):
  - `main`: estável; merges revisados.
  - `develop`: integração antes de release.
  - `feature/<slug>`: funcionalidades.
  - `hotfix/<slug>`: correções urgentes sobre `main`.
- Releases:
  - Atualize `CHANGELOG.md` e versões dos plugins antes de tag.
  - Tags anotadas: `git tag -a vX.Y.Z`.
- Commits: preferir mensagens curtas em português, no imperativo (ex.: “Corrigir validação de CPF”).

---

## Requisitos mínimos e níveis de regra

### Versões mínimas
Todos os plugins/add-ons DEVEM declarar:
- `Requires at least: 6.9`
- `Requires PHP: 8.4`

### MUST (obrigatório)
- **Segurança**: validar **nonce + capability + sanitização/escape** em toda entrada/saída (inclui AJAX e REST).
- **I18n e bootstrap**: carregar text domain em `init` (prioridade 1) e inicializar classes principais em `init` (prioridade 5) após o text domain.
- **Admin menus**: registrar menus e páginas administrativas sempre como **submenus** do menu pai `desi-pet-shower` (capability `manage_options`, `admin_menu` prioridade 20). Não usar `add_menu_page` próprio nem `parent=null`.
- **Banco**: versionar alterações de banco (option de versão + `dbDelta()` somente quando a versão salva for menor que a atual; nunca em todo request).
- **Contratos**: preservar assinaturas de hooks/tabelas compartilhadas. Se precisar mudar, criar novo hook e manter compatibilidade com depreciação documentada.
- **Segredos**: nunca expor segredos em código; usar constantes ou variáveis de ambiente.
- **Cache proibido**: é **proibido** implementar qualquer tipo de cache no sistema — inclui, mas não se limita a: transients do WordPress (`set_transient`, `get_transient`), object cache (`wp_cache_*`), cache de queries, cache de fragmentos HTML, cache de API responses, cache em arquivos, e qualquer mecanismo customizado de armazenamento temporário para reutilização. Todas as consultas e renderizações devem ser executadas em tempo real, sem camadas intermediárias de cache.

### ASK BEFORE (requer validação humana)
- Alterar schema de tabelas compartilhadas (`dps_transacoes`, `dps_parcelas`, etc.).
- Mudanças grandes de UX ou novas dependências externas (APIs/SDKs).
- Alterar assinaturas de hooks existentes ou fluxos críticos de autenticação.

### PREFER (recomendado)
- Reutilizar helpers globais (`DPS_Phone_Helper`, `DPS_Money_Helper`, `DPS_URL_Builder`, etc.) em vez de duplicar validações/formatadores.
- Registrar assets de forma condicional (apenas nas páginas/abas relevantes).
- Para CPTs no admin: usar `show_in_menu => 'desi-pet-shower'` quando aplicável e otimizar queries (`fields => 'ids'`, `no_found_rows`, `update_meta_cache()`).

---

## Documentação (sem burocracia)

- Documentação em português, clara e orientada a passos.
- **Atualize docs somente quando houver impacto real** (Trilha B ou user-facing):
  - `ANALYSIS.md`: contratos, hooks, menus, flags, fluxos de integração, novas extensões.
  - `CHANGELOG.md`: mudanças que chegam ao usuário/integrador (Added/Changed/Fixed/Removed/Deprecated/Security/Refactoring).
- **Novos add-ons**: adicione seção no `ANALYSIS.md` contendo:
  - diretório, propósito, hooks utilizados/expostos, dependências, tabelas, shortcodes/CPTs/capabilities.

---

## Convenções de código
- WordPress: indentação 4 espaços.
- Funções globais em `snake_case`; métodos/propriedades de classe em `camelCase`.
- Escape e sanitização obrigatórios (`esc_html__`, `esc_attr`, `wp_nonce_*`, `sanitize_text_field`, etc.).
- `require/require_once` organizados (sem envolver imports em `try/catch`).
- Assets: prefira `wp_register_*` + `wp_enqueue_*` em pontos específicos; evite carregar no site inteiro.
- Hooks/options/handles prefixados com `dps_`.
- **Deprecated**: evitar funções/classes obsoletas de WP, PHP, JS e jQuery. Quando encontrar código deprecated, atualizar para a alternativa moderna recomendada. Referências úteis: [WordPress Developer Resources](https://developer.wordpress.org/reference/), guias de migração PHP e notas de release do jQuery.

---

## Boas práticas WordPress (arquitetura e APIs)
- **Priorize APIs nativas** (Settings API, REST API, WP_Query, `$wpdb->prepare()`) antes de soluções customizadas.
- **Hooks first**: prefira estender via `add_action`/`add_filter` em vez de alterar fluxo direto do núcleo/base.
- **Enqueue correto**: registre e carregue scripts/estilos apenas nas telas necessárias, com dependências explícitas.
- **I18n sempre que houver UI**: strings visíveis ao usuário devem usar funções de tradução.
- **Separação clara**: lógica de negócio em classes/helpers, UI em templates/partials, assets em `assets/`.

---

## UI/UX (diretrizes mínimas)
- Use cores com propósito (status/alertas/ação).
- Mantenha hierarquia semântica (H1 único, H2 seções, H3 subseções).
- Feedback consistente: use `DPS_Message_Helper` para sucesso/erro/aviso.
- **Responsividade é obrigatória** em toda entrega visual/frontend: o agente deve projetar, implementar e revisar a interface para uso real em telas pequenas, médias e grandes, evitando depender de ajustes posteriores.
- Validar layouts, navegação, densidade, legibilidade e áreas de toque nos breakpoints de referência do sistema: **375px, 600px, 840px, 1200px e 1920px**.
- Em mudanças visuais, tratar como defeito: overflow horizontal, conteúdo cortado, CTA inacessível, tabela sem estratégia mobile, modal que excede a viewport, targets de toque insuficientes e hierarquia comprometida entre breakpoints.
- **Obrigatório:** qualquer tarefa de **layout/design/frontend** (criar, recriar, corrigir ou ajustar UI) **DEVE** seguir as especificações de `docs/visual/` (ver referências abaixo).

Referências de design e layout (padrão visual **M3** do sistema):
- `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` — **instruções completas de design frontend** (metodologia, contextos de uso, acessibilidade, performance, checklist)
- `docs/visual/VISUAL_STYLE_GUIDE.md` — paleta, componentes e espaçamento

**Regra de comunicação obrigatória:** sempre que uma tarefa envolver visual/layout/frontend (criação, ajuste, correção, refatoração ou revisão), o agente **deve indicar explicitamente** que seguiu `docs/visual/` como fonte de verdade do padrão M3.

**Registro obrigatório para mudanças visuais:** qualquer alteração visual/layout/frontend deve ser documentada com:
- descrição objetiva do que mudou (antes/depois, impacto e arquivos afetados);
- capturas **completas** das telas alteradas para registro em `docs/screenshots/`;
- organização dos registros em subpastas por data no formato `docs/screenshots/YYYY-MM-DD/`.

Sempre orientar e confirmar no fechamento/PR onde os prints e o documento de registro foram salvos.

### Fluxo obrigatório para mudanças visuais (M3)
1. Consultar `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md` antes de implementar.
2. Implementar a mudança mantendo coerência com o padrão M3 e com responsividade real para small/medium/large screens.
3. Gerar capturas completas das telas alteradas.
4. Salvar artefatos em `docs/screenshots/YYYY-MM-DD/`.
5. Criar/atualizar o documento do dia (`SCREENSHOTS_YYYY-MM-DD.md`) com contexto, antes/depois, breakpoints validados e lista dos arquivos.
6. Citar no fechamento/PR os caminhos dos registros e prints salvos.

---

## Diretrizes para add-ons
- Cada add-on deve manter um arquivo principal `desi-pet-shower-<feature>-addon.php` e, se preciso, subpastas `includes/` e `assets/`.
- Use hooks de extensão do núcleo (`dps_base_nav_tabs_*`, `dps_base_sections_*`, `dps_settings_*`) **sem alterar assinaturas existentes**.
- Reutilize a tabela `dps_transacoes` e contratos de metadados para fluxos financeiros/assinaturas.
- Documente dependências entre add-ons (ex.: Financeiro + Assinaturas) quando houver integração real.
- Assets apenas em páginas relevantes; considere colisões com temas/plugins.
- Menus/admin pages de add-ons devem ser submenus de `desi-pet-shower`.

---

## Auditoria e análise de código

### Escopo de análise
- **Incluir**: código PHP, JS, CSS, templates e assets dos plugins em `plugins/`.
- **Ignorar** (não analisar nem alterar): `vendor/`, `node_modules/`, `dist/`, `build/`, `coverage/`, arquivos minificados (`*.min.js`, `*.min.css`), caches e dependências externas.

### Fluxo de auditoria (quando aplicável)
Para tarefas de auditoria, revisão ampla ou refatoração significativa:
1. **Inventário**: mapear entrypoints, includes, classes, hooks, shortcodes, REST/AJAX, admin pages e cron.
2. **Relatório de achados** (antes de mudanças grandes):
   - Categorias: Deprecated / Duplicado / Morto / Segurança / Performance / Manutenibilidade.
   - Severidade: Alta / Média / Baixa.
   - Evidência: `arquivo:linha` (ou trecho).
   - Recomendação.
3. **Correções em patches isolados**: mudanças pequenas, cada uma com justificativa clara.
4. **Perguntar antes** nas situações listadas em **ASK BEFORE** (ver acima).

### Heurísticas para detecção de problemas
- **Código morto**: funções/classes sem referências, arquivos nunca incluídos, callbacks não registrados, hooks inexistentes. **Atenção**: verificar também chamadas dinâmicas (`call_user_func`, `do_action`/`apply_filters` com nomes de hook variáveis, instanciação via strings) antes de considerar código como morto.
- **Duplicação**: mesmas validações/sanitizações repetidas, mesma lógica de montagem de arrays, mesmos patterns de `$wpdb`, mesmo HTML gerado em múltiplos pontos.
- **Deprecated**: uso de funções/classes/parâmetros marcados como deprecated no WP, PHP ou jQuery/JS.

### Políticas de mudança em auditorias
- Não reformatar o projeto inteiro de uma vez.
- Não "modernizar" tudo simultaneamente; preferir mudanças incrementais e seguras.
- Não remover funcionalidades sem evidência clara de que são código morto. Verificar referências estáticas e dinâmicas (`add_action`/`add_filter` com variáveis, `$wp_filter`, autoloading, `require` condicional).

---

## Liberdade x segurança

### Priorização de mudanças
Ao decidir o que corrigir ou melhorar, siga esta ordem de prioridade:
1. **Segurança** (vulnerabilidades, falhas de validação)
2. **Bugs** (comportamento incorreto)
3. **Compatibilidade** (deprecated, breaking changes)
4. **Performance** (queries lentas, assets desnecessários)
5. **Limpeza** (código morto, duplicação, legibilidade)

### Autorizado (e incentivado) quando for seguro
- ✅ Corrigir bugs encontrados no caminho **quando a correção for claramente segura** e não ampliar escopo sem necessidade.
- ✅ Quebrar funções grandes em métodos menores (clareza e testabilidade).
- ✅ Extrair helpers reutilizáveis quando houver duplicação real.
- ✅ Melhorar DocBlocks e nomenclatura.
- ✅ Otimizar queries quando houver ganho e baixo risco.
- ✅ Adicionar hooks novos (documentar no `ANALYSIS.md` com assinatura, propósito e exemplo).
- ✅ Melhorar segurança (reforçar validações, escapes e sanitização).

### Evitar sem validação extra
- ❌ Afrouxar validações de segurança (sempre reforçar, nunca remover).
- ❌ Mudar assinaturas de hooks existentes (crie novos hooks e deprecie os antigos).
- ❌ Remover/modificar capabilities sem análise de impacto.
- ❌ Alterar schema de tabelas compartilhadas sem migração reversível + documentação + validação (ASK BEFORE).
- ❌ Implementar qualquer tipo de cache (transients, object cache, cache de queries, cache de fragmentos, cache em arquivos ou qualquer mecanismo de armazenamento temporário). Ver regra **Cache proibido** na seção MUST.

**Princípio geral:** na dúvida, prefira adicionalidade (criar novo em vez de quebrar existente) e documente o mínimo necessário.

---

## Integração núcleo ⇄ extensões
- Novos pontos de extensão no núcleo devem ter documentação mínima no `ANALYSIS.md` (assinatura, propósito, exemplos).
- Compatibilidade retroativa: introduza novos hooks sem quebrar os existentes; depreciações no `CHANGELOG.md` com versão alvo.
- Fluxos compartilhados (agendamento, pagamentos, notificações): centralize no núcleo e reutilize nos add-ons.
- Esquemas de dados compartilhados: migrações reversíveis + validação de sincronização.

---

## Políticas de segurança obrigatórias
- Nonces em formulários e ações autenticadas; rejeitar requisições sem verificação.
- Escape de saída em HTML/atributos/JS inline; sanitize toda entrada do usuário (inclui webhooks).
- Menor privilégio para capabilities.
- Segredos apenas via constantes/variáveis de ambiente; nunca commitar tokens.
- Correções de segurança: registrar em “Security (Segurança)” no `CHANGELOG.md`.

---

## Boas práticas de revisão e testes
- Rode `php -l` nos arquivos alterados e valide fluxos críticos em WP local.
- Para mudanças de dados/cron jobs, inclua passos de rollback no PR quando aplicável.
- Antes do merge, garanta consistência entre código e docs apenas quando houve impacto (Trilha B).

## Contato e conflitos de instruções
- Em conflito entre este documento e um `AGENTS.md` mais específico, siga o de escopo menor e registre a decisão no PR.
- Novos requisitos/políticas devem ser adicionados aqui **apenas se forem guardrails globais**; regras locais devem ir no `AGENTS.md` do plugin correspondente.
