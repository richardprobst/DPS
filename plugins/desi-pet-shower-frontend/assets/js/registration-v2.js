/**
 * Registration V2 — JavaScript (Vanilla)
 *
 * Comportamento nativo para o formulário de cadastro V2.
 * Zero dependência de jQuery.
 *
 * Features:
 * - Client-side validation
 * - Pet repeater (add/remove pets)
 * - Breed datalist (species-dependent)
 * - Phone mask
 * - CPF mask
 * - reCAPTCHA v3 integration
 * - Form submit loader
 * - Dismissible alerts
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 */

( function() {
    'use strict';

    /**
     * Inicializa comportamentos do formulário de cadastro V2.
     */
    function init() {
        var forms = document.querySelectorAll( '.dps-v2-registration__form' );

        forms.forEach( function( form ) {
            initPetRepeater( form );
            initBreedDatalist( form );
            initPhoneMask( form );
            initCpfMask( form );
            initFormValidation( form );
            initFormSubmitLoader( form );
            initRecaptcha( form );
            initDismissibleAlerts( form.closest( '.dps-v2-registration' ) );
        } );
    }

    /**
     * Pet repeater — add/remove pet entries.
     *
     * @param {HTMLFormElement} form
     */
    function initPetRepeater( form ) {
        var addBtn = document.getElementById( 'dps-v2-add-pet' );
        if ( ! addBtn ) {
            return;
        }

        var repeater = document.getElementById( 'dps-v2-pet-repeater' );
        if ( ! repeater ) {
            return;
        }

        addBtn.addEventListener( 'click', function() {
            var entries = repeater.querySelectorAll( '.dps-v2-pet-entry' );
            var newIndex = entries.length;
            var template = entries[0];

            if ( ! template ) {
                return;
            }

            var clone = template.cloneNode( true );
            clone.setAttribute( 'data-pet-index', newIndex );

            // Update names, ids, clear values
            var inputs = clone.querySelectorAll( 'input, select, textarea' );
            inputs.forEach( function( input ) {
                var name = input.getAttribute( 'name' );
                if ( name ) {
                    input.setAttribute( 'name', name.replace( /pets\[\d+\]/, 'pets[' + newIndex + ']' ) );
                }
                var id = input.getAttribute( 'id' );
                if ( id ) {
                    input.setAttribute( 'id', id.replace( /_\d+$/, '_' + newIndex ) );
                }
                // Clear values
                if ( 'SELECT' === input.tagName ) {
                    input.selectedIndex = 0;
                } else {
                    input.value = '';
                }
            } );

            // Update labels
            var labels = clone.querySelectorAll( 'label' );
            labels.forEach( function( label ) {
                var forAttr = label.getAttribute( 'for' );
                if ( forAttr ) {
                    label.setAttribute( 'for', forAttr.replace( /_\d+$/, '_' + newIndex ) );
                }
            } );

            // Update datalist id
            var datalist = clone.querySelector( 'datalist' );
            if ( datalist ) {
                datalist.id = 'dps-v2-breed-list-' + newIndex;
                var breedInput = clone.querySelector( '.dps-v2-breed-input' );
                if ( breedInput ) {
                    breedInput.setAttribute( 'list', datalist.id );
                }
            }

            // Add header with pet number and remove button
            var header = clone.querySelector( '.dps-v2-pet-entry__header' );
            if ( ! header ) {
                header = document.createElement( 'div' );
                header.className = 'dps-v2-pet-entry__header';
                clone.insertBefore( header, clone.firstChild );
            }
            header.innerHTML = '<span class="dps-v2-typescale-title-medium">Pet #' + ( newIndex + 1 ) + '</span>' +
                '<button type="button" class="dps-v2-button dps-v2-button--text dps-v2-pet-remove">' +
                '<span class="dps-v2-button__text">Remover</span></button>';

            repeater.appendChild( clone );

            // Bind remove
            initRemoveButtons( repeater );
            // Rebind breed datalist
            initBreedDatalist( form );
        } );

        initRemoveButtons( repeater );
    }

    /**
     * Bind remove buttons for pet entries.
     *
     * @param {HTMLElement} repeater
     */
    function initRemoveButtons( repeater ) {
        var removeBtns = repeater.querySelectorAll( '.dps-v2-pet-remove' );
        removeBtns.forEach( function( btn ) {
            btn.onclick = function() {
                var entry = btn.closest( '.dps-v2-pet-entry' );
                if ( entry && repeater.querySelectorAll( '.dps-v2-pet-entry' ).length > 1 ) {
                    entry.remove();
                    reindexPets( repeater );
                }
            };
        } );
    }

    /**
     * Reindex pet entries after removal.
     *
     * @param {HTMLElement} repeater
     */
    function reindexPets( repeater ) {
        var entries = repeater.querySelectorAll( '.dps-v2-pet-entry' );
        entries.forEach( function( entry, index ) {
            entry.setAttribute( 'data-pet-index', index );
            var inputs = entry.querySelectorAll( 'input, select, textarea' );
            inputs.forEach( function( input ) {
                var name = input.getAttribute( 'name' );
                if ( name ) {
                    input.setAttribute( 'name', name.replace( /pets\[\d+\]/, 'pets[' + index + ']' ) );
                }
            } );
        } );
    }

    /**
     * Breed datalist — updates options based on selected species.
     *
     * @param {HTMLFormElement} form
     */
    function initBreedDatalist( form ) {
        var repeater = document.getElementById( 'dps-v2-pet-repeater' );
        if ( ! repeater ) {
            return;
        }

        var breedsData = {};
        try {
            breedsData = JSON.parse( repeater.getAttribute( 'data-breeds' ) || '{}' );
        } catch ( e ) {
            return;
        }

        var speciesSelects = form.querySelectorAll( '.dps-v2-species-select' );
        speciesSelects.forEach( function( select ) {
            select.addEventListener( 'change', function() {
                updateBreedList( select, breedsData );
            } );
            // Initial populate
            if ( select.value ) {
                updateBreedList( select, breedsData );
            }
        } );
    }

    /**
     * @param {HTMLSelectElement} select
     * @param {Object} breedsData
     */
    function updateBreedList( select, breedsData ) {
        var entry = select.closest( '.dps-v2-pet-entry' );
        if ( ! entry ) {
            return;
        }

        var breedInput = entry.querySelector( '.dps-v2-breed-input' );
        var datalist = entry.querySelector( 'datalist' );
        if ( ! breedInput || ! datalist ) {
            return;
        }

        var species = select.value;
        var breeds = breedsData[ species ] || [];

        datalist.innerHTML = '';
        breeds.forEach( function( breed ) {
            var option = document.createElement( 'option' );
            option.value = breed;
            datalist.appendChild( option );
        } );
    }

    /**
     * Phone mask — formats as (XX) XXXXX-XXXX.
     *
     * @param {HTMLFormElement} form
     */
    function initPhoneMask( form ) {
        var phoneInput = form.querySelector( '#dps-v2-client_phone' );
        if ( ! phoneInput ) {
            return;
        }

        phoneInput.addEventListener( 'input', function() {
            var digits = phoneInput.value.replace( /\D/g, '' );
            if ( digits.length > 11 ) {
                digits = digits.substring( 0, 11 );
            }

            if ( digits.length > 6 ) {
                phoneInput.value = '(' + digits.substring( 0, 2 ) + ') ' + digits.substring( 2, 7 ) + '-' + digits.substring( 7 );
            } else if ( digits.length > 2 ) {
                phoneInput.value = '(' + digits.substring( 0, 2 ) + ') ' + digits.substring( 2 );
            } else if ( digits.length > 0 ) {
                phoneInput.value = '(' + digits;
            }
        } );
    }

    /**
     * CPF mask — formats as XXX.XXX.XXX-XX.
     *
     * @param {HTMLFormElement} form
     */
    function initCpfMask( form ) {
        var cpfInput = form.querySelector( '[data-mask="cpf"]' );
        if ( ! cpfInput ) {
            return;
        }

        cpfInput.addEventListener( 'input', function() {
            var digits = cpfInput.value.replace( /\D/g, '' );
            if ( digits.length > 11 ) {
                digits = digits.substring( 0, 11 );
            }

            if ( digits.length > 9 ) {
                cpfInput.value = digits.substring( 0, 3 ) + '.' + digits.substring( 3, 6 ) + '.' + digits.substring( 6, 9 ) + '-' + digits.substring( 9 );
            } else if ( digits.length > 6 ) {
                cpfInput.value = digits.substring( 0, 3 ) + '.' + digits.substring( 3, 6 ) + '.' + digits.substring( 6 );
            } else if ( digits.length > 3 ) {
                cpfInput.value = digits.substring( 0, 3 ) + '.' + digits.substring( 3 );
            }
        } );
    }

    /**
     * Client-side validation.
     *
     * @param {HTMLFormElement} form
     */
    function initFormValidation( form ) {
        form.addEventListener( 'submit', function( e ) {
            var errors = [];

            var name = form.querySelector( '#dps-v2-client_name' );
            if ( name && '' === name.value.trim() ) {
                errors.push( { field: name, message: 'Nome é obrigatório.' } );
            }

            var email = form.querySelector( '#dps-v2-client_email' );
            if ( email && '' === email.value.trim() ) {
                errors.push( { field: email, message: 'Email é obrigatório.' } );
            } else if ( email && ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( email.value ) ) {
                errors.push( { field: email, message: 'Email não é válido.' } );
            }

            var phone = form.querySelector( '#dps-v2-client_phone' );
            if ( phone && '' === phone.value.trim() ) {
                errors.push( { field: phone, message: 'Telefone é obrigatório.' } );
            }

            // Validate pet names
            var petNames = form.querySelectorAll( '[name$="[pet_name]"]' );
            petNames.forEach( function( input, idx ) {
                if ( '' === input.value.trim() ) {
                    errors.push( { field: input, message: 'Nome do pet #' + ( idx + 1 ) + ' é obrigatório.' } );
                }
            } );

            if ( errors.length > 0 ) {
                e.preventDefault();

                // Clear previous errors
                form.querySelectorAll( '.dps-v2-field--error' ).forEach( function( el ) {
                    el.classList.remove( 'dps-v2-field--error' );
                } );
                form.querySelectorAll( '.dps-v2-field__error[data-js]' ).forEach( function( el ) {
                    el.remove();
                } );

                // Show errors
                errors.forEach( function( err ) {
                    var field = err.field.closest( '.dps-v2-field' );
                    if ( field ) {
                        field.classList.add( 'dps-v2-field--error' );
                        var errSpan = document.createElement( 'span' );
                        errSpan.className = 'dps-v2-field__error';
                        errSpan.setAttribute( 'role', 'alert' );
                        errSpan.setAttribute( 'data-js', '1' );
                        errSpan.textContent = err.message;
                        field.appendChild( errSpan );
                    }
                } );

                // Scroll to first error
                errors[0].field.scrollIntoView( { behavior: 'smooth', block: 'center' } );
                errors[0].field.focus();
            }
        } );
    }

    /**
     * Mostra loader no botão ao submeter o formulário.
     *
     * @param {HTMLFormElement} form
     */
    function initFormSubmitLoader( form ) {
        form.addEventListener( 'submit', function() {
            var buttons = form.querySelectorAll( '.dps-v2-button--loading' );
            buttons.forEach( function( btn ) {
                btn.classList.add( 'dps-v2-button--submitting' );
                btn.setAttribute( 'disabled', 'disabled' );
            } );
        } );
    }

    /**
     * reCAPTCHA v3 — execute before submit.
     *
     * @param {HTMLFormElement} form
     */
    function initRecaptcha( form ) {
        var siteKey = form.getAttribute( 'data-recaptcha-site-key' );
        if ( ! siteKey || typeof grecaptcha === 'undefined' ) {
            return;
        }

        form.addEventListener( 'submit', function( e ) {
            var tokenInput = document.getElementById( 'dps-v2-recaptcha-token' );
            if ( ! tokenInput || tokenInput.value ) {
                return; // Already has token
            }

            e.preventDefault();

            grecaptcha.ready( function() {
                grecaptcha.execute( siteKey, { action: 'dps_registration' } ).then( function( token ) {
                    tokenInput.value = token;
                    form.submit();
                } );
            } );
        } );
    }

    /**
     * Habilita fechar alertas dismissíveis.
     *
     * @param {HTMLElement|null} container
     */
    function initDismissibleAlerts( container ) {
        if ( ! container ) {
            return;
        }

        var dismissBtns = container.querySelectorAll( '.dps-v2-alert__dismiss' );
        dismissBtns.forEach( function( btn ) {
            btn.addEventListener( 'click', function() {
                var alert = btn.closest( '.dps-v2-alert' );
                if ( alert ) {
                    alert.remove();
                }
            } );
        } );
    }

    // Init on DOM ready
    if ( 'loading' === document.readyState ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }

} )();
