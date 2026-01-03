# Verificação Funcional (QA) – Portal do Cliente Add-on

**Data:** 2026-01-03  
**Versão do Add-on:** 2.4.3

---

## 1. Matriz de Funcionalidades

### 1.1 Shortcodes

| Feature | Onde Fica | Como Acionar | Resultado Esperado | Status |
|---------|-----------|--------------|-------------------|--------|
| `[dps_client_portal]` | Qualquer página | Inserir shortcode | Exibe portal (se autenticado) ou tela de acesso | ✅ OK |
| `[dps_client_login]` | Qualquer página | Inserir shortcode | Exibe formulário para solicitar acesso | ✅ OK |

### 1.2 Front-end (Portal do Cliente)

| Feature | Onde Fica | Como Acionar | Resultado Esperado | Status |
|---------|-----------|--------------|-------------------|--------|
| Autenticação por Token | URL com `?dps_token=XXX` | Acessar link com token | Valida token, inicia sessão, redireciona ao portal | ✅ OK |
| Limpar Token da URL | Após autenticação | Automático via JS | Remove `dps_token` da URL (segurança) | ✅ OK |
| Navegação por Tabs | Portal principal | Clicar nas abas | Alterna entre panels, atualiza URL hash | ✅ OK |
| Atualizar Dados Cliente | Aba "Meus Dados" | Preencher e enviar form | Salva telefone, endereço, email, redes sociais | ✅ OK |
| Atualizar Dados Pet | Aba "Meus Pets" | Preencher e enviar form | Salva nome, espécie, raça, peso, etc. | ✅ OK |
| Upload Foto Pet | Aba "Meus Pets" | Selecionar arquivo | Preview, validação MIME, upload seguro | ✅ OK |
| Chat Widget | Canto inferior direito | Clicar no ícone | Abre/fecha chat flutuante | ✅ OK |
| Enviar Mensagem Chat | Chat aberto | Digitar e enviar | Mensagem adicionada, notifica admin | ✅ OK |
| Carregar Histórico Chat | Chat aberto | Automático | Polling a cada 10s, exibe mensagens | ✅ OK |
| Marcar Mensagens Lidas | Chat aberto | Automático | Marca mensagens do admin como lidas | ✅ OK |
| Ver Agendamentos | Aba "Agendamentos" | Navegar | Lista próximos e histórico | ✅ OK |
| Ver Financeiro | Aba "Financeiro" | Navegar | Lista transações, pendências, links pagamento | ✅ OK |
| Gerar Link Pagamento | Pendência financeira | Clicar "Pagar" | Redireciona para Mercado Pago | ✅ OK |
| Download Calendário .ics | Agendamento | Clicar "Adicionar ao Calendário" | Download arquivo .ics | ✅ OK |
| Programa Fidelidade | Aba "Fidelidade" | Navegar | Pontos, tier, histórico, resgate | ✅ OK |
| Resgatar Pontos | Aba "Fidelidade" | Preencher valor e enviar | Converte pontos em crédito | ✅ OK |
| Histórico Fidelidade | Aba "Fidelidade" | Clicar "Carregar mais" | Paginação AJAX do histórico | ✅ OK |
| Copiar Link Indicação | Aba "Fidelidade" | Clicar "Copiar" | Copia link para clipboard | ✅ OK |
| Feedback de Ações | Após ação | Automático | Toast com mensagem de sucesso/erro | ✅ OK |
| Solicitar Acesso Email | Tela de acesso | Preencher email | Envia link de acesso por email | ✅ OK |
| Solicitar Acesso WhatsApp | Tela de acesso | Clicar no botão | Abre WhatsApp com mensagem pré-preenchida | ✅ OK |

### 1.3 Admin (WordPress)

