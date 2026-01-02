# Diagrama Visual - Refatoração DPS_Base_Frontend

## Arquitetura Antes da Refatoração

```
┌─────────────────────────────────────────────────────────────┐
│                  DPS_Base_Frontend                          │
│                     (~3000 linhas)                          │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  section_clients() [55 linhas - TUDO JUNTO]                │
│  ├── Queries (get_clients)                                 │
│  ├── Detecção de estado ($_GET)                            │
│  ├── Carregamento de metadados                             │
│  ├── Preparação de dados                                   │
│  ├── HTML inline (echo '<div>...')                         │
│  ├── Chamadas a templates parciais                         │
│  └── Output buffering                                      │
│                                                             │
│  section_pets() [~400 linhas - TUDO JUNTO]                 │
│  section_agendas() [~900 linhas - TUDO JUNTO]              │
│  section_history() [~200 linhas - TUDO JUNTO]              │
│                                                             │
│  save_client(), save_pet(), save_appointment()...          │
│  handle_request(), handle_delete()...                      │
│                                                             │
└─────────────────────────────────────────────────────────────┘

PROBLEMAS:
❌ Responsabilidades misturadas (dados + apresentação)
❌ Difícil de testar (tudo acoplado)
❌ Difícil de reutilizar (lógica presa ao HTML)
❌ Difícil de customizar (HTML inline no PHP)
```

---

## Arquitetura Depois da Refatoração (Fase 1)

```
┌─────────────────────────────────────────────────────────────┐
│                  DPS_Base_Frontend                          │
│                     (~3000 linhas)                          │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌───────────────────────────────────────────────────────┐ │
│  │ SEÇÃO CLIENTES (Refatorada) ✅                        │ │
│  ├───────────────────────────────────────────────────────┤ │
│  │                                                       │ │
│  │  section_clients() [3 linhas - ORQUESTRADOR]         │ │
│  │  │                                                    │ │
│  │  ├─► prepare_clients_section_data() [45 linhas]      │ │
│  │  │   └── APENAS LÓGICA DE NEGÓCIO                    │ │
│  │  │       ├── Queries (get_clients)                   │ │
│  │  │       ├── Detecção de estado ($_GET)              │ │
│  │  │       ├── Carregamento de metadados               │ │
│  │  │       └── Return array estruturado                │ │
│  │  │                                                    │ │
│  │  └─► render_clients_section($data) [5 linhas]        │ │
│  │      └── APENAS RENDERIZAÇÃO                         │ │
│  │          └── dps_get_template('frontend/...')        │ │
│  │                                                       │ │
│  └───────────────────────────────────────────────────────┘ │
│                                                             │
│  section_pets() [~400 linhas - AINDA NÃO REFATORADO]       │
│  section_agendas() [~900 linhas - AINDA NÃO REFATORADO]    │
│  section_history() [~200 linhas - AINDA NÃO REFATORADO]    │
│                                                             │
└─────────────────────────────────────────────────────────────┘
         │
         │ usa template
         ▼
┌─────────────────────────────────────────────────────────────┐
│  templates/frontend/clients-section.php                    │
│  └── APENAS HTML (sem lógica de negócio)                   │
│      ├── Extrai variáveis com validação                    │
│      ├── Wrapper <div class="dps-section">                 │
│      ├── Título da seção                                   │
│      ├── dps_get_template('forms/client-form.php')         │
│      └── dps_get_template('lists/clients-list.php')        │
└─────────────────────────────────────────────────────────────┘

BENEFÍCIOS:
✅ Responsabilidades separadas (dados vs apresentação)
✅ Testável (prepare_* pode ser testado isoladamente)
✅ Reutilizável (dados podem ser usados em API/exports)
✅ Customizável (tema pode sobrescrever template)
```

---

## Fluxo de Execução Detalhado

### ANTES (Monolítico)

