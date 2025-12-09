# Resumo Executivo do Add-on Financeiro - DPS by PRObst

**Plugin:** DPS by PRObst – Financeiro  
**Versão Analisada:** 1.3.0  
**Data da Análise:** 09/12/2025  
**Contexto:** Sistema de Banho e Tosa / Pet Shop

---

## 1. VISÃO GERAL

### 1.1 Função do Add-on no Sistema

O **Finance Add-on** é a espinha dorsal financeira do sistema DPS, responsável por:

- **Controle de Receitas e Despesas**: Registro completo de transações financeiras
- **Cobranças Vinculadas a Atendimentos**: Integração automática com a Agenda
- **Gestão de Pendências**: Acompanhamento de pagamentos em aberto
- **Documentação Fiscal**: Geração de notas e cobranças em HTML
- **Sincronização de Status**: Atualização automática de status entre Agenda e Financeiro
- **Pagamentos Parciais**: Suporte a quitação fracionada de cobranças

**Arquitetura Modular:**
```
desi-pet-shower-finance_addon/
├── desi-pet-shower-finance-addon.php (2.526 linhas) - Arquivo principal
├── includes/
│   ├── class-dps-finance-api.php (562 linhas) - API pública para add-ons
│   ├── class-dps-finance-settings.php (177 linhas) - Configurações centralizadas
│   └── class-dps-finance-revenue-query.php (54 linhas) - Consultas de receita
├── assets/
│   ├── css/finance-addon.css - Estilos da interface
│   └── js/finance-addon.js - Interações AJAX
└── uninstall.php - Limpeza na desinstalação
```

### 1.2 Integração com Outros Módulos

| Módulo | Tipo de Integração | Descrição |
|--------|-------------------|-----------|
| **Agenda** | Bidirecional | Cria cobranças quando atendimento é finalizado; atualiza status financeiro quando pagamento é confirmado |
| **Payment (Mercado Pago)** | Consumidor | Payment add-on atualiza status via webhook; Finance registra transações |
| **Portal do Cliente** | Provedor | Finance Repository fornece dados de pendências e histórico de pagamentos |
| **Subscription** | Provedor | Finance armazena cobranças recorrentes de assinaturas |
| **Loyalty** | Observador | Loyalty reage ao hook `dps_finance_booking_paid` para bonificar pontos |

### 1.3 Pontos Fortes

✅ **API Bem Definida**: `DPS_Finance_API` centraliza operações, evitando manipulação direta de tabelas  
✅ **Sincronização Automática**: Status de agendamentos reflete automaticamente no financeiro  
✅ **Pagamentos Parciais**: Sistema flexível de quitação fracionada com histórico completo  
✅ **Segurança Reforçada**: Nonces em todas as ações, verificação de capabilities (`manage_options`)  
✅ **Helpers Globais**: Uso correto de `DPS_Money_Helper` para conversão monetária  
✅ **Filtros Avançados**: Data, categoria, status, intervalos rápidos (7/30 dias)  
✅ **Exportação CSV**: Facilita análise externa de dados financeiros

### 1.4 Pontos Fracos e Riscos

⚠️ **Arquivo Principal Muito Grande**: 2.526 linhas violam o princípio Single Responsibility  
⚠️ **Falta de Relatórios Gerenciais**: Apenas resumo básico; sem gráficos de evolução mensal  
⚠️ **UX Fragmentada**: Funcionalidades espalhadas; falta dashboard centralizado  
⚠️ **Reenvio de Links Manual**: Não há botão rápido para reenviar cobrança pendente  
⚠️ **Indicadores de Inadimplência**: Falta painel de "A receber hoje/esta semana"  
⚠️ **Performance em Grandes Volumes**: Queries sem paginação podem travar com milhares de registros  
⚠️ **Ausência de Auditoria**: Não registra quem alterou status de transações manualmente

---

## 2. PRINCIPAIS FLUXOS FINANCEIROS

### 2.1 Fluxo de Cobrança Padrão

