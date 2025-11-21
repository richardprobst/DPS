# Desi Pet Shower – Assinaturas Add-on

Gestão de planos de assinatura recorrentes para clientes.

## Visão geral

O **Assinaturas Add-on** permite oferecer planos de assinatura mensal/anual para clientes, gerando cobranças recorrentes automaticamente. Integra-se com Finance Add-on para criar transações e com Payment Add-on para gerar links de renovação via Mercado Pago. Ideal para pet shops que oferecem pacotes de serviços mensais.

Funcionalidades principais:
- Criação de planos de assinatura (mensal, trimestral, anual)
- Vinculação de clientes a planos
- Geração automática de cobranças recorrentes
- Links de renovação via Mercado Pago
- Envio de lembretes de renovação via WhatsApp
- Controle de status (ativa, suspensa, cancelada)

**Tipo**: Add-on (extensão do plugin base DPS)

## Localização e identificação

- **Diretório**: `add-ons/desi-pet-shower-subscription_addon/`
- **Slug**: `dps-subscription-addon`
- **Classe principal**: (verificar no arquivo principal)
- **Arquivo principal**: (verificar diretório)
- **Tipo**: Add-on (depende de Finance e Payment add-ons)

## Dependências e compatibilidade

### Dependências obrigatórias
- **Desi Pet Shower Base**: v1.0.0 ou superior
- **Finance Add-on**: obrigatório para criar transações recorrentes
- **Payment Add-on**: obrigatório para gerar links de renovação Mercado Pago
- **WordPress**: 6.0 ou superior
- **PHP**: 7.4 ou superior

### Dependências opcionais
- **Communications Add-on**: para enviar lembretes de renovação via WhatsApp

### Versão
- **Introduzido em**: v0.2.0 (estimado)
- **Compatível com plugin base**: v1.0.0+

## Funcionalidades principais

### Planos de assinatura
- **Cadastro de planos**: criar planos com nome, valor, periodicidade, benefícios
- **Periodicidade**: mensal, trimestral, semestral, anual
- **Valor fixo**: preço da assinatura por período
- **Benefícios**: descrever serviços inclusos (ex.: "2 banhos + 1 tosa por mês")

### Gestão de assinantes
- **Vincular cliente a plano**: associar cliente a plano de assinatura
- **Data de início**: definir quando assinatura começa
- **Status**: ativa, suspensa, cancelada
- **Renovação automática**: gerar cobrança a cada período

### Cobranças recorrentes
- **Geração automática**: cron job cria transação em `dps_transacoes` a cada período
- **Integração com Finance**: transações aparecem na aba Financeiro
- **Links de pagamento**: gera link Mercado Pago automaticamente via Payment Add-on
- **Envio de lembrete**: notifica cliente via WhatsApp (se Communications ativo)

### Controle de inadimplência
- **Suspensão automática**: suspende assinatura após X dias de atraso
- **Reativação**: permite reativar ao quitar pendências
- **Cancelamento**: cliente ou administrador pode cancelar

## Shortcodes, widgets e endpoints

### Shortcodes
Este add-on não expõe shortcodes públicos.

## Hooks (actions e filters) relevantes

### Hooks CONSUMIDOS por este add-on

#### Hooks do Finance Add-on
- **Funções para criar transações**: usa APIs do Finance para criar cobranças recorrentes
- **Tabela `dps_transacoes`**: grava transações de assinatura com tipo específico

#### Hooks do Payment Add-on
- **Funções para gerar links**: usa APIs do Payment para criar links de pagamento Mercado Pago

### Hooks DISPARADOS por este add-on

Este add-on não dispara hooks customizados próprios.

## Dados armazenados (CPTs, tabelas, options)

### Custom Post Types

#### `dps_subscription`
Armazena planos de assinatura.

**Metadados principais**:
- **`subscription_price`**: valor da assinatura (em centavos)
- **`subscription_period`**: periodicidade (monthly, quarterly, annually)
- **`subscription_benefits`**: descrição de benefícios inclusos
- **`subscription_max_renewals`**: número máximo de renovações (0 = ilimitado)

### Metadados em clientes

#### Em `dps_client`
- **`client_subscription_id`**: ID do plano de assinatura ativo
- **`client_subscription_status`**: status (active, suspended, canceled)
- **`client_subscription_start_date`**: data de início da assinatura
- **`client_subscription_next_billing`**: data da próxima cobrança

### Tabelas utilizadas (não criadas por este add-on)
- **`dps_transacoes`** (do Finance Add-on): armazena cobranças de renovação

### Tabelas customizadas
Este add-on NÃO cria tabelas próprias.

### Options armazenadas
Este add-on não armazena options globais (configurações gerais vão em planos individuais).

## Como usar (visão funcional)

### Para administradores

