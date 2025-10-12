(function($){
  $(document).ready(function(){
    // Evento de alteração de status
    $(document).on('change', '.dps-status-select', function(){
      var select = $(this);
      var apptId = select.data('appt-id');
      var status = select.val();
      // Envia solicitação AJAX
      $.post(DPS_AG_Addon.ajax, {
        action: 'dps_update_status',
        id: apptId,
        status: status,
        nonce: DPS_AG_Addon.nonce_status
      }, function(resp){
        if ( resp && resp.success ) {
          // Atualiza a classe CSS da linha correspondente para refletir o novo status
          updateRowStatus(apptId, status);
          console.log('Status atualizado para ' + status);
          // Recarrega a página após atualização para refletir todas as mudanças (como alterações em valores ou ações)
          location.reload();
        } else {
          alert(resp.data ? resp.data.message : 'Erro ao atualizar status.');
        }
      });
    });
    // Evento para visualizar serviços de um agendamento
    $(document).on('click', '.dps-services-link', function(e){
      e.preventDefault();
      var apptId = $(this).data('appt-id');
      $.post(DPS_AG_Addon.ajax, {
        action: 'dps_get_services_details',
        appt_id: apptId,
        nonce: DPS_AG_Addon.nonce_services
      }, function(resp){
        if ( resp && resp.success ) {
          var services = resp.data.services || [];
          if ( services.length > 0 ) {
            var message = '';
            for ( var i=0; i < services.length; i++ ) {
              var srv = services[i];
              message += srv.name + ' - R$ ' + parseFloat(srv.price).toFixed(2);
              if ( i < services.length - 1 ) message += "\n";
            }
            alert(message);
          } else {
            alert('Nenhum serviço encontrado.');
          }
        } else {
          alert(resp.data ? resp.data.message : 'Erro ao buscar serviços.');
        }
      });
    });
  });

  /**
   * Atualiza a classe de uma linha da tabela da agenda com o novo status.
   * Cada linha utiliza data-appt-id para identificá-la e classes CSS no formato
   * status-pendente, status-finalizado, status-finalizado_pago ou status-cancelado.
   * @param {string|number} apptId ID do agendamento
   * @param {string} status Novo status definido
   */
  function updateRowStatus(apptId, status){
    var row = $('tr[data-appt-id="' + apptId + '"]');
    if (row.length){
      row.removeClass('status-pendente status-finalizado status-finalizado_pago status-cancelado')
         .addClass('status-' + status);
    }
  }
})(jQuery);