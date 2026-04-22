# Screenshots 2026-04-22 - Cadastro UX e estrutura funcional

## Contexto
- Objetivo da mudanca: revisar o cadastro publico com foco em UX funcional, estrutura de formulario moderna e uso real no contexto do pet shop.
- Ambiente: pagina oficial publicada em `https://desi.pet/cadastro-de-clientes-e-pets/`, com reenvio por SFTP e validacao direta no runtime do WordPress publicado.
- Fonte de verdade visual seguida explicitamente: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`, mantendo o cadastro restrito ao DPS Signature.

## Antes/Depois
- Antes: o formulario ja estava limpo visualmente, mas ainda tinha ordem de campos pouco usual, `porte` do pet sem obrigatoriedade, endereco em `textarea` com autocomplete acoplado e a secao final com hierarquia extra.
- Depois: o fluxo principal ficou `Nome completo -> Telefone / WhatsApp -> E-mail`, o pet principal ficou `Nome -> Especie -> Porte -> Raca`, o endereco passou para `input` compativel com autocomplete e a etapa final perdeu o titulo redundante.
- Ajuste funcional complementar: o submit agora usa uma unica trilha de validacao e envio, sem risco de o callback do reCAPTCHA forcar `form.submit()` antes da validacao local.

## Arquivos de codigo alterados nesta rodada
- `plugins/desi-pet-shower-frontend/assets/css/registration-v2.css`
- `plugins/desi-pet-shower-frontend/assets/js/registration-v2.js`
- `plugins/desi-pet-shower-frontend/includes/modules/class-dps-frontend-registration-v2-module.php`
- `plugins/desi-pet-shower-frontend/includes/validators/class-dps-form-validator.php`
- `plugins/desi-pet-shower-frontend/templates/registration/form-client-data.php`
- `plugins/desi-pet-shower-frontend/templates/registration/form-main.php`
- `plugins/desi-pet-shower-frontend/templates/registration/form-pet-data.php`

## Breakpoints validados
- `375`
- `600`
- `840`
- `1200`
- `1920`

## Capturas - cadastro publico UX
- `./registration-ux-signature-375.png`
- `./registration-ux-signature-600.png`
- `./registration-ux-signature-840.png`
- `./registration-ux-signature-1200.png`
- `./registration-ux-signature-1920.png`

## Validacao
- O HTML publicado respondeu `200` e confirmou `Telefone / WhatsApp` antes de `E-mail`.
- O campo `Endereço completo` passou a ser `input type="text"` no runtime publicado; o `textarea` antigo nao foi mais renderizado.
- O campo `Porte` passou a sair como obrigatorio no HTML publicado e o backend devolveu erro quando o submit foi feito sem esse dado.
- O titulo `Finalizar cadastro` deixou de ser renderizado, mantendo a area final mais direta.
- O reCAPTCHA nao estava ativo na pagina publicada durante esta rodada, entao a nova trilha de submit foi validada por codigo e por comportamento sem o listener antigo, mas nao por execucao real do token em producao.
- O Playwright MCP permaneceu indisponivel no ambiente local por `EPERM`; por isso as capturas foram geradas via Chrome headless.

## Rodada final - prioridade, coerencia e protecao de preenchimento
- Objetivo desta etapa: executar as recomendacoes restantes por prioridade, mantendo o formulario 100% em DPS Signature, sem remendo visual e com melhor estrutura de uso cotidiano.
- Resultado visual: hero e formulario passaram a ler como uma unica superficie, o toggle do card do pet perdeu peso de botao secundario e os disclosures agora exibem contagem de campos preenchidos em vez de copy generica.
- Resultado funcional: o frontend publicado agora serve protecao de `beforeunload`, limpeza de raca quando a especie muda para uma lista incompativel, contagem viva dos disclosures e validacao configurada por payload localizado do backend.

## Arquivos de codigo alterados nesta rodada final
- `plugins/desi-pet-shower-frontend/assets/css/registration-v2.css`
- `plugins/desi-pet-shower-frontend/assets/js/registration-v2.js`
- `plugins/desi-pet-shower-frontend/includes/modules/class-dps-frontend-registration-v2-module.php`
- `plugins/desi-pet-shower-frontend/templates/registration/form-client-data.php`
- `plugins/desi-pet-shower-frontend/templates/registration/form-main.php`
- `plugins/desi-pet-shower-frontend/templates/registration/form-pet-data.php`

## Capturas - rodada final DPS Signature
- `./registration-ux-priority-375.png`
- `./registration-ux-priority-600.png`
- `./registration-ux-priority-840.png`
- `./registration-ux-priority-1200.png`
- `./registration-ux-priority-1920.png`

## Validacao complementar - rodada final
- O HTML publicado respondeu `200` e confirmou `0 PREENCHIDOS` nos disclosures, sem `Opcional` renderizado no runtime do cadastro.
- O runtime publicado confirmou `Recolher` no card aberto do primeiro pet e preservou `type=\"text\"` no campo de endereco.
- O asset JS publicado respondeu `200` e expos `beforeunload`, `syncBreedField`, `formatDisclosureCount` e `data-dps-pet-toggle-label`, confirmando a nova base funcional publicada.
- O POST de erro controlado no runtime publicado continuou retornando erro para `pet_size` ausente, sem falso positivo de sucesso.
- O Playwright MCP continuou indisponivel por `EPERM`; a verificacao interativa ficou limitada a HTML, assets publicados, POST controlado e screenshots do Chrome headless.

