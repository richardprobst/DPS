/**
 * DPS Registration Add-on - Frontend JavaScript
 *
 * Handles form validation, input masks, pet cloning, and Google Places autocomplete.
 *
 * @package DPS_Registration_Addon
 * @since 1.2.0
 */

(function() {
    'use strict';

    // =========================================================================
    // Configuration
    // =========================================================================

    var CONFIG = {
        CPF_MASK: '###.###.###-##',
        PHONE_MASK_10: '(##) ####-####',
        PHONE_MASK_11: '(##) #####-####',
        MIN_NAME_LENGTH: 2,
        LOADING_TEXT: 'Enviando...',
        SUBMIT_TEXT: 'Enviar cadastro'
    };

    // =========================================================================
    // Utility Functions
    // =========================================================================

    /**
     * Remove all non-digit characters from a string.
     *
     * @param {string} value - Input string.
     * @return {string} Digits only.
     */
    function onlyDigits(value) {
        return (value || '').replace(/\D/g, '');
    }

    /**
     * Apply a mask to a value.
     *
     * @param {string} value - Raw value (digits only expected).
     * @param {string} mask  - Mask pattern (# = digit placeholder).
     * @return {string} Masked value.
     */
    function applyMask(value, mask) {
        var digits = onlyDigits(value);
        var result = '';
        var digitIndex = 0;

        for (var i = 0; i < mask.length && digitIndex < digits.length; i++) {
            if (mask[i] === '#') {
                result += digits[digitIndex];
                digitIndex++;
            } else {
                result += mask[i];
            }
        }

        return result;
    }

    /**
     * Validate CPF using mod 11 algorithm.
     *
     * @param {string} cpf - CPF (can contain punctuation).
     * @return {boolean} True if valid.
     */
    function validateCPF(cpf) {
        var digits = onlyDigits(cpf);

        if (digits.length !== 11) {
            return false;
        }

        // Reject known invalid sequences (all same digit)
        if (/^(\d)\1{10}$/.test(digits)) {
            return false;
        }

        // Calculate first verification digit
        var sum = 0;
        for (var i = 0; i < 9; i++) {
            sum += parseInt(digits[i], 10) * (10 - i);
        }
        var remainder = sum % 11;
        var digit1 = remainder < 2 ? 0 : 11 - remainder;

        if (parseInt(digits[9], 10) !== digit1) {
            return false;
        }

        // Calculate second verification digit
        sum = 0;
        for (var j = 0; j < 10; j++) {
            sum += parseInt(digits[j], 10) * (11 - j);
        }
        remainder = sum % 11;
        var digit2 = remainder < 2 ? 0 : 11 - remainder;

        return parseInt(digits[10], 10) === digit2;
    }

    /**
     * Validate Brazilian phone (10 or 11 digits).
     *
     * @param {string} phone - Phone (can contain punctuation).
     * @return {boolean} True if valid.
     */
    function validatePhone(phone) {
        var digits = onlyDigits(phone);

        // Remove country code if present (55)
        if (digits.length === 12 || digits.length === 13) {
            if (digits.substring(0, 2) === '55') {
                digits = digits.substring(2);
            }
        }

        // Must be 10 (landline) or 11 (mobile) digits
        if (digits.length !== 10 && digits.length !== 11) {
            return false;
        }

        // DDD must be between 11 and 99
        var ddd = parseInt(digits.substring(0, 2), 10);
        return ddd >= 11 && ddd <= 99;
    }

    /**
     * Validate email format (simple check).
     *
     * @param {string} email - Email address.
     * @return {boolean} True if valid format.
     */
    function validateEmail(email) {
        if (!email) {
            return true; // Optional field
        }
        // Simple regex: something@something.something
        var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    }

    /**
     * Retorna a lista de raças para a espécie informada com populares primeiro.
     *
     * @param {string} species - Código da espécie.
     * @return {Array<string>} Lista de raças.
     */
    function getBreedOptions(species) {
        var dataset = (window.dpsRegistrationData && window.dpsRegistrationData.breeds) ? window.dpsRegistrationData.breeds : null;
        if (!dataset) {
            return [];
        }

        var selected = dataset[species] || dataset.all || { popular: [], all: [] };
        var combined = [].concat(selected.popular || [], selected.all || []);
        var seen = {};
        var result = [];

        for (var i = 0; i < combined.length; i++) {
            var value = combined[i];
            if (!value || seen[value]) {
                continue;
            }
            seen[value] = true;
            result.push(value);
        }

        return result;
    }

    /**
     * Popula o datalist de raças de acordo com a espécie selecionada.
     *
     * @param {HTMLSelectElement} selectEl - Select de espécie.
     */
    function populateBreedDatalist(selectEl) {
        if (!selectEl || !(window.dpsRegistrationData && window.dpsRegistrationData.breeds)) {
            return;
        }

        var fieldset = selectEl.closest('.dps-pet-fieldset');
        if (!fieldset) {
            return;
        }

        var breedInput = fieldset.querySelector('input[name="pet_breed[]"]');
        var listId = breedInput ? breedInput.getAttribute('list') : null;
        var datalist = listId ? document.getElementById(listId) : null;
        if (!datalist) {
            return;
        }

        var species = selectEl.value || '';
        var options = getBreedOptions(species);
        if (!options.length) {
            options = getBreedOptions('all');
        }

        datalist.innerHTML = '';
        for (var i = 0; i < options.length; i++) {
            var option = document.createElement('option');
            option.value = options[i];
            datalist.appendChild(option);
        }
    }

    /**
     * Habilita autocomplete de raças em um select de espécie.
     *
     * @param {HTMLSelectElement} selectEl - Select de espécie.
     */
    function bindBreedSelector(selectEl) {
        if (!selectEl || selectEl.dataset.breedBound === '1' || !(window.dpsRegistrationData && window.dpsRegistrationData.breeds)) {
            return;
        }

        selectEl.dataset.breedBound = '1';
        populateBreedDatalist(selectEl);

        selectEl.addEventListener('change', function() {
            populateBreedDatalist(selectEl);
            var fieldset = selectEl.closest('.dps-pet-fieldset');
            var breedInput = fieldset ? fieldset.querySelector('input[name="pet_breed[]"]') : null;
            if (breedInput) {
                breedInput.value = '';
            }
        });
    }

    /**
     * Inicializa selects de espécie para manter o datalist sincronizado.
     */
    function initBreedSelectors() {
        if (!(window.dpsRegistrationData && window.dpsRegistrationData.breeds)) {
            return;
        }

        var selects = document.querySelectorAll('select[name="pet_species[]"]');
        for (var i = 0; i < selects.length; i++) {
            bindBreedSelector(selects[i]);
        }
    }

    // =========================================================================
    // Input Masks (F2.1)
    // =========================================================================

    /**
     * Apply CPF mask to input.
     *
     * @param {HTMLInputElement} input - The input element.
     */
    function applyCPFMask(input) {
        if (!input) return;

        input.addEventListener('input', function(e) {
            var cursorPos = input.selectionStart;
            var oldValue = input.value;
            var oldLength = oldValue.length;
            
            var digits = onlyDigits(input.value);
            // Limit to 11 digits
            digits = digits.substring(0, 11);
            
            var masked = applyMask(digits, CONFIG.CPF_MASK);
            input.value = masked;
            
            // Try to maintain cursor position
            var newLength = masked.length;
            var diff = newLength - oldLength;
            var newPos = cursorPos + diff;
            if (newPos < 0) newPos = 0;
            if (newPos > newLength) newPos = newLength;
            
            // Only set if focused
            if (document.activeElement === input) {
                try {
                    input.setSelectionRange(newPos, newPos);
                } catch (ex) {
                    // Ignore if not supported
                }
            }
        });

        // Handle paste
        input.addEventListener('paste', function(e) {
            setTimeout(function() {
                var digits = onlyDigits(input.value).substring(0, 11);
                input.value = applyMask(digits, CONFIG.CPF_MASK);
            }, 0);
        });
    }

    /**
     * Apply phone mask to input.
     *
     * @param {HTMLInputElement} input - The input element.
     */
    function applyPhoneMask(input) {
        if (!input) return;

        input.addEventListener('input', function(e) {
            var cursorPos = input.selectionStart;
            var oldValue = input.value;
            var oldLength = oldValue.length;
            
            var digits = onlyDigits(input.value);
            // Limit to 11 digits
            digits = digits.substring(0, 11);
            
            // Use appropriate mask based on digit count
            var mask = digits.length <= 10 ? CONFIG.PHONE_MASK_10 : CONFIG.PHONE_MASK_11;
            var masked = applyMask(digits, mask);
            input.value = masked;
            
            // Try to maintain cursor position
            var newLength = masked.length;
            var diff = newLength - oldLength;
            var newPos = cursorPos + diff;
            if (newPos < 0) newPos = 0;
            if (newPos > newLength) newPos = newLength;
            
            // Only set if focused
            if (document.activeElement === input) {
                try {
                    input.setSelectionRange(newPos, newPos);
                } catch (ex) {
                    // Ignore if not supported
                }
            }
        });

        // Handle paste
        input.addEventListener('paste', function(e) {
            setTimeout(function() {
                var digits = onlyDigits(input.value).substring(0, 11);
                var mask = digits.length <= 10 ? CONFIG.PHONE_MASK_10 : CONFIG.PHONE_MASK_11;
                input.value = applyMask(digits, mask);
            }, 0);
        });
    }

    // =========================================================================
    // Form Validation (F2.2)
    // =========================================================================

    /**
     * Display error message in container.
     *
     * @param {HTMLElement} container - Error container element.
     * @param {string} message        - Error message.
     */
    function showError(container, message) {
        if (!container) return;
        
        var errorDiv = document.createElement('div');
        errorDiv.className = 'dps-js-error';
        errorDiv.style.cssText = 'padding: 8px 12px; margin-bottom: 8px; border-radius: 4px; background-color: #fef2f2; border: 1px solid #ef4444; color: #991b1b; font-size: 14px;';
        errorDiv.textContent = message;
        container.appendChild(errorDiv);
    }

    /**
     * Clear all JS-generated errors.
     *
     * @param {HTMLFormElement} form - The form element.
     */
    function clearJSErrors(form) {
        var errors = form.querySelectorAll('.dps-js-error');
        for (var i = 0; i < errors.length; i++) {
            errors[i].parentNode.removeChild(errors[i]);
        }

        var wrapper = form.querySelector('.dps-js-errors');
        if (wrapper) {
            wrapper.innerHTML = '';
        }
    }

    /**
     * Returns the error container element, creating it if necessary.
     *
     * @param {HTMLFormElement} form - The form element.
     * @return {HTMLElement} Error container.
     */
    function getErrorContainer(form) {
        var errorContainer = form.querySelector('.dps-js-errors');
        if (!errorContainer) {
            errorContainer = document.createElement('div');
            errorContainer.className = 'dps-js-errors';
            errorContainer.setAttribute('role', 'alert');
            errorContainer.setAttribute('aria-live', 'polite');
            form.insertBefore(errorContainer, form.firstChild);
        }

        return errorContainer;
    }

    /**
     * Validate form before submission.
     *
     * @param {HTMLFormElement} form - The form element.
     * @return {boolean} True if valid.
     */
    function validateForm(form) {
        clearJSErrors(form);

        var errors = [];

        // Get error container (create if not exists)
        var errorContainer = getErrorContainer(form);
        
        // Required: Name
        var nameInput = form.querySelector('input[name="client_name"]');
        if (nameInput) {
            var name = (nameInput.value || '').trim();
            if (!name || name.length < CONFIG.MIN_NAME_LENGTH) {
                errors.push('O campo Nome é obrigatório.');
            }
        }
        
        // Required: Phone
        var phoneInput = form.querySelector('input[name="client_phone"]');
        if (phoneInput) {
            var phone = phoneInput.value || '';
            var phoneDigits = onlyDigits(phone);
            if (!phoneDigits) {
                errors.push('O campo Telefone / WhatsApp é obrigatório.');
            } else if (!validatePhone(phone)) {
                errors.push('O telefone informado não é válido. Use o formato (11) 98765-4321.');
            }
        }
        
        // Optional but must be valid if filled: CPF
        var cpfInput = form.querySelector('input[name="client_cpf"]');
        if (cpfInput) {
            var cpf = cpfInput.value || '';
            var cpfDigits = onlyDigits(cpf);
            if (cpfDigits && !validateCPF(cpf)) {
                errors.push('O CPF informado não é válido. Verifique os dígitos.');
            }
        }
        
        // Optional but must be valid if filled: Email
        var emailInput = form.querySelector('input[name="client_email"]');
        if (emailInput) {
            var email = (emailInput.value || '').trim();
            if (email && !validateEmail(email)) {
                errors.push('O email informado não é válido.');
            }
        }
        
        // Show errors
        if (errors.length > 0) {
            for (var i = 0; i < errors.length; i++) {
                showError(errorContainer, errors[i]);
            }
            // Scroll to errors (with fallback for older browsers)
            try {
                if (errorContainer.scrollIntoView) {
                    errorContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            } catch (e) {
                // Fallback for browsers that don't support smooth scrolling
                if (errorContainer.scrollIntoView) {
                    errorContainer.scrollIntoView(true);
                }
            }
            return false;
        }
        
        return true;
    }

    /**
     * Validate step 1 fields before advancing in the wizard.
     *
     * @param {HTMLFormElement} form - The form element.
     * @return {boolean} True if valid.
     */
    function validateStepOne(form) {
        clearJSErrors(form);

        var errors = [];
        var errorContainer = getErrorContainer(form);

        var nameInput = form.querySelector('input[name="client_name"]');
        if (nameInput) {
            var name = (nameInput.value || '').trim();
            if (!name || name.length < CONFIG.MIN_NAME_LENGTH) {
                errors.push('O campo Nome é obrigatório.');
            }
        }

        var phoneInput = form.querySelector('input[name="client_phone"]');
        if (phoneInput) {
            var phone = phoneInput.value || '';
            var phoneDigits = onlyDigits(phone);
            if (!phoneDigits) {
                errors.push('O campo Telefone / WhatsApp é obrigatório.');
            } else if (!validatePhone(phone)) {
                errors.push('O telefone informado não é válido. Use o formato (11) 98765-4321.');
            }
        }

        var emailInput = form.querySelector('input[name="client_email"]');
        if (emailInput) {
            var email = (emailInput.value || '').trim();
            if (email && !validateEmail(email)) {
                errors.push('O email informado não é válido.');
            }
        }

        if (errors.length > 0) {
            for (var i = 0; i < errors.length; i++) {
                showError(errorContainer, errors[i]);
            }
            return false;
        }

        return true;
    }

    /**
     * Validate step two (pet data) before proceeding.
     *
     * @param {HTMLFormElement} form - The form.
     * @return {boolean} True if valid.
     */
    function validateStepTwo(form) {
        clearJSErrors(form);

        var errors = [];
        var errorContainer = getErrorContainer(form);

        // Verifica se há pelo menos um pet com nome
        var petsWrapper = document.getElementById('dps-pets-wrapper');
        var petFieldsets = petsWrapper ? petsWrapper.querySelectorAll('.dps-pet-fieldset') : [];
        
        if (!petFieldsets || !petFieldsets.length) {
            errors.push('Adicione pelo menos um pet para continuar.');
        } else {
            var hasValidPet = false;
            for (var i = 0; i < petFieldsets.length; i++) {
                var petNameInput = petFieldsets[i].querySelector('input[name="pet_name[]"]');
                if (petNameInput && petNameInput.value.trim().length >= 2) {
                    hasValidPet = true;
                    break;
                }
            }
            
            if (!hasValidPet) {
                errors.push('Informe o nome do pet para continuar.');
            }
        }

        if (errors.length > 0) {
            for (var i = 0; i < errors.length; i++) {
                showError(errorContainer, errors[i]);
            }
            return false;
        }

        return true;
    }

    // =========================================================================
    // Loading Indicator (F2.4)
    // =========================================================================

    /**
     * Show loading state on submit button.
     *
     * @param {HTMLButtonElement} button - The submit button.
     */
    function showLoading(button) {
        if (!button) return;
        
        button.disabled = true;
        button.setAttribute('data-original-text', button.textContent);
        button.textContent = CONFIG.LOADING_TEXT;
        button.style.opacity = '0.7';
        button.style.cursor = 'wait';
    }

    /**
     * Hide loading state on submit button.
     *
     * @param {HTMLButtonElement} button - The submit button.
     */
    function hideLoading(button) {
        if (!button) return;
        
        var originalText = button.getAttribute('data-original-text');
        button.disabled = false;
        button.textContent = originalText || CONFIG.SUBMIT_TEXT;
        button.style.opacity = '';
        button.style.cursor = '';
    }

    // =========================================================================
    // Pet Clone Functionality (moved from inline)
    // =========================================================================

    /**
     * Initialize pet clone functionality.
     *
     * @param {string} templateJson - JSON-encoded HTML template.
     */
    function initPetClone(templateJson, onAddPet) {
        var petCount = 1;
        var wrapper = document.getElementById('dps-pets-wrapper');
        var addBtn = document.getElementById('dps-add-pet');
        var clientNameInput = document.getElementById('dps-client-name');
        
        if (!wrapper || !addBtn || !templateJson) {
            return;
        }
        
        var template;
        try {
            template = JSON.parse(templateJson);
        } catch (e) {
            console.error('DPS Registration: Failed to parse pet template');
            return;
        }
        
        // Function to update owner name fields
        function updateOwnerFields() {
            var ownerFields = document.querySelectorAll('.dps-owner-name');
            var ownerName = clientNameInput ? clientNameInput.value : '';
            for (var i = 0; i < ownerFields.length; i++) {
                ownerFields[i].value = ownerName;
            }
        }
        
        // Listen for name input changes
        if (clientNameInput) {
            clientNameInput.addEventListener('input', updateOwnerFields);
        }
        
        // Add pet button click
        addBtn.addEventListener('click', function() {
            petCount++;
            var html = template.replace(/__INDEX__/g, petCount);
            var div = document.createElement('div');
            div.innerHTML = html;
            wrapper.appendChild(div);
            updateOwnerFields();

            if (typeof onAddPet === 'function') {
                onAddPet();
            }
        });
        
        // Initial update
        updateOwnerFields();
    }

    // =========================================================================
    // Google Places Autocomplete (moved from inline)
    // =========================================================================

    /**
     * Initialize Google Places autocomplete.
     */
    function initGooglePlaces() {
        var input = document.getElementById('dps-client-address');
        
        if (!input || typeof google === 'undefined' || !google.maps || !google.maps.places) {
            return;
        }
        
        var autocomplete = new google.maps.places.Autocomplete(input, {
            types: ['geocode']
        });
        
        autocomplete.addListener('place_changed', function() {
            var place = autocomplete.getPlace();
            if (place && place.geometry) {
                var lat = place.geometry.location.lat();
                var lng = place.geometry.location.lng();
                var latField = document.getElementById('dps-client-lat');
                var lngField = document.getElementById('dps-client-lng');
                
                if (latField && lngField) {
                    latField.value = lat;
                    lngField.value = lng;
                }
            }
        });
    }

    // =========================================================================
    // Wizard Navigation and Summary (F2.6 / F2.7)
    // =========================================================================

    var currentStep = 1;
    var totalSteps = 3;

    function updateProgress(step, progressElements) {
        if (!progressElements) return;

        var label = progressElements.label;
        var counter = progressElements.counter;
        var bar = progressElements.bar;

        if (label) {
            label.textContent = 'Passo ' + step + ' de ' + totalSteps;
        }

        if (counter) {
            counter.textContent = step + '/' + totalSteps;
        }

        if (bar) {
            var width = Math.round((step / totalSteps) * 100) + '%';
            bar.style.width = width;
            bar.parentElement.setAttribute('aria-valuenow', step);
        }
    }

    function showStep(step, form, steps, progressElements, buttons) {
        if (!steps || !steps.length) return;

        for (var i = 0; i < steps.length; i++) {
            var isCurrent = parseInt(steps[i].getAttribute('data-step'), 10) === step;
            steps[i].classList.toggle('dps-step-active', isCurrent);
            steps[i].setAttribute('aria-hidden', !isCurrent);
        }

        currentStep = step;
        updateProgress(step, progressElements);

        // Atualiza visibilidade dos botões de navegação
        if (buttons) {
            // Botão "Próximo" da etapa 1 → etapa 2
            if (buttons.next) {
                buttons.next.style.display = step === 1 ? 'inline-flex' : 'none';
            }
            // Botão "Próximo" da etapa 2 → etapa 3
            if (buttons.next2) {
                buttons.next2.style.display = step === 2 ? 'inline-flex' : 'none';
            }
            // Botão "Voltar" da etapa 2 → etapa 1
            if (buttons.back) {
                buttons.back.style.display = step === 2 ? 'inline-flex' : 'none';
            }
            // Botão "Voltar" da etapa 3 → etapa 2
            if (buttons.back2) {
                buttons.back2.style.display = step === 3 ? 'inline-flex' : 'none';
            }
            // Botão "Enviar" só aparece na etapa 3
            if (buttons.submit) {
                buttons.submit.style.display = step === 3 ? 'inline-flex' : 'none';
            }
        }
        
        // Na etapa 3, renderiza os campos de preferências para cada pet
        if (step === 3) {
            renderProductPrefsStep(form);
        }
    }

    /**
     * Renderiza os campos de preferências de produtos para cada pet na etapa 3.
     *
     * @param {HTMLFormElement} form - Formulário de cadastro.
     */
    function renderProductPrefsStep(form) {
        var prefsWrapper = document.getElementById('dps-product-prefs-wrapper');
        if (!prefsWrapper) return;
        
        prefsWrapper.innerHTML = '';
        
        var petsWrapper = document.getElementById('dps-pets-wrapper');
        var petFieldsets = petsWrapper ? petsWrapper.querySelectorAll('.dps-pet-fieldset') : [];
        
        if (!petFieldsets || !petFieldsets.length) {
            prefsWrapper.innerHTML = '<p class="dps-empty-message">Nenhum pet cadastrado. Volte para a etapa anterior para adicionar pets.</p>';
            return;
        }
        
        for (var i = 0; i < petFieldsets.length; i++) {
            var fieldset = petFieldsets[i];
            var petNameInput = fieldset.querySelector('input[name="pet_name[]"]');
            var petSpeciesSelect = fieldset.querySelector('select[name="pet_species[]"]');
            
            var petName = petNameInput ? petNameInput.value.trim() : ('Pet ' + (i + 1));
            var speciesValue = petSpeciesSelect ? petSpeciesSelect.value : '';
            var speciesIcon = speciesValue === 'cao' ? '🐶' : (speciesValue === 'gato' ? '🐱' : '🐾');
            
            // Cria fieldset para este pet
            var petPrefsBox = document.createElement('div');
            petPrefsBox.className = 'dps-product-prefs-pet';
            
            var petTitle = document.createElement('h5');
            petTitle.className = 'dps-product-prefs-pet__title';
            petTitle.innerHTML = speciesIcon + ' ' + (petName || 'Pet ' + (i + 1));
            petPrefsBox.appendChild(petTitle);
            
            // Campo: Preferência de Shampoo
            var shampooField = document.createElement('div');
            shampooField.className = 'dps-product-pref-field';
            shampooField.innerHTML = '<label>🧴 Preferência de Shampoo<br>' +
                '<select name="pet_shampoo_pref[]">' +
                '<option value="">Sem preferência específica</option>' +
                '<option value="hipoalergenico">Hipoalergênico</option>' +
                '<option value="antisseptico">Antisséptico</option>' +
                '<option value="pelagem_branca">Para pelagem branca</option>' +
                '<option value="pelagem_escura">Para pelagem escura</option>' +
                '<option value="antipulgas">Antipulgas</option>' +
                '<option value="hidratante">Hidratante</option>' +
                '<option value="outro">Outro (especificar abaixo)</option>' +
                '</select></label>';
            petPrefsBox.appendChild(shampooField);
            
            // Campo: Preferência de Perfume
            var perfumeField = document.createElement('div');
            perfumeField.className = 'dps-product-pref-field';
            perfumeField.innerHTML = '<label>✨ Preferência de Perfume<br>' +
                '<select name="pet_perfume_pref[]">' +
                '<option value="">Sem preferência</option>' +
                '<option value="suave">Perfume suave</option>' +
                '<option value="intenso">Perfume intenso</option>' +
                '<option value="sem_perfume">Sem perfume (proibido)</option>' +
                '<option value="hipoalergenico">Hipoalergênico apenas</option>' +
                '</select></label>';
            petPrefsBox.appendChild(perfumeField);
            
            // Campo: Preferência de Adereços
            var accessoriesField = document.createElement('div');
            accessoriesField.className = 'dps-product-pref-field';
            accessoriesField.innerHTML = '<label>🎀 Adereços<br>' +
                '<select name="pet_accessories_pref[]">' +
                '<option value="">Sem preferência</option>' +
                '<option value="lacinho">Lacinho</option>' +
                '<option value="gravata">Gravata</option>' +
                '<option value="lenco">Lenço</option>' +
                '<option value="bandana">Bandana</option>' +
                '<option value="sem_aderecos">Não usar adereços</option>' +
                '</select></label>';
            petPrefsBox.appendChild(accessoriesField);
            
            // Campo: Outras restrições/observações
            var restrictionsField = document.createElement('div');
            restrictionsField.className = 'dps-product-pref-field dps-product-pref-field--full';
            restrictionsField.innerHTML = '<label>📝 Outras restrições ou observações sobre produtos<br>' +
                '<textarea name="pet_product_restrictions[]" rows="2" ' +
                'placeholder="Ex.: Alérgico a produto X, usar apenas produtos naturais, etc."></textarea></label>';
            petPrefsBox.appendChild(restrictionsField);
            
            prefsWrapper.appendChild(petPrefsBox);
        }
    }

    function addSummaryItem(list, label, value) {
        if (!value) return;

        var li = document.createElement('li');
        var strong = document.createElement('strong');
        strong.textContent = label + ':';
        li.appendChild(strong);
        li.appendChild(document.createTextNode(' ' + value));
        list.appendChild(li);
    }

    /**
     * Helper to get selected text from a select element.
     *
     * @param {HTMLSelectElement} select - Select element.
     * @return {string} Selected option text or empty string.
     */
    function getSelectText(select) {
        if (!select || !select.options) return '';
        var selectedOption = select.options[select.selectedIndex];
        return selectedOption ? selectedOption.text.trim() : (select.value ? select.value.trim() : '');
    }

    /**
     * Build summary of all form data before submission.
     * Displays tutor and pet information in the summary box.
     *
     * @param {HTMLFormElement} form - The registration form.
     */
    function buildSummary(form) {
        var summaryContent = document.getElementById('dps-summary-content');

        if (!summaryContent) {
            return;
        }

        summaryContent.innerHTML = '';

        // =====================================================================
        // Tutor Section - All client fields
        // =====================================================================
        var tutorSection = document.createElement('div');
        tutorSection.className = 'dps-summary-section';
        var tutorTitle = document.createElement('h5');
        tutorTitle.textContent = '👤 Tutor';
        tutorSection.appendChild(tutorTitle);
        var tutorList = document.createElement('ul');

        // Client fields
        var nameInput = form.querySelector('input[name="client_name"]');
        var cpfInput = form.querySelector('input[name="client_cpf"]');
        var phoneInput = form.querySelector('input[name="client_phone"]');
        var emailInput = form.querySelector('input[name="client_email"]');
        var birthInput = form.querySelector('input[name="client_birth"]');
        var instagramInput = form.querySelector('input[name="client_instagram"]');
        var facebookInput = form.querySelector('input[name="client_facebook"]');
        var photoAuthInput = form.querySelector('input[name="client_photo_auth"]');
        var addressInput = form.querySelector('textarea[name="client_address"]');
        var referralInput = form.querySelector('input[name="client_referral"]');

        addSummaryItem(tutorList, 'Nome', nameInput ? nameInput.value.trim() : '');
        addSummaryItem(tutorList, 'CPF', cpfInput ? cpfInput.value.trim() : '');
        addSummaryItem(tutorList, 'Telefone', phoneInput ? phoneInput.value.trim() : '');
        addSummaryItem(tutorList, 'Email', emailInput ? emailInput.value.trim() : '');
        
        // Format birth date for display
        if (birthInput && birthInput.value) {
            var birthParts = birthInput.value.split('-');
            if (birthParts.length === 3) {
                var formattedBirth = birthParts[2] + '/' + birthParts[1] + '/' + birthParts[0];
                addSummaryItem(tutorList, 'Data de nascimento', formattedBirth);
            }
        }
        
        addSummaryItem(tutorList, 'Instagram', instagramInput ? instagramInput.value.trim() : '');
        addSummaryItem(tutorList, 'Facebook', facebookInput ? facebookInput.value.trim() : '');
        
        if (photoAuthInput && photoAuthInput.checked) {
            addSummaryItem(tutorList, 'Autorização de foto', '✓ Autorizado');
        }
        
        addSummaryItem(tutorList, 'Endereço', addressInput ? addressInput.value.trim() : '');
        addSummaryItem(tutorList, 'Como conheceu', referralInput ? referralInput.value.trim() : '');

        if (tutorList.children.length) {
            tutorSection.appendChild(tutorList);
            summaryContent.appendChild(tutorSection);
        }

        // =====================================================================
        // Pets Section - All pet fields
        // =====================================================================
        var petsWrapper = document.getElementById('dps-pets-wrapper');
        var petFieldsets = petsWrapper ? petsWrapper.querySelectorAll('.dps-pet-fieldset') : [];

        if (petFieldsets && petFieldsets.length) {
            var petsContainer = document.createElement('div');
            petsContainer.className = 'dps-summary-section';
            var petsTitle = document.createElement('h5');
            petsTitle.textContent = '🐾 Pets';
            petsContainer.appendChild(petsTitle);

            for (var i = 0; i < petFieldsets.length; i++) {
                var fieldset = petFieldsets[i];
                var petBox = document.createElement('div');
                petBox.className = 'dps-summary-pet';
                
                // Get species for pet icon
                var petSpecies = fieldset.querySelector('select[name="pet_species[]"]');
                var speciesValue = petSpecies ? petSpecies.value : '';
                var speciesIcon = speciesValue === 'cao' ? '🐶' : (speciesValue === 'gato' ? '🐱' : '🐾');
                
                var petTitle = document.createElement('h6');
                petTitle.textContent = speciesIcon + ' Pet ' + (i + 1);
                petBox.appendChild(petTitle);

                var petList = document.createElement('ul');
                
                // Pet fields
                var petName = fieldset.querySelector('input[name="pet_name[]"]');
                var petBreed = fieldset.querySelector('input[name="pet_breed[]"]');
                var petSize = fieldset.querySelector('select[name="pet_size[]"]');
                var petWeight = fieldset.querySelector('input[name="pet_weight[]"]');
                var petCoat = fieldset.querySelector('input[name="pet_coat[]"]');
                var petColor = fieldset.querySelector('input[name="pet_color[]"]');
                var petBirth = fieldset.querySelector('input[name="pet_birth[]"]');
                var petSex = fieldset.querySelector('select[name="pet_sex[]"]');
                var petNotes = fieldset.querySelector('textarea[name="pet_care[]"]');
                var petAggressive = fieldset.querySelector('input[name^="pet_aggressive"]');

                addSummaryItem(petList, 'Nome', petName ? petName.value.trim() : '');
                addSummaryItem(petList, 'Espécie', getSelectText(petSpecies));
                addSummaryItem(petList, 'Raça', petBreed ? petBreed.value.trim() : '');
                addSummaryItem(petList, 'Porte', getSelectText(petSize));
                
                if (petWeight && petWeight.value) {
                    addSummaryItem(petList, 'Peso', petWeight.value.trim() + ' kg');
                }
                
                addSummaryItem(petList, 'Pelagem', petCoat ? petCoat.value.trim() : '');
                addSummaryItem(petList, 'Cor', petColor ? petColor.value.trim() : '');
                
                // Format pet birth date
                if (petBirth && petBirth.value) {
                    var petBirthParts = petBirth.value.split('-');
                    if (petBirthParts.length === 3) {
                        var formattedPetBirth = petBirthParts[2] + '/' + petBirthParts[1] + '/' + petBirthParts[0];
                        addSummaryItem(petList, 'Nascimento', formattedPetBirth);
                    }
                }
                
                addSummaryItem(petList, 'Sexo', getSelectText(petSex));
                addSummaryItem(petList, 'Cuidados especiais', petNotes ? petNotes.value.trim() : '');
                
                if (petAggressive && petAggressive.checked) {
                    addSummaryItem(petList, 'Alerta', '⚠️ Pet agressivo');
                }
                
                // Preferências de produtos (Etapa 3)
                var prefsWrapper = document.getElementById('dps-product-prefs-wrapper');
                if (prefsWrapper) {
                    var allShampooPrefs = prefsWrapper.querySelectorAll('select[name="pet_shampoo_pref[]"]');
                    var allPerfumePrefs = prefsWrapper.querySelectorAll('select[name="pet_perfume_pref[]"]');
                    var allAccessoriesPrefs = prefsWrapper.querySelectorAll('select[name="pet_accessories_pref[]"]');
                    var allRestrictions = prefsWrapper.querySelectorAll('textarea[name="pet_product_restrictions[]"]');
                    
                    if (allShampooPrefs[i]) {
                        addSummaryItem(petList, 'Shampoo', getSelectText(allShampooPrefs[i]));
                    }
                    if (allPerfumePrefs[i]) {
                        addSummaryItem(petList, 'Perfume', getSelectText(allPerfumePrefs[i]));
                    }
                    if (allAccessoriesPrefs[i]) {
                        addSummaryItem(petList, 'Adereços', getSelectText(allAccessoriesPrefs[i]));
                    }
                    if (allRestrictions[i] && allRestrictions[i].value.trim()) {
                        addSummaryItem(petList, 'Restrições', allRestrictions[i].value.trim());
                    }
                }

                if (petList.children.length) {
                    petBox.appendChild(petList);
                    petsContainer.appendChild(petBox);
                }
            }

            summaryContent.appendChild(petsContainer);
        }

        if (!summaryContent.children.length) {
            var empty = document.createElement('p');
            empty.className = 'dps-summary-empty';
            empty.textContent = 'Preencha os campos para visualizar o resumo.';
            summaryContent.appendChild(empty);
        }
    }

    // =========================================================================
    // Duplicate Check Modal (Admin Only)
    // =========================================================================

    /**
     * Create and inject modal styles if not already present.
     */
    function ensureModalStyles() {
        if (document.getElementById('dps-duplicate-modal-styles')) {
            return;
        }

        var styles = document.createElement('style');
        styles.id = 'dps-duplicate-modal-styles';
        styles.textContent = [
            '.dps-modal-overlay {',
            '  position: fixed;',
            '  top: 0;',
            '  left: 0;',
            '  right: 0;',
            '  bottom: 0;',
            '  background: rgba(0, 0, 0, 0.6);',
            '  display: flex;',
            '  align-items: center;',
            '  justify-content: center;',
            '  z-index: 999999;',
            '  padding: 20px;',
            '}',
            '.dps-modal {',
            '  background: #fff;',
            '  border-radius: 8px;',
            '  max-width: 500px;',
            '  width: 100%;',
            '  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);',
            '  animation: dps-modal-appear 0.2s ease-out;',
            '}',
            '@keyframes dps-modal-appear {',
            '  from { opacity: 0; transform: scale(0.95); }',
            '  to { opacity: 1; transform: scale(1); }',
            '}',
            '.dps-modal-header {',
            '  padding: 20px 24px;',
            '  border-bottom: 1px solid #e5e7eb;',
            '  display: flex;',
            '  align-items: center;',
            '  gap: 12px;',
            '}',
            '.dps-modal-header .dps-modal-icon {',
            '  font-size: 24px;',
            '}',
            '.dps-modal-header h3 {',
            '  margin: 0;',
            '  font-size: 18px;',
            '  font-weight: 600;',
            '  color: #374151;',
            '}',
            '.dps-modal-body {',
            '  padding: 20px 24px;',
            '}',
            '.dps-modal-body p {',
            '  margin: 0 0 16px;',
            '  color: #4b5563;',
            '  line-height: 1.5;',
            '}',
            '.dps-modal-duplicate-fields {',
            '  display: flex;',
            '  flex-wrap: wrap;',
            '  gap: 8px;',
            '  margin-bottom: 16px;',
            '}',
            '.dps-modal-duplicate-fields .dps-duplicate-badge {',
            '  background: #fef3c7;',
            '  color: #92400e;',
            '  padding: 4px 12px;',
            '  border-radius: 9999px;',
            '  font-size: 13px;',
            '  font-weight: 500;',
            '}',
            '.dps-modal-client-info {',
            '  background: #f9fafb;',
            '  border: 1px solid #e5e7eb;',
            '  border-radius: 6px;',
            '  padding: 12px 16px;',
            '  margin-top: 12px;',
            '}',
            '.dps-modal-client-info .dps-client-label {',
            '  font-size: 12px;',
            '  color: #6b7280;',
            '  margin-bottom: 4px;',
            '}',
            '.dps-modal-client-info .dps-client-name {',
            '  font-weight: 600;',
            '  color: #111827;',
            '}',
            '.dps-modal-footer {',
            '  padding: 16px 24px;',
            '  border-top: 1px solid #e5e7eb;',
            '  display: flex;',
            '  flex-wrap: wrap;',
            '  gap: 12px;',
            '  justify-content: flex-end;',
            '}',
            '.dps-modal-footer button, .dps-modal-footer a {',
            '  padding: 10px 20px;',
            '  border-radius: 6px;',
            '  font-size: 14px;',
            '  font-weight: 500;',
            '  cursor: pointer;',
            '  text-decoration: none;',
            '  display: inline-flex;',
            '  align-items: center;',
            '  justify-content: center;',
            '  transition: all 0.15s ease;',
            '}',
            '.dps-modal-btn-cancel {',
            '  background: #f3f4f6;',
            '  border: 1px solid #d1d5db;',
            '  color: #374151;',
            '}',
            '.dps-modal-btn-cancel:hover {',
            '  background: #e5e7eb;',
            '}',
            '.dps-modal-btn-view {',
            '  background: #0ea5e9;',
            '  border: 1px solid #0ea5e9;',
            '  color: #fff;',
            '}',
            '.dps-modal-btn-view:hover {',
            '  background: #0284c7;',
            '  border-color: #0284c7;',
            '}',
            '.dps-modal-btn-continue {',
            '  background: #f59e0b;',
            '  border: 1px solid #f59e0b;',
            '  color: #fff;',
            '}',
            '.dps-modal-btn-continue:hover {',
            '  background: #d97706;',
            '  border-color: #d97706;',
            '}',
            '@media (max-width: 480px) {',
            '  .dps-modal-footer {',
            '    flex-direction: column;',
            '  }',
            '  .dps-modal-footer button, .dps-modal-footer a {',
            '    width: 100%;',
            '  }',
            '}'
        ].join('\n');

        document.head.appendChild(styles);
    }

    /**
     * Show duplicate client modal.
     *
     * @param {Object} data - Duplicate data from server.
     * @param {Object} i18n - Internationalized strings.
     * @param {Function} onContinue - Callback when user chooses to continue.
     * @param {Function} onCancel - Callback when user cancels.
     */
    function showDuplicateModal(data, i18n, onContinue, onCancel) {
        ensureModalStyles();

        // Remove existing modal if any
        var existingModal = document.getElementById('dps-duplicate-modal');
        if (existingModal) {
            existingModal.remove();
        }

        // Build duplicate fields badges
        var fieldsHtml = '';
        if (data.duplicated_fields && data.duplicated_fields.length) {
            for (var i = 0; i < data.duplicated_fields.length; i++) {
                fieldsHtml += '<span class="dps-duplicate-badge">' + escapeHtml(data.duplicated_fields[i]) + '</span>';
            }
        }

        // Create modal HTML
        var modalHtml = [
            '<div class="dps-modal-overlay" id="dps-duplicate-modal">',
            '  <div class="dps-modal" role="dialog" aria-modal="true" aria-labelledby="dps-modal-title">',
            '    <div class="dps-modal-header">',
            '      <span class="dps-modal-icon" aria-hidden="true">⚠️</span>',
            '      <h3 id="dps-modal-title">' + escapeHtml(i18n.modalTitle) + '</h3>',
            '    </div>',
            '    <div class="dps-modal-body">',
            '      <p>' + escapeHtml(i18n.modalMessage) + '</p>',
            '      <div class="dps-modal-duplicate-fields">' + fieldsHtml + '</div>',
            '      <div class="dps-modal-client-info">',
            '        <div class="dps-client-label">' + escapeHtml(i18n.clientLabel) + '</div>',
            '        <div class="dps-client-name">' + escapeHtml(data.client_name || 'ID: ' + data.client_id) + '</div>',
            '      </div>',
            '    </div>',
            '    <div class="dps-modal-footer">',
            '      <button type="button" class="dps-modal-btn-cancel" id="dps-modal-cancel">' + escapeHtml(i18n.cancelButton) + '</button>',
            '      <a href="#" class="dps-modal-btn-view" id="dps-modal-view">' + escapeHtml(i18n.viewClientButton) + '</a>',
            '      <button type="button" class="dps-modal-btn-continue" id="dps-modal-continue">' + escapeHtml(i18n.continueButton) + '</button>',
            '    </div>',
            '  </div>',
            '</div>'
        ].join('\n');

        // Inject modal
        var container = document.createElement('div');
        container.innerHTML = modalHtml;
        document.body.appendChild(container.firstElementChild);

        // Get modal elements
        var modal = document.getElementById('dps-duplicate-modal');
        var cancelBtn = document.getElementById('dps-modal-cancel');
        var continueBtn = document.getElementById('dps-modal-continue');
        var viewBtn = document.getElementById('dps-modal-view');

        // Set URL safely via DOM API (prevents XSS via href attribute)
        if (viewBtn && data.view_url) {
            viewBtn.href = data.view_url;
        }

        // Event handlers
        function closeModal() {
            if (modal) {
                modal.remove();
            }
        }

        // Named function for keydown handler
        function handleEscapeKey(e) {
            if (e.key === 'Escape' && document.getElementById('dps-duplicate-modal')) {
                closeModalWithCleanup();
                if (typeof onCancel === 'function') {
                    onCancel();
                }
            }
        }

        // Cleanup function to remove event listener
        function closeModalWithCleanup() {
            document.removeEventListener('keydown', handleEscapeKey);
            closeModal();
        }

        cancelBtn.addEventListener('click', function() {
            closeModalWithCleanup();
            if (typeof onCancel === 'function') {
                onCancel();
            }
        });

        continueBtn.addEventListener('click', function() {
            closeModalWithCleanup();
            if (typeof onContinue === 'function') {
                onContinue();
            }
        });

        // Close on overlay click
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModalWithCleanup();
                if (typeof onCancel === 'function') {
                    onCancel();
                }
            }
        });

        // Close on Escape key
        document.addEventListener('keydown', handleEscapeKey);

        // Focus the cancel button
        cancelBtn.focus();
    }

    /**
     * Escape HTML to prevent XSS.
     *
     * @param {string} str - String to escape.
     * @return {string} Escaped string.
     */
    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    /**
     * Check for duplicate client via AJAX.
     *
     * @param {HTMLFormElement} form - The form.
     * @param {HTMLButtonElement} submitButton - Submit button.
     * @param {string} email - Client email.
     * @param {string} phone - Client phone.
     * @param {string} cpf - Client CPF.
     * @param {Object} config - Duplicate check config.
     * @param {Function} callback - Callback(shouldContinue, wasDuplicate).
     */
    function checkDuplicate(form, submitButton, email, phone, cpf, config, callback) {
        // If no data to check, continue
        if (!email && !phone && !cpf) {
            callback(true, false);
            return;
        }

        // Show checking message
        if (submitButton) {
            submitButton.textContent = config.i18n.checkingMessage || 'Verificando...';
        }

        // Build form data
        var formData = new FormData();
        formData.append('action', config.action);
        formData.append('nonce', config.nonce);
        formData.append('email', email);
        formData.append('phone', phone);
        formData.append('cpf', cpf);

        // AJAX request
        var xhr = new XMLHttpRequest();
        xhr.open('POST', config.ajaxUrl, true);

        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 400) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success && response.data && response.data.is_duplicate) {
                        // Show modal
                        showDuplicateModal(response.data, config.i18n, function() {
                            // User chose to continue
                            callback(true, true);
                        }, function() {
                            // User cancelled
                            callback(false, true);
                        });
                    } else {
                        // No duplicate, continue
                        callback(true, false);
                    }
                } catch (e) {
                    // Parse error, continue anyway
                    callback(true, false);
                }
            } else {
                // HTTP error, continue anyway (don't block registration)
                callback(true, false);
            }
        };

        xhr.onerror = function() {
            // Network error, continue anyway
            callback(true, false);
        };

        xhr.send(formData);
    }

    // =========================================================================
    // Main Initialization
    // =========================================================================

    /**
     * Initialize all registration form functionality.
     */
    function init() {
        var form = document.getElementById('dps-reg-form');

        if (!form) {
            return;
        }

        // F2.1: Apply input masks
        var cpfInput = form.querySelector('input[name="client_cpf"]');
        var phoneInput = form.querySelector('input[name="client_phone"]');

        var recaptchaConfig = (window.dpsRegistrationData && window.dpsRegistrationData.recaptcha) ? window.dpsRegistrationData.recaptcha : null;
        var recaptchaValidated = false;

        // Duplicate check config for admins
        var duplicateConfig = (window.dpsRegistrationData && window.dpsRegistrationData.duplicateCheck) ? window.dpsRegistrationData.duplicateCheck : null;
        var duplicateConfirmed = false;

        applyCPFMask(cpfInput);
        applyPhoneMask(phoneInput);

        // Wizard elements
        var steps = form.querySelectorAll('.dps-step');
        var nextButton = document.getElementById('dps-next-step');
        var nextButton2 = document.getElementById('dps-next-step-2');
        var backButton = document.getElementById('dps-back-step');
        var backButton2 = document.getElementById('dps-back-step-2');
        var submitButton = form.querySelector('button[type="submit"]');
        var confirmCheckbox = document.getElementById('dps-summary-confirm');
        var progressElements = {
            label: document.getElementById('dps-step-label'),
            counter: document.getElementById('dps-step-counter'),
            bar: document.getElementById('dps-progress-bar-fill')
        };
        
        var wizardButtons = {
            next: nextButton,
            next2: nextButton2,
            back: backButton,
            back2: backButton2,
            submit: submitButton
        };

        showStep(1, form, steps, progressElements, wizardButtons);

        initBreedSelectors();

        // Etapa 1 → Etapa 2
        if (nextButton) {
            nextButton.addEventListener('click', function() {
                if (!validateStepOne(form)) {
                    return;
                }

                if (confirmCheckbox) {
                    confirmCheckbox.checked = false;
                }

                if (submitButton) {
                    submitButton.disabled = true;
                }

                showStep(2, form, steps, progressElements, wizardButtons);
            });
        }
        
        // Etapa 2 → Etapa 3
        if (nextButton2) {
            nextButton2.addEventListener('click', function() {
                if (!validateStepTwo(form)) {
                    return;
                }

                if (confirmCheckbox) {
                    confirmCheckbox.checked = false;
                }

                if (submitButton) {
                    submitButton.disabled = true;
                }

                showStep(3, form, steps, progressElements, wizardButtons);
                buildSummary(form);
            });
        }

        // Etapa 2 → Etapa 1 (voltar)
        if (backButton) {
            backButton.addEventListener('click', function() {
                showStep(1, form, steps, progressElements, wizardButtons);
            });
        }
        
        // Etapa 3 → Etapa 2 (voltar)
        if (backButton2) {
            backButton2.addEventListener('click', function() {
                showStep(2, form, steps, progressElements, wizardButtons);
            });
        }

        if (confirmCheckbox && submitButton) {
            submitButton.disabled = !confirmCheckbox.checked;
            confirmCheckbox.addEventListener('change', function() {
                submitButton.disabled = !confirmCheckbox.checked;
            });
        }

        form.addEventListener('input', function() {
            if (currentStep === 3) {
                buildSummary(form);
            }
        });

        // F2.2 & F2.4: Form validation and loading on submit
        form.addEventListener('submit', function(e) {
            if (!validateForm(form)) {
                e.preventDefault();
                hideLoading(submitButton);
                return false;
            }

            if (confirmCheckbox && !confirmCheckbox.checked) {
                e.preventDefault();
                hideLoading(submitButton);
                showError(getErrorContainer(form), 'Confirme que os dados estão corretos antes de enviar.');
                return false;
            }

            // Show loading
            showLoading(submitButton);

            // Check for duplicates (admin only) before reCAPTCHA
            if (duplicateConfig && duplicateConfig.enabled && !duplicateConfirmed) {
                e.preventDefault();

                var emailInput = form.querySelector('input[name="client_email"]');
                var phoneInput = form.querySelector('input[name="client_phone"]');
                var cpfInput = form.querySelector('input[name="client_cpf"]');

                var email = emailInput ? emailInput.value.trim() : '';
                var phone = phoneInput ? phoneInput.value.trim() : '';
                var cpf = cpfInput ? cpfInput.value.trim() : '';

                // Call AJAX to check for duplicates
                checkDuplicate(form, submitButton, email, phone, cpf, duplicateConfig, function(shouldContinue, wasDuplicate) {
                    if (shouldContinue) {
                        duplicateConfirmed = true;
                        // Add hidden field only if there was a duplicate and user confirmed
                        if (wasDuplicate) {
                            var confirmInput = form.querySelector('input[name="dps_confirm_duplicate"]');
                            if (!confirmInput) {
                                confirmInput = document.createElement('input');
                                confirmInput.type = 'hidden';
                                confirmInput.name = 'dps_confirm_duplicate';
                                form.appendChild(confirmInput);
                            }
                            confirmInput.value = '1';
                        }
                        // Re-trigger submit - requestSubmit() respects form validation/events, form.submit() bypasses them
                        // For browsers without requestSubmit (older), we still need the duplicateConfirmed flag to prevent re-checking
                        if (typeof form.requestSubmit === 'function') {
                            form.requestSubmit();
                        } else {
                            // Legacy fallback: dispatch event which will trigger our listener again
                            // The duplicateConfirmed flag ensures we skip the duplicate check
                            form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
                        }
                    } else {
                        hideLoading(submitButton);
                    }
                });

                return false;
            }

            if (recaptchaConfig && recaptchaConfig.enabled) {
                if (recaptchaValidated) {
                    return true;
                }

                e.preventDefault();

                if (typeof grecaptcha === 'undefined' || !grecaptcha.execute) {
                    hideLoading(submitButton);
                    showError(getErrorContainer(form), recaptchaConfig.unavailableMessage || 'Não foi possível validar o anti-spam. Tente novamente.');
                    return false;
                }

                var tokenInput = form.querySelector('input[name="dps_recaptcha_token"]');
                var actionInput = form.querySelector('input[name="dps_recaptcha_action"]');

                if (actionInput && recaptchaConfig.action) {
                    actionInput.value = recaptchaConfig.action;
                }

                grecaptcha.ready(function() {
                    grecaptcha.execute(recaptchaConfig.siteKey, { action: recaptchaConfig.action }).then(function(token) {
                        if (tokenInput) {
                            tokenInput.value = token;
                        }
                        recaptchaValidated = true;
                        form.submit();
                    }).catch(function() {
                        hideLoading(submitButton);
                        showError(getErrorContainer(form), recaptchaConfig.errorMessage || 'Não foi possível validar o anti-spam. Tente novamente.');
                    });
                });

                return false;
            }

            // Allow form to submit
            return true;
        });
        
        // Initialize pet clone if template is available
        var templateElement = document.getElementById('dps-pet-template');
        if (templateElement) {
            initPetClone(templateElement.textContent, function() {
                initBreedSelectors();
                if (currentStep === 2) {
                    buildSummary(form);
                }
            });
        }
        
        // Initialize Google Places if available
        if (typeof google !== 'undefined' && google.maps && google.maps.places) {
            initGooglePlaces();
        }
    }

    // =========================================================================
    // Expose for external use (pet template initialization)
    // =========================================================================

    window.DPSRegistration = {
        init: init,
        initPetClone: initPetClone,
        initGooglePlaces: initGooglePlaces,
        validateCPF: validateCPF,
        validatePhone: validatePhone,
        validateEmail: validateEmail
    };

    // Auto-init when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
