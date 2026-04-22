( function() {
    'use strict';

    function toArray( nodeList ) {
        return Array.prototype.slice.call( nodeList || [] );
    }

    function getConfig() {
        return window.dpsPortalProfileUpdate || { ajaxUrl: '', i18n: {} };
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

    function getPetSummary( card ) {
        var speciesField = card.querySelector( '[data-dps-pet-species]' );
        var sizeField = card.querySelector( '[data-dps-pet-size]' );
        var parts = [];

        if ( speciesField && speciesField.value && speciesField.selectedOptions.length ) {
            parts.push( speciesField.selectedOptions[ 0 ].textContent.trim() );
        }

        if ( sizeField && sizeField.value && sizeField.selectedOptions.length ) {
            parts.push( sizeField.selectedOptions[ 0 ].textContent.trim() );
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

    function bindPetCard( card ) {
        if ( card.dataset.dpsProfilePetReady === '1' ) {
            return;
        }

        card.dataset.dpsProfilePetReady = '1';

        var title = card.querySelector( '[data-dps-pet-title]' );
        var summary = card.querySelector( '[data-dps-pet-summary]' );
        var nameField = card.querySelector( '[data-dps-pet-name]' );
        var speciesField = card.querySelector( '[data-dps-pet-species]' );
        var sizeField = card.querySelector( '[data-dps-pet-size]' );
        var removeButton = card.querySelector( '[data-dps-remove-new-pet]' );

        if ( nameField && title ) {
            nameField.addEventListener( 'input', function() {
                title.textContent = nameField.value.trim() || ( getConfig().i18n.newPetTitle || 'Novo pet' );
            } );
        }

        [ speciesField, sizeField ].forEach( function( field ) {
            if ( field && summary ) {
                field.addEventListener( 'change', function() {
                    updatePetSummary( summary, getPetSummary( card ) );
                } );
            }
        } );

        if ( removeButton ) {
            removeButton.addEventListener( 'click', function() {
                card.remove();
            } );
        }

        if ( summary ) {
            updatePetSummary( summary, getPetSummary( card ) );
        }
    }

    function initProfileUpdateForm() {
        var form = document.getElementById( 'dps-profile-update-form' );
        var addButton = document.querySelector( '[data-dps-profile-add-pet]' );
        var template = document.getElementById( 'dps-profile-update-new-pet-template' );
        var container = document.getElementById( 'dps-new-pets' );
        var index = container ? container.querySelectorAll( '[data-dps-new-pet-card]' ).length : 0;

        if ( ! form ) {
            return;
        }

        toArray( form.querySelectorAll( '[data-dps-existing-pet], [data-dps-new-pet-card]' ) ).forEach( bindPetCard );

        if ( addButton && template && container ) {
            addButton.addEventListener( 'click', function() {
                var html = template.innerHTML.replace( /__INDEX__/g, String( index ) );
                var wrapper = document.createElement( 'div' );
                var card;

                wrapper.innerHTML = html.trim();
                card = wrapper.firstElementChild;

                if ( ! card ) {
                    return;
                }

                container.appendChild( card );
                bindPetCard( card );
                document.dispatchEvent( new CustomEvent( 'dps:signature-refresh', { detail: { root: card } } ) );
                index += 1;

                var nameField = card.querySelector( '[data-dps-pet-name]' );
                if ( nameField ) {
                    scrollToElement( card );
                    nameField.focus();
                }
            } );
        }
    }

    function setGeneratorResult( container, type, message, url ) {
        var messageNode;
        var urlNode;
        if ( ! container ) {
            return;
        }

        container.hidden = false;
        container.classList.remove( 'is-success', 'is-error' );
        container.classList.add( type === 'success' ? 'is-success' : 'is-error' );
        container.innerHTML = '';

        messageNode = document.createElement( 'p' );
        messageNode.textContent = message;
        container.appendChild( messageNode );

        if ( url ) {
            urlNode = document.createElement( 'div' );
            urlNode.className = 'dps-profile-update-link-generator__url';
            urlNode.textContent = url;
            container.appendChild( urlNode );
        }
    }

    function copyText( value ) {
        if ( navigator.clipboard && navigator.clipboard.writeText ) {
            return navigator.clipboard.writeText( value );
        }

        return Promise.reject();
    }

    function initLinkGenerator() {
        var config = getConfig();

        if ( ! config.ajaxUrl ) {
            return;
        }

        toArray( document.querySelectorAll( '.dps-generate-update-link' ) ).forEach( function( button ) {
            if ( button.dataset.dpsGeneratorReady === '1' ) {
                return;
            }

            button.dataset.dpsGeneratorReady = '1';
            button.addEventListener( 'click', function() {
                var clientId = button.getAttribute( 'data-client-id' );
                var nonce = button.getAttribute( 'data-nonce' );
                var result = button.parentElement ? button.parentElement.querySelector( '[data-dps-update-link-result]' ) : null;
                var body = new URLSearchParams();
                var defaultLabel = button.getAttribute( 'data-default-label' ) || ( config.i18n.generate || 'Gerar link' );
                var loadingLabel = button.getAttribute( 'data-loading-label' ) || ( config.i18n.generating || 'Gerando...' );

                button.disabled = true;
                button.textContent = loadingLabel;

                body.set( 'action', 'dps_generate_profile_update_link' );
                body.set( 'client_id', clientId || '' );
                body.set( '_wpnonce', nonce || '' );

                fetch( config.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    },
                    body: body.toString(),
                } )
                    .then( function( response ) {
                        return response.json();
                    } )
                    .then( function( response ) {
                        if ( ! response || ! response.success || ! response.data || ! response.data.url ) {
                            throw new Error( response && response.data && response.data.message ? response.data.message : ( config.i18n.genericError || 'Não foi possível gerar o link agora. Tente novamente.' ) );
                        }

                        return copyText( response.data.url )
                            .catch( function() {
                                window.prompt( config.i18n.copyPrompt || 'Copie o link abaixo:', response.data.url );
                            } )
                            .then( function() {
                                setGeneratorResult( result, 'success', config.i18n.generated || 'Link gerado e copiado. Envie para o cliente por WhatsApp ou e-mail.', response.data.url );
                            } );
                    } )
                    .catch( function( error ) {
                        setGeneratorResult( result, 'error', error && error.message ? error.message : ( config.i18n.genericError || 'Não foi possível gerar o link agora. Tente novamente.' ) );
                    } )
                    .finally( function() {
                        button.disabled = false;
                        button.textContent = defaultLabel;
                    } );
            } );
        } );
    }

    function init() {
        initLinkGenerator();
        initProfileUpdateForm();
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }
}() );
