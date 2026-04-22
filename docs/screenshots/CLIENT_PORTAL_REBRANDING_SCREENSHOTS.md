# Rebranding do Portal do Cliente â€” Registro Visual

## Contexto
- **Tela:** Portal do Cliente (tela de acesso + shell principal)
- **Objetivo:** Registrar o novo visual alinhado Ã  identidade DPS Signature do DPS, cobrindo a pÃ¡gina de acesso (login link request) e o shell do portal (breadcrumb, tabs, container).
- **Data:** 2026-02-09
- **Fonte:** `plugins/desi-pet-shower-client-portal/assets/css/client-portal.css`

## MudanÃ§as realizadas

### Tela de Acesso (Access Page)
- `font-weight: 600` â†’ `var(--dps-typescale-*-weight)` (400 para headline, 500 para labels/botÃµes)
- `#ffffff` â†’ `var(--dps-color-on-primary)` em botÃµes e elementos sobre primary
- Hardcoded `14px` â†’ `var(--dps-typescale-body-medium-size)` em mensagens de erro
- Alert pattern: `border: 1px solid + border-left: 4px` â†’ `border-left: 3px solid` (padrÃ£o DPS Signature `.dps-alert`)
- Hardcoded px spacing (`12px`, `16px`, `24px`, `28px`, `40px`) â†’ tokens `var(--dps-space-*)`

### Shell do Portal (Portal Main Page)
- TÃ­tulo H1: `font-size: 24px; font-weight: 600` â†’ `var(--dps-typescale-headline-small-size/weight)`
- Breadcrumb: `font-size: 14px` â†’ `var(--dps-typescale-body-medium-size)`, `font-weight: 600` â†’ `500`
- Review link: `font-size: 13px` â†’ `var(--dps-typescale-label-small-size)`
- Tabs: `font-size: 14px` â†’ `var(--dps-typescale-body-medium-size)`, `font-weight: 600` (active) â†’ `500`
- Tab icon: `font-size: 18px` â†’ `var(--dps-typescale-title-medium-size)`
- Badge: `font-size: 11px; font-weight: 700; #fff` â†’ `var(--dps-typescale-label-small-size); 500; var(--dps-color-on-primary)`
- Tab content padding: `24px` â†’ `var(--dps-space-6)`
- Nav link hover: `#fff` â†’ `var(--dps-color-on-primary)`
- Hardcoded px gaps/paddings â†’ `var(--dps-space-*)` tokens

### ExceÃ§Ãµes intencionais
- WhatsApp brand colors (`#25d366`, `#1fb355`) mantidos â€” nÃ£o hÃ¡ token DPS para cores de terceiros
- Logo emoji `font-size: 56px` mantido â€” nÃ£o hÃ¡ token de typescale para emojis decorativos

## Escopo
- âœ… Tela de acesso (solicitaÃ§Ã£o de link) â€” **completo**
- âœ… Shell do portal (breadcrumb, tabs, container) â€” **completo**
- âŒ ConteÃºdo interno das abas â€” **fora de escopo** (serÃ¡ feito em passes separados)

## Viewports
- Desktop: 1440Ã—900

## Telas capturadas

| PÃ¡gina/Ãrea | DescriÃ§Ã£o | Data | Imagem | Status | Notas |
|---|---|---|---|---|---|
| Tela de Acesso (padrÃ£o) | FormulÃ¡rio de solicitaÃ§Ã£o de link por email + WhatsApp | 2026-02-09 | [Screenshot](assets/client-portal-rebranding/client-portal-desktop.png) | Completo | Todos tokens DPS Signature, zero hex hardcoded (exceto WhatsApp brand) |
| Tela de Acesso (erro + feedback) | Token expirado + feedback sucesso/erro | 2026-02-09 | [Screenshot](assets/client-portal-rebranding/client-portal-desktop.png) | Completo | Alert DPS Signature border-left:3px pattern |
| Portal Shell (breadcrumb + tabs) | NavegaÃ§Ã£o principal com badges | 2026-02-09 | [Screenshot](assets/client-portal-rebranding/client-portal-desktop.png) | Completo | Tabs com typescale tokens, badge weight 500 |

## Capturas

### Desktop (1440Ã—900) â€” Todas as telas
![Client Portal rebranding desktop](assets/client-portal-rebranding/client-portal-desktop.png)

## ObservaÃ§Ãµes
- Capturas geradas a partir do arquivo de demo em `docs/screenshots/client-portal-rebranding.html` com os estilos oficiais do add-on (`client-portal.css` + `dps-design-tokens.css`).
- O CSS do portal jÃ¡ possuÃ­a mapeamento de tokens DPS Signature semÃ¢nticos (variÃ¡veis intermediÃ¡rias como `--dps-gray-*`, `--dps-primary`). O rebranding focou em: (1) eliminar `font-weight: 600/700` em favor de tokens weight, (2) substituir hex hardcoded por tokens de cor, (3) substituir px hardcoded por tokens de espaÃ§amento/tipografia, e (4) corrigir o padrÃ£o de alerta DPS Signature.
- ConteÃºdo interno das abas mantÃ©m estilos anteriores â€” serÃ¡ rebranded em passes individuais por aba.
