# Screenshots 2026-04-21 - Agenda (operacao, modal do pet e reagendamento)

## Contexto
- Objetivo da mudanca: corrigir inconsistencias visuais e funcionais da Agenda em runtime real, com foco na aba Operacao, no modal de perfil do pet, no modal de reagendamento e no fluxo de checklist/check-in/check-out.
- Ambiente: WordPress ativo em `https://desi.pet/agenda-de-atendimentos/`, com validacao autenticada via WP-CLI + login administrativo temporario.
- Referencia visual utilizada: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`, tratados como fonte de verdade do padrao DPS Signature.

## Antes/Depois
- Antes: a Agenda apresentava desalinhamentos entre textos e acoes, margens insuficientes em partes da tabela, overflow em alguns blocos operacionais, modal de perfil do pet quebrando o layout e modal de reagendamento com header inconsistente.
- Depois: a area operacional usa um dialog system unico, com espacamento e alinhamento padronizados, CTA compactos na tabela, checklist/check-in/check-out abertos fora da grade principal e modais coerentes com o shell DPS Signature.
- Ajuste funcional complementar: o checklist operacional passa a abrir somente quando o status muda para `finalizado` ou quando o operador aciona os botoes dedicados da linha.
- Persistencia complementar: check-in e check-out agora aceitam edicao, mantem os dados gravados no atendimento e registram historico de alteracao.

## Arquivos de codigo alterados
- `plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php`
- `plugins/desi-pet-shower-agenda/includes/class-dps-agenda-checkin-service.php`
- `plugins/desi-pet-shower-agenda/includes/class-dps-agenda-checklist-service.php`
- `plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php`
- `plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`
- `plugins/desi-pet-shower-agenda/assets/css/checklist-checkin.css`
- `plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js`
- `plugins/desi-pet-shower-agenda/assets/js/checklist-checkin.js`
- `plugins/desi-pet-shower-agenda/assets/js/pet-profile-modal.js`

## Breakpoints validados
- `375`
- `600`
- `840`
- `1200`
- `1920`

## Capturas - tela operacional ao vivo
- `./agenda-operacao-375-live.png`
- `./agenda-operacao-600-live.png`
- `./agenda-operacao-840-live.png`
- `./agenda-operacao-1200-live.png`
- `./agenda-operacao-1920-live.png`

## Capturas - alinhamento das tabelas da agenda
- `./agenda-table-tab1-1528.png`
- `./agenda-table-tab2-1528.png`
- `./agenda-table-tab3-1528.png`
- `./tab1-after-fix.png`
- `./tab2-after-fix.png`
- `./tab3-after-fix.png`
- `./tab1-column-contract.png`
- `./tab3-column-contract.png`

## Analise por aba - rodada de alinhamento das tabelas
- `Confirmacoes e proximos passos`: os blocos de horario, pet, servicos, confirmacao e acoes passaram a compartilhar o mesmo alinhamento superior, eliminando o efeito de campos "caidos" no miolo da linha.
- `Checklist, check-in e cobranca`: a linha agora respeita uma grade unica entre status, pagamento, painel operacional e CTA final; o resumo operacional ganhou distribuicao previsivel e os botoes compactos deixaram de quebrar o eixo da tabela.
- `Logistica, notas e TaxiDog`: o resumo operacional textual recebeu estilo proprio, o bloco de observacoes deixou de competir com a coluna operacional e a coluna de acoes ficou ancorada no topo como nas demais abas.

## Ajuste complementar - segunda rodada de alinhamento
- Foi removido o `margin-top` herdado nos selects de `Confirmacao` e `Status do servico`, que ainda deixava esses campos cerca de `7px` abaixo dos demais componentes da linha no navegador real.
- O CTA de `Pet e tutor` foi reancorado na largura util da celula, com alinhamento horizontal a esquerda e sem o recuo centralizado que ainda aparecia na aba de confirmacoes.
- Validacao autenticada em runtime apos o reenvio do CSS confirmou que os elementos principais das tres abas passaram a iniciar na mesma linha superior da tabela.

## Ajuste complementar - contrato das colunas
- `Pet e tutor`: a celula voltou a exibir o tutor abaixo do CTA do pet, corrigindo a quebra entre o nome do cabecalho e o conteudo efetivamente renderizado.
- `Logistica, notas e TaxiDog`: a quinta coluna da terceira aba deixou de repetir o resumo operacional e passou a mostrar contexto logistico real do atendimento, com endereco e links de mapa/rota quando existirem.
- `TaxiDog`: a terceira aba passou a manter a coluna de TaxiDog focada apenas no status do deslocamento, sem duplicar a navegacao de mapa dentro da mesma celula.
- Validacao autenticada no HTML renderizado confirmou os cabecalhos `Pet e tutor` nas tres tabelas e `Logistica` na quinta coluna da aba de detalhes.

## Capturas - dialogs e estados funcionais
- `./agenda-operacao-desktop.png`
- `./agenda-operacao-mobile-375.png`
- `./agenda-operacao-modal-desktop.png`
- `./agenda-operacao-modal-desktop-checkin.png`
- `./agenda-operacao-modal-mobile-375.png`
- `./agenda-pet-modal-desktop.png`
- `./agenda-pet-modal-mobile-375.png`
- `./agenda-reagendamento-mobile-375.png`

## Observacoes
- A validacao funcional foi feita na agenda diaria com a aba `operacao` ativa, incluindo troca de status para `finalizado`, abertura automatica do modal operacional, edicao de check-in e edicao de check-out.
- O perfil rapido do pet foi revalidado em runtime real para garantir que o clique no nome do pet nao quebra mais a estrutura visual da lista.
- O modal de reagendamento foi revalidado em mobile para confirmar header integrado, botao de fechar dentro da shell e ausencia de overflow.
- As capturas `tab1-column-contract.png` e `tab3-column-contract.png` foram geradas a partir do HTML autenticado do ambiente real, renderizado localmente em Chrome headless para registrar o estado publicado no servidor.
- O atendimento de QA usado nos testes foi restaurado ao estado original ao final da rodada, e o usuario administrativo temporario criado para a auditoria foi removido do servidor.

# Screenshots 2026-04-21 - Cadastro / Portal Signature

## Contexto
- Objetivo da mudanca: reescrever o cadastro publico, os formularios internos de cliente/pet e os fluxos de acesso/reset/profile update do portal para a base unica DPS Signature.
- Ambiente: homologacao oculta no `https://desi.pet` com paginas QA dedicadas, validacao autenticada via WP-CLI e captura automatizada com Chrome headless.
- Fonte de verdade visual seguida explicitamente: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`, com a entrega final restrita ao padrao DPS Signature.

## Antes/Depois
- Antes: o cadastro coexistia com runtime legado, o portal misturava shells diferentes, havia CSS/JS inline em pontos criticos e o portal autenticado no `desi.pet` ainda sofria com texto corrompido e scripts nao carregados em templates sem `wp_footer`.
- Depois: cadastro publico e profile update passaram para a fundacao Signature, os formularios internos seguem a mesma linguagem visual, o portal de acesso/reset foi alinhado ao novo shell e o portal autenticado passou a carregar seus scripts corretamente mesmo no template blank do tema.
- Ajuste estrutural complementar: os scripts do portal e o `dps-signature-forms` deixaram de depender do footer ausente do template atual, eliminando falhas de execucao no acesso publico, no shell autenticado e no fluxo de adicionar novo pet no profile update.

## Arquivos de codigo alterados
- `ANALYSIS.md`
- `CHANGELOG.md`
- `plugins/desi-pet-shower-base/desi-pet-shower-base.php`
- `plugins/desi-pet-shower-base/assets/css/dps-signature-forms.css`
- `plugins/desi-pet-shower-base/assets/js/dps-signature-forms.js`
- `plugins/desi-pet-shower-base/templates/forms/client-form.php`
- `plugins/desi-pet-shower-base/templates/forms/pet-form.php`
- `plugins/desi-pet-shower-client-portal/includes/class-dps-client-portal.php`
- `plugins/desi-pet-shower-client-portal/includes/class-dps-portal-profile-update.php`
- `plugins/desi-pet-shower-client-portal/templates/portal-access.php`
- `plugins/desi-pet-shower-client-portal/templates/portal-password-reset.php`
- `plugins/desi-pet-shower-client-portal/templates/profile-update-form.php`
- `plugins/desi-pet-shower-client-portal/assets/css/client-portal-auth.css`
- `plugins/desi-pet-shower-client-portal/assets/css/client-portal-profile-update.css`
- `plugins/desi-pet-shower-client-portal/assets/js/client-portal.js`
- `plugins/desi-pet-shower-client-portal/assets/js/client-portal-profile-update.js`
- `plugins/desi-pet-shower-frontend/desi-pet-shower-frontend-addon.php`
- `plugins/desi-pet-shower-frontend/assets/css/registration-v2.css`
- `plugins/desi-pet-shower-frontend/assets/js/registration-v2.js`
- `plugins/desi-pet-shower-frontend/includes/handlers/class-dps-registration-handler.php`
- `plugins/desi-pet-shower-frontend/includes/modules/class-dps-frontend-registration-module.php`
- `plugins/desi-pet-shower-frontend/includes/modules/class-dps-frontend-registration-v2-module.php`
- `plugins/desi-pet-shower-frontend/includes/modules/class-dps-frontend-settings-module.php`
- `plugins/desi-pet-shower-frontend/includes/services/class-dps-client-service.php`
- `plugins/desi-pet-shower-frontend/includes/services/class-dps-email-confirmation-service.php`
- `plugins/desi-pet-shower-frontend/includes/services/class-dps-pet-service.php`
- `plugins/desi-pet-shower-frontend/includes/validators/class-dps-form-validator.php`
- `plugins/desi-pet-shower-frontend/templates/registration/form-client-data.php`
- `plugins/desi-pet-shower-frontend/templates/registration/form-duplicate-warning.php`
- `plugins/desi-pet-shower-frontend/templates/registration/form-error.php`
- `plugins/desi-pet-shower-frontend/templates/registration/form-main.php`
- `plugins/desi-pet-shower-frontend/templates/registration/form-pet-data.php`
- `plugins/desi-pet-shower-frontend/templates/registration/form-success.php`

## Breakpoints validados
- `375`
- `600`
- `840`
- `1200`
- `1920`

## Capturas - cadastro publico Signature
- `./qa-signature-registration-375.png`
- `./qa-signature-registration-600.png`
- `./qa-signature-registration-840.png`
- `./qa-signature-registration-1200.png`
- `./qa-signature-registration-1920.png`
- `./qa-signature-registration-errors-840.png`

## Capturas - portal de acesso e reset
- `./qa-signature-portal-access-375.png`
- `./qa-signature-portal-access-600.png`
- `./qa-signature-portal-access-840.png`
- `./qa-signature-portal-access-1200.png`
- `./qa-signature-portal-access-1920.png`
- `./qa-signature-portal-access-focus-375.png`
- `./qa-signature-portal-reset-375.png`
- `./qa-signature-portal-reset-600.png`
- `./qa-signature-portal-reset-840.png`
- `./qa-signature-portal-reset-1200.png`
- `./qa-signature-portal-reset-1920.png`

## Capturas - portal autenticado e profile update
- `./qa-signature-portal-auth-375.png`
- `./qa-signature-portal-auth-600.png`
- `./qa-signature-portal-auth-840.png`
- `./qa-signature-portal-auth-1200.png`
- `./qa-signature-portal-auth-1920.png`
- `./qa-signature-profile-update-375.png`
- `./qa-signature-profile-update-600.png`
- `./qa-signature-profile-update-840.png`
- `./qa-signature-profile-update-1200.png`
- `./qa-signature-profile-update-1920.png`
- `./qa-signature-profile-update-add-pet-840.png`

## Observacoes
- O smoke test visual confirmou o novo cadastro publico em todos os breakpoints, incluindo validacao obrigatoria e estados de erro sem overflow.
- O portal autenticado exigiu um ajuste adicional de runtime porque o template blank do tema nao imprimia scripts em footer; o fix foi revalidado com token QA novo e o shell deixou de exibir mojibake no titulo, breadcrumb e tabs principais.
- O profile update foi revalidado com inclusao dinamica de novo pet, preservando o contrato do backend e sem depender de scripts inline.
- Alguns checks textuais do relatorio JSON ficaram `false` porque a copy final mudou em relacao aos rascunhos de teste, mas as capturas finais confirmam que reset de senha e profile update renderizam corretamente com o shell Signature.

# Ajuste complementar - Cadastro DPS Signature limpo

## Contexto
- Objetivo da mudanca: remover a aparencia de manual do cadastro publico, mantendo apenas copy curta, labels objetivos, erros inline e acoes diretas.
- Ambiente: pagina oficial publicada em `https://desi.pet/cadastro-de-clientes-e-pets/`, apos envio dos arquivos por SFTP.
- PadrÃ£o visual aplicado: DPS Signature como unica linguagem visual do fluxo.

