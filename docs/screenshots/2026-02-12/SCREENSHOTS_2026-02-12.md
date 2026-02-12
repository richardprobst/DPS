# Screenshots 2026-02-12 — Frontend Add-on V2 (Fase 7 Completa)

## Contexto
- **Objetivo:** Documentar visualmente todas as telas do Frontend Add-on V2 (nativos M3 Expressive) — Registration V2, Booking V2 (wizard 5 steps), telas de sucesso, login obrigatório, aviso de depreciação V1 e status de coexistência admin.
- **Ambiente:** Preview HTML estático com CSS do design system M3 (dps-design-tokens.css + registration-v2.css + booking-v2.css). Sem WordPress runtime.
- **Referência de design M3 utilizada:** `docs/visual/VISUAL_STYLE_GUIDE.md`, `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md`
- **Versão:** Frontend Add-on v2.4.0 (Fase 7.1–7.5 completa)

## Antes/Depois
- **Antes (V1 dual-run):** Formulários legados envolvidos com wrapper M3 surface. Dependência de `DPS_Registration_Addon` e `DPS_Booking_Addon`. jQuery necessário.
- **Depois (V2 nativo):** Formulários 100% nativos M3 Expressive. Zero dependência de add-ons legados. JavaScript vanilla (zero jQuery). Wizard de 5 steps para booking. Hook Bridge garante compatibilidade com 8+ add-ons.
- **Arquivos de código alterados:**
  - `plugins/desi-pet-shower-frontend/templates/registration/` (form-main, form-client-data, form-pet-data, form-success, form-error, form-duplicate-warning)
  - `plugins/desi-pet-shower-frontend/templates/booking/` (form-main, step-client-selection, step-pet-selection, step-service-selection, step-datetime-selection, step-extras, step-confirmation, form-success, form-login-required)
  - `plugins/desi-pet-shower-frontend/assets/css/registration-v2.css`
  - `plugins/desi-pet-shower-frontend/assets/css/booking-v2.css`
  - `plugins/desi-pet-shower-frontend/assets/js/booking-v2.js`
  - `plugins/desi-pet-shower-frontend/includes/support/class-dps-frontend-deprecation-notice.php`
  - `plugins/desi-pet-shower-frontend/includes/modules/class-dps-frontend-settings-module.php`

## Capturas

### Página completa (todos os componentes)
- `./frontend-v2-desktop-fullpage.png` — Captura completa de todas as telas V2 em sequência (desktop 1280px)

### Cadastro V2 — `[dps_registration_v2]`
- `./registration-v2-form.png` — Formulário de cadastro com dados pessoais + seção de pets preenchidos

### Agendamento V2 — `[dps_booking_v2]` (Wizard 5 Steps)
- `./booking-v2-step3-services.png` — Step 3: Seleção de serviços com preços e total parcial
- `./booking-v2-step5-confirmation.png` — Step 5: Resumo completo do agendamento antes da confirmação
- `./booking-v2-success.png` — Tela de sucesso após criação do agendamento

### Administração
- `./admin-deprecation-notice.png` — Aviso de depreciação V1 exibido no painel WordPress
- `./settings-coexistence-status.png` — Seção de coexistência v1/v2 na aba Settings

### Preview interativo
- `./frontend-v2-preview.html` — Preview HTML completo com todas as telas (abra no navegador)

## Observações
- Screenshots capturados via Playwright headless (Chromium) com viewport desktop 1280px
- CSS renderizado sem fallback de design tokens (valores default inline nos CSS), o que pode causar pequenas diferenças visuais em relação ao ambiente real com WordPress + dps-design-tokens.css carregado
- As telas de preview HTML são estáticas e não incluem interatividade JavaScript (AJAX, wizard navigation, etc.)
- Em ambiente WordPress real, os formulários incluem campos de nonce, honeypot anti-spam e reCAPTCHA v3
