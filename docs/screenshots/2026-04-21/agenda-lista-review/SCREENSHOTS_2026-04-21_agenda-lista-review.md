# Agenda Lista Review — 2026-04-21

Fonte de verdade visual seguida nesta rodada: `docs/visual/`.

## Escopo

Revisão real de UI e UX da área `Lista de Atendimentos` da Agenda em `https://desi.pet/agenda-de-atendimentos/`, com foco em:

- densidade e alinhamento da aba `Operação`
- distribuição e legibilidade da aba `Detalhes`
- legibilidade do checklist expandido no mobile
- modal de serviços e consistência textual do fixture de QA

## Ajustes aplicados

- Removida a quebra agressiva de palavras nos cards informativos da Agenda.
- Compactado o resumo operacional da aba `Operação`, removendo texto redundante antes da expansão.
- Reorganizado o resumo operacional mobile para reduzir altura sem perder leitura.
- Corrigido o wrap dos labels do checklist, que estavam presos em `nowrap` no mobile.
- Refinada a microcopy da aba `Detalhes`:
  - `Observações do atendimento` -> `Observações`
  - copy de `TaxiDog` encurtada para leitura mais rápida
- Corrigido o contraste do botão `Ver operação` no estado expandido (`aria-expanded="true"`).
- Fixture de QA normalizado em produção para PT-BR correto:
  - `pet_breed`
  - `appointment_notes`
  - descrição do serviço de QA

## Arquivos alterados

- `plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`
- `plugins/desi-pet-shower-agenda/assets/css/checklist-checkin.css`
- `plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php`
- `plugins/desi-pet-shower-agenda/tests/smoke/agenda-production-smoke.spec.mjs`

## Capturas principais

- `mcp-visao-rapida-1200.png`
- `mcp-servicos-modal-1200-v2.png`
- `mcp-operacao-1200-v2.png`
- `mcp-operacao-expandido-1200-v2.png`
- `mcp-operacao-expandido-1200-v3.png`
- `mcp-detalhes-1200-v3.png`
- `mcp-operacao-expandido-375-v2.png`

## Validação desta rodada

- Produção autenticada com usuário operador via sessão temporária de QA.
- Breakpoints revisados visualmente nesta rodada:
  - `1200px`
  - `375px`
- Sem overflow horizontal detectado nas superfícies revisadas.
- Observação externa:
  - os erros de console restantes vêm do script de AdSense bloqueado por CORS e não da Agenda.

## Observações

- O header duplicado que aparece em alguns `fullPage screenshots` é efeito do cabeçalho fixo do tema durante a captura completa; não é renderização duplicada da Agenda.
- A smoke suite local foi endurecida para aceitar sessão autenticada por cookie e fallback para `Agenda completa`/`dps_date` de QA, mas o runtime local desta máquina ainda não tem o pacote `playwright` instalado para execução via Node tradicional.
