# Solução de Problemas: Formulário de Consentimento de Tosa

**Data:** 02/02/2026  
**Versão do plugin:** 1.2.3  
**PR relacionadas:** #518, #524, #526

## Situação Atual

O formulário de "Consentimento Permanente • Tosa na Máquina" **JÁ ESTÁ ATUALIZADO** com o design reformulado da PR #518. 

### ✅ Criação Automática da Página (v1.2.3)

A partir da versão 1.2.3, a página de consentimento é **criada automaticamente**:

1. **Na ativação do plugin**: O sistema cria a página `/consentimento-tosa-maquina/` com o shortcode correto
2. **Ao gerar um link**: Se a página não existir, ela é criada automaticamente quando o administrador gera o primeiro link
3. **Recuperação automática**: Se a página for deletada ou perder o shortcode, o sistema corrige automaticamente

**Isso significa que não é mais necessário criar manualmente a página!**

### Arquivos Atualizados

✅ **Template PHP:** `plugins/desi-pet-shower-base/templates/tosa-consent-form.php`  
✅ **CSS:** `plugins/desi-pet-shower-base/assets/css/tosa-consent-form.css`  
✅ **Classe de controle:** `plugins/desi-pet-shower-base/includes/class-dps-tosa-consent.php`

Todos os arquivos contêm a versão mais recente do formulário com:
- Layout moderno e responsivo
- Cards com destaque visual (`dps-consent-card--important`, `dps-consent-card--signature`)
- Aviso de permanência do consentimento
- Grid de pets melhorado
- Seções de termos bem organizadas
- Assinatura digital com informações detalhadas

## Se o Formulário Ainda Aparece Diferente

Se você ainda vê um formulário com aparência diferente da demo em `docs/forms/tosa-consent-form-demo.html`, siga este guia de diagnóstico:

### Passo 1: Verificar Cache

O problema mais comum é cache desatualizado.

#### Cache do Navegador
1. Abra o navegador
2. Pressione **Ctrl+Shift+R** (Windows/Linux) ou **Cmd+Shift+R** (Mac) na página do formulário
3. Isso força o navegador a baixar todos os arquivos novamente

#### Cache do WordPress
Se você usa plugins de cache:
- **WP Super Cache:** Dashboard → WP Super Cache → Delete Cache
- **W3 Total Cache:** Performance → Dashboard → Empty All Caches
- **WP Rocket:** Settings → Clear Cache
- **LiteSpeed Cache:** LiteSpeed Cache → Purge All

#### Cache de CDN
Se você usa Cloudflare, Bunny CDN ou similar:
1. Acesse o painel de controle do CDN
2. Localize a opção "Purge Cache" ou "Clear Cache"
3. Limpe o cache completamente

### Passo 2: Verificar Override do Tema

Mesmo com a proteção implementada na PR #524, é recomendável verificar se há arquivos antigos no tema.

#### Localizar Override
Verifique se existem estes arquivos:
```
wp-content/themes/SEU-TEMA/dps-templates/tosa-consent-form.php
wp-content/themes/SEU-TEMA-FILHO/dps-templates/tosa-consent-form.php
```

#### Como Verificar via FTP/SSH
```bash
# SSH
cd /caminho/para/wordpress
find wp-content/themes -name "tosa-consent-form.php"

# Se encontrar arquivos, remova-os:
rm wp-content/themes/SEU-TEMA/dps-templates/tosa-consent-form.php
```

#### Script de Diagnóstico Automatizado
Crie um arquivo `check-theme-override.php` na raiz do WordPress com o conteúdo de:
```
docs/forms/diagnostic-script/check-theme-override.php
```

Acesse: `https://seusite.com/check-theme-override.php`

O script irá:
- ✅ Detectar se há override do tema
- ✅ Mostrar qual arquivo está sendo usado
- ✅ Fornecer instruções específicas para resolver
- ✅ Verificar configuração do sistema

**⚠️ IMPORTANTE:** Remova o arquivo após o diagnóstico por segurança!

### Passo 3: Verificar Carregamento do CSS

O CSS pode não estar sendo carregado corretamente.

#### Inspeção no Navegador
1. Abra o formulário no navegador
2. Pressione **F12** para abrir o DevTools
3. Vá na aba **Network** (Rede)
4. Recarregue a página (F5)
5. Procure por: `tosa-consent-form.css`
6. Verifique:
   - ✅ Status: deve ser **200** (verde)
   - ✅ Size: deve ser ~16KB
   - ✅ Versão: deve ter `?ver=` com timestamp recente

#### CSS Não Está Carregando?
Se o CSS não aparecer:

1. Verifique se o shortcode está correto na página:
   ```
   [dps_tosa_consent]
   ```

2. Verifique permissões do arquivo:
   ```bash
   chmod 644 plugins/desi-pet-shower-base/assets/css/tosa-consent-form.css
   ```

3. Verifique se o arquivo existe:
   ```bash
   ls -lh plugins/desi-pet-shower-base/assets/css/tosa-consent-form.css
   # Deve mostrar ~16KB
   ```

### Passo 4: Verificar Conflitos de CSS

