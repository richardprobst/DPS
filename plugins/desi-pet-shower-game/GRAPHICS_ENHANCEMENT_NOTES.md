# Space Groomers - Graphics Enhancement Notes

## Objetivo
Melhorar a apresentacao visual geral do add-on `desi-pet-shower-game` sem reescrever a engine, preservando o carater arcade casual, a leitura em telas pequenas e a performance mobile.

## Referencia visual aplicada
A revisao visual seguiu explicitamente o padrao M3 descrito em:
- `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md`
- `docs/visual/VISUAL_STYLE_GUIDE.md`

## Arquivos alterados
- `plugins/desi-pet-shower-game/includes/class-dps-game-addon.php`
- `plugins/desi-pet-shower-game/assets/css/space-groomers.css`
- `plugins/desi-pet-shower-game/assets/js/space-groomers.js`
- `docs/screenshots/2026-03-08/space-groomers-graphics-enhancement-preview.html`
- `docs/screenshots/2026-03-08/SCREENSHOTS_2026-03-08.md`
- `plugins/desi-pet-shower-game/progress.md`

## Melhorias visuais implementadas

### 1. Direcao visual e paleta
- Fundo do jogo reequilibrado para um gradiente espacial mais profundo, com camadas suaves de luz e horizonte leve.
- Paleta reorganizada em torno de azul petroleo, ciano luminoso e acentos quentes dourado/coral para reforcar clareza e um look casual premium.
- Overlays, HUD e paineis passaram a usar superficies escuras coerentes, com bordas suaves e contraste mais consistente.

### 2. Gameplay e renderizacao do canvas
- Player redesenhado com silhueta mais legivel, canopy mais clara, asas laterais e propulsores com leitura melhor em movimento.
- Inimigos ganharam identidades mais distintas:
  - `flea`: leitura organica e mais amigavel, com corpo separado da cabeca.
  - `tick`: silhueta mais blindada e pesada.
  - `furball`: massa fofa com contorno mais reconhecivel.
- Projetil revisado para formato de capsula brilhante, mais facil de ler em telas pequenas.
- Power-ups diferenciados visualmente por forma interna e acento de cor, sem depender apenas de emoji/texto.
- Particulas passaram a usar pontos circulares leves e floating texts com sombra moderada para melhor contraste.

### 3. HUD e overlays
- HUD principal reorganizada em cards compactos com hierarquia mais clara para pontos, onda, vida e pausa.
- Missao, combo, power-up ativo e barra especial receberam superficies mais coesas e leitura melhor em mobile.
- Tela inicial ganhou eyebrow, CTA mais forte, meta diaria mais organizada e legenda de power-ups em formato de cards.
- Telas de game over/vitoria receberam cards-resumo para combo, onda e tempo da run, alem de CTA mais limpo.
- Tela de pausa passou a conversar melhor com o restante da identidade visual.

### 4. Efeitos visuais leves
- Profundidade sutil no fundo com planetas/luzes suaves e linhas de horizonte discretas.
- Melhor hit feedback por cor e squash leve nas silhuetas.
- Explosoes e pickups continuam leves, sem filtros caros nem bibliotecas externas.
- Removido o peso de `backdrop-filter` nas camadas recorrentes do HUD para reduzir custo em mobile.

## Decisoes de paleta e estilo
- Base escura: `#051427` -> `#174163` para preservar leitura arcade sem visual chapado.
- Acento frio: ciano/azul para player, CTA e energia especial.
- Acento quente: dourado/coral para combo, nariz do player e leitura de recompensa.
- Resultado: o jogo continua reconhecivelmente o mesmo, mas com acabamento mais polido e menos aspecto placeholder.

## Efeitos adicionados ou refinados
- Novo backdrop desenhado no canvas com profundidade leve.
- Novas silhuetas 2D para player, inimigos e power-ups.
- Cards de resultado nas telas finais.
- Melhor brilho visual percebido via contraste e shapes, nao via blur pesado.

## Performance e trade-offs
- A implementacao evitou bibliotecas novas, sprites bitmap e filtros pesados no canvas.
- Os desenhos continuam baseados em formas simples (`fill`, `stroke`, `arc`, `ellipse`, `path`) e o volume de particulas segue limitado pelo balanceamento existente.
- Trade-off adotado: a sensacao premium veio de composicao, cor, shape e hierarquia, em vez de efeitos complexos de pos-processamento.

## Assets futuros que ainda podem elevar o visual
- Sprites autorais do player e dos inimigos para acabamento ainda mais caracteristico.
- Pequenos SFX visuais por power-up (icone exclusivo ou trail proprio para cada tipo).
- Ilustracoes dedicadas para tela inicial e resultados, caso o add-on evolua para uma identidade mais proprietaria.

## Pontos que podem ser refinados futuramente
1. Criar um set de assets 2D autorais mantendo o mesmo footprint de performance.
2. Refinar ainda mais o preview/captura do gameplay desktop para mostrar mais acao simultanea no registro visual.
3. Separar a camada de renderizacao visual da engine em modulo proprio para facilitar novas iteracoes graficas.
4. Introduzir variacoes sazonais de paleta via configuracao, sem mexer na logica central.

## Validacoes previstas nesta entrega
- `git diff --check`
- `php -l plugins/desi-pet-shower-game/includes/class-dps-game-addon.php`
- `node --check plugins/desi-pet-shower-game/assets/js/space-groomers.js`
