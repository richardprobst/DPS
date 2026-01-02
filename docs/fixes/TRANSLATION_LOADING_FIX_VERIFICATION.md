# Verificação da Correção de Carregamento de Traduções

Este documento fornece um checklist de verificação manual para confirmar que a correção do carregamento de text domains está funcionando corretamente no WordPress 6.7.0+.

## Contexto

**Problema corrigido**: PHP Notices sobre text domains sendo carregados muito cedo no WordPress 6.7.0+

**Add-ons afetados**:
- `dps-services-addon` (Services Add-on)
- `dps-loyalty-addon` (Loyalty/Campanhas & Fidelidade)

## Checklist de Verificação

### 1. Verificar ausência de PHP Notices

**Passos**:
1. Ative o modo debug no WordPress:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```

2. Acesse o painel administrativo do WordPress

3. Navegue por páginas que usam os add-ons:
   - Services: `/wp-admin/admin.php?page=desi-pet-shower&tab=servicos`
   - Loyalty: `/wp-admin/admin.php?page=dps-loyalty`

4. Verifique o arquivo de log (`wp-content/debug.log`) e confirme que **não** há mensagens como:
   ```
   PHP Notice: Translation loading for the dps-services-addon domain was triggered too early
   PHP Notice: Translation loading for the dps-loyalty-addon domain was triggered too early
   ```

**Resultado esperado**: ✅ Nenhuma notice sobre text domain loading

---

### 2. Verificar funcionamento do Services Add-on

**Passos**:
1. Acesse `/wp-admin/admin.php?page=desi-pet-shower&tab=servicos`
2. Verifique se a lista de serviços carrega corretamente
3. Tente criar um novo serviço
4. Tente editar um serviço existente
5. Verifique se os serviços aparecem no formulário de agendamento

**Resultado esperado**: ✅ Todas as funcionalidades do Services Add-on funcionam normalmente

---

### 3. Verificar funcionamento do Loyalty Add-on

**Passos**:
1. Acesse `/wp-admin/admin.php?page=dps-loyalty`
2. Verifique se as configurações de fidelidade carregam corretamente
3. Verifique se a lista de clientes com pontos aparece
4. Acesse `/wp-admin/edit.php?post_type=dps_campaign`
5. Verifique se o CPT de campanhas foi registrado corretamente
6. Tente criar uma nova campanha

**Resultado esperado**: ✅ Todas as funcionalidades do Loyalty Add-on funcionam normalmente

---

### 4. Verificar CPT Registration (Custom Post Types)

**Passos**:
1. Acesse `/wp-admin/edit.php?post_type=dps_service`
2. Confirme que o CPT "Serviços" existe e está acessível
3. Acesse `/wp-admin/edit.php?post_type=dps_campaign`
4. Confirme que o CPT "Campanhas" existe e está acessível

**Resultado esperado**: ✅ Ambos os CPTs estão registrados e acessíveis

---

### 5. Verificar Strings Traduzíveis

**Passos**:
1. Instale um plugin de tradução ou arquivo `.mo` de tradução para `dps-services-addon` e `dps-loyalty-addon`
2. Acesse as páginas dos add-ons
3. Verifique se as strings aparecem traduzidas corretamente

**Resultado esperado**: ✅ Strings traduzíveis são carregadas e exibidas corretamente

---

## Detalhes Técnicos da Correção

### Ordem de Execução dos Hooks

**Antes da correção** (causava o erro):
```
1. plugins_loaded (Loyalty) ou file loading (Services): classe instanciada
2. Construtor chama strings traduzíveis (__(), _x(), etc.)
3. init: text domain carregado (TARDE DEMAIS!)
```

**Após a correção**:
```
1. init priority 1: text domain carregado
2. init priority 5: classe instanciada
3. init priority 10: CPT e outros métodos registrados
```

### Arquivos Modificados

1. **Services Add-on**:
   - `plugins/desi-pet-shower-services/desi-pet-shower-services.php`
     - Linha 30: `add_action('init', 'dps_services_load_textdomain', 1)`
   - `plugins/desi-pet-shower-services/dps_service/desi-pet-shower-services-addon.php`
     - Linha 1167: `add_action('init', 'dps_services_addon_init', 5)`

2. **Loyalty Add-on**:
   - `plugins/desi-pet-shower-loyalty/desi-pet-shower-loyalty.php`
     - Linha 25: `add_action('init', 'dps_loyalty_load_textdomain', 1)`
     - Linha 911: `add_action('init', 'dps_loyalty_init', 5)` (antes: `plugins_loaded`)

---

## Referências

- **CHANGELOG.md**: Seção `[Unreleased] > Fixed (Corrigido)`
- **ANALYSIS.md**: Seção "Text Domains para Internacionalização (i18n)"
- **WordPress 6.7 Release Notes**: https://make.wordpress.org/core/2024/11/12/wordpress-6-7-field-guide/

---

## Troubleshooting

### Se ainda houver notices após a correção:

1. **Limpar cache**: Desabilite todos os plugins de cache e limpe o cache do servidor
2. **Verificar versão do WordPress**: Confirme que está usando WordPress 6.7.0 ou superior
3. **Verificar outros add-ons**: Outros add-ons podem ter o mesmo problema. Use este padrão para corrigi-los
4. **Verificar autoloading de classes**: Se usar autoloading, certifique-se que não instancia classes antes do hook `init`

### Pattern para corrigir outros add-ons:

```php
// No arquivo principal do add-on:

/**
 * Carrega o text domain.
 * Priority 1 garante que rode antes da instanciação da classe.
 */
function meu_addon_load_textdomain() {
    load_plugin_textdomain('meu-addon-domain', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('init', 'meu_addon_load_textdomain', 1);

/**
 * Instancia a classe principal.
 * Priority 5 permite que o constructor adicione actions em init:10.
 */
function meu_addon_init() {
    static $instance = null;
    if (null === $instance) {
        $instance = new Meu_Addon();
    }
    return $instance;
}
add_action('init', 'meu_addon_init', 5);
```

---

**Data da Correção**: 2025-11-24
**Versão WordPress Testada**: 6.7.0+
**Impacto**: Correção crítica para WordPress 6.7.0+, sem impacto em versões anteriores
