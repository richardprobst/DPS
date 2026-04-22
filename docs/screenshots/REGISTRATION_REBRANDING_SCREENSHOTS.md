# Rebranding do FormulÃ¡rio de Cadastro â€” Registro Visual

## Contexto
- **Tela:** FormulÃ¡rio de cadastro pÃºblico (shortcode `dps_registration_form`)
- **Objetivo:** Registrar o novo visual alinhado Ã  identidade DPS Signature do DPS, eliminando estilos inline com cores hex hardcoded.
- **Data:** 2026-02-09
- **Fonte:** `plugins/desi-pet-shower-registration/assets/css/registration-addon.css`

## MudanÃ§as realizadas
- SubstituiÃ§Ã£o de todas as cores hex hardcoded por tokens DPS Signature (`var(--dps-*)`)
- CriaÃ§Ã£o de classes CSS semÃ¢nticas: `.dps-reg-success`, `.dps-reg-message--error/--success`, `.dps-admin-options__title`
- RemoÃ§Ã£o de bloco `<style>` inline redundante (jÃ¡ coberto por regras CSS grid)
- RemoÃ§Ã£o de estilos inline em pet fieldsets (`style="border:1px solid #ddd"`)
- Mensagens de feedback (fallback) migradas de inline para classes CSS
- Alinhamento com `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `VISUAL_STYLE_GUIDE.md`:
  - `font-weight`: 700/600 â†’ 500 (DPS Signature permite apenas 400 e 500)
  - Font-sizes base: px â†’ `var(--dps-typescale-*)`
  - EspaÃ§amentos: px â†’ `var(--dps-space-*)`
  - Alertas: `border: 2px + border-left: 5px` â†’ `border-left: 3px solid` (padrÃ£o `.dps-alert`)
  - RemoÃ§Ã£o de `letter-spacing` negativo (nÃ£o DPS Signature)
  - Estilos inline PHP (`style="flex:1 1 100%"`) â†’ classes CSS (`dps-field-full`)

## Viewports
- Desktop: 1440Ã—900
- Tablet: 1024Ã—768
- Mobile: 375Ã—812

## Telas capturadas

Todas as telas do formulÃ¡rio de cadastro pÃºblico (shortcode `dps_registration_form`):

1. **Estado de Sucesso** â€” Cadastro realizado com sucesso + CTA "Agendar"
2. **Email Confirmado** â€” ConfirmaÃ§Ã£o via link de email
3. **Passo 1 â€” Dados do Cliente** â€” Campos do tutor (nome, CPF, telefone, email, etc.) + opÃ§Ãµes administrativas
4. **Passo 2 â€” Dados dos Pets** â€” Fieldsets de pets com grid 2 colunas + botÃ£o "Adicionar outro pet"
5. **Passo 3 â€” PreferÃªncias e Resumo** â€” PreferÃªncias de produtos por pet + resumo completo antes do envio
6. **Mensagens de Feedback** â€” Alertas de erro e sucesso (DPS Signature border-left pattern)

## Capturas

### Desktop (1440Ã—900) â€” Todas as telas
![Registration rebranding desktop](assets/registration-rebranding/registration-desktop.png)

### Tablet (1024Ã—768) â€” Layout responsivo
![Registration rebranding tablet](assets/registration-rebranding/registration-tablet.png)

### Mobile (375Ã—812) â€” Layout mobile-first
![Registration rebranding mobile](assets/registration-rebranding/registration-mobile.png)

## ObservaÃ§Ãµes
- Capturas geradas a partir do arquivo de demo em `docs/screenshots/registration-rebranding.html` com os estilos oficiais do add-on (`registration-addon.css` + `dps-design-tokens.css`).
- O formulÃ¡rio jÃ¡ possuÃ­a CSS DPS Signature maduro (1900+ linhas). O rebranding focou em: (1) eliminar estilos inline hardcoded no PHP, e (2) alinhar com as orientaÃ§Ãµes de `docs/visual/`.
- Templates de email mantÃªm estilos inline intencionalmente (necessÃ¡rio para compatibilidade com clientes de email).
- PÃ¡gina de configuraÃ§Ãµes admin (escopo fora do shortcode pÃºblico) mantÃ©m estilos WP admin padrÃ£o.
