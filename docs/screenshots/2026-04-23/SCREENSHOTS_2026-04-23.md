# Screenshots 2026-04-23

## Portal do Cliente: Fase 3 forca de senha no reset

Fonte de verdade visual seguida nesta Fase 3: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md` (padrao DPS Signature).

### Objetivo

Executar somente a Fase 3 do plano pos-auditoria do login do Portal do Cliente: adicionar indicacao de forca de senha e dicas de composicao na tela de reset valido, preservando contratos externos, campos, nonces, action, redirects e a regra backend ja publicada.

### Verificacao previa dos riscos residuais

- `play.google.com/log` abortado foi classificado como ruido externo de rede, sem `pageErrors` e sem acao necessaria no add-on.
- o notice de WP-CLI do `all-in-one-wp-migration` foi confirmado como externo: ele aparece com plugins padrao e desaparece ao executar WP-CLI com `--skip-plugins=all-in-one-wp-migration`.
- a verificacao foi salva em `client-portal-phase-3-password-strength/residual-risk-verification.json`.

### Ajustes implementados

- a tela de reset valido ganhou medidor de forca da senha com estados `Senha fraca`, `Senha em construcao`, `Senha boa` e `Senha forte`;
- adicionada lista de dicas de composicao com progresso inline: minimo de 8 caracteres, letras maiusculas/minusculas, numero e simbolo ou frase mais longa;
- adicionada mensagem inline para confirmar se os dois campos de senha conferem;
- o runtime publico `client-portal-access.js` passou a controlar apenas essa interacao de UI, sem tocar no fluxo autenticado;
- o CSS do shell publico passou a quebrar e-mails/identificadores longos dentro do reset, corrigindo overflow horizontal em `375px`.

### Publicado e validado

- validado no reset publicado emitido pelo proprio add-on em `https://desi.pet/portal-do-cliente/`;
- fixture temporario final:
  - tag: `codex_portal_phase3_20260423_230107`;
  - usuarios/posts temporarios removidos ao final;
  - script WP-CLI temporario remoto removido ao final;
- backup remoto:
  - `/home/u944637195/backups/dps-client-portal-phase3-password-strength-20260423-225737`;
  - `/home/u944637195/backups/dps-client-portal-phase3-password-strength-overflow-20260423-230022`.

### Validacao funcional

- `pageErrors = []`;
- `consoleErrors = []`;
- estados fraco, intermediario, mismatch e forte validados no runtime publicado;
- `Senha forte` exibida em todos os breakpoints com `4/4` dicas atendidas;
- mensagem `As senhas conferem.` exibida em todos os breakpoints;
- painel com geometria reta (`borderRadius = 0px`);
- envio final do reset continuou autenticando e abriu o portal.

### Breakpoints validados

- `375`: `overflowX = 0`, `Senha forte`, `tips = 4/4`;
- `600`: `overflowX = 0`, `Senha forte`, `tips = 4/4`;
- `840`: `overflowX = 0`, `Senha forte`, `tips = 4/4`;
- `1200`: `overflowX = 0`, `Senha forte`, `tips = 4/4`;
- `1920`: `overflowX = 0`, `Senha forte`, `tips = 4/4`.

### Artefatos

- [residual-risk-verification.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-phase-3-password-strength/residual-risk-verification.json)
- [password-strength-validation.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-phase-3-password-strength/password-strength-validation.json)
- [password-strength-fixture.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-phase-3-password-strength/password-strength-fixture.json)
- [password-strength-cleanup.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-phase-3-password-strength/password-strength-cleanup.json)
- [password-strength-cleanup-consumed-fixture.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-phase-3-password-strength/password-strength-cleanup-consumed-fixture.json)
- [deploy-sftp-result.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-phase-3-password-strength/deploy-sftp-result.json)
- [deploy-sftp-overflow-fix-result.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-phase-3-password-strength/deploy-sftp-overflow-fix-result.json)
- [portal-reset-password-strength-375.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-phase-3-password-strength/portal-reset-password-strength-375.png)
- [portal-reset-password-strength-600.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-phase-3-password-strength/portal-reset-password-strength-600.png)
- [portal-reset-password-strength-840.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-phase-3-password-strength/portal-reset-password-strength-840.png)
- [portal-reset-password-strength-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-phase-3-password-strength/portal-reset-password-strength-1200.png)
- [portal-reset-password-strength-1920.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-phase-3-password-strength/portal-reset-password-strength-1920.png)
- [portal-reset-password-strength-weak-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-phase-3-password-strength/portal-reset-password-strength-weak-1200.png)
- [portal-reset-password-strength-mismatch-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-phase-3-password-strength/portal-reset-password-strength-mismatch-1200.png)

### Comandos executados

- `php -l plugins/desi-pet-shower-client-portal/templates/portal-password-reset.php`
- `node --check plugins/desi-pet-shower-client-portal/assets/js/client-portal-access.js`
- upload por SSH/SFTP com backup remoto dos arquivos alterados
- `php -l` remoto no template publicado
- fixture temporario via WP-CLI com `tools/client-portal/client-portal-smoke-fixture.php`
- validacao publicada com Playwright em `375`, `600`, `840`, `1200` e `1920`
- limpeza dos clientes/usuarios temporarios e scripts WP-CLI remotos

## Portal do Cliente: Fase 2 suporte ao throttling publico

Fonte de verdade visual seguida nesta Fase 2: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md` (padrao DPS Signature).

### Objetivo

Executar somente a Fase 2 do plano pos-auditoria do login do Portal do Cliente: expor no admin um resumo operacional de throttling publico por e-mail/IP para suporte quando clientes relatarem dificuldade no acesso, sem alterar shortcodes, hooks, endpoints, nonces, nomes de campos ou fluxos publicados.

### Ajustes implementados

- o `DPS_Portal_Rate_Limiter` passou a expor um resumo seguro e em tempo real dos buckets publicos persistidos em `dps_portal_rate_limits`;
- a tela administrativa de Logins passou a mostrar metricas, status e linhas por fluxo (`login por senha`, `magic link`, `criar/redefinir senha`, pedido geral e validacao de token);
- e-mails conhecidos sao resolvidos contra clientes publicados; IPs aparecem apenas como fingerprint, sem expor endereco bruto;
- o painel segue a superficie operacional DPS Signature: geometria reta, densidade controlada, status objetivos e tabela com rolagem interna no mobile;
- corrigida a base URL da aba Logins dentro do hub do Portal para manter a busca em `admin.php?page=dps-portal-hub&tab=logins`, em vez do submenu legado oculto `dps-client-logins`.

### Publicado e validado

- validado no admin publicado em `https://desi.pet/wp-admin/admin.php?page=dps-portal-hub&tab=logins`;
- fixture temporario:
  - tag: `codex_phase2_throttling_20260423222010`;
  - usuario admin temporario removido ao final;
  - cliente temporario removido ao final;
  - contadores semeados limpos ao final;
- backups remotos:
  - `/home/u944637195/backups/dps-client-portal-phase2-throttling-20260423-220848`;
  - `/home/u944637195/backups/dps-client-portal-phase2-throttling-hub-20260423-221632`;
  - `/home/u944637195/backups/dps-client-portal-phase2-throttling-css-20260423-221944`.

### Validacao funcional

