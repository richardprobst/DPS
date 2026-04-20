# Template de Matéria - Breaking News / Blog

Este template existe para todas as matérias informativas da Desi Pet Shower.

Ele segue o `DPS Signature`, mas com uma linguagem mais editorial que as páginas comerciais:
- hero mais jornalístico e menos promocional;
- leitura mais concentrada e mais vertical;
- trilha de leitura clara com resumo, meta, destaques e fechamento discreto;
- CTA final presente, mas sem competir com o conteúdo.

## Quando usar

Use este modelo para:
- matérias informativas;
- posts de blog;
- atualizações relevantes;
- explicações mais profundas sobre rotina, cuidados, orientações e bastidores operacionais.

Não use este modelo para:
- página principal;
- páginas de serviço;
- páginas institucionais;
- termos, política ou páginas estritamente legais.

## Estrutura recomendada

1. Hero editorial
2. Resumo rápido
3. Navegação da matéria
4. Corpo principal da leitura
5. Quote, callout, lista ou figura de apoio
6. Fechamento com próximo passo

## Blocos previstos

- `dps-editorial-hero__flag`
  - use para marcar o tipo da matéria
  - variantes disponíveis: `--breaking`, `--guide`, `--update`

- `dps-editorial-card`
  - use para sidebar, resumo, ficha da matéria e links de apoio

- `dps-editorial-block`
  - bloco principal do artigo
  - cada bloco deve tratar um assunto

- `dps-editorial-callout`
  - use para alerta, nota importante ou orientação objetiva
  - variantes disponíveis: `--alert`, `--note`

- `dps-editorial-figure`
  - área para imagem, dado visual, quadro ou foto de apoio

- `dps-editorial-quote`
  - cita um princípio, orientação ou fala curta com valor editorial

## Regra de escrita

- título forte, claro e direto;
- sempre propor um título principal recomendado para publicação;
- subtítulo curto, com promessa de leitura;
- corpo em parágrafos curtos;
- listas quando a informação realmente pedir lista;
- evitar tom de landing page;
- evitar repetir CTA ao longo do texto;
- escrever sempre em português brasileiro com acentuação correta.

## Regra de manutenção

Ao criar uma nova matéria:
- preserve a estrutura-base;
- troque slug, título, subtítulo, data, leitura estimada e links;
- ajuste a sidebar conforme o conteúdo;
- mantenha a classe `dps-site-page--materia` para herdar o visual correto;
- revise ortografia, acentuação e pontuação em português brasileiro antes de fechar;
- quando a marca aparecer no contexto de banho e tosa, use `Desi Pet Shower` ou `DPS`, nunca `Desi` isolado;
- sempre defina uma proposta de SEO junto com a matéria:
  - título principal recomendado;
  - 2 a 3 variações de título;
  - slug;
  - frase-chave principal;
  - meta description;
  - sugestão de links internos.

## Documentos complementares

- `EDITORIAL_VOICE_GUIDE.md`
  - define o tom de voz editorial das matérias
- `VISUAL_ASSET_GUIDE.md`
  - define quando usar foto, quadro ou dado visual
- `EDITORIAL_SEO_GUIDE.md`
  - define como propor título, slug, estrutura e copy com foco em SEO sem sacrificar naturalidade
