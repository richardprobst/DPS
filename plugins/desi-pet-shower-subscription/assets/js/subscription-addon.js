/**
 * JavaScript do Add-on de Assinaturas
 *
 * @package Desi_Pet_Shower_Subscription
 * @since 1.1.0
 */

(function($) {
    'use strict';

    window.DPSSubscription = window.DPSSubscription || {};
    window.dpsSubscriptionStrings = window.dpsSubscriptionStrings || {
        description: 'Descrição do serviço',
        remove: 'Remover'
    };

    /**
     * Filtra pets pelo cliente selecionado no formulário de assinatura.
     * Oculta pets que não pertencem ao cliente e limpa seleção se necessário.
     *
     * @param {string} ownerId ID do cliente proprietário
     */
    DPSSubscription.filterPetsByClient = function(ownerId) {
        var $select = $('#dps-subscription-pet-select');
        var $container = $select.closest('.dps-form-field');
        
        if (!ownerId) {
            $select.val('');
            $container.hide();
            return;
        }
        
        $container.show();
        $select.find('option').each(function() {
            var $opt = $(this);
            var optOwner = $opt.attr('data-owner');
            
            if (!optOwner) {
                // Opção "Selecione..." sempre visível
                $opt.show();
            } else {
                // Mostra apenas pets do cliente selecionado
                $opt.toggle(String(optOwner) === String(ownerId));
            }
        });
        
        // Limpar seleção se pet atual não pertence ao cliente
        if ($select.find('option:selected').is(':hidden')) {
            $select.val('');
        }
    };

    /**
     * Inicialização do módulo de assinaturas.
     * Configura eventos e estado inicial dos formulários.
     */
    DPSSubscription.init = function() {
        // Vincula eventos de extras SEMPRE no início, antes de qualquer return.
        // Isso garante que o binding funcione mesmo ao navegar da listagem
        // para o formulário, pois usa delegação de eventos $(document).on().
        // Antes, era chamado após a verificação do $clientSelect, o que impedia
        // o funcionamento quando o formulário não existia na página inicial.
        DPSSubscription.bindExtras();

        var $clientSelect = $('select[name="subscription_client_id"]');
        
        if ($clientSelect.length === 0) {
            return; // Não estamos na página de assinaturas com formulário
        }
        
        var initialClient = $clientSelect.val();
        
        // Aplica filtro inicial
        if (initialClient) {
            DPSSubscription.filterPetsByClient(initialClient);
        } else {
            $('#dps-subscription-pet-select').closest('.dps-form-field').hide();
        }
        
        // Evento de mudança de cliente
        $clientSelect.on('change', function() {
            DPSSubscription.filterPetsByClient($(this).val());
        });
    };

    /**
     * Gera uma nova linha de extra na lista desejada.
     *
     * @param {string} listSelector Seletor CSS da lista de extras.
     */
    DPSSubscription.addExtraRow = function(listSelector) {
        var $list = $(listSelector);
        if ($list.length === 0) {
            return;
        }
        var index = $list.children('.dps-extra-row').length;
        var row = [
            '<div class="dps-extra-row" data-index="' + index + '">',
            '  <div class="dps-extra-row-fields">',
            '    <div class="dps-extra-description-field">',
            '      <input type="text" name="subscription_extras_descriptions[]" placeholder="' + dpsSubscriptionStrings.description + '" class="dps-extra-description-input" />',
            '    </div>',
            '    <div class="dps-extra-value-field">',
            '      <div class="dps-input-with-prefix">',
            '        <span class="dps-input-prefix">R$</span>',
            '        <input type="number" step="0.01" min="0" name="subscription_extras_values[]" placeholder="0,00" class="dps-extra-value-input" />',
            '      </div>',
            '    </div>',
            '    <button type="button" class="dps-btn dps-btn--icon dps-remove-extra-btn" title="' + dpsSubscriptionStrings.remove + '"><span>✕</span></button>',
            '  </div>',
            '</div>'
        ].join('');
        $list.append(row);
        $list.removeAttr('data-empty');
    };

    /**
     * Liga eventos de adicionar/remover extras.
     */
    DPSSubscription.bindExtras = function() {
        $(document).on('click', '.dps-add-extra-btn', function(event) {
            event.preventDefault();
            var listSelector = $(this).data('list');
            DPSSubscription.addExtraRow(listSelector);
        });

        $(document).on('click', '.dps-remove-extra-btn', function(event) {
            event.preventDefault();
            var $list = $(this).closest('.dps-extras-list');
            $(this).closest('.dps-extra-row').remove();
            if ($list.children('.dps-extra-row').length === 0) {
                $list.attr('data-empty', 'true');
            }
        });
    };

    /**
     * Inicializa quando o documento estiver pronto
     */
    $(document).ready(function() {
        DPSSubscription.init();
    });

})(jQuery);
