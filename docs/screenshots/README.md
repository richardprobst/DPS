# Screenshots do DPS

Este diretÃ³rio centraliza registros visuais padronizados do sistema DPS.

## Ãndice

- **Agenda**
  - [Rebranding da Agenda (2026)](AGENDA_REBRANDING_SCREENSHOTS.md)
- **Cadastro**
  - [Rebranding do FormulÃ¡rio de Cadastro (2026)](REGISTRATION_REBRANDING_SCREENSHOTS.md)
- **Portal do Cliente**
  - [Rebranding do Portal do Cliente (2026)](CLIENT_PORTAL_REBRANDING_SCREENSHOTS.md)
- **Capturas por data**
  - [2026-02-11 â€” Agenda, Cadastro e Agendamento](2026-02-11/SCREENSHOTS_2026-02-11.md)
  - [2026-02-12 â€” Frontend V2 Completo (Registration V2 + Booking V2 Wizard + Admin)](2026-02-12/SCREENSHOTS_2026-02-12.md)
  - [2026-02-12 â€” Checklist Operacional & Check-in/Check-out (Agenda v1.2.0)](2026-02-12/SCREENSHOTS_2026-02-12_checklist-checkin.md)
  - [2026-02-15 â€” Agenda (Check-in / Check-out responsivo)](2026-02-15/SCREENSHOTS_2026-02-15.md)
  - [2026-02-16 â€” Booking add-on (revisÃ£o UX/UI)](2026-02-16/SCREENSHOTS_2026-02-16.md)
  - [2026-02-17 â€” Agenda add-on (revisÃ£o UX/UI)](2026-02-17/SCREENSHOTS_2026-02-17.md)
  - [2026-03-09 - Portal do Cliente e Agenda](2026-03-09/SCREENSHOTS_2026-03-09.md)
  - [2026-03-10 - Agenda (remocao de blocos contextuais)](2026-03-10/SCREENSHOTS_2026-03-10.md)
  - [2026-03-11 - Agenda (abas junto da lista de atendimentos)](2026-03-11/SCREENSHOTS_2026-03-11.md)
  - [2026-03-11 - Agenda (bloco Visao operacional)](2026-03-11/SCREENSHOTS_2026-03-11_visao-operacional.md)
  - [2026-03-11 - Agenda (padronizacao DPS Signature das abas)](2026-03-11/SCREENSHOTS_2026-03-11_agenda-signature-standardization.md)
  - [2026-03-12 - Agenda (cabecalho minimalista com periodo ativo integrado)](2026-03-12/SCREENSHOTS_2026-03-12.md)
  - [2026-03-13 - Agenda (cabecalho centralizado em tablet/mobile + fundo wave)](2026-03-13/SCREENSHOTS_2026-03-13.md)
  - [2026-03-21 - Agenda (auditoria UX/UI por fases)](2026-03-21/SCREENSHOTS_2026-03-21.md)
  - [2026-03-23 - Agenda (Lista de Atendimentos redesign DPS Signature)](2026-03-23/SCREENSHOTS_2026-03-23.md)
  - [2026-04-23 - Agenda (views, filtros, layout regional e estados vazios)](2026-04-23/SCREENSHOTS_2026-04-23.md)
  - [2026-04-24 - Agenda (card operacional simplificado)](2026-04-24/SCREENSHOTS_2026-04-24.md)
  - [2026-03-08 - Space Groomers Fase 3 e resumo sincronizado no portal](2026-03-08/SCREENSHOTS_2026-03-08.md)

## Regras obrigatÃ³rias para novas mudanÃ§as visuais

Para qualquer ajuste visual/layout/frontend no projeto:
- criar um registro documental da mudanÃ§a (objetivo, antes/depois, telas afetadas e arquivos alterados);
- salvar os arquivos em subpasta por data no padrÃ£o `docs/screenshots/YYYY-MM-DD/`;
- incluir prints **completos** (full page) das telas alteradas;
- opcionalmente adicionar capturas por viewport, alÃ©m da captura completa;
- referenciar no PR/resposta final o caminho exato dos arquivos salvos.

## PadrÃ£o de registro

Cada pÃ¡gina de captura deve conter:
- Contexto (tela, objetivo, versÃ£o)
- Data e ambiente
- Viewports utilizados (Desktop: 1440Ã—900, Tablet: 1024Ã—768, Mobile: 375Ã—812)
- Lista das imagens com legenda
- ObservaÃ§Ãµes relevantes (ex.: limitaÃ§Ãµes do ambiente)

### Estrutura de arquivos

```
docs/screenshots/
â”œâ”€â”€ README.md                           # Este Ã­ndice
â”œâ”€â”€ YYYY-MM-DD/                         # Registros organizados por data
â”‚   â”œâ”€â”€ SCREENSHOTS_YYYY-MM-DD.md       # Documento do dia (contexto + antes/depois)
â”‚   â”œâ”€â”€ <tela>-desktop-fullpage.png     # Captura completa desktop
â”‚   â”œâ”€â”€ <tela>-tablet-fullpage.png      # Captura completa tablet (quando aplicÃ¡vel)
â”‚   â””â”€â”€ <tela>-mobile-fullpage.png      # Captura completa mobile (quando aplicÃ¡vel)
â”œâ”€â”€ <FEATURE>_SCREENSHOTS.md            # DocumentaÃ§Ã£o temÃ¡tica consolidada (opcional)
â””â”€â”€ assets/                             # Arquivos legados ou comparativos por feature
```

### ConvenÃ§Ãµes de nomenclatura
- Screenshots em PNG com nomes descritivos: `<tela>-<viewport>-fullpage.png` para capturas completas
- Demos HTML referenciam CSS via caminhos relativos ao repositÃ³rio
- As imagens devem ser PNGs reais (nÃ£o placeholders de texto)

### Template recomendado para `SCREENSHOTS_YYYY-MM-DD.md`

```md
# Screenshots YYYY-MM-DD â€” <feature/tela>

## Contexto
- Objetivo da mudanÃ§a:
- Ambiente:
- ReferÃªncia de design DPS Signature utilizada:

## Antes/Depois
- Resumo do antes:
- Resumo do depois:
- Arquivos de cÃ³digo alterados:

## Capturas
- `./<tela>-desktop-fullpage.png`
- `./<tela>-tablet-fullpage.png` (se aplicÃ¡vel)
- `./<tela>-mobile-fullpage.png` (se aplicÃ¡vel)

## ObservaÃ§Ãµes
- LimitaÃ§Ãµes de ambiente (se houver)
```
