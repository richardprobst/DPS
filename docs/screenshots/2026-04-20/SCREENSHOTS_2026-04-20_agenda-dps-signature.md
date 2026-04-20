# Registro visual - Agenda DPS Signature

Data: 2026-04-20
Escopo: `plugins/desi-pet-shower-agenda`
Sistema visual: `DPS Signature`

## Contexto

A Agenda foi tratada como interface operacional/admin, nao como landing page. A decisao foi reescrever a camada visual acumulada em vez de acrescentar novos overrides. Os contratos de negocio foram preservados: shortcodes, hooks, AJAX, nonces, capabilities, slugs, schema e fluxo de dados nao foram alterados.

## Antes

- CSS principal ainda carregava historico visual baseado em M3/Material, com muitos overrides e estados redundantes.
- Checklist, check-in, dashboard e admin tinham arquivos auxiliares com tokens antigos, cantos arredondados, transicoes amplas e densidade visual irregular.
- Badges, acoes, TaxiDog, GPS e pagamento ainda tinham residuos de emoji, cor hardcoded ou microcopy de construcao.

## Depois

- `agenda-addon.css` foi substituido por uma camada operacional DPS Signature: superficies claras/quentes, petrol como estrutura, action apenas para acao real, warning/danger apenas para estado, cantos retos e bordas finas.
- `checklist-checkin.css`, `dashboard.css` e `agenda-admin.css` foram reescritos com hierarquia consistente, foco visivel, breakpoints explicitos e sem `transition: all`.
- Modais e toasts foram alinhados com foco, `aria-live`, fechamento por teclado e sem dependencia visual de icones.
- Microcopy revisada em portugues brasileiro nos fluxos tocados: acentos, reticencias tipograficas e labels mais diretos.
- TaxiDog/GPS/pagamento/status foram reduzidos a componentes objetivos, sem decoracao gratuita.

## Breakpoints previstos

- `375px`: tabelas viram leitura em blocos, botoes ocupam largura util, modais respeitam viewport.
- `600px`: formularios e controles passam para coluna, acoes ficam com area de toque maior.
- `840px`: dashboard e paineis deixam grids largos e priorizam leitura vertical.
- `1200px`: KPIs e capacidade reduzem colunas sem overflow horizontal.
- `1920px`: largura maxima preserva densidade operacional sem esticar linhas longas.

## Evidencia de captura

Capturas reais ainda nao foram geradas nesta execucao porque o workspace nao contem uma instalacao WordPress local executavel ou `wp-cli` disponivel para abrir as telas da Agenda. A validacao feita nesta etapa e estatica por codigo/CSS. Quando o WordPress local estiver acessivel, capturar:

- Agenda principal: `375`, `600`, `840`, `1200`, `1920`.
- Detalhes expandidos com checklist/check-in.
- Modal de servicos.
- Modal de pagamento.
- Dashboard administrativo.
- Hub/configuracoes da Agenda.
- Integracoes Google.

## Arquivos afetados

- `plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`
- `plugins/desi-pet-shower-agenda/assets/css/checklist-checkin.css`
- `plugins/desi-pet-shower-agenda/assets/css/dashboard.css`
- `plugins/desi-pet-shower-agenda/assets/css/agenda-admin.css`
- `plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js`
- `plugins/desi-pet-shower-agenda/assets/js/checklist-checkin.js`
- `plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php`
- `plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php`
- `plugins/desi-pet-shower-agenda/includes/class-dps-agenda-gps-helper.php`
- `plugins/desi-pet-shower-agenda/includes/class-dps-agenda-payment-helper.php`
- `plugins/desi-pet-shower-agenda/includes/class-dps-agenda-taxidog-helper.php`
- `plugins/desi-pet-shower-agenda/includes/class-dps-agenda-checklist-service.php`
- `plugins/desi-pet-shower-agenda/includes/class-dps-agenda-checkin-service.php`
- `plugins/desi-pet-shower-agenda/includes/integrations/class-dps-google-calendar-sync.php`
- `plugins/desi-pet-shower-agenda/includes/integrations/class-dps-google-tasks-sync.php`