## Rodada Agenda - verificacao integral DPS Signature
- Objetivo desta etapa: executar uma nova rodada completa de UI e UX da Agenda publicada, seguindo explicitamente `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md` como fonte de verdade do DPS Signature.
- Escopo revisado: shell da Agenda, navegacao de visao (`Dia`, `Semana`, `Mes`, `Agenda completa`), cards de overview, tres abas operacionais, modal do pet, modal de reagendamento e modal operacional com checklist/check-in/check-out.
- Ambiente validado: `https://desi.pet/agenda-de-atendimentos/?dps_date=2026-04-20&view=day`, com login administrativo temporario e reenvio por SFTP dos arquivos corrigidos.

## Achados corrigidos nesta rodada
- Tipografia: a Agenda estava com shell reto e volumetria correta, mas ainda herdava `Outfit` e `Source Sans 3` do tema em vez de `Sora` e `Manrope`. A causa era a ausencia dos aliases `--dps-signature-font-body` e `--dps-signature-font-display` no bundle local de fontes.
- Encoding: textos visiveis da Agenda tinham varias strings corrompidas no runtime publicado, incluindo `Confirmações`, `Próximos`, `Serviços`, `Ações`, `Observações`, `Histórico` e copys de modal/toast.
- Modal operacional: o checklist ja abria corretamente, mas ainda havia copy localizada sem acentuacao completa no subtitulo e nos fallbacks de erro.

## Arquivos de codigo alterados nesta rodada da Agenda
- `plugins/desi-pet-shower-base/assets/css/dps-signature-fonts.css`
- `plugins/desi-pet-shower-base/assets/fonts/signature/manrope-latin-ext.woff2`
- `plugins/desi-pet-shower-base/assets/fonts/signature/manrope-latin.woff2`
- `plugins/desi-pet-shower-base/assets/fonts/signature/sora-latin-ext.woff2`
- `plugins/desi-pet-shower-base/assets/fonts/signature/sora-latin.woff2`
- `plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`
- `plugins/desi-pet-shower-agenda/assets/css/checklist-checkin.css`
- `plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js`
- `plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php`
- `plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php`