```
shortcode [dps_base]
    │
    ├─► render_app()
    │   └─► section_clients()
    │       ├── 1. Queries de banco ─────────┐
    │       ├── 2. Detecção de estado ───────┤
    │       ├── 3. Carregamento de metas ────┤► TUDO JUNTO
    │       ├── 4. Preparação de dados ──────┤  (55 linhas)
    │       ├── 5. HTML inline ──────────────┤
    │       └── 6. Output buffering ─────────┘
    │
    └─► HTML retornado ao WordPress
```

### DEPOIS (Modular)

```
shortcode [dps_base]
    │
    ├─► render_app()
    │   └─► section_clients()
    │       │
    │       ├─► prepare_clients_section_data()
    │       │   ├── 1. Queries de banco
    │       │   ├── 2. Detecção de estado
    │       │   ├── 3. Carregamento de metas
    │       │   ├── 4. Preparação de dados
    │       │   └── return $data (array)
    │       │
    │       └─► render_clients_section($data)
    │           ├── ob_start()
    │           ├── dps_get_template('frontend/clients-section.php', $data)
    │           │   │
    │           │   ├─► Template extrai variáveis
    │           │   ├─► Renderiza wrapper HTML
    │           │   ├─► Inclui form template
    │           │   └─► Inclui list template
    │           │
    │           └── ob_get_clean()
    │
    └─► HTML retornado ao WordPress

SEPARAÇÃO CLARA:
┌──────────────────┐     ┌─────────────────┐
│ DADOS (Lógica)   │────►│ HTML (Template) │
│ prepare_*()      │     │ render_*()      │
└──────────────────┘     └─────────────────┘
```

---

## Padrão de 3 Métodos (Aplicável a Todas as Seções)

```
┌─────────────────────────────────────────────────────────────┐
│                   MÉTODO 1: Orquestrador                    │
│                   section_NOME() [~3 linhas]                │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│   $data = self::prepare_NOME_section_data();               │
│   return self::render_NOME_section( $data );               │
│                                                             │
│   Responsabilidade: Coordenar o fluxo                       │
└─────────────────────────────────────────────────────────────┘
                              │
            ┌─────────────────┴─────────────────┐
            ▼                                   ▼
┌─────────────────────────────┐   ┌─────────────────────────────┐
│ MÉTODO 2: Preparação        │   │ MÉTODO 3: Renderização      │
│ prepare_NOME_section_data() │   │ render_NOME_section($data)  │
│ [~40-50 linhas]             │   │ [~5 linhas]                 │
├─────────────────────────────┤   ├─────────────────────────────┤
│                             │   │                             │
│ - Queries                   │   │ ob_start();                 │
│ - Detecção de estado        │   │ dps_get_template(           │
│ - Validações                │   │   'frontend/NOME.php',      │
│ - Transformações            │   │   $data                     │
│                             │   │ );                          │
│ return [                    │   │ return ob_get_clean();      │
│   'items' => $items,        │   │                             │
│   'meta'  => $meta,         │   │ Responsabilidade:           │
│   ...                       │   │ Delegar ao template         │
│ ];                          │   │                             │
│                             │   │                             │
│ Responsabilidade:           │   └─────────────────────────────┘
│ APENAS dados                │                 │
└─────────────────────────────┘                 │
                                                ▼
                              ┌─────────────────────────────────┐
                              │ TEMPLATE: frontend/NOME.php     │
                              ├─────────────────────────────────┤
                              │                                 │
                              │ - Extrai variáveis              │
                              │ - Wrapper HTML                  │
                              │ - Título da seção               │
                              │ - Formulário                    │
                              │ - Listagem                      │
                              │                                 │
                              │ Responsabilidade:               │
                              │ APENAS apresentação             │
                              └─────────────────────────────────┘
```

---

## Roadmap Visual de Refatoração

