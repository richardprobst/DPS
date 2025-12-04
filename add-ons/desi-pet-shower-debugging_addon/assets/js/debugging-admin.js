/**
 * DPS Debugging Admin JavaScript
 *
 * Funcionalidades interativas para o add-on de debugging.
 *
 * @package DPS_Debugging_Addon
 */

(function($) {
    'use strict';

    /**
     * Inicialização quando o DOM estiver pronto.
     */
    $(document).ready(function() {
        initSearch();
        initCopyLog();
        initSearchClear();
    });

    /**
     * Inicializa a funcionalidade de busca no log.
     */
    function initSearch() {
        var $searchInput = $('#dps-debugging-search');
        var $searchResults = $('#dps-debugging-search-results');
        var $searchClear = $('.dps-debugging-search-clear');
        var searchTimeout = null;

        if (!$searchInput.length) {
            return;
        }

        $searchInput.on('input', function() {
            var searchTerm = $(this).val().trim().toLowerCase();
            
            // Debounce para evitar buscas excessivas
            clearTimeout(searchTimeout);
            
            if (searchTerm.length < 2) {
                showAllEntries();
                $searchClear.hide();
                $searchResults.hide();
                return;
            }

            $searchClear.show();

            searchTimeout = setTimeout(function() {
                filterEntries(searchTerm);
            }, 300);
        });

        // Busca ao pressionar Enter
        $searchInput.on('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(searchTimeout);
                var searchTerm = $(this).val().trim().toLowerCase();
                if (searchTerm.length >= 2) {
                    filterEntries(searchTerm);
                }
            }
        });
    }

    /**
     * Filtra entradas do log baseado no termo de busca.
     *
     * @param {string} searchTerm Termo de busca.
     */
    function filterEntries(searchTerm) {
        var $entries = $('.dps-debugging-log-entry');
        var $searchResults = $('#dps-debugging-search-results');
        var matchCount = 0;

        $entries.each(function() {
            var $entry = $(this);
            var entryText = $entry.text().toLowerCase();
            
            if (entryText.indexOf(searchTerm) !== -1) {
                $entry.show();
                highlightText($entry, searchTerm);
                matchCount++;
            } else {
                $entry.hide();
            }
        });

        // Exibe mensagem de resultados
        if (matchCount === 0) {
            $searchResults.html('<span class="dps-debugging-no-results">' + 
                dpsDebugging.noResults + ' "' + escapeHtml(searchTerm) + '"</span>').show();
        } else {
            $searchResults.html('<span class="dps-debugging-found">' + 
                matchCount + ' ' + dpsDebugging.filtered + '</span>').show();
        }
    }

    /**
     * Mostra todas as entradas e remove highlights.
     */
    function showAllEntries() {
        var $entries = $('.dps-debugging-log-entry');
        $entries.show();
        
        // Remove highlights
        $('.dps-debugging-highlight').each(function() {
            $(this).replaceWith($(this).text());
        });
    }

    /**
     * Destaca o texto encontrado nas entradas.
     *
     * @param {jQuery} $entry Elemento da entrada.
     * @param {string} searchTerm Termo de busca.
     */
    function highlightText($entry, searchTerm) {
        var $content = $entry.find('.dps-debugging-log-entry-content');
        
        if (!$content.length) {
            return;
        }

        // Remove highlights anteriores
        $content.find('.dps-debugging-highlight').each(function() {
            $(this).replaceWith($(this).text());
        });

        // Aplica novo highlight
        var html = $content.html();
        var regex = new RegExp('(' + escapeRegex(searchTerm) + ')', 'gi');
        html = html.replace(regex, '<mark class="dps-debugging-highlight">$1</mark>');
        $content.html(html);
    }

    /**
     * Inicializa o botão de limpar busca.
     */
    function initSearchClear() {
        var $searchClear = $('.dps-debugging-search-clear');
        var $searchInput = $('#dps-debugging-search');
        var $searchResults = $('#dps-debugging-search-results');

        $searchClear.on('click', function() {
            $searchInput.val('');
            showAllEntries();
            $(this).hide();
            $searchResults.hide();
            $searchInput.focus();
        });
    }

    /**
     * Inicializa a funcionalidade de copiar log.
     */
    function initCopyLog() {
        var $copyButton = $('.dps-debugging-copy-log');

        if (!$copyButton.length) {
            return;
        }

        $copyButton.on('click', function() {
            var $button = $(this);
            var targetSelector = $button.data('target');
            var $target = $(targetSelector);
            var text = '';

            if ($target.length) {
                // Para logs formatados, pega o texto de cada entrada
                if ($target.is('.dps-debugging-log-entries')) {
                    $target.find('.dps-debugging-log-entry').each(function() {
                        text += $(this).text().trim() + '\n\n';
                    });
                } else {
                    // Para log raw
                    text = $target.text();
                }
            }

            if (!text) {
                return;
            }

            copyToClipboard(text, $button);
        });
    }

    /**
     * Copia texto para a área de transferência.
     *
     * @param {string} text Texto para copiar.
     * @param {jQuery} $button Botão que iniciou a ação.
     */
    function copyToClipboard(text, $button) {
        var originalText = $button.text();

        // Usa API moderna de clipboard se disponível
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function() {
                showCopySuccess($button, originalText);
            }).catch(function() {
                fallbackCopy(text, $button, originalText);
            });
        } else {
            fallbackCopy(text, $button, originalText);
        }
    }

    /**
     * Fallback para copiar texto em navegadores antigos.
     *
     * @param {string} text Texto para copiar.
     * @param {jQuery} $button Botão que iniciou a ação.
     * @param {string} originalText Texto original do botão.
     */
    function fallbackCopy(text, $button, originalText) {
        var $temp = $('<textarea>');
        $temp.val(text).css({
            position: 'absolute',
            left: '-9999px'
        }).appendTo('body').select();

        try {
            var successful = document.execCommand('copy');
            if (successful) {
                showCopySuccess($button, originalText);
            } else {
                showCopyError($button, originalText);
            }
        } catch (err) {
            showCopyError($button, originalText);
        }

        $temp.remove();
    }

    /**
     * Exibe feedback de sucesso ao copiar.
     *
     * @param {jQuery} $button Botão que iniciou a ação.
     * @param {string} originalText Texto original do botão.
     */
    function showCopySuccess($button, originalText) {
        $button.text(dpsDebugging.copySuccess);
        $button.addClass('dps-debugging-copy-success');

        setTimeout(function() {
            $button.text(originalText);
            $button.removeClass('dps-debugging-copy-success');
        }, 2000);
    }

    /**
     * Exibe feedback de erro ao copiar.
     *
     * @param {jQuery} $button Botão que iniciou a ação.
     * @param {string} originalText Texto original do botão.
     */
    function showCopyError($button, originalText) {
        $button.text(dpsDebugging.copyError);
        $button.addClass('dps-debugging-copy-error');

        setTimeout(function() {
            $button.text(originalText);
            $button.removeClass('dps-debugging-copy-error');
        }, 2000);
    }

    /**
     * Escapa caracteres especiais para uso em regex.
     *
     * @param {string} string String para escapar.
     * @return {string} String escapada.
     */
    function escapeRegex(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    /**
     * Escapa HTML para exibição segura.
     *
     * @param {string} text Texto para escapar.
     * @return {string} Texto escapado.
     */
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

})(jQuery);
