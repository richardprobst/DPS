# Diretrizes para agentes do desi.pet by PRObst

**Autor:** PRObst  
**Site:** <a href="https://www.probst.pro">www.probst.pro</a>

## Filosofia (guardrails, não algemas)

Este documento existe para **proteger o sistema** (segurança, compatibilidade, contratos entre plugins) sem engessar a criatividade.
- **Default = autonomia:** se algo não estiver em **MUST** ou **ASK BEFORE**, o agente pode escolher a melhor abordagem.
- **Preferir pragmatismo:** entregue valor com o menor risco. Refatore quando fizer sentido, mas evite “refactors por esporte”.
- **Quando houver trade-off:** escolha uma opção e deixe **2–3 linhas** registrando a alternativa e por que não foi usada.

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
- **docs/refactoring/REFACTORING_ANALYSIS.md**: análise de problemas conhecidos e padrões recomendados.
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

---

## UI/UX (diretrizes mínimas)
O DPS adota padrão **minimalista/clean** no admin.
- Use cores com propósito (status/alertas/ação), evite decoração.
- Mantenha hierarquia semântica (H1 único, H2 seções, H3 subseções).
- Feedback consistente: use `DPS_Message_Helper` para sucesso/erro/aviso.
- Responsividade básica quando necessário (480/768/1024).
- **Antes de criar qualquer frontend**, consulte `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` para metodologia de design, contextos de uso e checklist de implementação.

Referências completas:
- `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` — **instruções completas de design frontend** (metodologia, contextos, acessibilidade, performance)
- `docs/visual/VISUAL_STYLE_GUIDE.md` — paleta, componentes e espaçamento
- `docs/layout/admin/ADMIN_LAYOUT_ANALYSIS.md`
- `docs/implementation/UI_UX_IMPROVEMENTS_SUMMARY.md`

---

## Diretrizes para add-ons
- Cada add-on deve manter um arquivo principal `desi-pet-shower-<feature>-addon.php` e, se preciso, subpastas `includes/` e `assets/`.
- Use hooks de extensão do núcleo (`dps_base_nav_tabs_*`, `dps_base_sections_*`, `dps_settings_*`) **sem alterar assinaturas existentes**.
- Reutilize a tabela `dps_transacoes` e contratos de metadados para fluxos financeiros/assinaturas.
- Documente dependências entre add-ons (ex.: Financeiro + Assinaturas) quando houver integração real.
- Assets apenas em páginas relevantes; considere colisões com temas/plugins.
- Menus/admin pages de add-ons devem ser submenus de `desi-pet-shower`.

---

## Recursos para refatoração
- `docs/refactoring/REFACTORING_ANALYSIS.md`: problemas conhecidos + candidatos prioritários + padrões sugeridos.
- `plugins/desi-pet-shower-base/includes/refactoring-examples.php`: exemplos “antes/depois” com helpers e validação.

Quando consultar:
- Refatorações significativas, novas validações de formulários, manipulação de valores monetários/URLs/queries, revisão de PRs que introduzam novos helpers.

---

## Liberdade x segurança

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

## Setup & validação (quando aplicável)
- Ambiente local: use o ambiente oficial do projeto (ex.: `docker compose up` ou `wp-env start` se disponível). Se não existir automação, descreva como validou manualmente.
- Dependências: `composer install` e `npm ci` (quando houver build de assets).
- Checks sugeridos:
  - `php -l <arquivos alterados>`
  - `phpcs` (se configurado)
  - Testes automatizados disponíveis (`phpunit`, `npm test`, `npm run build`/`npm run lint` etc.)
- Se algum comando não estiver disponível, registre no PR e descreva validação manual equivalente.

---

## Definition of Done (por gatilho)

### DoD — Segurança (se tocou input/output, forms, AJAX, REST)
- [ ] Nonce + capability + sanitização/escape aplicados nos fluxos tocados
- [ ] Sem segredos no código / logs

### DoD — Banco & contratos (se tocou tabelas, migrações, hooks, integrações)
- [ ] `dbDelta()` protegido por option de versão
- [ ] Compatibilidade preservada (ou novo hook + depreciação documentada)
- [ ] `ANALYSIS.md` atualizado quando houver mudança de contrato/fluxo

### DoD — Admin/UI (se tocou menus, páginas, assets)
- [ ] Menus/admin pages como submenus de `desi-pet-shower` (sem `parent=null` / sem menu topo)
- [ ] Assets carregados apenas onde necessário
- [ ] Feedback via `DPS_Message_Helper` (quando aplicável)

### DoD — Release / user-facing
- [ ] `CHANGELOG.md` atualizado (categorias corretas) quando a mudança chega ao usuário/integrador

---

## Boas práticas de revisão e testes
- Rode `php -l` nos arquivos alterados e valide fluxos críticos em WP local.
- Para mudanças de dados/cron jobs, inclua passos de rollback no PR quando aplicável.
- Antes do merge, garanta consistência entre código e docs apenas quando houve impacto (Trilha B).

## Contato e conflitos de instruções
- Em conflito entre este documento e um `AGENTS.md` mais específico, siga o de escopo menor e registre a decisão no PR.
- Novos requisitos/políticas devem ser adicionados aqui **apenas se forem guardrails globais**; regras locais devem ir no `AGENTS.md` do plugin correspondente.