1. **Criar plano de assinatura**:
   - No painel, crie novo post do tipo `dps_subscription`
   - Preencha:
     - Nome: "Plano Gold Mensal"
     - Valor: R$ 150,00
     - Periodicidade: Mensal
     - Benefícios: "2 banhos + 1 tosa + hidratação inclusa"
   - Publique

2. **Vincular cliente a plano**:
   - Na ficha do cliente, localize seção "Assinatura"
   - Selecione plano criado
   - Defina data de início
   - Salve

3. **Acompanhar renovações**:
   - Cron job gera cobranças automaticamente
   - Transações aparecem na aba Financeiro
   - Links de pagamento são enviados via WhatsApp (se configurado)

4. **Gerenciar inadimplência**:
   - Sistema suspende assinaturas com atraso > X dias (configurável)
   - Administrador pode reativar manualmente ao receber pagamento

5. **Cancelar assinatura**:
   - Na ficha do cliente, clique em "Cancelar Assinatura"
   - Confirme ação
   - Cron job para de gerar cobranças

### Fluxo automático

```
1. Cliente A assina "Plano Gold Mensal" em 01/11/2024
2. Sistema cria vinculação em client_subscription_id
3. Define próxima cobrança: 01/12/2024

[Dia 01/12/2024]
4. Cron job executa
5. Verifica assinaturas com next_billing = hoje
6. Cria transação em dps_transacoes (Finance)
7. Gera link de pagamento via Payment Add-on
8. Envia WhatsApp com link (se Communications ativo)
9. Atualiza next_billing para 01/01/2025

[Cliente paga]
10. Webhook do Mercado Pago atualiza status para "pago"
11. Assinatura permanece ativa

[Cliente NÃO paga em 15 dias]
12. Cron job verifica atrasos
13. Suspende assinatura (status = suspended)
14. Envia notificação de suspensão
```

## Notas para desenvolvimento

### Convenções e padrões

Este add-on segue as diretrizes do repositório DPS:
- **[AGENTS.md](../../AGENTS.md)**: regras de desenvolvimento, versionamento, segurança
- **[ANALYSIS.md](../../ANALYSIS.md)**: integração com Finance e Payment, uso de tabela compartilhada `dps_transacoes`

### Fluxo obrigatório para mudanças

Ao modificar este add-on:

1. **Ler ANALYSIS.md** para entender dependências de Finance e Payment
2. **Implementar** seguindo políticas de segurança (validação de renovações, valores)
3. **Testar** fluxo completo de renovação automática
4. **Atualizar ANALYSIS.md** se mudar fluxo de integração
5. **Atualizar CHANGELOG.md** antes de criar tags

### Políticas de segurança

- ✅ **Validação de valores**: garantir que preço é número positivo
- ✅ **Sanitização**: usar `DPS_Money_Helper` para conversões monetárias
- ✅ **Capabilities**: verificar permissões antes de criar/cancelar assinaturas
- ✅ **Idempotência**: evitar criar cobranças duplicadas para mesma renovação
- ✅ **Logs**: registrar renovações via `DPS_Logger` para auditoria

### Integração estreita com Finance e Payment

**ATENÇÃO**: Este add-on depende fortemente de Finance e Payment. Mudanças nesses add-ons podem quebrar funcionalidade de assinaturas.

- Sempre validar que Finance e Payment estão ativos antes de executar cron jobs
- Usar `function_exists()` ou `class_exists()` para verificar APIs disponíveis

### Cron jobs

- **Renovações**: executar diariamente para verificar assinaturas a renovar
- **Suspensões**: executar diariamente para suspender inadimplentes
- **Deactivation hook**: limpar cron jobs ao desativar plugin

### Pontos de atenção

- **Timezone**: garantir que datas de renovação respeitam timezone do WordPress
- **Falhas de pagamento**: implementar lógica de retry ou notificação manual
- **Cancelamento vs Suspensão**: deixar claro diferença (suspensão é temporária, cancelamento é definitivo)
- **Upgrade/downgrade de planos**: considerar implementar mudança de plano mid-cycle
- **Proporcionalidade**: cobrar valor proporcional se cliente mudar plano no meio do período

### Melhorias futuras sugeridas

- Upgrade/downgrade de planos com cálculo proporcional
- Período de teste gratuito (trial)
- Descontos por tempo de assinatura (fidelidade)
- Pausar assinatura temporariamente (férias do cliente)
- Relatório de churn (taxa de cancelamento)
- Integração com cartão de crédito recorrente (Mercado Pago Subscriptions)

## Histórico de mudanças (resumo)

### Principais marcos

- **v0.2.0**: Lançamento inicial com planos de assinatura, cobranças recorrentes, integração Finance/Payment e lembretes de renovação

Para o histórico completo de mudanças, consulte `CHANGELOG.md` na raiz do repositório.
