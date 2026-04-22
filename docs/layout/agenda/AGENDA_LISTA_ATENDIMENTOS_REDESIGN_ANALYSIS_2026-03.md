# Auditoria de Redesign - Agenda / Lista de Atendimentos (2026-03-23)

## Contexto
- Escopo analisado: secao `Lista de Atendimentos` da Agenda e todos os componentes acionados a partir dela.
- Fonte de verdade visual seguida: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.
- Perfil DPS Signature aplicado ao contexto: **Admin / Dashboard -> Standard**, com foco em densidade legivel, hierarquia tonal, interacoes contidas e alto valor operacional.
- Objetivo desta auditoria: identificar cada parte da secao, mapear seus elementos e registrar uma analise profunda para orientar um redesign completo, coerente e implementavel.

## Arquivos-base do estado atual
- `plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php`
- `plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php`
- `plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`
- `plugins/desi-pet-shower-agenda/assets/css/checklist-checkin.css`
- `plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js`
- `plugins/desi-pet-shower-agenda/assets/js/checklist-checkin.js`
- `plugins/desi-pet-shower-agenda/assets/js/pet-profile-modal.js`

## Tese visual e de interacao
- Tese visual: transformar a Lista de Atendimentos em um workspace operacional DPS Signature limpo, sereno e preciso, onde o estado do atendimento seja entendido em um olhar e a proxima acao fique obvia sem ruido visual.
- Tese de conteudo: reduzir textos redundantes, dar nomes mais funcionais aos blocos e padronizar labels, estados e CTAs para que a leitura seja orientada a operacao.
- Tese de interacao: unificar a linguagem de tabs, linhas, badges, seletores, checkboxes e modais para que todas as acoes parecam parte do mesmo sistema, nao de subsistemas costurados.

## 1. Mapa completo da secao

### 1.1 Shell principal da Lista de Atendimentos
1. Header contextual da Agenda
   - titulo da agenda
   - periodo ativo
   - subtitulo contextual
2. Barra de navegacao temporal e de visualizacao
   - botoes `Dia`, `Semana`, `Mes`, `Agenda completa`
   - navegacao `anterior`, `hoje`, `proximo`
3. Resumo numerico
   - cards `Total`, `Pendentes`, `Finalizados`, `Cancelados`, `Atrasados`, `PG pendente`, `TaxiDog`
4. Shell da listagem
   - titulo `Lista de Atendimentos`
   - tabs `Visao Rapida`, `Operacao`, `Detalhes`
   - paineis por dia
   - tabelas por estado (`Proximos Atendimentos`, `Atendimentos Finalizados`)
   - estado vazio
   - paginacao em `Agenda completa`

### 1.2 Aba `Visao Rapida`
- Colunas:
  - `Horario`
  - `Pet`
  - `Servicos`
  - `Confirmacao`
  - `Acoes`
- Elementos por linha:
  - gatilho do perfil rapido do pet/tutor
  - badge de agressividade
  - badge de restricoes de produto
  - botao de servicos
  - dropdown de confirmacao
  - botao de reagendamento

### 1.3 Aba `Operacao`
- Colunas:
  - `Horario`
  - `Pet`
  - `Status do Servico`
  - `Pagamento`
  - `Check-in / Check-out`
  - `Acoes`
- Elementos por linha:
  - dropdown de status
  - CTA de pagamento / badge de pagamento
  - botao expansivel de operacao
  - safety tags compactas
  - linha expansivel com painel de check-in/check-out
  - botao de reagendamento

### 1.4 Aba `Detalhes`
- Colunas:
  - `Horario`
  - `Pet`
  - `TaxiDog`
  - `Observacoes`
  - `Operacional`
  - `Acoes`
- Elementos por linha:
  - badge/CTA de TaxiDog
  - preview de observacoes com tooltip
  - resumo operacional somente leitura
  - botao de reagendamento

