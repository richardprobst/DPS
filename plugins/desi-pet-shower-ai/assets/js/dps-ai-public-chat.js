/**
 * JavaScript do Chat Público de IA.
 *
 * Gerencia interações do chat público para visitantes do site.
 * Inclui: envio de perguntas, exibição de respostas, FAQs, feedback,
 * modo flutuante, reconhecimento de voz, formatação Markdown, cópia de texto.
 *
 * @since 1.6.0
 * @updated 1.7.0 - Adicionadas funcionalidades de UX modernas
 */

(function($) {
    'use strict';

    // Constantes
    var HISTORY_EXPIRATION_MINUTES = 30;
    var MAX_CHARS = 500;
    var WARNING_THRESHOLD = 400;

    // Variáveis globais
    var $widget = null;
    var $messages = null;
    var $input = null;
    var $submit = null;
    var $voiceBtn = null;
    var $typing = null;
    var $charCounter = null;
    var nonce = '';
    var isFloating = false;
    var conversationHistory = [];
    var recognition = null;
    var isListening = false;
    var messageCount = 0;

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

        // Adiciona contador de caracteres
        addCharCounter();

        // Inicializa reconhecimento de voz
        initVoiceRecognition();

        // Restaura histórico da sessão
        restoreHistory();

        // Event listeners
        bindEvents();

        // Atualiza contador de mensagens
        updateMessageCount();
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

        // Auto-resize do textarea e contador de caracteres
        $input.on('input', function() {
            autoResizeTextarea(this);
            updateCharCounter();
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
                updateCharCounter();
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

        // Copiar mensagem
        $widget.on('click', '.dps-ai-public-copy-btn', handleCopyMessage);

        // Limpar conversa
        $widget.on('click', '.dps-ai-public-clear-btn', handleClearConversation);
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

        // Formata o conteúdo
        var formattedContent = formatMessage(content);

        // Ações da mensagem (feedback, copiar)
        var actionsHtml = '';
        if (type === 'assistant' && messageId) {
            actionsHtml = '<div class="dps-ai-public-message-actions">' +
                '<button type="button" class="dps-ai-public-copy-btn" data-content="' + escapeHtml(content) + '" title="Copiar resposta">' +
                    '<span>📋</span> Copiar' +
                '</button>' +
                '<div class="dps-ai-public-feedback" data-message-id="' + messageId + '" data-question="' + escapeHtml(question) + '">' +
                    '<span class="dps-ai-public-feedback-label">' + dpsAIPublicChat.i18n.wasHelpful + '</span>' +
                    '<button type="button" class="dps-ai-public-feedback-btn" data-feedback="positive" aria-label="Útil">👍</button>' +
                    '<button type="button" class="dps-ai-public-feedback-btn" data-feedback="negative" aria-label="Não útil">👎</button>' +
                '</div>' +
            '</div>';
        }

        var html = '<div class="' + messageClass + '">' +
            '<div class="dps-ai-public-message-avatar">' + avatar + '</div>' +
            '<div class="dps-ai-public-message-content">' +
                '<div class="dps-ai-public-message-text">' + formattedContent + '</div>' +
                actionsHtml +
            '</div>' +
        '</div>';

        $messages.append(html);
        
        // Atualiza contador
        messageCount++;
        updateMessageCount();
        
        // Autoscroll inteligente
        smartScrollToBottom();

        // Salva no histórico
        if (type !== 'error' && type !== 'rate-limit') {
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
     * Formata mensagem para exibição com suporte a Markdown básico.
     *
     * @param {string} text Texto da mensagem.
     * @return {string} Texto formatado em HTML.
     */
    function formatMessage(text) {
        if (!text) return '';
        
        // Escapa HTML primeiro
        text = escapeHtml(text);
        
        // Converte Markdown básico
        
        // Negrito: **texto** ou __texto__
        text = text.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        text = text.replace(/__(.+?)__/g, '<strong>$1</strong>');
        
        // Itálico: *texto* ou _texto_ (simplificado para compatibilidade)
        // Processa apenas quando há espaço ou início de linha antes
        text = text.replace(/(^|\s)\*([^*]+)\*(\s|$)/g, '$1<em>$2</em>$3');
        text = text.replace(/(^|\s)_([^_]+)_(\s|$)/g, '$1<em>$2</em>$3');
        
        // Código inline: `texto`
        text = text.replace(/`([^`]+)`/g, '<code>$1</code>');
        
        // Listas não ordenadas: linhas começando com - ou *
        text = text.replace(/^[\-\*]\s+(.+)$/gm, '<li>$1</li>');
        // Agrupa <li> consecutivos em <ul>
        text = text.replace(/(<li>.*<\/li>\n?)+/g, function(match) {
            return '<ul>' + match.replace(/\n/g, '') + '</ul>';
        });
        
        // Listas ordenadas: linhas começando com número.
        text = text.replace(/^\d+\.\s+(.+)$/gm, '<li>$1</li>');
        // Agrupa <li> de listas ordenadas em <ol>
        text = text.replace(/(<li>.*<\/li>\n?)+/g, function(match) {
            // Verifica se já tem <ul>, senão usa <ol>
            if (match.indexOf('<ul>') === -1 && match.indexOf('</ul>') === -1) {
                return '<ol>' + match.replace(/\n/g, '') + '</ol>';
            }
            return match;
        });
        
        // Converte quebras de linha (exceto dentro de listas)
        text = text.replace(/\n(?![<])/g, '<br>');
        
        // Converte URLs em links clicáveis
        text = text.replace(
            /(https?:\/\/[^\s<]+)/gi,
            '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>'
        );
        
        // Envolve em parágrafos se não tiver tags de bloco
        if (text.indexOf('<ul>') === -1 && text.indexOf('<ol>') === -1) {
            // Divide por quebras duplas para criar parágrafos
            var paragraphs = text.split(/<br><br>/);
            if (paragraphs.length > 1) {
                text = paragraphs.map(function(p) {
                    return '<p>' + p + '</p>';
                }).join('');
            } else {
                text = '<p>' + text + '</p>';
            }
        }
        
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

    /**
     * Adiciona contador de caracteres ao input.
     */
    function addCharCounter() {
        var $wrapper = $input.closest('.dps-ai-public-input-wrapper');
        $charCounter = $('<span class="dps-ai-public-char-counter">0/' + MAX_CHARS + '</span>');
        $wrapper.append($charCounter);
    }

    /**
     * Atualiza o contador de caracteres.
     */
    function updateCharCounter() {
        if (!$charCounter) return;
        
        var length = $input.val().length;
        $charCounter.text(length + '/' + MAX_CHARS);
        
        // Remove classes anteriores
        $charCounter.removeClass('warning limit');
        
        if (length >= MAX_CHARS) {
            $charCounter.addClass('limit');
        } else if (length >= WARNING_THRESHOLD) {
            $charCounter.addClass('warning');
        }
    }

    /**
     * Atualiza contador de mensagens na toolbar.
     */
    function updateMessageCount() {
        var $msgCount = $widget.find('.dps-ai-public-msg-count');
        if ($msgCount.length) {
            var count = $messages.find('.dps-ai-public-message').length;
            $msgCount.text(count + ' msg');
        }
    }

    /**
     * Handler para copiar mensagem.
     */
    function handleCopyMessage(e) {
        e.preventDefault();
        var $btn = $(this);
        var content = $btn.data('content');
        
        if (!content) return;
        
        // Usa a Clipboard API moderna
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(content).then(function() {
                showCopyFeedback($btn);
            }).catch(function() {
                fallbackCopyText(content, $btn);
            });
        } else {
            fallbackCopyText(content, $btn);
        }
    }

    /**
     * Fallback para copiar texto em navegadores antigos.
     */
    function fallbackCopyText(text, $btn) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();
        
        try {
            document.execCommand('copy');
            showCopyFeedback($btn);
        } catch (err) {
            console.error('Erro ao copiar:', err);
        }
        
        document.body.removeChild(textarea);
    }

    /**
     * Mostra feedback visual de cópia bem-sucedida.
     */
    function showCopyFeedback($btn) {
        var originalHtml = $btn.html();
        $btn.addClass('copied').html('<span>✓</span> Copiado!');
        
        setTimeout(function() {
            $btn.removeClass('copied').html(originalHtml);
        }, 2000);
    }

    /**
     * Handler para limpar conversa.
     */
    function handleClearConversation(e) {
        e.preventDefault();
        
        // Confirmação antes de limpar
        var confirmMsg = dpsAIPublicChat.i18n.clearConfirm || 'Tem certeza que deseja limpar a conversa?';
        if (!confirm(confirmMsg)) {
            return;
        }
        
        // Limpa histórico
        window.dpsAIPublicClearHistory();
        
        // Atualiza contador
        messageCount = 1; // Mantém mensagem de boas-vindas
        updateMessageCount();
    }

    // Inicializa quando DOM estiver pronto
    $(document).ready(init);

})(jQuery);
