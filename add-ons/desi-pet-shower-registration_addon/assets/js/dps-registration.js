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
     * Validate form before submission.
     *
     * @param {HTMLFormElement} form - The form element.
     * @return {boolean} True if valid.
     */
    function validateForm(form) {
        clearJSErrors(form);
        
        var errors = [];
        
        // Get error container (create if not exists)
        var errorContainer = form.querySelector('.dps-js-errors');
        if (!errorContainer) {
            errorContainer = document.createElement('div');
            errorContainer.className = 'dps-js-errors';
            errorContainer.setAttribute('role', 'alert');
            errorContainer.setAttribute('aria-live', 'polite');
            form.insertBefore(errorContainer, form.firstChild);
        }
        
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
    function initPetClone(templateJson) {
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
        
        applyCPFMask(cpfInput);
        applyPhoneMask(phoneInput);
        
        // F2.2 & F2.4: Form validation and loading on submit
        var submitButton = form.querySelector('button[type="submit"]');
        
        form.addEventListener('submit', function(e) {
            // Validate form
            if (!validateForm(form)) {
                e.preventDefault();
                hideLoading(submitButton);
                return false;
            }
            
            // Show loading
            showLoading(submitButton);
            
            // Allow form to submit
            return true;
        });
        
        // Initialize pet clone if template is available
        var templateElement = document.getElementById('dps-pet-template');
        if (templateElement) {
            initPetClone(templateElement.textContent);
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
