# Screenshots 2026-03-09 - Portal do Cliente (login hibrido)

## Contexto
- Objetivo da mudanca: reestruturar o acesso do Portal do Cliente com magic link + e-mail e senha, revisar o admin de logins e atualizar a tela inicial publica.
- Ambiente: previews estaticos locais, sem WordPress ativo nem banco de dados.
- Referencia de design M3 utilizada: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.

## Antes/Depois
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

## Capturas
- `./portal-access-desktop-fullpage.png`
- `./portal-access-mobile-fullpage.png`
- `./portal-password-reset-desktop-fullpage.png`
- `./portal-password-reset-mobile-fullpage.png`
- `./portal-admin-logins-desktop-fullpage.png`
- `./portal-admin-logins-mobile-fullpage.png`
- `./portal-settings-desktop-fullpage.png`
- `./portal-settings-mobile-fullpage.png`

## Observacoes
- As capturas foram geradas a partir de previews estaticos para validar layout, hierarquia visual e responsividade sem depender de uma instancia WordPress local.
- O CSS inclui ajustes dedicados para 375px, 600px, 840px e 1200px. O registro visual salvo cobre 375px e 1440px; nao houve captura dedicada em 1920px neste ambiente.
- Nao foi possivel criar um usuario WordPress real de teste neste workspace porque nao existe `wp-config.php`, banco de dados nem `wp-cli` conectados a uma instalacao ativa.
