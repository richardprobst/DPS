# Screenshots 2026-04-16 - Paginas publicas do site

## Contexto
- Objetivo da mudanca: revisar de forma integral a UI e UX das paginas publicas do site, removendo texto metalinguistico, reforcando hierarquia visual, refinando composicao e validando responsividade em contexto de preview local.
- Ambiente: edicao local no repositorio.
- Referencia de design M3 utilizada: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.

## Antes/Depois
- Antes: as paginas estavam organizadas e limpas, mas ainda com uma linguagem visual mais proxima de blocos isolados do que de um conjunto editorial premium; algumas areas repetiam o mesmo tratamento visual e faltava um loop de preview responsivo confiavel.
- Depois: a base visual compartilhada foi refeita para WordPress + Flatsome com hero mais forte, superfices mais elegantes, melhor ritmo vertical, melhor contraste entre CTA primario/secundarios e leitura mais clara em paginas institucionais e legais.
- Ajustes adicionais:
  - hero e composicao geral reforcados com base no padrao M3 e em uma direcao visual mais premium;
  - remocao de frases de explicacao de layout, wireframe ou construcao da pagina;
  - substituicao de trechos que soavam placeholder por texto institucional/comercial final;
  - limpeza de caracteres corrompidos em `quem-somos`, `banho-e-tosa` e `politica-de-privacidade`;
  - reorganizacao de `home`, `banho-e-tosa`, `regras-e-termos-de-atendimento` e `politica-de-privacidade` para melhorar CTA, leitura, navegacao e escaneabilidade;
  - adicao de fechamentos de jornada em `quem-somos`, `regras-e-termos-de-atendimento` e `politica-de-privacidade`, com CTA, contato e navegacao complementar;
  - criacao de um preview HTTP local reutilizavel para validar os fragments do WordPress fora do editor;
  - integracao da logo da Desi em formato de lockup no topo dos heros, com base clara para garantir leitura sobre o fundo escuro;
  - reforco do CTA de WhatsApp com tratamento visual mais tipico do canal, incluindo icone inline e preenchimento verde mais evidente;
  - transicao da direcao visual para uma identidade mais propria da marca, com tipografia mais contemporanea, espacamento mais controlado e linguagem menos proxima de UI padrao;
  - reducao agressiva de arredondamento para uma composicao mais reta, moderna e minimalista;
  - reescrita editorial das paginas em portugues brasileiro com voz de marca mais consistente, acentuacao corrigida e corte de repeticoes excessivas.
  - consolidacao de uma paleta mais sobria, com base em ink, petrol, paper e bone, usando azul claro apenas como acento discreto e o verde reservado ao CTA de WhatsApp;
  - alinhamento do preview local a essa mesma paleta para registro visual coerente com a rodada mais recente.
  - refinamento fino aplicado em `quem-somos` e `banho-e-tosa`, com hero interno mais intencional, CTA final mais editorial e melhor densidade visual;
  - expansao da arquitetura publica com cinco novas paginas: `perguntas-frequentes`, `primeira-visita`, `taxidog`, `contato-e-localizacao` e `frequencia-e-cuidados`.
  - passada estrategica na home para encaixar melhor as paginas novas na jornada comercial, com uma faixa dedicada a decisao, confianca, primeira visita, FAQ, TaxiDog e localizacao;
  - integracao de `nossos-diferenciais` na navegacao interna de `quem-somos` e `banho-e-tosa`, reforcando o percurso entre marca, servico e confianca.
- Arquivos de codigo alterados:
  - `docs/layout/site/flatsome/flatsome-additional-css.css`
  - `docs/layout/site/pages/home/page-content.html`
  - `docs/layout/site/pages/quem-somos/page-content.html`
  - `docs/layout/site/pages/banho-e-tosa/page-content.html`
  - `docs/layout/site/pages/perguntas-frequentes/page-content.html`
  - `docs/layout/site/pages/primeira-visita/page-content.html`
  - `docs/layout/site/pages/taxidog/page-content.html`
  - `docs/layout/site/pages/contato-e-localizacao/page-content.html`
  - `docs/layout/site/pages/frequencia-e-cuidados/page-content.html`
  - `docs/layout/site/pages/regras-e-termos-de-atendimento/page-content.html`
  - `docs/layout/site/pages/politica-de-privacidade/page-content.html`
  - `docs/layout/site/site-pages.manifest.json`
  - `docs/layout/site/README.md`
  - `docs/screenshots/2026-04-16/site-pages-preview.html`

