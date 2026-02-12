/**
 * Booking V2 — JavaScript (Vanilla)
 *
 * Wizard state machine nativo para o agendamento V2.
 * Zero dependência de jQuery.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 */

( function() {
    'use strict';

    /**
     * Inicializa comportamentos do wizard de agendamento V2.
     */
    function init() {
        var wizards = document.querySelectorAll( '.dps-v2-booking' );

        wizards.forEach( function( wizard ) {
            initFormSubmitLoader( wizard );
            initDismissibleAlerts( wizard );
        } );
    }

    /**
     * Mostra loader no botão ao submeter o formulário.
     *
     * @param {HTMLElement} wizard
     */
    function initFormSubmitLoader( wizard ) {
        var form = wizard.querySelector( '.dps-v2-booking__form' );
        if ( ! form ) {
            return;
        }

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
     * @param {HTMLElement} container
     */
    function initDismissibleAlerts( container ) {
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
