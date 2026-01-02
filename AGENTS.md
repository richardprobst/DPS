# Diretrizes para agentes do desi.pet by PRObst

**Autor:** PRObst  
**Site:** [www.probst.pro](https://www.probst.pro)

## Escopo
Estas orientações cobrem todo o repositório desi.pet by PRObst, incluindo o plugin base em `plugin/` e os complementos em `add-ons/`. Caso exista um `AGENTS.md` mais específico em subdiretórios, ele prevalece para arquivos dentro de seu escopo.

## Estrutura do repositório
- **plugin/**: plugin WordPress principal (`desi-pet-shower-base_plugin`) com ponto de entrada, includes e assets compartilhados.
- **add-ons/**: add-ons opcionais, cada um com arquivo principal próprio e subpastas por funcionalidade.
- **docs/**: documentação detalhada de UX, layout, refatoração e planos de implementação (veja `/docs/README.md` para índice completo).
- **ANALYSIS.md**: visão arquitetural, fluxos de integração e contratos entre núcleo e extensões.
- **CHANGELOG.md**: histórico de versões e lançamentos. Deve ser atualizado em cada release.
- **docs/refactoring/REFACTORING_ANALYSIS.md**: análise detalhada de problemas de código conhecidos e padrões de refatoração recomendados.
- **plugin/desi-pet-shower-base_plugin/includes/refactoring-examples.php**: exemplos práticos de uso correto das classes helper globais.
- Pastas adicionais podem surgir para ferramentas de build, exemplos ou documentação; mantenha-as descritas nesta seção quando adicionadas.

## Organização de arquivos

### Arquivos permitidos na raiz do repositório
Apenas os seguintes arquivos devem permanecer na raiz:
- `README.md` - Introdução e visão geral do projeto
- `AGENTS.md` - Diretrizes para agentes (humanos e IA)
- `ANALYSIS.md` - Visão arquitetural do sistema
- `CHANGELOG.md` - Histórico de versões
- `.gitignore` - Configuração de arquivos ignorados pelo Git

### Estrutura da pasta docs/
Toda documentação adicional deve ser organizada nas seguintes subpastas:

| Pasta | Propósito | Exemplos |
|-------|-----------|----------|
| `docs/admin/` | Interface administrativa, CPTs, menus | Análises de UI admin, mockups, habilitação de CPTs |
| `docs/analysis/` | Análises arquiteturais e de sistema | Análises de add-ons, mapeamentos backend/frontend |
| `docs/compatibility/` | Compatibilidade com temas e plugins | YooTheme, Elementor, page builders |
| `docs/fixes/` | Correções e diagnósticos | Fixes de ativação, correções de layout |
| `docs/forms/` | Formulários e inputs | Análises de UX de formulários, melhorias de campos |
| `docs/implementation/` | Resumos de implementação | Sumários de features implementadas |
| `docs/improvements/` | Melhorias gerais | Propostas e análises de melhoria |
| `docs/layout/` | Layout e UX (com subpastas) | `admin/`, `agenda/`, `client-portal/`, `forms/` |
| `docs/performance/` | Otimizações de performance | Análises e guias de performance |
| `docs/refactoring/` | Refatoração de código | Planos, análises, diagramas |
| `docs/review/` | Revisões de código e PRs | Verificações de PRs (ex: `pr-161/`) |
| `docs/security/` | Segurança e auditoria | Correções de segurança, exemplos de vulnerabilidades |
| `docs/visual/` | Estilo visual e design | Guias de estilo, comparações visuais |

### Regras para novos arquivos de documentação
1. **NUNCA** criar arquivos `.md` soltos na raiz do repositório (exceto os 4 permitidos)
2. Identifique a categoria mais apropriada na tabela acima
3. Se nenhuma categoria existente for adequada, crie uma nova subpasta em `docs/` e documente-a aqui
4. Arquivos de revisão de PRs devem ir em `docs/review/pr-XXX/` onde XXX é o número do PR
5. Arquivos de demonstração HTML devem acompanhar a documentação relacionada (ex: demos do portal em `docs/layout/client-portal/`)
6. Mantenha o `docs/README.md` atualizado ao adicionar novas pastas ou categorias

## Versionamento e git-flow
- Utilize SemVer (MAJOR.MINOR.PATCH) para o plugin base e para cada add-on.
- Branches:
  - `main`: sempre estável; somente merges revisados.
  - `develop`: integra funcionalidades antes de promover para release.
  - `feature/<slug-descritivo>`: novas funcionalidades ou ajustes relevantes.
  - `hotfix/<slug-descritivo>`: correções urgentes sobre `main`.
- Releases:
  - Crie tags anotadas (`git tag -a vX.Y.Z`) apenas após atualizar `CHANGELOG.md` e conferir versões em arquivos do plugin.
  - Documente migrações ou passos manuais em `CHANGELOG.md` e, se necessário, em `ANALYSIS.md`.
- Commits devem ser curtos, em português, no imperativo, descrevendo a ação (ex.: "Atualizar checklist de segurança").

## Requisitos mínimos e níveis de regra
- **Versões mínimas**: todos os plugins e add-ons DEVEM declarar `Requires at least: 6.9` e `Requires PHP: 8.4` nos headers, utilizando apenas APIs compatíveis com essas versões.
- **MUST (obrigatório)**:
  - Validar nonce + capability + sanitização/escape em toda entrada/saída (inclui AJAX e REST).
  - Carregar text domain em `init` (prioridade 1) e inicializar classes principais em `init` (prioridade 5) após o text domain.
  - Registrar menus e páginas administrativas sempre como submenus do menu pai `desi-pet-shower` (capability `manage_options`, `admin_menu` prioridade 20); não usar `add_menu_page` próprio nem `parent=null`.
  - Versionar alterações de banco: manter option de versão e executar `dbDelta()` apenas quando a versão salva for menor que a atual (nunca em todo request).
  - Preservar assinaturas de hooks/tabelas compartilhadas; se precisar mudar, criar novo hook e manter compatibilidade com depreciação documentada.
  - Não expor segredos em código; usar constantes ou variáveis de ambiente.
- **PREFER (recomendado)**:
  - Usar helpers globais (`DPS_Phone_Helper`, `DPS_Money_Helper`, `DPS_URL_Builder`, etc.) em vez de duplicar regex, formatadores ou validações.
  - Registrar assets de forma condicional apenas nas páginas/abas relevantes.
  - Usar `show_in_menu => 'desi-pet-shower'` para CPTs que precisam aparecer no admin e otimizar consultas com `fields => 'ids'`/`update_meta_cache()`.
  - Manter `ANALYSIS.md` alinhado ao comportamento real (menus, flags como `show_ui`, hooks e fluxos).
- **ASK BEFORE (requer validação humana)**:
  - Alterar schema de tabelas compartilhadas (`dps_transacoes`, `dps_parcelas`, etc.).
  - Mudanças grandes de UX ou novas dependências externas (APIs/SDKs).
  - Alterar assinaturas de hooks existentes ou fluxos críticos de autenticação.

## Regras de documentação
- Mantenha a documentação em português, clara e orientada a passos.
- Sempre que alterar fluxos de integração, atualize `ANALYSIS.md` e descreva impactos em add-ons.
- `CHANGELOG.md` deve refletir o que chega ao usuário ou integrador; siga a estrutura padrão de categorias.
- Inclua exemplos de uso ou contratos de hooks ao criar novas extensões.
- Use tabelas ou listas para requisitos de ambiente, permissões e dependências externas.
- **Novos add-ons**: ao criar um novo add-on, adicione uma seção descritiva no `ANALYSIS.md` incluindo:
  - Nome do add-on e diretório correspondente
  - Propósito e funcionalidades principais
  - Hooks utilizados ou expostos
  - Dependências de outros add-ons, se aplicável
  - Tabelas de banco de dados criadas ou utilizadas
  - Shortcodes, CPTs ou capabilities adicionados

## Fluxo obrigatório para mudanças

Qualquer agente (humano ou IA) que implemente mudanças no código deve seguir este fluxo:

1. **Ler ANALYSIS.md antes de começar**:
   - Entender o fluxo atual e os hooks utilizados pelo núcleo e add-ons
   - Identificar dependências entre componentes
   - Localizar o add-on ou parte do núcleo afetado
   - Verificar se a estrutura de arquivos segue o padrão recomendado na seção "Padrões de desenvolvimento de add-ons"

2. **Implementar as mudanças**:
   - Seguir as convenções de código descritas neste AGENTS.md (indentação, prefixação, nomenclatura)
   - Aplicar as políticas de segurança obrigatórias (nonces, escape, sanitização, capabilities)
   - Considerar performance (carregamento condicional de assets, otimização de queries)
   - Reutilizar helpers globais quando disponíveis (DPS_Money_Helper, DPS_URL_Builder, etc.)

3. **Atualizar ANALYSIS.md quando necessário**:
   - Mudanças em fluxos de integração ou contratos de hooks
   - Criação ou modificação de estrutura de dados (tabelas, CPTs, metadados)
   - Novos pontos de extensão ou hooks expostos
   - Alteração de assinaturas de hooks existentes (sempre marcar depreciação primeiro)
   - Criação de novos add-ons ou helpers globais

4. **Atualizar CHANGELOG.md antes de criar tags de release**:
   - Adicionar entradas em `[Unreleased]` durante o desenvolvimento
   - Usar categorias apropriadas (Added/Changed/Fixed/Removed/Deprecated/Security/Refactoring)
   - Respeitar SemVer ao determinar se a mudança é MAJOR, MINOR ou PATCH
   - Seguir o "Fluxo de release" descrito no próprio CHANGELOG.md antes de criar tags

5. **Validar consistência entre documentos**:
   - Conferir que ANALYSIS.md, CHANGELOG.md e AGENTS.md estão alinhados
   - Garantir que novos hooks estão documentados em ANALYSIS.md com assinaturas e exemplos
   - Verificar que mudanças de arquitetura estão refletidas em todos os documentos relevantes

## Convenções de código
- WordPress: indentação de 4 espaços; funções globais em `snake_case`; métodos e propriedades de classe em `camelCase`.
- Escape e sanitização são obrigatórios (`esc_html__`, `esc_attr`, `wp_nonce_*`, `sanitize_text_field`, etc.).
- Não envolva imports em blocos `try/catch` e mantenha require/require_once organizados.
- Scripts e estilos: prefira `wp_register_*` + `wp_enqueue_*` em pontos específicos; evite carregar assets no site inteiro.
- Nomes de hooks, options e handles prefixados com `dps_`.

## Diretrizes de estilo visual e interface

O DPS adota um padrão **minimalista/clean** para todas as interfaces administrativas. Novos desenvolvimentos devem seguir estas diretrizes:

### Paleta de cores
- **Base neutra**: `#f9fafb` (fundos), `#e5e7eb` (bordas), `#374151` (texto principal), `#6b7280` (texto secundário)
- **Destaque**: `#0ea5e9` (azul) para ações e links importantes
- **Status** (uso essencial apenas):
  - Verde `#10b981` / `#d1fae5` → sucesso, confirmações, status "pago"
  - Amarelo `#f59e0b` / `#fef3c7` → avisos, status "pendente"
  - Vermelho `#ef4444` → erros críticos, cancelamentos
  - Cinza `#f3f4f6` → neutro, status "finalizado"

### Princípios visuais
- **Menos é mais**: evite sombras decorativas, gradientes, bordas grossas ou elementos puramente estéticos
- **Cores com propósito**: use cores apenas quando comunicam informação (status, tipo de alerta, ação)
- **Espaçamento generoso**: 20px padding em containers, 32px entre seções principais, 40px antes de subseções
- **Bordas padronizadas**: `1px solid #e5e7eb` para separadores sutis, `4px solid [cor]` para bordas laterais de destaque
- **Tipografia limpa**: peso 400 (normal) para texto, 600 (semibold) para títulos, tamanhos 24px (H1), 20px (H2), 18px (H3)

### Estrutura de formulários e seções
- **Hierarquia semântica**: H1 único por página, H2 para seções principais, H3 para subseções
- **Agrupamento lógico**: use `<fieldset>` com `<legend>` para organizar campos relacionados (ex.: Dados Pessoais, Contato, Endereço)
- **Feedback visual obrigatório**: use `DPS_Message_Helper` para mensagens de sucesso/erro/aviso em todas as operações
- **Responsividade básica**: media queries em 480px, 768px e 1024px para adaptar tabelas, grids e navegação

**Referências completas**:
- `docs/visual/VISUAL_STYLE_GUIDE.md`: guia detalhado de cores, tipografia e componentes
- `docs/layout/admin/ADMIN_LAYOUT_ANALYSIS.md`: análise de usabilidade e padrões de layout
- `docs/implementation/UI_UX_IMPROVEMENTS_SUMMARY.md`: resumo de melhorias implementadas



## Diretrizes para add-ons
- Cada add-on deve manter um arquivo principal `desi-pet-shower-<feature>-addon.php` e, se preciso, subpastas `includes/` ou específicas por domínio.
- Use os hooks de extensão documentados no núcleo (`dps_base_nav_tabs_*`, `dps_base_sections_*`, `dps_settings_*`) sem alterar assinaturas existentes.
- Reutilize a tabela `dps_transacoes` e contratos de metadados para fluxos financeiros ou de assinatura.
- Documente dependências entre add-ons (ex.: Financeiro + Assinaturas) e valide o comportamento conjunto em ambiente de testes.
- Registre assets apenas nas páginas relevantes e considere colisões com temas/plugins instalados.
- Menus/admin pages de add-ons devem sempre ser submenus de `desi-pet-shower`; evite páginas ocultas (`parent=null`) e menus de topo próprios.
- Para detalhes de estrutura de arquivos recomendada, cron hooks e prioridades de refatoração, consulte a seção "Padrões de desenvolvimento de add-ons" no **ANALYSIS.md**.

## Recursos para refatoração

O repositório mantém recursos específicos para orientar refatorações de código:

### docs/refactoring/REFACTORING_ANALYSIS.md
- Fonte oficial de problemas conhecidos de código (funções muito grandes, nomes pouco descritivos, duplicação)
- Identifica candidatos prioritários para refatoração com métricas objetivas (linhas de código, complexidade)
- Sugere versões refatoradas com nomes melhores e quebra em métodos menores
- Deve ser consultado antes de iniciar refatorações significativas

### plugin/desi-pet-shower-base_plugin/includes/refactoring-examples.php
- Coleção de exemplos práticos de uso correto das classes helper globais
- Demonstra padrões de refatoração recomendados (conversão de valores monetários, construção de URLs, validação de requisições)
- Mostra comparações "antes/depois" para ilustrar melhorias de código
- Use como referência ao refatorar código existente ou criar novos componentes

**Quando usar esses recursos**:
- Antes de refatorar funções grandes identificadas no `docs/refactoring/REFACTORING_ANALYSIS.md`
- Ao criar novos formulários ou fluxos que precisem de validação/sanitização
- Sempre que precisar manipular valores monetários, construir URLs ou fazer queries otimizadas
- Ao revisar pull requests que introduzem helpers ou padrões novos

## Liberdade x segurança

### O que o agente está autorizado a fazer

O agente tem liberdade para melhorar o código dentro dos seguintes limites:

- ✅ **Quebrar funções grandes em métodos menores**: seguir sugestões do `docs/refactoring/REFACTORING_ANALYSIS.md`
- ✅ **Extrair helpers reutilizáveis**: centralizar lógica duplicada em classes utilitárias
- ✅ **Melhorar DocBlocks e nomenclatura**: tornar código mais legível e autodocumentado
- ✅ **Aderir à estrutura proposta de add-ons**: reorganizar arquivos seguindo padrão modular de `includes/` e `assets/`
- ✅ **Otimizar queries**: usar `fields => 'ids'`, `no_found_rows`, `update_meta_cache()` quando apropriado
- ✅ **Refatorar lógica de formulários**: usar `DPS_Request_Validator` para nonces/sanitização, `DPS_Money_Helper` para valores monetários
- ✅ **Adicionar hooks novos**: desde que documentados em ANALYSIS.md com assinatura, propósito e exemplos
- ✅ **Melhorar segurança**: reforçar validações, escape e sanitização conforme políticas deste documento

### O que o agente NÃO deve fazer sem documentação e validação extra

As seguintes ações requerem cuidado especial e devem ser acompanhadas de documentação detalhada:

- ❌ **Alterar schema de tabelas compartilhadas** (ex.: `dps_transacoes`, `dps_parcelas`) sem:
  - Criar migração reversível
  - Documentar impacto em todos os add-ons que usam a tabela
  - Validar sincronização entre plugins

- ❌ **Remover hooks existentes** sem:
  - Marcar depreciação no CHANGELOG.md com versão alvo de remoção
  - Manter retrocompatibilidade por pelo menos uma versão MINOR
  - Notificar todos os add-ons que consomem o hook

- ❌ **Afrouxar validações de segurança** (nonces, capabilities, sanitização):
  - Sempre reforçar, nunca remover validações
  - Qualquer mudança em validação de webhooks deve ser auditada

- ❌ **Mudar assinaturas de hooks existentes** (número/tipo de parâmetros):
  - Criar novo hook com nova assinatura
  - Depreciar hook antigo mantendo retrocompatibilidade
  - Documentar migração no CHANGELOG.md

- ❌ **Remover ou modificar capabilities existentes**:
  - Pode quebrar controle de acesso de add-ons
  - Requer análise de impacto em todos os componentes

**Princípio geral**: em caso de dúvida sobre impacto de uma mudança, prefira adicionalidade (criar novo em vez de modificar existente) e sempre documente extensivamente.

## Integração núcleo ⇄ extensões
- Novos pontos de extensão no núcleo devem vir acompanhados de documentação mínima (assinatura, propósito, exemplos) no `ANALYSIS.md`.
- Mantenha compatibilidade retroativa: introduza novos hooks sem quebrar os existentes; marque depreciações no `CHANGELOG.md` com versão alvo.
- Para fluxos compartilhados (agendamento, pagamentos, notificações), centralize lógica em classes utilitárias no núcleo e reutilize-as nos add-ons.
- Ao alterar esquemas de dados compartilhados, inclua migrações reversíveis e valide a sincronização entre plugins.

## Políticas de segurança obrigatórias
- Nonces obrigatórios em formulários e ações autenticadas; rejeite requisições sem verificação.
- Escape de saída em HTML, atributos e JS inline; sanitize toda entrada do usuário, inclusive parâmetros de webhooks.
- Princípio do menor privilégio para capabilities (`manage_options`, `edit_posts`, etc.).
- Armazene segredos apenas via constantes ou variáveis de ambiente; não commitar chaves ou tokens.
- Sempre registrar correções de segurança na categoria "Security (Segurança)" do changelog.

## Setup & validação antes de abrir PR
- Ambiente local: utilize o ambiente oficial do projeto (ex.: `docker compose up` ou `wp-env start` se disponível). Caso não exista automação, descreva no PR como validou manualmente.
- Dependências: `composer install` e `npm ci` (quando houver build de assets).
- Checks mínimos:
  - `php -l <arquivos alterados>`
  - `phpcs` (se configurado no repositório)
  - Testes automatizados disponíveis (ex.: `phpunit`, `npm test`, `npm run build`/`npm run lint` se aplicável)
- Fallback: se algum comando não estiver disponível no ambiente, registre no PR que o check não pôde ser executado e descreva a validação manual equivalente.

## Definition of Done (checklist rápido)
- [ ] Text domain carregado em `init` (prioridade 1) e classes principais inicializadas em `init` (prioridade 5).
- [ ] Menus/admin pages registrados como submenus de `desi-pet-shower` (sem `parent=null` ou menus de topo próprios).
- [ ] Nonce + capability + sanitização/escape aplicados em todos os fluxos tocados.
- [ ] `dbDelta()` protegido por option de versão, executando apenas quando necessário (nunca em todo request).
- [ ] `ANALYSIS.md` atualizado ao alterar fluxos/menus/hooks/flags; `CHANGELOG.md` atualizado para mudanças user-facing.
- [ ] Checks de lint/teste rodados ou limitações documentadas no PR.

## Boas práticas de revisão e testes
- Execute `php -l <arquivo>` nos arquivos alterados e valide fluxos críticos em ambiente WordPress local.
- Para mudanças de dados ou cron jobs, inclua passos de rollback no PR.
- Revise diffs garantindo consistência com `ANALYSIS.md` e `CHANGELOG.md` antes do merge.

## Contato e conflitos de instruções
- Em caso de conflito entre este documento e um `AGENTS.md` mais específico, siga o de escopo menor e registre a decisão na PR.
- Adicione novos requisitos ou políticas diretamente neste arquivo sempre que expandir o repositório ou os processos.