## Antes/Depois
- Antes: hero, painel e campos tinham textos explicativos longos, com linguagem tecnica e instrucional demais para um formulario publico.
- Depois: o cadastro exibe titulo curto, uma frase de contexto, etapas compactas e campos sem helpers desnecessarios, preservando contratos de dados, hooks, nonces e comportamento responsivo.

## Arquivos de codigo alterados nesta rodada
- `plugins/desi-pet-shower-base/desi-pet-shower-base.php`
- `plugins/desi-pet-shower-base/assets/css/dps-base.css`
- `plugins/desi-pet-shower-base/assets/css/dps-design-tokens.css`
- `plugins/desi-pet-shower-base/assets/css/dps-signature-forms.css`
- `plugins/desi-pet-shower-base/templates/forms/client-form.php`
- `plugins/desi-pet-shower-base/templates/forms/pet-form.php`
- `plugins/desi-pet-shower-client-portal/templates/portal-access.php`
- `plugins/desi-pet-shower-client-portal/templates/portal-password-reset.php`
- `plugins/desi-pet-shower-client-portal/templates/profile-update-form.php`
- `plugins/desi-pet-shower-frontend/assets/css/registration-v2.css`
- `plugins/desi-pet-shower-frontend/assets/js/registration-v2.js`
- `plugins/desi-pet-shower-frontend/includes/support/class-dps-frontend-assets.php`
- `plugins/desi-pet-shower-frontend/templates/registration/form-client-data.php`
- `plugins/desi-pet-shower-frontend/templates/registration/form-main.php`
- `plugins/desi-pet-shower-frontend/templates/registration/form-pet-data.php`

