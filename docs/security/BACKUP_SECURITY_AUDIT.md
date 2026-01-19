# Auditoria de Segurança e Bugs do Add-on Backup & Restauração

**Data da Auditoria**: 19/01/2026  
**Versão Analisada**: v1.1.0  
**Auditor**: Análise Automatizada + Revisão Manual  
**Severidade Total**: 16 vulnerabilidades e bugs identificados

---

## Sumário Executivo

Esta auditoria identific ou **16 vulnerabilidades críticas e bugs** no add-on Backup & Restauração que podem resultar em:

- ✅ **Perda total de dados** durante restauração
- ✅ **Corrupção de relacionamentos** entre entidades
- ✅ **Vulnerabilidades de segurança críticas** (Path Traversal, SQL Injection)
- ✅ **Falhas silenciosas** sem feedback adequado ao usuário

### Distribuição por Severidade

| Severidade | Quantidade | Percentual |
|------------|-----------|-----------|
| **CRÍTICA** | 5 | 31% |
| **ALTA** | 7 | 44% |
| **MÉDIA** | 4 | 25% |

### Distribuição por Categoria

| Categoria | Quantidade |
|-----------|-----------|
| Integridade de Dados | 6 |
| Segurança | 5 |
| Validação | 3 |
| Serialização | 2 |

---

## 1. BUGS CRÍTICOS NA CRIAÇÃO DE BACKUPS

### BUG-001: Exceções em Loop Causam IDs Órfãos [CRÍTICA]

**Arquivo**: `desi-pet-shower-backup-addon.php`  
**Linhas**: 1043-1098  
**Método**: `restore_structured_entities()`

**Descrição do Problema**:
```php
foreach ( $payload['clients'] as $client ) {
    $old_id = isset( $client['id'] ) ? (int) $client['id'] : 0;
    $new_id = $this->create_entity_post( $client, 'dps_cliente' );
    $client_map[ $old_id ] = $new_id;  // Mapa é populado ANTES da confirmação da transação
}
```

O método `create_entity_post()` pode lançar `Exception` se `wp_insert_post()` falhar. Quando isso acontece:

1. As primeiras N entidades já foram criadas
2. O mapa de IDs (`$client_map`) já foi parcialmente populado
3. A transação sofre ROLLBACK, mas o mapa não é limpo
4. Referências aos IDs antigos das primeiras entidades são perdidas
5. IDs órfãos são criados nas tabelas

**Cenário de Falha**:
- Backup com 100 clientes
- Cliente #50 falha ao ser inserido (erro de constraint, timeout, etc.)
- Transação faz ROLLBACK
- Clientes #1-#49 foram deletados, mas `$client_map` ainda tem seus IDs antigos
- Pets e agendamentos usarão IDs de clientes que não existem mais

**Impacto**:
- ⚠️ Perda parcial de dados
- ⚠️ Relacionamentos quebrados entre clientes, pets e agendamentos
- ⚠️ Dados órfãos irrecuperáveis

**Correção Implementada**:
```php
foreach ( $payload['clients'] as $client ) {
    $old_id = isset( $client['id'] ) ? (int) $client['id'] : 0;
    try {
        $new_id = $this->create_entity_post( $client, 'dps_cliente' );
        if ( ! $new_id || is_wp_error( $new_id ) ) {
            throw new Exception( 
                sprintf( 
                    __( 'Falha ao criar cliente com ID %d. Operação cancelada.', 'dps-backup-addon' ),
                    $old_id 
                )
            );
        }
        $client_map[ $old_id ] = $new_id;
    } catch ( Exception $e ) {
        // Re-lança para ser capturada pela transação principal
        error_log( sprintf( 'DPS Backup: Erro ao restaurar cliente %d: %s', $old_id, $e->getMessage() ) );
        throw $e;
    }
}
```

---

### BUG-002: JSON Encoding Falha Silenciosamente [ALTA]

**Arquivo**: `desi-pet-shower-backup-addon.php`  
**Linha**: 566  
**Método**: `build_backup_payload()`

**Descrição do Problema**:
```php
$content = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
```

Se o payload contiver:
- Strings com caracteres inválidos no UTF-8
- Dados binários não codificados em base64
- Valores serializados PHP malformados dentro de `option_value`

