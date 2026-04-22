# Guia de MigraÃ§Ã£o â€” Frontend V1 para V2

> **VersÃ£o**: 1.0.0
> **Data**: 2026-02-12
> **Autor**: PRObst
> **Site**: [www.probst.pro](https://www.probst.pro)

---

## 1. VisÃ£o Geral

### O que Ã© V1 (dual-run)

O Frontend V1 opera como **wrapper de dual-run** sobre os add-ons legados (`DPS_Registration_Addon` e `DPS_Booking_Addon`). Ele aplica uma camada visual DPS Signature sobre a renderizaÃ§Ã£o original, mas continua dependendo do cÃ³digo, lÃ³gica e hooks dos plugins legados para funcionar.

**Shortcodes V1:**
- `[dps_registration_form]` â€” flag: `registration`
- `[dps_booking_form]` â€” flag: `booking`

### O que Ã© V2 (nativo DPS Signature)

O Frontend V2 Ã© uma **reimplementaÃ§Ã£o 100% nativa**, sem qualquer dependÃªncia dos add-ons legados. Toda a lÃ³gica de formulÃ¡rios, validaÃ§Ã£o, AJAX e integraÃ§Ã£o de hooks foi reescrita do zero, seguindo o padrÃ£o DPS Signature e eliminando jQuery.

**Shortcodes V2:**
- `[dps_registration_v2]` â€” flag: `registration_v2`
- `[dps_booking_v2]` â€” flag: `booking_v2`

### Por que migrar

| BenefÃ­cio | Detalhe |
|-----------|---------|
| **IndependÃªncia** | V2 nÃ£o requer `desi-pet-shower-registration` nem `desi-pet-shower-booking` ativos |
| **Performance** | Zero jQuery, JS nativo com lazy loading |
| **UX nativa DPS Signature** | Componentes DPS Signature nativos (nÃ£o wrappers sobre legado) |
| **Manutenibilidade** | CÃ³digo Ãºnico â€” sem camada de compatibilidade dual-run |
| **SeguranÃ§a** | ValidaÃ§Ãµes nativas (CPF mod-11, nonce, reCAPTCHA v3) sem depender de implementaÃ§Ãµes legadas |

### RecomendaÃ§Ã£o de timeline

| Semana | AÃ§Ã£o |
|--------|------|
| 1 | Habilitar V2 em paralelo + criar pÃ¡ginas de teste |
| 2 | Validar integraÃ§Ãµes (hooks, add-ons, telemetria) |
| 3 | Migrar shortcodes em produÃ§Ã£o |
| 4 | Desabilitar V1 |
| 5â€“8 | PerÃ­odo de observaÃ§Ã£o (30 dias) |

---

## 2. PrÃ©-requisitos

- **WordPress** 6.9+ instalado e ativo.
- **PHP** 8.4+ configurado no servidor.
- Plugin base `desi-pet-shower-base` ativo e atualizado.
- Frontend add-on `desi-pet-shower-frontend` instalado e ativo.
- Design tokens CSS (`dps-design-tokens.css`) disponÃ­veis no base.
- **NÃ£o Ã© necessÃ¡rio** ter `desi-pet-shower-registration` ou `desi-pet-shower-booking` ativos para V2.

### VerificaÃ§Ã£o rÃ¡pida

```bash
# Verificar versÃµes
wp core version
php -v

# Verificar plugins ativos
wp plugin list --status=active | grep desi-pet-shower

# Verificar feature flags atuais
wp option get dps_frontend_feature_flags --format=json
```

---

## 3. ComparaÃ§Ã£o de Features V1 vs V2

### 3.1 Registration (Cadastro)

| Feature | V1 (dual-run) | V2 (nativo) |
|---------|---------------|-------------|
| RenderizaÃ§Ã£o do form | Legado com superfÃ­cie DPS Signature | 100% nativo DPS Signature |
| ValidaÃ§Ã£o de CPF | Via `DPS_Registration_Addon` | Nativa (algoritmo mod-11) |
| DetecÃ§Ã£o de duplicatas | Via add-on legado | Nativa (baseada em telefone) |
| reCAPTCHA v3 | Via add-on legado | Nativo (integraÃ§Ã£o direta) |
| ConfirmaÃ§Ã£o por e-mail | Via add-on legado | Nativa (token com expiraÃ§Ã£o de 48h) |
| Hooks de Loyalty | Disparados pelo legado | Via Hook Bridge (legado â†’ V2) |
| Repetidor de Pets | JS legado (jQuery) | JS nativo (vanilla) |
| Datalist de raÃ§as | Via add-on legado | Nativo (44 cÃ£es + 20 gatos) |
| DependÃªncia de jQuery | Sim | NÃ£o |
| DependÃªncia do add-on legado | **ObrigatÃ³ria** | **Nenhuma** |

### 3.2 Booking (Agendamento)

| Feature | V1 (dual-run) | V2 (nativo) |
|---------|---------------|-------------|
| Wizard de etapas | Legado com superfÃ­cie DPS Signature | 100% nativo DPS Signature |
| RequisiÃ§Ãµes AJAX | Via add-on legado (jQuery) | Nativo (Fetch API) |
| TaxiDog / Tosa | LÃ³gica legada | ImplementaÃ§Ã£o nativa |
| 3 tipos de agendamento | Via `DPS_Booking_Addon` | Nativo (banho, tosa, banho+tosa) |
| VerificaÃ§Ã£o de conflito de horÃ¡rio | Via add-on legado | Nativa (AJAX em tempo real) |
| SeleÃ§Ã£o de horÃ¡rio | Legado com wrapper | Nativo (slot picker DPS Signature) |
| IntegraÃ§Ã£o com 8 add-ons | Via hooks legados | Via Hook Bridge (compatibilidade total) |
| CalendÃ¡rio visual | Legado | Nativo DPS Signature |
| DependÃªncia de jQuery | Sim | NÃ£o |
| DependÃªncia do add-on legado | **ObrigatÃ³ria** | **Nenhuma** |

### 3.3 Hook Bridge â€” Compatibilidade de integraÃ§Ãµes

O Hook Bridge garante que os **hooks legados disparam primeiro** e os **hooks V2 disparam em seguida**, preservando a compatibilidade com todos os add-ons que consomem `dps_base_after_save_appointment`:

1. Stock (controle de estoque)
2. Payment (pagamentos/financeiro)
3. Groomers (groomer assignment)
4. Calendar (sincronizaÃ§Ã£o de calendÃ¡rio)
5. Communications (e-mail/SMS)
6. Push (notificaÃ§Ãµes push)
7. Services (serviÃ§os adicionais)
8. Booking (lÃ³gica de agendamento)

---

## 4. Guia Passo a Passo de MigraÃ§Ã£o

### Etapa 1: Verificar Compatibilidade

```bash
# 1. Verificar versÃ£o do WordPress
wp core version
# Esperado: 6.9 ou superior

# 2. Verificar versÃ£o do PHP
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
1. Acesse **ConfiguraÃ§Ãµes â†’ Frontend** (aba "Frontend").
2. Marque `registration_v2` âœ… (mantendo `registration` âœ… ativo).
3. Marque `booking_v2` âœ… (mantendo `booking` âœ… ativo).
4. Salve as configuraÃ§Ãµes.

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

### Etapa 3: Criar PÃ¡ginas V2

1. Crie uma nova pÃ¡gina no WordPress com o shortcode `[dps_registration_v2]`.
2. Crie uma nova pÃ¡gina com o shortcode `[dps_booking_v2]`.
3. Teste ambas as pÃ¡ginas em paralelo com as pÃ¡ginas V1 existentes.
4. **Mantenha as pÃ¡ginas V1 ativas** durante todo o perÃ­odo de testes.

### Etapa 4: Validar IntegraÃ§Ãµes

**Registration â€” Loyalty hooks:**
- Preencha um cadastro V2 e verifique se o campo de indicaÃ§Ã£o do Loyalty estÃ¡ presente.
- Confirme que o hook `dps_after_registration` dispara corretamente (check via debug log).

**Booking â€” 8 add-ons:**
- Crie um agendamento V2 e valide cada integraÃ§Ã£o:

| Add-on | O que verificar |
|--------|-----------------|
| Stock | Estoque decrementado apÃ³s agendamento |
| Payment | TransaÃ§Ã£o criada em `dps_transacoes` |
| Groomers | Groomer atribuÃ­do ao agendamento |
| Calendar | Evento sincronizado |
| Communications | E-mail/SMS enviado |
| Push | NotificaÃ§Ã£o push disparada |
| Services | ServiÃ§os adicionais vinculados |
| Booking | Dados salvos corretamente no post type |

**Telemetria:**
- Acesse **ConfiguraÃ§Ãµes â†’ Frontend** e verifique se os contadores V2 estÃ£o registrando uso.

```bash
# Verificar contadores via WP-CLI
wp option get dps_frontend_usage_counters --format=json
```

### Etapa 5: Migrar Shortcodes

ApÃ³s validaÃ§Ã£o bem-sucedida, migre as pÃ¡ginas de produÃ§Ã£o:

**OpÃ§Ã£o A â€” Trocar shortcodes nas pÃ¡ginas:**
- Edite a pÃ¡gina de cadastro: substitua `[dps_registration_form]` por `[dps_registration_v2]`.
- Edite a pÃ¡gina de agendamento: substitua `[dps_booking_form]` por `[dps_booking_v2]`.

**OpÃ§Ã£o B â€” Trocar feature flags (sem editar pÃ¡ginas):**
- Se cada pÃ¡gina jÃ¡ usa o shortcode V2, basta desabilitar os flags V1 (prÃ³xima etapa).

### Etapa 6: Desabilitar V1

**Via painel administrativo:**
1. Acesse **ConfiguraÃ§Ãµes â†’ Frontend**.
2. Desmarque `registration` âŒ.
3. Desmarque `booking` âŒ.
4. Salve as configuraÃ§Ãµes.

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

> **Importante:** Monitore o site por no mÃ­nimo **48 horas** apÃ³s desabilitar V1.

### Etapa 7: ObservaÃ§Ã£o

- Monitore a **telemetria** por pelo menos 30 dias.
- Confirme que os contadores V1 estÃ£o zerados (nenhum uso residual).
- Verifique que todas as integraÃ§Ãµes dos 8 add-ons continuam funcionando.
- Acompanhe os logs para erros: `grep '\[DPS Frontend\]' wp-content/debug.log`.

```bash
# Verificar telemetria apÃ³s 30 dias
wp option get dps_frontend_usage_counters --format=json
# Esperado: contadores V1 = 0, contadores V2 > 0
```

---

## 5. Plano de Rollback

O rollback Ã© **instantÃ¢neo** e sem perda de dados.

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

Ou via painel: **ConfiguraÃ§Ãµes â†’ Frontend** â†’ desmarcar V2, marcar V1.

### Por que nÃ£o hÃ¡ perda de dados

- V1 e V2 utilizam os **mesmos post types e meta fields**.
- Nenhum dado Ã© migrado ou transformado entre versÃµes.
- Todos os hooks sÃ£o preservados via Hook Bridge.

### Garantias

| Aspecto | Garantia |
|---------|----------|
| Dados de cadastro | Mesmo `post_type`, mesmos `post_meta` |
| Dados de agendamento | Mesmo `post_type`, mesmos `post_meta` |
| Hooks | Hook Bridge mantÃ©m compatibilidade bidirecional |
| Feature flags | AlternÃ¢ncia instantÃ¢nea, sem restart |

---

## 6. Checklist de Compatibilidade

Use esta checklist antes, durante e apÃ³s a migraÃ§Ã£o:

- [ ] WordPress 6.9+ instalado
- [ ] PHP 8.4+ ativo
- [ ] Plugin base `desi-pet-shower-base` ativo e atualizado
- [ ] Frontend add-on `desi-pet-shower-frontend` ativo
- [ ] PÃ¡ginas V1 funcionando normalmente
- [ ] PÃ¡ginas V2 criadas e testadas
- [ ] Loyalty hooks testados (registration)
- [ ] 8 add-ons de booking testados
- [ ] Telemetria V2 registrando contagens
- [ ] Rollback testado (V2 â†’ V1 â†’ V2)
- [ ] 48h de observaÃ§Ã£o sem erros
- [ ] 30 dias de monitoramento planejado

---

## 7. Troubleshooting

### V2 shortcode exibe pÃ¡gina em branco

**Causa provÃ¡vel:** Feature flag V2 nÃ£o estÃ¡ habilitado.

```bash
wp option get dps_frontend_feature_flags --format=json
# Verificar se registration_v2 ou booking_v2 estÃ¡ true
```

**SoluÃ§Ã£o:** Habilitar o flag correspondente via ConfiguraÃ§Ãµes â†’ Frontend ou WP-CLI.

### Hooks nÃ£o disparam apÃ³s agendamento V2

**Causa provÃ¡vel:** Hook Bridge nÃ£o estÃ¡ ativo ou hÃ¡ conflito de prioridade.

```bash
# Verificar se o hook estÃ¡ registrado
wp eval "global \$wp_filter; var_dump(isset(\$wp_filter['dps_base_after_save_appointment']));"
```

**SoluÃ§Ã£o:** Verificar se o Frontend add-on estÃ¡ ativo e atualizado. O Hook Bridge registra callbacks com prioridade especÃ­fica (legado primeiro, V2 depois).

### Estilos DPS Signature nÃ£o carregam no V2

**Causa provÃ¡vel:** Design tokens CSS nÃ£o estÃ¡ enfileirado.

**SoluÃ§Ã£o:** Verificar se `dps-design-tokens.css` estÃ¡ registrado no plugin base. Limpar cache do navegador e de plugins de cache (W3 Total Cache, WP Super Cache, etc.).

### Erros AJAX no booking V2

**Causa provÃ¡vel:** Nonce expirado ou capability insuficiente.

```bash
# Verificar logs de erro
grep 'dps_booking_v2' wp-content/debug.log | tail -20
```

**SoluÃ§Ã£o:** Verificar se o nonce estÃ¡ sendo gerado corretamente na pÃ¡gina. Confirmar que o usuÃ¡rio (ou visitante) tem as capabilities necessÃ¡rias para a aÃ§Ã£o.

### reCAPTCHA v3 falhando no V2

**Causa provÃ¡vel:** Chave do site (site key) nÃ£o configurada ou domÃ­nio nÃ£o autorizado.

**SoluÃ§Ã£o:** Verificar a configuraÃ§Ã£o do reCAPTCHA em **ConfiguraÃ§Ãµes â†’ Frontend**. Confirmar que o domÃ­nio do site estÃ¡ autorizado no painel do Google reCAPTCHA.

### Telemetria V2 nÃ£o registra contagens

**Causa provÃ¡vel:** Option `dps_frontend_usage_counters` nÃ£o existe ou nÃ£o estÃ¡ sendo incrementada.

```bash
wp option get dps_frontend_usage_counters --format=json
```

**SoluÃ§Ã£o:** Verificar se o mÃ³dulo Settings estÃ¡ habilitado (`settings: true`). A telemetria depende do mÃ³dulo Settings ativo.

---

## 8. ConfiguraÃ§Ã£o via WP-CLI

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

| Documento | Caminho | DescriÃ§Ã£o |
|-----------|---------|-----------|
| Guia de Rollout | `docs/implementation/FRONTEND_ROLLOUT_GUIDE.md` | Procedimentos operacionais de ativaÃ§Ã£o por ambiente |
| Runbook de Incidentes | `docs/implementation/FRONTEND_RUNBOOK.md` | DiagnÃ³stico e rollback para incidentes |
| AnÃ¡lise Arquitetural | `ANALYSIS.md` | VisÃ£o completa de contratos, hooks e integraÃ§Ãµes |
| Design Frontend | `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` | InstruÃ§Ãµes de design DPS Signature |
| Guia Visual | `docs/visual/VISUAL_STYLE_GUIDE.md` | Paleta, componentes e espaÃ§amento |
| Changelog | `CHANGELOG.md` | HistÃ³rico de versÃµes e releases |
