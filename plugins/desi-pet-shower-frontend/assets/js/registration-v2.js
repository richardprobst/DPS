( function() {
    'use strict';

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

    function getShell( form ) {
        return form.closest( '.dps-registration-signature' );
    }

    function showFormNotice( form, type, message ) {
        var shell = getShell( form );
        var stack = shell ? shell.querySelector( '.dps-signature-form__notice-stack' ) : null;
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
            notice.className = 'dps-signature-notice';
            title = document.createElement( 'h3' );
            title.className = 'dps-signature-notice__title';
            text = document.createElement( 'p' );
            text.className = 'dps-signature-notice__text';
            notice.appendChild( title );
            notice.appendChild( text );
            stack.appendChild( notice );
        } else {
            title = notice.querySelector( '.dps-signature-notice__title' );
            text = notice.querySelector( '.dps-signature-notice__text' );
        }

        notice.className = 'dps-signature-notice dps-signature-notice--' + ( type || 'error' );
        if ( title ) {
            title.textContent = type === 'warning' ? 'Atenção' : 'Não foi possível continuar';
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

        return parts.length ? parts.join( ' • ' ) : 'Pet sem detalhes';
    }

    function refreshPetCard( card, index, total ) {
        var title = card.querySelector( '[data-dps-pet-title]' );
        var nameField = card.querySelector( '[data-dps-pet-name]' );
        var summary = card.querySelector( '[data-dps-pet-summary]' );
        var eyebrow = card.querySelector( '.dps-signature-card__eyebrow' );
        var removeButton = card.querySelector( '[data-dps-remove-pet]' );
        var toggle = card.querySelector( '[data-dps-pet-toggle]' );
        var body = card.querySelector( '.dps-signature-card__body' );

        card.setAttribute( 'data-pet-index', String( index ) );

        if ( eyebrow ) {
            eyebrow.textContent = 'Pet ' + ( index + 1 );
        }

        if ( title ) {
            title.textContent = nameField && nameField.value.trim() ? nameField.value.trim() : 'Pet ' + ( index + 1 );
        }

        if ( summary ) {
            summary.textContent = buildPetSummary( card );
        }

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

        toArray( card.querySelectorAll( '.dps-signature-field__error' ) ).forEach( function( error ) {
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
    }

    function refreshAllPetCards( form ) {
        var cards = getPetCards( form );
        cards.forEach( function( card, index ) {
            refreshPetCard( card, index, cards.length );
        } );
    }

    function initPetCards( form ) {
        function bindCard( card ) {
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
            var body = card.querySelector( '.dps-signature-card__body' );

            if ( nameField && title ) {
                nameField.addEventListener( 'input', function() {
                    title.textContent = nameField.value.trim() || 'Pet ' + ( Number( card.getAttribute( 'data-pet-index' ) ) + 1 );
                } );
            }

            [ speciesField, sizeField ].forEach( function( field ) {
                if ( field && summary ) {
                    field.addEventListener( 'change', function() {
                        summary.textContent = buildPetSummary( card );
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

            if ( toggle && body ) {
                toggle.addEventListener( 'click', function() {
                    if ( toggle.getAttribute( 'aria-expanded' ) === 'true' ) {
                        scrollToElement( card );
                    }
                } );
            }
        }

        getPetCards( form ).forEach( bindCard );

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
                refreshAllPetCards( form );
                bindCard( clone );

                var toggle = clone.querySelector( '[data-dps-pet-toggle]' );
                var body = clone.querySelector( '.dps-signature-card__body' );
                if ( toggle && body ) {
                    toggle.setAttribute( 'aria-expanded', 'true' );
                    body.hidden = false;
                }

                document.dispatchEvent( new CustomEvent( 'dps:signature-refresh', { detail: { root: clone } } ) );
                var nameField = clone.querySelector( '[data-dps-pet-name]' );
                if ( nameField ) {
                    scrollToElement( clone );
                    nameField.focus();
                }
            } );
        }
    }

    function setFieldError( field, message ) {
        var wrapper = field.closest( '.dps-signature-field' );
        if ( ! wrapper ) {
            return;
        }

        var errorId = field.id ? field.id + '-client-error' : '';
        var error = document.createElement( 'p' );
        error.className = 'dps-signature-field__error';
        error.setAttribute( 'role', 'alert' );
        error.setAttribute( 'data-dps-client-error', '1' );
        if ( errorId ) {
            error.id = errorId;
            field.setAttribute( 'aria-describedby', errorId );
        }
        field.setAttribute( 'aria-invalid', 'true' );
        error.textContent = message;
        wrapper.appendChild( error );
    }

    function clearClientValidation( form ) {
        toArray( form.querySelectorAll( '.dps-signature-field__error[data-dps-client-error="1"]' ) ).forEach( function( error ) {
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
            var body = card.querySelector( '.dps-signature-card__body' );

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
            if ( ! error.field ) {
                return;
            }

            error.field.setAttribute( 'data-dps-client-validated', '1' );
            setFieldError( error.field, error.message );
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
        button.dataset.originalLabel = button.querySelector( '.dps-signature-button__text' ) ? button.querySelector( '.dps-signature-button__text' ).textContent : '';
    }

    function startSubmitState( form ) {
        var button = form.querySelector( '[data-dps-submit-button]' );
        var labelNode = button ? button.querySelector( '.dps-signature-button__text' ) : null;

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
        var labelNode = button ? button.querySelector( '.dps-signature-button__text' ) : null;

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

    function initStepNavigation( form ) {
        var shell = getShell( form );
        var buttons;
        if ( ! shell ) {
            return;
        }

        buttons = toArray( shell.querySelectorAll( '[data-dps-registration-section]' ) );

        buttons.forEach( function( button, index ) {
            if ( button.dataset.dpsStepReady === '1' ) {
                return;
            }

            button.dataset.dpsStepReady = '1';
            if ( index === 0 ) {
                button.classList.add( 'is-current' );
            }
            button.addEventListener( 'click', function() {
                var targetId = button.getAttribute( 'data-dps-registration-section' );
                var target = targetId ? document.getElementById( targetId ) : null;
                buttons.forEach( function( stepButton ) {
                    stepButton.classList.remove( 'is-current' );
                } );
                button.classList.add( 'is-current' );
                if ( target ) {
                    scrollToElement( target );
                }
            } );
        } );
    }

    function initForm( form ) {
        if ( form.dataset.dpsRegistrationReady === '1' ) {
            return;
        }

        form.dataset.dpsRegistrationReady = '1';
        initPetCards( form );
        refreshAllPetCards( form );
        initSubmitState( form );
        initRecaptcha( form );
        initStepNavigation( form );

        form.addEventListener( 'submit', function( event ) {
            if ( ! validateForm( form ) ) {
                event.preventDefault();
                return;
            }

            startSubmitState( form );
        } );
    }

    function init() {
        toArray( document.querySelectorAll( '#dps-registration-signature-form' ) ).forEach( initForm );
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }
}() );
