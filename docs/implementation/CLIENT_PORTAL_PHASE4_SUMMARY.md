# Client Portal - Fase 4: Timeline de Serviços e Ações Rápidas

## Resumo da Implementação

Esta implementação adiciona funcionalidades avançadas ao Portal do Cliente, permitindo que clientes vejam o histórico de serviços dos seus pets e façam pedidos de agendamento (novo, reagendamento, cancelamento) de forma simplificada.

**Importante**: Este é um sistema de BANHO E TOSA, não clínica veterinária. Todos os agendamentos são tratados como **pedidos** que precisam de confirmação da equipe.

## Componentes Criados

### 1. Classe DPS_Portal_Pet_History

**Localização**: `includes/client-portal/class-dps-portal-pet-history.php`

Responsável por buscar e organizar histórico de serviços realizados em pets.

**Métodos principais**:
- `get_pet_service_history($pet_id, $limit)`: Retorna serviços de um pet específico
- `get_client_service_history($client_id, $limit)`: Retorna serviços de todos os pets do cliente

**Dados retornados**:
- `appointment_id`: ID do agendamento original
- `date`: Data do serviço
- `time`: Horário do serviço
- `services`: Nome(s) do(s) serviço(s) realizado(s)
- `services_array`: Array de serviços para repetição
- `observations`: Observações/recomendações
- `professional`: Profissional que atendeu
- `status`: Status do agendamento

### 2. CPT dps_appt_request

**Localização**: Registrado em `desi-pet-shower-client-portal.php`

Armazena pedidos de agendamento feitos pelos clientes.

**Metadados**:
- `request_client_id`: ID do cliente
- `request_pet_id`: ID do pet
- `request_type`: Tipo de pedido ('new', 'reschedule', 'cancel')
- `request_desired_date`: Data desejada (Y-m-d)
- `request_desired_period`: Período desejado ('morning' ou 'afternoon')
- `request_services`: Array de serviços desejados
- `request_original_appointment_id`: ID do agendamento original (para reschedule/cancel)
- `request_notes`: Observações do cliente
- `request_status`: Status do pedido ('pending', 'confirmed', 'rejected', 'adjusted')
- `request_created_at`: Data de criação
- `request_updated_at`: Data de última atualização
- `request_confirmed_date`: Data confirmada (quando status = confirmed)
- `request_confirmed_time`: Horário confirmado (quando status = confirmed)

### 3. Classe DPS_Appointment_Request_Repository

**Localização**: `includes/client-portal/repositories/class-dps-appointment-request-repository.php`

Gerencia operações CRUD de pedidos de agendamento.

**Métodos principais**:
- `create_request($data)`: Cria novo pedido
- `get_requests_by_client($client_id, $status, $limit)`: Lista pedidos do cliente
- `update_request_status($request_id, $status, $meta)`: Atualiza status do pedido
- `request_belongs_to_client($request_id, $client_id)`: Valida ownership
- `get_request_data($request_id)`: Retorna dados completos do pedido

### 4. Métodos do Renderer

**Localização**: `includes/client-portal/class-dps-portal-renderer.php`

Novos métodos de renderização adicionados:

#### `render_pet_service_timeline($pet_id, $client_id, $limit)`
Renderiza linha do tempo de serviços do pet com:
- Timeline visual com marcadores
- Cards de serviço com data, tipo, observações
- Botão "Repetir este Serviço" em cada item
- Estado vazio quando pet não tem histórico

#### `render_appointment_quick_actions($appointment, $client_id)`
Renderiza botões de ação rápida:
- "Solicitar Reagendamento"
- "Solicitar Cancelamento"

#### `render_recent_requests($client_id)`
Renderiza seção de solicitações recentes:
- Lista últimos 5 pedidos
- Indicadores visuais de status
- Exibe data/hora confirmadas

### 5. Handlers AJAX

**Localização**: `includes/client-portal/class-dps-portal-ajax-handler.php`

#### `ajax_create_appointment_request()`
Endpoint: `wp_ajax_dps_create_appointment_request`

