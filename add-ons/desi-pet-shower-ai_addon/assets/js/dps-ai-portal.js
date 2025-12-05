/**
 * JavaScript do widget de IA no Portal do Cliente.
 *
 * Gerencia interações do usuário com o assistente virtual,
 * incluindo envio de perguntas, exibição de respostas e
 * controle de estado do widget.
 *
 * v1.5.0 - FAQs sugeridas, feedback, widget flutuante, analytics
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
        const $fab = $('#dps-ai-fab');

        // Verifica se o widget existe na página
        if (!$widget.length) {
            return;
        }

        // Configurações
        const clientId = $widget.data('client-id') || 0;
        const enableFeedback = dpsAI.enableFeedback || false;
        const isFloating = dpsAI.widgetMode === 'floating';

        // Chave para armazenar mensagens no sessionStorage
        const STORAGE_KEY = 'dps_ai_messages';

        // Variável para rastrear última pergunta/resposta (para feedback)
        let lastQuestion = '';
        let lastAnswer = '';

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

        // Widget flutuante - FAB button
        if (isFloating && $fab.length) {
            $fab.on('click', function() {
                $widget.toggleClass('is-open');
                if ($widget.hasClass('is-open')) {
                    $question.focus();
                }
            });
        }

        // Toggle do widget (modo inline)
        if (!isFloating) {
            $header.on('click', function(e) {
                if ($(e.target).closest('.dps-ai-toggle').length) {
                    return;
                }
                toggleWidget();
            });

            $toggle.on('click', function(e) {
                e.stopPropagation();
                toggleWidget();
            });
        }

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

        // Clique nos botões de FAQ
        $(document).on('click', '.dps-ai-faq-btn', function() {
            const faqQuestion = $(this).data('question');
            if (faqQuestion) {
                $question.val(faqQuestion);
                submitQuestion();
            }
        });

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

            // Salva pergunta para feedback
            lastQuestion = question;

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
                        lastAnswer = response.data.answer;
                        addMessage('assistant', response.data.answer, dpsAI.i18n.assistant, true);
                    } else {
                        const errorMsg = response.data && response.data.message 
                            ? response.data.message 
                            : dpsAI.i18n.errorGeneric;
                        addMessage('error', errorMsg, 'Sistema');
                    }
                },
                error: function() {
                    addMessage('error', dpsAI.i18n.errorGeneric, 'Sistema');
                },
                complete: function() {
                    $question.prop('disabled', false);
                    $submit.prop('disabled', false);
                    $loading.slideUp(150);
                    $question.focus();
                }
            });
        }

        /**
         * Adiciona uma mensagem ao chat.
         */
        function addMessage(type, content, label, showFeedback) {
            addMessageToDOM(type, content, label, true, showFeedback && enableFeedback);
            saveMessages();
        }

        /**
         * Adiciona mensagem ao DOM.
         */
        function addMessageToDOM(type, content, label, animate, showFeedback) {
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

            const $contentDiv = $('<div>', {
                class: 'dps-ai-message-content',
                html: formatMessage(content)
            });

            $message.append($label).append($contentDiv);

            // Adiciona botões de feedback se habilitado
            if (showFeedback && type === 'assistant') {
                const $feedback = $('<div>', {
                    class: 'dps-ai-feedback'
                });
                
                $feedback.append('<span class="dps-ai-feedback-label">' + dpsAI.i18n.wasHelpful + '</span>');
                $feedback.append('<button type="button" class="dps-ai-feedback-btn dps-ai-feedback-positive" data-feedback="positive" title="Sim">👍</button>');
                $feedback.append('<button type="button" class="dps-ai-feedback-btn dps-ai-feedback-negative" data-feedback="negative" title="Não">👎</button>');
                
                $message.append($feedback);
            }

            $messages.append($message);
            $messages.scrollTop($messages[0].scrollHeight);
        }

        // Handler para botões de feedback
        $(document).on('click', '.dps-ai-feedback-btn', function() {
            const $btn = $(this);
            const $feedbackContainer = $btn.closest('.dps-ai-feedback');
            const feedback = $btn.data('feedback');

            // Desabilita botões após clique
            $feedbackContainer.find('.dps-ai-feedback-btn').prop('disabled', true);
            $btn.addClass('active');

            // Envia feedback via AJAX
            $.ajax({
                url: dpsAI.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'dps_ai_submit_feedback',
                    nonce: dpsAI.feedbackNonce,
                    feedback: feedback,
                    question: lastQuestion,
                    answer: lastAnswer,
                    client_id: clientId
                },
                success: function(response) {
                    $feedbackContainer.html('<span class="dps-ai-feedback-thanks">' + dpsAI.i18n.feedbackThanks + '</span>');
                },
                error: function() {
                    // Silently fail
                }
            });
        });

        /**
         * Formata o conteúdo da mensagem.
         */
        function formatMessage(text) {
            const escaped = $('<div>').text(text).html();
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
