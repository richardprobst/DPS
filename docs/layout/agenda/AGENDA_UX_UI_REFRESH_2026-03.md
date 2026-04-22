# Revisao UX/UI da Agenda - Marco 2026

## Contexto
- Escopo: `plugins/desi-pet-shower-agenda/`
- Fonte de verdade visual (padrao DPS Signature): `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`
- Objetivo: manter a Agenda com leitura operacional clara, navegacao temporal objetiva e estado consistente entre abas, sem reintroduzir blocos legados fora do fluxo principal.

## Estado atual da interface
- Header em tres zonas: contexto do periodo, navegacao temporal e acoes principais.
- Overview cards com leitura imediata de pendencias, finalizados, cancelados e pagamentos.
- Listas organizadas por dia, com abas persistidas em URL/sessionStorage.
- Paginacao da agenda completa preservando apenas o contexto necessario da aba ativa.
- Camada visual consolidada em `assets/css/agenda-addon.css`, sem markup extra fora do shell principal.

## Ajustes consolidados
- Mantido o shell principal da Agenda sem painel operacional adicional acima da listagem.
- Removidos parametros legados de recorte operacional do shortcode e da paginacao.
- Removidos estilos e artefatos estaticos que serviam de base para remontar o bloco legado.
- Mantidos `id`, `aria-labelledby`, `hidden`, `tabindex` e navegacao por teclado coerente nas tabs.
- Exportacao CSV segue restaurando o rotulo original do botao apos sucesso ou erro.

## Responsividade
- Referencia de breakpoints do sistema: `375`, `600`, `840`, `1200` e `1920`.
- O shell atual prioriza empilhamento progressivo de acoes, overview e tabelas, evitando overflow horizontal e CTA inacessivel.

## Registro de 2026-03-23
- O bloco operacional legado foi removido de frontend, backend e documentacao de apoio.
- Previews HTML usados em revisoes anteriores foram aposentados para nao servirem como template de reimplementacao.
- Esta pagina passa a refletir apenas o shell vigente da Agenda.
