/**
 * Frontend Add-on — JavaScript
 *
 * Vanilla JS, IIFE, strict mode. Nenhuma dependência de jQuery.
 * Ponto de entrada para funcionalidades JS do add-on.
 *
 * @since 1.0.0
 */
( function () {
    'use strict';

    /**
     * Inicializa quando o DOM estiver pronto.
     */
    function init() {
        // Módulos registrarão seus handlers aqui nas fases seguintes.
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }
} )();
