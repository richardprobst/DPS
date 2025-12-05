# DPS by PRObst – Base

Plugin núcleo do sistema de gestão para pet shops.

**Autor:** PRObst  
**Site:** [www.probst.pro](https://www.probst.pro)

## Visão geral

O **DPS by PRObst Base** é o plugin principal que fornece a infraestrutura completa para cadastro de clientes, pets e agendamentos. Ele serve como fundação para todo o ecossistema DPS, expondo pontos de extensão (hooks) que permitem aos add-ons complementares adicionar funcionalidades como financeiro, comunicações, portal do cliente, entre outros.

Este plugin oferece:
- Sistema de cadastro de clientes e pets com relacionamento 1:N
- Gerenciamento de agendamentos com controle de status
- Interface unificada no painel administrativo via shortcodes
- Helpers globais reutilizáveis para operações comuns (valores monetários, URLs, validação)
- Pontos de extensão padronizados para add-ons
- Sistema de logging centralizado

## Estrutura de Pastas

```
desi-pet-shower-base_plugin/
├── assets/              # CSS, JS e imagens do núcleo
│   ├── css/            # Estilos globais
│   ├── js/             # Scripts JavaScript
│   └── images/         # Imagens e ícones
├── includes/           # Classes PHP, hooks e helpers
│   ├── class-*.php     # Classes principais do sistema
│   └── helpers/        # Classes helper reutilizáveis
├── templates/          # Templates de saída (shortcodes, telas admin/front)
├── docs/               # Documentação específica do núcleo (se necessário)
├── desi-pet-shower-base.php  # Arquivo principal do plugin
├── uninstall.php       # Script de desinstalação
└── README.md           # Este arquivo
```

## Documentação e Diretrizes

Para entender a arquitetura geral do sistema e regras de desenvolvimento:

- **[AGENTS.md](../../AGENTS.md)** - Regras e diretrizes para contribuidores (humanos e IAs)
- **[ANALYSIS.md](../../ANALYSIS.md)** - Visão geral de arquitetura, fluxos de integração e contratos entre núcleo e extensões
- **[CHANGELOG.md](../../CHANGELOG.md)** - Histórico de versões e lançamentos
- **[/docs](../../docs/)** - Documentação detalhada de UX, layout, refatoração e implementação

## Localização e identificação

- **Diretório**: `plugin/desi-pet-shower-base_plugin/`
- **Slug**: `desi-pet-shower-base`
- **Classe principal**: `DPS_Base_Plugin`
- **Arquivo principal**: `desi-pet-shower-base.php`
- **Tipo**: Plugin base (núcleo do sistema)

## Dependências e compatibilidade

- **WordPress**: 6.0 ou superior
- **PHP**: 7.4 ou superior
- **Introduzido em**: v1.0.0
- **Versão atual**: v1.0.1

Este é o plugin base; todos os add-ons do DPS dependem dele para funcionar.

## Funcionalidades principais

### Gestão de dados
- **Clientes**: cadastro completo com nome, telefone, e-mail, endereço e observações
- **Pets**: vinculação de múltiplos pets a cada cliente, com nome, espécie, raça, porte e características
- **Agendamentos**: criação de atendimentos com data, horário, status e vinculação a clientes/pets

### Interface administrativa
- Painel unificado acessível via shortcode `[dps_base]` com navegação por abas
- Tela de configurações via shortcode `[dps_configuracoes]` para add-ons injetarem opções
- Histórico completo de atendimentos com filtros e exportação para CSV
- Paginação e busca em todas as listagens

### Sistema de extensão
- Hooks de navegação (`dps_base_nav_tabs_*`) para add-ons adicionarem abas
- Hooks de seções (`dps_base_sections_*`) para add-ons renderizarem conteúdo
- Hooks de formulários (`dps_base_appointment_fields`) para campos customizados
- Hooks de limpeza (`dps_finance_cleanup_for_appointment`) para dados relacionados

### Helpers globais
- **DPS_Money_Helper**: conversão entre formato brasileiro (R$ 1.234,56) e centavos
- **DPS_URL_Builder**: construção padronizada de URLs com nonces de segurança
- **DPS_Query_Helper**: consultas WP_Query otimizadas com paginação
- **DPS_Request_Validator**: validação centralizada de nonces, capabilities e sanitização

### Segurança
- Validação de nonces em todas as ações (CSRF protection)
- Sanitização de entrada e escape de saída seguindo padrões WordPress
- Sistema de capabilities customizadas (`dps_manage_clients`, `dps_manage_pets`, `dps_manage_appointments`)
- Role customizada `dps_reception` para usuários de recepção

### Performance
- Cache de pets relacionados a clientes via REST API
- Carregamento em lotes de agendamentos históricos (configurável via filtro)
- Pré-carregamento de metadados com `update_meta_cache()`
- Queries otimizadas com `fields => 'ids'` quando apropriado

#### Desabilitando o cache

O DPS utiliza transients do WordPress para cache de dados em vários pontos do sistema, melhorando a performance. Em situações de desenvolvimento ou debug, pode ser útil desabilitar o cache temporariamente.

Para desabilitar todo o cache do DPS, adicione a seguinte constante no arquivo `wp-config.php`:

```php
define( 'DPS_DISABLE_CACHE', true );
```

**⚠️ Atenção**: Desabilitar o cache pode impactar significativamente a performance do sistema. Recomenda-se usar esta opção apenas para:
- Desenvolvimento e testes
- Debug de problemas relacionados a dados em cache
- Ambientes de staging temporariamente

Para reabilitar o cache, remova a constante ou defina como `false`:

```php
define( 'DPS_DISABLE_CACHE', false );
```

**Caches afetados por esta configuração**:
- Listagem de pets na API REST (15 minutos)
- Lista de clientes na agenda (1 hora)
- Lista de serviços na agenda (1 hora)
- Estatísticas de atendimentos e receita (1 hora)
- Métricas do programa de fidelidade (5 minutos)
- Contexto de IA para mensagens (30 minutos)

**Caches NÃO afetados** (por questões de segurança):
- Tokens de login do portal do cliente
- Rate limiting de requisições
- Tentativas de login

## Shortcodes, widgets e endpoints

### Shortcodes

> ⚠️ **Como inserir shortcodes corretamente no editor WordPress (Gutenberg)**
>
> Use o bloco **"Shortcode"** ou **"Parágrafo"** para inserir shortcodes.
>
> **NÃO use o bloco "Código"** (Code) — ele exibe texto literalmente e não executa shortcodes.
> Se você inserir `[dps_base]` no bloco Código, o texto aparecerá como está em vez de renderizar o painel.

#### `[dps_base]`
Renderiza o painel principal do sistema com navegação por abas.

**Uso**:
```
[dps_base]
```

**Descrição**: Exibe interface completa de gerenciamento com abas para Clientes, Pets, Agendamentos e Histórico. Add-ons podem injetar abas adicionais via hooks.

**Permissões**: Usuário deve ter capability `dps_manage_clients`, `dps_manage_pets` ou `dps_manage_appointments`.

**⚠️ Problemas com Page Builders?** Se você usa YooTheme PRO ou outro page builder e encontra erros ao adicionar este shortcode, consulte [Guia de Compatibilidade com YooTheme PRO](../../docs/compatibility/YOOTHEME_COMPATIBILITY.md).

---

#### `[dps_configuracoes]`
Renderiza a tela de configurações do sistema.

**Uso**:
```
[dps_configuracoes]
```

**Descrição**: Exibe navegação de configurações onde add-ons injetam suas próprias seções via hooks `dps_settings_nav_tabs` e `dps_settings_sections`.

**Permissões**: Usuário deve ter capability `manage_options`.

### Endpoints REST

- **GET `/wp-json/dps/v1/pets-by-client/{client_id}`**: retorna lista de pets vinculados a um cliente específico, com cache automático.

## Hooks (actions e filters) relevantes

### Hooks CONSUMIDOS pelo plugin base

Este plugin não consome hooks externos; ele é a base do sistema.

### Hooks DISPARADOS pelo plugin base

#### Navegação e interface

- **`dps_base_nav_tabs_after_pets`** (action)
  - **Momento**: Durante renderização das abas de navegação, após a aba "Pets"
  - **Parâmetros**: nenhum
  - **Uso**: Add-ons podem adicionar abas customizadas na navegação principal

- **`dps_base_nav_tabs_after_history`** (action)
  - **Momento**: Durante renderização das abas de navegação, após a aba "Histórico"
  - **Parâmetros**: nenhum
  - **Uso**: Add-ons podem adicionar abas gerenciais (Estatísticas, Groomers, Estoque)

- **`dps_base_sections_after_pets`** (action)
  - **Momento**: Durante renderização do conteúdo das seções, após seção de Pets
  - **Parâmetros**: nenhum
  - **Uso**: Add-ons renderizam conteúdo da aba correspondente

- **`dps_base_sections_after_history`** (action)
  - **Momento**: Durante renderização do conteúdo das seções, após seção de Histórico
  - **Parâmetros**: nenhum
  - **Uso**: Add-ons renderizam conteúdo da aba correspondente

#### Configurações

- **`dps_settings_nav_tabs`** (action)
  - **Momento**: Durante renderização das abas de configurações
  - **Parâmetros**: nenhum
  - **Uso**: Add-ons adicionam abas na tela de configurações

- **`dps_settings_sections`** (action)
  - **Momento**: Durante renderização do conteúdo de configurações
  - **Parâmetros**: nenhum
  - **Uso**: Add-ons renderizam suas configurações

#### Formulários e dados

- **`dps_base_appointment_fields`** (action)
  - **Momento**: Durante renderização do formulário de agendamento
  - **Parâmetros**: nenhum
  - **Uso**: Add-ons podem injetar campos customizados (ex.: seleção de groomer, serviços)

- **`dps_base_after_save_appointment`** (action)
  - **Momento**: Após salvar um agendamento com sucesso
  - **Parâmetros**: `$appointment_id` (int)
  - **Uso**: Add-ons podem executar lógica adicional (ex.: baixa de estoque, envio de notificações)

- **`dps_finance_cleanup_for_appointment`** (action)
  - **Momento**: Durante exclusão de um agendamento
  - **Parâmetros**: `$appointment_id` (int)
  - **Uso**: Add-ons financeiros podem remover transações vinculadas

#### Filtros

- **`dps_history_batch_size`** (filter)
  - **Momento**: Antes de carregar lotes de agendamentos no histórico
  - **Parâmetros**: `$batch_size` (int, padrão: 200)
  - **Retorno**: int (tamanho do lote)
  - **Uso**: Ajustar performance de consultas de histórico

## Dados armazenados (CPTs, tabelas, options)

### Custom Post Types

#### `dps_client` (Clientes)
Armazena dados cadastrais de clientes.

**Metadados principais**:
- `client_phone`: telefone do cliente
- `client_email`: e-mail do cliente
- `client_address`: endereço completo
- `client_notes`: observações adicionais

**Capabilities**: `dps_manage_clients`

---

#### `dps_pet` (Pets)
Armazena dados de animais vinculados a clientes.

**Metadados principais**:
- `pet_client_id`: ID do cliente proprietário (relacionamento)
- `pet_species`: espécie (cachorro, gato, etc.)
- `pet_breed`: raça
- `pet_size`: porte (pequeno, médio, grande)
- `pet_notes`: observações adicionais

**Capabilities**: `dps_manage_pets`

**Relacionamento**: N pets : 1 cliente (via meta `pet_client_id`)

---

#### `dps_appointment` (Agendamentos)
Armazena agendamentos de serviços.

**Metadados principais**:
- `appointment_client_id`: ID do cliente
- `appointment_pet_ids`: array serializado de IDs de pets
- `appointment_date`: data do agendamento (YYYY-MM-DD)
- `appointment_time`: horário do agendamento (HH:MM)
- `appointment_status`: status (agendado, concluído, cancelado)
- `appointment_notes`: observações

**Capabilities**: `dps_manage_appointments`

**Relacionamento**: 
- N agendamentos : 1 cliente
- N agendamentos : N pets (multi-pet)

### Tabelas customizadas

#### `{prefix}dps_logs`
Armazena logs de eventos do sistema.

**Campos**:
- `id`: identificador único
- `timestamp`: data/hora do evento
- `level`: nível do log (info, warning, error)
- `message`: mensagem descritiva
- `context`: dados adicionais (JSON)

**Uso**: Sistema de logging centralizado acessível via classe `DPS_Logger`.

### Options armazenadas

- `dps_logger_db_version`: versão do schema da tabela de logs (controle de migrações)

## Como usar (visão funcional)

### Para administradores

1. **Acessar o painel**:
   - Crie uma página WordPress e adicione o shortcode `[dps_base]`
   - Acesse a página criada (requer permissões adequadas)

2. **Cadastrar clientes**:
   - Clique na aba "Clientes"
   - Preencha nome, telefone, e-mail e endereço
   - Clique em "Salvar Cliente"

3. **Cadastrar pets**:
   - Clique na aba "Pets"
   - Selecione o cliente proprietário
   - Preencha dados do pet (nome, espécie, raça, porte)
   - Clique em "Salvar Pet"

4. **Criar agendamento**:
   - Clique na aba "Agendamentos"
   - Selecione cliente e pets
   - Defina data, horário e observações
   - Clique em "Salvar Agendamento"

5. **Acompanhar histórico**:
   - Clique na aba "Histórico"
   - Visualize todos os agendamentos finalizados
   - Use filtros para refinar a busca
   - Exporte para CSV se necessário

6. **Configurar o sistema**:
   - Crie uma página com o shortcode `[dps_configuracoes]`
   - Acesse para configurar add-ons instalados

### Para recepcionistas

Usuários com role `dps_reception` têm acesso às mesmas funcionalidades, mas sem permissões administrativas avançadas.

## Notas para desenvolvimento

### Convenções e padrões

Este plugin segue rigorosamente as diretrizes documentadas em:
- **[AGENTS.md](../../AGENTS.md)**: regras de desenvolvimento, versionamento SemVer, git-flow, convenções de código, políticas de segurança e fluxo obrigatório de mudanças
- **[ANALYSIS.md](../../ANALYSIS.md)**: detalhes arquiteturais, mapa completo de hooks, descrição de helpers globais, integrações entre núcleo e add-ons

### Fluxo obrigatório para mudanças

Qualquer desenvolvedor que modifique este plugin DEVE seguir o fluxo:

1. **Ler ANALYSIS.md** antes de começar para entender arquitetura e hooks
2. **Implementar** seguindo convenções de AGENTS.md (segurança, performance, nomenclatura)
3. **Atualizar ANALYSIS.md** se mudar hooks, fluxos de integração ou estrutura de dados
4. **Atualizar CHANGELOG.md** antes de criar tags de release
5. **Validar consistência** entre ANALYSIS.md, AGENTS.md e CHANGELOG.md

### Políticas de segurança obrigatórias

- ✅ Nonces obrigatórios em todos os formulários (`dps_nonce`)
- ✅ Capabilities verificadas antes de ações sensíveis
- ✅ Sanitização de entrada (`sanitize_text_field`, `sanitize_email`)
- ✅ Escape de saída (`esc_html`, `esc_attr`, `esc_url`)
- ✅ Prepared statements em queries customizadas
- ❌ NUNCA armazenar segredos em código (use constantes ou variáveis de ambiente)

### Helpers globais disponíveis

Antes de criar nova lógica, verifique se já existe helper adequado:
- Valores monetários? Use `DPS_Money_Helper`
- Construir URLs de ação? Use `DPS_URL_Builder`
- Fazer queries de CPTs? Use `DPS_Query_Helper`
- Validar formulários? Use `DPS_Request_Validator`

Consulte exemplos práticos em `includes/refactoring-examples.php`.

### Criando novos pontos de extensão

Ao adicionar novos hooks:
1. Use prefixo `dps_` em todos os nomes
2. Documente assinatura completa em ANALYSIS.md (parâmetros, tipo de retorno, propósito)
3. Adicione exemplo de uso em ANALYSIS.md
4. Registre no CHANGELOG.md na categoria "Added"
5. Mantenha retrocompatibilidade; nunca altere assinaturas de hooks existentes

### Modificando esquemas de dados

Ao alterar CPTs ou tabelas:
1. Crie migração reversível
2. Documente impacto em todos os add-ons que usam os dados
3. Atualize versão de schema em options
4. Registre no CHANGELOG.md com instruções de migração se necessário

## Histórico de mudanças (resumo)

### Principais marcos

- **v1.0.1**: Criadas classes helper (DPS_Money_Helper, DPS_URL_Builder, DPS_Query_Helper, DPS_Request_Validator) para padronizar operações comuns e melhorar manutenibilidade
- **v1.0.0**: Lançamento inicial com CPTs de clientes, pets e agendamentos, sistema de navegação por abas, hooks de extensão para add-ons e helpers de registro de CPTs

### Documentação adicional

- Documento de análise de refatoração em `../../docs/refactoring/REFACTORING_ANALYSIS.md` com problemas conhecidos de código e sugestões de melhoria
- Exemplos práticos de refatoração em `includes/refactoring-examples.php`
- Padrões de desenvolvimento de add-ons detalhados na seção correspondente de `../../ANALYSIS.md`

Para o histórico completo de mudanças, consulte `../../CHANGELOG.md` na raiz do repositório.