## Breakpoints validados
- `375`
- `600`
- `840`
- `1200`
- `1920`

## Capturas - cadastro publico limpo
- `./signature-registration-clean-375.png`
- `./signature-registration-clean-600.png`
- `./signature-registration-clean-840.png`
- `./signature-registration-clean-1200.png`
- `./signature-registration-clean-1920.png`

## Validacao
- O HTML publicado retornou o titulo `Cadastro de tutor e pets`, carregou `registration-v2.css` e nao exibiu as frases explicativas removidas.
- As capturas confirmaram ausencia de overflow horizontal nos cinco breakpoints.

# Ajuste complementar - UI Signature sem resquicios DPS Signature

## Contexto
- Objetivo da mudanca: consolidar cadastro, portal de acesso e atualizacao de perfil em uma unica linguagem DPS Signature, sem step rail, hero duplicado, metric cards, copy de manual ou fallback visual DPS Signature.
- Ambiente: paginas publicadas em `https://desi.pet/cadastro-de-clientes-e-pets/` e `https://desi.pet/portal-do-cliente/`, apos envio dos arquivos por SFTP.
- O token temporario de atualizacao de perfil foi gerado via WP-CLI para QA real e revogado ao final.

## Antes/Depois
- Antes: a UI ainda misturava trilhas laterais, cards auxiliares, textos instrutivos repetidos e acento tertiary que deixava o portal com aparencia lilas.
- Depois: o cadastro usa um workspace unico Signature, o portal usa hero direto com acento primario, os botoes do acesso nao colapsam em circulos e os resumos vazios de pet ficam ocultos ate haver dados reais.
- Ajuste posterior: o chip textual `DPS Signature` e a frase introdutoria do topo do cadastro foram removidos; as secoes ficaram apenas como `Tutor`, `Pets` e `Enviar`.