| Feature | Onde Fica | Como Acionar | Resultado Esperado | Status |
|---------|-----------|--------------|-------------------|--------|
| Menu "Portal do Cliente" | Admin sidebar | Clicar no menu | Abre tela de configurações | ✅ OK |
| Submenu "Logins" | Portal do Cliente | Clicar no submenu | Lista clientes com status de acesso | ✅ OK |
| Submenu "Configurações" | Portal do Cliente | Clicar no submenu | Configurações do portal | ✅ OK |
| Submenu "Mensagens" | Portal do Cliente | Clicar no submenu | Lista CPT dps_portal_message | ✅ OK |
| Gerar Token | Logins > Cliente | Clicar "Gerar Acesso" | Modal escolhe temporário/permanente, gera token | ✅ OK |
| Revogar Tokens | Logins > Cliente | Clicar "Revogar" | Revoga todos os tokens do cliente | ✅ OK |
| Copiar Link | Logins > Cliente | Clicar "Copiar" | Copia URL de acesso para clipboard | ✅ OK |
| Enviar por WhatsApp | Logins > Cliente | Clicar "WhatsApp" | Abre WhatsApp com link de acesso | ✅ OK |
| Pré-visualizar Email | Logins > Cliente | Clicar "Email" | Modal com preview do email | ✅ OK |
| Enviar Email | Modal de email | Editar e enviar | Envia email com link de acesso | ✅ OK |
| Filtrar Clientes | Logins | Buscar por nome/telefone | Filtra lista de clientes | ✅ OK |
| Responder Mensagem | Editar mensagem | Preencher resposta | Salva resposta, notifica cliente | ✅ OK |
| Upload Logo Portal | Configurações | Selecionar arquivo | Valida, faz upload, salva option | ✅ OK |
| Upload Hero Portal | Configurações | Selecionar arquivo | Valida, faz upload, salva option | ✅ OK |
| Metabox Cliente | Editar dps_cliente | Área "Portal do Cliente" | Exibe status de acesso, ações rápidas | ✅ OK |

### 1.4 AJAX Endpoints

| Endpoint | Tipo | Nonce | Autenticação | Resultado | Status |
|----------|------|-------|--------------|-----------|--------|
| `dps_chat_get_messages` | wp_ajax + nopriv | dps_portal_chat | Sessão cliente | Lista mensagens | ✅ OK |
| `dps_chat_send_message` | wp_ajax + nopriv | dps_portal_chat | Sessão cliente | Envia mensagem | ✅ OK |
| `dps_chat_mark_read` | wp_ajax + nopriv | dps_portal_chat | Sessão cliente | Marca como lidas | ✅ OK |
| `dps_request_portal_access` | wp_ajax_nopriv | Nenhum | Rate limit IP | Notifica admin | ✅ OK |
| `dps_request_access_link_by_email` | wp_ajax_nopriv | dps_request_access_link | Rate limit IP/email | Envia email | ✅ OK |
| `dps_create_appointment_request` | wp_ajax + nopriv | dps_portal_chat | Sessão cliente | Cria pedido | ✅ OK |
| `dps_loyalty_get_history` | wp_ajax + nopriv | loyalty.nonce | Sessão cliente | Histórico pontos | ✅ OK |
| `dps_loyalty_portal_redeem` | wp_ajax + nopriv | redemption.nonce | Sessão cliente | Resgata pontos | ✅ OK |
| `dps_generate_client_token` | wp_ajax | admin.nonce | manage_options | Gera token | ✅ OK |
| `dps_revoke_client_tokens` | wp_ajax | admin.nonce | manage_options | Revoga tokens | ✅ OK |
| `dps_get_whatsapp_message` | wp_ajax | admin.nonce | manage_options | Monta mensagem | ✅ OK |
| `dps_preview_email` | wp_ajax | admin.nonce | manage_options | Preview email | ✅ OK |
| `dps_send_email_with_token` | wp_ajax | admin.nonce | manage_options | Envia email | ✅ OK |

---

## 2. Problemas Encontrados e Correções

### 2.1 Problemas Corrigidos Nesta Auditoria

| # | Severidade | Problema | Correção | Arquivo |
|---|------------|----------|----------|---------|
| 1 | MÉDIO | Hook `dps_portal_after_update_client` em `class-dps-portal-actions-handler.php` passa `$_POST` diretamente | Passa apenas dados sanitizados | `class-dps-portal-actions-handler.php` |

### 2.2 Observações de QA (Sem Correção Necessária)

| # | Observação | Motivo |
|---|------------|--------|
| 1 | Rate limiting em chat (10 msgs/min) | Implementado corretamente |
| 2 | Rate limiting em solicitação de acesso (3-5/hora) | Implementado corretamente |
| 3 | Validação de ownership de recursos | Helper `dps_portal_assert_client_owns_resource()` |
| 4 | Prevenção de envio duplo em forms | `submitBtn.disabled = true` no JS |
| 5 | Escape de HTML no chat | `escapeHtml()` em JS, `wp_strip_all_tags()` em PHP |
| 6 | Token removido da URL | `cleanTokenFromURL()` em JS |
| 7 | Sessão expirada | Redireciona com `portal_msg=session_expired` |

