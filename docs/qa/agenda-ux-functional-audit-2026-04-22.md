# Auditoria funcional e UX da Agenda - 2026-04-22

## Contexto
- Escopo: Agenda publicada em `https://desi.pet/agenda-de-atendimentos/?dps_date=2026-04-20&view=day`.
- Objetivo: verificar funcionamento real, experiencia de uso, consistencia visual e melhor estrutura para uma Agenda moderna e usual.
- Fonte visual obrigatoria: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.
- Padrao permitido: somente DPS Signature. Nenhuma recomendacao desta auditoria depende de padrao visual antigo, cards arredondados, chips soltos ou remendos visuais.
- Metodo: sessao administrativa temporaria via WP-CLI, Selenium + Chrome headless autenticado, validacao em `375`, `600`, `840`, `1200` e `1920`.

## Resultado funcional
- Login administrativo temporario funcionou e permitiu validar a pagina publicada.
- Views `Dia`, `Semana`, `Mes` e `Agenda completa` responderam corretamente.
- Abas `Confirmacoes e proximos passos`, `Checklist, check-in e cobranca` e `Logistica, notas e TaxiDog` alternaram sem quebra.
- Modal do pet abriu no runtime publicado e nao quebrou o layout.
- Modal de reagendamento abriu no runtime publicado e manteve fechamento integrado ao cabecalho.
- Modal operacional abriu com checklist, check-in e check-out centralizados.
- Modal de servicos abriu corretamente pelo seletor publicado `.dps-services-popup-btn`.
- Nao houve overflow horizontal nos breakpoints auditados.
- Tipografia computada ficou coerente com DPS Signature: `Sora` no titulo e `Manrope` em corpo, tabs e controles.

## Achados prioritarios
- Alta - A arquitetura atual divide o mesmo atendimento em tres tabelas separadas. Isso resolve a organizacao tecnica, mas piora a operacao real: o usuario precisa reconstruir mentalmente o mesmo atendimento entre confirmacao, operacao e logistica. Evidencia: `plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php:3376` descreve "tres leituras do mesmo fluxo operacional" e as tres renderizacoes ficam em `3414`, `3464` e `3514`.
- Alta - No mobile, o trabalho real fica muito abaixo da primeira dobra. Em `375px`, a lista aparece depois de cabecalho, filtros, cards de resumo e tabs; o primeiro atendimento so fica acessivel apos muita rolagem. Isso deixa a Agenda correta visualmente, mas lenta para uso de loja.
- Media - A renderizacao ainda tem efeitos colaterais de dados. O renderizador legado da aba Operacao chama `update_post_meta()` durante a montagem da UI em `plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php:1105` e `1125`. Renderizar nao deve normalizar estado; isso deve ir para camada de migracao, servico ou rotina explicita de saneamento.
- Media - Os botoes de navegacao anterior/proximo ficaram com alvo de toque estreito no desktop, perto de `35px x 36px`. Para uma superficie operacional, o minimo pratico deve ser `44px x 44px`, sem aumentar peso visual.
- Media - O modal de servicos funciona, mas ainda precisa convergir para o mesmo shell modal DPS Signature dos outros modais. Hoje ele aparenta uma linguagem mais macia/arredondada do que a Agenda.
- Media - Acoes repetidas em todas as abas tornam a prioridade operacional menos clara. `Reagendar` aparece em contextos diferentes, mas a acao primaria deveria mudar conforme o estagio do atendimento.
- Baixa - Ha ruido externo de console por Mixpanel/Ads e avisos de jQuery Migrate. Nao bloqueia a Agenda, mas deve ser separado dos erros do plugin em QA.

## Estrutura recomendada
A melhor estrutura nao e continuar refinando tres tabelas independentes. A Agenda deve ter uma lista canonica de atendimentos e paineis contextuais por etapa.

### Desktop
- Manter um unico eixo principal por atendimento: horario, pet/tutor, servicos, status, financeiro, operacao, logistica e acoes.
- Transformar as abas atuais em filtros/visoes de trabalho, nao em tabelas concorrentes do mesmo dado.
- Usar uma linha canonica densa, reta e alinhada, com detalhes em drawer/modal quando necessario.
- Acao primaria por fase: confirmar, operar atendimento, finalizar, cobrar, reagendar ou revisar logistica.
- Acoes secundarias em um grupo consistente, sem duplicar botoes grandes em todas as leituras.

### Mobile
- Trocar tabela por cards operacionais compactos.
- Usar barra fixa/compacta com data, setas, view atual e contadores essenciais.
- Mostrar primeiro o proximo atendimento acionavel; metricas completas devem ficar recolhidas ou abaixo.
- Cada card deve seguir a ordem: hora/status, pet/tutor, servico principal, proxima acao, detalhes secundarios.
- Modais devem ocupar a viewport com cabecalho fixo, fechamento integrado e conteudo rolavel, sem botoes soltos.

### Fluxo operacional
- Pipeline sugerido: `A confirmar` -> `Confirmado` -> `Check-in` -> `Em atendimento` -> `Finalizado` -> `Check-out / Relatorio / Cobranca`.
- Checklist so deve abrir como obrigatorio ao finalizar atendimento, mas pode ser consultado/editado pelo modal operacional quando houver permissao.
- Check-in e check-out devem ser editaveis no mesmo modal operacional, com historico de alteracao no atendimento.
- Perfil do pet, servicos, logistica e TaxiDog devem ser detalhes auxiliares, nao colunas que competem com a acao principal.

## Proxima implementacao sugerida
- Fase 1: corrigir alvos de toque, copy restante, padronizacao do modal de servicos e remover mutacoes de `update_post_meta()` do render.
- Fase 2: criar a lista canonica de atendimentos para desktop mantendo compatibilidade com dados e hooks atuais.
- Fase 3: criar layout mobile em cards operacionais, sem tabela horizontal e sem duplicar markup inutil.
- Fase 4: consolidar modais em um unico componente visual DPS Signature para pet, servicos, operacao e reagendamento.
- Fase 5: revisar logs de alteracao de check-in/check-out/checklist e separar claramente eventos automaticos de edicoes humanas.

## Evidencias salvas
- `docs/screenshots/2026-04-22/agenda-ux-audit-375.png`
- `docs/screenshots/2026-04-22/agenda-ux-audit-600.png`
- `docs/screenshots/2026-04-22/agenda-ux-audit-840.png`
- `docs/screenshots/2026-04-22/agenda-ux-audit-1200.png`
- `docs/screenshots/2026-04-22/agenda-ux-audit-1920.png`
- `docs/screenshots/2026-04-22/agenda-ux-audit-operation-modal.png`
- `docs/screenshots/2026-04-22/agenda-ux-audit-pet-modal.png`
- `docs/screenshots/2026-04-22/agenda-ux-audit-reschedule-modal.png`
- `docs/screenshots/2026-04-22/agenda-ux-audit-services-modal.png`
- `docs/screenshots/2026-04-22/agenda-ux-functional-audit.json`

## Decisao
Nao foi feita uma reestruturacao grande de UX nesta rodada. A auditoria conclui que os fluxos funcionam, mas a estrutura ideal exige uma mudanca de arquitetura visual e operacional. Pela regra do projeto, essa mudanca deve ser validada antes da implementacao para evitar outro ajuste incremental que apenas maquie a complexidade atual.
