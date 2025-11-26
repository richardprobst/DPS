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
            this.togglePastPaymentValue();
            this.updateAppointmentSummary();
        },
        
        /**
         * Vincula eventos aos elementos do formulário
         */
        bindEvents: function() {
            const self = this;
            
            // Eventos de mudança de tipo de agendamento
            $(document).on('change', 'input[name="appointment_type"]', this.handleTypeChange.bind(this));
            $('#appointment_frequency, select[name="appointment_frequency"]').on('change', this.updateTosaOptions.bind(this));
            $('#dps-taxidog-toggle').on('change', this.toggleTaxiDog.bind(this));
            $('#dps-tosa-toggle').on('change', this.updateTosaFields.bind(this));
            
            // Eventos para agendamento passado
            $('#past_payment_status').on('change', this.togglePastPaymentValue.bind(this));
            
            // FASE 2: Eventos para atualização de resumo
            $('#dps-appointment-cliente').on('change', this.updateAppointmentSummary.bind(this));
            $(document).on('change', '.dps-pet-checkbox', this.updateAppointmentSummary.bind(this));
            $('#appointment_date').on('change', function() {
                self.loadAvailableTimes();
                self.updateAppointmentSummary();
            });
            $('#appointment_time').on('change', this.updateAppointmentSummary.bind(this));
            $('#dps-taxidog-toggle, #dps-tosa-toggle').on('change', this.updateAppointmentSummary.bind(this));
            $('#dps-taxidog-price, #dps-tosa-price').on('input', this.updateAppointmentSummary.bind(this));
            $('#appointment_notes').on('input', this.updateAppointmentSummary.bind(this));
            
            // Eventos para serviços do Services Add-on
            $(document).on('change', '.dps-service-checkbox', this.updateAppointmentSummary.bind(this));
            $(document).on('input', '.dps-service-price', this.updateAppointmentSummary.bind(this));
            
            // FASE 2: Validação e estado do botão submit
            $('form.dps-form').on('submit', this.handleFormSubmit.bind(this));
        },
        
        /**
         * Manipula mudança de tipo de agendamento
         */
        handleTypeChange: function() {
            this.updateTypeFields();
            this.updateTosaOptions();
            this.updateTosaFields();
            this.updateAppointmentSummary();
        },
        
        /**
         * Atualiza visibilidade de campos baseado no tipo de agendamento
         */
        updateTypeFields: function() {
            const type = $('input[name="appointment_type"]:checked').val();
            const isSubscription = (type === 'subscription');
            const isPast = (type === 'past');
            
            // Exibe ou oculta o seletor de frequência
            $('#dps-appointment-frequency-wrapper').toggle(isSubscription);
            
            // Exibe ou oculta campos de tosa somente nas assinaturas
            $('#dps-tosa-wrapper').toggle(isSubscription);
            
            // Exibe ou oculta campos de pagamento passado
            $('#dps-past-payment-wrapper').toggle(isPast);
            
            // Controla campos específicos de cada tipo
            $('.dps-simple-fields').toggle(!isSubscription && !isPast);
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
            
            if (type === 'subscription' || type === 'past') {
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
         * Alterna exibição do campo de valor do pagamento pendente
         */
        togglePastPaymentValue: function() {
            const status = $('#past_payment_status').val();
            $('#dps-past-payment-value-wrapper').toggle(status === 'pending');
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
        },
        
        /**
         * FASE 2: Atualiza o resumo dinâmico do agendamento
         */
        updateAppointmentSummary: function() {
            const $summary = $('.dps-appointment-summary');
            const $empty = $('.dps-appointment-summary__empty');
            const $list = $('.dps-appointment-summary__list');
            const appointmentType = $('input[name="appointment_type"]:checked').val() || 'simple';

            const parseCurrency = function(value, fallback) {
                if (value === undefined || value === null || value === '') {
                    return fallback || 0;
                }
                const normalized = String(value).replace(',', '.');
                const parsed = parseFloat(normalized);
                if (Number.isNaN(parsed)) {
                    return fallback || 0;
                }
                return parsed;
            };

            // Coleta dados do formulário
            const clientText = $('#dps-appointment-cliente option:selected').text();
            const clientId = $('#dps-appointment-cliente').val();

            const selectedPets = $('.dps-pet-checkbox:checked').map(function() {
                return $(this).closest('.dps-pet-option').find('.dps-pet-name').text();
            }).get();
            
            const date = $('#appointment_date').val();
            const time = $('#appointment_time').val();
            const notes = $('#appointment_notes').val();

            // Coleta serviços selecionados
            const services = [];
            let totalValue = 0;
            if ($('#dps-taxidog-toggle').is(':checked')) {
                const taxiPrice = parseCurrency($('#dps-taxidog-price').val());
                services.push('TaxiDog (R$ ' + taxiPrice.toFixed(2) + ')');
                totalValue += taxiPrice;
            }
            if ($('#dps-tosa-toggle').is(':checked')) {
                const tosaPrice = parseCurrency($('#dps-tosa-price').val(), 30);
                services.push('Tosa (R$ ' + tosaPrice.toFixed(2) + ')');
                totalValue += tosaPrice;
            }

            // Coleta serviços do Services Add-on (se existirem)
            if ($('.dps-service-checkbox').length > 0) {
                $('.dps-service-checkbox:checked').each(function() {
                    const checkbox = $(this);
                    const label = checkbox.closest('label');
                    const priceInput = label.find('.dps-service-price');
                    const sanitizedLabel = label.clone();
                    sanitizedLabel.find('.dps-service-price').remove();

                    // Extrai nome do serviço (texto antes do "(R$")
                    const fullText = sanitizedLabel.text().trim();
                    const serviceName = fullText.split('(R$')[0].trim();

                    const price = parseCurrency(priceInput.val());

                    if (serviceName) {
                        services.push(serviceName + ' (R$ ' + price.toFixed(2) + ')');
                    }
                    totalValue += price;
                });
            }

            if (appointmentType === 'subscription') {
                const baseValue = parseCurrency($('#dps-subscription-base').val());
                if (baseValue > 0) {
                    services.push('Assinatura base (R$ ' + baseValue.toFixed(2) + ')');
                    totalValue += baseValue;
                }

                const extraWrapper = $('#dps-subscription-extra-fields');
                const extraDesc = $('input[name="subscription_extra_description"]').val();
                const extraValue = parseCurrency($('#dps-subscription-extra-value').val());
                if (extraWrapper.length && extraWrapper.is(':visible') && extraValue > 0) {
                    const labelText = extraDesc && extraDesc.trim() !== '' ? extraDesc.trim() : 'Extra';
                    services.push(labelText + ' (R$ ' + extraValue.toFixed(2) + ')');
                    totalValue += extraValue;
                }
            } else {
                const extraWrapper = $('#dps-simple-extra-fields');
                const extraDesc = $('input[name="appointment_extra_description"]').val();
                const extraValue = parseCurrency($('#dps-simple-extra-value').val());
                if (extraWrapper.length && extraWrapper.is(':visible') && extraValue > 0) {
                    const labelText = extraDesc && extraDesc.trim() !== '' ? extraDesc.trim() : 'Extra';
                    services.push(labelText + ' (R$ ' + extraValue.toFixed(2) + ')');
                    totalValue += extraValue;
                }
            }

            // Verifica se campos mínimos estão preenchidos
            const hasMinimumData = clientId && selectedPets.length > 0 && date && time;
            
            if (hasMinimumData) {
                // Atualiza os valores no resumo
                $list.find('[data-summary="client"]').text(clientText);
                $list.find('[data-summary="pets"]').text(selectedPets.join(', '));
                
                // Formata a data para exibição
                const dateObj = new Date(date + 'T00:00:00');
                const dateFormatted = dateObj.toLocaleDateString('pt-BR');
                $list.find('[data-summary="date"]').text(dateFormatted);
                
                $list.find('[data-summary="time"]').text(time);
                $list.find('[data-summary="services"]').text(
                    services.length > 0 ? services.join(', ') : 'Nenhum serviço extra'
                );
                $list.find('[data-summary="price"]').text('R$ ' + totalValue.toFixed(2));
                
                // Atualiza observações (exibe somente se tiver conteúdo)
                if (notes && notes.trim() !== '') {
                    $list.find('[data-summary="notes"]').text(notes.trim());
                    $('.dps-appointment-summary__notes').show();
                } else {
                    $('.dps-appointment-summary__notes').hide();
                }
                
                // Mostra o resumo
                $empty.hide();
                $list.removeAttr('hidden');
            } else {
                // Esconde o resumo
                $empty.show();
                $list.attr('hidden', true);
            }
        },
        
        /**
         * FASE 2: Carrega horários disponíveis via AJAX
         */
        loadAvailableTimes: function() {
            const date = $('#appointment_date').val();
            const $timeSelect = $('#appointment_time');
            
            if (!date) {
                $timeSelect.html('<option value="">' + (dpsAppointmentData.l10n.selectTime || 'Escolha uma data primeiro') + '</option>');
                return;
            }
            
            // Mostra estado de carregamento
            $timeSelect.prop('disabled', true).html('<option>' + (dpsAppointmentData.l10n.loadingTimes || 'Carregando...') + '</option>');
            
            // Faz requisição AJAX
            $.ajax({
                url: dpsAppointmentData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'dps_get_available_times',
                    nonce: dpsAppointmentData.nonce,
                    date: date,
                    appointment_id: dpsAppointmentData.appointmentId || 0
                },
                success: function(response) {
                    if (response.success && response.data.times) {
                        let html = '<option value="">' + (dpsAppointmentData.l10n.selectTime || 'Selecione um horário') + '</option>';
                        
                        const times = response.data.times;
                        let hasAvailable = false;
                        
                        times.forEach(function(timeObj) {
                            if (timeObj.available) {
                                html += '<option value="' + timeObj.value + '">' + timeObj.label + '</option>';
                                hasAvailable = true;
                            }
                        });
                        
                        if (!hasAvailable) {
                            html = '<option value="">' + (dpsAppointmentData.l10n.noTimes || 'Nenhum horário disponível') + '</option>';
                        }
                        
                        $timeSelect.html(html).prop('disabled', false);
                    } else {
                        $timeSelect.html('<option value="">' + (dpsAppointmentData.l10n.loadError || 'Erro ao carregar horários') + '</option>').prop('disabled', false);
                    }
                },
                error: function() {
                    $timeSelect.html('<option value="">' + (dpsAppointmentData.l10n.loadError || 'Erro ao carregar horários') + '</option>').prop('disabled', false);
                }
            });
        },
        
        /**
         * FASE 2: Validação do formulário antes do submit
         */
        validateForm: function() {
            const errors = [];
            
            // Valida cliente
            const clientId = $('#dps-appointment-cliente').val();
            if (!clientId) {
                errors.push(dpsAppointmentData.l10n.selectClient || 'Selecione um cliente');
            }
            
            // Valida pets (pelo menos 1)
            const selectedPets = $('.dps-pet-checkbox:checked').length;
            if (selectedPets === 0) {
                errors.push(dpsAppointmentData.l10n.selectPet || 'Selecione pelo menos um pet');
            }
            
            // Valida data
            const date = $('#appointment_date').val();
            if (!date) {
                errors.push(dpsAppointmentData.l10n.selectDate || 'Selecione uma data');
            } else {
                // Verifica se não é data passada
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                const selectedDate = new Date(date + 'T00:00:00');
                
                if (selectedDate < today) {
                    errors.push(dpsAppointmentData.l10n.pastDate || 'A data não pode ser anterior a hoje');
                }
            }
            
            // Valida horário
            const time = $('#appointment_time').val();
            if (!time) {
                errors.push(dpsAppointmentData.l10n.selectTimeSlot || 'Selecione um horário');
            }
            
            return errors;
        },
        
        /**
         * FASE 2: Manipula o submit do formulário
         */
        handleFormSubmit: function(event) {
            // Limpa erros anteriores
            const $errorBlock = $('.dps-form-error');
            $errorBlock.attr('hidden', true).empty();
            
            // Valida formulário
            const errors = this.validateForm();
            
            if (errors.length > 0) {
                event.preventDefault();
                
                // Mostra erros
                let errorHtml = '<strong>' + (dpsAppointmentData.l10n.formErrorsTitle || 'Por favor, corrija os seguintes erros:') + '</strong><ul>';
                errors.forEach(function(error) {
                    errorHtml += '<li>' + error + '</li>';
                });
                errorHtml += '</ul>';
                
                $errorBlock.html(errorHtml).removeAttr('hidden');
                
                // Scroll suave para o topo do formulário
                $('html, body').animate({
                    scrollTop: $('form.dps-form').offset().top - 20
                }, 300);
                
                return false;
            }
            
            // Se validação passou, desabilita botão e mostra estado "Salvando..."
            const $submitBtn = $('.dps-appointment-submit');
            const originalText = $submitBtn.text();
            
            $submitBtn.prop('disabled', true)
                      .data('original-text', originalText)
                      .text(dpsAppointmentData.l10n.saving || 'Salvando...');
            
            // Reabilita botão após 5 segundos como fallback
            setTimeout(function() {
                $submitBtn.prop('disabled', false).text(originalText);
            }, 5000);
        }
    };
    
    // Inicializa quando o documento estiver pronto
    $(document).ready(function() {
        if ($('form.dps-form input[name="appointment_type"]').length) {
            DPSAppointmentForm.init();
        }
    });
    
})(jQuery);
