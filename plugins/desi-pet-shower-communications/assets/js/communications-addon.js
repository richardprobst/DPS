/**
 * JavaScript do Communications Add-on - DPS
 * 
 * Funcionalidades:
 * - Prevenção de duplo clique no formulário
 * - Validação client-side básica
 * - Feedback visual durante submissão
 * - Melhorias de acessibilidade
 * 
 * @package Desi_Pet_Shower_Communications
 * @since 0.3.0
 */

(function($) {
    'use strict';

    /**
     * Inicializa o módulo de comunicações
     */
    function init() {
        var $form = $('#dps-comm-settings-form');
        
        if (!$form.length) {
            return;
        }

        initFormValidation($form);
        initDoubleClickPrevention($form);
        initAccessibility($form);
    }

    /**
     * Validação client-side do formulário
     * 
     * @param {jQuery} $form Elemento do formulário
     */
    function initFormValidation($form) {
        // Validação de e-mail em tempo real
        var $emailField = $form.find('#dps_comm_default_email_from');
        
        $emailField.on('blur', function() {
            var value = $(this).val().trim();
            var $wrapper = $(this).closest('td');
            var $error = $wrapper.find('.dps-field-error');
            
            // Remove erro anterior
            $error.remove();
            $(this).removeClass('dps-field-invalid');
            
            // Valida se não está vazio
            if (value && !isValidEmail(value)) {
                $(this).addClass('dps-field-invalid');
                $wrapper.append('<span class="dps-field-error" role="alert">' + dpsCommL10n.invalidEmail + '</span>');
            }
        });

        // Validação de URL em tempo real
        var $urlField = $form.find('#dps_comm_whatsapp_api_url');
        
        $urlField.on('blur', function() {
            var value = $(this).val().trim();
            var $wrapper = $(this).closest('td');
            var $error = $wrapper.find('.dps-field-error');
            
            // Remove erro anterior
            $error.remove();
            $(this).removeClass('dps-field-invalid');
            
            // Valida se não está vazio e é HTTPS
            if (value && !isValidHttpsUrl(value)) {
                $(this).addClass('dps-field-invalid');
                $wrapper.append('<span class="dps-field-error" role="alert">' + dpsCommL10n.invalidUrl + '</span>');
            }
        });

        // Validação no submit
        $form.on('submit', function(e) {
            var isValid = true;
            
            // Valida e-mail
            var emailValue = $emailField.val().trim();
            if (emailValue && !isValidEmail(emailValue)) {
                isValid = false;
                $emailField.trigger('blur');
            }
            
            // Valida URL
            var urlValue = $urlField.val().trim();
            if (urlValue && !isValidHttpsUrl(urlValue)) {
                isValid = false;
                $urlField.trigger('blur');
            }
            
            // Foca no primeiro campo inválido
            if (!isValid) {
                var $firstInvalid = $form.find('.dps-field-invalid').first();
                if ($firstInvalid.length) {
                    $firstInvalid.focus();
                }
                e.preventDefault();
                return false;
            }
        });
    }

    /**
     * Prevenção de duplo clique no formulário
     * 
     * @param {jQuery} $form Elemento do formulário
     */
    function initDoubleClickPrevention($form) {
        var $submitBtn = $form.find('.button-primary');
        var originalText = $submitBtn.val();
        var isSubmitting = false;

        $form.on('submit', function(e) {
            // Se já está submetendo, previne
            if (isSubmitting) {
                e.preventDefault();
                return false;
            }

            // Marca como submetendo
            isSubmitting = true;

            // Desabilita botão e mostra loading
            $submitBtn
                .prop('disabled', true)
                .addClass('is-loading')
                .val(dpsCommL10n.saving);

            // Adiciona spinner
            if (!$submitBtn.find('.spinner').length) {
                $submitBtn.append('<span class="spinner"></span>');
            }

            // Timeout de segurança (re-habilita após 30s se algo der errado)
            setTimeout(function() {
                if (isSubmitting) {
                    isSubmitting = false;
                    $submitBtn
                        .prop('disabled', false)
                        .removeClass('is-loading')
                        .val(originalText)
                        .find('.spinner').remove();
                }
            }, 30000);
        });
    }

    /**
     * Melhorias de acessibilidade
     * 
     * @param {jQuery} $form Elemento do formulário
     */
    function initAccessibility($form) {
        // Adiciona aria-describedby aos campos que têm descrição
        $form.find('.form-table tr').each(function() {
            var $row = $(this);
            var $input = $row.find('input, textarea').first();
            var $description = $row.find('.description');
            
            if ($input.length && $description.length) {
                var descId = $input.attr('id') + '-desc';
                $description.attr('id', descId);
                $input.attr('aria-describedby', descId);
            }
        });

        // Adiciona aria-required aos campos obrigatórios (se existirem)
        $form.find('input[required], textarea[required]').attr('aria-required', 'true');

        // Melhora foco para navegação por teclado
        $form.find('input, textarea').on('focus', function() {
            $(this).closest('tr').addClass('is-focused');
        }).on('blur', function() {
            $(this).closest('tr').removeClass('is-focused');
        });
    }

    /**
     * Valida formato de e-mail
     * 
     * @param {string} email E-mail a validar
     * @return {boolean} True se válido
     */
    function isValidEmail(email) {
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    /**
     * Valida URL HTTPS
     * 
     * @param {string} url URL a validar
     * @return {boolean} True se válido
     */
    function isValidHttpsUrl(url) {
        try {
            var urlObj = new URL(url);
            return urlObj.protocol === 'https:';
        } catch (e) {
            return false;
        }
    }

    /**
     * Inicializa funcionalidades da seção de webhook
     */
    function initWebhookSection() {
        var $secretField = $('#dps_webhook_secret');
        var $toggleBtn = $('#dps-toggle-secret');
        var $copyBtn = $('#dps-copy-secret');

        if (!$secretField.length) {
            return;
        }

        // Toggle mostrar/ocultar secret
        $toggleBtn.on('click', function() {
            var $icon = $(this).find('.dashicons');
            if ($secretField.attr('type') === 'password') {
                $secretField.attr('type', 'text');
                $icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
            } else {
                $secretField.attr('type', 'password');
                $icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
            }
        });

        // Copiar secret para clipboard
        $copyBtn.on('click', function() {
            var secret = $secretField.val();
            var $btn = $(this);
            var $icon = $btn.find('.dashicons');
            
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(secret).then(function() {
                    // Feedback visual de sucesso
                    $icon.removeClass('dashicons-clipboard').addClass('dashicons-yes');
                    setTimeout(function() {
                        $icon.removeClass('dashicons-yes').addClass('dashicons-clipboard');
                    }, 2000);
                }).catch(function() {
                    fallbackCopyToClipboard(secret);
                });
            } else {
                fallbackCopyToClipboard(secret);
            }
        });
    }

    /**
     * Fallback para copiar ao clipboard (browsers antigos)
     * 
     * Usa document.execCommand('copy') que é deprecated mas ainda funciona
     * em browsers mais antigos que não suportam Clipboard API.
     * 
     * @param {string} text Texto a copiar
     * @deprecated Usar navigator.clipboard.writeText() quando disponível
     */
    function fallbackCopyToClipboard(text) {
        try {
            var $temp = $('<input>');
            $('body').append($temp);
            $temp.val(text).select();
            // eslint-disable-next-line no-restricted-syntax
            document.execCommand('copy');
            $temp.remove();
        } catch (e) {
            // Silently fail - user will need to copy manually
            console.warn('Clipboard fallback failed:', e);
        }
    }

    // Inicializa quando DOM estiver pronto
    $(document).ready(function() {
        init();
        initWebhookSection();
    });

})(jQuery);
