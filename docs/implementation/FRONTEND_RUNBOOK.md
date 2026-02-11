# Runbook de Incidentes — Frontend Add-on

> **Versão**: 1.3.0 (Fase 5)
> **Última atualização**: 2026-02-11

## 1. Resumo

Este runbook documenta procedimentos de diagnóstico e rollback para incidentes relacionados ao add-on `desi-pet-shower-frontend`.

---

## 2. Classificação de incidentes

| Severidade | Descrição | Tempo de resposta | Ação |
|------------|-----------|-------------------|------|
| **P1 — Crítico** | Página de cadastro ou agendamento quebrada | Imediato | Rollback do módulo afetado |
| **P2 — Alto** | Hooks não disparando (ex.: Loyalty sem campo de indicação) | < 1h | Diagnóstico + rollback se necessário |
| **P3 — Médio** | CSS não carregando / visual incorreto | < 4h | Diagnóstico + fix |
| **P4 — Baixo** | Log excessivo / performance marginal | Próximo sprint | Investigar |

---

## 3. Diagnóstico rápido

### 3.1 Verificar estado dos feature flags

```bash
wp option get dps_frontend_feature_flags --format=json
```

**Saída esperada:**
```json
{"registration":true,"booking":true,"settings":true}
```

### 3.2 Verificar logs

```bash
# Últimas 50 linhas do log do frontend
grep '\[DPS Frontend\]' wp-content/debug.log | tail -50

# Filtrar apenas erros
grep '\[DPS Frontend\] \[ERROR\]' wp-content/debug.log
```

### 3.3 Verificar plugin ativo

```bash
wp plugin list --status=active | grep frontend
```

### 3.4 Verificar dependências

```bash
# Base plugin
wp plugin list --status=active | grep desi-pet-shower-base

# Legado Registration
wp plugin list --status=active | grep desi-pet-shower-registration

# Legado Booking
wp plugin list --status=active | grep desi-pet-shower-booking
```

### 3.5 Verificar shortcode registration

```bash
# No WordPress, verificar se o shortcode está registrado
wp eval "var_dump(shortcode_exists('dps_registration_form'));"
wp eval "var_dump(shortcode_exists('dps_booking_form'));"
```

---

## 4. Procedimentos de rollback

### 4.1 Rollback de módulo Registration

**Sintoma**: Formulário de cadastro quebrado, campos faltando ou erro PHP.

```bash
# 1. Desabilitar módulo Registration
wp eval "
\$flags = get_option('dps_frontend_feature_flags', []);
\$flags['registration'] = false;
update_option('dps_frontend_feature_flags', \$flags);
echo 'Registration desabilitado.';
"

# 2. Limpar cache (se houver)
wp cache flush

# 3. Verificar que o legado assumiu
wp eval "var_dump(shortcode_exists('dps_registration_form'));"
# Deve retornar true (registrado pelo legado)

# 4. Testar página de cadastro
curl -sI https://DOMINIO/pagina-de-cadastro/ | head -5
```

### 4.2 Rollback de módulo Booking

**Sintoma**: Formulário de agendamento quebrado ou tela de confirmação não aparece.

```bash
# 1. Desabilitar módulo Booking
wp eval "
\$flags = get_option('dps_frontend_feature_flags', []);
\$flags['booking'] = false;
update_option('dps_frontend_feature_flags', \$flags);
echo 'Booking desabilitado.';
"

# 2. Limpar cache
wp cache flush

# 3. Testar página de agendamento
curl -sI https://DOMINIO/pagina-de-agendamento/ | head -5
```

### 4.3 Rollback de módulo Settings

**Sintoma**: Aba "Frontend" não aparece ou erro ao salvar.

```bash
# 1. Desabilitar módulo Settings
wp eval "
\$flags = get_option('dps_frontend_feature_flags', []);
\$flags['settings'] = false;
update_option('dps_frontend_feature_flags', \$flags);
echo 'Settings desabilitado.';
"
```

### 4.4 Rollback completo

**Sintoma**: Múltiplos problemas ou incidente P1.

```bash
# Opção A: desabilitar todos os flags
wp option update dps_frontend_feature_flags '{"registration":false,"booking":false,"settings":false}' --format=json

# Opção B: desativar plugin completamente
wp plugin deactivate desi-pet-shower-frontend

# Verificar restauração
wp eval "var_dump(shortcode_exists('dps_registration_form'));"
wp eval "var_dump(shortcode_exists('dps_booking_form'));"
```

---

## 5. Cenários de incidente específicos

### 5.1 Campo de indicação do Loyalty não aparece no cadastro

**Causa provável**: Hook `dps_registration_after_fields` não está disparando.

**Diagnóstico**:
```bash
# Verificar se o Loyalty está ativo
wp plugin list --status=active | grep loyalty

# Verificar se o hook está registrado
wp eval "global \$wp_filter; var_dump(isset(\$wp_filter['dps_registration_after_fields']));"
```

**Resolução**: O módulo Registration usa dual-run — o hook é disparado pelo legado. Se não aparece, o problema está no legado ou no Loyalty, não no frontend add-on. Desabilitar o flag `registration` para confirmar.

### 5.2 Agendamento salvo mas hooks não disparam

**Causa provável**: `dps_base_after_save_appointment` não está disparando.

**Diagnóstico**:
```bash
# Verificar hooks registrados
wp eval "global \$wp_filter; var_dump(array_keys(\$wp_filter['dps_base_after_save_appointment']->callbacks ?? []));"
```

**Resolução**: O módulo Booking usa dual-run — o hook é disparado pelo legado (`DPS_Booking_Addon::capture_saved_appointment`). Desabilitar o flag `booking` para confirmar.

### 5.3 CSS não carregando

**Causa provável**: `dps-design-tokens.css` não registrado ou `DPS_BASE_URL` não definida.

**Diagnóstico**:
```bash
# Verificar constantes
wp eval "echo 'DPS_BASE_URL: ' . (defined('DPS_BASE_URL') ? DPS_BASE_URL : 'NÃO DEFINIDA') . PHP_EOL;"
wp eval "echo 'DPS_FRONTEND_URL: ' . (defined('DPS_FRONTEND_URL') ? DPS_FRONTEND_URL : 'NÃO DEFINIDA') . PHP_EOL;"
```

**Resolução**: Verificar que o plugin base está ativo e que as constantes estão definidas.

### 5.4 Erro PHP fatal ao ativar

**Causa provável**: PHP < 8.4 (o add-on usa constructor promotion, readonly properties, union types).

**Diagnóstico**:
```bash
php -v
wp eval "echo PHP_VERSION;"
```

**Resolução**: Atualizar PHP para 8.4+.

---

## 6. Pós-incidente

1. **Registrar causa raiz** no log do projeto ou issue tracker.
2. **Verificar integridade**: após rollback, confirmar que o legado está operando normalmente.
3. **Comunicar equipe**: informar sobre o rollback e a causa raiz.
4. **Planejar correção**: criar issue para correção e reativação após fix.
5. **Retestar**: antes de reativar, executar checklist de verificação do [FRONTEND_ROLLOUT_GUIDE.md](FRONTEND_ROLLOUT_GUIDE.md).

---

## 7. Contatos

| Papel | Responsável | Canal |
|-------|-------------|-------|
| Mantenedor do Frontend Add-on | PRObst | GitHub Issues |
| Mantenedor do Plugin Base | PRObst | GitHub Issues |
| Suporte operacional | PRObst | GitHub Issues |
