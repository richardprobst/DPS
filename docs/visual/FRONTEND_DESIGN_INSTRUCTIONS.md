# InstruÃ§Ãµes de Design Frontend â€” DPS Signature

**VersÃ£o:** 3.0
**Ãšltima atualizaÃ§Ã£o:** 21/04/2026
**Status:** fonte de verdade visual ativa do DPS
**Complementa:** `VISUAL_STYLE_GUIDE.md`
**Base tÃ©cnica:** `plugins/desi-pet-shower-base/assets/css/dps-design-tokens.css`

---

## 1. Status e precedÃªncia

Este documento substitui integralmente qualquer orientaÃ§Ã£o visual anterior do projeto.

No DPS:

- o Ãºnico padrÃ£o visual ativo Ã© **DPS Signature**;
- qualquer referÃªncia visual anterior deve ser tratada como **histÃ³rico obsoleto**, nunca como direÃ§Ã£o atual;
- em caso de conflito entre um documento antigo e `docs/visual/`, **vence `docs/visual/`**.

---

## 2. Tese visual do sistema

O DPS Signature nÃ£o Ã© uma adaptaÃ§Ã£o de um sistema visual externo. Ele define uma identidade prÃ³pria para um software e um ecossistema editorial de uma marca premium de banho e tosa.

Os atributos obrigatÃ³rios do padrÃ£o sÃ£o:

- **premium** sem ostentaÃ§Ã£o;
- **moderno** sem parecer genÃ©rico;
- **minimalista** sem ficar frio ou vazio;
- **sÃ³brio** com acentos pontuais;
- **operacional** em superfÃ­cies de trabalho;
- **editorial** em pÃ¡ginas pÃºblicas e matÃ©rias;
- **reto e preciso** na geometria;
- **consistente** entre WordPress, pÃ¡ginas pÃºblicas, portal, agenda e materiais informativos.

O sistema deve transmitir:

- cuidado,
- mÃ©todo,
- clareza,
- confianÃ§a,
- refinamento.

NÃ£o deve transmitir:

- visual infantilizado,
- estÃ©tica excessivamente arredondada,
- â€œtema de componenteâ€ genÃ©rico,
- aparÃªncia de template SaaS comum,
- heranÃ§a perceptÃ­vel de sistemas visuais genÃ©ricos de terceiros.

---

## 3. PrincÃ­pios obrigatÃ³rios

### 3.1 Clareza antes de ornamento

Toda interface precisa ser compreendida rapidamente por quem opera ou consome o conteÃºdo. Cor, borda, tipografia, espaÃ§amento e movimento devem ajudar leitura e decisÃ£o.

### 3.2 Geometria reta

O DPS Signature privilegia linhas retas, cantos secos e composiÃ§Ã£o precisa.

Regras:

- raio padrÃ£o de superfÃ­cies: `0px`;
- raio aceitÃ¡vel em controles pequenos, quando necessÃ¡rio: `2px` a `4px`;
- evitar qualquer elemento pill por padrÃ£o;
- evitar cartÃµes excessivamente macios;
- nÃ£o misturar cantos retos com componentes muito arredondados na mesma tela.

### 3.3 Densidade controlada

O layout deve respirar, mas sem desperdiÃ§ar altura. O DPS Signature nÃ£o usa grandes vazios decorativos para â€œparecer premiumâ€.

Regras:

- proximidade lÃ³gica entre elementos relacionados;
- afastamento suficiente entre blocos de contexto diferentes;
- evitar cÃ©lulas altas com conteÃºdo centralizado;
- evitar linhas ou cards visualmente â€œmolesâ€ ou subocupados.

### 3.4 Uma hierarquia forte por seÃ§Ã£o

Cada seÃ§Ã£o precisa ter uma funÃ§Ã£o clara:

- orientar;
- operar;
- contextualizar;
- converter;
- informar.

Se uma seÃ§Ã£o mistura tudo ao mesmo tempo, ela estÃ¡ errada.

### 3.5 Cor com funÃ§Ã£o

A paleta nÃ£o Ã© decorativa. Cor deve comunicar:

- aÃ§Ã£o;
- estado;
- prioridade;
- contraste;
- agrupamento.

### 3.6 Movimento discreto

