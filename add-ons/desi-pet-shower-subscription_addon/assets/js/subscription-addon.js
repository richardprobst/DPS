/**
 * JavaScript do Add-on de Assinaturas
 *
 * @package Desi_Pet_Shower_Subscription
 * @since 1.1.0
 */

(function($) {
    'use strict';

    window.DPSSubscription = window.DPSSubscription || {};

    /**
     * Filtra pets pelo cliente selecionado no formulário de assinatura.
     * Oculta pets que não pertencem ao cliente e limpa seleção se necessário.
     *
     * @param {string} ownerId ID do cliente proprietário
     */
    DPSSubscription.filterPetsByClient = function(ownerId) {
        var $select = $('#dps-subscription-pet-select');
        var $container = $select.closest('p');
        
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
        var $clientSelect = $('select[name="subscription_client_id"]');
        
        if ($clientSelect.length === 0) {
            return; // Não estamos na página de assinaturas
        }
        
        var initialClient = $clientSelect.val();
        
        // Aplica filtro inicial
        if (initialClient) {
            DPSSubscription.filterPetsByClient(initialClient);
        } else {
            $('#dps-subscription-pet-select').closest('p').hide();
        }
        
        // Evento de mudança de cliente
        $clientSelect.on('change', function() {
            DPSSubscription.filterPetsByClient($(this).val());
        });
    };

    /**
     * Inicializa quando o documento estiver pronto
     */
    $(document).ready(function() {
        DPSSubscription.init();
    });

})(jQuery);