## Capturas
- `./home-desktop-1280-fullpage.png`
- `./home-mobile-375-fullpage.png`
- `./quem-somos-desktop-1280-fullpage.png`
- `./quem-somos-mobile-375-fullpage.png`
- `./banho-e-tosa-desktop-1280-fullpage.png`
- `./banho-e-tosa-mobile-375-fullpage.png`
- `./perguntas-frequentes-desktop-1280-fullpage.png`
- `./perguntas-frequentes-mobile-375-fullpage.png`
- `./primeira-visita-desktop-1280-fullpage.png`
- `./primeira-visita-mobile-375-fullpage.png`
- `./taxidog-desktop-1280-fullpage.png`
- `./taxidog-mobile-375-fullpage.png`
- `./contato-e-localizacao-desktop-1280-fullpage.png`
- `./contato-e-localizacao-mobile-375-fullpage.png`
- `./frequencia-e-cuidados-desktop-1280-fullpage.png`
- `./frequencia-e-cuidados-mobile-375-fullpage.png`
- `./nossos-diferenciais-desktop-1280-fullpage.png`
- `./nossos-diferenciais-mobile-375-fullpage.png`
- `./regras-e-termos-de-atendimento-desktop-1280-fullpage.png`
- `./regras-e-termos-de-atendimento-mobile-375-fullpage.png`
- `./politica-de-privacidade-desktop-1280-fullpage.png`
- `./politica-de-privacidade-mobile-375-fullpage.png`
- Preview local usado para validacao: `./site-pages-preview.html`

## Observacoes
- Breakpoints validados no preview local: `375`, `600`, `840`, `1200` e `1920`.
- Resultado da checagem automatizada: sem overflow horizontal nas cinco paginas em todos os breakpoints verificados.
- As capturas foram geradas a partir do preview local com `clean=1`, para registrar apenas a pagina renderizada e nao a barra de navegacao do shell de preview.
- Nesta rodada final, os prints foram regenerados apos a correcao de presenca da marca no hero e apos o ajuste do CTA de WhatsApp para um padrao visual mais reconhecivel.
- Os prints atuais ja refletem a fase mais recente de refinamento, com fonte de tendencia mais minimalista, espacamento recalibrado e cantos retos nos elementos.
- Os textos capturados nesta versao ja correspondem a uma nova passada de copy, com foco em Afeto Organizado + Premium Sereno, revisao de acentos em PT-BR e reducao de redundancias.
- Esta rodada final tambem reduziu a presenca de acentos visuais fora dos pontos realmente importantes, para reforcar uma leitura mais premium, moderna e minimalista.
- Ajuste fino adicional na home: os dois paineis lado a lado de "O que faz diferenca" e "Pontos da rotina" passaram a compartilhar a mesma altura visual no desktop, eliminando a diferenca de tamanho entre as boxes.
- Ajuste fino adicional no hero da home: redistribuicao dos botoes e sinais de confianca para reduzir ruido visual, com CTA principal mais destacado, acao secundaria mais leve e badges organizadas em uma malha mais regular.
- Ajuste fino adicional no painel lateral da home: horarios reorganizados em linhas separadas para leitura imediata, endereco com CTA dedicado para abrir rota no GPS e textos de apoio reduzidos para escaneabilidade mais clara.
- Microajustes finais na home: hero com proporcao mais favoravel ao bloco principal, painel lateral mais compacto e menos pesado, e cards de acesso rapido com menor massa visual para funcionarem como apoio e nao como novo foco da pagina.
- Ajuste fino adicional na area de agendamento da home: cabecalho da secao, CTA e cards de contato foram reorganizados em uma composicao mais alinhada, com CTA encaixado ao lado do texto principal no desktop e cards com altura e ritmo mais consistentes.
- Ajuste fino adicional nos blocos de "Servicos principais" e "Perguntas frequentes" da home: servicos com composicao menos generica e mais editorial, e FAQ com cards mais regulares e melhor escaneabilidade em desktop e mobile.
- As novas paginas surgiram de benchmark em sites relevantes do segmento e foram escolhidas para cobrir lacunas reais da arquitetura atual: FAQ dedicada, primeira visita, deslocamento/TaxiDog, contato/localizacao e frequencia de cuidados.
- Em uma passada adicional de benchmark, a pagina `nossos-diferenciais` foi adicionada para cobrir o bloco de confianca/processo que aparece com frequencia em marcas mais fortes do segmento.
- Nesta passada estrategica final, a home deixou de depender apenas de CTA primario e acesso rapido, passando a orientar melhor o visitante entre confianca, preparo, duvidas e canais de contato antes do agendamento.
- `quem-somos` e `banho-e-tosa` agora encaminham tambem para `nossos-diferenciais`, reduzindo a chance de a jornada ficar limitada a servico + regras e melhorando a leitura de valor da marca.
