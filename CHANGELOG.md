# Desi Pet Shower — CHANGELOG

Este documento registra, em ordem cronológica inversa, todas as alterações lançadas do Desi Pet Shower (DPS). Mantenha-o sempre atualizado para que equipe, parceiros e clientes tenham clareza sobre evoluções, correções e impactos.

## Como atualizar este changelog
1. **Abra uma nova seção** para cada versão liberada, usando o formato `AAAA-MM-DD` para a data real do lançamento.
2. **Agrupe entradas por categoria**, mesmo que alguma fique vazia (remova a categoria vazia apenas se não houver conteúdo relevante).
3. **Use linguagem imperativa e concisa**, indicando impacto visível para usuários e integradores.
4. **Referencie tickets ou links**, quando útil, no final de cada item.
5. **Não liste alterações internas triviais** (refactors menores ou ajustes de estilo) a menos que afetem integrações ou documentação.

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

---

### [Unreleased]

#### Added (Adicionado)
- Criadas classes helper para melhorar qualidade e manutenibilidade do código:
  - `DPS_Money_Helper`: manipulação consistente de valores monetários, conversão formato brasileiro ↔ centavos
  - `DPS_URL_Builder`: construção padronizada de URLs de edição, exclusão, visualização e navegação
  - `DPS_Query_Helper`: consultas WP_Query reutilizáveis com filtros comuns e paginação
  - `DPS_Request_Validator`: validação centralizada de nonces, capabilities e sanitização de campos
- Adicionado documento de análise de refatoração (`REFACTORING_ANALYSIS.md`) com identificação detalhada de problemas de código e sugestões de melhoria
- Criado arquivo de exemplos práticos (`includes/refactoring-examples.php`) demonstrando uso das classes helper e padrões de refatoração

#### Changed (Alterado)
- Documentação expandida com exemplos de como quebrar funções grandes em métodos menores e mais focados
- Estabelecidos padrões de nomenclatura mais descritiva para variáveis e funções

#### Fixed (Corrigido)
- Evitado retorno 401 e mensagem "Unauthorized" em acessos comuns ao site, aplicando a validação do webhook do Mercado Pago apenas quando a requisição traz indicadores da notificação.

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
