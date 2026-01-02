# desi.pet by PRObst – Campanhas & Fidelidade Add-on

Programa de pontos, indicações (Indique e Ganhe) e campanhas de marketing.

## Visão geral

O **Campanhas & Fidelidade Add-on** oferece três módulos integrados para engajamento e retenção de clientes: programa de pontos por faturamento, sistema "Indique e Ganhe" com recompensas para indicador e indicado, e criação de campanhas de marketing direcionadas.

Funcionalidades principais:
- **Programa de Pontos**: acumular pontos baseados em faturamento e resgatar benefícios
- **Níveis de Fidelidade**: Bronze, Prata e Ouro com multiplicadores de pontos
- **Indique e Ganhe**: códigos únicos por cliente, bonificações na primeira compra do indicado
- **Campanhas**: criação de ações promocionais com público-alvo segmentado
- Integração com Finance Add-on para bonificações automáticas
- Integração com Registration Add-on para capturar códigos de indicação
- Integração com Client Portal para exibir código/link de convite

**Tipo**: Add-on (extensão do plugin base DPS)

## Localização e identificação

- **Diretório**: `plugins/desi-pet-shower-loyalty/`
- **Slug**: `dps-loyalty-addon`
- **Classe principal**: `DPS_Loyalty_Addon`
- **Arquivo principal**: `desi-pet-shower-loyalty.php`
- **Tipo**: Add-on (depende do plugin base)

## Dependências e compatibilidade

### Dependências obrigatórias
- **desi.pet by PRObst Base**: v1.0.0 ou superior (obrigatório)
- **WordPress**: 6.0 ou superior
- **PHP**: 7.4 ou superior

### Dependências opcionais
- **Finance Add-on**: para bonificações automáticas ao marcar cobranças como pagas (recomendado)
- **Registration Add-on**: para capturar códigos de indicação no cadastro público (recomendado)
- **Client Portal Add-on**: para exibir código de indicação na área do cliente (recomendado)

### Versão
- **Introduzido em**: v0.2.0
- **Versão atual**: v1.2.0
- **Compatível com plugin base**: v1.0.0+

## Novidades da versão 1.2.0

- **Multiplicador de nível aplicado**: clientes Prata ganham 1.5x pontos, Ouro ganham 2x
- **Compartilhamento via WhatsApp**: botão para compartilhar código de indicação
- **Exportação CSV de indicações**: baixar relatório completo das indicações
- **Labels de contexto traduzidos**: histórico de pontos com descrições legíveis
- **Novos métodos na API**:
  - `calculate_points_for_amount()`: calcula pontos antes de conceder
  - `get_top_clients()`: ranking dos melhores clientes
  - `get_clients_by_tier()`: contagem por nível de fidelidade
  - `export_referrals_csv()`: exportação de indicações

## Funcionalidades principais

### Programa de Pontos
- **Acúmulo automático**: pontos creditados conforme faturamento (ex.: 1 ponto a cada R$ 10,00)
- **Regras configuráveis**: administrador define taxa de conversão e elegibilidade
- **Resgate de pontos**: clientes trocam pontos por descontos ou serviços gratuitos
- **Saldo disponível**: visualização no Portal do Cliente
- **Histórico de movimentações**: registro de acúmulos e resgates

### Indique e Ganhe
- **Código único**: cada cliente recebe código alfanumérico único (ex.: "MARIA2024")
- **Link compartilhável**: URL de cadastro com código pré-preenchido
- **Bonificação indicador**: crédito concedido quando indicado realiza primeira compra
- **Bonificação indicado**: desconto ou crédito para novo cliente na primeira compra
- **Rastreamento**: tabela `dps_referrals` armazena indicações e status de bonificação

### Campanhas de Marketing
- **Criação via CPT**: campanhas são custom post type `dps_campaign`
- **Segmentação**: público-alvo configurável (todos os clientes, clientes inativos, etc.)
- **Mensagens personalizadas**: templates com variáveis dinâmicas
- **Disparo manual ou automático**: integração com Communications Add-on para envio
- **Métricas**: acompanhamento de alcance e conversão