### 1.5 Modais, popups e dialogs acionados pela secao
1. Modal de servicos
2. Modal de perfil rapido do pet/tutor
3. Modal de envio de link de pagamento
4. Modal de reagendamento
5. Modal de checklist operacional
6. Modal de retrabalho do checklist
7. `alert()` nativo para historico
8. `confirm()` nativo para solicitar TaxiDog
9. `confirm()` nativo para reenviar pagamento
10. `alert()` nativo em fluxos de checklist/check-in/check-out

### 1.6 Elementos de microinteracao
- toasts
- pills de contexto por dia
- badges de status, pagamento, confirmacao e restricoes
- dropdowns com cor contextual
- checkboxes de safety items
- linha expansivel na aba `Operacao`

## 2. Diagnostico profundo por bloco

## 2.1 Arquitetura da informacao
### O que funciona
- A secao ja tem uma logica correta de densidade progressiva: resumo geral -> tabs -> paineis por dia -> linhas acionaveis.
- A separacao por `Visao Rapida`, `Operacao` e `Detalhes` reduz ruido por contexto e evita uma tabela monolitica.
- O agrupamento por dia ajuda a leitura de volume e estado sem exigir leitura de cada linha inteira.

### O que quebra a experiencia
- A secao ainda se comporta como **tres tabelas diferentes**, nao como um unico workspace. O usuario muda de aba e precisa reaprender o grid, a hierarquia e o comportamento das acoes.
- A divisao interna `Proximos Atendimentos` vs `Atendimentos Finalizados` repete o mesmo bloco visual varias vezes e alonga demais a pagina em mobile.
- O shell superior continua pesado antes da primeira linha util: navegacao, cards, tabs, cabecalho do dia e subtitulos competem pelo mesmo nivel de atencao.

### Direcao de redesign
- Manter as 3 tabs, mas trata-las como variacoes do mesmo grid operacional.
- Reduzir o peso visual acima da primeira linha.
- Unificar comportamento de linha, densidade, alinhamento e linguagem de CTA nas 3 abas.

## 2.2 Sistema visual e consistencia DPS Signature
### Achado critico
- O desenho atual usa **dois sistemas visuais em paralelo**:
  - `agenda-addon.css` usa tokens `--dps-*`
  - `checklist-checkin.css` usa `--md-sys-color-*` com varios fallbacks hardcoded em hex

### Impacto
- A secao principal e os paineis/modais operacionais nao pertencem claramente ao mesmo produto.
- Shapes, sombras, superficies, estados e contraste variam entre blocos.
- Qualquer redesign parcial corre risco de ficar inconsistente logo ao abrir checklist, retrabalho ou check-in.

### Direcao de redesign
- Unificar tudo em um unico dialeto de tokens DPS/DPS Signature.
- Eliminar hex solto e fallbacks visuais arbitrarios dos componentes vivos da lista.
- Definir um kit especifico da Agenda:
  - surface base
  - surface elevada
  - border default
  - action primary
  - action support
  - status semantic
  - dialog shell
  - field shell

## 2.3 Divida tecnica de CSS
### Achado critico
- `agenda-addon.css` concentra multiplas geracoes do mesmo componente.
- Seletores principais aparecem em mais de uma camada:
  - `.dps-agenda-header`
  - `.dps-agenda-controls-wrapper`
  - `.dps-agenda-tabs-nav`
  - `.dps-agenda-tab-button`
  - `.dps-services-modal`
  - `.dps-pet-profile-modal`
  - `.dps-payment-modal`

### Impacto
- O comportamento final depende da ordem historica das regras, nao de uma fonte unica.
- Pequenos ajustes em hover, spacing ou overlay podem ser sobrescritos por blocos anteriores.
- O redesign visual fica instavel, dificil de prever e caro de manter.

### Direcao de redesign
- Antes de redesenhar o look final, consolidar por familias:
  - shell da agenda
  - tabs
  - tabelas/linhas
  - forms/selects
  - modais
  - feedback/toasts
  - estados responsivos

## 2.4 Header, navegacao e resumo
### O que funciona
- O periodo ativo esta claro.
- A navegacao temporal e direta.
- Os overview cards ajudam a identificar gargalos operacionais.

