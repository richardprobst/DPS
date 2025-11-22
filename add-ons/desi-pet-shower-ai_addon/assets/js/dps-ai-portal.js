/**
 * JavaScript do widget de IA no Portal do Cliente.
 *
 * Gerencia interações do usuário com o assistente virtual,
 * incluindo envio de perguntas, exibição de respostas e
 * controle de estado do widget.
 */

(function($) {
    'use strict';

    // Aguarda o DOM estar pronto
    $(document).ready(function() {
        const $widget = $('#dps-ai-widget');
        const $toggle = $('#dps-ai-toggle');
        const $content = $('#dps-ai-content');
        const $messages = $('#dps-ai-messages');
        const $question = $('#dps-ai-question');
        const $submit = $('#dps-ai-submit');
        const $loading = $('#dps-ai-loading');

        // Verifica se o widget existe na página
        if (!$widget.length) {
            return;
        }

        // Toggle do widget (expandir/recolher)
        $toggle.on('click', function() {
            const isVisible = $content.is(':visible');
            
            if (isVisible) {
                $content.slideUp(300);
                $toggle.find('.dashicons').removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
            } else {
                $content.slideDown(300);
                $toggle.find('.dashicons').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
            }
        });

        // Enviar pergunta ao pressionar Ctrl+Enter no textarea
        $question.on('keydown', function(e) {
            if (e.ctrlKey && e.key === 'Enter') {
                e.preventDefault();
                submitQuestion();
            }
        });

        // Enviar pergunta ao clicar no botão
        $submit.on('click', function(e) {
            e.preventDefault();
            submitQuestion();
        });

        /**
         * Envia a pergunta para o servidor via AJAX.
         */
        function submitQuestion() {
            const question = $question.val().trim();

            // Valida se há pergunta
            if (!question) {
                addMessage('error', 'Por favor, digite uma pergunta.', 'system');
                return;
            }

            // Desabilita inputs durante o processamento
            $question.prop('disabled', true);
            $submit.prop('disabled', true);
            $loading.show();

            // Adiciona mensagem do usuário ao chat
            addMessage('user', question, dpsAI.i18n.you);

            // Limpa o textarea
            $question.val('');

            // Envia requisição AJAX
            $.ajax({
                url: dpsAI.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'dps_ai_portal_ask',
                    nonce: dpsAI.nonce,
                    question: question
                },
                success: function(response) {
                    if (response.success && response.data.answer) {
                        // Adiciona resposta do assistente
                        addMessage('assistant', response.data.answer, dpsAI.i18n.assistant);
                    } else {
                        // Exibe mensagem de erro
                        const errorMsg = response.data && response.data.message 
                            ? response.data.message 
                            : dpsAI.i18n.errorGeneric;
                        addMessage('error', errorMsg, 'system');
                    }
                },
                error: function() {
                    // Erro de rede ou servidor
                    addMessage('error', dpsAI.i18n.errorGeneric, 'system');
                },
                complete: function() {
                    // Reabilita inputs
                    $question.prop('disabled', false);
                    $submit.prop('disabled', false);
                    $loading.hide();
                    $question.focus();
                }
            });
        }

        /**
         * Adiciona uma mensagem ao chat.
         *
         * @param {string} type    Tipo da mensagem: 'user', 'assistant' ou 'error'
         * @param {string} content Conteúdo da mensagem
         * @param {string} label   Rótulo do remetente
         */
        function addMessage(type, content, label) {
            const $message = $('<div>', {
                class: 'dps-ai-message dps-ai-message-' + type
            });

            const $label = $('<div>', {
                class: 'dps-ai-message-label',
                text: label
            });

            const $content = $('<div>', {
                class: 'dps-ai-message-content',
                html: formatMessage(content)
            });

            $message.append($label).append($content);
            $messages.append($message);

            // Scroll automático para a última mensagem
            $messages.scrollTop($messages[0].scrollHeight);
        }

        /**
         * Formata o conteúdo da mensagem.
         * Converte quebras de linha em <br> e preserva espaçamento.
         *
         * @param {string} text Texto a ser formatado
         * @return {string} Texto formatado em HTML
         */
        function formatMessage(text) {
            // Escapa HTML básico mas preserva quebras de linha
            const escaped = $('<div>').text(text).html();
            // Converte quebras de linha em <br>
            return escaped.replace(/\n/g, '<br>');
        }
    });

})(jQuery);