O padrÃ£o nÃ£o proÃ­be animaÃ§Ã£o, mas rejeita movimento chamativo por padrÃ£o.

Use motion apenas para:

- presenÃ§a,
- foco,
- abertura/fechamento,
- mudanÃ§a de estado,
- orientaÃ§Ã£o de fluxo.

---

## 4. Como pensar antes de desenhar

Antes de implementar qualquer UI, responda:

1. Quem usa esta tela?
2. O objetivo Ã© operar, decidir, consultar, converter ou ler?
3. O usuÃ¡rio precisa velocidade, confianÃ§a ou contexto?
4. Qual elemento deve dominar a atenÃ§Ã£o?
5. O que pode ser removido para a tela ficar mais precisa?

Se nÃ£o houver resposta clara para essas cinco perguntas, a interface ainda nÃ£o estÃ¡ pronta para ser desenhada.

---

## 5. Contextos de uso do DPS Signature

### 5.1 SuperfÃ­cies operacionais e administrativas

Exemplos:

- Agenda,
- dashboard,
- configuraÃ§Ãµes,
- hubs administrativos,
- tabelas,
- modais de operaÃ§Ã£o.

DireÃ§Ã£o obrigatÃ³ria:

- aparÃªncia contida;
- contraste alto;
- poucos acentos;
- tipografia firme;
- grids claros;
- foco em leitura horizontal e vertical;
- status objetivos;
- sem hero decorativo;
- sem mosaico exagerado de cards.

### 5.2 Portal do cliente

Exemplos:

- acesso,
- atualizaÃ§Ã£o de perfil,
- histÃ³rico,
- Ã¡reas autenticadas.

DireÃ§Ã£o obrigatÃ³ria:

- acolhimento sem excesso de marketing;
- linguagem limpa;
- navegaÃ§Ã£o direta;
- painÃ©is claros;
- identidade visÃ­vel, mas sem teatralidade.

### 5.3 PÃ¡ginas pÃºblicas institucionais e comerciais

Exemplos:

- home,
- quem somos,
- banho e tosa,
- pÃ¡ginas de serviÃ§o,
- pÃ¡ginas editoriais.

DireÃ§Ã£o obrigatÃ³ria:

- composiÃ§Ã£o mais aberta;
- tipografia mais marcante;
- contraste entre blocos;
- menos bordas desnecessÃ¡rias;
- sensaÃ§Ã£o de marca proprietÃ¡ria;
- CTA claro;
- conteÃºdo com acabamento editorial.

### 5.4 MatÃ©rias e conteÃºdo informativo

Exemplos:

- blog,
- matÃ©rias de saÃºde,
- conteÃºdos de orientaÃ§Ã£o.

DireÃ§Ã£o obrigatÃ³ria:

- leitura confortÃ¡vel;
- ritmo editorial;
- blocos de destaque precisos;
- SEO sem linguagem artificial;
- diferenciaÃ§Ã£o sutil em relaÃ§Ã£o Ã s pÃ¡ginas comerciais, mantendo a mesma identidade-base.

---

## 6. Paleta oficial

Os nomes abaixo sÃ£o a semÃ¢ntica visual oficial do DPS Signature. A implementaÃ§Ã£o pode usar tokens legados por compatibilidade, mas a interpretaÃ§Ã£o de design deve seguir estes papÃ©is:

| Papel | Uso | Valor de referÃªncia |
|------|-----|---------------------|
| `ink` | texto principal, tÃ­tulos, Ã­cones fortes | `#11161c` |
| `petrol` | blocos de destaque, botÃµes fortes, fundos densos | `#173042` |
| `paper` | fundo principal claro | `#f7f2ea` |
| `bone` | superfÃ­cies secundÃ¡rias, Ã¡reas de apoio | `#ece2d3` |
| `line` | bordas, divisores, estrutura | `#d9ccba` |
| `sky` | acento frio sutil, leitura informativa | `#bdd8e7` |
| `action` | sucesso e aÃ§Ã£o positiva | `#1f8c57` |
| `warning` | atenÃ§Ã£o e pendÃªncia | `#8a6622` |
| `danger` | erro, cancelamento, bloqueio | `#a44439` |

Regras:

