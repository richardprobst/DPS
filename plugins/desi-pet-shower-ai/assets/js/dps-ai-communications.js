/**
 * DPS AI Communications - Interface JavaScript
 *
 * Gerencia botões de sugestão de IA para mensagens de WhatsApp e e-mail.
 * NUNCA envia mensagens automaticamente - apenas preenche campos.
 *
 * @package DPS_AI_Addon
 * @since 1.2.0
 */

(function($) {
    'use strict';

    /**
     * Objeto principal do módulo
     */
    var DPSAICommunications = {
        
        /**
         * Inicializa o módulo
         */
        init: function() {
            this.bindEvents();
            this.setupEmailPreviewModal();
        },

        /**
         * Registra event listeners
         */
        bindEvents: function() {
            // Botões de sugestão para WhatsApp
            $(document).on('click', '.dps-ai-suggest-whatsapp', this.handleWhatsAppSuggestion.bind(this));
            
            // Botões de sugestão para e-mail
            $(document).on('click', '.dps-ai-suggest-email', this.handleEmailSuggestion.bind(this));
            
            // Botão de inserir no modal de e-mail
            $(document).on('click', '#dps-ai-email-insert', this.handleEmailInsert.bind(this));
            
            // Botão de cancelar no modal de e-mail
            $(document).on('click', '#dps-ai-email-cancel', this.closeEmailPreviewModal.bind(this));
        },

        /**
         * Cria modal de pré-visualização de e-mail
         */
        setupEmailPreviewModal: function() {
            if ($('#dps-ai-email-preview-modal').length > 0) {
                return; // Já existe
            }

            var modalHtml = '<div id="dps-ai-email-preview-modal" class="dps-ai-modal" style="display:none;">' +
                '<div class="dps-ai-modal-overlay"></div>' +
                '<div class="dps-ai-modal-content">' +
                    '<div class="dps-ai-modal-header">' +
                        '<h2>' + dpsAiComm.i18n.emailPreviewTitle + '</h2>' +
                        '<button type="button" class="dps-ai-modal-close" id="dps-ai-email-cancel">&times;</button>' +
                    '</div>' +
                    '<div class="dps-ai-modal-body">' +
                        '<div class="dps-ai-form-field">' +
                            '<label for="dps-ai-email-subject-preview">' + dpsAiComm.i18n.subject + '</label>' +
                            '<input type="text" id="dps-ai-email-subject-preview" class="widefat" />' +
                        '</div>' +
                        '<div class="dps-ai-form-field">' +
                            '<label for="dps-ai-email-body-preview">' + dpsAiComm.i18n.body + '</label>' +
                            '<textarea id="dps-ai-email-body-preview" rows="10" class="widefat"></textarea>' +
                        '</div>' +
                    '</div>' +
                    '<div class="dps-ai-modal-footer">' +
                        '<button type="button" class="button button-primary" id="dps-ai-email-insert">' + dpsAiComm.i18n.insert + '</button>' +
                        '<button type="button" class="button" id="dps-ai-email-cancel-btn">' + dpsAiComm.i18n.cancel + '</button>' +
                    '</div>' +
                '</div>' +
            '</div>';

            $('body').append(modalHtml);

            // Fechar ao clicar no overlay
            $(document).on('click', '.dps-ai-modal-overlay', this.closeEmailPreviewModal.bind(this));
            $(document).on('click', '#dps-ai-email-cancel-btn', this.closeEmailPreviewModal.bind(this));
        },

        /**
         * Abre modal de pré-visualização de e-mail
         */
        openEmailPreviewModal: function(subject, body) {
            $('#dps-ai-email-subject-preview').val(subject);
            $('#dps-ai-email-body-preview').val(body);
            $('#dps-ai-email-preview-modal').fadeIn(200);
        },

        /**
         * Fecha modal de pré-visualização de e-mail
         */
        closeEmailPreviewModal: function() {
            $('#dps-ai-email-preview-modal').fadeOut(200);
        },

        /**
         * Handler para botão de sugestão de WhatsApp
         */
        handleWhatsAppSuggestion: function(e) {
            e.preventDefault();
            
            var $button = $(e.currentTarget);
            var $targetField = $($button.data('target'));
            
            if ($targetField.length === 0) {
                alert('Campo de mensagem não encontrado');
                return;
            }

            // Coleta contexto do DOM
            var context = this.collectContextFromButton($button);
            
            if (!context || !context.type) {
                alert('Tipo de mensagem não especificado');
                return;
            }

            // Desabilita botão durante processamento
            var originalText = $button.text();
            $button.prop('disabled', true).text(dpsAiComm.i18n.generating);

            // Faz chamada AJAX
            $.ajax({
                url: dpsAiComm.ajaxurl,
                type: 'POST',
                data: {
                    action: 'dps_ai_suggest_whatsapp_message',
                    nonce: dpsAiComm.nonce,
                    context: context
                },
                success: function(response) {
                    if (response.success && response.data.text) {
                        // Preenche o campo com a sugestão
                        $targetField.val(response.data.text);
                        // Feedback visual (opcional)
                        $targetField.focus();
                    } else {
                        alert(response.data.message || dpsAiComm.i18n.error);
                    }
                },
                error: function() {
                    alert(dpsAiComm.i18n.error);
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Handler para botão de sugestão de e-mail
         */
        handleEmailSuggestion: function(e) {
            e.preventDefault();
            
            var $button = $(e.currentTarget);
            
            // Salva referência aos campos de destino
            this.emailTargetSubject = $($button.data('target-subject'));
            this.emailTargetBody = $($button.data('target-body'));
            
            if (this.emailTargetSubject.length === 0 || this.emailTargetBody.length === 0) {
                alert('Campos de e-mail não encontrados');
                return;
            }

            // Coleta contexto
            var context = this.collectContextFromButton($button);
            
            if (!context || !context.type) {
                alert('Tipo de mensagem não especificado');
                return;
            }

            // Desabilita botão
            var originalText = $button.text();
            $button.prop('disabled', true).text(dpsAiComm.i18n.generating);

            // Faz chamada AJAX
            var self = this;
            $.ajax({
                url: dpsAiComm.ajaxurl,
                type: 'POST',
                data: {
                    action: 'dps_ai_suggest_email_message',
                    nonce: dpsAiComm.nonce,
                    context: context
                },
                success: function(response) {
                    if (response.success && response.data.subject && response.data.body) {
                        // Abre modal com pré-visualização
                        self.openEmailPreviewModal(response.data.subject, response.data.body);
                    } else {
                        alert(response.data.message || dpsAiComm.i18n.error);
                    }
                },
                error: function() {
                    alert(dpsAiComm.i18n.error);
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Handler para botão de inserir e-mail do modal
         */
        handleEmailInsert: function(e) {
            e.preventDefault();
            
            var subject = $('#dps-ai-email-subject-preview').val();
            var body = $('#dps-ai-email-body-preview').val();
            
            // Preenche os campos de destino
            if (this.emailTargetSubject && this.emailTargetSubject.length > 0) {
                this.emailTargetSubject.val(subject);
            }
            
            if (this.emailTargetBody && this.emailTargetBody.length > 0) {
                this.emailTargetBody.val(body);
            }
            
            // Fecha modal
            this.closeEmailPreviewModal();
        },

        /**
         * Coleta contexto de dados do botão
         * 
         * Espera que o botão tenha atributos data-* com as informações:
         * - data-type: tipo de mensagem
         * - data-client-name: nome do cliente
         * - data-pet-name: nome do pet
         * - data-appointment-date: data do agendamento
         * - data-appointment-time: hora do agendamento
         * - data-services: serviços (JSON array ou string separada por vírgula)
         * - data-groomer-name: nome do groomer (opcional)
         * - data-amount: valor (opcional)
         * - data-additional-info: informações adicionais (opcional)
         */
        collectContextFromButton: function($button) {
            var context = {
                type: $button.data('type') || '',
                client_name: $button.data('client-name') || '',
                pet_name: $button.data('pet-name') || '',
                appointment_date: $button.data('appointment-date') || '',
                appointment_time: $button.data('appointment-time') || '',
                groomer_name: $button.data('groomer-name') || '',
                amount: $button.data('amount') || '',
                additional_info: $button.data('additional-info') || ''
            };

            // Trata serviços (pode ser array JSON ou string separada por vírgula)
            var services = $button.data('services');
            if (services) {
                if (typeof services === 'string') {
                    // Tenta fazer parse como JSON
                    try {
                        context.services = JSON.parse(services);
                    } catch(e) {
                        // Se falhar, divide por vírgula
                        context.services = services.split(',').map(function(s) {
                            return s.trim();
                        });
                    }
                } else if (Array.isArray(services)) {
                    context.services = services;
                }
            }

            return context;
        }
    };

    // Inicializa quando o DOM estiver pronto
    $(document).ready(function() {
        DPSAICommunications.init();
        
        // Handler genérico para formulários de exportação CSV
        // Previne duplo clique e mostra spinner durante processamento
        $(document).on('submit', '.dps-ai-export-form', function() {
            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            
            // Previne duplo submit
            if ($button.prop('disabled')) {
                return false;
            }
            
            // Desabilita botão e mostra spinner
            $button.prop('disabled', true);
            $button.html('<span class="dashicons dashicons-update-alt spinning" style="vertical-align: middle; margin-right: 5px;"></span> ' + (dpsAiComm.i18n.exporting || 'Exportando...'));
            
            // Permite o submit continuar
            return true;
        });
    });

})(jQuery);
