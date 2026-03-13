(function($){
  /**
   * Escapa caracteres especiais HTML para prevenir XSS.
   * @param {string} str String a ser escapada.
   * @return {string} String escapada.
   */
  function escapeHtml(str) {
    if (str === null || str === undefined) {
      return '';
    }
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  /**
   * Exibe notificaÃ§Ã£o toast estilizada para feedback do usuÃ¡rio.
   * Substitui alert() nativo por uma notificaÃ§Ã£o moderna.
   * @param {string} message Mensagem a ser exibida.
   * @param {string} type Tipo da notificaÃ§Ã£o: 'error', 'success', 'warning', 'info'.
   * @param {number} duration DuraÃ§Ã£o em ms (padrÃ£o: 4000).
   */
  function showToast(message, type, duration) {
    type = type || 'error';
    duration = duration || 4000;

    // Remove toast existente
    $('.dps-toast').remove();

    var icons = {
      error: 'âŒ',
      success: 'âœ…',
      warning: 'âš ï¸',
      info: 'â„¹ï¸'
    };

    var icon = icons[type] || icons.info;

    var toast = $('<div class="dps-toast dps-toast--' + type + '">' +
      '<span class="dps-toast-icon">' + icon + '</span>' +
      '<span class="dps-toast-message">' + escapeHtml(message) + '</span>' +
      '<button type="button" class="dps-toast-close" aria-label="Fechar">&times;</button>' +
    '</div>');

    $('body').append(toast);

    // Anima entrada
    setTimeout(function() {
      toast.addClass('dps-toast--visible');
    }, 10);

    // Fechar ao clicar
    toast.find('.dps-toast-close').on('click', function() {
      toast.removeClass('dps-toast--visible');
      setTimeout(function() { toast.remove(); }, 300);
    });

    // Auto-remove apÃ³s duraÃ§Ã£o
    if (duration > 0) {
      setTimeout(function() {
        toast.removeClass('dps-toast--visible');
        setTimeout(function() { toast.remove(); }, 300);
      }, duration);
    }
  }

  // ExpÃµe globalmente para uso em outros mÃ³dulos
  window.DPSToast = { show: showToast };

  /**
   * ObtÃ©m o label e Ã­cone do porte do pet.
   * @param {string} size Porte do pet (pequeno, medio, grande, small, medium, large).
   * @return {object} Objeto com label e icon.
   */
  function getPetSizeInfo(size) {
    if (!size) return { label: '', icon: 'ðŸ•' };

    var sizeLower = size.toLowerCase();
    var sizeMap = {
      'pequeno': { label: 'Pequeno', icon: 'ðŸ•' },
      'small': { label: 'Pequeno', icon: 'ðŸ•' },
      'medio': { label: 'MÃ©dio', icon: 'ðŸ¦®' },
      'mÃ©dio': { label: 'MÃ©dio', icon: 'ðŸ¦®' },
      'medium': { label: 'MÃ©dio', icon: 'ðŸ¦®' },
      'grande': { label: 'Grande', icon: 'ðŸ•â€ðŸ¦º' },
      'large': { label: 'Grande', icon: 'ðŸ•â€ðŸ¦º' }
    };

    return sizeMap[sizeLower] || { label: '', icon: 'ðŸ•' };
  }

  /**
   * ObtÃ©m informaÃ§Ãµes visuais do serviÃ§o (Ã­cone, classe, label).
   * @param {object} service Objeto do serviÃ§o com name, type, category, is_taxidog.
   * @return {object} Objeto com icon, typeClass, typeLabel.
   */
  function getServiceVisualInfo(service) {
    // Mapas de Ã­cones por tipo e categoria
    var typeIcons = {
      'taxidog': { icon: 'ðŸš', typeClass: 'dps-service-type-taxidog', typeLabel: 'Transporte' },
      'extra': { icon: 'âœ¨', typeClass: 'dps-service-type-extra', typeLabel: 'Extra' },
      'package': { icon: 'ðŸ“¦', typeClass: 'dps-service-type-pacote', typeLabel: 'Pacote' }
    };

    var categoryIcons = {
      'banho': 'ðŸ›',
      'tosa': 'âœ‚ï¸',
      'unha': 'ðŸ’…',
      'ouvido': 'ðŸ‘‚',
      'dente': 'ðŸ¦·'
    };

    // Verifica TaxiDog primeiro
    if (service.is_taxidog) {
      return typeIcons.taxidog;
    }

    // Verifica tipo
    if (service.type && typeIcons[service.type]) {
      var info = Object.assign({}, typeIcons[service.type]);
      // Determina Ã­cone pela categoria ou nome
      info.icon = getCategoryIcon(service, categoryIcons);
      return info;
    }

    // ServiÃ§o padrÃ£o
    return {
      icon: getCategoryIcon(service, categoryIcons),
      typeClass: 'dps-service-type-padrao',
      typeLabel: 'ServiÃ§o'
    };
  }

  /**
   * ObtÃ©m o Ã­cone baseado na categoria ou nome do serviÃ§o.
   * @param {object} service Objeto do serviÃ§o.
   * @param {object} categoryIcons Mapa de Ã­cones por categoria.
   * @return {string} Ãcone emoji.
   */
  function getCategoryIcon(service, categoryIcons) {
    // Verifica categoria diretamente
    if (service.category && categoryIcons[service.category]) {
      return categoryIcons[service.category];
    }

    // Verifica pelo nome do serviÃ§o
    if (service.name) {
      var nameLower = service.name.toLowerCase();
      for (var cat in categoryIcons) {
        if (nameLower.indexOf(cat) !== -1) {
          return categoryIcons[cat];
        }
      }
    }

    // Ãcone padrÃ£o
    return 'âœ‚ï¸';
  }

  $(document).ready(function(){
    var reloadDelay = parseInt(DPS_AG_Addon.reloadDelay, 10) || 600;

    // Garante que o status atual esteja salvo em data attributes
    $('.dps-status-select').each(function(){
      var select = $(this);
      if ( ! select.data('current-status') ) {
        select.data('current-status', select.val());
      }
    });

    // Restaura a ultima aba apenas quando a URL nao define uma aba explicitamente.
    try {
      var agendaUrl = new URL(window.location.href);
      var requestedTab = agendaUrl.searchParams.get('agenda_tab');
      if (!requestedTab) {
        var lastTab = sessionStorage.getItem('dps_agenda_current_tab');
        if (lastTab) {
          var button = $('.dps-agenda-tab-button[data-tab="' + lastTab + '"]');
          if (button.length) {
            button.trigger('click');
          }
        }
      }
    } catch(e) {
      // Ignora erros de URL/sessionStorage (modo privado, etc)
    }
    // Evento de alteraÃ§Ã£o de status

    $(document).on('change', '.dps-status-select', function(){
      var select = $(this);
      var apptId = select.data('appt-id');
      var status = select.val();
      var apptVersion = parseInt(select.data('appt-version'), 10) || 0;
      var previous = select.data('current-status');
      var feedback = select.siblings('.dps-status-feedback');
      var row = select.closest('tr');

      if ( ! feedback.length ) {
        feedback = $('<span class="dps-status-feedback" aria-live="polite"></span>');
        select.after(feedback);
      }

      feedback.removeClass('dps-status-feedback--error').text(getMessage('updating', 'Atualizando status...'));
      select.addClass('is-loading').prop('disabled', true);

      $.post(DPS_AG_Addon.ajax, {
        action: 'dps_update_status',
        id: apptId,
        status: status,
        version: apptVersion,
        nonce: DPS_AG_Addon.nonce_status,
        agenda_tab: getCurrentAgendaTab()
      }).done(function(resp){
        if ( resp && resp.success ) {
          select.data('current-status', status);
          if ( resp.data && resp.data.version ) {
            select.data('appt-version', resp.data.version);
          }

          if ( resp.data && resp.data.row_html ) {
            replaceAgendaRow(row, resp.data.row_html);
            showToast(getMessage('updated', 'Status atualizado!'), 'success', 1800);
          } else {
            updateRowStatus(apptId, status);
            feedback.text(getMessage('updated', 'Status atualizado!'));
            setTimeout(function(){
              location.reload();
            }, reloadDelay);
          }
        } else {
          handleError(resp);
        }
      }).fail(function(xhr){
        handleError(xhr);
      }).always(function(){
        select.removeClass('is-loading').prop('disabled', false);
      });

      function handleError(response){
        var fallback = 'Erro ao atualizar status.';
        if ( response && response.data && response.data.error_code === 'version_conflict' ) {
          feedback.addClass('dps-status-feedback--error').text(getMessage('versionConflict', 'Esse agendamento foi atualizado por outro usu?rio. Atualize a p?gina para ver as altera??es.'));
          select.val(previous);
          return;
        }

        feedback.addClass('dps-status-feedback--error').text(getAjaxErrorMessage(response, getMessage('error', fallback)));
        if ( previous ) {
          select.val(previous);
        }
      }
    });
    // Evento para visualizar servi?os de um agendamento
    // Usa modal customizado em vez de alert() para melhor UX
    $(document).on('click', '.dps-services-link', function(e){
      e.preventDefault();
      var link = $(this);
      var apptId = parseInt(link.data('appt-id'), 10) || 0;

      if (!apptId) {
        showToast('Agendamento inválido.', 'error');
        return;
      }

      $.post(DPS_AG_Addon.ajax, {
        action: 'dps_get_services_details',
        appt_id: apptId,
        nonce: DPS_AG_Addon.nonce_services
      }, function(resp){
        if ( resp && resp.success ) {
          var services = resp.data.services || [];
          if ( services.length > 0 ) {
            // Exibe modal customizado em vez de alert()
            if ( typeof window.DPSServicesModal !== 'undefined' ) {
              window.DPSServicesModal.show(services);
            } else {
              // Fallback para alert() caso o modal n?o esteja carregado
              var message = '';
              for ( var i=0; i < services.length; i++ ) {
                var srv = services[i];
                message += srv.name + ' - R$ ' + parseFloat(srv.price).toFixed(2);
                if ( i < services.length - 1 ) message += "\n";
              }
              alert(message);
            }
          } else {
            // Lista vazia - exibe modal com mensagem apropriada se dispon?vel
            if ( typeof window.DPSServicesModal !== 'undefined' ) {
              window.DPSServicesModal.show([]);
            } else {
              showToast('Nenhum serviço encontrado para este agendamento.', 'info');
            }
          }
        } else {
          showToast(resp && resp.data ? resp.data.message : 'Erro ao buscar serviços.', 'error');
        }
      }).fail(function(xhr){
        showToast(getAjaxErrorMessage(xhr, 'Erro de comunicacao.'), 'error');
      });
    });
    $(document).on('click', '.dps-quick-action-btn', function(e){
      e.preventDefault();
      var btn = $(this);
      var apptId = btn.data('appt-id');
      var actionType = btn.data('action');
      var row = $('tr[data-appt-id="' + apptId + '"]');

      if ( ! apptId || ! actionType ) {
        return;
      }

      row.find('.dps-quick-action-btn').prop('disabled', true).addClass('is-loading');

      $.post(DPS_AG_Addon.ajax, {
        action: 'dps_agenda_quick_action',
        appt_id: apptId,
        action_type: actionType,
        nonce: DPS_AG_Addon.nonce_quick_action,
        agenda_tab: getCurrentAgendaTab()
      }).done(function(resp){
        if ( resp && resp.success ) {
          if ( resp.data && resp.data.row_html ) {
            replaceAgendaRow(row, resp.data.row_html);
          } else if ( resp.data && resp.data.new_status ) {
            updateRowStatus(apptId, resp.data.new_status);
            row.find('.dps-quick-action-btn').prop('disabled', false).removeClass('is-loading');
          }
        } else {
          handleQuickActionError(resp);
        }
      }).fail(function(xhr){
        handleQuickActionError(xhr);
      });

      function handleQuickActionError(response){
        showToast(getAjaxErrorMessage(response, 'Erro ao executar a acao. Tente novamente.'), 'error');
        row.find('.dps-quick-action-btn').prop('disabled', false).removeClass('is-loading');
        setTimeout(function(){
          location.reload();
        }, 1000);
      }
    });

    // CONF-2: Evento para botÃµes de confirmaÃ§Ã£o

    $(document).on('click', '.dps-confirmation-btn', function(e){
      e.preventDefault();
      var btn = $(this);
      var apptId = btn.data('appt-id');
      var confirmationStatus = btn.data('action');
      var row = $('tr[data-appt-id="' + apptId + '"]');

      if ( ! apptId || ! confirmationStatus ) {
        return;
      }

      row.find('.dps-confirmation-btn').prop('disabled', true).addClass('is-loading');

      $.post(DPS_AG_Addon.ajax, {
        action: 'dps_agenda_update_confirmation',
        appt_id: apptId,
        confirmation_status: confirmationStatus,
        nonce: DPS_AG_Addon.nonce_confirmation,
        agenda_tab: getCurrentAgendaTab()
      }).done(function(resp){
        if ( resp && resp.success ) {
          if ( resp.data && resp.data.row_html ) {
            replaceAgendaRow(row, resp.data.row_html);
          } else {
            row.find('.dps-confirmation-btn').prop('disabled', false).removeClass('is-loading');
          }
        } else {
          handleConfirmationError(resp);
        }
      }).fail(function(xhr){
        handleConfirmationError(xhr);
      });

      function handleConfirmationError(response){
        showToast(getAjaxErrorMessage(response, 'Erro ao atualizar confirmacao. Tente novamente.'), 'error');
        row.find('.dps-confirmation-btn').prop('disabled', false).removeClass('is-loading');
      }
    });
  });

  /**
   * Atualiza a classe de uma linha da tabela da agenda com o novo status.
   * Cada linha utiliza data-appt-id para identificÃ¡-la e classes CSS no formato
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


  function getCurrentAgendaTab(){
    var activeButton = $('.dps-agenda-tab-button--active').first();
    if ( activeButton.length && activeButton.data('tab') ) {
      return String(activeButton.data('tab'));
    }

    try {
      var agendaUrl = new URL(window.location.href);
      var requestedTab = agendaUrl.searchParams.get('agenda_tab');
      if ( requestedTab ) {
        return requestedTab;
      }
    } catch(e) {
      // Ignora erros da URL API
    }

    return 'visao-rapida';
  }

  function replaceAgendaRow(row, rowHtml){
    var currentRow = row && row.jquery ? row : $(row);
    if ( ! currentRow.length || ! rowHtml ) {
      return currentRow;
    }

    var parsedRows = $($.parseHTML($.trim(rowHtml), document, true)).filter('tr');
    if ( ! parsedRows.length ) {
      parsedRows = $(rowHtml).filter('tr');
    }
    if ( ! parsedRows.length ) {
      return currentRow;
    }

    var apptId = currentRow.data('appt-id');
    var detailRow = currentRow.next('.dps-detail-row[data-appt-id="' + apptId + '"]');
    var keepExpanded = detailRow.length && detailRow.is(':visible');

    currentRow.replaceWith(parsedRows);
    if ( detailRow.length ) {
      detailRow.remove();
    }

    if ( keepExpanded ) {
      parsedRows.filter('.dps-detail-row').show();
      parsedRows.first().find('.dps-expand-panels-btn').attr('aria-expanded', 'true');
    }

    parsedRows.addClass('dps-row-updated');
    setTimeout(function(){
      parsedRows.removeClass('dps-row-updated');
    }, 1500);

    return parsedRows.first();
  }

  function parseAjaxPayload(text){
    if ( ! text || typeof text !== 'string' ) {
      return null;
    }

    var trimmed = text.trim();
    if ( ! trimmed ) {
      return null;
    }

    try {
      return JSON.parse(trimmed);
    } catch (error) {
      // Continua: alguns proxies/plugins injetam HTML antes do JSON.
    }

    var start = trimmed.indexOf('{');
    var end = trimmed.lastIndexOf('}');
    if ( start === -1 || end === -1 || end <= start ) {
      return null;
    }

    try {
      return JSON.parse(trimmed.slice(start, end + 1));
    } catch (error) {
      return null;
    }
  }

  function getAjaxErrorMessage(xhr, fallback){
    if ( xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) {
      return xhr.responseJSON.data.message;
    }
    if ( xhr && xhr.responseJSON && xhr.responseJSON.message ) {
      return xhr.responseJSON.message;
    }

    var parsed = xhr && xhr.responseText ? parseAjaxPayload(xhr.responseText) : null;
    if ( parsed && parsed.data && parsed.data.message ) {
      return parsed.data.message;
    }
    if ( parsed && parsed.message ) {
      return parsed.message;
    }

    if ( xhr && xhr.responseText && /Fatal error|Parse error|Falha|Permiss|nonce|admin-ajax/i.test(xhr.responseText) ) {
      var text = $('<div>').html(xhr.responseText).text().replace(/\s+/g, ' ').trim();
      if ( text ) {
        return text.slice(0, 220);
      }
    }

    return fallback;
  }

  // FASE 2: ExportaÃ§Ã£o PDF (abre pÃ¡gina de impressÃ£o em nova janela)
  $(document).on('click', '.dps-export-pdf-btn', function(e){
    e.preventDefault();
    var btn = $(this);
    var date = btn.data('date') || '';
    var view = btn.data('view') || 'day';

    // ConstrÃ³i URL para a pÃ¡gina de impressÃ£o PDF
    var pdfUrl = DPS_AG_Addon.ajax +
      '?action=dps_agenda_export_pdf' +
      '&date=' + encodeURIComponent(date) +
      '&view=' + encodeURIComponent(view) +
      '&nonce=' + encodeURIComponent(DPS_AG_Addon.nonce_export_pdf);

    // Calcula dimensÃµes responsivas para a janela
    var width = Math.min(950, window.screen.availWidth - 100);
    var height = Math.min(700, window.screen.availHeight - 100);

    // Abre em nova janela com dimensÃµes responsivas
    window.open(pdfUrl, '_blank', 'width=' + width + ',height=' + height + ',scrollbars=yes');
  });

  // =========================================================================
  // FASE 5: Reagendamento RÃ¡pido
  // =========================================================================

  // Abrir modal de reagendamento
  $(document).on('click', '.dps-quick-reschedule', function(e){
    e.preventDefault();
    var btn = $(this);
    var apptId = btn.data('appt-id');
    var currentDate = btn.data('date');
    var currentTime = btn.data('time');

    // XSS FIX: Escape dos valores que serÃ£o inseridos no HTML
    var modal = $('<div class="dps-reschedule-modal">' +
      '<div class="dps-reschedule-content">' +
        '<div class="dps-reschedule-header">' +
          '<h3 class="dps-reschedule-title">ðŸ“… ' + getMessage('reschedule_title', 'Reagendar') + '</h3>' +
          '<button type="button" class="dps-reschedule-close">&times;</button>' +
        '</div>' +
        '<div class="dps-reschedule-body">' +
          '<div class="dps-reschedule-field">' +
            '<label>' + getMessage('new_date', 'Nova data') + '</label>' +
            '<input type="date" id="dps-reschedule-date" value="' + escapeHtml(currentDate) + '" required>' +
          '</div>' +
          '<div class="dps-reschedule-field">' +
            '<label>' + getMessage('new_time', 'Novo horÃ¡rio') + '</label>' +
            '<input type="time" id="dps-reschedule-time" value="' + escapeHtml(currentTime) + '" required>' +
          '</div>' +
        '</div>' +
        '<div class="dps-reschedule-footer">' +
          '<button type="button" class="dps-reschedule-btn dps-reschedule-btn--cancel">' + getMessage('cancel', 'Cancelar') + '</button>' +
          '<button type="button" class="dps-reschedule-btn dps-reschedule-btn--save" data-appt-id="' + parseInt(apptId, 10) + '">' + getMessage('save', 'Salvar') + '</button>' +
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
      showToast(getMessage('fill_all_fields', 'Preencha todos os campos.'), 'warning');
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
        showToast(resp.data.message, 'success');
        // Aguarda 2 segundos para o usuÃ¡rio ver a mensagem de sucesso antes de recarregar
        setTimeout(function(){ location.reload(); }, 2000);
      } else {
        showToast(resp.data ? resp.data.message : getMessage('error', 'Erro ao reagendar.'), 'error');
        btn.prop('disabled', false).text(getMessage('save', 'Salvar'));
      }
    }).fail(function(xhr){
      showToast(getAjaxErrorMessage(xhr, getMessage('error', 'Erro ao reagendar.')), 'error');
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
  // FASE 5: HistÃ³rico de AlteraÃ§Ãµes
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
          showToast(getMessage('no_history', 'Sem histÃ³rico de alteraÃ§Ãµes.'), 'info');
          return;
        }

        var content = getMessage('history_title', 'HistÃ³rico de AlteraÃ§Ãµes') + ':\n\n';
        history.forEach(function(entry){
          var actionLabel = getActionLabel(entry.action);
          content += 'â€¢ ' + entry.date + ' - ' + actionLabel + ' (' + entry.user + ')\n';
          if ( entry.details ) {
            if ( entry.details.old_status && entry.details.new_status ) {
              content += '  De: ' + entry.details.old_status + ' â†’ Para: ' + entry.details.new_status + '\n';
            }
            if ( entry.details.old_date && entry.details.new_date ) {
              content += '  De: ' + entry.details.old_date + ' ' + entry.details.old_time + '\n';
              content += '  Para: ' + entry.details.new_date + ' ' + entry.details.new_time + '\n';
            }
          }
        });

        // Exibe histÃ³rico em alert - formato de texto longo requer modal dedicado
        // TODO: Criar modal genÃ©rico de conteÃºdo para substituir alert em histÃ³rico
        alert(content);
      } else {
        showToast(resp.data ? resp.data.message : getMessage('error', 'Erro ao buscar histÃ³rico.'), 'error');
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

  // FASE 3: AÃ§Ãµes rÃ¡pidas de TaxiDog

  $(document).on('click', '.dps-taxidog-action-btn', function(e){
    e.preventDefault();
    var btn = $(this);
    var apptId = btn.data('appt-id');
    var taxidogStatus = btn.data('action');
    var row = btn.closest('tr');

    row.find('.dps-taxidog-action-btn').prop('disabled', true).css('opacity', 0.5);

    $.post(DPS_AG_Addon.ajax, {
      action: 'dps_agenda_update_taxidog',
      appt_id: apptId,
      taxidog_status: taxidogStatus,
      nonce: DPS_AG_Addon.nonce_taxidog,
      agenda_tab: getCurrentAgendaTab()
    }, function(resp){
      if ( resp && resp.success ) {
        if ( resp.data && resp.data.row_html ) {
          replaceAgendaRow(row, resp.data.row_html);
        } else {
          location.reload();
        }
      } else {
        showToast(resp && resp.data ? resp.data.message : 'Erro ao atualizar TaxiDog.', 'error');
        row.find('.dps-taxidog-action-btn').prop('disabled', false).css('opacity', 1);
      }
    }).fail(function(xhr){
      showToast(getAjaxErrorMessage(xhr, 'Erro de comunicacao ao atualizar TaxiDog.'), 'error');
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

    btn.prop('disabled', true).text('Reenviando...');

    $.post(DPS_AG_Addon.ajax, {
      action: 'dps_agenda_resend_payment',
      appt_id: apptId,
      nonce: DPS_AG_Addon.nonce_resend_payment,
      agenda_tab: getCurrentAgendaTab()
    }, function(resp){
      if (resp && resp.success) {
        if (resp.data && resp.data.row_html) {
          replaceAgendaRow(row, resp.data.row_html);
        } else {
          location.reload();
        }
      } else {
        showToast(resp && resp.data ? resp.data.message : 'Erro ao reenviar link.', 'error');
        btn.prop('disabled', false).text('Reenviar');
      }
    }).fail(function(xhr){
      showToast(getAjaxErrorMessage(xhr, 'Erro de comunicacao ao reenviar link.'), 'error');
      btn.prop('disabled', false).text('Reenviar');
    });
  });

  // FASE 6: Sistema de navegacao entre abas
  function activateAgendaTab(clickedButton) {
    var targetTab = clickedButton.data('tab');
    var buttons = $('.dps-agenda-tab-button');

    buttons.removeClass('dps-agenda-tab-button--active')
      .attr('aria-selected', 'false')
      .attr('tabindex', '-1');
    clickedButton.addClass('dps-agenda-tab-button--active')
      .attr('aria-selected', 'true')
      .attr('tabindex', '0');

    $('.dps-tab-content').removeClass('dps-tab-content--active').attr('hidden', true);
    $('#dps-tab-content-' + targetTab).addClass('dps-tab-content--active').removeAttr('hidden');

    try {
      sessionStorage.setItem('dps_agenda_current_tab', targetTab);
    } catch(e) {
      // Ignora erros de sessionStorage (modo privado, etc)
    }

    try {
      var nextUrl = new URL(window.location.href);
      nextUrl.searchParams.set('agenda_tab', targetTab);
      window.history.replaceState({}, '', nextUrl.toString());
    } catch(e) {
      // Ignora erros da URL API
    }
  }

  $(document).on('click', '.dps-agenda-tab-button', function(e){
    e.preventDefault();
    activateAgendaTab($(this));
  });

  $(document).on('keydown', '.dps-agenda-tab-button', function(e){
    var buttons = $('.dps-agenda-tab-button');
    var currentIndex = buttons.index(this);
    var nextIndex = currentIndex;

    if (e.key === 'ArrowRight') {
      nextIndex = (currentIndex + 1) % buttons.length;
    } else if (e.key === 'ArrowLeft') {
      nextIndex = (currentIndex - 1 + buttons.length) % buttons.length;
    } else if (e.key === 'Home') {
      nextIndex = 0;
    } else if (e.key === 'End') {
      nextIndex = buttons.length - 1;
    } else if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      activateAgendaTab($(this));
      return;
    } else {
      return;
    }

    e.preventDefault();
    var nextButton = buttons.eq(nextIndex);
    nextButton.focus();
    activateAgendaTab(nextButton);
  });
  // =========================================================================
  // FASE 7: Novos Handlers para Dropdowns e Popups
  // =========================================================================

  // Handler para dropdown de confirmaÃ§Ã£o (Tab1)

  $(document).on('change', '.dps-confirmation-dropdown', function(){
    var select = $(this);
    var apptId = select.data('appt-id');
    var confirmationStatus = select.val();
    var row = select.closest('tr');

    select.prop('disabled', true).addClass('is-loading');

    $.post(DPS_AG_Addon.ajax, {
      action: 'dps_agenda_update_confirmation',
      appt_id: apptId,
      confirmation_status: confirmationStatus,
      nonce: DPS_AG_Addon.nonce_confirmation,
      agenda_tab: getCurrentAgendaTab()
    }, function(resp){
      if (resp && resp.success) {
        if (resp.data && resp.data.row_html) {
          replaceAgendaRow(row, resp.data.row_html);
          showToast(resp.data.message || 'Confirmacao atualizada.', 'success', 1600);
        } else {
          select.removeClass('dps-dropdown--confirmed dps-dropdown--not-confirmed dps-dropdown--cancelled');
          if (confirmationStatus === 'confirmed') {
            select.addClass('dps-dropdown--confirmed');
          } else if (confirmationStatus === 'denied') {
            select.addClass('dps-dropdown--cancelled');
          } else {
            select.addClass('dps-dropdown--not-confirmed');
          }
          row.css('background-color', '#d1fae5');
          setTimeout(function(){
            row.css('background-color', '');
          }, 1000);
        }
      } else {
        showToast(resp && resp.data ? resp.data.message : 'Erro ao atualizar confirmacao.', 'error');
      }
    }).fail(function(xhr){
      showToast(getAjaxErrorMessage(xhr, 'Erro de comunicacao.'), 'error');
    }).always(function(){
      select.prop('disabled', false).removeClass('is-loading');
    });
  });

  // Handler para dropdown de status (Tab2) - usa o mesmo handler dps-status-select existente

  $(document).on('change', '.dps-status-dropdown', function(){
    var select = $(this);
    var apptId = select.data('appt-id');
    var status = select.val();
    var apptVersion = parseInt(select.data('appt-version'), 10) || 0;
    var previous = select.data('current-status');
    var row = select.closest('tr');

    select.prop('disabled', true).addClass('is-loading');

    $.post(DPS_AG_Addon.ajax, {
      action: 'dps_update_status',
      id: apptId,
      status: status,
      version: apptVersion,
      nonce: DPS_AG_Addon.nonce_status,
      agenda_tab: getCurrentAgendaTab()
    }, function(resp){
      if (resp && resp.success) {
        select.data('current-status', status);
        if (resp.data && resp.data.version) {
          select.data('appt-version', resp.data.version);
        }

        if (resp.data && resp.data.row_html) {
          replaceAgendaRow(row, resp.data.row_html);
          if (status === 'finalizado' || status === 'finalizado_pago') {
            setTimeout(function(){
              openChecklistPopup(apptId);
            }, 150);
          } else {
            showToast(resp.data.message || 'Status atualizado.', 'success', 1600);
          }
        } else {
          select.removeClass('dps-dropdown--pending dps-dropdown--finished dps-dropdown--paid dps-dropdown--cancelled');
          if (status === 'pendente') {
            select.addClass('dps-dropdown--pending');
          } else if (status === 'finalizado') {
            select.addClass('dps-dropdown--finished');
          } else if (status === 'finalizado_pago') {
            select.addClass('dps-dropdown--paid');
          } else if (status === 'cancelado') {
            select.addClass('dps-dropdown--cancelled');
          }
          row.removeClass('status-pendente status-finalizado status-finalizado_pago status-cancelado')
             .addClass('status-' + status);
          row.css('background-color', '#d1fae5');
          setTimeout(function(){
            row.css('background-color', '');
            if (status === 'finalizado' || status === 'finalizado_pago') {
              openChecklistPopup(apptId);
            } else {
              location.reload();
            }
          }, 800);
        }
      } else {
        if (resp && resp.data && resp.data.error_code === 'version_conflict') {
          showToast(getMessage('versionConflict', 'Esse agendamento foi atualizado por outro usu?rio. Atualize a p?gina para ver as altera??es.'), 'warning');
        } else {
          showToast(resp && resp.data ? resp.data.message : 'Erro ao atualizar status.', 'error');
        }
        select.val(previous);
      }
    }).fail(function(xhr){
      showToast(getAjaxErrorMessage(xhr, 'Erro de comunicacao.'), 'error');
      select.val(previous);
    }).always(function(){
      select.prop('disabled', false).removeClass('is-loading');
    });
  });

  // Handler para bot�o de popup de servi�os (Tab1)
  var SERVICES_MODAL_SELECTOR = '.dps-services-modal';
  var SERVICES_MODAL_CONTENT_SELECTOR = '.dps-services-modal-content';
  var SERVICES_MODAL_CLOSE_SELECTOR = '.dps-services-modal-close';
  var SERVICES_MODAL_FOCUSABLE_SELECTOR = 'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"]), input:not([disabled]), select:not([disabled]), textarea:not([disabled])';
  var servicesModalCounter = 0;
  var lastServicesTrigger = null;

  function formatServiceDuration(totalDuration) {
    var minutes = parseInt(totalDuration, 10) || 0;
    var hours = Math.floor(minutes / 60);
    var mins = minutes % 60;

    if (minutes <= 0) {
      return '';
    }

    if (hours > 0 && mins > 0) {
      return hours + 'h ' + mins + 'min';
    }

    if (hours > 0) {
      return hours + 'h';
    }

    return mins + 'min';
  }

  function getServiceCardVisual(service) {
    if (service && service.is_taxidog) {
      return { icon: '\uD83D\uDE90', typeClass: 'dps-service-type-taxidog', typeLabel: 'Transporte' };
    }

    if (service && service.type === 'extra') {
      return { icon: '\u2728', typeClass: 'dps-service-type-extra', typeLabel: 'Extra' };
    }

    if (service && service.type === 'package') {
      return { icon: '\uD83D\uDCE6', typeClass: 'dps-service-type-pacote', typeLabel: 'Pacote' };
    }

    return { icon: '\u2702\uFE0F', typeClass: 'dps-service-type-padrao', typeLabel: 'Servi\u00E7o' };
  }

  function getPetSizeLabel(size) {
    var normalized = String(size || '').toLowerCase();

    if (normalized === 'pequeno' || normalized === 'small') {
      return 'Pequeno';
    }

    if (normalized === 'medio' || normalized === 'm�dio' || normalized === 'medium') {
      return 'M\u00E9dio';
    }

    if (normalized === 'grande' || normalized === 'large') {
      return 'Grande';
    }

    return '';
  }

  function closeServicesModal() {
    var modal = $(SERVICES_MODAL_SELECTOR);

    if (!modal.length) {
      return;
    }

    modal.remove();
    $('body').removeClass('dps-services-modal-open');
    $(document).off('keydown.dpsServicesModal');
    $('.dps-services-popup-btn[aria-expanded="true"]').attr('aria-expanded', 'false');

    if (lastServicesTrigger && lastServicesTrigger.length) {
      lastServicesTrigger.trigger('focus');
    }

    lastServicesTrigger = null;
  }

  function getServicesModalFocusables(modal) {
    return modal.find(SERVICES_MODAL_FOCUSABLE_SELECTOR).filter(':visible');
  }

  function trapServicesModalFocus(event) {
    if (event.key !== 'Tab') {
      return;
    }

    var modal = $(SERVICES_MODAL_SELECTOR);
    if (!modal.length) {
      return;
    }

    var focusables = getServicesModalFocusables(modal);
    if (!focusables.length) {
      event.preventDefault();
      modal.find(SERVICES_MODAL_CONTENT_SELECTOR).trigger('focus');
      return;
    }

    var first = focusables.first()[0];
    var last = focusables.last()[0];

    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
      return;
    }

    if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  }

  function buildServicesModalHtml(services, notes, pet, totalDuration) {
    servicesModalCounter += 1;

    var titleId = 'dps-services-modal-title-' + servicesModalCounter;
    var descId = 'dps-services-modal-desc-' + servicesModalCounter;
    var total = 0;
    var durationText = formatServiceDuration(totalDuration);
    var modalHtml = '<div class="dps-services-modal" role="dialog" aria-modal="true" aria-labelledby="' + titleId + '" aria-describedby="' + descId + '">' +
      '<div class="dps-services-modal-content" role="document" tabindex="-1">' +
        '<div class="dps-services-modal-header">' +
          '<h3 id="' + titleId + '" class="dps-services-modal-title">\uD83D\uDCCB Servi\u00E7os do atendimento</h3>' +
          '<button type="button" class="dps-services-modal-close" aria-label="Fechar modal">&times;</button>' +
        '</div>' +
        '<div class="dps-services-modal-body" id="' + descId + '">';

    if (pet && pet.name) {
      var petMeta = [];
      var petSize = getPetSizeLabel(pet.size);

      if (petSize) {
        petMeta.push(petSize);
      }
      if (pet.breed) {
        petMeta.push(String(pet.breed));
      }
      if (pet.weight) {
        petMeta.push(String(pet.weight) + ' kg');
      }

      modalHtml += '<div class="dps-services-pet-info">' +
        '<span class="dps-services-pet-icon" aria-hidden="true">\uD83D\uDC15</span>' +
        '<div class="dps-services-pet-details">' +
          '<span class="dps-services-pet-name">' + escapeHtml(pet.name) + '</span>';

      if (petMeta.length > 0) {
        modalHtml += '<span class="dps-services-pet-meta">' + escapeHtml(petMeta.join(' \u2022 ')) + '</span>';
      }

      modalHtml += '</div></div>';
    }

    if (services.length > 0 || durationText) {
      modalHtml += '<div class="dps-services-summary">';
      modalHtml += '<div class="dps-services-summary-item">' +
        '<span class="dps-services-summary-icon" aria-hidden="true">\uD83D\uDCCB</span>' +
        '<span class="dps-services-summary-value">' + services.length + '</span>' +
        '<span class="dps-services-summary-label">servi\u00E7o' + (services.length !== 1 ? 's' : '') + '</span>' +
      '</div>';

      if (durationText) {
        modalHtml += '<div class="dps-services-summary-item">' +
          '<span class="dps-services-summary-icon" aria-hidden="true">\u23F1\uFE0F</span>' +
          '<span class="dps-services-summary-value">' + escapeHtml(durationText) + '</span>' +
          '<span class="dps-services-summary-label">tempo estimado</span>' +
        '</div>';
      }

      modalHtml += '</div>';
    }

    if (services.length > 0) {
      modalHtml += '<div class="dps-services-checklist">';
      modalHtml += '<h4 class="dps-services-section-title">Servi\u00E7os a realizar</h4>';

      for (var i = 0; i < services.length; i++) {
        var srv = services[i] || {};
        var price = parseFloat(srv.price) || 0;
        var visualInfo = getServiceCardVisual(srv);
        var serviceName = String(srv.name || 'Servi\u00E7o');
        var cardMeta = [];

        total += price;

        if (visualInfo.typeLabel && srv.type !== 'padrao') {
          cardMeta.push(visualInfo.typeLabel);
        }
        if (srv.duration && parseInt(srv.duration, 10) > 0) {
          cardMeta.push(String(srv.duration) + ' min');
        }

        modalHtml += '<div class="dps-service-card ' + visualInfo.typeClass + '">' +
          '<div class="dps-service-card-header">' +
            '<span class="dps-service-card-icon" aria-hidden="true">' + visualInfo.icon + '</span>' +
            '<div class="dps-service-card-info">' +
              '<span class="dps-service-card-name">' + escapeHtml(serviceName) + '</span>' +
              '<span class="dps-service-card-meta">' + (cardMeta.length ? escapeHtml(cardMeta.join(' \u2022 ')) : '') + '</span>' +
            '</div>' +
            '<span class="dps-service-card-price">R$ ' + price.toFixed(2).replace('.', ',') + '</span>' +
          '</div>';

        if (srv.description && String(srv.description).trim()) {
          modalHtml += '<div class="dps-service-card-description">' +
            '<span class="dps-service-card-desc-icon" aria-hidden="true">\uD83D\uDCA1</span>' +
            '<span>' + escapeHtml(String(srv.description)).replace(/\n/g, '<br>') + '</span>' +
          '</div>';
        }

        modalHtml += '</div>';
      }

      modalHtml += '<div class="dps-services-total-row">' +
        '<span class="dps-services-total-label">Total</span>' +
        '<span class="dps-services-total-value">R$ ' + total.toFixed(2).replace('.', ',') + '</span>' +
      '</div>';

      modalHtml += '</div>';
    } else {
      modalHtml += '<div class="dps-services-empty">' +
        '<span class="dps-services-empty-icon" aria-hidden="true">\uD83D\uDCCB</span>' +
        '<span>Nenhum servi\u00E7o registrado para este atendimento.</span>' +
      '</div>';
    }

    if (notes && String(notes).trim()) {
      modalHtml += '<div class="dps-services-notes dps-services-notes-highlight">' +
        '<div class="dps-services-notes-title">' +
          '<span class="dps-services-notes-icon" aria-hidden="true">\u26A0\uFE0F</span>' +
          '<span>Observa\u00E7\u00F5es do cliente</span>' +
        '</div>' +
        '<div class="dps-services-notes-content">' + escapeHtml(String(notes)).replace(/\n/g, '<br>') + '</div>' +
      '</div>';
    }

    modalHtml += '</div></div></div>';

    return modalHtml;
  }

  function openServicesModal(trigger, services, notes, pet, totalDuration) {
    closeServicesModal();

    lastServicesTrigger = trigger;
    if (trigger && trigger.length) {
      trigger.attr('aria-expanded', 'true');
    }

    $('body')
      .addClass('dps-services-modal-open')
      .append(buildServicesModalHtml(services, notes, pet, totalDuration));

    $(document).on('keydown.dpsServicesModal', function(event) {
      if (event.key === 'Escape') {
        event.preventDefault();
        closeServicesModal();
        return;
      }

      trapServicesModalFocus(event);
    });

    window.setTimeout(function() {
      var modal = $(SERVICES_MODAL_SELECTOR);
      var focusables = getServicesModalFocusables(modal);

      if (focusables.length) {
        focusables.first().trigger('focus');
      } else {
        modal.find(SERVICES_MODAL_CONTENT_SELECTOR).trigger('focus');
      }
    }, 0);
  }

  $(document).on('click', '.dps-services-popup-btn', function(e) {
    e.preventDefault();
    var btn = $(this);
    var apptId = parseInt(btn.data('appt-id'), 10) || 0;

    if (!apptId) {
      showToast('Agendamento inv\u00E1lido.', 'error');
      return;
    }

    btn.attr('aria-haspopup', 'dialog');
    if (!btn.attr('aria-expanded')) {
      btn.attr('aria-expanded', 'false');
    }

    btn.prop('disabled', true);

    $.post(DPS_AG_Addon.ajax, {
      action: 'dps_get_services_details',
      appt_id: apptId,
      nonce: DPS_AG_Addon.nonce_services
    }, function(resp){
      if (resp && resp.success) {
        var services = resp.data.services || [];
        var notes = resp.data.notes || '';
        var pet = resp.data.pet || {};
        var totalDuration = resp.data.total_duration || 0;

        openServicesModal(btn, services, notes, pet, totalDuration);
      } else {
        showToast(resp && resp.data ? resp.data.message : 'Erro ao carregar servi\u00E7os.', 'error');
      }
    }).fail(function(xhr){
      showToast(getAjaxErrorMessage(xhr, 'Erro de comunica\u00E7\u00E3o.'), 'error');
    }).always(function(){
      btn.prop('disabled', false);
    });
  });

  $(document).on('click', SERVICES_MODAL_CLOSE_SELECTOR, function(event) {
    event.preventDefault();
    closeServicesModal();
  });

  $(document).on('click', SERVICES_MODAL_SELECTOR, function(event) {
    if ($(event.target).is(SERVICES_MODAL_SELECTOR)) {
      closeServicesModal();
    }
  });

  $(document).on('click', '.dps-payment-popup-btn', function(e){
    e.preventDefault();
    var btn = $(this);
    var apptId = btn.data('appt-id');
    var paymentLink = btn.data('payment-link');
    var clientPhone = btn.data('client-phone');
    var whatsappMsg = btn.data('whatsapp-msg');
    var clientName = btn.data('client-name');
    var petName = btn.data('pet-name');
    var totalValue = btn.data('total-value');

    // Formata o nÃºmero de WhatsApp (Brasil)
    // Remove todos os caracteres nÃ£o numÃ©ricos
    var whatsappNumber = (clientPhone || '').replace(/\D/g, '');
    // Adiciona cÃ³digo do paÃ­s apenas se necessÃ¡rio
    // 10 dÃ­gitos = DDD (2) + nÃºmero fixo (8) - formato antigo celular
    // 11 dÃ­gitos = DDD (2) + nÃºmero celular (9)
    // 12 dÃ­gitos = cÃ³digo paÃ­s (2) + DDD (2) + nÃºmero fixo (8)
    // 13 dÃ­gitos = cÃ³digo paÃ­s (2) + DDD (2) + nÃºmero celular (9)
    if (whatsappNumber.length === 10 || whatsappNumber.length === 11) {
      // NÃºmero brasileiro sem cÃ³digo do paÃ­s
      whatsappNumber = '55' + whatsappNumber;
    } else if (whatsappNumber.length >= 12 && whatsappNumber.substring(0, 2) === '55') {
      // NÃºmero jÃ¡ tem cÃ³digo do paÃ­s (mantÃ©m como estÃ¡)
    } else if (whatsappNumber.length < 10) {
      // NÃºmero muito curto - mantÃ©m para o usuÃ¡rio corrigir
    }

    var whatsappUrl = 'https://wa.me/' + whatsappNumber + '?text=' + encodeURIComponent(whatsappMsg);

    // Monta o HTML do modal
    // XSS FIX: Escape de dados de texto inseridos no HTML
    // URLs em href nÃ£o precisam de escapeHtml (jÃ¡ sÃ£o URL-encoded), mas data-attributes sim
    var modalHtml = '<div class="dps-payment-modal">' +
      '<div class="dps-payment-modal-content">' +
        '<div class="dps-payment-modal-header">' +
          '<h3 class="dps-payment-modal-title">ðŸ’³ Enviar Link de Pagamento</h3>' +
          '<button type="button" class="dps-payment-modal-close">&times;</button>' +
        '</div>' +
        '<div class="dps-payment-modal-body">' +
          '<div class="dps-payment-info">' +
            '<div class="dps-payment-info-item"><span class="dps-payment-info-label">Cliente:</span><span class="dps-payment-info-value">' + escapeHtml(clientName) + '</span></div>' +
            '<div class="dps-payment-info-item"><span class="dps-payment-info-label">Pet:</span><span class="dps-payment-info-value">' + escapeHtml(petName) + '</span></div>' +
            '<div class="dps-payment-info-item"><span class="dps-payment-info-label">Valor:</span><span class="dps-payment-info-value">R$ ' + escapeHtml(totalValue) + '</span></div>' +
          '</div>' +
          '<div class="dps-payment-actions">' +
            '<a href="' + whatsappUrl + '" target="_blank" class="dps-payment-action-btn dps-payment-action-btn--whatsapp">' +
              'ðŸ“± Enviar por WhatsApp' +
            '</a>' +
            '<button type="button" class="dps-payment-action-btn dps-payment-action-btn--copy" data-link="' + escapeHtml(paymentLink) + '">' +
              'ðŸ“‹ Copiar Link de Pagamento' +
            '</button>' +
          '</div>' +
        '</div>' +
      '</div>' +
    '</div>';

    $('body').append(modalHtml);
  });

  // Fechar modal de pagamento
  $(document).on('click', '.dps-payment-modal-close', function(){
    $('.dps-payment-modal').remove();
  });

  $(document).on('click', '.dps-payment-modal', function(e){
    if ($(e.target).hasClass('dps-payment-modal')) {
      $(this).remove();
    }
  });

  // Copiar link de pagamento
  $(document).on('click', '.dps-payment-action-btn--copy', function(){
    var btn = $(this);
    var link = btn.data('link');

    // Copia para clipboard
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(link).then(function(){
        btn.addClass('copied').text('âœ… Link Copiado!');
        setTimeout(function(){
          btn.removeClass('copied').text('ðŸ“‹ Copiar Link de Pagamento');
        }, 2000);
      });
    } else {
      // Fallback para navegadores antigos
      var tempInput = $('<input>');
      $('body').append(tempInput);
      tempInput.val(link).select();
      document.execCommand('copy');
      tempInput.remove();
      btn.addClass('copied').text('âœ… Link Copiado!');
      setTimeout(function(){
        btn.removeClass('copied').text('ðŸ“‹ Copiar Link de Pagamento');
      }, 2000);
    }
  });

  // Handler para dropdown de TaxiDog (Tab3)

  $(document).on('change', '.dps-taxidog-dropdown', function(){
    var select = $(this);
    var apptId = select.data('appt-id');
    var taxidogStatus = select.val();
    var row = select.closest('tr');

    select.prop('disabled', true).addClass('is-loading');

    $.post(DPS_AG_Addon.ajax, {
      action: 'dps_agenda_update_taxidog',
      appt_id: apptId,
      taxidog_status: taxidogStatus,
      nonce: DPS_AG_Addon.nonce_taxidog,
      agenda_tab: getCurrentAgendaTab()
    }, function(resp){
      if (resp && resp.success) {
        if (resp.data && resp.data.row_html) {
          replaceAgendaRow(row, resp.data.row_html);
          showToast(resp.data.message || 'TaxiDog atualizado.', 'success', 1600);
        } else {
          row.css('background-color', '#d1fae5');
          setTimeout(function(){
            row.css('background-color', '');
          }, 1000);
        }
      } else {
        showToast(resp && resp.data ? resp.data.message : 'Erro ao atualizar TaxiDog.', 'error');
      }
    }).fail(function(xhr){
      showToast(getAjaxErrorMessage(xhr, 'Erro de comunicacao.'), 'error');
    }).always(function(){
      select.prop('disabled', false).removeClass('is-loading');
    });
  });

  // Handler para botÃ£o de solicitar TaxiDog (Tab3)

  $(document).on('click', '.dps-taxidog-request-btn', function(e){
    e.preventDefault();
    var btn = $(this);
    var apptId = btn.data('appt-id');
    var row = btn.closest('tr');

    if (!confirm('Deseja solicitar TaxiDog para este atendimento?')) {
      return;
    }

    btn.prop('disabled', true).text('Solicitando...');

    $.post(DPS_AG_Addon.ajax, {
      action: 'dps_agenda_request_taxidog',
      appt_id: apptId,
      nonce: DPS_AG_Addon.nonce_taxidog,
      agenda_tab: getCurrentAgendaTab()
    }, function(resp){
      if (resp && resp.success) {
        if (resp.data && resp.data.row_html) {
          replaceAgendaRow(row, resp.data.row_html);
          showToast(resp.data.message || 'TaxiDog solicitado com sucesso!', 'success', 1600);
        } else {
          location.reload();
        }
      } else {
        showToast(resp && resp.data ? resp.data.message : 'Erro ao solicitar TaxiDog.', 'error');
        btn.prop('disabled', false).text('Solicitar TaxiDog');
      }
    }).fail(function(xhr){
      showToast(getAjaxErrorMessage(xhr, 'Erro de comunicacao.'), 'error');
      btn.prop('disabled', false).text('Solicitar TaxiDog');
    });
  });

  /* ===========================
     CHECKLIST OPERACIONAL â€” POPUP
     =========================== */

  /**
   * Abre popup com o checklist operacional carregado via AJAX.
   *
   * @param {number} apptId ID do agendamento.
   */
  function openChecklistPopup(apptId) {
    // Remove popup existente
    $('.dps-checklist-modal-overlay').remove();

    var loadingMsg = escapeHtml(getMessage('checklistLoading', 'Carregando checklist...'));
    var titleMsg = escapeHtml(getMessage('checklistTitle', 'Checklist Operacional'));
    var closeMsg = escapeHtml(getMessage('close', 'Fechar'));

    var html =
      '<div class="dps-checklist-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="dps-checklist-modal-title">' +
        '<div class="dps-checklist-modal">' +
          '<div class="dps-checklist-modal-header">' +
            '<h3 id="dps-checklist-modal-title">ðŸ“‹ ' + titleMsg + '</h3>' +
            '<button type="button" class="dps-checklist-modal-close" title="' + closeMsg + '">&times;</button>' +
          '</div>' +
          '<div class="dps-checklist-modal-body">' +
            '<p class="dps-checklist-modal-loading">' + loadingMsg + '</p>' +
          '</div>' +
          '<div class="dps-checklist-modal-footer">' +
            '<button type="button" class="dps-checklist-btn dps-checklist-modal-close-btn">' + closeMsg + '</button>' +
          '</div>' +
        '</div>' +
      '</div>';

    $('body').append(html);

    // Carrega o painel do checklist via AJAX
    $.post(DPS_AG_Addon.ajax, {
      action: 'dps_get_checklist_panel',
      appointment_id: apptId,
      nonce: DPS_AG_Addon.nonce_checklist
    }, function(resp) {
      if (resp && resp.success && resp.data && resp.data.html) {
        $('.dps-checklist-modal-body').html(resp.data.html);
      } else {
        var errorMsg = getMessage('checklistError', 'NÃ£o foi possÃ­vel carregar o checklist.');
        $('.dps-checklist-modal-body').html('<p class="dps-checklist-modal-error">' + escapeHtml(errorMsg) + '</p>');
      }
    }).fail(function() {
      var errorMsg = getMessage('checklistError', 'NÃ£o foi possÃ­vel carregar o checklist.');
      $('.dps-checklist-modal-body').html('<p class="dps-checklist-modal-error">' + escapeHtml(errorMsg) + '</p>');
    });
  }

  // Fechar popup do checklist
  $(document).on('click', '.dps-checklist-modal-close, .dps-checklist-modal-close-btn', function() {
    $('.dps-checklist-modal-overlay').remove();
    location.reload();
  });

  // Fechar popup do checklist clicando fora
  $(document).on('click', '.dps-checklist-modal-overlay', function(e) {
    if ($(e.target).hasClass('dps-checklist-modal-overlay')) {
      $('.dps-checklist-modal-overlay').remove();
      location.reload();
    }
  });

  // Fechar modais com ESC
  $(document).on('keydown', function(e){
    if (e.key === 'Escape') {
      if ($('.dps-checklist-modal-overlay').length) {
        $('.dps-checklist-modal-overlay').remove();
        location.reload();
        return;
      }
      if ($('.dps-services-modal').length) {
        closeServicesModal();
      }
      $('.dps-payment-modal').remove();
    }
  });

})(jQuery);
