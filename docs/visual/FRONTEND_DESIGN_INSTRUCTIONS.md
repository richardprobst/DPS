# Instruções de Design Frontend — DPS Signature

**Versão:** 3.0
**Última atualização:** 17/04/2026
**Sistema visual ativo:** `DPS Signature`
**Complementa:** `VISUAL_STYLE_GUIDE.md`

---

## 1. Propósito

Este documento define a fonte de verdade para qualquer trabalho de frontend, layout, UI e UX no ecossistema DPS.

Ele substitui o uso de referências genéricas de framework como direção visual principal. O DPS agora adota um sistema próprio, com identidade deliberadamente:

- premium;
- moderna;
- minimalista;
- sóbria;
- reta;
- editorial;
- orientada por contraste, ritmo e hierarquia.

Consulte este documento sempre que criar ou alterar:

- páginas públicas;
- portal do cliente;
- telas administrativas;
- formulários;
- componentes reutilizáveis;
- demos visuais;
- previews HTML;
- CSS compartilhado;
- documentação visual de novas features.

---

## 2. O que é o DPS Signature

`DPS Signature` é um sistema visual proprietário do DPS. Ele não tenta reproduzir Material, Bootstrap, shadcn, Ant ou qualquer outra linguagem pronta. A referência vem da identidade construída no próprio projeto.

### 2.1 Tese visual

O sistema comunica:

- cuidado sem excesso;
- sofisticação sem ostentação;
- clareza sem frieza;
- proximidade sem infantilização.

### 2.2 Como isso aparece na interface

- base cromática clara e quente, com contraste alto;
- massas escuras bem controladas em heros e áreas de destaque;
- uma única cor de acento fria para orientação e foco;
- verde reservado para ação real, especialmente WhatsApp;
- cantos predominantemente retos;
- bordas finas e honestas, em vez de relevos decorativos;
- tipografia forte na hierarquia e contida no corpo;
- pouca ornamentação;
- espaçamento rigoroso;
- blocos com função clara;
- composições mais próximas de página editorial do que de mosaico de cards.

### 2.3 Regra central

Quando houver dúvida entre adicionar e remover, remova.

---

## 3. Princípios do sistema

### 3.1 Contenção premium

O DPS não usa excesso de cor, sombra, ícone, badge ou microefeito para parecer valioso. O valor percebido vem de:

- proporção;
- contraste;
- silêncio visual;
- alinhamento;
- texto enxuto;
- consistência.

### 3.2 Geometria reta

A geometria padrão do sistema é reta. Arredondamento não é proibido, mas é exceção funcional, não linguagem dominante.

### 3.3 Cor com função

Cor serve para orientar:

- ação;
- prioridade;
- estado;
- foco;
- leitura de hierarquia.

Cor não deve ser usada como decoração solta.

### 3.4 Hierarquia editorial

Cada seção precisa dizer, à primeira leitura:

- o que é;
- por que importa;
- o que fazer em seguida.

### 3.5 Ritmo acima de ornamento

Um layout bonito no DPS depende mais de:

- largura da coluna;
- relação entre blocos;
- respiro vertical;
- cadência entre títulos e conteúdo;

do que de gradiente, sombra ou shape.

### 3.6 Marca antes do componente

O sistema não deve parecer uma biblioteca de componentes. Deve parecer uma marca com interfaces coerentes.

---

## 4. Perfis de aplicação

O sistema é único, mas sua intensidade muda conforme o contexto.

| Contexto | Perfil | Característica dominante | Densidade | Ênfase visual |
|---|---|---|---|---|
| Páginas públicas | `Brand` | presença editorial e confiança | média | alta |
| Portal do cliente | `Operational` | clareza, leitura rápida e orientação | média-alta | média |
| Formulários e fluxos críticos | `Transactional` | foco, legibilidade e baixa distração | alta | baixa-média |
| Admin interno | `Administrative` | controle, eficiência e estabilidade | alta | baixa |

### 4.1 Perfil Brand

Usar quando o objetivo for:

