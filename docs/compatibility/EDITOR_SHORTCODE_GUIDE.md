# Guia: Como Inserir Shortcodes no Editor WordPress

**Autor:** PRObst  
**Site:** [www.probst.pro](https://www.probst.pro)

---

## 🎯 Problema Comum

Usuários frequentemente relatam que os shortcodes do DPS "não funcionam" quando inseridos no editor. Na maioria dos casos, isso ocorre porque o shortcode foi inserido no **bloco errado**.

### Sintoma
O shortcode aparece como texto literal na página (ex: `[dps_base]`) em vez de renderizar o painel do sistema.

### Causa
O shortcode foi inserido no bloco **"Código"** (Code block), que é projetado para **exibir** código formatado, não para **executá-lo**.

---

## ✅ Como Inserir Shortcodes Corretamente

### Editor Gutenberg (Editor de Blocos)

#### Opção 1: Bloco "Shortcode" (Recomendado)

1. Clique no botão **"+"** para adicionar um novo bloco
2. Pesquise por **"Shortcode"** na barra de busca
3. Selecione o bloco **Shortcode** (ícone de colchetes `[ ]`)
4. Cole o shortcode, por exemplo: `[dps_base]`

#### Opção 2: Bloco "Parágrafo" (Alternativa Simples)

1. Simplesmente digite ou cole o shortcode em um bloco de parágrafo normal
2. O WordPress reconhecerá automaticamente e executará o shortcode

#### ⚠️ NÃO Use: Bloco "Código"

O bloco **Código** (`</> Code`) serve para exibir snippets de código como exemplos, similar ao que você vê em tutoriais de programação. Ele **não executa** shortcodes.

| Bloco | Ícone | Propósito | Executa Shortcode? |
|-------|-------|-----------|-------------------|
| Shortcode | `[ ]` | Inserir e executar shortcodes | ✅ SIM |
| Parágrafo | ¶ | Texto comum | ✅ SIM |
| Código | `</>` | Exibir código literalmente | ❌ NÃO |
| HTML | `<>` | Código HTML customizado | ⚠️ Parcial¹ |

> ¹ O bloco HTML pode funcionar, mas não é recomendado para shortcodes simples.

---

### Editor Clássico

Se você usa o editor clássico (TinyMCE):

1. Alterne para o modo **"Texto"** (não "Visual")
2. Cole o shortcode diretamente: `[dps_base]`
3. Ou use no modo Visual - simplesmente digite o shortcode

---

## 🔧 Como Corrigir se Usou o Bloco Errado

### Transformar bloco existente

1. Clique no bloco que contém o shortcode
2. Na barra de ferramentas do bloco, clique no ícone do bloco (primeiro ícone à esquerda)
3. Selecione **"Transformar em"**
4. Escolha **"Shortcode"** ou **"Parágrafo"**

### Criar novo bloco

1. Delete o bloco incorreto
2. Adicione um novo bloco **Shortcode**
3. Cole o shortcode novamente

---

## 📋 Lista de Shortcodes do DPS

| Shortcode | Plugin/Add-on | Descrição |
|-----------|---------------|-----------|
| `[dps_base]` | Base | Painel administrativo principal |
| `[dps_configuracoes]` | Base | Tela de configurações |
| `[dps_agenda_page]` | Agenda | Visualização da agenda |
| `[dps_client_portal]` | Portal | Portal do cliente |
| `[dps_client_login]` | Portal | Login do cliente |
| `[dps_registration_form]` | Cadastro | Formulário público |
| `[dps_services_catalog]` | Serviços | Catálogo de serviços |
| `[dps_fin_docs]` | Financeiro | Documentos financeiros |
| `[dps_groomer_portal]` | Groomers | Portal do groomer |
| `[dps_groomer_login]` | Groomers | Login do groomer |
| `[dps_groomer_dashboard]` | Groomers | Dashboard individual |
| `[dps_groomer_agenda]` | Groomers | Agenda semanal |
| `[dps_groomer_review]` | Groomers | Formulário de avaliação |
| `[dps_groomer_reviews]` | Groomers | Lista de avaliações |
| `[dps_ai_public_chat]` | AI | Chat público para visitantes |

---

## 🚨 Outros Problemas com Shortcodes

Se o shortcode ainda não funciona após usar o bloco correto, verifique:

1. **Plugin ativo**: O plugin base ou add-on correspondente está ativo?
2. **Digitação correta**: O shortcode está exatamente como documentado?
3. **Permissões**: Você tem as capabilities necessárias?
4. **Conflito de cache**: Limpe o cache do site/navegador
5. **Page builder**: Veja os guias de compatibilidade em [docs/compatibility/](./)

---

## 📖 Referências

- [Documentação WordPress: Bloco Shortcode](https://wordpress.org/documentation/article/shortcode-block/)
- [Guia Completo do Sistema DPS](../GUIA_SISTEMA_DPS.md)
- [Compatibilidade com YooTheme PRO](./YOOTHEME_COMPATIBILITY.md)

---

*Documento criado para resolver dúvidas frequentes sobre inserção de shortcodes no editor WordPress.*
