# Screenshots 2026-04-25 - Auditoria do Cadastro Add-on

## Contexto

- Objetivo: registrar baseline visual e funcional do formulario de Cadastro antes da reescrita integral proposta para DPS Signature.
- Ambiente: `https://desi.pet/cadastro/`, WordPress publicado.
- Sessao: usuario temporario criado via WP-CLI com role administrator para validar a variante autenticada/admin; usuario removido ao final.
- Referencia de design: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.

## Antes/Depois

- Antes: formulario atual ainda usa CSS legado, radius/elevacao fora do gate DPS Signature, callback proprio de Google Maps e `textarea` para endereco.
- Depois: nao houve alteracao visual implementada nesta rodada. Este registro documenta o estado auditado e os artefatos para orientar a reescrita.
- Arquivos de codigo alterados: nenhum arquivo de codigo foi alterado nesta rodada.

## Capturas

- `./cadastro-audit-admin-375.png` - Cadastro autenticado/admin em 375px.
- `./cadastro-audit-admin-600.png` - Cadastro autenticado/admin em 600px.
- `./cadastro-audit-admin-840.png` - Cadastro autenticado/admin em 840px.
- `./cadastro-audit-admin-1200.png` - Cadastro autenticado/admin em 1200px.
- `./cadastro-audit-admin-1920.png` - Cadastro autenticado/admin em 1920px.

## Evidencia funcional

- `./cadastro-audit-runtime-check.json`

Resumo:
- formulario renderizou nos cinco breakpoints;
- sem overflow horizontal detectado na coleta;
- opcoes administrativas apareceram na sessao autenticada;
- `duplicateCheck` estava ativo para admin;
- validacao em branco bloqueou o avanco da etapa 1;
- fluxo preenchido avancou ate a etapa 3, gerou resumo e habilitou submit apenas apos confirmacao;
- Google Places apresentou erro publicado porque o campo `#dps-client-address` e `TEXTAREA`, enquanto o autocomplete legado espera `HTMLInputElement`.

## Observacoes

- As capturas sao baseline de auditoria, nao evidencia de correcao visual.
- O console tambem apresentou erro de recurso externo de anuncios, tratado como ruido fora do escopo do Cadastro Add-on.
- O erro de Google Places e do escopo do Cadastro e deve ser corrigido na reescrita.
