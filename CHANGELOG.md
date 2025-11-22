# Desi Pet Shower — CHANGELOG

Este documento registra, em ordem cronológica inversa, todas as alterações lançadas do Desi Pet Shower (DPS). Mantenha-o sempre atualizado para que equipe, parceiros e clientes tenham clareza sobre evoluções, correções e impactos.

## Relação com outros documentos

Este CHANGELOG complementa e se relaciona com:
- **ANALYSIS.md**: contém detalhes arquiteturais, fluxos internos de integração e contratos de hooks entre núcleo e add-ons. Consulte-o para entender *como* o sistema funciona internamente.
- **AGENTS.md**: define políticas de versionamento, git-flow, convenções de código e obrigações de documentação. Consulte-o para entender *como* contribuir e manter o código.

Este CHANGELOG registra *o que* mudou, em qual versão e com qual impacto visível para usuários e integradores.

## Como atualizar este changelog
1. **Abra uma nova seção** para cada versão liberada, usando o formato `AAAA-MM-DD` para a data real do lançamento.
2. **Agrupe entradas por categoria**, mesmo que alguma fique vazia (remova a categoria vazia apenas se não houver conteúdo relevante).
3. **Use linguagem imperativa e concisa**, indicando impacto visível para usuários e integradores.
4. **Referencie tickets ou links**, quando útil, no final de cada item.
5. **Não liste alterações internas triviais** (refactors menores ou ajustes de estilo) a menos que afetem integrações ou documentação.

### Fluxo de release

Antes de criar uma nova versão oficial:

1. **Mover entradas de `[Unreleased]` para nova seção datada**: crie uma seção `### [AAAA-MM-DD] vX.Y.Z` e transfira todas as entradas acumuladas de `[Unreleased]` para ela.
2. **Deixar `[Unreleased]` pronto para a próxima rodada**: mantenha a seção `[Unreleased]` com categorias vazias prontas para receber novas mudanças.
3. **Conferir coerência com ANALYSIS.md e AGENTS.md**:
   - Se houve mudanças de arquitetura, criação de helpers, novos hooks ou alterações de fluxo financeiro, valide que o `ANALYSIS.md` reflete essas mudanças.
   - Se houve mudanças em políticas de versionamento, convenções de código ou estrutura de add-ons, valide que o `AGENTS.md` está atualizado.
4. **Criar tag de release**: após garantir que todos os arquivos estão consistentes, crie a tag anotada `git tag -a vX.Y.Z -m "Descrição da versão"` e publique.

## Estrutura recomendada
- Todas as versões listadas do mais recente para o mais antigo.
- Cada versão organizada por data de publicação.
- Categorias oficiais (utilize-as neste exato título e ordem quando possível):
  - Added (Adicionado)
  - Changed (Alterado)
  - Fixed (Corrigido)
  - Removed (Removido)
  - Deprecated (Depreciado)
  - Security (Segurança)
  - Refactoring (Interno) — *opcional, apenas para grandes refatorações que impactam arquitetura ou helpers globais*

## Exemplos e placeholders

### [YYYY-MM-DD] vX.Y.Z — Nome da versão (opcional)

#### Added (Adicionado)
- Adicione aqui novas funcionalidades, endpoints, páginas do painel ou comandos WP-CLI.
- Exemplo: "Implementada aba de assinaturas com integração ao gateway XPTO." (TCK-123)

#### Changed (Alterado)
- Registre alterações de comportamento, migrações de dados ou ajustes de UX.
- Exemplo: "Reordenada navegação das abas para destacar Agendamentos." (TCK-124)

#### Fixed (Corrigido)
- Liste correções de bugs, incluindo contexto e impacto.
- Exemplo: "Corrigido cálculo de taxas na tabela `dps_transacoes` em assinaturas recorrentes." (TCK-125)

#### Removed (Removido)
- Documente remoções de APIs, *hooks* ou configurações.
- Exemplo: "Removido shortcode legado `dps_old_checkout` em favor do `dps_checkout`."

