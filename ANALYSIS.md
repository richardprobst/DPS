# Análise funcional do Desi Pet Shower

## Plugin base (`plugin/desi-pet-shower-base_plugin`)
- O arquivo principal declara constantes globais, registra os *custom post types* de clientes, pets e agendamentos, carrega os ativos do frontend e expõe os *shortcodes* `[dps_base]` e `[dps_configuracoes]`, que servem como ponto de entrada para o painel e para a tela de configurações consumida pelos add-ons.
- `includes/class-dps-cpt-helper.php` centraliza o registro de CPTs com rótulos e argumentos padrão; novos tipos devem instanciar `DPS_CPT_Helper` para herdar as opções comuns e apenas sobrescrever particularidades (ex.: capabilities customizadas ou suporte a editor).
- A classe `DPS_Base_Frontend` concentra a lógica de interface: normaliza telefones para WhatsApp, agrega dados de agendamentos multi-pet para cobranças conjuntas, monta botões de cobrança, controla salvamento/exclusão de clientes, pets e atendimentos e renderiza as abas consumidas pelos add-ons via *hooks* (`dps_base_nav_tabs_after_pets`, `dps_base_nav_tabs_after_history`, `dps_settings_nav_tabs`, etc.).
- O fluxo de formulários usa `dps_nonce` para CSRF e delega ações específicas (`save_client`, `save_pet`, `save_appointment`, `update_appointment_status`) para métodos especializados, enquanto exclusões limpam também dados financeiros relacionados quando disponíveis.
- A exclusão de agendamentos dispara o hook `dps_finance_cleanup_for_appointment`, permitindo que add-ons financeiros tratem a remoção de lançamentos vinculados sem depender de SQL no núcleo.
- A criação de tabelas do núcleo (ex.: `dps_logs`) é registrada no `register_activation_hook` e versionada via option `dps_logger_db_version`. Caso a flag de versão não exista ou esteja desatualizada, `dbDelta` é chamado uma única vez em `plugins_loaded` para alinhar o esquema, evitando consultas de verificação em todos os ciclos de `init`.

### Histórico e exportação de agendamentos
- A coleta de atendimentos finalizados é feita em lotes pelo `WP_Query` com `fields => 'ids'`, `no_found_rows => true` e tamanho configurável via filtro `dps_history_batch_size` (padrão: 200). Isso evita uma única consulta gigante em tabelas volumosas e permite tratar listas grandes de forma incremental.
- As metas dos agendamentos são pré-carregadas com `update_meta_cache('post')` antes do loop, reduzindo consultas repetidas às mesmas linhas durante a renderização e exportação.
- Clientes, pets e serviços relacionados são resolvidos com caches em memória por ID, evitando `get_post` duplicadas quando o mesmo registro aparece em várias linhas.
- O botão de exportação gera CSV apenas com as colunas exibidas e respeita os filtros aplicados na tabela, o que limita o volume exportado a um subconjunto relevante e já paginado/filtrado pelo usuário.

## Add-ons complementares (`add-ons/`)
### Agenda (`desi-pet-shower-agenda_addon`)
- Cria automaticamente páginas de agenda e de cobranças, registra os *shortcodes* `[dps_agenda_page]` e `[dps_charges_notes]`, entrega scripts próprios e implementa fluxos AJAX para atualização de status e inspeção de serviços associados, além de agendar lembretes diários via `dps_agenda_send_reminders`.
- O JavaScript (`agenda-addon.js`) trata interações de status, mensagens de feedback e leitura dos serviços retornados pela API do plugin base.

### Backup & Restauração (`desi-pet-shower-backup_addon`)
- Adiciona a aba "Backup & Restauração" nas configurações do painel principal, gera arquivos JSON com todo o conteúdo e possibilita restauração completa, incluindo salvaguardas com *nonce* e validações de arquivo.

