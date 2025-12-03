(function($){
  $(document).ready(function(){
    var reloadDelay = parseInt(DPS_AG_Addon.reloadDelay, 10) || 600;

    // Garante que o status atual esteja salvo em data attributes
    $('.dps-status-select').each(function(){
      var select = $(this);
      if ( ! select.data('current-status') ) {
        select.data('current-status', select.val());
      }
    });

    // Evento de alteração de status
    $(document).on('change', '.dps-status-select', function(){
      var select   = $(this);
      var apptId   = select.data('appt-id');
      var status   = select.val();
      var apptVersion = parseInt(select.data('appt-version'), 10) || 0;
      var previous = select.data('current-status');
      var feedback = select.siblings('.dps-status-feedback');

      if ( ! feedback.length ) {
        feedback = $('<span class="dps-status-feedback" aria-live="polite"></span>');
        select.after(feedback);
      }

      var updatingMessage = getMessage('updating', 'Atualizando status...');
      feedback.removeClass('dps-status-feedback--error').text(updatingMessage);

      select.addClass('is-loading').prop('disabled', true);

      var request = $.post(DPS_AG_Addon.ajax, {
        action: 'dps_update_status',
        id: apptId,
        status: status,
        version: apptVersion,
        nonce: DPS_AG_Addon.nonce_status
      });

      request.done(function(resp){
        if ( resp && resp.success ) {
          updateRowStatus(apptId, status);
          select.data('current-status', status);
          if ( resp.data && resp.data.version ) {
            select.data('appt-version', resp.data.version);
          }
          var successMessage = getMessage('updated', 'Status atualizado!');
          feedback.text(successMessage);
          setTimeout(function(){
            location.reload();
          }, reloadDelay);
        } else {
          handleError(resp);
        }
      }).fail(function(){
        handleError();
      }).always(function(){
        select.removeClass('is-loading').prop('disabled', false);
      });

      function handleError(response){
        var fallback = 'Erro ao atualizar status.';
        if ( response && response.data && response.data.error_code === 'version_conflict' ) {
          var conflictMessage = getMessage('versionConflict', 'Esse agendamento foi atualizado por outro usuário. Atualize a página para ver as alterações.');
          feedback.addClass('dps-status-feedback--error').text(conflictMessage);
          select.val(previous);
          return;
        }
        var message  = (response && response.data && response.data.message) ? response.data.message : getMessage('error', fallback);
        feedback.addClass('dps-status-feedback--error').text(message);
        if ( previous ) {
          select.val(previous);
        }
      }
    });
    // Evento para visualizar serviços de um agendamento
    // Usa modal customizado em vez de alert() para melhor UX
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
            // Exibe modal customizado ao invés de alert()
            if ( typeof window.DPSServicesModal !== 'undefined' ) {
              window.DPSServicesModal.show(services);
            } else {
              // Fallback para alert() caso o modal não esteja carregado
              var message = '';
              for ( var i=0; i < services.length; i++ ) {
                var srv = services[i];
                message += srv.name + ' - R$ ' + parseFloat(srv.price).toFixed(2);
                if ( i < services.length - 1 ) message += "\n";
              }
              alert(message);
            }
          } else {
            // Lista vazia - exibe modal com mensagem apropriada se disponível
            if ( typeof window.DPSServicesModal !== 'undefined' ) {
              window.DPSServicesModal.show([]);
            } else {
              alert('Nenhum serviço encontrado para este agendamento.');
            }
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

  function getMessage(key, fallback){
    if ( typeof DPS_AG_Addon !== 'undefined' && DPS_AG_Addon.messages && DPS_AG_Addon.messages[key] ) {
      return DPS_AG_Addon.messages[key];
    }
    return fallback;
  }

  // FASE 2: Exportação CSV
  $(document).on('click', '.dps-export-csv-btn', function(e){
    e.preventDefault();
    var btn = $(this);
    var date = btn.data('date');
    var view = btn.data('view');
    
    btn.prop('disabled', true).text('⏳ Exportando...');
    
    $.post(DPS_AG_Addon.ajax, {
      action: 'dps_agenda_export_csv',
      nonce: DPS_AG_Addon.nonce_export,
      date: date,
      view: view
    }, function(resp){
      if ( resp && resp.success ) {
        // Decodificar base64 e criar download
        var content = atob(resp.data.content);
        var blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = resp.data.filename;
        link.click();
        URL.revokeObjectURL(link.href);
        btn.text('✅ Exportado!');
        setTimeout(function(){
          btn.prop('disabled', false).html('📥 ' + getMessage('export', 'Exportar'));
        }, 2000);
      } else {
        alert(resp.data ? resp.data.message : 'Erro ao exportar.');
        btn.prop('disabled', false).html('📥 ' + getMessage('export', 'Exportar'));
      }
    }).fail(function(){
      alert('Erro ao exportar agenda.');
      btn.prop('disabled', false).html('📥 ' + getMessage('export', 'Exportar'));
    });
  });
})(jQuery);