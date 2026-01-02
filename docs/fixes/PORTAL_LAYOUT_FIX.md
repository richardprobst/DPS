# Correção: Layout Quebrado do Portal do Cliente

## Histórico de Correções

Este documento registra as correções aplicadas ao Portal do Cliente para resolver problemas de layout onde o card aparecia fora da área de conteúdo.

---

## Correção #2: Output Buffering Incorreto (2025-11-22)

### Problema

O card "Portal do Cliente – desi.pet by PRObst" aparecia ANTES do cabeçalho do tema (menu principal), quebrando completamente a estrutura visual da página `/portal-do-cliente/`.

**Sintomas:**
- O box do "Portal do Cliente" aparecia visualmente ANTES do conteúdo da página
- Parecia estar "encaixado no cabeçalho"
- O menu principal do site (tema YOOtheme) aparecia embaixo, como se a estrutura normal do tema tivesse sido "empurrada"
- O card em si estava visualmente correto (título, texto, botão)
- O problema era ONDE ele estava sendo injetado na página
- Não havia fatal errors, apenas notices de tradução

### Causa Raiz

Bug no método `render_portal_shortcode()` em `class-dps-client-portal.php` (linhas 698-723):

```php
// CÓDIGO ERRADO (ANTES):
public function render_portal_shortcode() {
    ob_start();  // ← Inicia buffer
    wp_enqueue_style( 'dps-client-portal' );
    wp_enqueue_script( 'dps-client-portal' );
    
    $client_id = $this->get_authenticated_client_id();
    
    if ( ! $client_id ) {
        $template_path = DPS_CLIENT_PORTAL_ADDON_DIR . 'templates/portal-access.php';
        
        if ( file_exists( $template_path ) ) {
            ob_end_clean();  // ← PROBLEMA: Limpa o buffer!
            include $template_path;  // ← Output vai direto para tela
            return '';  // ← Retorna string VAZIA para shortcode
        }
        // ...
    }
}
```

**Por que quebrava:**
1. `ob_end_clean()` descartava o conteúdo do buffer
2. `include $template_path` imprimia HTML **diretamente no output stream** (não capturado)
3. `return ''` fazia o shortcode retornar string vazia
4. Resultado: HTML era emitido ANTES de `the_content()` finalizar processamento

### Solução Implementada

```php
// CÓDIGO CORRETO (DEPOIS):
public function render_portal_shortcode() {
    wp_enqueue_style( 'dps-client-portal' );  // ← Movido para antes do buffer
    wp_enqueue_script( 'dps-client-portal' );
    
    $client_id = $this->get_authenticated_client_id();
    
    if ( ! $client_id ) {
        $template_path = DPS_CLIENT_PORTAL_ADDON_DIR . 'templates/portal-access.php';
        
        if ( file_exists( $template_path ) ) {
            ob_start();  // ← CORREÇÃO: Inicia novo buffer
            include $template_path;  // ← Output é capturado
            return ob_get_clean();  // ← CORREÇÃO: Retorna conteúdo capturado
        }
        
        // Fallback também com buffer correto
        ob_start();
        echo '<div class="dps-client-portal-login">';
        echo '<h3>' . esc_html__( 'Acesso ao Portal do Cliente', 'dps-client-portal' ) . '</h3>';
        echo '<p>' . esc_html__( 'Para acessar o portal, solicite seu link exclusivo à nossa equipe.', 'dps-client-portal' ) . '</p>';
        echo '</div>';
        return ob_get_clean();
    }
    
    ob_start();  // ← Continua normal para cliente autenticado
    // ...
}
```

### Arquivos Modificados

1. **`add-ons/desi-pet-shower-client-portal_addon/includes/class-dps-client-portal.php`**
   - Linhas 698-725: Refatorado método `render_portal_shortcode()`
   - Mudança principal: `ob_end_clean() + return ''` → `ob_start() + return ob_get_clean()`

### Resultado

✅ **Card do portal renderiza DENTRO da área de conteúdo**  
✅ **Menu do tema permanece no topo** (não é mais empurrado para baixo)  
✅ **Ordem correta**: Header → Conteúdo (com card do portal) → Footer  
✅ **Shortcode retorna HTML corretamente** para `the_content()` processar

### Fluxo Corrigido

```
[Tema abre: <html><head>...</head><body>]
  [Header do tema]                     ← NO TOPO
  [Menu principal]                     ← POSIÇÃO CORRETA
  [Área de conteúdo inicia]
    [WordPress processa the_content()]
      [Shortcode dps_client_portal:]
        ob_start()
        include portal-access.php     ← Capturado no buffer
        return ob_get_clean()         ← Retornado para shortcode
      [WordPress insere HTML no lugar do shortcode]
    [Área de conteúdo termina]
  [Footer do tema]                     ← NO FINAL CORRETO
[Tema fecha: </body></html>]
```

---

## Correção #1: Documento HTML Completo no Template (Data anterior)

O Portal do Cliente estava com layout quebrado no front-end quando acessado via página `/portal-do-cliente/`.

**Sintomas:**
- O card do "Portal do Cliente – desi.pet by PRObst" aparecia à esquerda
- O resto da área de conteúdo ficava branca
- O menu principal do site (tema YOOtheme) aparecia lá embaixo, como se a estrutura da página estivesse quebrada

## Causa Raiz

O template `add-ons/desi-pet-shower-client-portal_addon/templates/portal-access.php` continha um **documento HTML COMPLETO** com:

