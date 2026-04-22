# Screenshots 2026-03-13 - Agenda (header wave + centralizacao mobile/tablet)

## Contexto
- Objetivo da mudanca: centralizar os elementos do primeiro bloco do cabecalho da Agenda em Tablet e Mobile e aplicar animacao de fundo suave com linguagem visual moderna.
- Ambiente: preview local em `http://127.0.0.1:8770/docs/screenshots/2026-03-12/agenda-header-minimal-preview.html`.
- Referencia de design DPS Signature utilizada: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.

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
- Objetivo da mudanca: alinhar o botao da coluna Pet ao padrao visual DPS Signature e melhorar a UX da modal aberta por esse botao.
- Fonte de verdade visual aplicada: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.
- Preview usado: `./agenda-pet-profile-modal-preview.html`.

### Antes/Depois
- Antes: botao da coluna Pet sem metadado de acao, alvo de toque limitado e modal com estrutura simples sem foco inicial/trap de teclado e sem retorno de foco ao fechar.
- Depois: botao com hierarquia visual DPS Signature (shape, foco visivel e target maior), sem a palavra "Perfil", centralizado horizontal e verticalmente na coluna Pet, com atributos ARIA para dialog e modal em secoes (Pet/Tutor), foco controlado, fechamento consistente e responsividade para 840/600/375.
- Ajuste solicitado: modal sem os textos "Perfil do atendimento" e "Dados essenciais do pet e do tutor para agilizar o atendimento.", mantendo apenas um botao de fechar (X no cabecalho).
- Ajuste solicitado: correÃ§Ã£o de acentuaÃ§Ã£o nos textos da modal (`Perfil rÃ¡pido do pet`, `EspÃ©cie`, `RaÃ§a`, `EndereÃ§o`).
- Ajuste tÃ©cnico complementar: versÃ£o de `pet-profile-modal.js` incrementada para `1.0.1` a fim de invalidar cache do navegador e refletir imediatamente os textos acentuados.
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

---

## Atualizacao extra - Agenda (coluna Servicos + modal de Servicos)

### Contexto
- Objetivo da mudanca: centralizar o botao da coluna Servicos na aba Visao Rapida, alinhar o componente ao padrao DPS Signature e melhorar UX/UI da modal de servicos.
- Fonte de verdade visual aplicada: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.
- Preview usado: `./agenda-services-modal-preview.html`.

### Antes/Depois
- Antes: botao de Servicos sem alinhamento consistente com a coluna Pet e modal sem controle de foco/ESC/trap de teclado, com ocorrencia de caracteres corrompidos.
- Depois: botao centralizado horizontal e verticalmente com hierarquia visual DPS Signature; modal com ARIA de dialog, foco inicial, trap de teclado, retorno de foco ao gatilho, fechamento por X/overlay/ESC e textos com caracteres corrigidos.
- Arquivos de codigo alterados:
  - `plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php`
  - `plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js`
  - `plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`
  - `plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php`

### Breakpoints validados
- `1920px`
- `1200px`
- `840px`
- `600px`
- `375px`

### Capturas
- `./agenda-services-modal-1920-fullpage.png`
- `./agenda-services-modal-1200-fullpage.png`
- `./agenda-services-modal-840-fullpage.png`
- `./agenda-services-modal-600-fullpage.png`
- `./agenda-services-modal-375-fullpage.png`
