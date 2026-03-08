# AnÃƒÂ¡lise funcional do desi.pet by PRObst

## Plugin base (`plugins/desi-pet-shower-base`)
- O arquivo principal declara constantes globais, registra os *custom post types* de clientes, pets e agendamentos, carrega os ativos do frontend e expÃƒÂµe os *shortcodes* `[dps_base]` e `[dps_configuracoes]`, que servem como ponto de entrada para o painel e para a tela de configuraÃƒÂ§ÃƒÂµes consumida pelos add-ons.
- `includes/class-dps-cpt-helper.php` centraliza o registro de CPTs com rÃƒÂ³tulos e argumentos padrÃƒÂ£o; novos tipos devem instanciar `DPS_CPT_Helper` para herdar as opÃƒÂ§ÃƒÂµes comuns e apenas sobrescrever particularidades (ex.: capabilities customizadas ou suporte a editor).
- **NOTA**: Os CPTs principais (`dps_cliente`, `dps_pet`, `dps_agendamento`) estÃƒÂ£o registrados com `show_ui => true` e `show_in_menu => false`, sendo exibidos pelo painel central e reutilizÃƒÂ¡veis pelos add-ons via abas. Para anÃƒÂ¡lise completa sobre a interface nativa do WordPress para estes CPTs, consulte `docs/analysis/BASE_PLUGIN_DEEP_ANALYSIS.md` e `docs/analysis/ADMIN_MENUS_MAPPING.md`.
- A classe `DPS_Base_Frontend` concentra a lÃƒÂ³gica de interface: normaliza telefones para WhatsApp, agrega dados de agendamentos multi-pet para cobranÃƒÂ§as conjuntas, monta botÃƒÂµes de cobranÃƒÂ§a, controla salvamento/exclusÃƒÂ£o de clientes, pets e atendimentos e renderiza as abas consumidas pelos add-ons via *hooks* (`dps_base_nav_tabs_after_pets`, `dps_base_nav_tabs_after_history`, etc.).
- A classe `DPS_Settings_Frontend` gerencia a pÃƒÂ¡gina de configuraÃƒÂ§ÃƒÂµes (`[dps_configuracoes]`) com sistema moderno de registro de abas via `register_tab()`. Os hooks legados `dps_settings_nav_tabs` e `dps_settings_sections` foram depreciados em favor do sistema moderno que oferece melhor consistÃƒÂªncia visual. A pÃƒÂ¡gina inclui assets dedicados (`dps-settings.css` e `dps-settings.js`) carregados automaticamente, com suporte a navegaÃƒÂ§ÃƒÂ£o client-side entre abas, busca em tempo real de configuraÃƒÂ§ÃƒÂµes com destaque visual, barra de status contextual e detecÃƒÂ§ÃƒÂ£o de alteraÃƒÂ§ÃƒÂµes nÃƒÂ£o salvas com aviso ao sair.
- O fluxo de formulÃƒÂ¡rios usa `dps_nonce` para CSRF e delega aÃƒÂ§ÃƒÂµes especÃƒÂ­ficas (`save_client`, `save_pet`, `save_appointment`, `update_appointment_status`) para mÃƒÂ©todos especializados, enquanto exclusÃƒÂµes limpam tambÃƒÂ©m dados financeiros relacionados quando disponÃƒÂ­veis. A classe principal ÃƒÂ© inicializada no hook `init` com prioridade 5, apÃƒÂ³s o carregamento do text domain em prioridade 1.
- A exclusÃƒÂ£o de agendamentos dispara o hook `dps_finance_cleanup_for_appointment`, permitindo que add-ons financeiros tratem a remoÃƒÂ§ÃƒÂ£o de lanÃƒÂ§amentos vinculados sem depender de SQL no nÃƒÂºcleo.
- O filtro `dps_tosa_consent_required` permite ajustar quando o consentimento de tosa com mÃƒÂ¡quina ÃƒÂ© exigido ao salvar agendamentos (parÃƒÂ¢metros: `$requires`, `$data`, `$service_ids`).
- A criaÃƒÂ§ÃƒÂ£o de tabelas do nÃƒÂºcleo (ex.: `dps_logs`) ÃƒÂ© registrada no `register_activation_hook` e versionada via option `dps_logger_db_version`. Caso a flag de versÃƒÂ£o nÃƒÂ£o exista ou esteja desatualizada, `dbDelta` ÃƒÂ© chamado uma ÃƒÂºnica vez em `plugins_loaded` para alinhar o esquema, evitando consultas de verificaÃƒÂ§ÃƒÂ£o em todos os ciclos de `init`.
- **OrganizaÃƒÂ§ÃƒÂ£o do menu admin**: o menu pai `desi-pet-shower` apresenta apenas hubs e itens principais. Um limpador dedicado (`DPS_Admin_Menu_Cleaner`) remove submenus duplicados que jÃƒÂ¡ estÃƒÂ£o cobertos por hubs (IntegraÃƒÂ§ÃƒÂµes, Sistema, Ferramentas, Agenda, IA, Portal). As pÃƒÂ¡ginas continuam acessÃƒÂ­veis via URL direta e pelas abas dos hubs, evitando poluiÃƒÂ§ÃƒÂ£o visual na navegaÃƒÂ§ÃƒÂ£o.

### Helpers globais do nÃƒÂºcleo

O plugin base oferece classes utilitÃƒÂ¡rias para padronizar operaÃƒÂ§ÃƒÂµes comuns e evitar duplicaÃƒÂ§ÃƒÂ£o de lÃƒÂ³gica. Estes helpers estÃƒÂ£o disponÃƒÂ­veis em `plugins/desi-pet-shower-base/includes/` e podem ser usados tanto pelo nÃƒÂºcleo quanto pelos add-ons.

#### DPS_Money_Helper
**PropÃƒÂ³sito**: ManipulaÃƒÂ§ÃƒÂ£o consistente de valores monetÃƒÂ¡rios com conversÃƒÂ£o entre formato brasileiro e centavos.

**Entrada/SaÃƒÂ­da**:
- `parse_brazilian_format( string )`: Converte string BR (ex.: "1.234,56") Ã¢â€ â€™ int centavos (123456)
- `format_to_brazilian( int )`: Converte centavos (123456) Ã¢â€ â€™ string BR ("1.234,56")
- `format_currency( int, string $symbol = 'R$ ' )`: Converte centavos Ã¢â€ â€™ string com sÃƒÂ­mbolo ("R$ 1.234,56")
- `format_currency_from_decimal( float, string $symbol = 'R$ ' )`: Converte decimal Ã¢â€ â€™ string com sÃƒÂ­mbolo ("R$ 1.234,56")
- `decimal_to_cents( float )`: Converte decimal (12.34) Ã¢â€ â€™ int centavos (1234)
- `cents_to_decimal( int )`: Converte centavos (1234) Ã¢â€ â€™ float decimal (12.34)
- `is_valid_money_string( string )`: Valida se string representa valor monetÃƒÂ¡rio Ã¢â€ â€™ bool

**Exemplos prÃƒÂ¡ticos**:
```php
// Validar e converter valor do formulÃƒÂ¡rio para centavos
$preco_raw = isset( $_POST['preco'] ) ? sanitize_text_field( $_POST['preco'] ) : '';
$valor_centavos = DPS_Money_Helper::parse_brazilian_format( $preco_raw );

// Exibir valor formatado na tela (com sÃƒÂ­mbolo de moeda)
echo DPS_Money_Helper::format_currency( $valor_centavos );
// Resultado: "R$ 1.234,56"

// Para valores decimais (em reais, nÃƒÂ£o centavos)
echo DPS_Money_Helper::format_currency_from_decimal( 1234.56 );
// Resultado: "R$ 1.234,56"
```

**Boas prÃƒÂ¡ticas**: 
- Use `format_currency()` para exibiÃƒÂ§ÃƒÂ£o em interfaces (jÃƒÂ¡ inclui "R$ ")
- Use `format_to_brazilian()` quando precisar apenas do valor sem sÃƒÂ­mbolo
- Evite lÃƒÂ³gica duplicada de `number_format` espalhada pelo cÃƒÂ³digo

#### DPS_URL_Builder
**PropÃƒÂ³sito**: ConstruÃƒÂ§ÃƒÂ£o padronizada de URLs de aÃƒÂ§ÃƒÂ£o (ediÃƒÂ§ÃƒÂ£o, exclusÃƒÂ£o, visualizaÃƒÂ§ÃƒÂ£o, navegaÃƒÂ§ÃƒÂ£o entre abas).

**Entrada/SaÃƒÂ­da**:
- `build_edit_url( int $post_id, string $tab )`: Gera URL de ediÃƒÂ§ÃƒÂ£o com nonce
- `build_delete_url( int $post_id, string $action, string $tab )`: Gera URL de exclusÃƒÂ£o com nonce
- `build_view_url( int $post_id, string $tab )`: Gera URL de visualizaÃƒÂ§ÃƒÂ£o
- `build_tab_url( string $tab_name )`: Gera URL de navegaÃƒÂ§ÃƒÂ£o entre abas

**Exemplos prÃƒÂ¡ticos**:
```php
// Gerar link de ediÃƒÂ§ÃƒÂ£o de cliente
$url_editar = DPS_URL_Builder::build_edit_url( $client_id, 'clientes' );

// Gerar link de exclusÃƒÂ£o de agendamento com confirmaÃƒÂ§ÃƒÂ£o
$url_excluir = DPS_URL_Builder::build_delete_url( $appointment_id, 'delete_appointment', 'historico' );
```

**Boas prÃƒÂ¡ticas**: Centralize geraÃƒÂ§ÃƒÂ£o de URLs neste helper para garantir nonces consistentes e evitar links quebrados.

#### DPS_Query_Helper
**PropÃƒÂ³sito**: Consultas WP_Query reutilizÃƒÂ¡veis com filtros comuns, paginaÃƒÂ§ÃƒÂ£o e otimizaÃƒÂ§ÃƒÂµes de performance.

**Entrada/SaÃƒÂ­da**:
- `get_all_posts_by_type( string $post_type, array $args )`: Retorna posts com argumentos otimizados
- `get_paginated_posts( string $post_type, int $per_page, int $paged, array $args )`: Retorna posts paginados
- `count_posts_by_status( string $post_type, string $status )`: Conta posts por status

**Exemplos prÃƒÂ¡ticos**:
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

**Boas prÃƒÂ¡ticas**: Use `fields => 'ids'` quando precisar apenas de IDs, e prÃƒÂ©-carregue metadados com `update_meta_cache()` quando precisar de metas.

#### DPS_Request_Validator
**PropÃƒÂ³sito**: ValidaÃƒÂ§ÃƒÂ£o centralizada de nonces, capabilities, requisiÃƒÂ§ÃƒÂµes AJAX e sanitizaÃƒÂ§ÃƒÂ£o de campos de formulÃƒÂ¡rio.

**MÃƒÂ©todos principais:**
- `verify_request_nonce( $nonce_field, $nonce_action, $method, $die_on_failure )`: Verifica nonce POST/GET
- `verify_nonce_and_capability( $nonce_field, $nonce_action, $capability )`: Valida nonce e permissÃƒÂ£o
- `verify_capability( $capability, $die_on_failure )`: Verifica apenas capability

**MÃƒÂ©todos AJAX (Fase 3):**
- `verify_ajax_nonce( $nonce_action, $nonce_field = 'nonce' )`: Verifica nonce AJAX com resposta JSON automÃƒÂ¡tica
- `verify_ajax_admin( $nonce_action, $capability = 'manage_options' )`: Verifica nonce + capability para AJAX admin
- `verify_admin_action( $nonce_action, $capability, $nonce_field = '_wpnonce' )`: Verifica nonce de aÃƒÂ§ÃƒÂ£o GET
- `verify_admin_form( $nonce_action, $nonce_field, $capability )`: Verifica nonce de formulÃƒÂ¡rio POST
- `verify_dynamic_nonce( $nonce_prefix, $item_id )`: Verifica nonce com ID dinÃƒÂ¢mico

**MÃƒÂ©todos de resposta:**
- `send_json_error( $message, $code, $status )`: Resposta JSON de erro padronizada
- `send_json_success( $message, $data )`: Resposta JSON de sucesso padronizada

**MÃƒÂ©todos auxiliares:**
- `get_post_int( $field_name, $default )`: ObtÃƒÂ©m inteiro do POST sanitizado
- `get_post_string( $field_name, $default )`: ObtÃƒÂ©m string do POST sanitizada
- `get_get_int( $field_name, $default )`: ObtÃƒÂ©m inteiro do GET sanitizado
- `get_get_string( $field_name, $default )`: ObtÃƒÂ©m string do GET sanitizada

**Exemplos prÃƒÂ¡ticos:**
```php
// Handler AJAX admin simples
public function ajax_delete_item() {
    if ( ! DPS_Request_Validator::verify_ajax_admin( 'dps_delete_item' ) ) {
        return; // Resposta JSON de erro jÃƒÂ¡ enviada
    }
    // ... processar aÃƒÂ§ÃƒÂ£o
}

// Verificar nonce com ID dinÃƒÂ¢mico
$client_id = absint( $_GET['client_id'] );
if ( ! DPS_Request_Validator::verify_dynamic_nonce( 'dps_delete_client_', $client_id, 'nonce', 'GET' ) ) {
    return;
}

// Validar formulÃƒÂ¡rio admin
if ( ! DPS_Request_Validator::verify_admin_form( 'dps_save_settings', 'dps_settings_nonce' ) ) {
    return;
}
```

**Boas prÃƒÂ¡ticas**: Use `verify_ajax_admin()` para handlers AJAX admin e `verify_ajax_nonce()` para AJAX pÃƒÂºblico. Evite duplicar lÃƒÂ³gica de seguranÃƒÂ§a.

#### DPS_Phone_Helper
**PropÃƒÂ³sito**: FormataÃƒÂ§ÃƒÂ£o e validaÃƒÂ§ÃƒÂ£o padronizada de nÃƒÂºmeros de telefone para comunicaÃƒÂ§ÃƒÂµes (WhatsApp, exibiÃƒÂ§ÃƒÂ£o).

**Entrada/SaÃƒÂ­da**:
- `format_for_whatsapp( string $phone )`: Formata telefone para WhatsApp (adiciona cÃƒÂ³digo do paÃƒÂ­s 55 se necessÃƒÂ¡rio) Ã¢â€ â€™ string apenas dÃƒÂ­gitos
- `format_for_display( string $phone )`: Formata telefone para exibiÃƒÂ§ÃƒÂ£o brasileira Ã¢â€ â€™ string formatada "(11) 98765-4321"
- `is_valid_brazilian_phone( string $phone )`: Valida se telefone brasileiro ÃƒÂ© vÃƒÂ¡lido Ã¢â€ â€™ bool

**Exemplos prÃƒÂ¡ticos**:
```php
// Formatar para envio via WhatsApp
$phone_raw = '(11) 98765-4321';
$whatsapp_number = DPS_Phone_Helper::format_for_whatsapp( $phone_raw );
// Retorna: '5511987654321'

// Formatar para exibiÃƒÂ§ÃƒÂ£o na tela
$phone_stored = '5511987654321';
$phone_display = DPS_Phone_Helper::format_for_display( $phone_stored );
// Retorna: '(11) 98765-4321'

// Validar telefone antes de salvar
if ( ! DPS_Phone_Helper::is_valid_brazilian_phone( $phone_input ) ) {
    DPS_Message_Helper::add_error( 'Telefone invÃƒÂ¡lido' );
}
```

**Boas prÃƒÂ¡ticas**: 
- Use sempre este helper para formataÃƒÂ§ÃƒÂ£o de telefones
- Evite duplicaÃƒÂ§ÃƒÂ£o de lÃƒÂ³gica `preg_replace` espalhada entre add-ons
- Integrado com `DPS_Communications_API` para envio automÃƒÂ¡tico via WhatsApp
- **IMPORTANTE**: Todas as funÃƒÂ§ÃƒÂµes duplicadas `format_whatsapp_number()` foram removidas do plugin base e add-ons. Use SEMPRE `DPS_Phone_Helper::format_for_whatsapp()` diretamente

#### DPS_WhatsApp_Helper
**PropÃƒÂ³sito**: GeraÃƒÂ§ÃƒÂ£o centralizada de links do WhatsApp com mensagens personalizadas. Introduzida para padronizar criaÃƒÂ§ÃƒÂ£o de URLs do WhatsApp em todo o sistema.

**Constante**:
- `TEAM_PHONE = '5515991606299'`: NÃƒÂºmero padrÃƒÂ£o da equipe (+55 15 99160-6299)

**Entrada/SaÃƒÂ­da**:
- `get_link_to_team( string $message = '' )`: Gera link para cliente Ã¢â€ â€™ equipe Ã¢â€ â€™ string URL
- `get_link_to_client( string $client_phone, string $message = '' )`: Gera link para equipe Ã¢â€ â€™ cliente Ã¢â€ â€™ string URL ou vazio se invÃƒÂ¡lido
- `get_share_link( string $message )`: Gera link de compartilhamento genÃƒÂ©rico Ã¢â€ â€™ string URL
- `get_team_phone()`: ObtÃƒÂ©m nÃƒÂºmero da equipe configurado Ã¢â€ â€™ string (formatado)

**MÃƒÂ©todos auxiliares para mensagens padrÃƒÂ£o**:
- `get_portal_access_request_message( $client_name = '', $pet_name = '' )`: Mensagem padrÃƒÂ£o para solicitar acesso
- `get_portal_link_message( $client_name, $portal_url )`: Mensagem padrÃƒÂ£o para enviar link do portal
- `get_appointment_confirmation_message( $appointment_data )`: Mensagem padrÃƒÂ£o de confirmaÃƒÂ§ÃƒÂ£o de agendamento
- `get_payment_request_message( $client_name, $amount, $payment_url = '' )`: Mensagem padrÃƒÂ£o de cobranÃƒÂ§a

**Exemplos prÃƒÂ¡ticos**:
```php
// Cliente quer contatar a equipe (ex: solicitar acesso ao portal)
$message = DPS_WhatsApp_Helper::get_portal_access_request_message();
$url = DPS_WhatsApp_Helper::get_link_to_team( $message );
echo '<a href="' . esc_url( $url ) . '" target="_blank">Quero acesso</a>';

// Equipe quer contatar cliente (ex: enviar link do portal)
$client_phone = get_post_meta( $client_id, 'client_phone', true );
$portal_url = 'https://exemplo.com/portal?token=abc123';
$message = DPS_WhatsApp_Helper::get_portal_link_message( 'JoÃƒÂ£o Silva', $portal_url );
$url = DPS_WhatsApp_Helper::get_link_to_client( $client_phone, $message );
echo '<a href="' . esc_url( $url ) . '" target="_blank">Enviar via WhatsApp</a>';

// Compartilhamento genÃƒÂ©rico (ex: foto do pet)
$share_text = 'Olha a foto do meu pet: https://exemplo.com/foto.jpg';
$url = DPS_WhatsApp_Helper::get_share_link( $share_text );
echo '<a href="' . esc_url( $url ) . '" target="_blank">Compartilhar</a>';
```

**ConfiguraÃƒÂ§ÃƒÂ£o**:
- NÃƒÂºmero da equipe configurÃƒÂ¡vel em: Admin Ã¢â€ â€™ desi.pet by PRObst Ã¢â€ â€™ ComunicaÃƒÂ§ÃƒÂµes
- Option: `dps_whatsapp_number` (padrÃƒÂ£o: +55 15 99160-6299)
- Fallback automÃƒÂ¡tico para constante `TEAM_PHONE` se option nÃƒÂ£o existir
- Filtro disponÃƒÂ­vel: `dps_team_whatsapp_number` para customizaÃƒÂ§ÃƒÂ£o programÃƒÂ¡tica

**Boas prÃƒÂ¡ticas**:
- Use sempre este helper para criar links WhatsApp (nÃƒÂ£o construa URLs manualmente)
- Helper formata automaticamente nÃƒÂºmeros de clientes usando `DPS_Phone_Helper`
- Sempre escape URLs com `esc_url()` ao exibir em HTML
- Mensagens sÃƒÂ£o codificadas automaticamente com `rawurlencode()`
- Retorna string vazia se nÃƒÂºmero do cliente for invÃƒÂ¡lido (verificar antes de exibir link)

**Locais que usam este helper**:
- Lista de clientes (plugin base)
- Add-on de Agenda (confirmaÃƒÂ§ÃƒÂ£o e cobranÃƒÂ§a)
- Add-on de Assinaturas (cobranÃƒÂ§a de renovaÃƒÂ§ÃƒÂ£o)
- Add-on de Finance (pendÃƒÂªncias financeiras)
- Add-on de Stats (reengajamento de clientes inativos)
- Portal do Cliente (solicitaÃƒÂ§ÃƒÂ£o de acesso, envio de link, agendamento, compartilhamento)

#### DPS_IP_Helper
**PropÃƒÂ³sito**: ObtenÃƒÂ§ÃƒÂ£o e validaÃƒÂ§ÃƒÂ£o centralizada de endereÃƒÂ§os IP do cliente, com suporte a proxies, CDNs (Cloudflare) e ambientes de desenvolvimento.

**Entrada/SaÃƒÂ­da**:
- `get_ip()`: ObtÃƒÂ©m IP simples via REMOTE_ADDR Ã¢â€ â€™ string (IP ou 'unknown')
- `get_ip_with_proxy_support()`: ObtÃƒÂ©m IP real atravÃƒÂ©s de proxies/CDNs Ã¢â€ â€™ string (IP ou vazio)
- `get_ip_hash( string $salt )`: ObtÃƒÂ©m hash SHA-256 do IP para rate limiting Ã¢â€ â€™ string (64 caracteres)
- `is_valid_ip( string $ip )`: Valida IPv4 ou IPv6 Ã¢â€ â€™ bool
- `is_valid_ipv4( string $ip )`: Valida apenas IPv4 Ã¢â€ â€™ bool
- `is_valid_ipv6( string $ip )`: Valida apenas IPv6 Ã¢â€ â€™ bool
- `is_localhost( string $ip = null )`: Verifica se ÃƒÂ© localhost Ã¢â€ â€™ bool
- `anonymize( string $ip )`: Anonimiza IP para LGPD/GDPR Ã¢â€ â€™ string

**Exemplos prÃƒÂ¡ticos**:
```php
// Obter IP simples para logging
$ip = DPS_IP_Helper::get_ip();

// Obter IP real atravÃƒÂ©s de CDN (Cloudflare)
$ip = DPS_IP_Helper::get_ip_with_proxy_support();

// Gerar hash para rate limiting
$hash = DPS_IP_Helper::get_ip_hash( 'dps_login_' );
set_transient( 'rate_limit_' . $hash, $count, HOUR_IN_SECONDS );

// Anonimizar IP para logs de longa duraÃƒÂ§ÃƒÂ£o (LGPD)
$anon_ip = DPS_IP_Helper::anonymize( $ip );
// '192.168.1.100' Ã¢â€ â€™ '192.168.1.0'
```

**Headers verificados** (em ordem de prioridade):
1. `HTTP_CF_CONNECTING_IP` - Cloudflare
2. `HTTP_X_REAL_IP` - Nginx proxy
3. `HTTP_X_FORWARDED_FOR` - Proxy padrÃƒÂ£o (usa primeiro IP da lista)
4. `REMOTE_ADDR` - ConexÃƒÂ£o direta

**Boas prÃƒÂ¡ticas**:
- Use `get_ip()` para casos simples (logging, auditoria)
- Use `get_ip_with_proxy_support()` quando hÃƒÂ¡ CDN/proxy (rate limiting, seguranÃƒÂ§a)
- Use `get_ip_hash()` para armazenar referÃƒÂªncias de IP sem expor o endereÃƒÂ§o real
- Use `anonymize()` para logs de longa duraÃƒÂ§ÃƒÂ£o em compliance com LGPD/GDPR

**Add-ons que usam este helper**:
- Portal do Cliente (autenticaÃƒÂ§ÃƒÂ£o, rate limiting, logs de acesso)
- Add-on de Pagamentos (webhooks, auditoria)
- Add-on de IA (rate limiting do chat pÃƒÂºblico)
- Add-on de Finance (auditoria de operaÃƒÂ§ÃƒÂµes)
- Add-on de Registration (rate limiting de cadastros)

#### DPS_Client_Helper
**PropÃƒÂ³sito**: Acesso centralizado a dados de clientes, com suporte a CPT `dps_client` e usermeta do WordPress, eliminando duplicaÃƒÂ§ÃƒÂ£o de cÃƒÂ³digo para obtenÃƒÂ§ÃƒÂ£o de telefone, email, endereÃƒÂ§o e outros metadados.

**Entrada/SaÃƒÂ­da**:
- `get_phone( int $client_id, ?string $source = null )`: ObtÃƒÂ©m telefone do cliente Ã¢â€ â€™ string
- `get_email( int $client_id, ?string $source = null )`: ObtÃƒÂ©m email do cliente Ã¢â€ â€™ string
- `get_whatsapp( int $client_id, ?string $source = null )`: ObtÃƒÂ©m WhatsApp (fallback para phone) Ã¢â€ â€™ string
- `get_name( int $client_id, ?string $source = null )`: ObtÃƒÂ©m nome do cliente Ã¢â€ â€™ string
- `get_display_name( int $client_id, ?string $source = null )`: ObtÃƒÂ©m nome para exibiÃƒÂ§ÃƒÂ£o Ã¢â€ â€™ string
- `get_address( int $client_id, ?string $source = null, string $sep = ', ' )`: ObtÃƒÂ©m endereÃƒÂ§o formatado Ã¢â€ â€™ string
- `get_all_data( int $client_id, ?string $source = null )`: ObtÃƒÂ©m todos os metadados de uma vez Ã¢â€ â€™ array
- `has_valid_phone( int $client_id, ?string $source = null )`: Verifica se tem telefone vÃƒÂ¡lido Ã¢â€ â€™ bool
- `has_valid_email( int $client_id, ?string $source = null )`: Verifica se tem email vÃƒÂ¡lido Ã¢â€ â€™ bool
- `get_pets( int $client_id, array $args = [] )`: ObtÃƒÂ©m lista de pets do cliente Ã¢â€ â€™ array
- `get_pets_count( int $client_id )`: Conta pets do cliente Ã¢â€ â€™ int
- `get_primary_pet( int $client_id )`: ObtÃƒÂ©m pet principal Ã¢â€ â€™ WP_Post|null
- `format_contact_info( int $client_id, ?string $source = null )`: Formata informaÃƒÂ§ÃƒÂµes de contato Ã¢â€ â€™ string (HTML)
- `get_for_display( int $client_id, ?string $source = null )`: ObtÃƒÂ©m dados formatados para exibiÃƒÂ§ÃƒÂ£o Ã¢â€ â€™ array
- `search_by_phone( string $phone, bool $exact = false )`: Busca cliente por telefone Ã¢â€ â€™ int|null
- `search_by_email( string $email )`: Busca cliente por email Ã¢â€ â€™ int|null

**ParÃƒÂ¢metro `$source`**:
- `null` (padrÃƒÂ£o): Auto-detecta se ÃƒÂ© post (`dps_client`) ou user (WordPress user)
- `'post'`: ForÃƒÂ§a busca em post_meta
- `'user'`: ForÃƒÂ§a busca em usermeta

**Constantes de meta keys**:
- `META_PHONE` = 'client_phone'
- `META_EMAIL` = 'client_email'
- `META_WHATSAPP` = 'client_whatsapp'
- `META_ADDRESS` = 'client_address'
- `META_CITY` = 'client_city'
- `META_STATE` = 'client_state'
- `META_ZIP` = 'client_zip'

**Exemplos prÃƒÂ¡ticos**:
```php
// Obter telefone de um cliente (auto-detecta source)
$phone = DPS_Client_Helper::get_phone( $client_id );

// Obter todos os dados de uma vez (mais eficiente)
$data = DPS_Client_Helper::get_all_data( $client_id );
echo $data['name'] . ' - ' . $data['phone'];

// Verificar se tem telefone vÃƒÂ¡lido antes de enviar WhatsApp
if ( DPS_Client_Helper::has_valid_phone( $client_id ) ) {
    $whatsapp = DPS_Client_Helper::get_whatsapp( $client_id );
    // ...enviar mensagem
}

// Buscar cliente por telefone
$existing = DPS_Client_Helper::search_by_phone( '11999887766' );
if ( $existing ) {
    // Cliente jÃƒÂ¡ existe
}

// Para exibiÃƒÂ§ÃƒÂ£o na UI (jÃƒÂ¡ formatado)
$display = DPS_Client_Helper::get_for_display( $client_id );
echo $display['display_name']; // "JoÃƒÂ£o Silva" ou "Cliente sem nome"
echo $display['phone_formatted']; // "(11) 99988-7766"
```

