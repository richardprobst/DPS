# desi.pet by PRObst – Serviços Add-on

Catálogo de serviços oferecidos com preços por porte de pet.

## Visão geral

O **Serviços Add-on** permite gerenciar o catálogo completo de serviços oferecidos pelo pet shop, definindo preços e duração estimada por porte de pet (pequeno, médio, grande). Serviços podem ser vinculados a agendamentos e o sistema vem com catálogo padrão pré-povoado na ativação.

Funcionalidades principais:
- Cadastro de serviços (banho, tosa, hidratação, etc.)
- Preços diferenciados por porte do pet
- Duração estimada por serviço e porte
- Vinculação de serviços a agendamentos
- **Pacotes promocionais** com desconto ou preço fixo
- **Histórico de alterações de preços** para rastreabilidade
- **Duplicação de serviços** para agilizar cadastros
- **Shortcode de catálogo público** para exibição no site
- **API para Portal do Cliente** com histórico de uso
- Catálogo padrão criado automaticamente na ativação
- Interface integrada ao painel principal

**Tipo**: Add-on (extensão do plugin base DPS)

## Localização e identificação

- **Diretório**: `plugins/desi-pet-shower-services/`
- **Slug**: `dps-services-addon`
- **Classe principal**: `DPS_Services_Addon`
- **API pública**: `DPS_Services_API`
- **Arquivo principal**: `desi-pet-shower-services-addon.php`
- **Tipo**: Add-on (depende do plugin base)

## Dependências e compatibilidade

### Dependências obrigatórias
- **desi.pet by PRObst Base**: v1.0.0 ou superior (obrigatório)
- **WordPress**: 6.0 ou superior
- **PHP**: 7.4 ou superior

### Versão
- **Versão atual**: v1.3.0
- **Introduzido em**: v0.1.0
- **Compatível com plugin base**: v1.0.0+

## Funcionalidades principais

### Catálogo de serviços
- **Cadastro de serviços**: criar serviços customizados (ex.: Banho Completo, Tosa Higiênica, Hidratação)
- **Tipos de serviço**: Padrão, Extra e Pacote promocional
- **Categorização**: agrupar extras por categoria (Tosa, Tratamento, Cuidados, etc.)

### Pacotes promocionais (v1.3.0)
- **Combinar serviços**: selecionar múltiplos serviços para compor um pacote
- **Desconto percentual**: definir desconto (ex.: 10% off no combo)
- **Preço fixo**: definir valor único para o pacote

### Precificação por porte
- **Pequeno**: preço e duração para pets pequenos (até 10kg)
- **Médio**: preço e duração para pets médios (10-25kg)
- **Grande**: preço e duração para pets grandes (acima de 25kg)
- **Histórico de preços**: rastreabilidade de todas as alterações

### Duplicação de serviços (v1.3.0)
- **Ação rápida**: botão "Duplicar" na tabela de serviços
- **Cópia completa**: copia todos os metadados incluindo preços por porte
- **Segurança**: serviço duplicado inicia como inativo

### Vinculação a agendamentos
- **Campo de seleção**: adiciona dropdown de serviços no formulário de agendamento
- **Multi-seleção**: permite vincular múltiplos serviços a um único agendamento
- **Cálculo automático**: soma valores dos serviços para gerar cobrança (via Finance Add-on)

### Catálogo padrão
- **Povoamento automático**: ao ativar plugin, cria serviços padrão:
  - Banho Simples
  - Banho e Tosa
  - Tosa Higiênica
  - Hidratação
  - Corte de Unhas
- **Editável**: administrador pode editar/excluir serviços padrão

## Shortcodes, widgets e endpoints

### Shortcodes

#### `[dps_services_catalog]` (v1.3.0)
Exibe catálogo público de serviços ativos.

**Atributos:**
- `show_prices` (yes|no): Exibir preços. Padrão: 'yes'
- `type` (padrao|extra|package): Filtrar por tipo de serviço
- `category` (slug): Filtrar por categoria
- `layout` (list|grid): Layout de exibição. Padrão: 'list'

