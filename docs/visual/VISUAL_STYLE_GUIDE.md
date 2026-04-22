# Guia de Estilo Visual â€” DPS Signature

**VersÃ£o:** 3.0
**Ãšltima atualizaÃ§Ã£o:** 21/04/2026
**Status:** guia visual normativo do sistema

---

## 1. Fonte de verdade

Este guia define a implementaÃ§Ã£o visual do **DPS Signature**.

Ele substitui qualquer referÃªncia visual anterior do projeto.

Quando houver divergÃªncia entre documentaÃ§Ã£o antiga e este guia, use este guia.

---

## 2. DNA visual do DPS Signature

O sistema deve parecer:

- premium,
- preciso,
- contemporÃ¢neo,
- sÃ³brio,
- claro,
- proprietÃ¡rio.

O sistema nÃ£o deve parecer:

- arredondado por padrÃ£o,
- infantil,
- fofo,
- genÃ©rico,
- derivado visualmente de bibliotecas prontas.

Assinatura formal do padrÃ£o:

- blocos retos,
- linhas finas,
- contraste limpo,
- tipografia moderna,
- uma paleta contida,
- composiÃ§Ã£o mais importante que ornamento.

---

## 3. Paleta canÃ´nica

### 3.1 NÃºcleo

| Token conceitual | Valor | Uso |
|------------------|-------|-----|
| `ink` | `#11161c` | tÃ­tulos, Ã­cones, texto principal |
| `petrol` | `#173042` | CTA principal, fundos densos, tabs ativas |
| `paper` | `#f7f2ea` | fundo principal claro |
| `bone` | `#ece2d3` | superfÃ­cies internas e Ã¡reas de apoio |
| `line` | `#d9ccba` | bordas, tabelas, divisÃ³rias |
| `sky` | `#bdd8e7` | realce frio discreto, leitura auxiliar |
| `action` | `#1f8c57` | aÃ§Ã£o positiva, pagamento, sucesso |
| `warning` | `#8a6622` | atenÃ§Ã£o, pendÃªncia |
| `danger` | `#a44439` | cancelamento, erro, bloqueio |

### 3.2 ProporÃ§Ã£o de uso

- `paper` e `bone` dominam a base;
- `ink` sustenta leitura;
- `petrol` lidera a identidade;
- `sky` aparece como contraponto leve;
- `action`, `warning` e `danger` sÃ£o reservados para estados.

### 3.3 Regras

- usar no mÃ¡ximo um acento dominante por viewport;
- nÃ£o competir `petrol` e `action` como cores principais na mesma dobra;
- evitar fundos escuros extensos sem necessidade;
- evitar vÃ¡rios blocos coloridos lado a lado.

---

## 4. Tipografia

### 4.1 Par principal

- **Display:** `Sora`
- **Body/UI:** `Manrope`

### 4.2 PapÃ©is

| Papel | Uso |
|------|-----|
| `display` | herÃ³is, tÃ­tulos editoriais, chamadas |
| `headline` | tÃ­tulos de pÃ¡gina e seÃ§Ã£o |
| `title` | cards, painÃ©is, blocos contextuais |
| `body` | parÃ¡grafos, descriÃ§Ãµes, observaÃ§Ãµes |
| `label` | botÃµes, tabs, status, tabelas |
| `mono` | horÃ¡rios, IDs, cÃ³digos, dados tabulares quando necessÃ¡rio |

### 4.3 Diretrizes

- display sÃ³ onde realmente precisa presenÃ§a;
- body deve ser sempre confortÃ¡vel para leitura;
- label deve ser conciso e firme;
- evitar bold excessivo;
- preferir contraste por escala e composiÃ§Ã£o.

### 4.4 Escala prÃ¡tica recomendada

| Elemento | Tamanho sugerido |
|----------|------------------|
| H1 | `40px` a `56px` em pÃ¡ginas pÃºblicas, `32px` a `40px` em superfÃ­cies operacionais |
| H2 | `24px` a `32px` |
| H3 | `18px` a `24px` |
| Body principal | `15px` a `18px` |
| Body operacional | `14px` a `16px` |
| Label | `11px` a `14px` |

---

## 5. Formas e bordas

### 5.1 Regra principal

O padrÃ£o geomÃ©trico do DPS Signature Ã© reto.

### 5.2 Escala de raio