**Boas prÃƒÂ¡ticas**:
- Use `get_all_data()` quando precisar de mÃƒÂºltiplos campos (evita queries repetidas)
- Use `get_for_display()` para dados jÃƒÂ¡ formatados para UI
- O helper integra com `DPS_Phone_Helper` automaticamente quando disponÃƒÂ­vel
- NÃƒÂ£o acesse diretamente `get_post_meta( $id, 'client_phone' )` Ã¢â‚¬â€ use o helper para consistÃƒÂªncia

**Add-ons que usam este helper**:
- Plugin Base (formulÃƒÂ¡rios de cliente, frontend)
- Portal do Cliente (exibiÃƒÂ§ÃƒÂ£o de dados, mensagens)
- Add-on de IA (chat pÃƒÂºblico, agendador)
- Add-on de Push (notificaÃƒÂ§ÃƒÂµes por email/WhatsApp)
- Add-on de Communications (envio de comunicados)
- Add-on de Finance (relatÃƒÂ³rios, cobranÃƒÂ§as)

#### DPS_Message_Helper
**PropÃƒÂ³sito**: Gerenciamento de mensagens de feedback visual (sucesso, erro, aviso) para operaÃƒÂ§ÃƒÂµes administrativas.

**Entrada/SaÃƒÂ­da**:
- `add_success( string $message )`: Adiciona mensagem de sucesso
- `add_error( string $message )`: Adiciona mensagem de erro
- `add_warning( string $message )`: Adiciona mensagem de aviso
- `display_messages()`: Retorna HTML com todas as mensagens pendentes e as remove automaticamente

**Exemplos prÃƒÂ¡ticos**:
```php
// ApÃƒÂ³s salvar cliente com sucesso
DPS_Message_Helper::add_success( __( 'Cliente salvo com sucesso!', 'desi-pet-shower' ) );
wp_safe_redirect( $redirect_url );
exit;

// No inÃƒÂ­cio da seÃƒÂ§ÃƒÂ£o, exibir mensagens pendentes
echo '<div class="dps-section">';
echo DPS_Message_Helper::display_messages(); // Renderiza alertas
echo '<h2>Cadastro de Clientes</h2>';
```

**Boas prÃƒÂ¡ticas**: 
- Use mensagens apÃƒÂ³s operaÃƒÂ§ÃƒÂµes que modificam dados (salvar, excluir, atualizar status)
- Coloque `display_messages()` no inÃƒÂ­cio de cada seÃƒÂ§ÃƒÂ£o do painel para feedback imediato
- Mensagens sÃƒÂ£o armazenadas via transients especÃƒÂ­ficos por usuÃƒÂ¡rio, garantindo isolamento
- Mensagens sÃƒÂ£o exibidas apenas uma vez (single-use) e removidas automaticamente apÃƒÂ³s renderizaÃƒÂ§ÃƒÂ£o

#### DPS_Cache_Control
**PropÃƒÂ³sito**: Gerenciamento de cache de pÃƒÂ¡ginas para garantir que todas as pÃƒÂ¡ginas do sistema DPS nÃƒÂ£o sejam armazenadas em cache, forÃƒÂ§ando conteÃƒÂºdo sempre atualizado.

**Entrada/SaÃƒÂ­da**:
- `init()`: Registra hooks para detecÃƒÂ§ÃƒÂ£o e prevenÃƒÂ§ÃƒÂ£o de cache (chamado automaticamente no boot do plugin)
- `force_no_cache()`: ForÃƒÂ§a desabilitaÃƒÂ§ÃƒÂ£o de cache na requisiÃƒÂ§ÃƒÂ£o atual
- `register_shortcode( string $shortcode )`: Registra shortcode adicional para prevenÃƒÂ§ÃƒÂ£o automÃƒÂ¡tica de cache
- `get_registered_shortcodes()`: Retorna lista de shortcodes registrados

**Constantes definidas quando cache ÃƒÂ© desabilitado**:
- `DONOTCACHEPAGE`: Previne cache de pÃƒÂ¡gina (WP Super Cache, W3 Total Cache, LiteSpeed Cache)
- `DONOTCACHEDB`: Previne cache de queries
- `DONOTMINIFY`: Previne minificaÃƒÂ§ÃƒÂ£o de assets
- `DONOTCDN`: Previne uso de CDN
- `DONOTCACHEOBJECT`: Previne cache de objetos

**Headers HTTP enviados**:
- `Cache-Control: no-cache, must-revalidate, max-age=0`
- `Pragma: no-cache`
- `Expires: Wed, 11 Jan 1984 05:00:00 GMT`

**Exemplos prÃƒÂ¡ticos**:
```php
// Em um shortcode personalizado de add-on, forÃƒÂ§ar no-cache
public function render_meu_shortcode() {
    if ( class_exists( 'DPS_Cache_Control' ) ) {
        DPS_Cache_Control::force_no_cache();
    }
    // ... renderizaÃƒÂ§ÃƒÂ£o do shortcode
}

// Registrar um shortcode personalizado para prevenÃƒÂ§ÃƒÂ£o automÃƒÂ¡tica de cache
add_action( 'init', function() {
    if ( class_exists( 'DPS_Cache_Control' ) ) {
        DPS_Cache_Control::register_shortcode( 'meu_addon_shortcode' );
    }
} );
```

**Boas prÃƒÂ¡ticas**:
- Todos os shortcodes do DPS jÃƒÂ¡ chamam `force_no_cache()` automaticamente
- Para add-ons customizados, sempre inclua a chamada no inÃƒÂ­cio do mÃƒÂ©todo de renderizaÃƒÂ§ÃƒÂ£o
- Use `class_exists( 'DPS_Cache_Control' )` antes de chamar para compatibilidade com versÃƒÂµes anteriores
- A detecÃƒÂ§ÃƒÂ£o automÃƒÂ¡tica via hook `template_redirect` funciona como backup

#### Sistema de Templates SobrescrevÃƒÂ­veis

**PropÃƒÂ³sito**: Permitir que temas customizem a aparÃƒÂªncia de templates do DPS mantendo a lÃƒÂ³gica de negÃƒÂ³cio no plugin. O sistema tambÃƒÂ©m oferece controle sobre quando forÃƒÂ§ar o uso do template do plugin.

**FunÃƒÂ§ÃƒÂµes disponÃƒÂ­veis** (definidas em `includes/template-functions.php`):

| FunÃƒÂ§ÃƒÂ£o | PropÃƒÂ³sito |
|--------|-----------|
| `dps_get_template( $template_name, $args )` | Localiza e inclui um template, permitindo override pelo tema |
| `dps_get_template_path( $template_name )` | Retorna o caminho do template que seria carregado (sem incluÃƒÂ­-lo) |
| `dps_is_template_overridden( $template_name )` | Verifica se um template estÃƒÂ¡ sendo sobrescrito pelo tema |

**Ordem de busca de templates**:
1. Tema filho: `wp-content/themes/CHILD_THEME/dps-templates/{template_name}`
2. Tema pai: `wp-content/themes/PARENT_THEME/dps-templates/{template_name}`
3. Plugin base: `wp-content/plugins/desi-pet-shower-base/templates/{template_name}`

**Filtros disponÃƒÂ­veis**:

| Filtro | PropÃƒÂ³sito | ParÃƒÂ¢metros |
|--------|-----------|------------|
| `dps_use_plugin_template` | ForÃƒÂ§a uso do template do plugin, ignorando override do tema | `$use_plugin (bool)`, `$template_name (string)` |
| `dps_allow_consent_template_override` | Permite que tema sobrescreva o template de consentimento de tosa | `$allow_override (bool)` |

**Actions disponÃƒÂ­veis**:

| Action | PropÃƒÂ³sito | ParÃƒÂ¢metros |
|--------|-----------|------------|
| `dps_template_loaded` | Disparada quando um template ÃƒÂ© carregado | `$path_to_load (string)`, `$template_name (string)`, `$is_theme_override (bool)` |

**Exemplos prÃƒÂ¡ticos**:
```php
// ForÃƒÂ§ar uso do template do plugin para um template especÃƒÂ­fico
add_filter( 'dps_use_plugin_template', function( $use_plugin, $template_name ) {
    if ( $template_name === 'meu-template.php' ) {
        return true; // Sempre usa versÃƒÂ£o do plugin
    }
    return $use_plugin;
}, 10, 2 );

// Permitir override do tema no template de consentimento de tosa
add_filter( 'dps_allow_consent_template_override', '__return_true' );

// Debug: logar qual template estÃƒÂ¡ sendo carregado
add_action( 'dps_template_loaded', function( $path, $name, $is_override ) {
    if ( $is_override ) {
        error_log( "DPS: Template '$name' sendo carregado do tema: $path" );
    }
}, 10, 3 );

// Verificar se um template estÃƒÂ¡ sendo sobrescrito
if ( dps_is_template_overridden( 'tosa-consent-form.php' ) ) {
    // Template do tema estÃƒÂ¡ sendo usado
}
```

**Boas prÃƒÂ¡ticas**:
- O template de consentimento de tosa (`tosa-consent-form.php`) forÃƒÂ§a uso do plugin por padrÃƒÂ£o para garantir que melhorias sejam visÃƒÂ­veis
- Use `dps_get_template_path()` para debug quando templates nÃƒÂ£o aparecem como esperado
- A action `dps_template_loaded` ÃƒÂ© ÃƒÂºtil para logging e diagnÃƒÂ³stico de problemas
- Quando sobrescrever templates no tema, mantenha as variÃƒÂ¡veis esperadas pelo sistema

#### DPS_Base_Template_Engine
**PropÃƒÂ³sito**: Motor de templates compartilhado para renderizaÃƒÂ§ÃƒÂ£o de componentes PHP com output buffering e suporte a override pelo tema. Portado do Frontend Add-on para uso global (Fase 2.4).

**Arquivo**: `includes/class-dps-base-template-engine.php`

**PadrÃƒÂ£o**: Singleton via `DPS_Base_Template_Engine::get_instance()`

**MÃƒÂ©todos**:
- `render( string $template, array $data = [] )`: Renderiza template e retorna HTML. Usa `extract( $data, EXTR_SKIP )` + `ob_start()`/`ob_get_clean()`.
- `exists( string $template )`: Verifica se um template existe (no tema ou no plugin) Ã¢â€ â€™ bool.
- `locateTemplate( string $template )` (private): Busca template em: 1) tema `dps-templates/{prefix}/{file}`, 2) plugin `templates/{file}`.

**Templates disponÃƒÂ­veis** (em `templates/`):
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

