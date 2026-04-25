/**
 * DPS Signature Forms
 *
 * Utilitários compartilhados para máscaras, autocomplete e disclosures dos
 * formulários de cadastro.
 *
 * @package DesiPetShower
 */

( function() {
    'use strict';

    var googlePlacesPromise = null;

    function toArray( nodeList ) {
        return Array.prototype.slice.call( nodeList || [] );
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
            var callbackName = 'dpsSignatureGooglePlacesReady';

            window[ callbackName ] = function() {
                resolve( window.google.maps.places );
            };

            var script = document.createElement( 'script' );
            script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent( apiKey ) + '&libraries=places&loading=async&callback=' + callbackName;
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

    function toggleDisclosure( toggle ) {
        var targetId = toggle.getAttribute( 'aria-controls' );
        if ( ! targetId ) {
            return;
        }

        var body = document.getElementById( targetId );
        if ( ! body ) {
            return;
        }

        var expanded = toggle.getAttribute( 'aria-expanded' ) === 'true';
        toggle.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );
        body.hidden = expanded;
    }

    function initDisclosures( root ) {
        var scope = root || document;
        toArray( scope.querySelectorAll( '[data-dps-disclosure-toggle]' ) ).forEach( function( toggle ) {
            if ( toggle.dataset.dpsDisclosureReady === '1' ) {
                return;
            }

            toggle.dataset.dpsDisclosureReady = '1';
            toggle.addEventListener( 'click', function() {
                toggleDisclosure( toggle );
            } );
        } );
    }

    function init( root ) {
        initMasks( root );
        initBreedDatalists( root );
        initAddressAutocomplete( root );
        initDisclosures( root );
    }

    document.addEventListener( 'DOMContentLoaded', function() {
        init( document );
    } );

    document.addEventListener( 'dps:signature-refresh', function( event ) {
        init( event && event.detail && event.detail.root ? event.detail.root : document );
    } );

    window.DPSSignatureForms = {
        init: init,
        initMasks: initMasks,
        initBreedDatalists: initBreedDatalists,
        initAddressAutocomplete: initAddressAutocomplete,
        maskPhone: maskPhone,
        maskCpf: maskCpf,
    };
}() );