- apresentar a marca;
- converter;
- explicar serviços;
- construir confiança;
- orientar a jornada comercial.

Preferências:

- hero forte;
- composição mais aberta;
- massas escuras e superfícies claras;
- títulos curtos;
- CTA evidente;
- links auxiliares bem organizados.

### 4.2 Perfil Operational

Usar quando o usuário precisa:

- consultar;
- decidir;
- navegar;
- atualizar informações;
- entender status.

Preferências:

- layout mais funcional;
- títulos menos publicitários;
- texto objetivo;
- menos hero e menos “clima”;
- mais estrutura e scanning.

### 4.3 Perfil Transactional

Usar quando o usuário precisa completar uma tarefa:

- cadastro;
- login;
- agendamento;
- aceite;
- pagamento;
- confirmação.

Preferências:

- distração mínima;
- passos claros;
- uma ação principal por tela;
- menos variação cromática;
- textos curtos e instrutivos.

### 4.4 Perfil Administrative

Usar no WordPress/admin e em interfaces densas de operação.

Preferências:

- menos impacto visual;
- mais legibilidade;
- consistência estrutural;
- controle de densidade;
- feedbacks claros;
- interfaces sólidas e previsíveis.

---

## 5. Processo obrigatório antes de desenhar

Antes de implementar, responda:

### 5.1 Contexto

- Quem usa essa tela?
- O que essa pessoa precisa resolver?
- A decisão é emocional, operacional ou transacional?
- O uso principal é desktop, mobile ou ambos?
- O que precisa ficar óbvio em 3 segundos?

### 5.2 Visual thesis

Escreva uma frase com:

- humor;
- materialidade;
- energia.

Exemplo:

> “Clareza premium, com massa escura controlada, superfícies claras quentes e contraste seco.”

### 5.3 Content plan

Defina a ordem do conteúdo:

1. hero ou entrada;
2. bloco de orientação;
3. profundidade;
4. prova/confiança;
5. fechamento/ação.

### 5.4 Interaction thesis

Defina no máximo 2 ou 3 comportamentos de movimento:

- entrada do hero;
- hover de links/CTA;
- transição de painéis ou navegação.

Se não houver motivo claro, não anime.

---

## 6. Regras de composição

### 6.1 Um bloco, uma função

Cada seção precisa ter apenas uma função principal:

- explicar;
- provar;
- aprofundar;
- converter;
- orientar.

Se uma seção faz duas coisas mal resolvidas, ela precisa ser dividida.

### 6.2 Hero

O hero precisa:

- deixar a marca clara;
- expor a proposta principal;
- sustentar um CTA inequívoco;
- evitar excesso de elementos paralelos.

#### Hero: obrigatório

- marca visível;
- título curto e dominante;
- uma linha de apoio;
- CTA principal;
- apoio operacional quando necessário.

#### Hero: evitar

- 4 ou 5 botões grandes competindo;
- cards aleatórios flutuando;
- prova social genérica;
- excesso de pills;
- slogan e subtítulo dizendo a mesma coisa.

### 6.3 Seções

A sequência preferencial para páginas públicas é:

1. impacto;
2. orientação;
3. serviços ou proposta;
4. confiança;
5. dúvidas;
6. ação final.

### 6.4 Grids

Use grid para organizar, não para “encher”.

Preferências:

- 2 colunas quando há contraste entre conteúdo e apoio;
- 3 colunas para fatos curtos;
- 1 coluna quando o texto exige ritmo;
- destaque assimétrico para um item principal quando fizer sentido.

### 6.5 Cardless by default

No DPS, não se deve empilhar cards por hábito.

Use card apenas quando houver necessidade real de:

- isolar um conteúdo;
- reforçar clicabilidade;
- separar uma unidade de leitura;
- estruturar um bloco legal, FAQ, contato ou formulário.

### 6.6 Espaçamento

O sistema depende de respiro preciso.

Regras:

- evitar blocos “colados”;
- evitar vazios excessivos sem função;
- manter consistência entre seções equivalentes;
- aumentar respiro antes de títulos importantes;
- reduzir gap em áreas de leitura utilitária.

---

## 7. Tipografia

### 7.1 Famílias oficiais

Use no máximo duas famílias:

- `Sora` para display e títulos;
- `Manrope` para texto, UI, labels e apoio.

Fallback:

```css
font-family: "Sora", "Segoe UI", sans-serif;
font-family: "Manrope", "Segoe UI", sans-serif;
```

### 7.2 Papel de cada fonte

`Sora`:

- títulos;
- hero headings;
- títulos de seção;
- chamadas curtas.

`Manrope`:

- parágrafos;
- labels;
- botões;
- FAQs;
- painéis operacionais;
- tabelas;
- conteúdo legal.

### 7.3 Pesos recomendados

- `400` para corpo;
- `500` para UI e subtítulos;
- `600` para títulos médios;
- `700` apenas em headlines realmente dominantes.

Evite bold pesado em excesso. O sistema precisa parecer firme, não agressivo.

### 7.4 Escala recomendada

| Papel | Uso | Faixa sugerida |
|---|---|---|
| Display XL | hero principal | `56–88px` |
| Display L | heros internos | `40–64px` |
| Heading L | título de seção | `28–40px` |
| Heading M | subtítulo forte | `22–28px` |
| Body L | subtítulo/apoio | `18–20px` |
| Body M | texto padrão | `16–18px` |
| Body S | apoio e detalhe | `14–15px` |
| Label | botões, kicker, meta | `11–14px` |

### 7.5 Regras tipográficas

- H1 único por página;
- kicker sempre curto e em caixa alta;
- evitar linhas muito longas;
- preferir blocos de texto de 45 a 75 caracteres por linha;
- usar `text-wrap: balance` em títulos quando possível;
- evitar repetição de palavras entre kicker, título e subtítulo.

---

## 8. Cor

### 8.1 Base cromática oficial

| Papel | Valor | Uso |
|---|---|---|
| Ink | `#11161C` | texto principal, títulos, massa escura |
| Petrol | `#173042` | fundo institucional, estrutura, links fortes |
| Petrol Deep | `#0D141B` | hero, contraste máximo |
| Paper | `#FFF9F1` | fundo principal |
| Bone | `#F3ECE1` | superfícies secundárias |
| Line | `#D9CDBD` | bordas e divisores |
| Sky Accent | `#8FD6F4` | acento frio e foco |
| Sky Accent Soft | `#E8F6FC` | fundo de destaque suave |
| Action Green | `#1F8C57` | ação real, especialmente WhatsApp |

### 8.2 Estratégia de uso

- `Ink`, `Petrol`, `Paper` e `Bone` sustentam quase toda a interface;
- `Sky Accent` entra pouco e com intenção;
- `Action Green` fica reservado para ação;
- as cores da logo não devem contaminar toda a UI.

### 8.3 Regra de prioridade cromática

1. neutros estruturam;
2. azul orienta;
3. verde converte.

### 8.4 O que evitar

- amarelo e vermelho da logo espalhados na interface;
- múltiplas cores vibrantes competindo;
- fundo branco frio puro como padrão principal;
- gradientes saturados;
- roxo genérico de UI pronta.

---

## 9. Formas, bordas e superfícies

### 9.1 Forma padrão

O padrão é reto.

Use:

- `0px` como shape dominante;
- `2px` ou `4px` apenas em casos funcionais e discretos;
- cantos suaves apenas se houver motivo claro de ergonomia, nunca como linguagem dominante.

### 9.2 Borda

O DPS usa borda para estruturar leitura.

Preferências:

- `1px solid` com tons de `Line`;
- divisores finos;
- top border ou left border apenas quando fizer sentido semântico.

### 9.3 Superfícies

Superfícies devem parecer materiais leves e claros, não caixas pesadas.

Use:

- fundo principal em `Paper`;
- containers em `Paper` ou `Bone`;
- contraste escuro apenas em áreas de ênfase;
- sombra suave e rara.

