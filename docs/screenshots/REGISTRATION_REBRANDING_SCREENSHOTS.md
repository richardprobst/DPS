# Rebranding do Formulário de Cadastro — Registro Visual

## Contexto
- **Tela:** Formulário de cadastro público (shortcode `dps_registration_form`)
- **Objetivo:** Registrar o novo visual alinhado à identidade M3 Expressive do DPS, eliminando estilos inline com cores hex hardcoded.
- **Data:** 2026-02-09
- **Fonte:** `plugins/desi-pet-shower-registration/assets/css/registration-addon.css`

## Mudanças realizadas
- Substituição de todas as cores hex hardcoded por tokens M3 (`var(--dps-*)`)
- Criação de classes CSS semânticas: `.dps-reg-success`, `.dps-reg-message--error/--success`, `.dps-admin-options__title`
- Remoção de bloco `<style>` inline redundante (já coberto por regras CSS grid)
- Remoção de estilos inline em pet fieldsets (`style="border:1px solid #ddd"`)
- Mensagens de feedback (fallback) migradas de inline para classes CSS

## Viewports
- Desktop: 1440×900
- Tablet: 1024×768
- Mobile: 375×812

## Capturas

### Desktop — Formulário completo (todos os passos)
![Registration rebranding desktop](assets/registration-rebranding/registration-desktop.png)

### Tablet — Layout responsivo
![Registration rebranding tablet](assets/registration-rebranding/registration-tablet.png)

### Mobile — Layout mobile-first
![Registration rebranding mobile](assets/registration-rebranding/registration-mobile.png)

## Observações
- Capturas geradas a partir do arquivo de demo em `docs/screenshots/registration-rebranding.html` com os estilos oficiais do add-on.
- O formulário já possuía CSS M3 Expressive maduro (1800+ linhas). O rebranding focou em eliminar estilos inline hardcoded no PHP.
- Templates de email mantêm estilos inline intencionalmente (necessário para compatibilidade com clientes de email).
