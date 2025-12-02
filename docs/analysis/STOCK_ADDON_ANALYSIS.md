# Análise Profunda do Add-on Estoque (desi-pet-shower-stock_addon)

## Resumo Executivo

O **Add-on Estoque** é um módulo **independente** para controle de insumos (shampoos, condicionadores, toalhas, etc.) utilizados nos atendimentos de banho e tosa. Ele **NÃO faz parte** do Add-on Serviços, mas os dois trabalham juntos: você cadastra insumos no Estoque, depois associa esses insumos aos Serviços.

**Confusão comum**: A interface de cadastro de Serviços permite vincular insumos de Estoque, criando a impressão de que Estoque está "dentro" de Serviços. Na verdade, são módulos separados que se integram.

---

## Índice

1. [O Que é o Add-on Estoque?](#1-o-que-é-o-add-on-estoque)
2. [Onde Acessar o Estoque?](#2-onde-acessar-o-estoque)
3. [Funcionalidades Principais](#3-funcionalidades-principais)
4. [Integração com Serviços](#4-integração-com-serviços)
5. [Fluxo Completo de Uso](#5-fluxo-completo-de-uso)
6. [Arquitetura Técnica](#6-arquitetura-técnica)
7. [Perguntas Frequentes](#7-perguntas-frequentes)

---

## 1. O Que é o Add-on Estoque?

### Propósito

Controlar o inventário de **insumos físicos** consumidos durante os atendimentos:

- **Produtos de limpeza**: shampoos, condicionadores, neutralizadores
- **Materiais descartáveis**: toalhas descartáveis, luvas, máscaras
- **Equipamentos**: lâminas de tosa, escovas específicas
- **Produtos de higiene**: perfumes, secadores (gás), etc.

### O Que NÃO é

- **Não é catálogo de serviços** → isso é o Add-on Serviços
- **Não é gestão financeira** → isso é o Add-on Financeiro
- **Não gerencia agendamentos** → isso é o plugin base + Add-on Agenda

---

## 2. Onde Acessar o Estoque?

### Localização Principal: Aba "Estoque" no Painel DPS

O Estoque possui sua **própria aba** na navegação principal do painel:

```
Painel DPS → Navegação:
┌─────────────────────────────────────────────────────────┐
│  Clientes | Pets | Serviços | Histórico | ... | Estoque │
└─────────────────────────────────────────────────────────┘
                                                    ▲
                                            Aba do Estoque
```

**Como chegar:**
1. Acesse o shortcode `[dps_base]` no frontend (página do painel DPS)
2. Na barra de navegação, clique em **"Estoque"**
3. Você verá a lista de todos os itens cadastrados

### Localização Secundária: No Cadastro de Serviços

Dentro da aba "Serviços", ao editar um serviço, existe uma seção chamada **"Consumo de estoque"** que permite vincular itens de estoque ao serviço. **Isso é integração, não é o módulo de estoque em si.**

```
Aba "Serviços" → Formulário de Cadastro:
┌────────────────────────────────────────────┐
│ Nome do serviço: [Banho Completo]          │
│ Tipo: [Serviço padrão ▼]                   │
│ Valores por porte: [Pequeno] [Médio] [Grande] │
│                                            │
│ ───── Consumo de estoque ─────             │ ◄── INTEGRAÇÃO
│ | Item de estoque    | Qtd. consumida |    │
│ |───────────────────────────────────|      │
│ | Shampoo Neutro    | 0.5 litros     |    │
│ | Condicionador     | 0.3 litros     |    │
│ [Adicionar insumo]                         │
│                                            │
│ [Salvar Serviço]                           │
└────────────────────────────────────────────┘
```

> ⚠️ **Atenção**: Se você não cadastrou itens de estoque primeiro, a mensagem aparecerá:
> *"Cadastre itens em Estoque DPS para selecionar insumos."*

---

## 3. Funcionalidades Principais

### 3.1 Cadastro de Itens de Estoque

Feito na interface administrativa do WordPress (fora do shortcode):

**Caminho**: Admin WP → Desi Pet Shower → (interface de itens de estoque via CPT)

Cada item possui:

| Campo | Descrição | Exemplo |
|-------|-----------|---------|
| **Nome** | Nome do insumo | "Shampoo Hipoalergênico 5L" |
| **Unidade** | Unidade de medida | ml, L, un, pct |
| **Quantidade atual** | Estoque disponível | 4500 (ml) |
| **Quantidade mínima** | Nível para alerta | 1000 (ml) |

### 3.2 Visualização e Alertas (Aba "Estoque")

Na aba "Estoque" do painel frontend, você pode:

- **Adicionar novos itens** diretamente (botão "Adicionar Item")
- **Gerenciar itens no admin** (botão "Gerenciar no Admin")
- **Ver todos os itens** cadastrados com quantidades atuais
- **Filtrar por críticos** (itens abaixo do mínimo)
- **Ver alertas** de estoque baixo com data de registro
- **Editar itens** rapidamente (coluna de ações)
- **Paginar** resultados (50 itens por página)

```
┌──────────────────────────────────────────────────────────────────────┐
│ Estoque de Insumos                                                   │
├──────────────────────────────────────────────────────────────────────┤
│ [+ Adicionar Item] [Gerenciar no Admin] [Mostrar apenas críticos]    │
│                                                                      │
│ ⚠️ 2 itens abaixo do mínimo                                          │
├──────────────────────────────────────────────────────────────────────┤
│ Item              │ Unidade │ Qtd. atual │ Qtd. mínima │ Status │ ✎  │
│───────────────────│─────────│────────────│─────────────│────────│────│
│ Shampoo Neutro    │ litro   │ 3.50       │ 2.00        │ ✓ OK   │ ✎  │
│ Condicionador     │ litro   │ 0.80       │ 1.00        │ ⚠️ Baixo│ ✎  │
│ Toalha Descartável│ unidade │ 45.00      │ 50.00       │ ⚠️ Baixo│ ✎  │
└──────────────────────────────────────────────────────────────────────┘
```

**Requisitos de acesso**: A aba "Estoque" aparece para usuários com:
- Capability `dps_manage_stock` (atribuída na ativação do plugin)
- **OU** capability `manage_options` (administradores WordPress)

### 3.3 Baixa Automática de Estoque

Quando um agendamento é **concluído** (status `finalizado` ou `finalizado_pago`):

1. Sistema identifica os serviços realizados
2. Para cada serviço, verifica insumos configurados
3. Subtrai a quantidade consumida do estoque
4. Se ficar abaixo do mínimo, registra alerta

```
Fluxo Automático:
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│ Agendamento     │───▶│ Serviços         │───▶│ Estoque         │
│ (finalizado)    │    │ (Banho + Tosa)   │    │ (baixa insumos) │
└─────────────────┘    └──────────────────┘    └─────────────────┘
```

---

## 4. Integração com Serviços

### Como Funciona a Integração

O Add-on Serviços permite **vincular itens de estoque** a cada serviço. Isso é configurado no cadastro do serviço:

```php
// Metadado salvo no serviço (CPT: dps_service)
// Meta key: dps_service_stock_consumption
// Valor: array de [item_id, quantity]
[
    ['item_id' => 123, 'quantity' => 0.5],  // 0.5 litros de shampoo
    ['item_id' => 456, 'quantity' => 0.3],  // 0.3 litros de condicionador
]
```

### Por Que Aparece em "Serviços"?

A seção "Consumo de estoque" aparece dentro do formulário de Serviços porque:

1. **Faz sentido lógico**: você define *quanto* de cada insumo é usado por serviço
2. **Evita duplicação**: não precisa configurar consumo em dois lugares
3. **É opcional**: se o Add-on Estoque não estiver ativo, a seção não aparece

### Responsabilidades de Cada Add-on

| Add-on | Responsabilidade |
|--------|------------------|
| **Estoque** | Cadastrar itens, controlar quantidades, gerar alertas, baixar estoque |
| **Serviços** | Cadastrar serviços, definir preços, **vincular** consumo de estoque por serviço |

---

## 5. Fluxo Completo de Uso

### Passo 1: Cadastrar Itens de Estoque

**Onde**: Admin WP → Desi Pet Shower → Itens de Estoque

1. Clique em "Adicionar Novo"
2. Preencha: Nome, Unidade, Quantidade atual, Quantidade mínima
3. Salve

**Exemplo**:
- Nome: "Shampoo Neutro 5L"
- Unidade: litro
- Quantidade atual: 5
- Quantidade mínima: 1

### Passo 2: Vincular Insumos aos Serviços

**Onde**: Painel DPS → Aba "Serviços" → Editar serviço

1. Localize a seção "Consumo de estoque"
2. Selecione o item de estoque no dropdown
3. Informe a quantidade consumida por atendimento
4. Adicione outros insumos se necessário
5. Salve o serviço

**Exemplo** (para "Banho Completo"):
- Shampoo Neutro: 0.5 litros
- Condicionador: 0.3 litros

### Passo 3: Realizar Atendimento

**Onde**: Painel DPS → Criar/Editar Agendamento

1. Crie um agendamento normalmente
2. Selecione o serviço "Banho Completo"
3. Realize o atendimento
4. Marque como "Finalizado"

### Passo 4: Baixa Automática

Ao marcar o agendamento como finalizado:
- Shampoo: 5.0 → 4.5 litros
- Condicionador: 3.0 → 2.7 litros

### Passo 5: Monitorar Estoque

**Onde**: Painel DPS → Aba "Estoque"

- Verifique alertas de estoque baixo
- Reponha itens conforme necessário

---

## 6. Arquitetura Técnica

### Estrutura de Arquivos

```
add-ons/desi-pet-shower-stock_addon/
├── desi-pet-shower-stock.php    # Arquivo principal (432 linhas)
├── uninstall.php                # Limpeza na desinstalação
└── README.md                    # Documentação técnica
```

### Custom Post Type

| Propriedade | Valor |
|-------------|-------|
| **Slug** | `dps_stock_item` |
| **Visibilidade** | `show_ui => true`, `show_in_menu => false` |
| **Capability** | `dps_manage_stock` |

### Metadados do Item de Estoque

| Meta Key | Tipo | Descrição |
|----------|------|-----------|
| `dps_stock_unit` | string | Unidade de medida |
| `dps_stock_quantity` | float | Quantidade atual |
| `dps_stock_minimum` | float | Quantidade mínima para alerta |

### Hooks Consumidos

| Hook | Propósito |
|------|-----------|
| `dps_base_after_save_appointment` | Baixar estoque ao concluir atendimento |
| `dps_base_nav_tabs_after_history` | Adicionar aba "Estoque" na navegação |
| `dps_base_sections_after_history` | Renderizar conteúdo da aba |

### Metadado de Consumo (no Serviço)

| Meta Key | Tipo | Localização |
|----------|------|-------------|
| `dps_service_stock_consumption` | array | CPT `dps_service` |

Estrutura:
```php
[
    ['item_id' => int, 'quantity' => float],
    ...
]
```

---

## 7. Perguntas Frequentes

### "A aba Estoque não aparece para mim!"

A aba "Estoque" requer uma das seguintes permissões:
- **`manage_options`** (administrador WordPress) — sempre funciona
- **`dps_manage_stock`** (capability customizada)

**Soluções:**
1. Verifique se você está logado como **administrador**
2. Se você é administrador e ainda não vê, **desative e reative** o Add-on Estoque para reassociar a capability
3. Confirme que o Add-on Estoque está **ativo** em Plugins

### "Por que vejo Estoque na aba Serviços?"

Você está vendo a **integração** de consumo de estoque, não o módulo de Estoque em si. É onde você configura *quanto* de cada insumo é usado por serviço. Para gerenciar os itens de estoque (cadastrar, ver quantidades, alertas), use a aba "Estoque".

### "Preciso do Add-on Estoque para usar Serviços?"

**Não**. O Add-on Serviços funciona independentemente. A seção "Consumo de estoque" só aparece se o Add-on Estoque estiver ativo. Se não estiver, você simplesmente não poderá configurar baixa automática de insumos.

### "Como adiciono mais itens de estoque?"

**Opção 1 (Rápido)**: Na aba "Estoque" do painel DPS, clique no botão **"Adicionar Item"** no topo.

**Opção 2 (Completo)**: Clique em **"Gerenciar no Admin"** para acessar a interface administrativa completa do WordPress.

### "Por que o estoque não está baixando automaticamente?"

Verifique:
1. O agendamento foi marcado como **finalizado** ou **finalizado_pago**?
2. Os serviços do agendamento têm insumos **vinculados**?
3. O Add-on Estoque está **ativo**?

### "Posso vincular o mesmo insumo a vários serviços?"

**Sim**. Cada serviço pode ter sua própria configuração de consumo. Por exemplo:
- "Banho Simples": 0.3L de shampoo
- "Banho Completo": 0.5L de shampoo
- "Banho Terapêutico": 0.7L de shampoo especial

---

## Diagrama Visual

```
┌─────────────────────────────────────────────────────────────────┐
│                         PAINEL DPS                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │ NAVEGAÇÃO: [Clientes] [Pets] [Serviços] [Histórico] ... │    │
│  │                                          [Estatísticas] │    │
│  │                                          [ESTOQUE] ◄────┼────┤
│  └─────────────────────────────────────────────────────────┘    │
│                                                                 │
│  ┌───────────────────────┐    ┌───────────────────────────┐     │
│  │     ABA "SERVIÇOS"    │    │      ABA "ESTOQUE"        │     │
│  ├───────────────────────┤    ├───────────────────────────┤     │
│  │ • Cadastro de serviços│    │ • Lista de itens          │     │
│  │ • Preços por porte    │    │ • Qtd. atual vs mínima    │     │
│  │ • Tipos e categorias  │    │ • Alertas de baixa        │     │
│  │                       │    │ • Filtro de críticos      │     │
│  │ ──── INTEGRAÇÃO ────  │    │                           │     │
│  │ • Consumo de estoque  │◄───│ (itens cadastrados aqui)  │     │
│  │   ↳ vincula insumos   │    │                           │     │
│  └───────────────────────┘    └───────────────────────────┘     │
│              │                           ▲                      │
│              │                           │                      │
│              ▼                           │                      │
│  ┌───────────────────────────────────────┘                      │
│  │ AGENDAMENTO (finalizado)                                     │
│  │ ↳ Hook dps_base_after_save_appointment                       │
│  │ ↳ Baixa automática de estoque                                │
│  └──────────────────────────────────────────────────────────────┤
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## Conclusão

O Add-on Estoque é um módulo **separado e independente** do Add-on Serviços. A confusão surge porque:

1. **Integração visual**: A seção "Consumo de estoque" aparece dentro do cadastro de Serviços
2. **Dependência lógica**: Você precisa cadastrar itens no Estoque antes de vincular a Serviços

**Para usar corretamente**:
1. Primeiro: Cadastre itens em **Estoque** (Admin WP)
2. Depois: Vincule insumos aos **Serviços** (Painel DPS → Serviços)
3. Por fim: Monitore níveis na aba **Estoque** (Painel DPS)

---

## Referências

- **README do Add-on**: `add-ons/desi-pet-shower-stock_addon/README.md`
- **Arquivo principal**: `add-ons/desi-pet-shower-stock_addon/desi-pet-shower-stock.php`
- **Documentação geral**: `ANALYSIS.md` (seção "Estoque")
- **Guia do Sistema**: `docs/GUIA_SISTEMA_DPS.md`