### Campanhas & Fidelidade (`desi-pet-shower-loyalty_addon`)
- Mantém o programa de pontos por faturamento e agora inclui o módulo "Indique e Ganhe" com geração automática de códigos únicos por cliente, tabela dedicada `dps_referrals` criada via `dbDelta` e CRUD auxiliar para registrar indicações e recompensas.
- O módulo integra o cadastro público via `dps_registration_after_client_created` para armazenar indicações válidas e consome o novo hook financeiro `dps_finance_booking_paid` para bonificar indicador/indicado na primeira cobrança paga, aplicando pontos ou créditos conforme configuração administrativa.

### Comunicações (`desi-pet-shower-communications_addon`)
- Gerencia comunicações automatizadas via WhatsApp, SMS e e-mail para eventos do sistema (agendamentos, lembretes, pós-atendimento).
- Registra configurações específicas para cada canal de comunicação e permite personalização de mensagens por tipo de evento.
- Conecta-se aos hooks do plugin base (`dps_base_after_save_appointment`) e agenda tarefas (`dps_comm_send_appointment_reminder`, `dps_comm_send_post_service`) para envio automatizado.
- Exibe suas configurações na navegação padrão do núcleo usando `dps_settings_nav_tabs`/`dps_settings_sections`, em vez de menus próprios no admin.

### Groomers (`desi-pet-shower-groomers_addon`)
- Adiciona cadastro de profissionais (groomers) com papel de usuário dedicado `dps_groomer`.
- Permite vincular atendimentos a profissionais específicos através de campos adicionais no formulário de agendamento via hook `dps_base_appointment_fields`.
- Oferece relatórios por profissional para análise de produtividade e desempenho individual.
- Usa a navegação do painel base via `dps_base_nav_tabs_after_history`/`dps_base_sections_after_history` para cadastro e relatórios, substituindo páginas de menu próprias.

### Portal do Cliente (`desi-pet-shower-client-portal_addon`)
- A inicialização define constantes e instancia `DPS_Client_Portal`, que abre sessões próprias, gera logins para clientes recém-criados, expõe os *shortcodes* `[dps_client_portal]` e `[dps_client_login]`, registra tipos de mensagem e integra suas abas com o painel base.
- O portal permite que clientes atualizem dados próprios/pets e paguem pendências via links do Mercado Pago reutilizando a infraestrutura do add-on de pagamentos, além de exibir um bloco "Indique e Ganhe" com código, link e contagem de indicações bonificadas quando o módulo de fidelidade está ativo.

### Financeiro (`desi-pet-shower-finance_addon`)
- Acrescenta a aba "Financeiro", garante a criação das tabelas `dps_transacoes` e `dps_parcelas`, sincroniza alterações vindas dos agendamentos e oferece formulários para registrar, quitar (inclusive parcialmente) ou excluir transações, além de gerar documentos e shortcodes auxiliares.
- Consome o hook `dps_finance_cleanup_for_appointment` para remover lançamentos associados sempre que um agendamento é excluído, centralizando a limpeza financeira.

### Pagamentos (`desi-pet-shower-payment_addon`)
- Gera links de checkout no Mercado Pago sempre que agendamentos finalizados são salvos, injeta mensagens personalizadas no WhatsApp da agenda e provê tela de configurações com token de acesso e chave PIX, além de tratar notificações de *webhook* cedo no ciclo de inicialização.

### Push Notifications (`desi-pet-shower-push_addon`)
- Agenda tarefas recorrentes (agenda diária, relatório financeiro diário, relatório semanal de pets inativos), renderiza aba de configurações, coleta destinatários por filtros e envia e-mails ou integrações externas via `dps_send_push_notification`/Telegram.

### Cadastro Público (`desi-pet-shower-registration_addon`)
- Cria a página de cadastro público com o shortcode `[dps_registration_form]`, expõe configurações para chave do Google Maps, sanitiza entradas e cadastra clientes/pets vinculados diretamente nos *custom post types* do plugin base.

### Serviços (`desi-pet-shower-services_addon`)
- Registra o *custom post type* `dps_service` via `DPS_CPT_Helper`, injeta abas/seções na interface principal, adiciona campos de seleção de serviços ao agendamento, salva metas auxiliares e povoa o catálogo padrão na ativação, incluindo preços/duração por porte.

