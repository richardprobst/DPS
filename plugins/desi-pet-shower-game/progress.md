Original prompt: Implementar a Fase 2 no add-on plugins/desi-pet-shower-game, assumindo a Fase 1 concluida, com foco em game feel, clareza, dificuldade progressiva, replay e performance mobile, incluindo feedback de combate, power-ups, curva de dificuldade, uma nova variedade de inimigo/padrao, score/combo mais claros e documentacao em PHASE_2_NOTES.md.

2026-03-07
- Lido o estado atual da Fase 1, auditoria do add-on e diretrizes M3 em docs/visual/.
- Reescrito o loop principal com tuning centralizado, hit feedback, freeze curto, shake leve, toast contextual, combo com barra de progresso e power-ups mais explicitos.
- Adicionado novo padrao de inimigo de baixo risco: mergulho telegrafado a partir da onda 4.
- Reduzidas as waves para 8 com escalada mais suave e bonus perfeito ajustado para runs curtas.
- Incluidos window.render_game_to_text e window.advanceTime para facilitar validacao automatizada do jogo.
- Pendente: validar sintaxe, revisar diff final, documentar Fase 2 e tentar validacao visual automatizada no ambiente atual.