**Exemplos:**
```
[dps_services_catalog]
[dps_services_catalog show_prices="no"]
[dps_services_catalog type="package" layout="grid"]
[dps_services_catalog category="tratamento"]
```

## API Pública (v1.3.0)

A classe `DPS_Services_API` fornece métodos estáticos para integração:

### Métodos principais

```php
// Obter dados de um serviço
$service = DPS_Services_API::get_service( $service_id );

// Calcular preço por porte
$price = DPS_Services_API::calculate_price( $service_id, 'medium' );

// Calcular preço de pacote promocional
$package_price = DPS_Services_API::calculate_package_price( $package_id, 'large' );

// Listar serviços públicos
$services = DPS_Services_API::get_public_services( [
    'type'           => 'padrao',
    'category'       => '',
    'include_prices' => true,
] );

// Obter histórico de preços
$history = DPS_Services_API::get_price_history( $service_id );

// Duplicar serviço
$new_id = DPS_Services_API::duplicate_service( $service_id );

// Obter serviços para Portal do Cliente
$data = DPS_Services_API::get_portal_services( $client_id, [
    'include_history' => true,
    'limit_history'   => 10,
] );

// Histórico de uso de serviços por cliente
$usage = DPS_Services_API::get_client_service_history( $client_id, 10 );
```

## Hooks (actions e filters) relevantes

### Hooks CONSUMIDOS por este add-on

#### `dps_base_nav_tabs_*` (action)
- **Propósito**: adicionar aba "Serviços" à navegação do painel base
- **Implementação**: renderiza tab na interface principal

#### `dps_base_sections_*` (action)
- **Propósito**: renderizar catálogo e formulários de serviços
- **Implementação**: exibe listagem e formulários de criação/edição

#### Hook de agendamento
- **Propósito**: adicionar campos de seleção de serviços no formulário de agendamento
- **Implementação**: renderiza checkboxes/selects de serviços disponíveis

### Hooks DISPARADOS por este add-on

#### `dps_service_duplicated` (action) (v1.3.0)
- **Quando dispara**: após duplicar um serviço com sucesso
- **Parâmetros**: `$new_id` (int), `$original_id` (int)
- **Uso**: realizar ações adicionais após duplicação

## Dados armazenados (CPTs, tabelas, options)

### Custom Post Types

#### `dps_service`
Armazena serviços oferecidos pelo pet shop.

**Metadados principais**:
- **`service_type`**: tipo do serviço ('padrao', 'extra', 'package')
- **`service_category`**: categoria (para extras)
- **`service_active`**: status ativo/inativo ('0' ou '1')
- **`service_price`**: preço base
- **`service_price_small`**: preço para pets pequenos
- **`service_price_medium`**: preço para pets médios
- **`service_price_large`**: preço para pets grandes
- **`service_duration`**: duração base (minutos)
- **`service_duration_small`**: duração para pequenos
- **`service_duration_medium`**: duração para médios
- **`service_duration_large`**: duração para grandes
- **`service_package_items`**: array de IDs (para pacotes)
- **`service_package_discount`**: desconto percentual (para pacotes)
- **`service_package_fixed_price`**: preço fixo (para pacotes)
- **`service_price_history`**: histórico de alterações de preço
- **`dps_service_stock_consumption`**: consumo de estoque por atendimento

**Uso**: Registrado via `DPS_CPT_Helper` com rótulos e capabilities padrão.

### Metadados em agendamentos

#### Em `dps_agendamento`
- **`appointment_services`**: array de IDs de serviços vinculados
- **`appointment_service_prices`**: array de preços customizados por serviço
- **`_dps_total_at_booking`**: total histórico em centavos
- **`_dps_services_at_booking`**: snapshot de serviços e preços

### Tabelas customizadas
Este add-on NÃO cria tabelas próprias.

### Options armazenadas
Este add-on não armazena options globais.

## Como usar (visão funcional)

