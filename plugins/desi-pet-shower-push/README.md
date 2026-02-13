# desi.pet by PRObst – Push Notifications Add-on

Notificações push nativas do navegador e relatórios automáticos por email/Telegram para administradores do DPS. Receba alertas em tempo real sobre novos agendamentos, mudanças de status e reagendamentos.

## Funcionalidades

- **Notificações em tempo real**: Receba alertas mesmo com o navegador fechado
- **Multi-dispositivo**: Ative em quantos dispositivos quiser
- **Configurável**: Escolha quais eventos devem gerar notificações
- **Privado**: Dados ficam no seu servidor, sem terceiros
- **Relatórios por Email**: Agenda diária, relatório financeiro e pets inativos
- **Integração Telegram**: Receba relatórios diretamente no seu grupo ou chat

## Eventos Notificados

1. **Novos agendamentos**: Quando um cliente agenda um serviço
2. **Mudanças de status**: Quando outro usuário altera status de um agendamento
3. **Reagendamentos**: Quando um agendamento é reagendado para outra data/hora

## Relatórios Automáticos

1. **Agenda Diária**: Resumo dos agendamentos do dia
2. **Relatório Financeiro**: Receitas e despesas do dia
3. **Relatório Semanal**: Lista de pets sem atendimento há mais de X dias

## Requisitos

- WordPress 6.9+
- PHP 8.4+
- OpenSSL com suporte a curvas EC P-256
- HTTPS (obrigatório para Web Push)
- Navegador compatível:
  - Chrome 50+
  - Firefox 44+
  - Edge 17+
  - Safari 16+

## Instalação

1. Faça upload da pasta `desi-pet-shower-push` para `/wp-content/plugins/`
2. Ative o plugin no WordPress
3. Acesse **desi.pet by PRObst > Notificações**
4. Clique em "Ativar Notificações" e permita no navegador
5. Configure quais eventos devem gerar notificações
6. Configure destinatários e horários para relatórios por email
7. (Opcional) Configure integração com Telegram

## Como Funciona

### Arquitetura

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│   WordPress     │────▶│  Push Service   │────▶│   Navegador     │
│   (Servidor)    │     │ (Chrome/Firefox)│     │  (Notificação)  │
└─────────────────┘     └─────────────────┘     └─────────────────┘
        │                                               │
        │ Evento dispara                                │
        │ hook WordPress                                │
        ▼                                               ▼
