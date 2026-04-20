# Guia de Estilo Visual DPS — DPS Signature

**Versão:** 3.0
**Última atualização:** 17/04/2026
**Sistema visual ativo:** `DPS Signature`

---

## 1. Visão geral

Este guia converte a direção visual do DPS em decisões práticas de:

- cor;
- tipografia;
- espaçamento;
- forma;
- elevação;
- motion;
- componentes;
- exemplos de implementação.

Ele deve ser usado junto com `FRONTEND_DESIGN_INSTRUCTIONS.md`.

### 1.1 Resumo da linguagem

O sistema é:

- premium;
- contido;
- contemporâneo;
- predominantemente reto;
- claro e contrastado;
- editorial;
- pouco dependente de ornamento.

### 1.2 Regras rápidas

- duas famílias tipográficas no máximo;
- uma cor fria de acento;
- verde apenas para ação;
- cantos retos por padrão;
- borda fina antes de sombra;
- composições com ritmo, não com excesso de dispositivos.

---

## 2. Tokens de cor

### 2.1 Paleta base

```css
:root {
  --dps-color-ink: #11161c;
  --dps-color-petrol: #173042;
  --dps-color-petrol-deep: #0d141b;
  --dps-color-paper: #fff9f1;
  --dps-color-bone: #f3ece1;
  --dps-color-line: #d9cdbd;
  --dps-color-line-strong: #b9ab98;
  --dps-color-accent: #8fd6f4;
  --dps-color-accent-soft: #e8f6fc;
  --dps-color-action: #1f8c57;
  --dps-color-action-deep: #166540;
  --dps-color-danger: #a6463c;
  --dps-color-warning: #8f6a24;
}
```

### 2.2 Papéis semânticos

| Token | Papel |
|---|---|
| `--dps-color-ink` | texto principal, títulos, ícones escuros |
| `--dps-color-petrol` | links fortes, fundos institucionais, divisores escuros |
| `--dps-color-petrol-deep` | hero e áreas de contraste máximo |
| `--dps-color-paper` | fundo principal |
| `--dps-color-bone` | superfícies secundárias |
| `--dps-color-line` | bordas e linhas estruturais |
| `--dps-color-accent` | foco, acento frio, top lines, detalhes de destaque |
| `--dps-color-accent-soft` | superfícies suaves de ênfase |
| `--dps-color-action` | CTA real |
| `--dps-color-danger` | erro, destrutivo |
| `--dps-color-warning` | pendência, atenção |

### 2.3 Distribuição recomendada

Em uma tela típica:

- 70% a 80% neutros (`Paper`, `Bone`, `Ink`, `Line`);
- 10% a 15% `Petrol`;
- 5% a 8% `Accent`;
- até 5% `Action`.

### 2.4 Regras de uso

- `Accent` não substitui o CTA;
- `Action` não deve virar cor de decoração;
- `Danger` e `Warning` só devem aparecer quando o estado exigir;
- o fundo principal nunca deve depender de branco puro frio;
- a UI não deve herdar automaticamente as cores da logo.

---

## 3. Tipografia

### 3.1 Famílias

```css
:root {
  --dps-font-display: "Sora", "Segoe UI", sans-serif;
  --dps-font-body: "Manrope", "Segoe UI", sans-serif;
}
```

### 3.2 Pesos

```css
:root {
  --dps-font-weight-regular: 400;
  --dps-font-weight-medium: 500;
  --dps-font-weight-semibold: 600;
  --dps-font-weight-bold: 700;
}
```

### 3.3 Escala

```css
:root {
  --dps-text-display-xl: clamp(3.5rem, 7vw, 5.5rem);
  --dps-text-display-l: clamp(2.6rem, 5.6vw, 5rem);
  --dps-text-heading-l: clamp(2rem, 4vw, 2.8rem);
  --dps-text-heading-m: clamp(1.5rem, 2.8vw, 2rem);
  --dps-text-heading-s: 1.25rem;
  --dps-text-body-l: 1.125rem;
  --dps-text-body-m: 1rem;
  --dps-text-body-s: 0.9375rem;
  --dps-text-label: 0.75rem;
}
```

### 3.4 Aplicação

| Papel | Fonte | Peso | Uso |
|---|---|---|---|
| Display | `Sora` | `600–700` | H1 e headlines dominantes |
| Heading | `Sora` | `600` | títulos de seção |
| Subheading | `Sora` | `500–600` | títulos de card/bloco |
| Body | `Manrope` | `400` | parágrafos |
| UI | `Manrope` | `500–600` | botões, labels, meta |

### 3.5 Regras

- evite caps lock fora de kicker/meta;
- não use mais de um display pesado na mesma dobra;
- o corpo deve respirar;
- o subtítulo nunca deve ficar mais chamativo que o título;
- o texto auxiliar deve continuar legível, não lavado.

---

## 4. Espaçamento

### 4.1 Escala