## Capturas - Agenda DPS Signature
- `./agenda-signature-tab1-375.png`
- `./agenda-signature-tab1-600.png`
- `./agenda-signature-tab1-840.png`
- `./agenda-signature-tab1-1200.png`
- `./agenda-signature-tab1-1920.png`
- `./agenda-signature-tab1-desktop.png`
- `./agenda-signature-tab2-desktop.png`
- `./agenda-signature-tab3-desktop.png`
- `./agenda-signature-operation-modal.png`
- `./agenda-signature-pet-modal.png`
- `./agenda-signature-reschedule-modal.png`
- `./agenda-signature-responsive-check.json`

## Breakpoints validados - Agenda
- `375`
- `600`
- `840`
- `1200`
- `1920`

## Validacao - Agenda DPS Signature
- O runtime publicado respondeu com a tipografia esperada pelo DPS Signature: `Sora` no titulo principal da Agenda e `Manrope` nas abas, botoes e corpo operacional.
- O shell principal foi validado com `border-radius: 0px` no cabecalho e sem overflow horizontal nos breakpoints auditados.
- As tres abas publicadas passaram a renderizar labels corretos no HTML e nas capturas: `Confirmações e próximos passos`, `Checklist, check-in e cobrança` e `Logística, notas e TaxiDog`.
- O modal operacional foi validado com o subtitulo `Checklist, check-in e check-out centralizados em um único modal.` e o checklist carregado dentro do dialogo.
- O modal do pet abriu no runtime com os dados estruturados e sem quebra de layout.
- O modal de reagendamento abriu no runtime com shell reto e botao de fechar integrado ao cabecalho.
- O Playwright MCP permaneceu indisponivel por `EPERM`; por isso a rodada foi fechada com Selenium + Chrome headless autenticado, com capturas completas e leitura direta do CSS computado.

## Rodada Agenda - auditoria funcional e UX
- Objetivo desta etapa: revisar cada detalhe funcional e de UX da Agenda publicada, avaliar se a estrutura atual e adequada para uso real e propor a melhor organizacao moderna dentro do DPS Signature.
- Fonte de verdade visual seguida explicitamente: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.
- Resultado funcional: views `Dia`, `Semana`, `Mes` e `Agenda completa` responderam; as tres abas alternaram; os modais de pet, reagendamento, operacao e servicos abriram; nao houve overflow horizontal em `375`, `600`, `840`, `1200` e `1920`.
- Decisao de UX: a Agenda funciona, mas a estrutura de tres tabelas para o mesmo atendimento deve evoluir para uma lista canonica com paineis contextuais. A reestruturacao nao foi aplicada nesta rodada porque e uma mudanca ampla de UX e precisa de validacao antes da implementacao.

## Capturas - auditoria funcional e UX da Agenda
- `./agenda-ux-audit-375.png`
- `./agenda-ux-audit-600.png`
- `./agenda-ux-audit-840.png`
- `./agenda-ux-audit-1200.png`
- `./agenda-ux-audit-1920.png`
- `./agenda-ux-audit-operation-modal.png`
- `./agenda-ux-audit-pet-modal.png`
- `./agenda-ux-audit-reschedule-modal.png`
- `./agenda-ux-audit-services-modal.png`
- `./agenda-ux-functional-audit.json`

## Relatorio - auditoria funcional e UX da Agenda
- `../../qa/agenda-ux-functional-audit-2026-04-22.md`

## Protótipo funcional - Agenda operacional DPS Signature
- Objetivo desta etapa: criar uma página HTML navegável para testar na prática a estrutura recomendada antes de alterar o plugin da Agenda.
- Fonte de verdade visual seguida explicitamente: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.
- Arquivo criado: `../../layout/agenda/agenda-operacional-dps-signature-prototype.html`.
- Funcionalidades simuladas: lista canônica única, busca, filtros rápidos, alternância de visão, painel contextual, ação primária por etapa, modal de finalização com checklist obrigatório, modal operacional editável, perfil rápido do pet e ações secundárias agrupadas.
- A página foi aberta localmente no navegador padrão via `Start-Process`.

