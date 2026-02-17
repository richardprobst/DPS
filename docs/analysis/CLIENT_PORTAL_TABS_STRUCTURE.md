# Estrutura de abas e conteúdo do Portal do Cliente

## Objetivo
Este documento descreve a estrutura atual das abas do Portal do Cliente (`[dps_client_portal]`) e o conteúdo renderizado em cada aba, com base na implementação ativa do add-on `desi-pet-shower-client-portal`.

## Visão geral da navegação

A navegação principal do portal é montada por um array de abas padrão e pode ser estendida por filtros/hooks.

- Filtro de extensão das abas: `dps_portal_tabs`
- Hook para inserir painéis adicionais: `dps_portal_custom_tab_panels`

### Ordem padrão das abas
1. **Início** (`inicio`)
2. **Fidelidade** (`fidelidade`)
3. **Avaliações** (`avaliacoes`)
4. **Mensagens** (`mensagens`)
5. **Agendamentos** (`agendamentos`)
6. **Histórico dos Pets** (`historico-pets`)
7. **Galeria** (`galeria`)
8. **Meus Dados** (`dados`)

## Mapa de abas e conteúdos

| Aba | ID técnico | Conteúdo principal | Funções/métodos envolvidos |
|---|---|---|---|
| Início | `inicio` | Dashboard com métricas rápidas (agendamentos, pets, mensagens, pontos), ações rápidas, próximo agendamento, pendências financeiras, solicitações recentes, resumo dos pets e sugestões contextuais. | `render_quick_overview()`, `render_quick_actions()`, `render_next_appointment()`, `render_financial_pending()`, `render_recent_requests()`, `render_pets_summary()`, `render_contextual_suggestions()` |
| Fidelidade | `fidelidade` | Painel de fidelidade com nível/progresso, pontos, créditos, indicação, explicação de funcionamento e conquistas. | `render_loyalty_panel()` |
| Avaliações | `avaliacoes` | Central de avaliações com métricas, formulário interno, CTA para Google Reviews e prova social (lista de avaliações). | `render_reviews_hub()` |
| Mensagens | `mensagens` | Central de mensagens com orientação para uso do chat flutuante em tempo real. | `render_message_center()` |
| Agendamentos | `agendamentos` | Histórico de agendamentos em tabela (data, pet, serviços, status, detalhes/checklist). | `render_appointment_history()` |
| Histórico dos Pets | `historico-pets` | Timeline de serviços por pet, com cabeçalho de métricas, suporte a múltiplos pets por sub-abas e estado vazio com CTA para contato. | `render_pets_timeline()`, `render_pet_history_header()`, `render_pet_tabs_navigation()`, `render_pet_service_timeline()` |
| Galeria | `galeria` | Galeria de fotos dos pets com cards por pet (foto ou placeholder). | `render_pet_gallery()` |
| Meus Dados | `dados` | Formulários de atualização de dados do cliente e pets + seção de preferências (canal e período preferidos). | `render_update_forms()`, `render_client_preferences()` |

## Badges e sinalizadores por aba

As abas podem exibir badges de contagem dinâmica:

- **Fidelidade**: total de pontos (quando add-on de fidelidade está ativo)
- **Mensagens**: quantidade de mensagens não lidas
- **Agendamentos**: quantidade de agendamentos futuros

## Extensibilidade (add-ons)

Além das abas padrão, o portal foi preparado para extensão:

- Alteração/adição de abas via filtro `dps_portal_tabs`
- Inserção de painéis customizados via hook `dps_portal_custom_tab_panels`
- Hooks antes/depois do conteúdo de cada aba (`dps_portal_before_*` e `dps_portal_after_*`)

## Observações de manutenção

- A estrutura documentada reflete o estado atual do arquivo de renderização principal do portal.
- Se novas abas forem criadas por add-ons, este documento deve ser atualizado para manter visibilidade do mapa de navegação completo.
