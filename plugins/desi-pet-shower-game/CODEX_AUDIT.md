# CODEX AUDIT — desi-pet-shower-game (Space Groomers)

## 1) Escopo e método

Este relatório cobre inspeção completa do add-on `plugins/desi-pet-shower-game` na versão atual (`1.0.0`), sem alterar gameplay nesta etapa.

Arquivos auditados:
- `desi-pet-shower-game.php`
- `includes/class-dps-game-addon.php`
- `assets/js/space-groomers.js`
- `assets/css/space-groomers.css`

Também foi verificada a integração real do hook do portal no plugin de portal do cliente para confirmar o ponto de acoplamento (`dps_portal_after_inicio_content`).

---

## 2) Arquitetura atual (visão geral)

O add-on segue uma arquitetura simples em 2 camadas:

1. **Camada WordPress/PHP (bootstrap + render de markup)**
   - Registra plugin, textdomain, init e dependência do plugin base.
   - Registra shortcode, submenu admin e integração no portal.
   - Renderiza HTML do jogo (canvas, HUD, overlays, botões mobile).

2. **Camada Front-end JS (engine única no arquivo `space-groomers.js`)**
   - IIFE com toda lógica do jogo em uma “classe” `SpaceGroomers` via prototype.
   - Implementa game loop, update, draw, colisões, ondas, power-ups e HUD.
   - Inicializa automaticamente todos os containers `.dps-space-groomers` na página.

Características:
- **Sem bibliotecas externas** (JS puro + canvas + Web Audio API).
- **Sem backend de score/progresso** (recorde apenas em `localStorage`).
- **Acoplamento alto entre lógica de jogo e DOM/HUD** dentro da mesma classe.

---

## 3) Mapeamento solicitado

### 3.1 Arquivos principais

- **Entrypoint do plugin:** `plugins/desi-pet-shower-game/desi-pet-shower-game.php`
- **Classe principal do add-on:** `plugins/desi-pet-shower-game/includes/class-dps-game-addon.php`
- **Engine do jogo:** `plugins/desi-pet-shower-game/assets/js/space-groomers.js`
- **Estilo do jogo:** `plugins/desi-pet-shower-game/assets/css/space-groomers.css`

### 3.2 Entrypoints PHP

1. **Bootstrap e init**
   - `dps_game_check_base_plugin()` valida presença do base plugin (`DPS_Base_Plugin`).
   - `dps_game_load_textdomain()` em `init` prioridade 1.
   - `dps_game_init()` em `init` prioridade 5 carrega a classe principal.

2. **Classe singleton `DPS_Game_Addon`**
   - Registra shortcode `[dps_space_groomers]`.
   - Registra assets via `wp_register_style/script` em `wp_enqueue_scripts`.
   - Registra submenu admin (`add_submenu_page`) sob `desi-pet-shower`.
   - Integra no portal via `add_action('dps_portal_after_inicio_content', ...)`.

3. **Renderização**
   - `render_game_shortcode()` enfileira assets e renderiza container.
   - `render_portal_card($client_id)` enfileira assets + card no portal.
   - `render_game_container($context)` gera toda estrutura HTML do game.

### 3.3 Assets JS/CSS

- `assets/js/space-groomers.js`
  - IIFE, constantes globais do jogo, funções de áudio/SFX, funções de render de sprites, classe `SpaceGroomers`.
- `assets/css/space-groomers.css`
  - Layout do container/canvas, HUD, overlays, botões, responsividade e card do portal.

### 3.4 Estrutura de rendering do jogo

Rendering usa **Canvas 2D** + **HUD em DOM**:

1. PHP gera `<canvas class="dps-sg-canvas" width="480" height="640">`.
2. JS executa loop `requestAnimationFrame`.
3. Método `draw()` renderiza, em ordem:
   - fundo e estrelas,
   - inimigos,
   - projéteis,
   - “mud” (projéteis inimigos),
   - power-ups,
   - player,
   - partículas.
4. Método `updateHUD()` atualiza score, wave, vidas, combo, power-up ativo e barra especial no DOM.

### 3.5 Modelo de estado do game

Estado macro (`this.state`):
- `idle`
- `waveIntro`
- `playing`
- `gameover`
- `victory`

Estado de partida (em `reset()`):
- score, wave, lives, combo, specialCharge, activePowerup/powerupTimer
- stats por tipo de inimigo (`flea`, `tick`, `furball`)
- entidades em arrays: `bullets`, `enemies`, `muds`, `powerups`, `particles`, `stars`
- input: `keys`, `touchMoving`, `touchFiring`
- controle de inimigos: `enemyDir`, `mudCooldown`

Progressão:
- `start()` → `waveIntro` → `spawnWave()` → `playing`
- `endWave()` aplica bônus “perfect wave”, avança até `TOTAL_WAVES = 10`
- término por `gameOver()` (vidas zeradas) ou `victory()` (onda final)

### 3.6 Sistema de input atual

**Teclado** (global por documento, compartilhado entre instâncias):
- Movimento: `ArrowLeft/ArrowRight` e `a/d`
- Tiro: `Space`
- Especial: `Shift` ou `Control`
- `preventDefault` parcial em teclas de navegação quando há instância jogando

