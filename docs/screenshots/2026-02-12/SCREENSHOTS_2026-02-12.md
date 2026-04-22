# Screenshots 2026-02-12 â€” Frontend Add-on V2 (Fase 7 Completa)

## Contexto
- **Objetivo:** Documentar visualmente todas as telas do Frontend Add-on V2 (nativos DPS Signature) â€” Registration V2, Booking V2 (wizard 5 steps), telas de sucesso, login obrigatÃ³rio, aviso de depreciaÃ§Ã£o V1 e status de coexistÃªncia admin.
- **Ambiente:** Preview HTML estÃ¡tico com CSS do design system DPS Signature (dps-design-tokens.css + registration-v2.css + booking-v2.css). Sem WordPress runtime.
- **ReferÃªncia de design DPS Signature utilizada:** `docs/visual/VISUAL_STYLE_GUIDE.md`, `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md`
- **VersÃ£o:** Frontend Add-on v2.4.0 (Fase 7.1â€“7.5 completa)

## Antes/Depois
- **Antes (V1 dual-run):** FormulÃ¡rios legados envolvidos com wrapper DPS Signature surface. DependÃªncia de `DPS_Registration_Addon` e `DPS_Booking_Addon`. jQuery necessÃ¡rio.
- **Depois (V2 nativo):** FormulÃ¡rios 100% nativos DPS Signature. Zero dependÃªncia de add-ons legados. JavaScript vanilla (zero jQuery). Wizard de 5 steps para booking. Hook Bridge garante compatibilidade com 8+ add-ons.
- **Arquivos de cÃ³digo alterados:**
  - `plugins/desi-pet-shower-frontend/templates/registration/` (form-main, form-client-data, form-pet-data, form-success, form-error, form-duplicate-warning)
  - `plugins/desi-pet-shower-frontend/templates/booking/` (form-main, step-client-selection, step-pet-selection, step-service-selection, step-datetime-selection, step-extras, step-confirmation, form-success, form-login-required)
  - `plugins/desi-pet-shower-frontend/assets/css/registration-v2.css`
  - `plugins/desi-pet-shower-frontend/assets/css/booking-v2.css`
  - `plugins/desi-pet-shower-frontend/assets/js/booking-v2.js`
  - `plugins/desi-pet-shower-frontend/includes/support/class-dps-frontend-deprecation-notice.php`
  - `plugins/desi-pet-shower-frontend/includes/modules/class-dps-frontend-settings-module.php`

## Capturas

### PÃ¡gina completa (todos os componentes)
- `./frontend-v2-desktop-fullpage.png` â€” Captura completa de todas as telas V2 em sequÃªncia (desktop 1280px)

### Cadastro V2 â€” `[dps_registration_v2]`
- `./registration-v2-form.png` â€” FormulÃ¡rio de cadastro com dados pessoais + seÃ§Ã£o de pets preenchidos

### Agendamento V2 â€” `[dps_booking_v2]` (Wizard 5 Steps)
- `./booking-v2-step3-services.png` â€” Step 3: SeleÃ§Ã£o de serviÃ§os com preÃ§os e total parcial
- `./booking-v2-step5-confirmation.png` â€” Step 5: Resumo completo do agendamento antes da confirmaÃ§Ã£o
- `./booking-v2-success.png` â€” Tela de sucesso apÃ³s criaÃ§Ã£o do agendamento

### AdministraÃ§Ã£o
- `./admin-deprecation-notice.png` â€” Aviso de depreciaÃ§Ã£o V1 exibido no painel WordPress
- `./settings-coexistence-status.png` â€” SeÃ§Ã£o de coexistÃªncia v1/v2 na aba Settings

### Preview interativo
- `./frontend-v2-preview.html` â€” Preview HTML completo com todas as telas (abra no navegador)

## ObservaÃ§Ãµes
- Screenshots capturados via Playwright headless (Chromium) com viewport desktop 1280px
- CSS renderizado sem fallback de design tokens (valores default inline nos CSS), o que pode causar pequenas diferenÃ§as visuais em relaÃ§Ã£o ao ambiente real com WordPress + dps-design-tokens.css carregado
- As telas de preview HTML sÃ£o estÃ¡ticas e nÃ£o incluem interatividade JavaScript (AJAX, wizard navigation, etc.)
- Em ambiente WordPress real, os formulÃ¡rios incluem campos de nonce, honeypot anti-spam e reCAPTCHA v3
