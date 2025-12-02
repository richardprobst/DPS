# Desi Pet Shower – Backup & Restauração Add-on

Exportação e restauração completa de dados do sistema DPS.

## Visão geral

O **Backup & Restauração Add-on** permite aos administradores exportar todo o conteúdo do sistema Desi Pet Shower em formato JSON e restaurar esses dados em outro ambiente WordPress. É ideal para migrações, cópias de segurança e testes em ambientes de staging.

Funcionalidades principais:
- Exportação completa de CPTs (clientes, pets, agendamentos, serviços, etc.)
- Inclusão de metadados e relacionamentos entre entidades
- Exportação de tabelas customizadas (`dps_transacoes`, `dps_parcelas`, etc.)
- Exportação de anexos (fotos de pets) e documentos financeiros
- Restauração reversa de backups anteriores com mapeamento inteligente de IDs
- Proteção com nonces e validação de capabilities
- Transações SQL para garantir atomicidade da restauração
- Interface administrativa integrada ao menu Desi Pet Shower

**Tipo**: Add-on (extensão do plugin base DPS)

## Localização e identificação

- **Diretório**: `add-ons/desi-pet-shower-backup_addon/`
- **Slug**: `dps-backup-addon`
- **Text Domain**: `dps-backup-addon`
- **Classe principal**: `DPS_Backup_Addon`
- **Arquivo principal**: `desi-pet-shower-backup-addon.php` (1338 linhas)
- **Tipo**: Add-on (depende do plugin base)

## Dependências e compatibilidade

### Dependências obrigatórias
- **Desi Pet Shower Base**: v1.0.0 ou superior (obrigatório)
- **WordPress**: 6.0 ou superior
- **PHP**: 7.4 ou superior (com extensão JSON ativada)

### Versão
- **Introduzido em**: v0.1.0 (estimado)
- **Versão atual**: v1.0.0
- **Compatível com plugin base**: v1.0.0+

## Funcionalidades principais

### Exportação de dados
- **Escopo completo**: exporta todos os CPTs do DPS (clientes, pets, agendamentos, serviços, campanhas, etc.)
- **Metadados incluídos**: preserva todos os custom fields vinculados aos posts
- **Tabelas customizadas**: exporta estrutura (CREATE TABLE) e dados de tabelas prefixadas com `dps_`
- **Options do sistema**: exporta todas as options prefixadas com `dps_`
- **Anexos e arquivos**: exporta fotos de pets e documentos financeiros (pasta `dps_docs`) em base64
- **Formato JSON**: arquivo legível e facilmente versionável
- **Download direto**: arquivo `.json` gerado e enviado para download imediatamente
- **Nomenclatura**: `dps-backup-YYYYMMDD-HHiiss.json`

### Restauração de dados
- **Upload de backup**: interface para enviar arquivo JSON de backup
- **Validação de integridade**: verifica formato, estrutura e versão do schema antes de importar
- **Limpeza prévia**: remove dados existentes do DPS antes de restaurar
- **Mapeamento de IDs**: reconstrói relacionamentos (cliente → pet → agendamento → transação)
- **Transações SQL**: usa `START TRANSACTION` / `COMMIT` / `ROLLBACK` para atomicidade
- **Recriação de tabelas**: restaura estrutura e dados de tabelas customizadas
- **Restauração de arquivos**: reconstrói anexos e documentos a partir de base64

### Segurança
- **Capability obrigatória**: apenas usuários com `manage_options` podem exportar/restaurar
- **Nonces validados**: todas as ações protegidas contra CSRF
- **Acesso negado a anônimos**: requisições não autenticadas são bloqueadas
- **Validação de extensão**: apenas arquivos `.json` são aceitos
- **Limite de tamanho**: máximo de 50 MB para upload
- **Sanitização de tabelas**: apenas tabelas prefixadas com `dps_` são restauradas
- **Sanitização de options**: apenas options prefixadas com `dps_` são restauradas
- **Deserialização segura**: `allowed_classes => false` para prevenir object injection
- **Status válidos**: whitelist de post_status permitidos