- `pageErrors = []`;
- `consoleErrors = []`;
- `overflowX = 0` em todos os breakpoints;
- painel `Throttling publico` presente em todos os breakpoints;
- `2` bloqueios ativos exibidos durante a fixture;
- e-mail do cliente temporario resolvido na tabela;
- fingerprint de IP exibido sem mostrar o IP bruto;
- formulario de busca da aba Logins permaneceu apontando para o hub acessivel.
- painel de throttling publicado com geometria reta (`borderRadius = 0px`) nos cinco breakpoints.

### Breakpoints validados

- `375`: `overflowX = 0`, `rowCount = 5`, `limited = 2`;
- `600`: `overflowX = 0`, `rowCount = 5`, `limited = 2`;
- `840`: `overflowX = 0`, `rowCount = 5`, `limited = 2`;
- `1200`: `overflowX = 0`, `rowCount = 5`, `limited = 2`;
- `1920`: `overflowX = 0`, `rowCount = 5`, `limited = 2`.

### Artefatos

- [portal-admin-throttling-validation.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-admin-throttling/portal-admin-throttling-validation.json)
- [portal-admin-throttling-fixture.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-admin-throttling/portal-admin-throttling-fixture.json)
- [portal-admin-throttling-cleanup.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-admin-throttling/portal-admin-throttling-cleanup.json)
- [deploy-sftp-result.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-admin-throttling/deploy-sftp-result.json)
- [deploy-sftp-hub-fix-result.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-admin-throttling/deploy-sftp-hub-fix-result.json)
- [deploy-sftp-css-fix-result.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-admin-throttling/deploy-sftp-css-fix-result.json)
- [portal-admin-throttling-375.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-admin-throttling/portal-admin-throttling-375.png)
- [portal-admin-throttling-600.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-admin-throttling/portal-admin-throttling-600.png)
- [portal-admin-throttling-840.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-admin-throttling/portal-admin-throttling-840.png)
- [portal-admin-throttling-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-admin-throttling/portal-admin-throttling-1200.png)
- [portal-admin-throttling-1920.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-admin-throttling/portal-admin-throttling-1920.png)

### Comandos executados

- `php -l plugins/desi-pet-shower-client-portal/includes/class-dps-portal-rate-limiter.php`
- `php -l plugins/desi-pet-shower-client-portal/includes/client-portal/class-dps-portal-admin.php`
- `php -l plugins/desi-pet-shower-client-portal/includes/class-dps-portal-hub.php`
- `php -l plugins/desi-pet-shower-client-portal/templates/admin-logins.php`
- upload por SSH/SFTP com backup remoto dos arquivos alterados
- `php -l` remoto nos arquivos PHP publicados
- fixture temporario via WP-CLI com usuario admin, cliente publicado e janelas reais de `DPS_Portal_Rate_Limiter`
- validacao publicada com Playwright autenticado em `375`, `600`, `840`, `1200` e `1920`
- limpeza dos contadores, cliente, usuario temporario e scripts WP-CLI remotos

## Portal do Cliente: Fase 1 pos-auditoria do acesso publico

Fonte de verdade visual seguida nesta Fase 1: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md` (padrao DPS Signature).

### Objetivo

Executar somente a Fase 1 do plano pos-auditoria do login do Portal do Cliente: consolidar a separacao entre runtime publico e autenticado, criar smoke test reexecutavel para o publicado, validar fluxos reais com fixture temporario WP-CLI e registrar evidencias funcionais/visuais.

### Ajustes implementados

- residuos de CSS da landing publica, reset e 2FA foram removidos do bundle autenticado `client-portal.css`;
- estilos publicos/2FA ficaram concentrados em `client-portal-auth.css`, mantendo a casca DPS Signature usada fora da area logada;
- a tela de 2FA passou a usar o mesmo shell publico DPS Signature, sem alterar endpoint AJAX ou fluxo de verificacao;
- links de reset expirados agora recebem feedback proprio (`Link de senha expirado`) em vez de cair no mesmo estado de link invalido;
- criado fixture temporario WP-CLI em `tools/client-portal/client-portal-smoke-fixture.php`;
- criado smoke test Playwright reexecutavel em `tools/client-portal/client-portal-public-smoke.mjs`.

### Publicado e validado

- publicado em `https://desi.pet/portal-do-cliente/`;
- backup remoto:
  - `/home/u944637195/backups/dps-client-portal-phase1-20260423-214752`;
- fixture temporario final:
  - tag: `codex_portal_phase1_20260423_215219`;
  - usuarios/posts temporarios removidos ao final;
  - scripts temporarios WP-CLI removidos do home remoto.

### Validacao funcional

- `pageErrors = []` em todos os fluxos do smoke publicado;
- `consoleErrors = []` e `consoleWarnings = []` no smoke final;
- o runtime publico carregou `client-portal-access.js` e nao carregou `client-portal.js`;
- login por e-mail e senha autenticou e abriu o portal com `9` tabs;
- magic link autenticou e abriu o portal com `9` tabs;
- CTA `Criar ou redefinir senha` respondeu `200` com feedback inline anti-enumeration;
- solicitacao de magic link respondeu `200` com feedback inline;
- reset invalido mostrou estado de link invalido sem formulario;
- reset expirado mostrou estado de link expirado sem formulario;
- reset valido exibiu formulario com `toggleCount = 2`;
- envio do reset valido autenticou e abriu o portal com `9` tabs.

### Breakpoints validados

- `375`: `overflowX = 0`, `pageErrors = []`, CTA `295x56`;
- `600`: `overflowX = 0`, `pageErrors = []`, CTA `514x56`;
- `840`: `overflowX = 0`, `pageErrors = []`, CTA `751x56`;
- `1200`: `overflowX = 0`, `pageErrors = []`, CTA `617x56`;
- `1920`: `overflowX = 0`, `pageErrors = []`, CTA `617x56`.

### Artefatos

- [portal-client-public-smoke-result.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-public-smoke/portal-client-public-smoke-result.json)
- [deploy-sftp-result.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-public-smoke/deploy-sftp-result.json)
- [fixture-cleanup-result.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-public-smoke/fixture-cleanup-result.json)
- [portal-public-shell-375.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-public-smoke/portal-public-shell-375.png)
- [portal-public-shell-600.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-public-smoke/portal-public-shell-600.png)
- [portal-public-shell-840.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-public-smoke/portal-public-shell-840.png)
- [portal-public-shell-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-public-smoke/portal-public-shell-1200.png)
- [portal-public-shell-1920.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-public-smoke/portal-public-shell-1920.png)
- [portal-password-access-anti-enumeration-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-public-smoke/portal-password-access-anti-enumeration-1200.png)
- [portal-magic-link-request-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-public-smoke/portal-magic-link-request-1200.png)
- [portal-password-login-success-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-public-smoke/portal-password-login-success-1200.png)
- [portal-magic-login-success-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-public-smoke/portal-magic-login-success-1200.png)
- [portal-reset-invalid-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-public-smoke/portal-reset-invalid-1200.png)
- [portal-reset-expired-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-public-smoke/portal-reset-expired-1200.png)
- [portal-reset-valid-screen-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-public-smoke/portal-reset-valid-screen-1200.png)
- [portal-reset-valid-success-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/client-portal-public-smoke/portal-reset-valid-success-1200.png)