┌─────────────────┐                            ┌─────────────────┐
│  DPS_Push_API   │                            │  Service Worker │
│  (RFC 8291)     │                            │   (push-sw.js)  │
└─────────────────┘                            └─────────────────┘
```

### Estrutura de Classes (v2.0.0)

```
desi-pet-shower-push-addon.php      → Bootstrap + AJAX handlers (DPS_Push_Addon)
includes/
├── class-dps-push-api.php          → Web Push API com criptografia RFC 8291
├── class-dps-push-admin.php        → Página admin e assets (DPS_Push_Admin)
├── class-dps-push-settings.php     → Salvar/validar configurações (DPS_Push_Settings)
├── class-dps-push-notifications.php → Handlers de eventos (DPS_Push_Notifications)
├── class-dps-push-telegram.php     → Integração Telegram (DPS_Push_Telegram)
└── class-dps-email-reports.php     → Relatórios por email (DPS_Email_Reports)
```

### Fluxo de Notificação

1. Evento ocorre no DPS (novo agendamento, mudança de status, etc.)
2. Hook WordPress dispara (`dps_base_after_save_appointment`, etc.)
3. `DPS_Push_API::send_to_all_admins()` é chamado
4. Para cada admin inscrito, envia notificação via Web Push
5. Service Worker recebe e exibe notificação no dispositivo

### Chaves VAPID

O plugin gera automaticamente um par de chaves VAPID (Voluntary Application Server Identification) na ativação. Estas chaves são usadas para autenticar o servidor junto aos serviços de push dos navegadores.

## API

### Enviar para usuário específico

```php
DPS_Push_API::send_to_user( $user_id, [
    'title' => 'Título da notificação',
    'body'  => 'Corpo da mensagem',
    'icon'  => 'URL do ícone',
    'tag'   => 'identificador-unico',
    'data'  => [
        'type' => 'custom_event',
        'url'  => 'https://...',
    ],
] );
```

### Enviar para todos os admins

```php
DPS_Push_API::send_to_all_admins( [
    'title' => 'Novo Agendamento',
    'body'  => 'Rex (Maria) - 15/12 às 14:00',
], [ $exclude_user_id ] ); // Opcional: excluir usuários
```

## Hooks Disponíveis

### Filtros

```php
// Customizar payload antes de enviar
add_filter( 'dps_push_payload', function( $payload, $event_type ) {
    // Modificar payload
    return $payload;
}, 10, 2 );
```

## Troubleshooting

### Notificações não aparecem

1. Verifique se o site usa HTTPS
2. Verifique se permitiu notificações no navegador
3. Teste em aba anônima
4. Verifique console do navegador para erros

### Service Worker não registra

1. Verifique se o arquivo `push-sw.js` está acessível
2. Verifique se o MIME type é `application/javascript`
3. Limpe cache do navegador

### Erros de VAPID

1. Desative e reative o plugin para gerar novas chaves
2. Verifique se OpenSSL está disponível no PHP

## Changelog

### v2.0.0 (2026-02-13)

**Reescrita completa do add-on — Arquitetura modular + Web Push funcional**

- **Web Push com criptografia RFC 8291**: Payload agora é criptografado com ECDH + HKDF + AES-128-GCM (aes128gcm content encoding), permitindo que notificações push funcionem corretamente com todos os serviços de push (FCM, Mozilla, Apple, Windows).
- **Arquitetura modular**: Classe monolítica (1200 linhas) dividida em 6 classes com responsabilidades claras:
  - `DPS_Push_API` — Web Push API com criptografia e VAPID
  - `DPS_Push_Admin` — Página admin e gestão de assets
  - `DPS_Push_Settings` — Validação e salvamento de configurações
  - `DPS_Push_Notifications` — Handlers de eventos de agendamento
  - `DPS_Push_Telegram` — Serviço de integração com Telegram
  - `DPS_Email_Reports` — Relatórios por email (mantido, com Telegram desacoplado)
- **VAPID JWT robusto**: Removido fallback inseguro de geração de chaves aleatórias; agora requer OpenSSL P-256 funcional.
- **Limpeza de subscriptions**: Diferenciação entre subscriptions expiradas (404/410 → removidas) e outros erros (mantidas para retry).
- **Telegram desacoplado**: Integração Telegram extraída para classe própria, eliminando acoplamento com DPS_Email_Reports.
- **Construtor privado**: Singleton agora com construtor privado conforme padrão correto.

### v1.3.0 (2026-01-03)

**Auditoria de Segurança Completa**

- **SSRF Protection**: Validação de whitelist para endpoints de push (FCM, Mozilla, Windows, Apple) e Telegram API
- **SQL Injection Fix**: Corrigido uso de query direta em uninstall.php
- **Input Validation**: Sanitização completa de subscription JSON e validação de formato
- **Authorization**: Verificação de capability em todas as ações AJAX
- **Token Protection**: Campo de token Telegram agora usa `type="password"`
- **Log Security**: Whitelist de níveis de log para prevenir execução de métodos arbitrários
- **Data Validation**: Validação de formato de datas antes de queries

**Verificação Funcional e UX**

- **Prevenção de duplo clique**: Botão de salvar com spinner e estado desabilitado
- **Validação client-side**: Emails validados em tempo real, dias de inatividade com limites
- **Feedback visual**: Mensagens de sucesso/erro após salvar configurações
- **Strings internacionalizadas**: Estados de loading traduzíveis
- **Service Worker**: Removidos caminhos hardcoded de ícones
- **Acessibilidade**: Estilos :focus-visible para navegação por teclado

### v1.2.0 (2025-12-17)

- **Menu admin visível**: Menu agora registrado sob "desi.pet by PRObst > Notificações"
- **Botões de teste**: Botões "Enviar Teste" para cada tipo de relatório (Agenda, Financeiro, Semanal)
- **Teste de conexão Telegram**: Valida configuração e envia mensagem de teste
- **Carregamento de assets otimizado**: CSS/JS carregados apenas em páginas DPS relevantes
- **uninstall.php corrigido**: Limpa todas as options e cron jobs

### v1.1.0 (2025-12-02)

- Status card com próximos envios
- Checkbox habilitar/desabilitar por relatório
- Threshold de inatividade configurável (padrão: 30 dias)
- Integração com DPS_Logger
- CSS e JS externos

### v1.0.0

- Lançamento inicial
- Notificações push via Web Push API
- Relatórios por email (agenda, financeiro, semanal)
- Integração com Telegram

## Licença

GPL-2.0+
