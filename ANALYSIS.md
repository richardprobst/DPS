# AnÃ¡lise funcional do desi.pet by PRObst

## Plugin base (`plugins/desi-pet-shower-base`)
- O arquivo principal declara constantes globais, registra os *custom post types* de clientes, pets e agendamentos, carrega os ativos do frontend e expÃµe os *shortcodes* `[dps_base]` e `[dps_configuracoes]`, que servem como ponto de entrada para o painel e para a tela de configuraÃ§Ãµes consumida pelos add-ons.
- `includes/class-dps-cpt-helper.php` centraliza o registro de CPTs com rÃ³tulos e argumentos padrÃ£o; novos tipos devem instanciar `DPS_CPT_Helper` para herdar as opÃ§Ãµes comuns e apenas sobrescrever particularidades (ex.: capabilities customizadas ou suporte a editor).
- **NOTA**: Os CPTs principais (`dps_cliente`, `dps_pet`, `dps_agendamento`) estÃ£o registrados com `show_ui => true` e `show_in_menu => false`, sendo exibidos pelo painel central e reutilizÃ¡veis pelos add-ons via abas. Para anÃ¡lise completa sobre a interface nativa do WordPress para estes CPTs, consulte `docs/analysis/BASE_PLUGIN_DEEP_ANALYSIS.md` e `docs/analysis/ADMIN_MENUS_MAPPING.md`.
- A classe `DPS_Base_Frontend` concentra a lÃ³gica de interface: normaliza telefones para WhatsApp, agrega dados de agendamentos multi-pet para cobranÃ§as conjuntas, monta botÃµes de cobranÃ§a, controla salvamento/exclusÃ£o de clientes, pets e atendimentos e renderiza as abas consumidas pelos add-ons via *hooks* (`dps_base_nav_tabs_after_pets`, `dps_base_nav_tabs_after_history`, etc.).
- A classe `DPS_Settings_Frontend` gerencia a pÃ¡gina de configuraÃ§Ãµes (`[dps_configuracoes]`) com sistema moderno de registro de abas via `register_tab()`. Os hooks legados `dps_settings_nav_tabs` e `dps_settings_sections` foram depreciados em favor do sistema moderno que oferece melhor consistÃªncia visual. A pÃ¡gina inclui assets dedicados (`dps-settings.css` e `dps-settings.js`) carregados automaticamente, com suporte a navegaÃ§Ã£o client-side entre abas, busca em tempo real de configuraÃ§Ãµes com destaque visual, barra de status contextual e detecÃ§Ã£o de alteraÃ§Ãµes nÃ£o salvas com aviso ao sair.
- O fluxo de formulÃ¡rios usa `dps_nonce` para CSRF e delega aÃ§Ãµes especÃ­ficas (`save_client`, `save_pet`, `save_appointment`, `update_appointment_status`) para mÃ©todos especializados, enquanto exclusÃµes limpam tambÃ©m dados financeiros relacionados quando disponÃ­veis. A classe principal Ã© inicializada no hook `init` com prioridade 5, apÃ³s o carregamento do text domain em prioridade 1.
- A exclusÃ£o de agendamentos dispara o hook `dps_finance_cleanup_for_appointment`, permitindo que add-ons financeiros tratem a remoÃ§Ã£o de lanÃ§amentos vinculados sem depender de SQL no nÃºcleo.
- O filtro `dps_tosa_consent_required` permite ajustar quando o consentimento de tosa com mÃ¡quina Ã© exigido ao salvar agendamentos (parÃ¢metros: `$requires`, `$data`, `$service_ids`).
- A criaÃ§Ã£o de tabelas do nÃºcleo (ex.: `dps_logs`) Ã© registrada no `register_activation_hook` e versionada via option `dps_logger_db_version`. Caso a flag de versÃ£o nÃ£o exista ou esteja desatualizada, `dbDelta` Ã© chamado uma Ãºnica vez em `plugins_loaded` para alinhar o esquema, evitando consultas de verificaÃ§Ã£o em todos os ciclos de `init`.
- **OrganizaÃ§Ã£o do menu admin**: o menu pai `desi-pet-shower` apresenta apenas hubs e itens principais. Um limpador dedicado (`DPS_Admin_Menu_Cleaner`) remove submenus duplicados que jÃ¡ estÃ£o cobertos por hubs (IntegraÃ§Ãµes, Sistema, Ferramentas, Agenda, IA, Portal). As pÃ¡ginas continuam acessÃ­veis via URL direta e pelas abas dos hubs, evitando poluiÃ§Ã£o visual na navegaÃ§Ã£o.

### Helpers globais do nÃºcleo

O plugin base oferece classes utilitÃ¡rias para padronizar operaÃ§Ãµes comuns e evitar duplicaÃ§Ã£o de lÃ³gica. Estes helpers estÃ£o disponÃ­veis em `plugins/desi-pet-shower-base/includes/` e podem ser usados tanto pelo nÃºcleo quanto pelos add-ons.

#### DPS_Money_Helper
**PropÃ³sito**: ManipulaÃ§Ã£o consistente de valores monetÃ¡rios com conversÃ£o entre formato brasileiro e centavos.

**Entrada/SaÃ­da**:
- `parse_brazilian_format( string )`: Converte string BR (ex.: "1.234,56") â†’ int centavos (123456)
- `format_to_brazilian( int )`: Converte centavos (123456) â†’ string BR ("1.234,56")
- `format_currency( int, string $symbol = 'R$ ' )`: Converte centavos â†’ string com sÃ­mbolo ("R$ 1.234,56")
- `format_currency_from_decimal( float, string $symbol = 'R$ ' )`: Converte decimal â†’ string com sÃ­mbolo ("R$ 1.234,56")
- `decimal_to_cents( float )`: Converte decimal (12.34) â†’ int centavos (1234)
- `cents_to_decimal( int )`: Converte centavos (1234) â†’ float decimal (12.34)
- `is_valid_money_string( string )`: Valida se string representa valor monetÃ¡rio â†’ bool

**Exemplos prÃ¡ticos**:
```php
// Validar e converter valor do formulÃ¡rio para centavos
$preco_raw = isset( $_POST['preco'] ) ? sanitize_text_field( $_POST['preco'] ) : '';
$valor_centavos = DPS_Money_Helper::parse_brazilian_format( $preco_raw );

// Exibir valor formatado na tela (com sÃ­mbolo de moeda)
echo DPS_Money_Helper::format_currency( $valor_centavos );
// Resultado: "R$ 1.234,56"

// Para valores decimais (em reais, nÃ£o centavos)
echo DPS_Money_Helper::format_currency_from_decimal( 1234.56 );
// Resultado: "R$ 1.234,56"
```

**Boas prÃ¡ticas**:
- Use `format_currency()` para exibiÃ§Ã£o em interfaces (jÃ¡ inclui "R$ ")
- Use `format_to_brazilian()` quando precisar apenas do valor sem sÃ­mbolo
- Evite lÃ³gica duplicada de `number_format` espalhada pelo cÃ³digo

#### DPS_URL_Builder
**PropÃ³sito**: ConstruÃ§Ã£o padronizada de URLs de aÃ§Ã£o (ediÃ§Ã£o, exclusÃ£o, visualizaÃ§Ã£o, navegaÃ§Ã£o entre abas).

**Entrada/SaÃ­da**:
- `build_edit_url( int $post_id, string $tab )`: Gera URL de ediÃ§Ã£o com nonce
- `build_delete_url( int $post_id, string $action, string $tab )`: Gera URL de exclusÃ£o com nonce
- `build_view_url( int $post_id, string $tab )`: Gera URL de visualizaÃ§Ã£o
- `build_tab_url( string $tab_name )`: Gera URL de navegaÃ§Ã£o entre abas

**Exemplos prÃ¡ticos**:
```php
// Gerar link de ediÃ§Ã£o de cliente
$url_editar = DPS_URL_Builder::build_edit_url( $client_id, 'clientes' );

// Gerar link de exclusÃ£o de agendamento com confirmaÃ§Ã£o
$url_excluir = DPS_URL_Builder::build_delete_url( $appointment_id, 'delete_appointment', 'historico' );
```

**Boas prÃ¡ticas**: Centralize geraÃ§Ã£o de URLs neste helper para garantir nonces consistentes e evitar links quebrados.

#### DPS_Query_Helper
**PropÃ³sito**: Consultas WP_Query reutilizÃ¡veis com filtros comuns, paginaÃ§Ã£o e otimizaÃ§Ãµes de performance.

**Entrada/SaÃ­da**:
- `get_all_posts_by_type( string $post_type, array $args )`: Retorna posts com argumentos otimizados
- `get_paginated_posts( string $post_type, int $per_page, int $paged, array $args )`: Retorna posts paginados
- `count_posts_by_status( string $post_type, string $status )`: Conta posts por status

**Exemplos prÃ¡ticos**:
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

**Boas prÃ¡ticas**: Use `fields => 'ids'` quando precisar apenas de IDs, e prÃ©-carregue metadados com `update_meta_cache()` quando precisar de metas.

#### DPS_Request_Validator
**PropÃ³sito**: ValidaÃ§Ã£o centralizada de nonces, capabilities, requisiÃ§Ãµes AJAX e sanitizaÃ§Ã£o de campos de formulÃ¡rio.

**MÃ©todos principais:**
- `verify_request_nonce( $nonce_field, $nonce_action, $method, $die_on_failure )`: Verifica nonce POST/GET
- `verify_nonce_and_capability( $nonce_field, $nonce_action, $capability )`: Valida nonce e permissÃ£o
- `verify_capability( $capability, $die_on_failure )`: Verifica apenas capability

**MÃ©todos AJAX (Fase 3):**
- `verify_ajax_nonce( $nonce_action, $nonce_field = 'nonce' )`: Verifica nonce AJAX com resposta JSON automÃ¡tica
- `verify_ajax_admin( $nonce_action, $capability = 'manage_options' )`: Verifica nonce + capability para AJAX admin
- `verify_admin_action( $nonce_action, $capability, $nonce_field = '_wpnonce' )`: Verifica nonce de aÃ§Ã£o GET
- `verify_admin_form( $nonce_action, $nonce_field, $capability )`: Verifica nonce de formulÃ¡rio POST
- `verify_dynamic_nonce( $nonce_prefix, $item_id )`: Verifica nonce com ID dinÃ¢mico

**MÃ©todos de resposta:**
- `send_json_error( $message, $code, $status )`: Resposta JSON de erro padronizada
- `send_json_success( $message, $data )`: Resposta JSON de sucesso padronizada

**MÃ©todos auxiliares:**
- `get_post_int( $field_name, $default )`: ObtÃ©m inteiro do POST sanitizado
- `get_post_string( $field_name, $default )`: ObtÃ©m string do POST sanitizada
- `get_get_int( $field_name, $default )`: ObtÃ©m inteiro do GET sanitizado
- `get_get_string( $field_name, $default )`: ObtÃ©m string do GET sanitizada

**Exemplos prÃ¡ticos:**
```php
// Handler AJAX admin simples
public function ajax_delete_item() {
    if ( ! DPS_Request_Validator::verify_ajax_admin( 'dps_delete_item' ) ) {
        return; // Resposta JSON de erro jÃ¡ enviada
    }
    // ... processar aÃ§Ã£o
}

// Verificar nonce com ID dinÃ¢mico
$client_id = absint( $_GET['client_id'] );
if ( ! DPS_Request_Validator::verify_dynamic_nonce( 'dps_delete_client_', $client_id, 'nonce', 'GET' ) ) {
    return;
}

// Validar formulÃ¡rio admin
if ( ! DPS_Request_Validator::verify_admin_form( 'dps_save_settings', 'dps_settings_nonce' ) ) {
    return;
}
```

**Boas prÃ¡ticas**: Use `verify_ajax_admin()` para handlers AJAX admin e `verify_ajax_nonce()` para AJAX pÃºblico. Evite duplicar lÃ³gica de seguranÃ§a.

#### DPS_Phone_Helper
**PropÃ³sito**: FormataÃ§Ã£o e validaÃ§Ã£o padronizada de nÃºmeros de telefone para comunicaÃ§Ãµes (WhatsApp, exibiÃ§Ã£o).

**Entrada/SaÃ­da**:
- `format_for_whatsapp( string $phone )`: Formata telefone para WhatsApp (adiciona cÃ³digo do paÃ­s 55 se necessÃ¡rio) â†’ string apenas dÃ­gitos
- `format_for_display( string $phone )`: Formata telefone para exibiÃ§Ã£o brasileira â†’ string formatada "(11) 98765-4321"
- `is_valid_brazilian_phone( string $phone )`: Valida se telefone brasileiro Ã© vÃ¡lido â†’ bool

**Exemplos prÃ¡ticos**:
```php
// Formatar para envio via WhatsApp
$phone_raw = '(11) 98765-4321';
$whatsapp_number = DPS_Phone_Helper::format_for_whatsapp( $phone_raw );
// Retorna: '5511987654321'

// Formatar para exibiÃ§Ã£o na tela
$phone_stored = '5511987654321';
$phone_display = DPS_Phone_Helper::format_for_display( $phone_stored );
// Retorna: '(11) 98765-4321'

// Validar telefone antes de salvar
if ( ! DPS_Phone_Helper::is_valid_brazilian_phone( $phone_input ) ) {
    DPS_Message_Helper::add_error( 'Telefone invÃ¡lido' );
}
```

**Boas prÃ¡ticas**:
- Use sempre este helper para formataÃ§Ã£o de telefones
- Evite duplicaÃ§Ã£o de lÃ³gica `preg_replace` espalhada entre add-ons
- Integrado com `DPS_Communications_API` para envio automÃ¡tico via WhatsApp
- **IMPORTANTE**: Todas as funÃ§Ãµes duplicadas `format_whatsapp_number()` foram removidas do plugin base e add-ons. Use SEMPRE `DPS_Phone_Helper::format_for_whatsapp()` diretamente

#### DPS_WhatsApp_Helper
**PropÃ³sito**: GeraÃ§Ã£o centralizada de links do WhatsApp com mensagens personalizadas. Introduzida para padronizar criaÃ§Ã£o de URLs do WhatsApp em todo o sistema.

**Constante**:
- `TEAM_PHONE = '5515991606299'`: NÃºmero padrÃ£o da equipe (+55 15 99160-6299)

**Entrada/SaÃ­da**:
- `get_link_to_team( string $message = '' )`: Gera link para cliente â†’ equipe â†’ string URL
- `get_link_to_client( string $client_phone, string $message = '' )`: Gera link para equipe â†’ cliente â†’ string URL ou vazio se invÃ¡lido
- `get_share_link( string $message )`: Gera link de compartilhamento genÃ©rico â†’ string URL
- `get_team_phone()`: ObtÃ©m nÃºmero da equipe configurado â†’ string (formatado)

**MÃ©todos auxiliares para mensagens padrÃ£o**:
- `get_portal_access_request_message( $client_name = '', $pet_name = '' )`: Mensagem padrÃ£o para solicitar acesso
- `get_portal_link_message( $client_name, $portal_url )`: Mensagem padrÃ£o para enviar link do portal
- `get_appointment_confirmation_message( $appointment_data )`: Mensagem padrÃ£o de confirmaÃ§Ã£o de agendamento
- `get_payment_request_message( $client_name, $amount, $payment_url = '' )`: Mensagem padrÃ£o de cobranÃ§a

**Exemplos prÃ¡ticos**:
```php
// Cliente quer contatar a equipe (ex: solicitar acesso ao portal)
$message = DPS_WhatsApp_Helper::get_portal_access_request_message();
$url = DPS_WhatsApp_Helper::get_link_to_team( $message );
echo '<a href="' . esc_url( $url ) . '" target="_blank">Quero acesso</a>';

// Equipe quer contatar cliente (ex: enviar link do portal)
$client_phone = get_post_meta( $client_id, 'client_phone', true );
$portal_url = 'https://exemplo.com/portal?token=abc123';
$message = DPS_WhatsApp_Helper::get_portal_link_message( 'JoÃ£o Silva', $portal_url );
$url = DPS_WhatsApp_Helper::get_link_to_client( $client_phone, $message );
echo '<a href="' . esc_url( $url ) . '" target="_blank">Enviar via WhatsApp</a>';

// Compartilhamento genÃ©rico (ex: foto do pet)
$share_text = 'Olha a foto do meu pet: https://exemplo.com/foto.jpg';
$url = DPS_WhatsApp_Helper::get_share_link( $share_text );
echo '<a href="' . esc_url( $url ) . '" target="_blank">Compartilhar</a>';
```

**ConfiguraÃ§Ã£o**:
- NÃºmero da equipe configurÃ¡vel em: Admin â†’ desi.pet by PRObst â†’ ComunicaÃ§Ãµes
- Option: `dps_whatsapp_number` (padrÃ£o: +55 15 99160-6299)
- Fallback automÃ¡tico para constante `TEAM_PHONE` se option nÃ£o existir
- Filtro disponÃ­vel: `dps_team_whatsapp_number` para customizaÃ§Ã£o programÃ¡tica

**Boas prÃ¡ticas**:
- Use sempre este helper para criar links WhatsApp (nÃ£o construa URLs manualmente)
- Helper formata automaticamente nÃºmeros de clientes usando `DPS_Phone_Helper`
- Sempre escape URLs com `esc_url()` ao exibir em HTML
- Mensagens sÃ£o codificadas automaticamente com `rawurlencode()`
- Retorna string vazia se nÃºmero do cliente for invÃ¡lido (verificar antes de exibir link)

**Locais que usam este helper**:
- Lista de clientes (plugin base)
- Add-on de Agenda (confirmaÃ§Ã£o e cobranÃ§a)
- Add-on de Assinaturas (cobranÃ§a de renovaÃ§Ã£o)
- Add-on de Finance (pendÃªncias financeiras)
- Add-on de Stats (reengajamento de clientes inativos)
- Portal do Cliente (solicitaÃ§Ã£o de acesso, envio de link, agendamento, compartilhamento)

#### DPS_IP_Helper
**PropÃ³sito**: ObtenÃ§Ã£o e validaÃ§Ã£o centralizada de endereÃ§os IP do cliente, com suporte a proxies, CDNs (Cloudflare) e ambientes de desenvolvimento.

**Entrada/SaÃ­da**:
- `get_ip()`: ObtÃ©m IP simples via REMOTE_ADDR â†’ string (IP ou 'unknown')
- `get_ip_with_proxy_support()`: ObtÃ©m IP real atravÃ©s de proxies/CDNs â†’ string (IP ou vazio)
- `get_ip_hash( string $salt )`: ObtÃ©m hash SHA-256 do IP para rate limiting â†’ string (64 caracteres)
- `is_valid_ip( string $ip )`: Valida IPv4 ou IPv6 â†’ bool
- `is_valid_ipv4( string $ip )`: Valida apenas IPv4 â†’ bool
- `is_valid_ipv6( string $ip )`: Valida apenas IPv6 â†’ bool
- `is_localhost( string $ip = null )`: Verifica se Ã© localhost â†’ bool
- `anonymize( string $ip )`: Anonimiza IP para LGPD/GDPR â†’ string

**Exemplos prÃ¡ticos**:
```php
// Obter IP simples para logging
$ip = DPS_IP_Helper::get_ip();

// Obter IP real atravÃ©s de CDN (Cloudflare)
$ip = DPS_IP_Helper::get_ip_with_proxy_support();

// Gerar hash para rate limiting
$hash = DPS_IP_Helper::get_ip_hash( 'dps_login_' );
set_transient( 'rate_limit_' . $hash, $count, HOUR_IN_SECONDS );

// Anonimizar IP para logs de longa duraÃ§Ã£o (LGPD)
$anon_ip = DPS_IP_Helper::anonymize( $ip );
// '192.168.1.100' â†’ '192.168.1.0'
```

**Headers verificados** (em ordem de prioridade):
1. `HTTP_CF_CONNECTING_IP` - Cloudflare
2. `HTTP_X_REAL_IP` - Nginx proxy
3. `HTTP_X_FORWARDED_FOR` - Proxy padrÃ£o (usa primeiro IP da lista)
4. `REMOTE_ADDR` - ConexÃ£o direta

**Boas prÃ¡ticas**:
- Use `get_ip()` para casos simples (logging, auditoria)
- Use `get_ip_with_proxy_support()` quando hÃ¡ CDN/proxy (rate limiting, seguranÃ§a)
- Use `get_ip_hash()` para armazenar referÃªncias de IP sem expor o endereÃ§o real
- Use `anonymize()` para logs de longa duraÃ§Ã£o em compliance com LGPD/GDPR

**Add-ons que usam este helper**:
- Portal do Cliente (autenticaÃ§Ã£o, rate limiting, logs de acesso)
- Add-on de Pagamentos (webhooks, auditoria)
- Add-on de IA (rate limiting do chat pÃºblico)
- Add-on de Finance (auditoria de operaÃ§Ãµes)
- Add-on de Registration (rate limiting de cadastros)

#### DPS_Client_Helper
**PropÃ³sito**: Acesso centralizado a dados de clientes, com suporte a CPT `dps_client` e usermeta do WordPress, eliminando duplicaÃ§Ã£o de cÃ³digo para obtenÃ§Ã£o de telefone, email, endereÃ§o e outros metadados.

**Entrada/SaÃ­da**:
- `get_phone( int $client_id, ?string $source = null )`: ObtÃ©m telefone do cliente â†’ string
- `get_email( int $client_id, ?string $source = null )`: ObtÃ©m email do cliente â†’ string
- `get_whatsapp( int $client_id, ?string $source = null )`: ObtÃ©m WhatsApp (fallback para phone) â†’ string
- `get_name( int $client_id, ?string $source = null )`: ObtÃ©m nome do cliente â†’ string
- `get_display_name( int $client_id, ?string $source = null )`: ObtÃ©m nome para exibiÃ§Ã£o â†’ string
- `get_address( int $client_id, ?string $source = null, string $sep = ', ' )`: ObtÃ©m endereÃ§o formatado â†’ string
- `get_all_data( int $client_id, ?string $source = null )`: ObtÃ©m todos os metadados de uma vez â†’ array
- `has_valid_phone( int $client_id, ?string $source = null )`: Verifica se tem telefone vÃ¡lido â†’ bool
- `has_valid_email( int $client_id, ?string $source = null )`: Verifica se tem email vÃ¡lido â†’ bool
- `get_pets( int $client_id, array $args = [] )`: ObtÃ©m lista de pets do cliente â†’ array
- `get_pets_count( int $client_id )`: Conta pets do cliente â†’ int
- `get_primary_pet( int $client_id )`: ObtÃ©m pet principal â†’ WP_Post|null
- `format_contact_info( int $client_id, ?string $source = null )`: Formata informaÃ§Ãµes de contato â†’ string (HTML)
- `get_for_display( int $client_id, ?string $source = null )`: ObtÃ©m dados formatados para exibiÃ§Ã£o â†’ array
- `search_by_phone( string $phone, bool $exact = false )`: Busca cliente por telefone â†’ int|null
- `search_by_email( string $email )`: Busca cliente por email â†’ int|null

**ParÃ¢metro `$source`**:
- `null` (padrÃ£o): Auto-detecta se Ã© post (`dps_client`) ou user (WordPress user)
- `'post'`: ForÃ§a busca em post_meta
- `'user'`: ForÃ§a busca em usermeta

**Constantes de meta keys**:
- `META_PHONE` = 'client_phone'
- `META_EMAIL` = 'client_email'
- `META_WHATSAPP` = 'client_whatsapp'
- `META_ADDRESS` = 'client_address'
- `META_CITY` = 'client_city'
- `META_STATE` = 'client_state'
- `META_ZIP` = 'client_zip'

**Exemplos prÃ¡ticos**:
```php
// Obter telefone de um cliente (auto-detecta source)
$phone = DPS_Client_Helper::get_phone( $client_id );

// Obter todos os dados de uma vez (mais eficiente)
$data = DPS_Client_Helper::get_all_data( $client_id );
echo $data['name'] . ' - ' . $data['phone'];

// Verificar se tem telefone vÃ¡lido antes de enviar WhatsApp
if ( DPS_Client_Helper::has_valid_phone( $client_id ) ) {
    $whatsapp = DPS_Client_Helper::get_whatsapp( $client_id );
    // ...enviar mensagem
}

// Buscar cliente por telefone
$existing = DPS_Client_Helper::search_by_phone( '11999887766' );
if ( $existing ) {
    // Cliente jÃ¡ existe
}

// Para exibiÃ§Ã£o na UI (jÃ¡ formatado)
$display = DPS_Client_Helper::get_for_display( $client_id );
echo $display['display_name']; // "JoÃ£o Silva" ou "Cliente sem nome"
echo $display['phone_formatted']; // "(11) 99988-7766"
```

**Boas prÃ¡ticas**:
- Use `get_all_data()` quando precisar de mÃºltiplos campos (evita queries repetidas)
- Use `get_for_display()` para dados jÃ¡ formatados para UI
- O helper integra com `DPS_Phone_Helper` automaticamente quando disponÃ­vel
- NÃ£o acesse diretamente `get_post_meta( $id, 'client_phone' )` â€” use o helper para consistÃªncia

**Add-ons que usam este helper**:
- Plugin Base (formulÃ¡rios de cliente, frontend)
- Portal do Cliente (exibiÃ§Ã£o de dados, mensagens)
- Add-on de IA (chat pÃºblico, agendador)
- Add-on de Push (notificaÃ§Ãµes por email/WhatsApp)
- Add-on de Communications (envio de comunicados)
- Add-on de Finance (relatÃ³rios, cobranÃ§as)

#### DPS_Message_Helper
**PropÃ³sito**: Gerenciamento de mensagens de feedback visual (sucesso, erro, aviso) para operaÃ§Ãµes administrativas.

**Entrada/SaÃ­da**:
- `add_success( string $message )`: Adiciona mensagem de sucesso
- `add_error( string $message )`: Adiciona mensagem de erro
- `add_warning( string $message )`: Adiciona mensagem de aviso
- `display_messages()`: Retorna HTML com todas as mensagens pendentes e as remove automaticamente

