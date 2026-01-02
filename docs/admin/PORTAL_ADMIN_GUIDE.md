# Guia do Administrador: Gerar e Enviar Links de Acesso ao Portal

**Autor:** PRObst  
**Público:** Equipe administrativa  
**Atualizado:** 2024-12-08

## Visão Geral

Este guia ensina como gerar e enviar links de acesso ao Portal do Cliente para seus clientes. Os links são **temporários** (válidos por 30 minutos) e **seguros** (não requerem senha).

## Passo a Passo: Gerar Link de Acesso

### 1. Acessar o Gerenciamento de Logins

1. Faça login no painel administrativo do WordPress
2. No menu lateral, clique em **desi.pet by PRObst**
3. Selecione **Logins de Clientes**

Ou acesse diretamente: `https://seusite.com/wp-admin/admin.php?page=dps-client-logins`

### 2. Localizar o Cliente

Na tela de **Logins de Clientes**, você verá uma tabela com todos os clientes cadastrados:

| Cliente | Contato | Situação | Último Login | Ações |
|---------|---------|----------|--------------|-------|
| João Silva | (11) 98765-4321 | Nunca acessou | - | [Primeiro Acesso] |
| Maria Santos | (11) 91234-5678 | Ativo | há 2 dias | [Gerar Novo Link] [Revogar] |

**Campos exibidos:**
- **Cliente:** Nome do cliente
- **Contato:** Telefone e e-mail (se cadastrado)
- **Situação:** Status do acesso
  - "Nunca acessou" = cliente novo
  - "Ativo" = já acessou pelo menos uma vez
  - "Token expirado" = último token expirou
- **Último Login:** Quando o cliente acessou pela última vez
- **Ações:** Botões disponíveis para o cliente

### 3. Gerar o Link

Dependendo da situação do cliente:

#### Cliente Novo (Nunca Acessou)

1. Clique no botão **Primeiro Acesso** (verde)
2. Aguarde alguns segundos
3. O link aparecerá temporariamente na tela

#### Cliente Existente

1. Clique no botão **Gerar Novo Link** (azul)
2. **IMPORTANTE:** Isso revoga todos os links antigos do cliente
3. Aguarde alguns segundos
4. O novo link aparecerá temporariamente na tela

**Nota:** O link é exibido apenas **uma vez** e fica visível por **5 minutos**. Depois disso, desaparece por segurança.

### 4. Enviar o Link ao Cliente

Após gerar o link, você tem duas opções:

#### Opção A: Enviar por WhatsApp (Recomendado)

1. Clique no botão **📱 WhatsApp** ao lado do link gerado
2. O WhatsApp Web/App será aberto automaticamente
3. Você verá uma mensagem pronta:
   ```
   Olá, [Nome do Cliente]! 
   
   Aqui está seu link exclusivo para acessar o Portal do Cliente:
   
   https://seusite.com/portal-do-cliente/?dps_token=...
   
   Este link é válido por 30 minutos.
   
   Atenciosamente,
   [Nome da Loja]
   ```
4. **Revise a mensagem** (pode editar se desejar)
5. Clique em **Enviar**

#### Opção B: Enviar por E-mail

1. Clique no botão **✉️ E-mail** ao lado do link gerado
2. Uma janela (modal) aparecerá com pré-visualização do e-mail
3. **Revise o conteúdo:**
   - **Para:** E-mail do cliente (pré-preenchido)
   - **Assunto:** "Acesso ao Portal do Cliente - [Nome da Loja]"
   - **Mensagem:** Texto personalizado com o link
4. Se quiser, **edite a mensagem** no campo de texto
5. Clique em **Confirmar e Enviar**
6. Aguarde a confirmação de envio

**Nota:** O e-mail só será enviado se o cliente tiver um e-mail cadastrado válido.

#### Opção C: Copiar e Colar Manualmente

Se preferir outro método de envio (SMS, Telegram, etc.):

1. Clique no botão **📋 Copiar** ao lado do link gerado
2. O link será copiado para a área de transferência
3. Cole onde desejar (WhatsApp, e-mail, SMS, etc.)
4. **Lembre-se:** O link expira em 30 minutos!

## Cenários Comuns

### Cliente Solicita Novo Link (Link Expirou)

**Problema:** Cliente diz que o link não funciona mais.

**Solução:**
1. Acesse **Logins de Clientes**
2. Localize o cliente
3. Clique em **Gerar Novo Link**
4. Envie o novo link por WhatsApp ou e-mail

**Por que acontece:** Links expiram após 30 minutos ou após serem usados uma vez.

### Cliente Perdeu o Link

**Problema:** Cliente perdeu a mensagem com o link.

**Solução:**
- **Se o link ainda está válido (< 30 min):** Reenvie a mesma mensagem
- **Se o link já expirou:** Gere um novo link (isso revoga o antigo automaticamente)

### Cliente Quer Acesso Permanente

**Problema:** Cliente pergunta se precisa solicitar link toda vez.

