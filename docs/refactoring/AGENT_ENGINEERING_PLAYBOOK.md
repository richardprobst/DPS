# Playbook de engenharia para agentes (Core + Add-ons)

Este documento complementa o `AGENTS.md` da raiz com diretrizes práticas para implementação e refatoração.
Objetivo: manter código limpo, sustentável e escalável para o plugin base e add-ons, preservando compatibilidade com WordPress.

## Princípios de implementação

1. **Clareza > esperteza**: o código deve ser óbvio para quem mantém.
2. **KISS e YAGNI**: sem abstrações sem uso real.
3. **DRY com bom senso**: evitar duplicação de regra de negócio sem criar acoplamento indevido entre add-ons.
4. **Extensibilidade**: core pequeno e estável; add-ons integram por contratos (hooks/interfaces).
5. **Segurança e performance**: tratadas como requisitos de entrega.

## Regras arquiteturais

- Regra de negócio não deve ficar em callbacks de hooks/shortcodes.
- O core deve expor contratos estáveis por:
  - interfaces PHP (uso interno), e/ou
  - actions/filters (extensões/add-ons).
- Add-ons não devem acessar internals do core por caminhos não oficiais.
- Dependências explícitas por construtor/factory; evitar espalhar singletons/globais.

## Regras WordPress (execução)

- Sempre aplicar:
  - capability checks em rotas/admin actions;
  - nonce em formulários/admin actions;
  - sanitize/validate de input e escape de output;
  - `$wpdb->prepare()` em SQL.
- Evitar:
  - consultas em loop (N+1);
  - uso indiscriminado de `wp_postmeta` para dados relacionais complexos.
- REST:
  - endpoints paginados;
  - autorização explícita;
  - payload validado.

## Padrões de código

- Nomes descritivos, sem abreviações obscuras.
- Funções pequenas, com responsabilidade única (SRP).
- Evitar “classes Deus”.
- Preferir early returns para reduzir aninhamento.
- Comentários explicam o **porquê**, não o **o quê**.

## Definition of Done (DoD)

Uma alteração é considerada pronta quando, conforme aplicável ao escopo:

- Passa em lint/PHPCS (WordPress standards) e análise estática (PHPStan no nível acordado).
- Mantém compatibilidade com versões de PHP/WordPress suportadas pelo projeto.
- Inclui testes unitários para regra de negócio (quando aplicável).
- Inclui teste de integração para endpoints/repositórios críticos (quando aplicável).
- Inclui log adequado para falhas relevantes.
- Não introduz regressões de performance perceptíveis (ex.: N+1, queries não indexáveis).
- Atualiza docs/README quando houver novo contrato/hook exposto.

## Processo recomendado para agentes

Antes de codar:
- resumir intenção e impacto (arquitetura/dados/API);
- quebrar em mudanças pequenas e revisáveis.

Ao encerrar:
- registrar trade-offs e alternativas consideradas, quando houver;
- se alguma regra precisar ser violada por motivo técnico, justificar no PR.

## Regra para demandas visuais (M3)

Quando a tarefa envolver UI, frontend ou layout:
- seguir obrigatoriamente as referências em `docs/visual/`;
- tratar `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md` como fonte de verdade do padrão visual M3;
- declarar na resposta/PR que as orientações M3 foram aplicadas;
- documentar a mudança com resumo de antes/depois e arquivos impactados;
- capturar prints completos das telas alteradas e salvar em `docs/screenshots/YYYY-MM-DD/`;
- informar no fechamento/PR o caminho exato onde os registros e prints foram salvos.

## Validação recomendada por tipo de mudança

- **Documentação apenas:** `git diff --check` e revisão de apontamentos/paths citados.
- **PHP alterado:** executar `php -l` por arquivo modificado.
- **Mudança funcional:** validar fluxo crítico no WordPress local.
- **Mudança visual (M3):** validar aderência com `docs/visual/` + registrar prints em `docs/screenshots/YYYY-MM-DD/`.

## Checklist rápido de fechamento

- Confirmar escopo da trilha (A ou B) e impactos.
- Listar testes/comandos executados e resultado.
- Informar trade-offs relevantes (quando houver).
- Para tarefas visuais: citar o caminho do documento e dos screenshots salvos.

## Conflitos e precedência

- Em conflito, prevalecem as regras da raiz em `AGENTS.md` (MUST / ASK BEFORE / segurança).
- Este playbook funciona como guia complementar para decisões de implementação.
