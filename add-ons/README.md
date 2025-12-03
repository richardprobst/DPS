# Add-ons do DPS by PRObst

Esta pasta contém os plugins complementares (add-ons) do sistema DPS by PRObst. Cada add-on é um plugin WordPress separado que **depende do plugin núcleo** localizado em `/plugin/desi-pet-shower-base_plugin/`.

**Autor:** PRObst  
**Site:** [www.probst.pro](https://www.probst.pro)

## Estrutura Geral

Cada subpasta neste diretório representa um add-on independente:

```
add-ons/
├── desi-pet-shower-agenda_addon/           # Gerenciamento de agenda e agendamentos
├── desi-pet-shower-backup_addon/           # Sistema de backup e restauração
├── desi-pet-shower-client-portal_addon/    # Portal do cliente (front-end)
├── desi-pet-shower-communications_addon/   # Comunicações (SMS, e-mail, WhatsApp)
├── desi-pet-shower-finance_addon/          # Gestão financeira e transações
├── desi-pet-shower-groomers_addon/         # Gerenciamento de tosadores
├── desi-pet-shower-loyalty_addon/          # Programa de fidelidade
├── desi-pet-shower-payment_addon/          # Integrações de pagamento
├── desi-pet-shower-push_addon/             # Notificações push
├── desi-pet-shower-registration_addon/     # Formulário de cadastro inicial
├── desi-pet-shower-services_addon/         # Catálogo de serviços
├── desi-pet-shower-stats_addon/            # Estatísticas e relatórios
├── desi-pet-shower-stock_addon/            # Controle de estoque
└── desi-pet-shower-subscription_addon/     # Gerenciamento de assinaturas
```

## Dependências

**TODOS os add-ons dependem do plugin base** (`desi-pet-shower-base_plugin`) para funcionar. O plugin núcleo fornece:

- Sistema de cadastro de clientes e pets
- Infraestrutura de hooks e pontos de extensão
- Classes helper reutilizáveis (DPS_Money_Helper, DPS_URL_Builder, etc.)
- Interface administrativa unificada
- Sistema de logging centralizado

## Arquitetura de Add-ons

Cada add-on segue o padrão de estrutura descrito em **[ANALYSIS.md](../ANALYSIS.md)** (seção "Padrões de desenvolvimento de add-ons"):

### Estrutura Recomendada

```
desi-pet-shower-<nome>-addon/
├── desi-pet-shower-<nome>-addon.php  # Arquivo principal do plugin
├── assets/                            # CSS/JS específicos do add-on
│   ├── css/
│   └── js/
├── includes/                          # Classes e lógica PHP
│   ├── class-*.php                   # Classes principais
│   └── helpers/                      # Helpers específicos (se necessário)
├── templates/                         # Templates de saída (se o add-on tiver)
├── uninstall.php                     # Script de desinstalação
└── README.md                         # Documentação do add-on
```

### Pontos de Integração

Os add-ons se integram ao núcleo através de hooks padronizados:

- **Navegação**: `dps_base_nav_tabs_*` - adicionar abas no painel admin
- **Seções**: `dps_base_sections_*` - renderizar conteúdo de seções
- **Configurações**: `dps_settings_*` - adicionar campos de configuração
- **Dados**: hooks de ação/filtro específicos para eventos de negócio

Consulte o **[ANALYSIS.md](../ANALYSIS.md)** para detalhes completos sobre os hooks disponíveis e contratos de integração.

## Regras de Desenvolvimento

Todas as regras de desenvolvimento, convenções de código e políticas de segurança estão documentadas em:

- **[AGENTS.md](../AGENTS.md)** - Diretrizes completas para desenvolvimento
- **[ANALYSIS.md](../ANALYSIS.md)** - Arquitetura e contratos de integração
- **[CHANGELOG.md](../CHANGELOG.md)** - Histórico de versões (sempre atualizar)

## Documentação Individual

Cada add-on possui seu próprio `README.md` com:

- Propósito e funcionalidades principais
- Dependências específicas (além do plugin base)
- Hooks utilizados ou expostos
- Tabelas de banco de dados (se criar alguma)
- Shortcodes, CPTs ou capabilities adicionados

Consulte o README.md dentro de cada pasta de add-on para detalhes específicos.

## Desenvolvimento de Novos Add-ons

Ao criar um novo add-on:

1. **Siga a estrutura recomendada** de arquivos e pastas descrita acima
2. **Use os hooks do núcleo** para se integrar (não modifique o plugin base)
3. **Reutilize helpers globais** quando possível (DPS_Money_Helper, etc.)
4. **Documente em ANALYSIS.md**: adicione uma seção descritiva incluindo:
   - Nome do add-on e diretório
   - Propósito e funcionalidades
   - Hooks utilizados ou expostos
   - Dependências de outros add-ons
   - Tabelas/metadados criados
5. **Atualize CHANGELOG.md** com a adição do novo add-on
6. **Crie README.md** dentro da pasta do add-on

## Recursos Adicionais

- **[/docs](../docs/)** - Documentação detalhada de UX, layout e refatoração
- **[/docs/visual/VISUAL_STYLE_GUIDE.md](../docs/visual/VISUAL_STYLE_GUIDE.md)** - Guia de estilo visual
- **[/docs/refactoring/REFACTORING_ANALYSIS.md](../docs/refactoring/REFACTORING_ANALYSIS.md)** - Padrões de refatoração

## Ordem de Ativação Recomendada

1. **Plugin Base** (obrigatório)
2. **Agenda** (gerenciamento de agendamentos)
3. **Finance** (gestão financeira)
4. **Services** (catálogo de serviços)
5. **Communications** (notificações)
6. **Client Portal** (portal do cliente)
7. Demais add-ons conforme necessidade

**Nota**: Alguns add-ons dependem de outros. Por exemplo, o add-on de Assinaturas (`subscription`) depende do add-on Financeiro (`finance`). Consulte o README.md de cada add-on para verificar dependências específicas.
