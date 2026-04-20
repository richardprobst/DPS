# Agenda Hardening Smoke — 2026-04-20

## Contexto
- Superfície validada: add-on `plugins/desi-pet-shower-agenda`
- Fonte de verdade visual seguida na implementação: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`
- Ambiente validado: produção em [desi.pet](https://desi.pet/)
- Objetivo desta rodada: fechar o hardening operacional da Agenda, corrigir regressões visuais do shell administrativo, remover ruído externo nas telas da Agenda e estabilizar a smoke suite real

## Ajustes principais desta rodada
- Reescrita do shell do hub administrativo para DPS Signature, com navegação própria e sem dependência visual do `nav-tab-wrapper`
- Reescrita do carregamento de assets da Agenda com versionamento por `filemtime()` para evitar CSS/JS antigo em cache
- Remoção de efeito colateral na renderização do dashboard do hub: o HTML interno deixou de ser filtrado por `wp_kses_post` no hub
- Extração do script inline do dashboard para `assets/js/dashboard-admin.js`
- Reestilização do bloco de capacidade semanal no dashboard administrativo
- Saneamento da tela admin da Agenda para ocultar ruídos externos não operacionais:
  - `#setting-error-tgmpa`
  - `.wp-pointer`
  - mounts residuais de Hostinger/Kodee já tratados antes
- Correção de warnings reais do add-on no frontend:
  - substituição de `$.trim(...)`
  - substituição de `removeAttr('hidden')` por `prop('hidden', false)`
- Ajustes da smoke suite para refletir o DOM real e o estado acumulado do registro QA sem falsos negativos

## Before / After
- Antes:
  - [admin-hub-current.png](/Users/casaprobst/DPS/docs/screenshots/2026-04-20/admin-hub-current.png)
  - [agenda-public-current.png](/Users/casaprobst/DPS/docs/screenshots/2026-04-20/agenda-public-current.png)
- Depois:
  - [admin-hub-1920-clean.png](/Users/casaprobst/DPS/docs/screenshots/2026-04-20/agenda-hardening-smoke/admin-hub-1920-clean.png)
  - [operator-operacao-1200.png](/Users/casaprobst/DPS/docs/screenshots/2026-04-20/agenda-hardening-smoke/operator-operacao-1200.png)
  - [operator-operacao-expandido.png](/Users/casaprobst/DPS/docs/screenshots/2026-04-20/agenda-hardening-smoke/operator-operacao-expandido.png)
  - [operator-services-modal.png](/Users/casaprobst/DPS/docs/screenshots/2026-04-20/agenda-hardening-smoke/operator-services-modal.png)

## Breakpoints validados
- `375px`: [operator-operacao-375.png](/Users/casaprobst/DPS/docs/screenshots/2026-04-20/agenda-hardening-smoke/operator-operacao-375.png)
- `600px`: [operator-operacao-600.png](/Users/casaprobst/DPS/docs/screenshots/2026-04-20/agenda-hardening-smoke/operator-operacao-600.png)
- `840px`: [operator-operacao-840.png](/Users/casaprobst/DPS/docs/screenshots/2026-04-20/agenda-hardening-smoke/operator-operacao-840.png)
- `1200px`: [operator-operacao-1200.png](/Users/casaprobst/DPS/docs/screenshots/2026-04-20/agenda-hardening-smoke/operator-operacao-1200.png)
- `1920px`: [operator-operacao-1920.png](/Users/casaprobst/DPS/docs/screenshots/2026-04-20/agenda-hardening-smoke/operator-operacao-1920.png)

## Telas e cenários validados
- Acesso público sem login:
  - [guest-1200.png](/Users/casaprobst/DPS/docs/screenshots/2026-04-20/agenda-hardening-smoke/guest-1200.png)
  - [guest-375.png](/Users/casaprobst/DPS/docs/screenshots/2026-04-20/agenda-hardening-smoke/guest-375.png)
- Operador autenticado:
  - [operator-agenda-1200.png](/Users/casaprobst/DPS/docs/screenshots/2026-04-20/agenda-hardening-smoke/operator-agenda-1200.png)
  - [operator-admin-denied.png](/Users/casaprobst/DPS/docs/screenshots/2026-04-20/agenda-hardening-smoke/operator-admin-denied.png)
- Admin autenticado:
  - [admin-hub-1920-clean.png](/Users/casaprobst/DPS/docs/screenshots/2026-04-20/agenda-hardening-smoke/admin-hub-1920-clean.png)

## Smoke suite real
- Script-base: `plugins/desi-pet-shower-agenda/tests/smoke/agenda-production-smoke.spec.mjs`
- Evidência da execução:
  - [smoke-report.json](/Users/casaprobst/DPS/docs/screenshots/2026-04-20/agenda-hardening-smoke/smoke-report.json)
- Cenários cobertos:
  - convidado sem acesso operacional
  - operador com `dps_manage_appointments`
  - admin no hub da Agenda
  - fluxo operacional com:
    - modal de serviços
    - nonce inválido
    - conflito de versão
    - alteração de status
    - expansão do painel operacional
    - checklist
    - check-in/check-out
    - TaxiDog
    - reenvio de pagamento
    - checagem de overflow horizontal

## Arquivos afetados nesta fase
- `plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`
- `plugins/desi-pet-shower-agenda/assets/css/agenda-admin.css`
- `plugins/desi-pet-shower-agenda/assets/css/checklist-checkin.css`
- `plugins/desi-pet-shower-agenda/assets/css/dashboard.css`
- `plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js`
- `plugins/desi-pet-shower-agenda/assets/js/dashboard-admin.js`
- `plugins/desi-pet-shower-agenda/assets/js/pet-profile-modal.js`
- `plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php`
- `plugins/desi-pet-shower-agenda/includes/class-dps-agenda-access.php`
- `plugins/desi-pet-shower-agenda/includes/class-dps-agenda-hub.php`
- `plugins/desi-pet-shower-agenda/includes/class-dps-agenda-payment-helper.php`
- `plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php`
- `plugins/desi-pet-shower-agenda/tests/smoke/agenda-production-smoke.spec.mjs`

## Observações
- O ruído de `report-only CSP` envolvendo `accounts.google.com` foi tratado na smoke suite como ruído externo conhecido do ambiente, sem evidência de quebra funcional na Agenda
- Os avisos reais de `jQuery Migrate` que vinham do próprio add-on foram corrigidos nesta rodada
