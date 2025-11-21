# Desi Pet Shower – Estoque Add-on

Controle de estoque de insumos com baixa automática em atendimentos.

## Visão geral

O **Estoque Add-on** permite controlar o inventário de insumos utilizados nos atendimentos (shampoos, condicionadores, tosas, etc.), registrar movimentações de entrada e saída, gerar alertas de estoque baixo e baixar estoque automaticamente quando atendimentos são concluídos.

Funcionalidades principais:
- Cadastro de itens de estoque com quantidade mínima
- Movimentações de entrada (compras) e saída (uso em atendimentos)
- Alertas automáticos de estoque baixo
- Baixa automática de estoque ao concluir atendimento
- Histórico completo de movimentações
- Capability customizada `dps_manage_stock`

**Tipo**: Add-on (extensão do plugin base DPS)

## Localização e identificação

- **Diretório**: `add-ons/desi-pet-shower-stock_addon/`
- **Slug**: `dps-stock-addon`
- **Classe principal**: (verificar no arquivo principal)
- **Arquivo principal**: `desi-pet-shower-stock-addon.php`
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

### Gestão de itens
- **Cadastro de produtos**: criar itens com nome, descrição, unidade de medida
- **Quantidade atual**: estoque disponível em tempo real
- **Quantidade mínima**: nível abaixo do qual alerta é gerado
- **Custo unitário**: valor de compra do item (para cálculo de valor em estoque)

### Movimentações
- **Entrada**: registrar compras e reposições
- **Saída manual**: registrar uso não vinculado a atendimentos
- **Saída automática**: baixa ao concluir atendimento (via hook)
- **Histórico**: visualizar todas as movimentações por item

### Alertas
- **Estoque baixo**: notificação quando quantidade < quantidade mínima
- **Estoque zerado**: alerta crítico quando quantidade = 0
- **Dashboard de alertas**: lista de itens que precisam reposição

### Baixa automática
- **Integração com agendamentos**: ao concluir atendimento, sistema baixa insumos vinculados
- **Configuração de consumo**: definir quais itens são usados em cada tipo de atendimento
- **Quantidade variável**: baixar quantidade diferente conforme porte do pet

## Shortcodes, widgets e endpoints

### Shortcodes
Este add-on não expõe shortcodes públicos.

## Hooks (actions e filters) relevantes

### Hooks CONSUMIDOS por este add-on

#### `dps_base_after_save_appointment` (action)
- **Propósito**: baixar estoque automaticamente ao concluir atendimento
- **Parâmetros**: `$appointment_id` (int)
- **Implementação**: verifica serviços vinculados, baixa insumos correspondentes

#### `dps_base_nav_tabs_after_history` (action)
- **Propósito**: adicionar aba "Estoque" à navegação do painel base
- **Implementação**: renderiza tab na interface principal

#### `dps_base_sections_after_history` (action)
- **Propósito**: renderizar controle de estoque
- **Implementação**: exibe listagem de itens, formulários, alertas

### Hooks DISPARADOS por este add-on

Este add-on não dispara hooks customizados próprios.

## Dados armazenados (CPTs, tabelas, options)

### Custom Post Types

#### `dps_stock_item`
Armazena itens de estoque.

**Metadados principais**:
- **`stock_current_quantity`**: quantidade atual em estoque (float)
- **`stock_minimum_quantity`**: quantidade mínima desejada (float)
- **`stock_unit`**: unidade de medida (litro, kg, unidade, etc.)
- **`stock_unit_cost`**: custo unitário em centavos
- **`stock_movements`**: array serializado com histórico de movimentações

**Uso**: Registrado via `DPS_CPT_Helper` com capability customizada `dps_manage_stock`.

### Capability customizada

#### `dps_manage_stock`
Permissão para gerenciar estoque (criar, editar, excluir itens e registrar movimentações).

**Atribuída a**:
- Administrator (role padrão)
- Roles customizadas conforme necessidade

### Tabelas customizadas
Este add-on NÃO cria tabelas próprias. Usa metadados serializados para histórico.

### Options armazenadas
Este add-on não armazena options globais.

## Como usar (visão funcional)

### Para administradores

1. **Cadastrar item de estoque**:
   - No painel base, clique na aba "Estoque"
   - Clique em "Adicionar Novo Item"
   - Preencha:
     - Nome: "Shampoo Hipoalergênico"
     - Quantidade atual: 10
     - Quantidade mínima: 3
     - Unidade: litro
     - Custo unitário: R$ 45,00
   - Salve

