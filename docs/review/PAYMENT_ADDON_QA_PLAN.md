# Plano de Testes Funcionais - Payment Add-on v1.2.0

**Data**: 2026-01-03  
**Escopo**: Verificação funcional completa do Payment Add-on

---

## 1. Matriz de Funcionalidades

| # | Feature | Localização | Como Acionar | Resultado Esperado |
|---|---------|-------------|--------------|-------------------|
| 1 | Página de Configurações | Admin > desi.pet by PRObst > Pagamentos | Acessar menu | Página renderiza com 3 campos + indicador de status |
| 2 | Campo Access Token | Página de Configurações | Preencher e salvar | Token sanitizado salvo em `dps_mercadopago_access_token` |
| 3 | Campo Chave PIX | Página de Configurações | Preencher e salvar | Chave salva em `dps_pix_key` |
| 4 | Campo Webhook Secret | Página de Configurações | Preencher e salvar | Secret sanitizado salvo em `dps_mercadopago_webhook_secret` |
| 5 | Indicador de Status | Página de Configurações | Visual automático | Badge verde "Integração configurada" ou amarelo "Configuração pendente" |
| 6 | Prevenção de Duplo Clique | Página de Configurações | Clicar no botão Salvar | Botão desabilitado + texto "Salvando..." |
| 7 | Geração de Link de Pagamento | Agendamento finalizado | Mudar status para "finalizado" | Meta `dps_payment_link` criado com URL do Mercado Pago |
| 8 | Injeção de Link no WhatsApp | Botão WhatsApp na Agenda | Clicar em "Enviar Cobrança" | Mensagem contém link de pagamento e chave PIX |
| 9 | Processamento de Webhook | Notificação do Mercado Pago | POST com payload de pagamento | Status do agendamento atualizado para `finalizado_pago` |
| 10 | Rate Limiting | Webhook com secret errado | 10+ tentativas inválidas | IP bloqueado por 5 minutos |
| 11 | Idempotência | Webhook duplicado | Mesmo `notification_id` enviado 2x | Segunda notificação ignorada |
| 12 | Email de Confirmação | Pagamento aprovado | Webhook com status `approved` | Email enviado para admin |

---

## 2. Casos de Teste Detalhados

### TC-001: Salvar Configurações com Valores Válidos

**Pré-condição**: Usuário logado como administrador

**Passos**:
1. Acessar Admin > desi.pet by PRObst > Pagamentos
2. Preencher Access Token com valor válido (ex: `TEST-123456789012345678901234567890`)
3. Preencher Chave PIX (ex: `11999999999`)
4. Preencher Webhook Secret (ex: `minhaChaveSecreta2024!@#`)
5. Clicar em "Salvar configurações"

**Resultado Esperado**:
- Botão mostra "Salvando..." e fica desabilitado
- Página recarrega com mensagem de sucesso do WordPress
- Indicador mostra "✓ Integração configurada"
- Campos mantêm os valores salvos

---

### TC-002: Salvar Configurações com Campos Vazios

**Pré-condição**: Configurações já preenchidas

**Passos**:
1. Acessar página de configurações
2. Apagar todos os campos
3. Clicar em "Salvar"

**Resultado Esperado**:
- Configurações salvas vazias
- Indicador mostra "⚠ Configuração pendente - Access Token não configurado"

---

### TC-003: Sanitização de Access Token

**Passos**:
1. Preencher Access Token com: `TEST-123<script>alert('xss')</script>`
2. Salvar

**Resultado Esperado**:
- Token sanitizado para: `TEST-123scriptalertxssscript`
- Caracteres especiais removidos

---

### TC-004: Constantes em wp-config.php

**Pré-condição**: Adicionar em wp-config.php:
```php
define('DPS_MERCADOPAGO_ACCESS_TOKEN', 'TEST-CONST-TOKEN');
```

**Passos**:
1. Acessar página de configurações

**Resultado Esperado**:
- Campo Access Token mostra `••••OKEN` (últimos 4 chars)
- Campo desabilitado com texto "✓ Definido em wp-config.php"
- Não é possível editar via interface

---

### TC-005: Geração de Link de Pagamento

**Pré-condição**: Access Token configurado, agendamento criado

**Passos**:
1. Editar agendamento existente
2. Definir valor total (ex: R$ 150,00)
3. Mudar status para "Finalizado"
4. Salvar

**Resultado Esperado**:
- Meta `dps_payment_link` criado com URL do Mercado Pago
- Meta `_dps_payment_link_status` = `success`
- Log registra geração do link

---

### TC-006: Fallback quando Token não Configurado

**Pré-condição**: Access Token vazio

**Passos**:
1. Criar agendamento com valor
2. Mudar status para "Finalizado"
3. Clicar em "Enviar Cobrança" na Agenda

**Resultado Esperado**:
- Link de fallback usado: `https://link.mercadopago.com.br/desipetshower`
- Meta `_dps_payment_link_status` = `error`
- Log registra erro "Access token do Mercado Pago não configurado"

---

### TC-007: Webhook com Secret Válido

**Pré-condição**: Webhook Secret configurado