#### Deprecated (Depreciado)
- Marque funcionalidades em descontinuação e a versão alvo de remoção.
- Exemplo: "Depreciada opção `dps_enable_legacy_assets`; remoção prevista para vX.Y." (TCK-126)

#### Security (Segurança)
- Registre correções de segurança, incluindo CVE/avisos internos.
- Exemplo: "Sanitização reforçada nos parâmetros de webhook `dps_webhook_token`." (TCK-127)

#### Refactoring (Interno)
- Liste apenas grandes refatorações que impactam arquitetura, estrutura de add-ons ou criação de helpers globais.
- Refatorações triviais (renomeação de variáveis, quebra de funções pequenas) devem ficar fora do changelog.
- Exemplo: "Criadas classes helper `DPS_Money_Helper`, `DPS_URL_Builder`, `DPS_Query_Helper` e `DPS_Request_Validator` para padronizar operações comuns." (TCK-128)
- Exemplo: "Documentado padrão de estrutura de arquivos para add-ons em `ANALYSIS.md` com exemplos práticos em `refactoring-examples.php`." (TCK-129)

---

### [Unreleased]

#### Added (Adicionado)
- **AI Add-on (v1.1.0)**: Campo de "Instruções adicionais" nas configurações da IA
  - Permite administrador complementar comportamento da IA sem substituir regras base de segurança
  - Campo opcional com limite de 2000 caracteres
  - Instruções adicionais são enviadas como segunda mensagem de sistema após prompt base
  - Prompt base protegido contra contradições posteriores
  - Novo método público `DPS_AI_Assistant::get_base_system_prompt()` para reutilização
- **AI Add-on (v1.2.0)**: Assistente de IA para Comunicações
  - Nova classe `DPS_AI_Message_Assistant` para gerar sugestões de mensagens
  - `DPS_AI_Message_Assistant::suggest_whatsapp_message($context)` - Gera sugestão de mensagem para WhatsApp
  - `DPS_AI_Message_Assistant::suggest_email_message($context)` - Gera sugestão de e-mail (assunto e corpo)
  - Handlers AJAX `wp_ajax_dps_ai_suggest_whatsapp_message` e `wp_ajax_dps_ai_suggest_email_message`
  - Interface JavaScript com botões de sugestão e modal de pré-visualização para e-mails
  - Suporta 6 tipos de mensagens: lembrete, confirmação, pós-atendimento, cobrança suave, cancelamento, reagendamento
  - **IMPORTANTE**: IA NUNCA envia automaticamente - apenas gera sugestões que o usuário revisa antes de enviar
  - Documentação completa em `add-ons/desi-pet-shower-ai_addon/AI_COMMUNICATIONS.md`
  - Exemplos de integração em `add-ons/desi-pet-shower-ai_addon/includes/ai-communications-examples.php`
- **Services Add-on**: Nova API pública (`DPS_Services_API`) para centralizar lógica de serviços e cálculo de preços (v1.2.0)
  - `DPS_Services_API::get_service($service_id)` - Retornar dados completos de um serviço
  - `DPS_Services_API::calculate_price($service_id, $pet_size, $context)` - Calcular preço por porte do pet
  - `DPS_Services_API::calculate_appointment_total($services_ids, $pets_ids, $context)` - Calcular total de agendamento
  - `DPS_Services_API::get_services_details($appointment_id)` - Retornar detalhes dos serviços de um agendamento
- **Services Add-on**: Endpoint AJAX `dps_get_services_details` movido da Agenda para Services (mantém compatibilidade)
- **Finance Add-on**: Nova API financeira pública (`DPS_Finance_API`) para centralizar operações de cobranças
  - `DPS_Finance_API::create_or_update_charge()` - Criar ou atualizar cobrança vinculada a agendamento
  - `DPS_Finance_API::mark_as_paid()` - Marcar cobrança como paga
  - `DPS_Finance_API::mark_as_pending()` - Reabrir cobrança como pendente
  - `DPS_Finance_API::mark_as_cancelled()` - Cancelar cobrança
  - `DPS_Finance_API::get_charge()` - Buscar dados de uma cobrança
  - `DPS_Finance_API::get_charges_by_appointment()` - Buscar todas as cobranças de um agendamento
  - `DPS_Finance_API::delete_charges_by_appointment()` - Remover cobranças ao excluir agendamento
  - `DPS_Finance_API::validate_charge_data()` - Validar dados antes de criar/atualizar
