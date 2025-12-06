# Guia do Usuário: Controle de Acesso do White Label

**Versão:** 1.1.0  
**Última atualização:** 2025-12-06  
**Nível:** Iniciante

## O que é o Controle de Acesso?

O **Controle de Acesso ao Site** permite que você restrinja quem pode visualizar seu site WordPress. Com esta funcionalidade, você pode:

- 🔒 Tornar seu site totalmente privado (apenas usuários logados)
- 🌐 Manter páginas específicas públicas (home, contato, etc.)
- 🚪 Redirecionar visitantes para uma página de login personalizada
- 👥 Controlar quais tipos de usuários têm acesso

---

## Casos de Uso Comuns

### 1. Site Totalmente Privado

**Situação:** Você quer que apenas clientes cadastrados vejam seu site.

**Configuração:**
- Ativar "Restringir acesso ao site"
- Selecionar "Subscriber" (assinante) como role permitida
- Deixar lista de exceções vazia
- Todos os visitantes serão redirecionados para login

**Ideal para:** Portais de clientes, intranets, sites de membros

### 2. Landing Page Pública + Portal Privado

**Situação:** Você quer um site público para marketing, mas o portal de clientes deve ser privado.

**Configuração:**
- Ativar "Restringir acesso ao site"
- Adicionar nas exceções:
  - `/` (home)
  - `/sobre-nos/`
  - `/servicos/`
  - `/contato/`
  - `/blog/*` (blog e posts)

**Ideal para:** Pet shops, clínicas, empresas de serviços

### 3. Site em Desenvolvimento

**Situação:** Você está construindo o site e quer mostrar apenas para clientes específicos.

**Configuração:**
- Ativar "Restringir acesso ao site"
- Selecionar apenas "Administrator" e "Editor"
- Deixar exceções vazio
- Apenas você e sua equipe poderão acessar

**Ideal para:** Desenvolvimento, testes, homologação

---

## Como Configurar Passo a Passo

### Passo 1: Acessar as Configurações

1. Faça login no WordPress como administrador
2. No menu lateral, clique em **DPS by PRObst**
3. Clique em **White Label**
4. Clique na aba **Acesso ao Site**

### Passo 2: Ativar o Controle de Acesso

1. Marque a caixa **"Restringir acesso ao site"**
2. Você verá a mensagem: "Visitantes sem login serão redirecionados"

⚠️ **ATENÇÃO:** Assim que ativar, visitantes sem login NÃO poderão acessar seu site!

### Passo 3: Escolher Quem Pode Acessar

Selecione as "**roles**" (tipos de usuário) que podem acessar:

- **Administrator** (Administrador) - Sempre ativo, não pode desmarcar
- **Editor** - Editores do site
- **Author** (Autor) - Autores de posts
- **Contributor** (Colaborador) - Colaboradores
- **Subscriber** (Assinante) - Clientes/assinantes

**Dica:** Para um portal de clientes, marque apenas "Administrator" e "Subscriber".

### Passo 4: Definir Páginas Públicas (Exceções)

Se você quer que ALGUMAS páginas fiquem públicas:

1. Na caixa **"Páginas Públicas (Exceções)"**, digite uma URL por linha
2. Exemplos:
   ```
   /
   /contato/
   /servicos/
   /sobre-nos/
   /blog/*
   ```

**Explicação dos exemplos:**
- `/` - Página inicial
- `/contato/` - Página de contato específica
- `/blog/*` - Blog E todos os posts dele (o `*` significa "qualquer coisa depois")

**Dica:** Para descobrir a URL de uma página:
1. Abra a página no navegador
2. Copie tudo DEPOIS do domínio (ex: `www.seusite.com.br/contato/` → copie `/contato/`)

### Passo 5: Configurar Redirecionamento

Escolha para onde enviar visitantes bloqueados:

1. **Página de login padrão** - O login normal do WordPress (`/wp-login.php`)
2. **Página de login customizada** - Se você configurou uma na aba "Login"
3. **URL customizada** - Digite uma URL específica

