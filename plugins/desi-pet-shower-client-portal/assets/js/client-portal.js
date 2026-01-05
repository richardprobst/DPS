/**
 * Client Portal JavaScript
 * Gerencia interações do Portal do Cliente DPS
 * 
 * @package DPS Client Portal
 * @version 2.0.0
 */

(function() {
    'use strict';

    // Compatibilidade: mapeia dpsPortalChat para dpsPortal para código legado
    window.dpsPortalChat = window.dpsPortal || {};
    if (window.dpsPortal && !window.dpsPortalChat.nonce) {
        window.dpsPortalChat.nonce = window.dpsPortal.chatNonce;
        window.dpsPortalChat.ajaxUrl = window.dpsPortal.ajaxUrl;
        window.dpsPortalChat.clientId = window.dpsPortal.clientId;
    }

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
        handlePortalMessages(); // Phase 2: feedback for actions
        handleLoyalty();
        initChatWidget();
        handleQuickActions(); // Fase 3: Quick Actions na aba Início
        handleReviewForm(); // Fase 5: Formulário de avaliação interna
        handlePetHistoryTabs(); // Revisão Jan/2026: Navegação por pet na aba Histórico
        handleRepeatService(); // Revisão Jan/2026: Botão repetir serviço via WhatsApp
    }

    /**
     * Gerencia ações rápidas (Quick Actions) na aba Início
     * Permite abrir chat, navegar para tabs e outras ações rápidas
     */
    function handleQuickActions() {
        // Botões de ação rápida que abrem o chat
        var chatButtons = document.querySelectorAll('[data-action="open-chat"]');
        chatButtons.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                // Abre o chat widget
                var chatToggle = document.querySelector('.dps-chat-toggle');
                if (chatToggle) {
                    chatToggle.click();
                }
            });
        });

        // Botões de ação rápida que navegam para tabs
        var tabButtons = document.querySelectorAll('.dps-quick-action[data-tab], .dps-link-button[data-tab]');
        // Lista de tabs válidas para prevenir DOM-based XSS
        var validTabs = ['inicio', 'fidelidade', 'avaliacoes', 'mensagens', 'agendamentos', 'historico-pets', 'galeria', 'dados'];
        
        tabButtons.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var targetTab = this.getAttribute('data-tab');
                
                // Valida se a tab é uma das tabs conhecidas
                if (!targetTab || validTabs.indexOf(targetTab) === -1) {
                    return;
                }

                // Encontra e clica na tab correspondente (selector seguro após validação)
                var tabLink = document.querySelector('.dps-portal-tabs__link[data-tab="' + targetTab + '"]');
                if (tabLink) {
                    tabLink.click();
                    // Scroll suave para o topo do conteúdo
                    var tabContent = document.querySelector('.dps-portal-tab-content');
                    if (tabContent) {
                        tabContent.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }
            });
        });
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
     * Interações da aba de fidelidade.
     */
    function handleLoyalty() {
        var section = document.querySelector('.dps-portal-loyalty');
        if (!section || !window.dpsPortal || !window.dpsPortal.loyalty) return;

        var loyaltyConfig = window.dpsPortal.loyalty;
        var ajaxUrl = window.dpsPortal.ajaxUrl;

        function copyToClipboard(text) {
            if (!navigator.clipboard) {
                return false;
            }
            navigator.clipboard.writeText(text).then(function() {
                if (window.DPSToast) {
                    window.DPSToast.success('Link copiado!', 2500);
                }
            }).catch(function() {});
            return true;
        }

        var copyButtons = section.querySelectorAll('.dps-portal-copy');
        copyButtons.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var target = btn.getAttribute('data-copy-target') || '';
                copyToClipboard(target);
            });
        });

        var historyButton = section.querySelector('.dps-loyalty-history-more');
        var historyList = section.querySelector('.dps-loyalty-history__list');
        var historyTrigger = section.querySelector('.dps-loyalty-history-trigger');

        if (historyTrigger && historyButton) {
            historyTrigger.addEventListener('click', function() {
                historyButton.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        }

        function appendHistoryItems(items) {
            if (!historyList) {
                historyList = document.createElement('ul');
                historyList.className = 'dps-loyalty-history__list';
                section.querySelector('.dps-loyalty-history').insertBefore(historyList, historyButton);
            }

            items.forEach(function(item) {
                var li = document.createElement('li');
                li.className = 'dps-loyalty-history__item';
                var isCredit = item.action === 'add' || item.action === 'credit_add';
                li.innerHTML = '<div><p class="dps-loyalty-history__context">' + item.context + '</p>' +
                    '<span class="dps-loyalty-history__date">' + item.date + '</span></div>' +
                    '<span class="dps-loyalty-history__points dps-loyalty-history__points--' + item.action + '">' + (isCredit ? '+' : '-') + item.points + '</span>';
                historyList.appendChild(li);
            });
        }

        function loadHistory(button) {
            if (!button) return;
            var offset = parseInt(button.getAttribute('data-offset'), 10) || loyaltyConfig.historyLimit || 5;
            var limit = parseInt(button.getAttribute('data-limit'), 10) || loyaltyConfig.historyLimit || 5;

            button.disabled = true;
            button.textContent = loyaltyConfig.i18n.loading || 'Carregando...';

            var formData = new FormData();
            formData.append('action', 'dps_loyalty_get_history');
            formData.append('nonce', loyaltyConfig.nonce);
            formData.append('limit', limit);
            formData.append('offset', offset);

            fetch(ajaxUrl, {
                method: 'POST',
                body: formData
            }).then(function(res) { return res.json(); }).then(function(res) {
                if (res && res.success && res.data && res.data.items) {
                    appendHistoryItems(res.data.items);

                    if (res.data.has_more) {
                        button.disabled = false;
                        button.textContent = 'Carregar mais';
                        button.setAttribute('data-offset', res.data.next_offset || (offset + limit));
                    } else {
                        button.remove();
                    }
                } else {
                    button.disabled = false;
                    button.textContent = 'Carregar mais';
                }
            }).catch(function() {
                button.disabled = false;
                button.textContent = 'Carregar mais';
            });
        }

        if (historyButton) {
            historyButton.addEventListener('click', function() {
                loadHistory(historyButton);
            });
        }

        var redemptionForm = section.querySelector('.dps-loyalty-redemption-form');
        if (redemptionForm) {
            var input = redemptionForm.querySelector('#dps-loyalty-points-input');
            var feedback = redemptionForm.querySelector('.dps-loyalty-redemption__feedback');
            var submitBtn = redemptionForm.querySelector('.dps-loyalty-redeem-btn');

            redemptionForm.addEventListener('submit', function(e) {
                e.preventDefault();
                if (!submitBtn || !input) return;

                submitBtn.disabled = true;
                submitBtn.textContent = loyaltyConfig.i18n.loading || 'Carregando...';
                feedback.textContent = '';

                var formData = new FormData();
                formData.append('action', 'dps_loyalty_portal_redeem');
                formData.append('nonce', redemptionForm.getAttribute('data-nonce'));
                formData.append('points', parseInt(input.value, 10) || 0);

                fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData
                }).then(function(res) { return res.json(); }).then(function(res) {
                    if (res && res.success && res.data) {
                        var pointsEls = section.querySelectorAll('[data-loyalty-points]');
                        pointsEls.forEach(function(el) { el.textContent = res.data.points; });
                        var creditEl = section.querySelector('[data-loyalty-credit]');
                        if (creditEl) { creditEl.textContent = res.data.credit; }
                        feedback.textContent = res.data.message || loyaltyConfig.i18n.redeemSuccess;
                        feedback.classList.remove('is-error');
                        feedback.classList.add('is-success');
                        input.value = res.data.points;

                        var maxAttr = parseInt(redemptionForm.getAttribute('data-max-cents'), 10) || 0;
                        var rate = parseInt(redemptionForm.getAttribute('data-rate'), 10) || 1;
                        var maxByCap = maxAttr > 0 ? Math.floor((maxAttr / 100) * rate) : res.data.points;
                        var newMax = Math.min(res.data.points, maxByCap);
                        input.setAttribute('max', newMax);

                        if (window.DPSToast) {
                            window.DPSToast.success(res.data.message || loyaltyConfig.i18n.redeemSuccess, 5000);
                        }
                    } else {
                        var msg = res && res.data && res.data.message ? res.data.message : loyaltyConfig.i18n.redeemError;
                        feedback.textContent = msg;
                        feedback.classList.remove('is-success');
                        feedback.classList.add('is-error');
                        if (window.DPSToast) {
                            window.DPSToast.error(msg, 5000);
                        }
                    }
                }).catch(function() {
                    feedback.textContent = loyaltyConfig.i18n.redeemError;
                    feedback.classList.add('is-error');
                    if (window.DPSToast) {
                        window.DPSToast.error(loyaltyConfig.i18n.redeemError, 5000);
                    }
                }).finally(function() {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Resgatar pontos';
                });
            });
        }
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

    /**
     * Exibe toasts baseados em mensagens da URL (após ações do cliente)
     * Phase 2: Feedback de ações
     */
    function handlePortalMessages() {
        var urlParams = new URLSearchParams(window.location.search);
        var message = urlParams.get('portal_msg');
        
        if (!message) {
            return;
        }
        
        // Remove o parâmetro da URL
        if (window.history && window.history.replaceState) {
            var cleanUrl = window.location.pathname + window.location.hash;
            window.history.replaceState({}, document.title, cleanUrl);
        }
        
        // Mapeia mensagens para toasts
        var messages = {
            'updated': {
                type: 'success',
                title: 'Sucesso!',
                message: 'Seus dados foram atualizados com sucesso.'
            },
            'pet_updated': {
                type: 'success',
                title: 'Sucesso!',
                message: 'Dados do pet atualizados com sucesso.'
            },
            'message_sent': {
                type: 'success',
                title: 'Mensagem Enviada',
                message: 'Sua mensagem foi enviada para a equipe.'
            },
            'error': {
                type: 'error',
                title: 'Erro',
                message: 'Ocorreu um erro ao processar sua solicitação. Tente novamente.'
            },
            'unauthorized': {
                type: 'error',
                title: 'Acesso Negado',
                message: 'Você não tem permissão para acessar este recurso.'
            }
        };
        
        var toastData = messages[message] || messages.error;
        
        // Aguarda DPSToast estar disponível
        setTimeout(function() {
            if (window.DPSToast) {
                window.DPSToast.show(toastData.title, toastData.message, toastData.type, 5000);
            }
        }, 500);
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

    /**
     * Fase 4: Handlers para pedidos de agendamento
     */
    
    // Handler para botões de reagendamento
    document.addEventListener('click', function(e) {
        if (e.target.closest('.dps-btn-reschedule')) {
            e.preventDefault();
            var btn = e.target.closest('.dps-btn-reschedule');
            var appointmentId = btn.dataset.appointmentId;
            showRescheduleModal(appointmentId);
        }
    });

    // Handler para botões de cancelamento
    document.addEventListener('click', function(e) {
        if (e.target.closest('.dps-btn-cancel')) {
            e.preventDefault();
            var btn = e.target.closest('.dps-btn-cancel');
            var appointmentId = btn.dataset.appointmentId;
            showCancelModal(appointmentId);
        }
    });

    // Handler para botões "Repetir serviço"
    document.addEventListener('click', function(e) {
        if (e.target.closest('.dps-btn-repeat-service')) {
            e.preventDefault();
            var btn = e.target.closest('.dps-btn-repeat-service');
            var appointmentId = btn.dataset.appointmentId;
            var petId = btn.dataset.petId;
            var services = JSON.parse(btn.dataset.services || '[]');
            showRepeatServiceModal(appointmentId, petId, services);
        }
    });

    /**
     * Mostra modal de reagendamento
     */
    function showRescheduleModal(appointmentId) {
        var modal = createRequestModal('reschedule', appointmentId);
        document.body.appendChild(modal);
        modal.classList.add('is-active');
    }

    /**
     * Mostra modal de cancelamento
     */
    function showCancelModal(appointmentId) {
        if (!confirm('Tem certeza que deseja solicitar o cancelamento deste agendamento?\n\nA equipe do Banho e Tosa pode entrar em contato para confirmar.')) {
            return;
        }
        
        // Envia direto a solicitação de cancelamento
        submitAppointmentRequest({
            request_type: 'cancel',
            original_appointment_id: appointmentId,
            desired_date: '',
            desired_period: '',
            notes: 'Cliente solicitou cancelamento via portal'
        });
    }

    /**
     * Mostra modal para repetir serviço
     */
    function showRepeatServiceModal(appointmentId, petId, services) {
        var modal = createRequestModal('new', appointmentId, petId, services);
        document.body.appendChild(modal);
        modal.classList.add('is-active');
    }

    /**
     * Cria modal de pedido de agendamento
     */
    function createRequestModal(type, appointmentId, petId, services) {
        var modal = document.createElement('div');
        modal.className = 'dps-modal dps-appointment-request-modal';
        
        var titles = {
            reschedule: 'Solicitar Reagendamento',
            new: 'Repetir Serviço'
        };
        
        var title = titles[type] || 'Solicitar Agendamento';
        
        var html = '<div class="dps-modal__overlay"></div>';
        html += '<div class="dps-modal__content">';
        html += '<div class="dps-modal__header">';
        html += '<h3>' + title + '</h3>';
        html += '<button class="dps-modal__close" aria-label="Fechar">×</button>';
        html += '</div>';
        html += '<div class="dps-modal__body">';
        html += '<p class="dps-modal__notice"><strong>⚠️ Importante:</strong> Este é um <strong>pedido de agendamento</strong>. A equipe do Banho e Tosa irá confirmar o horário final com você.</p>';
        html += '<form class="dps-request-form" id="dps-request-form">';
        html += '<input type="hidden" name="request_type" value="' + type + '">';
        html += '<input type="hidden" name="original_appointment_id" value="' + (appointmentId || '') + '">';
        if (petId) {
            html += '<input type="hidden" name="pet_id" value="' + petId + '">';
        }
        html += '<div class="dps-form-field">';
        html += '<label for="desired_date">Data Desejada <span class="required">*</span></label>';
        html += '<input type="date" id="desired_date" name="desired_date" required min="' + getTomorrowDate() + '">';
        html += '</div>';
        html += '<div class="dps-form-field">';
        html += '<label for="desired_period">Período Desejado <span class="required">*</span></label>';
        html += '<select id="desired_period" name="desired_period" required>';
        html += '<option value="">Selecione...</option>';
        html += '<option value="morning">Manhã</option>';
        html += '<option value="afternoon">Tarde</option>';
        html += '</select>';
        html += '</div>';
        html += '<div class="dps-form-field">';
        html += '<label for="notes">Observações (opcional)</label>';
        html += '<textarea id="notes" name="notes" rows="3" placeholder="Alguma preferência ou observação?"></textarea>';
        html += '</div>';
        html += '<div class="dps-form-actions">';
        html += '<button type="button" class="button dps-modal-cancel">Cancelar</button>';
        html += '<button type="submit" class="button button-primary">Enviar Solicitação</button>';
        html += '</div>';
        html += '</form>';
        html += '</div>';
        html += '</div>';
        
        modal.innerHTML = html;
        
        // Event listeners
        modal.querySelector('.dps-modal__close').addEventListener('click', function() {
            closeModal(modal);
        });
        
        modal.querySelector('.dps-modal__overlay').addEventListener('click', function() {
            closeModal(modal);
        });
        
        modal.querySelector('.dps-modal-cancel').addEventListener('click', function() {
            closeModal(modal);
        });
        
        modal.querySelector('#dps-request-form').addEventListener('submit', function(e) {
            e.preventDefault();
            var formData = new FormData(e.target);
            var data = {
                request_type: formData.get('request_type'),
                original_appointment_id: formData.get('original_appointment_id'),
                pet_id: formData.get('pet_id') || petId || '',
                desired_date: formData.get('desired_date'),
                desired_period: formData.get('desired_period'),
                notes: formData.get('notes'),
                services: services || []
            };
            
            submitAppointmentRequest(data);
            closeModal(modal);
        });
        
        return modal;
    }

    /**
     * Fecha modal
     */
    function closeModal(modal) {
        modal.classList.remove('is-active');
        setTimeout(function() {
            modal.remove();
        }, 300);
    }

    /**
     * Retorna data de amanhã no formato YYYY-MM-DD
     */
    function getTomorrowDate() {
        var tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        return tomorrow.toISOString().split('T')[0];
    }

    /**
     * Envia pedido de agendamento via AJAX
     */
    function submitAppointmentRequest(data) {
        var submitBtn = document.querySelector('.dps-appointment-request-modal button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Enviando...';
        }
        
        fetch(dpsPortal.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'dps_create_appointment_request',
                nonce: dpsPortal.requestNonce,
                ...data
            })
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(result) {
            if (result.success) {
                showNotification(result.data.message, 'success');
                // Recarrega a página após 2 segundos para mostrar o pedido
                setTimeout(function() {
                    location.reload();
                }, 2000);
            } else {
                showNotification(result.data.message || 'Erro ao enviar solicitação', 'error');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Enviar Solicitação';
                }
            }
        })
        .catch(function(error) {
            showNotification('Erro ao enviar solicitação. Tente novamente.', 'error');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Enviar Solicitação';
            }
        });
    }

    /**
     * Mostra notificação na tela
     */
    function showNotification(message, type) {
        var notification = document.createElement('div');
        notification.className = 'dps-portal-notice dps-portal-notice--' + type;
        notification.textContent = message;
        notification.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 99999; max-width: 400px;';
        
        document.body.appendChild(notification);
        
        setTimeout(function() {
            notification.remove();
        }, 5000);
    }

    /**
     * Gerencia o formulário de avaliação interna
     * - Contador de caracteres para o comentário
     * - Feedback visual na seleção de estrelas
     * - Validação antes do envio
     */
    function handleReviewForm() {
        var form = document.getElementById('dps-review-internal-form');
        if (!form) {
            return;
        }

        // Contador de caracteres
        var textarea = form.querySelector('#review_comment');
        var charCount = document.getElementById('char-count');
        
        if (textarea && charCount) {
            textarea.addEventListener('input', function() {
                var count = this.value.length;
                charCount.textContent = count;
                
                // Cor de aviso quando próximo do limite
                if (count > 450) {
                    charCount.style.color = '#f59e0b';
                } else if (count >= 500) {
                    charCount.style.color = '#ef4444';
                } else {
                    charCount.style.color = '';
                }
            });
        }

        // Feedback visual aprimorado para seleção de estrelas
        var starInputs = form.querySelectorAll('.dps-star-input');
        var starLabels = form.querySelectorAll('.dps-star-label');
        var ratingHint = form.querySelector('.dps-star-rating-hint');
        
        var ratingMessages = {
            1: '😞 Pode melhorar',
            2: '😕 Razoável',
            3: '🙂 Bom',
            4: '😊 Muito bom!',
            5: '🤩 Excelente!'
        };

        starInputs.forEach(function(input, index) {
            input.addEventListener('change', function() {
                var rating = parseInt(this.value, 10);
                
                // Atualiza mensagem de feedback
                if (ratingHint && ratingMessages[rating]) {
                    ratingHint.textContent = ratingMessages[rating];
                    ratingHint.style.fontWeight = '600';
                    ratingHint.style.color = '#374151';
                }
                
                // Animação de confirmação
                starLabels.forEach(function(label, labelIndex) {
                    if (labelIndex >= (5 - rating)) {
                        label.style.transform = 'scale(1.15)';
                        setTimeout(function() {
                            label.style.transform = '';
                        }, 200);
                    }
                });
            });
        });

        // Validação antes do envio
        form.addEventListener('submit', function(e) {
            var selectedRating = form.querySelector('.dps-star-input:checked');
            
            if (!selectedRating) {
                e.preventDefault();
                
                // Highlight visual nos stars
                var starSelector = form.querySelector('.dps-star-rating-selector');
                if (starSelector) {
                    starSelector.style.animation = 'shake 0.5s ease';
                    setTimeout(function() {
                        starSelector.style.animation = '';
                    }, 500);
                }
                
                if (ratingHint) {
                    ratingHint.textContent = '⚠️ Por favor, selecione uma nota';
                    ratingHint.style.color = '#ef4444';
                }
                
                return false;
            }
            
            // Feedback de envio
            var submitBtn = form.querySelector('.dps-btn-submit-review');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="dps-btn-icon">⏳</span><span class="dps-btn-text">Enviando...</span>';
            }
        });
    }

    /**
     * Gerencia navegação por tabs de pets na aba Histórico dos Pets
     * Revisão de layout: Janeiro 2026
     */
    function handlePetHistoryTabs() {
        var petTabs = document.querySelectorAll('.dps-pet-tab');
        var petPanels = document.querySelectorAll('.dps-pet-timeline-panel');

        if (petTabs.length === 0 || petPanels.length === 0) {
            return;
        }

        petTabs.forEach(function(tab) {
            tab.addEventListener('click', function() {
                var targetPetId = this.getAttribute('data-pet-id');

                // Remove classe ativa de todas as tabs
                petTabs.forEach(function(t) {
                    t.classList.remove('dps-pet-tab--active');
                    t.setAttribute('aria-selected', 'false');
                });

                // Adiciona classe ativa à tab clicada
                this.classList.add('dps-pet-tab--active');
                this.setAttribute('aria-selected', 'true');

                // Esconde todos os painéis
                petPanels.forEach(function(panel) {
                    panel.classList.add('dps-pet-timeline-panel--hidden');
                    panel.setAttribute('aria-hidden', 'true');
                });

                // Mostra o painel correspondente
                var targetPanel = document.querySelector('.dps-pet-timeline-panel[data-pet-id="' + targetPetId + '"]');
                if (targetPanel) {
                    targetPanel.classList.remove('dps-pet-timeline-panel--hidden');
                    targetPanel.setAttribute('aria-hidden', 'false');

                    // Scroll suave para o painel
                    targetPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            });
        });
    }

    /**
     * Gerencia botão "Repetir Serviço" para abrir WhatsApp
     * Revisão de layout: Janeiro 2026
     */
    function handleRepeatService() {
        // Handler para botões que não são links diretos (fallback)
        var repeatButtons = document.querySelectorAll('button.dps-btn-repeat-service');

        repeatButtons.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var services = this.getAttribute('data-services');
                var petId = this.getAttribute('data-pet-id');
                
                // Tenta parsear serviços
                var servicesText = 'serviços';
                try {
                    var servicesArray = JSON.parse(services);
                    if (Array.isArray(servicesArray) && servicesArray.length > 0) {
                        servicesText = servicesArray.join(', ');
                    }
                } catch (e) {
                    // Mantém texto genérico
                }

                // Monta mensagem
                var message = 'Olá! Gostaria de agendar novamente os serviços: ' + servicesText + ' para meu pet.';
                
                // Obtém número do WhatsApp (usa valor padrão se não encontrado)
                var whatsappNumber = '5515991606299'; // Valor padrão
                if (typeof dpsPortal !== 'undefined' && dpsPortal.whatsappNumber) {
                    whatsappNumber = dpsPortal.whatsappNumber;
                }

                // Abre WhatsApp
                var whatsappUrl = 'https://wa.me/' + whatsappNumber + '?text=' + encodeURIComponent(message);
                window.open(whatsappUrl, '_blank');
            });
        });
    }
})();
