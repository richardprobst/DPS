# Análise funcional do Desi Pet Shower

## Plugin base (`plugin/desi-pet-shower-base_plugin`)
- O arquivo principal declara constantes globais, registra os *custom post types* de clientes, pets e agendamentos, carrega os ativos do frontend e expõe os *shortcodes* `[dps_base]` e `[dps_configuracoes]`, que servem como ponto de entrada para o painel e para a tela de configurações consumida pelos add-ons.【F:plugin/desi-pet-shower-base_plugin/desi-pet-shower-base.php†L19-L183】
- A classe `DPS_Base_Frontend` concentra a lógica de interface: normaliza telefones para WhatsApp, agrega dados de agendamentos multi-pet para cobranças conjuntas, monta botões de cobrança, controla salvamento/exclusão de clientes, pets e atendimentos e renderiza as abas consumidas pelos add-ons via *hooks* (`dps_base_nav_tabs_after_pets`, `dps_base_nav_tabs_after_history`, `dps_settings_nav_tabs`, etc.).【F:plugin/desi-pet-shower-base_plugin/includes/class-dps-base-frontend.php†L6-L520】
- O fluxo de formulários usa `dps_nonce` para CSRF e delega ações específicas (`save_client`, `save_pet`, `save_appointment`, `update_appointment_status`) para métodos especializados, enquanto exclusões limpam também dados financeiros relacionados quando disponíveis.【F:plugin/desi-pet-shower-base_plugin/includes/class-dps-base-frontend.php†L380-L447】

## Add-ons complementares (`add-ons/`)
### Agenda (`desi-pet-shower-agenda_addon`)
- Cria automaticamente páginas de agenda e de cobranças, registra os *shortcodes* `[dps_agenda_page]` e `[dps_charges_notes]`, entrega scripts próprios e implementa fluxos AJAX para atualização de status e inspeção de serviços associados, além de agendar lembretes diários via `dps_agenda_send_reminders`.【F:add-ons/desi-pet-shower-agenda_addon/desi-pet-shower-agenda-addon.php†L20-L125】
- O JavaScript (`agenda-addon.js`) trata interações de status, mensagens de feedback e leitura dos serviços retornados pela API do plugin base.【F:add-ons/desi-pet-shower-agenda_addon/agenda-addon.js†L1-L86】

### Backup & Restauração (`desi-pet-shower-backup_addon`)
- Adiciona a aba “Backup & Restauração” nas configurações do painel principal, gera arquivos JSON com todo o conteúdo e possibilita restauração completa, incluindo salvaguardas com *nonce* e validações de arquivo.【F:add-ons/desi-pet-shower-backup_addon/desi-pet-shower-backup-addon.php†L28-L197】

### Portal do Cliente (`desi-pet-shower-client-portal_addon`)
- A inicialização define constantes e instancia `DPS_Client_Portal`, que abre sessões próprias, gera logins para clientes recém-criados, expõe os *shortcodes* `[dps_client_portal]` e `[dps_client_login]`, registra tipos de mensagem e integra suas abas com o painel base.【F:add-ons/desi-pet-shower-client-portal_addon/desi-pet-shower-client-portal.php†L1-L37】【F:add-ons/desi-pet-shower-client-portal_addon/includes/class-dps-client-portal.php†L6-L188】
- O portal permite que clientes atualizem dados próprios/pets e paguem pendências via links do Mercado Pago reutilizando a infraestrutura do add-on de pagamentos.【F:add-ons/desi-pet-shower-client-portal_addon/includes/class-dps-client-portal.php†L131-L198】

### Financeiro (`desi-pet-shower-finance_addon`)
- Acrescenta a aba “Financeiro”, garante a criação das tabelas `dps_transacoes` e `dps_parcelas`, sincroniza alterações vindas dos agendamentos e oferece formulários para registrar, quitar (inclusive parcialmente) ou excluir transações, além de gerar documentos e shortcodes auxiliares.【F:add-ons/desi-pet-shower-finance_addon/desi-pet-shower-finance-addon.php†L19-L199】

### Pagamentos (`desi-pet-shower-payment_addon`)
- Gera links de checkout no Mercado Pago sempre que agendamentos finalizados são salvos, injeta mensagens personalizadas no WhatsApp da agenda e provê tela de configurações com token de acesso e chave PIX, além de tratar notificações de *webhook* cedo no ciclo de inicialização.【F:add-ons/desi-pet-shower-payment_addon/desi-pet-shower-payment-addon.php†L23-L200】

### Push Notifications (`desi-pet-shower-push_addon`)
- Agenda tarefas recorrentes (agenda diária, relatório financeiro diário, relatório semanal de pets inativos), renderiza aba de configurações, coleta destinatários por filtros e envia e-mails ou integrações externas via `dps_send_push_notification`/Telegram.【F:add-ons/desi-pet-shower-push_addon/desi-pet-shower-push-addon.php†L20-L200】

### Cadastro Público (`desi-pet-shower-registration_addon`)
- Cria a página de cadastro público com o shortcode `[dps_registration_form]`, expõe configurações para chave do Google Maps, sanitiza entradas e cadastra clientes/pets vinculados diretamente nos *custom post types* do plugin base.【F:add-ons/desi-pet-shower-registration_addon/desi-pet-shower-registration-addon.php†L1-L152】

### Serviços (`desi-pet-shower-services_addon`)
- Registra o *custom post type* `dps_service`, injeta abas/seções na interface principal, adiciona campos de seleção de serviços ao agendamento, salva metas auxiliares e povo a catálogo padrão na ativação, incluindo preços/duração por porte.【F:add-ons/desi-pet-shower-services_addon/dps_service/desi-pet-shower-services-addon.php†L17-L166】

### Estatísticas (`desi-pet-shower-stats_addon`)
- Disponibiliza a aba “Estatísticas” com filtros de data, listas de clientes/pets inativos, contagem de atendimentos e serviços mais recorrentes, além de métricas de espécies, raças e receita recente consultando `dps_transacoes`.【F:add-ons/desi-pet-shower-stats_addon/desi-pet-shower-stats-addon.php†L20-L200】

### Assinaturas (`desi-pet-shower-subscription_addon`)
- Define o *custom post type* `dps_subscription`, adiciona UI própria ao painel, integra-se ao módulo financeiro, cria/atualiza transações relacionadas e gera links de renovação no Mercado Pago com mensagens padrão para WhatsApp.【F:add-ons/desi-pet-shower-subscription_addon/dps_subscription/desi-pet-shower-subscription-addon.php†L16-L200】

## Considerações de estrutura e integração
- Todos os add-ons se conectam por meio dos *hooks* expostos pelo plugin base (`dps_base_nav_tabs_after_pets`, `dps_base_sections_after_history`, `dps_settings_*`), preservando a renderização centralizada de navegação/abas feita por `DPS_Base_Frontend`.【F:plugin/desi-pet-shower-base_plugin/includes/class-dps-base-frontend.php†L484-L519】
- As integrações financeiras compartilham a tabela `dps_transacoes`, seja para sincronizar agendamentos (base + financeiro), gerar cobranças (pagamentos, assinaturas) ou exibir pendências no portal e na agenda, reforçando a necessidade de manter o esquema consistente ao evoluir o sistema.【F:plugin/desi-pet-shower-base_plugin/includes/class-dps-base-frontend.php†L325-L447】【F:add-ons/desi-pet-shower-finance_addon/desi-pet-shower-finance-addon.php†L19-L199】【F:add-ons/desi-pet-shower-subscription_addon/dps_subscription/desi-pet-shower-subscription-addon.php†L26-L200】