### Comandos executados

- `php -l plugins/desi-pet-shower-client-portal/includes/class-dps-client-portal.php`
- `php -l plugins/desi-pet-shower-client-portal/includes/class-dps-portal-2fa.php`
- `php -l plugins/desi-pet-shower-client-portal/templates/portal-password-reset.php`
- `php -l tools/client-portal/client-portal-smoke-fixture.php`
- `node --check tools/client-portal/client-portal-public-smoke.mjs`
- `node --check plugins/desi-pet-shower-client-portal/assets/js/client-portal-access.js`
- `node --check plugins/desi-pet-shower-client-portal/assets/js/client-portal.js`
- `git diff --check`
- upload por SSH/SFTP com backup remoto dos arquivos alterados
- fixture temporario com `wp eval-file tools/client-portal/client-portal-smoke-fixture.php`
- smoke publicado com Playwright em `375`, `600`, `840`, `1200` e `1920`
- limpeza dos clientes/usuarios temporarios e scripts WP-CLI remotos

## Agenda: botao `COMPLETA` e navegacao de views

Fonte de verdade visual seguida nesta correcao: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md` (padrao DPS Signature).

### Objetivo

Corrigir o estouro do botao `AGENDA COMPLETA` na Agenda publicada, renomeando para `COMPLETA`, estabilizando a malha da navegacao de views e validando o fluxo funcional da view completa (`show_all=1`) no site publicado.

### Ajustes implementados

- renderer da Agenda atualizado para exibir `COMPLETA` no seletor de views;
- botao da view completa recebeu classe propria `dps-view-btn--all`;
- navegacao de views consolidada em grade previsivel, sem depender de `flex-wrap`;
- CTA de datas `Hoje` centralizado nos breakpoints amplos;
- bloco legado de estilos da navegacao, ainda em semantica visual herdada, removido da camada ativa dessa area.

### Publicado e validado

- publicado em `https://desi.pet/agenda-de-atendimentos/`;
- backups remotos:
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php.__backup_20260423-084626`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css.__backup_20260423-084626`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css.__backup_20260423-085132`

### Validacao funcional

- login autenticado via usuario temporario WP-CLI;
- fluxo `Semana -> Completa -> Semana` passou;
- `Completa` ativa `show_all=1`;
- retorno para `Semana` remove `show_all` da URL;
- label ativo em modo completo: `COMPLETA`;
- escopo exibido em modo completo: `Todos os atendimentos futuros`;
- conferencia via `wp eval`: `found=0` atendimentos futuros em `2026-04-23`, entao o estado vazio do modo completo esta correto no ambiente publicado;
- sessao temporaria removida ao final.

### Breakpoints validados

- `375`: sem overflow horizontal, grade `2x2`, botao `Completa` `140x44`;
- `600`: sem overflow horizontal, grade `2x2`, botao `Completa` `252x44`;
- `840`: sem overflow horizontal, grade `2x2`, botao `Completa` `360x44`;
- `1200`: sem overflow horizontal, grade `4x1`, botao `Completa` `256x44`;
- `1920`: sem overflow horizontal, grade `4x1`, botao `Completa` `256x44`.

### Console

- `consoleErrors = 0`
- `consoleWarnings = 0`

### Artefatos

- [agenda-completa-button-check.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-completa-button-check.json)
- [agenda-completa-375.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-completa-375.png)
- [agenda-completa-600.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-completa-600.png)
- [agenda-completa-840.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-completa-840.png)
- [agenda-completa-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-completa-1200.png)
- [agenda-completa-1920.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-completa-1920.png)

### Comandos executados

- `php -l plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php`
- `git diff --check -- plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`
- upload por SSH/SFTP com backup remoto dos arquivos alterados
- criacao e remocao de usuario temporario com `wp user create` e `wp user delete`
- validacao publicada com Playwright autenticado em `375`, `600`, `840`, `1200` e `1920`

## Agenda: filtros da `Fila operacional`

Fonte de verdade visual seguida nesta correcao: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md` (padrao DPS Signature).

### Objetivo

Corrigir a distribuicao ruim dos filtros `Todos`, `Atrasados` e `TaxiDog` na `Fila operacional`, reorganizando o bloco em uma hierarquia operacional mais clara, com empilhamento vertical consistente no desktop e no mobile, alem de validar as interacoes reais dos filtros e da busca.

### Ajustes implementados

- o header da `Fila operacional` ficou dedicado so a contexto: titulo e subtitulo;
- a busca foi movida para a toolbar operacional, junto dos filtros;
- os filtros passaram a operar como coluna vertical no desktop;
- no mobile, os filtros passaram a ocupar largura total, tambem empilhados verticalmente;
- os botoes agora expoem `aria-pressed` e o JS sincroniza o estado ativo corretamente a cada clique;
- uma regra antiga com `!important` que forcava 3 colunas no mobile foi neutralizada na camada final ativa.

### Publicado e validado

- publicado em `https://desi.pet/agenda-de-atendimentos/`;
- backups remotos:
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php.__backup_20260423-090559`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css.__backup_20260423-090559`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js.__backup_20260423-090559`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css.__backup_20260423-090832`

### Validacao funcional

- sessao autenticada criada via WP-CLI para teste publicado;
- fixtures temporarios criados no ambiente para validar filtros reais:
  - `1713` atrasado;
  - `1714` com TaxiDog;
  - `1715` normal;
- desktop `1200`:
  - `Todos` exibiu `3` cards;
  - `Atrasados` exibiu `1` card;
  - `TaxiDog` exibiu `1` card;
  - busca por `fixture taxidog` retornou `1` card;
  - retorno para `Todos` restaurou `3` cards;
- mobile `375`:
  - `Todos` exibiu `3` cards;
  - `TaxiDog` exibiu `1` card;
  - retorno para `Todos` restaurou `3` cards;
- `aria-pressed` alternou corretamente nos filtros ativos;
- fixtures e usuario temporario removidos ao final, sem residuos.

### Breakpoints validados

- `375`: sem overflow horizontal, filtros verticais `325x55`, `filterUniqueXCount = 1`;
- `600`: sem overflow horizontal, filtros verticais `550x55`, `filterUniqueXCount = 1`;
- `840`: sem overflow horizontal, filtros verticais `774x55`, `filterUniqueXCount = 1`;
- `1200`: sem overflow horizontal, coluna lateral de filtros `228x55`, `filterUniqueXCount = 1`;
- `1920`: sem overflow horizontal, coluna lateral de filtros `228x55`, `filterUniqueXCount = 1`.

### Console

- `consoleErrors = 0`
- `consoleWarnings = 0`

### Artefatos

- [agenda-operational-filters-check.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-operational-filters-check.json)
- [agenda-operational-filters-375.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-operational-filters-375.png)
- [agenda-operational-filters-600.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-operational-filters-600.png)
- [agenda-operational-filters-840.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-operational-filters-840.png)
- [agenda-operational-filters-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-operational-filters-1200.png)
- [agenda-operational-filters-1920.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-operational-filters-1920.png)

### Comandos executados

- `php -l plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php`
- `node --check plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js`
- `git diff --check -- plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`
- upload por SSH/SFTP com backup remoto dos arquivos alterados
- criacao e remocao de usuario temporario com `wp user create` e `wp user delete`
- criacao e remocao de fixtures temporarios com `wp post create`, `wp post meta update` e `wp post delete --force`
- validacao publicada com Playwright autenticado em `375`, `600`, `840`, `1200` e `1920`

