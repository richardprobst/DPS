# Análise funcional do Desi Pet Shower

## Plugin base (`plugin/desi-pet-shower-base_plugin`)
- O arquivo principal declara constantes globais, registra os *custom post types* de clientes, pets e agendamentos, carrega os ativos do frontend e expõe os *shortcodes* `[dps_base]` e `[dps_configuracoes]`, que servem como ponto de entrada para o painel e para a tela de configurações consumida pelos add-ons.
- `includes/class-dps-cpt-helper.php` centraliza o registro de CPTs com rótulos e argumentos padrão; novos tipos devem instanciar `DPS_CPT_Helper` para herdar as opções comuns e apenas sobrescrever particularidades (ex.: capabilities customizadas ou suporte a editor).
- A classe `DPS_Base_Frontend` concentra a lógica de interface: normaliza telefones para WhatsApp, agrega dados de agendamentos multi-pet para cobranças conjuntas, monta botões de cobrança, controla salvamento/exclusão de clientes, pets e atendimentos e renderiza as abas consumidas pelos add-ons via *hooks* (`dps_base_nav_tabs_after_pets`, `dps_base_nav_tabs_after_history`, `dps_settings_nav_tabs`, etc.).
- O fluxo de formulários usa `dps_nonce` para CSRF e delega ações específicas (`save_client`, `save_pet`, `save_appointment`, `update_appointment_status`) para métodos especializados, enquanto exclusões limpam também dados financeiros relacionados quando disponíveis.
- A exclusão de agendamentos dispara o hook `dps_finance_cleanup_for_appointment`, permitindo que add-ons financeiros tratem a remoção de lançamentos vinculados sem depender de SQL no núcleo.
- A criação de tabelas do núcleo (ex.: `dps_logs`) é registrada no `register_activation_hook` e versionada via option `dps_logger_db_version`. Caso a flag de versão não exista ou esteja desatualizada, `dbDelta` é chamado uma única vez em `plugins_loaded` para alinhar o esquema, evitando consultas de verificação em todos os ciclos de `init`.

## Add-ons complementares (`add-ons/`)
### Agenda (`desi-pet-shower-agenda_addon`)
- Cria automaticamente páginas de agenda e de cobranças, registra os *shortcodes* `[dps_agenda_page]` e `[dps_charges_notes]`, entrega scripts próprios e implementa fluxos AJAX para atualização de status e inspeção de serviços associados, além de agendar lembretes diários via `dps_agenda_send_reminders`.
- O JavaScript (`agenda-addon.js`) trata interações de status, mensagens de feedback e leitura dos serviços retornados pela API do plugin base.

### Backup & Restauração (`desi-pet-shower-backup_addon`)
- Adiciona a aba "Backup & Restauração" nas configurações do painel principal, gera arquivos JSON com todo o conteúdo e possibilita restauração completa, incluindo salvaguardas com *nonce* e validações de arquivo.

### Campanhas & Fidelidade (`desi-pet-shower-loyalty_addon`)
- Mantém o programa de pontos por faturamento e agora inclui o módulo "Indique e Ganhe" com geração automática de códigos únicos por cliente, tabela dedicada `dps_referrals` criada via `dbDelta` e CRUD auxiliar para registrar indicações e recompensas.
- O módulo integra o cadastro público via `dps_registration_after_client_created` para armazenar indicações válidas e consome o novo hook financeiro `dps_finance_booking_paid` para bonificar indicador/indicado na primeira cobrança paga, aplicando pontos ou créditos conforme configuração administrativa.

### Comunicações (`desi-pet-shower-communications_addon`)
- Gerencia comunicações automatizadas via WhatsApp, SMS e e-mail para eventos do sistema (agendamentos, lembretes, pós-atendimento).
- Registra configurações específicas para cada canal de comunicação e permite personalização de mensagens por tipo de evento.
- Conecta-se aos hooks do plugin base (`dps_base_after_save_appointment`) e agenda tarefas (`dps_comm_send_appointment_reminder`, `dps_comm_send_post_service`) para envio automatizado.
- Exibe suas configurações na navegação padrão do núcleo usando `dps_settings_nav_tabs`/`dps_settings_sections`, em vez de menus próprios no admin.

### Groomers (`desi-pet-shower-groomers_addon`)
- Adiciona cadastro de profissionais (groomers) com papel de usuário dedicado `dps_groomer`.
- Permite vincular atendimentos a profissionais específicos através de campos adicionais no formulário de agendamento via hook `dps_base_appointment_fields`.
- Oferece relatórios por profissional para análise de produtividade e desempenho individual.
- Usa a navegação do painel base via `dps_base_nav_tabs_after_history`/`dps_base_sections_after_history` para cadastro e relatórios, substituindo páginas de menu próprias.

