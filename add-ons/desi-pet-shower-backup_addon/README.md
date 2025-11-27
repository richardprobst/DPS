# Desi Pet Shower – Backup & Restauração Add-on

Exportação e restauração completa de dados do sistema DPS.

## Visão geral

O **Backup & Restauração Add-on** permite aos administradores exportar todo o conteúdo do sistema Desi Pet Shower em formato JSON e restaurar esses dados em outro ambiente WordPress. É ideal para migrações, cópias de segurança e testes em ambientes de staging.

Funcionalidades principais:
- Exportação completa de CPTs (clientes, pets, agendamentos, serviços, etc.)
- Inclusão de metadados e relacionamentos entre entidades
- Restauração reversa de backups anteriores
- Proteção com nonces e validação de capabilities
- Interface integrada à tela de configurações do plugin base

**Tipo**: Add-on (extensão do plugin base DPS)

## Localização e identificação

- **Diretório**: `add-ons/desi-pet-shower-backup_addon/`
- **Slug**: `dps-backup-addon`
- **Classe principal**: `DPS_Backup_Addon`
- **Arquivo principal**: `desi-pet-shower-backup-addon.php`
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
- **Formato JSON**: arquivo legível e facilmente versionável
- **Download direto**: arquivo `.json` gerado e enviado para download imediatamente

### Restauração de dados
- **Upload de backup**: interface para enviar arquivo JSON de backup
- **Validação de integridade**: verifica formato e estrutura do arquivo antes de importar
- **Criação de posts**: recria todos os CPTs exportados com os mesmos dados
- **Preservação de IDs**: tenta manter IDs originais quando possível (se não conflitarem)
- **Relacionamentos**: restaura metadados que vinculam entidades (ex.: pets a clientes)

### Segurança
- **Capability obrigatória**: apenas usuários com `manage_options` podem exportar/restaurar
- **Nonces validados**: todas as ações protegidas contra CSRF
- **Acesso negado a anônimos**: requisições não autenticadas são bloqueadas
- **Log de operações**: registra exportações e restaurações via `DPS_Logger`

## Shortcodes, widgets e endpoints

### Shortcodes
Este add-on não expõe shortcodes próprios. A interface é acessada através da tela de configurações do plugin base (`[dps_configuracoes]`).

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
- Outros CPTs prefixados com `dps_` (serviços, campanhas, assinaturas, etc.)

### Tabelas customizadas
Este add-on EXPORTA tabelas customizadas prefixadas com `dps_` (ex.: `dps_transacoes`, `dps_parcelas`). O backup inclui estrutura (CREATE TABLE) e dados das tabelas.

### Options armazenadas
Este add-on exporta/importa options prefixadas com `dps_`. Não armazena options próprias permanentemente.

## Como usar (visão funcional)

### Para administradores

1. **Acessar configurações**:
   - Acesse a página com o shortcode `[dps_configuracoes]`
   - Clique na aba "Backup & Restauração"

2. **Exportar dados**:
   - Clique no botão "Exportar Dados"
   - Aguarde o processamento
   - Arquivo JSON será baixado automaticamente no navegador
   - Salve o arquivo em local seguro

3. **Restaurar dados**:
   - Na mesma aba, clique em "Escolher arquivo"
   - Selecione o arquivo `.json` de backup exportado anteriormente
   - Clique em "Restaurar Backup"
   - Aguarde o processamento (pode demorar alguns segundos)
   - Mensagem de sucesso confirma importação

### Fluxo típico de uso

```
BACKUP ANTES DE MIGRAÇÃO:
1. Administrador acessa configurações
2. Exporta dados para arquivo JSON
3. Salva arquivo localmente

MIGRAÇÃO PARA NOVO AMBIENTE:
1. Instala WordPress e plugins DPS no novo ambiente
2. Acessa configurações de backup
3. Faz upload do arquivo JSON
4. Clica em "Restaurar"
5. Sistema recria todos os dados
```

### Observações importantes

- **Não sobrescreve dados existentes**: se já houver posts com mesmos IDs, a importação pode falhar ou criar duplicatas
- **Use em ambientes limpos**: ideal para restaurar em WordPress novo ou após desinstalação completa do DPS
- **Tabelas customizadas não incluídas**: transações financeiras e outras tabelas devem ser exportadas/importadas separadamente

## Notas para desenvolvimento

### Convenções e padrões

Este add-on segue as diretrizes do repositório DPS:
- **[AGENTS.md](../../AGENTS.md)**: regras de desenvolvimento, versionamento, segurança
- **[ANALYSIS.md](../../ANALYSIS.md)**: arquitetura, integração com sistema de configurações do núcleo

### Fluxo obrigatório para mudanças

Ao modificar este add-on:

1. **Ler ANALYSIS.md** para entender hooks de configurações do plugin base
2. **Implementar** seguindo políticas de segurança (nonces, capabilities, sanitização)
3. **Atualizar ANALYSIS.md** se criar novos pontos de extensão
4. **Atualizar CHANGELOG.md** antes de criar tags
5. **Validar** em ambiente de testes antes de usar em produção

### Políticas de segurança

- ✅ **Capabilities**: verifica `manage_options` antes de qualquer ação
- ✅ **Nonces**: valida `_wpnonce` em exportação e importação
- ✅ **Sanitização**: valida estrutura JSON e sanitiza dados antes de importar
- ✅ **Escape**: saída escapada em mensagens de feedback
- ✅ **Validação de arquivo**: verifica extensão (apenas .json) e tamanho máximo (50 MB)
- ✅ **Validação de estrutura JSON**: verifica integridade do payload antes de restaurar
- ✅ **Sanitização de dados**: sanitiza meta keys, post status e campos de texto durante restauração

### Pontos de atenção

- **Timeout PHP**: backups muito grandes podem estourar `max_execution_time`; considerar processamento em lotes
- **Memória PHP**: JSON grande consome memória; verificar `memory_limit` adequado
- **Restauração destrutiva**: todos os dados existentes são removidos antes de restaurar; use em ambientes limpos

### Melhorias futuras sugeridas

- Processamento em lotes para backups grandes
- Compressão ZIP do arquivo JSON
- Agendamento de backups automáticos via cron
- Versionamento de backups com histórico
- Backup incremental (apenas mudanças desde último backup)
- Integração com DPS_Logger para auditoria de operações

## Histórico de mudanças (resumo)

### Principais marcos

- **v1.0.0**: Lançamento inicial com exportação/importação completa via JSON
  - Suporte a CPTs, metadados, tabelas customizadas, opções e arquivos
  - Interface administrativa sob menu Desi Pet Shower
  - Proteção de segurança com nonces e capabilities

Para o histórico completo de mudanças, consulte `CHANGELOG.md` na raiz do repositório.
