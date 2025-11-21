# Diretrizes para agentes do Desi Pet Shower (DPS)

## Escopo
Estas orientações cobrem todo o repositório DPS, incluindo o plugin base em `plugin/` e os complementos em `add-ons/`. Caso exista um `AGENTS.md` mais específico em subdiretórios, ele prevalece para arquivos dentro de seu escopo.

## Estrutura do repositório
- **plugin/**: plugin WordPress principal (`desi-pet-shower-base_plugin`) com ponto de entrada, includes e assets compartilhados.
- **add-ons/**: add-ons opcionais, cada um com arquivo principal próprio e subpastas por funcionalidade.
- **ANALYSIS.md**: visão arquitetural, fluxos de integração e contratos entre núcleo e extensões.
- **CHANGELOG.md**: histórico de versões e lançamentos. Deve ser atualizado em cada release.
- Pastas adicionais podem surgir para ferramentas de build, exemplos ou documentação; mantenha-as descritas nesta seção quando adicionadas.

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

## Convenções de código
- WordPress: indentação de 4 espaços; funções globais em `snake_case`; métodos e propriedades de classe em `camelCase`.
- Escape e sanitização são obrigatórios (`esc_html__`, `esc_attr`, `wp_nonce_*`, `sanitize_text_field`, etc.).
- Não envolva imports em blocos `try/catch` e mantenha require/require_once organizados.
- Scripts e estilos: prefira `wp_register_*` + `wp_enqueue_*` em pontos específicos; evite carregar assets no site inteiro.
- Nomes de hooks, options e handles prefixados com `dps_`.

## Diretrizes para add-ons
- Cada add-on deve manter um arquivo principal `desi-pet-shower-<feature>-addon.php` e, se preciso, subpastas `includes/` ou específicas por domínio.
- Use os hooks de extensão documentados no núcleo (`dps_base_nav_tabs_*`, `dps_base_sections_*`, `dps_settings_*`) sem alterar assinaturas existentes.
- Reutilize a tabela `dps_transacoes` e contratos de metadados para fluxos financeiros ou de assinatura.
- Documente dependências entre add-ons (ex.: Financeiro + Assinaturas) e valide o comportamento conjunto em ambiente de testes.
- Registre assets apenas nas páginas relevantes e considere colisões com temas/plugins instalados.

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

## Boas práticas de revisão e testes
- Execute `php -l <arquivo>` nos arquivos alterados e valide fluxos críticos em ambiente WordPress local.
- Para mudanças de dados ou cron jobs, inclua passos de rollback no PR.
- Revise diffs garantindo consistência com `ANALYSIS.md` e `CHANGELOG.md` antes do merge.

## Contato e conflitos de instruções
- Em caso de conflito entre este documento e um `AGENTS.md` mais específico, siga o de escopo menor e registre a decisão na PR.
- Adicione novos requisitos ou políticas diretamente neste arquivo sempre que expandir o repositório ou os processos.
