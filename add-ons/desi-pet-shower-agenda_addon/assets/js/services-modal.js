/**
 * Modal de Serviços - DPS Agenda
 * 
 * Componente de modal customizado para exibir serviços de um agendamento,
 * substituindo o alert() nativo por uma interface moderna e acessível.
 */

(function($) {
    'use strict';

    /**
     * Objeto público DPSServicesModal
     */
    window.DPSServicesModal = {
        /**
         * Exibe o modal com a lista de serviços
         * @param {Array} services - Array de objetos com {name, price}
         */
        show: function(services) {
            if (!services || services.length === 0) {
                this.show([{
                    name: 'Nenhum serviço encontrado',
                    price: '0.00'
                }]);
                return;
            }

            // Cria o overlay do modal
            var overlay = $('<div>', {
                'class': 'dps-modal-overlay',
                'role': 'dialog',
                'aria-modal': 'true',
                'aria-labelledby': 'dps-modal-title'
            });

            // Cria o container do modal
            var modalContent = $('<div>', {
                'class': 'dps-modal-content'
            });

            // Header do modal
            var header = $('<div>', {
                'class': 'dps-modal-header'
            });

            var title = $('<h3>', {
                'class': 'dps-modal-title',
                'id': 'dps-modal-title',
                'text': 'Serviços do Agendamento'
            });

            var closeButton = $('<button>', {
                'class': 'dps-modal-close',
                'type': 'button',
                'aria-label': 'Fechar modal',
                'html': '&times;'
            });

            header.append(title, closeButton);

            // Body do modal com lista de serviços
            var body = $('<div>', {
                'class': 'dps-modal-body'
            });

            var servicesList = $('<ul>', {
                'class': 'dps-services-list'
            });

            var total = 0;

            // Adiciona cada serviço à lista
            services.forEach(function(service) {
                var price = parseFloat(service.price) || 0;
                total += price;

                var item = $('<li>', {
                    'class': 'dps-service-item'
                });

                var serviceName = $('<span>', {
                    'class': 'dps-service-name',
                    'text': service.name
                });

                var servicePrice = $('<span>', {
                    'class': 'dps-service-price',
                    'text': 'R$ ' + price.toFixed(2).replace('.', ',')
                });

                item.append(serviceName, servicePrice);
                servicesList.append(item);
            });

            body.append(servicesList);

            // Total (só exibe se houver mais de um serviço)
            if (services.length > 1) {
                var totalRow = $('<div>', {
                    'class': 'dps-services-total'
                });

                var totalLabel = $('<span>', {
                    'text': 'Total'
                });

                var totalValue = $('<span>', {
                    'text': 'R$ ' + total.toFixed(2).replace('.', ',')
                });

                totalRow.append(totalLabel, totalValue);
                body.append(totalRow);
            }

            // Footer do modal
            var footer = $('<div>', {
                'class': 'dps-modal-footer'
            });

            var closeButtonFooter = $('<button>', {
                'class': 'button dps-btn dps-btn--primary',
                'type': 'button',
                'text': 'Fechar'
            });

            footer.append(closeButtonFooter);

            // Monta o modal
            modalContent.append(header, body, footer);
            overlay.append(modalContent);

            // Adiciona ao body
            $('body').append(overlay);

            // Foco no modal para acessibilidade
            overlay.focus();

            // Event handlers para fechar o modal
            var closeModal = function(e) {
                if (e) {
                    e.preventDefault();
                }
                overlay.fadeOut(200, function() {
                    overlay.remove();
                });
            };

            // Fechar ao clicar no X ou no botão Fechar
            closeButton.on('click', closeModal);
            closeButtonFooter.on('click', closeModal);

            // Fechar ao clicar no overlay (fora do modal)
            overlay.on('click', function(e) {
                if (e.target === overlay[0]) {
                    closeModal(e);
                }
            });

            // Fechar ao pressionar ESC
            $(document).on('keydown.dps-modal', function(e) {
                if (e.key === 'Escape' || e.keyCode === 27) {
                    closeModal(e);
                    $(document).off('keydown.dps-modal');
                }
            });

            // Animação de entrada
            overlay.hide().fadeIn(200);
        }
    };

})(jQuery);
