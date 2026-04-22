# Registro de Rebranding â€” DPS Signature

Registro das migraÃ§Ãµes visuais relevantes do DPS para o padrÃ£o **DPS Signature**.

## Status

Este arquivo usa a nomenclatura atual e substitui o registro visual anterior.

Regra de leitura:

- entradas antigas podem ter nascido durante a fase de transiÃ§Ã£o;
- a interpretaÃ§Ã£o correta hoje Ã© sempre **DPS Signature**;
- nenhuma entrada deste arquivo deve ser usada para reativar orientaÃ§Ã£o visual antiga.

---

## Componentes registrados

| # | Componente | Plugin | Arquivo(s) | Estado | Data | Nota atual |
|---|-----------|--------|-----------|--------|------|------------|
| 1 | AI Agent do Portal | `desi-pet-shower-ai` | `assets/css/dps-ai-portal.css`, `includes/class-dps-ai-integration-portal.php`, `assets/js/dps-ai-portal.js` | HistÃ³rico | 2026-02-09 | MigraÃ§Ã£o inicial do shell visual. Deve ser interpretado hoje como fase preliminar do DPS Signature. |
| 2 | Aba Inicial do Portal | `desi-pet-shower-client-portal` | `assets/css/client-portal.css`, `includes/class-dps-client-portal.php` | HistÃ³rico | 2026-02-10 | ReestruturaÃ§Ã£o do layout principal do portal. Hoje deve convergir para DPS Signature sem resquÃ­cios terminolÃ³gicos antigos. |
| 3 | Agenda operacional | `desi-pet-shower-agenda` | `assets/css/agenda-addon.css`, `assets/css/checklist-checkin.css`, `assets/css/dashboard.css`, `assets/css/agenda-admin.css` | Ativo | 2026-04-20 a 2026-04-21 | Reescrita do shell operacional com linguagem reta, contraste controlado e foco em clareza operacional. |
| 4 | Cadastro e formulÃ¡rios compartilhados | `desi-pet-shower-base`, `desi-pet-shower-frontend`, `desi-pet-shower-registration` | `assets/css/dps-signature-forms.css` e mÃ³dulos relacionados | Ativo | 2026-04-21 | ConsolidaÃ§Ã£o do motor visual compartilhado dos fluxos de cadastro, portal e formulÃ¡rios internos. |
| 5 | PÃ¡ginas pÃºblicas institucionais | repositÃ³rio de pÃ¡ginas HTML | `docs/screenshots/2026-04-21/` e diretÃ³rios de pÃ¡ginas | Ativo | 2026-04-21 | ConsolidaÃ§Ã£o de linguagem editorial/comercial com identidade prÃ³pria, premium, moderna e minimalista. |

---

## CritÃ©rios de migraÃ§Ã£o considerados vÃ¡lidos

Uma migraÃ§Ã£o sÃ³ pode ser considerada alinhada ao DPS Signature se cumprir os pontos abaixo:

- geometria predominantemente reta;
- paleta sÃ³bria;
- CTA principal claramente visÃ­vel;
- tipografia moderna e limpa;
- reduÃ§Ã£o de ruÃ­do visual;
- melhor distribuiÃ§Ã£o espacial;
- linguagem coerente entre desktop e mobile;
- abandono explÃ­cito da orientaÃ§Ã£o antiga.

---

## O que nÃ£o registrar como â€œrebranding concluÃ­doâ€

- simples troca de cores sem rever hierarquia;
- aumento de arredondamento;
- reuso de shell antigo com nova nomenclatura;
- telas ainda dependentes de linguagem visual herdada;
- mudanÃ§as sem validaÃ§Ã£o visual real.

---

## ObservaÃ§Ã£o operacional

Se uma nova entrega visual for feita:

1. seguir `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md`;
2. seguir `docs/visual/VISUAL_STYLE_GUIDE.md`;
3. registrar evidÃªncias em `docs/screenshots/YYYY-MM-DD/`;
4. usar exclusivamente a nomenclatura **DPS Signature**.
