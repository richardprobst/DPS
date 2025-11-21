# Desi Pet Shower – Serviços Add-on

Catálogo de serviços oferecidos com preços por porte de pet.

## Visão geral

O **Serviços Add-on** permite gerenciar o catálogo completo de serviços oferecidos pelo pet shop, definindo preços e duração estimada por porte de pet (pequeno, médio, grande). Serviços podem ser vinculados a agendamentos e o sistema vem com catálogo padrão pré-povoado na ativação.

Funcionalidades principais:
- Cadastro de serviços (banho, tosa, hidratação, etc.)
- Preços diferenciados por porte do pet
- Duração estimada por serviço e porte
- Vinculação de serviços a agendamentos
- Catálogo padrão criado automaticamente na ativação
- Interface integrada ao painel principal

**Tipo**: Add-on (extensão do plugin base DPS)

## Localização e identificação

- **Diretório**: `add-ons/desi-pet-shower-services_addon/`
- **Slug**: `dps-services-addon`
- **Classe principal**: (verificar no arquivo principal)
- **Arquivo principal**: `desi-pet-shower-services-addon.php`
- **Tipo**: Add-on (depende do plugin base)

## Dependências e compatibilidade

### Dependências obrigatórias
- **Desi Pet Shower Base**: v1.0.0 ou superior (obrigatório)
- **WordPress**: 6.0 ou superior
- **PHP**: 7.4 ou superior

### Versão
- **Introduzido em**: v0.1.0 (estimado)
- **Compatível com plugin base**: v1.0.0+

## Funcionalidades principais

### Catálogo de serviços
- **Cadastro de serviços**: criar serviços customizados (ex.: Banho Completo, Tosa Higiênica, Hidratação)
- **Descrição**: adicionar descrição detalhada de cada serviço
- **Imagem**: (se suportado) anexar foto ilustrativa do serviço

### Precificação por porte
- **Pequeno**: preço e duração para pets pequenos (até 10kg)
- **Médio**: preço e duração para pets médios (10-25kg)
- **Grande**: preço e duração para pets grandes (acima de 25kg)
- **Flexibilidade**: cada serviço pode ter preços diferentes para cada porte

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
Este add-on não expõe shortcodes públicos.

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

Este add-on não dispara hooks customizados próprios.

## Dados armazenados (CPTs, tabelas, options)

### Custom Post Types

#### `dps_service`
Armazena serviços oferecidos pelo pet shop.

**Metadados principais**:
- **`service_price_small`**: preço para pets pequenos (em centavos)
- **`service_price_medium`**: preço para pets médios (em centavos)
- **`service_price_large`**: preço para pets grandes (em centavos)
- **`service_duration_small`**: duração estimada para pequenos (em minutos)
- **`service_duration_medium`**: duração estimada para médios (em minutos)
- **`service_duration_large`**: duração estimada para grandes (em minutos)
- **`service_description`**: descrição do serviço

**Uso**: Registrado via `DPS_CPT_Helper` com rótulos e capabilities padrão.

### Metadados em agendamentos

#### Em `dps_appointment`
- **`appointment_services`**: array serializado de IDs de serviços vinculados ao agendamento

### Tabelas customizadas
Este add-on NÃO cria tabelas próprias.

### Options armazenadas
Este add-on não armazena options globais.

## Como usar (visão funcional)

### Para administradores

1. **Gerenciar serviços**:
   - No painel base, clique na aba "Serviços"
   - Visualize lista de serviços cadastrados
   - Clique em "Adicionar Novo Serviço"

2. **Criar serviço**:
   - Preencha nome do serviço (ex.: "Banho Premium")
   - Insira descrição
   - Defina preços por porte:
     - Pequeno: R$ 40,00
     - Médio: R$ 60,00
     - Grande: R$ 80,00
   - Defina duração estimada por porte (em minutos)
   - Salve

3. **Editar/excluir serviço**:
   - Na lista de serviços, clique em "Editar"
   - Altere dados conforme necessário
   - Salve ou exclua

4. **Vincular a agendamento**:
   - Ao criar/editar agendamento, selecione serviços na lista
   - Sistema calcula valor total baseado em serviços + porte do pet
   - Salve agendamento

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

- ✅ **Capabilities**: verificar `dps_manage_services` ou similar antes de salvar
- ✅ **Sanitização**: sanitizar valores monetários com `DPS_Money_Helper`
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
- **Valores em centavos**: SEMPRE armazenar preços como inteiros em centavos
- **Conversão de moeda**: usar `DPS_Money_Helper` para conversões
- **Porte do pet**: garantir que porte está definido para calcular preço correto

### Melhorias futuras sugeridas

- Combos de serviços (pacotes promocionais)
- Desconto por quantidade (ex.: 3 banhos = desconto de 10%)
- Sazonalidade de preços (preços diferentes em períodos específicos)
- Galeria de fotos de serviços
- Avaliações de clientes por serviço
- Sugestão de serviços baseada em histórico do cliente

## Histórico de mudanças (resumo)

### Principais marcos

- **v0.1.0**: Lançamento inicial com catálogo de serviços, preços por porte, vinculação a agendamentos e catálogo padrão pré-povoado

Para o histórico completo de mudanças, consulte `CHANGELOG.md` na raiz do repositório.