## Arquivos de codigo alterados nesta rodada
- `plugins/desi-pet-shower-base/assets/css/dps-signature-forms.css`
- `plugins/desi-pet-shower-client-portal/README.md`
- `plugins/desi-pet-shower-client-portal/assets/css/client-portal-auth.css`
- `plugins/desi-pet-shower-client-portal/assets/css/client-portal.css`
- `plugins/desi-pet-shower-client-portal/assets/js/client-portal-profile-update.js`
- `plugins/desi-pet-shower-client-portal/includes/class-dps-client-portal.php`
- `plugins/desi-pet-shower-client-portal/templates/portal-access.php`
- `plugins/desi-pet-shower-client-portal/templates/portal-password-reset.php`
- `plugins/desi-pet-shower-client-portal/templates/profile-update-form.php`
- `plugins/desi-pet-shower-frontend/assets/css/booking-v2.css`
- `plugins/desi-pet-shower-frontend/assets/css/frontend-addon.css`
- `plugins/desi-pet-shower-frontend/assets/css/registration-v2.css`
- `plugins/desi-pet-shower-frontend/assets/js/registration-v2.js`
- `plugins/desi-pet-shower-frontend/includes/modules/class-dps-frontend-booking-module.php`
- `plugins/desi-pet-shower-frontend/includes/modules/class-dps-frontend-booking-v2-module.php`
- `plugins/desi-pet-shower-frontend/includes/modules/class-dps-frontend-registration-module.php`
- `plugins/desi-pet-shower-frontend/includes/modules/class-dps-frontend-registration-v2-module.php`
- `plugins/desi-pet-shower-frontend/includes/modules/class-dps-frontend-settings-module.php`
- `plugins/desi-pet-shower-frontend/includes/support/class-dps-frontend-deprecation-notice.php`
- `plugins/desi-pet-shower-frontend/templates/booking/form-main.php`
- `plugins/desi-pet-shower-frontend/templates/components/alert.php`
- `plugins/desi-pet-shower-frontend/templates/components/button-primary.php`
- `plugins/desi-pet-shower-frontend/templates/components/button-secondary.php`
- `plugins/desi-pet-shower-frontend/templates/components/card.php`
- `plugins/desi-pet-shower-frontend/templates/components/field-checkbox.php`
- `plugins/desi-pet-shower-frontend/templates/components/field-email.php`
- `plugins/desi-pet-shower-frontend/templates/components/field-phone.php`
- `plugins/desi-pet-shower-frontend/templates/components/field-select.php`
- `plugins/desi-pet-shower-frontend/templates/components/field-text.php`
- `plugins/desi-pet-shower-frontend/templates/components/field-textarea.php`
- `plugins/desi-pet-shower-frontend/templates/components/loader.php`
- `plugins/desi-pet-shower-frontend/templates/registration/form-client-data.php`
- `plugins/desi-pet-shower-frontend/templates/registration/form-main.php`
- `plugins/desi-pet-shower-frontend/templates/registration/form-pet-data.php`
- `plugins/desi-pet-shower-registration/assets/css/registration-addon.css`
- `plugins/desi-pet-shower-registration/assets/js/dps-registration.js`
- `plugins/desi-pet-shower-registration/desi-pet-shower-registration-addon.php`

