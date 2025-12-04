/**
 * JavaScript do widget de IA no Portal do Cliente.
 *
 * Gerencia interações do usuário com o assistente virtual,
 * incluindo envio de perguntas, exibição de respostas e
 * controle de estado do widget.
 *
 * v1.4.0 - Interface modernizada, histórico persistente, UX aprimorada
 */

(function($) {
    'use strict';

    // Aguarda o DOM estar pronto
    $(document).ready(function() {
        const $widget = $('#dps-ai-widget');
        const $header = $('#dps-ai-header');
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

        // Chave para armazenar mensagens no sessionStorage
        const STORAGE_KEY = 'dps_ai_messages';

        /**
         * Restaura mensagens do sessionStorage (se houver)
         */
        function restoreMessages() {
            try {
                const stored = sessionStorage.getItem(STORAGE_KEY);
                if (stored) {
                    const messages = JSON.parse(stored);
                    messages.forEach(function(msg) {
                        addMessageToDOM(msg.type, msg.content, msg.label, false);
                    });
                }
            } catch (e) {
                // Ignora erros de parsing
            }
        }

        /**
         * Salva mensagens no sessionStorage
         */
        function saveMessages() {
            try {
                const messages = [];
                $messages.find('.dps-ai-message').each(function() {
                    const $msg = $(this);
                    let type = 'assistant';
                    if ($msg.hasClass('dps-ai-message-user')) type = 'user';
                    if ($msg.hasClass('dps-ai-message-error')) type = 'error';
                    
                    messages.push({
                        type: type,
                        content: $msg.find('.dps-ai-message-content').text(),
                        label: $msg.find('.dps-ai-message-label').text()
                    });
                });
                sessionStorage.setItem(STORAGE_KEY, JSON.stringify(messages));
            } catch (e) {
                // Ignora erros de storage
            }
        }

        // Restaura mensagens ao carregar
        restoreMessages();

        // Toggle do widget - clique no header inteiro
        $header.on('click', function(e) {
            // Evita duplo clique no botão
            if ($(e.target).closest('.dps-ai-toggle').length) {
                return;
            }
            toggleWidget();
        });

        $toggle.on('click', function(e) {
            e.stopPropagation();
            toggleWidget();
        });

        function toggleWidget() {
            const isVisible = $content.is(':visible');
            
            if (isVisible) {
                $content.slideUp(250);
                $widget.removeClass('is-expanded');
                $toggle.attr('aria-expanded', 'false');
            } else {
                $content.slideDown(250, function() {
                    $question.focus();
                });
                $widget.addClass('is-expanded');
                $toggle.attr('aria-expanded', 'true');
            }
        }

        // Auto-resize do textarea
        $question.on('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });

        // Enviar pergunta ao pressionar Ctrl+Enter ou Enter (sem shift)
        $question.on('keydown', function(e) {
            if ((e.ctrlKey && e.key === 'Enter') || (e.key === 'Enter' && !e.shiftKey)) {
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
                addMessage('error', dpsAI.i18n.pleaseEnterQuestion, 'Sistema');
                return;
            }

            // Desabilita inputs durante o processamento
            $question.prop('disabled', true);
            $submit.prop('disabled', true);
            $loading.slideDown(150);

            // Adiciona mensagem do usuário ao chat
            addMessage('user', question, dpsAI.i18n.you);

            // Limpa o textarea e reseta altura
            $question.val('');
            $question[0].style.height = 'auto';

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
                        addMessage('error', errorMsg, 'Sistema');
                    }
                },
                error: function() {
                    // Erro de rede ou servidor
                    addMessage('error', dpsAI.i18n.errorGeneric, 'Sistema');
                },
                complete: function() {
                    // Reabilita inputs
                    $question.prop('disabled', false);
                    $submit.prop('disabled', false);
                    $loading.slideUp(150);
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
            addMessageToDOM(type, content, label, true);
            saveMessages();
        }

        /**
         * Adiciona mensagem ao DOM sem salvar.
         */
        function addMessageToDOM(type, content, label, animate) {
            const $message = $('<div>', {
                class: 'dps-ai-message dps-ai-message-' + type
            });

            if (!animate) {
                $message.css('animation', 'none');
            }

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

        /**
         * Limpa o histórico de mensagens
         */
        window.dpsAIClearHistory = function() {
            $messages.empty();
            sessionStorage.removeItem(STORAGE_KEY);
        };
    });

})(jQuery);