### Problemas atuais
- Header, navegacao e overview cards ainda disputam o mesmo protagonismo.
- Os cards sao visualmente fortes demais para um bloco que deveria ser suporte da listagem.
- Em telas pequenas, o usuario percorre muito conteudo antes de chegar ao primeiro atendimento.
- O texto auxiliar do shell poderia ser mais utilitario e menos descritivo.

### Direcao de redesign
- Reduzir a massa visual do header.
- Rebaixar visualmente o resumo numerico para papel de contexto, nao de hero.
- Em mobile, priorizar:
  1. periodo
  2. troca de visualizacao
  3. tab ativa
  4. primeira lista

## 2.5 Tabs e shell da lista
### O que funciona
- As tabs tem `role=tab`, `aria-selected`, `tabindex` e navegacao por teclado.
- A persistencia via URL/sessionStorage e correta para continuidade de contexto.

### Problemas atuais
- As tabs ainda usam bloco-card alto demais para uma acao de troca de modo.
- `label + descricao + icone` em tres linhas aumenta a altura e empurra o conteudo para baixo.
- Em mobile, o conjunto parece mais um menu de landing page do que um seletor de workspace.
- Ha repeticao de estilo das tabs em blocos diferentes do CSS, o que fragiliza a manutencao.

### Direcao de redesign
- Transformar tabs em um controle mais compacto e mais operacional.
- Manter descricao apenas onde realmente agrega.
- Dar foco em estado ativo, nao em decoracao.
- Considerar:
  - pills largas horizontais em desktop
  - segmented control em tablet/mobile

## 2.6 Tabelas, linhas e escaneabilidade
### O que funciona
- A separacao por dia e por estado ajuda a leitura.
- As linhas ja carregam estado (`status-*`, `is-late`) e suportam atualizacao parcial via AJAX.

### Problemas atuais
- Cada aba muda demais a estrutura da linha e quebra memoria visual.
- Ha mistura de centro, esquerda e pills sem uma grade clara de alinhamento.
- Alguns CTAs ficam enterrados dentro da celula e outros viram coluna dedicada, o que reduz previsibilidade.
- O uso de emojis em massa melhora descoberta pontual, mas fragiliza refinamento visual quando repetido em dropdowns, titulos e CTAs.
- As secoes `Proximos` e `Finalizados` repetem `h5` e container de tabela diversas vezes, alongando a leitura.

### Direcao de redesign
- Definir um esqueleto de linha unico:
  - bloco identitario
  - bloco de estado
  - bloco de acao principal
  - bloco de acao secundaria
- Reduzir variacao desnecessaria entre abas.
- Tratar a linha como unidade operacional, nao como soma de celulas independentes.

## 2.7 Analise por aba

### Aba `Visao Rapida`
#### Pontos fortes
- Boa para recepcao e confirmacao rapida.
- Coluna de servicos e confirmacao tem relevancia real.

#### Problemas
- O dropdown de confirmacao ainda parece controle tecnico, nao decisao de operacao.
- O botao de servicos e o gatilho do pet ja foram melhorados, mas ainda vivem em shells visuais diferentes.
- `Reagendar` como unica acao dedicada deixa a coluna final subaproveitada.

#### Direcao
- Reforcar o conceito de `status de confirmacao` como chip/segmented/select padronizado.
- Integrar `pet`, `servicos` e `confirmacao` com a mesma linguagem de controles leves.

### Aba `Operacao`
#### Pontos fortes
- E a aba com maior valor operacional.
- A linha expansivel evita abrir outro modal para check-in/check-out.

#### Problemas
- Mistura dois modelos de interacao na mesma aba:
  - modal para checklist
  - linha expansivel para check-in/check-out
- O CTA operacional muda demais de forma conforme o estado (`Check-in`, `Check-out`, `Concluido`).
- O popup de pagamento tem estilo proprio e nao conversa com checklist nem perfil do pet.
- Apos status `finalizado`, o fluxo dispara checklist modal; isso quebra a sensacao de continuidade do grid.

#### Direcao
- Tornar `Operacao` a aba mais consistente do sistema.
- Escolher uma linguagem unica para profundidade:
  - ou expandivel inline
  - ou modal/sheet
  - nao metade inline, metade modal sem justificativa clara

