# Análise Profunda do Add-on Backup & Restauração

**Data da Análise**: 02/12/2025  
**Versão Analisada**: 1.0.0 → **1.1.0** (atualizado)  
**Arquivo Principal**: `desi-pet-shower-backup-addon.php`  
**Arquivos Auxiliares**: `includes/` (5 classes), `assets/` (CSS + JS), `README.md`, `uninstall.php`

---

## ✅ Melhorias Implementadas (v1.1.0)

### Funcionalidades Novas

| Funcionalidade | Status | Descrição |
|----------------|--------|-----------|
| **Backup Seletivo** | ✅ Implementado | Escolher quais componentes incluir no backup |
| **Histórico de Backups** | ✅ Implementado | Registro dos últimos N backups com opção de download, restaurar e excluir |
| **Backup Agendado** | ✅ Implementado | Cron job para backups automáticos (diário/semanal/mensal) |
| **Restauração do Histórico** | ✅ Implementado | Restaurar backup diretamente do histórico sem upload |
| **Comparação de Backups** | ✅ Implementado | Preview do impacto antes de restaurar (o que será adicionado/atualizado/removido) |
| **Backup Diferencial** | ✅ Implementado | Classe `DPS_Backup_Exporter` com método `build_differential_backup()` |

### Melhorias de UI/UX

| Melhoria | Status | Descrição |
|----------|--------|-----------|
| Dashboard de Status | ✅ Implementado | Cards mostrando contagem de clientes, pets, agendamentos, etc. |
| Assets Externos | ✅ Implementado | CSS e JS separados em `assets/` |
| Área de Upload | ✅ Implementado | Drag and drop para upload de arquivo |
| Tabela de Histórico | ✅ Implementado | Lista de backups com ações (baixar, comparar, restaurar, excluir) |
| Configurações de Agendamento | ✅ Implementado | Interface para configurar backup automático |
| Progress Bar | ✅ Implementado | Indicador visual durante operações longas |

### Arquitetura Modular

Nova estrutura com separação de responsabilidades:

```
add-ons/desi-pet-shower-backup_addon/
├── desi-pet-shower-backup-addon.php    # Arquivo principal (~700 linhas)
├── includes/
│   ├── class-dps-backup-settings.php   # Configurações
│   ├── class-dps-backup-history.php    # Histórico de backups
│   ├── class-dps-backup-scheduler.php  # Cron jobs
│   ├── class-dps-backup-exporter.php   # Lógica de exportação
│   └── class-dps-backup-comparator.php # Comparação de backups
├── assets/
│   ├── css/backup-addon.css            # Estilos
│   └── js/backup-addon.js              # Interatividade
├── README.md
└── uninstall.php
```

---

## Índice

