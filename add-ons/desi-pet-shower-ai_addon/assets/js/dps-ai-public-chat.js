/**
 * JavaScript do Chat Público de IA.
 *
 * Gerencia interações do chat público para visitantes do site.
 * Inclui: envio de perguntas, exibição de respostas, FAQs, feedback, modo flutuante.
 *
 * @since 1.6.0
 */

(function($) {
    'use strict';

    // Constantes
    var HISTORY_EXPIRATION_MINUTES = 30;

    // Variáveis globais
    var $widget = null;
    var $messages = null;
    var $input = null;
    var $submit = null;
    var $typing = null;
    var nonce = '';
    var isFloating = false;
    var conversationHistory = [];

    /**
     * Inicializa o chat público.
     */
    function init() {
        $widget = $('#dps-ai-public-chat');
        
        if (!$widget.length) {
            return;
        }

        // Elementos
        $messages = $('#dps-ai-public-messages');
        $input = $('#dps-ai-public-input');
        $submit = $('#dps-ai-public-submit');
        $typing = $('#dps-ai-public-typing');
        nonce = $widget.data('nonce');
        isFloating = $widget.hasClass('dps-ai-public-chat--floating');

        // Restaura histórico da sessão
        restoreHistory();

        // Event listeners
        bindEvents();
    }

    /**
     * Vincula eventos.
     */
    function bindEvents() {
        // Envio de mensagem
        $submit.on('click', handleSubmit);
        
        // Enter para enviar (Shift+Enter para nova linha)
        $input.on('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                handleSubmit();
            }
        });

        // Auto-resize do textarea
        $input.on('input', function() {
            autoResizeTextarea(this);
        });

        // FAQs
        $widget.on('click', '.dps-ai-public-faq-btn', function() {
            var question = $(this).data('question');
            if (question) {
                $input.val(question);
                handleSubmit();
            }
        });

        // Toggle do widget flutuante
        if (isFloating) {
            $('#dps-ai-public-fab').on('click', function() {
                $widget.toggleClass('is-open');
                
                if ($widget.hasClass('is-open')) {
                    setTimeout(function() {
                        $input.focus();
                        scrollToBottom();
                    }, 300);
                }
            });

            // Fecha ao clicar fora (opcional)
            $(document).on('click', function(e) {
                if ($widget.hasClass('is-open') && 
                    !$(e.target).closest('.dps-ai-public-panel, .dps-ai-public-fab').length) {
                    // Comentado para não fechar automaticamente
                    // $widget.removeClass('is-open');
                }
            });
        }

        // Feedback
        $widget.on('click', '.dps-ai-public-feedback-btn', handleFeedback);
    }

    /**
     * Processa envio de mensagem.
     */
    function handleSubmit() {
        var question = $input.val().trim();
        
        if (!question) {
            showError(dpsAIPublicChat.i18n.pleaseEnterQuestion);
            return;
        }

        // Limpa input
        $input.val('').focus();
        autoResizeTextarea($input[0]);

        // Adiciona mensagem do usuário
        addMessage(question, 'user');

        // Mostra indicador de digitação
        showTyping();

        // Desabilita input durante requisição
        setLoading(true);

        // Envia requisição AJAX
        $.ajax({
            url: dpsAIPublicChat.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dps_ai_public_ask',
                nonce: nonce,
                question: question
            },
            success: function(response) {
                hideTyping();
                setLoading(false);

                if (response.success) {
                    addMessage(response.data.answer, 'assistant', response.data.message_id, question);
                } else {
                    addMessage(response.data.message || dpsAIPublicChat.i18n.errorGeneric, 'error');
                }
            },
            error: function() {
                hideTyping();
                setLoading(false);
                addMessage(dpsAIPublicChat.i18n.errorGeneric, 'error');
            }
        });
    }

    /**
     * Adiciona uma mensagem ao chat.
     *
     * @param {string} content Conteúdo da mensagem.
     * @param {string} type Tipo: 'user', 'assistant' ou 'error'.
     * @param {string} messageId ID único da mensagem (para feedback).
     * @param {string} question Pergunta original (para feedback).
     */
    function addMessage(content, type, messageId, question) {
        var messageClass = 'dps-ai-public-message dps-ai-public-message--' + type;
        var avatar = type === 'user' ? '👤' : '🐾';
        
        if (type === 'error') {
            avatar = '⚠️';
        }

        var feedbackHtml = '';
        if (type === 'assistant' && messageId) {
            feedbackHtml = '<div class="dps-ai-public-feedback" data-message-id="' + messageId + '" data-question="' + escapeHtml(question) + '">' +
                '<span class="dps-ai-public-feedback-label">' + dpsAIPublicChat.i18n.wasHelpful + '</span>' +
                '<button type="button" class="dps-ai-public-feedback-btn" data-feedback="positive" aria-label="Útil">👍</button>' +
                '<button type="button" class="dps-ai-public-feedback-btn" data-feedback="negative" aria-label="Não útil">👎</button>' +
            '</div>';
        }

        var html = '<div class="' + messageClass + '">' +
            '<div class="dps-ai-public-message-avatar">' + avatar + '</div>' +
            '<div class="dps-ai-public-message-content">' +
                '<p>' + formatMessage(content) + '</p>' +
                feedbackHtml +
            '</div>' +
        '</div>';

        $messages.append(html);
        scrollToBottom();

        // Salva no histórico
        if (type !== 'error') {
            conversationHistory.push({
                type: type,
                content: content,
                messageId: messageId || null,
                question: question || null,
                timestamp: Date.now()
            });
            saveHistory();
        }
    }

    /**
     * Processa feedback.
     */
    function handleFeedback() {
        var $btn = $(this);
        var $container = $btn.closest('.dps-ai-public-feedback');
        var messageId = $container.data('message-id');
        var question = $container.data('question');
        var feedback = $btn.data('feedback');

        // Desabilita botões
        $container.find('.dps-ai-public-feedback-btn').prop('disabled', true);
        $btn.addClass('is-selected');

        // Envia feedback
        $.ajax({
            url: dpsAIPublicChat.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dps_ai_public_feedback',
                nonce: nonce,
                message_id: messageId,
                feedback: feedback,
                question: question
            },
            success: function(response) {
                if (response.success) {
                    $container.html('<span class="dps-ai-public-feedback-thanks">' + dpsAIPublicChat.i18n.feedbackThanks + '</span>');
                }
            }
        });
    }

    /**
     * Mostra indicador de digitação.
     */
    function showTyping() {
        $typing.show();
        scrollToBottom();
    }

    /**
     * Esconde indicador de digitação.
     */
    function hideTyping() {
        $typing.hide();
    }

    /**
     * Define estado de loading.
     *
     * @param {boolean} loading Se está carregando.
     */
    function setLoading(loading) {
        $input.prop('disabled', loading);
        $submit.prop('disabled', loading);
    }

    /**
     * Mostra erro temporário.
     *
     * @param {string} message Mensagem de erro.
     */
    function showError(message) {
        // Adiciona shake no input
        $input.addClass('shake');
        setTimeout(function() {
            $input.removeClass('shake');
        }, 500);
    }

    /**
     * Rola para o final das mensagens.
     */
    function scrollToBottom() {
        var $body = $('.dps-ai-public-body');
        $body.animate({
            scrollTop: $body[0].scrollHeight
        }, 300);
    }

    /**
     * Auto-resize do textarea.
     *
     * @param {HTMLElement} textarea Elemento textarea.
     */
    function autoResizeTextarea(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
    }

    /**
     * Formata mensagem para exibição.
     *
     * @param {string} text Texto da mensagem.
     * @return {string} Texto formatado.
     */
    function formatMessage(text) {
        // Escapa HTML
        text = escapeHtml(text);
        
        // Converte quebras de linha
        text = text.replace(/\n/g, '<br>');
        
        // Converte URLs em links (básico)
        text = text.replace(
            /(https?:\/\/[^\s<]+)/gi,
            '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>'
        );
        
        return text;
    }

    /**
     * Escapa HTML.
     *
     * @param {string} text Texto para escapar.
     * @return {string} Texto escapado.
     */
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Salva histórico da conversa na sessão.
     */
    function saveHistory() {
        try {
            // Limita a últimas 20 mensagens
            var history = conversationHistory.slice(-20);
            sessionStorage.setItem('dps_ai_public_history', JSON.stringify(history));
        } catch (e) {
            // SessionStorage pode estar desabilitado
        }
    }

    /**
     * Restaura histórico da sessão.
     */
    function restoreHistory() {
        try {
            var stored = sessionStorage.getItem('dps_ai_public_history');
            if (stored) {
                conversationHistory = JSON.parse(stored);
                
                // Restaura mensagens (exceto mensagens antigas demais)
                var expirationTime = Date.now() - (HISTORY_EXPIRATION_MINUTES * 60 * 1000);
                conversationHistory = conversationHistory.filter(function(msg) {
                    return msg.timestamp > expirationTime;
                });

                conversationHistory.forEach(function(msg) {
                    addMessageWithoutSave(msg.content, msg.type, msg.messageId, msg.question);
                });
            }
        } catch (e) {
            conversationHistory = [];
        }
    }

    /**
     * Adiciona mensagem sem salvar no histórico (para restauração).
     */
    function addMessageWithoutSave(content, type, messageId, question) {
        var messageClass = 'dps-ai-public-message dps-ai-public-message--' + type;
        var avatar = type === 'user' ? '👤' : '🐾';
        
        // Não mostra feedback em mensagens restauradas
        var html = '<div class="' + messageClass + '">' +
            '<div class="dps-ai-public-message-avatar">' + avatar + '</div>' +
            '<div class="dps-ai-public-message-content">' +
                '<p>' + formatMessage(content) + '</p>' +
            '</div>' +
        '</div>';

        $messages.append(html);
    }

    /**
     * Limpa histórico (pode ser chamado externamente).
     */
    window.dpsAIPublicClearHistory = function() {
        try {
            sessionStorage.removeItem('dps_ai_public_history');
            conversationHistory = [];
            $messages.find('.dps-ai-public-message:not(:first)').remove();
        } catch (e) {
            // Ignore
        }
    };

    // Inicializa quando DOM estiver pronto
    $(document).ready(init);

})(jQuery);
