# Instruções para `add-ons/`

## Escopo
Aplica-se a todos os subdiretórios de `add-ons/`, que representam plugins complementares ao Desi Pet Shower.

## Organização do diretório
- Cada add-on deve manter um arquivo principal `desi-pet-shower-<feature>-addon.php` que registra *hooks*, *shortcodes* e dependências próprias.
- Utilize subpastas apenas quando a funcionalidade exigir classes auxiliares (`includes/`, `dps_service/`, `dps_subscription/`, etc.), mantendo nomes alinhados ao recurso descrito.
- Consulte `ANALYSIS.md` para entender responsabilidades de cada add-on antes de alterar fluxos existentes.

## Diretrizes de desenvolvimento
- Preserve a integração com o plugin base por meio dos *hooks* documentados (`dps_base_nav_tabs_*`, `dps_base_sections_*`, `dps_settings_*`). Evite sobrescrever ações ou filtros do núcleo; complemente-os adicionando novas prioridades quando necessário.
- Prefira prefixar funções, *handles* de scripts e *options* com `dps_` para evitar colisões com outros plugins.
- Ao manipular dados financeiros, reutilize a tabela `dps_transacoes` e siga o contrato atual de campos/metadados compartilhados com o add-on Financeiro.
- Quando adicionar assets (JS/CSS), registre-os com `wp_register_*` e enfileire-os apenas nas páginas relevantes para o add-on.
- Ao criar *custom post types* ou *cron jobs*, mantenha os *slugs* existentes para evitar migrações desnecessárias e documente alterações relevantes no `ANALYSIS.md`.

## Estilo e testes
- Siga as convenções do WordPress (indentação com 4 espaços, funções globais em `snake_case`, métodos em `camelCase` e internacionalização via funções `__()`/`_e()` e equivalentes de `esc_*`).
- Após mudanças relevantes, execute `php -l <arquivo>` nos arquivos alterados e teste o plugin em um ambiente WordPress para garantir que os fluxos principais (agenda, financeiro, assinaturas, etc.) continuem funcionando.

## Documentação
- Se um add-on ganhar novas integrações ou alterar contratos com o plugin base, atualize a respectiva seção em `ANALYSIS.md` e registre requisitos adicionais em um `README.md` local, se necessário.