### Estatísticas (`desi-pet-shower-stats_addon`)
- Disponibiliza a aba "Estatísticas" com filtros de data, listas de clientes/pets inativos, contagem de atendimentos e serviços mais recorrentes, além de métricas de espécies, raças e receita recente consultando `dps_transacoes`.

### Estoque (`desi-pet-shower-stock_addon`)
- Controla estoque de insumos utilizados nos atendimentos através do *custom post type* `dps_stock_item`, registrado com `DPS_CPT_Helper` e capabilities específicas (`dps_manage_stock`).
- Registra movimentações de entrada e saída de produtos, incluindo baixa automática quando atendimentos são concluídos via hook `dps_base_after_save_appointment`.
- Oferece alertas de estoque baixo e relatórios de consumo por período, além de capability específica `dps_manage_stock` para controle de acesso.
- Passou a renderizar a tela de estoque como aba/seção no painel principal (`dps_base_nav_tabs_after_history`/`dps_base_sections_after_history`), removendo menus próprios no admin.

### Assinaturas (`desi-pet-shower-subscription_addon`)
- Define o *custom post type* `dps_subscription`, adiciona UI própria ao painel, integra-se ao módulo financeiro, cria/atualiza transações relacionadas e gera links de renovação no Mercado Pago com mensagens padrão para WhatsApp.

## Considerações de estrutura e integração
- Todos os add-ons se conectam por meio dos *hooks* expostos pelo plugin base (`dps_base_nav_tabs_after_pets`, `dps_base_sections_after_history`, `dps_settings_*`), preservando a renderização centralizada de navegação/abas feita por `DPS_Base_Frontend`.
- As integrações financeiras compartilham a tabela `dps_transacoes`, seja para sincronizar agendamentos (base + financeiro), gerar cobranças (pagamentos, assinaturas) ou exibir pendências no portal e na agenda, reforçando a necessidade de manter o esquema consistente ao evoluir o sistema.

## Padrões de desenvolvimento de add-ons

### Estrutura de arquivos recomendada
Para novos add-ons ou refatorações futuras, recomenda-se seguir a estrutura modular:

```
add-ons/desi-pet-shower-NOME_addon/
├── desi-pet-shower-NOME-addon.php    # Arquivo principal (apenas bootstrapping)
├── includes/                          # Classes e lógica do negócio
│   ├── class-dps-NOME-cpt.php        # Registro de Custom Post Types
│   ├── class-dps-NOME-metaboxes.php  # Metaboxes e campos customizados
│   ├── class-dps-NOME-admin.php      # Interface administrativa
│   └── class-dps-NOME-frontend.php   # Lógica do frontend
├── assets/                            # Recursos estáticos
│   ├── css/                          # Estilos CSS
│   │   └── NOME-addon.css
│   └── js/                           # Scripts JavaScript
│       └── NOME-addon.js
└── uninstall.php                      # Limpeza de dados na desinstalação
```

**Benefícios desta estrutura:**
- **Separação de responsabilidades**: cada classe tem um propósito claro
- **Manutenibilidade**: mais fácil localizar e modificar funcionalidades específicas
- **Reutilização**: classes podem ser testadas e reutilizadas independentemente
- **Performance**: possibilita carregamento condicional de componentes

**Add-ons que já seguem este padrão:**
- `client-portal_addon`: estrutura bem organizada com `includes/` e `assets/`
- `finance_addon`: possui `includes/` para classes auxiliares

**Add-ons que poderiam se beneficiar de refatoração futura:**
- `groomers_addon`: 473 linhas em um único arquivo
- `stats_addon`: 538 linhas em um único arquivo
- `stock_addon`: 432 linhas em um único arquivo
- `loyalty_addon`: 1148 linhas em um único arquivo
- `registration_addon`: 636 linhas em um único arquivo
- `backup_addon`: 1131 linhas em um único arquivo

### Activation e Deactivation Hooks

**Activation Hook (`register_activation_hook`):**
- Criar páginas necessárias
- Criar tabelas de banco de dados via `dbDelta()`
- Definir opções padrão do plugin
- Criar roles e capabilities customizadas
- **NÃO** agendar cron jobs (use `init` com verificação `wp_next_scheduled`)

