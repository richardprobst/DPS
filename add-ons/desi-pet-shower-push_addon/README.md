# DPS by PRObst – Push Notifications Add-on

Notificações push nativas do navegador para administradores do DPS. Receba alertas em tempo real sobre novos agendamentos, mudanças de status e reagendamentos.

## Funcionalidades

- **Notificações em tempo real**: Receba alertas mesmo com o navegador fechado
- **Multi-dispositivo**: Ative em quantos dispositivos quiser
- **Configurável**: Escolha quais eventos devem gerar notificações
- **Privado**: Dados ficam no seu servidor, sem terceiros

## Eventos Notificados

1. **Novos agendamentos**: Quando um cliente agenda um serviço
2. **Mudanças de status**: Quando outro usuário altera status de um agendamento
3. **Reagendamentos**: Quando um agendamento é reagendado para outra data/hora

## Requisitos

- WordPress 6.0+
- PHP 7.4+
- HTTPS (obrigatório para Web Push)
- Navegador compatível:
  - Chrome 50+
  - Firefox 44+
  - Edge 17+
  - Safari 16+

## Instalação

1. Faça upload da pasta `desi-pet-shower-push_addon` para `/wp-content/plugins/`
2. Ative o plugin no WordPress
3. Acesse **DPS by PRObst > Push Notifications**
4. Clique em "Ativar Notificações" e permita no navegador
5. Configure quais eventos devem gerar notificações

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
│  send_to_user() │                            │   (push-sw.js)  │
└─────────────────┘                            └─────────────────┘
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

## Licença

GPL-2.0+
