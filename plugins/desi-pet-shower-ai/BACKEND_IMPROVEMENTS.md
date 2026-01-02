# Backend Improvements - AI Add-on v1.6.1

**Data:** 07/12/2024  
**Versão:** 1.6.1  
**Autor:** PRObst

Este documento detalha as melhorias de backend implementadas no AI Add-on v1.6.1, focando em manutenibilidade, robustez e escalabilidade.

---

## Índice

1. [Visão Geral](#visão-geral)
2. [Limpeza Automática de Dados](#limpeza-automática-de-dados)
3. [Tratamento Robusto de Erros HTTP](#tratamento-robusto-de-erros-http)
4. [Logger Condicional](#logger-condicional)
5. [Configuração](#configuração)
6. [API para Desenvolvedores](#api-para-desenvolvedores)
7. [Troubleshooting](#troubleshooting)

---

## Visão Geral

As melhorias implementadas em v1.6.1 resolvem três problemas principais identificados na análise de código:

1. **Crescimento indefinido do banco de dados** - Métricas e feedback acumulavam sem limite
2. **Logs poluídos em produção** - Todos os logs eram registrados independente do ambiente
3. **Tratamento básico de erros HTTP** - Falhas de API não eram adequadamente tratadas

### Arquivos Criados

- `includes/dps-ai-logger.php` - Sistema de logging condicional
- `includes/class-dps-ai-maintenance.php` - Rotinas de limpeza automática

### Arquivos Modificados

- `desi-pet-shower-ai-addon.php` - Carregamento de novos componentes, settings UI, versão
- `includes/class-dps-ai-client.php` - Tratamento robusto de erros HTTP
- `includes/class-dps-ai-message-assistant.php` - Substituição de error_log por logger

---

## Limpeza Automática de Dados

### Problema Resolvido

**ANTES v1.6.1:**
- Tabela `dps_ai_metrics` crescia indefinidamente
- Tabela `dps_ai_feedback` acumulava todos os registros históricos
- Transients expirados permaneciam no banco de dados

**DEPOIS v1.6.1:**
- Limpeza automática diária via WP-Cron
- Período de retenção configurável (padrão: 365 dias)
- Limpeza manual disponível na interface admin

### Como Funciona

#### 1. Agendamento WP-Cron

O evento `dps_ai_daily_cleanup` é agendado para rodar diariamente às 03:00 (horário do servidor).

```php
// Evento é registrado automaticamente na inicialização
DPS_AI_Maintenance::get_instance();

// Agenda é criado em init hook
wp_schedule_event( strtotime( 'tomorrow 03:00' ), 'daily', 'dps_ai_daily_cleanup' );
```

#### 2. Rotinas de Limpeza

**Deletar Métricas Antigas:**
```sql
DELETE FROM wp_dps_ai_metrics 
WHERE date < (CURDATE() - INTERVAL 365 DAY)
```

**Deletar Feedback Antigo:**
```sql
DELETE FROM wp_dps_ai_feedback 
WHERE created_at < (NOW() - INTERVAL 365 DAY)
```

**Deletar Transients Expirados:**
```php
// Busca transients com prefixo 'dps_ai' que já expiraram
// Remove usando delete_transient()
```

#### 3. Limpeza Manual

Disponível na página **Assistente de IA** > **Manutenção e Logs**:

1. Clique em "Executar Limpeza Agora"
2. Confirme a ação (não reversível)
3. Sistema executa todas as rotinas e exibe resultado
4. Página recarrega automaticamente para atualizar estatísticas

### Configuração

**Período de Retenção:**

- **Padrão:** 365 dias (1 ano)
- **Mínimo:** 30 dias
- **Máximo:** 3650 dias (10 anos)

**Como ajustar:**

1. Acesse **Assistente de IA** > **Configurações**
2. Role até seção "Manutenção e Logs"
3. Altere "Período de Retenção de Dados"
4. Salve as configurações

**Desabilitar limpeza automática:**

Não há opção na interface. Para desabilitar via código:

```php
// Em functions.php do tema ou plugin custom
add_action( 'init', function() {
    $timestamp = wp_next_scheduled( 'dps_ai_daily_cleanup' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'dps_ai_daily_cleanup' );
    }
}, 99 );
```

### Estatísticas de Armazenamento

A página de configurações exibe:

- **Número de métricas armazenadas**
- **Número de feedbacks armazenados**
- **Data do registro mais antigo (métricas)**
- **Data do registro mais antigo (feedback)**

Exemplo:
> Atualmente: **1.234 métricas**, **567 feedbacks**. Dados mais antigos: **15/01/2023** (métricas), **20/02/2023** (feedback).

---

## Tratamento Robusto de Erros HTTP

### Problema Resolvido

**ANTES v1.6.1:**
- Erros genéricos sem contexto técnico
- Códigos HTTP de erro não diferenciados
- Exceções inesperadas não capturadas
- Logs sem informações de diagnóstico

**DEPOIS v1.6.1:**
- Tratamento específico por tipo de erro
- Try/catch para exceções inesperadas
- Logs contextualizados com métricas
- Validações de estrutura de resposta

### Códigos HTTP Tratados

| Código | Tipo de Erro | Ação |
|--------|--------------|------|
| 400 | Bad Request | Log erro + retorna null |
| 401 | Unauthorized | Log erro (API key inválida) + retorna null |
| 429 | Too Many Requests | Log warning (rate limit) + retorna null |
| 500 | Internal Server Error | Log warning (servidor OpenAI) + retorna null |
| 502 | Bad Gateway | Log warning (servidor OpenAI) + retorna null |
| 503 | Service Unavailable | Log warning (servidor OpenAI) + retorna null |
| Outros | Erro genérico | Log erro + retorna null |

### Validações Implementadas

1. **API key configurada** - Verifica antes de fazer requisição
2. **Array de mensagens válido** - Não vazio e é array
3. **Resposta não vazia** - Body tem conteúdo
4. **JSON válido** - Decodifica e valida json_last_error()
5. **Estrutura esperada** - Verifica `choices[0]['message']['content']`

### Logs Contextualizados

Cada tipo de erro registra contexto relevante:

**Exemplo - Timeout:**
```
[DPS AI ERROR] Falha na requisição HTTP para API da OpenAI | Context: {"error":"cURL error 28: Timeout was reached","timeout":10,"response_time":10.02}
```

**Exemplo - Rate Limit:**
```
[DPS AI WARNING] Rate limit excedido na API da OpenAI (Too Many Requests) | Context: {"status":429,"api_error":"Rate limit exceeded","response_time":0.15}
```

**Exemplo - Sucesso (apenas em debug):**
```
[DPS AI DEBUG] Resposta recebida com sucesso da API da OpenAI | Context: {"response_time":1.23,"tokens_used":456}
```

### Try/Catch

Qualquer exceção inesperada é capturada e logada:

```php
try {
    // Lógica de requisição HTTP
} catch ( Exception $e ) {
    dps_ai_log_error( 'Exceção inesperada ao chamar API da OpenAI', [
        'exception' => $e->getMessage(),
        'file'      => $e->getFile(),
        'line'      => $e->getLine(),
    ] );
    return null;
}
```

---

## Logger Condicional

### Problema Resolvido

**ANTES v1.6.1:**
- Todos os `error_log()` sempre executavam
- Logs de debug poluíam arquivos em produção
- Sem controle de verbosidade

**DEPOIS v1.6.1:**
- Logs respeitam `WP_DEBUG` e configuração do plugin
- Níveis de log (debug, info, warning, error)
- Em produção, apenas erros críticos são registrados

### Níveis de Log

| Nível | Quando Registra | Uso Recomendado |
|-------|----------------|-----------------|
| **debug** | WP_DEBUG=true OU debug_logging=true | Rastreamento detalhado durante desenvolvimento |
| **info** | WP_DEBUG=true OU debug_logging=true | Eventos normais do sistema (ex: "Limpeza concluída") |
| **warning** | WP_DEBUG=true OU debug_logging=true | Situações anormais não críticas (ex: rate limit) |
| **error** | Sempre | Falhas críticas que requerem atenção |

### Funções Disponíveis

```php
// Função genérica
dps_ai_log( string $message, string $level = 'info', array $context = [] )

// Helpers específicos
dps_ai_log_debug( string $message, array $context = [] )
dps_ai_log_info( string $message, array $context = [] )
dps_ai_log_warning( string $message, array $context = [] )
dps_ai_log_error( string $message, array $context = [] )
```

### Exemplos de Uso

**Log de Erro (sempre registrado):**
```php
if ( empty( $api_key ) ) {
    dps_ai_log_error( 'API key da OpenAI não configurada' );
    return null;
}
```

**Log de Warning (apenas com debug):**
```php
if ( 429 === $status_code ) {
    dps_ai_log_warning( 'Rate limit excedido na API da OpenAI', [
        'status' => $status_code,
        'api_error' => $api_error,
    ] );
}
```

**Log de Info (apenas com debug):**
```php
dps_ai_log_info( 'Limpeza automática concluída', [
    'metrics_deleted' => 150,
    'feedback_deleted' => 75,
    'transients_deleted' => 23,
] );
```

**Log de Debug (apenas com debug):**
```php
dps_ai_log_debug( 'Enviando requisição para API da OpenAI', [
    'model' => 'gpt-4o-mini',
    'msg_count' => 5,
    'max_tokens' => 500,
] );
```

### Formato de Saída

```
[DPS AI LEVEL] Mensagem | Context: {"key":"value","key2":"value2"}
```

**Exemplo real:**
```
[DPS AI INFO] Limpeza automática concluída | Context: {"metrics_deleted":150,"feedback_deleted":75,"transients_deleted":23}
```

---

## Configuração

### Página de Settings

Acesse **Assistente de IA** > **Configurações**, role até **Manutenção e Logs**.

#### Período de Retenção de Dados

- **Campo:** Input numérico
- **Padrão:** 365 dias
- **Min/Max:** 30-3650 dias
- **Efeito:** Define quantos dias de dados manter antes de deletar automaticamente

#### Habilitar Logs Detalhados

- **Campo:** Checkbox
- **Padrão:** Desabilitado
- **Efeito:** Quando marcado, registra logs de debug/info/warning mesmo em produção
- **Aviso:** Exibe alerta se `WP_DEBUG` está habilitado

#### Limpeza Manual

- **Campo:** Botão "Executar Limpeza Agora"
- **Efeito:** Executa todas as rotinas de limpeza imediatamente
- **Resultado:** Exibe mensagem de sucesso com contadores de itens deletados

### Via Código

**Alterar período de retenção:**
```php
$settings = get_option( 'dps_ai_settings', [] );
$settings['data_retention_days'] = 180; // 6 meses
update_option( 'dps_ai_settings', $settings );
```

**Habilitar logging detalhado:**
```php
$settings = get_option( 'dps_ai_settings', [] );
$settings['debug_logging'] = true;
update_option( 'dps_ai_settings', $settings );
```

**Executar limpeza programaticamente:**
```php
if ( class_exists( 'DPS_AI_Maintenance' ) ) {
    $results = DPS_AI_Maintenance::get_instance()->run_cleanup();
    // $results = ['metrics_deleted' => 150, 'feedback_deleted' => 75, ...]
}
```

---

## API para Desenvolvedores

### Hooks Disponíveis

**Modificar período de retenção dinamicamente:**
```php
add_filter( 'dps_ai_data_retention_days', function( $days ) {
    // Exemplo: manter dados por 2 anos em ambiente staging
    if ( defined( 'WP_ENV' ) && 'staging' === WP_ENV ) {
        return 730;
    }
    return $days;
} );
```

**Executar ação após limpeza:**
```php
add_action( 'dps_ai_daily_cleanup', function() {
    // Roda APÓS a limpeza automática
    dps_ai_log_info( 'Limpeza customizada executada' );
}, 20 ); // Prioridade > 10 para rodar depois
```

**Desabilitar limpeza de transients:**
```php
add_filter( 'dps_ai_cleanup_transients', '__return_false' );
```

### Métodos Públicos

#### DPS_AI_Maintenance

```php
// Executar limpeza completa
$results = DPS_AI_Maintenance::get_instance()->run_cleanup();
// Retorna: ['metrics_deleted' => int, 'feedback_deleted' => int, 'transients_deleted' => int]

// Limpar apenas métricas
$deleted = DPS_AI_Maintenance::get_instance()->cleanup_old_metrics( 365 );

// Limpar apenas feedback
$deleted = DPS_AI_Maintenance::get_instance()->cleanup_old_feedback( 365 );

// Limpar apenas transients
$deleted = DPS_AI_Maintenance::get_instance()->cleanup_expired_transients();

// Obter estatísticas de armazenamento
$stats = DPS_AI_Maintenance::get_storage_stats();
// Retorna: ['metrics_count' => int, 'feedback_count' => int, 'oldest_metric' => 'dd/mm/YYYY', 'oldest_feedback' => 'dd/mm/YYYY HH:MM']

// Desagendar cron job
DPS_AI_Maintenance::unschedule_cleanup();
```

#### Logger

```php
// Log genérico com nível e contexto
dps_ai_log( 'Minha mensagem', 'info', ['key' => 'value'] );

// Helpers específicos
dps_ai_log_debug( 'Debug info', ['data' => $debug_data] );
dps_ai_log_info( 'Operação concluída' );
dps_ai_log_warning( 'Situação anormal', ['context' => $ctx] );
dps_ai_log_error( 'Erro crítico', ['error' => $e->getMessage()] );
```

---

## Troubleshooting

### Limpeza Automática Não Está Rodando

**Verificar se o evento está agendado:**
```php
$timestamp = wp_next_scheduled( 'dps_ai_daily_cleanup' );
if ( $timestamp ) {
    echo 'Próxima execução: ' . date( 'd/m/Y H:i:s', $timestamp );
} else {
    echo 'Evento não agendado!';
}
```

**Re-agendar manualmente:**
```php
// Desagendar se existir
$timestamp = wp_next_scheduled( 'dps_ai_daily_cleanup' );
if ( $timestamp ) {
    wp_unschedule_event( $timestamp, 'dps_ai_daily_cleanup' );
}

// Agendar novamente
wp_schedule_event( strtotime( 'tomorrow 03:00' ), 'daily', 'dps_ai_daily_cleanup' );
```

**Verificar se WP-Cron está funcionando:**

O WordPress depende de visitas ao site para executar cron jobs. Em sites de baixo tráfego, considere usar cron real:

```bash
# Adicionar ao crontab do servidor
*/15 * * * * curl -s https://seusite.com.br/wp-cron.php?doing_wp_cron > /dev/null 2>&1
```

E desabilitar WP-Cron no `wp-config.php`:
```php
define( 'DISABLE_WP_CRON', true );
```

### Logs Não Aparecem

**Verificar configurações:**
```php
$settings = get_option( 'dps_ai_settings', [] );
echo 'Debug logging: ' . ( ! empty( $settings['debug_logging'] ) ? 'HABILITADO' : 'DESABILITADO' ) . "\n";
echo 'WP_DEBUG: ' . ( defined( 'WP_DEBUG' ) && WP_DEBUG ? 'HABILITADO' : 'DESABILITADO' ) . "\n";
```

**Verificar arquivo de log:**

Por padrão, logs vão para `wp-content/debug.log` se `WP_DEBUG_LOG` está habilitado:

```php
// Em wp-config.php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false ); // Não exibir na tela
```

### Erro HTTP Não É Logado

Apenas erros são sempre logados. Warnings e info requerem debug habilitado.

**Forçar logging de warnings/info:**
```php
// Temporariamente em wp-config.php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

Ou habilitar "Logs Detalhados" nas configurações do plugin.

### Banco de Dados Cresceu Muito

**Executar limpeza imediata:**

1. Vá em **Assistente de IA** > **Configurações**
2. Role até "Manutenção e Logs"
3. Clique em "Executar Limpeza Agora"

**Reduzir período de retenção:**

1. Altere "Período de Retenção de Dados" para 90 ou 180 dias
2. Salve as configurações
3. Execute limpeza manual
4. Próxima limpeza automática manterá o novo período

**Verificar tamanho das tabelas:**
```sql
SELECT 
    table_name AS 'Tabela',
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Tamanho (MB)',
    table_rows AS 'Registros'
FROM information_schema.TABLES 
WHERE table_schema = 'nome_do_banco'
AND table_name LIKE 'wp_dps_ai%';
```

---

## Resumo de Benefícios

### Para Administradores

✅ **Banco de dados não cresce indefinidamente** - Limpeza automática mantém tamanho sob controle  
✅ **Logs organizados** - Apenas erros críticos em produção, debug quando necessário  
✅ **Interface simples** - Configuração via settings do WordPress, sem código  
✅ **Estatísticas visíveis** - Veja volume de dados armazenado antes de limpar  

### Para Desenvolvedores

✅ **Logging estruturado** - Níveis de log (debug/info/warning/error) + contexto JSON  
✅ **Tratamento robusto de erros** - Códigos HTTP específicos, validações de resposta  
✅ **API extensível** - Hooks e métodos públicos para customizações  
✅ **Manutenção automatizada** - WP-Cron cuida da limpeza sem intervenção manual  

### Para o Sistema

✅ **Escalabilidade** - Dados antigos removidos automaticamente  
✅ **Performance** - Menos registros = queries mais rápidas  
✅ **Diagnóstico facilitado** - Logs contextualizados ajudam a identificar problemas  
✅ **Produção limpa** - Sem poluição de logs desnecessários  

---

**Dúvidas ou problemas?** Abra uma issue no repositório ou consulte a equipe de desenvolvimento.
