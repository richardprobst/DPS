/**
 * JavaScript do Modo Especialista da IA.
 *
 * @package DPS_AI_Addon
 * @since 1.7.0
 */

(function($) {
    'use strict';

    const SpecialistMode = {
        /**
         * Inicializa o modo especialista.
         */
        init: function() {
            this.cacheDom();
            this.bindEvents();
        },

        /**
         * Cache de elementos DOM.
         */
        cacheDom: function() {
            this.$form = $('#dps-specialist-form');
            this.$query = $('#dps-specialist-query');
            this.$messages = $('#dps-specialist-messages');
            this.$submitBtn = this.$form.find('button[type="submit"]');
        },

        /**
         * Bind eventos.
         */
        bindEvents: function() {
            this.$form.on('submit', this.handleSubmit.bind(this));
        },

        /**
         * Handler do submit do formulário.
         *
         * @param {Event} e
         */
        handleSubmit: function(e) {
            e.preventDefault();

            const query = this.$query.val().trim();

            if (!query) {
                alert(dpsAiSpecialist.i18n.error_generic);
                return;
            }

            // Adicionar mensagem do usuário
            this.addMessage(query, 'user');

            // Limpar textarea
            this.$query.val('');

            // Exibir loading
            this.showLoading();

            // Desabilitar botão
            this.$submitBtn.prop('disabled', true);

            // Enviar requisição AJAX
            $.ajax({
                url: dpsAiSpecialist.ajax_url,
                type: 'POST',
                data: {
                    action: 'dps_ai_specialist_query',
                    nonce: dpsAiSpecialist.nonce,
                    query: query
                },
                success: this.handleSuccess.bind(this),
                error: this.handleError.bind(this),
                complete: function() {
                    this.$submitBtn.prop('disabled', false);
                    this.$query.focus();
                }.bind(this)
            });
        },

        /**
         * Handler de sucesso da requisição.
         *
         * @param {Object} response
         */
        handleSuccess: function(response) {
            this.hideLoading();

            if (response.success && response.data.response) {
                this.addMessage(response.data.response, 'assistant');
            } else {
                this.addMessage(
                    response.data.message || dpsAiSpecialist.i18n.error_generic,
                    'assistant'
                );
            }
        },

        /**
         * Handler de erro da requisição.
         *
         * @param {Object} xhr
         */
        handleError: function(xhr) {
            this.hideLoading();
            
            let errorMsg = dpsAiSpecialist.i18n.error_generic;
            
            if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                errorMsg = xhr.responseJSON.data.message;
            }

            this.addMessage(errorMsg, 'assistant');
        },

        /**
         * Adiciona uma mensagem ao chat.
         *
         * @param {string} text
         * @param {string} sender 'user' ou 'assistant'
         */
        addMessage: function(text, sender) {
            const now = new Date();
            const time = now.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });

            // Formatar texto (básico markdown)
            const formattedText = this.formatText(text);

            const $message = $('<div>')
                .addClass('dps-specialist-message')
                .addClass(sender);

            const $bubble = $('<div>')
                .addClass('dps-specialist-message-bubble')
                .html(formattedText);

            const $time = $('<span>')
                .addClass('dps-specialist-message-time')
                .text(time);

            $bubble.append($time);
            $message.append($bubble);

            this.$messages.append($message);

            // Scroll para o final
            this.scrollToBottom();
        },

        /**
         * Formata texto com markdown básico.
         *
         * @param {string} text
         * @return {string}
         */
        formatText: function(text) {
            // Escapar HTML primeiro
            text = $('<div>').text(text).html();

            // Negrito: **texto** ou __texto__
            text = text.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
            text = text.replace(/__(.+?)__/g, '<strong>$1</strong>');

            // Código inline: `texto`
            text = text.replace(/`(.+?)`/g, '<code>$1</code>');

            // Quebras de linha
            text = text.replace(/\n/g, '<br>');

            return text;
        },

        /**
         * Exibe loading.
         */
        showLoading: function() {
            const $loading = $('<div>')
                .addClass('dps-specialist-loading')
                .text(dpsAiSpecialist.i18n.processing);

            this.$messages.append($loading);
            this.scrollToBottom();
        },

        /**
         * Oculta loading.
         */
        hideLoading: function() {
            this.$messages.find('.dps-specialist-loading').remove();
        },

        /**
         * Scroll para o final do chat.
         */
        scrollToBottom: function() {
            this.$messages.animate({
                scrollTop: this.$messages[0].scrollHeight
            }, 300);
        }
    };

    // Inicializar quando o DOM estiver pronto
    $(document).ready(function() {
        if ($('#dps-specialist-form').length) {
            SpecialistMode.init();
        }
    });

})(jQuery);
