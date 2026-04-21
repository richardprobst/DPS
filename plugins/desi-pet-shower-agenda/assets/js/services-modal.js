/**
 * Compatibilidade do modal de serviços.
 *
 * Mantém a API histórica `window.DPSServicesModal.show()` apontando para a
 * implementação ativa centralizada em `agenda-addon.js`, evitando duas UIs
 * divergentes para a mesma função.
 */
(function($) {
  'use strict';

  function normalizeServices(input) {
    return Array.isArray(input) ? input : [];
  }

  function getTotalDuration(services) {
    var total = 0;

    services.forEach(function(service) {
      var duration = parseInt(service && service.duration, 10) || 0;
      total += duration;
    });

    return total;
  }

  window.DPSServicesModal = {
    show: function(services, options) {
      var payload = options && typeof options === 'object' ? options : {};
      var normalizedServices = normalizeServices(services);

      if (window.DPSAgendaServicesModal && typeof window.DPSAgendaServicesModal.open === 'function') {
        window.DPSAgendaServicesModal.open(
          payload.trigger ? $(payload.trigger) : null,
          normalizedServices,
          payload.notes || '',
          payload.pet || {},
          payload.totalDuration || getTotalDuration(normalizedServices)
        );
        return;
      }

      if (window.console && typeof window.console.warn === 'function') {
        window.console.warn('DPSAgendaServicesModal indisponível.');
      }
    }
  };
})(jQuery);
