( function() {
    'use strict';

    var googlePlacesPromise = null;

    function toArray( nodeList ) {
        return Array.prototype.slice.call( nodeList || [] );
    }

    function getI18n() {
        return window.dpsRegistrationV2 && window.dpsRegistrationV2.i18n ? window.dpsRegistrationV2.i18n : {};
    }

    function scrollToElement( element ) {
        if ( ! element ) {
            return;
        }

        element.scrollIntoView( {
            behavior: window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ? 'auto' : 'smooth',
            block: 'start',
        } );
    }

    function maskPhone( value ) {
        var digits = String( value || '' ).replace( /\D/g, '' );

        if ( digits.length > 11 ) {
            digits = digits.slice( 0, 11 );
        }

        if ( digits.length > 10 ) {
            return '(' + digits.slice( 0, 2 ) + ') ' + digits.slice( 2, 7 ) + '-' + digits.slice( 7 );
        }

        if ( digits.length > 6 ) {
            return '(' + digits.slice( 0, 2 ) + ') ' + digits.slice( 2, 6 ) + '-' + digits.slice( 6 );
        }

        if ( digits.length > 2 ) {
            return '(' + digits.slice( 0, 2 ) + ') ' + digits.slice( 2 );
        }

        if ( digits.length > 0 ) {
            return '(' + digits;
        }

        return '';
    }

    function maskCpf( value ) {
        var digits = String( value || '' ).replace( /\D/g, '' );

        if ( digits.length > 11 ) {
            digits = digits.slice( 0, 11 );
        }

        if ( digits.length > 9 ) {
            return digits.slice( 0, 3 ) + '.' + digits.slice( 3, 6 ) + '.' + digits.slice( 6, 9 ) + '-' + digits.slice( 9 );
        }

        if ( digits.length > 6 ) {
            return digits.slice( 0, 3 ) + '.' + digits.slice( 3, 6 ) + '.' + digits.slice( 6 );
        }

        if ( digits.length > 3 ) {
            return digits.slice( 0, 3 ) + '.' + digits.slice( 3 );
        }

        return digits;
    }

    function applyMaskedValue( input, formatter ) {
        var cursor = input.selectionStart || 0;
        var previousLength = input.value.length;
        input.value = formatter( input.value );
        var newLength = input.value.length;
        var nextCursor = cursor + ( newLength - previousLength );

        if ( input === document.activeElement && typeof input.setSelectionRange === 'function' ) {
            input.setSelectionRange( nextCursor, nextCursor );
        }
    }

    function initMasks( root ) {
        var scope = root || document;
        toArray( scope.querySelectorAll( '[data-dps-mask]' ) ).forEach( function( input ) {
            if ( input.dataset.dpsMaskReady === '1' ) {
                return;
            }

            var type = input.getAttribute( 'data-dps-mask' );
            var formatter = type === 'phone' ? maskPhone : ( type === 'cpf' ? maskCpf : null );

            if ( ! formatter ) {
                return;
            }

            input.dataset.dpsMaskReady = '1';
            applyMaskedValue( input, formatter );
            input.addEventListener( 'input', function() {
                applyMaskedValue( input, formatter );
            } );
        } );
    }

    function parseBreedMap( value ) {
        if ( ! value ) {
            return {};
        }

        try {
            return JSON.parse( value );
        } catch ( error ) {
            return {};
        }
    }

    function normalizeBreedList( source ) {
        if ( Array.isArray( source ) ) {
            return source;
        }

        if ( source && typeof source === 'object' ) {
            return []
                .concat( Array.isArray( source.popular ) ? source.popular : [] )
                .concat( Array.isArray( source.all ) ? source.all : [] );
        }

        return [];
    }

    function populateBreedDatalist( select ) {
        var targetId = select.getAttribute( 'data-dps-breed-target' );
        if ( ! targetId ) {
            return;
        }

        var datalist = document.getElementById( targetId );
        if ( ! datalist ) {
            return;
        }

        var breedMap = parseBreedMap( datalist.getAttribute( 'data-dps-breed-map' ) );
        var currentSpecies = select.value || '';
        var breeds = normalizeBreedList( breedMap[ currentSpecies ] || breedMap.all || [] );
        var seen = {};

        datalist.innerHTML = '';
        breeds.forEach( function( breed ) {
            if ( ! breed || seen[ breed ] ) {
                return;
            }

            seen[ breed ] = true;
            var option = document.createElement( 'option' );
            option.value = breed;
            datalist.appendChild( option );
        } );
    }

    function initBreedDatalists( root ) {
        var scope = root || document;

        toArray( scope.querySelectorAll( '[data-dps-breed-target]' ) ).forEach( function( select ) {
            if ( select.dataset.dpsBreedReady === '1' ) {
                populateBreedDatalist( select );
                return;
            }

            select.dataset.dpsBreedReady = '1';
            populateBreedDatalist( select );
            select.addEventListener( 'change', function() {
                populateBreedDatalist( select );
            } );
        } );
    }

    function loadGooglePlaces( apiKey ) {
        if ( ! apiKey ) {
            return Promise.reject( new Error( 'Google Places API key not provided.' ) );
        }

        if ( window.google && window.google.maps && window.google.maps.places ) {
            return Promise.resolve( window.google.maps.places );
        }

        if ( googlePlacesPromise ) {
            return googlePlacesPromise;
        }

        googlePlacesPromise = new Promise( function( resolve, reject ) {
            var callbackName = 'dpsRegistrationGooglePlacesReady';

            window[ callbackName ] = function() {
                resolve( window.google.maps.places );
            };

            var script = document.createElement( 'script' );
            script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent( apiKey ) + '&libraries=places&callback=' + callbackName;
            script.async = true;
            script.defer = true;
            script.onerror = function() {
                reject( new Error( 'Failed to load Google Places script.' ) );
            };

            document.head.appendChild( script );
        } );

        return googlePlacesPromise;
    }

    function initAddressAutocomplete( root ) {
        var scope = root || document;
        var fields = toArray( scope.querySelectorAll( '[data-dps-address-autocomplete]' ) );

        if ( ! fields.length ) {
            return;
        }

        var apiKey = '';
        fields.some( function( field ) {
            apiKey = field.getAttribute( 'data-dps-google-api-key' ) || '';
            return !!apiKey;
        } );

        if ( ! apiKey ) {
            return;
        }

        loadGooglePlaces( apiKey ).then( function() {
            fields.forEach( function( field ) {
                if ( field.dataset.dpsPlacesReady === '1' ) {
                    return;
                }

                field.dataset.dpsPlacesReady = '1';
                var autocomplete = new window.google.maps.places.Autocomplete( field, {
                    fields: [ 'formatted_address', 'geometry' ],
                    types: [ 'geocode' ],
                } );

                autocomplete.addListener( 'place_changed', function() {
                    var place = autocomplete.getPlace();
                    if ( place && place.formatted_address ) {
                        field.value = place.formatted_address;
                    }

                    var latTargetId = field.getAttribute( 'data-dps-lat-target' );
                    var lngTargetId = field.getAttribute( 'data-dps-lng-target' );
                    var latField = latTargetId ? document.getElementById( latTargetId ) : null;
                    var lngField = lngTargetId ? document.getElementById( lngTargetId ) : null;

                    if ( place && place.geometry && place.geometry.location ) {
                        if ( latField ) {
                            latField.value = String( place.geometry.location.lat() );
                        }

                        if ( lngField ) {
                            lngField.value = String( place.geometry.location.lng() );
                        }
                    }
                } );
            } );
        } ).catch( function() {
            // Falha silenciosa: o campo continua funcionando como texto livre.
        } );
    }

    function initFieldEnhancements( root ) {
        initMasks( root );
        initBreedDatalists( root );
        initAddressAutocomplete( root );
    }

    function getShell( form ) {
        return form.closest( '.dps-registration' );
    }

    function showFormNotice( form, type, message ) {
        var shell = getShell( form );
        var stack = shell ? shell.querySelector( '.dps-registration__notice-stack' ) : null;
        var notice;
        var title;
        var text;

        if ( ! stack || ! message ) {
            return;
        }

        notice = stack.querySelector( '[data-dps-runtime-notice]' );
        if ( ! notice ) {
            notice = document.createElement( 'article' );
            notice.setAttribute( 'data-dps-runtime-notice', '1' );
            notice.className = 'dps-registration-notice';
            title = document.createElement( 'h3' );
            title.className = 'dps-registration-notice__title';
            text = document.createElement( 'p' );
            text.className = 'dps-registration-notice__text';
            notice.appendChild( title );
            notice.appendChild( text );
            stack.appendChild( notice );
        } else {
            title = notice.querySelector( '.dps-registration-notice__title' );
            text = notice.querySelector( '.dps-registration-notice__text' );
        }

        notice.className = 'dps-registration-notice dps-registration-notice--' + ( type || 'error' );
        if ( title ) {
            title.textContent = type === 'warning' ? 'Atenção' : 'Não foi possível concluir';
        }
        if ( text ) {
            text.textContent = message;
        }
    }

    function getPetCards( form ) {
        return toArray( form.querySelectorAll( '[data-pet-index]' ) );
    }

    function buildPetSummary( card ) {
        var speciesField = card.querySelector( '[data-dps-pet-species]' );
        var sizeField = card.querySelector( '[data-dps-pet-size]' );
        var speciesLabel = speciesField && speciesField.selectedOptions.length ? speciesField.selectedOptions[ 0 ].textContent.trim() : '';
        var sizeLabel = sizeField && sizeField.selectedOptions.length ? sizeField.selectedOptions[ 0 ].textContent.trim() : '';
        var parts = [];

        if ( speciesField && speciesField.value && speciesLabel ) {
            parts.push( speciesLabel );
        }

        if ( sizeField && sizeField.value && sizeLabel ) {
            parts.push( sizeLabel );
        }

        return parts.length ? parts.join( ' • ' ) : '';
    }

    function updatePetSummary( summary, value ) {
        if ( ! summary ) {
            return;
        }

        summary.textContent = value || '';
        summary.hidden = ! value;
    }

    function refreshPetCard( card, index, total ) {
        var title = card.querySelector( '[data-dps-pet-title]' );
        var nameField = card.querySelector( '[data-dps-pet-name]' );
        var summary = card.querySelector( '[data-dps-pet-summary]' );
        var eyebrow = card.querySelector( '.dps-registration-pet__eyebrow' );
        var removeButton = card.querySelector( '[data-dps-remove-pet]' );
        var toggle = card.querySelector( '[data-dps-pet-toggle]' );
        var body = card.querySelector( '.dps-registration-pet__body' );

        card.setAttribute( 'data-pet-index', String( index ) );

        if ( eyebrow ) {
            eyebrow.textContent = 'Pet ' + ( index + 1 );
        }

        if ( title ) {
            title.textContent = nameField && nameField.value.trim() ? nameField.value.trim() : 'Novo pet';
        }

        updatePetSummary( summary, buildPetSummary( card ) );

        if ( removeButton ) {
            removeButton.hidden = total <= 1;
        }

        if ( toggle && body ) {
            var bodyId = 'dps-registration-pet-body-' + index;
            toggle.setAttribute( 'aria-controls', bodyId );
            body.id = bodyId;
        }

        toArray( card.querySelectorAll( 'input, select, textarea, datalist' ) ).forEach( function( field ) {
            var name = field.getAttribute( 'name' );
            var id = field.getAttribute( 'id' );
            var list = field.getAttribute( 'list' );
            var describedBy = field.getAttribute( 'aria-describedby' );
            var breedTarget = field.getAttribute( 'data-dps-breed-target' );

            if ( name ) {
                field.setAttribute( 'name', name.replace( /pets\[\d+\]/, 'pets[' + index + ']' ) );
            }

            if ( id ) {
                field.id = id.replace( /-\d+$/, '-' + index );
            }

            if ( list ) {
                field.setAttribute( 'list', list.replace( /-\d+$/, '-' + index ) );
            }

            if ( describedBy ) {
                field.setAttribute( 'aria-describedby', describedBy.replace( /-\d+$/, '-' + index ) );
            }

            if ( breedTarget ) {
                field.setAttribute( 'data-dps-breed-target', breedTarget.replace( /-\d+$/, '-' + index ) );
            }
        } );
    }

    function clearClonedCard( card ) {
        toArray( card.querySelectorAll( '[aria-invalid="true"]' ) ).forEach( function( field ) {
            field.removeAttribute( 'aria-invalid' );
            field.removeAttribute( 'aria-describedby' );
        } );

        toArray( card.querySelectorAll( '.dps-registration-field__error' ) ).forEach( function( error ) {
            error.remove();
        } );

        toArray( card.querySelectorAll( 'input, select, textarea' ) ).forEach( function( field ) {
            if ( field.type === 'hidden' ) {
                field.value = '';
                return;
            }

            if ( field.type === 'checkbox' || field.type === 'radio' ) {
                field.checked = false;
                return;
            }

            if ( field.tagName === 'SELECT' ) {
                field.selectedIndex = 0;
                return;
            }

            field.value = '';
        } );

        toArray( card.querySelectorAll( 'datalist' ) ).forEach( function( datalist ) {
            datalist.innerHTML = '';
        } );

        toArray( card.querySelectorAll( 'details' ) ).forEach( function( disclosure ) {
            disclosure.open = false;
        } );
    }

    function refreshAllPetCards( form ) {
        var cards = getPetCards( form );
        cards.forEach( function( card, index ) {
            refreshPetCard( card, index, cards.length );
        } );
    }

    function togglePetCard( card ) {
        var toggle = card.querySelector( '[data-dps-pet-toggle]' );
        var body = card.querySelector( '.dps-registration-pet__body' );

        if ( ! toggle || ! body ) {
            return;
        }

        var expanded = toggle.getAttribute( 'aria-expanded' ) === 'true';
        toggle.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );
        body.hidden = expanded;
    }

    function bindCard( card, form ) {
        if ( card.dataset.dpsPetCardReady === '1' ) {
            return;
        }

        card.dataset.dpsPetCardReady = '1';

        var removeButton = card.querySelector( '[data-dps-remove-pet]' );
        var nameField = card.querySelector( '[data-dps-pet-name]' );
        var speciesField = card.querySelector( '[data-dps-pet-species]' );
        var sizeField = card.querySelector( '[data-dps-pet-size]' );
        var summary = card.querySelector( '[data-dps-pet-summary]' );
        var title = card.querySelector( '[data-dps-pet-title]' );
        var toggle = card.querySelector( '[data-dps-pet-toggle]' );

        if ( nameField && title ) {
            nameField.addEventListener( 'input', function() {
                title.textContent = nameField.value.trim() || 'Novo pet';
            } );
        }

        [ speciesField, sizeField ].forEach( function( field ) {
            if ( field && summary ) {
                field.addEventListener( 'change', function() {
                    updatePetSummary( summary, buildPetSummary( card ) );
                } );
            }
        } );

        if ( removeButton ) {
            removeButton.addEventListener( 'click', function() {
                var cards = getPetCards( form );
                if ( cards.length <= 1 ) {
                    return;
                }

                card.remove();
                refreshAllPetCards( form );
            } );
        }

        if ( toggle ) {
            toggle.addEventListener( 'click', function() {
                togglePetCard( card );
            } );
        }
    }

    function initPetCards( form ) {
        getPetCards( form ).forEach( function( card ) {
            bindCard( card, form );
        } );
        refreshAllPetCards( form );

        var addButton = form.querySelector( '[data-dps-add-pet]' );
        if ( addButton && addButton.dataset.dpsAddPetReady !== '1' ) {
            addButton.dataset.dpsAddPetReady = '1';
            addButton.addEventListener( 'click', function() {
                var cards = getPetCards( form );
                var source = cards[ cards.length - 1 ];
                if ( ! source ) {
                    return;
                }

                var clone = source.cloneNode( true );
                clearClonedCard( clone );
                form.querySelector( '[data-dps-registration-pets]' ).appendChild( clone );
                bindCard( clone, form );
                refreshAllPetCards( form );
                initFieldEnhancements( clone );

                var toggle = clone.querySelector( '[data-dps-pet-toggle]' );
                var body = clone.querySelector( '.dps-registration-pet__body' );
                if ( toggle && body ) {
                    toggle.setAttribute( 'aria-expanded', 'true' );
                    body.hidden = false;
                }

                var nameField = clone.querySelector( '[data-dps-pet-name]' );
                if ( nameField ) {
                    scrollToElement( clone );
                    nameField.focus();
                }
            } );
        }
    }

    function setFieldError( field, message ) {
        var wrapper = field.closest( '.dps-registration-field' );
        if ( ! wrapper ) {
            return;
        }

        var errorId = field.id ? field.id + '-client-error' : '';
        var error = document.createElement( 'p' );
        error.className = 'dps-registration-field__error';
        error.setAttribute( 'role', 'alert' );
        error.setAttribute( 'data-dps-client-error', '1' );
        if ( errorId ) {
            error.id = errorId;
            field.setAttribute( 'aria-describedby', errorId );
        }

        field.setAttribute( 'aria-invalid', 'true' );
        field.setAttribute( 'data-dps-client-validated', '1' );
        error.textContent = message;
        wrapper.appendChild( error );
    }

    function clearClientValidation( form ) {
        toArray( form.querySelectorAll( '.dps-registration-field__error[data-dps-client-error="1"]' ) ).forEach( function( error ) {
            error.remove();
        } );

        toArray( form.querySelectorAll( '[data-dps-client-validated]' ) ).forEach( function( field ) {
            field.removeAttribute( 'aria-invalid' );
            field.removeAttribute( 'data-dps-client-validated' );
            if ( field.getAttribute( 'aria-describedby' ) && field.getAttribute( 'aria-describedby' ).indexOf( '-client-error' ) !== -1 ) {
                field.removeAttribute( 'aria-describedby' );
            }
        } );
    }

    function validateForm( form ) {
        clearClientValidation( form );

        var i18n = getI18n();
        var errors = [];
        var requiredFields = [
            {
                field: form.querySelector( '#dps-registration-client-name' ),
                message: i18n.nameRequired || 'Informe o nome completo do tutor.',
            },
            {
                field: form.querySelector( '#dps-registration-client-email' ),
                message: i18n.emailRequired || 'Informe um e-mail válido para o cadastro.',
            },
            {
                field: form.querySelector( '#dps-registration-client-phone' ),
                message: i18n.phoneRequired || 'Informe o telefone ou WhatsApp do tutor.',
            },
        ];

        requiredFields.forEach( function( item ) {
            if ( item.field && ! item.field.value.trim() ) {
                errors.push( item );
            }
        } );

        var emailField = form.querySelector( '#dps-registration-client-email' );
        if ( emailField && emailField.value.trim() && ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( emailField.value.trim() ) ) {
            errors.push( {
                field: emailField,
                message: i18n.emailInvalid || 'O e-mail informado não é válido.',
            } );
        }

        getPetCards( form ).forEach( function( card, index ) {
            var nameField = card.querySelector( '[data-dps-pet-name]' );
            var speciesField = card.querySelector( '[data-dps-pet-species]' );
            var toggle = card.querySelector( '[data-dps-pet-toggle]' );
            var body = card.querySelector( '.dps-registration-pet__body' );

            if ( nameField && ! nameField.value.trim() ) {
                errors.push( {
                    field: nameField,
                    message: 'Pet ' + ( index + 1 ) + ': ' + ( i18n.petNameRequired || 'Informe o nome do pet.' ),
                } );
                if ( toggle && body ) {
                    toggle.setAttribute( 'aria-expanded', 'true' );
                    body.hidden = false;
                }
            }

            if ( speciesField && ! speciesField.value.trim() ) {
                errors.push( {
                    field: speciesField,
                    message: 'Pet ' + ( index + 1 ) + ': ' + ( i18n.petSpeciesRequired || 'Selecione a espécie do pet.' ),
                } );
                if ( toggle && body ) {
                    toggle.setAttribute( 'aria-expanded', 'true' );
                    body.hidden = false;
                }
            }
        } );

        errors.forEach( function( error ) {
            if ( error.field ) {
                setFieldError( error.field, error.message );
            }
        } );

        if ( errors.length ) {
            scrollToElement( errors[ 0 ].field );
            errors[ 0 ].field.focus();
        }

        return errors.length === 0;
    }

    function initSubmitState( form ) {
        var button = form.querySelector( '[data-dps-submit-button]' );
        if ( ! button || button.dataset.dpsSubmitReady === '1' ) {
            return;
        }

        button.dataset.dpsSubmitReady = '1';
        button.dataset.originalLabel = button.querySelector( '.dps-registration-button__text' )
            ? button.querySelector( '.dps-registration-button__text' ).textContent
            : '';
    }

    function startSubmitState( form ) {
        var button = form.querySelector( '[data-dps-submit-button]' );
        var labelNode = button ? button.querySelector( '.dps-registration-button__text' ) : null;

        if ( ! button || button.classList.contains( 'is-loading' ) ) {
            return;
        }

        if ( labelNode ) {
            labelNode.textContent = button.getAttribute( 'data-loading-label' ) || labelNode.textContent;
        }

        button.classList.add( 'is-loading' );
        button.disabled = true;
    }

    function resetSubmitState( form ) {
        var button = form.querySelector( '[data-dps-submit-button]' );
        var labelNode = button ? button.querySelector( '.dps-registration-button__text' ) : null;

        if ( ! button ) {
            return;
        }

        if ( labelNode && button.dataset.originalLabel ) {
            labelNode.textContent = button.dataset.originalLabel;
        }

        button.classList.remove( 'is-loading' );
        button.disabled = false;
    }

    function initRecaptcha( form ) {
        if ( form.dataset.dpsRecaptchaReady === '1' ) {
            return;
        }

        form.dataset.dpsRecaptchaReady = '1';
        var siteKey = form.getAttribute( 'data-recaptcha-site-key' );
        if ( ! siteKey || typeof window.grecaptcha === 'undefined' ) {
            return;
        }

        form.addEventListener( 'submit', function( event ) {
            var tokenField = document.getElementById( 'dps-registration-recaptcha-token' );
            if ( ! tokenField || tokenField.value ) {
                return;
            }

            event.preventDefault();

            window.grecaptcha.ready( function() {
                window.grecaptcha.execute( siteKey, { action: 'dps_registration' } ).then( function( token ) {
                    tokenField.value = token;
                    form.submit();
                } ).catch( function() {
                    resetSubmitState( form );
                    showFormNotice( form, 'warning', getI18n().recaptchaUnavailable || 'Não foi possível validar o anti-spam. Tente novamente.' );
                } );
            } );
        } );
    }

    function initForm( form ) {
        if ( form.dataset.dpsRegistrationReady === '1' ) {
            return;
        }

        form.dataset.dpsRegistrationReady = '1';
        initFieldEnhancements( form );
        initPetCards( form );
        initSubmitState( form );
        initRecaptcha( form );

        form.addEventListener( 'submit', function( event ) {
            if ( ! validateForm( form ) ) {
                event.preventDefault();
                return;
            }

            startSubmitState( form );
        } );
    }

    function init() {
        toArray( document.querySelectorAll( '#dps-registration-form' ) ).forEach( initForm );
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }
}() );