## Shortcodes, widgets e endpoints

### Shortcodes
Este add-on não expõe shortcodes públicos próprios. Funcionalidades são acessadas via painel administrativo e Portal do Cliente.

## Hooks (actions e filters) relevantes

### Hooks CONSUMIDOS por este add-on

#### `dps_registration_after_client_created` (action)
- **Propósito**: registrar indicação quando novo cliente é criado via cadastro público
- **Parâmetros**: `$client_id` (int)
- **Implementação**: verifica se requisição contém código de indicação, salva em tabela `dps_referrals`

#### `dps_finance_booking_paid` (action)
- **Propósito**: bonificar indicador e indicado quando primeira cobrança é paga
- **Parâmetros**: `$transaction_id` (int), `$client_id` (int), `$amount` (int)
- **Implementação**: busca indicação em `dps_referrals`, credita pontos/descontos para ambas as partes

#### `dps_base_nav_tabs_after_history` (action)
- **Propósito**: adicionar aba "Campanhas & Fidelidade" ao painel base
- **Parâmetros**: `$visitor_only` (bool)
- **Implementação**: renderiza tab na navegação principal

#### `dps_base_sections_after_history` (action)
- **Propósito**: renderizar conteúdo da aba de campanhas e fidelidade
- **Parâmetros**: `$active_tab` (string)
- **Implementação**: exibe interface de gerenciamento

### Hooks DISPARADOS por este add-on

Este add-on não dispara hooks customizados próprios.

## Dados armazenados (CPTs, tabelas, options)

### Custom Post Types

#### `dps_campaign`
Armazena campanhas de marketing criadas.

**Metadados principais**:
- `campaign_target_audience`: público-alvo (todos, inativos, vip, etc.)
- `campaign_message_template`: template de mensagem
- `campaign_start_date`: data de início
- `campaign_end_date`: data de término
- `campaign_status`: status (ativa, pausada, finalizada)

**Uso**: Administradores criam campanhas via interface do CPT.

### Tabelas customizadas

#### `{prefix}dps_referrals`
Armazena indicações de clientes.

**Campos principais**:
- `id` (bigint): identificador único
- `referrer_id` (bigint): ID do cliente indicador (FK para `dps_client`)
- `referred_id` (bigint): ID do cliente indicado (FK para `dps_client`)
- `referral_code` (varchar): código usado na indicação
- `created_at` (datetime): data da indicação
- `bonus_granted_referrer` (tinyint): bonificação concedida ao indicador (0/1)
- `bonus_granted_referred` (tinyint): bonificação concedida ao indicado (0/1)
- `first_purchase_date` (datetime): data da primeira compra do indicado

**Uso**: Rastreia indicações e status de bonificações.

### Options armazenadas

- **`dps_loyalty_settings`**: array serializado com configurações do add-on:
  - Taxa de conversão de pontos (faturamento → pontos)
  - Valor da bonificação para indicador
  - Valor da bonificação para indicado
  - Regras de elegibilidade para pontos
  - Configurações de campanhas

## Como usar (visão funcional)

### Para administradores

1. **Configurar programa de pontos**:
   - Acesse aba "Campanhas & Fidelidade" no painel base
   - Defina taxa de conversão (ex.: 1 ponto = R$ 10,00 faturados)
   - Defina regras de resgate (ex.: 100 pontos = R$ 50,00 de desconto)
   - Salve configurações

2. **Configurar Indique e Ganhe**:
   - Na mesma aba, configure valores de bonificação
   - Defina bonificação para indicador (ex.: R$ 20,00 de crédito)
   - Defina bonificação para indicado (ex.: 10% de desconto na primeira compra)
   - Salve

3. **Criar campanha**:
   - Clique em "Adicionar Nova Campanha"
   - Preencha nome, descrição, público-alvo
   - Defina template de mensagem com variáveis dinâmicas
   - Agende data de início/término
   - Publique

4. **Acompanhar indicações**:
   - Na aba "Campanhas & Fidelidade", visualize lista de indicações
   - Veja quais já foram bonificadas
   - Exporte relatório de indicações

### Para clientes (via Portal do Cliente)

