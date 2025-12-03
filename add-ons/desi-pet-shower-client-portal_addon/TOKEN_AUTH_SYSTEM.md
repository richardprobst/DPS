# Sistema de Autenticação por Token (Magic Link) - Documentação Completa

## Visão Geral

O Portal do Cliente do DPS by PRObst (DPS) agora utiliza um sistema moderno de autenticação baseado em **links com token (magic links)**, eliminando completamente a necessidade de senhas fixas.

## Como Funciona

### Para o Cliente

1. **Solicitar Acesso**
   - Cliente acessa a URL do Portal do Cliente
   - Vê uma tela minimalista pedindo para solicitar acesso
   - Clica em "Quero acesso ao meu portal"
   - WhatsApp abre com mensagem pronta para a loja

2. **Receber Link**
   - Equipe da loja gera um link exclusivo
   - Cliente recebe o link por WhatsApp ou e-mail
   - Link é válido por 30 minutos

3. **Acessar Portal**
   - Cliente clica no link recebido
   - É autenticado automaticamente
   - Acessa o portal sem digitar senha
   - Token é invalidado após uso (single use)

4. **Usar o Portal**
   - Cliente navega normalmente pelo portal
   - Botão "Sair" disponível no header
   - Ao sair, pode solicitar novo link quando quiser

### Para a Equipe

1. **Gerenciar Logins**
   - Acesse "Portal do Cliente → Logins" no admin
   - Veja lista de todos os clientes com status de acesso

2. **Gerar Links**
   - **Primeiro Acesso:** Para clientes novos (botão verde)
   - **Gerar Novo Link:** Para clientes que já acessaram (regenera e revoga anteriores)
   - Link é exibido temporariamente por 5 minutos

3. **Enviar Links**
   - **WhatsApp:** Clique no botão, abre WhatsApp com mensagem pronta, envie manualmente
   - **E-mail:** Clique no botão, edite a mensagem no modal, confirme o envio

4. **Revogar Acesso**
   - Botão "Revogar" invalida todos os links ativos do cliente
   - Cliente precisará solicitar novo link

## Arquitetura Técnica

### Componentes Principais

#### 1. Gerenciamento de Tokens
**Classe:** `DPS_Portal_Token_Manager`  
**Localização:** `includes/class-dps-portal-token-manager.php`

**Responsabilidades:**
- Gerar tokens seguros (64 caracteres)
- Validar tokens recebidos
- Marcar tokens como usados
- Revogar tokens ativos
- Limpar tokens expirados (cron job)

**Tabela do Banco:**
```sql
wp_dps_portal_tokens
├── id (bigint) - PK auto_increment
├── client_id (bigint) - FK para dps_cliente
├── token_hash (varchar 255) - Hash do token
├── type (varchar 50) - 'login' ou 'first_access'
├── created_at (datetime)
├── expires_at (datetime)
├── used_at (datetime NULL)
├── revoked_at (datetime NULL)
├── ip_created (varchar 45)
└── user_agent (text)
```

**Índices:**
- PRIMARY KEY (id)
- KEY client_id
- KEY token_hash
- KEY expires_at
- KEY type

#### 2. Gerenciamento de Sessões
**Classe:** `DPS_Portal_Session_Manager`  
**Localização:** `includes/class-dps-portal-session-manager.php`

**Responsabilidades:**
- Iniciar sessões PHP seguras
- Autenticar clientes no portal
- Validar sessões ativas
- Processar logout
- Gerenciar tempo de vida das sessões (24h)

**Configurações de Segurança:**
```php
session.cookie_httponly = 1
session.cookie_secure = 1 (quando HTTPS)
session.cookie_samesite = Strict
session.use_strict_mode = 1
```

#### 3. Ações Administrativas
**Classe:** `DPS_Portal_Admin_Actions`  
**Localização:** `includes/class-dps-portal-admin-actions.php`

**Responsabilidades:**
- Processar geração de tokens
- Processar revogação de tokens
- Preparar mensagens para WhatsApp
- Gerar pré-visualização de e-mails
- Enviar e-mails confirmados

**Endpoints AJAX:**
- `wp_ajax_dps_generate_client_token`
- `wp_ajax_dps_revoke_client_tokens`
- `wp_ajax_dps_get_whatsapp_message`
- `wp_ajax_dps_preview_email`
- `wp_ajax_dps_send_email_with_token`

### Templates

#### Tela de Acesso Pública
**Arquivo:** `templates/portal-access.php`

**Características:**
- Design minimalista com card centralizado
- Botão para solicitar acesso via WhatsApp
- Mensagens de erro contextualizadas
- CSS responsivo embutido

#### Interface Administrativa
**Arquivo:** `templates/admin-logins.php`

**Características:**
- Tabela responsiva de clientes
- Colunas: Cliente, Contato, Situação, Último Login, Ações
- Exibição temporária de links gerados
- Modal de pré-visualização de e-mail
- CSS responsivo embutido

### JavaScript

**Arquivo:** `assets/js/portal-admin.js`

**Funcionalidades:**
- Copiar links para clipboard
- Abrir/fechar modal de e-mail
- AJAX para pré-visualização de e-mail
- AJAX para envio de e-mail
- Feedback visual em tempo real

