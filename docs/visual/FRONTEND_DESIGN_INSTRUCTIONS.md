# Instruções de Design Frontend — DPS

**Versão:** 1.0  
**Última atualização:** 06/02/2026  
**Complementa:** `VISUAL_STYLE_GUIDE.md` (paleta, componentes, espaçamento)

---

## 1. Propósito

Este documento define as **instruções completas** para criação de interfaces frontend no sistema DPS. Deve ser consultado **sempre que** um agente (humano ou IA) criar ou modificar:
- Páginas administrativas (admin dashboard)
- Portal do cliente (client-facing)
- Formulários públicos (agendamento, consentimento)
- Componentes reutilizáveis (surfaces, cards, tabelas)

### Relação com documentos existentes

| Documento | O que cobre | Quando consultar |
|-----------|------------|-----------------|
| Este documento | Processo de design, metodologia, decisões criativas, contextos de uso | **Sempre** — antes de codar qualquer frontend |
| `VISUAL_STYLE_GUIDE.md` | Paleta de cores, tipografia, espaçamento, componentes CSS | Implementação de estilos específicos |
| `ADMIN_LAYOUT_ANALYSIS.md` | Análise de problemas de layout admin | Refatoração de telas admin |
| `RESPONSIVENESS_ANALYSIS.md` | Breakpoints e responsividade | Ajustes mobile/tablet |

---

## 2. Design Thinking — Antes de Codar

Antes de escrever qualquer HTML/CSS/PHP, responda estas perguntas:

### 2.1 Contexto
- **Quem usa?** Staff (admin), cliente final (portal), visitante (público)?
- **Qual o objetivo?** Gestão de dados, consulta rápida, cadastro, tomada de decisão?
- **Qual o volume de informação?** Poucos campos vs. tabelas densas vs. dashboards?
- **Qual dispositivo predominante?** Desktop (admin) vs. mobile-first (portal/público)?

### 2.2 Direção Estética

O DPS utiliza **duas faixas de expressão visual**, definidas pelo contexto:

| Contexto | Estética | Liberdade criativa |
|----------|----------|-------------------|
| **Admin/Dashboard** | Minimalista/Clean — funcional, sem decoração | Baixa — seguir `VISUAL_STYLE_GUIDE.md` rigorosamente |
| **Portal do Cliente** | Clean com personalidade — acolhedor, moderno, confiável | Média — respeitar paleta base, mas permitir expressividade |
| **Páginas públicas** | Adaptável ao tema do site — integração visual com WordPress | Média-alta — adaptar ao tema ativo, manter identidade DPS |
| **Formulários de consentimento** | Formal, limpo, legível — inspiração editorial | Baixa — clareza e legibilidade acima de tudo |

### 2.3 Diferenciação — O que torna memorável?

Para cada interface, identifique **um elemento diferenciador**:
- Um micro-feedback que surpreende (ex.: animação sutil ao salvar)
- Uma visualização de dados inesperada (ex.: timeline ao invés de tabela)
- Uma interação que economiza tempo (ex.: inline editing, autocomplete inteligente)
- Uma organização de informação que facilita a decisão (ex.: cards com status visual)

**Princípio:** Intencionalidade > intensidade. Um detalhe bem executado vale mais que dez efeitos dispersos.

---

## 3. Tipografia

### 3.1 Admin (obrigatório)
```css
/* Sistema operacional — máxima performance e legibilidade */
font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
```
Usar exclusivamente a stack de fontes do sistema no admin. Sem fontes externas.

### 3.2 Portal do Cliente e Páginas Públicas (recomendado)

Quando criar interfaces client-facing que precisam de mais personalidade:

**Regras:**
- **Escolher fontes com intenção** — cada font-family comunica algo. Evitar fontes genéricas quando houver oportunidade de diferenciação.
- **Pairing tipográfico** — combinar uma fonte display (títulos) com uma fonte corpo (texto). Manter no máximo 2 famílias.
- **Google Fonts ou fontes self-hosted** — nunca depender de CDNs de terceiros sem fallback.
- **Performance** — carregar apenas os pesos necessários (400, 600 no máximo). Usar `font-display: swap`.

**Fontes a evitar** (excesso de uso reduz identidade):
- Inter, Roboto, Arial como escolha primária para títulos display
- Open Sans como padrão sem justificativa

**Exemplos de combinações eficazes:**
```css
/* Combinação 1: Moderno e profissional */
--dps-font-display: 'DM Sans', sans-serif;
--dps-font-body: 'Source Sans 3', sans-serif;

/* Combinação 2: Acolhedor e amigável (pet-friendly) */
--dps-font-display: 'Nunito', sans-serif;
--dps-font-body: 'Lato', sans-serif;

/* Combinação 3: Elegante e confiável */
--dps-font-display: 'Outfit', sans-serif;
--dps-font-body: 'Work Sans', sans-serif;
```