**Exemplos prÃ¡ticos**:
```php
// ApÃ³s salvar cliente com sucesso
DPS_Message_Helper::add_success( __( 'Cliente salvo com sucesso!', 'desi-pet-shower' ) );
wp_safe_redirect( $redirect_url );
exit;

// No inÃ­cio da seÃ§Ã£o, exibir mensagens pendentes
echo '<div class="dps-section">';
echo DPS_Message_Helper::display_messages(); // Renderiza alertas
echo '<h2>Cadastro de Clientes</h2>';
```

**Boas prÃ¡ticas**:
- Use mensagens apÃ³s operaÃ§Ãµes que modificam dados (salvar, excluir, atualizar status)
- Coloque `display_messages()` no inÃ­cio de cada seÃ§Ã£o do painel para feedback imediato
- Mensagens sÃ£o armazenadas via transients especÃ­ficos por usuÃ¡rio, garantindo isolamento
- Mensagens sÃ£o exibidas apenas uma vez (single-use) e removidas automaticamente apÃ³s renderizaÃ§Ã£o

#### DPS_Cache_Control
**PropÃ³sito**: Gerenciamento de cache de pÃ¡ginas para garantir que todas as pÃ¡ginas do sistema DPS nÃ£o sejam armazenadas em cache, forÃ§ando conteÃºdo sempre atualizado.

**Entrada/SaÃ­da**:
- `init()`: Registra hooks para detecÃ§Ã£o e prevenÃ§Ã£o de cache (chamado automaticamente no boot do plugin)
- `force_no_cache()`: ForÃ§a desabilitaÃ§Ã£o de cache na requisiÃ§Ã£o atual
- `register_shortcode( string $shortcode )`: Registra shortcode adicional para prevenÃ§Ã£o automÃ¡tica de cache
- `get_registered_shortcodes()`: Retorna lista de shortcodes registrados

**Constantes definidas quando cache Ã© desabilitado**:
- `DONOTCACHEPAGE`: Previne cache de pÃ¡gina (WP Super Cache, W3 Total Cache, LiteSpeed Cache)
- `DONOTCACHEDB`: Previne cache de queries
- `DONOTMINIFY`: Previne minificaÃ§Ã£o de assets
- `DONOTCDN`: Previne uso de CDN
- `DONOTCACHEOBJECT`: Previne cache de objetos

**Headers HTTP enviados**:
- `Cache-Control: no-cache, must-revalidate, max-age=0`
- `Pragma: no-cache`
- `Expires: Wed, 11 Jan 1984 05:00:00 GMT`

**Exemplos prÃ¡ticos**:
```php
// Em um shortcode personalizado de add-on, forÃ§ar no-cache
public function render_meu_shortcode() {
    if ( class_exists( 'DPS_Cache_Control' ) ) {
        DPS_Cache_Control::force_no_cache();
    }
    // ... renderizaÃ§Ã£o do shortcode
}

// Registrar um shortcode personalizado para prevenÃ§Ã£o automÃ¡tica de cache
add_action( 'init', function() {
    if ( class_exists( 'DPS_Cache_Control' ) ) {
        DPS_Cache_Control::register_shortcode( 'meu_addon_shortcode' );
    }
} );
```

**Boas prÃ¡ticas**:
- Todos os shortcodes do DPS jÃ¡ chamam `force_no_cache()` automaticamente
- Para add-ons customizados, sempre inclua a chamada no inÃ­cio do mÃ©todo de renderizaÃ§Ã£o
- Use `class_exists( 'DPS_Cache_Control' )` antes de chamar para compatibilidade com versÃµes anteriores
- A detecÃ§Ã£o automÃ¡tica via hook `template_redirect` funciona como backup

#### Sistema de Templates SobrescrevÃ­veis

**PropÃ³sito**: Permitir que temas customizem a aparÃªncia de templates do DPS mantendo a lÃ³gica de negÃ³cio no plugin. O sistema tambÃ©m oferece controle sobre quando forÃ§ar o uso do template do plugin.

**FunÃ§Ãµes disponÃ­veis** (definidas em `includes/template-functions.php`):

| FunÃ§Ã£o | PropÃ³sito |
|--------|-----------|
| `dps_get_template( $template_name, $args )` | Localiza e inclui um template, permitindo override pelo tema |
| `dps_get_template_path( $template_name )` | Retorna o caminho do template que seria carregado (sem incluÃ­-lo) |
| `dps_is_template_overridden( $template_name )` | Verifica se um template estÃ¡ sendo sobrescrito pelo tema |

**Ordem de busca de templates**:
1. Tema filho: `wp-content/themes/CHILD_THEME/dps-templates/{template_name}`
2. Tema pai: `wp-content/themes/PARENT_THEME/dps-templates/{template_name}`
3. Plugin base: `wp-content/plugins/desi-pet-shower-base/templates/{template_name}`

**Filtros disponÃ­veis**:

| Filtro | PropÃ³sito | ParÃ¢metros |
|--------|-----------|------------|
| `dps_use_plugin_template` | ForÃ§a uso do template do plugin, ignorando override do tema | `$use_plugin (bool)`, `$template_name (string)` |
| `dps_allow_consent_template_override` | Permite que tema sobrescreva o template de consentimento de tosa | `$allow_override (bool)` |

**Actions disponÃ­veis**:

| Action | PropÃ³sito | ParÃ¢metros |
|--------|-----------|------------|
| `dps_template_loaded` | Disparada quando um template Ã© carregado | `$path_to_load (string)`, `$template_name (string)`, `$is_theme_override (bool)` |

**Exemplos prÃ¡ticos**:
```php
// ForÃ§ar uso do template do plugin para um template especÃ­fico
add_filter( 'dps_use_plugin_template', function( $use_plugin, $template_name ) {
    if ( $template_name === 'meu-template.php' ) {
        return true; // Sempre usa versÃ£o do plugin
    }
    return $use_plugin;
}, 10, 2 );

// Permitir override do tema no template de consentimento de tosa
add_filter( 'dps_allow_consent_template_override', '__return_true' );

// Debug: logar qual template estÃ¡ sendo carregado
add_action( 'dps_template_loaded', function( $path, $name, $is_override ) {
    if ( $is_override ) {
        error_log( "DPS: Template '$name' sendo carregado do tema: $path" );
    }
}, 10, 3 );

// Verificar se um template estÃ¡ sendo sobrescrito
if ( dps_is_template_overridden( 'tosa-consent-form.php' ) ) {
    // Template do tema estÃ¡ sendo usado
}
```

**Boas prÃ¡ticas**:
- O template de consentimento de tosa (`tosa-consent-form.php`) forÃ§a uso do plugin por padrÃ£o para garantir que melhorias sejam visÃ­veis
- Use `dps_get_template_path()` para debug quando templates nÃ£o aparecem como esperado
- A action `dps_template_loaded` Ã© Ãºtil para logging e diagnÃ³stico de problemas
- Quando sobrescrever templates no tema, mantenha as variÃ¡veis esperadas pelo sistema

#### DPS_Base_Template_Engine
**PropÃ³sito**: Motor de templates compartilhado para renderizaÃ§Ã£o de componentes PHP com output buffering e suporte a override pelo tema. Portado do Frontend Add-on para uso global (Fase 2.4).

**Arquivo**: `includes/class-dps-base-template-engine.php`

**PadrÃ£o**: Singleton via `DPS_Base_Template_Engine::get_instance()`

**MÃ©todos**:
- `render( string $template, array $data = [] )`: Renderiza template e retorna HTML. Usa `extract( $data, EXTR_SKIP )` + `ob_start()`/`ob_get_clean()`.
- `exists( string $template )`: Verifica se um template existe (no tema ou no plugin) â†’ bool.
- `locateTemplate( string $template )` (private): Busca template em: 1) tema `dps-templates/{prefix}/{file}`, 2) plugin `templates/{file}`.

**Templates disponÃ­veis** (em `templates/`):
- `components/client-summary-cards.php`: cards de resumo do cliente (total atendimentos, pets, valor total)

**Exemplo**:
```php
$engine = DPS_Base_Template_Engine::get_instance();
echo $engine->render( 'components/client-summary-cards.php', [
    'total_appointments' => 15,
    'total_pets'         => 3,
    'total_value'        => 'R$ 1.500,00',
] );
```

