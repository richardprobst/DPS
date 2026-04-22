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
