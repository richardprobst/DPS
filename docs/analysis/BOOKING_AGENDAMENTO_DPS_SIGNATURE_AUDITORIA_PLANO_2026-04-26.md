# Booking Add-on e pagina de Agendamento - Auditoria integral e plano DPS Signature

**Data:** 2026-04-26
**Escopo:** `plugins/desi-pet-shower-booking`, pagina publicada `https://desi.pet/agendamento/`, contratos de agendamento do plugin base e integracoes Services/Groomers/Finance.
**Trilha:** B - mudanca estrutural planejada.
**Padrao visual seguido:** `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md` como fonte de verdade do DPS Signature.

## 1. Resumo executivo

O Booking Add-on esta funcionalmente vivo no runtime publicado, mas nao esta completamente alinhado ao DPS Signature atual nem ao estado arquitetural mais recente do sistema.

O smoke autenticado no `desi.pet` confirmou que:

- a pagina real e `https://desi.pet/agendamento/`;
- o shortcode renderiza o formulario autenticado;
- `booking-addon.css` esta carregado;
- nonce do formulario existe;
- os breakpoints `375`, `600`, `840`, `1200` e `1920` nao apresentaram overflow horizontal no estado inicial;
- a selecao de cliente carrega pets via REST e a selecao de data carrega horarios via `admin-ajax.php`.

Ao mesmo tempo, a auditoria encontrou problemas que justificam reescrita integral, nao remendo:

- uso de transients no fluxo de confirmacao, proibido pelas regras atuais do projeto;
- strings e icones com mojibake visivel no runtime publicado;
- UI herdada com azul/verde/laranja/roxo, cantos arredondados, pills e sombras, fora do DPS Signature canonico;
- duplicacao relevante do renderer de agendamentos do plugin base;
- divergencia de metadados (`appointment_pet` versus `appointment_pet_id`);
- descoberta de URL/canonical page fragil, com login do visitante redirecionando para a home;
- documentacao de arquitetura parcialmente desatualizada;
- formulario operacional excessivamente longo, com 108 campos/controles renderizados no estado inicial autenticado.

Recomendacao: reescrever o Booking como uma superficie operacional propria de agendamento, preservando os contratos externos, mas substituindo a renderizacao duplicada por uma camada canonica compartilhada com o base.

## 2. Fontes auditadas

### Codigo do Booking

- `plugins/desi-pet-shower-booking/desi-pet-shower-booking-addon.php`
- `plugins/desi-pet-shower-booking/assets/css/booking-addon.css`

### Contratos compartilhados

- `plugins/desi-pet-shower-base/includes/class-dps-appointments-section-renderer.php`
- `plugins/desi-pet-shower-base/includes/class-dps-appointment-handler.php`
- `plugins/desi-pet-shower-base/includes/class-dps-base-frontend.php`
- `plugins/desi-pet-shower-base/includes/class-dps-cache-control.php`
- `plugins/desi-pet-shower-services/dps_service/desi-pet-shower-services-addon.php`

### Documentacao

- `ANALYSIS.md`
- `CHANGELOG.md`
- `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md`
- `docs/visual/VISUAL_STYLE_GUIDE.md`
- `docs/refactoring/AGENT_ENGINEERING_PLAYBOOK.md`

### Evidencia visual e funcional publicada

- `docs/screenshots/2026-04-26/booking-audit-auth-375.png`
- `docs/screenshots/2026-04-26/booking-audit-auth-600.png`
- `docs/screenshots/2026-04-26/booking-audit-auth-840.png`
- `docs/screenshots/2026-04-26/booking-audit-auth-1200.png`
- `docs/screenshots/2026-04-26/booking-audit-auth-1920.png`
- `docs/screenshots/2026-04-26/booking-audit-auth-check.json`
- `docs/screenshots/2026-04-26/booking-audit-interaction-1200.png`
- `docs/screenshots/2026-04-26/booking-audit-interaction-check.json`

## 3. Contratos externos que devem ser preservados

Nao mudar sem aprovacao explicita:

- shortcodes:
  - `[dps_booking_form]`
  - `[dps_booking_v2]` como alias de compatibilidade;
- option:
  - `dps_booking_page_id`;
- pagina publicada:
  - `https://desi.pet/agendamento/`;
