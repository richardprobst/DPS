# Space Groomers - Fase 4

## Objetivo
Integrar o jogo ao ecossistema DPS/desi.pet de forma leve, util e extensivel, priorizando pontos de integracao reais ja existentes no repositorio.

## Arquitetura inspecionada

### Portal do cliente
- `plugins/desi-pet-shower-client-portal/includes/class-dps-client-portal.php`
  - `apply_filters( 'dps_portal_tabs', $default_tabs, $client_id )`
  - `do_action( 'dps_portal_custom_tab_panels', $client_id, $tabs )`
  - `do_action( 'dps_portal_after_inicio_content', $client_id )`
- `plugins/desi-pet-shower-client-portal/HOOKS.md`
  - documenta o contrato oficial para tabs customizadas e superficies extras no portal

### Contexto de cliente e pet
- Cliente autenticado no portal:
  - `DPS_Portal_Session_Manager::get_instance()->get_authenticated_client_id()`
  - fallback de associacao WordPress: `user_meta dps_client_id` e `post meta client_user_id`
- Pets do cliente:
  - relacao por `owner_id` no CPT `dps_pet`
  - repositorio existente: `DPS_Pet_Repository::get_instance()->get_pets_by_client( $client_id )`
- Metadados uteis do pet encontrados:
  - `pet_species`
  - `pet_breed`
  - `pet_size`
  - `pet_birth`
  - `pet_care`

### Agendamentos e status do ecossistema
- Repositorio existente:
  - `DPS_Appointment_Repository::get_instance()->get_next_appointment_for_client( $client_id )`
  - `DPS_Appointment_Repository::get_instance()->get_last_finished_appointment_for_pet( $client_id, $pet_id )`
- Metadados relevantes:
  - `appointment_client_id`
  - `appointment_pet_id`
  - `appointment_date`
  - `appointment_status`

### Loyalty / fidelidade
- API publica existente:
  - `DPS_Loyalty_API::award_game_event_points( $client_id, $event_key )`
  - `DPS_Loyalty_API::get_points( $client_id )`
  - `DPS_Loyalty_API::get_loyalty_tier( $client_id )`
- Persistencia existente:
  - saldo em `post meta dps_loyalty_points`
  - historico em `post meta dps_loyalty_points_log`
- Contextos de jogo ja suportados pelo loyalty:
  - `game_daily_mission`
  - `game_streak_3`
  - `game_streak_7`
  - `game_first_victory`

### Jogo / persistencia atual
- Progresso canonico do jogo:
  - `post meta dps_game_progress_v1` no cliente (`dps_cliente`)
- Servicos existentes no add-on:
  - `DPS_Game_Progress_Service`
  - `DPS_Game_REST`
- Hook ja consumido:
  - `dps_portal_after_inicio_content`

## Dados disponiveis de forma consistente
- ID do cliente autenticado no portal
- nome do cliente (`get_the_title( $client_id )`)
- progresso do jogo por cliente
- lista de pets do cliente
- pet de um proximo agendamento quando houver
- status e data do proximo agendamento
- saldo e nivel de fidelidade quando o add-on Loyalty estiver ativo
- historico de eventos de loyalty para identificar recompensas do jogo

## Melhor abordagem tecnica

### Escopo escolhido
Implementar 3 integracoes de alto valor e baixo risco, todas concentradas no add-on `desi-pet-shower-game`:

1. **Aba propria do jogo no portal**
   - Registrar via `dps_portal_tabs`
   - Renderizar via `dps_portal_custom_tab_panels`
   - Mostrar perfil do jogo, recorde, streak, badges e CTA para jogar

2. **Contexto real de cliente/pet/agendamento**
   - Resolver um pet em destaque com preferencia para o pet do proximo agendamento
   - Exibir status/evento contextual simples ligado ao ecossistema:
     - proximo banho/agendamento
     - ultima visita do pet quando disponivel
     - fallback seguro para cliente sem agendamento

3. **Resumo de recompensas conectado ao loyalty**
   - Quando `DPS_Loyalty_API` estiver disponivel, exibir pontos totais, tier e pontos ganhos pelo jogo
   - Quando loyalty nao estiver ativo, manter badges locais e mensagem neutra

### Principios aplicados
- usar hooks oficiais do portal em vez de alterar diretamente o add-on de portal;
- reaproveitar APIs e meta keys ja existentes;
- manter toda logica nova dentro do add-on do jogo;
- degradar graciosamente quando client portal ou loyalty nao estiverem ativos;
- evitar novas tabelas, caches ou contratos fragis.

## Integracoes consideradas e descartadas por agora

### Ranking global simples
- Tecnicamente possivel usando `dps_game_progress_v1` de todos os clientes.
- Nao sera implementado nesta fase porque exigiria consulta global frequente sem cache, o que aumenta custo em tela de portal e nao agrega tanto quanto a integracao de perfil/contexto.

### Escrita em user meta para progresso do jogo
- Inviavel como fonte canonica neste momento.
- O ecossistema ja usa `dps_cliente` como entidade principal do portal e do loyalty; duplicar em `user_meta` criaria fonte secundaria de verdade.

### Missoes alteradas por pet/agendamento
- Possivel, mas arriscado nesta fase.
- A missao diaria atual e deterministica por data; alterar regra de progressao para depender de pet/agendamento mudaria contrato entre frontend e servidor.
- Melhor manter a missao principal como esta e adicionar apenas camada contextual/visual no portal.

### Dependencia obrigatoria do Loyalty Add-on
- Rejeitada.
- O jogo ja funciona sem loyalty e deve continuar assim.

## Dependencias opcionais e degradacao
- **Client Portal Add-on ausente**:
  - nao registrar tab nem card de portal
  - shortcode continua funcional
- **Loyalty Add-on ausente**:
  - nao exibir saldo/tier
  - manter badges locais e progresso do jogo
- **Sem pets ou sem agendamento**:
  - usar fallback de identidade do cliente
  - nao assumir que existe `appointment_pet_id`

## Resultado esperado desta fase
- o portal passa a exibir uma integracao formal do jogo, nao apenas um canvas avulso;
- o jogo ganha identidade vinculada ao cliente e ao pet real do portal;
- recompensas simbolicas ficam conectadas ao loyalty quando disponivel;
- o add-on fica preparado para futuras expansoes sem espalhar logica para outros plugins.
