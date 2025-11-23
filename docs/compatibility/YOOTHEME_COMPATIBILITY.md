# Compatibilidade com YooTheme PRO

## Problema Comum: "O construtor não está disponível nesta página"

### Descrição do Problema

Ao criar uma página no WordPress com o tema YooTheme PRO e tentar inserir o shortcode `[dps_base]`, alguns usuários podem ver a mensagem de erro:

> "O construtor não está disponível nesta página. Ele só pode ser usado em páginas, posts e categorias."

### Causa

**Esta mensagem NÃO vem do plugin DPS** - ela é gerada pelo próprio YooTheme PRO quando o construtor visual detecta uma situação onde ele não pode operar.

O problema ocorre tipicamente em uma das seguintes situações:

1. **Tentativa de editar no Builder do YooTheme**: O YooTheme PRO pode ter restrições sobre quais tipos de página podem ser editadas no modo visual
2. **Configuração do template**: A página pode estar usando um template customizado que o YooTheme não reconhece
3. **Conflito de contexto**: O builder pode detectar algo que o faz pensar que não está numa página editável

## Solução: Como Usar o Shortcode [dps_base] com YooTheme PRO

### Método 1: Adicionar Shortcode no Editor de Código (RECOMENDADO)

1. **Criar a Página**:
   - Vá em WordPress Admin → Páginas → Adicionar Nova
   - Dê um nome à página (ex: "Painel DPS")

2. **Adicionar o Shortcode**:
   - **NÃO use o builder visual do YooTheme**
   - Clique no botão "Código" ou "HTML" no editor
   - OU use o Editor Clássico do WordPress
   - Insira apenas: `[dps_base]`

3. **Publicar**:
   - Clique em "Publicar"
   - Acesse a página no front-end para ver o painel funcionando

### Método 2: Usar Elemento de Shortcode do YooTheme

Se você precisar usar o builder do YooTheme:

1. **No Builder do YooTheme**:
   - Adicione um elemento "HTML" ou "Shortcode" (se disponível)
   - Dentro desse elemento, insira: `[dps_base]`

2. **Configurações de Layout**:
   - Configure o elemento para largura total (100%)
   - Remova padding/margin se necessário
   - Salve e publique

### Método 3: Usar Template Personalizado

Para controle total:

1. **Criar Template PHP Customizado** (avançado):
   ```php
   <?php
   /**
    * Template Name: DPS Panel
    */
   get_header();
   ?>
   <div class="dps-panel-container">
       <?php echo do_shortcode('[dps_base]'); ?>
   </div>
   <?php
   get_footer();
   ```

2. **Aplicar à Página**:
   - Selecione "DPS Panel" como template da página
   - O shortcode será renderizado automaticamente

## Verificação de Funcionamento

### O Shortcode Está Funcionando Corretamente?

Para verificar se o problema é só no editor ou se afeta também o front-end:

1. **Acesse a página publicada no front-end** (não no admin)
2. **Verifique se o painel DPS aparece corretamente**
3. **Teste as funcionalidades** (abas, formulários, etc.)

**Se funcionar no front-end mas não no editor do YooTheme**: O problema é apenas visual/UX do builder. Você pode continuar usando o método de código/HTML para editar.

**Se não funcionar nem no front-end**: Pode haver outro problema. Veja seção de troubleshooting abaixo.

## Troubleshooting

### 1. Página em Branco ou Sem Conteúdo

**Causa**: Permissões de usuário ou página não publicada

**Solução**:
- Verifique se você está logado como Administrador
- Certifique-se de que a página está publicada (não rascunho)
- O shortcode `[dps_base]` requer permissões de `manage_options`

### 2. CSS/Layout Quebrado

**Causa**: Conflito de estilos entre YooTheme e DPS

**Solução**:
```css
/* Adicionar em Appearance → Customizar → CSS Adicional */
.dps-base-wrapper {
    clear: both;
    width: 100%;
    max-width: 100%;
}
```

