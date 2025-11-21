/**
 * Client Portal JavaScript
 * Gerencia interações do Portal do Cliente DPS
 */

(function() {
    'use strict';

    /**
     * Inicializa os handlers do portal
     */
    function init() {
        handleFormSubmits();
        handleSmoothScroll();
    }

    /**
     * Adiciona feedback visual durante submit de formulários
     */
    function handleFormSubmits() {
        const forms = document.querySelectorAll('.dps-portal-form');
        
        forms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                const submitBtn = form.querySelector('.dps-submit-btn');
                
                if (submitBtn && !submitBtn.disabled) {
                    // Salva texto original
                    const originalText = submitBtn.textContent;
                    
                    // Desabilita botão e mostra "Salvando..."
                    submitBtn.disabled = true;
                    submitBtn.classList.add('is-loading');
                    submitBtn.textContent = 'Salvando...';
                    
                    // Se houver erro de validação HTML5, reabilita o botão
                    setTimeout(function() {
                        if (!form.checkValidity()) {
                            submitBtn.disabled = false;
                            submitBtn.classList.remove('is-loading');
                            submitBtn.textContent = originalText;
                        }
                    }, 100);
                }
            });
        });
    }

    /**
     * Implementa scroll suave para links de âncora
     * (Alternativa caso scroll-behavior: smooth no CSS não funcione em todos os navegadores)
     */
    function handleSmoothScroll() {
        const navLinks = document.querySelectorAll('.dps-portal-nav__link');
        
        navLinks.forEach(function(link) {
            link.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                
                // Verifica se é uma âncora
                if (href && href.startsWith('#')) {
                    const target = document.querySelector(href);
                    
                    if (target) {
                        e.preventDefault();
                        
                        // Scroll suave
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                        
                        // Atualiza URL sem reload
                        if (history.pushState) {
                            history.pushState(null, null, href);
                        }
                    }
                }
            });
        });
    }

    // Inicializa quando DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
