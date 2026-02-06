/**
 * DPS Settings Page JavaScript
 *
 * Navegação client-side entre abas, busca de configurações
 * e detecção de alterações não salvas.
 *
 * @package DPS_Base_Plugin
 * @since 2.6.0
 */

/* global jQuery */
(function( $ ) {
    'use strict';

    var DPSSettings = {

        /**
         * Elementos DOM cacheados.
         */
        $wrapper: null,
        $nav: null,
        $tabs: null,
        $sections: null,
        $search: null,
        $searchWrapper: null,
        $clearBtn: null,
        $noResults: null,

        /**
         * Inicializa o módulo de configurações.
         */
        init: function() {
            this.$wrapper = $( '.dps-settings-wrapper' );

            if ( ! this.$wrapper.length ) {
                return;
            }

            this.$nav = this.$wrapper.find( '#dps-settings-nav' );
            this.$tabs = this.$nav.find( '.dps-tab-link' );
            this.$sections = this.$wrapper.find( '.dps-settings-section' );
            this.$search = this.$wrapper.find( '#dps-settings-search' );
            this.$searchWrapper = this.$wrapper.find( '.dps-settings-search-wrapper' );
            this.$clearBtn = this.$wrapper.find( '.dps-settings-search-clear' );
            this.$noResults = this.$wrapper.find( '.dps-settings-no-results' );

            this.bindEvents();
            this.initDirtyTracking();
        },

        /**
         * Vincula event listeners.
         */
        bindEvents: function() {
            var self = this;

            // Navegação entre abas (client-side)
            this.$tabs.on( 'click', function( e ) {
                // Se a busca estiver ativa, limpar primeiro
                if ( self.$search.length && self.$search.val().trim() ) {
                    self.clearSearch();
                }

                e.preventDefault();
                var tab = $( this ).data( 'tab' );
                self.switchTab( tab );

                // Atualiza URL sem recarregar
                if ( window.history && window.history.replaceState ) {
                    var url = new URL( window.location.href );
                    url.searchParams.set( 'dps_settings_tab', tab );
                    window.history.replaceState( {}, '', url.toString() );
                }
            });

            // Mobile toggle
            this.$wrapper.find( '.dps-nav-mobile-toggle' ).on( 'click', function() {
                var $navContainer = $( this ).closest( '.dps-settings-nav' );
                $navContainer.toggleClass( 'is-open' );
                $( this ).attr( 'aria-expanded', $navContainer.hasClass( 'is-open' ) );
            });

            // Busca de configurações
            if ( this.$search.length ) {
                this.$search.on( 'input', function() {
                    self.handleSearch( $( this ).val() );
                });

                this.$clearBtn.on( 'click', function() {
                    self.clearSearch();
                });

                // Atalho de teclado: Escape limpa busca
                this.$search.on( 'keydown', function( e ) {
                    if ( e.key === 'Escape' ) {
                        self.clearSearch();
                        $( this ).blur();
                    }
                });
            }
        },

        /**
         * Alterna para uma aba específica.
         *
         * @param {string} tabSlug Identificador da aba.
         */
        switchTab: function( tabSlug ) {
            // Atualiza tabs ativas
            this.$tabs.removeClass( 'active' ).attr( 'aria-selected', 'false' );
            this.$tabs.filter( '[data-tab="' + tabSlug + '"]' )
                .addClass( 'active' )
                .attr( 'aria-selected', 'true' );

            // Atualiza seções visíveis
            this.$sections.removeClass( 'active' ).css( 'display', 'none' );
            $( '#dps-settings-' + tabSlug )
                .addClass( 'active' )
                .css( 'display', 'block' );

            // Fecha menu mobile
            this.$wrapper.find( '.dps-settings-nav' ).removeClass( 'is-open' );

            // Scroll to top da seção
            var wrapperTop = this.$wrapper.offset().top;
            if ( $( window ).scrollTop() > wrapperTop ) {
                $( 'html, body' ).animate({ scrollTop: wrapperTop - 20 }, 200 );
            }
        },

        /**
         * Processa busca de configurações.
         *
         * @param {string} query Texto de busca.
         */
        handleSearch: function( query ) {
            query = query.trim().toLowerCase();

            // Toggle do botão limpar
            if ( query.length > 0 ) {
                this.$searchWrapper.addClass( 'has-value' );
            } else {
                this.$searchWrapper.removeClass( 'has-value' );
            }

            if ( query.length < 2 ) {
                this.resetSearch();
                return;
            }

            var matchingTabs = {};
            var self = this;

            // Remove destaques anteriores
            this.$sections.find( '.search-highlight' ).removeClass( 'search-highlight' );
            this.$tabs.removeClass( 'search-match' );

            // Mostra todas as seções temporariamente para busca
            this.$sections.each( function() {
                var $section = $( this );
                var sectionId = $section.attr( 'id' );
                var tabSlug = sectionId ? sectionId.replace( 'dps-settings-', '' ) : '';
                var hasMatch = false;

                // Busca em labels, descriptions, legends, titles
                $section.find( 'label, .description, legend, .dps-surface__title, .dps-surface__description, h3, option' ).each( function() {
                    var text = $( this ).text().toLowerCase();
                    if ( text.indexOf( query ) !== -1 ) {
                        hasMatch = true;

                        // Destaca o fieldset pai
                        var $fieldset = $( this ).closest( '.dps-fieldset' );
                        if ( $fieldset.length ) {
                            $fieldset.addClass( 'search-highlight' );
                        }

                        // Destaca o form-row pai
                        var $formRow = $( this ).closest( '.dps-form-row' );
                        if ( $formRow.length ) {
                            $formRow.addClass( 'search-highlight' );
                        }
                    }
                });

                if ( hasMatch ) {
                    matchingTabs[ tabSlug ] = true;
                }
            });

            var tabSlugs = Object.keys( matchingTabs );

            // Destaca as tabs com resultados
            this.$tabs.each( function() {
                var $tab = $( this );
                if ( matchingTabs[ $tab.data( 'tab' ) ] ) {
                    $tab.addClass( 'search-match' );
                }
            });

            // Se há resultados, ativa o primeiro tab com match
            if ( tabSlugs.length > 0 ) {
                this.$noResults.removeClass( 'visible' );
                var activeTab = this.$tabs.filter( '.active' ).data( 'tab' );
                if ( ! matchingTabs[ activeTab ] ) {
                    self.switchTab( tabSlugs[0] );
                }
            } else {
                this.$noResults.addClass( 'visible' );
            }
        },

        /**
         * Reseta a busca para o estado inicial.
         */
        resetSearch: function() {
            this.$sections.find( '.search-highlight' ).removeClass( 'search-highlight' );
            this.$tabs.removeClass( 'search-match' );
            this.$noResults.removeClass( 'visible' );
        },

        /**
         * Limpa o campo de busca e reseta destaques.
         */
        clearSearch: function() {
            this.$search.val( '' );
            this.$searchWrapper.removeClass( 'has-value' );
            this.resetSearch();
        },

        /**
         * Inicializa rastreamento de alterações não salvas.
         */
        initDirtyTracking: function() {
            var self = this;

            this.$wrapper.find( '.dps-settings-form' ).each( function() {
                var $form = $( this );
                var initialState = $form.serialize();

                // Injeta indicador de alterações não salvas no botão de ações
                var $formActions = $form.find( '.dps-form-actions' );
                if ( $formActions.length && ! $formActions.find( '.dps-unsaved-indicator' ).length ) {
                    $formActions.prepend(
                        '<span class="dps-unsaved-indicator">' +
                        '<span class="dashicons dashicons-warning"></span> ' +
                        dpsSettingsL10n.unsavedChanges +
                        '</span>'
                    );
                }

                $form.on( 'input change', 'input, select, textarea', function() {
                    var currentState = $form.serialize();
                    if ( currentState !== initialState ) {
                        $form.addClass( 'is-dirty' );
                    } else {
                        $form.removeClass( 'is-dirty' );
                    }
                });

                // Reset dirty state após submit
                $form.on( 'submit', function() {
                    $form.removeClass( 'is-dirty' );
                });
            });

            // Aviso ao sair com alterações não salvas
            $( window ).on( 'beforeunload', function() {
                if ( self.$wrapper.find( '.dps-settings-form.is-dirty' ).length > 0 ) {
                    return true;
                }
            });
        }
    };

    // Inicializa quando o DOM estiver pronto
    $( function() {
        DPSSettings.init();
    });

})( jQuery );
