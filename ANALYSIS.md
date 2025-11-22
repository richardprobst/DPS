# Análise funcional do Desi Pet Shower

## Plugin base (`plugin/desi-pet-shower-base_plugin`)
- O arquivo principal declara constantes globais, registra os *custom post types* de clientes, pets e agendamentos, carrega os ativos do frontend e expõe os *shortcodes* `[dps_base]` e `[dps_configuracoes]`, que servem como ponto de entrada para o painel e para a tela de configurações consumida pelos add-ons.
- `includes/class-dps-cpt-helper.php` centraliza o registro de CPTs com rótulos e argumentos padrão; novos tipos devem instanciar `DPS_CPT_Helper` para herdar as opções comuns e apenas sobrescrever particularidades (ex.: capabilities customizadas ou suporte a editor).
- **NOTA**: Os CPTs principais (`dps_cliente`, `dps_pet`, `dps_agendamento`) atualmente possuem `show_ui => false`, operando apenas via shortcode `[dps_base]`. Para análise completa sobre habilitação da interface admin nativa do WordPress para estes CPTs, consulte `docs/admin/ADMIN_CPT_INTERFACE_ANALYSIS.md` e `docs/admin/ADMIN_CPT_INTERFACE_SUMMARY.md`.
- A classe `DPS_Base_Frontend` concentra a lógica de interface: normaliza telefones para WhatsApp, agrega dados de agendamentos multi-pet para cobranças conjuntas, monta botões de cobrança, controla salvamento/exclusão de clientes, pets e atendimentos e renderiza as abas consumidas pelos add-ons via *hooks* (`dps_base_nav_tabs_after_pets`, `dps_base_nav_tabs_after_history`, `dps_settings_nav_tabs`, etc.).
- O fluxo de formulários usa `dps_nonce` para CSRF e delega ações específicas (`save_client`, `save_pet`, `save_appointment`, `update_appointment_status`) para métodos especializados, enquanto exclusões limpam também dados financeiros relacionados quando disponíveis.
- A exclusão de agendamentos dispara o hook `dps_finance_cleanup_for_appointment`, permitindo que add-ons financeiros tratem a remoção de lançamentos vinculados sem depender de SQL no núcleo.
- A criação de tabelas do núcleo (ex.: `dps_logs`) é registrada no `register_activation_hook` e versionada via option `dps_logger_db_version`. Caso a flag de versão não exista ou esteja desatualizada, `dbDelta` é chamado uma única vez em `plugins_loaded` para alinhar o esquema, evitando consultas de verificação em todos os ciclos de `init`.

### Helpers globais do núcleo

O plugin base oferece classes utilitárias para padronizar operações comuns e evitar duplicação de lógica. Estes helpers estão disponíveis em `plugin/desi-pet-shower-base_plugin/includes/` e podem ser usados tanto pelo núcleo quanto pelos add-ons.

#### DPS_Money_Helper
**Propósito**: Manipulação consistente de valores monetários com conversão entre formato brasileiro e centavos.

**Entrada/Saída**:
- `parse_brazilian_format( string )`: Converte string BR (ex.: "1.234,56") → int centavos (123456)
- `format_to_brazilian( int )`: Converte centavos (123456) → string BR ("1.234,56")
- `decimal_to_cents( float )`: Converte decimal (12.34) → int centavos (1234)
- `cents_to_decimal( int )`: Converte centavos (1234) → float decimal (12.34)

**Exemplos práticos**:
```php
// Validar e converter valor do formulário para centavos
$preco_raw = isset( $_POST['preco'] ) ? sanitize_text_field( $_POST['preco'] ) : '';
$valor_centavos = DPS_Money_Helper::parse_brazilian_format( $preco_raw );

// Exibir valor formatado na tela
echo 'R$ ' . DPS_Money_Helper::format_to_brazilian( $valor_centavos );
```

**Boas práticas**: Use sempre este helper para conversões monetárias. Evite lógica duplicada de `str_replace` e `number_format` espalhada pelo código.

