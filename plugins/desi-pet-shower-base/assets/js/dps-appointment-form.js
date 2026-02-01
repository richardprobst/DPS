/**
 * Formulário de Agendamento - Lógica de campos condicionais e validação
 * 
 * @since 1.0.0
 */

(function($) {
    'use strict';
    
    /**
     * Escapa caracteres HTML para prevenir XSS
     * @param {string} text - Texto a ser escapado
     * @returns {string} Texto com caracteres HTML escapados
     */
    function escapeHtml(text) {
        if (typeof text !== 'string') {
            return '';
        }
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    const DPSAppointmentForm = {
        eventsBound: false,
        /**
         * Inicializa o formulário
         */
        init: function() {
            if ( ! this.eventsBound ) {
                this.bindEvents();
                this.eventsBound = true;
            }
            this.updateTypeFields();
            this.updateTosaOptions();
            this.updateTosaFields();
            this.togglePastPaymentValue();
            this.updateAppointmentSummary();
            this.updateConsentStatus();
            this.updateConsentWarning();
        },
        
        /**
         * Vincula eventos aos elementos do formulário
         */
        bindEvents: function() {
            const self = this;
            
            // Eventos de mudança de tipo de agendamento
            $(document).on('change', 'input[name="appointment_type"]', this.handleTypeChange.bind(this));
            $(document).on('change', '#appointment_frequency, select[name="appointment_frequency"]', function() {
                self.updateTosaOptions();
                self.updateAppointmentSummary();
            });
            $(document).on('change', '#dps-taxidog-toggle', this.toggleTaxiDog.bind(this));
            $(document).on('change', '#dps-tosa-toggle', this.updateTosaFields.bind(this));
            
            // Eventos para agendamento passado
            $(document).on('change', '#past_payment_status', this.togglePastPaymentValue.bind(this));
            
            // FASE 2: Eventos para atualização de resumo
            $(document).on('change', '#dps-appointment-cliente', this.updateAppointmentSummary.bind(this));
            $(document).on('change', '#dps-appointment-cliente', this.updateConsentStatus.bind(this));
            $(document).on('change', '#dps-appointment-cliente', this.updateConsentWarning.bind(this));
            $(document).on('change', '.dps-pet-checkbox', this.updateAppointmentSummary.bind(this));
            $(document).on('change', '#appointment_date', function() {
                self.loadAvailableTimes();
                self.updateAppointmentSummary();
            });
            $(document).on('change', '#appointment_time', this.updateAppointmentSummary.bind(this));
            $(document).on('change', '#dps-taxidog-toggle, #dps-tosa-toggle', this.updateAppointmentSummary.bind(this));
            $(document).on('change', '#dps-tosa-toggle', this.updateConsentWarning.bind(this));
            $(document).on('input', '#dps-taxidog-price, #dps-tosa-price', this.updateAppointmentSummary.bind(this));
            $(document).on('input', '#appointment_notes', this.updateAppointmentSummary.bind(this));
            
            // Eventos para serviços do Services Add-on
            $(document).on('change', '.dps-service-checkbox', this.updateAppointmentSummary.bind(this));
            $(document).on('change', '.dps-service-checkbox', this.updateConsentWarning.bind(this));
            $(document).on('input', '.dps-service-price', this.updateAppointmentSummary.bind(this));
            
            // Eventos para valores de assinatura por pet
            $(document).on('input', '.dps-subscription-pet-value', this.updateAppointmentSummary.bind(this));
            
            // Eventos para extras (novo formato múltiplos)
            $(document).on('input', '.dps-extra-description-input, .dps-extra-value-input', this.updateAppointmentSummary.bind(this));
            $(document).on('input', '.dps-discount-description-input, .dps-discount-value-input', this.updateAppointmentSummary.bind(this));
            $(document).on('click', '.dps-extras-toggle, .dps-add-extra-btn, .dps-remove-extra-btn, .dps-discount-toggle', function() {
                // Delay para aguardar animação de toggle
                setTimeout(function() { DPSAppointmentForm.updateAppointmentSummary(); }, 250);
            });
            
            // FASE 2: Validação e estado do botão submit
            // Aplica somente ao form de agendamento (que contém appointment_type), não a outros forms .dps-form
            $(document).on('submit', 'form.dps-form', function(event) {
                if ( $(this).find('input[name="appointment_type"]').length ) {
                    DPSAppointmentForm.handleFormSubmit.call(DPSAppointmentForm, event);
                }
            });
        },
        
        /**
         * Manipula mudança de tipo de agendamento
         */
        handleTypeChange: function() {
            this.updateTypeFields();
            this.updateTosaOptions();
            this.updateTosaFields();
            this.updateAppointmentSummary();
            this.updateConsentWarning();
        },
        
        /**
         * Atualiza visibilidade de campos baseado no tipo de agendamento
         */
        updateTypeFields: function() {
            const type = $('input[name="appointment_type"]:checked').val();
            const isSubscription = (type === 'subscription');
            const isPast = (type === 'past');
            const isSimple = (type === 'simple');
            
            // Exibe ou oculta o seletor de frequência
            $('#dps-appointment-frequency-wrapper').toggle(isSubscription);
            
            // Exibe ou oculta campos de tosa somente nas assinaturas
            $('#dps-tosa-wrapper').toggle(isSubscription);
            
            // Exibe ou oculta campos de pagamento passado
            $('#dps-past-payment-wrapper').toggle(isPast);
            
            // Controla campos específicos de cada tipo
            // "Past" appointments should have the same fields as "Simple" appointments
            $('.dps-simple-fields').toggle(isSimple || isPast);
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
            const $card = $('.dps-taxidog-card');
            
            // TaxiDog price field is available for simple and past appointments
            if (type === 'subscription') {
                $('#dps-taxidog-extra').hide();
                $card.attr('data-taxidog-active', '0');
            } else {
                $('#dps-taxidog-extra').toggle(hasTaxi);
                $card.attr('data-taxidog-active', hasTaxi ? '1' : '0');
            }
        },
        
        /**
         * Alterna exibição dos campos de tosa e atualiza o estado visual do card
         */
        updateTosaFields: function() {
            const show = $('#dps-tosa-toggle').is(':checked');
            const $card = $('.dps-tosa-card');
            
            if (show) {
                $('#dps-tosa-fields').slideDown(200);
                $card.attr('data-tosa-active', '1');
            } else {
                $('#dps-tosa-fields').slideUp(200);
                $card.attr('data-tosa-active', '0');
            }
        },

        /**
         * Obtém status de consentimento do cliente selecionado
         */
        getSelectedConsentStatus: function() {
            const $select = $('#dps-appointment-cliente');
            const $option = $select.find('option:selected');
            return {
                status: $option.data('consent-status') || 'missing',
                date: $option.data('consent-date') || ''
            };
        },

        /**
         * Atualiza badge de consentimento no formulário
         */
        updateConsentStatus: function() {
            const $status = $('#dps-client-consent-status');
            if (!$status.length) {
                return;
            }

            const clientSelected = $('#dps-appointment-cliente').val();
            if (!clientSelected) {
                $status.hide().attr('aria-hidden', 'true');
                return;
            }

            const consent = this.getSelectedConsentStatus();
            const badge = $status.find('.dps-consent-badge');
            let note = $status.find('.dps-consent-status__note');
            if (!note.length) {
                note = $('<span class="dps-consent-status__note"></span>');
                $status.append(note);
            }

            let label = dpsAppointmentData.l10n.tosaConsentMissing || 'Consentimento tosa máquina pendente';
            let badgeClass = 'dps-consent-badge--missing';

            if (consent.status === 'granted') {
                label = dpsAppointmentData.l10n.tosaConsentOk || 'Consentimento tosa máquina ativo';
                badgeClass = 'dps-consent-badge--ok';
            } else if (consent.status === 'revoked') {
                label = dpsAppointmentData.l10n.tosaConsentRevoked || 'Consentimento tosa máquina revogado';
                badgeClass = 'dps-consent-badge--danger';
            }

            badge.text(label)
                .removeClass('dps-consent-badge--ok dps-consent-badge--missing dps-consent-badge--danger')
                .addClass(badgeClass);

            if (consent.date) {
                const noteLabel = (consent.status === 'granted')
                    ? (dpsAppointmentData.l10n.tosaConsentSignedAt || 'Assinado em %s')
                    : (dpsAppointmentData.l10n.tosaConsentRevokedAt || 'Revogado em %s');
                note.text(noteLabel.replace('%s', consent.date)).show();
            } else {
                note.hide();
            }

            $status.show().attr('aria-hidden', 'false');
        },

        /**
         * Verifica se o agendamento requer consentimento de tosa máquina
         */
        appointmentRequiresConsent: function() {
            if ($('#dps-tosa-toggle').length && $('#dps-tosa-toggle').is(':checked')) {
                return true;
            }

            let requires = false;
            $('.dps-service-checkbox:checked').each(function() {
                if ($(this).data('consent') === 'tosa_maquina') {
                    requires = true;
                }
            });

            return requires;
        },

        /**
         * Atualiza alerta de consentimento quando necessário
         */
        updateConsentWarning: function() {
            const $warning = $('#dps-consent-warning');
            if (!$warning.length) {
                return;
            }

            const consent = this.getSelectedConsentStatus();
            const requires = this.appointmentRequiresConsent();
            const shouldShow = requires && consent.status !== 'granted';

            if (shouldShow) {
                $warning.show().attr('aria-hidden', 'false');
            } else {
                $warning.hide().attr('aria-hidden', 'true');
            }
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

            // Converte tamanho do pet para chave interna
            const normalizePetSize = function(sizeAttr) {
                if (typeof sizeAttr !== 'string') {
                    return null;
                }
                sizeAttr = sizeAttr.toLowerCase();
                if (sizeAttr === 'pequeno') {
                    return 'small';
                } else if (sizeAttr === 'medio' || sizeAttr === 'médio') {
                    return 'medium';
                } else if (sizeAttr === 'grande') {
                    return 'large';
                }
                return null;
            };

            // Obtém preço do serviço para um porte específico
            const getServicePriceForSize = function($checkbox, size) {
                const defaultPrice = $checkbox.data('price-default');
                const priceSmall = $checkbox.data('price-small');
                const priceMedium = $checkbox.data('price-medium');
                const priceLarge = $checkbox.data('price-large');
                let price = defaultPrice;

                if (size === 'small' && priceSmall !== undefined && priceSmall !== null && priceSmall !== '') {
                    price = priceSmall;
                } else if (size === 'medium' && priceMedium !== undefined && priceMedium !== null && priceMedium !== '') {
                    price = priceMedium;
                } else if (size === 'large' && priceLarge !== undefined && priceLarge !== null && priceLarge !== '') {
                    price = priceLarge;
                }

                return parseCurrency(price);
            };

            // Coleta dados do formulário
            const clientText = $('#dps-appointment-cliente option:selected').text();
            const clientId = $('#dps-appointment-cliente').val();

            // Coleta pets selecionados com seus portes
            const selectedPetsData = [];
            $('.dps-pet-checkbox:checked').each(function() {
                const $checkbox = $(this);
                const $option = $checkbox.closest('.dps-pet-option');
                const name = $option.find('.dps-pet-name').text().trim();
                const sizeAttr = $option.data('size') || $option.attr('data-size') || '';
                const size = normalizePetSize(sizeAttr);
                const sizeLabel = sizeAttr ? sizeAttr.charAt(0).toUpperCase() + sizeAttr.slice(1).toLowerCase() : '';
                
                selectedPetsData.push({
                    id: $checkbox.val(),
                    name: name,
                    size: size,
                    sizeLabel: sizeLabel
                });
            });

            const selectedPetNames = selectedPetsData.map(function(p) {
                return p.sizeLabel ? p.name + ' (' + p.sizeLabel + ')' : p.name;
            });
            
            const date = $('#appointment_date').val();
            const time = $('#appointment_time').val();
            const notes = $('#appointment_notes').val();

            // Coleta serviços selecionados e calcula valores por pet
            const services = [];
            let totalValue = 0;
            const petBreakdown = []; // Detalhamento por pet

            // TaxiDog e Tosa são valores únicos (não por pet)
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

            // Coleta serviços do Services Add-on considerando múltiplos pets
            if ($('.dps-service-checkbox').length > 0) {
                const petCount = selectedPetsData.length;
                
                if (petCount > 1) {
                    // Múltiplos pets: calcula valor por pet
                    selectedPetsData.forEach(function(pet) {
                        let petTotal = 0;
                        const petServices = [];
                        
                        $('.dps-service-checkbox:checked').each(function() {
                            const $checkbox = $(this);
                            const label = $checkbox.closest('label');
                            const sanitizedLabel = label.clone();
                            sanitizedLabel.find('.dps-service-price').remove();
                            const fullText = sanitizedLabel.text().trim();
                            const serviceName = fullText.split('(R$')[0].trim();
                            
                            const price = getServicePriceForSize($checkbox, pet.size);
                            petTotal += price;
                            petServices.push(serviceName);
                        });
                        
                        if (petServices.length > 0) {
                            petBreakdown.push({
                                pet: pet,
                                total: petTotal,
                                services: petServices
                            });
                            totalValue += petTotal;
                        }
                    });
                    
                    // Adiciona os serviços resumidos (para múltiplos pets mostramos no breakdown)
                    const uniqueServices = [];
                    $('.dps-service-checkbox:checked').each(function() {
                        const $checkbox = $(this);
                        const label = $checkbox.closest('label');
                        const sanitizedLabel = label.clone();
                        sanitizedLabel.find('.dps-service-price').remove();
                        const fullText = sanitizedLabel.text().trim();
                        const serviceName = fullText.split('(R$')[0].trim();
                        if (serviceName && uniqueServices.indexOf(serviceName) === -1) {
                            uniqueServices.push(serviceName);
                        }
                    });
                    if (uniqueServices.length > 0) {
                        services.push(uniqueServices.join(', ') + ' (x' + petCount + ' pets)');
                    }
                } else {
                    // Pet único: comportamento original
                    $('.dps-service-checkbox:checked').each(function() {
                        const $checkbox = $(this);
                        const label = $checkbox.closest('label');
                        const priceInput = label.find('.dps-service-price');
                        const sanitizedLabel = label.clone();
                        sanitizedLabel.find('.dps-service-price').remove();

                        const fullText = sanitizedLabel.text().trim();
                        const serviceName = fullText.split('(R$')[0].trim();

                        const price = parseCurrency(priceInput.val());

                        if (serviceName) {
                            services.push(serviceName + ' (R$ ' + price.toFixed(2) + ')');
                        }
                        totalValue += price;
                    });
                }
            }

            if (appointmentType === 'subscription') {
                // Prioriza valores por pet se existirem
                const $petValues = $('.dps-subscription-pet-value');
                if ($petValues.length > 0) {
                    let petTotalValue = 0;
                    const petValuesBreakdown = [];
                    $petValues.each(function() {
                        const $row = $(this).closest('.dps-subscription-pet-value-row');
                        const petName = $row.find('.dps-subscription-pet-name').text().trim();
                        const val = parseCurrency($(this).val());
                        if (val > 0) {
                            petTotalValue += val;
                            petValuesBreakdown.push(petName + ': R$ ' + val.toFixed(2));
                        }
                    });
                    if (petTotalValue > 0) {
                        services.push('Assinatura por pet (' + petValuesBreakdown.join(', ') + ')');
                        totalValue += petTotalValue;
                    }
                } else {
                    // Fallback para campo base único
                    const baseValue = parseCurrency($('#dps-subscription-base').val());
                    if (baseValue > 0) {
                        services.push('Assinatura base (R$ ' + baseValue.toFixed(2) + ')');
                        totalValue += baseValue;
                    }
                }

                // Novo formato: múltiplos extras
                if ($('#dps-subscription-extras-container').is(':visible')) {
                    $('#dps-subscription-extras-list .dps-extra-row').each(function() {
                        const desc = $(this).find('.dps-extra-description-input').val();
                        const val = parseCurrency($(this).find('.dps-extra-value-input').val());
                        if (val > 0) {
                            const labelText = desc && desc.trim() !== '' ? desc.trim() : 'Extra';
                            services.push(labelText + ' (R$ ' + val.toFixed(2) + ')');
                            totalValue += val;
                        }
                    });
                }
                // Compatibilidade: formato antigo
                const extraWrapper = $('#dps-subscription-extra-fields');
                const extraDesc = $('input[name="subscription_extra_description"]').val();
                const extraValue = parseCurrency($('#dps-subscription-extra-value').val());
                if (extraWrapper.length && extraWrapper.is(':visible') && extraValue > 0) {
                    const labelText = extraDesc && extraDesc.trim() !== '' ? extraDesc.trim() : 'Extra';
                    services.push(labelText + ' (R$ ' + extraValue.toFixed(2) + ')');
                    totalValue += extraValue;
                }
            } else {
                // Novo formato: múltiplos extras
                if ($('#dps-simple-extras-container').is(':visible')) {
                    $('#dps-simple-extras-list .dps-extra-row').each(function() {
                        const desc = $(this).find('.dps-extra-description-input').val();
                        const val = parseCurrency($(this).find('.dps-extra-value-input').val());
                        if (val > 0) {
                            const labelText = desc && desc.trim() !== '' ? desc.trim() : 'Extra';
                            services.push(labelText + ' (R$ ' + val.toFixed(2) + ')');
                            totalValue += val;
                        }
                    });
                }
                // Compatibilidade: formato antigo
                const extraWrapper = $('#dps-simple-extra-fields');
                const extraDesc = $('input[name="appointment_extra_description"]').val();
                const extraValue = parseCurrency($('#dps-simple-extra-value').val());
                if (extraWrapper.length && extraWrapper.is(':visible') && extraValue > 0) {
                    const labelText = extraDesc && extraDesc.trim() !== '' ? extraDesc.trim() : 'Extra';
                    services.push(labelText + ' (R$ ' + extraValue.toFixed(2) + ')');
                    totalValue += extraValue;
                }
            }
            
            // Calcula desconto se aplicável
            let discountValue = 0;
            if ($('#dps-discount-container').is(':visible')) {
                discountValue = parseCurrency($('.dps-discount-value-input').val());
                if (discountValue > 0) {
                    const discountDesc = $('input[name="appointment_discount_description"]').val();
                    const discountLabel = discountDesc && discountDesc.trim() !== '' ? discountDesc.trim() : 'Desconto';
                    services.push(discountLabel + ' (- R$ ' + discountValue.toFixed(2) + ')');
                }
            }
            
            // Aplica o desconto ao total
            totalValue = Math.max(0, totalValue - discountValue);

            // Verifica se campos mínimos estão preenchidos
            const hasMinimumData = clientId && selectedPetsData.length > 0 && date && time;
            
            if (hasMinimumData) {
                // Atualiza os valores no resumo
                $list.find('[data-summary="client"]').text(clientText);
                $list.find('[data-summary="pets"]').text(selectedPetNames.join(', '));
                
                // Formata a data para exibição
                const dateObj = new Date(date + 'T00:00:00');
                const dateFormatted = dateObj.toLocaleDateString('pt-BR');
                $list.find('[data-summary="date"]').text(dateFormatted);
                
                $list.find('[data-summary="time"]').text(time);
                
                // Subscription-specific: show frequency and future dates
                if (appointmentType === 'subscription') {
                    const frequency = $('select[name="appointment_frequency"]').val() || 'semanal';
                    const frequencyLabel = frequency === 'quinzenal' ? 'Quinzenal' : 'Semanal';
                    $list.find('[data-summary="frequency"]').text(frequencyLabel);
                    
                    // Calculate future appointment dates using a base timestamp for consistency
                    const futureDates = [];
                    const daysInterval = frequency === 'quinzenal' ? 14 : 7;
                    const numberOfFutureDates = 4; // Show next 4 appointments
                    const baseTimestamp = new Date(date + 'T12:00:00').getTime(); // Use noon to avoid DST issues
                    const msPerDay = 24 * 60 * 60 * 1000;
                    
                    for (let i = 1; i <= numberOfFutureDates; i++) {
                        const futureDate = new Date(baseTimestamp + (daysInterval * i * msPerDay));
                        futureDates.push(futureDate.toLocaleDateString('pt-BR'));
                    }
                    
                    $list.find('[data-summary="future-dates"]').text(futureDates.join(', '));
                    $('.dps-appointment-summary__subscription-info').show();
                } else {
                    $('.dps-appointment-summary__subscription-info').hide();
                }
                
                // Formata serviços como lista visual com badges
                const $servicesEl = $list.find('[data-summary="services"]');
                const $servicesLi = $servicesEl.closest('li');
                if (services.length > 0) {
                    // Adiciona classe para estilização do item pai
                    $servicesLi.addClass('dps-summary-services-item');
                    let servicesHtml = '<ul class="dps-summary-services-list">';
                    services.forEach(function(service) {
                        // Identifica se é desconto (valor negativo) - regex para variações de formato
                        const isDiscount = /[-−]\s*R\$/.test(service);
                        const badgeClass = isDiscount ? 'dps-service-badge--discount' : 'dps-service-badge';
                        servicesHtml += `<li class="${badgeClass}">${escapeHtml(service)}</li>`;
                    });
                    servicesHtml += '</ul>';
                    $servicesEl.html(servicesHtml);
                } else {
                    $servicesLi.removeClass('dps-summary-services-item');
                    $servicesEl.text('Nenhum serviço extra');
                }
                
                // Se há múltiplos pets, mostra o detalhamento
                const $priceEl = $list.find('[data-summary="price"]');
                if (petBreakdown.length > 1) {
                    let priceHtml = 'R$ ' + totalValue.toFixed(2) + '<br><small class="dps-pet-breakdown">';
                    petBreakdown.forEach(function(item, idx) {
                        if (idx > 0) priceHtml += ' | ';
                        priceHtml += item.pet.name + ': R$ ' + item.total.toFixed(2);
                    });
                    priceHtml += '</small>';
                    $priceEl.html(priceHtml);
                } else {
                    $priceEl.text('R$ ' + totalValue.toFixed(2));
                }
                
                // Atualiza campos hidden com os valores calculados para submissão
                // Cache dos seletores para melhor performance
                var $appointmentTotal = $('#appointment_total');
                var $subscriptionBaseValue = $('#subscription_base_value');
                var $subscriptionTotalValue = $('#subscription_total_value');
                var $subscriptionExtraValue = $('#subscription_extra_value');
                
                $appointmentTotal.val(totalValue.toFixed(2));
                
                // Para assinaturas, calcula e popula os valores específicos
                if (appointmentType === 'subscription') {
                    // subscription_base_value = valor total dos serviços (sem extras de assinatura)
                    var baseValue = totalValue;
                    var extraValue = 0;
                    
                    // Subtrai extras de assinatura do total para obter o base
                    $('.dps-subscription-extra-value').each(function() {
                        var val = parseCurrency($(this).val());
                        if (val > 0) {
                            extraValue += val;
                            baseValue -= val;
                        }
                    });
                    
                    $subscriptionBaseValue.val(Math.max(0, baseValue).toFixed(2));
                    $subscriptionTotalValue.val(totalValue.toFixed(2));
                    $subscriptionExtraValue.val(extraValue.toFixed(2));
                } else {
                    // Para agendamentos simples e passados, zera os valores de assinatura
                    $subscriptionBaseValue.val('0');
                    $subscriptionTotalValue.val('0');
                    $subscriptionExtraValue.val('0');
                }
                
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
                        // Limpa o select e adiciona opção padrão
                        $timeSelect.empty();
                        var $defaultOption = $('<option></option>')
                            .attr('value', '')
                            .text(dpsAppointmentData.l10n.selectTime || 'Selecione um horário');
                        $timeSelect.append($defaultOption);
                        
                        const times = response.data.times;
                        let hasAvailable = false;
                        
                        times.forEach(function(timeObj) {
                            if (timeObj.available) {
                                // Usa criação de elementos DOM para evitar XSS
                                var $option = $('<option></option>')
                                    .attr('value', timeObj.value)
                                    .text(timeObj.label);
                                $timeSelect.append($option);
                                hasAvailable = true;
                            }
                        });
                        
                        if (!hasAvailable) {
                            $timeSelect.empty();
                            var $noTimesOption = $('<option></option>')
                                .attr('value', '')
                                .text(dpsAppointmentData.l10n.noTimes || 'Nenhum horário disponível');
                            $timeSelect.append($noTimesOption);
                        }
                        
                        $timeSelect.prop('disabled', false);
                    } else {
                        $timeSelect.empty();
                        var $errorOption = $('<option></option>')
                            .attr('value', '')
                            .text(dpsAppointmentData.l10n.loadError || 'Erro ao carregar horários');
                        $timeSelect.append($errorOption);
                        $timeSelect.prop('disabled', false);
                    }
                },
                error: function() {
                    $timeSelect.empty();
                    var $errorOption = $('<option></option>')
                        .attr('value', '')
                        .text(dpsAppointmentData.l10n.loadError || 'Erro ao carregar horários');
                    $timeSelect.append($errorOption);
                    $timeSelect.prop('disabled', false);
                }
            });
        },
        
        /**
         * FASE 2: Validação do formulário antes do submit
         */
        validateForm: function() {
            const errors = [];
            const appointmentType = $('input[name="appointment_type"]:checked').val() || 'simple';
            
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
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                const selectedDate = new Date(date + 'T00:00:00');
                
                if (appointmentType === 'past') {
                    // Past appointments require exclusively past dates
                    if (selectedDate >= today) {
                        errors.push(dpsAppointmentData.l10n.requirePastDate || 'Para agendamento passado, a data deve ser anterior a hoje');
                    }
                } else {
                    // Simple and subscription appointments cannot use past dates
                    if (selectedDate < today) {
                        errors.push(dpsAppointmentData.l10n.pastDate || 'A data não pode ser anterior a hoje');
                    }
                }
            }
            
            // Valida horário
            const time = $('#appointment_time').val();
            if (!time) {
                errors.push(dpsAppointmentData.l10n.selectTimeSlot || 'Selecione um horário');
            }
            
            // Valida status de pagamento para agendamentos passados
            if (appointmentType === 'past') {
                const paymentStatus = $('#past_payment_status').val();
                if (!paymentStatus) {
                    errors.push(dpsAppointmentData.l10n.selectPaymentStatus || 'Selecione o status do pagamento');
                }
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

            const consent = this.getSelectedConsentStatus();
            if (this.appointmentRequiresConsent() && consent.status !== 'granted') {
                const confirmMessage = dpsAppointmentData.l10n.tosaConsentConfirm
                    || 'Este cliente não possui consentimento de tosa com máquina. Deseja continuar mesmo assim?';
                if (!window.confirm(confirmMessage)) {
                    event.preventDefault();
                    return false;
                }
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
    
    window.DPSAppointmentForm = DPSAppointmentForm;

    document.addEventListener('dps:appointmentFormLoaded', function() {
        if ($('form.dps-form input[name="appointment_type"]').length) {
            DPSAppointmentForm.init();
        }
    });

    // Inicializa quando o documento estiver pronto
    $(document).ready(function() {
        if ($('form.dps-form input[name="appointment_type"]').length) {
            DPSAppointmentForm.init();
        }
    });
    
})(jQuery);