Alguns temas ou plugins podem sobrescrever estilos.

#### Como Identificar
1. Abra o formulário
2. Pressione **F12** → **Elements** (Elementos)
3. Clique em qualquer elemento do formulário
4. Na aba **Styles** (Estilos), verifique:
   - Se há estilos de `tosa-consent-form.css`
   - Se há outros arquivos CSS sobrescrevendo

#### Solução
Se houver conflitos, adicione este código ao `functions.php` do tema:
```php
add_action( 'wp_enqueue_scripts', function() {
    if ( is_singular() && has_shortcode( get_post()->post_content, 'dps_tosa_consent' ) ) {
        wp_enqueue_style(
            'dps-tosa-consent-form',
            plugins_url( 'desi-pet-shower-base/assets/css/tosa-consent-form.css' ),
            [],
            filemtime( WP_PLUGIN_DIR . '/desi-pet-shower-base/assets/css/tosa-consent-form.css' )
        );
    }
}, 999 ); // Prioridade alta para carregar por último
```

### Passo 5: Modo de Depuração

Ative o modo debug do WordPress para ver mensagens de erro.

#### wp-config.php
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

Depois, verifique o arquivo:
```
wp-content/debug.log
```

## Comparação: Formulário Antigo vs Novo

### Formulário ANTIGO (Pré-PR #518)
❌ Layout básico sem destaque visual  
❌ Cards sem bordas coloridas  
❌ Sem aviso de permanência destacado  
❌ Lista de pets simples  
❌ Seções de termos sem hierarquia visual  
❌ Assinatura digital básica  

### Formulário NOVO (PR #518)
✅ Layout moderno com gradientes sutis  
✅ Cards com bordas coloridas (amarelo para termos, azul para assinatura)  
✅ Badge azul destacando consentimento permanente  
✅ Grid de pets com tags e emojis  
✅ Seções de termos bem organizadas com ícones  
✅ Assinatura digital com informações detalhadas  
✅ Responsivo para mobile  
✅ Animações suaves  

### Classes CSS Exclusivas do Novo Design
Se essas classes estiverem presentes no HTML, é o formulário novo:
- `dps-consent-permanent-notice`
- `dps-consent-card--important`
- `dps-consent-card--signature`
- `dps-consent-pet-card`
- `dps-consent-terms-section`

## Proteção Contra Override (PR #524)

A PR #524 implementou um sistema de proteção que **força o uso do template do plugin** por padrão:

```php
// Em class-dps-tosa-consent.php
public function force_consent_template( $use_plugin, $template_name ) {
    if ( 'tosa-consent-form.php' !== $template_name ) {
        return $use_plugin;
    }
    
    // Permite override apenas se explicitamente habilitado
    $allow_theme_override = apply_filters( 'dps_allow_consent_template_override', false );
    
    if ( $allow_theme_override ) {
        return false; // Permite tema sobrescrever
    }
    
    // Força uso do plugin
    return true;
}
```

### Como Permitir Override do Tema (Não Recomendado)
Se você **realmente** precisa que o tema sobrescreva o template:

```php
// functions.php do tema
add_filter( 'dps_allow_consent_template_override', '__return_true' );
```

⚠️ **ATENÇÃO:** Isso desativa a proteção e você será responsável por manter o template do tema atualizado.

## Verificação Final

Para confirmar que está tudo certo:

1. ✅ Abra o formulário via link de consentimento
2. ✅ Verifique se há:
   - Badge azul "Este é um consentimento permanente..."
   - Cards com bordas coloridas (amarelo e azul)
   - Grid de pets com emojis e tags
   - Seções de termos bem organizadas
3. ✅ Inspecione o HTML (F12) e confirme as classes CSS listadas acima
4. ✅ Verifique se o CSS está carregando (Network tab)

## Suporte

Se após seguir todos os passos o problema persistir:

1. **Documente**:
   - Screenshot do formulário atual
   - Screenshot do console do navegador (F12 → Console)
   - Screenshot da aba Network mostrando requisições
   - Resultado do script de diagnóstico

2. **Abra uma issue** no GitHub com as informações coletadas

3. **Inclua**:
   - Versão do WordPress
   - Versão do plugin DPS Base
   - Tema ativo (nome e versão)
   - Plugins de cache ativos
   - URL do site (se possível)

## Histórico de Mudanças

| Versão | PR | Data | Descrição |
|--------|-----|------|-----------|
| 1.1.1 | #518 | 2026-01-XX | Formulário redesenhado com layout moderno |
| 1.2.2 | #524 | 2026-02-02 | Proteção contra override do tema + diagnóstico |

## Referências

- **Demo HTML:** `docs/forms/tosa-consent-form-demo.html`
- **Template PHP:** `plugins/desi-pet-shower-base/templates/tosa-consent-form.php`
- **CSS:** `plugins/desi-pet-shower-base/assets/css/tosa-consent-form.css`
- **Classe:** `plugins/desi-pet-shower-base/includes/class-dps-tosa-consent.php`
- **CHANGELOG:** Seção "Formulário de Consentimento de Tosa não exibindo versão atualizada"
