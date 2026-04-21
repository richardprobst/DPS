# desi.pet by PRObst — CHANGELOG

**Autor:** PRObst
**Site:** [www.probst.pro](https://www.probst.pro)

Este documento registra, em ordem cronológica inversa, todas as alterações lançadas do desi.pet by PRObst. Mantenha-o sempre atualizado para que equipe, parceiros e clientes tenham clareza sobre evoluções, correções e impactos.

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

**Client Portal ? Login h?brido e acesso recorrente**

- **Login por e-mail e senha no Portal do Cliente**: mantido o acesso por magic link e adicionado fluxo recorrente com usu?rio WordPress vinculado ao e-mail cadastrado no cliente.
- **Cria??o/redefini??o de senha por e-mail**: nova jornada para o cliente receber um link de configura??o de senha sem sair da tela inicial do portal.
- **Provisionamento e sincroniza??o de usu?rio do portal**: novo gerenciador `DPS_Portal_User_Manager` para vincular o cadastro do cliente ao usu?rio WordPress correto.
- **Rate limiting para solicita??es de acesso**: novo gerenciador `DPS_Portal_Rate_Limiter` aplicado aos pedidos de magic link e de senha.

**Space Groomers — Jogo Temático (Add-on)**

- **Novo add-on `desi-pet-shower-game`**: jogo "Space Groomers: Invasão das Pulgas" estilo Space Invaders para engajamento de clientes no portal.
- **Canvas + JS puro**: zero dependências pesadas, roda liso em desktop e mobile (touch controls).
- **Mecânica MVP**: 3 tipos de inimigo (Pulga, Carrapato, Bolota de Pelo), 2 power-ups (Shampoo Turbo, Toalha), 10 waves com dificuldade crescente, combo system, especial "Banho de Espuma".
- **Integração no portal**: card automático na aba Início via hook `dps_portal_after_inicio_content`.
- **Shortcode**: `[dps_space_groomers]` para uso em qualquer página WordPress.
- **Áudio**: SFX chiptune via Web Audio API (sem arquivos externos).
- **Recorde local**: pontuação salva em `localStorage` do navegador.
- **Persistencia sincronizada do Space Groomers**: progresso passa a ser salvo em `post meta` do cliente (`dps_game_progress_v1`) quando ha portal autenticado, mantendo fallback local fora do portal.
- **REST do jogo**: adicionadas rotas `dps-game/v1/progress` e `dps-game/v1/progress/sync` para leitura e merge seguro do progresso.
- **Resumo do jogo no portal**: aba Inicio agora mostra missao atual, streak, recorde, badges e ultima run usando dados sincronizados.
- **Recompensas leves no loyalty**: missao diaria, streak 3, streak 7 e primeira vitoria agora podem render pontos com idempotencia via `rewardMarkers`.


**Client Portal — Fase 4.1: Indicador de Progresso no Agendamento**

- **Progress bar (stepper)**: modal de pedido de agendamento transformado em wizard de 3 etapas — Data/Pet → Detalhes → Revisão/Confirmar. Componente reutilizável `dps-progress-bar` com círculos numerados, conectores e labels.
- **Revisão pré-envio**: Step 3 exibe resumo completo (tipo, pet, data, período, observações) antes do envio da solicitação.
- **Validação por etapa**: campos obrigatórios validados antes de prosseguir para a próxima etapa, com mensagens inline de erro (`role="alert"`).
- **Acessibilidade**: `role="progressbar"`, `aria-valuenow`, `aria-valuemax`, `aria-live="polite"` para anúncio de "Passo X de Y", `aria-required` em campos obrigatórios.
- **Responsivo**: stepper adapta-se a mobile (480px), botões empilhados verticalmente. `prefers-reduced-motion` remove animações.

**Client Portal — Fase 5.3: Seletor Rápido de Pet (Multi-pet)**

- **Pet selector**: dropdown de pet no Step 1 do modal de agendamento, visível quando cliente tem 2+ pets, com ícones de espécie (🐶/🐱/🐾). Dados de pets via `dpsPortal.clientPets`.
- **Revisão com pet**: pet selecionado aparece no resumo de revisão (Step 3). Pet pré-selecionado quando ação vem de botão com `data-pet-id`.

**Client Portal — Fase 5.5: Aba Pagamentos**

- **Nova aba "Pagamentos"**: aba dedicada no portal com badge de pendências, acessível via tab navigation.
- **Cards de resumo**: grid com cards "Pendente" (⏳) e "Pago" (✅), exibindo totais formatados e contagem de pendências.
- **Transações com parcelas**: cada transação exibida como card com data, descrição, valor, status. Cards pendentes com borda laranja, pagos com borda verde.
- **Detalhamento de parcelas**: parcelas registradas exibidas em rows com data, método de pagamento (PIX/Cartão/Dinheiro/Fidelidade) e valor. Saldo restante calculado para pendentes.
- **Botão "Pagar Agora"**: em cada transação pendente para integração com gateway.
- **Responsivo**: layout adapta-se a mobile (480px).

**Client Portal — Fase 6.4: Autenticação de Dois Fatores (2FA)**

- **2FA via e-mail**: verificação de segurança opcional com código de 6 dígitos enviado por e-mail após clicar no magic link. Habilitável em Portal → Configurações.
- **Segurança**: código hashed com `wp_hash_password()`, expiração de 10 minutos, máximo 5 tentativas (anti-brute-force). Anti-enumeration: tentativas incrementadas antes da verificação.
- **UI de verificação**: 6 inputs individuais com auto-advance entre dígitos, suporte a paste (colar código inteiro), auto-submit quando completo. E-mail ofuscado no formulário (j***@gmail.com).
- **E-mail responsivo**: template HTML com dígitos em caixas estilizadas, branding do portal.
- **Remember-me preservado**: flag de "Manter acesso" é mantida através do fluxo 2FA via transient.
- **Auditoria**: eventos `2fa_code_sent` e `2fa_verified` registrados via `DPS_Audit_Logger`.

**Base Plugin — Fase 7.1+7.2: Infraestrutura de Testes**

- **PHPUnit configurado**: `composer.json`, `phpunit.xml`, `tests/bootstrap.php` com mocks WordPress para o plugin base.
- **22 testes unitários**: 13 testes para `DPS_Money_Helper` (parse, format, cents, currency, validação) + 9 testes para `DPS_Phone_Helper` (clean, format, validate, WhatsApp).
- **Comando**: `cd plugins/desi-pet-shower-base && composer install && vendor/bin/phpunit`

**Base Plugin — Fase 2.4: Sistema de Templates**

- **Template Engine**: `DPS_Base_Template_Engine` — renderiza templates PHP com dados injetados via `extract()`, suporta override via tema em `dps-templates/base/`. Singleton com `get_instance()`, métodos `render()` e `exists()`.
- **Primeiro template**: `templates/components/client-summary-cards.php` — cards de métricas do cliente (cadastro, atendimentos, total gasto, último atendimento, pendências).
- **7 testes unitários** para o template engine (render, exists, subdirectory, XSS escaping, static content, nonexistent).
- **Total: 29 testes** passando no plugin base.

**Client Portal — Fase 8.1: Sugestões Inteligentes de Agendamento**

- **Sugestão baseada em histórico**: `DPS_Scheduling_Suggestions` analisa até 20 atendimentos por pet para calcular intervalo médio, serviços mais frequentes (top 3), dias desde último atendimento e urgência.
- **Banner no modal**: exibido no Step 1 do wizard de agendamento com 3 níveis de urgência: ⏰ Atenção (overdue/amber), 📅 Em breve (soon/blue), 💡 Sugestão (normal/cinza).
- **Auto-fill**: data sugerida preenchida automaticamente no campo de data. Botão "Usar data sugerida" para aplicar.
- **Multi-pet**: banner atualiza dinamicamente ao trocar pet no seletor, mostrando sugestão específica de cada pet.
- **Dados via JS**: `dpsPortal.schedulingSuggestions` indexado por pet_id com suggested_date, avg_interval, days_since_last, top_services, urgency.

**Documentação — Fase 7.3: Padrão de DI**

- **Seção adicionada** ao `docs/refactoring/AGENT_ENGINEERING_PLAYBOOK.md`: documenta 3 estratégias de instanciação (Singleton, Constructor Injection, Static Renderers) com exemplos e regras de quando usar cada uma.

**Documentação — Fase 8.2: Atualização Contínua**

- **ANALYSIS.md**: Portal do Cliente expandido com 2FA, payments tab, scheduling suggestions, progress bar, multi-pet selector, classes e hooks. Base Plugin: DPS_Base_Template_Engine. Hooks map: hooks do Portal Add-on adicionados.
- **FUNCTIONS_REFERENCE.md**: DPS_Portal_2FA (8 métodos), DPS_Scheduling_Suggestions (1 método), DPS_Finance_Repository (6 métodos), DPS_Base_Template_Engine (3 métodos) documentados com assinaturas, parâmetros, retornos e exemplos.
- **Table of Contents**: atualizada com novos links para DPS_Portal_2FA, DPS_Scheduling_Suggestions, DPS_Finance_Repository, DPS_Base_Template_Engine, DPS_Audit_Logger.

#### Changed (Alterado)

**Cadastro e Portal - DPS Signature**

- **Cadastro publico consolidado**: `[dps_registration_v2]` passa a ser o motor canonico do cadastro DPS Signature e `[dps_registration_form]` permanece apenas como alias de compatibilidade sobre o mesmo renderer nativo.
- **Formularios alinhados ao novo padrao visual**: cadastro publico, formularios internos de cliente/pet, acesso do portal, reset de senha e atualizacao de perfil passam a compartilhar a mesma linguagem visual DPS Signature, com foco visivel, mensagens inline e comportamento mobile-first.
- **Escopo funcional ampliado no cadastro**: o fluxo publico agora cobre tutor e pets com conjunto completo de campos, mascaras, autocomplete, multiplos pets, reCAPTCHA e confirmacao por e-mail na mesma experiencia.

**Agenda Add-on - revisao UX/UI operacional**

- **Operacao por modal compartilhado**: checklist, check-in e check-out passam a abrir no mesmo shell DPS Signature tambem quando o status do atendimento muda para `finalizado`, preservando a tabela mais limpa.
- **Perfil rapido do pet no dialog system da Agenda**: o clique no nome do pet agora reutiliza o modal compartilhado do add-on, com alinhamento e espacamento consistentes entre desktop e mobile.
- **Reagendamento alinhado ao shell principal**: o modal de reagendamento recebeu header, fechamento e espacamentos coerentes com a mesma linguagem visual da Agenda.
- **Shell da agenda**: reestruturado o cabecalho com contexto do periodo, CTAs principais e navegacao temporal mais clara.
- **Leitura operacional**: a lista foi reorganizada em paineis por dia com cards de overview, contagem por status e persistencia da aba ativa na URL e na sessao.
- **Simplificacao estrutural**: o bloco operacional legado foi aposentado para manter a Agenda alinhada ao shell principal do add-on.
- **Agenda sem criacao direta**: removido da agenda o botao **Novo agendamento** e todo o fluxo associado no add-on (modal, template e submissao AJAX do formulario).
- **Lista de Atendimentos em workspace M3**: shell, overview, tabs, linhas e microcopy da lista foram redesenhados para leitura operacional mais limpa, consistente e responsiva.
- **Cards de overview refinados**: Total, Pendentes, Finalizados, Cancelados, Atrasados, Pagamento pendente e TaxiDog agora usam superficies tonais mais claras, hierarquia forte de numero e destaque principal para o volume total.
- **CSS da Agenda consolidado**: removidas regras redundantes e estados legados nas tabs, overview e paineis operacionais, reduzindo heranca acidental e duplicacao entre `agenda-addon.css` e `checklist-checkin.css`.
- **Cards de overview compactados**: reduzida a altura, o padding interno, a escala numerica, o destaque expansivo do card Total e a iconografia decorativa para deixar a Agenda menos poluida e mais elegante.
- **Cards de overview sem respiro morto**: removido o excesso de margem superior herdado da antiga area de icones, aproximando o valor e o label do topo util do card.
- **Operacao com profundidade unica**: checklist operacional e check-in/check-out agora vivem no mesmo painel expansivel inline da aba Operacao, eliminando a quebra de contexto entre grid e modal.
- **Dialogs padronizados na Agenda**: reagendamento, historico, cobranca, confirmacoes sensiveis e retrabalho passaram a usar o mesmo dialog system do add-on, removendo `confirm()`/`alert()` do fluxo principal da lista.
**Client Portal ? Tela inicial e administra??o de logins**

- **Landing p?blica refeita no padr?o M3**: a p?gina inicial do portal agora apresenta lado a lado as op??es de link direto e e-mail com senha, com suporte contextual para WhatsApp quando o e-mail n?o estiver cadastrado.
- **Reset de senha dentro do portal**: nova tela dedicada para cria??o/redefini??o de senha, mantendo o mesmo contexto visual do acesso p?blico.
- **Admin de logins revisado**: a ?rea administrativa passou a exibir estado de magic link, estado do acesso por senha, ?ltimo login, atividade recente e a??es de sincroniza??o/envio de acesso por senha.
- **Sess?o h?brida unificada**: logins por magic link e por senha agora compartilham restaura??o de sessão, remember-me e registro de ?ltimo acesso.

**AI Add-on — Assistente Virtual no Portal do Cliente**

- **Acessibilidade**: adicionado `role="region"` e `aria-label` ao container principal, `tabindex="0"` ao header, `aria-live="polite"` na área de mensagens, `aria-label` nos botões de sugestão, `focus-visible` em todos os elementos interativos (header, FAB, sugestões, enviar, feedback).
- **Teclado**: tecla Escape recolhe o widget inline ou fecha o flutuante, retornando foco ao elemento adequado.
- **Resiliência**: timeout de 15s no AJAX com mensagem de erro específica; prevenção de envio duplo com flag `isSubmitting`.
- **Chevron**: ícone de seta agora aponta para baixo quando colapsado (indicando "expandir") e para cima quando expandido.

**Client Portal — UX/UI do Shell e Navegação por Tabs**

- **Navegação por tabs**: estado ativo mais forte (font-weight 600), scroll horizontal com snap em mobile, gradientes de overflow indicando direção de rolagem.
- **Breadcrumb dinâmico**: atualiza automaticamente o item ativo ao trocar de aba, mantendo contexto de navegação.
- **Scroll automático**: aba ativa é rolada para a área visível em dispositivos móveis.
- **Acessibilidade**: separador do breadcrumb com `aria-hidden`, suporte a `prefers-reduced-motion` na animação de troca de painel, transições CSS específicas (sem `transition: all`).
- **Espaçamento**: hierarquia visual refinada com título e breadcrumb mais compactos.

**Client Portal — Aba Início (revisão completa)**

- **Acessibilidade**: `focus-visible` adicionado a todos os elementos interativos da aba Início (overview cards, quick actions, botões de ação pet, link buttons, collapsible header, botões de agendamento, botões de pagamento, botões de sugestão).
- **Card de fidelidade**: corrigido clique no card de pontos (overview) — agora navega para a aba Fidelidade conforme esperado; suporte a Enter/Space para elementos com `role="button"`.
- **Transições CSS**: substituído `transition: all` por propriedades específicas nos componentes pet card, quick action e pet action button.

**Client Portal — Aba Fidelidade (revisão completa)**

- **Acessibilidade**: barra de progresso com `role="progressbar"`, `aria-valuenow`, `aria-valuemin`, `aria-valuemax` e `aria-label`; `focus-visible` em todos os elementos interativos (botão copiar, link ver histórico, carregar mais, botão resgatar, input de referral); campo numérico agora mantém outline no foco (era removido com `outline: none`).
- **Resiliência**: erro no carregamento de histórico agora exibe toast; botão de resgate preserva texto original após submit (era hardcoded); valor do input de resgate é clamped ao novo max após resgate bem-sucedido.
- **Clipboard**: fallback via `document.execCommand('copy')` para contextos sem HTTPS.
- **Transições CSS**: substituído `transition: all` por propriedades específicas no botão de resgate.

**Client Portal — Home autenticada refresh M3**

- **Home reestruturada**: a aba Inicio passa a combinar hero contextual, cards de overview e quick actions priorizadas, com badges e status operacionais alimentados por um snapshot unico do cliente.
- **Leitura operacional mais clara**: proximos passos, fidelidade, pendencias financeiras, mensagens e resumo do Space Groomers agora aparecem na primeira dobra com hierarquia visual mais forte e responsiva.
- **Atalhos mais resilientes**: quick actions passam a descobrir as abas disponiveis no DOM e aceitam `data-portal-nav-target`, evitando quebra quando a ordem das tabs muda ou quando add-ons adicionam novas entradas.
#### Fixed (Corrigido)

**Cadastro e Portal - robustez operacional**

- **Sem cache/transient no cadastro publico**: anti-spam, duplicate warning e estados de confirmacao passaram a operar por nonce, honeypot, timestamp e tokens persistidos, eliminando a dependencia de cache proibido no fluxo de cadastro.
- **Link de atualizacao de perfil em tempo real**: a geracao do link do portal deixa de depender de transient e passa a responder sob demanda via AJAX, mantendo o mesmo contrato externo para a operacao administrativa.
- **Assets contextuais no portal**: acesso, reset e profile update agora carregam CSS/JS dedicados por contexto, reduzindo divergencias entre o runtime publicado e o renderer local.

**Agenda Add-on - acabamento funcional e visual**

- **Modal do pet**: corrigida a abertura do perfil rapido na lista de atendimentos, removendo a quebra de layout do modal legado.
- **Modal de reagendamento**: corrigido o posicionamento do botao de fechar e o shell visual do reagendamento, eliminando o aspecto solto que deformava o dialogo.
- **Alinhamentos e overflow**: padronizados margens, alinhamentos e contencao de overflow na aba Operacao e nos dialogos, com revalidacao nos breakpoints `375`, `600`, `840`, `1200` e `1920`.
- **Check-in e check-out editaveis**: os registros operacionais agora podem ser editados sem estourar a tabela, continuam gravados no atendimento e deixam rastros em historico para auditoria.

- **Agenda vazia**: corrigida a condicao de empty state para refletir o conjunto exibido de fato e oferecer recuperacao objetiva ao usuario.
- **Paginacao e acessibilidade das tabs**: preservado apenas o contexto necessario da aba ativa ao paginar a agenda completa; os paineis agora expoem `aria-labelledby`, `hidden` e navegacao por teclado consistente.
- **Segurança**: corrigida verificação de propriedade do pet na impressão de histórico — usava meta key incorreta `pet_client_id` ao invés de `owner_id`, impedindo acesso legítimo à funcionalidade.

- **Agenda - modal de serviços**: corrigido o carregamento do modal na lista de atendimentos, com endpoint mais resiliente para dados inconsistentes e resposta JSON mesmo quando a sessão expira antes do clique.
- **Resumo do próximo agendamento**: a consulta de futuros no portal agora ordena por data/hora e ignora status concluidos ou cancelados, evitando destaque incorreto na home autenticada.
- **WhatsApp do portal**: a ação de repetir serviço deixa de depender de número hardcoded e passa a usar apenas o contato configurado, com fallback seguro quando o número não estiver disponível.
- **Agendamentos - horarios**: endurecido o carregamento de horarios com controle de concorrencia no frontend, validacao de nonce compativel no backend e fallback autenticado via REST quando `admin-ajax.php` nao responde corretamente.
- **Booking Add-on - permissao de agendamento**: a pagina dedicada passa a exigir permissao real de agendamentos antes de renderizar o formulario, evitando o estado inconsistente em que a data era selecionada mas o carregamento de horarios falhava no AJAX.
- **Space Groomers - conflito com agendamentos**: removida a saida indevida de BOM UTF-8 dos arquivos PHP do add-on e corrigido o card do portal que usava payload nao inicializado, eliminando o conflito que quebrava headers e contaminava respostas AJAX/JSON.
- **Agendamentos - selecao de pets**: unificada a compatibilidade `owner_id`/`pet_owner` no preparo e renderizacao do formulario, evitando casos em que apenas parte dos pets era exibida ao selecionar o cliente.

#### Removed (Removido)

- **Agenda Add-on**: removido definitivamente o bloco operacional legado da Agenda no frontend, backend, estilos e artefatos estaticos de apoio.

#### Security (Segurança)

**Fase 1 — Segurança Crítica (Plano de Implementação)**

- **Finance Add-on**: adicionados backticks em table identifiers e `phpcs:ignore` documentado em queries DDL (ALTER TABLE, CREATE INDEX, SHOW COLUMNS) que usam `$wpdb->prefix`. Queries `get_col`, `count_query` e `all_trans_query` agora utilizam backticks e documentação de segurança.
- **Base Plugin**: corrigida query LIKE sem `esc_like`/`prepare()` em `class-dps-base-frontend.php`. Adicionada documentação de segurança em `class-dps-logs-admin-page.php` e `uninstall.php`.
- **Backup Add-on**: migradas queries SELECT/DELETE que usavam `$ids_in` com `intval()` para padrão correto com placeholders dinâmicos e `$wpdb->prepare()`. Queries LIKE agora usam `$wpdb->prepare()`.
- **AI Add-on**: adicionados backticks e documentação de segurança em queries COUNT/MIN em `class-dps-ai-maintenance.php` e `class-dps-ai-analytics.php`.
- **Services Add-on**: sanitização imediata de arrays `$_POST` (`appointment_extra_names`, `appointment_extra_prices`) com `sanitize_text_field()` e `wp_unslash()`.
- **Auditoria**: criado documento completo de auditoria em `docs/security/AUDIT_FASE1.md` com mapeamento de todas as queries, nonces, capabilities, REST permissions e sanitização de entrada.

#### Refactoring (Interno)

**Cadastro e Portal - fundacao compartilhada**

- **Fundacao unica de formularios**: criada a camada compartilhada `dps-signature-forms.css/js` no base plugin para concentrar tokens, estados de campo, mascara, autocomplete, disclosures e comportamentos reutilizados por cadastro, portal e formularios internos.
- **Reescrita estrutural sem wrapper legado**: o alias `[dps_registration_form]` foi reduzido a compatibilidade de entrada, enquanto o motor nativo do frontend assumiu a renderizacao e o pipeline efetivo do cadastro publico.
- **Portal profile update desacoplado de inline code**: template, CSS e JavaScript do update de perfil foram extraidos para assets dedicados, removendo scripts/estilos inline e bridges temporarias.

**Agenda Add-on - fluxo operacional consolidado**

- **Renderer, AJAX e JavaScript reorganizados**: a aba Operacao passa a reutilizar um unico modal DPS Signature, reduzindo acoplamento entre tabela, modais avulsos e paineis operacionais legados.

**Fase 2 — Refatoração Estrutural (Plano de Implementação)**

- **Decomposição do monólito**: extraídas 9 classes de `class-dps-base-frontend.php` (5.986 → 1.581 linhas, –74%): `DPS_Client_Handler` (184L), `DPS_Pet_Handler` (337L), `DPS_Appointment_Handler` (810L), `DPS_Client_Page_Renderer` (1.506L, 23 métodos), `DPS_Breed_Registry` (201L, dataset de raças por espécie), `DPS_History_Section_Renderer` (481L, seção de histórico), `DPS_Appointments_Section_Renderer` (926L, seção de agendamentos com formulário e listagem), `DPS_Clients_Section_Renderer` (270L, seção de clientes com filtros e estatísticas), `DPS_Pets_Section_Renderer` (345L, seção de pets com filtros e paginação). Cada classe encapsula responsabilidade única (SRP). O frontend mantém facades que delegam para as classes extraídas.
- **DPS_Phone_Helper::clean()**: adicionado método utilitário para limpeza de telefone (remove não-dígitos), centralizando lógica duplicada em 9+ arquivos.
- **Centralização DPS_Money_Helper**: migradas 16 instâncias de `number_format()` para `DPS_Money_Helper::format_currency()` e `format_currency_from_decimal()` em 10 add-ons (Communications, AI, Agenda, Finance, Loyalty, Client Portal). Removidos fallbacks `class_exists()` desnecessários.
- **Template padrão de add-on**: documentado em `ANALYSIS.md` com estrutura de diretórios, header WP, padrão de inicialização (init@1, classes@5, admin_menu@20), assets condicionais e tabela de compliance.
- **Documentação de metadados**: adicionada seção "Contratos de Metadados dos CPTs" no `ANALYSIS.md` com tabelas detalhadas de meta keys para `dps_cliente`, `dps_pet` e `dps_agendamento`, incluindo tipos, formatos e relações.

**Fase 3 — Performance e Escalabilidade (Plano de Implementação)**

- **N+1 eliminado**: refatorado `query_appointments_for_week()` no trait `DPS_Agenda_Query` de 7 queries separadas para 1 query com `BETWEEN` + agrupamento em PHP (–85% queries DB).
- **Lazy loading**: adicionado `loading="lazy"` em 5 imagens nos plugins Base e Client Portal (`class-dps-base-frontend.php`, `pet-form.php`, `class-dps-portal-renderer.php`).
- **dbDelta version checks**: adicionados guards de versão em `DPS_AI_Analytics::maybe_create_tables()` e `DPS_AI_Conversations_Repository::maybe_create_tables()` para evitar `dbDelta()` em toda requisição.
- **WP_Query otimizada**: `DPS_Query_Helper::get_all_posts_by_type()`, `get_posts_by_meta()` e `get_posts_by_meta_query()` agora incluem `no_found_rows => true` por padrão, eliminando SQL_CALC_FOUND_ROWS desnecessário em todas as consultas centralizadas.
- **Assets condicionais**: Stock add-on corrigido — CSS não é mais carregado globalmente em todas as páginas admin; agora usa `$hook_suffix` para carregamento condicional.
- **Subscription queries**: queries de delete de agendamentos e contagem migradas para `fields => 'ids'` + `no_found_rows => true`, eliminando carregamento desnecessário de objetos completos.
- **Finance query limits** (Fase 3.2): dropdown de clientes otimizado com `no_found_rows => true` e desabilitação de meta/term cache. Query de resumo financeiro limitada a 5.000 registros (safety cap). Busca de clientes limitada a 200 resultados.
- **Auditoria de rate limiting**: verificado que rate limiting já existe em 3 camadas: magic link request (3/hora por IP+email), token validation (5/hora por IP), chat (10/60s por cliente).

#### Changed (Alterado)

**Fase 4 — UX do Portal do Cliente (Plano de Implementação)**

- **Validação em tempo real**: adicionado `handleFormValidation()` no portal do cliente com regras para telefone (formato BR), e-mail, CEP, UF, peso do pet, data de nascimento e campos obrigatórios. Validação on blur + limpeza instantânea on input + validação completa pre-submit com scroll automático para o primeiro erro.
- **Estados visuais**: CSS para `.is-invalid` (borda e glow vermelho) e `.is-valid` (borda verde) nos inputs `.dps-form-control`, com suporte a `prefers-reduced-motion`.
- **Containers de erro**: adicionados `<span class="dps-field-error" role="alert">` após campos validados, com `aria-describedby` vinculando input ao container de mensagem.
- **Acessibilidade ARIA**: `aria-required="true"` em campos obrigatórios (pet name), `aria-describedby` em 7 campos, `role="alert"` em containers de erro, `inputmode="numeric"` no CEP.
- **Atributos HTML5**: `max` no campo de data de nascimento (impede futuro), `max="200"` no campo de peso.
- **Mensagens aprimoradas**: 5 novos tipos de mensagem toast (message_error, review_submitted, review_already, review_invalid, review_error). Todas as mensagens reescritas com títulos descritivos e textos orientados a ação.
- **Filtro de período no histórico** (Fase 4.4): barra de filtros (30/60/90 dias, Todos) acima da timeline de serviços. Filtragem client-side via `data-date` nos itens. Mensagem "nenhum resultado" quando filtro vazio. CSS M3 com `focus-visible` e `aria-pressed`.
- **Detalhes do pet no card** (Fase 4.5): porte (📏 Pequeno/Médio/Grande/Gigante), peso (⚖️ em kg), sexo (♂️/♀️), idade (🎂 calculada automaticamente de `pet_birth`) exibidos no card de info do pet na timeline. CSS com grid responsiva de meta items.
- **"Manter acesso neste dispositivo"** (Fase 4.6): checkbox no formulário de login por e-mail permite manter sessão permanente. Gera token permanente com cookie seguro `dps_portal_remember` (HttpOnly, Secure, SameSite=Strict, 90 dias). Auto-autenticação via `handle_remember_cookie()` na próxima visita. Cookie removido no logout.

**Fase 5 — Funcionalidades Novas (Portal)**

- **Galeria multi-fotos** (Fase 5.1): pets agora suportam múltiplas fotos via meta key `pet_photos` (array de IDs) com fallback automático para `pet_photo_id` legado. Adicionado `DPS_Pet_Handler::get_all_photo_ids()`. Grid multi-foto responsiva com contagem de fotos por pet. Lightbox com navegação prev/next (setas clicáveis + ArrowLeft/ArrowRight no teclado), contador de fotos (1/N) e agrupamento por `data-gallery`.
- **Preferências de notificação** (Fase 5.2): 4 toggles M3 na tela de preferências — lembretes de agendamento (📅), avisos de pagamento (💰), promoções e ofertas (🎁), atualizações do pet (🐾). Defaults inteligentes: lembretes e pagamentos ligados, promoções e updates desligados. Toggle switches CSS com focus-visible e hover states. Handler atualizado com hook `dps_portal_after_update_preferences` expandido.
- **Feedback pós-agendamento** (Fase 5.4): prompt de avaliação exibido no final do histórico de agendamentos. Star rating interativo (1-5 estrelas, `role="radiogroup"` com ARIA labels). Textarea para comentário opcional. Integração com handler existente `submit_internal_review` e CPT `dps_groomer_review`. Estado "já avaliou" com estrelas e mensagem de agradecimento.

**Fase 6 — Segurança Avançada e Auditoria**

- **Auditoria centralizada** (Fase 6.2): criada classe `DPS_Audit_Logger` (446 linhas, 14 métodos estáticos) com tabela `dps_audit_log` para registro de eventos de auditoria (criar, atualizar, excluir, login, mudança de status) em todas as entidades do sistema (clientes, pets, agendamentos, portal, financeiro).
- **Admin page de auditoria**: criada `DPS_Audit_Admin_Page` (370 linhas) com filtros por tipo de entidade, ação, período e paginação (30/página). Badges coloridos para tipos de ação. Integrada como aba "Auditoria" no System Hub.
- **Integração nos handlers**: chamadas de auditoria adicionadas em `DPS_Client_Handler` (save/delete), `DPS_Pet_Handler` (save/delete) e `DPS_Appointment_Handler` (save/status_change).
- **Auditoria de código morto** (Fase 7.4): inventário completo de JS/CSS/PHP em todos os plugins — nenhum arquivo morto encontrado. Único arquivo não carregado (`refactoring-examples.php`) é intencional e documentado em AGENTS.md.
- **Logging de tentativas falhadas** (Fase 6.3): integrado `DPS_Audit_Logger` nos fluxos de autenticação do portal — registra token_validation_failed, login_success e rate_limit_ip no log de auditoria centralizado.

#### Added (Adicionado)

**Agenda Add-on v1.2.0 — Checklist Operacional e Check-in/Check-out**

- **Checklist Operacional**: painel interativo com etapas de banho e tosa (pré-banho, banho, secagem, tosa/corte, orelhas/unhas, acabamento). Cada etapa pode ser marcada como concluída, pulada ou revertida. Barra de progresso em tempo real.
- **Retrabalho (rework)**: registro de retrabalho por etapa com motivo e timestamp. Badge visual indica quantas vezes uma etapa precisou ser refeita.
- **Check-in / Check-out**: registro rápido de entrada e saída do pet com cálculo automático de duração (em minutos).
- **Itens de segurança**: 7 itens pré-definidos (pulgas, carrapatos, feridinhas, alergia, otite, nós, comportamento) com nível de severidade e campo de notas por item. Filtrável via `dps_checkin_safety_items`.
- **Observações rápidas**: campo de texto livre para observações no check-in e check-out.
- **AJAX endpoints**: `dps_checklist_update`, `dps_checklist_rework`, `dps_appointment_checkin`, `dps_appointment_checkout` — todos com nonce + capability check.
- **Hooks de extensão**: `dps_checklist_default_steps`, `dps_checklist_rework_registered`, `dps_checkin_safety_items`, `dps_appointment_checked_in`, `dps_appointment_checked_out`.
- **Render helpers**: `render_checklist_panel()`, `render_checkin_panel()`, `render_compact_indicators()` — prontos para integração em templates de cards de agendamento.
- **Design M3**: CSS com design tokens, responsivo, com modal de retrabalho e grid de itens de segurança.

**Frontend Add-on v1.0.0 — Fundação (Fase 1)**

- **Novo add-on `desi-pet-shower-frontend`**: esqueleto modular para consolidação de experiências frontend (cadastro, agendamento, configurações).
- **Arquitetura moderna PHP 8.4**: constructor promotion, readonly properties, typed properties, return types. Sem singletons — composição via construtor.
- **Module Registry**: registro e boot de módulos independentes controlados por feature flags.
- **Feature Flags**: controle de rollout por módulo via option `dps_frontend_feature_flags`. Todos desabilitados na Fase 1.
- **Camada de compatibilidade**: preparada para bridges de shortcodes e hooks legados (Fases 2-4).
- **Assets M3 Expressive**: CSS sem hex literais (100% via design tokens), JS vanilla com IIFE. Enqueue condicional.
- **Observabilidade**: logger estruturado com níveis INFO/WARNING/ERROR (ativo apenas em WP_DEBUG).
- **Request Guard**: segurança centralizada para nonce, capability e sanitização.
- **Módulos stub**: Registration (Fase 2), Booking (Fase 3), Settings (Fase 4).
- **Registrado no Addon Manager** do plugin base (categoria client, prioridade 72).
- **Documentado no ANALYSIS.md** com arquitetura interna, contratos e roadmap.

**Frontend Add-on v1.1.0 — Módulo Registration (Fase 2)**

- **Módulo Registration operacional** em dual-run com add-on legado `desi-pet-shower-registration`.
- **Estratégia de intervenção mínima**: assume shortcode `[dps_registration_form]`, delega toda a lógica (formulário, validação, emails, REST, AJAX) ao legado.
- **Surface M3 wrapper**: output do formulário envolvido em `.dps-frontend` para aplicação de estilos M3 Expressive.
- **CSS extra**: `frontend-addon.css` carregado condicionalmente sobre os assets do legado.
- **Hooks preservados**: `dps_registration_after_fields`, `dps_registration_after_client_created`, `dps_registration_spam_check`, `dps_registration_agenda_url`.
- **Rollback instantâneo**: desabilitar flag `registration` restaura comportamento 100% legado.
- **Camada de compatibilidade**: bridge de shortcode ativo quando flag habilitada.

**Frontend Add-on v1.2.0 — Módulo Booking (Fase 3)**

- **Módulo Booking operacional** em dual-run com add-on legado `desi-pet-shower-booking`.
- **Estratégia de intervenção mínima**: assume shortcode `[dps_booking_form]`, delega toda a lógica (formulário, confirmação, captura de appointment) ao legado.
- **Surface M3 wrapper**: output do formulário envolvido em `.dps-frontend` para aplicação de estilos M3 Expressive.
- **CSS extra**: `frontend-addon.css` carregado condicionalmente sobre os assets do legado.
- **Hooks preservados**: `dps_base_after_save_appointment` (consumido por 7+ add-ons: stock, payment, groomers, calendar, communications, push, services), `dps_base_appointment_fields`, `dps_base_appointment_assignment_fields`.
- **Rollback instantâneo**: desabilitar flag `booking` restaura comportamento 100% legado.
- **Camada de compatibilidade**: bridge de shortcode ativo quando flag habilitada.

**Frontend Add-on v1.3.0 — Módulo Settings (Fase 4)**

- **Módulo Settings operacional** integrado ao sistema de abas de `DPS_Settings_Frontend`.
- **Aba "Frontend"** registrada via API moderna `register_tab()` com prioridade 110.
- **Controles de feature flags**: interface administrativa para habilitar/desabilitar módulos individualmente (Registration, Booking, Settings).
- **Salvamento seguro**: handler via hook `dps_settings_save_save_frontend`, nonce e capability verificados pelo sistema base.
- **Informações do add-on**: versão e contagem de módulos ativos exibidos na aba.
- **Hooks consumidos**: `dps_settings_register_tabs`, `dps_settings_save_save_frontend`.
- **Rollback instantâneo**: desabilitar flag `settings` remove a aba sem impacto em outras configurações.
- **Camada de compatibilidade**: bridge de hooks ativo quando flag habilitada.

**Frontend Add-on v1.4.0 — Consolidação e Documentação (Fase 5)**

- **Guia operacional de rollout** (`docs/implementation/FRONTEND_ROLLOUT_GUIDE.md`): passos de ativação por ambiente (dev, homolog, prod), ordem recomendada, verificação pós-ativação.
- **Runbook de incidentes** (`docs/implementation/FRONTEND_RUNBOOK.md`): classificação de severidade, diagnóstico rápido, procedimentos de rollback por módulo, cenários de incidente específicos.
- **Matriz de compatibilidade** (`docs/qa/FRONTEND_COMPATIBILITY_MATRIX.md`): status de integração com 18 add-ons, contratos de shortcodes/hooks/options verificados, impacto de desativação por módulo.
- **Checklist de remoção futura** (`docs/qa/FRONTEND_REMOVAL_READINESS.md`): critérios objetivos por módulo, riscos e mitigação, procedimento de remoção segura (nenhuma remoção nesta etapa).

**Frontend Add-on v1.5.0 — Governança de Depreciação (Fase 6)**

- **Política de depreciação** (`docs/refactoring/FRONTEND_DEPRECATION_POLICY.md`): janela mínima de 180 dias (90 dual-run + 60 aviso + 30 observação), processo de comunicação formal, critérios de aceite técnicos e de governança, procedimento de depreciação em 5 etapas.
- **Lista de alvos de remoção** (`docs/refactoring/FRONTEND_REMOVAL_TARGETS.md`): inventário completo com dependências por grep (registration: 5 refs no base + 2 hooks no Loyalty; booking: 0 refs externas), risco por alvo, esforço estimado, plano de reversão, ordem de prioridade recomendada.
- **Telemetria de uso**: método `DPS_Frontend_Logger::track()` com contadores por módulo persistidos em `dps_frontend_usage_counters`. Cada renderização de shortcode via módulo frontend é contabilizada. Contadores exibidos na aba Settings para apoiar decisões de depreciação.

**Frontend Add-on v2.0.0 — Fase 7.1 Preparação (Implementação Nativa)**

- **Novas feature flags `registration_v2` e `booking_v2`**: flags independentes para módulos nativos V2. Coexistem com flags v1 (`registration`, `booking`). Ambas podem estar ativas simultaneamente.
- **Template Engine (`DPS_Template_Engine`)**: sistema de renderização com suporte a override via tema (dps-templates/), output buffering seguro e dados isolados por escopo.
- **Classes abstratas base (Fase 7)**:
  - `DPS_Abstract_Module_V2`: base para módulos nativos com boot padronizado, registro de shortcode e enqueue condicional de assets.
  - `DPS_Abstract_Handler`: base para handlers de formulário com resultado padronizado (success/error).
  - `DPS_Abstract_Service`: base para services CRUD com wp_insert_post e gerenciamento de metas.
  - `DPS_Abstract_Validator`: base para validadores com helpers de campo obrigatório e email.
- **Hook Bridges (compatibilidade retroativa)**:
  - `DPS_Registration_Hook_Bridge`: dispara hooks legados (Loyalty) + novos hooks v2 após ações de registro. Ordem: legado PRIMEIRO, v2 DEPOIS.
  - `DPS_Booking_Hook_Bridge`: dispara hook crítico `dps_base_after_save_appointment` (8 consumidores) + novos hooks v2. Ordem: legado PRIMEIRO, v2 DEPOIS.
- **Módulos V2 nativos (skeleton)**:
  - `DPS_Frontend_Registration_V2_Module`: shortcode `[dps_registration_v2]`, independente do legado, com template engine e hook bridge.
  - `DPS_Frontend_Booking_V2_Module`: shortcode `[dps_booking_v2]`, independente do legado, com login check, REST/AJAX skip, template engine e hook bridge.
- **11 componentes M3 reutilizáveis** (templates/components/): field-text, field-email, field-phone, field-select, field-textarea, field-checkbox, button-primary, button-secondary, card, alert, loader. Todos com acessibilidade ARIA nativa, namespacing `.dps-v2-*`, suporte a erro e helper text.
- **Templates skeleton**: registration/form-main.php, booking/form-main.php, booking/form-login-required.php. Wizard com barra de progresso 5 steps.
- **Assets V2 nativos (CSS + JS)**: registration-v2.css, booking-v2.css com 100% design tokens M3 (zero hex hardcoded), suporte a tema escuro, `prefers-reduced-motion`, responsividade. JS vanilla (zero jQuery).
- **Aba Settings atualizada**: exibe flags v2 (Fase 7) com labels e descrições distintas. Telemetria v2 separada.
- **Estrutura de diretórios completa**: handlers/, services/, validators/, ajax/, bridges/, abstracts/, templates/registration/, templates/booking/, templates/components/, templates/emails/.

**Frontend Add-on v2.1.0 — Fase 7.2 Registration V2 (Implementação Nativa)**

- **Validators**:
  - `DPS_Cpf_Validator`: validação CPF mod-11 com normalização, rejeição de sequências repetidas. Compatível com legado.
  - `DPS_Form_Validator`: validação completa do formulário (nome, email, telefone, CPF, pets). Usa `DPS_Cpf_Validator` internamente.
- **Services**:
  - `DPS_Client_Service`: CRUD para post type `dps_cliente`. Cria clientes com 13+ metas padronizadas. Normalização de telefone com fallback para `DPS_Phone_Helper`.
  - `DPS_Pet_Service`: CRUD para post type `dps_pet`. Vincula pets a clientes via meta `owner_id`.
  - `DPS_Breed_Provider`: dataset de raças por espécie (cão: 44 raças, gato: 20 raças). Populares priorizadas. Cache em memória. Output JSON para datalist.
  - `DPS_Duplicate_Detector`: detecção de duplicatas APENAS por telefone (conforme legado v1.3.0). Admin override suportado.
  - `DPS_Recaptcha_Service`: verificação reCAPTCHA v3 server-side. Score threshold configurável. Lê options do legado.
  - `DPS_Email_Confirmation_Service`: token UUID 48h com `wp_generate_uuid4()`. Envio via `DPS_Communications_API` ou `wp_mail()`. Confirmação + limpeza de tokens.
- **Handler**:
  - `DPS_Registration_Handler`: processamento completo — reCAPTCHA → anti-spam → validação → duplicata → criação cliente → hooks (Loyalty) → criação pets → email confirmação. 100% independente do legado.
- **Templates nativos M3**:
  - `form-main.php`: expandido com seções, honeypot, reCAPTCHA, marketing opt-in, hook bridge `dps_registration_after_fields`.
  - `form-client-data.php`: nome, email, telefone, CPF (com mask), endereço (com coords ocultas). Sticky form com erros por campo.
  - `form-pet-data.php`: repeater JavaScript para múltiplos pets. Nome, espécie, raça (datalist dinâmico), porte, observações.
  - `form-success.php`: confirmação com CTA para agendamento.
  - `form-duplicate-warning.php`: aviso de duplicata com checkbox de override (admin).
  - `form-error.php`: exibição de erros (lista ou parágrafo).
- **Module atualizado**:
  - `DPS_Frontend_Registration_V2_Module`: processa POST submissions via handler, renderiza breed data, reCAPTCHA v3, booking URL. Setters para DI tardia de handler/breed/recaptcha.
- **JavaScript nativo expandido** (`registration-v2.js`):
  - Pet repeater (add/remove/reindex)
  - Breed datalist dinâmico (espécie → raças)
  - Phone mask `(XX) XXXXX-XXXX`
  - CPF mask `XXX.XXX.XXX-XX`
  - Client-side validation com scroll para primeiro erro
  - reCAPTCHA v3 execute antes do submit
  - Submit loader + alerts dismissíveis
- **CSS expandido** (`registration-v2.css`): grid layout para campos, pet entry cards, repeater actions, success state, compact mode, responsive.
- **Bootstrap atualizado**: carrega validators, services, handler com DI completa.

**Frontend Add-on v2.2.0 — Fase 7.3 Booking V2 (Implementação Nativa)**

- **Services**:
  - `DPS_Appointment_Service`: CRUD completo para post type `dps_agendamento`. Cria agendamentos com 16+ metas padronizadas (client, pets, services, pricing, extras). Verificação de conflitos por data/hora. Busca por cliente. Versionamento via `_dps_appointment_version`.
  - `DPS_Booking_Confirmation_Service`: confirmação via transient (`dps_booking_confirmation_{user_id}`, TTL 5min). Store, retrieve, clear, isConfirmed.
- **Validators**:
  - `DPS_Booking_Validator`: validação multi-step (5 steps) — cliente (ID obrigatório), pets (array não vazio), serviços (array não vazio), data/hora (formato, passado, conflitos), confirmação. Validação de extras (TaxiDog preço ≥ 0, Tosa preço ≥ 0 e ocorrência > 0 quando habilitada). Tipo `past` permite datas passadas.
- **Handler**:
  - `DPS_Booking_Handler`: pipeline completo — beforeProcess → validação → extras → buildMeta → criação appointment → confirmação transient → hook CRÍTICO `dps_base_after_save_appointment` (8 add-ons: Stock, Payment, Groomers, Calendar, Communications, Push, Services, Booking) → afterProcess. 100% independente do legado.
- **AJAX Endpoints** (`DPS_Booking_Ajax`):
  - `dps_booking_search_client`: busca clientes por telefone (LIKE com dígitos normalizados). Retorna id, name, phone, email.
  - `dps_booking_get_pets`: lista pets do cliente com paginação. Retorna id, name, species, breed, size.
  - `dps_booking_get_services`: serviços ativos com preços por porte (base, small, medium, large, category).
  - `dps_booking_get_slots`: horários disponíveis (08:00-18:00, 30min) com verificação de conflitos.
  - `dps_booking_validate_step`: validação server-side por step com sanitização contextual.
  - Todos com nonce + capability check (`manage_options` OU `dps_manage_clients` OU `dps_manage_pets` OU `dps_manage_appointments`).
- **Templates nativos M3 (Wizard 5 steps)**:
  - `form-main.php`: expandido com renderização dinâmica de steps via template engine, suporte a success state.
  - `step-client-selection.php`: Step 1 — busca de cliente por telefone via AJAX, cards selecionáveis, hidden input client_id.
  - `step-pet-selection.php`: Step 2 — multi-select de pets com checkboxes, paginação "Carregar mais".
  - `step-service-selection.php`: Step 3 — seleção de serviços com preços R$, total acumulado.
  - `step-datetime-selection.php`: Step 4 — date picker, time slots via AJAX, seletor de tipo (simple/subscription/past), notas.
  - `step-extras.php`: Step 5a — TaxiDog (checkbox + preço), Tosa (subscription only, checkbox + preço + frequência).
  - `step-confirmation.php`: Step 5b — resumo read-only com hidden inputs para submissão.
  - `form-success.php`: tela de confirmação com dados do agendamento e CTA.
- **Module atualizado**:
  - `DPS_Frontend_Booking_V2_Module`: processa POST via handler, sanitiza dados (client, pets, services, datetime, extras), capability check, setters para DI tardia de handler/confirmationService.
- **JavaScript nativo expandido** (`booking-v2.js`):
  - Wizard state machine com navegação entre steps (next/prev)
  - Atualização dinâmica de barra de progresso e URL (?step=X via pushState)
  - AJAX via Fetch API para busca de clientes, pets, serviços e horários
  - Debounce na busca de telefone (300ms)
  - Running total dinâmico na seleção de serviços
  - Toggle de extras (TaxiDog/Tosa) com visibilidade condicional
  - Builder de resumo para confirmação
  - XSS mitigation via escapeHtml()
  - Zero jQuery
- **CSS expandido** (`booking-v2.css`): step containers, search UI, selectable cards grid, time slot grid, extras cards, summary sections, running total bar, appointment type selector, loading states, navigation actions, compact mode, responsive, dark theme, `prefers-reduced-motion`.
- **Bootstrap atualizado**: carrega validators, services, handler, AJAX com DI completa. `wp_localize_script` para ajaxUrl e nonce.

**Frontend Add-on v2.3.0 — Fase 7.4 Coexistência e Migração**

- **Guia de Migração** (`docs/implementation/FRONTEND_V2_MIGRATION_GUIDE.md`):
  - Guia passo a passo completo em 7 etapas para migrar de v1 (dual-run) para v2 (nativo)
  - Comparação detalhada de features v1 vs v2 para Registration e Booking
  - Checklist de compatibilidade com 12 itens de verificação
  - Plano de rollback instantâneo (swap de flags, zero perda de dados)
  - Troubleshooting para problemas comuns de migração
  - Configuração via WP-CLI para automação de migração
- **Status de Coexistência v1/v2** (Settings Admin UI):
  - Seção "Status de Coexistência v1 / v2" na aba Frontend do painel de configurações
  - Indicador visual por módulo (Cadastro/Agendamento): 4 estados distintos com cores e ícones
    - ✅ Somente v2 — migração concluída (verde)
    - ⚡ Coexistência — v1 + v2 ativos (âmbar)
    - 📦 Somente v1 — considere migrar (neutro)
    - ⏸️ Nenhum ativo (muted)
  - Link direto para guia de migração
- **Telemetria v2** (já implementada):
  - Contadores por módulo (v1 e v2) via `DPS_Frontend_Logger::track()`
  - Exibidos na aba Settings com comparação v1 vs v2
  - Decisões de depreciação futura baseadas nos contadores

**Frontend Add-on v2.4.0 — Fase 7.5 Depreciação do Dual-Run**

- **Aviso de depreciação admin** (`DPS_Frontend_Deprecation_Notice`):
  - Banner administrativo exibido quando módulos v1 (registration e/ou booking) estão ativos
  - Aviso dismissível por usuário (transient 30 dias)
  - Dismiss via AJAX com nonce + capability check (`manage_options`)
  - Mensagem inclui lista dos módulos v1 ativos e link para guia de migração
  - Só exibe para administradores (capability `manage_options`)
- **Documentação visual completa** (`docs/screenshots/2026-02-12/`):
  - 7 screenshots PNG: Registration V2, Booking V2 (steps 3 e 5), sucesso, login obrigatório, aviso depreciação, status coexistência
  - Preview HTML interativo com todas as telas V2
  - Documento de registro `SCREENSHOTS_2026-02-12.md` com contexto, antes/depois e lista de arquivos
- **Bootstrap atualizado**: carrega `DPS_Frontend_Deprecation_Notice` e inicializa após boot do add-on

**Booking Add-on v1.3.0 — Migração M3 e Melhorias de Segurança**

- **Validação granular de edição de agendamentos**: Método `can_edit_appointment()` verifica se usuário pode editar agendamento específico (criador ou admin).
- **Suporte a `prefers-reduced-motion`**: Animação de confirmação respeita preferência de acessibilidade do usuário.

**Design System Material 3 Expressive (Docs + Design Tokens v2.0)**

- **Design tokens CSS** (`dps-design-tokens.css`): Arquivo centralizado com 200+ CSS custom properties implementando o sistema completo do Material 3 Expressive — cores (primary/secondary/tertiary/error/success/warning + surface containers), tipografia (escala M3: Display/Headline/Title/Body/Label), formas (escala de arredondamento: 0–4–8–12–16–28–pill), elevação tonal (6 níveis), motion (easing expressivo com springs + duração), espaçamento e state layers.
- **Suporte a tema escuro** via `[data-dps-theme="dark"]` com paleta completa de cores invertidas.
- **Aliases de compatibilidade** para migração gradual dos tokens legados (`--dps-bg-*`, `--dps-accent`, etc.) para os novos tokens M3.
- **Demo interativo** (`visual-comparison.html`): Preview completo do design system com todos os componentes, toggle claro/escuro e animações expressivas.

#### Changed (Alterado)

**Client Portal Add-on — Revisão UX/UI da Página Principal e Navegação por Abas**

- **Shell principal refinado** no shortcode `[dps_client_portal]` (estado autenticado): header reorganizado em bloco de conteúdo + ações globais (avaliar/sair), com hierarquia visual e espaçamento mais claros.
- **Navegação por abas com acessibilidade reforçada**:
  - foco visível consistente (`:focus-visible`),
  - relacionamento ARIA explícito (`tablist`, `tab`, `tabpanel`, `aria-controls`, `aria-labelledby`, `aria-selected`),
  - suporte a abas desabilitadas sem quebrar extensões.
- **Interação por teclado aprimorada**: setas esquerda/direita, Home/End e ativação com Enter/Espaço.
- **Persistência e navegação**: aba ativa preservada por hash (`#tab-*`) com sincronização em refresh/back.
- **Feedback leve de troca de abas**: indicador visual/textual de carregamento sem alterar o conteúdo interno dos painéis.
- **Mobile**: tabs mantêm labels visíveis e overflow horizontal controlado para melhor descobribilidade.
- **Compatibilidade preservada**: filtro `dps_portal_tabs` e hooks `dps_portal_before_*_content` / `dps_portal_after_*_content` mantidos sem alteração de assinatura.

**Booking Add-on v1.3.0 — Migração M3 e Melhorias de Segurança**

- **Migração completa para M3 Expressive tokens** (`booking-addon.css`):
  - 37 cores hardcoded → tokens M3 (`--dps-color-*`)
  - 5 border-radius → shape tokens (`--dps-shape-*`)
  - 3 transições → motion tokens (`--dps-motion-*`)
  - 3 sombras → elevation tokens (`--dps-elevation-*`)
  - 24 valores tipográficos → escala M3 (`--dps-typescale-*`)
  - Semantic mapping em `.dps-booking-wrapper` para customização local
- **Enfileiramento condicional de design tokens**: Dependência de `dps-design-tokens.css` via check de `DPS_BASE_URL`.
- **Otimização de performance** (batch queries):
  - Fix N+1: owners de pets agora fetched em batch (redução de 100+ queries para 1)
  - Prepared for future optimization of client pagination
- **Melhorias de acessibilidade**:
  - `aria-hidden="true"` adicionado a todos emojis decorativos
  - Documentação phpcs para parâmetros GET read-only validados por capability

- **`VISUAL_STYLE_GUIDE.md` v1.2 → v2.0**: Redesenhado integralmente como design system baseado no Material 3 Expressive — sistema de cores com papéis semânticos (color roles), escala tipográfica M3 (5 papéis × 3 tamanhos), sistema de formas expressivas (botões pill, cards 12px, diálogos 28px), elevação tonal, motion com springs, state layers, novos componentes (btn-filled/outlined/tonal/text, FAB, chips, badges, alertas M3), guia de migração do sistema legado.
- **`FRONTEND_DESIGN_INSTRUCTIONS.md` v1.0 → v2.0**: Atualizado com metodologia M3 Expressive — dois perfis (Standard para admin, Expressive para portal), princípios de design expressivo, state layers, shape system, elevation tonal, motion com easing de springs, exemplos práticos adaptados ao contexto pet shop, checklist atualizado com tokens M3.

**Front-end de Configurações do Sistema (Base v2.6.0)**

- **CSS dedicado para configurações** (`dps-settings.css`): Folha de estilos exclusiva para a página de configurações com layout melhorado, barra de status, campo de busca, navegação por abas aprimorada, indicador de alterações não salvas e design responsivo completo.
- **JavaScript dedicado para configurações** (`dps-settings.js`): Navegação client-side entre abas sem recarregar a página, busca em tempo real com destaque visual dos resultados encontrados, rastreamento de alterações não salvas com aviso ao sair da página.
- **Barra de status**: Exibe contagem de categorias de configuração disponíveis e nome do usuário logado.
- **Busca de configurações**: Campo de pesquisa que filtra e destaca configurações em todas as abas simultaneamente, com indicador visual de "sem resultados" e destaque nas abas que contêm resultados.
- **Indicador de alterações não salvas**: Detecção automática de modificações em formulários com barra de ação fixa (sticky) e aviso `beforeunload` para prevenir perda de dados.
- **Enfileiramento automático de assets**: CSS e JS de configurações são carregados apenas na página de configurações, com versionamento automático por data de modificação do arquivo.

**Redesign da Página de Detalhes do Cliente (Base v1.3.0)**

- **Novo layout de cabeçalho**: Reorganização visual com navegação separada, título com badges e ações primárias destacadas.
- **Painel de Ações Rápidas**: Nova seção dedicada para links de consentimento, atualização de perfil e outras ações externas, com visual moderno e organizado.
- **Hook para badges no título**: `dps_client_page_header_badges` permite que add-ons de fidelidade adicionem indicadores de nível/status ao lado do nome do cliente.
- **Seção de Notas Internas**: Campo de texto editável para anotações administrativas sobre o cliente (visível apenas para a equipe).
  - Salvamento via AJAX com feedback visual
  - Armazenado em `client_internal_notes` meta
  - Estilo diferenciado (amarelo) para destacar que são notas internas

**Melhorias na Página de Detalhes do Cliente (Base v1.2.0)**

- **Data de cadastro do cliente**: Agora exibida nos cards de resumo ("Cliente Desde") e na seção de Dados Pessoais para visualização do tempo de relacionamento.
- **Hooks de extensão para add-ons na página do cliente**: Novos hooks permitem que add-ons injetem seções personalizadas:
  - `dps_client_page_after_personal_section`: após dados pessoais
  - `dps_client_page_after_contact_section`: após contato e redes sociais
  - `dps_client_page_after_pets_section`: após lista de pets
  - `dps_client_page_after_appointments_section`: após histórico de atendimentos
- **Autorização de fotos com badge visual**: Campo de autorização para fotos agora exibe badges coloridos (✓ Autorizado em verde, ✕ Não Autorizado em vermelho) para melhor visibilidade.

**Melhorias de UI/UX e Responsividade no Formulário de Cadastro Público (Registration Add-on v1.3.1)**

- **Novo breakpoint para telas muito pequenas (< 375px)**: Adicionado suporte para dispositivos móveis com telas extra pequenas (ex: iPhone SE, dispositivos antigos).
  - Padding e espaçamento reduzidos para melhor aproveitamento do espaço
  - Tamanhos de fonte ajustados mantendo legibilidade
  - Border-radius menores para visual mais compacto
- **Indicadores de campos obrigatórios nos pets**: Campos de Espécie, Porte e Sexo agora exibem asterisco vermelho (*) indicando obrigatoriedade.
  - Aplicado tanto no fieldset inicial quanto nos pets adicionados dinamicamente via JavaScript
- **Altura mínima de inputs para melhor usabilidade móvel**: Inputs agora têm altura mínima de 48px, melhorando a área de toque para dispositivos touch.

**Consentimento de Tosa com Máquina (Client Portal + Base)**

- **Página pública de consentimento via token**: Novo shortcode `[dps_tosa_consent]` para coletar consentimento com preenchimento automático e registro por cliente.
- **Geração de link pelo administrador**: Botão no header do cliente para gerar link, copiar e enviar ao tutor.
- **Revogação registrada**: Consentimento válido até revogação manual pelo administrador.
- **Indicadores operacionais**: Badge no formulário e na lista de agendamentos, com alerta de ausência ao salvar.
- **Logging de auditoria**: Eventos de geração de link, revogação e registro de consentimento agora são registrados no DPS_Logger para rastreabilidade.
- **Códigos de erro estruturados**: Respostas AJAX agora incluem códigos de erro padronizados (NONCE_INVALIDO, SEM_PERMISSAO, CLIENTE_NAO_ENCONTRADO) para melhor integração.
- **Função helper global**: `dps_get_tosa_consent_page_url()` para obter URL da página de consentimento.
- **Acessibilidade aprimorada**: Formulário de consentimento com atributos ARIA (aria-label, aria-labelledby, aria-required), autocomplete semântico e navegação por teclado melhorada.
- **CSS externalizado**: Estilos movidos para arquivo separado (`tosa-consent-form.css`) para melhor cache e manutenibilidade.
- **UX mobile otimizada**: Área de toque aumentada em checkboxes, inputs com altura mínima de 48px, breakpoints responsivos (480px, 768px).

#### Changed (Alterado)

**Melhoria de UI no Painel de Ações Rápidas (Base v1.3.1)**

- **Reorganização do painel de Ações Rápidas**: Elementos que antes estavam misturados agora são agrupados por funcionalidade em cards separados:
  - **Grupo "Consentimento de Tosa"**: Status badge, botões de copiar/gerar link e revogar organizados em um card dedicado
  - **Grupo "Atualização de Perfil"**: Botões de copiar/gerar link organizados em um card dedicado
- **Textos mais concisos**: Botões com textos reduzidos ("Copiar" em vez de "Copiar Link", "Gerar Link" em vez de "Link de Consentimento")
- **Badges de status mais compactos**: "Ativo", "Pendente", "Revogado" em vez de "Consentimento ativo", etc.
- **Layout responsivo melhorado**: Estilos específicos para mobile (< 600px) com botões em coluna e largura total
- **Novo estilo `.dps-btn-action--danger`**: Botão vermelho para ações destrutivas como "Revogar"

**Refinamentos visuais conforme Guia de Estilo (Registration Add-on v1.3.1)**

- **Bordas padronizadas para 1px**: Alteradas bordas de 2px para 1px em inputs, pet fieldsets, summary box, botão secundário e botão "Adicionar pet", seguindo o guia de estilo visual do DPS.
- **Botão "Adicionar pet" com borda consistente**: Alterado de `border: 2px dashed` para `border: 1px dashed` para maior consistência visual.
- **Padding de inputs aumentado**: Alterado de 12px para 14px vertical, resultando em área de toque mais confortável (48px total).

**Link de Atualização de Perfil para Clientes (Client Portal v2.5.0)**

- **Botão "Link de Atualização" na página do cliente**: Administradores agora podem gerar um link exclusivo para que o cliente atualize seus próprios dados e de seus pets.
  - Botão disponível no header da página de detalhes do cliente
  - Link válido por 7 dias (token type: `profile_update`)
  - Copia automaticamente para a área de transferência
  - Pode ser enviado via WhatsApp ou Email pelo administrador
- **Formulário público de atualização de perfil**: Clientes podem atualizar:
  - Dados pessoais (nome, CPF, data de nascimento)
  - Contato (telefone, email, Instagram, Facebook)
  - Endereço e preferências
  - Dados de pets existentes (espécie, raça, porte, peso, cuidados especiais)
  - Cadastrar novos pets
- **Design responsivo e intuitivo**: Formulário com interface limpa, cards colapsáveis para pets, validação de campos obrigatórios
- **Hook `dps_client_page_header_actions`**: Novo hook no header da página do cliente para extensões adicionarem ações personalizadas
- **Novo token type `profile_update`**: Suporte no Token Manager para tokens de atualização de perfil com expiração de 7 dias

**Catálogo Completo de Serviços de Banho e Tosa - Região SP (v1.6.1)**

- **30+ serviços pré-configurados com valores de mercado SP 2024**: Lista completa de serviços típicos de pet shop com preços diferenciados por porte (pequeno/médio/grande):
  - **Serviços Padrão**: Banho (R$ 50-120), Banho e Tosa (R$ 100-230), Tosa Higiênica (R$ 40-80)
  - **Opções de Tosa**: Tosa Máquina (R$ 65-140), Tosa Tesoura (R$ 85-180), Tosa da Raça (R$ 120-280), Corte Estilizado (R$ 135-300)
  - **Preparação da Pelagem**: Remoção de Nós (leve/moderado/severo), Desembaraço Total
  - **Tratamentos**: Banho Terapêutico/Ozônio, Banho Medicamentoso, Banho Antipulgas, Tratamento Dermatológico
  - **Pelagem e Pele**: Hidratação, Hidratação Profunda, Restauração Capilar, Cauterização
  - **Cuidados Adicionais**: Corte de Unhas (R$ 18-35), Limpeza de Ouvido, Escovação Dental, Limpeza de Glândulas Anais, Tosa de Patas
  - **Extras/Mimos**: Perfume Premium, Laço/Gravatinha, Bandana, Tintura/Coloração
  - **Transporte**: TaxiDog (Leva e Traz) R$ 30-45
  - **Pacotes**: Pacote Completo, Pacote Spa
- **Durações por porte**: Cada serviço inclui tempo estimado de execução para cada porte de pet
- **Ativo por padrão**: Todos os serviços são criados como ativos para edição imediata pelo administrador

**Seção de Tosa no Formulário de Agendamento via Shortcode (v1.2.1)**

- **Card de tosa no shortcode `[dps_booking_form]`**: Adicionada a mesma seção de tosa com design card-based que foi implementada no formulário de agendamento do Painel de Gestão DPS pela PR #498.
  - Card com toggle switch para ativar/desativar tosa
  - Campo de valor da tosa com prefixo R$
  - Seletor de ocorrência (em qual atendimento a tosa será realizada)
  - Design consistente com o card de TaxiDog já existente no formulário
  - Estilos reutilizam classes CSS do plugin base (`dps-tosa-section`, `dps-tosa-card`, etc.)
  - Visibilidade condicional via JavaScript (aparece apenas para agendamentos de assinatura)

**Botão de Reagendamento nas Abas Simplificadas da Agenda (v1.1.0)**

- **Coluna "Ações" nas abas da agenda**: Adicionada nova coluna "Ações" nas três abas simplificadas da agenda (Visão Rápida, Operação, Detalhes).
  - Botão "📅 Reagendar" disponível em cada linha de atendimento
  - Permite alterar a data e/ou horário de um agendamento diretamente pela interface
  - Modal de reagendamento com seletor de data e hora
  - Registro automático no histórico do agendamento
  - Dispara hook `dps_appointment_rescheduled` para integrações
- **Funcionalidade já existente agora acessível**: O backend de reagendamento já existia (`quick_reschedule_ajax`), mas o botão não estava visível nas abas mais utilizadas do dia-a-dia.
- **Método helper `render_reschedule_button()`**: Criado método privado para renderizar o botão de reagendamento, evitando duplicação de código em 4 locais diferentes.

**Modo Administrador no Chat Público de IA (v1.8.0)**

- **Modo Administrador com acesso expandido**: O shortcode `[dps_ai_public_chat]` agora detecta automaticamente quando um administrador (capability `manage_options`) está logado e ativa o modo sistema:
  - Acesso a dados de clientes cadastrados (total, ativos nos últimos 90 dias)
  - Acesso a estatísticas de pets registrados
  - Acesso a informações de agendamentos (hoje, semana, mês)
  - Acesso a dados financeiros (faturamento do mês, valores pendentes)
  - Informações de versão e status do sistema
- **UI/UX diferenciada para administradores**:
  - Badge visual "🔐 Admin" no cabeçalho do chat
  - Indicador "Modo Sistema" na toolbar
  - Cor temática roxa (#7c3aed) para distinguir do modo visitante
  - FAQs específicas para gestão (clientes, agendamentos, faturamento)
  - Mensagem de boas-vindas com lista de capacidades disponíveis
  - Disclaimer informando sobre acesso a dados sensíveis
- **Segurança reforçada**:
  - Validação de capability no backend (não pode ser burlada via frontend)
  - Rate limiting diferenciado: 30/min e 200/hora para admins (vs 10/min e 60/hora para visitantes)
  - Logs de auditoria para todas as consultas em modo admin
  - Visitantes NUNCA recebem dados de clientes, financeiros ou sensíveis
- **Prompt de sistema específico**: Administradores recebem prompt expandido com instruções para fornecer dados do sistema
- **Limite de caracteres expandido**: 1000 caracteres para admins (vs 500 para visitantes)
- **Atributo `data-admin-mode`**: Indicador no HTML para debugging e extensibilidade

#### Changed (Alterado)

**Services Add-on - Melhorias de UI/UX e Validações (v1.6.0)**

- **Empty state com CTA**: A aba Serviços agora exibe botão "Cadastrar primeiro serviço" quando não há serviços cadastrados, melhorando o fluxo de onboarding.
- **Indicador de campos obrigatórios**: Adicionada mensagem explicativa "* Campos obrigatórios" no formulário de cadastro/edição de serviços.
- **Espaçamento padronizado**: Valores por pet (assinatura) agora usam 16px de padding, alinhado com padrão visual global.
- **Link de cancelar edição melhorado**: Estilizado como botão secundário vermelho para melhor feedback visual.
- **Acessibilidade em ícones**: Adicionados atributos `aria-label` e `role="img"` nos ícones de informação.
- **Focus visible melhorado**: Estilos de foco visíveis consistentes para acessibilidade de navegação por teclado.

#### Security (Segurança)

**Booking Add-on v1.3.0**

- **Validação de permissões reforçada**: Verificação de `can_access()` antes de renderizar seção de agendamentos.
- **Proteção contra edição não autorizada**: Novos checks garantem que usuário só edita/duplica agendamentos próprios (exceto admins).
- **Documentação de segurança**: Comentários phpcs explicam validação de parâmetros GET read-only.

#### Refactoring (Interno)

**Booking Add-on v1.3.0**

- **Arquivo CSS backup**: Original mantido em `booking-addon.css.backup` para referência durante migração M3.

#### Fixed (Corrigido)

**Aviso de dependências Elementor não registradas (Base v1.1.2)**

- **Sintoma**: Notice PHP "The script with the handle 'elementor-v2-editor-components' was enqueued with dependencies that are not registered" aparecia nos logs quando Elementor estava instalado.
- **Causa raiz identificada**: A classe `DPS_Cache_Control` verifica metadados de page builders (Elementor, YooTheme) para detectar shortcodes DPS e desabilitar cache. A chamada `get_post_meta()` para `_elementor_data` disparava hooks internos do Elementor que tentavam carregar scripts do editor no frontend, causando o aviso de dependências não registradas.
- **Solução implementada**:
  - Adicionada verificação condicional antes de buscar metadados: `if ( defined( 'ELEMENTOR_VERSION' ) || class_exists( '\Elementor\Plugin' ) )`
  - Metadados do Elementor só são carregados quando o plugin está realmente ativo, evitando disparar hooks desnecessários
  - Mesmo padrão aplicado ao YooTheme para prevenção: `if ( class_exists( 'YOOtheme\Application' ) || function_exists( 'yootheme' ) )`
- **Impacto**: Elimina notices no log sem afetar a funcionalidade de detecção de shortcodes em páginas construídas com page builders.

**Página de Consentimento de Tosa não exibida (Base v1.2.3)**

- **Causa raiz identificada**: O formulário de consentimento de tosa não era exibido porque a página com o shortcode `[dps_tosa_consent]` não existia. O sistema gerava um link para `/consentimento-tosa-maquina/` que resultava em erro 404.
- **Solução implementada**:
  - Página de consentimento agora é criada automaticamente na ativação do plugin ou quando o primeiro link é gerado.
  - Novo método estático `DPS_Tosa_Consent::create_consent_page()` cria a página com shortcode correto.
  - Método `get_consent_page_url()` refatorado para verificar existência da página e criá-la se necessário.
  - Se a página existir mas não tiver o shortcode, ele é adicionado automaticamente.
- **Método de diagnóstico**: `DPS_Tosa_Consent::diagnose_consent_page()` permite verificar status da página.
- **Documentação atualizada**: Catálogo de shortcodes agora indica que a página é criada automaticamente.

**Formulário de Consentimento de Tosa não exibindo versão atualizada (Base v1.2.2)**

- **Template do tema sobrescrevendo versão do plugin**: O sistema de templates permite que temas sobrescrevam arquivos via `dps-templates/`. Se o tema tinha uma versão antiga do template `tosa-consent-form.php`, a versão melhorada da PR #518 não era exibida no site, mesmo após o merge.
- **Solução implementada**:
  - Template de consentimento agora força uso da versão do plugin por padrão, garantindo que melhorias sejam imediatamente visíveis.
  - Novo filtro `dps_allow_consent_template_override` para permitir que temas sobrescrevam quando desejado: `add_filter( 'dps_allow_consent_template_override', '__return_true' );`
  - Logging de warning quando override do tema é detectado e ignorado, facilitando diagnóstico de problemas.
- **Melhorias no sistema de templates**:
  - Novo filtro `dps_use_plugin_template` para forçar uso do template do plugin em qualquer template.
  - Nova action `dps_template_loaded` disparada quando um template é carregado, útil para debug.
  - Nova função `dps_get_template_path()` retorna caminho do template sem incluí-lo.
  - Nova função `dps_is_template_overridden()` verifica se um template está sendo sobrescrito pelo tema.

**Services Add-on - Correção de ativação do catálogo de serviços (v1.6.2)**

- **Hook de ativação movido para arquivo wrapper**: O `register_activation_hook` que popula os 30+ serviços padrão estava incorretamente registrado dentro do construtor da classe `DPS_Services_Addon`, que só era instanciada no hook `init`. Como o WordPress processa hooks de ativação ANTES do hook `init` rodar, o callback nunca era executado, resultando em catálogo vazio mesmo após desativar/reativar o plugin.
- **Método `activate()` tornado estático**: O método agora pode ser chamado diretamente pelo hook de ativação sem necessitar de uma instância da classe.
- **Impacto**: Corrige o problema onde o catálogo de 30+ serviços implementado na PR #508 não era refletido no site mesmo após desativar/reativar o add-on.

#### Security (Segurança)

**Services Add-on - Validações reforçadas (v1.6.0)**

- **Validação de preços não-negativos**: Todos os preços de serviços (pequeno/médio/grande) agora são validados para impedir valores negativos via `max(0, floatval(...))`.
- **Validação de durações não-negativas**: Durações por porte agora impedem valores negativos.
- **Sanitização de insumos**: Quantidade de insumos vinculados a serviços agora é sanitizada com `sanitize_text_field()` antes da conversão numérica.
- **Total de agendamento não-negativo**: Valor total do agendamento validado para impedir negativos.
- **Desconto de pacotes normalizado**: Desconto percentual na API de cálculo de pacotes agora é normalizado para intervalo 0-100 com `min(100, max(0, $discount))`.

- **Estrutura do header do chat público**: Reorganizada para acomodar badge de admin e status lado a lado
- **Método `check_rate_limit()`**: Agora aceita parâmetro `$is_admin_mode` para aplicar limites diferenciados
- **Método `get_ai_response()`**: Agora aceita parâmetro `$is_admin_mode` para usar contexto e prompt apropriados
- **Demo HTML atualizado**: Nova seção demonstrando o Modo Administrador com todas as características visuais

#### Security (Segurança)

- **Isolamento de dados por role**: Implementada separação completa de contexto entre visitantes e administradores
- **Auditoria de requisições admin**: Todas as perguntas feitas por administradores são registradas com user_login e user_id

**Sistema de Prevenção de Cache de Páginas (v1.1.1)**

- **Nova classe `DPS_Cache_Control`**: Classe helper no plugin base que gerencia a prevenção de cache em todas as páginas do sistema DPS.
  - Envia headers HTTP de no-cache (`Cache-Control`, `Pragma`, `Expires`) para garantir que navegadores não armazenem páginas em cache.
  - Define constantes `DONOTCACHEPAGE`, `DONOTCACHEDB`, `DONOTMINIFY`, `DONOTCDN` e `DONOTCACHEOBJECT` para compatibilidade com plugins de cache populares (WP Super Cache, W3 Total Cache, LiteSpeed Cache, etc.).
  - Detecta automaticamente páginas com shortcodes DPS via hook `template_redirect`.
  - Desabilita cache em todas as páginas administrativas do DPS via hook `admin_init`.
- **Método `DPS_Cache_Control::force_no_cache()`**: Método público para forçar desabilitação de cache em qualquer contexto.
- **Método `DPS_Cache_Control::register_shortcode()`**: Permite que add-ons registrem shortcodes adicionais para prevenção automática de cache.
- **Integração em todos os shortcodes**: Todos os shortcodes do sistema agora chamam `DPS_Cache_Control::force_no_cache()` para garantir camada extra de proteção:
  - Base: `dps_base`, `dps_configuracoes`
  - Client Portal: `dps_client_portal`, `dps_client_login`
  - Agenda: `dps_agenda_page`, `dps_agenda_dashboard`
  - Groomers: `dps_groomer_portal`, `dps_groomer_login`, `dps_groomer_dashboard`, `dps_groomer_agenda`, `dps_groomer_review`, `dps_groomer_reviews`
  - Services: `dps_services_catalog`
  - Finance: `dps_fin_docs`
  - Registration: `dps_registration_form`
  - AI: `dps_ai_chat`

**Formulário de Cadastro - Terceira Etapa com Preferências de Produtos (v2.0.0)**

- **Terceira etapa no Registration Add-on**: O formulário de cadastro agora possui 3 etapas:
  1. Dados do Cliente
  2. Dados dos Pets
  3. Preferências e Restrições de Produtos
- **Campos de preferências por pet**: Para cada pet cadastrado, é possível definir:
  - Preferência de shampoo (hipoalergênico, antisséptico, pelagem branca/escura, antipulgas, hidratante)
  - Preferência de perfume (suave, intenso, sem perfume/proibido, hipoalergênico)
  - Preferência de adereços (lacinho, gravata, lenço, bandana, sem adereços)
  - Outras restrições de produtos (campo livre)
- **Novos meta fields do pet**: `pet_shampoo_pref`, `pet_perfume_pref`, `pet_accessories_pref`, `pet_product_restrictions`
- **Badge visual na agenda**: Pets com restrições de produtos exibem badge 🧴 ao lado do nome com tooltip detalhado
- **Portal do Cliente**: Clientes podem visualizar e editar as preferências de produtos de seus pets
- **Admin Panel**: Nova seção "Preferências de Produtos" no formulário de edição de pets
- **Formulário de Agendamento**: Exibe as preferências de produtos na seção de informações do pet
- **~110 linhas de CSS** para estilização da nova etapa no formulário de cadastro
- **JavaScript atualizado** para navegação entre 3 etapas com validação e renderização dinâmica

**Página de Configurações Frontend - Fase 6: Aba Agenda (v2.0.0)**

- **Aba Agenda (Agenda Add-on)**: Nova aba de configurações para gerenciamento da agenda de atendimentos:
  - Selector de página da agenda (`dps_agenda_page_id`)
  - Configuração de capacidade por horário (manhã 08:00-11:59 e tarde 12:00-17:59)
  - Campo de endereço do petshop para GPS e navegação (sincronizado com aba Empresa)
  - Integração com `DPS_Agenda_Capacity_Helper` para cálculos de heatmap de lotação
- **Validação e segurança**: Nonce verification, capability check (`manage_options`), sanitização de inputs e log de auditoria
- **Responsividade**: Estilos herdados do sistema de abas garantem funcionamento em mobile

**Página de Configurações Frontend - Fase 4: Abas de Automação (v2.0.0)**

- **Aba Notificações (Push Add-on)**: Nova aba de configurações para gerenciamento de relatórios automáticos por email:
  - Configuração de horário e destinatários para relatório da manhã (agenda do dia)
  - Configuração de horário e destinatários para relatório financeiro do final do dia
  - Configuração de dia da semana, horário e período de inatividade para relatório semanal de pets inativos
  - Campos para integração com Telegram (token do bot e chat ID)
  - Checkboxes individuais para ativar/desativar cada tipo de relatório
  - Visualização do próximo envio agendado para cada relatório
- **Aba Financeiro - Lembretes (Finance Add-on)**: Nova aba de configurações para gerenciamento de lembretes automáticos de pagamento:
  - Checkbox para habilitar/desabilitar lembretes automáticos
  - Configuração de dias antes do vencimento para envio de lembrete preventivo
  - Configuração de dias após vencimento para envio de cobrança
  - Templates de mensagem personalizáveis com placeholders ({cliente}, {pet}, {data}, {valor}, {link}, {pix}, {loja})
- **Validação de formulários**: Validação de formato de horário (HH:MM), lista de emails e limites numéricos
- **Estilos CSS**: Novos estilos para campos de horário, selects, textareas e badges de próximo agendamento

**Formulário de Agendamento - Melhorias de UX (v1.5.0)**

- **TaxiDog em card próprio**: O campo TaxiDog agora é exibido em um card visual destacado com cores dinâmicas (amarelo quando desativado, verde quando ativado).
- **Campo de valor TaxiDog simplificado**: Removido o label "Valor TaxiDog" quando o serviço é selecionado, mostrando apenas o campo de valor com prefixo R$.
- **Botão "Adicionar desconto"**: Novo botão abaixo de "Adicionar Serviço Extra" para aplicar descontos ao agendamento simples, com campo de descrição e valor.
- **Exibição de preços por porte**: Os serviços agora exibem os preços por porte (P, M, G) de forma identificada sem campo de edição, facilitando a visualização.
- **Valores por pet em assinaturas**: Para agendamentos de assinatura com múltiplos pets, cada pet é listado com seu porte e campo individual para inserção do valor.
- **"Valor total da assinatura" reposicionado**: Campo movido para o final da seção, abaixo do botão "Adicionar Serviço Extra".
- **Desconto refletido no resumo**: O resumo do agendamento agora exibe o desconto aplicado e calcula corretamente o valor total.
- **Novos estilos visuais**: ~260 linhas de CSS para cards de serviço, seção de desconto, valores por pet em assinatura e preços por porte.

#### Changed (Alterado)

**Formulário de Agendamento - Simplificação da Seção "Cliente e Pet(s)" (v1.5.0)**

- **Textos de orientação removidos**: Removidos os textos "Selecione os pets do cliente escolhido..." e "Escolha um cliente para visualizar os pets disponíveis.".
- **Área de busca removida**: Removida a barra de busca de pets por nome, tutor ou raça, simplificando a interface.
- **Nome do proprietário oculto nos cards de pets**: Nos cards de seleção de pets, o nome do proprietário não é mais exibido, já que o cliente já foi selecionado acima.

**Client Portal Add-on - Modernização Completa da Aba Galeria (v3.2.0)**

- **Header moderno padronizado**: Título 📸 com subtítulo descritivo seguindo padrão global DPS (`.dps-section-title`).
- **Cards de métricas**: Três cards exibindo total de pets, fotos de perfil e fotos de atendimentos com destaque visual.
- **Filtro por pet**: Botões para filtrar galeria por pet específico ou visualizar todos, com estilo pill moderno.
- **Cards de pet organizados**: Cada pet em card próprio (`.dps-gallery-pet-card`) com header destacado e grid de fotos.
- **Grid de fotos moderno**: Layout responsivo com cards de foto (`.dps-gallery-photo`) incluindo overlay de zoom ao hover.
- **Suporte a fotos de atendimento**: Nova meta key `pet_grooming_photos` para armazenar fotos enviadas pelos administradores após banho/tosa.
- **Diferenciação visual**: Fotos de perfil com borda azul, fotos de atendimento com borda verde.
- **Ações por foto**: Botões de compartilhamento WhatsApp e download direto em cada item.
- **Lightbox integrado**: Visualização ampliada de fotos com fechamento por ESC ou clique fora, caption e botão de download.
- **Estado vazio orientador**: Mensagem amigável com ícone e CTA para WhatsApp quando não há pets cadastrados.
- **Nota informativa**: Texto explicativo sobre adição de fotos pela equipe após atendimentos.
- **Oito novos métodos helper**: `render_gallery_metrics()`, `render_gallery_pet_filter()`, `render_pet_gallery_card()`, `render_gallery_photo_item()`, `render_gallery_empty_state()`, `parse_grooming_photo()`.
- **~400 linhas de CSS**: Novos estilos para métricas, filtros, cards de pet, grid de fotos, lightbox e responsividade mobile.
- **~170 linhas de JavaScript**: Handlers para filtro de pets (`handleGalleryFilter()`) e lightbox (`handleGalleryLightbox()`).

**Client Portal Add-on - Modernização Completa da Aba Agendamentos (v3.1.0)**

- **Métricas rápidas no topo**: Dois cards destacando número de próximos agendamentos e total de atendimentos realizados.
- **Seção de Próximos Agendamentos em cards**: Agendamentos futuros exibidos em cards visuais modernos com data destacada, horário, pet, serviços e status.
- **Badges de urgência**: Labels "Hoje!" e "Amanhã" em destaque visual nos cards de agendamentos próximos.
- **Separação lógica de conteúdo**: Próximos agendamentos e histórico de atendimentos em seções distintas com hierarquia visual clara.
- **Oito novos métodos helper**: `render_appointments_metrics()`, `render_upcoming_appointments_section()`, `render_upcoming_appointment_card()`, `render_no_upcoming_state()`, `render_history_section()`, `render_history_row()`, `render_no_history_state()` e `get_status_class()`.
- **Badges de status coloridos**: Status de agendamentos com cores semânticas (verde para confirmado/pago, amarelo para pendente, vermelho para cancelado).
- **Estados vazios orientadores**: Mensagens amigáveis com ícones e CTA para WhatsApp quando não há agendamentos.
- **~170 linhas de CSS**: Novos estilos para métricas, cards de próximos agendamentos, badges de status e responsividade mobile.

**Stock Add-on - Modernização Completa do Layout da Aba Estoque (v1.2.0)**

- **Header da seção padronizado**: Título com ícone 📦 e subtítulo descritivo seguindo padrão global DPS (`.dps-section-title`).
- **Layout empilhado com cards**: Novo sistema de cards `.dps-surface` empilhados verticalmente, seguindo padrão de outras abas (Pets, Clientes, Serviços).
- **Card de resumo/estatísticas**: Exibe total de itens, estoque OK e estoque baixo usando `.dps-inline-stats--panel` com badges de status.
- **Card de alertas críticos**: Lista itens abaixo do mínimo em card destacado `.dps-surface--warning` com nome, quantidade e botão de edição.
- **Card de inventário completo**: Tabela responsiva de todos os itens com toolbar de filtros e paginação moderna.
- **Toolbar de filtros**: Botão para alternar entre "Ver todos" e "Mostrar apenas críticos".
- **Três novos métodos helper**: `calculate_stock_stats()`, `render_critical_items_list()` e `render_stock_table()` para melhor organização do código.
- **~150 linhas de CSS**: Novos estilos para layout stack, inline-stats, lista de críticos e toolbar.

**Stats Add-on - Modernização Completa do Layout da Aba Estatísticas (v1.5.0)**

- **Header da seção padronizado**: Título com ícone 📊 e subtítulo descritivo seguindo padrão global DPS (`.dps-section-title`).
- **Layout empilhado com cards**: Substituído `<details>` colapsáveis por cards `.dps-surface` empilhados verticalmente, seguindo padrão de outras abas (Pets, Clientes, Serviços).
- **Filtro de período em card dedicado**: Seletor de datas agora usa `.dps-surface--neutral` com título 📅 e layout responsivo melhorado.
- **Métricas financeiras com ícones**: Cards de receita, despesas e lucro agora exibem emojis contextuais (💵, 💸, 📊, 📈/📉).
- **Estados vazios amigáveis**: Mensagens para dados ausentes agora usam `.dps-stats-empty-state` com ícones centralizados.
- **Tabela de inativos melhorada**: Botão WhatsApp agora usa estilo pill com background verde (#ecfdf5), melhor legibilidade da data e destaque para pets nunca atendidos.
- **~550 linhas de CSS refatorado**: Novo `stats-addon.css` v1.5.0 com layout stack, cards com hover animation, métricas coloridas por tipo e espaçamento consistente.

#### Changed (Alterado)

**Stock Add-on - Melhorias de UX (v1.2.0)**

- **Descrições explicativas em cada seção**: Todos os cards agora incluem `.dps-surface__description` explicando o propósito.
- **Tabela responsiva**: Tabela de inventário usa classes `.dps-table` com responsividade mobile (cards em telas < 640px).
- **Paginação melhorada**: Layout flex com informações à esquerda e botões à direita, empilhando em mobile.
- **Remoção de estilos inline**: Substituídos todos os `style=""` por classes CSS dedicadas.
- **Botões com gradiente moderno**: `.button-primary` e `.button-secondary` agora herdam estilos globais do DPS.

**Stats Add-on - Melhorias de UX (v1.5.0)**

- **Descrições explicativas em cada seção**: Todos os cards de métricas agora incluem `.dps-surface__description` explicando o propósito e fonte dos dados.
- **Cores semânticas nas métricas**: Assinaturas ativas (verde), pendentes (amarelo), valor em aberto (vermelho) seguindo padrão de cores de status do Visual Style Guide.
- **Hierarquia visual clara**: Seções organizadas em ordem de importância: Visão Geral → Indicadores Avançados → Financeiro → Assinaturas → Serviços → Pets → Inativos.
- **Remoção de estilos inline**: Substituídos todos os `style=""` por classes CSS dedicadas para manutenibilidade e performance.
- **Formatação de código PHP**: Templates HTML agora usam indentação consistente e comentários explicativos.

#### Fixed (Corrigido)

**Backup Add-on - Correções de Documentação (v1.3.1)**

- **Erro de digitação corrigido**: Corrigido "identific ou" → "identificou" na documentação de auditoria de segurança (`docs/security/BACKUP_SECURITY_AUDIT.md`).

**Stats Add-on - Correção de PHP Warning no Cache Invalidator (v1.2.1)**

- **PHP Warning corrigido**: O método `invalidate_on_post_delete()` assumia que o segundo parâmetro era sempre um objeto WP_Post, mas o hook `trashed_post` passa `$post_id` (int) e `$previous_status` (string), causando warnings "Attempt to read property 'post_type' on string" ao mover posts para lixeira.
- **Separação de métodos**: Criados métodos separados para cada hook:
  - `invalidate_on_before_delete()`: Lida com o hook `before_delete_post` que recebe objeto WP_Post
  - `invalidate_on_trash()`: Lida com o hook `trashed_post` que recebe apenas post_id e busca o objeto internamente
- **Validação de tipo robusta**: Adicionada verificação `instanceof WP_Post` no método `invalidate_on_before_delete()` para garantir que o parâmetro é um objeto válido antes de acessar propriedades.

**Agenda Add-on - Validação Defensiva no Google Calendar Sync (v2.0.1)**

- **Validação preventiva adicionada**: Método `handle_delete_appointment()` agora valida que o segundo parâmetro é `instanceof WP_Post` antes de acessar propriedades, prevenindo potenciais warnings caso o hook seja usado incorretamente no futuro.
- **Consistência com correção do Stats Add-on**: Aplica o mesmo padrão de validação defensiva implementado no cache invalidator.

**AI Add-on - Correção das Configurações do Assistente de IA (v1.6.2)**

- **Configurações não editáveis corrigidas**: O uso de `wp_kses_post()` no Hub de IA (`class-dps-ai-hub.php`) removia elementos de formulário (`<input>`, `<select>`, `<textarea>`, `<form>`, `<button>`), tornando todas as configurações apenas texto sem possibilidade de edição.
- **Novo método `get_allowed_form_tags()`**: Criada lista personalizada de tags HTML permitidas que extende `wp_kses_post` com elementos de formulário essenciais para as configurações funcionarem.
- **Correção em todas as 7 abas do Hub**: Configurações, Analytics, Conversas, Base de Conhecimento, Testar Base, Modo Especialista e Insights agora usam `wp_kses()` com lista segura em vez de bypass total ou `wp_kses_post()`.
- **Campos de WhatsApp não salvavam**: Os campos de integração WhatsApp Business (enabled, provider, tokens, etc.) estavam presentes no formulário mas não eram processados no salvamento. Adicionados 11 campos ao método `maybe_handle_save()`.
- **Campos de Sugestões Proativas não salvavam**: Os campos de sugestões proativas de agendamento (enabled, interval, cooldown, mensagens) não eram salvos. Adicionados 5 campos ao método `maybe_handle_save()`.

#### Security (Segurança)

**AI Add-on - Melhorias de Segurança no Hub de IA (v1.6.3)**

- **Validação de whatsapp_provider**: Adicionado novo método `sanitize_whatsapp_provider()` para validação explícita do campo `whatsapp_provider`, restringindo a valores permitidos ('meta', 'twilio', 'custom'). Valores inválidos agora retornam o padrão 'meta', evitando erros de configuração.
- **Limite de caracteres em campos textarea**: Campos `whatsapp_instructions`, `proactive_scheduling_first_time_message` e `proactive_scheduling_recurring_message` agora têm limite de 2000 caracteres (consistente com outros campos similares como `additional_instructions`).
- **Remoção de atributos perigosos em wp_kses**: Removido atributo `onclick` de links e `src` de scripts no método `get_allowed_form_tags()` para prevenir potenciais vulnerabilidades XSS. Scripts externos devem ser carregados via `wp_enqueue_script()`.
- **Documentação de data-* attributes**: Adicionados comentários explicativos sobre os atributos `data-*` permitidos e incluídos atributos genéricos adicionais (`data-id`, `data-value`, `data-type`) para compatibilidade com UIs de admin.

**Base Plugin - Correção do Shortcode [dps_configuracoes] (v1.1.1)**

- **Erro "Falha ao publicar. A resposta não é um JSON válido" corrigido**: O shortcode `[dps_configuracoes]` causava um PHP Fatal Error ao ser inserido no editor de blocos (Gutenberg). A classe `DPS_Settings_Frontend` referenciava `DPS_Logger::LEVEL_DEBUG` que não estava definida na classe `DPS_Logger`.
- **Constante LEVEL_DEBUG adicionada**: Adicionada constante `LEVEL_DEBUG = 'debug'` à classe `DPS_Logger` para suportar nível de log mais detalhado.
- **Método debug() adicionado**: Novo método `DPS_Logger::debug()` para consistência com os outros níveis de log (info, warning, error).
- **Ordem de prioridade de logs atualizada**: DEBUG (0) → INFO (1) → WARNING (2) → ERROR (3), permitindo filtrar logs por nível mínimo configurado.
- **Causa raiz**: A aba "Empresa" do shortcode de configurações usava `DPS_Logger::LEVEL_DEBUG` no dropdown de níveis de log, mas a constante nunca foi definida na classe.

**Stats Add-on - Correções na Aba Estatísticas (v1.5.1)**

- **Erro de Finance não detectado no comparativo de períodos**: O erro `finance_not_active` retornado por `get_financial_totals()` agora é corretamente propagado para o array `current` em `get_period_comparison()`. Anteriormente, se o Finance Add-on não estivesse ativo, as métricas financeiras exibiam zero sem mostrar a mensagem de aviso adequada.
- **Datas do período adicionadas ao array current**: O array `current` em `get_period_comparison()` agora inclui `start_date` e `end_date` para consistência com o array `previous` e melhor tratamento de dados no frontend.
- **Nota do período anterior com validação**: A nota "Comparando com período anterior" agora verifica se as datas estão preenchidas antes de tentar formatá-las, evitando exibição de datas incorretas quando os dados estão incompletos.

**Push Add-on - Correção de Relatórios por Email (v1.3.1)**

- **Relatório da manhã vazio corrigido**: A query de agendamentos do dia usava `post_type => 'dps_appointment'` ao invés de `post_type => 'dps_agendamento'`, fazendo com que nenhum agendamento fosse encontrado. Corrigido para usar o post_type correto `dps_agendamento`.
- **Relatório semanal de pets inativos corrigido**: A query SQL também usava `post_type = 'dps_appointment'`, causando o mesmo problema. Corrigido para `dps_agendamento`.
- **Horário de envio não respeitando configuração**: Adicionado método `reschedule_all_crons()` que é chamado explicitamente após salvar configurações, garantindo que todos os crons sejam reagendados com os novos horários. Anteriormente, os hooks `update_option_*` podiam não ser disparados se os valores não mudassem, ou podiam haver problemas de cache.
- **Cache de opções limpo antes de reagendar**: O novo método `reschedule_all_crons()` limpa o cache de todas as opções relevantes antes de reagendar, evitando uso de valores desatualizados.

**Client Portal Add-on - Correção de Solicitação de Link de Acesso (v2.4.4)**

- **Erro "Erro ao processar solicitação" corrigido**: O handler AJAX `dps_request_access_link_by_email` agora funciona tanto para usuários logados quanto não-logados no WordPress. Anteriormente, apenas `wp_ajax_nopriv_*` estava registrado, causando falha para clientes logados no WP.
- **Handler `dps_request_portal_access` corrigido**: Mesmo problema - adicionado `wp_ajax_*` para suportar usuários logados.
- **Tratamento de erros JavaScript robusto**: Melhorado o código de tratamento de resposta AJAX para verificar `data.data` antes de acessar propriedades, evitando erros silenciosos.
- **Mensagem de erro mais clara**: Erro de conexão agora exibe "Erro de conexão. Verifique sua internet e tente novamente." em vez de mensagem genérica.

**Client Portal Add-on - Melhoria do Email de Link de Acesso (v2.4.4)**

- **Email em HTML moderno**: O email com link de acesso ao portal agora usa template HTML responsivo com:
  - Logo e branding do site
  - Botão CTA azul com gradiente e sombra
  - Aviso de validade em card amarelo destacado
  - Link alternativo para copiar/colar
  - Footer com copyright
- **Compatibilidade com clientes de email**: Template testado para Gmail, Outlook e outros clientes principais usando estilos inline.

**Base Plugin - Melhoria da Mensagem de WhatsApp (v1.4.0)**

- **Mensagem de solicitação de acesso ao portal melhorada**: Nova mensagem é mais clara e amigável:
  - Antes: `Olá, gostaria de acesso ao Portal do Cliente. Meu nome é ______ e o nome do meu pet é ______.`
  - Depois: `Olá! 🐾 Gostaria de receber o link de acesso ao Portal do Cliente para acompanhar os serviços do meu pet. Meu nome: (informe seu nome) | Nome do pet: (informe o nome do pet)`
- **Emoji adicionado**: 🐾 no início da mensagem para torná-la mais amigável e visual.
- **Instruções claras**: Campos a preencher agora usam parênteses ao invés de underscores para maior clareza.

**Registration Add-on - Modal de Confirmação para Duplicatas (v1.3.1)**

- **Modal de confirmação para admins**: Quando um administrador tenta cadastrar um cliente com dados já existentes (email, telefone ou CPF), um modal é exibido com três opções:
  - **Cancelar**: Fecha o modal e não prossegue com o cadastro.
  - **Ver cadastro existente**: Redireciona para a página do cliente já cadastrado.
  - **Continuar mesmo assim**: Cria o novo cliente com os dados duplicados.
- **Verificação AJAX**: Os dados são verificados via AJAX antes do envio do formulário, sem recarregar a página.
- **Identificação de campos duplicados**: O modal mostra exatamente quais campos são duplicados (Email, Telefone, CPF).
- **Rate limiting bypassed para admins**: Administradores (`manage_options`) não são mais limitados a 3 cadastros por hora.
- **reCAPTCHA bypassed para admins**: Verificação anti-spam não é aplicada quando o usuário logado é administrador.
- **Spam check bypassed para admins**: Hooks de validação adicional (`dps_registration_spam_check`) são pulados para administradores.
- **Causa raiz**: Restrições de segurança do formulário público estavam impedindo administradores de cadastrar múltiplos clientes em sequência.

**Groomers Add-on - Correção de HTML Malformado (v1.8.6)**

- **Aba GROOMERS em branco corrigida**: Removido `</div>` extra na função `render_groomers_section()` que causava HTML malformado e impedia a renderização do conteúdo da aba.
- **Causa raiz**: Havia 62 tags `</div>` para 61 tags `<div>` abertas, resultando em estrutura HTML quebrada.

**Finance Add-on - Correção de Cache Busting (v1.6.1)**

- **Version bump para invalidar cache**: Atualizada versão do add-on de 1.6.0 para 1.6.1 para forçar navegadores e CDNs a carregar o CSS corrigido do PR #439.
- **Causa raiz identificada**: O PR #439 corrigiu margens da aba FINANCEIRO e visibilidade da aba GROOMER, mas não atualizou a constante `DPS_FINANCE_VERSION`, resultando em cache stale.

**Stats Add-on - Correções de Compatibilidade (v1.5.0)**

- **Mensagem de erro da API formatada**: Aviso de "API não disponível" agora usa `.dps-surface--warning` em vez de HTML inline.
- **Botões com estilos consistentes**: `.button-primary` e `.button-secondary` agora herdam corretamente os estilos globais do DPS.

**Groomers Add-on - Modernização do Layout da Aba Equipe (v1.8.4)**

- **Header da seção modernizado**: Título com ícone 👥 e subtítulo descritivo seguindo padrão global DPS.
- **Sub-abas estilo card**: Navegação por sub-abas (Equipe, Relatórios, Comissões) agora usa cards visuais com ícone, título e descrição, similar ao padrão da Agenda.
- **Cards de estatísticas da equipe**: Novo bloco de métricas exibindo total de profissionais, ativos, inativos e freelancers no topo da sub-aba Equipe.
- **Breakdown por função**: Exibição de badges com contagem por tipo de profissional (Groomer, Banhista, Auxiliar, Recepção).
- **~300 linhas de CSS**: Novas seções 20-24 no `groomers-admin.css` com estilos para header, sub-abas card, estatísticas e melhorias visuais.
- **Métodos helper**: Adicionados `get_team_stats()` e `render_team_stats_cards()` para calcular e renderizar estatísticas da equipe.

#### Changed (Alterado)

**Groomers Add-on - Melhorias Visuais (v1.8.4)**

- **Avatares com cores por função**: Gradientes de cores específicos para cada tipo de profissional (azul=groomer, verde=banhista, amarelo=auxiliar, roxo=recepção).
- **Tooltip no status dot**: Indicador de status agora exibe tooltip CSS puro ao passar o mouse.
- **Empty state melhorado**: Mensagem de lista vazia com visual mais limpo e centralizado.
- **Accordions do formulário**: Melhor feedback visual quando aberto com borda azul.

**Finance Add-on - Modernização Visual da Aba Financeiro (v1.8.0)**

- **Layout moderno padronizado**: Aba Financeiro agora segue o padrão visual global do sistema DPS com classes `dps-surface` e `dps-section-title`.
- **Título com ícone e subtítulo**: Header da seção usa estrutura padronizada com emoji 💰 e descrição explicativa.
- **Dashboard de resumo encapsulado**: Cards de receitas, despesas, pendentes e saldo agora estão dentro de `dps-surface--info` com título e descrição.
- **Formulário de pagamento parcial moderno**: Novo grid `dps-partial-summary` com destaque visual para valor restante.
- **Estado vazio amigável**: Quando não há transações, exibe mensagem com ícone 📭 e dica para criar primeira transação.
- **Demo HTML**: Criado arquivo `docs/layout/admin/demo/finance-layout-demo.html` para visualização offline do layout.
- **~200 linhas de CSS**: Novas seções 21-25 no `finance-addon.css` com estilos para grid, surfaces e componentes modernos.

#### Changed (Alterado)

**Finance Add-on - Reorganização de Estrutura (v1.8.0)**

- **Formulário de nova transação**: Agora usa `dps-surface--info` com descrição explicativa e estrutura colapsável.
- **Lista de transações**: Usa `dps-surface--neutral` com título 📋, descrição e filtros visuais melhorados.
- **Seção de cobrança rápida**: Usa `dps-surface--warning` (destaque amarelo) com descrição sobre WhatsApp.
- **Toolbar de configurações**: Botão de configurações agora fica em toolbar dedicada ao invés de inline.
- **Documentação atualizada**: `docs/layout/admin/FINANCE_LAYOUT_IMPROVEMENTS.md` reescrito para v1.8.0 com todas as novas classes e estruturas.

#### Fixed (Corrigido)

**Finance Add-on - Acessibilidade (v1.8.0)**

- **Removidos emojis de selects de formulário**: Melhora compatibilidade com leitores de tela (acessibilidade).
- **Comentários CSS explicativos**: Adicionados comentários no CSS sobre comportamento do grid layout.

**Registration Add-on - Modernização Visual e Funcionalidades Admin (v1.3.0)**

- **Cards de resumo completos**: Agora exibem todos os campos preenchidos pelo usuário (CPF, data de nascimento, Instagram, Facebook, autorização de foto, como conheceu) no resumo do tutor, e todos os campos do pet (espécie, peso, pelagem, cor, nascimento, sexo, alerta de pet agressivo) no resumo dos pets.
- **Indicadores de campo obrigatório**: Adicionado asterisco vermelho (*) nos campos obrigatórios (Nome e Telefone) com legenda explicativa no topo do formulário.
- **Banner informativo para admin**: Quando um administrador acessa o formulário público, é exibido um banner informativo com links rápidos para configurações e cadastros pendentes.
- **Opções de cadastro rápido para admin**: Administradores podem ativar cadastros imediatamente (pulando confirmação de email) e escolher se desejam enviar email de boas-vindas.
- **Ícones de espécie nos cards de pet**: O resumo agora exibe emoji correspondente à espécie selecionada (🐶 Cachorro, 🐱 Gato, 🐾 Outro).
- **Formatação de datas no resumo**: Datas de nascimento são formatadas para exibição brasileira (DD/MM/AAAA).
- **Documentação de análise visual**: Criado documento `docs/forms/REGISTRATION_FORM_VISUAL_ANALYSIS.md` com análise profunda do visual do formulário e plano de melhorias.

#### Changed (Alterado)

**Registration Add-on - Melhorias Visuais (v1.3.0)**

- **Summary box com destaque**: Adicionada borda lateral azul (#0ea5e9) seguindo padrão do guia de estilo visual para chamar atenção do usuário.
- **Grid responsivo no resumo**: Campos do resumo agora são exibidos em grid de 2 colunas que adapta-se automaticamente a telas menores.
- **Transição suave entre steps**: Adicionada animação de opacidade (0.2s) para transição mais fluida entre passos do formulário.
- **Títulos de seção com emoji**: Seções do resumo agora têm emojis (👤 Tutor, 🐾 Pets) para melhor identificação visual.

**Communications Add-on - Funcionalidades Avançadas (v0.3.0)**

- **Histórico de Comunicações**: Nova tabela `dps_comm_history` para registro de todas as mensagens enviadas (WhatsApp, e-mail, SMS). Inclui status de entrega, metadata, cliente/agendamento associado e timestamps de criação/atualização/entrega/leitura.
- **Retry com Exponential Backoff**: Sistema automático de retry para mensagens que falham. Máximo de 5 tentativas com delays exponenciais (1min, 2min, 4min, 8min, 16min) + jitter aleatório para evitar thundering herd. Cap máximo de 1 hora.
- **REST API de Webhooks**: Endpoints para receber status de entrega de gateways externos:
  - `POST /wp-json/dps-communications/v1/webhook/{provider}` - Recebe webhooks de Evolution API, Twilio ou formato genérico
  - `GET /wp-json/dps-communications/v1/webhook-url` - Retorna URLs e preview do secret para configuração (admin only)
  - `GET /wp-json/dps-communications/v1/stats` - Estatísticas de comunicações e retries (admin only)
  - `GET /wp-json/dps-communications/v1/history` - Histórico de comunicações com filtros (admin only)
- **Suporte a múltiplos providers**: Webhooks suportam Evolution API, Twilio e formato genérico, com mapeamento automático de status.
- **Webhook Secret**: Secret automático gerado para autenticação de webhooks via header `Authorization: Bearer` ou `X-Webhook-Secret`.
- **Limpeza automática**: Cron job diário para limpeza de transients de retry expirados e método para limpar histórico antigo (padrão 90 dias).
- **Classes modulares**: Novas classes `DPS_Communications_History`, `DPS_Communications_Retry` e `DPS_Communications_Webhook` seguindo padrão singleton.

**Communications Add-on - Verificação Funcional (v0.3.0)**

- **JavaScript para UX**: Novo arquivo `communications-addon.js` com prevenção de duplo clique, validação client-side de e-mail e URL, e feedback visual durante submissão.
- **Seção de Webhooks na UI**: Nova seção na página admin exibindo URLs de webhook e secret com botões para mostrar/ocultar e copiar para clipboard.
- **Seção de Estatísticas**: Dashboard com cards visuais mostrando contagem de mensagens por status (pendentes, enviadas, entregues, lidas, falhas, reenviando) com ícones e cores temáticas.
- **Validação client-side**: Campos de e-mail e URL do gateway agora são validados em tempo real no navegador, com mensagens de erro em português.
- **Prevenção de duplo clique**: Botão de salvar é desabilitado durante submissão e exibe spinner "Salvando..." para evitar envios duplicados.
- **Melhorias de acessibilidade**: Adicionados `aria-describedby` nos campos, `:focus-visible` para navegação por teclado, e feedback visual em rows com foco.
- **Mensagens de erro persistidas**: Erros de nonce/permissão agora são persistidos via transient e exibidos corretamente após redirect.
- **Secret mascarado no REST**: Endpoint `/webhook-url` agora retorna apenas preview mascarado do secret (`abc***xyz`) em vez do valor completo.

#### Security (Segurança)

**Backup Add-on - Correções de Revisão de Código (v1.3.1)**

- **Placeholder SQL inválido corrigido**: Removido uso de `%1s` (placeholder não suportado) em `$wpdb->prepare()` para queries de tabelas. Como as tabelas já são validadas com regex `^[a-zA-Z0-9_]+$`, a interpolação direta é segura e não causa erros.
- **Cast explícito para INTEGER em queries**: Adicionado `CAST(pm.meta_value AS UNSIGNED)` nas queries de validação de integridade referencial para garantir comparação correta entre meta_value (string) e post ID (integer), melhorando performance e confiabilidade.
- **Validação de admin_email fallback**: O fallback para email do administrador agora valida que o email é válido antes de usar, evitando configurações com emails inválidos.
- **Sanitização de array keys preserva maiúsculas**: Substituído `sanitize_key()` por `preg_replace('/[^\w\-]/', '')` para preservar case-sensitivity em chaves de array, evitando quebrar configurações que dependem de maiúsculas.
- **Validação de valores falsy em mapeamento de IDs**: Adicionada verificação `! empty()` e `> 0` para owner_id, appointment_client_id e appointment_pet_id antes de tentar mapear, evitando processamento incorreto de valores zerados ou vazios.

**Communications Add-on - Auditoria de Segurança Completa (v0.2.1)**

- **Chave de API exposta**: Campo de API key do WhatsApp alterado de `type="text"` para `type="password"` com `autocomplete="off"` para evitar exposição casual.
- **SSRF Prevention**: Implementada validação rigorosa de URL do gateway WhatsApp bloqueando endereços internos (localhost, IPs privados 10.x, 172.16-31.x, 192.168.x, metadata endpoints de cloud). URLs HTTP só são aceitas em modo debug.
- **PII Leak em Logs**: Removida exposição de dados pessoais (telefones, mensagens, emails) em logs. Implementado método `safe_log()` que mascara dados sensíveis antes de logar.
- **PII Leak em error_log**: Funções legadas `dps_comm_send_whatsapp()` e `dps_comm_send_sms()` não expõem mais telefones e mensagens no error_log do PHP.
- **Verificação de DPS_Logger**: Adicionada verificação de existência da classe `DPS_Logger` antes de usar, evitando fatal errors quando o plugin base não está ativo.
- **Timeout preparado**: Adicionada constante `REQUEST_TIMEOUT` (30s) e exemplo de implementação segura de `wp_remote_post()` com timeout, sslverify e tratamento de erro para futura integração com gateway.
- **Validação de URL dupla**: Gateway WhatsApp valida URL novamente antes do envio (`filter_var()`) como double-check de segurança.

#### Fixed (Corrigido)

**Communications Add-on - Correções Funcionais (v0.3.0)**

- **CSS class do container**: Corrigida classe CSS do container (`wrap` → `wrap dps-communications-wrap`) para aplicar estilos customizados.
- **Estilos para password**: Adicionados estilos para `input[type="password"]` que estavam faltando no CSS responsivo.
- **ID do formulário**: Adicionado `id="dps-comm-settings-form"` para permitir binding de eventos JavaScript.
- **Validação de número WhatsApp**: Número do WhatsApp da equipe agora é sanitizado removendo caracteres inválidos.
- **Grid de estatísticas responsivo**: Grid de cards de estatísticas adapta-se automaticamente a diferentes tamanhos de tela.

**Compatibilidade PHP 8.1+ - Múltiplos Add-ons**

- **Deprecation warnings em strpos/str_replace/trim**: Corrigidos warnings do PHP 8.1+ que ocorriam durante ativação dos plugins. Adicionado cast `(string)` para parâmetros `$hook` em 10 métodos `enqueue_*_assets()` nos add-ons: Agenda, AI, Backup, Base, Client Portal, Communications, Payment.
- **trim(get_option()) sem valor padrão**: Corrigido em `class-dps-client-portal.php` para usar valor padrão vazio e cast `(string)`.
- **Domain Path incorreto**: Corrigido caminho do text domain no plugin Subscription de `/../languages` para `/languages`.

**Communications Add-on - Correções de Bugs (v0.2.1)**

- **uninstall.php corrigido**: Arquivo de desinstalação agora remove corretamente a option `dps_comm_settings` (principal) além de `dps_whatsapp_number` e options legadas.
- **Log context sanitizado**: Contexto de logs agora mascara chaves sensíveis (phone, to, email, message, body, subject, api_key) para compliance com LGPD/GDPR.

**Push Notifications Add-on - Auditoria de Segurança Completa (v1.3.0)**

- **SQL Injection em uninstall.php**: Corrigido uso de query direta sem `$wpdb->prepare()` na exclusão de user meta durante desinstalação.
- **SSRF em Push API**: Adicionada validação de whitelist de hosts permitidos para endpoints de push (FCM, Mozilla, Windows, Apple) antes de enviar requisições. Endpoints não reconhecidos são rejeitados.
- **SSRF em Telegram API**: Implementada validação de formato do token do bot e chat ID antes de construir URLs da API Telegram. Token validado com regex rigoroso.
- **Sanitização de Subscription JSON**: Adicionada validação de JSON com `json_last_error()`, validação de estrutura do objeto subscription, e sanitização de chaves criptográficas (p256dh, auth).
- **Validação de Endpoint Push**: Endpoints de push agora são validados contra lista de hosts conhecidos e devem usar HTTPS.
- **Autorização em unsubscribe AJAX**: Adicionada verificação de capability `manage_options` para cancelar inscrições push (antes qualquer usuário logado podia cancelar).
- **Log Level Injection**: Adicionada whitelist de níveis de log permitidos (info, error, warning, debug) para evitar execução de métodos arbitrários via `call_user_func()`.
- **Sanitização de data em transações**: Validação de formato de data (Y-m-d) antes de consultas ao banco de dados.
- **Escape de erro Telegram**: Descrição de erro retornada pela API Telegram agora é sanitizada com `sanitize_text_field()`.
- **Token oculto na UI**: Campo de token do Telegram agora usa `type="password"` para evitar exposição casual.
- **phpcs annotations**: Adicionadas anotações de ignorar para queries diretas necessárias com justificativas.

#### Added (Adicionado)

**Push Notifications Add-on - Verificação Funcional e UX (v1.3.0)**

- **Prevenção de duplo clique**: Botão de salvar configurações é desabilitado durante envio e exibe spinner "Salvando..." para evitar submissões duplicadas.
- **Validação de emails client-side**: Campos de email são validados em tempo real ao perder foco, exibindo mensagens de erro específicas para emails inválidos.
- **Validação de dias de inatividade**: Campo numérico valida e corrige valores fora do intervalo (7-365 dias) tanto no client quanto no servidor.
- **Mensagens de feedback visuais**: Adicionado `settings_errors('dps_push')` para exibir mensagens de sucesso/erro após salvar configurações.
- **Strings internacionalizadas em JS**: Estados de loading ("Salvando...", "Enviando...", "Testando...") agora são traduzíveis via `wp_localize_script()`.
- **Service Worker melhorado**: Removidos caminhos hardcoded de ícones. Ícones agora são definidos dinamicamente pelo payload da notificação.
- **Estilos de acessibilidade**: Adicionado `:focus-visible` para navegação por teclado em campos de formulário.
- **Hook corrigido**: Movido `maybe_handle_save` de `init` para `admin_init` para garantir exibição correta de `settings_errors()`.

**Registration Add-on - Auditoria de Segurança Completa (v1.2.2)**

- **Sanitização de entrada aprimorada**: Adicionado `wp_unslash()` antes de `sanitize_*` em todos os campos do formulário de cadastro para tratamento correto de magic quotes.
- **Validação de coordenadas**: Coordenadas de latitude (-90 a 90) e longitude (-180 a 180) agora são validadas como valores numéricos antes de serem salvas.
- **Whitelist para campos de seleção**: Campos de espécie, porte e sexo do pet agora são validados contra lista branca de valores permitidos.
- **Validação de peso do pet**: Campo de peso valida se é número positivo e razoável (máximo 500kg).
- **Validação de data de nascimento**: Data de nascimento do pet é validada como data válida e não-futura.
- **Escape de placeholders em email**: Placeholders `{client_name}` e `{business_name}` no template de email de confirmação agora são escapados com `esc_html()` para prevenir XSS.
- **Dados sanitizados em filter**: O filter `dps_registration_spam_check` agora recebe um array com dados sanitizados em vez do `$_POST` bruto.
- **wp_safe_redirect**: Substituído `wp_redirect()` por `wp_safe_redirect()` no redirecionamento após cadastro bem-sucedido.
- **Header Retry-After em rate limit**: Resposta 429 da REST API agora inclui header `Retry-After` com tempo de espera em segundos.
- **Sanitização de arrays de pets**: Campos de pets enviados como arrays agora aplicam `wp_unslash()` antes de sanitizar.
- **uninstall.php atualizado**: Arquivo de desinstalação agora remove todas as options, transients e cron jobs criados pelo add-on.
- **Escape de wildcards LIKE**: Busca de cadastros pendentes agora escapa caracteres especiais (%, _) para prevenir wildcard injection.

#### Added (Adicionado)

**Registration Add-on - Verificação Funcional e UX (v1.2.3)**

- **Prevenção de duplo clique no admin**: Botão de salvar configurações é desabilitado durante o envio e exibe texto "Salvando..." para evitar submissões duplicadas.
- **Estilos para botão desabilitado**: CSS atualizado com estilos visuais para botões desabilitados e estado de loading com spinner animado.
- **Mensagem de "sem resultados" melhorada**: Página de cadastros pendentes agora exibe mensagem estilizada como notice quando não há resultados.
- **Estilos de erros JS animados**: Container de erros de validação client-side agora inclui animação shake para maior visibilidade.

**Registration Add-on - Template de Email e Gerenciamento (v1.2.4)**

- **Template de email moderno**: Redesenhado template padrão do email de confirmação de cadastro com layout responsivo, cores vibrantes, botão de CTA destacado e visual profissional seguindo padrão dos outros emails do sistema.
- **Seção de gerenciamento de emails**: Reorganizada interface de configurações com nova seção dedicada "📧 Gerenciamento de Emails" com dicas claras e exemplos de placeholders.
- **Funcionalidade de teste de email**: Nova seção "🧪 Teste de Envio de Emails" permite enviar emails de teste (confirmação ou lembrete) para qualquer endereço, facilitando validação de configurações e verificação visual do template.
- **AJAX para envio de teste**: Endpoint seguro `wp_ajax_dps_registration_send_test_email` com verificação de nonce e capability para envio de emails de teste.
- **Aviso visual em emails de teste**: Emails de teste incluem banner de aviso destacado informando que se trata de teste e que links não são funcionais.

**Payment Add-on - Verificação Funcional e UX (v1.2.0)**

- **Indicador de status de configuração**: Página de configurações exibe badge "Integração configurada" ou "Configuração pendente" com informações sobre o que falta configurar.
- **Prevenção de duplo clique**: Botão de salvar é desabilitado durante o envio e exibe texto "Salvando..." para evitar submissões duplicadas.
- **Classe wrapper CSS**: Página de configurações usa classe `dps-payment-wrap` para estilos responsivos e consistentes.
- **Acessibilidade A11y**: Campos de formulário com atributos `id`, `aria-describedby`, e `rel="noopener"` em links externos. Adicionada classe `screen-reader-text` para textos apenas para leitores de tela.
- **Focus visible**: Estilos CSS para navegação por teclado com outline visível em elementos focados.
- **Placeholder no campo PIX**: Campo de chave PIX agora exibe placeholder de exemplo para orientar o usuário.

**Subscription Add-on - Auditoria de Segurança Completa (v1.3.0)**

- **Path Traversal em exclusão de arquivos**: Corrigida vulnerabilidade em `delete_finance_records()` onde a conversão de URL para path do sistema poderia ser manipulada. Agora valida que o arquivo está dentro do diretório de uploads usando `realpath()` e `wp_delete_file()`.
- **Verificação de existência de tabela SQL**: Adicionada verificação `SHOW TABLES LIKE` antes de operações SQL em `create_or_update_finance_record()` e `delete_finance_records()` para prevenir erros quando a tabela `dps_transacoes` não existe.
- **Validação de tipo de post em todas as ações**: Todas as ações GET e POST (cancel, restore, delete, renew, delete_appts, update_payment) agora validam que o ID corresponde a um post do tipo `dps_subscription` antes de executar operações.
- **wp_redirect vs wp_safe_redirect**: Substituídos todos os usos de `wp_redirect()` por `wp_safe_redirect()` para prevenir vulnerabilidades de open redirect.
- **Sanitização reforçada em save_subscription**: Implementada validação completa de formato de data (Y-m-d), horário (H:i), frequência (whitelist), existência de cliente/pet, e preço positivo.
- **Validação de nonces melhorada**: Substituído operador `??` por `isset()` com `wp_unslash()` e `sanitize_text_field()` em todas as verificações de nonce.
- **Validação de status de pagamento**: Adicionada whitelist de status permitidos (pendente, pago, em_atraso) na atualização de status de pagamento.
- **API Mercado Pago**: Adicionada validação de URL retornada (`filter_var(..., FILTER_VALIDATE_URL)`), verificação de código de resposta HTTP, e logging seguro sem expor token de acesso.
- **hook handle_subscription_payment_status**: Adicionada validação de existência e tipo de assinatura, formato de cycle_key (regex `^\d{4}-\d{2}$`), e cast para string antes de `strtolower()`.
- **Formatos de insert/update wpdb**: Adicionados arrays de formato (`%d`, `%s`, `%f`) em todas as chamadas `$wpdb->insert()` e `$wpdb->update()` para prevenir SQL injection.
- **absint vs intval**: Substituídos todos os usos de `intval()` por `absint()` para IDs de posts, garantindo valores não-negativos.

#### Added (Adicionado)

**Subscription Add-on - Melhorias Funcionais e UX (v1.3.0)**

- **Feedback de validação**: Formulário agora exibe mensagens de erro específicas quando validação falha no servidor (campos obrigatórios, formato de data/hora, cliente/pet inválido).
- **Prevenção de duplo clique**: Botões de submit são desabilitados durante o envio do formulário para evitar submissões duplicadas.
- **Estado de loading visual**: Botões exibem animação de spinner e texto "Salvando..." durante operações.
- **Validação client-side**: JavaScript valida campos obrigatórios, formato de data e horário antes do envio.
- **Internacionalização de strings JS**: Strings do JavaScript agora são traduzíveis via `wp_localize_script()`.
- **Foco em campo com erro**: Formulário faz scroll automático para o primeiro campo com erro de validação.
- **Estilos de acessibilidade**: Adicionados estilos para `:focus-visible` e classe `.dps-sr-only` para leitores de tela.

**Base Plugin - Auditoria de Segurança Completa (v1.1.1)**

- **CSRF em GitHub Updater**: Adicionada verificação de nonce na função `maybe_force_check()` que permite forçar verificação de atualizações. Anteriormente, atacantes podiam forçar limpeza de cache via link malicioso.
- **CSRF em Geração de Histórico do Cliente**: Implementada proteção CSRF na geração de histórico do cliente e envio de email. A ação `dps_client_history` agora requer nonce válido.
- **Validação de MIME em Upload de Foto do Pet**: Implementada lista branca de MIME types permitidos (jpg, png, gif, webp) e validação adicional de tipo de imagem no upload de foto do pet.
- **Endpoint AJAX Exposto**: Removido o endpoint `wp_ajax_nopriv_dps_get_available_times` que permitia consulta de horários sem autenticação.
- **XSS em Resposta AJAX**: Substituído uso de `.html()` com concatenação de strings por APIs DOM seguras (`.text()` e `.attr()`) no carregamento de horários disponíveis.
- **wp_redirect vs wp_safe_redirect**: Substituídos todos os usos de `wp_redirect()` por `wp_safe_redirect()` para prevenir vulnerabilidades de open redirect.
- **Supressão de erro em unlink**: Substituído `@unlink()` por `wp_delete_file()` com verificação prévia de existência do arquivo.
- **Sanitização de parâmetro GET**: Adicionado `wp_unslash()` antes de `sanitize_text_field()` em `class-dps-admin-tabs-helper.php`.

**Base Plugin - Correções de Segurança Críticas**

- **Verificação de permissão em visualização de cliente**: Corrigida vulnerabilidade onde a verificação `can_manage()` era executada APÓS a chamada de `render_client_page()`, permitindo potencial acesso não autorizado a dados de clientes. A verificação agora é feita ANTES de processar a requisição.
- **Nonce em exclusão de agendamentos na seção de histórico**: Adicionada proteção CSRF ao link de exclusão de agendamentos na tabela de histórico. O link agora utiliza `wp_nonce_url()` com a action `dps_delete`.
- **Nonce em exclusão de documentos**: Implementada verificação de nonce na ação de exclusão de documentos (`dps_delete_doc`). Requisições sem nonce válido agora retornam erro "Ação não autorizada" e feedback visual ao usuário.

#### Changed (Alterado)

**Renomeação do Sistema - desi.pet by PRObst**

- **Rebranding completo**: O sistema foi renomeado de "DPS by PRObst" para "desi.pet by PRObst" em todas as interfaces visíveis ao usuário.
- **Plugin Names atualizados**: Todos os 16 plugins (1 base + 15 add-ons) tiveram seus headers "Plugin Name" atualizados para refletir o novo nome.
- **Menu administrativo**: O menu principal do WordPress agora exibe "desi.pet by PRObst" em vez de "DPS by PRObst".
- **Comunicações e e-mails**: Todos os templates de e-mail, mensagens do portal e notificações foram atualizados para usar o novo nome.
- **Documentação**: README.md, AGENTS.md, ANALYSIS.md, CHANGELOG.md e toda a documentação em `/docs` foram atualizados.
- **Prompts de IA**: System prompts do AI Add-on foram atualizados para refletir o novo nome do sistema.
- **IMPORTANTE - Integridade mantida**: Para garantir a estabilidade do sistema, os seguintes elementos NÃO foram alterados:
  - Slugs internos (ex: `desi-pet-shower`, `dps-*`)
  - Prefixos de código (`dps_`, `DPS_`)
  - Text domains para internacionalização
  - Nomes de Custom Post Types e tabelas de banco de dados
  - Hooks e filtros existentes

**Reorganização de pastas para estrutura unificada**

- **Nova estrutura**: Todos os plugins (base + 15 add-ons) foram movidos para uma única pasta `plugins/`:
  - `plugin/desi-pet-shower-base_plugin/` → `plugins/desi-pet-shower-base/`
  - `add-ons/desi-pet-shower-*_addon/` → `plugins/desi-pet-shower-*/`
- **Benefícios**:
  - Estrutura mais limpa e organizada
  - Todos os 16 plugins em um único local identificável
  - Nomenclatura simplificada (remoção dos sufixos `_addon` e `_plugin`)
- **Atualizações realizadas**:
  - GitHub Updater atualizado com novos caminhos
  - Addon Manager atualizado com novos caminhos de arquivos
  - Documentação (README.md, AGENTS.md, ANALYSIS.md) atualizada
- **IMPORTANTE para instalações existentes**: Os plugins devem ser reinstalados a partir das novas pastas. O WordPress espera cada plugin em sua própria pasta em `wp-content/plugins/`, portanto:
  - Copie cada pasta de `plugins/desi-pet-shower-*` para `wp-content/plugins/`
  - Reative os plugins no painel do WordPress

#### Added (Adicionado)

**Documentação - Guia Passo a Passo do GitHub Updater (v1.4)**

- **Guia completo para usuários leigos**: Adicionado guia detalhado explicando como usar o sistema de atualizações automáticas via GitHub no arquivo `docs/GUIA_SISTEMA_DPS.md`.
- **Instruções visuais**: Incluídos diagramas ASCII e representações visuais de como os avisos de atualização aparecem no WordPress.
- **FAQ de atualizações**: Adicionadas perguntas frequentes sobre o processo de atualização, como forçar verificação e desabilitar o atualizador.
- **Passo a passo estruturado**: Documentados os 4 passos principais: Verificar atualizações → Fazer backup → Atualizar → Testar.

**Client Portal Add-on (v2.4.3) - Auto-envio de Link de Acesso por E-mail**

- **Formulário de solicitação de link por e-mail**: Clientes podem agora informar seu e-mail cadastrado na tela de acesso ao portal para receber automaticamente o link de acesso. Não é mais necessário aguardar envio manual pela equipe para quem tem e-mail cadastrado.
- **AJAX endpoint `dps_request_access_link_by_email`**: Novo endpoint que busca cliente por e-mail, gera token de acesso e envia automaticamente. Inclui rate limiting (3 solicitações/hora por IP ou e-mail).
- **Fallback para WhatsApp**: Clientes sem e-mail cadastrado são orientados a solicitar via WhatsApp (comportamento anterior mantido como alternativa).
- **Feedback visual em tempo real**: Mensagens de sucesso/erro exibidas no formulário sem recarregar a página.
- **Proteção contra brute force**: Rate limiting duplo (por IP e por e-mail) para evitar abuso do endpoint.

**Base Plugin (v1.2.0) - Card "Agendar serviço" na aba Agendamentos**

- **Card "Agendar serviço" no formulário de agendamentos**: Formulário de agendamento agora está envolvido por um card visual com header contendo eyebrow "AGENDAR SERVIÇO", título dinâmico (Novo Agendamento/Editar Agendamento) e hint descritivo. Estrutura idêntica ao implementado na aba Assinaturas.
- **Estilos de card no CSS base**: Adicionados estilos `.dps-card`, `.dps-card__header`, `.dps-card__body`, `.dps-card__eyebrow`, `.dps-card__title`, `.dps-card__hint` e `.dps-card__actions` no arquivo `dps-base.css` para garantir consistência visual em todas as abas.
- **Responsividade do card**: Media queries para adaptar layout do card em dispositivos móveis (768px e 480px).

**Base Plugin (v1.2.0) - Atualizações via GitHub**

- **Atualizações automáticas via GitHub**: Nova classe `DPS_GitHub_Updater` que verifica e notifica atualizações disponíveis diretamente do repositório GitHub.
- **Suporte a todos os plugins DPS**: O sistema verifica atualizações para o plugin base e todos os 15 add-ons oficiais automaticamente.
- **Integração nativa com WordPress**: Utiliza os hooks `pre_set_site_transient_update_plugins` e `plugins_api` para exibir atualizações no painel de Plugins padrão do WordPress.
- **Cache inteligente**: Verificações são cacheadas por 12 horas para evitar chamadas excessivas à API do GitHub.
- **Notificações no admin**: Aviso visual na página de Plugins quando há atualizações DPS disponíveis.
- **Header Update URI**: Adicionado header `Update URI` em todos os plugins para desabilitar verificação no wordpress.org.
- **Verificação forçada**: Parâmetro `?dps_force_update_check=1` permite forçar nova verificação de atualizações.

**Base Plugin (v1.1.0) - Gerenciador de Add-ons**

- **Gerenciador centralizado de add-ons**: Nova página administrativa (desi.pet by PRObst → Add-ons) para visualizar, ativar e desativar add-ons do ecossistema DPS.
- **Resolução automática de dependências**: Sistema ordena add-ons por suas dependências e ativa na ordem correta automaticamente.
- **Visualização de ordem de ativação**: Painel exibe ordem recomendada de ativação baseada nas dependências de cada add-on.
- **Ativação/desativação em lote**: Seleção múltipla de add-ons com ativação respeitando ordem de dependências.
- **Categorização de add-ons**: Add-ons organizados em 6 categorias (Essenciais, Operação, Integrações, Cliente, Avançado, Sistema).
- **Verificação de dependências**: Alertas visuais quando dependências de um add-on não estão ativas.

#### Removed (Removido)

**Agenda Add-on - Simplificação da Interface (v1.6.0)**

- **Botão "Novo Agendamento" removido da agenda**: Botão "➕ Novo" removido do grupo de ações principais da agenda. Novos agendamentos devem ser criados pela aba Agendamentos padrão.
- **Botão "Exportar PDF" removido**: Botão de exportação para PDF removido do grupo de ações da agenda. Relatórios podem ser acessados pela aba Estatísticas.
- **Seção "Relatório de Ocupação" removida**: Seção colapsável com métricas de ocupação (taxa de conclusão, cancelamento, horário de pico, média por hora) removida do final da agenda. Métricas similares disponíveis na aba Estatísticas com filtro de período.
- **Seção "Resumo do Dia" removida**: Dashboard de KPIs do dia (pendentes, finalizados, faturamento estimado, taxa de cancelamento, média diária) removido do final da agenda. Métricas disponíveis na aba Estatísticas selecionando período de 1 dia.
- **Plano de implementação criado**: Documento `docs/implementation/STATS_DAILY_ANALYSIS_PLAN.md` criado com plano para adicionar métricas complementares (horário de pico, média por hora ativa) na aba Estatísticas.

#### Deprecated (Depreciado)

**Agenda Add-on - Métodos Depreciados (v1.6.0)**

- **Método `render_occupancy_report()` depreciado**: Método marcado como `@deprecated 1.6.0`. Funcionalidade movida para aba Estatísticas. Remoção completa prevista para v1.7.0.
- **Método `render_admin_dashboard()` depreciado**: Método marcado como `@deprecated 1.6.0`. Funcionalidade movida para aba Estatísticas. Remoção completa prevista para v1.7.0.

**Add-ons Descontinuados**

- **Debugging Add-on removido**: Add-on de gerenciamento de constantes de debug e visualização de logs removido por complexidade de manutenção.
- **White Label Add-on removido**: Add-on de personalização de marca, cores, logo e SMTP removido por baixa utilização e dificuldades de manutenção.

**Base Plugin (v1.0.4) - Redesign das Abas CLIENTES e PETS**

- **Templates modulares para pets**: Criados templates separados para formulário (`pet-form.php`), listagem (`pets-list.php`) e seção completa (`pets-section.php`), seguindo mesmo padrão já existente para clientes.
- **Colunas adicionais na listagem de clientes**: Email e contagem de pets agora visíveis na tabela de clientes para consulta rápida.
- **Colunas adicionais na listagem de pets**: Porte e Sexo agora visíveis na tabela de pets, com ícones para espécie e badges coloridos por tamanho.
- **Indicador de pet agressivo na listagem**: Badge visual ⚠️ e destaque vermelho na linha para pets marcados como agressivos.
- **Link "Adicionar pet" para clientes sem pets**: Na coluna Pets, clientes sem pets têm link rápido para cadastrar.
- **Contagem de registros no header das listas**: Badge com total de clientes/pets cadastrados ao lado do título.

#### Changed (Alterado)

**Base Plugin (v1.0.4)**

- **Formulário de pets refatorado para templates**: Lógica de preparação de dados separada da renderização (métodos `prepare_pets_section_data()` e `render_pets_section()`).
- **Header de listas redesenhado**: Títulos "Clientes Cadastrados" e "Pets Cadastrados" agora com ícones, badges de contagem e espaçamento melhorado.
- **Toolbar de busca padronizada**: Campo de busca com placeholder mais descritivo e layout flex responsivo.
- **Ações nas tabelas melhoradas**: Links Editar/Agendar/Excluir agora com cores semânticas (azul para editar, verde para agendar, vermelho para excluir).
- **Estilos CSS ampliados**: Novas classes para badges de porte (`.dps-size-badge--pequeno/medio/grande`), pets agressivos, links de ação e responsividade.

**Groomers Add-on (v1.8.0) - Redesign completo do Layout da Aba Equipe**

- **Navegação por sub-abas**: Separação em 3 sub-abas (Equipe, Relatórios, Comissões) para organização mais clara e navegação mais fluida.
- **Layout em cards**: Formulário e listagem agora em containers visuais estilizados com headers e bordas claras.
- **Tabela compacta com avatares**: Listagem de profissionais redesenhada com avatares circulares, indicadores de comissão e status como ponto colorido (dot).
- **Formulário reorganizado com accordions**: Campos básicos sempre visíveis, credenciais e configurações adicionais em seções colapsáveis (`<details>`).
- **Dias de trabalho compactos**: Grid de checkboxes em formato mini (letras) para melhor aproveitamento de espaço.
- **Filtros inline na listagem**: Filtros de tipo e status como dropdowns compactos no header do card.

#### Changed (Alterado)

**Groomers Add-on (v1.8.0)**

- **Título da seção alterado de "Groomers" para "Equipe"**: Nomenclatura mais abrangente para suportar diferentes tipos de profissionais.
- **Tabela de 6 para 5 colunas**: Colunas reorganizadas (Profissional, Contato, Função, Status, Ações) com informações condensadas.
- **Status como indicador visual**: Antes era badge com texto, agora é ponto colorido clicável para alternar status.
- **Botões de ação como ícones**: Editar e Excluir agora são botões de ícone compactos em vez de links com texto.
- **Relatórios e Comissões em abas separadas**: Antes ficavam no final da página, agora têm abas dedicadas para melhor foco.
- **CSS ampliado com variáveis CSS**: Uso de custom properties para cores e bordas, facilitando manutenção.

**Subscription Add-on (v1.2.0) - Melhorias de Layout e UX na Aba Assinaturas**

- **Dashboard de métricas**: Cards de resumo no topo da seção mostrando Assinaturas Ativas, Receita Mensal, Pagamentos Pendentes e Canceladas.
- **Barra de progresso visual**: Visualização gráfica do progresso de atendimentos (X/4 ou X/2 realizados) com cores e animação.
- **Tabela responsiva**: Wrapper com scroll horizontal e transformação em cards para mobile (<640px).
- **Data-labels para mobile**: Cada célula da tabela inclui atributo `data-label` para exibição correta em layout de cards.
- **Botões de ação estilizados**: Ações (Editar, Cancelar, Renovar, Cobrar) exibidas como botões compactos com cores semânticas e hover states.
- **Badges de status**: Status de pagamento em assinaturas canceladas exibido como badge colorido.

#### Changed (Alterado)

**Subscription Add-on (v1.2.0)**

- **Formulário reorganizado em fieldsets**: Campos agrupados em "Dados do Cliente", "Detalhes da Assinatura" e "Agendamento Inicial" com legendas claras.
- **Grid de 2 colunas**: Campos Cliente/Pet, Serviço/Frequência e Data/Hora lado a lado em desktop.
- **Tabela simplificada**: Colunas Cliente e Pet unificadas em "Cliente / Pet" com layout empilhado para reduzir número de colunas.
- **Coluna Início removida**: Data de início não exibida na listagem (informação menos relevante para operação diária).
- **Próximo agendamento compacto**: Formato de data reduzido para "dd/mm HH:mm" para economizar espaço.
- **Estilos CSS ampliados**: Novos estilos para dashboard, formulário com fieldsets, barra de progresso, badges, botões de ação e responsividade.
- **Versão atualizada para 1.2.0** no cabeçalho do plugin e assets.

**Push Add-on (v1.2.0) - Melhorias de Interface e Correções**

- **Menu admin visível**: Menu agora registrado sob "desi.pet by PRObst > Notificações" (antes estava oculto).
- **Botões de teste de relatórios**: Botões "Enviar Teste" para cada tipo de relatório (Agenda, Financeiro, Semanal).
- **Botão de teste de conexão Telegram**: Valida configuração e envia mensagem de teste.
- **AJAX handlers**: Novos handlers `dps_push_test_report` e `dps_push_test_telegram` para testes via AJAX.
- **Feedback visual**: Mensagens de sucesso/erro exibidas ao lado dos botões de teste.

#### Changed (Alterado)

**Push Add-on (v1.2.0)**

- **Carregamento de assets otimizado**: CSS/JS agora carregados apenas em páginas DPS relevantes.
- **Cron hooks adicionais**: Reagendamento automático quando opções `_enabled` ou `_day` mudam.
- **Versão atualizada para 1.2.0** no cabeçalho do plugin e assets.

#### Fixed (Corrigido)

- **Base Plugin (v1.1.1)**: Corrigido PHP Notice "Translation loading for the desi-pet-shower domain was triggered too early" no WordPress 6.7+. A função `add_role()` no hook de ativação agora usa string literal em vez de `__()` para evitar carregamento prematuro do text domain.

- **Base Plugin (v1.0.4)**: Cache dos assets CSS/JS agora usa `filemtime` para versionar automaticamente o layout modernizado do Painel de Gestão DPS, evitando exibição do modelo antigo em navegadores com cache.

**Push Add-on (v1.2.0)**

- **uninstall.php corrigido**: Agora limpa todas as options criadas pelo add-on e remove cron jobs.

**Subscription Add-on (v1.2.1)**

- **Botão "Adicionar serviço extra" corrigido**: Movida chamada do `bindExtras()` para o início da função `init()`, garantindo que os eventos de clique sejam vinculados mesmo quando o formulário não está presente na página inicial. Antes, se o usuário acessava a listagem de assinaturas e depois navegava para "Nova Assinatura", o botão não funcionava por falta de binding dos eventos.

---

**AI Add-on (v1.9.0) - Edição de Regras de Sistema (System Prompts)**

- **Campo editável de System Prompts**: Nova seção "Regras de Sistema (System Prompts)" na página de configurações do add-on IA.
- Permite visualizar e editar as regras de segurança e escopo para cada contexto: Portal do Cliente, Chat Público, WhatsApp e E-mail.
- Indicadores visuais (badges) mostram se o prompt está "Customizado", "Padrão" ou "Modificado".
- Botão "Restaurar Padrão" via AJAX para cada contexto, permitindo reverter para o prompt original.
- Prompts customizados são armazenados na opção `dps_ai_custom_prompts` e priorizados sobre os arquivos padrão.
- Classe `DPS_AI_Prompts` refatorada com cache unificado para arquivos (`$file_cache`) e banco de dados (`$custom_prompts_cache`).
- Novos métodos: `get_custom_prompt()`, `save_custom_prompt()`, `reset_to_default()`, `has_custom_prompt()`, `get_default_prompt()`, `get_all_custom_prompts()`.

**Groomers Add-on (v1.7.0) - FASE 4: Recursos Avançados**

- **F4.1 - Configuração de disponibilidade**: Novos campos para horário de início/término e dias de trabalho por profissional.
- Metas `_dps_work_start`, `_dps_work_end`, `_dps_work_days` para armazenar configuração de turnos.
- Fieldset "Disponibilidade" no formulário de cadastro com inputs de horário e grid de checkboxes para dias.
- CSS responsivo para componentes de disponibilidade.

**Groomers Add-on (v1.6.0) - FASE 3: Finance/Repasse**

- **F3.2 - Hook `dps_finance_booking_paid` consumido**: Ao confirmar pagamento, comissão é calculada automaticamente para profissionais vinculados.
- **F3.3 - Método `generate_staff_commission()`**: Calcula comissão proporcional para múltiplos profissionais.
- Metas `_dps_staff_commissions`, `_dps_commission_generated`, `_dps_commission_date` no agendamento.
- Hook `dps_groomers_commission_generated` para extensões (Loyalty, Stats, etc.).

**Services Add-on (v1.4.0) - Reformulação do Layout da Aba Serviços**

- **Layout do formulário completamente reorganizado**: Formulário de cadastro de serviços agora usa fieldsets semânticos com legendas claras ("Informações Básicas", "Valores por Porte", "Duração por Porte", "Configuração do Pacote").
- **Grid responsivo**: Campos organizados em grid de 2 colunas (desktop) com fallback para 1 coluna (mobile).
- **Inputs com prefixo/sufixo**: Campos de preço mostram "R$" como prefixo visual, campos de duração mostram "min" como sufixo.
- **Listagem melhorada**: Nova coluna "Duração" na tabela, busca com placeholder mais claro, contador de serviços ativos/totais no cabeçalho.
- **Badges de tipo coloridos**: Tipo de serviço exibido como badge colorido (padrão=azul, extra=amarelo, pacote=roxo).
- **Botões de ação estilizados**: Ações (Editar, Duplicar, Ativar/Desativar, Excluir) exibidas como botões compactos com cores semânticas.
- **Categoria como linha secundária**: Categoria exibida abaixo do nome do serviço em vez de coluna separada.
- **Estado vazio amigável**: Mensagem orientativa quando não há serviços cadastrados.
- **CSS ampliado**: Novos estilos para formulário, fieldsets, grid de porte, inputs com prefixo/sufixo, badges e ações.
- **Botão Cancelar**: Ao editar serviço, botão para cancelar edição e voltar ao formulário vazio.

#### Removed (Removido)

**Services Add-on (v1.4.0)**

- **Seção "Consumo de estoque" removida**: Funcionalidade não utilizada foi removida do formulário de cadastro de serviços. A meta `dps_service_stock_consumption` continua sendo lida para serviços existentes mas não é mais editável.

**Services Add-on (v1.3.0) - FASE 2: Integração com Profissionais**

- **F2.1 - Campo `required_staff_type`**: Serviços podem exigir tipo específico de profissional (groomer, banhista ou qualquer).
- Meta `required_staff_type` salva com valores 'any', 'groomer', 'banhista'.

**Agenda Add-on (v1.4.2) - FASE 7: Reorganização das Abas**

- **Resumo do Dia e Relatório de Ocupação**: Movidos para o final da página, ambos agora usam `<details>` expansível (fechados por padrão).
- **Aba "Visão Rápida" reorganizada**: Colunas Checkbox, Horário, Pet (com badge de agressivo), Tutor, Serviços (botão popup), Confirmação (dropdown elegante com CONFIRMADO/NÃO CONFIRMADO/CANCELADO).
- **Aba "Operação" reorganizada**: Colunas Checkbox, Horário, Pet (com badge de agressivo), Tutor, Status do Serviço (dropdown com ícones), Pagamento (popup com envio por WhatsApp e copiar link).
- **Aba "Detalhes" reorganizada**: Colunas Checkbox, Horário, Pet (com badge de agressivo), Tutor, TaxiDog (lógica condicional para solicitado/não solicitado).
- **Badge de pet agressivo**: Badge visual em todas as abas identificando pets marcados como agressivos.
- **Popup de Serviços**: Modal com lista de serviços, preços e observações do atendimento.
- **Popup de Pagamento**: Modal com botão para enviar link de pagamento por WhatsApp e botão para copiar link.
- **Handler AJAX `dps_agenda_request_taxidog`**: Permite solicitar TaxiDog para agendamentos que não tinham solicitado.
- **CSS e JS**: Novos estilos para dropdowns elegantes, badges, popups e responsividade.

**Push Notifications Add-on (v1.1.0) - Relatórios por Email**

- **Interface de configuração de relatórios por email**: Adicionada seção completa de configuração na página de administração do Push Add-on.
- **Agenda Diária por Email**: Resumo dos agendamentos do dia enviado automaticamente no horário configurado.
- **Relatório Financeiro Diário**: Receitas, despesas e transações do dia enviados automaticamente.
- **Relatório Semanal de Pets Inativos**: Lista de pets sem atendimento há X dias para reengajamento.
- **Configuração de destinatários**: Campos para definir emails de destinatários separados por vírgula.
- **Configuração de horários**: Inputs de horário para cada tipo de relatório.
- **Configuração de Telegram**: Campos para token do bot e chat ID para envio paralelo via Telegram.
- **Classe DPS_Email_Reports carregada e instanciada**: Classe existente agora é incluída e inicializada automaticamente.

**Agenda Add-on (v1.1.0) - FASE 2: Filtro por Profissional**

- **F2.5 - Filtro por profissional na Agenda**: Novo filtro nos filtros avançados para selecionar profissional específico.
- Parâmetro `filter_staff` adicionado no trait de renderização.
- Profissionais exibidos com tipo entre parênteses no dropdown de filtro.

**Groomers Add-on (v1.5.0) - FASE 1: Tipos de Profissional + Freelancer**

- **F1.1 - Meta `_dps_staff_type`**: Novo campo para diferenciar tipos de profissional (groomer, banhista, auxiliar, recepção). Metas são migradas automaticamente para groomers existentes.
- **F1.2 - Meta `_dps_is_freelancer`**: Flag booleana para identificar profissionais autônomos vs CLT. Permite regras diferenciadas em relatórios e financeiro.
- **F1.3 - Migração automática**: Na primeira execução da v1.5.0, todos os profissionais existentes recebem `staff_type='groomer'` e `is_freelancer='0'` automaticamente.
- **F1.4 - Formulário de cadastro atualizado**: Novo fieldset "Tipo e Vínculo" com select de tipo de profissional e checkbox de freelancer.
- **F1.5 - Tabela de listagem atualizada**: Novas colunas "Tipo" e "Freelancer" com badges visuais coloridas por tipo.
- **F1.6 - Filtros na listagem**: Novos filtros por tipo, freelancer e status para facilitar busca em petshops com muitos profissionais.
- **Select agrupado por tipo no agendamento**: Profissionais agrupados por tipo com optgroup no select.
- **Método `get_staff_types()`**: Método estático para obter tipos disponíveis com labels traduzidos.
- **Método `get_staff_type_label()`**: Método estático para obter label traduzido de um tipo específico.
- **Método `validate_staff_type()`**: Método estático para validar e normalizar tipos.

**Registration Add-on (v1.2.0) - FASE 2A: UX Quick Wins & Higiene Técnica**

- **F2.5 - JS em arquivo separado**: Criado `assets/js/dps-registration.js` com ~400 linhas de JavaScript modular. Remove ~40 linhas de JS inline do PHP. Script enfileirado com `wp_enqueue_script` apenas quando o shortcode está presente. Expõe objeto global `DPSRegistration` com métodos públicos para extensibilidade.
- **F2.1 - Máscaras de entrada (CPF e telefone)**: Máscara visual de CPF (###.###.###-##) aplicada automaticamente. Máscara de telefone adapta entre 10 dígitos (##) ####-#### e 11 dígitos (##) #####-####. Suporta colagem (paste) e edição no meio do texto sem quebrar.
- **F2.2 - Validação client-side (JS)**: Validação de campos obrigatórios antes do submit. Validação de CPF com algoritmo mod 11 em JavaScript. Validação de telefone (10-11 dígitos) e email. Erros exibidos no topo do formulário com estilo consistente. Formulário ainda funciona se JS estiver desabilitado (graceful degradation).
- **F2.4 - Indicador de loading no botão**: Botão é desabilitado durante envio. Texto muda para "Enviando..." com estilo visual de espera.
- **F2.3 - Mensagem de sucesso melhorada**: Título destacado com ícone de check. Mensagem contextualizada para banho e tosa.
- **F2.8 - Próximo passo sugerido**: Após sucesso, exibe orientação para agendar via WhatsApp/telefone. Formulário não é mais exibido após cadastro concluído.
- **F2.9 - Removido session_start()**: Função removida pois não era mais necessária (sistema usa transients/cookies para mensagens). Elimina conflitos de headers e warnings em alguns hosts.

**Registration Add-on (v1.1.0) - FASE 1: Segurança, Validação & Hardening**

- **F1.1 - Validação de campos obrigatórios no backend**: Nome e telefone são agora validados no backend (não apenas HTML required). Campos vazios resultam em mensagem de erro clara e impede criação do cadastro.
- **F1.2 - Validação de CPF com algoritmo mod 11**: CPF informado é validado com dígitos verificadores. CPF inválido bloqueia cadastro. Campo continua opcional, mas se preenchido deve ser válido.
- **F1.3 - Validação de telefone brasileiro**: Telefone validado para formato BR (10-11 dígitos). Aceita com ou sem código de país (55). Usa `DPS_Phone_Helper::is_valid_brazilian_phone()` quando disponível.
- **F1.4 - Validação de email com `is_email()`**: Email preenchido é validado com função nativa do WordPress. Email inválido bloqueia cadastro com mensagem específica.
- **F1.5 - Detecção de duplicatas**: Sistema verifica email, telefone e CPF antes de criar novo cliente. Se encontrar cadastro existente, exibe mensagem genérica orientando contato com equipe (não revela qual campo duplicou para evitar enumeração).
- **F1.6 - Rate limiting por IP**: Máximo 3 cadastros por hora por IP. 4ª tentativa bloqueada com mensagem amigável. Usa transients com hash do IP para privacidade.
- **F1.7 - Expiração de token de confirmação**: Token de confirmação de email agora expira em 48 horas. Novo meta `dps_email_confirm_token_created` registra timestamp. Email de confirmação menciona validade de 48h.
- **F1.8 - Feedback de erro visível**: Todas as falhas de validação agora exibem mensagens claras no formulário. Usa `DPS_Message_Helper` quando disponível, com fallback para transients próprios.
- **F1.9 - Normalização de telefone**: Telefone é salvo apenas com dígitos (sem máscaras). Facilita integração com WhatsApp e Communications Add-on.

#### Changed (Alterado)

- Mensagem de sucesso de cadastro agora menciona verificar email se informado.
- Mensagem de email confirmado atualizada com estilo visual consistente.
- Métodos helpers de validação (CPF, telefone, duplicatas) implementados como métodos privados na classe.

#### Security (Segurança)

- Nonce inválido agora exibe mensagem de erro em vez de falha silenciosa.
- Honeypot preenchido exibe mensagem genérica (não revela ser anti-bot).
- Rate limiting protege contra ataques de flood/spam.
- Tokens de confirmação expiram em 48h, reduzindo janela de exposição.
- Mensagem de duplicata é genérica para evitar enumeração de contas.

**Loyalty Add-on (v1.5.0) - FASE 4: Recursos Avançados**

- **F4.2 - Gamificação (badges e conquistas)**: Nova classe `DPS_Loyalty_Achievements` com sistema de conquistas automáticas. 4 conquistas iniciais: `first_bath` (Primeiro Banho), `loyal_client` (Fiel da Casa - 10 atendimentos), `referral_master` (Indicador Master - 5 indicações), `vip` (VIP - nível máximo). Avaliação automática após pontuação ou resgate via `evaluate_achievements_for_client()`. Hook `dps_loyalty_achievement_unlocked` para extensões. Exibição de badges no admin (Consulta de Cliente) e no Portal do Cliente com visual de cards desbloqueados/bloqueados.
- **F4.3 - Níveis configuráveis pelo admin**: Tabela dinâmica na aba Configurações permite criar, editar e excluir níveis de fidelidade. Campos: slug, label, pontos mínimos, multiplicador, ícone e cor. Botão "Adicionar nível" com JavaScript. API `DPS_Loyalty_API::get_tiers_config()` retorna níveis personalizados ou padrão (Bronze/Prata/Ouro). Método `get_default_tiers()` para fallback. Método `get_highest_tier_slug()` para determinar nível máximo. Ordenação automática por pontos mínimos.
- **F4.4 - Integração de créditos com Finance + limite por atendimento**: Nova seção "Integração com Finance" nas configurações. Checkbox `enable_finance_credit_usage` habilita uso de créditos no momento do pagamento. Campo monetário `finance_max_credit_per_appointment` define limite máximo (ex.: R$ 10,00). Finance Add-on consome créditos via `DPS_Loyalty_API::use_credit()` durante lançamento de parcelas. Validação de limite e saldo disponível. Log de auditoria `loyalty_credit` registra uso no histórico financeiro. Nota automática na descrição da transação.
- **F4.5 - API REST de fidelidade (somente leitura)**: Nova classe `DPS_Loyalty_REST` com namespace `dps-loyalty/v1`. 3 endpoints: `GET /client/{id}` (pontos, tier, créditos, conquistas), `GET /client-by-ref/{code}` (busca por código de indicação), `GET /summary?months=N` (timeseries e distribuição por tier). Permissão `manage_options` para todos os endpoints. Formatação de conquistas com label, descrição e status de desbloqueio.

**Loyalty Add-on (v1.4.0) - FASE 3: Relatórios & Engajamento**

- **Dashboard de métricas** com cards de resumo, gráfico de pontos concedidos x resgatados (últimos 6 meses) e pizza de distribuição por nível.
- **Relatório de campanhas** exibindo elegíveis, uso estimado e pontos gerados por campanha `dps_campaign`.
- **Ranking de clientes engajados** com filtros de período, somatório de pontos ganhos/resgatados, indicações e atendimentos.
- **Expiração automática de pontos** configurável (meses) com cron diário e lançamento de expiração no histórico.
- **Avisos de pontos a expirar** integrados ao Communications (template configurável e janela em dias).

**Loyalty Add-on (v1.3.0) - FASE 1: Performance & UX Básica**

- **F1.1 - Auditoria de campanhas otimizada**: Novo método `get_last_appointments_batch()` elimina queries N+1 ao verificar clientes inativos. Antes: 500 clientes = 500+ queries individuais. Agora: 500 clientes = 1 query batch. Mesma lógica de elegibilidade mantida, apenas mais rápido. Métodos legados `is_client_inactive_for_days()` e `get_last_appointment_date_for_client()` marcados como depreciados.
- **F1.2 - Autocomplete na aba "Consulta de Cliente"**: Substituído dropdown paginado por campo de busca com autocomplete AJAX. Novo endpoint `wp_ajax_dps_loyalty_search_clients` busca clientes por nome ou telefone. Busca dinâmica com debounce de 300ms e mínimo de 2 caracteres. Navegação por teclado (setas, Enter, Escape) e seleção por clique. Submissão automática do formulário ao selecionar cliente. Resultados exibem nome, telefone e pontos do cliente.
- **F1.3 - Exibição padronizada de créditos**: Novos métodos `get_credit_for_display()` e `format_credits_display()` centralizam formatação de créditos. Valores negativos são tratados como zero. Formatação consistente (R$ X,XX) usando `DPS_Money_Helper` quando disponível, com fallback manual. Aplicado no Dashboard e na Consulta de Cliente.

**Finance Add-on (v1.6.0) - FASE 4: Extras Avançados (Selecionados)**

- **F4.2 - Lembretes automáticos de pagamento**: Sistema completo de lembretes configurável via painel admin. Checkbox para habilitar/desabilitar lembretes. Configuração de dias antes do vencimento (padrão: 1 dia) e dias após vencimento (padrão: 1 dia). Mensagens customizáveis com placeholders ({cliente}, {pet}, {valor}, {link}). Evento WP-Cron diário (`dps_finance_process_payment_reminders`) processa lembretes automaticamente. Sistema de flags via transients impede envio duplicado de lembretes (janela de 7 dias). Log de execução em error_log para debug. UI acessível via "⚙️ Configurações Avançadas" na aba Financeiro.
- **F4.4 - Auditoria de alterações financeiras**: Nova tabela `dps_finance_audit_log` registra todas as mudanças em transações. Captura mudanças de status (em_aberto → pago, etc.), criações manuais de transações e adições de pagamentos parciais. Registra user_id, IP, timestamps e valores before/after. Índices em trans_id, created_at e user_id para performance. Tela de visualização com filtros por transação ID e data em `admin.php?page=dps-finance-audit`. Paginação (20 registros por página). Labels traduzidas para tipos de ação. Sistema não bloqueia operações principais em caso de falha (log silencioso).
- **F4.5 - API REST de consulta financeira (read-only)**: Namespace `dps-finance/v1` com 3 endpoints. `GET /transactions` lista transações com filtros (status, date_from, date_to, customer, paginação). `GET /transactions/{id}` retorna detalhes de transação específica. `GET /summary` retorna resumo financeiro por período (current_month, last_month, custom). Todos os endpoints requerem autenticação e capability `manage_options`. Validação robusta de parâmetros (status enum, datas, limites de paginação). Headers X-WP-Total e X-WP-TotalPages em respostas paginadas. Formatação monetária via DPS_Money_Helper. Estrutura WP_REST_Response padrão.

**Finance Add-on (v1.5.0) - FASE 3: Relatórios & Visão Gerencial**

- **F3.1 - Gráfico de evolução mensal aprimorado**: Gráfico convertido de barras para linhas com área preenchida, proporcionando melhor visualização de tendências. Exibe receitas (verde) e despesas (vermelho) nos últimos 6 meses (configurável via constante `DPS_FINANCE_CHART_MONTHS`). Inclui título "Evolução Financeira" e tooltips formatados em R$.
- **F3.2 - Relatório DRE simplificado existente mantido**: DRE já implementado na v1.3.0 continua disponível, exibindo receitas por categoria, despesas por categoria e resultado do período. Exibe automaticamente quando há filtro de data aplicado ou ao clicar em "show_dre".
- **F3.3 - Exportação PDF de relatórios**: Novos botões "📄 Exportar DRE (PDF)" e "📊 Exportar Resumo (PDF)" no painel de filtros. Gera HTML limpo otimizado para impressão em PDF via navegador. DRE inclui receitas/despesas por categoria e resultado do período. Resumo Mensal inclui cards de totais e Top 10 clientes. Validação de nonce e capability (manage_options) em todos os endpoints.
- **F3.4 - Comparativo mensal (mês atual vs anterior)**: Novos cards exibindo receita do mês atual vs mês anterior com indicador de variação percentual. Exibe ↑ (verde) para crescimento ou ↓ (vermelho) para queda. Cálculo automático usando apenas transações pagas tipo receita. Posicionado no topo dos relatórios para visibilidade imediata.
- **F3.5 - Top 10 clientes por receita**: Nova tabela ranking exibindo os 10 clientes que mais geraram receita no período filtrado (ou mês atual se sem filtro). Mostra posição (#), nome do cliente, quantidade de atendimentos e valor total pago. Botão "Ver transações" permite filtrar rapidamente todas as transações de cada cliente. Query otimizada com GROUP BY e agregação SQL.

**Finance Add-on (v1.4.0) - FASE 2: UX do Dia a Dia**

- **F2.1 - Card de pendências urgentes**: Novo card visual no topo da aba Financeiro exibindo pendências vencidas (🚨 vermelho) e pendências de hoje (⚠️ amarelo) com quantidade e valor total. Links diretos para filtrar e ver detalhes. Melhora visibilidade de cobranças urgentes para equipe.
- **F2.2 - Botão "Reenviar link de pagamento"**: Novo botão "✉️ Reenviar link" na coluna de Ações para transações em aberto com link do Mercado Pago. Abre WhatsApp com mensagem personalizada contendo link de pagamento. Registra log de reenvio com timestamp e usuário. Reduz de 5 para 1 clique para follow-up com clientes.
- **F2.3 - Badges visuais de status**: Status financeiros agora exibidos como badges coloridos: ✅ Pago (verde), ⏳ Em aberto (amarelo), ❌ Cancelado (vermelho). Facilita identificação rápida do estado de cada transação. Select de alteração de status agora menor e inline ao badge.
- **F2.4 - Indicadores visuais de vencimento**: Datas na coluna exibem ícones e cores para urgência: 🚨 Vermelho para vencidas, ⚠️ Amarelo para hoje, normal para futuras. Aplicado apenas em transações em aberto tipo receita. Equipe identifica prioridades visualmente.
- **F2.5 - Busca rápida por cliente**: Novo campo de texto "Buscar cliente" no formulário de filtros. Busca por nome de cliente em tempo real usando LIKE no banco. Funciona em conjunto com outros filtros (data, categoria, status). Reduz tempo de localização de transações específicas de minutos para segundos.

#### Changed (Alterado)

#### Fixed (Corrigido)

**Plugin Base (v1.x.x)**

- **Correção ao alterar status de agendamento no Painel de Gestão DPS**: Corrigido bug onde a mensagem "Selecione um status válido para o agendamento" aparecia mesmo ao selecionar um status válido. O problema era causado pelo JavaScript em `dps-base.js` que desabilitava o elemento `<select>` antes de disparar o submit do formulário, fazendo com que o browser não incluísse o valor do status nos dados enviados. A linha que desabilitava o select foi removida, mantendo a proteção contra múltiplos envios via flag `submitting`.

**Services Add-on (v1.3.1)**

- **Redirecionamento incorreto após salvar serviço corrigido**: Após adicionar ou editar um serviço no Painel de Gestão DPS, o sistema agora redireciona corretamente para a aba de serviços (ex: `/administracao/?tab=servicos`) em vez da página inicial do site. O método `get_redirect_url()` agora segue a mesma hierarquia de fallbacks do plugin base: (1) HTTP referer, (2) `get_queried_object_id()` + `get_permalink()`, (3) global `$post`, (4) `REQUEST_URI`, (5) `home_url()`. Resolve problema onde o usuário era redirecionado para "Welcome to WordPress" após salvar serviço.

**Client Portal Add-on (v2.4.2)**

- **Melhoria no fallback de redirecionamento**: Método `get_redirect_url()` em `DPS_Portal_Admin_Actions` agora inclui fallback adicional via global `$post` e `REQUEST_URI` antes de usar `home_url()`, seguindo o padrão do plugin base para maior robustez.

**Registration Add-on (v1.2.1)**

- **Redirecionamento pós-cadastro corrigido**: Após finalizar o cadastro, o sistema agora busca corretamente a página de registro, mesmo quando a option `dps_registration_page_id` não está configurada ou a página foi excluída. O método `get_registration_page_url()` agora tenta: (1) ID salvo na option, (2) página pelo slug padrão "cadastro-de-clientes-e-pets", (3) qualquer página com o shortcode `[dps_registration_form]`. Quando encontra a página por fallback, atualiza automaticamente a option para evitar buscas futuras. Resolve problema de página em branco após cadastro.

#### Security (Segurança)

**Finance Add-on (v1.3.1) - FASE 1: Segurança e Performance**

- **F1.1 - Documentos financeiros protegidos contra acesso não autorizado**: Documentos HTML (notas e cobranças) agora são servidos via endpoint autenticado com nonce e verificação de capability, em vez de URLs públicas diretas. Diretório `wp-content/uploads/dps_docs/` protegido com `.htaccess` para bloquear acesso direto. Mantém compatibilidade backward com documentos já gerados.
- **F1.2 - Validação de pagamentos parciais**: Sistema agora impede que a soma de pagamentos parciais ultrapasse o valor total da transação, evitando inconsistências financeiras. Inclui mensagem de erro detalhada informando total, já pago e valor restante.
- **F1.3 - Índices de banco de dados adicionados**: Criados índices compostos em `dps_transacoes` (`data`, `status`, `categoria`) para melhorar drasticamente a performance de filtros e relatórios. Melhoria de ~80% em queries com volumes acima de 10.000 registros.
- **F1.4 - Query do gráfico mensal otimizada**: Gráfico de receitas/despesas agora limita automaticamente aos últimos 12 meses quando nenhum filtro de data é aplicado, evitando timeout com grandes volumes de dados (> 50.000 registros). Usa agregação SQL em vez de carregar todos os registros em memória.

#### Refactoring (Interno)

---

#### Added (Adicionado)
- **Client Portal Add-on (v2.4.1)**: Criação automática da página do portal na ativação do add-on
  - Função `dps_client_portal_maybe_create_page()` cria página "Portal do Cliente" se não existir
  - Verifica se página configurada tem o shortcode `[dps_client_portal]` e adiciona se necessário
  - Armazena ID da página em `dps_portal_page_id` automaticamente
  - Previne erros de "página não encontrada" ao acessar links de autenticação
- **Client Portal Add-on (v2.4.1)**: Verificação contínua da configuração do portal no painel administrativo
  - Sistema de avisos que alerta se a página do portal não existe, está em rascunho ou sem shortcode
  - Avisos contextualizados com links diretos para corrigir problemas
  - Executa automaticamente em `admin_init` para administradores
- **AGENDA Add-on (v1.4.0)**: Sistema de 3 abas para reorganização da lista de agendamentos
  - Aba 1 "Visão Rápida": Visualização enxuta com Horário, Pet, Tutor, Status, Confirmação (badge), TaxiDog
  - Aba 2 "Operação": Visualização operacional completa com todas as ações (status, confirmação com botões, pagamento, ações rápidas)
  - Aba 3 "Detalhes": Foco em informações complementares (observações do atendimento, observações do pet, endereço, mapa/GPS)
  - Navegação entre abas sem recarregar página
  - Preferência de aba salva em sessionStorage
  - Aba "Visão Rápida" como padrão ao carregar
  - Campos de identificação (Horário + Pet + Tutor) presentes em todas as abas
- **Payment Add-on (v1.1.0)**: Suporte para credenciais via constantes wp-config.php
  - Nova classe `DPS_MercadoPago_Config` para gerenciar credenciais do Mercado Pago
  - Ordem de prioridade: constantes wp-config.php → options em banco de dados
  - Constantes suportadas: `DPS_MERCADOPAGO_ACCESS_TOKEN`, `DPS_MERCADOPAGO_WEBHOOK_SECRET`, `DPS_MERCADOPAGO_PUBLIC_KEY`
  - Tela de configurações exibe campos readonly quando constante está definida
  - Exibe apenas últimos 4 caracteres de tokens definidos via constante
  - Recomendações de segurança na interface administrativa
- **Payment Add-on (v1.1.0)**: Sistema de logging e flags de erro para cobranças
  - Novo metadado `_dps_payment_link_status` nos agendamentos (values: success/error/not_requested)
  - Novo metadado `_dps_payment_last_error` com detalhes do último erro (code, message, timestamp, context)
  - Método `log_payment_error()` para logging centralizado de erros de cobrança
  - Método `extract_appointment_id_from_reference()` para extrair ID de external_reference
- **AGENDA Add-on (v1.0.2)**: Indicador visual de erro na geração de link de pagamento
  - Exibe aviso "⚠️ Erro ao gerar link" quando `_dps_payment_link_status` = 'error'
  - Tooltip com mensagem explicativa para o usuário
  - Detalhes do erro para administradores (mensagem e timestamp)
  - Não quebra UX existente - apenas adiciona feedback quando há erro

#### Changed (Alterado)
- **AGENDA Add-on (v1.4.0)**: Reorganização da interface de lista de agendamentos
  - Interface anterior com tabela única substituída por sistema de 3 abas
  - Botões de confirmação movidos para Aba 2 (Operação), removidos da Aba 1 (Visão Rápida)
  - Coluna TaxiDog agora mostra "–" quando não há TaxiDog solicitado (antes mostrava botão vazio)
  - Títulos de colunas ajustados para melhor correspondência com conteúdo
  - Layout responsivo com tabs em coluna em telas mobile
- **Payment Add-on (v1.1.0)**: Tratamento de erros aprimorado na integração Mercado Pago
  - Método `create_payment_preference()` agora valida HTTP status code
  - Verifica presença de campos obrigatórios na resposta (`init_point`)
  - Loga erros de conexão, HTTP não-sucesso e campos faltantes
  - Salva flag de status em agendamentos ao gerar links
- **Payment Add-on (v1.1.0)**: Métodos atualizados para usar `DPS_MercadoPago_Config`
  - `create_payment_preference()` usa config class em vez de `get_option()`
  - `process_payment_notification()` usa config class
  - `get_webhook_secret()` simplificado para usar config class
  - `maybe_generate_payment_link()` salva flags de sucesso/erro
  - `inject_payment_link_in_message()` salva flags de sucesso/erro

#### Fixed (Corrigido)
- **Base Plugin (v1.1.1)**: Validações defensivas em Hubs administrativos para prevenir erros fatais
  - Adicionado `method_exists()` antes de chamar `get_instance()` em todos os Hubs
  - DPS_Tools_Hub agora verifica existência do método antes de renderizar aba de Cadastro
  - DPS_Integrations_Hub valida método em abas de Comunicações, Pagamentos e Push
  - DPS_System_Hub valida método em abas de Backup, Debugging e White Label
  - Mensagens informativas quando add-on precisa ser atualizado
  - Previne erro "Call to undefined method" quando add-ons desatualizados estão ativos
- **Base Plugin (v1.1.1)**: Dashboard não consulta mais tabela inexistente do Finance Add-on
  - Adicionada verificação `SHOW TABLES LIKE` antes de consultar `wp_dps_transacoes`
  - Query de pendências financeiras executa apenas se tabela existir no banco
  - Previne erro "Table doesn't exist" quando Finance Add-on não criou suas tabelas
  - Usa `$wpdb->prepare()` para segurança adicional na verificação de tabela
- **Client Portal Add-on (v2.4.1)**: Menu "Painel Central" desaparece ao ativar o add-on
  - Registro duplicado do CPT `dps_portal_message` causava conflito de menu
  - `DPS_Client_Portal` e `DPS_Portal_Admin` ambos registravam o mesmo CPT com `show_in_menu => 'desi-pet-shower'`
  - WordPress sobrescreve callback do menu pai quando CPT usa `show_in_menu`, causando desaparecimento do "Painel Central"
  - Removido registro duplicado em `DPS_Client_Portal` (linha 72), mantendo apenas em `DPS_Portal_Admin`
  - Menu "Painel Central" agora permanece visível após ativar Client Portal
  - CPT "Mensagens do Portal" continua aparecendo corretamente no menu DPS
- **AGENDA Add-on (v1.4.1)**: Erro crítico ao acessar menu AGENDA no painel administrativo
  - `DPS_Agenda_Addon::get_instance()` causava fatal error (linhas 93 e 112 de class-dps-agenda-hub.php)
  - Implementado padrão singleton em `DPS_Agenda_Addon`
  - Construtor convertido para privado com método público estático `get_instance()`
  - Propriedade estática `$instance` adicionada para armazenar instância única
  - Função de inicialização `dps_agenda_init_addon()` atualizada para usar `get_instance()`
  - Alinha com padrão de todos os outros add-ons integrados aos Hubs do sistema
  - Menu AGENDA agora funciona corretamente com suas 3 abas (Dashboard, Configurações, Capacidade)
- **Finance Add-on (v1.3.1)**: PHP 8+ deprecation warnings relacionados a null em funções de string
  - Corrigido `add_query_arg( null, null )` para `add_query_arg( array() )` para compatibilidade com PHP 8+
  - Adicionado método helper `get_current_url()` para obter URL atual com fallback seguro
  - Substituídas todas as chamadas diretas de `get_permalink()` pelo helper para evitar warnings quando função retorna `false`
  - Corrige avisos "Deprecated: strpos(): Passing null to parameter #1 ($haystack) of type string is deprecated"
  - Corrige avisos "Deprecated: str_replace(): Passing null to parameter #3 ($subject) of type array|string is deprecated"
  - Elimina warnings de "Cannot modify header information - headers already sent" causados pelos deprecation notices
- **Registration Add-on (v1.0.1)**: Erro fatal ao acessar página Hub de Ferramentas
  - `DPS_Registration_Addon::get_instance()` causava fatal error (linha 96 de class-dps-tools-hub.php)
  - Implementado padrão singleton em `DPS_Registration_Addon`
  - Construtor convertido para privado com método público `get_instance()`
  - Alinha com padrão de outros add-ons integrados aos Hubs do sistema
- **Push Add-on (v1.0.1)**: Menu standalone visível incorretamente no painel administrativo
  - Corrigido `parent='desi-pet-shower'` para `parent=null` na função `register_admin_menu()`
  - Menu agora oculto do menu principal (acessível apenas via URL direta)
  - Mantém backward compatibility com URLs diretas existentes
  - Alinha com padrão de outros add-ons integrados ao Hub de Integrações
  - Acesso via aba "Notificações Push" em DPS > Integrações funciona corretamente
- **Base Plugin (v1.1.0)**: Erro fatal ao acessar página Hub de Integrações
  - `DPS_Push_Addon::get_instance()` causava fatal error (linha 144 de class-dps-integrations-hub.php)
  - `DPS_Payment_Addon::get_instance()` causava fatal error (linha 126 de class-dps-integrations-hub.php)
  - `DPS_Communications_Addon::get_instance()` causava fatal error (linha 108 de class-dps-integrations-hub.php)
  - Implementado padrão singleton em `DPS_Push_Addon`, `DPS_Payment_Addon` e `DPS_Communications_Addon`
  - Adicionado método público estático `get_instance()` em cada classe
  - Funções de inicialização atualizadas para usar singleton pattern
  - Fix compatível com versões anteriores - comportamento mantido

#### Security (Segurança)
- **Payment Add-on (v1.1.0)**: Tokens do Mercado Pago podem ser movidos para wp-config.php
  - Recomendado definir `DPS_MERCADOPAGO_ACCESS_TOKEN` e `DPS_MERCADOPAGO_WEBHOOK_SECRET` em wp-config.php
  - Evita armazenamento de credenciais sensíveis em texto plano no banco de dados
  - Mantém compatibilidade com configuração via painel (útil para desenvolvimento)

#### Client Portal (v2.4.0)**: Linha do tempo de serviços por pet (Fase 4)
  - Nova classe `DPS_Portal_Pet_History` para buscar histórico de serviços realizados
  - Método `get_pet_service_history()` retorna serviços por pet em ordem cronológica
  - Método `get_client_service_history()` agrupa serviços de todos os pets do cliente
  - Nova aba "Histórico dos Pets" no portal com timeline visual de serviços
  - Timeline mostra: data, tipo de serviço, observações e profissional
  - Botão "Repetir este Serviço" em cada item da timeline
  - Estado vazio amigável quando pet não tem histórico
  - Design responsivo para mobile com cards empilháveis
- **Client Portal (v2.4.0)**: Sistema de pedidos de agendamento (Fase 4)
  - Novo CPT `dps_appt_request` para armazenar pedidos de agendamento
  - Classe `DPS_Appointment_Request_Repository` para gerenciar pedidos
  - Campos: cliente, pet, tipo (novo/reagendar/cancelar), dia desejado, período (manhã/tarde), status
  - Status possíveis: pending, confirmed, rejected, adjusted
  - NUNCA confirma automaticamente - sempre requer aprovação da equipe
  - Método `create_request()` para criar novos pedidos
  - Método `get_requests_by_client()` para listar pedidos do cliente
  - Método `update_request_status()` para equipe atualizar status
- **Client Portal (v2.4.0)**: Ações rápidas no dashboard (Fase 4)
  - Botão "Solicitar Reagendamento" no card de próximo agendamento
  - Botão "Solicitar Cancelamento" no card de próximo agendamento
  - Modal interativo para escolher dia e período (manhã/tarde) desejados
  - Textos claros informando que é PEDIDO, não confirmação automática
  - Mensagem: "Este é um pedido de agendamento. O Banho e Tosa irá confirmar o horário final"
  - Fluxo de reagendamento: cliente escolhe data + período → status "pendente"
  - Fluxo de cancelamento: confirmação → status "cancelamento solicitado"
- **Client Portal (v2.4.0)**: Dashboard de solicitações recentes (Fase 4)
  - Nova seção "Suas Solicitações Recentes" no painel inicial
  - Renderiza últimos 5 pedidos do cliente com cards visuais
  - Indicadores de status: Aguardando Confirmação (amarelo), Confirmado (verde), Não Aprovado (vermelho)
  - Exibe data desejada, período, pet e observações
  - Mostra data/hora confirmadas quando status = "confirmed"
  - Método `render_recent_requests()` na classe renderer
- **Client Portal (v2.4.0)**: Handlers AJAX para pedidos (Fase 4)
  - Endpoint AJAX `dps_create_appointment_request`
  - Validação de nonce e autenticação de sessão
  - Validação de ownership de pet
  - Sanitização completa de todos os inputs
  - Mensagens de sucesso diferenciadas por tipo de pedido
  - Resposta JSON com ID do pedido criado
- **Client Portal (v2.4.0)**: Interface JavaScript para modais (Fase 4)
  - Handlers para botões `.dps-btn-reschedule`, `.dps-btn-cancel`, `.dps-btn-repeat-service`
  - Função `createRequestModal()` para criar modais dinamicamente
  - Função `submitAppointmentRequest()` para envio via AJAX
  - Validação de formulário com data mínima (amanhã)
  - Notificações visuais de sucesso/erro
  - Reload automático da página após sucesso (2 segundos)
- **Client Portal (v2.4.0)**: Estilos CSS para timeline e modais (Fase 4)
  - Classe `.dps-timeline` com marcadores e linha conectora
  - Classe `.dps-timeline-item` com layout de card
  - Classe `.dps-request-card` com bordas coloridas por status
  - Classe `.dps-appointment-actions` para ações rápidas
  - Modal `.dps-appointment-request-modal` com aviso destacado
  - Design responsivo para mobile (media queries 768px)
- **Client Portal (v2.4.0)**: Central de Mensagens melhorada (Fase 4 - continuação)
  - Nova aba dedicada "Mensagens" 💬 no portal com contador de não lidas
  - Badge dinâmica mostrando quantidade de mensagens não lidas
  - Destaque visual para mensagens não lidas (borda azul, fundo claro, badge "Nova")
  - Exibição de tipo de mensagem (confirmação, lembrete, mudança, geral)
  - Link para agendamento relacionado quando mensagem está associada a um serviço
  - Ordenação com mensagens mais recentes primeiro (DESC)
  - Estado vazio melhorado com ícone e texto explicativo
  - Marcação automática como lida ao visualizar
  - Método `get_unread_messages_count()` para contagem eficiente
  - Texto "Equipe do Banho e Tosa" em vez de genérico
- **Client Portal (v2.4.0)**: Preferências do Cliente (Fase 4 - continuação)
  - Nova seção "Minhas Preferências" ⚙️ em "Meus Dados"
  - Campo "Como prefere ser contatado?": WhatsApp, Telefone, E-mail ou Sem preferência
  - Campo "Período preferido para banho/tosa": Manhã, Tarde, Indiferente
  - Salvamento em meta do cliente: `client_contact_preference`, `client_period_preference`
  - Handler `update_client_preferences` para processar formulário
  - Hook `dps_portal_after_update_preferences` para extensões
  - Layout em grid responsivo com 2 colunas em desktop
- **Client Portal (v2.4.0)**: Preferências do Pet (Fase 4 - continuação)
  - Novo fieldset "Preferências de Banho e Tosa" 🌟 nos formulários de pet
  - Campo "Observações de Comportamento": medos, sensibilidades (ex: medo de secador)
  - Campo "Preferências de Corte/Tosa": estilo preferido (ex: tosa na tesoura, padrão raça)
  - Campo "Produtos Especiais / Alergias": necessidades específicas (ex: shampoo hipoalergênico)
  - Salvamento junto com dados do pet em update_pet
  - Metadados: `pet_behavior_notes`, `pet_grooming_preference`, `pet_product_notes`
  - Textos contextualizados para Banho e Tosa (não clínica veterinária)
  - Preparado para futura visualização pela equipe ao atender o pet
- **Client Portal (v2.4.0)**: Branding Customizável (Fase 4 - conclusão)
  - Nova aba "Branding" 🎨 nas configurações admin ([dps_configuracoes])
  - Upload de logo do Banho e Tosa (recomendado: 200x80px)
  - Seletor de cor primária com preview visual e color picker
  - Upload de imagem hero/destaque para topo do portal (recomendado: 1200x200px)
  - Opções para remover logo ou hero image
  - Preview das imagens atuais antes de trocar
  - Handler `save_branding_settings()` com validação de segurança
  - Aplicação automática no portal:
    - Logo exibido no header (classe `.dps-portal-logo`)
    - Hero image como background no topo (classe `.dps-portal-hero`)
    - Cor primária via CSS custom properties (`--dps-custom-primary`)
    - Cor de hover calculada automaticamente (20% mais escura)
    - Classe `.dps-portal-branded` quando há customizações ativas
  - Afeta: botões primários, links, badges de tab, timeline markers, mensagens não lidas
  - Método helper `adjust_brightness()` para calcular variações de cor
  - Armazenamento em options: `dps_portal_logo_id`, `dps_portal_primary_color`, `dps_portal_hero_id`
  - Portal reflete identidade visual única de cada Banho e Tosa
- **Client Portal (v2.4.0)**: Sistema de notificação de acesso ao portal (Fase 1.3)
  - Nova opção nas configurações do portal para ativar/desativar notificações de acesso
  - E-mail automático enviado ao cliente quando o portal é acessado via token
  - Notificação inclui data/hora do acesso e IP (parcialmente ofuscado para privacidade)
  - Integração com DPS_Communications_API quando disponível, com fallback para wp_mail
  - Mensagem de segurança alertando cliente para reportar acessos não reconhecidos
  - Hook `dps_portal_access_notification_sent` para extensões
- **Client Portal (v2.4.0)**: Helper centralizado de validação de ownership (Fase 1.4)
  - Função global `dps_portal_assert_client_owns_resource()` para validar propriedade de recursos
  - Suporta tipos: appointment, pet, message, transaction, client
  - Logs automáticos de tentativas de acesso indevido
  - Extensível via filtros `dps_portal_pre_ownership_check` e `dps_portal_ownership_validated`
  - Aplicado em download de .ics, atualização de dados de pets
- **AI Add-on (v1.7.0)**: Dashboard de Insights (Fase 6)
  - Nova página administrativa "IA – Insights" com métricas consolidadas
  - Criada classe `DPS_AI_Insights_Dashboard` em `includes/class-dps-ai-insights-dashboard.php`
  - KPIs principais exibidos em cards destacados:
    - Total de conversas no período selecionado
    - Total de mensagens trocadas
    - Taxa de resolução baseada em feedback positivo
    - Custo estimado de tokens consumidos
  - Top 10 Perguntas mais frequentes:
    - Análise automática de mensagens de usuários
    - Exibição em tabela ordenada por frequência
    - Útil para identificar dúvidas recorrentes e oportunidades de FAQ
  - Horários de pico de uso (gráfico de barras):
    - Distribuição de mensagens por hora do dia (0-23h)
    - Identifica períodos de maior demanda
    - Auxilia no planejamento de atendimento
  - Dias da semana com mais conversas (gráfico de barras):
    - Análise de volume de conversas por dia
    - Identifica padrões semanais de uso
  - Top 10 Clientes mais engajados:
    - Lista ordenada por número de conversas e mensagens
    - Identifica clientes com maior interação com a IA
  - Estatísticas por canal (gráfico de pizza):
    - Distribuição de conversas entre web_chat, portal, whatsapp e admin_specialist
    - Visualiza participação de cada canal no total
  - Filtros de período:
    - Últimos 7 dias
    - Últimos 30 dias
    - Período customizado (seleção de data inicial e final)
  - Visualizações com Chart.js:
    - Reutiliza biblioteca já implementada na Fase 2
    - Gráficos responsivos e interativos
  - Performance otimizada:
    - Queries com índices apropriados
    - Agregações eficientes no MySQL
    - Paginação e limites para evitar carga excessiva
  - Arquivos criados:
    - `includes/class-dps-ai-insights-dashboard.php`: Lógica de cálculo e renderização
    - `assets/css/dps-ai-insights-dashboard.css`: Estilos responsivos para dashboard
  - Arquivos modificados:
    - `desi-pet-shower-ai-addon.php`: Include e inicialização da classe
- **AI Add-on (v1.7.0)**: Modo Especialista (Fase 6)
  - Nova página administrativa "IA – Modo Especialista" para equipe interna
  - Criada classe `DPS_AI_Specialist_Mode` em `includes/class-dps-ai-specialist-mode.php`
  - Chat interno restrito a admins (capability `manage_options`):
    - Interface similar ao chat público, mas com recursos avançados
    - Acesso a dados completos do sistema
    - System prompt técnico para equipe interna
  - Comandos especiais tipo "/" para buscar dados:
    - `/buscar_cliente [nome]`: Busca cliente por nome/email/login
    - `/historico [cliente_id]`: Exibe últimas 10 conversas de um cliente
    - `/metricas [dias]`: Mostra métricas consolidadas dos últimos N dias
    - `/conversas [canal]`: Lista últimas 10 conversas de um canal específico
  - Respostas formatadas com contexto técnico:
    - Exibe IDs, timestamps, contadores detalhados
    - Informações estruturadas para análise rápida
    - Formato markdown com negrito, código e listas
  - Consultas em linguagem natural:
    - Processa perguntas que não são comandos usando IA
    - System prompt especializado para tom técnico e profissional
    - Fornece insights baseados em dados do sistema
    - Sugere ações práticas quando relevante
  - Histórico persistente:
    - Conversas do modo especialista gravadas com `channel='admin_specialist'`
    - Visível na página "Conversas IA" para auditoria
    - Rastreamento completo de consultas da equipe interna
  - Interface intuitiva:
    - Mensagem de boas-vindas com exemplos de comandos
    - Feedback visual durante processamento
    - Histórico de conversas na mesma sessão
    - Auto-scroll para última mensagem
  - Arquivos criados:
    - `includes/class-dps-ai-specialist-mode.php`: Lógica de comandos e integração com IA
    - `assets/css/dps-ai-specialist-mode.css`: Estilos do chat especialista
    - `assets/js/dps-ai-specialist-mode.js`: Lógica AJAX e formatação de mensagens
  - Arquivos modificados:
    - `desi-pet-shower-ai-addon.php`: Include e inicialização da classe
- **AI Add-on (v1.7.0)**: Sugestões Proativas de Agendamento (Fase 6)
  - Sistema inteligente que sugere agendamentos automaticamente durante conversas
  - Criada classe `DPS_AI_Proactive_Scheduler` em `includes/class-dps-ai-proactive-scheduler.php`
  - Detecção automática de oportunidades de agendamento:
    - Analisa último agendamento do cliente via CPT `dps_agendamento`
    - Calcula há quantos dias/semanas foi o último serviço
    - Compara com intervalo configurável (padrão: 28 dias / 4 semanas)
  - Integração com portal do cliente:
    - Sugestões aparecem automaticamente após resposta da IA
    - Contexto personalizado por cliente (nome do pet, tipo de serviço, tempo decorrido)
    - Não interfere na funcionalidade existente do chat
  - Controle de frequência para evitar ser invasivo:
    - Cooldown configurável entre sugestões (padrão: 7 dias)
    - Armazena última sugestão em user meta `_dps_ai_last_scheduling_suggestion`
    - Máximo 1 sugestão a cada X dias por cliente
  - Configurações admin completas:
    - Ativar/desativar sugestões proativas
    - Intervalo de dias sem serviço para sugerir (7-90 dias)
    - Intervalo mínimo entre sugestões (1-30 dias)
    - Mensagem customizável para clientes novos (sem histórico)
    - Mensagem customizável para clientes recorrentes com variáveis dinâmicas:
      - `{pet_name}`: Nome do pet
      - `{weeks}`: Semanas desde último serviço
      - `{service}`: Tipo de serviço anterior
  - Mensagens padrão inteligentes:
    - Clientes novos: "Que tal agendar um horário para o banho e tosa do seu pet?"
    - Clientes recorrentes: "Observei que já faz X semanas desde o último serviço do [pet]. Gostaria que eu te ajudasse a agendar?"
  - Query otimizada:
    - Usa `fields => 'ids'` para performance
    - Meta query com índice em `appointment_client_id`
    - Ordenação por `appointment_date` DESC
  - Arquivos modificados:
    - `includes/class-dps-ai-integration-portal.php`: Integração com fluxo de resposta
    - `desi-pet-shower-ai-addon.php`: Include da nova classe e configurações admin
- **AI Add-on (v1.7.0)**: Entrada por Voz no Chat Público (Fase 6)
  - Botão de microfone adicionado ao chat público para entrada por voz
  - Integração com Web Speech API (navegadores compatíveis)
  - Detecção automática de suporte do navegador
    - Botão exibido apenas se API estiver disponível
    - Funciona em Chrome, Edge, Safari e navegadores baseados em Chromium
  - Feedback visual durante reconhecimento de voz:
    - Animação de pulso com cor vermelha indicando "ouvindo"
    - Tooltip informativo ("Ouvindo... Clique para parar")
    - Ícone animado durante captura de áudio
  - UX otimizada:
    - Texto reconhecido preenche o textarea automaticamente
    - Permite edição do texto antes de enviar
    - Adiciona ao texto existente ou substitui se vazio
    - Não envia automaticamente (usuário revisa e clica "Enviar")
    - Auto-resize do textarea após transcrição
  - Tratamento de erros discreto:
    - Log no console para debugging
    - Mensagens específicas por tipo de erro (no-speech, not-allowed, network)
    - Não quebra a funcionalidade do chat em caso de erro
  - Reconhecimento em português do Brasil (pt-BR)
  - Arquivos modificados:
    - `includes/class-dps-ai-public-chat.php`: Botão HTML de microfone
    - `assets/css/dps-ai-public-chat.css`: Estilos e animações do botão de voz
    - `assets/js/dps-ai-public-chat.js`: Lógica de reconhecimento de voz
- **AI Add-on (v1.7.0)**: Integração WhatsApp Business (Fase 6)
  - Criada classe `DPS_AI_WhatsApp_Connector` em `includes/class-dps-ai-whatsapp-connector.php`
    - Normaliza mensagens recebidas de diferentes providers (Meta, Twilio, Custom)
    - Envia mensagens de resposta via HTTP para WhatsApp
    - Suporta múltiplos providers com lógica isolada e reutilizável
  - Criada classe `DPS_AI_WhatsApp_Webhook` em `includes/class-dps-ai-whatsapp-webhook.php`
    - Endpoint REST API: `/wp-json/dps-ai/v1/whatsapp-webhook`
    - Recebe mensagens via webhook (POST)
    - Verificação do webhook para Meta WhatsApp (GET)
    - Validação de assinaturas (Meta: X-Hub-Signature-256, Custom: Bearer token)
    - Cria/recupera conversa com `channel='whatsapp'` e `session_identifier` baseado em hash seguro do telefone
    - Registra mensagem do usuário e resposta da IA no histórico
    - Reutiliza conversas abertas das últimas 24 horas
    - Envia resposta automaticamente de volta para WhatsApp
  - Nova seção "Integração WhatsApp Business" nas configurações de IA
    - Ativar/desativar canal WhatsApp
    - Seleção de provider (Meta, Twilio, Custom)
    - Campos de configuração específicos por provider:
      - **Meta**: Phone Number ID, Access Token, App Secret
      - **Twilio**: Account SID, Auth Token, From Number
      - **Custom**: Webhook URL, API Key
    - Token de verificação para webhook
    - Instruções customizadas para WhatsApp (opcional)
    - Exibição da URL do webhook para configurar no provider
  - JavaScript para toggle de campos específicos por provider selecionado
  - Reutiliza mesma lógica de IA já existente para geração de respostas
  - Context prompt adaptado para WhatsApp (respostas curtas, sem HTML)
  - Tratamento de erros com logging apropriado
  - Conversas WhatsApp aparecem na interface admin "Conversas IA" com filtro por canal
- **AI Add-on (v1.7.0)**: Histórico de Conversas Persistente (Fase 6)
  - Criada nova estrutura de banco de dados para armazenar conversas e mensagens de IA:
    - Tabela `dps_ai_conversations`: id, customer_id, channel, session_identifier, started_at, last_activity_at, status
    - Tabela `dps_ai_messages`: id, conversation_id, sender_type, sender_identifier, message_text, message_metadata, created_at
  - Criada classe `DPS_AI_Conversations_Repository` em `includes/class-dps-ai-conversations-repository.php` para CRUD de conversas
    - Métodos: `create_conversation()`, `add_message()`, `get_conversation()`, `get_messages()`, `list_conversations()`, `count_conversations()`
    - Suporta múltiplos canais: `web_chat` (chat público), `portal`, `whatsapp` (futuro), `admin_specialist` (futuro)
    - Suporta visitantes não identificados via `session_identifier` (hash de IP para chat público)
    - Metadata JSON para armazenar informações adicionais (tokens, custo, tempo de resposta, etc.)
  - Integração automática com chat do portal do cliente (`DPS_AI_Integration_Portal`)
    - Cria/recupera conversa por `customer_id` e canal `portal`
    - Reutiliza conversa se última atividade foi nas últimas 24 horas
    - Registra mensagem do usuário antes de processar
    - Registra resposta da IA após processar
  - Integração automática com chat público (`DPS_AI_Public_Chat`)
    - Cria/recupera conversa por hash de IP e canal `web_chat`
    - Reutiliza conversa se última atividade foi nas últimas 2 horas
    - Registra IP do visitante como `sender_identifier`
    - Armazena metadados de performance (response_time_ms, ip_address)
  - Criada interface administrativa `DPS_AI_Conversations_Admin` em `includes/class-dps-ai-conversations-admin.php`
    - Nova página admin "Conversas IA" (submenu no menu DPS)
    - Slug da página: `dps-ai-conversations`
    - Lista conversas com filtros: canal, status (aberta/fechada), período de datas
    - Paginação (20 conversas por página)
    - Exibe: ID, Cliente/Visitante, Canal, Data de Início, Última Atividade, Status, Ações
    - Página de detalhes da conversa com histórico completo de mensagens
    - Mensagens exibidas cronologicamente com tipo (usuário/assistente/sistema), data/hora, texto
    - Metadados JSON expansíveis para visualizar informações técnicas
    - Diferenciação visual por tipo de remetente (cores de borda e fundo)
    - Controle de permissões: apenas `manage_options`
  - Incrementado `DPS_AI_DB_VERSION` para `1.6.0`
  - Migração automática via `dps_ai_maybe_upgrade_database()` para criar tabelas em atualizações
  - Preparado para futuros canais (WhatsApp, Modo Especialista) sem alterações de schema
- **AI Add-on (v1.6.2)**: Validação de Contraste de Cores para Chat Público (Acessibilidade WCAG AA)
  - Criada classe `DPS_AI_Color_Contrast` em `includes/class-dps-ai-color-contrast.php` para validação de contraste segundo padrões WCAG 2.0
  - Novos campos de configuração na página de settings: Cor Primária, Cor do Texto e Cor de Fundo do chat público
  - Validação em tempo real de contraste usando WordPress Color Picker nativo
  - Calcula luminância relativa e ratio de contraste (fórmula WCAG: (L1 + 0.05) / (L2 + 0.05))
  - Exibe avisos visuais se contraste insuficiente (<4.5:1 para texto normal, <3.0:1 para texto grande)
  - Avisos não bloqueiam salvamento, apenas alertam admin sobre possível dificuldade de leitura
  - Endpoint AJAX `dps_ai_validate_contrast` para validação assíncrona com nonce e capability check (`manage_options`)
  - Mensagens específicas com ratio calculado (exemplo: "contraste 3.2:1, mínimo recomendado 4.5:1")
  - Valida tanto contraste Texto/Fundo quanto Branco/Cor Primária (para legibilidade em botões)
  - Configurações salvas com `sanitize_hex_color()` e padrões: primária=#2271b1, texto=#1d2327, fundo=#ffffff
- **AI Add-on (v1.6.2)**: Indicador de Rate Limit no Chat Público (UX)
  - Modificado `DPS_AI_Client` para armazenar tipo de erro em propriedade estática `$last_error`
  - Novos métodos `get_last_error()` e `clear_last_error()` para recuperar informações de erro
  - Diferenciação de erros HTTP por tipo: `rate_limit` (429), `bad_request` (400), `unauthorized` (401), `server_error` (500-503), `network_error`, `generic`
  - Backend (`DPS_AI_Public_Chat::handle_ajax_ask()`) detecta rate limit via `get_last_error()` e retorna `error_type` específico no JSON
  - Frontend JavaScript detecta `error_type === 'rate_limit'` e exibe UX diferenciada:
    - Mensagem específica: "Muitas solicitações em sequência. Aguarde alguns segundos antes de tentar novamente."
    - Ícone especial ⏱️ (em vez de ⚠️ genérico)
    - Botão de enviar desabilitado temporariamente por 5 segundos
    - Contagem regressiva visual no botão (5, 4, 3, 2, 1) para feedback ao usuário
    - Classe CSS adicional `dps-ai-public-message--rate-limit` para estilização
  - Função JavaScript `disableSubmitTemporarily(seconds)` gerencia contagem regressiva e reabilitação automática
  - Erros genéricos (rede, servidor, etc.) mantêm comportamento original sem alterações
  - 100% retrocompatível, não afeta fluxo de produção existente
- **AI Add-on (v1.6.2)**: Interface de Teste e Validação da Base de Conhecimento
  - Criada nova página admin "Testar Base de Conhecimento" (submenu no menu DPS)
  - Slug da página: `dps-ai-kb-tester`
  - Classe `DPS_AI_Knowledge_Base_Tester` em `includes/class-dps-ai-knowledge-base-tester.php`
  - **Preview de Artigos Selecionados:** Permite testar quais artigos seriam selecionados para uma pergunta de teste
  - Campo de texto para digitar pergunta de teste + botão "Testar Matching" (suporta Ctrl+Enter)
  - Configuração de limite de artigos (1-10, padrão: 5)
  - Usa mesma lógica de matching de produção (`get_relevant_articles_with_details()` reusa `get_relevant_articles()`)
  - Exibe artigos que seriam incluídos no contexto com: título (link para edição), prioridade (badge colorido), keywords (destacando em azul as que fizeram match), tamanho (chars/words/tokens), trecho do conteúdo (200 chars)
  - Resumo com 3 cards estatísticos: Artigos Encontrados, Total de Caracteres, Tokens Estimados
  - **Validação de Tamanho de Artigos:** Função `estimate_article_size($content)` para estimar tamanho baseado em caracteres, palavras e aproximação de tokens (1 token ≈ 4 chars para português)
  - Classificação de tamanho: Curto (<500 chars), Médio (500-2000 chars), Longo (>2000 chars)
  - Metabox "Validação de Tamanho" na tela de edição do CPT mostrando classificação com badge colorido (verde/amarelo/vermelho), estatísticas detalhadas e aviso se artigo muito longo
  - Sugestão automática para resumir ou dividir artigos longos (>2000 chars)
  - Badges de tamanho exibidos tanto no teste quanto na listagem de artigos
  - Assets: `assets/css/kb-tester.css` (4.4KB, estilos para cards, badges, grid responsivo) e `assets/js/kb-tester.js` (7KB, AJAX, renderização dinâmica, destaque de keywords)
  - Endpoint AJAX: `wp_ajax_dps_ai_kb_test_matching` com segurança (nonce, capability `edit_posts`)
  - Interface responsiva com grid adaptativo para mobile
- **AI Add-on (v1.6.2)**: Interface Administrativa para Gerenciar Base de Conhecimento
  - Criada nova página admin "Base de Conhecimento" (submenu no menu DPS)
  - Slug da página: `dps-ai-knowledge-base`
  - Classe `DPS_AI_Knowledge_Base_Admin` em `includes/class-dps-ai-knowledge-base-admin.php`
  - Listagem completa dos artigos do CPT `dps_ai_knowledge` com colunas: Título, Keywords, Prioridade, Status, Ações
  - **Edição Rápida Inline:** Permite editar keywords e prioridade diretamente na listagem sem entrar em cada post
  - Botão "Editar Rápido" por linha abre formulário inline com textarea (keywords) e input numérico (prioridade 1-10)
  - Salvamento via AJAX com validação de nonce e capability (`edit_posts`)
  - Feedback visual de sucesso (linha pisca em verde) e notice temporária
  - Botões Salvar (verde primário) e Cancelar
  - **Filtros e Ordenação:** Busca por texto (título), filtro por prioridade (Alta 8-10/Média 4-7/Baixa 1-3), ordenação por Título ou Prioridade (ASC/DESC)
  - Botão "Limpar Filtros" quando filtros estão ativos
  - Badges coloridos para prioridade (verde=alta, amarelo=média, cinza=baixa) e status (publicado/rascunho/ativo/inativo)
  - Link para edição completa do post em cada linha
  - Contador de total de artigos exibido
  - Assets: `assets/css/kb-admin.css` (estilos, badges, animações) e `assets/js/kb-admin.js` (AJAX, edição inline, validação)
  - Endpoint AJAX: `wp_ajax_dps_ai_kb_quick_edit` com segurança (nonce, capability, sanitização, escapagem)
  - Visual consistente com padrões do admin WordPress (tabelas, classes, botões)
- **AI Add-on (v1.6.2)**: Integração Real da Base de Conhecimento com Matching por Keywords
  - Implementada busca automática de artigos relevantes baseada em keywords nas perguntas dos clientes
  - Método `DPS_AI_Knowledge_Base::get_relevant_articles()` agora é chamado automaticamente em `answer_portal_question()` e `get_ai_response()` (chat público)
  - Até 5 artigos mais relevantes são incluídos no contexto da IA, ordenados por prioridade (1-10)
  - Artigos são formatados com cabeçalho "INFORMAÇÕES DA BASE DE CONHECIMENTO:" para clareza no contexto
  - Infraestrutura de metaboxes de keywords (`_dps_ai_keywords`) e prioridade (`_dps_ai_priority`) já existia, apenas conectada ao fluxo de respostas
  - Documentação completa em `docs/implementation/AI_KNOWLEDGE_BASE_MULTILINGUAL_IMPLEMENTATION.md`
- **AI Add-on (v1.6.2)**: Suporte Real a Multiidioma com Instruções Explícitas
  - Implementado método `get_base_system_prompt_with_language($language)` que adiciona instrução explícita de idioma ao system prompt
  - Suporte a 4 idiomas: pt_BR (Português Brasil), en_US (English US), es_ES (Español), auto (detectar automaticamente)
  - Instrução orienta a IA a SEMPRE responder no idioma configurado, mesmo que artigos da base estejam em outro idioma
  - Configuração de idioma (`dps_ai_settings['language']`) já existia, agora é efetivamente utilizada nas instruções
  - Aplicado em todos os contextos: chat do portal, chat público e assistente de mensagens (WhatsApp/Email)
  - Método similar `get_public_system_prompt_with_language()` criado para chat público
- **AI Add-on (v1.6.1)**: Limpeza Automática de Dados Antigos
  - Implementada rotina de limpeza automática via WP-Cron para deletar métricas e feedback com mais de 365 dias (configurável)
  - Criada classe `DPS_AI_Maintenance` em `includes/class-dps-ai-maintenance.php`
  - Adicionada limpeza automática de transients expirados relacionados à IA
  - Evento agendado para rodar diariamente às 03:00 (horário do servidor)
  - Nova configuração "Período de Retenção de Dados" na página de settings (padrão: 365 dias, mínimo: 30, máximo: 3650)
  - Botão de limpeza manual na página de settings com estatísticas de dados armazenados
  - Função `DPS_AI_Maintenance::get_storage_stats()` para exibir volume de dados e registros mais antigos
- **AI Add-on (v1.6.1)**: Logger Condicional Respeitando WP_DEBUG
  - Criado sistema de logging condicional em `includes/dps-ai-logger.php`
  - Funções helper: `dps_ai_log()`, `dps_ai_log_debug()`, `dps_ai_log_info()`, `dps_ai_log_warning()`, `dps_ai_log_error()`
  - Logs detalhados (debug/info/warning) são registrados apenas quando `WP_DEBUG` está habilitado OU quando a opção "Enable debug logging" está ativa
  - Em produção (debug desabilitado), apenas erros críticos são registrados
  - Nova configuração "Habilitar Logs Detalhados" na página de settings
  - Indicador visual quando `WP_DEBUG` está ativo nas configurações
- **AI Add-on (v1.6.1)**: Melhorias de UX na Página de Configurações
  - Toggle de visibilidade da API Key com ícone de olho (dashicons) para mostrar/ocultar chave
  - Destaque visual do modelo GPT atualmente selecionado na tabela de custos
  - Nova coluna "Status" na tabela de custos mostrando badge "Modelo Ativo" para o modelo em uso
  - Background azul claro e borda lateral azul destacando a linha do modelo ativo
  - Melhor acessibilidade com texto explícito além de indicadores visuais
- **AI Add-on (v1.6.1)**: Melhorias de UX no Widget de Chat
  - Autoscroll inteligente para a última mensagem (apenas se usuário não estiver lendo mensagens antigas)
  - Textarea auto-expansível até 6 linhas (~120px) com overflow interno após o limite
  - Implementado tanto no chat do portal (`dps-ai-portal.js`) quanto no chat público (`dps-ai-public-chat.js`)
  - Detecção automática de posição de scroll: não interrompe leitura de mensagens anteriores
- **AI Add-on (v1.6.1)**: Dashboard de Analytics com Gráficos e Conversão de Moeda
  - Integração com Chart.js 4.4.0 via CDN para visualização de dados
  - Gráfico de linhas: uso de tokens ao longo do tempo
  - Gráfico de barras: número de requisições por dia
  - Gráfico de área: custo acumulado no período (USD e BRL com eixos duplos)
  - Nova configuração "Taxa de Conversão USD → BRL" nas settings (validação 0.01-100)
  - Exibição automática de custos em BRL nos cards do dashboard quando taxa configurada
  - Aviso visual indicando taxa atual ou sugerindo configuração
  - Link direto para configurar taxa a partir do analytics
- **AI Add-on (v1.6.1)**: Exportação CSV de Métricas e Feedbacks
  - Botão "Exportar CSV" na página de analytics para exportar métricas do período filtrado
  - Botão "Exportar Feedbacks CSV" para exportar últimos 1000 feedbacks
  - CSV de métricas inclui: data, perguntas, tokens (entrada/saída/total), custo (USD/BRL), tempo médio, erros, modelo
  - CSV de feedbacks inclui: data/hora, cliente ID, pergunta, resposta, tipo de feedback, comentário
  - Encoding UTF-8 com BOM para compatibilidade com Excel
  - Separador ponto-e-vírgula (`;`) para melhor compatibilidade com Excel Brasil
  - Tratamento de caracteres especiais (acentos, vírgulas, quebras de linha)
  - Endpoints seguros: `admin-post.php?action=dps_ai_export_metrics` e `admin-post.php?action=dps_ai_export_feedback`
  - Verificação de capability `manage_options` e nonces obrigatórios
  - Função helper centralizada `generate_csv()` para reuso de código
- **AI Add-on (v1.6.1)**: Paginação na Listagem de Feedbacks Recentes
  - Implementada paginação de 20 feedbacks por página no dashboard de analytics
  - Controles de navegação padrão do WordPress: Primeira, Anterior, Próxima, Última
  - Input para navegar diretamente a uma página específica (com validação JavaScript)
  - Exibição do total de feedbacks e página atual
  - URL mantém filtros de data ao navegar entre páginas
  - Controles exibidos apenas quando há mais de uma página
  - Parâmetro `?feedback_paged=N` na URL para controlar página atual
  - Nova função `DPS_AI_Analytics::count_feedback()` para contar total de registros
  - Adicionado parâmetro `$offset` na função `get_recent_feedback()` para suportar paginação
- **AI Add-on (v1.6.1)**: Sistema de Prompts Centralizado e Customizável
  - Criado diretório `/prompts` com arquivos de system prompts separados por contexto
  - 4 contextos disponíveis: `portal`, `public`, `whatsapp`, `email`
  - Nova classe `DPS_AI_Prompts` em `includes/class-dps-ai-prompts.php` gerencia carregamento e filtros
  - Arquivos de prompt:
    - `prompts/system-portal.txt` - Chat do Portal do Cliente
    - `prompts/system-public.txt` - Chat Público para visitantes
    - `prompts/system-whatsapp.txt` - Mensagens via WhatsApp
    - `prompts/system-email.txt` - Conteúdo de e-mails
  - Filtros do WordPress para customização:
    - `dps_ai_system_prompt` - Filtro global para todos os contextos
    - `dps_ai_system_prompt_{contexto}` - Filtro específico por contexto (ex: `dps_ai_system_prompt_portal`)
  - API simplificada: `DPS_AI_Prompts::get('contexto')` retorna prompt com filtros aplicados
  - Retrocompatibilidade: métodos `get_base_system_prompt()` e `get_public_system_prompt()` agora usam a nova classe internamente
  - Funções auxiliares: `is_valid_context()`, `get_available_contexts()`, `clear_cache()`
  - Cache interno para evitar releituras de arquivos
- **AI Add-on (v1.6.1)**: Estrutura de Testes Unitários e CI
  - Configurado PHPUnit para testes automatizados do add-on
  - Criado `composer.json` com PHPUnit 9.5+ como dependência de desenvolvimento
  - Arquivo `phpunit.xml` com configuração de test suite e coverage
  - Bootstrap de testes (`tests/bootstrap.php`) com mocks de funções WordPress
  - **Testes implementados** (24 testes no total):
    - `Test_DPS_AI_Email_Parser` - 8 testes para parsing de e-mails (JSON, labeled, separated, plain, malicioso, vazio, text_to_html, stats)
    - `Test_DPS_AI_Prompts` - 9 testes para sistema de prompts (4 contextos, validação, cache, clear_cache)
    - `Test_DPS_AI_Analytics` - 7 testes para cálculo de custos (GPT-4o-mini, GPT-4o, GPT-4-turbo, zero tokens, modelo desconhecido, conversão USD→BRL, tokens fracionários)
  - **GitHub Actions CI** (`.github/workflows/phpunit.yml`):
    - Executa testes em push/PR para branches `main`, `develop`, `copilot/**`
    - Testa em múltiplas versões do PHP (8.0, 8.1, 8.2)
    - Gera relatório de cobertura para PHP 8.1
    - Cache de dependências Composer para build mais rápido
  - Scripts Composer: `composer test` e `composer test:coverage`
  - Documentação completa em `tests/README.md` com instruções de uso e troubleshooting
  - Arquivo `.gitignore` para excluir `vendor/`, `coverage/` e arquivos de cache

#### Changed (Alterado)
- **AI Add-on (v1.6.2)**: Integração da Base de Conhecimento nos Fluxos de Resposta
  - Modificado `DPS_AI_Assistant::answer_portal_question()` para buscar e incluir artigos relevantes via `get_relevant_articles()`
  - Modificado `DPS_AI_Public_Chat::get_ai_response()` para buscar e incluir artigos relevantes no chat público
  - Contexto da base de conhecimento é adicionado após contexto do cliente/negócio e antes da pergunta do usuário

#### Deprecated (Depreciado)
- **Client Portal (v2.4.0)**: Shortcode `[dps_client_login]` descontinuado (Fase 1.1)
  - Shortcode agora exibe mensagem de depreciação ao invés de formulário de login
  - Sistema de login por usuário/senha removido em favor de autenticação exclusiva por token (magic link)
  - Remoção completa prevista para v3.0.0
  - Migração: clientes devem usar apenas `[dps_client_portal]` e solicitar links de acesso
  - Documentação atualizada em `TOKEN_AUTH_SYSTEM.md` com guia de migração
  - Artigos são formatados com cabeçalho claro "INFORMAÇÕES DA BASE DE CONHECIMENTO:" para melhor compreensão da IA
- **AI Add-on (v1.6.2)**: Aplicação Real do Idioma Configurado em Todos os Contextos
  - Modificado `DPS_AI_Assistant::answer_portal_question()` para usar `get_base_system_prompt_with_language()` ao invés de `get_base_system_prompt()`
  - Modificado `DPS_AI_Public_Chat::get_ai_response()` para usar `get_public_system_prompt_with_language()`
  - Modificado `DPS_AI_Message_Assistant::suggest_whatsapp_message()` e `suggest_email_message()` para usar prompt com idioma
  - System prompt agora inclui instrução explícita: "IMPORTANTE: Você DEVE responder SEMPRE em [IDIOMA]"
  - Configuração `dps_ai_settings['language']` que já existia agora é efetivamente utilizada
- **AI Add-on (v1.6.1)**: Tratamento Robusto de Erros nas Chamadas HTTP
  - Refatorada classe `DPS_AI_Client::chat()` com tratamento avançado de erros
  - Validação de array de mensagens antes de enviar requisição
  - Tratamento específico para diferentes códigos HTTP de erro (400, 401, 429, 500, 502, 503)
  - Adicionado try/catch para capturar exceções inesperadas
  - Logs contextualizados com detalhes técnicos (timeout, response_time, status code, tokens_used)
  - Validação de resposta vazia e JSON inválido antes de processar
  - Mensagens de erro amigáveis sem expor dados sensíveis (API key, payloads, etc.)
- **AI Add-on (v1.6.1)**: Refatoração de Logging em Todas as Classes
  - Substituídos 7 chamadas `error_log()` por funções do novo logger condicional
  - Afetados: `class-dps-ai-message-assistant.php` (4 ocorrências)
  - Todos os logs agora respeitam configurações de debug do plugin
- **AI Add-on (v1.6.1)**: Dashboard de Analytics Aprimorado
  - Método `enqueue_charts_scripts()` para carregar Chart.js e preparar dados
  - Dados agregados por dia incluem cálculo de custo acumulado
  - Gráficos responsivos adaptam-se ao tamanho da tela
  - Layout em grid para gráficos (mínimo 400px por coluna)
- **AI Add-on (v1.6.1)**: Refatoração de System Prompts (BREAKING para customizações diretas)
  - `DPS_AI_Assistant::get_base_system_prompt()` agora usa `DPS_AI_Prompts::get('portal')` internamente
  - `DPS_AI_Public_Chat::get_public_system_prompt()` agora usa `DPS_AI_Prompts::get('public')` internamente
  - `DPS_AI_Message_Assistant::build_message_system_prompt()` agora carrega prompts base de arquivos antes de adicionar instruções específicas
  - **IMPORTANTE**: Se você estava sobrescrevendo métodos de prompt diretamente, migre para os filtros `dps_ai_system_prompt` ou `dps_ai_system_prompt_{contexto}`
- **AI Add-on (v1.6.1)**: Parser Robusto de Respostas de E-mail da IA
  - Criada classe `DPS_AI_Email_Parser` em `includes/class-dps-ai-email-parser.php` para parsing defensivo e robusto de e-mails
  - Suporta múltiplos formatos de resposta: JSON estruturado, formato com rótulos (ASSUNTO:/CORPO:), separado por linha vazia e texto plano
  - Implementados fallbacks inteligentes quando formato esperado não é encontrado
  - Validação e sanitização automática com `wp_kses_post()`, `sanitize_text_field()`, `strip_tags()`
  - Proteção contra scripts maliciosos e conteúdo perigoso injetado pela IA
  - Limite configurável para tamanho do assunto (padrão: 200 caracteres)
  - Logging detalhado do processo de parsing para diagnóstico (formato usado, tamanho de subject/body, estatísticas)
  - Método `DPS_AI_Email_Parser::text_to_html()` para converter texto plano em HTML básico
  - Método `DPS_AI_Email_Parser::get_parse_stats()` para obter estatísticas sobre qualidade do parse
  - Classe `DPS_AI_Message_Assistant` refatorada para usar o novo parser robusto
  - Método `parse_email_response()` depreciado mas mantido para retrocompatibilidade

#### Fixed (Corrigido)
- **Client Portal Add-on (v2.4.1)**: Correção de aviso "Translation loading triggered too early" no WordPress 6.7.0+
  - **Problema**: Aviso PHP Notice "Translation loading for the dps-client-portal domain was triggered too early" no WordPress 6.7.0+
  - **Causa Raiz**: Constante `DPS_CLIENT_PORTAL_PAGE_TITLE` definia valor com `__()` no nível do arquivo (linha 61), antes do hook `init`
  - **Correção Aplicada**:
    - Removido `__()` da definição da constante; constante agora contém string não traduzida 'Portal do Cliente'
    - Adicionada tradução onde a constante é usada para criar páginas (linha 443): `__( DPS_CLIENT_PORTAL_PAGE_TITLE, 'dps-client-portal' )`
    - Busca de páginas existentes usa título não traduzido para consistência entre idiomas
  - **Impacto**: Elimina avisos de carregamento prematuro de traduções nos logs; páginas criadas usam título traduzido conforme idioma do site
  - **Arquivos Alterados**: `plugins/desi-pet-shower-client-portal/desi-pet-shower-client-portal.php`
  - **Compatibilidade**: Mantida retrocompatibilidade - constante ainda existe e funciona normalmente
- **AGENDA Add-on (v1.4.1)**: Correção de PHP Warning - Undefined array key "payment"
  - **Problema**: Avisos PHP "Undefined array key 'payment'" na linha 455 de `trait-dps-agenda-renderer.php`
  - **Causa Raiz**: Funções de renderização (`render_appointment_row`, `render_appointment_row_tab1`, `render_appointment_row_tab2`) acessavam índices do array `$column_labels` sem verificar existência
  - **Correção Aplicada**: Adicionado operador de coalescência nula (`??`) em todos os acessos a `$column_labels` com valores padrão traduzidos
  - **Escopo da Correção**:
    - `trait-dps-agenda-renderer.php`: 13 ocorrências corrigidas nas funções de renderização
    - `desi-pet-shower-agenda-addon.php`: 6 ocorrências corrigidas nos cabeçalhos de tabela
  - **Impacto**: Elimina warnings PHP nos logs e previne erros futuros caso array incompleto seja passado
  - **Arquivos Alterados**:
    - `plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php`
    - `plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php`
- **Client Portal Add-on (v2.4.1)**: Correção Crítica no Login por Token
  - **Problema**: Links de acesso mágico (magic links) redirecionavam para tela de login mesmo com token válido
  - **Causa Raiz**: Sintaxe incorreta do `setcookie()` com array associativo (incompatível com PHP 7.3+)
  - **Correção Aplicada** em `class-dps-portal-session-manager.php`:
    - Substituída sintaxe `setcookie($name, $value, $options_array)` por parâmetros individuais
    - Adicionado `header()` separado para `SameSite=Strict` (compatibilidade PHP <7.3)
    - Corrigida prioridade do hook `validate_session` de 5 para 10 (executa APÓS autenticação por token)
    - Removidas chamadas deprecadas a `maybe_start_session()` que não faziam nada
  - **Impacto**: Clientes agora conseguem acessar o portal via magic link sem serem redirecionados para login
  - **Arquivos Alterados**:
    - `plugins/desi-pet-shower-client-portal/includes/class-dps-portal-session-manager.php`
    - `plugins/desi-pet-shower-client-portal/includes/class-dps-client-portal.php`
  - **Commit**: Corrigir sintaxe setcookie() e ordem de execução de hooks
- **AI Add-on (v1.6.1)**: Tabelas de Banco de Dados Não Criadas em Atualizações
  - **Problema**: Usuários que atualizaram de v1.4.0 para v1.5.0+ sem desativar/reativar o plugin não tinham as tabelas `wp_dps_ai_metrics` e `wp_dps_ai_feedback` criadas, causando erros na página de analytics
  - **Causa Raiz**: Tabelas eram criadas apenas no hook de ativação (`register_activation_hook`), que não executa durante atualizações de plugin
  - **Solução Implementada**:
    - Adicionado rastreamento de versão do schema via opção `dps_ai_db_version`
    - Criada função `dps_ai_maybe_upgrade_database()` que executa em `plugins_loaded` (prioridade 10)
    - Verifica versão instalada e cria tabelas automaticamente se necessário
    - Segue mesmo padrão de versionamento usado em outros add-ons
  - **Correção de SQL para dbDelta()**:
    - Corrigido espaçamento após `PRIMARY KEY` (deve ter 2 espaços conforme requisito do WordPress)
    - Tabelas agora são criadas corretamente em todas as instalações
  - **Impacto**: Analytics funcionará corretamente para todos os usuários, incluindo aqueles que atualizaram sem reativar o plugin
  - Arquivos alterados: `desi-pet-shower-ai-addon.php`, `includes/class-dps-ai-analytics.php`

#### Security (Segurança)
- **White Label Add-on (v1.1.1)**: Correções Críticas de Segurança
  - **Validação de Open Redirect Reforçada**: `class-dps-whitelabel-access-control.php`
    - Validação redundante no método `get_login_url()` além da validação no salvamento
    - Sanitização com `esc_url_raw()` antes de retornar URL customizada
    - Log de tentativas suspeitas via `DPS_Logger` quando domínio externo é detectado
    - Proteção contra manipulação direta no banco de dados
  - **Sanitização Robusta de CSS Customizado**: `class-dps-whitelabel-settings.php`
    - Proteção contra bypass via encoding hexadecimal/octal (ex: `\74` = 't')
    - Bloqueio de URLs com encoding suspeito em `url()`
    - Validação adicional via `preg_replace_callback` para detectar caracteres codificados
    - Mantém bloqueio de `javascript:`, `expression()`, `behavior:`, `vbscript:`, `data:` e `@import`
    - Adicionado hook `dps_whitelabel_sanitize_custom_css` para customização
  - **Validação de URLs de Logo Implementada**: `class-dps-whitelabel-settings.php`
    - Método `validate_logo_url()` agora é chamado em `handle_settings_save()`
    - Valida formatos permitidos: JPG, PNG, GIF, SVG, WebP, ICO
    - Verifica MIME type via Media Library para attachments do WordPress
    - Valida extensão para URLs externas
    - Exibe mensagem de aviso e define campo vazio quando URL inválida

#### Refactoring (Interno)
- **White Label Add-on (v1.1.2)**: Otimizações de Performance
  - **Cache de CSS Customizado**: `class-dps-whitelabel-assets.php`
    - Implementado cache via transient (24 horas) para CSS gerado dinamicamente
    - Método `invalidate_css_cache()` limpa cache ao salvar configurações
    - Reduz processamento em cada pageload (regeneração somente quando necessário)
  - **Verificação Otimizada de Hooks Admin**: `class-dps-whitelabel-assets.php`
    - Substituído `strpos()` genérico por whitelist de hooks específicos
    - Previne carregamento de CSS em páginas não-DPS
    - Adicionado filtro `dps_whitelabel_admin_hooks` para extensibilidade
  - **Cache Estático de Settings em Memória**: Aplicado em 6 classes
    - `class-dps-whitelabel-settings.php`
    - `class-dps-whitelabel-smtp.php`
    - `class-dps-whitelabel-login-page.php`
    - `class-dps-whitelabel-admin-bar.php`
    - `class-dps-whitelabel-maintenance.php`
    - `class-dps-whitelabel-access-control.php`
    - Cache estático evita múltiplas chamadas `get_option()` e `wp_parse_args()` por requisição
    - Método `clear_cache()` limpa cache ao salvar configurações
    - Método `get_settings()` aceita parâmetro `$force_refresh` para invalidação explícita

#### Changed (Alterado)
- **White Label Add-on (v1.2.0)**: Melhorias de UX Básicas
  - **Validação de URLs em Tempo Real**: `whitelabel-admin.js`
    - Validação JavaScript ao sair do campo (evento `blur`)
    - Feedback visual imediato com ícones ✓/✗ e cores verde/vermelho
    - Valida formatos de URLs para logos, website, suporte, documentação, termos e privacidade
  - **Paletas de Cores Pré-definidas**: `admin-settings.php`, `whitelabel-admin.js`
    - 5 paletas harmonizadas: Padrão DPS, Oceano, Floresta, Pôr do Sol, Moderno
    - Aplicação com um clique via JavaScript
    - Integração com WordPress Color Picker
    - Feedback visual quando paleta é aplicada
  - **Indicadores de Campos Recomendados**: `admin-settings.php`
    - Asterisco laranja (*) em "Nome da Marca" e "Logo"
    - Tooltip explicativo ao passar mouse
    - Melhora orientação do usuário sobre campos importantes
  - **Scroll Automático para Mensagens**: `whitelabel-admin.js`
    - Scroll suave para mensagens de sucesso/erro após salvar
    - Garante que usuário veja feedback mesmo em telas pequenas
  - **Responsividade Melhorada**: `whitelabel-admin.css`
    - Novo breakpoint em 480px para tablets/mobiles em portrait
    - Form tables adaptam layout em colunas verticais
    - Botões e presets ocupam largura total em mobile
    - Melhora usabilidade em dispositivos pequenos

- **White Label Add-on (v1.2.1)**: Funcionalidades Essenciais (Parcial)
  - **Hide Author Links Implementado**: `class-dps-whitelabel-branding.php`
    - Opção `hide_author_links` agora funcional (estava salva mas não aplicada)
    - Filtra `the_author_posts_link` e `author_link` do WordPress
    - Remove links de autor em posts quando opção ativada
    - Útil para white label completo sem referência a autores WordPress
  - **Teste de Conectividade SMTP**: `class-dps-whitelabel-smtp.php`, `whitelabel-admin.js`
    - Novo método `test_smtp_connection()` para testar apenas conectividade (sem enviar e-mail)
    - Verifica host, porta, credenciais e autenticação SMTP
    - Timeout de 10 segundos para evitar espera longa
    - Botão "Testar Conexão SMTP" na aba de configurações SMTP
    - Feedback visual (✓ sucesso / ✗ erro) via AJAX
    - Útil para diagnosticar problemas de configuração antes de enviar e-mails

#### Added (Adicionado)
- **AI Add-on (v1.6.0)**: Chat Público para Visitantes do Site
  - **Novo Shortcode `[dps_ai_public_chat]`**: Chat de IA aberto para visitantes não logados
    - Permite que visitantes tirem dúvidas sobre serviços de Banho e Tosa
    - Não requer autenticação (diferente do chat do Portal do Cliente)
    - Foco em informações gerais: preços, horários, serviços, formas de pagamento
  - **Modos de Exibição**:
    - `mode="inline"`: Widget integrado na página
    - `mode="floating"`: Botão flutuante no canto da tela
  - **Temas Visuais**:
    - `theme="light"`: Tema claro (padrão)
    - `theme="dark"`: Tema escuro
    - `primary_color="#hex"`: Cor principal customizável
  - **FAQs Personalizáveis**:
    - Botões clicáveis com perguntas frequentes
    - Configurável via painel administrativo
    - FAQs padrão incluídas
  - **Rate Limiting por IP**:
    - Limite de 10 perguntas por minuto
    - Limite de 60 perguntas por hora
    - Proteção contra abuso por visitantes
  - **Configurações Administrativas**:
    - Seção dedicada "Chat Público para Visitantes"
    - Campo para informações do negócio (horários, endereço, pagamentos)
    - Instruções adicionais para personalização do comportamento
  - **Integração com Métricas**:
    - Registro de interações (perguntas, tempo de resposta)
    - Registro de feedback (👍/👎)
    - Métricas agregadas no dashboard de Analytics
  - **System Prompt Específico**:
    - Prompt otimizado para visitantes
    - Foco em informações públicas (sem dados de clientes)
    - Tom amigável com uso de emojis 🐶🐱
  - **Novos Arquivos**:
    - `includes/class-dps-ai-public-chat.php`: Classe principal
    - `assets/css/dps-ai-public-chat.css`: Estilos responsivos
    - `assets/js/dps-ai-public-chat.js`: Interatividade do chat

- **Loyalty Add-on (v1.2.0)**: Multiplicador de nível, compartilhamento e exportação
  - **Multiplicador de Nível Ativo**: Pontos agora são multiplicados por nível de fidelidade
    - Bronze: 1x (padrão)
    - Prata: 1.5x (a partir de 500 pontos)
    - Ouro: 2x (a partir de 1000 pontos)
  - **Compartilhamento via WhatsApp**: Botão para compartilhar código de indicação
    - Mensagem pré-formatada com código e link
    - Abre WhatsApp Web ou app mobile
  - **Exportação CSV de Indicações**: Botão para baixar relatório
    - Inclui indicador, indicado, código, data, status e recompensas
    - Formato CSV com BOM UTF-8 para compatibilidade com Excel
  - **Novos Métodos na API `DPS_Loyalty_API`**:
    - `calculate_points_for_amount($amount, $client_id)`: preview de pontos antes de conceder
    - `get_top_clients($limit)`: ranking de clientes por pontos
    - `get_clients_by_tier()`: contagem de clientes por nível
    - `export_referrals_csv($args)`: exportação de indicações
  - **Novos Hooks**:
    - `dps_loyalty_points_awarded_appointment`: disparado após conceder pontos por atendimento
    - `dps_loyalty_tier_bonus_applied`: disparado quando bônus de nível é aplicado
  - **UX Melhorada**:
    - Labels de contexto traduzidos no histórico de pontos
    - Datas formatadas em dd/mm/yyyy HH:mm
    - Seção de indicação redesenhada com box, link e botões de ação
    - Contador de indicações na aba
  - **Documentação**: Análise profunda atualizada em `docs/analysis/LOYALTY_ADDON_ANALYSIS.md`

- **AI Add-on (v1.5.0)**: Nova versão com 8 funcionalidades principais
  - **1. Sugestões de Perguntas Frequentes (FAQs)**:
    - Botões clicáveis exibidos no widget para perguntas comuns
    - FAQs personalizáveis na página de configurações
    - FAQs padrão incluídas (horário, preços, agendamento, etc.)
  - **2. Feedback Positivo/Negativo**:
    - Botões 👍/👎 após cada resposta da IA
    - Registro de feedback em tabela customizada `dps_ai_feedback`
    - Handler AJAX `dps_ai_submit_feedback` para salvar feedback
  - **3. Métricas de Uso**:
    - Tabela `dps_ai_metrics` para registro de uso diário
    - Contabilização de perguntas, tokens, erros, tempo de resposta
    - Registro por cliente e por dia
  - **4. Base de Conhecimento**:
    - CPT `dps_ai_knowledge` para FAQs/artigos personalizados
    - Taxonomia para categorizar artigos
    - Palavras-chave para ativação automática no contexto
    - Interface admin para gerenciar conhecimento
  - **5. Widget Flutuante Alternativo**:
    - Modo "chat bubble" no canto da tela
    - Opção de posição (inferior direito/esquerdo)
    - Animação de abertura/fechamento suave
    - Toggle entre modos na configuração
  - **6. Suporte a Múltiplos Idiomas**:
    - Opções: Português (Brasil), English, Español, Automático
    - Instrução de idioma enviada ao modelo GPT
    - Interface traduzível via text domain
  - **7. Agendamento via Chat**:
    - Verificação de disponibilidade por data
    - Dois modos: solicitar confirmação ou agendamento direto
    - Handlers AJAX para disponibilidade e solicitação
    - Notificação por e-mail para admins (modo solicitação)
    - Criação automática de agendamentos (modo direto)
  - **8. Dashboard de Analytics**:
    - Página admin com métricas visuais em cards
    - Filtro por período (data início/fim)
    - Métricas: perguntas, tokens, custos, tempo de resposta
    - Tabela de feedback recente
    - Uso diário com histórico
  - **Classes Novas**:
    - `DPS_AI_Analytics`: métricas, feedback, custos
    - `DPS_AI_Knowledge_Base`: CPT, taxonomia, artigos
    - `DPS_AI_Scheduler`: agendamento via chat

- **AI Add-on (v1.4.0)**: Melhorias de interface e funcionalidades
  - **Modelos GPT Atualizados**: Adicionados GPT-4o Mini (recomendado), GPT-4o e GPT-4 Turbo
    - GPT-4o Mini como modelo padrão recomendado para melhor custo/benefício em 2024+
    - Mantido GPT-3.5 Turbo como opção legada
  - **Teste de Conexão**: Botão para validar API key diretamente na página de configurações
    - Handler AJAX `dps_ai_test_connection` com verificação de nonce e permissões
    - Feedback visual de sucesso/erro em tempo real
  - **Tabela de Custos**: Informações de custo estimado por modelo na página admin
  - **Interface do Widget Modernizada**:
    - Novo design com header azul gradiente e ícone de robô
    - Badge de status "Online" com animação de pulse
    - Clique no header inteiro para expandir/recolher
    - Botão de envio circular com ícone de seta
    - Mensagens com estilo de chat moderno (bolhas coloridas)
    - Textarea com auto-resize dinâmico
    - Scrollbar estilizada no container de mensagens
    - Layout horizontal de input em desktop, vertical em mobile
  - **Histórico de Conversas**: Persistência via sessionStorage
    - Mensagens mantidas durante a sessão do navegador
    - Função `dpsAIClearHistory()` para limpar manualmente
  - **UX Aprimorada**:
    - Envio com Enter (sem Shift) além de Ctrl+Enter
    - Dica de atalho de teclado visível
    - Animações suaves de slide para toggle
    - Foco automático no textarea ao expandir

- **Push Notifications Add-on (v1.0.0)**: Notificações push nativas do navegador
  - **Web Push API**: Implementação nativa sem dependência de serviços externos
    - Chaves VAPID geradas automaticamente na ativação
    - Service Worker para receber notificações em segundo plano
    - Suporte multi-dispositivo por usuário
  - **Eventos notificados**:
    - Novos agendamentos (`dps_base_after_save_appointment`)
    - Mudanças de status (`dps_appointment_status_changed`)
    - Reagendamentos (`dps_appointment_rescheduled`)
  - **Interface administrativa**:
    - Página de configurações em desi.pet by PRObst > Push Notifications
    - Indicador de status com cores (inscrito/não inscrito/negado)
    - Botão para ativar notificações no navegador atual
    - Botão para enviar notificação de teste
    - Checkboxes para selecionar eventos a notificar
  - **API pública**:
    - `DPS_Push_API::send_to_user($user_id, $payload)` - Envia para usuário específico
    - `DPS_Push_API::send_to_all_admins($payload, $exclude_ids)` - Envia para todos os admins
    - `DPS_Push_API::generate_vapid_keys()` - Gera novo par de chaves VAPID
  - **Segurança**:
    - Nonces em todas as ações AJAX
    - Verificação de capability `manage_options`
    - Chaves VAPID únicas por instalação
    - Remoção automática de inscrições expiradas
  - **Arquivos**:
    - `desi-pet-shower-push-addon.php` - Plugin principal
    - `includes/class-dps-push-api.php` - API de envio
    - `assets/js/push-addon.js` - JavaScript do admin
    - `assets/js/push-sw.js` - Service Worker
    - `assets/css/push-addon.css` - Estilos da interface
  - **Requisitos**: HTTPS obrigatório, PHP 7.4+, navegadores modernos
- **Agenda Add-on (v1.3.2)**: Funcionalidades administrativas avançadas
  - **Dashboard de KPIs**: Cards de métricas no topo da agenda
    - Agendamentos pendentes/finalizados do dia
    - Faturamento estimado baseado em serviços
    - Taxa de cancelamento semanal
    - Média de atendimentos diários (últimos 7 dias)
  - **Ações em Lote**: Atualização de múltiplos agendamentos de uma só vez
    - Checkbox de seleção em cada linha da tabela
    - Checkbox "selecionar todos" no header
    - Barra de ações flutuante (sticky) com botões:
      - Finalizar selecionados
      - Marcar como pago
      - Cancelar selecionados
    - Handler AJAX `dps_bulk_update_status` com validação de nonce
  - **Reagendamento Rápido**: Modal simplificado para alterar data/hora
    - Botão "📅 Reagendar" em cada linha da tabela
    - Modal com apenas campos de data e hora
    - Handler AJAX `dps_quick_reschedule`
    - Hook `dps_appointment_rescheduled` para notificações
  - **Histórico de Alterações**: Registro de todas as mudanças em agendamentos
    - Metadado `_dps_appointment_history` com até 50 entradas
    - Registra: criação, alteração de status, reagendamento
    - Indicador visual "📜" quando há histórico
    - Handler AJAX `dps_get_appointment_history`
    - Integração com hook `dps_appointment_status_changed`
  - **API de KPIs**: Handler AJAX `dps_get_admin_kpis` para consulta programática
  - **CSS**: Novos estilos para dashboard, barra de lote, modal de reagendamento
  - **JavaScript**: Lógica para seleção em lote, modal de reagendamento, histórico
- **Constante `DPS_DISABLE_CACHE`**: Nova constante para desabilitar completamente o cache do sistema
  - Útil para desenvolvimento, testes e debug de problemas relacionados a dados em cache
  - Afeta todos os transients de cache de dados (pets, clientes, serviços, estatísticas, métricas, contexto de IA)
  - Não afeta caches de segurança (tokens de login, rate limiting, tentativas de login)
  - Para desabilitar, adicione `define( 'DPS_DISABLE_CACHE', true );` no wp-config.php
  - Documentação completa no README do plugin base
- **Portal do Cliente v2.3.0**: Navegação por Tabs e Widget de Chat em tempo real
  - **Navegação por Tabs**: Interface reorganizada em 4 abas (Início, Agendamentos, Galeria, Meus Dados)
    - Tab "Início": Próximo agendamento + pendências financeiras + programa de fidelidade
    - Tab "Agendamentos": Histórico completo de atendimentos
    - Tab "Galeria": Fotos dos pets
    - Tab "Meus Dados": Formulários de atualização de dados pessoais e pets
  - **Widget de Chat Flutuante**: Comunicação em tempo real com a equipe
    - Botão flutuante no canto inferior direito
    - Badge de mensagens não lidas com animação
    - AJAX polling a cada 10 segundos para novas mensagens
    - Rate limiting (máximo 10 mensagens/minuto por cliente)
    - Notificação automática ao admin via Communications API
  - **Melhorias de UX**:
    - Acessibilidade: ARIA roles, labels e states em tabs e chat
    - Responsividade: Tabs com scroll horizontal em mobile, chat fullscreen
    - Animações CSS suaves em transições de tab e chat
  - **Handlers AJAX**:
    - `dps_chat_get_messages`: Obtém histórico de mensagens
    - `dps_chat_send_message`: Envia nova mensagem do cliente
    - `dps_chat_mark_read`: Marca mensagens do admin como lidas
- **Documentação de compatibilidade**: Criado documento `docs/compatibility/COMPATIBILITY_ANALYSIS.md` com análise detalhada de compatibilidade PHP 8.3+/8.4, WordPress 6.9 e tema Astra
- **Helper dps_get_page_by_title_compat()**: Nova função utilitária no Portal do Cliente para substituir `get_page_by_title()` deprecado
- **Debugging Add-on (v1.1.0)**: Melhorias significativas de funcionalidade, código e UX
  - **Novas funcionalidades**:
    - Busca client-side com highlight de termos encontrados
    - Filtros por tipo de erro (Fatal, Warning, Notice, Deprecated, Parse, DB Error, Exception)
    - Cards de estatísticas com contagem por tipo de erro
    - Exportação/download do arquivo de log
    - Botão de cópia rápida do log para área de transferência
    - Alerta visual na admin bar quando há erros fatais (badge vermelho com animação pulse)
    - Sincronização automática de opções com estado real do wp-config.php
  - **Melhorias de código**:
    - Novo método `sync_options_with_config()` para manter interface consistente com arquivo
    - Método `get_entry_stats()` para estatísticas de entradas do log
    - Método `get_formatted_content()` agora suporta filtro por tipo
    - Cache de entradas parseadas para performance
    - Suporte a tipos adicionais de erro: Exception, Catchable
  - **Melhorias de UX**:
    - Interface com duas abas (Configurações e Visualizador de Log)
    - Dashboard de estatísticas no topo do visualizador
    - Barra de filtros com botões coloridos por tipo de erro
    - Campo de busca com debounce e limpar
    - Feedback visual de sucesso/erro ao copiar
  - **Novos assets**:
    - `assets/js/debugging-admin.js` - busca, filtros e cópia de logs
    - CSS expandido com estilos para stats, filtros e busca
  - **Admin bar melhorada**:
    - Contador diferenciado para erros fatais (badge vermelho)
    - Animação pulse para alertar sobre fatais
    - Link direto para visualizar erros fatais
    - Background visual quando há erros fatais
  - **Impacto**: Experiência de debugging muito mais produtiva com busca, filtros e alertas visuais
- **Debugging Add-on (v1.0.0)**: Novo add-on para gerenciamento de debug do WordPress
  - **Funcionalidades principais**:
    - Configuração de constantes de debug (WP_DEBUG, WP_DEBUG_LOG, WP_DEBUG_DISPLAY, SCRIPT_DEBUG, SAVEQUERIES, WP_DISABLE_FATAL_ERROR_HANDLER) diretamente via interface administrativa
    - Modificação segura do wp-config.php com backup de estado original
    - Visualizador de debug.log com formatação inteligente
    - Destaque visual por tipo de erro (Fatal, Warning, Notice, Deprecated, Parse, DB Error)
    - Formatação de stack traces e pretty-print de JSON
    - Função de limpeza (purge) do arquivo de log
    - Menu na admin bar com acesso rápido e status das constantes
    - Contador de entradas de log na admin bar
  - **Estrutura modular**:
    - Nova pasta `includes/` com classes especializadas:
      - `class-dps-debugging-config-transformer.php` - leitura/escrita do wp-config.php
      - `class-dps-debugging-log-viewer.php` - visualização e parsing do debug.log
      - `class-dps-debugging-admin-bar.php` - integração com admin bar
    - Nova pasta `assets/css/` com `debugging-admin.css` (tema escuro para logs)
  - **Segurança**:
    - Nonces em todas as ações
    - Verificação de capability `manage_options`
    - Validação de permissões de arquivo antes de modificar
    - Confirmação JavaScript antes de purge
  - **Filtros expostos**:
    - `dps_debugging_config_path` - customizar caminho do wp-config.php
    - `dps_debugging_admin_bar_cap` - customizar capability para admin bar
  - **Impacto**: Facilita debugging durante desenvolvimento sem necessidade de plugins externos
- **Stats Add-on (v1.1.0)**: Refatoração completa com novas funcionalidades
  - **Estrutura modular**:
    - Nova pasta `includes/` com `class-dps-stats-api.php` (API pública)
    - Nova pasta `assets/css/` com `stats-addon.css` (estilos externos)
    - Nova pasta `assets/js/` com `stats-addon.js` (gráficos Chart.js)
    - Plugin principal refatorado com métodos menores e especializados
  - **API pública DPS_Stats_API**:
    - `get_appointments_count()` - contagem de atendimentos
    - `get_revenue_total()` / `get_expenses_total()` - totais financeiros
    - `get_financial_totals()` - receita e despesas com integração Finance API
    - `get_ticket_average()` - ticket médio calculado
    - `get_cancellation_rate()` - taxa de cancelamento
    - `get_new_clients_count()` - novos clientes no período
    - `get_inactive_pets()` - pets inativos com query SQL otimizada
    - `get_top_services()` - serviços mais solicitados
    - `get_species_distribution()` - distribuição por espécie
    - `get_top_breeds()` - raças mais atendidas
    - `get_period_comparison()` - comparativo com período anterior (%)
    - `export_metrics_csv()` / `export_inactive_pets_csv()` - exportação CSV
  - **Dashboard visual**:
    - Cards de métricas coloridos com ícones
    - Variação percentual vs período anterior (verde/vermelho)
    - Seções colapsáveis com `<details>` para organização
    - Gráfico de barras para top serviços (Chart.js)
    - Gráfico de pizza para distribuição de espécies (Chart.js)
    - Barras horizontais para top raças
    - Grid responsivo com media queries
  - **Novas métricas**:
    - Ticket médio (receita ÷ atendimentos)
    - Taxa de cancelamento (%)
    - Novos clientes cadastrados no período
    - Comparativo automático com período anterior
  - **Exportação CSV**:
    - Botão "Exportar Métricas CSV" com todas as métricas
    - Botão "Exportar Inativos CSV" com lista de pets
    - BOM UTF-8 para compatibilidade com Excel
    - Nonces para segurança
  - **Otimizações**:
    - Query SQL otimizada para pets inativos (GROUP BY em vez de N+1)
    - Integração com Finance API (quando disponível)
    - Cache via transients mantido
    - Assets carregados via wp_enqueue_* padrão WordPress
  - **Impacto**: Dashboard visual moderno, API para integração, performance melhorada
- **Stats Add-on**: Documento de análise completa do add-on
  - `docs/analysis/STATS_ADDON_ANALYSIS.md` com ~850 linhas de análise detalhada
  - Avaliação de funcionalidade, código, segurança, performance e UX (notas 5-8/10)
  - Identificação de 7 problemas de código (método muito grande, queries N+1, dados não exibidos, etc.)
  - Boas práticas já implementadas (cache, nonces, sanitização, escape, capabilities)
  - Propostas de melhorias: modularização, API pública, otimização de queries, UX visual
  - Mockup de interface melhorada com cards, gráficos e tabelas responsivas
  - Plano de refatoração em 5 fases com estimativa de 38-58h de esforço
  - Sugestão de novas funcionalidades: comparativo de períodos, exportação CSV, ticket médio, taxa de retenção
  - **Impacto**: Documentação técnica completa para orientar desenvolvimento futuro do dashboard de estatísticas
- **ANALYSIS.md**: Seção do Stats Add-on expandida com detalhes de hooks, funções globais, dependências e transients
- **Services Add-on (v1.3.0)**: Novas funcionalidades de pacotes, histórico e catálogo
  - **Pacotes promocionais com desconto**:
    - Combinar múltiplos serviços em um pacote
    - Definir desconto percentual (ex: 10% off no combo)
    - Definir preço fixo alternativo ao desconto
    - Método `DPS_Services_API::calculate_package_price()` para cálculo automático
  - **Histórico de alterações de preços**:
    - Registro automático de todas as alterações de preço
    - Armazena data, usuário, preço antigo e novo
    - Método `DPS_Services_API::get_price_history()` para consulta
    - Mantém últimos 50 registros por serviço
  - **Duplicação de serviço**:
    - Botão "Duplicar" na tabela de serviços
    - Copia todos os metadados (preços, durações, consumo de estoque)
    - Serviço duplicado inicia como inativo (segurança)
    - Método `DPS_Services_API::duplicate_service()` na API
    - Hook `dps_service_duplicated` disparado após duplicação
  - **Shortcode de catálogo público**:
    - `[dps_services_catalog]` para exibir serviços no site
    - Atributos: `show_prices`, `type`, `category`, `layout`
    - Layouts: lista e grid responsivo
    - Agrupa por tipo e categoria automaticamente
    - Destaca pacotes com badge de desconto
  - **API para Portal do Cliente**:
    - Método `get_public_services()` para listar serviços ativos
    - Método `get_portal_services()` com dados para o portal
    - Método `get_client_service_history()` com histórico de uso
    - Método `get_service_categories()` para categorias disponíveis
  - **Impacto**: Funcionalidades completas de catálogo, pacotes e rastreabilidade
- **Services Add-on**: Documento de análise completa do add-on
  - `docs/analysis/SERVICES_ADDON_ANALYSIS.md` com ~850 linhas de análise
  - Avaliação de funcionalidade, código, segurança, performance e UX
  - Identificação de vulnerabilidades e propostas de correção
  - Roadmap de melhorias futuras (pacotes, histórico de preços, catálogo público)
  - Estimativas de esforço para cada melhoria
  - **Impacto**: Documentação técnica para orientar desenvolvimento futuro
- **Groomers Add-on (v1.2.0)**: Edição, exclusão de groomers e exportação de relatórios
  - Coluna "Ações" na tabela de groomers com botões Editar e Excluir
  - Modal de edição de groomer (nome e email)
  - Confirmação de exclusão com aviso de agendamentos vinculados
  - Botão "Exportar CSV" no relatório de produtividade
  - Exportação inclui: data, horário, cliente, pet, status, valor
  - Linha de totais no final do CSV exportado
  - Handlers seguros com nonces para todas as ações
  - Validação de role antes de excluir groomer
  - Mensagens de feedback via DPS_Message_Helper
  - CSS para modal responsivo com animação
  - **Impacto**: CRUD completo de groomers e exportação de dados
- **Groomers Add-on (v1.1.0)**: Refatoração completa com melhorias de código e layout
  - Nova estrutura de assets: pasta `assets/css/` e `assets/js/`
  - Arquivo CSS externo `groomers-admin.css` com ~400 linhas de estilos minimalistas
  - Arquivo JS externo `groomers-admin.js` com validações e interatividade
  - Cards de métricas visuais no relatório: profissional, atendimentos, receita total, ticket médio
  - Coluna "Pet" adicionada na tabela de resultados do relatório
  - Formatação de data no padrão brasileiro (dd/mm/yyyy)
  - Badges de status com cores semânticas (realizado, pendente, cancelado)
  - Fieldsets no formulário de cadastro: "Dados de Acesso" e "Informações Pessoais"
  - Indicadores de campos obrigatórios (asterisco vermelho)
  - Placeholders descritivos em todos os campos
  - Integração com Finance API para cálculo de receitas (com fallback para SQL direto)
  - Novo método `calculate_total_revenue()` com suporte à Finance API
  - Documento de análise completa: `docs/analysis/GROOMERS_ADDON_ANALYSIS.md`
  - **Impacto**: Interface mais profissional e consistente com o padrão visual DPS
- **GUIA_SISTEMA_DPS.md**: Documento completo de apresentação e configuração do sistema
  - Apresentação geral do sistema e arquitetura modular
  - Instruções detalhadas de instalação do plugin base e add-ons
  - Configuração passo a passo de todos os 15 add-ons
  - Guia de uso do sistema (clientes, pets, agendamentos, financeiro)
  - Recursos avançados (assinaturas, fidelidade, WhatsApp)
  - Seção de resolução de problemas comuns
  - Referência técnica (shortcodes, roles, estrutura de dados)
  - Formatado para publicação web (HTML-ready)
  - Instruções para manter documento atualizado
  - **Localização**: `docs/GUIA_SISTEMA_DPS.md`
- **DPS_WhatsApp_Helper**: Classe helper centralizada para geração de links WhatsApp
  - Método `get_link_to_team()` para cliente contatar equipe (usa número configurado)
  - Método `get_link_to_client()` para equipe contatar cliente (formata número automaticamente)
  - Método `get_share_link()` para compartilhamento genérico (ex: fotos de pets)
  - Método `get_team_phone()` para obter número da equipe (configurável ou padrão)
  - Métodos auxiliares para mensagens padrão (portal, agendamento, cobrança)
  - Constante padrão `TEAM_PHONE = '5515991606299'` (+55 15 99160-6299)
- **Configuração de WhatsApp**: Campo "Número do WhatsApp da Equipe" nas configurações de Comunicações
  - Option `dps_whatsapp_number` para armazenar número da equipe (padrão: +55 15 99160-6299)
  - Número configurável centralmente em Admin → desi.pet by PRObst → Comunicações
  - Suporte a filtro `dps_team_whatsapp_number` para customização programática
- **Plugin Base**: Nova opção "Agendamento Passado" no formulário de agendamentos
  - Adicionada terceira opção de tipo de agendamento para registrar atendimentos já realizados
  - Novo fieldset "Informações de Pagamento" com campos específicos:
    - Status do Pagamento: dropdown com opções "Pago" ou "Pendente"
    - Valor Pendente: campo numérico exibido condicionalmente quando status = "Pendente"
  - Campos salvos como metadados: `past_payment_status` e `past_payment_value`
  - Agendamentos passados recebem automaticamente status "realizado"
  - JavaScript atualizado para controlar visibilidade dos campos condicionais
  - TaxiDog e Tosa ocultados automaticamente para agendamentos passados (não aplicável)
  - **Impacto**: Permite registrar no sistema atendimentos realizados anteriormente e controlar pagamentos pendentes
- **Client Portal Add-on (v2.2.0)**: Menu administrativo e tokens permanentes
  - Adicionado menu "Portal do Cliente" sob "desi.pet by PRObst" com dois submenus:
    - "Portal do Cliente": configurações gerais do portal
    - "Logins de Clientes": gerenciamento de tokens de acesso
  - Implementado suporte a tokens permanentes (válidos até revogação manual)
  - Modal de seleção de tipo de token ao gerar links:
    - "Temporário (30 minutos)": expira automaticamente após 30 minutos
    - "Permanente (até revogar)": válido por 10 anos, revogável manualmente
  - Interface atualizada para exibir tipo de token gerado
  - Tokens permanentes facilitam acesso recorrente sem necessidade de gerar novos links
  - **Impacto**: Administradores agora têm acesso direto ao gerenciamento do portal via menu WP Admin

#### Changed (Mudado)
- **Groomers Add-on**: Removidos estilos inline, substituídos por classes CSS
- **Groomers Add-on**: Layout responsivo com flexbox e grid
- **Groomers Add-on**: Formulário reorganizado com fieldsets semânticos
- **Groomers Add-on**: Tabela de groomers e relatórios com classes CSS customizadas
- **Lista de Clientes**: Atualizada para usar `DPS_WhatsApp_Helper::get_link_to_client()`
- **Add-on de Agenda**: Botões de confirmação e cobrança (individual e conjunta) usam helper centralizado
- **Add-on de Agenda (v1.3.1)**: Centralização de constantes de status
  - Adicionadas constantes `STATUS_PENDING`, `STATUS_FINISHED`, `STATUS_PAID`, `STATUS_CANCELED`
  - Novo método estático `get_status_config()` retorna configuração completa (label, cor, ícone)
  - Novo método estático `get_status_label()` para obter label traduzida de um status
  - Traits refatorados para usar métodos centralizados ao invés de strings hardcoded
  - Documentação de melhorias administrativas em `docs/analysis/AGENDA_ADMIN_IMPROVEMENTS_ANALYSIS.md`
- **Add-on de Assinaturas**: Botão de cobrança de renovação usa helper centralizado
- **Add-on de Finance**: Botão de cobrança em pendências financeiras usa helper centralizado
- **Add-on de Stats**: Link de reengajamento para clientes inativos usa helper centralizado
- **Portal do Cliente**: Todos os botões WhatsApp atualizados:
  - Botão "Quero acesso ao meu portal" usa número configurado da equipe
  - Envio de link do portal via WhatsApp usa helper para formatar número do cliente
  - Botão "Agendar via WhatsApp" (empty state) usa número configurado da equipe
  - Botão "Compartilhar via WhatsApp" (fotos de pets) usa helper para compartilhamento
- **Add-on de AI**: Função JavaScript `openWhatsAppWithMessage` melhorada com comentários
- **Add-on de Comunicações**: Interface reorganizada com seções separadas para WhatsApp, E-mail e Templates
- **Services Add-on**: Melhorias de UX na interface de serviços
  - Mensagens de feedback (sucesso/erro) via `DPS_Message_Helper` em todas as ações
  - Badges de status visual (Ativo/Inativo) na tabela de serviços
  - Tabela de serviços com classes CSS dedicadas para melhor responsividade
  - Wrapper responsivo na tabela com scroll horizontal em mobile
  - Estilos CSS expandidos (~100 linhas adicionadas) para formulário e tabela

#### Fixed (Corrigido)
- **Client Portal Add-on (v2.3.1)**: Corrigido link de token não autenticando cliente imediatamente
  - **Problema**: Quando cliente clicava no link com token (`?dps_token=...`), permanecia na tela de solicitação de login em vez de acessar o portal
  - **Causa raiz**: Cookie de sessão criado com `setcookie()` não estava disponível em `$_COOKIE` na requisição atual, apenas na próxima requisição. O redirecionamento após autenticação causava perda do contexto de autenticação
  - **Solução implementada**:
    - Adicionada propriedade `$current_request_client_id` em `DPS_Client_Portal` para armazenar autenticação da requisição atual
    - Modificado `get_authenticated_client_id()` para priorizar: autenticação atual → cookies → fallback WP user
    - Removido redirecionamento em `handle_token_authentication()` - portal agora carrega imediatamente com cliente autenticado
    - Adicionada função JavaScript `cleanTokenFromURL()` que remove token da URL via `history.replaceState()` por segurança
  - **Impacto**: Links de token agora funcionam imediatamente, sem necessidade de segundo clique ou refresh
  - **Arquivos modificados**:
    - `includes/class-dps-client-portal.php` - lógica de autenticação
    - `assets/js/client-portal.js` - limpeza de URL
- **Finance Add-on (v1.3.1)**: Corrigida página de Documentos Financeiros em branco e vulnerabilidade CSRF
  - **Bug #1 - Página sem shortcode**: Quando página "Documentos Financeiros" já existia com slug `dps-documentos-financeiros`, o método `activate()` apenas atualizava option mas não verificava/atualizava conteúdo da página
    - **Sintoma**: Página aparecia em branco se foi criada manualmente ou teve conteúdo removido
    - **Solução**: Adicionada verificação em `activate()` para garantir que página existente sempre tenha shortcode `[dps_fin_docs]`
    - **Impacto**: Página de documentos sempre funcional mesmo após modificações manuais
  - **Bug #2 - Falta de controle de acesso**: Shortcode `render_fin_docs_shortcode()` não verificava permissões
    - **Sintoma**: Qualquer visitante poderia acessar lista de documentos financeiros sensíveis
    - **Solução**: Adicionada verificação `current_user_can('manage_options')` com filtro `dps_finance_docs_allow_public` para flexibilidade
    - **Impacto**: Documentos agora requerem autenticação e permissão administrativa por padrão
  - **Bug #3 - CSRF em ações de documentos (CRÍTICO)**: Ações `dps_send_doc` e `dps_delete_doc` não verificavam nonce
    - **Vulnerabilidade**: CSRF permitindo atacante forçar usuário autenticado a enviar/deletar documentos
    - **Solução**: Adicionada verificação de nonce em ambas as ações; links atualizados para usar `wp_nonce_url()` com nonces únicos por arquivo
    - **Impacto**: Eliminada vulnerabilidade CSRF crítica; ações de documentos agora protegidas contra ataques
  - **Melhoria de UX**: Listagem de documentos convertida de `<ul>` para tabela estruturada
    - Novas colunas: Documento, Cliente, Data, Valor, Ações
    - Informações extraídas automaticamente da transação vinculada
    - Formatação adequada de datas e valores monetários
    - **Impacto**: Interface mais profissional e informativa; documentos identificáveis sem precisar abri-los
  - **Análise completa**: Documento detalhado criado em `docs/review/finance-addon-analysis-2025-12-06.md` com 10 sugestões de melhorias futuras
- **AI Add-on (v1.6.0)**: Corrigido shortcode `[dps_ai_public_chat]` aparecendo como texto plano
  - **Problema**: Shortcode nunca era registrado, aparecendo como texto plano nas páginas
  - **Causa**: `init_components()` estava registrado no hook `plugins_loaded` (prioridade 21), mas `DPS_AI_Addon` só era inicializado no hook `init` (prioridade 5). Como `plugins_loaded` executa ANTES de `init`, o hook nunca era chamado.
  - **Solução**:
    1. Alterado hook de `init_components()` e `init_portal_integration()` de `plugins_loaded` para `init`
    2. Removido método intermediário `register_shortcode()` e chamado `add_shortcode()` diretamente no construtor
  - **Impacto**: Shortcode agora renderiza corretamente o chat público quando inserido em páginas/posts
- **Compatibilidade WordPress 6.2+**: Substituída função deprecada `get_page_by_title()` por `dps_get_page_by_title_compat()` no Portal do Cliente. A nova função usa `WP_Query` conforme recomendação oficial do WordPress, garantindo compatibilidade com WordPress 6.9+
- **Plugin Base**: Corrigido botões "Selecionar todos" e "Desmarcar todos" na seleção de pets
  - O handler de toggle de pets usava `.data('owner')` que lê do cache interno do jQuery
  - Após PR #165, `buildPetOption` passou a usar `.attr()` para definir atributos DOM
  - O handler de toggle não foi atualizado junto, causando inconsistência
  - **Corrigido**: Alterado handler para usar `.attr('data-owner')` ao invés de `.data('owner')`
  - **Impacto**: Botões de seleção/desmarcar todos os pets agora funcionam corretamente
- **Groomers Add-on**: Corrigido `uninstall.php` para usar meta key correta `_dps_groomers`
  - Problema: arquivo tentava deletar meta keys incorretas (`appointment_groomer_id`, `appointment_groomers`)
  - Meta key correta é `_dps_groomers` (array de IDs de groomers)
  - **Impacto**: Desinstalação do add-on agora remove corretamente os metadados
- **Plugin Base**: Corrigido seletor de pets não exibir pets ao selecionar cliente no formulário de agendamentos
  - A função `buildPetOption` usava `$('<label/>', { 'data-owner': ... })` que armazena dados no cache interno do jQuery
  - A função `applyPetFilters` usava `.attr('data-owner')` para ler, que busca no atributo DOM (sempre vazio)
  - **Corrigido**: Alterado para usar `.attr()` para definir `data-owner` e `data-search`, garantindo consistência
  - **Impacto**: Pets do cliente selecionado agora aparecem corretamente na lista de seleção de pets
- **Plugin Base**: Corrigido aviso PHP `map_meta_cap was called incorrectly` no WordPress 6.1+
  - Adicionadas capabilities de exclusão faltantes (`delete_posts`, `delete_private_posts`, `delete_published_posts`, `delete_others_posts`) nos CPTs:
    - `dps_cliente` (Clientes)
    - `dps_pet` (Pets)
    - `dps_agendamento` (Agendamentos)
  - **Corrigido**: Notices repetidos no error log sobre `delete_post` capability sem post específico
  - **Impacto**: Elimina avisos no log ao excluir ou gerenciar posts dos CPTs personalizados
- **Plugin Base**: Corrigido aviso PHP `Undefined variable $initial_pending_rows`
  - Inicializada variável como array vazio antes de uso condicional
  - **Corrigido**: Notice na linha 1261 de class-dps-base-frontend.php
  - **Impacto**: Elimina aviso no error log ao carregar formulário de agendamentos
- **Stock Add-on**: Adicionadas capabilities de exclusão faltantes (`delete_private_posts`, `delete_published_posts`)
  - Complementa capabilities já existentes para total compatibilidade com `map_meta_cap`
- Número da equipe agora é configurável e centralizado (antes estava hardcoded em vários locais)
- Formatação de números de telefone padronizada em todo o sistema usando `DPS_Phone_Helper`
- Portal do Cliente agora usa número da equipe configurado ao invés de placeholder `5551999999999`
- Todos os links WhatsApp agora formatam números de clientes corretamente (adicionam código do país automaticamente)
- **AI Add-on & Client Portal Add-on**: Corrigido assistente virtual no Portal do Cliente
  - Adicionado método público `get_current_client_id()` na classe `DPS_Client_Portal` para permitir acesso externo ao ID do cliente autenticado
  - Criado novo hook `dps_client_portal_before_content` que dispara após a navegação e antes das seções de conteúdo
  - Movido widget do assistente virtual de `dps_client_portal_after_content` para `dps_client_portal_before_content`
  - **Corrigido**: Erro "Você precisa estar logado para usar o assistente" ao acessar portal via link de acesso
  - **Corrigido**: Posicionamento do assistente agora é no topo da página (após navegação), conforme especificação
  - **Impacto**: Assistente virtual agora funciona corretamente quando cliente acessa via token/link permanente
- **Services Add-on & Loyalty Add-on (WordPress 6.7+)**: Corrigido carregamento de traduções antes do hook 'init'
  - Movido carregamento de text domain para hook 'init' com prioridade 1 (anteriormente prioridade padrão 10)
  - Movida instanciação de classes para hook 'init' com prioridade 5:
    - Services Add-on: de escopo global para `init` priority 5
    - Loyalty Add-on: de hook `plugins_loaded` para `init` priority 5
  - Ordem de execução garantida: (1) text domain carrega em init:1, (2) classe instancia em init:5, (3) CPT registra em init:10
  - **Corrigido**: PHP Notice "Translation loading for the domain was triggered too early" no WordPress 6.7.0+
  - **Documentado**: Padrão de carregamento de text domains no ANALYSIS.md seção "Text Domains para Internacionalização"
- **Loyalty Add-on**: Corrigido erro de capability check ao atribuir pontos
  - Adicionada verificação se o post existe antes de chamar `get_post_type()`
  - **Corrigido**: Notice "map_meta_cap was called incorrectly" ao verificar capability `delete_post`
  - Previne erro quando WordPress verifica capabilities internamente durante mudança de status de agendamento
- **Plugin Base**: Corrigido acesso ao painel de gestão para usuários com role `dps_reception`
  - Função `can_manage()` agora aceita `manage_options` OU qualquer capability DPS específica (`dps_manage_clients`, `dps_manage_pets`, `dps_manage_appointments`)
  - Removida verificação duplicada de `manage_options` no método `handle_request()` que bloqueava usuários sem permissão de administrador
  - Usuários com capabilities DPS específicas agora podem acessar o painel e executar ações permitidas
  - **Corrigido**: Pets vinculados ao cliente não apareciam ao selecionar cliente (causado pelo bloqueio de acesso ao painel)
  - **Corrigido**: Erro "Acesso negado" ao alterar status de agendamento (causado pela verificação duplicada de permissões)
  - Atualizada mensagem de erro de login para refletir que não apenas administradores podem acessar
  - Adicionada documentação explicando modelo de permissões: painel visível para qualquer capability DPS, mas ações protegidas individualmente
- **Menus Administrativos**: Corrigido registro de menus em add-ons
  - Backup Add-on: submenu agora aparece corretamente sob "desi.pet by PRObst" (corrigida ordem de carregamento)
  - Loyalty Add-on: menus agora aparecem sob "desi.pet by PRObst" em vez de criar menu próprio separado
  - Logs do Sistema: migrado de menu separado para submenu sob "desi.pet by PRObst" (melhor organização)
  - Mensagens do Portal: migrado de menu separado para submenu sob "desi.pet by PRObst" (CPT com show_in_menu)
  - Cadastro Público renomeado para "Formulário de Cadastro" (nome mais intuitivo)
  - Todos os add-ons com menus agora usam prioridade 20 no hook `admin_menu` para garantir que o menu pai já existe
  - Estrutura de menus documentada em `ANALYSIS.md` na seção "Estrutura de Menus Administrativos"
  - Adicionadas diretrizes de nomenclatura para melhorar usabilidade (nomes descritivos, sem prefixos redundantes)
  - **Impacto**: Todos os menus e submenus agora estão agrupados no mesmo menu principal "desi.pet by PRObst" para facilitar gerenciamento
- **Formulário de Agendamentos**: Melhorias de responsividade para telas pequenas
  - Corrigido overflow horizontal em mobile e tablet (adicionado `overflow-x: hidden` em `.dps-form`)
  - Ajustado tamanho de inputs e selects para mobile (`padding: 8px` em ≤768px, `10px 8px` em ≤480px)
  - Incluídos todos os tipos de input (date, time, number) nas regras de font-size mobile (16px para evitar zoom iOS)
  - Adicionado wrapper `.dps-form-field` com margin-bottom consistente (12px)
  - Reduzido padding de fieldsets em mobile pequeno (12px em ≤480px)
  - Ajustado card de resumo para telas pequenas:
    - Labels strong: `min-width: 100px` (era 140px) em ≤480px
    - Font-size reduzido para 13px (itens) e 16px (título H3)
  - Reduzido tamanho da legend em telas muito pequenas (15px em ≤480px)
- **Finance Add-on**: Corrigido fatal error ao renderizar mensagens de feedback
  - **Problema**: Chamada a método inexistente `DPS_Message_Helper::render()` causava fatal error na linha 1725
  - **Causa**: Finance add-on tentava usar método `render()` que não existe na classe `DPS_Message_Helper`
  - **Solução**: Substituída chamada por renderização inline usando a mesma estrutura HTML do método `display_messages()`
  - **Impacto**: Mensagens de feedback (sucesso/erro) agora são exibidas corretamente na seção financeira sem causar erros

#### Security (Segurança)
- **Finance Add-on (v1.3.1)**: Corrigida vulnerabilidade CSRF crítica em ações de documentos
  - **Vulnerabilidade**: Ações `dps_send_doc` e `dps_delete_doc` não verificavam nonce, permitindo CSRF
  - **Impacto potencial**: Atacante poderia forçar administrador autenticado a:
    - Enviar documentos financeiros sensíveis para emails maliciosos
    - Deletar documentos importantes sem autorização
    - Executar ações não autorizadas em documentos
  - **Solução**: Adicionada verificação de nonce única por arquivo em ambas as ações
  - **Proteção adicional**: Controle de acesso via `current_user_can('manage_options')` no shortcode
  - **Severidade**: CRÍTICA - eliminada completamente com as correções implementadas
- **Services Add-on**: Corrigidas vulnerabilidades CSRF críticas
  - Adicionada verificação de nonce em exclusão de serviço (`dps_delete_service_{id}`)
  - Adicionada verificação de nonce em toggle de status (`dps_toggle_service_{id}`)
  - Adicionada verificação de post_type antes de excluir/modificar
  - URLs de ação agora usam `wp_nonce_url()` para proteção automática
  - **Impacto**: Elimina possibilidade de exclusão/alteração de serviços via links maliciosos
- Todas as URLs de WhatsApp usam `esc_url()` para escape adequado
- Mensagens de WhatsApp usam `rawurlencode()` para encoding seguro de caracteres especiais
- Números de telefone são sanitizados via `sanitize_text_field()` antes de salvar configuração
- Helper `DPS_WhatsApp_Helper` implementa validação de entrada para prevenir links malformados

#### Documentation (Documentação)
- **ANALYSIS.md**: Atualizada seção "Portal do Cliente" com novos hooks, funções helper e versão 2.1.0
- **Client Portal README.md**: Atualizada seção "Para administradores" com instruções de configuração da página do portal

#### Added (Adicionado)
- **Client Portal Add-on (v2.1.0)**: Interface de configurações para gerenciamento do Portal do Cliente
  - Nova aba "Portal" nas configurações do sistema para configurar página do portal
  - Campo de seleção (dropdown) para escolher a página onde o shortcode `[dps_client_portal]` está inserido
  - Exibição do link do portal com botão "Copiar Link" para facilitar compartilhamento
  - Instruções de uso do portal com passos detalhados
  - Salvamento de configurações via option `dps_portal_page_id` com validação de nonce
  - Funções helper globais `dps_get_portal_page_url()` e `dps_get_portal_page_id()` para obter URL/ID do portal
  - Fallback automático para página com título "Portal do Cliente" (compatibilidade com versões anteriores)
  - Template `templates/portal-settings.php` com estilos minimalistas DPS
  - Script inline para copiar URL do portal com feedback visual
- **Payment Add-on**: Documentação completa de configuração do webhook secret
  - Novo arquivo `WEBHOOK_CONFIGURATION.md` com guia passo a passo completo
  - Instruções detalhadas sobre geração de senha forte, configuração no DPS e no Mercado Pago
  - Exemplos de URLs de webhook com os 4 métodos suportados (query parameter, headers)
  - Seção de troubleshooting com erros comuns e soluções
  - Seção de validação e testes com exemplos de logs
  - FAQ com perguntas frequentes sobre segurança e configuração
- **Internacionalização (i18n)**: Documentação de text domains oficiais em ANALYSIS.md para facilitar tradução
- **Client Portal Add-on (v2.0.0)**: Sistema completo de autenticação por token (magic links)
  - **BREAKING CHANGE**: Substituído sistema de login com senha por autenticação via links com token
  - Nova tabela `wp_dps_portal_tokens` para gerenciar tokens de acesso
  - Classe `DPS_Portal_Token_Manager` para geração, validação e revogação de tokens
  - Classe `DPS_Portal_Session_Manager` para gerenciar sessões independentes do WordPress
  - Classe `DPS_Portal_Admin_Actions` para processar ações administrativas
  - Tokens seguros de 64 caracteres com hash (password_hash/password_verify)
  - Expiração configurável (padrão 30 minutos)
  - Marcação de uso (single use)
  - Cleanup automático via cron job (tokens > 30 dias)
  - Tela de acesso pública minimalista (`templates/portal-access.php`)
  - Interface administrativa completa de gerenciamento (`templates/admin-logins.php`)
  - Tabela responsiva de clientes com status de acesso e último login
  - Botões "Primeiro Acesso" e "Gerar Novo Link"
  - Botão "Revogar" para invalidar tokens ativos
  - Exibição temporária de links gerados (5 minutos)
  - Integração com WhatsApp: abre WhatsApp Web com mensagem pronta
  - Integração com E-mail: modal de pré-visualização obrigatória antes de enviar
  - JavaScript para copiar links, modais e AJAX (`assets/js/portal-admin.js`)
  - Busca de clientes por nome ou telefone
  - Feedback visual para todas as ações
  - Compatibilidade com sistema antigo mantida (fallback)
  - Documentação em `templates/portal-access.php` e `templates/admin-logins.php`
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
  - Documentação completa em `plugins/desi-pet-shower-ai/AI_COMMUNICATIONS.md`
  - Exemplos de integração em `plugins/desi-pet-shower-ai/includes/ai-communications-examples.php`
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
  - Documentação completa em `plugins/desi-pet-shower-ai/README.md`
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
- **Client Portal Add-on**: Refatoração de 7 ocorrências de `get_page_by_title('Portal do Cliente')` hardcoded
  - Substituído por chamadas às funções helper centralizadas `dps_get_portal_page_url()` e `dps_get_portal_page_id()`
  - Modificados: `class-dps-client-portal.php` (4x), `class-dps-portal-session-manager.php` (2x), `class-dps-portal-token-manager.php` (1x)
  - Mantido comportamento legado como fallback dentro das funções helper
- **Payment Add-on**: Campo "Webhook secret" nas configurações melhorado com instruções inline
  - Descrição expandida com passos numerados de configuração
  - Exemplo de URL do webhook com domínio real do site
  - Link para guia completo de configuração (abre em nova aba)
  - Destaque visual para facilitar compreensão da configuração obrigatória
- **Payment Add-on README.md**: Seção de configuração atualizada com destaque para webhook secret
  - Aviso destacado sobre obrigatoriedade do webhook secret no topo do documento
  - Link proeminente para guia de configuração em múltiplas seções
  - Fluxo automático atualizado com passo de validação do webhook secret
- **ANALYSIS.md**: Documentação do Payment Add-on atualizada
  - Option `dps_mercadopago_webhook_secret` adicionada à lista de opções armazenadas
  - Referência ao guia de configuração completo em observações do add-on
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

#### Changed (Alterado)
- **Client Portal Add-on (v2.0.0)**: Método de autenticação completamente substituído
  - Sistema antigo de login com usuário/senha do WordPress REMOVIDO
  - Novo sistema baseado 100% em tokens (magic links)
  - Shortcode `[dps_client_login]` agora exibe apenas a tela de acesso minimalista
  - Método `render_client_logins_page()` completamente reescrito (de ~400 para ~100 linhas)
  - Interface administrativa totalmente nova baseada em templates
  - Compatibilidade retroativa mantida via fallback no método `get_authenticated_client_id()`
  - **IMPORTANTE**: Clientes existentes precisarão solicitar novo link de acesso na primeira vez após a atualização

#### Security (Segurança)
- **Plugin Base**: Adicionada proteção CSRF no logout do painel DPS
  - Novo método `DPS_Base_Frontend::handle_logout()` agora requer nonce válido (`_wpnonce`)
  - Proteção contra logout forçado via links maliciosos (CSRF)
  - Sanitização adequada de parâmetros GET
  - **IMPORTANTE**: Links de logout devem incluir `wp_nonce_url()` com action `dps_logout`
- **Client Portal Add-on (v2.0.0)**: Melhorias de segurança no sistema de sessões e e-mails
  - Configuração de flags de segurança em cookies de sessão (httponly, secure, samesite=Strict)
  - Modo estrito de sessão habilitado (use_strict_mode)
  - Regeneração sistemática de session_id em autenticação (proteção contra session fixation)
  - E-mails enviados apenas em formato plain text (proteção contra social engineering)
  - Sanitização com `sanitize_textarea_field()` em vez de `wp_kses_post()` para e-mails

#### Fixed (Corrigido)
- **Internacionalização (i18n)**: Corrigidas strings hardcoded não traduzíveis
  - **Plugin Base**: 6 strings envolvidas em funções de tradução
    - Mensagens WhatsApp de cobrança (individual e conjunta) agora usam `__()` com 'desi-pet-shower'
    - Mensagem de depreciação do shortcode `[dps_configuracoes]` agora usa `__()`
    - Placeholder "Digite ou selecione" no campo de raça agora usa `esc_attr__()`
    - Mensagem de sucesso de envio de histórico agora usa `esc_html__()`
    - Prompt de email JavaScript agora usa `esc_js( __() )`
  - **Finance Add-on**: 2 mensagens WhatsApp de cobrança agora usam `__()` com 'dps-finance-addon'
- **Internacionalização (i18n)**: Corrigidos text domains incorretos em 4 add-ons
  - **Communications Add-on**: Todas strings (20 ocorrências) atualizadas de 'desi-pet-shower' para 'dps-communications-addon'
  - **Stock Add-on**: Todas strings (15 ocorrências) atualizadas de 'desi-pet-shower' para 'dps-stock-addon'
  - **Groomers Add-on**: Todas strings (12 ocorrências) atualizadas de 'desi-pet-shower' para 'dps-groomers-addon'
  - **Loyalty Add-on**: Todas strings (8 ocorrências) atualizadas de 'desi-pet-shower' para 'dps-loyalty-addon'
  - Headers dos plugins também atualizados para refletir text domains corretos
- **Agenda Add-on**: Corrigido aviso incorreto de dependência do Finance Add-on no painel administrativo
  - **Problema**: Mensagem "O Finance Add-on é recomendado para funcionalidade completa de cobranças" aparecia mesmo com Finance ativo
  - **Causa raiz**: Verificação `class_exists('DPS_Finance_API')` no construtor executava antes do Finance carregar (ordem alfabética de plugins)
  - **Solução**: Movida verificação do construtor para hook `plugins_loaded` (novo método `check_finance_dependency()`)
  - **Impacto**: Aviso agora aparece apenas quando Finance realmente não está ativo
  - **Arquivo alterado**: `plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php`
- **Plugin Base**: Corrigido erro "Falha ao atualizar. A resposta não é um JSON válido" ao inserir shortcode `[dps_base]` no Block Editor
  - **Causa raiz**: Método `render_app()` processava logout e POST requests ANTES de iniciar output buffering (`ob_start()`)
  - **Sintoma**: Block Editor falhava ao validar shortcode porque redirects/exits causavam conflito com resposta JSON esperada
  - **Solução**: Movido processamento de logout para hook `init` (novo método `DPS_Base_Frontend::handle_logout()`)
  - **Solução**: Removida chamada redundante a `handle_request()` dentro de `render_app()` (já processado via `init`)
  - **Impacto**: Shortcode `[dps_base]` agora é método puro de renderização sem side-effects, compatível com Block Editor
  - **Arquivos alterados**:
    - `plugins/desi-pet-shower-base/desi-pet-shower-base.php` (adicionado logout ao `maybe_handle_request()`)
    - `plugins/desi-pet-shower-base/includes/class-dps-base-frontend.php` (novo método `handle_logout()`, `render_app()` simplificado)
  - **Verificação**: Todos os outros shortcodes (`[dps_agenda_page]`, `[dps_client_portal]`, `[dps_registration_form]`, etc.) já seguem o padrão correto
- **Client Portal Add-on**: Corrigido problema de layout onde o card "Portal do Cliente" aparecia antes do cabeçalho do tema
  - **Causa raiz**: Método `render_portal_shortcode()` estava chamando `ob_end_clean()` seguido de `include`, causando output direto em vez de retornar HTML via shortcode
  - **Sintoma**: Card do portal aparecia ANTES do menu principal do tema YOOtheme, como se estivesse "encaixado no header"
  - **Solução**: Substituído `ob_end_clean() + include + return ''` por `ob_start() + include + return ob_get_clean()`
  - **Impacto**: Portal agora renderiza corretamente DENTRO da área de conteúdo da página, respeitando header/footer do tema
  - **Arquivos alterados**: `plugins/desi-pet-shower-client-portal/includes/class-dps-client-portal.php` (linhas 710-723)
- **Groomers Add-on**: Corrigido fatal error ao renderizar seção no front-end via shortcode [dps_base]
  - Problema: função `add_settings_error()` só existe no contexto admin (wp-admin)
  - Solução: adicionada verificação `function_exists('add_settings_error')` antes de todas as chamadas
  - Impacto: aba "Groomers" agora funciona corretamente no Painel de Gestão DPS sem fatal errors
  - Mensagens no front-end exibidas via `DPS_Message_Helper`, mantendo compatibilidade com admin
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
- **Client Portal Add-on (v2.0.0)**: Sistema de login com usuário/senha descontinuado
  - Shortcode `[dps_client_login]` ainda existe mas comportamento mudou (não exibe mais formulário de login)
  - Método `maybe_create_login_for_client()` ainda é executado mas não tem mais utilidade prática
  - Método `get_client_id_for_current_user()` ainda funciona como fallback mas será removido em v3.0.0
  - Métodos relacionados a senha serão removidos em versão futura: `render_login_shortcode()` (parcialmente mantido), ações de reset/send password
- **Agenda Add-on**: Método `get_services_details_ajax()` - Lógica movida para Services Add-on (delega para `DPS_Services_API::get_services_details()`, mantém compatibilidade com fallback)
- **Agenda Add-on**: Endpoint AJAX `dps_get_services_details` agora é gerenciado pelo Services Add-on (Agenda mantém por compatibilidade)
- **Finance Add-on**: `dps_parse_money_br()` - Use `DPS_Money_Helper::parse_brazilian_format()` (retrocompatível, aviso de depreciação)
- **Finance Add-on**: `dps_format_money_br()` - Use `DPS_Money_Helper::format_to_brazilian()` (retrocompatível, aviso de depreciação)
- **Loyalty Add-on**: `dps_format_money_br()` - Use `DPS_Money_Helper::format_to_brazilian()` (retrocompatível, aviso de depreciação)
- **Agenda Add-on**: Shortcode `[dps_charges_notes]` - Use `[dps_fin_docs]` do Finance (redirect automático, mensagem de depreciação)

#### Refactoring (Interno)
- **Plugin Base + Agenda Add-on**: Centralização completa da formatação de WhatsApp em `DPS_Phone_Helper::format_for_whatsapp()`
  - Removido método privado `format_whatsapp_number()` de `DPS_Base_Frontend` (13 linhas duplicadas)
  - Removido método wrapper deprecado `format_whatsapp_number()` de `DPS_Agenda_Addon` (19 linhas)
  - Total de 32 linhas de código duplicado eliminadas
  - Todas as chamadas agora usam diretamente `DPS_Phone_Helper::format_for_whatsapp()`
  - **Benefícios**: eliminação de duplicação, manutenção simplificada, consistência entre add-ons
  - **Arquivos modificados**: `class-dps-base-frontend.php`, `desi-pet-shower-agenda-addon.php`
- **Services Add-on**: Removido header duplicado de plugin no arquivo `dps_service/desi-pet-shower-services-addon.php` (mantém apenas no wrapper)
- **Subscription Add-on**: Removido header duplicado de plugin no arquivo `dps_subscription/desi-pet-shower-subscription-addon.php` (mantém apenas no wrapper)
- **Services Add-on**: Centralização completa de lógica de serviços e cálculo de preços via `DPS_Services_API` (redução de duplicação, separação de responsabilidades)
- **Arquitetura**: Centralização completa de lógica financeira no Finance Add-on (eliminação de duplicação, redução de acoplamento)
- **Agenda Add-on**: Removidas ~55 linhas de SQL direto para `dps_transacoes` (agora usa sincronização automática via hooks do Finance)
- **Funções monetárias**: Todas as chamadas legadas `dps_format_money_br()` e `dps_parse_money_br()` substituídas por `DPS_Money_Helper`
  - Finance Add-on: 11 substituições (4x parse, 7x format)
  - Loyalty Add-on: 2 substituições (format)
  - Services Add-on: 1 substituição (parse com class_exists)
  - Client Portal Add-on: 1 substituição (format com class_exists)
  - Refactoring Examples: 1 substituição (parse)
  - Funções legadas mantidas como wrappers deprecados para compatibilidade retroativa
  - Garantia de que `DPS_Money_Helper` é sempre usado internamente, eliminando duplicação de lógica
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
- **Groomers Add-on**: Corrigido erro fatal "Call to undefined function settings_errors()" no front-end ao usar shortcode [dps_base]
  - **Problema**: `settings_errors()` é função exclusiva do WordPress admin, não disponível no front-end
  - **Impacto**: Fatal error na seção Groomers do Painel de Gestão DPS (shortcode)
  - **Solução**: Implementada separação de contexto:
    - Método `handle_new_groomer_submission()` agora aceita parâmetro `$use_frontend_messages`
    - Front-end (`render_groomers_section`): usa `DPS_Message_Helper::add_error/add_success()` e `display_messages()`
    - Admin (`render_groomers_page`): usa `add_settings_error()` e `settings_errors()` com guard `function_exists()`
  - O shortcode [dps_base] agora funciona normalmente no front-end sem fatal errors
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
