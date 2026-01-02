# desi.pet by PRObst – Pagamentos Add-on

Integração com Mercado Pago para geração de links de pagamento e processamento de webhooks.

> 📌 **CONFIGURAÇÃO OBRIGATÓRIA**: Para que os pagamentos sejam confirmados automaticamente, você precisa configurar o **Webhook secret**. [**Veja o guia completo aqui →**](WEBHOOK_CONFIGURATION.md)

## Visão geral

O **Pagamentos Add-on** integra o sistema DPS com o Mercado Pago, permitindo geração automática de links de pagamento PIX/boleto e processamento de notificações de webhook para atualização de status de cobranças. É essencial para pet shops que desejam oferecer pagamento digital aos clientes.

Funcionalidades principais:
- Geração de links de pagamento via API do Mercado Pago
- Processamento de webhooks para atualização automática de status
- Suporte a PIX e outros métodos de pagamento do Mercado Pago
- Injeção de mensagens de cobrança com links no WhatsApp (via Agenda Add-on)
- Sincronização com Finance Add-on para atualizar transações

**Tipo**: Add-on (extensão do plugin base DPS)

## Localização e identificação

- **Diretório**: `add-ons/desi-pet-shower-payment_addon/`
- **Slug**: `dps-payment-addon`
- **Classe principal**: (verificar no arquivo principal)
- **Arquivo principal**: (verificar diretório)
- **Tipo**: Add-on (depende do Finance Add-on)

## Dependências e compatibilidade

### Dependências obrigatórias
- **desi.pet by PRObst Base**: v1.0.0 ou superior
- **Finance Add-on**: obrigatório para criar/atualizar transações
- **WordPress**: 6.0 ou superior
- **PHP**: 7.4 ou superior (com extensão cURL)

### Dependências opcionais
- **Agenda Add-on**: para enviar links de pagamento via WhatsApp
- **Client Portal Add-on**: para exibir links de pagamento na área do cliente

### Versão
- **Introduzido em**: v0.1.0 (estimado)
- **Compatível com plugin base**: v1.0.0+

## Funcionalidades principais

### Geração de links de pagamento
- **PIX**: gera link com QR Code para pagamento instantâneo
- **Boleto**: gera boleto bancário com código de barras
- **Outros métodos**: suporte a cartão de crédito, débito (conforme configuração do Mercado Pago)
- **Expiração configurável**: define validade do link/boleto
- **Valores dinâmicos**: calcula valor da transação automaticamente

### Processamento de webhooks
- **Notificações em tempo real**: recebe callbacks do Mercado Pago quando pagamento é confirmado
- **Atualização automática**: marca transação como "paga" no Finance Add-on
- **Validação de segurança**: verifica assinatura do webhook para evitar fraudes
- **Idempotência**: evita processar mesma notificação múltiplas vezes

### Integração com outros add-ons
- **Finance**: busca/atualiza transações na tabela `dps_transacoes`
- **Agenda**: injeta links de pagamento em mensagens de WhatsApp
- **Client Portal**: exibe botões de pagamento para pendências

## Shortcodes, widgets e endpoints

### Shortcodes
Este add-on não expõe shortcodes públicos. Opera através de funções chamadas por outros add-ons.

### Endpoints webhook

- **`/wp-json/dps/v1/mercadopago-webhook`** (ou similar)
  - **Método**: POST
  - **Origem**: servidores do Mercado Pago
  - **Propósito**: receber notificações de mudança de status de pagamento
  - **Validação**: verifica assinatura HMAC ou token configurado

## Hooks (actions e filters) relevantes

### Hooks CONSUMIDOS por este add-on

Este add-on processa webhooks cedo no ciclo de inicialização do WordPress, antes de hooks padrão.

### Hooks DISPARADOS por este add-on

Este add-on não dispara hooks customizados próprios. Atualiza diretamente dados no Finance Add-on.

## Dados armazenados (CPTs, tabelas, options)

### Custom Post Types
Este add-on NÃO cria CPTs próprios.

### Tabelas customizadas
Este add-on NÃO cria tabelas próprias. Utiliza `dps_transacoes` do Finance Add-on.

### Options armazenadas

- **`dps_mp_access_token`**: token de acesso à API do Mercado Pago
- **`dps_pix_key`**: chave PIX configurada no Mercado Pago
- **`dps_mp_webhook_secret`**: segredo para validação de webhooks (opcional)

