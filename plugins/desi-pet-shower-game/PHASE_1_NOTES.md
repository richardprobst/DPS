# Space Groomers — Fase 1 (mobile-first)

## Objetivo da fase
Evoluir o add-on para uma experiência mobile-first com entrada rápida, controle confortável com uma mão, retry instantâneo e estabilidade de performance.

## Referência visual aplicada
As mudanças de layout/frontend seguiram o sistema visual DPS Signature descrito em:
- `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md`
- `docs/visual/VISUAL_STYLE_GUIDE.md`

## Arquivos alterados
- `plugins/desi-pet-shower-game/assets/js/space-groomers.js`
- `plugins/desi-pet-shower-game/assets/css/space-groomers.css`
- `plugins/desi-pet-shower-game/includes/class-dps-game-addon.php`
- `plugins/desi-pet-shower-game/desi-pet-shower-game.php`
- `plugins/desi-pet-shower-game/PHASE_1_NOTES.md`

## Decisões tomadas (Fase 1)
1. **Input mobile-first por arrasto (pointer events)**
   - Removidos controles primários por botões esquerda/direita/tiro para mobile.
   - Implementado arrasto direto na área do jogo para mover a nave com precisão.
   - Mantido fallback de teclado para desktop (`A/D`, setas, `Shift/Ctrl` para especial).

2. **Auto-fire sempre ativo durante a partida**
   - O tiro não depende mais de manter botão/tecla pressionada no mobile.
   - Reduz fricção de entrada e favorece sessões curtas.

3. **Onboarding mínimo e CTA direto**
   - CTA de início alterado para “Toque para começar”.
   - Tutorial embutido em uma linha (“arraste para mover / tiro automático”).
   - Overlay inicial também responde a toque para iniciar rapidamente.

4. **Loop mais rápido de jogo**
   - Intro de onda reduzida para 450ms para acelerar início/reinício.
   - Retry continua sem recarregar a página (reinício em memória).

5. **HUD simplificado e legível em tela pequena**
   - Textos do HUD ajustados para “Pontos / Onda / Vida”.
   - Ajustes de tipografia e espaçamento para viewport reduzido.
   - Barra especial e indicadores reposicionados com foco mobile.

6. **Layout e safe areas**
   - Espaçamentos com `env(safe-area-inset-top/bottom)`.
   - Botões críticos afastados das bordas.
   - Botão especial com área de toque maior (60x60).

7. **Performance e estabilidade de listeners**
   - Mantida estratégia de listeners globais únicos para teclado.
   - Aplicada mesma abordagem para pausa/resume em foco/visibilidade (sem duplicar handlers por instância).
   - Jogo pausa corretamente em `blur`/aba oculta e retoma em `focus`/aba visível.

## Trade-off registrado
- **Portrait-first total** (canvas dinâmico com reprojeção de mundo e UI adaptativa completa) foi adiado para Fase 2 para manter o diff contido e baixo risco.
- Nesta fase foi priorizado **mobile confortável e responsivo** mantendo o mundo base 480x640 e ajustes incrementais.

## Limitações conhecidas
- O balanceamento de dificuldade com auto-fire ainda precisa validação fina em aparelho real (principalmente ondas 6–10).
- O especial ainda depende de botão dedicado; não foi implementado gesto alternativo para evitar conflito com o arrasto.
- Ainda não há calibração separada de sensibilidade por dispositivo.

## Próximos passos ideais (Fase 2)
1. Escala/resolução dinâmica com tuning específico para portrait estreito.
2. Polimento de feedback visual/háptico em dano, coleta de power-up e game over.
3. Ajuste fino de ritmo (spawn/cooldown) por telemetria local.
4. Tutorial contextual opcional nas primeiras partidas (sem modal longo).
5. Revisão de contraste em dark/light conforme tokens globais do DPS.
