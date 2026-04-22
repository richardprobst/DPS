# Space Groomers - Fase 4

## Objetivo da fase
Conectar o add-on `desi-pet-shower-game` ao ecossistema DPS/desi.pet sem criar acoplamentos frageis, priorizando integracoes reais com portal do cliente, contexto de cliente/pet/agendamento e loyalty opcional.

## Referencia visual aplicada
As superficies novas do portal seguiram explicitamente o padrao DPS Signature descrito em:
- `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md`
- `docs/visual/VISUAL_STYLE_GUIDE.md`

## Integracoes implementadas

### 1. Aba propria do jogo no portal
Implementada via hooks oficiais do portal:
- `dps_portal_tabs`
- `dps_portal_custom_tab_panels`

Resultado:
- o portal ganhou uma aba dedicada `Space Groomers`;
- a aba mostra perfil do jogo, recorde, streak, missao, badges e CTAs para voltar ao jogo ou abrir a aba de agendamentos/historico.

### 2. Perfil contextual de cliente/pet
Implementado no novo servico `DPS_Game_Ecosystem_Service`.

Dados reaproveitados:
- nome do cliente (`dps_cliente`)
- pet em destaque do cliente
- pet do proximo agendamento, quando houver
- metadados de pet (`pet_species`, `pet_breed`, `pet_size`)
- ultimo atendimento concluido do pet, quando disponivel

Comportamento:
- prioriza o pet vinculado ao proximo agendamento;
- se nao houver agendamento, usa o primeiro pet do cliente;
- se nao houver pet, faz fallback para contexto generico do cliente.

### 3. Evento/status ligado ao ecossistema
A aba do jogo e o card da aba Inicio agora mostram um bloco contextual com base em dados reais do portal:
- proximo atendimento do pet em destaque;
- missao do dia sincronizada no portal;
- historico do pet quando nao ha proximo agendamento;
- fallback leve quando o cliente ainda nao tem pet/agendamento vinculado.

### 4. Recompensas conectadas ao loyalty
Quando o Loyalty Add-on esta ativo, o jogo agora exibe:
- saldo total de pontos;
- tier atual;
- total de pontos ganhos especificamente pelos eventos do jogo;
- contagem de eventos de recompensa do jogo;
- ultima recompensa do jogo registrada no log de loyalty.

Quando o Loyalty Add-on nao esta ativo:
- o jogo continua funcional;
- o portal mostra apenas badges locais, streak e recorde do cliente.

## Dependencias opcionais
- **Client Portal Add-on**
  - necessario apenas para card/aba no portal;
  - o shortcode do jogo continua funcionando sem ele.
- **Loyalty Add-on**
  - opcional;
  - habilita o bloco de recompensas conectadas e o reaproveitamento de pontos/tier.

## Verificacoes de compatibilidade
- uso exclusivo de hooks ja existentes do portal;
- sem tabela nova;
- sem cache novo;
- sem duplicar fonte canonica de progresso em `user_meta`;
- sem tornar loyalty obrigatorio;
- compatibilidade com status legados de agendamento (`finalizado_pago`, `finalizado e pago`, `em andamento`).

## Arquivos principais desta fase
- `plugins/desi-pet-shower-game/desi-pet-shower-game.php`
- `plugins/desi-pet-shower-game/includes/class-dps-game-addon.php`
- `plugins/desi-pet-shower-game/includes/class-dps-game-ecosystem-service.php`
- `plugins/desi-pet-shower-game/assets/css/space-groomers.css`
- `plugins/desi-pet-shower-game/PHASE_4_PLAN.md`

## Integracoes deliberadamente adiadas
- ranking global simples:
  - possivel, mas nao entrou nesta fase para evitar consulta global frequente no portal sem cache.
- missoes alteradas dinamicamente por pet/agendamento:
  - adiadas para nao quebrar o contrato atual entre frontend e servidor.

## Proximos passos possiveis
1. Expor ranking global opcional com pagina propria ou endpoint dedicado, se houver necessidade real.
2. Fazer o frontend do jogo consumir `portalProfile` para refletir o pet/evento tambem no overlay inicial do canvas.
3. Adicionar pontos de extensao para eventos sazonais do jogo ligados a campanhas do loyalty.
4. Incluir analytics leves de uso do jogo no portal, sem criar economia paralela.

## Validacoes desta entrega
- `git diff --check -- plugins/desi-pet-shower-game`
  - passou, com avisos apenas de normalizacao LF/CRLF do Git no Windows.
- `php -l ...`
  - nao executado: `php`/`php.exe` nao esta disponivel no ambiente desta sessao.

## Registro visual
Artefatos desta fase salvos em:
- `docs/screenshots/2026-03-08/space-groomers-phase4-preview.html`
- `docs/screenshots/2026-03-08/space-groomers-phase4-preview-desktop.png`
- `docs/screenshots/2026-03-08/space-groomers-phase4-preview-mobile.png`
