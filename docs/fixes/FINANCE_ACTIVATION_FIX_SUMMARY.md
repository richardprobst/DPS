# Finance Add-on - Correção do Activation Hook

## Resumo

Este documento mostra as alterações implementadas para corrigir a forma como o Finance Add-on cria tabelas e registra a ativação, conforme solicitado.

## Problema Original

Segundo o `ADDONS_DETAILED_ANALYSIS.md`:
- O método `activate()` existia mas **NÃO** estava registrado no `register_activation_hook()`
- Criação de tabelas estava pendurada no hook `init` (linhas 97, 103), executando `dbDelta()` em **todo request**
- Página de documentos financeiros não era criada automaticamente

## Mudanças Implementadas

### 1. REGISTRATION DO ACTIVATION HOOK

**Adicionado ao final do arquivo** `desi-pet-shower-finance-addon.php`:

```php
// Registra o hook de ativação do plugin
register_activation_hook( __FILE__, [ 'DPS_Finance_Addon', 'activate' ] );

// Instancia a classe somente se ainda não houver uma instância global
if ( class_exists( 'DPS_Finance_Addon' ) && ! isset( $GLOBALS['dps_finance_addon'] ) ) {
    $GLOBALS['dps_finance_addon'] = new DPS_Finance_Addon();
}
```

### 2. MÉTODO activate() REFATORADO

**Antes** (linhas 115-137):
```php
/**
 * Executado na ativação do add‑on financeiro. Garante que exista uma página para listar os
 * documentos (notas e cobranças) gerados. A página recebe o shortcode [dps_fin_docs].
 */
public function activate() {
    $title = __( 'Documentos Financeiros', 'dps-finance-addon' );
    $slug  = sanitize_title( $title );
    $page  = get_page_by_path( $slug );
    if ( ! $page ) {
        $page_id = wp_insert_post( [
            'post_title'   => $title,
            'post_name'    => $slug,
            'post_content' => '[dps_fin_docs]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ] );
        if ( $page_id ) {
            update_option( 'dps_fin_docs_page_id', $page_id );
        }
    } else {
        update_option( 'dps_fin_docs_page_id', $page->ID );
    }
}
```

**Depois** (linhas 107-210):
```php
/**
 * Executado na ativação do add‑on financeiro.
 * 
 * Este método:
 * 1. Cria as tabelas dps_transacoes e dps_parcelas se não existirem
 * 2. Garante que exista uma página para listar documentos financeiros
 * 3. É idempotente: pode ser executado múltiplas vezes sem problemas
 */
public static function activate() {
    global $wpdb;
    
    // Define versão atual do schema
    $current_version = '1.0.0';
    
    // ========== 1. Criar tabela dps_transacoes ==========
    $transacoes_table = $wpdb->prefix . 'dps_transacoes';
    $transacoes_version = get_option( 'dps_transacoes_db_version', '0' );
    
    // Só cria/atualiza se a versão for diferente
    if ( version_compare( $transacoes_version, $current_version, '<' ) ) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $transacoes_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            cliente_id bigint(20) DEFAULT NULL,
            agendamento_id bigint(20) DEFAULT NULL,
            plano_id bigint(20) DEFAULT NULL,
            data date DEFAULT NULL,
            valor float DEFAULT 0,
            categoria varchar(255) NOT NULL DEFAULT '',
            tipo varchar(50) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT '',
            descricao text NOT NULL DEFAULT '',
            PRIMARY KEY  (id),
            KEY cliente_id (cliente_id),
            KEY agendamento_id (agendamento_id),
            KEY plano_id (plano_id)
        ) $charset_collate;";
        
        dbDelta( $sql );
        update_option( 'dps_transacoes_db_version', $current_version );
    }
    
    // ========== 2. Criar tabela dps_parcelas ==========
    $parcelas_table = $wpdb->prefix . 'dps_parcelas';
    $parcelas_version = get_option( 'dps_parcelas_db_version', '0' );
    
    // Só cria/atualiza se a versão for diferente
    if ( version_compare( $parcelas_version, $current_version, '<' ) ) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $parcelas_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            trans_id bigint(20) NOT NULL,
            data date NOT NULL,
            valor float NOT NULL,
            metodo varchar(50) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY trans_id (trans_id)
        ) $charset_collate;";
        
        dbDelta( $sql );
        update_option( 'dps_parcelas_db_version', $current_version );
    }
    
    // ========== 3. Criar página de Documentos Financeiros ==========
    $page_id = get_option( 'dps_fin_docs_page_id' );
    
    // Verifica se a página existe (pode ter sido excluída)
    if ( $page_id ) {
        $page = get_post( $page_id );
        if ( ! $page || $page->post_status === 'trash' ) {
            $page_id = false;
        }
    }
    
    // Se não existe ou foi excluída, cria uma nova
    if ( ! $page_id ) {
        $title = __( 'Documentos Financeiros', 'dps-finance-addon' );
        $slug  = 'dps-documentos-financeiros';
        
        // Verifica se já existe uma página com este slug
        $page = get_page_by_path( $slug );
        
        if ( ! $page ) {
            $new_page_id = wp_insert_post( [
                'post_title'   => $title,
                'post_name'    => $slug,
                'post_content' => '[dps_fin_docs]',
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ] );
            
            if ( $new_page_id && ! is_wp_error( $new_page_id ) ) {
                update_option( 'dps_fin_docs_page_id', $new_page_id );
            }
        } else {
            // Página já existe com o slug, apenas atualiza a option
            update_option( 'dps_fin_docs_page_id', $page->ID );
        }
    }
}
```

