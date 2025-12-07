# Melhorias de UX - AI Add-on v1.6.1

**Data:** 07/12/2024  
**Commit:** 9b9fd67  
**Recursos Adicionados:** Toggle API Key + Destaque Modelo Selecionado

---

## 1. Toggle de Visibilidade da API Key

### Antes

```
┌─────────────────────────────────────────────────────────┐
│ Chave de API da OpenAI                                  │
├─────────────────────────────────────────────────────────┤
│ [••••••••••••••••••••••] [Testar Conexão]               │
│ Token de autenticação da API da OpenAI (sk-...)         │
└─────────────────────────────────────────────────────────┘
```

**Problema:** Usuário não conseguia conferir se digitou a chave corretamente.

### Depois

```
┌─────────────────────────────────────────────────────────┐
│ Chave de API da OpenAI                                  │
├─────────────────────────────────────────────────────────┤
│ [••••••••••••••••••••••][👁] [Testar Conexão]           │
│ Token de autenticação da API da OpenAI (sk-...)         │
└─────────────────────────────────────────────────────────┘
```

**Ao clicar no ícone de olho:**

```
┌─────────────────────────────────────────────────────────┐
│ Chave de API da OpenAI                                  │
├─────────────────────────────────────────────────────────┤
│ [sk-proj-abc123xyz789][🚫] [Testar Conexão]             │
│ Token de autenticação da API da OpenAI (sk-...)         │
└─────────────────────────────────────────────────────────┘
```

### Implementação

**HTML:**
```html
<div style="position: relative; display: inline-block;">
    <input type="password" id="dps_ai_api_key" 
           style="padding-right: 40px;" />
    
    <button type="button" id="dps_ai_toggle_api_key" 
            style="position: absolute; right: 2px; top: 50%; 
                   transform: translateY(-50%); width: 32px; 
                   height: 28px; background: transparent;">
        <span class="dashicons dashicons-visibility"></span>
    </button>
</div>
```

**JavaScript:**
```javascript
$('#dps_ai_toggle_api_key').on('click', function(e) {
    e.preventDefault();
    
    var $input = $('#dps_ai_api_key');
    var $icon = $(this).find('.dashicons');
    
    if ($input.attr('type') === 'password') {
        // Mostrar
        $input.attr('type', 'text');
        $icon.removeClass('dashicons-visibility')
             .addClass('dashicons-hidden');
        $(this).attr('title', 'Ocultar API Key');
    } else {
        // Ocultar
        $input.attr('type', 'password');
        $icon.removeClass('dashicons-hidden')
             .addClass('dashicons-visibility');
        $(this).attr('title', 'Mostrar API Key');
    }
});
```

### Características

✅ **Usa Dashicons nativos** - Sem ícones externos ou SVG customizado  
✅ **Posicionamento absoluto** - Botão dentro do campo, não quebra layout  
✅ **Tooltip dinâmico** - "Mostrar API Key" / "Ocultar API Key"  
✅ **Responsivo** - Funciona em desktop e mobile  
✅ **Acessível** - Atributo `title` descritivo  

---

## 2. Destaque do Modelo Selecionado na Tabela de Custos

### Antes

```
┌──────────────────────────────────────────────────────────┐
│ Custos Estimados (OpenAI)                                │
├─────────────┬──────────────────┬─────────────────────────┤
│ Modelo      │ Custo por Perg.  │ Recomendação            │
├─────────────┼──────────────────┼─────────────────────────┤
│ GPT-4o Mini │ ~$0.0003         │ Recomendado             │
│ GPT-4o      │ ~$0.005          │ Alta precisão           │
│ GPT-4 Turbo │ ~$0.01           │ Máxima precisão         │
│ GPT-3.5     │ ~$0.001          │ Legado                  │
└─────────────┴──────────────────┴─────────────────────────┘
```

**Problema:** Não ficava claro visualmente qual modelo estava em uso.

### Depois (com GPT-4o selecionado)

```
┌──────────────────────────────────────────────────────────────────┐
│ Custos Estimados (OpenAI)                                        │
├─────────────┬──────────────────┬─────────────────┬───────────────┤
│ Modelo      │ Custo por Perg.  │ Recomendação    │ Status        │
├─────────────┼──────────────────┼─────────────────┼───────────────┤
│ GPT-4o Mini │ ~$0.0003         │ Recomendado     │               │
├─────────────┼──────────────────┼─────────────────┼───────────────┤
║ GPT-4o      │ ~$0.005          │ Alta precisão   │ ✓ Modelo Ativo║ ← Background azul
║             │                  │                 │               ║    Borda azul
├─────────────┼──────────────────┼─────────────────┼───────────────┤
│ GPT-4 Turbo │ ~$0.01           │ Máxima precisão │               │
│ GPT-3.5     │ ~$0.001          │ Legado          │               │
└─────────────┴──────────────────┴─────────────────┴───────────────┘
```

### Implementação

