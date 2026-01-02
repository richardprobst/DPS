# desi.pet by PRObst – Financeiro Add-on

Gerenciamento completo de transações financeiras, cobranças e parcelas.

## Visão geral

O **Financeiro Add-on** é o núcleo de gestão financeira do sistema DPS. Ele gerencia todas as transações financeiras, sincroniza cobranças com agendamentos, suporta quitação parcial e geração de documentos, e fornece infraestrutura compartilhada para outros add-ons (Pagamentos, Assinaturas, Fidelidade).

Funcionalidades principais:
- Criação e gestão de lançamentos financeiros (receitas e despesas)
- Sincronização automática de cobranças com agendamentos
- Suporte a parcelas e quitação parcial
- Geração de documentos (recibos, boletos)
- Tabelas compartilhadas (`dps_transacoes`, `dps_parcelas`) usadas por outros add-ons
- Hook `dps_finance_booking_paid` para integração com Fidelidade e outros

**Tipo**: Add-on (extensão do plugin base DPS)

## Localização e identificação

- **Diretório**: `plugins/desi-pet-shower-finance/`
- **Slug**: `dps-finance-addon`
- **Classe principal**: `DPS_Finance_Addon`
- **Arquivo principal**: `desi-pet-shower-finance-addon.php` (arquivo com cabeçalho de plugin)
- **Arquivo de compatibilidade**: `desi-pet-shower-finance.php` (apenas para retrocompatibilidade, sem cabeçalho de plugin)
- **Tipo**: Add-on (depende do plugin base)

## Dependências e compatibilidade

### Dependências obrigatórias
- **desi.pet by PRObst Base**: v1.0.0 ou superior (obrigatório)
- **WordPress**: 6.0 ou superior
- **PHP**: 7.4 ou superior
- **Extensões PHP**: MySQLi (para queries customizadas)

### Dependências opcionais
- **Payment Add-on**: para processar pagamentos via Mercado Pago
- **Subscription Add-on**: para gerenciar cobranças recorrentes
- **Loyalty Add-on**: para bonificações ao marcar cobranças como pagas

### Versão
- **Introduzido em**: v0.1.0
- **Versão atual**: (verificar header do plugin)
- **Compatível com plugin base**: v1.0.0+

## Funcionalidades principais

### Gestão de transações
- **Lançamentos financeiros**: criação de receitas e despesas vinculadas a agendamentos
- **Sincronização automática**: ao criar/concluir agendamento, lançamento financeiro é gerado
- **Edição de valores**: permite ajustar valores, datas de vencimento e observações
- **Histórico completo**: lista todas as transações com filtros por período, status, cliente

### Sistema de parcelas
- **Parcelamento**: suporte a divisão de cobranças em múltiplas parcelas
- **Controle individual**: cada parcela tem status próprio (paga, pendente, vencida)
- **Quitação parcial**: permite marcar apenas algumas parcelas como pagas
- **Recálculo automático**: ao alterar valor total, parcelas são recalculadas

### Documentos e relatórios
- **Geração de recibos**: emite recibos em PDF para cobranças quitadas
- **Boletos**: (se integrado com gateway) gera boletos bancários
- **Relatórios financeiros**: exportação de lançamentos por período em CSV/Excel
- **Dashboard financeiro**: resumo de receitas, despesas, inadimplência

### Integração com agendamentos
- **Lançamento automático**: ao concluir agendamento, cria transação vinculada
- **Limpeza em cascata**: ao excluir agendamento, remove lançamentos relacionados (via hook)
- **Vínculo bidirecional**: transação guarda ID do agendamento; agendamento pode ter múltiplas transações

## Shortcodes, widgets e endpoints

### Shortcodes
Este add-on pode expor shortcodes para exibir resumos financeiros (verificar documentação interna).

## Hooks (actions e filters) relevantes

### Hooks CONSUMIDOS por este add-on