## Breakpoints validados
- `375`
- `600`
- `840`
- `1200`
- `1920`

## Capturas - cadastro publico final
- `./signature-registration-clean-375.png`
- `./signature-registration-clean-600.png`
- `./signature-registration-clean-840.png`
- `./signature-registration-clean-1200.png`
- `./signature-registration-clean-1920.png`

## Capturas - portal de acesso final
- `./signature-portal-access-clean-375.png`
- `./signature-portal-access-clean-600.png`
- `./signature-portal-access-clean-840.png`
- `./signature-portal-access-clean-1200.png`
- `./signature-portal-access-clean-1920.png`

## Capturas - atualizacao de perfil final
- `./signature-profile-update-clean-375.png`
- `./signature-profile-update-clean-600.png`
- `./signature-profile-update-clean-840.png`
- `./signature-profile-update-clean-1200.png`
- `./signature-profile-update-clean-1920.png`

## Validacao
- Cadastro: `#dps-registration-signature-form` renderizado, `#dps-reg-form` ausente, sem step rail, sem texto DPS Signature/Material, sem copy de manual e sem overflow nos cinco breakpoints.
- Cadastro complementar: sem texto visivel `DPS Signature` no topo, sem frase introdutoria redundante e sem os titulos antigos `Contato principal`, `Dados do pet` ou `Concluir cadastro`.
- Portal de acesso: sem step rail, sem texto DPS Signature/Material, sem overflow, hero com acento primario e botoes com largura utilizavel.
- Atualizacao de perfil: formulario com token real renderizado, sem step rail, sem texto DPS Signature/Material, sem copy de manual e sem overflow nos cinco breakpoints.

