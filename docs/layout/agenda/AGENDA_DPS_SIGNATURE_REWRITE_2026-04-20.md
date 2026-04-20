# Agenda DPS Signature - Reescrita visual controlada

Data: 2026-04-20
Escopo: `plugins/desi-pet-shower-agenda`

## Decisao

A Agenda passou por historico longo de redesenhos, o que tornou a camada visual acumulada mais cara de manter do que substituir. A estrategia adotada foi uma reescrita visual controlada: substituir CSS e micro-interacoes de interface, preservando regras de negocio e contratos publicos.

## O que foi preservado

- Shortcodes, slugs e menus existentes.
- Hooks e filtros existentes.
- AJAX actions, nonces e capabilities.
- Schema, tabelas, meta keys e fluxo de dados.
- Regras de status, checklist, check-in, pagamento, TaxiDog e integracoes.

## O que foi reescrito

- `agenda-addon.css`: shell principal, tabs, tabelas, cards, badges, toasts, modais, calendario, ocupacao e estados responsivos.
- `checklist-checkin.css`: checklist operacional, check-in/check-out, itens de seguranca, retrabalho e fallback modal.
- `dashboard.css`: dashboard operacional, KPIs, tabela, capacidade e acoes.
- `agenda-admin.css`: hub admin, configuracoes, campos, notices, chips, Google integrations e formularios.

## Racional

Remendos adicionais manteriam a dependencia de seletores legados, tokens antigos e excecoes visuais. A reescrita reduz conflito entre arquivos, remove a heranca visual M3 da Agenda e estabelece um contrato de interface coerente com `docs/visual/`.

## Guardrails

- Nao criar cache.
- Nao alterar schema.
- Nao alterar assinaturas publicas.
- Nao afrouxar validacoes de nonce/capability/sanitizacao.
- Nao remover funcionalidade sem evidencia.

## Resultado esperado

- Interface operacional mais densa e legivel.
- Menos ornamento e menos dependencia de emojis.
- Status com cores semanticas e previsiveis.
- Modais e toasts com foco visivel e comportamento de teclado.
- Responsividade real nos breakpoints de referencia do DPS Signature.
