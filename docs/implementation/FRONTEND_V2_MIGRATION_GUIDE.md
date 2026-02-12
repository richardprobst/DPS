# Guia de Migração — Frontend V1 para V2

> **Versão**: 1.0.0
> **Data**: 2026-02-12
> **Autor**: PRObst
> **Site**: [www.probst.pro](https://www.probst.pro)

---

## 1. Visão Geral

### O que é V1 (dual-run)

O Frontend V1 opera como **wrapper de dual-run** sobre os add-ons legados (`DPS_Registration_Addon` e `DPS_Booking_Addon`). Ele aplica uma camada visual M3 sobre a renderização original, mas continua dependendo do código, lógica e hooks dos plugins legados para funcionar.

**Shortcodes V1:**
- `[dps_registration_form]` — flag: `registration`
- `[dps_booking_form]` — flag: `booking`

### O que é V2 (nativo M3 Expressive)

O Frontend V2 é uma **reimplementação 100% nativa**, sem qualquer dependência dos add-ons legados. Toda a lógica de formulários, validação, AJAX e integração de hooks foi reescrita do zero, seguindo o padrão M3 Expressive e eliminando jQuery.

**Shortcodes V2:**
- `[dps_registration_v2]` — flag: `registration_v2`
- `[dps_booking_v2]` — flag: `booking_v2`

### Por que migrar

| Benefício | Detalhe |
|-----------|---------|
| **Independência** | V2 não requer `desi-pet-shower-registration` nem `desi-pet-shower-booking` ativos |
| **Performance** | Zero jQuery, JS nativo com lazy loading |
| **UX nativa M3** | Componentes M3 Expressive nativos (não wrappers sobre legado) |
| **Manutenibilidade** | Código único — sem camada de compatibilidade dual-run |
| **Segurança** | Validações nativas (CPF mod-11, nonce, reCAPTCHA v3) sem depender de implementações legadas |

### Recomendação de timeline

| Semana | Ação |
|--------|------|
| 1 | Habilitar V2 em paralelo + criar páginas de teste |
| 2 | Validar integrações (hooks, add-ons, telemetria) |
| 3 | Migrar shortcodes em produção |
| 4 | Desabilitar V1 |
| 5–8 | Período de observação (30 dias) |

---

## 2. Pré-requisitos

- **WordPress** 6.9+ instalado e ativo.
- **PHP** 8.4+ configurado no servidor.
- Plugin base `desi-pet-shower-base` ativo e atualizado.
- Frontend add-on `desi-pet-shower-frontend` instalado e ativo.
- Design tokens CSS (`dps-design-tokens.css`) disponíveis no base.
- **Não é necessário** ter `desi-pet-shower-registration` ou `desi-pet-shower-booking` ativos para V2.

### Verificação rápida

```bash
# Verificar versões
wp core version
php -v

# Verificar plugins ativos
wp plugin list --status=active | grep desi-pet-shower

# Verificar feature flags atuais
wp option get dps_frontend_feature_flags --format=json
```

---

## 3. Comparação de Features V1 vs V2

### 3.1 Registration (Cadastro)

| Feature | V1 (dual-run) | V2 (nativo) |
|---------|---------------|-------------|
| Renderização do form | Legado com superfície M3 | 100% nativo M3 Expressive |
| Validação de CPF | Via `DPS_Registration_Addon` | Nativa (algoritmo mod-11) |
| Detecção de duplicatas | Via add-on legado | Nativa (baseada em telefone) |
| reCAPTCHA v3 | Via add-on legado | Nativo (integração direta) |
| Confirmação por e-mail | Via add-on legado | Nativa (token com expiração de 48h) |
| Hooks de Loyalty | Disparados pelo legado | Via Hook Bridge (legado → V2) |
| Repetidor de Pets | JS legado (jQuery) | JS nativo (vanilla) |
| Datalist de raças | Via add-on legado | Nativo (44 cães + 20 gatos) |
| Dependência de jQuery | Sim | Não |
| Dependência do add-on legado | **Obrigatória** | **Nenhuma** |

### 3.2 Booking (Agendamento)

| Feature | V1 (dual-run) | V2 (nativo) |
|---------|---------------|-------------|
| Wizard de etapas | Legado com superfície M3 | 100% nativo M3 Expressive |
| Requisições AJAX | Via add-on legado (jQuery) | Nativo (Fetch API) |
| TaxiDog / Tosa | Lógica legada | Implementação nativa |
| 3 tipos de agendamento | Via `DPS_Booking_Addon` | Nativo (banho, tosa, banho+tosa) |
| Verificação de conflito de horário | Via add-on legado | Nativa (AJAX em tempo real) |
| Seleção de horário | Legado com wrapper | Nativo (slot picker M3) |
| Integração com 8 add-ons | Via hooks legados | Via Hook Bridge (compatibilidade total) |
| Calendário visual | Legado | Nativo M3 |
| Dependência de jQuery | Sim | Não |
| Dependência do add-on legado | **Obrigatória** | **Nenhuma** |

### 3.3 Hook Bridge — Compatibilidade de integrações

O Hook Bridge garante que os **hooks legados disparam primeiro** e os **hooks V2 disparam em seguida**, preservando a compatibilidade com todos os add-ons que consomem `dps_base_after_save_appointment`:

1. Stock (controle de estoque)
2. Payment (pagamentos/financeiro)
3. Groomers (groomer assignment)
4. Calendar (sincronização de calendário)
5. Communications (e-mail/SMS)
6. Push (notificações push)
7. Services (serviços adicionais)
8. Booking (lógica de agendamento)

---

## 4. Guia Passo a Passo de Migração

### Etapa 1: Verificar Compatibilidade

```bash
# 1. Verificar versão do WordPress
wp core version
# Esperado: 6.9 ou superior

# 2. Verificar versão do PHP
php -v
# Esperado: 8.4 ou superior

# 3. Verificar plugin base ativo
wp plugin list --status=active | grep desi-pet-shower-base

# 4. Verificar frontend add-on
wp plugin list --status=active | grep desi-pet-shower-frontend

# 5. Verificar sintaxe dos arquivos do add-on
find wp-content/plugins/desi-pet-shower-frontend -name "*.php" -exec php -l {} \;
```

### Etapa 2: Habilitar V2 em Paralelo

**Via painel administrativo:**
1. Acesse **Configurações → Frontend** (aba "Frontend").
2. Marque `registration_v2` ✅ (mantendo `registration` ✅ ativo).
3. Marque `booking_v2` ✅ (mantendo `booking` ✅ ativo).
4. Salve as configurações.

**Via WP-CLI:**
```bash
wp eval "update_option('dps_frontend_feature_flags', [
    'registration'    => true,
    'booking'         => true,
    'settings'        => true,
    'registration_v2' => true,
    'booking_v2'      => true
]);"
```

> **Nota:** V1 e V2 podem coexistir sem conflitos. Cada shortcode funciona de forma independente.

### Etapa 3: Criar Páginas V2

1. Crie uma nova página no WordPress com o shortcode `[dps_registration_v2]`.
2. Crie uma nova página com o shortcode `[dps_booking_v2]`.
3. Teste ambas as páginas em paralelo com as páginas V1 existentes.
4. **Mantenha as páginas V1 ativas** durante todo o período de testes.

### Etapa 4: Validar Integrações

**Registration — Loyalty hooks:**
- Preencha um cadastro V2 e verifique se o campo de indicação do Loyalty está presente.
- Confirme que o hook `dps_after_registration` dispara corretamente (check via debug log).

**Booking — 8 add-ons:**
- Crie um agendamento V2 e valide cada integração:

| Add-on | O que verificar |
|--------|-----------------|
| Stock | Estoque decrementado após agendamento |
| Payment | Transação criada em `dps_transacoes` |
| Groomers | Groomer atribuído ao agendamento |
| Calendar | Evento sincronizado |
| Communications | E-mail/SMS enviado |
| Push | Notificação push disparada |
| Services | Serviços adicionais vinculados |
| Booking | Dados salvos corretamente no post type |

**Telemetria:**
- Acesse **Configurações → Frontend** e verifique se os contadores V2 estão registrando uso.

```bash
# Verificar contadores via WP-CLI
wp option get dps_frontend_usage_counters --format=json
```

### Etapa 5: Migrar Shortcodes

Após validação bem-sucedida, migre as páginas de produção:

**Opção A — Trocar shortcodes nas páginas:**
- Edite a página de cadastro: substitua `[dps_registration_form]` por `[dps_registration_v2]`.
- Edite a página de agendamento: substitua `[dps_booking_form]` por `[dps_booking_v2]`.

**Opção B — Trocar feature flags (sem editar páginas):**
- Se cada página já usa o shortcode V2, basta desabilitar os flags V1 (próxima etapa).

### Etapa 6: Desabilitar V1

**Via painel administrativo:**
1. Acesse **Configurações → Frontend**.
2. Desmarque `registration` ❌.
3. Desmarque `booking` ❌.
4. Salve as configurações.

**Via WP-CLI:**
```bash
wp eval "update_option('dps_frontend_feature_flags', [
    'registration'    => false,
    'booking'         => false,
    'settings'        => true,
    'registration_v2' => true,
    'booking_v2'      => true
]);"
```

> **Importante:** Monitore o site por no mínimo **48 horas** após desabilitar V1.

### Etapa 7: Observação

- Monitore a **telemetria** por pelo menos 30 dias.
- Confirme que os contadores V1 estão zerados (nenhum uso residual).
- Verifique que todas as integrações dos 8 add-ons continuam funcionando.
- Acompanhe os logs para erros: `grep '\[DPS Frontend\]' wp-content/debug.log`.

```bash
# Verificar telemetria após 30 dias
wp option get dps_frontend_usage_counters --format=json
# Esperado: contadores V1 = 0, contadores V2 > 0
```

---

## 5. Plano de Rollback

O rollback é **instantâneo** e sem perda de dados.

### Como reverter

```bash
# Rollback imediato: desabilitar V2, habilitar V1
wp eval "update_option('dps_frontend_feature_flags', [
    'registration'    => true,
    'booking'         => true,
    'settings'        => true,
    'registration_v2' => false,
    'booking_v2'      => false
]);"
```

Ou via painel: **Configurações → Frontend** → desmarcar V2, marcar V1.

### Por que não há perda de dados

- V1 e V2 utilizam os **mesmos post types e meta fields**.
- Nenhum dado é migrado ou transformado entre versões.
- Todos os hooks são preservados via Hook Bridge.

### Garantias

| Aspecto | Garantia |
|---------|----------|
| Dados de cadastro | Mesmo `post_type`, mesmos `post_meta` |
| Dados de agendamento | Mesmo `post_type`, mesmos `post_meta` |
| Hooks | Hook Bridge mantém compatibilidade bidirecional |
| Feature flags | Alternância instantânea, sem restart |

---

## 6. Checklist de Compatibilidade

Use esta checklist antes, durante e após a migração:

- [ ] WordPress 6.9+ instalado
- [ ] PHP 8.4+ ativo
- [ ] Plugin base `desi-pet-shower-base` ativo e atualizado
- [ ] Frontend add-on `desi-pet-shower-frontend` ativo
- [ ] Páginas V1 funcionando normalmente
- [ ] Páginas V2 criadas e testadas
- [ ] Loyalty hooks testados (registration)
- [ ] 8 add-ons de booking testados
- [ ] Telemetria V2 registrando contagens
- [ ] Rollback testado (V2 → V1 → V2)
- [ ] 48h de observação sem erros
- [ ] 30 dias de monitoramento planejado

---

## 7. Troubleshooting

### V2 shortcode exibe página em branco

**Causa provável:** Feature flag V2 não está habilitado.

```bash
wp option get dps_frontend_feature_flags --format=json
# Verificar se registration_v2 ou booking_v2 está true
```

**Solução:** Habilitar o flag correspondente via Configurações → Frontend ou WP-CLI.

### Hooks não disparam após agendamento V2

**Causa provável:** Hook Bridge não está ativo ou há conflito de prioridade.

```bash
# Verificar se o hook está registrado
wp eval "global \$wp_filter; var_dump(isset(\$wp_filter['dps_base_after_save_appointment']));"
```

**Solução:** Verificar se o Frontend add-on está ativo e atualizado. O Hook Bridge registra callbacks com prioridade específica (legado primeiro, V2 depois).

### Estilos M3 não carregam no V2

**Causa provável:** Design tokens CSS não está enfileirado.

**Solução:** Verificar se `dps-design-tokens.css` está registrado no plugin base. Limpar cache do navegador e de plugins de cache (W3 Total Cache, WP Super Cache, etc.).

### Erros AJAX no booking V2

**Causa provável:** Nonce expirado ou capability insuficiente.

```bash
# Verificar logs de erro
grep 'dps_booking_v2' wp-content/debug.log | tail -20
```

**Solução:** Verificar se o nonce está sendo gerado corretamente na página. Confirmar que o usuário (ou visitante) tem as capabilities necessárias para a ação.

### reCAPTCHA v3 falhando no V2

**Causa provável:** Chave do site (site key) não configurada ou domínio não autorizado.

**Solução:** Verificar a configuração do reCAPTCHA em **Configurações → Frontend**. Confirmar que o domínio do site está autorizado no painel do Google reCAPTCHA.

### Telemetria V2 não registra contagens

**Causa provável:** Option `dps_frontend_usage_counters` não existe ou não está sendo incrementada.

```bash
wp option get dps_frontend_usage_counters --format=json
```

**Solução:** Verificar se o módulo Settings está habilitado (`settings: true`). A telemetria depende do módulo Settings ativo.

---

## 8. Configuração via WP-CLI

### Verificar flags atuais

```bash
wp option get dps_frontend_feature_flags --format=json
```

### Habilitar V2 (manter V1 ativo)

```bash
wp eval "update_option('dps_frontend_feature_flags', [
    'registration'    => true,
    'booking'         => true,
    'settings'        => true,
    'registration_v2' => true,
    'booking_v2'      => true
]);"
```

### Migrar para V2 (desabilitar V1)

```bash
wp eval "update_option('dps_frontend_feature_flags', [
    'registration'    => false,
    'booking'         => false,
    'settings'        => true,
    'registration_v2' => true,
    'booking_v2'      => true
]);"
```

### Verificar telemetria

```bash
wp option get dps_frontend_usage_counters --format=json
```

### Rollback para V1

```bash
wp eval "update_option('dps_frontend_feature_flags', [
    'registration'    => true,
    'booking'         => true,
    'settings'        => true,
    'registration_v2' => false,
    'booking_v2'      => false
]);"
```

---

## 9. Documentos Relacionados

| Documento | Caminho | Descrição |
|-----------|---------|-----------|
| Guia de Rollout | `docs/implementation/FRONTEND_ROLLOUT_GUIDE.md` | Procedimentos operacionais de ativação por ambiente |
| Runbook de Incidentes | `docs/implementation/FRONTEND_RUNBOOK.md` | Diagnóstico e rollback para incidentes |
| Análise Arquitetural | `ANALYSIS.md` | Visão completa de contratos, hooks e integrações |
| Design Frontend | `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` | Instruções de design M3 Expressive |
| Guia Visual | `docs/visual/VISUAL_STYLE_GUIDE.md` | Paleta, componentes e espaçamento |
| Changelog | `CHANGELOG.md` | Histórico de versões e releases |