### Aba `Detalhes`
#### Pontos fortes
- Concentra contexto logistico sem poluir as outras abas.

#### Problemas
- `TaxiDog`, `Observacoes` e `Operacional` parecem pertencer a sistemas diferentes.
- `Observacoes` depende de truncamento + tooltip, o que esconde informacao importante.
- O resumo operacional somente leitura compete com a coluna de detalhes e gera densidade irregular.

#### Direcao
- Reorganizar a aba como leitura de excecoes e logistica.
- Transformar observacoes em um componente legivel, com prioridade real quando existir.

## 2.8 Controles e formularios
### Dropdowns
- Problema: cada dropdown usa classes e semanticas diferentes (`confirmacao`, `status`, `taxidog`), com pesos e cromias pouco alinhados.
- Problema: labels em uppercase total e com emoji deixam o visual mais ruidoso do que necessario.
- Direcao: criar uma unica base de `select field` DPS Signature para a Agenda, com variacoes semanticas por estado.

### Checkboxes de safety items
- Problema: os checkboxes ainda sao nativos, pequenos e pouco expressivos.
- Problema: o estado selecionado depende mais da mudanca do card do que do proprio controle.
- Problema: a digitacao de notas aparece depois, mas sem uma hierarquia forte de motivo/contexto.
- Direcao:
  - aumentar alvo de toque
  - customizar estado checked/unchecked no padrao DPS Signature
  - dar mais clareza entre item obrigatorio, alerta e anotacao opcional

### Botoes
- Problema: convivem pills, chips, links-texto, botoes soft, botoes coloridos, links com emoji e `a href="#"` com papeis de botao.
- Direcao: consolidar familias:
  - primario
  - tonal
  - text/ghost
  - danger
  - inline icon action

## 2.9 Modais e dialogs

### Achado critico
- A Lista de Atendimentos hoje possui varios modais com shells, overlays, titulos, paddings, hierarquia interna e acessibilidade diferentes.

### Inventario e diagnostico
1. Modal de servicos
   - bom nivel de conteudo
   - foco/ESC/trap ja existem
   - ainda usa shell visual independente
2. Modal de perfil do pet
   - melhorou bastante
   - continua com linguagem propria e grid simples demais para expansao futura
3. Modal de pagamento
   - mais fragil dos modais customizados
   - nao segue o mesmo refinamento do modal de servicos
4. Modal de reagendamento
   - muito simples visualmente
   - funciona, mas parece utilitario legado
5. Modal de checklist
   - vem de outro sistema visual
   - divide linguagem com o modal de retrabalho, mas nao com a Agenda
6. Modal de retrabalho
   - shell basico, sem o mesmo nivel de acabamento/acessibilidade dos outros
7. Historico em `alert()`
   - quebra totalmente o padrao visual
8. Confirmacoes nativas em `confirm()`
   - quebram continuidade de produto
9. Alerts de erro em checklist/check-in
   - quebram feedback e parecem falha de acabamento

### Direcao de redesign
- Criar um unico `dialog system` da Agenda:
  - overlay
  - card shell
  - header
  - body
  - footer
  - foco inicial
  - trap de teclado
  - retorno de foco
  - largura por categoria (`small`, `medium`, `large`)

## 2.10 Feedback, estados e copy
### Problemas
- Toasts convivem com `alert()` e `confirm()`.
- O sistema mistura labels em caixa alta, frases completas, texto tecnico e texto operacional.
- Ha labels corretas no contexto tecnico, mas pouco refinadas no contexto visual:
  - `PG pendente`
  - `Nao confirmado`
  - `Aguardando`
  - `Enviar Link`
  - `Operacional`

### Direcao
- Padronizar microcopy com foco em:
  - orientacao
  - estado
  - proxima acao
- Evitar abreviacoes opacas.
- Definir nomenclatura operacional unica para os 3 modos.

## 2.11 Responsividade
### Problemas atuais
- Em mobile, a altura do shell antes do primeiro atendimento ainda e grande.
- Tabs, cards de resumo e repeticao de secoes por dia geram scroll excessivo.
- A estrategia `tabela -> cards` precisa ser validada junto com as novas hierarquias, nao apenas adaptada por media query.
- O painel expandivel da aba `Operacao` precisa ser reconsiderado para 375 e 600, onde a largura util e muito pequena para manter o grid legivel.

