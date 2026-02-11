# Guia Operacional de Rollout — Frontend Add-on

> **Versão**: 1.3.0 (Fase 5)
> **Última atualização**: 2026-02-11

## 1. Visão geral

O add-on `desi-pet-shower-frontend` consolida experiências frontend (cadastro, agendamento, configurações) com rollout controlado por **feature flags**. Cada módulo pode ser habilitado/desabilitado independentemente, com rollback instantâneo.

### Módulos disponíveis

| Módulo | Flag | Fase | Shortcode/Hook assumido |
|--------|------|------|-------------------------|
| Registration | `registration` | 2 | `[dps_registration_form]` |
| Booking | `booking` | 3 | `[dps_booking_form]` |
| Settings | `settings` | 4 | `dps_settings_register_tabs` |

---

## 2. Pré-requisitos

- Plugin base `desi-pet-shower-base` ativo (obrigatório).
- Para módulo Registration: add-on `desi-pet-shower-registration` ativo.
- Para módulo Booking: add-on `desi-pet-shower-booking` ativo.
- WordPress 6.9+ e PHP 8.4+.
- Design tokens CSS (`dps-design-tokens.css`) disponíveis no base.

---

## 3. Ativação por ambiente

### 3.1 Ambiente de desenvolvimento

```bash
# 1. Ativar plugin no WordPress
wp plugin activate desi-pet-shower-frontend

# 2. Habilitar todos os módulos de uma vez (dev/teste)
wp option update dps_frontend_feature_flags '{"registration":true,"booking":true,"settings":true}' --format=json

# 3. Verificar ativação
wp option get dps_frontend_feature_flags --format=json
```

### 3.2 Ambiente de homologação

```bash
# 1. Ativar plugin
wp plugin activate desi-pet-shower-frontend

# 2. Habilitar módulos um a um (rollout gradual)
# Primeiro: Settings (menor risco — apenas aba admin)
wp option update dps_frontend_feature_flags '{"registration":false,"booking":false,"settings":true}' --format=json

# Após validação: Registration
wp option update dps_frontend_feature_flags '{"registration":true,"booking":false,"settings":true}' --format=json

# Após validação: Booking
wp option update dps_frontend_feature_flags '{"registration":true,"booking":true,"settings":true}' --format=json
```

### 3.3 Ambiente de produção

**Ordem recomendada de ativação:**

1. **Settings** (risco mínimo — aba admin, não afeta frontend público)
2. **Registration** (risco médio — formulário público, dual-run com legado)
3. **Booking** (risco médio — agendamento, dual-run com legado)

**Janela de observação:** mínimo 48h entre ativação de cada módulo.

```bash
# Via WP-CLI
wp option update dps_frontend_feature_flags '{"registration":false,"booking":false,"settings":true}' --format=json

# Ou via admin: Configurações → aba Frontend → checkboxes
```

### 3.4 Ativação via painel administrativo

1. Acesse **Configurações** (shortcode `[dps_configuracoes]`).
2. Navegue até a aba **Frontend**.
3. Marque os módulos desejados.
4. Clique em **Salvar Configurações**.

> **Requisito**: o módulo Settings precisa estar ativo para que a aba apareça. Para ativá-lo pela primeira vez, use WP-CLI ou modifique a option diretamente.

---

## 4. Verificação pós-ativação

### 4.1 Verificação Settings

- [ ] Aba "Frontend" aparece na página de configurações.
- [ ] Checkboxes de feature flags renderizam corretamente.
- [ ] Salvar altera os flags (verificar via `wp option get dps_frontend_feature_flags`).
- [ ] Mensagem de sucesso exibida após salvar.

### 4.2 Verificação Registration

- [ ] Página com `[dps_registration_form]` carrega sem erro.
- [ ] Formulário de cadastro renderiza com wrapper `.dps-frontend`.
- [ ] CSS `frontend-addon.css` está enfileirado na página.
- [ ] Envio do formulário funciona (criar cliente e pet).
- [ ] Emails de confirmação/welcome enviados.
- [ ] Hook `dps_registration_after_fields` funciona (campo de indicação do Loyalty aparece, se ativo).
- [ ] Hook `dps_registration_after_client_created` funciona (indicação registrada, se aplicável).

### 4.3 Verificação Booking

- [ ] Página com `[dps_booking_form]` carrega sem erro.
- [ ] Formulário de agendamento renderiza com wrapper `.dps-frontend`.
- [ ] CSS `frontend-addon.css` está enfileirado na página.
- [ ] Criar agendamento funciona.
- [ ] Tela de confirmação aparece após salvar.
- [ ] Hook `dps_base_after_save_appointment` dispara (verificar integração com payment, groomers, calendar, etc.).

### 4.4 Verificação geral

- [ ] WP_DEBUG ativo: verificar `debug.log` para logs `[DPS Frontend]`.
- [ ] Sem erros PHP no `debug.log`.
- [ ] Performance: tempo de carregamento das páginas dentro do esperado.

---

## 5. Monitoramento contínuo

### Logs

Com `WP_DEBUG = true`, o add-on registra:
- `[DPS Frontend] [INFO]` — Ativação de módulos, renderização de shortcodes.
- `[DPS Frontend] [WARNING]` — Módulo ativado sem legado disponível.
- `[DPS Frontend] [ERROR]` — Método do legado não encontrado.

### Métricas de observabilidade

- Presença de log `Shortcode dps_registration_form renderizado via módulo Frontend.` confirma que o módulo está assumindo o shortcode.
- Presença de log `Shortcode dps_booking_form renderizado via módulo Frontend.` confirma que o módulo de booking está ativo.
- Ausência de logs `[ERROR]` indica operação normal.

---

## 6. Rollback

Ver [FRONTEND_RUNBOOK.md](FRONTEND_RUNBOOK.md) para procedimentos detalhados de rollback.

**Rollback rápido:**
```bash
# Desabilitar módulo específico
wp option update dps_frontend_feature_flags '{"registration":false,"booking":true,"settings":true}' --format=json

# Desabilitar todos os módulos
wp option update dps_frontend_feature_flags '{"registration":false,"booking":false,"settings":false}' --format=json

# Desativar plugin completamente
wp plugin deactivate desi-pet-shower-frontend
```

---

## 7. Notas importantes

- **Dual-run**: o legado continua processando toda a lógica (forms, emails, REST, AJAX). O módulo frontend apenas envolve o output.
- **Não há migração de dados**: nenhuma option ou metadado é criado/alterado pelo frontend add-on (exceto `dps_frontend_feature_flags`).
- **Cache**: se houver cache de página, limpar após ativação/desativação de módulos.
- **Add-ons legados**: devem permanecer ativos durante o dual-run. Não desativar `desi-pet-shower-registration` ou `desi-pet-shower-booking` enquanto os módulos estiverem em dual-run.
