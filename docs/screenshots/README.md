# Screenshots do DPS

Este diretorio centraliza registros visuais padronizados do sistema DPS.

## Indice

- **Agenda**
  - [Rebranding da Agenda (2026)](AGENDA_REBRANDING_SCREENSHOTS.md)
- **Cadastro**
  - [Rebranding do Formulario de Cadastro (2026)](REGISTRATION_REBRANDING_SCREENSHOTS.md)
- **Portal do Cliente**
  - [Rebranding do Portal do Cliente (2026)](CLIENT_PORTAL_REBRANDING_SCREENSHOTS.md)
- **Capturas por data**
  - [2026-02-11 - Agenda, Cadastro e Agendamento](2026-02-11/SCREENSHOTS_2026-02-11.md)
  - [2026-02-12 - Frontend V2 completo (Registration V2 + Booking V2 Wizard + Admin)](2026-02-12/SCREENSHOTS_2026-02-12.md)
  - [2026-02-12 - Checklist operacional e check-in/check-out (Agenda v1.2.0)](2026-02-12/SCREENSHOTS_2026-02-12_checklist-checkin.md)
  - [2026-02-15 - Agenda (check-in / check-out responsivo)](2026-02-15/SCREENSHOTS_2026-02-15.md)
  - [2026-02-16 - Booking add-on (revisao UX/UI)](2026-02-16/SCREENSHOTS_2026-02-16.md)
  - [2026-02-17 - Agenda add-on (revisao UX/UI)](2026-02-17/SCREENSHOTS_2026-02-17.md)
  - [2026-03-08 - Space Groomers fase 3 e resumo sincronizado no portal](2026-03-08/SCREENSHOTS_2026-03-08.md)
  - [2026-03-09 - Portal do Cliente e Agenda](2026-03-09/SCREENSHOTS_2026-03-09.md)
  - [2026-03-10 - Agenda (remocao de blocos contextuais)](2026-03-10/SCREENSHOTS_2026-03-10.md)
  - [2026-03-11 - Agenda (abas junto da lista de atendimentos)](2026-03-11/SCREENSHOTS_2026-03-11.md)
  - [2026-03-11 - Agenda (bloco Visao operacional)](2026-03-11/SCREENSHOTS_2026-03-11_visao-operacional.md)
  - [2026-03-11 - Agenda (padronizacao visual das abas)](2026-03-11/SCREENSHOTS_2026-03-11_agenda-m3-standardization.md)
  - [2026-03-12 - Agenda (cabecalho minimalista com periodo ativo integrado)](2026-03-12/SCREENSHOTS_2026-03-12.md)
  - [2026-03-13 - Agenda (cabecalho centralizado em tablet/mobile + fundo wave)](2026-03-13/SCREENSHOTS_2026-03-13.md)
  - [2026-03-21 - Agenda (auditoria UX/UI por fases)](2026-03-21/SCREENSHOTS_2026-03-21.md)
  - [2026-03-23 - Agenda (redesign visual da lista de atendimentos)](2026-03-23/SCREENSHOTS_2026-03-23.md)
  - [2026-03-31 - Paginas publicas do site (WordPress + Flatsome)](2026-03-31/SCREENSHOTS_2026-03-31.md)
  - [2026-04-16 - Paginas publicas do site (revisao integral de UI, UX e responsividade)](2026-04-16/SCREENSHOTS_2026-04-16.md)
  - [2026-04-17 - Migracao integral do sistema visual para DPS Signature](2026-04-17/SCREENSHOTS_2026-04-17.md)
  - [2026-04-17 - Template editorial para materias informativas](2026-04-17/SCREENSHOTS_2026-04-17_materia-breaking-news.md)
  - [2026-04-17 - Materia piloto: Cinco sinais de que a rotina de banho do seu pet pode pedir um ajuste](2026-04-17/SCREENSHOTS_2026-04-17_materia-piloto.md)
  - [2026-04-17 - Materia: Carrapatos e pulgas no cachorro](2026-04-17/SCREENSHOTS_2026-04-17_materia-carrapatos-pulgas.md)

## Regras obrigatorias para novas mudancas visuais

Para qualquer ajuste visual/layout/frontend no projeto:
- criar um registro documental da mudanca com objetivo, antes/depois, telas afetadas e arquivos alterados;
- salvar os arquivos em subpasta por data no padrao `docs/screenshots/YYYY-MM-DD/`;
- incluir prints completos (`full page`) das telas alteradas quando houver mudanca renderizada de interface;
- opcionalmente adicionar capturas por viewport, alem da captura completa;
- referenciar no PR/resposta final o caminho exato dos arquivos salvos.

## Padrao de registro

Cada pagina de captura deve conter:
- contexto da tela e objetivo da iteracao;
- data e ambiente;
- viewports utilizados;
- lista das imagens com legenda;
- observacoes relevantes e limitacoes do ambiente.

### Estrutura de arquivos

```text
docs/screenshots/
|-- README.md
|-- YYYY-MM-DD/
|   |-- SCREENSHOTS_YYYY-MM-DD.md
|   |-- <tela>-desktop-fullpage.png
|   |-- <tela>-tablet-fullpage.png
|   `-- <tela>-mobile-fullpage.png
|-- <FEATURE>_SCREENSHOTS.md
`-- assets/
```

### Convencoes de nomenclatura

- screenshots em PNG com nomes descritivos: `<tela>-<viewport>-fullpage.png`;
- demos HTML referenciam CSS via caminhos relativos ao repositorio;
- as imagens devem ser PNGs reais, nunca placeholders de texto.

### Template recomendado para `SCREENSHOTS_YYYY-MM-DD.md`

```md
# Screenshots YYYY-MM-DD - <feature/tela>

## Contexto
- Objetivo da mudanca:
- Ambiente:
- Referencia de design DPS Signature utilizada:

## Antes/Depois
- Resumo do antes:
- Resumo do depois:
- Arquivos de codigo alterados:

## Capturas
- `./<tela>-desktop-fullpage.png`
- `./<tela>-tablet-fullpage.png` (se aplicavel)
- `./<tela>-mobile-fullpage.png` (se aplicavel)

## Observacoes
- Limitacoes de ambiente (se houver)
```

## Nota historica

Alguns artefatos antigos podem manter nomes de arquivo herdados de iteracoes anteriores. O sistema visual ativo e a unica fonte de verdade para novas interfaces e `DPS Signature`, definido em `docs/visual/`.