```
1. [AGENDA] Atendimento criado/editado
   ↓
2. [AGENDA] Status alterado para "Finalizado" ou "Finalizado Pago"
   ↓
3. [FINANCE] Hook sync_status_to_finance disparado
   ↓
4. [FINANCE] Verifica se já existe transação para o agendamento
   ↓
5A. [FINANCE] Se existe → Atualiza valor, status e descrição
5B. [FINANCE] Se não existe → Cria nova transação
   ↓
6. [PAYMENT] (Opcional) Gera link de pagamento Mercado Pago
   ↓
7. [CLIENTE] Acessa link e efetua pagamento
   ↓
8. [MERCADO PAGO] Envia webhook de confirmação
   ↓
9. [PAYMENT] Valida webhook e atualiza meta appointment_status
   ↓
10. [FINANCE] Detecta mudança e marca transação como "pago"
   ↓
11. [FINANCE] Dispara hook dps_finance_booking_paid
   ↓
12. [LOYALTY] (Se ativo) Bonifica pontos ao cliente
```

**Pontos Críticos:**
- Sincronização depende de metas corretas no agendamento (`appointment_total_value`, `_dps_total_at_booking`)
- Webhook do Mercado Pago DEVE conter `external_reference` no formato `dps_appointment_{ID}`
- Alteração manual de status na Agenda dispara recriação/atualização de transação

### 2.2 Fluxo de Pagamento Parcial

```
1. [ADMIN] Acessa aba Financeiro
   ↓
2. [ADMIN] Clica em "Registrar parcial" em transação pendente
   ↓
3. [FINANCE] Exibe formulário com data, valor e método
   ↓
4. [ADMIN] Submete formulário
   ↓
5. [FINANCE] Insere registro na tabela dps_parcelas
   ↓
6. [FINANCE] Soma total de parcelas pagas
   ↓
7A. [FINANCE] Se total >= valor da transação → Status = "pago"
7B. [FINANCE] Se total < valor da transação → Status = "em_aberto"
   ↓
8. [FINANCE] Redireciona com mensagem de sucesso
```

**Vantagens:**
- Flexibilidade para negócios que aceitam pagamento em múltiplas parcelas
- Histórico completo de pagamentos parciais via AJAX

**Limitações:**
- Não há validação de valor máximo (pode ultrapassar o total)
- Falta integração com Mercado Pago para pagamentos parcelados

### 2.3 Fluxo de Geração de Documentos

```
1. [ADMIN] Clica em "Gerar doc" na lista de transações
   ↓
2. [FINANCE] Verifica nonce e permissão
   ↓
3. [FINANCE] Consulta transação no banco
   ↓
4. [FINANCE] Determina tipo (nota = pago, cobrança = em_aberto)
   ↓
5. [FINANCE] Monta HTML com dados da loja (DPS_Finance_Settings)
   ↓
6. [FINANCE] Salva arquivo em wp-content/uploads/dps_docs/
   ↓
7. [FINANCE] Armazena URL em option dps_fin_doc_{trans_id}
   ↓
8. [FINANCE] Redireciona para visualização do documento
```

**Pontos Positivos:**
- Reutiliza documentos já gerados (não duplica arquivos)
- Nome de arquivo estruturado: `Nota_Cliente_Pet_Data.html` ou `Cobranca_Cliente_Pet_Data.html`

**Pontos de Melhoria:**
- HTML básico sem CSS inline (impressão pode ficar desformatada)
- Não gera PDF (depende de impressão do navegador)
- Falta opção de personalização de template

---

## 3. INTEGRAÇÃO COM MERCADO PAGO

### 3.1 Divisão de Responsabilidades

**Finance Add-on (desi-pet-shower-finance_addon):**
- ✅ Armazena transações na tabela `dps_transacoes`
- ✅ Fornece API pública (`DPS_Finance_API::create_or_update_charge`)
- ✅ Sincroniza status com agendamentos
- ✅ Dispara hook `dps_finance_booking_paid` quando pago

**Payment Add-on (desi-pet-shower-payment_addon):**
- ✅ Gerencia credenciais do Mercado Pago (Access Token, Webhook Secret)
- ✅ Cria preferências de pagamento via API MP
- ✅ Processa webhooks de confirmação de pagamento
- ✅ Atualiza meta `appointment_status` após validação

### 3.2 Fluxo de Webhook (Crítico para Segurança)

```php
// Payment Add-on valida webhook:
1. Verifica se requisição contém assinatura MP
2. Valida secret contra DPS_MERCADOPAGO_WEBHOOK_SECRET (constante ou option)
3. Consulta API do MP para confirmar status do pagamento
4. Extrai external_reference (ex: dps_appointment_123)
5. Atualiza meta appointment_status para "finalizado_pago"

// Finance Add-on reage via hook:
6. Hook updated_post_meta detecta mudança
7. Método sync_status_to_finance atualiza dps_transacoes
8. Dispara dps_finance_booking_paid para Loyalty e outros add-ons
```