**PHP:**
```php
<?php
// Obtém o modelo atualmente selecionado
$selected_model = $options['model'] ?? 'gpt-4o-mini';
?>

<table class="widefat" style="max-width: 700px;">
    <thead>
        <tr>
            <th>Modelo</th>
            <th>Custo Aprox. por Pergunta</th>
            <th>Recomendação</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <!-- Linha para GPT-4o -->
        <tr<?php echo ('gpt-4o' === $selected_model) ? 
            ' style="background-color: #e0f2fe; border-left: 4px solid #0ea5e9;"' : 
            ''; ?>>
            <td><strong>GPT-4o</strong></td>
            <td>~$0.005</td>
            <td>Alta precisão</td>
            <td>
                <?php if ('gpt-4o' === $selected_model) : ?>
                    <span style="display: inline-flex; align-items: center; 
                                 gap: 4px; padding: 2px 8px; 
                                 background: #0ea5e9; color: #fff; 
                                 border-radius: 3px; font-size: 11px; 
                                 font-weight: 600;">
                        <span class="dashicons dashicons-yes-alt" 
                              style="font-size: 14px; width: 14px; 
                                     height: 14px; line-height: 14px;">
                        </span>
                        Modelo Ativo
                    </span>
                <?php endif; ?>
            </td>
        </tr>
        
        <!-- Repetir para outros modelos -->
    </tbody>
</table>
```

### Características

