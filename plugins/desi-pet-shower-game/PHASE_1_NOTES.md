# Space Groomers â€” Fase 1 (mobile-first)

## Objetivo da fase
Evoluir o add-on para uma experiÃªncia mobile-first com entrada rÃ¡pida, controle confortÃ¡vel com uma mÃ£o, retry instantÃ¢neo e estabilidade de performance.

## ReferÃªncia visual aplicada
As mudanÃ§as de layout/frontend seguiram o padrÃ£o DPS Signature descrito em:
- `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md`
- `docs/visual/VISUAL_STYLE_GUIDE.md`

## Arquivos alterados
- `plugins/desi-pet-shower-game/assets/js/space-groomers.js`
- `plugins/desi-pet-shower-game/assets/css/space-groomers.css`
- `plugins/desi-pet-shower-game/includes/class-dps-game-addon.php`
- `plugins/desi-pet-shower-game/desi-pet-shower-game.php`
- `plugins/desi-pet-shower-game/PHASE_1_NOTES.md`

## DecisÃµes tomadas (Fase 1)
1. **Input mobile-first por arrasto (pointer events)**
   - Removidos controles primÃ¡rios por botÃµes esquerda/direita/tiro para mobile.
   - Implementado arrasto direto na Ã¡rea do jogo para mover a nave com precisÃ£o.
   - Mantido fallback de teclado para desktop (`A/D`, setas, `Shift/Ctrl` para especial).

2. **Auto-fire sempre ativo durante a partida**
   - O tiro nÃ£o depende mais de manter botÃ£o/tecla pressionada no mobile.
   - Reduz fricÃ§Ã£o de entrada e favorece sessÃµes curtas.

3. **Onboarding mÃ­nimo e CTA direto**
   - CTA de inÃ­cio alterado para â€œToque para comeÃ§arâ€.
   - Tutorial embutido em uma linha (â€œarraste para mover / tiro automÃ¡ticoâ€).
   - Overlay inicial tambÃ©m responde a toque para iniciar rapidamente.

4. **Loop mais rÃ¡pido de jogo**
   - Intro de onda reduzida para 450ms para acelerar inÃ­cio/reinÃ­cio.
   - Retry continua sem recarregar a pÃ¡gina (reinÃ­cio em memÃ³ria).

5. **HUD simplificado e legÃ­vel em tela pequena**
   - Textos do HUD ajustados para â€œPontos / Onda / Vidaâ€.
   - Ajustes de tipografia e espaÃ§amento para viewport reduzido.
   - Barra especial e indicadores reposicionados com foco mobile.

6. **Layout e safe areas**
   - EspaÃ§amentos com `env(safe-area-inset-top/bottom)`.
   - BotÃµes crÃ­ticos afastados das bordas.
   - BotÃ£o especial com Ã¡rea de toque maior (60x60).

7. **Performance e estabilidade de listeners**
   - Mantida estratÃ©gia de listeners globais Ãºnicos para teclado.
   - Aplicada mesma abordagem para pausa/resume em foco/visibilidade (sem duplicar handlers por instÃ¢ncia).
   - Jogo pausa corretamente em `blur`/aba oculta e retoma em `focus`/aba visÃ­vel.

## Trade-off registrado
- **Portrait-first total** (canvas dinÃ¢mico com reprojeÃ§Ã£o de mundo e UI adaptativa completa) foi adiado para Fase 2 para manter o diff contido e baixo risco.
- Nesta fase foi priorizado **mobile confortÃ¡vel e responsivo** mantendo o mundo base 480x640 e ajustes incrementais.

## LimitaÃ§Ãµes conhecidas
- O balanceamento de dificuldade com auto-fire ainda precisa validaÃ§Ã£o fina em aparelho real (principalmente ondas 6â€“10).
- O especial ainda depende de botÃ£o dedicado; nÃ£o foi implementado gesto alternativo para evitar conflito com o arrasto.
- Ainda nÃ£o hÃ¡ calibraÃ§Ã£o separada de sensibilidade por dispositivo.

## PrÃ³ximos passos ideais (Fase 2)
1. Escala/resoluÃ§Ã£o dinÃ¢mica com tuning especÃ­fico para portrait estreito.
2. Polimento de feedback visual/hÃ¡ptico em dano, coleta de power-up e game over.
3. Ajuste fino de ritmo (spawn/cooldown) por telemetria local.
4. Tutorial contextual opcional nas primeiras partidas (sem modal longo).
5. RevisÃ£o de contraste em dark/light conforme tokens globais do DPS.
