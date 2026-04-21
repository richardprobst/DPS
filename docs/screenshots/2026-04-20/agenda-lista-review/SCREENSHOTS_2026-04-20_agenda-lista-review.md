# Agenda Lista De Atendimentos - Revisão UI/UX

Data: `2026-04-20`

Fonte de verdade visual usada nesta rodada:
- `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md`
- `docs/visual/VISUAL_STYLE_GUIDE.md`
- Web Interface Guidelines da Vercel

## Escopo
- Área `Lista de Atendimentos`
- Abas `Visão rápida`, `Operação` e `Detalhes`
- Linha expandida operacional
- Modal de serviços
- Regra de visibilidade do checklist

## Problemas encontrados
- Textos corrompidos no renderer ativo da lista, afetando labels, tooltips e botões.
- Colunas da tabela sem pesos previsíveis, causando truncamento em `Visão rápida`.
- Painel operacional e linha expandida com densidade ruim e alinhamento inconsistente.
- Checklist aparecendo antes do status permitir a etapa final.
- Ações e badges do pet sem acabamento visual consistente na malha DPS Signature.

## Correções aplicadas
- Reescrita da camada visível do renderer M3/DPS Signature para a lista, sem reaproveitar strings quebradas.
- Ajuste de largura por aba via `colgroup`, com redistribuição específica para `Visão rápida`, `Operação` e `Detalhes`.
- Refinamento da célula operacional e da linha expandida para leitura mais estável, com menos compressão lateral.
- Reorganização do checklist expandido para priorizar legibilidade dos passos e ações.
- Checklist restrito a `finalizado` e `finalizado_pago` no render principal, no resumo operacional e nas rotas AJAX.
- Limpeza de microcopy e tooltips da superfície ativa da lista.

## Arquivos afetados
- `plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php`
- `plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php`
- `plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`
- `plugins/desi-pet-shower-agenda/assets/css/checklist-checkin.css`
- `plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js`
- `plugins/desi-pet-shower-agenda/tests/smoke/agenda-production-smoke.spec.mjs`

## Evidências
Comparativo inicial:
- `pre-fix-smoke/operator-agenda-1200.png`
- `post-fix-smoke/operator-operacao-expandido.png`

Rodada final validada:
- `final-smoke-v2/operator-agenda-1200.png`
- `final-smoke-v2/operator-operacao-1200.png`
- `final-smoke-v2/operator-operacao-expandido.png`
- `final-smoke-v2/operator-operacao-375.png`
- `final-smoke-v2/operator-operacao-600.png`
- `final-smoke-v2/operator-operacao-840.png`
- `final-smoke-v2/operator-operacao-1920.png`
- `final-smoke-v2/operator-services-modal.png`
- `final-smoke-v2/admin-hub-1920.png`
- `final-smoke-v2/smoke-report.json`

## Breakpoints validados
- `375px`
- `600px`
- `840px`
- `1200px`
- `1920px`

## Validação funcional real
- Acesso de visitante bloqueado corretamente.
- Operador com `dps_manage_appointments` acessa e opera a Agenda.
- Admin continua com acesso integral.
- Alternância entre abas funcionando.
- Expansão do painel operacional funcionando em desktop e mobile.
- Modal de serviços abrindo e fechando com `Escape`.
- Checklist oculto em `pendente`.
- Checklist visível após mudança para `finalizado`.
- Sem overflow horizontal nos breakpoints obrigatórios da smoke suite.

## Observação
- O arquivo `trait-dps-agenda-renderer.php` ainda possui trechos legados antigos com texto mal codificado fora da superfície ativa desta revisão. Nesta rodada a limpeza foi concentrada no renderer realmente usado pela `Lista de Atendimentos`, para evitar ampliar escopo sem necessidade.
