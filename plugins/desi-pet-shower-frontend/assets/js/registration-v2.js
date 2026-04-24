( function() {
    'use strict';

    var googlePlacesPromise = null;
    var GOOGLE_PLACES_CALLBACK = 'dpsSignatureGooglePlacesReady';
    var GOOGLE_PLACES_WAIT_TIMEOUT = 12000;

    function toArray( nodeList ) {
        return Array.prototype.slice.call( nodeList || [] );
    }

    function getRuntimeConfig() {
        return window.dpsRegistrationV2 || {};
    }

    function getI18n() {
        return getRuntimeConfig().i18n || {};
    }

    function getValidationConfig() {
        return getRuntimeConfig().validation || {};
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
        var nextCursor;

        input.value = formatter( input.value );
        nextCursor = cursor + ( input.value.length - previousLength );

        if ( input === document.activeElement && typeof input.setSelectionRange === 'function' ) {
            input.setSelectionRange( nextCursor, nextCursor );
        }
    }

    function initMasks( root ) {
        var scope = root || document;

        toArray( scope.querySelectorAll( '[data-dps-mask]' ) ).forEach( function( input ) {
            var formatter = null;

            if ( input.dataset.dpsMaskReady === '1' ) {
                return;
            }

            if ( input.getAttribute( 'data-dps-mask' ) === 'phone' ) {
                formatter = maskPhone;
            } else if ( input.getAttribute( 'data-dps-mask' ) === 'cpf' ) {
                formatter = maskCpf;
            }

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

    function getBreedDatalist( select ) {
        var targetId = select.getAttribute( 'data-dps-breed-target' );

        if ( ! targetId ) {
            return null;
        }

        return document.getElementById( targetId );
    }

    function getBreedField( select ) {
        var targetId = select.getAttribute( 'data-dps-breed-target' );
        var scope = select.closest( '[data-pet-index]' ) || select.form || document;

        if ( ! targetId ) {
            return null;
        }

        return scope.querySelector( '[list="' + targetId + '"]' );
    }

    function getAvailableBreeds( select ) {
        var datalist = getBreedDatalist( select );
        var breedMap;
        var currentSpecies;

        if ( ! datalist ) {
            return [];
        }

        breedMap = parseBreedMap( datalist.getAttribute( 'data-dps-breed-map' ) );
        currentSpecies = select.value || '';

        return normalizeBreedList( breedMap[ currentSpecies ] || breedMap.all || [] );
    }

    function populateBreedDatalist( select ) {
        var datalist = getBreedDatalist( select );
        var breeds = getAvailableBreeds( select );
        var seen = {};

        if ( ! datalist ) {
            return [];
        }

        datalist.innerHTML = '';

        breeds.forEach( function( breed ) {
            var option;

            if ( ! breed || seen[ breed ] ) {
                return;
            }

            seen[ breed ] = true;
            option = document.createElement( 'option' );
            option.value = breed;
            datalist.appendChild( option );
        } );

        return Object.keys( seen );
    }

    function syncBreedField( select, breeds ) {
        var breedField = getBreedField( select );
        var currentValue;
        var isAllowed;

        if ( ! breedField ) {
            return;
        }

        currentValue = breedField.value.trim();

        if ( ! currentValue ) {
            return;
        }

        if ( ! select.value ) {
            breedField.value = '';
            return;
        }

        if ( ! breeds.length ) {
            return;
        }

        isAllowed = breeds.some( function( breed ) {
            return String( breed ).toLowerCase() === currentValue.toLowerCase();
        } );

        if ( ! isAllowed ) {
            breedField.value = '';
        }
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
                syncBreedField( select, populateBreedDatalist( select ) );
            } );
        } );
    }

    function getGooglePlacesInstance() {
        return window.google && window.google.maps && window.google.maps.places
            ? window.google.maps.places
            : null;
    }

    function getExistingGooglePlacesScript() {
        return document.querySelector(
            'script[src*="maps.googleapis.com/maps/api/js"][src*="callback=' + GOOGLE_PLACES_CALLBACK + '"]'
        );
    }

    function waitForSharedGooglePlaces() {
        var existingCallback = window[ GOOGLE_PLACES_CALLBACK ];
        var existingScript = getExistingGooglePlacesScript();

        if ( 'function' !== typeof existingCallback && ! existingScript ) {
            return null;
        }

        googlePlacesPromise = new Promise( function( resolve, reject ) {
            var intervalId = null;
            var timeoutId = null;
            var hasSettled = false;
            var handleScriptError = function() {
                cleanup();
                reject( new Error( 'Failed to load shared Google Places script.' ) );
            };

            function cleanup() {
                if ( intervalId ) {
                    window.clearInterval( intervalId );
                }

                if ( timeoutId ) {
                    window.clearTimeout( timeoutId );
                }

                if ( existingScript ) {
                    existingScript.removeEventListener( 'error', handleScriptError );
                }
            }

            function resolveIfReady() {
                var places = getGooglePlacesInstance();

                if ( hasSettled || ! places ) {
                    return false;
                }

                hasSettled = true;
                cleanup();
                resolve( places );

                return true;
            }

            if ( resolveIfReady() ) {
                return;
            }

            if ( 'function' === typeof existingCallback ) {
                window[ GOOGLE_PLACES_CALLBACK ] = function() {
                    var result = existingCallback.apply( this, arguments );

                    resolveIfReady();

                    return result;
                };
            }

            if ( existingScript ) {
                existingScript.addEventListener( 'error', handleScriptError );
            }

            intervalId = window.setInterval( resolveIfReady, 50 );
            timeoutId = window.setTimeout( function() {
                if ( hasSettled ) {
                    return;
                }

                cleanup();
                reject( new Error( 'Timed out waiting for shared Google Places script.' ) );
            }, GOOGLE_PLACES_WAIT_TIMEOUT );
        } );

        return googlePlacesPromise;
    }

    function loadGooglePlaces( apiKey ) {
        var sharedLoader = window.DPSSignatureForms && 'function' === typeof window.DPSSignatureForms.loadGooglePlaces
            ? window.DPSSignatureForms.loadGooglePlaces
            : null;

        if ( ! apiKey ) {
            return Promise.reject( new Error( 'Google Places API key not provided.' ) );
        }

        if ( getGooglePlacesInstance() ) {
            return Promise.resolve( getGooglePlacesInstance() );
        }

        if ( googlePlacesPromise ) {
            return googlePlacesPromise;
        }

        if ( sharedLoader ) {
            googlePlacesPromise = sharedLoader( apiKey );
            return googlePlacesPromise;
        }

        googlePlacesPromise = waitForSharedGooglePlaces();

        if ( googlePlacesPromise ) {
            return googlePlacesPromise;
        }

        googlePlacesPromise = new Promise( function( resolve, reject ) {
            var script = document.createElement( 'script' );

            window[ GOOGLE_PLACES_CALLBACK ] = function() {
                resolve( window.google.maps.places );
            };

            script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent( apiKey ) + '&libraries=places&callback=' + GOOGLE_PLACES_CALLBACK;
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
        var apiKey = '';

        if ( ! fields.length ) {
            return;
        }

        fields.some( function( field ) {
            apiKey = field.getAttribute( 'data-dps-google-api-key' ) || '';
            return !! apiKey;
        } );

        if ( ! apiKey ) {
            return;
        }

        loadGooglePlaces( apiKey ).then( function() {
            fields.forEach( function( field ) {
                var autocomplete;

                if ( field.dataset.dpsPlacesReady === '1' ) {
                    return;
                }

                field.dataset.dpsPlacesReady = '1';
                autocomplete = new window.google.maps.places.Autocomplete( field, {
                    fields: [ 'formatted_address', 'geometry' ],
                    types: [ 'geocode' ],
                } );

                autocomplete.addListener( 'place_changed', function() {
                    var place = autocomplete.getPlace();
                    var latTargetId = field.getAttribute( 'data-dps-lat-target' );
                    var lngTargetId = field.getAttribute( 'data-dps-lng-target' );
                    var latField = latTargetId ? document.getElementById( latTargetId ) : null;
                    var lngField = lngTargetId ? document.getElementById( lngTargetId ) : null;

                    if ( place && place.formatted_address ) {
                        field.value = place.formatted_address;
                    }

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
            // Campo continua funcionando como texto livre.
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
        var i18n = getI18n();
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
            title.textContent = type === 'warning'
                ? ( i18n.noticeWarningTitle || 'Atencao' )
                : ( i18n.noticeErrorTitle || 'Nao foi possivel concluir' );
        }

        if ( text ) {
            text.textContent = message;
        }
    }

    function clearFormNotice( form ) {
        var shell = getShell( form );
        var stack = shell ? shell.querySelector( '.dps-registration__notice-stack' ) : null;
        var notice = stack ? stack.querySelector( '[data-dps-runtime-notice]' ) : null;

        if ( notice ) {
            notice.remove();
        }
    }

    function getPetCards( form ) {
        return toArray( form.querySelectorAll( '[data-pet-index]' ) );
    }

    function buildPetSummary( card ) {
        var speciesField = card.querySelector( '[data-dps-pet-species]' );
        var sizeField = card.querySelector( '[data-dps-pet-size]' );
        var parts = [];
        var speciesLabel = speciesField && speciesField.selectedOptions.length ? speciesField.selectedOptions[ 0 ].textContent.trim() : '';
        var sizeLabel = sizeField && sizeField.selectedOptions.length ? sizeField.selectedOptions[ 0 ].textContent.trim() : '';

        if ( speciesField && speciesField.value && speciesLabel ) {
            parts.push( speciesLabel );
        }

        if ( sizeField && sizeField.value && sizeLabel ) {
            parts.push( sizeLabel );
        }

        return parts.length ? parts.join( ' / ' ) : '';
    }

    function updatePetSummary( summary, value ) {
        if ( ! summary ) {
            return;
        }

        summary.textContent = value || '';
        summary.hidden = ! value;
    }

    function updatePetToggleLabel( toggle ) {
        var label = toggle ? toggle.querySelector( '[data-dps-pet-toggle-label]' ) : null;
        var i18n = getI18n();

        if ( ! toggle || ! label ) {
            return;
        }

        label.textContent = toggle.getAttribute( 'aria-expanded' ) === 'true'
            ? ( i18n.toggleCollapse || 'Recolher' )
            : ( i18n.toggleExpand || 'Expandir' );
    }

    function getDisclosureFilledCount( disclosure ) {
        var body = disclosure ? disclosure.querySelector( '.dps-registration-disclosure__body' ) : null;
        var total = 0;

        if ( ! body ) {
            return 0;
        }

        toArray( body.querySelectorAll( 'input, select, textarea' ) ).forEach( function( field ) {
            if ( field.disabled || field.type === 'hidden' || field.hasAttribute( 'data-dps-disclosure-ignore' ) ) {
                return;
            }

            if ( field.type === 'checkbox' || field.type === 'radio' ) {
                if ( field.checked ) {
                    total += 1;
                }
                return;
            }

            if ( field.tagName === 'SELECT' ) {
                if ( field.value && field.value.trim() ) {
                    total += 1;
                }
                return;
            }

            if ( field.value && field.value.trim() ) {
                total += 1;
            }
        } );

        return total;
    }

    function formatDisclosureCount( count ) {
        var i18n = getI18n();
        var template = count === 1
            ? ( i18n.disclosureSingle || '%d preenchido' )
            : ( i18n.disclosurePlural || '%d preenchidos' );

        return template.replace( '%d', String( count ) );
    }

    function updateDisclosureMeta( disclosure ) {
        var meta = disclosure ? disclosure.querySelector( '.dps-registration-disclosure__meta' ) : null;

        if ( ! meta ) {
            return;
        }

        meta.textContent = formatDisclosureCount( getDisclosureFilledCount( disclosure ) );
    }

    function initDisclosureMeta( root ) {
        var scope = root || document;
        var disclosures = scope.matches && scope.matches( '.dps-registration-disclosure' )
            ? [ scope ]
            : toArray( scope.querySelectorAll( '.dps-registration-disclosure' ) );

        disclosures.forEach( function( disclosure ) {
            if ( disclosure.dataset.dpsDisclosureReady !== '1' ) {
                disclosure.dataset.dpsDisclosureReady = '1';

                toArray( disclosure.querySelectorAll( '.dps-registration-disclosure__body input, .dps-registration-disclosure__body select, .dps-registration-disclosure__body textarea' ) ).forEach( function( field ) {
                    field.addEventListener( 'input', function() {
                        updateDisclosureMeta( disclosure );
                    } );
                    field.addEventListener( 'change', function() {
                        updateDisclosureMeta( disclosure );
                    } );
                } );
            }

            updateDisclosureMeta( disclosure );
        } );
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
            body.id = 'dps-registration-pet-body-' + index;
            toggle.setAttribute( 'aria-controls', body.id );
            updatePetToggleLabel( toggle );
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
        getPetCards( form ).forEach( function( card, index, cards ) {
            refreshPetCard( card, index, cards.length );
        } );
    }

    function togglePetCard( card ) {
        var toggle = card.querySelector( '[data-dps-pet-toggle]' );
        var body = card.querySelector( '.dps-registration-pet__body' );
        var expanded;

        if ( ! toggle || ! body ) {
            return;
        }

        expanded = toggle.getAttribute( 'aria-expanded' ) === 'true';
        toggle.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );
        body.hidden = expanded;
        updatePetToggleLabel( toggle );
    }

    function markFormDirty( form ) {
        if ( form && form.dataset.dpsNativeSubmitting !== '1' ) {
            form.dataset.dpsDirty = '1';
        }
    }

    function clearFormDirty( form ) {
        if ( form ) {
            form.dataset.dpsDirty = '0';
        }
    }

    function bindCard( card, form ) {
        var removeButton;
        var nameField;
        var speciesField;
        var sizeField;
        var summary;
        var title;
        var toggle;

        if ( card.dataset.dpsPetCardReady === '1' ) {
            return;
        }

        card.dataset.dpsPetCardReady = '1';
        removeButton = card.querySelector( '[data-dps-remove-pet]' );
        nameField = card.querySelector( '[data-dps-pet-name]' );
        speciesField = card.querySelector( '[data-dps-pet-species]' );
        sizeField = card.querySelector( '[data-dps-pet-size]' );
        summary = card.querySelector( '[data-dps-pet-summary]' );
        title = card.querySelector( '[data-dps-pet-title]' );
        toggle = card.querySelector( '[data-dps-pet-toggle]' );

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
                if ( getPetCards( form ).length <= 1 ) {
                    return;
                }

                card.remove();
                markFormDirty( form );
                refreshAllPetCards( form );
            } );
        }

        if ( toggle ) {
            toggle.addEventListener( 'click', function() {
                togglePetCard( card );
            } );
            updatePetToggleLabel( toggle );
        }
    }

    function initPetCards( form ) {
        var addButton = form.querySelector( '[data-dps-add-pet]' );

        getPetCards( form ).forEach( function( card ) {
            bindCard( card, form );
        } );
        refreshAllPetCards( form );

        if ( addButton && addButton.dataset.dpsAddPetReady !== '1' ) {
            addButton.dataset.dpsAddPetReady = '1';
            addButton.addEventListener( 'click', function() {
                var cards = getPetCards( form );
                var source = cards[ cards.length - 1 ];
                var clone;
                var toggle;
                var body;
                var nameField;

                if ( ! source ) {
                    return;
                }

                clone = source.cloneNode( true );
                clearClonedCard( clone );
                form.querySelector( '[data-dps-registration-pets]' ).appendChild( clone );
                bindCard( clone, form );
                refreshAllPetCards( form );
                initFieldEnhancements( clone );
                initDisclosureMeta( clone );
                markFormDirty( form );

                toggle = clone.querySelector( '[data-dps-pet-toggle]' );
                body = clone.querySelector( '.dps-registration-pet__body' );

                if ( toggle && body ) {
                    toggle.setAttribute( 'aria-expanded', 'true' );
                    body.hidden = false;
                    updatePetToggleLabel( toggle );
                }

                nameField = clone.querySelector( '[data-dps-pet-name]' );

                if ( nameField ) {
                    scrollToElement( clone );
                    nameField.focus();
                }
            } );
        }
    }

    function setFieldError( field, message ) {
        var wrapper = field.closest( '.dps-registration-field' );
        var errorId;
        var error;

        if ( ! wrapper ) {
            return;
        }

        errorId = field.id ? field.id + '-client-error' : '';
        error = document.createElement( 'p' );
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

    function expandCardForError( card ) {
        var toggle = card.querySelector( '[data-dps-pet-toggle]' );
        var body = card.querySelector( '.dps-registration-pet__body' );

        if ( toggle && body ) {
            toggle.setAttribute( 'aria-expanded', 'true' );
            body.hidden = false;
            updatePetToggleLabel( toggle );
        }
    }

    function validateForm( form ) {
        var i18n = getI18n();
        var validation = getValidationConfig();
        var requiredFields;
        var emailField;
        var errors = [];

        clearClientValidation( form );

        requiredFields = Array.isArray( validation.clientRequired ) && validation.clientRequired.length
            ? validation.clientRequired.map( function( rule ) {
                return {
                    field: rule.selector ? form.querySelector( rule.selector ) : null,
                    message: rule.message || '',
                };
            } )
            : [
                {
                    field: form.querySelector( '#dps-registration-client-name' ),
                    message: i18n.nameRequired || 'Informe o nome completo do tutor.',
                },
                {
                    field: form.querySelector( '#dps-registration-client-email' ),
                    message: i18n.emailRequired || 'Informe um e-mail valido para o cadastro.',
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

        emailField = validation.email && validation.email.selector
            ? form.querySelector( validation.email.selector )
            : form.querySelector( '#dps-registration-client-email' );

        if ( emailField && emailField.value.trim() && ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( emailField.value.trim() ) ) {
            errors.push( {
                field: emailField,
                message: validation.email && validation.email.invalid
                    ? validation.email.invalid
                    : ( i18n.emailInvalid || 'O e-mail informado nao e valido.' ),
            } );
        }

        getPetCards( form ).forEach( function( card, index ) {
            var nameField = card.querySelector( '[data-dps-pet-name]' );
            var speciesField = card.querySelector( '[data-dps-pet-species]' );
            var sizeField = card.querySelector( '[data-dps-pet-size]' );

            if ( nameField && ! nameField.value.trim() ) {
                errors.push( {
                    field: nameField,
                    message: 'Pet ' + ( index + 1 ) + ': ' + (
                        validation.petRequired && validation.petRequired.name
                            ? validation.petRequired.name
                            : ( i18n.petNameRequired || 'Informe o nome do pet.' )
                    ),
                } );
                expandCardForError( card );
            }

            if ( speciesField && ! speciesField.value.trim() ) {
                errors.push( {
                    field: speciesField,
                    message: 'Pet ' + ( index + 1 ) + ': ' + (
                        validation.petRequired && validation.petRequired.species
                            ? validation.petRequired.species
                            : ( i18n.petSpeciesRequired || 'Selecione a especie do pet.' )
                    ),
                } );
                expandCardForError( card );
            }

            if ( sizeField && ! sizeField.value.trim() ) {
                errors.push( {
                    field: sizeField,
                    message: 'Pet ' + ( index + 1 ) + ': ' + (
                        validation.petRequired && validation.petRequired.size
                            ? validation.petRequired.size
                            : ( i18n.petSizeRequired || 'Selecione o porte do pet.' )
                    ),
                } );
                expandCardForError( card );
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
        var labelNode;

        if ( ! button || button.dataset.dpsSubmitReady === '1' ) {
            return;
        }

        labelNode = button.querySelector( '.dps-registration-button__text' );
        button.dataset.dpsSubmitReady = '1';
        button.dataset.originalLabel = labelNode ? labelNode.textContent : '';
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

    function shouldTrackField( field ) {
        return !! ( field && field.matches && field.matches( 'input, select, textarea' ) && field.type !== 'hidden' );
    }

    function initDirtyGuard( form ) {
        if ( form.dataset.dpsDirtyReady === '1' ) {
            return;
        }

        form.dataset.dpsDirtyReady = '1';
        clearFormDirty( form );

        form.addEventListener( 'input', function( event ) {
            if ( shouldTrackField( event.target ) ) {
                markFormDirty( form );
            }
        } );

        form.addEventListener( 'change', function( event ) {
            if ( shouldTrackField( event.target ) ) {
                markFormDirty( form );
            }
        } );

        if ( document.body.dataset.dpsBeforeUnloadReady !== '1' ) {
            document.body.dataset.dpsBeforeUnloadReady = '1';
            window.addEventListener( 'beforeunload', function( event ) {
                var dirtyForm = toArray( document.querySelectorAll( '#dps-registration-form[data-dps-dirty="1"]' ) ).find( function( currentForm ) {
                    return currentForm.dataset.dpsNativeSubmitting !== '1' && currentForm.dataset.dpsRecaptchaPending !== '1';
                } );

                if ( ! dirtyForm ) {
                    return;
                }

                event.preventDefault();
                event.returnValue = getI18n().unsavedChanges || 'Voce tem alteracoes nao salvas. Se sair agora, perdera o que ja preencheu.';
            } );
        }
    }

    function initForm( form ) {
        if ( form.dataset.dpsRegistrationReady === '1' ) {
            return;
        }

        form.dataset.dpsRegistrationReady = '1';
        form.dataset.dpsDirty = '0';
        form.dataset.dpsNativeSubmitting = '0';
        form.dataset.dpsRecaptchaPending = '0';

        initFieldEnhancements( form );
        initPetCards( form );
        initDisclosureMeta( form );
        initSubmitState( form );
        initDirtyGuard( form );

        form.addEventListener( 'submit', function( event ) {
            var siteKey = form.getAttribute( 'data-recaptcha-site-key' );
            var tokenField = document.getElementById( 'dps-registration-recaptcha-token' );

            clearFormNotice( form );

            if ( form.dataset.dpsRecaptchaPending === '1' ) {
                event.preventDefault();
                return;
            }

            if ( form.dataset.dpsNativeSubmitting === '1' ) {
                return;
            }

            if ( ! validateForm( form ) ) {
                event.preventDefault();
                return;
            }

            if ( siteKey && tokenField && ! tokenField.value ) {
                event.preventDefault();

                if ( typeof window.grecaptcha === 'undefined' ) {
                    showFormNotice( form, 'warning', getI18n().recaptchaUnavailable || 'Nao foi possivel validar o anti-spam. Tente novamente.' );
                    return;
                }

                form.dataset.dpsRecaptchaPending = '1';
                startSubmitState( form );

                window.grecaptcha.ready( function() {
                    window.grecaptcha.execute( siteKey, { action: 'dps_registration' } ).then( function( token ) {
                        tokenField.value = token;
                        form.dataset.dpsRecaptchaPending = '0';
                        form.dataset.dpsNativeSubmitting = '1';
                        clearFormDirty( form );
                        HTMLFormElement.prototype.submit.call( form );
                    } ).catch( function() {
                        form.dataset.dpsRecaptchaPending = '0';
                        resetSubmitState( form );
                        showFormNotice( form, 'warning', getI18n().recaptchaUnavailable || 'Nao foi possivel validar o anti-spam. Tente novamente.' );
                    } );
                } );

                return;
            }

            form.dataset.dpsNativeSubmitting = '1';
            clearFormDirty( form );
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