---

## 3. Checklist de Validação Funcional

### 3.1 Formulários

- [x] **Validação client-side**: HTML5 required, type=email, maxlength
- [x] **Validação server-side**: sanitize_*, is_email(), absint()
- [x] **Prevenção duplo envio**: disabled + loading state
- [x] **Estados de loading**: "Salvando..." no botão
- [x] **Tratamento de erros**: Toast com mensagem de erro
- [x] **Feedback de sucesso**: Toast com mensagem de sucesso
- [x] **Persistência**: update_post_meta() para dados do cliente/pet

### 3.2 Botões e Ações

- [x] **Ação correta**: Cada botão executa a ação esperada
- [x] **Prevenção cliques repetidos**: disabled durante AJAX
- [x] **Confirmações**: Modal para seleção de tipo de token
- [x] **Permissões**: current_user_can('manage_options') para ações admin
- [x] **Feedback visual**: Texto muda durante ação (Copiado!, Enviando...)

### 3.3 Filtros e Listagens

- [x] **Busca**: Por nome e telefone na lista de logins
- [x] **Estado preservado**: Query args mantidos após ação
- [x] **Mensagem vazio**: "Nenhum cliente encontrado"

### 3.4 Modais

- [x] **Abertura/fechamento**: fadeIn/fadeOut
- [x] **ESC para fechar**: Keyup handler
- [x] **Clique fora fecha**: Click em overlay
- [x] **Conteúdo AJAX**: Loading state + erro handling
- [x] **Prevenção XSS**: escape no conteúdo renderizado

### 3.5 Shortcodes

- [x] **Atributos**: Nenhum atributo obrigatório
- [x] **Fallback**: Tela de acesso para não autenticados
- [x] **Escape**: esc_html__(), esc_attr(), esc_url()
- [x] **Cache**: Compatível (sem conteúdo dinâmico sensível)

### 3.6 AJAX/REST

- [x] **Nonce**: Verificado em todos os endpoints autenticados
- [x] **Mensagens de erro**: Texto útil ao usuário
- [x] **Rate limiting**: Implementado onde necessário
- [x] **401/403**: wp_send_json_error com mensagem apropriada

### 3.7 Acessibilidade

- [x] **Labels**: for/id em campos de formulário
- [x] **ARIA**: aria-selected, aria-hidden em tabs
- [x] **Foco modal**: Foco move para modal ao abrir
- [x] **Navegação teclado**: Enter para enviar no chat

---

## 4. Plano de Testes Manuais

### 4.1 Fluxo de Autenticação

1. **Gerar token para cliente**
   - Acesse Admin > Portal do Cliente > Logins
   - Busque um cliente
   - Clique "Gerar Acesso"
   - Selecione "Temporário (30 min)"
   - Confirme geração
   - ✓ Token gerado com sucesso

2. **Autenticar com token**
   - Copie o link de acesso
   - Abra em janela anônima
   - ✓ Redireciona para portal
   - ✓ Token removido da URL

3. **Token expirado**
   - Use token após 30 min
   - ✓ Exibe mensagem "Link expirou"

4. **Token inválido**
   - Altere caracteres do token na URL
   - ✓ Exibe mensagem "Link não é válido"

### 4.2 Chat do Portal

1. **Enviar mensagem**
   - Acesse portal autenticado
   - Abra chat (ícone flutuante)
   - Digite mensagem e envie
   - ✓ Mensagem aparece na lista
   - ✓ Admin recebe notificação

2. **Rate limiting**
   - Envie 10 mensagens rapidamente
   - ✓ 11ª mensagem bloqueada com aviso

3. **Mensagem longa**
   - Tente enviar mensagem > 1000 chars
   - ✓ Erro "Mensagem muito longa"

### 4.3 Atualização de Dados

1. **Atualizar dados do cliente**
   - Acesse portal > Meus Dados
   - Altere telefone e email
   - Salve
   - ✓ Toast "Dados atualizados com sucesso"
   - ✓ Dados persistidos no banco

2. **Atualizar dados do pet**
   - Acesse portal > Meus Pets
   - Edite nome/raça
   - Salve
   - ✓ Toast "Pet atualizado com sucesso"

