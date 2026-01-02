# Finance Add-on - Validação da FASE 1

**Versão:** 1.3.1  
**Data:** 09/12/2025  
**Commit:** 5a7894f

Este documento descreve os testes de validação para as implementações da FASE 1 do Finance Add-on.

---

## F1.1 - Proteção de Documentos Financeiros

### Objetivo
Garantir que documentos HTML (notas e cobranças) não sejam acessíveis via URL direta sem autenticação.

### Cenários de Teste

#### Teste 1.1: Acesso Direto Bloqueado
**Pré-requisito:** Gerar um documento financeiro via interface  
**Passos:**
1. Acessar aba "Financeiro"
2. Clicar em "Gerar doc" para uma transação
3. Copiar URL antiga do documento (se houver): `/wp-content/uploads/dps_docs/Nota_...html`
4. Tentar acessar URL direta em navegador anônimo

**Resultado Esperado:**  
❌ **HTTP 403 Forbidden** ou página em branco (bloqueado por .htaccess)

#### Teste 1.2: Acesso Autenticado Funcional
**Pré-requisito:** Estar logado como administrador  
**Passos:**
1. Gerar documento financeiro via interface
2. Sistema redireciona para URL segura: `?dps_view_doc=123&_wpnonce=abc...`
3. Documento HTML deve exibir corretamente

**Resultado Esperado:**  
✅ **Documento exibido** com dados da transação, cliente e pet

#### Teste 1.3: Acesso Sem Permissão Negado
**Pré-requisito:** Fazer logout ou usar usuário sem capability `manage_options`  
**Passos:**
1. Copiar URL com nonce válido
2. Acessar URL deslogado ou com usuário editor/autor

**Resultado Esperado:**  
❌ **"Você não tem permissão para visualizar documentos financeiros."** (HTTP 403)

#### Teste 1.4: Nonce Expirado ou Inválido
**Pré-requisito:** URL com nonce corrompido ou expirado (> 24h)  
**Passos:**
1. Modificar parâmetro `_wpnonce` na URL
2. OU aguardar 24+ horas e tentar acessar URL

**Resultado Esperado:**  
❌ **"Link de segurança inválido ou expirado. Por favor, gere o documento novamente."** (HTTP 403)

#### Teste 1.5: Verificar .htaccess Criado
**Pré-requisito:** Desativar e reativar add-on  
**Passos:**
1. Navegar para `/wp-content/uploads/dps_docs/` via FTP/SSH
2. Verificar existência do arquivo `.htaccess`
3. Conteúdo deve conter: `Require all denied`

**Resultado Esperado:**  
✅ **Arquivo .htaccess presente** com regra de bloqueio

---

## F1.2 - Validação de Pagamentos Parciais

### Objetivo
Impedir que soma de parciais ultrapasse valor total da transação.

### Cenários de Teste

#### Teste 2.1: Parcial Dentro do Limite (Sucesso)
**Pré-requisito:** Transação de R$ 150,00 em aberto  
**Passos:**
1. Clicar em "Registrar parcial" na transação
2. Informar: Data = hoje, Valor = `50.00`, Método = PIX
3. Submeter formulário

**Resultado Esperado:**  
✅ **"Pagamento parcial registrado com sucesso!"**  
✅ Status permanece "em_aberto" (R$ 50 de R$ 150 pagos)

#### Teste 2.2: Parcial que Excede Total (Erro)
**Pré-requisito:** Transação de R$ 150,00 com R$ 80,00 já pagos (restante: R$ 70)  
**Passos:**
1. Clicar em "Registrar parcial"
2. Informar: Data = hoje, Valor = `100.00`, Método = Cartão
3. Submeter formulário

**Resultado Esperado:**  
❌ **Mensagem de erro vermelha:**  
```
ERRO: O valor informado (R$ 100,00) ultrapassa o saldo restante da transação.
Total: R$ 150,00 | Já pago: R$ 80,00 | Restante: R$ 70,00
```