### Feedback visual e organizaÃƒÂ§ÃƒÂ£o de interface
- Todos os formulÃƒÂ¡rios principais (clientes, pets, agendamentos) utilizam `DPS_Message_Helper` para feedback apÃƒÂ³s salvar ou excluir
- FormulÃƒÂ¡rios sÃƒÂ£o organizados em fieldsets semÃƒÂ¢nticos com bordas sutis (`1px solid #e5e7eb`) e legends descritivos
- Hierarquia de tÃƒÂ­tulos padronizada: H1 ÃƒÂºnico no topo ("Painel de GestÃƒÂ£o DPS"), H2 para seÃƒÂ§ÃƒÂµes principais, H3 para subseÃƒÂ§ÃƒÂµes
- Design minimalista com paleta reduzida: base neutra (#f9fafb, #e5e7eb, #374151) + 3 cores de status essenciais (verde, amarelo, vermelho)
- Responsividade bÃƒÂ¡sica implementada com media queries para mobile (480px), tablets (768px) e desktops pequenos (1024px)

### Gerenciador de Add-ons

O plugin base inclui um gerenciador de add-ons centralizado (`DPS_Addon_Manager`) que:
- Lista todos os add-ons disponÃƒÂ­veis do ecossistema DPS
- Verifica status de instalaÃƒÂ§ÃƒÂ£o e ativaÃƒÂ§ÃƒÂ£o
- Determina a ordem correta de ativaÃƒÂ§ÃƒÂ£o baseada em dependÃƒÂªncias
- Permite ativar/desativar add-ons em lote respeitando dependÃƒÂªncias

**Classe**: `includes/class-dps-addon-manager.php`

**Menu administrativo**: desi.pet by PRObst Ã¢â€ â€™ Add-ons (`dps-addons`)

#### Categorias de Add-ons

| Categoria | DescriÃƒÂ§ÃƒÂ£o | Add-ons |
|-----------|-----------|---------|
| Essenciais | Funcionalidades base recomendadas | ServiÃƒÂ§os, Financeiro, ComunicaÃƒÂ§ÃƒÂµes |
| OperaÃƒÂ§ÃƒÂ£o | GestÃƒÂ£o do dia a dia | Agenda, Groomers, Assinaturas, Estoque |
| IntegraÃƒÂ§ÃƒÂµes | ConexÃƒÂµes externas | Pagamentos, Push Notifications |
| Cliente | Voltados ao cliente final | Cadastro PÃƒÂºblico, Portal do Cliente, Fidelidade |
| AvanÃƒÂ§ado | Funcionalidades extras | IA, EstatÃƒÂ­sticas |
| Sistema | AdministraÃƒÂ§ÃƒÂ£o e manutenÃƒÂ§ÃƒÂ£o | Backup |

#### DependÃƒÂªncias entre Add-ons

O sistema resolve automaticamente as dependÃƒÂªncias na ordem de ativaÃƒÂ§ÃƒÂ£o:

| Add-on | Depende de |
|--------|-----------|
| Agenda | ServiÃƒÂ§os |
| Assinaturas | ServiÃƒÂ§os, Financeiro |
| Pagamentos | Financeiro |
| IA | Portal do Cliente |

#### API PÃƒÂºblica

```php
// Obter instÃƒÂ¢ncia do gerenciador
$manager = DPS_Addon_Manager::get_instance();

// Verificar se add-on estÃƒÂ¡ ativo
$is_active = $manager->is_active( 'agenda' );

// Verificar dependÃƒÂªncias
$deps = $manager->check_dependencies( 'ai' );
// Retorna: ['satisfied' => false, 'missing' => ['client-portal']]

// Obter ordem recomendada de ativaÃƒÂ§ÃƒÂ£o
$order = $manager->get_activation_order();
// Retorna array ordenado por dependÃƒÂªncias com status de cada add-on

// Ativar mÃƒÂºltiplos add-ons na ordem correta
$result = $manager->activate_addons( ['services', 'agenda', 'finance'] );
// Ativa: services Ã¢â€ â€™ finance Ã¢â€ â€™ agenda (respeitando dependÃƒÂªncias)
```

#### Interface Administrativa

A pÃƒÂ¡gina "Add-ons" exibe:
1. **Ordem de AtivaÃƒÂ§ÃƒÂ£o Recomendada**: Lista visual dos add-ons instalados na ordem sugerida
2. **Categorias de Add-ons**: Cards organizados por categoria com:
   - Nome e ÃƒÂ­cone do add-on
   - Status (Ativo/Inativo/NÃƒÂ£o Instalado)
   - DescriÃƒÂ§ÃƒÂ£o curta
   - DependÃƒÂªncias necessÃƒÂ¡rias
   - Checkbox para seleÃƒÂ§ÃƒÂ£o
3. **AÃƒÂ§ÃƒÂµes em Lote**: BotÃƒÂµes para ativar ou desativar add-ons selecionados

**SeguranÃƒÂ§a**:
- VerificaÃƒÂ§ÃƒÂ£o de nonce em todas as aÃƒÂ§ÃƒÂµes
- Capability `manage_options` para acesso ÃƒÂ  pÃƒÂ¡gina
- Capability `activate_plugins`/`deactivate_plugins` para aÃƒÂ§ÃƒÂµes

### GitHub Updater

O plugin base inclui um sistema de atualizaÃƒÂ§ÃƒÂ£o automÃƒÂ¡tica via GitHub (`DPS_GitHub_Updater`) que:
- Verifica novas versÃƒÂµes diretamente do repositÃƒÂ³rio GitHub
- Notifica atualizaÃƒÂ§ÃƒÂµes disponÃƒÂ­veis no painel de Plugins do WordPress
- Suporta o plugin base e todos os add-ons oficiais
- Usa cache inteligente para evitar chamadas excessivas ÃƒÂ  API

**Classe**: `includes/class-dps-github-updater.php`

**RepositÃƒÂ³rio**: `richardprobst/DPS`

#### Como Funciona

1. **VerificaÃƒÂ§ÃƒÂ£o de VersÃƒÂµes**: O updater consulta a API do GitHub (`/repos/{owner}/{repo}/releases/latest`) para obter a versÃƒÂ£o mais recente.
2. **ComparaÃƒÂ§ÃƒÂ£o**: Compara a versÃƒÂ£o instalada de cada plugin com a versÃƒÂ£o da release mais recente.
3. **NotificaÃƒÂ§ÃƒÂ£o**: Se houver atualizaÃƒÂ§ÃƒÂ£o disponÃƒÂ­vel, injeta os dados no transient de updates do WordPress.
4. **InstalaÃƒÂ§ÃƒÂ£o**: O WordPress usa seu fluxo padrÃƒÂ£o de atualizaÃƒÂ§ÃƒÂ£o para baixar e instalar.

#### ConfiguraÃƒÂ§ÃƒÂ£o

O sistema funciona automaticamente sem configuraÃƒÂ§ÃƒÂ£o adicional. Para desabilitar:

```php
// Desabilitar o updater via hook (em wp-config.php ou plugin)
add_filter( 'dps_github_updater_enabled', '__return_false' );
```

#### API PÃƒÂºblica

```php
// Obter instÃƒÂ¢ncia do updater
$updater = DPS_GitHub_Updater::get_instance();

// ForÃƒÂ§ar verificaÃƒÂ§ÃƒÂ£o (limpa cache)
$release_data = $updater->force_check();

// Obter lista de plugins gerenciados
$plugins = $updater->get_managed_plugins();

// Verificar se um plugin ÃƒÂ© gerenciado
$is_managed = $updater->is_managed_plugin( 'desi-pet-shower-base_plugin/desi-pet-shower-base.php' );
```

#### ForÃƒÂ§ar VerificaÃƒÂ§ÃƒÂ£o Manual

Adicione `?dps_force_update_check=1` ÃƒÂ  URL do painel de Plugins para forÃƒÂ§ar nova verificaÃƒÂ§ÃƒÂ£o:

```
/wp-admin/plugins.php?dps_force_update_check=1
```

#### Requisitos para Releases

Para que o updater reconheÃƒÂ§a uma nova versÃƒÂ£o:
1. A release no GitHub deve usar tags semver (ex: `v1.2.0` ou `1.2.0`)
2. A versÃƒÂ£o na tag deve ser maior que a versÃƒÂ£o instalada
3. Opcionalmente, anexe arquivos `.zip` individuais por plugin para download direto

#### Plugins Gerenciados

| Plugin | Arquivo | Caminho no RepositÃƒÂ³rio |
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

O sistema suporta trÃƒÂªs tipos de agendamentos, identificados pelo metadado `appointment_type`:

#### 1. Agendamento Simples (`simple`)
- **PropÃƒÂ³sito**: Atendimento ÃƒÂºnico, sem recorrÃƒÂªncia
- **Campos especÃƒÂ­ficos**: Permite adicionar TaxiDog com valor personalizado
- **Comportamento**: Status inicial "pendente", precisa ser manualmente atualizado para "realizado"
- **Metadados salvos**: 
  - `appointment_type` = 'simple'
  - `appointment_taxidog` (0 ou 1)
  - `appointment_taxidog_price` (float)
  - `appointment_total_value` (calculado pelo Services Add-on)

#### 2. Agendamento de Assinatura (`subscription`)
- **PropÃƒÂ³sito**: Atendimentos recorrentes (semanal ou quinzenal)
- **Campos especÃƒÂ­ficos**: 
  - FrequÃƒÂªncia (semanal ou quinzenal)
  - Tosa opcional com preÃƒÂ§o e ocorrÃƒÂªncia configurÃƒÂ¡vel
  - TaxiDog disponÃƒÂ­vel mas sem custo adicional
- **Comportamento**: Vincula-se a um registro de assinatura (`dps_subscription`) e gera atendimentos recorrentes
- **Metadados salvos**:
  - `appointment_type` = 'subscription'
  - `subscription_id` (ID do post de assinatura vinculado)
  - `appointment_tosa` (0 ou 1)
  - `appointment_tosa_price` (float)
  - `appointment_tosa_occurrence` (1-4 para semanal, 1-2 para quinzenal)
  - `subscription_base_value`, `subscription_total_value`

#### 3. Agendamento Passado (`past`)
- **PropÃƒÂ³sito**: Registrar atendimentos jÃƒÂ¡ realizados anteriormente
- **Campos especÃƒÂ­ficos**:
  - Status do Pagamento: dropdown com opÃƒÂ§ÃƒÂµes "Pago" ou "Pendente"
  - Valor Pendente: campo numÃƒÂ©rico condicional (exibido apenas se status = "Pendente")
- **Comportamento**: 
  - Status inicial automaticamente definido como "realizado"
  - TaxiDog e Tosa nÃƒÂ£o disponÃƒÂ­veis (nÃƒÂ£o aplicÃƒÂ¡vel para registros passados)
  - Permite controlar pagamentos pendentes de atendimentos histÃƒÂ³ricos
- **Metadados salvos**:
  - `appointment_type` = 'past'
  - `appointment_status` = 'realizado' (definido automaticamente)
  - `past_payment_status` ('paid' ou 'pending')
  - `past_payment_value` (float, salvo apenas se status = 'pending')
  - `appointment_total_value` (calculado pelo Services Add-on)
- **Casos de uso**:
  - MigraÃƒÂ§ÃƒÂ£o de dados de sistemas anteriores
  - Registro de atendimentos realizados antes da implementaÃƒÂ§ÃƒÂ£o do sistema
  - Controle de pagamentos em atraso de atendimentos histÃƒÂ³ricos

**Controle de visibilidade de campos (JavaScript)**:
- A funÃƒÂ§ÃƒÂ£o `updateTypeFields()` em `dps-appointment-form.js` controla a exibiÃƒÂ§ÃƒÂ£o condicional de campos baseada no tipo selecionado
- Campos de frequÃƒÂªncia: visÃƒÂ­veis apenas para tipo `subscription`
- Campos de tosa: visÃƒÂ­veis apenas para tipo `subscription`
- Campos de pagamento passado: visÃƒÂ­veis apenas para tipo `past`
- TaxiDog com preÃƒÂ§o: visÃƒÂ­vel apenas para tipo `simple`


### HistÃƒÂ³rico e exportaÃƒÂ§ÃƒÂ£o de agendamentos
- A coleta de atendimentos finalizados ÃƒÂ© feita em lotes pelo `WP_Query` com `fields => 'ids'`, `no_found_rows => true` e tamanho configurÃƒÂ¡vel via filtro `dps_history_batch_size` (padrÃƒÂ£o: 200). Isso evita uma ÃƒÂºnica consulta gigante em tabelas volumosas e permite tratar listas grandes de forma incremental.
- As metas dos agendamentos sÃƒÂ£o prÃƒÂ©-carregadas com `update_meta_cache('post')` antes do loop, reduzindo consultas repetidas ÃƒÂ s mesmas linhas durante a renderizaÃƒÂ§ÃƒÂ£o e exportaÃƒÂ§ÃƒÂ£o.
- Clientes, pets e serviÃƒÂ§os relacionados sÃƒÂ£o resolvidos com caches em memÃƒÂ³ria por ID, evitando `get_post` duplicadas quando o mesmo registro aparece em vÃƒÂ¡rias linhas.
- O botÃƒÂ£o de exportaÃƒÂ§ÃƒÂ£o gera CSV apenas com as colunas exibidas e respeita os filtros aplicados na tabela, o que limita o volume exportado a um subconjunto relevante e jÃƒÂ¡ paginado/filtrado pelo usuÃƒÂ¡rio.

## Add-ons complementares (`plugins/`)

### Text Domains para InternacionalizaÃƒÂ§ÃƒÂ£o (i18n)

Todos os plugins e add-ons do DPS seguem o padrÃƒÂ£o WordPress de text domains para internacionalizaÃƒÂ§ÃƒÂ£o. Os text domains oficiais sÃƒÂ£o:

**Plugin Base**:
- `desi-pet-shower` - Plugin base que fornece CPTs e funcionalidades core

**Add-ons**:
- `dps-agenda-addon` - Agenda e agendamentos
- `dps-ai` - Assistente de IA
- `dps-backup-addon` - Backup e restauraÃƒÂ§ÃƒÂ£o
- `dps-booking-addon` - PÃƒÂ¡gina dedicada de agendamentos
- `dps-client-portal` - Portal do cliente
- `dps-communications-addon` - ComunicaÃƒÂ§ÃƒÂµes (WhatsApp, SMS, email)
- `dps-finance-addon` - Financeiro (transaÃƒÂ§ÃƒÂµes, parcelas, cobranÃƒÂ§as)
- `dps-groomers-addon` - GestÃƒÂ£o de groomers/profissionais
- `dps-loyalty-addon` - Campanhas e fidelidade
- `dps-payment-addon` - IntegraÃƒÂ§ÃƒÂ£o de pagamentos
- `dps-push-addon` - NotificaÃƒÂ§ÃƒÂµes push
- `dps-registration-addon` - Registro e autenticaÃƒÂ§ÃƒÂ£o
- `dps-services-addon` - ServiÃƒÂ§os e produtos
- `dps-stats-addon` - EstatÃƒÂ­sticas e relatÃƒÂ³rios
- `dps-stock-addon` - Controle de estoque
- `dps-subscription-addon` - Assinaturas e recorrÃƒÂªncia

**Boas prÃƒÂ¡ticas de i18n**:
- Use sempre `__()`, `_e()`, `esc_html__()`, `esc_attr__()` ou `esc_html_e()` para strings exibidas ao usuÃƒÂ¡rio
- Sempre especifique o text domain correto do plugin/add-on correspondente
- Para strings JavaScript em `prompt()` ou `alert()`, use `esc_js( __() )` para escapar e traduzir
- Mensagens de erro, sucesso, labels de formulÃƒÂ¡rio e textos de interface devem sempre ser traduzÃƒÂ­veis
- Dados de negÃƒÂ³cio (nomes de clientes, endereÃƒÂ§os hardcoded, etc.) nÃƒÂ£o precisam de traduÃƒÂ§ÃƒÂ£o

**Carregamento de text domains (WordPress 6.7+)**:
- Todos os plugins devem incluir o header `Domain Path: /languages` para indicar onde os arquivos de traduÃƒÂ§ÃƒÂ£o devem ser armazenados
- Add-ons devem carregar text domains usando `load_plugin_textdomain()` no hook `init` com prioridade 1
- Instanciar classes principais no hook `init` com prioridade 5 (apÃƒÂ³s carregamento do text domain)
- Isso garante que strings traduzÃƒÂ­veis no constructor sejam traduzidas corretamente
- MÃƒÂ©todos de registro (CPT, taxonomias, etc.) devem ser adicionados ao `init` com prioridade padrÃƒÂ£o (10)
- **NÃƒÂ£o** carregar text domains ou instanciar classes antes do hook `init` (evitar `plugins_loaded` ou escopo global)

**Status de localizaÃƒÂ§ÃƒÂ£o pt_BR**:
- Ã¢Å“â€¦ Todos os 17 plugins (1 base + 16 add-ons) possuem headers `Text Domain` e `Domain Path` corretos
- Ã¢Å“â€¦ Todos os plugins carregam text domain no hook `init` com prioridade 1
- Ã¢Å“â€¦ Todas as classes sÃƒÂ£o inicializadas no hook `init` com prioridade 5
- Ã¢Å“â€¦ Todo cÃƒÂ³digo, comentÃƒÂ¡rios e strings estÃƒÂ£o em PortuguÃƒÂªs do Brasil
- Ã¢Å“â€¦ Sistema pronto para expansÃƒÂ£o multilÃƒÂ­ngue com arquivos .po/.mo em `/languages`

---

### Estrutura de Menus Administrativos

Todos os add-ons do DPS devem registrar seus menus e submenus sob o menu principal **"desi.pet by PRObst"** (slug: `desi-pet-shower`) para manter a interface administrativa organizada e unificada.

**Menu Principal** (criado pelo plugin base):
- Slug: `desi-pet-shower`
- ÃƒÂcone: `dashicons-pets`
- Capability: `manage_options`
- PosiÃƒÂ§ÃƒÂ£o: 56 (apÃƒÂ³s "Settings")

**Submenus Ativos** (registrados pelo plugin base e add-ons):
- **Assistente de IA** (`dps-ai-settings`) - AI Add-on (configuraÃƒÂ§ÃƒÂµes do assistente virtual)
- **Backup & RestauraÃƒÂ§ÃƒÂ£o** (`dps-backup`) - Backup Add-on (exportar/importar dados)
- **Campanhas** (`edit.php?post_type=dps_campaign`) - Loyalty Add-on (listagem de campanhas)
- **Campanhas & Fidelidade** (`dps-loyalty`) - Loyalty Add-on (configuraÃƒÂ§ÃƒÂµes de pontos e indicaÃƒÂ§ÃƒÂµes)
- **Clientes** (`dps-clients-settings`) - Plugin Base (define a URL da pÃƒÂ¡gina dedicada de cadastro exibida nos atalhos da aba Clientes)
- **ComunicaÃƒÂ§ÃƒÂµes** (`dps-communications`) - Communications Add-on (templates e gateways)
- **FormulÃƒÂ¡rio de Cadastro** (`dps-registration-settings`) - Registration Add-on (configuraÃƒÂ§ÃƒÂµes do formulÃƒÂ¡rio pÃƒÂºblico para clientes se cadastrarem)
- **Logins de Clientes** (`dps-client-logins`) - Client Portal Add-on (gerenciar tokens de acesso)
- **Logs do Sistema** (`dps-logs`) - Plugin Base (visualizaÃƒÂ§ÃƒÂ£o de logs do sistema)
- **Mensagens do Portal** (`edit.php?post_type=dps_portal_message`) - Client Portal Add-on (mensagens enviadas pelos clientes)
- **NotificaÃƒÂ§ÃƒÂµes** (`dps-push-notifications`) - Push Add-on (push, agenda, relatÃƒÂ³rios, Telegram)
- **Pagamentos** (`dps-payment-settings`) - Payment Add-on (Mercado Pago, PIX)
- **Portal do Cliente** (`dps-client-portal-settings`) - Client Portal Add-on (configuraÃƒÂ§ÃƒÂµes do portal)

**Nomenclatura de Menus - Diretrizes de Usabilidade**:
- Use nomes curtos e descritivos que indiquem claramente a funÃƒÂ§ÃƒÂ£o
- Evite prefixos redundantes como "DPS" ou "desi.pet by PRObst" nos nomes de submenu
- Use verbos ou substantivos que descrevam a aÃƒÂ§ÃƒÂ£o/entidade gerenciada
- Exemplos de nomes descritivos:
  - Ã¢Å“â€¦ "Logs do Sistema" (indica claramente que sÃƒÂ£o logs tÃƒÂ©cnicos)
  - Ã¢Å“â€¦ "Backup & RestauraÃƒÂ§ÃƒÂ£o" (aÃƒÂ§ÃƒÂµes disponÃƒÂ­veis)
  - Ã¢Å“â€¦ "FormulÃƒÂ¡rio de Cadastro" (indica que ÃƒÂ© um formulÃƒÂ¡rio para clientes se registrarem)
  - Ã¢ÂÅ’ "DPS Logs" (prefixo redundante - jÃƒÂ¡ estÃƒÂ¡ no menu pai)
  - Ã¢ÂÅ’ "Settings" (genÃƒÂ©rico demais)
  - Ã¢ÂÅ’ "Cadastro PÃƒÂºblico" (pouco intuitivo, prefira "FormulÃƒÂ¡rio de Cadastro")

**Boas prÃƒÂ¡ticas para registro de menus**:
- Sempre use `add_submenu_page()` com `'desi-pet-shower'` como menu pai
- Use prioridade 20 no hook `admin_menu` para garantir que o menu pai jÃƒÂ¡ existe:
  ```php
  add_action( 'admin_menu', [ $this, 'register_admin_menu' ], 20 );
  ```
- Evite criar menus prÃƒÂ³prios separados (ex: `add_menu_page()` em add-ons)
- Para CPTs que precisam aparecer no menu, use `show_in_menu => 'desi-pet-shower'` ao registrar o CPT:
  ```php
  register_post_type( 'meu_cpt', [
      'show_in_menu' => 'desi-pet-shower', // Agrupa no menu principal
      // ...
  ] );
  ```
- Prefira integraÃƒÂ§ÃƒÂ£o via `DPS_Settings_Frontend::register_tab()` para adicionar abas na pÃƒÂ¡gina de configuraÃƒÂ§ÃƒÂµes. Os hooks legados (`dps_settings_nav_tabs`, `dps_settings_sections`) estÃƒÂ£o depreciados.

**HistÃƒÂ³rico de correÃƒÂ§ÃƒÂµes**:
- **2025-01-13**: Hooks legados `dps_settings_nav_tabs` e `dps_settings_sections` depreciados em favor do sistema moderno de abas
- **2025-12-01**: Mensagens do Portal migrado de menu prÃƒÂ³prio para submenu do desi.pet by PRObst (CPT com show_in_menu)
- **2025-12-01**: Cadastro PÃƒÂºblico renomeado para "FormulÃƒÂ¡rio de Cadastro" (mais intuitivo)
- **2025-12-01**: Logs do Sistema migrado de menu prÃƒÂ³prio para submenu do desi.pet by PRObst
- **2025-11-24**: Adicionado menu administrativo ao Client Portal Add-on (Portal do Cliente e Logins de Clientes)
- **2024-11-24**: Corrigida prioridade de registro de menus em todos os add-ons (de 10 para 20)
- **2024-11-24**: Loyalty Add-on migrado de menu prÃƒÂ³prio (`dps-loyalty-addon`) para submenu unificado (`desi-pet-shower`)

---

### Agenda (`desi-pet-shower-agenda_addon`)

**DiretÃƒÂ³rio**: `plugins/desi-pet-shower-agenda`

**PropÃƒÂ³sito e funcionalidades principais**:
- Gerenciar agenda de atendimentos e cobranÃƒÂ§as pendentes
- Enviar lembretes automÃƒÂ¡ticos diÃƒÂ¡rios aos clientes
- Atualizar status de agendamentos via interface AJAX
- **[Deprecated v1.1.0]** Endpoint `dps_get_services_details` (movido para Services Add-on)

**Shortcodes expostos**:
- `[dps_agenda_page]`: renderiza pÃƒÂ¡gina de agenda com filtros e aÃƒÂ§ÃƒÂµes
- `[dps_charges_notes]`: **[Deprecated]** redirecionado para Finance Add-on (`[dps_fin_docs]`)

**CPTs, tabelas e opÃƒÂ§ÃƒÂµes**:
- NÃƒÂ£o cria CPTs prÃƒÂ³prios; consome `dps_agendamento` do nÃƒÂºcleo
- Cria pÃƒÂ¡ginas automaticamente: "Agenda DPS"
- Options: `dps_agenda_page_id`

**Meta keys de agendamento** (post meta de `dps_agendamento`):
- `_dps_checklist`: checklist operacional com status por etapa (prÃƒÂ©-banho, banho, secagem, tosa, orelhas/unhas, acabamento) e histÃƒÂ³rico de retrabalho
- `_dps_checkin`: dados de check-in (horÃƒÂ¡rio, observaÃƒÂ§ÃƒÂµes, itens de seguranÃƒÂ§a com severidade)
- `_dps_checkout`: dados de check-out (horÃƒÂ¡rio, observaÃƒÂ§ÃƒÂµes, itens de seguranÃƒÂ§a)

**Hooks consumidos**:
- Nenhum hook especÃƒÂ­fico do nÃƒÂºcleo (opera diretamente sobre CPTs)

**Hooks disparados**:
- `dps_agenda_send_reminders`: cron job diÃƒÂ¡rio para envio de lembretes
- `dps_checklist_rework_registered( $appointment_id, $step_key, $reason )`: quando uma etapa do checklist precisa de retrabalho
- `dps_appointment_checked_in( $appointment_id, $data )`: apÃƒÂ³s check-in registrado
- `dps_appointment_checked_out( $appointment_id, $data )`: apÃƒÂ³s check-out registrado

**Filtros**:
- `dps_checklist_default_steps`: permite add-ons adicionarem etapas ao checklist operacional (ex.: hidrataÃƒÂ§ÃƒÂ£o, ozÃƒÂ´nio)
- `dps_checkin_safety_items`: permite add-ons adicionarem itens de seguranÃƒÂ§a ao check-in/check-out

**Endpoints AJAX**:
- `dps_update_status`: atualiza status de agendamento
- `dps_checklist_update`: atualiza status de uma etapa do checklist (nonce: `dps_checklist`)
- `dps_checklist_rework`: registra retrabalho em uma etapa do checklist (nonce: `dps_checklist`)
- `dps_appointment_checkin`: registra check-in com observaÃƒÂ§ÃƒÂµes e itens de seguranÃƒÂ§a (nonce: `dps_checkin`)
- `dps_appointment_checkout`: registra check-out com observaÃƒÂ§ÃƒÂµes e itens de seguranÃƒÂ§a (nonce: `dps_checkin`)
- `dps_get_services_details`: **[Deprecated v1.1.0]** mantido por compatibilidade, delega para `DPS_Services_API::get_services_details()`

**DependÃƒÂªncias**:
- Depende do plugin base para CPTs de agendamento
- **[Recomendado]** Services Add-on para cÃƒÂ¡lculo de valores via API
- Integra-se com add-on de ComunicaÃƒÂ§ÃƒÂµes para envio de mensagens (se ativo)
- Aviso exibido se Finance Add-on nÃƒÂ£o estiver ativo (funcionalidades financeiras limitadas)

**Introduzido em**: v0.1.0 (estimado)

**Assets**:
- `assets/js/agenda-addon.js`: interaÃƒÂ§ÃƒÂµes AJAX e feedback visual
- `assets/js/checklist-checkin.js`: interaÃƒÂ§ÃƒÂµes do checklist operacional e check-in/check-out
- `assets/css/checklist-checkin.css`: estilos M3 para checklist e check-in/check-out
- **[Deprecated]** `agenda-addon.js` e `agenda.js` na raiz (devem ser removidos)

**Classes de serviÃƒÂ§o**:
- `DPS_Agenda_Checklist_Service`: CRUD de checklist operacional com etapas, progresso e retrabalho
- `DPS_Agenda_Checkin_Service`: check-in/check-out com itens de seguranÃƒÂ§a e cÃƒÂ¡lculo de duraÃƒÂ§ÃƒÂ£o

**ObservaÃƒÂ§ÃƒÂµes**:
- Implementa `register_deactivation_hook` para limpar cron job `dps_agenda_send_reminders` ao desativar
- **[v1.1.0]** LÃƒÂ³gica de serviÃƒÂ§os movida para Services Add-on; Agenda delega cÃƒÂ¡lculos para `DPS_Services_API`
- **DocumentaÃƒÂ§ÃƒÂ£o completa**: `docs/analysis/AGENDA_ADDON_ANALYSIS.md` (anÃƒÂ¡lise profunda de cÃƒÂ³digo, funcionalidades, layout e melhorias propostas)
- **DocumentaÃƒÂ§ÃƒÂ£o de layout**: `docs/analysis/AGENDA_ADDON_ANALYSIS.md` (seÃƒÂ§ÃƒÂµes de UX, responsividade e acessibilidade)

---

### Backup & RestauraÃƒÂ§ÃƒÂ£o (`desi-pet-shower-backup_addon`)

**DiretÃƒÂ³rio**: `plugins/desi-pet-shower-backup`

**PropÃƒÂ³sito e funcionalidades principais**:
- Exportar todo o conteÃƒÂºdo do sistema em formato JSON (CPTs, metadados, options, tabelas, anexos)
- Restaurar dados de backups anteriores com mapeamento inteligente de IDs
- Proteger operaÃƒÂ§ÃƒÂµes com nonces, validaÃƒÂ§ÃƒÂµes e transaÃƒÂ§ÃƒÂµes SQL
- Suportar migraÃƒÂ§ÃƒÂ£o entre ambientes WordPress

**Shortcodes expostos**: Nenhum

**Menus administrativos**:
- **Backup & RestauraÃƒÂ§ÃƒÂ£o** (`dps-backup`): interface para exportar e restaurar dados

**CPTs, tabelas e opÃƒÂ§ÃƒÂµes**:
- NÃƒÂ£o cria CPTs ou tabelas prÃƒÂ³prias
- **Exporta/Importa**: todos os CPTs prefixados com `dps_`, tabelas `dps_*`, options `dps_*`
- Options de histÃƒÂ³rico (planejado): `dps_backup_history`, `dps_backup_settings`

**Hooks consumidos**:
- `admin_menu` (prioridade 20): registra submenu sob "desi.pet by PRObst"
- `admin_post_dps_backup_export`: processa exportaÃƒÂ§ÃƒÂ£o de backup
- `admin_post_dps_backup_import`: processa importaÃƒÂ§ÃƒÂ£o de backup

**Hooks disparados**: Nenhum (opera de forma autÃƒÂ´noma)

**SeguranÃƒÂ§a implementada**:
- Ã¢Å“â€¦ Nonces em exportaÃƒÂ§ÃƒÂ£o e importaÃƒÂ§ÃƒÂ£o (`dps_backup_nonce`)
- Ã¢Å“â€¦ VerificaÃƒÂ§ÃƒÂ£o de capability `manage_options`
- Ã¢Å“â€¦ ValidaÃƒÂ§ÃƒÂ£o de extensÃƒÂ£o (apenas `.json`) e tamanho (mÃƒÂ¡x. 50MB)
- Ã¢Å“â€¦ SanitizaÃƒÂ§ÃƒÂ£o de tabelas e options (apenas prefixo `dps_`)
- Ã¢Å“â€¦ DeserializaÃƒÂ§ÃƒÂ£o segura (`allowed_classes => false`)
- Ã¢Å“â€¦ TransaÃƒÂ§ÃƒÂµes SQL com rollback em caso de falha

**DependÃƒÂªncias**:
- **ObrigatÃƒÂ³ria**: Plugin base DPS (verifica `DPS_Base_Plugin`)
- Acessa todos os CPTs e tabelas do sistema para exportaÃƒÂ§ÃƒÂ£o/importaÃƒÂ§ÃƒÂ£o

**Introduzido em**: v0.1.0 (estimado)

**VersÃƒÂ£o atual**: 1.0.0

**ObservaÃƒÂ§ÃƒÂµes**:
- Arquivo ÃƒÂºnico de 1338 linhas; candidato a refatoraÃƒÂ§ÃƒÂ£o modular futura
- Suporta exportaÃƒÂ§ÃƒÂ£o de anexos (fotos de pets) e documentos financeiros (`dps_docs`)
- Mapeamento inteligente de IDs: clientes Ã¢â€ â€™ pets Ã¢â€ â€™ agendamentos Ã¢â€ â€™ transaÃƒÂ§ÃƒÂµes

**AnÃƒÂ¡lise completa**: Consulte `docs/analysis/BACKUP_ADDON_ANALYSIS.md` para anÃƒÂ¡lise detalhada de cÃƒÂ³digo, funcionalidades, seguranÃƒÂ§a e melhorias propostas

---

### Booking (`desi-pet-shower-booking`)

**DiretÃƒÂ³rio**: `plugins/desi-pet-shower-booking`  
**VersÃƒÂ£o**: 1.3.0

**PropÃƒÂ³sito e funcionalidades principais**:
- PÃƒÂ¡gina dedicada de agendamentos para administradores
- Mesma funcionalidade da aba Agendamentos do Painel de GestÃƒÂ£o DPS, porÃƒÂ©m em pÃƒÂ¡gina independente
- FormulÃƒÂ¡rio completo com seleÃƒÂ§ÃƒÂ£o de cliente, pets, serviÃƒÂ§os, data/hora, tipo de agendamento (avulso/assinatura) e status de pagamento
- Tela de confirmaÃƒÂ§ÃƒÂ£o pÃƒÂ³s-agendamento com resumo e aÃƒÂ§ÃƒÂµes rÃƒÂ¡pidas (WhatsApp, novo agendamento, voltar ao painel)
- Design system migrado para Material 3 Expressive (v1.3.0)
- OtimizaÃƒÂ§ÃƒÂµes de performance (batch queries para owners de pets)
- ValidaÃƒÂ§ÃƒÂµes granulares de seguranÃƒÂ§a (verificaÃƒÂ§ÃƒÂ£o por agendamento especÃƒÂ­fico)

**Shortcodes expostos**:
- `[dps_booking_form]`: renderiza formulÃƒÂ¡rio completo de agendamento

**CPTs, tabelas e opÃƒÂ§ÃƒÂµes**:
- NÃƒÂ£o cria CPTs ou tabelas prÃƒÂ³prias; consome `dps_agendamento` do nÃƒÂºcleo
- Cria pÃƒÂ¡gina automaticamente na ativaÃƒÂ§ÃƒÂ£o: "Agendamento de ServiÃƒÂ§os"
- Options: `dps_booking_page_id`

**Hooks consumidos**:
- `dps_base_after_save_appointment`: captura agendamento salvo para exibir tela de confirmaÃƒÂ§ÃƒÂ£o
- `dps_base_appointment_fields`: permite injeÃƒÂ§ÃƒÂ£o de campos customizados por add-ons
- `dps_base_appointment_assignment_fields`: permite adicionar campos de atribuiÃƒÂ§ÃƒÂ£o

**Hooks disparados**: Nenhum hook prÃƒÂ³prio

**Capabilities verificadas**:
- `manage_options` (admin total)
- `dps_manage_clients` (gestÃƒÂ£o de clientes)
- `dps_manage_pets` (gestÃƒÂ£o de pets)
- `dps_manage_appointments` (gestÃƒÂ£o de agendamentos)

**Assets (v1.3.0)**:
- `booking-addon.css`: Estilos M3 Expressive com semantic mapping, 100% tokens M3
- DependÃƒÂªncia condicional de `dps-design-tokens.css` via check de `DPS_BASE_URL`
- Assets do base plugin carregados via `DPS_Base_Plugin::enqueue_frontend_assets()`

**Melhorias de seguranÃƒÂ§a (v1.3.0)**:
- MÃƒÂ©todo `can_edit_appointment()`: valida se usuÃƒÂ¡rio pode editar agendamento especÃƒÂ­fico
- VerificaÃƒÂ§ÃƒÂ£o de `can_access()` antes de renderizar seÃƒÂ§ÃƒÂ£o
- DocumentaÃƒÂ§ÃƒÂ£o phpcs para parÃƒÂ¢metros GET read-only

**OtimizaÃƒÂ§ÃƒÂµes de performance (v1.3.0)**:
- Batch fetch de owners de pets (reduÃƒÂ§ÃƒÂ£o de N+1 queries: 100+ Ã¢â€ â€™ 1)
- Preparado para futura paginaÃƒÂ§ÃƒÂ£o de clientes

**Acessibilidade (v1.3.0)**:
- `aria-hidden="true"` em todos emojis decorativos
- Suporte a `prefers-reduced-motion` em animaÃƒÂ§ÃƒÂµes
- ARIA roles e labels conforme padrÃƒÂµes do base plugin

**Endpoints AJAX**: Nenhum

**DependÃƒÂªncias**:
- Depende do plugin base para CPTs de agendamento e helpers globais
- Integra-se com Services Add-on para listagem de serviÃƒÂ§os disponÃƒÂ­veis
- Integra-se com Groomers Add-on para atribuiÃƒÂ§ÃƒÂ£o de profissionais (se ativo)

**Introduzido em**: v1.0.0

**Assets**:
- `assets/css/dps-booking-form.css`: estilos do formulÃƒÂ¡rio de agendamento
- `assets/js/dps-booking-form.js`: interaÃƒÂ§ÃƒÂµes do formulÃƒÂ¡rio (seleÃƒÂ§ÃƒÂ£o de pets, datas, etc.)

**ObservaÃƒÂ§ÃƒÂµes**:
- Assets carregados condicionalmente apenas na pÃƒÂ¡gina de agendamento (`dps_booking_page_id`)
- Implementa `register_activation_hook` para criar pÃƒÂ¡gina automaticamente
- FormulÃƒÂ¡rio reutiliza lÃƒÂ³gica de salvamento do plugin base (`save_appointment`)

---

### Campanhas & Fidelidade (`desi-pet-shower-loyalty_addon`)

**DiretÃƒÂ³rio**: `plugins/desi-pet-shower-loyalty`

**PropÃƒÂ³sito e funcionalidades principais**:
- Gerenciar programa de pontos por faturamento
- MÃƒÂ³dulo "Indique e Ganhe" com cÃƒÂ³digos ÃƒÂºnicos e recompensas
- Criar e executar campanhas de marketing direcionadas

**Shortcodes expostos**: Nenhum

**CPTs, tabelas e opÃƒÂ§ÃƒÂµes**:
- CPT: `dps_campaign` (campanhas de marketing)
- Tabela: `dps_referrals` (indicaÃƒÂ§ÃƒÂµes de clientes)
- Options: configuraÃƒÂ§ÃƒÂµes de pontos, recompensas e elegibilidade

**Hooks consumidos**:
- `dps_registration_after_client_created`: registra indicaÃƒÂ§ÃƒÂµes no cadastro pÃƒÂºblico
- `dps_finance_booking_paid`: bonifica indicador/indicado na primeira cobranÃƒÂ§a paga
- `dps_base_nav_tabs_after_history`: adiciona aba "Campanhas & Fidelidade"
- `dps_base_sections_after_history`: renderiza conteÃƒÂºdo da aba

**Hooks disparados**: Nenhum

**DependÃƒÂªncias**:
- Integra-se com add-on Financeiro para bonificaÃƒÂ§ÃƒÂµes
- Integra-se com add-on de Cadastro PÃƒÂºblico para capturar cÃƒÂ³digos de indicaÃƒÂ§ÃƒÂ£o
- Integra-se com Portal do Cliente para exibir cÃƒÂ³digo/link de convite

**Introduzido em**: v0.2.0

**ObservaÃƒÂ§ÃƒÂµes**:
- Tabela `dps_referrals` criada via `dbDelta` na ativaÃƒÂ§ÃƒÂ£o
- Oferece funÃƒÂ§ÃƒÂµes globais para crÃƒÂ©dito e resgate de pontos

---

### ComunicaÃƒÂ§ÃƒÂµes (`desi-pet-shower-communications_addon`)

**DiretÃƒÂ³rio**: `plugins/desi-pet-shower-communications`

**PropÃƒÂ³sito e funcionalidades principais**:
- **Centralizar TODAS as comunicaÃƒÂ§ÃƒÂµes do sistema** via API pÃƒÂºblica `DPS_Communications_API`
- Enviar mensagens via WhatsApp, e-mail e SMS (futuro)
- Aplicar templates configurÃƒÂ¡veis com placeholders dinÃƒÂ¢micos
- Registrar logs automÃƒÂ¡ticos de todas as comunicaÃƒÂ§ÃƒÂµes via `DPS_Logger`
- Fornecer hooks para extensibilidade por outros add-ons

**Arquitetura - Camada Centralizada**:
- **API Central**: `DPS_Communications_API` (singleton) expÃƒÂµe mÃƒÂ©todos pÃƒÂºblicos
- **Gatilhos**: Agenda, Portal e outros add-ons **delegam** envios para a API
- **Interfaces mantidas**: BotÃƒÂµes de aÃƒÂ§ÃƒÂ£o (wa.me links) **permanecem** na Agenda e Portal
- **LÃƒÂ³gica de envio**: Concentrada na API, nÃƒÂ£o duplicada entre add-ons

**API PÃƒÂºblica** (`includes/class-dps-communications-api.php`):
```php
$api = DPS_Communications_API::get_instance();

// MÃƒÂ©todos principais:
$api->send_whatsapp( $to, $message, $context = [] );
$api->send_email( $to, $subject, $body, $context = [] );
$api->send_appointment_reminder( $appointment_id );
$api->send_payment_notification( $client_id, $amount_cents, $context = [] );
$api->send_message_from_client( $client_id, $message, $context = [] );
```

**Shortcodes expostos**: Nenhum (operaÃƒÂ§ÃƒÂ£o via API e configuraÃƒÂ§ÃƒÂµes)

**CPTs, tabelas e opÃƒÂ§ÃƒÂµes**:
- NÃƒÂ£o cria CPTs ou tabelas prÃƒÂ³prias
- Option `dps_comm_settings`: configuraÃƒÂ§ÃƒÂµes de gateways e templates
  - `whatsapp_api_key`: chave de API do gateway WhatsApp
  - `whatsapp_api_url`: endpoint base do gateway
  - `default_email_from`: e-mail remetente padrÃƒÂ£o
  - `template_confirmation`: template de confirmaÃƒÂ§ÃƒÂ£o de agendamento
  - `template_reminder`: template de lembrete (placeholders: `{client_name}`, `{pet_name}`, `{date}`, `{time}`)
  - `template_post_service`: template de pÃƒÂ³s-atendimento

**Hooks consumidos**:
- `dps_base_after_save_appointment`: dispara confirmaÃƒÂ§ÃƒÂ£o apÃƒÂ³s salvar agendamento
- ~~`dps_settings_nav_tabs`~~: (migrado para sistema moderno) aba "ComunicaÃƒÂ§ÃƒÂµes" registrada em `DPS_Settings_Frontend`
- ~~`dps_settings_sections`~~: (migrado para sistema moderno) renderizaÃƒÂ§ÃƒÂ£o via callback em `register_tab()`

**Hooks disparados (Actions)**:
- `dps_after_whatsapp_sent( $to, $message, $context, $result )`: apÃƒÂ³s envio de WhatsApp
- `dps_after_email_sent( $to, $subject, $body, $context, $result )`: apÃƒÂ³s envio de e-mail
- `dps_after_reminder_sent( $appointment_id, $sent )`: apÃƒÂ³s envio de lembrete
- `dps_comm_send_appointment_reminder`: cron job para lembretes de agendamento
- `dps_comm_send_post_service`: cron job para mensagens pÃƒÂ³s-atendimento

**Hooks disparados (Filters)**:
- `dps_comm_whatsapp_message( $message, $to, $context )`: filtra mensagem WhatsApp antes de enviar
- `dps_comm_email_subject( $subject, $to, $context )`: filtra assunto de e-mail
- `dps_comm_email_body( $body, $to, $context )`: filtra corpo de e-mail
- `dps_comm_email_headers( $headers, $to, $context )`: filtra headers de e-mail
- `dps_comm_reminder_message( $message, $appointment_id )`: filtra mensagem de lembrete
- `dps_comm_payment_notification_message( $message, $client_id, $amount_cents, $context )`: filtra notificaÃƒÂ§ÃƒÂ£o de pagamento

**DependÃƒÂªncias**:
- Depende do plugin base para `DPS_Logger` e `DPS_Phone_Helper`
- Agenda e Portal delegam comunicaÃƒÂ§ÃƒÂµes para esta API (dependÃƒÂªncia soft)

**IntegraÃƒÂ§ÃƒÂ£o com outros add-ons**:
- **Agenda**: delega lembretes e notificaÃƒÂ§ÃƒÂµes de status, **mantÃƒÂ©m** botÃƒÂµes wa.me
- **Portal**: delega mensagens de clientes para admin
- **Finance**: pode usar API para notificar pagamentos

**Introduzido em**: v0.1.0  
**Refatorado em**: v0.2.0 (API centralizada)

**DocumentaÃƒÂ§ÃƒÂ£o completa**: `plugins/desi-pet-shower-communications/README.md`

---

### Groomers (`desi-pet-shower-groomers_addon`)

**DiretÃƒÂ³rio**: `plugins/desi-pet-shower-groomers`

**PropÃƒÂ³sito e funcionalidades principais**:
- Cadastrar e gerenciar profissionais (groomers) via role customizada
- Vincular mÃƒÂºltiplos groomers por atendimento
- Gerar relatÃƒÂ³rios de produtividade por profissional com mÃƒÂ©tricas visuais
- Exibir cards de mÃƒÂ©tricas: total de atendimentos, receita total, ticket mÃƒÂ©dio
- IntegraÃƒÂ§ÃƒÂ£o com Finance API para cÃƒÂ¡lculo de receitas

**Shortcodes expostos**: Nenhum

**CPTs, tabelas e opÃƒÂ§ÃƒÂµes**:
- Role customizada: `dps_groomer` (profissional de banho e tosa)
- Metadados: `_dps_groomers` (array de IDs de groomers por agendamento)

**Hooks consumidos**:
- `dps_base_appointment_assignment_fields`: adiciona campo de seleÃƒÂ§ÃƒÂ£o mÃƒÂºltipla de groomers na seÃƒÂ§ÃƒÂ£o "AtribuiÃƒÂ§ÃƒÂ£o" (desde v1.8.0)
- `dps_base_after_save_appointment`: salva groomers selecionados
- `dps_base_nav_tabs_after_history`: adiciona aba "Groomers" (prioridade 15)
- `dps_base_sections_after_history`: renderiza cadastro e relatÃƒÂ³rios (prioridade 15)
- `wp_enqueue_scripts`: carrega CSS e JS no frontend
- `admin_enqueue_scripts`: carrega CSS e JS no admin

**Hooks disparados**: Nenhum

**DependÃƒÂªncias**:
- Depende do plugin base para estrutura de navegaÃƒÂ§ÃƒÂ£o e agendamentos
- **Opcional**: Finance Add-on para cÃƒÂ¡lculo automÃƒÂ¡tico de receitas nos relatÃƒÂ³rios

**Introduzido em**: v0.1.0 (estimado)

**VersÃƒÂ£o atual**: v1.1.0

**Assets**:
- `assets/css/groomers-admin.css`: estilos seguindo padrÃƒÂ£o visual minimalista DPS
- `assets/js/groomers-admin.js`: validaÃƒÂ§ÃƒÂµes e interatividade do formulÃƒÂ¡rio

**ObservaÃƒÂ§ÃƒÂµes**:
- v1.1.0: Refatorado com assets externos, fieldsets no formulÃƒÂ¡rio e cards de mÃƒÂ©tricas
- FormulÃƒÂ¡rio de cadastro com fieldsets: Dados de Acesso e InformaÃƒÂ§ÃƒÂµes Pessoais
- RelatÃƒÂ³rios exibem detalhes de cliente e pet por atendimento
- IntegraÃƒÂ§ÃƒÂ£o inteligente com Finance API (fallback para SQL direto)
- Consulte `docs/analysis/GROOMERS_ADDON_ANALYSIS.md` para anÃƒÂ¡lise detalhada e plano de melhorias

---

### Portal do Cliente (`desi-pet-shower-client-portal_addon`)

**DiretÃƒÂ³rio**: `plugins/desi-pet-shower-client-portal`

**PropÃƒÂ³sito e funcionalidades principais**:
- Fornecer ÃƒÂ¡rea autenticada para clientes
- Permitir atualizaÃƒÂ§ÃƒÂ£o de dados pessoais e de pets
- Exibir histÃƒÂ³rico de atendimentos e pendÃƒÂªncias financeiras
- Integrar com mÃƒÂ³dulo "Indique e Ganhe" quando ativo
- Sistema de autenticaÃƒÂ§ÃƒÂ£o via tokens (magic links) sem necessidade de senhas
- Link de atualizaÃƒÂ§ÃƒÂ£o de perfil para clientes atualizarem seus dados sem login
- Coleta de consentimento de tosa com mÃƒÂ¡quina via link tokenizado
- Aba de pagamentos com resumo financeiro, pendÃƒÂªncias e histÃƒÂ³rico de parcelas (Fase 5.5)
- Galeria multi-fotos por pet com lightbox (Fase 5.1)
- PreferÃƒÂªncias de notificaÃƒÂ§ÃƒÂ£o configurÃƒÂ¡veis pelo cliente (Fase 5.2)
- Seletor de pet no modal de agendamento para clientes com mÃƒÂºltiplos pets (Fase 5.3)
- Barra de progresso stepper (3 etapas) no fluxo de agendamento (Fase 4.1)
- SugestÃƒÂµes inteligentes de agendamento baseadas no histÃƒÂ³rico do pet (Fase 8.1)
- AutenticaÃƒÂ§ÃƒÂ£o de dois fatores (2FA) via e-mail, opcional (Fase 6.4)
- Remember-me com cookie permanente (Fase 4.6)

**Shortcodes expostos**:
- `[dps_client_portal]`: renderiza portal completo do cliente
- `[dps_client_login]`: exibe formulÃƒÂ¡rio de login
- `[dps_profile_update]`: formulÃƒÂ¡rio pÃƒÂºblico de atualizaÃƒÂ§ÃƒÂ£o de perfil (usado internamente via token)
- `[dps_tosa_consent]`: formulÃƒÂ¡rio pÃƒÂºblico de consentimento de tosa com mÃƒÂ¡quina (via token)

**CPTs, tabelas e opÃƒÂ§ÃƒÂµes**:
- NÃƒÂ£o cria CPTs prÃƒÂ³prios
- Tabela customizada `wp_dps_portal_tokens` para gerenciar tokens de acesso
  - Suporta 5 tipos de token: `login` (temporÃƒÂ¡rio 30min), `first_access` (temporÃƒÂ¡rio 30min), `permanent` (vÃƒÂ¡lido atÃƒÂ© revogaÃƒÂ§ÃƒÂ£o), `profile_update` (7 dias), `tosa_consent` (7 dias)
- SessÃƒÂµes PHP prÃƒÂ³prias para autenticaÃƒÂ§ÃƒÂ£o independente do WordPress
- Option `dps_portal_page_id`: armazena ID da pÃƒÂ¡gina configurada do portal
- Option `dps_portal_2fa_enabled`: habilita/desabilita 2FA via e-mail (padrÃƒÂ£o: desabilitado)
- Tipos de mensagem customizados para notificaÃƒÂ§ÃƒÂµes

**Abas do portal**:
- `inicio`: dashboard com resumo (agendamentos, pets, status financeiro)
- `agendamentos`: histÃƒÂ³rico de atendimentos com filtro por perÃƒÂ­odo
- `pagamentos`: resumo financeiro, transaÃƒÂ§ÃƒÂµes pendentes com parcelas, histÃƒÂ³rico de pagos (Fase 5.5)
- `pet-history`: timeline de atendimentos por pet com info card detalhado
- `galeria`: galeria multi-fotos por pet com lightbox (Fase 5.1)
- `fidelidade`: programa de indicaÃƒÂ§ÃƒÂ£o e recompensas
- `reviews`: avaliaÃƒÂ§ÃƒÂµes pÃƒÂ³s-serviÃƒÂ§o
- `mensagens`: comunicaÃƒÂ§ÃƒÂ£o com o pet shop
- `dados`: dados pessoais, pets e preferÃƒÂªncias de notificaÃƒÂ§ÃƒÂ£o
- Hook `dps_portal_tabs` (filter): permite add-ons adicionarem abas customizadas

**Menus administrativos**:
- **Portal do Cliente** (`dps-client-portal-settings`): configuraÃƒÂ§ÃƒÂµes gerais do portal, toggle 2FA
- **Logins de Clientes** (`dps-client-logins`): gerenciamento de tokens de acesso
  - Interface para gerar tokens temporÃƒÂ¡rios ou permanentes
  - RevogaÃƒÂ§ÃƒÂ£o manual de tokens ativos
  - Envio de links por WhatsApp ou e-mail
  - HistÃƒÂ³rico de acessos por cliente

**Classes principais**:

| Classe | Arquivo | PropÃƒÂ³sito |
|--------|---------|-----------|
| `DPS_Client_Portal` | `includes/class-dps-client-portal.php` | Classe principal: shortcode, auth flow, tabs, localize_script |
| `DPS_Portal_2FA` | `includes/class-dps-portal-2fa.php` | 2FA via e-mail: gera/verifica cÃƒÂ³digos, renderiza form, AJAX handler |
| `DPS_Scheduling_Suggestions` | `includes/class-dps-scheduling-suggestions.php` | SugestÃƒÂµes de agendamento baseadas no histÃƒÂ³rico do pet |
| `DPS_Portal_Renderer` | `includes/client-portal/class-dps-portal-renderer.php` | RenderizaÃƒÂ§ÃƒÂ£o das abas e componentes visuais |
| `DPS_Portal_Actions_Handler` | `includes/client-portal/class-dps-portal-actions-handler.php` | Handlers de aÃƒÂ§ÃƒÂµes POST (save, update, upload) |
| `DPS_Portal_Ajax_Handler` | `includes/client-portal/class-dps-portal-ajax-handler.php` | Handlers de requisiÃƒÂ§ÃƒÂµes AJAX |
| `DPS_Portal_Session_Manager` | `includes/class-dps-portal-session-manager.php` | Gerenciamento de sessÃƒÂµes PHP |
| `DPS_Portal_Token_Manager` | `includes/class-dps-portal-token-manager.php` | CRUD de tokens com suporte a permanentes e temporÃƒÂ¡rios |
| `DPS_Finance_Repository` | `includes/client-portal/repositories/class-dps-finance-repository.php` | Acesso a dados financeiros (transaÃƒÂ§ÃƒÂµes, parcelas, resumos) |
| `DPS_Pet_Repository` | `includes/client-portal/repositories/class-dps-pet-repository.php` | Acesso a dados de pets do cliente |
| `DPS_Appointment_Repository` | `includes/client-portal/repositories/class-dps-appointment-repository.php` | Acesso a dados de agendamentos do cliente |

**Hooks consumidos**:
- ~~`dps_settings_nav_tabs`~~: (migrado para sistema moderno) abas "Portal do Cliente" e "Logins de Clientes" registradas em `DPS_Settings_Frontend`
- ~~`dps_settings_sections`~~: (migrado para sistema moderno) renderizaÃƒÂ§ÃƒÂ£o via callbacks em `register_tab()`
- Hooks do add-on de Pagamentos para links de quitaÃƒÂ§ÃƒÂ£o via Mercado Pago
- `dps_client_page_header_actions`: adiciona botÃƒÂ£o "Link de AtualizaÃƒÂ§ÃƒÂ£o" no header da pÃƒÂ¡gina de detalhes do cliente

**Hooks disparados**:
- `dps_client_portal_before_content`: disparado apÃƒÂ³s o menu de navegaÃƒÂ§ÃƒÂ£o e antes das seÃƒÂ§ÃƒÂµes de conteÃƒÂºdo; passa $client_id como parÃƒÂ¢metro; ÃƒÂºtil para adicionar conteÃƒÂºdo no topo do portal (ex: widgets, assistentes)
- `dps_client_portal_after_content`: disparado ao final do portal, antes do fechamento do container principal; passa $client_id como parÃƒÂ¢metro
- `dps_portal_tabs` (filter): filtra o array de abas do portal; passa $tabs e $client_id
- `dps_portal_before_{tab}_content` / `dps_portal_after_{tab}_content` (action): disparados antes/depois do conteÃƒÂºdo de cada aba (inicio, agendamentos, pagamentos, pet-history, galeria, fidelidade, reviews, mensagens, dados); passa $client_id
- `dps_portal_custom_tab_panels` (action): renderiza painÃƒÂ©is de abas customizadas; passa $client_id e $tabs
- `dps_portal_profile_update_link_generated`: disparado quando um link de atualizaÃƒÂ§ÃƒÂ£o de perfil ÃƒÂ© gerado; passa $client_id e $update_url como parÃƒÂ¢metros
- `dps_portal_profile_updated`: disparado quando o cliente atualiza seu perfil; passa $client_id como parÃƒÂ¢metro
- `dps_portal_new_pet_created`: disparado quando um novo pet ÃƒÂ© cadastrado via formulÃƒÂ¡rio de atualizaÃƒÂ§ÃƒÂ£o; passa $pet_id e $client_id como parÃƒÂ¢metros
- `dps_portal_tosa_consent_link_generated`: disparado ao gerar link de consentimento; passa $client_id e $consent_url
- `dps_portal_tosa_consent_saved`: disparado ao salvar consentimento; passa $client_id
- `dps_portal_tosa_consent_revoked`: disparado ao revogar consentimento; passa $client_id
- `dps_portal_after_update_preferences` (action): disparado apÃƒÂ³s salvar preferÃƒÂªncias de notificaÃƒÂ§ÃƒÂ£o; passa $client_id
- `dps_portal_before_render` / `dps_portal_after_auth_check` / `dps_portal_client_authenticated` (actions): hooks do ciclo de vida do shortcode
- `dps_portal_access_notification_sent` (action): disparado apÃƒÂ³s enviar notificaÃƒÂ§ÃƒÂ£o de acesso; passa $client_id, $sent, $access_date, $ip_address
- `dps_portal_review_url` (filter): permite filtrar a URL de avaliaÃƒÂ§ÃƒÂ£o do Google

**MÃƒÂ©todos pÃƒÂºblicos da classe `DPS_Client_Portal`**:
- `get_current_client_id()`: retorna o ID do cliente autenticado via sessÃƒÂ£o ou usuÃƒÂ¡rio WordPress (0 se nÃƒÂ£o autenticado); permite que add-ons obtenham o cliente logado no portal

**FunÃƒÂ§ÃƒÂµes helper globais**:
- `dps_get_portal_page_url()`: retorna URL da pÃƒÂ¡gina do portal (configurada ou fallback)
- `dps_get_portal_page_id()`: retorna ID da pÃƒÂ¡gina do portal (configurada ou fallback)
- `dps_get_tosa_consent_page_url()`: retorna URL da pÃƒÂ¡gina de consentimento (configurada ou fallback)

**Metadados de cliente utilizados** (meta keys em `dps_cliente` CPT):
- `client_notification_reminders` (default '1'): preferÃƒÂªncia de lembretes de agendamento
- `client_notification_payments` (default '1'): preferÃƒÂªncia de notificaÃƒÂ§ÃƒÂµes financeiras
- `client_notification_promotions` (default '0'): preferÃƒÂªncia de promoÃƒÂ§ÃƒÂµes
- `client_notification_updates` (default ''): preferÃƒÂªncia de atualizaÃƒÂ§ÃƒÂµes do sistema

**DependÃƒÂªncias**:
- Depende do plugin base para CPTs de clientes e pets
- Integra-se com add-on Financeiro para exibir pendÃƒÂªncias e parcelas (aba Pagamentos)
- Integra-se com add-on de Fidelidade para exibir cÃƒÂ³digo de indicaÃƒÂ§ÃƒÂ£o

**Introduzido em**: v0.1.0 (estimado)
**VersÃƒÂ£o atual**: v2.1.0

**ObservaÃƒÂ§ÃƒÂµes**:
- JÃƒÂ¡ segue padrÃƒÂ£o modular com estrutura `includes/` e `assets/`
- Sistema de tokens com suporte a temporÃƒÂ¡rios (30min) e permanentes (atÃƒÂ© revogaÃƒÂ§ÃƒÂ£o)
- Cleanup automÃƒÂ¡tico de tokens expirados via cron job hourly
- ConfiguraÃƒÂ§ÃƒÂ£o centralizada da pÃƒÂ¡gina do portal via interface administrativa
- Menu administrativo registrado sob `desi-pet-shower` desde v2.1.0
- 2FA opcional via e-mail (cÃƒÂ³digos hashed com `wp_hash_password`, 10min expiraÃƒÂ§ÃƒÂ£o, 5 tentativas max)
- Remember-me: cookie permanente (HttpOnly, Secure, SameSite=Strict, 90 dias)
- SugestÃƒÂµes inteligentes: anÃƒÂ¡lise de atÃƒÂ© 20 atendimentos por pet (intervalo mÃƒÂ©dio, top 3 serviÃƒÂ§os, urgÃƒÂªncia)

**AnÃƒÂ¡lise de Layout e UX**:
- Consulte `docs/analysis/CLIENT_PORTAL_ADDON_DEEP_ANALYSIS.md` para anÃƒÂ¡lise detalhada de usabilidade e arquitetura do portal
- Consulte `docs/screenshots/CLIENT_PORTAL_REBRANDING_SCREENSHOTS.md` para registro visual e resumo executivo das melhorias aplicadas
- Portal usa design M3 com tabs, cards, lightbox, progress bar stepper, formulÃƒÂ¡rios com validaÃƒÂ§ÃƒÂ£o real-time
- Responsividade em 480px, 768px e 1024px; suporte a `prefers-reduced-motion`

---

### Assistente de IA (`desi-pet-shower-ai_addon`)

**DiretÃƒÂ³rio**: `plugins/desi-pet-shower-ai`

**PropÃƒÂ³sito e funcionalidades principais**:
- Fornecer assistente virtual inteligente no Portal do Cliente
- Responder perguntas EXCLUSIVAMENTE sobre: Banho e Tosa, serviÃƒÂ§os, agendamentos, histÃƒÂ³rico, pagamentos, fidelidade, assinaturas e dados do cliente/pet
- NÃƒÆ’O responder sobre assuntos aleatÃƒÂ³rios fora do contexto (polÃƒÂ­tica, religiÃƒÂ£o, tecnologia, etc.)
- Integrar-se com OpenAI via API Chat Completions (GPT-3.5 Turbo / GPT-4)

**Shortcodes expostos**: Nenhum (integra-se diretamente ao Portal via hook)

**CPTs, tabelas e opÃƒÂ§ÃƒÂµes**:
- NÃƒÂ£o cria CPTs ou tabelas prÃƒÂ³prias
- Option: `dps_ai_settings` (armazena configuraÃƒÂ§ÃƒÂµes: enabled, api_key, model, temperature, timeout, max_tokens)

**Hooks consumidos**:
- `dps_client_portal_before_content`: renderiza widget de chat no topo do portal (apÃƒÂ³s navegaÃƒÂ§ÃƒÂ£o, antes das seÃƒÂ§ÃƒÂµes)

**Hooks disparados**: Nenhum

**Endpoints AJAX**:
- `dps_ai_portal_ask` (wp_ajax e wp_ajax_nopriv): processa perguntas do cliente e retorna respostas da IA

**DependÃƒÂªncias**:
- **ObrigatÃƒÂ³rio**: Client Portal (fornece autenticaÃƒÂ§ÃƒÂ£o e shortcode `[dps_client_portal]`)
- **Opcional**: Finance, Loyalty, Services (enriquecem contexto disponÃƒÂ­vel para a IA)

**Introduzido em**: v1.0.0

**Assets**:
- `assets/js/dps-ai-portal.js`: gerencia widget de chat e envio de perguntas via AJAX
- `assets/css/dps-ai-portal.css`: estilos minimalistas seguindo paleta visual DPS

**Arquitetura interna**:
- `includes/class-dps-ai-client.php`: cliente da API OpenAI com tratamento de erros e timeouts
- `includes/class-dps-ai-assistant.php`: lÃƒÂ³gica do assistente (system prompt restritivo, montagem de contexto, filtro de palavras-chave)
- `includes/class-dps-ai-integration-portal.php`: integraÃƒÂ§ÃƒÂ£o com Portal do Cliente (widget, AJAX handlers)

**System Prompt e Regras**:
- Prompt restritivo define domÃƒÂ­nio permitido (banho/tosa, pet shop, sistema DPS)
- ProÃƒÂ­be explicitamente assuntos fora do contexto
- Instrui a IA a recusar educadamente perguntas inadequadas
- Recomenda procurar veterinÃƒÂ¡rio para problemas de saÃƒÂºde graves do pet
- ProÃƒÂ­be inventar descontos, promoÃƒÂ§ÃƒÂµes ou alteraÃƒÂ§ÃƒÂµes de plano nÃƒÂ£o documentadas
- Exige honestidade quando dados nÃƒÂ£o forem encontrados no sistema

**Filtro Preventivo**:
- Antes de chamar API, valida se pergunta contÃƒÂ©m palavras-chave do contexto (pet, banho, tosa, agendamento, pagamento, etc.)
- Economiza chamadas de API e protege contra perguntas totalmente fora de escopo
- Resposta padrÃƒÂ£o retornada sem chamar API se pergunta nÃƒÂ£o passar no filtro

**Contexto Fornecido ÃƒÂ  IA**:
- Dados do cliente (nome, telefone, email)
- Lista de pets cadastrados (nome, raÃƒÂ§a, porte, idade)
- ÃƒÅ¡ltimos 5 agendamentos (data, status, serviÃƒÂ§os)
- PendÃƒÂªncias financeiras (se Finance add-on ativo)
- Pontos de fidelidade (se Loyalty add-on ativo)

**Comportamento em CenÃƒÂ¡rios**:
- **IA ativa e funcionando**: Widget aparece e processa perguntas normalmente
- **IA desabilitada ou sem API key**: Widget nÃƒÂ£o aparece; Portal funciona normalmente
- **Falha na API**: Mensagem amigÃƒÂ¡vel exibida; Portal continua funcional

**SeguranÃƒÂ§a**:
- API key NUNCA exposta no JavaScript (chamadas server-side only)
- Nonces em todas as requisiÃƒÂ§ÃƒÂµes AJAX
- SanitizaÃƒÂ§ÃƒÂ£o de entrada do usuÃƒÂ¡rio
- ValidaÃƒÂ§ÃƒÂ£o de cliente logado antes de processar pergunta
- Timeout configurÃƒÂ¡vel para evitar requisiÃƒÂ§ÃƒÂµes travadas
- Logs de erro apenas no server (error_log, nÃƒÂ£o expostos ao cliente)

**Interface Administrativa**:
- Menu: **desi.pet by PRObst > Assistente de IA**
- ConfiguraÃƒÂ§ÃƒÂµes: ativar/desativar IA, API key, modelo GPT, temperatura, timeout, max_tokens
- DocumentaÃƒÂ§ÃƒÂ£o inline sobre comportamento do assistente

**ObservaÃƒÂ§ÃƒÂµes**:
- Sistema totalmente autocontido: falhas nÃƒÂ£o afetam funcionamento do Portal
- Custo por requisiÃƒÂ§ÃƒÂ£o varia conforme modelo escolhido (GPT-3.5 Turbo recomendado para custo/benefÃƒÂ­cio)
- Consulte `plugins/desi-pet-shower-ai/README.md` para documentaÃƒÂ§ÃƒÂ£o completa de uso e manutenÃƒÂ§ÃƒÂ£o

---

### Financeiro (`desi-pet-shower-finance_addon`)

**DiretÃƒÂ³rio**: `plugins/desi-pet-shower-finance`

**PropÃƒÂ³sito e funcionalidades principais**:
- Gerenciar transaÃƒÂ§ÃƒÂµes financeiras e cobranÃƒÂ§as
- Sincronizar lanÃƒÂ§amentos com agendamentos
- Suportar quitaÃƒÂ§ÃƒÂ£o parcial e geraÃƒÂ§ÃƒÂ£o de documentos
- Integrar com outros add-ons para bonificaÃƒÂ§ÃƒÂµes e assinaturas

**Shortcodes expostos**: Sim (nÃƒÂ£o especificados na documentaÃƒÂ§ÃƒÂ£o atual)

**CPTs, tabelas e opÃƒÂ§ÃƒÂµes**:
- Tabela: `dps_transacoes` (lanÃƒÂ§amentos financeiros)
- Tabela: `dps_parcelas` (parcelas de cobranÃƒÂ§as)

**Hooks consumidos**:
- `dps_finance_cleanup_for_appointment`: remove lanÃƒÂ§amentos ao excluir agendamento
- `dps_base_nav_tabs_*`: adiciona aba "Financeiro"
- `dps_base_sections_*`: renderiza seÃƒÂ§ÃƒÂ£o financeira

**Hooks disparados**:
- `dps_finance_booking_paid`: disparado quando cobranÃƒÂ§a ÃƒÂ© marcada como paga

**DependÃƒÂªncias**:
- Depende do plugin base para estrutura de navegaÃƒÂ§ÃƒÂ£o
- Fornece infraestrutura para add-ons de Pagamentos, Assinaturas e Fidelidade

**Introduzido em**: v0.1.0

**ObservaÃƒÂ§ÃƒÂµes**:
- JÃƒÂ¡ segue padrÃƒÂ£o modular com classes auxiliares em `includes/`
- Tabela compartilhada por mÃƒÂºltiplos add-ons; mudanÃƒÂ§as de schema requerem migraÃƒÂ§ÃƒÂ£o cuidadosa

---

### Pagamentos (`desi-pet-shower-payment_addon`)

**DiretÃƒÂ³rio**: `plugins/desi-pet-shower-payment`

**PropÃƒÂ³sito e funcionalidades principais**:
- Integrar com Mercado Pago para geraÃƒÂ§ÃƒÂ£o de links de pagamento
- Processar notificaÃƒÂ§ÃƒÂµes de webhook para atualizaÃƒÂ§ÃƒÂ£o de status
- Injetar mensagens de cobranÃƒÂ§a no WhatsApp via add-on de Agenda
- Gerenciar credenciais do Mercado Pago de forma segura

**Shortcodes expostos**: Nenhum

**Classes principais**:
- `DPS_Payment_Addon`: Classe principal do add-on (gerencia hooks e integraÃƒÂ§ÃƒÂ£o)
- `DPS_MercadoPago_Config` (v1.1.0+): Gerencia credenciais do Mercado Pago com ordem de prioridade

**CPTs, tabelas e opÃƒÂ§ÃƒÂµes**:
- Options: `dps_mercadopago_access_token`, `dps_pix_key`, `dps_mercadopago_webhook_secret` (credenciais Mercado Pago)
- **IMPORTANTE (v1.1.0+)**: Recomendado definir credenciais via constantes em `wp-config.php` para produÃƒÂ§ÃƒÂ£o:
  - `DPS_MERCADOPAGO_ACCESS_TOKEN`: Token de acesso da API Mercado Pago
  - `DPS_MERCADOPAGO_WEBHOOK_SECRET`: Secret para validaÃƒÂ§ÃƒÂ£o de webhooks
  - `DPS_MERCADOPAGO_PUBLIC_KEY`: Chave pÃƒÂºblica (opcional)
- Ordem de prioridade: constantes wp-config.php Ã¢â€ â€™ options em banco de dados
- Metadados em agendamentos (v1.1.0+):
  - `_dps_payment_link_status`: Status da geraÃƒÂ§ÃƒÂ£o do link ('success' | 'error' | 'not_requested')
  - `_dps_payment_last_error`: Detalhes do ÃƒÂºltimo erro (array: code, message, timestamp, context)

**Hooks consumidos**:
- `dps_base_after_save_appointment`: Gera link de pagamento quando agendamento ÃƒÂ© finalizado
- `dps_agenda_whatsapp_message`: Injeta link de pagamento na mensagem de cobranÃƒÂ§a
- `init` (prioridade 1): Processa webhooks cedo no ciclo de inicializaÃƒÂ§ÃƒÂ£o do WordPress

**Hooks disparados**: Nenhum

**DependÃƒÂªncias**:
- Depende do add-on Financeiro para criar transaÃƒÂ§ÃƒÂµes
- Integra-se com add-on de Agenda para envio de links via WhatsApp

**Introduzido em**: v0.1.0 (estimado)

**VersÃƒÂ£o atual**: v1.1.0

**MudanÃƒÂ§as na v1.1.0**:
- Classe `DPS_MercadoPago_Config` para gerenciamento seguro de credenciais
- Suporte para constantes em wp-config.php (recomendado para produÃƒÂ§ÃƒÂ£o)
- Tratamento de erros aprimorado com logging detalhado
- Flags de status em agendamentos para rastreamento de falhas
- Interface administrativa mostra campos readonly quando credenciais vÃƒÂªm de constantes
- ValidaÃƒÂ§ÃƒÂ£o completa de respostas da API Mercado Pago

**MÃƒÂ©todos principais**:
- `DPS_MercadoPago_Config::get_access_token()`: Retorna access token (constante ou option)
- `DPS_MercadoPago_Config::get_webhook_secret()`: Retorna webhook secret (constante ou option)
- `DPS_MercadoPago_Config::is_*_from_constant()`: Verifica se credencial vem de constante
- `DPS_MercadoPago_Config::get_masked_credential()`: Retorna ÃƒÂºltimos 4 caracteres para exibiÃƒÂ§ÃƒÂ£o
- `DPS_Payment_Addon::create_payment_preference()`: Cria preferÃƒÂªncia de pagamento via API MP
- `DPS_Payment_Addon::log_payment_error()`: Logging centralizado de erros de cobranÃƒÂ§a
- `DPS_Payment_Addon::process_payment_notification()`: Processa notificaÃƒÂ§ÃƒÂµes de webhook
- `DPS_Payment_Addon::maybe_generate_payment_link()`: Gera link automaticamente para finalizados

**ObservaÃƒÂ§ÃƒÂµes**:
- ValidaÃƒÂ§ÃƒÂ£o de webhook aplicada apenas quando requisiÃƒÂ§ÃƒÂ£o traz indicadores de notificaÃƒÂ§ÃƒÂ£o do MP
- Requer token de acesso e chave PIX configurados (via constantes ou options)
- **IMPORTANTE**: ConfiguraÃƒÂ§ÃƒÂ£o do webhook secret ÃƒÂ© obrigatÃƒÂ³ria para processamento automÃƒÂ¡tico de pagamentos. Veja documentaÃƒÂ§ÃƒÂ£o completa em `plugins/desi-pet-shower-payment/WEBHOOK_CONFIGURATION.md`
- **SEGURANÃƒâ€¡A**: Em produÃƒÂ§ÃƒÂ£o, sempre defina credenciais via constantes em wp-config.php para evitar armazenamento em texto plano no banco de dados
- Logs de erro incluem contexto completo para debugging (HTTP code, response body, timestamps)
- Flags de status permitem rastreamento e retry de falhas na geraÃƒÂ§ÃƒÂ£o de links

---

### Push Notifications (`desi-pet-shower-push_addon`)

**DiretÃƒÂ³rio**: `plugins/desi-pet-shower-push`

**PropÃƒÂ³sito e funcionalidades principais**:
- Enviar resumo diÃƒÂ¡rio de agendamentos para equipe administrativa
- Enviar relatÃƒÂ³rio financeiro diÃƒÂ¡rio com atendimentos e transaÃƒÂ§ÃƒÂµes
- Enviar relatÃƒÂ³rio semanal de pets inativos (sem atendimento hÃƒÂ¡ 30 dias)
- Integrar com e-mail (via `wp_mail()`) e Telegram Bot API
- HorÃƒÂ¡rios e dias configurÃƒÂ¡veis para cada tipo de notificaÃƒÂ§ÃƒÂ£o

**Shortcodes expostos**: Nenhum

**CPTs, tabelas e opÃƒÂ§ÃƒÂµes**:

| Option | Tipo | DescriÃƒÂ§ÃƒÂ£o |
|--------|------|-----------|
| `dps_push_emails_agenda` | array | Lista de emails para agenda diÃƒÂ¡ria |
| `dps_push_emails_report` | array | Lista de emails para relatÃƒÂ³rio financeiro |
| `dps_push_agenda_time` | string | HorÃƒÂ¡rio do resumo de agendamentos (HH:MM) |
| `dps_push_report_time` | string | HorÃƒÂ¡rio do relatÃƒÂ³rio financeiro (HH:MM) |
| `dps_push_weekly_day` | string | Dia da semana para relatÃƒÂ³rio semanal (english) |
| `dps_push_weekly_time` | string | HorÃƒÂ¡rio do relatÃƒÂ³rio semanal (HH:MM) |
| `dps_push_inactive_days` | int | Dias de inatividade para considerar pet inativo (padrÃƒÂ£o: 30) |
| `dps_push_agenda_enabled` | bool | Ativar/desativar agenda diÃƒÂ¡ria |
| `dps_push_report_enabled` | bool | Ativar/desativar relatÃƒÂ³rio financeiro |
| `dps_push_weekly_enabled` | bool | Ativar/desativar relatÃƒÂ³rio semanal |
| `dps_push_telegram_token` | string | Token do bot do Telegram |
| `dps_push_telegram_chat` | string | ID do chat/grupo Telegram |

**Menus administrativos**:
- **NotificaÃƒÂ§ÃƒÂµes** (`dps-push-notifications`): configuraÃƒÂ§ÃƒÂµes de destinatÃƒÂ¡rios, horÃƒÂ¡rios e integraÃƒÂ§ÃƒÂ£o Telegram

**Hooks consumidos**:
- Nenhum hook do sistema de configuraÃƒÂ§ÃƒÂµes (usa menu admin prÃƒÂ³prio)

**Hooks disparados**:

| Hook | Tipo | ParÃƒÂ¢metros | DescriÃƒÂ§ÃƒÂ£o |
|------|------|------------|-----------|
| `dps_send_agenda_notification` | cron | - | Dispara envio da agenda diÃƒÂ¡ria |
| `dps_send_daily_report` | cron | - | Dispara envio do relatÃƒÂ³rio financeiro |
| `dps_send_weekly_inactive_report` | cron | - | Dispara envio do relatÃƒÂ³rio de pets inativos |
| `dps_send_push_notification` | action | `$message`, `$context` | Permite add-ons enviarem notificaÃƒÂ§ÃƒÂµes via Telegram |
| `dps_push_notification_content` | filter | `$content`, `$appointments` | Filtra conteÃƒÂºdo do email antes de enviar |
| `dps_push_notification_recipients` | filter | `$recipients` | Filtra destinatÃƒÂ¡rios da agenda diÃƒÂ¡ria |
| `dps_daily_report_recipients` | filter | `$recipients` | Filtra destinatÃƒÂ¡rios do relatÃƒÂ³rio financeiro |
| `dps_daily_report_content` | filter | `$content`, `$appointments`, `$trans` | Filtra conteÃƒÂºdo do relatÃƒÂ³rio |
| `dps_daily_report_html` | filter | `$html`, `$appointments`, `$trans` | Filtra HTML do relatÃƒÂ³rio |
| `dps_weekly_inactive_report_recipients` | filter | `$recipients` | Filtra destinatÃƒÂ¡rios do relatÃƒÂ³rio semanal |

**DependÃƒÂªncias**:
- **ObrigatÃƒÂ³ria**: Plugin base DPS (verifica `DPS_Base_Plugin`)
- **Opcional**: Finance Add-on (para relatÃƒÂ³rio financeiro com tabela `dps_transacoes`)

**Introduzido em**: v0.1.0 (estimado)

**VersÃƒÂ£o atual**: 1.2.0

**ObservaÃƒÂ§ÃƒÂµes**:
- Implementa `register_deactivation_hook` corretamente para limpar cron jobs
- Usa timezone do WordPress para agendamentos (`get_option('timezone_string')`)
- Emails enviados em formato HTML com headers `Content-Type: text/html; charset=UTF-8`
- IntegraÃƒÂ§ÃƒÂ£o Telegram envia mensagens em texto plano com `parse_mode` HTML
- Threshold de inatividade configurÃƒÂ¡vel via interface admin (padrÃƒÂ£o: 30 dias)
- Interface administrativa integrada na pÃƒÂ¡gina de NotificaÃƒÂ§ÃƒÂµes sob menu desi.pet by PRObst
- **v1.2.0**: Menu admin visÃƒÂ­vel, botÃƒÂµes de teste para relatÃƒÂ³rios e Telegram, uninstall.php atualizado

**AnÃƒÂ¡lise completa**: Consulte `docs/analysis/PUSH_ADDON_ANALYSIS.md` para anÃƒÂ¡lise detalhada de cÃƒÂ³digo, funcionalidades e melhorias propostas

---

### Cadastro PÃƒÂºblico (`desi-pet-shower-registration_addon`)

**DiretÃƒÂ³rio**: `plugins/desi-pet-shower-registration`

**PropÃƒÂ³sito e funcionalidades principais**:
- Permitir cadastro pÃƒÂºblico de clientes e pets via formulÃƒÂ¡rio web
- Integrar com Google Maps para autocomplete de endereÃƒÂ§os
- Disparar hook para outros add-ons apÃƒÂ³s criaÃƒÂ§ÃƒÂ£o de cliente

**Shortcodes expostos**:
- `[dps_registration_form]`: renderiza formulÃƒÂ¡rio de cadastro pÃƒÂºblico

**CPTs, tabelas e opÃƒÂ§ÃƒÂµes**:
- NÃƒÂ£o cria CPTs prÃƒÂ³prios; cria posts do tipo `dps_client` e `dps_pet`
- Options: `dps_google_maps_api_key` (chave de API do Google Maps)

**Hooks consumidos**: Nenhum

**Hooks disparados**:
- `dps_registration_after_client_created`: disparado apÃƒÂ³s criar novo cliente

**DependÃƒÂªncias**:
- Depende do plugin base para CPTs de cliente e pet
- Integra-se com add-on de Fidelidade para capturar cÃƒÂ³digos de indicaÃƒÂ§ÃƒÂ£o

**Introduzido em**: v0.1.0 (estimado)

**ObservaÃƒÂ§ÃƒÂµes**:
- Sanitiza todas as entradas antes de criar posts
- Arquivo ÃƒÂºnico de 636 linhas; candidato a refatoraÃƒÂ§ÃƒÂ£o futura

---

### Frontend (`desi-pet-shower-frontend`)

**DiretÃƒÂ³rio**: `plugins/desi-pet-shower-frontend`

**PropÃƒÂ³sito e funcionalidades principais**:
- Consolidar experiÃƒÂªncias frontend (cadastro, agendamento, configuraÃƒÂ§ÃƒÂµes) em add-on modular
- Arquitetura com mÃƒÂ³dulos independentes, feature flags e camada de compatibilidade
- Rollout controlado: cada mÃƒÂ³dulo pode ser habilitado/desabilitado individualmente
- **[Fase 2]** MÃƒÂ³dulo Registration operacional em dual-run com o add-on legado
- **[Fase 3]** MÃƒÂ³dulo Booking operacional em dual-run com o add-on legado
- **[Fase 4]** MÃƒÂ³dulo Settings integrado ao sistema de abas de configuraÃƒÂ§ÃƒÂµes
- **[Fase 7.1]** PreparaÃƒÂ§ÃƒÂ£o: abstracts, template engine, hook bridges, componentes M3, flags v2
- **[Fase 7.2]** Registration V2: formulÃƒÂ¡rio nativo 100% independente do legado (cadastro + pets + reCAPTCHA + email confirmation)
- **[Fase 7.3]** Booking V2: wizard nativo 5-step 100% independente do legado (cliente Ã¢â€ â€™ pets Ã¢â€ â€™ serviÃƒÂ§os Ã¢â€ â€™ data/hora Ã¢â€ â€™ confirmaÃƒÂ§ÃƒÂ£o + extras TaxiDog/Tosa)

**Shortcodes expostos**:
- `dps_registration_form` Ã¢â‚¬â€ quando flag `registration` ativada, o mÃƒÂ³dulo assume o shortcode (wrapper sobre o legado com surface M3)
- `dps_booking_form` Ã¢â‚¬â€ quando flag `booking` ativada, o mÃƒÂ³dulo assume o shortcode (wrapper sobre o legado com surface M3)
- `dps_registration_v2` Ã¢â‚¬â€ quando flag `registration_v2` ativada, formulÃƒÂ¡rio nativo M3 (100% independente do legado)
- `dps_booking_v2` Ã¢â‚¬â€ quando flag `booking_v2` ativada, wizard nativo M3 de 5 steps (100% independente do legado)

**CPTs, tabelas e opÃƒÂ§ÃƒÂµes**:
- Option: `dps_frontend_feature_flags` Ã¢â‚¬â€ controle de rollout por mÃƒÂ³dulo (flags: `registration`, `booking`, `settings`, `registration_v2`, `booking_v2`)
- Option: `dps_frontend_usage_counters` Ã¢â‚¬â€ contadores de telemetria por mÃƒÂ³dulo
- Transient: `dps_booking_confirmation_{user_id}` Ã¢â‚¬â€ confirmaÃƒÂ§ÃƒÂ£o de agendamento v2 (TTL 5min)

**Hooks consumidos** (Fase 2 Ã¢â‚¬â€ mÃƒÂ³dulo Registration v1 dual-run):
- `dps_registration_after_fields` (preservado Ã¢â‚¬â€ consumido pelo Loyalty)
- `dps_registration_after_client_created` (preservado Ã¢â‚¬â€ consumido pelo Loyalty)
- `dps_registration_spam_check` (preservado)
- `dps_registration_agenda_url` (preservado)

**Hooks consumidos** (Fase 3 Ã¢â‚¬â€ mÃƒÂ³dulo Booking v1 dual-run):
- `dps_base_after_save_appointment` (preservado Ã¢â‚¬â€ consumido por stock, payment, groomers, calendar, communications, push, services e booking)
- `dps_base_appointment_fields` (preservado)
- `dps_base_appointment_assignment_fields` (preservado)

**Hooks consumidos** (Fase 4 Ã¢â‚¬â€ mÃƒÂ³dulo Settings):
- `dps_settings_register_tabs` Ã¢â‚¬â€ registra aba "Frontend" via `DPS_Settings_Frontend::register_tab()`
- `dps_settings_save_save_frontend` Ã¢â‚¬â€ processa salvamento das feature flags

**Hooks disparados** (Fase 7 Ã¢â‚¬â€ mÃƒÂ³dulos nativos V2):
- `dps_registration_v2_before_render` Ã¢â‚¬â€ antes de renderizar formulÃƒÂ¡rio de cadastro v2
- `dps_registration_v2_after_render` Ã¢â‚¬â€ apÃƒÂ³s renderizar formulÃƒÂ¡rio de cadastro v2
- `dps_registration_v2_client_created` Ã¢â‚¬â€ apÃƒÂ³s criar cliente via v2 (bridge: dispara hooks legados do Loyalty primeiro)
- `dps_registration_v2_pet_created` Ã¢â‚¬â€ apÃƒÂ³s criar pet via v2
- `dps_registration_spam_check` Ã¢â‚¬â€ filtro anti-spam (reusa hook legado via bridge)
- `dps_booking_v2_before_render` Ã¢â‚¬â€ antes de renderizar wizard de booking v2
- `dps_booking_v2_step_render` Ã¢â‚¬â€ ao renderizar step do wizard
- `dps_booking_v2_step_validate` Ã¢â‚¬â€ filtro de validaÃƒÂ§ÃƒÂ£o por step
- `dps_booking_v2_before_process` Ã¢â‚¬â€ antes de criar agendamento v2
- `dps_booking_v2_after_process` Ã¢â‚¬â€ apÃƒÂ³s processar agendamento v2
- `dps_booking_v2_appointment_created` Ã¢â‚¬â€ apÃƒÂ³s criar agendamento v2

**Hooks de bridge** (Fase 7 Ã¢â‚¬â€ CRÃƒÂTICO: legado PRIMEIRO, v2 DEPOIS):
- `dps_base_after_save_appointment` Ã¢â‚¬â€ 8 consumidores: Stock, Payment, Groomers, Calendar, Communications, Push, Services, Booking
- `dps_base_appointment_fields` Ã¢â‚¬â€ Services: injeÃƒÂ§ÃƒÂ£o de campos
- `dps_base_appointment_assignment_fields` Ã¢â‚¬â€ Groomers: campos de atribuiÃƒÂ§ÃƒÂ£o
- `dps_registration_after_client_created` Ã¢â‚¬â€ Loyalty: cÃƒÂ³digo de indicaÃƒÂ§ÃƒÂ£o

**AJAX endpoints** (Fase 7.3 Ã¢â‚¬â€ Booking V2):
- `wp_ajax_dps_booking_search_client` Ã¢â‚¬â€ busca cliente por telefone (nonce + capability)
- `wp_ajax_dps_booking_get_pets` Ã¢â‚¬â€ lista pets do cliente com paginaÃƒÂ§ÃƒÂ£o (nonce + capability)
- `wp_ajax_dps_booking_get_services` Ã¢â‚¬â€ serviÃƒÂ§os ativos com preÃƒÂ§os por porte (nonce + capability)
- `wp_ajax_dps_booking_get_slots` Ã¢â‚¬â€ horÃƒÂ¡rios livres 08:00-18:00/30min (nonce + capability)
- `wp_ajax_dps_booking_validate_step` Ã¢â‚¬â€ validaÃƒÂ§ÃƒÂ£o server-side por step (nonce + capability)

**DependÃƒÂªncias**:
- Depende do plugin base (DPS_Base_Plugin + design tokens CSS)
- MÃƒÂ³dulo Registration v1 depende de `DPS_Registration_Addon` (add-on legado) para dual-run
- MÃƒÂ³dulo Booking v1 depende de `DPS_Booking_Addon` (add-on legado) para dual-run
- MÃƒÂ³dulos V2 nativos (Registration V2, Booking V2) sÃƒÂ£o 100% independentes dos add-ons legados
- MÃƒÂ³dulo Settings depende de `DPS_Settings_Frontend` (sistema de abas do base)

**Arquitetura interna**:
- `DPS_Frontend_Addon` Ã¢â‚¬â€ orquestrador com injeÃƒÂ§ÃƒÂ£o de dependÃƒÂªncias
- `DPS_Frontend_Module_Registry` Ã¢â‚¬â€ registro e boot de mÃƒÂ³dulos
- `DPS_Frontend_Feature_Flags` Ã¢â‚¬â€ controle de rollout persistido
- `DPS_Frontend_Compatibility` Ã¢â‚¬â€ bridges para legado
- `DPS_Frontend_Assets` Ã¢â‚¬â€ enqueue condicional M3 Expressive
- `DPS_Frontend_Logger` Ã¢â‚¬â€ observabilidade via error_log + telemetria batch
- `DPS_Frontend_Request_Guard` Ã¢â‚¬â€ seguranÃƒÂ§a centralizada (nonce, capability, sanitizaÃƒÂ§ÃƒÂ£o)
- `DPS_Template_Engine` Ã¢â‚¬â€ renderizaÃƒÂ§ÃƒÂ£o com suporte a override via tema (dps-templates/)
- `DPS_Frontend_Registration_Module` Ã¢â‚¬â€ v1 dual-run: assume shortcode, delega lÃƒÂ³gica ao legado
- `DPS_Frontend_Booking_Module` Ã¢â‚¬â€ v1 dual-run: assume shortcode, delega lÃƒÂ³gica ao legado
- `DPS_Frontend_Settings_Module` Ã¢â‚¬â€ registra aba de configuraÃƒÂ§ÃƒÂµes com controles de feature flags
- `DPS_Frontend_Registration_V2_Module` Ã¢â‚¬â€ v2 nativo: shortcode `[dps_registration_v2]`, handler, services
- `DPS_Frontend_Booking_V2_Module` Ã¢â‚¬â€ v2 nativo: shortcode `[dps_booking_v2]`, handler, services, AJAX
- `DPS_Registration_Hook_Bridge` Ã¢â‚¬â€ compatibilidade v1/v2 Registration (legado primeiro, v2 depois)
- `DPS_Booking_Hook_Bridge` Ã¢â‚¬â€ compatibilidade v1/v2 Booking (legado primeiro, v2 depois)

**Classes de negÃƒÂ³cio Ã¢â‚¬â€ Registration V2** (Fase 7.2):
- `DPS_Registration_Handler` Ã¢â‚¬â€ pipeline: reCAPTCHA Ã¢â€ â€™ anti-spam Ã¢â€ â€™ validaÃƒÂ§ÃƒÂ£o Ã¢â€ â€™ duplicata Ã¢â€ â€™ criar cliente Ã¢â€ â€™ hooks Loyalty Ã¢â€ â€™ criar pets Ã¢â€ â€™ email confirmaÃƒÂ§ÃƒÂ£o
- `DPS_Form_Validator` Ã¢â‚¬â€ validaÃƒÂ§ÃƒÂ£o de formulÃƒÂ¡rio (nome, email, telefone, CPF, pets)
- `DPS_Cpf_Validator` Ã¢â‚¬â€ validaÃƒÂ§ÃƒÂ£o CPF mod-11
- `DPS_Client_Service` Ã¢â‚¬â€ CRUD para `dps_cliente` (13+ metas)
- `DPS_Pet_Service` Ã¢â‚¬â€ CRUD para `dps_pet`
- `DPS_Breed_Provider` Ã¢â‚¬â€ dataset de raÃƒÂ§as por espÃƒÂ©cie (cÃƒÂ£o: 44, gato: 20)
- `DPS_Duplicate_Detector` Ã¢â‚¬â€ detecÃƒÂ§ÃƒÂ£o por telefone com override admin
- `DPS_Recaptcha_Service` Ã¢â‚¬â€ verificaÃƒÂ§ÃƒÂ£o reCAPTCHA v3
- `DPS_Email_Confirmation_Service` Ã¢â‚¬â€ token UUID 48h + envio

**Classes de negÃƒÂ³cio Ã¢â‚¬â€ Booking V2** (Fase 7.3):
- `DPS_Booking_Handler` Ã¢â‚¬â€ pipeline: validaÃƒÂ§ÃƒÂ£o Ã¢â€ â€™ extras Ã¢â€ â€™ criar appointment Ã¢â€ â€™ confirmaÃƒÂ§ÃƒÂ£o transient Ã¢â€ â€™ hook bridge (8 add-ons)
- `DPS_Booking_Validator` Ã¢â‚¬â€ validaÃƒÂ§ÃƒÂ£o multi-step (5 steps) + extras (TaxiDog/Tosa)
- `DPS_Appointment_Service` Ã¢â‚¬â€ CRUD para `dps_agendamento` (16+ metas, conflitos, busca por cliente)
- `DPS_Booking_Confirmation_Service` Ã¢â‚¬â€ transient de confirmaÃƒÂ§ÃƒÂ£o (5min TTL)
- `DPS_Booking_Ajax` Ã¢â‚¬â€ 5 endpoints AJAX (busca cliente, pets, serviÃƒÂ§os, slots, validaÃƒÂ§ÃƒÂ£o)

**EstratÃƒÂ©gia de compatibilidade (Fases 2Ã¢â‚¬â€œ4)**:
- IntervenÃƒÂ§ÃƒÂ£o mÃƒÂ­nima: o legado continua processando formulÃƒÂ¡rio, emails, REST, AJAX, settings e cron
- MÃƒÂ³dulos de shortcode assumem o shortcode (envolve output na `.dps-frontend` surface) e adicionam CSS extra
- MÃƒÂ³dulo de settings registra aba via API moderna `register_tab()` sem alterar abas existentes
- Rollback: desabilitar flag do mÃƒÂ³dulo restaura comportamento 100% legado

**CoexistÃƒÂªncia v1/v2** (Fase 7):
- Shortcodes v1 (`[dps_registration_form]`, `[dps_booking_form]`) e v2 (`[dps_registration_v2]`, `[dps_booking_v2]`) podem estar ativos simultaneamente
- Feature flags independentes: `registration` (v1), `registration_v2` (v2), `booking` (v1), `booking_v2` (v2)
- Hook bridge garante compatibilidade: hooks legados disparam PRIMEIRO, hooks v2 DEPOIS
- Rollback instantÃƒÂ¢neo via toggle de flag Ã¢â‚¬â€ sem perda de dados

**Introduzido em**: v1.0.0 (Fases 1Ã¢â‚¬â€œ6), v2.0.0 (Fase 7.1), v2.1.0 (Fase 7.2), v2.2.0 (Fase 7.3), v2.3.0 (Fase 7.4), v2.4.0 (Fase 7.5)

**DocumentaÃƒÂ§ÃƒÂ£o operacional (Fase 5)**:
- `docs/implementation/FRONTEND_ROLLOUT_GUIDE.md` Ã¢â‚¬â€ guia de ativaÃƒÂ§ÃƒÂ£o por ambiente
- `docs/implementation/FRONTEND_RUNBOOK.md` Ã¢â‚¬â€ diagnÃƒÂ³stico e rollback de incidentes
- `docs/qa/FRONTEND_COMPATIBILITY_MATRIX.md` Ã¢â‚¬â€ matriz de compatibilidade com todos os add-ons
- `docs/qa/FRONTEND_REMOVAL_READINESS.md` Ã¢â‚¬â€ checklist de prontidÃƒÂ£o para remoÃƒÂ§ÃƒÂ£o futura

**DocumentaÃƒÂ§ÃƒÂ£o de governanÃƒÂ§a (Fase 6)**:
- `docs/refactoring/FRONTEND_DEPRECATION_POLICY.md` Ã¢â‚¬â€ polÃƒÂ­tica de depreciaÃƒÂ§ÃƒÂ£o (janela mÃƒÂ­nima 180 dias, processo de comunicaÃƒÂ§ÃƒÂ£o, critÃƒÂ©rios de aceite)
- `docs/refactoring/FRONTEND_REMOVAL_TARGETS.md` Ã¢â‚¬â€ lista de alvos com risco, dependÃƒÂªncias e esforÃƒÂ§o (booking Ã°Å¸Å¸Â¢ baixo; registration Ã°Å¸Å¸Â¡ mÃƒÂ©dio)
- Telemetria de uso: contadores por mÃƒÂ³dulo via `dps_frontend_usage_counters`, exibidos na aba Settings

**DocumentaÃƒÂ§ÃƒÂ£o de implementaÃƒÂ§ÃƒÂ£o nativa (Fase 7)**:
- `docs/refactoring/FRONTEND_NATIVE_IMPLEMENTATION_PLAN.md` Ã¢â‚¬â€ plano completo com inventÃƒÂ¡rio legado, hook bridge, templates, estratÃƒÂ©gia de migraÃƒÂ§ÃƒÂ£o

**DocumentaÃƒÂ§ÃƒÂ£o de coexistÃƒÂªncia e migraÃƒÂ§ÃƒÂ£o (Fase 7.4)**:
- `docs/implementation/FRONTEND_V2_MIGRATION_GUIDE.md` Ã¢â‚¬â€ guia passo a passo de migraÃƒÂ§ÃƒÂ£o v1Ã¢â€ â€™v2 (7 etapas, comparaÃƒÂ§ÃƒÂ£o de features, checklist, rollback, troubleshooting, WP-CLI)
- SeÃƒÂ§ÃƒÂ£o "Status de CoexistÃƒÂªncia v1/v2" na aba Settings com indicadores visuais por mÃƒÂ³dulo

**ObservaÃƒÂ§ÃƒÂµes**:
- PHP 8.4 moderno: constructor promotion, readonly properties, typed properties, return types
- Sem singletons: objetos montados por composiÃƒÂ§ÃƒÂ£o no bootstrap
- Assets carregados somente quando ao menos um mÃƒÂ³dulo estÃƒÂ¡ habilitado (feature flag)
- Roadmap completo em `docs/refactoring/FRONTEND_ADDON_PHASED_ROADMAP.md`

---

### ServiÃƒÂ§os (`desi-pet-shower-services_addon`)

**DiretÃƒÂ³rio**: `plugins/desi-pet-shower-services`

**PropÃƒÂ³sito e funcionalidades principais**:
- Gerenciar catÃƒÂ¡logo de serviÃƒÂ§os oferecidos
- Definir preÃƒÂ§os e duraÃƒÂ§ÃƒÂ£o por porte de pet
- Vincular serviÃƒÂ§os aos agendamentos
- Povoar catÃƒÂ¡logo padrÃƒÂ£o na ativaÃƒÂ§ÃƒÂ£o
- **[v1.2.0]** Centralizar toda lÃƒÂ³gica de cÃƒÂ¡lculo de preÃƒÂ§os via API pÃƒÂºblica

**Shortcodes expostos**: Nenhum

**CPTs, tabelas e opÃƒÂ§ÃƒÂµes**:
- CPT: `dps_service` (registrado via `DPS_CPT_Helper`)
- Metadados: preÃƒÂ§os e duraÃƒÂ§ÃƒÂ£o por porte (pequeno, mÃƒÂ©dio, grande)

**Hooks consumidos**:
- `dps_base_nav_tabs_*`: adiciona aba "ServiÃƒÂ§os"
- `dps_base_sections_*`: renderiza catÃƒÂ¡logo e formulÃƒÂ¡rios
- Hook de agendamento: adiciona campos de seleÃƒÂ§ÃƒÂ£o de serviÃƒÂ§os

**Hooks disparados**: Nenhum

**Endpoints AJAX expostos**:
- `dps_get_services_details`: retorna detalhes de serviÃƒÂ§os de um agendamento (movido da Agenda em v1.2.0)

**API PÃƒÂºblica** (desde v1.2.0):
A classe `DPS_Services_API` centraliza toda a lÃƒÂ³gica de serviÃƒÂ§os e cÃƒÂ¡lculo de preÃƒÂ§os:

```php
// Obter dados completos de um serviÃƒÂ§o
$service = DPS_Services_API::get_service( $service_id );
// Retorna: ['id', 'title', 'type', 'category', 'active', 'price', 'price_small', 'price_medium', 'price_large']

// Calcular preÃƒÂ§o de um serviÃƒÂ§o por porte
$price = DPS_Services_API::calculate_price( $service_id, 'medio' );
// Aceita: 'pequeno'/'small', 'medio'/'medium', 'grande'/'large'

// Calcular total de um agendamento
$total = DPS_Services_API::calculate_appointment_total( 
    $service_ids,  // array de IDs de serviÃƒÂ§os
    $pet_ids,      // array de IDs de pets
    [              // contexto opcional
        'custom_prices' => [ service_id => price ],  // preÃƒÂ§os personalizados
        'extras' => 50.00,     // valor de extras
        'taxidog' => 25.00,    // valor de taxidog
    ]
);
// Retorna: ['total', 'services_total', 'services_details', 'extras_total', 'taxidog_total']

// Obter detalhes de serviÃƒÂ§os de um agendamento
$details = DPS_Services_API::get_services_details( $appointment_id );
// Retorna: ['services' => [['name', 'price'], ...], 'total']
```

**Contrato de integraÃƒÂ§ÃƒÂ£o**:
- Outros add-ons DEVEM usar `DPS_Services_API` para cÃƒÂ¡lculos de preÃƒÂ§os
- Agenda Add-on delega `dps_get_services_details` para esta API (desde v1.1.0)
- Finance Add-on DEVE usar esta API para obter valores histÃƒÂ³ricos
- Portal do Cliente DEVE usar esta API para exibir valores

**DependÃƒÂªncias**:
- Depende do plugin base para estrutura de navegaÃƒÂ§ÃƒÂ£o
- Reutiliza `DPS_CPT_Helper` para registro de CPT

**Introduzido em**: v0.1.0  
**API pÃƒÂºblica**: v1.2.0

---

### EstatÃƒÂ­sticas (`desi-pet-shower-stats_addon`)

**DiretÃƒÂ³rio**: `plugins/desi-pet-shower-stats`

**PropÃƒÂ³sito e funcionalidades principais**:
- Exibir mÃƒÂ©tricas de uso do sistema (atendimentos, receita, despesas, lucro)
- Listar serviÃƒÂ§os mais recorrentes com grÃƒÂ¡fico de barras (Chart.js)
- Filtrar estatÃƒÂ­sticas por perÃƒÂ­odo personalizado
- Exibir pets inativos com link de reengajamento via WhatsApp
- MÃƒÂ©tricas de assinaturas (ativas, pendentes, receita, valor em aberto)
- Sistema de cache via transients (1h para financeiros, 24h para inativos)

**Shortcodes expostos**: Nenhum

**CPTs, tabelas e opÃƒÂ§ÃƒÂµes**:
- NÃƒÂ£o cria CPTs ou tabelas prÃƒÂ³prias
- Consulta `dps_transacoes` para mÃƒÂ©tricas financeiras
- Consulta CPTs do nÃƒÂºcleo: `dps_agendamento`, `dps_cliente`, `dps_pet`, `dps_subscription`, `dps_service`
- Transients criados: `dps_stats_total_revenue_*`, `dps_stats_financial_*`, `dps_stats_appointments_*`, `dps_stats_inactive_*`

**Hooks consumidos**:
- `dps_base_nav_tabs_after_history` (prioridade 20): adiciona aba "EstatÃƒÂ­sticas"
- `dps_base_sections_after_history` (prioridade 20): renderiza dashboard de estatÃƒÂ­sticas
- `admin_post_dps_clear_stats_cache`: processa limpeza de cache

**Hooks disparados**: Nenhum

**FunÃƒÂ§ÃƒÂµes globais expostas**:
- `dps_get_total_revenue( $start_date, $end_date )`: retorna receita total paga no perÃƒÂ­odo
- `dps_stats_build_cache_key( $prefix, $start, $end )`: gera chave de cache ÃƒÂºnica
- `dps_stats_clear_cache()`: limpa todos os transients de estatÃƒÂ­sticas (requer capability `manage_options`)

**DependÃƒÂªncias**:
- **ObrigatÃƒÂ³ria**: Plugin base DPS (verifica `DPS_Base_Plugin`)
- **Recomendada**: Finance Add-on (para tabela `dps_transacoes` e mÃƒÂ©tricas financeiras)
- **Opcional**: Services Add-on (para tÃƒÂ­tulos de serviÃƒÂ§os no ranking)
- **Opcional**: Subscription Add-on (para mÃƒÂ©tricas de assinaturas)
- **Opcional**: DPS_WhatsApp_Helper (para links de reengajamento)

**Introduzido em**: v0.1.0 (estimado)

**VersÃƒÂ£o atual**: 1.0.0

**ObservaÃƒÂ§ÃƒÂµes**:
- Arquivo ÃƒÂºnico de ~600 linhas; candidato a refatoraÃƒÂ§ÃƒÂ£o modular futura
- Usa Chart.js (CDN) para grÃƒÂ¡fico de barras de serviÃƒÂ§os
- Cache de 1 hora para mÃƒÂ©tricas financeiras, 24 horas para entidades inativas
- Limites de seguranÃƒÂ§a: 500 clientes e 1000 agendamentos por consulta
- Coleta dados de espÃƒÂ©cies/raÃƒÂ§as/mÃƒÂ©dia por cliente mas nÃƒÂ£o exibe (oportunidade de melhoria)

**AnÃƒÂ¡lise completa**: Consulte `docs/analysis/STATS_ADDON_ANALYSIS.md` para anÃƒÂ¡lise detalhada de cÃƒÂ³digo, funcionalidades, seguranÃƒÂ§a, performance, UX e melhorias propostas (38-58h de esforÃƒÂ§o estimado)

---

### Estoque (`desi-pet-shower-stock_addon`)

**DiretÃƒÂ³rio**: `plugins/desi-pet-shower-stock`

**PropÃƒÂ³sito e funcionalidades principais**:
- Controlar estoque de insumos utilizados nos atendimentos
- Registrar movimentaÃƒÂ§ÃƒÂµes de entrada e saÃƒÂ­da
- Gerar alertas de estoque baixo
- Baixar estoque automaticamente ao concluir atendimentos

**Shortcodes expostos**: Nenhum

**CPTs, tabelas e opÃƒÂ§ÃƒÂµes**:
- CPT: `dps_stock_item` (registrado via `DPS_CPT_Helper`)
- Capability customizada: `dps_manage_stock`
- Metadados: quantidade atual, mÃƒÂ­nima, histÃƒÂ³rico de movimentaÃƒÂ§ÃƒÂµes

**Hooks consumidos**:
- `dps_base_after_save_appointment`: baixa estoque automaticamente ao concluir atendimento
- `dps_base_nav_tabs_after_history`: adiciona aba "Estoque"
- `dps_base_sections_after_history`: renderiza controle de estoque

**Hooks disparados**: Nenhum

**DependÃƒÂªncias**:
- Depende do plugin base para estrutura de navegaÃƒÂ§ÃƒÂ£o e hooks de agendamento
- Reutiliza `DPS_CPT_Helper` para registro de CPT

**Introduzido em**: v0.1.0 (estimado)

**ObservaÃƒÂ§ÃƒÂµes**:
- Arquivo ÃƒÂºnico de 432 linhas; candidato a refatoraÃƒÂ§ÃƒÂ£o futura
- Passou a usar navegaÃƒÂ§ÃƒÂ£o integrada ao painel base, removendo menus prÃƒÂ³prios

---

### Assinaturas (`desi-pet-shower-subscription_addon`)

**DiretÃƒÂ³rio**: `plugins/desi-pet-shower-subscription`

**PropÃƒÂ³sito e funcionalidades principais**:
- Gerenciar pacotes mensais de banho e tosa com frequÃƒÂªncias semanal (4 atendimentos) ou quinzenal (2 atendimentos)
- Gerar automaticamente os agendamentos do ciclo vinculados ÃƒÂ  assinatura
- Criar e sincronizar transaÃƒÂ§ÃƒÂµes financeiras na tabela `dps_transacoes`
- Controlar status de pagamento (pendente, pago, em atraso) por ciclo
- Gerar links de renovaÃƒÂ§ÃƒÂ£o via API do Mercado Pago
- Enviar mensagens de cobranÃƒÂ§a via WhatsApp usando `DPS_WhatsApp_Helper`

**Shortcodes expostos**: Nenhum

**CPTs, tabelas e opÃƒÂ§ÃƒÂµes**:

**CPT `dps_subscription`** (show_ui: false, opera via aba no painel base):

| Meta Key | Tipo | DescriÃƒÂ§ÃƒÂ£o |
|----------|------|-----------|
| `subscription_client_id` | int | ID do cliente (`dps_cliente`) |
| `subscription_pet_id` | int | ID do pet (`dps_pet`) |
| `subscription_service` | string | "Banho" ou "Banho e Tosa" |
| `subscription_frequency` | string | "semanal" (4 atendimentos) ou "quinzenal" (2 atendimentos) |
| `subscription_price` | float | Valor do pacote mensal |
| `subscription_start_date` | date | Data de inÃƒÂ­cio do ciclo (Y-m-d) |
| `subscription_start_time` | time | HorÃƒÂ¡rio dos atendimentos (H:i) |
| `subscription_payment_status` | string | "pendente", "pago" ou "em_atraso" |
| `dps_subscription_payment_link` | url | Cache do link de pagamento Mercado Pago |
| `dps_generated_cycle_YYYY-mm` | bool | Flag indicando ciclo jÃƒÂ¡ gerado (evita duplicaÃƒÂ§ÃƒÂ£o) |
| `dps_cycle_status_YYYY-mm` | string | Status de pagamento do ciclo especÃƒÂ­fico |

**Metadados em agendamentos vinculados** (`dps_agendamento`):

| Meta Key | Tipo | DescriÃƒÂ§ÃƒÂ£o |
|----------|------|-----------|
| `subscription_id` | int | ID da assinatura vinculada |
| `subscription_cycle` | string | Ciclo no formato Y-m (ex: "2025-12") |

**Options armazenadas**: Nenhuma (usa credenciais do Payment Add-on via `DPS_MercadoPago_Config`)

**Hooks consumidos**:
- `dps_base_nav_tabs_after_pets`: Adiciona aba "Assinaturas" no painel (prioridade 20)
- `dps_base_sections_after_pets`: Renderiza seÃƒÂ§ÃƒÂ£o de assinaturas (prioridade 20)
- Usa `DPS_MercadoPago_Config::get_access_token()` do Payment Add-on v1.1.0+ (ou options legadas se v1.0.0)

**Hooks disparados**:
- `dps_subscription_payment_status` (action): Permite add-ons de pagamento atualizar status do ciclo
  - **Assinatura**: `do_action( 'dps_subscription_payment_status', int $sub_id, string $cycle_key, string $status )`
  - **ParÃƒÂ¢metros**:
    - `$sub_id`: ID da assinatura
    - `$cycle_key`: Ciclo no formato Y-m (ex: "2025-12"), vazio usa ciclo atual
    - `$status`: "paid", "approved", "success" Ã¢â€ â€™ pago | "failed", "rejected" Ã¢â€ â€™ em_atraso | outros Ã¢â€ â€™ pendente
  - **Exemplo de uso**: `do_action( 'dps_subscription_payment_status', 123, '2025-12', 'paid' );`
- `dps_subscription_whatsapp_message` (filter): Permite customizar mensagem de cobranÃƒÂ§a via WhatsApp
  - **Assinatura**: `apply_filters( 'dps_subscription_whatsapp_message', string $message, WP_Post $subscription, string $payment_link )`

**Fluxo de geraÃƒÂ§ÃƒÂ£o de agendamentos**:
1. Admin salva assinatura com cliente, pet, serviÃƒÂ§o, frequÃƒÂªncia, valor, data/hora
2. Sistema calcula datas: semanal = 4 datas (+7 dias cada), quinzenal = 2 datas (+14 dias cada)
3. Remove agendamentos existentes do mesmo ciclo (evita duplicaÃƒÂ§ÃƒÂ£o)
4. Cria novos `dps_agendamento` com metas vinculadas
5. Marca ciclo como gerado (`dps_generated_cycle_YYYY-mm`)
6. Cria/atualiza transaÃƒÂ§ÃƒÂ£o em `dps_transacoes` via Finance Add-on

**Fluxo de renovaÃƒÂ§ÃƒÂ£o**:
1. Quando todos os atendimentos do ciclo sÃƒÂ£o finalizados, botÃƒÂ£o "Renovar" aparece
2. Admin clica em "Renovar"
3. Sistema avanÃƒÂ§a `subscription_start_date` para prÃƒÂ³ximo mÃƒÂªs (mesmo dia da semana)
4. Reseta `subscription_payment_status` para "pendente"
5. Gera novos agendamentos para o novo ciclo
6. Cria nova transaÃƒÂ§ÃƒÂ£o financeira

**DependÃƒÂªncias**:
- **ObrigatÃƒÂ³ria**: Plugin base DPS (verifica `DPS_Base_Plugin` na inicializaÃƒÂ§ÃƒÂ£o)
- **Recomendada**: Finance Add-on (para tabela `dps_transacoes` e sincronizaÃƒÂ§ÃƒÂ£o de cobranÃƒÂ§as)
- **Recomendada**: Payment Add-on (para geraÃƒÂ§ÃƒÂ£o de links Mercado Pago via API)
- **Opcional**: Communications Add-on (para mensagens via WhatsApp)

**Introduzido em**: v0.2.0

**VersÃƒÂ£o atual**: 1.0.0

**ObservaÃƒÂ§ÃƒÂµes**:
- Arquivo ÃƒÂºnico de 995 linhas; candidato a refatoraÃƒÂ§ÃƒÂ£o futura para padrÃƒÂ£o modular (`includes/`, `assets/`, `templates/`)
- CSS e JavaScript inline na funÃƒÂ§ÃƒÂ£o `section_subscriptions()`; recomenda-se extrair para arquivos externos
- Usa `DPS_WhatsApp_Helper::get_link_to_client()` para links de cobranÃƒÂ§a (desde v1.0.0)
- Cancela assinatura via `wp_trash_post()` (soft delete), preservando dados para possÃƒÂ­vel restauraÃƒÂ§ÃƒÂ£o
- ExclusÃƒÂ£o permanente remove assinatura E todas as transaÃƒÂ§ÃƒÂµes financeiras vinculadas
- GeraÃƒÂ§ÃƒÂ£o de links Mercado Pago usa `external_reference` no formato `dps_subscription_{ID}` para rastreamento via webhook

**AnÃƒÂ¡lise completa**: Consulte `docs/analysis/SUBSCRIPTION_ADDON_ANALYSIS.md` para anÃƒÂ¡lise detalhada de cÃƒÂ³digo, funcionalidades e melhorias propostas (32KB, 10 seÃƒÂ§ÃƒÂµes)

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

  - **MigraÃƒÂ§ÃƒÂ£o**: usar `DPS_Settings_Frontend::register_tab()` com callback que renderiza o conteÃƒÂºdo
  - **Nota**: O sistema moderno de abas jÃƒÂ¡ renderiza automaticamente o conteÃƒÂºdo via callbacks registrados.

#### PÃƒÂ¡gina de detalhes do cliente

- **`dps_client_page_header_badges`** (action) (desde v1.3.0)
  - **ParÃƒÂ¢metros**: `$client_id` (int), `$client` (WP_Post)
  - **PropÃƒÂ³sito**: adicionar badges ao lado do nome do cliente (ex: nÃƒÂ­vel de fidelidade, tags)
  - **Consumido por**: Add-ons de fidelidade para mostrar nÃƒÂ­vel/status
  - **Exemplo**:
    ```php
    add_action( 'dps_client_page_header_badges', function( $client_id, $client ) {
        echo '<span class="dps-badge dps-badge--gold">Ã¢Â­Â VIP</span>';
    }, 10, 2 );
    ```

- **`dps_client_page_header_actions`** (action) (desde v1.1.0)
  - **ParÃƒÂ¢metros**: `$client_id` (int), `$client` (WP_Post), `$base_url` (string)
  - **PropÃƒÂ³sito**: adicionar botÃƒÂµes de aÃƒÂ§ÃƒÂ£o ao painel de aÃƒÂ§ÃƒÂµes rÃƒÂ¡pidas da pÃƒÂ¡gina de detalhes do cliente
  - **AtualizaÃƒÂ§ÃƒÂ£o v1.3.0**: movido para painel dedicado "AÃƒÂ§ÃƒÂµes RÃƒÂ¡pidas" com melhor organizaÃƒÂ§ÃƒÂ£o visual
  - **Consumido por**: Client Portal (link de atualizaÃƒÂ§ÃƒÂ£o de perfil), Tosa Consent (link de consentimento)
  - **Exemplo**:
    ```php
    add_action( 'dps_client_page_header_actions', function( $client_id, $client, $base_url ) {
        echo '<button class="dps-btn-action">Minha AÃƒÂ§ÃƒÂ£o</button>';
    }, 10, 3 );
    ```

- **`dps_client_page_after_personal_section`** (action) (desde v1.2.0)
  - **ParÃƒÂ¢metros**: `$client_id` (int), `$client` (WP_Post), `$meta` (array)
  - **PropÃƒÂ³sito**: adicionar seÃƒÂ§ÃƒÂµes personalizadas apÃƒÂ³s os dados pessoais do cliente
  - **Consumido por**: Add-ons que precisam exibir informaÃƒÂ§ÃƒÂµes complementares
  - **Exemplo**:
    ```php
    add_action( 'dps_client_page_after_personal_section', function( $client_id, $client, $meta ) {
        echo '<div class="dps-client-section"><!-- ConteÃƒÂºdo personalizado --></div>';
    }, 10, 3 );
    ```

- **`dps_client_page_after_contact_section`** (action) (desde v1.2.0)
  - **ParÃƒÂ¢metros**: `$client_id` (int), `$client` (WP_Post), `$meta` (array)
  - **PropÃƒÂ³sito**: adicionar seÃƒÂ§ÃƒÂµes apÃƒÂ³s contato e redes sociais
  - **Consumido por**: Add-ons de fidelidade, comunicaÃƒÂ§ÃƒÂµes avanÃƒÂ§adas

- **`dps_client_page_after_pets_section`** (action) (desde v1.2.0)
  - **ParÃƒÂ¢metros**: `$client_id` (int), `$client` (WP_Post), `$pets` (array)
  - **PropÃƒÂ³sito**: adicionar seÃƒÂ§ÃƒÂµes apÃƒÂ³s a lista de pets do cliente
  - **Consumido por**: Add-ons de assinaturas, pacotes de serviÃƒÂ§os

- **`dps_client_page_after_appointments_section`** (action) (desde v1.2.0)
  - **ParÃƒÂ¢metros**: `$client_id` (int), `$client` (WP_Post), `$appointments` (array)
  - **PropÃƒÂ³sito**: adicionar seÃƒÂ§ÃƒÂµes apÃƒÂ³s o histÃƒÂ³rico de atendimentos
  - **Consumido por**: Add-ons financeiros, estatÃƒÂ­sticas avanÃƒÂ§adas

#### Fluxo de agendamentos

- **`dps_base_appointment_fields`** (action)
  - **ParÃƒÂ¢metros**: `$appointment_id` (int, pode ser 0 para novos agendamentos), `$meta` (array)
  - **PropÃƒÂ³sito**: adicionar campos customizados ao formulÃƒÂ¡rio de agendamento (seÃƒÂ§ÃƒÂ£o "ServiÃƒÂ§os e Extras")
  - **Consumido por**: ServiÃƒÂ§os (seleÃƒÂ§ÃƒÂ£o de serviÃƒÂ§os e extras)

- **`dps_base_appointment_assignment_fields`** (action) (desde v1.8.0)
  - **ParÃƒÂ¢metros**: `$appointment_id` (int, pode ser 0 para novos agendamentos), `$meta` (array)
  - **PropÃƒÂ³sito**: adicionar campos de atribuiÃƒÂ§ÃƒÂ£o de profissionais ao formulÃƒÂ¡rio de agendamento (seÃƒÂ§ÃƒÂ£o "AtribuiÃƒÂ§ÃƒÂ£o")
  - **Consumido por**: Groomers (seleÃƒÂ§ÃƒÂ£o de profissionais responsÃƒÂ¡veis)
  - **Nota**: Esta seÃƒÂ§ÃƒÂ£o sÃƒÂ³ ÃƒÂ© renderizada se houver hooks registrados

- **`dps_base_after_save_appointment`** (action)
  - **ParÃƒÂ¢metros**: `$appointment_id` (int)
  - **PropÃƒÂ³sito**: executar aÃƒÂ§ÃƒÂµes apÃƒÂ³s salvar um agendamento
  - **Consumido por**: ComunicaÃƒÂ§ÃƒÂµes (envio de notificaÃƒÂ§ÃƒÂµes), Estoque (baixa automÃƒÂ¡tica)

#### Limpeza de dados

- **`dps_finance_cleanup_for_appointment`** (action)
  - **ParÃƒÂ¢metros**: `$appointment_id` (int)
  - **PropÃƒÂ³sito**: remover dados financeiros associados antes de excluir agendamento
  - **Consumido por**: Financeiro (remove transaÃƒÂ§ÃƒÂµes vinculadas)

### Hooks de add-ons

#### Add-on Financeiro

- **`dps_finance_booking_paid`** (action)
  - **ParÃƒÂ¢metros**: `$transaction_id` (int), `$client_id` (int)
  - **PropÃƒÂ³sito**: disparado quando uma cobranÃƒÂ§a ÃƒÂ© marcada como paga
  - **Consumido por**: Campanhas & Fidelidade (bonifica indicador/indicado na primeira cobranÃƒÂ§a)

#### Add-on de Cadastro PÃƒÂºblico

- **`dps_registration_after_client_created`** (action)
  - **ParÃƒÂ¢metros**: `$client_id` (int), `$referral_code` (string|null)
  - **PropÃƒÂ³sito**: disparado apÃƒÂ³s criar novo cliente via formulÃƒÂ¡rio pÃƒÂºblico
  - **Consumido por**: Campanhas & Fidelidade (registra indicaÃƒÂ§ÃƒÂµes)

#### Add-on Portal do Cliente

- **`dps_portal_tabs`** (filter)
  - **ParÃƒÂ¢metros**: `$tabs` (array), `$client_id` (int)
  - **PropÃƒÂ³sito**: filtrar abas do portal; permite add-ons adicionarem ou removerem abas
  - **Retorno**: array de abas com keys: label, icon, badge (opcional)

- **`dps_portal_before_{tab}_content`** / **`dps_portal_after_{tab}_content`** (action)
  - **ParÃƒÂ¢metros**: `$client_id` (int)
  - **PropÃƒÂ³sito**: injetar conteÃƒÂºdo antes/depois do conteÃƒÂºdo de cada aba
  - **Abas disponÃƒÂ­veis**: inicio, agendamentos, pagamentos, pet-history, galeria, fidelidade, reviews, mensagens, dados

- **`dps_portal_custom_tab_panels`** (action)
  - **ParÃƒÂ¢metros**: `$client_id` (int), `$tabs` (array)
  - **PropÃƒÂ³sito**: renderizar painÃƒÂ©is de abas customizadas adicionadas via `dps_portal_tabs`

- **`dps_portal_after_update_preferences`** (action)
  - **ParÃƒÂ¢metros**: `$client_id` (int)
  - **PropÃƒÂ³sito**: executar aÃƒÂ§ÃƒÂµes apÃƒÂ³s salvar preferÃƒÂªncias de notificaÃƒÂ§ÃƒÂ£o do cliente

- **`dps_portal_access_notification_sent`** (action)
  - **ParÃƒÂ¢metros**: `$client_id` (int), `$sent` (bool), `$access_date` (string), `$ip_address` (string)
  - **PropÃƒÂ³sito**: executar aÃƒÂ§ÃƒÂµes apÃƒÂ³s enviar notificaÃƒÂ§ÃƒÂ£o de acesso ao portal

#### Cron jobs de add-ons

- **`dps_agenda_send_reminders`** (action)
  - **FrequÃƒÂªncia**: diÃƒÂ¡ria
  - **PropÃƒÂ³sito**: enviar lembretes de agendamentos prÃƒÂ³ximos
  - **Registrado por**: Agenda

- **`dps_comm_send_appointment_reminder`** (action)
  - **FrequÃƒÂªncia**: conforme agendado
  - **PropÃƒÂ³sito**: enviar lembretes de agendamento via canais configurados
  - **Registrado por**: ComunicaÃƒÂ§ÃƒÂµes

- **`dps_comm_send_post_service`** (action)
  - **FrequÃƒÂªncia**: conforme agendado
  - **PropÃƒÂ³sito**: enviar mensagens pÃƒÂ³s-atendimento
  - **Registrado por**: ComunicaÃƒÂ§ÃƒÂµes

- **`dps_send_push_notification`** (action)
  - **ParÃƒÂ¢metros**: `$message` (string), `$recipients` (array)
  - **PropÃƒÂ³sito**: enviar notificaÃƒÂ§ÃƒÂµes via Telegram ou e-mail
  - **Registrado por**: Push Notifications

---

## ConsideraÃƒÂ§ÃƒÂµes de estrutura e integraÃƒÂ§ÃƒÂ£o
- Todos os add-ons se conectam por meio dos *hooks* expostos pelo plugin base (`dps_base_nav_tabs_after_pets`, `dps_base_sections_after_history`, `dps_settings_*`), preservando a renderizaÃƒÂ§ÃƒÂ£o centralizada de navegaÃƒÂ§ÃƒÂ£o/abas feita por `DPS_Base_Frontend`.
- As integraÃƒÂ§ÃƒÂµes financeiras compartilham a tabela `dps_transacoes`, seja para sincronizar agendamentos (base + financeiro), gerar cobranÃƒÂ§as (pagamentos, assinaturas) ou exibir pendÃƒÂªncias no portal e na agenda, reforÃƒÂ§ando a necessidade de manter o esquema consistente ao evoluir o sistema.

## PadrÃƒÂµes de desenvolvimento de add-ons

### Estrutura de arquivos recomendada
Para novos add-ons ou refatoraÃƒÂ§ÃƒÂµes futuras, recomenda-se seguir a estrutura modular:

```
plugins/desi-pet-shower-NOME_addon/
Ã¢â€Å“Ã¢â€â‚¬Ã¢â€â‚¬ desi-pet-shower-NOME-addon.php    # Arquivo principal (apenas bootstrapping)
Ã¢â€Å“Ã¢â€â‚¬Ã¢â€â‚¬ includes/                          # Classes e lÃƒÂ³gica do negÃƒÂ³cio
Ã¢â€â€š   Ã¢â€Å“Ã¢â€â‚¬Ã¢â€â‚¬ class-dps-NOME-cpt.php        # Registro de Custom Post Types
Ã¢â€â€š   Ã¢â€Å“Ã¢â€â‚¬Ã¢â€â‚¬ class-dps-NOME-metaboxes.php  # Metaboxes e campos customizados
Ã¢â€â€š   Ã¢â€Å“Ã¢â€â‚¬Ã¢â€â‚¬ class-dps-NOME-admin.php      # Interface administrativa
Ã¢â€â€š   Ã¢â€â€Ã¢â€â‚¬Ã¢â€â‚¬ class-dps-NOME-frontend.php   # LÃƒÂ³gica do frontend
Ã¢â€Å“Ã¢â€â‚¬Ã¢â€â‚¬ assets/                            # Recursos estÃƒÂ¡ticos
Ã¢â€â€š   Ã¢â€Å“Ã¢â€â‚¬Ã¢â€â‚¬ css/                          # Estilos CSS
Ã¢â€â€š   Ã¢â€â€š   Ã¢â€â€Ã¢â€â‚¬Ã¢â€â‚¬ NOME-addon.css
Ã¢â€â€š   Ã¢â€â€Ã¢â€â‚¬Ã¢â€â‚¬ js/                           # Scripts JavaScript
Ã¢â€â€š       Ã¢â€â€Ã¢â€â‚¬Ã¢â€â‚¬ NOME-addon.js
Ã¢â€â€Ã¢â€â‚¬Ã¢â€â‚¬ uninstall.php                      # Limpeza de dados na desinstalaÃƒÂ§ÃƒÂ£o
```

**BenefÃƒÂ­cios desta estrutura:**
- **SeparaÃƒÂ§ÃƒÂ£o de responsabilidades**: cada classe tem um propÃƒÂ³sito claro
- **Manutenibilidade**: mais fÃƒÂ¡cil localizar e modificar funcionalidades especÃƒÂ­ficas
- **ReutilizaÃƒÂ§ÃƒÂ£o**: classes podem ser testadas e reutilizadas independentemente
- **Performance**: possibilita carregamento condicional de componentes

**Add-ons que jÃƒÂ¡ seguem este padrÃƒÂ£o:**
- `client-portal_addon`: estrutura bem organizada com `includes/` e `assets/`
- `finance_addon`: possui `includes/` para classes auxiliares

**Add-ons que poderiam se beneficiar de refatoraÃƒÂ§ÃƒÂ£o futura:**
- `backup_addon`: 1338 linhas em um ÃƒÂºnico arquivo (anÃƒÂ¡lise em `docs/analysis/BACKUP_ADDON_ANALYSIS.md`)
- `loyalty_addon`: 1148 linhas em um ÃƒÂºnico arquivo
- `subscription_addon`: 995 linhas em um ÃƒÂºnico arquivo (anÃƒÂ¡lise em `docs/analysis/SUBSCRIPTION_ADDON_ANALYSIS.md`)
- `registration_addon`: 636 linhas em um ÃƒÂºnico arquivo
- `stats_addon`: 538 linhas em um ÃƒÂºnico arquivo
- `groomers_addon`: 473 linhas em um ÃƒÂºnico arquivo (anÃƒÂ¡lise em `docs/analysis/GROOMERS_ADDON_ANALYSIS.md`)
- `stock_addon`: 432 linhas em um ÃƒÂºnico arquivo (anÃƒÂ¡lise em `docs/analysis/STOCK_ADDON_ANALYSIS.md`)

### Activation e Deactivation Hooks

**Activation Hook (`register_activation_hook`):**
- Criar pÃƒÂ¡ginas necessÃƒÂ¡rias
- Criar tabelas de banco de dados via `dbDelta()`
- Definir opÃƒÂ§ÃƒÂµes padrÃƒÂ£o do plugin
- Criar roles e capabilities customizadas
- **NÃƒÆ’O** agendar cron jobs (use `init` com verificaÃƒÂ§ÃƒÂ£o `wp_next_scheduled`)

**Deactivation Hook (`register_deactivation_hook`):**
- Limpar cron jobs agendados com `wp_clear_scheduled_hook()`
- **NÃƒÆ’O** remover dados do usuÃƒÂ¡rio (reservado para `uninstall.php`)

**Exemplo de implementaÃƒÂ§ÃƒÂ£o:**
```php
class DPS_Exemplo_Addon {
    public function __construct() {
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
        
        add_action( 'init', [ $this, 'maybe_schedule_cron' ] );
        add_action( 'dps_exemplo_cron_event', [ $this, 'execute_cron' ] );
    }
    
    public function activate() {
        // Criar pÃƒÂ¡ginas, tabelas, opÃƒÂ§ÃƒÂµes padrÃƒÂ£o
        $this->create_pages();
        $this->create_database_tables();
    }
    
    public function deactivate() {
        // Limpar APENAS cron jobs temporÃƒÂ¡rios
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
- Ã¢Å“â€¦ `push_addon`: implementa deactivation hook corretamente
- Ã¢Å“â€¦ `agenda_addon`: agora implementa deactivation hook para limpar `dps_agenda_send_reminders`

### PadrÃƒÂµes de documentaÃƒÂ§ÃƒÂ£o (DocBlocks)

Todos os mÃƒÂ©todos devem seguir o padrÃƒÂ£o WordPress de DocBlocks:

```php
/**
 * Breve descriÃƒÂ§ÃƒÂ£o do mÃƒÂ©todo (uma linha).
 *
 * DescriÃƒÂ§ÃƒÂ£o mais detalhada explicando o propÃƒÂ³sito, comportamento
 * e contexto de uso do mÃƒÂ©todo (opcional).
 *
 * @since 1.0.0
 *
 * @param string $param1 DescriÃƒÂ§ÃƒÂ£o do primeiro parÃƒÂ¢metro.
 * @param int    $param2 DescriÃƒÂ§ÃƒÂ£o do segundo parÃƒÂ¢metro.
 * @param array  $args {
 *     Argumentos opcionais.
 *
 *     @type string $key1 DescriÃƒÂ§ÃƒÂ£o da chave 1.
 *     @type int    $key2 DescriÃƒÂ§ÃƒÂ£o da chave 2.
 * }
 * @return bool Retorna true em caso de sucesso, false caso contrÃƒÂ¡rio.
 */
public function exemplo_metodo( $param1, $param2, $args = [] ) {
    // ImplementaÃƒÂ§ÃƒÂ£o
}
```

**Elementos obrigatÃƒÂ³rios:**
- DescriÃƒÂ§ÃƒÂ£o breve do propÃƒÂ³sito do mÃƒÂ©todo
- `@param` para cada parÃƒÂ¢metro, com tipo e descriÃƒÂ§ÃƒÂ£o
- `@return` com tipo e descriÃƒÂ§ÃƒÂ£o do valor retornado
- `@since` indicando a versÃƒÂ£o de introduÃƒÂ§ÃƒÂ£o (opcional, mas recomendado)

**Elementos opcionais mas ÃƒÂºteis:**
- DescriÃƒÂ§ÃƒÂ£o detalhada para mÃƒÂ©todos complexos
- `@throws` para exceÃƒÂ§ÃƒÂµes que podem ser lanÃƒÂ§adas
- `@see` para referenciar mÃƒÂ©todos ou classes relacionadas
- `@link` para documentaÃƒÂ§ÃƒÂ£o externa
- `@global` para variÃƒÂ¡veis globais utilizadas

**Prioridade de documentaÃƒÂ§ÃƒÂ£o:**
1. MÃƒÂ©todos pÃƒÂºblicos (sempre documentar)
2. MÃƒÂ©todos protegidos/privados complexos
3. Hooks e filtros expostos
4. Constantes e propriedades de classe

### Boas prÃƒÂ¡ticas adicionais

**PrefixaÃƒÂ§ÃƒÂ£o:**
- Todas as funÃƒÂ§ÃƒÂµes globais: `dps_`
- Todas as classes: `DPS_`
- Hooks e filtros: `dps_`
- Options: `dps_`
- Handles de scripts/estilos: `dps-`
- Custom Post Types: `dps_`

**SeguranÃƒÂ§a:**
- Sempre usar nonces em formulÃƒÂ¡rios: `wp_nonce_field()` / `wp_verify_nonce()`
- Escapar saÃƒÂ­da: `esc_html()`, `esc_attr()`, `esc_url()`, `esc_js()`
- Sanitizar entrada: `sanitize_text_field()`, `sanitize_email()`, `wp_kses_post()`
- Verificar capabilities: `current_user_can()`

**Performance:**
- Registrar assets apenas onde necessÃƒÂ¡rio
- Usar `wp_register_*` seguido de `wp_enqueue_*` condicionalmente
- Otimizar queries com `fields => 'ids'` quando apropriado
- PrÃƒÂ©-carregar metadados com `update_meta_cache()`

**IntegraÃƒÂ§ÃƒÂ£o com o nÃƒÂºcleo:**
- Preferir hooks do plugin base (`dps_base_*`, `dps_settings_*`) a menus prÃƒÂ³prios
- Reutilizar classes helper quando disponÃƒÂ­veis (`DPS_CPT_Helper`, `DPS_Money_Helper`, etc.)
- Seguir contratos de hooks existentes sem modificar assinaturas
- Documentar novos hooks expostos com exemplos de uso

---

## Add-on: White Label (PersonalizaÃƒÂ§ÃƒÂ£o de Marca)

**DiretÃƒÂ³rio**: `plugins/desi-pet-shower-whitelabel_addon/`

**VersÃƒÂ£o**: 1.0.0

**PropÃƒÂ³sito**: Personalize o sistema DPS com sua prÃƒÂ³pria marca, cores, logo, SMTP customizado e controles de acesso. Ideal para agÃƒÂªncias e revendedores que desejam oferecer o DPS sob sua prÃƒÂ³pria identidade visual.

### Funcionalidades Principais

1. **Branding e Identidade Visual**
   - Logo customizada (versÃƒÂµes clara e escura)
   - Favicon personalizado
   - Paleta de cores (primÃƒÂ¡ria, secundÃƒÂ¡ria, accent, background, texto)
   - Nome da marca e tagline
   - InformaÃƒÂ§ÃƒÂµes de contato (email, telefone, WhatsApp, URL de suporte)
   - URLs customizadas (website, documentaÃƒÂ§ÃƒÂ£o, termos de uso, privacidade)
   - Footer customizado
   - CSS customizado para ajustes visuais finos
   - OpÃƒÂ§ÃƒÂ£o de ocultar links "Powered by" e links do autor

2. **PÃƒÂ¡gina de Login Personalizada**
   - Logo customizada com dimensÃƒÂµes configurÃƒÂ¡veis
   - Background (cor sÃƒÂ³lida, imagem ou gradiente)
   - FormulÃƒÂ¡rio de login com largura, cor de fundo e bordas customizÃƒÂ¡veis
   - BotÃƒÂ£o de login com cores personalizadas
   - Mensagem customizada acima do formulÃƒÂ¡rio
   - Footer text customizado
   - CSS adicional para ajustes finos
   - OpÃƒÂ§ÃƒÂ£o de ocultar links de registro e recuperaÃƒÂ§ÃƒÂ£o de senha

3. **Modo de ManutenÃƒÂ§ÃƒÂ£o**
   - Bloqueia acesso ao site para visitantes (HTTP 503)
   - Bypass configurÃƒÂ¡vel por roles WordPress (padrÃƒÂ£o: administrator)
   - PÃƒÂ¡gina de manutenÃƒÂ§ÃƒÂ£o customizada com logo, tÃƒÂ­tulo e mensagem
   - Background e cores de texto configurÃƒÂ¡veis
   - Countdown timer opcional para previsÃƒÂ£o de retorno
   - Indicador visual na admin bar quando modo manutenÃƒÂ§ÃƒÂ£o estÃƒÂ¡ ativo
   - Preserva acesso a wp-admin, wp-login e AJAX

4. **PersonalizaÃƒÂ§ÃƒÂ£o da Admin Bar**
   - Ocultar itens especÃƒÂ­ficos da admin bar
   - Customizar logo e links
   - Remover menus do WordPress que nÃƒÂ£o sejam relevantes

5. **SMTP Customizado**
   - ConfiguraÃƒÂ§ÃƒÂ£o de servidor SMTP prÃƒÂ³prio
   - AutenticaÃƒÂ§ÃƒÂ£o segura
   - Teste de envio de e-mail
   - Suporte a TLS/SSL

6. **Assets e Estilos**
   - Carregamento condicional de assets apenas nas pÃƒÂ¡ginas relevantes
   - WordPress Color Picker integrado
   - WordPress Media Uploader para upload de logos
   - Interface responsiva e intuitiva

### Estrutura de Arquivos

```
desi-pet-shower-whitelabel_addon/
Ã¢â€Å“Ã¢â€â‚¬Ã¢â€â‚¬ desi-pet-shower-whitelabel-addon.php (orquestraÃƒÂ§ÃƒÂ£o principal)
Ã¢â€Å“Ã¢â€â‚¬Ã¢â€â‚¬ includes/
Ã¢â€â€š   Ã¢â€Å“Ã¢â€â‚¬Ã¢â€â‚¬ class-dps-whitelabel-settings.php (branding e configuraÃƒÂ§ÃƒÂµes gerais)
Ã¢â€â€š   Ã¢â€Å“Ã¢â€â‚¬Ã¢â€â‚¬ class-dps-whitelabel-branding.php (aplicaÃƒÂ§ÃƒÂ£o de branding no site)
Ã¢â€â€š   Ã¢â€Å“Ã¢â€â‚¬Ã¢â€â‚¬ class-dps-whitelabel-assets.php (gerenciamento de assets CSS/JS)
Ã¢â€â€š   Ã¢â€Å“Ã¢â€â‚¬Ã¢â€â‚¬ class-dps-whitelabel-smtp.php (SMTP customizado)
Ã¢â€â€š   Ã¢â€Å“Ã¢â€â‚¬Ã¢â€â‚¬ class-dps-whitelabel-login-page.php (pÃƒÂ¡gina de login personalizada)
Ã¢â€â€š   Ã¢â€Å“Ã¢â€â‚¬Ã¢â€â‚¬ class-dps-whitelabel-admin-bar.php (personalizaÃƒÂ§ÃƒÂ£o da admin bar)
Ã¢â€â€š   Ã¢â€â€Ã¢â€â‚¬Ã¢â€â‚¬ class-dps-whitelabel-maintenance.php (modo de manutenÃƒÂ§ÃƒÂ£o)
Ã¢â€Å“Ã¢â€â‚¬Ã¢â€â‚¬ assets/
Ã¢â€â€š   Ã¢â€Å“Ã¢â€â‚¬Ã¢â€â‚¬ css/
Ã¢â€â€š   Ã¢â€â€š   Ã¢â€â€Ã¢â€â‚¬Ã¢â€â‚¬ whitelabel-admin.css (estilos da interface admin)
Ã¢â€â€š   Ã¢â€â€Ã¢â€â‚¬Ã¢â€â‚¬ js/
Ã¢â€â€š       Ã¢â€â€Ã¢â€â‚¬Ã¢â€â‚¬ whitelabel-admin.js (JavaScript para color picker, media uploader)
Ã¢â€Å“Ã¢â€â‚¬Ã¢â€â‚¬ templates/
Ã¢â€â€š   Ã¢â€Å“Ã¢â€â‚¬Ã¢â€â‚¬ admin-settings.php (interface de configuraÃƒÂ§ÃƒÂ£o com abas)
Ã¢â€â€š   Ã¢â€â€Ã¢â€â‚¬Ã¢â€â‚¬ maintenance.php (template da pÃƒÂ¡gina de manutenÃƒÂ§ÃƒÂ£o)
Ã¢â€Å“Ã¢â€â‚¬Ã¢â€â‚¬ languages/ (arquivos de traduÃƒÂ§ÃƒÂ£o pt_BR)
Ã¢â€â€Ã¢â€â‚¬Ã¢â€â‚¬ uninstall.php (limpeza ao desinstalar)
```

### Hooks Utilizados

**Do WordPress:**
- `init` (prioridade 1) - Carrega text domain
- `init` (prioridade 5) - Inicializa classes do add-on
- `admin_menu` (prioridade 20) - Registra menu admin
- `admin_enqueue_scripts` - Carrega assets admin
- `template_redirect` (prioridade 1) - Intercepta requisiÃƒÂ§ÃƒÂµes para modo manutenÃƒÂ§ÃƒÂ£o
- `login_enqueue_scripts` - Aplica estilos customizados na pÃƒÂ¡gina de login
- `login_headerurl` - Customiza URL do logo de login
- `login_headertext` - Customiza texto alternativo do logo
- `login_footer` - Adiciona footer customizado no login
- `login_message` - Adiciona mensagem customizada no login
- `admin_bar_menu` (prioridade 100) - Adiciona indicadores visuais na admin bar

**Hooks Expostos (futuros):**
```php
// Permitir bypass customizado do modo manutenÃƒÂ§ÃƒÂ£o
apply_filters( 'dps_whitelabel_maintenance_can_access', false, WP_User $user );

// Customizar template da pÃƒÂ¡gina de manutenÃƒÂ§ÃƒÂ£o
apply_filters( 'dps_whitelabel_maintenance_template', string $template_path );

// Disparado apÃƒÂ³s salvar configuraÃƒÂ§ÃƒÂµes
do_action( 'dps_whitelabel_settings_saved', array $settings );
```

### Tabelas de Banco de Dados

Nenhuma tabela prÃƒÂ³pria. Todas as configuraÃƒÂ§ÃƒÂµes sÃƒÂ£o armazenadas como options do WordPress:

**Options criadas:**
- `dps_whitelabel_settings` - ConfiguraÃƒÂ§ÃƒÂµes de branding e identidade visual
- `dps_whitelabel_smtp` - ConfiguraÃƒÂ§ÃƒÂµes de servidor SMTP
- `dps_whitelabel_login` - ConfiguraÃƒÂ§ÃƒÂµes da pÃƒÂ¡gina de login
- `dps_whitelabel_admin_bar` - ConfiguraÃƒÂ§ÃƒÂµes da admin bar
- `dps_whitelabel_maintenance` - ConfiguraÃƒÂ§ÃƒÂµes do modo de manutenÃƒÂ§ÃƒÂ£o

### Interface Administrativa

**Menu Principal:** desi.pet by PRObst Ã¢â€ â€™ White Label

**Abas de ConfiguraÃƒÂ§ÃƒÂ£o:**
1. **Branding** - Logo, cores, nome da marca, contatos
2. **SMTP** - Servidor de e-mail customizado
3. **Login** - PersonalizaÃƒÂ§ÃƒÂ£o da pÃƒÂ¡gina de login
4. **Admin Bar** - CustomizaÃƒÂ§ÃƒÂ£o da barra administrativa
5. **ManutenÃƒÂ§ÃƒÂ£o** - Modo de manutenÃƒÂ§ÃƒÂ£o e mensagens

**Recursos de UX:**
- Interface com abas para organizaÃƒÂ§ÃƒÂ£o clara
- Color pickers para seleÃƒÂ§ÃƒÂ£o visual de cores
- Media uploader integrado para upload de logos e imagens
- Preview ao vivo de alteraÃƒÂ§ÃƒÂµes (em desenvolvimento)
- BotÃƒÂ£o de restaurar padrÃƒÂµes
- Mensagens de sucesso/erro apÃƒÂ³s salvamento
- ValidaÃƒÂ§ÃƒÂ£o de campos (URLs, cores hexadecimais)

### SeguranÃƒÂ§a

**ValidaÃƒÂ§ÃƒÂµes Implementadas:**
- Ã¢Å“â€¦ Nonce verification em todos os formulÃƒÂ¡rios
- Ã¢Å“â€¦ Capability check (`manage_options`) em todas as aÃƒÂ§ÃƒÂµes
- Ã¢Å“â€¦ SanitizaÃƒÂ§ÃƒÂ£o rigorosa de inputs:
  - `sanitize_text_field()` para textos
  - `esc_url_raw()` para URLs
  - `sanitize_hex_color()` para cores
  - `sanitize_email()` para e-mails
  - `wp_kses_post()` para HTML permitido
- Ã¢Å“â€¦ Escape de outputs:
  - `esc_html()`, `esc_attr()`, `esc_url()` conforme contexto
- Ã¢Å“â€¦ CSS customizado sanitizado (remove JavaScript, expressions, @import)
- Ã¢Å“â€¦ Administrator sempre incluÃƒÂ­do nas roles de bypass (nÃƒÂ£o pode ser removido)
- Ã¢Å“â€¦ ValidaÃƒÂ§ÃƒÂ£o de extensÃƒÂµes de imagem (logo, favicon)

### Compatibilidade

**WordPress:**
- VersÃƒÂ£o mÃƒÂ­nima: 6.9
- PHP: 8.4+

**DPS:**
- Requer: Plugin base (`DPS_Base_Plugin`)
- CompatÃƒÂ­vel com todos os add-ons existentes

**Plugins de Terceiros:**
- CompatÃƒÂ­vel com WP Mail SMTP (prioriza configuraÃƒÂ§ÃƒÂ£o do White Label)
- CompatÃƒÂ­vel com temas page builders (YooTheme, Elementor)
- NÃƒÂ£o conflita com plugins de cache (assets condicionais)

### AnÃƒÂ¡lise Detalhada de Novas Funcionalidades

Para anÃƒÂ¡lise completa sobre a implementaÃƒÂ§ÃƒÂ£o de **Controle de Acesso ao Site**, incluindo:
- Bloqueio de acesso para visitantes nÃƒÂ£o autenticados
- Lista de exceÃƒÂ§ÃƒÂµes de pÃƒÂ¡ginas pÃƒÂºblicas
- Redirecionamento para login customizado
- Controle por role WordPress
- Funcionalidades adicionais sugeridas (controle por CPT, horÃƒÂ¡rio, IP, logs)

Consulte a seÃƒÂ§ÃƒÂ£o **White Label (`desi-pet-shower-whitelabel_addon`)** neste arquivo para o detalhamento funcional e recomendaÃƒÂ§ÃƒÂµes

### LimitaÃƒÂ§ÃƒÂµes Conhecidas

- Modo de manutenÃƒÂ§ÃƒÂ£o bloqueia TODO o site (nÃƒÂ£o permite exceÃƒÂ§ÃƒÂµes por pÃƒÂ¡gina)
- NÃƒÂ£o hÃƒÂ¡ controle granular de acesso (apenas modo manutenÃƒÂ§ÃƒÂ£o "tudo ou nada")
- CSS customizado nÃƒÂ£o tem preview ao vivo (requer salvamento para visualizar)
- Assets admin carregados mesmo fora da pÃƒÂ¡gina de configuraÃƒÂ§ÃƒÂµes (otimizaÃƒÂ§ÃƒÂ£o pendente)
- Falta integraÃƒÂ§ÃƒÂ£o com plugins de two-factor authentication

### PrÃƒÂ³ximos Passos Recomendados (Roadmap)

**v1.1.0 - Controle de Acesso ao Site** (ALTA PRIORIDADE)
- Implementar classe `DPS_WhiteLabel_Access_Control`
- Permitir bloqueio de acesso para visitantes nÃƒÂ£o autenticados
- Lista de exceÃƒÂ§ÃƒÂµes de URLs (com suporte a wildcards)
- Redirecionamento inteligente para login com preservaÃƒÂ§ÃƒÂ£o de URL original
- Controle por role WordPress
- Indicador visual na admin bar quando ativo

**v1.2.0 - Melhorias de Interface** (MÃƒâ€°DIA PRIORIDADE)
- Preview ao vivo de alteraÃƒÂ§ÃƒÂµes de cores
- Editor visual de CSS com syntax highlighting
- Upload de mÃƒÂºltiplos logos para diferentes contextos
- Galeria de presets de cores e layouts

**v1.3.0 - Recursos AvanÃƒÂ§ados** (BAIXA PRIORIDADE)
- Logs de acesso e auditoria
- Controle de acesso por CPT
- Redirecionamento baseado em role
- IntegraÃƒÂ§ÃƒÂ£o com 2FA
- Rate limiting anti-bot

### Changelog

**v1.0.0** - 2025-12-06 - LanÃƒÂ§amento Inicial
- Branding completo (logo, cores, nome da marca)
- PÃƒÂ¡gina de login personalizada
- Modo de manutenÃƒÂ§ÃƒÂ£o com bypass por roles
- PersonalizaÃƒÂ§ÃƒÂ£o da admin bar
- SMTP customizado
- Interface administrativa com abas
- Suporte a i18n (pt_BR)
- DocumentaÃƒÂ§ÃƒÂ£o completa

---

## Add-on: AI (Assistente Virtual)

**DiretÃƒÂ³rio**: `plugins/desi-pet-shower-ai/`

**VersÃƒÂ£o**: 1.6.0 (schema DB: 1.5.0)

**PropÃƒÂ³sito**: Assistente virtual inteligente para o Portal do Cliente, chat pÃƒÂºblico para visitantes, e geraÃƒÂ§ÃƒÂ£o de sugestÃƒÂµes de comunicaÃƒÂ§ÃƒÂµes (WhatsApp e e-mail). Inclui analytics e base de conhecimento.

### Funcionalidades Principais

1. **Portal do Cliente**
   - Widget de chat para clientes fazerem perguntas sobre agendamentos, serviÃƒÂ§os, histÃƒÂ³rico
   - Respostas contextualizadas baseadas em dados reais do cliente e pets
   - Escopo restrito a assuntos relacionados a Banho e Tosa

2. **Chat PÃƒÂºblico** (v1.6.0+)
   - Shortcode `[dps_ai_public_chat]` para visitantes nÃƒÂ£o autenticados
   - Modos inline e floating, temas light/dark
   - FAQs customizÃƒÂ¡veis, rate limiting por IP
   - IntegraÃƒÂ§ÃƒÂ£o com base de conhecimento

3. **Assistente de ComunicaÃƒÂ§ÃƒÂµes** (v1.2.0+)
   - Gera sugestÃƒÂµes de mensagens para WhatsApp
   - Gera sugestÃƒÂµes de e-mail (assunto e corpo)
   - **NUNCA envia automaticamente** - apenas sugere textos para revisÃƒÂ£o humana

4. **Analytics e Feedback** (v1.5.0+)
   - MÃƒÂ©tricas de uso (perguntas, tokens, erros, tempo de resposta)
   - Feedback positivo/negativo com comentÃƒÂ¡rios
   - Dashboard administrativo de analytics
   - Base de conhecimento (CPT `dps_ai_knowledge`)

5. **Agendamento via Chat** (v1.5.0+)
   - IntegraÃƒÂ§ÃƒÂ£o com Agenda Add-on
   - SugestÃƒÂ£o de horÃƒÂ¡rios disponÃƒÂ­veis
   - Modos: request (solicita agendamento) e direct (agenda diretamente)

### Classes Principais

#### `DPS_AI_Client`

Cliente HTTP para API da OpenAI.

**MÃƒÂ©todos:**
- `chat( array $messages, array $options = [] )`: Faz chamada ÃƒÂ  API Chat Completions
- `test_connection()`: Testa validaÃƒÂ§ÃƒÂ£o da API key

**ConfiguraÃƒÂ§ÃƒÂµes:**
- API key armazenada em `dps_ai_settings['api_key']`
- Modelo, temperatura, max_tokens, timeout configurÃƒÂ¡veis

#### `DPS_AI_Assistant`

Assistente principal para Portal do Cliente.

**MÃƒÂ©todos:**
- `answer_portal_question( int $client_id, array $pet_ids, string $user_question )`: Responde pergunta do cliente
- `get_base_system_prompt()`: Retorna prompt base de seguranÃƒÂ§a (pÃƒÂºblico, reutilizÃƒÂ¡vel)

**System Prompt:**
- Escopo restrito a Banho e Tosa, serviÃƒÂ§os, agendamentos, histÃƒÂ³rico, funcionalidades DPS
- ProÃƒÂ­be assuntos fora do contexto (polÃƒÂ­tica, religiÃƒÂ£o, finanÃƒÂ§as pessoais, etc.)
- Protegido contra contradiÃƒÂ§ÃƒÂµes de instruÃƒÂ§ÃƒÂµes adicionais

#### `DPS_AI_Message_Assistant` (v1.2.0+)

Assistente para geraÃƒÂ§ÃƒÂ£o de sugestÃƒÂµes de comunicaÃƒÂ§ÃƒÂµes.

**MÃƒÂ©todos:**

```php
/**
 * Gera sugestÃƒÂ£o de mensagem para WhatsApp.
 *
 * @param array $context {
 *     Contexto da mensagem.
 *
 *     @type string   $type              Tipo de mensagem (lembrete, confirmacao, pos_atendimento, etc.)
 *     @type string   $client_name       Nome do cliente
 *     @type string   $client_phone      Telefone do cliente
 *     @type string   $pet_name          Nome do pet
 *     @type string   $appointment_date  Data do agendamento (formato legÃƒÂ­vel)
 *     @type string   $appointment_time  Hora do agendamento
 *     @type array    $services          Lista de nomes de serviÃƒÂ§os
 *     @type string   $groomer_name      Nome do groomer (opcional)
 *     @type string   $amount            Valor formatado (opcional, para cobranÃƒÂ§as)
 *     @type string   $additional_info   InformaÃƒÂ§ÃƒÂµes adicionais (opcional)
 * }
 * @return array|null Array com ['text' => 'mensagem'] ou null em caso de erro.
 */
public static function suggest_whatsapp_message( array $context )

/**
 * Gera sugestÃƒÂ£o de e-mail (assunto e corpo).
 *
 * @param array $context Contexto da mensagem (mesmos campos do WhatsApp).
 * @return array|null Array com ['subject' => 'assunto', 'body' => 'corpo'] ou null.
 */
public static function suggest_email_message( array $context )
```

**Tipos de mensagens suportados:**
- `lembrete`: Lembrete de agendamento
- `confirmacao`: ConfirmaÃƒÂ§ÃƒÂ£o de agendamento
- `pos_atendimento`: Agradecimento pÃƒÂ³s-atendimento
- `cobranca_suave`: Lembrete educado de pagamento
- `cancelamento`: NotificaÃƒÂ§ÃƒÂ£o de cancelamento
- `reagendamento`: ConfirmaÃƒÂ§ÃƒÂ£o de reagendamento

### Handlers AJAX

#### `wp_ajax_dps_ai_suggest_whatsapp_message`

Gera sugestÃƒÂ£o de mensagem WhatsApp via AJAX.

**Request:**
```javascript
{
    action: 'dps_ai_suggest_whatsapp_message',
    nonce: 'dps_ai_comm_nonce',
    context: {
        type: 'lembrete',
        client_name: 'JoÃƒÂ£o Silva',
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
        text: 'OlÃƒÂ¡ JoÃƒÂ£o! Lembrete: amanhÃƒÂ£ ÃƒÂ s 14:00 temos o agendamento...'
    }
}
```

**Response (erro):**
```javascript
{
    success: false,
    data: {
        message: 'NÃƒÂ£o foi possÃƒÂ­vel gerar sugestÃƒÂ£o automÃƒÂ¡tica. A IA pode estar desativada...'
    }
}
```

#### `wp_ajax_dps_ai_suggest_email_message`

Gera sugestÃƒÂ£o de e-mail via AJAX.

**Request:** (mesma estrutura do WhatsApp)

**Response (sucesso):**
```javascript
{
    success: true,
    data: {
        subject: 'Lembrete de Agendamento - desi.pet by PRObst',
        body: 'OlÃƒÂ¡ JoÃƒÂ£o,\n\nEste ÃƒÂ© um lembrete...'
    }
}
```

### Interface JavaScript

**Arquivo:** `assets/js/dps-ai-communications.js`

**Classes CSS:**
- `.dps-ai-suggest-whatsapp`: BotÃƒÂ£o de sugestÃƒÂ£o para WhatsApp
- `.dps-ai-suggest-email`: BotÃƒÂ£o de sugestÃƒÂ£o para e-mail

**Atributos de dados (data-*):**

Para WhatsApp:
```html
<button 
    class="button dps-ai-suggest-whatsapp"
    data-target="#campo-mensagem"
    data-type="lembrete"
    data-client-name="JoÃƒÂ£o Silva"
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

**Modal de prÃƒÂ©-visualizaÃƒÂ§ÃƒÂ£o:**
- E-mails abrem modal para revisÃƒÂ£o antes de inserir nos campos
- UsuÃƒÂ¡rio pode editar assunto e corpo no modal
- BotÃƒÂ£o "Inserir" preenche os campos do formulÃƒÂ¡rio (nÃƒÂ£o envia)

### ConfiguraÃƒÂ§ÃƒÂµes

Armazenadas em `dps_ai_settings`:

```php
[
    'enabled'                 => bool,   // Habilita/desabilita IA
    'api_key'                 => string, // Chave da OpenAI (sk-...)
    'model'                   => string, // gpt-3.5-turbo, gpt-4, etc.
    'temperature'             => float,  // 0-1, padrÃƒÂ£o 0.4
    'timeout'                 => int,    // Segundos, padrÃƒÂ£o 10
    'max_tokens'              => int,    // PadrÃƒÂ£o 500
    'additional_instructions' => string, // InstruÃƒÂ§ÃƒÂµes customizadas (max 2000 chars)
]
```

**OpÃƒÂ§ÃƒÂµes especÃƒÂ­ficas para comunicaÃƒÂ§ÃƒÂµes:**
- WhatsApp: `max_tokens => 300` (mensagens curtas)
- E-mail: `max_tokens => 500` (pode ter mais contexto)
- Temperatura: `0.5` (levemente mais criativo para tom amigÃƒÂ¡vel)

### SeguranÃƒÂ§a

- Ã¢Å“â€¦ ValidaÃƒÂ§ÃƒÂ£o de nonce em todos os handlers AJAX
- Ã¢Å“â€¦ VerificaÃƒÂ§ÃƒÂ£o de capability `edit_posts`
- Ã¢Å“â€¦ SanitizaÃƒÂ§ÃƒÂ£o de todos os inputs (`sanitize_text_field`, `wp_unslash`)
- Ã¢Å“â€¦ System prompt base protegido contra sobrescrita
- Ã¢Å“â€¦ **NUNCA envia mensagens automaticamente**
- Ã¢Å“â€¦ API key server-side only (nunca exposta no JavaScript)

### Falhas e Tratamento de Erros

**IA desativada ou sem API key:**
- Retorna `null` em mÃƒÂ©todos PHP
- Retorna erro amigÃƒÂ¡vel em AJAX: "IA pode estar desativada..."
- **Campo de mensagem nÃƒÂ£o ÃƒÂ© alterado** - usuÃƒÂ¡rio pode escrever manualmente

**Erro na API da OpenAI:**
- Timeout, erro de rede, resposta invÃƒÂ¡lida Ã¢â€ â€™ retorna `null`
- Logs em `error_log()` para debug
- NÃƒÂ£o quebra a interface - usuÃƒÂ¡rio pode continuar

**Parse de e-mail falha:**
- Tenta mÃƒÂºltiplos padrÃƒÂµes (ASSUNTO:/CORPO:, Subject:/Body:, divisÃƒÂ£o por linhas)
- Fallback: primeira linha como assunto, resto como corpo
- Se tudo falhar: retorna `null`

### IntegraÃƒÂ§ÃƒÂ£o com Outros Add-ons

**Communications Add-on:**
- SugestÃƒÂµes de IA podem ser usadas com `DPS_Communications_API`
- IA gera texto Ã¢â€ â€™ usuÃƒÂ¡rio revisa Ã¢â€ â€™ `send_whatsapp()` ou `send_email()`

**Agenda Add-on:**
- Pode adicionar botÃƒÂµes de sugestÃƒÂ£o nas pÃƒÂ¡ginas de agendamento
- Ver exemplos em `includes/ai-communications-examples.php`

**Portal do Cliente:**
- Widget de chat jÃƒÂ¡ integrado via `DPS_AI_Integration_Portal`
- Usa mesmo system prompt base e configuraÃƒÂ§ÃƒÂµes

### DocumentaÃƒÂ§ÃƒÂ£o Adicional

- **Manual completo**: `plugins/desi-pet-shower-ai/AI_COMMUNICATIONS.md`
- **Exemplos de cÃƒÂ³digo**: `plugins/desi-pet-shower-ai/includes/ai-communications-examples.php`
- **Comportamento da IA**: `plugins/desi-pet-shower-ai/BEHAVIOR_EXAMPLES.md`

### Hooks Expostos

Atualmente nenhum hook especÃƒÂ­fico de comunicaÃƒÂ§ÃƒÂµes. PossÃƒÂ­veis hooks futuros:

```php
// Filtro antes de gerar sugestÃƒÂ£o
$context = apply_filters( 'dps_ai_comm_whatsapp_context', $context, $type );

// Filtro apÃƒÂ³s gerar sugestÃƒÂ£o (permite pÃƒÂ³s-processamento)
$message = apply_filters( 'dps_ai_comm_whatsapp_message', $message, $context );
```

### Tabelas de Banco de Dados

**Desde v1.5.0**, o AI Add-on mantÃƒÂ©m 2 tabelas customizadas para analytics e feedback.
**Desde v1.7.0**, foram adicionadas 2 tabelas para histÃƒÂ³rico de conversas persistente.

#### `wp_dps_ai_conversations` (desde v1.7.0)

Armazena metadados de conversas com o assistente de IA em mÃƒÂºltiplos canais.

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

**PropÃƒÂ³sito:**
- Rastrear conversas em mÃƒÂºltiplos canais: `web_chat` (pÃƒÂºblico), `portal`, `whatsapp`, `admin_specialist`
- Identificar usuÃƒÂ¡rios logados via `customer_id` ou visitantes via `session_identifier`
- Agrupar mensagens relacionadas em conversas contextuais
- Analisar padrÃƒÂµes de uso por canal
- Suportar histÃƒÂ³rico de conversas para futuras funcionalidades (ex: "rever conversas anteriores")

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
- `sender_identifier`: ID do usuÃƒÂ¡rio, telefone, IP, etc (opcional)
- `message_metadata`: JSON com dados adicionais (tokens, custo, tempo de resposta, etc)

**PropÃƒÂ³sito:**
- HistÃƒÂ³rico completo de interaÃƒÂ§ÃƒÂµes em ordem cronolÃƒÂ³gica
- AnÃƒÂ¡lise de padrÃƒÂµes de perguntas e respostas
- Compliance (LGPD/GDPR - exportaÃƒÂ§ÃƒÂ£o de dados pessoais)
- Debugging de problemas de IA
- Base para melhorias futuras (ex: sugestÃƒÂµes baseadas em histÃƒÂ³rico)

**Classe de Acesso:**
- `DPS_AI_Conversations_Repository` em `includes/class-dps-ai-conversations-repository.php`
- MÃƒÂ©todos: `create_conversation()`, `add_message()`, `get_conversation()`, `get_messages()`, `list_conversations()`

#### `wp_dps_ai_metrics`

Armazena mÃƒÂ©tricas agregadas de uso da IA por dia e cliente.

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

**PropÃƒÂ³sito:**
- Rastrear uso diÃƒÂ¡rio da IA (quantidade de perguntas, tokens consumidos)
- Monitorar performance (tempo mÃƒÂ©dio de resposta, taxa de erros)
- AnÃƒÂ¡lise de custos e utilizaÃƒÂ§ÃƒÂ£o por cliente
- Dados para dashboard de analytics

#### `wp_dps_ai_feedback`

Armazena feedback individual (Ã°Å¸â€˜Â/Ã°Å¸â€˜Å½) de cada resposta da IA.

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

**PropÃƒÂ³sito:**
- Coletar feedback de usuÃƒÂ¡rios sobre qualidade das respostas
- Identificar padrÃƒÂµes de respostas problemÃƒÂ¡ticas
- Melhorar prompts e treinamento da IA
- AnÃƒÂ¡lise de satisfaÃƒÂ§ÃƒÂ£o

**Versionamento de Schema:**
- VersÃƒÂ£o do schema rastreada em opÃƒÂ§ÃƒÂ£o `dps_ai_db_version`
- Upgrade automÃƒÂ¡tico via `dps_ai_maybe_upgrade_database()` em `plugins_loaded`
- v1.5.0: Tabelas `dps_ai_metrics` e `dps_ai_feedback` criadas via `dbDelta()`
- v1.6.0: Tabelas `dps_ai_conversations` e `dps_ai_messages` criadas via `dbDelta()`
- Idempotente: seguro executar mÃƒÂºltiplas vezes

**ConfiguraÃƒÂ§ÃƒÂµes em `wp_options`:**
- `dps_ai_settings` - ConfiguraÃƒÂ§ÃƒÂµes gerais (API key, modelo, temperatura, etc.)
- `dps_ai_db_version` - VersÃƒÂ£o do schema (desde v1.6.1)

### LimitaÃƒÂ§ÃƒÂµes Conhecidas

- Depende de conexÃƒÂ£o com internet e API key vÃƒÂ¡lida da OpenAI
- Custo por chamada ÃƒÂ  API (variÃƒÂ¡vel por modelo e tokens)
- Qualidade das sugestÃƒÂµes depende da qualidade dos dados fornecidos no contexto
- NÃƒÂ£o substitui revisÃƒÂ£o humana - **sempre revisar antes de enviar**
- Assets carregados em todas as pÃƒÂ¡ginas admin (TODO: otimizar para carregar apenas onde necessÃƒÂ¡rio)

### Exemplos de Uso

Ver arquivo completo de exemplos: `plugins/desi-pet-shower-ai/includes/ai-communications-examples.php`

**Exemplo rÃƒÂ¡pido:**

```php
// Gerar sugestÃƒÂ£o de WhatsApp
$result = DPS_AI_Message_Assistant::suggest_whatsapp_message([
    'type'              => 'lembrete',
    'client_name'       => 'JoÃƒÂ£o Silva',
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

**v1.0.0** - LanÃƒÂ§amento inicial
- Widget de chat no Portal do Cliente
- Respostas contextualizadas sobre agendamentos e serviÃƒÂ§os

**v1.1.0** - InstruÃƒÂ§ÃƒÂµes adicionais
- Campo de instruÃƒÂ§ÃƒÂµes customizadas nas configuraÃƒÂ§ÃƒÂµes
- MÃƒÂ©todo pÃƒÂºblico `get_base_system_prompt()`

**v1.2.0** - Assistente de ComunicaÃƒÂ§ÃƒÂµes
- Classe `DPS_AI_Message_Assistant`
- SugestÃƒÂµes de WhatsApp e e-mail
- Handlers AJAX e interface JavaScript
- Modal de prÃƒÂ©-visualizaÃƒÂ§ÃƒÂ£o para e-mails
- 6 tipos de mensagens suportados
- DocumentaÃƒÂ§ÃƒÂ£o e exemplos de integraÃƒÂ§ÃƒÂ£o

---

## Mapeamento de Capabilities

> **Adicionado em:** 2026-02-18 Ã¢â‚¬â€ Fase 1 do Plano de ImplementaÃƒÂ§ÃƒÂ£o

### Capabilities utilizadas no sistema

| Capability | Contexto de Uso | Plugins |
|-----------|-----------------|---------|
| `manage_options` | Admin pages, REST endpoints, AJAX handlers, configuraÃƒÂ§ÃƒÂµes | Todos os add-ons |
| `dps_manage_clients` | GestÃƒÂ£o de clientes (CRUD) | Base, Frontend |
| `dps_manage_pets` | GestÃƒÂ£o de pets (CRUD) | Base, Frontend |
| `dps_manage_appointments` | GestÃƒÂ£o de agendamentos (CRUD) | Base, Agenda, Frontend |

### Modelo de permissÃƒÂµes

- **Administradores** (`manage_options`): acesso total a todas as operaÃƒÂ§ÃƒÂµes do sistema, incluindo configuraÃƒÂ§ÃƒÂµes, relatÃƒÂ³rios financeiros e endpoints REST.
- **Gestores** (`dps_manage_*`): acesso ÃƒÂ s operaÃƒÂ§ÃƒÂµes de gestÃƒÂ£o do dia a dia (clientes, pets, agendamentos).
- **Portal do cliente**: autenticaÃƒÂ§ÃƒÂ£o via token/sessÃƒÂ£o sem WordPress capabilities. Acesso restrito via `DPS_Portal_Session_Manager::get_authenticated_client_id()`.

### Endpoints REST Ã¢â‚¬â€ Modelo de PermissÃƒÂ£o

| Plugin | Endpoint | Permission Callback |
|--------|----------|---------------------|
| Finance | `dps-finance/v1/transactions` | `current_user_can('manage_options')` |
| Loyalty | `dps-loyalty/v1/*` (5 rotas) | `current_user_can('manage_options')` |
| Communications | `dps-communications/v1/*` (3 rotas) | `current_user_can('manage_options')` |
| AI | `dps-ai/v1/whatsapp-webhook` | `__return_true` (webhook pÃƒÂºblico com validaÃƒÂ§ÃƒÂ£o interna) |
| Agenda | `dps/v1/google-calendar-webhook` | `__return_true` (webhook pÃƒÂºblico com validaÃƒÂ§ÃƒÂ£o interna) |
| Game | `dps-game/v1/*` (2 rotas) | sessao do portal + nonce custom ou `current_user_can('manage_options')` |

---

## Template PadrÃƒÂ£o de Add-on (Fase 2.2)

> DocumentaÃƒÂ§ÃƒÂ£o do padrÃƒÂ£o de inicializaÃƒÂ§ÃƒÂ£o e estrutura de add-ons. Todos os add-ons devem seguir este template para garantir consistÃƒÂªncia.

### Estrutura de DiretÃƒÂ³rios

```
desi-pet-shower-{nome}/
Ã¢â€Å“Ã¢â€â‚¬Ã¢â€â‚¬ desi-pet-shower-{nome}-addon.php   # Arquivo principal com header WP
Ã¢â€Å“Ã¢â€â‚¬Ã¢â€â‚¬ includes/                           # Classes PHP
Ã¢â€â€š   Ã¢â€Å“Ã¢â€â‚¬Ã¢â€â‚¬ class-dps-{nome}-*.php         # Classes de negÃƒÂ³cio
Ã¢â€â€š   Ã¢â€â€Ã¢â€â‚¬Ã¢â€â‚¬ ...
Ã¢â€Å“Ã¢â€â‚¬Ã¢â€â‚¬ assets/                             # CSS/JS
Ã¢â€â€š   Ã¢â€Å“Ã¢â€â‚¬Ã¢â€â‚¬ css/
Ã¢â€â€š   Ã¢â€â€Ã¢â€â‚¬Ã¢â€â‚¬ js/
Ã¢â€Å“Ã¢â€â‚¬Ã¢â€â‚¬ templates/                          # Templates HTML (quando aplicÃƒÂ¡vel)
Ã¢â€â€Ã¢â€â‚¬Ã¢â€â‚¬ uninstall.php                       # Limpeza na desinstalaÃƒÂ§ÃƒÂ£o (quando tem tabelas)
```

### Header WordPress ObrigatÃƒÂ³rio

```php
/**
 * Plugin Name: Desi Pet Shower - {Nome} Add-on
 * Plugin URI: https://github.com/richardprobst/DPS
 * Description: {DescriÃƒÂ§ÃƒÂ£o curta}
 * Version: X.Y.Z
 * Author: PRObst
 * Author URI: https://probst.pro
 * Text Domain: desi-pet-shower
 * Domain Path: /languages
 * Requires at least: 6.9
 * Requires PHP: 8.4
 */
```

### PadrÃƒÂ£o de InicializaÃƒÂ§ÃƒÂ£o

| Etapa | Hook | Prioridade | Responsabilidade |
|-------|------|-----------|------------------|
| Text domain | `init` | 1 | `load_plugin_textdomain()` |
| Classes/lÃƒÂ³gica | `init` | 5 | Instanciar classes, registrar CPTs, hooks |
| Admin menus | `admin_menu` | 20 | Submenu de `desi-pet-shower` |
| Admin assets | `admin_enqueue_scripts` | 10 | CSS/JS condicionais (`$hook_suffix`) |
| AtivaÃƒÂ§ÃƒÂ£o | `register_activation_hook` | Ã¢â‚¬â€ | dbDelta, flush rewrite, capabilities |

### Assets Ã¢â‚¬â€ Carregamento Condicional (ObrigatÃƒÂ³rio)

```php
add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

public function enqueue_admin_assets( $hook ) {
    // Carrega apenas nas pÃƒÂ¡ginas do DPS
    if ( false === strpos( $hook, 'desi-pet-shower' ) ) {
        return;
    }
    wp_enqueue_style( 'dps-{nome}-addon', ... );
    wp_enqueue_script( 'dps-{nome}-addon', ... );
}
```

### Helpers Globais DisponÃƒÂ­veis (Base Plugin)

| Helper | MÃƒÂ©todos Principais |
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
| agenda | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢Å“â€¦ |
| ai | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢Å“â€¦ |
| backup | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢ÂÅ’ |
| booking | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢â‚¬â€ | Ã¢â‚¬â€ | Ã¢Å“â€¦ |
| client-portal | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢Å“â€¦ |
| communications | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢ÂÅ’ |
| finance | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢Å“â€¦ |
| frontend | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢â‚¬â€ | Ã¢â‚¬â€ | Ã¢ÂÅ’ |
| groomers | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢Å“â€¦ |
| loyalty | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢Å“â€¦ |
| payment | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢ÂÅ’ |
| push | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢Å“â€¦ |
| registration | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢Å“â€¦ |
| services | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢â‚¬â€ | Ã¢â‚¬â€ | Ã¢Å“â€¦ |
| stats | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢â‚¬â€ | Ã¢â‚¬â€ | Ã¢ÂÅ’ |
| stock | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢â‚¬â€ | Ã¢Å“â€¦ | Ã¢Å“â€¦ |
| subscription | Ã¢Å“â€¦ | Ã¢Å“â€¦ | Ã¢â‚¬â€ | Ã¢â‚¬â€ | Ã¢ÂÅ’ |

**Legenda:** Ã¢Å“â€¦ Conforme | Ã¢ÂÅ’ Ausente | Ã¢â‚¬â€ NÃƒÂ£o aplicÃƒÂ¡vel (add-on sem UI admin prÃƒÂ³pria)

---

## Contratos de Metadados dos CPTs

> **Adicionado em:** 2026-02-18 Ã¢â‚¬â€ Fase 2.5 do Plano de ImplementaÃƒÂ§ÃƒÂ£o

### dps_cliente Ã¢â‚¬â€ Metadados do Cliente

| Meta Key | Tipo/Formato | ObrigatÃƒÂ³rio | DescriÃƒÂ§ÃƒÂ£o |
|----------|-------------|-------------|-----------|
| `client_cpf` | String (CPF: `000.000.000-00`) | NÃƒÂ£o | CPF do cliente |
| `client_phone` | String (telefone) | **Sim** | Telefone principal |
| `client_email` | String (email) | NÃƒÂ£o | E-mail do cliente |
| `client_birth` | String (data: `Y-m-d`) | NÃƒÂ£o | Data de nascimento |
| `client_instagram` | String | NÃƒÂ£o | Handle do Instagram |
| `client_facebook` | String | NÃƒÂ£o | Perfil do Facebook |
| `client_photo_auth` | Int (`0` ou `1`) | NÃƒÂ£o | AutorizaÃƒÂ§ÃƒÂ£o para fotos |
| `client_address` | String (textarea) | NÃƒÂ£o | EndereÃƒÂ§o completo |
| `client_referral` | String | NÃƒÂ£o | CÃƒÂ³digo de indicaÃƒÂ§ÃƒÂ£o |
| `client_lat` | String (float: `-23.5505`) | NÃƒÂ£o | Latitude (geolocalizaÃƒÂ§ÃƒÂ£o) |
| `client_lng` | String (float: `-46.6333`) | NÃƒÂ£o | Longitude (geolocalizaÃƒÂ§ÃƒÂ£o) |

**Classe handler:** `DPS_Client_Handler` (`includes/class-dps-client-handler.php`)
**Campos obrigatÃƒÂ³rios na validaÃƒÂ§ÃƒÂ£o:** `client_name` (post_title), `client_phone`

### dps_pet Ã¢â‚¬â€ Metadados do Pet

| Meta Key | Tipo/Formato | ObrigatÃƒÂ³rio | DescriÃƒÂ§ÃƒÂ£o |
|----------|-------------|-------------|-----------|
| `owner_id` | Int (ID do `dps_cliente`) | **Sim** | ID do tutor/proprietÃƒÂ¡rio |
| `pet_species` | String (enum: `cachorro`, `gato`, `outro`) | **Sim** | EspÃƒÂ©cie |
| `pet_breed` | String | NÃƒÂ£o | RaÃƒÂ§a |
| `pet_size` | String (enum: `pequeno`, `medio`, `grande`, `gigante`) | **Sim** | Porte |
| `pet_weight` | String (float em kg) | NÃƒÂ£o | Peso |
| `pet_coat` | String | NÃƒÂ£o | Tipo de pelagem |
| `pet_color` | String | NÃƒÂ£o | Cor/marcaÃƒÂ§ÃƒÂµes |
| `pet_birth` | String (data: `Y-m-d`) | NÃƒÂ£o | Data de nascimento |
| `pet_sex` | String (enum: `macho`, `femea`) | **Sim** | Sexo |
| `pet_care` | String (textarea) | NÃƒÂ£o | Cuidados especiais |
| `pet_aggressive` | Int (`0` ou `1`) | NÃƒÂ£o | Flag de agressividade |
| `pet_vaccinations` | String (textarea) | NÃƒÂ£o | Registro de vacinaÃƒÂ§ÃƒÂ£o |
| `pet_allergies` | String (textarea) | NÃƒÂ£o | Alergias conhecidas |
| `pet_behavior` | String (textarea) | NÃƒÂ£o | Notas comportamentais |
| `pet_shampoo_pref` | String | NÃƒÂ£o | PreferÃƒÂªncia de shampoo |
| `pet_perfume_pref` | String | NÃƒÂ£o | PreferÃƒÂªncia de perfume |
| `pet_accessories_pref` | String | NÃƒÂ£o | PreferÃƒÂªncia de acessÃƒÂ³rios |
| `pet_product_restrictions` | String (textarea) | NÃƒÂ£o | RestriÃƒÂ§ÃƒÂµes de produtos |
| `pet_photo_id` | Int (attachment ID) | NÃƒÂ£o | ID da foto do pet |

**Classe handler:** `DPS_Pet_Handler` (`includes/class-dps-pet-handler.php`)
**Campos obrigatÃƒÂ³rios na validaÃƒÂ§ÃƒÂ£o:** `pet_name` (post_title), `owner_id`, `pet_species`, `pet_size`, `pet_sex`

### dps_agendamento Ã¢â‚¬â€ Metadados do Agendamento

| Meta Key | Tipo/Formato | ObrigatÃƒÂ³rio | DescriÃƒÂ§ÃƒÂ£o |
|----------|-------------|-------------|-----------|
| `appointment_client_id` | Int (ID do `dps_cliente`) | **Sim** | ID do cliente |
| `appointment_pet_id` | Int (ID do `dps_pet`) | **Sim** | Pet principal (legado) |
| `appointment_pet_ids` | Array serializado de IDs | NÃƒÂ£o | Multi-pet: lista de pet IDs |
| `appointment_date` | String (data: `Y-m-d`) | **Sim** | Data do atendimento |
| `appointment_time` | String (hora: `H:i`) | **Sim** | HorÃƒÂ¡rio do atendimento |
| `appointment_status` | String (enum) | **Sim** | Status do agendamento |
| `appointment_type` | String (enum: `simple`, `subscription`, `past`) | NÃƒÂ£o | Tipo de agendamento |
| `appointment_services` | Array serializado de IDs | NÃƒÂ£o | IDs dos serviÃƒÂ§os |
| `appointment_service_prices` | Array serializado de floats | NÃƒÂ£o | PreÃƒÂ§os dos serviÃƒÂ§os |
| `appointment_total_value` | Float | NÃƒÂ£o | Valor total |
| `appointment_notes` | String (textarea) | NÃƒÂ£o | ObservaÃƒÂ§ÃƒÂµes |
| `appointment_taxidog` | Int (`0` ou `1`) | NÃƒÂ£o | Flag de TaxiDog |
| `appointment_taxidog_price` | Float | NÃƒÂ£o | PreÃƒÂ§o do TaxiDog |

**Status possÃƒÂ­veis:** `pendente`, `confirmado`, `em_atendimento`, `finalizado`, `finalizado e pago`, `finalizado_pago`, `cancelado`

### RelaÃƒÂ§ÃƒÂµes entre CPTs

```
dps_cliente (1) Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬ (N) dps_pet          via pet.owner_id Ã¢â€ â€™ cliente.ID
dps_cliente (1) Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬ (N) dps_agendamento  via agendamento.appointment_client_id Ã¢â€ â€™ cliente.ID
dps_pet     (1) Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬ (N) dps_agendamento  via agendamento.appointment_pet_id Ã¢â€ â€™ pet.ID
dps_pet     (N) Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬ (N) dps_agendamento  via agendamento.appointment_pet_ids (serializado)
```

---

## IntegraÃƒÂ§ÃƒÂµes Futuras Propostas

### IntegraÃƒÂ§ÃƒÂ£o com Google Tarefas (Google Tasks API)

**Status:** Proposta de anÃƒÂ¡lise (2026-01-19)  
**DocumentaÃƒÂ§ÃƒÂ£o:** proposta consolidada nesta seÃƒÂ§ÃƒÂ£o do `ANALYSIS.md` (ainda sem documento dedicado em `docs/analysis/`)

**Resumo:**
A integraÃƒÂ§ÃƒÂ£o do sistema DPS com Google Tasks API permite sincronizar atividades do sistema (agendamentos, cobranÃƒÂ§as, mensagens) com listas de tarefas do Google, melhorando a organizaÃƒÂ§ÃƒÂ£o e follow-up de atividades administrativas.

**Status:** Ã¢Å“â€¦ VIÃƒÂVEL e RECOMENDADO

**Funcionalidades propostas:**
1. **Agendamentos** (Alta Prioridade)
   - Lembretes de agendamentos pendentes (1 dia antes)
   - Follow-ups pÃƒÂ³s-atendimento (2 dias depois)

2. **Financeiro** (Alta Prioridade)
   - CobranÃƒÂ§as pendentes (1 dia antes do vencimento)
   - RenovaÃƒÂ§ÃƒÂµes de assinatura (5 dias antes)

3. **Portal do Cliente** (MÃƒÂ©dia Prioridade)
   - Mensagens recebidas de clientes (tarefa imediata)

4. **Estoque** (Baixa Prioridade)
   - Alertas de estoque baixo (tarefa de reposiÃƒÂ§ÃƒÂ£o)

**Add-on proposto:** `desi-pet-shower-google-tasks`

**Tipo de sincronizaÃƒÂ§ÃƒÂ£o:** Unidirecional (DPS Ã¢â€ â€™ Google Tasks)
- DPS cria tarefas no Google Tasks
- Google Tasks nÃƒÂ£o modifica dados do DPS
- DPS permanece como "fonte da verdade"

**EsforÃƒÂ§o estimado:**
- v1.0.0 MVP (OAuth + Agendamentos): 42h (~5.5 dias)
- v1.1.0 (+ Financeiro): 10h (~1.5 dias)
- v1.2.0 (+ Portal + Estoque): 14h (~2 dias)
- v1.3.0 (Testes + DocumentaÃƒÂ§ÃƒÂ£o): 21h (~2.5 dias)
- **Total:** 87h (~11 dias ÃƒÂºteis)

**BenefÃƒÂ­cios:**
- CentralizaÃƒÂ§ÃƒÂ£o de tarefas em app que equipe jÃƒÂ¡ usa
- NotificaÃƒÂ§ÃƒÂµes nativas do Google (mobile, desktop, email)
- IntegraÃƒÂ§ÃƒÂ£o com ecossistema Google (Calendar, Gmail, Android, iOS)
- API gratuita (50.000 requisiÃƒÂ§ÃƒÂµes/dia)
- ReduÃƒÂ§ÃƒÂ£o de agendamentos esquecidos (-30% esperado)

**SeguranÃƒÂ§a:**
- AutenticaÃƒÂ§ÃƒÂ£o OAuth 2.0
- Tokens criptografados com AES-256
- Dados sensÃƒÂ­veis filtrÃƒÂ¡veis (admin escolhe o que incluir)
- LGPD compliance (nÃƒÂ£o envia CPF, RG, telefone completo)

**PrÃƒÂ³ximos passos (se aprovado):**
1. Criar projeto no Google Cloud Console
2. Obter credenciais OAuth 2.0
3. Implementar v1.0.0 MVP
4. Testar com 3-5 pet shops piloto (beta 1 mÃƒÂªs)
5. Iterar baseado em feedback
6. LanÃƒÂ§amento geral para clientes DPS

**Consulte os documentos completos para:**
- Arquitetura detalhada (classes, hooks, estrutura de dados)
- Casos de uso detalhados (3 cenÃƒÂ¡rios reais)
- Requisitos tÃƒÂ©cnicos (APIs, OAuth, configuraÃƒÂ§ÃƒÂ£o Google Cloud)
- AnÃƒÂ¡lise de riscos e mitigaÃƒÂ§ÃƒÂµes
- MÃƒÂ©tricas de sucesso (KPIs tÃƒÂ©cnicos e de negÃƒÂ³cio)
- ComparaÃƒÂ§ÃƒÂ£o com alternativas (Microsoft To Do, Todoist, sistema interno)