- **Finance Add-on**: Novos hooks para integração:
  - `dps_finance_charge_created` - Disparado ao criar nova cobrança
  - `dps_finance_charge_updated` - Disparado ao atualizar cobrança existente
  - `dps_finance_charges_deleted` - Disparado ao deletar cobranças de um agendamento
- **Agenda Add-on**: Verificação de dependência do Finance Add-on com aviso no admin
- **Documentação**: `FINANCE_AGENDA_REORGANIZATION_DIAGNOSTIC.md` - Diagnóstico completo da reorganização arquitetural (33KB, 7 seções)
- Criadas classes helper para melhorar qualidade e manutenibilidade do código:
  - `DPS_Money_Helper`: manipulação consistente de valores monetários, conversão formato brasileiro ↔ centavos
  - `DPS_URL_Builder`: construção padronizada de URLs de edição, exclusão, visualização e navegação
  - `DPS_Query_Helper`: consultas WP_Query reutilizáveis com filtros comuns e paginação
  - `DPS_Request_Validator`: validação centralizada de nonces, capabilities e sanitização de campos
- Criada classe `DPS_Message_Helper` para feedback visual consistente:
  - Mensagens de sucesso, erro e aviso via transients específicos por usuário
  - Exibição automática no topo das seções com remoção após visualização
  - Integrada em todos os fluxos de salvamento e exclusão (clientes, pets, agendamentos)
- Adicionado documento de análise de refatoração (`docs/refactoring/REFACTORING_ANALYSIS.md`) com identificação detalhada de problemas de código e sugestões de melhoria
- Criado arquivo de exemplos práticos (`includes/refactoring-examples.php`) demonstrando uso das classes helper e padrões de refatoração
- Implementado `register_deactivation_hook` no add-on Agenda para limpar cron job `dps_agenda_send_reminders` ao desativar
- Adicionada seção completa de "Padrões de desenvolvimento de add-ons" no `ANALYSIS.md` incluindo:
  - Estrutura de arquivos recomendada com separação de responsabilidades
  - Guia de uso correto de activation/deactivation hooks
  - Padrões de documentação com DocBlocks seguindo convenções WordPress
  - Boas práticas de prefixação, segurança, performance e integração
- Criados documentos de análise e guias de estilo:
  - `docs/visual/VISUAL_STYLE_GUIDE.md`: guia completo de cores, tipografia, componentes e ícones (450+ linhas)
  - `docs/layout/admin/ADMIN_LAYOUT_ANALYSIS.md`: análise detalhada de usabilidade das telas administrativas (600+ linhas)
  - `docs/implementation/UI_UX_IMPROVEMENTS_SUMMARY.md`: resumo executivo de melhorias implementadas
- **AI Add-on**: Novo add-on de Assistente Virtual para Portal do Cliente (v1.0.0)
  - Assistente focado EXCLUSIVAMENTE em Banho e Tosa, serviços, agendamentos, histórico e funcionalidades do DPS
  - Integração com OpenAI Chat Completions API (GPT-3.5 Turbo / GPT-4 / GPT-4 Turbo)
  - System prompt restritivo que proíbe conversas sobre política, religião, tecnologia e outros assuntos fora do contexto
  - Filtro preventivo de palavras-chave antes de chamar API (economiza custos e protege contexto)
  - Widget de chat responsivo no Portal do Cliente com estilos minimalistas DPS
  - Contexto automático incluindo dados do cliente/pet, agendamentos recentes, pendências financeiras e pontos de fidelidade
  - Endpoint AJAX `dps_ai_portal_ask` com validação de nonce e cliente logado
  - Interface administrativa para configuração (API key, modelo, temperatura, timeout, max_tokens)
  - Sistema autocontido: falhas não afetam funcionamento do Portal
  - Documentação completa em `add-ons/desi-pet-shower-ai_addon/README.md`