#### Teste 2.3: Quitação Exata (Marca como Pago)
**Pré-requisito:** Transação de R$ 150,00 com R$ 80,00 já pagos  
**Passos:**
1. Registrar parcial de `70.00` (exato)
2. Verificar status da transação

**Resultado Esperado:**  
✅ **Status atualizado para "pago"**  
✅ Total de parcelas = R$ 150,00 (80 + 70)

#### Teste 2.4: Tolerância de Arredondamento
**Pré-requisito:** Transação de R$ 99,99  
**Passos:**
1. Registrar parcial de `99.99`
2. Sistema deve permitir mesmo com diferença de centavos devido a arredondamento

**Resultado Esperado:**  
✅ **Aceito** (tolerância de R$ 0,01 implementada)

---

## F1.3 - Índices de Banco de Dados

### Objetivo
Melhorar performance de queries em tabelas com grande volume de dados.

### Cenários de Teste

#### Teste 3.1: Verificar Criação de Índices
**Pré-requisito:** Acesso ao banco de dados (PHPMyAdmin/Adminer)  
**Passos:**
1. Desativar Finance Add-on
2. Reativar Finance Add-on (executa `activate()`)
3. Acessar tabela `wp_dps_transacoes`
4. Verificar índices criados:
   - `idx_finance_date_status` em `(data, status)`
   - `idx_finance_categoria` em `(categoria)`
   - `cliente_id` (KEY, já existia)
   - `agendamento_id` (KEY, já existia)

**Resultado Esperado:**  
✅ **4 índices presentes** (2 novos + 2 antigos)

#### Teste 3.2: Validar Uso de Índice em Query
**Pré-requisito:** 1.000+ registros em `dps_transacoes`  
**Passos:**
1. Executar no MySQL/PHPMyAdmin:
```sql
EXPLAIN SELECT * FROM wp_dps_transacoes 
WHERE data >= '2024-01-01' AND status = 'pago' 
ORDER BY data DESC;
```
2. Verificar coluna `key` na saída do EXPLAIN

**Resultado Esperado:**  
✅ **key = `idx_finance_date_status`** (índice sendo usado)  
✅ **type = `range` ou `ref`** (não `ALL`)

#### Teste 3.3: Performance de Filtro (Antes/Depois)
**Pré-requisito:** 10.000+ registros  
**Passos:**
1. Medir tempo de resposta com filtro:
   - Período: últimos 30 dias
   - Status: "em_aberto"
2. Comparar com versão anterior (se possível)

**Resultado Esperado:**  
⚡ **Redução de ~80% no tempo** (ex: de 2s para 400ms)

---

## F1.4 - Otimização do Gráfico Mensal

### Objetivo
Evitar timeout com grandes volumes de dados no gráfico de receitas/despesas.

### Cenários de Teste

#### Teste 4.1: Gráfico com Limite Automático
**Pré-requisito:** 5.000+ registros distribuídos em 24+ meses  
**Passos:**
1. Acessar aba "Financeiro" SEM aplicar filtro de data
2. Scroll até o gráfico de receitas/despesas
3. Verificar meses exibidos

**Resultado Esperado:**  
✅ **Gráfico exibe últimos 6 meses** (DPS_FINANCE_CHART_MONTHS = 6)  
✅ **Query limita a `data >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)`**  
✅ **Carregamento < 1 segundo**

#### Teste 4.2: Gráfico com Filtro do Usuário
**Pré-requisito:** Mesma base de teste 4.1  
**Passos:**
1. Aplicar filtro: De = 2020-01-01, Até = 2025-12-31
2. Clicar em "Filtrar"
3. Verificar gráfico

**Resultado Esperado:**  
✅ **Gráfico exibe TODOS os meses do período filtrado** (não limita)  
✅ **Respeita escolha do usuário**

#### Teste 4.3: Performance em Base Grande
**Pré-requisito:** 50.000+ registros  
**Passos:**
1. Acessar aba Financeiro sem filtros
2. Medir tempo de carregamento da página
3. Verificar uso de memória PHP (opcional, via logs)

