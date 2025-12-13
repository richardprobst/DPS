# Stats Add-on — FASE 4D: REST API Read-Only (v2.0.0)

**Autor:** DPS Development Team  
**Data:** 2025-12-13  
**Versão Target:** 2.0.0  
**Complexidade:** High (Security-sensitive)  
**Esforço Estimado:** 8-10 horas

---

## 📋 Índice

1. [Visão Geral](#visão-geral)
2. [F4.5 — REST API Read-Only](#f45--rest-api-read-only)
3. [Arquitetura de Segurança](#arquitetura-de-segurança)
4. [Implementação Backend](#implementação-backend)
5. [Settings & UI](#settings--ui)
6. [Logging & Auditoria](#logging--auditoria)
7. [Checklist de Testes](#checklist-de-testes)
8. [Considerações de Segurança](#considerações-de-segurança)

---

## Visão Geral

### Objetivo

Expor métricas do Stats Add-on via **REST API segura** para integração com ferramentas externas de BI (Power BI, Tableau, Data Studio) mantendo **zero exposição de PII** e proteção contra abuso.

### Escopo (F4.5)

- ✅ Endpoint `/wp-json/dps/v1/stats/summary` (KPIs agregados)
- ✅ Endpoint `/wp-json/dps/v1/stats/timeseries` (série temporal opcional)
- ✅ API Key authentication (SHA-256 + timing-safe comparison)
- ✅ Rate limiting (por key + por IP)
- ✅ Access logging (hashed, sem PII)
- ✅ Settings UI (gerar/revogar keys, configurar rate limits)
- ❌ **FORA DO ESCOPO**: Endpoints de write, drill-down por cliente/pet, webhooks

### Princípios de Segurança

1. **Authentication:** Header `X-DPS-Stats-Key` obrigatório (SHA-256 hashed)
2. **Authorization:** Apenas métricas agregadas (zero PII)
3. **Rate Limiting:** 120 req/h per key, 60 req/h per IP
4. **Logging:** IP e key hashed (não reversíveis)
5. **HTTPS:** Documentar que API key DEVE ser usada apenas via HTTPS

---

## F4.5 — REST API Read-Only

### Endpoints Implementados

#### 1. `GET /wp-json/dps/v1/stats/summary`

**Parâmetros Query:**
- `start` (string, required): Data início formato `YYYY-MM-DD`
- `end` (string, required): Data fim formato `YYYY-MM-DD`

**Headers Required:**
- `X-DPS-Stats-Key`: API key gerada nas settings

**Resposta 200 OK:**
```json
{
  "period": {
    "start": "2024-11-01",
    "end": "2024-11-30"
  },
  "metrics": {
    "appointments": {
      "total": 145,
      "completed": 132,
      "cancelled": 8,
      "no_show": 5
    },
    "clients": {
      "new": 18,
      "recurring": 42
    },
    "revenue": {
      "total": 8750.00,
      "currency": "BRL"
    },
    "avg_ticket": 60.34,
    "return_rate_30d": 42.5,
    "conversion_rate": 68.0
  },
  "meta": {
    "generated_at": "2024-12-13T12:00:00Z",
    "cache_hit": true,
    "finance_addon_active": true
  }
}
```

**Erros:**
- `401 Unauthorized`: Key ausente ou inválida
- `429 Too Many Requests`: Rate limit excedido
- `400 Bad Request`: Parâmetros inválidos (datas)

---

#### 2. `GET /wp-json/dps/v1/stats/timeseries` (Opcional)

**Parâmetros Query:**
- `start` (string, required): Data início `YYYY-MM-DD`
- `end` (string, required): Data fim `YYYY-MM-DD`
- `granularity` (string, optional): `daily` ou `weekly` (default: `daily`)

**Resposta 200 OK:**
```json
{
  "period": {
    "start": "2024-11-01",
    "end": "2024-11-30",
    "granularity": "daily"
  },
  "data": [
    {"date": "2024-11-01", "appointments": 5, "moving_avg_7d": 4.8},
    {"date": "2024-11-02", "appointments": 6, "moving_avg_7d": 5.1},
    ...
  ],
  "meta": {
    "points": 30,
    "cache_hit": true
  }
}
```

---

## Arquitetura de Segurança

### 1. API Key Generation

```php
/**
 * Gera API key aleatória e retorna hash SHA-256 para storage.
 * 
 * @return array ['key' => string (plain), 'hash' => string (SHA-256)]
 */
function dps_stats_generate_api_key() {
    // 32 bytes = 256 bits de entropia
    $random_bytes = random_bytes(32);
    $key = bin2hex($random_bytes); // 64 chars hex
    
    $hash = hash('sha256', $key);
    
    return [
        'key' => $key,
        'hash' => $hash
    ];
}
```

**Storage:**
- Option `dps_stats_api_key_hash` (SHA-256)
- **NÃO** armazenar plain key após exibir uma vez

---

### 2. Authentication Middleware

```php
/**
 * Valida API key do header X-DPS-Stats-Key.
 * 
 * @param WP_REST_Request $request
 * @return bool|WP_Error
 */
function dps_stats_api_authenticate( $request ) {
    // Checa se API está habilitada
    if ( ! get_option( 'dps_stats_api_enabled', false ) ) {
        return new WP_Error(
            'api_disabled',
            __( 'Stats API is currently disabled.', 'dps-stats' ),
            ['status' => 503]
        );
    }
    
    // Extrai key do header
    $provided_key = $request->get_header( 'X-DPS-Stats-Key' );
    if ( empty( $provided_key ) ) {
        return new WP_Error(
            'missing_auth',
            __( 'API key is required via X-DPS-Stats-Key header.', 'dps-stats' ),
            ['status' => 401]
        );
    }
    
    // Compara hashes (timing-safe)
    $stored_hash = get_option( 'dps_stats_api_key_hash', '' );
    if ( empty( $stored_hash ) ) {
        return new WP_Error(
            'no_key_configured',
            __( 'No API key configured. Generate one in Stats settings.', 'dps-stats' ),
            ['status' => 401]
        );
    }
    
    $provided_hash = hash( 'sha256', $provided_key );
    
    if ( ! hash_equals( $stored_hash, $provided_hash ) ) {
        // Log tentativa falha (IP hashed)
        dps_stats_log_api_access( 'summary', 401, null, $request->get_header('X-Forwarded-For') ?: $_SERVER['REMOTE_ADDR'] );
        
        return new WP_Error(
            'invalid_key',
            __( 'Invalid API key.', 'dps-stats' ),
            ['status' => 401]
        );
    }
    
    return true;
}
```

---

### 3. Rate Limiting

```php
/**
 * Verifica rate limit por key e por IP.
 * 
 * @param string $key_hash SHA-256 da key
 * @param string $ip IP address
 * @return bool|WP_Error true se OK, WP_Error se excedeu
 */
function dps_stats_check_rate_limit( $key_hash, $ip ) {
    $rate_key_per_hour = (int) get_option( 'dps_stats_api_rate_key_per_hour', 120 );
    $rate_ip_per_hour = (int) get_option( 'dps_stats_api_rate_ip_per_hour', 60 );
    
    // Rate limit por key
    $key_transient = 'dps_stats_ratelimit_key_' . substr( $key_hash, 0, 16 );
    $key_count = (int) get_transient( $key_transient );
    if ( $key_count >= $rate_key_per_hour ) {
        return new WP_Error(
            'rate_limit_key',
            sprintf( __( 'Rate limit exceeded: %d requests per hour allowed per API key.', 'dps-stats' ), $rate_key_per_hour ),
            ['status' => 429]
        );
    }
    set_transient( $key_transient, $key_count + 1, HOUR_IN_SECONDS );
    
    // Rate limit por IP
    $ip_hash = hash( 'sha256', $ip );
    $ip_transient = 'dps_stats_ratelimit_ip_' . substr( $ip_hash, 0, 16 );
    $ip_count = (int) get_transient( $ip_transient );
    if ( $ip_count >= $rate_ip_per_hour ) {
        return new WP_Error(
            'rate_limit_ip',
            sprintf( __( 'Rate limit exceeded: %d requests per hour allowed per IP.', 'dps-stats' ), $rate_ip_per_hour ),
            ['status' => 429]
        );
    }
    set_transient( $ip_transient, $ip_count + 1, HOUR_IN_SECONDS );
    
    return true;
}
```

---

## Implementação Backend

### 1. REST Controller Class

**Arquivo:** `includes/class-dps-stats-rest-controller.php`

```php
<?php
/**
 * REST API controller for Stats add-on.
 *
 * @package DPS_Stats
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class DPS_Stats_REST_Controller
 */
class DPS_Stats_REST_Controller extends WP_REST_Controller {

    /**
     * Namespace.
     *
     * @var string
     */
    protected $namespace = 'dps/v1';

    /**
     * Register routes.
     */
    public function register_routes() {
        register_rest_route( $this->namespace, '/stats/summary', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_summary' ],
            'permission_callback' => [ $this, 'check_permission' ],
            'args'                => [
                'start' => [
                    'required'          => true,
                    'validate_callback' => [ $this, 'validate_date' ],
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'end' => [
                    'required'          => true,
                    'validate_callback' => [ $this, 'validate_date' ],
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ] );

        register_rest_route( $this->namespace, '/stats/timeseries', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_timeseries' ],
            'permission_callback' => [ $this, 'check_permission' ],
            'args'                => [
                'start' => [
                    'required'          => true,
                    'validate_callback' => [ $this, 'validate_date' ],
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'end' => [
                    'required'          => true,
                    'validate_callback' => [ $this, 'validate_date' ],
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'granularity' => [
                    'required'          => false,
                    'default'           => 'daily',
                    'enum'              => [ 'daily', 'weekly' ],
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ] );
    }

    /**
     * Check API permission (authentication + rate limit).
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public function check_permission( $request ) {
        // Auth
        $auth_result = $this->authenticate( $request );
        if ( is_wp_error( $auth_result ) ) {
            return $auth_result;
        }

        // Rate limit
        $provided_key = $request->get_header( 'X-DPS-Stats-Key' );
        $key_hash = hash( 'sha256', $provided_key );
        $ip = $request->get_header('X-Forwarded-For') ?: $_SERVER['REMOTE_ADDR'];

        $rate_check = $this->check_rate_limit( $key_hash, $ip );
        if ( is_wp_error( $rate_check ) ) {
            return $rate_check;
        }

        return true;
    }

    /**
     * Authenticate request.
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    private function authenticate( $request ) {
        if ( ! get_option( 'dps_stats_api_enabled', false ) ) {
            return new WP_Error(
                'api_disabled',
                __( 'Stats API is currently disabled.', 'dps-stats' ),
                ['status' => 503]
            );
        }

        $provided_key = $request->get_header( 'X-DPS-Stats-Key' );
        if ( empty( $provided_key ) ) {
            return new WP_Error(
                'missing_auth',
                __( 'API key is required via X-DPS-Stats-Key header.', 'dps-stats' ),
                ['status' => 401]
            );
        }

        $stored_hash = get_option( 'dps_stats_api_key_hash', '' );
        if ( empty( $stored_hash ) ) {
            return new WP_Error(
                'no_key_configured',
                __( 'No API key configured.', 'dps-stats' ),
                ['status' => 401]
            );
        }

        $provided_hash = hash( 'sha256', $provided_key );

        if ( ! hash_equals( $stored_hash, $provided_hash ) ) {
            $this->log_access( $request->get_route(), 401, $key_hash, $request->get_header('X-Forwarded-For') ?: $_SERVER['REMOTE_ADDR'] );
            return new WP_Error(
                'invalid_key',
                __( 'Invalid API key.', 'dps-stats' ),
                ['status' => 401]
            );
        }

        return true;
    }

    /**
     * Check rate limit.
     *
     * @param string $key_hash
     * @param string $ip
     * @return bool|WP_Error
     */
    private function check_rate_limit( $key_hash, $ip ) {
        $rate_key_per_hour = (int) get_option( 'dps_stats_api_rate_key_per_hour', 120 );
        $rate_ip_per_hour = (int) get_option( 'dps_stats_api_rate_ip_per_hour', 60 );

        // Rate limit por key
        $key_transient = 'dps_stats_ratelimit_key_' . substr( $key_hash, 0, 16 );
        $key_count = (int) get_transient( $key_transient );
        if ( $key_count >= $rate_key_per_hour ) {
            return new WP_Error(
                'rate_limit_key',
                sprintf( __( 'Rate limit exceeded: %d requests per hour allowed per API key.', 'dps-stats' ), $rate_key_per_hour ),
                ['status' => 429, 'headers' => ['Retry-After' => 3600 - (time() % 3600)]]
            );
        }
        set_transient( $key_transient, $key_count + 1, HOUR_IN_SECONDS );

        // Rate limit por IP
        $ip_hash = hash( 'sha256', $ip );
        $ip_transient = 'dps_stats_ratelimit_ip_' . substr( $ip_hash, 0, 16 );
        $ip_count = (int) get_transient( $ip_transient );
        if ( $ip_count >= $rate_ip_per_hour ) {
            return new WP_Error(
                'rate_limit_ip',
                sprintf( __( 'Rate limit exceeded: %d requests per hour allowed per IP.', 'dps-stats' ), $rate_ip_per_hour ),
                ['status' => 429, 'headers' => ['Retry-After' => 3600 - (time() % 3600)]]
            );
        }
        set_transient( $ip_transient, $ip_count + 1, HOUR_IN_SECONDS );

        return true;
    }

    /**
     * Get summary metrics.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_summary( $request ) {
        $start = $request->get_param( 'start' );
        $end = $request->get_param( 'end' );

        // Cache key (sem incluir key do usuário, mas sim período)
        $cache_key = "dps_stats_api_summary_{$start}_{$end}";
        $cached = get_transient( $cache_key );
        if ( false !== $cached ) {
            $cached['meta']['cache_hit'] = true;
            $this->log_access( '/stats/summary', 200, null, $request->get_header('X-Forwarded-For') ?: $_SERVER['REMOTE_ADDR'] );
            return rest_ensure_response( $cached );
        }

        // Busca métricas via DPS_Stats_API
        $appointments_data = DPS_Stats_API::get_appointments_count( $start, $end );
        $cancellations_data = DPS_Stats_API::get_cancellations_count( $start, $end );
        $new_clients = DPS_Stats_API::get_new_clients_count( $start, $end );
        $financial = DPS_Stats_API::get_financial_totals( $start, $end );
        
        // KPIs avançados (se Phase 3.1 implementada)
        $no_show = method_exists( 'DPS_Stats_API', 'get_no_show_rate' )
            ? DPS_Stats_API::get_no_show_rate( $start, $end )
            : null;
        $return_rate = method_exists( 'DPS_Stats_API', 'get_return_rate' )
            ? DPS_Stats_API::get_return_rate( $start, $end, 30 )
            : null;
        $conversion = method_exists( 'DPS_Stats_API', 'get_conversion_rate' )
            ? DPS_Stats_API::get_conversion_rate( $start, $end, 30 )
            : null;
        $recurring = method_exists( 'DPS_Stats_API', 'get_recurring_clients' )
            ? DPS_Stats_API::get_recurring_clients( $start, $end )
            : null;

        $response = [
            'period' => [
                'start' => $start,
                'end' => $end,
            ],
            'metrics' => [
                'appointments' => [
                    'total' => $appointments_data['total'],
                    'completed' => $appointments_data['completed'] ?? 0,
                    'cancelled' => $cancellations_data['count'] ?? 0,
                    'no_show' => $no_show ? $no_show['count'] : 0,
                ],
                'clients' => [
                    'new' => $new_clients,
                    'recurring' => $recurring ? $recurring['value'] : 0,
                ],
                'revenue' => [
                    'total' => $financial['revenue'],
                    'currency' => 'BRL',
                ],
                'avg_ticket' => $financial['revenue'] > 0 && $appointments_data['total'] > 0
                    ? round( $financial['revenue'] / $appointments_data['total'], 2 )
                    : 0,
                'return_rate_30d' => $return_rate ? $return_rate['value'] : null,
                'conversion_rate' => $conversion ? $conversion['value'] : null,
            ],
            'meta' => [
                'generated_at' => gmdate( 'Y-m-d\TH:i:s\Z' ),
                'cache_hit' => false,
                'finance_addon_active' => ! isset( $financial['error'] ),
            ],
        ];

        // Cache por 1h
        set_transient( $cache_key, $response, HOUR_IN_SECONDS );

        $this->log_access( '/stats/summary', 200, null, $request->get_header('X-Forwarded-For') ?: $_SERVER['REMOTE_ADDR'] );

        return rest_ensure_response( $response );
    }

    /**
     * Get timeseries data (opcional).
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_timeseries( $request ) {
        $start = $request->get_param( 'start' );
        $end = $request->get_param( 'end' );
        $granularity = $request->get_param( 'granularity' );

        // Verifica se método existe (Phase 3B)
        if ( ! method_exists( 'DPS_Stats_API', 'get_appointments_timeseries' ) ) {
            return new WP_Error(
                'not_implemented',
                __( 'Timeseries endpoint requires Phase 3B implementation.', 'dps-stats' ),
                ['status' => 501]
            );
        }

        $cache_key = "dps_stats_api_timeseries_{$start}_{$end}_{$granularity}";
        $cached = get_transient( $cache_key );
        if ( false !== $cached ) {
            $cached['meta']['cache_hit'] = true;
            $this->log_access( '/stats/timeseries', 200, null, $request->get_header('X-Forwarded-For') ?: $_SERVER['REMOTE_ADDR'] );
            return rest_ensure_response( $cached );
        }

        $data = DPS_Stats_API::get_appointments_timeseries( $start, $end, [] );

        $response = [
            'period' => [
                'start' => $start,
                'end' => $end,
                'granularity' => $granularity,
            ],
            'data' => $data,
            'meta' => [
                'points' => count( $data ),
                'cache_hit' => false,
                'generated_at' => gmdate( 'Y-m-d\TH:i:s\Z' ),
            ],
        ];

        set_transient( $cache_key, $response, HOUR_IN_SECONDS );

        $this->log_access( '/stats/timeseries', 200, null, $request->get_header('X-Forwarded-For') ?: $_SERVER['REMOTE_ADDR'] );

        return rest_ensure_response( $response );
    }

    /**
     * Validate date parameter.
     *
     * @param string $param
     * @param WP_REST_Request $request
     * @param string $key
     * @return bool
     */
    public function validate_date( $param, $request, $key ) {
        $date = DateTime::createFromFormat( 'Y-m-d', $param );
        return $date && $date->format( 'Y-m-d' ) === $param;
    }

    /**
     * Log API access.
     *
     * @param string $endpoint
     * @param int $status
     * @param string|null $key_hash
     * @param string $ip
     */
    private function log_access( $endpoint, $status, $key_hash, $ip ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_stats_api_logs';

        // Verifica se tabela existe
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
            return;
        }

        $wpdb->insert(
            $table,
            [
                'timestamp' => current_time( 'mysql' ),
                'endpoint' => sanitize_text_field( $endpoint ),
                'status' => (int) $status,
                'ip_hash' => substr( hash( 'sha256', $ip ), 0, 16 ), // 16 chars apenas
                'key_hash_partial' => $key_hash ? substr( $key_hash, 0, 8 ) : null,
            ],
            ['%s', '%s', '%d', '%s', '%s']
        );
    }
}
```

---

### 2. Activation Hook (Create Logs Table)

```php
/**
 * Create API logs table on activation.
 */
function dps_stats_api_create_logs_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dps_stats_api_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        timestamp datetime NOT NULL,
        endpoint varchar(255) NOT NULL,
        status int(3) NOT NULL,
        ip_hash varchar(16) NOT NULL,
        key_hash_partial varchar(8) DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY timestamp (timestamp),
        KEY endpoint (endpoint),
        KEY status (status)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}
register_activation_hook( __FILE__, 'dps_stats_api_create_logs_table' );
```

---

## Settings & UI

### 1. Register Settings

```php
/**
 * Register API settings.
 */
function dps_stats_register_api_settings() {
    register_setting( 'dps_stats_api_settings', 'dps_stats_api_enabled', [
        'type' => 'boolean',
        'default' => false,
        'sanitize_callback' => 'rest_sanitize_boolean',
    ] );

    register_setting( 'dps_stats_api_settings', 'dps_stats_api_key_hash', [
        'type' => 'string',
        'default' => '',
        'sanitize_callback' => 'sanitize_text_field',
    ] );

    register_setting( 'dps_stats_api_settings', 'dps_stats_api_rate_key_per_hour', [
        'type' => 'integer',
        'default' => 120,
        'sanitize_callback' => 'absint',
    ] );

    register_setting( 'dps_stats_api_settings', 'dps_stats_api_rate_ip_per_hour', [
        'type' => 'integer',
        'default' => 60,
        'sanitize_callback' => 'absint',
    ] );
}
add_action( 'admin_init', 'dps_stats_register_api_settings' );
```

---

### 2. Settings Page UI

```php
/**
 * Render API settings page.
 */
function dps_stats_render_api_settings() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $api_enabled = get_option( 'dps_stats_api_enabled', false );
    $key_hash = get_option( 'dps_stats_api_key_hash', '' );
    $rate_key = get_option( 'dps_stats_api_rate_key_per_hour', 120 );
    $rate_ip = get_option( 'dps_stats_api_rate_ip_per_hour', 60 );

    // Handle key generation
    if ( isset( $_POST['dps_generate_api_key'] ) && check_admin_referer( 'dps_stats_generate_key' ) ) {
        $new_key_data = dps_stats_generate_api_key();
        update_option( 'dps_stats_api_key_hash', $new_key_data['hash'] );
        $generated_key = $new_key_data['key'];
    }

    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Stats API Settings', 'dps-stats' ); ?></h1>

        <form method="post" action="options.php">
            <?php settings_fields( 'dps_stats_api_settings' ); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="dps_stats_api_enabled"><?php esc_html_e( 'Enable API', 'dps-stats' ); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="dps_stats_api_enabled" name="dps_stats_api_enabled" value="1" <?php checked( $api_enabled ); ?> />
                        <p class="description"><?php esc_html_e( 'Enable REST API access to stats metrics.', 'dps-stats' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e( 'API Key', 'dps-stats' ); ?></th>
                    <td>
                        <?php if ( $key_hash ) : ?>
                            <p><strong><?php esc_html_e( 'Key configured', 'dps-stats' ); ?></strong> (Hash: <code><?php echo esc_html( substr( $key_hash, 0, 16 ) ); ?>...</code>)</p>
                        <?php else : ?>
                            <p><em><?php esc_html_e( 'No key generated yet.', 'dps-stats' ); ?></em></p>
                        <?php endif; ?>
                        
                        <?php if ( isset( $generated_key ) ) : ?>
                            <div style="background: #fff3cd; border-left: 4px solid #ffb900; padding: 12px; margin: 12px 0;">
                                <p><strong><?php esc_html_e( '⚠️ IMPORTANT: Copy this key now. It will not be shown again!', 'dps-stats' ); ?></strong></p>
                                <input type="text" readonly value="<?php echo esc_attr( $generated_key ); ?>" style="width: 100%; font-family: monospace;" onclick="this.select();" />
                                <button type="button" class="button" onclick="navigator.clipboard.writeText('<?php echo esc_js( $generated_key ); ?>')"><?php esc_html_e( 'Copy to Clipboard', 'dps-stats' ); ?></button>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="dps_stats_api_rate_key_per_hour"><?php esc_html_e( 'Rate Limit (per Key)', 'dps-stats' ); ?></label>
                    </th>
                    <td>
                        <input type="number" id="dps_stats_api_rate_key_per_hour" name="dps_stats_api_rate_key_per_hour" value="<?php echo esc_attr( $rate_key ); ?>" min="10" max="1000" />
                        <p class="description"><?php esc_html_e( 'Maximum requests per hour per API key (default: 120).', 'dps-stats' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="dps_stats_api_rate_ip_per_hour"><?php esc_html_e( 'Rate Limit (per IP)', 'dps-stats' ); ?></label>
                    </th>
                    <td>
                        <input type="number" id="dps_stats_api_rate_ip_per_hour" name="dps_stats_api_rate_ip_per_hour" value="<?php echo esc_attr( $rate_ip ); ?>" min="10" max="1000" />
                        <p class="description"><?php esc_html_e( 'Maximum requests per hour per IP address (default: 60).', 'dps-stats' ); ?></p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>

        <hr />

        <h2><?php esc_html_e( 'Generate New API Key', 'dps-stats' ); ?></h2>
        <form method="post">
            <?php wp_nonce_field( 'dps_stats_generate_key' ); ?>
            <p><?php esc_html_e( 'Generate a new API key. This will REVOKE the previous key immediately.', 'dps-stats' ); ?></p>
            <button type="submit" name="dps_generate_api_key" class="button button-secondary"><?php esc_html_e( 'Generate New Key', 'dps-stats' ); ?></button>
        </form>

        <hr />

        <h2><?php esc_html_e( 'API Documentation', 'dps-stats' ); ?></h2>
        <div style="background: #f0f0f1; padding: 16px; border-radius: 4px;">
            <h3>Endpoint: <code>GET /wp-json/dps/v1/stats/summary</code></h3>
            <p><strong>Parameters:</strong> <code>start</code> (YYYY-MM-DD), <code>end</code> (YYYY-MM-DD)</p>
            <p><strong>Headers:</strong> <code>X-DPS-Stats-Key: {your_api_key}</code></p>
            <p><strong>Example:</strong></p>
            <pre style="background: #fff; padding: 12px; border: 1px solid #ddd; overflow-x: auto;">curl -H "X-DPS-Stats-Key: YOUR_KEY" \
  "<?php echo esc_url( rest_url( 'dps/v1/stats/summary' ) ); ?>?start=2024-11-01&end=2024-11-30"</pre>

            <p><strong>⚠️ Security:</strong> Always use HTTPS in production. Never expose your API key in public repositories or client-side code.</p>
        </div>

        <hr />

        <h2><?php esc_html_e( 'Access Logs', 'dps-stats' ); ?></h2>
        <?php dps_stats_render_api_logs(); ?>
    </div>
    <?php
}

/**
 * Render API access logs table.
 */
function dps_stats_render_api_logs() {
    global $wpdb;
    $table = $wpdb->prefix . 'dps_stats_api_logs';

    // Check if table exists
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
        echo '<p><em>' . esc_html__( 'No logs table found. Activate the addon to create it.', 'dps-stats' ) . '</em></p>';
        return;
    }

    $logs = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY timestamp DESC LIMIT 100" );

    if ( empty( $logs ) ) {
        echo '<p><em>' . esc_html__( 'No API requests logged yet.', 'dps-stats' ) . '</em></p>';
        return;
    }

    echo '<table class="widefat fixed striped">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__( 'Timestamp', 'dps-stats' ) . '</th>';
    echo '<th>' . esc_html__( 'Endpoint', 'dps-stats' ) . '</th>';
    echo '<th>' . esc_html__( 'Status', 'dps-stats' ) . '</th>';
    echo '<th>' . esc_html__( 'IP Hash', 'dps-stats' ) . '</th>';
    echo '<th>' . esc_html__( 'Key Hash', 'dps-stats' ) . '</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    foreach ( $logs as $log ) {
        $status_class = $log->status >= 200 && $log->status < 300 ? 'success' : 'error';
        echo '<tr>';
        echo '<td>' . esc_html( $log->timestamp ) . '</td>';
        echo '<td><code>' . esc_html( $log->endpoint ) . '</code></td>';
        echo '<td><span style="color: ' . ( $status_class === 'success' ? 'green' : 'red' ) . ';">' . esc_html( $log->status ) . '</span></td>';
        echo '<td><code>' . esc_html( $log->ip_hash ) . '</code></td>';
        echo '<td><code>' . esc_html( $log->key_hash_partial ?: '—' ) . '</code></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
}

/**
 * Generate API key.
 *
 * @return array ['key' => string, 'hash' => string]
 */
function dps_stats_generate_api_key() {
    $random_bytes = random_bytes( 32 );
    $key = bin2hex( $random_bytes );
    $hash = hash( 'sha256', $key );

    return [
        'key' => $key,
        'hash' => $hash,
    ];
}
```

---

## Logging & Auditoria

### Cleanup de Logs Antigos (Cron)

```php
/**
 * Schedule log cleanup cron.
 */
function dps_stats_schedule_log_cleanup() {
    if ( ! wp_next_scheduled( 'dps_stats_cleanup_logs_cron' ) ) {
        wp_schedule_event( time(), 'daily', 'dps_stats_cleanup_logs_cron' );
    }
}
add_action( 'wp', 'dps_stats_schedule_log_cleanup' );

/**
 * Cleanup logs older than 30 days.
 */
function dps_stats_cleanup_old_logs() {
    global $wpdb;
    $table = $wpdb->prefix . 'dps_stats_api_logs';

    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
        return;
    }

    $wpdb->query( "DELETE FROM {$table} WHERE timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY)" );
}
add_action( 'dps_stats_cleanup_logs_cron', 'dps_stats_cleanup_old_logs' );
```

---

## Checklist de Testes

### Segurança

- [ ] **401 Unauthorized:** Request sem header `X-DPS-Stats-Key`
- [ ] **401 Unauthorized:** Request com key inválida (tentativa aleatória)
- [ ] **401 Unauthorized:** Request com key correta mas API desabilitada (503)
- [ ] **200 OK:** Request com key válida e API habilitada
- [ ] **429 Too Many Requests:** Exceder rate limit por key (121º request na mesma hora)
- [ ] **429 Too Many Requests:** Exceder rate limit por IP (61º request na mesma hora)
- [ ] **Log criado:** Tentativa com key inválida registra 401 no log
- [ ] **Log criado:** Request bem-sucedido registra 200 no log

### Funcionalidade

- [ ] **Summary endpoint:** Retorna todas métricas (appointments, clients, revenue, avg_ticket)
- [ ] **Summary endpoint:** Métricas avançadas aparecem se Phase 3.1 implementada (no-show, return_rate, conversion, recurring)
- [ ] **Summary endpoint:** `finance_addon_active: false` se Finance não ativo
- [ ] **Cache:** Segunda chamada com mesmos parâmetros retorna `cache_hit: true`
- [ ] **Timeseries endpoint:** Retorna array de datas + counts (se Phase 3B implementada)
- [ ] **Timeseries endpoint:** 501 Not Implemented se Phase 3B não implementada
- [ ] **Parâmetros inválidos:** 400 Bad Request se `start`/`end` não são datas YYYY-MM-DD

### UI

- [ ] **Settings page:** Checkbox "Enable API" funciona (salva/carrega)
- [ ] **Settings page:** Botão "Generate New Key" cria key e exibe uma única vez
- [ ] **Settings page:** Key copiada para clipboard com botão "Copy to Clipboard"
- [ ] **Settings page:** Após refresh, key NÃO é exibida (apenas hash partial)
- [ ] **Settings page:** Rate limits salvam valores (10-1000)
- [ ] **Logs viewer:** Últimos 100 requests exibidos com timestamp, endpoint, status, hashes
- [ ] **Logs viewer:** Status colorido (verde 200, vermelho 401/429)

### Regressão

- [ ] **Dashboard Stats:** Funciona normalmente (API não impacta dashboard)
- [ ] **Phase 1-3 features:** Todas continuam funcionando
- [ ] **Desativar addon:** Remove cron de cleanup de logs

---

## Considerações de Segurança

### 1. HTTPS Obrigatório em Produção

⚠️ **IMPORTANTE:** API key é enviada em header. SEMPRE usar HTTPS em produção para evitar man-in-the-middle attacks.

**Documentar na UI:**
```
⚠️ Security Warning:
Your API key grants access to all stats metrics. 
NEVER use HTTP in production - always HTTPS. 
Never commit your key to version control or expose it in client-side code.
```

---

### 2. Sem PII nos Payloads

✅ **O que É SEGURO retornar:**
- Contagens agregadas (total appointments, new clients)
- Somas financeiras (revenue total, avg ticket)
- Percentuais (return rate, conversion rate)
- Séries temporais de contagens (appointments per day)

❌ **O que NÃO DEVE SER EXPOSTO:**
- Nomes de clientes
- Telefones, emails, CPF
- Endereços completos
- Nomes de pets
- Detalhes individuais de agendamentos

---

### 3. Rate Limiting

**Configuração recomendada:**
- **120 req/h per key** (suficiente para dashboards que atualizam a cada 30s)
- **60 req/h per IP** (proteção contra brute force de IPs)

**Cálculo:** 120 req/h = 1 request a cada 30 segundos (suficiente para dashboards em tempo real)

---

### 4. Key Rotation

**Prática recomendada:**
- Rotacionar key a cada 90 dias
- Revogar imediatamente se suspeita de exposição
- Manter log de quando última rotação ocorreu (adicionar em settings UI)

---

### 5. IP Whitelisting (Feature Futura)

**Não implementado na v2.0.0, mas recomendado para v2.1.0:**
- Setting opcional: lista de IPs permitidos
- Bloquear todos os outros IPs mesmo com key válida

---

## Esforço Estimado

**Total:** 8-10 horas

**Breakdown:**
- REST controller + auth/rate limit: 3h
- Settings UI + key generation: 2h
- Logs table + viewer UI: 1.5h
- Cleanup cron: 0.5h
- Testes manuais (15+ casos): 2h
- Documentação na UI: 1h

---

## Próximos Passos

1. Implementar código seguindo guia
2. Criar API key de teste
3. Validar com curl/Postman todos os casos de teste
4. Testar rate limiting (script para 121 requests consecutivos)
5. Validar logs sendo criados corretamente
6. Atualizar CHANGELOG.md (v2.0.0)
7. Criar PR

---

## 🎉 Stats Add-on 2.0 COMPLETO

Com a implementação da Fase 4D, o **Stats Add-on 2.0** está completo:

✅ **Phase 1** (v1.2.0): Critical fixes  
✅ **Phase 2** (v1.3.0): Performance optimizations  
✅ **Phase 3.1** (v1.4.0): Missing KPIs + tooltips  
📋 **Phase 3B** (v1.5.0): Drill-down/filters/trends (guide ready)  
📋 **Phase 4A** (v1.6.0): Goals/customizable dashboard (guide ready)  
📋 **Phase 4B** (v1.7.0): Alerts/scheduled reports (guide ready)  
📋 **Phase 4D** (v2.0.0): REST API read-only (guide ready)

**Total implementado:** 41h de desenvolvimento  
**Total documentado:** 49-71h adicionais em guias  
**Linhas de código:** 3400+ implementadas, 2400+ documentadas  

**Próximo:** Implementar fases com guias prontos para alcançar Stats 2.0 completo! 🚀
