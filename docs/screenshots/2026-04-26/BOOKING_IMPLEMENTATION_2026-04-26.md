# Booking - implementacao DPS Signature

## Contexto

- Objetivo: reescrever a pagina publica de agendamento para usar o renderer canonico de agendamentos, reduzir duplicacao do add-on Booking e alinhar a UI ao padrao DPS Signature.
- Ambiente: `https://desi.pet/agendamento/`, WordPress publicado.
- Sessao: validacao autenticada criada via WP-CLI com usuario administrador existente; nenhum agendamento real foi salvo durante o smoke visual/funcional.
- Referencia de design: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.

## Antes/Depois

- Antes: o add-on Booking mantinha uma segunda implementacao extensa do formulario, com logica duplicada de clientes, pets, servicos, recorrencia, horarios e confirmacao.
- Depois: o Booking passou a compor `DPS_Appointments_Section_Renderer`, preservando os shortcodes publicos `[dps_booking_form]` e `[dps_booking_v2]`, o fluxo `dps_action=save_appointment`, o nonce `dps_nonce_agendamentos` e os nomes de campos consumidos pelo JS/base.
- Antes: a confirmacao pos-save dependia de transient e o cache-control nao reconhecia todos os aliases publicos do agendamento.
- Depois: a confirmacao publica usa query assinada por nonce, o aviso financeiro e recalculado em tempo real sem cache/transient e o cache-control cobre `dps_booking_form` e `dps_booking_v2`.
- Arquivos de codigo alterados: `plugins/desi-pet-shower-booking/desi-pet-shower-booking-addon.php`, `plugins/desi-pet-shower-booking/assets/css/booking-addon.css`, `plugins/desi-pet-shower-base/includes/class-dps-appointments-section-renderer.php`, `plugins/desi-pet-shower-base/includes/class-dps-base-frontend.php`, `plugins/desi-pet-shower-base/includes/class-dps-cache-control.php`.

## Capturas

- `./booking-implementation-auth-375.png` - Agendamento publicado em 375px.
- `./booking-implementation-auth-600.png` - Agendamento publicado em 600px.
- `./booking-implementation-auth-840.png` - Agendamento publicado em 840px.
- `./booking-implementation-auth-1200.png` - Agendamento publicado em 1200px.
- `./booking-implementation-auth-1920.png` - Agendamento publicado em 1920px.

## Evidencia funcional

- `./booking-implementation-check.json`

Resumo:

- pagina publicada retornou HTTP 200 nos cinco breakpoints;
- wrapper `dps-booking-wrapper` presente e apenas um formulario de agendamento renderizado;
- CSS do Booking carregado nos cinco breakpoints com `?ver=1.4.1`;
- sem overflow horizontal em 375px, 600px, 840px, 1200px e 1920px;
- grupo de data/horario empilhado em uma coluna no breakpoint de 375px para evitar texto cortado no seletor de horario;
- raio principal da superficie validado como `0px`, conforme geometria DPS Signature;
- nonce do formulario e campo oculto `dps_booking_context` presentes;
- smoke funcional em 1200px selecionou cliente, atualizou pets, escolheu a data `2026-04-28` e carregou 22 opcoes de horario;
- amostras de texto foram removidas do JSON para nao persistir nomes de clientes.

## Observacoes

- O runtime publicado emitiu um aviso legado de `JQMIGRATE: jQuery.trim is deprecated` e ruidos externos de CORS vindos de ads/Mixpanel; eles nao bloquearam o fluxo testado e devem ser tratados fora do add-on Booking.
- A validacao nao criou agendamento de producao para evitar alterar dados reais.
