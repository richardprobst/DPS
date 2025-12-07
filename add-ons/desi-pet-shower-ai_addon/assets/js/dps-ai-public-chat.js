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
    var $voiceBtn = null;
    var $typing = null;
    var nonce = '';
    var isFloating = false;
    var conversationHistory = [];
    var recognition = null;
    var isListening = false;

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
        $voiceBtn = $('#dps-ai-public-voice');
        $typing = $('#dps-ai-public-typing');
        nonce = $widget.data('nonce');
        isFloating = $widget.hasClass('dps-ai-public-chat--floating');

        // Inicializa reconhecimento de voz
        initVoiceRecognition();

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

        // Botão de voz
        if ($voiceBtn.length) {
            $voiceBtn.on('click', handleVoiceClick);
        }

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
                    // Verifica se é erro de rate limit
                    var errorType = response.data.error_type || 'generic';
                    var errorMessage = response.data.message || dpsAIPublicChat.i18n.errorGeneric;
                    
                    if (errorType === 'rate_limit') {
                        // Exibe mensagem específica de rate limit
                        addMessage(errorMessage, 'rate-limit');
                        // Desabilita botão temporariamente (5 segundos)
                        disableSubmitTemporarily(5);
                    } else {
                        // Erro genérico
                        addMessage(errorMessage, 'error');
                    }
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
     * @param {string} type Tipo: 'user', 'assistant', 'error', ou 'rate-limit'.
     * @param {string} messageId ID único da mensagem (para feedback).
     * @param {string} question Pergunta original (para feedback).
     */
    function addMessage(content, type, messageId, question) {
        var messageClass = 'dps-ai-public-message dps-ai-public-message--' + type;
        var avatar = '🐾';
        
        // Define avatar baseado no tipo
        if (type === 'user') {
            avatar = '👤';
        } else if (type === 'error') {
            avatar = '⚠️';
        } else if (type === 'rate-limit') {
            avatar = '⏱️';
            // Trata como erro mas com ícone diferente
            messageClass = 'dps-ai-public-message dps-ai-public-message--error dps-ai-public-message--rate-limit';
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
        
        // Autoscroll inteligente
        smartScrollToBottom();

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
        smartScrollToBottom();
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
     * Desabilita botão de enviar temporariamente com contagem regressiva.
     *
     * @param {number} seconds Número de segundos para desabilitar.
     */
    function disableSubmitTemporarily(seconds) {
        var originalText = $submit.find('.dps-ai-public-submit-icon').text();
        var countdown = seconds;
        
        $submit.prop('disabled', true);
        $submit.find('.dps-ai-public-submit-icon').text(countdown);
        
        var interval = setInterval(function() {
            countdown--;
            if (countdown > 0) {
                $submit.find('.dps-ai-public-submit-icon').text(countdown);
            } else {
                clearInterval(interval);
                $submit.prop('disabled', false);
                $submit.find('.dps-ai-public-submit-icon').text(originalText);
            }
        }, 1000);
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
     * Rola para o final das mensagens (versão básica - compatibilidade).
     */
    function scrollToBottom() {
        var $body = $('.dps-ai-public-body');
        $body.animate({
            scrollTop: $body[0].scrollHeight
        }, 300);
    }

    /**
     * Rola para o final de forma inteligente.
     * Só faz scroll se o usuário já estava perto do final (não está lendo mensagens antigas).
     */
    function smartScrollToBottom() {
        var $body = $('.dps-ai-public-body');
        if (!$body.length) return;
        
        var container = $body[0];
        var scrollTop = container.scrollTop;
        var scrollHeight = container.scrollHeight;
        var clientHeight = container.clientHeight;
        
        // Considera "perto do final" se estiver a menos de 100px do fim
        var isNearBottom = (scrollHeight - scrollTop - clientHeight) < 100;
        
        // Sempre rola se for a primeira mensagem OU se usuário está perto do final
        if (isNearBottom || scrollHeight <= clientHeight) {
            $body.animate({
                scrollTop: scrollHeight
            }, 300);
        }
    }

    /**
     * Auto-resize do textarea (expansível até 6 linhas ~120px).
     *
     * @param {HTMLElement} textarea Elemento textarea.
     */
    function autoResizeTextarea(textarea) {
        // Reset para calcular altura real
        textarea.style.height = 'auto';
        
        // Define altura baseada no conteúdo, limitando a ~6 linhas (120px)
        var maxHeight = 120;
        var newHeight = Math.min(textarea.scrollHeight, maxHeight);
        textarea.style.height = newHeight + 'px';
        
        // Se passou do limite, habilita overflow interno
        if (textarea.scrollHeight > maxHeight) {
            textarea.style.overflowY = 'auto';
        } else {
            textarea.style.overflowY = 'hidden';
        }
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

    /**
     * Inicializa reconhecimento de voz (Web Speech API).
     */
    function initVoiceRecognition() {
        // Verifica suporte do navegador
        var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        
        if (!SpeechRecognition) {
            // API não suportada - deixa botão oculto
            console.info('Web Speech API não suportada neste navegador');
            return;
        }

        // Exibe botão de voz
        $voiceBtn.show();

        // Cria instância de reconhecimento
        recognition = new SpeechRecognition();
        recognition.lang = 'pt-BR'; // Português do Brasil
        recognition.continuous = false; // Para após uma frase
        recognition.interimResults = false; // Apenas resultados finais

        // Evento: resultado do reconhecimento
        recognition.onresult = function(event) {
            var transcript = event.results[0][0].transcript;
            
            // Preenche textarea com o texto reconhecido
            var currentText = $input.val().trim();
            if (currentText) {
                // Adiciona ao texto existente
                $input.val(currentText + ' ' + transcript);
            } else {
                // Substitui texto vazio
                $input.val(transcript);
            }
            
            // Auto-resize
            autoResizeTextarea($input[0]);
            
            // Foca no input para permitir edição
            $input.focus();
        };

        // Evento: fim do reconhecimento
        recognition.onend = function() {
            stopListening();
        };

        // Evento: erro no reconhecimento
        recognition.onerror = function(event) {
            console.error('Erro no reconhecimento de voz:', event.error);
            stopListening();
            
            // Mensagens de erro discretas
            if (event.error === 'no-speech') {
                console.info('Nenhuma fala detectada. Tente novamente.');
            } else if (event.error === 'not-allowed') {
                console.warn('Permissão de microfone negada. Habilite o microfone nas configurações do navegador.');
            } else if (event.error === 'network') {
                console.error('Erro de rede ao processar voz.');
            }
        };
    }

    /**
     * Handler do clique no botão de voz.
     */
    function handleVoiceClick(e) {
        e.preventDefault();
        
        if (!recognition) {
            return;
        }

        if (isListening) {
            // Para reconhecimento
            recognition.stop();
        } else {
            // Inicia reconhecimento
            startListening();
        }
    }

    /**
     * Inicia o reconhecimento de voz.
     */
    function startListening() {
        if (!recognition || isListening) {
            return;
        }

        try {
            recognition.start();
            isListening = true;
            
            // Feedback visual
            $voiceBtn.addClass('dps-ai-public-voice--listening');
            $voiceBtn.attr('title', 'Ouvindo... Clique para parar');
            
        } catch (e) {
            console.error('Erro ao iniciar reconhecimento de voz:', e);
            stopListening();
        }
    }

    /**
     * Para o reconhecimento de voz.
     */
    function stopListening() {
        isListening = false;
        
        // Remove feedback visual
        $voiceBtn.removeClass('dps-ai-public-voice--listening');
        $voiceBtn.attr('title', 'Falar ao invés de digitar');
    }

    // Inicializa quando DOM estiver pronto
    $(document).ready(init);

})(jQuery);