### Para administradores

1. **Gerenciar serviços**:
   - No painel base, clique na aba "Serviços"
   - Visualize lista de serviços cadastrados
   - Use a busca para encontrar serviços específicos

2. **Criar serviço**:
   - Preencha nome do serviço (ex.: "Banho Premium")
   - Selecione tipo (Padrão, Extra ou Pacote)
   - Defina preços por porte:
     - Pequeno: R$ 40,00
     - Médio: R$ 60,00
     - Grande: R$ 80,00
   - Defina duração estimada por porte
   - Salve

3. **Criar pacote promocional**:
   - Selecione tipo "Pacote de serviços"
   - Escolha os serviços incluídos
   - Defina desconto percentual OU preço fixo
   - Salve

4. **Duplicar serviço**:
   - Clique em "Duplicar" na linha do serviço
   - Serviço é copiado como inativo
   - Edite e ative conforme necessário

5. **Exibir catálogo no site**:
   - Crie uma página WordPress
   - Insira `[dps_services_catalog]`
   - Publique

### Para recepcionistas

- Ao criar agendamento, selecione serviços desejados
- Sistema mostra preço automaticamente baseado no porte do pet
- Múltiplos serviços podem ser adicionados ao mesmo agendamento

## Notas para desenvolvimento

### Convenções e padrões

Este add-on segue as diretrizes do repositório DPS:
- **[AGENTS.md](../../AGENTS.md)**: regras de desenvolvimento, versionamento, segurança
- **[ANALYSIS.md](../../ANALYSIS.md)**: uso de `DPS_CPT_Helper`, integração com painel base

### Fluxo obrigatório para mudanças

Ao modificar este add-on:

1. **Ler ANALYSIS.md** para entender uso de `DPS_CPT_Helper`
2. **Implementar** seguindo políticas de segurança (sanitização, capabilities)
3. **Atualizar ANALYSIS.md** se criar novos metadados ou hooks
4. **Atualizar CHANGELOG.md** antes de criar tags
5. **Validar** criação de catálogo padrão na ativação

### Políticas de segurança

- ✅ **Capabilities**: verificar `manage_options` antes de salvar
- ✅ **Nonces**: todas as ações protegidas com nonce
- ✅ **Sanitização**: sanitizar valores monetários
- ✅ **Validação**: validar que preços/durações são números positivos
- ✅ **Escape**: escapar saída em listagens

### Uso de DPS_CPT_Helper

Este add-on utiliza corretamente `DPS_CPT_Helper` para registro de CPT, seguindo padrão recomendado em ANALYSIS.md.

### Integração com Finance Add-on

- Ao vincular serviços a agendamento, Finance pode calcular valor total da cobrança
- Soma de `service_price_{size}` de cada serviço vinculado
- Buscar porte do pet em metadado para determinar qual preço usar

### Pontos de atenção

- **Catálogo padrão**: validar se serviços já existem antes de criar na ativação (evitar duplicatas em reativações)
- **Histórico de preços**: mantém apenas últimos 50 registros
- **Duplicação**: serviços duplicados iniciam inativos por segurança
- **Porte do pet**: garantir que porte está definido para calcular preço correto

### Melhorias futuras sugeridas

- Sazonalidade de preços (preços diferentes em períodos específicos)
- Galeria de fotos de serviços
- Avaliações de clientes por serviço
- Sugestão de serviços baseada em histórico do cliente
- Ordenação customizada (drag-and-drop)

## Histórico de mudanças (resumo)

### Principais marcos

- **v1.3.0**: Pacotes promocionais, histórico de preços, duplicação de serviço, shortcode de catálogo, API para Portal
- **v1.2.0**: Correções de segurança CSRF, badges de status, mensagens de feedback
- **v0.1.0**: Lançamento inicial com catálogo de serviços, preços por porte, vinculação a agendamentos e catálogo padrão pré-povoado

Para o histórico completo de mudanças, consulte `CHANGELOG.md` na raiz do repositório.
