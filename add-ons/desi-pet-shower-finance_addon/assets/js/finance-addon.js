/**
 * Desi Pet Shower - Finance Add-on Scripts
 *
 * @package    Desi_Pet_Shower
 * @subpackage Finance_Addon
 * @since      1.1.0
 */

(function($) {
    'use strict';

    /**
     * Format currency value to Brazilian format
     * @param {number} value - Value to format
     * @returns {string} Formatted currency string
     */
    function formatCurrency(value) {
        return parseFloat(value).toFixed(2).replace('.', ',');
    }

    /**
     * Parse Brazilian currency format to float
     * @param {string} value - Value in Brazilian format (ex: 1.234,56)
     * @returns {number} Float value
     */
    function parseBrazilianCurrency(value) {
        if (!value) return 0;
        
        var str = String(value).trim();
        
        // Remove currency symbol and extra spaces
        str = str.replace(/R\$\s*/g, '').trim();
        
        // Brazilian format: 1.234,56 (dot as thousand separator, comma as decimal)
        // International format: 1234.56 (dot as decimal)
        
        // Check if it's Brazilian format (has comma as decimal separator)
        if (str.indexOf(',') !== -1) {
            // Brazilian format: remove thousand separators (dots) and replace decimal comma with dot
            str = str.replace(/\./g, '').replace(',', '.');
        } else if (str.indexOf('.') !== -1) {
            // Check if dot is thousand separator (more than 3 digits after) or decimal
            var parts = str.split('.');
            if (parts.length === 2 && parts[1].length === 3 && parts[0].length > 0) {
                // Likely thousand separator (e.g., "1.234"), remove it
                str = str.replace(/\./g, '');
            }
            // Otherwise keep as is (e.g., "1.5" stays "1.5")
        }
        
        return parseFloat(str) || 0;
    }

    /**
     * Format number to Brazilian currency format
     * @param {number} value - Numeric value
     * @returns {string} Formatted string (ex: 1.234,56)
     */
    function formatToBrazilianCurrency(value) {
        if (isNaN(value)) return '0,00';
        return value.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    /**
     * Initialize Finance Add-on functionality
     */
    function init() {
        initServicesModal();
        initDeleteConfirmation();
        initStatusChangeConfirmation();
        initPartialHistory();
        initMoneyMask();
        initFormValidation();
    }

    /**
     * Initialize money mask for currency inputs
     */
    function initMoneyMask() {
        $(document).on('input', '.dps-input-money', function() {
            var $input = $(this);
            var value = $input.val();
            
            // Remove non-numeric characters except comma and dot
            value = value.replace(/[^\d,]/g, '');
            
            // If there's a comma, only keep last 2 digits after it
            if (value.indexOf(',') !== -1) {
                var parts = value.split(',');
                var intPart = parts[0];
                var decPart = parts[1] || '';
                // Limit decimal to 2 digits
                decPart = decPart.substring(0, 2);
                value = intPart + ',' + decPart;
            }
            
            $input.val(value);
        });
        
        // Format on blur
        $(document).on('blur', '.dps-input-money', function() {
            var $input = $(this);
            var value = $input.val();
            
            if (value) {
                var numValue = parseBrazilianCurrency(value);
                if (numValue > 0) {
                    $input.val(formatToBrazilianCurrency(numValue));
                }
            }
        });
        
        // Parse before form submission
        $(document).on('submit', '#dps-finance-new-form', function() {
            var $valueInput = $(this).find('.dps-input-money');
            var brValue = $valueInput.val();
            var numValue = parseBrazilianCurrency(brValue);
            
            // Create a hidden input with the numeric value for form submission
            $(this).find('input[name="finance_value_numeric"]').remove();
            $('<input>').attr({
                type: 'hidden',
                name: 'finance_value_numeric',
                value: numValue
            }).appendTo($(this));
        });
    }

    /**
     * Initialize form validation
     */
    function initFormValidation() {
        $(document).on('submit', '#dps-finance-new-form', function(e) {
            var $form = $(this);
            var isValid = true;
            var errorMessages = [];
            
            // Validate value > 0
            var $valueInput = $form.find('.dps-input-money');
            var value = parseBrazilianCurrency($valueInput.val());
            if (value <= 0) {
                isValid = false;
                errorMessages.push(dpsFinance.i18n.valueRequired || 'O valor deve ser maior que zero.');
                $valueInput.addClass('dps-input-error');
            } else {
                $valueInput.removeClass('dps-input-error');
            }
            
            // Validate date is not in the future (optional - allow future dates for planned transactions)
            var $dateInput = $form.find('input[name="finance_date"]');
            var dateValue = $dateInput.val();
            if (!dateValue) {
                isValid = false;
                errorMessages.push(dpsFinance.i18n.dateRequired || 'A data é obrigatória.');
                $dateInput.addClass('dps-input-error');
            } else {
                $dateInput.removeClass('dps-input-error');
            }
            
            // Validate category is not empty
            var $categoryInput = $form.find('input[name="finance_category"]');
            if (!$categoryInput.val().trim()) {
                isValid = false;
                errorMessages.push(dpsFinance.i18n.categoryRequired || 'A categoria é obrigatória.');
                $categoryInput.addClass('dps-input-error');
            } else {
                $categoryInput.removeClass('dps-input-error');
            }
            
            if (!isValid) {
                e.preventDefault();
                showMessage(errorMessages.join(' '), 'error');
                return false;
            }
            
            return true;
        });
        
        // Remove error class on input
        $(document).on('input change', '.dps-input-error', function() {
            $(this).removeClass('dps-input-error');
        });
    }

    /**
     * Initialize services modal functionality
     * Replaces inline alert() with a styled modal
     */
    function initServicesModal() {
        $(document).on('click', '.dps-trans-services', function(e) {
            e.preventDefault();
            
            var $link = $(this);
            var apptId = $link.data('appt-id');
            
            if (!apptId) {
                return;
            }

            // Show loading state
            $link.addClass('loading').text(dpsFinance.i18n.loading || 'Carregando...');

            $.post(dpsFinance.ajaxUrl, {
                action: 'dps_get_services_details',
                appt_id: apptId,
                nonce: dpsFinance.servicesNonce
            }, function(resp) {
                $link.removeClass('loading').text(dpsFinance.i18n.view || 'Ver');
                
                if (resp && resp.success) {
                    var services = resp.data.services || [];
                    if (services.length > 0) {
                        showServicesModal(services, resp.data.total || 0);
                    } else {
                        showMessage(dpsFinance.i18n.noServices || 'Nenhum serviço encontrado.', 'warning');
                    }
                } else {
                    var msg = resp.data ? resp.data.message : (dpsFinance.i18n.error || 'Erro ao buscar serviços.');
                    showMessage(msg, 'error');
                }
            }).fail(function() {
                $link.removeClass('loading').text(dpsFinance.i18n.view || 'Ver');
                showMessage(dpsFinance.i18n.error || 'Erro ao buscar serviços.', 'error');
            });
        });
    }

    /**
     * Show services in a styled modal
     */
    function showServicesModal(services, total) {
        // Remove existing modal
        $('#dps-services-modal').remove();

        var html = '<div id="dps-services-modal" class="dps-modal-overlay">';
        html += '<div class="dps-modal-content">';
        html += '<div class="dps-modal-header">';
        html += '<h3>' + (dpsFinance.i18n.servicesTitle || 'Serviços do Atendimento') + '</h3>';
        html += '<button type="button" class="dps-modal-close">&times;</button>';
        html += '</div>';
        html += '<div class="dps-modal-body">';
        html += '<table class="dps-modal-table">';
        html += '<thead><tr><th>' + (dpsFinance.i18n.service || 'Serviço') + '</th><th>' + (dpsFinance.i18n.price || 'Valor') + '</th></tr></thead>';
        html += '<tbody>';
        
        for (var i = 0; i < services.length; i++) {
            var srv = services[i];
            html += '<tr><td>' + escapeHtml(srv.name) + '</td><td>R$ ' + formatCurrency(srv.price) + '</td></tr>';
        }
        
        html += '</tbody>';
        
        if (total) {
            html += '<tfoot><tr><th>' + (dpsFinance.i18n.total || 'Total') + '</th><th>R$ ' + formatCurrency(total) + '</th></tr></tfoot>';
        }
        
        html += '</table>';
        html += '</div>';
        html += '<div class="dps-modal-footer">';
        html += '<button type="button" class="button dps-modal-close">' + (dpsFinance.i18n.close || 'Fechar') + '</button>';
        html += '</div>';
        html += '</div>';
        html += '</div>';

        // Add modal styles if not present
        addModalStyles();

        $('body').append(html);

        // Close modal handlers
        $(document).on('click', '.dps-modal-close, .dps-modal-overlay', function(e) {
            if (e.target === this) {
                $('#dps-services-modal').fadeOut(200, function() {
                    $(this).remove();
                });
            }
        });

        // Show modal
        $('#dps-services-modal').fadeIn(200);
    }

    /**
     * Add modal styles dynamically
     */
    function addModalStyles() {
        if ($('#dps-modal-styles').length) {
            return;
        }

        var styles = '<style id="dps-modal-styles">' +
            '.dps-modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 100000; align-items: center; justify-content: center; }' +
            '.dps-modal-content { background: #fff; border-radius: 12px; max-width: 500px; width: 90%; max-height: 80vh; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }' +
            '.dps-modal-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid #e5e7eb; }' +
            '.dps-modal-header h3 { margin: 0; font-size: 18px; color: #111827; }' +
            '.dps-modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #6b7280; line-height: 1; }' +
            '.dps-modal-close:hover { color: #111827; }' +
            '.dps-modal-body { padding: 20px; overflow-y: auto; max-height: 50vh; }' +
            '.dps-modal-footer { padding: 16px 20px; border-top: 1px solid #e5e7eb; text-align: right; }' +
            '.dps-modal-table { width: 100%; border-collapse: collapse; }' +
            '.dps-modal-table th, .dps-modal-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }' +
            '.dps-modal-table thead th { background: #f9fafb; font-weight: 600; }' +
            '.dps-modal-table tfoot th { background: #f3f4f6; font-weight: 600; }' +
            '</style>';

        $('head').append(styles);
    }

    /**
     * Initialize delete confirmation
     */
    function initDeleteConfirmation() {
        $(document).on('click', '.dps-delete-trans', function(e) {
            var confirmMsg = dpsFinance.i18n.confirmDelete || 'Tem certeza que deseja excluir esta transação?';
            if (!confirm(confirmMsg)) {
                e.preventDefault();
                return false;
            }
        });
    }

    /**
     * Initialize status change confirmation for important status changes
     */
    function initStatusChangeConfirmation() {
        $(document).on('change', '.dps-status-select', function() {
            var newStatus = $(this).val();
            var oldStatus = $(this).data('current');
            
            // Confirm if changing from 'pago' to another status
            if (oldStatus === 'pago' && newStatus !== 'pago') {
                var confirmMsg = dpsFinance.i18n.confirmStatusChange || 'Tem certeza que deseja alterar o status desta transação já paga?';
                if (!confirm(confirmMsg)) {
                    $(this).val(oldStatus);
                    return false;
                }
            }
        });
    }

    /**
     * Initialize partial payment history functionality
     */
    function initPartialHistory() {
        $(document).on('click', '.dps-view-partials', function(e) {
            e.preventDefault();
            
            var $link = $(this);
            var transId = $link.data('trans-id');
            
            if (!transId) {
                return;
            }

            // Show loading state
            $link.addClass('loading').text(dpsFinance.i18n.loading || 'Carregando...');

            $.post(dpsFinance.ajaxUrl, {
                action: 'dps_get_partial_history',
                trans_id: transId,
                nonce: dpsFinance.partialHistoryNonce
            }, function(resp) {
                $link.removeClass('loading').text(dpsFinance.i18n.history || 'Histórico');
                
                if (resp && resp.success) {
                    showPartialHistoryModal(transId, resp.data);
                } else {
                    var msg = resp.data ? resp.data.message : (dpsFinance.i18n.error || 'Erro ao buscar histórico.');
                    showMessage(msg, 'error');
                }
            }).fail(function() {
                $link.removeClass('loading').text(dpsFinance.i18n.history || 'Histórico');
                showMessage(dpsFinance.i18n.error || 'Erro ao buscar histórico.', 'error');
            });
        });

        // Handler for deleting partials
        $(document).on('click', '.dps-delete-partial', function(e) {
            e.preventDefault();
            
            var confirmMsg = dpsFinance.i18n.confirmDeletePartial || 'Tem certeza que deseja excluir este pagamento?';
            if (!confirm(confirmMsg)) {
                return;
            }

            var $btn = $(this);
            var partialId = $btn.data('partial-id');
            var transId = $btn.data('trans-id');

            $btn.prop('disabled', true).text('...');

            $.post(dpsFinance.ajaxUrl, {
                action: 'dps_delete_partial',
                partial_id: partialId,
                nonce: dpsFinance.deletePartialNonce
            }, function(resp) {
                if (resp && resp.success) {
                    // Remove row from table
                    $btn.closest('tr').fadeOut(200, function() {
                        $(this).remove();
                        
                        // Update totals in modal
                        if (resp.data.total_pago !== undefined) {
                            $('#dps-partial-total-pago').text('R$ ' + resp.data.total_pago);
                        }
                        if (resp.data.restante !== undefined) {
                            $('#dps-partial-restante').text('R$ ' + resp.data.restante);
                        }

                        // Check if table is empty
                        if ($('#dps-partial-modal tbody tr').length === 0) {
                            $('#dps-partial-modal tbody').html(
                                '<tr><td colspan="4" style="text-align:center;">' + 
                                (dpsFinance.i18n.noPartials || 'Nenhum pagamento registrado.') + 
                                '</td></tr>'
                            );
                        }
                    });
                    showMessage(resp.data.message || 'Parcela excluída.', 'success');
                } else {
                    $btn.prop('disabled', false).text(dpsFinance.i18n.delete || 'Excluir');
                    var msg = resp.data ? resp.data.message : 'Erro ao excluir.';
                    showMessage(msg, 'error');
                }
            }).fail(function() {
                $btn.prop('disabled', false).text(dpsFinance.i18n.delete || 'Excluir');
                showMessage('Erro ao excluir.', 'error');
            });
        });
    }

    /**
     * Show partial payment history in a modal
     */
    function showPartialHistoryModal(transId, data) {
        // Remove existing modal
        $('#dps-partial-modal').remove();

        var html = '<div id="dps-partial-modal" class="dps-modal-overlay">';
        html += '<div class="dps-modal-content">';
        html += '<div class="dps-modal-header">';
        html += '<h3>' + (dpsFinance.i18n.partialHistoryTitle || 'Histórico de Pagamentos') + ' #' + transId + '</h3>';
        html += '<button type="button" class="dps-modal-close">&times;</button>';
        html += '</div>';
        html += '<div class="dps-modal-body">';

        // Summary
        html += '<div class="dps-partial-summary" style="margin-bottom:15px;padding:10px;background:#f9fafb;border-radius:6px;">';
        html += '<div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px;">';
        html += '<span><strong>' + (dpsFinance.i18n.total || 'Total') + ':</strong> R$ ' + data.total + '</span>';
        html += '<span><strong>' + (dpsFinance.i18n.totalPaid || 'Total Pago') + ':</strong> <span id="dps-partial-total-pago">R$ ' + data.total_pago + '</span></span>';
        html += '<span><strong>' + (dpsFinance.i18n.remaining || 'Restante') + ':</strong> <span id="dps-partial-restante">R$ ' + data.restante + '</span></span>';
        html += '</div>';
        html += '</div>';

        // Table
        html += '<table class="dps-modal-table">';
        html += '<thead><tr>';
        html += '<th>' + (dpsFinance.i18n.date || 'Data') + '</th>';
        html += '<th>' + (dpsFinance.i18n.value || 'Valor') + '</th>';
        html += '<th>' + (dpsFinance.i18n.method || 'Método') + '</th>';
        html += '<th>' + (dpsFinance.i18n.actions || 'Ações') + '</th>';
        html += '</tr></thead>';
        html += '<tbody>';
        
        if (data.parcelas && data.parcelas.length > 0) {
            for (var i = 0; i < data.parcelas.length; i++) {
                var p = data.parcelas[i];
                html += '<tr>';
                html += '<td>' + escapeHtml(p.date) + '</td>';
                html += '<td>R$ ' + escapeHtml(p.value) + '</td>';
                html += '<td>' + escapeHtml(p.method) + '</td>';
                html += '<td><button type="button" class="button button-small dps-delete-partial" data-partial-id="' + p.id + '" data-trans-id="' + transId + '">' + (dpsFinance.i18n.delete || 'Excluir') + '</button></td>';
                html += '</tr>';
            }
        } else {
            html += '<tr><td colspan="4" style="text-align:center;">' + (dpsFinance.i18n.noPartials || 'Nenhum pagamento registrado.') + '</td></tr>';
        }
        
        html += '</tbody></table>';
        html += '</div>';
        html += '<div class="dps-modal-footer">';
        html += '<button type="button" class="button dps-modal-close">' + (dpsFinance.i18n.close || 'Fechar') + '</button>';
        html += '</div>';
        html += '</div>';
        html += '</div>';

        // Add modal styles if not present
        addModalStyles();

        $('body').append(html);

        // Show modal
        $('#dps-partial-modal').fadeIn(200);
    }

    /**
     * Show a temporary message
     */
    function showMessage(text, type) {
        // Remove existing messages
        $('.dps-temp-message').remove();

        var className = 'dps-temp-message notice notice-' + (type || 'info');
        var html = '<div class="' + className + '" style="padding: 10px; margin: 10px 0;"><p>' + escapeHtml(text) + '</p></div>';
        
        $('#dps-section-financeiro h3').first().after(html);

        // Auto-remove after 5 seconds
        setTimeout(function() {
            $('.dps-temp-message').fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize when DOM is ready
    $(document).ready(init);

})(jQuery);
