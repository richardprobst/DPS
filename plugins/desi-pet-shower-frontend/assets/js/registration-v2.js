/**
 * Registration V2 — JavaScript (Vanilla)
 *
 * Comportamento nativo para o formulário de cadastro V2.
 * Zero dependência de jQuery.
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
        const forms = document.querySelectorAll( '.dps-v2-registration__form' );

        forms.forEach( function( form ) {
            initFormSubmitLoader( form );
            initDismissibleAlerts( form.closest( '.dps-v2-registration' ) );
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