**Mobile (controles virtuais)**:
- Botões touch para esquerda, direita e tiro contínuo
- Botão de especial com clique
- Sem suporte a gesto de arrastar, sem pointer events unificados, sem vibração háptica

### 3.7 HUD atual

HUD e feedbacks atuais:
- Score
- Wave
- Vidas (❤️ / 🖤)
- Indicador de combo (`x2`, `x3`)
- Indicador de power-up ativo + barra de duração
- Barra de especial (carregamento por pontos)
- Overlays: start, wave intro, game over e victory
- Stats finais de abates por tipo de inimigo

### 3.8 Persistência atual

Persistência identificada:
- **`localStorage` apenas para highscore** (`dps_sg_highscore`).
- Não há:
  - persistência em user meta,
  - endpoint AJAX/REST,
  - transient/object cache,
  - ranking global,
  - missões diárias, streaks ou meta de progresso.

### 3.9 Integração com WordPress e portal do cliente

Integrações reais:
- **WordPress**
  - plugin bootstrap padrão, i18n, shortcode, submenu admin, enqueue por registro+enqueue.
- **Portal do cliente**
  - acoplamento via hook `dps_portal_after_inicio_content`.
  - o hook é realmente disparado no portal (`class-dps-client-portal.php`).

Não há integração com:
- autenticação da sessão para placar,
- dados do cliente para progressão,
- gamificação sistêmica do portal.

### 3.10 Dependências entre arquivos

Fluxo de dependências:
1. `desi-pet-shower-game.php`
   - define constantes e inicializa `DPS_Game_Addon`.
2. `includes/class-dps-game-addon.php`
   - depende das constantes (`DPS_GAME_URL`, `DPS_GAME_VERSION`).
   - renderiza markup que define contratos de classes CSS/DOM esperados pelo JS.
3. `assets/js/space-groomers.js`
   - depende 100% da estrutura HTML/CSS classes emitidas pelo PHP.
4. `assets/css/space-groomers.css`
   - depende dos mesmos seletores renderizados em `render_game_container()` e `render_portal_card()`.

Ponto crítico: há **contrato implícito forte por classe CSS** entre PHP ↔ JS ↔ CSS (sem camada de template versionada ou teste de contrato).

---

## 4) Problemas para mobile

1. **Canvas fixo lógico 480x640**
   - Escala visual ocorre via CSS, mas velocidade/física não se adapta ao viewport/dispositivo.
   - Pode reduzir legibilidade em telas pequenas e gerar sensação de hitbox “injusta”.

2. **Input touch básico e limitado**
   - Somente botões discretos (esquerda/direita/fogo/especial).
   - Sem suporte a arraste contínuo do player (drag to move).
   - Sem fallback de pointer events para caneta/mouse touch híbrido.

3. **Sem pausa/resume por visibilidade**
   - Não há gestão explícita de `visibilitychange`.
   - Em mobile, troca de app/interrupções pode causar experiência inconsistente.

4. **Overlays e HUD densos em telas menores**
   - Existe media query básica, porém sem reflow avançado de conteúdo.
   - Informações competem com área de jogo em dispositivos compactos.

5. **Ausência de otimizações de input/percepção mobile**
   - Sem feedback háptico.
   - Sem calibração de cooldown/velocidade para controle touch (normalmente menos preciso que teclado).

---

## 5) Problemas de game feel

1. **Loop simples sem curva de dificuldade adaptativa por desempenho**
   - A dificuldade escala por wave fixa; não considera performance do jogador.

2. **Combate com pouca variedade de feedback**
   - Há SFX e partículas, mas sem variação mais rica por tipo de acerto crítico/cadeia.

3. **Especial pouco telegrafado no momento de uso**
   - Barra existe, mas falta antecipação/efeito pré-ativação e impacto audiovisual mais claro.

4. **Combo com regra oculta**
   - Sistema de combo existe, porém sem tutorial progressivo/contextual, dificultando domínio inicial.

5. **Ausência de micro-metas intra-partida**
   - Não há objetivos curtos (“elimine X sem errar”, “colete Y powerups”), reduzindo sensação de progressão minuto a minuto.

---

## 6) Problemas de retenção/engajamento

1. **Persistência mínima (apenas highscore local)**
   - Jogador perde progresso ao trocar navegador/dispositivo.
   - Não existe identidade de progresso vinculada ao cliente do portal.

2. **Sem loops de retorno (retention loops)**
   - Não há recompensas diárias, missões, streak ou desbloqueáveis.

3. **Sem social proof/comparação**
   - Não existe ranking por pet shop/unidade/cliente.

4. **Pouca integração com ecossistema do portal**
   - O jogo está inserido visualmente no início, mas não influencia métricas/benefícios no portal.

5. **Sem instrumentação básica de analytics de gameplay**
   - Sem telemetria de sessão/abandono/wave média, dificultando evolução orientada por dados.

---

## 7) Riscos técnicos

