# Guia Completo: Integração DPS com Google Workspace (Calendar + Tasks)

**Versão:** 2.0.0  
**Autor:** PRObst  
**Data:** 19 de Janeiro de 2026  
**Última Atualização:** 19 de Janeiro de 2026

---

## 📋 Índice

1. [O que é esta integração?](#1-o-que-é-esta-integração)
2. [O que você vai conseguir fazer?](#2-o-que-você-vai-conseguir-fazer)
3. [Pré-requisitos](#3-pré-requisitos)
4. [Passo 1: Criar Projeto no Google Cloud](#4-passo-1-criar-projeto-no-google-cloud)
5. [Passo 2: Habilitar APIs](#5-passo-2-habilitar-apis)
6. [Passo 3: Configurar OAuth 2.0](#6-passo-3-configurar-oauth-20)
7. [Passo 4: Configurar Credenciais no WordPress](#7-passo-4-configurar-credenciais-no-wordpress)
8. [Passo 5: Conectar sua Conta Google](#8-passo-5-conectar-sua-conta-google)
9. [Passo 6: Configurar Sincronizações](#9-passo-6-configurar-sincronizações)
10. [Como Usar: Google Calendar](#10-como-usar-google-calendar)
11. [Como Usar: Google Tasks](#11-como-usar-google-tasks)
12. [Perguntas Frequentes (FAQ)](#12-perguntas-frequentes-faq)
13. [Solução de Problemas](#13-solução-de-problemas)
14. [Suporte](#14-suporte)

---

## 1. O que é esta integração?

Esta integração conecta o sistema DPS (gestão de pet shop) com o **Google Workspace**, permitindo que você use:

- **Google Calendar** 📅: Para visualizar seus agendamentos de atendimento (banho, tosa, etc.)
- **Google Tasks** ✅: Para gerenciar tarefas administrativas (follow-ups, cobranças)

Tudo acontece **automaticamente** - você não precisa fazer nada manualmente!

---

## 2. O que você vai conseguir fazer?

### ✅ Com Google Calendar

1. **Ver agendamentos no Google Calendar**
   - Todos os agendamentos criados no DPS aparecem automaticamente no Google Calendar
   - Você pode ver seus compromissos no celular, computador, tablet
   - Recebe notificações antes de cada atendimento

2. **Reagendar do Google Calendar**
   - Se você arrastar um evento para outro horário no Google Calendar, o DPS atualiza automaticamente
   - **Funciona nos dois sentidos!** DPS ⇄ Google Calendar

3. **Visualização profissional**
   - Eventos com cores diferentes por status (pendente = azul, finalizado = verde)
   - Título claro: "🐾 Banho, Tosa - Rex (João Silva)"
   - Descrição completa com todos os detalhes

### ✅ Com Google Tasks

1. **Follow-ups automáticos**
   - Quando um atendimento é finalizado, cria automaticamente uma tarefa "Fazer follow-up com cliente João Silva"
   - Você recebe lembrete para ligar 2 dias depois e avaliar satisfação

2. **Lembretes de cobrança**
   - Quando uma cobrança está para vencer, cria tarefa "Cobrar R$ 150,00 de João Silva"
   - Você recebe lembrete 1 dia antes do vencimento

3. **Mensagens do portal**
   - Quando cliente envia mensagem pelo portal, cria tarefa "Responder mensagem de João Silva"
   - Você não esquece de responder ninguém!

---

## 3. Pré-requisitos

### O que você precisa ter:

✅ **Conta Google** (Gmail)
- Pode ser conta pessoal ou conta Google Workspace (empresarial)
- É grátis se você já tem Gmail

✅ **Acesso Administrador do WordPress**
- Você precisa conseguir entrar no painel administrativo do WordPress
- Precisa ter permissão para instalar plugins e editar arquivos

✅ **10-15 minutos de tempo**
- Configuração inicial leva cerca de 10-15 minutos
- É feita uma vez só, depois funciona automaticamente

### O que você NÃO precisa:

❌ **Conhecimento técnico avançado**
- Este guia é para iniciantes
- Vamos explicar cada passo com imagens

❌ **Pagar pelo Google**
- As APIs do Google Calendar e Google Tasks são **100% gratuitas**
- Você tem 50.000 requisições por dia (muito mais do que você vai usar)

❌ **Saber programar**
- Não precisa escrever código
- Apenas copiar e colar algumas informações

---

## 4. Passo 1: Criar Projeto no Google Cloud

### Por que fazer isso?

O Google precisa saber qual aplicação está acessando sua conta. Você vai criar um "projeto" (é como uma identificação) para o DPS no Google Cloud.

### Passo a passo:

#### 1.1. Acesse o Google Cloud Console

1. Abra seu navegador (Chrome, Firefox, etc.)
2. Acesse: **https://console.cloud.google.com**
3. Faça login com sua conta Google (Gmail)

![Tela de login do Google Cloud Console](imagem-placeholder)

#### 1.2. Criar novo projeto

1. No canto superior esquerdo, clique em **"Selecionar um projeto"**
2. Na janela que abrir, clique em **"NOVO PROJETO"** (canto superior direito)

![Botão Novo Projeto](imagem-placeholder)

#### 1.3. Preencher informações do projeto

1. **Nome do projeto**: Digite `DPS Pet Shop` (ou nome de sua preferência)
2. **Organização**: Deixe como está (Sem organização)
3. **Local**: Deixe como está
4. Clique no botão azul **"CRIAR"**

![Formulário de novo projeto](imagem-placeholder)

#### 1.4. Aguarde criação

- O Google vai criar seu projeto (leva 5-10 segundos)
- Você verá uma notificação no canto superior direito quando estiver pronto
- Clique em **"SELECIONAR PROJETO"** na notificação

![Projeto criado com sucesso](imagem-placeholder)

✅ **Pronto!** Seu projeto está criado.

---

## 5. Passo 2: Habilitar APIs

### Por que fazer isso?

Você precisa dar permissão para o projeto acessar o Google Calendar e o Google Tasks. É como "ligar" essas funcionalidades.

### Passo a passo:

#### 2.1. Habilitar Google Calendar API

1. No menu lateral esquerdo, clique em **"APIs e Serviços"** → **"Biblioteca"**
2. No campo de busca, digite: `Google Calendar API`
3. Clique no resultado **"Google Calendar API"**
4. Clique no botão azul **"ATIVAR"**
5. Aguarde 5-10 segundos até ativar

![Habilitar Calendar API](imagem-placeholder)

#### 2.2. Habilitar Google Tasks API

1. Clique na seta **"←"** (voltar) no canto superior esquerdo
2. Você volta para a Biblioteca de APIs
3. No campo de busca, digite: `Google Tasks API`
4. Clique no resultado **"Google Tasks API"**
5. Clique no botão azul **"ATIVAR"**
6. Aguarde 5-10 segundos até ativar

![Habilitar Tasks API](imagem-placeholder)

✅ **Pronto!** As duas APIs estão ativadas.

---

## 6. Passo 3: Configurar OAuth 2.0

### Por que fazer isso?

OAuth 2.0 é o sistema de segurança do Google. Você vai configurar a "tela de consentimento" (aquela tela que pergunta "Permitir que DPS acesse sua conta?").

### Passo a passo:

#### 3.1. Acessar Tela de Consentimento OAuth

1. No menu lateral esquerdo, clique em **"APIs e Serviços"** → **"Tela de consentimento OAuth"**

![Menu Tela de Consentimento](imagem-placeholder)

#### 3.2. Escolher tipo de usuário

1. Selecione **"Externo"** (ou "Interno" se você usa Google Workspace empresarial)
2. Clique no botão **"CRIAR"**

![Escolher tipo externo](imagem-placeholder)

#### 3.3. Preencher informações do aplicativo

**Seção 1: Informações do app**

1. **Nome do app**: `DPS Pet Shop` (ou nome de sua preferência)
2. **E-mail de suporte do usuário**: Seu e-mail (Gmail)
3. **Logotipo do app**: Deixe em branco (opcional)
4. **Domínio do app**: Deixe em branco
5. **Links do aplicativo**: Deixe em branco
6. **Domínios autorizados**: Deixe em branco
7. **E-mail do desenvolvedor**: Seu e-mail (Gmail)
8. Clique em **"SALVAR E CONTINUAR"**

![Formulário informações do app](imagem-placeholder)

**Seção 2: Escopos**

1. Clique em **"ADICIONAR OU REMOVER ESCOPOS"**
2. Na janela que abrir, **marque as caixinhas**:
   - ✅ `https://www.googleapis.com/auth/calendar`
   - ✅ `https://www.googleapis.com/auth/calendar.events`
   - ✅ `https://www.googleapis.com/auth/tasks`
3. Clique em **"ATUALIZAR"**
4. Clique em **"SALVAR E CONTINUAR"**

![Adicionar escopos](imagem-placeholder)

**Seção 3: Usuários de teste (IMPORTANTE)**

1. Clique em **"+ ADD USERS"**
2. Digite seu e-mail (Gmail) que vai usar para conectar
3. Clique em **"ADICIONAR"**
4. Clique em **"SALVAR E CONTINUAR"**

![Adicionar usuários de teste](imagem-placeholder)

**Seção 4: Resumo**

1. Revise as informações
2. Clique em **"VOLTAR PARA O PAINEL"**

✅ **Pronto!** Tela de consentimento configurada.

#### 3.4. Criar credenciais OAuth 2.0

1. No menu lateral esquerdo, clique em **"APIs e Serviços"** → **"Credenciais"**
2. No topo da página, clique em **"+ CRIAR CREDENCIAIS"**
3. Selecione **"ID do cliente OAuth"**

![Criar credenciais](imagem-placeholder)

#### 3.5. Configurar ID do cliente

1. **Tipo de aplicativo**: Selecione **"Aplicativo da Web"**
2. **Nome**: `DPS WordPress` (ou nome de sua preferência)
3. **Origens JavaScript autorizadas**: Clique em **"+ ADICIONAR URI"**
   - Digite a URL do seu site WordPress (exemplo: `https://seupetshop.com.br`)
   - **SEM barra no final!** ❌ `https://seupetshop.com.br/` ✅ `https://seupetshop.com.br`
4. **URIs de redirecionamento autorizados**: Clique em **"+ ADICIONAR URI"**
   - Digite: `https://seupetshop.com.br/wp-admin/admin.php?page=dps-agenda-hub&tab=google-integrations&action=oauth_callback`
   - **Substitua** `seupetshop.com.br` pelo domínio real do seu site!
5. Clique no botão **"CRIAR"**

![Configurar ID do cliente](imagem-placeholder)

#### 3.6. Copiar credenciais

Uma janela vai aparecer com suas credenciais:

1. **ID do cliente**: Algo como `123456789-abc.apps.googleusercontent.com`
   - Clique no ícone de **copiar** 📋
   - Cole em um bloco de notas (vamos usar no Passo 4)

2. **Chave secreta do cliente**: Algo como `GOCSPX-AbC123xyz`
   - Clique no ícone de **copiar** 📋
   - Cole em um bloco de notas (vamos usar no Passo 4)

3. Clique em **"OK"**

![Copiar credenciais](imagem-placeholder)

✅ **Pronto!** Credenciais criadas. Guarde bem essas informações!

---

## 7. Passo 4: Configurar Credenciais no WordPress

### Por que fazer isso?

Você precisa informar ao WordPress (DPS) quais são as credenciais que você criou no Google Cloud.

### Passo a passo:

#### 4.1. Acessar arquivo wp-config.php

**Opção A: Via FTP (FileZilla, WinSCP)**

1. Abra seu cliente FTP (FileZilla ou similar)
2. Conecte no servidor do seu site
3. Navegue até a pasta raiz do WordPress (onde estão as pastas `wp-content`, `wp-admin`, etc.)
4. Encontre o arquivo `wp-config.php`
5. Clique com botão direito → **"Editar"**

**Opção B: Via painel de hospedagem (cPanel, Plesk)**

1. Acesse o painel da sua hospedagem
2. Procure por **"Gerenciador de Arquivos"** ou **"File Manager"**
3. Navegue até a pasta raiz do WordPress
4. Encontre o arquivo `wp-config.php`
5. Clique com botão direito → **"Editar"**

**Opção C: Via plugin (File Manager)**

1. No WordPress admin, instale o plugin **"File Manager"**
2. Acesse **Ferramentas** → **File Manager**
3. Navegue até a pasta raiz
4. Clique no arquivo `wp-config.php` → **"Edit"**

![Editar wp-config.php](imagem-placeholder)

#### 4.2. Adicionar constantes ao wp-config.php

1. No arquivo `wp-config.php`, procure a linha que diz:
   ```php
   /* Isto é tudo, pode parar de editar! :) */
   ```

2. **ANTES** dessa linha, adicione este código:

```php
/* Google Workspace Integration - DPS */
define( 'DPS_GOOGLE_CLIENT_ID', 'SEU_CLIENT_ID_AQUI' );
define( 'DPS_GOOGLE_CLIENT_SECRET', 'SUA_CLIENT_SECRET_AQUI' );
```

3. **Substitua** os valores:
   - Troque `SEU_CLIENT_ID_AQUI` pelo **ID do cliente** que você copiou no Passo 3.6
   - Troque `SUA_CLIENT_SECRET_AQUI` pela **Chave secreta do cliente** que você copiou no Passo 3.6

**Exemplo real:**

```php
/* Google Workspace Integration - DPS */
define( 'DPS_GOOGLE_CLIENT_ID', '123456789-abc.apps.googleusercontent.com' );
define( 'DPS_GOOGLE_CLIENT_SECRET', 'GOCSPX-AbC123xyz456' );
```

4. **Salve o arquivo**

![Adicionar constantes](imagem-placeholder)

⚠️ **ATENÇÃO:**
- As aspas simples `'` são importantes!
- Não adicione espaços extras
- Não remova as linhas existentes, apenas adicione as novas

✅ **Pronto!** WordPress configurado.

---

## 8. Passo 5: Conectar sua Conta Google

### Passo a passo:

#### 5.1. Acessar página de integrações

1. No WordPress admin, no menu lateral esquerdo:
2. Clique em **"desi.pet by PRObst"**
3. Clique em **"Agenda"**
4. Clique na aba **"Integrações Google"** 🔗 (canto superior direito)

![Menu Integrações Google](imagem-placeholder)

#### 5.2. Conectar com Google

1. Você verá um botão grande azul: **"🔐 Conectar com Google"**
2. Clique nesse botão
3. Você será redirecionado para uma página do Google

![Botão Conectar](imagem-placeholder)

#### 5.3. Autorizar acesso (Tela do Google)

1. **Escolha sua conta Google** (se solicitado)
2. Você verá a tela: **"DPS Pet Shop quer acessar sua Conta do Google"**
3. Revise as permissões solicitadas:
   - ✅ Ver e gerenciar eventos do Google Calendar
   - ✅ Ver, editar e excluir todas as suas tarefas
4. Clique em **"Continuar"** ou **"Permitir"**

![Tela de consentimento Google](imagem-placeholder)

#### 5.4. Confirmação

1. Você será redirecionado de volta para o WordPress
2. Verá uma mensagem verde: **"✅ Conectado com sucesso!"**
3. Verá o status: **"✅ Conectado como seuemail@gmail.com"**

![Conectado com sucesso](imagem-placeholder)

✅ **Pronto!** Conta Google conectada.

---

## 9. Passo 6: Configurar Sincronizações

### Passo a passo:

#### 6.1. Habilitar Google Calendar

1. Na mesma página (Integrações Google), role para baixo até **"Configurações de Sincronização"**
2. **Marque a caixinha**:
   - ✅ **Sincronizar agendamentos com Google Calendar**
3. Clique no botão **"Salvar Configurações"**

![Habilitar Calendar](imagem-placeholder)

**O que isso faz?**
- Todos os agendamentos salvos no DPS vão aparecer no Google Calendar
- Se você reagendar no Google Calendar, o DPS atualiza automaticamente

#### 6.2. Habilitar Google Tasks

1. Na mesma seção, **marque a caixinha**:
   - ✅ **Sincronizar tarefas administrativas com Google Tasks**
2. Clique no botão **"Salvar Configurações"**

![Habilitar Tasks](imagem-placeholder)

**O que isso faz?**
- Cria tarefas automáticas para:
  - Follow-ups pós-atendimento (2 dias depois)
  - Cobranças pendentes (1 dia antes do vencimento)
  - Mensagens do portal do cliente

✅ **Pronto!** Tudo configurado e funcionando!

---

## 10. Como Usar: Google Calendar

### Ver agendamentos no Google Calendar

1. Abra **Google Calendar** (calendar.google.com ou app mobile)
2. Seus agendamentos do DPS estarão lá automaticamente! 🎉

![Agendamentos no Calendar](imagem-placeholder)

### Formato dos eventos

**Título:**
```
🐾 Banho, Tosa - Rex (João Silva)
```

**Descrição:**
```
Cliente: João Silva
Pet: Rex (Cachorro, 5 anos)
Serviços: Banho, Tosa
Profissional: Maria Santos

🔗 Ver no DPS: [link direto para o agendamento]
```

**Horário:**
- Data e hora exatos do agendamento

**Lembretes:**
- 1 hora antes (popup)
- 15 minutos antes (popup)

### Cores por status

- 🔵 **Azul**: Agendamento pendente
- 🟢 **Verde**: Agendamento finalizado
- 🔴 **Vermelho**: Agendamento cancelado

### Reagendar do Google Calendar

#### Passo a passo:

1. Abra Google Calendar
2. Encontre o evento do agendamento
3. **Arraste** o evento para outro horário
4. Aguarde ~30 segundos
5. O DPS atualiza automaticamente! ✨

![Reagendar arrastando](imagem-placeholder)

**OU:**

1. Clique no evento
2. Clique no ícone de **"Editar"** (lápis)
3. Altere data/hora
4. Clique em **"Salvar"**
5. Aguarde ~30 segundos
6. O DPS atualiza automaticamente! ✨

⚠️ **IMPORTANTE:**
- Só funciona para data e hora
- Não altere título ou descrição (não sincronizam)
- Alterações levam até 1 minuto para sincronizar

---

## 11. Como Usar: Google Tasks

### Ver tarefas no Google Tasks

1. Abra **Google Tasks**:
   - Web: tasks.google.com
   - Mobile: App "Google Tasks" (Android/iOS)
   - Gmail: Lateral direita → ícone de checklist
2. Suas tarefas administrativas estarão lá automaticamente! 🎉

![Tarefas no Google Tasks](imagem-placeholder)

### Tipos de tarefas automáticas

#### 1. Follow-ups pós-atendimento

**Quando cria:**
- Quando você marca um agendamento como "Finalizado" no DPS

**Título:**
```
📞 Follow-up: Rex - Banho, Tosa
```

**Descrição:**
```
Cliente: João Silva
Pet: Rex
Serviços: Banho, Tosa

✅ Atendimento finalizado - fazer contato para avaliar satisfação e agendar retorno.

🔗 Ver agendamento no DPS: [link]
```

**Vencimento:**
- 2 dias após o atendimento

**Como usar:**
1. Você recebe notificação do Google Tasks
2. Liga para o cliente
3. Marca tarefa como concluída ✅
4. Pronto!

#### 2. Cobranças pendentes

**Quando cria:**
- Quando uma cobrança está para vencer no DPS

**Título:**
```
💰 Cobrança: João Silva - R$ 150,00
```

**Descrição:**
```
Cliente: João Silva
Valor: R$ 150,00
Vencimento: 25/01/2026
Descrição: Pagamento de serviços

⚠️ Cobrança pendente - entrar em contato para solicitar pagamento.

🔗 Ver agendamento no DPS: [link]
```

**Vencimento:**
- 1 dia antes da data de vencimento

**Como usar:**
1. Você recebe notificação do Google Tasks
2. Liga para o cliente e solicita pagamento
3. Quando cliente pagar, a tarefa é **automaticamente marcada como concluída** ✅

#### 3. Mensagens do portal

**Quando cria:**
- Quando cliente envia mensagem pelo portal do DPS

**Título:**
```
💬 Responder: João Silva - Solicitação
```

**Descrição:**
```
Cliente: João Silva
Assunto: Dúvida sobre horários

Mensagem:
Olá, gostaria de saber se vocês atendem aos sábados...

📱 Responder no Portal: [link]
```

**Vencimento:**
- 1 dia após recebimento da mensagem

**Como usar:**
1. Você recebe notificação do Google Tasks
2. Acessa o portal e responde a mensagem
3. Marca tarefa como concluída ✅

---

## 12. Perguntas Frequentes (FAQ)

### 1. Preciso pagar pelo Google?

**R:** Não! As APIs do Google Calendar e Google Tasks são **100% gratuitas**. Você tem 50.000 requisições por dia, o que é muito mais do que qualquer pet shop usa.

### 2. Meus dados estão seguros?

**R:** Sim! A integração usa OAuth 2.0, o sistema de segurança do Google. Suas credenciais são criptografadas com AES-256. O DPS só acessa o que você autorizar.

### 3. Posso desconectar a qualquer momento?

**R:** Sim! Na página "Integrações Google", clique em "Desconectar". Todas as sincronizações param imediatamente.

### 4. O que acontece se eu desconectar?

**R:**
- Sincronizações param
- Eventos/tarefas já criados no Google continuam lá (não são deletados)
- Novos agendamentos no DPS não vão mais para o Google

### 5. Posso usar com múltiplas contas Google?

**R:** Atualmente, apenas uma conta pode estar conectada por vez. Se precisar trocar de conta, desconecte e conecte com a outra.

### 6. Funciona no celular?

**R:** Sim! Tanto o Google Calendar quanto o Google Tasks têm apps mobile excelentes (Android e iOS). Você recebe notificações no celular.

### 7. E se eu deletar um agendamento no DPS?

**R:** O evento é automaticamente removido do Google Calendar.

### 8. E se eu deletar um evento no Google Calendar?

**R:** O agendamento **não** é deletado do DPS (para preservar histórico e dados financeiros). Ele apenas é marcado internamente como "deletado no Calendar".

### 9. Quantos segundos leva para sincronizar?

**R:**
- **DPS → Google**: Imediato (~2 segundos)
- **Google → DPS**: Até 1 minuto (depende de webhook)

### 10. Posso sincronizar agendamentos antigos?

**R:** A sincronização começa a partir do momento que você conecta. Agendamentos antigos não são sincronizados automaticamente. Você pode editá-los no DPS para forçar sincronização.

### 11. O que são "escopos" no Google?

**R:** São as permissões que você dá para o DPS acessar sua conta. Exemplo: "Ver e gerenciar eventos do Calendar". Você autoriza isso na tela de consentimento.

### 12. Posso usar Google Workspace (conta empresarial)?

**R:** Sim! Funciona perfeitamente com contas pessoais (Gmail) e empresariais (Google Workspace).

### 13. A configuração expira?

**R:** Não! Uma vez configurado, funciona indefinidamente. O único prazo é a renovação automática do webhook (que acontece automaticamente a cada 7 dias).

### 14. Posso escolher quais agendamentos sincronizar?

**R:** Atualmente, todos os agendamentos sincronizam. Em versões futuras, planejamos adicionar filtros (ex: só agendamentos pendentes).

### 15. Como sei se está funcionando?

**R:**
1. Crie um agendamento de teste no DPS
2. Aguarde 5 segundos
3. Abra Google Calendar
4. Deve aparecer lá! 🎉

---

## 13. Solução de Problemas

### ❌ Erro: "Token de segurança inválido"

**Causa:** As constantes no `wp-config.php` estão incorretas ou com espaços extras.

**Solução:**
1. Abra `wp-config.php`
2. Verifique se as linhas estão exatamente assim:
   ```php
   define( 'DPS_GOOGLE_CLIENT_ID', 'seu-client-id-aqui' );
   define( 'DPS_GOOGLE_CLIENT_SECRET', 'sua-secret-aqui' );
   ```
3. Não pode ter espaços antes/depois das aspas
4. Salve e tente novamente

### ❌ Erro: "Redirect URI mismatch"

**Causa:** A URI de redirecionamento no Google Cloud não está correta.

**Solução:**
1. Acesse Google Cloud Console → Credenciais
2. Edite o "ID do cliente OAuth"
3. Em "URIs de redirecionamento autorizados", **confira**:
   - Deve ser EXATAMENTE: `https://seusite.com.br/wp-admin/admin.php?page=dps-agenda-hub&tab=google-integrations&action=oauth_callback`
   - Substitua `seusite.com.br` pelo seu domínio real
   - **Sem espaços, sem barra final**
4. Clique em "Salvar"
5. Aguarde 5 minutos (mudanças levam tempo para propagar)
6. Tente conectar novamente

### ❌ Eventos não aparecem no Google Calendar

**Causa 1:** Sincronização do Calendar não está habilitada.

**Solução:**
1. Vá em **Agenda** → **Integrações Google**
2. Marque: ✅ **Sincronizar agendamentos com Google Calendar**
3. Clique em "Salvar Configurações"

**Causa 2:** Você não está conectado.

**Solução:**
1. Verifique status na página "Integrações Google"
2. Deve dizer: "✅ Conectado como seuemail@gmail.com"
3. Se não, clique em "Conectar com Google"

**Causa 3:** Agendamento foi criado antes de conectar.

**Solução:**
1. Edite o agendamento no DPS (mude qualquer campo)
2. Clique em "Salvar"
3. Isso força a sincronização

### ❌ Tarefas não aparecem no Google Tasks

**Causa:** Sincronização do Tasks não está habilitada.

**Solução:**
1. Vá em **Agenda** → **Integrações Google**
2. Marque: ✅ **Sincronizar tarefas administrativas com Google Tasks**
3. Clique em "Salvar Configurações"

### ❌ Reagendamento no Calendar não sincroniza para DPS

**Causa:** Webhook não está ativo.

**Solução:**
1. Desconecte e reconecte sua conta Google
2. Isso registra o webhook novamente
3. Aguarde 1 minuto
4. Tente reagendar novamente

### ❌ Erro: "Este app não foi verificado"

**Causa:** Você está em modo de teste no Google Cloud.

**Solução:**
1. Clique em "Avançado" (canto inferior esquerdo)
2. Clique em "Ir para DPS Pet Shop (não seguro)"
3. Continue a autorização normalmente

**Por que isso acontece?**
- O Google exige que apps públicos passem por verificação
- Como você está usando para si mesmo, não precisa verificar
- Adicione seu e-mail como "usuário de teste" (Passo 3.3) para evitar isso

### 🔍 Como verificar logs de erro

1. Habilite debug no WordPress:
   - Edite `wp-config.php`
   - Adicione antes de "Isto é tudo":
     ```php
     define( 'WP_DEBUG', true );
     define( 'WP_DEBUG_LOG', true );
     define( 'WP_DEBUG_DISPLAY', false );
     ```
2. Erros serão salvos em: `/wp-content/debug.log`
3. Use FTP ou File Manager para baixar e ler o arquivo

---

## 14. Suporte

### Precisa de ajuda?

**Documentação técnica:**
- `docs/analysis/GOOGLE_TASKS_INTEGRATION_ANALYSIS.md` (análise completa, 42KB)
- `docs/analysis/GOOGLE_TASKS_INTEGRATION_SUMMARY.md` (resumo executivo, 6KB)
- `plugins/desi-pet-shower-agenda/includes/integrations/README.md` (documentação técnica)

**Contato PRObst:**
- Site: https://www.probst.pro
- GitHub: https://github.com/richardprobst/DPS

**Suporte Google:**
- Google Cloud Console: https://console.cloud.google.com
- Documentação Google Calendar API: https://developers.google.com/calendar
- Documentação Google Tasks API: https://developers.google.com/tasks

---

## 🎉 Parabéns!

Você concluiu a configuração da integração DPS com Google Workspace!

Agora você tem:
- ✅ Agendamentos sincronizados automaticamente no Google Calendar
- ✅ Tarefas administrativas automáticas no Google Tasks
- ✅ Sincronização bidirecional (DPS ⇄ Google)
- ✅ Notificações no celular, desktop e email
- ✅ Visibilidade completa da operação do seu pet shop

**Próximos passos:**
1. Crie um agendamento de teste para ver funcionando
2. Abra Google Calendar e Google Tasks no celular
3. Marque um agendamento como finalizado e veja o follow-up aparecer
4. Aproveite sua operação mais organizada! 🐾

---

**Versão do guia:** 2.0.0  
**Última atualização:** 19 de Janeiro de 2026
