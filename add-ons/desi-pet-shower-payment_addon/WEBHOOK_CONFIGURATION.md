# Configuração de Webhook do Mercado Pago

Este guia explica como configurar o campo "Webhook secret" nas Configurações de Pagamentos do Desi Pet Shower e como configurar o webhook no painel do Mercado Pago.

## O que é o Webhook Secret?

O **Webhook secret** é uma chave de segurança que valida se as notificações de pagamento recebidas pelo seu site realmente vieram do Mercado Pago, e não de terceiros mal-intencionados tentando manipular o sistema.

Quando o Mercado Pago processa um pagamento (PIX, boleto, cartão), ele envia uma notificação automática para o seu site informando que o pagamento foi confirmado. O webhook secret garante que apenas notificações legítimas sejam processadas.

## Por que preciso configurar?

Sem o webhook secret configurado, o sistema **não processará** as notificações do Mercado Pago, e os pagamentos não serão marcados como "pagos" automaticamente no sistema DPS.

## Passo a passo de configuração

### 1. Gerar um secret seguro

Primeiro, você precisa gerar uma chave secreta forte. Você pode:

**Opção A**: Usar um gerador online como [RandomKeygen](https://randomkeygen.com/) - copie uma das chaves "Fort Knox Passwords"

**Opção B**: Gerar via linha de comando (Linux/Mac):
```bash
openssl rand -base64 32
```

**Opção C**: Criar manualmente uma senha forte com:
- Mínimo 20 caracteres
- Letras maiúsculas e minúsculas
- Números
- Caracteres especiais
- Exemplo: `Dps2024!Mp@Wh00k#Sec87Zt`

⚠️ **IMPORTANTE**: Guarde essa chave em local seguro (gerenciador de senhas). Você precisará dela novamente no passo 3.

### 2. Configurar no DPS

1. Acesse o painel administrativo do WordPress
2. Vá em **Desi Pet Shower > Pagamentos**
3. No campo **"Webhook secret"**, cole a chave que você gerou no passo 1
4. Clique em **"Salvar alterações"**

### 3. Configurar no Mercado Pago

Agora você precisa configurar o webhook no painel do Mercado Pago para que ele envie notificações para o seu site.

#### 3.1. Acesse o painel do Mercado Pago

1. Entre no [Painel do Mercado Pago](https://www.mercadopago.com.br/developers/panel)
2. Vá em **Suas integrações** (ou "Your integrations")
3. Clique na sua aplicação (ou crie uma nova se não tiver)

#### 3.2. Configure a URL do webhook

1. No menu lateral, clique em **Webhooks** (ou "Notificações")
2. Em "URL de notificação", insira a URL do seu site seguida de `?secret=SUA_CHAVE_SECRETA`

**Formato da URL**:
```
https://seusite.com.br?secret=Dps2024!Mp@Wh00k#Sec87Zt
```

⚠️ **Substitua**:
- `seusite.com.br` pelo domínio real do seu site
- `Dps2024!Mp@Wh00k#Sec87Zt` pela chave que você configurou no passo 2

**Exemplo real**:
```
https://desipetshower.com.br?secret=Dps2024!Mp@Wh00k#Sec87Zt
```

#### 3.3. Selecione os eventos

Marque os seguintes eventos para receber notificações:

- ✅ **Pagamentos** (Payments)
- ✅ **Assinaturas** (Subscriptions) - se usar planos recorrentes

Outros eventos são opcionais e não serão processados pelo DPS.

#### 3.4. Ative o webhook

1. Clique em **"Salvar"** ou **"Criar webhook"**
2. O Mercado Pago enviará uma notificação de teste
3. Se tudo estiver correto, o status do webhook ficará **"Ativo"** ✅

## Métodos alternativos de envio do secret

O DPS suporta 4 formas diferentes de enviar o webhook secret. Use o método que funcionar melhor com a interface do Mercado Pago:

### Método 1: Query parameter `?secret=` (RECOMENDADO)
```
https://seusite.com.br?secret=SUA_CHAVE
```

### Método 2: Query parameter `?token=`
```
https://seusite.com.br?token=SUA_CHAVE
```

### Método 3: Header HTTP `Authorization: Bearer`
Configure o Mercado Pago para enviar um header HTTP:
```
Authorization: Bearer SUA_CHAVE
```

### Método 4: Header HTTP `X-Webhook-Secret`
Configure o Mercado Pago para enviar um header HTTP customizado:
```
X-Webhook-Secret: SUA_CHAVE
```

## Validação e testes

### Como saber se está funcionando?

Após configurar o webhook:

1. Crie um agendamento de teste no DPS
2. Marque como "Finalizado" para gerar um link de pagamento
3. Acesse o link e faça um pagamento de teste (Mercado Pago oferece sandbox para testes)
4. Aguarde alguns segundos

**Se funcionou corretamente**:
- O agendamento mudará de status "finalizado" para "finalizado_pago"
- Uma transação será criada/atualizada em Finanças
- Você receberá um e-mail de confirmação (se configurado)

**Se não funcionou**:
- Confira se o webhook secret no DPS é idêntico ao configurado no Mercado Pago
- Verifique se a URL no Mercado Pago está correta
- Ative logs do WordPress para ver erros (veja seção abaixo)

### Verificar logs de webhook

Para verificar se as notificações estão chegando e sendo processadas:

1. Ative o modo de debug do WordPress em `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

2. Verifique o arquivo `wp-content/debug.log`

3. Procure por linhas com `[DPS Pagamentos]`:
```
[DPS Pagamentos] Notificação do Mercado Pago recebida | {"raw":"..."}
[DPS Pagamentos] Status do agendamento atualizado | {"appointment_id":123,"status":"approved"}
```

### Erros comuns

**Erro: "Unauthorized" (401)**
- ❌ O secret configurado no DPS não bate com o enviado pelo Mercado Pago
- ✅ Solução: Confira se copiou a chave corretamente em ambos os lugares

**Erro: Pagamento não atualiza automaticamente**
- ❌ Webhook não está ativo no Mercado Pago
- ❌ URL do webhook está incorreta
- ❌ Firewall/plugin de segurança está bloqueando notificações
- ✅ Solução: Verifique configuração do webhook no painel do Mercado Pago

**Erro: "Notificação ignorada por idempotência"**
- ℹ️ Isso é normal! O Mercado Pago envia a mesma notificação várias vezes
- ℹ️ O DPS processa apenas a primeira vez e ignora duplicatas

## Fallback: Se não configurar o webhook secret

Se você **não configurar** o webhook secret, o DPS usará o **Access Token** como fallback temporário. Isso funciona, mas **NÃO é recomendado** pois:

- ❌ O Access Token é enviado em requisições públicas e pode vazar
- ❌ Menos seguro que um secret dedicado
- ⚠️ Pode deixar de funcionar em futuras atualizações do Mercado Pago

**Por segurança, sempre configure um webhook secret dedicado.**

## Perguntas frequentes

### Posso usar qualquer senha como secret?

Sim, mas recomendamos senhas fortes (mínimo 20 caracteres, com números e símbolos). Evite senhas fracas como "123456" ou "mercadopago".

### Preciso configurar isso para cada site?

Sim. Cada instalação do DPS precisa ter seu próprio webhook secret único. **Nunca reutilize** a mesma chave em múltiplos sites.

### O secret precisa ser o mesmo em sandbox e produção?

Não. Use secrets diferentes para ambiente de testes (sandbox) e produção. Isso aumenta a segurança e facilita identificar de onde vêm as notificações nos logs.

### Posso mudar o secret depois de configurado?

Sim. Basta atualizar tanto no DPS quanto no painel do Mercado Pago. Não afetará pagamentos já processados.

### O webhook funciona com Elementor/WooCommerce/outro plugin?

Sim. O webhook do DPS opera independentemente de page builders ou plugins de e-commerce. Ele apenas precisa que o DPS esteja ativo e corretamente configurado.

## Suporte técnico

Se após seguir este guia você ainda tiver problemas:

1. Confira os logs do WordPress (`wp-content/debug.log`)
2. Verifique se o plugin base DPS e o Payment Add-on estão atualizados
3. Teste a URL do webhook diretamente em ferramentas como [Webhook.site](https://webhook.site)
4. Entre em contato com o suporte em [probst.pro](https://probst.pro)

## Referências técnicas

- [Documentação de Webhooks do Mercado Pago](https://www.mercadopago.com.br/developers/pt/docs/your-integrations/notifications/webhooks)
- [ANALYSIS.md](../../ANALYSIS.md) - Arquitetura técnica do DPS
- [Código do Payment Add-on](desi-pet-shower-payment-addon.php) - Implementação da validação (linhas 770-789)
