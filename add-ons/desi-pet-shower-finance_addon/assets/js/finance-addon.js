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
     * Initialize Finance Add-on functionality
     */
    function init() {
        initServicesModal();
        initDeleteConfirmation();
        initStatusChangeConfirmation();
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
