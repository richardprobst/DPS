# Desi Pet Shower (DPS) – Sistema de Gestão para Banho e Tosa

Sistema completo de gestão para pet shops especializados em banho e tosa, desenvolvido como monorepo contendo o plugin WordPress principal e diversos add-ons complementares.

## 📂 Estrutura do Repositório

```
DPS/
├── plugin/
│   └── desi-pet-shower-base_plugin/    # Plugin núcleo (base do sistema)
├── add-ons/                             # Add-ons oficiais (plugins complementares)
│   ├── desi-pet-shower-agenda_addon/
│   ├── desi-pet-shower-finance_addon/
│   ├── desi-pet-shower-client-portal_addon/
│   └── ... (14 add-ons no total)
├── docs/                                # Documentação detalhada
│   ├── layout/                         # Análises de layout (admin, agenda, portal, forms)
│   ├── forms/                          # Docs do formulário de agendamento
│   ├── refactoring/                    # Análises e planos de refatoração
│   ├── visual/                         # Guia de estilo visual
│   ├── implementation/                 # Resumos de implementação UI/UX
│   └── README.md                       # Índice da documentação
├── AGENTS.md                           # Regras para contribuidores (humanos e IAs)
├── ANALYSIS.md                         # Visão geral de arquitetura
├── CHANGELOG.md                        # Histórico de versões
├── SYSTEM_ANALYSIS_COMPLETE.md        # Análise profunda do sistema (back + front)
├── SYSTEM_ANALYSIS_SUMMARY.md         # Resumo executivo da análise
└── README.md                           # Este arquivo
```

## 🎯 Visão Geral

O **Desi Pet Shower** é um sistema modular composto por:

### Plugin Núcleo (`/plugin/desi-pet-shower-base_plugin`)

O plugin base fornece a infraestrutura fundamental:
- Sistema de cadastro de clientes e pets
- Gerenciamento de agendamentos
- Interface administrativa unificada
- Helpers globais reutilizáveis
- Pontos de extensão (hooks) para add-ons
- Sistema de logging centralizado

**[Ver documentação do plugin base →](plugin/desi-pet-shower-base_plugin/README.md)**

### Add-ons Oficiais (`/add-ons`)

Plugins complementares que estendem o sistema com funcionalidades específicas:
- **Agenda** - Visualização e gerenciamento de agendamentos
- **Finance** - Gestão financeira e controle de transações
- **Client Portal** - Portal do cliente (front-end)
- **Communications** - SMS, e-mail e WhatsApp
- **Services** - Catálogo de serviços
- **Payment** - Integrações de pagamento
- **Subscription** - Gerenciamento de assinaturas
- **Stats** - Estatísticas e relatórios
- E mais 6 add-ons adicionais

**[Ver documentação dos add-ons →](add-ons/README.md)**

## 📚 Documentação

### Documentos Principais (raiz)

- **[AGENTS.md](AGENTS.md)** - Diretrizes completas para contribuidores
  - Convenções de código
  - Fluxo obrigatório para mudanças
  - Políticas de segurança
  - Regras de documentação
  - Versionamento e git-flow

- **[ANALYSIS.md](ANALYSIS.md)** - Arquitetura do sistema
  - Visão geral do núcleo e add-ons
  - Fluxos de integração
  - Contratos de hooks
  - Estrutura de dados (CPTs, tabelas)
  - Padrões de desenvolvimento

- **[CHANGELOG.md](CHANGELOG.md)** - Histórico de versões
  - Releases e tags
  - Mudanças por versão
  - Breaking changes
  - Migrações necessárias

- **[BACKEND_FRONTEND_MAPPING.md](BACKEND_FRONTEND_MAPPING.md)** - 🆕 Mapeamento BACK-END vs FRONT-END
  - Classificação completa: CONFIG vs OPERAÇÃO
  - Identificação de violações críticas (configurações expostas no front)
  - 10 ações priorizadas para segregação adequada
  - Análise de segurança e estimativas de esforço
  - **Baseado no código real** (fonte da verdade)

- **[SYSTEM_ANALYSIS_COMPLETE.md](SYSTEM_ANALYSIS_COMPLETE.md)** - Análise profunda do sistema
  - Mapeamento completo de back-end (admin) e front-end
  - Identificação de duplicações de arquivos, funções e classes
  - Lógica espalhada entre core e add-ons
  - Sugestões detalhadas de reorganização
  - Baseado no código real (não em documentação)

- **[SYSTEM_ANALYSIS_SUMMARY.md](SYSTEM_ANALYSIS_SUMMARY.md)** - Resumo executivo da análise
  - Quick reference com principais descobertas
  - Ações priorizadas (Alta/Média/Baixa prioridade)
  - Problemas críticos identificados
  - Guia rápido para tomada de decisões