**Segurança Implementada:**
- ✅ Validação de webhook secret
- ✅ Consulta à API MP para confirmar dados (não confia apenas no POST)
- ✅ Idempotência de notificações (evita duplicatas)
- ✅ Logging completo em `wp-content/uploads/dps_logs/payment_notifications.log`

**Riscos Residuais:**
- ⚠️ Webhook secret pode ser armazenado em wp_options (menos seguro que constante)
- ⚠️ Falta rate limiting para webhooks (potencial DDoS)
- ⚠️ Não há alerta de falha de webhook (admin pode não perceber pagamentos não confirmados)

---

## 4. TABELAS DE BANCO DE DADOS

### 4.1 Estrutura de dps_transacoes

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `id` | BIGINT | PK auto increment |
| `cliente_id` | BIGINT | FK para wp_posts (dps_cliente) |
| `agendamento_id` | BIGINT | FK para wp_posts (dps_agendamento) |
| `plano_id` | BIGINT | FK para wp_posts (dps_subscription) |
| `data` | DATE | Data da transação |
| `valor` | DECIMAL(10,2) | Valor em reais (ex: 129.90) |
| `categoria` | VARCHAR(100) | Categoria (ex: "Serviço", "Produto") |
| `tipo` | VARCHAR(50) | "receita" ou "despesa" |
| `status` | VARCHAR(50) | "em_aberto", "pago", "cancelado" |
| `descricao` | TEXT | Descrição detalhada |

**Índices Necessários (não implementados):**
```sql
CREATE INDEX idx_cliente ON dps_transacoes(cliente_id);
CREATE INDEX idx_agendamento ON dps_transacoes(agendamento_id);
CREATE INDEX idx_data_status ON dps_transacoes(data, status);
```

### 4.2 Estrutura de dps_parcelas

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `id` | BIGINT | PK auto increment |
| `trans_id` | BIGINT | FK para dps_transacoes |
| `data` | DATE | Data do pagamento parcial |
| `valor` | DECIMAL(10,2) | Valor pago nesta parcela |
| `metodo` | VARCHAR(50) | "pix", "cartao", "dinheiro", "outro" |

**Índice Necessário (não implementado):**
```sql
CREATE INDEX idx_trans ON dps_parcelas(trans_id);
```

---

## 5. RECURSOS ATUAIS VS. NECESSIDADES

| Recurso | Status Atual | Necessidade do Negócio | Prioridade |
|---------|--------------|------------------------|------------|
| **Registro de Transações** | ✅ Completo | Alta | - |
| **Sincronização com Agenda** | ✅ Completo | Alta | - |
| **Integração Mercado Pago** | ✅ Funcional | Alta | - |
| **Pagamentos Parciais** | ✅ Funcional | Média | - |
| **Filtros de Data/Categoria** | ✅ Completo | Alta | - |
| **Exportação CSV** | ✅ Completo | Média | - |
| **Geração de Documentos** | ⚠️ Básico (apenas HTML) | Alta | **Média** |
| **Dashboard Financeiro** | ⚠️ Resumo simples | Alta | **Alta** |
| **Gráficos de Evolução** | ❌ Ausente | Alta | **Alta** |
| **Painel de Pendências** | ❌ Ausente | Alta | **Alta** |
| **Reenvio de Link de Pagamento** | ❌ Ausente | Média | **Média** |
| **Relatório DRE** | ⚠️ Básico (apenas com filtro) | Média | **Baixa** |
| **Reconciliação com Extrato Bancário** | ❌ Ausente | Baixa | **Baixa** |
| **Auditoria de Alterações** | ❌ Ausente | Média | **Baixa** |

---

## 6. PONTOS DE ATENÇÃO PARA O NEGÓCIO

### 6.1 Segurança ⭐⭐⭐⭐☆ (4/5)

**Pontos Fortes:**
- ✅ Nonces em todas as ações (CSRF protection)
- ✅ Verificação de capability `manage_options`
- ✅ Sanitização de entrada (`wp_unslash`, `sanitize_text_field`)
- ✅ Queries com `$wpdb->prepare()` (SQL injection protection)
- ✅ Validação de webhook do Mercado Pago