## Agenda: filtros horizontais sem busca na `Fila operacional`

Fonte de verdade visual seguida nesta correcao: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md` (padrao DPS Signature).

### Objetivo

Limpar a barra operacional da `Fila operacional`, removendo o campo de busca e mantendo apenas os filtros `Todos`, `Atrasados` e `TaxiDog` em distribuicao horizontal, com validacao real de layout e funcionalidade no site publicado.

### Ajustes implementados

- campo de pesquisa removido do renderer da `Fila operacional`;
- logica JS simplificada para filtrar apenas pelo estado ativo dos botoes;
- `aria-pressed` mantido e sincronizado a cada clique;
- toolbar final consolidada como faixa centralizada com tres colunas horizontais;
- breakpoints pequenos mantidos em uma unica linha, sem overflow horizontal e sem quebra dos labels.

### Publicado e validado

- publicado em `https://desi.pet/agenda-de-atendimentos/`;
- backups remotos:
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php.__backup_20260423-092646`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css.__backup_20260423-092646`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js.__backup_20260423-092646`

### Validacao funcional

- sessao autenticada criada via WP-CLI para teste publicado;
- fixtures temporarios criados no ambiente para validar filtros reais:
  - `1716` atrasado;
  - `1717` com TaxiDog;
  - `1718` normal;
- desktop `1200`:
  - `Todos` exibiu `3` cards;
  - `Atrasados` exibiu `1` card;
  - `TaxiDog` exibiu `1` card;
  - retorno para `Todos` restaurou `3` cards;
- mobile `375`:
  - `Todos` exibiu `3` cards;
  - `Atrasados` exibiu `1` card;
  - `TaxiDog` exibiu `1` card;
  - retorno para `Todos` restaurou `3` cards;
- busca removida do runtime: `searchPresent = false` em todos os breakpoints;
- `aria-pressed` alternou corretamente nos filtros ativos;
- usuario temporario e fixtures nao permaneceram no ambiente ao final da validacao.

### Breakpoints validados

- `375`: sem overflow horizontal, faixa horizontal `3x1`, botoes `102x55`, `filterUniqueYCount = 1`;
- `600`: sem overflow horizontal, faixa horizontal `3x1`, botoes `177x55`, `filterUniqueYCount = 1`;
- `840`: sem overflow horizontal, faixa horizontal `3x1`, botoes `233x55`, `filterUniqueYCount = 1`;
- `1200`: sem overflow horizontal, faixa horizontal centralizada `3x1`, botoes `233x55`, `filterUniqueYCount = 1`;
- `1920`: sem overflow horizontal, faixa horizontal centralizada `3x1`, botoes `233x55`, `filterUniqueYCount = 1`.

### Console

- `consoleErrors = 0`
- `consoleWarnings = 0`

### Artefatos

- [agenda-operational-filters-horizontal-check.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-operational-filters-horizontal-check.json)
- [agenda-operational-filters-horizontal-375.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-operational-filters-horizontal-375.png)
- [agenda-operational-filters-horizontal-600.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-operational-filters-horizontal-600.png)
- [agenda-operational-filters-horizontal-840.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-operational-filters-horizontal-840.png)
- [agenda-operational-filters-horizontal-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-operational-filters-horizontal-1200.png)
- [agenda-operational-filters-horizontal-1920.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-operational-filters-horizontal-1920.png)

### Comandos executados

- `php -l plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php`
- `node --check plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js`
- `git diff --check -- plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`
- upload por SSH/SFTP com backup remoto dos arquivos alterados
- criacao e validacao de fixtures temporarios com `wp post create`, `wp post meta update`, `wp post list` e `wp post get`
- validacao publicada com Playwright autenticado em `375`, `600`, `840`, `1200` e `1920`
- limpeza e verificacao final do usuario temporario com `wp user delete`, `wp user list` e `wp user get`

## Agenda: validacao e correcao dos filtros mobile

Fonte de verdade visual seguida nesta correcao: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md` (padrao DPS Signature).

### Objetivo

Verificar o relato de que os atendimentos sumiam ao aplicar filtros na Agenda mobile e corrigir tanto a logica do filtro `TaxiDog` em dados reais quanto a ausencia de feedback visual quando um filtro nao encontra resultados.

### Causa encontrada

- o filtro `TaxiDog` dependia de busca por texto agregado no `haystack`;
- quando o atendimento tinha `TaxiDog` ativo e tambem tinha endereco/logistica preenchidos, o atributo `data-dps-logistics` passava a conter o endereco em vez da string `TaxiDog`, e o filtro deixava de encontrar o card;
- quando um filtro retornava `0` resultados, a Agenda escondia os cards e os paineis, mas nao exibia nenhum estado vazio contextual, o que fazia parecer que a tela tinha quebrado no mobile.

### Ajustes implementados

- renderer operacional passou a expor `data-dps-taxidog` e `data-dps-late` em [trait-dps-agenda-renderer.php](/C:/Users/casaprobst/DPS/plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php:1063);
- filtro JS deixou de depender de texto e passou a usar os atributos estruturais em [agenda-addon.js](/C:/Users/casaprobst/DPS/plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js:792);
- ao filtrar sem resultados, a Agenda agora mostra um estado vazio contextual e oculta o inspetor stale em [desi-pet-shower-agenda-addon.php](/C:/Users/casaprobst/DPS/plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php:3026);
- o estado vazio operacional recebeu encaixe responsivo no workspace em [agenda-addon.css](/C:/Users/casaprobst/DPS/plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css:11435).

### Publicado e validado

- publicado em `https://desi.pet/agenda-de-atendimentos/`;
- backups remotos:
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php.__backup_20260423-100002`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php.__backup_20260423-100002`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js.__backup_20260423-100002`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css.__backup_20260423-100002`

### Validacao funcional

- fixture estrutural criado com `TaxiDog` ativo e endereco real no cliente;
- mobile `375`:
  - `Todos` exibiu `2` cards;
  - `TaxiDog` exibiu `1` card correto (`1740`) mesmo com endereco presente;
  - `Atrasados` exibiu `0` cards e mostrou estado vazio contextual;
- desktop `1200`:
  - `Todos` exibiu `2` cards;
  - `TaxiDog` exibiu `1` card correto (`1740`);
  - `Atrasados` exibiu `0` cards, mostrou estado vazio e ocultou o inspetor;
- `aria-pressed` permaneceu sincronizado;
- `consoleErrors = 0` e `consoleWarnings = 0`.

### Breakpoints validados

- `375`: `TaxiDog` manteve `1` card visivel com `data-dps-taxidog="1"` e logistica preenchida; `Atrasados` mostrou vazio contextual;
- `1200`: `TaxiDog` manteve `1` card visivel com endereco; `Atrasados` mostrou vazio contextual e inspetor oculto.

### Artefatos

- [agenda-mobile-filter-fix-validation.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-mobile-filter-fix-validation.json)
- [agenda-filter-fix-taxidog-375.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-filter-fix-taxidog-375.png)
- [agenda-filter-fix-late-375.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-filter-fix-late-375.png)
- [agenda-filter-fix-taxidog-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-filter-fix-taxidog-1200.png)
- [agenda-filter-fix-late-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-filter-fix-late-1200.png)

