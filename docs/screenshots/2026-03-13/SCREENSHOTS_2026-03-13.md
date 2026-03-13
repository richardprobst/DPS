# Screenshots 2026-03-13 - Agenda (header wave + centralizacao mobile/tablet)

## Contexto
- Objetivo da mudanca: centralizar os elementos do primeiro bloco do cabecalho da Agenda em Tablet e Mobile e aplicar animacao de fundo suave com linguagem visual moderna.
- Ambiente: preview local em `http://127.0.0.1:8770/docs/screenshots/2026-03-12/agenda-header-minimal-preview.html`.
- Referencia de design M3 utilizada: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.

## Antes/Depois
- Antes: no comportamento responsivo, o bloco principal do cabecalho mantinha alinhamentos mais proximos do layout desktop e o fundo usava orb motion com foco radial.
- Depois: em `<=840px` o cabecalho da Agenda centraliza titulo, periodo ativo, subtitulo e CTA; o fundo usa motion de ondas com variacao tonal suave e elegante.
- Arquivos de codigo alterados:
  - `plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`

## Breakpoints validados
- `1920px`
- `1200px`
- `840px`
- `600px`
- `375px`

## Capturas
- `./agenda-header-wave-1920-fullpage.png`
- `./agenda-header-wave-1200-fullpage.png`
- `./agenda-header-wave-840-fullpage.png`
- `./agenda-header-wave-600-fullpage.png`
- `./agenda-header-wave-375-fullpage.png`

## Observacoes
- Capturas feitas em full page para registrar comportamento completo da tela.
- O preview foi executado com estilos reais do add-on de Agenda para validar o resultado sem depender de instancia WordPress ativa.

---

## Atualizacao extra - Fix de caracteres aleatorios no estado vazio

### Contexto
- Objetivo da mudanca: remover uma sequencia de caracteres corrompidos que aparecia no bloco com texto "Nenhum atendimento encontrado." quando nao existiam atendimentos agendados.
- Causa raiz: `content` corrompido (mojibake) em pseudo-elementos CSS (`.dps-agenda-empty::before` e `.dps-summary-report > summary::before`).
- Solucao aplicada: substituicao por escapes Unicode seguros (`\1F4C5` e `\25B8`), mantendo o comportamento visual previsto.

### Arquivos de codigo alterados
- `plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`

### Preview usado
- `./agenda-empty-state-preview.html`

### Breakpoints validados
- `1920px`
- `1200px`
- `840px`
- `600px`
- `375px`

### Capturas
- `./agenda-empty-state-1920-fullpage.png`
- `./agenda-empty-state-1200-fullpage.png`
- `./agenda-empty-state-840-fullpage.png`
- `./agenda-empty-state-600-fullpage.png`
- `./agenda-empty-state-375-fullpage.png`

---

## Atualizacao extra - Agenda (coluna Pet + modal de perfil rapido)

### Contexto
- Objetivo da mudanca: alinhar o botao da coluna Pet ao padrao visual M3 e melhorar a UX da modal aberta por esse botao.
- Fonte de verdade visual aplicada: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.
- Preview usado: `./agenda-pet-profile-modal-preview.html`.

### Antes/Depois
- Antes: botao da coluna Pet sem metadado de acao, alvo de toque limitado e modal com estrutura simples sem foco inicial/trap de teclado e sem retorno de foco ao fechar.
- Depois: botao com hierarquia visual M3 (shape, foco visivel, target maior, label auxiliar "Perfil"), atributos ARIA para dialog e modal com estrutura em secoes (Pet/Tutor), foco controlado, fechamento consistente e responsividade para 840/600/375.
- Arquivos de codigo alterados:
  - `plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php`
  - `plugins/desi-pet-shower-agenda/assets/js/pet-profile-modal.js`
  - `plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`

### Breakpoints validados
- `1920px`
- `1200px`
- `840px`
- `600px`
- `375px`

### Capturas
- `./agenda-pet-profile-modal-1920-fullpage.png`
- `./agenda-pet-profile-modal-1200-fullpage.png`
- `./agenda-pet-profile-modal-840-fullpage.png`
- `./agenda-pet-profile-modal-600-fullpage.png`
- `./agenda-pet-profile-modal-375-fullpage.png`
