# Space Groomers - Fase 2

## Objetivo da fase
Deixar o jogo mais vivo e mais claro no momento a momento, com impacto suficiente para estimular replay sem sacrificar performance mobile.

## Referencia visual aplicada
As mudancas visuais e de HUD desta fase seguiram o sistema visual DPS Signature descrito em:
- `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md`
- `docs/visual/VISUAL_STYLE_GUIDE.md`

## Arquivos alterados
- `plugins/desi-pet-shower-game/assets/js/space-groomers.js`
- `plugins/desi-pet-shower-game/assets/css/space-groomers.css`
- `plugins/desi-pet-shower-game/includes/class-dps-game-addon.php`
- `plugins/desi-pet-shower-game/desi-pet-shower-game.php`
- `plugins/desi-pet-shower-game/PHASE_2_NOTES.md`
- `plugins/desi-pet-shower-game/progress.md`

## Efeitos e mecanicas adicionados
1. **Feedback de combate mais legivel**
   - Hit feedback com `hurtTimer` nos inimigos e pequenos bursts de particulas.
   - Floating score por kill para reforcar recompensa imediata.
   - Freeze-frame curto e shake leve em hit/kill para aumentar impacto sem exagero.
   - Feedback de dano no player com flash vermelho, burst de particulas e breve invulnerabilidade.
   - Transicao de game over em duas etapas (`gameoverTransition` -> overlay) para encerrar a run com mais peso.

2. **Impacto leve e seguro para mobile**
   - Particulas com cap global (`particleCap: 96`).
   - Toast contextual reutilizado para combo, power-up, especial pronto e aviso de ultima vida.
   - Pulso apenas em elementos pequenos de HUD (`combo`, `especial pronto`, botao carregado).
   - Nenhum efeito depende de filtros pesados, blur no canvas ou bibliotecas externas.

3. **Power-ups mais claros**
   - Cada power-up agora aparece com label curta ainda no objeto em queda.
   - O indicador ativo mostra nome + descricao, nao apenas icone.
   - Overlay inicial passou a explicar rapidamente os dois power-ups existentes.
   - Area de coleta foi levemente ampliada (`pickupRadius: 34`) para reduzir confusao/frustracao na coleta.

4. **Variedade nova com baixo risco**
   - Novo padrao simples: inimigo em **mergulho telegrafado** a partir da onda 4.
   - O mergulho usa aviso visual curto, reaproveita o mesmo sistema de inimigos e nao exige novos assets.
   - Escolha feita por menor risco estrutural que mini-boss/boss e por impacto melhor que apenas um novo status de projectile.

5. **Score e combo mais claros**
   - Combo agora aparece cedo, com thresholds menores e barra de progressao.
   - Toast e floating text reforcam quando o jogador entra em x2/x3.
   - Especial pronto tambem ganhou aviso claro e pulso no botao/barra.

## Como a curva foi ajustada
- **Total de waves** reduzido de 10 para 8 para aproximar melhor o alvo de runs de 45 a 90 segundos.
- **Inicio mais amigavel**:
  - waves 1-2 sem mergulho;
  - lama so a partir da wave 3;
  - composicao inicial menor (5 colunas x 2 linhas).
- **Escalada progressiva**:
  - colunas/linhas sobem aos poucos (`cols` ate 7, `rows` ate 4);
  - velocidade cresce por multiplicador de wave (`0.92 + wave * 0.06`);
  - cooldown de lama e de mergulho encurta gradualmente por wave.
- **Combo mais acessivel**:
  - x2 em 4 acertos seguidos;
  - x3 em 9 acertos seguidos;
  - janela de combo de `3.8s`.
- **Especial mais presente**:
  - custo reduzido para `420`, para o jogador experimentar a ferramenta com mais frequencia em runs medias.

## Valores de tuning que provavelmente vao pedir iteracao manual
- `BALANCE.comboTier2 = 4`
- `BALANCE.comboTier3 = 9`
- `BALANCE.comboWindow = 3.8`
- `BALANCE.specialCost = 420`
- `BALANCE.powerupBaseChance = 0.00125`
- `BALANCE.mudBaseInterval = 3.1`
- `BALANCE.diveBaseInterval = 7.5`
- `BALANCE.playerInvulnMs = 850`
- `BALANCE.gameOverDelayMs = 620`

## Riscos e perguntas em aberto para calibracao futura
1. **Run time real em aparelho**
   - A meta de 45-90s foi ajustada por tuning e densidade de wave, mas ainda precisa confirmacao em uso real touch, principalmente em telas pequenas.

2. **Combo x3 pode ficar permissivo demais para jogadores experientes**
   - Se o replay estiver bom, mas score crescer rapido demais, os thresholds de combo ou a janela podem subir um pouco.

3. **Mergulho telegrafado pode precisar de mais/menos aviso**
   - O telegraph atual foi pensado para ser legivel sem ficar lento. Vale validar se o aviso esta justo no mobile.

4. **Custo do especial pode estar baixo demais em runs longas**
   - Se o jogador estiver limpando a metade inferior com muita frequencia, o custo pode voltar um pouco acima de 420.

5. **Power-up de toalha continua forte por definicao**
   - Ele e claro e satisfatorio, mas pode exigir chance de drop menor caso domine demais as waves medias.