### Feedback visual e organizaÃ§Ã£o de interface
- Todos os formulÃ¡rios principais (clientes, pets, agendamentos) utilizam `DPS_Message_Helper` para feedback apÃ³s salvar ou excluir
- FormulÃ¡rios sÃ£o organizados em fieldsets semÃ¢nticos com bordas sutis (`1px solid #e5e7eb`) e legends descritivos
- Hierarquia de tÃ­tulos padronizada: H1 Ãºnico no topo ("Painel de GestÃ£o DPS"), H2 para seÃ§Ãµes principais, H3 para subseÃ§Ãµes
- Design minimalista com paleta reduzida: base neutra (#f9fafb, #e5e7eb, #374151) + 3 cores de status essenciais (verde, amarelo, vermelho)
- Responsividade bÃ¡sica implementada com media queries para mobile (480px), tablets (768px) e desktops pequenos (1024px)

### Gerenciador de Add-ons

O plugin base inclui um gerenciador de add-ons centralizado (`DPS_Addon_Manager`) que:
- Lista todos os add-ons disponÃ­veis do ecossistema DPS
- Verifica status de instalaÃ§Ã£o e ativaÃ§Ã£o
- Determina a ordem correta de ativaÃ§Ã£o baseada em dependÃªncias
- Permite ativar/desativar add-ons em lote respeitando dependÃªncias

**Classe**: `includes/class-dps-addon-manager.php`

**Menu administrativo**: desi.pet by PRObst â†’ Add-ons (`dps-addons`)

#### Categorias de Add-ons

| Categoria | DescriÃ§Ã£o | Add-ons |
|-----------|-----------|---------|
| Essenciais | Funcionalidades base recomendadas | ServiÃ§os, Financeiro, ComunicaÃ§Ãµes |
| OperaÃ§Ã£o | GestÃ£o do dia a dia | Agenda, Groomers, Assinaturas, Estoque |
| IntegraÃ§Ãµes | ConexÃµes externas | Pagamentos, Push Notifications |
| Cliente | Voltados ao cliente final | Cadastro PÃºblico, Portal do Cliente, Fidelidade |
| AvanÃ§ado | Funcionalidades extras | IA, EstatÃ­sticas |
| Sistema | AdministraÃ§Ã£o e manutenÃ§Ã£o | Backup |

#### DependÃªncias entre Add-ons

O sistema resolve automaticamente as dependÃªncias na ordem de ativaÃ§Ã£o:

| Add-on | Depende de |
|--------|-----------|
| Agenda | ServiÃ§os |
| Assinaturas | ServiÃ§os, Financeiro |
| Pagamentos | Financeiro |
| IA | Portal do Cliente |

#### API PÃºblica

```php
// Obter instÃ¢ncia do gerenciador
$manager = DPS_Addon_Manager::get_instance();

// Verificar se add-on estÃ¡ ativo
$is_active = $manager->is_active( 'agenda' );

// Verificar dependÃªncias
$deps = $manager->check_dependencies( 'ai' );
// Retorna: ['satisfied' => false, 'missing' => ['client-portal']]

// Obter ordem recomendada de ativaÃ§Ã£o
$order = $manager->get_activation_order();
// Retorna array ordenado por dependÃªncias com status de cada add-on

// Ativar mÃºltiplos add-ons na ordem correta
$result = $manager->activate_addons( ['services', 'agenda', 'finance'] );
// Ativa: services â†’ finance â†’ agenda (respeitando dependÃªncias)
```

#### Interface Administrativa

A pÃ¡gina "Add-ons" exibe:
1. **Ordem de AtivaÃ§Ã£o Recomendada**: Lista visual dos add-ons instalados na ordem sugerida
2. **Categorias de Add-ons**: Cards organizados por categoria com:
   - Nome e Ã­cone do add-on
   - Status (Ativo/Inativo/NÃ£o Instalado)
   - DescriÃ§Ã£o curta
   - DependÃªncias necessÃ¡rias
   - Checkbox para seleÃ§Ã£o
3. **AÃ§Ãµes em Lote**: BotÃµes para ativar ou desativar add-ons selecionados

**SeguranÃ§a**:
- VerificaÃ§Ã£o de nonce em todas as aÃ§Ãµes
- Capability `manage_options` para acesso Ã  pÃ¡gina
- Capability `activate_plugins`/`deactivate_plugins` para aÃ§Ãµes

### GitHub Updater

O plugin base inclui um sistema de atualizaÃ§Ã£o automÃ¡tica via GitHub (`DPS_GitHub_Updater`) que:
- Verifica novas versÃµes diretamente do repositÃ³rio GitHub
- Notifica atualizaÃ§Ãµes disponÃ­veis no painel de Plugins do WordPress
- Suporta o plugin base e todos os add-ons oficiais
- Usa cache inteligente para evitar chamadas excessivas Ã  API

**Classe**: `includes/class-dps-github-updater.php`

**RepositÃ³rio**: `richardprobst/DPS`

#### Como Funciona

1. **VerificaÃ§Ã£o de VersÃµes**: O updater consulta a API do GitHub (`/repos/{owner}/{repo}/releases/latest`) para obter a versÃ£o mais recente.
2. **ComparaÃ§Ã£o**: Compara a versÃ£o instalada de cada plugin com a versÃ£o da release mais recente.
3. **NotificaÃ§Ã£o**: Se houver atualizaÃ§Ã£o disponÃ­vel, injeta os dados no transient de updates do WordPress.
4. **InstalaÃ§Ã£o**: O WordPress usa seu fluxo padrÃ£o de atualizaÃ§Ã£o para baixar e instalar.

#### ConfiguraÃ§Ã£o

O sistema funciona automaticamente sem configuraÃ§Ã£o adicional. Para desabilitar:

```php
// Desabilitar o updater via hook (em wp-config.php ou plugin)
add_filter( 'dps_github_updater_enabled', '__return_false' );
```

#### API PÃºblica

```php
// Obter instÃ¢ncia do updater
$updater = DPS_GitHub_Updater::get_instance();

// ForÃ§ar verificaÃ§Ã£o (limpa cache)
$release_data = $updater->force_check();

// Obter lista de plugins gerenciados
$plugins = $updater->get_managed_plugins();

// Verificar se um plugin Ã© gerenciado
$is_managed = $updater->is_managed_plugin( 'desi-pet-shower-base_plugin/desi-pet-shower-base.php' );
```

#### ForÃ§ar VerificaÃ§Ã£o Manual

Adicione `?dps_force_update_check=1` Ã  URL do painel de Plugins para forÃ§ar nova verificaÃ§Ã£o:

```
/wp-admin/plugins.php?dps_force_update_check=1
```

#### Requisitos para Releases

Para que o updater reconheÃ§a uma nova versÃ£o:
1. A release no GitHub deve usar tags semver (ex: `v1.2.0` ou `1.2.0`)
2. A versÃ£o na tag deve ser maior que a versÃ£o instalada
3. Opcionalmente, anexe arquivos `.zip` individuais por plugin para download direto

#### Plugins Gerenciados

| Plugin | Arquivo | Caminho no RepositÃ³rio |
|--------|---------|------------------------|
| Base Plugin | `desi-pet-shower-base_plugin/desi-pet-shower-base.php` | `plugins/desi-pet-shower-base` |
| Agenda | `desi-pet-shower-agenda_addon/desi-pet-shower-agenda-addon.php` | `plugins/desi-pet-shower-agenda` |
| AI | `desi-pet-shower-ai_addon/desi-pet-shower-ai-addon.php` | `plugins/desi-pet-shower-ai` |
| Backup | `desi-pet-shower-backup_addon/desi-pet-shower-backup-addon.php` | `plugins/desi-pet-shower-backup` |
| Client Portal | `desi-pet-shower-client-portal_addon/desi-pet-shower-client-portal.php` | `plugins/desi-pet-shower-client-portal` |
| Communications | `desi-pet-shower-communications_addon/desi-pet-shower-communications-addon.php` | `plugins/desi-pet-shower-communications` |
| Finance | `desi-pet-shower-finance_addon/desi-pet-shower-finance-addon.php` | `plugins/desi-pet-shower-finance` |
| Groomers | `desi-pet-shower-groomers_addon/desi-pet-shower-groomers-addon.php` | `plugins/desi-pet-shower-groomers` |
| Loyalty | `desi-pet-shower-loyalty_addon/desi-pet-shower-loyalty.php` | `plugins/desi-pet-shower-loyalty` |
| Payment | `desi-pet-shower-payment_addon/desi-pet-shower-payment-addon.php` | `plugins/desi-pet-shower-payment` |
| Push | `desi-pet-shower-push_addon/desi-pet-shower-push-addon.php` | `plugins/desi-pet-shower-push` |
| Registration | `desi-pet-shower-registration_addon/desi-pet-shower-registration-addon.php` | `plugins/desi-pet-shower-registration` |
| Services | `desi-pet-shower-services_addon/desi-pet-shower-services.php` | `plugins/desi-pet-shower-services` |
| Stats | `desi-pet-shower-stats_addon/desi-pet-shower-stats-addon.php` | `plugins/desi-pet-shower-stats` |
| Stock | `desi-pet-shower-stock_addon/desi-pet-shower-stock.php` | `plugins/desi-pet-shower-stock` |
| Subscription | `desi-pet-shower-subscription_addon/desi-pet-shower-subscription.php` | `plugins/desi-pet-shower-subscription` |

### Tipos de Agendamento

O sistema suporta trÃªs tipos de agendamentos, identificados pelo metadado `appointment_type`:

#### 1. Agendamento Simples (`simple`)
- **PropÃ³sito**: Atendimento Ãºnico, sem recorrÃªncia
- **Campos especÃ­ficos**: Permite adicionar TaxiDog com valor personalizado
- **Comportamento**: Status inicial "pendente", precisa ser manualmente atualizado para "realizado"
- **Metadados salvos**:
  - `appointment_type` = 'simple'
  - `appointment_taxidog` (0 ou 1)
  - `appointment_taxidog_price` (float)
  - `appointment_total_value` (calculado pelo Services Add-on)

#### 2. Agendamento de Assinatura (`subscription`)
- **PropÃ³sito**: Atendimentos recorrentes (semanal ou quinzenal)
- **Campos especÃ­ficos**:
  - FrequÃªncia (semanal ou quinzenal)
  - Tosa opcional com preÃ§o e ocorrÃªncia configurÃ¡vel
  - TaxiDog disponÃ­vel mas sem custo adicional
- **Comportamento**: Vincula-se a um registro de assinatura (`dps_subscription`) e gera atendimentos recorrentes
- **Metadados salvos**:
  - `appointment_type` = 'subscription'
  - `subscription_id` (ID do post de assinatura vinculado)
  - `appointment_tosa` (0 ou 1)
  - `appointment_tosa_price` (float)
  - `appointment_tosa_occurrence` (1-4 para semanal, 1-2 para quinzenal)
  - `subscription_base_value`, `subscription_total_value`

#### 3. Agendamento Passado (`past`)
- **PropÃ³sito**: Registrar atendimentos jÃ¡ realizados anteriormente
- **Campos especÃ­ficos**:
  - Status do Pagamento: dropdown com opÃ§Ãµes "Pago" ou "Pendente"
  - Valor Pendente: campo numÃ©rico condicional (exibido apenas se status = "Pendente")
- **Comportamento**:
  - Status inicial automaticamente definido como "realizado"
  - TaxiDog e Tosa nÃ£o disponÃ­veis (nÃ£o aplicÃ¡vel para registros passados)
  - Permite controlar pagamentos pendentes de atendimentos histÃ³ricos
- **Metadados salvos**:
  - `appointment_type` = 'past'
  - `appointment_status` = 'realizado' (definido automaticamente)
  - `past_payment_status` ('paid' ou 'pending')
  - `past_payment_value` (float, salvo apenas se status = 'pending')
  - `appointment_total_value` (calculado pelo Services Add-on)
- **Casos de uso**:
  - MigraÃ§Ã£o de dados de sistemas anteriores
  - Registro de atendimentos realizados antes da implementaÃ§Ã£o do sistema
  - Controle de pagamentos em atraso de atendimentos histÃ³ricos

**Controle de visibilidade de campos (JavaScript)**:
- A funÃ§Ã£o `updateTypeFields()` em `dps-appointment-form.js` controla a exibiÃ§Ã£o condicional de campos baseada no tipo selecionado
- Campos de frequÃªncia: visÃ­veis apenas para tipo `subscription`
- Campos de tosa: visÃ­veis apenas para tipo `subscription`
- Campos de pagamento passado: visÃ­veis apenas para tipo `past`
- TaxiDog com preÃ§o: visÃ­vel apenas para tipo `simple`


### HistÃ³rico e exportaÃ§Ã£o de agendamentos
- A coleta de atendimentos finalizados Ã© feita em lotes pelo `WP_Query` com `fields => 'ids'`, `no_found_rows => true` e tamanho configurÃ¡vel via filtro `dps_history_batch_size` (padrÃ£o: 200). Isso evita uma Ãºnica consulta gigante em tabelas volumosas e permite tratar listas grandes de forma incremental.
- As metas dos agendamentos sÃ£o prÃ©-carregadas com `update_meta_cache('post')` antes do loop, reduzindo consultas repetidas Ã s mesmas linhas durante a renderizaÃ§Ã£o e exportaÃ§Ã£o.
- Clientes, pets e serviÃ§os relacionados sÃ£o resolvidos com caches em memÃ³ria por ID, evitando `get_post` duplicadas quando o mesmo registro aparece em vÃ¡rias linhas.
- O botÃ£o de exportaÃ§Ã£o gera CSV apenas com as colunas exibidas e respeita os filtros aplicados na tabela, o que limita o volume exportado a um subconjunto relevante e jÃ¡ paginado/filtrado pelo usuÃ¡rio.

## Add-ons complementares (`plugins/`)

### Text Domains para InternacionalizaÃ§Ã£o (i18n)

Todos os plugins e add-ons do DPS seguem o padrÃ£o WordPress de text domains para internacionalizaÃ§Ã£o. Os text domains oficiais sÃ£o:

**Plugin Base**:
- `desi-pet-shower` - Plugin base que fornece CPTs e funcionalidades core

**Add-ons**:
- `dps-agenda-addon` - Agenda e agendamentos
- `dps-ai` - Assistente de IA
- `dps-backup-addon` - Backup e restauraÃ§Ã£o
- `dps-booking-addon` - PÃ¡gina dedicada de agendamentos
- `dps-client-portal` - Portal do cliente
- `dps-communications-addon` - ComunicaÃ§Ãµes (WhatsApp, SMS, email)
- `dps-finance-addon` - Financeiro (transaÃ§Ãµes, parcelas, cobranÃ§as)
- `dps-groomers-addon` - GestÃ£o de groomers/profissionais
- `dps-loyalty-addon` - Campanhas e fidelidade
- `dps-payment-addon` - IntegraÃ§Ã£o de pagamentos
- `dps-push-addon` - NotificaÃ§Ãµes push
- `dps-registration-addon` - Registro e autenticaÃ§Ã£o
- `dps-services-addon` - ServiÃ§os e produtos
- `dps-stats-addon` - EstatÃ­sticas e relatÃ³rios
- `dps-stock-addon` - Controle de estoque
- `dps-subscription-addon` - Assinaturas e recorrÃªncia

**Boas prÃ¡ticas de i18n**:
- Use sempre `__()`, `_e()`, `esc_html__()`, `esc_attr__()` ou `esc_html_e()` para strings exibidas ao usuÃ¡rio
- Sempre especifique o text domain correto do plugin/add-on correspondente
- Para strings JavaScript em `prompt()` ou `alert()`, use `esc_js( __() )` para escapar e traduzir
- Mensagens de erro, sucesso, labels de formulÃ¡rio e textos de interface devem sempre ser traduzÃ­veis
- Dados de negÃ³cio (nomes de clientes, endereÃ§os hardcoded, etc.) nÃ£o precisam de traduÃ§Ã£o

**Carregamento de text domains (WordPress 6.7+)**:
- Todos os plugins devem incluir o header `Domain Path: /languages` para indicar onde os arquivos de traduÃ§Ã£o devem ser armazenados
- Add-ons devem carregar text domains usando `load_plugin_textdomain()` no hook `init` com prioridade 1
- Instanciar classes principais no hook `init` com prioridade 5 (apÃ³s carregamento do text domain)
- Isso garante que strings traduzÃ­veis no constructor sejam traduzidas corretamente
- MÃ©todos de registro (CPT, taxonomias, etc.) devem ser adicionados ao `init` com prioridade padrÃ£o (10)
- **NÃ£o** carregar text domains ou instanciar classes antes do hook `init` (evitar `plugins_loaded` ou escopo global)

**Status de localizaÃ§Ã£o pt_BR**:
- âœ… Todos os 17 plugins (1 base + 16 add-ons) possuem headers `Text Domain` e `Domain Path` corretos
- âœ… Todos os plugins carregam text domain no hook `init` com prioridade 1
- âœ… Todas as classes sÃ£o inicializadas no hook `init` com prioridade 5
- âœ… Todo cÃ³digo, comentÃ¡rios e strings estÃ£o em PortuguÃªs do Brasil
- âœ… Sistema pronto para expansÃ£o multilÃ­ngue com arquivos .po/.mo em `/languages`

---

### Estrutura de Menus Administrativos

Todos os add-ons do DPS devem registrar seus menus e submenus sob o menu principal **"desi.pet by PRObst"** (slug: `desi-pet-shower`) para manter a interface administrativa organizada e unificada.

**Menu Principal** (criado pelo plugin base):
- Slug: `desi-pet-shower`
- Ãcone: `dashicons-pets`
- Capability: `manage_options`
- PosiÃ§Ã£o: 56 (apÃ³s "Settings")

**Submenus Ativos** (registrados pelo plugin base e add-ons):
- **Assistente de IA** (`dps-ai-settings`) - AI Add-on (configuraÃ§Ãµes do assistente virtual)
- **Backup & RestauraÃ§Ã£o** (`dps-backup`) - Backup Add-on (exportar/importar dados)
- **Campanhas** (`edit.php?post_type=dps_campaign`) - Loyalty Add-on (listagem de campanhas)
- **Campanhas & Fidelidade** (`dps-loyalty`) - Loyalty Add-on (configuraÃ§Ãµes de pontos e indicaÃ§Ãµes)
- **Clientes** (`dps-clients-settings`) - Plugin Base (define a URL da pÃ¡gina dedicada de cadastro exibida nos atalhos da aba Clientes)
- **ComunicaÃ§Ãµes** (`dps-communications`) - Communications Add-on (templates e gateways)
- **FormulÃ¡rio de Cadastro** (`dps-registration-settings`) - Registration Add-on (configuraÃ§Ãµes do formulÃ¡rio pÃºblico para clientes se cadastrarem)
- **Logins de Clientes** (`dps-client-logins`) - Client Portal Add-on (gerenciar tokens de acesso)
- **Logs do Sistema** (`dps-logs`) - Plugin Base (visualizaÃ§Ã£o de logs do sistema)
- **Mensagens do Portal** (`edit.php?post_type=dps_portal_message`) - Client Portal Add-on (mensagens enviadas pelos clientes)
- **NotificaÃ§Ãµes** (`dps-push-notifications`) - Push Add-on (push, agenda, relatÃ³rios, Telegram)
- **Pagamentos** (`dps-payment-settings`) - Payment Add-on (Mercado Pago, PIX)
- **Portal do Cliente** (`dps-client-portal-settings`) - Client Portal Add-on (configuraÃ§Ãµes do portal)

**Nomenclatura de Menus - Diretrizes de Usabilidade**:
- Use nomes curtos e descritivos que indiquem claramente a funÃ§Ã£o
- Evite prefixos redundantes como "DPS" ou "desi.pet by PRObst" nos nomes de submenu
- Use verbos ou substantivos que descrevam a aÃ§Ã£o/entidade gerenciada
- Exemplos de nomes descritivos:
  - âœ… "Logs do Sistema" (indica claramente que sÃ£o logs tÃ©cnicos)
  - âœ… "Backup & RestauraÃ§Ã£o" (aÃ§Ãµes disponÃ­veis)
  - âœ… "FormulÃ¡rio de Cadastro" (indica que Ã© um formulÃ¡rio para clientes se registrarem)
  - âŒ "DPS Logs" (prefixo redundante - jÃ¡ estÃ¡ no menu pai)
  - âŒ "Settings" (genÃ©rico demais)
  - âŒ "Cadastro PÃºblico" (pouco intuitivo, prefira "FormulÃ¡rio de Cadastro")

**Boas prÃ¡ticas para registro de menus**:
- Sempre use `add_submenu_page()` com `'desi-pet-shower'` como menu pai
- Use prioridade 20 no hook `admin_menu` para garantir que o menu pai jÃ¡ existe:
  ```php
  add_action( 'admin_menu', [ $this, 'register_admin_menu' ], 20 );
  ```
- Evite criar menus prÃ³prios separados (ex: `add_menu_page()` em add-ons)
- Para CPTs que precisam aparecer no menu, use `show_in_menu => 'desi-pet-shower'` ao registrar o CPT:
  ```php
  register_post_type( 'meu_cpt', [
      'show_in_menu' => 'desi-pet-shower', // Agrupa no menu principal
      // ...
  ] );
  ```
- Prefira integraÃ§Ã£o via `DPS_Settings_Frontend::register_tab()` para adicionar abas na pÃ¡gina de configuraÃ§Ãµes. Os hooks legados (`dps_settings_nav_tabs`, `dps_settings_sections`) estÃ£o depreciados.

**HistÃ³rico de correÃ§Ãµes**:
- **2025-01-13**: Hooks legados `dps_settings_nav_tabs` e `dps_settings_sections` depreciados em favor do sistema moderno de abas
- **2025-12-01**: Mensagens do Portal migrado de menu prÃ³prio para submenu do desi.pet by PRObst (CPT com show_in_menu)
- **2025-12-01**: Cadastro PÃºblico renomeado para "FormulÃ¡rio de Cadastro" (mais intuitivo)
- **2025-12-01**: Logs do Sistema migrado de menu prÃ³prio para submenu do desi.pet by PRObst
- **2025-11-24**: Adicionado menu administrativo ao Client Portal Add-on (Portal do Cliente e Logins de Clientes)
- **2024-11-24**: Corrigida prioridade de registro de menus em todos os add-ons (de 10 para 20)
- **2024-11-24**: Loyalty Add-on migrado de menu prÃ³prio (`dps-loyalty-addon`) para submenu unificado (`desi-pet-shower`)

---

### Agenda (`desi-pet-shower-agenda_addon`)

**DiretÃ³rio**: `plugins/desi-pet-shower-agenda`

**PropÃ³sito e funcionalidades principais**:
- Gerenciar agenda de atendimentos e cobranÃ§as pendentes
- Enviar lembretes automÃ¡ticos diÃ¡rios aos clientes
- Atualizar status de agendamentos via interface AJAX
- **[Deprecated v1.1.0]** Endpoint `dps_get_services_details` (movido para Services Add-on)

**Shortcodes expostos**:
- `[dps_agenda_page]`: renderiza pÃ¡gina de agenda com contexto de perÃ­odo, abas operacionais e aÃ§Ãµes
- `[dps_charges_notes]`: **[Deprecated]** redirecionado para Finance Add-on (`[dps_fin_docs]`)

**CPTs, tabelas e opÃ§Ãµes**:
- NÃ£o cria CPTs prÃ³prios; consome `dps_agendamento` do nÃºcleo
- Cria pÃ¡ginas automaticamente: "Agenda DPS"
- Options: `dps_agenda_page_id`

**Meta keys de agendamento** (post meta de `dps_agendamento`):
- `_dps_checklist`: checklist operacional com status por etapa (prÃ©-banho, banho, secagem, tosa, orelhas/unhas, acabamento) e histÃ³rico de retrabalho
- `_dps_checkin`: dados de check-in (horÃ¡rio, observaÃ§Ãµes, itens de seguranÃ§a com severidade)
- `_dps_checkout`: dados de check-out (horÃ¡rio, observaÃ§Ãµes, itens de seguranÃ§a)

**Hooks consumidos**:
- Nenhum hook especÃ­fico do nÃºcleo (opera diretamente sobre CPTs)

**Hooks disparados**:
- `dps_agenda_send_reminders`: cron job diÃ¡rio para envio de lembretes
- `dps_checklist_rework_registered( $appointment_id, $step_key, $reason )`: quando uma etapa do checklist precisa de retrabalho
- `dps_appointment_checked_in( $appointment_id, $data )`: apÃ³s check-in registrado
- `dps_appointment_checked_out( $appointment_id, $data )`: apÃ³s check-out registrado

**Filtros**:
- `dps_checklist_default_steps`: permite add-ons adicionarem etapas ao checklist operacional (ex.: hidrataÃ§Ã£o, ozÃ´nio)
- `dps_checkin_safety_items`: permite add-ons adicionarem itens de seguranÃ§a ao check-in/check-out

**Endpoints AJAX**:
- `dps_update_status`: atualiza status de agendamento
- `dps_checklist_update`: atualiza status de uma etapa do checklist (nonce: `dps_checklist`)
- `dps_checklist_rework`: registra retrabalho em uma etapa do checklist (nonce: `dps_checklist`)
- `dps_appointment_checkin`: registra check-in com observaÃ§Ãµes e itens de seguranÃ§a (nonce: `dps_checkin`)
- `dps_appointment_checkout`: registra check-out com observaÃ§Ãµes e itens de seguranÃ§a (nonce: `dps_checkin`)
- `dps_get_services_details`: **[Deprecated v1.1.0]** mantido por compatibilidade, delega para `DPS_Services_API::get_services_details()`

**DependÃªncias**:
- Depende do plugin base para CPTs de agendamento
- **[Recomendado]** Services Add-on para cÃ¡lculo de valores via API
- Integra-se com add-on de ComunicaÃ§Ãµes para envio de mensagens (se ativo)
- Aviso exibido se Finance Add-on nÃ£o estiver ativo (funcionalidades financeiras limitadas)

**Introduzido em**: v0.1.0 (estimado)

**Assets**:
- `assets/js/agenda-addon.js`: interaÃ§Ãµes AJAX e feedback visual
- `assets/js/checklist-checkin.js`: interaÃ§Ãµes do checklist operacional e check-in/check-out
- `assets/css/checklist-checkin.css`: estilos DPS Signature para checklist e check-in/check-out
- `assets/css/agenda-addon.css`: shell DPS Signature da Agenda, linhas por aba, overview, tabs compactas e dialog system unificado
- **[Deprecated]** `agenda-addon.js` e `agenda.js` na raiz (devem ser removidos)

**Classes de serviÃ§o**:
- `DPS_Agenda_Checklist_Service`: CRUD de checklist operacional com etapas, progresso e retrabalho
- `DPS_Agenda_Checkin_Service`: check-in/check-out com itens de seguranÃ§a e cÃ¡lculo de duraÃ§Ã£o

**ObservaÃ§Ãµes**:
- Implementa `register_deactivation_hook` para limpar cron job `dps_agenda_send_reminders` ao desativar
- **[v1.1.0]** LÃ³gica de serviÃ§os movida para Services Add-on; Agenda delega cÃ¡lculos para `DPS_Services_API`
- **DocumentaÃ§Ã£o completa**: `docs/analysis/AGENDA_ADDON_ANALYSIS.md` (anÃ¡lise profunda de cÃ³digo, funcionalidades, layout e melhorias propostas)
- **DocumentaÃ§Ã£o de layout**: `docs/analysis/AGENDA_ADDON_ANALYSIS.md` (seÃ§Ãµes de UX, responsividade e acessibilidade)
- **[2026-03-23] Lista de Atendimentos redesenhada**: shell DPS Signature unificado com overview mais contido, tabs compactas e microcopy operacional orientada a decisao.
- **[2026-03-23] Operacao inline unificada**: checklist operacional e check-in/check-out passam a compartilhar o mesmo painel expansivel da aba Operacao.
- **[2026-03-23] Dialog system da Agenda**: historico, cobranca, reagendamento, confirmacoes sensiveis e retrabalho convergem para o mesmo shell modal.

---

### Backup & RestauraÃ§Ã£o (`desi-pet-shower-backup_addon`)

**DiretÃ³rio**: `plugins/desi-pet-shower-backup`

**PropÃ³sito e funcionalidades principais**:
- Exportar todo o conteÃºdo do sistema em formato JSON (CPTs, metadados, options, tabelas, anexos)
- Restaurar dados de backups anteriores com mapeamento inteligente de IDs
- Proteger operaÃ§Ãµes com nonces, validaÃ§Ãµes e transaÃ§Ãµes SQL
- Suportar migraÃ§Ã£o entre ambientes WordPress

**Shortcodes expostos**: Nenhum

**Menus administrativos**:
- **Backup & RestauraÃ§Ã£o** (`dps-backup`): interface para exportar e restaurar dados

**CPTs, tabelas e opÃ§Ãµes**:
- NÃ£o cria CPTs ou tabelas prÃ³prias
- **Exporta/Importa**: todos os CPTs prefixados com `dps_`, tabelas `dps_*`, options `dps_*`
- Options de histÃ³rico (planejado): `dps_backup_history`, `dps_backup_settings`

**Hooks consumidos**:
- `admin_menu` (prioridade 20): registra submenu sob "desi.pet by PRObst"
- `admin_post_dps_backup_export`: processa exportaÃ§Ã£o de backup
- `admin_post_dps_backup_import`: processa importaÃ§Ã£o de backup

**Hooks disparados**: Nenhum (opera de forma autÃ´noma)

**SeguranÃ§a implementada**:
- âœ… Nonces em exportaÃ§Ã£o e importaÃ§Ã£o (`dps_backup_nonce`)
- âœ… VerificaÃ§Ã£o de capability `manage_options`
- âœ… ValidaÃ§Ã£o de extensÃ£o (apenas `.json`) e tamanho (mÃ¡x. 50MB)
- âœ… SanitizaÃ§Ã£o de tabelas e options (apenas prefixo `dps_`)
- âœ… DeserializaÃ§Ã£o segura (`allowed_classes => false`)
- âœ… TransaÃ§Ãµes SQL com rollback em caso de falha

**DependÃªncias**:
- **ObrigatÃ³ria**: Plugin base DPS (verifica `DPS_Base_Plugin`)
- Acessa todos os CPTs e tabelas do sistema para exportaÃ§Ã£o/importaÃ§Ã£o

**Introduzido em**: v0.1.0 (estimado)

**VersÃ£o atual**: 1.0.0

**ObservaÃ§Ãµes**:
- Arquivo Ãºnico de 1338 linhas; candidato a refatoraÃ§Ã£o modular futura
- Suporta exportaÃ§Ã£o de anexos (fotos de pets) e documentos financeiros (`dps_docs`)
- Mapeamento inteligente de IDs: clientes â†’ pets â†’ agendamentos â†’ transaÃ§Ãµes

**AnÃ¡lise completa**: Consulte `docs/analysis/BACKUP_ADDON_ANALYSIS.md` para anÃ¡lise detalhada de cÃ³digo, funcionalidades, seguranÃ§a e melhorias propostas

---

### Booking (`desi-pet-shower-booking`)

**DiretÃ³rio**: `plugins/desi-pet-shower-booking`
**VersÃ£o**: 1.3.0

**PropÃ³sito e funcionalidades principais**:
- PÃ¡gina dedicada de agendamentos para administradores
- Mesma funcionalidade da aba Agendamentos do Painel de GestÃ£o DPS, porÃ©m em pÃ¡gina independente
- FormulÃ¡rio completo com seleÃ§Ã£o de cliente, pets, serviÃ§os, data/hora, tipo de agendamento (avulso/assinatura) e status de pagamento
- Tela de confirmaÃ§Ã£o pÃ³s-agendamento com resumo e aÃ§Ãµes rÃ¡pidas (WhatsApp, novo agendamento, voltar ao painel)
- Design system migrado para DPS Signature (v1.3.0)
- OtimizaÃ§Ãµes de performance (batch queries para owners de pets)
- ValidaÃ§Ãµes granulares de seguranÃ§a (verificaÃ§Ã£o por agendamento especÃ­fico)

**Shortcodes expostos**:
- `[dps_booking_form]`: renderiza formulÃ¡rio completo de agendamento

**CPTs, tabelas e opÃ§Ãµes**:
- NÃ£o cria CPTs ou tabelas prÃ³prias; consome `dps_agendamento` do nÃºcleo
- Cria pÃ¡gina automaticamente na ativaÃ§Ã£o: "Agendamento de ServiÃ§os"
- Options: `dps_booking_page_id`

**Hooks consumidos**:
- `dps_base_after_save_appointment`: captura agendamento salvo para exibir tela de confirmaÃ§Ã£o
- `dps_base_appointment_fields`: permite injeÃ§Ã£o de campos customizados por add-ons
- `dps_base_appointment_assignment_fields`: permite adicionar campos de atribuiÃ§Ã£o

**Hooks disparados**: Nenhum hook prÃ³prio

**Capabilities verificadas**:
- `manage_options` (admin total)
- `dps_manage_clients` (gestÃ£o de clientes)
- `dps_manage_pets` (gestÃ£o de pets)
- `dps_manage_appointments` (gestÃ£o de agendamentos)
- ObservaÃ§Ã£o: a pÃ¡gina dedicada de booking valida carregamento e salvamento com `manage_options` ou `dps_manage_appointments`, evitando que o formulÃ¡rio fique acessÃ­vel sem permissÃ£o real de agendamento.

**Assets (v1.3.0)**:
- `booking-addon.css`: Estilos DPS Signature com semantic mapping, 100% tokens DPS Signature
- DependÃªncia condicional de `dps-design-tokens.css` via check de `DPS_BASE_URL`
- Assets do base plugin carregados via `DPS_Base_Plugin::enqueue_frontend_assets()`

**Melhorias de seguranÃ§a (v1.3.0)**:
- MÃ©todo `can_edit_appointment()`: valida se usuÃ¡rio pode editar agendamento especÃ­fico
- VerificaÃ§Ã£o de `can_access()` antes de renderizar seÃ§Ã£o
- DocumentaÃ§Ã£o phpcs para parÃ¢metros GET read-only

**OtimizaÃ§Ãµes de performance (v1.3.0)**:
- Batch fetch de owners de pets (reduÃ§Ã£o de N+1 queries: 100+ â†’ 1)
- Preparado para futura paginaÃ§Ã£o de clientes

**Acessibilidade (v1.3.0)**:
- `aria-hidden="true"` em todos emojis decorativos
- Suporte a `prefers-reduced-motion` em animaÃ§Ãµes
- ARIA roles e labels conforme padrÃµes do base plugin

**Endpoints AJAX**: Nenhum

**DependÃªncias**:
- Depende do plugin base para CPTs de agendamento e helpers globais
- Integra-se com Services Add-on para listagem de serviÃ§os disponÃ­veis
- Integra-se com Groomers Add-on para atribuiÃ§Ã£o de profissionais (se ativo)

**Introduzido em**: v1.0.0

**Assets**:
- `assets/css/dps-booking-form.css`: estilos do formulÃ¡rio de agendamento
- `assets/js/dps-booking-form.js`: interaÃ§Ãµes do formulÃ¡rio (seleÃ§Ã£o de pets, datas, etc.)

**ObservaÃ§Ãµes**:
- Assets carregados condicionalmente apenas na pÃ¡gina de agendamento (`dps_booking_page_id`)
- Implementa `register_activation_hook` para criar pÃ¡gina automaticamente
- FormulÃ¡rio reutiliza lÃ³gica de salvamento do plugin base (`save_appointment`)

---

### Campanhas & Fidelidade (`desi-pet-shower-loyalty_addon`)

**DiretÃ³rio**: `plugins/desi-pet-shower-loyalty`

**PropÃ³sito e funcionalidades principais**:
- Gerenciar programa de pontos por faturamento
- MÃ³dulo "Indique e Ganhe" com cÃ³digos Ãºnicos e recompensas
- Criar e executar campanhas de marketing direcionadas

**Shortcodes expostos**: Nenhum

**CPTs, tabelas e opÃ§Ãµes**:
- CPT: `dps_campaign` (campanhas de marketing)
- Tabela: `dps_referrals` (indicaÃ§Ãµes de clientes)
- Options: configuraÃ§Ãµes de pontos, recompensas e elegibilidade

**Hooks consumidos**:
- `dps_registration_after_client_created`: registra indicaÃ§Ãµes no cadastro pÃºblico
- `dps_finance_booking_paid`: bonifica indicador/indicado na primeira cobranÃ§a paga
- `dps_base_nav_tabs_after_history`: adiciona aba "Campanhas & Fidelidade"
- `dps_base_sections_after_history`: renderiza conteÃºdo da aba

**Hooks disparados**: Nenhum

**DependÃªncias**:
- Integra-se com add-on Financeiro para bonificaÃ§Ãµes
- Integra-se com add-on de Cadastro PÃºblico para capturar cÃ³digos de indicaÃ§Ã£o
- Integra-se com Portal do Cliente para exibir cÃ³digo/link de convite

**Introduzido em**: v0.2.0

**ObservaÃ§Ãµes**:
- Tabela `dps_referrals` criada via `dbDelta` na ativaÃ§Ã£o
- Oferece funÃ§Ãµes globais para crÃ©dito e resgate de pontos

---

### ComunicaÃ§Ãµes (`desi-pet-shower-communications_addon`)

**DiretÃ³rio**: `plugins/desi-pet-shower-communications`

**PropÃ³sito e funcionalidades principais**:
- **Centralizar TODAS as comunicaÃ§Ãµes do sistema** via API pÃºblica `DPS_Communications_API`
- Enviar mensagens via WhatsApp, e-mail e SMS (futuro)
- Aplicar templates configurÃ¡veis com placeholders dinÃ¢micos
- Registrar logs automÃ¡ticos de todas as comunicaÃ§Ãµes via `DPS_Logger`
- Fornecer hooks para extensibilidade por outros add-ons

**Arquitetura - Camada Centralizada**:
- **API Central**: `DPS_Communications_API` (singleton) expÃµe mÃ©todos pÃºblicos
- **Gatilhos**: Agenda, Portal e outros add-ons **delegam** envios para a API
- **Interfaces mantidas**: BotÃµes de aÃ§Ã£o (wa.me links) **permanecem** na Agenda e Portal
- **LÃ³gica de envio**: Concentrada na API, nÃ£o duplicada entre add-ons

**API PÃºblica** (`includes/class-dps-communications-api.php`):
```php
$api = DPS_Communications_API::get_instance();

// MÃ©todos principais:
$api->send_whatsapp( $to, $message, $context = [] );
$api->send_email( $to, $subject, $body, $context = [] );
$api->send_appointment_reminder( $appointment_id );
$api->send_payment_notification( $client_id, $amount_cents, $context = [] );
$api->send_message_from_client( $client_id, $message, $context = [] );
```

**Shortcodes expostos**: Nenhum (operaÃ§Ã£o via API e configuraÃ§Ãµes)

**CPTs, tabelas e opÃ§Ãµes**:
- NÃ£o cria CPTs ou tabelas prÃ³prias
- Option `dps_comm_settings`: configuraÃ§Ãµes de gateways e templates
  - `whatsapp_api_key`: chave de API do gateway WhatsApp
  - `whatsapp_api_url`: endpoint base do gateway
  - `default_email_from`: e-mail remetente padrÃ£o
  - `template_confirmation`: template de confirmaÃ§Ã£o de agendamento
  - `template_reminder`: template de lembrete (placeholders: `{client_name}`, `{pet_name}`, `{date}`, `{time}`)
  - `template_post_service`: template de pÃ³s-atendimento

**Hooks consumidos**:
- `dps_base_after_save_appointment`: dispara confirmaÃ§Ã£o apÃ³s salvar agendamento
- ~~`dps_settings_nav_tabs`~~: (migrado para sistema moderno) aba "ComunicaÃ§Ãµes" registrada em `DPS_Settings_Frontend`
- ~~`dps_settings_sections`~~: (migrado para sistema moderno) renderizaÃ§Ã£o via callback em `register_tab()`

**Hooks disparados (Actions)**:
- `dps_after_whatsapp_sent( $to, $message, $context, $result )`: apÃ³s envio de WhatsApp
- `dps_after_email_sent( $to, $subject, $body, $context, $result )`: apÃ³s envio de e-mail
- `dps_after_reminder_sent( $appointment_id, $sent )`: apÃ³s envio de lembrete
- `dps_comm_send_appointment_reminder`: cron job para lembretes de agendamento
- `dps_comm_send_post_service`: cron job para mensagens pÃ³s-atendimento

**Hooks disparados (Filters)**:
- `dps_comm_whatsapp_message( $message, $to, $context )`: filtra mensagem WhatsApp antes de enviar
- `dps_comm_email_subject( $subject, $to, $context )`: filtra assunto de e-mail
- `dps_comm_email_body( $body, $to, $context )`: filtra corpo de e-mail
- `dps_comm_email_headers( $headers, $to, $context )`: filtra headers de e-mail
- `dps_comm_reminder_message( $message, $appointment_id )`: filtra mensagem de lembrete
- `dps_comm_payment_notification_message( $message, $client_id, $amount_cents, $context )`: filtra notificaÃ§Ã£o de pagamento

**DependÃªncias**:
- Depende do plugin base para `DPS_Logger` e `DPS_Phone_Helper`
- Agenda e Portal delegam comunicaÃ§Ãµes para esta API (dependÃªncia soft)

**IntegraÃ§Ã£o com outros add-ons**:
- **Agenda**: delega lembretes e notificaÃ§Ãµes de status, **mantÃ©m** botÃµes wa.me
- **Portal**: delega mensagens de clientes para admin
- **Finance**: pode usar API para notificar pagamentos

**Introduzido em**: v0.1.0
**Refatorado em**: v0.2.0 (API centralizada)

**DocumentaÃ§Ã£o completa**: `plugins/desi-pet-shower-communications/README.md`

---

### Groomers (`desi-pet-shower-groomers_addon`)

**DiretÃ³rio**: `plugins/desi-pet-shower-groomers`

**PropÃ³sito e funcionalidades principais**:
- Cadastrar e gerenciar profissionais (groomers) via role customizada
- Vincular mÃºltiplos groomers por atendimento
- Gerar relatÃ³rios de produtividade por profissional com mÃ©tricas visuais
- Exibir cards de mÃ©tricas: total de atendimentos, receita total, ticket mÃ©dio
- IntegraÃ§Ã£o com Finance API para cÃ¡lculo de receitas

**Shortcodes expostos**: Nenhum

**CPTs, tabelas e opÃ§Ãµes**:
- Role customizada: `dps_groomer` (profissional de banho e tosa)
- Metadados: `_dps_groomers` (array de IDs de groomers por agendamento)

**Hooks consumidos**:
- `dps_base_appointment_assignment_fields`: adiciona campo de seleÃ§Ã£o mÃºltipla de groomers na seÃ§Ã£o "AtribuiÃ§Ã£o" (desde v1.8.0)
- `dps_base_after_save_appointment`: salva groomers selecionados
- `dps_base_nav_tabs_after_history`: adiciona aba "Groomers" (prioridade 15)
- `dps_base_sections_after_history`: renderiza cadastro e relatÃ³rios (prioridade 15)
- `wp_enqueue_scripts`: carrega CSS e JS no frontend
- `admin_enqueue_scripts`: carrega CSS e JS no admin

**Hooks disparados**: Nenhum

**DependÃªncias**:
- Depende do plugin base para estrutura de navegaÃ§Ã£o e agendamentos
- **Opcional**: Finance Add-on para cÃ¡lculo automÃ¡tico de receitas nos relatÃ³rios

**Introduzido em**: v0.1.0 (estimado)

**VersÃ£o atual**: v1.1.0

**Assets**:
- `assets/css/groomers-admin.css`: estilos seguindo padrÃ£o visual minimalista DPS
- `assets/js/groomers-admin.js`: validaÃ§Ãµes e interatividade do formulÃ¡rio

**ObservaÃ§Ãµes**:
- v1.1.0: Refatorado com assets externos, fieldsets no formulÃ¡rio e cards de mÃ©tricas
- FormulÃ¡rio de cadastro com fieldsets: Dados de Acesso e InformaÃ§Ãµes Pessoais
- RelatÃ³rios exibem detalhes de cliente e pet por atendimento
- IntegraÃ§Ã£o inteligente com Finance API (fallback para SQL direto)
- Consulte `docs/analysis/GROOMERS_ADDON_ANALYSIS.md` para anÃ¡lise detalhada e plano de melhorias

---

### Portal do Cliente (`desi-pet-shower-client-portal_addon`)

**Diret�rio**: `plugins/desi-pet-shower-client-portal`

**Prop�sito e funcionalidades principais**:
- Fornecer �rea autenticada para clientes
- Permitir atualiza��o de dados pessoais e de pets
- Exibir hist�rico de atendimentos e pend�ncias financeiras
- Integrar com m�dulo "Indique e Ganhe" quando ativo
- Sistema hibrido de autenticacao com magic links e login por e-mail e senha
- O usuario do portal usa o e-mail cadastrado no cliente como identificador de acesso
- Link de atualiza��o de perfil para clientes atualizarem seus dados sem login
- Coleta de consentimento de tosa com m�quina via link tokenizado
- Aba de pagamentos com resumo financeiro, pend�ncias e hist�rico de parcelas (Fase 5.5)
- Galeria multi-fotos por pet com lightbox (Fase 5.1)
- PreferÃªncias de notificaÃ§Ã£o configurÃ¡veis pelo cliente (Fase 5.2)
- Seletor de pet no modal de agendamento para clientes com mÃºltiplos pets (Fase 5.3)
- Barra de progresso stepper (3 etapas) no fluxo de agendamento (Fase 4.1)
- SugestÃµes inteligentes de agendamento baseadas no histÃ³rico do pet (Fase 8.1)
- AutenticaÃ§Ã£o de dois fatores (2FA) via e-mail, opcional (Fase 6.4)
- Remember-me com cookie permanente (Fase 4.6)

**Shortcodes expostos**:
- `[dps_client_portal]`: renderiza portal completo do cliente
- `[dps_client_login]`: exibe formulÃ¡rio de login
- `[dps_profile_update]`: formulÃ¡rio pÃºblico de atualizaÃ§Ã£o de perfil (usado internamente via token)
- `[dps_tosa_consent]`: formulÃ¡rio pÃºblico de consentimento de tosa com mÃ¡quina (via token)

**CPTs, tabelas e opÃ§Ãµes**:
- NÃ£o cria CPTs prÃ³prios
- Tabela customizada `wp_dps_portal_tokens` para gerenciar tokens de acesso
  - Suporta 5 tipos de token: `login` (tempor�rio 30min), `first_access` (tempor�rio 30min), `permanent` (v�lido at� revoga��o), `profile_update` (7 dias), `tosa_consent` (7 dias)
- Sess�es PHP pr�prias para autentica��o independente do WordPress
- Option `dps_portal_page_id`: armazena ID da p�gina configurada do portal
- Option `dps_portal_2fa_enabled`: habilita/desabilita 2FA via e-mail (padr�o: desabilitado)
- Option `dps_portal_rate_limits`: controle simples de tentativas para pedidos de link e cria??o/redefini??o de senha
- Tipos de mensagem customizados para notifica��es

**Abas do portal**:
- `inicio`: dashboard com resumo (agendamentos, pets, status financeiro)
- `agendamentos`: histÃ³rico de atendimentos com filtro por perÃ­odo
- `pagamentos`: resumo financeiro, transaÃ§Ãµes pendentes com parcelas, histÃ³rico de pagos (Fase 5.5)
- `pet-history`: timeline de atendimentos por pet com info card detalhado
- `galeria`: galeria multi-fotos por pet com lightbox (Fase 5.1)
- `fidelidade`: programa de indicaÃ§Ã£o e recompensas
- `reviews`: avaliaÃ§Ãµes pÃ³s-serviÃ§o
- `mensagens`: comunicaÃ§Ã£o com o pet shop
- `dados`: dados pessoais, pets e preferÃªncias de notificaÃ§Ã£o
- Hook `dps_portal_tabs` (filter): permite add-ons adicionarem abas customizadas

**Notas de implementacao recentes**:
- A home autenticada passou a usar um snapshot agregado do cliente para alimentar hero, cards de overview, quick actions e badges das tabs sem duplicar regras de apresentacao.
- O JavaScript de navegacao rapida passou a resolver destinos a partir das tabs realmente renderizadas no DOM e aceita `data-portal-nav-target` como atributo preferencial para CTAs internos.
- O resumo do proximo agendamento depende da ordenacao cronologica em `DPS_Appointment_Repository`, ignorando registros concluidos ou cancelados para evitar destaque incorreto na aba `inicio`.
**Menus administrativos**:
- **Portal do Cliente** (`dps-client-portal-settings`): configura??es gerais do portal, toggle 2FA e resumo operacional do acesso h?brido
- **Logins de Clientes** (`dps-client-logins`): gerenciamento de tokens de acesso
  - Interface para gerar tokens tempor?rios ou permanentes
  - Revoga??o manual de tokens ativos
  - Envio de links por WhatsApp ou e-mail
  - Envio de e-mail para criar ou redefinir senha do portal
  - Sincroniza??o de usu?rios WordPress vinculados ao cliente
  - Hist?rico de acessos por cliente com distin??o entre link direto e login por senha

**Classes principais**:

| Classe | Arquivo | PropÃ³sito |
|--------|---------|-----------|
| `DPS_Client_Portal` | `includes/class-dps-client-portal.php` | Classe principal: shortcode, auth flow, tabs, localize_script |
| `DPS_Portal_2FA` | `includes/class-dps-portal-2fa.php` | 2FA via e-mail: gera/verifica c�digos, renderiza form, AJAX handler |
| `DPS_Scheduling_Suggestions` | `includes/class-dps-scheduling-suggestions.php` | Sugest�es de agendamento baseadas no hist�rico do pet |
| `DPS_Portal_Renderer` | `includes/client-portal/class-dps-portal-renderer.php` | Renderiza��o das abas e componentes visuais |
| `DPS_Portal_Actions_Handler` | `includes/client-portal/class-dps-portal-actions-handler.php` | Handlers de a��es POST (save, update, upload) |
| `DPS_Portal_Ajax_Handler` | `includes/client-portal/class-dps-portal-ajax-handler.php` | Handlers de requisi��es AJAX |
| `DPS_Portal_Session_Manager` | `includes/class-dps-portal-session-manager.php` | Gerenciamento de sess�es PHP |
| `DPS_Portal_Token_Manager` | `includes/class-dps-portal-token-manager.php` | CRUD de tokens com suporte a permanentes e tempor�rios |
| `DPS_Portal_User_Manager` | `includes/class-dps-portal-user-manager.php` | Provisiona/sincroniza usu?rio WordPress pelo e-mail do cliente e envia acesso por senha |
| `DPS_Portal_Rate_Limiter` | `includes/class-dps-portal-rate-limiter.php` | Limita tentativas de solicita??o de link e de cria??o/redefini??o de senha |
| `DPS_Finance_Repository` | `includes/client-portal/repositories/class-dps-finance-repository.php` | Acesso a dados financeiros (transa��es, parcelas, resumos) |
| `DPS_Portal_2FA` | `includes/class-dps-portal-2fa.php` | 2FA via e-mail: gera/verifica cÃ³digos, renderiza form, AJAX handler |
| `DPS_Scheduling_Suggestions` | `includes/class-dps-scheduling-suggestions.php` | SugestÃµes de agendamento baseadas no histÃ³rico do pet |
| `DPS_Portal_Renderer` | `includes/client-portal/class-dps-portal-renderer.php` | RenderizaÃ§Ã£o das abas e componentes visuais |
| `DPS_Portal_Actions_Handler` | `includes/client-portal/class-dps-portal-actions-handler.php` | Handlers de aÃ§Ãµes POST (save, update, upload) |
| `DPS_Portal_Ajax_Handler` | `includes/client-portal/class-dps-portal-ajax-handler.php` | Handlers de requisiÃ§Ãµes AJAX |
| `DPS_Portal_Session_Manager` | `includes/class-dps-portal-session-manager.php` | Gerenciamento de sessÃµes PHP |
| `DPS_Portal_Token_Manager` | `includes/class-dps-portal-token-manager.php` | CRUD de tokens com suporte a permanentes e temporÃ¡rios |
| `DPS_Finance_Repository` | `includes/client-portal/repositories/class-dps-finance-repository.php` | Acesso a dados financeiros (transaÃ§Ãµes, parcelas, resumos) |
| `DPS_Pet_Repository` | `includes/client-portal/repositories/class-dps-pet-repository.php` | Acesso a dados de pets do cliente |
| `DPS_Appointment_Repository` | `includes/client-portal/repositories/class-dps-appointment-repository.php` | Acesso a dados de agendamentos do cliente |

**Hooks consumidos**:
- ~~`dps_settings_nav_tabs`~~: (migrado para sistema moderno) abas "Portal do Cliente" e "Logins de Clientes" registradas em `DPS_Settings_Frontend`
- ~~`dps_settings_sections`~~: (migrado para sistema moderno) renderizaÃ§Ã£o via callbacks em `register_tab()`
- Hooks do add-on de Pagamentos para links de quitaÃ§Ã£o via Mercado Pago
- `dps_client_page_header_actions`: adiciona botÃ£o "Link de AtualizaÃ§Ã£o" no header da pÃ¡gina de detalhes do cliente

**Hooks disparados**:
- `dps_client_portal_before_content`: disparado apÃ³s o menu de navegaÃ§Ã£o e antes das seÃ§Ãµes de conteÃºdo; passa $client_id como parÃ¢metro; Ãºtil para adicionar conteÃºdo no topo do portal (ex: widgets, assistentes)
- `dps_client_portal_after_content`: disparado ao final do portal, antes do fechamento do container principal; passa $client_id como parÃ¢metro
- `dps_portal_tabs` (filter): filtra o array de abas do portal; passa $tabs e $client_id
- `dps_portal_before_{tab}_content` / `dps_portal_after_{tab}_content` (action): disparados antes/depois do conteÃºdo de cada aba (inicio, agendamentos, pagamentos, pet-history, galeria, fidelidade, reviews, mensagens, dados); passa $client_id
- `dps_portal_custom_tab_panels` (action): renderiza painÃ©is de abas customizadas; passa $client_id e $tabs
- `dps_portal_profile_update_link_generated`: disparado quando um link de atualizaÃ§Ã£o de perfil Ã© gerado; passa $client_id e $update_url como parÃ¢metros
- `dps_portal_profile_updated`: disparado quando o cliente atualiza seu perfil; passa $client_id como parÃ¢metro
- `dps_portal_new_pet_created`: disparado quando um novo pet Ã© cadastrado via formulÃ¡rio de atualizaÃ§Ã£o; passa $pet_id e $client_id como parÃ¢metros
- `dps_portal_tosa_consent_link_generated`: disparado ao gerar link de consentimento; passa $client_id e $consent_url
- `dps_portal_tosa_consent_saved`: disparado ao salvar consentimento; passa $client_id
- `dps_portal_tosa_consent_revoked`: disparado ao revogar consentimento; passa $client_id
- `dps_portal_after_update_preferences` (action): disparado apÃ³s salvar preferÃªncias de notificaÃ§Ã£o; passa $client_id
- `dps_portal_before_render` / `dps_portal_after_auth_check` / `dps_portal_client_authenticated` (actions): hooks do ciclo de vida do shortcode
- `dps_portal_access_notification_sent` (action): disparado apÃ³s enviar notificaÃ§Ã£o de acesso; passa $client_id, $sent, $access_date, $ip_address
- `dps_portal_review_url` (filter): permite filtrar a URL de avaliaÃ§Ã£o do Google

**MÃ©todos pÃºblicos da classe `DPS_Client_Portal`**:
- `get_current_client_id()`: retorna o ID do cliente autenticado via sessÃ£o ou usuÃ¡rio WordPress (0 se nÃ£o autenticado); permite que add-ons obtenham o cliente logado no portal

**FunÃ§Ãµes helper globais**:
- `dps_get_portal_page_url()`: retorna URL da pÃ¡gina do portal (configurada ou fallback)
- `dps_get_portal_page_id()`: retorna ID da pÃ¡gina do portal (configurada ou fallback)
- `dps_get_tosa_consent_page_url()`: retorna URL da pÃ¡gina de consentimento (configurada ou fallback)

**Metadados de cliente utilizados** (meta keys em `dps_cliente` CPT):
- `client_notification_reminders` (default '1'): preferÃªncia de lembretes de agendamento
- `client_notification_payments` (default '1'): preferÃªncia de notificaÃ§Ãµes financeiras
- `client_notification_promotions` (default '0'): preferÃªncia de promoÃ§Ãµes
- `client_notification_updates` (default ''): preferÃªncia de atualizaÃ§Ãµes do sistema

**DependÃªncias**:
- Depende do plugin base para CPTs de clientes e pets
- Integra-se com add-on Financeiro para exibir pendÃªncias e parcelas (aba Pagamentos)
- Integra-se com add-on de Fidelidade para exibir cÃ³digo de indicaÃ§Ã£o

**Introduzido em**: v0.1.0 (estimado)
**VersÃ£o atual**: v2.1.0

**ObservaÃ§Ãµes**:
- JÃ¡ segue padrÃ£o modular com estrutura `includes/` e `assets/`
- Sistema de tokens com suporte a temporÃ¡rios (30min) e permanentes (atÃ© revogaÃ§Ã£o)
- Cleanup automÃ¡tico de tokens expirados via cron job hourly
- ConfiguraÃ§Ã£o centralizada da pÃ¡gina do portal via interface administrativa
- Menu administrativo registrado sob `desi-pet-shower` desde v2.1.0
- 2FA opcional via e-mail (cÃ³digos hashed com `wp_hash_password`, 10min expiraÃ§Ã£o, 5 tentativas max)
- Remember-me: cookie permanente (HttpOnly, Secure, SameSite=Strict, 90 dias)
- SugestÃµes inteligentes: anÃ¡lise de atÃ© 20 atendimentos por pet (intervalo mÃ©dio, top 3 serviÃ§os, urgÃªncia)

**AnÃ¡lise de Layout e UX**:
- Consulte `docs/analysis/CLIENT_PORTAL_ADDON_DEEP_ANALYSIS.md` para anÃ¡lise detalhada de usabilidade e arquitetura do portal
- Consulte `docs/screenshots/CLIENT_PORTAL_REBRANDING_SCREENSHOTS.md` para registro visual e resumo executivo das melhorias aplicadas
- Portal usa design DPS Signature com tabs, cards, lightbox, progress bar stepper, formulÃ¡rios com validaÃ§Ã£o real-time
- Responsividade em 480px, 768px e 1024px; suporte a `prefers-reduced-motion`

---

### Assistente de IA (`desi-pet-shower-ai_addon`)

**DiretÃ³rio**: `plugins/desi-pet-shower-ai`

**PropÃ³sito e funcionalidades principais**:
- Fornecer assistente virtual inteligente no Portal do Cliente
- Responder perguntas EXCLUSIVAMENTE sobre: Banho e Tosa, serviÃ§os, agendamentos, histÃ³rico, pagamentos, fidelidade, assinaturas e dados do cliente/pet
- NÃƒO responder sobre assuntos aleatÃ³rios fora do contexto (polÃ­tica, religiÃ£o, tecnologia, etc.)
- Integrar-se com OpenAI via API Chat Completions (GPT-3.5 Turbo / GPT-4)

**Shortcodes expostos**: Nenhum (integra-se diretamente ao Portal via hook)

**CPTs, tabelas e opÃ§Ãµes**:
- NÃ£o cria CPTs ou tabelas prÃ³prias
- Option: `dps_ai_settings` (armazena configuraÃ§Ãµes: enabled, api_key, model, temperature, timeout, max_tokens)

**Hooks consumidos**:
- `dps_client_portal_before_content`: renderiza widget de chat no topo do portal (apÃ³s navegaÃ§Ã£o, antes das seÃ§Ãµes)

**Hooks disparados**: Nenhum

**Endpoints AJAX**:
- `dps_ai_portal_ask` (wp_ajax e wp_ajax_nopriv): processa perguntas do cliente e retorna respostas da IA

**DependÃªncias**:
- **ObrigatÃ³rio**: Client Portal (fornece autenticaÃ§Ã£o e shortcode `[dps_client_portal]`)
- **Opcional**: Finance, Loyalty, Services (enriquecem contexto disponÃ­vel para a IA)

**Introduzido em**: v1.0.0

**Assets**:
- `assets/js/dps-ai-portal.js`: gerencia widget de chat e envio de perguntas via AJAX
- `assets/css/dps-ai-portal.css`: estilos minimalistas seguindo paleta visual DPS

**Arquitetura interna**:
- `includes/class-dps-ai-client.php`: cliente da API OpenAI com tratamento de erros e timeouts
- `includes/class-dps-ai-assistant.php`: lÃ³gica do assistente (system prompt restritivo, montagem de contexto, filtro de palavras-chave)
- `includes/class-dps-ai-integration-portal.php`: integraÃ§Ã£o com Portal do Cliente (widget, AJAX handlers)

**System Prompt e Regras**:
- Prompt restritivo define domÃ­nio permitido (banho/tosa, pet shop, sistema DPS)
- ProÃ­be explicitamente assuntos fora do contexto
- Instrui a IA a recusar educadamente perguntas inadequadas
- Recomenda procurar veterinÃ¡rio para problemas de saÃºde graves do pet
- ProÃ­be inventar descontos, promoÃ§Ãµes ou alteraÃ§Ãµes de plano nÃ£o documentadas
- Exige honestidade quando dados nÃ£o forem encontrados no sistema

**Filtro Preventivo**:
- Antes de chamar API, valida se pergunta contÃ©m palavras-chave do contexto (pet, banho, tosa, agendamento, pagamento, etc.)
- Economiza chamadas de API e protege contra perguntas totalmente fora de escopo
- Resposta padrÃ£o retornada sem chamar API se pergunta nÃ£o passar no filtro

**Contexto Fornecido Ã  IA**:
- Dados do cliente (nome, telefone, email)
- Lista de pets cadastrados (nome, raÃ§a, porte, idade)
- Ãšltimos 5 agendamentos (data, status, serviÃ§os)
- PendÃªncias financeiras (se Finance add-on ativo)
- Pontos de fidelidade (se Loyalty add-on ativo)

**Comportamento em CenÃ¡rios**:
- **IA ativa e funcionando**: Widget aparece e processa perguntas normalmente
- **IA desabilitada ou sem API key**: Widget nÃ£o aparece; Portal funciona normalmente
- **Falha na API**: Mensagem amigÃ¡vel exibida; Portal continua funcional

**SeguranÃ§a**:
- API key NUNCA exposta no JavaScript (chamadas server-side only)
- Nonces em todas as requisiÃ§Ãµes AJAX
- SanitizaÃ§Ã£o de entrada do usuÃ¡rio
- ValidaÃ§Ã£o de cliente logado antes de processar pergunta
- Timeout configurÃ¡vel para evitar requisiÃ§Ãµes travadas
- Logs de erro apenas no server (error_log, nÃ£o expostos ao cliente)

**Interface Administrativa**:
- Menu: **desi.pet by PRObst > Assistente de IA**
- ConfiguraÃ§Ãµes: ativar/desativar IA, API key, modelo GPT, temperatura, timeout, max_tokens
- DocumentaÃ§Ã£o inline sobre comportamento do assistente

**ObservaÃ§Ãµes**:
- Sistema totalmente autocontido: falhas nÃ£o afetam funcionamento do Portal
- Custo por requisiÃ§Ã£o varia conforme modelo escolhido (GPT-3.5 Turbo recomendado para custo/benefÃ­cio)
- Consulte `plugins/desi-pet-shower-ai/README.md` para documentaÃ§Ã£o completa de uso e manutenÃ§Ã£o

---

### Financeiro (`desi-pet-shower-finance_addon`)

**DiretÃ³rio**: `plugins/desi-pet-shower-finance`

**PropÃ³sito e funcionalidades principais**:
- Gerenciar transaÃ§Ãµes financeiras e cobranÃ§as
- Sincronizar lanÃ§amentos com agendamentos
- Suportar quitaÃ§Ã£o parcial e geraÃ§Ã£o de documentos
- Integrar com outros add-ons para bonificaÃ§Ãµes e assinaturas

**Shortcodes expostos**: Sim (nÃ£o especificados na documentaÃ§Ã£o atual)

**CPTs, tabelas e opÃ§Ãµes**:
- Tabela: `dps_transacoes` (lanÃ§amentos financeiros)
- Tabela: `dps_parcelas` (parcelas de cobranÃ§as)

**Hooks consumidos**:
- `dps_finance_cleanup_for_appointment`: remove lanÃ§amentos ao excluir agendamento
- `dps_base_nav_tabs_*`: adiciona aba "Financeiro"
- `dps_base_sections_*`: renderiza seÃ§Ã£o financeira

**Hooks disparados**:
- `dps_finance_booking_paid`: disparado quando cobranÃ§a Ã© marcada como paga

**DependÃªncias**:
- Depende do plugin base para estrutura de navegaÃ§Ã£o
- Fornece infraestrutura para add-ons de Pagamentos, Assinaturas e Fidelidade

**Introduzido em**: v0.1.0

**ObservaÃ§Ãµes**:
- JÃ¡ segue padrÃ£o modular com classes auxiliares em `includes/`
- Tabela compartilhada por mÃºltiplos add-ons; mudanÃ§as de schema requerem migraÃ§Ã£o cuidadosa

---

### Pagamentos (`desi-pet-shower-payment_addon`)

**DiretÃ³rio**: `plugins/desi-pet-shower-payment`

**PropÃ³sito e funcionalidades principais**:
- Integrar com Mercado Pago para geraÃ§Ã£o de links de pagamento
- Processar notificaÃ§Ãµes de webhook para atualizaÃ§Ã£o de status
- Injetar mensagens de cobranÃ§a no WhatsApp via add-on de Agenda
- Gerenciar credenciais do Mercado Pago de forma segura

**Shortcodes expostos**: Nenhum

**Classes principais**:
- `DPS_Payment_Addon`: Classe principal do add-on (gerencia hooks e integraÃ§Ã£o)
- `DPS_MercadoPago_Config` (v1.1.0+): Gerencia credenciais do Mercado Pago com ordem de prioridade

**CPTs, tabelas e opÃ§Ãµes**:
- Options: `dps_mercadopago_access_token`, `dps_pix_key`, `dps_mercadopago_webhook_secret` (credenciais Mercado Pago)
- **IMPORTANTE (v1.1.0+)**: Recomendado definir credenciais via constantes em `wp-config.php` para produÃ§Ã£o:
  - `DPS_MERCADOPAGO_ACCESS_TOKEN`: Token de acesso da API Mercado Pago
  - `DPS_MERCADOPAGO_WEBHOOK_SECRET`: Secret para validaÃ§Ã£o de webhooks
  - `DPS_MERCADOPAGO_PUBLIC_KEY`: Chave pÃºblica (opcional)
- Ordem de prioridade: constantes wp-config.php â†’ options em banco de dados
- Metadados em agendamentos (v1.1.0+):
  - `_dps_payment_link_status`: Status da geraÃ§Ã£o do link ('success' | 'error' | 'not_requested')
  - `_dps_payment_last_error`: Detalhes do Ãºltimo erro (array: code, message, timestamp, context)

**Hooks consumidos**:
- `dps_base_after_save_appointment`: Gera link de pagamento quando agendamento Ã© finalizado
- `dps_agenda_whatsapp_message`: Injeta link de pagamento na mensagem de cobranÃ§a
- `init` (prioridade 1): Processa webhooks cedo no ciclo de inicializaÃ§Ã£o do WordPress

**Hooks disparados**: Nenhum

**DependÃªncias**:
- Depende do add-on Financeiro para criar transaÃ§Ãµes
- Integra-se com add-on de Agenda para envio de links via WhatsApp

**Introduzido em**: v0.1.0 (estimado)

**VersÃ£o atual**: v1.1.0

**MudanÃ§as na v1.1.0**:
- Classe `DPS_MercadoPago_Config` para gerenciamento seguro de credenciais
- Suporte para constantes em wp-config.php (recomendado para produÃ§Ã£o)
- Tratamento de erros aprimorado com logging detalhado
- Flags de status em agendamentos para rastreamento de falhas
- Interface administrativa mostra campos readonly quando credenciais vÃªm de constantes
- ValidaÃ§Ã£o completa de respostas da API Mercado Pago

**MÃ©todos principais**:
- `DPS_MercadoPago_Config::get_access_token()`: Retorna access token (constante ou option)
- `DPS_MercadoPago_Config::get_webhook_secret()`: Retorna webhook secret (constante ou option)
- `DPS_MercadoPago_Config::is_*_from_constant()`: Verifica se credencial vem de constante
- `DPS_MercadoPago_Config::get_masked_credential()`: Retorna Ãºltimos 4 caracteres para exibiÃ§Ã£o
- `DPS_Payment_Addon::create_payment_preference()`: Cria preferÃªncia de pagamento via API MP
- `DPS_Payment_Addon::log_payment_error()`: Logging centralizado de erros de cobranÃ§a
- `DPS_Payment_Addon::process_payment_notification()`: Processa notificaÃ§Ãµes de webhook
- `DPS_Payment_Addon::maybe_generate_payment_link()`: Gera link automaticamente para finalizados

**ObservaÃ§Ãµes**:
- ValidaÃ§Ã£o de webhook aplicada apenas quando requisiÃ§Ã£o traz indicadores de notificaÃ§Ã£o do MP
- Requer token de acesso e chave PIX configurados (via constantes ou options)
- **IMPORTANTE**: ConfiguraÃ§Ã£o do webhook secret Ã© obrigatÃ³ria para processamento automÃ¡tico de pagamentos. Veja documentaÃ§Ã£o completa em `plugins/desi-pet-shower-payment/WEBHOOK_CONFIGURATION.md`
- **SEGURANÃ‡A**: Em produÃ§Ã£o, sempre defina credenciais via constantes em wp-config.php para evitar armazenamento em texto plano no banco de dados
- Logs de erro incluem contexto completo para debugging (HTTP code, response body, timestamps)
- Flags de status permitem rastreamento e retry de falhas na geraÃ§Ã£o de links

---

### Push Notifications (`desi-pet-shower-push_addon`)

**DiretÃ³rio**: `plugins/desi-pet-shower-push`

**PropÃ³sito e funcionalidades principais**:
- Enviar resumo diÃ¡rio de agendamentos para equipe administrativa
- Enviar relatÃ³rio financeiro diÃ¡rio com atendimentos e transaÃ§Ãµes
- Enviar relatÃ³rio semanal de pets inativos (sem atendimento hÃ¡ 30 dias)
- Integrar com e-mail (via `wp_mail()`) e Telegram Bot API
- HorÃ¡rios e dias configurÃ¡veis para cada tipo de notificaÃ§Ã£o

**Shortcodes expostos**: Nenhum

**CPTs, tabelas e opÃ§Ãµes**:

| Option | Tipo | DescriÃ§Ã£o |
|--------|------|-----------|
| `dps_push_emails_agenda` | array | Lista de emails para agenda diÃ¡ria |
| `dps_push_emails_report` | array | Lista de emails para relatÃ³rio financeiro |
| `dps_push_agenda_time` | string | HorÃ¡rio do resumo de agendamentos (HH:MM) |
| `dps_push_report_time` | string | HorÃ¡rio do relatÃ³rio financeiro (HH:MM) |
| `dps_push_weekly_day` | string | Dia da semana para relatÃ³rio semanal (english) |
| `dps_push_weekly_time` | string | HorÃ¡rio do relatÃ³rio semanal (HH:MM) |
| `dps_push_inactive_days` | int | Dias de inatividade para considerar pet inativo (padrÃ£o: 30) |
| `dps_push_agenda_enabled` | bool | Ativar/desativar agenda diÃ¡ria |
| `dps_push_report_enabled` | bool | Ativar/desativar relatÃ³rio financeiro |
| `dps_push_weekly_enabled` | bool | Ativar/desativar relatÃ³rio semanal |
| `dps_push_telegram_token` | string | Token do bot do Telegram |
| `dps_push_telegram_chat` | string | ID do chat/grupo Telegram |

**Menus administrativos**:
- **NotificaÃ§Ãµes** (`dps-push-notifications`): configuraÃ§Ãµes de destinatÃ¡rios, horÃ¡rios e integraÃ§Ã£o Telegram

**Hooks consumidos**:
- Nenhum hook do sistema de configuraÃ§Ãµes (usa menu admin prÃ³prio)

**Hooks disparados**:

| Hook | Tipo | ParÃ¢metros | DescriÃ§Ã£o |
|------|------|------------|-----------|
| `dps_send_agenda_notification` | cron | - | Dispara envio da agenda diÃ¡ria |
| `dps_send_daily_report` | cron | - | Dispara envio do relatÃ³rio financeiro |
| `dps_send_weekly_inactive_report` | cron | - | Dispara envio do relatÃ³rio de pets inativos |
| `dps_send_push_notification` | action | `$message`, `$context` | Permite add-ons enviarem notificaÃ§Ãµes via Telegram |
| `dps_push_notification_content` | filter | `$content`, `$appointments` | Filtra conteÃºdo do email antes de enviar |
| `dps_push_notification_recipients` | filter | `$recipients` | Filtra destinatÃ¡rios da agenda diÃ¡ria |
| `dps_daily_report_recipients` | filter | `$recipients` | Filtra destinatÃ¡rios do relatÃ³rio financeiro |
| `dps_daily_report_content` | filter | `$content`, `$appointments`, `$trans` | Filtra conteÃºdo do relatÃ³rio |
| `dps_daily_report_html` | filter | `$html`, `$appointments`, `$trans` | Filtra HTML do relatÃ³rio |
| `dps_weekly_inactive_report_recipients` | filter | `$recipients` | Filtra destinatÃ¡rios do relatÃ³rio semanal |

**DependÃªncias**:
- **ObrigatÃ³ria**: Plugin base DPS (verifica `DPS_Base_Plugin`)
- **Opcional**: Finance Add-on (para relatÃ³rio financeiro com tabela `dps_transacoes`)

**Introduzido em**: v0.1.0 (estimado)

**VersÃ£o atual**: 1.2.0

**ObservaÃ§Ãµes**:
- Implementa `register_deactivation_hook` corretamente para limpar cron jobs
- Usa timezone do WordPress para agendamentos (`get_option('timezone_string')`)
- Emails enviados em formato HTML com headers `Content-Type: text/html; charset=UTF-8`
- IntegraÃ§Ã£o Telegram envia mensagens em texto plano com `parse_mode` HTML
- Threshold de inatividade configurÃ¡vel via interface admin (padrÃ£o: 30 dias)
- Interface administrativa integrada na pÃ¡gina de NotificaÃ§Ãµes sob menu desi.pet by PRObst
- **v1.2.0**: Menu admin visÃ­vel, botÃµes de teste para relatÃ³rios e Telegram, uninstall.php atualizado

**AnÃ¡lise completa**: Consulte `docs/analysis/PUSH_ADDON_ANALYSIS.md` para anÃ¡lise detalhada de cÃ³digo, funcionalidades e melhorias propostas

---

### Cadastro PÃºblico (`desi-pet-shower-registration_addon`)

**DiretÃ³rio**: `plugins/desi-pet-shower-registration`

**PropÃ³sito e funcionalidades principais**:
- Permitir cadastro pÃºblico de clientes e pets via formulÃ¡rio web
- Integrar com Google Maps para autocomplete de endereÃ§os
- Disparar hook para outros add-ons apÃ³s criaÃ§Ã£o de cliente

**Shortcodes expostos**:
- `[dps_registration_form]`: renderiza formulÃ¡rio de cadastro pÃºblico

**CPTs, tabelas e opÃ§Ãµes**:
- NÃ£o cria CPTs prÃ³prios; cria posts do tipo `dps_client` e `dps_pet`
- Options: `dps_google_maps_api_key` (chave de API do Google Maps)

**Hooks consumidos**: Nenhum

**Hooks disparados**:
- `dps_registration_after_client_created`: disparado apÃ³s criar novo cliente

**DependÃªncias**:
- Depende do plugin base para CPTs de cliente e pet
- Integra-se com add-on de Fidelidade para capturar cÃ³digos de indicaÃ§Ã£o

**Introduzido em**: v0.1.0 (estimado)

**ObservaÃ§Ãµes**:
- Sanitiza todas as entradas antes de criar posts
- Arquivo Ãºnico de 636 linhas; candidato a refatoraÃ§Ã£o futura

---

### Frontend (`desi-pet-shower-frontend`)

**DiretÃ³rio**: `plugins/desi-pet-shower-frontend`

**PropÃ³sito e funcionalidades principais**:
- Consolidar experiÃªncias frontend (cadastro, agendamento, configuraÃ§Ãµes) em add-on modular
- Arquitetura com mÃ³dulos independentes, feature flags e camada de compatibilidade
- Rollout controlado: cada mÃ³dulo pode ser habilitado/desabilitado individualmente
- **[Fase 2]** MÃ³dulo Registration operacional em dual-run com o add-on legado
- **[Fase 3]** MÃ³dulo Booking operacional em dual-run com o add-on legado
- **[Fase 4]** MÃ³dulo Settings integrado ao sistema de abas de configuraÃ§Ãµes
- **[Fase 7.1]** PreparaÃ§Ã£o: abstracts, template engine, hook bridges, componentes DPS Signature, flags v2
- **[Fase 7.2]** Registration V2: formulÃ¡rio nativo 100% independente do legado (cadastro + pets + reCAPTCHA + email confirmation)
- **[Fase 7.3]** Booking V2: wizard nativo 5-step 100% independente do legado (cliente â†’ pets â†’ serviÃ§os â†’ data/hora â†’ confirmaÃ§Ã£o + extras TaxiDog/Tosa)

**Shortcodes expostos**:
- `dps_registration_form` â€” quando flag `registration` ativada, o mÃ³dulo assume o shortcode (wrapper sobre o legado com surface DPS Signature)
- `dps_booking_form` â€” quando flag `booking` ativada, o mÃ³dulo assume o shortcode (wrapper sobre o legado com surface DPS Signature)
- `dps_registration_v2` â€” quando flag `registration_v2` ativada, formulÃ¡rio nativo DPS Signature (100% independente do legado)
- `dps_booking_v2` â€” quando flag `booking_v2` ativada, wizard nativo DPS Signature de 5 steps (100% independente do legado)

**CPTs, tabelas e opÃ§Ãµes**:
- Option: `dps_frontend_feature_flags` â€” controle de rollout por mÃ³dulo (flags: `registration`, `booking`, `settings`, `registration_v2`, `booking_v2`)
- Option: `dps_frontend_usage_counters` â€” contadores de telemetria por mÃ³dulo
- Transient: `dps_booking_confirmation_{user_id}` â€” confirmaÃ§Ã£o de agendamento v2 (TTL 5min)

**Hooks consumidos** (Fase 2 â€” mÃ³dulo Registration v1 dual-run):
- `dps_registration_after_fields` (preservado â€” consumido pelo Loyalty)
- `dps_registration_after_client_created` (preservado â€” consumido pelo Loyalty)
- `dps_registration_spam_check` (preservado)
- `dps_registration_agenda_url` (preservado)

**Hooks consumidos** (Fase 3 â€” mÃ³dulo Booking v1 dual-run):
- `dps_base_after_save_appointment` (preservado â€” consumido por stock, payment, groomers, calendar, communications, push, services e booking)
- `dps_base_appointment_fields` (preservado)
- `dps_base_appointment_assignment_fields` (preservado)

**Hooks consumidos** (Fase 4 â€” mÃ³dulo Settings):
- `dps_settings_register_tabs` â€” registra aba "Frontend" via `DPS_Settings_Frontend::register_tab()`
- `dps_settings_save_save_frontend` â€” processa salvamento das feature flags

**Hooks disparados** (Fase 7 â€” mÃ³dulos nativos V2):
- `dps_registration_v2_before_render` â€” antes de renderizar formulÃ¡rio de cadastro v2
- `dps_registration_v2_after_render` â€” apÃ³s renderizar formulÃ¡rio de cadastro v2
- `dps_registration_v2_client_created` â€” apÃ³s criar cliente via v2 (bridge: dispara hooks legados do Loyalty primeiro)
- `dps_registration_v2_pet_created` â€” apÃ³s criar pet via v2
- `dps_registration_spam_check` â€” filtro anti-spam (reusa hook legado via bridge)
- `dps_booking_v2_before_render` â€” antes de renderizar wizard de booking v2
- `dps_booking_v2_step_render` â€” ao renderizar step do wizard
- `dps_booking_v2_step_validate` â€” filtro de validaÃ§Ã£o por step
- `dps_booking_v2_before_process` â€” antes de criar agendamento v2
- `dps_booking_v2_after_process` â€” apÃ³s processar agendamento v2
- `dps_booking_v2_appointment_created` â€” apÃ³s criar agendamento v2

**Hooks de bridge** (Fase 7 â€” CRÃTICO: legado PRIMEIRO, v2 DEPOIS):
- `dps_base_after_save_appointment` â€” 8 consumidores: Stock, Payment, Groomers, Calendar, Communications, Push, Services, Booking
- `dps_base_appointment_fields` â€” Services: injeÃ§Ã£o de campos
- `dps_base_appointment_assignment_fields` â€” Groomers: campos de atribuiÃ§Ã£o
- `dps_registration_after_client_created` â€” Loyalty: cÃ³digo de indicaÃ§Ã£o

**AJAX endpoints** (Fase 7.3 â€” Booking V2):
- `wp_ajax_dps_booking_search_client` â€” busca cliente por telefone (nonce + capability)
- `wp_ajax_dps_booking_get_pets` â€” lista pets do cliente com paginaÃ§Ã£o (nonce + capability)
- `wp_ajax_dps_booking_get_services` â€” serviÃ§os ativos com preÃ§os por porte (nonce + capability)
- `wp_ajax_dps_booking_get_slots` â€” horÃ¡rios livres 08:00-18:00/30min (nonce + capability)
- `wp_ajax_dps_booking_validate_step` â€” validaÃ§Ã£o server-side por step (nonce + capability)

**DependÃªncias**:
- Depende do plugin base (DPS_Base_Plugin + design tokens CSS)
- MÃ³dulo Registration v1 depende de `DPS_Registration_Addon` (add-on legado) para dual-run
- MÃ³dulo Booking v1 depende de `DPS_Booking_Addon` (add-on legado) para dual-run
- MÃ³dulos V2 nativos (Registration V2, Booking V2) sÃ£o 100% independentes dos add-ons legados
- MÃ³dulo Settings depende de `DPS_Settings_Frontend` (sistema de abas do base)

**Arquitetura interna**:
- `DPS_Frontend_Addon` â€” orquestrador com injeÃ§Ã£o de dependÃªncias
- `DPS_Frontend_Module_Registry` â€” registro e boot de mÃ³dulos
- `DPS_Frontend_Feature_Flags` â€” controle de rollout persistido
- `DPS_Frontend_Compatibility` â€” bridges para legado
- `DPS_Frontend_Assets` â€” enqueue condicional DPS Signature
- `DPS_Frontend_Logger` â€” observabilidade via error_log + telemetria batch
- `DPS_Frontend_Request_Guard` â€” seguranÃ§a centralizada (nonce, capability, sanitizaÃ§Ã£o)
- `DPS_Template_Engine` â€” renderizaÃ§Ã£o com suporte a override via tema (dps-templates/)
- `DPS_Frontend_Registration_Module` â€” v1 dual-run: assume shortcode, delega lÃ³gica ao legado
- `DPS_Frontend_Booking_Module` â€” v1 dual-run: assume shortcode, delega lÃ³gica ao legado
- `DPS_Frontend_Settings_Module` â€” registra aba de configuraÃ§Ãµes com controles de feature flags
- `DPS_Frontend_Registration_V2_Module` â€” v2 nativo: shortcode `[dps_registration_v2]`, handler, services
- `DPS_Frontend_Booking_V2_Module` â€” v2 nativo: shortcode `[dps_booking_v2]`, handler, services, AJAX
- `DPS_Registration_Hook_Bridge` â€” compatibilidade v1/v2 Registration (legado primeiro, v2 depois)
- `DPS_Booking_Hook_Bridge` â€” compatibilidade v1/v2 Booking (legado primeiro, v2 depois)

**Classes de negÃ³cio â€” Registration V2** (Fase 7.2):
- `DPS_Registration_Handler` â€” pipeline: reCAPTCHA â†’ anti-spam â†’ validaÃ§Ã£o â†’ duplicata â†’ criar cliente â†’ hooks Loyalty â†’ criar pets â†’ email confirmaÃ§Ã£o
- `DPS_Form_Validator` â€” validaÃ§Ã£o de formulÃ¡rio (nome, email, telefone, CPF, pets)
- `DPS_Cpf_Validator` â€” validaÃ§Ã£o CPF mod-11
- `DPS_Client_Service` â€” CRUD para `dps_cliente` (13+ metas)
- `DPS_Pet_Service` â€” CRUD para `dps_pet`
- `DPS_Breed_Provider` â€” dataset de raÃ§as por espÃ©cie (cÃ£o: 44, gato: 20)
- `DPS_Duplicate_Detector` â€” detecÃ§Ã£o por telefone com override admin
- `DPS_Recaptcha_Service` â€” verificaÃ§Ã£o reCAPTCHA v3
- `DPS_Email_Confirmation_Service` â€” token UUID 48h + envio

**Classes de negÃ³cio â€” Booking V2** (Fase 7.3):
- `DPS_Booking_Handler` â€” pipeline: validaÃ§Ã£o â†’ extras â†’ criar appointment â†’ confirmaÃ§Ã£o transient â†’ hook bridge (8 add-ons)
- `DPS_Booking_Validator` â€” validaÃ§Ã£o multi-step (5 steps) + extras (TaxiDog/Tosa)
- `DPS_Appointment_Service` â€” CRUD para `dps_agendamento` (16+ metas, conflitos, busca por cliente)
- `DPS_Booking_Confirmation_Service` â€” transient de confirmaÃ§Ã£o (5min TTL)
- `DPS_Booking_Ajax` â€” 5 endpoints AJAX (busca cliente, pets, serviÃ§os, slots, validaÃ§Ã£o)

**EstratÃ©gia de compatibilidade (Fases 2â€“4)**:
- IntervenÃ§Ã£o mÃ­nima: o legado continua processando formulÃ¡rio, emails, REST, AJAX, settings e cron
- MÃ³dulos de shortcode assumem o shortcode (envolve output na `.dps-frontend` surface) e adicionam CSS extra
- MÃ³dulo de settings registra aba via API moderna `register_tab()` sem alterar abas existentes
- Rollback: desabilitar flag do mÃ³dulo restaura comportamento 100% legado

**CoexistÃªncia v1/v2** (Fase 7):
- Shortcodes v1 (`[dps_registration_form]`, `[dps_booking_form]`) e v2 (`[dps_registration_v2]`, `[dps_booking_v2]`) podem estar ativos simultaneamente
- Feature flags independentes: `registration` (v1), `registration_v2` (v2), `booking` (v1), `booking_v2` (v2)
- Hook bridge garante compatibilidade: hooks legados disparam PRIMEIRO, hooks v2 DEPOIS
- Rollback instantÃ¢neo via toggle de flag â€” sem perda de dados

**Introduzido em**: v1.0.0 (Fases 1â€“6), v2.0.0 (Fase 7.1), v2.1.0 (Fase 7.2), v2.2.0 (Fase 7.3), v2.3.0 (Fase 7.4), v2.4.0 (Fase 7.5)

**DocumentaÃ§Ã£o operacional (Fase 5)**:
- `docs/implementation/FRONTEND_ROLLOUT_GUIDE.md` â€” guia de ativaÃ§Ã£o por ambiente
- `docs/implementation/FRONTEND_RUNBOOK.md` â€” diagnÃ³stico e rollback de incidentes
- `docs/qa/FRONTEND_COMPATIBILITY_MATRIX.md` â€” matriz de compatibilidade com todos os add-ons
- `docs/qa/FRONTEND_REMOVAL_READINESS.md` â€” checklist de prontidÃ£o para remoÃ§Ã£o futura

**DocumentaÃ§Ã£o de governanÃ§a (Fase 6)**:
- `docs/refactoring/FRONTEND_DEPRECATION_POLICY.md` â€” polÃ­tica de depreciaÃ§Ã£o (janela mÃ­nima 180 dias, processo de comunicaÃ§Ã£o, critÃ©rios de aceite)
- `docs/refactoring/FRONTEND_REMOVAL_TARGETS.md` â€” lista de alvos com risco, dependÃªncias e esforÃ§o (booking ðŸŸ¢ baixo; registration ðŸŸ¡ mÃ©dio)
- Telemetria de uso: contadores por mÃ³dulo via `dps_frontend_usage_counters`, exibidos na aba Settings

**DocumentaÃ§Ã£o de implementaÃ§Ã£o nativa (Fase 7)**:
- `docs/refactoring/FRONTEND_NATIVE_IMPLEMENTATION_PLAN.md` â€” plano completo com inventÃ¡rio legado, hook bridge, templates, estratÃ©gia de migraÃ§Ã£o

**DocumentaÃ§Ã£o de coexistÃªncia e migraÃ§Ã£o (Fase 7.4)**:
- `docs/implementation/FRONTEND_V2_MIGRATION_GUIDE.md` â€” guia passo a passo de migraÃ§Ã£o v1â†’v2 (7 etapas, comparaÃ§Ã£o de features, checklist, rollback, troubleshooting, WP-CLI)
- SeÃ§Ã£o "Status de CoexistÃªncia v1/v2" na aba Settings com indicadores visuais por mÃ³dulo

**ObservaÃ§Ãµes**:
- PHP 8.4 moderno: constructor promotion, readonly properties, typed properties, return types
- Sem singletons: objetos montados por composiÃ§Ã£o no bootstrap
- Assets carregados somente quando ao menos um mÃ³dulo estÃ¡ habilitado (feature flag)
- Roadmap completo em `docs/refactoring/FRONTEND_ADDON_PHASED_ROADMAP.md`

---

### ServiÃ§os (`desi-pet-shower-services_addon`)

**DiretÃ³rio**: `plugins/desi-pet-shower-services`

**PropÃ³sito e funcionalidades principais**:
- Gerenciar catÃ¡logo de serviÃ§os oferecidos
- Definir preÃ§os e duraÃ§Ã£o por porte de pet
- Vincular serviÃ§os aos agendamentos
- Povoar catÃ¡logo padrÃ£o na ativaÃ§Ã£o
- **[v1.2.0]** Centralizar toda lÃ³gica de cÃ¡lculo de preÃ§os via API pÃºblica

**Shortcodes expostos**: Nenhum

**CPTs, tabelas e opÃ§Ãµes**:
- CPT: `dps_service` (registrado via `DPS_CPT_Helper`)
- Metadados: preÃ§os e duraÃ§Ã£o por porte (pequeno, mÃ©dio, grande)

**Hooks consumidos**:
- `dps_base_nav_tabs_*`: adiciona aba "ServiÃ§os"
- `dps_base_sections_*`: renderiza catÃ¡logo e formulÃ¡rios
- Hook de agendamento: adiciona campos de seleÃ§Ã£o de serviÃ§os

**Hooks disparados**: Nenhum

**Endpoints AJAX expostos**:
- `dps_get_services_details`: retorna detalhes de serviÃ§os de um agendamento (movido da Agenda em v1.2.0)

**API PÃºblica** (desde v1.2.0):
A classe `DPS_Services_API` centraliza toda a lÃ³gica de serviÃ§os e cÃ¡lculo de preÃ§os:

```php
// Obter dados completos de um serviÃ§o
$service = DPS_Services_API::get_service( $service_id );
// Retorna: ['id', 'title', 'type', 'category', 'active', 'price', 'price_small', 'price_medium', 'price_large']

// Calcular preÃ§o de um serviÃ§o por porte
$price = DPS_Services_API::calculate_price( $service_id, 'medio' );
// Aceita: 'pequeno'/'small', 'medio'/'medium', 'grande'/'large'

// Calcular total de um agendamento
$total = DPS_Services_API::calculate_appointment_total(
    $service_ids,  // array de IDs de serviÃ§os
    $pet_ids,      // array de IDs de pets
    [              // contexto opcional
        'custom_prices' => [ service_id => price ],  // preÃ§os personalizados
        'extras' => 50.00,     // valor de extras
        'taxidog' => 25.00,    // valor de taxidog
    ]
);
// Retorna: ['total', 'services_total', 'services_details', 'extras_total', 'taxidog_total']

// Obter detalhes de serviÃ§os de um agendamento
$details = DPS_Services_API::get_services_details( $appointment_id );
// Retorna: ['services' => [['name', 'price'], ...], 'total']
```

**Contrato de integraÃ§Ã£o**:
- Outros add-ons DEVEM usar `DPS_Services_API` para cÃ¡lculos de preÃ§os
- Agenda Add-on delega `dps_get_services_details` para esta API (desde v1.1.0)
- Finance Add-on DEVE usar esta API para obter valores histÃ³ricos
- Portal do Cliente DEVE usar esta API para exibir valores

**DependÃªncias**:
- Depende do plugin base para estrutura de navegaÃ§Ã£o
- Reutiliza `DPS_CPT_Helper` para registro de CPT

**Introduzido em**: v0.1.0
**API pÃºblica**: v1.2.0

---

### EstatÃ­sticas (`desi-pet-shower-stats_addon`)

**DiretÃ³rio**: `plugins/desi-pet-shower-stats`

**PropÃ³sito e funcionalidades principais**:
- Exibir mÃ©tricas de uso do sistema (atendimentos, receita, despesas, lucro)
- Listar serviÃ§os mais recorrentes com grÃ¡fico de barras (Chart.js)
- Filtrar estatÃ­sticas por perÃ­odo personalizado
- Exibir pets inativos com link de reengajamento via WhatsApp
- MÃ©tricas de assinaturas (ativas, pendentes, receita, valor em aberto)
- Sistema de cache via transients (1h para financeiros, 24h para inativos)

**Shortcodes expostos**: Nenhum

**CPTs, tabelas e opÃ§Ãµes**:
- NÃ£o cria CPTs ou tabelas prÃ³prias
- Consulta `dps_transacoes` para mÃ©tricas financeiras
- Consulta CPTs do nÃºcleo: `dps_agendamento`, `dps_cliente`, `dps_pet`, `dps_subscription`, `dps_service`
- Transients criados: `dps_stats_total_revenue_*`, `dps_stats_financial_*`, `dps_stats_appointments_*`, `dps_stats_inactive_*`

**Hooks consumidos**:
- `dps_base_nav_tabs_after_history` (prioridade 20): adiciona aba "EstatÃ­sticas"
- `dps_base_sections_after_history` (prioridade 20): renderiza dashboard de estatÃ­sticas
- `admin_post_dps_clear_stats_cache`: processa limpeza de cache

**Hooks disparados**: Nenhum

**FunÃ§Ãµes globais expostas**:
- `dps_get_total_revenue( $start_date, $end_date )`: retorna receita total paga no perÃ­odo
- `dps_stats_build_cache_key( $prefix, $start, $end )`: gera chave de cache Ãºnica
- `dps_stats_clear_cache()`: limpa todos os transients de estatÃ­sticas (requer capability `manage_options`)

**DependÃªncias**:
- **ObrigatÃ³ria**: Plugin base DPS (verifica `DPS_Base_Plugin`)
- **Recomendada**: Finance Add-on (para tabela `dps_transacoes` e mÃ©tricas financeiras)
- **Opcional**: Services Add-on (para tÃ­tulos de serviÃ§os no ranking)
- **Opcional**: Subscription Add-on (para mÃ©tricas de assinaturas)
- **Opcional**: DPS_WhatsApp_Helper (para links de reengajamento)

**Introduzido em**: v0.1.0 (estimado)

**VersÃ£o atual**: 1.0.0

**ObservaÃ§Ãµes**:
- Arquivo Ãºnico de ~600 linhas; candidato a refatoraÃ§Ã£o modular futura
- Usa Chart.js (CDN) para grÃ¡fico de barras de serviÃ§os
- Cache de 1 hora para mÃ©tricas financeiras, 24 horas para entidades inativas
- Limites de seguranÃ§a: 500 clientes e 1000 agendamentos por consulta
- Coleta dados de espÃ©cies/raÃ§as/mÃ©dia por cliente mas nÃ£o exibe (oportunidade de melhoria)

**AnÃ¡lise completa**: Consulte `docs/analysis/STATS_ADDON_ANALYSIS.md` para anÃ¡lise detalhada de cÃ³digo, funcionalidades, seguranÃ§a, performance, UX e melhorias propostas (38-58h de esforÃ§o estimado)

---

### Estoque (`desi-pet-shower-stock_addon`)

**DiretÃ³rio**: `plugins/desi-pet-shower-stock`

**PropÃ³sito e funcionalidades principais**:
- Controlar estoque de insumos utilizados nos atendimentos
- Registrar movimentaÃ§Ãµes de entrada e saÃ­da
- Gerar alertas de estoque baixo
- Baixar estoque automaticamente ao concluir atendimentos

**Shortcodes expostos**: Nenhum

**CPTs, tabelas e opÃ§Ãµes**:
- CPT: `dps_stock_item` (registrado via `DPS_CPT_Helper`)
- Capability customizada: `dps_manage_stock`
- Metadados: quantidade atual, mÃ­nima, histÃ³rico de movimentaÃ§Ãµes

**Hooks consumidos**:
- `dps_base_after_save_appointment`: baixa estoque automaticamente ao concluir atendimento
- `dps_base_nav_tabs_after_history`: adiciona aba "Estoque"
- `dps_base_sections_after_history`: renderiza controle de estoque

**Hooks disparados**: Nenhum

**DependÃªncias**:
- Depende do plugin base para estrutura de navegaÃ§Ã£o e hooks de agendamento
- Reutiliza `DPS_CPT_Helper` para registro de CPT

**Introduzido em**: v0.1.0 (estimado)

**ObservaÃ§Ãµes**:
- Arquivo Ãºnico de 432 linhas; candidato a refatoraÃ§Ã£o futura
- Passou a usar navegaÃ§Ã£o integrada ao painel base, removendo menus prÃ³prios

---

### Assinaturas (`desi-pet-shower-subscription_addon`)

**DiretÃ³rio**: `plugins/desi-pet-shower-subscription`

**PropÃ³sito e funcionalidades principais**:
- Gerenciar pacotes mensais de banho e tosa com frequÃªncias semanal (4 atendimentos) ou quinzenal (2 atendimentos)
- Gerar automaticamente os agendamentos do ciclo vinculados Ã  assinatura
- Criar e sincronizar transaÃ§Ãµes financeiras na tabela `dps_transacoes`
- Controlar status de pagamento (pendente, pago, em atraso) por ciclo
- Gerar links de renovaÃ§Ã£o via API do Mercado Pago
- Enviar mensagens de cobranÃ§a via WhatsApp usando `DPS_WhatsApp_Helper`

**Shortcodes expostos**: Nenhum

**CPTs, tabelas e opÃ§Ãµes**:

**CPT `dps_subscription`** (show_ui: false, opera via aba no painel base):

| Meta Key | Tipo | DescriÃ§Ã£o |
|----------|------|-----------|
| `subscription_client_id` | int | ID do cliente (`dps_cliente`) |
| `subscription_pet_id` | int | ID do pet (`dps_pet`) |
| `subscription_service` | string | "Banho" ou "Banho e Tosa" |
| `subscription_frequency` | string | "semanal" (4 atendimentos) ou "quinzenal" (2 atendimentos) |
| `subscription_price` | float | Valor do pacote mensal |
| `subscription_start_date` | date | Data de inÃ­cio do ciclo (Y-m-d) |
| `subscription_start_time` | time | HorÃ¡rio dos atendimentos (H:i) |
| `subscription_payment_status` | string | "pendente", "pago" ou "em_atraso" |
| `dps_subscription_payment_link` | url | Cache do link de pagamento Mercado Pago |
| `dps_generated_cycle_YYYY-mm` | bool | Flag indicando ciclo jÃ¡ gerado (evita duplicaÃ§Ã£o) |
| `dps_cycle_status_YYYY-mm` | string | Status de pagamento do ciclo especÃ­fico |

**Metadados em agendamentos vinculados** (`dps_agendamento`):

| Meta Key | Tipo | DescriÃ§Ã£o |
|----------|------|-----------|
| `subscription_id` | int | ID da assinatura vinculada |
| `subscription_cycle` | string | Ciclo no formato Y-m (ex: "2025-12") |

**Options armazenadas**: Nenhuma (usa credenciais do Payment Add-on via `DPS_MercadoPago_Config`)

**Hooks consumidos**:
- `dps_base_nav_tabs_after_pets`: Adiciona aba "Assinaturas" no painel (prioridade 20)
- `dps_base_sections_after_pets`: Renderiza seÃ§Ã£o de assinaturas (prioridade 20)
- Usa `DPS_MercadoPago_Config::get_access_token()` do Payment Add-on v1.1.0+ (ou options legadas se v1.0.0)

**Hooks disparados**:
- `dps_subscription_payment_status` (action): Permite add-ons de pagamento atualizar status do ciclo
  - **Assinatura**: `do_action( 'dps_subscription_payment_status', int $sub_id, string $cycle_key, string $status )`
  - **ParÃ¢metros**:
    - `$sub_id`: ID da assinatura
    - `$cycle_key`: Ciclo no formato Y-m (ex: "2025-12"), vazio usa ciclo atual
    - `$status`: "paid", "approved", "success" â†’ pago | "failed", "rejected" â†’ em_atraso | outros â†’ pendente
  - **Exemplo de uso**: `do_action( 'dps_subscription_payment_status', 123, '2025-12', 'paid' );`
- `dps_subscription_whatsapp_message` (filter): Permite customizar mensagem de cobranÃ§a via WhatsApp
  - **Assinatura**: `apply_filters( 'dps_subscription_whatsapp_message', string $message, WP_Post $subscription, string $payment_link )`

**Fluxo de geraÃ§Ã£o de agendamentos**:
1. Admin salva assinatura com cliente, pet, serviÃ§o, frequÃªncia, valor, data/hora
2. Sistema calcula datas: semanal = 4 datas (+7 dias cada), quinzenal = 2 datas (+14 dias cada)
3. Remove agendamentos existentes do mesmo ciclo (evita duplicaÃ§Ã£o)
4. Cria novos `dps_agendamento` com metas vinculadas
5. Marca ciclo como gerado (`dps_generated_cycle_YYYY-mm`)
6. Cria/atualiza transaÃ§Ã£o em `dps_transacoes` via Finance Add-on

**Fluxo de renovaÃ§Ã£o**:
1. Quando todos os atendimentos do ciclo sÃ£o finalizados, botÃ£o "Renovar" aparece
2. Admin clica em "Renovar"
3. Sistema avanÃ§a `subscription_start_date` para prÃ³ximo mÃªs (mesmo dia da semana)
4. Reseta `subscription_payment_status` para "pendente"
5. Gera novos agendamentos para o novo ciclo
6. Cria nova transaÃ§Ã£o financeira

**DependÃªncias**:
- **ObrigatÃ³ria**: Plugin base DPS (verifica `DPS_Base_Plugin` na inicializaÃ§Ã£o)
- **Recomendada**: Finance Add-on (para tabela `dps_transacoes` e sincronizaÃ§Ã£o de cobranÃ§as)
- **Recomendada**: Payment Add-on (para geraÃ§Ã£o de links Mercado Pago via API)
- **Opcional**: Communications Add-on (para mensagens via WhatsApp)

**Introduzido em**: v0.2.0

**VersÃ£o atual**: 1.0.0

**ObservaÃ§Ãµes**:
- Arquivo Ãºnico de 995 linhas; candidato a refatoraÃ§Ã£o futura para padrÃ£o modular (`includes/`, `assets/`, `templates/`)
- CSS e JavaScript inline na funÃ§Ã£o `section_subscriptions()`; recomenda-se extrair para arquivos externos
- Usa `DPS_WhatsApp_Helper::get_link_to_client()` para links de cobranÃ§a (desde v1.0.0)
- Cancela assinatura via `wp_trash_post()` (soft delete), preservando dados para possÃ­vel restauraÃ§Ã£o
- ExclusÃ£o permanente remove assinatura E todas as transaÃ§Ãµes financeiras vinculadas
- GeraÃ§Ã£o de links Mercado Pago usa `external_reference` no formato `dps_subscription_{ID}` para rastreamento via webhook

**AnÃ¡lise completa**: Consulte `docs/analysis/SUBSCRIPTION_ADDON_ANALYSIS.md` para anÃ¡lise detalhada de cÃ³digo, funcionalidades e melhorias propostas (32KB, 10 seÃ§Ãµes)

---

### Space Groomers (`desi-pet-shower-game`)

**Diretorio**: `plugins/desi-pet-shower-game`

**Proposito e funcionalidades principais**:
- jogo tematico "Space Groomers: Invasao das Pulgas" para engajamento casual no portal
- canvas + JavaScript puro, sem dependencias externas pesadas
- runs curtas com missao diaria, streak leve, badges locais e resumo pos-run
- integracao automatica com a aba Inicio do portal e com o hub proprio do jogo

**Shortcodes expostos**:
- `[dps_space_groomers]` - renderiza o jogo completo em qualquer pagina

**Persistencia, contratos e REST**:
- `localStorage` segue como fallback local (`dps_sg_progress_v1` + `dps_sg_highscore`)
- `post meta` do cliente e a fonte canonica quando ha portal autenticado (`dps_game_progress_v1`)
- `DPS_Game_Progress_Service` normaliza, faz merge, limita historico, garante idempotencia e mantem a missao corrente coerente
- `DPS_Game_REST` valida nonce custom, respeita sessao do portal e aceita resumo sanitizado de telemetria junto do sync de progresso

**Lifecycle endurecido**:
- fluxo explicito de `start -> waveIntro -> playing -> paused -> gameover/victory -> retry`
- pausa manual por botao e `Escape`
- pausa automatica por `visibilitychange`, `blur` e `orientationchange`
- retomada sempre explicita pelo usuario, sem auto-resume silencioso

**Mecanicas meta atuais**:
- 1 missao rotativa diaria com pool enxuto
- streak simples de retorno
- badges locais desbloqueadas por recordes e marcos
- resumo sincronizado para o portal com missao, streak, recorde, badges e ultima run

**Telemetria e pontos de extensao**:
- frontend despacha `dps-space-groomers-telemetry` e eventos especificos como `game_start`, `pause`, `resume`, `game_over`, `mission_completed`, `run_complete`, `retry`, `sync_success` e `sync_error`
- backend expoe `dps_game_progress_synced` para integracoes de progresso
- backend expoe `dps_game_telemetry_run_complete` para consumo opt-in do resumo da run
- filtro `dps_game_should_log_telemetry` permite ligar auditoria sem impor logging padrao

**Integracao com loyalty**:
- reaproveita `DPS_Loyalty_API::award_game_event_points()` e `dps_loyalty_add_points()`
- contextos expostos: `game_daily_mission`, `game_streak_3`, `game_streak_7`, `game_first_victory`
- `rewardMarkers` no progresso evitam credito duplicado de pontos

**Hooks consumidos**:
- `dps_portal_after_inicio_content`: renderiza o card jogavel na aba Inicio do portal

**Hooks disparados**:
- evento frontend `dps-space-groomers-progress`: notifica outras superficies do portal apos sync bem-sucedido
- action `dps_game_progress_synced`
- action `dps_game_telemetry_run_complete`
- filter `dps_game_should_log_telemetry`

**Dependencias**:
- **Obrigatoria**: Plugin base DPS
- **Opcional**: Client Portal Add-on (sessao e render na aba Inicio)
- **Opcional**: Loyalty Add-on (pontuacao leve por missao/streak/vitoria)

**Versao atual**: 1.4.0

  - **MigraÃ§Ã£o**: usar `DPS_Settings_Frontend::register_tab()` com callback que renderiza o conteÃºdo
  - **Nota**: O sistema moderno de abas jÃ¡ renderiza automaticamente o conteÃºdo via callbacks registrados.

#### PÃ¡gina de detalhes do cliente

- **`dps_client_page_header_badges`** (action) (desde v1.3.0)
  - **ParÃ¢metros**: `$client_id` (int), `$client` (WP_Post)
  - **PropÃ³sito**: adicionar badges ao lado do nome do cliente (ex: nÃ­vel de fidelidade, tags)
  - **Consumido por**: Add-ons de fidelidade para mostrar nÃ­vel/status
  - **Exemplo**:
    ```php
    add_action( 'dps_client_page_header_badges', function( $client_id, $client ) {
        echo '<span class="dps-badge dps-badge--gold">â­ VIP</span>';
    }, 10, 2 );
    ```

- **`dps_client_page_header_actions`** (action) (desde v1.1.0)
  - **ParÃ¢metros**: `$client_id` (int), `$client` (WP_Post), `$base_url` (string)
  - **PropÃ³sito**: adicionar botÃµes de aÃ§Ã£o ao painel de aÃ§Ãµes rÃ¡pidas da pÃ¡gina de detalhes do cliente
  - **AtualizaÃ§Ã£o v1.3.0**: movido para painel dedicado "AÃ§Ãµes RÃ¡pidas" com melhor organizaÃ§Ã£o visual
  - **Consumido por**: Client Portal (link de atualizaÃ§Ã£o de perfil), Tosa Consent (link de consentimento)
  - **Exemplo**:
    ```php
    add_action( 'dps_client_page_header_actions', function( $client_id, $client, $base_url ) {
        echo '<button class="dps-btn-action">Minha AÃ§Ã£o</button>';
    }, 10, 3 );
    ```

- **`dps_client_page_after_personal_section`** (action) (desde v1.2.0)
  - **ParÃ¢metros**: `$client_id` (int), `$client` (WP_Post), `$meta` (array)
  - **PropÃ³sito**: adicionar seÃ§Ãµes personalizadas apÃ³s os dados pessoais do cliente
  - **Consumido por**: Add-ons que precisam exibir informaÃ§Ãµes complementares
  - **Exemplo**:
    ```php
    add_action( 'dps_client_page_after_personal_section', function( $client_id, $client, $meta ) {
        echo '<div class="dps-client-section"><!-- ConteÃºdo personalizado --></div>';
    }, 10, 3 );
    ```

- **`dps_client_page_after_contact_section`** (action) (desde v1.2.0)
  - **ParÃ¢metros**: `$client_id` (int), `$client` (WP_Post), `$meta` (array)
  - **PropÃ³sito**: adicionar seÃ§Ãµes apÃ³s contato e redes sociais
  - **Consumido por**: Add-ons de fidelidade, comunicaÃ§Ãµes avanÃ§adas

- **`dps_client_page_after_pets_section`** (action) (desde v1.2.0)
  - **ParÃ¢metros**: `$client_id` (int), `$client` (WP_Post), `$pets` (array)
  - **PropÃ³sito**: adicionar seÃ§Ãµes apÃ³s a lista de pets do cliente
  - **Consumido por**: Add-ons de assinaturas, pacotes de serviÃ§os

- **`dps_client_page_after_appointments_section`** (action) (desde v1.2.0)
  - **ParÃ¢metros**: `$client_id` (int), `$client` (WP_Post), `$appointments` (array)
  - **PropÃ³sito**: adicionar seÃ§Ãµes apÃ³s o histÃ³rico de atendimentos
  - **Consumido por**: Add-ons financeiros, estatÃ­sticas avanÃ§adas

#### Fluxo de agendamentos

- **`dps_base_appointment_fields`** (action)
  - **ParÃ¢metros**: `$appointment_id` (int, pode ser 0 para novos agendamentos), `$meta` (array)
  - **PropÃ³sito**: adicionar campos customizados ao formulÃ¡rio de agendamento (seÃ§Ã£o "ServiÃ§os e Extras")
  - **Consumido por**: ServiÃ§os (seleÃ§Ã£o de serviÃ§os e extras)

- **`dps_base_appointment_assignment_fields`** (action) (desde v1.8.0)
  - **ParÃ¢metros**: `$appointment_id` (int, pode ser 0 para novos agendamentos), `$meta` (array)
  - **PropÃ³sito**: adicionar campos de atribuiÃ§Ã£o de profissionais ao formulÃ¡rio de agendamento (seÃ§Ã£o "AtribuiÃ§Ã£o")
  - **Consumido por**: Groomers (seleÃ§Ã£o de profissionais responsÃ¡veis)
  - **Nota**: Esta seÃ§Ã£o sÃ³ Ã© renderizada se houver hooks registrados

- **`dps_base_after_save_appointment`** (action)
  - **ParÃ¢metros**: `$appointment_id` (int)
  - **PropÃ³sito**: executar aÃ§Ãµes apÃ³s salvar um agendamento
  - **Consumido por**: ComunicaÃ§Ãµes (envio de notificaÃ§Ãµes), Estoque (baixa automÃ¡tica)

#### Limpeza de dados

- **`dps_finance_cleanup_for_appointment`** (action)
  - **ParÃ¢metros**: `$appointment_id` (int)
  - **PropÃ³sito**: remover dados financeiros associados antes de excluir agendamento
  - **Consumido por**: Financeiro (remove transaÃ§Ãµes vinculadas)

### Hooks de add-ons

#### Add-on Financeiro

- **`dps_finance_booking_paid`** (action)
  - **ParÃ¢metros**: `$transaction_id` (int), `$client_id` (int)
  - **PropÃ³sito**: disparado quando uma cobranÃ§a Ã© marcada como paga
  - **Consumido por**: Campanhas & Fidelidade (bonifica indicador/indicado na primeira cobranÃ§a)

#### Add-on de Cadastro PÃºblico

- **`dps_registration_after_client_created`** (action)
  - **ParÃ¢metros**: `$client_id` (int), `$referral_code` (string|null)
  - **PropÃ³sito**: disparado apÃ³s criar novo cliente via formulÃ¡rio pÃºblico
  - **Consumido por**: Campanhas & Fidelidade (registra indicaÃ§Ãµes)

#### Add-on Portal do Cliente

- **`dps_portal_tabs`** (filter)
  - **ParÃ¢metros**: `$tabs` (array), `$client_id` (int)
  - **PropÃ³sito**: filtrar abas do portal; permite add-ons adicionarem ou removerem abas
  - **Retorno**: array de abas com keys: label, icon, badge (opcional)

- **`dps_portal_before_{tab}_content`** / **`dps_portal_after_{tab}_content`** (action)
  - **ParÃ¢metros**: `$client_id` (int)
  - **PropÃ³sito**: injetar conteÃºdo antes/depois do conteÃºdo de cada aba
  - **Abas disponÃ­veis**: inicio, agendamentos, pagamentos, pet-history, galeria, fidelidade, reviews, mensagens, dados

- **`dps_portal_custom_tab_panels`** (action)
  - **ParÃ¢metros**: `$client_id` (int), `$tabs` (array)
  - **PropÃ³sito**: renderizar painÃ©is de abas customizadas adicionadas via `dps_portal_tabs`

- **`dps_portal_after_update_preferences`** (action)
  - **ParÃ¢metros**: `$client_id` (int)
  - **PropÃ³sito**: executar aÃ§Ãµes apÃ³s salvar preferÃªncias de notificaÃ§Ã£o do cliente

- **`dps_portal_access_notification_sent`** (action)
  - **ParÃ¢metros**: `$client_id` (int), `$sent` (bool), `$access_date` (string), `$ip_address` (string)
  - **PropÃ³sito**: executar aÃ§Ãµes apÃ³s enviar notificaÃ§Ã£o de acesso ao portal

#### Cron jobs de add-ons

- **`dps_agenda_send_reminders`** (action)
  - **FrequÃªncia**: diÃ¡ria
  - **PropÃ³sito**: enviar lembretes de agendamentos prÃ³ximos
  - **Registrado por**: Agenda

- **`dps_comm_send_appointment_reminder`** (action)
  - **FrequÃªncia**: conforme agendado
  - **PropÃ³sito**: enviar lembretes de agendamento via canais configurados
  - **Registrado por**: ComunicaÃ§Ãµes

- **`dps_comm_send_post_service`** (action)
  - **FrequÃªncia**: conforme agendado
  - **PropÃ³sito**: enviar mensagens pÃ³s-atendimento
  - **Registrado por**: ComunicaÃ§Ãµes

- **`dps_send_push_notification`** (action)
  - **ParÃ¢metros**: `$message` (string), `$recipients` (array)
  - **PropÃ³sito**: enviar notificaÃ§Ãµes via Telegram ou e-mail
  - **Registrado por**: Push Notifications

---

## ConsideraÃ§Ãµes de estrutura e integraÃ§Ã£o
- Todos os add-ons se conectam por meio dos *hooks* expostos pelo plugin base (`dps_base_nav_tabs_after_pets`, `dps_base_sections_after_history`, `dps_settings_*`), preservando a renderizaÃ§Ã£o centralizada de navegaÃ§Ã£o/abas feita por `DPS_Base_Frontend`.
- As integraÃ§Ãµes financeiras compartilham a tabela `dps_transacoes`, seja para sincronizar agendamentos (base + financeiro), gerar cobranÃ§as (pagamentos, assinaturas) ou exibir pendÃªncias no portal e na agenda, reforÃ§ando a necessidade de manter o esquema consistente ao evoluir o sistema.

## PadrÃµes de desenvolvimento de add-ons

### Estrutura de arquivos recomendada
Para novos add-ons ou refatoraÃ§Ãµes futuras, recomenda-se seguir a estrutura modular:

```
plugins/desi-pet-shower-NOME_addon/
â”œâ”€â”€ desi-pet-shower-NOME-addon.php    # Arquivo principal (apenas bootstrapping)
â”œâ”€â”€ includes/                          # Classes e lÃ³gica do negÃ³cio
â”‚   â”œâ”€â”€ class-dps-NOME-cpt.php        # Registro de Custom Post Types
â”‚   â”œâ”€â”€ class-dps-NOME-metaboxes.php  # Metaboxes e campos customizados
â”‚   â”œâ”€â”€ class-dps-NOME-admin.php      # Interface administrativa
â”‚   â””â”€â”€ class-dps-NOME-frontend.php   # LÃ³gica do frontend
â”œâ”€â”€ assets/                            # Recursos estÃ¡ticos
â”‚   â”œâ”€â”€ css/                          # Estilos CSS
â”‚   â”‚   â””â”€â”€ NOME-addon.css
â”‚   â””â”€â”€ js/                           # Scripts JavaScript
â”‚       â””â”€â”€ NOME-addon.js
â””â”€â”€ uninstall.php                      # Limpeza de dados na desinstalaÃ§Ã£o
```

**BenefÃ­cios desta estrutura:**
- **SeparaÃ§Ã£o de responsabilidades**: cada classe tem um propÃ³sito claro
- **Manutenibilidade**: mais fÃ¡cil localizar e modificar funcionalidades especÃ­ficas
- **ReutilizaÃ§Ã£o**: classes podem ser testadas e reutilizadas independentemente
- **Performance**: possibilita carregamento condicional de componentes

**Add-ons que jÃ¡ seguem este padrÃ£o:**
- `client-portal_addon`: estrutura bem organizada com `includes/` e `assets/`
- `finance_addon`: possui `includes/` para classes auxiliares

**Add-ons que poderiam se beneficiar de refatoraÃ§Ã£o futura:**
- `backup_addon`: 1338 linhas em um Ãºnico arquivo (anÃ¡lise em `docs/analysis/BACKUP_ADDON_ANALYSIS.md`)
- `loyalty_addon`: 1148 linhas em um Ãºnico arquivo
- `subscription_addon`: 995 linhas em um Ãºnico arquivo (anÃ¡lise em `docs/analysis/SUBSCRIPTION_ADDON_ANALYSIS.md`)
- `registration_addon`: 636 linhas em um Ãºnico arquivo
- `stats_addon`: 538 linhas em um Ãºnico arquivo
- `groomers_addon`: 473 linhas em um Ãºnico arquivo (anÃ¡lise em `docs/analysis/GROOMERS_ADDON_ANALYSIS.md`)
- `stock_addon`: 432 linhas em um Ãºnico arquivo (anÃ¡lise em `docs/analysis/STOCK_ADDON_ANALYSIS.md`)

### Activation e Deactivation Hooks

**Activation Hook (`register_activation_hook`):**
- Criar pÃ¡ginas necessÃ¡rias
- Criar tabelas de banco de dados via `dbDelta()`
- Definir opÃ§Ãµes padrÃ£o do plugin
- Criar roles e capabilities customizadas
- **NÃƒO** agendar cron jobs (use `init` com verificaÃ§Ã£o `wp_next_scheduled`)

**Deactivation Hook (`register_deactivation_hook`):**
- Limpar cron jobs agendados com `wp_clear_scheduled_hook()`
- **NÃƒO** remover dados do usuÃ¡rio (reservado para `uninstall.php`)

**Exemplo de implementaÃ§Ã£o:**
```php
class DPS_Exemplo_Addon {
    public function __construct() {
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );

        add_action( 'init', [ $this, 'maybe_schedule_cron' ] );
        add_action( 'dps_exemplo_cron_event', [ $this, 'execute_cron' ] );
    }

    public function activate() {
        // Criar pÃ¡ginas, tabelas, opÃ§Ãµes padrÃ£o
        $this->create_pages();
        $this->create_database_tables();
    }

    public function deactivate() {
        // Limpar APENAS cron jobs temporÃ¡rios
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
- âœ… `push_addon`: implementa deactivation hook corretamente
- âœ… `agenda_addon`: agora implementa deactivation hook para limpar `dps_agenda_send_reminders`

### PadrÃµes de documentaÃ§Ã£o (DocBlocks)

Todos os mÃ©todos devem seguir o padrÃ£o WordPress de DocBlocks:

```php
/**
 * Breve descriÃ§Ã£o do mÃ©todo (uma linha).
 *
 * DescriÃ§Ã£o mais detalhada explicando o propÃ³sito, comportamento
 * e contexto de uso do mÃ©todo (opcional).
 *
 * @since 1.0.0
 *
 * @param string $param1 DescriÃ§Ã£o do primeiro parÃ¢metro.
 * @param int    $param2 DescriÃ§Ã£o do segundo parÃ¢metro.
 * @param array  $args {
 *     Argumentos opcionais.
 *
 *     @type string $key1 DescriÃ§Ã£o da chave 1.
 *     @type int    $key2 DescriÃ§Ã£o da chave 2.
 * }
 * @return bool Retorna true em caso de sucesso, false caso contrÃ¡rio.
 */
public function exemplo_metodo( $param1, $param2, $args = [] ) {
    // ImplementaÃ§Ã£o
}
```

**Elementos obrigatÃ³rios:**
- DescriÃ§Ã£o breve do propÃ³sito do mÃ©todo
- `@param` para cada parÃ¢metro, com tipo e descriÃ§Ã£o
- `@return` com tipo e descriÃ§Ã£o do valor retornado
- `@since` indicando a versÃ£o de introduÃ§Ã£o (opcional, mas recomendado)

**Elementos opcionais mas Ãºteis:**
- DescriÃ§Ã£o detalhada para mÃ©todos complexos
- `@throws` para exceÃ§Ãµes que podem ser lanÃ§adas
- `@see` para referenciar mÃ©todos ou classes relacionadas
- `@link` para documentaÃ§Ã£o externa
- `@global` para variÃ¡veis globais utilizadas

**Prioridade de documentaÃ§Ã£o:**
1. MÃ©todos pÃºblicos (sempre documentar)
2. MÃ©todos protegidos/privados complexos
3. Hooks e filtros expostos
4. Constantes e propriedades de classe

### Boas prÃ¡ticas adicionais

**PrefixaÃ§Ã£o:**
- Todas as funÃ§Ãµes globais: `dps_`
- Todas as classes: `DPS_`
- Hooks e filtros: `dps_`
- Options: `dps_`
- Handles de scripts/estilos: `dps-`
- Custom Post Types: `dps_`

**SeguranÃ§a:**
- Sempre usar nonces em formulÃ¡rios: `wp_nonce_field()` / `wp_verify_nonce()`
- Escapar saÃ­da: `esc_html()`, `esc_attr()`, `esc_url()`, `esc_js()`
- Sanitizar entrada: `sanitize_text_field()`, `sanitize_email()`, `wp_kses_post()`
- Verificar capabilities: `current_user_can()`

**Performance:**
- Registrar assets apenas onde necessÃ¡rio
- Usar `wp_register_*` seguido de `wp_enqueue_*` condicionalmente
- Otimizar queries com `fields => 'ids'` quando apropriado
- PrÃ©-carregar metadados com `update_meta_cache()`

**IntegraÃ§Ã£o com o nÃºcleo:**
- Preferir hooks do plugin base (`dps_base_*`, `dps_settings_*`) a menus prÃ³prios
- Reutilizar classes helper quando disponÃ­veis (`DPS_CPT_Helper`, `DPS_Money_Helper`, etc.)
- Seguir contratos de hooks existentes sem modificar assinaturas
- Documentar novos hooks expostos com exemplos de uso

---

## Add-on: White Label (PersonalizaÃ§Ã£o de Marca)

**DiretÃ³rio**: `plugins/desi-pet-shower-whitelabel_addon/`

**VersÃ£o**: 1.0.0

**PropÃ³sito**: Personalize o sistema DPS com sua prÃ³pria marca, cores, logo, SMTP customizado e controles de acesso. Ideal para agÃªncias e revendedores que desejam oferecer o DPS sob sua prÃ³pria identidade visual.

### Funcionalidades Principais

1. **Branding e Identidade Visual**
   - Logo customizada (versÃµes clara e escura)
   - Favicon personalizado
   - Paleta de cores (primÃ¡ria, secundÃ¡ria, accent, background, texto)
   - Nome da marca e tagline
   - InformaÃ§Ãµes de contato (email, telefone, WhatsApp, URL de suporte)
   - URLs customizadas (website, documentaÃ§Ã£o, termos de uso, privacidade)
   - Footer customizado
   - CSS customizado para ajustes visuais finos
   - OpÃ§Ã£o de ocultar links "Powered by" e links do autor

2. **PÃ¡gina de Login Personalizada**
   - Logo customizada com dimensÃµes configurÃ¡veis
   - Background (cor sÃ³lida, imagem ou gradiente)
   - FormulÃ¡rio de login com largura, cor de fundo e bordas customizÃ¡veis
   - BotÃ£o de login com cores personalizadas
   - Mensagem customizada acima do formulÃ¡rio
   - Footer text customizado
   - CSS adicional para ajustes finos
   - OpÃ§Ã£o de ocultar links de registro e recuperaÃ§Ã£o de senha

3. **Modo de ManutenÃ§Ã£o**
   - Bloqueia acesso ao site para visitantes (HTTP 503)
   - Bypass configurÃ¡vel por roles WordPress (padrÃ£o: administrator)
   - PÃ¡gina de manutenÃ§Ã£o customizada com logo, tÃ­tulo e mensagem
   - Background e cores de texto configurÃ¡veis
   - Countdown timer opcional para previsÃ£o de retorno
   - Indicador visual na admin bar quando modo manutenÃ§Ã£o estÃ¡ ativo
   - Preserva acesso a wp-admin, wp-login e AJAX

4. **PersonalizaÃ§Ã£o da Admin Bar**
   - Ocultar itens especÃ­ficos da admin bar
   - Customizar logo e links
   - Remover menus do WordPress que nÃ£o sejam relevantes

5. **SMTP Customizado**
   - ConfiguraÃ§Ã£o de servidor SMTP prÃ³prio
   - AutenticaÃ§Ã£o segura
   - Teste de envio de e-mail
   - Suporte a TLS/SSL

6. **Assets e Estilos**
   - Carregamento condicional de assets apenas nas pÃ¡ginas relevantes
   - WordPress Color Picker integrado
   - WordPress Media Uploader para upload de logos
   - Interface responsiva e intuitiva

### Estrutura de Arquivos

```
desi-pet-shower-whitelabel_addon/
â”œâ”€â”€ desi-pet-shower-whitelabel-addon.php (orquestraÃ§Ã£o principal)
â”œâ”€â”€ includes/
â”‚   â”œâ”€â”€ class-dps-whitelabel-settings.php (branding e configuraÃ§Ãµes gerais)
â”‚   â”œâ”€â”€ class-dps-whitelabel-branding.php (aplicaÃ§Ã£o de branding no site)
â”‚   â”œâ”€â”€ class-dps-whitelabel-assets.php (gerenciamento de assets CSS/JS)
â”‚   â”œâ”€â”€ class-dps-whitelabel-smtp.php (SMTP customizado)
â”‚   â”œâ”€â”€ class-dps-whitelabel-login-page.php (pÃ¡gina de login personalizada)
â”‚   â”œâ”€â”€ class-dps-whitelabel-admin-bar.php (personalizaÃ§Ã£o da admin bar)
â”‚   â””â”€â”€ class-dps-whitelabel-maintenance.php (modo de manutenÃ§Ã£o)
â”œâ”€â”€ assets/
â”‚   â”œâ”€â”€ css/
â”‚   â”‚   â””â”€â”€ whitelabel-admin.css (estilos da interface admin)
â”‚   â””â”€â”€ js/
â”‚       â””â”€â”€ whitelabel-admin.js (JavaScript para color picker, media uploader)
â”œâ”€â”€ templates/
â”‚   â”œâ”€â”€ admin-settings.php (interface de configuraÃ§Ã£o com abas)
â”‚   â””â”€â”€ maintenance.php (template da pÃ¡gina de manutenÃ§Ã£o)
â”œâ”€â”€ languages/ (arquivos de traduÃ§Ã£o pt_BR)
â””â”€â”€ uninstall.php (limpeza ao desinstalar)
```

### Hooks Utilizados

**Do WordPress:**
- `init` (prioridade 1) - Carrega text domain
- `init` (prioridade 5) - Inicializa classes do add-on
- `admin_menu` (prioridade 20) - Registra menu admin
- `admin_enqueue_scripts` - Carrega assets admin
- `template_redirect` (prioridade 1) - Intercepta requisiÃ§Ãµes para modo manutenÃ§Ã£o
- `login_enqueue_scripts` - Aplica estilos customizados na pÃ¡gina de login
- `login_headerurl` - Customiza URL do logo de login
- `login_headertext` - Customiza texto alternativo do logo
- `login_footer` - Adiciona footer customizado no login
- `login_message` - Adiciona mensagem customizada no login
- `admin_bar_menu` (prioridade 100) - Adiciona indicadores visuais na admin bar

**Hooks Expostos (futuros):**
```php
// Permitir bypass customizado do modo manutenÃ§Ã£o
apply_filters( 'dps_whitelabel_maintenance_can_access', false, WP_User $user );

// Customizar template da pÃ¡gina de manutenÃ§Ã£o
apply_filters( 'dps_whitelabel_maintenance_template', string $template_path );

// Disparado apÃ³s salvar configuraÃ§Ãµes
do_action( 'dps_whitelabel_settings_saved', array $settings );
```

### Tabelas de Banco de Dados

Nenhuma tabela prÃ³pria. Todas as configuraÃ§Ãµes sÃ£o armazenadas como options do WordPress:

**Options criadas:**
- `dps_whitelabel_settings` - ConfiguraÃ§Ãµes de branding e identidade visual
- `dps_whitelabel_smtp` - ConfiguraÃ§Ãµes de servidor SMTP
- `dps_whitelabel_login` - ConfiguraÃ§Ãµes da pÃ¡gina de login
- `dps_whitelabel_admin_bar` - ConfiguraÃ§Ãµes da admin bar
- `dps_whitelabel_maintenance` - ConfiguraÃ§Ãµes do modo de manutenÃ§Ã£o

### Interface Administrativa

**Menu Principal:** desi.pet by PRObst â†’ White Label

**Abas de ConfiguraÃ§Ã£o:**
1. **Branding** - Logo, cores, nome da marca, contatos
2. **SMTP** - Servidor de e-mail customizado
3. **Login** - PersonalizaÃ§Ã£o da pÃ¡gina de login
4. **Admin Bar** - CustomizaÃ§Ã£o da barra administrativa
5. **ManutenÃ§Ã£o** - Modo de manutenÃ§Ã£o e mensagens

**Recursos de UX:**
- Interface com abas para organizaÃ§Ã£o clara
- Color pickers para seleÃ§Ã£o visual de cores
- Media uploader integrado para upload de logos e imagens
- Preview ao vivo de alteraÃ§Ãµes (em desenvolvimento)
- BotÃ£o de restaurar padrÃµes
- Mensagens de sucesso/erro apÃ³s salvamento
- ValidaÃ§Ã£o de campos (URLs, cores hexadecimais)

### SeguranÃ§a

**ValidaÃ§Ãµes Implementadas:**
- âœ… Nonce verification em todos os formulÃ¡rios
- âœ… Capability check (`manage_options`) em todas as aÃ§Ãµes
- âœ… SanitizaÃ§Ã£o rigorosa de inputs:
  - `sanitize_text_field()` para textos
  - `esc_url_raw()` para URLs
  - `sanitize_hex_color()` para cores
  - `sanitize_email()` para e-mails
  - `wp_kses_post()` para HTML permitido
- âœ… Escape de outputs:
  - `esc_html()`, `esc_attr()`, `esc_url()` conforme contexto
- âœ… CSS customizado sanitizado (remove JavaScript, expressions, @import)
- âœ… Administrator sempre incluÃ­do nas roles de bypass (nÃ£o pode ser removido)
- âœ… ValidaÃ§Ã£o de extensÃµes de imagem (logo, favicon)

### Compatibilidade

**WordPress:**
- VersÃ£o mÃ­nima: 6.9
- PHP: 8.4+

**DPS:**
- Requer: Plugin base (`DPS_Base_Plugin`)
- CompatÃ­vel com todos os add-ons existentes

**Plugins de Terceiros:**
- CompatÃ­vel com WP Mail SMTP (prioriza configuraÃ§Ã£o do White Label)
- CompatÃ­vel com temas page builders (YooTheme, Elementor)
- NÃ£o conflita com plugins de cache (assets condicionais)

### AnÃ¡lise Detalhada de Novas Funcionalidades

Para anÃ¡lise completa sobre a implementaÃ§Ã£o de **Controle de Acesso ao Site**, incluindo:
- Bloqueio de acesso para visitantes nÃ£o autenticados
- Lista de exceÃ§Ãµes de pÃ¡ginas pÃºblicas
- Redirecionamento para login customizado
- Controle por role WordPress
- Funcionalidades adicionais sugeridas (controle por CPT, horÃ¡rio, IP, logs)

Consulte a seÃ§Ã£o **White Label (`desi-pet-shower-whitelabel_addon`)** neste arquivo para o detalhamento funcional e recomendaÃ§Ãµes

### LimitaÃ§Ãµes Conhecidas

- Modo de manutenÃ§Ã£o bloqueia TODO o site (nÃ£o permite exceÃ§Ãµes por pÃ¡gina)
- NÃ£o hÃ¡ controle granular de acesso (apenas modo manutenÃ§Ã£o "tudo ou nada")
- CSS customizado nÃ£o tem preview ao vivo (requer salvamento para visualizar)
- Assets admin carregados mesmo fora da pÃ¡gina de configuraÃ§Ãµes (otimizaÃ§Ã£o pendente)
- Falta integraÃ§Ã£o com plugins de two-factor authentication

### PrÃ³ximos Passos Recomendados (Roadmap)

**v1.1.0 - Controle de Acesso ao Site** (ALTA PRIORIDADE)
- Implementar classe `DPS_WhiteLabel_Access_Control`
- Permitir bloqueio de acesso para visitantes nÃ£o autenticados
- Lista de exceÃ§Ãµes de URLs (com suporte a wildcards)
- Redirecionamento inteligente para login com preservaÃ§Ã£o de URL original
- Controle por role WordPress
- Indicador visual na admin bar quando ativo

**v1.2.0 - Melhorias de Interface** (MÃ‰DIA PRIORIDADE)
- Preview ao vivo de alteraÃ§Ãµes de cores
- Editor visual de CSS com syntax highlighting
- Upload de mÃºltiplos logos para diferentes contextos
- Galeria de presets de cores e layouts

**v1.3.0 - Recursos AvanÃ§ados** (BAIXA PRIORIDADE)
- Logs de acesso e auditoria
- Controle de acesso por CPT
- Redirecionamento baseado em role
- IntegraÃ§Ã£o com 2FA
- Rate limiting anti-bot

### Changelog

**v1.0.0** - 2025-12-06 - LanÃ§amento Inicial
- Branding completo (logo, cores, nome da marca)
- PÃ¡gina de login personalizada
- Modo de manutenÃ§Ã£o com bypass por roles
- PersonalizaÃ§Ã£o da admin bar
- SMTP customizado
- Interface administrativa com abas
- Suporte a i18n (pt_BR)
- DocumentaÃ§Ã£o completa

---

## Add-on: AI (Assistente Virtual)

**DiretÃ³rio**: `plugins/desi-pet-shower-ai/`

**VersÃ£o**: 1.6.0 (schema DB: 1.5.0)

**PropÃ³sito**: Assistente virtual inteligente para o Portal do Cliente, chat pÃºblico para visitantes, e geraÃ§Ã£o de sugestÃµes de comunicaÃ§Ãµes (WhatsApp e e-mail). Inclui analytics e base de conhecimento.

### Funcionalidades Principais

1. **Portal do Cliente**
   - Widget de chat para clientes fazerem perguntas sobre agendamentos, serviÃ§os, histÃ³rico
   - Respostas contextualizadas baseadas em dados reais do cliente e pets
   - Escopo restrito a assuntos relacionados a Banho e Tosa

2. **Chat PÃºblico** (v1.6.0+)
   - Shortcode `[dps_ai_public_chat]` para visitantes nÃ£o autenticados
   - Modos inline e floating, temas light/dark
   - FAQs customizÃ¡veis, rate limiting por IP
   - IntegraÃ§Ã£o com base de conhecimento

3. **Assistente de ComunicaÃ§Ãµes** (v1.2.0+)
   - Gera sugestÃµes de mensagens para WhatsApp
   - Gera sugestÃµes de e-mail (assunto e corpo)
   - **NUNCA envia automaticamente** - apenas sugere textos para revisÃ£o humana

4. **Analytics e Feedback** (v1.5.0+)
   - MÃ©tricas de uso (perguntas, tokens, erros, tempo de resposta)
   - Feedback positivo/negativo com comentÃ¡rios
   - Dashboard administrativo de analytics
   - Base de conhecimento (CPT `dps_ai_knowledge`)

5. **Agendamento via Chat** (v1.5.0+)
   - IntegraÃ§Ã£o com Agenda Add-on
   - SugestÃ£o de horÃ¡rios disponÃ­veis
   - Modos: request (solicita agendamento) e direct (agenda diretamente)

### Classes Principais

#### `DPS_AI_Client`

Cliente HTTP para API da OpenAI.

**MÃ©todos:**
- `chat( array $messages, array $options = [] )`: Faz chamada Ã  API Chat Completions
- `test_connection()`: Testa validaÃ§Ã£o da API key

**ConfiguraÃ§Ãµes:**
- API key armazenada em `dps_ai_settings['api_key']`
- Modelo, temperatura, max_tokens, timeout configurÃ¡veis

#### `DPS_AI_Assistant`

Assistente principal para Portal do Cliente.

**MÃ©todos:**
- `answer_portal_question( int $client_id, array $pet_ids, string $user_question )`: Responde pergunta do cliente
- `get_base_system_prompt()`: Retorna prompt base de seguranÃ§a (pÃºblico, reutilizÃ¡vel)

**System Prompt:**
- Escopo restrito a Banho e Tosa, serviÃ§os, agendamentos, histÃ³rico, funcionalidades DPS
- ProÃ­be assuntos fora do contexto (polÃ­tica, religiÃ£o, finanÃ§as pessoais, etc.)
- Protegido contra contradiÃ§Ãµes de instruÃ§Ãµes adicionais

#### `DPS_AI_Message_Assistant` (v1.2.0+)

Assistente para geraÃ§Ã£o de sugestÃµes de comunicaÃ§Ãµes.

**MÃ©todos:**

```php
/**
 * Gera sugestÃ£o de mensagem para WhatsApp.
 *
 * @param array $context {
 *     Contexto da mensagem.
 *
 *     @type string   $type              Tipo de mensagem (lembrete, confirmacao, pos_atendimento, etc.)
 *     @type string   $client_name       Nome do cliente
 *     @type string   $client_phone      Telefone do cliente
 *     @type string   $pet_name          Nome do pet
 *     @type string   $appointment_date  Data do agendamento (formato legÃ­vel)
 *     @type string   $appointment_time  Hora do agendamento
 *     @type array    $services          Lista de nomes de serviÃ§os
 *     @type string   $groomer_name      Nome do groomer (opcional)
 *     @type string   $amount            Valor formatado (opcional, para cobranÃ§as)
 *     @type string   $additional_info   InformaÃ§Ãµes adicionais (opcional)
 * }
 * @return array|null Array com ['text' => 'mensagem'] ou null em caso de erro.
 */
public static function suggest_whatsapp_message( array $context )

/**
 * Gera sugestÃ£o de e-mail (assunto e corpo).
 *
 * @param array $context Contexto da mensagem (mesmos campos do WhatsApp).
 * @return array|null Array com ['subject' => 'assunto', 'body' => 'corpo'] ou null.
 */
public static function suggest_email_message( array $context )
```

**Tipos de mensagens suportados:**
- `lembrete`: Lembrete de agendamento
- `confirmacao`: ConfirmaÃ§Ã£o de agendamento
- `pos_atendimento`: Agradecimento pÃ³s-atendimento
- `cobranca_suave`: Lembrete educado de pagamento
- `cancelamento`: NotificaÃ§Ã£o de cancelamento
- `reagendamento`: ConfirmaÃ§Ã£o de reagendamento

### Handlers AJAX

#### `wp_ajax_dps_ai_suggest_whatsapp_message`

Gera sugestÃ£o de mensagem WhatsApp via AJAX.

**Request:**
```javascript
{
    action: 'dps_ai_suggest_whatsapp_message',
    nonce: 'dps_ai_comm_nonce',
    context: {
        type: 'lembrete',
        client_name: 'JoÃ£o Silva',
        pet_name: 'Rex',
        appointment_date: '15/12/2024',
        appointment_time: '14:00',
        services: ['Banho', 'Tosa']
    }
}
```

**Response (sucesso):**
```javascript
{
    success: true,
    data: {
        text: 'OlÃ¡ JoÃ£o! Lembrete: amanhÃ£ Ã s 14:00 temos o agendamento...'
    }
}
```

**Response (erro):**
```javascript
{
    success: false,
    data: {
        message: 'NÃ£o foi possÃ­vel gerar sugestÃ£o automÃ¡tica. A IA pode estar desativada...'
    }
}
```

#### `wp_ajax_dps_ai_suggest_email_message`

Gera sugestÃ£o de e-mail via AJAX.

**Request:** (mesma estrutura do WhatsApp)

**Response (sucesso):**
```javascript
{
    success: true,
    data: {
        subject: 'Lembrete de Agendamento - desi.pet by PRObst',
        body: 'OlÃ¡ JoÃ£o,\n\nEste Ã© um lembrete...'
    }
}
```

### Interface JavaScript

**Arquivo:** `assets/js/dps-ai-communications.js`

**Classes CSS:**
- `.dps-ai-suggest-whatsapp`: BotÃ£o de sugestÃ£o para WhatsApp
- `.dps-ai-suggest-email`: BotÃ£o de sugestÃ£o para e-mail

**Atributos de dados (data-*):**

Para WhatsApp:
```html
<button
    class="button dps-ai-suggest-whatsapp"
    data-target="#campo-mensagem"
    data-type="lembrete"
    data-client-name="JoÃ£o Silva"
    data-pet-name="Rex"
    data-appointment-date="15/12/2024"
    data-appointment-time="14:00"
    data-services='["Banho", "Tosa"]'
>
    Sugerir com IA
</button>
```

Para e-mail:
```html
<button
    class="button dps-ai-suggest-email"
    data-target-subject="#campo-assunto"
    data-target-body="#campo-corpo"
    data-type="pos_atendimento"
    data-client-name="Maria Santos"
    data-pet-name="Mel"
>
    Sugerir E-mail com IA
</button>
```

**Modal de prÃ©-visualizaÃ§Ã£o:**
- E-mails abrem modal para revisÃ£o antes de inserir nos campos
- UsuÃ¡rio pode editar assunto e corpo no modal
- BotÃ£o "Inserir" preenche os campos do formulÃ¡rio (nÃ£o envia)

### ConfiguraÃ§Ãµes

Armazenadas em `dps_ai_settings`:

```php
[
    'enabled'                 => bool,   // Habilita/desabilita IA
    'api_key'                 => string, // Chave da OpenAI (sk-...)
    'model'                   => string, // gpt-3.5-turbo, gpt-4, etc.
    'temperature'             => float,  // 0-1, padrÃ£o 0.4
    'timeout'                 => int,    // Segundos, padrÃ£o 10
    'max_tokens'              => int,    // PadrÃ£o 500
    'additional_instructions' => string, // InstruÃ§Ãµes customizadas (max 2000 chars)
]
```

**OpÃ§Ãµes especÃ­ficas para comunicaÃ§Ãµes:**
- WhatsApp: `max_tokens => 300` (mensagens curtas)
- E-mail: `max_tokens => 500` (pode ter mais contexto)
- Temperatura: `0.5` (levemente mais criativo para tom amigÃ¡vel)

### SeguranÃ§a

- âœ… ValidaÃ§Ã£o de nonce em todos os handlers AJAX
- âœ… VerificaÃ§Ã£o de capability `edit_posts`
- âœ… SanitizaÃ§Ã£o de todos os inputs (`sanitize_text_field`, `wp_unslash`)
- âœ… System prompt base protegido contra sobrescrita
- âœ… **NUNCA envia mensagens automaticamente**
- âœ… API key server-side only (nunca exposta no JavaScript)

### Falhas e Tratamento de Erros

**IA desativada ou sem API key:**
- Retorna `null` em mÃ©todos PHP
- Retorna erro amigÃ¡vel em AJAX: "IA pode estar desativada..."
- **Campo de mensagem nÃ£o Ã© alterado** - usuÃ¡rio pode escrever manualmente

**Erro na API da OpenAI:**
- Timeout, erro de rede, resposta invÃ¡lida â†’ retorna `null`
- Logs em `error_log()` para debug
- NÃ£o quebra a interface - usuÃ¡rio pode continuar

**Parse de e-mail falha:**
- Tenta mÃºltiplos padrÃµes (ASSUNTO:/CORPO:, Subject:/Body:, divisÃ£o por linhas)
- Fallback: primeira linha como assunto, resto como corpo
- Se tudo falhar: retorna `null`

### IntegraÃ§Ã£o com Outros Add-ons

**Communications Add-on:**
- SugestÃµes de IA podem ser usadas com `DPS_Communications_API`
- IA gera texto â†’ usuÃ¡rio revisa â†’ `send_whatsapp()` ou `send_email()`

**Agenda Add-on:**
- Pode adicionar botÃµes de sugestÃ£o nas pÃ¡ginas de agendamento
- Ver exemplos em `includes/ai-communications-examples.php`

**Portal do Cliente:**
- Widget de chat jÃ¡ integrado via `DPS_AI_Integration_Portal`
- Usa mesmo system prompt base e configuraÃ§Ãµes

### DocumentaÃ§Ã£o Adicional

- **Manual completo**: `plugins/desi-pet-shower-ai/AI_COMMUNICATIONS.md`
- **Exemplos de cÃ³digo**: `plugins/desi-pet-shower-ai/includes/ai-communications-examples.php`
- **Comportamento da IA**: `plugins/desi-pet-shower-ai/BEHAVIOR_EXAMPLES.md`

### Hooks Expostos

Atualmente nenhum hook especÃ­fico de comunicaÃ§Ãµes. PossÃ­veis hooks futuros:

```php
// Filtro antes de gerar sugestÃ£o
$context = apply_filters( 'dps_ai_comm_whatsapp_context', $context, $type );

// Filtro apÃ³s gerar sugestÃ£o (permite pÃ³s-processamento)
$message = apply_filters( 'dps_ai_comm_whatsapp_message', $message, $context );
```

### Tabelas de Banco de Dados

**Desde v1.5.0**, o AI Add-on mantÃ©m 2 tabelas customizadas para analytics e feedback.
**Desde v1.7.0**, foram adicionadas 2 tabelas para histÃ³rico de conversas persistente.

#### `wp_dps_ai_conversations` (desde v1.7.0)

Armazena metadados de conversas com o assistente de IA em mÃºltiplos canais.

**Estrutura:**
```sql
CREATE TABLE wp_dps_ai_conversations (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id BIGINT(20) UNSIGNED DEFAULT NULL,
    channel VARCHAR(50) NOT NULL DEFAULT 'web_chat',
    session_identifier VARCHAR(255) DEFAULT NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_activity_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY customer_idx (customer_id),
    KEY channel_idx (channel),
    KEY session_idx (session_identifier),
    KEY status_idx (status),
    KEY last_activity_idx (last_activity_at)
);
```

**PropÃ³sito:**
- Rastrear conversas em mÃºltiplos canais: `web_chat` (pÃºblico), `portal`, `whatsapp`, `admin_specialist`
- Identificar usuÃ¡rios logados via `customer_id` ou visitantes via `session_identifier`
- Agrupar mensagens relacionadas em conversas contextuais
- Analisar padrÃµes de uso por canal
- Suportar histÃ³rico de conversas para futuras funcionalidades (ex: "rever conversas anteriores")

#### `wp_dps_ai_messages` (desde v1.7.0)

Armazena mensagens individuais de conversas.

**Estrutura:**
```sql
CREATE TABLE wp_dps_ai_messages (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    conversation_id BIGINT(20) UNSIGNED NOT NULL,
    sender_type VARCHAR(20) NOT NULL,
    sender_identifier VARCHAR(255) DEFAULT NULL,
    message_text TEXT NOT NULL,
    message_metadata TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY conversation_idx (conversation_id),
    KEY sender_type_idx (sender_type),
    KEY created_at_idx (created_at)
);
```

**Campos:**
- `sender_type`: 'user' (cliente/visitante), 'assistant' (IA), 'system' (mensagens do sistema)
- `sender_identifier`: ID do usuÃ¡rio, telefone, IP, etc (opcional)
- `message_metadata`: JSON com dados adicionais (tokens, custo, tempo de resposta, etc)

**PropÃ³sito:**
- HistÃ³rico completo de interaÃ§Ãµes em ordem cronolÃ³gica
- AnÃ¡lise de padrÃµes de perguntas e respostas
- Compliance (LGPD/GDPR - exportaÃ§Ã£o de dados pessoais)
- Debugging de problemas de IA
- Base para melhorias futuras (ex: sugestÃµes baseadas em histÃ³rico)

**Classe de Acesso:**
- `DPS_AI_Conversations_Repository` em `includes/class-dps-ai-conversations-repository.php`
- MÃ©todos: `create_conversation()`, `add_message()`, `get_conversation()`, `get_messages()`, `list_conversations()`

#### `wp_dps_ai_metrics`

Armazena mÃ©tricas agregadas de uso da IA por dia e cliente.

**Estrutura:**
```sql
CREATE TABLE wp_dps_ai_metrics (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    date DATE NOT NULL,
    client_id BIGINT(20) UNSIGNED DEFAULT 0,
    questions_count INT(11) UNSIGNED DEFAULT 0,
    tokens_input INT(11) UNSIGNED DEFAULT 0,
    tokens_output INT(11) UNSIGNED DEFAULT 0,
    errors_count INT(11) UNSIGNED DEFAULT 0,
    avg_response_time FLOAT DEFAULT 0,
    model VARCHAR(50) DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY date_client (date, client_id),
    KEY date_idx (date),
    KEY client_idx (client_id)
);
```

**PropÃ³sito:**
- Rastrear uso diÃ¡rio da IA (quantidade de perguntas, tokens consumidos)
- Monitorar performance (tempo mÃ©dio de resposta, taxa de erros)
- AnÃ¡lise de custos e utilizaÃ§Ã£o por cliente
- Dados para dashboard de analytics

#### `wp_dps_ai_feedback`

Armazena feedback individual (ðŸ‘/ðŸ‘Ž) de cada resposta da IA.

**Estrutura:**
```sql
CREATE TABLE wp_dps_ai_feedback (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id BIGINT(20) UNSIGNED DEFAULT 0,
    question TEXT,
    answer TEXT,
    feedback ENUM('positive', 'negative') NOT NULL,
    comment TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY client_idx (client_id),
    KEY feedback_idx (feedback),
    KEY created_at_idx (created_at)
);
```

**PropÃ³sito:**
- Coletar feedback de usuÃ¡rios sobre qualidade das respostas
- Identificar padrÃµes de respostas problemÃ¡ticas
- Melhorar prompts e treinamento da IA
- AnÃ¡lise de satisfaÃ§Ã£o

**Versionamento de Schema:**
- VersÃ£o do schema rastreada em opÃ§Ã£o `dps_ai_db_version`
- Upgrade automÃ¡tico via `dps_ai_maybe_upgrade_database()` em `plugins_loaded`
- v1.5.0: Tabelas `dps_ai_metrics` e `dps_ai_feedback` criadas via `dbDelta()`
- v1.6.0: Tabelas `dps_ai_conversations` e `dps_ai_messages` criadas via `dbDelta()`
- Idempotente: seguro executar mÃºltiplas vezes

**ConfiguraÃ§Ãµes em `wp_options`:**
- `dps_ai_settings` - ConfiguraÃ§Ãµes gerais (API key, modelo, temperatura, etc.)
- `dps_ai_db_version` - VersÃ£o do schema (desde v1.6.1)

### LimitaÃ§Ãµes Conhecidas

- Depende de conexÃ£o com internet e API key vÃ¡lida da OpenAI
- Custo por chamada Ã  API (variÃ¡vel por modelo e tokens)
- Qualidade das sugestÃµes depende da qualidade dos dados fornecidos no contexto
- NÃ£o substitui revisÃ£o humana - **sempre revisar antes de enviar**
- Assets carregados em todas as pÃ¡ginas admin (TODO: otimizar para carregar apenas onde necessÃ¡rio)

### Exemplos de Uso

Ver arquivo completo de exemplos: `plugins/desi-pet-shower-ai/includes/ai-communications-examples.php`

**Exemplo rÃ¡pido:**

```php
// Gerar sugestÃ£o de WhatsApp
$result = DPS_AI_Message_Assistant::suggest_whatsapp_message([
    'type'              => 'lembrete',
    'client_name'       => 'JoÃ£o Silva',
    'pet_name'          => 'Rex',
    'appointment_date'  => '15/12/2024',
    'appointment_time'  => '14:00',
    'services'          => ['Banho', 'Tosa'],
]);

if ( null !== $result ) {
    echo $result['text']; // Mensagem sugerida
}
```

### Changelog

**v1.0.0** - LanÃ§amento inicial
- Widget de chat no Portal do Cliente
- Respostas contextualizadas sobre agendamentos e serviÃ§os

**v1.1.0** - InstruÃ§Ãµes adicionais
- Campo de instruÃ§Ãµes customizadas nas configuraÃ§Ãµes
- MÃ©todo pÃºblico `get_base_system_prompt()`

**v1.2.0** - Assistente de ComunicaÃ§Ãµes
- Classe `DPS_AI_Message_Assistant`
- SugestÃµes de WhatsApp e e-mail
- Handlers AJAX e interface JavaScript
- Modal de prÃ©-visualizaÃ§Ã£o para e-mails
- 6 tipos de mensagens suportados
- DocumentaÃ§Ã£o e exemplos de integraÃ§Ã£o

---

## Mapeamento de Capabilities

> **Adicionado em:** 2026-02-18 â€” Fase 1 do Plano de ImplementaÃ§Ã£o

### Capabilities utilizadas no sistema

| Capability | Contexto de Uso | Plugins |
|-----------|-----------------|---------|
| `manage_options` | Admin pages, REST endpoints, AJAX handlers, configuraÃ§Ãµes | Todos os add-ons |
| `dps_manage_clients` | GestÃ£o de clientes (CRUD) | Base, Frontend |
| `dps_manage_pets` | GestÃ£o de pets (CRUD) | Base, Frontend |
| `dps_manage_appointments` | GestÃ£o de agendamentos (CRUD) | Base, Agenda, Frontend |

### Modelo de permissÃµes

- **Administradores** (`manage_options`): acesso total a todas as operaÃ§Ãµes do sistema, incluindo configuraÃ§Ãµes, relatÃ³rios financeiros e endpoints REST.
- **Gestores** (`dps_manage_*`): acesso Ã s operaÃ§Ãµes de gestÃ£o do dia a dia (clientes, pets, agendamentos).
- **Portal do cliente**: autenticaÃ§Ã£o via token/sessÃ£o sem WordPress capabilities. Acesso restrito via `DPS_Portal_Session_Manager::get_authenticated_client_id()`.

### Endpoints REST â€” Modelo de PermissÃ£o

| Plugin | Endpoint | Permission Callback |
|--------|----------|---------------------|
| Finance | `dps-finance/v1/transactions` | `current_user_can('manage_options')` |
| Loyalty | `dps-loyalty/v1/*` (5 rotas) | `current_user_can('manage_options')` |
| Communications | `dps-communications/v1/*` (3 rotas) | `current_user_can('manage_options')` |
| AI | `dps-ai/v1/whatsapp-webhook` | `__return_true` (webhook pÃºblico com validaÃ§Ã£o interna) |
| Agenda | `dps/v1/google-calendar-webhook` | `__return_true` (webhook pÃºblico com validaÃ§Ã£o interna) |
| Game | `dps-game/v1/*` (2 rotas) | sessao do portal + nonce custom ou `current_user_can('manage_options')` |

---

## Template PadrÃ£o de Add-on (Fase 2.2)

> DocumentaÃ§Ã£o do padrÃ£o de inicializaÃ§Ã£o e estrutura de add-ons. Todos os add-ons devem seguir este template para garantir consistÃªncia.

### Estrutura de DiretÃ³rios

```
desi-pet-shower-{nome}/
â”œâ”€â”€ desi-pet-shower-{nome}-addon.php   # Arquivo principal com header WP
â”œâ”€â”€ includes/                           # Classes PHP
â”‚   â”œâ”€â”€ class-dps-{nome}-*.php         # Classes de negÃ³cio
â”‚   â””â”€â”€ ...
â”œâ”€â”€ assets/                             # CSS/JS
â”‚   â”œâ”€â”€ css/
â”‚   â””â”€â”€ js/
â”œâ”€â”€ templates/                          # Templates HTML (quando aplicÃ¡vel)
â””â”€â”€ uninstall.php                       # Limpeza na desinstalaÃ§Ã£o (quando tem tabelas)
```

### Header WordPress ObrigatÃ³rio

```php
/**
 * Plugin Name: Desi Pet Shower - {Nome} Add-on
 * Plugin URI: https://github.com/richardprobst/DPS
 * Description: {DescriÃ§Ã£o curta}
 * Version: X.Y.Z
 * Author: PRObst
 * Author URI: https://probst.pro
 * Text Domain: desi-pet-shower
 * Domain Path: /languages
 * Requires at least: 6.9
 * Requires PHP: 8.4
 */
```

### PadrÃ£o de InicializaÃ§Ã£o

| Etapa | Hook | Prioridade | Responsabilidade |
|-------|------|-----------|------------------|
| Text domain | `init` | 1 | `load_plugin_textdomain()` |
| Classes/lÃ³gica | `init` | 5 | Instanciar classes, registrar CPTs, hooks |
| Admin menus | `admin_menu` | 20 | Submenu de `desi-pet-shower` |
| Admin assets | `admin_enqueue_scripts` | 10 | CSS/JS condicionais (`$hook_suffix`) |
| AtivaÃ§Ã£o | `register_activation_hook` | â€” | dbDelta, flush rewrite, capabilities |

### Assets â€” Carregamento Condicional (ObrigatÃ³rio)

```php
add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

public function enqueue_admin_assets( $hook ) {
    // Carrega apenas nas pÃ¡ginas do DPS
    if ( false === strpos( $hook, 'desi-pet-shower' ) ) {
        return;
    }
    wp_enqueue_style( 'dps-{nome}-addon', ... );
    wp_enqueue_script( 'dps-{nome}-addon', ... );
}
```

### Helpers Globais DisponÃ­veis (Base Plugin)

| Helper | MÃ©todos Principais |
|--------|-------------------|
| `DPS_Money_Helper` | `format_currency($cents)`, `format_currency_from_decimal($val)`, `format_decimal_to_brazilian($val)` |
| `DPS_Phone_Helper` | `clean($phone)`, `format_for_display($phone)`, `format_for_whatsapp($phone)` |
| `DPS_Query_Helper` | `get_all_posts_by_type($type)`, `get_posts_by_meta($type, $key, $val)` |
| `DPS_Message_Helper` | `add_success($msg)`, `add_error($msg)`, `add_warning($msg)` |
| `DPS_Request_Validator` | `verify_nonce($action)`, `verify_ajax_nonce($action)` |
| `DPS_URL_Builder` | `build_admin_url($page, $params)` |
| `DPS_Logger` | `info($msg, $ctx, $cat)`, `warning(...)`, `error(...)` |

### Compliance Status (Fev/2026)

| Add-on | Init@1 | Classes@5 | Menu@20 | Assets Cond. | Activation |
|--------|--------|-----------|---------|-------------|------------|
| agenda | âœ… | âœ… | âœ… | âœ… | âœ… |
| ai | âœ… | âœ… | âœ… | âœ… | âœ… |
| backup | âœ… | âœ… | âœ… | âœ… | âŒ |
| booking | âœ… | âœ… | â€” | â€” | âœ… |
| client-portal | âœ… | âœ… | âœ… | âœ… | âœ… |
| communications | âœ… | âœ… | âœ… | âœ… | âŒ |
| finance | âœ… | âœ… | âœ… | âœ… | âœ… |
| frontend | âœ… | âœ… | â€” | â€” | âŒ |
| groomers | âœ… | âœ… | âœ… | âœ… | âœ… |
| loyalty | âœ… | âœ… | âœ… | âœ… | âœ… |
| payment | âœ… | âœ… | âœ… | âœ… | âŒ |
| push | âœ… | âœ… | âœ… | âœ… | âœ… |
| registration | âœ… | âœ… | âœ… | âœ… | âœ… |
| services | âœ… | âœ… | â€” | â€” | âœ… |
| stats | âœ… | âœ… | â€” | â€” | âŒ |
| stock | âœ… | âœ… | â€” | âœ… | âœ… |
| subscription | âœ… | âœ… | â€” | â€” | âŒ |

**Legenda:** âœ… Conforme | âŒ Ausente | â€” NÃ£o aplicÃ¡vel (add-on sem UI admin prÃ³pria)

---

## Contratos de Metadados dos CPTs

> **Adicionado em:** 2026-02-18 â€” Fase 2.5 do Plano de ImplementaÃ§Ã£o

### dps_cliente â€” Metadados do Cliente

| Meta Key | Tipo/Formato | ObrigatÃ³rio | DescriÃ§Ã£o |
|----------|-------------|-------------|-----------|
| `client_cpf` | String (CPF: `000.000.000-00`) | NÃ£o | CPF do cliente |
| `client_phone` | String (telefone) | **Sim** | Telefone principal |
| `client_email` | String (email) | NÃ£o | E-mail do cliente |
| `client_birth` | String (data: `Y-m-d`) | NÃ£o | Data de nascimento |
| `client_instagram` | String | NÃ£o | Handle do Instagram |
| `client_facebook` | String | NÃ£o | Perfil do Facebook |
| `client_photo_auth` | Int (`0` ou `1`) | NÃ£o | AutorizaÃ§Ã£o para fotos |
| `client_address` | String (textarea) | NÃ£o | EndereÃ§o completo |
| `client_referral` | String | NÃ£o | CÃ³digo de indicaÃ§Ã£o |
| `client_lat` | String (float: `-23.5505`) | NÃ£o | Latitude (geolocalizaÃ§Ã£o) |
| `client_lng` | String (float: `-46.6333`) | NÃ£o | Longitude (geolocalizaÃ§Ã£o) |

**Classe handler:** `DPS_Client_Handler` (`includes/class-dps-client-handler.php`)
**Campos obrigatÃ³rios na validaÃ§Ã£o:** `client_name` (post_title), `client_phone`

### dps_pet â€” Metadados do Pet

| Meta Key | Tipo/Formato | ObrigatÃ³rio | DescriÃ§Ã£o |
|----------|-------------|-------------|-----------|
| `owner_id` | Int (ID do `dps_cliente`) | **Sim** | ID do tutor/proprietÃ¡rio |
| `pet_species` | String (enum: `cachorro`, `gato`, `outro`) | **Sim** | EspÃ©cie |
| `pet_breed` | String | NÃ£o | RaÃ§a |
| `pet_size` | String (enum: `pequeno`, `medio`, `grande`, `gigante`) | **Sim** | Porte |
| `pet_weight` | String (float em kg) | NÃ£o | Peso |
| `pet_coat` | String | NÃ£o | Tipo de pelagem |
| `pet_color` | String | NÃ£o | Cor/marcaÃ§Ãµes |
| `pet_birth` | String (data: `Y-m-d`) | NÃ£o | Data de nascimento |
| `pet_sex` | String (enum: `macho`, `femea`) | **Sim** | Sexo |
| `pet_care` | String (textarea) | NÃ£o | Cuidados especiais |
| `pet_aggressive` | Int (`0` ou `1`) | NÃ£o | Flag de agressividade |
| `pet_vaccinations` | String (textarea) | NÃ£o | Registro de vacinaÃ§Ã£o |
| `pet_allergies` | String (textarea) | NÃ£o | Alergias conhecidas |
| `pet_behavior` | String (textarea) | NÃ£o | Notas comportamentais |
| `pet_shampoo_pref` | String | NÃ£o | PreferÃªncia de shampoo |
| `pet_perfume_pref` | String | NÃ£o | PreferÃªncia de perfume |
| `pet_accessories_pref` | String | NÃ£o | PreferÃªncia de acessÃ³rios |
| `pet_product_restrictions` | String (textarea) | NÃ£o | RestriÃ§Ãµes de produtos |
| `pet_photo_id` | Int (attachment ID) | NÃ£o | ID da foto do pet |

**Classe handler:** `DPS_Pet_Handler` (`includes/class-dps-pet-handler.php`)
**Campos obrigatÃ³rios na validaÃ§Ã£o:** `pet_name` (post_title), `owner_id`, `pet_species`, `pet_size`, `pet_sex`

### dps_agendamento â€” Metadados do Agendamento

| Meta Key | Tipo/Formato | ObrigatÃ³rio | DescriÃ§Ã£o |
|----------|-------------|-------------|-----------|
| `appointment_client_id` | Int (ID do `dps_cliente`) | **Sim** | ID do cliente |
| `appointment_pet_id` | Int (ID do `dps_pet`) | **Sim** | Pet principal (legado) |
| `appointment_pet_ids` | Array serializado de IDs | NÃ£o | Multi-pet: lista de pet IDs |
| `appointment_date` | String (data: `Y-m-d`) | **Sim** | Data do atendimento |
| `appointment_time` | String (hora: `H:i`) | **Sim** | HorÃ¡rio do atendimento |
| `appointment_status` | String (enum) | **Sim** | Status do agendamento |
| `appointment_type` | String (enum: `simple`, `subscription`, `past`) | NÃ£o | Tipo de agendamento |
| `appointment_services` | Array serializado de IDs | NÃ£o | IDs dos serviÃ§os |
| `appointment_service_prices` | Array serializado de floats | NÃ£o | PreÃ§os dos serviÃ§os |
| `appointment_total_value` | Float | NÃ£o | Valor total |
| `appointment_notes` | String (textarea) | NÃ£o | ObservaÃ§Ãµes |
| `appointment_taxidog` | Int (`0` ou `1`) | NÃ£o | Flag de TaxiDog |
| `appointment_taxidog_price` | Float | NÃ£o | PreÃ§o do TaxiDog |

**Status possÃ­veis:** `pendente`, `confirmado`, `em_atendimento`, `finalizado`, `finalizado e pago`, `finalizado_pago`, `cancelado`

### RelaÃ§Ãµes entre CPTs

```
dps_cliente (1) â”€â”€â”€â”€ (N) dps_pet          via pet.owner_id â†’ cliente.ID
dps_cliente (1) â”€â”€â”€â”€ (N) dps_agendamento  via agendamento.appointment_client_id â†’ cliente.ID
dps_pet     (1) â”€â”€â”€â”€ (N) dps_agendamento  via agendamento.appointment_pet_id â†’ pet.ID
dps_pet     (N) â”€â”€â”€â”€ (N) dps_agendamento  via agendamento.appointment_pet_ids (serializado)
```

---

## IntegraÃ§Ãµes Futuras Propostas

### IntegraÃ§Ã£o com Google Tarefas (Google Tasks API)

**Status:** Proposta de anÃ¡lise (2026-01-19)
**DocumentaÃ§Ã£o:** proposta consolidada nesta seÃ§Ã£o do `ANALYSIS.md` (ainda sem documento dedicado em `docs/analysis/`)

**Resumo:**
A integraÃ§Ã£o do sistema DPS com Google Tasks API permite sincronizar atividades do sistema (agendamentos, cobranÃ§as, mensagens) com listas de tarefas do Google, melhorando a organizaÃ§Ã£o e follow-up de atividades administrativas.

**Status:** âœ… VIÃVEL e RECOMENDADO

**Funcionalidades propostas:**
1. **Agendamentos** (Alta Prioridade)
   - Lembretes de agendamentos pendentes (1 dia antes)
   - Follow-ups pÃ³s-atendimento (2 dias depois)

2. **Financeiro** (Alta Prioridade)
   - CobranÃ§as pendentes (1 dia antes do vencimento)
   - RenovaÃ§Ãµes de assinatura (5 dias antes)

3. **Portal do Cliente** (MÃ©dia Prioridade)
   - Mensagens recebidas de clientes (tarefa imediata)

4. **Estoque** (Baixa Prioridade)
   - Alertas de estoque baixo (tarefa de reposiÃ§Ã£o)

**Add-on proposto:** `desi-pet-shower-google-tasks`

**Tipo de sincronizaÃ§Ã£o:** Unidirecional (DPS â†’ Google Tasks)
- DPS cria tarefas no Google Tasks
- Google Tasks nÃ£o modifica dados do DPS
- DPS permanece como "fonte da verdade"

**EsforÃ§o estimado:**
- v1.0.0 MVP (OAuth + Agendamentos): 42h (~5.5 dias)
- v1.1.0 (+ Financeiro): 10h (~1.5 dias)
- v1.2.0 (+ Portal + Estoque): 14h (~2 dias)
- v1.3.0 (Testes + DocumentaÃ§Ã£o): 21h (~2.5 dias)
- **Total:** 87h (~11 dias Ãºteis)

**BenefÃ­cios:**
- CentralizaÃ§Ã£o de tarefas em app que equipe jÃ¡ usa
- NotificaÃ§Ãµes nativas do Google (mobile, desktop, email)
- IntegraÃ§Ã£o com ecossistema Google (Calendar, Gmail, Android, iOS)
- API gratuita (50.000 requisiÃ§Ãµes/dia)
- ReduÃ§Ã£o de agendamentos esquecidos (-30% esperado)

**SeguranÃ§a:**
- AutenticaÃ§Ã£o OAuth 2.0
- Tokens criptografados com AES-256
- Dados sensÃ­veis filtrÃ¡veis (admin escolhe o que incluir)
- LGPD compliance (nÃ£o envia CPF, RG, telefone completo)

**PrÃ³ximos passos (se aprovado):**
1. Criar projeto no Google Cloud Console
2. Obter credenciais OAuth 2.0
3. Implementar v1.0.0 MVP
4. Testar com 3-5 pet shops piloto (beta 1 mÃªs)
5. Iterar baseado em feedback
6. LanÃ§amento geral para clientes DPS

**Consulte os documentos completos para:**
- Arquitetura detalhada (classes, hooks, estrutura de dados)
- Casos de uso detalhados (3 cenÃ¡rios reais)
- Requisitos tÃ©cnicos (APIs, OAuth, configuraÃ§Ã£o Google Cloud)
- AnÃ¡lise de riscos e mitigaÃ§Ãµes
- MÃ©tricas de sucesso (KPIs tÃ©cnicos e de negÃ³cio)
- ComparaÃ§Ã£o com alternativas (Microsoft To Do, Todoist, sistema interno)