1. **Visualizar código de indicação**:
   - Faça login no Portal do Cliente
   - Localize seção "Indique e Ganhe"
   - Copie código ou link compartilhável

2. **Compartilhar indicação**:
   - Envie link para amigos via WhatsApp, e-mail, redes sociais
   - Amigo se cadastra usando o link (código pré-preenchido)

3. **Receber bonificação**:
   - Quando indicado realiza primeira compra
   - Bonificação é creditada automaticamente

### Fluxo automático de indicação

```
1. Cliente A recebe código único "MARIA2024"
2. Cliente A compartilha link: seusite.com/cadastro?ref=MARIA2024
3. Novo cliente B acessa link e se cadastra
4. Registration Add-on salva indicação em dps_referrals
5. Cliente B faz primeira compra (Finance cria transação)
6. Finance marca cobrança como paga (dispara hook dps_finance_booking_paid)
7. Loyalty detecta indicação e bonifica:
   - Cliente A (indicador): recebe crédito/pontos
   - Cliente B (indicado): recebe desconto
8. Flags bonus_granted_* são marcadas como 1 na tabela
```

## Notas para desenvolvimento

### Convenções e padrões

Este add-on segue as diretrizes do repositório DPS:
- **[AGENTS.md](../../AGENTS.md)**: regras de desenvolvimento, versionamento, segurança
- **[ANALYSIS.md](../../ANALYSIS.md)**: arquitetura, integrações com Finance, Registration e Client Portal

### Fluxo obrigatório para mudanças

Ao modificar este add-on:

1. **Ler ANALYSIS.md** para entender tabela `dps_referrals` e integrações
2. **Implementar** seguindo políticas de segurança (sanitização de códigos, validação)
3. **Migrar dados** se alterar schema de `dps_referrals`
4. **Atualizar ANALYSIS.md** se criar novos hooks ou fluxos
5. **Atualizar CHANGELOG.md** antes de criar tags
6. **Validar** com Finance, Registration e Client Portal ativos

### Políticas de segurança

- ✅ **Códigos de indicação**: gerar códigos únicos e validar existência
- ✅ **Prepared statements**: usar `$wpdb->prepare()` em queries de `dps_referrals`
- ✅ **Sanitização**: sanitizar códigos de indicação antes de salvar
- ✅ **Validação**: verificar elegibilidade antes de bonificar
- ✅ **Nonces**: validar em formulários de configuração

### Tabela compartilhada

A tabela `dps_referrals` é específica deste add-on, mas é consultada por Client Portal Add-on. Mudanças de schema devem ser documentadas.

### Funções globais expostas

Este add-on oferece funções globais para outros add-ons:

- **`dps_loyalty_credit_points( $client_id, $points )`**: creditar pontos a cliente
- **`dps_loyalty_deduct_points( $client_id, $points )`**: debitar pontos de cliente
- **`dps_loyalty_get_balance( $client_id )`**: obter saldo de pontos de cliente
- **`dps_loyalty_get_referral_code( $client_id )`**: obter código de indicação de cliente

Use `function_exists()` antes de chamar.

### Pontos de atenção

- **Geração de códigos**: garantir unicidade e evitar colisões
- **Bonificações duplas**: validar flag `bonus_granted_*` antes de creditar novamente
- **Expiração de pontos**: considerar implementar validade de pontos
- **Fraude**: detectar padrões suspeitos de indicação (mesmo IP, mesmo endereço, etc.)

### Melhorias futuras sugeridas

- Níveis de fidelidade (bronze, prata, ouro) com benefícios crescentes
- Expiração de pontos após período de inatividade
- Resgates via portal do cliente (sem intervenção do administrador)
- Gamificação (badges, conquistas)
- Integração com e-mail marketing para campanhas

## Histórico de mudanças (resumo)

### Principais marcos

- **v0.2.0**: Lançamento com programa de pontos, Indique e Ganhe e campanhas de marketing
- Tabela `dps_referrals` criada para rastreamento de indicações
- Integração com Finance para bonificações automáticas

Para o histórico completo de mudanças, consulte `CHANGELOG.md` na raiz do repositório.