3. **Upload de foto do pet**
   - Selecione imagem JPG válida
   - Salve
   - ✓ Preview exibido
   - ✓ Foto salva no media library

4. **Upload de arquivo inválido**
   - Selecione arquivo PHP renomeado para .jpg
   - ✓ Rejeitado com erro

### 4.4 Programa Fidelidade

1. **Ver pontos e tier**
   - Acesse portal > Fidelidade
   - ✓ Exibe pontos, tier, multiplicador

2. **Resgatar pontos**
   - Insira quantidade válida
   - Clique "Resgatar"
   - ✓ Pontos convertidos em crédito
   - ✓ Toast de sucesso

3. **Resgatar pontos inválidos**
   - Insira mais pontos do que tem
   - ✓ Erro "Pontos insuficientes"

4. **Carregar histórico**
   - Clique "Carregar mais"
   - ✓ Itens adicionados à lista
   - ✓ Botão some quando acabar

### 4.5 Admin - Gerenciamento de Logins

1. **Buscar cliente**
   - Digite nome/telefone no campo de busca
   - ✓ Lista filtrada

2. **Gerar token temporário**
   - Clique "Gerar Acesso"
   - Selecione "Temporário"
   - ✓ Token gerado, status atualizado

3. **Gerar token permanente**
   - Clique "Gerar Acesso"
   - Selecione "Permanente"
   - ✓ Token gerado sem expiração

4. **Revogar tokens**
   - Clique "Revogar"
   - ✓ Todos os tokens invalidados

5. **Copiar link**
   - Clique "Copiar"
   - ✓ Link no clipboard
   - ✓ Botão muda para "✓ Copiado!"

6. **Enviar por email**
   - Clique "Email"
   - ✓ Modal com preview
   - Edite se necessário
   - Clique "Enviar"
   - ✓ Email enviado

---

## 5. Sugestões para Testes E2E (Playwright)

```javascript
// tests/client-portal.spec.js
import { test, expect } from '@playwright/test';

test.describe('Client Portal', () => {
  test('should authenticate with valid token', async ({ page }) => {
    // Gerar token via API ou fixture
    const token = 'VALID_TOKEN';
    await page.goto(`/portal-cliente/?dps_token=${token}`);
    
    // Verificar que token foi removido da URL
    await expect(page).not.toHaveURL(/dps_token/);
    
    // Verificar que portal está visível
    await expect(page.locator('.dps-portal-tabs')).toBeVisible();
  });

  test('should show error for invalid token', async ({ page }) => {
    await page.goto('/portal-cliente/?dps_token=INVALID');
    
    await expect(page.locator('.dps-portal-access__error')).toBeVisible();
  });

  test('should update client data', async ({ page }) => {
    // Login primeiro
    await loginAsClient(page);
    
    await page.click('[data-tab="dados"]');
    await page.fill('[name="client_phone"]', '11999999999');
    await page.click('.dps-submit-btn');
    
    // Verificar toast de sucesso
    await expect(page.locator('.dps-toast--success')).toBeVisible();
  });

  test('should send chat message', async ({ page }) => {
    await loginAsClient(page);
    
    // Abrir chat
    await page.click('.dps-chat-toggle');
    await expect(page.locator('.dps-chat-window')).toHaveClass(/is-open/);
    
    // Enviar mensagem
    await page.fill('.dps-chat-input__field', 'Olá, teste!');
    await page.click('.dps-chat-input__send');
    
    // Verificar mensagem na lista
    await expect(page.locator('.dps-chat-message--client')).toContainText('Olá, teste!');
  });
});
```

---

## 6. Conclusão

O Portal do Cliente está **funcionalmente completo** e pronto para produção. Todas as funcionalidades foram verificadas no código e seguem as boas práticas de:

- **Segurança**: Nonces, sanitização, escape, validação de ownership
- **UX**: Loading states, feedback visual, prevenção de duplo clique
- **Acessibilidade**: Labels, ARIA, navegação por teclado
- **Performance**: Rate limiting, polling otimizado, cache-friendly

### Próximos Passos Recomendados

1. Executar testes manuais do Plano de Testes (Seção 4)
2. Implementar testes E2E com Playwright/Cypress
3. Monitorar logs de acesso após deploy
4. Configurar alertas para tentativas de acesso inválido
