/**
 * JavaScript do Assistente de IA no Portal do Cliente.
 *
 * Design v2.0.0: Interface moderna e integrada
 * Gerencia interações do usuário com o assistente virtual,
 * incluindo envio de perguntas, exibição de respostas e
 * controle de estado do widget.
 */

(function($) {
    'use strict';

    // Aguarda o DOM estar pronto
    $(document).ready(function() {
        // Seletores para a nova estrutura
        const $widget = $('#dps-ai-assistant');
        const $header = $('#dps-ai-header');
        const $toggle = $('#dps-ai-toggle');
        const $content = $('#dps-ai-content');
        const $messages = $('#dps-ai-messages');
        const $question = $('#dps-ai-question');
        const $submit = $('#dps-ai-submit');
        const $loading = $('#dps-ai-loading');
        const $fab = $('#dps-ai-fab');

        // Compatibilidade com seletores antigos
        const $widgetLegacy = $('#dps-ai-widget');
        const widgetElement = $widget.length ? $widget : $widgetLegacy;

        // Verifica se o widget existe na página
        if (!widgetElement.length) {
            return;
        }

        // Configurações
        const clientId = widgetElement.data('client-id') || 0;
        const enableFeedback = dpsAI.enableFeedback || false;
        const isFloating = dpsAI.widgetMode === 'floating';
        var isSubmitting = false;
        var AJAX_TIMEOUT = 15000;

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
                widgetElement.toggleClass('is-open');
                if (widgetElement.hasClass('is-open')) {
                    $question.focus();
                }
            });
        }

        // Toggle do widget (modo inline)
        if (!isFloating) {
            $header.on('click', function(e) {
                if ($(e.target).closest('.dps-ai-assistant__toggle, #dps-ai-toggle').length) {
                    return;
                }
                toggleWidget();
            });

            $toggle.on('click', function(e) {
                e.stopPropagation();
                toggleWidget();
            });

            // Acessibilidade: Enter/Space para toggle via header ou botão
            $header.on('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toggleWidget();
                }
            });
        }

        // Escape key: collapse inline widget or close floating widget
        $(document).on('keydown', function(e) {
            if (e.key !== 'Escape') return;
            if (isFloating) {
                if (widgetElement.hasClass('is-open')) {
                    widgetElement.removeClass('is-open');
                    $fab.focus();
                }
            } else {
                if (!widgetElement.hasClass('is-collapsed')) {
                    toggleWidget();
                    $header.focus();
                }
            }
        });

        function toggleWidget() {
            const isCollapsed = widgetElement.hasClass('is-collapsed');
            
            if (isCollapsed) {
                // Expandir
                widgetElement.removeClass('is-collapsed');
                $content.slideDown(350, function() {
                    $question.focus();
                });
                $toggle.attr('aria-expanded', 'true');
            } else {
                // Colapsar
                $content.slideUp(350, function() {
                    widgetElement.addClass('is-collapsed');
                });
                $toggle.attr('aria-expanded', 'false');
            }
        }

        // Clique nos botões de sugestão/FAQ
        $(document).on('click', '.dps-ai-assistant__suggestion-btn, .dps-ai-faq-btn', function() {
            const faqQuestion = $(this).data('question');
            if (faqQuestion) {
                $question.val(faqQuestion);
                submitQuestion();
            }
        });

        // Auto-resize do textarea (expansível até 6 linhas)
        $question.on('input', function() {
            autoResizeTextarea(this);
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
            if (isSubmitting) return;

            const question = $question.val().trim();

            // Valida se há pergunta
            if (!question) {
                addMessage('error', dpsAI.i18n.pleaseEnterQuestion, 'Sistema');
                return;
            }

            isSubmitting = true;

            // Desabilita inputs durante o processamento
            $question.prop('disabled', true);
            $submit.prop('disabled', true);
            $loading.slideDown(200);

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
                timeout: AJAX_TIMEOUT,
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
                error: function(jqXHR, textStatus) {
                    var errorMsg = dpsAI.i18n.errorGeneric;
                    if (textStatus === 'timeout') {
                        errorMsg = dpsAI.i18n.errorTimeout || dpsAI.i18n.errorGeneric;
                    }
                    addMessage('error', errorMsg, 'Sistema');
                },
                complete: function() {
                    isSubmitting = false;
                    $question.prop('disabled', false);
                    $submit.prop('disabled', false);
                    $loading.slideUp(200);
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
            
            // Autoscroll inteligente: apenas se o usuário não estiver rolando manualmente
            smartScrollToBottom();
        }

        /**
         * Rola para o final do chat de forma inteligente.
         * Só faz scroll se o usuário já estava perto do final (não está lendo mensagens antigas).
         */
        function smartScrollToBottom() {
            const container = $messages[0];
            if (!container) return;
            
            const scrollTop = container.scrollTop;
            const scrollHeight = container.scrollHeight;
            const clientHeight = container.clientHeight;
            
            // Considera "perto do final" se estiver a menos de 100px do fim
            const isNearBottom = (scrollHeight - scrollTop - clientHeight) < 100;
            
            // Sempre rola se for a primeira mensagem OU se usuário está perto do final
            if (isNearBottom || scrollHeight <= clientHeight) {
                $messages.animate({
                    scrollTop: scrollHeight
                }, 300);
            }
        }

        /**
         * Auto-resize do textarea (expansível até 6 linhas ~120px).
         */
        function autoResizeTextarea(textarea) {
            // Reset para calcular altura real
            textarea.style.height = 'auto';
            
            // Define altura baseada no conteúdo, limitando a ~6 linhas (120px)
            const maxHeight = 120;
            const newHeight = Math.min(textarea.scrollHeight, maxHeight);
            textarea.style.height = newHeight + 'px';
            
            // Se passou do limite, habilita overflow interno
            if (textarea.scrollHeight > maxHeight) {
                textarea.style.overflowY = 'auto';
            } else {
                textarea.style.overflowY = 'hidden';
            }
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