- formulario:
  - `dps_action=save_appointment`;
  - nonce `dps_nonce_agendamentos`;
  - `dps_redirect_url`;
  - `appointment_id`;
  - `appointment_type`;
  - `appointment_client_id`;
  - `appointment_pet_ids[]`;
  - `appointment_date`;
  - `appointment_time`;
  - `appointment_taxidog`;
  - `appointment_taxidog_price`;
  - `appointment_tosa`;
  - `appointment_tosa_price`;
  - `appointment_tosa_occurrence`;
  - `past_payment_status`;
  - `past_payment_value`;
  - `appointment_notes`;
  - `appointment_total`;
  - `subscription_base_value`;
  - `subscription_total_value`;
  - `subscription_extra_value`;
- hooks consumidos:
  - `dps_base_after_save_appointment`;
  - `dps_base_appointment_fields`;
  - `dps_base_appointment_assignment_fields`;
- capabilities:
  - `manage_options`;
  - `dps_manage_appointments`;
  - manter leitura auxiliar de clientes/pets apenas quando necessario, sem permitir salvar agendamento sem `dps_manage_appointments`;
- integracoes:
  - Services Add-on;
  - Groomers Add-on;
  - Finance Add-on;
  - Communications/WhatsApp quando disponivel.

## 4. Achados tecnicos e de produto

### A1 - Uso proibido de transients no fluxo de confirmacao

**Severidade:** Alta
**Evidencia:** `plugins/desi-pet-shower-booking/desi-pet-shower-booking-addon.php:290-295` usa `get_transient()` e `delete_transient()`; `plugins/desi-pet-shower-booking/desi-pet-shower-booking-addon.php:914-920` usa `set_transient()`.

Isto viola a regra atual do reposititorio de nao implementar cache nem armazenamento temporario por transients. Mesmo que o objetivo seja uma mensagem efemera, o mecanismo usado e transient.

**Correcao planejada:** substituir por redirecionamento assinado sem storage temporario:

- depois do salvamento, redirecionar para `?dps_booking_confirmed={appointment_id}&dps_booking_nonce={nonce}`;
- gerar nonce vinculado a `appointment_id`, usuario e acao;
- ao renderizar confirmacao, validar nonce + capability + `can_edit_appointment()`;
- remover o query arg apos acao do usuario, sem persistir estado.

### A2 - Mojibake visivel no runtime publicado

**Severidade:** Alta
**Evidencia:** capturas autenticadas mostram textos como `VocÃƒÂª`, `horÃƒÂ¡rio`, `Salvar` e icones quebrados; o PHP tambem exibe strings mojibake em pontos como `plugins/desi-pet-shower-booking/desi-pet-shower-booking-addon.php:270`, `:483`, `:676`, `:711`, `:827`.

O problema prejudica confianca operacional e torna a tela incompatÃ­vel com o acabamento premium esperado.

**Correcao planejada:**

- normalizar os arquivos do Booking para UTF-8 sem BOM;
- substituir emojis decorativos por icones controlados ou texto funcional;
- revisar todas as strings visiveis do Booking e dos campos injetados por add-ons na pagina;
- validar que respostas AJAX/JSON continuam sem BOM.

### A3 - Duplicacao estrutural do renderer de agendamentos

**Severidade:** Alta
**Evidencia:** o Booking replica manualmente grande parte do formulario em `plugins/desi-pet-shower-booking/desi-pet-shower-booking-addon.php:345-842`, enquanto o base tem `DPS_Appointments_Section_Renderer::render()` e `prepare_data()` em `plugins/desi-pet-shower-base/includes/class-dps-appointments-section-renderer.php:31-60` e renderiza campos equivalentes em `:293-760`.

Esta duplicacao ja causou drift. Exemplo: o renderer do base inclui alerta de pendencias financeiras e consentimento de tosa nos options do cliente (`class-dps-appointments-section-renderer.php:366-428`), mas o Booking tem um select mais simples em `desi-pet-shower-booking-addon.php:550-558`.

**Correcao planejada:**

- extrair uma camada compartilhada de formulario de agendamento ou expor publicamente um renderer parametrizavel no base;
- Booking vira shell/contexto dedicado, nao copia do formulario;
- Services/Groomers/Finance continuam entrando pelos hooks existentes;
- qualquer variacao visual do Booking deve ser por parametros/contexto, nao por fork de HTML.

