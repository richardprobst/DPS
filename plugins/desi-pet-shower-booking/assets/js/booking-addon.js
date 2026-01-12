/**
 * JavaScript do Booking Add-on - DPS
 * 
 * Gerencia interações do formulário de agendamento de serviços.
 * - Navegação entre steps
 * - Validação client-side
 * - Máscara de telefone
 * - Resumo dinâmico
 * - Progress bar
 * 
 * @package Desi_Pet_Shower_Booking
 * @since 1.0.0
 */

(function() {
    'use strict';

    /**
     * Booking Form Controller
     */
    const DPSBooking = {
        currentStep: 1,
        totalSteps: 4,
        form: null,
        steps: [],
        progressSteps: [],
        data: {},

        /**
         * Inicializa o controller
         */
        init: function() {
            this.form = document.getElementById('dps-booking-form-element');
            if (!this.form) {
                return;
            }

            this.steps = this.form.querySelectorAll('.dps-booking-step');
            this.progressSteps = document.querySelectorAll('.dps-booking-progress__step');
            
            this.bindEvents();
            this.initPhoneMask();
            this.updateProgress();
        },

        /**
         * Vincula eventos
         */
        bindEvents: function() {
            const self = this;

            // Botões de navegação
            document.querySelectorAll('.dps-booking-btn--next').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const nextStep = parseInt(this.dataset.next);
                    if (self.validateCurrentStep()) {
                        self.goToStep(nextStep);
                    }
                });
            });

            document.querySelectorAll('.dps-booking-btn--prev').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const prevStep = parseInt(this.dataset.prev);
                    self.goToStep(prevStep);
                });
            });

            // Submissão do formulário
            this.form.addEventListener('submit', function(e) {
                if (!self.validateCurrentStep()) {
                    e.preventDefault();
                    return;
                }

                // Mostra loading
                const submitBtn = self.form.querySelector('.dps-booking-btn--submit');
                if (submitBtn) {
                    submitBtn.classList.add('dps-booking-btn--loading');
                    submitBtn.disabled = true;
                }
            });

            // Atualiza resumo quando campos mudam
            this.form.addEventListener('change', function() {
                self.updateSummary();
            });

            this.form.addEventListener('input', function() {
                self.updateSummary();
            });

            // Atualiza seleção de serviços
            document.querySelectorAll('input[name="service_id"]').forEach(function(input) {
                input.addEventListener('change', function() {
                    self.updateSummary();
                });
            });

            document.querySelectorAll('input[name="extras[]"]').forEach(function(input) {
                input.addEventListener('change', function() {
                    self.updateSummary();
                });
            });
        },

        /**
         * Navega para um step específico
         * @param {number} step Número do step
         */
        goToStep: function(step) {
            if (step < 1 || step > this.totalSteps) {
                return;
            }

            // Esconde step atual
            this.steps[this.currentStep - 1].classList.remove('dps-booking-step--active');

            // Mostra novo step
            this.currentStep = step;
            this.steps[this.currentStep - 1].classList.add('dps-booking-step--active');

            // Atualiza progress
            this.updateProgress();

            // Atualiza resumo se estiver no último step
            if (this.currentStep === this.totalSteps) {
                this.updateSummary();
            }

            // Scroll suave para o topo do formulário
            const formContainer = document.querySelector('.dps-booking-form');
            if (formContainer) {
                formContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        },

        /**
         * Atualiza indicador de progresso
         */
        updateProgress: function() {
            const self = this;

            this.progressSteps.forEach(function(stepEl, index) {
                const stepNum = index + 1;
                
                stepEl.classList.remove('dps-booking-progress__step--active');
                stepEl.classList.remove('dps-booking-progress__step--completed');

                if (stepNum === self.currentStep) {
                    stepEl.classList.add('dps-booking-progress__step--active');
                } else if (stepNum < self.currentStep) {
                    stepEl.classList.add('dps-booking-progress__step--completed');
                }
            });

            // Atualiza conectores
            const connectors = document.querySelectorAll('.dps-booking-progress__connector');
            connectors.forEach(function(connector, index) {
                const stepNum = index + 1;
                if (stepNum < self.currentStep) {
                    connector.style.background = 'var(--dps-success)';
                } else {
                    connector.style.background = 'var(--dps-border)';
                }
            });
        },

        /**
         * Valida o step atual
         * @returns {boolean}
         */
        validateCurrentStep: function() {
            const currentStepEl = this.steps[this.currentStep - 1];
            const requiredFields = currentStepEl.querySelectorAll('[required]');
            let isValid = true;

            // Remove erros anteriores
            currentStepEl.querySelectorAll('.dps-booking-field--error').forEach(function(field) {
                field.classList.remove('dps-booking-field--error');
            });
            currentStepEl.querySelectorAll('.dps-booking-error').forEach(function(error) {
                error.remove();
            });

            // Valida campos obrigatórios
            requiredFields.forEach(function(field) {
                if (!field.value.trim()) {
                    isValid = false;
                    this.showFieldError(field, this.getI18n('required'));
                }
            }.bind(this));

            // Validações específicas por step
            if (this.currentStep === 1) {
                isValid = this.validateStep1() && isValid;
            } else if (this.currentStep === 3) {
                isValid = this.validateStep3() && isValid;
            }

            return isValid;
        },

        /**
         * Valida Step 1 - Dados do Cliente
         * @returns {boolean}
         */
        validateStep1: function() {
            let isValid = true;

            // Valida telefone
            const phoneField = document.getElementById('client_phone');
            if (phoneField && phoneField.value) {
                const digits = phoneField.value.replace(/\D/g, '');
                if (digits.length < 10 || digits.length > 13) {
                    isValid = false;
                    this.showFieldError(phoneField, this.getI18n('invalidPhone'));
                }
            }

            // Valida email (se preenchido)
            const emailField = document.getElementById('client_email');
            if (emailField && emailField.value) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(emailField.value)) {
                    isValid = false;
                    this.showFieldError(emailField, this.getI18n('invalidEmail'));
                }
            }

            return isValid;
        },

        /**
         * Valida Step 3 - Serviço
         * @returns {boolean}
         */
        validateStep3: function() {
            const serviceSelected = document.querySelector('input[name="service_id"]:checked');
            if (!serviceSelected) {
                const servicesContainer = document.querySelector('.dps-booking-services__list');
                if (servicesContainer) {
                    this.showContainerError(servicesContainer, this.getI18n('selectService'));
                }
                return false;
            }
            return true;
        },

        /**
         * Exibe erro em um campo
         * @param {HTMLElement} field Campo com erro
         * @param {string} message Mensagem de erro
         */
        showFieldError: function(field, message) {
            const wrapper = field.closest('.dps-booking-field');
            if (wrapper) {
                wrapper.classList.add('dps-booking-field--error');
            }

            // Adiciona mensagem de erro
            const errorEl = document.createElement('span');
            errorEl.className = 'dps-booking-error';
            errorEl.textContent = message;
            errorEl.style.cssText = 'color: #ef4444; font-size: 13px; margin-top: 4px; display: block;';
            
            field.parentNode.appendChild(errorEl);
            field.focus();

            // Adiciona estilo de erro ao campo
            field.style.borderColor = '#ef4444';
            field.addEventListener('input', function() {
                field.style.borderColor = '';
                const error = field.parentNode.querySelector('.dps-booking-error');
                if (error) error.remove();
                if (wrapper) wrapper.classList.remove('dps-booking-field--error');
            }, { once: true });
        },

        /**
         * Exibe erro em um container
         * @param {HTMLElement} container Container com erro
         * @param {string} message Mensagem de erro
         */
        showContainerError: function(container, message) {
            const errorEl = document.createElement('div');
            errorEl.className = 'dps-booking-error dps-booking-message dps-booking-message--error';
            errorEl.innerHTML = '<span class="dps-booking-message__icon">⚠️</span><span>' + message + '</span>';
            
            container.parentNode.insertBefore(errorEl, container);

            // Remove erro quando selecionar algo
            container.addEventListener('change', function() {
                errorEl.remove();
            }, { once: true });
        },

        /**
         * Inicializa máscara de telefone
         */
        initPhoneMask: function() {
            const phoneField = document.getElementById('client_phone');
            if (!phoneField) {
                return;
            }

            phoneField.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                
                // Limita a 11 dígitos (celular com DDD)
                if (value.length > 11) {
                    value = value.substring(0, 11);
                }

                // Formata
                if (value.length > 0) {
                    if (value.length <= 2) {
                        value = '(' + value;
                    } else if (value.length <= 6) {
                        value = '(' + value.substring(0, 2) + ') ' + value.substring(2);
                    } else if (value.length <= 10) {
                        value = '(' + value.substring(0, 2) + ') ' + value.substring(2, 6) + '-' + value.substring(6);
                    } else {
                        value = '(' + value.substring(0, 2) + ') ' + value.substring(2, 7) + '-' + value.substring(7);
                    }
                }

                e.target.value = value;
            });
        },

        /**
         * Atualiza o resumo do agendamento
         */
        updateSummary: function() {
            const summaryContent = document.getElementById('summary-content');
            if (!summaryContent) {
                return;
            }

            let html = '';

            // Dados do cliente
            const clientName = document.getElementById('client_name');
            const clientPhone = document.getElementById('client_phone');
            if (clientName && clientName.value) {
                html += '<div class="dps-booking-summary__row">';
                html += '<span class="dps-booking-summary__label">👤 Cliente</span>';
                html += '<span class="dps-booking-summary__value">' + this.escapeHtml(clientName.value) + '</span>';
                html += '</div>';
            }
            if (clientPhone && clientPhone.value) {
                html += '<div class="dps-booking-summary__row">';
                html += '<span class="dps-booking-summary__label">📱 WhatsApp</span>';
                html += '<span class="dps-booking-summary__value">' + this.escapeHtml(clientPhone.value) + '</span>';
                html += '</div>';
            }

            // Dados do pet
            const petName = document.getElementById('pet_name');
            const petBreed = document.getElementById('pet_breed');
            const petSize = document.querySelector('input[name="pet_size"]:checked');
            if (petName && petName.value) {
                html += '<div class="dps-booking-summary__row">';
                html += '<span class="dps-booking-summary__label">🐾 Pet</span>';
                let petInfo = this.escapeHtml(petName.value);
                if (petBreed && petBreed.value) {
                    petInfo += ' (' + this.escapeHtml(petBreed.value) + ')';
                }
                html += '<span class="dps-booking-summary__value">' + petInfo + '</span>';
                html += '</div>';
            }
            if (petSize) {
                const sizeLabels = { 'pequeno': 'Pequeno', 'medio': 'Médio', 'grande': 'Grande' };
                html += '<div class="dps-booking-summary__row">';
                html += '<span class="dps-booking-summary__label">📏 Porte</span>';
                html += '<span class="dps-booking-summary__value">' + (sizeLabels[petSize.value] || petSize.value) + '</span>';
                html += '</div>';
            }

            // Serviço selecionado
            const selectedService = document.querySelector('input[name="service_id"]:checked');
            if (selectedService) {
                const serviceCard = selectedService.closest('.dps-booking-service');
                const serviceName = serviceCard ? serviceCard.querySelector('.dps-booking-service__name') : null;
                const servicePrice = serviceCard ? serviceCard.querySelector('.dps-booking-service__price') : null;
                
                if (serviceName) {
                    html += '<div class="dps-booking-summary__row">';
                    html += '<span class="dps-booking-summary__label">✨ Serviço</span>';
                    html += '<span class="dps-booking-summary__value">' + this.escapeHtml(serviceName.textContent) + '</span>';
                    html += '</div>';
                }
            }

            // Extras selecionados
            const selectedExtras = document.querySelectorAll('input[name="extras[]"]:checked');
            if (selectedExtras.length > 0) {
                let extrasNames = [];
                selectedExtras.forEach(function(extra) {
                    const extraCard = extra.closest('.dps-booking-extra');
                    const extraName = extraCard ? extraCard.querySelector('.dps-booking-extra__name') : null;
                    if (extraName) {
                        extrasNames.push(extraName.textContent);
                    }
                });
                if (extrasNames.length > 0) {
                    html += '<div class="dps-booking-summary__row">';
                    html += '<span class="dps-booking-summary__label">➕ Extras</span>';
                    html += '<span class="dps-booking-summary__value">' + this.escapeHtml(extrasNames.join(', ')) + '</span>';
                    html += '</div>';
                }
            }

            // Data e hora
            const appointmentDate = document.getElementById('appointment_date');
            const appointmentTime = document.getElementById('appointment_time');
            if (appointmentDate && appointmentDate.value) {
                const dateObj = new Date(appointmentDate.value + 'T12:00:00');
                const formattedDate = dateObj.toLocaleDateString('pt-BR', { 
                    weekday: 'long', 
                    day: 'numeric', 
                    month: 'long',
                    year: 'numeric'
                });
                html += '<div class="dps-booking-summary__row">';
                html += '<span class="dps-booking-summary__label">📅 Data</span>';
                html += '<span class="dps-booking-summary__value">' + this.escapeHtml(formattedDate) + '</span>';
                html += '</div>';
            }
            if (appointmentTime && appointmentTime.value) {
                html += '<div class="dps-booking-summary__row">';
                html += '<span class="dps-booking-summary__label">🕐 Horário</span>';
                html += '<span class="dps-booking-summary__value">' + this.escapeHtml(appointmentTime.value) + '</span>';
                html += '</div>';
            }

            // Se não houver nada, mostra mensagem
            if (!html) {
                html = '<p style="color: #64748b; text-align: center; margin: 0;">Preencha os dados para ver o resumo</p>';
            }

            summaryContent.innerHTML = html;
        },

        /**
         * Escape HTML para prevenir XSS
         * @param {string} text Texto a escapar
         * @returns {string}
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        /**
         * Obtém string de internacionalização
         * @param {string} key Chave
         * @returns {string}
         */
        getI18n: function(key) {
            if (typeof dpsBookingData !== 'undefined' && dpsBookingData.i18n && dpsBookingData.i18n[key]) {
                return dpsBookingData.i18n[key];
            }
            
            // Fallbacks
            const fallbacks = {
                'required': 'Este campo é obrigatório',
                'invalidPhone': 'Telefone inválido',
                'invalidEmail': 'Email inválido',
                'selectService': 'Selecione um serviço'
            };
            
            return fallbacks[key] || key;
        }
    };

    // Inicializa quando DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            DPSBooking.init();
        });
    } else {
        DPSBooking.init();
    }

    // Expõe globalmente para debug
    window.DPSBooking = DPSBooking;

})();
