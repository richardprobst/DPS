/**
 * Push Notifications Add-on - JavaScript
 * 
 * Gerencia inscrição e comunicação com Web Push API.
 *
 * @package DPS_Push_Addon
 * @since 1.0.0
 */

(function($) {
    'use strict';

    var DPSPush = {
        
        /**
         * Inicializa o módulo de push.
         */
        init: function() {
            this.bindEvents();
            this.checkStatus();
        },

        /**
         * Liga eventos aos elementos.
         */
        bindEvents: function() {
            $('#dps-push-subscribe').on('click', this.subscribe.bind(this));
            $('#dps-push-test').on('click', this.testPush.bind(this));
        },

        /**
         * Verifica status atual das notificações.
         */
        checkStatus: function() {
            var self = this;
            var $indicator = $('#dps-push-status-indicator');
            var $subscribeBtn = $('#dps-push-subscribe');
            var $testBtn = $('#dps-push-test');

            // Verificar suporte
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                $indicator
                    .removeClass('dps-push-checking')
                    .addClass('dps-push-unsupported')
                    .find('.dps-push-status-text')
                    .text(DPS_Push.messages.not_supported);
                $subscribeBtn.prop('disabled', true);
                return;
            }

            // Verificar permissão
            if (Notification.permission === 'denied') {
                $indicator
                    .removeClass('dps-push-checking')
                    .addClass('dps-push-denied')
                    .find('.dps-push-status-text')
                    .text(DPS_Push.messages.permission_denied);
                $subscribeBtn.prop('disabled', true);
                return;
            }

            // Verificar inscrição existente
            navigator.serviceWorker.ready.then(function(registration) {
                return registration.pushManager.getSubscription();
            }).then(function(subscription) {
                if (subscription) {
                    $indicator
                        .removeClass('dps-push-checking')
                        .addClass('dps-push-subscribed')
                        .find('.dps-push-status-text')
                        .text(DPS_Push.messages.subscribed);
                    $subscribeBtn.text('Notificações Ativas').prop('disabled', true);
                    $testBtn.show();
                } else {
                    $indicator
                        .removeClass('dps-push-checking')
                        .addClass('dps-push-not-subscribed')
                        .find('.dps-push-status-text')
                        .text('Notificações desativadas');
                    $subscribeBtn.prop('disabled', false);
                }
            }).catch(function(err) {
                console.error('Erro ao verificar inscrição push:', err);
                $indicator
                    .removeClass('dps-push-checking')
                    .addClass('dps-push-error')
                    .find('.dps-push-status-text')
                    .text(DPS_Push.messages.error);
            });
        },

        /**
         * Inscreve para notificações push.
         */
        subscribe: function(e) {
            e.preventDefault();
            var self = this;
            var $btn = $('#dps-push-subscribe');

            $btn.prop('disabled', true).text(DPS_Push.messages.subscribing);

            // Registrar Service Worker
            navigator.serviceWorker.register(DPS_Push.sw_url, { scope: '/' })
                .then(function(registration) {
                    console.log('Service Worker registrado:', registration);
                    return registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: self.urlBase64ToUint8Array(DPS_Push.vapid_public)
                    });
                })
                .then(function(subscription) {
                    console.log('Inscrito para push:', subscription);
                    
                    // Enviar inscrição para o servidor
                    return $.ajax({
                        url: DPS_Push.ajax_url,
                        method: 'POST',
                        data: {
                            action: 'dps_push_subscribe',
                            nonce: DPS_Push.nonce_subscribe,
                            subscription: JSON.stringify(subscription.toJSON())
                        }
                    });
                })
                .then(function(response) {
                    if (response.success) {
                        self.checkStatus();
                        alert(DPS_Push.messages.subscribed);
                    } else {
                        throw new Error(response.data.message);
                    }
                })
                .catch(function(err) {
                    console.error('Erro ao inscrever:', err);
                    
                    if (Notification.permission === 'denied') {
                        alert(DPS_Push.messages.permission_denied);
                    } else {
                        alert(DPS_Push.messages.error + ' ' + err.message);
                    }
                    
                    $btn.prop('disabled', false).text('Ativar Notificações');
                });
        },

        /**
         * Envia notificação de teste.
         */
        testPush: function(e) {
            e.preventDefault();
            var $btn = $('#dps-push-test');

            $btn.prop('disabled', true).text('Enviando...');

            $.ajax({
                url: DPS_Push.ajax_url,
                method: 'POST',
                data: {
                    action: 'dps_push_test',
                    nonce: DPS_Push.nonce_test
                }
            }).done(function(response) {
                if (response.success) {
                    alert(response.data.message);
                } else {
                    alert(response.data.message);
                }
            }).fail(function() {
                alert(DPS_Push.messages.error);
            }).always(function() {
                $btn.prop('disabled', false).text('Testar Notificação');
            });
        },

        /**
         * Converte chave VAPID de base64url para Uint8Array.
         */
        urlBase64ToUint8Array: function(base64String) {
            var padding = '='.repeat((4 - base64String.length % 4) % 4);
            var base64 = (base64String + padding)
                .replace(/\-/g, '+')
                .replace(/_/g, '/');

            var rawData = window.atob(base64);
            var outputArray = new Uint8Array(rawData.length);

            for (var i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            
            return outputArray;
        }
    };

    // Inicializar quando o DOM estiver pronto
    $(document).ready(function() {
        if (typeof DPS_Push !== 'undefined') {
            DPSPush.init();
        }
    });

})(jQuery);