### Comandos executados

- `php -l plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php`
- `php -l plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php`
- `node --check plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js`
- `git diff --check -- plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`
- upload por SSH/SFTP com backup remoto dos arquivos alterados
- criacao e validacao publicada de fixtures com `wp post create`, `wp post meta update`, `wp post list` e Playwright autenticado

## Agenda: correcao definitiva da sequencia dos filtros operacionais

Fonte de verdade visual seguida nesta correcao: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md` (padrao DPS Signature).

### Objetivo

Corrigir o bug em que os filtros `Atrasados` e `TaxiDog` faziam a lista operacional sumir depois de alternar estados no desktop e no mobile.

### Causa confirmada

- o JS escondia o painel do dia quando um filtro retornava `0` resultados;
- na troca seguinte, a contagem usava `:visible` dentro de um painel ja oculto;
- como filhos de um painel oculto nunca contam como visiveis, a lista nao conseguia reabrir em `TaxiDog` ou `Todos`;
- o uso de `jQuery.toggle()` tambem criava estilos inline fragilizando o layout dos cards e linhas.

### Ajustes implementados

- filtro operacional passou a usar classe `is-filter-hidden` nos itens em vez de `toggle()` inline;
- a abertura/fechamento do painel agora usa o estado estrutural dos itens, nao `:visible`;
- `Atrasados`, `TaxiDog` e `Todos` voltam a reabrir corretamente depois de qualquer filtro sem resultado;
- `aria-hidden` foi sincronizado nos itens filtrados;
- textos longos do inspetor operacional agora quebram corretamente, evitando overflow em `1200`.

### Publicado e validado

- publicado em `https://desi.pet/agenda-de-atendimentos/`;
- backups remotos:
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js.__backup_20260423-104636`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css.__backup_20260423-104636`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js.__backup_20260423-105230`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css.__backup_20260423-105230`

### Reproducao antes da correcao

- fixture com `2` atendimentos no dia: um normal e um `TaxiDog`;
- sequencia que falhava:
  - inicial: `2` cards;
  - `Atrasados`: `0` cards;
  - `TaxiDog`: `0` cards, mesmo existindo atendimento TaxiDog;
  - `Todos`: `0` cards, mesmo existindo `2` atendimentos.

### Validacao final

- mesma sequencia validada no publicado com fixture controlado;
- `375`: inicial `2`, `Atrasados` `1`, `TaxiDog` `1`, `Todos` `2`, overflow `0`;
- `600`: inicial `2`, `Atrasados` `1`, `TaxiDog` `1`, `Todos` `2`, overflow `0`;
- `840`: inicial `2`, `Atrasados` `1`, `TaxiDog` `1`, `Todos` `2`, overflow `0`;
- `1200`: inicial `2`, `Atrasados` `1`, `TaxiDog` `1`, `Todos` `2`, overflow `0`;
- `1920`: inicial `2`, `Atrasados` `1`, `TaxiDog` `1`, `Todos` `2`, overflow `0`;
- filtros permaneceram horizontais: `filterRows = 1` em todos os breakpoints;
- `consoleErrors = 0` e `consoleWarnings = 0`.

### Artefatos

- [agenda-filter-sequence-before-fix.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-filter-sequence-before-fix.json)
- [agenda-filter-sequence-after-fix.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-filter-sequence-after-fix.json)
- [agenda-filter-late-match-validation.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-filter-late-match-validation.json)
- [agenda-filter-final-v2-breakpoints.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-filter-final-v2-breakpoints.json)
- [agenda-filter-final-v2-late-375.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-filter-final-v2-late-375.png)
- [agenda-filter-final-v2-taxidog-375.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-filter-final-v2-taxidog-375.png)
- [agenda-filter-final-v2-breakpoint-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-filter-final-v2-breakpoint-1200.png)

### Comandos executados

- `node --check plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js`
- `git diff --check -- plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`
- upload por SSH/SFTP com backup remoto dos arquivos alterados
- criacao e validacao publicada de fixtures com `wp user create`, `wp post create`, `wp post meta update`, `wp post list` e Playwright autenticado
- validacao publicada em `375`, `600`, `840`, `1200` e `1920`

## Agenda: filtros operacionais com agendamentos passados

Fonte de verdade visual seguida nesta correcao: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md` (padrao DPS Signature).

### Objetivo

Corrigir a persistencia do erro no site publicado ao filtrar agendamentos passados e refinar a faixa visual dos filtros `Todos`, `Atrasados` e `TaxiDog` na `Fila operacional`.

### Causa encontrada

- o CSS publicado ja recebia `filemtime`, mas o JS principal da Agenda ainda era carregado com `?ver=1.6.0`;
- isso permitia que browsers mantivessem a versao antiga do filtro, mesmo apos novo upload do arquivo;
- a contagem do painel nao indicava o recorte filtrado, dificultando conferir se o filtro estava realmente aplicado.

### Ajustes implementados

- `agenda-addon.js` passou a ser versionado por `filemtime`, igual ao CSS;
- a contagem do painel operacional agora muda para `X atendimentos no filtro` ao aplicar `Atrasados` ou `TaxiDog` e volta ao texto original em `Todos`;
- a contagem por painel usa IDs unicos, evitando duplicidade entre tabela desktop e card responsivo;
- a faixa dos filtros foi compactada: altura reduzida, largura maxima menor, botoes horizontais com toque valido e sem excesso visual;
- a hierarquia da `Fila operacional` ficou mais limpa, com header e toolbar menos pesados no mobile e no desktop.

### Publicado e validado

- publicado em `https://desi.pet/agenda-de-atendimentos/?dps_date=2026-04-20&view=day`;
- backups remotos:
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php.__backup_20260423-110613`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js.__backup_20260423-110613`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css.__backup_20260423-110613`

### Validacao funcional

- sessao autenticada criada via WP-CLI para teste publicado;
- fixtures temporarios criados no dia passado `20/04/2026`:
  - `1753` atrasado;
  - `1754` atrasado com `TaxiDog`;
  - a base publicada tambem tinha `1667` com `TaxiDog` no mesmo recorte;
- `Todos` exibiu `3` atendimentos;
- `Atrasados` exibiu `2` atendimentos e atualizou o contador para `2 atendimentos no filtro`;
- `TaxiDog` exibiu `2` atendimentos e atualizou o contador para `2 atendimentos no filtro`;
- retorno para `Todos` restaurou `3 atendimentos no periodo`;
- JS publicado passou de `?ver=1.6.0` para `?ver=1776953174`;
- `consoleErrors = 0` e `consoleWarnings = 0`.

### Breakpoints validados

- `375`: sem overflow horizontal, botoes `42px`, faixa de filtros `353x75`;
- `600`: sem overflow horizontal, botoes `42px`, faixa de filtros `578x75`;
- `840`: sem overflow horizontal, botoes `44px`, faixa de filtros `802x80`;
- `1200`: sem overflow horizontal, botoes `46px`, faixa de filtros `1138x86`;
- `1920`: sem overflow horizontal, botoes `46px`, faixa de filtros `1138x86`.

### Artefatos

- [agenda-past-filter-repro.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-past-filter-repro.json)
- [agenda-past-filter-published-v3.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-past-filter-published-v3.json)
- [agenda-past-filter-published-v3-375-1-late.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-past-filter-published-v3-375-1-late.png)
- [agenda-past-filter-published-v3-375-2-taxidog.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-past-filter-published-v3-375-2-taxidog.png)
- [agenda-past-filter-published-v3-1200-2-taxidog.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-past-filter-published-v3-1200-2-taxidog.png)
- [agenda-past-filter-published-v3-1920-2-taxidog.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-past-filter-published-v3-1920-2-taxidog.png)

### Comandos executados

- `php -l plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php`
- `node --check plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js`
- upload por SSH/SFTP com backup remoto dos arquivos alterados
- `php -l wp-content/plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php` no servidor
- validacao publicada com Playwright autenticado em `375`, `600`, `840`, `1200` e `1920`
- limpeza dos fixtures e usuario temporario com `wp post delete`, `wp user delete`, `wp post list`, `wp db query` e `wp user get`

## Agenda: ajuste de margens, bordas e overflow da regiao superior

Fonte de verdade visual seguida nesta correcao: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md` (padrao DPS Signature).

