# Paginas Publicas do Site

Esta area centraliza o material que sera publicado nas paginas do WordPress do site institucional da desi.pet.

Modelo de publicacao adotado:
- o conteudo de cada pagina fica em um arquivo `page-content.html`;
- o CSS compartilhado fica em um unico arquivo para colar no CSS adicional do Flatsome;
- o manifesto central registra URL final, slug e arquivo local de cada pagina.

As bases seguem os guardrails tecnicos de `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`, que agora formalizam o sistema visual proprietario `DPS Signature` aplicado no CSS compartilhado.

## Estrutura

```text
docs/layout/site/
|-- README.md
|-- site-pages.manifest.json
|-- flatsome/
|   `-- flatsome-additional-css.css
|-- templates/
|   `-- materia-breaking-news/
|       |-- README.md
|       `-- page-content.html
`-- pages/
    |-- home/
    |   `-- page-content.html
    |-- materias/
    |   `-- cinco-sinais-ajuste-rotina-banho/
    |       `-- page-content.html
    |   `-- carrapatos-e-pulgas-no-cachorro-riscos-e-cuidados/
    |       `-- page-content.html
    |-- quem-somos/
    |   `-- page-content.html
    |-- banho-e-tosa/
    |   `-- page-content.html
    |-- perguntas-frequentes/
    |   `-- page-content.html
    |-- primeira-visita/
    |   `-- page-content.html
    |-- taxidog/
    |   `-- page-content.html
    |-- contato-e-localizacao/
    |   `-- page-content.html
    |-- frequencia-e-cuidados/
    |   `-- page-content.html
    |-- nossos-diferenciais/
    |   `-- page-content.html
    |-- regras-e-termos-de-atendimento/
    |   `-- page-content.html
    `-- politica-de-privacidade/
        `-- page-content.html
```

## Fluxo de uso no WordPress

1. Copiar o conteudo de `flatsome/flatsome-additional-css.css`.
2. Colar no CSS adicional usado pelo tema Flatsome.
3. Abrir a pagina correspondente no WordPress.
4. Colar o conteudo do respectivo `page-content.html` no editor HTML da pagina.

## Convencao adotada

- Cada pagina fica em uma pasta propria dentro de `pages/`.
- O nome da pasta usa o slug da URL final.
- O arquivo principal para publicacao e sempre `page-content.html`.
- O CSS compartilhado e unico e fica em `flatsome/flatsome-additional-css.css`.
- Todo o CSS e escopado ao wrapper `.dps-site-page` para nao vazar para o restante do tema.
- O arquivo `site-pages.manifest.json` mantem o mapeamento URL -> slug -> arquivo.
- Os templates reutilizaveis ficam em `templates/` e servem como base para novas paginas editoriais.

## Mapeamento atual

| Pagina | URL de destino | Slug local | HTML para colar |
|---|---|---|---|
| Pagina inicial | `https://desi.pet/` | `home` | `pages/home/page-content.html` |
| Materia piloto | `https://desi.pet/materias/cinco-sinais-ajuste-rotina-banho/` | `materias/cinco-sinais-ajuste-rotina-banho` | `pages/materias/cinco-sinais-ajuste-rotina-banho/page-content.html` |
| Materia - Carrapatos e pulgas | `https://desi.pet/materias/carrapatos-e-pulgas-no-cachorro-riscos-e-cuidados/` | `materias/carrapatos-e-pulgas-no-cachorro-riscos-e-cuidados` | `pages/materias/carrapatos-e-pulgas-no-cachorro-riscos-e-cuidados/page-content.html` |
| Quem somos | `https://desi.pet/quem-somos/` | `quem-somos` | `pages/quem-somos/page-content.html` |
| Banho e tosa | `https://desi.pet/banho-e-tosa/` | `banho-e-tosa` | `pages/banho-e-tosa/page-content.html` |
| Perguntas frequentes | `https://desi.pet/perguntas-frequentes/` | `perguntas-frequentes` | `pages/perguntas-frequentes/page-content.html` |
| Primeira visita | `https://desi.pet/primeira-visita/` | `primeira-visita` | `pages/primeira-visita/page-content.html` |
| TaxiDog | `https://desi.pet/taxidog/` | `taxidog` | `pages/taxidog/page-content.html` |
| Contato e localizacao | `https://desi.pet/contato-e-localizacao/` | `contato-e-localizacao` | `pages/contato-e-localizacao/page-content.html` |
| Frequencia e cuidados | `https://desi.pet/frequencia-e-cuidados/` | `frequencia-e-cuidados` | `pages/frequencia-e-cuidados/page-content.html` |
| Nossos diferenciais | `https://desi.pet/nossos-diferenciais/` | `nossos-diferenciais` | `pages/nossos-diferenciais/page-content.html` |
| Regras e termos de atendimento | `https://desi.pet/regras-e-termos-de-atendimento/` | `regras-e-termos-de-atendimento` | `pages/regras-e-termos-de-atendimento/page-content.html` |
| Politica de privacidade | `https://desi.pet/politica-de-privacidade/` | `politica-de-privacidade` | `pages/politica-de-privacidade/page-content.html` |

## Como adicionar novas paginas

1. Criar uma nova pasta em `pages/` com o slug desejado.
2. Duplicar um `page-content.html` existente.
3. Ajustar o wrapper da pagina para o novo slug.
4. Registrar a nova pagina no `site-pages.manifest.json`.
5. Atualizar este README com a URL final e o caminho local.

## Como adicionar materias informativas

1. Usar `templates/materia-breaking-news/page-content.html` como ponto de partida.
2. Duplicar a estrutura para `pages/<slug-da-materia>/page-content.html`.
3. Manter a classe base `dps-site-page dps-site-page--materia`.
4. Reescrever apenas conteudo, metadados e links relacionados, preservando a estrutura editorial.
5. Registrar a nova materia no `site-pages.manifest.json` e no preview local quando ela virar pagina real.

## Regra de manutencao

Se uma pagina precisar de ajuste visual especifico, faca a variacao no CSS compartilhado usando o modificador do wrapper da pagina, por exemplo `.dps-site-page--home` ou `.dps-site-page--quem-somos`, evitando criar multiplos arquivos CSS paralelos.
