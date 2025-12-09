# Finance Add-on – Fase 4: Extras Avançados (Selecionados)

**Versão:** 1.6.0  
**Data:** 09/12/2025  
**Status:** ✅ Implementado e testado

## Visão Geral

A **Fase 4** do Finance Add-on implementa recursos avançados para automatização, rastreabilidade e integrações externas. Conforme solicitação do usuário, foram implementados **3 dos 5 recursos** planejados para esta fase.

### Recursos Implementados

1. **F4.2** – Lembretes automáticos de pagamento
2. **F4.4** – Auditoria de alterações financeiras
3. **F4.5** – API REST de consulta financeira (read-only)

### Recursos NÃO Implementados (conforme solicitação)

- ❌ **F4.1** – Reconciliação com extrato bancário
- ❌ **F4.3** – Suporte a outros gateways de pagamento

---

## F4.2 – Lembretes Automáticos de Pagamento

### Funcionalidade

Sistema completo de lembretes automáticos que envia notificações para clientes com cobranças pendentes.

**Características**:
- ✅ Habilitação via checkbox (on/off)
- ✅ Configuração de dias antes do vencimento (padrão: 1)
- ✅ Configuração de dias após vencimento (padrão: 1)
- ✅ Mensagens customizáveis com placeholders
- ✅ Processamento diário automático via WP-Cron
- ✅ Sistema de flags para evitar envio duplicado

### Arquitetura Técnica

**Classe**: `DPS_Finance_Reminders` (`includes/class-dps-finance-reminders.php`)

**Evento Cron**:
```php
Hook: 'dps_finance_process_payment_reminders'
Frequência: daily
Handler: DPS_Finance_Reminders::process_reminders()
```

**Opções do WordPress**:
- `dps_finance_reminders_enabled` (yes/no)
- `dps_finance_reminder_days_before` (integer, 0-30)
- `dps_finance_reminder_days_after` (integer, 0-30)
- `dps_finance_reminder_message_before` (text)
- `dps_finance_reminder_message_after` (text)

**Sistema de Flags** (via Transients):
```php
// Impede reenvio de lembrete antes do vencimento
set_transient('dps_reminder_before_' . $trans_id, timestamp, 7 * DAY_IN_SECONDS);

// Impede reenvio de lembrete após vencimento
set_transient('dps_reminder_after_' . $trans_id, timestamp, 7 * DAY_IN_SECONDS);
```

### Lógica de Processamento

1. **Verificação de habilitação**: Se `dps_finance_reminders_enabled !== 'yes'`, retorna cedo
2. **Cálculo de datas alvo**:
   - Antes: `hoje + dias_antes`
   - Depois: `hoje - dias_depois`
3. **Busca de transações elegíveis**:
   - Tipo: receita
   - Status: em_aberto
   - Data de vencimento = data alvo
4. **Verificação de flags**: Se já enviou recentemente (< 7 dias), pula
5. **Envio de lembrete**: Via sistema de comunicações (WhatsApp/Email)
6. **Registro de flag**: Marca como enviado com TTL de 7 dias

### Placeholders Disponíveis

```php
{cliente}  // Nome do cliente
{pet}      // Nome do pet
{data}     // Data do atendimento (dd/mm/yyyy)
{valor}    // Valor formatado (R$ 0,00)
{link}     // Link de pagamento (se disponível)
{pix}      // Chave PIX da loja
{loja}     // Nome da loja
```

### Como Usar

**Habilitar Lembretes**:
1. Acesse aba **Financeiro**
2. Clique em **"⚙️ Configurações Avançadas"**
3. Marque ☑️ **"Enviar lembretes automáticos de pagamento"**
4. Configure dias antes/depois (ex: 1 dia antes, 3 dias depois)
5. Personalize mensagens (opcional)
6. Clique em **"Salvar Configurações"**

**Testar Manualmente**:
```php
// Via WP-CLI (requer WP-CLI instalado)
wp cron event run dps_finance_process_payment_reminders

// Via código (adicionar temporariamente em functions.php)
do_action('dps_finance_process_payment_reminders');
```

**Logs**:
- Ativações/erros registrados em `error_log`
- Verificar logs do servidor: `/var/log/apache2/error.log` ou similar

---

## F4.4 – Auditoria de Alterações Financeiras

### Funcionalidade

Sistema de rastreamento completo de todas as alterações realizadas nas transações financeiras.

