# Notas de personalizacao de branding - Desi Pet Shower Game

## Objetivo
Alinhar o add-on `desi-pet-shower-game` a uma leitura clara de jogo oficial da `Desi Pet Shower`, preservando a jogabilidade atual e evitando branding invasivo.

## Textos alterados
- Nome exibido do jogo consolidado como `Desi Pet Shower: Space Groomers`.
- Tagline principal adicionada: `Banho em ordem, pet brilhando.`
- HUD ajustada para linguagem de marca:
  - `Pontos` -> `Brilho`
  - `Onda` -> `Etapa`
  - `Vida` -> `Cuidado`
  - `Missao de hoje` -> `Cuidado do dia`
  - `Especial` -> `Espuma total`
- Tela inicial revisada para tom mais amigavel e institucional da marca.
- Tela final revisada com textos mais coerentes com banho e tosa:
  - `Pontuacao` -> `Brilho final`
  - `Melhor combo` -> `Melhor embalo`
  - CTA de retry com linguagem de cuidado pet.
- Missoes, badges e toasts do gameplay receberam nomes menos genericos e mais conectados ao universo de banho e tosa.

## Elementos visuais personalizados
- Paleta refinada para o azul principal do projeto com acentos aqua e areia, mantendo coerencia com o M3 do DPS.
- Overlay inicial e overlays finais reforcados com hierarquia visual mais institucional.
- Canvas com novo pano de fundo usando bolhas, espuma e marcas sutis de pata para aproximar o tema do universo pet/grooming.
- Card do portal recebeu titulo, descricao e icone mais coerentes com a Desi Pet Shower.
- Barra especial e botoes passaram a usar acentos mais proximos da marca em vez de combinacoes mais genericas.

## Decisoes de branding
- Mantido `Space Groomers` no nome para preservar reconhecimento do minigame existente, mas agora subordinado claramente a `Desi Pet Shower`.
- Evitado uso repetitivo do nome da marca dentro da HUD e durante o gameplay para nao poluir a interface.
- Mantido o eixo arcade espacial ja existente por baixo risco, reforcando o tema de banho e tosa via copy, HUD, power-ups e detalhes do canvas.
- Optado por constantes simples em PHP para `brand name`, `display name` e `tagline`, sem criar uma camada maior de configuracoes porque isso aumentaria complexidade com baixo ganho imediato.

## Pontos ideais para assets oficiais futuros
- Inserir um selo/icone oficial da Desi Pet Shower no card do portal e na tela inicial, caso a marca disponibilize um simbolo reduzido para fundo escuro.
- Substituir o player atual por uma nave/secador oficial da marca, se houver ilustracao vetorial leve.
- Criar sprites autorais para `pulga`, `carrapato`, `bolo de pelo`, `espuma turbo` e `toalha relampago` para reduzir ainda mais a leitura de minigame generico.
- Considerar uma textura oficial de padrao pet/banho para o backdrop, desde que continue leve em mobile.