## Segurança

### Camadas de Proteção

1. **Geração de Tokens**
   - `bin2hex(random_bytes(32))` - 64 caracteres aleatórios
   - Criptograficamente seguro
   - Imprevisível

2. **Armazenamento**
   - `password_hash($token, PASSWORD_DEFAULT)`
   - Bcrypt com salt automático
   - Impossível reverter

3. **Validação**
   - `password_verify($token_plain, $token_hash)`
   - Comparação segura
   - Resistente a timing attacks

4. **Expiração**
   - Padrão: 30 minutos
   - Configurável via constante
   - Validação em cada uso

5. **Single Use**
   - Token marcado como usado após validação
   - Não pode ser reutilizado
   - Previne replay attacks

6. **Revogação Manual**
   - Administrador pode invalidar tokens
   - Útil em casos de suspeita de vazamento
   - Revoga todos os tokens ativos do cliente

7. **Nonces em Ações**
   - Todas as ações administrativas protegidas
   - Previne CSRF
   - Validação obrigatória

8. **Permissões**
   - `manage_options` para ações administrativas
   - Verificação em cada requisição
   - Sem bypass possível

9. **Sanitização**
   - Todos os inputs sanitizados
   - `sanitize_text_field()`, `sanitize_textarea_field()`, etc.
   - Previne injection attacks

10. **Escape de Saídas**
    - Todos os outputs escapados
    - `esc_html()`, `esc_attr()`, `esc_url()`, etc.
    - Previne XSS

11. **Session Security**
    - Cookies com flags httponly, secure, samesite
    - Modo estrito habilitado
    - Regeneração de session_id em autenticação
    - Previne session fixation

12. **E-mails Plain Text**
    - Apenas formato texto puro
    - Sem HTML permitido
    - Previne social engineering

### Validações CodeQL

**Status:** ✅ 0 alertas de segurança

**Linguagens Analisadas:**
- JavaScript
- PHP (via análise estática manual)

## Fluxo de Dados

```
┌──────────────┐
│   Cliente    │
│ Solicita     │
│   Acesso     │
└──────┬───────┘
       │
       ▼
┌──────────────┐
│  WhatsApp    │
│  (Manual)    │
└──────┬───────┘
       │
       ▼
┌──────────────┐
│   Equipe     │
│ Gera Token   │
└──────┬───────┘
       │
       ▼
┌──────────────┐
│    Banco     │
│ wp_dps_      │
│portal_tokens │
└──────┬───────┘
       │
       ▼
┌──────────────┐
│  WhatsApp    │
│  ou E-mail   │
└──────┬───────┘
       │
       ▼
┌──────────────┐
│   Cliente    │
│ Clica Link   │
└──────┬───────┘
       │
       ▼
┌──────────────┐
│  Validação   │
│    Token     │
└──────┬───────┘
       │
       ├─ Inválido ──► Mensagem de erro
       │
       └─ Válido ────► Autenticação
                       │
                       ▼
                  ┌────────────┐
                  │   Sessão   │
                  │   PHP      │
                  └─────┬──────┘
                        │
                        ▼
                  ┌────────────┐
                  │   Portal   │
                  │ do Cliente │
                  └────────────┘
```

## Migração do Sistema Antigo

### Compatibilidade Retroativa

O sistema mantém compatibilidade temporária com o método antigo:

```php
// Método novo (priorizado)
$client_id = $session_manager->get_authenticated_client_id();

// Fallback para método antigo
if ( ! $client_id ) {
    $client_id = $this->get_client_id_for_current_user();
}
```

### Plano de Migração

**Fase 1 (Atual - v2.0.0):**
- ✅ Sistema novo implementado
- ✅ Sistema antigo mantido como fallback
- ✅ Clientes existentes podem continuar usando

**Fase 2 (v2.x.x):**
- Notificações para clientes migrarem
- Documentação de migração
- Suporte a ambos os sistemas

**Fase 3 (v3.0.0):**
- Remoção completa do sistema antigo
- Apenas tokens funcionam
- Breaking change documentado

### Ações para Clientes Existentes

1. **Após Atualização:**
   - Clientes com usuário/senha ainda podem usar (temporário)
   - Recomendado migrar para novo sistema

2. **Para Migrar:**
   - Cliente solicita novo link via WhatsApp
   - Equipe gera link na tela de logins
   - Cliente usa o link e acessa normalmente

3. **Benefícios:**
   - Sem necessidade de lembrar senhas
   - Acesso mais rápido e fácil
   - Mais seguro (tokens temporários)

## Configuração

### Opções do WordPress

**Número de WhatsApp da Loja:**
```php
// Armazenado em:
get_option( 'dps_whatsapp_number' )

// Usado para:
// - Botão "Quero acesso" na tela pública
// - Envio de links por WhatsApp
```

### Constantes Configuráveis

**Expiração de Tokens:**
```php
// Em class-dps-portal-token-manager.php
const DEFAULT_EXPIRATION_MINUTES = 30;

// Para mudar:
// 1. Editar o arquivo
// 2. Alterar o valor (em minutos)
// 3. Salvar
```