**Principais mudanças:**
- ✅ Método agora é `public static` (compatível com `register_activation_hook()`)
- ✅ Cria tabela `dps_transacoes` com versionamento
- ✅ Cria tabela `dps_parcelas` com versionamento
- ✅ Slug fixo `dps-documentos-financeiros` (mais previsível)
- ✅ Verifica se página foi excluída (trash) e recria
- ✅ 100% idempotente: usa `version_compare()` para evitar recriar tabelas

### 3. CONSTRUTOR SIMPLIFICADO

**Antes** (linhas 90-105):
```php
public function __construct() {
    // Registra abas e seções no plugin base
    add_action( 'dps_base_nav_tabs_after_history', [ $this, 'add_finance_tab' ], 10, 1 );
    add_action( 'dps_base_sections_after_history', [ $this, 'add_finance_section' ], 10, 1 );
    // Trata salvamento e exclusão de transações
    add_action( 'init', [ $this, 'maybe_handle_finance_actions' ] );
    // Cria tabela de parcelas de pagamentos (pagamentos parciais) se ainda não existir
    add_action( 'init', [ $this, 'maybe_create_parcelas_table' ] );  // ❌ REMOVIDO
    // Garante que a tabela principal de transações exista...
    add_action( 'init', [ $this, 'maybe_create_transacoes_table' ] ); // ❌ REMOVIDO
    add_action( 'dps_finance_cleanup_for_appointment', [ $this, 'cleanup_transactions_for_appointment' ] );
    
    add_shortcode( 'dps_fin_docs', [ $this, 'render_fin_docs_shortcode' ] );
    
    add_action( 'updated_post_meta', [ $this, 'sync_status_to_finance' ], 10, 4 );
    add_action( 'added_post_meta',  [ $this, 'sync_status_to_finance' ], 10, 4 );
}
```

**Depois** (linhas 90-105):
```php
public function __construct() {
    // Registra abas e seções no plugin base
    add_action( 'dps_base_nav_tabs_after_history', [ $this, 'add_finance_tab' ], 10, 1 );
    add_action( 'dps_base_sections_after_history', [ $this, 'add_finance_section' ], 10, 1 );
    // Trata salvamento e exclusão de transações
    add_action( 'init', [ $this, 'maybe_handle_finance_actions' ] );
    add_action( 'dps_finance_cleanup_for_appointment', [ $this, 'cleanup_transactions_for_appointment' ] );

    // Não cria mais uma página pública para documentos; apenas registra o shortcode
    add_shortcode( 'dps_fin_docs', [ $this, 'render_fin_docs_shortcode' ] );

    // Sincroniza automaticamente o status das transações quando o status do agendamento é atualizado ou criado
    // Utilize tanto updated_post_meta quanto added_post_meta para capturar atualizações e inserções de meta
    add_action( 'updated_post_meta', [ $this, 'sync_status_to_finance' ], 10, 4 );
    add_action( 'added_post_meta',  [ $this, 'sync_status_to_finance' ], 10, 4 );
}
```