- **Client Portal Add-on**: Novo hook `dps_client_portal_after_content` para permitir add-ons adicionarem conteúdo ao final do portal (usado pelo AI Add-on)
  - `docs/layout/admin/ADMIN_LAYOUT_ANALYSIS.md`: análise detalhada de usabilidade e layout das telas administrativas
  - `docs/visual/VISUAL_STYLE_GUIDE.md`: guia oficial de estilo visual minimalista
  - `docs/implementation/UI_UX_IMPROVEMENTS_SUMMARY.md`: resumo das melhorias implementadas
  - `docs/layout/forms/FORMS_UX_ANALYSIS.md`: análise completa de UX dos formulários de cadastro com priorização de melhorias
- **Agenda Add-on**: Implementadas melhorias de FASE 1 e FASE 2:
  - Botão "➕ Novo Agendamento" adicionado à barra de navegação para workflow completo
  - Modal customizado para visualização de serviços (substitui alert() nativo)
  - Ícones e tooltips em links de ação (📍 Mapa, 💬 Confirmar, 💰 Cobrar)
  - Flag de pet agressivo melhorada (⚠️ com tooltip "Pet agressivo - cuidado no manejo")
  - Criados arquivos de assets: `assets/css/agenda-addon.css` e `assets/js/services-modal.js`
- **Formulários de cadastro**: Sistema completo de grid responsivo e componentes visuais:
  - Classes CSS para grid: `.dps-form-row`, `.dps-form-row--2col`, `.dps-form-row--3col`
  - Asterisco vermelho para campos obrigatórios: `.dps-required`
  - Checkbox melhorado: `.dps-checkbox-label`, `.dps-checkbox-text`
  - Upload de arquivo estilizado: `.dps-file-upload` com border dashed e hover
  - Preview de imagem antes do upload via JavaScript (FileReader API)
  - Desabilitação automática de botão submit durante salvamento (previne duplicatas)

#### Changed (Alterado)
- **Communications Add-on v0.2.0**: Arquitetura completamente reorganizada
  - Toda lógica de envio centralizada em `DPS_Communications_API`
  - Templates de mensagens com suporte a placeholders (`{client_name}`, `{pet_name}`, `{date}`, `{time}`)
  - Logs automáticos de envios via `DPS_Logger` (níveis INFO/ERROR/WARNING)
  - Funções legadas `dps_comm_send_whatsapp()` e `dps_comm_send_email()` agora delegam para API (deprecated)
- **Agenda Add-on**: Comunicações delegadas para Communications API
  - Envio de lembretes diários via `DPS_Communications_API::send_appointment_reminder()`
  - Notificações de status (finalizado/finalizado_pago) via `DPS_Communications_API::send_whatsapp()`
  - Método `format_whatsapp_number()` agora delega para `DPS_Phone_Helper` (deprecated)
  - **Mantidos**: botões de confirmação e cobrança via links wa.me (não são envios automáticos)
- **Client Portal Add-on**: Mensagens de clientes delegadas para Communications API
  - Envio de mensagens do Portal via `DPS_Communications_API::send_message_from_client()`
  - Fallback para `wp_mail()` direto se API não estiver disponível (compatibilidade retroativa)