### Direcao
- Projetar a lista mobile como produto de primeira classe.
- Tratar explicitamente os breakpoints obrigatorios:
  - `375`
  - `600`
  - `840`
  - `1200`
  - `1920`

## 3. Achados prioritarios

### P1 - Fundacao visual fragmentada
- `agenda-addon.css` e `checklist-checkin.css` usam linguagens visuais diferentes.
- Sem consolidar isso, qualquer redesign sera parcial e instavel.

### P1 - Sistema de modal inconsistente
- A Lista de Atendimentos usa multiplos shells e ainda depende de `alert()`/`confirm()`.
- O redesign precisa substituir dialogs nativos e unificar todos os modais.

### P1 - CSS com sobreposicao historica
- Varios seletores principais aparecem em mais de um bloco.
- O primeiro passo tecnico deve ser consolidar componentes antes de polir aparencia final.

### P2 - Tabs altas e pouco operacionais
- O seletor de modo ocupa altura demais e atrasa a chegada ao conteudo.

### P2 - Diferenca excessiva entre abas
- A mudanca de aba altera demais a forma da linha e aumenta custo cognitivo.

### P2 - Controles ainda heterogeneos
- Selects, botoes, links-acao e checkboxes nao compartilham um mesmo kit.

### P2 - Feedback visual irregular
- Toasts coexistem com `alert()` e `confirm()`, o que quebra consistencia.

### P3 - Copy e semantica podem ficar mais claras
- Ha espaco para simplificar labels, reduzir uppercase e melhorar orientacao operacional.

## 4. Estrategia recomendada de implementacao

### Fase 1 - Fundacao
- Consolidar CSS por familias de componente.
- Unificar tokens visuais da Agenda e do checklist/check-in.
- Criar base de campo, botao, badge, pill e dialog shell.

### Fase 2 - Shell da lista
- Redesenhar header utilitario, resumo e tabs.
- Reduzir peso visual acima da listagem.
- Ajustar ritmo vertical e hierarquia dos paineis por dia.

### Fase 3 - Linhas e controles
- Unificar esqueleto das linhas nas 3 abas.
- Redesenhar selects/status/confirmacao.
- Padronizar pet trigger, services trigger e CTA de reagendamento.

### Fase 4 - Operacao profunda
- Redesenhar check-in/check-out, safety items e painel operacional.
- Decidir um unico modelo de profundidade para checklist e operacao.
- Substituir checkboxes nativos por controle DPS Signature coerente.

### Fase 5 - Modais
- Reimplementar servicos, perfil, pagamento, reagendamento, checklist e retrabalho em um mesmo sistema de dialog.
- Remover `alert()` e `confirm()` do fluxo.

### Fase 6 - Responsividade e polimento
- Revisar `375`, `600`, `840`, `1200` e `1920`.
- Validar overflow, densidade, targets de toque e ordem de leitura.
- Gerar capturas completas em `docs/screenshots/YYYY-MM-DD/`.

## 5. Decisoes de design que merecem aprovacao antes da execucao
- Se a aba `Operacao` mantera painel expansivel inline ou migrara para um dialog/sheet padronizado.
- Se `Visao Rapida`, `Operacao` e `Detalhes` permanecem como tabs ou viram um segmented control mais compacto em todas as larguras.
- Se a divisao `Proximos Atendimentos` / `Atendimentos Finalizados` continua explicita por bloco ou vira filtro/agrupamento mais compacto.

## 6. Conclusao
- A secao ja tem uma boa base funcional, mas ainda nao opera como um sistema visual unico.
- O principal problema nao e falta de componentes; e falta de **unificacao** entre shell, linhas, controles, operacao e dialogs.
- O redesign completo deve comeÃ§ar pela consolidacao estrutural e so depois partir para o refinamento visual final.
- Seguindo `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`, a Agenda pode evoluir para uma superficie admin DPS Signature realmente elegante, clara e estavel sem perder densidade operacional.