## Shortcodes, widgets e endpoints

### Shortcodes
Este add-on não expõe shortcodes próprios. A interface é acessada através do menu administrativo do WordPress.

### Menus administrativos
- **Backup & Restauração** (`dps-backup`): submenu sob "Desi Pet Shower"

### Endpoints admin_post

- **`admin_post_dps_backup_export`**: gera e faz download do arquivo JSON de backup
  - **Método**: POST
  - **Parâmetros**: `dps_backup_nonce`, `action=dps_backup_export`
  - **Capability**: `manage_options`
  
- **`admin_post_dps_backup_import`**: processa upload e restauração de backup
  - **Método**: POST (multipart/form-data)
  - **Parâmetros**: `dps_backup_nonce`, `action=dps_backup_import`, `dps_backup_file` (arquivo JSON, máx. 50 MB)
  - **Capability**: `manage_options`

## Hooks (actions e filters) relevantes

### Hooks CONSUMIDOS por este add-on

#### `admin_menu` (action, prioridade 20)
- **Propósito**: registra submenu "Backup & Restauração" sob o menu principal Desi Pet Shower
- **Implementação**: método `register_admin_menu()`

### Hooks DISPARADOS por este add-on

Este add-on não dispara hooks customizados; opera de forma autônoma.

## Dados armazenados (CPTs, tabelas, options)

### Custom Post Types
Este add-on NÃO cria CPTs próprios. Exporta/importa os seguintes CPTs do sistema DPS:
- `dps_cliente` (clientes)
- `dps_pet` (pets)
- `dps_agendamento` (agendamentos)
- `dps_service` (serviços)
- `dps_subscription` (assinaturas)
- `dps_campaign` (campanhas)
- Outros CPTs prefixados com `dps_`

### Tabelas customizadas
Este add-on EXPORTA tabelas customizadas prefixadas com `dps_`:
- `dps_transacoes` (transações financeiras)
- `dps_parcelas` (parcelas de pagamento)
- `dps_referrals` (indicações)
- `dps_portal_tokens` (tokens de acesso ao portal)
- Outras tabelas prefixadas com `dps_`

### Options armazenadas
Este add-on exporta/importa options prefixadas com `dps_`. Não armazena options próprias permanentemente.

## Estrutura do Payload de Backup

```json
{
    "plugin": "desi-pet-shower",
    "version": "1.0.0",
    "schema_version": 1,
    "generated_at": "2025-12-02T14:30:00+00:00",
    "site_url": "https://exemplo.com",
    "db_prefix": "wp_",
    "clients": [...],
    "pets": [...],
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

## Como usar (visão funcional)

### Para administradores

1. **Acessar backup**:
   - No menu WordPress, acesse "Desi Pet Shower" → "Backup & Restauração"

2. **Exportar dados**:
   - Clique no botão "Baixar backup completo"
   - Aguarde o processamento
   - Arquivo JSON será baixado automaticamente no navegador
   - Salve o arquivo em local seguro

3. **Restaurar dados**:
   - Selecione o arquivo `.json` de backup exportado anteriormente
   - **ATENÇÃO**: Leia o aviso sobre substituição de dados
   - Clique em "Restaurar dados"
   - Aguarde o processamento (pode demorar alguns segundos)
   - Mensagem de sucesso confirma importação

### Fluxo típico de uso

```
BACKUP ANTES DE MIGRAÇÃO:
1. Administrador acessa menu Backup & Restauração
2. Exporta dados para arquivo JSON
3. Salva arquivo localmente

