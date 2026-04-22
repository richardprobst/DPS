# Diretrizes para agentes do desi.pet by PRObst

**Autor:** PRObst
**Site:** <a href="https://www.probst.pro">www.probst.pro</a>

## Filosofia (guardrails, nÃ£o algemas)

Este documento existe para **proteger o sistema** (seguranÃ§a, compatibilidade, contratos entre plugins) sem engessar a criatividade.
- **Default = autonomia:** se algo nÃ£o estiver em **MUST** ou **ASK BEFORE**, o agente pode escolher a melhor abordagem.
- **Preferir pragmatismo:** entregue valor com o menor risco. Refatore quando fizer sentido, mas evite â€œrefactors por esporteâ€.
- **Quando houver trade-off:** escolha uma opÃ§Ã£o e deixe **2â€“3 linhas** registrando a alternativa e por que nÃ£o foi usada.
- **Contexto e aplicabilidade:** se uma diretriz nÃ£o se aplica ao caso concreto, registre em 1â€“2 linhas o motivo.

## Como usar este documento (trilhas)

Antes de comeÃ§ar, classifique a mudanÃ§a:

### Trilha A â€” MudanÃ§a pequena (rÃ¡pida) âœ… (padrÃ£o)
Use quando a mudanÃ§a for local e **nÃ£o** mexer em contratos, schema compartilhado, autenticaÃ§Ã£o, ou UX ampla.
- FaÃ§a a implementaÃ§Ã£o.
- Respeite **MUST** (principalmente seguranÃ§a).
- Rode validaÃ§Ãµes aplicÃ¡veis (ver â€œSetup & validaÃ§Ã£oâ€).
- Atualize docs **somente se** algum gatilho abaixo for acionado.

### Trilha B â€” MudanÃ§a estrutural ðŸ§±
Use quando acionar **qualquer** gatilho:
- AlteraÃ§Ã£o de **schema/tabelas compartilhadas** (ex.: `dps_transacoes`, `dps_parcelas`).
- MudanÃ§a em **assinaturas/contratos de hooks**, ou comportamento consumido por add-ons.
- MudanÃ§a relevante de **menus/admin**, flags (`show_ui`, `show_in_menu`), rotas REST/AJAX, autenticaÃ§Ã£o.
- Nova **dependÃªncia externa** (API/SDK) ou fluxo sensÃ­vel (pagamentos, webhooks).
- MudanÃ§a grande de UX (navegaÃ§Ã£o, telas principais, fluxos do cliente).

Na Trilha B:
- Consulte as seÃ§Ãµes relevantes do **ANALYSIS.md** antes de codar.
- Documente impacto em **ANALYSIS.md** (e **CHANGELOG.md** quando user-facing).
- Se houver risco de quebra, aplique **ASK BEFORE**.

---


## Guia complementar de engenharia para agentes

Para manter este arquivo mais enxuto, as diretrizes operacionais de engenharia (arquitetura de cÃ³digo, DoD e processo de execuÃ§Ã£o) ficam centralizadas em:
- `docs/refactoring/AGENT_ENGINEERING_PLAYBOOK.md`

Use esse playbook quando a tarefa envolver implementaÃ§Ã£o/refatoraÃ§Ã£o de cÃ³digo no core ou add-ons.
Ele complementa este AGENTS.md sem substituir as regras de **MUST** e **ASK BEFORE**.

## Uso de agentes especializados (quando disponÃ­vel)

- Sempre que possÃ­vel, priorize um **agente especializado/skill** para executar tarefas de implementaÃ§Ã£o.
- Para mudanÃ§as de **cÃ³digo** (backend, arquitetura, integraÃ§Ãµes), prefira agente focado em engenharia/cÃ³digo.
- Para mudanÃ§as de **frontend/UI/UX/layout**, prefira agente focado em frontend/design e mantenha conformidade com `docs/visual/`.
- Se nÃ£o houver agente especializado disponÃ­vel no ambiente atual, siga com a implementaÃ§Ã£o padrÃ£o e registre em 1â€“2 linhas o motivo.

---

