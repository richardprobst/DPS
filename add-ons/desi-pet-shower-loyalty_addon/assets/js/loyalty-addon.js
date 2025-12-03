/**
 * JavaScript do Loyalty Add-on
 *
 * @package Desi_Pet_Shower_Loyalty
 * @since   1.1.0
 */

(function($) {
    'use strict';

    window.DPSLoyalty = window.DPSLoyalty || {};

    /**
     * Copia texto para a área de transferência.
     *
     * @param {string} text Texto a copiar.
     * @param {function} callback Callback após copiar.
     */
    DPSLoyalty.copyToClipboard = function(text, callback) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function() {
                if (callback) callback(true);
            }).catch(function() {
                DPSLoyalty.fallbackCopyToClipboard(text, callback);
            });
        } else {
            DPSLoyalty.fallbackCopyToClipboard(text, callback);
        }
    };

    /**
     * Fallback para copiar em navegadores antigos.
     *
     * @param {string} text Texto a copiar.
     * @param {function} callback Callback após copiar.
     */
    DPSLoyalty.fallbackCopyToClipboard = function(text, callback) {
        var textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            var successful = document.execCommand('copy');
            if (callback) callback(successful);
        } catch (err) {
            if (callback) callback(false);
        }

        document.body.removeChild(textArea);
    };

    /**
     * Exibe feedback visual temporário.
     *
     * @param {jQuery} $element Elemento onde exibir.
     * @param {string} message Mensagem a exibir.
     * @param {string} type Tipo: success, error.
     */
    DPSLoyalty.showFeedback = function($element, message, type) {
        var $feedback = $('<span class="dps-feedback dps-feedback-' + type + '">' + message + '</span>');
        $element.after($feedback);
        
        setTimeout(function() {
            $feedback.fadeOut(300, function() {
                $(this).remove();
            });
        }, 2000);
    };

    /**
     * Inicializa botões de copiar código de indicação.
     */
    DPSLoyalty.initCopyButtons = function() {
        $(document).on('click', '.dps-copy-referral-code', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var code = $btn.data('code') || $btn.siblings('code').text();
            
            DPSLoyalty.copyToClipboard(code, function(success) {
                if (success) {
                    var originalText = $btn.text();
                    $btn.text('✓ Copiado!');
                    setTimeout(function() {
                        $btn.text(originalText);
                    }, 2000);
                }
            });
        });

        $(document).on('click', '.dps-copy-referral-link', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $input = $btn.closest('.dps-referral-link-box').find('input');
            var link = $input.val();
            
            DPSLoyalty.copyToClipboard(link, function(success) {
                if (success) {
                    var originalText = $btn.text();
                    $btn.text('✓ Copiado!');
                    setTimeout(function() {
                        $btn.text(originalText);
                    }, 2000);
                }
            });
        });
    };

    /**
     * Inicializa filtros da tabela de indicações.
     */
    DPSLoyalty.initReferralsFilters = function() {
        var $statusFilter = $('#dps-referrals-status-filter');
        
        if ($statusFilter.length) {
            $statusFilter.on('change', function() {
                var status = $(this).val();
                var currentUrl = new URL(window.location.href);
                
                if (status) {
                    currentUrl.searchParams.set('ref_status', status);
                } else {
                    currentUrl.searchParams.delete('ref_status');
                }
                
                currentUrl.searchParams.delete('ref_page'); // Reset pagination
                window.location.href = currentUrl.toString();
            });
        }
    };

    /**
     * Inicializa animações de progresso.
     */
    DPSLoyalty.initProgressAnimations = function() {
        $('.dps-points-progress-fill').each(function() {
            var $fill = $(this);
            var width = $fill.data('width') || $fill.css('width');
            
            $fill.css('width', '0%');
            
            setTimeout(function() {
                $fill.css('width', width);
            }, 100);
        });
    };

    /**
     * Inicializa tooltips.
     */
    DPSLoyalty.initTooltips = function() {
        $('[data-tooltip]').each(function() {
            var $el = $(this);
            var text = $el.data('tooltip');
            
            $el.on('mouseenter', function() {
                var $tooltip = $('<div class="dps-tooltip">' + text + '</div>');
                $('body').append($tooltip);
                
                var offset = $el.offset();
                $tooltip.css({
                    top: offset.top - $tooltip.outerHeight() - 8,
                    left: offset.left + ($el.outerWidth() / 2) - ($tooltip.outerWidth() / 2)
                });
            });
            
            $el.on('mouseleave', function() {
                $('.dps-tooltip').remove();
            });
        });
    };

    /**
     * Inicializa compartilhamento via WhatsApp.
     */
    DPSLoyalty.initWhatsAppShare = function() {
        $(document).on('click', '.dps-share-whatsapp', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var message = $btn.data('message') || '';
            var url = $btn.data('url') || '';
            
            var text = message + (url ? '\n' + url : '');
            var whatsappUrl = 'https://wa.me/?text=' + encodeURIComponent(text);
            
            window.open(whatsappUrl, '_blank');
        });
    };

    /**
     * Inicialização principal.
     */
    DPSLoyalty.init = function() {
        DPSLoyalty.initCopyButtons();
        DPSLoyalty.initReferralsFilters();
        DPSLoyalty.initProgressAnimations();
        DPSLoyalty.initTooltips();
        DPSLoyalty.initWhatsAppShare();
    };

    // Inicializar quando documento estiver pronto
    $(document).ready(function() {
        DPSLoyalty.init();
    });

})(jQuery);
