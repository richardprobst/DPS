# Screenshots 2026-03-09 - Portal do Cliente e Agenda

## Portal do Cliente - login hibrido

### Contexto
- Objetivo da mudanca: reestruturar o acesso do Portal do Cliente com magic link + e-mail e senha, revisar o admin de logins e atualizar a tela inicial publica.
- Ambiente: previews estaticos locais, sem WordPress ativo nem banco de dados.
- Referencia de design M3 utilizada: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.

### Antes/Depois
- Resumo do antes: portal com foco em magic link, tela inicial antiga e admin de logins centrado em tokens.
- Resumo do depois: landing publica refeita com dois caminhos de acesso, reset de senha no proprio portal e admin com status de magic link, senha, sincronizacao e historico recente.
- Arquivos de codigo alterados:
  - `plugins/desi-pet-shower-client-portal/includes/class-dps-client-portal.php`
  - `plugins/desi-pet-shower-client-portal/includes/class-dps-portal-user-manager.php`
  - `plugins/desi-pet-shower-client-portal/includes/class-dps-portal-rate-limiter.php`
  - `plugins/desi-pet-shower-client-portal/includes/class-dps-portal-admin-actions.php`
  - `plugins/desi-pet-shower-client-portal/includes/client-portal/class-dps-portal-admin.php`
  - `plugins/desi-pet-shower-client-portal/includes/client-portal/class-dps-portal-ajax-handler.php`
  - `plugins/desi-pet-shower-client-portal/templates/portal-access.php`
  - `plugins/desi-pet-shower-client-portal/templates/portal-password-reset.php`
  - `plugins/desi-pet-shower-client-portal/templates/admin-logins.php`
  - `plugins/desi-pet-shower-client-portal/templates/portal-settings.php`
  - `plugins/desi-pet-shower-client-portal/assets/js/client-portal.js`
  - `plugins/desi-pet-shower-client-portal/assets/js/portal-admin.js`
  - `plugins/desi-pet-shower-client-portal/assets/css/client-portal.css`
  - `plugins/desi-pet-shower-client-portal/assets/css/portal-admin.css`

### Capturas
- `./portal-access-desktop-fullpage.png`
- `./portal-access-mobile-fullpage.png`
- `./portal-password-reset-desktop-fullpage.png`
- `./portal-password-reset-mobile-fullpage.png`
- `./portal-admin-logins-desktop-fullpage.png`
- `./portal-admin-logins-mobile-fullpage.png`
- `./portal-settings-desktop-fullpage.png`
- `./portal-settings-mobile-fullpage.png`

### Observacoes
- As capturas foram geradas a partir de previews estaticos para validar layout, hierarquia visual e responsividade sem depender de uma instancia WordPress local.
- O CSS inclui ajustes dedicados para 375px, 600px, 840px e 1200px. O registro visual salvo cobre 375px e 1440px; nao houve captura dedicada em 1920px neste ambiente.
- Nao foi possivel criar um usuario WordPress real de teste neste workspace porque nao existe `wp-config.php`, banco de dados nem `wp-cli` conectados a uma instalacao ativa.

## Agenda add-on - recriacao UX/UI operacional

### Contexto
- Objetivo da mudanca: revisar profundamente a UX/UI da Agenda, recriar o shell operacional principal e corrigir inconsistencias de estado, navegacao e paginacao.
- Ambiente: preview HTML local com os estilos oficiais do add-on.
- Referencia de design M3 utilizada: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.

### Antes/Depois
- Resumo do antes: topo com pouca hierarquia, filtros dispersos, leitura operacional concentrada apenas nas tabelas, empty state pouco orientado e perda de contexto em parte da navegacao.
- Resumo do depois: header contextual, CTAs claros, painel unico de filtros, chips com filtros ativos, overview cards, paineis por dia e estado vazio com recuperacao objetiva.
- Arquivos de codigo alterados:
  - `plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php`
  - `plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php`
  - `plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js`
  - `plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`

### Breakpoints validados
- 375px
- 600px
- 840px
- 1200px
- 1920px

### Capturas
- `./agenda-refresh-mobile-375-fullpage.png`
- `./agenda-refresh-mobile-600-fullpage.png`
- `./agenda-refresh-tablet-840-fullpage.png`
- `./agenda-refresh-desktop-1200-fullpage.png`
- `./agenda-refresh-wide-1920-fullpage.png`
- `./agenda-ux-refresh-preview.html`

### Observacoes
- As capturas foram geradas a partir de um preview estatico para registrar a camada visual e responsiva usando o CSS real do add-on.
- A validacao funcional completa em um WordPress local nao foi executada neste ambiente.
## Portal do Cliente - home autenticada refresh

### Contexto
- Objetivo da mudanca: revisar a home autenticada do Portal do Cliente, reorganizar a leitura inicial, corrigir a navegacao contextual e destacar agenda, mensagens, pagamentos e fidelidade logo na entrada.
- Ambiente: preview HTML local com o CSS real do add-on, sem WordPress ativo nem banco de dados.
- Referencia de design M3 utilizada: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.

### Antes/Depois
- Resumo do antes: aba Inicio concentrava cards e secoes importantes sem hierarquia suficiente, badges eram montados por chamadas dispersas e os atalhos dependiam de tabs hardcoded no JS.
- Resumo do depois: a home ganhou hero contextual, overview acionavel, atalhos mais orientados a tarefa, badges centralizados em snapshot unico e CTA de WhatsApp sem fallback inseguro.
- Arquivos de codigo alterados:
  - `plugins/desi-pet-shower-client-portal/includes/class-dps-client-portal.php`
  - `plugins/desi-pet-shower-client-portal/includes/client-portal/repositories/class-dps-appointment-repository.php`
  - `plugins/desi-pet-shower-client-portal/assets/js/client-portal.js`
  - `plugins/desi-pet-shower-client-portal/assets/css/client-portal.css`
  - `docs/screenshots/2026-03-09/client-portal-home-refresh-preview.html`

### Breakpoints validados
- 375px
- 600px
- 840px
- 1200px
- 1920px

### Capturas
- `./portal-home-refresh-mobile-375-fullpage.png`
- `./portal-home-refresh-mobile-600-fullpage.png`
- `./portal-home-refresh-tablet-840-fullpage.png`
- `./portal-home-refresh-desktop-1200-fullpage.png`
- `./portal-home-refresh-wide-1920-fullpage.png`
- `./client-portal-home-refresh-preview.html`

### Observacoes
- A validacao visual foi feita sobre um preview estatico para exercitar o CSS oficial e registrar a nova composicao da home sem depender de uma instalacao WordPress ativa.
- A validacao funcional no ambiente real continua limitada pela ausencia de `wp-config.php`, banco e sessao autenticada do portal neste workspace.