### A4 - Divergencia de meta key de pet na edicao

**Severidade:** Media/Alta
**Evidencia:** Booking carrega `appointment_pet` em `plugins/desi-pet-shower-booking/desi-pet-shower-booking-addon.php:398`, mas o contrato documentado e o handler usam `appointment_pet_id` (`ANALYSIS.md:3239-3260`, `class-dps-appointments-section-renderer.php:84`, `class-dps-appointment-handler.php` salva `appointment_pet_ids` e compatibilidade de pet individual).

Isto pode quebrar pre-selecao de pet em edicoes quando `appointment_pet_ids` nao estiver presente ou em dados legados.

**Correcao planejada:**

- padronizar leitura em `appointment_pet_id`;
- manter fallback read-only para `appointment_pet` apenas se houver dado legado real;
- escrever apenas o contrato canonico.

### A5 - URL canonica fragil e login redirecionando para home

**Severidade:** Media
**Evidencia:** `get_booking_page_url()` depende de `dps_booking_page_id` e cai para `home_url('/')` em `plugins/desi-pet-shower-booking/desi-pet-shower-booking-addon.php:234-242`. No runtime publicado de visitante, o botao de login gerou `redirect_to=https%3A%2F%2Fdesi.pet%2F`, embora a pagina real seja `/agendamento/`.

**Correcao planejada:**

- no render do shortcode, usar a URL atual validada como fallback prioritario;
- reconciliar `dps_booking_page_id` com a pagina publicada que contem o shortcode no Elementor;
- adicionar descoberta alias-aware para `[dps_booking_form]` e `[dps_booking_v2]` no post content e metadados de page builder;
- preservar a pagina `/agendamento/` como canonical operacional.

### A6 - Cache-control nao registra shortcodes de Booking

**Severidade:** Media
**Evidencia:** `plugins/desi-pet-shower-base/includes/class-dps-cache-control.php:31-58` lista varios shortcodes DPS, mas nao inclui `dps_booking_form` nem `dps_booking_v2`.

O runtime atual ainda envia headers no-cache porque o shortcode chama `DPS_Cache_Control::force_no_cache()`, mas a deteccao preventiva por shortcode esta incompleta.

**Correcao planejada:**

- registrar `dps_booking_form` e `dps_booking_v2` em `DPS_Cache_Control`;
- manter `force_no_cache()` no shortcode como defesa adicional;
- validar headers em visitante e autenticado.

### A7 - Visual atual nao atende integralmente ao DPS Signature

**Severidade:** Media/Alta
**Evidencia:** `plugins/desi-pet-shower-booking/assets/css/booking-addon.css` usa `--dps-color-primary`, `--dps-color-success`, `--dps-color-warning`, `--dps-color-error`, `--dps-shape-medium`, `--dps-shape-full`, `--dps-shape-button`, sombras e `translateY()` em linhas como `:77-78`, `:100-113`, `:162-163`, `:194-196`, `:317`, `:361-378`. As capturas mostram azul dominante, verde vivo, laranja, roxo de atribuicao, cantos arredondados e chips/pills.

O DPS Signature atual pede base `paper/bone`, ancora `petrol`, bordas retas, raio `0px` por padrao, controles pequenos em `2px-4px`, estado semantico contido e sem estetica de componente generico.

**Correcao planejada:**

- substituir a camada visual do Booking por tokens conceituais locais:
  - `--booking-ink`;
  - `--booking-petrol`;
  - `--booking-paper`;
  - `--booking-bone`;
  - `--booking-line`;
  - `--booking-sky`;
  - `--booking-action`;
  - `--booking-warning`;
  - `--booking-danger`;
- eliminar pills como default;
- remover sombras pesadas e bounce;
- alinhar Services/Groomers injetados ao mesmo escopo visual.

### A8 - UX operacional excessivamente longa

**Severidade:** Media
**Evidencia:** smoke autenticado detectou 108 campos/controles no estado inicial. A captura mobile `booking-audit-auth-375.png` mostra uma pagina muito longa, com dezenas de servicos abertos antes da revisao/salvamento.

Isto aumenta custo cognitivo, especialmente no atendimento de balcao ou telefone.

**Correcao planejada:**