- **Agenda Add-on**: Agora depende do Finance Add-on para funcionalidade completa de cobranças
- **Agenda Add-on**: Removida lógica financeira duplicada (~55 linhas de SQL direto)
- **Agenda Add-on**: `update_status_ajax()` agora confia na sincronização automática do Finance via hooks
- **Finance Add-on**: `cleanup_transactions_for_appointment()` agora delega para `DPS_Finance_API`
- **Finance Add-on**: Funções `dps_parse_money_br()` e `dps_format_money_br()` agora delegam para `DPS_Money_Helper` do núcleo
- **Loyalty Add-on**: Função `dps_format_money_br()` agora delega para `DPS_Money_Helper` do núcleo
- Interface administrativa completamente reformulada com design minimalista:
  - Paleta de cores reduzida e consistente (base neutra + 3 cores de status essenciais)
  - Remoção de sombras decorativas e elementos visuais desnecessários
  - Alertas simplificados com borda lateral colorida (sem pseudo-elementos ou fundos vibrantes)
  - Cores de status em tabelas mais suaves (amarelo claro, verde claro, cinza neutro, opacidade para cancelados)
- Hierarquia semântica corrigida em todas as telas do painel:
  - H1 único no topo do painel ("Painel de Gestão DPS")
  - H2 para seções principais (Cadastro de Clientes, Cadastro de Pets, etc.)
  - H3 para subseções e listagens com separação visual (borda superior + padding)