### Objetivo

Reequilibrar o layout geral da regiao formada por cabecalho da Agenda, seletor de visualizacao e `Fila operacional`, com foco em margens laterais, bordas internas e prevencao de overflow.

### Ajustes implementados

- largura da Agenda consolidada em `min(100%, 1140px)` com respiro lateral controlado no mobile;
- blocos principais passaram a usar `overflow-x: clip` e `min-width: 0` para impedir estouro de filhos;
- wrapper mobile recebeu margem lateral real, evitando blocos encostados na viewport;
- painel de navegacao deixou de herdar `flex` antigo e foi consolidado como `grid`;
- raio antigo exagerado do painel de navegacao foi reduzido para `2px`;
- bloco de controles recebeu padding simetrico e borda superior propria;
- `Fila operacional` recebeu subtitulo menos estreito, filtros mais contidos e toolbar com largura controlada;
- labels longos no cabecalho passaram a quebrar sem empurrar a grade.

### Publicado e validado

- publicado em `https://desi.pet/agenda-de-atendimentos/?dps_date=2026-04-20&view=day`;
- backups remotos:
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css.__backup_20260423-112918`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css.__backup_20260423-113049`
- CSS publicado com `?ver=1776954650`;
- `consoleErrors = 0` e `consoleWarnings = 0`.

### Breakpoints validados

- `375`: wrapper `339px`, margem lateral `18px`, overflow `0`, painel de navegacao `grid`, raio `2px`;
- `600`: wrapper `564px`, margem lateral `18px`, overflow `0`, painel de navegacao `grid`, raio `2px`;
- `840`: wrapper `800px`, overflow `0`, filtros `560px`, sem elementos fora da Agenda;
- `1200`: wrapper `1140px`, overflow `0`, filtros `560px`, sem elementos fora da Agenda;
- `1920`: wrapper `1140px` centralizado, overflow `0`, filtros `560px`, sem elementos fora da Agenda.

### Artefatos

- [agenda-region-layout-before.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-region-layout-before.json)
- [agenda-region-layout-final.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-region-layout-final.json)
- [agenda-region-layout-final-375.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-region-layout-final-375.png)
- [agenda-region-layout-final-600.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-region-layout-final-600.png)
- [agenda-region-layout-final-840.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-region-layout-final-840.png)
- [agenda-region-layout-final-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-region-layout-final-1200.png)
- [agenda-region-layout-final-1920.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-region-layout-final-1920.png)

### Comandos executados

- `git diff --check -- plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`
- upload por SSH/SFTP com backup remoto do CSS alterado
- validacao publicada com Playwright autenticado em `375`, `600`, `840`, `1200` e `1920`
- remocao e verificacao do usuario temporario com `wp user delete` e `wp user get`

## Agenda: empty state operacional DPS Signature

Fonte de verdade visual seguida nesta correcao: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md` (padrao DPS Signature).

### Objetivo

Substituir o empty state generico visto no filtro `Atrasados` por uma composicao mais premium e proprietaria da Agenda, alinhada ao DPS Signature e sem iconografia antiga de calendario.

### Antes/Depois

- Antes: bloco centralizado com borda tracejada, icone de calendario generico e mensagem com pouca presenca de marca.
- Depois: bloco operacional com geometria reta, linha petrol, malha sutil, selo tipografico `DPS Agenda`, microcopy contextual e hierarquia mais forte para recortes sem atrasos.
- Arquivos de codigo alterados:
  - [desi-pet-shower-agenda-addon.php](/C:/Users/casaprobst/DPS/plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php:3042)
  - [agenda-addon.js](/C:/Users/casaprobst/DPS/plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js:845)
  - [agenda-addon.css](/C:/Users/casaprobst/DPS/plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css:12111)

### Validacao visual

- Publicado em `https://desi.pet/agenda-de-atendimentos/?dps_date=2026-04-24&view=day`.
- Validado com sessao temporaria autenticada por usuario administrativo criado via WP-CLI apenas para o teste publicado.
- `375`, `600`, `840`, `1200` e `1920`: `overflowX = 0`, `Todos = 2`, `TaxiDog = 1`, `Atrasados = 0`.
- O pseudo-elemento operacional foi validado sem conteudo herdado de calendario: `beforeContent = ""`.
- `consoleErrors = 0` e `consoleWarnings = 0` na bateria final publicada.
- Durante a publicacao, o pacote completo dos plugins DPS foi republicado sem BOM em arquivos PHP que contaminavam respostas JSON do AJAX publicado.

### Publicacao e limpeza controlada

- Backup remoto principal do redeploy final: `/home/u944637195/backups/dps-plugin-deploy-20260423-122803`.
- Pacote completo publicado novamente apos a higienizacao de BOM em:
  - [class-dps-ai-integration-portal.php](/C:/Users/casaprobst/DPS/plugins/desi-pet-shower-ai/includes/class-dps-ai-integration-portal.php)
  - [desi-pet-shower-booking-addon.php](/C:/Users/casaprobst/DPS/plugins/desi-pet-shower-booking/desi-pet-shower-booking-addon.php)
  - [class-dps-game-addon.php](/C:/Users/casaprobst/DPS/plugins/desi-pet-shower-game/includes/class-dps-game-addon.php)
  - [desi-pet-shower-loyalty.php](/C:/Users/casaprobst/DPS/plugins/desi-pet-shower-loyalty/desi-pet-shower-loyalty.php)
  - [class-dps-email-reports.php](/C:/Users/casaprobst/DPS/plugins/desi-pet-shower-push/includes/class-dps-email-reports.php)
  - [class-dps-push-admin.php](/C:/Users/casaprobst/DPS/plugins/desi-pet-shower-push/includes/class-dps-push-admin.php)
- Fixture temporario utilizado para validacao publicada:
  - tag: `codex_dps_empty_signature_20260423-121941`
  - posts: `1760`, `1761`, `1762`, `1763`, `1764`
  - usuario temporario removido: `codex_dps_20260423121941`
- Ao final desta validacao, fixtures e usuario temporario foram removidos do servidor.

### Artefatos

