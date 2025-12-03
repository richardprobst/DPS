/**
 * Client Portal JavaScript
 * Gerencia interações do Portal do Cliente DPS
 * 
 * @package DPS Client Portal
 * @version 2.0.0
 */

(function() {
    'use strict';

    /**
     * Configuração do chat
     */
    var chatConfig = {
        pollInterval: 10000, // 10 segundos
        clientId: 0,
        isOpen: false,
        lastMessageId: 0,
        pollTimer: null
    };

    /**
     * Inicializa os handlers do portal
     */
    function init() {
        handleTabNavigation();
        handleFormSubmits();
        handleFileUploadPreview();
        handleSmoothScroll();
        initChatWidget();
    }

    /**
     * Gerencia navegação por tabs
     */
    function handleTabNavigation() {
        var tabs = document.querySelectorAll('.dps-portal-tabs__link');
        var panels = document.querySelectorAll('.dps-portal-tab-panel');
        
        if (!tabs.length || !panels.length) return;
        
        // Obtém tab ativa da URL hash ou usa a primeira
        var activeTab = window.location.hash.replace('#tab-', '') || 'inicio';
        
        tabs.forEach(function(tab) {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                
                var targetTab = this.getAttribute('data-tab');
                if (!targetTab) return;
                
                // Remove active de todas as tabs
                tabs.forEach(function(t) {
                    t.classList.remove('is-active');
                    t.setAttribute('aria-selected', 'false');
                });
                
                // Remove active de todos os panels
                panels.forEach(function(p) {
                    p.classList.remove('is-active');
                    p.setAttribute('aria-hidden', 'true');
                });
                
                // Ativa a tab clicada
                this.classList.add('is-active');
                this.setAttribute('aria-selected', 'true');
                
                // Ativa o panel correspondente
                var targetPanel = document.getElementById('panel-' + targetTab);
                if (targetPanel) {
                    targetPanel.classList.add('is-active');
                    targetPanel.setAttribute('aria-hidden', 'false');
                }
                
                // Atualiza URL hash
                if (history.pushState) {
                    history.pushState(null, null, '#tab-' + targetTab);
                }
            });
        });
        
        // Ativa tab inicial
        var initialTab = document.querySelector('[data-tab="' + activeTab + '"]');
        if (initialTab) {
            initialTab.click();
        } else if (tabs[0]) {
            tabs[0].click();
        }
    }

    /**
     * Inicializa o widget de chat
     */
    function initChatWidget() {
        var chatWidget = document.querySelector('.dps-chat-widget');
        if (!chatWidget) return;
        
        var toggle = chatWidget.querySelector('.dps-chat-toggle');
        var chatWindow = chatWidget.querySelector('.dps-chat-window');
        var closeBtn = chatWidget.querySelector('.dps-chat-header__close');
        var form = chatWidget.querySelector('.dps-chat-input__form');
        var input = chatWidget.querySelector('.dps-chat-input__field');
        
        // Obtém client_id do data attribute
        chatConfig.clientId = parseInt(chatWidget.getAttribute('data-client-id')) || 0;
        
        // Toggle do chat
        if (toggle) {
            toggle.addEventListener('click', function() {
                toggleChat();
            });
        }
        
        // Fechar chat
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                closeChat();
            });
        }
        
        // Enviar mensagem
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var message = input ? input.value.trim() : '';
                if (message) {
                    sendChatMessage(message);
                    input.value = '';
                }
            });
        }
        
        // Tecla Enter para enviar
        if (input) {
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    form.dispatchEvent(new Event('submit'));
                }
            });
        }
        
        // Carrega mensagens ao iniciar
        loadChatMessages();
    }

    /**
     * Abre/fecha o chat
     */
    function toggleChat() {
        var chatWidget = document.querySelector('.dps-chat-widget');
        var toggle = chatWidget.querySelector('.dps-chat-toggle');
        var chatWindow = chatWidget.querySelector('.dps-chat-window');
        
        chatConfig.isOpen = !chatConfig.isOpen;
        
        if (chatConfig.isOpen) {
            chatWindow.classList.add('is-open');
            toggle.classList.add('is-open');
            startPolling();
            scrollToBottom();
            
            // Marca mensagens como lidas
            markMessagesAsRead();
        } else {
            chatWindow.classList.remove('is-open');
            toggle.classList.remove('is-open');
            stopPolling();
        }
    }

    /**
     * Fecha o chat
     */
    function closeChat() {
        var chatWidget = document.querySelector('.dps-chat-widget');
        var toggle = chatWidget.querySelector('.dps-chat-toggle');
        var chatWindow = chatWidget.querySelector('.dps-chat-window');
        
        chatConfig.isOpen = false;
        chatWindow.classList.remove('is-open');
        toggle.classList.remove('is-open');
        stopPolling();
    }

    /**
     * Carrega mensagens do chat via AJAX
     */
    function loadChatMessages() {
        if (!chatConfig.clientId || !window.dpsPortalChat) return;
        
        var xhr = new XMLHttpRequest();
        xhr.open('POST', window.dpsPortalChat.ajaxUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success && response.data) {
                        renderChatMessages(response.data.messages);
                        updateUnreadBadge(response.data.unread_count);
                        if (response.data.messages && response.data.messages.length) {
                            chatConfig.lastMessageId = response.data.messages[response.data.messages.length - 1].id;
                        }
                    }
                } catch (e) {
                    console.error('Erro ao carregar mensagens:', e);
                }
            }
        };
        
        var params = 'action=dps_chat_get_messages';
        params += '&client_id=' + chatConfig.clientId;
        params += '&nonce=' + window.dpsPortalChat.nonce;
        
        xhr.send(params);
    }

    /**
     * Renderiza mensagens no chat
     */
    function renderChatMessages(messages) {
        var container = document.querySelector('.dps-chat-messages');
        if (!container) return;
        
        if (!messages || !messages.length) {
            container.innerHTML = '<div class="dps-chat-empty">' +
                '<div class="dps-chat-empty__icon">💬</div>' +
                '<div class="dps-chat-empty__text">Inicie uma conversa com nossa equipe!</div>' +
                '</div>';
            return;
        }
        
        var html = '';
        messages.forEach(function(msg) {
            var isClient = msg.sender === 'client';
            var cssClass = isClient ? 'dps-chat-message--client' : 'dps-chat-message--admin';
            
            html += '<div class="dps-chat-message ' + cssClass + '" data-id="' + msg.id + '">';
            html += '<div class="dps-chat-message__content">' + escapeHtml(msg.content) + '</div>';
            html += '<span class="dps-chat-message__time">' + msg.time + '</span>';
            html += '</div>';
        });
        
        container.innerHTML = html;
        scrollToBottom();
    }

    /**
     * Envia mensagem via AJAX
     */
    function sendChatMessage(message) {
        if (!chatConfig.clientId || !window.dpsPortalChat) return;
        
        var sendBtn = document.querySelector('.dps-chat-input__send');
        if (sendBtn) sendBtn.disabled = true;
        
        // Adiciona mensagem otimisticamente
        addOptimisticMessage(message);
        
        var xhr = new XMLHttpRequest();
        xhr.open('POST', window.dpsPortalChat.ajaxUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onload = function() {
            if (sendBtn) sendBtn.disabled = false;
            
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        // Recarrega mensagens para sincronizar
                        loadChatMessages();
                    } else {
                        alert('Erro ao enviar mensagem. Tente novamente.');
                    }
                } catch (e) {
                    console.error('Erro ao enviar mensagem:', e);
                }
            }
        };
        
        var params = 'action=dps_chat_send_message';
        params += '&client_id=' + chatConfig.clientId;
        params += '&message=' + encodeURIComponent(message);
        params += '&nonce=' + window.dpsPortalChat.nonce;
        
        xhr.send(params);
    }

    /**
     * Adiciona mensagem de forma otimista (antes da confirmação do servidor)
     */
    function addOptimisticMessage(message) {
        var container = document.querySelector('.dps-chat-messages');
        if (!container) return;
        
        // Remove estado vazio se houver
        var empty = container.querySelector('.dps-chat-empty');
        if (empty) empty.remove();
        
        var div = document.createElement('div');
        div.className = 'dps-chat-message dps-chat-message--client';
        div.innerHTML = '<div class="dps-chat-message__content">' + escapeHtml(message) + '</div>' +
            '<span class="dps-chat-message__time">Agora</span>';
        
        container.appendChild(div);
        scrollToBottom();
    }

    /**
     * Atualiza badge de mensagens não lidas
     */
    function updateUnreadBadge(count) {
        var badge = document.querySelector('.dps-chat-badge');
        if (!badge) return;
        
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'flex';
        } else {
            badge.textContent = '';
            badge.style.display = 'none';
        }
    }

    /**
     * Marca mensagens como lidas
     */
    function markMessagesAsRead() {
        if (!chatConfig.clientId || !window.dpsPortalChat) return;
        
        var xhr = new XMLHttpRequest();
        xhr.open('POST', window.dpsPortalChat.ajaxUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        var params = 'action=dps_chat_mark_read';
        params += '&client_id=' + chatConfig.clientId;
        params += '&nonce=' + window.dpsPortalChat.nonce;
        
        xhr.send(params);
        
        // Limpa badge
        updateUnreadBadge(0);
    }

    /**
     * Inicia polling para novas mensagens
     */
    function startPolling() {
        stopPolling();
        chatConfig.pollTimer = setInterval(function() {
            loadChatMessages();
        }, chatConfig.pollInterval);
    }

    /**
     * Para o polling
     */
    function stopPolling() {
        if (chatConfig.pollTimer) {
            clearInterval(chatConfig.pollTimer);
            chatConfig.pollTimer = null;
        }
    }

    /**
     * Scroll para o final do chat
     */
    function scrollToBottom() {
        var container = document.querySelector('.dps-chat-messages');
        if (container) {
            container.scrollTop = container.scrollHeight;
        }
    }

    /**
     * Escape HTML para prevenir XSS
     */
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Adiciona feedback visual durante submit de formulários
     */
    function handleFormSubmits() {
        var forms = document.querySelectorAll('.dps-portal-form');
        
        forms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                var submitBtn = form.querySelector('.dps-submit-btn');
                
                if (submitBtn && !submitBtn.disabled) {
                    // Salva texto original
                    var originalText = submitBtn.textContent;
                    
                    // Desabilita botão e mostra "Salvando..."
                    submitBtn.disabled = true;
                    submitBtn.classList.add('is-loading');
                    submitBtn.textContent = 'Salvando...';
                    
                    // Se houver erro de validação HTML5, reabilita o botão
                    setTimeout(function() {
                        if (!form.checkValidity()) {
                            submitBtn.disabled = false;
                            submitBtn.classList.remove('is-loading');
                            submitBtn.textContent = originalText;
                        }
                    }, 100);
                }
            });
        });
    }

    /**
     * Preview de upload de foto
     */
    function handleFileUploadPreview() {
        var fileInputs = document.querySelectorAll('.dps-file-upload__input');
        
        fileInputs.forEach(function(input) {
            input.addEventListener('change', function() {
                var file = this.files[0];
                var uploadDiv = this.closest('.dps-file-upload');
                var preview = uploadDiv ? uploadDiv.querySelector('.dps-file-upload__preview') : null;
                
                if (!preview) return;
                
                if (file && file.type.match('image.*')) {
                    var reader = new FileReader();
                    
                    reader.onload = function(e) {
                        preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview">';
                    };
                    
                    reader.readAsDataURL(file);
                } else {
                    preview.innerHTML = '';
                }
            });
        });
    }

    /**
     * Implementa scroll suave para links de âncora
     * (Alternativa caso scroll-behavior: smooth no CSS não funcione em todos os navegadores)
     */
    function handleSmoothScroll() {
        var navLinks = document.querySelectorAll('.dps-portal-nav__link');
        
        navLinks.forEach(function(link) {
            link.addEventListener('click', function(e) {
                var href = this.getAttribute('href');
                
                // Verifica se é uma âncora
                if (href && href.startsWith('#')) {
                    var target = document.querySelector(href);
                    
                    if (target) {
                        e.preventDefault();
                        
                        // Scroll suave
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                        
                        // Atualiza URL sem reload
                        if (history.pushState) {
                            history.pushState(null, null, href);
                        }
                    }
                }
            });
        });
    }

    // Inicializa quando DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
