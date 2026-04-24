# Screenshots 2026-04-24 — Agenda

## Agenda: card operacional simplificado

Fonte de verdade visual seguida nesta correção: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md` (padrão DPS Signature).

### Objetivo

Remover do card operacional os blocos redundantes `Serviços`, `Financeiro`, `Logística` e `Checklist`, pois os dados completos ficam no inspetor lateral da Agenda. Também foi removido o botão `Operação` do rodapé do card e do menu secundário.

### Antes/Depois

- Antes: o card repetia dados já presentes na barra lateral e exibia três ações visíveis (`Cobrar cliente`, `Operação`, `Mais`).
- Depois: o card fica focado em horário, status, pet, tutor e ações principais (`Cobrar cliente`, `Mais`), mantendo os detalhes completos no inspetor lateral.

### Arquivos alterados

- `plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php`
- `plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`
- `plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js`

### Capturas

- [agenda-operational-card-simplified-375.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-operational-card-simplified-375.png)
- [agenda-operational-card-simplified-600.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-operational-card-simplified-600.png)
- [agenda-operational-card-simplified-840.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-operational-card-simplified-840.png)
- [agenda-operational-card-simplified-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-operational-card-simplified-1200.png)
- [agenda-operational-card-simplified-1920.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-operational-card-simplified-1920.png)
- [agenda-operational-card-simplified-check.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-operational-card-simplified-check.json)
- [agenda-operational-card-simplified-preview.html](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-operational-card-simplified-preview.html)

### Breakpoints validados

- `375`: sem overflow horizontal; card sem blocos redundantes; ações visíveis `COBRAR CLIENTE` e `MAIS`.
- `600`: sem overflow horizontal; card sem blocos redundantes; ações visíveis `COBRAR CLIENTE` e `MAIS`.
- `840`: sem overflow horizontal; card sem blocos redundantes; ações visíveis `COBRAR CLIENTE` e `MAIS`.
- `1200`: sem overflow horizontal; card simplificado e inspetor lateral visível com `Serviços`, `Financeiro`, `Logística` e `Checklist`.
- `1920`: sem overflow horizontal; card simplificado e inspetor lateral visível com `Serviços`, `Financeiro`, `Logística` e `Checklist`.

### Validação automática

- `cardMetaCount = 0`
- `cardProgressCount = 0`
- `cardContainsForbiddenLabels = []`
- `actionText = ["COBRAR CLIENTE", "MAIS"]`
- `consoleMessages = []`

### Publicado no servidor

- Servidor: `195.35.41.35:65002`
- Caminho publicado: `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/`
- Backups remotos:
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php.__backup_20260424-002414`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css.__backup_20260424-002414`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js.__backup_20260424-002414`
- Arquivos publicados:
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js`
- `php -l` remoto: sem erros de sintaxe.
- Hashes SHA-256 remotos conferidos contra os arquivos locais.
- `https://desi.pet/agenda-de-atendimentos/`: HTTP `200 OK`, PHP `8.4.19`, headers sem cache.
- WP-CLI remoto confirmou o add-on `desi-pet-shower-agenda` ativo.

### Observações

- A validação visual foi feita em fixture local usando os estilos reais do add-on. A rodada de publicação confirmou arquivos ativos, sintaxe PHP remota, página publicada respondendo e plugin ativo via WP-CLI.
- A busca local no add-on da Agenda não encontrou referências ativas ao padrão visual antigo.