### Documentação Detalhada (`/docs`)

A pasta `/docs` contém análises detalhadas de UX, layout, refatoração e implementação:

- **[/docs/README.md](docs/README.md)** - Índice completo da documentação
- `/docs/layout/` - Análises de layout (admin, agenda, portal do cliente, formulários)
- `/docs/forms/` - Documentação do formulário de agendamento
- `/docs/refactoring/` - Análises de código e padrões de refatoração
- `/docs/visual/` - Guia de estilo visual (cores, tipografia, componentes)
- `/docs/implementation/` - Resumos de implementação de melhorias UI/UX

## 🚀 Como Começar

### Para Usuários

1. Instale o plugin base (`desi-pet-shower-base_plugin`)
2. Ative os add-ons desejados conforme suas necessidades
3. Configure via painel admin WordPress

### Para Desenvolvedores

1. **Primeiro**: Leia [AGENTS.md](AGENTS.md) para entender as regras de desenvolvimento
2. **Depois**: Consulte [ANALYSIS.md](ANALYSIS.md) para entender a arquitetura
3. **Sempre**: Atualize [CHANGELOG.md](CHANGELOG.md) ao fazer mudanças
4. **Referência**: Use `/docs` para guias de UX, layout e refatoração

## 🛠️ Tecnologias

- **WordPress**: 6.0+
- **PHP**: 7.4+
- **MySQL**: 5.7+ / MariaDB 10.2+
- **JavaScript**: Vanilla JS e jQuery (fornecido pelo WordPress)
- **CSS**: CSS3 com abordagem minimalista

## 📋 Requisitos do Sistema

- WordPress 6.0 ou superior
- PHP 7.4 ou superior
- MySQL 5.7+ ou MariaDB 10.2+
- Recomendado: PHP 8.0+ para melhor performance

## 🔒 Segurança

O projeto segue rigorosas políticas de segurança:
- Validação de nonces em todas as requisições
- Escape de saída (esc_html, esc_attr, etc.)
- Sanitização de entrada (sanitize_text_field, etc.)
- Verificação de capabilities (manage_options, etc.)
- Sem armazenamento de segredos no código

Consulte [AGENTS.md - Políticas de segurança](AGENTS.md#políticas-de-segurança-obrigatórias) para detalhes completos.

## 🤝 Contribuindo

Este é um repositório privado desenvolvido para uso específico. Para contribuir:

1. Leia [AGENTS.md](AGENTS.md) completamente
2. Siga o git-flow descrito (feature branches, PRs revisados)
3. Use SemVer (MAJOR.MINOR.PATCH) para versionamento
4. Documente mudanças no [CHANGELOG.md](CHANGELOG.md)
5. Atualize [ANALYSIS.md](ANALYSIS.md) se alterar arquitetura
6. Siga as convenções de código WordPress

## 📖 Convenções de Código

- **WordPress Coding Standards**: indentação de 4 espaços, snake_case para funções
- **Prefixação**: todas as funções, classes e hooks prefixados com `dps_`
- **Documentação**: DocBlocks em todas as classes e funções públicas
- **Estilo**: minimalista e clean (veja `/docs/visual/VISUAL_STYLE_GUIDE.md`)

## 📄 Licença

Software proprietário - todos os direitos reservados.

## 🔗 Links Rápidos

### Código e Estrutura
- [Plugin Base](plugin/desi-pet-shower-base_plugin/README.md)
- [Add-ons](add-ons/README.md)
- [Documentação Completa](docs/README.md)

### Desenvolvimento
- [Guia de Desenvolvimento](AGENTS.md)
- [Arquitetura do Sistema](ANALYSIS.md)
- [Histórico de Versões](CHANGELOG.md)

### Análise do Sistema 🆕
- [**Mapeamento BACK-END vs FRONT-END**](BACKEND_FRONTEND_MAPPING.md) - ⚠️ Classificação CONFIG vs OPERAÇÃO
- [**Análise Completa**](SYSTEM_ANALYSIS_COMPLETE.md) - Mapeamento detalhado back + front
- [**Resumo Executivo**](SYSTEM_ANALYSIS_SUMMARY.md) - Quick reference e ações priorizadas

### UX e Refatoração
- [Guia de Estilo Visual](docs/visual/VISUAL_STYLE_GUIDE.md)
- [Padrões de Refatoração](docs/refactoring/REFACTORING_ANALYSIS.md)

### Compatibilidade
- [**YooTheme PRO**](docs/compatibility/YOOTHEME_COMPATIBILITY.md) - ⚠️ Resolver erro "O construtor não está disponível"

---

**Desi Pet Shower** - Sistema completo de gestão para pet shops especializados em banho e tosa.