2. **Registrar entrada (compra)**:
   - Na lista de itens, clique em "Adicionar Entrada"
   - Informe quantidade adicionada (ex.: 5 litros)
   - Informe motivo (ex.: "Compra - Nota Fiscal #1234")
   - Salve

3. **Registrar saída manual**:
   - Na lista de itens, clique em "Adicionar Saída"
   - Informe quantidade retirada (ex.: 2 litros)
   - Informe motivo (ex.: "Uso em teste de produto")
   - Salve

4. **Configurar baixa automática**:
   - Na configuração do item, vincule a serviços
   - Defina consumo padrão (ex.: Banho Completo consome 0,5L de shampoo)
   - Ao concluir agendamento com serviço vinculado, estoque é baixado automaticamente

5. **Acompanhar alertas**:
   - Dashboard de estoque exibe itens com estoque baixo
   - Notificações visuais para itens zerados
   - Exportar lista de reposição

### Fluxo automático

```
1. Recepcionista cria agendamento com serviço "Banho Completo"
2. Agendamento é concluído
3. Hook dps_base_after_save_appointment é disparado
4. Stock Add-on detecta serviço vinculado
5. Busca itens configurados para este serviço
6. Baixa quantidade: Shampoo (-0,5L), Condicionador (-0,3L)
7. Atualiza metadado stock_current_quantity
8. Registra movimentação no histórico
9. Verifica se quantidade < mínima → gera alerta
```

## Notas para desenvolvimento

### Convenções e padrões

Este add-on segue as diretrizes do repositório DPS:
- **[AGENTS.md](../../AGENTS.md)**: regras de desenvolvimento, versionamento, segurança
- **[ANALYSIS.md](../../ANALYSIS.md)**: uso de `DPS_CPT_Helper`, integração com hooks de agendamento

### Fluxo obrigatório para mudanças

Ao modificar este add-on:

1. **Ler ANALYSIS.md** para entender hook `dps_base_after_save_appointment`
2. **Implementar** seguindo políticas de segurança (capabilities, sanitização)
3. **Atualizar ANALYSIS.md** se criar novos metadados ou hooks
4. **Atualizar CHANGELOG.md** antes de criar tags
5. **Validar** baixa automática em diferentes cenários

### Políticas de segurança

- ✅ **Capability**: verificar `dps_manage_stock` antes de salvar/editar
- ✅ **Validação**: garantir que quantidades são números positivos
- ✅ **Sanitização**: sanitizar valores monetários com `DPS_Money_Helper`
- ✅ **Atomicidade**: usar transações ou locks para evitar race conditions em baixas simultâneas

### Oportunidades de refatoração

**ANALYSIS.md** indica que este add-on é candidato a refatoração:
- **Arquivo único**: atualmente 432 linhas em um único arquivo
- **Estrutura recomendada**: migrar para padrão modular com `includes/` e `assets/`
- **Classes separadas**: extrair lógica de movimentações, alertas e baixa automática
- **Navegação integrada**: já migrado para painel base (bom exemplo)

Consulte **REFACTORING_ANALYSIS.md** para detalhes.

### Uso de DPS_CPT_Helper

Este add-on utiliza corretamente `DPS_CPT_Helper` para registro de CPT com capability customizada, seguindo padrão recomendado.

### Pontos de atenção

- **Race conditions**: baixas simultâneas podem causar quantidade negativa
- **Histórico serializado**: considerar migrar para tabela customizada para melhor performance
- **Valores em centavos**: custo unitário deve ser armazenado como int em centavos
- **Unidades de medida**: validar conversões entre unidades (ex.: litro para ml)

### Melhorias futuras sugeridas

- Migrar histórico de movimentações para tabela customizada
- Código de barras para itens (integração com leitor)
- Inventário físico (contagem manual vs sistema)
- Previsão de reposição baseada em consumo médio
- Integração com fornecedores (pedido automático)
- Relatório de valor total em estoque
- Controle de lotes e validade

## Histórico de mudanças (resumo)

### Principais marcos

- **v0.1.0**: Lançamento inicial com controle de estoque, movimentações, alertas de estoque baixo e baixa automática em atendimentos
- Migração para navegação integrada ao painel base (removido menu próprio)

Para o histórico completo de mudanças, consulte `CHANGELOG.md` na raiz do repositório.