**Características**:
- ✅ Registro automático de todas as mudanças
- ✅ Captura de quem alterou (user_id)
- ✅ Captura de quando alterou (timestamp)
- ✅ Captura de valores antes/depois (from → to)
- ✅ Captura de IP do usuário
- ✅ Tela de visualização com filtros
- ✅ Paginação (20 registros/página)

### Arquitetura Técnica

**Classe**: `DPS_Finance_Audit` (`includes/class-dps-finance-audit.php`)

**Tabela**: `wp_dps_finance_audit_log`

```sql
CREATE TABLE wp_dps_finance_audit_log (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    trans_id bigint(20) NOT NULL,
    user_id bigint(20) DEFAULT 0,
    action varchar(50) NOT NULL,
    from_status varchar(50) DEFAULT NULL,
    to_status varchar(50) DEFAULT NULL,
    from_value varchar(50) DEFAULT NULL,
    to_value varchar(50) DEFAULT NULL,
    meta_info text DEFAULT NULL,
    ip_address varchar(50) DEFAULT 'unknown',
    created_at datetime NOT NULL,
    PRIMARY KEY (id),
    KEY trans_id (trans_id),
    KEY created_at (created_at),
    KEY user_id (user_id)
);
```

### Tipos de Ação Registrados

| Action | Descrição | Quando Ocorre |
|--------|-----------|---------------|
| `status_change` | Mudança de Status | Cliente altera status via dropdown (em_aberto → pago) |
| `value_change` | Alteração de Valor | Edição manual do valor da transação |
| `partial_add` | Pagamento Parcial Adicionado | Registro de pagamento parcial manual |
| `manual_create` | Criação Manual | Nova transação criada manualmente via formulário |
| `status_change_webhook` | Status via Webhook | Atualização de status via webhook do gateway |

### Pontos de Integração

**1. Mudança de Status** (linha 822):
```php
// Antes de atualizar status
$old_status = $wpdb->get_var(...);

// Atualiza
$wpdb->update(...);

// Registra auditoria
DPS_Finance_Audit::log_event($id, 'status_change', [
    'from_status' => $old_status,
    'to_status'   => $new_status,
]);
```

**2. Criação Manual** (linha 778):
```php
// Após inserir transação
$new_trans_id = $wpdb->insert_id;

// Registra auditoria
DPS_Finance_Audit::log_event($new_trans_id, 'manual_create', [
    'to_status' => $status,
    'to_value'  => $valor_formatado,
    'meta_info' => ['category' => $categoria, ...]
]);
```

**3. Pagamento Parcial** (linha 708):
```php
// Após inserir parcela
$wpdb->insert(...);

// Registra auditoria
DPS_Finance_Audit::log_event($trans_id, 'partial_add', [
    'to_value'  => $valor_formatado,
    'meta_info' => ['method' => $metodo, 'date' => $data]
]);
```

### Como Usar

**Visualizar Histórico**:
1. Acesse **"⚙️ Configurações Avançadas"** na aba Financeiro
2. Clique em **"Ver Histórico de Auditoria"**
3. Use filtros:
   - **ID da Transação**: ver histórico de uma transação específica
   - **Data de/até**: filtrar por período

**Consultar via SQL** (para relatórios avançados):
```sql
-- Ver últimas 100 alterações
SELECT * FROM wp_dps_finance_audit_log 
ORDER BY created_at DESC 
LIMIT 100;

-- Ver histórico de uma transação
SELECT * FROM wp_dps_finance_audit_log 
WHERE trans_id = 123 
ORDER BY created_at DESC;

-- Ver quem mais altera transações
SELECT 
    u.display_name, 
    COUNT(*) as total_changes
FROM wp_dps_finance_audit_log a
JOIN wp_users u ON a.user_id = u.ID
GROUP BY user_id
ORDER BY total_changes DESC
LIMIT 10;
```

---

## F4.5 – API REST de Consulta Financeira

### Funcionalidade

Endpoints REST para consulta de dados financeiros, permitindo integrações externas e relatórios customizados.

**Características**:
- ✅ Somente leitura (GET apenas)
- ✅ Autenticação obrigatória
- ✅ Validação de permissões (`manage_options`)
- ✅ Filtros avançados
- ✅ Paginação completa
- ✅ Formatação monetária

### Arquitetura Técnica

**Classe**: `DPS_Finance_REST` (`includes/class-dps-finance-rest.php`)

**Namespace**: `dps-finance/v1`

**Base URL**: `https://seusite.com/wp-json/dps-finance/v1/`

### Endpoints Disponíveis

#### 1. GET /transactions

Lista transações com filtros opcionais.

