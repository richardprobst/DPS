# Auditoria integral de UI do site publicado

Data: 2026-04-22
Escopo: superfícies públicas do site publicado `https://desi.pet/`
Fonte de verdade visual: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`

## Resumo executivo

A UI publicada ainda nao esta consistente com o padrao DPS Signature. O principal problema nao e apenas visual: existem superficies publicas com rotas quebradas, scripts com erro em runtime, acoes que nao respondem e mistura ativa de linguagem visual antiga com componentes novos. O portal do cliente e hoje a superficie mais distante do padrao.

Nos breakpoints `375`, `600`, `840`, `1200` e `1920`, a maior parte das paginas publicas testadas nao apresentou overflow horizontal continuo, mas isso nao significa conformidade. O problema predominante no mobile e de estrutura, hierarquia, duplicacao de acoes, geometrias herdadas do padrao antigo e estados quebrados.

## Evidencias geradas

- JSON consolidado: `docs/screenshots/2026-04-22/ui-audit-site/site-ui-audit.json`
- Capturas: `docs/screenshots/2026-04-22/ui-audit-site/`

## Achados priorizados

### Critico

1. O menu publico aponta para paginas quebradas.
   - `https://desi.pet/contato-e-localizacao/` retorna `Pagina nao encontrada`.
   - `https://desi.pet/perguntas-frequentes/` retorna `Pagina nao encontrada`.
   - Impacto: navegacao publica quebrada e perda direta de confianca.

2. O Portal do Cliente tem erro de JavaScript em runtime e acao quebrada.
   - O botao `CRIAR OU REDEFINIR SENHA` nao abriu modal nem navegou durante o teste.
   - O console do site publicado registrou:
     - `ReferenceError: handleReviewForm is not defined`
     - `Failed to execute 'observe' on 'MutationObserver': parameter 1 is not of type 'Node'`
   - Causa localizada em [client-portal.js](C:/Users/casaprobst/DPS/plugins/desi-pet-shower-client-portal/assets/js/client-portal.js:50), [client-portal.js](C:/Users/casaprobst/DPS/plugins/desi-pet-shower-client-portal/assets/js/client-portal.js:1914), [client-portal.js](C:/Users/casaprobst/DPS/plugins/desi-pet-shower-client-portal/assets/js/client-portal.js:1926) e [client-portal.js](C:/Users/casaprobst/DPS/plugins/desi-pet-shower-client-portal/assets/js/client-portal.js:2737).
   - Sintese: `init()` chama `handleReviewForm()` muito antes da funcao ser definida, e o bloco do `MutationObserver` esta em posicao inconsistente para o fluxo atual.

3. A pagina de cadastro carrega Google Maps Places duas vezes.
   - O console do site publicado acusou inclusao duplicada da API do Google Maps.
   - Causa localizada em [dps-signature-forms.js](C:/Users/casaprobst/DPS/plugins/desi-pet-shower-base/assets/js/dps-signature-forms.js:187), [dps-signature-forms.js](C:/Users/casaprobst/DPS/plugins/desi-pet-shower-base/assets/js/dps-signature-forms.js:194), [registration-v2.js](C:/Users/casaprobst/DPS/plugins/desi-pet-shower-frontend/assets/js/registration-v2.js:275) e [registration-v2.js](C:/Users/casaprobst/DPS/plugins/desi-pet-shower-frontend/assets/js/registration-v2.js:282).
   - Impacto: erro em runtime, custo desnecessario e comportamento imprevisivel do autocomplete.

### Alta

4. A base visual dita como "DPS Signature" ainda carrega tokens de geometria do padrao antigo.
   - O arquivo [dps-design-tokens.css](C:/Users/casaprobst/DPS/plugins/desi-pet-shower-base/assets/css/dps-design-tokens.css:255) define raios grandes e pill shapes, incluindo [dps-design-tokens.css](C:/Users/casaprobst/DPS/plugins/desi-pet-shower-base/assets/css/dps-design-tokens.css:257), [dps-design-tokens.css](C:/Users/casaprobst/DPS/plugins/desi-pet-shower-base/assets/css/dps-design-tokens.css:260), [dps-design-tokens.css](C:/Users/casaprobst/DPS/plugins/desi-pet-shower-base/assets/css/dps-design-tokens.css:263) e [dps-design-tokens.css](C:/Users/casaprobst/DPS/plugins/desi-pet-shower-base/assets/css/dps-design-tokens.css:266).
   - Isso contradiz diretamente o padrao atual, que pede geometria reta por padrao e evita pills.

