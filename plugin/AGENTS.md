# Instruções para `plugin/desi-pet-shower-base_plugin`

## Escopo
Este documento orienta qualquer alteração dentro de `plugin/desi-pet-shower-base_plugin` e seus subdiretórios.

## Organização do código
- `desi-pet-shower-base.php` é o ponto de entrada: carrega dependências de `includes/`, registra *custom post types*, *shortcodes* e *hooks* para os add-ons. Preserve a sequência de `require_once` e o carregamento condicional de assets.
- A classe `DPS_Base_Frontend` concentra a renderização de abas, ações de formulário e integrações compartilhadas. Novas funcionalidades devem ser encapsuladas em métodos claros e reutilizáveis dentro dessa classe ou de novas classes sob `includes/`.
- Consultar `ANALYSIS.md` antes de alterar fluxos principais para garantir compatibilidade com os add-ons.

## Diretrizes de desenvolvimento
- Mantenha os *hooks* públicos (`dps_base_nav_tabs_*`, `dps_base_sections_*`, `dps_settings_*`) com as mesmas assinaturas. Se precisar estender, adicione novos *hooks* sem quebrar os existentes.
- Preserve o uso de *nonces* (`dps_nonce`) e sanitização/escape em todas as ações que manipulam dados do painel.
- Registre e enfileire assets apenas quando necessários para evitar conflitos com outros plugins WordPress.
- Para integrações financeiras, reutilize as funções utilitárias que sincronizam a tabela `dps_transacoes`, respeitando seus campos atuais.

## Estilo e testes
- Siga o padrão de código WordPress: indentação com 4 espaços, nomes de funções/métodos em `snake_case` quando globais e `camelCase` para métodos de classes, e uso consistente de funções de internacionalização/escape (`__()`, `esc_html__()`, `esc_attr()` etc.).
- Execute `php -l <arquivo>` nos arquivos modificados quando possível e valide os fluxos críticos em um ambiente WordPress local.

## Documentação
- Sempre que alterar pontos de integração ou a estrutura da classe base, atualize também `ANALYSIS.md` para manter a visão arquitetural alinhada.
