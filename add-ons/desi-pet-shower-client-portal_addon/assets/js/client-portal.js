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
        cleanTokenFromURL();
        handleTabNavigation();
        handleFormSubmits();
        handleFileUploadPreview();
        handleSmoothScroll();
        handleToggleDetails(); // Phase 2: toggle for financial details
        initChatWidget();
    }

    /**
     * Remove token de autenticação da URL por segurança
     * Chamado após autenticação bem-sucedida para limpar o dps_token da URL
     */
    function cleanTokenFromURL() {
        // Verifica se há dps_token na URL
        if (window.location.search.indexOf('dps_token=') === -1) {
            return;
        }

        // Remove o parâmetro dps_token da URL usando History API
        if (window.history && window.history.replaceState) {
            try {
                // Método moderno com URL API (navegadores atuais)
                if (typeof URL !== 'undefined') {
                    var url = new URL(window.location.href);
                    url.searchParams.delete('dps_token');
                    window.history.replaceState({}, document.title, url.toString());
                } else {
                    // Fallback para navegadores antigos (IE)
                    var currentUrl = window.location.href;
                    var cleanUrl = currentUrl.replace(/([?&])dps_token=[^&]+(&|$)/, function(match, prefix, suffix) {
                        // Se era o único parâmetro (?dps_token=...), remove o ?
                        if (prefix === '?' && suffix === '') {
                            return '';
                        }
                        // Se era o primeiro parâmetro (?dps_token=...&), mantém o ?
                        if (prefix === '?' && suffix === '&') {
                            return '?';
                        }
                        // Se era um parâmetro intermediário (&dps_token=...&), remove completamente
                        if (prefix === '&' && suffix === '&') {
                            return '&';
                        }
                        // Se era o último parâmetro (&dps_token=...), remove o &
                        return '';
                    });
                    
                    // Remove & duplicados e & no final da query string
                    cleanUrl = cleanUrl.replace(/&&+/g, '&').replace(/[?&]$/, '');
                    
                    window.history.replaceState({}, document.title, cleanUrl);
                }
            } catch (e) {
                // Em caso de erro, apenas loga sem quebrar a página
                // O token ficará visível na URL mas a autenticação já foi feita
                if (console && console.warn) {
                    console.warn('Não foi possível limpar token da URL:', e);
                }
            }
        }
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

    /**
     * Gerencia toggle de detalhes (ex: detalhes financeiros)
     * Phase 2: melhor UX em mobile com collapse/expand
     */
    function handleToggleDetails() {
        var toggleButtons = document.querySelectorAll('.dps-btn-toggle-details');
        
        toggleButtons.forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                var targetId = this.getAttribute('data-target');
                var target = document.getElementById(targetId);
                
                if (target) {
                    // Toggle visibility
                    if (target.style.display === 'none') {
                        target.style.display = 'block';
                        this.textContent = 'Ocultar Detalhes';
                    } else {
                        target.style.display = 'none';
                        this.textContent = 'Ver Detalhes';
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

/* ========================================
   TOAST NOTIFICATIONS (Fase 1.5)
   Sistema global de notificações
   ======================================== */

/**
 * DPS Toast - Sistema de notificações toast
 * 
 * Uso:
 * DPSToast.success('Dados salvos com sucesso!');
 * DPSToast.error('Erro ao processar solicitação');
 * DPSToast.warning('Atenção: link expira em 5 minutos');
 * DPSToast.info('Nova mensagem recebida');
 * DPSToast.show('Título', 'Mensagem', 'success', 5000);
 */
window.DPSToast = (function() {
    'use strict';
    
    var container = null;
    var toastCount = 0;
    
    /**
     * Inicializa o container de toasts
     */
    function initContainer() {
        if (!container) {
            container = document.createElement('div');
            container.className = 'dps-toast-container';
            container.setAttribute('role', 'status');
            container.setAttribute('aria-live', 'polite');
            document.body.appendChild(container);
        }
        return container;
    }
    
    /**
     * Obtém ícone para tipo de toast
     */
    function getIcon(type) {
        var icons = {
            'success': '✓',
            'error': '✕',
            'warning': '⚠',
            'info': 'ℹ'
        };
        return icons[type] || icons.info;
    }
    
    /**
     * Mostra um toast
     * 
     * @param {string} title - Título do toast
     * @param {string} message - Mensagem do toast
     * @param {string} type - Tipo: success, error, warning, info
     * @param {number} duration - Duração em ms (0 = não fecha automaticamente)
     */
    function show(title, message, type, duration) {
        type = type || 'info';
        duration = duration !== undefined ? duration : 5000;
        
        var cont = initContainer();
        var id = 'dps-toast-' + (++toastCount);
        
        // Cria elemento do toast
        var toast = document.createElement('div');
        toast.id = id;
        toast.className = 'dps-toast dps-toast--' + type;
        toast.setAttribute('role', 'alert');
        
        var iconSpan = document.createElement('span');
        iconSpan.className = 'dps-toast__icon';
        iconSpan.setAttribute('aria-hidden', 'true');
        iconSpan.textContent = getIcon(type);
        
        var contentDiv = document.createElement('div');
        contentDiv.className = 'dps-toast__content';
        
        if (title) {
            var titleP = document.createElement('p');
            titleP.className = 'dps-toast__title';
            titleP.textContent = title;
            contentDiv.appendChild(titleP);
        }
        
        if (message) {
            var messageP = document.createElement('p');
            messageP.className = 'dps-toast__message';
            messageP.textContent = message;
            contentDiv.appendChild(messageP);
        }
        
        var closeBtn = document.createElement('button');
        closeBtn.className = 'dps-toast__close';
        closeBtn.setAttribute('type', 'button');
        closeBtn.setAttribute('aria-label', 'Fechar notificação');
        closeBtn.textContent = '×';
        closeBtn.onclick = function() {
            hide(id);
        };
        
        toast.appendChild(iconSpan);
        toast.appendChild(contentDiv);
        toast.appendChild(closeBtn);
        
        cont.appendChild(toast);
        
        // Anima entrada
        setTimeout(function() {
            toast.classList.add('show');
        }, 10);
        
        // Auto-fecha se duration > 0
        if (duration > 0) {
            setTimeout(function() {
                hide(id);
            }, duration);
        }
        
        return id;
    }
    
    /**
     * Esconde um toast
     */
    function hide(id) {
        var toast = document.getElementById(id);
        if (toast) {
            toast.classList.remove('show');
            toast.classList.add('hide');
            setTimeout(function() {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }
    }
    
    /**
     * Atalhos para tipos comuns
     */
    function success(message, duration) {
        return show('Sucesso', message, 'success', duration);
    }
    
    function error(message, duration) {
        return show('Erro', message, 'error', duration);
    }
    
    function warning(message, duration) {
        return show('Atenção', message, 'warning', duration);
    }
    
    function info(message, duration) {
        return show('', message, 'info', duration);
    }
    
    // API pública
    return {
        show: show,
        hide: hide,
        success: success,
        error: error,
        warning: warning,
        info: info
    };
})();

/**
 * Substitui alertas padrão de mensagens por toasts
 * Converte <div class="dps-alert"> em toasts
 */
(function() {
    'use strict';
    
    function convertAlertsToToasts() {
        var alerts = document.querySelectorAll('.dps-alert, .dps-portal-notice');
        
        alerts.forEach(function(alert) {
            var message = alert.textContent.trim();
            var type = 'info';
            
            if (alert.classList.contains('dps-alert--success') || alert.classList.contains('dps-portal-notice--success')) {
                type = 'success';
            } else if (alert.classList.contains('dps-alert--danger') || alert.classList.contains('dps-portal-notice--error')) {
                type = 'error';
            } else if (alert.classList.contains('dps-alert--pending') || alert.classList.contains('dps-portal-notice--warning')) {
                type = 'warning';
            }
            
            if (message) {
                DPSToast.show('', message, type, 6000);
            }
            
            // Remove alert original
            alert.style.display = 'none';
        });
    }
    
    // Converte alerts existentes quando página carregar
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', convertAlertsToToasts);
    } else {
        convertAlertsToToasts();
    }
    
    // Observer para alerts adicionados dinamicamente
    var observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1 && (node.classList.contains('dps-alert') || node.classList.contains('dps-portal-notice'))) {
                        setTimeout(convertAlertsToToasts, 100);
                    }
                });
            }
        });
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
})();

