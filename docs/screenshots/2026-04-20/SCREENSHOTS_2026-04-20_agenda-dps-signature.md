# Registro visual - Agenda DPS Signature

Data: 2026-04-20
Escopo: `plugins/desi-pet-shower-agenda`
Ambiente validado: produção `https://www.desi.pet/`
Fonte de verdade visual: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`

## Contexto

Esta rodada substituiu a validação estática anterior por testes reais em produção, com sessão administrativa temporária criada por WP-CLI apenas para QA. A Agenda foi revisada como interface operacional sob o sistema visual DPS Signature, preservando contratos de negócio, shortcodes, hooks, nonces, capabilities e fluxo de dados.

## Correções confirmadas nesta rodada

- `agenda-admin.css` e `dashboard.css` passaram a carregar no admin real mesmo com prefixo de hook diferente do esperado no site.
- O dashboard deixou de vazar JavaScript bruto no HTML renderizado.
- `dps-design-tokens.css`, `dps-base.css` e agora também os assets do add-on Agenda usam versionamento por `filemtime`, evitando cache preso em produção.
- O CTA operacional deixou de duplicar rótulos e passou a respeitar hierarquia diferente entre desktop e mobile.
- O modal de serviços voltou a abrir corretamente e o cabeçalho do pet foi ajustado para leitura correta.
- Microcopy em português brasileiro foi revisada nos pontos tocados: acentuação, labels operacionais e estado de período.
- A página pública da Agenda deixou de carregar `gtag`, AdSense e `dns-prefetch` externos do Site Kit.
- A Agenda pública e o hub admin deixaram de carregar scripts do Elementor e widgets globais do Hostinger que geravam ruído visual e erros de console.
- O placeholder `#vue-app` do Hostinger AI Assistant deixou de ser injetado no `admin_footer` das telas da Agenda.

## Breakpoints validados

- `375px`: leitura em cards/blocos, CTA operacional encurtado para `+ Abrir`, sem overflow horizontal da Agenda.
- `600px`: inspeção real da tabela responsiva e do fluxo operacional.
- `840px`: inspeção real da composição intermediária da Agenda.
- `1200px`: inspeção real completa das visões pública e operacional.
- `1920px`: inspeção real do hub administrativo e dashboard.

Observação: os registros finais arquivados focam nos estados representativos após as últimas correções. Parte dos breakpoints intermediários foi validada em navegador real sem necessidade de nova captura final.

## Interações reais validadas

- Agenda pública carregando com dados reais em `2026-02-10`.
- Navegação por aba entre `visao-rapida`, `operacao` e `detalhes`.
- Expansão do painel operacional com `aria-expanded="true"`.
- Modal de serviços abrindo com conteúdo real.
- Modal de serviços fechando com `Escape` e retorno de foco ao gatilho.
- Hub admin da Agenda em `Dashboard`, `Configurações` e `Integrações Google`.
- Ação rápida do dashboard `Amanhã`, atualizando URL e data de contexto.
- Console da Agenda pública sem erros nem warnings após a remoção dos scripts externos.
- Console do hub admin da Agenda sem erros nem warnings após a supressão dos assets globais de terceiros.

## Capturas salvas

- `docs/screenshots/2026-04-20/agenda-public-visao-rapida-1200.png`
- `docs/screenshots/2026-04-20/agenda-public-operacao-1200.png`
- `docs/screenshots/2026-04-20/agenda-public-operacao-375.png`
- `docs/screenshots/2026-04-20/agenda-public-operacao-expandido-1200.png`
- `docs/screenshots/2026-04-20/agenda-public-servicos-modal-1200.png`
- `docs/screenshots/2026-04-20/agenda-admin-dashboard-1920.png`
- `docs/screenshots/2026-04-20/agenda-admin-settings-1920.png`
- `docs/screenshots/2026-04-20/agenda-admin-google-integrations-1920.png`
- `docs/screenshots/2026-04-20/agenda-public-cleanup-viewport.png`
- `docs/screenshots/2026-04-20/agenda-admin-cleanup-viewport.png`

## Arquivos afetados nesta rodada de QA/correção

- `plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php`
- `plugins/desi-pet-shower-agenda/includes/class-dps-agenda-hub.php`
- `plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php`
- `plugins/desi-pet-shower-agenda/includes/integrations/class-dps-google-integrations-settings.php`
- `plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`
- `plugins/desi-pet-shower-base/assets/css/dps-design-tokens.css`
- `plugins/desi-pet-shower-base/desi-pet-shower-base.php`

## Achados externos ao escopo da Agenda

- O ambiente WordPress ainda mantém menus e integrações de terceiros no admin global, mas a superfície da Agenda ficou isolada desse ruído.
- Em mobile autenticado, o cabeçalho do tema/ambiente admin interfere visualmente na viewport; não é markup da Agenda.