Processa criação de pedidos de agendamento:
- Valida nonce e sessão
- Valida ownership de pet
- Sanitiza todos os inputs
- Cria pedido com status "pending"
- Retorna mensagem de sucesso diferenciada por tipo

**Parâmetros**:
- `request_type`: Tipo do pedido
- `pet_id`: ID do pet
- `desired_date`: Data desejada
- `desired_period`: Período ('morning'/'afternoon')
- `services`: Array de serviços
- `original_appointment_id`: ID original (para reschedule/cancel)
- `notes`: Observações

### 6. Interface JavaScript

**Localização**: `assets/js/client-portal.js`

**Handlers de eventos**:
- Click em `.dps-btn-reschedule` → abre modal de reagendamento
- Click em `.dps-btn-cancel` → confirma e envia cancelamento
- Click em `.dps-btn-repeat-service` → abre modal de repetição

**Funções principais**:
- `createRequestModal(type, appointmentId, petId, services)`: Cria modal dinamicamente
- `submitAppointmentRequest(data)`: Envia pedido via AJAX
- `showNotification(message, type)`: Exibe notificação visual

**Modal de pedido**:
- Campo de data (mínimo: amanhã)
- Seletor de período (manhã/tarde)
- Textarea de observações
- Aviso destacado: "Este é um pedido de agendamento"
- Validação antes de envio
- Reload automático após sucesso

### 7. Estilos CSS

**Localização**: `assets/css/client-portal.css`

Novos componentes estilizados:

#### Timeline
- `.dps-timeline`: Container da timeline
- `.dps-timeline-item`: Item individual com marcador e linha
- `.dps-timeline-marker`: Marcador circular colorido
- `.dps-timeline-content`: Card com dados do serviço

#### Request Cards
- `.dps-request-card`: Card de solicitação
- `.status-pending/confirmed/rejected`: Variações de cor por status
- `.dps-request-card__status`: Badge de status

#### Modal
- `.dps-appointment-request-modal`: Container do modal
- `.dps-modal__notice`: Aviso destacado sobre pedido
- `.dps-request-form`: Formulário de pedido

#### Responsividade
- Media query 768px para mobile
- Timeline adaptada para telas pequenas
- Ações empilhadas verticalmente

## Novos Recursos de UI

### 1. Aba "Histórico dos Pets"

Adicionada nova aba na navegação do portal que mostra:
- Timeline de serviços para cada pet do cliente
- Histórico em ordem cronológica (mais recentes primeiro)
- Botão para repetir cada serviço

### 2. Seção "Suas Solicitações Recentes"

Adicionada ao painel inicial (Início):
- Exibe últimos 5 pedidos do cliente
- Status visual com cores
- Integrada antes da seção de pendências financeiras

### 3. Ações Rápidas no Próximo Agendamento

Card de próximo agendamento agora inclui:
- Botão "Solicitar Reagendamento"
- Botão "Solicitar Cancelamento"
- Integrado ao final do card existente

## Fluxos de Uso

### Fluxo 1: Reagendar Agendamento Existente

1. Cliente visualiza próximo agendamento no dashboard
2. Clica em "Solicitar Reagendamento"
3. Modal abre com formulário
4. Escolhe nova data e período (manhã/tarde)
5. Adiciona observações (opcional)
6. Envia solicitação
7. Pedido criado com status "pending"
8. Mensagem: "Sua solicitação foi enviada. A equipe confirmará o horário."
9. Pedido aparece em "Solicitações Recentes"

### Fluxo 2: Cancelar Agendamento

1. Cliente clica em "Solicitar Cancelamento"
2. Confirma em dialog nativo
3. Pedido de cancelamento enviado automaticamente
4. Status: "pending"
5. Mensagem: "Cancelamento solicitado. Equipe pode entrar em contato."

### Fluxo 3: Repetir Serviço da Timeline

1. Cliente acessa aba "Histórico dos Pets"
2. Visualiza timeline de serviços do pet
3. Clica em "Repetir este Serviço" em serviço desejado
4. Modal abre com tipo de serviço pré-selecionado
5. Escolhe data e período
6. Envia solicitação
7. Pedido criado como tipo "new" com serviços do histórico

