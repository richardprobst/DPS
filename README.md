# desi.pet by PRObst (DPS)

Sistema modular de gestão para pet shops, construído como monorepo WordPress com um plugin base e add-ons especializados.

**Autor:** PRObst  
**Site:** [www.probst.pro](https://www.probst.pro)

---

## Visão geral

O DPS foi estruturado para separar responsabilidades:

- **Plugin base (`desi-pet-shower-base`)**: cadastro de clientes e pets, gestão de agendamentos, núcleo de interface, helpers globais e pontos de extensão.
- **Add-ons (`desi-pet-shower-*`)**: funcionalidades opcionais (agenda, finanças, IA, portal do cliente, pagamentos, estoque, assinaturas, etc.).
- **Documentação centralizada (`docs/`)**: guias de uso, análises técnicas, padrões visuais, QA e refatoração.

Essa arquitetura permite ativação gradual por necessidade do negócio, com evolução incremental sem acoplamento excessivo.

---

## Requisitos mínimos

- **WordPress:** 6.9+
- **PHP:** 8.4+
- **MySQL:** 5.7+ (ou MariaDB 10.2+)

> Recomendação: manter ambiente em PHP 8.4+ para melhor compatibilidade com os add-ons atuais.

---

## Estrutura do repositório

```text
DPS/
├── plugins/
│   ├── desi-pet-shower-base/
│   ├── desi-pet-shower-agenda/
│   ├── desi-pet-shower-ai/
│   ├── desi-pet-shower-backup/
│   ├── desi-pet-shower-booking/
│   ├── desi-pet-shower-client-portal/
│   ├── desi-pet-shower-communications/
│   ├── desi-pet-shower-finance/
│   ├── desi-pet-shower-groomers/
│   ├── desi-pet-shower-loyalty/
│   ├── desi-pet-shower-payment/
│   ├── desi-pet-shower-push/
│   ├── desi-pet-shower-registration/
│   ├── desi-pet-shower-services/
│   ├── desi-pet-shower-stats/
│   ├── desi-pet-shower-stock/
│   ├── desi-pet-shower-subscription/
│   └── README.md
├── docs/
├── AGENTS.md
├── ANALYSIS.md
├── CHANGELOG.md
└── README.md
```

---

## Componentes do ecossistema

### Núcleo

- [Plugin Base](plugins/desi-pet-shower-base/README.md): infraestrutura principal, hooks de extensão, telas centrais e serviços compartilhados.

### Add-ons oficiais

- **Operação:** Agenda, Booking, Groomers, Services, Stock.
- **Relacionamento:** Client Portal, Communications, Registration, Loyalty, Push.
- **Financeiro:** Finance, Payment, Subscription.
- **Inteligência e suporte:** AI, Backup, Stats.

Para visão consolidada dos add-ons e suas responsabilidades, consulte:
- [plugins/README.md](plugins/README.md)
- [ANALYSIS.md](ANALYSIS.md)
- [docs/analysis/ADDONS_DETAILED_ANALYSIS.md](docs/analysis/ADDONS_DETAILED_ANALYSIS.md)

---

## Primeiros passos

### Para operação (usuário/admin)

1. Instale e ative o plugin base.
2. Ative os add-ons necessários para o seu cenário.
3. Configure o sistema no painel WordPress.
4. Valide fluxos críticos (clientes, pets, agendamentos e financeiro, quando aplicável).

Documentação recomendada:
- [docs/GUIA_SISTEMA_DPS.md](docs/GUIA_SISTEMA_DPS.md)
- [docs/FUNCTIONS_REFERENCE.md](docs/FUNCTIONS_REFERENCE.md)

### Para desenvolvimento

1. Leia integralmente [AGENTS.md](AGENTS.md).
2. Revise [ANALYSIS.md](ANALYSIS.md) antes de alterações estruturais.
3. Consulte [docs/refactoring/AGENT_ENGINEERING_PLAYBOOK.md](docs/refactoring/AGENT_ENGINEERING_PLAYBOOK.md) para implementação/refatoração.
4. Atualize documentação quando houver impacto arquitetural ou funcional relevante.

---

## Mapa de documentação

### Documentos centrais (raiz)

- [AGENTS.md](AGENTS.md): diretrizes obrigatórias de contribuição (segurança, contratos, fluxo de trabalho).
- [ANALYSIS.md](ANALYSIS.md): arquitetura funcional, integrações e contratos entre núcleo e extensões.
- [CHANGELOG.md](CHANGELOG.md): histórico de versões e mudanças por release.

### Índices e guias principais

- [docs/README.md](docs/README.md): índice da documentação.
- [docs/GUIA_SISTEMA_DPS.md](docs/GUIA_SISTEMA_DPS.md): guia de uso e operação.
- [docs/FUNCTIONS_REFERENCE.md](docs/FUNCTIONS_REFERENCE.md): referência técnica de funções e métodos.

### Trilhas por assunto

- **Arquitetura:** [docs/analysis/SYSTEM_ANALYSIS_COMPLETE.md](docs/analysis/SYSTEM_ANALYSIS_COMPLETE.md), [docs/analysis/API_DOCUMENTATION.md](docs/analysis/API_DOCUMENTATION.md).
- **Admin:** [docs/admin/PORTAL_ADMIN_GUIDE.md](docs/admin/PORTAL_ADMIN_GUIDE.md), [docs/analysis/ADMIN_MENUS_MAPPING.md](docs/analysis/ADMIN_MENUS_MAPPING.md).
- **Configurações:** [docs/settings/FRONTEND_SETTINGS_IMPLEMENTATION_PLAN.md](docs/settings/FRONTEND_SETTINGS_IMPLEMENTATION_PLAN.md), [docs/settings/FRONTEND_SETTINGS_LAYOUT_ANALYSIS.md](docs/settings/FRONTEND_SETTINGS_LAYOUT_ANALYSIS.md).
- **Visual e UX:** [docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md](docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md), [docs/visual/VISUAL_STYLE_GUIDE.md](docs/visual/VISUAL_STYLE_GUIDE.md), [docs/screenshots/README.md](docs/screenshots/README.md).
- **Refatoração e manutenção:** [docs/refactoring/](docs/refactoring/).
- **Evidências visuais:** [docs/screenshots/](docs/screenshots/).

---

## Segurança e governança técnica

O projeto adota como padrão:

- validação de **nonce + capability + sanitização/escape**;
- uso de APIs nativas do WordPress sempre que possível;
- preservação de contratos entre plugin base e add-ons;
- versionamento com SemVer e documentação de mudanças relevantes.

Referência obrigatória: [AGENTS.md](AGENTS.md).

---

## Convenções de contribuição

- Use commits curtos em português (imperativo).
- Não adicione arquivos `.md` soltos na raiz além dos documentos centrais.
- Para mudanças visuais, siga o padrão M3 em `docs/visual/` e registre evidências em `docs/screenshots/YYYY-MM-DD/`.
- Para mudanças estruturais, reflita impactos em `ANALYSIS.md` e, quando necessário, em `CHANGELOG.md`.

---

## Links rápidos

### Operação

- [Guia do sistema](docs/GUIA_SISTEMA_DPS.md)
- [Portal admin](docs/admin/PORTAL_ADMIN_GUIDE.md)

### Engenharia

- [Diretrizes de agentes](AGENTS.md)
- [Playbook de engenharia](docs/refactoring/AGENT_ENGINEERING_PLAYBOOK.md)
- [Arquitetura funcional](ANALYSIS.md)
- [Análise completa do sistema](docs/analysis/SYSTEM_ANALYSIS_COMPLETE.md)

### Histórico e evolução

- [Changelog](CHANGELOG.md)
- [Registro visual](docs/screenshots/README.md)

---

**desi.pet by PRObst** — sistema completo de gestão para pet shops.