```html
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal do Cliente – desi.pet by PRObst</title>
    <?php wp_head(); ?>
</head>
<body class="dps-portal-access-page">
    <!-- Conteúdo do portal -->
    <?php wp_footer(); ?>
</body>
</html>
```

Quando o shortcode `[dps_client_portal]` era renderizado em uma página WordPress:

1. **Duplicação de estrutura**: O tema YOOtheme já fornecia `<html>`, `<head>`, `<body>` etc.
2. **Fechamento prematuro**: As tags `</body>` e `</html>` do template fechavam a estrutura do tema antes do tempo
3. **Conteúdo órfão**: Todo o resto da página (menu do tema, footer) ficava fora da estrutura HTML válida

### Fluxo problemático

```
[Tema abre: <html><head>...</head><body>]
  [Header do tema]
  [Área de conteúdo inicia]
    [Shortcode dps_client_portal renderiza:]
      <!DOCTYPE html>
      <html><head>...</head><body>
        [Card do portal]
      </body></html>   ← FECHA PREMATURAMENTE A ESTRUTURA DO TEMA!
    [Fim do shortcode]
  [Área de conteúdo termina]
  [Footer do tema]    ← FICA ÓRFÃO FORA DO HTML VÁLIDO
[Tema fecha: </body></html>]
```

## Solução Implementada

### 1. Transformar template em fragmento HTML

**ANTES** (`portal-access.php`):
```html
<!DOCTYPE html>
<html>
<head>
    <?php wp_head(); ?>
</head>
<body class="dps-portal-access-page">
    <div class="dps-portal-access">
        <!-- conteúdo -->
    </div>
    <style>
        /* CSS inline */
    </style>
    <?php wp_footer(); ?>
</body>
</html>
```

**DEPOIS** (`portal-access.php`):
```html
<?php
// Apenas fragmento HTML, sem estrutura completa
?>
<div class="dps-client-portal-access-page">
    <div class="dps-portal-access">
        <div class="dps-portal-access__card">
            <!-- conteúdo -->
        </div>
    </div>
</div>
```

### 2. Mover estilos inline para CSS externo

**ANTES**: Estilos inline dentro de `<style>` no template, incluindo:
```css
body.dps-portal-access-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
}
```

**DEPOIS**: Estilos movidos para `assets/css/client-portal.css`, sem afetar elementos globais:
```css
.dps-client-portal-access-page {
    max-width: 480px;
    margin: 40px auto;
    padding: 0 20px;
}

.dps-portal-access {
    width: 100%;
}

.dps-portal-access__card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 32px;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
}
```

### 3. Ajustar hierarquia de títulos

- Mudado de `<h2>` para `<h1>` no título principal "Portal do Cliente – desi.pet by PRObst"
- Mantém semântica correta pois é o título principal do conteúdo do shortcode

## Arquivos Modificados

1. **`add-ons/desi-pet-shower-client-portal_addon/templates/portal-access.php`**
   - Removidas tags: `<!DOCTYPE>`, `<html>`, `<head>`, `<body>` e fechamentos
   - Removidas chamadas: `wp_head()`, `wp_footer()`
   - Removido bloco `<style>` inline
   - Adicionado wrapper `.dps-client-portal-access-page`
   - Alterado `<h2>` para `<h1>` no título principal

2. **`add-ons/desi-pet-shower-client-portal_addon/assets/css/client-portal.css`**
   - Adicionada seção "TELA DE ACESSO (Portal Access Screen)" no topo
   - Estilos para `.dps-client-portal-access-page` e elementos filhos
   - Estilos responsivos para mobile (@media max-width: 782px)
   - CSS não afeta elementos globais (`html`, `body`, `main`)

## Resultado

✅ **Card do portal aparece corretamente** dentro da área de conteúdo do tema  
✅ **Menu do tema permanece no topo** (não é mais empurrado para baixo)  
✅ **Layout do tema intacto** (header, conteúdo, footer na ordem correta)  
✅ **Estrutura HTML válida** (sem duplicação de tags)  
✅ **CSS isolado** (não interfere com elementos globais do tema)

### Fluxo corrigido

```
[Tema abre: <html><head>...</head><body>]
  [Header do tema]                     ← NO TOPO
  [Área de conteúdo inicia]
    [Shortcode dps_client_portal renderiza:]
      <div class="dps-client-portal-access-page">
        [Card do portal]
      </div>                           ← APENAS FRAGMENTO
    [Fim do shortcode]
  [Área de conteúdo termina]
  [Footer do tema]                     ← NO FINAL CORRETO
[Tema fecha: </body></html>]
```

## Lições Aprendidas

1. **Templates para shortcodes NUNCA devem incluir estrutura HTML completa**
   - `<!DOCTYPE>`, `<html>`, `<head>`, `<body>` são responsabilidade do tema
   - Shortcodes devem retornar apenas fragmentos HTML

2. **Evitar `wp_head()` e `wp_footer()` em templates de shortcode**
   - Essas funções são chamadas pelo tema em `header.php` e `footer.php`
   - Chamá-las em shortcode causa duplicação e quebra de hooks

3. **CSS inline em templates deve ser evitado**
   - Sempre usar arquivos CSS externos registrados corretamente
   - Facilita manutenção e reutilização de estilos

4. **Não manipular elementos globais do tema no CSS de plugins**
   - Regras como `body { display: flex; min-height: 100vh; }` quebram layouts
   - Sempre usar wrappers específicos do plugin (`.dps-*`)

## Referências

- Commit: d596a76
- Arquivos alterados: 2
- Linhas adicionadas: 230
- Linhas removidas: 232
- Issue: Layout quebrado do Portal do Cliente no front-end