### 3.3 Hierarquia tipográfica (todos os contextos)
- **H1 único** por página/seção principal
- **H2** para seções — `font-size: 20px; font-weight: 600`
- **H3** para subseções — `font-size: 16px; font-weight: 600`
- **Corpo** — `font-size: 14px; line-height: 1.5`
- **Texto auxiliar** — `font-size: 13px; color: #6b7280`

Respeitar a escala definida no `VISUAL_STYLE_GUIDE.md`. Não inventar tamanhos intermediários.

---

## 4. Cor e Tema

### 4.1 Paleta base (obrigatória)

A paleta completa está definida em `VISUAL_STYLE_GUIDE.md`. Resumo executivo:

```css
:root {
    /* Neutros */
    --dps-bg-primary: #f9fafb;
    --dps-bg-white: #ffffff;
    --dps-border: #e5e7eb;
    --dps-text-primary: #374151;
    --dps-text-secondary: #6b7280;

    /* Destaque */
    --dps-accent: #0ea5e9;
    --dps-accent-hover: #0284c7;

    /* Status */
    --dps-success: #10b981;
    --dps-error: #ef4444;
    --dps-warning: #f59e0b;
}
```

### 4.2 Estratégia de cor

- **Cor dominante com acentos precisos** — o branco/cinza claro domina; o azul `#0ea5e9` aparece apenas em ações e elementos interativos.
- **Cores de status com propósito** — verde, vermelho e amarelo aparecem **apenas** para comunicar estado (sucesso, erro, alerta). Nunca decorativo.
- **Consistência via variáveis CSS** — toda cor deve ser referenciada por variável. Nunca usar hex literal solto no código.
- **Contraste acessível** — mínimo 4.5:1 para texto sobre fundo (WCAG AA).

### 4.3 O que evitar

- Gradientes de fundo em containers (exceto botões primários, conforme Style Guide)
- Paletas roxas/magenta sem justificativa contextual
- Mais de 3 cores diferentes por tela (excluindo neutros e status)
- Cores com opacidade que comprometam legibilidade

---

## 5. Motion e Micro-interações

### 5.1 Filosofia

Motion no DPS é **funcional, não decorativo**. Cada animação deve comunicar:
- **Feedback** — confirmar que uma ação aconteceu
- **Orientação** — guiar o olhar para o próximo passo
- **Continuidade** — suavizar transições entre estados

### 5.2 Admin (conservador)

```css
/* Transições básicas — apenas em estados interativos */
transition: background 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;

/* Hover em botões — sutil */
transform: translateY(-1px);

/* NÃO usar no admin: */
/* - Animações de entrada de página */
/* - Scroll-triggered effects */
/* - Partículas, ondas, efeitos visuais elaborados */
```

### 5.3 Portal do Cliente e Páginas Públicas (moderado)

Mais liberdade, mas com contenção:

```css
/* Entrada de cards (stagger) — permitido em portais */
@keyframes dps-fade-in-up {
    from {
        opacity: 0;
        transform: translateY(12px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.dps-surface {
    animation: dps-fade-in-up 0.3s ease-out both;
}

/* Stagger para múltiplos cards */
.dps-surface:nth-child(1) { animation-delay: 0ms; }
.dps-surface:nth-child(2) { animation-delay: 60ms; }
.dps-surface:nth-child(3) { animation-delay: 120ms; }
.dps-surface:nth-child(4) { animation-delay: 180ms; }

/* Feedback de ação bem-sucedida */
@keyframes dps-success-pulse {
    0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.3); }
    70% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
    100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}
```

### 5.4 Regras de implementação

- **Preferir CSS puro** — `@keyframes` e `transition` nativos. Sem bibliotecas JS de animação no WordPress admin.
- **Duração máxima** — 300ms para micro-interações, 500ms para transições de página.
- **`prefers-reduced-motion`** — sempre respeitar:
  ```css
  @media (prefers-reduced-motion: reduce) {
      *, *::before, *::after {
          animation-duration: 0.01ms !important;
          animation-iteration-count: 1 !important;
          transition-duration: 0.01ms !important;
      }
  }
  ```
- **Um momento orquestrado** — em interfaces client-facing, investir em **uma** animação bem feita (ex.: stagger de cards na carga) em vez de muitas animações dispersas.

---

## 6. Composição Espacial e Layout

### 6.1 Layout empilhado (padrão admin)

O admin usa **cards empilhados verticalmente** (`.dps-surface`). Não quebrar este padrão. Detalhes completos no `VISUAL_STYLE_GUIDE.md`, seção 9.

### 6.2 Portal do Cliente e páginas públicas