1. [Visão Geral](#1-visão-geral)
2. [Estrutura de Arquivos](#2-estrutura-de-arquivos)
3. [Funcionalidades Atuais](#3-funcionalidades-atuais)
4. [Análise de Código](#4-análise-de-código)
5. [Análise de Segurança](#5-análise-de-segurança)
6. [Melhorias de Código Propostas](#6-melhorias-de-código-propostas)
7. [Melhorias de Funcionalidades Propostas](#7-melhorias-de-funcionalidades-propostas)
8. [Melhorias de Layout/UX Propostas](#8-melhorias-de-layoutux-propostas)
9. [Novas Implementações Sugeridas](#9-novas-implementações-sugeridas)
10. [Roadmap de Implementação](#10-roadmap-de-implementação)
11. [Conclusão](#11-conclusão)

---

## 1. Visão Geral

O **Backup & Restauração Add-on** permite exportar e restaurar dados completos do sistema desi.pet by PRObst em formato JSON. É uma ferramenta crítica para migrações, recuperação de desastres e manutenção do sistema.

### 1.1 Propósito Principal

- **Exportação**: Gera arquivo JSON com todos os dados do DPS
- **Exportação Seletiva**: Escolher componentes específicos para backup (v1.1.0)
- **Exportação Diferencial**: Exportar apenas dados modificados desde última data (v1.1.0)
- **Restauração**: Reconstrói o sistema a partir de um backup existente
- **Histórico**: Mantém registro e arquivos dos últimos backups (v1.1.0)
- **Agendamento**: Backup automático via cron (v1.1.0)
- **Comparação**: Preview do impacto antes de restaurar (v1.1.0)
- **Migração**: Permite transferir dados entre ambientes WordPress

### 1.2 Dependências

| Tipo | Componente | Obrigatório |
|------|------------|-------------|
| Plugin Base | `DPS_Base_Plugin` | ✅ Sim |
| WordPress | v6.0+ | ✅ Sim |
| PHP | v7.4+ | ✅ Sim |
| Extensão | JSON | ✅ Sim |

### 1.3 Escopo de Dados

**Dados Exportados:**
- CPTs prefixados com `dps_` (clientes, pets, agendamentos, serviços, etc.)
- Metadados de posts (`wp_postmeta`)
- Options prefixadas com `dps_`
- Tabelas customizadas (ex: `dps_transacoes`, `dps_parcelas`)
- Anexos (imagens de pets, documentos)
- Arquivos adicionais (pasta `dps_docs`)

---

## 2. Estrutura de Arquivos

### 2.1 Estrutura Atual

```
add-ons/desi-pet-shower-backup_addon/
├── desi-pet-shower-backup-addon.php    # Arquivo único (1338 linhas)
├── README.md                            # Documentação (199 linhas)
└── uninstall.php                        # Limpeza (50 linhas)
```

### 2.2 Problemas Estruturais Identificados

| Problema | Descrição | Impacto | Prioridade |
|----------|-----------|---------|------------|
| Arquivo único muito grande | 1338 linhas em um único arquivo | Difícil manutenção e testes | Médio |
| Sem pasta `includes/` | Toda lógica em uma classe | Sem separação de responsabilidades | Médio |
| Sem pasta `assets/` | Estilos inline mínimos | Inconsistente com outros add-ons | Baixo |
| Sem pasta `languages/` | Text domain definido mas sem arquivos .po/.mo | Limite de tradução | Baixo |

### 2.3 Estrutura Recomendada

```
add-ons/desi-pet-shower-backup_addon/
├── desi-pet-shower-backup-addon.php    # Wrapper (bootstrapping, ~100 linhas)
├── includes/
│   ├── class-dps-backup-exporter.php   # Lógica de exportação
│   ├── class-dps-backup-importer.php   # Lógica de importação
│   ├── class-dps-backup-validator.php  # Validação de payloads
│   ├── class-dps-backup-files.php      # Manipulação de arquivos
│   └── class-dps-backup-admin.php      # Interface administrativa
├── assets/
│   ├── css/backup-addon.css            # Estilos
│   └── js/backup-addon.js              # Interatividade (progress, confirmações)
├── languages/
│   └── dps-backup-addon-pt_BR.po       # Traduções
├── README.md
└── uninstall.php
```

---

## 3. Funcionalidades Atuais

### 3.1 Exportação

| Funcionalidade | Status | Descrição |
|----------------|--------|-----------|
| Exportar CPTs | ✅ Implementado | Exporta todos os CPTs prefixados com `dps_` |
| Exportar Metadados | ✅ Implementado | Inclui todos os custom fields |
| Exportar Options | ✅ Implementado | Exporta options prefixadas com `dps_` |
| Exportar Tabelas | ✅ Implementado | Inclui schema e dados de tabelas `dps_*` |
| Exportar Anexos | ✅ Implementado | Exporta arquivos como base64 |
| Exportar Documentos | ✅ Implementado | Inclui pasta `dps_docs` |
| Mapeamento de IDs | ✅ Implementado | Preserva relacionamentos entre entidades |
| Nome do Arquivo | ✅ Implementado | Formato: `dps-backup-YYYYMMDD-HHiiss.json` |

### 3.2 Restauração

| Funcionalidade | Status | Descrição |
|----------------|--------|-----------|
| Upload de Arquivo | ✅ Implementado | Aceita apenas JSON, máx 50MB |
| Validação de Plugin | ✅ Implementado | Verifica campo `plugin: "desi-pet-shower"` |
| Validação de Schema | ✅ Implementado | Verifica `schema_version: 1` |
| Validação de Entidades | ✅ Implementado | Verifica estrutura de clients/pets/appointments |
| Limpeza Prévia | ✅ Implementado | Remove dados existentes antes de restaurar |
| Mapeamento de IDs | ✅ Implementado | Mapeia IDs antigos para novos |
| Restaurar CPTs | ✅ Implementado | Recria posts e metadados |
| Restaurar Transações | ✅ Implementado | Recria registros na tabela `dps_transacoes` |
| Restaurar Tabelas | ✅ Implementado | Recria estrutura e dados |
| Restaurar Anexos | ✅ Implementado | Reconstrói arquivos a partir de base64 |
| Transações SQL | ✅ Implementado | Usa `START TRANSACTION` / `COMMIT` / `ROLLBACK` |

### 3.3 Segurança

| Funcionalidade | Status | Descrição |
|----------------|--------|-----------|
| Nonce em Export | ✅ Implementado | `dps_backup_nonce` com action `dps_backup_export` |
| Nonce em Import | ✅ Implementado | `dps_backup_nonce` com action `dps_backup_import` |
| Capability Check | ✅ Implementado | Requer `manage_options` |
| Validação Extensão | ✅ Implementado | Apenas `.json` permitido |
| Limite de Tamanho | ✅ Implementado | Máximo 50MB |
| Sanitização Options | ✅ Implementado | Apenas prefixo `dps_` permitido |
| Sanitização Tabelas | ✅ Implementado | Apenas prefixo `dps_` permitido |
| Sanitização Status | ✅ Implementado | Lista de status válidos do WP |
| Deserialização Segura | ✅ Implementado | `allowed_classes => false` |

---

## 4. Análise de Código

### 4.1 Métricas de Qualidade

| Métrica | Valor | Avaliação |
|---------|-------|-----------|
| Linhas de código | 1338 | Alto - candidato a refatoração |
| Complexidade ciclomática | Alta | Vários métodos longos |
| DocBlocks | Parcial | Classe e principais métodos documentados |
| Cobertura de testes | 0% | Sem estrutura de testes |
| PHPCS (WordPress) | Boa | Maioria das regras seguidas |

### 4.2 Pontos Fortes

1. **Validação robusta de importação**
   - Verifica plugin, schema, blocos obrigatórios e estrutura de entidades
   - Mensagens de erro detalhadas e localizadas

2. **Mapeamento de IDs inteligente**
   - Mapeia clientes → pets → agendamentos na ordem correta
   - Atualiza referências em `appointment_client_id`, `appointment_pet_ids`, etc.

3. **Transações SQL**
   - Usa `START TRANSACTION` para garantir atomicidade
   - Rollback em caso de falha

4. **Segurança bem implementada**
   - Nonces, capabilities, sanitização de inputs
   - Validação de extensão e tamanho de arquivo
   - Restrição de tabelas e options ao prefixo `dps_`

5. **Tratamento de arquivos**
   - Base64 para anexos permite portabilidade
   - Preserva estrutura de diretórios

### 4.3 Pontos de Atenção

#### 4.3.1 Métodos Muito Longos

| Método | Linhas | Recomendação |
|--------|--------|--------------|
| `build_backup_payload()` | ~50 | Extrair para `DPS_Backup_Exporter` |
| `restore_backup_payload()` | ~30 | Extrair para `DPS_Backup_Importer` |
| `restore_structured_entities()` | ~60 | Extrair métodos separados por entidade |
| `wipe_existing_data()` | ~60 | Extrair para método dedicado |
| `gather_attachments()` | ~90 | Complexidade alta, candidato a refatoração |
| `restore_attachments()` | ~50 | Extrair para classe de arquivos |

#### 4.3.2 Código Duplicado

1. **Validação de nonces** - Padrão repetido em `handle_export()` e `handle_import()`
2. **Sanitização de tabelas** - Lógica similar em `restore_tables()` e `gather_custom_tables()`
3. **Queries de posts/meta** - Padrões de query repetidos

#### 4.3.3 Uso de `file_get_contents` / `file_put_contents`

O código usa funções nativas PHP corretamente, mas poderia usar o sistema de arquivos do WordPress (`WP_Filesystem`) para melhor compatibilidade.

### 4.4 Fluxo de Dados

```
EXPORTAÇÃO:
┌─────────────────┐
│ handle_export() │
└────────┬────────┘
         │
         ▼
┌─────────────────────┐
│ build_backup_payload│
└────────┬────────────┘
         │
    ┌────┴────┬────────────┬────────────┬────────────┐
    │         │            │            │            │
    ▼         ▼            ▼            ▼            ▼
┌───────┐ ┌───────┐   ┌─────────┐  ┌─────────┐  ┌─────────┐
│ CPTs  │ │Options│   │ Tabelas │  │ Anexos  │  │ Arquivos│
└───────┘ └───────┘   └─────────┘  └─────────┘  └─────────┘
         │
         ▼
┌─────────────────┐
│ JSON Download   │
└─────────────────┘

IMPORTAÇÃO:
┌─────────────────┐
│ handle_import() │
└────────┬────────┘
         │
         ▼
┌───────────────────────┐
│ validate_import_payload│
└────────┬──────────────┘
         │
         ▼
┌───────────────────────┐
│ restore_backup_payload│
└────────┬──────────────┘
         │
    ┌────┴────┬────────────┬────────────┬────────────┐
    │         │            │            │            │
    ▼         ▼            ▼            ▼            ▼
┌────────┐ ┌───────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐
│Entidades│ │Transações│ │ Tabelas │ │ Anexos  │ │ Arquivos│
└────────┘ └───────────┘ └─────────┘ └─────────┘ └─────────┘
```

---

## 5. Análise de Segurança

### 5.1 Vulnerabilidades Verificadas

| Vulnerabilidade | Status | Notas |
|-----------------|--------|-------|
| SQL Injection | ✅ Protegido | Usa `$wpdb->prepare()` ou IDs sanitizados |
| CSRF | ✅ Protegido | Nonces em todas as ações |
| File Upload | ✅ Protegido | Validação de extensão e tamanho |
| Object Injection | ✅ Protegido | `allowed_classes => false` em unserialize |
| Privilege Escalation | ✅ Protegido | Verifica `manage_options` |
| Path Traversal | ✅ Protegido | Usa `wp_upload_dir()` como base |
| XSS | ✅ Protegido | Escape de saída com `esc_html()` |

### 5.2 Boas Práticas Implementadas

1. **Prefixo DPS obrigatório** - Tabelas e options restritas ao escopo do plugin
2. **Deserialização segura** - Impede instanciação de objetos maliciosos
3. **Sanitização de meta keys** - Usa `sanitize_key()` para meta keys
4. **Status válidos** - Lista whitelist de post_status permitidos
5. **Rollback em falhas** - Transação SQL é revertida em caso de erro

### 5.3 Recomendações de Segurança

| Recomendação | Prioridade | Justificativa |
|--------------|------------|---------------|
| Adicionar rate limiting | Baixa | Evitar DoS via uploads repetidos |
| Log de operações | Média | Registrar backups e restaurações via `DPS_Logger` |
| Checksum de arquivo | Baixa | Validar integridade do backup |
| Criptografia opcional | Baixa | Proteger dados sensíveis em backups |

---

## 6. Melhorias de Código Propostas

### 6.1 Fase 1 - Quick Wins (Prioridade Alta)

#### 6.1.1 Extrair Assets CSS/JS

**Problema**: Estilos inline no método `render_admin_page()` são difíceis de manter.

**Solução**: Criar `assets/css/backup-addon.css` e registrar via `admin_enqueue_scripts`.

```php
// Novo método
public function enqueue_admin_assets( $hook ) {
    if ( 'desi-pet-shower_page_dps-backup' !== $hook ) {
        return;
    }
    
    wp_enqueue_style(
        'dps-backup-addon',
        plugin_dir_url( __FILE__ ) . 'assets/css/backup-addon.css',
        [],
        self::VERSION
    );
    
    wp_enqueue_script(
        'dps-backup-addon',
        plugin_dir_url( __FILE__ ) . 'assets/js/backup-addon.js',
        [ 'jquery' ],
        self::VERSION,
        true
    );
}
```

#### 6.1.2 Adicionar Logs de Operações

**Problema**: Não há registro de backups e restaurações realizados.

**Solução**: Integrar com `DPS_Logger` para auditoria.

```php
// Na exportação
if ( class_exists( 'DPS_Logger' ) ) {
    DPS_Logger::log(
        'backup_export',
        sprintf( 'Backup exportado: %d clientes, %d pets, %d agendamentos', $counts['clients'], $counts['pets'], $counts['appointments'] ),
        'info'
    );
}

// Na importação
if ( class_exists( 'DPS_Logger' ) ) {
    DPS_Logger::log(
        'backup_import',
        sprintf( 'Backup restaurado: %s (schema v%d)', $payload['generated_at'], $payload['schema_version'] ),
        'info'
    );
}
```

#### 6.1.3 Feedback Visual Melhorado

**Problema**: Mensagens de sucesso/erro simples sem detalhes.

**Solução**: Usar `DPS_Message_Helper` para feedback consistente e incluir estatísticas.

```php
DPS_Message_Helper::add_success(
    sprintf(
        __( 'Backup restaurado com sucesso! %d clientes, %d pets, %d agendamentos importados.', 'dps-backup-addon' ),
        count( $payload['clients'] ),
        count( $payload['pets'] ),
        count( $payload['appointments'] )
    )
);
```

### 6.2 Fase 2 - Refatoração Estrutural (Prioridade Média)

#### 6.2.1 Separar em Classes

**Objetivo**: Seguir padrão do Finance Add-on com separação de responsabilidades.

**Arquivos propostos**:

1. **`class-dps-backup-exporter.php`** (~300 linhas)
   - `build_backup_payload()`
   - `export_entities_by_type()`
   - `export_transactions()`
   - `gather_custom_tables()`
   - `gather_attachments()`
   - `gather_additional_files()`

2. **`class-dps-backup-importer.php`** (~400 linhas)
   - `restore_backup_payload()`
   - `restore_structured_entities()`
   - `restore_transactions_with_mapping()`
   - `restore_options()`
   - `restore_tables()`
   - `restore_attachments()`
   - `restore_additional_files()`

3. **`class-dps-backup-validator.php`** (~100 linhas)
   - `validate_import_payload()`
   - `validate_file_upload()`
   - `validate_json_structure()`

4. **`class-dps-backup-files.php`** (~150 linhas)
   - `write_upload_file()`
   - `clear_finance_documents()`
   - Métodos de manipulação de arquivos

5. **`class-dps-backup-admin.php`** (~200 linhas)
   - `register_admin_menu()`
   - `render_admin_page()`
   - `enqueue_admin_assets()`

#### 6.2.2 Usar Traits para Código Comum

```php
trait DPS_Backup_Sanitization {
    protected function sanitize_table_name( $name ) {
        return preg_replace( '/[^a-zA-Z0-9_]/', '', $name );
    }
    
    protected function is_valid_dps_table( $name ) {
        return 0 === strpos( $name, 'dps_' );
    }
}
```

### 6.3 Fase 3 - Otimizações (Prioridade Baixa)

#### 6.3.1 Processamento em Lotes

**Problema**: Backups grandes podem estourar `memory_limit` e `max_execution_time`.

**Solução**: Implementar processamento em lotes com AJAX.

```php
// Exportação em lotes
public function export_batch() {
    $batch_size = 100;
    $offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
    
    // Processar lote
    $data = $this->export_entities_batch( $offset, $batch_size );
    
    wp_send_json_success( [
        'offset' => $offset + $batch_size,
        'total'  => $this->get_total_entities(),
        'data'   => $data,
        'done'   => $offset + $batch_size >= $this->get_total_entities(),
    ] );
}
```

#### 6.3.2 Compressão de Backup

**Proposta**: Opção de gerar backup compactado (ZIP ou GZIP).

```php
// Opção de compressão
if ( class_exists( 'ZipArchive' ) && $compress ) {
    $zip = new ZipArchive();
    $zip_filename = str_replace( '.json', '.zip', $filename );
    $zip->open( $zip_path, ZipArchive::CREATE );
    $zip->addFromString( $filename, wp_json_encode( $payload ) );
    $zip->close();
    // Enviar ZIP
}
```

---

## 7. Melhorias de Funcionalidades Propostas

### 7.1 Backup Seletivo

**Descrição**: Permitir escolher quais componentes incluir no backup.

**UI Proposta**:
```
☑ Clientes e Pets
☑ Agendamentos  
☑ Transações Financeiras
☑ Serviços
☑ Assinaturas
☐ Campanhas e Fidelidade
☐ Configurações
☐ Arquivos (fotos, documentos)
```

**Implementação**:
```php
public function build_selective_backup( $components = [] ) {
    $payload = [
        'plugin'        => 'desi-pet-shower',
        'schema_version' => 1,
        'generated_at'  => gmdate( 'c' ),
        'components'    => $components,
    ];
    
    if ( in_array( 'clients', $components, true ) ) {
        $payload['clients'] = $this->export_entities_by_type( 'dps_cliente' );
    }
    // ... outros componentes
    
    return $payload;
}
```

### 7.2 Histórico de Backups

**Descrição**: Manter histórico dos últimos N backups realizados.

**Implementação**:
- Option `dps_backup_history` com array de backups
- Metadados: data, tamanho, componentes incluídos
- Limite configurável (ex: últimos 10)

```php
private function register_backup_history( $filename, $size, $components ) {
    $history = get_option( 'dps_backup_history', [] );
    
    array_unshift( $history, [
        'filename'   => $filename,
        'size'       => $size,
        'components' => $components,
        'date'       => current_time( 'mysql' ),
        'user_id'    => get_current_user_id(),
    ] );
    
    // Manter apenas últimos 10
    $history = array_slice( $history, 0, 10 );
    
    update_option( 'dps_backup_history', $history );
}
```

### 7.3 Backup Agendado (Cron)

**Descrição**: Agendar backups automáticos diários/semanais.

**Implementação**:
- Configuração de frequência (diário, semanal, mensal)
- Horário preferencial
- Armazenamento local ou envio por e-mail
- Retenção configurável

```php
// Agendar cron
public function schedule_automatic_backup() {
    if ( ! wp_next_scheduled( 'dps_automatic_backup' ) ) {
        $settings = get_option( 'dps_backup_settings', [] );
        $recurrence = $settings['frequency'] ?? 'weekly';
        
        wp_schedule_event( time(), $recurrence, 'dps_automatic_backup' );
    }
}

// Executar backup agendado
public function run_automatic_backup() {
    $payload = $this->build_backup_payload();
    $filename = 'dps-backup-auto-' . gmdate( 'Ymd-His' ) . '.json';
    
    // Salvar no servidor
    $upload_dir = wp_upload_dir();
    $backup_dir = $upload_dir['basedir'] . '/dps-backups/';
    wp_mkdir_p( $backup_dir );
    
    file_put_contents(
        $backup_dir . $filename,
        wp_json_encode( $payload, JSON_PRETTY_PRINT )
    );
    
    // Enviar notificação
    $this->send_backup_notification( $filename );
}
```

### 7.4 Restauração Parcial

**Descrição**: Restaurar apenas componentes específicos de um backup.

**UI Proposta**:
```
Backup selecionado: dps-backup-20251202-143000.json
Conteúdo detectado:
  - 150 clientes ☑ Restaurar
  - 280 pets ☑ Restaurar
  - 1200 agendamentos ☐ Ignorar
  - 500 transações ☐ Ignorar
  - 12 serviços ☑ Restaurar
```

### 7.5 Comparação de Backups

**Descrição**: Comparar backup com dados atuais antes de restaurar.

**Métricas**:
- Registros que serão adicionados
- Registros que serão substituídos
- Registros que não estão no backup (serão removidos)

### 7.6 Exportação Diferencial

**Descrição**: Exportar apenas dados modificados desde o último backup.

**Implementação**:
- Usar campos `post_modified` e timestamps de tabelas
- Armazenar data do último backup
- Incluir apenas registros modificados após essa data

### 7.7 Migração entre Versões

**Descrição**: Suporte a migração de backups de versões anteriores do schema.

**Implementação**:
```php
private function migrate_schema( $payload ) {
    $version = $payload['schema_version'] ?? 0;
    
    // Migração v0 → v1
    if ( $version < 1 ) {
        $payload = $this->migrate_v0_to_v1( $payload );
    }
    
    // Migração v1 → v2 (futuro)
    // if ( $version < 2 ) { ... }
    
    return $payload;
}
```

---

## 8. Melhorias de Layout/UX Propostas

### 8.1 Interface Atual

A interface atual é funcional mas minimalista:
- Dois cards lado a lado (exportar e restaurar)
- Mensagens de status simples
- Sem indicação de progresso

### 8.2 Melhorias Propostas

#### 8.2.1 Dashboard de Status

```
┌─────────────────────────────────────────────────────────┐
│ Backup & Restauração                                    │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ ┌─────────────┐ ┌─────────────┐ ┌─────────────────────┐│
│ │   CLIENTES  │ │    PETS     │ │    AGENDAMENTOS     ││
│ │     150     │ │     280     │ │        1.200        ││
│ └─────────────┘ └─────────────┘ └─────────────────────┘│
│                                                         │
│ ┌─────────────┐ ┌─────────────┐ ┌─────────────────────┐│
│ │  TRANSAÇÕES │ │  SERVIÇOS   │ │     ASSINATURAS     ││
│ │     500     │ │      12     │ │         45          ││
│ └─────────────┘ └─────────────┘ └─────────────────────┘│
│                                                         │
│ Último backup: 02/12/2025 às 14:30 (2 horas atrás)     │
│ Tamanho estimado: ~5.2 MB                               │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

#### 8.2.2 Cards de Ação Melhorados

**Card de Exportação**:
```
┌─────────────────────────────────────────────────────────┐
│ 💾 Gerar Backup                                         │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ Componentes a incluir:                                  │
│ ☑ Clientes e Pets (430 registros)                       │
│ ☑ Agendamentos (1.200 registros)                        │
│ ☑ Transações (500 registros)                            │
│ ☑ Configurações                                         │
│ ☑ Arquivos (fotos, documentos)                          │
│                                                         │
│ Formato:                                                │
│ ○ JSON (legível, maior)                                 │
│ ○ ZIP (compactado, menor)                               │
│                                                         │
│ [████████████████████░░░░░░░░░] 75%                    │
│ Exportando agendamentos...                              │
│                                                         │
│ [ Cancelar ]                    [ Gerar Backup ]        │
└─────────────────────────────────────────────────────────┘
```

**Card de Restauração**:
```
┌─────────────────────────────────────────────────────────┐
│ 🔄 Restaurar Backup                                     │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ ⚠️ ATENÇÃO: Esta ação irá substituir todos os dados     │
│ atuais do desi.pet by PRObst. Esta operação não pode ser   │
│ desfeita.                                               │
│                                                         │
│ Selecione o arquivo de backup:                          │
│ ┌─────────────────────────────────────────────────────┐ │
│ │ 📄 Arraste um arquivo aqui ou clique para selecionar│ │
│ │    Apenas arquivos .json (máx. 50 MB)               │ │
│ └─────────────────────────────────────────────────────┘ │
│                                                         │
│ ☑ Entendo que os dados atuais serão removidos          │
│                                                         │
│ [ Validar Arquivo ]             [ Restaurar Dados ]     │
└─────────────────────────────────────────────────────────┘
```

#### 8.2.3 Preview de Backup

Antes de restaurar, mostrar resumo do backup:

```
┌─────────────────────────────────────────────────────────┐
│ 📋 Resumo do Backup                                     │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ Arquivo: dps-backup-20251202-143000.json                │
│ Gerado em: 02/12/2025 às 14:30                          │
│ Versão do schema: 1                                     │
│ Site de origem: https://exemplo.com                     │
│                                                         │
│ Conteúdo:                                               │
│ ├─ 150 clientes                                         │
│ ├─ 280 pets                                             │
│ ├─ 1.200 agendamentos                                   │
│ ├─ 500 transações                                       │
│ ├─ 12 serviços                                          │
│ ├─ 45 assinaturas                                       │
│ ├─ 85 anexos (12.5 MB)                                  │
│ └─ Configurações do sistema                             │
│                                                         │
│ Comparação com dados atuais:                            │
│ ├─ +30 clientes novos                                   │
│ ├─ +50 pets novos                                       │
│ ├─ =12 serviços iguais                                  │
│ └─ -5 transações que serão removidas                    │
│                                                         │
│ [ Cancelar ]                    [ Confirmar Restauração]│
└─────────────────────────────────────────────────────────┘
```

#### 8.2.4 Histórico de Backups

```
┌─────────────────────────────────────────────────────────┐
│ 📚 Histórico de Backups                                 │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ ┌─────────────────┬──────────┬─────────┬──────────────┐│
│ │ Data            │ Tamanho  │ Tipo    │ Ações        ││
│ ├─────────────────┼──────────┼─────────┼──────────────┤│
│ │ 02/12/25 14:30  │ 5.2 MB   │ Manual  │ [📥] [🗑️]    ││
│ │ 01/12/25 00:00  │ 5.1 MB   │ Auto    │ [📥] [🗑️]    ││
│ │ 24/11/25 00:00  │ 4.8 MB   │ Auto    │ [📥] [🗑️]    ││
│ │ 17/11/25 00:00  │ 4.5 MB   │ Auto    │ [📥] [🗑️]    ││
│ └─────────────────┴──────────┴─────────┴──────────────┘│
│                                                         │
│ Backup automático: ☑ Ativado (Semanal, Dom 02:00)       │
│ Retenção: 4 backups                                     │
│ [ Configurar ]                                          │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

#### 8.2.5 Barra de Progresso

Implementar progress bar com AJAX para operações longas:

```javascript
// assets/js/backup-addon.js
jQuery(document).ready(function($) {
    $('#dps-backup-form').on('submit', function(e) {
        e.preventDefault();
        
        var $button = $(this).find('button[type="submit"]');
        var $progress = $('#dps-backup-progress');
        
        $button.prop('disabled', true);
        $progress.show();
        
        exportBackupBatch(0);
    });
    
    function exportBackupBatch(offset) {
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'dps_backup_export_batch',
                nonce: dpsBackup.nonce,
                offset: offset
            },
            success: function(response) {
                if (response.success) {
                    var progress = (response.data.offset / response.data.total) * 100;
                    updateProgress(progress, response.data.message);
                    
                    if (!response.data.done) {
                        exportBackupBatch(response.data.offset);
                    } else {
                        downloadBackup(response.data.file);
                    }
                }
            }
        });
    }
});
```

#### 8.2.6 Responsividade

Adicionar CSS para layout responsivo:

```css
/* assets/css/backup-addon.css */
.dps-backup-wrap {
    max-width: 1200px;
    margin: 20px auto;
}

.dps-backup-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-top: 30px;
}

.dps-backup-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 24px;
}

.dps-backup-card h2 {
    margin: 0 0 16px 0;
    font-size: 20px;
    font-weight: 600;
    color: #374151;
}

/* Mobile */
@media (max-width: 768px) {
    .dps-backup-cards {
        grid-template-columns: 1fr;
    }
    
    .dps-backup-card {
        padding: 16px;
    }
}
```

---

## 9. Novas Implementações Sugeridas

### 9.1 Prioridade Alta

| Funcionalidade | Esforço | Impacto | Justificativa |
|----------------|---------|---------|---------------|
| Log de operações | 2h | Alto | Auditoria e debugging |
| Feedback com estatísticas | 1h | Alto | UX melhorada |
| Assets externos (CSS/JS) | 3h | Médio | Manutenibilidade |
| Validação pré-restauração | 2h | Alto | Segurança e UX |

### 9.2 Prioridade Média

| Funcionalidade | Esforço | Impacto | Justificativa |
|----------------|---------|---------|---------------|
| Backup seletivo | 4h | Alto | Flexibilidade |
| Histórico de backups | 4h | Médio | Rastreabilidade |
| Progress bar AJAX | 6h | Médio | UX para grandes volumes |
| Dashboard de status | 3h | Médio | Visibilidade |

### 9.3 Prioridade Baixa

| Funcionalidade | Esforço | Impacto | Justificativa |
|----------------|---------|---------|---------------|
| Backup agendado | 8h | Médio | Automação |
| Compressão ZIP | 4h | Baixo | Economia de espaço |
| Restauração parcial | 8h | Médio | Flexibilidade |
| Backup diferencial | 12h | Médio | Eficiência |
| Migração de schema | 6h | Baixo | Compatibilidade futura |
| Envio por e-mail | 4h | Baixo | Conveniência |

### 9.4 Tabela Resumo

| Fase | Funcionalidades | Esforço Total | Complexidade |
|------|-----------------|---------------|--------------|
| 1 - Quick Wins | Logs, feedback, assets | 6h | Baixa |
| 2 - Usabilidade | Dashboard, progress, histórico | 13h | Média |
| 3 - Funcionalidades | Backup seletivo, agendado | 12h | Média |
| 4 - Refatoração | Separação em classes | 16h | Alta |
| 5 - Avançado | Diferencial, compressão, parcial | 24h | Alta |

---

## 10. Roadmap de Implementação

### Fase 1 - Quick Wins (1-2 dias)

```
┌────────────────────────────────────────────────────────┐
│ Semana 1                                               │
├────────────────────────────────────────────────────────┤
│ ☐ 1.1 Integrar com DPS_Logger                          │
│ ☐ 1.2 Usar DPS_Message_Helper para feedback            │
│ ☐ 1.3 Criar assets/css/backup-addon.css                │
│ ☐ 1.4 Criar assets/js/backup-addon.js                  │
│ ☐ 1.5 Adicionar estatísticas na mensagem de sucesso    │
└────────────────────────────────────────────────────────┘
```

### Fase 2 - Usabilidade (3-4 dias)

```
┌────────────────────────────────────────────────────────┐
│ Semana 2                                               │
├────────────────────────────────────────────────────────┤
│ ☐ 2.1 Implementar dashboard de status                  │
│ ☐ 2.2 Adicionar preview de backup antes de restaurar   │
│ ☐ 2.3 Implementar progress bar com AJAX                │
│ ☐ 2.4 Adicionar histórico de backups                   │
│ ☐ 2.5 Melhorar responsividade da interface             │
└────────────────────────────────────────────────────────┘
```

### Fase 3 - Funcionalidades (4-5 dias)

```
┌────────────────────────────────────────────────────────┐
│ Semana 3                                               │
├────────────────────────────────────────────────────────┤
│ ☐ 3.1 Implementar backup seletivo                      │
│ ☐ 3.2 Adicionar backup agendado (cron)                 │
│ ☐ 3.3 Implementar notificações por e-mail              │
│ ☐ 3.4 Adicionar configurações de retenção              │
└────────────────────────────────────────────────────────┘
```

### Fase 4 - Refatoração (5-7 dias)

```
┌────────────────────────────────────────────────────────┐
│ Semana 4                                               │
├────────────────────────────────────────────────────────┤
│ ☐ 4.1 Criar class-dps-backup-exporter.php              │
│ ☐ 4.2 Criar class-dps-backup-importer.php              │
│ ☐ 4.3 Criar class-dps-backup-validator.php             │
│ ☐ 4.4 Criar class-dps-backup-files.php                 │
│ ☐ 4.5 Criar class-dps-backup-admin.php                 │
│ ☐ 4.6 Refatorar arquivo principal                      │
│ ☐ 4.7 Adicionar testes unitários                       │
└────────────────────────────────────────────────────────┘
```

### Fase 5 - Avançado (Futuro)

```
┌────────────────────────────────────────────────────────┐
│ Backlog                                                │
├────────────────────────────────────────────────────────┤
│ ☐ 5.1 Backup diferencial                               │
│ ☐ 5.2 Compressão ZIP                                   │
│ ☐ 5.3 Restauração parcial                              │
│ ☐ 5.4 Migração de schemas                              │
│ ☐ 5.5 Criptografia opcional                            │
│ ☐ 5.6 Integração com serviços cloud                    │
└────────────────────────────────────────────────────────┘
```

---

## 11. Conclusão

### 11.1 Pontos Fortes

O add-on de Backup & Restauração já possui uma base sólida:

- ✅ Exportação completa de todos os dados do DPS
- ✅ Restauração com mapeamento inteligente de IDs
- ✅ Segurança bem implementada (nonces, capabilities, validações)
- ✅ Transações SQL para atomicidade
- ✅ Documentação clara no README.md

### 11.2 Áreas de Melhoria

| Área | Prioridade | Impacto |
|------|------------|---------|
| Feedback visual | Alta | Melhora UX significativamente |
| Logs de auditoria | Alta | Essencial para debugging |
| Assets externos | Média | Manutenibilidade |
| Backup seletivo | Média | Flexibilidade para usuários |
| Refatoração estrutural | Baixa | Manutenibilidade a longo prazo |

### 11.3 Recomendação Final

**Curto prazo** (1-2 semanas):
- Implementar Fase 1 (Quick Wins) para melhorias imediatas de UX
- Adicionar logs e feedback com estatísticas

**Médio prazo** (1 mês):
- Implementar Fase 2 (Usabilidade) para interface mais profissional
- Dashboard de status e histórico de backups

**Longo prazo** (2-3 meses):
- Considerar refatoração estrutural (Fase 4) se houver necessidade de novos desenvolvedores no projeto
- Avaliar funcionalidades avançadas (Fase 5) baseado em feedback de usuários

---

## Anexos

### A. Estrutura do Payload de Backup

```json
{
    "plugin": "desi-pet-shower",
    "version": "1.0.0",
    "schema_version": 1,
    "generated_at": "2025-12-02T14:30:00+00:00",
    "site_url": "https://exemplo.com",
    "db_prefix": "wp_",
    "clients": [
        {
            "id": 1,
            "post": {
                "post_title": "João Silva",
                "post_status": "publish",
                "post_type": "dps_cliente"
            },
            "meta": {
                "client_phone": "11999999999",
                "client_email": "joao@exemplo.com"
            }
        }
    ],
    "pets": [
        {
            "id": 2,
            "post": {
                "post_title": "Rex",
                "post_status": "publish",
                "post_type": "dps_pet"
            },
            "meta": {
                "owner_id": 1,
                "pet_species": "dog"
            }
        }
    ],
    "appointments": [...],
    "transactions": [...],
    "posts": [...],
    "postmeta": [...],
    "attachments": [...],
    "options": [...],
    "tables": [...],
    "files": [...]
}
```

### B. Checklist de Segurança

- [ ] Nonces validados em todas as ações
- [ ] Capabilities verificadas (`manage_options`)
- [ ] Inputs sanitizados
- [ ] Outputs escapados
- [ ] Extensão de arquivo validada
- [ ] Tamanho de arquivo limitado
- [ ] Prefixo DPS obrigatório para tabelas/options
- [ ] Deserialização segura
- [ ] Transações SQL com rollback
- [ ] Logs de operações

### C. Referências

- [ANALYSIS.md](../../ANALYSIS.md) - Arquitetura do sistema DPS
- [AGENTS.md](../../AGENTS.md) - Diretrizes de desenvolvimento
- [Finance Add-on Analysis](./FINANCE_ADDON_ANALYSIS.md) - Referência de estrutura modular
- [Subscription Add-on Analysis](./SUBSCRIPTION_ADDON_ANALYSIS.md) - Referência de refatoração
