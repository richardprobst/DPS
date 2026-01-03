/**
 * Push Notifications Service Worker
 * 
 * Recebe e exibe notificações push mesmo com o navegador fechado.
 *
 * @package DPS_Push_Addon
 * @since 1.0.0
 */

// Instalação do Service Worker
self.addEventListener('install', function(event) {
    console.log('DPS Push SW: Instalado');
    self.skipWaiting();
});

// Ativação do Service Worker
self.addEventListener('activate', function(event) {
    console.log('DPS Push SW: Ativado');
    event.waitUntil(clients.claim());
});

// Recebimento de notificação push
self.addEventListener('push', function(event) {
    console.log('DPS Push SW: Notificação recebida', event);

    var data = {
        title: 'desi.pet by PRObst',
        body: 'Nova notificação',
        icon: '',  // Será definido pelo payload ou usa default do navegador
        badge: '',
        tag: 'dps-notification',
        requireInteraction: false,
        data: {}
    };

    // Tentar parsear dados da notificação
    if (event.data) {
        try {
            var payload = event.data.json();
            data = Object.assign(data, payload);
        } catch (e) {
            data.body = event.data.text();
        }
    }

    var options = {
        body: data.body,
        tag: data.tag,
        requireInteraction: data.requireInteraction,
        data: data.data,
        vibrate: [200, 100, 200],
        actions: [
            {
                action: 'open',
                title: 'Abrir'
            },
            {
                action: 'close',
                title: 'Fechar'
            }
        ]
    };

    // Adicionar ícone e badge apenas se fornecidos
    if (data.icon) {
        options.icon = data.icon;
    }
    if (data.badge) {
        options.badge = data.badge;
    }

    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

// Clique na notificação
self.addEventListener('notificationclick', function(event) {
    console.log('DPS Push SW: Clique na notificação', event);

    event.notification.close();

    if (event.action === 'close') {
        return;
    }

    // Determinar URL a abrir
    var url = '/wp-admin/admin.php?page=desi-pet-shower';
    
    if (event.notification.data && event.notification.data.url) {
        url = event.notification.data.url;
    }

    // Abrir ou focar janela existente
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then(function(clientList) {
                // Procurar janela existente
                for (var i = 0; i < clientList.length; i++) {
                    var client = clientList[i];
                    if (client.url.indexOf('/wp-admin/') !== -1 && 'focus' in client) {
                        return client.focus();
                    }
                }
                // Abrir nova janela
                if (clients.openWindow) {
                    return clients.openWindow(url);
                }
            })
    );
});

// Fechamento da notificação
self.addEventListener('notificationclose', function(event) {
    console.log('DPS Push SW: Notificação fechada', event);
});
