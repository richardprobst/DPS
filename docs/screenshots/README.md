# Screenshots do DPS

Este diretório centraliza registros visuais padronizados do sistema DPS.

## Índice

- **Agenda**
  - [Rebranding da Agenda (2026)](AGENDA_REBRANDING_SCREENSHOTS.md)
- **Cadastro**
  - [Rebranding do Formulário de Cadastro (2026)](REGISTRATION_REBRANDING_SCREENSHOTS.md)
- **Portal do Cliente**
  - [Rebranding do Portal do Cliente (2026)](CLIENT_PORTAL_REBRANDING_SCREENSHOTS.md)
- **Capturas por data**
  - [2026-02-11 — Agenda, Cadastro e Agendamento](2026-02-11/SCREENSHOTS_2026-02-11.md)
  - [2026-02-12 — Frontend V2 Completo (Registration V2 + Booking V2 Wizard + Admin)](2026-02-12/SCREENSHOTS_2026-02-12.md)
  - [2026-02-12 — Checklist Operacional & Check-in/Check-out (Agenda v1.2.0)](2026-02-12/SCREENSHOTS_2026-02-12_checklist-checkin.md)

## Regras obrigatórias para novas mudanças visuais

Para qualquer ajuste visual/layout/frontend no projeto:
- criar um registro documental da mudança (objetivo, antes/depois, telas afetadas e arquivos alterados);
- salvar os arquivos em subpasta por data no padrão `docs/screenshots/YYYY-MM-DD/`;
- incluir prints **completos** (full page) das telas alteradas;
- opcionalmente adicionar capturas por viewport, além da captura completa;
- referenciar no PR/resposta final o caminho exato dos arquivos salvos.

## Padrão de registro

Cada página de captura deve conter:
- Contexto (tela, objetivo, versão)
- Data e ambiente
- Viewports utilizados (Desktop: 1440×900, Tablet: 1024×768, Mobile: 375×812)
- Lista das imagens com legenda
- Observações relevantes (ex.: limitações do ambiente)

### Estrutura de arquivos

```
docs/screenshots/
├── README.md                           # Este índice
├── YYYY-MM-DD/                         # Registros organizados por data
│   ├── SCREENSHOTS_YYYY-MM-DD.md       # Documento do dia (contexto + antes/depois)
│   ├── <tela>-desktop-fullpage.png     # Captura completa desktop
│   ├── <tela>-tablet-fullpage.png      # Captura completa tablet (quando aplicável)
│   └── <tela>-mobile-fullpage.png      # Captura completa mobile (quando aplicável)
├── <FEATURE>_SCREENSHOTS.md            # Documentação temática consolidada (opcional)
└── assets/                             # Arquivos legados ou comparativos por feature
```

### Convenções de nomenclatura
- Screenshots em PNG com nomes descritivos: `<tela>-<viewport>-fullpage.png` para capturas completas
- Demos HTML referenciam CSS via caminhos relativos ao repositório
- As imagens devem ser PNGs reais (não placeholders de texto)

### Template recomendado para `SCREENSHOTS_YYYY-MM-DD.md`

```md
# Screenshots YYYY-MM-DD — <feature/tela>

## Contexto
- Objetivo da mudança:
- Ambiente:
- Referência de design M3 utilizada:

## Antes/Depois
- Resumo do antes:
- Resumo do depois:
- Arquivos de código alterados:

## Capturas
- `./<tela>-desktop-fullpage.png`
- `./<tela>-tablet-fullpage.png` (se aplicável)
- `./<tela>-mobile-fullpage.png` (se aplicável)

## Observações
- Limitações de ambiente (se houver)
```
