# Agenda Production Audit v2 — 2026-04-21

## Contexto
- Fonte de verdade visual: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.
- Ambiente validado: `https://desi.pet/agenda-de-atendimentos/` e `https://desi.pet/wp-admin/admin.php?page=dps-agenda-hub`.
- Sessão autenticada criada via WP-CLI para operador com `dps_manage_appointments` e admin com `manage_options`.

## Ajustes desta rodada
- Reescrita do resumo colapsado da aba `Operação` para reduzir altura de linha e remover ruído repetitivo.
- Compactação dos cards da aba `Detalhes`, com resumo operacional mais denso e TaxiDog/Observações menos verbosos.
- Correção da grid dos painéis expandidos para não esticar a coluna de check-in/check-out à altura do checklist.
- Reorganização dos itens do checklist no desktop para alinhar ações na mesma linha.
- Substituição de microcopy visível que ainda citava `M3` por `DPS Signature`.
- Toast com `aria-live="polite"` para feedback operacional mais consistente.

## Breakpoints validados
- `375px`
- `600px`
- `840px`
- `1200px`
- `1920px`

## Fluxos validados
- Visitante sem login: acesso bloqueado corretamente.
- Operador: acesso à Agenda, alternância de abas, modal de serviços, atualização de status, TaxiDog, check-in, check-out e checklist.
- Admin: acesso ao hub e dashboard da Agenda.
- Checklist: visível apenas em `finalizado` e `finalizado_pago`; oculto em `pendente`.

## Evidências principais
- `operator-visao-rapida-1200.png`
- `operator-operacao-1200.png`
- `operator-operacao-expandido.png`
- `operator-detalhes-1200.png`
- `operator-detalhes-375.png`
- `operator-operacao-375.png`
- `operator-services-modal.png`
- `admin-hub-1920.png`
- `smoke-report.json`

## Observações
- Os full-page screenshots mobile e alguns desktop com cabeçalho fixo do tema podem mostrar repetição visual do header durante a captura longa. Isso é artefato do screenshot de página completa do tema/sticky header, não duplicação do markup da Agenda.
- O publicado respondeu `200` e os PHP enviados passaram em `php -l` no servidor.

## Arquivos afetados nesta rodada
- `plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php`
- `plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php`
- `plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`
- `plugins/desi-pet-shower-agenda/assets/css/checklist-checkin.css`
- `plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js`