**Explicação:**
- Por enquanto, **sim**, o cliente precisa de um novo link a cada vez
- Isso é por segurança (links temporários são mais seguros que senhas fixas)
- **Futuramente**, haverá opção de tokens permanentes (em desenvolvimento)

**Orientação ao Cliente:**
- "Para sua segurança, geramos um link único a cada acesso"
- "É só pedir à nossa equipe quando quiser acessar novamente"
- "Leva apenas alguns segundos!"

### Revogar Acesso de um Cliente

**Cenário:** Cliente perdeu o celular ou você suspeita de acesso indevido.

**Solução:**
1. Acesse **Logins de Clientes**
2. Localize o cliente
3. Clique em **Revogar**
4. Confirme a revogação
5. Todos os links ativos desse cliente serão invalidados imediatamente
6. Cliente precisará solicitar um novo link

## Boas Práticas

### ✅ Faça

- **Gere o link apenas quando o cliente solicitar** (evita desperdício)
- **Envie por WhatsApp sempre que possível** (mais rápido e confiável)
- **Revise a mensagem antes de enviar** (personalize se necessário)
- **Confirme com o cliente que ele recebeu** (especialmente por e-mail)
- **Oriente o cliente sobre o prazo de 30 minutos**

### ❌ Não Faça

- **Não gere links "antecipadamente"** (vão expirar)
- **Não envie links por canais públicos** (redes sociais, grupos)
- **Não compartilhe o mesmo link com vários clientes** (cada cliente precisa do seu próprio)
- **Não guarde links em arquivos ou planilhas** (risco de segurança)

## Mensagens Personalizadas

### Modelo WhatsApp Padrão
```
Olá, [Nome]! 

Aqui está seu link exclusivo para acessar o Portal do Cliente:

[LINK]

Este link é válido por 30 minutos.

Atenciosamente,
[Nome da Loja]
```

### Modelo WhatsApp Personalizado (Sugestão)
```
Oi [Nome]! 😊

Segue seu acesso ao portal onde você pode ver fotos do [Nome do Pet], 
próximos agendamentos e muito mais:

[LINK]

⏰ Lembrando: o link vale por 30 minutinhos! 

Qualquer dúvida é só chamar!
```

### Modelo E-mail Padrão
```
Olá [Nome],

Você solicitou acesso ao Portal do Cliente.

Clique no link abaixo para acessar:
[LINK]

IMPORTANTE: Este link é válido por 30 minutos.

Se você não solicitou este acesso, ignore esta mensagem.

Atenciosamente,
Equipe [Nome da Loja]
```

## Perguntas Frequentes

### Por que os links expiram?

**Segurança.** Links temporários são muito mais seguros que senhas fixas porque:
- Não podem ser roubados e reutilizados
- Expiram automaticamente se interceptados
- Cada acesso usa um novo link único

### Posso aumentar o tempo de validade?

**Sim, mas não é recomendado.** Para alterar:
1. Edite `class-dps-portal-token-manager.php`
2. Localize `const DEFAULT_EXPIRATION_MINUTES = 30;`
3. Altere o valor (em minutos)
4. Salve o arquivo

**Atenção:** Aumentar muito o tempo de validade reduz a segurança.

### Por que não usar senha fixa?

**Senhas fixas têm vários problemas:**
- Clientes esquecem senhas
- Clientes criam senhas fracas
- Senhas podem ser roubadas
- Recuperação de senha é complexa
- Magic links são mais modernos e seguros

### O que acontece se eu gerar múltiplos links?

**Apenas o mais recente funciona.** Quando você gera um novo link:
1. Todos os links antigos são revogados
2. Apenas o link novo é válido
3. Se o cliente tentar usar um link antigo, verá mensagem de erro

### Posso ver quem acessou e quando?

**Sim!** A coluna "Último Login" mostra quando o cliente acessou pela última vez.

Para informações detalhadas, use WP-CLI:
```bash
wp db query "
  SELECT 
    p.post_title as cliente,
    t.used_at as acesso,
    t.ip_created as ip
  FROM wp_dps_portal_tokens t
  LEFT JOIN wp_posts p ON p.ID = t.client_id
  WHERE t.used_at IS NOT NULL
  ORDER BY t.used_at DESC
  LIMIT 20
"
```

## Suporte e Dúvidas

Se tiver dificuldades:

1. **Consulte primeiro:**
   - Este guia
   - `docs/fixes/PORTAL_ACCESS_TROUBLESHOOTING.md` (para problemas técnicos)
   - `plugins/desi-pet-shower-client-portal/TOKEN_AUTH_SYSTEM.md` (documentação técnica completa)

2. **Execute o teste automático:**
   ```bash
   wp eval-file plugins/desi-pet-shower-client-portal/test-portal-access.php
   ```

3. **Entre em contato:**
   - E-mail: suporte@probst.pro
   - Site: www.probst.pro

---

**Última atualização:** 2024-12-08  
**Versão do Add-on:** 2.4.1  
**Versão deste Guia:** 1.0