- [agenda-empty-signature-published-check.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-empty-signature-published-check.json)
- [agenda-empty-signature-published-375.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-empty-signature-published-375.png)
- [agenda-empty-signature-published-600.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-empty-signature-published-600.png)
- [agenda-empty-signature-published-840.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-empty-signature-published-840.png)
- [agenda-empty-signature-published-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-empty-signature-published-1200.png)
- [agenda-empty-signature-published-1920.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-empty-signature-published-1920.png)
- [agenda-empty-signature-published-1200-all.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-empty-signature-published-1200-all.png)
- [agenda-empty-signature-published-1200-taxidog.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/agenda-empty-signature-published-1200-taxidog.png)

### Comandos executados

- `php -l plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php`
- `node --check plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js`
- `git diff --check -- plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`
- `Select-String` no add-on da Agenda para o escape antigo de calendario e nomenclaturas do padrao visual anterior
- upload do pacote completo por SSH/SFTP com backup remoto em `/home/u944637195/backups/dps-plugin-deploy-20260423-122803`
- `php -l` remoto dos arquivos PHP higienizados sem BOM
- varredura remota com `python3` para confirmar `REMOTE_BOM_COUNT=0`
- Playwright autenticado no ambiente publicado em `375`, `600`, `840`, `1200` e `1920`
- `wp post list`, `wp post delete`, `wp user get` e `wp user delete` para limpeza do fixture temporario

## Cadastro publico: consolidacao do Google Places

Fonte de verdade visual seguida nesta correcao: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md` (padrao DPS Signature).

### Objetivo

Fechar o achado aberto em `2026-04-22` no cadastro publico, removendo a carga duplicada do Google Maps Places entre a trilha compartilhada `dpsSignatureGooglePlacesReady` e a trilha local `dpsRegistrationGooglePlacesReady`, sem quebrar autocomplete, validacao ou a UX funcional do formulario.

### Ajuste implementado

- o bootstrap de Google Places do `registration-v2.js` passou a aderir primeiro a uma carga compartilhada ja existente na pagina;
- quando a trilha compartilhada ainda nao existe, o add-on deixa de criar uma callback paralela e sobe a propria carga usando a mesma callback canonica `dpsSignatureGooglePlacesReady`;
- a correcao ficou restrita ao frontend do cadastro, sem reabrir o layout nem a hierarquia visual do shell DPS Signature.

### Publicado e validado

- publicado em `https://desi.pet/cadastro-de-clientes-e-pets/`;
- backup remoto do arquivo substituido:
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-frontend/assets/js/registration-v2.js.__backup_20260423-163110`
- runtime publicado confirmou:
  - `api_js_count = 1`;
  - unica callback carregada: `dpsSignatureGooglePlacesReady`;
  - `runtime_state.placesReady = 1`;
  - `runtime_state.hasPacTargetInput = true`;
  - `runtime_state.googleReady = true`;
  - `registration-v2.js` publicado serviu com `?ver=1776972670`;
- a submissao vazia continuou bloqueada no cliente com os erros esperados para tutor e pet, confirmando que a validacao local permaneceu ativa;
- nao houve `page_errors` nem erro de duplicidade do Google Maps no probe final;
- residuos fora do escopo desta pendencia:
  - warning do Google sobre `loading=async` na carga direta da API;
  - warning da API legada `google.maps.places.Autocomplete`;
  - erro CORS de `adsbygoogle.js`, alheio ao cadastro.

### Artefatos

- [cadastro-google-maps-final-verification.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/followup-verification/cadastro-google-maps-final-verification.json)
- [cadastro-google-maps-final-verification-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/followup-verification/cadastro-google-maps-final-verification-1200.png)

### Comandos executados

- `node --check plugins/desi-pet-shower-frontend/assets/js/registration-v2.js`
- `git diff --check -- plugins/desi-pet-shower-frontend/assets/js/registration-v2.js`
- upload por FTP/SFTP do `registration-v2.js` publicado com backup remoto previo
- validacao publicada com Chrome headless via `playwright-core`, gerando screenshot e JSON em `docs/screenshots/2026-04-23/followup-verification/`

## Portal do Cliente: bootstrap publicado e CTA `CRIAR OU REDEFINIR SENHA`

Fonte de verdade visual seguida nesta correcao: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md` (padrao DPS Signature).

### Objetivo

Fechar o blocker registrado em `2026-04-22` no Portal do Cliente publicado, eliminando os erros de runtime do bootstrap e restaurando o fluxo real do CTA `CRIAR OU REDEFINIR SENHA`, que precisava continuar assincrono, anti-enumeration e com feedback inline na propria tela.

### Causa encontrada

- o shell principal do portal chamava handlers declarados em um bloco tardio do mesmo `client-portal.js`, o que quebrava o bootstrap publicado com erros como `handleReviewForm is not defined` e `handlePetHistoryTabs is not defined`;
- o observer de toasts ainda podia tentar observar `document.body` cedo demais;
- como o bootstrap quebrava antes de estabilizar o shell, o CTA de senha deixava de responder, nao abria modal e tambem nao disparava o AJAX esperado.

### Ajustes implementados

- criado um mecanismo de proxy/bridge no bootstrap inicial para handlers tardios do add-on, preservando os contratos externos do portal;
- review form, historico dos pets, repetir servico, exportacao PDF e timeline (`load more` + filtro por periodo) passaram a ser delegados para o bloco tardio de enhancements;
- o `MutationObserver` dos toasts agora so inicia quando `document.body` existe;
- a casca publica de acesso/reset publicada foi mantida no padrao DPS Signature com geometria reta (`0px` e `2px`) e paleta `ink`/`petrol`/`paper`/`bone`, sem alterar shortcodes, hooks ou endpoints.

### Publicado e validado

