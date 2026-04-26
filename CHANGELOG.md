# desi.pet by PRObst â€” CHANGELOG

**Autor:** PRObst
**Site:** [www.probst.pro](https://www.probst.pro)

Este documento registra, em ordem cronolÃ³gica inversa, todas as alteraÃ§Ãµes lanÃ§adas do desi.pet by PRObst. Mantenha-o sempre atualizado para que equipe, parceiros e clientes tenham clareza sobre evoluÃ§Ãµes, correÃ§Ãµes e impactos.

## RelaÃ§Ã£o com outros documentos

Este CHANGELOG complementa e se relaciona com:
- **ANALYSIS.md**: contÃ©m detalhes arquiteturais, fluxos internos de integraÃ§Ã£o e contratos de hooks entre nÃºcleo e add-ons. Consulte-o para entender *como* o sistema funciona internamente.
- **AGENTS.md**: define polÃ­ticas de versionamento, git-flow, convenÃ§Ãµes de cÃ³digo e obrigaÃ§Ãµes de documentaÃ§Ã£o. Consulte-o para entender *como* contribuir e manter o cÃ³digo.

Este CHANGELOG registra *o que* mudou, em qual versÃ£o e com qual impacto visÃ­vel para usuÃ¡rios e integradores.

## Como atualizar este changelog
1. **Abra uma nova seÃ§Ã£o** para cada versÃ£o liberada, usando o formato `AAAA-MM-DD` para a data real do lanÃ§amento.
2. **Agrupe entradas por categoria**, mesmo que alguma fique vazia (remova a categoria vazia apenas se nÃ£o houver conteÃºdo relevante).
3. **Use linguagem imperativa e concisa**, indicando impacto visÃ­vel para usuÃ¡rios e integradores.
4. **Referencie tickets ou links**, quando Ãºtil, no final de cada item.
5. **NÃ£o liste alteraÃ§Ãµes internas triviais** (refactors menores ou ajustes de estilo) a menos que afetem integraÃ§Ãµes ou documentaÃ§Ã£o.

### Fluxo de release

Antes de criar uma nova versÃ£o oficial:

1. **Mover entradas de `[Unreleased]` para nova seÃ§Ã£o datada**: crie uma seÃ§Ã£o `### [AAAA-MM-DD] vX.Y.Z` e transfira todas as entradas acumuladas de `[Unreleased]` para ela.
2. **Deixar `[Unreleased]` pronto para a prÃ³xima rodada**: mantenha a seÃ§Ã£o `[Unreleased]` com categorias vazias prontas para receber novas mudanÃ§as.
3. **Conferir coerÃªncia com ANALYSIS.md e AGENTS.md**:
   - Se houve mudanÃ§as de arquitetura, criaÃ§Ã£o de helpers, novos hooks ou alteraÃ§Ãµes de fluxo financeiro, valide que o `ANALYSIS.md` reflete essas mudanÃ§as.
   - Se houve mudanÃ§as em polÃ­ticas de versionamento, convenÃ§Ãµes de cÃ³digo ou estrutura de add-ons, valide que o `AGENTS.md` estÃ¡ atualizado.
4. **Criar tag de release**: apÃ³s garantir que todos os arquivos estÃ£o consistentes, crie a tag anotada `git tag -a vX.Y.Z -m "DescriÃ§Ã£o da versÃ£o"` e publique.

## Estrutura recomendada
- Todas as versÃµes listadas do mais recente para o mais antigo.
- Cada versÃ£o organizada por data de publicaÃ§Ã£o.
- Categorias oficiais (utilize-as neste exato tÃ­tulo e ordem quando possÃ­vel):
  - Added (Adicionado)
  - Changed (Alterado)
  - Fixed (Corrigido)
  - Removed (Removido)
  - Deprecated (Depreciado)
  - Security (SeguranÃ§a)
  - Refactoring (Interno) â€” *opcional, apenas para grandes refatoraÃ§Ãµes que impactam arquitetura ou helpers globais*

## Exemplos e placeholders

### [YYYY-MM-DD] vX.Y.Z â€” Nome da versÃ£o (opcional)

#### Added (Adicionado)
- Adicione aqui novas funcionalidades, endpoints, pÃ¡ginas do painel ou comandos WP-CLI.
- Exemplo: "Implementada aba de assinaturas com integraÃ§Ã£o ao gateway XPTO." (TCK-123)

#### Changed (Alterado)
- Registre alteraÃ§Ãµes de comportamento, migraÃ§Ãµes de dados ou ajustes de UX.
- Exemplo: "Reordenada navegaÃ§Ã£o das abas para destacar Agendamentos." (TCK-124)

#### Fixed (Corrigido)
- Liste correÃ§Ãµes de bugs, incluindo contexto e impacto.
- Exemplo: "Corrigido cÃ¡lculo de taxas na tabela `dps_transacoes` em assinaturas recorrentes." (TCK-125)

#### Removed (Removido)
- Documente remoÃ§Ãµes de APIs, *hooks* ou configuraÃ§Ãµes.
- Exemplo: "Removido shortcode legado `dps_old_checkout` em favor do `dps_checkout`."

#### Deprecated (Depreciado)
- Marque funcionalidades em descontinuaÃ§Ã£o e a versÃ£o alvo de remoÃ§Ã£o.
- Exemplo: "Depreciada opÃ§Ã£o `dps_enable_legacy_assets`; remoÃ§Ã£o prevista para vX.Y." (TCK-126)

#### Security (SeguranÃ§a)
- Registre correÃ§Ãµes de seguranÃ§a, incluindo CVE/avisos internos.
- Exemplo: "SanitizaÃ§Ã£o reforÃ§ada nos parÃ¢metros de webhook `dps_webhook_token`." (TCK-127)

#### Refactoring (Interno)
- Liste apenas grandes refatoraÃ§Ãµes que impactam arquitetura, estrutura de add-ons ou criaÃ§Ã£o de helpers globais.
- RefatoraÃ§Ãµes triviais (renomeaÃ§Ã£o de variÃ¡veis, quebra de funÃ§Ãµes pequenas) devem ficar fora do changelog.
- Exemplo: "Criadas classes helper `DPS_Money_Helper`, `DPS_URL_Builder`, `DPS_Query_Helper` e `DPS_Request_Validator` para padronizar operaÃ§Ãµes comuns." (TCK-128)
- Exemplo: "Documentado padrÃ£o de estrutura de arquivos para add-ons em `ANALYSIS.md` com exemplos prÃ¡ticos em `refactoring-examples.php`." (TCK-129)

---

### [Unreleased]

#### Added (Adicionado)

**Agenda Add-on - fila operacional DPS Signature**

- Implementada a primeira versão da fila operacional canônica da Agenda, substituindo a leitura em três tabelas por um eixo único com horário, pet/tutor, serviços, etapa, financeiro, operação, logística e ações.
- Adicionados cards operacionais mobile, painel contextual do atendimento selecionado, busca local, filtros rápidos, ação primária por etapa e agrupamento de ações secundárias.
- Mantidos os modais funcionais existentes para serviços, operação/checklist/check-in/check-out, histórico, pagamento e reagendamento, agora acionados a partir da nova estrutura.

#### Changed (Alterado)

- Refinado o layout publicado do Agendamento no padrão DPS Signature, com painel de atribuição em paleta canônica, CTA final reto, chips de preço mais sóbrios e melhor aproveitamento de tela grande.
- A página de Agendamento passou a suprimir scripts externos de marketing/telemetria não necessários ao fluxo operacional, removendo ruídos de CORS vindos de Ads/Site Kit/Elementor no runtime publicado sem alterar o formulário DPS.
- Refeito o Booking Add-on como superfície de agendamento DPS Signature baseada no renderer canônico do núcleo, preservando `[dps_booking_form]`, `[dps_booking_v2]`, nonces, campos do POST e hooks de Services/Groomers.
- Atualizada a página de Agendamento para usar confirmação pós-save por query assinada com nonce, sem transients, e URL canônica reconciliada com `/agendamento/`.
- Reafirmados `desi-pet-shower-registration` e `desi-pet-shower-booking` como implementações canônicas dos fluxos públicos de cadastro e agendamento, encerrando o dual-run do add-on `desi-pet-shower-frontend`.
- Preservados `[dps_registration_v2]` e `[dps_booking_v2]` como aliases de compatibilidade nos plugins canônicos, evitando quebra de páginas publicadas após a remoção do add-on Frontend.
- Consolidada a Agenda publicada como superfície operacional única do DPS Signature, sem navegação funcional por abas legadas e com navegação preservando apenas visão e período.
- Padronizado o modal de serviços no mesmo shell visual e comportamental dos demais diálogos da Agenda.
- Refinado o mobile da fila operacional com stage badge dedicada no card, toolbar compacta e estados sincronizados entre linha desktop, card mobile e inspetor contextual.
- Refinado o estado vazio dos filtros operacionais da Agenda com assinatura visual DPS Signature, marca tipográfica própria e microcopy contextual para recortes sem atrasos ou sem logística TaxiDog.
- Reordenada a hierarquia mobile da Agenda em codigo para priorizar a fila operacional sobre os KPIs no shell DPS Signature.
- Normalizados rotulos textuais da Agenda operacional e de views legadas para remover indicadores antigos e copy quebrada.
- Simplificado o card operacional da Agenda para focar em horário, status, pet, tutor e ações principais, mantendo os detalhes completos no inspetor lateral DPS Signature.

#### Fixed (Corrigido)

- Corrigida a supressão de scripts externos do Agendamento para não desregistrar dependências do Elementor, eliminando notices técnicos `WP_Scripts::add` impressos no runtime publicado.
- Removido uso deprecated de `$.trim` no JS ativo do plugin base, eliminando o warning global do jQuery Migrate no Agendamento.
- Corrigido o fluxo do Booking que usava `get_transient/set_transient/delete_transient` para confirmação de agendamento.
- Removidos armazenamentos temporários em memória no caminho de agendamento público/base, mantendo pendências financeiras e cobranças multi-pet recalculadas em tempo real por requisição.
- Corrigida a cobertura de no-cache para `[dps_booking_form]` e `[dps_booking_v2]` no `DPS_Cache_Control`.
- Corrigida a pré-seleção/edição de pet no renderer de agendamento com fallback read-only de `appointment_pet` para o contrato canônico `appointment_pet_id`.
- Corrigidos arquivos PHP do pacote DPS com BOM no ambiente publicado, eliminando contaminação de respostas JSON do AJAX e estabilizando modais, painéis e integrações que dependem de payload limpo.
- Corrigida a telemetria de histórico para devolver `source` e `source_label`, permitindo badges coerentes para registros automáticos e ações manuais no modal de linha do tempo.
- Validado no `desi.pet` que os modais de pet, serviços, operação, histórico e reagendamento abriram corretamente após a publicação final.
- Removidos efeitos colaterais de persistencia no renderer operacional da Agenda, que antes ajustava metadados durante a montagem da UI.
- Removido uso deprecated de `$.trim` nos scripts ativos da Agenda; a revalidacao publicada ficou sem erros e sem warnings no Playwright.

#### Removed (Removido)

- Removido o add-on `desi-pet-shower-frontend`, incluindo shortcodes V2, feature flags, telemetria própria, aba administrativa, assets, templates e documentação operacional de rollout/migração, preservando os contratos canônicos dos plugins separados.
- Removidos definitivamente da Agenda operacional os cards de resumo `Total`, `Pendentes`, `Finalizados`, `Cancelados`, `Atrasados`, `Pagamento pendente` e `TaxiDog`, mantendo a tela focada diretamente na fila operacional.
- Removida da Agenda a iconografia genérica de calendário no pseudo-elemento de estado vazio, substituída por assinatura tipográfica DPS.
- Removidos do card operacional os blocos redundantes `Serviços`, `Financeiro`, `Logística` e `Checklist`, além do botão `Operação` no rodapé e no menu secundário.

#### Refactoring (Interno)

- Reduzida a duplicação do Booking Add-on ao remover o fork do formulário de agendamento e parametrizar `DPS_Appointments_Section_Renderer` para contextos dedicados.
- Adicionado o filtro `dps_base_appointment_redirect_url` para ajustes de redirect pós-save por add-ons sem alterar o contrato do handler de agendamentos.
- Removidos resíduos de CSS/JS/layout das antigas tabelas concorrentes da Agenda, mantendo o runtime ativo concentrado na fila operacional canônica do DPS Signature.
- Simplificada a semântica do frontend para tratar a Agenda como modo operacional único, reduzindo dependências de `agenda_tab` no runtime publicado.
- Consolidado o fluxo de servicos da Agenda no `agenda-addon.js`, removendo o arquivo legado `services-modal.js` e a dependencia de `agenda_tab` no frontend operacional.

**Client Portal ? Login h?brido e acesso recorrente**

- **Login por e-mail e senha no Portal do Cliente**: mantido o acesso por magic link e adicionado fluxo recorrente com usu?rio WordPress vinculado ao e-mail cadastrado no cliente.
- **Cria??o/redefini??o de senha por e-mail**: nova jornada para o cliente receber um link de configura??o de senha sem sair da tela inicial do portal.
- **Provisionamento e sincroniza??o de usu?rio do portal**: novo gerenciador `DPS_Portal_User_Manager` para vincular o cadastro do cliente ao usu?rio WordPress correto.
- **Rate limiting para solicita??es de acesso**: novo gerenciador `DPS_Portal_Rate_Limiter` aplicado aos pedidos de magic link e de senha.

**Space Groomers â€” Jogo TemÃ¡tico (Add-on)**

- **Novo add-on `desi-pet-shower-game`**: jogo "Space Groomers: InvasÃ£o das Pulgas" estilo Space Invaders para engajamento de clientes no portal.
- **Canvas + JS puro**: zero dependÃªncias pesadas, roda liso em desktop e mobile (touch controls).
- **MecÃ¢nica MVP**: 3 tipos de inimigo (Pulga, Carrapato, Bolota de Pelo), 2 power-ups (Shampoo Turbo, Toalha), 10 waves com dificuldade crescente, combo system, especial "Banho de Espuma".
- **IntegraÃ§Ã£o no portal**: card automÃ¡tico na aba InÃ­cio via hook `dps_portal_after_inicio_content`.
- **Shortcode**: `[dps_space_groomers]` para uso em qualquer pÃ¡gina WordPress.
- **Ãudio**: SFX chiptune via Web Audio API (sem arquivos externos).
- **Recorde local**: pontuaÃ§Ã£o salva em `localStorage` do navegador.
- **Persistencia sincronizada do Space Groomers**: progresso passa a ser salvo em `post meta` do cliente (`dps_game_progress_v1`) quando ha portal autenticado, mantendo fallback local fora do portal.
- **REST do jogo**: adicionadas rotas `dps-game/v1/progress` e `dps-game/v1/progress/sync` para leitura e merge seguro do progresso.
- **Resumo do jogo no portal**: aba Inicio agora mostra missao atual, streak, recorde, badges e ultima run usando dados sincronizados.
- **Recompensas leves no loyalty**: missao diaria, streak 3, streak 7 e primeira vitoria agora podem render pontos com idempotencia via `rewardMarkers`.


**Client Portal â€” Fase 4.1: Indicador de Progresso no Agendamento**

- **Progress bar (stepper)**: modal de pedido de agendamento transformado em wizard de 3 etapas â€” Data/Pet â†’ Detalhes â†’ RevisÃ£o/Confirmar. Componente reutilizÃ¡vel `dps-progress-bar` com cÃ­rculos numerados, conectores e labels.
- **RevisÃ£o prÃ©-envio**: Step 3 exibe resumo completo (tipo, pet, data, perÃ­odo, observaÃ§Ãµes) antes do envio da solicitaÃ§Ã£o.
- **ValidaÃ§Ã£o por etapa**: campos obrigatÃ³rios validados antes de prosseguir para a prÃ³xima etapa, com mensagens inline de erro (`role="alert"`).
- **Acessibilidade**: `role="progressbar"`, `aria-valuenow`, `aria-valuemax`, `aria-live="polite"` para anÃºncio de "Passo X de Y", `aria-required` em campos obrigatÃ³rios.
- **Responsivo**: stepper adapta-se a mobile (480px), botÃµes empilhados verticalmente. `prefers-reduced-motion` remove animaÃ§Ãµes.

**Client Portal â€” Fase 5.3: Seletor RÃ¡pido de Pet (Multi-pet)**

- **Pet selector**: dropdown de pet no Step 1 do modal de agendamento, visÃ­vel quando cliente tem 2+ pets, com Ã­cones de espÃ©cie (ðŸ¶/ðŸ±/ðŸ¾). Dados de pets via `dpsPortal.clientPets`.
- **RevisÃ£o com pet**: pet selecionado aparece no resumo de revisÃ£o (Step 3). Pet prÃ©-selecionado quando aÃ§Ã£o vem de botÃ£o com `data-pet-id`.

**Client Portal â€” Fase 5.5: Aba Pagamentos**

- **Nova aba "Pagamentos"**: aba dedicada no portal com badge de pendÃªncias, acessÃ­vel via tab navigation.
- **Cards de resumo**: grid com cards "Pendente" (â³) e "Pago" (âœ…), exibindo totais formatados e contagem de pendÃªncias.
- **TransaÃ§Ãµes com parcelas**: cada transaÃ§Ã£o exibida como card com data, descriÃ§Ã£o, valor, status. Cards pendentes com borda laranja, pagos com borda verde.
- **Detalhamento de parcelas**: parcelas registradas exibidas em rows com data, mÃ©todo de pagamento (PIX/CartÃ£o/Dinheiro/Fidelidade) e valor. Saldo restante calculado para pendentes.
- **BotÃ£o "Pagar Agora"**: em cada transaÃ§Ã£o pendente para integraÃ§Ã£o com gateway.
- **Responsivo**: layout adapta-se a mobile (480px).

**Client Portal â€” Fase 6.4: AutenticaÃ§Ã£o de Dois Fatores (2FA)**

- **2FA via e-mail**: verificaÃ§Ã£o de seguranÃ§a opcional com cÃ³digo de 6 dÃ­gitos enviado por e-mail apÃ³s clicar no magic link. HabilitÃ¡vel em Portal â†’ ConfiguraÃ§Ãµes.
- **SeguranÃ§a**: cÃ³digo hashed com `wp_hash_password()`, expiraÃ§Ã£o de 10 minutos, mÃ¡ximo 5 tentativas (anti-brute-force). Anti-enumeration: tentativas incrementadas antes da verificaÃ§Ã£o.
- **UI de verificaÃ§Ã£o**: 6 inputs individuais com auto-advance entre dÃ­gitos, suporte a paste (colar cÃ³digo inteiro), auto-submit quando completo. E-mail ofuscado no formulÃ¡rio (j***@gmail.com).
- **E-mail responsivo**: template HTML com dÃ­gitos em caixas estilizadas, branding do portal.
- **Remember-me preservado**: flag de "Manter acesso" Ã© mantida atravÃ©s do fluxo 2FA por estado persistente expirÃ¡vel.
- **Auditoria**: eventos `2fa_code_sent` e `2fa_verified` registrados via `DPS_Audit_Logger`.

**Base Plugin â€” Fase 7.1+7.2: Infraestrutura de Testes**

- **PHPUnit configurado**: `composer.json`, `phpunit.xml`, `tests/bootstrap.php` com mocks WordPress para o plugin base.
- **22 testes unitÃ¡rios**: 13 testes para `DPS_Money_Helper` (parse, format, cents, currency, validaÃ§Ã£o) + 9 testes para `DPS_Phone_Helper` (clean, format, validate, WhatsApp).
- **Comando**: `cd plugins/desi-pet-shower-base && composer install && vendor/bin/phpunit`

**Base Plugin â€” Fase 2.4: Sistema de Templates**

- **Template Engine**: `DPS_Base_Template_Engine` â€” renderiza templates PHP com dados injetados via `extract()`, suporta override via tema em `dps-templates/base/`. Singleton com `get_instance()`, mÃ©todos `render()` e `exists()`.
- **Primeiro template**: `templates/components/client-summary-cards.php` â€” cards de mÃ©tricas do cliente (cadastro, atendimentos, total gasto, Ãºltimo atendimento, pendÃªncias).
- **7 testes unitÃ¡rios** para o template engine (render, exists, subdirectory, XSS escaping, static content, nonexistent).
- **Total: 29 testes** passando no plugin base.

**Client Portal â€” Fase 8.1: SugestÃµes Inteligentes de Agendamento**

- **SugestÃ£o baseada em histÃ³rico**: `DPS_Scheduling_Suggestions` analisa atÃ© 20 atendimentos por pet para calcular intervalo mÃ©dio, serviÃ§os mais frequentes (top 3), dias desde Ãºltimo atendimento e urgÃªncia.
- **Banner no modal**: exibido no Step 1 do wizard de agendamento com 3 nÃ­veis de urgÃªncia: â° AtenÃ§Ã£o (overdue/amber), ðŸ“… Em breve (soon/blue), ðŸ’¡ SugestÃ£o (normal/cinza).
- **Auto-fill**: data sugerida preenchida automaticamente no campo de data. BotÃ£o "Usar data sugerida" para aplicar.
- **Multi-pet**: banner atualiza dinamicamente ao trocar pet no seletor, mostrando sugestÃ£o especÃ­fica de cada pet.
- **Dados via JS**: `dpsPortal.schedulingSuggestions` indexado por pet_id com suggested_date, avg_interval, days_since_last, top_services, urgency.

**DocumentaÃ§Ã£o â€” Fase 7.3: PadrÃ£o de DI**

- **SeÃ§Ã£o adicionada** ao `docs/refactoring/AGENT_ENGINEERING_PLAYBOOK.md`: documenta 3 estratÃ©gias de instanciaÃ§Ã£o (Singleton, Constructor Injection, Static Renderers) com exemplos e regras de quando usar cada uma.

**DocumentaÃ§Ã£o â€” Fase 8.2: AtualizaÃ§Ã£o ContÃ­nua**

- **ANALYSIS.md**: Portal do Cliente expandido com 2FA, payments tab, scheduling suggestions, progress bar, multi-pet selector, classes e hooks. Base Plugin: DPS_Base_Template_Engine. Hooks map: hooks do Portal Add-on adicionados.
- **FUNCTIONS_REFERENCE.md**: DPS_Portal_2FA (8 mÃ©todos), DPS_Scheduling_Suggestions (1 mÃ©todo), DPS_Finance_Repository (6 mÃ©todos), DPS_Base_Template_Engine (3 mÃ©todos) documentados com assinaturas, parÃ¢metros, retornos e exemplos.
- **Table of Contents**: atualizada com novos links para DPS_Portal_2FA, DPS_Scheduling_Suggestions, DPS_Finance_Repository, DPS_Base_Template_Engine, DPS_Audit_Logger.

#### Changed (Alterado)

**Cadastro e Portal - DPS Signature**

- **Topo do cadastro simplificado**: removido o chip textual `DPS Signature` e a frase introdutoria redundante do formulario publico, mantendo apenas o titulo operacional do cadastro.

- **Cadastro sem camada de manual**: removidos textos explicativos longos, listas de instruÃ§Ã£o e helpers desnecessÃ¡rios do cadastro pÃºblico, deixando a experiÃªncia objetiva, compacta e alinhada exclusivamente ao DPS Signature.
- **RemoÃ§Ã£o de referÃªncias do padrÃ£o visual anterior**: templates e assets carregados pelos formulÃ¡rios Signature deixam de expor nomenclatura tÃ©cnica antiga no cÃ³digo e na interface.
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
- **Lista de Atendimentos em workspace DPS Signature**: shell, overview, tabs, linhas e microcopy da lista foram redesenhados para leitura operacional mais limpa, consistente e responsiva.
- **Cards de overview refinados**: Total, Pendentes, Finalizados, Cancelados, Atrasados, Pagamento pendente e TaxiDog agora usam superficies tonais mais claras, hierarquia forte de numero e destaque principal para o volume total.
- **CSS da Agenda consolidado**: removidas regras redundantes e estados legados nas tabs, overview e paineis operacionais, reduzindo heranca acidental e duplicacao entre `agenda-addon.css` e `checklist-checkin.css`.
- **Cards de overview compactados**: reduzida a altura, o padding interno, a escala numerica, o destaque expansivo do card Total e a iconografia decorativa para deixar a Agenda menos poluida e mais elegante.
- **Cards de overview sem respiro morto**: removido o excesso de margem superior herdado da antiga area de icones, aproximando o valor e o label do topo util do card.
- **Operacao com profundidade unica**: checklist operacional e check-in/check-out agora vivem no mesmo painel expansivel inline da aba Operacao, eliminando a quebra de contexto entre grid e modal.
- **Dialogs padronizados na Agenda**: reagendamento, historico, cobranca, confirmacoes sensiveis e retrabalho passaram a usar o mesmo dialog system do add-on, removendo `confirm()`/`alert()` do fluxo principal da lista.
**Client Portal ? Tela inicial e administra??o de logins**

- **Landing p?blica refeita no padr?o DPS Signature**: a p?gina inicial do portal agora apresenta lado a lado as op??es de link direto e e-mail com senha, com suporte contextual para WhatsApp quando o e-mail n?o estiver cadastrado.
- **Reset de senha dentro do portal**: nova tela dedicada para cria??o/redefini??o de senha, mantendo o mesmo contexto visual do acesso p?blico.
- **Admin de logins revisado**: a ?rea administrativa passou a exibir estado de magic link, estado do acesso por senha, ?ltimo login, atividade recente e a??es de sincroniza??o/envio de acesso por senha.
- **Sess?o h?brida unificada**: logins por magic link e por senha agora compartilham restaura??o de sessÃ£o, remember-me e registro de ?ltimo acesso.
- **Client Portal sem transients/cache interno**: sessao do portal, 2FA, remember-me pendente, rate limiting e auditoria de tokens foram migrados para options persistentes com expiracao/retencao propria; secoes do portal passam a renderizar sempre em tempo real mantendo hooks legados de compatibilidade.

**AI Add-on â€” Assistente Virtual no Portal do Cliente**

- **Acessibilidade**: adicionado `role="region"` e `aria-label` ao container principal, `tabindex="0"` ao header, `aria-live="polite"` na Ã¡rea de mensagens, `aria-label` nos botÃµes de sugestÃ£o, `focus-visible` em todos os elementos interativos (header, FAB, sugestÃµes, enviar, feedback).
- **Teclado**: tecla Escape recolhe o widget inline ou fecha o flutuante, retornando foco ao elemento adequado.
- **ResiliÃªncia**: timeout de 15s no AJAX com mensagem de erro especÃ­fica; prevenÃ§Ã£o de envio duplo com flag `isSubmitting`.
- **Chevron**: Ã­cone de seta agora aponta para baixo quando colapsado (indicando "expandir") e para cima quando expandido.

**Client Portal â€” UX/UI do Shell e NavegaÃ§Ã£o por Tabs**

- **NavegaÃ§Ã£o por tabs**: estado ativo mais forte (font-weight 600), scroll horizontal com snap em mobile, gradientes de overflow indicando direÃ§Ã£o de rolagem.
- **Breadcrumb dinÃ¢mico**: atualiza automaticamente o item ativo ao trocar de aba, mantendo contexto de navegaÃ§Ã£o.
- **Scroll automÃ¡tico**: aba ativa Ã© rolada para a Ã¡rea visÃ­vel em dispositivos mÃ³veis.
- **Acessibilidade**: separador do breadcrumb com `aria-hidden`, suporte a `prefers-reduced-motion` na animaÃ§Ã£o de troca de painel, transiÃ§Ãµes CSS especÃ­ficas (sem `transition: all`).
- **EspaÃ§amento**: hierarquia visual refinada com tÃ­tulo e breadcrumb mais compactos.

**Client Portal â€” Aba InÃ­cio (revisÃ£o completa)**

- **Acessibilidade**: `focus-visible` adicionado a todos os elementos interativos da aba InÃ­cio (overview cards, quick actions, botÃµes de aÃ§Ã£o pet, link buttons, collapsible header, botÃµes de agendamento, botÃµes de pagamento, botÃµes de sugestÃ£o).
- **Card de fidelidade**: corrigido clique no card de pontos (overview) â€” agora navega para a aba Fidelidade conforme esperado; suporte a Enter/Space para elementos com `role="button"`.
- **TransiÃ§Ãµes CSS**: substituÃ­do `transition: all` por propriedades especÃ­ficas nos componentes pet card, quick action e pet action button.

**Client Portal â€” Aba Fidelidade (revisÃ£o completa)**

- **Acessibilidade**: barra de progresso com `role="progressbar"`, `aria-valuenow`, `aria-valuemin`, `aria-valuemax` e `aria-label`; `focus-visible` em todos os elementos interativos (botÃ£o copiar, link ver histÃ³rico, carregar mais, botÃ£o resgatar, input de referral); campo numÃ©rico agora mantÃ©m outline no foco (era removido com `outline: none`).
- **ResiliÃªncia**: erro no carregamento de histÃ³rico agora exibe toast; botÃ£o de resgate preserva texto original apÃ³s submit (era hardcoded); valor do input de resgate Ã© clamped ao novo max apÃ³s resgate bem-sucedido.
- **Clipboard**: fallback via `document.execCommand('copy')` para contextos sem HTTPS.
- **TransiÃ§Ãµes CSS**: substituÃ­do `transition: all` por propriedades especÃ­ficas no botÃ£o de resgate.

**Client Portal â€” Home autenticada refresh DPS Signature**

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
- **Fila operacional canonica**: o refresh AJAX da Agenda agora atualiza em conjunto a linha desktop e o card mobile do mesmo atendimento, mantendo selecao, inspetor contextual e resposta coerente no runtime publicado.
- **Shell operacional unico**: o runtime publicado deixou de depender das abas legadas e agora responde sempre pela fila operacional DPS Signature, inclusive nas atualizacoes do modal operacional.

- **Agenda vazia**: corrigida a condicao de empty state para refletir o conjunto exibido de fato e oferecer recuperacao objetiva ao usuario.
- **Paginacao e acessibilidade das tabs**: preservado apenas o contexto necessario da aba ativa ao paginar a agenda completa; os paineis agora expoem `aria-labelledby`, `hidden` e navegacao por teclado consistente.
- **SeguranÃ§a**: corrigida verificaÃ§Ã£o de propriedade do pet na impressÃ£o de histÃ³rico â€” usava meta key incorreta `pet_client_id` ao invÃ©s de `owner_id`, impedindo acesso legÃ­timo Ã  funcionalidade.

- **Agenda - modal de serviÃ§os**: corrigido o carregamento do modal na lista de atendimentos, com endpoint mais resiliente para dados inconsistentes e resposta JSON mesmo quando a sessÃ£o expira antes do clique.
- **Resumo do prÃ³ximo agendamento**: a consulta de futuros no portal agora ordena por data/hora e ignora status concluidos ou cancelados, evitando destaque incorreto na home autenticada.
- **WhatsApp do portal**: a aÃ§Ã£o de repetir serviÃ§o deixa de depender de nÃºmero hardcoded e passa a usar apenas o contato configurado, com fallback seguro quando o nÃºmero nÃ£o estiver disponÃ­vel.
- **Agendamentos - horarios**: endurecido o carregamento de horarios com controle de concorrencia no frontend, validacao de nonce compativel no backend e fallback autenticado via REST quando `admin-ajax.php` nao responde corretamente.
- **Booking Add-on - permissao de agendamento**: a pagina dedicada passa a exigir permissao real de agendamentos antes de renderizar o formulario, evitando o estado inconsistente em que a data era selecionada mas o carregamento de horarios falhava no AJAX.
- **Space Groomers - conflito com agendamentos**: removida a saida indevida de BOM UTF-8 dos arquivos PHP do add-on e corrigido o card do portal que usava payload nao inicializado, eliminando o conflito que quebrava headers e contaminava respostas AJAX/JSON.
- **Agendamentos - selecao de pets**: unificada a compatibilidade `owner_id`/`pet_owner` no preparo e renderizacao do formulario, evitando casos em que apenas parte dos pets era exibida ao selecionar o cliente.

#### Removed (Removido)

- **Agenda Add-on**: removido definitivamente o bloco operacional legado da Agenda no frontend, backend, estilos e artefatos estaticos de apoio.

#### Security (SeguranÃ§a)

**Fase 1 â€” SeguranÃ§a CrÃ­tica (Plano de ImplementaÃ§Ã£o)**

- **Finance Add-on**: adicionados backticks em table identifiers e `phpcs:ignore` documentado em queries DDL (ALTER TABLE, CREATE INDEX, SHOW COLUMNS) que usam `$wpdb->prefix`. Queries `get_col`, `count_query` e `all_trans_query` agora utilizam backticks e documentaÃ§Ã£o de seguranÃ§a.
- **Base Plugin**: corrigida query LIKE sem `esc_like`/`prepare()` em `class-dps-base-frontend.php`. Adicionada documentaÃ§Ã£o de seguranÃ§a em `class-dps-logs-admin-page.php` e `uninstall.php`.
- **Backup Add-on**: migradas queries SELECT/DELETE que usavam `$ids_in` com `intval()` para padrÃ£o correto com placeholders dinÃ¢micos e `$wpdb->prepare()`. Queries LIKE agora usam `$wpdb->prepare()`.
- **AI Add-on**: adicionados backticks e documentaÃ§Ã£o de seguranÃ§a em queries COUNT/MIN em `class-dps-ai-maintenance.php` e `class-dps-ai-analytics.php`.
- **Services Add-on**: sanitizaÃ§Ã£o imediata de arrays `$_POST` (`appointment_extra_names`, `appointment_extra_prices`) com `sanitize_text_field()` e `wp_unslash()`.
- **Auditoria**: criado documento completo de auditoria em `docs/security/AUDIT_FASE1.md` com mapeamento de todas as queries, nonces, capabilities, REST permissions e sanitizaÃ§Ã£o de entrada.

#### Refactoring (Interno)

**Cadastro e Portal - fundacao compartilhada**

- **Fundacao unica de formularios**: criada a camada compartilhada `dps-signature-forms.css/js` no base plugin para concentrar tokens, estados de campo, mascara, autocomplete, disclosures e comportamentos reutilizados por cadastro, portal e formularios internos.
- **Reescrita estrutural sem wrapper legado**: o alias `[dps_registration_form]` foi reduzido a compatibilidade de entrada, enquanto o motor nativo do frontend assumiu a renderizacao e o pipeline efetivo do cadastro publico.
- **Portal profile update desacoplado de inline code**: template, CSS e JavaScript do update de perfil foram extraidos para assets dedicados, removendo scripts/estilos inline e bridges temporarias.

**Agenda Add-on - fluxo operacional consolidado**

- **Renderer, AJAX e JavaScript reorganizados**: a aba Operacao passa a reutilizar um unico modal DPS Signature, reduzindo acoplamento entre tabela, modais avulsos e paineis operacionais legados.

**Fase 2 â€” RefatoraÃ§Ã£o Estrutural (Plano de ImplementaÃ§Ã£o)**

- **DecomposiÃ§Ã£o do monÃ³lito**: extraÃ­das 9 classes de `class-dps-base-frontend.php` (5.986 â†’ 1.581 linhas, â€“74%): `DPS_Client_Handler` (184L), `DPS_Pet_Handler` (337L), `DPS_Appointment_Handler` (810L), `DPS_Client_Page_Renderer` (1.506L, 23 mÃ©todos), `DPS_Breed_Registry` (201L, dataset de raÃ§as por espÃ©cie), `DPS_History_Section_Renderer` (481L, seÃ§Ã£o de histÃ³rico), `DPS_Appointments_Section_Renderer` (926L, seÃ§Ã£o de agendamentos com formulÃ¡rio e listagem), `DPS_Clients_Section_Renderer` (270L, seÃ§Ã£o de clientes com filtros e estatÃ­sticas), `DPS_Pets_Section_Renderer` (345L, seÃ§Ã£o de pets com filtros e paginaÃ§Ã£o). Cada classe encapsula responsabilidade Ãºnica (SRP). O frontend mantÃ©m facades que delegam para as classes extraÃ­das.
- **DPS_Phone_Helper::clean()**: adicionado mÃ©todo utilitÃ¡rio para limpeza de telefone (remove nÃ£o-dÃ­gitos), centralizando lÃ³gica duplicada em 9+ arquivos.
- **CentralizaÃ§Ã£o DPS_Money_Helper**: migradas 16 instÃ¢ncias de `number_format()` para `DPS_Money_Helper::format_currency()` e `format_currency_from_decimal()` em 10 add-ons (Communications, AI, Agenda, Finance, Loyalty, Client Portal). Removidos fallbacks `class_exists()` desnecessÃ¡rios.
- **Template padrÃ£o de add-on**: documentado em `ANALYSIS.md` com estrutura de diretÃ³rios, header WP, padrÃ£o de inicializaÃ§Ã£o (init@1, classes@5, admin_menu@20), assets condicionais e tabela de compliance.
- **DocumentaÃ§Ã£o de metadados**: adicionada seÃ§Ã£o "Contratos de Metadados dos CPTs" no `ANALYSIS.md` com tabelas detalhadas de meta keys para `dps_cliente`, `dps_pet` e `dps_agendamento`, incluindo tipos, formatos e relaÃ§Ãµes.

**Fase 3 â€” Performance e Escalabilidade (Plano de ImplementaÃ§Ã£o)**

- **N+1 eliminado**: refatorado `query_appointments_for_week()` no trait `DPS_Agenda_Query` de 7 queries separadas para 1 query com `BETWEEN` + agrupamento em PHP (â€“85% queries DB).
- **Lazy loading**: adicionado `loading="lazy"` em 5 imagens nos plugins Base e Client Portal (`class-dps-base-frontend.php`, `pet-form.php`, `class-dps-portal-renderer.php`).
- **dbDelta version checks**: adicionados guards de versÃ£o em `DPS_AI_Analytics::maybe_create_tables()` e `DPS_AI_Conversations_Repository::maybe_create_tables()` para evitar `dbDelta()` em toda requisiÃ§Ã£o.
- **WP_Query otimizada**: `DPS_Query_Helper::get_all_posts_by_type()`, `get_posts_by_meta()` e `get_posts_by_meta_query()` agora incluem `no_found_rows => true` por padrÃ£o, eliminando SQL_CALC_FOUND_ROWS desnecessÃ¡rio em todas as consultas centralizadas.
- **Assets condicionais**: Stock add-on corrigido â€” CSS nÃ£o Ã© mais carregado globalmente em todas as pÃ¡ginas admin; agora usa `$hook_suffix` para carregamento condicional.
- **Subscription queries**: queries de delete de agendamentos e contagem migradas para `fields => 'ids'` + `no_found_rows => true`, eliminando carregamento desnecessÃ¡rio de objetos completos.
- **Finance query limits** (Fase 3.2): dropdown de clientes otimizado com `no_found_rows => true` e desabilitaÃ§Ã£o de meta/term cache. Query de resumo financeiro limitada a 5.000 registros (safety cap). Busca de clientes limitada a 200 resultados.
- **Auditoria de rate limiting**: verificado que rate limiting jÃ¡ existe em 3 camadas: magic link request (3/hora por IP+email), token validation (5/hora por IP), chat (10/60s por cliente).

#### Changed (Alterado)

**Fase 4 â€” UX do Portal do Cliente (Plano de ImplementaÃ§Ã£o)**

- **ValidaÃ§Ã£o em tempo real**: adicionado `handleFormValidation()` no portal do cliente com regras para telefone (formato BR), e-mail, CEP, UF, peso do pet, data de nascimento e campos obrigatÃ³rios. ValidaÃ§Ã£o on blur + limpeza instantÃ¢nea on input + validaÃ§Ã£o completa pre-submit com scroll automÃ¡tico para o primeiro erro.
- **Estados visuais**: CSS para `.is-invalid` (borda e glow vermelho) e `.is-valid` (borda verde) nos inputs `.dps-form-control`, com suporte a `prefers-reduced-motion`.
- **Containers de erro**: adicionados `<span class="dps-field-error" role="alert">` apÃ³s campos validados, com `aria-describedby` vinculando input ao container de mensagem.
- **Acessibilidade ARIA**: `aria-required="true"` em campos obrigatÃ³rios (pet name), `aria-describedby` em 7 campos, `role="alert"` em containers de erro, `inputmode="numeric"` no CEP.
- **Atributos HTML5**: `max` no campo de data de nascimento (impede futuro), `max="200"` no campo de peso.
- **Mensagens aprimoradas**: 5 novos tipos de mensagem toast (message_error, review_submitted, review_already, review_invalid, review_error). Todas as mensagens reescritas com tÃ­tulos descritivos e textos orientados a aÃ§Ã£o.
- **Filtro de perÃ­odo no histÃ³rico** (Fase 4.4): barra de filtros (30/60/90 dias, Todos) acima da timeline de serviÃ§os. Filtragem client-side via `data-date` nos itens. Mensagem "nenhum resultado" quando filtro vazio. CSS DPS Signature com `focus-visible` e `aria-pressed`.
- **Detalhes do pet no card** (Fase 4.5): porte (ðŸ“ Pequeno/MÃ©dio/Grande/Gigante), peso (âš–ï¸ em kg), sexo (â™‚ï¸/â™€ï¸), idade (ðŸŽ‚ calculada automaticamente de `pet_birth`) exibidos no card de info do pet na timeline. CSS com grid responsiva de meta items.
- **"Manter acesso neste dispositivo"** (Fase 4.6): checkbox no formulÃ¡rio de login por e-mail permite manter sessÃ£o permanente. Gera token permanente com cookie seguro `dps_portal_remember` (HttpOnly, Secure, SameSite=Strict, 90 dias). Auto-autenticaÃ§Ã£o via `handle_remember_cookie()` na prÃ³xima visita. Cookie removido no logout.

**Fase 5 â€” Funcionalidades Novas (Portal)**

- **Galeria multi-fotos** (Fase 5.1): pets agora suportam mÃºltiplas fotos via meta key `pet_photos` (array de IDs) com fallback automÃ¡tico para `pet_photo_id` legado. Adicionado `DPS_Pet_Handler::get_all_photo_ids()`. Grid multi-foto responsiva com contagem de fotos por pet. Lightbox com navegaÃ§Ã£o prev/next (setas clicÃ¡veis + ArrowLeft/ArrowRight no teclado), contador de fotos (1/N) e agrupamento por `data-gallery`.
- **PreferÃªncias de notificaÃ§Ã£o** (Fase 5.2): 4 toggles DPS Signature na tela de preferÃªncias â€” lembretes de agendamento (ðŸ“…), avisos de pagamento (ðŸ’°), promoÃ§Ãµes e ofertas (ðŸŽ), atualizaÃ§Ãµes do pet (ðŸ¾). Defaults inteligentes: lembretes e pagamentos ligados, promoÃ§Ãµes e updates desligados. Toggle switches CSS com focus-visible e hover states. Handler atualizado com hook `dps_portal_after_update_preferences` expandido.
- **Feedback pÃ³s-agendamento** (Fase 5.4): prompt de avaliaÃ§Ã£o exibido no final do histÃ³rico de agendamentos. Star rating interativo (1-5 estrelas, `role="radiogroup"` com ARIA labels). Textarea para comentÃ¡rio opcional. IntegraÃ§Ã£o com handler existente `submit_internal_review` e CPT `dps_groomer_review`. Estado "jÃ¡ avaliou" com estrelas e mensagem de agradecimento.

**Fase 6 â€” SeguranÃ§a AvanÃ§ada e Auditoria**

- **Auditoria centralizada** (Fase 6.2): criada classe `DPS_Audit_Logger` (446 linhas, 14 mÃ©todos estÃ¡ticos) com tabela `dps_audit_log` para registro de eventos de auditoria (criar, atualizar, excluir, login, mudanÃ§a de status) em todas as entidades do sistema (clientes, pets, agendamentos, portal, financeiro).
- **Admin page de auditoria**: criada `DPS_Audit_Admin_Page` (370 linhas) com filtros por tipo de entidade, aÃ§Ã£o, perÃ­odo e paginaÃ§Ã£o (30/pÃ¡gina). Badges coloridos para tipos de aÃ§Ã£o. Integrada como aba "Auditoria" no System Hub.
- **IntegraÃ§Ã£o nos handlers**: chamadas de auditoria adicionadas em `DPS_Client_Handler` (save/delete), `DPS_Pet_Handler` (save/delete) e `DPS_Appointment_Handler` (save/status_change).
- **Auditoria de cÃ³digo morto** (Fase 7.4): inventÃ¡rio completo de JS/CSS/PHP em todos os plugins â€” nenhum arquivo morto encontrado. Ãšnico arquivo nÃ£o carregado (`refactoring-examples.php`) Ã© intencional e documentado em AGENTS.md.
- **Logging de tentativas falhadas** (Fase 6.3): integrado `DPS_Audit_Logger` nos fluxos de autenticaÃ§Ã£o do portal â€” registra token_validation_failed, login_success e rate_limit_ip no log de auditoria centralizado.

#### Added (Adicionado)

**Agenda Add-on v1.2.0 â€” Checklist Operacional e Check-in/Check-out**

- **Checklist Operacional**: painel interativo com etapas de banho e tosa (prÃ©-banho, banho, secagem, tosa/corte, orelhas/unhas, acabamento). Cada etapa pode ser marcada como concluÃ­da, pulada ou revertida. Barra de progresso em tempo real.
- **Retrabalho (rework)**: registro de retrabalho por etapa com motivo e timestamp. Badge visual indica quantas vezes uma etapa precisou ser refeita.
- **Check-in / Check-out**: registro rÃ¡pido de entrada e saÃ­da do pet com cÃ¡lculo automÃ¡tico de duraÃ§Ã£o (em minutos).
- **Itens de seguranÃ§a**: 7 itens prÃ©-definidos (pulgas, carrapatos, feridinhas, alergia, otite, nÃ³s, comportamento) com nÃ­vel de severidade e campo de notas por item. FiltrÃ¡vel via `dps_checkin_safety_items`.
- **ObservaÃ§Ãµes rÃ¡pidas**: campo de texto livre para observaÃ§Ãµes no check-in e check-out.
- **AJAX endpoints**: `dps_checklist_update`, `dps_checklist_rework`, `dps_appointment_checkin`, `dps_appointment_checkout` â€” todos com nonce + capability check.
- **Hooks de extensÃ£o**: `dps_checklist_default_steps`, `dps_checklist_rework_registered`, `dps_checkin_safety_items`, `dps_appointment_checked_in`, `dps_appointment_checked_out`.
- **Render helpers**: `render_checklist_panel()`, `render_checkin_panel()`, `render_compact_indicators()` â€” prontos para integraÃ§Ã£o em templates de cards de agendamento.
- **Design DPS Signature**: CSS com design tokens, responsivo, com modal de retrabalho e grid de itens de seguranÃ§a.

**Frontend Add-on v1.0.0 â€” FundaÃ§Ã£o (Fase 1)**

- **Novo add-on `desi-pet-shower-frontend`**: esqueleto modular para consolidaÃ§Ã£o de experiÃªncias frontend (cadastro, agendamento, configuraÃ§Ãµes).
- **Arquitetura moderna PHP 8.4**: constructor promotion, readonly properties, typed properties, return types. Sem singletons â€” composiÃ§Ã£o via construtor.
- **Module Registry**: registro e boot de mÃ³dulos independentes controlados por feature flags.
- **Feature Flags**: controle de rollout por mÃ³dulo via option `dps_frontend_feature_flags`. Todos desabilitados na Fase 1.
- **Camada de compatibilidade**: preparada para bridges de shortcodes e hooks legados (Fases 2-4).
- **Assets DPS Signature**: CSS sem hex literais (100% via design tokens), JS vanilla com IIFE. Enqueue condicional.
- **Observabilidade**: logger estruturado com nÃ­veis INFO/WARNING/ERROR (ativo apenas em WP_DEBUG).
- **Request Guard**: seguranÃ§a centralizada para nonce, capability e sanitizaÃ§Ã£o.
- **MÃ³dulos stub**: Registration (Fase 2), Booking (Fase 3), Settings (Fase 4).
- **Registrado no Addon Manager** do plugin base (categoria client, prioridade 72).
- **Documentado no ANALYSIS.md** com arquitetura interna, contratos e roadmap.

**Frontend Add-on v1.1.0 â€” MÃ³dulo Registration (Fase 2)**

- **MÃ³dulo Registration operacional** em dual-run com add-on legado `desi-pet-shower-registration`.
- **EstratÃ©gia de intervenÃ§Ã£o mÃ­nima**: assume shortcode `[dps_registration_form]`, delega toda a lÃ³gica (formulÃ¡rio, validaÃ§Ã£o, emails, REST, AJAX) ao legado.
- **Surface DPS Signature wrapper**: output do formulÃ¡rio envolvido em `.dps-frontend` para aplicaÃ§Ã£o de estilos DPS Signature.
- **CSS extra**: `frontend-addon.css` carregado condicionalmente sobre os assets do legado.
- **Hooks preservados**: `dps_registration_after_fields`, `dps_registration_after_client_created`, `dps_registration_spam_check`, `dps_registration_agenda_url`.
- **Rollback instantÃ¢neo**: desabilitar flag `registration` restaura comportamento 100% legado.
- **Camada de compatibilidade**: bridge de shortcode ativo quando flag habilitada.

**Frontend Add-on v1.2.0 â€” MÃ³dulo Booking (Fase 3)**

- **MÃ³dulo Booking operacional** em dual-run com add-on legado `desi-pet-shower-booking`.
- **EstratÃ©gia de intervenÃ§Ã£o mÃ­nima**: assume shortcode `[dps_booking_form]`, delega toda a lÃ³gica (formulÃ¡rio, confirmaÃ§Ã£o, captura de appointment) ao legado.
- **Surface DPS Signature wrapper**: output do formulÃ¡rio envolvido em `.dps-frontend` para aplicaÃ§Ã£o de estilos DPS Signature.
- **CSS extra**: `frontend-addon.css` carregado condicionalmente sobre os assets do legado.
- **Hooks preservados**: `dps_base_after_save_appointment` (consumido por 7+ add-ons: stock, payment, groomers, calendar, communications, push, services), `dps_base_appointment_fields`, `dps_base_appointment_assignment_fields`.
- **Rollback instantÃ¢neo**: desabilitar flag `booking` restaura comportamento 100% legado.
- **Camada de compatibilidade**: bridge de shortcode ativo quando flag habilitada.

**Frontend Add-on v1.3.0 â€” MÃ³dulo Settings (Fase 4)**

- **MÃ³dulo Settings operacional** integrado ao sistema de abas de `DPS_Settings_Frontend`.
- **Aba "Frontend"** registrada via API moderna `register_tab()` com prioridade 110.
- **Controles de feature flags**: interface administrativa para habilitar/desabilitar mÃ³dulos individualmente (Registration, Booking, Settings).
- **Salvamento seguro**: handler via hook `dps_settings_save_save_frontend`, nonce e capability verificados pelo sistema base.
- **InformaÃ§Ãµes do add-on**: versÃ£o e contagem de mÃ³dulos ativos exibidos na aba.
- **Hooks consumidos**: `dps_settings_register_tabs`, `dps_settings_save_save_frontend`.
- **Rollback instantÃ¢neo**: desabilitar flag `settings` remove a aba sem impacto em outras configuraÃ§Ãµes.
- **Camada de compatibilidade**: bridge de hooks ativo quando flag habilitada.

**Frontend Add-on v1.4.0 â€” ConsolidaÃ§Ã£o e DocumentaÃ§Ã£o (Fase 5)**

- **Guia operacional de rollout** (`docs/implementation/FRONTEND_ROLLOUT_GUIDE.md`): passos de ativaÃ§Ã£o por ambiente (dev, homolog, prod), ordem recomendada, verificaÃ§Ã£o pÃ³s-ativaÃ§Ã£o.
- **Runbook de incidentes** (`docs/implementation/FRONTEND_RUNBOOK.md`): classificaÃ§Ã£o de severidade, diagnÃ³stico rÃ¡pido, procedimentos de rollback por mÃ³dulo, cenÃ¡rios de incidente especÃ­ficos.
- **Matriz de compatibilidade** (`docs/qa/FRONTEND_COMPATIBILITY_MATRIX.md`): status de integraÃ§Ã£o com 18 add-ons, contratos de shortcodes/hooks/options verificados, impacto de desativaÃ§Ã£o por mÃ³dulo.
- **Checklist de remoÃ§Ã£o futura** (`docs/qa/FRONTEND_REMOVAL_READINESS.md`): critÃ©rios objetivos por mÃ³dulo, riscos e mitigaÃ§Ã£o, procedimento de remoÃ§Ã£o segura (nenhuma remoÃ§Ã£o nesta etapa).

**Frontend Add-on v1.5.0 â€” GovernanÃ§a de DepreciaÃ§Ã£o (Fase 6)**

- **PolÃ­tica de depreciaÃ§Ã£o** (`docs/refactoring/FRONTEND_DEPRECATION_POLICY.md`): janela mÃ­nima de 180 dias (90 dual-run + 60 aviso + 30 observaÃ§Ã£o), processo de comunicaÃ§Ã£o formal, critÃ©rios de aceite tÃ©cnicos e de governanÃ§a, procedimento de depreciaÃ§Ã£o em 5 etapas.
- **Lista de alvos de remoÃ§Ã£o** (`docs/refactoring/FRONTEND_REMOVAL_TARGETS.md`): inventÃ¡rio completo com dependÃªncias por grep (registration: 5 refs no base + 2 hooks no Loyalty; booking: 0 refs externas), risco por alvo, esforÃ§o estimado, plano de reversÃ£o, ordem de prioridade recomendada.
- **Telemetria de uso**: mÃ©todo `DPS_Frontend_Logger::track()` com contadores por mÃ³dulo persistidos em `dps_frontend_usage_counters`. Cada renderizaÃ§Ã£o de shortcode via mÃ³dulo frontend Ã© contabilizada. Contadores exibidos na aba Settings para apoiar decisÃµes de depreciaÃ§Ã£o.

**Frontend Add-on v2.0.0 â€” Fase 7.1 PreparaÃ§Ã£o (ImplementaÃ§Ã£o Nativa)**

- **Novas feature flags `registration_v2` e `booking_v2`**: flags independentes para mÃ³dulos nativos V2. Coexistem com flags v1 (`registration`, `booking`). Ambas podem estar ativas simultaneamente.
- **Template Engine (`DPS_Template_Engine`)**: sistema de renderizaÃ§Ã£o com suporte a override via tema (dps-templates/), output buffering seguro e dados isolados por escopo.
- **Classes abstratas base (Fase 7)**:
  - `DPS_Abstract_Module_V2`: base para mÃ³dulos nativos com boot padronizado, registro de shortcode e enqueue condicional de assets.
  - `DPS_Abstract_Handler`: base para handlers de formulÃ¡rio com resultado padronizado (success/error).
  - `DPS_Abstract_Service`: base para services CRUD com wp_insert_post e gerenciamento de metas.
  - `DPS_Abstract_Validator`: base para validadores com helpers de campo obrigatÃ³rio e email.
- **Hook Bridges (compatibilidade retroativa)**:
  - `DPS_Registration_Hook_Bridge`: dispara hooks legados (Loyalty) + novos hooks v2 apÃ³s aÃ§Ãµes de registro. Ordem: legado PRIMEIRO, v2 DEPOIS.
  - `DPS_Booking_Hook_Bridge`: dispara hook crÃ­tico `dps_base_after_save_appointment` (8 consumidores) + novos hooks v2. Ordem: legado PRIMEIRO, v2 DEPOIS.
- **MÃ³dulos V2 nativos (skeleton)**:
  - `DPS_Frontend_Registration_V2_Module`: shortcode `[dps_registration_v2]`, independente do legado, com template engine e hook bridge.
  - `DPS_Frontend_Booking_V2_Module`: shortcode `[dps_booking_v2]`, independente do legado, com login check, REST/AJAX skip, template engine e hook bridge.
- **11 componentes DPS Signature reutilizÃ¡veis** (templates/components/): field-text, field-email, field-phone, field-select, field-textarea, field-checkbox, button-primary, button-secondary, card, alert, loader. Todos com acessibilidade ARIA nativa, namespacing `.dps-v2-*`, suporte a erro e helper text.
- **Templates skeleton**: registration/form-main.php, booking/form-main.php, booking/form-login-required.php. Wizard com barra de progresso 5 steps.
- **Assets V2 nativos (CSS + JS)**: registration-v2.css, booking-v2.css com 100% design tokens DPS Signature (zero hex hardcoded), suporte a tema escuro, `prefers-reduced-motion`, responsividade. JS vanilla (zero jQuery).
- **Aba Settings atualizada**: exibe flags v2 (Fase 7) com labels e descriÃ§Ãµes distintas. Telemetria v2 separada.
- **Estrutura de diretÃ³rios completa**: handlers/, services/, validators/, ajax/, bridges/, abstracts/, templates/registration/, templates/booking/, templates/components/, templates/emails/.

**Frontend Add-on v2.1.0 â€” Fase 7.2 Registration V2 (ImplementaÃ§Ã£o Nativa)**

- **Validators**:
  - `DPS_Cpf_Validator`: validaÃ§Ã£o CPF mod-11 com normalizaÃ§Ã£o, rejeiÃ§Ã£o de sequÃªncias repetidas. CompatÃ­vel com legado.
  - `DPS_Form_Validator`: validaÃ§Ã£o completa do formulÃ¡rio (nome, email, telefone, CPF, pets). Usa `DPS_Cpf_Validator` internamente.
- **Services**:
  - `DPS_Client_Service`: CRUD para post type `dps_cliente`. Cria clientes com 13+ metas padronizadas. NormalizaÃ§Ã£o de telefone com fallback para `DPS_Phone_Helper`.
  - `DPS_Pet_Service`: CRUD para post type `dps_pet`. Vincula pets a clientes via meta `owner_id`.
  - `DPS_Breed_Provider`: dataset de raÃ§as por espÃ©cie (cÃ£o: 44 raÃ§as, gato: 20 raÃ§as). Populares priorizadas. Cache em memÃ³ria. Output JSON para datalist.
  - `DPS_Duplicate_Detector`: detecÃ§Ã£o de duplicatas APENAS por telefone (conforme legado v1.3.0). Admin override suportado.
  - `DPS_Recaptcha_Service`: verificaÃ§Ã£o reCAPTCHA v3 server-side. Score threshold configurÃ¡vel. LÃª options do legado.
  - `DPS_Email_Confirmation_Service`: token UUID 48h com `wp_generate_uuid4()`. Envio via `DPS_Communications_API` ou `wp_mail()`. ConfirmaÃ§Ã£o + limpeza de tokens.
- **Handler**:
  - `DPS_Registration_Handler`: processamento completo â€” reCAPTCHA â†’ anti-spam â†’ validaÃ§Ã£o â†’ duplicata â†’ criaÃ§Ã£o cliente â†’ hooks (Loyalty) â†’ criaÃ§Ã£o pets â†’ email confirmaÃ§Ã£o. 100% independente do legado.
- **Templates nativos DPS Signature**:
  - `form-main.php`: expandido com seÃ§Ãµes, honeypot, reCAPTCHA, marketing opt-in, hook bridge `dps_registration_after_fields`.
  - `form-client-data.php`: nome, email, telefone, CPF (com mask), endereÃ§o (com coords ocultas). Sticky form com erros por campo.
  - `form-pet-data.php`: repeater JavaScript para mÃºltiplos pets. Nome, espÃ©cie, raÃ§a (datalist dinÃ¢mico), porte, observaÃ§Ãµes.
  - `form-success.php`: confirmaÃ§Ã£o com CTA para agendamento.
  - `form-duplicate-warning.php`: aviso de duplicata com checkbox de override (admin).
  - `form-error.php`: exibiÃ§Ã£o de erros (lista ou parÃ¡grafo).
- **Module atualizado**:
  - `DPS_Frontend_Registration_V2_Module`: processa POST submissions via handler, renderiza breed data, reCAPTCHA v3, booking URL. Setters para DI tardia de handler/breed/recaptcha.
- **JavaScript nativo expandido** (`registration-v2.js`):
  - Pet repeater (add/remove/reindex)
  - Breed datalist dinÃ¢mico (espÃ©cie â†’ raÃ§as)
  - Phone mask `(XX) XXXXX-XXXX`
  - CPF mask `XXX.XXX.XXX-XX`
  - Client-side validation com scroll para primeiro erro
  - reCAPTCHA v3 execute antes do submit
  - Submit loader + alerts dismissÃ­veis
- **CSS expandido** (`registration-v2.css`): grid layout para campos, pet entry cards, repeater actions, success state, compact mode, responsive.
- **Bootstrap atualizado**: carrega validators, services, handler com DI completa.

**Frontend Add-on v2.2.0 â€” Fase 7.3 Booking V2 (ImplementaÃ§Ã£o Nativa)**

- **Services**:
  - `DPS_Appointment_Service`: CRUD completo para post type `dps_agendamento`. Cria agendamentos com 16+ metas padronizadas (client, pets, services, pricing, extras). VerificaÃ§Ã£o de conflitos por data/hora. Busca por cliente. Versionamento via `_dps_appointment_version`.
  - `DPS_Booking_Confirmation_Service`: confirmaÃ§Ã£o via transient (`dps_booking_confirmation_{user_id}`, TTL 5min). Store, retrieve, clear, isConfirmed.
- **Validators**:
  - `DPS_Booking_Validator`: validaÃ§Ã£o multi-step (5 steps) â€” cliente (ID obrigatÃ³rio), pets (array nÃ£o vazio), serviÃ§os (array nÃ£o vazio), data/hora (formato, passado, conflitos), confirmaÃ§Ã£o. ValidaÃ§Ã£o de extras (TaxiDog preÃ§o â‰¥ 0, Tosa preÃ§o â‰¥ 0 e ocorrÃªncia > 0 quando habilitada). Tipo `past` permite datas passadas.
- **Handler**:
  - `DPS_Booking_Handler`: pipeline completo â€” beforeProcess â†’ validaÃ§Ã£o â†’ extras â†’ buildMeta â†’ criaÃ§Ã£o appointment â†’ confirmaÃ§Ã£o transient â†’ hook CRÃTICO `dps_base_after_save_appointment` (8 add-ons: Stock, Payment, Groomers, Calendar, Communications, Push, Services, Booking) â†’ afterProcess. 100% independente do legado.
- **AJAX Endpoints** (`DPS_Booking_Ajax`):
  - `dps_booking_search_client`: busca clientes por telefone (LIKE com dÃ­gitos normalizados). Retorna id, name, phone, email.
  - `dps_booking_get_pets`: lista pets do cliente com paginaÃ§Ã£o. Retorna id, name, species, breed, size.
  - `dps_booking_get_services`: serviÃ§os ativos com preÃ§os por porte (base, small, medium, large, category).
  - `dps_booking_get_slots`: horÃ¡rios disponÃ­veis (08:00-18:00, 30min) com verificaÃ§Ã£o de conflitos.
  - `dps_booking_validate_step`: validaÃ§Ã£o server-side por step com sanitizaÃ§Ã£o contextual.
  - Todos com nonce + capability check (`manage_options` OU `dps_manage_clients` OU `dps_manage_pets` OU `dps_manage_appointments`).
- **Templates nativos DPS Signature (Wizard 5 steps)**:
  - `form-main.php`: expandido com renderizaÃ§Ã£o dinÃ¢mica de steps via template engine, suporte a success state.
  - `step-client-selection.php`: Step 1 â€” busca de cliente por telefone via AJAX, cards selecionÃ¡veis, hidden input client_id.
  - `step-pet-selection.php`: Step 2 â€” multi-select de pets com checkboxes, paginaÃ§Ã£o "Carregar mais".
  - `step-service-selection.php`: Step 3 â€” seleÃ§Ã£o de serviÃ§os com preÃ§os R$, total acumulado.
  - `step-datetime-selection.php`: Step 4 â€” date picker, time slots via AJAX, seletor de tipo (simple/subscription/past), notas.
  - `step-extras.php`: Step 5a â€” TaxiDog (checkbox + preÃ§o), Tosa (subscription only, checkbox + preÃ§o + frequÃªncia).
  - `step-confirmation.php`: Step 5b â€” resumo read-only com hidden inputs para submissÃ£o.
  - `form-success.php`: tela de confirmaÃ§Ã£o com dados do agendamento e CTA.
- **Module atualizado**:
  - `DPS_Frontend_Booking_V2_Module`: processa POST via handler, sanitiza dados (client, pets, services, datetime, extras), capability check, setters para DI tardia de handler/confirmationService.
- **JavaScript nativo expandido** (`booking-v2.js`):
  - Wizard state machine com navegaÃ§Ã£o entre steps (next/prev)
  - AtualizaÃ§Ã£o dinÃ¢mica de barra de progresso e URL (?step=X via pushState)
  - AJAX via Fetch API para busca de clientes, pets, serviÃ§os e horÃ¡rios
  - Debounce na busca de telefone (300ms)
  - Running total dinÃ¢mico na seleÃ§Ã£o de serviÃ§os
  - Toggle de extras (TaxiDog/Tosa) com visibilidade condicional
  - Builder de resumo para confirmaÃ§Ã£o
  - XSS mitigation via escapeHtml()
  - Zero jQuery
- **CSS expandido** (`booking-v2.css`): step containers, search UI, selectable cards grid, time slot grid, extras cards, summary sections, running total bar, appointment type selector, loading states, navigation actions, compact mode, responsive, dark theme, `prefers-reduced-motion`.
- **Bootstrap atualizado**: carrega validators, services, handler, AJAX com DI completa. `wp_localize_script` para ajaxUrl e nonce.

**Frontend Add-on v2.3.0 â€” Fase 7.4 CoexistÃªncia e MigraÃ§Ã£o**

- **Guia de MigraÃ§Ã£o** (`docs/implementation/FRONTEND_V2_MIGRATION_GUIDE.md`):
  - Guia passo a passo completo em 7 etapas para migrar de v1 (dual-run) para v2 (nativo)
  - ComparaÃ§Ã£o detalhada de features v1 vs v2 para Registration e Booking
  - Checklist de compatibilidade com 12 itens de verificaÃ§Ã£o
  - Plano de rollback instantÃ¢neo (swap de flags, zero perda de dados)
  - Troubleshooting para problemas comuns de migraÃ§Ã£o
  - ConfiguraÃ§Ã£o via WP-CLI para automaÃ§Ã£o de migraÃ§Ã£o
- **Status de CoexistÃªncia v1/v2** (Settings Admin UI):
  - SeÃ§Ã£o "Status de CoexistÃªncia v1 / v2" na aba Frontend do painel de configuraÃ§Ãµes
  - Indicador visual por mÃ³dulo (Cadastro/Agendamento): 4 estados distintos com cores e Ã­cones
    - âœ… Somente v2 â€” migraÃ§Ã£o concluÃ­da (verde)
    - âš¡ CoexistÃªncia â€” v1 + v2 ativos (Ã¢mbar)
    - ðŸ“¦ Somente v1 â€” considere migrar (neutro)
    - â¸ï¸ Nenhum ativo (muted)
  - Link direto para guia de migraÃ§Ã£o
- **Telemetria v2** (jÃ¡ implementada):
  - Contadores por mÃ³dulo (v1 e v2) via `DPS_Frontend_Logger::track()`
  - Exibidos na aba Settings com comparaÃ§Ã£o v1 vs v2
  - DecisÃµes de depreciaÃ§Ã£o futura baseadas nos contadores

**Frontend Add-on v2.4.0 â€” Fase 7.5 DepreciaÃ§Ã£o do Dual-Run**

- **Aviso de depreciaÃ§Ã£o admin** (`DPS_Frontend_Deprecation_Notice`):
  - Banner administrativo exibido quando mÃ³dulos v1 (registration e/ou booking) estÃ£o ativos
  - Aviso dismissÃ­vel por usuÃ¡rio (transient 30 dias)
  - Dismiss via AJAX com nonce + capability check (`manage_options`)
  - Mensagem inclui lista dos mÃ³dulos v1 ativos e link para guia de migraÃ§Ã£o
  - SÃ³ exibe para administradores (capability `manage_options`)
- **DocumentaÃ§Ã£o visual completa** (`docs/screenshots/2026-02-12/`):
  - 7 screenshots PNG: Registration V2, Booking V2 (steps 3 e 5), sucesso, login obrigatÃ³rio, aviso depreciaÃ§Ã£o, status coexistÃªncia
  - Preview HTML interativo com todas as telas V2
  - Documento de registro `SCREENSHOTS_2026-02-12.md` com contexto, antes/depois e lista de arquivos
- **Bootstrap atualizado**: carrega `DPS_Frontend_Deprecation_Notice` e inicializa apÃ³s boot do add-on

**Booking Add-on v1.3.0 â€” MigraÃ§Ã£o DPS Signature e Melhorias de SeguranÃ§a**

- **ValidaÃ§Ã£o granular de ediÃ§Ã£o de agendamentos**: MÃ©todo `can_edit_appointment()` verifica se usuÃ¡rio pode editar agendamento especÃ­fico (criador ou admin).
- **Suporte a `prefers-reduced-motion`**: AnimaÃ§Ã£o de confirmaÃ§Ã£o respeita preferÃªncia de acessibilidade do usuÃ¡rio.

**Design System DPS Signature (Docs + Design Tokens v2.0)**

- **Design tokens CSS** (`dps-design-tokens.css`): Arquivo centralizado com 200+ CSS custom properties implementando o sistema completo do DPS Signature â€” cores (primary/secondary/tertiary/error/success/warning + surface containers), tipografia (escala DPS Signature: Display/Headline/Title/Body/Label), formas (escala de arredondamento: 0â€“4â€“8â€“12â€“16â€“28â€“pill), elevaÃ§Ã£o tonal (6 nÃ­veis), motion (easing expressivo com springs + duraÃ§Ã£o), espaÃ§amento e state layers.
- **Suporte a tema escuro** via `[data-dps-theme="dark"]` com paleta completa de cores invertidas.
- **Aliases de compatibilidade** para migraÃ§Ã£o gradual dos tokens legados (`--dps-bg-*`, `--dps-accent`, etc.) para os novos tokens DPS Signature.
- **Demo interativo** (`visual-comparison.html`): Preview completo do design system com todos os componentes, toggle claro/escuro e animaÃ§Ãµes expressivas.

#### Changed (Alterado)

**Client Portal Add-on â€” RevisÃ£o UX/UI da PÃ¡gina Principal e NavegaÃ§Ã£o por Abas**

- **Shell principal refinado** no shortcode `[dps_client_portal]` (estado autenticado): header reorganizado em bloco de conteÃºdo + aÃ§Ãµes globais (avaliar/sair), com hierarquia visual e espaÃ§amento mais claros.
- **NavegaÃ§Ã£o por abas com acessibilidade reforÃ§ada**:
  - foco visÃ­vel consistente (`:focus-visible`),
  - relacionamento ARIA explÃ­cito (`tablist`, `tab`, `tabpanel`, `aria-controls`, `aria-labelledby`, `aria-selected`),
  - suporte a abas desabilitadas sem quebrar extensÃµes.
- **InteraÃ§Ã£o por teclado aprimorada**: setas esquerda/direita, Home/End e ativaÃ§Ã£o com Enter/EspaÃ§o.
- **PersistÃªncia e navegaÃ§Ã£o**: aba ativa preservada por hash (`#tab-*`) com sincronizaÃ§Ã£o em refresh/back.
- **Feedback leve de troca de abas**: indicador visual/textual de carregamento sem alterar o conteÃºdo interno dos painÃ©is.
- **Mobile**: tabs mantÃªm labels visÃ­veis e overflow horizontal controlado para melhor descobribilidade.
- **Compatibilidade preservada**: filtro `dps_portal_tabs` e hooks `dps_portal_before_*_content` / `dps_portal_after_*_content` mantidos sem alteraÃ§Ã£o de assinatura.

**Booking Add-on v1.3.0 â€” MigraÃ§Ã£o DPS Signature e Melhorias de SeguranÃ§a**

- **MigraÃ§Ã£o completa para DPS Signature tokens** (`booking-addon.css`):
  - 37 cores hardcoded â†’ tokens DPS Signature (`--dps-color-*`)
  - 5 border-radius â†’ shape tokens (`--dps-shape-*`)
  - 3 transiÃ§Ãµes â†’ motion tokens (`--dps-motion-*`)
  - 3 sombras â†’ elevation tokens (`--dps-elevation-*`)
  - 24 valores tipogrÃ¡ficos â†’ escala DPS Signature (`--dps-typescale-*`)
  - Semantic mapping em `.dps-booking-wrapper` para customizaÃ§Ã£o local
- **Enfileiramento condicional de design tokens**: DependÃªncia de `dps-design-tokens.css` via check de `DPS_BASE_URL`.
- **OtimizaÃ§Ã£o de performance** (batch queries):
  - Fix N+1: owners de pets agora fetched em batch (reduÃ§Ã£o de 100+ queries para 1)
  - Prepared for future optimization of client pagination
- **Melhorias de acessibilidade**:
  - `aria-hidden="true"` adicionado a todos emojis decorativos
  - DocumentaÃ§Ã£o phpcs para parÃ¢metros GET read-only validados por capability

- **`VISUAL_STYLE_GUIDE.md` v1.2 â†’ v2.0**: Redesenhado integralmente como design system baseado no DPS Signature â€” sistema de cores com papÃ©is semÃ¢nticos (color roles), escala tipogrÃ¡fica DPS Signature (5 papÃ©is Ã— 3 tamanhos), sistema de formas do projeto, elevaÃ§Ã£o tonal, motion com springs, state layers, novos componentes e guia de migraÃ§Ã£o do sistema legado.
- **`FRONTEND_DESIGN_INSTRUCTIONS.md` v1.0 â†’ v2.0**: Atualizado com metodologia DPS Signature â€” perfis de contexto para admin e portal, princÃ­pios de design do sistema, state layers, shape system, elevation tonal, motion com easing de springs, exemplos prÃ¡ticos adaptados ao contexto pet shop e checklist atualizado com tokens DPS Signature.

**Front-end de ConfiguraÃ§Ãµes do Sistema (Base v2.6.0)**

- **CSS dedicado para configuraÃ§Ãµes** (`dps-settings.css`): Folha de estilos exclusiva para a pÃ¡gina de configuraÃ§Ãµes com layout melhorado, barra de status, campo de busca, navegaÃ§Ã£o por abas aprimorada, indicador de alteraÃ§Ãµes nÃ£o salvas e design responsivo completo.
- **JavaScript dedicado para configuraÃ§Ãµes** (`dps-settings.js`): NavegaÃ§Ã£o client-side entre abas sem recarregar a pÃ¡gina, busca em tempo real com destaque visual dos resultados encontrados, rastreamento de alteraÃ§Ãµes nÃ£o salvas com aviso ao sair da pÃ¡gina.
- **Barra de status**: Exibe contagem de categorias de configuraÃ§Ã£o disponÃ­veis e nome do usuÃ¡rio logado.
- **Busca de configuraÃ§Ãµes**: Campo de pesquisa que filtra e destaca configuraÃ§Ãµes em todas as abas simultaneamente, com indicador visual de "sem resultados" e destaque nas abas que contÃªm resultados.
- **Indicador de alteraÃ§Ãµes nÃ£o salvas**: DetecÃ§Ã£o automÃ¡tica de modificaÃ§Ãµes em formulÃ¡rios com barra de aÃ§Ã£o fixa (sticky) e aviso `beforeunload` para prevenir perda de dados.
- **Enfileiramento automÃ¡tico de assets**: CSS e JS de configuraÃ§Ãµes sÃ£o carregados apenas na pÃ¡gina de configuraÃ§Ãµes, com versionamento automÃ¡tico por data de modificaÃ§Ã£o do arquivo.

**Redesign da PÃ¡gina de Detalhes do Cliente (Base v1.3.0)**

- **Novo layout de cabeÃ§alho**: ReorganizaÃ§Ã£o visual com navegaÃ§Ã£o separada, tÃ­tulo com badges e aÃ§Ãµes primÃ¡rias destacadas.
- **Painel de AÃ§Ãµes RÃ¡pidas**: Nova seÃ§Ã£o dedicada para links de consentimento, atualizaÃ§Ã£o de perfil e outras aÃ§Ãµes externas, com visual moderno e organizado.
- **Hook para badges no tÃ­tulo**: `dps_client_page_header_badges` permite que add-ons de fidelidade adicionem indicadores de nÃ­vel/status ao lado do nome do cliente.
- **SeÃ§Ã£o de Notas Internas**: Campo de texto editÃ¡vel para anotaÃ§Ãµes administrativas sobre o cliente (visÃ­vel apenas para a equipe).
  - Salvamento via AJAX com feedback visual
  - Armazenado em `client_internal_notes` meta
  - Estilo diferenciado (amarelo) para destacar que sÃ£o notas internas

**Melhorias na PÃ¡gina de Detalhes do Cliente (Base v1.2.0)**

- **Data de cadastro do cliente**: Agora exibida nos cards de resumo ("Cliente Desde") e na seÃ§Ã£o de Dados Pessoais para visualizaÃ§Ã£o do tempo de relacionamento.
- **Hooks de extensÃ£o para add-ons na pÃ¡gina do cliente**: Novos hooks permitem que add-ons injetem seÃ§Ãµes personalizadas:
  - `dps_client_page_after_personal_section`: apÃ³s dados pessoais
  - `dps_client_page_after_contact_section`: apÃ³s contato e redes sociais
  - `dps_client_page_after_pets_section`: apÃ³s lista de pets
  - `dps_client_page_after_appointments_section`: apÃ³s histÃ³rico de atendimentos
- **AutorizaÃ§Ã£o de fotos com badge visual**: Campo de autorizaÃ§Ã£o para fotos agora exibe badges coloridos (âœ“ Autorizado em verde, âœ• NÃ£o Autorizado em vermelho) para melhor visibilidade.

**Melhorias de UI/UX e Responsividade no FormulÃ¡rio de Cadastro PÃºblico (Registration Add-on v1.3.1)**

- **Novo breakpoint para telas muito pequenas (< 375px)**: Adicionado suporte para dispositivos mÃ³veis com telas extra pequenas (ex: iPhone SE, dispositivos antigos).
  - Padding e espaÃ§amento reduzidos para melhor aproveitamento do espaÃ§o
  - Tamanhos de fonte ajustados mantendo legibilidade
  - Border-radius menores para visual mais compacto
- **Indicadores de campos obrigatÃ³rios nos pets**: Campos de EspÃ©cie, Porte e Sexo agora exibem asterisco vermelho (*) indicando obrigatoriedade.
  - Aplicado tanto no fieldset inicial quanto nos pets adicionados dinamicamente via JavaScript
- **Altura mÃ­nima de inputs para melhor usabilidade mÃ³vel**: Inputs agora tÃªm altura mÃ­nima de 48px, melhorando a Ã¡rea de toque para dispositivos touch.

**Consentimento de Tosa com MÃ¡quina (Client Portal + Base)**

- **PÃ¡gina pÃºblica de consentimento via token**: Novo shortcode `[dps_tosa_consent]` para coletar consentimento com preenchimento automÃ¡tico e registro por cliente.
- **GeraÃ§Ã£o de link pelo administrador**: BotÃ£o no header do cliente para gerar link, copiar e enviar ao tutor.
- **RevogaÃ§Ã£o registrada**: Consentimento vÃ¡lido atÃ© revogaÃ§Ã£o manual pelo administrador.
- **Indicadores operacionais**: Badge no formulÃ¡rio e na lista de agendamentos, com alerta de ausÃªncia ao salvar.
- **Logging de auditoria**: Eventos de geraÃ§Ã£o de link, revogaÃ§Ã£o e registro de consentimento agora sÃ£o registrados no DPS_Logger para rastreabilidade.
- **CÃ³digos de erro estruturados**: Respostas AJAX agora incluem cÃ³digos de erro padronizados (NONCE_INVALIDO, SEM_PERMISSAO, CLIENTE_NAO_ENCONTRADO) para melhor integraÃ§Ã£o.
- **FunÃ§Ã£o helper global**: `dps_get_tosa_consent_page_url()` para obter URL da pÃ¡gina de consentimento.
- **Acessibilidade aprimorada**: FormulÃ¡rio de consentimento com atributos ARIA (aria-label, aria-labelledby, aria-required), autocomplete semÃ¢ntico e navegaÃ§Ã£o por teclado melhorada.
- **CSS externalizado**: Estilos movidos para arquivo separado (`tosa-consent-form.css`) para melhor cache e manutenibilidade.
- **UX mobile otimizada**: Ãrea de toque aumentada em checkboxes, inputs com altura mÃ­nima de 48px, breakpoints responsivos (480px, 768px).

#### Changed (Alterado)

**Melhoria de UI no Painel de AÃ§Ãµes RÃ¡pidas (Base v1.3.1)**

- **ReorganizaÃ§Ã£o do painel de AÃ§Ãµes RÃ¡pidas**: Elementos que antes estavam misturados agora sÃ£o agrupados por funcionalidade em cards separados:
  - **Grupo "Consentimento de Tosa"**: Status badge, botÃµes de copiar/gerar link e revogar organizados em um card dedicado
  - **Grupo "AtualizaÃ§Ã£o de Perfil"**: BotÃµes de copiar/gerar link organizados em um card dedicado
- **Textos mais concisos**: BotÃµes com textos reduzidos ("Copiar" em vez de "Copiar Link", "Gerar Link" em vez de "Link de Consentimento")
- **Badges de status mais compactos**: "Ativo", "Pendente", "Revogado" em vez de "Consentimento ativo", etc.
- **Layout responsivo melhorado**: Estilos especÃ­ficos para mobile (< 600px) com botÃµes em coluna e largura total
- **Novo estilo `.dps-btn-action--danger`**: BotÃ£o vermelho para aÃ§Ãµes destrutivas como "Revogar"

**Refinamentos visuais conforme Guia de Estilo (Registration Add-on v1.3.1)**

- **Bordas padronizadas para 1px**: Alteradas bordas de 2px para 1px em inputs, pet fieldsets, summary box, botÃ£o secundÃ¡rio e botÃ£o "Adicionar pet", seguindo o guia de estilo visual do DPS.
- **BotÃ£o "Adicionar pet" com borda consistente**: Alterado de `border: 2px dashed` para `border: 1px dashed` para maior consistÃªncia visual.
- **Padding de inputs aumentado**: Alterado de 12px para 14px vertical, resultando em Ã¡rea de toque mais confortÃ¡vel (48px total).

**Link de AtualizaÃ§Ã£o de Perfil para Clientes (Client Portal v2.5.0)**

- **BotÃ£o "Link de AtualizaÃ§Ã£o" na pÃ¡gina do cliente**: Administradores agora podem gerar um link exclusivo para que o cliente atualize seus prÃ³prios dados e de seus pets.
  - BotÃ£o disponÃ­vel no header da pÃ¡gina de detalhes do cliente
  - Link vÃ¡lido por 7 dias (token type: `profile_update`)
  - Copia automaticamente para a Ã¡rea de transferÃªncia
  - Pode ser enviado via WhatsApp ou Email pelo administrador
- **FormulÃ¡rio pÃºblico de atualizaÃ§Ã£o de perfil**: Clientes podem atualizar:
  - Dados pessoais (nome, CPF, data de nascimento)
  - Contato (telefone, email, Instagram, Facebook)
  - EndereÃ§o e preferÃªncias
  - Dados de pets existentes (espÃ©cie, raÃ§a, porte, peso, cuidados especiais)
  - Cadastrar novos pets
- **Design responsivo e intuitivo**: FormulÃ¡rio com interface limpa, cards colapsÃ¡veis para pets, validaÃ§Ã£o de campos obrigatÃ³rios
- **Hook `dps_client_page_header_actions`**: Novo hook no header da pÃ¡gina do cliente para extensÃµes adicionarem aÃ§Ãµes personalizadas
- **Novo token type `profile_update`**: Suporte no Token Manager para tokens de atualizaÃ§Ã£o de perfil com expiraÃ§Ã£o de 7 dias

**CatÃ¡logo Completo de ServiÃ§os de Banho e Tosa - RegiÃ£o SP (v1.6.1)**

- **30+ serviÃ§os prÃ©-configurados com valores de mercado SP 2024**: Lista completa de serviÃ§os tÃ­picos de pet shop com preÃ§os diferenciados por porte (pequeno/mÃ©dio/grande):
  - **ServiÃ§os PadrÃ£o**: Banho (R$ 50-120), Banho e Tosa (R$ 100-230), Tosa HigiÃªnica (R$ 40-80)
  - **OpÃ§Ãµes de Tosa**: Tosa MÃ¡quina (R$ 65-140), Tosa Tesoura (R$ 85-180), Tosa da RaÃ§a (R$ 120-280), Corte Estilizado (R$ 135-300)
  - **PreparaÃ§Ã£o da Pelagem**: RemoÃ§Ã£o de NÃ³s (leve/moderado/severo), DesembaraÃ§o Total
  - **Tratamentos**: Banho TerapÃªutico/OzÃ´nio, Banho Medicamentoso, Banho Antipulgas, Tratamento DermatolÃ³gico
  - **Pelagem e Pele**: HidrataÃ§Ã£o, HidrataÃ§Ã£o Profunda, RestauraÃ§Ã£o Capilar, CauterizaÃ§Ã£o
  - **Cuidados Adicionais**: Corte de Unhas (R$ 18-35), Limpeza de Ouvido, EscovaÃ§Ã£o Dental, Limpeza de GlÃ¢ndulas Anais, Tosa de Patas
  - **Extras/Mimos**: Perfume Premium, LaÃ§o/Gravatinha, Bandana, Tintura/ColoraÃ§Ã£o
  - **Transporte**: TaxiDog (Leva e Traz) R$ 30-45
  - **Pacotes**: Pacote Completo, Pacote Spa
- **DuraÃ§Ãµes por porte**: Cada serviÃ§o inclui tempo estimado de execuÃ§Ã£o para cada porte de pet
- **Ativo por padrÃ£o**: Todos os serviÃ§os sÃ£o criados como ativos para ediÃ§Ã£o imediata pelo administrador

**SeÃ§Ã£o de Tosa no FormulÃ¡rio de Agendamento via Shortcode (v1.2.1)**

- **Card de tosa no shortcode `[dps_booking_form]`**: Adicionada a mesma seÃ§Ã£o de tosa com design card-based que foi implementada no formulÃ¡rio de agendamento do Painel de GestÃ£o DPS pela PR #498.
  - Card com toggle switch para ativar/desativar tosa
  - Campo de valor da tosa com prefixo R$
  - Seletor de ocorrÃªncia (em qual atendimento a tosa serÃ¡ realizada)
  - Design consistente com o card de TaxiDog jÃ¡ existente no formulÃ¡rio
  - Estilos reutilizam classes CSS do plugin base (`dps-tosa-section`, `dps-tosa-card`, etc.)
  - Visibilidade condicional via JavaScript (aparece apenas para agendamentos de assinatura)

**BotÃ£o de Reagendamento nas Abas Simplificadas da Agenda (v1.1.0)**

- **Coluna "AÃ§Ãµes" nas abas da agenda**: Adicionada nova coluna "AÃ§Ãµes" nas trÃªs abas simplificadas da agenda (VisÃ£o RÃ¡pida, OperaÃ§Ã£o, Detalhes).
  - BotÃ£o "ðŸ“… Reagendar" disponÃ­vel em cada linha de atendimento
  - Permite alterar a data e/ou horÃ¡rio de um agendamento diretamente pela interface
  - Modal de reagendamento com seletor de data e hora
  - Registro automÃ¡tico no histÃ³rico do agendamento
  - Dispara hook `dps_appointment_rescheduled` para integraÃ§Ãµes
- **Funcionalidade jÃ¡ existente agora acessÃ­vel**: O backend de reagendamento jÃ¡ existia (`quick_reschedule_ajax`), mas o botÃ£o nÃ£o estava visÃ­vel nas abas mais utilizadas do dia-a-dia.
- **MÃ©todo helper `render_reschedule_button()`**: Criado mÃ©todo privado para renderizar o botÃ£o de reagendamento, evitando duplicaÃ§Ã£o de cÃ³digo em 4 locais diferentes.

**Modo Administrador no Chat PÃºblico de IA (v1.8.0)**

- **Modo Administrador com acesso expandido**: O shortcode `[dps_ai_public_chat]` agora detecta automaticamente quando um administrador (capability `manage_options`) estÃ¡ logado e ativa o modo sistema:
  - Acesso a dados de clientes cadastrados (total, ativos nos Ãºltimos 90 dias)
  - Acesso a estatÃ­sticas de pets registrados
  - Acesso a informaÃ§Ãµes de agendamentos (hoje, semana, mÃªs)
  - Acesso a dados financeiros (faturamento do mÃªs, valores pendentes)
  - InformaÃ§Ãµes de versÃ£o e status do sistema
- **UI/UX diferenciada para administradores**:
  - Badge visual "ðŸ” Admin" no cabeÃ§alho do chat
  - Indicador "Modo Sistema" na toolbar
  - Cor temÃ¡tica roxa (#7c3aed) para distinguir do modo visitante
  - FAQs especÃ­ficas para gestÃ£o (clientes, agendamentos, faturamento)
  - Mensagem de boas-vindas com lista de capacidades disponÃ­veis
  - Disclaimer informando sobre acesso a dados sensÃ­veis
- **SeguranÃ§a reforÃ§ada**:
  - ValidaÃ§Ã£o de capability no backend (nÃ£o pode ser burlada via frontend)
  - Rate limiting diferenciado: 30/min e 200/hora para admins (vs 10/min e 60/hora para visitantes)
  - Logs de auditoria para todas as consultas em modo admin
  - Visitantes NUNCA recebem dados de clientes, financeiros ou sensÃ­veis
- **Prompt de sistema especÃ­fico**: Administradores recebem prompt expandido com instruÃ§Ãµes para fornecer dados do sistema
- **Limite de caracteres expandido**: 1000 caracteres para admins (vs 500 para visitantes)
- **Atributo `data-admin-mode`**: Indicador no HTML para debugging e extensibilidade

#### Changed (Alterado)

**Services Add-on - Melhorias de UI/UX e ValidaÃ§Ãµes (v1.6.0)**

- **Empty state com CTA**: A aba ServiÃ§os agora exibe botÃ£o "Cadastrar primeiro serviÃ§o" quando nÃ£o hÃ¡ serviÃ§os cadastrados, melhorando o fluxo de onboarding.
- **Indicador de campos obrigatÃ³rios**: Adicionada mensagem explicativa "* Campos obrigatÃ³rios" no formulÃ¡rio de cadastro/ediÃ§Ã£o de serviÃ§os.
- **EspaÃ§amento padronizado**: Valores por pet (assinatura) agora usam 16px de padding, alinhado com padrÃ£o visual global.
- **Link de cancelar ediÃ§Ã£o melhorado**: Estilizado como botÃ£o secundÃ¡rio vermelho para melhor feedback visual.
- **Acessibilidade em Ã­cones**: Adicionados atributos `aria-label` e `role="img"` nos Ã­cones de informaÃ§Ã£o.
- **Focus visible melhorado**: Estilos de foco visÃ­veis consistentes para acessibilidade de navegaÃ§Ã£o por teclado.

#### Security (SeguranÃ§a)

**Booking Add-on v1.3.0**

- **ValidaÃ§Ã£o de permissÃµes reforÃ§ada**: VerificaÃ§Ã£o de `can_access()` antes de renderizar seÃ§Ã£o de agendamentos.
- **ProteÃ§Ã£o contra ediÃ§Ã£o nÃ£o autorizada**: Novos checks garantem que usuÃ¡rio sÃ³ edita/duplica agendamentos prÃ³prios (exceto admins).
- **DocumentaÃ§Ã£o de seguranÃ§a**: ComentÃ¡rios phpcs explicam validaÃ§Ã£o de parÃ¢metros GET read-only.

#### Refactoring (Interno)

**Booking Add-on v1.3.0**

- **Arquivo CSS backup**: Original mantido em `booking-addon.css.backup` para referÃªncia durante migraÃ§Ã£o DPS Signature.

#### Fixed (Corrigido)

**Aviso de dependÃªncias Elementor nÃ£o registradas (Base v1.1.2)**

- **Sintoma**: Notice PHP "The script with the handle 'elementor-v2-editor-components' was enqueued with dependencies that are not registered" aparecia nos logs quando Elementor estava instalado.
- **Causa raiz identificada**: A classe `DPS_Cache_Control` verifica metadados de page builders (Elementor, YooTheme) para detectar shortcodes DPS e desabilitar cache. A chamada `get_post_meta()` para `_elementor_data` disparava hooks internos do Elementor que tentavam carregar scripts do editor no frontend, causando o aviso de dependÃªncias nÃ£o registradas.
- **SoluÃ§Ã£o implementada**:
  - Adicionada verificaÃ§Ã£o condicional antes de buscar metadados: `if ( defined( 'ELEMENTOR_VERSION' ) || class_exists( '\Elementor\Plugin' ) )`
  - Metadados do Elementor sÃ³ sÃ£o carregados quando o plugin estÃ¡ realmente ativo, evitando disparar hooks desnecessÃ¡rios
  - Mesmo padrÃ£o aplicado ao YooTheme para prevenÃ§Ã£o: `if ( class_exists( 'YOOtheme\Application' ) || function_exists( 'yootheme' ) )`
- **Impacto**: Elimina notices no log sem afetar a funcionalidade de detecÃ§Ã£o de shortcodes em pÃ¡ginas construÃ­das com page builders.

**PÃ¡gina de Consentimento de Tosa nÃ£o exibida (Base v1.2.3)**

- **Causa raiz identificada**: O formulÃ¡rio de consentimento de tosa nÃ£o era exibido porque a pÃ¡gina com o shortcode `[dps_tosa_consent]` nÃ£o existia. O sistema gerava um link para `/consentimento-tosa-maquina/` que resultava em erro 404.
- **SoluÃ§Ã£o implementada**:
  - PÃ¡gina de consentimento agora Ã© criada automaticamente na ativaÃ§Ã£o do plugin ou quando o primeiro link Ã© gerado.
  - Novo mÃ©todo estÃ¡tico `DPS_Tosa_Consent::create_consent_page()` cria a pÃ¡gina com shortcode correto.
  - MÃ©todo `get_consent_page_url()` refatorado para verificar existÃªncia da pÃ¡gina e criÃ¡-la se necessÃ¡rio.
  - Se a pÃ¡gina existir mas nÃ£o tiver o shortcode, ele Ã© adicionado automaticamente.
- **MÃ©todo de diagnÃ³stico**: `DPS_Tosa_Consent::diagnose_consent_page()` permite verificar status da pÃ¡gina.
- **DocumentaÃ§Ã£o atualizada**: CatÃ¡logo de shortcodes agora indica que a pÃ¡gina Ã© criada automaticamente.

**FormulÃ¡rio de Consentimento de Tosa nÃ£o exibindo versÃ£o atualizada (Base v1.2.2)**

- **Template do tema sobrescrevendo versÃ£o do plugin**: O sistema de templates permite que temas sobrescrevam arquivos via `dps-templates/`. Se o tema tinha uma versÃ£o antiga do template `tosa-consent-form.php`, a versÃ£o melhorada da PR #518 nÃ£o era exibida no site, mesmo apÃ³s o merge.
- **SoluÃ§Ã£o implementada**:
  - Template de consentimento agora forÃ§a uso da versÃ£o do plugin por padrÃ£o, garantindo que melhorias sejam imediatamente visÃ­veis.
  - Novo filtro `dps_allow_consent_template_override` para permitir que temas sobrescrevam quando desejado: `add_filter( 'dps_allow_consent_template_override', '__return_true' );`
  - Logging de warning quando override do tema Ã© detectado e ignorado, facilitando diagnÃ³stico de problemas.
- **Melhorias no sistema de templates**:
  - Novo filtro `dps_use_plugin_template` para forÃ§ar uso do template do plugin em qualquer template.
  - Nova action `dps_template_loaded` disparada quando um template Ã© carregado, Ãºtil para debug.
  - Nova funÃ§Ã£o `dps_get_template_path()` retorna caminho do template sem incluÃ­-lo.
  - Nova funÃ§Ã£o `dps_is_template_overridden()` verifica se um template estÃ¡ sendo sobrescrito pelo tema.

**Services Add-on - CorreÃ§Ã£o de ativaÃ§Ã£o do catÃ¡logo de serviÃ§os (v1.6.2)**

- **Hook de ativaÃ§Ã£o movido para arquivo wrapper**: O `register_activation_hook` que popula os 30+ serviÃ§os padrÃ£o estava incorretamente registrado dentro do construtor da classe `DPS_Services_Addon`, que sÃ³ era instanciada no hook `init`. Como o WordPress processa hooks de ativaÃ§Ã£o ANTES do hook `init` rodar, o callback nunca era executado, resultando em catÃ¡logo vazio mesmo apÃ³s desativar/reativar o plugin.
- **MÃ©todo `activate()` tornado estÃ¡tico**: O mÃ©todo agora pode ser chamado diretamente pelo hook de ativaÃ§Ã£o sem necessitar de uma instÃ¢ncia da classe.
- **Impacto**: Corrige o problema onde o catÃ¡logo de 30+ serviÃ§os implementado na PR #508 nÃ£o era refletido no site mesmo apÃ³s desativar/reativar o add-on.

#### Security (SeguranÃ§a)

**Services Add-on - ValidaÃ§Ãµes reforÃ§adas (v1.6.0)**

- **ValidaÃ§Ã£o de preÃ§os nÃ£o-negativos**: Todos os preÃ§os de serviÃ§os (pequeno/mÃ©dio/grande) agora sÃ£o validados para impedir valores negativos via `max(0, floatval(...))`.
- **ValidaÃ§Ã£o de duraÃ§Ãµes nÃ£o-negativas**: DuraÃ§Ãµes por porte agora impedem valores negativos.
- **SanitizaÃ§Ã£o de insumos**: Quantidade de insumos vinculados a serviÃ§os agora Ã© sanitizada com `sanitize_text_field()` antes da conversÃ£o numÃ©rica.
- **Total de agendamento nÃ£o-negativo**: Valor total do agendamento validado para impedir negativos.
- **Desconto de pacotes normalizado**: Desconto percentual na API de cÃ¡lculo de pacotes agora Ã© normalizado para intervalo 0-100 com `min(100, max(0, $discount))`.

- **Estrutura do header do chat pÃºblico**: Reorganizada para acomodar badge de admin e status lado a lado
- **MÃ©todo `check_rate_limit()`**: Agora aceita parÃ¢metro `$is_admin_mode` para aplicar limites diferenciados
- **MÃ©todo `get_ai_response()`**: Agora aceita parÃ¢metro `$is_admin_mode` para usar contexto e prompt apropriados
- **Demo HTML atualizado**: Nova seÃ§Ã£o demonstrando o Modo Administrador com todas as caracterÃ­sticas visuais

#### Security (SeguranÃ§a)

- **Isolamento de dados por role**: Implementada separaÃ§Ã£o completa de contexto entre visitantes e administradores
- **Auditoria de requisiÃ§Ãµes admin**: Todas as perguntas feitas por administradores sÃ£o registradas com user_login e user_id

**Sistema de PrevenÃ§Ã£o de Cache de PÃ¡ginas (v1.1.1)**

- **Nova classe `DPS_Cache_Control`**: Classe helper no plugin base que gerencia a prevenÃ§Ã£o de cache em todas as pÃ¡ginas do sistema DPS.
  - Envia headers HTTP de no-cache (`Cache-Control`, `Pragma`, `Expires`) para garantir que navegadores nÃ£o armazenem pÃ¡ginas em cache.
  - Define constantes `DONOTCACHEPAGE`, `DONOTCACHEDB`, `DONOTMINIFY`, `DONOTCDN` e `DONOTCACHEOBJECT` para compatibilidade com plugins de cache populares (WP Super Cache, W3 Total Cache, LiteSpeed Cache, etc.).
  - Detecta automaticamente pÃ¡ginas com shortcodes DPS via hook `template_redirect`.
  - Desabilita cache em todas as pÃ¡ginas administrativas do DPS via hook `admin_init`.
- **MÃ©todo `DPS_Cache_Control::force_no_cache()`**: MÃ©todo pÃºblico para forÃ§ar desabilitaÃ§Ã£o de cache em qualquer contexto.
- **MÃ©todo `DPS_Cache_Control::register_shortcode()`**: Permite que add-ons registrem shortcodes adicionais para prevenÃ§Ã£o automÃ¡tica de cache.
- **IntegraÃ§Ã£o em todos os shortcodes**: Todos os shortcodes do sistema agora chamam `DPS_Cache_Control::force_no_cache()` para garantir camada extra de proteÃ§Ã£o:
  - Base: `dps_base`, `dps_configuracoes`
  - Client Portal: `dps_client_portal`, `dps_client_login`
  - Agenda: `dps_agenda_page`, `dps_agenda_dashboard`
  - Groomers: `dps_groomer_portal`, `dps_groomer_login`, `dps_groomer_dashboard`, `dps_groomer_agenda`, `dps_groomer_review`, `dps_groomer_reviews`
  - Services: `dps_services_catalog`
  - Finance: `dps_fin_docs`
  - Registration: `dps_registration_form`
  - AI: `dps_ai_chat`

**FormulÃ¡rio de Cadastro - Terceira Etapa com PreferÃªncias de Produtos (v2.0.0)**

- **Terceira etapa no Registration Add-on**: O formulÃ¡rio de cadastro agora possui 3 etapas:
  1. Dados do Cliente
  2. Dados dos Pets
  3. PreferÃªncias e RestriÃ§Ãµes de Produtos
- **Campos de preferÃªncias por pet**: Para cada pet cadastrado, Ã© possÃ­vel definir:
  - PreferÃªncia de shampoo (hipoalergÃªnico, antissÃ©ptico, pelagem branca/escura, antipulgas, hidratante)
  - PreferÃªncia de perfume (suave, intenso, sem perfume/proibido, hipoalergÃªnico)
  - PreferÃªncia de adereÃ§os (lacinho, gravata, lenÃ§o, bandana, sem adereÃ§os)
  - Outras restriÃ§Ãµes de produtos (campo livre)
- **Novos meta fields do pet**: `pet_shampoo_pref`, `pet_perfume_pref`, `pet_accessories_pref`, `pet_product_restrictions`
- **Badge visual na agenda**: Pets com restriÃ§Ãµes de produtos exibem badge ðŸ§´ ao lado do nome com tooltip detalhado
- **Portal do Cliente**: Clientes podem visualizar e editar as preferÃªncias de produtos de seus pets
- **Admin Panel**: Nova seÃ§Ã£o "PreferÃªncias de Produtos" no formulÃ¡rio de ediÃ§Ã£o de pets
- **FormulÃ¡rio de Agendamento**: Exibe as preferÃªncias de produtos na seÃ§Ã£o de informaÃ§Ãµes do pet
- **~110 linhas de CSS** para estilizaÃ§Ã£o da nova etapa no formulÃ¡rio de cadastro
- **JavaScript atualizado** para navegaÃ§Ã£o entre 3 etapas com validaÃ§Ã£o e renderizaÃ§Ã£o dinÃ¢mica

**PÃ¡gina de ConfiguraÃ§Ãµes Frontend - Fase 6: Aba Agenda (v2.0.0)**

- **Aba Agenda (Agenda Add-on)**: Nova aba de configuraÃ§Ãµes para gerenciamento da agenda de atendimentos:
  - Selector de pÃ¡gina da agenda (`dps_agenda_page_id`)
  - ConfiguraÃ§Ã£o de capacidade por horÃ¡rio (manhÃ£ 08:00-11:59 e tarde 12:00-17:59)
  - Campo de endereÃ§o do petshop para GPS e navegaÃ§Ã£o (sincronizado com aba Empresa)
  - IntegraÃ§Ã£o com `DPS_Agenda_Capacity_Helper` para cÃ¡lculos de heatmap de lotaÃ§Ã£o
- **ValidaÃ§Ã£o e seguranÃ§a**: Nonce verification, capability check (`manage_options`), sanitizaÃ§Ã£o de inputs e log de auditoria
- **Responsividade**: Estilos herdados do sistema de abas garantem funcionamento em mobile

**PÃ¡gina de ConfiguraÃ§Ãµes Frontend - Fase 4: Abas de AutomaÃ§Ã£o (v2.0.0)**

- **Aba NotificaÃ§Ãµes (Push Add-on)**: Nova aba de configuraÃ§Ãµes para gerenciamento de relatÃ³rios automÃ¡ticos por email:
  - ConfiguraÃ§Ã£o de horÃ¡rio e destinatÃ¡rios para relatÃ³rio da manhÃ£ (agenda do dia)
  - ConfiguraÃ§Ã£o de horÃ¡rio e destinatÃ¡rios para relatÃ³rio financeiro do final do dia
  - ConfiguraÃ§Ã£o de dia da semana, horÃ¡rio e perÃ­odo de inatividade para relatÃ³rio semanal de pets inativos
  - Campos para integraÃ§Ã£o com Telegram (token do bot e chat ID)
  - Checkboxes individuais para ativar/desativar cada tipo de relatÃ³rio
  - VisualizaÃ§Ã£o do prÃ³ximo envio agendado para cada relatÃ³rio
- **Aba Financeiro - Lembretes (Finance Add-on)**: Nova aba de configuraÃ§Ãµes para gerenciamento de lembretes automÃ¡ticos de pagamento:
  - Checkbox para habilitar/desabilitar lembretes automÃ¡ticos
  - ConfiguraÃ§Ã£o de dias antes do vencimento para envio de lembrete preventivo
  - ConfiguraÃ§Ã£o de dias apÃ³s vencimento para envio de cobranÃ§a
  - Templates de mensagem personalizÃ¡veis com placeholders ({cliente}, {pet}, {data}, {valor}, {link}, {pix}, {loja})
- **ValidaÃ§Ã£o de formulÃ¡rios**: ValidaÃ§Ã£o de formato de horÃ¡rio (HH:MM), lista de emails e limites numÃ©ricos
- **Estilos CSS**: Novos estilos para campos de horÃ¡rio, selects, textareas e badges de prÃ³ximo agendamento

**FormulÃ¡rio de Agendamento - Melhorias de UX (v1.5.0)**

- **TaxiDog em card prÃ³prio**: O campo TaxiDog agora Ã© exibido em um card visual destacado com cores dinÃ¢micas (amarelo quando desativado, verde quando ativado).
- **Campo de valor TaxiDog simplificado**: Removido o label "Valor TaxiDog" quando o serviÃ§o Ã© selecionado, mostrando apenas o campo de valor com prefixo R$.
- **BotÃ£o "Adicionar desconto"**: Novo botÃ£o abaixo de "Adicionar ServiÃ§o Extra" para aplicar descontos ao agendamento simples, com campo de descriÃ§Ã£o e valor.
- **ExibiÃ§Ã£o de preÃ§os por porte**: Os serviÃ§os agora exibem os preÃ§os por porte (P, M, G) de forma identificada sem campo de ediÃ§Ã£o, facilitando a visualizaÃ§Ã£o.
- **Valores por pet em assinaturas**: Para agendamentos de assinatura com mÃºltiplos pets, cada pet Ã© listado com seu porte e campo individual para inserÃ§Ã£o do valor.
- **"Valor total da assinatura" reposicionado**: Campo movido para o final da seÃ§Ã£o, abaixo do botÃ£o "Adicionar ServiÃ§o Extra".
- **Desconto refletido no resumo**: O resumo do agendamento agora exibe o desconto aplicado e calcula corretamente o valor total.
- **Novos estilos visuais**: ~260 linhas de CSS para cards de serviÃ§o, seÃ§Ã£o de desconto, valores por pet em assinatura e preÃ§os por porte.

#### Changed (Alterado)

**FormulÃ¡rio de Agendamento - SimplificaÃ§Ã£o da SeÃ§Ã£o "Cliente e Pet(s)" (v1.5.0)**

- **Textos de orientaÃ§Ã£o removidos**: Removidos os textos "Selecione os pets do cliente escolhido..." e "Escolha um cliente para visualizar os pets disponÃ­veis.".
- **Ãrea de busca removida**: Removida a barra de busca de pets por nome, tutor ou raÃ§a, simplificando a interface.
- **Nome do proprietÃ¡rio oculto nos cards de pets**: Nos cards de seleÃ§Ã£o de pets, o nome do proprietÃ¡rio nÃ£o Ã© mais exibido, jÃ¡ que o cliente jÃ¡ foi selecionado acima.

**Client Portal Add-on - ModernizaÃ§Ã£o Completa da Aba Galeria (v3.2.0)**

- **Header moderno padronizado**: TÃ­tulo ðŸ“¸ com subtÃ­tulo descritivo seguindo padrÃ£o global DPS (`.dps-section-title`).
- **Cards de mÃ©tricas**: TrÃªs cards exibindo total de pets, fotos de perfil e fotos de atendimentos com destaque visual.
- **Filtro por pet**: BotÃµes para filtrar galeria por pet especÃ­fico ou visualizar todos, com estilo pill moderno.
- **Cards de pet organizados**: Cada pet em card prÃ³prio (`.dps-gallery-pet-card`) com header destacado e grid de fotos.
- **Grid de fotos moderno**: Layout responsivo com cards de foto (`.dps-gallery-photo`) incluindo overlay de zoom ao hover.
- **Suporte a fotos de atendimento**: Nova meta key `pet_grooming_photos` para armazenar fotos enviadas pelos administradores apÃ³s banho/tosa.
- **DiferenciaÃ§Ã£o visual**: Fotos de perfil com borda azul, fotos de atendimento com borda verde.
- **AÃ§Ãµes por foto**: BotÃµes de compartilhamento WhatsApp e download direto em cada item.
- **Lightbox integrado**: VisualizaÃ§Ã£o ampliada de fotos com fechamento por ESC ou clique fora, caption e botÃ£o de download.
- **Estado vazio orientador**: Mensagem amigÃ¡vel com Ã­cone e CTA para WhatsApp quando nÃ£o hÃ¡ pets cadastrados.
- **Nota informativa**: Texto explicativo sobre adiÃ§Ã£o de fotos pela equipe apÃ³s atendimentos.
- **Oito novos mÃ©todos helper**: `render_gallery_metrics()`, `render_gallery_pet_filter()`, `render_pet_gallery_card()`, `render_gallery_photo_item()`, `render_gallery_empty_state()`, `parse_grooming_photo()`.
- **~400 linhas de CSS**: Novos estilos para mÃ©tricas, filtros, cards de pet, grid de fotos, lightbox e responsividade mobile.
- **~170 linhas de JavaScript**: Handlers para filtro de pets (`handleGalleryFilter()`) e lightbox (`handleGalleryLightbox()`).

**Client Portal Add-on - ModernizaÃ§Ã£o Completa da Aba Agendamentos (v3.1.0)**

- **MÃ©tricas rÃ¡pidas no topo**: Dois cards destacando nÃºmero de prÃ³ximos agendamentos e total de atendimentos realizados.
- **SeÃ§Ã£o de PrÃ³ximos Agendamentos em cards**: Agendamentos futuros exibidos em cards visuais modernos com data destacada, horÃ¡rio, pet, serviÃ§os e status.
- **Badges de urgÃªncia**: Labels "Hoje!" e "AmanhÃ£" em destaque visual nos cards de agendamentos prÃ³ximos.
- **SeparaÃ§Ã£o lÃ³gica de conteÃºdo**: PrÃ³ximos agendamentos e histÃ³rico de atendimentos em seÃ§Ãµes distintas com hierarquia visual clara.
- **Oito novos mÃ©todos helper**: `render_appointments_metrics()`, `render_upcoming_appointments_section()`, `render_upcoming_appointment_card()`, `render_no_upcoming_state()`, `render_history_section()`, `render_history_row()`, `render_no_history_state()` e `get_status_class()`.
- **Badges de status coloridos**: Status de agendamentos com cores semÃ¢nticas (verde para confirmado/pago, amarelo para pendente, vermelho para cancelado).
- **Estados vazios orientadores**: Mensagens amigÃ¡veis com Ã­cones e CTA para WhatsApp quando nÃ£o hÃ¡ agendamentos.
- **~170 linhas de CSS**: Novos estilos para mÃ©tricas, cards de prÃ³ximos agendamentos, badges de status e responsividade mobile.

**Stock Add-on - ModernizaÃ§Ã£o Completa do Layout da Aba Estoque (v1.2.0)**

- **Header da seÃ§Ã£o padronizado**: TÃ­tulo com Ã­cone ðŸ“¦ e subtÃ­tulo descritivo seguindo padrÃ£o global DPS (`.dps-section-title`).
- **Layout empilhado com cards**: Novo sistema de cards `.dps-surface` empilhados verticalmente, seguindo padrÃ£o de outras abas (Pets, Clientes, ServiÃ§os).
- **Card de resumo/estatÃ­sticas**: Exibe total de itens, estoque OK e estoque baixo usando `.dps-inline-stats--panel` com badges de status.
- **Card de alertas crÃ­ticos**: Lista itens abaixo do mÃ­nimo em card destacado `.dps-surface--warning` com nome, quantidade e botÃ£o de ediÃ§Ã£o.
- **Card de inventÃ¡rio completo**: Tabela responsiva de todos os itens com toolbar de filtros e paginaÃ§Ã£o moderna.
- **Toolbar de filtros**: BotÃ£o para alternar entre "Ver todos" e "Mostrar apenas crÃ­ticos".
- **TrÃªs novos mÃ©todos helper**: `calculate_stock_stats()`, `render_critical_items_list()` e `render_stock_table()` para melhor organizaÃ§Ã£o do cÃ³digo.
- **~150 linhas de CSS**: Novos estilos para layout stack, inline-stats, lista de crÃ­ticos e toolbar.

**Stats Add-on - ModernizaÃ§Ã£o Completa do Layout da Aba EstatÃ­sticas (v1.5.0)**

- **Header da seÃ§Ã£o padronizado**: TÃ­tulo com Ã­cone ðŸ“Š e subtÃ­tulo descritivo seguindo padrÃ£o global DPS (`.dps-section-title`).
- **Layout empilhado com cards**: SubstituÃ­do `<details>` colapsÃ¡veis por cards `.dps-surface` empilhados verticalmente, seguindo padrÃ£o de outras abas (Pets, Clientes, ServiÃ§os).
- **Filtro de perÃ­odo em card dedicado**: Seletor de datas agora usa `.dps-surface--neutral` com tÃ­tulo ðŸ“… e layout responsivo melhorado.
- **MÃ©tricas financeiras com Ã­cones**: Cards de receita, despesas e lucro agora exibem emojis contextuais (ðŸ’µ, ðŸ’¸, ðŸ“Š, ðŸ“ˆ/ðŸ“‰).
- **Estados vazios amigÃ¡veis**: Mensagens para dados ausentes agora usam `.dps-stats-empty-state` com Ã­cones centralizados.
- **Tabela de inativos melhorada**: BotÃ£o WhatsApp agora usa estilo pill com background verde (#ecfdf5), melhor legibilidade da data e destaque para pets nunca atendidos.
- **~550 linhas de CSS refatorado**: Novo `stats-addon.css` v1.5.0 com layout stack, cards com hover animation, mÃ©tricas coloridas por tipo e espaÃ§amento consistente.

#### Changed (Alterado)

**Stock Add-on - Melhorias de UX (v1.2.0)**

- **DescriÃ§Ãµes explicativas em cada seÃ§Ã£o**: Todos os cards agora incluem `.dps-surface__description` explicando o propÃ³sito.
- **Tabela responsiva**: Tabela de inventÃ¡rio usa classes `.dps-table` com responsividade mobile (cards em telas < 640px).
- **PaginaÃ§Ã£o melhorada**: Layout flex com informaÃ§Ãµes Ã  esquerda e botÃµes Ã  direita, empilhando em mobile.
- **RemoÃ§Ã£o de estilos inline**: SubstituÃ­dos todos os `style=""` por classes CSS dedicadas.
- **BotÃµes com gradiente moderno**: `.button-primary` e `.button-secondary` agora herdam estilos globais do DPS.

**Stats Add-on - Melhorias de UX (v1.5.0)**

- **DescriÃ§Ãµes explicativas em cada seÃ§Ã£o**: Todos os cards de mÃ©tricas agora incluem `.dps-surface__description` explicando o propÃ³sito e fonte dos dados.
- **Cores semÃ¢nticas nas mÃ©tricas**: Assinaturas ativas (verde), pendentes (amarelo), valor em aberto (vermelho) seguindo padrÃ£o de cores de status do Visual Style Guide.
- **Hierarquia visual clara**: SeÃ§Ãµes organizadas em ordem de importÃ¢ncia: VisÃ£o Geral â†’ Indicadores AvanÃ§ados â†’ Financeiro â†’ Assinaturas â†’ ServiÃ§os â†’ Pets â†’ Inativos.
- **RemoÃ§Ã£o de estilos inline**: SubstituÃ­dos todos os `style=""` por classes CSS dedicadas para manutenibilidade e performance.
- **FormataÃ§Ã£o de cÃ³digo PHP**: Templates HTML agora usam indentaÃ§Ã£o consistente e comentÃ¡rios explicativos.

#### Fixed (Corrigido)

**Backup Add-on - CorreÃ§Ãµes de DocumentaÃ§Ã£o (v1.3.1)**

- **Erro de digitaÃ§Ã£o corrigido**: Corrigido "identific ou" â†’ "identificou" na documentaÃ§Ã£o de auditoria de seguranÃ§a (`docs/security/BACKUP_SECURITY_AUDIT.md`).

**Stats Add-on - CorreÃ§Ã£o de PHP Warning no Cache Invalidator (v1.2.1)**

- **PHP Warning corrigido**: O mÃ©todo `invalidate_on_post_delete()` assumia que o segundo parÃ¢metro era sempre um objeto WP_Post, mas o hook `trashed_post` passa `$post_id` (int) e `$previous_status` (string), causando warnings "Attempt to read property 'post_type' on string" ao mover posts para lixeira.
- **SeparaÃ§Ã£o de mÃ©todos**: Criados mÃ©todos separados para cada hook:
  - `invalidate_on_before_delete()`: Lida com o hook `before_delete_post` que recebe objeto WP_Post
  - `invalidate_on_trash()`: Lida com o hook `trashed_post` que recebe apenas post_id e busca o objeto internamente
- **ValidaÃ§Ã£o de tipo robusta**: Adicionada verificaÃ§Ã£o `instanceof WP_Post` no mÃ©todo `invalidate_on_before_delete()` para garantir que o parÃ¢metro Ã© um objeto vÃ¡lido antes de acessar propriedades.

**Agenda Add-on - ValidaÃ§Ã£o Defensiva no Google Calendar Sync (v2.0.1)**

- **ValidaÃ§Ã£o preventiva adicionada**: MÃ©todo `handle_delete_appointment()` agora valida que o segundo parÃ¢metro Ã© `instanceof WP_Post` antes de acessar propriedades, prevenindo potenciais warnings caso o hook seja usado incorretamente no futuro.
- **ConsistÃªncia com correÃ§Ã£o do Stats Add-on**: Aplica o mesmo padrÃ£o de validaÃ§Ã£o defensiva implementado no cache invalidator.

**AI Add-on - CorreÃ§Ã£o das ConfiguraÃ§Ãµes do Assistente de IA (v1.6.2)**

- **ConfiguraÃ§Ãµes nÃ£o editÃ¡veis corrigidas**: O uso de `wp_kses_post()` no Hub de IA (`class-dps-ai-hub.php`) removia elementos de formulÃ¡rio (`<input>`, `<select>`, `<textarea>`, `<form>`, `<button>`), tornando todas as configuraÃ§Ãµes apenas texto sem possibilidade de ediÃ§Ã£o.
- **Novo mÃ©todo `get_allowed_form_tags()`**: Criada lista personalizada de tags HTML permitidas que extende `wp_kses_post` com elementos de formulÃ¡rio essenciais para as configuraÃ§Ãµes funcionarem.
- **CorreÃ§Ã£o em todas as 7 abas do Hub**: ConfiguraÃ§Ãµes, Analytics, Conversas, Base de Conhecimento, Testar Base, Modo Especialista e Insights agora usam `wp_kses()` com lista segura em vez de bypass total ou `wp_kses_post()`.
- **Campos de WhatsApp nÃ£o salvavam**: Os campos de integraÃ§Ã£o WhatsApp Business (enabled, provider, tokens, etc.) estavam presentes no formulÃ¡rio mas nÃ£o eram processados no salvamento. Adicionados 11 campos ao mÃ©todo `maybe_handle_save()`.
- **Campos de SugestÃµes Proativas nÃ£o salvavam**: Os campos de sugestÃµes proativas de agendamento (enabled, interval, cooldown, mensagens) nÃ£o eram salvos. Adicionados 5 campos ao mÃ©todo `maybe_handle_save()`.

#### Security (SeguranÃ§a)

**AI Add-on - Melhorias de SeguranÃ§a no Hub de IA (v1.6.3)**

- **ValidaÃ§Ã£o de whatsapp_provider**: Adicionado novo mÃ©todo `sanitize_whatsapp_provider()` para validaÃ§Ã£o explÃ­cita do campo `whatsapp_provider`, restringindo a valores permitidos ('meta', 'twilio', 'custom'). Valores invÃ¡lidos agora retornam o padrÃ£o 'meta', evitando erros de configuraÃ§Ã£o.
- **Limite de caracteres em campos textarea**: Campos `whatsapp_instructions`, `proactive_scheduling_first_time_message` e `proactive_scheduling_recurring_message` agora tÃªm limite de 2000 caracteres (consistente com outros campos similares como `additional_instructions`).
- **RemoÃ§Ã£o de atributos perigosos em wp_kses**: Removido atributo `onclick` de links e `src` de scripts no mÃ©todo `get_allowed_form_tags()` para prevenir potenciais vulnerabilidades XSS. Scripts externos devem ser carregados via `wp_enqueue_script()`.
- **DocumentaÃ§Ã£o de data-* attributes**: Adicionados comentÃ¡rios explicativos sobre os atributos `data-*` permitidos e incluÃ­dos atributos genÃ©ricos adicionais (`data-id`, `data-value`, `data-type`) para compatibilidade com UIs de admin.

**Base Plugin - CorreÃ§Ã£o do Shortcode [dps_configuracoes] (v1.1.1)**

- **Erro "Falha ao publicar. A resposta nÃ£o Ã© um JSON vÃ¡lido" corrigido**: O shortcode `[dps_configuracoes]` causava um PHP Fatal Error ao ser inserido no editor de blocos (Gutenberg). A classe `DPS_Settings_Frontend` referenciava `DPS_Logger::LEVEL_DEBUG` que nÃ£o estava definida na classe `DPS_Logger`.
- **Constante LEVEL_DEBUG adicionada**: Adicionada constante `LEVEL_DEBUG = 'debug'` Ã  classe `DPS_Logger` para suportar nÃ­vel de log mais detalhado.
- **MÃ©todo debug() adicionado**: Novo mÃ©todo `DPS_Logger::debug()` para consistÃªncia com os outros nÃ­veis de log (info, warning, error).
- **Ordem de prioridade de logs atualizada**: DEBUG (0) â†’ INFO (1) â†’ WARNING (2) â†’ ERROR (3), permitindo filtrar logs por nÃ­vel mÃ­nimo configurado.
- **Causa raiz**: A aba "Empresa" do shortcode de configuraÃ§Ãµes usava `DPS_Logger::LEVEL_DEBUG` no dropdown de nÃ­veis de log, mas a constante nunca foi definida na classe.

**Stats Add-on - CorreÃ§Ãµes na Aba EstatÃ­sticas (v1.5.1)**

- **Erro de Finance nÃ£o detectado no comparativo de perÃ­odos**: O erro `finance_not_active` retornado por `get_financial_totals()` agora Ã© corretamente propagado para o array `current` em `get_period_comparison()`. Anteriormente, se o Finance Add-on nÃ£o estivesse ativo, as mÃ©tricas financeiras exibiam zero sem mostrar a mensagem de aviso adequada.
- **Datas do perÃ­odo adicionadas ao array current**: O array `current` em `get_period_comparison()` agora inclui `start_date` e `end_date` para consistÃªncia com o array `previous` e melhor tratamento de dados no frontend.
- **Nota do perÃ­odo anterior com validaÃ§Ã£o**: A nota "Comparando com perÃ­odo anterior" agora verifica se as datas estÃ£o preenchidas antes de tentar formatÃ¡-las, evitando exibiÃ§Ã£o de datas incorretas quando os dados estÃ£o incompletos.

**Push Add-on - CorreÃ§Ã£o de RelatÃ³rios por Email (v1.3.1)**

- **RelatÃ³rio da manhÃ£ vazio corrigido**: A query de agendamentos do dia usava `post_type => 'dps_appointment'` ao invÃ©s de `post_type => 'dps_agendamento'`, fazendo com que nenhum agendamento fosse encontrado. Corrigido para usar o post_type correto `dps_agendamento`.
- **RelatÃ³rio semanal de pets inativos corrigido**: A query SQL tambÃ©m usava `post_type = 'dps_appointment'`, causando o mesmo problema. Corrigido para `dps_agendamento`.
- **HorÃ¡rio de envio nÃ£o respeitando configuraÃ§Ã£o**: Adicionado mÃ©todo `reschedule_all_crons()` que Ã© chamado explicitamente apÃ³s salvar configuraÃ§Ãµes, garantindo que todos os crons sejam reagendados com os novos horÃ¡rios. Anteriormente, os hooks `update_option_*` podiam nÃ£o ser disparados se os valores nÃ£o mudassem, ou podiam haver problemas de cache.
- **Cache de opÃ§Ãµes limpo antes de reagendar**: O novo mÃ©todo `reschedule_all_crons()` limpa o cache de todas as opÃ§Ãµes relevantes antes de reagendar, evitando uso de valores desatualizados.

**Client Portal Add-on - CorreÃ§Ã£o de SolicitaÃ§Ã£o de Link de Acesso (v2.4.4)**

- **Erro "Erro ao processar solicitaÃ§Ã£o" corrigido**: O handler AJAX `dps_request_access_link_by_email` agora funciona tanto para usuÃ¡rios logados quanto nÃ£o-logados no WordPress. Anteriormente, apenas `wp_ajax_nopriv_*` estava registrado, causando falha para clientes logados no WP.
- **Handler `dps_request_portal_access` corrigido**: Mesmo problema - adicionado `wp_ajax_*` para suportar usuÃ¡rios logados.
- **Tratamento de erros JavaScript robusto**: Melhorado o cÃ³digo de tratamento de resposta AJAX para verificar `data.data` antes de acessar propriedades, evitando erros silenciosos.
- **Mensagem de erro mais clara**: Erro de conexÃ£o agora exibe "Erro de conexÃ£o. Verifique sua internet e tente novamente." em vez de mensagem genÃ©rica.

**Client Portal Add-on - Melhoria do Email de Link de Acesso (v2.4.4)**

- **Email em HTML moderno**: O email com link de acesso ao portal agora usa template HTML responsivo com:
  - Logo e branding do site
  - BotÃ£o CTA azul com gradiente e sombra
  - Aviso de validade em card amarelo destacado
  - Link alternativo para copiar/colar
  - Footer com copyright
- **Compatibilidade com clientes de email**: Template testado para Gmail, Outlook e outros clientes principais usando estilos inline.

**Base Plugin - Melhoria da Mensagem de WhatsApp (v1.4.0)**

- **Mensagem de solicitaÃ§Ã£o de acesso ao portal melhorada**: Nova mensagem Ã© mais clara e amigÃ¡vel:
  - Antes: `OlÃ¡, gostaria de acesso ao Portal do Cliente. Meu nome Ã© ______ e o nome do meu pet Ã© ______.`
  - Depois: `OlÃ¡! ðŸ¾ Gostaria de receber o link de acesso ao Portal do Cliente para acompanhar os serviÃ§os do meu pet. Meu nome: (informe seu nome) | Nome do pet: (informe o nome do pet)`
- **Emoji adicionado**: ðŸ¾ no inÃ­cio da mensagem para tornÃ¡-la mais amigÃ¡vel e visual.
- **InstruÃ§Ãµes claras**: Campos a preencher agora usam parÃªnteses ao invÃ©s de underscores para maior clareza.

**Registration Add-on - Modal de ConfirmaÃ§Ã£o para Duplicatas (v1.3.1)**

- **Modal de confirmaÃ§Ã£o para admins**: Quando um administrador tenta cadastrar um cliente com dados jÃ¡ existentes (email, telefone ou CPF), um modal Ã© exibido com trÃªs opÃ§Ãµes:
  - **Cancelar**: Fecha o modal e nÃ£o prossegue com o cadastro.
  - **Ver cadastro existente**: Redireciona para a pÃ¡gina do cliente jÃ¡ cadastrado.
  - **Continuar mesmo assim**: Cria o novo cliente com os dados duplicados.
- **VerificaÃ§Ã£o AJAX**: Os dados sÃ£o verificados via AJAX antes do envio do formulÃ¡rio, sem recarregar a pÃ¡gina.
- **IdentificaÃ§Ã£o de campos duplicados**: O modal mostra exatamente quais campos sÃ£o duplicados (Email, Telefone, CPF).
- **Rate limiting bypassed para admins**: Administradores (`manage_options`) nÃ£o sÃ£o mais limitados a 3 cadastros por hora.
- **reCAPTCHA bypassed para admins**: VerificaÃ§Ã£o anti-spam nÃ£o Ã© aplicada quando o usuÃ¡rio logado Ã© administrador.
- **Spam check bypassed para admins**: Hooks de validaÃ§Ã£o adicional (`dps_registration_spam_check`) sÃ£o pulados para administradores.
- **Causa raiz**: RestriÃ§Ãµes de seguranÃ§a do formulÃ¡rio pÃºblico estavam impedindo administradores de cadastrar mÃºltiplos clientes em sequÃªncia.

**Groomers Add-on - CorreÃ§Ã£o de HTML Malformado (v1.8.6)**

- **Aba GROOMERS em branco corrigida**: Removido `</div>` extra na funÃ§Ã£o `render_groomers_section()` que causava HTML malformado e impedia a renderizaÃ§Ã£o do conteÃºdo da aba.
- **Causa raiz**: Havia 62 tags `</div>` para 61 tags `<div>` abertas, resultando em estrutura HTML quebrada.

**Finance Add-on - CorreÃ§Ã£o de Cache Busting (v1.6.1)**

- **Version bump para invalidar cache**: Atualizada versÃ£o do add-on de 1.6.0 para 1.6.1 para forÃ§ar navegadores e CDNs a carregar o CSS corrigido do PR #439.
- **Causa raiz identificada**: O PR #439 corrigiu margens da aba FINANCEIRO e visibilidade da aba GROOMER, mas nÃ£o atualizou a constante `DPS_FINANCE_VERSION`, resultando em cache stale.

**Stats Add-on - CorreÃ§Ãµes de Compatibilidade (v1.5.0)**

- **Mensagem de erro da API formatada**: Aviso de "API nÃ£o disponÃ­vel" agora usa `.dps-surface--warning` em vez de HTML inline.
- **BotÃµes com estilos consistentes**: `.button-primary` e `.button-secondary` agora herdam corretamente os estilos globais do DPS.

**Groomers Add-on - ModernizaÃ§Ã£o do Layout da Aba Equipe (v1.8.4)**

- **Header da seÃ§Ã£o modernizado**: TÃ­tulo com Ã­cone ðŸ‘¥ e subtÃ­tulo descritivo seguindo padrÃ£o global DPS.
- **Sub-abas estilo card**: NavegaÃ§Ã£o por sub-abas (Equipe, RelatÃ³rios, ComissÃµes) agora usa cards visuais com Ã­cone, tÃ­tulo e descriÃ§Ã£o, similar ao padrÃ£o da Agenda.
- **Cards de estatÃ­sticas da equipe**: Novo bloco de mÃ©tricas exibindo total de profissionais, ativos, inativos e freelancers no topo da sub-aba Equipe.
- **Breakdown por funÃ§Ã£o**: ExibiÃ§Ã£o de badges com contagem por tipo de profissional (Groomer, Banhista, Auxiliar, RecepÃ§Ã£o).
- **~300 linhas de CSS**: Novas seÃ§Ãµes 20-24 no `groomers-admin.css` com estilos para header, sub-abas card, estatÃ­sticas e melhorias visuais.
- **MÃ©todos helper**: Adicionados `get_team_stats()` e `render_team_stats_cards()` para calcular e renderizar estatÃ­sticas da equipe.

#### Changed (Alterado)

**Groomers Add-on - Melhorias Visuais (v1.8.4)**

- **Avatares com cores por funÃ§Ã£o**: Gradientes de cores especÃ­ficos para cada tipo de profissional (azul=groomer, verde=banhista, amarelo=auxiliar, roxo=recepÃ§Ã£o).
- **Tooltip no status dot**: Indicador de status agora exibe tooltip CSS puro ao passar o mouse.
- **Empty state melhorado**: Mensagem de lista vazia com visual mais limpo e centralizado.
- **Accordions do formulÃ¡rio**: Melhor feedback visual quando aberto com borda azul.

**Finance Add-on - ModernizaÃ§Ã£o Visual da Aba Financeiro (v1.8.0)**

- **Layout moderno padronizado**: Aba Financeiro agora segue o padrÃ£o visual global do sistema DPS com classes `dps-surface` e `dps-section-title`.
- **TÃ­tulo com Ã­cone e subtÃ­tulo**: Header da seÃ§Ã£o usa estrutura padronizada com emoji ðŸ’° e descriÃ§Ã£o explicativa.
- **Dashboard de resumo encapsulado**: Cards de receitas, despesas, pendentes e saldo agora estÃ£o dentro de `dps-surface--info` com tÃ­tulo e descriÃ§Ã£o.
- **FormulÃ¡rio de pagamento parcial moderno**: Novo grid `dps-partial-summary` com destaque visual para valor restante.
- **Estado vazio amigÃ¡vel**: Quando nÃ£o hÃ¡ transaÃ§Ãµes, exibe mensagem com Ã­cone ðŸ“­ e dica para criar primeira transaÃ§Ã£o.
- **Demo HTML**: Criado arquivo `docs/layout/admin/demo/finance-layout-demo.html` para visualizaÃ§Ã£o offline do layout.
- **~200 linhas de CSS**: Novas seÃ§Ãµes 21-25 no `finance-addon.css` com estilos para grid, surfaces e componentes modernos.

#### Changed (Alterado)

**Finance Add-on - ReorganizaÃ§Ã£o de Estrutura (v1.8.0)**

- **FormulÃ¡rio de nova transaÃ§Ã£o**: Agora usa `dps-surface--info` com descriÃ§Ã£o explicativa e estrutura colapsÃ¡vel.
- **Lista de transaÃ§Ãµes**: Usa `dps-surface--neutral` com tÃ­tulo ðŸ“‹, descriÃ§Ã£o e filtros visuais melhorados.
- **SeÃ§Ã£o de cobranÃ§a rÃ¡pida**: Usa `dps-surface--warning` (destaque amarelo) com descriÃ§Ã£o sobre WhatsApp.
- **Toolbar de configuraÃ§Ãµes**: BotÃ£o de configuraÃ§Ãµes agora fica em toolbar dedicada ao invÃ©s de inline.
- **DocumentaÃ§Ã£o atualizada**: `docs/layout/admin/FINANCE_LAYOUT_IMPROVEMENTS.md` reescrito para v1.8.0 com todas as novas classes e estruturas.

#### Fixed (Corrigido)

**Finance Add-on - Acessibilidade (v1.8.0)**

- **Removidos emojis de selects de formulÃ¡rio**: Melhora compatibilidade com leitores de tela (acessibilidade).
- **ComentÃ¡rios CSS explicativos**: Adicionados comentÃ¡rios no CSS sobre comportamento do grid layout.

**Registration Add-on - ModernizaÃ§Ã£o Visual e Funcionalidades Admin (v1.3.0)**

- **Cards de resumo completos**: Agora exibem todos os campos preenchidos pelo usuÃ¡rio (CPF, data de nascimento, Instagram, Facebook, autorizaÃ§Ã£o de foto, como conheceu) no resumo do tutor, e todos os campos do pet (espÃ©cie, peso, pelagem, cor, nascimento, sexo, alerta de pet agressivo) no resumo dos pets.
- **Indicadores de campo obrigatÃ³rio**: Adicionado asterisco vermelho (*) nos campos obrigatÃ³rios (Nome e Telefone) com legenda explicativa no topo do formulÃ¡rio.
- **Banner informativo para admin**: Quando um administrador acessa o formulÃ¡rio pÃºblico, Ã© exibido um banner informativo com links rÃ¡pidos para configuraÃ§Ãµes e cadastros pendentes.
- **OpÃ§Ãµes de cadastro rÃ¡pido para admin**: Administradores podem ativar cadastros imediatamente (pulando confirmaÃ§Ã£o de email) e escolher se desejam enviar email de boas-vindas.
- **Ãcones de espÃ©cie nos cards de pet**: O resumo agora exibe emoji correspondente Ã  espÃ©cie selecionada (ðŸ¶ Cachorro, ðŸ± Gato, ðŸ¾ Outro).
- **FormataÃ§Ã£o de datas no resumo**: Datas de nascimento sÃ£o formatadas para exibiÃ§Ã£o brasileira (DD/MM/AAAA).
- **DocumentaÃ§Ã£o de anÃ¡lise visual**: Criado documento `docs/forms/REGISTRATION_FORM_VISUAL_ANALYSIS.md` com anÃ¡lise profunda do visual do formulÃ¡rio e plano de melhorias.

#### Changed (Alterado)

**Registration Add-on - Melhorias Visuais (v1.3.0)**

- **Summary box com destaque**: Adicionada borda lateral azul (#0ea5e9) seguindo padrÃ£o do guia de estilo visual para chamar atenÃ§Ã£o do usuÃ¡rio.
- **Grid responsivo no resumo**: Campos do resumo agora sÃ£o exibidos em grid de 2 colunas que adapta-se automaticamente a telas menores.
- **TransiÃ§Ã£o suave entre steps**: Adicionada animaÃ§Ã£o de opacidade (0.2s) para transiÃ§Ã£o mais fluida entre passos do formulÃ¡rio.
- **TÃ­tulos de seÃ§Ã£o com emoji**: SeÃ§Ãµes do resumo agora tÃªm emojis (ðŸ‘¤ Tutor, ðŸ¾ Pets) para melhor identificaÃ§Ã£o visual.

**Communications Add-on - Funcionalidades AvanÃ§adas (v0.3.0)**

- **HistÃ³rico de ComunicaÃ§Ãµes**: Nova tabela `dps_comm_history` para registro de todas as mensagens enviadas (WhatsApp, e-mail, SMS). Inclui status de entrega, metadata, cliente/agendamento associado e timestamps de criaÃ§Ã£o/atualizaÃ§Ã£o/entrega/leitura.
- **Retry com Exponential Backoff**: Sistema automÃ¡tico de retry para mensagens que falham. MÃ¡ximo de 5 tentativas com delays exponenciais (1min, 2min, 4min, 8min, 16min) + jitter aleatÃ³rio para evitar thundering herd. Cap mÃ¡ximo de 1 hora.
- **REST API de Webhooks**: Endpoints para receber status de entrega de gateways externos:
  - `POST /wp-json/dps-communications/v1/webhook/{provider}` - Recebe webhooks de Evolution API, Twilio ou formato genÃ©rico
  - `GET /wp-json/dps-communications/v1/webhook-url` - Retorna URLs e preview do secret para configuraÃ§Ã£o (admin only)
  - `GET /wp-json/dps-communications/v1/stats` - EstatÃ­sticas de comunicaÃ§Ãµes e retries (admin only)
  - `GET /wp-json/dps-communications/v1/history` - HistÃ³rico de comunicaÃ§Ãµes com filtros (admin only)
- **Suporte a mÃºltiplos providers**: Webhooks suportam Evolution API, Twilio e formato genÃ©rico, com mapeamento automÃ¡tico de status.
- **Webhook Secret**: Secret automÃ¡tico gerado para autenticaÃ§Ã£o de webhooks via header `Authorization: Bearer` ou `X-Webhook-Secret`.
- **Limpeza automÃ¡tica**: Cron job diÃ¡rio para limpeza de transients de retry expirados e mÃ©todo para limpar histÃ³rico antigo (padrÃ£o 90 dias).
- **Classes modulares**: Novas classes `DPS_Communications_History`, `DPS_Communications_Retry` e `DPS_Communications_Webhook` seguindo padrÃ£o singleton.

**Communications Add-on - VerificaÃ§Ã£o Funcional (v0.3.0)**

- **JavaScript para UX**: Novo arquivo `communications-addon.js` com prevenÃ§Ã£o de duplo clique, validaÃ§Ã£o client-side de e-mail e URL, e feedback visual durante submissÃ£o.
- **SeÃ§Ã£o de Webhooks na UI**: Nova seÃ§Ã£o na pÃ¡gina admin exibindo URLs de webhook e secret com botÃµes para mostrar/ocultar e copiar para clipboard.
- **SeÃ§Ã£o de EstatÃ­sticas**: Dashboard com cards visuais mostrando contagem de mensagens por status (pendentes, enviadas, entregues, lidas, falhas, reenviando) com Ã­cones e cores temÃ¡ticas.
- **ValidaÃ§Ã£o client-side**: Campos de e-mail e URL do gateway agora sÃ£o validados em tempo real no navegador, com mensagens de erro em portuguÃªs.
- **PrevenÃ§Ã£o de duplo clique**: BotÃ£o de salvar Ã© desabilitado durante submissÃ£o e exibe spinner "Salvando..." para evitar envios duplicados.
- **Melhorias de acessibilidade**: Adicionados `aria-describedby` nos campos, `:focus-visible` para navegaÃ§Ã£o por teclado, e feedback visual em rows com foco.
- **Mensagens de erro persistidas**: Erros de nonce/permissÃ£o agora sÃ£o persistidos via transient e exibidos corretamente apÃ³s redirect.
- **Secret mascarado no REST**: Endpoint `/webhook-url` agora retorna apenas preview mascarado do secret (`abc***xyz`) em vez do valor completo.

#### Security (SeguranÃ§a)

**Backup Add-on - CorreÃ§Ãµes de RevisÃ£o de CÃ³digo (v1.3.1)**

- **Placeholder SQL invÃ¡lido corrigido**: Removido uso de `%1s` (placeholder nÃ£o suportado) em `$wpdb->prepare()` para queries de tabelas. Como as tabelas jÃ¡ sÃ£o validadas com regex `^[a-zA-Z0-9_]+$`, a interpolaÃ§Ã£o direta Ã© segura e nÃ£o causa erros.
- **Cast explÃ­cito para INTEGER em queries**: Adicionado `CAST(pm.meta_value AS UNSIGNED)` nas queries de validaÃ§Ã£o de integridade referencial para garantir comparaÃ§Ã£o correta entre meta_value (string) e post ID (integer), melhorando performance e confiabilidade.
- **ValidaÃ§Ã£o de admin_email fallback**: O fallback para email do administrador agora valida que o email Ã© vÃ¡lido antes de usar, evitando configuraÃ§Ãµes com emails invÃ¡lidos.
- **SanitizaÃ§Ã£o de array keys preserva maiÃºsculas**: SubstituÃ­do `sanitize_key()` por `preg_replace('/[^\w\-]/', '')` para preservar case-sensitivity em chaves de array, evitando quebrar configuraÃ§Ãµes que dependem de maiÃºsculas.
- **ValidaÃ§Ã£o de valores falsy em mapeamento de IDs**: Adicionada verificaÃ§Ã£o `! empty()` e `> 0` para owner_id, appointment_client_id e appointment_pet_id antes de tentar mapear, evitando processamento incorreto de valores zerados ou vazios.

**Communications Add-on - Auditoria de SeguranÃ§a Completa (v0.2.1)**

- **Chave de API exposta**: Campo de API key do WhatsApp alterado de `type="text"` para `type="password"` com `autocomplete="off"` para evitar exposiÃ§Ã£o casual.
- **SSRF Prevention**: Implementada validaÃ§Ã£o rigorosa de URL do gateway WhatsApp bloqueando endereÃ§os internos (localhost, IPs privados 10.x, 172.16-31.x, 192.168.x, metadata endpoints de cloud). URLs HTTP sÃ³ sÃ£o aceitas em modo debug.
- **PII Leak em Logs**: Removida exposiÃ§Ã£o de dados pessoais (telefones, mensagens, emails) em logs. Implementado mÃ©todo `safe_log()` que mascara dados sensÃ­veis antes de logar.
- **PII Leak em error_log**: FunÃ§Ãµes legadas `dps_comm_send_whatsapp()` e `dps_comm_send_sms()` nÃ£o expÃµem mais telefones e mensagens no error_log do PHP.
- **VerificaÃ§Ã£o de DPS_Logger**: Adicionada verificaÃ§Ã£o de existÃªncia da classe `DPS_Logger` antes de usar, evitando fatal errors quando o plugin base nÃ£o estÃ¡ ativo.
- **Timeout preparado**: Adicionada constante `REQUEST_TIMEOUT` (30s) e exemplo de implementaÃ§Ã£o segura de `wp_remote_post()` com timeout, sslverify e tratamento de erro para futura integraÃ§Ã£o com gateway.
- **ValidaÃ§Ã£o de URL dupla**: Gateway WhatsApp valida URL novamente antes do envio (`filter_var()`) como double-check de seguranÃ§a.

#### Fixed (Corrigido)

**Communications Add-on - CorreÃ§Ãµes Funcionais (v0.3.0)**

- **CSS class do container**: Corrigida classe CSS do container (`wrap` â†’ `wrap dps-communications-wrap`) para aplicar estilos customizados.
- **Estilos para password**: Adicionados estilos para `input[type="password"]` que estavam faltando no CSS responsivo.
- **ID do formulÃ¡rio**: Adicionado `id="dps-comm-settings-form"` para permitir binding de eventos JavaScript.
- **ValidaÃ§Ã£o de nÃºmero WhatsApp**: NÃºmero do WhatsApp da equipe agora Ã© sanitizado removendo caracteres invÃ¡lidos.
- **Grid de estatÃ­sticas responsivo**: Grid de cards de estatÃ­sticas adapta-se automaticamente a diferentes tamanhos de tela.

**Compatibilidade PHP 8.1+ - MÃºltiplos Add-ons**

- **Deprecation warnings em strpos/str_replace/trim**: Corrigidos warnings do PHP 8.1+ que ocorriam durante ativaÃ§Ã£o dos plugins. Adicionado cast `(string)` para parÃ¢metros `$hook` em 10 mÃ©todos `enqueue_*_assets()` nos add-ons: Agenda, AI, Backup, Base, Client Portal, Communications, Payment.
- **trim(get_option()) sem valor padrÃ£o**: Corrigido em `class-dps-client-portal.php` para usar valor padrÃ£o vazio e cast `(string)`.
- **Domain Path incorreto**: Corrigido caminho do text domain no plugin Subscription de `/../languages` para `/languages`.

**Communications Add-on - CorreÃ§Ãµes de Bugs (v0.2.1)**

- **uninstall.php corrigido**: Arquivo de desinstalaÃ§Ã£o agora remove corretamente a option `dps_comm_settings` (principal) alÃ©m de `dps_whatsapp_number` e options legadas.
- **Log context sanitizado**: Contexto de logs agora mascara chaves sensÃ­veis (phone, to, email, message, body, subject, api_key) para compliance com LGPD/GDPR.

**Push Notifications Add-on - Auditoria de SeguranÃ§a Completa (v1.3.0)**

- **SQL Injection em uninstall.php**: Corrigido uso de query direta sem `$wpdb->prepare()` na exclusÃ£o de user meta durante desinstalaÃ§Ã£o.
- **SSRF em Push API**: Adicionada validaÃ§Ã£o de whitelist de hosts permitidos para endpoints de push (FCM, Mozilla, Windows, Apple) antes de enviar requisiÃ§Ãµes. Endpoints nÃ£o reconhecidos sÃ£o rejeitados.
- **SSRF em Telegram API**: Implementada validaÃ§Ã£o de formato do token do bot e chat ID antes de construir URLs da API Telegram. Token validado com regex rigoroso.
- **SanitizaÃ§Ã£o de Subscription JSON**: Adicionada validaÃ§Ã£o de JSON com `json_last_error()`, validaÃ§Ã£o de estrutura do objeto subscription, e sanitizaÃ§Ã£o de chaves criptogrÃ¡ficas (p256dh, auth).
- **ValidaÃ§Ã£o de Endpoint Push**: Endpoints de push agora sÃ£o validados contra lista de hosts conhecidos e devem usar HTTPS.
- **AutorizaÃ§Ã£o em unsubscribe AJAX**: Adicionada verificaÃ§Ã£o de capability `manage_options` para cancelar inscriÃ§Ãµes push (antes qualquer usuÃ¡rio logado podia cancelar).
- **Log Level Injection**: Adicionada whitelist de nÃ­veis de log permitidos (info, error, warning, debug) para evitar execuÃ§Ã£o de mÃ©todos arbitrÃ¡rios via `call_user_func()`.
- **SanitizaÃ§Ã£o de data em transaÃ§Ãµes**: ValidaÃ§Ã£o de formato de data (Y-m-d) antes de consultas ao banco de dados.
- **Escape de erro Telegram**: DescriÃ§Ã£o de erro retornada pela API Telegram agora Ã© sanitizada com `sanitize_text_field()`.
- **Token oculto na UI**: Campo de token do Telegram agora usa `type="password"` para evitar exposiÃ§Ã£o casual.
- **phpcs annotations**: Adicionadas anotaÃ§Ãµes de ignorar para queries diretas necessÃ¡rias com justificativas.

#### Added (Adicionado)

**Push Notifications Add-on - VerificaÃ§Ã£o Funcional e UX (v1.3.0)**

- **PrevenÃ§Ã£o de duplo clique**: BotÃ£o de salvar configuraÃ§Ãµes Ã© desabilitado durante envio e exibe spinner "Salvando..." para evitar submissÃµes duplicadas.
- **ValidaÃ§Ã£o de emails client-side**: Campos de email sÃ£o validados em tempo real ao perder foco, exibindo mensagens de erro especÃ­ficas para emails invÃ¡lidos.
- **ValidaÃ§Ã£o de dias de inatividade**: Campo numÃ©rico valida e corrige valores fora do intervalo (7-365 dias) tanto no client quanto no servidor.
- **Mensagens de feedback visuais**: Adicionado `settings_errors('dps_push')` para exibir mensagens de sucesso/erro apÃ³s salvar configuraÃ§Ãµes.
- **Strings internacionalizadas em JS**: Estados de loading ("Salvando...", "Enviando...", "Testando...") agora sÃ£o traduzÃ­veis via `wp_localize_script()`.
- **Service Worker melhorado**: Removidos caminhos hardcoded de Ã­cones. Ãcones agora sÃ£o definidos dinamicamente pelo payload da notificaÃ§Ã£o.
- **Estilos de acessibilidade**: Adicionado `:focus-visible` para navegaÃ§Ã£o por teclado em campos de formulÃ¡rio.
- **Hook corrigido**: Movido `maybe_handle_save` de `init` para `admin_init` para garantir exibiÃ§Ã£o correta de `settings_errors()`.

**Registration Add-on - Auditoria de SeguranÃ§a Completa (v1.2.2)**

- **SanitizaÃ§Ã£o de entrada aprimorada**: Adicionado `wp_unslash()` antes de `sanitize_*` em todos os campos do formulÃ¡rio de cadastro para tratamento correto de magic quotes.
- **ValidaÃ§Ã£o de coordenadas**: Coordenadas de latitude (-90 a 90) e longitude (-180 a 180) agora sÃ£o validadas como valores numÃ©ricos antes de serem salvas.
- **Whitelist para campos de seleÃ§Ã£o**: Campos de espÃ©cie, porte e sexo do pet agora sÃ£o validados contra lista branca de valores permitidos.
- **ValidaÃ§Ã£o de peso do pet**: Campo de peso valida se Ã© nÃºmero positivo e razoÃ¡vel (mÃ¡ximo 500kg).
- **ValidaÃ§Ã£o de data de nascimento**: Data de nascimento do pet Ã© validada como data vÃ¡lida e nÃ£o-futura.
- **Escape de placeholders em email**: Placeholders `{client_name}` e `{business_name}` no template de email de confirmaÃ§Ã£o agora sÃ£o escapados com `esc_html()` para prevenir XSS.
- **Dados sanitizados em filter**: O filter `dps_registration_spam_check` agora recebe um array com dados sanitizados em vez do `$_POST` bruto.
- **wp_safe_redirect**: SubstituÃ­do `wp_redirect()` por `wp_safe_redirect()` no redirecionamento apÃ³s cadastro bem-sucedido.
- **Header Retry-After em rate limit**: Resposta 429 da REST API agora inclui header `Retry-After` com tempo de espera em segundos.
- **SanitizaÃ§Ã£o de arrays de pets**: Campos de pets enviados como arrays agora aplicam `wp_unslash()` antes de sanitizar.
- **uninstall.php atualizado**: Arquivo de desinstalaÃ§Ã£o agora remove todas as options, transients e cron jobs criados pelo add-on.
- **Escape de wildcards LIKE**: Busca de cadastros pendentes agora escapa caracteres especiais (%, _) para prevenir wildcard injection.

#### Added (Adicionado)

**Registration Add-on - VerificaÃ§Ã£o Funcional e UX (v1.2.3)**

- **PrevenÃ§Ã£o de duplo clique no admin**: BotÃ£o de salvar configuraÃ§Ãµes Ã© desabilitado durante o envio e exibe texto "Salvando..." para evitar submissÃµes duplicadas.
- **Estilos para botÃ£o desabilitado**: CSS atualizado com estilos visuais para botÃµes desabilitados e estado de loading com spinner animado.
- **Mensagem de "sem resultados" melhorada**: PÃ¡gina de cadastros pendentes agora exibe mensagem estilizada como notice quando nÃ£o hÃ¡ resultados.
- **Estilos de erros JS animados**: Container de erros de validaÃ§Ã£o client-side agora inclui animaÃ§Ã£o shake para maior visibilidade.

**Registration Add-on - Template de Email e Gerenciamento (v1.2.4)**

- **Template de email moderno**: Redesenhado template padrÃ£o do email de confirmaÃ§Ã£o de cadastro com layout responsivo, cores vibrantes, botÃ£o de CTA destacado e visual profissional seguindo padrÃ£o dos outros emails do sistema.
- **SeÃ§Ã£o de gerenciamento de emails**: Reorganizada interface de configuraÃ§Ãµes com nova seÃ§Ã£o dedicada "ðŸ“§ Gerenciamento de Emails" com dicas claras e exemplos de placeholders.
- **Funcionalidade de teste de email**: Nova seÃ§Ã£o "ðŸ§ª Teste de Envio de Emails" permite enviar emails de teste (confirmaÃ§Ã£o ou lembrete) para qualquer endereÃ§o, facilitando validaÃ§Ã£o de configuraÃ§Ãµes e verificaÃ§Ã£o visual do template.
- **AJAX para envio de teste**: Endpoint seguro `wp_ajax_dps_registration_send_test_email` com verificaÃ§Ã£o de nonce e capability para envio de emails de teste.
- **Aviso visual em emails de teste**: Emails de teste incluem banner de aviso destacado informando que se trata de teste e que links nÃ£o sÃ£o funcionais.

**Payment Add-on - VerificaÃ§Ã£o Funcional e UX (v1.2.0)**

- **Indicador de status de configuraÃ§Ã£o**: PÃ¡gina de configuraÃ§Ãµes exibe badge "IntegraÃ§Ã£o configurada" ou "ConfiguraÃ§Ã£o pendente" com informaÃ§Ãµes sobre o que falta configurar.
- **PrevenÃ§Ã£o de duplo clique**: BotÃ£o de salvar Ã© desabilitado durante o envio e exibe texto "Salvando..." para evitar submissÃµes duplicadas.
- **Classe wrapper CSS**: PÃ¡gina de configuraÃ§Ãµes usa classe `dps-payment-wrap` para estilos responsivos e consistentes.
- **Acessibilidade A11y**: Campos de formulÃ¡rio com atributos `id`, `aria-describedby`, e `rel="noopener"` em links externos. Adicionada classe `screen-reader-text` para textos apenas para leitores de tela.
- **Focus visible**: Estilos CSS para navegaÃ§Ã£o por teclado com outline visÃ­vel em elementos focados.
- **Placeholder no campo PIX**: Campo de chave PIX agora exibe placeholder de exemplo para orientar o usuÃ¡rio.

**Subscription Add-on - Auditoria de SeguranÃ§a Completa (v1.3.0)**

- **Path Traversal em exclusÃ£o de arquivos**: Corrigida vulnerabilidade em `delete_finance_records()` onde a conversÃ£o de URL para path do sistema poderia ser manipulada. Agora valida que o arquivo estÃ¡ dentro do diretÃ³rio de uploads usando `realpath()` e `wp_delete_file()`.
- **VerificaÃ§Ã£o de existÃªncia de tabela SQL**: Adicionada verificaÃ§Ã£o `SHOW TABLES LIKE` antes de operaÃ§Ãµes SQL em `create_or_update_finance_record()` e `delete_finance_records()` para prevenir erros quando a tabela `dps_transacoes` nÃ£o existe.
- **ValidaÃ§Ã£o de tipo de post em todas as aÃ§Ãµes**: Todas as aÃ§Ãµes GET e POST (cancel, restore, delete, renew, delete_appts, update_payment) agora validam que o ID corresponde a um post do tipo `dps_subscription` antes de executar operaÃ§Ãµes.
- **wp_redirect vs wp_safe_redirect**: SubstituÃ­dos todos os usos de `wp_redirect()` por `wp_safe_redirect()` para prevenir vulnerabilidades de open redirect.
- **SanitizaÃ§Ã£o reforÃ§ada em save_subscription**: Implementada validaÃ§Ã£o completa de formato de data (Y-m-d), horÃ¡rio (H:i), frequÃªncia (whitelist), existÃªncia de cliente/pet, e preÃ§o positivo.
- **ValidaÃ§Ã£o de nonces melhorada**: SubstituÃ­do operador `??` por `isset()` com `wp_unslash()` e `sanitize_text_field()` em todas as verificaÃ§Ãµes de nonce.
- **ValidaÃ§Ã£o de status de pagamento**: Adicionada whitelist de status permitidos (pendente, pago, em_atraso) na atualizaÃ§Ã£o de status de pagamento.
- **API Mercado Pago**: Adicionada validaÃ§Ã£o de URL retornada (`filter_var(..., FILTER_VALIDATE_URL)`), verificaÃ§Ã£o de cÃ³digo de resposta HTTP, e logging seguro sem expor token de acesso.
- **hook handle_subscription_payment_status**: Adicionada validaÃ§Ã£o de existÃªncia e tipo de assinatura, formato de cycle_key (regex `^\d{4}-\d{2}$`), e cast para string antes de `strtolower()`.
- **Formatos de insert/update wpdb**: Adicionados arrays de formato (`%d`, `%s`, `%f`) em todas as chamadas `$wpdb->insert()` e `$wpdb->update()` para prevenir SQL injection.
- **absint vs intval**: SubstituÃ­dos todos os usos de `intval()` por `absint()` para IDs de posts, garantindo valores nÃ£o-negativos.

#### Added (Adicionado)

**Subscription Add-on - Melhorias Funcionais e UX (v1.3.0)**

- **Feedback de validaÃ§Ã£o**: FormulÃ¡rio agora exibe mensagens de erro especÃ­ficas quando validaÃ§Ã£o falha no servidor (campos obrigatÃ³rios, formato de data/hora, cliente/pet invÃ¡lido).
- **PrevenÃ§Ã£o de duplo clique**: BotÃµes de submit sÃ£o desabilitados durante o envio do formulÃ¡rio para evitar submissÃµes duplicadas.
- **Estado de loading visual**: BotÃµes exibem animaÃ§Ã£o de spinner e texto "Salvando..." durante operaÃ§Ãµes.
- **ValidaÃ§Ã£o client-side**: JavaScript valida campos obrigatÃ³rios, formato de data e horÃ¡rio antes do envio.
- **InternacionalizaÃ§Ã£o de strings JS**: Strings do JavaScript agora sÃ£o traduzÃ­veis via `wp_localize_script()`.
- **Foco em campo com erro**: FormulÃ¡rio faz scroll automÃ¡tico para o primeiro campo com erro de validaÃ§Ã£o.
- **Estilos de acessibilidade**: Adicionados estilos para `:focus-visible` e classe `.dps-sr-only` para leitores de tela.

**Base Plugin - Auditoria de SeguranÃ§a Completa (v1.1.1)**

- **CSRF em GitHub Updater**: Adicionada verificaÃ§Ã£o de nonce na funÃ§Ã£o `maybe_force_check()` que permite forÃ§ar verificaÃ§Ã£o de atualizaÃ§Ãµes. Anteriormente, atacantes podiam forÃ§ar limpeza de cache via link malicioso.
- **CSRF em GeraÃ§Ã£o de HistÃ³rico do Cliente**: Implementada proteÃ§Ã£o CSRF na geraÃ§Ã£o de histÃ³rico do cliente e envio de email. A aÃ§Ã£o `dps_client_history` agora requer nonce vÃ¡lido.
- **ValidaÃ§Ã£o de MIME em Upload de Foto do Pet**: Implementada lista branca de MIME types permitidos (jpg, png, gif, webp) e validaÃ§Ã£o adicional de tipo de imagem no upload de foto do pet.
- **Endpoint AJAX Exposto**: Removido o endpoint `wp_ajax_nopriv_dps_get_available_times` que permitia consulta de horÃ¡rios sem autenticaÃ§Ã£o.
- **XSS em Resposta AJAX**: SubstituÃ­do uso de `.html()` com concatenaÃ§Ã£o de strings por APIs DOM seguras (`.text()` e `.attr()`) no carregamento de horÃ¡rios disponÃ­veis.
- **wp_redirect vs wp_safe_redirect**: SubstituÃ­dos todos os usos de `wp_redirect()` por `wp_safe_redirect()` para prevenir vulnerabilidades de open redirect.
- **SupressÃ£o de erro em unlink**: SubstituÃ­do `@unlink()` por `wp_delete_file()` com verificaÃ§Ã£o prÃ©via de existÃªncia do arquivo.
- **SanitizaÃ§Ã£o de parÃ¢metro GET**: Adicionado `wp_unslash()` antes de `sanitize_text_field()` em `class-dps-admin-tabs-helper.php`.

**Base Plugin - CorreÃ§Ãµes de SeguranÃ§a CrÃ­ticas**

- **VerificaÃ§Ã£o de permissÃ£o em visualizaÃ§Ã£o de cliente**: Corrigida vulnerabilidade onde a verificaÃ§Ã£o `can_manage()` era executada APÃ“S a chamada de `render_client_page()`, permitindo potencial acesso nÃ£o autorizado a dados de clientes. A verificaÃ§Ã£o agora Ã© feita ANTES de processar a requisiÃ§Ã£o.
- **Nonce em exclusÃ£o de agendamentos na seÃ§Ã£o de histÃ³rico**: Adicionada proteÃ§Ã£o CSRF ao link de exclusÃ£o de agendamentos na tabela de histÃ³rico. O link agora utiliza `wp_nonce_url()` com a action `dps_delete`.
- **Nonce em exclusÃ£o de documentos**: Implementada verificaÃ§Ã£o de nonce na aÃ§Ã£o de exclusÃ£o de documentos (`dps_delete_doc`). RequisiÃ§Ãµes sem nonce vÃ¡lido agora retornam erro "AÃ§Ã£o nÃ£o autorizada" e feedback visual ao usuÃ¡rio.

#### Changed (Alterado)

**RenomeaÃ§Ã£o do Sistema - desi.pet by PRObst**

- **Rebranding completo**: O sistema foi renomeado de "DPS by PRObst" para "desi.pet by PRObst" em todas as interfaces visÃ­veis ao usuÃ¡rio.
- **Plugin Names atualizados**: Todos os 16 plugins (1 base + 15 add-ons) tiveram seus headers "Plugin Name" atualizados para refletir o novo nome.
- **Menu administrativo**: O menu principal do WordPress agora exibe "desi.pet by PRObst" em vez de "DPS by PRObst".
- **ComunicaÃ§Ãµes e e-mails**: Todos os templates de e-mail, mensagens do portal e notificaÃ§Ãµes foram atualizados para usar o novo nome.
- **DocumentaÃ§Ã£o**: README.md, AGENTS.md, ANALYSIS.md, CHANGELOG.md e toda a documentaÃ§Ã£o em `/docs` foram atualizados.
- **Prompts de IA**: System prompts do AI Add-on foram atualizados para refletir o novo nome do sistema.
- **IMPORTANTE - Integridade mantida**: Para garantir a estabilidade do sistema, os seguintes elementos NÃƒO foram alterados:
  - Slugs internos (ex: `desi-pet-shower`, `dps-*`)
  - Prefixos de cÃ³digo (`dps_`, `DPS_`)
  - Text domains para internacionalizaÃ§Ã£o
  - Nomes de Custom Post Types e tabelas de banco de dados
  - Hooks e filtros existentes

**ReorganizaÃ§Ã£o de pastas para estrutura unificada**

- **Nova estrutura**: Todos os plugins (base + 15 add-ons) foram movidos para uma Ãºnica pasta `plugins/`:
  - `plugin/desi-pet-shower-base_plugin/` â†’ `plugins/desi-pet-shower-base/`
  - `add-ons/desi-pet-shower-*_addon/` â†’ `plugins/desi-pet-shower-*/`
- **BenefÃ­cios**:
  - Estrutura mais limpa e organizada
  - Todos os 16 plugins em um Ãºnico local identificÃ¡vel
  - Nomenclatura simplificada (remoÃ§Ã£o dos sufixos `_addon` e `_plugin`)
- **AtualizaÃ§Ãµes realizadas**:
  - GitHub Updater atualizado com novos caminhos
  - Addon Manager atualizado com novos caminhos de arquivos
  - DocumentaÃ§Ã£o (README.md, AGENTS.md, ANALYSIS.md) atualizada
- **IMPORTANTE para instalaÃ§Ãµes existentes**: Os plugins devem ser reinstalados a partir das novas pastas. O WordPress espera cada plugin em sua prÃ³pria pasta em `wp-content/plugins/`, portanto:
  - Copie cada pasta de `plugins/desi-pet-shower-*` para `wp-content/plugins/`
  - Reative os plugins no painel do WordPress

#### Added (Adicionado)

**DocumentaÃ§Ã£o - Guia Passo a Passo do GitHub Updater (v1.4)**

- **Guia completo para usuÃ¡rios leigos**: Adicionado guia detalhado explicando como usar o sistema de atualizaÃ§Ãµes automÃ¡ticas via GitHub no arquivo `docs/GUIA_SISTEMA_DPS.md`.
- **InstruÃ§Ãµes visuais**: IncluÃ­dos diagramas ASCII e representaÃ§Ãµes visuais de como os avisos de atualizaÃ§Ã£o aparecem no WordPress.
- **FAQ de atualizaÃ§Ãµes**: Adicionadas perguntas frequentes sobre o processo de atualizaÃ§Ã£o, como forÃ§ar verificaÃ§Ã£o e desabilitar o atualizador.
- **Passo a passo estruturado**: Documentados os 4 passos principais: Verificar atualizaÃ§Ãµes â†’ Fazer backup â†’ Atualizar â†’ Testar.

**Client Portal Add-on (v2.4.3) - Auto-envio de Link de Acesso por E-mail**

- **FormulÃ¡rio de solicitaÃ§Ã£o de link por e-mail**: Clientes podem agora informar seu e-mail cadastrado na tela de acesso ao portal para receber automaticamente o link de acesso. NÃ£o Ã© mais necessÃ¡rio aguardar envio manual pela equipe para quem tem e-mail cadastrado.
- **AJAX endpoint `dps_request_access_link_by_email`**: Novo endpoint que busca cliente por e-mail, gera token de acesso e envia automaticamente. Inclui rate limiting (3 solicitaÃ§Ãµes/hora por IP ou e-mail).
- **Fallback para WhatsApp**: Clientes sem e-mail cadastrado sÃ£o orientados a solicitar via WhatsApp (comportamento anterior mantido como alternativa).
- **Feedback visual em tempo real**: Mensagens de sucesso/erro exibidas no formulÃ¡rio sem recarregar a pÃ¡gina.
- **ProteÃ§Ã£o contra brute force**: Rate limiting duplo (por IP e por e-mail) para evitar abuso do endpoint.

**Base Plugin (v1.2.0) - Card "Agendar serviÃ§o" na aba Agendamentos**

- **Card "Agendar serviÃ§o" no formulÃ¡rio de agendamentos**: FormulÃ¡rio de agendamento agora estÃ¡ envolvido por um card visual com header contendo eyebrow "AGENDAR SERVIÃ‡O", tÃ­tulo dinÃ¢mico (Novo Agendamento/Editar Agendamento) e hint descritivo. Estrutura idÃªntica ao implementado na aba Assinaturas.
- **Estilos de card no CSS base**: Adicionados estilos `.dps-card`, `.dps-card__header`, `.dps-card__body`, `.dps-card__eyebrow`, `.dps-card__title`, `.dps-card__hint` e `.dps-card__actions` no arquivo `dps-base.css` para garantir consistÃªncia visual em todas as abas.
- **Responsividade do card**: Media queries para adaptar layout do card em dispositivos mÃ³veis (768px e 480px).

**Base Plugin (v1.2.0) - AtualizaÃ§Ãµes via GitHub**

- **AtualizaÃ§Ãµes automÃ¡ticas via GitHub**: Nova classe `DPS_GitHub_Updater` que verifica e notifica atualizaÃ§Ãµes disponÃ­veis diretamente do repositÃ³rio GitHub.
- **Suporte a todos os plugins DPS**: O sistema verifica atualizaÃ§Ãµes para o plugin base e todos os 15 add-ons oficiais automaticamente.
- **IntegraÃ§Ã£o nativa com WordPress**: Utiliza os hooks `pre_set_site_transient_update_plugins` e `plugins_api` para exibir atualizaÃ§Ãµes no painel de Plugins padrÃ£o do WordPress.
- **Cache inteligente**: VerificaÃ§Ãµes sÃ£o cacheadas por 12 horas para evitar chamadas excessivas Ã  API do GitHub.
- **NotificaÃ§Ãµes no admin**: Aviso visual na pÃ¡gina de Plugins quando hÃ¡ atualizaÃ§Ãµes DPS disponÃ­veis.
- **Header Update URI**: Adicionado header `Update URI` em todos os plugins para desabilitar verificaÃ§Ã£o no wordpress.org.
- **VerificaÃ§Ã£o forÃ§ada**: ParÃ¢metro `?dps_force_update_check=1` permite forÃ§ar nova verificaÃ§Ã£o de atualizaÃ§Ãµes.

**Base Plugin (v1.1.0) - Gerenciador de Add-ons**

- **Gerenciador centralizado de add-ons**: Nova pÃ¡gina administrativa (desi.pet by PRObst â†’ Add-ons) para visualizar, ativar e desativar add-ons do ecossistema DPS.
- **ResoluÃ§Ã£o automÃ¡tica de dependÃªncias**: Sistema ordena add-ons por suas dependÃªncias e ativa na ordem correta automaticamente.
- **VisualizaÃ§Ã£o de ordem de ativaÃ§Ã£o**: Painel exibe ordem recomendada de ativaÃ§Ã£o baseada nas dependÃªncias de cada add-on.
- **AtivaÃ§Ã£o/desativaÃ§Ã£o em lote**: SeleÃ§Ã£o mÃºltipla de add-ons com ativaÃ§Ã£o respeitando ordem de dependÃªncias.
- **CategorizaÃ§Ã£o de add-ons**: Add-ons organizados em 6 categorias (Essenciais, OperaÃ§Ã£o, IntegraÃ§Ãµes, Cliente, AvanÃ§ado, Sistema).
- **VerificaÃ§Ã£o de dependÃªncias**: Alertas visuais quando dependÃªncias de um add-on nÃ£o estÃ£o ativas.

#### Removed (Removido)

**Agenda Add-on - SimplificaÃ§Ã£o da Interface (v1.6.0)**

- **BotÃ£o "Novo Agendamento" removido da agenda**: BotÃ£o "âž• Novo" removido do grupo de aÃ§Ãµes principais da agenda. Novos agendamentos devem ser criados pela aba Agendamentos padrÃ£o.
- **BotÃ£o "Exportar PDF" removido**: BotÃ£o de exportaÃ§Ã£o para PDF removido do grupo de aÃ§Ãµes da agenda. RelatÃ³rios podem ser acessados pela aba EstatÃ­sticas.
- **SeÃ§Ã£o "RelatÃ³rio de OcupaÃ§Ã£o" removida**: SeÃ§Ã£o colapsÃ¡vel com mÃ©tricas de ocupaÃ§Ã£o (taxa de conclusÃ£o, cancelamento, horÃ¡rio de pico, mÃ©dia por hora) removida do final da agenda. MÃ©tricas similares disponÃ­veis na aba EstatÃ­sticas com filtro de perÃ­odo.
- **SeÃ§Ã£o "Resumo do Dia" removida**: Dashboard de KPIs do dia (pendentes, finalizados, faturamento estimado, taxa de cancelamento, mÃ©dia diÃ¡ria) removido do final da agenda. MÃ©tricas disponÃ­veis na aba EstatÃ­sticas selecionando perÃ­odo de 1 dia.
- **Plano de implementaÃ§Ã£o criado**: Documento `docs/implementation/STATS_DAILY_ANALYSIS_PLAN.md` criado com plano para adicionar mÃ©tricas complementares (horÃ¡rio de pico, mÃ©dia por hora ativa) na aba EstatÃ­sticas.

#### Deprecated (Depreciado)

**Agenda Add-on - MÃ©todos Depreciados (v1.6.0)**

- **MÃ©todo `render_occupancy_report()` depreciado**: MÃ©todo marcado como `@deprecated 1.6.0`. Funcionalidade movida para aba EstatÃ­sticas. RemoÃ§Ã£o completa prevista para v1.7.0.
- **MÃ©todo `render_admin_dashboard()` depreciado**: MÃ©todo marcado como `@deprecated 1.6.0`. Funcionalidade movida para aba EstatÃ­sticas. RemoÃ§Ã£o completa prevista para v1.7.0.

**Add-ons Descontinuados**

- **Debugging Add-on removido**: Add-on de gerenciamento de constantes de debug e visualizaÃ§Ã£o de logs removido por complexidade de manutenÃ§Ã£o.
- **White Label Add-on removido**: Add-on de personalizaÃ§Ã£o de marca, cores, logo e SMTP removido por baixa utilizaÃ§Ã£o e dificuldades de manutenÃ§Ã£o.

**Base Plugin (v1.0.4) - Redesign das Abas CLIENTES e PETS**

- **Templates modulares para pets**: Criados templates separados para formulÃ¡rio (`pet-form.php`), listagem (`pets-list.php`) e seÃ§Ã£o completa (`pets-section.php`), seguindo mesmo padrÃ£o jÃ¡ existente para clientes.
- **Colunas adicionais na listagem de clientes**: Email e contagem de pets agora visÃ­veis na tabela de clientes para consulta rÃ¡pida.
- **Colunas adicionais na listagem de pets**: Porte e Sexo agora visÃ­veis na tabela de pets, com Ã­cones para espÃ©cie e badges coloridos por tamanho.
- **Indicador de pet agressivo na listagem**: Badge visual âš ï¸ e destaque vermelho na linha para pets marcados como agressivos.
- **Link "Adicionar pet" para clientes sem pets**: Na coluna Pets, clientes sem pets tÃªm link rÃ¡pido para cadastrar.
- **Contagem de registros no header das listas**: Badge com total de clientes/pets cadastrados ao lado do tÃ­tulo.

#### Changed (Alterado)

**Base Plugin (v1.0.4)**

- **FormulÃ¡rio de pets refatorado para templates**: LÃ³gica de preparaÃ§Ã£o de dados separada da renderizaÃ§Ã£o (mÃ©todos `prepare_pets_section_data()` e `render_pets_section()`).
- **Header de listas redesenhado**: TÃ­tulos "Clientes Cadastrados" e "Pets Cadastrados" agora com Ã­cones, badges de contagem e espaÃ§amento melhorado.
- **Toolbar de busca padronizada**: Campo de busca com placeholder mais descritivo e layout flex responsivo.
- **AÃ§Ãµes nas tabelas melhoradas**: Links Editar/Agendar/Excluir agora com cores semÃ¢nticas (azul para editar, verde para agendar, vermelho para excluir).
- **Estilos CSS ampliados**: Novas classes para badges de porte (`.dps-size-badge--pequeno/medio/grande`), pets agressivos, links de aÃ§Ã£o e responsividade.

**Groomers Add-on (v1.8.0) - Redesign completo do Layout da Aba Equipe**

- **NavegaÃ§Ã£o por sub-abas**: SeparaÃ§Ã£o em 3 sub-abas (Equipe, RelatÃ³rios, ComissÃµes) para organizaÃ§Ã£o mais clara e navegaÃ§Ã£o mais fluida.
- **Layout em cards**: FormulÃ¡rio e listagem agora em containers visuais estilizados com headers e bordas claras.
- **Tabela compacta com avatares**: Listagem de profissionais redesenhada com avatares circulares, indicadores de comissÃ£o e status como ponto colorido (dot).
- **FormulÃ¡rio reorganizado com accordions**: Campos bÃ¡sicos sempre visÃ­veis, credenciais e configuraÃ§Ãµes adicionais em seÃ§Ãµes colapsÃ¡veis (`<details>`).
- **Dias de trabalho compactos**: Grid de checkboxes em formato mini (letras) para melhor aproveitamento de espaÃ§o.
- **Filtros inline na listagem**: Filtros de tipo e status como dropdowns compactos no header do card.

#### Changed (Alterado)

**Groomers Add-on (v1.8.0)**

- **TÃ­tulo da seÃ§Ã£o alterado de "Groomers" para "Equipe"**: Nomenclatura mais abrangente para suportar diferentes tipos de profissionais.
- **Tabela de 6 para 5 colunas**: Colunas reorganizadas (Profissional, Contato, FunÃ§Ã£o, Status, AÃ§Ãµes) com informaÃ§Ãµes condensadas.
- **Status como indicador visual**: Antes era badge com texto, agora Ã© ponto colorido clicÃ¡vel para alternar status.
- **BotÃµes de aÃ§Ã£o como Ã­cones**: Editar e Excluir agora sÃ£o botÃµes de Ã­cone compactos em vez de links com texto.
- **RelatÃ³rios e ComissÃµes em abas separadas**: Antes ficavam no final da pÃ¡gina, agora tÃªm abas dedicadas para melhor foco.
- **CSS ampliado com variÃ¡veis CSS**: Uso de custom properties para cores e bordas, facilitando manutenÃ§Ã£o.

**Subscription Add-on (v1.2.0) - Melhorias de Layout e UX na Aba Assinaturas**

- **Dashboard de mÃ©tricas**: Cards de resumo no topo da seÃ§Ã£o mostrando Assinaturas Ativas, Receita Mensal, Pagamentos Pendentes e Canceladas.
- **Barra de progresso visual**: VisualizaÃ§Ã£o grÃ¡fica do progresso de atendimentos (X/4 ou X/2 realizados) com cores e animaÃ§Ã£o.
- **Tabela responsiva**: Wrapper com scroll horizontal e transformaÃ§Ã£o em cards para mobile (<640px).
- **Data-labels para mobile**: Cada cÃ©lula da tabela inclui atributo `data-label` para exibiÃ§Ã£o correta em layout de cards.
- **BotÃµes de aÃ§Ã£o estilizados**: AÃ§Ãµes (Editar, Cancelar, Renovar, Cobrar) exibidas como botÃµes compactos com cores semÃ¢nticas e hover states.
- **Badges de status**: Status de pagamento em assinaturas canceladas exibido como badge colorido.

#### Changed (Alterado)

**Subscription Add-on (v1.2.0)**

- **FormulÃ¡rio reorganizado em fieldsets**: Campos agrupados em "Dados do Cliente", "Detalhes da Assinatura" e "Agendamento Inicial" com legendas claras.
- **Grid de 2 colunas**: Campos Cliente/Pet, ServiÃ§o/FrequÃªncia e Data/Hora lado a lado em desktop.
- **Tabela simplificada**: Colunas Cliente e Pet unificadas em "Cliente / Pet" com layout empilhado para reduzir nÃºmero de colunas.
- **Coluna InÃ­cio removida**: Data de inÃ­cio nÃ£o exibida na listagem (informaÃ§Ã£o menos relevante para operaÃ§Ã£o diÃ¡ria).
- **PrÃ³ximo agendamento compacto**: Formato de data reduzido para "dd/mm HH:mm" para economizar espaÃ§o.
- **Estilos CSS ampliados**: Novos estilos para dashboard, formulÃ¡rio com fieldsets, barra de progresso, badges, botÃµes de aÃ§Ã£o e responsividade.
- **VersÃ£o atualizada para 1.2.0** no cabeÃ§alho do plugin e assets.

**Push Add-on (v1.2.0) - Melhorias de Interface e CorreÃ§Ãµes**

- **Menu admin visÃ­vel**: Menu agora registrado sob "desi.pet by PRObst > NotificaÃ§Ãµes" (antes estava oculto).
- **BotÃµes de teste de relatÃ³rios**: BotÃµes "Enviar Teste" para cada tipo de relatÃ³rio (Agenda, Financeiro, Semanal).
- **BotÃ£o de teste de conexÃ£o Telegram**: Valida configuraÃ§Ã£o e envia mensagem de teste.
- **AJAX handlers**: Novos handlers `dps_push_test_report` e `dps_push_test_telegram` para testes via AJAX.
- **Feedback visual**: Mensagens de sucesso/erro exibidas ao lado dos botÃµes de teste.

#### Changed (Alterado)

**Push Add-on (v1.2.0)**

- **Carregamento de assets otimizado**: CSS/JS agora carregados apenas em pÃ¡ginas DPS relevantes.
- **Cron hooks adicionais**: Reagendamento automÃ¡tico quando opÃ§Ãµes `_enabled` ou `_day` mudam.
- **VersÃ£o atualizada para 1.2.0** no cabeÃ§alho do plugin e assets.

#### Fixed (Corrigido)

- **Base Plugin (v1.1.1)**: Corrigido PHP Notice "Translation loading for the desi-pet-shower domain was triggered too early" no WordPress 6.7+. A funÃ§Ã£o `add_role()` no hook de ativaÃ§Ã£o agora usa string literal em vez de `__()` para evitar carregamento prematuro do text domain.

- **Base Plugin (v1.0.4)**: Cache dos assets CSS/JS agora usa `filemtime` para versionar automaticamente o layout modernizado do Painel de GestÃ£o DPS, evitando exibiÃ§Ã£o do modelo antigo em navegadores com cache.

**Push Add-on (v1.2.0)**

- **uninstall.php corrigido**: Agora limpa todas as options criadas pelo add-on e remove cron jobs.

**Subscription Add-on (v1.2.1)**

- **BotÃ£o "Adicionar serviÃ§o extra" corrigido**: Movida chamada do `bindExtras()` para o inÃ­cio da funÃ§Ã£o `init()`, garantindo que os eventos de clique sejam vinculados mesmo quando o formulÃ¡rio nÃ£o estÃ¡ presente na pÃ¡gina inicial. Antes, se o usuÃ¡rio acessava a listagem de assinaturas e depois navegava para "Nova Assinatura", o botÃ£o nÃ£o funcionava por falta de binding dos eventos.

---

**AI Add-on (v1.9.0) - EdiÃ§Ã£o de Regras de Sistema (System Prompts)**

- **Campo editÃ¡vel de System Prompts**: Nova seÃ§Ã£o "Regras de Sistema (System Prompts)" na pÃ¡gina de configuraÃ§Ãµes do add-on IA.
- Permite visualizar e editar as regras de seguranÃ§a e escopo para cada contexto: Portal do Cliente, Chat PÃºblico, WhatsApp e E-mail.
- Indicadores visuais (badges) mostram se o prompt estÃ¡ "Customizado", "PadrÃ£o" ou "Modificado".
- BotÃ£o "Restaurar PadrÃ£o" via AJAX para cada contexto, permitindo reverter para o prompt original.
- Prompts customizados sÃ£o armazenados na opÃ§Ã£o `dps_ai_custom_prompts` e priorizados sobre os arquivos padrÃ£o.
- Classe `DPS_AI_Prompts` refatorada com cache unificado para arquivos (`$file_cache`) e banco de dados (`$custom_prompts_cache`).
- Novos mÃ©todos: `get_custom_prompt()`, `save_custom_prompt()`, `reset_to_default()`, `has_custom_prompt()`, `get_default_prompt()`, `get_all_custom_prompts()`.

**Groomers Add-on (v1.7.0) - FASE 4: Recursos AvanÃ§ados**

- **F4.1 - ConfiguraÃ§Ã£o de disponibilidade**: Novos campos para horÃ¡rio de inÃ­cio/tÃ©rmino e dias de trabalho por profissional.
- Metas `_dps_work_start`, `_dps_work_end`, `_dps_work_days` para armazenar configuraÃ§Ã£o de turnos.
- Fieldset "Disponibilidade" no formulÃ¡rio de cadastro com inputs de horÃ¡rio e grid de checkboxes para dias.
- CSS responsivo para componentes de disponibilidade.

**Groomers Add-on (v1.6.0) - FASE 3: Finance/Repasse**

- **F3.2 - Hook `dps_finance_booking_paid` consumido**: Ao confirmar pagamento, comissÃ£o Ã© calculada automaticamente para profissionais vinculados.
- **F3.3 - MÃ©todo `generate_staff_commission()`**: Calcula comissÃ£o proporcional para mÃºltiplos profissionais.
- Metas `_dps_staff_commissions`, `_dps_commission_generated`, `_dps_commission_date` no agendamento.
- Hook `dps_groomers_commission_generated` para extensÃµes (Loyalty, Stats, etc.).

**Services Add-on (v1.4.0) - ReformulaÃ§Ã£o do Layout da Aba ServiÃ§os**

- **Layout do formulÃ¡rio completamente reorganizado**: FormulÃ¡rio de cadastro de serviÃ§os agora usa fieldsets semÃ¢nticos com legendas claras ("InformaÃ§Ãµes BÃ¡sicas", "Valores por Porte", "DuraÃ§Ã£o por Porte", "ConfiguraÃ§Ã£o do Pacote").
- **Grid responsivo**: Campos organizados em grid de 2 colunas (desktop) com fallback para 1 coluna (mobile).
- **Inputs com prefixo/sufixo**: Campos de preÃ§o mostram "R$" como prefixo visual, campos de duraÃ§Ã£o mostram "min" como sufixo.
- **Listagem melhorada**: Nova coluna "DuraÃ§Ã£o" na tabela, busca com placeholder mais claro, contador de serviÃ§os ativos/totais no cabeÃ§alho.
- **Badges de tipo coloridos**: Tipo de serviÃ§o exibido como badge colorido (padrÃ£o=azul, extra=amarelo, pacote=roxo).
- **BotÃµes de aÃ§Ã£o estilizados**: AÃ§Ãµes (Editar, Duplicar, Ativar/Desativar, Excluir) exibidas como botÃµes compactos com cores semÃ¢nticas.
- **Categoria como linha secundÃ¡ria**: Categoria exibida abaixo do nome do serviÃ§o em vez de coluna separada.
- **Estado vazio amigÃ¡vel**: Mensagem orientativa quando nÃ£o hÃ¡ serviÃ§os cadastrados.
- **CSS ampliado**: Novos estilos para formulÃ¡rio, fieldsets, grid de porte, inputs com prefixo/sufixo, badges e aÃ§Ãµes.
- **BotÃ£o Cancelar**: Ao editar serviÃ§o, botÃ£o para cancelar ediÃ§Ã£o e voltar ao formulÃ¡rio vazio.

#### Removed (Removido)

**Services Add-on (v1.4.0)**

- **SeÃ§Ã£o "Consumo de estoque" removida**: Funcionalidade nÃ£o utilizada foi removida do formulÃ¡rio de cadastro de serviÃ§os. A meta `dps_service_stock_consumption` continua sendo lida para serviÃ§os existentes mas nÃ£o Ã© mais editÃ¡vel.

**Services Add-on (v1.3.0) - FASE 2: IntegraÃ§Ã£o com Profissionais**

- **F2.1 - Campo `required_staff_type`**: ServiÃ§os podem exigir tipo especÃ­fico de profissional (groomer, banhista ou qualquer).
- Meta `required_staff_type` salva com valores 'any', 'groomer', 'banhista'.

**Agenda Add-on (v1.4.2) - FASE 7: ReorganizaÃ§Ã£o das Abas**

- **Resumo do Dia e RelatÃ³rio de OcupaÃ§Ã£o**: Movidos para o final da pÃ¡gina, ambos agora usam `<details>` expansÃ­vel (fechados por padrÃ£o).
- **Aba "VisÃ£o RÃ¡pida" reorganizada**: Colunas Checkbox, HorÃ¡rio, Pet (com badge de agressivo), Tutor, ServiÃ§os (botÃ£o popup), ConfirmaÃ§Ã£o (dropdown elegante com CONFIRMADO/NÃƒO CONFIRMADO/CANCELADO).
- **Aba "OperaÃ§Ã£o" reorganizada**: Colunas Checkbox, HorÃ¡rio, Pet (com badge de agressivo), Tutor, Status do ServiÃ§o (dropdown com Ã­cones), Pagamento (popup com envio por WhatsApp e copiar link).
- **Aba "Detalhes" reorganizada**: Colunas Checkbox, HorÃ¡rio, Pet (com badge de agressivo), Tutor, TaxiDog (lÃ³gica condicional para solicitado/nÃ£o solicitado).
- **Badge de pet agressivo**: Badge visual em todas as abas identificando pets marcados como agressivos.
- **Popup de ServiÃ§os**: Modal com lista de serviÃ§os, preÃ§os e observaÃ§Ãµes do atendimento.
- **Popup de Pagamento**: Modal com botÃ£o para enviar link de pagamento por WhatsApp e botÃ£o para copiar link.
- **Handler AJAX `dps_agenda_request_taxidog`**: Permite solicitar TaxiDog para agendamentos que nÃ£o tinham solicitado.
- **CSS e JS**: Novos estilos para dropdowns elegantes, badges, popups e responsividade.

**Push Notifications Add-on (v1.1.0) - RelatÃ³rios por Email**

- **Interface de configuraÃ§Ã£o de relatÃ³rios por email**: Adicionada seÃ§Ã£o completa de configuraÃ§Ã£o na pÃ¡gina de administraÃ§Ã£o do Push Add-on.
- **Agenda DiÃ¡ria por Email**: Resumo dos agendamentos do dia enviado automaticamente no horÃ¡rio configurado.
- **RelatÃ³rio Financeiro DiÃ¡rio**: Receitas, despesas e transaÃ§Ãµes do dia enviados automaticamente.
- **RelatÃ³rio Semanal de Pets Inativos**: Lista de pets sem atendimento hÃ¡ X dias para reengajamento.
- **ConfiguraÃ§Ã£o de destinatÃ¡rios**: Campos para definir emails de destinatÃ¡rios separados por vÃ­rgula.
- **ConfiguraÃ§Ã£o de horÃ¡rios**: Inputs de horÃ¡rio para cada tipo de relatÃ³rio.
- **ConfiguraÃ§Ã£o de Telegram**: Campos para token do bot e chat ID para envio paralelo via Telegram.
- **Classe DPS_Email_Reports carregada e instanciada**: Classe existente agora Ã© incluÃ­da e inicializada automaticamente.

**Agenda Add-on (v1.1.0) - FASE 2: Filtro por Profissional**

- **F2.5 - Filtro por profissional na Agenda**: Novo filtro nos filtros avanÃ§ados para selecionar profissional especÃ­fico.
- ParÃ¢metro `filter_staff` adicionado no trait de renderizaÃ§Ã£o.
- Profissionais exibidos com tipo entre parÃªnteses no dropdown de filtro.

**Groomers Add-on (v1.5.0) - FASE 1: Tipos de Profissional + Freelancer**

- **F1.1 - Meta `_dps_staff_type`**: Novo campo para diferenciar tipos de profissional (groomer, banhista, auxiliar, recepÃ§Ã£o). Metas sÃ£o migradas automaticamente para groomers existentes.
- **F1.2 - Meta `_dps_is_freelancer`**: Flag booleana para identificar profissionais autÃ´nomos vs CLT. Permite regras diferenciadas em relatÃ³rios e financeiro.
- **F1.3 - MigraÃ§Ã£o automÃ¡tica**: Na primeira execuÃ§Ã£o da v1.5.0, todos os profissionais existentes recebem `staff_type='groomer'` e `is_freelancer='0'` automaticamente.
- **F1.4 - FormulÃ¡rio de cadastro atualizado**: Novo fieldset "Tipo e VÃ­nculo" com select de tipo de profissional e checkbox de freelancer.
- **F1.5 - Tabela de listagem atualizada**: Novas colunas "Tipo" e "Freelancer" com badges visuais coloridas por tipo.
- **F1.6 - Filtros na listagem**: Novos filtros por tipo, freelancer e status para facilitar busca em petshops com muitos profissionais.
- **Select agrupado por tipo no agendamento**: Profissionais agrupados por tipo com optgroup no select.
- **MÃ©todo `get_staff_types()`**: MÃ©todo estÃ¡tico para obter tipos disponÃ­veis com labels traduzidos.
- **MÃ©todo `get_staff_type_label()`**: MÃ©todo estÃ¡tico para obter label traduzido de um tipo especÃ­fico.
- **MÃ©todo `validate_staff_type()`**: MÃ©todo estÃ¡tico para validar e normalizar tipos.

**Registration Add-on (v1.2.0) - FASE 2A: UX Quick Wins & Higiene TÃ©cnica**

- **F2.5 - JS em arquivo separado**: Criado `assets/js/dps-registration.js` com ~400 linhas de JavaScript modular. Remove ~40 linhas de JS inline do PHP. Script enfileirado com `wp_enqueue_script` apenas quando o shortcode estÃ¡ presente. ExpÃµe objeto global `DPSRegistration` com mÃ©todos pÃºblicos para extensibilidade.
- **F2.1 - MÃ¡scaras de entrada (CPF e telefone)**: MÃ¡scara visual de CPF (###.###.###-##) aplicada automaticamente. MÃ¡scara de telefone adapta entre 10 dÃ­gitos (##) ####-#### e 11 dÃ­gitos (##) #####-####. Suporta colagem (paste) e ediÃ§Ã£o no meio do texto sem quebrar.
- **F2.2 - ValidaÃ§Ã£o client-side (JS)**: ValidaÃ§Ã£o de campos obrigatÃ³rios antes do submit. ValidaÃ§Ã£o de CPF com algoritmo mod 11 em JavaScript. ValidaÃ§Ã£o de telefone (10-11 dÃ­gitos) e email. Erros exibidos no topo do formulÃ¡rio com estilo consistente. FormulÃ¡rio ainda funciona se JS estiver desabilitado (graceful degradation).
- **F2.4 - Indicador de loading no botÃ£o**: BotÃ£o Ã© desabilitado durante envio. Texto muda para "Enviando..." com estilo visual de espera.
- **F2.3 - Mensagem de sucesso melhorada**: TÃ­tulo destacado com Ã­cone de check. Mensagem contextualizada para banho e tosa.
- **F2.8 - PrÃ³ximo passo sugerido**: ApÃ³s sucesso, exibe orientaÃ§Ã£o para agendar via WhatsApp/telefone. FormulÃ¡rio nÃ£o Ã© mais exibido apÃ³s cadastro concluÃ­do.
- **F2.9 - Removido session_start()**: FunÃ§Ã£o removida pois nÃ£o era mais necessÃ¡ria (sistema usa transients/cookies para mensagens). Elimina conflitos de headers e warnings em alguns hosts.

**Registration Add-on (v1.1.0) - FASE 1: SeguranÃ§a, ValidaÃ§Ã£o & Hardening**

- **F1.1 - ValidaÃ§Ã£o de campos obrigatÃ³rios no backend**: Nome e telefone sÃ£o agora validados no backend (nÃ£o apenas HTML required). Campos vazios resultam em mensagem de erro clara e impede criaÃ§Ã£o do cadastro.
- **F1.2 - ValidaÃ§Ã£o de CPF com algoritmo mod 11**: CPF informado Ã© validado com dÃ­gitos verificadores. CPF invÃ¡lido bloqueia cadastro. Campo continua opcional, mas se preenchido deve ser vÃ¡lido.
- **F1.3 - ValidaÃ§Ã£o de telefone brasileiro**: Telefone validado para formato BR (10-11 dÃ­gitos). Aceita com ou sem cÃ³digo de paÃ­s (55). Usa `DPS_Phone_Helper::is_valid_brazilian_phone()` quando disponÃ­vel.
- **F1.4 - ValidaÃ§Ã£o de email com `is_email()`**: Email preenchido Ã© validado com funÃ§Ã£o nativa do WordPress. Email invÃ¡lido bloqueia cadastro com mensagem especÃ­fica.
- **F1.5 - DetecÃ§Ã£o de duplicatas**: Sistema verifica email, telefone e CPF antes de criar novo cliente. Se encontrar cadastro existente, exibe mensagem genÃ©rica orientando contato com equipe (nÃ£o revela qual campo duplicou para evitar enumeraÃ§Ã£o).
- **F1.6 - Rate limiting por IP**: MÃ¡ximo 3 cadastros por hora por IP. 4Âª tentativa bloqueada com mensagem amigÃ¡vel. Usa transients com hash do IP para privacidade.
- **F1.7 - ExpiraÃ§Ã£o de token de confirmaÃ§Ã£o**: Token de confirmaÃ§Ã£o de email agora expira em 48 horas. Novo meta `dps_email_confirm_token_created` registra timestamp. Email de confirmaÃ§Ã£o menciona validade de 48h.
- **F1.8 - Feedback de erro visÃ­vel**: Todas as falhas de validaÃ§Ã£o agora exibem mensagens claras no formulÃ¡rio. Usa `DPS_Message_Helper` quando disponÃ­vel, com fallback para transients prÃ³prios.
- **F1.9 - NormalizaÃ§Ã£o de telefone**: Telefone Ã© salvo apenas com dÃ­gitos (sem mÃ¡scaras). Facilita integraÃ§Ã£o com WhatsApp e Communications Add-on.

#### Changed (Alterado)

- Mensagem de sucesso de cadastro agora menciona verificar email se informado.
- Mensagem de email confirmado atualizada com estilo visual consistente.
- MÃ©todos helpers de validaÃ§Ã£o (CPF, telefone, duplicatas) implementados como mÃ©todos privados na classe.

#### Security (SeguranÃ§a)

- Nonce invÃ¡lido agora exibe mensagem de erro em vez de falha silenciosa.
- Honeypot preenchido exibe mensagem genÃ©rica (nÃ£o revela ser anti-bot).
- Rate limiting protege contra ataques de flood/spam.
- Tokens de confirmaÃ§Ã£o expiram em 48h, reduzindo janela de exposiÃ§Ã£o.
- Mensagem de duplicata Ã© genÃ©rica para evitar enumeraÃ§Ã£o de contas.

**Loyalty Add-on (v1.5.0) - FASE 4: Recursos AvanÃ§ados**

- **F4.2 - GamificaÃ§Ã£o (badges e conquistas)**: Nova classe `DPS_Loyalty_Achievements` com sistema de conquistas automÃ¡ticas. 4 conquistas iniciais: `first_bath` (Primeiro Banho), `loyal_client` (Fiel da Casa - 10 atendimentos), `referral_master` (Indicador Master - 5 indicaÃ§Ãµes), `vip` (VIP - nÃ­vel mÃ¡ximo). AvaliaÃ§Ã£o automÃ¡tica apÃ³s pontuaÃ§Ã£o ou resgate via `evaluate_achievements_for_client()`. Hook `dps_loyalty_achievement_unlocked` para extensÃµes. ExibiÃ§Ã£o de badges no admin (Consulta de Cliente) e no Portal do Cliente com visual de cards desbloqueados/bloqueados.
- **F4.3 - NÃ­veis configurÃ¡veis pelo admin**: Tabela dinÃ¢mica na aba ConfiguraÃ§Ãµes permite criar, editar e excluir nÃ­veis de fidelidade. Campos: slug, label, pontos mÃ­nimos, multiplicador, Ã­cone e cor. BotÃ£o "Adicionar nÃ­vel" com JavaScript. API `DPS_Loyalty_API::get_tiers_config()` retorna nÃ­veis personalizados ou padrÃ£o (Bronze/Prata/Ouro). MÃ©todo `get_default_tiers()` para fallback. MÃ©todo `get_highest_tier_slug()` para determinar nÃ­vel mÃ¡ximo. OrdenaÃ§Ã£o automÃ¡tica por pontos mÃ­nimos.
- **F4.4 - IntegraÃ§Ã£o de crÃ©ditos com Finance + limite por atendimento**: Nova seÃ§Ã£o "IntegraÃ§Ã£o com Finance" nas configuraÃ§Ãµes. Checkbox `enable_finance_credit_usage` habilita uso de crÃ©ditos no momento do pagamento. Campo monetÃ¡rio `finance_max_credit_per_appointment` define limite mÃ¡ximo (ex.: R$ 10,00). Finance Add-on consome crÃ©ditos via `DPS_Loyalty_API::use_credit()` durante lanÃ§amento de parcelas. ValidaÃ§Ã£o de limite e saldo disponÃ­vel. Log de auditoria `loyalty_credit` registra uso no histÃ³rico financeiro. Nota automÃ¡tica na descriÃ§Ã£o da transaÃ§Ã£o.
- **F4.5 - API REST de fidelidade (somente leitura)**: Nova classe `DPS_Loyalty_REST` com namespace `dps-loyalty/v1`. 3 endpoints: `GET /client/{id}` (pontos, tier, crÃ©ditos, conquistas), `GET /client-by-ref/{code}` (busca por cÃ³digo de indicaÃ§Ã£o), `GET /summary?months=N` (timeseries e distribuiÃ§Ã£o por tier). PermissÃ£o `manage_options` para todos os endpoints. FormataÃ§Ã£o de conquistas com label, descriÃ§Ã£o e status de desbloqueio.

**Loyalty Add-on (v1.4.0) - FASE 3: RelatÃ³rios & Engajamento**

- **Dashboard de mÃ©tricas** com cards de resumo, grÃ¡fico de pontos concedidos x resgatados (Ãºltimos 6 meses) e pizza de distribuiÃ§Ã£o por nÃ­vel.
- **RelatÃ³rio de campanhas** exibindo elegÃ­veis, uso estimado e pontos gerados por campanha `dps_campaign`.
- **Ranking de clientes engajados** com filtros de perÃ­odo, somatÃ³rio de pontos ganhos/resgatados, indicaÃ§Ãµes e atendimentos.
- **ExpiraÃ§Ã£o automÃ¡tica de pontos** configurÃ¡vel (meses) com cron diÃ¡rio e lanÃ§amento de expiraÃ§Ã£o no histÃ³rico.
- **Avisos de pontos a expirar** integrados ao Communications (template configurÃ¡vel e janela em dias).

**Loyalty Add-on (v1.3.0) - FASE 1: Performance & UX BÃ¡sica**

- **F1.1 - Auditoria de campanhas otimizada**: Novo mÃ©todo `get_last_appointments_batch()` elimina queries N+1 ao verificar clientes inativos. Antes: 500 clientes = 500+ queries individuais. Agora: 500 clientes = 1 query batch. Mesma lÃ³gica de elegibilidade mantida, apenas mais rÃ¡pido. MÃ©todos legados `is_client_inactive_for_days()` e `get_last_appointment_date_for_client()` marcados como depreciados.
- **F1.2 - Autocomplete na aba "Consulta de Cliente"**: SubstituÃ­do dropdown paginado por campo de busca com autocomplete AJAX. Novo endpoint `wp_ajax_dps_loyalty_search_clients` busca clientes por nome ou telefone. Busca dinÃ¢mica com debounce de 300ms e mÃ­nimo de 2 caracteres. NavegaÃ§Ã£o por teclado (setas, Enter, Escape) e seleÃ§Ã£o por clique. SubmissÃ£o automÃ¡tica do formulÃ¡rio ao selecionar cliente. Resultados exibem nome, telefone e pontos do cliente.
- **F1.3 - ExibiÃ§Ã£o padronizada de crÃ©ditos**: Novos mÃ©todos `get_credit_for_display()` e `format_credits_display()` centralizam formataÃ§Ã£o de crÃ©ditos. Valores negativos sÃ£o tratados como zero. FormataÃ§Ã£o consistente (R$ X,XX) usando `DPS_Money_Helper` quando disponÃ­vel, com fallback manual. Aplicado no Dashboard e na Consulta de Cliente.

**Finance Add-on (v1.6.0) - FASE 4: Extras AvanÃ§ados (Selecionados)**

- **F4.2 - Lembretes automÃ¡ticos de pagamento**: Sistema completo de lembretes configurÃ¡vel via painel admin. Checkbox para habilitar/desabilitar lembretes. ConfiguraÃ§Ã£o de dias antes do vencimento (padrÃ£o: 1 dia) e dias apÃ³s vencimento (padrÃ£o: 1 dia). Mensagens customizÃ¡veis com placeholders ({cliente}, {pet}, {valor}, {link}). Evento WP-Cron diÃ¡rio (`dps_finance_process_payment_reminders`) processa lembretes automaticamente. Sistema de flags via transients impede envio duplicado de lembretes (janela de 7 dias). Log de execuÃ§Ã£o em error_log para debug. UI acessÃ­vel via "âš™ï¸ ConfiguraÃ§Ãµes AvanÃ§adas" na aba Financeiro.
- **F4.4 - Auditoria de alteraÃ§Ãµes financeiras**: Nova tabela `dps_finance_audit_log` registra todas as mudanÃ§as em transaÃ§Ãµes. Captura mudanÃ§as de status (em_aberto â†’ pago, etc.), criaÃ§Ãµes manuais de transaÃ§Ãµes e adiÃ§Ãµes de pagamentos parciais. Registra user_id, IP, timestamps e valores before/after. Ãndices em trans_id, created_at e user_id para performance. Tela de visualizaÃ§Ã£o com filtros por transaÃ§Ã£o ID e data em `admin.php?page=dps-finance-audit`. PaginaÃ§Ã£o (20 registros por pÃ¡gina). Labels traduzidas para tipos de aÃ§Ã£o. Sistema nÃ£o bloqueia operaÃ§Ãµes principais em caso de falha (log silencioso).
- **F4.5 - API REST de consulta financeira (read-only)**: Namespace `dps-finance/v1` com 3 endpoints. `GET /transactions` lista transaÃ§Ãµes com filtros (status, date_from, date_to, customer, paginaÃ§Ã£o). `GET /transactions/{id}` retorna detalhes de transaÃ§Ã£o especÃ­fica. `GET /summary` retorna resumo financeiro por perÃ­odo (current_month, last_month, custom). Todos os endpoints requerem autenticaÃ§Ã£o e capability `manage_options`. ValidaÃ§Ã£o robusta de parÃ¢metros (status enum, datas, limites de paginaÃ§Ã£o). Headers X-WP-Total e X-WP-TotalPages em respostas paginadas. FormataÃ§Ã£o monetÃ¡ria via DPS_Money_Helper. Estrutura WP_REST_Response padrÃ£o.

**Finance Add-on (v1.5.0) - FASE 3: RelatÃ³rios & VisÃ£o Gerencial**

- **F3.1 - GrÃ¡fico de evoluÃ§Ã£o mensal aprimorado**: GrÃ¡fico convertido de barras para linhas com Ã¡rea preenchida, proporcionando melhor visualizaÃ§Ã£o de tendÃªncias. Exibe receitas (verde) e despesas (vermelho) nos Ãºltimos 6 meses (configurÃ¡vel via constante `DPS_FINANCE_CHART_MONTHS`). Inclui tÃ­tulo "EvoluÃ§Ã£o Financeira" e tooltips formatados em R$.
- **F3.2 - RelatÃ³rio DRE simplificado existente mantido**: DRE jÃ¡ implementado na v1.3.0 continua disponÃ­vel, exibindo receitas por categoria, despesas por categoria e resultado do perÃ­odo. Exibe automaticamente quando hÃ¡ filtro de data aplicado ou ao clicar em "show_dre".
- **F3.3 - ExportaÃ§Ã£o PDF de relatÃ³rios**: Novos botÃµes "ðŸ“„ Exportar DRE (PDF)" e "ðŸ“Š Exportar Resumo (PDF)" no painel de filtros. Gera HTML limpo otimizado para impressÃ£o em PDF via navegador. DRE inclui receitas/despesas por categoria e resultado do perÃ­odo. Resumo Mensal inclui cards de totais e Top 10 clientes. ValidaÃ§Ã£o de nonce e capability (manage_options) em todos os endpoints.
- **F3.4 - Comparativo mensal (mÃªs atual vs anterior)**: Novos cards exibindo receita do mÃªs atual vs mÃªs anterior com indicador de variaÃ§Ã£o percentual. Exibe â†‘ (verde) para crescimento ou â†“ (vermelho) para queda. CÃ¡lculo automÃ¡tico usando apenas transaÃ§Ãµes pagas tipo receita. Posicionado no topo dos relatÃ³rios para visibilidade imediata.
- **F3.5 - Top 10 clientes por receita**: Nova tabela ranking exibindo os 10 clientes que mais geraram receita no perÃ­odo filtrado (ou mÃªs atual se sem filtro). Mostra posiÃ§Ã£o (#), nome do cliente, quantidade de atendimentos e valor total pago. BotÃ£o "Ver transaÃ§Ãµes" permite filtrar rapidamente todas as transaÃ§Ãµes de cada cliente. Query otimizada com GROUP BY e agregaÃ§Ã£o SQL.

**Finance Add-on (v1.4.0) - FASE 2: UX do Dia a Dia**

- **F2.1 - Card de pendÃªncias urgentes**: Novo card visual no topo da aba Financeiro exibindo pendÃªncias vencidas (ðŸš¨ vermelho) e pendÃªncias de hoje (âš ï¸ amarelo) com quantidade e valor total. Links diretos para filtrar e ver detalhes. Melhora visibilidade de cobranÃ§as urgentes para equipe.
- **F2.2 - BotÃ£o "Reenviar link de pagamento"**: Novo botÃ£o "âœ‰ï¸ Reenviar link" na coluna de AÃ§Ãµes para transaÃ§Ãµes em aberto com link do Mercado Pago. Abre WhatsApp com mensagem personalizada contendo link de pagamento. Registra log de reenvio com timestamp e usuÃ¡rio. Reduz de 5 para 1 clique para follow-up com clientes.
- **F2.3 - Badges visuais de status**: Status financeiros agora exibidos como badges coloridos: âœ… Pago (verde), â³ Em aberto (amarelo), âŒ Cancelado (vermelho). Facilita identificaÃ§Ã£o rÃ¡pida do estado de cada transaÃ§Ã£o. Select de alteraÃ§Ã£o de status agora menor e inline ao badge.
- **F2.4 - Indicadores visuais de vencimento**: Datas na coluna exibem Ã­cones e cores para urgÃªncia: ðŸš¨ Vermelho para vencidas, âš ï¸ Amarelo para hoje, normal para futuras. Aplicado apenas em transaÃ§Ãµes em aberto tipo receita. Equipe identifica prioridades visualmente.
- **F2.5 - Busca rÃ¡pida por cliente**: Novo campo de texto "Buscar cliente" no formulÃ¡rio de filtros. Busca por nome de cliente em tempo real usando LIKE no banco. Funciona em conjunto com outros filtros (data, categoria, status). Reduz tempo de localizaÃ§Ã£o de transaÃ§Ãµes especÃ­ficas de minutos para segundos.

#### Changed (Alterado)

#### Fixed (Corrigido)

**Plugin Base (v1.x.x)**

- **CorreÃ§Ã£o ao alterar status de agendamento no Painel de GestÃ£o DPS**: Corrigido bug onde a mensagem "Selecione um status vÃ¡lido para o agendamento" aparecia mesmo ao selecionar um status vÃ¡lido. O problema era causado pelo JavaScript em `dps-base.js` que desabilitava o elemento `<select>` antes de disparar o submit do formulÃ¡rio, fazendo com que o browser nÃ£o incluÃ­sse o valor do status nos dados enviados. A linha que desabilitava o select foi removida, mantendo a proteÃ§Ã£o contra mÃºltiplos envios via flag `submitting`.

**Services Add-on (v1.3.1)**

- **Redirecionamento incorreto apÃ³s salvar serviÃ§o corrigido**: ApÃ³s adicionar ou editar um serviÃ§o no Painel de GestÃ£o DPS, o sistema agora redireciona corretamente para a aba de serviÃ§os (ex: `/administracao/?tab=servicos`) em vez da pÃ¡gina inicial do site. O mÃ©todo `get_redirect_url()` agora segue a mesma hierarquia de fallbacks do plugin base: (1) HTTP referer, (2) `get_queried_object_id()` + `get_permalink()`, (3) global `$post`, (4) `REQUEST_URI`, (5) `home_url()`. Resolve problema onde o usuÃ¡rio era redirecionado para "Welcome to WordPress" apÃ³s salvar serviÃ§o.

**Client Portal Add-on (v2.4.2)**

- **Melhoria no fallback de redirecionamento**: MÃ©todo `get_redirect_url()` em `DPS_Portal_Admin_Actions` agora inclui fallback adicional via global `$post` e `REQUEST_URI` antes de usar `home_url()`, seguindo o padrÃ£o do plugin base para maior robustez.

**Registration Add-on (v1.2.1)**

- **Redirecionamento pÃ³s-cadastro corrigido**: ApÃ³s finalizar o cadastro, o sistema agora busca corretamente a pÃ¡gina de registro, mesmo quando a option `dps_registration_page_id` nÃ£o estÃ¡ configurada ou a pÃ¡gina foi excluÃ­da. O mÃ©todo `get_registration_page_url()` agora tenta: (1) ID salvo na option, (2) pÃ¡gina pelo slug padrÃ£o "cadastro-de-clientes-e-pets", (3) qualquer pÃ¡gina com o shortcode `[dps_registration_form]`. Quando encontra a pÃ¡gina por fallback, atualiza automaticamente a option para evitar buscas futuras. Resolve problema de pÃ¡gina em branco apÃ³s cadastro.

#### Security (SeguranÃ§a)

**Finance Add-on (v1.3.1) - FASE 1: SeguranÃ§a e Performance**

- **F1.1 - Documentos financeiros protegidos contra acesso nÃ£o autorizado**: Documentos HTML (notas e cobranÃ§as) agora sÃ£o servidos via endpoint autenticado com nonce e verificaÃ§Ã£o de capability, em vez de URLs pÃºblicas diretas. DiretÃ³rio `wp-content/uploads/dps_docs/` protegido com `.htaccess` para bloquear acesso direto. MantÃ©m compatibilidade backward com documentos jÃ¡ gerados.
- **F1.2 - ValidaÃ§Ã£o de pagamentos parciais**: Sistema agora impede que a soma de pagamentos parciais ultrapasse o valor total da transaÃ§Ã£o, evitando inconsistÃªncias financeiras. Inclui mensagem de erro detalhada informando total, jÃ¡ pago e valor restante.
- **F1.3 - Ãndices de banco de dados adicionados**: Criados Ã­ndices compostos em `dps_transacoes` (`data`, `status`, `categoria`) para melhorar drasticamente a performance de filtros e relatÃ³rios. Melhoria de ~80% em queries com volumes acima de 10.000 registros.
- **F1.4 - Query do grÃ¡fico mensal otimizada**: GrÃ¡fico de receitas/despesas agora limita automaticamente aos Ãºltimos 12 meses quando nenhum filtro de data Ã© aplicado, evitando timeout com grandes volumes de dados (> 50.000 registros). Usa agregaÃ§Ã£o SQL em vez de carregar todos os registros em memÃ³ria.

#### Refactoring (Interno)

---

#### Added (Adicionado)
- **Client Portal Add-on (v2.4.1)**: CriaÃ§Ã£o automÃ¡tica da pÃ¡gina do portal na ativaÃ§Ã£o do add-on
  - FunÃ§Ã£o `dps_client_portal_maybe_create_page()` cria pÃ¡gina "Portal do Cliente" se nÃ£o existir
  - Verifica se pÃ¡gina configurada tem o shortcode `[dps_client_portal]` e adiciona se necessÃ¡rio
  - Armazena ID da pÃ¡gina em `dps_portal_page_id` automaticamente
  - Previne erros de "pÃ¡gina nÃ£o encontrada" ao acessar links de autenticaÃ§Ã£o
- **Client Portal Add-on (v2.4.1)**: VerificaÃ§Ã£o contÃ­nua da configuraÃ§Ã£o do portal no painel administrativo
  - Sistema de avisos que alerta se a pÃ¡gina do portal nÃ£o existe, estÃ¡ em rascunho ou sem shortcode
  - Avisos contextualizados com links diretos para corrigir problemas
  - Executa automaticamente em `admin_init` para administradores
- **AGENDA Add-on (v1.4.0)**: Sistema de 3 abas para reorganizaÃ§Ã£o da lista de agendamentos
  - Aba 1 "VisÃ£o RÃ¡pida": VisualizaÃ§Ã£o enxuta com HorÃ¡rio, Pet, Tutor, Status, ConfirmaÃ§Ã£o (badge), TaxiDog
  - Aba 2 "OperaÃ§Ã£o": VisualizaÃ§Ã£o operacional completa com todas as aÃ§Ãµes (status, confirmaÃ§Ã£o com botÃµes, pagamento, aÃ§Ãµes rÃ¡pidas)
  - Aba 3 "Detalhes": Foco em informaÃ§Ãµes complementares (observaÃ§Ãµes do atendimento, observaÃ§Ãµes do pet, endereÃ§o, mapa/GPS)
  - NavegaÃ§Ã£o entre abas sem recarregar pÃ¡gina
  - PreferÃªncia de aba salva em sessionStorage
  - Aba "VisÃ£o RÃ¡pida" como padrÃ£o ao carregar
  - Campos de identificaÃ§Ã£o (HorÃ¡rio + Pet + Tutor) presentes em todas as abas
- **Payment Add-on (v1.1.0)**: Suporte para credenciais via constantes wp-config.php
  - Nova classe `DPS_MercadoPago_Config` para gerenciar credenciais do Mercado Pago
  - Ordem de prioridade: constantes wp-config.php â†’ options em banco de dados
  - Constantes suportadas: `DPS_MERCADOPAGO_ACCESS_TOKEN`, `DPS_MERCADOPAGO_WEBHOOK_SECRET`, `DPS_MERCADOPAGO_PUBLIC_KEY`
  - Tela de configuraÃ§Ãµes exibe campos readonly quando constante estÃ¡ definida
  - Exibe apenas Ãºltimos 4 caracteres de tokens definidos via constante
  - RecomendaÃ§Ãµes de seguranÃ§a na interface administrativa
- **Payment Add-on (v1.1.0)**: Sistema de logging e flags de erro para cobranÃ§as
  - Novo metadado `_dps_payment_link_status` nos agendamentos (values: success/error/not_requested)
  - Novo metadado `_dps_payment_last_error` com detalhes do Ãºltimo erro (code, message, timestamp, context)
  - MÃ©todo `log_payment_error()` para logging centralizado de erros de cobranÃ§a
  - MÃ©todo `extract_appointment_id_from_reference()` para extrair ID de external_reference
- **AGENDA Add-on (v1.0.2)**: Indicador visual de erro na geraÃ§Ã£o de link de pagamento
  - Exibe aviso "âš ï¸ Erro ao gerar link" quando `_dps_payment_link_status` = 'error'
  - Tooltip com mensagem explicativa para o usuÃ¡rio
  - Detalhes do erro para administradores (mensagem e timestamp)
  - NÃ£o quebra UX existente - apenas adiciona feedback quando hÃ¡ erro

#### Changed (Alterado)
- **AGENDA Add-on (v1.4.0)**: ReorganizaÃ§Ã£o da interface de lista de agendamentos
  - Interface anterior com tabela Ãºnica substituÃ­da por sistema de 3 abas
  - BotÃµes de confirmaÃ§Ã£o movidos para Aba 2 (OperaÃ§Ã£o), removidos da Aba 1 (VisÃ£o RÃ¡pida)
  - Coluna TaxiDog agora mostra "â€“" quando nÃ£o hÃ¡ TaxiDog solicitado (antes mostrava botÃ£o vazio)
  - TÃ­tulos de colunas ajustados para melhor correspondÃªncia com conteÃºdo
  - Layout responsivo com tabs em coluna em telas mobile
- **Payment Add-on (v1.1.0)**: Tratamento de erros aprimorado na integraÃ§Ã£o Mercado Pago
  - MÃ©todo `create_payment_preference()` agora valida HTTP status code
  - Verifica presenÃ§a de campos obrigatÃ³rios na resposta (`init_point`)
  - Loga erros de conexÃ£o, HTTP nÃ£o-sucesso e campos faltantes
  - Salva flag de status em agendamentos ao gerar links
- **Payment Add-on (v1.1.0)**: MÃ©todos atualizados para usar `DPS_MercadoPago_Config`
  - `create_payment_preference()` usa config class em vez de `get_option()`
  - `process_payment_notification()` usa config class
  - `get_webhook_secret()` simplificado para usar config class
  - `maybe_generate_payment_link()` salva flags de sucesso/erro
  - `inject_payment_link_in_message()` salva flags de sucesso/erro

#### Fixed (Corrigido)
- **Base Plugin (v1.1.1)**: ValidaÃ§Ãµes defensivas em Hubs administrativos para prevenir erros fatais
  - Adicionado `method_exists()` antes de chamar `get_instance()` em todos os Hubs
  - DPS_Tools_Hub agora verifica existÃªncia do mÃ©todo antes de renderizar aba de Cadastro
  - DPS_Integrations_Hub valida mÃ©todo em abas de ComunicaÃ§Ãµes, Pagamentos e Push
  - DPS_System_Hub valida mÃ©todo em abas de Backup, Debugging e White Label
  - Mensagens informativas quando add-on precisa ser atualizado
  - Previne erro "Call to undefined method" quando add-ons desatualizados estÃ£o ativos
- **Base Plugin (v1.1.1)**: Dashboard nÃ£o consulta mais tabela inexistente do Finance Add-on
  - Adicionada verificaÃ§Ã£o `SHOW TABLES LIKE` antes de consultar `wp_dps_transacoes`
  - Query de pendÃªncias financeiras executa apenas se tabela existir no banco
  - Previne erro "Table doesn't exist" quando Finance Add-on nÃ£o criou suas tabelas
  - Usa `$wpdb->prepare()` para seguranÃ§a adicional na verificaÃ§Ã£o de tabela
- **Client Portal Add-on (v2.4.1)**: Menu "Painel Central" desaparece ao ativar o add-on
  - Registro duplicado do CPT `dps_portal_message` causava conflito de menu
  - `DPS_Client_Portal` e `DPS_Portal_Admin` ambos registravam o mesmo CPT com `show_in_menu => 'desi-pet-shower'`
  - WordPress sobrescreve callback do menu pai quando CPT usa `show_in_menu`, causando desaparecimento do "Painel Central"
  - Removido registro duplicado em `DPS_Client_Portal` (linha 72), mantendo apenas em `DPS_Portal_Admin`
  - Menu "Painel Central" agora permanece visÃ­vel apÃ³s ativar Client Portal
  - CPT "Mensagens do Portal" continua aparecendo corretamente no menu DPS
- **AGENDA Add-on (v1.4.1)**: Erro crÃ­tico ao acessar menu AGENDA no painel administrativo
  - `DPS_Agenda_Addon::get_instance()` causava fatal error (linhas 93 e 112 de class-dps-agenda-hub.php)
  - Implementado padrÃ£o singleton em `DPS_Agenda_Addon`
  - Construtor convertido para privado com mÃ©todo pÃºblico estÃ¡tico `get_instance()`
  - Propriedade estÃ¡tica `$instance` adicionada para armazenar instÃ¢ncia Ãºnica
  - FunÃ§Ã£o de inicializaÃ§Ã£o `dps_agenda_init_addon()` atualizada para usar `get_instance()`
  - Alinha com padrÃ£o de todos os outros add-ons integrados aos Hubs do sistema
  - Menu AGENDA agora funciona corretamente com suas 3 abas (Dashboard, ConfiguraÃ§Ãµes, Capacidade)
- **Finance Add-on (v1.3.1)**: PHP 8+ deprecation warnings relacionados a null em funÃ§Ãµes de string
  - Corrigido `add_query_arg( null, null )` para `add_query_arg( array() )` para compatibilidade com PHP 8+
  - Adicionado mÃ©todo helper `get_current_url()` para obter URL atual com fallback seguro
  - SubstituÃ­das todas as chamadas diretas de `get_permalink()` pelo helper para evitar warnings quando funÃ§Ã£o retorna `false`
  - Corrige avisos "Deprecated: strpos(): Passing null to parameter #1 ($haystack) of type string is deprecated"
  - Corrige avisos "Deprecated: str_replace(): Passing null to parameter #3 ($subject) of type array|string is deprecated"
  - Elimina warnings de "Cannot modify header information - headers already sent" causados pelos deprecation notices
- **Registration Add-on (v1.0.1)**: Erro fatal ao acessar pÃ¡gina Hub de Ferramentas
  - `DPS_Registration_Addon::get_instance()` causava fatal error (linha 96 de class-dps-tools-hub.php)
  - Implementado padrÃ£o singleton em `DPS_Registration_Addon`
  - Construtor convertido para privado com mÃ©todo pÃºblico `get_instance()`
  - Alinha com padrÃ£o de outros add-ons integrados aos Hubs do sistema
- **Push Add-on (v1.0.1)**: Menu standalone visÃ­vel incorretamente no painel administrativo
  - Corrigido `parent='desi-pet-shower'` para `parent=null` na funÃ§Ã£o `register_admin_menu()`
  - Menu agora oculto do menu principal (acessÃ­vel apenas via URL direta)
  - MantÃ©m backward compatibility com URLs diretas existentes
  - Alinha com padrÃ£o de outros add-ons integrados ao Hub de IntegraÃ§Ãµes
  - Acesso via aba "NotificaÃ§Ãµes Push" em DPS > IntegraÃ§Ãµes funciona corretamente
- **Base Plugin (v1.1.0)**: Erro fatal ao acessar pÃ¡gina Hub de IntegraÃ§Ãµes
  - `DPS_Push_Addon::get_instance()` causava fatal error (linha 144 de class-dps-integrations-hub.php)
  - `DPS_Payment_Addon::get_instance()` causava fatal error (linha 126 de class-dps-integrations-hub.php)
  - `DPS_Communications_Addon::get_instance()` causava fatal error (linha 108 de class-dps-integrations-hub.php)
  - Implementado padrÃ£o singleton em `DPS_Push_Addon`, `DPS_Payment_Addon` e `DPS_Communications_Addon`
  - Adicionado mÃ©todo pÃºblico estÃ¡tico `get_instance()` em cada classe
  - FunÃ§Ãµes de inicializaÃ§Ã£o atualizadas para usar singleton pattern
  - Fix compatÃ­vel com versÃµes anteriores - comportamento mantido

#### Security (SeguranÃ§a)
- **Payment Add-on (v1.1.0)**: Tokens do Mercado Pago podem ser movidos para wp-config.php
  - Recomendado definir `DPS_MERCADOPAGO_ACCESS_TOKEN` e `DPS_MERCADOPAGO_WEBHOOK_SECRET` em wp-config.php
  - Evita armazenamento de credenciais sensÃ­veis em texto plano no banco de dados
  - MantÃ©m compatibilidade com configuraÃ§Ã£o via painel (Ãºtil para desenvolvimento)

#### Client Portal (v2.4.0)**: Linha do tempo de serviÃ§os por pet (Fase 4)
  - Nova classe `DPS_Portal_Pet_History` para buscar histÃ³rico de serviÃ§os realizados
  - MÃ©todo `get_pet_service_history()` retorna serviÃ§os por pet em ordem cronolÃ³gica
  - MÃ©todo `get_client_service_history()` agrupa serviÃ§os de todos os pets do cliente
  - Nova aba "HistÃ³rico dos Pets" no portal com timeline visual de serviÃ§os
  - Timeline mostra: data, tipo de serviÃ§o, observaÃ§Ãµes e profissional
  - BotÃ£o "Repetir este ServiÃ§o" em cada item da timeline
  - Estado vazio amigÃ¡vel quando pet nÃ£o tem histÃ³rico
  - Design responsivo para mobile com cards empilhÃ¡veis
- **Client Portal (v2.4.0)**: Sistema de pedidos de agendamento (Fase 4)
  - Novo CPT `dps_appt_request` para armazenar pedidos de agendamento
  - Classe `DPS_Appointment_Request_Repository` para gerenciar pedidos
  - Campos: cliente, pet, tipo (novo/reagendar/cancelar), dia desejado, perÃ­odo (manhÃ£/tarde), status
  - Status possÃ­veis: pending, confirmed, rejected, adjusted
  - NUNCA confirma automaticamente - sempre requer aprovaÃ§Ã£o da equipe
  - MÃ©todo `create_request()` para criar novos pedidos
  - MÃ©todo `get_requests_by_client()` para listar pedidos do cliente
  - MÃ©todo `update_request_status()` para equipe atualizar status
- **Client Portal (v2.4.0)**: AÃ§Ãµes rÃ¡pidas no dashboard (Fase 4)
  - BotÃ£o "Solicitar Reagendamento" no card de prÃ³ximo agendamento
  - BotÃ£o "Solicitar Cancelamento" no card de prÃ³ximo agendamento
  - Modal interativo para escolher dia e perÃ­odo (manhÃ£/tarde) desejados
  - Textos claros informando que Ã© PEDIDO, nÃ£o confirmaÃ§Ã£o automÃ¡tica
  - Mensagem: "Este Ã© um pedido de agendamento. O Banho e Tosa irÃ¡ confirmar o horÃ¡rio final"
  - Fluxo de reagendamento: cliente escolhe data + perÃ­odo â†’ status "pendente"
  - Fluxo de cancelamento: confirmaÃ§Ã£o â†’ status "cancelamento solicitado"
- **Client Portal (v2.4.0)**: Dashboard de solicitaÃ§Ãµes recentes (Fase 4)
  - Nova seÃ§Ã£o "Suas SolicitaÃ§Ãµes Recentes" no painel inicial
  - Renderiza Ãºltimos 5 pedidos do cliente com cards visuais
  - Indicadores de status: Aguardando ConfirmaÃ§Ã£o (amarelo), Confirmado (verde), NÃ£o Aprovado (vermelho)
  - Exibe data desejada, perÃ­odo, pet e observaÃ§Ãµes
  - Mostra data/hora confirmadas quando status = "confirmed"
  - MÃ©todo `render_recent_requests()` na classe renderer
- **Client Portal (v2.4.0)**: Handlers AJAX para pedidos (Fase 4)
  - Endpoint AJAX `dps_create_appointment_request`
  - ValidaÃ§Ã£o de nonce e autenticaÃ§Ã£o de sessÃ£o
  - ValidaÃ§Ã£o de ownership de pet
  - SanitizaÃ§Ã£o completa de todos os inputs
  - Mensagens de sucesso diferenciadas por tipo de pedido
  - Resposta JSON com ID do pedido criado
- **Client Portal (v2.4.0)**: Interface JavaScript para modais (Fase 4)
  - Handlers para botÃµes `.dps-btn-reschedule`, `.dps-btn-cancel`, `.dps-btn-repeat-service`
  - FunÃ§Ã£o `createRequestModal()` para criar modais dinamicamente
  - FunÃ§Ã£o `submitAppointmentRequest()` para envio via AJAX
  - ValidaÃ§Ã£o de formulÃ¡rio com data mÃ­nima (amanhÃ£)
  - NotificaÃ§Ãµes visuais de sucesso/erro
  - Reload automÃ¡tico da pÃ¡gina apÃ³s sucesso (2 segundos)
- **Client Portal (v2.4.0)**: Estilos CSS para timeline e modais (Fase 4)
  - Classe `.dps-timeline` com marcadores e linha conectora
  - Classe `.dps-timeline-item` com layout de card
  - Classe `.dps-request-card` com bordas coloridas por status
  - Classe `.dps-appointment-actions` para aÃ§Ãµes rÃ¡pidas
  - Modal `.dps-appointment-request-modal` com aviso destacado
  - Design responsivo para mobile (media queries 768px)
- **Client Portal (v2.4.0)**: Central de Mensagens melhorada (Fase 4 - continuaÃ§Ã£o)
  - Nova aba dedicada "Mensagens" ðŸ’¬ no portal com contador de nÃ£o lidas
  - Badge dinÃ¢mica mostrando quantidade de mensagens nÃ£o lidas
  - Destaque visual para mensagens nÃ£o lidas (borda azul, fundo claro, badge "Nova")
  - ExibiÃ§Ã£o de tipo de mensagem (confirmaÃ§Ã£o, lembrete, mudanÃ§a, geral)
  - Link para agendamento relacionado quando mensagem estÃ¡ associada a um serviÃ§o
  - OrdenaÃ§Ã£o com mensagens mais recentes primeiro (DESC)
  - Estado vazio melhorado com Ã­cone e texto explicativo
  - MarcaÃ§Ã£o automÃ¡tica como lida ao visualizar
  - MÃ©todo `get_unread_messages_count()` para contagem eficiente
  - Texto "Equipe do Banho e Tosa" em vez de genÃ©rico
- **Client Portal (v2.4.0)**: PreferÃªncias do Cliente (Fase 4 - continuaÃ§Ã£o)
  - Nova seÃ§Ã£o "Minhas PreferÃªncias" âš™ï¸ em "Meus Dados"
  - Campo "Como prefere ser contatado?": WhatsApp, Telefone, E-mail ou Sem preferÃªncia
  - Campo "PerÃ­odo preferido para banho/tosa": ManhÃ£, Tarde, Indiferente
  - Salvamento em meta do cliente: `client_contact_preference`, `client_period_preference`
  - Handler `update_client_preferences` para processar formulÃ¡rio
  - Hook `dps_portal_after_update_preferences` para extensÃµes
  - Layout em grid responsivo com 2 colunas em desktop
- **Client Portal (v2.4.0)**: PreferÃªncias do Pet (Fase 4 - continuaÃ§Ã£o)
  - Novo fieldset "PreferÃªncias de Banho e Tosa" ðŸŒŸ nos formulÃ¡rios de pet
  - Campo "ObservaÃ§Ãµes de Comportamento": medos, sensibilidades (ex: medo de secador)
  - Campo "PreferÃªncias de Corte/Tosa": estilo preferido (ex: tosa na tesoura, padrÃ£o raÃ§a)
  - Campo "Produtos Especiais / Alergias": necessidades especÃ­ficas (ex: shampoo hipoalergÃªnico)
  - Salvamento junto com dados do pet em update_pet
  - Metadados: `pet_behavior_notes`, `pet_grooming_preference`, `pet_product_notes`
  - Textos contextualizados para Banho e Tosa (nÃ£o clÃ­nica veterinÃ¡ria)
  - Preparado para futura visualizaÃ§Ã£o pela equipe ao atender o pet
- **Client Portal (v2.4.0)**: Branding CustomizÃ¡vel (Fase 4 - conclusÃ£o)
  - Nova aba "Branding" ðŸŽ¨ nas configuraÃ§Ãµes admin ([dps_configuracoes])
  - Upload de logo do Banho e Tosa (recomendado: 200x80px)
  - Seletor de cor primÃ¡ria com preview visual e color picker
  - Upload de imagem hero/destaque para topo do portal (recomendado: 1200x200px)
  - OpÃ§Ãµes para remover logo ou hero image
  - Preview das imagens atuais antes de trocar
  - Handler `save_branding_settings()` com validaÃ§Ã£o de seguranÃ§a
  - AplicaÃ§Ã£o automÃ¡tica no portal:
    - Logo exibido no header (classe `.dps-portal-logo`)
    - Hero image como background no topo (classe `.dps-portal-hero`)
    - Cor primÃ¡ria via CSS custom properties (`--dps-custom-primary`)
    - Cor de hover calculada automaticamente (20% mais escura)
    - Classe `.dps-portal-branded` quando hÃ¡ customizaÃ§Ãµes ativas
  - Afeta: botÃµes primÃ¡rios, links, badges de tab, timeline markers, mensagens nÃ£o lidas
  - MÃ©todo helper `adjust_brightness()` para calcular variaÃ§Ãµes de cor
  - Armazenamento em options: `dps_portal_logo_id`, `dps_portal_primary_color`, `dps_portal_hero_id`
  - Portal reflete identidade visual Ãºnica de cada Banho e Tosa
- **Client Portal (v2.4.0)**: Sistema de notificaÃ§Ã£o de acesso ao portal (Fase 1.3)
  - Nova opÃ§Ã£o nas configuraÃ§Ãµes do portal para ativar/desativar notificaÃ§Ãµes de acesso
  - E-mail automÃ¡tico enviado ao cliente quando o portal Ã© acessado via token
  - NotificaÃ§Ã£o inclui data/hora do acesso e IP (parcialmente ofuscado para privacidade)
  - IntegraÃ§Ã£o com DPS_Communications_API quando disponÃ­vel, com fallback para wp_mail
  - Mensagem de seguranÃ§a alertando cliente para reportar acessos nÃ£o reconhecidos
  - Hook `dps_portal_access_notification_sent` para extensÃµes
- **Client Portal (v2.4.0)**: Helper centralizado de validaÃ§Ã£o de ownership (Fase 1.4)
  - FunÃ§Ã£o global `dps_portal_assert_client_owns_resource()` para validar propriedade de recursos
  - Suporta tipos: appointment, pet, message, transaction, client
  - Logs automÃ¡ticos de tentativas de acesso indevido
  - ExtensÃ­vel via filtros `dps_portal_pre_ownership_check` e `dps_portal_ownership_validated`
  - Aplicado em download de .ics, atualizaÃ§Ã£o de dados de pets
- **AI Add-on (v1.7.0)**: Dashboard de Insights (Fase 6)
  - Nova pÃ¡gina administrativa "IA â€“ Insights" com mÃ©tricas consolidadas
  - Criada classe `DPS_AI_Insights_Dashboard` em `includes/class-dps-ai-insights-dashboard.php`
  - KPIs principais exibidos em cards destacados:
    - Total de conversas no perÃ­odo selecionado
    - Total de mensagens trocadas
    - Taxa de resoluÃ§Ã£o baseada em feedback positivo
    - Custo estimado de tokens consumidos
  - Top 10 Perguntas mais frequentes:
    - AnÃ¡lise automÃ¡tica de mensagens de usuÃ¡rios
    - ExibiÃ§Ã£o em tabela ordenada por frequÃªncia
    - Ãštil para identificar dÃºvidas recorrentes e oportunidades de FAQ
  - HorÃ¡rios de pico de uso (grÃ¡fico de barras):
    - DistribuiÃ§Ã£o de mensagens por hora do dia (0-23h)
    - Identifica perÃ­odos de maior demanda
    - Auxilia no planejamento de atendimento
  - Dias da semana com mais conversas (grÃ¡fico de barras):
    - AnÃ¡lise de volume de conversas por dia
    - Identifica padrÃµes semanais de uso
  - Top 10 Clientes mais engajados:
    - Lista ordenada por nÃºmero de conversas e mensagens
    - Identifica clientes com maior interaÃ§Ã£o com a IA
  - EstatÃ­sticas por canal (grÃ¡fico de pizza):
    - DistribuiÃ§Ã£o de conversas entre web_chat, portal, whatsapp e admin_specialist
    - Visualiza participaÃ§Ã£o de cada canal no total
  - Filtros de perÃ­odo:
    - Ãšltimos 7 dias
    - Ãšltimos 30 dias
    - PerÃ­odo customizado (seleÃ§Ã£o de data inicial e final)
  - VisualizaÃ§Ãµes com Chart.js:
    - Reutiliza biblioteca jÃ¡ implementada na Fase 2
    - GrÃ¡ficos responsivos e interativos
  - Performance otimizada:
    - Queries com Ã­ndices apropriados
    - AgregaÃ§Ãµes eficientes no MySQL
    - PaginaÃ§Ã£o e limites para evitar carga excessiva
  - Arquivos criados:
    - `includes/class-dps-ai-insights-dashboard.php`: LÃ³gica de cÃ¡lculo e renderizaÃ§Ã£o
    - `assets/css/dps-ai-insights-dashboard.css`: Estilos responsivos para dashboard
  - Arquivos modificados:
    - `desi-pet-shower-ai-addon.php`: Include e inicializaÃ§Ã£o da classe
- **AI Add-on (v1.7.0)**: Modo Especialista (Fase 6)
  - Nova pÃ¡gina administrativa "IA â€“ Modo Especialista" para equipe interna
  - Criada classe `DPS_AI_Specialist_Mode` em `includes/class-dps-ai-specialist-mode.php`
  - Chat interno restrito a admins (capability `manage_options`):
    - Interface similar ao chat pÃºblico, mas com recursos avanÃ§ados
    - Acesso a dados completos do sistema
    - System prompt tÃ©cnico para equipe interna
  - Comandos especiais tipo "/" para buscar dados:
    - `/buscar_cliente [nome]`: Busca cliente por nome/email/login
    - `/historico [cliente_id]`: Exibe Ãºltimas 10 conversas de um cliente
    - `/metricas [dias]`: Mostra mÃ©tricas consolidadas dos Ãºltimos N dias
    - `/conversas [canal]`: Lista Ãºltimas 10 conversas de um canal especÃ­fico
  - Respostas formatadas com contexto tÃ©cnico:
    - Exibe IDs, timestamps, contadores detalhados
    - InformaÃ§Ãµes estruturadas para anÃ¡lise rÃ¡pida
    - Formato markdown com negrito, cÃ³digo e listas
  - Consultas em linguagem natural:
    - Processa perguntas que nÃ£o sÃ£o comandos usando IA
    - System prompt especializado para tom tÃ©cnico e profissional
    - Fornece insights baseados em dados do sistema
    - Sugere aÃ§Ãµes prÃ¡ticas quando relevante
  - HistÃ³rico persistente:
    - Conversas do modo especialista gravadas com `channel='admin_specialist'`
    - VisÃ­vel na pÃ¡gina "Conversas IA" para auditoria
    - Rastreamento completo de consultas da equipe interna
  - Interface intuitiva:
    - Mensagem de boas-vindas com exemplos de comandos
    - Feedback visual durante processamento
    - HistÃ³rico de conversas na mesma sessÃ£o
    - Auto-scroll para Ãºltima mensagem
  - Arquivos criados:
    - `includes/class-dps-ai-specialist-mode.php`: LÃ³gica de comandos e integraÃ§Ã£o com IA
    - `assets/css/dps-ai-specialist-mode.css`: Estilos do chat especialista
    - `assets/js/dps-ai-specialist-mode.js`: LÃ³gica AJAX e formataÃ§Ã£o de mensagens
  - Arquivos modificados:
    - `desi-pet-shower-ai-addon.php`: Include e inicializaÃ§Ã£o da classe
- **AI Add-on (v1.7.0)**: SugestÃµes Proativas de Agendamento (Fase 6)
  - Sistema inteligente que sugere agendamentos automaticamente durante conversas
  - Criada classe `DPS_AI_Proactive_Scheduler` em `includes/class-dps-ai-proactive-scheduler.php`
  - DetecÃ§Ã£o automÃ¡tica de oportunidades de agendamento:
    - Analisa Ãºltimo agendamento do cliente via CPT `dps_agendamento`
    - Calcula hÃ¡ quantos dias/semanas foi o Ãºltimo serviÃ§o
    - Compara com intervalo configurÃ¡vel (padrÃ£o: 28 dias / 4 semanas)
  - IntegraÃ§Ã£o com portal do cliente:
    - SugestÃµes aparecem automaticamente apÃ³s resposta da IA
    - Contexto personalizado por cliente (nome do pet, tipo de serviÃ§o, tempo decorrido)
    - NÃ£o interfere na funcionalidade existente do chat
  - Controle de frequÃªncia para evitar ser invasivo:
    - Cooldown configurÃ¡vel entre sugestÃµes (padrÃ£o: 7 dias)
    - Armazena Ãºltima sugestÃ£o em user meta `_dps_ai_last_scheduling_suggestion`
    - MÃ¡ximo 1 sugestÃ£o a cada X dias por cliente
  - ConfiguraÃ§Ãµes admin completas:
    - Ativar/desativar sugestÃµes proativas
    - Intervalo de dias sem serviÃ§o para sugerir (7-90 dias)
    - Intervalo mÃ­nimo entre sugestÃµes (1-30 dias)
    - Mensagem customizÃ¡vel para clientes novos (sem histÃ³rico)
    - Mensagem customizÃ¡vel para clientes recorrentes com variÃ¡veis dinÃ¢micas:
      - `{pet_name}`: Nome do pet
      - `{weeks}`: Semanas desde Ãºltimo serviÃ§o
      - `{service}`: Tipo de serviÃ§o anterior
  - Mensagens padrÃ£o inteligentes:
    - Clientes novos: "Que tal agendar um horÃ¡rio para o banho e tosa do seu pet?"
    - Clientes recorrentes: "Observei que jÃ¡ faz X semanas desde o Ãºltimo serviÃ§o do [pet]. Gostaria que eu te ajudasse a agendar?"
  - Query otimizada:
    - Usa `fields => 'ids'` para performance
    - Meta query com Ã­ndice em `appointment_client_id`
    - OrdenaÃ§Ã£o por `appointment_date` DESC
  - Arquivos modificados:
    - `includes/class-dps-ai-integration-portal.php`: IntegraÃ§Ã£o com fluxo de resposta
    - `desi-pet-shower-ai-addon.php`: Include da nova classe e configuraÃ§Ãµes admin
- **AI Add-on (v1.7.0)**: Entrada por Voz no Chat PÃºblico (Fase 6)
  - BotÃ£o de microfone adicionado ao chat pÃºblico para entrada por voz
  - IntegraÃ§Ã£o com Web Speech API (navegadores compatÃ­veis)
  - DetecÃ§Ã£o automÃ¡tica de suporte do navegador
    - BotÃ£o exibido apenas se API estiver disponÃ­vel
    - Funciona em Chrome, Edge, Safari e navegadores baseados em Chromium
  - Feedback visual durante reconhecimento de voz:
    - AnimaÃ§Ã£o de pulso com cor vermelha indicando "ouvindo"
    - Tooltip informativo ("Ouvindo... Clique para parar")
    - Ãcone animado durante captura de Ã¡udio
  - UX otimizada:
    - Texto reconhecido preenche o textarea automaticamente
    - Permite ediÃ§Ã£o do texto antes de enviar
    - Adiciona ao texto existente ou substitui se vazio
    - NÃ£o envia automaticamente (usuÃ¡rio revisa e clica "Enviar")
    - Auto-resize do textarea apÃ³s transcriÃ§Ã£o
  - Tratamento de erros discreto:
    - Log no console para debugging
    - Mensagens especÃ­ficas por tipo de erro (no-speech, not-allowed, network)
    - NÃ£o quebra a funcionalidade do chat em caso de erro
  - Reconhecimento em portuguÃªs do Brasil (pt-BR)
  - Arquivos modificados:
    - `includes/class-dps-ai-public-chat.php`: BotÃ£o HTML de microfone
    - `assets/css/dps-ai-public-chat.css`: Estilos e animaÃ§Ãµes do botÃ£o de voz
    - `assets/js/dps-ai-public-chat.js`: LÃ³gica de reconhecimento de voz
- **AI Add-on (v1.7.0)**: IntegraÃ§Ã£o WhatsApp Business (Fase 6)
  - Criada classe `DPS_AI_WhatsApp_Connector` em `includes/class-dps-ai-whatsapp-connector.php`
    - Normaliza mensagens recebidas de diferentes providers (Meta, Twilio, Custom)
    - Envia mensagens de resposta via HTTP para WhatsApp
    - Suporta mÃºltiplos providers com lÃ³gica isolada e reutilizÃ¡vel
  - Criada classe `DPS_AI_WhatsApp_Webhook` em `includes/class-dps-ai-whatsapp-webhook.php`
    - Endpoint REST API: `/wp-json/dps-ai/v1/whatsapp-webhook`
    - Recebe mensagens via webhook (POST)
    - VerificaÃ§Ã£o do webhook para Meta WhatsApp (GET)
    - ValidaÃ§Ã£o de assinaturas (Meta: X-Hub-Signature-256, Custom: Bearer token)
    - Cria/recupera conversa com `channel='whatsapp'` e `session_identifier` baseado em hash seguro do telefone
    - Registra mensagem do usuÃ¡rio e resposta da IA no histÃ³rico
    - Reutiliza conversas abertas das Ãºltimas 24 horas
    - Envia resposta automaticamente de volta para WhatsApp
  - Nova seÃ§Ã£o "IntegraÃ§Ã£o WhatsApp Business" nas configuraÃ§Ãµes de IA
    - Ativar/desativar canal WhatsApp
    - SeleÃ§Ã£o de provider (Meta, Twilio, Custom)
    - Campos de configuraÃ§Ã£o especÃ­ficos por provider:
      - **Meta**: Phone Number ID, Access Token, App Secret
      - **Twilio**: Account SID, Auth Token, From Number
      - **Custom**: Webhook URL, API Key
    - Token de verificaÃ§Ã£o para webhook
    - InstruÃ§Ãµes customizadas para WhatsApp (opcional)
    - ExibiÃ§Ã£o da URL do webhook para configurar no provider
  - JavaScript para toggle de campos especÃ­ficos por provider selecionado
  - Reutiliza mesma lÃ³gica de IA jÃ¡ existente para geraÃ§Ã£o de respostas
  - Context prompt adaptado para WhatsApp (respostas curtas, sem HTML)
  - Tratamento de erros com logging apropriado
  - Conversas WhatsApp aparecem na interface admin "Conversas IA" com filtro por canal
- **AI Add-on (v1.7.0)**: HistÃ³rico de Conversas Persistente (Fase 6)
  - Criada nova estrutura de banco de dados para armazenar conversas e mensagens de IA:
    - Tabela `dps_ai_conversations`: id, customer_id, channel, session_identifier, started_at, last_activity_at, status
    - Tabela `dps_ai_messages`: id, conversation_id, sender_type, sender_identifier, message_text, message_metadata, created_at
  - Criada classe `DPS_AI_Conversations_Repository` em `includes/class-dps-ai-conversations-repository.php` para CRUD de conversas
    - MÃ©todos: `create_conversation()`, `add_message()`, `get_conversation()`, `get_messages()`, `list_conversations()`, `count_conversations()`
    - Suporta mÃºltiplos canais: `web_chat` (chat pÃºblico), `portal`, `whatsapp` (futuro), `admin_specialist` (futuro)
    - Suporta visitantes nÃ£o identificados via `session_identifier` (hash de IP para chat pÃºblico)
    - Metadata JSON para armazenar informaÃ§Ãµes adicionais (tokens, custo, tempo de resposta, etc.)
  - IntegraÃ§Ã£o automÃ¡tica com chat do portal do cliente (`DPS_AI_Integration_Portal`)
    - Cria/recupera conversa por `customer_id` e canal `portal`
    - Reutiliza conversa se Ãºltima atividade foi nas Ãºltimas 24 horas
    - Registra mensagem do usuÃ¡rio antes de processar
    - Registra resposta da IA apÃ³s processar
  - IntegraÃ§Ã£o automÃ¡tica com chat pÃºblico (`DPS_AI_Public_Chat`)
    - Cria/recupera conversa por hash de IP e canal `web_chat`
    - Reutiliza conversa se Ãºltima atividade foi nas Ãºltimas 2 horas
    - Registra IP do visitante como `sender_identifier`
    - Armazena metadados de performance (response_time_ms, ip_address)
  - Criada interface administrativa `DPS_AI_Conversations_Admin` em `includes/class-dps-ai-conversations-admin.php`
    - Nova pÃ¡gina admin "Conversas IA" (submenu no menu DPS)
    - Slug da pÃ¡gina: `dps-ai-conversations`
    - Lista conversas com filtros: canal, status (aberta/fechada), perÃ­odo de datas
    - PaginaÃ§Ã£o (20 conversas por pÃ¡gina)
    - Exibe: ID, Cliente/Visitante, Canal, Data de InÃ­cio, Ãšltima Atividade, Status, AÃ§Ãµes
    - PÃ¡gina de detalhes da conversa com histÃ³rico completo de mensagens
    - Mensagens exibidas cronologicamente com tipo (usuÃ¡rio/assistente/sistema), data/hora, texto
    - Metadados JSON expansÃ­veis para visualizar informaÃ§Ãµes tÃ©cnicas
    - DiferenciaÃ§Ã£o visual por tipo de remetente (cores de borda e fundo)
    - Controle de permissÃµes: apenas `manage_options`
  - Incrementado `DPS_AI_DB_VERSION` para `1.6.0`
  - MigraÃ§Ã£o automÃ¡tica via `dps_ai_maybe_upgrade_database()` para criar tabelas em atualizaÃ§Ãµes
  - Preparado para futuros canais (WhatsApp, Modo Especialista) sem alteraÃ§Ãµes de schema
- **AI Add-on (v1.6.2)**: ValidaÃ§Ã£o de Contraste de Cores para Chat PÃºblico (Acessibilidade WCAG AA)
  - Criada classe `DPS_AI_Color_Contrast` em `includes/class-dps-ai-color-contrast.php` para validaÃ§Ã£o de contraste segundo padrÃµes WCAG 2.0
  - Novos campos de configuraÃ§Ã£o na pÃ¡gina de settings: Cor PrimÃ¡ria, Cor do Texto e Cor de Fundo do chat pÃºblico
  - ValidaÃ§Ã£o em tempo real de contraste usando WordPress Color Picker nativo
  - Calcula luminÃ¢ncia relativa e ratio de contraste (fÃ³rmula WCAG: (L1 + 0.05) / (L2 + 0.05))
  - Exibe avisos visuais se contraste insuficiente (<4.5:1 para texto normal, <3.0:1 para texto grande)
  - Avisos nÃ£o bloqueiam salvamento, apenas alertam admin sobre possÃ­vel dificuldade de leitura
  - Endpoint AJAX `dps_ai_validate_contrast` para validaÃ§Ã£o assÃ­ncrona com nonce e capability check (`manage_options`)
  - Mensagens especÃ­ficas com ratio calculado (exemplo: "contraste 3.2:1, mÃ­nimo recomendado 4.5:1")
  - Valida tanto contraste Texto/Fundo quanto Branco/Cor PrimÃ¡ria (para legibilidade em botÃµes)
  - ConfiguraÃ§Ãµes salvas com `sanitize_hex_color()` e padrÃµes: primÃ¡ria=#2271b1, texto=#1d2327, fundo=#ffffff
- **AI Add-on (v1.6.2)**: Indicador de Rate Limit no Chat PÃºblico (UX)
  - Modificado `DPS_AI_Client` para armazenar tipo de erro em propriedade estÃ¡tica `$last_error`
  - Novos mÃ©todos `get_last_error()` e `clear_last_error()` para recuperar informaÃ§Ãµes de erro
  - DiferenciaÃ§Ã£o de erros HTTP por tipo: `rate_limit` (429), `bad_request` (400), `unauthorized` (401), `server_error` (500-503), `network_error`, `generic`
  - Backend (`DPS_AI_Public_Chat::handle_ajax_ask()`) detecta rate limit via `get_last_error()` e retorna `error_type` especÃ­fico no JSON
  - Frontend JavaScript detecta `error_type === 'rate_limit'` e exibe UX diferenciada:
    - Mensagem especÃ­fica: "Muitas solicitaÃ§Ãµes em sequÃªncia. Aguarde alguns segundos antes de tentar novamente."
    - Ãcone especial â±ï¸ (em vez de âš ï¸ genÃ©rico)
    - BotÃ£o de enviar desabilitado temporariamente por 5 segundos
    - Contagem regressiva visual no botÃ£o (5, 4, 3, 2, 1) para feedback ao usuÃ¡rio
    - Classe CSS adicional `dps-ai-public-message--rate-limit` para estilizaÃ§Ã£o
  - FunÃ§Ã£o JavaScript `disableSubmitTemporarily(seconds)` gerencia contagem regressiva e reabilitaÃ§Ã£o automÃ¡tica
  - Erros genÃ©ricos (rede, servidor, etc.) mantÃªm comportamento original sem alteraÃ§Ãµes
  - 100% retrocompatÃ­vel, nÃ£o afeta fluxo de produÃ§Ã£o existente
- **AI Add-on (v1.6.2)**: Interface de Teste e ValidaÃ§Ã£o da Base de Conhecimento
  - Criada nova pÃ¡gina admin "Testar Base de Conhecimento" (submenu no menu DPS)
  - Slug da pÃ¡gina: `dps-ai-kb-tester`
  - Classe `DPS_AI_Knowledge_Base_Tester` em `includes/class-dps-ai-knowledge-base-tester.php`
  - **Preview de Artigos Selecionados:** Permite testar quais artigos seriam selecionados para uma pergunta de teste
  - Campo de texto para digitar pergunta de teste + botÃ£o "Testar Matching" (suporta Ctrl+Enter)
  - ConfiguraÃ§Ã£o de limite de artigos (1-10, padrÃ£o: 5)
  - Usa mesma lÃ³gica de matching de produÃ§Ã£o (`get_relevant_articles_with_details()` reusa `get_relevant_articles()`)
  - Exibe artigos que seriam incluÃ­dos no contexto com: tÃ­tulo (link para ediÃ§Ã£o), prioridade (badge colorido), keywords (destacando em azul as que fizeram match), tamanho (chars/words/tokens), trecho do conteÃºdo (200 chars)
  - Resumo com 3 cards estatÃ­sticos: Artigos Encontrados, Total de Caracteres, Tokens Estimados
  - **ValidaÃ§Ã£o de Tamanho de Artigos:** FunÃ§Ã£o `estimate_article_size($content)` para estimar tamanho baseado em caracteres, palavras e aproximaÃ§Ã£o de tokens (1 token â‰ˆ 4 chars para portuguÃªs)
  - ClassificaÃ§Ã£o de tamanho: Curto (<500 chars), MÃ©dio (500-2000 chars), Longo (>2000 chars)
  - Metabox "ValidaÃ§Ã£o de Tamanho" na tela de ediÃ§Ã£o do CPT mostrando classificaÃ§Ã£o com badge colorido (verde/amarelo/vermelho), estatÃ­sticas detalhadas e aviso se artigo muito longo
  - SugestÃ£o automÃ¡tica para resumir ou dividir artigos longos (>2000 chars)
  - Badges de tamanho exibidos tanto no teste quanto na listagem de artigos
  - Assets: `assets/css/kb-tester.css` (4.4KB, estilos para cards, badges, grid responsivo) e `assets/js/kb-tester.js` (7KB, AJAX, renderizaÃ§Ã£o dinÃ¢mica, destaque de keywords)
  - Endpoint AJAX: `wp_ajax_dps_ai_kb_test_matching` com seguranÃ§a (nonce, capability `edit_posts`)
  - Interface responsiva com grid adaptativo para mobile
- **AI Add-on (v1.6.2)**: Interface Administrativa para Gerenciar Base de Conhecimento
  - Criada nova pÃ¡gina admin "Base de Conhecimento" (submenu no menu DPS)
  - Slug da pÃ¡gina: `dps-ai-knowledge-base`
  - Classe `DPS_AI_Knowledge_Base_Admin` em `includes/class-dps-ai-knowledge-base-admin.php`
  - Listagem completa dos artigos do CPT `dps_ai_knowledge` com colunas: TÃ­tulo, Keywords, Prioridade, Status, AÃ§Ãµes
  - **EdiÃ§Ã£o RÃ¡pida Inline:** Permite editar keywords e prioridade diretamente na listagem sem entrar em cada post
  - BotÃ£o "Editar RÃ¡pido" por linha abre formulÃ¡rio inline com textarea (keywords) e input numÃ©rico (prioridade 1-10)
  - Salvamento via AJAX com validaÃ§Ã£o de nonce e capability (`edit_posts`)
  - Feedback visual de sucesso (linha pisca em verde) e notice temporÃ¡ria
  - BotÃµes Salvar (verde primÃ¡rio) e Cancelar
  - **Filtros e OrdenaÃ§Ã£o:** Busca por texto (tÃ­tulo), filtro por prioridade (Alta 8-10/MÃ©dia 4-7/Baixa 1-3), ordenaÃ§Ã£o por TÃ­tulo ou Prioridade (ASC/DESC)
  - BotÃ£o "Limpar Filtros" quando filtros estÃ£o ativos
  - Badges coloridos para prioridade (verde=alta, amarelo=mÃ©dia, cinza=baixa) e status (publicado/rascunho/ativo/inativo)
  - Link para ediÃ§Ã£o completa do post em cada linha
  - Contador de total de artigos exibido
  - Assets: `assets/css/kb-admin.css` (estilos, badges, animaÃ§Ãµes) e `assets/js/kb-admin.js` (AJAX, ediÃ§Ã£o inline, validaÃ§Ã£o)
  - Endpoint AJAX: `wp_ajax_dps_ai_kb_quick_edit` com seguranÃ§a (nonce, capability, sanitizaÃ§Ã£o, escapagem)
  - Visual consistente com padrÃµes do admin WordPress (tabelas, classes, botÃµes)
- **AI Add-on (v1.6.2)**: IntegraÃ§Ã£o Real da Base de Conhecimento com Matching por Keywords
  - Implementada busca automÃ¡tica de artigos relevantes baseada em keywords nas perguntas dos clientes
  - MÃ©todo `DPS_AI_Knowledge_Base::get_relevant_articles()` agora Ã© chamado automaticamente em `answer_portal_question()` e `get_ai_response()` (chat pÃºblico)
  - AtÃ© 5 artigos mais relevantes sÃ£o incluÃ­dos no contexto da IA, ordenados por prioridade (1-10)
  - Artigos sÃ£o formatados com cabeÃ§alho "INFORMAÃ‡Ã•ES DA BASE DE CONHECIMENTO:" para clareza no contexto
  - Infraestrutura de metaboxes de keywords (`_dps_ai_keywords`) e prioridade (`_dps_ai_priority`) jÃ¡ existia, apenas conectada ao fluxo de respostas
  - DocumentaÃ§Ã£o completa em `docs/implementation/AI_KNOWLEDGE_BASE_MULTILINGUAL_IMPLEMENTATION.md`
- **AI Add-on (v1.6.2)**: Suporte Real a Multiidioma com InstruÃ§Ãµes ExplÃ­citas
  - Implementado mÃ©todo `get_base_system_prompt_with_language($language)` que adiciona instruÃ§Ã£o explÃ­cita de idioma ao system prompt
  - Suporte a 4 idiomas: pt_BR (PortuguÃªs Brasil), en_US (English US), es_ES (EspaÃ±ol), auto (detectar automaticamente)
  - InstruÃ§Ã£o orienta a IA a SEMPRE responder no idioma configurado, mesmo que artigos da base estejam em outro idioma
  - ConfiguraÃ§Ã£o de idioma (`dps_ai_settings['language']`) jÃ¡ existia, agora Ã© efetivamente utilizada nas instruÃ§Ãµes
  - Aplicado em todos os contextos: chat do portal, chat pÃºblico e assistente de mensagens (WhatsApp/Email)
  - MÃ©todo similar `get_public_system_prompt_with_language()` criado para chat pÃºblico
- **AI Add-on (v1.6.1)**: Limpeza AutomÃ¡tica de Dados Antigos
  - Implementada rotina de limpeza automÃ¡tica via WP-Cron para deletar mÃ©tricas e feedback com mais de 365 dias (configurÃ¡vel)
  - Criada classe `DPS_AI_Maintenance` em `includes/class-dps-ai-maintenance.php`
  - Adicionada limpeza automÃ¡tica de transients expirados relacionados Ã  IA
  - Evento agendado para rodar diariamente Ã s 03:00 (horÃ¡rio do servidor)
  - Nova configuraÃ§Ã£o "PerÃ­odo de RetenÃ§Ã£o de Dados" na pÃ¡gina de settings (padrÃ£o: 365 dias, mÃ­nimo: 30, mÃ¡ximo: 3650)
  - BotÃ£o de limpeza manual na pÃ¡gina de settings com estatÃ­sticas de dados armazenados
  - FunÃ§Ã£o `DPS_AI_Maintenance::get_storage_stats()` para exibir volume de dados e registros mais antigos
- **AI Add-on (v1.6.1)**: Logger Condicional Respeitando WP_DEBUG
  - Criado sistema de logging condicional em `includes/dps-ai-logger.php`
  - FunÃ§Ãµes helper: `dps_ai_log()`, `dps_ai_log_debug()`, `dps_ai_log_info()`, `dps_ai_log_warning()`, `dps_ai_log_error()`
  - Logs detalhados (debug/info/warning) sÃ£o registrados apenas quando `WP_DEBUG` estÃ¡ habilitado OU quando a opÃ§Ã£o "Enable debug logging" estÃ¡ ativa
  - Em produÃ§Ã£o (debug desabilitado), apenas erros crÃ­ticos sÃ£o registrados
  - Nova configuraÃ§Ã£o "Habilitar Logs Detalhados" na pÃ¡gina de settings
  - Indicador visual quando `WP_DEBUG` estÃ¡ ativo nas configuraÃ§Ãµes
- **AI Add-on (v1.6.1)**: Melhorias de UX na PÃ¡gina de ConfiguraÃ§Ãµes
  - Toggle de visibilidade da API Key com Ã­cone de olho (dashicons) para mostrar/ocultar chave
  - Destaque visual do modelo GPT atualmente selecionado na tabela de custos
  - Nova coluna "Status" na tabela de custos mostrando badge "Modelo Ativo" para o modelo em uso
  - Background azul claro e borda lateral azul destacando a linha do modelo ativo
  - Melhor acessibilidade com texto explÃ­cito alÃ©m de indicadores visuais
- **AI Add-on (v1.6.1)**: Melhorias de UX no Widget de Chat
  - Autoscroll inteligente para a Ãºltima mensagem (apenas se usuÃ¡rio nÃ£o estiver lendo mensagens antigas)
  - Textarea auto-expansÃ­vel atÃ© 6 linhas (~120px) com overflow interno apÃ³s o limite
  - Implementado tanto no chat do portal (`dps-ai-portal.js`) quanto no chat pÃºblico (`dps-ai-public-chat.js`)
  - DetecÃ§Ã£o automÃ¡tica de posiÃ§Ã£o de scroll: nÃ£o interrompe leitura de mensagens anteriores
- **AI Add-on (v1.6.1)**: Dashboard de Analytics com GrÃ¡ficos e ConversÃ£o de Moeda
  - IntegraÃ§Ã£o com Chart.js 4.4.0 via CDN para visualizaÃ§Ã£o de dados
  - GrÃ¡fico de linhas: uso de tokens ao longo do tempo
  - GrÃ¡fico de barras: nÃºmero de requisiÃ§Ãµes por dia
  - GrÃ¡fico de Ã¡rea: custo acumulado no perÃ­odo (USD e BRL com eixos duplos)
  - Nova configuraÃ§Ã£o "Taxa de ConversÃ£o USD â†’ BRL" nas settings (validaÃ§Ã£o 0.01-100)
  - ExibiÃ§Ã£o automÃ¡tica de custos em BRL nos cards do dashboard quando taxa configurada
  - Aviso visual indicando taxa atual ou sugerindo configuraÃ§Ã£o
  - Link direto para configurar taxa a partir do analytics
- **AI Add-on (v1.6.1)**: ExportaÃ§Ã£o CSV de MÃ©tricas e Feedbacks
  - BotÃ£o "Exportar CSV" na pÃ¡gina de analytics para exportar mÃ©tricas do perÃ­odo filtrado
  - BotÃ£o "Exportar Feedbacks CSV" para exportar Ãºltimos 1000 feedbacks
  - CSV de mÃ©tricas inclui: data, perguntas, tokens (entrada/saÃ­da/total), custo (USD/BRL), tempo mÃ©dio, erros, modelo
  - CSV de feedbacks inclui: data/hora, cliente ID, pergunta, resposta, tipo de feedback, comentÃ¡rio
  - Encoding UTF-8 com BOM para compatibilidade com Excel
  - Separador ponto-e-vÃ­rgula (`;`) para melhor compatibilidade com Excel Brasil
  - Tratamento de caracteres especiais (acentos, vÃ­rgulas, quebras de linha)
  - Endpoints seguros: `admin-post.php?action=dps_ai_export_metrics` e `admin-post.php?action=dps_ai_export_feedback`
  - VerificaÃ§Ã£o de capability `manage_options` e nonces obrigatÃ³rios
  - FunÃ§Ã£o helper centralizada `generate_csv()` para reuso de cÃ³digo
- **AI Add-on (v1.6.1)**: PaginaÃ§Ã£o na Listagem de Feedbacks Recentes
  - Implementada paginaÃ§Ã£o de 20 feedbacks por pÃ¡gina no dashboard de analytics
  - Controles de navegaÃ§Ã£o padrÃ£o do WordPress: Primeira, Anterior, PrÃ³xima, Ãšltima
  - Input para navegar diretamente a uma pÃ¡gina especÃ­fica (com validaÃ§Ã£o JavaScript)
  - ExibiÃ§Ã£o do total de feedbacks e pÃ¡gina atual
  - URL mantÃ©m filtros de data ao navegar entre pÃ¡ginas
  - Controles exibidos apenas quando hÃ¡ mais de uma pÃ¡gina
  - ParÃ¢metro `?feedback_paged=N` na URL para controlar pÃ¡gina atual
  - Nova funÃ§Ã£o `DPS_AI_Analytics::count_feedback()` para contar total de registros
  - Adicionado parÃ¢metro `$offset` na funÃ§Ã£o `get_recent_feedback()` para suportar paginaÃ§Ã£o
- **AI Add-on (v1.6.1)**: Sistema de Prompts Centralizado e CustomizÃ¡vel
  - Criado diretÃ³rio `/prompts` com arquivos de system prompts separados por contexto
  - 4 contextos disponÃ­veis: `portal`, `public`, `whatsapp`, `email`
  - Nova classe `DPS_AI_Prompts` em `includes/class-dps-ai-prompts.php` gerencia carregamento e filtros
  - Arquivos de prompt:
    - `prompts/system-portal.txt` - Chat do Portal do Cliente
    - `prompts/system-public.txt` - Chat PÃºblico para visitantes
    - `prompts/system-whatsapp.txt` - Mensagens via WhatsApp
    - `prompts/system-email.txt` - ConteÃºdo de e-mails
  - Filtros do WordPress para customizaÃ§Ã£o:
    - `dps_ai_system_prompt` - Filtro global para todos os contextos
    - `dps_ai_system_prompt_{contexto}` - Filtro especÃ­fico por contexto (ex: `dps_ai_system_prompt_portal`)
  - API simplificada: `DPS_AI_Prompts::get('contexto')` retorna prompt com filtros aplicados
  - Retrocompatibilidade: mÃ©todos `get_base_system_prompt()` e `get_public_system_prompt()` agora usam a nova classe internamente
  - FunÃ§Ãµes auxiliares: `is_valid_context()`, `get_available_contexts()`, `clear_cache()`
  - Cache interno para evitar releituras de arquivos
- **AI Add-on (v1.6.1)**: Estrutura de Testes UnitÃ¡rios e CI
  - Configurado PHPUnit para testes automatizados do add-on
  - Criado `composer.json` com PHPUnit 9.5+ como dependÃªncia de desenvolvimento
  - Arquivo `phpunit.xml` com configuraÃ§Ã£o de test suite e coverage
  - Bootstrap de testes (`tests/bootstrap.php`) com mocks de funÃ§Ãµes WordPress
  - **Testes implementados** (24 testes no total):
    - `Test_DPS_AI_Email_Parser` - 8 testes para parsing de e-mails (JSON, labeled, separated, plain, malicioso, vazio, text_to_html, stats)
    - `Test_DPS_AI_Prompts` - 9 testes para sistema de prompts (4 contextos, validaÃ§Ã£o, cache, clear_cache)
    - `Test_DPS_AI_Analytics` - 7 testes para cÃ¡lculo de custos (GPT-4o-mini, GPT-4o, GPT-4-turbo, zero tokens, modelo desconhecido, conversÃ£o USDâ†’BRL, tokens fracionÃ¡rios)
  - **GitHub Actions CI** (`.github/workflows/phpunit.yml`):
    - Executa testes em push/PR para branches `main`, `develop`, `copilot/**`
    - Testa em mÃºltiplas versÃµes do PHP (8.0, 8.1, 8.2)
    - Gera relatÃ³rio de cobertura para PHP 8.1
    - Cache de dependÃªncias Composer para build mais rÃ¡pido
  - Scripts Composer: `composer test` e `composer test:coverage`
  - DocumentaÃ§Ã£o completa em `tests/README.md` com instruÃ§Ãµes de uso e troubleshooting
  - Arquivo `.gitignore` para excluir `vendor/`, `coverage/` e arquivos de cache

#### Changed (Alterado)
- **AI Add-on (v1.6.2)**: IntegraÃ§Ã£o da Base de Conhecimento nos Fluxos de Resposta
  - Modificado `DPS_AI_Assistant::answer_portal_question()` para buscar e incluir artigos relevantes via `get_relevant_articles()`
  - Modificado `DPS_AI_Public_Chat::get_ai_response()` para buscar e incluir artigos relevantes no chat pÃºblico
  - Contexto da base de conhecimento Ã© adicionado apÃ³s contexto do cliente/negÃ³cio e antes da pergunta do usuÃ¡rio

#### Deprecated (Depreciado)
- **Client Portal (v2.4.0)**: Shortcode `[dps_client_login]` descontinuado (Fase 1.1)
  - Shortcode agora exibe mensagem de depreciaÃ§Ã£o ao invÃ©s de formulÃ¡rio de login
  - Sistema de login por usuÃ¡rio/senha removido em favor de autenticaÃ§Ã£o exclusiva por token (magic link)
  - RemoÃ§Ã£o completa prevista para v3.0.0
  - MigraÃ§Ã£o: clientes devem usar apenas `[dps_client_portal]` e solicitar links de acesso
  - DocumentaÃ§Ã£o atualizada em `TOKEN_AUTH_SYSTEM.md` com guia de migraÃ§Ã£o
  - Artigos sÃ£o formatados com cabeÃ§alho claro "INFORMAÃ‡Ã•ES DA BASE DE CONHECIMENTO:" para melhor compreensÃ£o da IA
- **AI Add-on (v1.6.2)**: AplicaÃ§Ã£o Real do Idioma Configurado em Todos os Contextos
  - Modificado `DPS_AI_Assistant::answer_portal_question()` para usar `get_base_system_prompt_with_language()` ao invÃ©s de `get_base_system_prompt()`
  - Modificado `DPS_AI_Public_Chat::get_ai_response()` para usar `get_public_system_prompt_with_language()`
  - Modificado `DPS_AI_Message_Assistant::suggest_whatsapp_message()` e `suggest_email_message()` para usar prompt com idioma
  - System prompt agora inclui instruÃ§Ã£o explÃ­cita: "IMPORTANTE: VocÃª DEVE responder SEMPRE em [IDIOMA]"
  - ConfiguraÃ§Ã£o `dps_ai_settings['language']` que jÃ¡ existia agora Ã© efetivamente utilizada
- **AI Add-on (v1.6.1)**: Tratamento Robusto de Erros nas Chamadas HTTP
  - Refatorada classe `DPS_AI_Client::chat()` com tratamento avanÃ§ado de erros
  - ValidaÃ§Ã£o de array de mensagens antes de enviar requisiÃ§Ã£o
  - Tratamento especÃ­fico para diferentes cÃ³digos HTTP de erro (400, 401, 429, 500, 502, 503)
  - Adicionado try/catch para capturar exceÃ§Ãµes inesperadas
  - Logs contextualizados com detalhes tÃ©cnicos (timeout, response_time, status code, tokens_used)
  - ValidaÃ§Ã£o de resposta vazia e JSON invÃ¡lido antes de processar
  - Mensagens de erro amigÃ¡veis sem expor dados sensÃ­veis (API key, payloads, etc.)
- **AI Add-on (v1.6.1)**: RefatoraÃ§Ã£o de Logging em Todas as Classes
  - SubstituÃ­dos 7 chamadas `error_log()` por funÃ§Ãµes do novo logger condicional
  - Afetados: `class-dps-ai-message-assistant.php` (4 ocorrÃªncias)
  - Todos os logs agora respeitam configuraÃ§Ãµes de debug do plugin
- **AI Add-on (v1.6.1)**: Dashboard de Analytics Aprimorado
  - MÃ©todo `enqueue_charts_scripts()` para carregar Chart.js e preparar dados
  - Dados agregados por dia incluem cÃ¡lculo de custo acumulado
  - GrÃ¡ficos responsivos adaptam-se ao tamanho da tela
  - Layout em grid para grÃ¡ficos (mÃ­nimo 400px por coluna)
- **AI Add-on (v1.6.1)**: RefatoraÃ§Ã£o de System Prompts (BREAKING para customizaÃ§Ãµes diretas)
  - `DPS_AI_Assistant::get_base_system_prompt()` agora usa `DPS_AI_Prompts::get('portal')` internamente
  - `DPS_AI_Public_Chat::get_public_system_prompt()` agora usa `DPS_AI_Prompts::get('public')` internamente
  - `DPS_AI_Message_Assistant::build_message_system_prompt()` agora carrega prompts base de arquivos antes de adicionar instruÃ§Ãµes especÃ­ficas
  - **IMPORTANTE**: Se vocÃª estava sobrescrevendo mÃ©todos de prompt diretamente, migre para os filtros `dps_ai_system_prompt` ou `dps_ai_system_prompt_{contexto}`
- **AI Add-on (v1.6.1)**: Parser Robusto de Respostas de E-mail da IA
  - Criada classe `DPS_AI_Email_Parser` em `includes/class-dps-ai-email-parser.php` para parsing defensivo e robusto de e-mails
  - Suporta mÃºltiplos formatos de resposta: JSON estruturado, formato com rÃ³tulos (ASSUNTO:/CORPO:), separado por linha vazia e texto plano
  - Implementados fallbacks inteligentes quando formato esperado nÃ£o Ã© encontrado
  - ValidaÃ§Ã£o e sanitizaÃ§Ã£o automÃ¡tica com `wp_kses_post()`, `sanitize_text_field()`, `strip_tags()`
  - ProteÃ§Ã£o contra scripts maliciosos e conteÃºdo perigoso injetado pela IA
  - Limite configurÃ¡vel para tamanho do assunto (padrÃ£o: 200 caracteres)
  - Logging detalhado do processo de parsing para diagnÃ³stico (formato usado, tamanho de subject/body, estatÃ­sticas)
  - MÃ©todo `DPS_AI_Email_Parser::text_to_html()` para converter texto plano em HTML bÃ¡sico
  - MÃ©todo `DPS_AI_Email_Parser::get_parse_stats()` para obter estatÃ­sticas sobre qualidade do parse
  - Classe `DPS_AI_Message_Assistant` refatorada para usar o novo parser robusto
  - MÃ©todo `parse_email_response()` depreciado mas mantido para retrocompatibilidade

#### Fixed (Corrigido)
- **Client Portal Add-on (v2.4.1)**: CorreÃ§Ã£o de aviso "Translation loading triggered too early" no WordPress 6.7.0+
  - **Problema**: Aviso PHP Notice "Translation loading for the dps-client-portal domain was triggered too early" no WordPress 6.7.0+
  - **Causa Raiz**: Constante `DPS_CLIENT_PORTAL_PAGE_TITLE` definia valor com `__()` no nÃ­vel do arquivo (linha 61), antes do hook `init`
  - **CorreÃ§Ã£o Aplicada**:
    - Removido `__()` da definiÃ§Ã£o da constante; constante agora contÃ©m string nÃ£o traduzida 'Portal do Cliente'
    - Adicionada traduÃ§Ã£o onde a constante Ã© usada para criar pÃ¡ginas (linha 443): `__( DPS_CLIENT_PORTAL_PAGE_TITLE, 'dps-client-portal' )`
    - Busca de pÃ¡ginas existentes usa tÃ­tulo nÃ£o traduzido para consistÃªncia entre idiomas
  - **Impacto**: Elimina avisos de carregamento prematuro de traduÃ§Ãµes nos logs; pÃ¡ginas criadas usam tÃ­tulo traduzido conforme idioma do site
  - **Arquivos Alterados**: `plugins/desi-pet-shower-client-portal/desi-pet-shower-client-portal.php`
  - **Compatibilidade**: Mantida retrocompatibilidade - constante ainda existe e funciona normalmente
- **AGENDA Add-on (v1.4.1)**: CorreÃ§Ã£o de PHP Warning - Undefined array key "payment"
  - **Problema**: Avisos PHP "Undefined array key 'payment'" na linha 455 de `trait-dps-agenda-renderer.php`
  - **Causa Raiz**: FunÃ§Ãµes de renderizaÃ§Ã£o (`render_appointment_row`, `render_appointment_row_tab1`, `render_appointment_row_tab2`) acessavam Ã­ndices do array `$column_labels` sem verificar existÃªncia
  - **CorreÃ§Ã£o Aplicada**: Adicionado operador de coalescÃªncia nula (`??`) em todos os acessos a `$column_labels` com valores padrÃ£o traduzidos
  - **Escopo da CorreÃ§Ã£o**:
    - `trait-dps-agenda-renderer.php`: 13 ocorrÃªncias corrigidas nas funÃ§Ãµes de renderizaÃ§Ã£o
    - `desi-pet-shower-agenda-addon.php`: 6 ocorrÃªncias corrigidas nos cabeÃ§alhos de tabela
  - **Impacto**: Elimina warnings PHP nos logs e previne erros futuros caso array incompleto seja passado
  - **Arquivos Alterados**:
    - `plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php`
    - `plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php`
- **Client Portal Add-on (v2.4.1)**: CorreÃ§Ã£o CrÃ­tica no Login por Token
  - **Problema**: Links de acesso mÃ¡gico (magic links) redirecionavam para tela de login mesmo com token vÃ¡lido
  - **Causa Raiz**: Sintaxe incorreta do `setcookie()` com array associativo (incompatÃ­vel com PHP 7.3+)
  - **CorreÃ§Ã£o Aplicada** em `class-dps-portal-session-manager.php`:
    - SubstituÃ­da sintaxe `setcookie($name, $value, $options_array)` por parÃ¢metros individuais
    - Adicionado `header()` separado para `SameSite=Strict` (compatibilidade PHP <7.3)
    - Corrigida prioridade do hook `validate_session` de 5 para 10 (executa APÃ“S autenticaÃ§Ã£o por token)
    - Removidas chamadas deprecadas a `maybe_start_session()` que nÃ£o faziam nada
  - **Impacto**: Clientes agora conseguem acessar o portal via magic link sem serem redirecionados para login
  - **Arquivos Alterados**:
    - `plugins/desi-pet-shower-client-portal/includes/class-dps-portal-session-manager.php`
    - `plugins/desi-pet-shower-client-portal/includes/class-dps-client-portal.php`
  - **Commit**: Corrigir sintaxe setcookie() e ordem de execuÃ§Ã£o de hooks
- **AI Add-on (v1.6.1)**: Tabelas de Banco de Dados NÃ£o Criadas em AtualizaÃ§Ãµes
  - **Problema**: UsuÃ¡rios que atualizaram de v1.4.0 para v1.5.0+ sem desativar/reativar o plugin nÃ£o tinham as tabelas `wp_dps_ai_metrics` e `wp_dps_ai_feedback` criadas, causando erros na pÃ¡gina de analytics
  - **Causa Raiz**: Tabelas eram criadas apenas no hook de ativaÃ§Ã£o (`register_activation_hook`), que nÃ£o executa durante atualizaÃ§Ãµes de plugin
  - **SoluÃ§Ã£o Implementada**:
    - Adicionado rastreamento de versÃ£o do schema via opÃ§Ã£o `dps_ai_db_version`
    - Criada funÃ§Ã£o `dps_ai_maybe_upgrade_database()` que executa em `plugins_loaded` (prioridade 10)
    - Verifica versÃ£o instalada e cria tabelas automaticamente se necessÃ¡rio
    - Segue mesmo padrÃ£o de versionamento usado em outros add-ons
  - **CorreÃ§Ã£o de SQL para dbDelta()**:
    - Corrigido espaÃ§amento apÃ³s `PRIMARY KEY` (deve ter 2 espaÃ§os conforme requisito do WordPress)
    - Tabelas agora sÃ£o criadas corretamente em todas as instalaÃ§Ãµes
  - **Impacto**: Analytics funcionarÃ¡ corretamente para todos os usuÃ¡rios, incluindo aqueles que atualizaram sem reativar o plugin
  - Arquivos alterados: `desi-pet-shower-ai-addon.php`, `includes/class-dps-ai-analytics.php`

#### Security (SeguranÃ§a)
- **White Label Add-on (v1.1.1)**: CorreÃ§Ãµes CrÃ­ticas de SeguranÃ§a
  - **ValidaÃ§Ã£o de Open Redirect ReforÃ§ada**: `class-dps-whitelabel-access-control.php`
    - ValidaÃ§Ã£o redundante no mÃ©todo `get_login_url()` alÃ©m da validaÃ§Ã£o no salvamento
    - SanitizaÃ§Ã£o com `esc_url_raw()` antes de retornar URL customizada
    - Log de tentativas suspeitas via `DPS_Logger` quando domÃ­nio externo Ã© detectado
    - ProteÃ§Ã£o contra manipulaÃ§Ã£o direta no banco de dados
  - **SanitizaÃ§Ã£o Robusta de CSS Customizado**: `class-dps-whitelabel-settings.php`
    - ProteÃ§Ã£o contra bypass via encoding hexadecimal/octal (ex: `\74` = 't')
    - Bloqueio de URLs com encoding suspeito em `url()`
    - ValidaÃ§Ã£o adicional via `preg_replace_callback` para detectar caracteres codificados
    - MantÃ©m bloqueio de `javascript:`, `expression()`, `behavior:`, `vbscript:`, `data:` e `@import`
    - Adicionado hook `dps_whitelabel_sanitize_custom_css` para customizaÃ§Ã£o
  - **ValidaÃ§Ã£o de URLs de Logo Implementada**: `class-dps-whitelabel-settings.php`
    - MÃ©todo `validate_logo_url()` agora Ã© chamado em `handle_settings_save()`
    - Valida formatos permitidos: JPG, PNG, GIF, SVG, WebP, ICO
    - Verifica MIME type via Media Library para attachments do WordPress
    - Valida extensÃ£o para URLs externas
    - Exibe mensagem de aviso e define campo vazio quando URL invÃ¡lida

#### Refactoring (Interno)
- **White Label Add-on (v1.1.2)**: OtimizaÃ§Ãµes de Performance
  - **Cache de CSS Customizado**: `class-dps-whitelabel-assets.php`
    - Implementado cache via transient (24 horas) para CSS gerado dinamicamente
    - MÃ©todo `invalidate_css_cache()` limpa cache ao salvar configuraÃ§Ãµes
    - Reduz processamento em cada pageload (regeneraÃ§Ã£o somente quando necessÃ¡rio)
  - **VerificaÃ§Ã£o Otimizada de Hooks Admin**: `class-dps-whitelabel-assets.php`
    - SubstituÃ­do `strpos()` genÃ©rico por whitelist de hooks especÃ­ficos
    - Previne carregamento de CSS em pÃ¡ginas nÃ£o-DPS
    - Adicionado filtro `dps_whitelabel_admin_hooks` para extensibilidade
  - **Cache EstÃ¡tico de Settings em MemÃ³ria**: Aplicado em 6 classes
    - `class-dps-whitelabel-settings.php`
    - `class-dps-whitelabel-smtp.php`
    - `class-dps-whitelabel-login-page.php`
    - `class-dps-whitelabel-admin-bar.php`
    - `class-dps-whitelabel-maintenance.php`
    - `class-dps-whitelabel-access-control.php`
    - Cache estÃ¡tico evita mÃºltiplas chamadas `get_option()` e `wp_parse_args()` por requisiÃ§Ã£o
    - MÃ©todo `clear_cache()` limpa cache ao salvar configuraÃ§Ãµes
    - MÃ©todo `get_settings()` aceita parÃ¢metro `$force_refresh` para invalidaÃ§Ã£o explÃ­cita

#### Changed (Alterado)
- **White Label Add-on (v1.2.0)**: Melhorias de UX BÃ¡sicas
  - **ValidaÃ§Ã£o de URLs em Tempo Real**: `whitelabel-admin.js`
    - ValidaÃ§Ã£o JavaScript ao sair do campo (evento `blur`)
    - Feedback visual imediato com Ã­cones âœ“/âœ— e cores verde/vermelho
    - Valida formatos de URLs para logos, website, suporte, documentaÃ§Ã£o, termos e privacidade
  - **Paletas de Cores PrÃ©-definidas**: `admin-settings.php`, `whitelabel-admin.js`
    - 5 paletas harmonizadas: PadrÃ£o DPS, Oceano, Floresta, PÃ´r do Sol, Moderno
    - AplicaÃ§Ã£o com um clique via JavaScript
    - IntegraÃ§Ã£o com WordPress Color Picker
    - Feedback visual quando paleta Ã© aplicada
  - **Indicadores de Campos Recomendados**: `admin-settings.php`
    - Asterisco laranja (*) em "Nome da Marca" e "Logo"
    - Tooltip explicativo ao passar mouse
    - Melhora orientaÃ§Ã£o do usuÃ¡rio sobre campos importantes
  - **Scroll AutomÃ¡tico para Mensagens**: `whitelabel-admin.js`
    - Scroll suave para mensagens de sucesso/erro apÃ³s salvar
    - Garante que usuÃ¡rio veja feedback mesmo em telas pequenas
  - **Responsividade Melhorada**: `whitelabel-admin.css`
    - Novo breakpoint em 480px para tablets/mobiles em portrait
    - Form tables adaptam layout em colunas verticais
    - BotÃµes e presets ocupam largura total em mobile
    - Melhora usabilidade em dispositivos pequenos

- **White Label Add-on (v1.2.1)**: Funcionalidades Essenciais (Parcial)
  - **Hide Author Links Implementado**: `class-dps-whitelabel-branding.php`
    - OpÃ§Ã£o `hide_author_links` agora funcional (estava salva mas nÃ£o aplicada)
    - Filtra `the_author_posts_link` e `author_link` do WordPress
    - Remove links de autor em posts quando opÃ§Ã£o ativada
    - Ãštil para white label completo sem referÃªncia a autores WordPress
  - **Teste de Conectividade SMTP**: `class-dps-whitelabel-smtp.php`, `whitelabel-admin.js`
    - Novo mÃ©todo `test_smtp_connection()` para testar apenas conectividade (sem enviar e-mail)
    - Verifica host, porta, credenciais e autenticaÃ§Ã£o SMTP
    - Timeout de 10 segundos para evitar espera longa
    - BotÃ£o "Testar ConexÃ£o SMTP" na aba de configuraÃ§Ãµes SMTP
    - Feedback visual (âœ“ sucesso / âœ— erro) via AJAX
    - Ãštil para diagnosticar problemas de configuraÃ§Ã£o antes de enviar e-mails

#### Added (Adicionado)
- **AI Add-on (v1.6.0)**: Chat PÃºblico para Visitantes do Site
  - **Novo Shortcode `[dps_ai_public_chat]`**: Chat de IA aberto para visitantes nÃ£o logados
    - Permite que visitantes tirem dÃºvidas sobre serviÃ§os de Banho e Tosa
    - NÃ£o requer autenticaÃ§Ã£o (diferente do chat do Portal do Cliente)
    - Foco em informaÃ§Ãµes gerais: preÃ§os, horÃ¡rios, serviÃ§os, formas de pagamento
  - **Modos de ExibiÃ§Ã£o**:
    - `mode="inline"`: Widget integrado na pÃ¡gina
    - `mode="floating"`: BotÃ£o flutuante no canto da tela
  - **Temas Visuais**:
    - `theme="light"`: Tema claro (padrÃ£o)
    - `theme="dark"`: Tema escuro
    - `primary_color="#hex"`: Cor principal customizÃ¡vel
  - **FAQs PersonalizÃ¡veis**:
    - BotÃµes clicÃ¡veis com perguntas frequentes
    - ConfigurÃ¡vel via painel administrativo
    - FAQs padrÃ£o incluÃ­das
  - **Rate Limiting por IP**:
    - Limite de 10 perguntas por minuto
    - Limite de 60 perguntas por hora
    - ProteÃ§Ã£o contra abuso por visitantes
  - **ConfiguraÃ§Ãµes Administrativas**:
    - SeÃ§Ã£o dedicada "Chat PÃºblico para Visitantes"
    - Campo para informaÃ§Ãµes do negÃ³cio (horÃ¡rios, endereÃ§o, pagamentos)
    - InstruÃ§Ãµes adicionais para personalizaÃ§Ã£o do comportamento
  - **IntegraÃ§Ã£o com MÃ©tricas**:
    - Registro de interaÃ§Ãµes (perguntas, tempo de resposta)
    - Registro de feedback (ðŸ‘/ðŸ‘Ž)
    - MÃ©tricas agregadas no dashboard de Analytics
  - **System Prompt EspecÃ­fico**:
    - Prompt otimizado para visitantes
    - Foco em informaÃ§Ãµes pÃºblicas (sem dados de clientes)
    - Tom amigÃ¡vel com uso de emojis ðŸ¶ðŸ±
  - **Novos Arquivos**:
    - `includes/class-dps-ai-public-chat.php`: Classe principal
    - `assets/css/dps-ai-public-chat.css`: Estilos responsivos
    - `assets/js/dps-ai-public-chat.js`: Interatividade do chat

- **Loyalty Add-on (v1.2.0)**: Multiplicador de nÃ­vel, compartilhamento e exportaÃ§Ã£o
  - **Multiplicador de NÃ­vel Ativo**: Pontos agora sÃ£o multiplicados por nÃ­vel de fidelidade
    - Bronze: 1x (padrÃ£o)
    - Prata: 1.5x (a partir de 500 pontos)
    - Ouro: 2x (a partir de 1000 pontos)
  - **Compartilhamento via WhatsApp**: BotÃ£o para compartilhar cÃ³digo de indicaÃ§Ã£o
    - Mensagem prÃ©-formatada com cÃ³digo e link
    - Abre WhatsApp Web ou app mobile
  - **ExportaÃ§Ã£o CSV de IndicaÃ§Ãµes**: BotÃ£o para baixar relatÃ³rio
    - Inclui indicador, indicado, cÃ³digo, data, status e recompensas
    - Formato CSV com BOM UTF-8 para compatibilidade com Excel
  - **Novos MÃ©todos na API `DPS_Loyalty_API`**:
    - `calculate_points_for_amount($amount, $client_id)`: preview de pontos antes de conceder
    - `get_top_clients($limit)`: ranking de clientes por pontos
    - `get_clients_by_tier()`: contagem de clientes por nÃ­vel
    - `export_referrals_csv($args)`: exportaÃ§Ã£o de indicaÃ§Ãµes
  - **Novos Hooks**:
    - `dps_loyalty_points_awarded_appointment`: disparado apÃ³s conceder pontos por atendimento
    - `dps_loyalty_tier_bonus_applied`: disparado quando bÃ´nus de nÃ­vel Ã© aplicado
  - **UX Melhorada**:
    - Labels de contexto traduzidos no histÃ³rico de pontos
    - Datas formatadas em dd/mm/yyyy HH:mm
    - SeÃ§Ã£o de indicaÃ§Ã£o redesenhada com box, link e botÃµes de aÃ§Ã£o
    - Contador de indicaÃ§Ãµes na aba
  - **DocumentaÃ§Ã£o**: AnÃ¡lise profunda atualizada em `docs/analysis/LOYALTY_ADDON_ANALYSIS.md`

- **AI Add-on (v1.5.0)**: Nova versÃ£o com 8 funcionalidades principais
  - **1. SugestÃµes de Perguntas Frequentes (FAQs)**:
    - BotÃµes clicÃ¡veis exibidos no widget para perguntas comuns
    - FAQs personalizÃ¡veis na pÃ¡gina de configuraÃ§Ãµes
    - FAQs padrÃ£o incluÃ­das (horÃ¡rio, preÃ§os, agendamento, etc.)
  - **2. Feedback Positivo/Negativo**:
    - BotÃµes ðŸ‘/ðŸ‘Ž apÃ³s cada resposta da IA
    - Registro de feedback em tabela customizada `dps_ai_feedback`
    - Handler AJAX `dps_ai_submit_feedback` para salvar feedback
  - **3. MÃ©tricas de Uso**:
    - Tabela `dps_ai_metrics` para registro de uso diÃ¡rio
    - ContabilizaÃ§Ã£o de perguntas, tokens, erros, tempo de resposta
    - Registro por cliente e por dia
  - **4. Base de Conhecimento**:
    - CPT `dps_ai_knowledge` para FAQs/artigos personalizados
    - Taxonomia para categorizar artigos
    - Palavras-chave para ativaÃ§Ã£o automÃ¡tica no contexto
    - Interface admin para gerenciar conhecimento
  - **5. Widget Flutuante Alternativo**:
    - Modo "chat bubble" no canto da tela
    - OpÃ§Ã£o de posiÃ§Ã£o (inferior direito/esquerdo)
    - AnimaÃ§Ã£o de abertura/fechamento suave
    - Toggle entre modos na configuraÃ§Ã£o
  - **6. Suporte a MÃºltiplos Idiomas**:
    - OpÃ§Ãµes: PortuguÃªs (Brasil), English, EspaÃ±ol, AutomÃ¡tico
    - InstruÃ§Ã£o de idioma enviada ao modelo GPT
    - Interface traduzÃ­vel via text domain
  - **7. Agendamento via Chat**:
    - VerificaÃ§Ã£o de disponibilidade por data
    - Dois modos: solicitar confirmaÃ§Ã£o ou agendamento direto
    - Handlers AJAX para disponibilidade e solicitaÃ§Ã£o
    - NotificaÃ§Ã£o por e-mail para admins (modo solicitaÃ§Ã£o)
    - CriaÃ§Ã£o automÃ¡tica de agendamentos (modo direto)
  - **8. Dashboard de Analytics**:
    - PÃ¡gina admin com mÃ©tricas visuais em cards
    - Filtro por perÃ­odo (data inÃ­cio/fim)
    - MÃ©tricas: perguntas, tokens, custos, tempo de resposta
    - Tabela de feedback recente
    - Uso diÃ¡rio com histÃ³rico
  - **Classes Novas**:
    - `DPS_AI_Analytics`: mÃ©tricas, feedback, custos
    - `DPS_AI_Knowledge_Base`: CPT, taxonomia, artigos
    - `DPS_AI_Scheduler`: agendamento via chat

- **AI Add-on (v1.4.0)**: Melhorias de interface e funcionalidades
  - **Modelos GPT Atualizados**: Adicionados GPT-4o Mini (recomendado), GPT-4o e GPT-4 Turbo
    - GPT-4o Mini como modelo padrÃ£o recomendado para melhor custo/benefÃ­cio em 2024+
    - Mantido GPT-3.5 Turbo como opÃ§Ã£o legada
  - **Teste de ConexÃ£o**: BotÃ£o para validar API key diretamente na pÃ¡gina de configuraÃ§Ãµes
    - Handler AJAX `dps_ai_test_connection` com verificaÃ§Ã£o de nonce e permissÃµes
    - Feedback visual de sucesso/erro em tempo real
  - **Tabela de Custos**: InformaÃ§Ãµes de custo estimado por modelo na pÃ¡gina admin
  - **Interface do Widget Modernizada**:
    - Novo design com header azul gradiente e Ã­cone de robÃ´
    - Badge de status "Online" com animaÃ§Ã£o de pulse
    - Clique no header inteiro para expandir/recolher
    - BotÃ£o de envio circular com Ã­cone de seta
    - Mensagens com estilo de chat moderno (bolhas coloridas)
    - Textarea com auto-resize dinÃ¢mico
    - Scrollbar estilizada no container de mensagens
    - Layout horizontal de input em desktop, vertical em mobile
  - **HistÃ³rico de Conversas**: PersistÃªncia via sessionStorage
    - Mensagens mantidas durante a sessÃ£o do navegador
    - FunÃ§Ã£o `dpsAIClearHistory()` para limpar manualmente
  - **UX Aprimorada**:
    - Envio com Enter (sem Shift) alÃ©m de Ctrl+Enter
    - Dica de atalho de teclado visÃ­vel
    - AnimaÃ§Ãµes suaves de slide para toggle
    - Foco automÃ¡tico no textarea ao expandir

- **Push Notifications Add-on (v1.0.0)**: NotificaÃ§Ãµes push nativas do navegador
  - **Web Push API**: ImplementaÃ§Ã£o nativa sem dependÃªncia de serviÃ§os externos
    - Chaves VAPID geradas automaticamente na ativaÃ§Ã£o
    - Service Worker para receber notificaÃ§Ãµes em segundo plano
    - Suporte multi-dispositivo por usuÃ¡rio
  - **Eventos notificados**:
    - Novos agendamentos (`dps_base_after_save_appointment`)
    - MudanÃ§as de status (`dps_appointment_status_changed`)
    - Reagendamentos (`dps_appointment_rescheduled`)
  - **Interface administrativa**:
    - PÃ¡gina de configuraÃ§Ãµes em desi.pet by PRObst > Push Notifications
    - Indicador de status com cores (inscrito/nÃ£o inscrito/negado)
    - BotÃ£o para ativar notificaÃ§Ãµes no navegador atual
    - BotÃ£o para enviar notificaÃ§Ã£o de teste
    - Checkboxes para selecionar eventos a notificar
  - **API pÃºblica**:
    - `DPS_Push_API::send_to_user($user_id, $payload)` - Envia para usuÃ¡rio especÃ­fico
    - `DPS_Push_API::send_to_all_admins($payload, $exclude_ids)` - Envia para todos os admins
    - `DPS_Push_API::generate_vapid_keys()` - Gera novo par de chaves VAPID
  - **SeguranÃ§a**:
    - Nonces em todas as aÃ§Ãµes AJAX
    - VerificaÃ§Ã£o de capability `manage_options`
    - Chaves VAPID Ãºnicas por instalaÃ§Ã£o
    - RemoÃ§Ã£o automÃ¡tica de inscriÃ§Ãµes expiradas
  - **Arquivos**:
    - `desi-pet-shower-push-addon.php` - Plugin principal
    - `includes/class-dps-push-api.php` - API de envio
    - `assets/js/push-addon.js` - JavaScript do admin
    - `assets/js/push-sw.js` - Service Worker
    - `assets/css/push-addon.css` - Estilos da interface
  - **Requisitos**: HTTPS obrigatÃ³rio, PHP 7.4+, navegadores modernos
- **Agenda Add-on (v1.3.2)**: Funcionalidades administrativas avanÃ§adas
  - **Dashboard de KPIs**: Cards de mÃ©tricas no topo da agenda
    - Agendamentos pendentes/finalizados do dia
    - Faturamento estimado baseado em serviÃ§os
    - Taxa de cancelamento semanal
    - MÃ©dia de atendimentos diÃ¡rios (Ãºltimos 7 dias)
  - **AÃ§Ãµes em Lote**: AtualizaÃ§Ã£o de mÃºltiplos agendamentos de uma sÃ³ vez
    - Checkbox de seleÃ§Ã£o em cada linha da tabela
    - Checkbox "selecionar todos" no header
    - Barra de aÃ§Ãµes flutuante (sticky) com botÃµes:
      - Finalizar selecionados
      - Marcar como pago
      - Cancelar selecionados
    - Handler AJAX `dps_bulk_update_status` com validaÃ§Ã£o de nonce
  - **Reagendamento RÃ¡pido**: Modal simplificado para alterar data/hora
    - BotÃ£o "ðŸ“… Reagendar" em cada linha da tabela
    - Modal com apenas campos de data e hora
    - Handler AJAX `dps_quick_reschedule`
    - Hook `dps_appointment_rescheduled` para notificaÃ§Ãµes
  - **HistÃ³rico de AlteraÃ§Ãµes**: Registro de todas as mudanÃ§as em agendamentos
    - Metadado `_dps_appointment_history` com atÃ© 50 entradas
    - Registra: criaÃ§Ã£o, alteraÃ§Ã£o de status, reagendamento
    - Indicador visual "ðŸ“œ" quando hÃ¡ histÃ³rico
    - Handler AJAX `dps_get_appointment_history`
    - IntegraÃ§Ã£o com hook `dps_appointment_status_changed`
  - **API de KPIs**: Handler AJAX `dps_get_admin_kpis` para consulta programÃ¡tica
  - **CSS**: Novos estilos para dashboard, barra de lote, modal de reagendamento
  - **JavaScript**: LÃ³gica para seleÃ§Ã£o em lote, modal de reagendamento, histÃ³rico
- **Constante `DPS_DISABLE_CACHE`**: Nova constante para desabilitar completamente o cache do sistema
  - Ãštil para desenvolvimento, testes e debug de problemas relacionados a dados em cache
  - Afeta todos os transients de cache de dados (pets, clientes, serviÃ§os, estatÃ­sticas, mÃ©tricas, contexto de IA)
  - NÃ£o afeta caches de seguranÃ§a (tokens de login, rate limiting, tentativas de login)
  - Para desabilitar, adicione `define( 'DPS_DISABLE_CACHE', true );` no wp-config.php
  - DocumentaÃ§Ã£o completa no README do plugin base
- **Portal do Cliente v2.3.0**: NavegaÃ§Ã£o por Tabs e Widget de Chat em tempo real
  - **NavegaÃ§Ã£o por Tabs**: Interface reorganizada em 4 abas (InÃ­cio, Agendamentos, Galeria, Meus Dados)
    - Tab "InÃ­cio": PrÃ³ximo agendamento + pendÃªncias financeiras + programa de fidelidade
    - Tab "Agendamentos": HistÃ³rico completo de atendimentos
    - Tab "Galeria": Fotos dos pets
    - Tab "Meus Dados": FormulÃ¡rios de atualizaÃ§Ã£o de dados pessoais e pets
  - **Widget de Chat Flutuante**: ComunicaÃ§Ã£o em tempo real com a equipe
    - BotÃ£o flutuante no canto inferior direito
    - Badge de mensagens nÃ£o lidas com animaÃ§Ã£o
    - AJAX polling a cada 10 segundos para novas mensagens
    - Rate limiting (mÃ¡ximo 10 mensagens/minuto por cliente)
    - NotificaÃ§Ã£o automÃ¡tica ao admin via Communications API
  - **Melhorias de UX**:
    - Acessibilidade: ARIA roles, labels e states em tabs e chat
    - Responsividade: Tabs com scroll horizontal em mobile, chat fullscreen
    - AnimaÃ§Ãµes CSS suaves em transiÃ§Ãµes de tab e chat
  - **Handlers AJAX**:
    - `dps_chat_get_messages`: ObtÃ©m histÃ³rico de mensagens
    - `dps_chat_send_message`: Envia nova mensagem do cliente
    - `dps_chat_mark_read`: Marca mensagens do admin como lidas
- **DocumentaÃ§Ã£o de compatibilidade**: Criado documento `docs/compatibility/COMPATIBILITY_ANALYSIS.md` com anÃ¡lise detalhada de compatibilidade PHP 8.3+/8.4, WordPress 6.9 e tema Astra
- **Helper dps_get_page_by_title_compat()**: Nova funÃ§Ã£o utilitÃ¡ria no Portal do Cliente para substituir `get_page_by_title()` deprecado
- **Debugging Add-on (v1.1.0)**: Melhorias significativas de funcionalidade, cÃ³digo e UX
  - **Novas funcionalidades**:
    - Busca client-side com highlight de termos encontrados
    - Filtros por tipo de erro (Fatal, Warning, Notice, Deprecated, Parse, DB Error, Exception)
    - Cards de estatÃ­sticas com contagem por tipo de erro
    - ExportaÃ§Ã£o/download do arquivo de log
    - BotÃ£o de cÃ³pia rÃ¡pida do log para Ã¡rea de transferÃªncia
    - Alerta visual na admin bar quando hÃ¡ erros fatais (badge vermelho com animaÃ§Ã£o pulse)
    - SincronizaÃ§Ã£o automÃ¡tica de opÃ§Ãµes com estado real do wp-config.php
  - **Melhorias de cÃ³digo**:
    - Novo mÃ©todo `sync_options_with_config()` para manter interface consistente com arquivo
    - MÃ©todo `get_entry_stats()` para estatÃ­sticas de entradas do log
    - MÃ©todo `get_formatted_content()` agora suporta filtro por tipo
    - Cache de entradas parseadas para performance
    - Suporte a tipos adicionais de erro: Exception, Catchable
  - **Melhorias de UX**:
    - Interface com duas abas (ConfiguraÃ§Ãµes e Visualizador de Log)
    - Dashboard de estatÃ­sticas no topo do visualizador
    - Barra de filtros com botÃµes coloridos por tipo de erro
    - Campo de busca com debounce e limpar
    - Feedback visual de sucesso/erro ao copiar
  - **Novos assets**:
    - `assets/js/debugging-admin.js` - busca, filtros e cÃ³pia de logs
    - CSS expandido com estilos para stats, filtros e busca
  - **Admin bar melhorada**:
    - Contador diferenciado para erros fatais (badge vermelho)
    - AnimaÃ§Ã£o pulse para alertar sobre fatais
    - Link direto para visualizar erros fatais
    - Background visual quando hÃ¡ erros fatais
  - **Impacto**: ExperiÃªncia de debugging muito mais produtiva com busca, filtros e alertas visuais
- **Debugging Add-on (v1.0.0)**: Novo add-on para gerenciamento de debug do WordPress
  - **Funcionalidades principais**:
    - ConfiguraÃ§Ã£o de constantes de debug (WP_DEBUG, WP_DEBUG_LOG, WP_DEBUG_DISPLAY, SCRIPT_DEBUG, SAVEQUERIES, WP_DISABLE_FATAL_ERROR_HANDLER) diretamente via interface administrativa
    - ModificaÃ§Ã£o segura do wp-config.php com backup de estado original
    - Visualizador de debug.log com formataÃ§Ã£o inteligente
    - Destaque visual por tipo de erro (Fatal, Warning, Notice, Deprecated, Parse, DB Error)
    - FormataÃ§Ã£o de stack traces e pretty-print de JSON
    - FunÃ§Ã£o de limpeza (purge) do arquivo de log
    - Menu na admin bar com acesso rÃ¡pido e status das constantes
    - Contador de entradas de log na admin bar
  - **Estrutura modular**:
    - Nova pasta `includes/` com classes especializadas:
      - `class-dps-debugging-config-transformer.php` - leitura/escrita do wp-config.php
      - `class-dps-debugging-log-viewer.php` - visualizaÃ§Ã£o e parsing do debug.log
      - `class-dps-debugging-admin-bar.php` - integraÃ§Ã£o com admin bar
    - Nova pasta `assets/css/` com `debugging-admin.css` (tema escuro para logs)
  - **SeguranÃ§a**:
    - Nonces em todas as aÃ§Ãµes
    - VerificaÃ§Ã£o de capability `manage_options`
    - ValidaÃ§Ã£o de permissÃµes de arquivo antes de modificar
    - ConfirmaÃ§Ã£o JavaScript antes de purge
  - **Filtros expostos**:
    - `dps_debugging_config_path` - customizar caminho do wp-config.php
    - `dps_debugging_admin_bar_cap` - customizar capability para admin bar
  - **Impacto**: Facilita debugging durante desenvolvimento sem necessidade de plugins externos
- **Stats Add-on (v1.1.0)**: RefatoraÃ§Ã£o completa com novas funcionalidades
  - **Estrutura modular**:
    - Nova pasta `includes/` com `class-dps-stats-api.php` (API pÃºblica)
    - Nova pasta `assets/css/` com `stats-addon.css` (estilos externos)
    - Nova pasta `assets/js/` com `stats-addon.js` (grÃ¡ficos Chart.js)
    - Plugin principal refatorado com mÃ©todos menores e especializados
  - **API pÃºblica DPS_Stats_API**:
    - `get_appointments_count()` - contagem de atendimentos
    - `get_revenue_total()` / `get_expenses_total()` - totais financeiros
    - `get_financial_totals()` - receita e despesas com integraÃ§Ã£o Finance API
    - `get_ticket_average()` - ticket mÃ©dio calculado
    - `get_cancellation_rate()` - taxa de cancelamento
    - `get_new_clients_count()` - novos clientes no perÃ­odo
    - `get_inactive_pets()` - pets inativos com query SQL otimizada
    - `get_top_services()` - serviÃ§os mais solicitados
    - `get_species_distribution()` - distribuiÃ§Ã£o por espÃ©cie
    - `get_top_breeds()` - raÃ§as mais atendidas
    - `get_period_comparison()` - comparativo com perÃ­odo anterior (%)
    - `export_metrics_csv()` / `export_inactive_pets_csv()` - exportaÃ§Ã£o CSV
  - **Dashboard visual**:
    - Cards de mÃ©tricas coloridos com Ã­cones
    - VariaÃ§Ã£o percentual vs perÃ­odo anterior (verde/vermelho)
    - SeÃ§Ãµes colapsÃ¡veis com `<details>` para organizaÃ§Ã£o
    - GrÃ¡fico de barras para top serviÃ§os (Chart.js)
    - GrÃ¡fico de pizza para distribuiÃ§Ã£o de espÃ©cies (Chart.js)
    - Barras horizontais para top raÃ§as
    - Grid responsivo com media queries
  - **Novas mÃ©tricas**:
    - Ticket mÃ©dio (receita Ã· atendimentos)
    - Taxa de cancelamento (%)
    - Novos clientes cadastrados no perÃ­odo
    - Comparativo automÃ¡tico com perÃ­odo anterior
  - **ExportaÃ§Ã£o CSV**:
    - BotÃ£o "Exportar MÃ©tricas CSV" com todas as mÃ©tricas
    - BotÃ£o "Exportar Inativos CSV" com lista de pets
    - BOM UTF-8 para compatibilidade com Excel
    - Nonces para seguranÃ§a
  - **OtimizaÃ§Ãµes**:
    - Query SQL otimizada para pets inativos (GROUP BY em vez de N+1)
    - IntegraÃ§Ã£o com Finance API (quando disponÃ­vel)
    - Cache via transients mantido
    - Assets carregados via wp_enqueue_* padrÃ£o WordPress
  - **Impacto**: Dashboard visual moderno, API para integraÃ§Ã£o, performance melhorada
- **Stats Add-on**: Documento de anÃ¡lise completa do add-on
  - `docs/analysis/STATS_ADDON_ANALYSIS.md` com ~850 linhas de anÃ¡lise detalhada
  - AvaliaÃ§Ã£o de funcionalidade, cÃ³digo, seguranÃ§a, performance e UX (notas 5-8/10)
  - IdentificaÃ§Ã£o de 7 problemas de cÃ³digo (mÃ©todo muito grande, queries N+1, dados nÃ£o exibidos, etc.)
  - Boas prÃ¡ticas jÃ¡ implementadas (cache, nonces, sanitizaÃ§Ã£o, escape, capabilities)
  - Propostas de melhorias: modularizaÃ§Ã£o, API pÃºblica, otimizaÃ§Ã£o de queries, UX visual
  - Mockup de interface melhorada com cards, grÃ¡ficos e tabelas responsivas
  - Plano de refatoraÃ§Ã£o em 5 fases com estimativa de 38-58h de esforÃ§o
  - SugestÃ£o de novas funcionalidades: comparativo de perÃ­odos, exportaÃ§Ã£o CSV, ticket mÃ©dio, taxa de retenÃ§Ã£o
  - **Impacto**: DocumentaÃ§Ã£o tÃ©cnica completa para orientar desenvolvimento futuro do dashboard de estatÃ­sticas
- **ANALYSIS.md**: SeÃ§Ã£o do Stats Add-on expandida com detalhes de hooks, funÃ§Ãµes globais, dependÃªncias e transients
- **Services Add-on (v1.3.0)**: Novas funcionalidades de pacotes, histÃ³rico e catÃ¡logo
  - **Pacotes promocionais com desconto**:
    - Combinar mÃºltiplos serviÃ§os em um pacote
    - Definir desconto percentual (ex: 10% off no combo)
    - Definir preÃ§o fixo alternativo ao desconto
    - MÃ©todo `DPS_Services_API::calculate_package_price()` para cÃ¡lculo automÃ¡tico
  - **HistÃ³rico de alteraÃ§Ãµes de preÃ§os**:
    - Registro automÃ¡tico de todas as alteraÃ§Ãµes de preÃ§o
    - Armazena data, usuÃ¡rio, preÃ§o antigo e novo
    - MÃ©todo `DPS_Services_API::get_price_history()` para consulta
    - MantÃ©m Ãºltimos 50 registros por serviÃ§o
  - **DuplicaÃ§Ã£o de serviÃ§o**:
    - BotÃ£o "Duplicar" na tabela de serviÃ§os
    - Copia todos os metadados (preÃ§os, duraÃ§Ãµes, consumo de estoque)
    - ServiÃ§o duplicado inicia como inativo (seguranÃ§a)
    - MÃ©todo `DPS_Services_API::duplicate_service()` na API
    - Hook `dps_service_duplicated` disparado apÃ³s duplicaÃ§Ã£o
  - **Shortcode de catÃ¡logo pÃºblico**:
    - `[dps_services_catalog]` para exibir serviÃ§os no site
    - Atributos: `show_prices`, `type`, `category`, `layout`
    - Layouts: lista e grid responsivo
    - Agrupa por tipo e categoria automaticamente
    - Destaca pacotes com badge de desconto
  - **API para Portal do Cliente**:
    - MÃ©todo `get_public_services()` para listar serviÃ§os ativos
    - MÃ©todo `get_portal_services()` com dados para o portal
    - MÃ©todo `get_client_service_history()` com histÃ³rico de uso
    - MÃ©todo `get_service_categories()` para categorias disponÃ­veis
  - **Impacto**: Funcionalidades completas de catÃ¡logo, pacotes e rastreabilidade
- **Services Add-on**: Documento de anÃ¡lise completa do add-on
  - `docs/analysis/SERVICES_ADDON_ANALYSIS.md` com ~850 linhas de anÃ¡lise
  - AvaliaÃ§Ã£o de funcionalidade, cÃ³digo, seguranÃ§a, performance e UX
  - IdentificaÃ§Ã£o de vulnerabilidades e propostas de correÃ§Ã£o
  - Roadmap de melhorias futuras (pacotes, histÃ³rico de preÃ§os, catÃ¡logo pÃºblico)
  - Estimativas de esforÃ§o para cada melhoria
  - **Impacto**: DocumentaÃ§Ã£o tÃ©cnica para orientar desenvolvimento futuro
- **Groomers Add-on (v1.2.0)**: EdiÃ§Ã£o, exclusÃ£o de groomers e exportaÃ§Ã£o de relatÃ³rios
  - Coluna "AÃ§Ãµes" na tabela de groomers com botÃµes Editar e Excluir
  - Modal de ediÃ§Ã£o de groomer (nome e email)
  - ConfirmaÃ§Ã£o de exclusÃ£o com aviso de agendamentos vinculados
  - BotÃ£o "Exportar CSV" no relatÃ³rio de produtividade
  - ExportaÃ§Ã£o inclui: data, horÃ¡rio, cliente, pet, status, valor
  - Linha de totais no final do CSV exportado
  - Handlers seguros com nonces para todas as aÃ§Ãµes
  - ValidaÃ§Ã£o de role antes de excluir groomer
  - Mensagens de feedback via DPS_Message_Helper
  - CSS para modal responsivo com animaÃ§Ã£o
  - **Impacto**: CRUD completo de groomers e exportaÃ§Ã£o de dados
- **Groomers Add-on (v1.1.0)**: RefatoraÃ§Ã£o completa com melhorias de cÃ³digo e layout
  - Nova estrutura de assets: pasta `assets/css/` e `assets/js/`
  - Arquivo CSS externo `groomers-admin.css` com ~400 linhas de estilos minimalistas
  - Arquivo JS externo `groomers-admin.js` com validaÃ§Ãµes e interatividade
  - Cards de mÃ©tricas visuais no relatÃ³rio: profissional, atendimentos, receita total, ticket mÃ©dio
  - Coluna "Pet" adicionada na tabela de resultados do relatÃ³rio
  - FormataÃ§Ã£o de data no padrÃ£o brasileiro (dd/mm/yyyy)
  - Badges de status com cores semÃ¢nticas (realizado, pendente, cancelado)
  - Fieldsets no formulÃ¡rio de cadastro: "Dados de Acesso" e "InformaÃ§Ãµes Pessoais"
  - Indicadores de campos obrigatÃ³rios (asterisco vermelho)
  - Placeholders descritivos em todos os campos
  - IntegraÃ§Ã£o com Finance API para cÃ¡lculo de receitas (com fallback para SQL direto)
  - Novo mÃ©todo `calculate_total_revenue()` com suporte Ã  Finance API
  - Documento de anÃ¡lise completa: `docs/analysis/GROOMERS_ADDON_ANALYSIS.md`
  - **Impacto**: Interface mais profissional e consistente com o padrÃ£o visual DPS
- **GUIA_SISTEMA_DPS.md**: Documento completo de apresentaÃ§Ã£o e configuraÃ§Ã£o do sistema
  - ApresentaÃ§Ã£o geral do sistema e arquitetura modular
  - InstruÃ§Ãµes detalhadas de instalaÃ§Ã£o do plugin base e add-ons
  - ConfiguraÃ§Ã£o passo a passo de todos os 15 add-ons
  - Guia de uso do sistema (clientes, pets, agendamentos, financeiro)
  - Recursos avanÃ§ados (assinaturas, fidelidade, WhatsApp)
  - SeÃ§Ã£o de resoluÃ§Ã£o de problemas comuns
  - ReferÃªncia tÃ©cnica (shortcodes, roles, estrutura de dados)
  - Formatado para publicaÃ§Ã£o web (HTML-ready)
  - InstruÃ§Ãµes para manter documento atualizado
  - **LocalizaÃ§Ã£o**: `docs/GUIA_SISTEMA_DPS.md`
- **DPS_WhatsApp_Helper**: Classe helper centralizada para geraÃ§Ã£o de links WhatsApp
  - MÃ©todo `get_link_to_team()` para cliente contatar equipe (usa nÃºmero configurado)
  - MÃ©todo `get_link_to_client()` para equipe contatar cliente (formata nÃºmero automaticamente)
  - MÃ©todo `get_share_link()` para compartilhamento genÃ©rico (ex: fotos de pets)
  - MÃ©todo `get_team_phone()` para obter nÃºmero da equipe (configurÃ¡vel ou padrÃ£o)
  - MÃ©todos auxiliares para mensagens padrÃ£o (portal, agendamento, cobranÃ§a)
  - Constante padrÃ£o `TEAM_PHONE = '5515991606299'` (+55 15 99160-6299)
- **ConfiguraÃ§Ã£o de WhatsApp**: Campo "NÃºmero do WhatsApp da Equipe" nas configuraÃ§Ãµes de ComunicaÃ§Ãµes
  - Option `dps_whatsapp_number` para armazenar nÃºmero da equipe (padrÃ£o: +55 15 99160-6299)
  - NÃºmero configurÃ¡vel centralmente em Admin â†’ desi.pet by PRObst â†’ ComunicaÃ§Ãµes
  - Suporte a filtro `dps_team_whatsapp_number` para customizaÃ§Ã£o programÃ¡tica
- **Plugin Base**: Nova opÃ§Ã£o "Agendamento Passado" no formulÃ¡rio de agendamentos
  - Adicionada terceira opÃ§Ã£o de tipo de agendamento para registrar atendimentos jÃ¡ realizados
  - Novo fieldset "InformaÃ§Ãµes de Pagamento" com campos especÃ­ficos:
    - Status do Pagamento: dropdown com opÃ§Ãµes "Pago" ou "Pendente"
    - Valor Pendente: campo numÃ©rico exibido condicionalmente quando status = "Pendente"
  - Campos salvos como metadados: `past_payment_status` e `past_payment_value`
  - Agendamentos passados recebem automaticamente status "realizado"
  - JavaScript atualizado para controlar visibilidade dos campos condicionais
  - TaxiDog e Tosa ocultados automaticamente para agendamentos passados (nÃ£o aplicÃ¡vel)
  - **Impacto**: Permite registrar no sistema atendimentos realizados anteriormente e controlar pagamentos pendentes
- **Client Portal Add-on (v2.2.0)**: Menu administrativo e tokens permanentes
  - Adicionado menu "Portal do Cliente" sob "desi.pet by PRObst" com dois submenus:
    - "Portal do Cliente": configuraÃ§Ãµes gerais do portal
    - "Logins de Clientes": gerenciamento de tokens de acesso
  - Implementado suporte a tokens permanentes (vÃ¡lidos atÃ© revogaÃ§Ã£o manual)
  - Modal de seleÃ§Ã£o de tipo de token ao gerar links:
    - "TemporÃ¡rio (30 minutos)": expira automaticamente apÃ³s 30 minutos
    - "Permanente (atÃ© revogar)": vÃ¡lido por 10 anos, revogÃ¡vel manualmente
  - Interface atualizada para exibir tipo de token gerado
  - Tokens permanentes facilitam acesso recorrente sem necessidade de gerar novos links
  - **Impacto**: Administradores agora tÃªm acesso direto ao gerenciamento do portal via menu WP Admin

#### Changed (Mudado)
- **Groomers Add-on**: Removidos estilos inline, substituÃ­dos por classes CSS
- **Groomers Add-on**: Layout responsivo com flexbox e grid
- **Groomers Add-on**: FormulÃ¡rio reorganizado com fieldsets semÃ¢nticos
- **Groomers Add-on**: Tabela de groomers e relatÃ³rios com classes CSS customizadas
- **Lista de Clientes**: Atualizada para usar `DPS_WhatsApp_Helper::get_link_to_client()`
- **Add-on de Agenda**: BotÃµes de confirmaÃ§Ã£o e cobranÃ§a (individual e conjunta) usam helper centralizado
- **Add-on de Agenda (v1.3.1)**: CentralizaÃ§Ã£o de constantes de status
  - Adicionadas constantes `STATUS_PENDING`, `STATUS_FINISHED`, `STATUS_PAID`, `STATUS_CANCELED`
  - Novo mÃ©todo estÃ¡tico `get_status_config()` retorna configuraÃ§Ã£o completa (label, cor, Ã­cone)
  - Novo mÃ©todo estÃ¡tico `get_status_label()` para obter label traduzida de um status
  - Traits refatorados para usar mÃ©todos centralizados ao invÃ©s de strings hardcoded
  - DocumentaÃ§Ã£o de melhorias administrativas em `docs/analysis/AGENDA_ADMIN_IMPROVEMENTS_ANALYSIS.md`
- **Add-on de Assinaturas**: BotÃ£o de cobranÃ§a de renovaÃ§Ã£o usa helper centralizado
- **Add-on de Finance**: BotÃ£o de cobranÃ§a em pendÃªncias financeiras usa helper centralizado
- **Add-on de Stats**: Link de reengajamento para clientes inativos usa helper centralizado
- **Portal do Cliente**: Todos os botÃµes WhatsApp atualizados:
  - BotÃ£o "Quero acesso ao meu portal" usa nÃºmero configurado da equipe
  - Envio de link do portal via WhatsApp usa helper para formatar nÃºmero do cliente
  - BotÃ£o "Agendar via WhatsApp" (empty state) usa nÃºmero configurado da equipe
  - BotÃ£o "Compartilhar via WhatsApp" (fotos de pets) usa helper para compartilhamento
- **Add-on de AI**: FunÃ§Ã£o JavaScript `openWhatsAppWithMessage` melhorada com comentÃ¡rios
- **Add-on de ComunicaÃ§Ãµes**: Interface reorganizada com seÃ§Ãµes separadas para WhatsApp, E-mail e Templates
- **Services Add-on**: Melhorias de UX na interface de serviÃ§os
  - Mensagens de feedback (sucesso/erro) via `DPS_Message_Helper` em todas as aÃ§Ãµes
  - Badges de status visual (Ativo/Inativo) na tabela de serviÃ§os
  - Tabela de serviÃ§os com classes CSS dedicadas para melhor responsividade
  - Wrapper responsivo na tabela com scroll horizontal em mobile
  - Estilos CSS expandidos (~100 linhas adicionadas) para formulÃ¡rio e tabela

#### Fixed (Corrigido)
- **Client Portal Add-on (v2.3.1)**: Corrigido link de token nÃ£o autenticando cliente imediatamente
  - **Problema**: Quando cliente clicava no link com token (`?dps_token=...`), permanecia na tela de solicitaÃ§Ã£o de login em vez de acessar o portal
  - **Causa raiz**: Cookie de sessÃ£o criado com `setcookie()` nÃ£o estava disponÃ­vel em `$_COOKIE` na requisiÃ§Ã£o atual, apenas na prÃ³xima requisiÃ§Ã£o. O redirecionamento apÃ³s autenticaÃ§Ã£o causava perda do contexto de autenticaÃ§Ã£o
  - **SoluÃ§Ã£o implementada**:
    - Adicionada propriedade `$current_request_client_id` em `DPS_Client_Portal` para armazenar autenticaÃ§Ã£o da requisiÃ§Ã£o atual
    - Modificado `get_authenticated_client_id()` para priorizar: autenticaÃ§Ã£o atual â†’ cookies â†’ fallback WP user
    - Removido redirecionamento em `handle_token_authentication()` - portal agora carrega imediatamente com cliente autenticado
    - Adicionada funÃ§Ã£o JavaScript `cleanTokenFromURL()` que remove token da URL via `history.replaceState()` por seguranÃ§a
  - **Impacto**: Links de token agora funcionam imediatamente, sem necessidade de segundo clique ou refresh
  - **Arquivos modificados**:
    - `includes/class-dps-client-portal.php` - lÃ³gica de autenticaÃ§Ã£o
    - `assets/js/client-portal.js` - limpeza de URL
- **Finance Add-on (v1.3.1)**: Corrigida pÃ¡gina de Documentos Financeiros em branco e vulnerabilidade CSRF
  - **Bug #1 - PÃ¡gina sem shortcode**: Quando pÃ¡gina "Documentos Financeiros" jÃ¡ existia com slug `dps-documentos-financeiros`, o mÃ©todo `activate()` apenas atualizava option mas nÃ£o verificava/atualizava conteÃºdo da pÃ¡gina
    - **Sintoma**: PÃ¡gina aparecia em branco se foi criada manualmente ou teve conteÃºdo removido
    - **SoluÃ§Ã£o**: Adicionada verificaÃ§Ã£o em `activate()` para garantir que pÃ¡gina existente sempre tenha shortcode `[dps_fin_docs]`
    - **Impacto**: PÃ¡gina de documentos sempre funcional mesmo apÃ³s modificaÃ§Ãµes manuais
  - **Bug #2 - Falta de controle de acesso**: Shortcode `render_fin_docs_shortcode()` nÃ£o verificava permissÃµes
    - **Sintoma**: Qualquer visitante poderia acessar lista de documentos financeiros sensÃ­veis
    - **SoluÃ§Ã£o**: Adicionada verificaÃ§Ã£o `current_user_can('manage_options')` com filtro `dps_finance_docs_allow_public` para flexibilidade
    - **Impacto**: Documentos agora requerem autenticaÃ§Ã£o e permissÃ£o administrativa por padrÃ£o
  - **Bug #3 - CSRF em aÃ§Ãµes de documentos (CRÃTICO)**: AÃ§Ãµes `dps_send_doc` e `dps_delete_doc` nÃ£o verificavam nonce
    - **Vulnerabilidade**: CSRF permitindo atacante forÃ§ar usuÃ¡rio autenticado a enviar/deletar documentos
    - **SoluÃ§Ã£o**: Adicionada verificaÃ§Ã£o de nonce em ambas as aÃ§Ãµes; links atualizados para usar `wp_nonce_url()` com nonces Ãºnicos por arquivo
    - **Impacto**: Eliminada vulnerabilidade CSRF crÃ­tica; aÃ§Ãµes de documentos agora protegidas contra ataques
  - **Melhoria de UX**: Listagem de documentos convertida de `<ul>` para tabela estruturada
    - Novas colunas: Documento, Cliente, Data, Valor, AÃ§Ãµes
    - InformaÃ§Ãµes extraÃ­das automaticamente da transaÃ§Ã£o vinculada
    - FormataÃ§Ã£o adequada de datas e valores monetÃ¡rios
    - **Impacto**: Interface mais profissional e informativa; documentos identificÃ¡veis sem precisar abri-los
  - **AnÃ¡lise completa**: Documento detalhado criado em `docs/review/finance-addon-analysis-2025-12-06.md` com 10 sugestÃµes de melhorias futuras
- **AI Add-on (v1.6.0)**: Corrigido shortcode `[dps_ai_public_chat]` aparecendo como texto plano
  - **Problema**: Shortcode nunca era registrado, aparecendo como texto plano nas pÃ¡ginas
  - **Causa**: `init_components()` estava registrado no hook `plugins_loaded` (prioridade 21), mas `DPS_AI_Addon` sÃ³ era inicializado no hook `init` (prioridade 5). Como `plugins_loaded` executa ANTES de `init`, o hook nunca era chamado.
  - **SoluÃ§Ã£o**:
    1. Alterado hook de `init_components()` e `init_portal_integration()` de `plugins_loaded` para `init`
    2. Removido mÃ©todo intermediÃ¡rio `register_shortcode()` e chamado `add_shortcode()` diretamente no construtor
  - **Impacto**: Shortcode agora renderiza corretamente o chat pÃºblico quando inserido em pÃ¡ginas/posts
- **Compatibilidade WordPress 6.2+**: SubstituÃ­da funÃ§Ã£o deprecada `get_page_by_title()` por `dps_get_page_by_title_compat()` no Portal do Cliente. A nova funÃ§Ã£o usa `WP_Query` conforme recomendaÃ§Ã£o oficial do WordPress, garantindo compatibilidade com WordPress 6.9+
- **Plugin Base**: Corrigido botÃµes "Selecionar todos" e "Desmarcar todos" na seleÃ§Ã£o de pets
  - O handler de toggle de pets usava `.data('owner')` que lÃª do cache interno do jQuery
  - ApÃ³s PR #165, `buildPetOption` passou a usar `.attr()` para definir atributos DOM
  - O handler de toggle nÃ£o foi atualizado junto, causando inconsistÃªncia
  - **Corrigido**: Alterado handler para usar `.attr('data-owner')` ao invÃ©s de `.data('owner')`
  - **Impacto**: BotÃµes de seleÃ§Ã£o/desmarcar todos os pets agora funcionam corretamente
- **Groomers Add-on**: Corrigido `uninstall.php` para usar meta key correta `_dps_groomers`
  - Problema: arquivo tentava deletar meta keys incorretas (`appointment_groomer_id`, `appointment_groomers`)
  - Meta key correta Ã© `_dps_groomers` (array de IDs de groomers)
  - **Impacto**: DesinstalaÃ§Ã£o do add-on agora remove corretamente os metadados
- **Plugin Base**: Corrigido seletor de pets nÃ£o exibir pets ao selecionar cliente no formulÃ¡rio de agendamentos
  - A funÃ§Ã£o `buildPetOption` usava `$('<label/>', { 'data-owner': ... })` que armazena dados no cache interno do jQuery
  - A funÃ§Ã£o `applyPetFilters` usava `.attr('data-owner')` para ler, que busca no atributo DOM (sempre vazio)
  - **Corrigido**: Alterado para usar `.attr()` para definir `data-owner` e `data-search`, garantindo consistÃªncia
  - **Impacto**: Pets do cliente selecionado agora aparecem corretamente na lista de seleÃ§Ã£o de pets
- **Plugin Base**: Corrigido aviso PHP `map_meta_cap was called incorrectly` no WordPress 6.1+
  - Adicionadas capabilities de exclusÃ£o faltantes (`delete_posts`, `delete_private_posts`, `delete_published_posts`, `delete_others_posts`) nos CPTs:
    - `dps_cliente` (Clientes)
    - `dps_pet` (Pets)
    - `dps_agendamento` (Agendamentos)
  - **Corrigido**: Notices repetidos no error log sobre `delete_post` capability sem post especÃ­fico
  - **Impacto**: Elimina avisos no log ao excluir ou gerenciar posts dos CPTs personalizados
- **Plugin Base**: Corrigido aviso PHP `Undefined variable $initial_pending_rows`
  - Inicializada variÃ¡vel como array vazio antes de uso condicional
  - **Corrigido**: Notice na linha 1261 de class-dps-base-frontend.php
  - **Impacto**: Elimina aviso no error log ao carregar formulÃ¡rio de agendamentos
- **Stock Add-on**: Adicionadas capabilities de exclusÃ£o faltantes (`delete_private_posts`, `delete_published_posts`)
  - Complementa capabilities jÃ¡ existentes para total compatibilidade com `map_meta_cap`
- NÃºmero da equipe agora Ã© configurÃ¡vel e centralizado (antes estava hardcoded em vÃ¡rios locais)
- FormataÃ§Ã£o de nÃºmeros de telefone padronizada em todo o sistema usando `DPS_Phone_Helper`
- Portal do Cliente agora usa nÃºmero da equipe configurado ao invÃ©s de placeholder `5551999999999`
- Todos os links WhatsApp agora formatam nÃºmeros de clientes corretamente (adicionam cÃ³digo do paÃ­s automaticamente)
- **AI Add-on & Client Portal Add-on**: Corrigido assistente virtual no Portal do Cliente
  - Adicionado mÃ©todo pÃºblico `get_current_client_id()` na classe `DPS_Client_Portal` para permitir acesso externo ao ID do cliente autenticado
  - Criado novo hook `dps_client_portal_before_content` que dispara apÃ³s a navegaÃ§Ã£o e antes das seÃ§Ãµes de conteÃºdo
  - Movido widget do assistente virtual de `dps_client_portal_after_content` para `dps_client_portal_before_content`
  - **Corrigido**: Erro "VocÃª precisa estar logado para usar o assistente" ao acessar portal via link de acesso
  - **Corrigido**: Posicionamento do assistente agora Ã© no topo da pÃ¡gina (apÃ³s navegaÃ§Ã£o), conforme especificaÃ§Ã£o
  - **Impacto**: Assistente virtual agora funciona corretamente quando cliente acessa via token/link permanente
- **Services Add-on & Loyalty Add-on (WordPress 6.7+)**: Corrigido carregamento de traduÃ§Ãµes antes do hook 'init'
  - Movido carregamento de text domain para hook 'init' com prioridade 1 (anteriormente prioridade padrÃ£o 10)
  - Movida instanciaÃ§Ã£o de classes para hook 'init' com prioridade 5:
    - Services Add-on: de escopo global para `init` priority 5
    - Loyalty Add-on: de hook `plugins_loaded` para `init` priority 5
  - Ordem de execuÃ§Ã£o garantida: (1) text domain carrega em init:1, (2) classe instancia em init:5, (3) CPT registra em init:10
  - **Corrigido**: PHP Notice "Translation loading for the domain was triggered too early" no WordPress 6.7.0+
  - **Documentado**: PadrÃ£o de carregamento de text domains no ANALYSIS.md seÃ§Ã£o "Text Domains para InternacionalizaÃ§Ã£o"
- **Loyalty Add-on**: Corrigido erro de capability check ao atribuir pontos
  - Adicionada verificaÃ§Ã£o se o post existe antes de chamar `get_post_type()`
  - **Corrigido**: Notice "map_meta_cap was called incorrectly" ao verificar capability `delete_post`
  - Previne erro quando WordPress verifica capabilities internamente durante mudanÃ§a de status de agendamento
- **Plugin Base**: Corrigido acesso ao painel de gestÃ£o para usuÃ¡rios com role `dps_reception`
  - FunÃ§Ã£o `can_manage()` agora aceita `manage_options` OU qualquer capability DPS especÃ­fica (`dps_manage_clients`, `dps_manage_pets`, `dps_manage_appointments`)
  - Removida verificaÃ§Ã£o duplicada de `manage_options` no mÃ©todo `handle_request()` que bloqueava usuÃ¡rios sem permissÃ£o de administrador
  - UsuÃ¡rios com capabilities DPS especÃ­ficas agora podem acessar o painel e executar aÃ§Ãµes permitidas
  - **Corrigido**: Pets vinculados ao cliente nÃ£o apareciam ao selecionar cliente (causado pelo bloqueio de acesso ao painel)
  - **Corrigido**: Erro "Acesso negado" ao alterar status de agendamento (causado pela verificaÃ§Ã£o duplicada de permissÃµes)
  - Atualizada mensagem de erro de login para refletir que nÃ£o apenas administradores podem acessar
  - Adicionada documentaÃ§Ã£o explicando modelo de permissÃµes: painel visÃ­vel para qualquer capability DPS, mas aÃ§Ãµes protegidas individualmente
- **Menus Administrativos**: Corrigido registro de menus em add-ons
  - Backup Add-on: submenu agora aparece corretamente sob "desi.pet by PRObst" (corrigida ordem de carregamento)
  - Loyalty Add-on: menus agora aparecem sob "desi.pet by PRObst" em vez de criar menu prÃ³prio separado
  - Logs do Sistema: migrado de menu separado para submenu sob "desi.pet by PRObst" (melhor organizaÃ§Ã£o)
  - Mensagens do Portal: migrado de menu separado para submenu sob "desi.pet by PRObst" (CPT com show_in_menu)
  - Cadastro PÃºblico renomeado para "FormulÃ¡rio de Cadastro" (nome mais intuitivo)
  - Todos os add-ons com menus agora usam prioridade 20 no hook `admin_menu` para garantir que o menu pai jÃ¡ existe
  - Estrutura de menus documentada em `ANALYSIS.md` na seÃ§Ã£o "Estrutura de Menus Administrativos"
  - Adicionadas diretrizes de nomenclatura para melhorar usabilidade (nomes descritivos, sem prefixos redundantes)
  - **Impacto**: Todos os menus e submenus agora estÃ£o agrupados no mesmo menu principal "desi.pet by PRObst" para facilitar gerenciamento
- **FormulÃ¡rio de Agendamentos**: Melhorias de responsividade para telas pequenas
  - Corrigido overflow horizontal em mobile e tablet (adicionado `overflow-x: hidden` em `.dps-form`)
  - Ajustado tamanho de inputs e selects para mobile (`padding: 8px` em â‰¤768px, `10px 8px` em â‰¤480px)
  - IncluÃ­dos todos os tipos de input (date, time, number) nas regras de font-size mobile (16px para evitar zoom iOS)
  - Adicionado wrapper `.dps-form-field` com margin-bottom consistente (12px)
  - Reduzido padding de fieldsets em mobile pequeno (12px em â‰¤480px)
  - Ajustado card de resumo para telas pequenas:
    - Labels strong: `min-width: 100px` (era 140px) em â‰¤480px
    - Font-size reduzido para 13px (itens) e 16px (tÃ­tulo H3)
  - Reduzido tamanho da legend em telas muito pequenas (15px em â‰¤480px)
- **Finance Add-on**: Corrigido fatal error ao renderizar mensagens de feedback
  - **Problema**: Chamada a mÃ©todo inexistente `DPS_Message_Helper::render()` causava fatal error na linha 1725
  - **Causa**: Finance add-on tentava usar mÃ©todo `render()` que nÃ£o existe na classe `DPS_Message_Helper`
  - **SoluÃ§Ã£o**: SubstituÃ­da chamada por renderizaÃ§Ã£o inline usando a mesma estrutura HTML do mÃ©todo `display_messages()`
  - **Impacto**: Mensagens de feedback (sucesso/erro) agora sÃ£o exibidas corretamente na seÃ§Ã£o financeira sem causar erros

#### Security (SeguranÃ§a)
- **Finance Add-on (v1.3.1)**: Corrigida vulnerabilidade CSRF crÃ­tica em aÃ§Ãµes de documentos
  - **Vulnerabilidade**: AÃ§Ãµes `dps_send_doc` e `dps_delete_doc` nÃ£o verificavam nonce, permitindo CSRF
  - **Impacto potencial**: Atacante poderia forÃ§ar administrador autenticado a:
    - Enviar documentos financeiros sensÃ­veis para emails maliciosos
    - Deletar documentos importantes sem autorizaÃ§Ã£o
    - Executar aÃ§Ãµes nÃ£o autorizadas em documentos
  - **SoluÃ§Ã£o**: Adicionada verificaÃ§Ã£o de nonce Ãºnica por arquivo em ambas as aÃ§Ãµes
  - **ProteÃ§Ã£o adicional**: Controle de acesso via `current_user_can('manage_options')` no shortcode
  - **Severidade**: CRÃTICA - eliminada completamente com as correÃ§Ãµes implementadas
- **Services Add-on**: Corrigidas vulnerabilidades CSRF crÃ­ticas
  - Adicionada verificaÃ§Ã£o de nonce em exclusÃ£o de serviÃ§o (`dps_delete_service_{id}`)
  - Adicionada verificaÃ§Ã£o de nonce em toggle de status (`dps_toggle_service_{id}`)
  - Adicionada verificaÃ§Ã£o de post_type antes de excluir/modificar
  - URLs de aÃ§Ã£o agora usam `wp_nonce_url()` para proteÃ§Ã£o automÃ¡tica
  - **Impacto**: Elimina possibilidade de exclusÃ£o/alteraÃ§Ã£o de serviÃ§os via links maliciosos
- Todas as URLs de WhatsApp usam `esc_url()` para escape adequado
- Mensagens de WhatsApp usam `rawurlencode()` para encoding seguro de caracteres especiais
- NÃºmeros de telefone sÃ£o sanitizados via `sanitize_text_field()` antes de salvar configuraÃ§Ã£o
- Helper `DPS_WhatsApp_Helper` implementa validaÃ§Ã£o de entrada para prevenir links malformados

#### Documentation (DocumentaÃ§Ã£o)
- **ANALYSIS.md**: Atualizada seÃ§Ã£o "Portal do Cliente" com novos hooks, funÃ§Ãµes helper e versÃ£o 2.1.0
- **Client Portal README.md**: Atualizada seÃ§Ã£o "Para administradores" com instruÃ§Ãµes de configuraÃ§Ã£o da pÃ¡gina do portal

#### Added (Adicionado)
- **Client Portal Add-on (v2.1.0)**: Interface de configuraÃ§Ãµes para gerenciamento do Portal do Cliente
  - Nova aba "Portal" nas configuraÃ§Ãµes do sistema para configurar pÃ¡gina do portal
  - Campo de seleÃ§Ã£o (dropdown) para escolher a pÃ¡gina onde o shortcode `[dps_client_portal]` estÃ¡ inserido
  - ExibiÃ§Ã£o do link do portal com botÃ£o "Copiar Link" para facilitar compartilhamento
  - InstruÃ§Ãµes de uso do portal com passos detalhados
  - Salvamento de configuraÃ§Ãµes via option `dps_portal_page_id` com validaÃ§Ã£o de nonce
  - FunÃ§Ãµes helper globais `dps_get_portal_page_url()` e `dps_get_portal_page_id()` para obter URL/ID do portal
  - Fallback automÃ¡tico para pÃ¡gina com tÃ­tulo "Portal do Cliente" (compatibilidade com versÃµes anteriores)
  - Template `templates/portal-settings.php` com estilos minimalistas DPS
  - Script inline para copiar URL do portal com feedback visual
- **Payment Add-on**: DocumentaÃ§Ã£o completa de configuraÃ§Ã£o do webhook secret
  - Novo arquivo `WEBHOOK_CONFIGURATION.md` com guia passo a passo completo
  - InstruÃ§Ãµes detalhadas sobre geraÃ§Ã£o de senha forte, configuraÃ§Ã£o no DPS e no Mercado Pago
  - Exemplos de URLs de webhook com os 4 mÃ©todos suportados (query parameter, headers)
  - SeÃ§Ã£o de troubleshooting com erros comuns e soluÃ§Ãµes
  - SeÃ§Ã£o de validaÃ§Ã£o e testes com exemplos de logs
  - FAQ com perguntas frequentes sobre seguranÃ§a e configuraÃ§Ã£o
- **InternacionalizaÃ§Ã£o (i18n)**: DocumentaÃ§Ã£o de text domains oficiais em ANALYSIS.md para facilitar traduÃ§Ã£o
- **Client Portal Add-on (v2.0.0)**: Sistema completo de autenticaÃ§Ã£o por token (magic links)
  - **BREAKING CHANGE**: SubstituÃ­do sistema de login com senha por autenticaÃ§Ã£o via links com token
  - Nova tabela `wp_dps_portal_tokens` para gerenciar tokens de acesso
  - Classe `DPS_Portal_Token_Manager` para geraÃ§Ã£o, validaÃ§Ã£o e revogaÃ§Ã£o de tokens
  - Classe `DPS_Portal_Session_Manager` para gerenciar sessÃµes independentes do WordPress
  - Classe `DPS_Portal_Admin_Actions` para processar aÃ§Ãµes administrativas
  - Tokens seguros de 64 caracteres com hash (password_hash/password_verify)
  - ExpiraÃ§Ã£o configurÃ¡vel (padrÃ£o 30 minutos)
  - MarcaÃ§Ã£o de uso (single use)
  - Cleanup automÃ¡tico via cron job (tokens > 30 dias)
  - Tela de acesso pÃºblica minimalista (`templates/portal-access.php`)
  - Interface administrativa completa de gerenciamento (`templates/admin-logins.php`)
  - Tabela responsiva de clientes com status de acesso e Ãºltimo login
  - BotÃµes "Primeiro Acesso" e "Gerar Novo Link"
  - BotÃ£o "Revogar" para invalidar tokens ativos
  - ExibiÃ§Ã£o temporÃ¡ria de links gerados (5 minutos)
  - IntegraÃ§Ã£o com WhatsApp: abre WhatsApp Web com mensagem pronta
  - IntegraÃ§Ã£o com E-mail: modal de prÃ©-visualizaÃ§Ã£o obrigatÃ³ria antes de enviar
  - JavaScript para copiar links, modais e AJAX (`assets/js/portal-admin.js`)
  - Busca de clientes por nome ou telefone
  - Feedback visual para todas as aÃ§Ãµes
  - Compatibilidade com sistema antigo mantida (fallback)
  - DocumentaÃ§Ã£o em `templates/portal-access.php` e `templates/admin-logins.php`
- **AI Add-on (v1.1.0)**: Campo de "InstruÃ§Ãµes adicionais" nas configuraÃ§Ãµes da IA
  - Permite administrador complementar comportamento da IA sem substituir regras base de seguranÃ§a
  - Campo opcional com limite de 2000 caracteres
  - InstruÃ§Ãµes adicionais sÃ£o enviadas como segunda mensagem de sistema apÃ³s prompt base
  - Prompt base protegido contra contradiÃ§Ãµes posteriores
  - Novo mÃ©todo pÃºblico `DPS_AI_Assistant::get_base_system_prompt()` para reutilizaÃ§Ã£o
- **AI Add-on (v1.2.0)**: Assistente de IA para ComunicaÃ§Ãµes
  - Nova classe `DPS_AI_Message_Assistant` para gerar sugestÃµes de mensagens
  - `DPS_AI_Message_Assistant::suggest_whatsapp_message($context)` - Gera sugestÃ£o de mensagem para WhatsApp
  - `DPS_AI_Message_Assistant::suggest_email_message($context)` - Gera sugestÃ£o de e-mail (assunto e corpo)
  - Handlers AJAX `wp_ajax_dps_ai_suggest_whatsapp_message` e `wp_ajax_dps_ai_suggest_email_message`
  - Interface JavaScript com botÃµes de sugestÃ£o e modal de prÃ©-visualizaÃ§Ã£o para e-mails
  - Suporta 6 tipos de mensagens: lembrete, confirmaÃ§Ã£o, pÃ³s-atendimento, cobranÃ§a suave, cancelamento, reagendamento
  - **IMPORTANTE**: IA NUNCA envia automaticamente - apenas gera sugestÃµes que o usuÃ¡rio revisa antes de enviar
  - DocumentaÃ§Ã£o completa em `plugins/desi-pet-shower-ai/AI_COMMUNICATIONS.md`
  - Exemplos de integraÃ§Ã£o em `plugins/desi-pet-shower-ai/includes/ai-communications-examples.php`
- **Services Add-on**: Nova API pÃºblica (`DPS_Services_API`) para centralizar lÃ³gica de serviÃ§os e cÃ¡lculo de preÃ§os (v1.2.0)
  - `DPS_Services_API::get_service($service_id)` - Retornar dados completos de um serviÃ§o
  - `DPS_Services_API::calculate_price($service_id, $pet_size, $context)` - Calcular preÃ§o por porte do pet
  - `DPS_Services_API::calculate_appointment_total($services_ids, $pets_ids, $context)` - Calcular total de agendamento
  - `DPS_Services_API::get_services_details($appointment_id)` - Retornar detalhes dos serviÃ§os de um agendamento
- **Services Add-on**: Endpoint AJAX `dps_get_services_details` movido da Agenda para Services (mantÃ©m compatibilidade)
- **Finance Add-on**: Nova API financeira pÃºblica (`DPS_Finance_API`) para centralizar operaÃ§Ãµes de cobranÃ§as
  - `DPS_Finance_API::create_or_update_charge()` - Criar ou atualizar cobranÃ§a vinculada a agendamento
  - `DPS_Finance_API::mark_as_paid()` - Marcar cobranÃ§a como paga
  - `DPS_Finance_API::mark_as_pending()` - Reabrir cobranÃ§a como pendente
  - `DPS_Finance_API::mark_as_cancelled()` - Cancelar cobranÃ§a
  - `DPS_Finance_API::get_charge()` - Buscar dados de uma cobranÃ§a
  - `DPS_Finance_API::get_charges_by_appointment()` - Buscar todas as cobranÃ§as de um agendamento
  - `DPS_Finance_API::delete_charges_by_appointment()` - Remover cobranÃ§as ao excluir agendamento
  - `DPS_Finance_API::validate_charge_data()` - Validar dados antes de criar/atualizar
- **Finance Add-on**: Novos hooks para integraÃ§Ã£o:
  - `dps_finance_charge_created` - Disparado ao criar nova cobranÃ§a
  - `dps_finance_charge_updated` - Disparado ao atualizar cobranÃ§a existente
  - `dps_finance_charges_deleted` - Disparado ao deletar cobranÃ§as de um agendamento
- **Agenda Add-on**: VerificaÃ§Ã£o de dependÃªncia do Finance Add-on com aviso no admin
- **DocumentaÃ§Ã£o**: `FINANCE_AGENDA_REORGANIZATION_DIAGNOSTIC.md` - DiagnÃ³stico completo da reorganizaÃ§Ã£o arquitetural (33KB, 7 seÃ§Ãµes)
- Criadas classes helper para melhorar qualidade e manutenibilidade do cÃ³digo:
  - `DPS_Money_Helper`: manipulaÃ§Ã£o consistente de valores monetÃ¡rios, conversÃ£o formato brasileiro â†” centavos
  - `DPS_URL_Builder`: construÃ§Ã£o padronizada de URLs de ediÃ§Ã£o, exclusÃ£o, visualizaÃ§Ã£o e navegaÃ§Ã£o
  - `DPS_Query_Helper`: consultas WP_Query reutilizÃ¡veis com filtros comuns e paginaÃ§Ã£o
  - `DPS_Request_Validator`: validaÃ§Ã£o centralizada de nonces, capabilities e sanitizaÃ§Ã£o de campos
- Criada classe `DPS_Message_Helper` para feedback visual consistente:
  - Mensagens de sucesso, erro e aviso via transients especÃ­ficos por usuÃ¡rio
  - ExibiÃ§Ã£o automÃ¡tica no topo das seÃ§Ãµes com remoÃ§Ã£o apÃ³s visualizaÃ§Ã£o
  - Integrada em todos os fluxos de salvamento e exclusÃ£o (clientes, pets, agendamentos)
- Adicionado documento de anÃ¡lise de refatoraÃ§Ã£o (`docs/refactoring/REFACTORING_ANALYSIS.md`) com identificaÃ§Ã£o detalhada de problemas de cÃ³digo e sugestÃµes de melhoria
- Criado arquivo de exemplos prÃ¡ticos (`includes/refactoring-examples.php`) demonstrando uso das classes helper e padrÃµes de refatoraÃ§Ã£o
- Implementado `register_deactivation_hook` no add-on Agenda para limpar cron job `dps_agenda_send_reminders` ao desativar
- Adicionada seÃ§Ã£o completa de "PadrÃµes de desenvolvimento de add-ons" no `ANALYSIS.md` incluindo:
  - Estrutura de arquivos recomendada com separaÃ§Ã£o de responsabilidades
  - Guia de uso correto de activation/deactivation hooks
  - PadrÃµes de documentaÃ§Ã£o com DocBlocks seguindo convenÃ§Ãµes WordPress
  - Boas prÃ¡ticas de prefixaÃ§Ã£o, seguranÃ§a, performance e integraÃ§Ã£o
- Criados documentos de anÃ¡lise e guias de estilo:
  - `docs/visual/VISUAL_STYLE_GUIDE.md`: guia completo de cores, tipografia, componentes e Ã­cones (450+ linhas)
  - `docs/layout/admin/ADMIN_LAYOUT_ANALYSIS.md`: anÃ¡lise detalhada de usabilidade das telas administrativas (600+ linhas)
  - `docs/implementation/UI_UX_IMPROVEMENTS_SUMMARY.md`: resumo executivo de melhorias implementadas
- **AI Add-on**: Novo add-on de Assistente Virtual para Portal do Cliente (v1.0.0)
  - Assistente focado EXCLUSIVAMENTE em Banho e Tosa, serviÃ§os, agendamentos, histÃ³rico e funcionalidades do DPS
  - IntegraÃ§Ã£o com OpenAI Chat Completions API (GPT-3.5 Turbo / GPT-4 / GPT-4 Turbo)
  - System prompt restritivo que proÃ­be conversas sobre polÃ­tica, religiÃ£o, tecnologia e outros assuntos fora do contexto
  - Filtro preventivo de palavras-chave antes de chamar API (economiza custos e protege contexto)
  - Widget de chat responsivo no Portal do Cliente com estilos minimalistas DPS
  - Contexto automÃ¡tico incluindo dados do cliente/pet, agendamentos recentes, pendÃªncias financeiras e pontos de fidelidade
  - Endpoint AJAX `dps_ai_portal_ask` com validaÃ§Ã£o de nonce e cliente logado
  - Interface administrativa para configuraÃ§Ã£o (API key, modelo, temperatura, timeout, max_tokens)
  - Sistema autocontido: falhas nÃ£o afetam funcionamento do Portal
  - DocumentaÃ§Ã£o completa em `plugins/desi-pet-shower-ai/README.md`
- **Client Portal Add-on**: Novo hook `dps_client_portal_after_content` para permitir add-ons adicionarem conteÃºdo ao final do portal (usado pelo AI Add-on)
  - `docs/layout/admin/ADMIN_LAYOUT_ANALYSIS.md`: anÃ¡lise detalhada de usabilidade e layout das telas administrativas
  - `docs/visual/VISUAL_STYLE_GUIDE.md`: guia oficial de estilo visual minimalista
  - `docs/implementation/UI_UX_IMPROVEMENTS_SUMMARY.md`: resumo das melhorias implementadas
  - `docs/layout/forms/FORMS_UX_ANALYSIS.md`: anÃ¡lise completa de UX dos formulÃ¡rios de cadastro com priorizaÃ§Ã£o de melhorias
- **Agenda Add-on**: Implementadas melhorias de FASE 1 e FASE 2:
  - BotÃ£o "âž• Novo Agendamento" adicionado Ã  barra de navegaÃ§Ã£o para workflow completo
  - Modal customizado para visualizaÃ§Ã£o de serviÃ§os (substitui alert() nativo)
  - Ãcones e tooltips em links de aÃ§Ã£o (ðŸ“ Mapa, ðŸ’¬ Confirmar, ðŸ’° Cobrar)
  - Flag de pet agressivo melhorada (âš ï¸ com tooltip "Pet agressivo - cuidado no manejo")
  - Criados arquivos de assets: `assets/css/agenda-addon.css` e `assets/js/services-modal.js`
- **FormulÃ¡rios de cadastro**: Sistema completo de grid responsivo e componentes visuais:
  - Classes CSS para grid: `.dps-form-row`, `.dps-form-row--2col`, `.dps-form-row--3col`
  - Asterisco vermelho para campos obrigatÃ³rios: `.dps-required`
  - Checkbox melhorado: `.dps-checkbox-label`, `.dps-checkbox-text`
  - Upload de arquivo estilizado: `.dps-file-upload` com border dashed e hover
  - Preview de imagem antes do upload via JavaScript (FileReader API)
  - DesabilitaÃ§Ã£o automÃ¡tica de botÃ£o submit durante salvamento (previne duplicatas)

#### Changed (Alterado)
- **Client Portal Add-on**: RefatoraÃ§Ã£o de 7 ocorrÃªncias de `get_page_by_title('Portal do Cliente')` hardcoded
  - SubstituÃ­do por chamadas Ã s funÃ§Ãµes helper centralizadas `dps_get_portal_page_url()` e `dps_get_portal_page_id()`
  - Modificados: `class-dps-client-portal.php` (4x), `class-dps-portal-session-manager.php` (2x), `class-dps-portal-token-manager.php` (1x)
  - Mantido comportamento legado como fallback dentro das funÃ§Ãµes helper
- **Payment Add-on**: Campo "Webhook secret" nas configuraÃ§Ãµes melhorado com instruÃ§Ãµes inline
  - DescriÃ§Ã£o expandida com passos numerados de configuraÃ§Ã£o
  - Exemplo de URL do webhook com domÃ­nio real do site
  - Link para guia completo de configuraÃ§Ã£o (abre em nova aba)
  - Destaque visual para facilitar compreensÃ£o da configuraÃ§Ã£o obrigatÃ³ria
- **Payment Add-on README.md**: SeÃ§Ã£o de configuraÃ§Ã£o atualizada com destaque para webhook secret
  - Aviso destacado sobre obrigatoriedade do webhook secret no topo do documento
  - Link proeminente para guia de configuraÃ§Ã£o em mÃºltiplas seÃ§Ãµes
  - Fluxo automÃ¡tico atualizado com passo de validaÃ§Ã£o do webhook secret
- **ANALYSIS.md**: DocumentaÃ§Ã£o do Payment Add-on atualizada
  - Option `dps_mercadopago_webhook_secret` adicionada Ã  lista de opÃ§Ãµes armazenadas
  - ReferÃªncia ao guia de configuraÃ§Ã£o completo em observaÃ§Ãµes do add-on
- **Communications Add-on v0.2.0**: Arquitetura completamente reorganizada
  - Toda lÃ³gica de envio centralizada em `DPS_Communications_API`
  - Templates de mensagens com suporte a placeholders (`{client_name}`, `{pet_name}`, `{date}`, `{time}`)
  - Logs automÃ¡ticos de envios via `DPS_Logger` (nÃ­veis INFO/ERROR/WARNING)
  - FunÃ§Ãµes legadas `dps_comm_send_whatsapp()` e `dps_comm_send_email()` agora delegam para API (deprecated)
- **Agenda Add-on**: ComunicaÃ§Ãµes delegadas para Communications API
  - Envio de lembretes diÃ¡rios via `DPS_Communications_API::send_appointment_reminder()`
  - NotificaÃ§Ãµes de status (finalizado/finalizado_pago) via `DPS_Communications_API::send_whatsapp()`
  - MÃ©todo `format_whatsapp_number()` agora delega para `DPS_Phone_Helper` (deprecated)
  - **Mantidos**: botÃµes de confirmaÃ§Ã£o e cobranÃ§a via links wa.me (nÃ£o sÃ£o envios automÃ¡ticos)
- **Client Portal Add-on**: Mensagens de clientes delegadas para Communications API
  - Envio de mensagens do Portal via `DPS_Communications_API::send_message_from_client()`
  - Fallback para `wp_mail()` direto se API nÃ£o estiver disponÃ­vel (compatibilidade retroativa)
- **Agenda Add-on**: Agora depende do Finance Add-on para funcionalidade completa de cobranÃ§as
- **Agenda Add-on**: Removida lÃ³gica financeira duplicada (~55 linhas de SQL direto)
- **Agenda Add-on**: `update_status_ajax()` agora confia na sincronizaÃ§Ã£o automÃ¡tica do Finance via hooks
- **Finance Add-on**: `cleanup_transactions_for_appointment()` agora delega para `DPS_Finance_API`
- **Finance Add-on**: FunÃ§Ãµes `dps_parse_money_br()` e `dps_format_money_br()` agora delegam para `DPS_Money_Helper` do nÃºcleo
- **Loyalty Add-on**: FunÃ§Ã£o `dps_format_money_br()` agora delega para `DPS_Money_Helper` do nÃºcleo
- Interface administrativa completamente reformulada com design minimalista:
  - Paleta de cores reduzida e consistente (base neutra + 3 cores de status essenciais)
  - RemoÃ§Ã£o de sombras decorativas e elementos visuais desnecessÃ¡rios
  - Alertas simplificados com borda lateral colorida (sem pseudo-elementos ou fundos vibrantes)
  - Cores de status em tabelas mais suaves (amarelo claro, verde claro, cinza neutro, opacidade para cancelados)
- Hierarquia semÃ¢ntica corrigida em todas as telas do painel:
  - H1 Ãºnico no topo do painel ("Painel de GestÃ£o DPS")
  - H2 para seÃ§Ãµes principais (Cadastro de Clientes, Cadastro de Pets, etc.)
  - H3 para subseÃ§Ãµes e listagens com separaÃ§Ã£o visual (borda superior + padding)
- FormulÃ¡rios reorganizados com agrupamento lÃ³gico de campos:
  - FormulÃ¡rio de clientes dividido em 4 fieldsets: Dados Pessoais, Contato, Redes Sociais, EndereÃ§o e PreferÃªncias
  - Bordas sutis (#e5e7eb) e legends descritivos para cada grupo
  - ReduÃ§Ã£o de sobrecarga cognitiva atravÃ©s de organizaÃ§Ã£o visual clara
- **FormulÃ¡rio de Pet (Admin) completamente reestruturado**:
  - Dividido em 4 fieldsets temÃ¡ticos (antes eram 17+ campos soltos):
    1. **Dados BÃ¡sicos**: Nome, Cliente, EspÃ©cie, RaÃ§a, Sexo (grid 2col e 3col)
    2. **CaracterÃ­sticas FÃ­sicas**: Tamanho, Peso, Data nascimento, Tipo de pelo, Cor (grid 3col e 2col)
    3. **SaÃºde e Comportamento**: Vacinas, Alergias, Cuidados, Notas, Checkbox "CÃ£o agressivo âš ï¸"
    4. **Foto do Pet**: Upload estilizado com preview
  - Labels melhorados: "Pelagem" â†’ "Tipo de pelo", "Porte" â†’ "Tamanho", "Cor" â†’ "Cor predominante"
  - Peso com validaÃ§Ã£o HTML5: `min="0.1" max="100" step="0.1"`
  - Placeholders descritivos em todos os campos (ex.: "Curto, longo, encaracolado...", "Branco, preto, caramelo...")
- **FormulÃ¡rio de Cliente (Admin)** aprimorado:
  - Grid 2 colunas para campos relacionados: CPF + Data nascimento, Instagram + Facebook
  - Placeholders padronizados: CPF "000.000.000-00", Telefone "(00) 00000-0000", Email "seuemail@exemplo.com"
  - Asteriscos (*) em campos obrigatÃ³rios (Nome, Telefone)
  - Input `tel` para telefone em vez de `text` genÃ©rico
  - Checkbox de autorizaÃ§Ã£o de foto com layout melhorado (`.dps-checkbox-label`)
- **Portal do Cliente**: FormulÃ¡rios alinhados ao padrÃ£o minimalista:
  - Grid responsivo em formulÃ¡rios de cliente e pet (2-3 colunas em desktop â†’ 1 coluna em mobile)
  - Placeholders em todos os campos (Telefone, Email, EndereÃ§o, Instagram, Facebook, campos do pet)
  - Labels consistentes: "Pelagem" â†’ "Tipo de pelo", "Porte" â†’ "Tamanho"
  - Upload de foto estilizado com `.dps-file-upload` e preview JavaScript
  - BotÃµes submit com classe `.dps-submit-btn` (largura 100% em mobile)
- Responsividade bÃ¡sica implementada para dispositivos mÃ³veis:
  - Tabelas com scroll horizontal em telas <768px
  - NavegaÃ§Ã£o por abas em layout vertical em mobile
  - Grid de pets em coluna Ãºnica em smartphones
  - Grid de formulÃ¡rios adaptativo: 2-3 colunas em desktop â†’ 1 coluna em mobile @640px
  - Inputs com tamanho de fonte 16px para evitar zoom automÃ¡tico no iOS
  - BotÃµes submit com largura 100% em mobile para melhor Ã¡rea de toque
- DocumentaÃ§Ã£o expandida com exemplos de como quebrar funÃ§Ãµes grandes em mÃ©todos menores e mais focados
- Estabelecidos padrÃµes de nomenclatura mais descritiva para variÃ¡veis e funÃ§Ãµes
- DocumentaÃ§Ã£o do add-on Agenda atualizada para refletir limpeza de cron jobs na desativaÃ§Ã£o
- **Agenda Add-on**: NavegaÃ§Ã£o simplificada e melhorias visuais:
  - BotÃµes de navegaÃ§Ã£o consolidados de 7 para 6, organizados em 3 grupos lÃ³gicos
  - NavegaÃ§Ã£o: [â† Anterior] [Hoje] [PrÃ³ximo â†’] | [ðŸ“… Semana] [ðŸ“‹ Todos] | [âž• Novo]
  - CSS extraÃ­do de inline (~487 linhas) para arquivo externo `assets/css/agenda-addon.css`
  - Border-left de status reduzida de 4px para 3px (estilo mais clean)
  - RemoÃ§Ã£o de transform: translateY(-1px) em hover dos botÃµes (menos movimento visual)
  - RemoÃ§Ã£o de sombras decorativas (apenas bordas 1px solid)

#### Changed (Alterado)
- **Client Portal Add-on (v2.0.0)**: MÃ©todo de autenticaÃ§Ã£o completamente substituÃ­do
  - Sistema antigo de login com usuÃ¡rio/senha do WordPress REMOVIDO
  - Novo sistema baseado 100% em tokens (magic links)
  - Shortcode `[dps_client_login]` agora exibe apenas a tela de acesso minimalista
  - MÃ©todo `render_client_logins_page()` completamente reescrito (de ~400 para ~100 linhas)
  - Interface administrativa totalmente nova baseada em templates
  - Compatibilidade retroativa mantida via fallback no mÃ©todo `get_authenticated_client_id()`
  - **IMPORTANTE**: Clientes existentes precisarÃ£o solicitar novo link de acesso na primeira vez apÃ³s a atualizaÃ§Ã£o

#### Security (SeguranÃ§a)
- **Plugin Base**: Adicionada proteÃ§Ã£o CSRF no logout do painel DPS
  - Novo mÃ©todo `DPS_Base_Frontend::handle_logout()` agora requer nonce vÃ¡lido (`_wpnonce`)
  - ProteÃ§Ã£o contra logout forÃ§ado via links maliciosos (CSRF)
  - SanitizaÃ§Ã£o adequada de parÃ¢metros GET
  - **IMPORTANTE**: Links de logout devem incluir `wp_nonce_url()` com action `dps_logout`
- **Client Portal Add-on (v2.0.0)**: Melhorias de seguranÃ§a no sistema de sessÃµes e e-mails
  - ConfiguraÃ§Ã£o de flags de seguranÃ§a em cookies de sessÃ£o (httponly, secure, samesite=Strict)
  - Modo estrito de sessÃ£o habilitado (use_strict_mode)
  - RegeneraÃ§Ã£o sistemÃ¡tica de session_id em autenticaÃ§Ã£o (proteÃ§Ã£o contra session fixation)
  - E-mails enviados apenas em formato plain text (proteÃ§Ã£o contra social engineering)
  - SanitizaÃ§Ã£o com `sanitize_textarea_field()` em vez de `wp_kses_post()` para e-mails

#### Fixed (Corrigido)
- **InternacionalizaÃ§Ã£o (i18n)**: Corrigidas strings hardcoded nÃ£o traduzÃ­veis
  - **Plugin Base**: 6 strings envolvidas em funÃ§Ãµes de traduÃ§Ã£o
    - Mensagens WhatsApp de cobranÃ§a (individual e conjunta) agora usam `__()` com 'desi-pet-shower'
    - Mensagem de depreciaÃ§Ã£o do shortcode `[dps_configuracoes]` agora usa `__()`
    - Placeholder "Digite ou selecione" no campo de raÃ§a agora usa `esc_attr__()`
    - Mensagem de sucesso de envio de histÃ³rico agora usa `esc_html__()`
    - Prompt de email JavaScript agora usa `esc_js( __() )`
  - **Finance Add-on**: 2 mensagens WhatsApp de cobranÃ§a agora usam `__()` com 'dps-finance-addon'
- **InternacionalizaÃ§Ã£o (i18n)**: Corrigidos text domains incorretos em 4 add-ons
  - **Communications Add-on**: Todas strings (20 ocorrÃªncias) atualizadas de 'desi-pet-shower' para 'dps-communications-addon'
  - **Stock Add-on**: Todas strings (15 ocorrÃªncias) atualizadas de 'desi-pet-shower' para 'dps-stock-addon'
  - **Groomers Add-on**: Todas strings (12 ocorrÃªncias) atualizadas de 'desi-pet-shower' para 'dps-groomers-addon'
  - **Loyalty Add-on**: Todas strings (8 ocorrÃªncias) atualizadas de 'desi-pet-shower' para 'dps-loyalty-addon'
  - Headers dos plugins tambÃ©m atualizados para refletir text domains corretos
- **Agenda Add-on**: Corrigido aviso incorreto de dependÃªncia do Finance Add-on no painel administrativo
  - **Problema**: Mensagem "O Finance Add-on Ã© recomendado para funcionalidade completa de cobranÃ§as" aparecia mesmo com Finance ativo
  - **Causa raiz**: VerificaÃ§Ã£o `class_exists('DPS_Finance_API')` no construtor executava antes do Finance carregar (ordem alfabÃ©tica de plugins)
  - **SoluÃ§Ã£o**: Movida verificaÃ§Ã£o do construtor para hook `plugins_loaded` (novo mÃ©todo `check_finance_dependency()`)
  - **Impacto**: Aviso agora aparece apenas quando Finance realmente nÃ£o estÃ¡ ativo
  - **Arquivo alterado**: `plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php`
- **Plugin Base**: Corrigido erro "Falha ao atualizar. A resposta nÃ£o Ã© um JSON vÃ¡lido" ao inserir shortcode `[dps_base]` no Block Editor
  - **Causa raiz**: MÃ©todo `render_app()` processava logout e POST requests ANTES de iniciar output buffering (`ob_start()`)
  - **Sintoma**: Block Editor falhava ao validar shortcode porque redirects/exits causavam conflito com resposta JSON esperada
  - **SoluÃ§Ã£o**: Movido processamento de logout para hook `init` (novo mÃ©todo `DPS_Base_Frontend::handle_logout()`)
  - **SoluÃ§Ã£o**: Removida chamada redundante a `handle_request()` dentro de `render_app()` (jÃ¡ processado via `init`)
  - **Impacto**: Shortcode `[dps_base]` agora Ã© mÃ©todo puro de renderizaÃ§Ã£o sem side-effects, compatÃ­vel com Block Editor
  - **Arquivos alterados**:
    - `plugins/desi-pet-shower-base/desi-pet-shower-base.php` (adicionado logout ao `maybe_handle_request()`)
    - `plugins/desi-pet-shower-base/includes/class-dps-base-frontend.php` (novo mÃ©todo `handle_logout()`, `render_app()` simplificado)
  - **VerificaÃ§Ã£o**: Todos os outros shortcodes (`[dps_agenda_page]`, `[dps_client_portal]`, `[dps_registration_form]`, etc.) jÃ¡ seguem o padrÃ£o correto
- **Client Portal Add-on**: Corrigido problema de layout onde o card "Portal do Cliente" aparecia antes do cabeÃ§alho do tema
  - **Causa raiz**: MÃ©todo `render_portal_shortcode()` estava chamando `ob_end_clean()` seguido de `include`, causando output direto em vez de retornar HTML via shortcode
  - **Sintoma**: Card do portal aparecia ANTES do menu principal do tema YOOtheme, como se estivesse "encaixado no header"
  - **SoluÃ§Ã£o**: SubstituÃ­do `ob_end_clean() + include + return ''` por `ob_start() + include + return ob_get_clean()`
  - **Impacto**: Portal agora renderiza corretamente DENTRO da Ã¡rea de conteÃºdo da pÃ¡gina, respeitando header/footer do tema
  - **Arquivos alterados**: `plugins/desi-pet-shower-client-portal/includes/class-dps-client-portal.php` (linhas 710-723)
- **Groomers Add-on**: Corrigido fatal error ao renderizar seÃ§Ã£o no front-end via shortcode [dps_base]
  - Problema: funÃ§Ã£o `add_settings_error()` sÃ³ existe no contexto admin (wp-admin)
  - SoluÃ§Ã£o: adicionada verificaÃ§Ã£o `function_exists('add_settings_error')` antes de todas as chamadas
  - Impacto: aba "Groomers" agora funciona corretamente no Painel de GestÃ£o DPS sem fatal errors
  - Mensagens no front-end exibidas via `DPS_Message_Helper`, mantendo compatibilidade com admin
- **Agenda Add-on**: Corrigido syntax error prÃ©-existente (linha 936) com closing brace Ã³rfÃ£o e cÃ³digo quebrado usando variÃ¡veis indefinidas ($client_id, $pet_post, $date, $valor)
- Implementado feedback visual apÃ³s todas as operaÃ§Ãµes principais:
  - Mensagens de sucesso ao salvar clientes, pets e agendamentos
  - Mensagens de confirmaÃ§Ã£o ao excluir registros
  - Alertas de erro quando operaÃ§Ãµes falham
  - Feedback claro e imediato eliminando confusÃ£o sobre conclusÃ£o de aÃ§Ãµes
- Evitado retorno 401 e mensagem "Unauthorized" em acessos comuns ao site, aplicando a validaÃ§Ã£o do webhook do Mercado Pago apenas quando a requisiÃ§Ã£o traz indicadores da notificaÃ§Ã£o
- Corrigido potencial problema de cron jobs Ã³rfÃ£os ao desativar add-on Agenda
- **FormulÃ¡rios de cadastro**: Problemas crÃ­ticos de UX resolvidos:
  - âœ… FormulÃ¡rio de Pet sem fieldsets (17+ campos desorganizados)
  - âœ… Campos obrigatÃ³rios sem indicaÃ§Ã£o visual
  - âœ… Placeholders ausentes em CPF, telefone, email, endereÃ§o
  - âœ… Upload de foto sem preview
  - âœ… BotÃµes de submit sem desabilitaÃ§Ã£o durante processamento (risco de duplicatas)
  - âœ… Labels tÃ©cnicos substituÃ­dos por termos mais claros
  - âœ… Estilos inline substituÃ­dos por classes CSS reutilizÃ¡veis

#### Deprecated (Depreciado)
- **Client Portal Add-on (v2.0.0)**: Sistema de login com usuÃ¡rio/senha descontinuado
  - Shortcode `[dps_client_login]` ainda existe mas comportamento mudou (nÃ£o exibe mais formulÃ¡rio de login)
  - MÃ©todo `maybe_create_login_for_client()` ainda Ã© executado mas nÃ£o tem mais utilidade prÃ¡tica
  - MÃ©todo `get_client_id_for_current_user()` ainda funciona como fallback mas serÃ¡ removido em v3.0.0
  - MÃ©todos relacionados a senha serÃ£o removidos em versÃ£o futura: `render_login_shortcode()` (parcialmente mantido), aÃ§Ãµes de reset/send password
- **Agenda Add-on**: MÃ©todo `get_services_details_ajax()` - LÃ³gica movida para Services Add-on (delega para `DPS_Services_API::get_services_details()`, mantÃ©m compatibilidade com fallback)
- **Agenda Add-on**: Endpoint AJAX `dps_get_services_details` agora Ã© gerenciado pelo Services Add-on (Agenda mantÃ©m por compatibilidade)
- **Finance Add-on**: `dps_parse_money_br()` - Use `DPS_Money_Helper::parse_brazilian_format()` (retrocompatÃ­vel, aviso de depreciaÃ§Ã£o)
- **Finance Add-on**: `dps_format_money_br()` - Use `DPS_Money_Helper::format_to_brazilian()` (retrocompatÃ­vel, aviso de depreciaÃ§Ã£o)
- **Loyalty Add-on**: `dps_format_money_br()` - Use `DPS_Money_Helper::format_to_brazilian()` (retrocompatÃ­vel, aviso de depreciaÃ§Ã£o)
- **Agenda Add-on**: Shortcode `[dps_charges_notes]` - Use `[dps_fin_docs]` do Finance (redirect automÃ¡tico, mensagem de depreciaÃ§Ã£o)

#### Refactoring (Interno)
- **Plugin Base + Agenda Add-on**: CentralizaÃ§Ã£o completa da formataÃ§Ã£o de WhatsApp em `DPS_Phone_Helper::format_for_whatsapp()`
  - Removido mÃ©todo privado `format_whatsapp_number()` de `DPS_Base_Frontend` (13 linhas duplicadas)
  - Removido mÃ©todo wrapper deprecado `format_whatsapp_number()` de `DPS_Agenda_Addon` (19 linhas)
  - Total de 32 linhas de cÃ³digo duplicado eliminadas
  - Todas as chamadas agora usam diretamente `DPS_Phone_Helper::format_for_whatsapp()`
  - **BenefÃ­cios**: eliminaÃ§Ã£o de duplicaÃ§Ã£o, manutenÃ§Ã£o simplificada, consistÃªncia entre add-ons
  - **Arquivos modificados**: `class-dps-base-frontend.php`, `desi-pet-shower-agenda-addon.php`
- **Services Add-on**: Removido header duplicado de plugin no arquivo `dps_service/desi-pet-shower-services-addon.php` (mantÃ©m apenas no wrapper)
- **Subscription Add-on**: Removido header duplicado de plugin no arquivo `dps_subscription/desi-pet-shower-subscription-addon.php` (mantÃ©m apenas no wrapper)
- **Services Add-on**: CentralizaÃ§Ã£o completa de lÃ³gica de serviÃ§os e cÃ¡lculo de preÃ§os via `DPS_Services_API` (reduÃ§Ã£o de duplicaÃ§Ã£o, separaÃ§Ã£o de responsabilidades)
- **Arquitetura**: CentralizaÃ§Ã£o completa de lÃ³gica financeira no Finance Add-on (eliminaÃ§Ã£o de duplicaÃ§Ã£o, reduÃ§Ã£o de acoplamento)
- **Agenda Add-on**: Removidas ~55 linhas de SQL direto para `dps_transacoes` (agora usa sincronizaÃ§Ã£o automÃ¡tica via hooks do Finance)
- **FunÃ§Ãµes monetÃ¡rias**: Todas as chamadas legadas `dps_format_money_br()` e `dps_parse_money_br()` substituÃ­das por `DPS_Money_Helper`
  - Finance Add-on: 11 substituiÃ§Ãµes (4x parse, 7x format)
  - Loyalty Add-on: 2 substituiÃ§Ãµes (format)
  - Services Add-on: 1 substituiÃ§Ã£o (parse com class_exists)
  - Client Portal Add-on: 1 substituiÃ§Ã£o (format com class_exists)
  - Refactoring Examples: 1 substituiÃ§Ã£o (parse)
  - FunÃ§Ãµes legadas mantidas como wrappers deprecados para compatibilidade retroativa
  - Garantia de que `DPS_Money_Helper` Ã© sempre usado internamente, eliminando duplicaÃ§Ã£o de lÃ³gica
- **Finance Add-on**: `cleanup_transactions_for_appointment()` refatorado para delegar para `DPS_Finance_API`
- **PrevenÃ§Ã£o de race conditions**: Apenas Finance escreve em dados financeiros (fonte de verdade Ãºnica)
- **Melhoria de manutenibilidade**: MudanÃ§as financeiras centralizadas em 1 lugar (Finance Add-on API pÃºblica)
- ReestruturaÃ§Ã£o completa do CSS administrativo em `dps-base.css`:
  - SimplificaÃ§Ã£o da classe `.dps-alert` removendo pseudo-elementos decorativos e sombras
  - ReduÃ§Ã£o da paleta de cores de status de 4+ variantes para 3 cores essenciais
  - PadronizaÃ§Ã£o de bordas (1px ou 4px) e espaÃ§amentos (20px padding, 32px entre seÃ§Ãµes)
  - AdiÃ§Ã£o de media queries para responsividade bÃ¡sica (480px, 768px, 1024px breakpoints)
  - AdiÃ§Ã£o de classes para grid de formulÃ¡rios e componentes visuais (fieldsets, upload, checkbox)
- Melhorias estruturais em `class-dps-base-frontend.php`:
  - ExtraÃ§Ã£o de lÃ³gica de mensagens para helper dedicado (`DPS_Message_Helper`)
  - SeparaÃ§Ã£o de campos de formulÃ¡rio em fieldsets semÃ¢nticos
  - PadronizaÃ§Ã£o de tÃ­tulos com hierarquia H1 â†’ H2 â†’ H3 em todas as seÃ§Ãµes
  - AdiÃ§Ã£o de chamadas `display_messages()` no inÃ­cio de cada seÃ§Ã£o do painel
- Melhorias em pÃ¡ginas administrativas de add-ons:
  - Logs: organizaÃ§Ã£o de filtros e tabelas seguindo padrÃ£o minimalista
  - Clientes, pets e agendamentos: consistÃªncia visual com novo sistema de feedback
  - FormulÃ¡rios dos add-ons alinhados ao estilo visual do nÃºcleo
- **Agenda Add-on**: SeparaÃ§Ã£o de responsabilidades e melhoria de arquitetura:
  - ExtraÃ§Ã£o de 487 linhas de CSS inline para arquivo dedicado `assets/css/agenda-addon.css`
  - CriaÃ§Ã£o de componente modal reutilizÃ¡vel em `assets/js/services-modal.js` (acessÃ­vel, com ARIA)
  - AtualizaÃ§Ã£o de `enqueue_assets()` para carregar CSS/JS externos (habilita cache do navegador e minificaÃ§Ã£o)
  - IntegraÃ§Ã£o do modal com fallback para alert() caso script nÃ£o esteja carregado
  - BenefÃ­cios: separaÃ§Ã£o de responsabilidades, cache do navegador, minificaÃ§Ã£o possÃ­vel, manutenibilidade melhorada

#### Fixed (Corrigido)
- **Groomers Add-on**: Corrigido erro fatal "Call to undefined function settings_errors()" no front-end ao usar shortcode [dps_base]
  - **Problema**: `settings_errors()` Ã© funÃ§Ã£o exclusiva do WordPress admin, nÃ£o disponÃ­vel no front-end
  - **Impacto**: Fatal error na seÃ§Ã£o Groomers do Painel de GestÃ£o DPS (shortcode)
  - **SoluÃ§Ã£o**: Implementada separaÃ§Ã£o de contexto:
    - MÃ©todo `handle_new_groomer_submission()` agora aceita parÃ¢metro `$use_frontend_messages`
    - Front-end (`render_groomers_section`): usa `DPS_Message_Helper::add_error/add_success()` e `display_messages()`
    - Admin (`render_groomers_page`): usa `add_settings_error()` e `settings_errors()` com guard `function_exists()`
  - O shortcode [dps_base] agora funciona normalmente no front-end sem fatal errors
- Corrigido erro fatal "Call to undefined function" ao ativar add-ons de Communications e Loyalty:
  - **Communications**: funÃ§Ã£o `dps_comm_init()` era chamada antes de ser declarada (linha 214)
  - **Loyalty**: funÃ§Ã£o `dps_loyalty_init()` era chamada antes de ser declarada (linha 839)
  - **SoluÃ§Ã£o**: declarar funÃ§Ãµes primeiro, depois registrÃ¡-las no hook `plugins_loaded` (padrÃ£o seguido pelos demais add-ons)
  - Os add-ons agora inicializam via `add_action('plugins_loaded', 'dps_*_init')` em vez de chamada direta em escopo global

---

### [2025-11-17] v0.3.0 â€” Indique e Ganhe

#### Added (Adicionado)
- Criado mÃ³dulo "Indique e Ganhe" no add-on de fidelidade com cÃ³digos Ãºnicos, tabela `dps_referrals`, cadastro de indicaÃ§Ãµes e recompensas configurÃ¡veis por pontos ou crÃ©ditos para indicador e indicado.
- IncluÃ­da seÃ§Ã£o administrativa para ativar o programa, definir limites e tipos de bonificaÃ§Ã£o, alÃ©m de exibir cÃ³digo/link de convite e status de indicaÃ§Ãµes no Portal do Cliente.
- Adicionado hook `dps_finance_booking_paid` no fluxo financeiro e campo de cÃ³digo de indicaÃ§Ã£o no cadastro pÃºblico para registrar relaÃ§Ãµes entre clientes.

---

### [2025-11-17] v0.2.0 â€” Campanhas e fidelidade

#### Added (Adicionado)
- Criado add-on `desi-pet-shower-loyalty` com programa de pontos configurÃ¡vel e funÃ§Ãµes globais para crÃ©dito e resgate.
- Registrado CPT `dps_campaign` com metabox de elegibilidade e rotina administrativa para identificar clientes alvo.
- IncluÃ­da tela "Campanhas & Fidelidade" no menu principal do DPS com resumo de pontos por cliente e gatilho manual de campanhas.

---

### [2024-01-15] v0.1.0 â€” Primeira versÃ£o pÃºblica

#### Added (Adicionado)
- Estrutura inicial do plugin base com hooks `dps_base_nav_tabs_*` e `dps_settings_*`.
- Add-on Financeiro com sincronizaÃ§Ã£o da tabela `dps_transacoes`.
- Guia inicial de configuraÃ§Ã£o e checklist de seguranÃ§a do WordPress.

#### Security (SeguranÃ§a)
- Nonces aplicados em formulÃ¡rios de painel para evitar CSRF.