- `paper` e `bone` sÃ£o a base visual;
- `petrol` Ã© a Ã¢ncora sofisticada do sistema;
- `sky` deve aparecer como respiro, nunca como cor dominante da marca;
- `action`, `warning` e `danger` sÃ£o semÃ¢nticas, nÃ£o decorativas.

Evitar:

- multicolorismo;
- gradientes vistosos por padrÃ£o;
- roxo como viÃ©s automÃ¡tico;
- telas inteiras dominadas por azul saturado;
- uso simultÃ¢neo de vÃ¡rios acentos fortes.

---

## 7. Tipografia oficial

### 7.1 FamÃ­lia recomendada

Par tipogrÃ¡fico canÃ´nico do DPS Signature:

- **display/headings:** `Sora`
- **body/UI:** `Manrope`

Fallbacks aceitÃ¡veis:

- tÃ­tulos: `Segoe UI`, `Arial`, `sans-serif`
- corpo: `Segoe UI`, `Arial`, `sans-serif`

Uso:

- `Sora` para tÃ­tulos, nÃºmeros-chave, cabeÃ§alhos de seÃ§Ã£o, tabs e chamadas;
- `Manrope` para corpo, labels, tabelas, formulÃ¡rios e microcopy.

### 7.2 Regras tipogrÃ¡ficas

- evitar excesso de pesos;
- usar contraste por tamanho, espaÃ§amento e ritmo, nÃ£o sÃ³ por bold;
- labels em caixa alta devem ser curtas e espaÃ§adas;
- nÃ£o exagerar em tracking;
- evitar blocos longos em caixa alta.

### 7.3 Hierarquia mÃ­nima

- `H1`: tÃ­tulo principal da pÃ¡gina;
- `H2`: seÃ§Ã£o principal;
- `H3`: subseÃ§Ã£o ou bloco;
- `label`: informaÃ§Ã£o operacional curta;
- `body`: texto contÃ­nuo;
- `caption`: apoio, contexto, ajuda.

### 7.4 AplicaÃ§Ã£o por contexto

- **admin/operaÃ§Ã£o:** menor contraste emocional, maior contraste funcional;
- **institucional/editorial:** tÃ­tulos com mais presenÃ§a, corpo mais respirado;
- **legal/informativo:** mÃ¡xima legibilidade, mÃ­nimo ruÃ­do.

---

## 8. Formas, bordas e superfÃ­cies

### 8.1 Formas

O padrÃ£o Ã© reto.

Regras:

- containers principais: `0px`;
- inputs e selects: `0px` a `2px`;
- badges e pills: evitar, salvo quando o componente exigir chip curto;
- modais: `0px` a `4px`;
- botÃµes: preferencialmente `0px`.

### 8.2 Bordas

Borda Ã© estrutura, nÃ£o decoraÃ§Ã£o.

Regras:

- usar linhas finas;
- preferir uma borda consistente a mÃºltiplos efeitos;
- evitar sombra pesada quando a borda jÃ¡ resolve a separaÃ§Ã£o;
- evitar outline ornamental.

### 8.3 SuperfÃ­cies

O DPS Signature Ã© **cardless by default**.

Isso significa:

- nÃ£o transformar toda seÃ§Ã£o em card;
- usar blocos, colunas, divisÃ³rias e Ã¡reas bem compostas;
- sÃ³ usar card quando o card for a unidade semÃ¢ntica da interaÃ§Ã£o.

---

## 9. Componentes: direÃ§Ã£o obrigatÃ³ria

### 9.1 BotÃµes

- primÃ¡rio: fundo `petrol` ou `action`, texto claro, canto reto;
- secundÃ¡rio: fundo claro, borda definida, texto escuro;
- nunca usar botÃ£o branco sem contraste suficiente;
- botÃµes de WhatsApp devem ser claramente identificÃ¡veis e visualmente fortes.

### 9.2 Inputs e selects

- devem parecer controles confiÃ¡veis, nÃ£o blocos decorativos;
- altura suficiente para toque real;
- placeholder discreto;
- foco visÃ­vel;
- texto nunca pode cortar o valor selecionado.

### 9.3 Tabelas e listas operacionais