## Capturas - protótipo funcional da Agenda
- `./agenda-operacional-prototype-1440.png`
- `./agenda-operacional-prototype-375.png`

## Rodada Agenda - sincronizacao da fila operacional canonica
- Objetivo desta etapa: continuar a implementacao real da Agenda publicada, eliminando residuos temporarios do render canonico e garantindo que qualquer refresh AJAX atualize em conjunto a linha desktop, o card mobile e o inspetor contextual.
- Fonte de verdade visual seguida explicitamente: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.
- Ambiente validado: `https://desi.pet/agenda-de-atendimentos/?dps_date=2026-04-20&view=day`, com reenvio direto dos arquivos da Agenda para o runtime publicado e autenticacao administrativa temporaria apenas para verificacao.

## Arquivos de codigo alterados nesta rodada da Agenda
- `plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php`
- `plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js`

## Capturas - sincronizacao da fila operacional
- `./agenda-operational-sync-1440.png`
- `./agenda-operational-sync-375.png`
- `./agenda-operational-sync-375-card.png`
- `./agenda-operational-sync-check.json`

## Validacao - sincronizacao da fila operacional
- O runtime publicado respondeu com a fila operacional canonica ativa, mantendo `1` linha desktop e `1` card mobile para o atendimento `QA Smoke Pet`.
- A sonda AJAX autenticada respondeu com `row_html` e `card_html` no endpoint `dps_update_status`, confirmando que o backend agora devolve os dois fragmentos de markup para refresh sincronizado.
- A selecao contextual permaneceu coerente no runtime publicado: `selectedRows = 1`, `selectedCards = 1` e o inspetor continuou em `Finalizado` apos o probe autenticado.
- No mobile validado por Chrome headless, a tabela canonica ficou oculta pelo container (`tableContainerDisplay = none`) e o card operacional permaneceu visivel como superficie principal da Agenda.
- O Playwright MCP continuou indisponivel no ambiente; por isso esta rodada foi validada com Selenium + Chrome headless autenticado.

## Rodada Agenda - consolidacao final do runtime DPS Signature
- Objetivo desta etapa: concluir a implementacao publicada da Agenda para que o runtime responda apenas pela fila operacional canonica, com shell operacional nomeado no padrao DPS Signature e sem participar da navegacao antiga por abas.
- Fonte de verdade visual seguida explicitamente: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.
- Ambiente validado: `https://desi.pet/agenda-de-atendimentos/?dps_date=2026-04-20&view=day`, com deploy direto dos arquivos da Agenda, autenticacao administrativa temporaria e verificacao funcional no Chrome headless.

## Arquivos de codigo alterados na consolidacao final
- `plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php`
- `plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php`
- `plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js`
- `plugins/desi-pet-shower-agenda/assets/js/checklist-checkin.js`
- `plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`

## Capturas - consolidacao final do runtime
- `./agenda-operational-final-1440.png`
- `./agenda-operational-final-375-card.png`
- `./agenda-operational-final-check.json`

## Validacao - consolidacao final do runtime
- O shell publicado passou a renderizar `1` instancia de `.dps-agenda-operational-shell`, com `0` navegacoes `.dps-agenda-tabs-nav`, `0` botoes `.dps-agenda-tab-button` e `0` paines legados `visao-rapida`, `operacao` e `detalhes`.
- O runtime continuou coerente na superficie canonica: `1` linha desktop, `1` card mobile, `selectedRows = 1`, `selectedCards = 1` e inspetor contextual visivel em `Finalizado`.
- O modal operacional abriu no runtime publicado com o titulo `Fluxo operacional do atendimento` e corpo carregado, confirmando que a rodada final nao regrediu o fluxo de checklist/check-in/check-out.
- O backend passou a ignorar abas antigas no refresh e responder sempre com `row_html` e `card_html` da fila operacional DPS Signature, incluindo os updates disparados a partir do JS operacional e do modal.