### 9.4 Sombras

Sombra existe, mas não deve ser a linguagem principal.

Use sombra apenas para:

- hero;
- elevação real;
- overlay;
- CTA muito importante;
- componentes sobre fundo muito plano.

---

## 10. Motion

### 10.1 Filosofia

O sistema usa motion discreto e seco.

Não usar:

- bounce;
- overshoot;
- parallax ornamental;
- animar tudo na entrada;
- hover chamando mais atenção que o conteúdo.

### 10.2 Motion permitido

- fade/translate curto na entrada;
- hover com leve deslocamento ou mudança de borda;
- destaque de foco;
- transição simples de expansão/colapso.

### 10.3 Duração

- rápida: `160–180ms`;
- média: `220–260ms`;
- rara: até `320ms`.

### 10.4 Easing

Preferir:

```css
ease;
cubic-bezier(0.22, 1, 0.36, 1);
```

### 10.5 Reduced motion

Toda animação precisa respeitar `prefers-reduced-motion`.

---

## 11. Estratégia de componentes

### 11.1 CTA principal

Precisa:

- ser imediatamente visível;
- usar contraste claro;
- não competir com 3 outras ações equivalentes;
- ter área de clique confortável;
- usar verde apenas quando a ação for realmente de contato/agendamento.

### 11.2 CTA secundário

Preferir:

- link forte;
- botão fantasma;
- outlined seco;
- menos peso que o CTA principal.

### 11.3 Links de jornada

Blocos de caminho precisam parecer:

- organizados;
- legíveis;
- consistentes;
- parte da narrativa.

Evite que pareçam uma grade aleatória de promoções.

### 11.4 FAQ

FAQ no DPS deve:

- ser rapidamente escaneável;
- separar bem pergunta de resposta;
- manter altura coerente;
- evitar grandes blocos de texto.

### 11.5 Blocos legais

Páginas legais devem ter:

- ritmo mais calmo;
- largura de leitura controlada;
- menos ornamento;
- destaque claro para títulos e subtítulos;
- navegação complementar no final.

### 11.6 Formulários

Formulários devem priorizar:

- clareza;
- ordem;
- feedback;
- espaçamento;
- labels permanentes.

Evitar:

- placeholder como única label;
- bordas exageradas;
- campos com muitos estilos concorrentes.

### 11.7 Tabelas e dados

Em interfaces operacionais:

- use divisores claros;
- preserve hierarquia tipográfica;
- reduza ruído decorativo;
- trate densidade como parte do UX.

---

## 12. Tom de voz e conteúdo

O DPS adota a combinação:

- `Afeto Organizado`;
- `Premium Sereno`.

Isso significa:

- linguagem humana;
- frase curta;
- promessa contida;
- segurança sem rigidez;
- proximidade sem excesso de entusiasmo.

### 12.1 O texto deve soar

- claro;
- calmo;
- criterioso;
- confiável.

### 12.2 O texto não deve soar

- infantil;
- genérico;
- publicitário demais;
- corporativo frio;
- autoexplicação de layout.

### 12.3 Remover sempre

- texto de wireframe;
- texto que descreve o próprio componente;
- repetição entre seções;
- frases longas sem ganho real.

---

## 13. Responsividade

### 13.1 Breakpoints obrigatórios

Validar sempre:

- `375px`
- `600px`
- `840px`
- `1200px`
- `1920px`

### 13.2 O que validar

- overflow horizontal;
- corte de conteúdo;
- peso visual do hero;
- ordem dos blocos;
- densidade de grid;
- tamanho de CTA;
- legibilidade de títulos;
- equilíbrio entre texto e superfície;
- targets de toque.

### 13.3 Estratégia mobile

No mobile:

- reduzir competição visual;
- empilhar conteúdos por prioridade;
- manter CTA visível;
- simplificar grids;
- não forçar colunas pequenas demais.

---

## 14. Acessibilidade

Obrigatório:

- contraste AA;
- `focus-visible` perceptível;
- ordem semântica correta;
- H1 único;
- áreas de toque adequadas;
- motion opcional para quem reduz animação;
- texto nunca dependente só de cor para ser entendido.

Boas práticas:

- links sublinhados em textos corridos;
- labels permanentes;
- `aria-label` quando a ação não for explícita;
- estados visuais consistentes entre mouse, teclado e toque.

---

## 15. Performance e implementação

- carregar poucas fontes e poucos pesos;
- evitar dependências visuais desnecessárias;
- preferir CSS simples e robusto;
- limitar blur e sombras pesadas;
- evitar bibliotecas visuais inteiras para resolver pouco;
- implementar tokens e padrões reutilizáveis.

---

## 16. Anti-padrões

Nunca fazer:

- voltar a um visual claramente herdado do sistema anterior como padrão principal;
- encher a tela de cards equivalentes;
- usar botões pill como linguagem dominante;
- arredondar tudo;
- usar 3 ou 4 acentos fortes ao mesmo tempo;
- repetir a mesma mensagem em hero, subtítulo e FAQ;
- fazer hero fraco com texto genérico e sem hierarquia;
- transformar toda seção em painel com borda grossa;
- exagerar em blur, glow e transparência;
- usar microbadges por todo lado para compensar falta de estrutura;
- usar gradiente para simular sofisticação;
- criar UI “fofa” quando o objetivo for confiança.

---

## 17. Checklist de revisão

Antes de finalizar qualquer trabalho visual, confirme:

- a marca está clara no primeiro screen;
- existe uma ação principal inequívoca;
- cada seção tem uma função só;
- o layout está mais próximo de composição do que de biblioteca de componentes;
- a cor de acento aparece com moderação;
- o verde está reservado para ação;
- os cantos continuam predominantemente retos;
- o espaçamento está coerente;
- os títulos escaneiam bem;
- o mobile continua elegante e não apenas “quebrando menos”.

---

## 18. Exemplos práticos

### 18.1 Hero correto

```html
<section class="dps-hero">
  <div class="dps-hero__main">
    <p class="dps-kicker">Banho e tosa com calma e critério</p>
    <h1>Seu pet bem cuidado, do primeiro contato à entrega.</h1>
    <p>Agenda organizada, conversa clara e atenção real ao ritmo de cada pet.</p>
    <a class="dps-button dps-button--primary" href="#">Agendar pelo WhatsApp</a>
  </div>
  <aside class="dps-hero__aside">
    <h2>Tudo mais simples para você</h2>
    <p>Horários, contato e localização visíveis logo de cara.</p>
  </aside>
</section>
```

Por que funciona:

- uma tese clara;
- uma ação principal;
- apoio operacional separado;
- contraste forte;
- sem excesso de elementos concorrentes.

### 18.2 Grade de jornada correta

```html
<section class="dps-journey">
  <header>
    <p class="dps-kicker">Se quiser decidir com mais segurança</p>
    <h2>Estes caminhos ajudam a chegar no WhatsApp com mais clareza.</h2>
  </header>

  <div class="dps-journey__grid">
    <a href="#">Nossos diferenciais</a>
    <a href="#">Primeira visita</a>
    <a href="#">Perguntas frequentes</a>
    <a href="#">TaxiDog</a>
    <a href="#">Contato e localização</a>
  </div>
</section>
```

Por que funciona:

- blocos com função clara;
- leitura linear;
- jornada guiada;
- destaque principal e apoio secundário.

### 18.3 O que evitar

Não fazer algo como:

- quatro botões grandes lado a lado;
- cinco badges sem hierarquia;
- hero com estatísticas, carrossel, FAQ e CTA no primeiro screen;
- cards arredondados e coloridos sem função.

---

## 19. Regra final

Se a interface parecer pronta demais, genérica demais ou “bonita demais” para a função que resolve, ela ainda não está no padrão DPS Signature.

O objetivo não é impressionar pelo excesso. É transmitir critério.

---

**Fim das Instruções de Design Frontend DPS Signature v3.0**