- alinhar conteÃºdo pelo topo quando a linha for alta;
- evitar centralizaÃ§Ã£o vertical que produza vazios grandes;
- garantir estratÃ©gia mobile clara;
- aÃ§Ã£o crÃ­tica nÃ£o pode ficar comprimida;
- colunas devem refletir prioridade real de leitura.

### 9.4 Tabs

- tabs precisam parecer navegaÃ§Ã£o estrutural, nÃ£o botÃµes aleatÃ³rios;
- tÃ­tulo curto;
- subtÃ­tulo apenas quando agregar leitura;
- tab ativa deve ser Ã³bvia sem depender sÃ³ de cor.

### 9.5 Status

Use status com papÃ©is estÃ¡veis:

- pendente = atenÃ§Ã£o;
- finalizado = concluÃ­do operacional;
- pago = confirmaÃ§Ã£o financeira;
- cancelado = bloqueio/erro.

NÃ£o inverter a semÃ¢ntica de cor entre telas.

### 9.6 Modais e dialogs

- shell limpo;
- tÃ­tulo curto;
- aÃ§Ãµes agrupadas com lÃ³gica clara;
- foco visÃ­vel;
- escape funcional;
- retorno de foco no fechamento.

### 9.7 Cards editoriais e blocos de conteÃºdo

- sÃ³ usar quando ajudam narrativa;
- evitar grid de cards genÃ©ricos;
- priorizar composiÃ§Ã£o, ritmo e respiro;
- destacar um bloco por vez.

---

## 10. Layout e espaÃ§amento

O DPS Signature trabalha com ritmo modular e proporÃ§Ã£o disciplinada.

Escala recomendada:

- `4`
- `8`
- `12`
- `16`
- `24`
- `32`
- `48`
- `64`

Regras:

- usar a mesma escala em toda a tela;
- preferir ajustes por escala, nÃ£o por nÃºmeros arbitrÃ¡rios;
- nÃ£o deixar blocos â€œgrudadosâ€;
- nÃ£o criar vazios gigantes sem funÃ§Ã£o;
- revisar sempre desktop e mobile antes de considerar concluÃ­do.

---

## 11. Motion

Motion oficial do DPS Signature:

- curto;
- seco;
- responsivo;
- pouco elÃ¡stico;
- subordinado Ã  clareza.

AplicaÃ§Ãµes aceitÃ¡veis:

- fade/translate leve na entrada;
- expansÃ£o/colapso de painÃ©is;
- realce de hover/focus;
- transiÃ§Ãµes de modal.

AplicaÃ§Ãµes a evitar:

- bounce visÃ­vel por padrÃ£o;
- overshoot decorativo;
- entradas longas;
- animaÃ§Ãµes em cascata sem funÃ§Ã£o;
- elementos â€œflutuandoâ€.

Sempre respeitar `prefers-reduced-motion`.

---

## 12. ConteÃºdo e microcopy

### 12.1 SuperfÃ­cies operacionais

Usar microcopy utilitÃ¡ria:

- curta;
- direta;
- especÃ­fica;
- sem marketing.

### 12.2 PÃ¡ginas comerciais e editoriais

Usar voz natural, sÃ©ria e clara:

- sem artificialidade;
- sem repetiÃ§Ã£o vazia;
- sem texto que pareÃ§a instruÃ§Ã£o de construÃ§Ã£o da pÃ¡gina;
- sem mistura entre conteÃºdo real e placeholder.

### 12.3 PortuguÃªs brasileiro

ObrigatÃ³rio:

- acentuaÃ§Ã£o correta;
- revisÃ£o de redundÃ¢ncia;
- evitar traduÃ§Ãµes literais estranhas;
- manter consistÃªncia entre termos.

---

## 13. Responsividade e acessibilidade

Breakpoints oficiais:

- `375px`
- `600px`
- `840px`
- `1200px`
- `1920px`

Defeitos inaceitÃ¡veis:

- overflow horizontal;
- textos cortados;
- CTA invisÃ­vel;
- modal maior que a viewport;
- tabela sem soluÃ§Ã£o mobile;
- botÃµes pequenos demais;
- foco invisÃ­vel;
- contraste insuficiente.

Checklist mÃ­nimo:

- navegaÃ§Ã£o por teclado;
- `focus-visible`;
- alvos de toque adequados;
- retorno de foco em dialogs;
- sem bloqueio de zoom;
- hierarquia legÃ­vel em todos os breakpoints.