- transformar em fluxo operacional por etapas ou secoes progressivas:
  1. Cliente e pet;
  2. Servicos;
  3. Data, hora e profissional;
  4. Extras e pagamento;
  5. Revisao e salvar;
- manter desktop com duas colunas apenas quando houver espaco real;
- summary lateral/sticky somente desktop; em mobile, resumo vira etapa de revisao;
- criar busca/filtro de servicos e bandeja de selecionados;
- agrupar categorias de servicos sem renderizar tudo como lista infinita.

### A9 - Documentacao de arquitetura esta desatualizada

**Severidade:** Media
**Evidencia:** `ANALYSIS.md:991` cita Booking v1.3.0, mas o plugin esta em 1.3.1; `ANALYSIS.md:1052-1054` cita assets antigos `dps-booking-form.css/js` que nao existem no add-on atual; `ANALYSIS.md:997` cita acao rapida de WhatsApp na confirmacao, mas o codigo atual renderiza novo agendamento, ver cliente e ver agenda.

**Correcao planejada:**

- atualizar `ANALYSIS.md` com estado real;
- manter este plano como referencia de reescrita;
- atualizar `CHANGELOG.md` apenas quando houver implementacao user-facing.

### A10 - Breakpoints do CSS nao seguem explicitamente os oficiais

**Severidade:** Baixa/Media
**Evidencia:** `booking-addon.css:415-529` usa `1024`, `768`, `640` e `480`, enquanto o sistema exige validacao e desenho para `375`, `600`, `840`, `1200`, `1920`.

**Correcao planejada:**

- redesenhar a responsividade pelos breakpoints oficiais;
- manter checks de overflow e densidade por breakpoint;
- registrar screenshots completos em cada entrega visual.

## 5. Estado funcional observado

### Passou no smoke autenticado

- pagina autenticada renderizou formulario;
- assets do Booking carregaram;
- nonce `dps_nonce_agendamentos` presente;
- select de clientes populado;
- seletor de data carregou horarios para `2026-04-28`;
- selecao de cliente carregou pets via REST;
- nenhum overflow horizontal detectado nos breakpoints oficiais;
- usuario temporario criado via WP-CLI foi removido ao final.

### Nao executado nesta auditoria

Para evitar mutacao de dados de producao, nao foi salvo agendamento real. A implementacao deve usar fixture temporaria via WP-CLI:

- cliente temporario;
- pet temporario;
- agendamento simples;
- agendamento de assinatura;
- agendamento passado com pagamento pendente;
- edicao;
- duplicacao;
- rollback/exclusao dos dados de fixture.

## 6. Plano de reescrita integral

### Fase 0 - Preparacao e protecao

1. Criar backup remoto dos arquivos alterados antes de publicar:
   - `wp-content/plugins/desi-pet-shower-booking/desi-pet-shower-booking-addon.php`;
   - `wp-content/plugins/desi-pet-shower-booking/assets/css/booking-addon.css`;
   - arquivos do base se forem alterados.
2. Criar fixtures temporarias via WP-CLI para teste.
3. Confirmar pagina canonical `/agendamento/`, ID `1267`, shortcode ativo e option `dps_booking_page_id`.
4. Validar worktree e isolar alteracoes de Agenda preexistentes.

### Fase 1 - Arquitetura compartilhada do formulario

1. Remover o fork manual do formulario dentro do Booking.
2. Extrair ou expor uma API canonica no base:
   - `prepare_data( context )`;
   - `render_form( data, context )`;
   - `render_shell( context )`.
3. Booking deve possuir apenas:
   - bootstrap;
   - shortcodes;
   - controle de acesso;
   - canonical URL;
   - shell visual da pagina dedicada;
   - confirmacao pos-salvamento sem transient.
4. Base continua dono do contrato de salvamento.
5. Add-ons continuam injetando campos pelos hooks existentes.

### Fase 2 - Correcoes de contrato e seguranca

1. Remover transients do Booking.
2. Implementar confirmacao por query args assinados.
3. Corrigir `appointment_pet` para `appointment_pet_id` com fallback legado somente leitura.
4. Corrigir descoberta de pagina e login redirect.
5. Registrar `dps_booking_form` e `dps_booking_v2` em `DPS_Cache_Control`.
6. Revisar GET de edicao/duplicacao:
   - manter leitura segura;
   - preferir links com nonce para acoes vindas de UI interna;
   - capability continua obrigatoria.