**Parâmetros**:
```
status      : string (em_aberto|pago|cancelado)
date_from   : string (Y-m-d)
date_to     : string (Y-m-d)
customer    : integer (ID do cliente)
page        : integer (default: 1, min: 1)
per_page    : integer (default: 20, min: 1, max: 100)
```

**Exemplo de Requisição**:
```bash
curl -X GET \
  'https://seusite.com/wp-json/dps-finance/v1/transactions?status=em_aberto&per_page=10' \
  -u admin:senha
```

**Exemplo de Resposta**:
```json
[
  {
    "id": 123,
    "cliente_id": 45,
    "cliente_nome": "João Silva",
    "agendamento_id": 67,
    "data": "2025-12-15",
    "valor": 150.00,
    "valor_formatado": "R$ 150,00",
    "categoria": "Banho",
    "tipo": "receita",
    "status": "em_aberto",
    "descricao": "Banho e tosa completa"
  },
  ...
]
```

**Headers de Resposta**:
```
X-WP-Total: 45
X-WP-TotalPages: 5
```

#### 2. GET /transactions/{id}

Retorna detalhes de uma transação específica.

**Exemplo de Requisição**:
```bash
curl -X GET \
  'https://seusite.com/wp-json/dps-finance/v1/transactions/123' \
  -u admin:senha
```

**Exemplo de Resposta**:
```json
{
  "id": 123,
  "cliente_id": 45,
  "cliente_nome": "João Silva",
  "agendamento_id": 67,
  "data": "2025-12-15",
  "valor": 150.00,
  "valor_formatado": "R$ 150,00",
  "categoria": "Banho",
  "tipo": "receita",
  "status": "em_aberto",
  "descricao": "Banho e tosa completa",
  "created_at": "2025-12-09 10:30:00",
  "updated_at": "2025-12-09 10:30:00",
  "payment_link": "https://link.mercadopago.com.br/..."
}
```

#### 3. GET /summary

Retorna resumo financeiro por período.

**Parâmetros**:
```
period      : string (current_month|last_month|custom)
date_from   : string (Y-m-d) - obrigatório se period=custom
date_to     : string (Y-m-d) - obrigatório se period=custom
```

**Exemplo de Requisição**:
```bash
curl -X GET \
  'https://seusite.com/wp-json/dps-finance/v1/summary?period=current_month' \
  -u admin:senha
```

**Exemplo de Resposta**:
```json
{
  "period": {
    "type": "current_month",
    "date_from": "2025-12-01",
    "date_to": "2025-12-31"
  },
  "summary": {
    "total_receitas": 15500.00,
    "total_despesas": 3200.00,
    "total_pendente": 2500.00,
    "resultado": 12300.00
  },
  "formatted": {
    "total_receitas": "R$ 15.500,00",
    "total_despesas": "R$ 3.200,00",
    "total_pendente": "R$ 2.500,00",
    "resultado": "R$ 12.300,00"
  }
}
```

### Segurança

**Autenticação**:
- Todas as rotas requerem autenticação
- Suporta Basic Auth, Application Passwords, ou OAuth

**Permissões**:
```php
permission_callback: current_user_can('manage_options')
```

**Validação de Parâmetros**:
- Status: enum validado ('em_aberto', 'pago', 'cancelado')
- Datas: validação de formato (Y-m-d)
- Paginação: min/max enforced (1-100)
- IDs: sanitização com absint()

### Casos de Uso

**1. Dashboard Externo**:
```javascript
// Em React/Vue/Angular
async function fetchFinancialSummary() {
  const response = await fetch(
    'https://seusite.com/wp-json/dps-finance/v1/summary?period=current_month',
    {
      headers: {
        'Authorization': 'Basic ' + btoa('admin:senha')
      }
    }
  );
  
  const data = await response.json();
  return data.summary;
}
```

**2. Relatório em Excel/Google Sheets**:
```python
# Python script para exportar para CSV
import requests
import csv

auth = ('admin', 'senha')
url = 'https://seusite.com/wp-json/dps-finance/v1/transactions'
params = {'per_page': 100, 'page': 1}

response = requests.get(url, auth=auth, params=params)
transactions = response.json()

with open('financeiro.csv', 'w', newline='') as f:
    writer = csv.writer(f)
    writer.writerow(['ID', 'Cliente', 'Valor', 'Status', 'Data'])
    for t in transactions:
        writer.writerow([t['id'], t['cliente_nome'], t['valor_formatado'], t['status'], t['data']])
```

**3. Integração com Power BI / Tableau**:
- Usar endpoint `/transactions` como fonte de dados
- Autenticação via Application Passwords
- Refresh automático diário

---