---

## 14. Anti-padrÃµes proibidos

- qualquer orientaÃ§Ã£o para seguir padrÃµes visuais antigos ou externos como fonte de verdade;
- botÃµes pill como padrÃ£o do sistema;
- cards em excesso;
- raio grande por hÃ¡bito;
- hero com componentes aleatÃ³rios flutuando;
- gradiente gratuito;
- dashboard em mosaico genÃ©rico;
- botÃ£o branco sobre fundo claro;
- sombra pesada para simular sofisticaÃ§Ã£o;
- textos de exemplo, instruÃ§Ã£o interna ou copy de construÃ§Ã£o visÃ­vel ao usuÃ¡rio;
- centralizaÃ§Ã£o vertical em linhas altas de tabela;
- microcopy explicando o Ã³bvio.

---

## 15. Arquiteturas de pÃ¡gina obrigatÃ³rias

### 15.1 Home e pÃ¡ginas comerciais principais

Estrutura recomendada:

1. hero com uma promessa clara e um CTA primÃ¡rio;
2. bloco de apoio com prova, diferenciais ou processo;
3. aprofundamento com rotina, critÃ©rios, confianÃ§a ou serviÃ§o;
4. fechamento com CTA e bloco utilitÃ¡rio de contato.

Regras:

- a primeira dobra deve ter uma ideia dominante, nÃ£o uma coleÃ§Ã£o de widgets;
- o CTA primÃ¡rio deve aparecer cedo e com contraste inequÃ­voco;
- os acessos rÃ¡pidos podem existir, mas precisam formar um grupo coerente, nÃ£o uma â€œsopaâ€ de pills;
- endereÃ§o, horÃ¡rios e contato devem viver em uma faixa utilitÃ¡ria clara, normalmente perto do fechamento;
- evitar hero com cards voando, mÃ©tricas gratuitas ou blocos iguais competindo entre si.

### 15.2 PÃ¡ginas institucionais

Estrutura recomendada:

1. abertura com posicionamento claro da marca;
2. bloco de citaÃ§Ã£o, manifesto ou sÃ­ntese editorial;
3. grade de princÃ­pios, rotina, diferenciais ou mÃ©todo;
4. encerramento com confianÃ§a, contato ou prÃ³ximo passo.

Regras:

- a pÃ¡gina institucional nÃ£o deve parecer landing promocional agressiva;
- usar menos CTA do que na home;
- priorizar ritmo editorial e coerÃªncia narrativa;
- cronologias, equipe e bastidores sÃ³ entram quando houver conteÃºdo real.

### 15.3 PÃ¡ginas de serviÃ§o

Estrutura recomendada:

1. hero com resultado prometido e forma de agendamento;
2. grupos de serviÃ§o ou escopo do atendimento;
3. processo em etapas ou critÃ©rios operacionais;
4. restriÃ§Ãµes, dÃºvidas frequentes e bloco utilitÃ¡rio final.

Regras:

- o usuÃ¡rio deve entender rapidamente o que Ã© feito, como agenda e o que precisa consultar;
- incluir disponibilidade variÃ¡vel apenas quando ela for real e operacionalmente relevante;
- preÃ§os sÃ³ entram quando forem estÃ¡veis; caso contrÃ¡rio, orientar consulta;
- nÃ£o misturar linguagem de exemplo com informaÃ§Ã£o real.

### 15.4 PÃ¡ginas legais

Estrutura recomendada:

1. introduÃ§Ã£o curta com contexto e vigÃªncia;
2. Ã­ndice Ã¢ncora opcional se o documento for longo;
3. seÃ§Ãµes `H2` e `H3` com densidade de leitura estÃ¡vel;
4. nota final de atualizaÃ§Ã£o, contato ou canal oficial.

Regras:

- nÃ£o usar hero promocional;
- nÃ£o espalhar CTA comerciais dentro do corpo jurÃ­dico;
- manter respiro suficiente para leitura longa, mas sem teatralidade;
- destacar avisos importantes com boxes discretos, nÃ£o com blocos chamativos.

### 15.5 MatÃ©rias e conteÃºdo editorial

Estrutura recomendada:

1. tÃ­tulo SEO forte;
2. subtÃ­tulo ou linha de apoio;
3. ativo de abertura: imagem, quadro, dado visual ou bloco de destaque;
4. corpo em seÃ§Ãµes curtas;
5. destaques contextuais;
6. CTA final discreto e coerente.

Regras:

- a matÃ©ria deve soar natural e sÃ©ria, nÃ£o publicitÃ¡ria;
- usar quadros de alerta, listas e blocos de sÃ­ntese para conteÃºdo utilitÃ¡rio;
- diferenciar editorial de pÃ¡gina comercial pela densidade de leitura e pelo ritmo do texto;
- nÃ£o transformar a matÃ©ria em landing page disfarÃ§ada.

### 15.6 Portal, autenticaÃ§Ã£o e formulÃ¡rios pÃºblicos

Estrutura recomendada:

1. shell Ãºnico da pÃ¡gina;
2. tÃ­tulo direto;
3. frase de contexto curta;
4. pilha de notices, se houver;
5. seÃ§Ãµes/fieldsets do formulÃ¡rio;
6. rodapÃ© de aÃ§Ãµes.

Regras:

- acesso, reset, cadastro e atualizaÃ§Ã£o de perfil devem parecer da mesma famÃ­lia;
- evitar step rails, sidebars instrucionais e cards de apoio que desviem atenÃ§Ã£o;
- o formulÃ¡rio precisa explicar apenas o necessÃ¡rio para concluir a aÃ§Ã£o;
- helpers devem existir para reduzir erro, nÃ£o para narrar a construÃ§Ã£o da tela.

### 15.7 OperaÃ§Ã£o e administraÃ§Ã£o

Estrutura recomendada:

1. cabeÃ§alho curto com contexto e filtros;
2. Ã¡rea principal de trabalho;
3. painÃ©is de apoio, detalhes expandidos ou modais;
4. estados vazios, loading e erro tratados com o mesmo padrÃ£o.

Regras:

- nÃ£o usar hero em superfÃ­cies operacionais;
- toolbar, tabela, filtros e aÃ§Ãµes precisam formar um eixo visual estÃ¡vel;
- detalhes expandidos nÃ£o podem quebrar a leitura da grade principal;
- a interface deve parecer ferramenta de operaÃ§Ã£o, nÃ£o peÃ§a promocional.

---

## 16. Anatomia obrigatÃ³ria de formulÃ¡rios, notices e caixas de apoio

### 16.1 Shell de formulÃ¡rio

- um tÃ­tulo dominante por pÃ¡gina;
- um Ãºnico shell principal;
- pilha de notices acima das seÃ§Ãµes, nunca espalhada aleatoriamente;
- aÃ§Ãµes principais no rodapÃ© do formulÃ¡rio;
- painÃ©is laterais sÃ³ quando realmente ajudam decisÃ£o ou contexto.

### 16.2 SeÃ§Ãµes e fieldsets

- legend curta e objetiva;
- descriÃ§Ã£o opcional com uma frase;
- grid interno consistente;
- alinhamento de campos previsÃ­vel;
- cada seÃ§Ã£o deve ter um assunto Ãºnico.

### 16.3 Labels, helper text, erro e obrigatoriedade

- label sempre visÃ­vel fora do campo;
- helper text apenas quando reduz erro ou dÃºvida;
- mensagem de erro no mesmo eixo visual do campo;
- marcador de obrigatÃ³rio discreto;
- placeholder nunca substitui label.

### 16.4 Checkbox e radio

- Ã¡rea clicÃ¡vel ampla;
- alinhamento com a primeira linha do texto;
- descriÃ§Ã£o curta quando necessÃ¡ria;
- estado selecionado perceptÃ­vel por borda/fundo e nÃ£o apenas por Ã­cone;
- evitar transformar opÃ§Ãµes simples em chips decorativos.

### 16.5 Upload e anexos

- zona de aÃ§Ã£o clara;
- tipos e limites visÃ­veis quando relevantes;
- preview e estado do arquivo abaixo do controle;
- aÃ§Ã£o explÃ­cita para remover ou substituir;
- estados obrigatÃ³rios: vazio, enviando, enviado e erro.

### 16.6 Notices, alerts e feedback boxes