7. Garantir que nenhuma nova camada de cache seja criada.

### Fase 3 - Redesign DPS Signature

1. Criar shell operacional:
   - header compacto;
   - contexto da acao;
   - indicador de etapa;
   - CTA primario sempre claro.
2. Redesenhar fluxo:
   - etapa 1: Cliente e pet;
   - etapa 2: Servicos;
   - etapa 3: Data, horario e profissional;
   - etapa 4: Extras, TaxiDog, tosa, pagamento;
   - etapa 5: Revisao e salvar.
3. Desktop:
   - coluna principal para entrada;
   - coluna lateral para resumo e alertas;
   - resumo sticky apenas acima de `840px`.
4. Mobile:
   - uma etapa por vez;
   - botoes fixos por etapa apenas se nao cobrirem conteudo;
   - area de toque minima;
   - sem tabela/lista gigante aberta.
5. Servicos:
   - busca;
   - categorias recolhiveis;
   - selecionados em bandeja/resumo;
   - precos por porte como coluna controlada, nao chips coloridos competindo.
6. Visual:
   - base `paper/bone`;
   - identidade `petrol`;
   - estados `action/warning/danger`;
   - bordas `line`;
   - raio padrao `0px`, controles pequenos ate `4px`;
   - remover emojis decorativos quebraveis;
   - substituir verde/laranja/roxo legados por semantica DPS.

### Fase 4 - Integracoes

1. Services Add-on:
   - expor servicos como dados renderizaveis no novo picker;
   - preservar nomes de campos e calculo de totais;
   - impedir que estilos proprios quebrem o Booking Signature.
2. Groomers:
   - manter hook de atribuicao;
   - trocar painel roxo por estado neutro `bone/line`.
3. Finance:
   - trazer alerta de pendencias do renderer base;
   - manter informacao operacional sem bloquear salvamento quando nao aplicavel.
4. Communications:
   - restaurar/planejar acao WhatsApp pos-confirmacao se a API/helper estiver disponivel.
5. Subscription:
   - revisar frequencia, proximas datas, tosa opcional e totais.

### Fase 5 - Validacao

1. `php -l` em todos os PHP alterados.
2. `git diff --check`.
3. `composer run ci` em `tools/php` se dependencias estiverem disponiveis.
4. WP-CLI fixtures:
   - criar usuario temporario;
   - criar cliente/pet temporarios;
   - criar/editar/duplicar/excluir agendamentos;
   - remover fixtures.
5. Browser publicado:
   - visitante: login state e redirect correto para `/agendamento/`;
   - autenticado: formulario;
   - salvar simples;
   - salvar assinatura;
   - salvar passado;
   - editar;
   - duplicar;
   - confirmacao;
   - sem console errors relevantes.
6. Breakpoints obrigatorios:
   - `375`;
   - `600`;
   - `840`;
   - `1200`;
   - `1920`.
7. Evidencias:
   - screenshots completos;
   - JSON de smoke;
   - registro em `docs/screenshots/YYYY-MM-DD/`.

### Fase 6 - Deploy e fechamento

1. Commit em portugues, imperativo.
2. Push para GitHub.
3. Upload por SSH/SFTP somente dos arquivos alterados.
4. Verificar hash local/remoto dos arquivos publicados.
5. Revalidar runtime publicado.
6. Remover usuario/sessao temporaria.
7. Registrar comandos e status no fechamento.

## 7. Gates ASK BEFORE

Mesmo com permissao ampla para reescrita, pedir validacao humana antes de:

- alterar schema/tabelas compartilhadas;
- remover shortcodes ou renomear campos externos;
- alterar assinatura de hooks existentes;
- trocar fluxo critico de autenticacao;
- adicionar dependencia externa nova;
- remover compatibilidade com `[dps_booking_v2]`.

## 8. Trade-off principal

Alternativa considerada: corrigir CSS e strings no Booking atual.
Motivo para nao recomendar: manteria o fork do formulario, o transient proibido, o drift com o base e a lista operacional longa. A opcao mais limpa e reescrever o Booking como shell dedicado sobre um formulario canonico compartilhado.