### Fluxo 4: Visualizar Status de Solicitações

1. Cliente acessa painel inicial
2. Seção "Suas Solicitações Recentes" mostra pedidos
3. Status visual indica:
   - Amarelo: Aguardando confirmação
   - Verde: Confirmado (exibe data/hora final)
   - Vermelho: Não aprovado
4. Cliente vê informações completas de cada pedido

## Comunicação Clara com Cliente

Todos os pontos de interação reforçam que são PEDIDOS, não confirmações:

### Textos nos Modais
- "⚠️ Importante: Este é um **pedido de agendamento**. A equipe do Banho e Tosa irá confirmar o horário final com você."

### Mensagens de Sucesso
- "Sua solicitação de agendamento foi enviada! A equipe do Banho e Tosa irá confirmar o horário com você em breve."
- "Sua solicitação de reagendamento foi enviada! A equipe do Banho e Tosa irá confirmar o novo horário com você."
- "Sua solicitação de cancelamento foi enviada! A equipe do Banho e Tosa pode entrar em contato caso necessário."

### Status nos Cards
- "Aguardando Confirmação"
- "Confirmado para: [data/hora]"
- "Não Aprovado"

## Próximos Passos para Uso Completo

### Para a Equipe Administrativa (não implementado nesta fase)

1. **Interface de Gestão de Pedidos**:
   - Lista de pedidos pendentes
   - Ação de confirmar com seleção de data/hora final
   - Ação de recusar com motivo
   - Ação de ajustar (propor data alternativa)

2. **Notificações**:
   - Email/WhatsApp para equipe quando pedido é criado
   - Email/WhatsApp para cliente quando status é atualizado

3. **Conversão de Pedido em Agendamento**:
   - Quando equipe confirma, criar agendamento real no sistema
   - Atualizar pedido com data/hora confirmadas
   - Vincular pedido ao agendamento criado

## Arquivos Modificados

1. `desi-pet-shower-client-portal.php`: Registro de CPT e includes
2. `includes/class-dps-client-portal.php`: Novos render methods e tab
3. `includes/client-portal/class-dps-portal-renderer.php`: Métodos de renderização
4. `includes/client-portal/class-dps-portal-ajax-handler.php`: Handler AJAX
5. `assets/js/client-portal.js`: Lógica de modais e AJAX
6. `assets/css/client-portal.css`: Estilos de timeline e modais

## Arquivos Criados

1. `includes/client-portal/class-dps-portal-pet-history.php`
2. `includes/client-portal/repositories/class-dps-appointment-request-repository.php`

## Compatibilidade

- PHP: 7.4+
- WordPress: 6.0+
- Navegadores: Chrome, Firefox, Safari, Edge (últimas 2 versões)
- Mobile: Totalmente responsivo (iOS Safari, Chrome Android)

## Segurança

- ✅ Nonces validados em todas requisições AJAX
- ✅ Validação de sessão/autenticação
- ✅ Validação de ownership (cliente só acessa seus dados)
- ✅ Sanitização de todos os inputs
- ✅ Escape de todas as saídas HTML
- ✅ Prepared statements para queries SQL
- ✅ Validações de data (mínimo: amanhã)

## Performance

- Timeline carregada apenas quando aba é acessada
- Limite padrão de 10 serviços por pet
- Queries otimizadas com meta_query
- Assets carregados condicionalmente
- Modais criados dinamicamente (não pré-renderizados)

## Acessibilidade

- Labels associados a inputs
- Roles e aria attributes em componentes
- Contraste de cores adequado (WCAG AA)
- Navegação por teclado funcional
- Textos de botões descritivos

## Manutenção Futura

Para expandir este sistema:

1. **Admin Interface**: Criar interface administrativa para gerenciar pedidos
2. **Notificações**: Implementar sistema de notificações automáticas
3. **Histórico de Status**: Adicionar log de mudanças de status
4. **Filtros**: Permitir filtrar pedidos por status/tipo
5. **Paginação**: Adicionar paginação na lista de solicitações
6. **Export**: Permitir exportar histórico de solicitações