5. O CSS base de formularios ainda materializa geometrias e composicoes que nao sao DPS Signature.
   - Em [dps-signature-forms.css](C:/Users/casaprobst/DPS/plugins/desi-pet-shower-base/assets/css/dps-signature-forms.css:53), [dps-signature-forms.css](C:/Users/casaprobst/DPS/plugins/desi-pet-shower-base/assets/css/dps-signature-forms.css:120) e [dps-signature-forms.css](C:/Users/casaprobst/DPS/plugins/desi-pet-shower-base/assets/css/dps-signature-forms.css:588), o sistema ainda aplica bordas totalmente arredondadas ou raios grandes em tags, paineis e botoes.
   - Impacto visual: o portal e o banho e tosa continuam com aspecto M3/Material misturado ao DPS Signature.

6. Ainda existe resquicio funcional do fluxo antigo na Agenda.
   - O runtime continua registrando e dependendo do modal legado de servicos em [desi-pet-shower-agenda-addon.php](C:/Users/casaprobst/DPS/plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php:1962), [desi-pet-shower-agenda-addon.php](C:/Users/casaprobst/DPS/plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php:1964) e [desi-pet-shower-agenda-addon.php](C:/Users/casaprobst/DPS/plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php:1984).
   - O JS da Agenda ainda usa `window.DPSServicesModal` e `agenda_tab` em varios pontos, por exemplo em [agenda-addon.js](C:/Users/casaprobst/DPS/plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js:562), [agenda-addon.js](C:/Users/casaprobst/DPS/plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js:583) e [agenda-addon.js](C:/Users/casaprobst/DPS/plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js:704).
   - Impacto: aumenta chance de regressao, duplicacao de fluxo e reintroducao de UI antiga.
   - Status final da Agenda: corrigido, publicado e revalidado no site ao vivo. O plugin ativo nao contem `services-modal.js`, nao expõe `window.DPSServicesModal`, nao usa `agenda_tab` no frontend operacional e abriu os fluxos de servicos, operacao, historico e reagendamento no shell unificado.

### Media

7. Ha navegacao duplicada e item visualmente morto no topo do site.
   - O item `DESI PET SHOWER` aparece como link clicavel extra na home desktop e mobile, apontando para `https://desi.pet/#`.
   - Impacto: ruina de hierarquia, duplicacao de funcao e click sem valor.

8. A home e o banho e tosa repetem CTA com a mesma funcao.
   - `Agendar pelo WhatsApp` aparece duas vezes na mesma pagina.
   - Impacto: ruido visual e sensacao de interface mal consolidada.

9. O cabecalho mobile ainda expoe `ADMIN` em contexto publico.
   - Mesmo quando nao gera erro, isso e um vazamento de semantica administrativa para uma interface publica.

## Diagnostico de UX

- A pior superficie hoje e o Portal do Cliente. Ele mistura cartoes e botoes arredondados, campos com linguagem antiga e scripts quebrados.
- A pagina de cadastro esta mais proxima do DPS Signature, mas o runtime ainda esta poluido por assets e scripts de mais de um modulo.
- A home esta visualmente mais controlada, mas o topo ainda nao foi consolidado e repete navegacao.
- O problema mobile e mais de arquitetura do que de overflow continuo: repeticao, cabecalho pouco limpo, hierarquia instavel e componentes herdados do padrao antigo.

## Ordem correta de correcao

1. Corrigir rotas publicas quebradas do menu.
2. Corrigir os erros de runtime do Portal do Cliente.
3. Eliminar a carga duplicada do Google Maps na pagina de cadastro.
4. Reescrever os tokens e componentes base para obedecer rigorosamente ao DPS Signature.
5. Remover duplicacoes de navegacao e CTA no topo e nas paginas publicas.
6. Remover o resquicio funcional legado ainda ativo na Agenda.

## Observacao de escopo

Esta auditoria cobre a UI publicada das superficies publicas acessiveis sem login especial nesta rodada. A area autenticada da Agenda pode receber uma rodada separada de auditoria completa se for necessario validar novamente modais, fluxo operacional e comportamento responsivo no ambiente logado.