1. **Acoplamento monolítico no JS**
   - Estado, regras, render e input no mesmo arquivo/classe dificultam manutenção incremental.

2. **Contrato de DOM não tipado/não testado**
   - Mudanças de classe HTML podem quebrar JS silenciosamente.

3. **Listeners globais compartilhados (`document`)**
   - Lógica multi-instância existe, porém pode gerar efeitos colaterais em páginas com vários jogos embutidos.

4. **Constantes de gameplay hardcoded**
   - Ajustes finos exigem editar código-fonte, sem configuração central por contexto.

5. **Dependência total de `localStorage` para recorde**
   - Pode falhar por quota/políticas do navegador sem fallback de persistência no usuário logado.

6. **Sem testes automatizados de regressão de gameplay/DOM**
   - Refatorações ficam mais arriscadas ao longo do tempo.

---

## 8) Recomendação de refatoração em fases (incremental, sem rewrite)

### Fase 0 — Baseline e segurança de evolução (curta)

Objetivo: preparar terreno sem mudar gameplay.

- Extrair **config central** (constantes de balanceamento e timings) em objeto único.
- Mapear/selectors de DOM em camada única (ex.: `uiMap`) para reduzir acoplamento implícito.
- Adicionar eventos internos mínimos (`onGameStart`, `onWaveEnd`, `onGameOver`) para futura telemetria.
- Criar documentação de contratos (DOM + estado) no próprio add-on.

**Resultado esperado:** maior legibilidade e menor risco de quebra ao iterar.

### Fase 1 — Mobile first sem alterar core loop

Objetivo: melhorar jogabilidade mobile preservando regras atuais.

- Suporte a **pointer events** + opcional “drag para mover”.
- Ajustes de UX mobile:
  - áreas de toque maiores,
  - melhor posicionamento/escala de HUD,
  - pausa automática em `visibilitychange`.
- Ajuste leve de tuning para touch (sem alterar progressão macro).

**Resultado esperado:** controle mais responsivo e menos frustração em tela pequena.

### Fase 2 — Game feel e onboarding

Objetivo: aumentar diversão percebida sem romper compatibilidade.

- Melhorar feedback audiovisual contextual (acerto, combo, especial pronto/ativado).
- Tutorial curto no overlay inicial (2–3 dicas acionáveis).
- Clarear regras de combo/especial na HUD.

**Resultado esperado:** curva de aprendizado mais suave e sensação de impacto maior.

### Fase 3 — Engajamento e retorno

Objetivo: criar loop de retenção acoplado ao portal.

- Persistir progresso do usuário (mínimo: melhor score por cliente logado) via endpoint WP seguro.
- Missões simples e resetáveis (ex.: diárias/semanais) sem bibliotecas externas.
- Recompensas cosméticas/insígnias no portal (sem interferir em fluxos críticos).

**Resultado esperado:** aumento de retorno e tempo de sessão.

### Fase 4 — Observabilidade e otimização contínua

Objetivo: evolução orientada por dados.

- Telemetria básica (sessões iniciadas, wave média, abandono, uso de especial).
- Painel admin enxuto para métricas do add-on.
- Rodadas pequenas de balanceamento com base em dados reais.

---

## 9) Estrutura-alvo sugerida (simples e incremental)

Sem reescrever do zero, quebrar `space-groomers.js` em módulos lógicos (mesmo que inicialmente no mesmo arquivo):

1. `GameState` (estado e transições)
2. `GameSystems` (spawn, colisão, progressão, power-ups)
3. `GameRenderer` (canvas draw)
4. `GameUI` (HUD, overlays, mensagens)
5. `InputController` (keyboard/touch/pointer)
6. `PersistenceAdapter` (localStorage agora; WP API depois)

Passo intermediário seguro: manter um único arquivo por enquanto, mas separar claramente por blocos/métodos e contratos de interface internos.

---

## 10) Decisões e pontos de atenção

- **Sem mudança de gameplay nesta entrega:** foco exclusivo em auditoria técnica e plano.
- **Sem novas dependências externas:** não há necessidade nesta etapa de inspeção.
- **Sem inventar integrações:** somente integrações confirmadas no código foram reportadas.

Trade-off adotado:
- Em vez de já refatorar o JS monolítico agora, foi priorizado mapear riscos e desenhar fases incrementais para preservar comportamento atual e reduzir chance de regressão em produção.

---

## 11) Arquivos provavelmente alterados na Fase 1 (mobile first)

1. `plugins/desi-pet-shower-game/assets/js/space-groomers.js`
   - adicionar `pointer events`, pausa por `visibilitychange`, ajustes de controle touch.

2. `plugins/desi-pet-shower-game/assets/css/space-groomers.css`
   - melhorar ergonomia dos controles, HUD responsiva e safe areas mobile.

3. `plugins/desi-pet-shower-game/includes/class-dps-game-addon.php`
   - ajustes mínimos de markup/atributos para suporte a novos controles e acessibilidade.

4. (Opcional, se necessário) `plugins/desi-pet-shower-game/desi-pet-shower-game.php`
   - apenas se houver necessidade de version bump e/ou novos dados de configuração injetados para o script.