#### `dps_finance_cleanup_for_appointment` (action)
- **Propósito**: remover lançamentos financeiros ao excluir agendamento
- **Parâmetros**: `$appointment_id` (int)
- **Implementação**: busca transações vinculadas ao agendamento e as remove da tabela `dps_transacoes`

#### `dps_base_nav_tabs_*` (action)
- **Propósito**: adicionar aba "Financeiro" à navegação do painel base
- **Parâmetros**: variável conforme hook específico
- **Implementação**: renderiza tab na interface principal

#### `dps_base_sections_*` (action)
- **Propósito**: renderizar conteúdo da seção financeira
- **Parâmetros**: variável conforme hook específico
- **Implementação**: exibe listagem de transações, formulários, relatórios

### Hooks DISPARADOS por este add-on

#### `dps_finance_booking_paid` (action)
- **Momento**: Quando cobrança é marcada como paga
- **Parâmetros**: `$transaction_id` (int), `$client_id` (int), `$amount` (int em centavos)
- **Propósito**: Permitir outros add-ons reagirem a pagamentos (ex.: Loyalty bonificar pontos)
- **Consumido por**: Loyalty Add-on (para bonificações de indicação)

## Dados armazenados (CPTs, tabelas, options)

### Custom Post Types
Este add-on NÃO cria CPTs próprios; utiliza CPTs do núcleo para vincular transações.

### Tabelas customizadas

#### `{prefix}dps_transacoes`
Armazena lançamentos financeiros do sistema.

**Campos principais**:
- `id` (bigint): identificador único da transação
- `appointment_id` (bigint): ID do agendamento vinculado (FK para `wp_posts`)
- `client_id` (bigint): ID do cliente (FK para `dps_client`)
- `amount` (bigint): valor em centavos
- `status` (varchar): status (pendente, pago, cancelado, vencido)
- `due_date` (date): data de vencimento
- `paid_date` (date): data de pagamento efetivo
- `type` (varchar): tipo (receita, despesa)
- `description` (text): descrição/observações
- `created_at` (datetime): data de criação do registro

**Uso compartilhado**: Esta tabela é usada por Payment, Subscription e Loyalty add-ons. Mudanças de schema requerem migração cuidadosa.

---

#### `{prefix}dps_parcelas`
Armazena parcelas de cobranças parceladas.

**Campos principais**:
- `id` (bigint): identificador único da parcela
- `transaction_id` (bigint): ID da transação pai (FK para `dps_transacoes`)
- `installment_number` (int): número da parcela (1, 2, 3...)
- `amount` (bigint): valor da parcela em centavos
- `due_date` (date): vencimento da parcela
- `paid_date` (date): data de pagamento
- `status` (varchar): status (pendente, pago, vencido)

### Options armazenadas
- **`dps_finance_db_version`**: versão do schema das tabelas (controle de migrações)
- Configurações de documentos (dados da empresa para recibos, etc.)

## Como usar (visão funcional)

### Para administradores

1. **Acessar financeiro**:
   - No painel base (`[dps_base]`), clique na aba "Financeiro"
   - Visualize listagem de todas as transações

2. **Criar lançamento manual**:
   - Clique em "Nova Transação"
   - Selecione tipo (receita/despesa)
   - Informe valor, data de vencimento, descrição
   - Vincule a cliente/agendamento (opcional)
   - Escolha se deseja parcelar
   - Salve

3. **Marcar cobrança como paga**:
   - Localize transação na lista
   - Clique em "Marcar como Pago"
   - Informe data de pagamento
   - Confirme (dispara hook `dps_finance_booking_paid`)

4. **Visualizar parcelas**:
   - Clique em transação parcelada
   - Visualize lista de parcelas individuais
   - Marque parcelas como pagas conforme recebimentos

5. **Gerar relatórios**:
   - Use filtros de período e status
   - Clique em "Exportar CSV" ou "Gerar Relatório"
   - Arquivo será baixado

### Fluxo automático

```
1. Recepcionista cria agendamento no painel base
2. Agendamento é concluído
3. Finance Add-on cria transação automaticamente vinculada ao agendamento
4. Status inicial: "pendente"
5. Cliente paga
6. Administrador marca como "pago"
7. Hook dps_finance_booking_paid é disparado
8. Loyalty Add-on bonifica pontos (se ativo)
```

