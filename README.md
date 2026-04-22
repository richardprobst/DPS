# desi.pet by PRObst (DPS)

Sistema modular de gestÃ£o para pet shops, construÃ­do como monorepo WordPress com um plugin base e add-ons especializados.

**Autor:** PRObst
**Site:** [www.probst.pro](https://www.probst.pro)

---

## VisÃ£o geral

O DPS foi estruturado para separar responsabilidades:

- **Plugin base (`desi-pet-shower-base`)**: cadastro de clientes e pets, gestÃ£o de agendamentos, nÃºcleo de interface, helpers globais e pontos de extensÃ£o.
- **Add-ons (`desi-pet-shower-*`)**: funcionalidades opcionais (agenda, finanÃ§as, IA, portal do cliente, pagamentos, estoque, assinaturas, etc.).
- **DocumentaÃ§Ã£o centralizada (`docs/`)**: guias de uso, anÃ¡lises tÃ©cnicas, padrÃµes visuais, QA e refatoraÃ§Ã£o.

Essa arquitetura permite ativaÃ§Ã£o gradual por necessidade do negÃ³cio, com evoluÃ§Ã£o incremental sem acoplamento excessivo.

---

## Requisitos mÃ­nimos

- **WordPress:** 6.9+
- **PHP:** 8.4+
- **MySQL:** 5.7+ (ou MariaDB 10.2+)

> RecomendaÃ§Ã£o: manter ambiente em PHP 8.4+ para melhor compatibilidade com os add-ons atuais.

---

## Estrutura do repositÃ³rio

```text
DPS/
â”œâ”€â”€ plugins/
â”‚   â”œâ”€â”€ desi-pet-shower-base/
â”‚   â”œâ”€â”€ desi-pet-shower-agenda/
â”‚   â”œâ”€â”€ desi-pet-shower-ai/
â”‚   â”œâ”€â”€ desi-pet-shower-backup/
â”‚   â”œâ”€â”€ desi-pet-shower-booking/
â”‚   â”œâ”€â”€ desi-pet-shower-client-portal/
â”‚   â”œâ”€â”€ desi-pet-shower-communications/
â”‚   â”œâ”€â”€ desi-pet-shower-finance/
â”‚   â”œâ”€â”€ desi-pet-shower-groomers/
â”‚   â”œâ”€â”€ desi-pet-shower-loyalty/
â”‚   â”œâ”€â”€ desi-pet-shower-payment/
â”‚   â”œâ”€â”€ desi-pet-shower-push/
â”‚   â”œâ”€â”€ desi-pet-shower-registration/
â”‚   â”œâ”€â”€ desi-pet-shower-services/
â”‚   â”œâ”€â”€ desi-pet-shower-stats/
â”‚   â”œâ”€â”€ desi-pet-shower-stock/
â”‚   â”œâ”€â”€ desi-pet-shower-subscription/
â”‚   â””â”€â”€ README.md
â”œâ”€â”€ docs/
â”œâ”€â”€ AGENTS.md
â”œâ”€â”€ ANALYSIS.md
â”œâ”€â”€ CHANGELOG.md
â””â”€â”€ README.md
```

---

## Componentes do ecossistema

### NÃºcleo

- [Plugin Base](plugins/desi-pet-shower-base/README.md): infraestrutura principal, hooks de extensÃ£o, telas centrais e serviÃ§os compartilhados.

### Add-ons oficiais

- **OperaÃ§Ã£o:** Agenda, Booking, Groomers, Services, Stock.
- **Relacionamento:** Client Portal, Communications, Registration, Loyalty, Push.
- **Financeiro:** Finance, Payment, Subscription.
- **InteligÃªncia e suporte:** AI, Backup, Stats.

Para visÃ£o consolidada dos add-ons e suas responsabilidades, consulte:
- [plugins/README.md](plugins/README.md)
- [ANALYSIS.md](ANALYSIS.md)
- [docs/analysis/ADDONS_DETAILED_ANALYSIS.md](docs/analysis/ADDONS_DETAILED_ANALYSIS.md)

---

## Primeiros passos

### Para operaÃ§Ã£o (usuÃ¡rio/admin)

1. Instale e ative o plugin base.
2. Ative os add-ons necessÃ¡rios para o seu cenÃ¡rio.
3. Configure o sistema no painel WordPress.
4. Valide fluxos crÃ­ticos (clientes, pets, agendamentos e financeiro, quando aplicÃ¡vel).

DocumentaÃ§Ã£o recomendada:
- [docs/GUIA_SISTEMA_DPS.md](docs/GUIA_SISTEMA_DPS.md)
- [docs/FUNCTIONS_REFERENCE.md](docs/FUNCTIONS_REFERENCE.md)

### Para desenvolvimento

1. Leia integralmente [AGENTS.md](AGENTS.md).
2. Revise [ANALYSIS.md](ANALYSIS.md) antes de alteraÃ§Ãµes estruturais.
3. Consulte [docs/refactoring/AGENT_ENGINEERING_PLAYBOOK.md](docs/refactoring/AGENT_ENGINEERING_PLAYBOOK.md) para implementaÃ§Ã£o/refatoraÃ§Ã£o.
4. Atualize documentaÃ§Ã£o quando houver impacto arquitetural ou funcional relevante.

---

## Mapa de documentaÃ§Ã£o

### Documentos centrais (raiz)

- [AGENTS.md](AGENTS.md): diretrizes obrigatÃ³rias de contribuiÃ§Ã£o (seguranÃ§a, contratos, fluxo de trabalho).
- [ANALYSIS.md](ANALYSIS.md): arquitetura funcional, integraÃ§Ãµes e contratos entre nÃºcleo e extensÃµes.
- [CHANGELOG.md](CHANGELOG.md): histÃ³rico de versÃµes e mudanÃ§as por release.

### Ãndices e guias principais

- [docs/README.md](docs/README.md): Ã­ndice da documentaÃ§Ã£o.
- [docs/GUIA_SISTEMA_DPS.md](docs/GUIA_SISTEMA_DPS.md): guia de uso e operaÃ§Ã£o.
- [docs/FUNCTIONS_REFERENCE.md](docs/FUNCTIONS_REFERENCE.md): referÃªncia tÃ©cnica de funÃ§Ãµes e mÃ©todos.

### Trilhas por assunto

- **Arquitetura:** [docs/analysis/SYSTEM_ANALYSIS_COMPLETE.md](docs/analysis/SYSTEM_ANALYSIS_COMPLETE.md), [docs/analysis/API_DOCUMENTATION.md](docs/analysis/API_DOCUMENTATION.md).
- **Admin:** [docs/admin/PORTAL_ADMIN_GUIDE.md](docs/admin/PORTAL_ADMIN_GUIDE.md), [docs/analysis/ADMIN_MENUS_MAPPING.md](docs/analysis/ADMIN_MENUS_MAPPING.md).
- **ConfiguraÃ§Ãµes:** [docs/settings/FRONTEND_SETTINGS_IMPLEMENTATION_PLAN.md](docs/settings/FRONTEND_SETTINGS_IMPLEMENTATION_PLAN.md), [docs/settings/FRONTEND_SETTINGS_LAYOUT_ANALYSIS.md](docs/settings/FRONTEND_SETTINGS_LAYOUT_ANALYSIS.md).
- **Visual e UX:** [docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md](docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md), [docs/visual/VISUAL_STYLE_GUIDE.md](docs/visual/VISUAL_STYLE_GUIDE.md), [docs/screenshots/README.md](docs/screenshots/README.md).
- **RefatoraÃ§Ã£o e manutenÃ§Ã£o:** [docs/refactoring/](docs/refactoring/).
- **EvidÃªncias visuais:** [docs/screenshots/](docs/screenshots/).

---

## SeguranÃ§a e governanÃ§a tÃ©cnica

O projeto adota como padrÃ£o:

- validaÃ§Ã£o de **nonce + capability + sanitizaÃ§Ã£o/escape**;
- uso de APIs nativas do WordPress sempre que possÃ­vel;
- preservaÃ§Ã£o de contratos entre plugin base e add-ons;
- versionamento com SemVer e documentaÃ§Ã£o de mudanÃ§as relevantes.

ReferÃªncia obrigatÃ³ria: [AGENTS.md](AGENTS.md).

---

## ConvenÃ§Ãµes de contribuiÃ§Ã£o

- Use commits curtos em portuguÃªs (imperativo).
- NÃ£o adicione arquivos `.md` soltos na raiz alÃ©m dos documentos centrais.
- Para mudanÃ§as visuais, siga o padrÃ£o DPS Signature em `docs/visual/` e registre evidÃªncias em `docs/screenshots/YYYY-MM-DD/`.
- Para mudanÃ§as estruturais, reflita impactos em `ANALYSIS.md` e, quando necessÃ¡rio, em `CHANGELOG.md`.

---

## Links rÃ¡pidos

### OperaÃ§Ã£o

- [Guia do sistema](docs/GUIA_SISTEMA_DPS.md)
- [Portal admin](docs/admin/PORTAL_ADMIN_GUIDE.md)

### Engenharia

- [Diretrizes de agentes](AGENTS.md)
- [Playbook de engenharia](docs/refactoring/AGENT_ENGINEERING_PLAYBOOK.md)
- [Arquitetura funcional](ANALYSIS.md)
- [AnÃ¡lise completa do sistema](docs/analysis/SYSTEM_ANALYSIS_COMPLETE.md)

### HistÃ³rico e evoluÃ§Ã£o

- [Changelog](CHANGELOG.md)
- [Registro visual](docs/screenshots/README.md)

---

**desi.pet by PRObst** â€” sistema completo de gestÃ£o para pet shops.