| Uso | Valor |
|-----|-------|
| padrÃ£o | `0px` |
| controles pequenos | `2px` |
| exceÃ§Ã£o discreta | `4px` |

### 5.3 O que evitar

- botÃµes pill;
- cards com cantos muito suaves;
- mistura de `0px` com `16px+` na mesma superfÃ­cie;
- badges â€œfofosâ€.

### 5.4 Bordas

- borda padrÃ£o: `1px solid var(--line)` ou equivalente do tema ativo;
- borda deve estruturar o layout;
- nÃ£o usar borda grossa sem motivo semÃ¢ntico;
- nÃ£o usar sombra pesada para substituir borda.

---

## 6. ElevaÃ§Ã£o

### 6.1 DireÃ§Ã£o

No DPS Signature, profundidade Ã© sutil.

Prioridade:

1. contraste de fundo;
2. borda;
3. sombra leve;
4. sÃ³ entÃ£o efeitos extras.

### 6.2 Uso recomendado

| Componente | Tratamento |
|-----------|------------|
| pÃ¡gina | fundo limpo |
| painel comum | borda + superfÃ­cie clara |
| painel ativo | borda + leve contraste de fundo |
| modal | borda + sombra moderada |
| dropdown | borda + sombra curta |

### 6.3 Evitar

- sombras macias demais;
- glow colorido;
- mÃºltiplas camadas de sombra;
- â€œcartÃ£o flutuanteâ€ sem necessidade.

---

## 7. EspaÃ§amento

Escala base:

- `4`
- `8`
- `12`
- `16`
- `24`
- `32`
- `48`
- `64`

AplicaÃ§Ã£o:

- `4â€“8`: microajustes internos;
- `12â€“16`: respiro entre label e conteÃºdo;
- `24â€“32`: separaÃ§Ã£o entre blocos;
- `48â€“64`: transiÃ§Ã£o entre seÃ§Ãµes grandes.

Regra:

- nÃ£o usar nÃºmeros arbitrÃ¡rios quando a escala resolve;
- revisar sempre consistÃªncia entre padding, gap e margin.

---

## 8. Componentes

### 8.1 BotÃµes

#### PrimÃ¡rio

- fundo `petrol` ou `action`;
- texto claro;
- canto reto;
- altura consistente;
- Ã­cone opcional, nunca obrigatÃ³rio para compreensÃ£o.

#### SecundÃ¡rio

- fundo claro;
- borda definida;
- texto `ink` ou `petrol`.

#### BotÃ£o de WhatsApp

- deve ser imediatamente reconhecÃ­vel;
- pode usar verde especÃ­fico do canal;
- nÃ£o pode sumir em fundo claro;
- deve manter contraste forte em hover e focus.

### 8.2 Inputs e selects

- reta principal da UI;
- valor precisa caber;
- placeholder discreto;
- Ã­cone de seta simples;
- foco visÃ­vel.

### 8.3 Tabs

- aparÃªncia estrutural;
- label forte;
- opcionalmente subtÃ­tulo curto;
- ativa com contraste de fundo e/ou borda, nÃ£o sÃ³ cor do texto.

### 8.4 Tabelas

- header enxuto e legÃ­vel;
- conteÃºdo alinhado por topo quando a linha Ã© alta;
- colunas proporcionais ao peso real da informaÃ§Ã£o;
- aÃ§Ã£o nÃ£o pode colapsar;
- mobile deve empilhar com hierarquia nÃ­tida.

### 8.5 PainÃ©is operacionais

- bloco denso, mas respirado;
- tÃ­tulos curtos;
- status claros;
- sem excesso de badges;
- conteÃºdo interno em ritmo modular.

### 8.6 Status e badges

- usar como marcadores objetivos;
- evitar badge por decoraÃ§Ã£o;
- manter semÃ¢ntica estÃ¡vel entre telas.

### 8.7 Modais

- shell limpo;
- sem cabeÃ§alho ornamental;
- footer com aÃ§Ãµes bem agrupadas;
- altura compatÃ­vel com viewport;
- foco e teclado obrigatÃ³rios.

### 8.8 Blocos editoriais

- podem usar mais respiro e contraste tipogrÃ¡fico;
- ainda assim devem preservar a geometria reta e a sobriedade do sistema.

### 8.9 Notices, alerts e feedback