# Ajuste complementar - Cadastro DPS Signature reto e sem herdanca antiga

## Contexto
- Objetivo da mudanca: consolidar o cadastro publico em uma base visual DPS Signature propria, sem depender do `dps-signature-forms` compartilhado e sem geometria herdada do padrao antigo.
- Ambiente: pagina oficial publicada em `https://desi.pet/cadastro-de-clientes-e-pets/`, com upload por SFTP e validacao direta do HTML servido.
- Fonte de verdade visual seguida explicitamente: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.

## Antes/Depois
- Antes: o cadastro ainda carregava uma linguagem visual macia demais, com estrutura herdada do shell antigo, excesso de arredondamento, copy redundante e toggles que ainda denunciavam adaptacao.
- Depois: o formulario passou a usar uma fundacao propria, com blocos retos, labels mais objetivas, disclosures secos, botoes sem pill, hero sem texto de apoio redundante e cards de pet tratados apenas como superficie de interacao.
- Ajuste estrutural complementar: o shortcode continua preservando hooks, nonces, handlers e nomes de campos, mas o runtime visual do cadastro nao depende mais do CSS/JS compartilhado do formulario Signature antigo.

## Arquivos de codigo alterados nesta rodada
- `plugins/desi-pet-shower-frontend/assets/css/registration-v2.css`
- `plugins/desi-pet-shower-frontend/assets/js/registration-v2.js`
- `plugins/desi-pet-shower-frontend/includes/modules/class-dps-frontend-registration-v2-module.php`
- `plugins/desi-pet-shower-frontend/templates/registration/form-client-data.php`
- `plugins/desi-pet-shower-frontend/templates/registration/form-duplicate-warning.php`
- `plugins/desi-pet-shower-frontend/templates/registration/form-error.php`
- `plugins/desi-pet-shower-frontend/templates/registration/form-main.php`
- `plugins/desi-pet-shower-frontend/templates/registration/form-pet-data.php`
- `plugins/desi-pet-shower-frontend/templates/registration/form-success.php`
- `plugins/desi-pet-shower-loyalty/desi-pet-shower-loyalty.php`

## Breakpoints validados
- `375`
- `600`
- `840`
- `1200`
- `1920`

## Capturas - cadastro publico DPS Signature
- `./registration-signature-dps-375.png`
- `./registration-signature-dps-600.png`
- `./registration-signature-dps-840.png`
- `./registration-signature-dps-1200.png`
- `./registration-signature-dps-1920.png`

## Validacao
- O HTML publicado retornou `200` e renderizou `dps-registration__hero` e `#dps-registration-form`.
- O runtime publicado nao exibiu `dps-signature-shell`, `dps-signature-panel`, `dps-registration-signature`, `Preferencias e extras`, `Adicionar outro pet` nem texto visivel `DPS Signature`.
- A nova copy publicada confirmou os titulos `Cadastro de tutor e pets`, `Dados do tutor`, `Pets`, `Detalhes adicionais`, `Finalizar cadastro` e `Adicionar pet`.
- A captura automatizada foi feita com Chrome headless porque o Playwright MCP permaneceu bloqueado no ambiente local por `EPERM` ao tentar criar `C:\\Windows\\System32\\.playwright-mcp`.
