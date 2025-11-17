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

### [YYYY-MM-DD] v0.1.0 — Primeira versão pública

#### Added (Adicionado)
- Estrutura inicial do plugin base com hooks `dps_base_nav_tabs_*` e `dps_settings_*`.
- Add-on Financeiro com sincronização da tabela `dps_transacoes`.
- Guia inicial de configuração e checklist de segurança do WordPress.

#### Security (Segurança)
- Nonces aplicados em formulários de painel para evitar CSRF.