- uma semÃ¢ntica por caixa;
- tÃ­tulo opcional, mas curto;
- texto objetivo;
- sucesso, aviso, erro e informativo com semÃ¢ntica estÃ¡vel;
- caixa informativa nÃ£o deve competir visualmente com CTA.

### 16.7 Empty states, caixas utilitÃ¡rias e resumos

- estado vazio deve dizer o que falta e o prÃ³ximo passo;
- caixas de endereÃ§o, horÃ¡rio e contato devem compartilhar grade e proporÃ§Ã£o quando aparecem lado a lado;
- resumo utilitÃ¡rio deve usar relaÃ§Ã£o clara entre label, valor e aÃ§Ã£o;
- boxes de apoio precisam alinhar alturas, margens e ritmo quando formam um conjunto.

---

## 17. Anatomia obrigatÃ³ria de modais, dialogs e drawers

### 17.1 Estrutura

- header com tÃ­tulo curto e botÃ£o de fechar previsÃ­vel;
- conteÃºdo com scroll interno quando necessÃ¡rio;
- footer com agrupamento lÃ³gico de aÃ§Ãµes;
- largura definida pela tarefa, nÃ£o por um valor arbitrÃ¡rio replicado em tudo.

### 17.2 Quando usar

- ediÃ§Ã£o rÃ¡pida;
- detalhes complementares;
- confirmaÃ§Ã£o;
- serviÃ§os, perfil rÃ¡pido e operaÃ§Ãµes de suporte.

Se a tarefa exigir leitura longa, mÃºltiplas etapas ou navegaÃ§Ã£o complexa, preferir pÃ¡gina em vez de modal.

### 17.3 Comportamento obrigatÃ³rio

- `aria-modal` e foco visÃ­vel;
- fechamento com `Escape`;
- retorno de foco ao gatilho;
- bloqueio de scroll de fundo;
- respeito a viewport e safe areas no mobile;
- evitar modais aninhados.

---

## 18. Matriz mÃ­nima de cobertura do sistema

O DPS Signature sÃ³ pode ser considerado completo quando orientar explicitamente estes grupos:

- pÃ¡ginas pÃºblicas comerciais;
- pÃ¡ginas institucionais;
- pÃ¡ginas legais;
- matÃ©rias/editorial;
- portais e autenticaÃ§Ã£o;
- formulÃ¡rios pÃºblicos e internos;
- botÃµes e links de aÃ§Ã£o;
- grupos de CTA e links auxiliares;
- inputs, textarea, select, checkbox, radio e upload;
- notices, alerts, toasts e mensagens inline;
- tabs, tabelas e listas operacionais;
- status, badges e marcadores semÃ¢nticos;
- caixas utilitÃ¡rias, resumos e estados vazios;
- herÃ³is, blocos de citaÃ§Ã£o, faixas de destaque e blocos editoriais;
- modais, dialogs, drawers e painÃ©is expandidos.

Se um novo componente fugir dessa matriz, ele deve ser documentado em `docs/visual/` antes de virar padrÃ£o recorrente.

---

## 19. Regra operacional para agentes e implementaÃ§Ãµes

Toda tarefa visual deve:

1. consultar `FRONTEND_DESIGN_INSTRUCTIONS.md`;
2. consultar `VISUAL_STYLE_GUIDE.md`;
3. tratar ambos como fonte de verdade do **DPS Signature**;
4. validar os breakpoints oficiais;
5. registrar evidÃªncias em `docs/screenshots/YYYY-MM-DD/`.

FormulaÃ§Ã£o obrigatÃ³ria em documentaÃ§Ã£o, PRs e respostas:

- usar **DPS Signature** como nome do padrÃ£o;
- nunca descrever o padrÃ£o ativo por nomenclaturas visuais antigas.

---

## 20. PolÃ­tica de transiÃ§Ã£o documental

Se algum arquivo do repositÃ³rio ainda mencionar uma nomenclatura visual anterior:

- considere a referÃªncia **obsoleta**;
- atualize o documento se ele for normativo;
- trate como histÃ³rico apenas quando o documento for claramente archival, analÃ­tico ou changelog.

---

**Fim das InstruÃ§Ãµes de Design Frontend â€” DPS Signature**
