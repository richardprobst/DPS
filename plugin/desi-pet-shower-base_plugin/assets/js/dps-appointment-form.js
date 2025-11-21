/**
 * Formulário de Agendamento - Lógica de campos condicionais e validação
 * 
 * @since 1.0.0
 */

(function($) {
    'use strict';
    
    const DPSAppointmentForm = {
        /**
         * Inicializa o formulário
         */
        init: function() {
            this.bindEvents();
            this.updateTypeFields();
            this.updateTosaOptions();
            this.updateTosaFields();
        },
        
        /**
         * Vincula eventos aos elementos do formulário
         */
        bindEvents: function() {
            $(document).on('change', 'input[name="appointment_type"]', this.handleTypeChange.bind(this));
            $('#appointment_frequency, select[name="appointment_frequency"]').on('change', this.updateTosaOptions.bind(this));
            $('#dps-taxidog-toggle').on('change', this.toggleTaxiDog.bind(this));
            $('#dps-tosa-toggle').on('change', this.updateTosaFields.bind(this));
        },
        
        /**
         * Manipula mudança de tipo de agendamento
         */
        handleTypeChange: function() {
            this.updateTypeFields();
            this.updateTosaOptions();
            this.updateTosaFields();
        },
        
        /**
         * Atualiza visibilidade de campos baseado no tipo de agendamento
         */
        updateTypeFields: function() {
            const type = $('input[name="appointment_type"]:checked').val();
            const isSubscription = (type === 'subscription');
            
            // Exibe ou oculta o seletor de frequência
            $('#dps-appointment-frequency-wrapper').toggle(isSubscription);
            
            // Exibe ou oculta campos de tosa somente nas assinaturas
            $('#dps-tosa-wrapper').toggle(isSubscription);
            
            // Controla campos específicos de cada tipo
            $('.dps-simple-fields').toggle(!isSubscription);
            $('.dps-subscription-fields').toggle(isSubscription);
            
            // Atualiza campos de TaxiDog
            this.toggleTaxiDog();
        },
        
        /**
         * Alterna exibição do campo de preço do TaxiDog
         */
        toggleTaxiDog: function() {
            const type = $('input[name="appointment_type"]:checked').val();
            const hasTaxi = $('#dps-taxidog-toggle').is(':checked');
            
            if (type === 'subscription') {
                $('#dps-taxidog-extra').hide();
            } else {
                $('#dps-taxidog-extra').toggle(hasTaxi);
            }
        },
        
        /**
         * Alterna exibição dos campos de tosa
         */
        updateTosaFields: function() {
            const show = $('#dps-tosa-toggle').is(':checked');
            $('#dps-tosa-fields').toggle(show);
        },
        
        /**
         * Atualiza opções de ocorrência da tosa conforme a frequência selecionada
         */
        updateTosaOptions: function() {
            const freq = $('select[name="appointment_frequency"]').val() || 'semanal';
            const select = $('#appointment_tosa_occurrence');
            
            if (!select.length) {
                return;
            }
            
            const occurrences = (freq === 'quinzenal') ? 2 : 4;
            const current = select.data('current');
            
            select.empty();
            for (let i = 1; i <= occurrences; i++) {
                select.append('<option value="' + i + '">' + i + 'º Atendimento</option>');
            }
            
            if (current && current <= occurrences) {
                select.val(current);
            }
        }
    };
    
    // Inicializa quando o documento estiver pronto
    $(document).ready(function() {
        if ($('form.dps-form input[name="appointment_type"]').length) {
            DPSAppointmentForm.init();
        }
    });
    
})(jQuery);
