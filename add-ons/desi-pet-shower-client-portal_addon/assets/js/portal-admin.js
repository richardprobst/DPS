/**
 * Script para a interface administrativa de logins do Portal do Cliente
 *
 * @package DPS_Client_Portal
 * @since 2.0.0
 */

(function($) {
    'use strict';

    /**
     * Gerenciador de modais
     */
    const ModalManager = {
        /**
         * Abre um modal
         */
        open: function(modalId) {
            const $modal = $('#' + modalId);
            if ($modal.length) {
                $modal.fadeIn(200);
                $('body').addClass('dps-modal-open');
            }
        },

        /**
         * Fecha um modal
         */
        close: function(modalId) {
            const $modal = $('#' + modalId);
            if ($modal.length) {
                $modal.fadeOut(200);
                $('body').removeClass('dps-modal-open');
            }
        },

        /**
         * Fecha todos os modais
         */
        closeAll: function() {
            $('.dps-modal').fadeOut(200);
            $('body').removeClass('dps-modal-open');
        }
    };

    /**
     * Gerenciador de tokens admin
     */
    const TokenAdmin = {
        /**
         * Inicializa eventos
         */
        init: function() {
            this.bindCopyButtons();
            this.bindEmailPreviewButtons();
            this.bindModalCloseButtons();
            this.bindEmailSendButton();
        },

        /**
         * Bind para botões de copiar
         */
        bindCopyButtons: function() {
            $(document).on('click', '.dps-copy-token', function(e) {
                e.preventDefault();
                
                const $button = $(this);
                const url = $button.data('url');
                
                if (!url) {
                    return;
                }

                // Tenta copiar para clipboard
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url)
                        .then(function() {
                            TokenAdmin.showCopyFeedback($button, true);
                        })
                        .catch(function() {
                            TokenAdmin.showCopyFeedback($button, false);
                        });
                } else {
                    // Fallback para navegadores antigos
                    const $temp = $('<input>');
                    $('body').append($temp);
                    $temp.val(url).select();
                    const success = document.execCommand('copy');
                    $temp.remove();
                    TokenAdmin.showCopyFeedback($button, success);
                }
            });
        },

        /**
         * Mostra feedback visual de cópia
         */
        showCopyFeedback: function($button, success) {
            const originalText = $button.text();
            const newText = success ? '✓ Copiado!' : '✗ Erro';
            
            $button.text(newText);
            
            setTimeout(function() {
                $button.text(originalText);
            }, 2000);
        },

        /**
         * Bind para botões de pré-visualização de e-mail
         */
        bindEmailPreviewButtons: function() {
            $(document).on('click', '.dps-preview-email', function(e) {
                e.preventDefault();
                
                const $button = $(this);
                const clientId = $button.data('client-id');
                const accessUrl = $button.data('url');
                
                if (!clientId || !accessUrl) {
                    alert('Dados incompletos para pré-visualizar e-mail.');
                    return;
                }

                TokenAdmin.loadEmailPreview(clientId, accessUrl);
            });
        },

        /**
         * Carrega pré-visualização de e-mail
         */
        loadEmailPreview: function(clientId, accessUrl) {
            // Mostra loading
            const $modal = $('#dps-email-preview-modal');
            $modal.find('#dps-email-subject').val('Carregando...');
            $modal.find('#dps-email-body').val('Carregando...');
            $modal.data('client-id', clientId);
            $modal.data('access-url', accessUrl);
            
            ModalManager.open('dps-email-preview-modal');

            // Faz requisição AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'dps_preview_email',
                    nonce: dpsPortalAdmin.nonce,
                    client_id: clientId,
                    access_url: accessUrl
                },
                success: function(response) {
                    if (response.success && response.data) {
                        $modal.find('#dps-email-subject').val(response.data.subject || '');
                        $modal.find('#dps-email-body').val(response.data.body || '');
                    } else {
                        alert('Não foi possível carregar a pré-visualização: ' + (response.data.message || 'Erro desconhecido'));
                        ModalManager.close('dps-email-preview-modal');
                    }
                },
                error: function() {
                    alert('Erro ao conectar com o servidor.');
                    ModalManager.close('dps-email-preview-modal');
                }
            });
        },

        /**
         * Bind para botões de fechar modal
         */
        bindModalCloseButtons: function() {
            $(document).on('click', '.dps-modal__close', function(e) {
                e.preventDefault();
                ModalManager.closeAll();
            });

            $(document).on('click', '.dps-modal__overlay', function(e) {
                ModalManager.closeAll();
            });

            // ESC para fechar
            $(document).on('keyup', function(e) {
                if (e.key === 'Escape' || e.keyCode === 27) {
                    ModalManager.closeAll();
                }
            });
        },

        /**
         * Bind para botão de enviar e-mail
         */
        bindEmailSendButton: function() {
            $(document).on('click', '#dps-confirm-send-email', function(e) {
                e.preventDefault();
                
                const $button = $(this);
                const $modal = $('#dps-email-preview-modal');
                const clientId = $modal.data('client-id');
                const subject = $modal.find('#dps-email-subject').val();
                const body = $modal.find('#dps-email-body').val();

                if (!clientId || !subject || !body) {
                    alert('Preencha todos os campos do e-mail.');
                    return;
                }

                // Confirma envio
                if (!confirm('Confirma o envio do e-mail com essas informações?')) {
                    return;
                }

                // Desabilita botão
                $button.prop('disabled', true).text('Enviando...');

                // Envia e-mail
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dps_send_email_with_token',
                        nonce: dpsPortalAdmin.nonce,
                        client_id: clientId,
                        subject: subject,
                        body: body
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('E-mail enviado com sucesso!');
                            ModalManager.close('dps-email-preview-modal');
                            location.reload(); // Recarrega para limpar token temporário
                        } else {
                            alert('Erro ao enviar e-mail: ' + (response.data.message || 'Erro desconhecido'));
                            $button.prop('disabled', false).text('Confirmar Envio');
                        }
                    },
                    error: function() {
                        alert('Erro ao conectar com o servidor.');
                        $button.prop('disabled', false).text('Confirmar Envio');
                    }
                });
            });
        }
    };

    /**
     * Inicializa quando o DOM estiver pronto
     */
    $(document).ready(function() {
        // Verifica se estamos na página de logins
        if ($('.dps-portal-logins').length) {
            TokenAdmin.init();
        }
    });

})(jQuery);
