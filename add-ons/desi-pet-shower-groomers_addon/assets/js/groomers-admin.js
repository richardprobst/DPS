/**
 * Groomers Add-on JavaScript
 * 
 * Scripts para interatividade na interface de gestão de groomers.
 * 
 * @package Desi_Pet_Shower_Groomers
 * @since 1.1.0
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
            var message = 'Tem certeza que deseja excluir ' + groomerName + '?\n\n';
            message += 'Esta ação não pode ser desfeita. Os atendimentos vinculados permanecerão sem groomer associado.';
            
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
            
            return true;
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
            var $form = $('.dps-groomers-form form');
            
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