✅ **Destaque visual triplo:**
- Background azul claro (#e0f2fe)
- Borda lateral azul (#0ea5e9, 4px)
- Badge "Modelo Ativo" com ícone checkmark

✅ **Acessível:**
- Texto explícito "Modelo Ativo" (não só cor)
- Dashicon `dashicons-yes-alt` para reforço visual

✅ **Dinâmico:**
- Atualiza automaticamente quando modelo é alterado
- Sem necessidade de JavaScript

✅ **Expansível:**
- Nova coluna "Status" pode receber outras informações futuras
- Estrutura da tabela preservada

---

## Cores e Estilos Utilizados

### Paleta de Cores

| Elemento | Cor | Uso |
|----------|-----|-----|
| Background destaque | `#e0f2fe` | Linha do modelo ativo (azul muito claro) |
| Borda destaque | `#0ea5e9` | Borda lateral de 4px (azul médio) |
| Badge background | `#0ea5e9` | Fundo do badge "Modelo Ativo" |
| Badge texto | `#fff` | Texto branco para contraste |
| Ícone toggle | `#666` | Cinza médio (padrão WP Admin) |

### Dashicons Utilizados

| Ícone | Classe | Uso |
|-------|--------|-----|
| 👁 | `dashicons-visibility` | API Key oculta (padrão) |
| 🚫 | `dashicons-hidden` | API Key visível |
| ✓ | `dashicons-yes-alt` | Badge "Modelo Ativo" |

---

## Compatibilidade

### Desktop
- ✅ Chrome/Edge/Firefox/Safari
- ✅ Resolução >= 1024px

### Mobile
- ✅ Responsivo (flexbox com wrap)
- ✅ Touch-friendly (botão 32x28px)
- ✅ Resolução >= 320px

### WordPress
- ✅ WordPress 6.0+
- ✅ Dashicons nativos
- ✅ jQuery incluído por padrão

---

## Exemplos de Uso

### Cenário 1: Usuário Configurando pela Primeira Vez

1. Usuário acessa **Assistente de IA** > **Configurações**
2. Digita API Key no campo (aparece como `••••••••`)
3. Clica no ícone de olho para conferir se digitou certo
4. API Key fica visível temporariamente
5. Clica novamente para ocultar
6. Seleciona modelo desejado no dropdown
7. **Salva configurações**
8. Ao recarregar a página, tabela de custos destaca o modelo escolhido

### Cenário 2: Admin Consultando Custos

1. Admin acessa página de configurações
2. Verifica tabela de custos
3. **Imediatamente identifica** qual modelo está ativo pela linha destacada
4. Compara custos dos outros modelos
5. Decide se mantém ou altera modelo

---

## Fluxo Visual

```
┌─────────────────────────────────────────┐
│ 1. CAMPO API KEY                        │
│    [••••••••••][👁] ← Clique            │
└──────────────────┬──────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────┐
│ 2. TOGGLE JAVASCRIPT                    │
│    • Detecta clique                     │
│    • Altera type="password" → "text"    │
│    • Troca ícone 👁 → 🚫                 │
└──────────────────┬──────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────┐
│ 3. API KEY VISÍVEL                      │
│    [sk-proj-abc123][🚫] ← Clique       │
└──────────────────┬──────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────┐
│ 4. VOLTA PARA OCULTO                    │
│    [••••••••••][👁]                     │
└─────────────────────────────────────────┘
```

---

## Código Completo de Referência

### HTML da API Key

```html
<tr>
    <th scope="row">
        <label for="dps_ai_api_key">Chave de API da OpenAI</label>
    </th>
    <td>
        <div style="display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap;">
            <div style="position: relative; display: inline-block;">
                <input type="password" 
                       id="dps_ai_api_key" 
                       name="dps_ai_settings[api_key]" 
                       value="<?php echo esc_attr($options['api_key'] ?? ''); ?>" 
                       class="regular-text" 
                       style="padding-right: 40px;" />
                
                <button type="button" 
                        id="dps_ai_toggle_api_key" 
                        class="button" 
                        style="position: absolute; right: 2px; top: 50%; 
                               transform: translateY(-50%); padding: 0; 
                               width: 32px; height: 28px; border: none; 
                               background: transparent; cursor: pointer;" 
                        title="Mostrar/Ocultar API Key">
                    <span class="dashicons dashicons-visibility" 
                          style="line-height: 28px; width: 32px; 
                                 height: 28px; font-size: 18px; color: #666;">
                    </span>
                </button>
            </div>
            
            <button type="button" id="dps_ai_test_connection" class="button">
                Testar Conexão
            </button>
            
            <span id="dps_ai_test_result" style="display: none;"></span>
        </div>
        
        <p class="description">
            Token de autenticação da API da OpenAI (sk-...). Mantenha em segredo.
        </p>
    </td>
</tr>
```

### JavaScript do Toggle

```javascript
(function($) {
    // Toggle API Key visibility
    $('#dps_ai_toggle_api_key').on('click', function(e) {
        e.preventDefault();
        
        var $input = $('#dps_ai_api_key');
        var $icon = $(this).find('.dashicons');
        
        if ($input.attr('type') === 'password') {
            // Mostrar API Key
            $input.attr('type', 'text');
            $icon.removeClass('dashicons-visibility')
                 .addClass('dashicons-hidden');
            $(this).attr('title', 'Ocultar API Key');
        } else {
            // Ocultar API Key
            $input.attr('type', 'password');
            $icon.removeClass('dashicons-hidden')
                 .addClass('dashicons-visibility');
            $(this).attr('title', 'Mostrar API Key');
        }
    });
})(jQuery);
```

### PHP da Tabela de Custos

```php
<?php
$selected_model = $options['model'] ?? 'gpt-4o-mini';
?>

<h2>Custos Estimados (OpenAI)</h2>
<table class="widefat" style="max-width: 700px;">
    <thead>
        <tr>
            <th>Modelo</th>
            <th>Custo Aprox. por Pergunta</th>
            <th>Recomendação</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <tr<?php echo ('gpt-4o-mini' === $selected_model) ? 
            ' style="background-color: #e0f2fe; border-left: 4px solid #0ea5e9;"' : 
            ''; ?>>
            <td><strong>GPT-4o Mini</strong></td>
            <td>~$0.0003</td>
            <td><strong>Recomendado</strong></td>
            <td>
                <?php if ('gpt-4o-mini' === $selected_model) : ?>
                    <span style="display: inline-flex; align-items: center; 
                                 gap: 4px; padding: 2px 8px; 
                                 background: #0ea5e9; color: #fff; 
                                 border-radius: 3px; font-size: 11px; 
                                 font-weight: 600;">
                        <span class="dashicons dashicons-yes-alt" 
                              style="font-size: 14px; width: 14px; 
                                     height: 14px; line-height: 14px;">
                        </span>
                        Modelo Ativo
                    </span>
                <?php endif; ?>
            </td>
        </tr>
        
        <!-- Repetir para GPT-4o, GPT-4 Turbo, GPT-3.5 Turbo -->
    </tbody>
</table>
```

---

## Benefícios

### Para o Usuário

1. **Facilita conferência da API Key** - Pode ver se digitou corretamente sem precisar reenviar
2. **Identifica rapidamente o modelo ativo** - Não precisa procurar na tabela
3. **Reduz erros de configuração** - Visual claro previne confusões
4. **Melhora confiança** - Interface mais profissional e polida

### Para o Desenvolvedor

1. **Código limpo e manutenível** - Usa recursos nativos do WordPress
2. **Sem dependências externas** - Apenas jQuery e Dashicons (já incluídos)
3. **Fácil de estender** - Estrutura permite adicionar mais informações
4. **Acessível** - Segue padrões WCAG

---

## Resumo Técnico

| Aspecto | Implementação |
|---------|---------------|
| **Toggle API Key** | Input password + button absoluto + JS toggle type |
| **Ícones** | Dashicons nativos (visibility/hidden) |
| **Destaque Modelo** | Background + borda + badge com ícone |
| **Responsividade** | Flexbox com wrap, touch-friendly |
| **Acessibilidade** | Texto + cor, tooltips, ARIA implícito |
| **Performance** | Sem impacto (CSS inline, JS simples) |
| **Compatibilidade** | WP 6.0+, navegadores modernos |

---

**Implementado em:** 07/12/2024  
**Commit:** 9b9fd67  
**Arquivos modificados:** 2  
**Linhas adicionadas:** +83  
**Linhas removidas:** -14