- usar uma semÃ¢ntica por box;
- tÃ­tulo curto, quando existir;
- texto direto, sem dramatizaÃ§Ã£o;
- borda, fundo e acento devem comunicar estado sem virar espetÃ¡culo;
- preferir estrutura de aviso Ã  esquerda do conteÃºdo, nÃ£o banners chamativos.

### 8.10 Estados vazios e caixas utilitÃ¡rias

- estados vazios devem orientar a prÃ³xima aÃ§Ã£o;
- caixas de endereÃ§o, horÃ¡rio, contato e resumo devem compartilhar proporÃ§Ã£o quando estiverem no mesmo conjunto;
- label, valor e aÃ§Ã£o precisam formar um eixo claro;
- Ã­cones sÃ£o opcionais e nunca substituem a leitura do conteÃºdo.

### 8.11 Checkbox e radio

- layout compacto e legÃ­vel;
- Ã¡rea clicÃ¡vel ampla;
- descriÃ§Ã£o curta sob a opÃ§Ã£o quando necessÃ¡ria;
- estado selecionado deve ser visÃ­vel por fundo, borda ou tipografia, nÃ£o apenas pelo controle nativo;
- evitar usar chips, pills ou cartÃµes â€œfofosâ€ como escolha binÃ¡ria.

### 8.12 Upload de arquivo

- Ã¡rea de drop/select clara;
- helper de formato e limite junto do controle quando necessÃ¡rio;
- preview e estado do arquivo abaixo da aÃ§Ã£o principal;
- aÃ§Ãµes de substituir e remover explÃ­citas;
- estado de erro visualmente distinto de sucesso e informativo.

### 8.13 Grupos de CTA e links auxiliares

- um CTA primÃ¡rio dominante por bloco;
- CTA secundÃ¡rio com contraste menor, mas ainda legÃ­vel;
- links auxiliares devem complementar a aÃ§Ã£o, nÃ£o competir com ela;
- botÃµes e links relacionados precisam compartilhar alinhamento e espaÃ§amento;
- evitar empilhar CTAs sem hierarquia ou distribuir aÃ§Ãµes iguais como grade solta.

### 8.14 Shells de formulÃ¡rio

- um shell principal por pÃ¡gina;
- tÃ­tulo forte, texto de apoio curto e notices antes dos campos;
- seÃ§Ãµes organizadas por `fieldset` ou blocos equivalentes;
- rodapÃ© com aÃ§Ãµes agrupadas;
- evitar trilhas laterais, mÃ©tricas decorativas ou â€œmanuaisâ€ embutidos.

### 8.15 Hero, quote block e faixas de destaque

- o hero deve ter uma ideia dominante;
- quote blocks funcionam como pausa editorial, nÃ£o como testimonial genÃ©rico automÃ¡tico;
- faixas de destaque servem para confianÃ§a, rotina, diferenciais ou informaÃ§Ã£o operacional;
- evitar hero com dashboard fake, mosaico de cards ou excesso de chips;
- a primeira dobra precisa comunicar marca, proposta e aÃ§Ã£o em um sÃ³ gesto.

---

## 9. SuperfÃ­cies por contexto

### 9.1 Admin e operaÃ§Ã£o

- mais estrutura, menos atmosfera;
- densidade controlada;
- prioridade para tabelas, filtros, status e aÃ§Ãµes.

### 9.2 Portal

- mais acolhimento;
- linguagem visual limpa;
- sem visual promocional exagerado.

### 9.3 PÃºblico e comercial

- mais direÃ§Ã£o de arte;
- tipografia com mais voz;
- composiÃ§Ã£o forte e exclusiva.

### 9.4 Editorial

- foco em leitura;
- ritmo visual;
- destaques contextuais;
- SEO sem ruÃ­do visual.

---

## 10. Receitas de pÃ¡gina

### 10.1 Home e pÃ¡ginas comerciais principais

SequÃªncia preferencial:

1. hero;
2. prova ou diferenciais;
3. aprofundamento do mÃ©todo ou da rotina;
4. bloco utilitÃ¡rio com endereÃ§o, horÃ¡rios e contato;
5. fechamento com CTA.

### 10.2 Quem somos e pÃ¡ginas institucionais

SequÃªncia preferencial:

1. posicionamento;
2. sÃ­ntese editorial ou citaÃ§Ã£o;
3. princÃ­pios, rotina ou diferenciais;
4. fechamento de confianÃ§a.

### 10.3 PÃ¡ginas de serviÃ§o

SequÃªncia preferencial:

1. promessa do serviÃ§o;
2. escopo do atendimento;
3. como funciona;
4. critÃ©rios, dÃºvidas e consulta operacional.

### 10.4 PÃ¡ginas legais

SequÃªncia preferencial:

1. tÃ­tulo;
2. vigÃªncia/contexto;
3. seÃ§Ãµes jurÃ­dicas;
4. nota de atualizaÃ§Ã£o.

### 10.5 MatÃ©rias e conteÃºdo editorial

SequÃªncia preferencial:

1. tÃ­tulo SEO;
2. subtÃ­tulo;
3. ativo de abertura;
4. corpo seccionado;
5. bloco de sÃ­ntese ou alerta;
6. CTA final discreto.

### 10.6 Portal, autenticaÃ§Ã£o e formulÃ¡rios

SequÃªncia preferencial:

1. shell Ãºnico;
2. contexto breve;
3. notices;
4. seÃ§Ãµes do formulÃ¡rio;
5. aÃ§Ãµes finais.

### 10.7 OperaÃ§Ã£o e administraÃ§Ã£o

SequÃªncia preferencial:

1. cabeÃ§alho curto;
2. filtros e toolbar;
3. superfÃ­cie principal de trabalho;
4. painÃ©is ou modais auxiliares;
5. estados vazios e erro coerentes.

---

## 11. Motion

### 11.1 PrincÃ­pios

- rÃ¡pido;
- discreto;
- sem bounce ostensivo;
- orientado Ã  mudanÃ§a de estado.

### 11.2 Usos aceitÃ¡veis

- fade de entrada leve;
- expand/collapse;
- hover curto;
- presenÃ§a de modal;
- pequenos reforÃ§os de foco.

### 11.3 Usos proibidos

- elasticidade visÃ­vel;
- animaÃ§Ãµes â€œfofasâ€;
- delays longos;
- cascata decorativa em superfÃ­cies operacionais.

---

## 12. Acessibilidade

ObrigatÃ³rio:

- contraste adequado;
- `focus-visible`;
- hierarquia semÃ¢ntica;
- labels reais;
- teclado funcional;
- `Escape` em modais;
- retorno de foco;
- Ã¡reas de toque confortÃ¡veis;
- sem overflow horizontal.

Breakpoints obrigatÃ³rios:

- `375px`
- `600px`
- `840px`
- `1200px`
- `1920px`

---

## 13. Anti-padrÃµes

- descrever o padrÃ£o por nomenclaturas antigas;
- usar pill buttons por hÃ¡bito;
- arredondar tudo;
- grids de cards genÃ©ricos;
- fundos muito coloridos;
- excesso de badges;
- contraste baixo em CTA;
- microcopy longa em interface operacional;
- mistura de estilos publicitÃ¡rio e administrativo na mesma superfÃ­cie.

---

## 14. Mapeamento de compatibilidade

Enquanto a camada tÃ©cnica ainda mantiver nomes herdados em alguns tokens e estilos, a leitura correta Ã© esta:

| Nome tÃ©cnico herdado | Leitura conceitual atual |
|----------------------|--------------------------|
| `primary` | acento principal do DPS Signature |
| `secondary` | apoio estrutural |
| `surface` | fundo/superfÃ­cie clara |
| `outline` | linha estrutural |
| `success` | aÃ§Ã£o positiva |
| `warning` | atenÃ§Ã£o |
| `error` | bloqueio/erro |

Regra:

- nÃ£o usar mais a nomenclatura herdada para justificar decisÃµes de design;
- usar a semÃ¢ntica do DPS Signature para decidir.

---

## 15. Checklist de aceite visual

Antes de considerar uma UI pronta, confirmar:

- a tela parece DPS Signature sem precisar explicar;
- nÃ£o existe heranÃ§a visual percebida de sistemas visuais genÃ©ricos de terceiros;
- os cantos estÃ£o retos ou discretos;
- a paleta estÃ¡ sÃ³bria;
- o CTA principal tem contraste;
- a composiÃ§Ã£o estÃ¡ limpa;
- nÃ£o hÃ¡ overflow;
- nÃ£o hÃ¡ texto cortado;
- mobile continua legÃ­vel;
- o conteÃºdo estÃ¡ melhor distribuÃ­do, nÃ£o apenas â€œmais bonitoâ€.

---

**Fim do Guia de Estilo Visual â€” DPS Signature**