/* ========================================
   SKELETON LOADERS (Fase 2.7)
   Exibe placeholders durante carregamento
   ======================================== */

/**
 * DPS Skeleton - Sistema de skeleton loaders
 * Melhora a percepção de velocidade mostrando placeholders
 */
window.DPSSkeleton = (function() {
    'use strict';
    
    /**
     * Cria skeleton para histórico
     */
    function createHistorySkeleton() {
        return `
            <div class="dps-portal-skeleton-history" aria-hidden="true">
                <div class="dps-skeleton dps-skeleton--title"></div>
                <div class="dps-skeleton dps-skeleton--table-row"></div>
                <div class="dps-skeleton dps-skeleton--table-row"></div>
                <div class="dps-skeleton dps-skeleton--table-row"></div>
                <div class="dps-skeleton dps-skeleton--table-row"></div>
                <div class="dps-skeleton dps-skeleton--table-row"></div>
            </div>
        `;
    }
    
    /**
     * Cria skeleton para galeria
     */
    function createGallerySkeleton() {
        return `
            <div class="dps-portal-skeleton-gallery" aria-hidden="true">
                <div class="dps-skeleton dps-skeleton--card"></div>
                <div class="dps-skeleton dps-skeleton--card"></div>
                <div class="dps-skeleton dps-skeleton--card"></div>
                <div class="dps-skeleton dps-skeleton--card"></div>
            </div>
        `;
    }
    
    /**
     * Cria skeleton genérico com múltiplas linhas de texto
     */
    function createTextSkeleton(lines) {
        lines = lines || 3;
        var html = '<div class="dps-skeleton-container" aria-hidden="true">';
        html += '<div class="dps-skeleton dps-skeleton--title"></div>';
        for (var i = 0; i < lines; i++) {
            html += '<div class="dps-skeleton dps-skeleton--text"></div>';
        }
        html += '</div>';
        return html;
    }
    
    /**
     * Mostra skeleton em um container
     */
    function show(container, type) {
        if (typeof container === 'string') {
            container = document.querySelector(container);
        }
        
        if (!container) return;
        
        var skeleton;
        switch (type) {
            case 'history':
                skeleton = createHistorySkeleton();
                break;
            case 'gallery':
                skeleton = createGallerySkeleton();
                break;
            case 'text':
            default:
                skeleton = createTextSkeleton();
                break;
        }
        
        container.innerHTML = skeleton;
        container.classList.remove('dps-content-loaded');
    }
    
    /**
     * Remove skeleton e marca como loaded
     */
    function hide(container) {
        if (typeof container === 'string') {
            container = document.querySelector(container);
        }
        
        if (!container) return;
        
        container.classList.add('dps-content-loaded');
        
        // Remove skeletons após animação
        setTimeout(function() {
            var skeletons = container.querySelectorAll('.dps-skeleton, .dps-portal-skeleton-history, .dps-portal-skeleton-gallery, .dps-skeleton-container');
            skeletons.forEach(function(el) {
                if (el.parentNode) {
                    el.parentNode.removeChild(el);
                }
            });
        }, 300);
    }
    
    // API pública
    return {
        show: show,
        hide: hide,
        createHistorySkeleton: createHistorySkeleton,
        createGallerySkeleton: createGallerySkeleton,
        createTextSkeleton: createTextSkeleton
    };
})();

/**
 * Auto-aplica skeletons em tab panels durante navegação
 */
(function() {
    'use strict';
    
    // Aguarda navegação entre tabs
    document.addEventListener('click', function(e) {
        var tabLink = e.target.closest('.dps-portal-tabs__link');
        if (!tabLink) return;
        
        var tabId = tabLink.getAttribute('data-tab');
        if (!tabId) return;
        
        var panel = document.getElementById('panel-' + tabId);
        if (!panel || panel.classList.contains('is-active')) return;
        
        // Mostra skeleton se painel estiver vazio ou muito simples
        var hasContent = panel.querySelector('.dps-portal-section');
        if (!hasContent && !panel.classList.contains('dps-content-loaded')) {
            if (tabId === 'agendamentos') {
                DPSSkeleton.show(panel, 'history');
            } else if (tabId === 'galeria') {
                DPSSkeleton.show(panel, 'gallery');
            } else {
                DPSSkeleton.show(panel, 'text');
            }
            
            // Remove skeleton após delay (simula carregamento)
            setTimeout(function() {
                DPSSkeleton.hide(panel);
            }, 500);
        }
    });
})();
