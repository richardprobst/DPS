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
                var currentUrl = window.location.href;
                
                // Compatibilidade com navegadores mais antigos (sem URL constructor)
                if (status) {
                    if (currentUrl.indexOf('ref_status=') !== -1) {
                        currentUrl = currentUrl.replace(/ref_status=[^&]*/, 'ref_status=' + encodeURIComponent(status));
                    } else {
                        currentUrl += (currentUrl.indexOf('?') !== -1 ? '&' : '?') + 'ref_status=' + encodeURIComponent(status);
                    }
                } else {
                    currentUrl = currentUrl.replace(/[?&]ref_status=[^&]*/g, '').replace(/\?$/, '');
                }
                
                // Remove parâmetro de página para resetar paginação
                currentUrl = currentUrl.replace(/[?&]ref_page=[^&]*/g, '').replace(/\?$/, '');
                
                window.location.href = currentUrl;
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
     * Inicializa autocomplete de busca de clientes.
     *
     * @since 1.3.0
     */
    DPSLoyalty.initClientAutocomplete = function() {
        var $searchInput = $('#dps-loyalty-client-search');
        var $resultsContainer = $('#dps-loyalty-client-results');
        var $hiddenInput = $('#dps-loyalty-client-id');
        var $form = $('#dps-loyalty-client-form');
        
        if (!$searchInput.length) {
            return;
        }

        var searchTimeout = null;
        var minChars = 2;

        // Esconde resultados ao clicar fora.
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.dps-autocomplete-wrapper').length) {
                $resultsContainer.hide();
            }
        });

        // Busca ao digitar.
        $searchInput.on('input', function() {
            var query = $(this).val().trim();
            
            // Limpa seleção anterior quando usuário digita.
            $hiddenInput.val('');
            
            clearTimeout(searchTimeout);
            
            if (query.length < minChars) {
                $resultsContainer.hide().empty();
                return;
            }

            // Debounce de 300ms.
            searchTimeout = setTimeout(function() {
                DPSLoyalty.searchClients(query, $resultsContainer, $searchInput, $hiddenInput);
            }, 300);
        });

        // Navegação por teclado.
        $searchInput.on('keydown', function(e) {
            var $items = $resultsContainer.find('.dps-autocomplete-item');
            var $active = $items.filter('.active');
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (!$active.length) {
                    $items.first().addClass('active');
                } else {
                    $active.removeClass('active').next('.dps-autocomplete-item').addClass('active');
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if ($active.length) {
                    $active.removeClass('active').prev('.dps-autocomplete-item').addClass('active');
                }
            } else if (e.key === 'Enter') {
                if ($active.length) {
                    e.preventDefault();
                    $active.trigger('click');
                }
            } else if (e.key === 'Escape') {
                $resultsContainer.hide();
            }
        });

        // Seleção de resultado.
        $resultsContainer.on('click', '.dps-autocomplete-item', function(e) {
            e.preventDefault();
            var $item = $(this);
            var clientId = $item.data('id');
            var clientName = $item.data('name');
            
            $searchInput.val(clientName);
            $hiddenInput.val(clientId);
            $resultsContainer.hide();
            
            // Submete o formulário automaticamente.
            $form.submit();
        });

        // Hover sobre resultados.
        $resultsContainer.on('mouseenter', '.dps-autocomplete-item', function() {
            $resultsContainer.find('.dps-autocomplete-item').removeClass('active');
            $(this).addClass('active');
        });
    };

    /**
     * Busca clientes via AJAX.
     *
     * @since 1.3.0
     *
     * @param {string} query Termo de busca.
     * @param {jQuery} $resultsContainer Container dos resultados.
     * @param {jQuery} $searchInput Campo de busca.
     * @param {jQuery} $hiddenInput Campo oculto com ID.
     */
    DPSLoyalty.searchClients = function(query, $resultsContainer, $searchInput, $hiddenInput) {
        // Verifica se dados estão disponíveis.
        if (typeof dpsLoyaltyData === 'undefined') {
            return;
        }

        // Mostra loading.
        $resultsContainer.html('<div class="dps-autocomplete-loading">' + 
            (dpsLoyaltyData.i18n.searching || 'Buscando...') + '</div>').show();

        $.ajax({
            url: dpsLoyaltyData.ajaxUrl,
            type: 'GET',
            data: {
                action: 'dps_loyalty_search_clients',
                nonce: dpsLoyaltyData.nonce,
                q: query
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    var html = '';
                    $.each(response.data, function(i, client) {
                        var subtitle = client.phone ? client.phone : '';
                        if (client.points > 0) {
                            subtitle += (subtitle ? ' • ' : '') + client.points + ' pts';
                        }
                        
                        // Escape all dynamic values including ID to prevent XSS
                        var clientId = DPSLoyalty.escapeHtml(String(client.id));
                        var clientName = DPSLoyalty.escapeHtml(client.text);
                        
                        html += '<div class="dps-autocomplete-item" data-id="' + clientId + '" data-name="' + clientName + '">';
                        html += '<span class="dps-autocomplete-name">' + clientName + '</span>';
                        if (subtitle) {
                            html += '<span class="dps-autocomplete-subtitle">' + DPSLoyalty.escapeHtml(subtitle) + '</span>';
                        }
                        html += '</div>';
                    });
                    $resultsContainer.html(html).show();
                } else {
                    $resultsContainer.html('<div class="dps-autocomplete-no-results">' +
                        (dpsLoyaltyData.i18n.noResults || 'Nenhum cliente encontrado.') + '</div>').show();
                }
            },
            error: function() {
                $resultsContainer.html('<div class="dps-autocomplete-error">Erro na busca.</div>').show();
            }
        });
    };

    /**
     * Escapa HTML para prevenir XSS.
     *
     * @since 1.3.0
     *
     * @param {string} text Texto a escapar.
     * @return {string} Texto escapado.
     */
    DPSLoyalty.escapeHtml = function(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    };

    /**
     * Renderiza gráfico de série temporal (pontos concedidos x resgatados).
     */
    DPSLoyalty.renderTimeseries = function() {
        var $canvas = $('#dps-loyalty-timeseries');
        if (!$canvas.length || typeof Chart === 'undefined') {
            return;
        }

        var dataset = $canvas.data('timeseries');
        if (!dataset || !dataset.labels) {
            return;
        }

        new Chart($canvas, {
            type: 'line',
            data: {
                labels: dataset.labels,
                datasets: [
                    {
                        label: 'Pontos concedidos',
                        data: dataset.granted,
                        borderColor: '#0ea5e9',
                        backgroundColor: 'rgba(14,165,233,0.1)',
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Pontos resgatados/expirados',
                        data: dataset.redeemed,
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239,68,68,0.1)',
                        tension: 0.3,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    };

    /**
     * Renderiza gráfico de pizza para níveis.
     */
    DPSLoyalty.renderTierChart = function() {
        var $canvas = $('#dps-loyalty-tiers');
        if (!$canvas.length || typeof Chart === 'undefined') {
            return;
        }

        var tiers = $canvas.data('tiers');
        if (!tiers) {
            return;
        }

        new Chart($canvas, {
            type: 'doughnut',
            data: {
                labels: ['Bronze', 'Prata', 'Ouro'],
                datasets: [{
                    data: [tiers.bronze || 0, tiers.prata || 0, tiers.ouro || 0],
                    backgroundColor: ['#b45309', '#6b7280', '#d97706']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
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
        DPSLoyalty.initClientAutocomplete();
        DPSLoyalty.renderTimeseries();
        DPSLoyalty.renderTierChart();
    };

    // Inicializar quando documento estiver pronto
    $(document).ready(function() {
        DPSLoyalty.init();
    });

})(jQuery);