**Passos**:
1. Enviar POST para `https://site.com?secret=SUA_CHAVE`:
```json
{
  "type": "payment",
  "data": { "id": "12345678" }
}
```

**Resultado Esperado**:
- Resposta HTTP 200 com body "OK"
- Log registra processamento
- Agendamento atualizado se `external_reference` válida

---

### TC-008: Webhook com Secret Inválido

**Passos**:
1. Enviar POST para `https://site.com?secret=ERRADO`:
```json
{
  "type": "payment",
  "data": { "id": "12345678" }
}
```

**Resultado Esperado**:
- Resposta HTTP 401 com body "Unauthorized"
- Log registra "Tentativa de webhook com secret inválido"
- Contador de rate limiting incrementado

---

### TC-009: Rate Limiting de Webhook

**Passos**:
1. Enviar 11 requisições consecutivas com secret errado

**Resultado Esperado**:
- Requisições 1-10: Resposta 401
- Requisição 11: Resposta 401 + log "Rate limit excedido para webhook"
- IP bloqueado por 5 minutos (transient criado)

---

### TC-010: Idempotência de Notificações

**Pré-condição**: Webhook configurado, agendamento com link de pagamento

**Passos**:
1. Enviar webhook com `notification_id: "abc123"`
2. Enviar mesmo webhook novamente

**Resultado Esperado**:
- Primeira requisição: Processada normalmente
- Segunda requisição: Log "Notificação ignorada por idempotência", resposta 200

---

### TC-011: Verificação de Tabela dps_transacoes

**Pré-condição**: Finance Add-on desativado

**Passos**:
1. Processar webhook de pagamento aprovado

**Resultado Esperado**:
- Agendamento atualizado para `finalizado_pago`
- Log registra "Tabela dps_transacoes não existe"
- Nenhum erro SQL gerado

---

### TC-012: Acessibilidade - Navegação por Teclado

**Passos**:
1. Acessar página de configurações
2. Navegar usando TAB entre os campos

**Resultado Esperado**:
- Cada campo recebe foco visível (outline azul)
- Ordem de tabulação lógica: Token → PIX → Secret → Botão
- Links com indicação para leitores de tela "(abre em nova aba)"

---

## 3. Casos de Erro e Edge Cases

| Cenário | Comportamento Esperado |
|---------|----------------------|
| API Mercado Pago offline | Log de erro, `_dps_payment_link_status` = `error` |
| Timeout em request HTTP | Tratado como erro, log registrado |
| Resposta HTTP 4xx/5xx | Erro logado com código e mensagem |
| Webhook sem `external_reference` | Ignorado com log explicativo |
| Webhook para agendamento inexistente | Ignorado sem erro |
| Assinatura ao invés de agendamento | Processada via `mark_subscription_paid()` |
| Payload JSON malformado | Ignorado, não é identificado como notificação MP |

---

## 4. Sugestões de Testes E2E (Playwright/Cypress)

```javascript
// Exemplo Playwright
describe('Payment Add-on Settings', () => {
  test('should show pending status when not configured', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=dps-payment-settings');
    await expect(page.locator('.dps-payment-status--pending')).toBeVisible();
  });

  test('should show configured status when all fields filled', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=dps-payment-settings');
    await page.fill('#dps_mercadopago_access_token', 'TEST-TOKEN');
    await page.fill('#dps_mercadopago_webhook_secret', 'secret123');
    await page.click('#dps-payment-submit');
    await expect(page.locator('.dps-payment-status--configured')).toBeVisible();
  });

  test('should prevent double submit', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=dps-payment-settings');
    await page.click('#dps-payment-submit');
    await expect(page.locator('#dps-payment-submit')).toBeDisabled();
  });

  test('should have accessible labels', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=dps-payment-settings');
    const tokenField = page.locator('#dps_mercadopago_access_token');
    await expect(tokenField).toHaveAttribute('aria-describedby', 'dps_mercadopago_access_token-description');
  });
});
```

---

## 5. Checklist de Validação Funcional

### Formulários
- [x] Validação server-side (sanitize callbacks)
- [x] Prevenção de duplo clique
- [x] Estado de loading no botão
- [x] Mensagens de feedback (via Settings API)
- [x] Persistência correta em options

### Botões e Ações
- [x] Botão salvar desabilitado durante submit
- [x] Capability `manage_options` verificada
- [x] Feedback visual de processamento

### Acessibilidade
- [x] Labels com `id` e `aria-describedby`
- [x] Focus visible em navegação por teclado
- [x] `rel="noopener"` em links externos
- [x] Screen reader text para contexto

### Webhooks e API
- [x] Rate limiting implementado
- [x] Idempotência garantida
- [x] Tratamento de erros HTTP
- [x] Logging de auditoria
- [x] Timeout configurado

### Integração
- [x] Geração de link funciona com Mercado Pago
- [x] Injeção em mensagem WhatsApp
- [x] Atualização de status de agendamento
- [x] Criação de transação no Finance (quando ativo)

---

*Documento gerado como parte da verificação funcional do desi.pet by PRObst.*
