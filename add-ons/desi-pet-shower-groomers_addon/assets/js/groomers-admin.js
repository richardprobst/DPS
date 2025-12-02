/**
 * Groomers Add-on JavaScript
 * 
 * Scripts para interatividade na interface de gestão de groomers.
 * 
 * @package Desi_Pet_Shower_Groomers
 * @since 1.1.0
 * @updated 1.2.0 - Adicionado modal de edição e confirmação melhorada de exclusão
 */

(function($) {
    'use strict';

    /**
     * Módulo principal do Groomers Admin
     */
    var DPSGroomersAdmin = {
        
        /**
         * Inicializa o módulo
         */
        init: function() {
            this.bindEvents();
            this.initFormValidation();
        },

        /**
         * Vincula eventos aos elementos
         */
        bindEvents: function() {
            // Confirmação de exclusão de groomer
            $(document).on('click', '.dps-delete-groomer', this.confirmDelete);
            
            // Abrir modal de edição
            $(document).on('click', '.dps-edit-groomer', this.openEditModal);
            
            // Fechar modal
            $(document).on('click', '.dps-modal-close, .dps-modal-cancel', this.closeModal);
            $(document).on('click', '.dps-modal', this.closeModalOnOverlay);
            $(document).on('keydown', this.closeModalOnEsc);
            
            // Desabilita botão durante submit
            $(document).on('submit', '.dps-groomers-form', this.handleFormSubmit);
            
            // Validação em tempo real
            $(document).on('blur', '.dps-groomers-form input[required]', this.validateField);
        },

        /**
         * Confirma exclusão de groomer
         * @param {Event} e Evento de clique
         * @returns {boolean}
         */
        confirmDelete: function(e) {
            var groomerName = $(this).data('groomer-name') || 'este groomer';
            var appointmentsCount = $(this).data('appointments') || 0;
            
            var message = 'Tem certeza que deseja excluir "' + groomerName + '"?\n\n';
            
            if (appointmentsCount > 0) {
                message += '⚠️ Este groomer possui ' + appointmentsCount + ' agendamento(s) vinculado(s).\n';
                message += 'Os agendamentos serão mantidos, mas ficarão sem groomer associado.\n\n';
            }
            
            message += 'Esta ação não pode ser desfeita.';
            
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
            
            return true;
        },

        /**
         * Abre modal de edição de groomer
         * @param {Event} e Evento de clique
         */
        openEditModal: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var groomerId = $button.data('groomer-id');
            var groomerName = $button.data('groomer-name') || '';
            var groomerEmail = $button.data('groomer-email') || '';
            var groomerPhone = $button.data('groomer-phone') || '';
            var groomerCommission = $button.data('groomer-commission') || '';
            
            var $modal = $('#dps-edit-groomer-modal');
            
            // Preenche os campos do modal
            $modal.find('#edit_groomer_id').val(groomerId);
            $modal.find('#edit_groomer_name').val(groomerName);
            $modal.find('#edit_groomer_email').val(groomerEmail);
            $modal.find('#edit_groomer_phone').val(groomerPhone);
            $modal.find('#edit_groomer_commission').val(groomerCommission);
            
            // Exibe o modal
            $modal.fadeIn(200);
            
            // Foca no primeiro campo
            setTimeout(function() {
                $modal.find('#edit_groomer_name').focus();
            }, 250);
        },

        /**
         * Fecha o modal
         * @param {Event} e Evento de clique
         */
        closeModal: function(e) {
            e.preventDefault();
            $('#dps-edit-groomer-modal').fadeOut(200);
        },

        /**
         * Fecha o modal ao clicar no overlay
         * @param {Event} e Evento de clique
         */
        closeModalOnOverlay: function(e) {
            if ($(e.target).hasClass('dps-modal')) {
                $(this).fadeOut(200);
            }
        },

        /**
         * Fecha o modal ao pressionar ESC
         * @param {Event} e Evento de teclado
         */
        closeModalOnEsc: function(e) {
            if (e.keyCode === 27) { // ESC
                var $modal = $('#dps-edit-groomer-modal');
                if ($modal.is(':visible')) {
                    $modal.fadeOut(200);
                }
            }
        },

        /**
         * Manipula submissão do formulário
         * @param {Event} e Evento de submit
         */
        handleFormSubmit: function(e) {
            var $form = $(this);
            var $submitBtn = $form.find('button[type="submit"], input[type="submit"]');
            
            // Desabilita botão e mostra feedback
            $submitBtn.prop('disabled', true);
            
            if ($submitBtn.is('button')) {
                $submitBtn.data('original-text', $submitBtn.text());
                $submitBtn.html('<span class="spinner is-active" style="margin: 0 8px 0 0;"></span> Salvando...');
            }
        },

        /**
         * Inicializa validação do formulário
         */
        initFormValidation: function() {
            var $form = $('.dps-groomers-form');
            
            if ($form.length === 0) {
                return;
            }

            // Adiciona indicadores visuais aos campos obrigatórios
            $form.find('input[required], select[required]').each(function() {
                var $input = $(this);
                var $label = $input.closest('.dps-form-field').find('label');
                
                if ($label.length && !$label.find('.dps-required').length) {
                    $label.append(' <span class="dps-required">*</span>');
                }
            });
        },

        /**
         * Valida campo individual
         * @param {Event} e Evento de blur
         */
        validateField: function(e) {
            var $input = $(this);
            var $field = $input.closest('.dps-form-field');
            
            // Remove estados anteriores
            $field.removeClass('dps-field-error dps-field-success');
            $field.find('.dps-field-message').remove();
            
            // Valida
            if (!$input.val().trim()) {
                $field.addClass('dps-field-error');
                $field.append('<span class="dps-field-message dps-field-message--error">Este campo é obrigatório</span>');
            } else if ($input.attr('type') === 'email' && !DPSGroomersAdmin.isValidEmail($input.val())) {
                $field.addClass('dps-field-error');
                $field.append('<span class="dps-field-message dps-field-message--error">Email inválido</span>');
            }
        },

        /**
         * Valida formato de email.
         * 
         * Usa validação HTML5 nativa quando disponível, com fallback para regex básico.
         * O input já é do tipo email, então o navegador faz validação principal.
         * Este método é uma validação adicional de UX.
         *
         * @param {string} email Email a validar
         * @returns {boolean}
         */
        isValidEmail: function(email) {
            // Tenta usar a validação nativa do HTML5 se disponível
            var emailInput = document.createElement('input');
            emailInput.type = 'email';
            emailInput.value = email;
            
            if (typeof emailInput.checkValidity === 'function') {
                return emailInput.checkValidity();
            }
            
            // Fallback: regex básico para navegadores antigos
            var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        },

        /**
         * Exibe notificação temporária
         * @param {string} message Mensagem a exibir
         * @param {string} type Tipo: success, error, warning
         */
        showNotice: function(message, type) {
            type = type || 'info';
            
            var $notice = $('<div class="dps-groomers-notice dps-groomers-notice--' + type + '">' + message + '</div>');
            
            $('.dps-section#dps-section-groomers').prepend($notice);
            
            // Remove após 5 segundos
            setTimeout(function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    /**
     * Inicializa quando DOM estiver pronto
     */
    $(document).ready(function() {
        DPSGroomersAdmin.init();
    });

    // Expõe globalmente para uso externo
    window.DPSGroomersAdmin = DPSGroomersAdmin;

})(jQuery);