- publicado em `https://desi.pet/portal-do-cliente/`;
- backups remotos:
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-client-portal/assets/js/client-portal.js.__backup_20260423-184832`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-client-portal/assets/css/client-portal-auth.css.__backup_20260423-184832`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-client-portal/assets/js/client-portal.js.__backup_20260423-185524`
- assets ativos confirmados no probe final:
  - `client-portal.js?ver=1776981326`
  - `client-portal-auth.css?ver=1776980914`

### Validacao funcional

- `page_errors = []` no probe final publicado;
- CTA `Criar ou redefinir senha` encontrado e clicado com sucesso;
- o fluxo permaneceu na mesma URL, sem navegacao e sem abertura de modal:
  - `opened_dialog = false`
  - `navigated = false`
- o AJAX `dps_request_portal_password_access` respondeu `200` com `success = true`;
- feedback inline final exibido no shell:
  - `Se este e-mail estiver cadastrado no portal, voce recebera as instrucoes para criar ou redefinir a senha.`

### Breakpoints validados

- `375`: `overflowX = 0`, shell `355px`, hero `328x157`, CTA `295x56`, campos e cards empilhados corretamente;
- `600`: `overflowX = 0`, shell `580px`, hero `548x157`, CTA `498x56`;
- `840`: `overflowX = 0`, shell `820px`, hero `786x165`, grid em tres colunas, CTA `200x82`;
- `1200`: `overflowX = 0`, shell `1140px`, hero `1092x191`, CTA `292x56`, feedback inline validado;
- `1920`: `overflowX = 0`, shell maximo mantido em `1140px`, CTA `292x56`, composicao centralizada.

### Console

- sem erros de runtime do portal no probe final;
- residuo externo fora do escopo do add-on: `adsbygoogle.js` bloqueado por CORS no dominio publicado;
- log informativo do jQuery Migrate continua presente no ambiente publicado.

### Artefatos

- [portal-cliente-final-verification.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/followup-verification/portal-cliente-final-verification.json)
- [portal-cliente-final-verification-375.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/followup-verification/portal-cliente-final-verification-375.png)
- [portal-cliente-final-verification-600.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/followup-verification/portal-cliente-final-verification-600.png)
- [portal-cliente-final-verification-840.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/followup-verification/portal-cliente-final-verification-840.png)
- [portal-cliente-final-verification-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/followup-verification/portal-cliente-final-verification-1200.png)
- [portal-cliente-final-verification-1920.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/followup-verification/portal-cliente-final-verification-1920.png)
- [portal-cliente-final-verification-1200-feedback.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/followup-verification/portal-cliente-final-verification-1200-feedback.png)

### Comandos executados

- `node --check plugins/desi-pet-shower-client-portal/assets/js/client-portal.js`
- `git diff --check -- plugins/desi-pet-shower-client-portal/assets/js/client-portal.js plugins/desi-pet-shower-client-portal/assets/css/client-portal-auth.css ANALYSIS.md CHANGELOG.md`
- upload por SSH/SFTP com backup remoto de `client-portal.js` e `client-portal-auth.css`
- republicacao final do `client-portal.js` com novo backup remoto apos o fechamento do ultimo handler tardio
- validacao publicada com Chrome headless via `playwright`, gerando JSON e screenshots em `docs/screenshots/2026-04-23/followup-verification/`

## Portal do Cliente: auditoria integral do login inicial

Fonte de verdade visual seguida nesta auditoria e implementacao: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md` (padrao DPS Signature).

### Objetivo

Auditar integralmente a pagina inicial publica do Portal do Cliente em `https://desi.pet/portal-do-cliente/`, reescrever o shell necessario para alinhar o acesso inicial ao DPS Signature publicado, estabilizar o runtime publico e validar os fluxos reais de senha, magic link e redefinicao de senha com fixture temporario emitido via WP-CLI.

### Achados decisivos

- a landing publica ainda estava acoplada ao bundle autenticado do portal, o que nao era adequado para um shell de acesso inicial;
- o reset de senha emitido pelo proprio add-on estava quebrado no publicado porque `login` e `key` eram passados com `rawurlencode()` antes de `add_query_arg()`, produzindo link duplamente codificado e invalido para `check_password_reset_key()`;
- a hierarquia publica precisava de reescrita, com caminho principal mais claro para senha recorrente, comparacao entre modos e suporte contextual sem competir com o CTA primario.

### Ajustes implementados

- reescrita integral da landing publica e da tela de reset no padrao DPS Signature;
- criacao do runtime dedicado `client-portal-access.js` para tabs, sincronizacao de e-mail, toggles de senha e AJAX da tela publica;
- manutencao dos contratos externos do add-on: shortcodes, hooks, nonces, nomes de campos e endpoints AJAX;
- correcao do fluxo de reset publicado, removendo a dupla codificacao na geracao do link e nos redirects internos do proprio reset.

### Publicado e validado

- publicado em `https://desi.pet/portal-do-cliente/`;
- backups remotos desta rodada final:
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-client-portal/includes/class-dps-client-portal.php.__backup_20260423-200623`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-client-portal/includes/class-dps-portal-user-manager.php.__backup_20260423-200623`
- assets publicos ativos confirmados:
  - `client-portal-access.js?ver=1776984600`
  - `client-portal-auth.css?ver=1776984600`

### Validacao funcional

- fixture temporario criado via WP-CLI remoto:
  - `client_id = 1769`
  - `pet_id = 1770`
  - `user_id = 44`
  - `email = codex.portal.audit.20260423-200043@example.com`
- `pageErrors = []` em todas as amostras do shell publico e dos fluxos autenticados;
- `Criar ou redefinir senha` respondeu `200` com feedback inline anti-enumeration:
  - `Se este e-mail estiver cadastrado no portal, voce recebera as instrucoes para criar ou redefinir a senha.`
- `Link rapido` respondeu `200` com feedback inline:
  - `Link enviado com sucesso. Verifique sua caixa de entrada e spam.`
- login por senha autenticou e abriu o portal com `9` tabs;
- login por magic link autenticou e abriu o portal com `9` tabs;
- reset abriu com os campos validos, `toggleCount = 2` e `overflowX = 0`.

### Breakpoints validados

- `375`: `overflowX = 0`, shell `355px`, hero `328x773`, CTA `295x56`;
- `600`: `overflowX = 0`, shell `580px`, hero `548x504`, CTA `514x56`;
- `840`: `overflowX = 0`, shell `820px`, hero `786x434`, CTA `751x56`;
- `1200`: `overflowX = 0`, shell `1140px`, hero `1092x356`, CTA `617x56`;
- `1920`: `overflowX = 0`, shell maximo `1140px`, hero `1092x356`, CTA `617x56`.

### Console

- sem erros de runtime do add-on no login, reset, senha recorrente ou magic link;
- residuo externo fora do escopo do add-on: `adsbygoogle.js` bloqueado por CORS no dominio publicado;
- notice do `all-in-one-wp-migration` apareceu apenas nas chamadas WP-CLI remotas usadas para fixture e limpeza.

### Artefatos

- [portal-cliente-login-audit-final.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/followup-verification/portal-cliente-login-audit-final.json)
- [portal-cliente-login-audit-375.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/followup-verification/portal-cliente-login-audit-375.png)
- [portal-cliente-login-audit-600.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/followup-verification/portal-cliente-login-audit-600.png)
- [portal-cliente-login-audit-840.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/followup-verification/portal-cliente-login-audit-840.png)
- [portal-cliente-login-audit-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/followup-verification/portal-cliente-login-audit-1200.png)
- [portal-cliente-login-audit-1920.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/followup-verification/portal-cliente-login-audit-1920.png)
- [portal-cliente-login-reset-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/followup-verification/portal-cliente-login-reset-1200.png)
- [portal-cliente-login-password-success-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/followup-verification/portal-cliente-login-password-success-1200.png)
- [portal-cliente-login-magic-success-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/followup-verification/portal-cliente-login-magic-success-1200.png)
- [portal-cliente-login-password-reset-feedback-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/followup-verification/portal-cliente-login-password-reset-feedback-1200.png)
- [portal-cliente-login-magic-request-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-23/followup-verification/portal-cliente-login-magic-request-1200.png)

### Comandos executados

- `php -l plugins/desi-pet-shower-client-portal/includes/class-dps-client-portal.php`
- `php -l plugins/desi-pet-shower-client-portal/includes/class-dps-portal-user-manager.php`
- upload por SSH/SFTP com backup remoto de `class-dps-client-portal.php` e `class-dps-portal-user-manager.php`
- `php -l` remoto dos dois arquivos publicados
- fixture temporario, refresh de token/reset e limpeza de rate limit via WP-CLI remoto
- validacao publicada com Chrome headless via `playwright`, gerando JSON e screenshots em `docs/screenshots/2026-04-23/followup-verification/`
