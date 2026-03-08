# Guia de Estilo Visual DPS — Material 3 Expressive

**Versão:** 2.0  
**Última atualização:** 07/02/2026  
**Base:** [Material 3 Expressive](https://m3.material.io/) do Google  
**Tokens CSS:** `plugins/desi-pet-shower-base/assets/css/dps-design-tokens.css`

---

## 1. Filosofia do Design

O DPS adota o **Material 3 Expressive** como base de design, adaptado ao contexto WordPress de um sistema para pet shops. Esta abordagem equilibra:

- **Expressividade com propósito**: interfaces que criam conexão emocional sem sacrificar usabilidade
- **Clareza funcional**: ações-chave são até 4× mais fáceis de localizar (pesquisa UX do Google)
- **Acessibilidade inclusiva**: contraste, tipografia escalável e motion respeitando preferências do usuário
- **Personalidade de marca**: visual que comunica confiança, modernidade e cuidado — qualidades de um bom pet shop

### Princípios M3 Expressive no DPS

1. **Emoção a serviço da função** — cada escolha visual (cor, forma, animação) deve servir à usabilidade
2. **Hierarquia tonal** — elevação comunicada por tons de cor (surface containers) em vez de sombras pesadas
3. **Formas expressivas** — cantos arredondados generosos e consistentes que transmitem suavidade e acessibilidade
4. **Motion com personalidade** — transições baseadas em springs (física) que parecem naturais e responsivas
5. **Tokens centralizados** — todo valor visual referenciado por variável CSS (`var(--dps-*)`)

---

## 2. Sistema de Cores

O M3 Expressive organiza cores em **papéis semânticos** (roles) em vez de valores fixos. Isso garante consistência, acessibilidade e suporte a temas claro/escuro.

### 2.1 Cores Primárias (Ações Principais)

```css
--dps-color-primary:               #0b6bcb;  /* Botões, links, FABs */
--dps-color-on-primary:            #ffffff;  /* Texto sobre primary */
--dps-color-primary-container:     #d4e4ff;  /* Cards destacados, chips selecionados */
--dps-color-on-primary-container:  #001c3a;  /* Texto sobre primary-container */
```

### 2.2 Cores Secundárias (Suporte)

```css
--dps-color-secondary:              #545f70;  /* Filtros, chips, ações secundárias */
--dps-color-on-secondary:           #ffffff;
--dps-color-secondary-container:    #d8e3f8;  /* Cards de suporte */
--dps-color-on-secondary-container: #111c2b;
```

### 2.3 Cores Terciárias (Expressivas)

```css
--dps-color-tertiary:               #6d5e78;  /* Destaques criativos, badges */
--dps-color-on-tertiary:            #ffffff;
--dps-color-tertiary-container:     #f5d9ff;  /* Backgrounds expressivos */
--dps-color-on-tertiary-container:  #271432;
```

### 2.4 Cores de Status

```css
/* Error — erros críticos, cancelamentos, ações destrutivas */
--dps-color-error:                  #ba1a1a;
--dps-color-error-container:        #ffdad6;

/* Success — confirmações, "OK", concluído */
--dps-color-success:                #1a7a3a;
--dps-color-success-container:      #a8f5b5;

/* Warning — pendências, atenção necessária */
--dps-color-warning:                #8b6914;
--dps-color-warning-container:      #ffdea3;
```

### 2.5 Superfícies (Surface Containers)

O M3 usa **tons graduais** para criar hierarquia visual sem sombras:

```css
--dps-color-surface:                    #f8f9ff;  /* Fundo da página */
--dps-color-surface-container-lowest:   #ffffff;  /* Cards em primeiro plano */
--dps-color-surface-container-low:      #f2f3fa;  /* Cards padrão */
--dps-color-surface-container:          #ecedf4;  /* Containers genéricos */
--dps-color-surface-container-high:     #e6e8ef;  /* Elementos elevados */
--dps-color-surface-container-highest:  #e1e2e9;  /* Headers, nav bars */
--dps-color-on-surface:                 #191c20;  /* Texto principal */
--dps-color-on-surface-variant:         #43474e;  /* Texto secundário */
```

### 2.6 Bordas e Divisores

```css
--dps-color-outline:          #73777f;  /* Bordas de campos, divisores */
--dps-color-outline-variant:  #c3c6cf;  /* Bordas sutis, separadores */
```

### 2.7 Regras de Uso de Cor

1. **Sempre via tokens** — nunca usar hex literal. Use `var(--dps-color-*)`.
2. **Pareamento obrigatório** — `on-primary` sobre `primary`, `on-surface` sobre `surface`, etc.
3. **Contraste WCAG AA** — mínimo 4.5:1 para texto, 3:1 para elementos gráficos.
4. **Cores de status com propósito** — error, success, warning apenas para comunicar estado, nunca decorativo.
5. **Máximo 3 papéis de cor por tela** (excluindo neutros e status).

### 2.8 Tema Escuro

Todos os tokens possuem versão dark em `[data-dps-theme="dark"]`. Consulte `dps-design-tokens.css` para valores completos.

---

## 3. Tipografia

O M3 Expressive define 5 papéis tipográficos × 3 tamanhos = 15 estilos base, mais variantes enfatizadas.

### 3.1 Fontes

```css
/* Admin: stack do sistema — máxima performance */
--dps-font-system: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
                   "Helvetica Neue", Arial, sans-serif;

/* Portal/Público: fontes expressivas (Google Fonts) */
--dps-font-display: 'Outfit', var(--dps-font-system);
--dps-font-body:    'Source Sans 3', var(--dps-font-system);

/* Código e dados tabulares */
--dps-font-mono: 'JetBrains Mono', 'Fira Code', monospace;
```

**Regras:**
- **Admin**: usar exclusivamente `--dps-font-system`. Sem fontes externas.
- **Portal/Público**: máximo 2 famílias (`display` + `body`). Carregar com `font-display: swap`.
- **Apenas pesos necessários**: 400 (regular) e 500 (medium). Evitar 700/bold.

### 3.2 Escala Tipográfica M3

| Papel | Tamanho | Large | Medium | Small |
|-------|---------|-------|--------|-------|
| **Display** | Heróicos, banners | 57px / 64px | 45px / 52px | 36px / 44px |
| **Headline** | Títulos de seção | 32px / 40px | 28px / 36px | 24px / 32px |
| **Title** | Cards, listas | 22px / 28px | 16px / 24px | 14px / 20px |
| **Body** | Parágrafos | 16px / 24px | 14px / 20px | 12px / 16px |
| **Label** | Botões, chips | 14px / 20px | 12px / 16px | 11px / 16px |

> Formato: `font-size / line-height`. Tokens completos em `dps-design-tokens.css`.

### 3.3 Mapeamento Prático (Admin DPS)

| Elemento | Papel M3 | Resultado |
|----------|----------|-----------|
| Título da página (H1) | Headline Large | 32px, weight 400 |
| Seções (H2) | Headline Small | 24px, weight 400 |
| Subseções (H3) | Title Large | 22px, weight 500 |
| Corpo de texto | Body Medium | 14px, weight 400 |
| Descrições, help text | Body Small | 12px, weight 400 |
| Labels de campo | Label Large | 14px, weight 500 |
| Botões | Label Large | 14px, weight 500 |
| Headers de tabela | Label Medium | 12px, weight 500, uppercase |

### 3.4 Hierarquia Tipográfica

```css
/* H1 - Headline Large */
h1 { font-size: var(--dps-typescale-headline-large-size); /* 32px */
     line-height: var(--dps-typescale-headline-large-line);
     font-weight: var(--dps-typescale-headline-large-weight);
     color: var(--dps-color-on-surface); }

/* H2 - Headline Small */
h2 { font-size: var(--dps-typescale-headline-small-size); /* 24px */
     line-height: var(--dps-typescale-headline-small-line);
     font-weight: var(--dps-typescale-headline-small-weight); }

/* H3 - Title Large */
h3 { font-size: var(--dps-typescale-title-large-size); /* 22px */
     line-height: var(--dps-typescale-title-large-line);
     font-weight: var(--dps-typescale-title-large-weight); }

/* Texto corpo - Body Medium */
p, .dps-body { font-size: var(--dps-typescale-body-medium-size); /* 14px */
               line-height: var(--dps-typescale-body-medium-line); }

/* Texto auxiliar - Body Small */
small, .dps-caption { font-size: var(--dps-typescale-body-small-size); /* 12px */
                      color: var(--dps-color-on-surface-variant); }
```

---

## 4. Sistema de Formas (Shape)

O M3 Expressive introduz formas mais generosas e orgânicas, comunicando suavidade e acessibilidade.

### 4.1 Escala de Arredondamento

| Token | Valor | Uso |
|-------|-------|-----|
| `--dps-shape-none` | 0px | Imagens de borda a borda |
| `--dps-shape-extra-small` | 4px | Tooltips, snackbars, menus |
| `--dps-shape-small` | 8px | Chips, campos de texto |
| `--dps-shape-medium` | 12px | Cards, surfaces, containers |
| `--dps-shape-large` | 16px | FABs, sheets |
| `--dps-shape-extra-large` | 28px | Diálogos, modais, sheets grandes |
| `--dps-shape-full` | 9999px | Botões pill, badges, avatares |

### 4.2 Formas por Componente

```css
--dps-shape-button:   var(--dps-shape-full);         /* Pill — totalmente arredondado */
--dps-shape-fab:      var(--dps-shape-large);         /* 16px */
--dps-shape-card:     var(--dps-shape-medium);        /* 12px */
--dps-shape-dialog:   var(--dps-shape-extra-large);   /* 28px */
--dps-shape-input:    var(--dps-shape-extra-small);   /* 4px */
--dps-shape-chip:     var(--dps-shape-small);         /* 8px */
--dps-shape-badge:    var(--dps-shape-full);          /* Pill */
--dps-shape-surface:  var(--dps-shape-medium);        /* 12px */
```

### 4.3 Princípios de Forma

- **Cantos generosos** — o M3 Expressive favorece arredondamentos maiores que criam sensação de acolhimento
- **Consistência por tipo** — todos os botões usam o mesmo radius, todos os cards usam o mesmo radius
- **Botões pill** — forma `full` (totalmente arredondada) é o padrão M3 Expressive para botões
- **Hierarquia via forma** — elementos mais importantes podem ter formas mais expressivas (maior radius)
- **Sem misturar** — não combinar sharp corners com round corners no mesmo componente

---

## 5. Elevação

O M3 Expressive usa **elevação tonal** (variação de tom do fundo) em vez de sombras pesadas para criar hierarquia visual.

### 5.1 Níveis de Elevação

| Nível | Uso | Surface Container | Sombra |
|-------|-----|-------------------|--------|
| 0 | Superfície base da página | `surface` | Nenhuma |
| 1 | Cards, containers | `surface-container-low` | Mínima |
| 2 | Menus, dropdowns | `surface-container` | Sutil |
| 3 | FABs, diálogos | `surface-container-high` | Moderada |
| 4 | Hover de FABs | `surface-container-high` | Forte |
| 5 | Drag state | `surface-container-highest` | Máxima |

### 5.2 Sombras (tokens)

```css
--dps-elevation-0: none;
--dps-elevation-1: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
--dps-elevation-2: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
--dps-elevation-3: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
--dps-elevation-4: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
--dps-elevation-5: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
```

### 5.3 Princípios de Elevação

1. **Preferir elevação tonal** — use `surface-container-*` para criar camadas visuais antes de usar sombras
2. **Sombras são complementares** — usar para FABs, diálogos e estados de hover/drag
3. **Nunca sombra decorativa** — cada sombra deve comunicar interatividade ou elevação real
4. **Admin: elevação mínima** — cards em `surface-container-lowest` (#fff) sem sombra
5. **Portal: elevação expressiva** — cards podem usar `elevation-1` ou `elevation-2` para profundidade

---

## 6. Motion (Movimento)

O M3 Expressive usa **springs** (molas) para transições, criando animações que parecem físicas e naturais.

### 6.1 Esquemas de Motion

| Esquema | Quando usar | Sensação |
|---------|-------------|----------|
| **Expressive** | Momentos-chave (entrada de card, conclusão, transições) | Bounce sutil, vitalidade |
| **Standard** | Ações utilitárias (hover, press, toggle) | Suave, funcional |
| **Emphasized** | Entrada/saída de elementos (modais, menus) | Entrada lenta, saída rápida |

### 6.2 Easing Tokens

```css
/* Expressive (com overshoot) */
--dps-motion-easing-expressive-fast:     cubic-bezier(0.42, 1.67, 0.21, 0.90);
--dps-motion-easing-expressive-default:  cubic-bezier(0.38, 1.21, 0.22, 1.00);

/* Standard (sem overshoot) */
--dps-motion-easing-standard:            cubic-bezier(0.2, 0.0, 0.0, 1.0);

/* Emphasized (entrada/saída) */
--dps-motion-easing-emphasized-decelerate: cubic-bezier(0.05, 0.7, 0.1, 1.0);
--dps-motion-easing-emphasized-accelerate: cubic-bezier(0.3, 0.0, 0.8, 0.15);
```

### 6.3 Duração

```css
/* Curtas — hover, press, toggle */
--dps-motion-duration-short-3:  150ms;
--dps-motion-duration-short-4:  200ms;

/* Médias — abertura de menu, expansão de card */
--dps-motion-duration-medium-2: 300ms;
--dps-motion-duration-medium-3: 350ms;

/* Longas — transições de página, diálogos */
--dps-motion-duration-long-2:   500ms;
```

### 6.4 Atalhos Prontos para Uso

```css
/* Hover em qualquer elemento interativo */
transition: all var(--dps-motion-hover);
/* → 200ms cubic-bezier(0.2, 0, 0, 1) */

/* Entrada de elementos (cards, modais) */
transition: all var(--dps-motion-enter);
/* → 300ms cubic-bezier(0.05, 0.7, 0.1, 1) */

/* Saída de elementos */
transition: all var(--dps-motion-exit);
/* → 200ms cubic-bezier(0.3, 0, 0.8, 0.15) */

/* Expansão expressiva (accordion, detail) */
transition: all var(--dps-motion-expand);
/* → 350ms cubic-bezier(0.38, 1.21, 0.22, 1) — com bounce */
```

### 6.5 Regras de Motion

1. **CSS puro** — `@keyframes` e `transition`. Sem bibliotecas JS de animação.
2. **Funcional, não decorativo** — toda animação comunica feedback, orientação ou continuidade.
3. **`prefers-reduced-motion`** — **obrigatório**. O arquivo de tokens já inclui o override global.
4. **Admin: standard** — usar apenas hover/press transitions. Sem animações de entrada.
5. **Portal: expressive** — permitido stagger de cards, entrada animada, feedback de ação.
6. **Duração máxima** — 500ms para micro-interações, 800ms para transições de página.

### 6.6 Animações Expressivas (Portal/Público)

```css
/* Entrada de cards com stagger — M3 Expressive */
@keyframes dps-enter-from-below {
    from {
        opacity: 0;
        transform: translateY(16px) scale(0.97);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.dps-card-enter {
    animation: dps-enter-from-below
               var(--dps-motion-duration-medium-2)
               var(--dps-motion-easing-emphasized-decelerate)
               both;
}

/* Stagger para múltiplos cards */
.dps-card-enter:nth-child(1) { animation-delay: 0ms; }
.dps-card-enter:nth-child(2) { animation-delay: 50ms; }
.dps-card-enter:nth-child(3) { animation-delay: 100ms; }
.dps-card-enter:nth-child(4) { animation-delay: 150ms; }
.dps-card-enter:nth-child(5) { animation-delay: 200ms; }

/* Feedback de sucesso — pulse verde */
@keyframes dps-success-pulse {
    0%   { box-shadow: 0 0 0 0 rgba(26, 122, 58, 0.3); }
    70%  { box-shadow: 0 0 0 10px rgba(26, 122, 58, 0); }
    100% { box-shadow: 0 0 0 0 rgba(26, 122, 58, 0); }
}
```

---

## 7. State Layers (Estados Interativos)

O M3 Expressive usa **camadas de opacidade** sobre a cor do componente para indicar estados.

### 7.1 Opacidades de Estado

| Estado | Opacidade | Quando |
|--------|-----------|--------|
| Hover | 8% (`0.08`) | Mouse sobre elemento interativo |
| Focus | 10% (`0.10`) | Navegação por teclado |
| Pressed | 10% (`0.10`) | Clique/tap ativo |
| Dragged | 16% (`0.16`) | Arrastar elemento |
| Disabled | 38% (`0.38`) para conteúdo, 12% (`0.12`) para container | Inativo |

### 7.2 Implementação CSS

```css
/* State layer via pseudo-elemento */
.dps-interactive {
    position: relative;
    overflow: hidden;
}

.dps-interactive::before {
    content: '';
    position: absolute;
    inset: 0;
    background: currentColor;
    opacity: 0;
    transition: opacity var(--dps-motion-hover);
    pointer-events: none;
}

.dps-interactive:hover::before {
    opacity: var(--dps-state-hover-opacity);
}

.dps-interactive:focus-visible::before {
    opacity: var(--dps-state-focus-opacity);
}

.dps-interactive:active::before {
    opacity: var(--dps-state-pressed-opacity);
}
```

---

## 8. Componentes

### 8.1 Botões — M3 Expressive (Filled, Outlined, Text, FAB)

O M3 Expressive usa **botões pill** (totalmente arredondados) com cores sólidas.

```css
/* Filled Button (Primário) */
.dps-btn-filled {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: var(--dps-space-2);
    padding: var(--dps-space-3) var(--dps-space-6);
    height: 40px;
    min-width: 64px;
    background: var(--dps-color-primary);
    color: var(--dps-color-on-primary);
    border: none;
    border-radius: var(--dps-shape-button);
    font-size: var(--dps-typescale-label-large-size);
    font-weight: var(--dps-typescale-label-large-weight);
    letter-spacing: var(--dps-typescale-label-large-tracking);
    cursor: pointer;
    box-shadow: var(--dps-elevation-1);
    transition: box-shadow var(--dps-motion-hover),
                background var(--dps-motion-hover);
}

.dps-btn-filled:hover {
    box-shadow: var(--dps-elevation-2);
    background: var(--dps-color-primary-hover);
}

.dps-btn-filled:active {
    box-shadow: var(--dps-elevation-1);
    background: var(--dps-color-primary-pressed);
}

.dps-btn-filled:focus-visible {
    outline: 2px solid var(--dps-color-primary);
    outline-offset: 2px;
}

/* Outlined Button (Secundário) */
.dps-btn-outlined {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: var(--dps-space-2);
    padding: var(--dps-space-3) var(--dps-space-6);
    height: 40px;
    min-width: 64px;
    background: transparent;
    color: var(--dps-color-primary);
    border: 1px solid var(--dps-color-outline);
    border-radius: var(--dps-shape-button);
    font-size: var(--dps-typescale-label-large-size);
    font-weight: var(--dps-typescale-label-large-weight);
    letter-spacing: var(--dps-typescale-label-large-tracking);
    cursor: pointer;
    transition: background var(--dps-motion-hover),
                border-color var(--dps-motion-hover);
}

.dps-btn-outlined:hover {
    background: rgba(11, 107, 203, 0.08);
}

/* Tonal Button (Terciário) */
.dps-btn-tonal {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: var(--dps-space-2);
    padding: var(--dps-space-3) var(--dps-space-6);
    height: 40px;
    min-width: 64px;
    background: var(--dps-color-secondary-container);
    color: var(--dps-color-on-secondary-container);
    border: none;
    border-radius: var(--dps-shape-button);
    font-size: var(--dps-typescale-label-large-size);
    font-weight: var(--dps-typescale-label-large-weight);
    letter-spacing: var(--dps-typescale-label-large-tracking);
    cursor: pointer;
    box-shadow: var(--dps-elevation-0);
    transition: box-shadow var(--dps-motion-hover),
                background var(--dps-motion-hover);
}

.dps-btn-tonal:hover {
    box-shadow: var(--dps-elevation-1);
}

/* Text Button (Mínimo) */
.dps-btn-text {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: var(--dps-space-2);
    padding: var(--dps-space-3) var(--dps-space-3);
    height: 40px;
    background: transparent;
    color: var(--dps-color-primary);
    border: none;
    border-radius: var(--dps-shape-button);
    font-size: var(--dps-typescale-label-large-size);
    font-weight: var(--dps-typescale-label-large-weight);
    cursor: pointer;
    transition: background var(--dps-motion-hover);
}

.dps-btn-text:hover {
    background: rgba(11, 107, 203, 0.08);
}

/* FAB (Floating Action Button) */
.dps-fab {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 56px;
    height: 56px;
    background: var(--dps-color-primary-container);
    color: var(--dps-color-on-primary-container);
    border: none;
    border-radius: var(--dps-shape-fab);
    cursor: pointer;
    box-shadow: var(--dps-elevation-3);
    transition: box-shadow var(--dps-motion-hover);
}

.dps-fab:hover {
    box-shadow: var(--dps-elevation-4);
}
```

### 8.2 Cards

```css
/* Card padrão — elevação tonal */
.dps-card {
    background: var(--dps-color-surface-container-lowest);
    border: 1px solid var(--dps-color-outline-variant);
    border-radius: var(--dps-shape-card);
    padding: var(--dps-space-4);
    transition: box-shadow var(--dps-motion-hover);
}

/* Card elevado — com sombra */
.dps-card--elevated {
    background: var(--dps-color-surface-container-low);
    border: none;
    box-shadow: var(--dps-elevation-1);
}

.dps-card--elevated:hover {
    box-shadow: var(--dps-elevation-2);
}

/* Card filled — fundo tonal */
.dps-card--filled {
    background: var(--dps-color-surface-container-high);
    border: none;
}
```

### 8.3 Surface (Admin — Padrão DPS)

```css
/* Surface base — container padrão admin */
.dps-surface {
    background: var(--dps-color-surface-container-lowest);
    border: 1px solid var(--dps-color-outline-variant);
    border-radius: var(--dps-shape-surface);
    padding: var(--dps-space-5);
}

.dps-surface__title {
    font-size: var(--dps-typescale-title-medium-size);
    font-weight: var(--dps-typescale-title-medium-weight);
    line-height: var(--dps-typescale-title-medium-line);
    color: var(--dps-color-on-surface);
    margin-bottom: var(--dps-space-2);
    display: flex;
    align-items: center;
    gap: var(--dps-space-2);
}

.dps-surface__description {
    font-size: var(--dps-typescale-body-small-size);
    color: var(--dps-color-on-surface-variant);
    margin-bottom: var(--dps-space-4);
}

/* Variantes de surface com borda lateral colorida */
.dps-surface--primary  { border-left: 3px solid var(--dps-color-primary); }
.dps-surface--info     { border-left: 3px solid var(--dps-color-primary); }
.dps-surface--neutral  { /* padrão, sem borda colorida */ }
.dps-surface--success  { border-left: 3px solid var(--dps-color-success); }
.dps-surface--warning  { border-left: 3px solid var(--dps-color-warning); }
.dps-surface--danger   { border-left: 3px solid var(--dps-color-error); }
```

### 8.4 Inputs e Formulários

```css
/* Input padrão M3 — Outlined */
.dps-input {
    width: 100%;
    padding: var(--dps-space-4);
    border: 1px solid var(--dps-color-outline);
    border-radius: var(--dps-shape-input);
    font-size: var(--dps-typescale-body-large-size);
    color: var(--dps-color-on-surface);
    background: transparent;
    transition: border-color var(--dps-motion-hover),
                box-shadow var(--dps-motion-hover);
}

.dps-input:hover {
    border-color: var(--dps-color-on-surface);
}

.dps-input:focus {
    border-color: var(--dps-color-primary);
    border-width: 2px;
    padding: calc(var(--dps-space-4) - 1px);
    outline: none;
}

.dps-input::placeholder {
    color: var(--dps-color-on-surface-variant);
}

/* Label de campo */
.dps-field-label {
    font-size: var(--dps-typescale-label-large-size);
    font-weight: var(--dps-typescale-label-large-weight);
    color: var(--dps-color-on-surface-variant);
    margin-bottom: var(--dps-space-1);
    display: block;
}

/* Fieldset / Agrupamento */
.dps-field-group {
    border: 1px solid var(--dps-color-outline-variant);
    padding: var(--dps-space-5);
    margin-bottom: var(--dps-space-5);
    border-radius: var(--dps-shape-medium);
    background: var(--dps-color-surface-container-low);
}

.dps-field-group-title {
    font-size: var(--dps-typescale-title-small-size);
    font-weight: var(--dps-typescale-title-small-weight);
    color: var(--dps-color-on-surface);
    padding: 0 var(--dps-space-2);
}
```

### 8.4.1 Checkboxes — Estilo Elegante Inline

Checkboxes devem ser **discretos e proporcionais**. Nunca devem parecer botões ou cards.
O visual correto é um checkbox nativo com `accent-color` e label inline, sem borda, background ou padding excessivo.

```css
/* Checkbox M3 — inline elegante, sem card-like appearance */
.dps-checkbox {
    width: 18px;
    height: 18px;
    margin: 0 var(--dps-space-2) 0 0;
    accent-color: var(--dps-color-primary);
    cursor: pointer;
    flex-shrink: 0;
}

/* Label de checkbox — inline-flex, sem borda/background */
.dps-checkbox-label {
    display: inline-flex;
    align-items: center;
    font-weight: 400;                                   /* Nunca 500+ para checkbox labels */
    font-size: var(--dps-typescale-body-medium-size);    /* 14px */
    color: var(--dps-color-on-surface);
    cursor: pointer;
    padding: var(--dps-space-1) 0;
    background: none;                                   /* ❌ Nunca adicionar background */
    border: none;                                       /* ❌ Nunca adicionar borda */
}

.dps-checkbox-label:hover {
    color: var(--dps-color-primary);
}
```

**Anti-patterns de checkbox:**
- ❌ `padding: 10px 14px` + `border: 1px solid` + `background` em labels → cria aparência de botão/card
- ❌ `font-weight: 500` ou `700` em checkbox labels → peso visual desproporcional
- ❌ `input:hover` / `input:focus` genéricos sem excluir `[type="checkbox"]` → aplica estilos de input de texto
- ✅ `accent-color` para colorir o checkbox com a cor primária
- ✅ `inline-flex` com `align-items: center` para alinhamento elegante
- ✅ Tamanho 18×18px — proporção elegante sem exagero

### 8.5 Tabelas

```css
/* Headers */
.dps-table th {
    background: var(--dps-color-surface-container);
    font-size: var(--dps-typescale-label-medium-size);
    font-weight: var(--dps-typescale-label-medium-weight);
    letter-spacing: var(--dps-typescale-label-medium-tracking);
    text-transform: uppercase;
    color: var(--dps-color-on-surface-variant);
    padding: var(--dps-space-3) var(--dps-space-4);
    border-bottom: 1px solid var(--dps-color-outline-variant);
}

/* Linhas */
.dps-table td {
    padding: var(--dps-space-3) var(--dps-space-4);
    font-size: var(--dps-typescale-body-medium-size);
    color: var(--dps-color-on-surface);
    border-bottom: 1px solid var(--dps-color-outline-variant);
}

.dps-table tbody tr {
    transition: background var(--dps-motion-hover);
}

.dps-table tbody tr:hover {
    background: var(--dps-color-surface-container-low);
}

/* Wrapper responsivo */
.dps-table-wrapper {
    overflow-x: auto;
    border-radius: var(--dps-shape-medium);
    border: 1px solid var(--dps-color-outline-variant);
}
```

### 8.6 Chips e Badges

```css
/* Chip — filtros, tags, seleções */
.dps-chip {
    display: inline-flex;
    align-items: center;
    gap: var(--dps-space-1);
    padding: var(--dps-space-1) var(--dps-space-3);
    height: 32px;
    background: var(--dps-color-surface-container-low);
    border: 1px solid var(--dps-color-outline-variant);
    border-radius: var(--dps-shape-chip);
    font-size: var(--dps-typescale-label-large-size);
    font-weight: var(--dps-typescale-label-large-weight);
    color: var(--dps-color-on-surface-variant);
    cursor: pointer;
    transition: background var(--dps-motion-hover),
                border-color var(--dps-motion-hover);
}

.dps-chip:hover {
    background: var(--dps-color-surface-container);
}

.dps-chip--selected {
    background: var(--dps-color-secondary-container);
    color: var(--dps-color-on-secondary-container);
    border-color: transparent;
}

/* Badge — contadores, status */
.dps-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 24px;
    height: 24px;
    padding: 0 var(--dps-space-2);
    background: var(--dps-color-primary-container);
    color: var(--dps-color-on-primary-container);
    border-radius: var(--dps-shape-badge);
    font-size: var(--dps-typescale-label-small-size);
    font-weight: var(--dps-typescale-label-small-weight);
}

/* Status badges coloridos */
.dps-badge--success { background: var(--dps-color-success-container); color: var(--dps-color-on-success-container); }
.dps-badge--warning { background: var(--dps-color-warning-container); color: var(--dps-color-on-warning-container); }
.dps-badge--error   { background: var(--dps-color-error-container);   color: var(--dps-color-on-error-container); }
```

### 8.7 Avisos e Alertas

```css
/* Alerta M3 — borda lateral + container colorido */
.dps-alert {
    display: flex;
    gap: var(--dps-space-3);
    padding: var(--dps-space-4) var(--dps-space-5);
    border-radius: var(--dps-shape-medium);
    border-left: 3px solid;
}

.dps-alert--info {
    background: var(--dps-color-primary-container);
    color: var(--dps-color-on-primary-container);
    border-left-color: var(--dps-color-primary);
}

.dps-alert--success {
    background: var(--dps-color-success-container);
    color: var(--dps-color-on-success-container);
    border-left-color: var(--dps-color-success);
}

.dps-alert--warning {
    background: var(--dps-color-warning-container);
    color: var(--dps-color-on-warning-container);
    border-left-color: var(--dps-color-warning);
}

.dps-alert--error {
    background: var(--dps-color-error-container);
    color: var(--dps-color-on-error-container);
    border-left-color: var(--dps-color-error);
}
```

### 8.8 Tooltips

```css
.dps-tooltip {
    position: relative;
    display: inline-block;
    cursor: help;
}

.dps-tooltip:hover::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: calc(100% + var(--dps-space-2));
    left: 50%;
    transform: translateX(-50%);
    padding: var(--dps-space-1) var(--dps-space-2);
    background: var(--dps-color-inverse-surface);
    color: var(--dps-color-inverse-on-surface);
    border-radius: var(--dps-shape-tooltip);
    font-size: var(--dps-typescale-body-small-size);
    white-space: nowrap;
    box-shadow: var(--dps-elevation-2);
    animation: dps-enter-from-below var(--dps-motion-duration-short-3)
               var(--dps-motion-easing-emphasized-decelerate) both;
}
```

---

## 9. Espaçamento

### 9.1 Escala de Espaçamento

```css
--dps-space-1:   4px    /* Entre ícone e texto */
--dps-space-2:   8px    /* Padding interno mínimo */
--dps-space-3:  12px    /* Margem entre elementos próximos */
--dps-space-4:  16px    /* Padding padrão de inputs */
--dps-space-5:  20px    /* Padding de containers */
--dps-space-6:  24px    /* Margem entre seções */
--dps-space-8:  32px    /* Separação entre blocos */
--dps-space-10: 40px    /* Espaçamento amplo */
--dps-space-12: 48px    /* Separação de seções grandes */
--dps-space-16: 64px    /* Espaçamento máximo */
```

### 9.2 Aplicação

- **Sempre múltiplos de 4px** — usar tokens, nunca valores ad-hoc
- **Espaço em branco generoso** — priorizar respiração sobre densidade
- **Mínimo 16px** (`--dps-space-4`) entre campos de formulário
- **24px** (`--dps-space-6`) entre seções dentro de um card
- **32px** (`--dps-space-8`) entre cards/blocos principais

---

## 10. Ícones

### 10.1 Recomendação M3

O Material 3 Expressive recomenda **Material Symbols** (Google Fonts) no estilo **Rounded** para consistência com as formas expressivas. Para o contexto WordPress:

- **Admin**: ícones Dashicons do WordPress ou emojis Unicode (consistente com WP)
- **Portal/Público**: Material Symbols Rounded (via Google Fonts) ou SVG inline
- **Evitar**: icon fonts pesadas (FontAwesome completo), ícones decorativos sem função

### 10.2 Ícones Unicode Aprovados (Admin)

```
✓ (U+2713) — Sucesso, Confirmado
⚠ (U+26A0) — Aviso, Atenção
✕ (U+2715) — Erro, Fechar
🔍 (U+1F50D) — Busca
📋 (U+1F4CB) — Copiar
📊 (U+1F4CA) — Estatísticas
🐾 (U+1F43E) — Pet (identidade)
📅 (U+1F4C5) — Agendamento
```

### 10.3 Regras

- **Sempre com texto** — ícone nunca substitui label (exceto universais como ✕ para fechar)
- **Tamanho consistente** — 20px para ícones inline, 24px para ícones de ação
- **Cor via token** — `var(--dps-color-on-surface)` ou `var(--dps-color-primary)`

---

## 11. Responsividade

### 11.1 Breakpoints M3 Adaptados

```css
/* Compact — Mobile */
@media (max-width: 599px) { /* 1 coluna, bottom nav, padding 16px */ }

/* Medium — Tablet Portrait */
@media (min-width: 600px) and (max-width: 839px) { /* Nav rail, 2 colunas */ }

/* Expanded — Tablet Landscape / Desktop */
@media (min-width: 840px) and (max-width: 1199px) { /* Nav drawer, grid flexível */ }

/* Large — Desktop */
@media (min-width: 1200px) { /* Layout completo, max-width 1200px */ }

/* Extra Large — Wide Desktop */
@media (min-width: 1600px) { /* Multi-painel, dashboards amplos */ }
```

### 11.2 Estratégias

1. **Mobile-first** — CSS base para mobile, expandir com `min-width`
2. **Tabelas** — `overflow-x: auto` no wrapper + reorganização em cards para mobile
3. **Formulários** — campos `width: 100%`, agrupados em fieldsets
4. **Navegação** — tabs → dropdown/accordion em compact
5. **Touch targets** — mínimo `48×48px` para elementos interativos em mobile
6. **Zoom iOS** — inputs com `font-size: 16px` em mobile
7. **Validação obrigatória** — revisar no mínimo em `375px`, `600px`, `840px`, `1200px` e `1920px` antes de considerar a UI pronta
8. **Defeitos bloqueadores** — scroll horizontal, conteúdo cortado, CTA fora da viewport, modal maior que a tela e tabela sem estratégia mobile bloqueiam aceite

---

## 12. Padrões de Layout (Admin)

### 12.1 Layout Empilhado (Padrão)

```css
.dps-section-stack {
    display: flex;
    flex-direction: column;
    gap: var(--dps-space-6);
}
```

### 12.2 Estrutura HTML Padrão

```html
<div class="dps-section" id="dps-section-[nome]">
    <!-- Header -->
    <h2 class="dps-section-title">
        <span class="dps-section-title__icon">[emoji]</span>
        Gestão de [Nome]
    </h2>
    <p class="dps-section-subtitle">[Descrição]</p>

    <!-- Cards empilhados -->
    <div class="dps-section-stack">
        <div class="dps-surface dps-surface--primary">
            <div class="dps-surface__title">
                <span>📊</span> Estatísticas
            </div>
            <p class="dps-surface__description">[Descrição]</p>
            <!-- Conteúdo -->
        </div>

        <div class="dps-surface">
            <div class="dps-surface__title">
                <span>📋</span> Listagem
            </div>
            <!-- Tabela -->
        </div>
    </div>
</div>
```

### 12.3 Layout de Dashboard (Portal)

```css
/* Grid responsivo para dashboard do cliente */
.dps-portal-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: var(--dps-space-6);
}

/* Card amplo (ocupa 2 colunas) */
.dps-portal-grid__wide {
    grid-column: 1 / -1;
}

@media (min-width: 840px) {
    .dps-portal-grid__wide {
        grid-column: span 2;
    }
}
```

---

## 13. Acessibilidade

### 13.1 Requisitos Obrigatórios

- **Contraste WCAG AA** — 4.5:1 para texto, 3:1 para gráficos
- **Focus visible** — todo elemento interativo com `:focus-visible` visível:
  ```css
  :focus-visible {
      outline: 2px solid var(--dps-color-primary);
      outline-offset: 2px;
  }
  ```
- **Semântica HTML** — `<button>`, `<nav>`, `<main>`, `<section>`, `<fieldset>`
- **Labels** — todo input com `<label>` associado (`for`/`id`)
- **ARIA** — `aria-label`, `aria-describedby`, `aria-live` para feedback dinâmico
- **`prefers-reduced-motion`** — incluído no tokens CSS, obrigatório
- **Touch targets** — mínimo `48×48px` em mobile
- **Zoom** — inputs com `font-size: 16px` em mobile para evitar zoom iOS

---

## 14. Checklist de Implementação

### Antes de codar
- [ ] Contexto identificado (admin / portal / público / consentimento)
- [ ] Design tokens CSS importado (`dps-design-tokens.css`)
- [ ] `FRONTEND_DESIGN_INSTRUCTIONS.md` consultado para metodologia

### Durante a implementação
- [ ] Cores via tokens: `var(--dps-color-*)`
- [ ] Tipografia via escala M3: `var(--dps-typescale-*)`
- [ ] Formas via tokens: `var(--dps-shape-*)`
- [ ] Espaçamento via tokens: `var(--dps-space-*)`
- [ ] Motion via tokens: `var(--dps-motion-*)`
- [ ] Elevação via tokens: `var(--dps-elevation-*)`
- [ ] Estados interativos com opacidades M3
- [ ] Semântica HTML correta
- [ ] `prefers-reduced-motion` respeitado (automático via tokens)

### Validação
- [ ] Testado em 375px, 600px, 840px, 1200px, 1920px
- [ ] Sem scroll horizontal ou conteúdo cortado em nenhum breakpoint validado
- [ ] CTA primária e ações críticas acessíveis sem bloqueio visual em compact e medium
- [ ] Tabelas/listagens com estratégia explícita para compact (`overflow-x`, cards, colapso ou priorização)
- [ ] Modais, drawers e popovers cabem na viewport com rolagem interna quando necessário
- [ ] Contraste WCAG AA verificado
- [ ] Navegação por teclado funcional
- [ ] Focus visible em elementos interativos
- [ ] Theme dark funcional (se aplicável)
- [ ] Performance — assets carregados condicionalmente

---

## 15. Anti-padrões (NUNCA fazer)

### Visual
- ❌ Hex literais no código — usar `var(--dps-color-*)`
- ❌ Valores de radius ad-hoc — usar `var(--dps-shape-*)`
- ❌ Sombras decorativas em containers estáticos
- ❌ Mais de 2 famílias tipográficas por página
- ❌ Animações sem `prefers-reduced-motion`
- ❌ Ícones sem label de texto

### Técnico
- ❌ CSS inline em PHP (`style="..."`)
- ❌ `!important` exceto para override de tema WordPress
- ❌ Bibliotecas CSS/JS externas sem justificativa
- ❌ `<div>` onde `<button>`, `<nav>`, `<section>` é mais semântico

### UX
- ❌ Páginas "all-in-one" sem agrupamento
- ❌ Ações destrutivas sem confirmação
- ❌ Feedback silencioso (ações sem resposta visual)
- ❌ Scroll horizontal em mobile

---

## 16. Migração do Sistema Legado

### 16.1 Mapeamento de Tokens Antigos → Novos

| Token Legado | Token M3 Expressive |
|-------------|---------------------|
| `--dps-bg-primary` | `var(--dps-color-surface)` |
| `--dps-bg-white` | `var(--dps-color-surface-container-lowest)` |
| `--dps-border` | `var(--dps-color-outline-variant)` |
| `--dps-text-primary` | `var(--dps-color-on-surface)` |
| `--dps-text-secondary` | `var(--dps-color-on-surface-variant)` |
| `--dps-accent` | `var(--dps-color-primary)` |
| `--dps-accent-hover` | `var(--dps-color-primary-hover)` |
| `--dps-success` | `var(--dps-color-success)` |
| `--dps-error` | `var(--dps-color-error)` |
| `--dps-warning` | `var(--dps-color-warning)` |

### 16.2 Mapeamento de Componentes

| Classe Legada | Classe M3 Expressive |
|--------------|---------------------|
| `.dps-btn-primary` | `.dps-btn-filled` |
| `.dps-btn-secondary` | `.dps-btn-outlined` |
| `.dps-btn-success` | `.dps-btn-filled` (com cor de sucesso) |
| `.notice-*` | `.dps-alert--*` |
| `border-radius: 4px` (containers) | `var(--dps-shape-medium)` (12px) |
| `border-radius: 8px` (botões) | `var(--dps-shape-button)` (pill) |

### 16.3 Estratégia de Migração

1. **Importar `dps-design-tokens.css`** como primeiro stylesheet em todos os plugins
2. **Aliases de compatibilidade** já incluídos no arquivo de tokens (seção 11)
3. **Migrar componente por componente** — começar por botões e surfaces
4. **Manter classes legadas funcionando** enquanto migra para novas classes
5. **Remover aliases** após migração completa de todos os plugins

---

## 17. Manutenção

**Atualizar este documento quando:**
- Novo componente visual for criado
- Paleta de cores mudar (sincronizar com `dps-design-tokens.css`)
- Nova família tipográfica for adotada
- Novo padrão de interação for estabelecido
- Atualização relevante do Material 3 Expressive for publicada

**Versionamento:**
- Major (2.x): mudanças na paleta, tipografia ou sistema de formas
- Minor (x.1): novos componentes, breakpoints ou animações
- Patch (x.x.1): correções e esclarecimentos

**Relação com outros documentos:**
- `FRONTEND_DESIGN_INSTRUCTIONS.md` — metodologia e contextos de uso (complementar)
- `dps-design-tokens.css` — implementação CSS dos tokens definidos aqui (autoritativo)
- Em conflito: tokens CSS > Style Guide > Instructions

---

**Fim do Guia de Estilo Visual DPS v2.0 — Material 3 Expressive**