MIGRAÇÃO PARA NOVO AMBIENTE:
1. Instala WordPress e plugins DPS no novo ambiente
2. Acessa Backup & Restauração
3. Faz upload do arquivo JSON
4. Clica em "Restaurar dados"
5. Sistema recria todos os dados
```

### Observações importantes

- **Restauração destrutiva**: TODOS os dados existentes do DPS são removidos antes de restaurar
- **Use em ambientes limpos**: ideal para restaurar em WordPress novo ou após desinstalação completa do DPS
- **Tabelas incluídas**: transações financeiras e outras tabelas SÃO exportadas e restauradas
- **Anexos incluídos**: fotos de pets e documentos são exportados como base64

## Notas para desenvolvimento

### Convenções e padrões

Este add-on segue as diretrizes do repositório DPS:
- **[AGENTS.md](../../AGENTS.md)**: regras de desenvolvimento, versionamento, segurança
- **[ANALYSIS.md](../../ANALYSIS.md)**: arquitetura, integração com sistema de configurações do núcleo

### Análise detalhada

Para análise completa de código, segurança, melhorias propostas e roadmap de implementação, consulte:
- **[BACKUP_ADDON_ANALYSIS.md](../../docs/analysis/BACKUP_ADDON_ANALYSIS.md)**

### Fluxo obrigatório para mudanças

Ao modificar este add-on:

1. **Ler ANALYSIS.md** para entender hooks de configurações do plugin base
2. **Implementar** seguindo políticas de segurança (nonces, capabilities, sanitização)
3. **Atualizar ANALYSIS.md** se criar novos pontos de extensão
4. **Atualizar CHANGELOG.md** antes de criar tags
5. **Validar** em ambiente de testes antes de usar em produção

### Políticas de segurança

- ✅ **Capabilities**: verifica `manage_options` antes de qualquer ação
- ✅ **Nonces**: valida `dps_backup_nonce` em exportação e importação
- ✅ **Sanitização**: valida estrutura JSON e sanitiza dados antes de importar
- ✅ **Escape**: saída escapada em mensagens de feedback
- ✅ **Validação de arquivo**: verifica extensão (apenas .json) e tamanho máximo (50 MB)
- ✅ **Validação de estrutura JSON**: verifica integridade do payload antes de restaurar
- ✅ **Sanitização de dados**: sanitiza meta keys, post status e campos de texto durante restauração
- ✅ **Prefixo obrigatório**: apenas tabelas e options com prefixo `dps_` são restauradas
- ✅ **Deserialização segura**: `allowed_classes => false` previne object injection
- ✅ **Transações SQL**: rollback automático em caso de falha

### Pontos de atenção

- **Timeout PHP**: backups muito grandes podem estourar `max_execution_time`; considerar processamento em lotes
- **Memória PHP**: JSON grande consome memória; verificar `memory_limit` adequado
- **Restauração destrutiva**: todos os dados existentes são removidos antes de restaurar; use em ambientes limpos

### Melhorias futuras sugeridas

Consulte a análise detalhada em `docs/analysis/BACKUP_ADDON_ANALYSIS.md` para o roadmap completo. Principais melhorias:

**Prioridade Alta:**
- Integração com DPS_Logger para auditoria de operações
- Feedback visual com estatísticas após backup/restauração
- Assets externos (CSS/JS) para melhor manutenibilidade

**Prioridade Média:**
- Backup seletivo (escolher componentes)
- Histórico de backups realizados
- Dashboard de status com contadores
- Progress bar com AJAX para operações longas

**Prioridade Baixa:**
- Backup agendado (cron)
- Compressão ZIP
- Restauração parcial
- Backup diferencial/incremental

## Histórico de mudanças (resumo)

### Principais marcos

- **v1.0.0**: Lançamento inicial com exportação/importação completa via JSON
  - Suporte a CPTs, metadados, tabelas customizadas, opções e arquivos
  - Interface administrativa sob menu Desi Pet Shower
  - Proteção de segurança com nonces e capabilities
  - Mapeamento inteligente de IDs entre entidades
  - Transações SQL para atomicidade

Para o histórico completo de mudanças, consulte `CHANGELOG.md` na raiz do repositório.
