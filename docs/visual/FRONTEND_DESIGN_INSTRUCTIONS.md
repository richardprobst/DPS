# Instruções de Design Frontend — DPS (Material 3 Expressive)

**Versão:** 2.0  
**Última atualização:** 07/02/2026  
**Base:** [Material 3 Expressive](https://m3.material.io/) do Google  
**Complementa:** `VISUAL_STYLE_GUIDE.md` (tokens, componentes, paleta)

---

## 1. Propósito

Este documento define as **instruções completas** para criação de interfaces frontend no sistema DPS, baseadas no **Material 3 Expressive** do Google. Deve ser consultado **sempre que** um agente (humano ou IA) criar ou modificar:
- Páginas administrativas (admin dashboard)
- Portal do cliente (client-facing)
- Formulários públicos (agendamento, consentimento)
- Componentes reutilizáveis (surfaces, cards, tabelas)

### Relação com documentos existentes

| Documento | O que cobre | Quando consultar |
|-----------|------------|-----------------|
| Este documento | Metodologia, contextos de uso, decisões criativas, princípios M3 | **Sempre** — antes de codar qualquer frontend |
| `VISUAL_STYLE_GUIDE.md` | Tokens, paleta de cores, tipografia, componentes CSS, formas, elevação | Implementação de estilos e componentes |
| `dps-design-tokens.css` | CSS custom properties — implementação dos tokens M3 | Referência técnica durante codificação |
| `ADMIN_LAYOUT_ANALYSIS.md` | Análise de problemas de layout admin | Refatoração de telas admin |

---

## 2. O que é Material 3 Expressive?

O **Material 3 Expressive** é a evolução mais recente do Material Design do Google (2025), focada em:

1. **Expressividade emocional** — interfaces que criam conexão com o usuário, não apenas funcionalidade
2. **Usabilidade comprovada** — pesquisa UX mostrou identificação de ações-chave até 4× mais rápida
3. **Formas orgânicas** — cantos mais arredondados, botões pill, shapes expressivos
4. **Motion com personalidade** — transições baseadas em springs (física) que parecem naturais
5. **Sistema de cor tonal** — hierarquia visual via tons de superfície em vez de sombras pesadas
6. **Design tokens** — todas as decisões visuais centralizadas em variáveis reutilizáveis

### Por que M3 Expressive no DPS?

O DPS é um sistema para **pet shops** — um negócio que depende de **confiança**, **carinho** e **profissionalismo**. O M3 Expressive é ideal porque:

- **Formas arredondadas** comunicam acolhimento e cuidado (como o próprio serviço pet)
- **Cores tonais** criam atmosfera serena e profissional
- **Motion expressivo** (bounce sutil) traz personalidade sem perder seriedade
- **Tokens centralizados** garantem consistência visual em 17 plugins/add-ons
- **Acessibilidade nativa** — contraste e `prefers-reduced-motion` incluídos por design

---

## 3. Design Thinking — Antes de Codar

Antes de escrever qualquer HTML/CSS/PHP, responda estas perguntas:

### 3.1 Contexto

- **Quem usa?** Staff (admin), cliente final (portal), visitante (público)?
- **Qual o objetivo?** Gestão de dados, consulta rápida, cadastro, tomada de decisão?
- **Qual o volume de informação?** Poucos campos vs. tabelas densas vs. dashboards?
- **Qual dispositivo predominante?** Desktop (admin) vs. mobile-first (portal/público)?

### 3.2 Direção Estética — M3 Expressive por Contexto

O DPS utiliza **dois perfis expressivos**, definidos pelo contexto:

| Contexto | Perfil M3 | Expressividade | Tokens |
|----------|-----------|----------------|--------|
| **Admin/Dashboard** | **Standard** — funcional, limpo, eficiente | Baixa — motion standard, formas consistentes, cores neutras | Easing standard, elevation 0-1 |
| **Portal do Cliente** | **Expressive** — acolhedor, moderno, confiável | Média — motion com bounce, stagger de cards, cores tonais | Easing expressive, elevation 1-2 |
| **Páginas Públicas** | **Expressive** — adaptável ao tema, identidade DPS | Média-alta — animações de entrada, formas expressivas | Font display, easing expressive |
| **Formulários de Consentimento** | **Standard** — formal, limpo, legível | Baixa — sem animações, tipografia clara, contraste alto | Body large, elevation 0 |

### 3.3 Diferenciação — O que torna memorável?

Para cada interface, identifique **um elemento diferenciador M3 Expressive**:

- **Shape morphing** — um botão que muda de forma ao ser pressionado (ex.: ícone → chip)
- **Stagger expressivo** — cards que entram em cascata com timing natural
- **Container colorido** — usar `primary-container` ou `tertiary-container` para destacar
- **Elevation responsiva** — card que ganha sombra ao hover, comunicando interatividade
- **Feedback tátil visual** — ripple effect ou pulse ao completar uma ação

**Princípio M3:** Expressividade a serviço da função. Um detalhe bem orquestrado vale mais que dez efeitos dispersos.

---

## 4. Tipografia — M3 Expressive

### 4.1 Admin (obrigatório)

```css
/* Stack do sistema — sem fontes externas */
font-family: var(--dps-font-system);
```

Usar **exclusivamente** a stack do sistema no admin. A escala tipográfica M3 (Display → Label) se aplica nos tamanhos, não nas fontes.

### 4.2 Portal do Cliente e Páginas Públicas

```css
/* Fontes expressivas com personalidade */
--dps-font-display: 'Outfit', var(--dps-font-system);
--dps-font-body:    'Source Sans 3', var(--dps-font-system);
```

**Regras M3:**
- **Máximo 2 famílias** — uma display (títulos) + uma body (texto)
- **Apenas pesos 400 e 500** — evitar bold (700) no M3 Expressive
- **`font-display: swap`** — performance e fallback garantidos
- **Fontes self-hosted ou Google Fonts** — nunca CDNs sem fallback

**Fontes recomendadas para o contexto pet:**

```css
/* Padrão DPS — Moderno e acolhedor */
'Outfit' (display) + 'Source Sans 3' (body)

/* Alternativa 1 — Amigável */
'Nunito' (display) + 'Lato' (body)

/* Alternativa 2 — Profissional */
'DM Sans' (display) + 'Work Sans' (body)
```

### 4.3 Escala de Uso

Consultar o mapeamento prático completo no `VISUAL_STYLE_GUIDE.md`, seção 3.3.

- **H1 único** por página — Headline Large (32px)
- **H2 para seções** — Headline Small (24px)
- **H3 para subseções** — Title Large (22px)
- **Corpo** — Body Medium (14px)
- **Auxiliar** — Body Small (12px)
- **Botões e labels** — Label Large (14px, weight 500)

---

## 5. Cor e Tema — M3 Expressive

### 5.1 Sistema de Papéis de Cor (Color Roles)

O M3 Expressive organiza cores em **papéis semânticos**, não em valores fixos:

```
Primary      → ações principais (botões, links, FABs)
Secondary    → ações de suporte (filtros, chips)
Tertiary     → destaques expressivos (badges, acentos)
Error        → erros e ações destrutivas
Success      → confirmações (extensão DPS)
Warning      → pendências (extensão DPS)
Surface      → fundos e containers (com variação tonal)
Outline      → bordas e divisores
```

Cada papel tem 4 tokens: `color`, `on-color`, `color-container`, `on-color-container`.

### 5.2 Estratégia de Cor M3

1. **Dominância tonal** — superfícies em tons neutros (`surface-container-*`), cor apenas em ações e status
2. **Pareamento obrigatório** — `on-primary` sobre `primary`, nunca combinar arbitrariamente
3. **Containers para destaque suave** — usar `primary-container` em vez de `primary` para cards destacados
4. **Contraste WCAG AA** — garantido pelo sistema de pareamento M3
5. **Tema escuro** — todos os tokens possuem variante dark (ver `dps-design-tokens.css`)

### 5.3 O que evitar

- ❌ Hex literais no código — sempre `var(--dps-color-*)`
- ❌ Mais de 3 papéis de cor por tela (excluindo neutros e status)
- ❌ Cores sem pareamento (`primary` sem `on-primary`)
- ❌ Gradientes em containers (reservar para casos especiais de portal)
- ❌ Opacidade que comprometa contraste

---

## 6. Formas — M3 Expressive

### 6.1 Filosofia

O M3 Expressive usa formas **mais arredondadas e orgânicas** que transmitem suavidade:

- **Botões pill** (totalmente arredondados) — `border-radius: var(--dps-shape-full)`
- **Cards com cantos generosos** — `border-radius: var(--dps-shape-medium)` (12px)
- **Chips expressivos** — `border-radius: var(--dps-shape-small)` (8px)
- **Diálogos acolhedores** — `border-radius: var(--dps-shape-extra-large)` (28px)

### 6.2 Regras

1. **Sempre via token** — nunca usar `border-radius: 8px` literal, usar `var(--dps-shape-small)`
2. **Consistência por tipo** — todos os botões com o mesmo shape, todos os cards com o mesmo shape
3. **Hierarquia via forma** — elementos mais importantes podem ter formas mais expressivas
4. **Sem misturar** — não combinar cantos sharp e round no mesmo componente

### 6.3 Transição do Sistema Anterior

| Antes (v1.x) | Agora (M3 Expressive) |
|-------------|----------------------|
| `border-radius: 4px` (containers) | `var(--dps-shape-medium)` → 12px |
| `border-radius: 8px` (botões) | `var(--dps-shape-full)` → pill |
| Sem variação | Escala completa: 0–4–8–12–16–28–pill |

---

## 7. Elevação — M3 Expressive

### 7.1 Elevação Tonal (Preferida)

O M3 usa **variação tonal** para criar profundidade, não sombras:

```
Mais elevado → surface-container-highest (mais escuro)
                ↕
Menos elevado → surface (mais claro)
```

### 7.2 Quando usar sombras

Sombras são **complementares** à elevação tonal:

| Componente | Elevação Tonal | Sombra |
|-----------|---------------|--------|
| Página (fundo) | `surface` | Nenhuma |
| Card estático | `surface-container-lowest` | Nenhuma ou `elevation-1` |
| Card interativo | `surface-container-low` | `elevation-1` → `elevation-2` no hover |
| Menu/dropdown | `surface-container` | `elevation-2` |
| Diálogo/modal | `surface-container-high` | `elevation-3` |
| FAB | `primary-container` | `elevation-3` → `elevation-4` no hover |
| Tooltip | `inverse-surface` | `elevation-2` |

### 7.3 Admin vs. Portal

- **Admin**: elevação tonal mínima, sombras apenas em menus e tooltips
- **Portal**: elevação expressiva, cards podem usar `elevation-1` com hover para `elevation-2`

---

## 8. Motion — M3 Expressive

### 8.1 Filosofia M3

Motion no M3 Expressive é **baseado em springs** (molas físicas), criando transições que:
- **Respondem** — movimento começa imediatamente ao input
- **São naturais** — overshoot sutil que imita física real
- **Comunicam** — feedback, orientação e continuidade

### 8.2 Admin (Standard)

```css
/* Apenas transições básicas em estados interativos */
.dps-admin-element {
    transition: background var(--dps-motion-hover);
    /* → 200ms cubic-bezier(0.2, 0, 0, 1) */
}

/* NÃO usar no admin: */
/* ❌ Animações de entrada de página */
/* ❌ Stagger effects */
/* ❌ Scroll-triggered animations */
/* ❌ Shape morphing */
```

### 8.3 Portal (Expressive)

```css
/* Entrada de cards com bounce sutil */
.dps-portal-card {
    animation: dps-enter-from-below
               var(--dps-motion-duration-medium-2)
               var(--dps-motion-easing-emphasized-decelerate)
               both;
}

/* Stagger para lista de cards */
.dps-portal-card:nth-child(1) { animation-delay: 0ms; }
.dps-portal-card:nth-child(2) { animation-delay: 50ms; }
.dps-portal-card:nth-child(3) { animation-delay: 100ms; }

/* Hover expressivo em cards */
.dps-portal-card:hover {
    box-shadow: var(--dps-elevation-2);
    transform: translateY(-2px);
    transition: all var(--dps-motion-duration-short-4)
                var(--dps-motion-easing-expressive-fast);
}

/* Feedback de ação concluída */
@keyframes dps-success-pulse {
    0%   { box-shadow: 0 0 0 0 rgba(26, 122, 58, 0.3); }
    70%  { box-shadow: 0 0 0 10px rgba(26, 122, 58, 0); }
    100% { box-shadow: 0 0 0 0 rgba(26, 122, 58, 0); }
}
```

### 8.4 Regras de Implementação

1. **CSS puro** — `@keyframes` e `transition`. Sem bibliotecas JS de animação no WP admin.
2. **`prefers-reduced-motion`** — **obrigatório**. Já incluído globalmente no `dps-design-tokens.css`.
3. **Duração máxima** — 500ms para micro-interações, 800ms para transições de página.
4. **Um momento orquestrado** — em portal, investir em **uma** animação expressiva bem feita.
5. **Easing por contexto** — standard para admin, expressive para portal.

---

## 9. State Layers — M3 Expressive

O M3 Expressive usa **camadas de opacidade** sobre a cor do componente para comunicar estados interativos, em vez de mudanças de cor bruscas.

### 9.1 Como funciona

```
Estado normal:  cor base
Hover:          cor base + 8% da cor de conteúdo
Focus:          cor base + 10% da cor de conteúdo
Pressed:        cor base + 10% da cor de conteúdo
Disabled:       38% de opacidade no conteúdo, 12% no container
```

### 9.2 Implementação

```css
/* State layer via pseudo-elemento (M3 pattern) */
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

.dps-interactive:hover::before    { opacity: 0.08; }
.dps-interactive:focus-visible::before { opacity: 0.10; }
.dps-interactive:active::before   { opacity: 0.10; }
```

### 9.3 Quando usar

- **Botões** — state layer nativo (via background color change ou pseudo-elemento)
- **Cards interativos** — hover + pressed state layers
- **Chips** — hover e selected states
- **Linhas de tabela** — hover state layer sutil

---

## 10. Composição Espacial e Layout

### 10.1 Admin — Cards Empilhados

O admin usa **cards empilhados verticalmente** (`.dps-surface`). Padrão M3 Expressive:

```css
.dps-section-stack {
    display: flex;
    flex-direction: column;
    gap: var(--dps-space-6); /* 24px */
}
```

### 10.2 Portal — Grid Expressivo

```css
/* Grid responsivo M3 — auto-fit com mínimo generoso */
.dps-portal-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: var(--dps-space-6);
    padding: var(--dps-space-8); /* padding generoso no portal */
}
```

### 10.3 Regras Universais

- **Mobile-first** — CSS base para mobile, expandir com `min-width`
- **Breakpoints M3** — compact (0), medium (600px), expanded (840px), large (1200px)
- **Container** — `max-width: 1200px` em páginas públicas
- **Espaço negativo** — portal usa padding `32px` vs. admin `20px`
- **Touch targets** — `48×48px` mínimo em mobile (M3 Expressive recomenda 48px)

---

## 11. Fundos e Detalhes Visuais

### 11.1 Admin

```css
/* Fundo: surface puro, sem texturas */
background: var(--dps-color-surface);

/* Separação: bordas outline-variant e espaçamento */
border: 1px solid var(--dps-color-outline-variant);
```

### 11.2 Portal do Cliente

```css
/* Fundo com gradiente tonal sutil (M3 Expressive) */
.dps-portal-wrapper {
    background: var(--dps-color-surface);
    background-image:
        radial-gradient(ellipse at 20% 50%,
            rgba(11, 107, 203, 0.03) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 20%,
            rgba(26, 122, 58, 0.03) 0%, transparent 50%);
}

/* Separador decorativo M3 */
.dps-portal-divider {
    height: 1px;
    background: linear-gradient(
        to right,
        transparent,
        var(--dps-color-outline-variant) 20%,
        var(--dps-color-outline-variant) 80%,
        transparent
    );
    margin: var(--dps-space-8) 0;
}
```

### 11.3 Limites

- Texturas e gradientes **apenas** em containers raiz do portal
- Opacidade máxima de decoração: `0.05`
- Admin: **nunca** texturas, gradientes ou padrões de fundo
- Sem grain, noise, ou efeitos pesados

---

## 12. Acessibilidade — M3 Expressive

### 12.1 Requisitos Obrigatórios (todos os contextos)

- **Contraste WCAG AA** — 4.5:1 para texto, 3:1 para gráficos. O sistema de pareamento M3 (`on-*` sobre `*`) garante isso por design.
- **Focus visible** — outline de 2px em cor primary com offset de 2px:
  ```css
  :focus-visible {
      outline: 2px solid var(--dps-color-primary);
      outline-offset: 2px;
  }
  ```
- **Semântica HTML** — `<button>`, `<nav>`, `<main>`, `<section>`, `<fieldset>`, `<dialog>`
- **Labels** — todo input com `<label>` associado (`for`/`id`)
- **ARIA** — `aria-label`, `aria-describedby`, `aria-live="polite"` para feedback dinâmico
- **`prefers-reduced-motion`** — incluído globalmente no `dps-design-tokens.css`
- **Touch targets M3** — mínimo `48×48px` para elementos interativos em mobile
- **Zoom iOS** — inputs com `font-size: 16px` em mobile

### 12.2 Testes Recomendados

- Validar contraste com [WebAIM Contrast Checker](https://webaim.org/resources/contrastchecker/)
- Navegar por tab e verificar ordem lógica de foco
- Testar com `prefers-reduced-motion: reduce` ativo
- Verificar em leitor de tela (VoiceOver, NVDA) para fluxos críticos
- Verificar touch targets em dispositivos reais

---

## 13. Performance de Assets

### 13.1 CSS

- **`dps-design-tokens.css`** deve ser o **primeiro** stylesheet importado
- Carregar condicionalmente via `wp_enqueue_style()` apenas nas páginas necessárias
- Versionamento: `filemtime()` com fallback `DPS_BASE_VERSION`
- **Sem bibliotecas CSS externas** no admin (sem Bootstrap, Tailwind, etc.)
- CSS custom properties (`var(--dps-*)`) para todos os tokens

### 13.2 JavaScript

- **Vanilla JS** — sem frameworks no WP admin
- Padrão IIFE com `'use strict'`
- Eventos delegados em containers pai
- `wp_enqueue_script()` com dependências explícitas

### 13.3 Fontes

- **Admin**: zero fontes externas (stack do sistema)
- **Portal**: Google Fonts self-hosted ou `<link>` com `font-display: swap`
- Máximo 2 famílias, apenas pesos 400 + 500
- Preload para fontes críticas: `<link rel="preload" as="font">`

### 13.4 Imagens e Ícones

- **SVG inline** para ícones (preferível a icon fonts)
- **Material Symbols Rounded** para portal (via Google Fonts) — carregar apenas glifos usados
- `loading="lazy"` em imagens below-the-fold
- WebP como formato preferido
- Limite: 50KB por imagem, 200KB total por página

---

## 14. Checklist de Implementação Frontend M3

### Antes de codar
- [ ] Contexto identificado (admin / portal / público / consentimento)
- [ ] Perfil M3 escolhido (Standard para admin / Expressive para portal)
- [ ] Elemento diferenciador M3 definido (shape, motion, container colorido)
- [ ] `VISUAL_STYLE_GUIDE.md` consultado para tokens e componentes
- [ ] `dps-design-tokens.css` será importado como primeiro stylesheet

### Durante a implementação
- [ ] Cores via `var(--dps-color-*)`
- [ ] Tipografia via `var(--dps-typescale-*)`
- [ ] Formas via `var(--dps-shape-*)`
- [ ] Espaçamento via `var(--dps-space-*)`
- [ ] Motion via `var(--dps-motion-*)`
- [ ] Elevação via `var(--dps-elevation-*)`
- [ ] State layers com opacidades M3
- [ ] Semântica HTML correta
- [ ] Assets carregados condicionalmente
- [ ] `prefers-reduced-motion` respeitado (automático via tokens)

### Validação
- [ ] Testado em 375px, 600px, 840px, 1200px, 1920px
- [ ] Contraste WCAG AA verificado (sistema de pareamento M3)
- [ ] Navegação por teclado funcional
- [ ] Focus visible em elementos interativos
- [ ] Touch targets ≥ 48×48px em mobile
- [ ] Performance — sem bloqueio de renderização

---

## 15. Anti-padrões M3 Expressive (NUNCA fazer)

### Visual
- ❌ Hex literais — usar `var(--dps-color-*)`
- ❌ Radius ad-hoc — usar `var(--dps-shape-*)`
- ❌ Sombras decorativas sem comunicar elevação real
- ❌ Mais de 2 famílias tipográficas
- ❌ Animações sem `prefers-reduced-motion`
- ❌ Ícones sem label de texto
- ❌ Combinar cantos sharp e round no mesmo componente
- ❌ `font-weight: 700/bold` — M3 Expressive usa 400 e 500

### Técnico
- ❌ CSS inline em PHP (`style="..."`)
- ❌ `!important` exceto override de tema WordPress
- ❌ Bibliotecas CSS/JS sem justificativa
- ❌ Fontes de CDN sem fallback local
- ❌ `<div>` onde `<button>`, `<nav>`, `<section>` é mais semântico

### UX
- ❌ Páginas "all-in-one" sem agrupamento
- ❌ Formulários longos sem progressão
- ❌ Ações destrutivas sem confirmação
- ❌ Feedback silencioso (ações sem resposta visual)
- ❌ Scroll horizontal em mobile

---

## 16. Exemplos de Aplicação M3 Expressive

### 16.1 Card Admin (Standard)

```html
<div class="dps-surface dps-surface--primary">
    <div class="dps-surface__title">
        <span>📊</span>
        Estatísticas do Mês
    </div>
    <p class="dps-surface__description">
        Resumo de agendamentos e faturamento
    </p>
    <ul class="dps-inline-stats">
        <li>
            <span class="dps-badge">42</span>
            <span class="dps-caption">Agendamentos</span>
        </li>
        <li>
            <span class="dps-badge">R$ 3.150</span>
            <span class="dps-caption">Faturamento</span>
        </li>
    </ul>
</div>
```

### 16.2 Card Portal (Expressive)

```html
<div class="dps-card dps-card--elevated dps-card-enter">
    <div class="dps-card__header">
        <h3 style="font-size: var(--dps-typescale-title-medium-size);
                   font-weight: var(--dps-typescale-title-medium-weight);">
            Próximo Agendamento
        </h3>
        <span class="dps-badge--success">Confirmado</span>
    </div>
    <div class="dps-card__body">
        <p><strong>Rex</strong> — Banho e Tosa Completa</p>
        <p class="dps-caption">Sábado, 15 de Fevereiro às 10:00</p>
    </div>
    <div class="dps-card__actions">
        <button class="dps-btn-outlined">Reagendar</button>
        <button class="dps-btn-filled">Ver Detalhes</button>
    </div>
</div>
```

### 16.3 Alerta M3 Expressive

```html
<div class="dps-alert dps-alert--success">
    <span>✓</span>
    <div>
        <strong>Agendamento confirmado!</strong>
        <p>Rex está agendado para Sábado às 10:00.</p>
    </div>
</div>
```

### 16.4 Formulário M3

```html
<div class="dps-field-group">
    <span class="dps-field-group-title">Dados do Pet</span>

    <label class="dps-field-label" for="pet-name">Nome do Pet</label>
    <input class="dps-input" type="text" id="pet-name"
           placeholder="Ex.: Rex, Luna, Mel...">

    <label class="dps-field-label" for="pet-breed">Raça</label>
    <input class="dps-input" type="text" id="pet-breed"
           placeholder="Ex.: Golden Retriever">
</div>

<div class="dps-card__actions">
    <button class="dps-btn-text">Cancelar</button>
    <button class="dps-btn-filled">Salvar Pet</button>
</div>
```

---

## 17. Manutenção

**Atualizar este documento quando:**
- Novo contexto de frontend for criado (ex.: app mobile, PWA)
- Mudança no sistema de design M3 Expressive do Google
- Nova família tipográfica for adotada
- Novo padrão de componente M3 for estabelecido
- Mudança na paleta (sincronizar com `VISUAL_STYLE_GUIDE.md` e `dps-design-tokens.css`)

**Hierarquia de autoridade:**
1. `dps-design-tokens.css` — valores técnicos autoritativos
2. `VISUAL_STYLE_GUIDE.md` — especificações de componentes e tokens
3. Este documento — metodologia e contextos de uso

Em caso de conflito: tokens CSS > Style Guide > Instructions.

---

**Fim das Instruções de Design Frontend DPS v2.0 — Material 3 Expressive**