## Comparação com Fases Anteriores

| Fase | Versão | Objetivo | Recursos |
|------|--------|----------|----------|
| **Fase 1** | 1.3.1 | Segurança & Performance | Documentos protegidos, validação parciais, índices SQL, query otimizada |
| **Fase 2** | 1.4.0 | UX do Dia a Dia | Cards pendências, reenviar link, badges visuais, indicadores vencimento, busca rápida |
| **Fase 3** | 1.5.0 | Relatórios & Visão | Gráfico evolução, DRE, PDF export, comparativo mensal, Top 10 clientes |
| **Fase 4** | 1.6.0 | Extras Avançados | **Lembretes automáticos, Auditoria, REST API** |

---

## Performance e Otimização

### Lembretes (F4.2)

**Otimizações**:
- Cron executa apenas 1x/dia (não sobrecarrega)
- Flags via transients (TTL: 7 dias, limpeza automática)
- Queries com filtros específicos (data + status)
- Early return se desabilitado

**Carga Estimada**:
- 100 transações em aberto: ~2s de processamento
- 1000 transações em aberto: ~15s de processamento

### Auditoria (F4.4)

**Otimizações**:
- Índices em trans_id, created_at, user_id
- INSERT não bloqueia operação principal (fail silently)
- Paginação (20/página) em visualização
- Queries preparadas ($wpdb->prepare)

**Carga Estimada**:
- INSERT de log: <0.001s (negligível)
- SELECT com filtros: <0.1s (até 10k registros)

### REST API (F4.5)

**Otimizações**:
- Limit/offset em queries (paginação)
- Validação de parâmetros antes de query
- Cache de formatação monetária (DPS_Money_Helper)
- Headers de paginação para controle cliente

**Carga Estimada**:
- GET /transactions (20 itens): <0.2s
- GET /transactions/{id}: <0.05s
- GET /summary: <0.1s

---

## Troubleshooting

### Lembretes não estão sendo enviados

**Verificações**:
1. ✅ Lembretes estão habilitados?
   ```php
   get_option('dps_finance_reminders_enabled') === 'yes'
   ```

2. ✅ Cron do WordPress está rodando?
   ```bash
   wp cron event list
   # Deve aparecer: dps_finance_process_payment_reminders
   ```

3. ✅ Há transações elegíveis?
   ```sql
   SELECT * FROM wp_dps_transacoes 
   WHERE tipo = 'receita' 
   AND status = 'em_aberto' 
   AND data = CURDATE() + INTERVAL 1 DAY;
   ```

4. ✅ Verificar logs:
   ```bash
   tail -f /var/log/apache2/error.log | grep "DPS Finance Reminders"
   ```

### Auditoria não está registrando

**Verificações**:
1. ✅ Tabela existe?
   ```sql
   SHOW TABLES LIKE 'wp_dps_finance_audit_log';
   ```

2. ✅ Classe está carregada?
   ```php
   class_exists('DPS_Finance_Audit') // Deve retornar true
   ```

3. ✅ Verificar permissões de escrita no banco

### REST API retorna 401/403

**Verificações**:
1. ✅ Autenticação correta?
   - Testar com Application Passwords (WP 5.6+)
   - Verificar se Basic Auth está habilitado

2. ✅ Usuário tem capability?
   ```php
   current_user_can('manage_options') // Deve ser true
   ```

3. ✅ Permalink settings configurados?
   - Ir em Configurações → Links Permanentes
   - Salvar novamente (flush rewrite rules)

---

## Próximos Passos (Fase 5 - Futuro)

Se houver necessidade de expandir, os recursos restantes seriam:

**F4.1 - Reconciliação Bancária**:
- Upload de extrato (CSV/OFX)
- Matching automático de transações
- Sugestões de conciliação
- Relatório de divergências

**F4.3 - Outros Gateways**:
- PagSeguro
- Pix nativo (API Banco Central)
- Cielo/Rede
- Stripe internacional

---

## Conclusão

A **Fase 4** do Finance Add-on transforma o módulo em uma plataforma completa de gestão financeira com:
- 🤖 **Automação** via lembretes programados
- 🔍 **Rastreabilidade** total com auditoria
- 🔌 **Integrações** via REST API padrão

**Impacto para o Negócio**:
- ⏱️ **Reduz inadimplência** com lembretes automáticos
- 🛡️ **Aumenta segurança** com histórico de alterações
- 📊 **Expande possibilidades** com dados via API

---

**Documentado em:** 09/12/2025  
**Autor:** Agente de Implementação Copilot  
**Revisão:** Pendente