**Mudanças:**
- ❌ Removido: `add_action( 'init', [ $this, 'maybe_create_parcelas_table' ] )`
- ❌ Removido: `add_action( 'init', [ $this, 'maybe_create_transacoes_table' ] )`
- ❌ Removidos comentários excessivos sobre criação de tabelas
- ✅ Mantido apenas: hooks de integração, shortcode e sincronização

### 4. MÉTODOS REMOVIDOS

Os seguintes métodos foram **completamente removidos** pois sua lógica foi movida para `activate()`:

```php
// ❌ REMOVIDO (linhas 1202-1228)
public function maybe_create_parcelas_table() { ... }

// ❌ REMOVIDO (linhas 1230-1276)
public function maybe_create_transacoes_table() { ... }
```

### 5. IDEMPOTÊNCIA GARANTIDA

O método `activate()` agora é completamente idempotente através de:

1. **Versionamento de schema**:
   ```php
   $current_version = '1.0.0';
   $transacoes_version = get_option( 'dps_transacoes_db_version', '0' );
   
   if ( version_compare( $transacoes_version, $current_version, '<' ) ) {
       // Só cria/atualiza se versão for menor
       dbDelta( $sql );
       update_option( 'dps_transacoes_db_version', $current_version );
   }
   ```

2. **Verificação de página existente**:
   ```php
   $page_id = get_option( 'dps_fin_docs_page_id' );
   
   // Verifica se a página existe (pode ter sido excluída)
   if ( $page_id ) {
       $page = get_post( $page_id );
       if ( ! $page || $page->post_status === 'trash' ) {
           $page_id = false;
       }
   }
   
   // Só cria se não existe
   if ( ! $page_id ) { ... }
   ```

## Benefícios

### Performance
- ✅ **Não executa mais `dbDelta()` em todo request**: tabelas criadas apenas na ativação
- ✅ **Reduz overhead do hook `init`**: 2 verificações pesadas removidas

### Manutenibilidade
- ✅ **Segue padrão WordPress**: `register_activation_hook()` é o lugar correto
- ✅ **Versionamento de schema**: facilita migrações futuras
- ✅ **Código mais limpo**: lógica de setup separada da lógica de runtime

### Confiabilidade
- ✅ **Idempotente**: pode rodar múltiplas vezes sem problemas
- ✅ **Recupera de exclusões**: recria página se foi deletada
- ✅ **Slug fixo**: `dps-documentos-financeiros` é mais previsível que slug gerado

## Arquivos Alterados

1. **`add-ons/desi-pet-shower-finance_addon/desi-pet-shower-finance-addon.php`**
   - Método `activate()` refatorado (agora static, com criação de tabelas)
   - Construtor simplificado (removidas chamadas a `init`)
   - Métodos `maybe_create_*_table()` removidos
   - Hook de ativação registrado

2. **`ADDONS_DETAILED_ANALYSIS.md`**
   - Seção "Carregamento" atualizada
   - Seção "Páginas criadas" atualizada (marca como ✅)
   - Seção "Activation hook" atualizada (marca como ✅ CORRIGIDO)

## Checklist de Verificação

- [x] Hook de ativação registrado com `register_activation_hook()`
- [x] Método `activate()` é estático
- [x] Tabela `dps_transacoes` criada no `activate()`
- [x] Tabela `dps_parcelas` criada no `activate()`
- [x] Página "Documentos Financeiros" criada no `activate()`
- [x] Slug fixo `dps-documentos-financeiros` usado
- [x] Versionamento implementado (`dps_transacoes_db_version`, `dps_parcelas_db_version`)
- [x] Idempotência garantida (verificações antes de criar)
- [x] Chamadas ao `init` removidas do construtor
- [x] Métodos `maybe_create_*_table()` removidos
- [x] Documentação atualizada

## Testes Recomendados

1. **Ativação inicial**:
   - Ativar plugin pela primeira vez
   - Verificar que tabelas foram criadas: `wp_dps_transacoes`, `wp_dps_parcelas`
   - Verificar que página foi criada com slug `dps-documentos-financeiros`

2. **Reativação**:
   - Desativar e reativar plugin
   - Verificar que não há erros
   - Verificar que tabelas não foram recriadas (sem perda de dados)

3. **Recuperação de página excluída**:
   - Excluir página "Documentos Financeiros"
   - Reativar plugin
   - Verificar que página foi recriada

4. **Migração futura**:
   - Alterar `$current_version` para `1.1.0`
   - Adicionar novo campo na tabela
   - Reativar plugin
   - Verificar que schema foi atualizado
