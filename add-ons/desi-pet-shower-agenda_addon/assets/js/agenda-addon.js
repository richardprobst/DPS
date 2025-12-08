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
    
    // UX-1: Evento para botões de ação rápida de status
    $(document).on('click', '.dps-quick-action-btn', function(e){
      e.preventDefault();
      var btn = $(this);
      var apptId = btn.data('appt-id');
      var actionType = btn.data('action');
      var row = $('tr[data-appt-id="' + apptId + '"]');
      
      if ( ! apptId || ! actionType ) {
        return;
      }
      
      // Desabilita todos os botões de ação da linha
      row.find('.dps-quick-action-btn').prop('disabled', true).addClass('is-loading');
      
      var request = $.post(DPS_AG_Addon.ajax, {
        action: 'dps_agenda_quick_action',
        appt_id: apptId,
        action_type: actionType,
        nonce: DPS_AG_Addon.nonce_quick_action
      });
      
      request.done(function(resp){
        if ( resp && resp.success ) {
          // UX-2: Substitui a linha inteira com o HTML atualizado
          if ( resp.data && resp.data.row_html ) {
            var newRow = $(resp.data.row_html);
            row.replaceWith(newRow);
            
            // Anima a nova linha para feedback visual
            newRow.addClass('dps-row-updated');
            setTimeout(function(){
              newRow.removeClass('dps-row-updated');
            }, 1500);
          } else {
            // Fallback: atualiza apenas a classe de status
            if ( resp.data && resp.data.new_status ) {
              updateRowStatus(apptId, resp.data.new_status);
            }
            // Remove estado de loading
            row.find('.dps-quick-action-btn').prop('disabled', false).removeClass('is-loading');
          }
        } else {
          handleQuickActionError(resp, row);
        }
      }).fail(function(){
        handleQuickActionError(null, row);
      });
      
      function handleQuickActionError(response, row){
        var message = (response && response.data && response.data.message) 
          ? response.data.message 
          : 'Erro ao executar ação. Tente novamente.';
        
        alert(message);
        
        // Remove estado de loading
        row.find('.dps-quick-action-btn').prop('disabled', false).removeClass('is-loading');
        
        // Fallback: recarrega a página em caso de erro para garantir consistência
        setTimeout(function(){
          location.reload();
        }, 1000);
      }
    });
    
    // CONF-2: Evento para botões de confirmação
    $(document).on('click', '.dps-confirmation-btn', function(e){
      e.preventDefault();
      var btn = $(this);
      var apptId = btn.data('appt-id');
      var confirmationStatus = btn.data('action');
      var row = $('tr[data-appt-id="' + apptId + '"]');
      
      if ( ! apptId || ! confirmationStatus ) {
        return;
      }
      
      // Desabilita todos os botões de confirmação da linha
      row.find('.dps-confirmation-btn').prop('disabled', true).addClass('is-loading');
      
      var request = $.post(DPS_AG_Addon.ajax, {
        action: 'dps_agenda_update_confirmation',
        appt_id: apptId,
        confirmation_status: confirmationStatus,
        nonce: DPS_AG_Addon.nonce_confirmation
      });
      
      request.done(function(resp){
        if ( resp && resp.success ) {
          // Substitui a linha inteira com o HTML atualizado
          if ( resp.data && resp.data.row_html ) {
            var newRow = $(resp.data.row_html);
            row.replaceWith(newRow);
            
            // Anima a nova linha para feedback visual
            newRow.addClass('dps-row-updated');
            setTimeout(function(){
              newRow.removeClass('dps-row-updated');
            }, 1500);
          } else {
            // Remove estado de loading
            row.find('.dps-confirmation-btn').prop('disabled', false).removeClass('is-loading');
          }
        } else {
          handleConfirmationError(resp, row);
        }
      }).fail(function(){
        handleConfirmationError(null, row);
      });
      
      function handleConfirmationError(response, row){
        var message = (response && response.data && response.data.message) 
          ? response.data.message 
          : 'Erro ao atualizar confirmação. Tente novamente.';
        
        alert(message);
        
        // Remove estado de loading
        row.find('.dps-confirmation-btn').prop('disabled', false).removeClass('is-loading');
      }
    });
    
    // UX-5: Toggle de filtros avançados
    $(document).on('click', '.dps-toggle-advanced-filters', function(e){
      e.preventDefault();
      var btn = $(this);
      var advancedFilters = $('.dps-filters-advanced');
      var isExpanded = btn.attr('data-expanded') === 'true';
      
      if ( isExpanded ) {
        // Colapsar
        advancedFilters.addClass('dps-filters-advanced--hidden');
        btn.attr('data-expanded', 'false');
      } else {
        // Expandir
        advancedFilters.removeClass('dps-filters-advanced--hidden');
        btn.attr('data-expanded', 'true');
      }
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

  // =========================================================================
  // FASE 5: Ações em Lote
  // =========================================================================
  
  var selectedAppointments = [];
  
  // Selecionar/desmarcar checkbox individual
  $(document).on('change', '.dps-select-checkbox', function(){
    var checkbox = $(this);
    var apptId = checkbox.data('appt-id');
    
    if ( checkbox.is(':checked') ) {
      if ( selectedAppointments.indexOf(apptId) === -1 ) {
        selectedAppointments.push(apptId);
      }
    } else {
      selectedAppointments = selectedAppointments.filter(function(id){ return id !== apptId; });
    }
    
    updateBulkActionsBar();
  });
  
  // Selecionar/desmarcar todos
  $(document).on('change', '.dps-select-all', function(){
    var isChecked = $(this).is(':checked');
    
    $('.dps-select-checkbox').each(function(){
      $(this).prop('checked', isChecked);
      var apptId = $(this).data('appt-id');
      
      if ( isChecked ) {
        if ( selectedAppointments.indexOf(apptId) === -1 ) {
          selectedAppointments.push(apptId);
        }
      } else {
        selectedAppointments = selectedAppointments.filter(function(id){ return id !== apptId; });
      }
    });
    
    updateBulkActionsBar();
  });
  
  // Atualiza barra de ações em lote
  function updateBulkActionsBar(){
    var bar = $('.dps-bulk-actions');
    var count = selectedAppointments.length;
    
    if ( count > 0 ) {
      bar.addClass('is-visible');
      bar.find('.dps-bulk-count').text(count + ' ' + (count === 1 ? getMessage('selected_singular', 'selecionado') : getMessage('selected_plural', 'selecionados')));
    } else {
      bar.removeClass('is-visible');
    }
  }
  
  // Fechar barra de ações em lote
  $(document).on('click', '.dps-bulk-close', function(){
    selectedAppointments = [];
    $('.dps-select-checkbox, .dps-select-all').prop('checked', false);
    updateBulkActionsBar();
  });
  
  // Ação em lote: atualizar status
  $(document).on('click', '.dps-bulk-btn[data-action]', function(){
    var btn = $(this);
    var action = btn.data('action');
    var status = btn.data('status');
    
    if ( selectedAppointments.length === 0 ) {
      return;
    }
    
    if ( action === 'update_status' && status ) {
      var confirmMsg = getMessage('bulk_confirm', 'Deseja alterar o status de ' + selectedAppointments.length + ' agendamento(s)?');
      if ( ! confirm(confirmMsg) ) {
        return;
      }
      
      btn.prop('disabled', true);
      
      $.post(DPS_AG_Addon.ajax, {
        action: 'dps_bulk_update_status',
        nonce: DPS_AG_Addon.nonce_bulk,
        ids: selectedAppointments,
        status: status
      }, function(resp){
        if ( resp && resp.success ) {
          alert(resp.data.message);
          location.reload();
        } else {
          alert(resp.data ? resp.data.message : getMessage('error', 'Erro ao processar.'));
          btn.prop('disabled', false);
        }
      }).fail(function(){
        alert(getMessage('error', 'Erro ao processar.'));
        btn.prop('disabled', false);
      });
    }
  });

  // =========================================================================
  // FASE 5: Reagendamento Rápido
  // =========================================================================
  
  // Abrir modal de reagendamento
  $(document).on('click', '.dps-quick-reschedule', function(e){
    e.preventDefault();
    var btn = $(this);
    var apptId = btn.data('appt-id');
    var currentDate = btn.data('date');
    var currentTime = btn.data('time');
    
    var modal = $('<div class="dps-reschedule-modal">' +
      '<div class="dps-reschedule-content">' +
        '<div class="dps-reschedule-header">' +
          '<h3 class="dps-reschedule-title">📅 ' + getMessage('reschedule_title', 'Reagendar') + '</h3>' +
          '<button type="button" class="dps-reschedule-close">&times;</button>' +
        '</div>' +
        '<div class="dps-reschedule-body">' +
          '<div class="dps-reschedule-field">' +
            '<label>' + getMessage('new_date', 'Nova data') + '</label>' +
            '<input type="date" id="dps-reschedule-date" value="' + currentDate + '" required>' +
          '</div>' +
          '<div class="dps-reschedule-field">' +
            '<label>' + getMessage('new_time', 'Novo horário') + '</label>' +
            '<input type="time" id="dps-reschedule-time" value="' + currentTime + '" required>' +
          '</div>' +
        '</div>' +
        '<div class="dps-reschedule-footer">' +
          '<button type="button" class="dps-reschedule-btn dps-reschedule-btn--cancel">' + getMessage('cancel', 'Cancelar') + '</button>' +
          '<button type="button" class="dps-reschedule-btn dps-reschedule-btn--save" data-appt-id="' + apptId + '">' + getMessage('save', 'Salvar') + '</button>' +
        '</div>' +
      '</div>' +
    '</div>');
    
    $('body').append(modal);
    modal.find('#dps-reschedule-date').focus();
  });
  
  // Fechar modal de reagendamento
  $(document).on('click', '.dps-reschedule-close, .dps-reschedule-btn--cancel', function(){
    $('.dps-reschedule-modal').remove();
  });
  
  // Fechar ao clicar fora do modal
  $(document).on('click', '.dps-reschedule-modal', function(e){
    if ( $(e.target).hasClass('dps-reschedule-modal') ) {
      $(this).remove();
    }
  });
  
  // Salvar reagendamento
  $(document).on('click', '.dps-reschedule-btn--save', function(){
    var btn = $(this);
    var apptId = btn.data('appt-id');
    var newDate = $('#dps-reschedule-date').val();
    var newTime = $('#dps-reschedule-time').val();
    
    if ( ! newDate || ! newTime ) {
      alert(getMessage('fill_all_fields', 'Preencha todos os campos.'));
      return;
    }
    
    btn.prop('disabled', true).text(getMessage('saving', 'Salvando...'));
    
    $.post(DPS_AG_Addon.ajax, {
      action: 'dps_quick_reschedule',
      nonce: DPS_AG_Addon.nonce_reschedule,
      id: apptId,
      date: newDate,
      time: newTime
    }, function(resp){
      if ( resp && resp.success ) {
        alert(resp.data.message);
        location.reload();
      } else {
        alert(resp.data ? resp.data.message : getMessage('error', 'Erro ao reagendar.'));
        btn.prop('disabled', false).text(getMessage('save', 'Salvar'));
      }
    }).fail(function(){
      alert(getMessage('error', 'Erro ao reagendar.'));
      btn.prop('disabled', false).text(getMessage('save', 'Salvar'));
    });
  });
  
  // Tecla ESC fecha modal
  $(document).on('keydown', function(e){
    if ( e.key === 'Escape' && $('.dps-reschedule-modal').length ) {
      $('.dps-reschedule-modal').remove();
    }
  });

  // =========================================================================
  // FASE 5: Histórico de Alterações
  // =========================================================================
  
  $(document).on('click', '.dps-history-indicator', function(e){
    e.preventDefault();
    var btn = $(this);
    var apptId = btn.data('appt-id');
    
    $.post(DPS_AG_Addon.ajax, {
      action: 'dps_get_appointment_history',
      nonce: DPS_AG_Addon.nonce_history,
      id: apptId
    }, function(resp){
      if ( resp && resp.success ) {
        var history = resp.data.history || [];
        if ( history.length === 0 ) {
          alert(getMessage('no_history', 'Sem histórico de alterações.'));
          return;
        }
        
        var content = getMessage('history_title', 'Histórico de Alterações') + ':\n\n';
        history.forEach(function(entry){
          var actionLabel = getActionLabel(entry.action);
          content += '• ' + entry.date + ' - ' + actionLabel + ' (' + entry.user + ')\n';
          if ( entry.details ) {
            if ( entry.details.old_status && entry.details.new_status ) {
              content += '  De: ' + entry.details.old_status + ' → Para: ' + entry.details.new_status + '\n';
            }
            if ( entry.details.old_date && entry.details.new_date ) {
              content += '  De: ' + entry.details.old_date + ' ' + entry.details.old_time + '\n';
              content += '  Para: ' + entry.details.new_date + ' ' + entry.details.new_time + '\n';
            }
          }
        });
        
        alert(content);
      } else {
        alert(resp.data ? resp.data.message : getMessage('error', 'Erro ao buscar histórico.'));
      }
    });
  });
  
  function getActionLabel(action){
    var labels = {
      'created': getMessage('action_created', 'Criado'),
      'status_change': getMessage('action_status_change', 'Status alterado'),
      'rescheduled': getMessage('action_rescheduled', 'Reagendado')
    };
    return labels[action] || action;
  }

  // FASE 3: Ações rápidas de TaxiDog
  $(document).on('click', '.dps-taxidog-action-btn', function(e){
    e.preventDefault();
    var btn = $(this);
    var apptId = btn.data('appt-id');
    var taxidogStatus = btn.data('action');
    var row = btn.closest('tr');

    // Desabilita todos os botões da linha
    row.find('.dps-taxidog-action-btn').prop('disabled', true).css('opacity', 0.5);

    $.post(DPS_AG_Addon.ajax, {
      action: 'dps_agenda_update_taxidog',
      appt_id: apptId,
      taxidog_status: taxidogStatus,
      nonce: DPS_AG_Addon.nonce_taxidog
    }, function(resp){
      if ( resp && resp.success ) {
        // UX-2: Substitui a linha inteira com HTML atualizado
        if ( resp.data.row_html ) {
          var newRow = $(resp.data.row_html);
          row.replaceWith(newRow);
          // Aplica animação de feedback visual
          newRow.css('background-color', '#d1fae5');
          setTimeout(function(){
            newRow.css('background-color', '');
          }, 1000);
        } else {
          // Fallback: reload
          location.reload();
        }
      } else {
        var message = (resp && resp.data && resp.data.message) ? resp.data.message : 'Erro ao atualizar TaxiDog.';
        alert(message);
        // Reabilita botões em caso de erro
        row.find('.dps-taxidog-action-btn').prop('disabled', false).css('opacity', 1);
      }
    }).fail(function(){
      alert('Erro de comunicação ao atualizar TaxiDog.');
      // Reabilita botões em caso de erro
      row.find('.dps-taxidog-action-btn').prop('disabled', false).css('opacity', 1);
    });
  });

  // FASE 3: Tooltip de detalhes de pagamento
  $(document).on('mouseenter', '.dps-payment-badge', function(){
    var badge = $(this);
    var tooltip = badge.siblings('.dps-payment-tooltip');
    if ( tooltip.length && tooltip.html().trim() !== '' ) {
      tooltip.css({
        display: 'block',
        position: 'absolute',
        background: '#fff',
        border: '1px solid #e2e8f0',
        padding: '8px 12px',
        borderRadius: '4px',
        boxShadow: '0 2px 8px rgba(0,0,0,0.1)',
        zIndex: 1000,
        minWidth: '200px',
        marginTop: '5px'
      });
    }
  });

  $(document).on('mouseleave', '.dps-payment-badge', function(){
    var badge = $(this);
    var tooltip = badge.siblings('.dps-payment-tooltip');
    tooltip.css('display', 'none');
  });

  // FASE 5: Reenviar link de pagamento
  $(document).on('click', '.dps-resend-payment-btn', function(e){
    e.preventDefault();
    var btn = $(this);
    var apptId = btn.data('appt-id');
    var row = btn.closest('tr');

    if (!confirm('Deseja realmente reenviar o link de pagamento?')) {
      return;
    }

    // Desabilita botão
    btn.prop('disabled', true).text('Reenviando...');

    $.post(DPS_AG_Addon.ajax, {
      action: 'dps_agenda_resend_payment',
      appt_id: apptId,
      nonce: DPS_AG_Addon.nonce_resend_payment
    }, function(resp){
      if (resp && resp.success) {
        // Substitui a linha com HTML atualizado
        if (resp.data.row_html) {
          var newRow = $(resp.data.row_html);
          row.replaceWith(newRow);
          // Feedback visual
          newRow.css('background-color', '#d1fae5');
          setTimeout(function(){
            newRow.css('background-color', '');
          }, 1000);
        } else {
          location.reload();
        }
      } else {
        var message = (resp && resp.data && resp.data.message) ? resp.data.message : 'Erro ao reenviar link.';
        alert(message);
        btn.prop('disabled', false).text('🔄 Reenviar');
      }
    }).fail(function(){
      alert('Erro de comunicação ao reenviar link.');
      btn.prop('disabled', false).text('🔄 Reenviar');
    });
  });

})(jQuery);