**Recomendação:** Use "Página de login customizada" para uma experiência mais profissional.

✅ **Marque:** "Redirecionar de volta após login"
- Quando marcado: Cliente faz login e volta para a página que queria acessar
- Quando desmarcado: Cliente faz login e vai para a página inicial

### Passo 6: Opções Avançadas (Opcional)

**Permitir REST API:**
- Marque se usa integrações com outros sistemas
- Geralmente pode deixar marcado

**Permitir AJAX:**
- Marque se usa formulários dinâmicos
- **Deixe sempre marcado** a menos que saiba o que está fazendo

**Permitir arquivos de mídia:**
- Marque se quer que imagens sejam visíveis mesmo sem login
- **Deixe marcado** para evitar imagens quebradas

### Passo 7: Salvar

Clique em **"Salvar Configurações"** no final da página.

✅ Você verá a mensagem: "Configurações de controle de acesso salvas com sucesso!"

🔒 Um badge vermelho aparecerá no topo da página (admin bar): **"ACESSO RESTRITO"**

---

## Testando a Configuração

### Teste Básico

1. **Abra uma janela anônima** do navegador (Ctrl+Shift+N no Chrome)
2. **Acesse seu site**
3. Você deve ser redirecionado para a página de login
4. Tente acessar uma página de exceção (ex: `/contato/`)
5. Essa página deve carregar normalmente

### Teste de Login

1. Na janela anônima, faça login com um usuário permitido
2. Você deve ser redirecionado para a página que tentou acessar
3. Navegue pelo site - tudo deve funcionar normalmente

---

## Perguntas Frequentes (FAQ)

### ❓ Vou ser bloqueado do meu próprio site?

**Não!** Administradores sempre têm acesso. E você sempre pode acessar `/wp-admin/` e `/wp-login.php` para fazer login.

### ❓ Como desativo o controle de acesso?

1. Acesse **DPS by PRObst → White Label → Acesso ao Site**
2. Desmarque **"Restringir acesso ao site"**
3. Clique em **Salvar Configurações**

### ❓ Posso bloquear apenas o blog?

Não diretamente. Esta funcionalidade bloqueia todo o site EXCETO as páginas na lista de exceções.

**Solução alternativa:** Liste todas as páginas públicas nas exceções, deixando o blog de fora.

### ❓ O que é "wildcard" (*)?

O asterisco (`*`) significa "qualquer coisa". 

**Exemplos:**
- `/blog/*` = `/blog/`, `/blog/post-1/`, `/blog/categoria/pets/`, etc.
- `/servicos/*` = `/servicos/`, `/servicos/banho/`, `/servicos/tosa/`, etc.

### ❓ Funciona com plugins de cache?

Sim, mas você pode precisar limpar o cache após ativar.

### ❓ Funciona com Elementor/page builders?

Sim, é totalmente compatível.

### ❓ E se eu tiver Modo de Manutenção ativo?

O **Modo de Manutenção** tem prioridade. Se estiver ativo, ele bloqueia TODO o site (ignorando controle de acesso).

**Use assim:**
- **Modo de Manutenção:** Para bloqueios temporários (manutenção, atualizações)
- **Controle de Acesso:** Para restrições permanentes (site privado)

### ❓ Como sei se está ativo?

Quando o controle de acesso está ativo, você verá um **badge vermelho** "🔒 ACESSO RESTRITO" no topo da página (admin bar).

Clique nele para ir direto para as configurações.

---

## Cenários de Configuração

### Cenário 1: Portal do Cliente Pet Shop

**Objetivo:** Site público para marketing + portal privado para clientes

```
✅ Restringir acesso: SIM
👥 Roles permitidas: Administrator, Subscriber
📄 Exceções:
   /
   /sobre-nos/
   /servicos/
   /servicos/banho/
   /servicos/tosa/
   /contato/
   /blog/*
🚪 Redirecionamento: Página de login customizada
✅ Redirecionar de volta: SIM
```

