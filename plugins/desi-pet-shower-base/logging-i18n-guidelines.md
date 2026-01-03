# Diretrizes de Logging e i18n do desi.pet by PRObst

## Logging centralizado
- Classe: `DPS_Logger` (`includes/class-dps-logger.php`). Níveis: `info`, `warning`, `error`.
- Tabela padrão: `wp_prefix_dps_logs` (campos: `id`, `date_time` (UTC), `level`, `source`, `message`, `context`). Índices em `level`, `source`, `date_time`.
- Fallback: caso a inserção no banco falhe, grava em `wp-content/uploads/dps-logs/dps.log`.
- Nível mínimo configurável: option `dps_logger_min_level` (valor padrão `info`).
- Evite logs excessivos em produção; use `warning`/`error` apenas para eventos relevantes.
- Padrão de idioma: mensagens de log podem ser em inglês técnico; priorize clareza para suporte.

### Exemplos de uso
```php
// Falha de pagamento
DPS_Logger::error(
    'Payment gateway request failed',
    array(
        'charge_id' => $charge_id,
        'endpoint'  => $endpoint,
    ),
    'payment'
);

// Importação de backup com erro
DPS_Logger::error(
    'Backup import aborted: checksum mismatch',
    array(
        'file'      => $file_name,
        'expected'  => $expected_hash,
        'received'  => $received_hash,
    ),
    'base'
);

// Assinaturas: exceção inesperada
DPS_Logger::warning(
    'Subscription renewal skipped because of missing token',
    array( 'subscription_id' => $subscription_id ),
    'subscription'
);
```

### UI de logs (WP-Admin)
- Página **Logs DPS** adicionada ao menu principal.
- Filtros por nível (`info`, `warning`, `error`) e `source` (ex.: `base`, `finance`, `payment`).
- Paginação (20 registros/página) e botão para excluir logs mais antigos que *N* dias (padrão 30).

## Internacionalização (i18n)
- Text domain único: `desi-pet-shower` para todo o ecossistema.
- Carregamento: `dps_load_textdomain()` em `plugins_loaded` chama `load_plugin_textdomain( 'desi-pet-shower', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );`.
- Toda string exibida deve usar funções de i18n + escape conforme o contexto: `__( 'Texto', 'desi-pet-shower' )`, `esc_html__( 'Texto', 'desi-pet-shower' )`, `esc_attr__()`, etc.
- Não concatene partes traduzíveis; prefira placeholders e `sprintf`:
  ```php
  sprintf( __( 'Cliente %s criado com sucesso.', 'desi-pet-shower' ), $client_name );
  ```
- Use `_n()` para pluralização e evite strings que dependam de gênero/número embutidos.
- Mensagens exibidas ao usuário final devem sempre usar i18n; logs voltados a desenvolvedores podem permanecer em inglês técnico sem i18n.
- Escape de saída continua obrigatório mesmo com i18n (HTML, atributos, URLs).
