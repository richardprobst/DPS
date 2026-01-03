/**
 * Push Notifications Add-on - JavaScript
 * 
 * Gerencia inscrição e comunicação com Web Push API.
 *
 * @package DPS_Push_Addon
 * @since 1.0.0
 * @since 1.2.0 Adicionados botões de teste de relatórios e Telegram.
 * @since 1.3.0 Adicionada prevenção de duplo clique e validação client-side.
 */

(function($) {
    'use strict';

    var DPSPush = {
        
        /**
         * Flag para evitar duplo clique no formulário.
         */
        isSubmitting: false,

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
            
            // Botões de teste de relatórios por email.
            $('.dps-test-report-btn').on('click', this.testReport.bind(this));
            
            // Botão de teste de conexão Telegram.
            $('#dps-test-telegram').on('click', this.testTelegram.bind(this));

            // Prevenção de duplo clique no formulário de configurações.
            $('.dps-push-settings form').on('submit', this.handleFormSubmit.bind(this));

            // Validação de emails em tempo real.
            $('#dps_push_emails_agenda, #dps_push_emails_report').on('blur', this.validateEmailField.bind(this));

            // Validação de campo numérico.
            $('#dps_push_inactive_days').on('change', this.validateInactiveDays.bind(this));
        },

        /**
         * Manipula submit do formulário com prevenção de duplo clique.
         */
        handleFormSubmit: function(e) {
            var self = this;
            var $form = $(e.currentTarget);
            var $btn = $('#dps-push-save-btn');
            var $spinner = $('#dps-push-save-spinner');

            // Prevenir duplo clique
            if (this.isSubmitting) {
                e.preventDefault();
                return false;
            }

            // Validar emails antes do submit
            var emailsValid = true;
            $('#dps_push_emails_agenda, #dps_push_emails_report').each(function() {
                if (!self.validateEmailField({ currentTarget: this })) {
                    emailsValid = false;
                }
            });

            if (!emailsValid) {
                e.preventDefault();
                return false;
            }

            // Marcar como submetendo
            this.isSubmitting = true;

            // Alterar estado visual do botão
            $btn.prop('disabled', true)
                .text(DPS_Push.messages.saving || 'Salvando...');
            $spinner.addClass('is-active');

            // Não bloqueamos o submit normal, apenas sinalizamos visualmente
            return true;
        },

        /**
         * Valida campo de email separado por vírgula.
         */
        validateEmailField: function(e) {
            var $field = $(e.currentTarget);
            var value = $field.val().trim();
            var $error = $field.siblings('.dps-email-error');

            // Remover erro existente
            if ($error.length) {
                $error.remove();
            }

            if (!value) {
                return true; // Campo vazio é válido (usa admin_email)
            }

            var emails = value.split(',');
            var invalidEmails = [];
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            for (var i = 0; i < emails.length; i++) {
                var email = emails[i].trim();
                if (email && !emailRegex.test(email)) {
                    invalidEmails.push(email);
                }
            }

            if (invalidEmails.length > 0) {
                var msg = (DPS_Push.messages.invalid_email || 'Email inválido: ') + invalidEmails.join(', ');
                $field.after('<span class="dps-email-error" style="color: #ef4444; display: block; margin-top: 5px; font-size: 12px;">' + msg + '</span>');
                return false;
            }

            return true;
        },

        /**
         * Valida campo de dias de inatividade.
         */
        validateInactiveDays: function(e) {
            var $field = $(e.currentTarget);
            var value = parseInt($field.val(), 10);
            var min = parseInt($field.attr('min'), 10) || 7;
            var max = parseInt($field.attr('max'), 10) || 365;

            if (isNaN(value) || value < min) {
                $field.val(min);
            } else if (value > max) {
                $field.val(max);
            }
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

            // Prevenir duplo clique
            if ($btn.prop('disabled')) {
                return;
            }

            $btn.prop('disabled', true).text(DPS_Push.messages.subscribing);

            // Registrar Service Worker (usa scope padrão do diretório do SW)
            navigator.serviceWorker.register(DPS_Push.sw_url)
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

            // Prevenir duplo clique
            if ($btn.prop('disabled')) {
                return;
            }

            var originalText = $btn.text();
            $btn.prop('disabled', true).text(DPS_Push.messages.sending || 'Enviando...');

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
                $btn.prop('disabled', false).text(originalText);
            });
        },

        /**
         * Envia teste de relatório por email.
         *
         * @since 1.2.0
         */
        testReport: function(e) {
            e.preventDefault();
            var $btn = $(e.currentTarget);

            // Prevenir duplo clique
            if ($btn.prop('disabled')) {
                return;
            }

            var type = $btn.data('type');
            var $result = $('.dps-test-result[data-type="' + type + '"]');
            var originalText = $btn.html();

            $btn.prop('disabled', true).html('⏳ ' + (DPS_Push.messages.sending || 'Enviando...'));
            $result.removeClass('success error').text('');

            $.ajax({
                url: DPS_Push.ajax_url,
                method: 'POST',
                data: {
                    action: 'dps_push_test_report',
                    nonce: DPS_Push.nonce_test,
                    type: type
                }
            }).done(function(response) {
                if (response.success) {
                    $result.addClass('success').text('✓ ' + response.data.message);
                } else {
                    $result.addClass('error').text('✗ ' + response.data.message);
                }
            }).fail(function() {
                $result.addClass('error').text('✗ ' + DPS_Push.messages.error);
            }).always(function() {
                $btn.prop('disabled', false).html(originalText);
            });
        },

        /**
         * Testa conexão com Telegram.
         *
         * @since 1.2.0
         */
        testTelegram: function(e) {
            e.preventDefault();
            var $btn = $('#dps-test-telegram');

            // Prevenir duplo clique
            if ($btn.prop('disabled')) {
                return;
            }

            var $result = $('#dps-telegram-result');
            var originalText = $btn.html();

            $btn.prop('disabled', true).html('⏳ ' + (DPS_Push.messages.testing || 'Testando...'));
            $result.removeClass('success error').text('');

            $.ajax({
                url: DPS_Push.ajax_url,
                method: 'POST',
                data: {
                    action: 'dps_push_test_telegram',
                    nonce: DPS_Push.nonce_test
                }
            }).done(function(response) {
                if (response.success) {
                    $result.addClass('success').text('✓ ' + response.data.message);
                } else {
                    $result.addClass('error').text('✗ ' + response.data.message);
                }
            }).fail(function() {
                $result.addClass('error').text('✗ ' + DPS_Push.messages.error);
            }).always(function() {
                $btn.prop('disabled', false).html(originalText);
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