**Tempo de Vida da Sessão:**
```php
// Em class-dps-portal-session-manager.php
const SESSION_LIFETIME = 86400; // 24 horas

// Para mudar:
// 1. Editar o arquivo
// 2. Alterar o valor (em segundos)
// 3. Salvar
```

**Cleanup de Tokens:**
```php
// Em class-dps-portal-token-manager.php
// Linha ~321
$threshold = date( 'Y-m-d H:i:s', strtotime( '-30 days' ) );

// Para mudar:
// 1. Editar o arquivo
// 2. Alterar '-30 days' para o período desejado
// 3. Salvar
```

## Manutenção

### Cron Jobs

**Cleanup de Tokens Expirados:**
- Hook: `dps_portal_cleanup_tokens`
- Frequência: Hourly
- Função: Remove tokens expirados há mais de 30 dias
- Registrado em: `class-dps-portal-token-manager.php`

### Logs

Não há logs nativos implementados. Para debug:

1. Ativar `WP_DEBUG` e `WP_DEBUG_LOG`
2. Verificar `/wp-content/debug.log`
3. Procurar por erros relacionados a DPS Portal

### Troubleshooting

**Cliente não recebe link:**
- Verificar número de WhatsApp correto
- Verificar e-mail correto
- Verificar se token foi gerado (tabela no banco)

**Token inválido:**
- Verificar se token expirou (30 min)
- Verificar se token foi usado
- Verificar se token foi revogado
- Gerar novo link

**Sessão expira rápido:**
- Verificar `SESSION_LIFETIME` (padrão 24h)
- Verificar configurações de sessão do servidor
- Verificar se cliente tem cookies habilitados

**E-mail não é enviado:**
- Verificar configuração de SMTP do WordPress
- Testar `wp_mail()` com outro plugin
- Verificar logs do servidor de e-mail

## Integrações Futuras

### IA para Sugestões de Texto

O sistema já está preparado para integração com IA:

```php
// Em DPS_Portal_Admin_Actions
if ( class_exists( 'DPS_AI_Message_Assistant' ) ) {
    // Usar IA para sugerir mensagens
    $suggestion = DPS_AI_Message_Assistant::suggest_whatsapp_message( $context );
}
```

**Quando IA Disponível:**
- Botão "Sugerir com IA" aparece
- IA gera sugestão de mensagem
- Usuário pode editar antes de enviar
- Nunca envia automaticamente

### Notificações Automáticas

Possível implementar notificações quando cliente solicita acesso:

```php
// Hook potencial
do_action( 'dps_portal_access_requested', $client_id );

// Handler exemplo
add_action( 'dps_portal_access_requested', function( $client_id ) {
    // Enviar notificação para equipe
    // Via e-mail, WhatsApp, etc.
} );
```

### Dashboard de Métricas

Possível implementar dashboard com:
- Tokens gerados por período
- Taxa de uso de tokens
- Clientes mais ativos
- Tempo médio de resposta da equipe

## Desenvolvimento

### Estrutura de Arquivos

```
desi-pet-shower-client-portal_addon/
├── desi-pet-shower-client-portal.php
├── includes/
│   ├── class-dps-client-portal.php
│   ├── class-dps-portal-token-manager.php
│   ├── class-dps-portal-session-manager.php
│   └── class-dps-portal-admin-actions.php
├── templates/
│   ├── portal-access.php
│   └── admin-logins.php
├── assets/
│   ├── css/
│   │   └── client-portal.css
│   └── js/
│       ├── client-portal.js
│       └── portal-admin.js
└── uninstall.php
```

### Hooks Expostos

**Após autenticação:**
```php
do_action( 'dps_portal_client_authenticated', $client_id );
```

**Após logout:**
```php
do_action( 'dps_portal_client_logged_out', $client_id );
```

**Após gerar token:**
```php
do_action( 'dps_portal_token_generated', $client_id, $token_type );
```

**Após revogar tokens:**
```php
do_action( 'dps_portal_tokens_revoked', $client_id, $revoked_count );
```

### Filtros Disponíveis

**Modificar expiração de token:**
```php
apply_filters( 'dps_portal_token_expiration_minutes', 30, $client_id, $token_type );
```

**Modificar mensagem de WhatsApp:**
```php
apply_filters( 'dps_portal_whatsapp_message', $message, $client_id, $access_url );
```

**Modificar template de e-mail:**
```php
apply_filters( 'dps_portal_email_template', $template, $client_id, $access_url );
```

## Suporte

### Documentação Adicional

- `CHANGELOG.md` - Histórico de versões
- `ANALYSIS.md` - Arquitetura geral do DPS
- `AGENTS.md` - Guia para desenvolvedores

### Contato

Para dúvidas ou problemas:
1. Verificar esta documentação primeiro
2. Consultar CHANGELOG.md
3. Abrir issue no repositório

## Licença

Este add-on faz parte do DPS by PRObst (DPS) e segue a mesma licença do projeto principal.

---

**Versão:** 2.0.0  
**Data de Lançamento:** 2024-01-22  
**Autor:** PRObst  
**Status:** ✅ Production Ready