## Como usar (visão funcional)

### Para administradores

1. **Configurar credenciais**:
   - Acesse **desi.pet by PRObst > Pagamentos** no painel do WordPress
   - Insira **Access Token** do Mercado Pago (obtido no painel do MP)
   - Insira **chave PIX** (se aplicável)
   - Configure **Webhook secret** - veja [guia completo de configuração](WEBHOOK_CONFIGURATION.md)
   - Configure URL de webhook no painel do Mercado Pago (formato: `https://seusite.com.br?secret=SUA_CHAVE`)

   **⚠️ IMPORTANTE**: O webhook secret é obrigatório para que pagamentos sejam confirmados automaticamente. [Leia o guia completo aqui](WEBHOOK_CONFIGURATION.md).

2. **Gerar link de pagamento**:
   - Via Finance Add-on, localize transação pendente
   - Clique em "Gerar Link de Pagamento"
   - Sistema cria link via API do Mercado Pago
   - Link é exibido para copiar/compartilhar

3. **Enviar link ao cliente**:
   - Copie link manualmente e envie via WhatsApp/e-mail, OU
   - Use integração com Agenda Add-on para envio automático

4. **Acompanhar pagamentos**:
   - Webhooks atualizam status automaticamente (se configurado corretamente)
   - Verifique transação marcada como "paga" no Finance Add-on

### Fluxo automático

```
1. Cliente tem pendência financeira
2. Administrador gera link de pagamento via Finance Add-on
3. Link é enviado ao cliente (manual ou automático)
4. Cliente paga via PIX/boleto
5. Mercado Pago envia webhook para o site
6. Payment Add-on valida e processa webhook (verifica webhook secret)
7. Transação é marcada como "paga" em dps_transacoes
8. Finance dispara hook dps_finance_booking_paid
9. Loyalty bonifica pontos (se ativo)
```

## Notas para desenvolvimento

### Convenções e padrões

Este add-on segue as diretrizes do repositório DPS:
- **[AGENTS.md](../../AGENTS.md)**: regras de desenvolvimento, versionamento, segurança
- **[ANALYSIS.md](../../ANALYSIS.md)**: integração com Finance, processamento de webhooks

### Fluxo obrigatório para mudanças

Ao modificar este add-on:

1. **Ler ANALYSIS.md** para entender integração com Finance e Agenda
2. **Implementar** seguindo políticas de segurança (validação de webhooks, sanitização)
3. **Testar em sandbox** do Mercado Pago antes de produção
4. **Atualizar ANALYSIS.md** se criar novos endpoints ou fluxos
5. **Atualizar CHANGELOG.md** antes de criar tags

### Políticas de segurança

- ✅ **Tokens sensíveis**: NUNCA commitar access tokens em código
- ✅ **Validação de webhook**: verificar assinatura HMAC ou IP de origem
- ✅ **Sanitização**: validar dados recebidos do webhook antes de processar
- ✅ **Idempotência**: verificar se pagamento já foi processado antes de marcar como pago
- ✅ **Logs**: registrar webhooks recebidos via `DPS_Logger` para auditoria

### Integração com Finance Add-on

- Buscar transação por ID ou referência externa (ID do Mercado Pago)
- Atualizar campo `status` e `paid_date` na tabela `dps_transacoes`
- Verificar se hook `dps_finance_booking_paid` é disparado corretamente

### Pontos de atenção

- **Webhook duplicados**: Mercado Pago pode enviar mesma notificação múltiplas vezes
- **Timeout**: API do Mercado Pago pode demorar; usar timeout adequado em requisições
- **Sandbox vs Produção**: diferentes credenciais e URLs para testes e produção
- **Expiração de links**: links PIX expiram rapidamente (geralmente 15-30 minutos)
- **Validação de origem**: verificar IP de origem do webhook (lista do Mercado Pago)

### Melhorias futuras sugeridas

- Suporte a outros gateways (PagSeguro, PayPal)
- Geração de links via shortcode para página de checkout
- Relatório de transações processadas via webhook
- Retry automático para webhooks falhados
- Integração com cartão de crédito recorrente (assinaturas)

## Histórico de mudanças (resumo)

### Principais marcos

- **v0.1.0**: Lançamento inicial com integração Mercado Pago (PIX, boleto), processamento de webhooks e sincronização com Finance Add-on

Para o histórico completo de mudanças, consulte `CHANGELOG.md` na raiz do repositório.