```css
:root {
  --dps-space-4: 4px;
  --dps-space-8: 8px;
  --dps-space-12: 12px;
  --dps-space-16: 16px;
  --dps-space-20: 20px;
  --dps-space-24: 24px;
  --dps-space-32: 32px;
  --dps-space-40: 40px;
  --dps-space-48: 48px;
  --dps-space-64: 64px;
  --dps-space-80: 80px;
  --dps-space-96: 96px;
}
```

### 4.2 Uso

| Espaço | Uso |
|---|---|
| `4–8px` | micro-ajustes, ícone + texto |
| `12–16px` | elementos relacionados |
| `20–24px` | bloco interno padrão |
| `32–40px` | separação de grupos |
| `48–64px` | respiro entre seções |
| `80–96px` | grandes aberturas e encerramentos |

### 4.3 Regras

- use a mesma distância para relações equivalentes;
- aumente o espaço antes de títulos importantes;
- reduza espaço em contexto operacional denso;
- não compense má hierarquia com espaço demais.

---

## 5. Forma, borda e elevação

### 5.1 Shape

```css
:root {
  --dps-radius-none: 0px;
  --dps-radius-soft: 2px;
  --dps-radius-small: 4px;
}
```

### 5.2 Regra de shape

- padrão: `0px`;
- exceção discreta: `2px`;
- rara: `4px`;
- acima disso, somente se o contexto realmente justificar.

### 5.3 Bordas

```css
:root {
  --dps-border-default: 1px solid rgba(217, 205, 189, 0.78);
  --dps-border-strong: 1px solid rgba(185, 171, 152, 0.84);
  --dps-border-accent: 1px solid rgba(143, 214, 244, 0.58);
}
```

### 5.4 Sombras

```css
:root {
  --dps-shadow-soft: 0 20px 52px rgba(17, 22, 28, 0.06);
  --dps-shadow-strong: 0 30px 72px rgba(8, 12, 18, 0.2);
}
```

### 5.5 Princípios

- borda antes de sombra;
- sombra apenas quando houver elevação real;
- cards claros não devem parecer flutuando sem motivo;
- hero pode concentrar o maior peso de sombra.

---

## 6. Motion

### 6.1 Tokens

```css
:root {
  --dps-motion-fast: 180ms ease;
  --dps-motion-medium: 260ms ease;
  --dps-motion-emphasis: 260ms cubic-bezier(0.22, 1, 0.36, 1);
}
```

### 6.2 Padrão

- hover: leve;
- transição de layout: curta;
- entrada: sutil;
- foco: claro e rápido.

### 6.3 Evitar

- bounce;
- elasticidade;
- glow animado;
- scroll gimmicks sem valor de leitura.

---

## 7. Componentes-base

## 7.1 Shell de página

```css
.dps-page {
  width: min(100%, 1240px);
  margin: 0 auto;
  padding: clamp(16px, 2.8vw, 32px);
  color: var(--dps-color-ink);
  background: linear-gradient(180deg, rgba(255, 249, 241, 0.98), rgba(243, 236, 225, 1));
  font-family: var(--dps-font-body);
}
```

## 7.2 Hero

```css
.dps-hero {
  padding: clamp(28px, 4.4vw, 46px);
  border: var(--dps-border-default);
  background: linear-gradient(145deg, #0f141a 0%, #111b22 44%, #173042 100%);
  color: #fff;
  box-shadow: var(--dps-shadow-strong);
}
```

## 7.3 Seção

```css
.dps-section {
  padding: clamp(24px, 3.2vw, 34px);
  border: var(--dps-border-default);
  background: linear-gradient(180deg, rgba(255, 249, 241, 0.98), rgba(243, 236, 225, 0.92));
  box-shadow: var(--dps-shadow-soft);
}
```

## 7.4 Kicker

```css
.dps-kicker {
  margin: 0 0 14px;
  color: var(--dps-color-petrol);
  font-family: var(--dps-font-body);
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.18em;
  text-transform: uppercase;
}
```

## 7.5 Botão principal

```css
.dps-button--primary {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 54px;
  padding: 0 22px;
  border: 1px solid var(--dps-color-action);
  border-radius: 0;
  background: var(--dps-color-action);
  color: #fff;
  font-family: var(--dps-font-body);
  font-weight: 600;
  transition: transform var(--dps-motion-fast), background var(--dps-motion-fast), box-shadow var(--dps-motion-fast);
}

.dps-button--primary:hover {
  transform: translateY(-1px);
  background: var(--dps-color-action-deep);
}
```

## 7.6 Botão secundário

```css
.dps-button--secondary {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 54px;
  padding: 0 22px;
  border: var(--dps-border-default);
  border-radius: 0;
  background: transparent;
  color: var(--dps-color-ink);
  font-weight: 600;
}
```

## 7.7 Link auxiliar

```css
.dps-link-inline {
  color: var(--dps-color-petrol);
  font-weight: 600;
  text-decoration: none;
}

.dps-link-inline::after {
  content: " →";
}
```

## 7.8 Bloco clicável de jornada