Mais liberdade para:
- **Grid responsivo** — `grid-template-columns: repeat(auto-fit, minmax(280px, 1fr))` para cards de informação
- **Assimetria controlada** — um card largo (2/3) + card lateral (1/3) para dashboard do cliente
- **Espaço negativo generoso** — `padding: 32px` em containers públicos vs. `20px` no admin
- **Elementos visuais de apoio** — separadores, ícones descritivos, badges de status

### 6.3 Regras universais de layout

- **Mobile-first** — escrever CSS base para mobile, expandir com `min-width` media queries.
- **Breakpoints DPS** — `480px`, `768px`, `1024px` (não inventar breakpoints intermediários).
- **Containers** — largura máxima `1200px` em páginas públicas, sem limite no admin (usa o container do WP).
- **Formulários** — campos `width: 100%` em mobile; agrupados em fieldsets quando > 5 campos.
- **Tabelas** — sempre com `overflow-x: auto` no wrapper para mobile.

---

## 7. Fundos, Texturas e Detalhes Visuais

### 7.1 Admin

- **Fundo:** `#f9fafb` ou `#ffffff`. Sem texturas, sem gradientes, sem padrões.
- **Separação visual:** Bordas `1px solid #e5e7eb` e espaçamento.
- **Elevação:** Reservada para modais e tooltips.

### 7.2 Portal do Cliente

Quando necessário criar atmosfera:

```css
/* Fundo sutil com textura (opcional para portais) */
.dps-portal-wrapper {
    background-color: #f9fafb;
    background-image:
        radial-gradient(circle at 20% 50%, rgba(14, 165, 233, 0.03) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, rgba(16, 185, 129, 0.03) 0%, transparent 50%);
}

/* Separador decorativo (opcional) */
.dps-portal-divider {
    height: 1px;
    background: linear-gradient(
        to right,
        transparent,
        #e5e7eb 20%,
        #e5e7eb 80%,
        transparent
    );
    margin: 32px 0;
}
```

**Limites:**
- Texturas e gradientes de fundo **apenas** em containers raiz (`.dps-portal-wrapper`)
- Opacidade máxima de efeitos decorativos: `0.05`
- Sem grain overlays, noise textures, ou efeitos pesados no contexto WordPress

---

## 8. Acessibilidade

### 8.1 Requisitos obrigatórios (todos os contextos)

- **Contraste** — mínimo 4.5:1 para texto, 3:1 para elementos gráficos (WCAG AA)
- **Focus visible** — todo elemento interativo deve ter `:focus-visible` com outline claro
  ```css
  :focus-visible {
      outline: 2px solid var(--dps-accent);
      outline-offset: 2px;
  }
  ```
- **Semântica HTML** — usar elementos corretos (`<button>`, `<nav>`, `<main>`, `<section>`, `<fieldset>`)
- **Labels** — todo input deve ter `<label>` associado (com `for`/`id`)
- **ARIA** — usar `aria-label`, `aria-describedby`, `aria-live` quando necessário para feedback dinâmico
- **`prefers-reduced-motion`** — obrigatório quando houver animações
- **Tamanho mínimo de toque** — `44x44px` para elementos interativos em mobile
- **Zoom** — inputs com `font-size: 16px` em mobile para evitar zoom automático no iOS

### 8.2 Testes recomendados

