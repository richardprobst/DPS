/**
 * Client Portal JavaScript
 * Gerencia interaÃ§Ãµes do Portal do Cliente DPS
 * 
 * @package DPS Client Portal
 * @version 2.0.0
 */

(function() {
    'use strict';

    // Compatibilidade: mapeia dpsPortalChat para dpsPortal para cÃ³digo legado
    window.dpsPortalChat = window.dpsPortal || {};
    if (window.dpsPortal && !window.dpsPortalChat.nonce) {
        window.dpsPortalChat.nonce = window.dpsPortal.chatNonce;
        window.dpsPortalChat.ajaxUrl = window.dpsPortal.ajaxUrl;
        window.dpsPortalChat.clientId = window.dpsPortal.clientId;
    }

    /**
     * ConfiguraÃ§Ã£o do chat
     */
    var chatConfig = {
        pollInterval: 10000, // 10 segundos
        clientId: 0,
        isOpen: false,
        lastMessageId: 0,
        pollTimer: null
    };

    function runDeferredPortalEnhancement(globalHandlerName) {
        if (typeof window[globalHandlerName] === 'function') {
            window[globalHandlerName]();
        }
    }

    /**
     * Proxies handlers declared in the enhancement block below.
     * This keeps the main portal bootstrap stable even when that block loads later.
     */
    function handleReviewForm() {
        runDeferredPortalEnhancement('dpsPortalHandleReviewForm');
    }

    function handlePetHistoryTabs() {
        runDeferredPortalEnhancement('dpsPortalHandlePetHistoryTabs');
    }

    function handleRepeatService() {
        runDeferredPortalEnhancement('dpsPortalHandleRepeatService');
    }

    function handleExportPdf() {
        runDeferredPortalEnhancement('dpsPortalHandleExportPdf');
    }

    function handleLoadMorePetHistory() {
        runDeferredPortalEnhancement('dpsPortalHandleLoadMorePetHistory');
    }

    function handleTimelinePeriodFilter() {
        runDeferredPortalEnhancement('dpsPortalHandleTimelinePeriodFilter');
    }

    /**
     * Inicializa os handlers do portal
     */
    function init() {
        cleanTokenFromURL();
        normalizePortalCopy();
        handleTabNavigation();
        handleFormValidation(); // Fase 4.2: validaÃ§Ã£o em tempo real
        handleFormSubmits();
        handleFileUploadPreview();
        handleSmoothScroll();
        handleToggleDetails(); // Phase 2: toggle for financial details
        handleCollapsibleSections(); // SeÃ§Ãµes colapsÃ¡veis (ex: Pagamentos Pendentes)
        handlePortalMessages(); // Phase 2: feedback for actions
        handleLoyalty();
        handleGameProgress();
        initChatWidget();
        handleQuickActions(); // Fase 3: Quick Actions na aba InÃ­cio
        handleReviewForm(); // Fase 5: FormulÃ¡rio de avaliaÃ§Ã£o interna
        handlePetHistoryTabs(); // RevisÃ£o Jan/2026: NavegaÃ§Ã£o por pet na aba HistÃ³rico
        handleRepeatService(); // RevisÃ£o Jan/2026: BotÃ£o repetir serviÃ§o via WhatsApp
        handleExportPdf(); // Funcionalidade 3: Export PDF
        handleLoadMorePetHistory(); // Load more pet history items
        handleTimelinePeriodFilter(); // Fase 4.4: Filtro de perÃ­odo no histÃ³rico
    }

    /**
     * Corrige labels corrompidas do shell autenticado do portal em runtime.
     * O arquivo PHP legado ainda tem trechos com encoding inconsistente.
     */
    function normalizePortalCopy() {
        var portalRoot = document.querySelector('.dps-client-portal');
        var title = document.querySelector('.dps-portal-title');
        var activeBreadcrumb = document.querySelector('[data-breadcrumb-active]');
        var breadcrumbSeparator = document.querySelector('.dps-portal-breadcrumb__separator');
        var reviewIcon = document.querySelector('.dps-portal-review-icon');
        var petSummaryTitle = document.querySelector('.dps-portal-pets-summary .dps-section-header h2');
        var petSummaryButton = document.querySelector('.dps-portal-pets-summary .dps-link-button[data-tab=\"historico-pets\"]');
        var tabLabels = {
            inicio: 'Inicio',
            fidelidade: 'Fidelidade',
            avaliacoes: 'Avaliacoes',
            mensagens: 'Mensagens',
            agendamentos: 'Agendamentos',
            pagamentos: 'Pagamentos',
            'historico-pets': 'Historico dos Pets',
            galeria: 'Galeria',
            dados: 'Meus Dados'
        };

        if (!portalRoot) {
            return;
        }

        if (title) {
            title.textContent = normalizeBrokenPortalText(title.textContent);
        }

        if (activeBreadcrumb) {
            activeBreadcrumb.textContent = 'Inicio';
        }

        if (breadcrumbSeparator) {
            breadcrumbSeparator.textContent = '>';
        }

        if (reviewIcon) {
            reviewIcon.textContent = '*';
        }

        Object.keys(tabLabels).forEach(function(tabId) {
            var button = document.querySelector('.dps-portal-tabs__link[data-tab=\"' + tabId + '\"]');
            if (!button) {
                return;
            }

            var icon = button.querySelector('.dps-portal-tabs__icon');
            var text = button.querySelector('.dps-portal-tabs__text');

            if (icon) {
                icon.textContent = '';
                icon.setAttribute('aria-hidden', 'true');
            }

            if (text) {
                text.textContent = tabLabels[tabId];
            }
        });

        if (petSummaryTitle) {
            petSummaryTitle.textContent = 'Meus Pets';
        }

        if (petSummaryButton) {
            petSummaryButton.textContent = 'Ver Historico Completo';
        }
    }

    function normalizeBrokenPortalText(text) {
        var safeText = String(text || '').trim();
        var replacements = [
            ['OlÃ¡', 'Ola'],
            ['OlÃƒÂ¡', 'Ola'],
            ['InÃ­cio', 'Inicio'],
            ['InÃƒÂ­cio', 'Inicio'],
            ['AvaliaÃ§Ãµes', 'Avaliacoes'],
            ['AvaliaÃƒÂ§ÃƒÂµes', 'Avaliacoes'],
            ['HistÃ³rico dos Pets', 'Historico dos Pets'],
            ['HistÃƒÂ³rico dos Pets', 'Historico dos Pets'],
            ['Ver HistÃ³rico Completo', 'Ver Historico Completo'],
            ['Ver HistÃƒÂ³rico Completo', 'Ver Historico Completo'],
            ['Ã¢â‚¬Âº', '>'],
            ['Ã°Å¸â€˜â€¹', ''],
            ['ðŸ‘‹', ''],
            ['Ã°Å¸ÂÂ¾', '']
        ];

        replacements.forEach(function(entry) {
            safeText = safeText.split(entry[0]).join(entry[1]);
        });

        safeText = safeText.replace(/\s{2,}/g, ' ').trim();

        return safeText;
    }

    /**
     * Gerencia acoes rapidas (Quick Actions) na aba inicial.
     */
    function handleQuickActions() {
        var chatButtons = document.querySelectorAll('[data-action="open-chat"]');
        chatButtons.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var chatToggle = document.querySelector('.dps-chat-toggle');
                if (chatToggle) {
                    chatToggle.click();
                }
            });
        });

        var tabButtons = document.querySelectorAll('[data-portal-nav-target], .dps-quick-action[data-tab], .dps-link-button[data-tab], .dps-pet-card__action-btn[data-tab], .dps-overview-card[data-tab]');
        var validTabs = Array.prototype.slice.call(document.querySelectorAll('.dps-portal-tabs__link[data-tab]')).map(function(tab) {
            return tab.getAttribute('data-tab');
        }).filter(function(tabId, index, allTabs) {
            return !!tabId && allTabs.indexOf(tabId) === index;
        });

        if (!validTabs.length) {
            return;
        }

        tabButtons.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var targetTab = this.getAttribute('data-portal-nav-target') || this.getAttribute('data-tab');

                if (!targetTab || validTabs.indexOf(targetTab) === -1) {
                    return;
                }

                var tabLink = document.querySelector('.dps-portal-tabs__link[data-tab="' + targetTab + '"]');
                if (tabLink) {
                    tabLink.click();
                    var tabContent = document.querySelector('.dps-portal-tab-content');
                    if (tabContent) {
                        tabContent.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }
            });

            if (btn.getAttribute('role') === 'button') {
                btn.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        this.click();
                    }
                });
            }
        });
    }

    /**
     * Remove token de autenticaÃ§Ã£o da URL por seguranÃ§a
     * Chamado apÃ³s autenticaÃ§Ã£o bem-sucedida para limpar o dps_token da URL
     */
    function cleanTokenFromURL() {
        // Verifica se hÃ¡ dps_token na URL
        if (window.location.search.indexOf('dps_token=') === -1) {
            return;
        }

        // Remove o parÃ¢metro dps_token da URL usando History API
        if (window.history && window.history.replaceState) {
            try {
                // MÃ©todo moderno com URL API (navegadores atuais)
                if (typeof URL !== 'undefined') {
                    var url = new URL(window.location.href);
                    url.searchParams.delete('dps_token');
                    window.history.replaceState({}, document.title, url.toString());
                } else {
                    // Fallback para navegadores antigos (IE)
                    var currentUrl = window.location.href;
                    var cleanUrl = currentUrl.replace(/([?&])dps_token=[^&]+(&|$)/, function(match, prefix, suffix) {
                        // Se era o Ãºnico parÃ¢metro (?dps_token=...), remove o ?
                        if (prefix === '?' && suffix === '') {
                            return '';
                        }
                        // Se era o primeiro parÃ¢metro (?dps_token=...&), mantÃ©m o ?
                        if (prefix === '?' && suffix === '&') {
                            return '?';
                        }
                        // Se era um parÃ¢metro intermediÃ¡rio (&dps_token=...&), remove completamente
                        if (prefix === '&' && suffix === '&') {
                            return '&';
                        }
                        // Se era o Ãºltimo parÃ¢metro (&dps_token=...), remove o &
                        return '';
                    });
                    
                    // Remove & duplicados e & no final da query string
                    cleanUrl = cleanUrl.replace(/&&+/g, '&').replace(/[?&]$/, '');
                    
                    window.history.replaceState({}, document.title, cleanUrl);
                }
            } catch (e) {
                // Em caso de erro, apenas loga sem quebrar a pÃ¡gina
                // O token ficarÃ¡ visÃ­vel na URL mas a autenticaÃ§Ã£o jÃ¡ foi feita
                if (console && console.warn) {
                    console.warn('NÃ£o foi possÃ­vel limpar token da URL:', e);
                }
            }
        }
    }

    /**
     * Gerencia navegaÃ§Ã£o por tabs
     */
    function handleTabNavigation() {
        var LOADING_INDICATOR_DURATION = 220;
        var SCROLL_THRESHOLD = 4;
        var tabs = Array.prototype.slice.call(document.querySelectorAll('.dps-portal-tabs__link'));
        var panels = document.querySelectorAll('.dps-portal-tab-panel');
        var tabNav = document.querySelector('.dps-portal-tabs');
        var tabWrapper = document.querySelector('.dps-portal-tabs-wrapper');
        var tabContent = document.querySelector('.dps-portal-tab-content');
        var breadcrumbActive = document.querySelector('[data-breadcrumb-active]');
        var loadingEl = tabNav ? tabNav.querySelector('.dps-portal-tabs__loading') : null;
        var loadingTimer = null;
        
        if (!tabs.length || !panels.length) return;

        function updateScrollHints() {
            if (!tabNav || !tabWrapper) return;
            var scrollLeft = tabNav.scrollLeft;
            var maxScroll = tabNav.scrollWidth - tabNav.clientWidth;
            tabWrapper.classList.toggle('has-scroll-left', scrollLeft > SCROLL_THRESHOLD);
            tabWrapper.classList.toggle('has-scroll-right', maxScroll > SCROLL_THRESHOLD && scrollLeft < maxScroll - SCROLL_THRESHOLD);
        }

        if (tabNav) {
            tabNav.addEventListener('scroll', updateScrollHints, { passive: true });
            window.addEventListener('resize', updateScrollHints, { passive: true });
            updateScrollHints();
        }

        function scrollTabIntoView(tabElement) {
            if (!tabNav || !tabElement) return;
            var navRect = tabNav.getBoundingClientRect();
            var tabRect = tabElement.getBoundingClientRect();
            if (tabRect.left < navRect.left || tabRect.right > navRect.right) {
                tabElement.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
            }
        }

        function updateBreadcrumb(tabElement) {
            if (!breadcrumbActive || !tabElement) return;
            var labelEl = tabElement.querySelector('.dps-portal-tabs__text');
            if (labelEl) {
                breadcrumbActive.textContent = labelEl.textContent;
            }
        }

        function setLoading(isLoading) {
            if (!tabNav || !tabContent) return;
            tabNav.classList.toggle('is-loading', isLoading);
            tabContent.classList.toggle('is-loading', isLoading);
            if (loadingEl) {
                loadingEl.textContent = isLoading ? 'Carregando seÃ§Ã£o...' : '';
            }
        }

        function activateTab(tabId, options) {
            var opts = options || {};
            var targetTab = document.querySelector('.dps-portal-tabs__link[data-tab="' + tabId + '"]');
            if (!targetTab || targetTab.getAttribute('aria-disabled') === 'true') {
                return false;
            }

            if (targetTab.classList.contains('is-active')) {
                updateBreadcrumb(targetTab);
                return true;
            }

            if (!opts.silent) {
                setLoading(true);
            }

            tabs.forEach(function(t) {
                t.classList.remove('is-active');
                t.setAttribute('aria-selected', 'false');
                t.setAttribute('tabindex', '-1');
            });

            panels.forEach(function(p) {
                p.classList.remove('is-active');
                p.setAttribute('aria-hidden', 'true');
            });

            targetTab.classList.add('is-active');
            targetTab.setAttribute('aria-selected', 'true');
            targetTab.setAttribute('tabindex', '0');

            var targetPanel = document.getElementById('panel-' + tabId);
            if (targetPanel) {
                targetPanel.classList.add('is-active');
                targetPanel.setAttribute('aria-hidden', 'false');
                if (opts.focusPanel) {
                    targetPanel.focus();
                }
            }

            updateBreadcrumb(targetTab);
            scrollTabIntoView(targetTab);

            if (!opts.skipHash && history.pushState) {
                history.pushState(null, null, '#tab-' + tabId);
            }

            if (loadingTimer) {
                clearTimeout(loadingTimer);
            }
            if (!opts.silent) {
                loadingTimer = setTimeout(function() {
                    setLoading(false);
                    updateScrollHints();
                }, LOADING_INDICATOR_DURATION);
            } else {
                setLoading(false);
                updateScrollHints();
            }

            return true;
        }

        tabs.forEach(function(tab) {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                var targetTab = this.getAttribute('data-tab');
                if (targetTab) {
                    activateTab(targetTab);
                }
            });

            tab.addEventListener('keydown', function(e) {
                var enabledTabs = [];
                tabs.forEach(function(item) {
                    if (item.getAttribute('aria-disabled') !== 'true') {
                        enabledTabs.push(item);
                    }
                });
                var currentIndex = enabledTabs.indexOf(this);
                var nextTab = null;

                if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    nextTab = enabledTabs[(currentIndex + 1) % enabledTabs.length];
                } else if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    nextTab = enabledTabs[(currentIndex - 1 + enabledTabs.length) % enabledTabs.length];
                } else if (e.key === 'Home') {
                    e.preventDefault();
                    nextTab = enabledTabs[0];
                } else if (e.key === 'End') {
                    e.preventDefault();
                    nextTab = enabledTabs[enabledTabs.length - 1];
                } else if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    var selectedTab = this.getAttribute('data-tab');
                    if (selectedTab) {
                        activateTab(selectedTab, { focusPanel: true });
                    }
                }

                if (nextTab) {
                    var nextTabId = nextTab.getAttribute('data-tab');
                    if (nextTabId) {
                        activateTab(nextTabId);
                        nextTab.focus();
                    }
                }
            });
        });

        function activateFromHash(silent) {
            var activeTab = window.location.hash.replace('#tab-', '') || 'inicio';
            if (activateTab(activeTab, { silent: !!silent, skipHash: true })) {
                return;
            }
            var firstEnabledTab = null;
            tabs.some(function(item) {
                if (item.getAttribute('aria-disabled') !== 'true') {
                    firstEnabledTab = item;
                    return true;
                }
                return false;
            });
            if (firstEnabledTab) {
                activateTab(firstEnabledTab.getAttribute('data-tab'), { silent: !!silent, skipHash: true });
            }
        }

        window.addEventListener('hashchange', function() {
            activateFromHash(true);
        });

        activateFromHash(true);
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
        
        // ObtÃ©m client_id do data attribute
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

        // Tecla Escape para fechar o chat
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && chatConfig.isOpen) {
                closeChat();
            }
        });
        
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
            chatWindow.setAttribute('aria-hidden', 'false');
            toggle.classList.add('is-open');
            toggle.setAttribute('aria-expanded', 'true');
            toggle.setAttribute('aria-label', window.dpsPortalChat && window.dpsPortalChat.i18n ? window.dpsPortalChat.i18n.closeChat || 'Fechar chat' : 'Fechar chat');
            startPolling();
            scrollToBottom();
            
            // Marca mensagens como lidas
            markMessagesAsRead();
        } else {
            chatWindow.classList.remove('is-open');
            chatWindow.setAttribute('aria-hidden', 'true');
            toggle.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', window.dpsPortalChat && window.dpsPortalChat.i18n ? window.dpsPortalChat.i18n.openChat || 'Abrir chat' : 'Abrir chat');
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
        chatWindow.setAttribute('aria-hidden', 'true');
        toggle.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', window.dpsPortalChat && window.dpsPortalChat.i18n ? window.dpsPortalChat.i18n.openChat || 'Abrir chat' : 'Abrir chat');
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
        xhr.timeout = 15000;
        
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

        xhr.onerror = function() {
            console.error('Erro de rede ao carregar mensagens');
        };

        xhr.ontimeout = function() {
            console.error('Timeout ao carregar mensagens');
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
                '<div class="dps-chat-empty__icon">ðŸ’¬</div>' +
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
    var isSending = false;

    function sendChatMessage(message) {
        if (!chatConfig.clientId || !window.dpsPortalChat) return;
        if (isSending) return;

        isSending = true;
        var sendBtn = document.querySelector('.dps-chat-input__send');
        if (sendBtn) sendBtn.disabled = true;
        
        // Adiciona mensagem otimisticamente
        addOptimisticMessage(message);
        
        var xhr = new XMLHttpRequest();
        xhr.open('POST', window.dpsPortalChat.ajaxUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.timeout = 15000;
        
        xhr.onload = function() {
            isSending = false;
            if (sendBtn) sendBtn.disabled = false;
            
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        // Recarrega mensagens para sincronizar
                        loadChatMessages();
                    } else {
                        var errMsg = response.data && response.data.message
                            ? response.data.message
                            : 'Erro ao enviar mensagem. Tente novamente.';
                        if (window.DPSToast) {
                            window.DPSToast.error(errMsg);
                        }
                    }
                } catch (e) {
                    console.error('Erro ao enviar mensagem:', e);
                }
            } else {
                if (window.DPSToast) {
                    window.DPSToast.error('Erro de conexÃ£o. Tente novamente.');
                }
            }
        };

        xhr.onerror = function() {
            isSending = false;
            if (sendBtn) sendBtn.disabled = false;
            if (window.DPSToast) {
                window.DPSToast.error('Falha na conexÃ£o. Verifique sua internet e tente novamente.');
            }
        };

        xhr.ontimeout = function() {
            isSending = false;
            if (sendBtn) sendBtn.disabled = false;
            if (window.DPSToast) {
                window.DPSToast.error('A conexÃ£o demorou muito. Tente novamente.');
            }
        };
        
        var params = 'action=dps_chat_send_message';
        params += '&client_id=' + chatConfig.clientId;
        params += '&message=' + encodeURIComponent(message);
        params += '&nonce=' + window.dpsPortalChat.nonce;
        
        xhr.send(params);
    }

    /**
     * Adiciona mensagem de forma otimista (antes da confirmaÃ§Ã£o do servidor)
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
     * Atualiza badge de mensagens nÃ£o lidas (chat + tab nav)
     */
    function updateUnreadBadge(count) {
        // Badge do chat flutuante
        var badge = document.querySelector('.dps-chat-badge');
        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'flex';
            } else {
                badge.textContent = '';
                badge.style.display = 'none';
            }
        }

        // Badge na aba de navegaÃ§Ã£o do portal
        var tabBadge = document.querySelector('#dps-portal-tab-mensagens .dps-portal-tabs__badge');
        if (tabBadge) {
            if (count > 0) {
                tabBadge.textContent = count > 99 ? '99+' : count;
                tabBadge.style.display = '';
            } else {
                tabBadge.textContent = '';
                tabBadge.style.display = 'none';
            }
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
     * Fase 4.2: ValidaÃ§Ã£o em tempo real nos formulÃ¡rios do portal.
     * Valida campos on blur/input e mostra mensagens inline acessÃ­veis.
     */
    function handleFormValidation() {
        var forms = document.querySelectorAll('.dps-portal-form');
        if (!forms.length) return;

        // Regras de validaÃ§Ã£o por name do campo
        var rules = {
            'client_phone': {
                pattern: /^\(?\d{2}\)?\s?\d{4,5}-?\d{4}$/,
                message: 'Informe um telefone vÃ¡lido. Ex: (11) 99999-9999'
            },
            'client_email': {
                pattern: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
                message: 'Informe um e-mail vÃ¡lido. Ex: nome@email.com'
            },
            'client_zip': {
                pattern: /^\d{5}-?\d{3}$/,
                message: 'Informe um CEP vÃ¡lido. Ex: 01234-567'
            },
            'client_state': {
                pattern: /^[A-Za-z]{2}$/,
                message: 'Use a sigla do estado com 2 letras. Ex: SP'
            },
            'pet_name': {
                required: true,
                message: 'O nome do pet Ã© obrigatÃ³rio.'
            },
            'pet_weight': {
                pattern: /^\d+([.,]\d{1,2})?$/,
                message: 'Informe um peso vÃ¡lido. Ex: 8.5'
            },
            'pet_birth': {
                maxDate: new Date().toISOString().split('T')[0],
                message: 'A data de nascimento nÃ£o pode ser no futuro.'
            }
        };

        /**
         * Valida um campo individual.
         * @param {HTMLElement} field - O campo a validar.
         * @returns {boolean} true se vÃ¡lido.
         */
        function validateField(field) {
            var name = field.getAttribute('name');
            var value = field.value.trim();
            var rule = rules[name];
            var errorEl = field.parentNode.querySelector('.dps-field-error');

            // Sem regra customizada â€” usa validaÃ§Ã£o HTML5 nativa
            if (!rule) {
                if (field.hasAttribute('required') && !value) {
                    setInvalid(field, errorEl, 'Este campo Ã© obrigatÃ³rio.');
                    return false;
                }
                setValid(field, errorEl);
                return true;
            }

            // Campo vazio: sÃ³ erro se obrigatÃ³rio
            if (!value) {
                if (rule.required || field.hasAttribute('required')) {
                    setInvalid(field, errorEl, rule.message);
                    return false;
                }
                clearState(field, errorEl);
                return true;
            }

            // ValidaÃ§Ã£o de pattern
            if (rule.pattern && !rule.pattern.test(value)) {
                setInvalid(field, errorEl, rule.message);
                return false;
            }

            // ValidaÃ§Ã£o de data mÃ¡xima
            if (rule.maxDate && value > rule.maxDate) {
                setInvalid(field, errorEl, rule.message);
                return false;
            }

            setValid(field, errorEl);
            return true;
        }

        function setInvalid(field, errorEl, message) {
            field.classList.add('is-invalid');
            field.classList.remove('is-valid');
            field.setAttribute('aria-invalid', 'true');
            if (errorEl) {
                errorEl.textContent = message;
                errorEl.style.display = 'block';
            }
        }

        function setValid(field, errorEl) {
            field.classList.remove('is-invalid');
            field.classList.add('is-valid');
            field.setAttribute('aria-invalid', 'false');
            if (errorEl) {
                errorEl.textContent = '';
                errorEl.style.display = 'none';
            }
        }

        function clearState(field, errorEl) {
            field.classList.remove('is-invalid', 'is-valid');
            field.removeAttribute('aria-invalid');
            if (errorEl) {
                errorEl.textContent = '';
                errorEl.style.display = 'none';
            }
        }

        forms.forEach(function(form) {
            var fields = form.querySelectorAll('.dps-form-control');

            fields.forEach(function(field) {
                // Validar ao sair do campo (blur)
                field.addEventListener('blur', function() {
                    // SÃ³ valida se o campo jÃ¡ foi tocado (tem valor ou perdeu foco)
                    if (field.value.trim() || field.hasAttribute('required')) {
                        validateField(field);
                    }
                });

                // Limpar erro enquanto digita (feedback imediato)
                field.addEventListener('input', function() {
                    if (field.classList.contains('is-invalid')) {
                        validateField(field);
                    }
                });
            });

            // Validar todos os campos antes do submit
            form.addEventListener('submit', function(e) {
                var allValid = true;
                var firstInvalid = null;

                fields.forEach(function(field) {
                    if (!validateField(field)) {
                        allValid = false;
                        if (!firstInvalid) firstInvalid = field;
                    }
                });

                if (!allValid) {
                    e.preventDefault();
                    if (firstInvalid) {
                        firstInvalid.focus();
                        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            });
        });
    }

    /**
     * Adiciona feedback visual durante submit de formulÃ¡rios
     */
    function handleFormSubmits() {
        var forms = document.querySelectorAll('.dps-portal-form');
        
        forms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                // Se a validaÃ§Ã£o customizada jÃ¡ preveniu o submit, nÃ£o prosseguir
                if (e.defaultPrevented) return;

                var submitBtn = form.querySelector('.dps-btn-submit, .dps-submit-btn');
                
                if (submitBtn && !submitBtn.disabled) {
                    // Salva texto original
                    var originalText = submitBtn.textContent;
                    
                    // Desabilita botÃ£o e mostra "Salvando..."
                    submitBtn.disabled = true;
                    submitBtn.classList.add('is-loading');
                    submitBtn.textContent = 'Salvando...';
                    
                    // Se houver erro de validaÃ§Ã£o HTML5, reabilita o botÃ£o
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
     * InteraÃ§Ãµes da aba de fidelidade.
     */
    function handleLoyalty() {
        var section = document.querySelector('.dps-portal-loyalty');
        if (!section || !window.dpsPortal || !window.dpsPortal.loyalty) return;

        var loyaltyConfig = window.dpsPortal.loyalty;
        var ajaxUrl = window.dpsPortal.ajaxUrl;

        function copyToClipboard(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function() {
                    if (window.DPSToast) {
                        window.DPSToast.success('Link copiado!', 2500);
                    }
                }).catch(function() {});
                return true;
            }
            // Fallback for non-HTTPS contexts
            var textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                if (window.DPSToast) {
                    window.DPSToast.success('Link copiado!', 2500);
                }
            } catch (e) {}
            document.body.removeChild(textarea);
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
                if (window.DPSToast) {
                    window.DPSToast.error(loyaltyConfig.i18n.redeemError || 'Erro ao carregar. Tente novamente.', 3000);
                }
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
            var originalBtnText = submitBtn ? submitBtn.textContent : '';

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

                        var maxAttr = parseInt(redemptionForm.getAttribute('data-max-cents'), 10) || 0;
                        var rate = parseInt(redemptionForm.getAttribute('data-rate'), 10) || 1;
                        var maxByCap = maxAttr > 0 ? Math.floor((maxAttr / 100) * rate) : res.data.points;
                        var newMax = Math.min(res.data.points, maxByCap);
                        input.setAttribute('max', newMax);
                        // Clamp input value to new max (prevent HTML5 validation errors)
                        var minPoints = parseInt(redemptionForm.getAttribute('data-min-points'), 10) || 1;
                        input.value = Math.min(Math.max(minPoints, newMax), newMax);

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
                    submitBtn.textContent = originalBtnText;
                });
            });
        }
    }

    /**
     * Consome o resumo sincronizado do Space Groomers no portal.
     */
    function handleGameProgress() {
        var section = document.querySelector('.dps-portal-game-summary');
        if (!section || !window.dpsPortal || !window.dpsPortal.game || !window.dpsPortal.game.enabled) return;

        var gameConfig = window.dpsPortal.game;
        var statusEl = section.querySelector('.dps-portal-game-summary__status');

        function field(name) {
            return section.querySelector('[data-game-field="' + name + '"]');
        }

        function setState(state) {
            section.setAttribute('data-game-summary-state', state);
        }

        function setText(name, value) {
            var el = field(name);
            if (el) {
                el.textContent = value;
            }
        }

        function formatNumber(value) {
            return (parseInt(value, 10) || 0).toLocaleString('pt-BR');
        }

        function getLastRunLabel(lastRun) {
            if (!lastRun) {
                return gameConfig.i18n.empty || 'Jogue uma run para comecar seu historico sincronizado.';
            }

            var resultLabel = lastRun.result === 'victory' ? 'Vitoria' : 'Nova tentativa';
            return resultLabel + ': ' + formatNumber(lastRun.score) + ' pts, onda ' + formatNumber(lastRun.waveReached);
        }

        function renderSummary(summary) {
            if (!summary) {
                setState('empty');
                if (statusEl) {
                    statusEl.textContent = gameConfig.i18n.empty || 'Jogue uma run para comecar seu historico sincronizado.';
                }
                return;
            }

            var mission = summary.mission || {};
            var badges = Array.isArray(summary.badges) ? summary.badges : [];
            var streak = summary.streak || {};
            var records = summary.records || {};
            var missionProgress = mission.completed
                ? (gameConfig.i18n.missionDone || 'Missao concluida hoje.')
                : ((mission.progress || 0) + '/' + (mission.target || 0) + ' - faltam ' + (mission.remaining || 0));
            var missionStatus = mission.completed
                ? (gameConfig.i18n.missionDone || 'Missao concluida hoje.')
                : (gameConfig.i18n.missionPending || 'Falta pouco para concluir a meta.');
            var badgeNames = badges.length ? badges.map(function(item) { return item.name; }).join(' - ') : 'Sem badges novas';

            setState('ready');
            if (statusEl) {
                statusEl.textContent = missionStatus;
            }

            setText('mission-title', mission.title || 'Sem missao ativa');
            setText('mission-progress', missionProgress);
            setText('streak', formatNumber(streak.current || 0) + ' dias');
            setText('streak-note', 'Melhor: ' + formatNumber(streak.best || 0) + ' dias');
            setText('highscore', formatNumber(summary.highscore || 0));
            setText('record-note', 'Combo ' + formatNumber(records.bestCombo || 0) + ' - onda ' + formatNumber(records.bestWave || 1));
            setText('badges-count', formatNumber(summary.badgesCount || 0));
            setText('badges-note', badgeNames);
            setText('last-run', getLastRunLabel(summary.lastRun));
        }

        function renderError() {
            setState('error');
            if (statusEl) {
                statusEl.textContent = gameConfig.i18n.error || 'Nao foi possivel carregar o progresso do jogo agora.';
            }
        }

        function fetchSummary() {
            setState('loading');
            if (statusEl) {
                statusEl.textContent = gameConfig.i18n.loading || 'Carregando progresso do jogo...';
            }

            fetch(gameConfig.endpoints.progress, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-DPS-Game-Nonce': gameConfig.nonce
                }
            }).then(function(res) {
                if (!res.ok) {
                    throw new Error('game_progress_fetch_failed');
                }
                return res.json();
            }).then(function(res) {
                if (res && res.summary) {
                    renderSummary(res.summary);
                } else {
                    renderSummary(null);
                }
            }).catch(function() {
                renderError();
            });
        }

        window.addEventListener('dps-space-groomers-progress', function(event) {
            if (event && event.detail && event.detail.summary) {
                renderSummary(event.detail.summary);
                return;
            }
            fetchSummary();
        });

        fetchSummary();
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
     * Implementa scroll suave para links de Ã¢ncora
     * (Alternativa caso scroll-behavior: smooth no CSS nÃ£o funcione em todos os navegadores)
     */
    function handleSmoothScroll() {
        var navLinks = document.querySelectorAll('.dps-portal-nav__link');
        
        navLinks.forEach(function(link) {
            link.addEventListener('click', function(e) {
                var href = this.getAttribute('href');
                
                // Verifica se Ã© uma Ã¢ncora
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
     * Gerencia seÃ§Ãµes colapsÃ¡veis (ex: Pagamentos Pendentes)
     */
    function handleCollapsibleSections() {
        var collapsibleHeaders = document.querySelectorAll('.dps-collapsible__header');
        
        collapsibleHeaders.forEach(function(header) {
            header.addEventListener('click', function(e) {
                e.preventDefault();
                var section = this.closest('.dps-collapsible');
                if (section) {
                    var isCollapsed = section.classList.toggle('is-collapsed');
                    this.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
                }
            });
        });
    }

    /**
     * Exibe toasts baseados em mensagens da URL (apÃ³s aÃ§Ãµes do cliente)
     * Phase 2: Feedback de aÃ§Ãµes
     */
    function handlePortalMessages() {
        var urlParams = new URLSearchParams(window.location.search);
        var message = urlParams.get('portal_msg');
        
        if (!message) {
            return;
        }
        
        // Remove o parÃ¢metro da URL
        if (window.history && window.history.replaceState) {
            var cleanUrl = window.location.pathname + window.location.hash;
            window.history.replaceState({}, document.title, cleanUrl);
        }
        
        // Mapeia mensagens para toasts
        var messages = {
            'updated': {
                type: 'success',
                title: 'Dados Salvos!',
                message: 'Seus dados pessoais foram atualizados com sucesso.'
            },
            'pet_updated': {
                type: 'success',
                title: 'Pet Atualizado!',
                message: 'As informaÃ§Ãµes do pet foram salvas com sucesso.'
            },
            'preferences_updated': {
                type: 'success',
                title: 'PreferÃªncias Salvas',
                message: 'Suas preferÃªncias foram atualizadas com sucesso.'
            },
            'pet_preferences_updated': {
                type: 'success',
                title: 'PreferÃªncias do Pet Salvas',
                message: 'As preferÃªncias de produtos do pet foram atualizadas.'
            },
            'upload_error': {
                type: 'error',
                title: 'Erro no Upload',
                message: 'NÃ£o foi possÃ­vel enviar a foto. Verifique se o arquivo Ã© uma imagem vÃ¡lida (JPG, PNG, GIF ou WebP) com atÃ© 5 MB.'
            },
            'invalid_file_type': {
                type: 'error',
                title: 'Formato NÃ£o Aceito',
                message: 'O arquivo enviado nÃ£o Ã© uma imagem vÃ¡lida. Use JPG, PNG, GIF ou WebP.'
            },
            'file_too_large': {
                type: 'error',
                title: 'Foto Grande Demais',
                message: 'A foto deve ter no mÃ¡ximo 5 MB. Reduza o tamanho da imagem e tente novamente.'
            },
            'session_expired': {
                type: 'error',
                title: 'SessÃ£o Expirada',
                message: 'Sua sessÃ£o expirou por seguranÃ§a. Solicite um novo link de acesso para continuar.'
            },
            'message_sent': {
                type: 'success',
                title: 'Mensagem Enviada!',
                message: 'Sua mensagem foi enviada para a equipe. Responderemos o mais breve possÃ­vel.'
            },
            'message_error': {
                type: 'error',
                title: 'Erro ao Enviar',
                message: 'NÃ£o foi possÃ­vel enviar sua mensagem. Verifique o conteÃºdo e tente novamente.'
            },
            'review_submitted': {
                type: 'success',
                title: 'AvaliaÃ§Ã£o Enviada! ðŸŽ‰',
                message: 'Obrigado pela sua avaliaÃ§Ã£o! Sua opiniÃ£o Ã© muito importante para nÃ³s.'
            },
            'review_already': {
                type: 'info',
                title: 'AvaliaÃ§Ã£o JÃ¡ Registrada',
                message: 'VocÃª jÃ¡ enviou uma avaliaÃ§Ã£o anteriormente. Obrigado pelo feedback!'
            },
            'review_invalid': {
                type: 'error',
                title: 'AvaliaÃ§Ã£o Incompleta',
                message: 'Por favor, selecione uma nota de 1 a 5 estrelas antes de enviar.'
            },
            'review_error': {
                type: 'error',
                title: 'Erro na AvaliaÃ§Ã£o',
                message: 'NÃ£o foi possÃ­vel registrar sua avaliaÃ§Ã£o. Tente novamente em alguns instantes.'
            },
            'error': {
                type: 'error',
                title: 'Algo Deu Errado',
                message: 'NÃ£o foi possÃ­vel processar sua solicitaÃ§Ã£o. Tente novamente ou entre em contato pelo chat.'
            },
            'unauthorized': {
                type: 'error',
                title: 'Acesso NÃ£o Autorizado',
                message: 'Seu link de acesso pode ter expirado. Solicite um novo link para continuar.'
            }
        };
        
        var toastData = messages[message] || messages.error;
        
        // Aguarda DPSToast estar disponÃ­vel
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
   Sistema global de notificaÃ§Ãµes
   ======================================== */

/**
 * DPS Toast - Sistema de notificaÃ§Ãµes toast
 * 
 * Uso:
 * DPSToast.success('Dados salvos com sucesso!');
 * DPSToast.error('Erro ao processar solicitaÃ§Ã£o');
 * DPSToast.warning('AtenÃ§Ã£o: link expira em 5 minutos');
 * DPSToast.info('Nova mensagem recebida');
 * DPSToast.show('TÃ­tulo', 'Mensagem', 'success', 5000);
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
     * ObtÃ©m Ã­cone para tipo de toast
     */
    function getIcon(type) {
        var icons = {
            'success': 'âœ“',
            'error': 'âœ•',
            'warning': 'âš ',
            'info': 'â„¹'
        };
        return icons[type] || icons.info;
    }
    
    /**
     * Mostra um toast
     * 
     * @param {string} title - TÃ­tulo do toast
     * @param {string} message - Mensagem do toast
     * @param {string} type - Tipo: success, error, warning, info
     * @param {number} duration - DuraÃ§Ã£o em ms (0 = nÃ£o fecha automaticamente)
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
        closeBtn.setAttribute('aria-label', 'Fechar notificaÃ§Ã£o');
        closeBtn.textContent = 'Ã—';
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
        return show('AtenÃ§Ã£o', message, 'warning', duration);
    }
    
    function info(message, duration) {
        return show('', message, 'info', duration);
    }
    
    // API pÃºblica
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
 * Substitui alertas padrÃ£o de mensagens por toasts
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
    
    // Converte alerts existentes quando pÃ¡gina carregar
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', convertAlertsToToasts);
    } else {
        convertAlertsToToasts();
    }
    
    // Observer para alerts adicionados dinamicamente
    var observerStarted = false;
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
    
    function startAlertObserver() {
        if (observerStarted || !document.body) {
            return;
        }

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        observerStarted = true;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startAlertObserver, { once: true });
    } else {
        startAlertObserver();
    }
})();

/* ========================================
   SKELETON LOADERS (Fase 2.7)
   Exibe placeholders durante carregamento
   ======================================== */

/**
 * DPS Skeleton - Sistema de skeleton loaders
 * Melhora a percepÃ§Ã£o de velocidade mostrando placeholders
 */
window.DPSSkeleton = (function() {
    'use strict';
    
    /**
     * Cria skeleton para histÃ³rico
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
     * Cria skeleton genÃ©rico com mÃºltiplas linhas de texto
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
        
        // Remove skeletons apÃ³s animaÃ§Ã£o
        setTimeout(function() {
            var skeletons = container.querySelectorAll('.dps-skeleton, .dps-portal-skeleton-history, .dps-portal-skeleton-gallery, .dps-skeleton-container');
            skeletons.forEach(function(el) {
                if (el.parentNode) {
                    el.parentNode.removeChild(el);
                }
            });
        }, 300);
    }
    
    // API pÃºblica
    return {
        show: show,
        hide: hide,
        createHistorySkeleton: createHistorySkeleton,
        createGallerySkeleton: createGallerySkeleton,
        createTextSkeleton: createTextSkeleton
    };
})();

/**
 * Auto-aplica skeletons em tab panels durante navegaÃ§Ã£o
 */
(function() {
    'use strict';
    
    // Aguarda navegaÃ§Ã£o entre tabs
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
            
            // Remove skeleton apÃ³s delay (simula carregamento)
            setTimeout(function() {
                DPSSkeleton.hide(panel);
            }, 500);
        }
    });

    /**
     * Fase 4: Handlers para pedidos de agendamento
     */
    
    // Handler para botÃµes de reagendamento
    document.addEventListener('click', function(e) {
        if (e.target.closest('.dps-btn-reschedule')) {
            e.preventDefault();
            var btn = e.target.closest('.dps-btn-reschedule');
            var appointmentId = btn.dataset.appointmentId;
            showRescheduleModal(appointmentId);
        }
    });

    // Handler para botÃµes de cancelamento
    document.addEventListener('click', function(e) {
        if (e.target.closest('.dps-btn-cancel')) {
            e.preventDefault();
            var btn = e.target.closest('.dps-btn-cancel');
            var appointmentId = btn.dataset.appointmentId;
            showCancelModal(appointmentId);
        }
    });

    // Handler para botÃµes "Repetir serviÃ§o"
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
        var modal = document.createElement('div');
        modal.className = 'dps-modal dps-appointment-request-modal';

        var html = '<div class="dps-modal__overlay"></div>';
        html += '<div class="dps-modal__content">';
        html += '<div class="dps-modal__header">';
        html += '<h3>Confirmar Cancelamento</h3>';
        html += '<button class="dps-modal__close" aria-label="Fechar">Ã—</button>';
        html += '</div>';
        html += '<div class="dps-modal__body">';
        html += '<p>Tem certeza que deseja solicitar o cancelamento deste agendamento?</p>';
        html += '<p class="dps-modal__notice">A equipe pode entrar em contato para confirmar.</p>';
        html += '<div class="dps-form-actions">';
        html += '<button type="button" class="button dps-modal-cancel">Voltar</button>';
        html += '<button type="button" class="button button-primary dps-modal-confirm-cancel">Confirmar Cancelamento</button>';
        html += '</div>';
        html += '</div>';
        html += '</div>';

        modal.innerHTML = html;

        modal.querySelector('.dps-modal__close').addEventListener('click', function() {
            closeModal(modal);
        });
        modal.querySelector('.dps-modal__overlay').addEventListener('click', function() {
            closeModal(modal);
        });
        modal.querySelector('.dps-modal-cancel').addEventListener('click', function() {
            closeModal(modal);
        });
        modal.querySelector('.dps-modal-confirm-cancel').addEventListener('click', function() {
            closeModal(modal);
            submitAppointmentRequest({
                request_type: 'cancel',
                original_appointment_id: appointmentId,
                desired_date: '',
                desired_period: '',
                notes: 'Cliente solicitou cancelamento via portal'
            });
        });

        document.body.appendChild(modal);
        modal.classList.add('is-active');
    }

    /**
     * Mostra modal para repetir serviÃ§o
     */
    function showRepeatServiceModal(appointmentId, petId, services) {
        var modal = createRequestModal('new', appointmentId, petId, services);
        document.body.appendChild(modal);
        modal.classList.add('is-active');
    }

    /**
     * Phase 8.1: ConstrÃ³i HTML do banner de sugestÃ£o inteligente de agendamento.
     */
    function buildSuggestionBanner(suggestion) {
        if (!suggestion) return '';
        var html = '';
        var urgencyClass = '';
        var urgencyIcon = 'ðŸ’¡';
        var urgencyLabel = 'SugestÃ£o';

        if (suggestion.urgency === 'overdue') {
            urgencyClass = ' dps-suggestion-banner--overdue';
            urgencyIcon = 'â°';
            urgencyLabel = 'AtenÃ§Ã£o';
        } else if (suggestion.urgency === 'soon') {
            urgencyClass = ' dps-suggestion-banner--soon';
            urgencyIcon = 'ðŸ“…';
            urgencyLabel = 'Em breve';
        }

        html += '<div class="dps-suggestion-banner__content' + urgencyClass + '">';
        html += '<div class="dps-suggestion-banner__header">';
        html += '<span class="dps-suggestion-banner__icon">' + urgencyIcon + '</span>';
        html += '<strong>' + escapeHtml(urgencyLabel) + '</strong>';
        if (suggestion.pet_name) {
            html += ' â€” ' + escapeHtml(suggestion.pet_name);
        }
        html += '</div>';

        var details = [];
        if (suggestion.days_since_last > 0) {
            details.push('Ãšltimo atendimento: <strong>' + suggestion.days_since_last + ' dias atrÃ¡s</strong>');
        }
        if (suggestion.avg_interval > 0) {
            details.push('FrequÃªncia mÃ©dia: a cada <strong>' + suggestion.avg_interval + ' dias</strong>');
        }
        if (suggestion.top_services && suggestion.top_services.length > 0) {
            details.push('ServiÃ§os frequentes: <strong>' + suggestion.top_services.map(escapeHtml).join(', ') + '</strong>');
        }
        if (suggestion.suggested_date) {
            var parts = suggestion.suggested_date.split('-');
            var dateFormatted = parts[2] + '/' + parts[1] + '/' + parts[0];
            details.push('Data sugerida: <strong>' + escapeHtml(dateFormatted) + '</strong>');
        }

        if (details.length > 0) {
            html += '<div class="dps-suggestion-banner__details">';
            for (var d = 0; d < details.length; d++) {
                html += '<div class="dps-suggestion-banner__detail">' + details[d] + '</div>';
            }
            html += '</div>';
        }

        if (suggestion.suggested_date) {
            html += '<button type="button" class="dps-suggestion-banner__apply" data-date="' + escapeHtml(suggestion.suggested_date) + '">Usar data sugerida</button>';
        }

        html += '</div>';
        return html;
    }

    /**
     * Cria modal de pedido de agendamento com wizard multi-etapa (Phase 4.1)
     */
    function createRequestModal(type, appointmentId, petId, services) {
        var modal = document.createElement('div');
        modal.className = 'dps-modal dps-appointment-request-modal';
        
        var titles = {
            reschedule: 'Solicitar Reagendamento',
            new: 'Repetir ServiÃ§o'
        };
        
        var title = titles[type] || 'Solicitar Agendamento';
        var totalSteps = 3;
        var currentStep = 1;

        var stepLabels = ['Data', 'Detalhes', 'Confirmar'];
        
        var html = '<div class="dps-modal__overlay"></div>';
        html += '<div class="dps-modal__content">';
        html += '<div class="dps-modal__header">';
        html += '<h3>' + title + '</h3>';
        html += '<button class="dps-modal__close" aria-label="Fechar">Ã—</button>';
        html += '</div>';
        html += '<div class="dps-modal__body">';

        // Progress bar
        html += '<div class="dps-progress-bar" role="progressbar" aria-label="Progresso do agendamento" aria-valuenow="1" aria-valuemin="1" aria-valuemax="' + totalSteps + '">';
        for (var s = 1; s <= totalSteps; s++) {
            if (s > 1) {
                html += '<div class="dps-progress-bar__connector' + (s <= currentStep ? ' dps-progress-bar__connector--completed' : '') + '" data-connector="' + s + '"></div>';
            }
            html += '<div class="dps-progress-bar__step' + (s === currentStep ? ' dps-progress-bar__step--active' : '') + '" data-step-indicator="' + s + '">';
            html += '<div class="dps-progress-bar__step-wrapper">';
            html += '<div class="dps-progress-bar__circle">' + s + '</div>';
            html += '<span class="dps-progress-bar__label">' + stepLabels[s - 1] + '</span>';
            html += '</div>';
            html += '</div>';
        }
        html += '</div>';
        html += '<div class="dps-progress-bar__status" aria-live="polite">Passo 1 de ' + totalSteps + '</div>';

        html += '<form class="dps-request-form" id="dps-request-form" novalidate>';
        html += '<input type="hidden" name="request_type" value="' + type + '">';
        html += '<input type="hidden" name="original_appointment_id" value="' + (appointmentId || '') + '">';

        // Phase 5.3: Pet selector for multi-pet clients
        var clientPets = (typeof dpsPortal !== 'undefined' && Array.isArray(dpsPortal.clientPets)) ? dpsPortal.clientPets : [];
        var showPetSelector = clientPets.length > 1;
        if (petId && !showPetSelector) {
            html += '<input type="hidden" name="pet_id" value="' + petId + '">';
        }

        // Step 1: Date & Period (+ Pet selector when applicable)
        html += '<div class="dps-step-panel dps-step-panel--active" data-step="1">';
        html += '<p class="dps-modal__notice"><strong>âš ï¸ Importante:</strong> Este Ã© um <strong>pedido de agendamento</strong>. A equipe do Banho e Tosa irÃ¡ confirmar o horÃ¡rio final com vocÃª.</p>';

        // Pet selector for multi-pet clients
        if (showPetSelector) {
            html += '<div class="dps-form-field">';
            html += '<label for="pet_id">Pet <span class="required">*</span></label>';
            html += '<select id="pet_id" name="pet_id" required aria-required="true">';
            if (!petId) {
                html += '<option value="">Selecione o pet...</option>';
            }
            for (var p = 0; p < clientPets.length; p++) {
                var selected = (petId && String(clientPets[p].id) === String(petId)) ? ' selected' : '';
                html += '<option value="' + escapeHtml(String(clientPets[p].id)) + '"' + selected + '>' + escapeHtml(clientPets[p].icon + ' ' + clientPets[p].name) + '</option>';
            }
            html += '</select>';
            html += '<span class="dps-field-error" role="alert" id="pet_id_error"></span>';
            html += '</div>';
        } else if (petId && showPetSelector) {
            html += '<input type="hidden" name="pet_id" value="' + petId + '">';
        }

        // Phase 8.1: Smart scheduling suggestions
        var suggestions = (typeof dpsPortal !== 'undefined' && dpsPortal.schedulingSuggestions) ? dpsPortal.schedulingSuggestions : {};
        var activePetId = petId || (clientPets.length === 1 ? String(clientPets[0].id) : '');
        var activeSuggestion = activePetId && suggestions[activePetId] ? suggestions[activePetId] : null;

        html += '<div id="dps-scheduling-suggestion" class="dps-suggestion-banner"' + (!activeSuggestion ? ' style="display:none"' : '') + '>';
        if (activeSuggestion) {
            html += buildSuggestionBanner(activeSuggestion);
        }
        html += '</div>';

        html += '<div class="dps-form-field">';
        html += '<label for="desired_date">Data Desejada <span class="required">*</span></label>';
        html += '<input type="date" id="desired_date" name="desired_date" required min="' + getTomorrowDate() + '"' + (activeSuggestion && activeSuggestion.suggested_date ? ' value="' + escapeHtml(activeSuggestion.suggested_date) + '"' : '') + ' aria-required="true">';
        html += '<span class="dps-field-error" role="alert" id="desired_date_error"></span>';
        html += '</div>';
        html += '<div class="dps-form-field">';
        html += '<label for="desired_period">PerÃ­odo Desejado <span class="required">*</span></label>';
        html += '<select id="desired_period" name="desired_period" required aria-required="true">';
        html += '<option value="">Selecione...</option>';
        html += '<option value="morning">ManhÃ£</option>';
        html += '<option value="afternoon">Tarde</option>';
        html += '</select>';
        html += '<span class="dps-field-error" role="alert" id="desired_period_error"></span>';
        html += '</div>';
        html += '<div class="dps-step-actions">';
        html += '<div class="dps-step-actions__left"><button type="button" class="button dps-modal-cancel">Cancelar</button></div>';
        html += '<div class="dps-step-actions__right"><button type="button" class="button button-primary dps-step-next" data-next="2">PrÃ³ximo â†’</button></div>';
        html += '</div>';
        html += '</div>';

        // Step 2: Notes
        html += '<div class="dps-step-panel" data-step="2">';
        html += '<div class="dps-form-field">';
        html += '<label for="notes">ObservaÃ§Ãµes (opcional)</label>';
        html += '<textarea id="notes" name="notes" rows="3" placeholder="Alguma preferÃªncia ou observaÃ§Ã£o?"></textarea>';
        html += '</div>';
        html += '<div class="dps-step-actions">';
        html += '<div class="dps-step-actions__left"><button type="button" class="button dps-step-prev" data-prev="1">â† Voltar</button></div>';
        html += '<div class="dps-step-actions__right"><button type="button" class="button button-primary dps-step-next" data-next="3">PrÃ³ximo â†’</button></div>';
        html += '</div>';
        html += '</div>';

        // Step 3: Review & confirm
        html += '<div class="dps-step-panel" data-step="3">';
        html += '<div class="dps-review-summary" id="dps-review-summary"></div>';
        html += '<div class="dps-step-actions">';
        html += '<div class="dps-step-actions__left"><button type="button" class="button dps-step-prev" data-prev="2">â† Voltar</button></div>';
        html += '<div class="dps-step-actions__right"><button type="submit" class="button button-primary">Enviar SolicitaÃ§Ã£o âœ“</button></div>';
        html += '</div>';
        html += '</div>';

        html += '</form>';
        html += '</div>';
        html += '</div>';
        
        modal.innerHTML = html;

        // Render review summary
        function updateReviewSummary() {
            var dateInput = modal.querySelector('#desired_date');
            var periodInput = modal.querySelector('#desired_period');
            var notesInput = modal.querySelector('#notes');
            var petSelect = modal.querySelector('#pet_id');
            var summary = modal.querySelector('#dps-review-summary');

            var dateValue = dateInput.value;
            var formattedDate = '';
            if (dateValue) {
                var parts = dateValue.split('-');
                formattedDate = parts[2] + '/' + parts[1] + '/' + parts[0];
            }

            var periodMap = { morning: 'ManhÃ£', afternoon: 'Tarde' };
            var periodText = periodMap[periodInput.value] || periodInput.value;
            var notesText = notesInput.value ? notesInput.value : 'â€”';
            var typeMap = { reschedule: 'Reagendamento', new: 'Novo agendamento', cancel: 'Cancelamento' };

            var summaryHtml = '';
            summaryHtml += '<div class="dps-review-summary__item"><span class="dps-review-summary__label">Tipo</span><span class="dps-review-summary__value">' + (typeMap[type] || type) + '</span></div>';

            // Pet name in review (Phase 5.3)
            if (petSelect && petSelect.selectedIndex > 0) {
                summaryHtml += '<div class="dps-review-summary__item"><span class="dps-review-summary__label">ðŸ¾ Pet</span><span class="dps-review-summary__value">' + escapeHtml(petSelect.options[petSelect.selectedIndex].text) + '</span></div>';
            } else if (petId && clientPets.length > 0) {
                var petName = '';
                for (var pi = 0; pi < clientPets.length; pi++) {
                    if (String(clientPets[pi].id) === String(petId)) {
                        petName = clientPets[pi].icon + ' ' + clientPets[pi].name;
                        break;
                    }
                }
                if (petName) {
                    summaryHtml += '<div class="dps-review-summary__item"><span class="dps-review-summary__label">ðŸ¾ Pet</span><span class="dps-review-summary__value">' + escapeHtml(petName) + '</span></div>';
                }
            }

            summaryHtml += '<div class="dps-review-summary__item"><span class="dps-review-summary__label">ðŸ“… Data</span><span class="dps-review-summary__value">' + escapeHtml(formattedDate) + '</span></div>';
            summaryHtml += '<div class="dps-review-summary__item"><span class="dps-review-summary__label">ðŸ• PerÃ­odo</span><span class="dps-review-summary__value">' + escapeHtml(periodText) + '</span></div>';
            summaryHtml += '<div class="dps-review-summary__item"><span class="dps-review-summary__label">ðŸ“ ObservaÃ§Ãµes</span><span class="dps-review-summary__value">' + escapeHtml(notesText) + '</span></div>';
            summary.innerHTML = summaryHtml;
        }

        // Step navigation
        function goToStep(step) {
            if (step < 1 || step > totalSteps) return;
            currentStep = step;

            // Update panels
            var panels = modal.querySelectorAll('.dps-step-panel');
            panels.forEach(function(panel) {
                var panelStep = parseInt(panel.getAttribute('data-step'), 10);
                if (panelStep === currentStep) {
                    panel.classList.add('dps-step-panel--active');
                } else {
                    panel.classList.remove('dps-step-panel--active');
                }
            });

            // Update progress indicators
            for (var i = 1; i <= totalSteps; i++) {
                var indicator = modal.querySelector('[data-step-indicator="' + i + '"]');
                var circle = indicator.querySelector('.dps-progress-bar__circle');
                indicator.classList.remove('dps-progress-bar__step--active', 'dps-progress-bar__step--completed');
                if (i === currentStep) {
                    indicator.classList.add('dps-progress-bar__step--active');
                    circle.textContent = i;
                } else if (i < currentStep) {
                    indicator.classList.add('dps-progress-bar__step--completed');
                    circle.textContent = 'âœ“';
                } else {
                    circle.textContent = i;
                }

                if (i > 1) {
                    var connector = modal.querySelector('[data-connector="' + i + '"]');
                    if (i <= currentStep) {
                        connector.classList.add('dps-progress-bar__connector--completed');
                    } else {
                        connector.classList.remove('dps-progress-bar__connector--completed');
                    }
                }
            }

            // Update aria and status text
            var progressBar = modal.querySelector('.dps-progress-bar');
            progressBar.setAttribute('aria-valuenow', currentStep);
            modal.querySelector('.dps-progress-bar__status').textContent = 'Passo ' + currentStep + ' de ' + totalSteps;

            // Populate review summary on step 3
            if (currentStep === totalSteps) {
                updateReviewSummary();
            }
        }

        // Validate step before proceeding
        function validateStep(step) {
            if (step === 1) {
                var petSelect = modal.querySelector('#pet_id');
                var dateInput = modal.querySelector('#desired_date');
                var periodInput = modal.querySelector('#desired_period');
                var dateError = modal.querySelector('#desired_date_error');
                var periodError = modal.querySelector('#desired_period_error');
                var valid = true;

                dateError.textContent = '';
                periodError.textContent = '';
                dateInput.classList.remove('is-invalid');
                periodInput.classList.remove('is-invalid');

                // Validate pet selection (Phase 5.3)
                if (petSelect) {
                    var petError = modal.querySelector('#pet_id_error');
                    petError.textContent = '';
                    petSelect.classList.remove('is-invalid');
                    if (!petSelect.value) {
                        petError.textContent = 'Selecione o pet para o agendamento.';
                        petSelect.classList.add('is-invalid');
                        petSelect.focus();
                        valid = false;
                    }
                }

                if (!dateInput.value) {
                    dateError.textContent = 'Selecione uma data para o agendamento.';
                    dateInput.classList.add('is-invalid');
                    if (valid) dateInput.focus();
                    valid = false;
                }

                if (!periodInput.value) {
                    periodError.textContent = 'Selecione o perÃ­odo desejado.';
                    periodInput.classList.add('is-invalid');
                    if (valid) periodInput.focus();
                    valid = false;
                }

                return valid;
            }
            return true;
        }
        
        // Event listeners
        modal.querySelector('.dps-modal__close').addEventListener('click', function() {
            closeModal(modal);
        });
        
        modal.querySelector('.dps-modal__overlay').addEventListener('click', function() {
            closeModal(modal);
        });

        // Cancel buttons
        var cancelBtns = modal.querySelectorAll('.dps-modal-cancel');
        cancelBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                closeModal(modal);
            });
        });

        // Next buttons
        var nextBtns = modal.querySelectorAll('.dps-step-next');
        nextBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var nextStep = parseInt(this.getAttribute('data-next'), 10);
                if (validateStep(currentStep)) {
                    goToStep(nextStep);
                }
            });
        });

        // Prev buttons
        var prevBtns = modal.querySelectorAll('.dps-step-prev');
        prevBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var prevStep = parseInt(this.getAttribute('data-prev'), 10);
                goToStep(prevStep);
            });
        });

        // Phase 8.1: Suggestion banner interactions
        // "Usar data sugerida" button
        modal.addEventListener('click', function(e) {
            if (e.target.classList.contains('dps-suggestion-banner__apply')) {
                var suggestedDate = e.target.getAttribute('data-date');
                if (suggestedDate) {
                    var dateInput = modal.querySelector('#desired_date');
                    if (dateInput) {
                        dateInput.value = suggestedDate;
                        dateInput.classList.remove('is-invalid');
                        dateInput.classList.add('is-valid');
                        var err = modal.querySelector('#desired_date_error');
                        if (err) err.textContent = '';
                    }
                }
            }
        });

        // Pet selector change â†’ update suggestion banner
        var petSelect = modal.querySelector('#pet_id');
        if (petSelect) {
            petSelect.addEventListener('change', function() {
                var selectedPetId = this.value;
                var banner = modal.querySelector('#dps-scheduling-suggestion');
                if (!banner) return;
                var newSuggestion = selectedPetId && suggestions[selectedPetId] ? suggestions[selectedPetId] : null;
                if (newSuggestion) {
                    banner.innerHTML = buildSuggestionBanner(newSuggestion);
                    banner.style.display = '';
                    // Auto-fill date if suggestion available
                    if (newSuggestion.suggested_date) {
                        var dateField = modal.querySelector('#desired_date');
                        if (dateField && !dateField.value) {
                            dateField.value = newSuggestion.suggested_date;
                        }
                    }
                } else {
                    banner.innerHTML = '';
                    banner.style.display = 'none';
                }
            });
        }
        
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

        // Fecha modal com Escape
        var escHandler = function(e) {
            if (e.key === 'Escape') {
                closeModal(modal);
                document.removeEventListener('keydown', escHandler);
            }
        };
        document.addEventListener('keydown', escHandler);
        
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
     * Retorna data de amanhÃ£ no formato YYYY-MM-DD
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
                if (window.DPSToast) {
                    window.DPSToast.success(result.data.message);
                } else {
                    showNotification(result.data.message, 'success');
                }
                // Recarrega a pÃ¡gina apÃ³s 2 segundos para mostrar o pedido
                setTimeout(function() {
                    location.reload();
                }, 2000);
            } else {
                var errMsg = result.data && result.data.message ? result.data.message : 'Erro ao enviar solicitaÃ§Ã£o';
                if (window.DPSToast) {
                    window.DPSToast.error(errMsg);
                } else {
                    showNotification(errMsg, 'error');
                }
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Enviar SolicitaÃ§Ã£o';
                }
            }
        })
        .catch(function() {
            if (window.DPSToast) {
                window.DPSToast.error('Falha na conexÃ£o. Verifique sua internet e tente novamente.');
            } else {
                showNotification('Erro ao enviar solicitaÃ§Ã£o. Tente novamente.', 'error');
            }
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Enviar SolicitaÃ§Ã£o';
            }
        });
    }

    /**
     * Mostra notificaÃ§Ã£o na tela
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
     * Gerencia o formulÃ¡rio de avaliaÃ§Ã£o interna
     * - Contador de caracteres para o comentÃ¡rio
     * - Feedback visual na seleÃ§Ã£o de estrelas
     * - ValidaÃ§Ã£o antes do envio
     */
    function handleReviewForm() {
        var form = document.getElementById('dps-review-internal-form');
        if (!form) {
            return;
        }

        if (form.getAttribute('data-dps-review-bound') === '1') {
            return;
        }

        form.setAttribute('data-dps-review-bound', '1');

        // Contador de caracteres
        var textarea = form.querySelector('#review_comment');
        var charCount = document.getElementById('char-count');
        
        if (textarea && charCount) {
            textarea.addEventListener('input', function() {
                var count = this.value.length;
                charCount.textContent = count;
                
                // Cor de aviso quando prÃ³ximo do limite
                if (count >= 500) {
                    charCount.style.color = '#ef4444';
                } else if (count > 450) {
                    charCount.style.color = '#f59e0b';
                } else {
                    charCount.style.color = '';
                }
            });
        }

        // Feedback visual aprimorado para seleÃ§Ã£o de estrelas
        var starInputs = form.querySelectorAll('.dps-star-input');
        var starLabels = form.querySelectorAll('.dps-star-label');
        var ratingHint = form.querySelector('.dps-star-rating-hint');
        
        var ratingMessages = {
            1: 'ðŸ˜ž Pode melhorar',
            2: 'ðŸ˜• RazoÃ¡vel',
            3: 'ðŸ™‚ Bom',
            4: 'ðŸ˜Š Muito bom!',
            5: 'ðŸ¤© Excelente!'
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
                
                // AnimaÃ§Ã£o de confirmaÃ§Ã£o
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

        // PrevenÃ§Ã£o de duplo envio
        var isSubmitting = false;

        // ValidaÃ§Ã£o antes do envio
        form.addEventListener('submit', function(e) {
            // PrevenÃ§Ã£o de duplo envio
            if (isSubmitting) {
                e.preventDefault();
                return false;
            }

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
                    ratingHint.textContent = 'âš ï¸ Por favor, selecione uma nota';
                    ratingHint.style.color = '#ef4444';
                }
                
                return false;
            }
            
            // Marca como enviando para prevenir duplo clique
            isSubmitting = true;

            // Feedback de envio
            var submitBtn = form.querySelector('.dps-btn-submit-review');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="dps-btn-icon">â³</span><span class="dps-btn-text">Enviando...</span>';
            }
        });
    }

    /**
     * Gerencia navegaÃ§Ã£o por tabs de pets na aba HistÃ³rico dos Pets
     * RevisÃ£o de layout: Janeiro 2026
     */
    function handlePetHistoryTabs() {
        var petTabs = document.querySelectorAll('.dps-pet-tab');
        var petPanels = document.querySelectorAll('.dps-pet-timeline-panel');

        if (petTabs.length === 0 || petPanels.length === 0) {
            return;
        }

        /**
         * Ativa uma tab de pet especÃ­fica pelo Ã­ndice.
         * @param {number} index Ãndice da tab a ativar.
         */
        function activateTab(index) {
            if (index < 0 || index >= petTabs.length) {
                return;
            }

            var targetPetId = petTabs[index].getAttribute('data-pet-id');
            if (!targetPetId || !/^\d+$/.test(targetPetId)) {
                return;
            }

            // Remove classe ativa e tabindex de todas as tabs
            petTabs.forEach(function(t) {
                t.classList.remove('dps-pet-tab--active');
                t.setAttribute('aria-selected', 'false');
                t.setAttribute('tabindex', '-1');
            });

            // Adiciona classe ativa Ã  tab selecionada
            petTabs[index].classList.add('dps-pet-tab--active');
            petTabs[index].setAttribute('aria-selected', 'true');
            petTabs[index].setAttribute('tabindex', '0');
            petTabs[index].focus();

            // Esconde todos os painÃ©is
            petPanels.forEach(function(panel) {
                panel.classList.add('dps-pet-timeline-panel--hidden');
                panel.setAttribute('aria-hidden', 'true');
            });

            // Mostra o painel correspondente (usa CSS.escape para prevenir XSS)
            var escapedPetId = CSS.escape(targetPetId);
            var targetPanel = document.querySelector('.dps-pet-timeline-panel[data-pet-id="' + escapedPetId + '"]');
            if (targetPanel) {
                targetPanel.classList.remove('dps-pet-timeline-panel--hidden');
                targetPanel.setAttribute('aria-hidden', 'false');

                // Scroll suave para o painel
                targetPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }

        // Click handler
        petTabs.forEach(function(tab, idx) {
            tab.addEventListener('click', function() {
                activateTab(idx);
            });
        });

        // Keyboard navigation (arrow keys, Home, End)
        var tablist = document.querySelector('.dps-pet-tabs-nav__tabs[role="tablist"]');
        if (tablist) {
            tablist.addEventListener('keydown', function(e) {
                var currentIndex = Array.prototype.indexOf.call(petTabs, document.activeElement);
                if (currentIndex < 0) {
                    return;
                }

                var newIndex = currentIndex;

                switch (e.key) {
                    case 'ArrowRight':
                    case 'ArrowDown':
                        e.preventDefault();
                        newIndex = (currentIndex + 1) % petTabs.length;
                        break;
                    case 'ArrowLeft':
                    case 'ArrowUp':
                        e.preventDefault();
                        newIndex = (currentIndex - 1 + petTabs.length) % petTabs.length;
                        break;
                    case 'Home':
                        e.preventDefault();
                        newIndex = 0;
                        break;
                    case 'End':
                        e.preventDefault();
                        newIndex = petTabs.length - 1;
                        break;
                    default:
                        return;
                }

                activateTab(newIndex);
            });
        }
    }

    /**
     * Gerencia botÃ£o "Repetir ServiÃ§o" para abrir WhatsApp
     * RevisÃ£o de layout: Janeiro 2026
     */
    function handleRepeatService() {
        var repeatButtons = document.querySelectorAll('button.dps-btn-repeat-service');

        repeatButtons.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var services = this.getAttribute('data-services');

                var servicesText = 'servicos';
                try {
                    var servicesArray = JSON.parse(services);
                    if (Array.isArray(servicesArray) && servicesArray.length > 0) {
                        servicesText = servicesArray.join(', ');
                    }
                } catch (e) {
                    // Keep generic text.
                }

                var message = 'Ola! Gostaria de agendar novamente os servicos: ' + servicesText + ' para meu pet.';
                var whatsappNumber = typeof dpsPortal !== 'undefined' && dpsPortal.whatsappNumber ? dpsPortal.whatsappNumber : '';
                if (!whatsappNumber) {
                    console.warn('WhatsApp number is not configured');
                    return;
                }

                if (!/^[\d+]+$/.test(whatsappNumber)) {
                    console.warn('WhatsApp number contains invalid characters');
                    return;
                }

                var whatsappUrl = 'https://wa.me/' + whatsappNumber + '?text=' + encodeURIComponent(message);
                window.open(whatsappUrl, '_blank');
            });
        });
    }

    /**
     * Gerencia botÃ£o "Exportar HistÃ³rico (PDF)"
     * Funcionalidade 3: Export para PDF
     */
    function handleExportPdf() {
        var exportButtons = document.querySelectorAll('.dps-btn-export-pdf');

        if (exportButtons.length === 0) {
            return;
        }

        exportButtons.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var petId = this.getAttribute('data-pet-id');
                var petName = this.getAttribute('data-pet-name');

                // Valida que petId Ã© um nÃºmero
                if (!petId || !/^\d+$/.test(petId)) {
                    console.warn('Invalid pet ID for export');
                    return;
                }

                // ObtÃ©m dados do portal
                var clientId = '';
                var nonce = '';

                if (typeof dpsPortal !== 'undefined') {
                    clientId = dpsPortal.clientId;
                    nonce = dpsPortal.exportPdfNonce;
                }

                if (!clientId || !nonce) {
                    console.error('Missing client ID or nonce for export');
                    return;
                }

                // Monta URL para a pÃ¡gina de impressÃ£o
                var printUrl = dpsPortal.ajaxUrl + 
                    '?action=dps_export_pet_history_pdf' +
                    '&pet_id=' + encodeURIComponent(petId) +
                    '&client_id=' + encodeURIComponent(clientId) +
                    '&nonce=' + encodeURIComponent(nonce);

                // Abre em nova janela (mantendo controles do browser para acessibilidade)
                window.open(printUrl, '_blank', 'width=900,height=700');
            });
        });
    }

    /**
     * Gerencia botÃ£o "Ver mais serviÃ§os" na timeline de pets
     * Carrega mais itens via AJAX e os insere na timeline
     */
    function handleLoadMorePetHistory() {
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.dps-btn-load-more-services');
            if (!btn) {
                return;
            }

            // Previne cliques duplos
            if (btn.disabled) {
                return;
            }

            var petId = btn.getAttribute('data-pet-id');
            var offset = parseInt(btn.getAttribute('data-offset'), 10);

            if (!petId || !/^\d+$/.test(petId) || isNaN(offset)) {
                return;
            }

            if (typeof dpsPortal === 'undefined' || !dpsPortal.petHistoryNonce) {
                return;
            }

            // Estado de carregamento
            btn.disabled = true;
            var originalText = btn.innerHTML;
            btn.innerHTML = 'â³ ' + (dpsPortal.i18n && dpsPortal.i18n.loading ? dpsPortal.i18n.loading : 'Carregando...');

            var formData = new FormData();
            formData.append('action', 'dps_load_more_pet_history');
            formData.append('nonce', dpsPortal.petHistoryNonce);
            formData.append('pet_id', petId);
            formData.append('offset', offset);

            fetch(dpsPortal.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data.success && data.data.html) {
                    // Insere os novos itens antes do botÃ£o "Ver mais"
                    var timeline = btn.closest('.dps-portal-pet-timeline').querySelector('.dps-timeline');
                    if (timeline) {
                        var temp = document.createElement('div');
                        temp.innerHTML = data.data.html;
                        while (temp.firstChild) {
                            timeline.appendChild(temp.firstChild);
                        }
                    }

                    if (data.data.hasMore) {
                        btn.setAttribute('data-offset', data.data.newOffset);
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    } else {
                        // Sem mais itens - remove o botÃ£o
                        var loadMoreContainer = btn.closest('.dps-timeline-load-more');
                        if (loadMoreContainer) {
                            loadMoreContainer.remove();
                        }
                    }
                } else {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                    if (typeof DPSToast !== 'undefined') {
                        DPSToast.show(data.data && data.data.message ? data.data.message : 'Erro ao carregar mais serviÃ§os.', 'error');
                    }
                }
            })
            .catch(function() {
                btn.disabled = false;
                btn.innerHTML = originalText;
                if (typeof DPSToast !== 'undefined') {
                    DPSToast.show('Erro de conexÃ£o. Tente novamente.', 'error');
                }
            });
        });
    }

    /**
     * Filtra timeline de histÃ³rico por perÃ­odo (Fase 4.4).
     * Filtragem client-side: esconde/mostra itens com base no data-date.
     */
    function handleTimelinePeriodFilter() {
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.dps-timeline-filter__btn');
            if (!btn) {
                return;
            }

            var toolbar = btn.closest('.dps-timeline-filter');
            if (!toolbar) {
                return;
            }

            // Atualiza estado ativo
            var siblings = toolbar.querySelectorAll('.dps-timeline-filter__btn');
            for (var i = 0; i < siblings.length; i++) {
                siblings[i].classList.remove('dps-timeline-filter__btn--active');
                siblings[i].setAttribute('aria-pressed', 'false');
            }
            btn.classList.add('dps-timeline-filter__btn--active');
            btn.setAttribute('aria-pressed', 'true');

            var period = btn.getAttribute('data-period');
            var panel = btn.closest('.dps-portal-pet-timeline');
            if (!panel) {
                return;
            }

            var items = panel.querySelectorAll('.dps-timeline-item[data-date]');
            if (!items.length) {
                return;
            }

            var cutoffDate = null;
            if (period !== 'all') {
                var days = parseInt(period, 10);
                if (!isNaN(days) && days > 0) {
                    cutoffDate = new Date();
                    cutoffDate.setDate(cutoffDate.getDate() - days);
                    cutoffDate.setHours(0, 0, 0, 0);
                }
            }

            var visibleCount = 0;
            for (var j = 0; j < items.length; j++) {
                var itemDate = items[j].getAttribute('data-date');
                if (!cutoffDate || !itemDate) {
                    items[j].style.display = '';
                    visibleCount++;
                } else {
                    var d = new Date(itemDate + 'T00:00:00');
                    if (d >= cutoffDate) {
                        items[j].style.display = '';
                        visibleCount++;
                    } else {
                        items[j].style.display = 'none';
                    }
                }
            }

            // Mostra/esconde mensagem de "nenhum resultado"
            var timeline = panel.querySelector('.dps-timeline');
            if (timeline) {
                var emptyMsg = timeline.querySelector('.dps-timeline-filter-empty');
                if (visibleCount === 0) {
                    if (!emptyMsg) {
                        emptyMsg = document.createElement('p');
                        emptyMsg.className = 'dps-timeline-filter-empty';
                        emptyMsg.textContent = 'Nenhum serviÃ§o encontrado neste perÃ­odo.';
                        timeline.appendChild(emptyMsg);
                    }
                    emptyMsg.style.display = '';
                } else if (emptyMsg) {
                    emptyMsg.style.display = 'none';
                }
            }
        });
    }

    /**
     * Gerencia filtro de pets na galeria de fotos
     * RevisÃ£o de layout: Janeiro 2026
     */
    function handleGalleryFilter() {
        var filterButtons = document.querySelectorAll('.dps-gallery-filter__btn');
        var petCards = document.querySelectorAll('.dps-gallery-pet-card');

        if (filterButtons.length === 0 || petCards.length === 0) {
            return;
        }

        filterButtons.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var filterValue = this.getAttribute('data-filter');

                // Valida filtro
                if (!filterValue) {
                    return;
                }

                // Remove classe ativa de todos os botÃµes
                filterButtons.forEach(function(b) {
                    b.classList.remove('is-active');
                });

                // Adiciona classe ativa ao botÃ£o clicado
                this.classList.add('is-active');

                // Filtra cards
                if (filterValue === 'all') {
                    // Mostra todos
                    petCards.forEach(function(card) {
                        card.style.display = '';
                    });
                } else {
                    // Valida que filterValue segue o padrÃ£o esperado (pet-123)
                    if (!/^pet-\d+$/.test(filterValue)) {
                        return;
                    }

                    // Filtra por pet especÃ­fico
                    petCards.forEach(function(card) {
                        var cardPetId = card.getAttribute('data-pet-id');
                        if (cardPetId === filterValue) {
                            card.style.display = '';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                }
            });
        });
    }

    /**
     * Lightbox simples para galeria de fotos
     * RevisÃ£o de layout: Fevereiro 2026
     * Acessibilidade: ARIA dialog, focus trap, focus restore, broken image fallback
     */
    function handleGalleryLightbox() {
        var lightboxLinks = document.querySelectorAll('.dps-gallery-photo__link');

        if (lightboxLinks.length === 0) {
            return;
        }

        // Agrupa links por galeria (data-gallery)
        var galleries = {};
        lightboxLinks.forEach(function(link) {
            var gallery = link.getAttribute('data-gallery') || 'default';
            if (!galleries[gallery]) {
                galleries[gallery] = [];
            }
            galleries[gallery].push(link);
        });

        // Cria o container do lightbox com navegaÃ§Ã£o
        var lightbox = document.createElement('div');
        lightbox.className = 'dps-lightbox';
        lightbox.setAttribute('role', 'dialog');
        lightbox.setAttribute('aria-modal', 'true');
        lightbox.setAttribute('aria-label', 'Visualizar foto');
        lightbox.innerHTML = '' +
            '<div class="dps-lightbox__overlay"></div>' +
            '<div class="dps-lightbox__container">' +
                '<button class="dps-lightbox__close" aria-label="Fechar">&times;</button>' +
                '<button class="dps-lightbox__nav dps-lightbox__nav--prev" aria-label="Foto anterior">&#10094;</button>' +
                '<img class="dps-lightbox__img" src="" alt="">' +
                '<button class="dps-lightbox__nav dps-lightbox__nav--next" aria-label="PrÃ³xima foto">&#10095;</button>' +
                '<div class="dps-lightbox__caption"></div>' +
                '<div class="dps-lightbox__actions">' +
                    '<span class="dps-lightbox__counter"></span>' +
                    '<a href="" class="dps-lightbox__btn dps-lightbox__btn--download" download>â¬‡ï¸ Baixar</a>' +
                '</div>' +
            '</div>';

        document.body.appendChild(lightbox);

        var lightboxImg = lightbox.querySelector('.dps-lightbox__img');
        var lightboxCaption = lightbox.querySelector('.dps-lightbox__caption');
        var lightboxDownload = lightbox.querySelector('.dps-lightbox__btn--download');
        var lightboxClose = lightbox.querySelector('.dps-lightbox__close');
        var lightboxOverlay = lightbox.querySelector('.dps-lightbox__overlay');
        var lightboxPrev = lightbox.querySelector('.dps-lightbox__nav--prev');
        var lightboxNext = lightbox.querySelector('.dps-lightbox__nav--next');
        var lightboxCounter = lightbox.querySelector('.dps-lightbox__counter');
        var lastFocusedElement = null;
        var currentGallery = null;
        var currentIndex = 0;

        // Broken image fallback
        lightboxImg.addEventListener('error', function() {
            this.alt = 'Imagem indisponÃ­vel';
            lightboxCaption.textContent = 'Imagem indisponÃ­vel';
            lightboxDownload.style.display = 'none';
        });

        function showPhoto(index) {
            if (!currentGallery || !galleries[currentGallery]) {
                return;
            }
            var links = galleries[currentGallery];
            if (index < 0 || index >= links.length) {
                return;
            }
            currentIndex = index;
            var link = links[index];
            var imgUrl = link.getAttribute('href');
            var imgTitle = link.getAttribute('title') || '';

            lightboxImg.src = imgUrl;
            lightboxImg.alt = imgTitle || 'Foto do pet';
            lightboxCaption.textContent = imgTitle;
            lightboxDownload.href = imgUrl;
            lightboxDownload.style.display = '';

            // NavegaÃ§Ã£o: mostra/oculta botÃµes
            var hasMultiple = links.length > 1;
            lightboxPrev.style.display = hasMultiple ? '' : 'none';
            lightboxNext.style.display = hasMultiple ? '' : 'none';
            lightboxPrev.disabled = index === 0;
            lightboxNext.disabled = index === links.length - 1;

            // Contador
            lightboxCounter.textContent = hasMultiple ? (index + 1) + ' / ' + links.length : '';
        }

        // Abre lightbox ao clicar em foto
        lightboxLinks.forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                var imgUrl = this.getAttribute('href');
                if (!imgUrl) {
                    return;
                }

                lastFocusedElement = this;
                currentGallery = this.getAttribute('data-gallery') || 'default';
                currentIndex = parseInt(this.getAttribute('data-index') || '0', 10);

                showPhoto(currentIndex);
                lightbox.classList.add('is-active');
                document.body.style.overflow = 'hidden';
                lightboxClose.focus();
            });
        });

        // NavegaÃ§Ã£o
        lightboxPrev.addEventListener('click', function() {
            showPhoto(currentIndex - 1);
        });
        lightboxNext.addEventListener('click', function() {
            showPhoto(currentIndex + 1);
        });

        // Fecha lightbox
        function closeLightbox() {
            lightbox.classList.remove('is-active');
            document.body.style.overflow = '';
            lightboxImg.src = '';
            currentGallery = null;

            if (lastFocusedElement) {
                lastFocusedElement.focus();
                lastFocusedElement = null;
            }
        }

        lightboxClose.addEventListener('click', closeLightbox);
        lightboxOverlay.addEventListener('click', closeLightbox);

        // Keyboard: ESC, arrows, Tab trap
        document.addEventListener('keydown', function(e) {
            if (!lightbox.classList.contains('is-active')) {
                return;
            }

            if (e.key === 'Escape') {
                closeLightbox();
                return;
            }

            // Arrow navigation
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                showPhoto(currentIndex - 1);
                return;
            }
            if (e.key === 'ArrowRight') {
                e.preventDefault();
                showPhoto(currentIndex + 1);
                return;
            }

            // Focus trap
            if (e.key === 'Tab') {
                var focusable = lightbox.querySelectorAll('button:not([style*="display: none"]):not(:disabled), a[href], [tabindex]:not([tabindex="-1"])');
                if (focusable.length === 0) {
                    return;
                }
                var first = focusable[0];
                var last = focusable[focusable.length - 1];

                if (e.shiftKey) {
                    if (document.activeElement === first) {
                        e.preventDefault();
                        last.focus();
                    }
                } else {
                    if (document.activeElement === last) {
                        e.preventDefault();
                        first.focus();
                    }
                }
            }
        });

        // Broken image fallback for gallery grid images
        var galleryImages = document.querySelectorAll('.dps-gallery-photo__img');
        galleryImages.forEach(function(img) {
            img.addEventListener('error', function() {
                this.style.display = 'none';
                var parent = this.closest('.dps-gallery-photo__link');
                if (parent) {
                    var overlay = parent.querySelector('.dps-gallery-photo__overlay');
                    if (overlay) {
                        overlay.innerHTML = '<span class="dps-gallery-photo__zoom">âš ï¸</span>';
                        overlay.style.opacity = '1';
                        overlay.style.backgroundColor = 'var(--dps-gray-100)';
                    }
                }
            });
        });
    }

    // Chama os handlers de galeria na inicializaÃ§Ã£o do DOM
    // Apenas se a galeria existir na pÃ¡gina (otimizaÃ§Ã£o de performance)
    function initPortalEnhancements() {
        handleReviewForm();
        handlePetHistoryTabs();
        handleRepeatService();
        handleExportPdf();
        handleLoadMorePetHistory();
        handleTimelinePeriodFilter();

        var gallerySection = document.querySelector('.dps-portal-gallery');
        if (gallerySection) {
            handleGalleryFilter();
            handleGalleryLightbox();
        }
    }

    window.dpsPortalHandleReviewForm = handleReviewForm;
    window.dpsPortalHandlePetHistoryTabs = handlePetHistoryTabs;
    window.dpsPortalHandleRepeatService = handleRepeatService;
    window.dpsPortalHandleExportPdf = handleExportPdf;
    window.dpsPortalHandleLoadMorePetHistory = handleLoadMorePetHistory;
    window.dpsPortalHandleTimelinePeriodFilter = handleTimelinePeriodFilter;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPortalEnhancements, { once: true });
    } else {
        initPortalEnhancements();
    }
})();
