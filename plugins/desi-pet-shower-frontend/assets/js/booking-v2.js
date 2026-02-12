/**
 * Booking V2 — JavaScript (Vanilla)
 *
 * Wizard state machine nativo para o agendamento V2.
 * Zero dependência de jQuery.
 *
 * Features:
 * - 5-step wizard state machine
 * - AJAX integration (Fetch API)
 * - Client search with debounce
 * - Pet & service selection with running total
 * - Time slot picker
 * - Extras (TaxiDog / Tosa)
 * - Summary / confirmation
 * - URL state via history.pushState
 * - Smooth step transitions
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 */

( function() {
    'use strict';

    /* ---------------------------------------------------------------
     * Constants
     * ------------------------------------------------------------- */

    var TOTAL_STEPS = 5;
    var DEBOUNCE_MS = 300;

    /* ---------------------------------------------------------------
     * Helpers
     * ------------------------------------------------------------- */

    /**
     * Formata valor numérico como R$ (BRL).
     *
     * @param {number} value
     * @return {string}
     */
    function formatBRL( value ) {
        var num = parseFloat( value );
        if ( isNaN( num ) ) {
            return 'R$ 0,00';
        }
        return 'R$ ' + num.toFixed( 2 ).replace( '.', ',' ).replace( /\B(?=(\d{3})+(?!\d))/g, '.' );
    }

    /**
     * Retorna a URL base para AJAX.
     *
     * @return {string}
     */
    function getAjaxUrl() {
        if ( window.dpsBookingV2 && window.dpsBookingV2.ajaxUrl ) {
            return window.dpsBookingV2.ajaxUrl;
        }
        if ( window.ajaxurl ) {
            return window.ajaxurl;
        }
        return '/wp-admin/admin-ajax.php';
    }

    /**
     * Retorna o nonce para AJAX.
     *
     * @param {HTMLElement} wizard
     * @return {string}
     */
    function getNonce( wizard ) {
        var fromAttr = wizard.getAttribute( 'data-nonce' );
        if ( fromAttr ) {
            return fromAttr;
        }
        if ( window.dpsBookingV2 && window.dpsBookingV2.nonce ) {
            return window.dpsBookingV2.nonce;
        }
        var hidden = wizard.querySelector( 'input[name="_wpnonce"]' );
        if ( hidden ) {
            return hidden.value;
        }
        return '';
    }

    /**
     * Envia requisição AJAX via Fetch.
     *
     * @param {string} action
     * @param {Object} data
     * @param {HTMLElement} wizard
     * @return {Promise<Object>}
     */
    function ajaxPost( action, data, wizard ) {
        var body = new FormData();
        body.append( 'action', action );
        body.append( 'nonce', getNonce( wizard ) );

        var keys = Object.keys( data );
        for ( var i = 0; i < keys.length; i++ ) {
            var val = data[ keys[ i ] ];
            if ( Array.isArray( val ) ) {
                for ( var j = 0; j < val.length; j++ ) {
                    body.append( keys[ i ] + '[]', val[ j ] );
                }
            } else {
                body.append( keys[ i ], val );
            }
        }

        return fetch( getAjaxUrl(), {
            method: 'POST',
            credentials: 'same-origin',
            body: body
        } ).then( function( response ) {
            if ( ! response.ok ) {
                throw new Error( 'Erro na requisição (' + response.status + ')' );
            }
            return response.json();
        } );
    }

    /**
     * Debounce simples.
     *
     * @param {Function} fn
     * @param {number}   delay
     * @return {Function}
     */
    function debounce( fn, delay ) {
        var timer = null;
        return function() {
            var context = this;
            var args = arguments;
            if ( timer ) {
                clearTimeout( timer );
            }
            timer = setTimeout( function() {
                fn.apply( context, args );
            }, delay );
        };
    }

    /**
     * Escapa HTML para evitar XSS na renderização dinâmica.
     *
     * @param {string} str
     * @return {string}
     */
    function escapeHtml( str ) {
        if ( ! str ) {
            return '';
        }
        var div = document.createElement( 'div' );
        div.appendChild( document.createTextNode( str ) );
        return div.innerHTML;
    }

    /* ---------------------------------------------------------------
     * AJAX Functions
     * ------------------------------------------------------------- */

    /**
     * Busca cliente por telefone.
     *
     * @param {string}      phone
     * @param {HTMLElement}  wizard
     * @return {Promise<Object>}
     */
    function searchClient( phone, wizard ) {
        return ajaxPost( 'dps_booking_search_client', { phone: phone }, wizard );
    }

    /**
     * Busca pets de um cliente.
     *
     * @param {string|number} clientId
     * @param {number}        page
     * @param {HTMLElement}   wizard
     * @return {Promise<Object>}
     */
    function getPets( clientId, page, wizard ) {
        return ajaxPost( 'dps_booking_get_pets', { client_id: clientId, page: page }, wizard );
    }

    /**
     * Busca serviços disponíveis.
     *
     * @param {HTMLElement} wizard
     * @return {Promise<Object>}
     */
    function getServices( wizard ) {
        return ajaxPost( 'dps_booking_get_services', {}, wizard );
    }

    /**
     * Busca slots de horário para uma data.
     *
     * @param {string}      date   (YYYY-MM-DD)
     * @param {HTMLElement}  wizard
     * @return {Promise<Object>}
     */
    function getSlots( date, wizard ) {
        return ajaxPost( 'dps_booking_get_slots', { date: date }, wizard );
    }

    /**
     * Valida um step no servidor.
     *
     * @param {number}      step
     * @param {Object}      data
     * @param {HTMLElement}  wizard
     * @return {Promise<Object>}
     */
    function validateStep( step, data, wizard ) {
        data.step = step;
        return ajaxPost( 'dps_booking_validate_step', data, wizard );
    }

    /* ---------------------------------------------------------------
     * DOM Helpers
     * ------------------------------------------------------------- */

    /**
     * Mostra indicador de loading dentro de container.
     *
     * @param {HTMLElement} container
     */
    function showLoading( container ) {
        var existing = container.querySelector( '.dps-v2-booking__loading' );
        if ( existing ) {
            existing.style.display = 'flex';
            return;
        }
        var loader = document.createElement( 'div' );
        loader.className = 'dps-v2-booking__loading';
        loader.innerHTML = '<div class="dps-v2-loader dps-v2-loader--medium"><div class="dps-v2-loader__spinner"></div></div>';
        container.appendChild( loader );
    }

    /**
     * Esconde indicador de loading.
     *
     * @param {HTMLElement} container
     */
    function hideLoading( container ) {
        var loader = container.querySelector( '.dps-v2-booking__loading' );
        if ( loader ) {
            loader.style.display = 'none';
        }
    }

    /**
     * Exibe alerta dentro do wizard.
     *
     * @param {HTMLElement} wizard
     * @param {string}      message
     * @param {string}      type    (error|warning|info|success)
     */
    function showAlert( wizard, message, type ) {
        clearAlerts( wizard );
        var alert = document.createElement( 'div' );
        alert.className = 'dps-v2-alert dps-v2-alert--' + ( type || 'error' );
        alert.setAttribute( 'role', 'alert' );
        alert.innerHTML = '<span>' + escapeHtml( message ) + '</span>' +
            '<button type="button" class="dps-v2-alert__dismiss" aria-label="Fechar">&times;</button>';

        var header = wizard.querySelector( '.dps-v2-booking__header' );
        if ( header && header.nextSibling ) {
            header.parentNode.insertBefore( alert, header.nextSibling );
        } else {
            wizard.insertBefore( alert, wizard.firstChild );
        }

        var dismiss = alert.querySelector( '.dps-v2-alert__dismiss' );
        if ( dismiss ) {
            dismiss.addEventListener( 'click', function() {
                alert.remove();
            } );
        }

        alert.scrollIntoView( { behavior: 'smooth', block: 'center' } );
    }

    /**
     * Remove alertas existentes.
     *
     * @param {HTMLElement} wizard
     */
    function clearAlerts( wizard ) {
        var alerts = wizard.querySelectorAll( '.dps-v2-alert[role="alert"]' );
        alerts.forEach( function( el ) {
            el.remove();
        } );
    }

    /* ---------------------------------------------------------------
     * Wizard State Machine
     * ------------------------------------------------------------- */

    /**
     * Inicializa um wizard de agendamento.
     *
     * @param {HTMLElement} wizard
     */
    function initWizard( wizard ) {
        var form = wizard.querySelector( '.dps-v2-booking__form' );
        if ( ! form ) {
            return;
        }

        // State
        var state = {
            currentStep: 1,
            clientId: null,
            clientName: '',
            selectedPets: [],
            selectedServices: [],
            selectedDate: '',
            selectedSlot: '',
            appointmentType: '',
            notes: '',
            taxidog: false,
            taxidogPrice: 0,
            tosa: false,
            tosaPrice: 0,
            tosaOccurrence: '',
            petPage: 1,
            petHasMore: false
        };

        // Read initial step from URL
        var urlParams = new URLSearchParams( window.location.search );
        var urlStep = parseInt( urlParams.get( 'step' ), 10 );
        if ( urlStep >= 1 && urlStep <= TOTAL_STEPS ) {
            state.currentStep = urlStep;
        }

        // Cache DOM elements
        var steps = wizard.querySelectorAll( '.dps-v2-booking__step' );
        var progressItems = wizard.querySelectorAll( '.dps-v2-wizard-steps__item' );
        var nextBtn = wizard.querySelector( '[data-action="next"]' );
        var prevBtn = wizard.querySelector( '[data-action="prev"]' );
        var confirmBtn = wizard.querySelector( '[data-action="confirm"]' );
        var runningTotalEl = wizard.querySelector( '.dps-v2-running-total__value' );

        // Initial render
        goToStep( state.currentStep, true );

        // Bind navigation
        if ( nextBtn ) {
            nextBtn.addEventListener( 'click', function( e ) {
                e.preventDefault();
                handleNext();
            } );
        }

        if ( prevBtn ) {
            prevBtn.addEventListener( 'click', function( e ) {
                e.preventDefault();
                handlePrev();
            } );
        }

        if ( confirmBtn ) {
            confirmBtn.addEventListener( 'click', function( e ) {
                e.preventDefault();
                handleConfirm();
            } );
        }

        // Browser back/forward
        window.addEventListener( 'popstate', function() {
            var params = new URLSearchParams( window.location.search );
            var s = parseInt( params.get( 'step' ), 10 );
            if ( s >= 1 && s <= TOTAL_STEPS ) {
                goToStep( s, true );
            }
        } );

        // Init step-specific behaviors
        initStep1( wizard, state );
        initStep4( wizard, state );
        initExtras( wizard, state );
        initDismissibleAlerts( wizard );
        initFormSubmitLoader( form );

        /* -----------------------------------------------------------
         * Step navigation
         * --------------------------------------------------------- */

        /**
         * Navega para um step com transição.
         *
         * @param {number}  step
         * @param {boolean} skipPush  Pula history.pushState
         */
        function goToStep( step, skipPush ) {
            if ( step < 1 || step > TOTAL_STEPS ) {
                return;
            }

            var currentStepEl = getStepEl( state.currentStep );
            var nextStepEl = getStepEl( step );

            if ( ! nextStepEl ) {
                return;
            }

            // Fade out current
            if ( currentStepEl && currentStepEl !== nextStepEl ) {
                currentStepEl.classList.add( 'dps-v2-booking__step--entering' );
            }

            // Use rAF for smooth transition
            requestAnimationFrame( function() {
                // Hide all steps
                steps.forEach( function( s ) {
                    s.setAttribute( 'hidden', '' );
                    s.setAttribute( 'aria-hidden', 'true' );
                } );

                // Show target
                nextStepEl.removeAttribute( 'hidden' );
                nextStepEl.setAttribute( 'aria-hidden', 'false' );
                nextStepEl.classList.add( 'dps-v2-booking__step--entering' );

                requestAnimationFrame( function() {
                    nextStepEl.classList.remove( 'dps-v2-booking__step--entering' );
                } );
            } );

            state.currentStep = step;

            // Update progress bar
            updateProgress( step );

            // Update URL
            if ( ! skipPush ) {
                var url = new URL( window.location );
                url.searchParams.set( 'step', step );
                history.pushState( { step: step }, '', url );
            }

            // Update navigation buttons visibility
            updateNavButtons();

            // Trigger step activation
            onStepActivated( step );
        }

        /**
         * @param {number} step
         * @return {HTMLElement|null}
         */
        function getStepEl( step ) {
            return wizard.querySelector( '.dps-v2-booking__step[data-step="' + step + '"]' );
        }

        /**
         * Atualiza classes do progress bar.
         *
         * @param {number} activeStep
         */
        function updateProgress( activeStep ) {
            progressItems.forEach( function( item, idx ) {
                var stepNum = idx + 1;
                item.classList.remove( 'dps-v2-wizard-steps__item--active', 'dps-v2-wizard-steps__item--completed' );
                if ( stepNum === activeStep ) {
                    item.classList.add( 'dps-v2-wizard-steps__item--active' );
                } else if ( stepNum < activeStep ) {
                    item.classList.add( 'dps-v2-wizard-steps__item--completed' );
                }
            } );
        }

        /**
         * Mostra/esconde botões de navegação.
         */
        function updateNavButtons() {
            if ( prevBtn ) {
                prevBtn.style.display = state.currentStep > 1 ? '' : 'none';
            }
            if ( nextBtn ) {
                nextBtn.style.display = state.currentStep < TOTAL_STEPS ? '' : 'none';
            }
            if ( confirmBtn ) {
                confirmBtn.style.display = state.currentStep === TOTAL_STEPS ? '' : 'none';
            }
        }

        /**
         * Callback quando um step é ativado.
         *
         * @param {number} step
         */
        function onStepActivated( step ) {
            if ( 2 === step && state.clientId ) {
                loadPets();
            } else if ( 3 === step ) {
                loadServices();
            } else if ( 5 === step ) {
                buildSummary();
            }
        }

        /* -----------------------------------------------------------
         * Next / Previous / Confirm
         * --------------------------------------------------------- */

        /**
         * Avança para o próximo step com validação.
         */
        function handleNext() {
            var valid = validateCurrentStepClient();
            if ( ! valid ) {
                return;
            }

            var stepData = collectStepData( state.currentStep );

            // Show loading on next button
            if ( nextBtn ) {
                nextBtn.setAttribute( 'disabled', 'disabled' );
            }

            validateStep( state.currentStep, stepData, wizard ).then( function( response ) {
                if ( nextBtn ) {
                    nextBtn.removeAttribute( 'disabled' );
                }
                if ( response.success ) {
                    clearAlerts( wizard );
                    goToStep( state.currentStep + 1 );
                } else {
                    var msg = ( response.data && response.data.message ) ? response.data.message : 'Erro na validação.';
                    showAlert( wizard, msg, 'error' );
                }
            } ).catch( function( err ) {
                if ( nextBtn ) {
                    nextBtn.removeAttribute( 'disabled' );
                }
                showAlert( wizard, err.message || 'Erro de conexão.', 'error' );
            } );
        }

        /**
         * Volta ao step anterior sem validação.
         */
        function handlePrev() {
            clearAlerts( wizard );
            goToStep( state.currentStep - 1 );
        }

        /**
         * Submete o formulário final.
         */
        function handleConfirm() {
            if ( confirmBtn ) {
                confirmBtn.classList.add( 'dps-v2-button--submitting' );
                confirmBtn.setAttribute( 'disabled', 'disabled' );
            }

            // Populate hidden fields before submit
            populateHiddenFields();

            form.submit();
        }

        /* -----------------------------------------------------------
         * Client-side validation
         * --------------------------------------------------------- */

        /**
         * Valida o step atual no client-side.
         *
         * @return {boolean}
         */
        function validateCurrentStepClient() {
            clearAlerts( wizard );

            switch ( state.currentStep ) {
                case 1:
                    if ( ! state.clientId ) {
                        showAlert( wizard, 'Selecione um cliente para continuar.', 'warning' );
                        return false;
                    }
                    return true;

                case 2:
                    if ( state.selectedPets.length === 0 ) {
                        showAlert( wizard, 'Selecione pelo menos um pet.', 'warning' );
                        return false;
                    }
                    return true;

                case 3:
                    if ( state.selectedServices.length === 0 ) {
                        showAlert( wizard, 'Selecione pelo menos um serviço.', 'warning' );
                        return false;
                    }
                    return true;

                case 4:
                    if ( ! state.selectedDate || ! state.selectedSlot ) {
                        showAlert( wizard, 'Selecione uma data e horário.', 'warning' );
                        return false;
                    }
                    if ( ! state.appointmentType ) {
                        showAlert( wizard, 'Selecione o tipo de atendimento.', 'warning' );
                        return false;
                    }
                    return true;

                default:
                    return true;
            }
        }

        /**
         * Coleta dados do step para validação server-side.
         *
         * @param {number} step
         * @return {Object}
         */
        function collectStepData( step ) {
            switch ( step ) {
                case 1:
                    return { client_id: state.clientId };
                case 2:
                    return { client_id: state.clientId, pet_ids: state.selectedPets.map( function( p ) { return p.id; } ) };
                case 3:
                    return { service_ids: state.selectedServices.map( function( s ) { return s.id; } ) };
                case 4:
                    return {
                        date: state.selectedDate,
                        slot: state.selectedSlot,
                        appointment_type: state.appointmentType,
                        notes: state.notes
                    };
                default:
                    return {};
            }
        }

        /**
         * Popula campos hidden antes do submit final.
         */
        function populateHiddenFields() {
            setHiddenValue( 'client_id', state.clientId );
            setHiddenArrayValues( 'pet_ids', state.selectedPets.map( function( p ) { return p.id; } ) );
            setHiddenArrayValues( 'service_ids', state.selectedServices.map( function( s ) { return s.id; } ) );
            setHiddenValue( 'booking_date', state.selectedDate );
            setHiddenValue( 'booking_slot', state.selectedSlot );
            setHiddenValue( 'appointment_type', state.appointmentType );
            setHiddenValue( 'notes', state.notes );
            setHiddenValue( 'taxidog', state.taxidog ? '1' : '0' );
            setHiddenValue( 'taxidog_price', state.taxidogPrice );
            setHiddenValue( 'tosa', state.tosa ? '1' : '0' );
            setHiddenValue( 'tosa_price', state.tosaPrice );
            setHiddenValue( 'tosa_occurrence', state.tosaOccurrence );
        }

        /**
         * Define valor de um campo hidden, criando-o se necessário.
         *
         * @param {string} name
         * @param {*}      value
         */
        function setHiddenValue( name, value ) {
            var input = form.querySelector( 'input[name="' + name + '"]' );
            if ( ! input ) {
                input = document.createElement( 'input' );
                input.type = 'hidden';
                input.name = name;
                form.appendChild( input );
            }
            input.value = value;
        }

        /**
         * Define múltiplos campos hidden para array, criando-os se necessário.
         *
         * @param {string} name
         * @param {Array}  values
         */
        function setHiddenArrayValues( name, values ) {
            // Remove existing fields for this name
            var existing = form.querySelectorAll( 'input[name="' + name + '[]"]' );
            existing.forEach( function( el ) {
                el.remove();
            } );

            values.forEach( function( val ) {
                var input = document.createElement( 'input' );
                input.type = 'hidden';
                input.name = name + '[]';
                input.value = val;
                form.appendChild( input );
            } );
        }

        /* -----------------------------------------------------------
         * Step 1 — Client Search
         * --------------------------------------------------------- */

        /**
         * Inicializa comportamentos do Step 1.
         *
         * @param {HTMLElement} wiz
         * @param {Object}     st
         */
        function initStep1( wiz, st ) {
            var searchInput = wiz.querySelector( '.dps-v2-search__input' );
            var searchBtn = wiz.querySelector( '.dps-v2-search__button' );
            var resultsContainer = wiz.querySelector( '.dps-v2-search-results' );

            if ( ! searchInput || ! searchBtn || ! resultsContainer ) {
                return;
            }

            var doSearch = function() {
                var phone = searchInput.value.replace( /\D/g, '' );
                if ( phone.length < 8 ) {
                    showAlert( wiz, 'Digite um telefone com pelo menos 8 dígitos.', 'warning' );
                    return;
                }

                clearAlerts( wiz );
                showLoading( resultsContainer );
                resultsContainer.innerHTML = '';
                showLoading( resultsContainer );

                searchClient( phone, wiz ).then( function( response ) {
                    hideLoading( resultsContainer );
                    if ( response.success && response.data && response.data.clients ) {
                        renderClientResults( resultsContainer, response.data.clients, st );
                    } else {
                        var msg = ( response.data && response.data.message ) ? response.data.message : 'Nenhum cliente encontrado.';
                        resultsContainer.innerHTML = '<p class="dps-v2-color-on-surface-variant">' + escapeHtml( msg ) + '</p>';
                    }
                } ).catch( function( err ) {
                    hideLoading( resultsContainer );
                    showAlert( wiz, err.message || 'Erro ao buscar cliente.', 'error' );
                } );
            };

            searchBtn.addEventListener( 'click', function( e ) {
                e.preventDefault();
                doSearch();
            } );

            // Debounced search on input
            var debouncedSearch = debounce( function() {
                var phone = searchInput.value.replace( /\D/g, '' );
                if ( phone.length >= 10 ) {
                    doSearch();
                }
            }, DEBOUNCE_MS );

            searchInput.addEventListener( 'input', debouncedSearch );

            // Enter key
            searchInput.addEventListener( 'keydown', function( e ) {
                if ( 'Enter' === e.key ) {
                    e.preventDefault();
                    doSearch();
                }
            } );
        }

        /**
         * Renderiza cards de resultado de busca de clientes.
         *
         * @param {HTMLElement} container
         * @param {Array}       clients
         * @param {Object}      st
         */
        function renderClientResults( container, clients, st ) {
            container.innerHTML = '';

            if ( ! clients.length ) {
                container.innerHTML = '<p class="dps-v2-color-on-surface-variant">Nenhum cliente encontrado.</p>';
                return;
            }

            clients.forEach( function( client ) {
                var card = document.createElement( 'div' );
                card.className = 'dps-v2-client-card';
                card.setAttribute( 'tabindex', '0' );
                card.setAttribute( 'role', 'option' );
                card.setAttribute( 'data-client-id', client.id );

                card.innerHTML = '<span class="dps-v2-client-card__name">' + escapeHtml( client.name ) + '</span>' +
                    '<span class="dps-v2-client-card__phone">' + escapeHtml( client.phone || '' ) + '</span>' +
                    '<span class="dps-v2-client-card__email">' + escapeHtml( client.email || '' ) + '</span>';

                if ( st.clientId && String( st.clientId ) === String( client.id ) ) {
                    card.classList.add( 'dps-v2-client-card--selected' );
                }

                card.addEventListener( 'click', function() {
                    selectClient( container, card, client, st );
                } );

                card.addEventListener( 'keydown', function( e ) {
                    if ( 'Enter' === e.key || ' ' === e.key ) {
                        e.preventDefault();
                        selectClient( container, card, client, st );
                    }
                } );

                container.appendChild( card );
            } );
        }

        /**
         * Marca um client card como selecionado.
         *
         * @param {HTMLElement} container
         * @param {HTMLElement} card
         * @param {Object}      client
         * @param {Object}      st
         */
        function selectClient( container, card, client, st ) {
            var allCards = container.querySelectorAll( '.dps-v2-client-card' );
            allCards.forEach( function( c ) {
                c.classList.remove( 'dps-v2-client-card--selected' );
                c.setAttribute( 'aria-selected', 'false' );
            } );
            card.classList.add( 'dps-v2-client-card--selected' );
            card.setAttribute( 'aria-selected', 'true' );
            st.clientId = client.id;
            st.clientName = client.name || '';
        }

        /* -----------------------------------------------------------
         * Step 2 — Pets
         * --------------------------------------------------------- */

        /**
         * Carrega pets do cliente selecionado.
         */
        function loadPets() {
            var stepEl = getStepEl( 2 );
            if ( ! stepEl ) {
                return;
            }

            var grid = stepEl.querySelector( '.dps-v2-selectable-grid' );
            if ( ! grid ) {
                return;
            }

            state.petPage = 1;
            grid.innerHTML = '';
            showLoading( grid );

            getPets( state.clientId, state.petPage, wizard ).then( function( response ) {
                hideLoading( grid );
                if ( response.success && response.data && response.data.pets ) {
                    renderPetCards( grid, response.data.pets );
                    state.petHasMore = response.data.page < response.data.total_pages;
                    renderLoadMore( stepEl, grid );
                } else {
                    grid.innerHTML = '<p class="dps-v2-color-on-surface-variant">Nenhum pet encontrado.</p>';
                }
            } ).catch( function( err ) {
                hideLoading( grid );
                showAlert( wizard, err.message || 'Erro ao carregar pets.', 'error' );
            } );
        }

        /**
         * Renderiza cards de pets selecionáveis.
         *
         * @param {HTMLElement} grid
         * @param {Array}       pets
         */
        function renderPetCards( grid, pets ) {
            pets.forEach( function( pet ) {
                var card = document.createElement( 'label' );
                card.className = 'dps-v2-selectable-card';
                card.setAttribute( 'data-pet-id', pet.id );

                var isSelected = state.selectedPets.some( function( p ) { return String( p.id ) === String( pet.id ); } );
                if ( isSelected ) {
                    card.classList.add( 'dps-v2-selectable-card--selected' );
                }

                card.innerHTML = '<input type="checkbox" class="dps-v2-selectable-card__checkbox" value="' + escapeHtml( String( pet.id ) ) + '"' + ( isSelected ? ' checked' : '' ) + '>' +
                    '<span class="dps-v2-selectable-card__indicator"></span>' +
                    '<span class="dps-v2-selectable-card__info">' +
                    '<span class="dps-v2-selectable-card__name">' + escapeHtml( pet.name ) + '</span>' +
                    '<span class="dps-v2-selectable-card__details">' + escapeHtml( pet.species || '' ) + ( pet.breed ? ' · ' + escapeHtml( pet.breed ) : '' ) + '</span>' +
                    '</span>';

                var checkbox = card.querySelector( '.dps-v2-selectable-card__checkbox' );
                checkbox.addEventListener( 'change', function() {
                    if ( checkbox.checked ) {
                        card.classList.add( 'dps-v2-selectable-card--selected' );
                        state.selectedPets.push( { id: pet.id, name: pet.name } );
                    } else {
                        card.classList.remove( 'dps-v2-selectable-card--selected' );
                        state.selectedPets = state.selectedPets.filter( function( p ) { return String( p.id ) !== String( pet.id ); } );
                    }
                } );

                grid.appendChild( card );
            } );
        }

        /**
         * Renderiza botão "Carregar mais" para paginação de pets.
         *
         * @param {HTMLElement} stepEl
         * @param {HTMLElement} grid
         */
        function renderLoadMore( stepEl, grid ) {
            var existing = stepEl.querySelector( '.dps-v2-load-more' );
            if ( existing ) {
                existing.remove();
            }

            if ( ! state.petHasMore ) {
                return;
            }

            var wrapper = document.createElement( 'div' );
            wrapper.className = 'dps-v2-load-more';
            wrapper.innerHTML = '<button type="button" class="dps-v2-load-more__button">Carregar mais</button>';

            var btn = wrapper.querySelector( '.dps-v2-load-more__button' );
            btn.addEventListener( 'click', function() {
                state.petPage++;
                btn.textContent = 'Carregando...';
                btn.setAttribute( 'disabled', 'disabled' );

                getPets( state.clientId, state.petPage, wizard ).then( function( response ) {
                    if ( response.success && response.data && response.data.pets ) {
                        renderPetCards( grid, response.data.pets );
                        state.petHasMore = response.data.page < response.data.total_pages;
                    } else {
                        state.petHasMore = false;
                    }
                    renderLoadMore( stepEl, grid );
                } ).catch( function() {
                    btn.textContent = 'Carregar mais';
                    btn.removeAttribute( 'disabled' );
                } );
            } );

            stepEl.appendChild( wrapper );
        }

        /* -----------------------------------------------------------
         * Step 3 — Services
         * --------------------------------------------------------- */

        /**
         * Carrega serviços disponíveis.
         */
        function loadServices() {
            var stepEl = getStepEl( 3 );
            if ( ! stepEl ) {
                return;
            }

            var grid = stepEl.querySelector( '.dps-v2-selectable-grid' );
            if ( ! grid ) {
                return;
            }

            grid.innerHTML = '';
            showLoading( grid );

            getServices( wizard ).then( function( response ) {
                hideLoading( grid );
                if ( response.success && response.data && response.data.services ) {
                    renderServiceCards( grid, response.data.services );
                } else {
                    grid.innerHTML = '<p class="dps-v2-color-on-surface-variant">Nenhum serviço disponível.</p>';
                }
            } ).catch( function( err ) {
                hideLoading( grid );
                showAlert( wizard, err.message || 'Erro ao carregar serviços.', 'error' );
            } );
        }

        /**
         * Renderiza cards de serviços selecionáveis.
         *
         * @param {HTMLElement} grid
         * @param {Array}       services
         */
        function renderServiceCards( grid, services ) {
            services.forEach( function( service ) {
                var card = document.createElement( 'label' );
                card.className = 'dps-v2-selectable-card';
                card.setAttribute( 'data-service-id', service.id );

                var isSelected = state.selectedServices.some( function( s ) { return String( s.id ) === String( service.id ); } );
                if ( isSelected ) {
                    card.classList.add( 'dps-v2-selectable-card--selected' );
                }

                var priceText = service.price ? formatBRL( service.price ) : '';

                card.innerHTML = '<input type="checkbox" class="dps-v2-selectable-card__checkbox" value="' + escapeHtml( String( service.id ) ) + '"' + ( isSelected ? ' checked' : '' ) + '>' +
                    '<span class="dps-v2-selectable-card__indicator"></span>' +
                    '<span class="dps-v2-selectable-card__info">' +
                    '<span class="dps-v2-selectable-card__name">' + escapeHtml( service.name ) + '</span>' +
                    '<span class="dps-v2-selectable-card__details">' + escapeHtml( service.description || '' ) + '</span>' +
                    '</span>' +
                    ( priceText ? '<span class="dps-v2-selectable-card__price">' + escapeHtml( priceText ) + '</span>' : '' );

                var checkbox = card.querySelector( '.dps-v2-selectable-card__checkbox' );
                checkbox.addEventListener( 'change', function() {
                    if ( checkbox.checked ) {
                        card.classList.add( 'dps-v2-selectable-card--selected' );
                        state.selectedServices.push( { id: service.id, name: service.name, price: parseFloat( service.price ) || 0 } );
                    } else {
                        card.classList.remove( 'dps-v2-selectable-card--selected' );
                        state.selectedServices = state.selectedServices.filter( function( s ) { return String( s.id ) !== String( service.id ); } );
                    }
                    updateRunningTotal();
                } );

                grid.appendChild( card );
            } );
        }

        /**
         * Atualiza o total corrente exibido.
         */
        function updateRunningTotal() {
            var total = 0;

            state.selectedServices.forEach( function( s ) {
                total += s.price || 0;
            } );

            if ( state.taxidog ) {
                total += parseFloat( state.taxidogPrice ) || 0;
            }
            if ( state.tosa ) {
                total += parseFloat( state.tosaPrice ) || 0;
            }

            if ( runningTotalEl ) {
                runningTotalEl.textContent = formatBRL( total );
            }
        }

        /* -----------------------------------------------------------
         * Step 4 — Date / Time / Type
         * --------------------------------------------------------- */

        /**
         * Inicializa comportamentos do Step 4.
         *
         * @param {HTMLElement} wiz
         * @param {Object}     st
         */
        function initStep4( wiz, st ) {
            var dateInput = wiz.querySelector( '.dps-v2-booking__step[data-step="4"] input[type="date"]' );
            var slotGrid = wiz.querySelector( '.dps-v2-slot-grid' );
            var typeSelector = wiz.querySelector( '.dps-v2-type-selector' );
            var notesArea = wiz.querySelector( '.dps-v2-booking__step[data-step="4"] textarea' );

            if ( dateInput && slotGrid ) {
                dateInput.addEventListener( 'change', function() {
                    st.selectedDate = dateInput.value;
                    st.selectedSlot = '';
                    loadSlots( dateInput.value, slotGrid );
                } );
            }

            if ( typeSelector ) {
                initTypeSelector( typeSelector, st );
            }

            if ( notesArea ) {
                notesArea.addEventListener( 'input', function() {
                    st.notes = notesArea.value;
                } );
            }
        }

        /**
         * Carrega slots de horário.
         *
         * @param {string}      date
         * @param {HTMLElement}  grid
         */
        function loadSlots( date, grid ) {
            grid.innerHTML = '';
            showLoading( grid );

            getSlots( date, wizard ).then( function( response ) {
                hideLoading( grid );
                if ( response.success && response.data && response.data.slots ) {
                    renderSlots( grid, response.data.slots );
                } else {
                    grid.innerHTML = '<p class="dps-v2-color-on-surface-variant">Nenhum horário disponível.</p>';
                }
            } ).catch( function( err ) {
                hideLoading( grid );
                showAlert( wizard, err.message || 'Erro ao carregar horários.', 'error' );
            } );
        }

        /**
         * Renderiza grid de slots.
         *
         * @param {HTMLElement} grid
         * @param {Array}       slots
         */
        function renderSlots( grid, slots ) {
            grid.innerHTML = '';

            slots.forEach( function( slot ) {
                var el = document.createElement( 'label' );
                var isAvailable = slot.available !== false;

                el.className = 'dps-v2-slot ' + ( isAvailable ? 'dps-v2-slot--available' : 'dps-v2-slot--unavailable' );
                el.innerHTML = '<input type="radio" name="booking_slot" value="' + escapeHtml( slot.time ) + '"' + ( ! isAvailable ? ' disabled' : '' ) + '>' +
                    '<span>' + escapeHtml( slot.time ) + '</span>';

                if ( isAvailable ) {
                    var radio = el.querySelector( 'input[type="radio"]' );
                    radio.addEventListener( 'change', function() {
                        grid.querySelectorAll( '.dps-v2-slot' ).forEach( function( s ) {
                            s.classList.remove( 'dps-v2-slot--selected' );
                        } );
                        el.classList.add( 'dps-v2-slot--selected' );
                        state.selectedSlot = slot.time;
                    } );
                }

                grid.appendChild( el );
            } );
        }

        /**
         * Inicializa seletor de tipo de atendimento.
         *
         * @param {HTMLElement} selector
         * @param {Object}     st
         */
        function initTypeSelector( selector, st ) {
            var cards = selector.querySelectorAll( '.dps-v2-type-card' );

            cards.forEach( function( card ) {
                var radio = card.querySelector( 'input[type="radio"]' );
                if ( ! radio ) {
                    return;
                }

                radio.addEventListener( 'change', function() {
                    cards.forEach( function( c ) {
                        c.classList.remove( 'dps-v2-type-card--selected' );
                    } );
                    card.classList.add( 'dps-v2-type-card--selected' );
                    st.appointmentType = radio.value;

                    // Tosa visibility depends on appointment type
                    updateTosaVisibility( st );
                } );

                // Initial state
                if ( radio.checked ) {
                    card.classList.add( 'dps-v2-type-card--selected' );
                    st.appointmentType = radio.value;
                }
            } );
        }

        /* -----------------------------------------------------------
         * Extras — TaxiDog + Tosa
         * --------------------------------------------------------- */

        /**
         * Inicializa comportamentos de extras.
         *
         * @param {HTMLElement} wiz
         * @param {Object}     st
         */
        function initExtras( wiz, st ) {
            // TaxiDog
            var taxidogCheckbox = wiz.querySelector( '[data-extra="taxidog"] input[type="checkbox"]' );
            var taxidogBody = wiz.querySelector( '[data-extra="taxidog"] .dps-v2-extra-card__body' );
            var taxidogPriceInput = wiz.querySelector( '[data-extra="taxidog"] input[name="taxidog_price"]' );

            if ( taxidogCheckbox ) {
                taxidogCheckbox.addEventListener( 'change', function() {
                    st.taxidog = taxidogCheckbox.checked;
                    if ( taxidogBody ) {
                        taxidogBody.classList.toggle( 'dps-v2-extra-card__body--visible', taxidogCheckbox.checked );
                    }
                    updateRunningTotal();
                } );
            }

            if ( taxidogPriceInput ) {
                taxidogPriceInput.addEventListener( 'input', function() {
                    st.taxidogPrice = taxidogPriceInput.value;
                    updateRunningTotal();
                } );
            }

            // Tosa
            var tosaCheckbox = wiz.querySelector( '[data-extra="tosa"] input[type="checkbox"]' );
            var tosaBody = wiz.querySelector( '[data-extra="tosa"] .dps-v2-extra-card__body' );
            var tosaPriceInput = wiz.querySelector( '[data-extra="tosa"] input[name="tosa_price"]' );
            var tosaOccurrenceSelect = wiz.querySelector( '[data-extra="tosa"] select[name="tosa_occurrence"]' );

            if ( tosaCheckbox ) {
                tosaCheckbox.addEventListener( 'change', function() {
                    st.tosa = tosaCheckbox.checked;
                    if ( tosaBody ) {
                        tosaBody.classList.toggle( 'dps-v2-extra-card__body--visible', tosaCheckbox.checked );
                    }
                    updateRunningTotal();
                } );
            }

            if ( tosaPriceInput ) {
                tosaPriceInput.addEventListener( 'input', function() {
                    st.tosaPrice = tosaPriceInput.value;
                    updateRunningTotal();
                } );
            }

            if ( tosaOccurrenceSelect ) {
                tosaOccurrenceSelect.addEventListener( 'change', function() {
                    st.tosaOccurrence = tosaOccurrenceSelect.value;
                } );
            }
        }

        /**
         * Mostra/esconde seção de tosa baseado no tipo de atendimento.
         *
         * @param {Object} st
         */
        function updateTosaVisibility( st ) {
            var tosaCard = wizard.querySelector( '[data-extra="tosa"]' );
            if ( ! tosaCard ) {
                return;
            }

            if ( 'subscription' === st.appointmentType ) {
                tosaCard.classList.remove( 'dps-v2-extra-card--disabled' );
            } else {
                tosaCard.classList.add( 'dps-v2-extra-card--disabled' );
                // Reset tosa selection
                var tosaCheckbox = tosaCard.querySelector( 'input[type="checkbox"]' );
                if ( tosaCheckbox && tosaCheckbox.checked ) {
                    tosaCheckbox.checked = false;
                    st.tosa = false;
                    var tosaBody = tosaCard.querySelector( '.dps-v2-extra-card__body' );
                    if ( tosaBody ) {
                        tosaBody.classList.remove( 'dps-v2-extra-card__body--visible' );
                    }
                    updateRunningTotal();
                }
            }
        }

        /* -----------------------------------------------------------
         * Step 5 — Summary
         * --------------------------------------------------------- */

        /**
         * Constrói o resumo final a partir do state.
         */
        function buildSummary() {
            var stepEl = getStepEl( 5 );
            if ( ! stepEl ) {
                return;
            }

            var summary = stepEl.querySelector( '.dps-v2-summary' );
            if ( ! summary ) {
                return;
            }

            var total = 0;
            var html = '';

            // Client section
            html += '<div class="dps-v2-summary__section">' +
                '<h3 class="dps-v2-summary__section-title">Cliente</h3>' +
                '<div class="dps-v2-summary__row"><span class="dps-v2-summary__label">Nome</span><span class="dps-v2-summary__value">' + escapeHtml( state.clientName ) + '</span></div>' +
                '</div>';

            html += '<hr class="dps-v2-summary__divider">';

            // Pets section
            html += '<div class="dps-v2-summary__section">' +
                '<h3 class="dps-v2-summary__section-title">Pets</h3>';
            state.selectedPets.forEach( function( pet ) {
                html += '<div class="dps-v2-summary__row"><span class="dps-v2-summary__value">' + escapeHtml( pet.name ) + '</span></div>';
            } );
            html += '</div>';

            html += '<hr class="dps-v2-summary__divider">';

            // Services section
            html += '<div class="dps-v2-summary__section">' +
                '<h3 class="dps-v2-summary__section-title">Serviços</h3>';
            state.selectedServices.forEach( function( svc ) {
                total += svc.price || 0;
                html += '<div class="dps-v2-summary__row"><span class="dps-v2-summary__label">' + escapeHtml( svc.name ) + '</span><span class="dps-v2-summary__value">' + escapeHtml( formatBRL( svc.price ) ) + '</span></div>';
            } );
            html += '</div>';

            html += '<hr class="dps-v2-summary__divider">';

            // DateTime section
            html += '<div class="dps-v2-summary__section">' +
                '<h3 class="dps-v2-summary__section-title">Data e Horário</h3>' +
                '<div class="dps-v2-summary__row"><span class="dps-v2-summary__label">Data</span><span class="dps-v2-summary__value">' + escapeHtml( state.selectedDate ) + '</span></div>' +
                '<div class="dps-v2-summary__row"><span class="dps-v2-summary__label">Horário</span><span class="dps-v2-summary__value">' + escapeHtml( state.selectedSlot ) + '</span></div>' +
                '<div class="dps-v2-summary__row"><span class="dps-v2-summary__label">Tipo</span><span class="dps-v2-summary__value">' + escapeHtml( state.appointmentType ) + '</span></div>';
            if ( state.notes ) {
                html += '<div class="dps-v2-summary__row"><span class="dps-v2-summary__label">Observações</span><span class="dps-v2-summary__value">' + escapeHtml( state.notes ) + '</span></div>';
            }
            html += '</div>';

            // Extras section
            if ( state.taxidog || state.tosa ) {
                html += '<hr class="dps-v2-summary__divider">';
                html += '<div class="dps-v2-summary__section">' +
                    '<h3 class="dps-v2-summary__section-title">Extras</h3>';
                if ( state.taxidog ) {
                    var taxidogVal = parseFloat( state.taxidogPrice ) || 0;
                    total += taxidogVal;
                    html += '<div class="dps-v2-summary__row"><span class="dps-v2-summary__label">TaxiDog</span><span class="dps-v2-summary__value">' + escapeHtml( formatBRL( taxidogVal ) ) + '</span></div>';
                }
                if ( state.tosa ) {
                    var tosaVal = parseFloat( state.tosaPrice ) || 0;
                    total += tosaVal;
                    html += '<div class="dps-v2-summary__row"><span class="dps-v2-summary__label">Tosa' + ( state.tosaOccurrence ? ' (' + escapeHtml( state.tosaOccurrence ) + ')' : '' ) + '</span><span class="dps-v2-summary__value">' + escapeHtml( formatBRL( tosaVal ) ) + '</span></div>';
                }
                html += '</div>';
            }

            // Total
            html += '<hr class="dps-v2-summary__divider">';
            html += '<div class="dps-v2-summary__total"><span>Total</span><span>' + escapeHtml( formatBRL( total ) ) + '</span></div>';

            summary.innerHTML = html;
        }
    }

    /* ---------------------------------------------------------------
     * Shared UI behaviors
     * ------------------------------------------------------------- */

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

    /* ---------------------------------------------------------------
     * Init
     * ------------------------------------------------------------- */

    /**
     * Inicializa todos os wizards de agendamento V2 na página.
     */
    function init() {
        var wizards = document.querySelectorAll( '.dps-v2-booking' );

        wizards.forEach( function( wizard ) {
            initWizard( wizard );
        } );
    }

    // Init on DOM ready
    if ( 'loading' === document.readyState ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }

} )();