```css
.dps-link-panel {
  display: grid;
  gap: 10px;
  padding: 24px 20px;
  border: var(--dps-border-default);
  background: rgba(255, 249, 241, 0.94);
  transition: transform var(--dps-motion-fast), border-color var(--dps-motion-fast), background var(--dps-motion-fast);
}

.dps-link-panel:hover {
  transform: translateY(-1px);
  border-color: rgba(23, 48, 66, 0.2);
}
```

## 7.9 FAQ item

```css
.dps-faq-item {
  padding: 22px 20px;
  border: var(--dps-border-default);
  background: rgba(255, 249, 241, 0.96);
}

.dps-faq-item h3 {
  margin: 0 0 10px;
  font-family: var(--dps-font-display);
  font-size: 1.3rem;
}
```

## 7.10 Bloco legal

```css
.dps-legal-block {
  padding: 28px 24px;
  border: var(--dps-border-default);
  background: rgba(255, 252, 247, 0.98);
}

.dps-legal-block h2,
.dps-legal-block h3 {
  font-family: var(--dps-font-display);
}
```

## 7.11 Painel de contato

```css
.dps-contact-card {
  display: grid;
  gap: 10px;
  padding: 22px 20px;
  border: var(--dps-border-default);
  background: rgba(248, 243, 236, 0.96);
}
```

---

## 8. Layout recipes

### 8.1 Hero de duas colunas

Use quando houver:

- promessa principal;
- CTA;
- apoio operacional.

Estrutura:

- coluna maior para mensagem;
- coluna menor para painéis de apoio.

### 8.2 Seção editorial

Use quando quiser:

- abrir com título forte;
- seguir com 1 ou 2 parágrafos;
- destacar uma frase-chave;
- fechar com bloco de apoio lateral.

### 8.3 Grade assimétrica

Use quando houver um item líder e vários itens de apoio.

Boa para:

- jornada;
- diferenciais;
- FAQ destacada;
- caminhos rápidos.

### 8.4 Painel operacional

Use para:

- horários;
- localização;
- contato;
- status;
- resumo funcional.

Características:

- leitura direta;
- menos “copy de marca”;
- mais hierarquia e label.

---

## 9. Forms e tabelas

### 9.1 Inputs

```css
.dps-input {
  min-height: 52px;
  padding: 14px 16px;
  border: var(--dps-border-default);
  border-radius: 0;
  background: rgba(255, 252, 247, 0.98);
  color: var(--dps-color-ink);
  font-family: var(--dps-font-body);
}

.dps-input:focus-visible {
  outline: 2px solid rgba(143, 214, 244, 0.94);
  outline-offset: 3px;
}
```

Regras:

- label sempre visível;
- descrição curta abaixo quando necessário;
- erro abaixo do campo, nunca escondido;
- não usar placeholder como instrução principal.

### 9.2 Tabelas

```css
.dps-table {
  width: 100%;
  border-collapse: collapse;
}

.dps-table th,
.dps-table td {
  padding: 14px 16px;
  border-bottom: 1px solid rgba(217, 205, 189, 0.78);
}

.dps-table th {
  color: var(--dps-color-petrol);
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}
```

---

## 10. Do / Don’t

### 10.1 Faça

- use `Sora` para título e `Manrope` para texto;
- construa com poucas cores;
- privilegie borda fina e superfície clara;
- destaque só o que realmente precisa;
- mantenha as ações fortes fáceis de encontrar;
- deixe a marca visível no primeiro bloco.

### 10.2 Não faça

- arredonde tudo;
- pinte tudo;
- faça grids de cards sem hierarquia;
- transforme cada frase em badge;
- use gradiente para compensar falta de composição;
- use uma cor nova para cada tipo de bloco;
- crie hero sem contraste ou sem CTA forte.

---

## 11. Exemplo completo

```html
<section class="dps-section">
  <p class="dps-kicker">Continue explorando</p>
  <h2>Entenda a marca e consulte os combinados do atendimento.</h2>

  <div class="dps-grid dps-grid--3">
    <a class="dps-link-panel" href="/quem-somos/">
      <strong>Quem somos</strong>
      <span>Conheça melhor a marca e o jeito de cuidar que orienta cada visita.</span>
    </a>

    <a class="dps-link-panel" href="/nossos-diferenciais/">
      <strong>Nossos diferenciais</strong>
      <span>Entenda o que faz a Desi ser escolhida de novo na prática.</span>
    </a>

    <a class="dps-link-panel" href="/regras-e-termos-de-atendimento/">
      <strong>Regras e termos de atendimento</strong>
      <span>Consulte os combinados que ajudam a manter a rotina clara e segura.</span>
    </a>
  </div>
</section>
```

Esse padrão funciona porque:

- mantém hierarquia clara;
- usa o mesmo shape e a mesma superfície;
- não transforma navegação em banner promocional;
- permite scanning rápido.

---

## 12. Manutenção

Atualize este guia quando houver:

- mudança real na tipografia oficial;
- ajuste estrutural na paleta;
- nova regra global de shape;
- nova receita de componente recorrente;
- alteração intencional de direção visual.

Não atualize este guia para registrar exceções locais. Exceção local deve ficar documentada no contexto da feature.

---

**Fim do Guia de Estilo Visual DPS Signature v3.0**