#### DPS_URL_Builder
**Propósito**: Construção padronizada de URLs de ação (edição, exclusão, visualização, navegação entre abas).

**Entrada/Saída**:
- `build_edit_url( int $post_id, string $tab )`: Gera URL de edição com nonce
- `build_delete_url( int $post_id, string $action, string $tab )`: Gera URL de exclusão com nonce
- `build_view_url( int $post_id, string $tab )`: Gera URL de visualização
- `build_tab_url( string $tab_name )`: Gera URL de navegação entre abas

**Exemplos práticos**:
```php
// Gerar link de edição de cliente
$url_editar = DPS_URL_Builder::build_edit_url( $client_id, 'clientes' );

// Gerar link de exclusão de agendamento com confirmação
$url_excluir = DPS_URL_Builder::build_delete_url( $appointment_id, 'delete_appointment', 'historico' );
```

**Boas práticas**: Centralize geração de URLs neste helper para garantir nonces consistentes e evitar links quebrados.

#### DPS_Query_Helper
**Propósito**: Consultas WP_Query reutilizáveis com filtros comuns, paginação e otimizações de performance.

**Entrada/Saída**:
- `get_all_posts_by_type( string $post_type, array $args )`: Retorna posts com argumentos otimizados
- `get_paginated_posts( string $post_type, int $per_page, int $paged, array $args )`: Retorna posts paginados
- `count_posts_by_status( string $post_type, string $status )`: Conta posts por status

**Exemplos práticos**:
```php
// Buscar todos os clientes ativos
$clientes = DPS_Query_Helper::get_all_posts_by_type( 'dps_client', [
    'post_status' => 'publish',
    'orderby' => 'title',
    'order' => 'ASC'
] );

// Buscar agendamentos paginados
$agendamentos = DPS_Query_Helper::get_paginated_posts( 'dps_appointment', 20, $paged );
```

**Boas práticas**: Use `fields => 'ids'` quando precisar apenas de IDs, e pré-carregue metadados com `update_meta_cache()` quando precisar de metas.

#### DPS_Request_Validator
**Propósito**: Validação centralizada de nonces, capabilities e sanitização de campos de formulário.

**Entrada/Saída**:
- `verify_nonce_and_capability( string $nonce_field, string $capability )`: Valida nonce e permissão
- `sanitize_text_field_from_post( string $field_name, string $default )`: Sanitiza campo de texto
- `sanitize_email_from_post( string $field_name )`: Sanitiza e valida email
- `sanitize_int_from_post( string $field_name, int $default )`: Sanitiza inteiro

**Exemplos práticos**:
```php
// Validar requisição antes de processar formulário
if ( ! DPS_Request_Validator::verify_nonce_and_capability( 'dps_nonce', 'edit_posts' ) ) {
    wp_die( 'Acesso negado.' );
}

// Sanitizar campos do formulário
$nome = DPS_Request_Validator::sanitize_text_field_from_post( 'client_name', '' );
$email = DPS_Request_Validator::sanitize_email_from_post( 'client_email' );
```

**Boas práticas**: NUNCA implemente validação de nonce ou sanitização manual fora deste helper. Evite duplicar lógica de segurança.

#### DPS_Message_Helper
**Propósito**: Gerenciamento de mensagens de feedback visual (sucesso, erro, aviso) para operações administrativas.

**Entrada/Saída**:
- `add_success( string $message )`: Adiciona mensagem de sucesso
- `add_error( string $message )`: Adiciona mensagem de erro
- `add_warning( string $message )`: Adiciona mensagem de aviso
- `display_messages()`: Retorna HTML com todas as mensagens pendentes e as remove automaticamente

**Exemplos práticos**:
```php
// Após salvar cliente com sucesso
DPS_Message_Helper::add_success( __( 'Cliente salvo com sucesso!', 'desi-pet-shower' ) );
wp_safe_redirect( $redirect_url );
exit;

// No início da seção, exibir mensagens pendentes
echo '<div class="dps-section">';
echo DPS_Message_Helper::display_messages(); // Renderiza alertas
echo '<h2>Cadastro de Clientes</h2>';
```