## Escopo
Estas orientaÃ§Ãµes cobrem todo o repositÃ³rio desi.pet by PRObst, incluindo todos os plugins em `plugins/`.
Se existir um `AGENTS.md` mais especÃ­fico em subdiretÃ³rios, **ele prevalece** para arquivos dentro de seu escopo.

## Estrutura do repositÃ³rio
- **plugins/**: pasta Ãºnica contendo todos os plugins (base + add-ons), cada um em sua prÃ³pria subpasta:
  - `desi-pet-shower-base/` â€” plugin nÃºcleo com ponto de entrada, includes e assets compartilhados.
  - `desi-pet-shower-*` â€” add-ons opcionais, cada um com arquivo principal prÃ³prio e subpastas por funcionalidade.
- **docs/**: documentaÃ§Ã£o detalhada de UX, layout, refatoraÃ§Ã£o e planos de implementaÃ§Ã£o (veja `/docs/README.md` para Ã­ndice completo).
- **ANALYSIS.md**: visÃ£o arquitetural, fluxos de integraÃ§Ã£o e contratos entre nÃºcleo e extensÃµes.
- **CHANGELOG.md**: histÃ³rico de versÃµes e lanÃ§amentos (atualizar a cada release).
- **plugins/desi-pet-shower-base/includes/refactoring-examples.php**: exemplos prÃ¡ticos de uso correto das classes helper globais.

> **Dica (monorepo):** quando necessÃ¡rio, crie `AGENTS.md` dentro de plugins para comandos/contratos locais. Mantenha o root como â€œconstituiÃ§Ã£oâ€ e os especÃ­ficos como â€œmanual do mÃ³duloâ€.

## OrganizaÃ§Ã£o de arquivos

### Arquivos permitidos na raiz do repositÃ³rio
Apenas os seguintes arquivos devem permanecer na raiz:
- `README.md` â€” introduÃ§Ã£o e visÃ£o geral do projeto
- `AGENTS.md` â€” diretrizes para agentes (humanos e IA)
- `ANALYSIS.md` â€” visÃ£o arquitetural do sistema
- `CHANGELOG.md` â€” histÃ³rico de versÃµes
- `.gitignore` â€” configuraÃ§Ã£o do Git

### Estrutura da pasta docs/
Toda documentaÃ§Ã£o adicional deve ser organizada nas seguintes subpastas:

| Pasta | PropÃ³sito | Exemplos |
|-------|-----------|----------|
| `docs/admin/` | Interface administrativa, CPTs, menus | AnÃ¡lises de UI admin, mockups, habilitaÃ§Ã£o de CPTs |
| `docs/analysis/` | AnÃ¡lises arquiteturais e de sistema | AnÃ¡lises de add-ons, mapeamentos backend/frontend |
| `docs/compatibility/` | Compatibilidade com temas e plugins | YooTheme, Elementor, page builders |
| `docs/fixes/` | CorreÃ§Ãµes e diagnÃ³sticos | Fixes de ativaÃ§Ã£o, correÃ§Ãµes de layout |
| `docs/forms/` | FormulÃ¡rios e inputs | AnÃ¡lises de UX, melhorias de campos |
| `docs/implementation/` | Resumos de implementaÃ§Ã£o | SumÃ¡rios de features implementadas |
| `docs/improvements/` | Melhorias gerais | Propostas e anÃ¡lises de melhoria |
| `docs/layout/` | Layout e UX (com subpastas) | `admin/`, `agenda/`, `client-portal/`, `forms/` |
| `docs/performance/` | OtimizaÃ§Ãµes de performance | AnÃ¡lises e guias de performance |
| `docs/refactoring/` | RefatoraÃ§Ã£o de cÃ³digo | Planos, anÃ¡lises, diagramas |
| `docs/review/` | RevisÃµes de cÃ³digo e PRs | VerificaÃ§Ãµes de PRs (ex: `pr-161/`) |
| `docs/security/` | SeguranÃ§a e auditoria | CorreÃ§Ãµes de seguranÃ§a, exemplos de vulnerabilidades |
| `docs/settings/` | ConfiguraÃ§Ãµes do sistema | Planos de implementaÃ§Ã£o, verificaÃ§Ãµes de configuraÃ§Ãµes |
| `docs/screenshots/` | DocumentaÃ§Ã£o visual e capturas | Screenshots, guias visuais de componentes |
| `docs/visual/` | Estilo visual e design | Guias de estilo, comparaÃ§Ãµes visuais |
| `docs/qa/` | Quality assurance e validaÃ§Ã£o funcional | RelatÃ³rios de QA, validaÃ§Ãµes de add-ons |

### Regras para novos arquivos de documentaÃ§Ã£o
1. **NUNCA** criar arquivos `.md` soltos na raiz (exceto os listados acima)
2. Escolha a categoria mais apropriada na tabela acima
3. Se nenhuma categoria existir, crie nova subpasta em `docs/` e documente-a aqui
4. RevisÃµes de PRs: `docs/review/pr-XXX/` (XXX = nÃºmero do PR)
5. Demos HTML devem acompanhar a doc relacionada
6. Mantenha `docs/README.md` atualizado ao adicionar novas pastas/categorias

### ValidaÃ§Ã£o dos apontamentos (evitar links quebrados)
Sempre que atualizar documentaÃ§Ã£o estrutural (`AGENTS.md`, `docs/README.md`, Ã­ndices e guias), valide se os caminhos citados existem no repositÃ³rio atual.

Comandos Ãºteis:
- `find docs -maxdepth 3 -type f | sort` (inventÃ¡rio rÃ¡pido da documentaÃ§Ã£o)
- `rg -n "docs/" AGENTS.md docs/README.md docs/screenshots/README.md` (checagem de apontamentos)

Se um caminho citado nÃ£o existir mais, atualize o apontamento na mesma entrega.


## Setup & validaÃ§Ã£o

Antes de finalizar qualquer tarefa, execute validaÃ§Ãµes proporcionais ao impacto:
- **DocumentaÃ§Ã£o apenas:** `git diff --check` + conferÃªncia dos caminhos citados.
- **PHP alterado:** `php -l` nos arquivos modificados.
- **RefatoraÃ§Ã£o/auditoria ampla de PHP:** em `tools/php`, executar `composer run ci` (ou `composer run phpcs`, `composer run phpstan`, `composer run psalm` individualmente).
- **Plugin com `composer.json` e testes:** no diretÃ³rio do plugin alterado, executar `composer test` quando o script existir.
- **MudanÃ§a funcional relevante:** validaÃ§Ã£o local no WordPress dos fluxos afetados.
- **MudanÃ§a visual/layout:** seguir `docs/visual/` (padrÃ£o DPS Signature) + validar responsividade nos breakpoints definidos pelo sistema + registrar screenshots conforme `docs/screenshots/README.md`.

Sempre registrar no fechamento os comandos executados e o status (passou/falhou/limitaÃ§Ã£o de ambiente).

---

## Versionamento e git-flow (leve e prÃ¡tico)
- Utilize SemVer (MAJOR.MINOR.PATCH) para o plugin base e para cada add-on.
- Branches (sugestÃ£o):
  - `main`: estÃ¡vel; merges revisados.
  - `develop`: integraÃ§Ã£o antes de release.
  - `feature/<slug>`: funcionalidades.
  - `hotfix/<slug>`: correÃ§Ãµes urgentes sobre `main`.
- Releases:
  - Atualize `CHANGELOG.md` e versÃµes dos plugins antes de tag.
  - Tags anotadas: `git tag -a vX.Y.Z`.
- Commits: preferir mensagens curtas em portuguÃªs, no imperativo (ex.: â€œCorrigir validaÃ§Ã£o de CPFâ€).

---

## Requisitos mÃ­nimos e nÃ­veis de regra

### VersÃµes mÃ­nimas
Todos os plugins/add-ons DEVEM declarar:
- `Requires at least: 6.9`
- `Requires PHP: 8.4`

### MUST (obrigatÃ³rio)
- **SeguranÃ§a**: validar **nonce + capability + sanitizaÃ§Ã£o/escape** em toda entrada/saÃ­da (inclui AJAX e REST).
- **I18n e bootstrap**: carregar text domain em `init` (prioridade 1) e inicializar classes principais em `init` (prioridade 5) apÃ³s o text domain.
- **Admin menus**: registrar menus e pÃ¡ginas administrativas sempre como **submenus** do menu pai `desi-pet-shower` (capability `manage_options`, `admin_menu` prioridade 20). NÃ£o usar `add_menu_page` prÃ³prio nem `parent=null`.
- **Banco**: versionar alteraÃ§Ãµes de banco (option de versÃ£o + `dbDelta()` somente quando a versÃ£o salva for menor que a atual; nunca em todo request).
- **Contratos**: preservar assinaturas de hooks/tabelas compartilhadas. Se precisar mudar, criar novo hook e manter compatibilidade com depreciaÃ§Ã£o documentada.
- **Segredos**: nunca expor segredos em cÃ³digo; usar constantes ou variÃ¡veis de ambiente.
- **Cache proibido**: Ã© **proibido** implementar qualquer tipo de cache no sistema â€” inclui, mas nÃ£o se limita a: transients do WordPress (`set_transient`, `get_transient`), object cache (`wp_cache_*`), cache de queries, cache de fragmentos HTML, cache de API responses, cache em arquivos, e qualquer mecanismo customizado de armazenamento temporÃ¡rio para reutilizaÃ§Ã£o. Todas as consultas e renderizaÃ§Ãµes devem ser executadas em tempo real, sem camadas intermediÃ¡rias de cache.

### ASK BEFORE (requer validaÃ§Ã£o humana)
- Alterar schema de tabelas compartilhadas (`dps_transacoes`, `dps_parcelas`, etc.).
- MudanÃ§as grandes de UX ou novas dependÃªncias externas (APIs/SDKs).
- Alterar assinaturas de hooks existentes ou fluxos crÃ­ticos de autenticaÃ§Ã£o.

### PREFER (recomendado)
- Reutilizar helpers globais (`DPS_Phone_Helper`, `DPS_Money_Helper`, `DPS_URL_Builder`, etc.) em vez de duplicar validaÃ§Ãµes/formatadores.
- Registrar assets de forma condicional (apenas nas pÃ¡ginas/abas relevantes).
- Para CPTs no admin: usar `show_in_menu => 'desi-pet-shower'` quando aplicÃ¡vel e otimizar queries (`fields => 'ids'`, `no_found_rows`, `update_meta_cache()`).

---

## DocumentaÃ§Ã£o (sem burocracia)

- DocumentaÃ§Ã£o em portuguÃªs, clara e orientada a passos.
- **Atualize docs somente quando houver impacto real** (Trilha B ou user-facing):
  - `ANALYSIS.md`: contratos, hooks, menus, flags, fluxos de integraÃ§Ã£o, novas extensÃµes.
  - `CHANGELOG.md`: mudanÃ§as que chegam ao usuÃ¡rio/integrador (Added/Changed/Fixed/Removed/Deprecated/Security/Refactoring).
- **Novos add-ons**: adicione seÃ§Ã£o no `ANALYSIS.md` contendo:
  - diretÃ³rio, propÃ³sito, hooks utilizados/expostos, dependÃªncias, tabelas, shortcodes/CPTs/capabilities.

---

## ConvenÃ§Ãµes de cÃ³digo
- WordPress: indentaÃ§Ã£o 4 espaÃ§os.
- FunÃ§Ãµes globais em `snake_case`; mÃ©todos/propriedades de classe em `camelCase`.
- Escape e sanitizaÃ§Ã£o obrigatÃ³rios (`esc_html__`, `esc_attr`, `wp_nonce_*`, `sanitize_text_field`, etc.).
- `require/require_once` organizados (sem envolver imports em `try/catch`).
- Assets: prefira `wp_register_*` + `wp_enqueue_*` em pontos especÃ­ficos; evite carregar no site inteiro.
- Hooks/options/handles prefixados com `dps_`.
- **Deprecated**: evitar funÃ§Ãµes/classes obsoletas de WP, PHP, JS e jQuery. Quando encontrar cÃ³digo deprecated, atualizar para a alternativa moderna recomendada. ReferÃªncias Ãºteis: [WordPress Developer Resources](https://developer.wordpress.org/reference/), guias de migraÃ§Ã£o PHP e notas de release do jQuery.

---

## Boas prÃ¡ticas WordPress (arquitetura e APIs)
- **Priorize APIs nativas** (Settings API, REST API, WP_Query, `$wpdb->prepare()`) antes de soluÃ§Ãµes customizadas.
- **Hooks first**: prefira estender via `add_action`/`add_filter` em vez de alterar fluxo direto do nÃºcleo/base.
- **Enqueue correto**: registre e carregue scripts/estilos apenas nas telas necessÃ¡rias, com dependÃªncias explÃ­citas.
- **I18n sempre que houver UI**: strings visÃ­veis ao usuÃ¡rio devem usar funÃ§Ãµes de traduÃ§Ã£o.
- **SeparaÃ§Ã£o clara**: lÃ³gica de negÃ³cio em classes/helpers, UI em templates/partials, assets em `assets/`.

---

## UI/UX (diretrizes mÃ­nimas)
- Use cores com propÃ³sito (status/alertas/aÃ§Ã£o).
- Mantenha hierarquia semÃ¢ntica (H1 Ãºnico, H2 seÃ§Ãµes, H3 subseÃ§Ãµes).
- Feedback consistente: use `DPS_Message_Helper` para sucesso/erro/aviso.
- **Responsividade Ã© obrigatÃ³ria** em toda entrega visual/frontend: o agente deve projetar, implementar e revisar a interface para uso real em telas pequenas, mÃ©dias e grandes, evitando depender de ajustes posteriores.
- Validar layouts, navegaÃ§Ã£o, densidade, legibilidade e Ã¡reas de toque nos breakpoints de referÃªncia do sistema: **375px, 600px, 840px, 1200px e 1920px**.
- Em mudanÃ§as visuais, tratar como defeito: overflow horizontal, conteÃºdo cortado, CTA inacessÃ­vel, tabela sem estratÃ©gia mobile, modal que excede a viewport, targets de toque insuficientes e hierarquia comprometida entre breakpoints.
- **ObrigatÃ³rio:** qualquer tarefa de **layout/design/frontend** (criar, recriar, corrigir ou ajustar UI) **DEVE** seguir as especificaÃ§Ãµes de `docs/visual/` (ver referÃªncias abaixo).

ReferÃªncias de design e layout (padrÃ£o visual **DPS Signature** do sistema):
- `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` â€” **instruÃ§Ãµes completas de design frontend** (metodologia, contextos de uso, acessibilidade, performance, checklist)
- `docs/visual/VISUAL_STYLE_GUIDE.md` â€” paleta, componentes e espaÃ§amento

**Regra de comunicaÃ§Ã£o obrigatÃ³ria:** sempre que uma tarefa envolver visual/layout/frontend (criaÃ§Ã£o, ajuste, correÃ§Ã£o, refatoraÃ§Ã£o ou revisÃ£o), o agente **deve indicar explicitamente** que seguiu `docs/visual/` como fonte de verdade do padrÃ£o DPS Signature.

**Registro obrigatÃ³rio para mudanÃ§as visuais:** qualquer alteraÃ§Ã£o visual/layout/frontend deve ser documentada com:
- descriÃ§Ã£o objetiva do que mudou (antes/depois, impacto e arquivos afetados);
- capturas **completas** das telas alteradas para registro em `docs/screenshots/`;
- organizaÃ§Ã£o dos registros em subpastas por data no formato `docs/screenshots/YYYY-MM-DD/`.

Sempre orientar e confirmar no fechamento/PR onde os prints e o documento de registro foram salvos.

### Fluxo obrigatÃ³rio para mudanÃ§as visuais (DPS Signature)
1. Consultar `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md` antes de implementar.
2. Implementar a mudanÃ§a mantendo coerÃªncia com o padrÃ£o DPS Signature e com responsividade real para small/medium/large screens.
3. Gerar capturas completas das telas alteradas.
4. Salvar artefatos em `docs/screenshots/YYYY-MM-DD/`.
5. Criar/atualizar o documento do dia (`SCREENSHOTS_YYYY-MM-DD.md`) com contexto, antes/depois, breakpoints validados e lista dos arquivos.
6. Citar no fechamento/PR os caminhos dos registros e prints salvos.

---

## Diretrizes para add-ons
- Cada add-on deve manter um arquivo principal `desi-pet-shower-<feature>-addon.php` e, se preciso, subpastas `includes/` e `assets/`.
- Use hooks de extensÃ£o do nÃºcleo (`dps_base_nav_tabs_*`, `dps_base_sections_*`, `dps_settings_*`) **sem alterar assinaturas existentes**.
- Reutilize a tabela `dps_transacoes` e contratos de metadados para fluxos financeiros/assinaturas.
- Documente dependÃªncias entre add-ons (ex.: Financeiro + Assinaturas) quando houver integraÃ§Ã£o real.
- Assets apenas em pÃ¡ginas relevantes; considere colisÃµes com temas/plugins.
- Menus/admin pages de add-ons devem ser submenus de `desi-pet-shower`.

---

## Auditoria e anÃ¡lise de cÃ³digo

### Escopo de anÃ¡lise
- **Incluir**: cÃ³digo PHP, JS, CSS, templates e assets dos plugins em `plugins/`.
- **Ignorar** (nÃ£o analisar nem alterar): `vendor/`, `node_modules/`, `dist/`, `build/`, `coverage/`, arquivos minificados (`*.min.js`, `*.min.css`), caches e dependÃªncias externas.

### Fluxo de auditoria (quando aplicÃ¡vel)
Para tarefas de auditoria, revisÃ£o ampla ou refatoraÃ§Ã£o significativa:
1. **InventÃ¡rio**: mapear entrypoints, includes, classes, hooks, shortcodes, REST/AJAX, admin pages e cron.
2. **RelatÃ³rio de achados** (antes de mudanÃ§as grandes):
   - Categorias: Deprecated / Duplicado / Morto / SeguranÃ§a / Performance / Manutenibilidade.
   - Severidade: Alta / MÃ©dia / Baixa.
   - EvidÃªncia: `arquivo:linha` (ou trecho).
   - RecomendaÃ§Ã£o.
3. **CorreÃ§Ãµes em patches isolados**: mudanÃ§as pequenas, cada uma com justificativa clara.
4. **Perguntar antes** nas situaÃ§Ãµes listadas em **ASK BEFORE** (ver acima).

### HeurÃ­sticas para detecÃ§Ã£o de problemas
- **CÃ³digo morto**: funÃ§Ãµes/classes sem referÃªncias, arquivos nunca incluÃ­dos, callbacks nÃ£o registrados, hooks inexistentes. **AtenÃ§Ã£o**: verificar tambÃ©m chamadas dinÃ¢micas (`call_user_func`, `do_action`/`apply_filters` com nomes de hook variÃ¡veis, instanciaÃ§Ã£o via strings) antes de considerar cÃ³digo como morto.
- **DuplicaÃ§Ã£o**: mesmas validaÃ§Ãµes/sanitizaÃ§Ãµes repetidas, mesma lÃ³gica de montagem de arrays, mesmos patterns de `$wpdb`, mesmo HTML gerado em mÃºltiplos pontos.
- **Deprecated**: uso de funÃ§Ãµes/classes/parÃ¢metros marcados como deprecated no WP, PHP ou jQuery/JS.

### PolÃ­ticas de mudanÃ§a em auditorias
- NÃ£o reformatar o projeto inteiro de uma vez.
- NÃ£o "modernizar" tudo simultaneamente; preferir mudanÃ§as incrementais e seguras.
- NÃ£o remover funcionalidades sem evidÃªncia clara de que sÃ£o cÃ³digo morto. Verificar referÃªncias estÃ¡ticas e dinÃ¢micas (`add_action`/`add_filter` com variÃ¡veis, `$wp_filter`, autoloading, `require` condicional).

---

## Liberdade x seguranÃ§a

### PriorizaÃ§Ã£o de mudanÃ§as
Ao decidir o que corrigir ou melhorar, siga esta ordem de prioridade:
1. **SeguranÃ§a** (vulnerabilidades, falhas de validaÃ§Ã£o)
2. **Bugs** (comportamento incorreto)
3. **Compatibilidade** (deprecated, breaking changes)
4. **Performance** (queries lentas, assets desnecessÃ¡rios)
5. **Limpeza** (cÃ³digo morto, duplicaÃ§Ã£o, legibilidade)

### Autorizado (e incentivado) quando for seguro
- âœ… Corrigir bugs encontrados no caminho **quando a correÃ§Ã£o for claramente segura** e nÃ£o ampliar escopo sem necessidade.
- âœ… Quebrar funÃ§Ãµes grandes em mÃ©todos menores (clareza e testabilidade).
- âœ… Extrair helpers reutilizÃ¡veis quando houver duplicaÃ§Ã£o real.
- âœ… Melhorar DocBlocks e nomenclatura.
- âœ… Otimizar queries quando houver ganho e baixo risco.
- âœ… Adicionar hooks novos (documentar no `ANALYSIS.md` com assinatura, propÃ³sito e exemplo).
- âœ… Melhorar seguranÃ§a (reforÃ§ar validaÃ§Ãµes, escapes e sanitizaÃ§Ã£o).

### Evitar sem validaÃ§Ã£o extra
- âŒ Afrouxar validaÃ§Ãµes de seguranÃ§a (sempre reforÃ§ar, nunca remover).
- âŒ Mudar assinaturas de hooks existentes (crie novos hooks e deprecie os antigos).
- âŒ Remover/modificar capabilities sem anÃ¡lise de impacto.
- âŒ Alterar schema de tabelas compartilhadas sem migraÃ§Ã£o reversÃ­vel + documentaÃ§Ã£o + validaÃ§Ã£o (ASK BEFORE).
- âŒ Implementar qualquer tipo de cache (transients, object cache, cache de queries, cache de fragmentos, cache em arquivos ou qualquer mecanismo de armazenamento temporÃ¡rio). Ver regra **Cache proibido** na seÃ§Ã£o MUST.

**PrincÃ­pio geral:** na dÃºvida, prefira adicionalidade (criar novo em vez de quebrar existente) e documente o mÃ­nimo necessÃ¡rio.

---

## IntegraÃ§Ã£o nÃºcleo â‡„ extensÃµes
- Novos pontos de extensÃ£o no nÃºcleo devem ter documentaÃ§Ã£o mÃ­nima no `ANALYSIS.md` (assinatura, propÃ³sito, exemplos).
- Compatibilidade retroativa: introduza novos hooks sem quebrar os existentes; depreciaÃ§Ãµes no `CHANGELOG.md` com versÃ£o alvo.
- Fluxos compartilhados (agendamento, pagamentos, notificaÃ§Ãµes): centralize no nÃºcleo e reutilize nos add-ons.
- Esquemas de dados compartilhados: migraÃ§Ãµes reversÃ­veis + validaÃ§Ã£o de sincronizaÃ§Ã£o.

---

## PolÃ­ticas de seguranÃ§a obrigatÃ³rias
- Nonces em formulÃ¡rios e aÃ§Ãµes autenticadas; rejeitar requisiÃ§Ãµes sem verificaÃ§Ã£o.
- Escape de saÃ­da em HTML/atributos/JS inline; sanitize toda entrada do usuÃ¡rio (inclui webhooks).
- Menor privilÃ©gio para capabilities.
- Segredos apenas via constantes/variÃ¡veis de ambiente; nunca commitar tokens.
- CorreÃ§Ãµes de seguranÃ§a: registrar em â€œSecurity (SeguranÃ§a)â€ no `CHANGELOG.md`.

---

## Boas prÃ¡ticas de revisÃ£o e testes
- Rode `php -l` nos arquivos alterados e valide fluxos crÃ­ticos em WP local.
- Para mudanÃ§as de dados/cron jobs, inclua passos de rollback no PR quando aplicÃ¡vel.
- Antes do merge, garanta consistÃªncia entre cÃ³digo e docs apenas quando houve impacto (Trilha B).

## Contato e conflitos de instruÃ§Ãµes
- Em conflito entre este documento e um `AGENTS.md` mais especÃ­fico, siga o de escopo menor e registre a decisÃ£o no PR.
- Novos requisitos/polÃ­ticas devem ser adicionados aqui **apenas se forem guardrails globais**; regras locais devem ir no `AGENTS.md` do plugin correspondente.