**Resultado:**
- Home, Sobre, Serviços, Contato e Blog ficam públicos
- Portal do cliente (`/minha-conta/`, `/agendamentos/`) fica privado
- Clientes são redirecionados para login personalizado

### Cenário 2: Intranet Corporativa

**Objetivo:** Site totalmente privado para funcionários

```
✅ Restringir acesso: SIM
👥 Roles permitidas: Administrator, Editor, Author
📄 Exceções: (vazio - nenhuma exceção)
🚪 Redirecionamento: Página de login customizada
✅ Redirecionar de volta: SIM
```

**Resultado:**
- Todo o site bloqueado para visitantes
- Apenas funcionários (editor, autor) e admins podem acessar
- Redirecionamento automático para login

### Cenário 3: Site em Construção

**Objetivo:** Mostrar apenas para cliente e equipe

```
✅ Restringir acesso: SIM
👥 Roles permitidas: Administrator
📄 Exceções: (vazio)
🚪 Redirecionamento: Página de login padrão
❌ Redirecionar de volta: NÃO
```

**Resultado:**
- Site completamente bloqueado
- Apenas administradores podem acessar
- Ideal para desenvolvimento e homologação

---

## Dicas de Segurança

### ✅ Faça

- **Teste antes de ativar em produção** - Use uma cópia do site
- **Mantenha uma lista de exceções atualizada** - Revise periodicamente
- **Use senhas fortes** - Controle de acesso não adianta com senhas fracas
- **Marque "Redirecionar de volta"** - Melhor experiência para usuários
- **Combine com página de login customizada** - Mais profissional

### ❌ Não Faça

- **Não bloqueie `/wp-admin/`** - Já está sempre liberado, não precisa adicionar
- **Não use wildcards muito amplos** - Ex: `/*` bloquearia tudo
- **Não remova Administrator das roles** - Você não consegue (bloqueado)
- **Não ative sem testar** - Pode bloquear acesso inesperado

---

## Solução de Problemas

### Problema: "Não consigo salvar as configurações"

**Solução:**
1. Verifique se você está logado como Administrator
2. Tente fazer logout e login novamente
3. Desative plugins de cache temporariamente
4. Limpe o cache do navegador

### Problema: "Minhas exceções não funcionam"

**Verificar:**
- ✅ URL começa com `/` (ex: `/contato/` não `contato/`)
- ✅ URL termina com `/` para páginas (ex: `/blog/` não `/blog`)
- ✅ Wildcard está correto (ex: `/blog/*` não `/blog*`)
- ✅ Configurações foram salvas com sucesso

### Problema: "Estou vendo página em branco"

**Solução:**
1. Acesse diretamente `/wp-admin/`
2. Desative o Controle de Acesso temporariamente
3. Verifique se há erros no log do servidor
4. Ative o modo de debug do WordPress

### Problema: "Imagens não aparecem"

**Solução:**
1. Marque **"Permitir acesso a arquivos de mídia"**
2. Ou adicione `/wp-content/uploads/*` nas exceções
3. Limpe o cache do navegador

---

## Suporte

Se precisar de ajuda:

1. **Documentação técnica:** `docs/analysis/WHITELABEL_ACCESS_CONTROL_ANALYSIS.md`
2. **Guia de implementação:** `docs/implementation/WHITELABEL_ACCESS_CONTROL_IMPLEMENTATION.md`
3. **Contato:** Entre em contato com o suporte do DPS by PRObst

---

## Changelog

**v1.1.0** (2025-12-06)
- Lançamento inicial do Controle de Acesso ao Site
- Suporte a exceções de URLs com wildcards
- Redirecionamento inteligente com preservação de URL
- Controle por roles WordPress
- Indicador visual na admin bar

---

**© 2025 DPS by PRObst** | [www.probst.pro](https://www.probst.pro)
