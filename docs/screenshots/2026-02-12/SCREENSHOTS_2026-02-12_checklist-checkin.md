# Screenshots 2026-02-12 — Checklist Operacional & Check-in/Check-out (Agenda Add-on v1.2.0)

## Contexto
- **Objetivo:** Documentar visualmente os novos componentes de Checklist Operacional (etapas do banho e tosa com retrabalho) e Check-in/Check-out (registro de entrada/saída com itens de segurança) implementados na Agenda.
- **Ambiente:** Preview HTML estático com CSS do design system M3 (dps-design-tokens.css + checklist-checkin.css). Sem WordPress runtime.
- **Referência de design M3 utilizada:** `docs/visual/VISUAL_STYLE_GUIDE.md`, `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md`
- **Versão:** Agenda Add-on v1.2.0

## Antes/Depois
- **Antes:** Nenhuma funcionalidade de checklist ou check-in/check-out existia na agenda. O fluxo operacional do banho e tosa não era rastreado em etapas, e a entrada/saída dos pets não era registrada.
- **Depois:** Dois novos painéis interativos por agendamento: (1) Checklist operacional com 6 etapas (pré-banho, banho, secagem, tosa/corte, orelhas/unhas, acabamento), barra de progresso e sistema de retrabalho com motivo; (2) Check-in/check-out com 7 itens de segurança (pulgas, carrapatos, feridinhas, alergia, otite, nós, comportamento), observações e cálculo de duração.
- **Arquivos de código alterados:**
  - `plugins/desi-pet-shower-agenda/includes/class-dps-agenda-checklist-service.php` (novo)
  - `plugins/desi-pet-shower-agenda/includes/class-dps-agenda-checkin-service.php` (novo)
  - `plugins/desi-pet-shower-agenda/assets/css/checklist-checkin.css` (novo)
  - `plugins/desi-pet-shower-agenda/assets/js/checklist-checkin.js` (novo)
  - `plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php` (AJAX, enqueue, render helpers)

## Capturas

### Página completa (todos os cenários)
- `./checklist-checkin-desktop-fullpage.png` — Captura completa de todos os cenários em sequência (desktop 1280px)

### 📋 Checklist Operacional
- `./checklist-initial-0pct.png` — Cenário 1: Checklist inicial com 0% de conclusão, todas as etapas pendentes com botões "Concluir" e "Pular"
- `./checklist-progress-67pct.png` — Cenário 2: Checklist com 67% de conclusão, mostrando etapas concluídas (riscadas), uma etapa pulada, e badge de retrabalho (🔄 1) na etapa "Secagem"
- `./checklist-rework-modal.png` — Cenário 3: Modal de registro de retrabalho com campo de motivo preenchido ("Pelo ainda úmido nas patas traseiras, precisou secar novamente")

### 🏥 Check-in / Check-out
- `./checkin-awaiting-safety-items.png` — Cenário 4: Formulário de check-in com 7 itens de segurança (3 marcados: feridinhas, alergia, nós), campo de notas por item e observações gerais preenchidas
- `./checkin-done-awaiting-checkout.png` — Cenário 5: Check-in realizado (09:30), resumo de alertas de segurança (tags coloridas por severidade), formulário de check-out disponível
- `./checkin-checkout-complete.png` — Cenário 6: Ciclo completo com check-in (09:30), check-out (11:15), duração calculada (105 min) e resumo de alertas

### 🗂️ Indicadores Compactos (Cards de Agendamento)
- `./compact-card-in-progress.png` — Cenário 7: Card do Rex com checklist 67%, badge de retrabalho, check-in feito (📥) e alertas de segurança inline
- `./compact-card-complete.png` — Cenário 8: Card da Luna com checklist 100% e check-out concluído (✅)
- `./compact-card-awaiting.png` — Cenário 9: Card da Mimi com checklist 0% e sem check-in (⬜)

### Preview interativo
- `./checklist-checkin-preview.html` — Preview HTML completo com todos os cenários (abra no navegador)

## Observações
- Screenshots capturados via Playwright headless (Chromium) com viewport desktop 1280px
- CSS renderizado com fallback de design tokens (valores default inline no CSS), o que pode causar pequenas diferenças visuais em relação ao ambiente real com WordPress + dps-design-tokens.css carregado
- As telas de preview HTML são estáticas e não incluem interatividade JavaScript (AJAX para check-in/checklist não funciona no preview)
- Em ambiente WordPress real, os painéis aparecem dentro dos cards de agendamento na página da agenda