**Pontos de Melhoria:**
- ⚠️ Documentos financeiros em HTML ficam acessíveis por URL direta (sem autenticação)
- ⚠️ Shortcode `[dps_fin_docs]` verificado apenas para `manage_options` (corrigido na v1.3.0)
- ⚠️ Logs de pagamento podem conter dados sensíveis

### 6.2 Performance ⭐⭐⭐☆☆ (3/5)

**Pontos Fortes:**
- ✅ Paginação na listagem de transações (20 por página)
- ✅ Uso de `DPS_Money_Helper` evita cálculos float imprecisos

**Pontos de Melhoria:**
- ⚠️ Gráfico mensal carrega TODOS os registros sem limite de data
- ⚠️ Queries sem índices em `cliente_id`, `agendamento_id`, `data`
- ⚠️ Relatório DRE não pagina resultados
- ⚠️ Busca de categorias distintas sem cache (`get_col` sem transient)

### 6.3 Usabilidade ⭐⭐⭐☆☆ (3/5)

**Pontos Fortes:**
- ✅ Interface limpa com fieldsets semânticos
- ✅ Badges visuais de status (pago/pendente/cancelado)
- ✅ Filtros rápidos de 7 e 30 dias

**Pontos de Melhoria:**
- ⚠️ Falta botão de "Reenviar link de pagamento" na linha da transação
- ⚠️ Indicadores de pendências não são destacados visualmente
- ⚠️ Sem gráficos (apenas tabelas e números)
- ⚠️ Não mostra link de pagamento MP na lista (dificulta conferência)

---

## 7. RESUMO DE IMPACTO NO NEGÓCIO

### 7.1 O que Funciona Bem ✅

- **Cobrança Automática**: Atendimentos finalizados geram cobranças automaticamente
- **Sincronização Mercado Pago**: Pagamentos confirmados atualizam sistema em tempo real
- **Flexibilidade de Pagamento**: Suporta pagamento parcial para clientes que precisam parcelar
- **Rastreabilidade**: Transações vinculadas a agendamentos, clientes e pets

### 7.2 O que Precisa Melhorar 🔧

- **Visibilidade de Pendências**: Difícil saber quem deve quanto e há quanto tempo
- **Acompanhamento de Inadimplência**: Falta painel de "Vencidos hoje/esta semana"
- **Facilidade de Cobrança**: Não há botão rápido para reenviar link de pagamento
- **Visão Gerencial**: Falta gráfico de evolução mensal, comparativo com meses anteriores

### 7.3 Riscos ao Negócio ⚠️

| Risco | Impacto | Probabilidade | Mitigação |
|-------|---------|---------------|-----------|
| **Webhook falhar silenciosamente** | Alto | Média | Implementar alertas de falha |
| **Performance degradar com volume** | Médio | Alta | Adicionar índices no banco |
| **Cliente não pagar e ficar invisível** | Alto | Média | Criar painel de inadimplentes |
| **Erro de cálculo de valor parcial** | Médio | Baixa | Validar valor máximo no formulário |

---

## 8. CONCLUSÃO

O **Finance Add-on v1.3.0** é um módulo **funcional e seguro**, cumprindo bem seu papel de registrar transações e sincronizar com a Agenda e Mercado Pago. 

**Principais Conquistas:**
- ✅ Integração sólida com Payment Add-on
- ✅ Segurança reforçada (CSRF, SQL injection, validação de webhook)
- ✅ API pública bem documentada para extensões

**Principais Limitações:**
- ⚠️ UX básica sem recursos visuais (gráficos, dashboards)
- ⚠️ Falta ferramentas de gestão de inadimplência
- ⚠️ Performance pode degradar com grande volume de transações

**Recomendação Geral:**

Priorizar **Fase 2 (UX do dia a dia)** para tornar o módulo mais útil à equipe operacional, especialmente:
1. Painel de pendências destacado
2. Botão de reenvio de link de pagamento
3. Gráfico de evolução mensal

Em seguida, implementar **Fase 3 (Relatórios gerenciais)** para fornecer visão estratégica ao dono do negócio.

---

**Próximos Passos:**  
Consultar documento **FINANCIAL_ADDON_DEEP_ANALYSIS.md** para análise técnica detalhada e plano de melhorias em fases.