### Portal do Cliente (`desi-pet-shower-client-portal_addon`)
- A inicialização define constantes e instancia `DPS_Client_Portal`, que abre sessões próprias, gera logins para clientes recém-criados, expõe os *shortcodes* `[dps_client_portal]` e `[dps_client_login]`, registra tipos de mensagem e integra suas abas com o painel base.
- O portal permite que clientes atualizem dados próprios/pets e paguem pendências via links do Mercado Pago reutilizando a infraestrutura do add-on de pagamentos, além de exibir um bloco "Indique e Ganhe" com código, link e contagem de indicações bonificadas quando o módulo de fidelidade está ativo.

### Financeiro (`desi-pet-shower-finance_addon`)
- Acrescenta a aba "Financeiro", garante a criação das tabelas `dps_transacoes` e `dps_parcelas`, sincroniza alterações vindas dos agendamentos e oferece formulários para registrar, quitar (inclusive parcialmente) ou excluir transações, além de gerar documentos e shortcodes auxiliares.
- Consome o hook `dps_finance_cleanup_for_appointment` para remover lançamentos associados sempre que um agendamento é excluído, centralizando a limpeza financeira.

### Pagamentos (`desi-pet-shower-payment_addon`)
- Gera links de checkout no Mercado Pago sempre que agendamentos finalizados são salvos, injeta mensagens personalizadas no WhatsApp da agenda e provê tela de configurações com token de acesso e chave PIX, além de tratar notificações de *webhook* cedo no ciclo de inicialização.

### Push Notifications (`desi-pet-shower-push_addon`)
- Agenda tarefas recorrentes (agenda diária, relatório financeiro diário, relatório semanal de pets inativos), renderiza aba de configurações, coleta destinatários por filtros e envia e-mails ou integrações externas via `dps_send_push_notification`/Telegram.

### Cadastro Público (`desi-pet-shower-registration_addon`)
- Cria a página de cadastro público com o shortcode `[dps_registration_form]`, expõe configurações para chave do Google Maps, sanitiza entradas e cadastra clientes/pets vinculados diretamente nos *custom post types* do plugin base.

### Serviços (`desi-pet-shower-services_addon`)
- Registra o *custom post type* `dps_service` via `DPS_CPT_Helper`, injeta abas/seções na interface principal, adiciona campos de seleção de serviços ao agendamento, salva metas auxiliares e povoa o catálogo padrão na ativação, incluindo preços/duração por porte.

### Estatísticas (`desi-pet-shower-stats_addon`)
- Disponibiliza a aba "Estatísticas" com filtros de data, listas de clientes/pets inativos, contagem de atendimentos e serviços mais recorrentes, além de métricas de espécies, raças e receita recente consultando `dps_transacoes`.

### Estoque (`desi-pet-shower-stock_addon`)
- Controla estoque de insumos utilizados nos atendimentos através do *custom post type* `dps_stock_item`, registrado com `DPS_CPT_Helper` e capabilities específicas (`dps_manage_stock`).
- Registra movimentações de entrada e saída de produtos, incluindo baixa automática quando atendimentos são concluídos via hook `dps_base_after_save_appointment`.
- Oferece alertas de estoque baixo e relatórios de consumo por período, além de capability específica `dps_manage_stock` para controle de acesso.
- Passou a renderizar a tela de estoque como aba/seção no painel principal (`dps_base_nav_tabs_after_history`/`dps_base_sections_after_history`), removendo menus próprios no admin.

### Assinaturas (`desi-pet-shower-subscription_addon`)
- Define o *custom post type* `dps_subscription`, adiciona UI própria ao painel, integra-se ao módulo financeiro, cria/atualiza transações relacionadas e gera links de renovação no Mercado Pago com mensagens padrão para WhatsApp.

## Considerações de estrutura e integração
- Todos os add-ons se conectam por meio dos *hooks* expostos pelo plugin base (`dps_base_nav_tabs_after_pets`, `dps_base_sections_after_history`, `dps_settings_*`), preservando a renderização centralizada de navegação/abas feita por `DPS_Base_Frontend`.
- As integrações financeiras compartilham a tabela `dps_transacoes`, seja para sincronizar agendamentos (base + financeiro), gerar cobranças (pagamentos, assinaturas) ou exibir pendências no portal e na agenda, reforçando a necessidade de manter o esquema consistente ao evoluir o sistema.