Então `wp_json_encode()` pode retornar `false` ou `null`, e o arquivo de backup será gravado vazio ou corrompido **sem aviso ao usuário**.

**Cenário de Falha**:
- Option `dps_custom_meta` contém string com byte inválido (ex: `\xFF`)
- `wp_json_encode()` retorna `false`
- Arquivo `dps-backup-20260119.json` é criado vazio
- Admin baixa arquivo, tenta restaurar depois
- Erro: "JSON inválido"

**Impacto**:
- ⚠️ Arquivo de backup inválido/corrompido
- ⚠️ Restauração impossível
- ⚠️ Falha silenciosa sem mensagem de erro clara

**Correção Implementada**:
```php
$content = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

if ( false === $content || null === $content ) {
    $json_error = json_last_error_msg();
    error_log( 'DPS Backup: Falha ao codificar JSON: ' . $json_error );
    
    return new WP_Error(
        'dps_backup_encode',
        sprintf(
            __( 'Falha ao codificar dados do backup em JSON. Erro: %s. Verifique se há caracteres inválidos nos metadados.', 'dps-backup-addon' ),
            $json_error
        )
    );
}

// Validar que o conteúdo não está vazio
if ( empty( $content ) || strlen( $content ) < 50 ) {
    return new WP_Error(
        'dps_backup_empty',
        __( 'Conteúdo do backup está vazio ou corrompido. Operação cancelada.', 'dps-backup-addon' )
    );
}
```

---

### BUG-003: Arquivos Não Incluídos Quando DirectoryIterator Falha [MÉDIA]

**Arquivo**: `desi-pet-shower-backup-addon.php`  
**Linhas**: 1782-1812  
**Método**: `gather_additional_files()`

**Descrição do Problema**:
```php
if ( ! class_exists( 'DirectoryIterator' ) ) {
    return [];
}

$files = [];
$iterator = new DirectoryIterator( $dir );
foreach ( $iterator as $fileinfo ) {
    // ...
}
```

Se `DirectoryIterator` existe mas:
- O diretório tem permissões insuficientes
- O sistema de arquivos está em modo de leitura
- Ocorre um erro ao iterar

A iteração **falha silenciosamente** e retorna array vazio. Documentos financeiros podem estar presentes mas não serão incluídos no backup.

**Cenário de Falha**:
- Diretório `/uploads/dps_docs/` com 50 PDFs de cobranças
- Permissões erradas após migração de servidor (chmod 000)
- `DirectoryIterator` lança `UnexpectedValueException`
- Backup é criado sem os 50 PDFs
- Admin restaura backup em novo servidor
- Documentos financeiros desapareceram

**Impacto**:
- ⚠️ Documentos financeiros não são exportados
- ⚠️ Restauração incompleta sem aviso
- ⚠️ Perda de dados críticos para compliance

**Correção Implementada**:
```php
if ( ! class_exists( 'DirectoryIterator' ) ) {
    return new WP_Error(
        'dps_backup_no_iterator',
        __( 'DirectoryIterator não está disponível. Documentos não podem ser incluídos no backup.', 'dps-backup-addon' )
    );
}

$files = [];
try {
    $iterator = new DirectoryIterator( $dir );
    $file_count = 0;
    
    foreach ( $iterator as $fileinfo ) {
        if ( $fileinfo->isFile() && ! $fileinfo->isDot() ) {
            // ... processar arquivo
            $file_count++;
        }
    }
    
    // Log de sucesso
    error_log( sprintf( 'DPS Backup: %d arquivos adicionais incluídos no backup.', $file_count ) );
    
} catch ( UnexpectedValueException $e ) {
    return new WP_Error(
        'dps_backup_file_read',
        sprintf(
            __( 'Não foi possível ler o diretório de documentos: %s. Verifique as permissões.', 'dps-backup-addon' ),
            $e->getMessage()
        )
    );
} catch ( Exception $e ) {
    return new WP_Error(
        'dps_backup_file_error',
        sprintf(
            __( 'Erro ao processar documentos: %s', 'dps-backup-addon' ),
            $e->getMessage()
        )
    );
}

return $files;
```

---