**Resultado Esperado:**  
⚡ **Tempo < 2 segundos** (antes: timeout 30s+)  
⚡ **Uso de memória < 128MB** (antes: 512MB+)

#### Teste 4.4: Validar Query no Debug Log
**Pré-requisito:** Plugin Query Monitor instalado  
**Passos:**
1. Ativar Query Monitor
2. Acessar aba Financeiro
3. Verificar query `SELECT * FROM wp_dps_transacoes WHERE data >= ...`

**Resultado Esperado:**  
✅ **Query contém `WHERE data >= [12 meses atrás]`** quando sem filtros  
✅ **Query sem LIMIT quando filtros aplicados**

---

## Validação Geral

### Checklist de Ativação

- [ ] Desativar Finance Add-on v1.3.0
- [ ] Ativar Finance Add-on v1.3.1
- [ ] Verificar versão: `DPS_FINANCE_VERSION = 1.3.1`
- [ ] Verificar options:
  - [ ] `dps_transacoes_db_version = 1.3.1`
  - [ ] `dps_parcelas_db_version = 1.3.1`
- [ ] Verificar `.htaccess` em `/uploads/dps_docs/`
- [ ] Verificar índices no banco de dados

### Checklist de Funcionalidades

- [ ] Gerar documento funciona (URL segura)
- [ ] Enviar documento por email funciona
- [ ] Registrar parcial funciona (dentro do limite)
- [ ] Registrar parcial bloqueia (excede limite)
- [ ] Filtros de transações funcionam rápido
- [ ] Gráfico mensal carrega sem timeout
- [ ] Exportar CSV funciona
- [ ] Sincronização com Agenda funciona

### Checklist de Segurança

- [ ] Acesso direto a documento bloqueado
- [ ] Nonce inválido rejeita acesso
- [ ] Usuário sem permissão negado
- [ ] `.htaccess` presente e funcional
- [ ] Headers anti-indexação presentes

---

## Testes de Regressão

### Funcionalidades que NÃO devem quebrar

- [ ] Sincronização de status (Agenda → Finance)
- [ ] Webhook do Mercado Pago ainda funciona
- [ ] Portal do Cliente exibe pendências
- [ ] Loyalty bonifica pontos em pagamentos
- [ ] DRE simplificado ainda funciona
- [ ] AJAX de histórico de parcelas funciona
- [ ] Excluir parcela funciona
- [ ] Atualizar status manual funciona

---

## Problemas Conhecidos / Limitações

1. **Documentos já gerados:** URLs antigas ainda podem estar salvas em emails enviados antes da atualização. Sistema converte automaticamente quando acessadas.

2. **Compatibilidade backward:** Documentos gerados em v1.3.0 são migrados automaticamente para novo sistema na primeira visualização.

3. **Performance de índices:** Ganho de performance só é perceptível com > 1.000 registros. Bases menores não veem diferença significativa.

4. **Limite do gráfico:** Configurável via constante `DPS_FINANCE_CHART_MONTHS` (padrão: 6 meses exibidos, consulta 12 meses).

---

## Rollback (Se Necessário)

### Reverter para v1.3.0

1. Desativar Finance Add-on v1.3.1
2. Fazer backup do banco de dados
3. Instalar Finance Add-on v1.3.0
4. Ativar

**Nota:** Índices criados não são removidos automaticamente (não causam problemas). Opção `dps_fin_doc_path_*` permanece mas não é usada.

### Remover .htaccess (Restaurar Acesso Direto)

1. Conectar via FTP/SSH
2. Deletar `/wp-content/uploads/dps_docs/.htaccess`
3. Documentos voltam a ser acessíveis por URL direta ⚠️

---

## Suporte e Referências

- **Análise Completa:** `docs/review/FINANCIAL_ADDON_DEEP_ANALYSIS.md`
- **Resumo Executivo:** `docs/review/FINANCIAL_ADDON_SUMMARY.md`
- **CHANGELOG:** Seção "Security (Segurança)" - Finance Add-on v1.3.1
- **Commit:** 5a7894f - Implementar FASE 1 completa
