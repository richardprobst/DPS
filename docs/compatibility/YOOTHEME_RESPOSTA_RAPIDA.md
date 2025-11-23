# Resposta ao Problema: YooTheme PRO + Shortcode [dps_base]

## Sua Pergunta

> "uso o thema Yootheme PRO e ao criar uma pagina e inserir o shortcode [dps_base] recebo a seguinte mensagem 'O construtor não está disponível nesta página. Ele só pode ser usado em páginas, posts e categorias.' o que pode ser?"

## Resposta Rápida

**Esta mensagem vem do YooTheme PRO, não do plugin DPS.**

O erro acontece quando você tenta usar o **construtor visual do YooTheme** em uma página que contém o shortcode `[dps_base]`. O YooTheme tem limitações sobre quais páginas podem ser editadas no modo visual builder.

## ✅ SOLUÇÃO (Escolha uma das opções)

### Opção 1: Usar Editor de Código (MAIS SIMPLES)

1. **Ao criar a página**, NÃO use o builder visual do YooTheme
2. **Clique em "Código" ou "HTML"** no editor
3. **Insira apenas**: `[dps_base]`
4. **Publique a página**
5. **Acesse a página no front-end** (fora do admin) - o painel DPS aparecerá normalmente!

### Opção 2: Usar Elemento HTML do YooTheme

Se preferir usar o builder:

1. No builder do YooTheme, adicione um elemento **"HTML"**
2. Dentro dele, insira: `[dps_base]`
3. Configure o elemento para **largura total (100%)**
4. Salve e publique

### Opção 3: Usar Editor Clássico do WordPress

1. **Desative o YooTheme builder** para esta página específica
2. **Use o Editor Clássico** do WordPress
3. **Insira o shortcode** `[dps_base]`
4. **Publique**

## ⚠️ IMPORTANTE

- **O erro aparece apenas no editor** (parte administrativa)
- **NO FRONT-END O SHORTCODE FUNCIONA PERFEITAMENTE**
- **Não é um bug do DPS** - é uma limitação do builder visual do YooTheme

## 🔍 Como Verificar se Está Funcionando

1. **Publique a página** com o shortcode
2. **Acesse a URL da página** (como um visitante normal)
3. **Você verá o painel DPS** com todas as abas e funcionalidades

**Se aparecer corretamente no front-end**: está tudo OK! O "erro" é apenas no editor visual, não afeta o funcionamento real.

## 📚 Documentação Completa

Para detalhes completos, troubleshooting e configurações avançadas, consulte:

**[docs/compatibility/YOOTHEME_COMPATIBILITY.md](../compatibility/YOOTHEME_COMPATIBILITY.md)**

Esta documentação inclui:
- 3 métodos diferentes de usar o shortcode
- Solução de problemas (CSS quebrado, JavaScript não funciona, etc.)
- Configurações recomendadas de layout
- CSS customizado para melhor integração visual
- Limitações e alternativas

## 🎯 Resumo

**O QUE FAZER:**
1. Não tente editar a página no builder visual do YooTheme
2. Adicione o shortcode via código/HTML
3. Acesse a página publicada no front-end

**O QUE VAI ACONTECER:**
- ✅ Página funcionará perfeitamente no front-end
- ✅ Todas as funcionalidades DPS estarão disponíveis
- ✅ Layout será responsivo e integrado com o tema

**O QUE NÃO VAI FUNCIONAR:**
- ❌ Editar o conteúdo do shortcode no builder visual (mas você não precisa disso!)
- ❌ Preview em tempo real no builder (mas funciona ao publicar)

## 💡 Por Que Isso Acontece?

O YooTheme PRO tem um sistema de builder visual que precisa entender cada elemento da página para permitir edição visual. Shortcodes complexos como `[dps_base]` renderizam conteúdo dinâmico que o builder não consegue "abrir" para edição visual.

Isso é **completamente normal** e acontece com praticamente todos os page builders (Elementor, Beaver Builder, etc.) quando trabalham com shortcodes dinâmicos.

A solução é simples: **adicione o shortcode via código** e ele funcionará perfeitamente no front-end! 🚀

---

**Precisa de mais ajuda?** Consulte a [documentação completa de compatibilidade](../compatibility/YOOTHEME_COMPATIBILITY.md).