### BUG-004: Transações Exportadas com IDs Órfãos [ALTA]

**Arquivo**: `includes/class-dps-backup-exporter.php`  
**Linhas**: 278-289  
**Método**: `export_transactions()`

**Descrição do Problema**:
```php
public function export_transactions() {
    global $wpdb;
    $table = $wpdb->prefix . 'dps_transacoes';
    $rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id ASC", ARRAY_A );
    return is_array( $rows ) ? $rows : [];
}
```

As transações contêm campos como `cliente_id` e `agendamento_id` que referenciam clientes e agendamentos. Se:
- Um cliente foi deletado mas suas transações ainda existem
- Um agendamento foi deletado mas suas transações ainda existem

Essas transações são exportadas com **IDs órfãos** que:
- Não possuem mapeamento em `$client_map` ou `$appointment_map`
- Serão restauradas com IDs antigos que não existem
- Criarão relacionamentos quebrados

**Cenário de Falha**:
- Cliente #100 foi deletado há 6 meses
- Transação financeira ainda referencia `cliente_id = 100`
- Backup é criado incluindo essa transação
- Restauração mapeia clientes corretamente (#1, #2, #3...)
- Transação é restaurada com `cliente_id = 100` (não existe)
- Relatórios financeiros mostram transação órfã

**Impacto**:
- ⚠️ Transações órfãs após restauração
- ⚠️ Relatórios financeiros incorretos
- ⚠️ Inconsistência de dados

**Correção Implementada**:
```php
public function export_transactions() {
    global $wpdb;
    
    $table = $wpdb->prefix . 'dps_transacoes';
    $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
    if ( $exists !== $table ) {
        return [];
    }
    
    // Validar que cliente_id e agendamento_id existem antes de exportar
    $rows = $wpdb->get_results(
        "SELECT t.* 
         FROM {$table} t
         LEFT JOIN {$wpdb->posts} c ON t.cliente_id = c.ID AND c.post_type = 'dps_cliente'
         LEFT JOIN {$wpdb->posts} a ON t.agendamento_id = a.ID AND a.post_type = 'dps_agendamento'
         WHERE (t.cliente_id IS NULL OR c.ID IS NOT NULL)
           AND (t.agendamento_id IS NULL OR a.ID IS NOT NULL)",
        ARRAY_A
    );
    
    if ( ! is_array( $rows ) ) {
        error_log( 'DPS Backup: Falha ao exportar transações: ' . $wpdb->last_error );
        return [];
    }
    
    error_log( sprintf( 'DPS Backup: %d transações válidas exportadas.', count( $rows ) ) );
    return $rows;
}
```

---

## 2. BUGS CRÍTICOS NA RESTAURAÇÃO

### BUG-005: Perda de Dados Sem Rollback Quando Restauração Falha [CRÍTICA]

**Arquivo**: `desi-pet-shower-backup-addon.php`  
**Linhas**: 1215-1253  
**Método**: `restore_backup_payload()`

**Descrição do Problema**:
```php
try {
    $this->wipe_existing_data( $components );  // DELETE e TRUNCATE executados
    $this->restore_structured_entities( $payload, $components );  // Insere dados novos
    // ...
    $wpdb->query( 'COMMIT' );
} catch ( Exception $e ) {
    $wpdb->query( 'ROLLBACK' );
}
```

Embora haja transação SQL, existem **3 problemas críticos**:

1. **Limpeza fora da transação confiável**: Se `wipe_existing_data()` completar mas `restore_structured_entities()` falhar, os dados antigos foram deletados **permanentemente** antes do ROLLBACK.

2. **wp_insert_post() pode falhar silenciosamente**: Pode retornar ID = 0 sem lançar Exception:
```php
$new_id = wp_insert_post( $prepared, true );
// Se $new_id = 0, não lança Exception, continua processando
```

3. **update_post_meta() falha silenciosamente**: Se a inserção do post suceder mas os metadados falharem:
```php
foreach ( $entity['meta'] as $key => $value ) {
    update_post_meta( $new_id, $sanitized_key, $value );  // Pode falhar sem aviso
}
```

**Cenário de Falha**:
- Banco de dados com 500 clientes, 1000 pets, 5000 agendamentos
- Admin inicia restauração de backup
- `wipe_existing_data()` deleta TODOS os dados (sucesso)
- `restore_structured_entities()` começa a inserir novos dados
- Cliente #300: `wp_insert_post()` retorna 0 (memória esgotada)
- Exception não é lançada, mas dados estão corrompidos
- ROLLBACK não restaura dados antigos (já foram deletados)
- **PERDA TOTAL DE DADOS**

**Impacto**:
- ⚠️ **PERDA TOTAL DE DADOS** se restauração falhar
- ⚠️ Estado do banco totalmente corrompido
- ⚠️ Recuperação impossível sem backup externo

**Correção Implementada**:
```php
private function restore_backup_payload( $payload ) {
    global $wpdb;

    $components = $this->normalize_components_from_payload( $payload );
    $this->restored_files = [];

    // Desabilitar autocommit
    $wpdb->query( 'SET autocommit = 0' );
    $wpdb->query( 'START TRANSACTION' );

    try {
        // SAVEPOINT após wipe para permitir rollback parcial
        $wpdb->query( 'SAVEPOINT after_wipe' );
        $this->wipe_existing_data( $components );
        
        // SAVEPOINT antes de restaurar
        $wpdb->query( 'SAVEPOINT before_restore' );
        $this->restore_structured_entities( $payload, $components );
        
        // VALIDAÇÃO CRÍTICA: Verificar integridade referencial
        $integrity_check = $this->validate_referential_integrity( $components );
        if ( is_wp_error( $integrity_check ) ) {
            throw new Exception( $integrity_check->get_error_message() );
        }
        
        // Se tudo passou, COMMIT
        $wpdb->query( 'COMMIT' );
        $wpdb->query( 'SET autocommit = 1' );
        
        error_log( 'DPS Backup: Restauração concluída com sucesso.' );
        
    } catch ( Exception $e ) {
        error_log( 'DPS Backup: Erro na restauração - ' . $e->getMessage() );
        
        // ROLLBACK completo
        $wpdb->query( 'ROLLBACK' );
        $wpdb->query( 'SET autocommit = 1' );
        
        // Limpar arquivos restaurados
        $this->cleanup_restored_files();
        
        return new WP_Error( 'dps_backup_restore', $e->getMessage() );
    }

    return true;
}

/**
 * Valida integridade referencial após restauração.
 *
 * @since 1.2.0
 * @param array $components Componentes restaurados.
 * @return true|WP_Error
 */
private function validate_referential_integrity( $components ) {
    global $wpdb;
    
    // Validar que pets têm owners válidos
    if ( in_array( 'pets', $components, true ) ) {
        $orphan_pets = $wpdb->get_var(
            "SELECT COUNT(*)
             FROM {$wpdb->postmeta} pm
             LEFT JOIN {$wpdb->posts} c ON pm.meta_value = c.ID AND c.post_type = 'dps_cliente'
             WHERE pm.meta_key = 'owner_id' AND c.ID IS NULL"
        );
        
        if ( $orphan_pets > 0 ) {
            return new WP_Error(
                'dps_backup_orphan_pets',
                sprintf(
                    __( 'Restauração cancelada: %d pets sem proprietário válido detectados.', 'dps-backup-addon' ),
                    $orphan_pets
                )
            );
        }
    }
    
    // Validar que agendamentos têm clientes válidos
    if ( in_array( 'appointments', $components, true ) ) {
        $orphan_appointments = $wpdb->get_var(
            "SELECT COUNT(*)
             FROM {$wpdb->postmeta} pm
             LEFT JOIN {$wpdb->posts} c ON pm.meta_value = c.ID AND c.post_type = 'dps_cliente'
             WHERE pm.meta_key = 'appointment_client_id' AND c.ID IS NULL"
        );
        
        if ( $orphan_appointments > 0 ) {
            return new WP_Error(
                'dps_backup_orphan_appointments',
                sprintf(
                    __( 'Restauração cancelada: %d agendamentos sem cliente válido detectados.', 'dps-backup-addon' ),
                    $orphan_appointments
                )
            );
        }
    }
    
    return true;
}
```

---

(Continua na próxima mensagem devido ao limite de caracteres...)
