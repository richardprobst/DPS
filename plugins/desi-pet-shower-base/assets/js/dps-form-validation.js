(function () {
    'use strict';

    const defaults = {
        generic: {
            formErrorsTitle: 'Por favor, corrija os campos destacados:'
        },
        client: {
            nameRequired: 'Informe o nome do cliente.',
            phoneRequired: 'Informe o telefone/WhatsApp do cliente.'
        },
        pet: {
            nameRequired: 'Informe o nome do pet.',
            ownerRequired: 'Selecione o cliente (tutor) do pet.',
            speciesRequired: 'Selecione a espécie do pet.',
            sexRequired: 'Selecione o sexo do pet.',
            sizeRequired: 'Selecione o porte do pet.'
        },
        appointment: {
            clientRequired: 'Selecione um cliente para o agendamento.',
            petRequired: 'Selecione pelo menos um pet.',
            dateRequired: 'Informe a data do agendamento.',
            timeRequired: 'Informe o horário do agendamento.',
            frequencyRequired: 'Selecione a frequência da assinatura.',
            errorTitle: 'Corrija os erros para continuar.'
        }
    };

    function getMessages() {
        return window.dpsFormL10n ? { ...defaults, ...window.dpsFormL10n } : defaults;
    }

    function createErrorContainer(form) {
        let container = form.querySelector('.dps-form-errors');
        if (!container) {
            container = document.createElement('div');
            container.className = 'dps-form-errors';
            container.setAttribute('role', 'alert');
            container.setAttribute('aria-live', 'assertive');
            form.insertBefore(container, form.firstChild);
        }
        return container;
    }

    function clearErrors(form) {
        form.querySelectorAll('.dps-inline-error').forEach((el) => el.remove());
        form.querySelectorAll('.dps-field-error').forEach((el) => el.classList.remove('dps-field-error'));
        const container = form.querySelector('.dps-form-errors');
        if (container) {
            container.innerHTML = '';
            container.style.display = 'none';
        }
    }

    function addInlineError(target, message) {
        if (!target) {
            return;
        }
        target.classList.add('dps-field-error');
        const error = document.createElement('span');
        error.className = 'dps-inline-error';
        error.textContent = message;
        target.insertAdjacentElement('afterend', error);
    }

    function renderErrors(form, errors) {
        if (!errors.length) {
            return;
        }

        const messages = getMessages();
        const container = createErrorContainer(form);
        const title = messages.generic?.formErrorsTitle || defaults.generic.formErrorsTitle;
        const list = document.createElement('ul');

        errors.forEach((error) => {
            if (error.field) {
                addInlineError(error.field, error.message);
            }
            const item = document.createElement('li');
            item.textContent = error.message;
            list.appendChild(item);
        });

        container.innerHTML = '';
        const heading = document.createElement('p');
        heading.className = 'dps-form-errors__title';
        heading.textContent = title;
        container.appendChild(heading);
        container.appendChild(list);
        container.style.display = 'block';
    }

    function scrollToElement(element) {
        if (!element) {
            return;
        }
        const yOffset = -20;
        const rect = element.getBoundingClientRect();
        const offsetTop = rect.top + window.pageYOffset + yOffset;
        window.scrollTo({ top: offsetTop, behavior: 'smooth' });
    }

    function scrollToFirstError(form) {
        const target = form.querySelector('.dps-field-error') || form.querySelector('.dps-form-errors');
        if (target) {
            scrollToElement(target);
        } else {
            scrollToServerAlert();
        }
    }

    function scrollToServerAlert() {
        const alert = document.querySelector('.dps-alert--danger');
        if (alert) {
            scrollToElement(alert);
        }
    }

    function validateClientForm(form) {
        const messages = getMessages();
        const errors = [];
        const name = form.querySelector('input[name="client_name"]');
        const phone = form.querySelector('input[name="client_phone"]');

        if (name && !name.value.trim()) {
            errors.push({ field: name, message: messages.client?.nameRequired || defaults.client.nameRequired });
        }

        if (phone && !phone.value.trim()) {
            errors.push({ field: phone, message: messages.client?.phoneRequired || defaults.client.phoneRequired });
        }

        return errors;
    }

    function validatePetForm(form) {
        const messages = getMessages();
        const errors = [];
        const name = form.querySelector('input[name="pet_name"]');
        const owner = form.querySelector('select[name="owner_id"]');
        const species = form.querySelector('select[name="pet_species"]');
        const sex = form.querySelector('select[name="pet_sex"]');
        const size = form.querySelector('select[name="pet_size"]');

        if (name && !name.value.trim()) {
            errors.push({ field: name, message: messages.pet?.nameRequired || defaults.pet.nameRequired });
        }
        if (owner && !owner.value.trim()) {
            errors.push({ field: owner, message: messages.pet?.ownerRequired || defaults.pet.ownerRequired });
        }
        if (species && !species.value.trim()) {
            errors.push({ field: species, message: messages.pet?.speciesRequired || defaults.pet.speciesRequired });
        }
        if (sex && !sex.value.trim()) {
            errors.push({ field: sex, message: messages.pet?.sexRequired || defaults.pet.sexRequired });
        }
        if (size && !size.value.trim()) {
            errors.push({ field: size, message: messages.pet?.sizeRequired || defaults.pet.sizeRequired });
        }

        return errors;
    }

    function validateAppointmentForm(form) {
        const messages = getMessages();
        const errors = [];
        const client = form.querySelector('select[name="appointment_client_id"]');
        const petCheckboxes = form.querySelectorAll('input[name="appointment_pet_ids[]"]');
        const petWrapper = form.querySelector('#dps-appointment-pet-list') || form.querySelector('#dps-appointment-pet-wrapper');
        const date = form.querySelector('input[name="appointment_date"]');
        const time = form.querySelector('select[name="appointment_time"], input[name="appointment_time"]');
        const type = form.querySelector('input[name="appointment_type"]:checked');
        const frequency = form.querySelector('select[name="appointment_frequency"]');

        if (client && !client.value.trim()) {
            errors.push({ field: client, message: messages.appointment?.clientRequired || defaults.appointment.clientRequired });
        }

        const hasPetsSelected = Array.from(petCheckboxes).some((checkbox) => checkbox.checked);
        if (!hasPetsSelected) {
            errors.push({ field: petWrapper || petCheckboxes[0], message: messages.appointment?.petRequired || defaults.appointment.petRequired });
        }

        if (date && !date.value.trim()) {
            errors.push({ field: date, message: messages.appointment?.dateRequired || defaults.appointment.dateRequired });
        }

        if (time && !time.value.trim()) {
            errors.push({ field: time, message: messages.appointment?.timeRequired || defaults.appointment.timeRequired });
        }

        if (type && type.value === 'subscription' && frequency && !frequency.value.trim()) {
            errors.push({ field: frequency, message: messages.appointment?.frequencyRequired || defaults.appointment.frequencyRequired });
        }

        return errors;
    }

    function attachValidation(form) {
        const actionInput = form.querySelector('input[name="dps_action"]');
        if (!actionInput) {
            return;
        }

        const validators = {
            save_client: validateClientForm,
            save_pet: validatePetForm,
            save_appointment: validateAppointmentForm
        };

        const validator = validators[actionInput.value];
        if (!validator) {
            return;
        }

        form.addEventListener('submit', function (event) {
            clearErrors(form);
            const errors = validator(form);

            if (errors.length) {
                event.preventDefault();
                renderErrors(form, errors);
                scrollToFirstError(form);
            }
        });
    }

    function attachAll(container) {
        const scope = container && container.querySelectorAll ? container : document;
        scope.querySelectorAll('form.dps-form').forEach((form) => attachValidation(form));
    }

    window.DPSFormValidation = {
        attach: attachValidation,
        attachAll: attachAll
    };

    document.addEventListener('DOMContentLoaded', function () {
        attachAll(document);
        scrollToServerAlert();
    });

    document.addEventListener('dps:appointmentFormLoaded', function (event) {
        const target = event && event.detail && event.detail.container ? event.detail.container : document;
        attachAll(target);
    });
})();
