/**
 * JavaScript do Add-on de Assinaturas
 *
 * @package Desi_Pet_Shower_Subscription
 * @since 1.1.0
 * @updated 1.3.0 - Suporte a múltiplos pets via checkboxes, prevenção de duplo clique, feedback visual
 */

(function($) {
    'use strict';

    window.DPSSubscription = window.DPSSubscription || {};
    window.dpsSubscriptionStrings = window.dpsSubscriptionStrings || {
        description: 'Descrição do serviço',
        remove: 'Remover',
        saving: 'Salvando...',
        save_changes: 'Salvar Alterações',
        confirm_cancel: 'Tem certeza que deseja cancelar esta assinatura?',
        confirm_delete: 'Tem certeza que deseja excluir permanentemente? Esta ação não pode ser desfeita.',
        required_fields: 'Por favor, preencha todos os campos obrigatórios.',
        invalid_date: 'Por favor, insira uma data válida.',
        invalid_time: 'Por favor, insira um horário válido.',
        updating_status: 'Atualizando...',
        select_pet: 'Selecione pelo menos um pet.'
    };

    /**
     * Filtra pets pelo cliente selecionado no formulário de assinatura.
     * Oculta pets que não pertencem ao cliente.
     * Suporta tanto select único (legado) quanto checkboxes (novo).
     *
     * @param {string} ownerId ID do cliente proprietário
     */
    DPSSubscription.filterPetsByClient = function(ownerId) {
        // Filtra checkboxes de pets
        var $checkboxList = $('#dps-subscription-pets-list');
        if ($checkboxList.length > 0) {
            if (!ownerId) {
                $checkboxList.find('.dps-checkbox-item').hide();
                $checkboxList.find('input[type="checkbox"]').prop('checked', false);
            } else {
                $checkboxList.find('.dps-checkbox-item').each(function() {
                    var $item = $(this);
                    var itemOwner = $item.attr('data-owner');
                    
                    if (String(itemOwner) === String(ownerId)) {
                        $item.show();
                    } else {
                        $item.hide();
                        $item.find('input[type="checkbox"]').prop('checked', false);
                    }
                });
            }
            DPSSubscription.updatePetsCount();
            return;
        }
        
        // Fallback: Filtra select de pets (legado)
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
     * Atualiza o contador de pets selecionados
     */
    DPSSubscription.updatePetsCount = function() {
        var count = $('#dps-subscription-pets-list input[type="checkbox"]:checked:visible').length;
        $('#dps-selected-pets-count').text(count);
    };

    /**
     * Toggle campos de tosa baseado no checkbox
     */
    DPSSubscription.toggleTosaFields = function() {
        var isChecked = $('#subscription_tosa').is(':checked');
        if (isChecked) {
            $('.dps-tosa-conditional').slideDown(200);
        } else {
            $('.dps-tosa-conditional').slideUp(200);
        }
    };

    /**
     * Valida o formulário de assinatura antes do envio
     * 
     * @param {jQuery} $form Formulário a validar
     * @return {boolean} True se válido
     */
    DPSSubscription.validateForm = function($form) {
        var isValid = true;
        var $requiredFields = $form.find('[required]');
        
        // Remove marcações de erro anteriores
        $form.find('.dps-field-error').removeClass('dps-field-error');
        $form.find('.dps-error-message').remove();
        
        $requiredFields.each(function() {
            var $field = $(this);
            var value = $field.val();
            
            if (!value || value === '') {
                $field.addClass('dps-field-error');
                isValid = false;
            }
        });
        
        // Valida que pelo menos um pet foi selecionado (checkboxes)
        var $petCheckboxes = $form.find('input[name="subscription_pet_ids[]"]:checked:visible');
        if ($petCheckboxes.length === 0) {
            // Verifica se temos o select de pet único (legado)
            var $petSelect = $form.find('select[name="subscription_pet_id"]');
            if ($petSelect.length > 0 && !$petSelect.val()) {
                $petSelect.addClass('dps-field-error');
                isValid = false;
            } else if ($petCheckboxes.closest('#dps-subscription-pets-list').length > 0) {
                // Temos checkboxes mas nenhum selecionado
                $('#dps-subscription-pets-list').addClass('dps-field-error');
                isValid = false;
            }
        }
        
        // Valida formato de data (Y-m-d)
        var $dateField = $form.find('input[name="subscription_start_date"]');
        if ($dateField.length && $dateField.val()) {
            var dateVal = $dateField.val();
            if (!/^\d{4}-\d{2}-\d{2}$/.test(dateVal)) {
                $dateField.addClass('dps-field-error');
                isValid = false;
            }
        }
        
        // Valida formato de horário (H:i)
        var $timeField = $form.find('input[name="subscription_start_time"]');
        if ($timeField.length && $timeField.val()) {
            var timeVal = $timeField.val();
            if (!/^\d{2}:\d{2}$/.test(timeVal)) {
                $timeField.addClass('dps-field-error');
                isValid = false;
            }
        }
        
        if (!isValid) {
            // Exibe mensagem de erro
            var $firstError = $form.find('.dps-field-error').first();
            $firstError.focus();
            
            // Scroll para o campo com erro
            if ($firstError.length) {
                $('html, body').animate({
                    scrollTop: $firstError.offset().top - 100
                }, 300);
            }
        }
        
        return isValid;
    };

    /**
     * Desabilita botão durante o envio para prevenir duplo clique
     * 
     * @param {jQuery} $button Botão a desabilitar
     * @param {boolean} disable True para desabilitar
     */
    DPSSubscription.toggleSubmitButton = function($button, disable) {
        if (disable) {
            $button.prop('disabled', true)
                   .addClass('dps-btn-loading')
                   .data('original-text', $button.find('span:last').text())
                   .find('span:last').text(dpsSubscriptionStrings.saving);
        } else {
            var originalText = $button.data('original-text') || dpsSubscriptionStrings.save_changes;
            $button.prop('disabled', false)
                   .removeClass('dps-btn-loading')
                   .find('span:last').text(originalText);
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
        DPSSubscription.bindExtras();
        DPSSubscription.bindFormSubmit();
        DPSSubscription.bindPaymentStatusChange();
        DPSSubscription.bindConfirmActions();
        DPSSubscription.bindPetCheckboxes();

        var $clientSelect = $('select[name="subscription_client_id"]');
        
        if ($clientSelect.length === 0) {
            return; // Não estamos na página de assinaturas com formulário
        }
        
        var initialClient = $clientSelect.val();
        
        // Aplica filtro inicial para checkboxes de pets
        if (initialClient) {
            DPSSubscription.filterPetsByClient(initialClient);
        } else {
            // Oculta todos os pets se nenhum cliente selecionado
            $('#dps-subscription-pets-list .dps-checkbox-item').hide();
            $('#dps-subscription-pet-select').closest('.dps-form-field').hide();
        }
        
        // Evento de mudança de cliente
        $clientSelect.on('change', function() {
            DPSSubscription.filterPetsByClient($(this).val());
        });
        
        // Inicializa contagem de pets
        DPSSubscription.updatePetsCount();
    };

    /**
     * Vincula eventos para checkboxes de pets
     */
    DPSSubscription.bindPetCheckboxes = function() {
        $(document).on('change', '#dps-subscription-pets-list input[type="checkbox"]', function() {
            DPSSubscription.updatePetsCount();
            // Remove classe de erro quando pelo menos um pet é selecionado
            if ($('#dps-subscription-pets-list input[type="checkbox"]:checked:visible').length > 0) {
                $('#dps-subscription-pets-list').removeClass('dps-field-error');
            }
        });
    };

    /**
     * Vincula handler de submit do formulário com validação e prevenção de duplo clique
     */
    DPSSubscription.bindFormSubmit = function() {
        $(document).on('submit', '.dps-subscription-form', function(event) {
            var $form = $(this);
            var $submitBtn = $form.find('.dps-btn-submit');
            
            // Previne duplo clique
            if ($submitBtn.prop('disabled')) {
                event.preventDefault();
                return false;
            }
            
            // Valida formulário
            if (!DPSSubscription.validateForm($form)) {
                event.preventDefault();
                return false;
            }
            
            // Desabilita botão durante envio
            DPSSubscription.toggleSubmitButton($submitBtn, true);
            
            return true;
        });
    };

    /**
     * Vincula handler para mudança de status de pagamento com feedback visual
     */
    DPSSubscription.bindPaymentStatusChange = function() {
        $(document).on('change', '.dps-select-payment', function() {
            var $select = $(this);
            var $form = $select.closest('form');
            
            // Previne múltiplas submissões
            if ($select.prop('disabled')) {
                return false;
            }
            
            // Desabilita e mostra feedback
            $select.prop('disabled', true);
            $form.addClass('dps-form-loading');
            
            // Submit é feito pelo onchange no HTML - a classe visual indica loading
        });
    };

    /**
     * Vincula confirmações para ações destrutivas
     */
    DPSSubscription.bindConfirmActions = function() {
        // Confirmações já estão inline nos links via onclick
        // Este método pode ser expandido para modais de confirmação mais elaborados
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
        
        // Foca no novo campo
        $list.find('.dps-extra-row:last .dps-extra-description-input').focus();
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