## Notas para desenvolvimento

### Convenções e padrões

Este add-on segue as diretrizes do repositório DPS:
- **[AGENTS.md](../../AGENTS.md)**: regras de desenvolvimento, versionamento, segurança, migrações
- **[ANALYSIS.md](../../ANALYSIS.md)**: arquitetura, tabelas compartilhadas, hooks expostos

### Estrutura de arquivos

Este add-on já segue o padrão modular recomendado:
- **`includes/`**: classes auxiliares para transações, parcelas, relatórios
- Arquivo principal apenas faz bootstrapping

### Fluxo obrigatório para mudanças

Ao modificar este add-on:

1. **Ler ANALYSIS.md** para entender tabelas compartilhadas e impacto em outros add-ons
2. **Implementar** seguindo políticas de segurança (prepared statements, capabilities)
3. **Migrar dados** se alterar schema de `dps_transacoes` ou `dps_parcelas`
4. **Atualizar ANALYSIS.md** se criar novos hooks ou mudar estrutura de dados
5. **Atualizar CHANGELOG.md** antes de criar tags
6. **Validar** em ambiente de testes com Payment, Subscription e Loyalty ativos

### Políticas de segurança

- ✅ **Prepared statements**: SEMPRE usar `$wpdb->prepare()` em queries customizadas
- ✅ **Capabilities**: verificar `dps_manage_finances` ou `manage_options`
- ✅ **Sanitização**: sanitizar valores monetários com `DPS_Money_Helper`
- ✅ **Escape**: escapar saída em listagens e relatórios
- ✅ **Nonces**: validar em formulários de criação/edição de transações

### Tabelas compartilhadas

**ATENÇÃO**: As tabelas `dps_transacoes` e `dps_parcelas` são usadas por múltiplos add-ons:
- **Payment Add-on**: grava status de pagamentos via webhook
- **Subscription Add-on**: cria transações recorrentes
- **Loyalty Add-on**: lê transações para bonificar pontos

**Antes de alterar schema**:
1. Documente mudança em ANALYSIS.md
2. Crie migração reversível
3. Teste com TODOS os add-ons que usam essas tabelas
4. Registre breaking change no CHANGELOG.md com versão MAJOR

### Helpers globais recomendados

- Use `DPS_Money_Helper::parse_brazilian_format()` ao salvar valores de formulários
- Use `DPS_Money_Helper::format_to_brazilian()` ao exibir valores
- Use `DPS_Query_Helper` para consultas de transações com paginação
- Use `DPS_Request_Validator` para validar formulários

### Pontos de atenção

- **Valores em centavos**: SEMPRE armazenar valores como int em centavos na tabela
- **Conversão de moeda**: usar helpers globais para conversão formato BR ↔ centavos
- **Transações órfãs**: limpar via hook `dps_finance_cleanup_for_appointment`
- **Performance**: indexar colunas `client_id`, `appointment_id`, `status` na tabela
- **Migrações**: versionar schema com option `dps_finance_db_version`

### Melhorias futuras sugeridas

- Dashboard financeiro com gráficos (receita mensal, inadimplência)
- Conciliação bancária (importar extratos OFX)
- Categorias de receitas/despesas
- Centro de custos
- Emissão de notas fiscais (integração com APIs de NFe)
- Relatórios gerenciais (DRE, fluxo de caixa)

## Histórico de mudanças (resumo)

### Principais marcos

- **v0.1.0**: Lançamento inicial com tabelas `dps_transacoes` e `dps_parcelas`, sincronização com agendamentos, hook `dps_finance_booking_paid`

### Melhorias implementadas
- Estrutura modular com classes em `includes/`
- Suporte a parcelas e quitação parcial
- Integração com Payment, Subscription e Loyalty via hooks

Para o histórico completo de mudanças, consulte `CHANGELOG.md` na raiz do repositório.
