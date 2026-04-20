# Space Groomers - Fase 3

## Objetivo da fase
Adicionar engajamento de retorno com baixo risco tecnico, agora com persistencia sincronizada no ecossistema DPS quando o cliente estiver autenticado no portal.

## Referencia visual aplicada
As mudancas de frontend seguiram o sistema visual DPS Signature descrito em:
- `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md`
- `docs/visual/VISUAL_STYLE_GUIDE.md`

## Arquivos principais desta fase
- `plugins/desi-pet-shower-game/desi-pet-shower-game.php`
- `plugins/desi-pet-shower-game/includes/class-dps-game-addon.php`
- `plugins/desi-pet-shower-game/includes/class-dps-game-progress-service.php`
- `plugins/desi-pet-shower-game/includes/class-dps-game-rest.php`
- `plugins/desi-pet-shower-game/assets/js/space-groomers.js`
- `plugins/desi-pet-shower-client-portal/includes/class-dps-client-portal.php`
- `plugins/desi-pet-shower-client-portal/assets/js/client-portal.js`
- `plugins/desi-pet-shower-client-portal/assets/css/client-portal.css`
- `plugins/desi-pet-shower-loyalty/includes/class-dps-loyalty-api.php`
- `plugins/desi-pet-shower-loyalty/includes/class-dps-loyalty-rest.php`

## Modelo de persistencia
A fase passa a usar **persistencia hibrida**:
- `localStorage` continua como fallback local do navegador;
- `post meta` do cliente (`dps_cliente`) vira a fonte canonica quando ha portal autenticado.

### Armazenamento canonico no servidor
- Meta key: `dps_game_progress_v1`
- Entidade: post do cliente (`dps_cliente`)
- Servico PHP responsavel: `DPS_Game_Progress_Service`

### Fallback local
- Chave principal: `dps_sg_progress_v1`
- Chave legada preservada: `dps_sg_highscore`
- Adapter local: `LocalProgressAdapter`

### Adapter remoto
- Adapter remoto: `RemoteProgressAdapter`
- Selecionado quando `dpsSpaceGroomersConfig.syncEnabled === true`
- Usa rotas REST do jogo e cookie/sessao do portal

## Estrutura persistida
Payload salvo e sincronizado:
- `version`
- `highscore`
- `totals`
- `records`
- `streak`
- `mission`
- `badges`
- `history`
- `rewardMarkers`
- `lastSyncedAt`

### Campos principais
- `totals`: runs, vitorias, score acumulado, tempo total, power-ups coletados, missoes concluidas
- `records`: melhor combo, maior tempo de run, melhor wave
- `streak`: atual, melhor, ultima data ativa
- `mission`: data da missao, id, progresso, conclusao e timestamp
- `badges`: badges desbloqueadas com timestamp
- `history`: ultimas runs (resumo minimo)
- `rewardMarkers`: chaves de idempotencia para evitar pontuacao duplicada no loyalty
- `lastSyncedAt`: ultimo sync confirmado pelo servidor

## Estrutura de missoes
Modelo adotado: **1 missao rotativa diaria** (deterministica por data), com pool pequeno e facil de manter.

### Pool atual
- sobreviver 60s
- coletar 3 power-ups
- atingir combo 9
- derrotar 6 carrapatos

### Comportamento
- a missao troca por data (`YYYY-MM-DD`);
- o progresso do dia acompanha a conta quando ha sync remoto;
- o HUD do jogo e o resumo do portal mostram titulo, progresso, faltante e status de conclusao.

## Streak simples
- atualizada no fechamento de run;
- mesmo dia: mantem;
- dia seguinte: incrementa;
- intervalo maior: reinicia em 1 na proxima run;
- aparece no start meta do jogo, no pos-run e no resumo do portal.

## Recompensas leves
Recompensa principal continua sendo **badge local**, com extensao leve para loyalty.

### Badges atuais
- Primeiro Banho
- Ritmo de Tesoura
- Missao em Dia
- Retorno em Serie
- Banho Completo

### Recompensas de loyalty
Regras simples, sem economia paralela:
- missao diaria concluida no dia -> contexto `game_daily_mission`
- streak 3 -> contexto `game_streak_3`
- streak 7 -> contexto `game_streak_7`
- primeira vitoria -> contexto `game_first_victory`

Os pontos sao creditados de forma idempotente via `rewardMarkers` e `DPS_Loyalty_API::award_game_event_points()`.

## Endpoints e sincronizacao
Namespace REST do jogo:
- `GET /wp-json/dps-game/v1/progress`
- `POST /wp-json/dps-game/v1/progress/sync`

### Permissao
- cliente autenticado no portal + nonce `dps_game_progress`
- admin com `manage_options`

### Contrato do frontend
`dpsSpaceGroomersConfig` exposto para o jogo contem:
- `clientId`
- `syncEnabled`
- `restUrl`
- `nonce`
- `endpoints.progress`
- `endpoints.sync`
- `storage.progressKey`
- `storage.legacyScoreKey`

## Camada meta fora da run
### Jogo
- start overlay com streak, missao e badges
- pos-run com progresso da missao, records e badges novas
- sync remoto disparado fora do loop principal

### Portal
- resumo sincronizado na aba Inicio
- mostra missao atual, streak, recorde, badges e ultima run
- depende da API do jogo, nao de `localStorage`

## Como adicionar novas missoes depois
1. Adicionar a nova definicao em `MISSION_POOL` no JS.
2. Espelhar a definicao em `DPS_Game_Progress_Service::get_mission_definitions()`.
3. Se houver `kind` nova, implementar o calculo em `SGProgression.prototype.getMissionProgressFromRun`.
4. Validar o resumo do servidor (`build_summary`) e do cliente (`getMissionPreview`).
5. Se a missao puder gerar pontuacao especial, adicionar novo evento no loyalty e um `rewardMarker` correspondente.

## O que ja esta pronto para integracao externa
A fase deixa preparado:
- dominio de progressao separado da UI no JS (`SGProgression`, adapters e callbacks);
- servico canonico de progresso no PHP (`DPS_Game_Progress_Service`);
- rotas REST versionadas do jogo;
- payload sincronizado em meta do cliente, pronto para futuro adapter servidor/portal;
- resumo publico de progresso para consumo do portal;
- pontuacao leve do jogo centralizada no loyalty API;
- evento frontend `dps-space-groomers-progress` para atualizar outras superficies do portal sem acoplamento ao canvas.

## Trade-offs desta fase
Nao foi criada tabela nova nem economia interna.
- Alternativa considerada: tabela dedicada de progresso e fila de eventos por run.
- Motivo para nao usar agora: maior custo de migracao, maior acoplamento e desnecessario para o volume casual do jogo nesta fase.
- Resultado: persistencia leve em post meta, extensivel e suficiente para sincronizacao casual.