- Formulários reorganizados com agrupamento lógico de campos:
  - Formulário de clientes dividido em 4 fieldsets: Dados Pessoais, Contato, Redes Sociais, Endereço e Preferências
  - Bordas sutis (#e5e7eb) e legends descritivos para cada grupo
  - Redução de sobrecarga cognitiva através de organização visual clara
- **Formulário de Pet (Admin) completamente reestruturado**:
  - Dividido em 4 fieldsets temáticos (antes eram 17+ campos soltos):
    1. **Dados Básicos**: Nome, Cliente, Espécie, Raça, Sexo (grid 2col e 3col)
    2. **Características Físicas**: Tamanho, Peso, Data nascimento, Tipo de pelo, Cor (grid 3col e 2col)
    3. **Saúde e Comportamento**: Vacinas, Alergias, Cuidados, Notas, Checkbox "Cão agressivo ⚠️"
    4. **Foto do Pet**: Upload estilizado com preview
  - Labels melhorados: "Pelagem" → "Tipo de pelo", "Porte" → "Tamanho", "Cor" → "Cor predominante"
  - Peso com validação HTML5: `min="0.1" max="100" step="0.1"`
  - Placeholders descritivos em todos os campos (ex.: "Curto, longo, encaracolado...", "Branco, preto, caramelo...")
- **Formulário de Cliente (Admin)** aprimorado:
  - Grid 2 colunas para campos relacionados: CPF + Data nascimento, Instagram + Facebook
  - Placeholders padronizados: CPF "000.000.000-00", Telefone "(00) 00000-0000", Email "seuemail@exemplo.com"
  - Asteriscos (*) em campos obrigatórios (Nome, Telefone)
  - Input `tel` para telefone em vez de `text` genérico
  - Checkbox de autorização de foto com layout melhorado (`.dps-checkbox-label`)
- **Portal do Cliente**: Formulários alinhados ao padrão minimalista:
  - Grid responsivo em formulários de cliente e pet (2-3 colunas em desktop → 1 coluna em mobile)
  - Placeholders em todos os campos (Telefone, Email, Endereço, Instagram, Facebook, campos do pet)
  - Labels consistentes: "Pelagem" → "Tipo de pelo", "Porte" → "Tamanho"
  - Upload de foto estilizado com `.dps-file-upload` e preview JavaScript
  - Botões submit com classe `.dps-submit-btn` (largura 100% em mobile)
- Responsividade básica implementada para dispositivos móveis:
  - Tabelas com scroll horizontal em telas <768px
  - Navegação por abas em layout vertical em mobile
  - Grid de pets em coluna única em smartphones
  - Grid de formulários adaptativo: 2-3 colunas em desktop → 1 coluna em mobile @640px
  - Inputs com tamanho de fonte 16px para evitar zoom automático no iOS
  - Botões submit com largura 100% em mobile para melhor área de toque
- Documentação expandida com exemplos de como quebrar funções grandes em métodos menores e mais focados
- Estabelecidos padrões de nomenclatura mais descritiva para variáveis e funções
- Documentação do add-on Agenda atualizada para refletir limpeza de cron jobs na desativação
- **Agenda Add-on**: Navegação simplificada e melhorias visuais:
  - Botões de navegação consolidados de 7 para 6, organizados em 3 grupos lógicos
  - Navegação: [← Anterior] [Hoje] [Próximo →] | [📅 Semana] [📋 Todos] | [➕ Novo]
  - CSS extraído de inline (~487 linhas) para arquivo externo `assets/css/agenda-addon.css`
  - Border-left de status reduzida de 4px para 3px (estilo mais clean)
  - Remoção de transform: translateY(-1px) em hover dos botões (menos movimento visual)
  - Remoção de sombras decorativas (apenas bordas 1px solid)

#### Fixed (Corrigido)
- **Agenda Add-on**: Corrigido syntax error pré-existente (linha 936) com closing brace órfão e código quebrado usando variáveis indefinidas ($client_id, $pet_post, $date, $valor)
- Implementado feedback visual após todas as operações principais:
  - Mensagens de sucesso ao salvar clientes, pets e agendamentos
  - Mensagens de confirmação ao excluir registros
  - Alertas de erro quando operações falham
  - Feedback claro e imediato eliminando confusão sobre conclusão de ações
- Evitado retorno 401 e mensagem "Unauthorized" em acessos comuns ao site, aplicando a validação do webhook do Mercado Pago apenas quando a requisição traz indicadores da notificação
- Corrigido potencial problema de cron jobs órfãos ao desativar add-on Agenda
- **Formulários de cadastro**: Problemas críticos de UX resolvidos:
  - ✅ Formulário de Pet sem fieldsets (17+ campos desorganizados)
  - ✅ Campos obrigatórios sem indicação visual
  - ✅ Placeholders ausentes em CPF, telefone, email, endereço
  - ✅ Upload de foto sem preview
  - ✅ Botões de submit sem desabilitação durante processamento (risco de duplicatas)
  - ✅ Labels técnicos substituídos por termos mais claros
  - ✅ Estilos inline substituídos por classes CSS reutilizáveis

#### Deprecated (Depreciado)
- **Agenda Add-on**: Método `get_services_details_ajax()` - Lógica movida para Services Add-on (delega para `DPS_Services_API::get_services_details()`, mantém compatibilidade com fallback)
- **Agenda Add-on**: Endpoint AJAX `dps_get_services_details` agora é gerenciado pelo Services Add-on (Agenda mantém por compatibilidade)
- **Finance Add-on**: `dps_parse_money_br()` - Use `DPS_Money_Helper::parse_brazilian_format()` (retrocompatível, aviso de depreciação)
- **Finance Add-on**: `dps_format_money_br()` - Use `DPS_Money_Helper::format_to_brazilian()` (retrocompatível, aviso de depreciação)
- **Loyalty Add-on**: `dps_format_money_br()` - Use `DPS_Money_Helper::format_to_brazilian()` (retrocompatível, aviso de depreciação)
- **Agenda Add-on**: Shortcode `[dps_charges_notes]` - Use `[dps_fin_docs]` do Finance (redirect automático, mensagem de depreciação)

#### Refactoring (Interno)
- **Services Add-on**: Removido header duplicado de plugin no arquivo `dps_service/desi-pet-shower-services-addon.php` (mantém apenas no wrapper)
- **Services Add-on**: Centralização completa de lógica de serviços e cálculo de preços via `DPS_Services_API` (redução de duplicação, separação de responsabilidades)
- **Arquitetura**: Centralização completa de lógica financeira no Finance Add-on (eliminação de duplicação, redução de acoplamento)
- **Agenda Add-on**: Removidas ~55 linhas de SQL direto para `dps_transacoes` (agora usa sincronização automática via hooks do Finance)
- **Finance Add-on**: `cleanup_transactions_for_appointment()` refatorado para delegar para `DPS_Finance_API`
- **Prevenção de race conditions**: Apenas Finance escreve em dados financeiros (fonte de verdade única)
- **Melhoria de manutenibilidade**: Mudanças financeiras centralizadas em 1 lugar (Finance Add-on API pública)
- Reestruturação completa do CSS administrativo em `dps-base.css`:
  - Simplificação da classe `.dps-alert` removendo pseudo-elementos decorativos e sombras
  - Redução da paleta de cores de status de 4+ variantes para 3 cores essenciais
  - Padronização de bordas (1px ou 4px) e espaçamentos (20px padding, 32px entre seções)
  - Adição de media queries para responsividade básica (480px, 768px, 1024px breakpoints)
  - Adição de classes para grid de formulários e componentes visuais (fieldsets, upload, checkbox)
- Melhorias estruturais em `class-dps-base-frontend.php`:
  - Extração de lógica de mensagens para helper dedicado (`DPS_Message_Helper`)
  - Separação de campos de formulário em fieldsets semânticos
  - Padronização de títulos com hierarquia H1 → H2 → H3 em todas as seções
  - Adição de chamadas `display_messages()` no início de cada seção do painel
- Melhorias em páginas administrativas de add-ons:
  - Logs: organização de filtros e tabelas seguindo padrão minimalista
  - Clientes, pets e agendamentos: consistência visual com novo sistema de feedback
  - Formulários dos add-ons alinhados ao estilo visual do núcleo
- **Agenda Add-on**: Separação de responsabilidades e melhoria de arquitetura:
  - Extração de 487 linhas de CSS inline para arquivo dedicado `assets/css/agenda-addon.css`
  - Criação de componente modal reutilizável em `assets/js/services-modal.js` (acessível, com ARIA)
  - Atualização de `enqueue_assets()` para carregar CSS/JS externos (habilita cache do navegador e minificação)
  - Integração do modal com fallback para alert() caso script não esteja carregado
  - Benefícios: separação de responsabilidades, cache do navegador, minificação possível, manutenibilidade melhorada

#### Fixed (Corrigido)
- Corrigido erro fatal "Call to undefined function" ao ativar add-ons de Communications e Loyalty:
  - **Communications**: função `dps_comm_init()` era chamada antes de ser declarada (linha 214)
  - **Loyalty**: função `dps_loyalty_init()` era chamada antes de ser declarada (linha 839)
  - **Solução**: declarar funções primeiro, depois registrá-las no hook `plugins_loaded` (padrão seguido pelos demais add-ons)
  - Os add-ons agora inicializam via `add_action('plugins_loaded', 'dps_*_init')` em vez de chamada direta em escopo global

---

### [2025-11-17] v0.3.0 — Indique e Ganhe

#### Added (Adicionado)
- Criado módulo "Indique e Ganhe" no add-on de fidelidade com códigos únicos, tabela `dps_referrals`, cadastro de indicações e recompensas configuráveis por pontos ou créditos para indicador e indicado.
- Incluída seção administrativa para ativar o programa, definir limites e tipos de bonificação, além de exibir código/link de convite e status de indicações no Portal do Cliente.
- Adicionado hook `dps_finance_booking_paid` no fluxo financeiro e campo de código de indicação no cadastro público para registrar relações entre clientes.

---

### [2025-11-17] v0.2.0 — Campanhas e fidelidade

#### Added (Adicionado)
- Criado add-on `desi-pet-shower-loyalty` com programa de pontos configurável e funções globais para crédito e resgate.
- Registrado CPT `dps_campaign` com metabox de elegibilidade e rotina administrativa para identificar clientes alvo.
- Incluída tela "Campanhas & Fidelidade" no menu principal do DPS com resumo de pontos por cliente e gatilho manual de campanhas.

---

### [2024-01-15] v0.1.0 — Primeira versão pública

#### Added (Adicionado)
- Estrutura inicial do plugin base com hooks `dps_base_nav_tabs_*` e `dps_settings_*`.
- Add-on Financeiro com sincronização da tabela `dps_transacoes`.
- Guia inicial de configuração e checklist de segurança do WordPress.

#### Security (Segurança)
- Nonces aplicados em formulários de painel para evitar CSRF.