```
Estado Atual (Após Fase 1):
┌─────────────┬─────────────┬─────────────┬─────────────┐
│  Clientes   │    Pets     │ Agendamentos│  Histórico  │
│     ✅      │     ⏳      │     ⏳      │     ⏳      │
│ REFATORADO  │   PRÓXIMO   │   PLANEJADO │  PLANEJADO  │
└─────────────┴─────────────┴─────────────┴─────────────┘

Progresso: ████░░░░░░░░░░░░ 20%

Fase 1 ✅ │ Fase 2 ⏳ │ Fase 3 ⏳ │ Fase 4 ⏳ │ Fase 5 ⏳ │ Fase 6 ⏳
```

---

## Estrutura de Arquivos

```
plugins/desi-pet-shower-base/
│
├── includes/
│   ├── class-dps-base-frontend.php
│   │   ├── section_clients() ✅ REFATORADO
│   │   │   ├── prepare_clients_section_data() ✅ NOVO
│   │   │   └── render_clients_section() ✅ NOVO
│   │   │
│   │   ├── section_pets() ⏳ PENDENTE
│   │   ├── section_agendas() ⏳ PENDENTE
│   │   └── section_history() ⏳ PENDENTE
│   │
│   └── frontend/ (FUTURO - Fase 6)
│       ├── class-dps-frontend-app.php
│       ├── class-dps-frontend-clients.php
│       ├── class-dps-frontend-pets.php
│       └── loader.php
│
└── templates/
    ├── frontend/
    │   ├── clients-section.php ✅ CRIADO
    │   ├── pets-section.php ⏳ PENDENTE
    │   ├── appointments-section.php ⏳ PENDENTE
    │   └── history-section.php ⏳ PENDENTE
    │
    ├── forms/
    │   ├── client-form.php (já existia)
    │   └── ...
    │
    └── lists/
        ├── clients-list.php (já existia)
        └── ...
```

---

## Comparação de Complexidade

### Método Monolítico (Antes)

```
Complexidade Ciclomática: ALTA
Acoplamento: ALTO
Coesão: BAIXA
Testabilidade: IMPOSSÍVEL

┌──────────────────────────────────┐
│ section_clients() [55 linhas]    │
│ ┌──────────────────────────────┐ │
│ │ if (edição)                  │ │
│ │   ├─ get_post()              │ │
│ │   ├─ get_post_meta() x11     │ │
│ │   └─ preparar $meta          │ │
│ │                              │ │
│ │ echo HTML                    │ │
│ │ dps_get_template()           │ │
│ │ dps_get_template()           │ │
│ │ echo HTML                    │ │
│ └──────────────────────────────┘ │
│                                  │
│ Tudo acoplado e difícil de testar│
└──────────────────────────────────┘
```

### Métodos Modulares (Depois)

```
Complexidade Ciclomática: BAIXA
Acoplamento: BAIXO
Coesão: ALTA
Testabilidade: FÁCIL

┌────────────────────┐   ┌───────────────────┐   ┌────────────────┐
│ section_clients()  │──►│ prepare_*_data()  │──►│ render_*()     │
│ [3 linhas]         │   │ [45 linhas]       │   │ [5 linhas]     │
│                    │   │                   │   │                │
│ Orquestra          │   │ APENAS lógica     │   │ APENAS HTML    │
│                    │   │ - Testável ✅     │   │ - Template ✅  │
└────────────────────┘   └───────────────────┘   └────────────────┘

Cada método tem UMA responsabilidade clara
```

---

## Legenda

```
✅ Concluído
⏳ Pendente / Próximo
❌ Problema identificado
```

---

## Referências

- **Plano Completo**: `docs/refactoring/FRONTEND_CLASS_REFACTORING_PLAN.md`
- **Antes/Depois**: `docs/refactoring/CLIENTS_SECTION_BEFORE_AFTER.md`
- **Resumo Executivo**: `docs/refactoring/REFACTORING_EXECUTIVE_SUMMARY.md`