**Deactivation Hook (`register_deactivation_hook`):**
- Limpar cron jobs agendados com `wp_clear_scheduled_hook()`
- **NÃO** remover dados do usuário (reservado para `uninstall.php`)

**Exemplo de implementação:**
```php
class DPS_Exemplo_Addon {
    public function __construct() {
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
        
        add_action( 'init', [ $this, 'maybe_schedule_cron' ] );
        add_action( 'dps_exemplo_cron_event', [ $this, 'execute_cron' ] );
    }
    
    public function activate() {
        // Criar páginas, tabelas, opções padrão
        $this->create_pages();
        $this->create_database_tables();
    }
    
    public function deactivate() {
        // Limpar APENAS cron jobs temporários
        wp_clear_scheduled_hook( 'dps_exemplo_cron_event' );
    }
    
    public function maybe_schedule_cron() {
        if ( ! wp_next_scheduled( 'dps_exemplo_cron_event' ) ) {
            wp_schedule_event( time(), 'daily', 'dps_exemplo_cron_event' );
        }
    }
}
```

**Add-ons que usam cron jobs:**
- ✅ `push_addon`: implementa deactivation hook corretamente
- ✅ `agenda_addon`: agora implementa deactivation hook para limpar `dps_agenda_send_reminders`

### Padrões de documentação (DocBlocks)

Todos os métodos devem seguir o padrão WordPress de DocBlocks:

```php
/**
 * Breve descrição do método (uma linha).
 *
 * Descrição mais detalhada explicando o propósito, comportamento
 * e contexto de uso do método (opcional).
 *
 * @since 1.0.0
 *
 * @param string $param1 Descrição do primeiro parâmetro.
 * @param int    $param2 Descrição do segundo parâmetro.
 * @param array  $args {
 *     Argumentos opcionais.
 *
 *     @type string $key1 Descrição da chave 1.
 *     @type int    $key2 Descrição da chave 2.
 * }
 * @return bool Retorna true em caso de sucesso, false caso contrário.
 */
public function exemplo_metodo( $param1, $param2, $args = [] ) {
    // Implementação
}
```

**Elementos obrigatórios:**
- Descrição breve do propósito do método
- `@param` para cada parâmetro, com tipo e descrição
- `@return` com tipo e descrição do valor retornado
- `@since` indicando a versão de introdução (opcional, mas recomendado)

**Elementos opcionais mas úteis:**
- Descrição detalhada para métodos complexos
- `@throws` para exceções que podem ser lançadas
- `@see` para referenciar métodos ou classes relacionadas
- `@link` para documentação externa
- `@global` para variáveis globais utilizadas

**Prioridade de documentação:**
1. Métodos públicos (sempre documentar)
2. Métodos protegidos/privados complexos
3. Hooks e filtros expostos
4. Constantes e propriedades de classe

### Boas práticas adicionais

**Prefixação:**
- Todas as funções globais: `dps_`
- Todas as classes: `DPS_`
- Hooks e filtros: `dps_`
- Options: `dps_`
- Handles de scripts/estilos: `dps-`
- Custom Post Types: `dps_`

**Segurança:**
- Sempre usar nonces em formulários: `wp_nonce_field()` / `wp_verify_nonce()`
- Escapar saída: `esc_html()`, `esc_attr()`, `esc_url()`, `esc_js()`
- Sanitizar entrada: `sanitize_text_field()`, `sanitize_email()`, `wp_kses_post()`
- Verificar capabilities: `current_user_can()`

**Performance:**
- Registrar assets apenas onde necessário
- Usar `wp_register_*` seguido de `wp_enqueue_*` condicionalmente
- Otimizar queries com `fields => 'ids'` quando apropriado
- Pré-carregar metadados com `update_meta_cache()`

**Integração com o núcleo:**
- Preferir hooks do plugin base (`dps_base_*`, `dps_settings_*`) a menus próprios
- Reutilizar classes helper quando disponíveis (`DPS_CPT_Helper`, `DPS_Money_Helper`, etc.)
- Seguir contratos de hooks existentes sem modificar assinaturas
- Documentar novos hooks expostos com exemplos de uso
