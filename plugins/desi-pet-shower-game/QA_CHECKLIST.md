# QA Checklist — Space Groomers

## Instrucoes
Executar manualmente em ambiente com e sem portal autenticado. Marcar cada item como `OK`, `Falhou` ou `N/A`.

## Mobile portrait
- [ ] Abrir o jogo em viewport mobile portrait e confirmar que HUD, missao e botao especial continuam legiveis.
- [ ] Iniciar run pelo overlay inicial sem zoom involuntario ou toque impreciso.
- [ ] Arrastar a nave continuamente e validar que o controle acompanha o dedo sem saltos relevantes.
- [ ] Pausar pela interface e retomar do mesmo ponto.
- [ ] Trocar para aba/fundo do app e confirmar pausa segura ao voltar.

## Mobile landscape
- [ ] Girar o aparelho com run em andamento e confirmar pausa automatica.
- [ ] Verificar se o overlay de pausa explica o motivo e permite `Retomar` ou `Reiniciar run`.
- [ ] Validar que o canvas continua enquadrado e sem corte visual relevante apos retomar.

## Desktop
- [ ] Jogar usando teclado e confirmar movimento com setas/A-D.
- [ ] Pressionar `Escape` durante a run para pausar e novamente para retomar.
- [ ] Validar foco/blur: ao trocar de janela, a run pausa sem continuar em background.
- [ ] Confirmar overlays finais de derrota e vitoria com estatisticas legiveis.

## Login e portal
- [ ] Sem login: overlay inicial informa fallback local.
- [ ] Com login no portal: overlay inicial informa sincronizacao com o portal.
- [ ] Na aba/painel do portal, confirmar que recorde, streak, missao e badges continuam coerentes apos uma run.
- [ ] Validar que ausencia de modulo de loyalty nao quebra o jogo nem o hub.

## Persistencia
- [ ] Sem login: fechar e reabrir a pagina no mesmo navegador e validar persistencia local.
- [ ] Com login: concluir uma run, recarregar a pagina e validar que o progresso canonico voltou do servidor.
- [ ] Se possivel, testar em navegador sem `localStorage` e confirmar mensagem de progresso volatil.

## Reinicio e lifecycle
- [ ] Finalizar uma run em `game over` e usar `Tentar de novo`.
- [ ] Finalizar uma run em `vitoria` e usar `Jogar de novo`.
- [ ] Pausar manualmente e usar `Reiniciar run` no overlay de pausa.
- [ ] Confirmar que cada retry volta para estado limpo: score zero, vidas restauradas, timers zerados.

## Missao e telemetria
- [ ] Iniciar uma run e confirmar evento visual/funcional de inicio sem regressao.
- [ ] Completar uma missao diaria e confirmar feedback de conclusao ao fechar a run.
- [ ] Abrir console do navegador e validar emissao dos `CustomEvent` de telemetria esperados, se o ambiente permitir.
- [ ] Em ambiente com extensao consumindo hooks PHP, validar recebimento de `dps_game_progress_synced` e `dps_game_telemetry_run_complete`.

## Integracao com portal
- [ ] Card da aba Inicio continua renderizando e abrindo o hub do jogo.
- [ ] Hub do jogo continua funcional sem depender de loyalty.
- [ ] Dados de pet/agendamento continuam visiveis quando disponiveis.

## Observacoes da rodada
- Registrar navegador, viewport, data, estado de login e qualquer diferenca percebida entre desktop e mobile.
- Se algum item falhar, anotar passo exato, resultado esperado, resultado real e se o problema parece visual, de persistencia ou de lifecycle.
