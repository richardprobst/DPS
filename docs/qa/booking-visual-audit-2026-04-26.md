# Auditoria visual do Agendamento - 2026-04-26

## Escopo

- Superficie: pagina publicada `https://desi.pet/agendamento/`.
- Rodada: verificacao visual real, sem implementacao.
- Sessao: usuario temporario autenticado via WP-CLI, removido ao final.
- Fonte de verdade: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.
- Breakpoints: `375`, `600`, `840`, `1200`, `1920`.

## Evidencias

- `docs/screenshots/2026-04-26/booking-visual-audit-375.png`
- `docs/screenshots/2026-04-26/booking-visual-audit-600.png`
- `docs/screenshots/2026-04-26/booking-visual-audit-840.png`
- `docs/screenshots/2026-04-26/booking-visual-audit-1200.png`
- `docs/screenshots/2026-04-26/booking-visual-audit-1920.png`
- `docs/screenshots/2026-04-26/booking-visual-audit-check.json`

## Achados

### Alta - Notices do WordPress aparecem no corpo da pagina

O runtime publicado imprime mensagens `Notice: A funcao WP_Scripts::add foi chamada incorretamente` antes e depois do layout. Em `375px`, os caminhos `wp-includes/functions.php` ultrapassam a largura visual e criam quebra de apresentacao fora do padrao DPS Signature.

Impacto: polui a primeira dobra, desloca o formulario para baixo, expoe detalhe tecnico e cria overflow textual no mobile.

### Media - Painel Atribuicao usa roxo fora da paleta DPS Signature

O painel `Atribuicao` usa `rgb(245, 217, 255)`. A cor destoa dos papeis canonicos `paper`, `bone`, `line`, `sky`, `petrol`, `action`, `warning` e `danger`.

Impacto: cria um bloco que parece herdado de outro sistema visual e compete com os estados operacionais.

### Media - Tela grande subutiliza largura operacional

Em `1920px`, o conteudo principal ocupa `1140px`, equivalente a `59.4%` da viewport. A lista de servicos continua em uma coluna longa, apesar de haver largura suficiente para uma composicao operacional mais eficiente.

Impacto: aumenta rolagem, reduz velocidade de leitura e deixa a tela grande com excesso de vazio lateral.

### Media - Wrapper do CTA final tem raio fora do padrao

O container visual do botao `Salvar agendamento` usa `border-radius: 12px` em todos os breakpoints. O botao em si esta correto (`2px`), mas o wrapper cria um card macio que destoa da geometria reta DPS Signature.

Impacto: o fechamento do formulario parece menos preciso que os demais blocos.

### Baixa - Chips de preco criam ruido cromatico

Foram detectados `147` chips de preco com fundos amarelo claro, azul claro, vermelho claro e bone. A informacao e legivel, mas o volume de cores repetidas transforma tamanho/preco em decoracao visual constante.

Impacto: reduz sobriedade e compete com sinais que deveriam indicar estado/acao.

## Pontos que passaram

- Sem overflow horizontal do formulario principal nos cinco breakpoints.
- Tipografia principal usa `Sora` no H1 e `Manrope` no corpo operacional.
- Fieldsets principais preservam geometria reta (`0px`) e base `paper/bone`.
- Inputs principais mantem altura util de `44px`.
- Botao primario usa `petrol`, texto claro e raio pequeno.

## Prioridade recomendada

1. Remover os notices visiveis do WordPress e corrigir a origem do enqueue incorreto.
2. Recolorir o painel `Atribuicao` para `bone/sky` ou tratamento estrutural neutro.
3. Revisar layout de tela grande para reduzir rolagem e usar melhor a largura em `1920px`.
4. Reduzir raio do wrapper do CTA final.
5. Simplificar a linguagem cromatica dos chips de preco.
