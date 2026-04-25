/**
 * DPS Registration Add-on - public form controller.
 *
 * @package DPS_Registration_Addon
 */

(function() {
    'use strict';

    var CONFIG = {
        CPF_MASK: '###.###.###-##',
        PHONE_MASK_10: '(##) ####-####',
        PHONE_MASK_11: '(##) #####-####',
        MIN_NAME_LENGTH: 2,
        LOADING_TEXT: 'Enviando...',
        SUBMIT_TEXT: 'Enviar cadastro'
    };

    var state = {
        currentStep: 1,
        totalSteps: 3,
        duplicateConfirmed: false,
        recaptchaValidated: false
    };

    function toArray(list) {
        return Array.prototype.slice.call(list || []);
    }

    function onlyDigits(value) {
        return (value || '').replace(/\D/g, '');
    }

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

    function validateCPF(cpf) {
        var digits = onlyDigits(cpf);

        if (digits.length !== 11 || /^(\d)\1{10}$/.test(digits)) {
            return false;
        }

        var sum = 0;
        for (var i = 0; i < 9; i++) {
            sum += parseInt(digits[i], 10) * (10 - i);
        }

        var remainder = sum % 11;
        var digit1 = remainder < 2 ? 0 : 11 - remainder;

        if (parseInt(digits[9], 10) !== digit1) {
            return false;
        }

        sum = 0;
        for (var j = 0; j < 10; j++) {
            sum += parseInt(digits[j], 10) * (11 - j);
        }

        remainder = sum % 11;
        var digit2 = remainder < 2 ? 0 : 11 - remainder;

        return parseInt(digits[10], 10) === digit2;
    }

    function validatePhone(phone) {
        var digits = onlyDigits(phone);

        if ((digits.length === 12 || digits.length === 13) && digits.substring(0, 2) === '55') {
            digits = digits.substring(2);
        }

        if (digits.length !== 10 && digits.length !== 11) {
            return false;
        }

        var ddd = parseInt(digits.substring(0, 2), 10);
        return ddd >= 11 && ddd <= 99;
    }

    function validateEmail(email) {
        if (!email) {
            return true;
        }

        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function getRegistrationData() {
        return window.dpsRegistrationData || {};
    }

    function getBreedOptions(species) {
        var data = getRegistrationData();
        var dataset = data.breeds || null;

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

    function populateBreedDatalist(selectEl) {
        if (!selectEl) {
            return;
        }

        var fieldset = selectEl.closest('.dps-pet-fieldset');
        var breedInput = fieldset ? fieldset.querySelector('input[name="pet_breed[]"]') : null;
        var listId = breedInput ? breedInput.getAttribute('list') : null;
        var datalist = listId ? document.getElementById(listId) : null;

        if (!datalist) {
            return;
        }

        var options = getBreedOptions(selectEl.value || '');
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

    function bindBreedSelector(selectEl) {
        if (!selectEl || selectEl.dataset.dpsBreedReady === '1') {
            return;
        }

        selectEl.dataset.dpsBreedReady = '1';
        populateBreedDatalist(selectEl);

        selectEl.addEventListener('change', function() {
            var fieldset = selectEl.closest('.dps-pet-fieldset');
            var breedInput = fieldset ? fieldset.querySelector('input[name="pet_breed[]"]') : null;

            populateBreedDatalist(selectEl);
            if (breedInput) {
                breedInput.value = '';
            }
        });
    }

    function initBreedSelectors(root) {
        toArray((root || document).querySelectorAll('select[name="pet_species[]"]')).forEach(bindBreedSelector);
    }

    function bindMaskedInput(input, type) {
        if (!input || input.dataset.dpsMaskReady === type) {
            return;
        }

        input.dataset.dpsMaskReady = type;
        input.addEventListener('input', function() {
            var digits = onlyDigits(input.value);
            var mask = CONFIG.CPF_MASK;

            if (type === 'phone') {
                digits = digits.substring(0, 11);
                mask = digits.length <= 10 ? CONFIG.PHONE_MASK_10 : CONFIG.PHONE_MASK_11;
            } else {
                digits = digits.substring(0, 11);
            }

            input.value = applyMask(digits, mask);
        });

        input.addEventListener('paste', function() {
            window.setTimeout(function() {
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }, 0);
        });
    }

    function initSignatureForms(root) {
        if (window.DPSSignatureForms && typeof window.DPSSignatureForms.init === 'function') {
            window.DPSSignatureForms.init(root || document);
        }
    }

    function initGooglePlaces(root) {
        var scope = root || document;

        if (window.DPSSignatureForms && typeof window.DPSSignatureForms.initAddressAutocomplete === 'function') {
            window.DPSSignatureForms.initAddressAutocomplete(scope);
            return;
        }

        var input = scope.querySelector ? scope.querySelector('#dps-client-address') : document.getElementById('dps-client-address');
        if (!input || input.dataset.dpsPlacesReady === '1') {
            return;
        }

        if (typeof window.google === 'undefined' || !window.google.maps || !window.google.maps.places) {
            return;
        }

        input.dataset.dpsPlacesReady = '1';
        var autocomplete = new window.google.maps.places.Autocomplete(input, {
            fields: [ 'formatted_address', 'geometry' ],
            types: [ 'geocode' ]
        });

        autocomplete.addListener('place_changed', function() {
            var place = autocomplete.getPlace();
            var latField = document.getElementById('dps-client-lat');
            var lngField = document.getElementById('dps-client-lng');

            if (place && place.formatted_address) {
                input.value = place.formatted_address;
            }

            if (place && place.geometry && place.geometry.location) {
                if (latField) {
                    latField.value = String(place.geometry.location.lat());
                }
                if (lngField) {
                    lngField.value = String(place.geometry.location.lng());
                }
            }
        });
    }

    function getErrorContainer(form) {
        var container = form.querySelector('.dps-js-errors');

        if (!container) {
            container = document.createElement('div');
            container.className = 'dps-js-errors';
            container.setAttribute('role', 'alert');
            container.setAttribute('aria-live', 'polite');
            form.insertBefore(container, form.firstChild);
        }

        return container;
    }

    function clearJSErrors(form) {
        toArray(form.querySelectorAll('.dps-js-error')).forEach(function(error) {
            if (error.parentNode) {
                error.parentNode.removeChild(error);
            }
        });

        toArray(form.querySelectorAll('[aria-invalid="true"]')).forEach(function(field) {
            field.removeAttribute('aria-invalid');
        });
    }

    function clearErrorMessages(form) {
        toArray(form.querySelectorAll('.dps-js-error')).forEach(function(error) {
            if (error.parentNode) {
                error.parentNode.removeChild(error);
            }
        });
    }

    function markField(field) {
        if (field) {
            field.setAttribute('aria-invalid', 'true');
        }
    }

    function showError(container, message) {
        if (!container || !message) {
            return;
        }

        var error = document.createElement('div');
        error.className = 'dps-js-error';
        error.textContent = message;
        container.appendChild(error);
    }

    function scrollToErrors(container) {
        if (!container || !container.scrollIntoView) {
            return;
        }

        try {
            container.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } catch (e) {
            container.scrollIntoView(true);
        }
    }

    function getTrimmedValue(field) {
        return field ? (field.value || '').trim() : '';
    }

    function validateStepOne(form, silent) {
        var errors = [];
        var nameInput = form.querySelector('input[name="client_name"]');
        var phoneInput = form.querySelector('input[name="client_phone"]');
        var cpfInput = form.querySelector('input[name="client_cpf"]');
        var emailInput = form.querySelector('input[name="client_email"]');
        var name = getTrimmedValue(nameInput);
        var phone = getTrimmedValue(phoneInput);
        var cpf = getTrimmedValue(cpfInput);
        var email = getTrimmedValue(emailInput);

        if (!name || name.length < CONFIG.MIN_NAME_LENGTH) {
            errors.push('Informe o nome do tutor.');
            markField(nameInput);
        }

        if (!onlyDigits(phone)) {
            errors.push('Informe o telefone ou WhatsApp.');
            markField(phoneInput);
        } else if (!validatePhone(phone)) {
            errors.push('Revise o telefone. Use DDD e 8 ou 9 dígitos.');
            markField(phoneInput);
        }

        if (cpf && !validateCPF(cpf)) {
            errors.push('Revise o CPF informado.');
            markField(cpfInput);
        }

        if (email && !validateEmail(email)) {
            errors.push('Revise o email informado.');
            markField(emailInput);
        }

        if (errors.length && !silent) {
            renderErrors(form, errors);
        }

        return !errors.length;
    }

    function validateStepTwo(form, silent) {
        var errors = [];
        var petsWrapper = form.querySelector('#dps-pets-wrapper');
        var petFieldsets = petsWrapper ? toArray(petsWrapper.querySelectorAll('.dps-pet-fieldset')) : [];

        if (!petFieldsets.length) {
            errors.push('Adicione pelo menos um pet.');
        }

        petFieldsets.forEach(function(fieldset, index) {
            var number = index + 1;
            var nameInput = fieldset.querySelector('input[name="pet_name[]"]');
            var speciesSelect = fieldset.querySelector('select[name="pet_species[]"]');
            var sizeSelect = fieldset.querySelector('select[name="pet_size[]"]');
            var sexSelect = fieldset.querySelector('select[name="pet_sex[]"]');

            if (getTrimmedValue(nameInput).length < CONFIG.MIN_NAME_LENGTH) {
                errors.push('Informe o nome do Pet ' + number + '.');
                markField(nameInput);
            }

            if (!getTrimmedValue(speciesSelect)) {
                errors.push('Selecione a espécie do Pet ' + number + '.');
                markField(speciesSelect);
            }

            if (!getTrimmedValue(sizeSelect)) {
                errors.push('Selecione o porte do Pet ' + number + '.');
                markField(sizeSelect);
            }

            if (!getTrimmedValue(sexSelect)) {
                errors.push('Selecione o sexo do Pet ' + number + '.');
                markField(sexSelect);
            }
        });

        if (errors.length && !silent) {
            renderErrors(form, errors);
        }

        return !errors.length;
    }

    function renderErrors(form, errors) {
        var container = getErrorContainer(form);
        clearErrorMessages(form);

        errors.forEach(function(message) {
            showError(container, message);
        });

        scrollToErrors(container);
    }

    function validateForm(form) {
        clearJSErrors(form);

        var errors = [];
        var stepOneOk = validateStepOne(form, true);
        var stepTwoOk = validateStepTwo(form, true);
        var confirmCheckbox = form.querySelector('#dps-summary-confirm');

        if (!stepOneOk) {
            errors.push('Revise os dados do tutor.');
        }

        if (!stepTwoOk) {
            errors.push('Revise os dados dos pets.');
        }

        if (confirmCheckbox && !confirmCheckbox.checked) {
            errors.push('Confirme que os dados estão corretos antes de enviar.');
            markField(confirmCheckbox);
        }

        if (errors.length) {
            renderErrors(form, errors);
            return false;
        }

        return true;
    }

    function updateProgress(step, progress) {
        if (progress.label) {
            progress.label.textContent = 'Passo ' + step + ' de ' + state.totalSteps;
        }
        if (progress.counter) {
            progress.counter.textContent = step + '/' + state.totalSteps;
        }
        if (progress.bar) {
            progress.bar.style.width = Math.round((step / state.totalSteps) * 100) + '%';
            if (progress.bar.parentElement) {
                progress.bar.parentElement.setAttribute('aria-valuenow', String(step));
            }
        }
    }

    function showStep(form, step, progress) {
        toArray(form.querySelectorAll('.dps-step')).forEach(function(stepEl) {
            var isCurrent = parseInt(stepEl.getAttribute('data-step'), 10) === step;
            stepEl.classList.toggle('dps-step-active', isCurrent);
            stepEl.hidden = !isCurrent;
            stepEl.setAttribute('aria-hidden', isCurrent ? 'false' : 'true');
        });

        state.currentStep = step;
        updateProgress(step, progress);

        if (step === 3) {
            renderProductPrefsStep(form);
            buildSummary(form);
        }
    }

    function reindexPetFieldsets(wrapper) {
        var fieldsets = toArray(wrapper.querySelectorAll('.dps-pet-fieldset'));

        fieldsets.forEach(function(fieldset, index) {
            var number = index + 1;
            var legend = fieldset.querySelector('legend');
            var aggressive = fieldset.querySelector('input[name^="pet_aggressive"]');
            var breedInput = fieldset.querySelector('input[name="pet_breed[]"]');
            var datalist = fieldset.querySelector('datalist[id^="dps-breed-list"]');

            if (legend) {
                legend.textContent = 'Pet ' + number;
            }
            if (aggressive) {
                aggressive.name = 'pet_aggressive[' + index + ']';
            }
            if (breedInput) {
                breedInput.setAttribute('list', 'dps-breed-list-' + number);
            }
            if (datalist) {
                datalist.id = 'dps-breed-list-' + number;
            }

            ensureRemovePetButton(fieldset, index > 0);
        });
    }

    function ensureRemovePetButton(fieldset, shouldShow) {
        var existing = fieldset.querySelector('.dps-remove-pet-wrap');

        if (!shouldShow) {
            if (existing && existing.parentNode) {
                existing.parentNode.removeChild(existing);
            }
            return;
        }

        if (existing) {
            return;
        }

        var wrap = document.createElement('p');
        var button = document.createElement('button');
        wrap.className = 'dps-remove-pet-wrap';
        button.type = 'button';
        button.className = 'dps-remove-pet dps-button-secondary';
        button.textContent = 'Remover pet';
        wrap.appendChild(button);
        fieldset.appendChild(wrap);
    }

    function updateOwnerFields(form) {
        var ownerName = getTrimmedValue(form.querySelector('#dps-client-name'));
        toArray(form.querySelectorAll('.dps-owner-name')).forEach(function(input) {
            input.value = ownerName;
        });
    }

    function initPetClone(templateJson, onChange) {
        var form = document.getElementById('dps-reg-form');
        var wrapper = document.getElementById('dps-pets-wrapper');
        var addBtn = document.getElementById('dps-add-pet');

        if (!form || !wrapper || !addBtn || !templateJson || addBtn.dataset.dpsPetCloneReady === '1') {
            return;
        }

        var template = '';
        try {
            template = JSON.parse(templateJson);
        } catch (e) {
            return;
        }

        addBtn.dataset.dpsPetCloneReady = '1';
        reindexPetFieldsets(wrapper);

        addBtn.addEventListener('click', function() {
            var count = wrapper.querySelectorAll('.dps-pet-fieldset').length + 1;
            var index = count - 1;
            var html = template
                .replace(/__PET_NUMBER__/g, String(count))
                .replace(/__PET_INDEX__/g, String(index))
                .replace(/__INDEX__/g, String(count));
            var container = document.createElement('div');
            container.innerHTML = html;

            while (container.firstChild) {
                wrapper.appendChild(container.firstChild);
            }

            reindexPetFieldsets(wrapper);
            updateOwnerFields(form);
            initBreedSelectors(wrapper);

            if (typeof onChange === 'function') {
                onChange();
            }
        });

        wrapper.addEventListener('click', function(event) {
            var button = event.target.closest ? event.target.closest('.dps-remove-pet') : null;
            if (!button) {
                return;
            }

            var fieldset = button.closest('.dps-pet-fieldset');
            if (fieldset && wrapper.querySelectorAll('.dps-pet-fieldset').length > 1) {
                fieldset.parentNode.removeChild(fieldset);
                reindexPetFieldsets(wrapper);
                initBreedSelectors(wrapper);
                if (typeof onChange === 'function') {
                    onChange();
                }
            }
        });

        var clientNameInput = form.querySelector('#dps-client-name');
        if (clientNameInput) {
            clientNameInput.addEventListener('input', function() {
                updateOwnerFields(form);
            });
            updateOwnerFields(form);
        }
    }

    function createField(labelText, control) {
        var field = document.createElement('div');
        var label = document.createElement('label');
        field.className = 'dps-product-pref-field';
        label.textContent = labelText;
        label.appendChild(control);
        field.appendChild(label);
        return field;
    }

    function createSelect(name, options) {
        var select = document.createElement('select');
        select.name = name;

        options.forEach(function(item) {
            var option = document.createElement('option');
            option.value = item.value;
            option.textContent = item.label;
            select.appendChild(option);
        });

        return select;
    }

    function renderProductPrefsStep(form) {
        var wrapper = form.querySelector('#dps-product-prefs-wrapper');
        var petsWrapper = form.querySelector('#dps-pets-wrapper');
        var petFieldsets = petsWrapper ? toArray(petsWrapper.querySelectorAll('.dps-pet-fieldset')) : [];

        if (!wrapper) {
            return;
        }

        wrapper.innerHTML = '';
        if (!petFieldsets.length) {
            var empty = document.createElement('p');
            empty.className = 'dps-empty-message';
            empty.textContent = 'Volte para adicionar pelo menos um pet.';
            wrapper.appendChild(empty);
            return;
        }

        petFieldsets.forEach(function(fieldset, index) {
            var petName = getTrimmedValue(fieldset.querySelector('input[name="pet_name[]"]')) || 'Pet ' + (index + 1);
            var box = document.createElement('div');
            var title = document.createElement('h5');
            var restrictions = document.createElement('textarea');

            box.className = 'dps-product-prefs-pet';
            title.className = 'dps-product-prefs-pet__title';
            title.textContent = petName;

            box.appendChild(title);
            box.appendChild(createField('Preferência de shampoo', createSelect('pet_shampoo_pref[]', [
                { value: '', label: 'Sem preferência específica' },
                { value: 'hipoalergenico', label: 'Hipoalergênico' },
                { value: 'antisseptico', label: 'Antisséptico' },
                { value: 'pelagem_branca', label: 'Para pelagem branca' },
                { value: 'pelagem_escura', label: 'Para pelagem escura' },
                { value: 'antipulgas', label: 'Antipulgas' },
                { value: 'hidratante', label: 'Hidratante' },
                { value: 'outro', label: 'Outro' }
            ])));
            box.appendChild(createField('Preferência de perfume', createSelect('pet_perfume_pref[]', [
                { value: '', label: 'Sem preferência' },
                { value: 'suave', label: 'Perfume suave' },
                { value: 'intenso', label: 'Perfume intenso' },
                { value: 'sem_perfume', label: 'Sem perfume' },
                { value: 'hipoalergenico', label: 'Hipoalergênico apenas' }
            ])));
            box.appendChild(createField('Adereços', createSelect('pet_accessories_pref[]', [
                { value: '', label: 'Sem preferência' },
                { value: 'lacinho', label: 'Lacinho' },
                { value: 'gravata', label: 'Gravata' },
                { value: 'lenco', label: 'Lenço' },
                { value: 'bandana', label: 'Bandana' },
                { value: 'sem_aderecos', label: 'Não usar adereços' }
            ])));

            restrictions.name = 'pet_product_restrictions[]';
            restrictions.rows = 2;
            restrictions.placeholder = 'Alergias, restrições ou orientações de produtos';
            box.appendChild(createField('Restrições ou observações', restrictions));
            wrapper.appendChild(box);
        });
    }

    function addSummaryItem(list, label, value) {
        if (!value) {
            return;
        }

        var item = document.createElement('li');
        var strong = document.createElement('strong');
        strong.textContent = label + ':';
        item.appendChild(strong);
        item.appendChild(document.createTextNode(' ' + value));
        list.appendChild(item);
    }

    function getSelectText(select) {
        if (!select || !select.options) {
            return '';
        }

        var option = select.options[select.selectedIndex];
        return option ? option.text.trim() : '';
    }

    function formatDate(value) {
        var parts = value ? value.split('-') : [];
        return parts.length === 3 ? parts[2] + '/' + parts[1] + '/' + parts[0] : value;
    }

    function appendSummarySection(container, titleText, list) {
        if (!list.children.length) {
            return;
        }

        var section = document.createElement('div');
        var title = document.createElement('h5');
        title.textContent = titleText;
        section.className = 'dps-summary-section';
        section.appendChild(title);
        section.appendChild(list);
        container.appendChild(section);
    }

    function buildSummary(form) {
        var container = form.querySelector('#dps-summary-content');
        if (!container) {
            return;
        }

        container.innerHTML = '';

        var tutorList = document.createElement('ul');
        addSummaryItem(tutorList, 'Nome', getTrimmedValue(form.querySelector('input[name="client_name"]')));
        addSummaryItem(tutorList, 'CPF', getTrimmedValue(form.querySelector('input[name="client_cpf"]')));
        addSummaryItem(tutorList, 'Telefone', getTrimmedValue(form.querySelector('input[name="client_phone"]')));
        addSummaryItem(tutorList, 'Email', getTrimmedValue(form.querySelector('input[name="client_email"]')));
        addSummaryItem(tutorList, 'Nascimento', formatDate(getTrimmedValue(form.querySelector('input[name="client_birth"]'))));
        addSummaryItem(tutorList, 'Instagram', getTrimmedValue(form.querySelector('input[name="client_instagram"]')));
        addSummaryItem(tutorList, 'Facebook', getTrimmedValue(form.querySelector('input[name="client_facebook"]')));
        addSummaryItem(tutorList, 'Endereço', getTrimmedValue(form.querySelector('[name="client_address"]')));
        addSummaryItem(tutorList, 'Como conheceu', getTrimmedValue(form.querySelector('input[name="client_referral"]')));

        if (form.querySelector('input[name="client_photo_auth"]') && form.querySelector('input[name="client_photo_auth"]').checked) {
            addSummaryItem(tutorList, 'Fotos nas redes sociais', 'Autorizado');
        }

        appendSummarySection(container, 'Tutor', tutorList);

        var petsWrapper = form.querySelector('#dps-pets-wrapper');
        var pets = petsWrapper ? toArray(petsWrapper.querySelectorAll('.dps-pet-fieldset')) : [];
        var prefsWrapper = form.querySelector('#dps-product-prefs-wrapper');
        var shampooPrefs = prefsWrapper ? toArray(prefsWrapper.querySelectorAll('select[name="pet_shampoo_pref[]"]')) : [];
        var perfumePrefs = prefsWrapper ? toArray(prefsWrapper.querySelectorAll('select[name="pet_perfume_pref[]"]')) : [];
        var accessoriesPrefs = prefsWrapper ? toArray(prefsWrapper.querySelectorAll('select[name="pet_accessories_pref[]"]')) : [];
        var restrictions = prefsWrapper ? toArray(prefsWrapper.querySelectorAll('textarea[name="pet_product_restrictions[]"]')) : [];

        pets.forEach(function(fieldset, index) {
            var list = document.createElement('ul');
            var aggressive = fieldset.querySelector('input[name^="pet_aggressive"]');

            addSummaryItem(list, 'Nome', getTrimmedValue(fieldset.querySelector('input[name="pet_name[]"]')));
            addSummaryItem(list, 'Espécie', getSelectText(fieldset.querySelector('select[name="pet_species[]"]')));
            addSummaryItem(list, 'Raça', getTrimmedValue(fieldset.querySelector('input[name="pet_breed[]"]')));
            addSummaryItem(list, 'Porte', getSelectText(fieldset.querySelector('select[name="pet_size[]"]')));
            addSummaryItem(list, 'Peso', getTrimmedValue(fieldset.querySelector('input[name="pet_weight[]"]')));
            addSummaryItem(list, 'Pelagem', getTrimmedValue(fieldset.querySelector('input[name="pet_coat[]"]')));
            addSummaryItem(list, 'Cor', getTrimmedValue(fieldset.querySelector('input[name="pet_color[]"]')));
            addSummaryItem(list, 'Nascimento', formatDate(getTrimmedValue(fieldset.querySelector('input[name="pet_birth[]"]'))));
            addSummaryItem(list, 'Sexo', getSelectText(fieldset.querySelector('select[name="pet_sex[]"]')));
            addSummaryItem(list, 'Cuidados especiais', getTrimmedValue(fieldset.querySelector('textarea[name="pet_care[]"]')));

            if (aggressive && aggressive.checked) {
                addSummaryItem(list, 'Atenção', 'Pet agressivo');
            }

            addSummaryItem(list, 'Shampoo', getSelectText(shampooPrefs[index]));
            addSummaryItem(list, 'Perfume', getSelectText(perfumePrefs[index]));
            addSummaryItem(list, 'Adereços', getSelectText(accessoriesPrefs[index]));
            addSummaryItem(list, 'Restrições de produtos', getTrimmedValue(restrictions[index]));
            appendSummarySection(container, 'Pet ' + (index + 1), list);
        });

        if (!container.children.length) {
            var empty = document.createElement('p');
            empty.className = 'dps-summary-empty';
            empty.textContent = 'Preencha os campos para visualizar o resumo.';
            container.appendChild(empty);
        }
    }

    function showLoading(button) {
        if (!button) {
            return;
        }

        button.dataset.originalText = button.textContent;
        button.textContent = CONFIG.LOADING_TEXT;
        button.classList.add('dps-loading');
        button.disabled = true;
    }

    function hideLoading(button) {
        if (!button) {
            return;
        }

        button.textContent = button.dataset.originalText || CONFIG.SUBMIT_TEXT;
        button.classList.remove('dps-loading');
        button.disabled = false;
    }

    function createModalButton(className, text) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = className;
        button.textContent = text;
        return button;
    }

    function showDuplicateModal(data, i18n, onContinue, onCancel) {
        var existing = document.getElementById('dps-duplicate-modal');
        if (existing && existing.parentNode) {
            existing.parentNode.removeChild(existing);
        }

        var overlay = document.createElement('div');
        var modal = document.createElement('div');
        var header = document.createElement('div');
        var title = document.createElement('h3');
        var body = document.createElement('div');
        var message = document.createElement('p');
        var badges = document.createElement('div');
        var clientInfo = document.createElement('div');
        var clientLabel = document.createElement('div');
        var clientName = document.createElement('div');
        var footer = document.createElement('div');
        var cancelBtn = createModalButton('dps-modal-btn-cancel', i18n.cancelButton || 'Cancelar');
        var continueBtn = createModalButton('dps-modal-btn-continue', i18n.continueButton || 'Continuar');
        var viewBtn = document.createElement('a');

        overlay.id = 'dps-duplicate-modal';
        overlay.className = 'dps-modal-overlay';
        modal.className = 'dps-modal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-labelledby', 'dps-modal-title');
        header.className = 'dps-modal-header';
        title.id = 'dps-modal-title';
        title.textContent = i18n.modalTitle || 'Cliente já cadastrado';
        body.className = 'dps-modal-body';
        message.textContent = i18n.modalMessage || 'Já existe um cliente cadastrado com dados iguais.';
        badges.className = 'dps-modal-duplicate-fields';
        clientInfo.className = 'dps-modal-client-info';
        clientLabel.className = 'dps-client-label';
        clientLabel.textContent = i18n.clientLabel || 'Cliente existente';
        clientName.className = 'dps-client-name';
        clientName.textContent = data.client_name || ('ID: ' + data.client_id);
        footer.className = 'dps-modal-footer';
        viewBtn.className = 'dps-modal-btn-view';
        viewBtn.textContent = i18n.viewClientButton || 'Ver cadastro';
        viewBtn.href = data.view_url || '#';

        toArray(data.duplicated_fields || []).forEach(function(field) {
            var badge = document.createElement('span');
            badge.className = 'dps-duplicate-badge';
            badge.textContent = field;
            badges.appendChild(badge);
        });

        header.appendChild(title);
        body.appendChild(message);
        body.appendChild(badges);
        clientInfo.appendChild(clientLabel);
        clientInfo.appendChild(clientName);
        body.appendChild(clientInfo);
        footer.appendChild(cancelBtn);
        footer.appendChild(viewBtn);
        footer.appendChild(continueBtn);
        modal.appendChild(header);
        modal.appendChild(body);
        modal.appendChild(footer);
        overlay.appendChild(modal);
        document.body.appendChild(overlay);

        function close() {
            document.removeEventListener('keydown', handleKeydown);
            if (overlay.parentNode) {
                overlay.parentNode.removeChild(overlay);
            }
        }

        function handleCancel() {
            close();
            if (typeof onCancel === 'function') {
                onCancel();
            }
        }

        function handleKeydown(event) {
            if (event.key === 'Escape') {
                handleCancel();
            }
        }

        cancelBtn.addEventListener('click', handleCancel);
        overlay.addEventListener('click', function(event) {
            if (event.target === overlay) {
                handleCancel();
            }
        });
        continueBtn.addEventListener('click', function() {
            close();
            if (typeof onContinue === 'function') {
                onContinue();
            }
        });
        document.addEventListener('keydown', handleKeydown);
        cancelBtn.focus();
    }

    function checkDuplicate(form, submitButton, email, phone, cpf, config, callback) {
        if (!email && !phone && !cpf) {
            callback(true, false);
            return;
        }

        if (submitButton) {
            submitButton.textContent = config.i18n.checkingMessage || 'Verificando...';
        }

        var formData = new FormData();
        formData.append('action', config.action);
        formData.append('nonce', config.nonce);
        formData.append('email', email);
        formData.append('phone', phone);
        formData.append('cpf', cpf);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', config.ajaxUrl, true);
        xhr.onload = function() {
            if (xhr.status < 200 || xhr.status >= 400) {
                callback(true, false);
                return;
            }

            try {
                var response = JSON.parse(xhr.responseText);
                if (response.success && response.data && response.data.is_duplicate) {
                    showDuplicateModal(response.data, config.i18n || {}, function() {
                        callback(true, true);
                    }, function() {
                        callback(false, true);
                    });
                    return;
                }
            } catch (e) {
                callback(true, false);
                return;
            }

            callback(true, false);
        };
        xhr.onerror = function() {
            callback(true, false);
        };
        xhr.send(formData);
    }

    function requestSubmit(form) {
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
            return;
        }

        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    }

    function bindWizard(form) {
        var progress = {
            label: document.getElementById('dps-step-label'),
            counter: document.getElementById('dps-step-counter'),
            bar: document.getElementById('dps-progress-bar-fill')
        };
        var nextButton = document.getElementById('dps-next-step');
        var nextButton2 = document.getElementById('dps-next-step-2');
        var backButton = document.getElementById('dps-back-step');
        var backButton2 = document.getElementById('dps-back-step-2');
        var submitButton = form.querySelector('button[type="submit"]');
        var confirmCheckbox = document.getElementById('dps-summary-confirm');

        showStep(form, 1, progress);

        if (nextButton) {
            nextButton.addEventListener('click', function() {
                clearJSErrors(form);
                if (validateStepOne(form, false)) {
                    showStep(form, 2, progress);
                }
            });
        }

        if (nextButton2) {
            nextButton2.addEventListener('click', function() {
                clearJSErrors(form);
                if (validateStepTwo(form, false)) {
                    if (confirmCheckbox) {
                        confirmCheckbox.checked = false;
                    }
                    if (submitButton) {
                        submitButton.disabled = true;
                    }
                    showStep(form, 3, progress);
                }
            });
        }

        if (backButton) {
            backButton.addEventListener('click', function() {
                showStep(form, 1, progress);
            });
        }

        if (backButton2) {
            backButton2.addEventListener('click', function() {
                showStep(form, 2, progress);
            });
        }

        if (confirmCheckbox && submitButton) {
            submitButton.disabled = !confirmCheckbox.checked;
            confirmCheckbox.addEventListener('change', function() {
                submitButton.disabled = !confirmCheckbox.checked;
            });
        }

        form.addEventListener('input', function() {
            if (state.currentStep === 3) {
                buildSummary(form);
            }
        });
    }

    function handleRecaptcha(form, submitButton, config) {
        var tokenInput = form.querySelector('input[name="dps_recaptcha_token"]');
        var actionInput = form.querySelector('input[name="dps_recaptcha_action"]');

        if (!config || !config.enabled || state.recaptchaValidated) {
            return true;
        }

        if (typeof window.grecaptcha === 'undefined' || !window.grecaptcha.execute) {
            hideLoading(submitButton);
            renderErrors(form, [ config.unavailableMessage || 'Não foi possível carregar o verificador anti-spam.' ]);
            return false;
        }

        if (actionInput && config.action) {
            actionInput.value = config.action;
        }

        window.grecaptcha.ready(function() {
            window.grecaptcha.execute(config.siteKey, { action: config.action }).then(function(token) {
                if (tokenInput) {
                    tokenInput.value = token;
                }
                state.recaptchaValidated = true;
                form.submit();
            }).catch(function() {
                hideLoading(submitButton);
                renderErrors(form, [ config.errorMessage || 'Não foi possível validar o anti-spam.' ]);
            });
        });

        return false;
    }

    function bindSubmit(form) {
        var submitButton = form.querySelector('button[type="submit"]');
        var data = getRegistrationData();
        var recaptchaConfig = data.recaptcha || null;
        var duplicateConfig = data.duplicateCheck || null;

        form.addEventListener('submit', function(event) {
            if (!validateForm(form)) {
                event.preventDefault();
                hideLoading(submitButton);
                return false;
            }

            showLoading(submitButton);

            if (duplicateConfig && duplicateConfig.enabled && !state.duplicateConfirmed) {
                event.preventDefault();

                checkDuplicate(
                    form,
                    submitButton,
                    getTrimmedValue(form.querySelector('input[name="client_email"]')),
                    getTrimmedValue(form.querySelector('input[name="client_phone"]')),
                    getTrimmedValue(form.querySelector('input[name="client_cpf"]')),
                    duplicateConfig,
                    function(shouldContinue, wasDuplicate) {
                        if (!shouldContinue) {
                            hideLoading(submitButton);
                            return;
                        }

                        state.duplicateConfirmed = true;
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

                        hideLoading(submitButton);
                        requestSubmit(form);
                    }
                );

                return false;
            }

            if (recaptchaConfig && recaptchaConfig.enabled && !state.recaptchaValidated) {
                event.preventDefault();
                if (!handleRecaptcha(form, submitButton, recaptchaConfig)) {
                    return false;
                }
            }

            return true;
        });
    }

    function init(root) {
        var scope = root || document;
        var form = scope.querySelector ? scope.querySelector('#dps-reg-form') : document.getElementById('dps-reg-form');

        initSignatureForms(scope);
        initGooglePlaces(scope);

        if (!form || form.dataset.dpsRegistrationReady === '1') {
            return;
        }

        form.dataset.dpsRegistrationReady = '1';
        bindMaskedInput(form.querySelector('input[name="client_cpf"]'), 'cpf');
        bindMaskedInput(form.querySelector('input[name="client_phone"]'), 'phone');
        initBreedSelectors(form);
        bindWizard(form);
        bindSubmit(form);

        var template = document.getElementById('dps-pet-template');
        if (template) {
            initPetClone(template.textContent, function() {
                initBreedSelectors(form);
                if (state.currentStep === 3) {
                    renderProductPrefsStep(form);
                    buildSummary(form);
                }
            });
        }
    }

    window.DPSRegistration = {
        init: init,
        initPetClone: initPetClone,
        initGooglePlaces: initGooglePlaces,
        validateCPF: validateCPF,
        validatePhone: validatePhone,
        validateEmail: validateEmail
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            init(document);
        });
    } else {
        init(document);
    }
}());
