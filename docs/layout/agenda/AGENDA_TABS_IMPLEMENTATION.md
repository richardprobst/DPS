# Sistema de Abas da Agenda - Documentação de Implementação

**Data:** 2024-12-08  
**Versão:** 1.4.0  
**Autor:** PRObst

## Visão Geral

Este documento descreve a implementação do sistema de 3 abas na lista de agendamentos do AGENDA Add-on, criado para melhorar a organização e usabilidade da interface.

## Objetivo

Reorganizar a lista de agendamentos em 3 contextos de visualização distintos:
- **Visão Rápida**: Para consultas rápidas do dia
- **Operação**: Para ações operacionais completas
- **Detalhes**: Para informações complementares e consulta detalhada

## Estrutura das Abas

### Aba 1: Visão Rápida

**Propósito:** Visualização mais enxuta para consulta rápida de "quem vem agora".

**Colunas:**
- Horário
- Pet
- Tutor
- Status
- Confirmação (apenas badge, sem botões)
- TaxiDog (apenas se solicitado)

**Características:**
- Não exibe botões de alteração de confirmação
- Não exibe informações densas (observações, pagamento, endereço)
- TaxiDog mostra "–" quando não há solicitação
- Layout compacto com fonte menor (0.9rem)

### Aba 2: Operação

**Propósito:** Visão operacional completa com todas as ações.

**Colunas:**
- Checkbox (seleção em lote)
- Horário
- Pet
- Tutor
- Serviços
- Status (editável)
- Confirmação (badge + botões de ação)
- Pagamento (badge + botões)
- TaxiDog (badge + ações)
- Ações rápidas

**Características:**
- Mantém todos os botões de ação
- Botões de confirmação (Confirmar, Não atendeu, Cancelado, Limpar)
- Ações rápidas de status (Finalizar, Pago, Cancelar)
- Suporte completo para operações via AJAX

### Aba 3: Detalhes

**Propósito:** Informações complementares e contextuais.

**Colunas:**
- Horário
- Pet
- Tutor
- Observações do Atendimento
- Observações do Pet
- Endereço
- Mapa/GPS

**Características:**
- Foco em informações para consulta, não para ação
- Campos de texto com suporte para conteúdo mais longo
- Observações truncadas com `wp_trim_words()` (15 palavras)
- Links de mapa e GPS quando disponíveis

## Consistência Entre Abas

**Campos de Identificação Presentes em Todas as Abas:**
- Horário
- Pet
- Tutor (quando disponível)

Isso garante que o usuário sempre consiga identificar rapidamente o atendimento em qualquer aba.

## Arquitetura Técnica

### Arquivos Modificados

1. **desi-pet-shower-agenda-addon.php**
   - Adicionado sistema de navegação de abas (linhas ~1345-1365)
   - Criadas 3 closures de renderização (`$render_table_tab1`, `$render_table_tab2`, `$render_table_tab3`)
   - Renderização de 3 painéis de conteúdo com controle de visibilidade

2. **includes/trait-dps-agenda-renderer.php**
   - Novos métodos: `render_appointment_row_tab1()`, `render_appointment_row_tab2()`, `render_appointment_row_tab3()`
   - Cada método renderiza a linha do agendamento conforme estrutura da aba

3. **assets/css/agenda-addon.css**
   - Estilos para `.dps-agenda-tabs-wrapper`, `.dps-agenda-tabs-nav`
   - Estilos para `.dps-agenda-tab-button` e estados (hover, active)
   - Estilos para `.dps-tab-content` e controle de visibilidade
   - Media queries para layout responsivo em mobile

4. **assets/js/agenda-addon.js**
   - Event handler para clique em botões de aba
   - Alternância de classes `dps-tab-content--active` e `dps-agenda-tab-button--active`
   - Persistência de preferência em `sessionStorage`
   - Restauração automática da última aba visitada

### Fluxo de Navegação

1. Usuário clica em botão de aba
2. JavaScript remove classe `--active` de todos os botões e painéis
3. Adiciona classe `--active` ao botão clicado e painel correspondente
4. Salva preferência em `sessionStorage.setItem('dps_agenda_current_tab', tabId)`
5. Ao recarregar página, JS verifica `sessionStorage` e restaura última aba

### Compatibilidade com Funcionalidades Existentes

**Ações AJAX:**
- Todas as ações AJAX (status, confirmação, TaxiDog, pagamento) funcionam em todas as abas
- Respostas AJAX atualizam linhas via `data-appt-id`
- Aba 1 não possui botões de confirmação, mas recebe atualizações de badge quando ação é feita em outra aba

**Filtros e Navegação Temporal:**
- Filtros aplicados antes da renderização das abas
- Navegação de data/semana/mês mantida acima do sistema de abas
- Parâmetro `agenda_tab` na URL (futuro) para deep linking

**Agrupamento por Cliente:**
- Quando `group_by_client=1`, sistema de abas não é renderizado
- Mantém comportamento anterior com `render_grouped_by_client()`

**Modo "Todos os Atendimentos":**
- Sistema de abas funciona normalmente
- Paginação aplicada após renderização das abas

## Acessibilidade

- Navegação de abas usa atributos ARIA corretos:
  - `role="tablist"` no container de botões
  - `role="tab"` em cada botão
  - `aria-selected="true/false"` indica aba ativa
  - `aria-controls` aponta para painel correspondente
  - `role="tabpanel"` nos painéis de conteúdo

- Foco por teclado:
  - Botões de aba recebem outline visível ao focar
  - `outline: 2px solid var(--dps-accent)` com `outline-offset: 2px`

## Responsividade

**Desktop (> 768px):**
- Abas em linha horizontal
- Borda inferior para indicar aba ativa

**Mobile (≤ 768px):**
- Abas em coluna vertical
- Borda esquerda para indicar aba ativa
- Background colorido na aba ativa

## Melhorias Futuras

1. **Deep Linking:** Adicionar parâmetro `?agenda_tab=operacao` na URL para compartilhar link de aba específica
2. **Contadores:** Mostrar número de agendamentos pendentes/finalizados em cada aba
3. **Atalhos de Teclado:** Navegação entre abas com Tab/Shift+Tab ou números 1/2/3
4. **Customização:** Permitir admin escolher quais colunas aparecem em cada aba via settings
5. **Exportação:** Opção de exportar CSV apenas da aba ativa

## Impacto em Integrações

**Add-ons que Renderizam Colunas:**
- Payment Helper, TaxiDog Helper, GPS Helper continuam funcionando
- Métodos `render_*_badge()` e `render_*_button()` são chamados conforme necessário em cada aba

**Hooks Afetados:**
- Nenhum hook foi alterado ou removido
- Sistema de abas é puramente visual, não afeta lógica de negócio

**Migrações Necessárias:**
- Nenhuma migração de dados necessária
- Funcionalidade completamente retrocompatível

## Testes Recomendados

1. ✅ Navegação entre abas sem reload
2. ✅ Persistência de aba em sessionStorage
3. ✅ Ações AJAX funcionando em todas as abas
4. ✅ Filtros aplicados corretamente
5. ✅ Responsividade em mobile
6. ✅ Acessibilidade com leitor de tela
7. ✅ Compatibilidade com agrupamento por cliente

## Referências

- Análise de UX: `docs/layout/admin/ADMIN_LAYOUT_ANALYSIS.md`
- Guia de Estilo Visual: `docs/visual/VISUAL_STYLE_GUIDE.md`
- Código Principal: `plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php`
- Trait de Renderização: `plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php`