- Validar contraste com ferramentas como [WebAIM Contrast Checker](https://webaim.org/resources/contrastchecker/)
- Navegar por tab e verificar ordem lógica de foco
- Testar com `prefers-reduced-motion: reduce` ativo
- Verificar em leitor de tela (VoiceOver, NVDA) para fluxos críticos

---

## 9. Performance de Assets

### 9.1 CSS

- **Carregar condicionalmente** — `wp_enqueue_style()` apenas nas páginas necessárias
- **Versionamento** — `filemtime()` com fallback para `DPS_BASE_VERSION`
- **Sem bibliotecas CSS externas** no admin (sem Bootstrap, Tailwind, etc.)
- **CSS custom properties** (`var(--dps-*)`) para tokens de design reutilizáveis
- **Minificação** — quando disponível, servir `.min.css` em produção

### 9.2 JavaScript

- **Vanilla JS** — sem frameworks JS (React, Vue) no contexto WordPress admin
- **Padrão IIFE** com `'use strict'` conforme convenção do projeto
- **Eventos delegados** — usar `addEventListener` em containers pai
- **Enqueue correto** — `wp_enqueue_script()` com dependências explícitas (`jquery` quando necessário)

### 9.3 Imagens e ícones

- **SVG inline** para ícones pequenos (preferível a icon fonts)
- **Unicode** para ícones simples de status (ver lista aprovada no Style Guide)
- **Lazy loading** — `loading="lazy"` em imagens que não são above-the-fold
- **WebP** — formato preferido para imagens pesadas
- **Máximo:** 50KB por imagem individual; 200KB total de imagens por página

---

## 10. Checklist de Implementação Frontend

Ao criar ou modificar qualquer interface DPS:

### Antes de codar
- [ ] Contexto identificado (admin / portal / público / consentimento)
- [ ] Direção estética escolhida com intencionalidade
- [ ] Elemento diferenciador definido (o que torna memorável)
- [ ] `VISUAL_STYLE_GUIDE.md` consultado para paleta e componentes

### Durante a implementação
- [ ] Tipografia usando hierarquia definida (H1 > H2 > H3 > corpo)
- [ ] Cores via CSS variables (`var(--dps-*)`)
- [ ] Espaçamento em múltiplos de 4px
- [ ] Bordas `1px` em containers, `border-radius: 4px` (8px em botões)
- [ ] Assets carregados condicionalmente (`wp_enqueue_*`)
- [ ] Semântica HTML correta (buttons, labels, fieldsets, nav)
- [ ] Animations com `prefers-reduced-motion` respeitado

### Validação
- [ ] Testado em 375px, 768px, 1024px, 1920px
- [ ] Contraste WCAG AA verificado
- [ ] Navegação por teclado funcional
- [ ] Focus visible em elementos interativos
- [ ] Performance — sem bloqueio de renderização por assets

---

## 11. Anti-padrões de Design (NUNCA fazer)

### Visual
- ❌ Gradientes roxos ou paletas de cores genéricas de "template IA"
- ❌ Sombras exageradas (`box-shadow` > `12px` spread) em containers estáticos
- ❌ Mais de 2 famílias tipográficas por página
- ❌ Fundos com texturas pesadas (noise, grain) no contexto WordPress
- ❌ Animações sem propósito funcional
- ❌ Ícones sem label de texto (exceto quando universalmente reconhecidos)

### Técnico
- ❌ CSS inline em PHP (`style="..."`) — usar classes e stylesheets
- ❌ `!important` exceto quando sobrescrevendo estilos do tema WordPress
- ❌ Bibliotecas CSS/JS externas sem justificativa
- ❌ Fontes carregadas de CDNs sem fallback local
- ❌ `<div>` onde `<button>`, `<nav>`, `<section>` ou `<fieldset>` é mais semântico

### UX
- ❌ Páginas "all-in-one" que despejam toda informação de uma vez
- ❌ Formulários longos sem agrupamento ou progressão
- ❌ Ações destrutivas sem confirmação
- ❌ Feedback silencioso (ações sem resposta visual)
- ❌ Layouts que forçam scroll horizontal em mobile

---

## 12. Exemplos de Aplicação por Contexto

### 12.1 Novo card admin (Trilha A — conservador)

```html
<div class="dps-surface dps-surface--info">
    <div class="dps-surface__title">
        <span>📊</span>
        Estatísticas do Mês
    </div>
    <p class="dps-surface__description">
        Resumo de agendamentos e faturamento
    </p>
    <ul class="dps-inline-stats dps-inline-stats--panel">
        <li>
            <div class="dps-inline-stats__label">Agendamentos</div>
            <strong class="dps-inline-stats__value">42</strong>
        </li>
        <li>
            <div class="dps-inline-stats__label">Faturamento</div>
            <strong class="dps-inline-stats__value">R$ 3.150</strong>
        </li>
    </ul>
</div>
```

### 12.2 Card do portal do cliente (Trilha A — com personalidade)

```html
<div class="dps-portal-card" style="animation: dps-fade-in-up 0.3s ease-out both;">
    <div class="dps-portal-card__header">
        <h3 class="dps-portal-card__title">Próximo Agendamento</h3>
        <span class="dps-status-badge dps-status-badge--scheduled">Confirmado</span>
    </div>
    <div class="dps-portal-card__body">
        <p class="dps-portal-card__detail">
            <strong>Rex</strong> — Banho e Tosa Completa
        </p>
        <p class="dps-portal-card__date">Sábado, 15 de Fevereiro às 10:00</p>
    </div>
    <div class="dps-portal-card__actions">
        <button class="dps-btn-secondary dps-btn--sm">Reagendar</button>
        <button class="dps-btn-primary dps-btn--sm">Ver Detalhes</button>
    </div>
</div>
```

---

## 13. Manutenção

**Atualizar este documento quando:**
- Novo contexto de frontend for criado (ex.: app mobile, PWA)
- Paleta de cores mudar (sincronizar com `VISUAL_STYLE_GUIDE.md`)
- Nova biblioteca de animação/interação for adotada
- Novo padrão de componente for estabelecido

**Este documento complementa** o `VISUAL_STYLE_GUIDE.md` — nunca contradizê-lo. Em caso de conflito, o Style Guide prevalece para paleta e componentes; este documento prevalece para metodologia e decisões de design.

---

**Fim das Instruções de Design Frontend DPS v1.0**