**Boas práticas**: 
- Use mensagens após operações que modificam dados (salvar, excluir, atualizar status)
- Coloque `display_messages()` no início de cada seção do painel para feedback imediato
- Mensagens são armazenadas via transients específicos por usuário, garantindo isolamento
- Mensagens são exibidas apenas uma vez (single-use) e removidas automaticamente após renderização

### Feedback visual e organização de interface
- Todos os formulários principais (clientes, pets, agendamentos) utilizam `DPS_Message_Helper` para feedback após salvar ou excluir
- Formulários são organizados em fieldsets semânticos com bordas sutis (`1px solid #e5e7eb`) e legends descritivos
- Hierarquia de títulos padronizada: H1 único no topo ("Painel de Gestão DPS"), H2 para seções principais, H3 para subseções
- Design minimalista com paleta reduzida: base neutra (#f9fafb, #e5e7eb, #374151) + 3 cores de status essenciais (verde, amarelo, vermelho)
- Responsividade básica implementada com media queries para mobile (480px), tablets (768px) e desktops pequenos (1024px)


### Histórico e exportação de agendamentos
- A coleta de atendimentos finalizados é feita em lotes pelo `WP_Query` com `fields => 'ids'`, `no_found_rows => true` e tamanho configurável via filtro `dps_history_batch_size` (padrão: 200). Isso evita uma única consulta gigante em tabelas volumosas e permite tratar listas grandes de forma incremental.
- As metas dos agendamentos são pré-carregadas com `update_meta_cache('post')` antes do loop, reduzindo consultas repetidas às mesmas linhas durante a renderização e exportação.
- Clientes, pets e serviços relacionados são resolvidos com caches em memória por ID, evitando `get_post` duplicadas quando o mesmo registro aparece em várias linhas.
- O botão de exportação gera CSV apenas com as colunas exibidas e respeita os filtros aplicados na tabela, o que limita o volume exportado a um subconjunto relevante e já paginado/filtrado pelo usuário.

## Add-ons complementares (`add-ons/`)

### Agenda (`desi-pet-shower-agenda_addon`)

**Diretório**: `add-ons/desi-pet-shower-agenda_addon`

**Propósito e funcionalidades principais**:
- Gerenciar agenda de atendimentos e cobranças pendentes
- Enviar lembretes automáticos diários aos clientes
- Atualizar status de agendamentos via interface AJAX

**Shortcodes expostos**:
- `[dps_agenda_page]`: renderiza página de agenda com filtros e ações
- `[dps_charges_notes]`: exibe lista de cobranças pendentes

**CPTs, tabelas e opções**:
- Não cria CPTs próprios; consome `dps_appointment` do núcleo
- Cria páginas automaticamente: "Agenda DPS" e "Cobranças DPS"
- Options: `dps_agenda_page_id`, `dps_charges_page_id`

**Hooks consumidos**:
- Nenhum hook específico do núcleo (opera diretamente sobre CPTs)

**Hooks disparados**:
- `dps_agenda_send_reminders`: cron job diário para envio de lembretes

**Dependências**:
- Depende do plugin base para CPTs de agendamento
- Integra-se com add-on de Comunicações para envio de mensagens (se ativo)

**Introduzido em**: v0.1.0 (estimado)

**Assets**:
- `assets/js/agenda-addon.js`: interações AJAX e feedback visual

**Observações**:
- Implementa `register_deactivation_hook` para limpar cron job `dps_agenda_send_reminders` ao desativar

---

### Backup & Restauração (`desi-pet-shower-backup_addon`)

**Diretório**: `add-ons/desi-pet-shower-backup_addon`

**Propósito e funcionalidades principais**:
- Exportar todo o conteúdo do sistema em formato JSON
- Restaurar dados de backups anteriores
- Proteger operações com nonces e validações

**Shortcodes expostos**: Nenhum

**CPTs, tabelas e opções**:
- Não cria CPTs ou tabelas próprias
- Integra-se à navegação de configurações do plugin base

**Hooks consumidos**:
- `dps_settings_nav_tabs`: adiciona aba "Backup & Restauração"
- `dps_settings_sections`: renderiza conteúdo da seção

**Hooks disparados**: Nenhum

**Dependências**:
- Depende do plugin base para estrutura de configurações
- Acessa todos os CPTs do sistema para exportação/importação

**Introduzido em**: v0.1.0 (estimado)

---

### Campanhas & Fidelidade (`desi-pet-shower-loyalty_addon`)

**Diretório**: `add-ons/desi-pet-shower-loyalty_addon`

**Propósito e funcionalidades principais**:
- Gerenciar programa de pontos por faturamento
- Módulo "Indique e Ganhe" com códigos únicos e recompensas
- Criar e executar campanhas de marketing direcionadas

**Shortcodes expostos**: Nenhum

**CPTs, tabelas e opções**:
- CPT: `dps_campaign` (campanhas de marketing)
- Tabela: `dps_referrals` (indicações de clientes)
- Options: configurações de pontos, recompensas e elegibilidade

**Hooks consumidos**:
- `dps_registration_after_client_created`: registra indicações no cadastro público
- `dps_finance_booking_paid`: bonifica indicador/indicado na primeira cobrança paga
- `dps_base_nav_tabs_after_history`: adiciona aba "Campanhas & Fidelidade"
- `dps_base_sections_after_history`: renderiza conteúdo da aba

**Hooks disparados**: Nenhum

**Dependências**:
- Integra-se com add-on Financeiro para bonificações
- Integra-se com add-on de Cadastro Público para capturar códigos de indicação
- Integra-se com Portal do Cliente para exibir código/link de convite

**Introduzido em**: v0.2.0

**Observações**:
- Tabela `dps_referrals` criada via `dbDelta` na ativação
- Oferece funções globais para crédito e resgate de pontos

---

### Comunicações (`desi-pet-shower-communications_addon`)

**Diretório**: `add-ons/desi-pet-shower-communications_addon`

**Propósito e funcionalidades principais**:
- Gerenciar comunicações automatizadas via WhatsApp, SMS e e-mail
- Enviar notificações para eventos do sistema (agendamentos, lembretes, pós-atendimento)
- Personalizar mensagens por tipo de evento

**Shortcodes expostos**: Nenhum

**CPTs, tabelas e opções**:
- Não cria CPTs ou tabelas próprias
- Options: configurações de canais (WhatsApp, SMS, e-mail) e templates de mensagens

**Hooks consumidos**:
- `dps_base_after_save_appointment`: dispara comunicações após salvar agendamento
- `dps_settings_nav_tabs`: adiciona aba "Comunicações"
- `dps_settings_sections`: renderiza configurações de canais e mensagens

**Hooks disparados**:
- `dps_comm_send_appointment_reminder`: cron job para lembretes de agendamento
- `dps_comm_send_post_service`: cron job para mensagens pós-atendimento

**Dependências**:
- Depende do plugin base para estrutura de configurações e hooks de agendamento

**Introduzido em**: v0.1.0 (estimado)

---

### Groomers (`desi-pet-shower-groomers_addon`)

**Diretório**: `add-ons/desi-pet-shower-groomers_addon`

**Propósito e funcionalidades principais**:
- Cadastrar e gerenciar profissionais (groomers)
- Vincular atendimentos a profissionais específicos
- Gerar relatórios de produtividade por profissional

**Shortcodes expostos**: Nenhum

**CPTs, tabelas e opções**:
- Role customizada: `dps_groomer` (profissional de banho e tosa)
- Metadados: `_groomer_id` nos posts de agendamento

**Hooks consumidos**:
- `dps_base_appointment_fields`: adiciona campo de seleção de groomer no formulário de agendamento
- `dps_base_nav_tabs_after_history`: adiciona aba "Groomers"
- `dps_base_sections_after_history`: renderiza cadastro e relatórios

**Hooks disparados**: Nenhum

**Dependências**:
- Depende do plugin base para estrutura de navegação e agendamentos

**Introduzido em**: v0.1.0 (estimado)

**Observações**:
- Arquivo único de 473 linhas; candidato a refatoração futura seguindo padrão de estrutura modular

---

### Portal do Cliente (`desi-pet-shower-client-portal_addon`)

**Diretório**: `add-ons/desi-pet-shower-client-portal_addon`

**Propósito e funcionalidades principais**:
- Fornecer área autenticada para clientes
- Permitir atualização de dados pessoais e de pets
- Exibir histórico de atendimentos e pendências financeiras
- Integrar com módulo "Indique e Ganhe" quando ativo

**Shortcodes expostos**:
- `[dps_client_portal]`: renderiza portal completo do cliente
- `[dps_client_login]`: exibe formulário de login

**CPTs, tabelas e opções**:
- Não cria CPTs ou tabelas próprias
- Usa sessões PHP próprias para autenticação
- Tipos de mensagem customizados para notificações

**Hooks consumidos**:
- `dps_base_nav_tabs_*`: integra abas ao painel base (quando aplicável)
- Hooks do add-on de Pagamentos para links de quitação via Mercado Pago

**Hooks disparados**: Nenhum

**Dependências**:
- Depende do plugin base para CPTs de clientes e pets
- Integra-se com add-on Financeiro para exibir pendências
- Integra-se com add-on de Fidelidade para exibir código de indicação

**Introduzido em**: v0.1.0 (estimado)

**Observações**:
- Já segue padrão modular com estrutura `includes/` e `assets/`
- Gera credenciais de login automaticamente para novos clientes

**Análise de Layout e UX**:
- Consulte `docs/layout/client-portal/CLIENT_PORTAL_UX_ANALYSIS.md` para análise detalhada de usabilidade (800+ linhas)
- Consulte `docs/layout/client-portal/CLIENT_PORTAL_SUMMARY.md` para resumo executivo das melhorias propostas
- Principais achados: estrutura "all-in-one" sem navegação, responsividade precária em mobile, paleta de cores excessiva (15+ cores vs 8 recomendadas), feedback visual ausente
- Melhorias prioritárias documentadas em 3 fases (26.5h totais): navegação interna, cards destacados, tabelas responsivas, feedback visual, redução de paleta, fieldsets em formulários

---

### Financeiro (`desi-pet-shower-finance_addon`)

**Diretório**: `add-ons/desi-pet-shower-finance_addon`

**Propósito e funcionalidades principais**:
- Gerenciar transações financeiras e cobranças
- Sincronizar lançamentos com agendamentos
- Suportar quitação parcial e geração de documentos
- Integrar com outros add-ons para bonificações e assinaturas

**Shortcodes expostos**: Sim (não especificados na documentação atual)

**CPTs, tabelas e opções**:
- Tabela: `dps_transacoes` (lançamentos financeiros)
- Tabela: `dps_parcelas` (parcelas de cobranças)

**Hooks consumidos**:
- `dps_finance_cleanup_for_appointment`: remove lançamentos ao excluir agendamento
- `dps_base_nav_tabs_*`: adiciona aba "Financeiro"
- `dps_base_sections_*`: renderiza seção financeira

**Hooks disparados**:
- `dps_finance_booking_paid`: disparado quando cobrança é marcada como paga

**Dependências**:
- Depende do plugin base para estrutura de navegação
- Fornece infraestrutura para add-ons de Pagamentos, Assinaturas e Fidelidade

**Introduzido em**: v0.1.0

**Observações**:
- Já segue padrão modular com classes auxiliares em `includes/`
- Tabela compartilhada por múltiplos add-ons; mudanças de schema requerem migração cuidadosa

---

### Pagamentos (`desi-pet-shower-payment_addon`)

**Diretório**: `add-ons/desi-pet-shower-payment_addon`

**Propósito e funcionalidades principais**:
- Integrar com Mercado Pago para geração de links de pagamento
- Processar notificações de webhook para atualização de status
- Injetar mensagens de cobrança no WhatsApp via add-on de Agenda

**Shortcodes expostos**: Nenhum

**CPTs, tabelas e opções**:
- Options: `dps_mp_access_token`, `dps_pix_key` (credenciais Mercado Pago)

**Hooks consumidos**:
- Processa webhooks cedo no ciclo de inicialização do WordPress
- Integra-se com add-on Financeiro para gerar cobranças

**Hooks disparados**: Nenhum

**Dependências**:
- Depende do add-on Financeiro para criar transações
- Integra-se com add-on de Agenda para envio de links via WhatsApp

**Introduzido em**: v0.1.0 (estimado)

**Observações**:
- Validação de webhook aplicada apenas quando requisição traz indicadores de notificação do MP
- Requer token de acesso e chave PIX configurados nas opções

---

### Push Notifications (`desi-pet-shower-push_addon`)

**Diretório**: `add-ons/desi-pet-shower-push_addon`

**Propósito e funcionalidades principais**:
- Agendar e enviar notificações recorrentes (agenda diária, relatórios financeiros)
- Integrar com e-mail e Telegram para envio de mensagens
- Filtrar destinatários por critérios configuráveis

**Shortcodes expostos**: Nenhum

**CPTs, tabelas e opções**:
- Options: configurações de destinatários e frequência de notificações

**Hooks consumidos**:
- `dps_settings_nav_tabs`: adiciona aba "Notificações"
- `dps_settings_sections`: renderiza configurações

**Hooks disparados**:
- Múltiplos cron jobs: agenda diária, relatório financeiro diário, relatório semanal de pets inativos
- `dps_send_push_notification`: hook customizado para envio via Telegram

**Dependências**:
- Depende do plugin base para estrutura de configurações

**Introduzido em**: v0.1.0 (estimado)

**Observações**:
- Implementa `register_deactivation_hook` corretamente para limpar cron jobs

---

### Cadastro Público (`desi-pet-shower-registration_addon`)

**Diretório**: `add-ons/desi-pet-shower-registration_addon`

**Propósito e funcionalidades principais**:
- Permitir cadastro público de clientes e pets via formulário web
- Integrar com Google Maps para autocomplete de endereços
- Disparar hook para outros add-ons após criação de cliente

**Shortcodes expostos**:
- `[dps_registration_form]`: renderiza formulário de cadastro público

**CPTs, tabelas e opções**:
- Não cria CPTs próprios; cria posts do tipo `dps_client` e `dps_pet`
- Options: `dps_google_maps_api_key` (chave de API do Google Maps)

**Hooks consumidos**: Nenhum

**Hooks disparados**:
- `dps_registration_after_client_created`: disparado após criar novo cliente

**Dependências**:
- Depende do plugin base para CPTs de cliente e pet
- Integra-se com add-on de Fidelidade para capturar códigos de indicação

**Introduzido em**: v0.1.0 (estimado)

**Observações**:
- Sanitiza todas as entradas antes de criar posts
- Arquivo único de 636 linhas; candidato a refatoração futura

---

### Serviços (`desi-pet-shower-services_addon`)

**Diretório**: `add-ons/desi-pet-shower-services_addon`

**Propósito e funcionalidades principais**:
- Gerenciar catálogo de serviços oferecidos
- Definir preços e duração por porte de pet
- Vincular serviços aos agendamentos
- Povoar catálogo padrão na ativação

**Shortcodes expostos**: Nenhum

**CPTs, tabelas e opções**:
- CPT: `dps_service` (registrado via `DPS_CPT_Helper`)
- Metadados: preços e duração por porte (pequeno, médio, grande)

**Hooks consumidos**:
- `dps_base_nav_tabs_*`: adiciona aba "Serviços"
- `dps_base_sections_*`: renderiza catálogo e formulários
- Hook de agendamento: adiciona campos de seleção de serviços

**Hooks disparados**: Nenhum

**Dependências**:
- Depende do plugin base para estrutura de navegação
- Reutiliza `DPS_CPT_Helper` para registro de CPT

**Introduzido em**: v0.1.0 (estimado)

---

### Estatísticas (`desi-pet-shower-stats_addon`)

**Diretório**: `add-ons/desi-pet-shower-stats_addon`

**Propósito e funcionalidades principais**:
- Exibir métricas de uso do sistema (atendimentos, receita, clientes inativos)
- Listar serviços mais recorrentes
- Filtrar estatísticas por período
- Analisar distribuição de espécies e raças

**Shortcodes expostos**: Nenhum

**CPTs, tabelas e opções**:
- Não cria CPTs ou tabelas próprias
- Consulta `dps_transacoes` para métricas financeiras
- Consulta CPTs do núcleo para métricas operacionais

**Hooks consumidos**:
- `dps_base_nav_tabs_after_history`: adiciona aba "Estatísticas"
- `dps_base_sections_after_history`: renderiza gráficos e listas

**Hooks disparados**: Nenhum

**Dependências**:
- Depende do plugin base para CPTs
- Depende do add-on Financeiro para métricas de receita

**Introduzido em**: v0.1.0 (estimado)

**Observações**:
- Arquivo único de 538 linhas; candidato a refatoração futura

---

### Estoque (`desi-pet-shower-stock_addon`)

**Diretório**: `add-ons/desi-pet-shower-stock_addon`

**Propósito e funcionalidades principais**:
- Controlar estoque de insumos utilizados nos atendimentos
- Registrar movimentações de entrada e saída
- Gerar alertas de estoque baixo
- Baixar estoque automaticamente ao concluir atendimentos

**Shortcodes expostos**: Nenhum

**CPTs, tabelas e opções**:
- CPT: `dps_stock_item` (registrado via `DPS_CPT_Helper`)
- Capability customizada: `dps_manage_stock`
- Metadados: quantidade atual, mínima, histórico de movimentações

**Hooks consumidos**:
- `dps_base_after_save_appointment`: baixa estoque automaticamente ao concluir atendimento
- `dps_base_nav_tabs_after_history`: adiciona aba "Estoque"
- `dps_base_sections_after_history`: renderiza controle de estoque

**Hooks disparados**: Nenhum

**Dependências**:
- Depende do plugin base para estrutura de navegação e hooks de agendamento
- Reutiliza `DPS_CPT_Helper` para registro de CPT

**Introduzido em**: v0.1.0 (estimado)

**Observações**:
- Arquivo único de 432 linhas; candidato a refatoração futura
- Passou a usar navegação integrada ao painel base, removendo menus próprios

---

### Assinaturas (`desi-pet-shower-subscription_addon`)

**Diretório**: `add-ons/desi-pet-shower-subscription_addon`

**Propósito e funcionalidades principais**:
- Gerenciar assinaturas recorrentes de clientes
- Criar e atualizar transações relacionadas a assinaturas
- Gerar links de renovação via Mercado Pago
- Enviar mensagens padrão via WhatsApp para renovações

**Shortcodes expostos**: Nenhum

**CPTs, tabelas e opções**:
- CPT: `dps_subscription` (planos de assinatura)
- Integra-se com tabela `dps_transacoes` do add-on Financeiro

**Hooks consumidos**:
- Hooks do add-on Financeiro para criar transações recorrentes
- Hooks do add-on de Pagamentos para gerar links de renovação

**Hooks disparados**: Nenhum

**Dependências**:
- Depende do add-on Financeiro para gerenciar cobranças
- Depende do add-on de Pagamentos para integração com Mercado Pago

**Introduzido em**: v0.2.0 (estimado)

**Observações**:
- Define UI própria ao painel base
- Integração estreita com fluxo financeiro e de pagamentos

---

## Mapa de hooks

Esta seção consolida os principais hooks expostos pelo núcleo e pelos add-ons, facilitando a integração entre componentes.

### Hooks do plugin base (núcleo)

#### Navegação e seções do painel

- **`dps_base_nav_tabs_after_pets`** (action)
  - **Parâmetros**: nenhum
  - **Propósito**: adicionar abas de navegação após a aba "Pets"
  - **Consumido por**: add-ons que precisam injetar abas customizadas no painel principal

- **`dps_base_nav_tabs_after_history`** (action)
  - **Parâmetros**: nenhum
  - **Propósito**: adicionar abas de navegação após a aba "Histórico"
  - **Consumido por**: Groomers, Estatísticas, Estoque (abas gerenciais)

- **`dps_base_sections_after_pets`** (action)
  - **Parâmetros**: nenhum
  - **Propósito**: renderizar conteúdo de seções customizadas após a seção "Pets"
  - **Consumido por**: add-ons que adicionaram abas via `dps_base_nav_tabs_after_pets`

- **`dps_base_sections_after_history`** (action)
  - **Parâmetros**: nenhum
  - **Propósito**: renderizar conteúdo de seções customizadas após a seção "Histórico"
  - **Consumido por**: Groomers, Estatísticas, Estoque, Campanhas & Fidelidade

- **`dps_settings_nav_tabs`** (action)
  - **Parâmetros**: nenhum
  - **Propósito**: adicionar abas de navegação na página de configurações
  - **Consumido por**: Backup, Comunicações, Push Notifications

- **`dps_settings_sections`** (action)
  - **Parâmetros**: nenhum
  - **Propósito**: renderizar conteúdo de seções na página de configurações
  - **Consumido por**: add-ons que adicionaram abas via `dps_settings_nav_tabs`

#### Fluxo de agendamentos

- **`dps_base_appointment_fields`** (action)
  - **Parâmetros**: `$appointment_id` (int, pode ser 0 para novos agendamentos)
  - **Propósito**: adicionar campos customizados ao formulário de agendamento
  - **Consumido por**: Groomers (seleção de profissional), Serviços (seleção de serviços)

- **`dps_base_after_save_appointment`** (action)
  - **Parâmetros**: `$appointment_id` (int)
  - **Propósito**: executar ações após salvar um agendamento
  - **Consumido por**: Comunicações (envio de notificações), Estoque (baixa automática)

#### Limpeza de dados

- **`dps_finance_cleanup_for_appointment`** (action)
  - **Parâmetros**: `$appointment_id` (int)
  - **Propósito**: remover dados financeiros associados antes de excluir agendamento
  - **Consumido por**: Financeiro (remove transações vinculadas)

### Hooks de add-ons

#### Add-on Financeiro

- **`dps_finance_booking_paid`** (action)
  - **Parâmetros**: `$transaction_id` (int), `$client_id` (int)
  - **Propósito**: disparado quando uma cobrança é marcada como paga
  - **Consumido por**: Campanhas & Fidelidade (bonifica indicador/indicado na primeira cobrança)

#### Add-on de Cadastro Público

- **`dps_registration_after_client_created`** (action)
  - **Parâmetros**: `$client_id` (int), `$referral_code` (string|null)
  - **Propósito**: disparado após criar novo cliente via formulário público
  - **Consumido por**: Campanhas & Fidelidade (registra indicações)

#### Cron jobs de add-ons

- **`dps_agenda_send_reminders`** (action)
  - **Frequência**: diária
  - **Propósito**: enviar lembretes de agendamentos próximos
  - **Registrado por**: Agenda

- **`dps_comm_send_appointment_reminder`** (action)
  - **Frequência**: conforme agendado
  - **Propósito**: enviar lembretes de agendamento via canais configurados
  - **Registrado por**: Comunicações

- **`dps_comm_send_post_service`** (action)
  - **Frequência**: conforme agendado
  - **Propósito**: enviar mensagens pós-atendimento
  - **Registrado por**: Comunicações

- **`dps_send_push_notification`** (action)
  - **Parâmetros**: `$message` (string), `$recipients` (array)
  - **Propósito**: enviar notificações via Telegram ou e-mail
  - **Registrado por**: Push Notifications

---

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