### 3. JavaScript Não Funciona

**Causa**: Scripts não sendo carregados

**Solução**:
- Verifique se o shortcode está exatamente como `[dps_base]` (sem espaços extras)
- Limpe cache do site e do browser
- Desative temporariamente outros plugins para testar conflitos

### 4. Builder do YooTheme Continua Bloqueando

**Workaround**:
- Use o **Editor Clássico do WordPress** em vez do builder do YooTheme para esta página específica
- Ou crie a página via código PHP (template customizado)
- O importante é que funcione no front-end para os usuários finais

## Configurações Recomendadas para YooTheme PRO

### Layout da Página

Ao configurar a página no YooTheme:

1. **Layout**: Largura Total (Full Width)
2. **Sidebar**: Nenhuma
3. **Header/Footer**: Manter padrão do tema
4. **Padding**: Remover padding interno para dar mais espaço ao painel

### CSS Customizado (Opcional)

Para melhor integração visual:

```css
/* CSS específico para página do DPS */
.page-template-default .dps-base-wrapper {
    margin: 0;
    padding: 20px;
}

/* Ajustar navegação de abas */
.dps-nav {
    margin-bottom: 20px;
}

/* Responsividade */
@media (max-width: 768px) {
    .dps-base-wrapper {
        padding: 10px;
    }
}
```

## Limitações Conhecidas

### O Que NÃO é Suportado

1. **Edição Visual no Builder**: O conteúdo do shortcode `[dps_base]` não pode ser editado visualmente no builder do YooTheme (é renderizado dinamicamente)

2. **Preview em Tempo Real**: Mudanças no DPS não aparecerão no preview do builder (precisa acessar front-end)

3. **Elementos do YooTheme Dentro do DPS**: Não é possível usar elementos do builder do YooTheme dentro do painel DPS

### O Que É Suportado ✅

1. **Renderização no Front-End**: Funciona perfeitamente quando a página é acessada normalmente
2. **Todas as Funcionalidades DPS**: Abas, formulários, AJAX, validações, etc.
3. **Responsividade**: O painel DPS se adapta a diferentes tamanhos de tela
4. **Integração com Add-ons**: Todos os add-ons funcionam normalmente

## Alternativas

Se você precisa de edição visual total:

### Opção 1: Usar Outro Editor de Página

- Elementor
- Beaver Builder
- WPBakery

Todos suportam shortcodes através de widgets específicos.

### Opção 2: Criar Página Admin do WordPress

Em vez de usar shortcode no front-end:

1. Acesse: WordPress Admin → DPS → (menu admin do plugin)
2. Esta interface não depende do tema e funciona em qualquer ambiente

### Opção 3: Usar Template Customizado

Crie um template PHP específico para o DPS (veja "Método 3" acima) que não dependa do builder do YooTheme.

## Suporte

### Recursos Adicionais

- [Documentação Principal do DPS](../../README.md)
- [Guia de Shortcodes](../../plugin/desi-pet-shower-base_plugin/README.md#shortcodes)
- [Documentação do YooTheme PRO](https://yootheme.com/support)

### Reportar Problemas

Se o shortcode não funciona nem no front-end (fora do builder):

1. Verifique versão do WordPress (mínimo 6.0)
2. Verifique versão do PHP (mínimo 7.4)
3. Desative temporariamente outros plugins
4. Teste com tema padrão (Twenty Twenty-Four)
5. Se persistir, reporte em: [Issues do GitHub](https://github.com/richardprobst/DPS/issues)

## Resumo

**TL;DR**: A mensagem "O construtor não está disponível" vem do YooTheme PRO, não do DPS. 

**Solução rápida**: 
1. Não use o builder visual do YooTheme para esta página
2. Adicione `[dps_base]` via editor de código/HTML
3. Acesse a página no front-end - deve funcionar perfeitamente

**O shortcode DPS é totalmente compatível com YooTheme PRO no front-end**, apenas a edição visual no builder que pode ter limitações.
