/**
 * Public access runtime for the Client Portal.
 *
 * Keeps the login and password-reset shell isolated from the authenticated
 * portal runtime so the public page does not depend on dashboard handlers.
 *
 * @package DPS_Client_Portal
 */

(function() {
    'use strict';

    var config = window.dpsPortalAccess || {};

    function toArray(nodeList) {
        return Array.prototype.slice.call(nodeList || []);
    }

    function getAccessRoot() {
        return document.querySelector('[data-dps-access-root]');
    }

    function getActiveMode(root) {
        var activeTab = root.querySelector('[data-dps-auth-tab][aria-selected="true"]');
        return activeTab ? activeTab.getAttribute('data-dps-auth-tab') : 'password';
    }

    function setFeedback(target, type, message) {
        if (!target) {
            return;
        }

        target.classList.remove('is-success', 'is-error', 'is-info');

        if (!message) {
            target.textContent = '';
            target.hidden = true;
            return;
        }

        target.textContent = message;
        target.hidden = false;
        if (type) {
            target.classList.add(type);
        }
    }

    function setButtonPending(button, pending) {
        if (!button) {
            return;
        }

        if (!button.dataset.originalLabel) {
            button.dataset.originalLabel = button.textContent.trim();
        }

        button.disabled = !!pending;

        if (pending) {
            var loadingLabel = button.getAttribute('data-loading-label') || button.dataset.originalLabel;
            button.textContent = loadingLabel;
            return;
        }

        button.textContent = button.dataset.originalLabel;
    }

    function syncEmailInputs(root, value, sourceInput) {
        toArray(root.querySelectorAll('[data-dps-access-email]')).forEach(function(input) {
            if (sourceInput && input === sourceInput) {
                return;
            }

            if (input.value !== value) {
                input.value = value;
            }
        });
    }

    function togglePasswordField(button, input) {
        if (!button || !input) {
            return;
        }

        var isVisible = input.getAttribute('type') === 'text';
        input.setAttribute('type', isVisible ? 'password' : 'text');
        button.setAttribute('aria-pressed', isVisible ? 'false' : 'true');
        button.textContent = isVisible
            ? (config.i18n && config.i18n.showPassword ? config.i18n.showPassword : 'Mostrar')
            : (config.i18n && config.i18n.hidePassword ? config.i18n.hidePassword : 'Ocultar');
    }

    function activateMode(root, mode, shouldFocus) {
        var nextMode = mode === 'magic' ? 'magic' : 'password';
        var currentEmailInput = root.querySelector('[data-dps-auth-panel]:not([hidden]) [data-dps-access-email]');
        var currentEmailValue = currentEmailInput ? currentEmailInput.value.trim() : '';

        toArray(root.querySelectorAll('[data-dps-auth-tab]')).forEach(function(tab) {
            var isActive = tab.getAttribute('data-dps-auth-tab') === nextMode;
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
            tab.setAttribute('tabindex', isActive ? '0' : '-1');
            tab.classList.toggle('is-active', isActive);
        });

        toArray(root.querySelectorAll('[data-dps-auth-panel]')).forEach(function(panel) {
            var isActive = panel.getAttribute('data-dps-auth-panel') === nextMode;
            panel.hidden = !isActive;
            panel.classList.toggle('is-active', isActive);
        });

        if (currentEmailValue) {
            syncEmailInputs(root, currentEmailValue, null);
        }

        if (!shouldFocus) {
            return;
        }

        var focusSelector = nextMode === 'password'
            ? '[data-dps-auth-panel="password"] input[name="dps_portal_password"]'
            : '[data-dps-auth-panel="magic"] [data-dps-access-email]';
        var focusTarget = root.querySelector(focusSelector) || root.querySelector('[data-dps-auth-panel="' + nextMode + '"] [data-dps-access-email]');

        if (focusTarget) {
            focusTarget.focus();
        }
    }

    function bindModeSwitch(root) {
        toArray(root.querySelectorAll('[data-dps-auth-tab]')).forEach(function(tab) {
            tab.addEventListener('click', function() {
                activateMode(root, tab.getAttribute('data-dps-auth-tab'), true);
            });

            tab.addEventListener('keydown', function(event) {
                var tabs = toArray(root.querySelectorAll('[data-dps-auth-tab]'));
                var currentIndex = tabs.indexOf(tab);
                var nextIndex = currentIndex;

                if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
                    event.preventDefault();
                    nextIndex = (currentIndex + 1) % tabs.length;
                } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    nextIndex = (currentIndex - 1 + tabs.length) % tabs.length;
                } else if (event.key === 'Home') {
                    event.preventDefault();
                    nextIndex = 0;
                } else if (event.key === 'End') {
                    event.preventDefault();
                    nextIndex = tabs.length - 1;
                } else {
                    return;
                }

                var nextTab = tabs[nextIndex];
                if (nextTab) {
                    activateMode(root, nextTab.getAttribute('data-dps-auth-tab'), false);
                    nextTab.focus();
                }
            });
        });

        activateMode(root, root.getAttribute('data-dps-default-mode') || getActiveMode(root), false);
    }

    function bindEmailSync(root) {
        toArray(root.querySelectorAll('[data-dps-access-email]')).forEach(function(input) {
            input.addEventListener('input', function() {
                syncEmailInputs(root, input.value, input);
            });

            input.addEventListener('change', function() {
                syncEmailInputs(root, input.value, input);
            });
        });
    }

    function bindPasswordToggles(root) {
        toArray(root.querySelectorAll('[data-dps-password-toggle]')).forEach(function(button) {
            var targetId = button.getAttribute('aria-controls');
            var input = targetId ? document.getElementById(targetId) : null;

            if (!input) {
                return;
            }

            button.addEventListener('click', function() {
                togglePasswordField(button, input);
            });
        });
    }

    function requestAccess(actionName, nonce, payload, feedbackTarget, triggerButton, supportCard) {
        if (!config.ajaxUrl || !actionName || !nonce) {
            setFeedback(
                feedbackTarget,
                'is-error',
                config.i18n && config.i18n.genericError
                    ? config.i18n.genericError
                    : 'Nao foi possivel concluir sua solicitacao agora.'
            );
            return;
        }

        var params = new URLSearchParams();
        params.set('action', actionName);
        params.set('_wpnonce', nonce);

        Object.keys(payload).forEach(function(key) {
            params.set(key, payload[key]);
        });

        setButtonPending(triggerButton, true);
        if (supportCard) {
            supportCard.classList.remove('is-highlighted');
        }

        fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: params.toString()
        })
            .then(function(response) {
                return response.json();
            })
            .then(function(response) {
                if (response && response.success) {
                    setFeedback(
                        feedbackTarget,
                        'is-success',
                        response.data && response.data.message ? response.data.message : ''
                    );
                    return;
                }

                if (response && response.data && response.data.show_whatsapp && supportCard) {
                    supportCard.classList.add('is-highlighted');
                }

                setFeedback(
                    feedbackTarget,
                    'is-error',
                    response && response.data && response.data.message
                        ? response.data.message
                        : (config.i18n && config.i18n.genericError ? config.i18n.genericError : 'Nao foi possivel concluir sua solicitacao agora.')
                );
            })
            .catch(function() {
                setFeedback(
                    feedbackTarget,
                    'is-error',
                    config.i18n && config.i18n.genericError
                        ? config.i18n.genericError
                        : 'Nao foi possivel concluir sua solicitacao agora.'
                );
            })
            .finally(function() {
                setButtonPending(triggerButton, false);
            });
    }

    function bindMagicLinkForm(root) {
        var supportCard = root.querySelector('[data-dps-whatsapp-card]');

        toArray(root.querySelectorAll('[data-dps-access-form="magic-link"]')).forEach(function(form) {
            var emailInput = form.querySelector('[data-dps-access-email]');
            var feedback = form.querySelector('[data-dps-form-feedback]');
            var submitButton = form.querySelector('button[type="submit"]');
            var rememberInput = form.querySelector('input[name="remember_me"]');

            if (!emailInput || !submitButton) {
                return;
            }

            form.addEventListener('submit', function(event) {
                event.preventDefault();

                var email = emailInput.value.trim();
                if (!email) {
                    setFeedback(
                        feedback,
                        'is-error',
                        config.i18n && config.i18n.emailRequired
                            ? config.i18n.emailRequired
                            : 'Informe um e-mail valido para continuar.'
                    );
                    emailInput.focus();
                    return;
                }

                requestAccess(
                    config.actions && config.actions.magicLink,
                    config.nonces && config.nonces.magicLink,
                    {
                        email: email,
                        remember_me: rememberInput && rememberInput.checked ? '1' : '0'
                    },
                    feedback,
                    submitButton,
                    supportCard
                );
            });
        });
    }

    function bindPasswordAccessButtons(root) {
        var supportCard = root.querySelector('[data-dps-whatsapp-card]');

        toArray(root.querySelectorAll('[data-dps-password-access-trigger]')).forEach(function(button) {
            button.addEventListener('click', function() {
                var panel = button.closest('[data-dps-auth-panel]') || button.closest('.dps-signature-panel');
                var emailInput = panel ? panel.querySelector('[data-dps-access-email]') : null;
                var feedback = panel ? panel.querySelector('[data-dps-password-feedback]') : null;
                var email = emailInput ? emailInput.value.trim() : '';

                if (!email) {
                    setFeedback(
                        feedback,
                        'is-error',
                        config.i18n && config.i18n.emailRequired
                            ? config.i18n.emailRequired
                            : 'Informe um e-mail valido para continuar.'
                    );

                    if (emailInput) {
                        emailInput.focus();
                    }
                    return;
                }

                requestAccess(
                    config.actions && config.actions.passwordAccess,
                    config.nonces && config.nonces.passwordAccess,
                    {
                        email: email
                    },
                    feedback,
                    button,
                    supportCard
                );
            });
        });
    }

    function init() {
        var root = getAccessRoot();

        if (!root) {
            return;
        }

        bindModeSwitch(root);
        bindEmailSync(root);
        bindPasswordToggles(root);
        bindMagicLinkForm(root);
        bindPasswordAccessButtons(root);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
