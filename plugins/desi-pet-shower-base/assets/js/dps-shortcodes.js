( function() {
    'use strict';

    /**
     * Exibe feedback temporário no botão de cópia.
     *
     * @param {HTMLElement} button Botão acionado.
     * @param {string} message Mensagem de sucesso.
     */
    function showCopiedState( button, message ) {
        if ( ! button ) {
            return;
        }

        const originalText = button.textContent;
        button.classList.add( 'is-copied' );
        button.textContent = message;

        setTimeout( function() {
            button.classList.remove( 'is-copied' );
            button.textContent = originalText;
        }, 1400 );
    }

    /**
     * Copia o shortcode para a área de transferência.
     *
     * @param {string} text Texto a copiar.
     * @param {HTMLElement} button Botão acionado.
     */
    function copyToClipboard( text, button ) {
        if ( ! text ) {
            return;
        }

        if ( navigator.clipboard && navigator.clipboard.writeText ) {
            navigator.clipboard.writeText( text ).then( function() {
                showCopiedState(
                    button,
                    button.dataset.dpsCopySuccess || 'Copiado!'
                );
            } );
            return;
        }

        // Fallback para navegadores sem API de clipboard
        const tempInput = document.createElement( 'textarea' );
        tempInput.value = text;
        tempInput.setAttribute( 'readonly', '' );
        tempInput.style.position = 'absolute';
        tempInput.style.left = '-9999px';
        document.body.appendChild( tempInput );
        tempInput.select();
        document.execCommand( 'copy' );
        document.body.removeChild( tempInput );

        showCopiedState(
            button,
            button.dataset.dpsCopySuccess || 'Copiado!'
        );
    }

    document.addEventListener( 'click', function( event ) {
        const button = event.target.closest( '.dps-copy-button' );

        if ( ! button ) {
            return;
        }

        const text = button.getAttribute( 'data-dps-copy' );
        copyToClipboard( text, button );
    } );
} )();
