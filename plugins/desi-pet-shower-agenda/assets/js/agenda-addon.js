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
   * Exibe notificação toast estilizada para feedback do usuário.
   * Substitui alert() nativo por uma notificação moderna.
   * @param {string} message Mensagem a ser exibida.
   * @param {string} type Tipo da notificação: 'error', 'success', 'warning', 'info'.
   * @param {number} duration Duração em ms (padrão: 4000).
   */
  function showToast(message, type, duration) {
    type = type || 'error';
    duration = duration || 4000;

    // Remove toast existente
    $('.dps-toast').remove();

    var icons = {
      error: '❌',
      success: '✅',
      warning: '⚠️',
      info: 'ℹ️'
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

    // Auto-remove após duração
    if (duration > 0) {
      setTimeout(function() {
        toast.removeClass('dps-toast--visible');
        setTimeout(function() { toast.remove(); }, 300);
      }, duration);
    }
  }

  // Expõe globalmente para uso em outros módulos
  window.DPSToast = { show: showToast };

  /**
   * Obtém o label e ícone do porte do pet.
   * @param {string} size Porte do pet (pequeno, medio, grande, small, medium, large).
   * @return {object} Objeto com label e icon.
   */
  function getPetSizeInfo(size) {
    if (!size) return { label: '', icon: '🐕' };

    var sizeLower = size.toLowerCase();
    var sizeMap = {
      'pequeno': { label: 'Pequeno', icon: '🐕' },
      'small': { label: 'Pequeno', icon: '🐕' },
      'medio': { label: 'Médio', icon: '🦮' },
      'médio': { label: 'Médio', icon: '🦮' },
      'medium': { label: 'Médio', icon: '🦮' },
      'grande': { label: 'Grande', icon: '🐕‍🦺' },
      'large': { label: 'Grande', icon: '🐕‍🦺' }
    };

    return sizeMap[sizeLower] || { label: '', icon: '🐕' };
  }

  /**
   * Obtém informações visuais do serviço (ícone, classe, label).
   * @param {object} service Objeto do serviço com name, type, category, is_taxidog.
   * @return {object} Objeto com icon, typeClass, typeLabel.
   */
  function getServiceVisualInfo(service) {
    // Mapas de ícones por tipo e categoria
    var typeIcons = {
      'taxidog': { icon: '🚐', typeClass: 'dps-service-type-taxidog', typeLabel: 'Transporte' },
      'extra': { icon: '✨', typeClass: 'dps-service-type-extra', typeLabel: 'Extra' },
      'package': { icon: '📦', typeClass: 'dps-service-type-pacote', typeLabel: 'Pacote' }
    };

    var categoryIcons = {
      'banho': '🛁',
      'tosa': '✂️',
      'unha': '💅',
      'ouvido': '👂',
      'dente': '🦷'
    };

    // Verifica TaxiDog primeiro
    if (service.is_taxidog) {
      return typeIcons.taxidog;
    }

    // Verifica tipo
    if (service.type && typeIcons[service.type]) {
      var info = Object.assign({}, typeIcons[service.type]);
      // Determina ícone pela categoria ou nome
      info.icon = getCategoryIcon(service, categoryIcons);
      return info;
    }

    // Serviço padrão
    return {
      icon: getCategoryIcon(service, categoryIcons),
      typeClass: 'dps-service-type-padrao',
      typeLabel: 'Serviço'
    };
  }

  /**
   * Obtém o ícone baseado na categoria ou nome do serviço.
   * @param {object} service Objeto do serviço.
   * @param {object} categoryIcons Mapa de ícones por categoria.
   * @return {string} Ícone emoji.
   */
  function getCategoryIcon(service, categoryIcons) {
    // Verifica categoria diretamente
    if (service.category && categoryIcons[service.category]) {
      return categoryIcons[service.category];
    }

    // Verifica pelo nome do serviço
    if (service.name) {
      var nameLower = service.name.toLowerCase();
      for (var cat in categoryIcons) {
        if (nameLower.indexOf(cat) !== -1) {
          return categoryIcons[cat];
        }
      }
    }

    // Ícone padrão
    return '✂️';
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
    $(document).on('click', '[data-dps-open-appointment-modal]', function(e){
      e.preventDefault();
      openAppointmentModal($(this));
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
              showToast('Nenhum serviço encontrado para este agendamento.', 'info');
            }
          }
        } else {
          showToast(resp.data ? resp.data.message : 'Erro ao buscar serviços.', 'error');
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

        showToast(message, 'error');

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

        showToast(message, 'error');

        // Remove estado de loading
        row.find('.dps-confirmation-btn').prop('disabled', false).removeClass('is-loading');
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

  var appointmentModal;

  function ensureAppointmentModal(){
    if ( appointmentModal && appointmentModal.length ) {
      return appointmentModal;
    }
    appointmentModal = $('<div class="dps-appointment-modal" role="dialog" aria-modal="true" aria-labelledby="dps-appointment-modal-title"></div>');
    var content = $('<div class="dps-appointment-modal__content" role="document"></div>');
    content.append('<div class="dps-appointment-modal__header"><h2 class="dps-appointment-modal__title" id="dps-appointment-modal-title" tabindex="-1"></h2><button type="button" class="dps-appointment-modal__close" aria-label="' + getMessage('close', 'Fechar modal') + '">&times;</button></div>');
    content.append('<div class="dps-appointment-modal__messages" aria-live="polite"></div>');
    content.append('<div class="dps-appointment-modal__body"></div>');
    appointmentModal.append('<div class="dps-appointment-modal__backdrop" aria-hidden="true"></div>');
    appointmentModal.append(content);
    $('body').append(appointmentModal);
    return appointmentModal;
  }

  function setModalBody(content){
    var modal = ensureAppointmentModal();
    modal.find('.dps-appointment-modal__body').html(content);
  }

  function renderModalMessages(html, fallbackMessage){
    var modal = ensureAppointmentModal();
    var container = modal.find('.dps-appointment-modal__messages');
    container.empty();
    if ( html ) {
      container.html(html);
      return;
    }
    if ( fallbackMessage ) {
      container.html('<div class="dps-modal-alert">' + fallbackMessage + '</div>');
    }
  }

  function closeAppointmentModal(){
    if ( appointmentModal && appointmentModal.length ) {
      appointmentModal.removeClass('is-open');
      appointmentModal.find('.dps-appointment-modal__body').empty();
      appointmentModal.find('.dps-appointment-modal__messages').empty();
      $('body').removeClass('dps-modal-open');
    }
  }

  function dispatchAppointmentFormLoaded(container){
    var target = container || (appointmentModal ? appointmentModal.find('.dps-appointment-modal__body')[0] : document);
    var eventData = { detail: { container: target } };
    document.dispatchEvent(new CustomEvent('dps:appointmentFormLoaded', eventData));
    $(document).trigger('dps:appointmentFormLoaded', [target]);
  }

  function fallbackToNewPage(url){
    closeAppointmentModal();
    if ( url ) {
      window.location.href = url;
    }
  }

  function openAppointmentModal(button){
    var fallbackUrl = button && button.attr ? button.attr('href') : '';
    if ( typeof DPS_AG_Addon === 'undefined' || ! DPS_AG_Addon.nonce_modal_form ) {
      fallbackToNewPage(fallbackUrl);
      return;
    }

    var modal = ensureAppointmentModal();
    modal.find('.dps-appointment-modal__title').text(getMessage('modalTitle', 'Novo agendamento'));
    renderModalMessages('', '');
    setModalBody('<div class="dps-appointment-modal__loader">' + getMessage('formLoading', 'Carregando formulário...') + '</div>');
    modal.addClass('is-open');
    $('body').addClass('dps-modal-open');
    if ( button && button.length ) {
      button.addClass('is-loading').prop('disabled', true);
    }

    var prefClient = (button && button.data('pref-client')) ? button.data('pref-client') : '';
    var filterClient = $('[name="filter_client"]').val();
    if ( ! prefClient && filterClient ) {
      prefClient = filterClient;
    }
    var prefPet = (button && button.data('pref-pet')) ? button.data('pref-pet') : '';

    $.post(DPS_AG_Addon.ajax, {
      action: 'dps_render_appointment_form',
      nonce: DPS_AG_Addon.nonce_modal_form,
      pref_client: prefClient || '',
      pref_pet: prefPet || '',
      redirect_url: window.location.href
    }).done(function(resp){
      if ( resp && resp.success && resp.data && resp.data.html ) {
        setModalBody(resp.data.html);
        if ( typeof window.dpsAppointmentData !== 'undefined' ) {
          window.dpsAppointmentData.appointmentId = 0;
        }
        dispatchAppointmentFormLoaded(appointmentModal.find('.dps-appointment-modal__body')[0]);
        appointmentModal.find('.dps-appointment-modal__title').focus();
      } else {
        fallbackToNewPage(fallbackUrl);
      }
    }).fail(function(){
      fallbackToNewPage(fallbackUrl);
    }).always(function(){
      if ( button && button.length ) {
        button.removeClass('is-loading').prop('disabled', false);
      }
    });
  }

  $(document).on('click', '.dps-appointment-modal__close, .dps-appointment-modal__backdrop', function(){
    closeAppointmentModal();
  });

  $(document).on('submit', '.dps-appointment-modal form.dps-form', function(e){
    e.preventDefault();
    var form = $(this);
    var submitBtn = form.find('.dps-appointment-submit');
    submitBtn.addClass('is-loading').prop('disabled', true);

    var formData = new FormData(this);
    formData.append('action', 'dps_modal_save_appointment');
    formData.append('nonce', DPS_AG_Addon.nonce_modal_form);

    $.ajax({
      url: DPS_AG_Addon.ajax,
      method: 'POST',
      data: formData,
      processData: false,
      contentType: false
    }).done(function(resp){
      if ( resp && resp.data && resp.data.messages_html ) {
        renderModalMessages(resp.data.messages_html);
      } else if ( ! (resp && resp.success) ) {
        renderModalMessages('', getMessage('saveError', 'Não foi possível salvar o agendamento.'));
      }

      if ( resp && resp.success ) {
        setTimeout(function(){
          var redirect = (resp.data && resp.data.redirect) ? resp.data.redirect : window.location.href;
          window.location.href = redirect;
        }, 350);
      }
    }).fail(function(){
      renderModalMessages('', getMessage('saveError', 'Não foi possível salvar o agendamento.'));
    }).always(function(){
      submitBtn.removeClass('is-loading').prop('disabled', false);
    });
  });

  // FASE 2: Exportacao CSV (mantido por compatibilidade)
  $(document).on('click', '.dps-export-csv-btn', function(e){
    e.preventDefault();
    var btn = $(this);
    var date = btn.data('date');
    var view = btn.data('view');
    var defaultLabel = btn.data('default-label');

    if (!defaultLabel) {
      defaultLabel = $.trim(btn.text());
      btn.data('default-label', defaultLabel);
    }

    btn.prop('disabled', true).text('Exportando...');

    $.post(DPS_AG_Addon.ajax, {
      action: 'dps_agenda_export_csv',
      nonce: DPS_AG_Addon.nonce_export,
      date: date,
      view: view
    }, function(resp){
      if ( resp && resp.success ) {
        var content = atob(resp.data.content);
        var blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = resp.data.filename;
        link.click();
        URL.revokeObjectURL(link.href);
        btn.text('Exportado!');
        setTimeout(function(){
          btn.prop('disabled', false).text(defaultLabel);
        }, 2000);
      } else {
        showToast(resp.data ? resp.data.message : 'Erro ao exportar.', 'error');
        btn.prop('disabled', false).text(defaultLabel);
      }
    }).fail(function(){
      showToast('Erro ao exportar agenda.', 'error');
      btn.prop('disabled', false).text(defaultLabel);
    });
  });
  // FASE 2: Exportação PDF (abre página de impressão em nova janela)
  $(document).on('click', '.dps-export-pdf-btn', function(e){
    e.preventDefault();
    var btn = $(this);
    var date = btn.data('date') || '';
    var view = btn.data('view') || 'day';

    // Constrói URL para a página de impressão PDF
    var pdfUrl = DPS_AG_Addon.ajax +
      '?action=dps_agenda_export_pdf' +
      '&date=' + encodeURIComponent(date) +
      '&view=' + encodeURIComponent(view) +
      '&nonce=' + encodeURIComponent(DPS_AG_Addon.nonce_export_pdf);

    // Calcula dimensões responsivas para a janela
    var width = Math.min(950, window.screen.availWidth - 100);
    var height = Math.min(700, window.screen.availHeight - 100);

    // Abre em nova janela com dimensões responsivas
    window.open(pdfUrl, '_blank', 'width=' + width + ',height=' + height + ',scrollbars=yes');
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

    // XSS FIX: Escape dos valores que serão inseridos no HTML
    var modal = $('<div class="dps-reschedule-modal">' +
      '<div class="dps-reschedule-content">' +
        '<div class="dps-reschedule-header">' +
          '<h3 class="dps-reschedule-title">📅 ' + getMessage('reschedule_title', 'Reagendar') + '</h3>' +
          '<button type="button" class="dps-reschedule-close">&times;</button>' +
        '</div>' +
        '<div class="dps-reschedule-body">' +
          '<div class="dps-reschedule-field">' +
            '<label>' + getMessage('new_date', 'Nova data') + '</label>' +
            '<input type="date" id="dps-reschedule-date" value="' + escapeHtml(currentDate) + '" required>' +
          '</div>' +
          '<div class="dps-reschedule-field">' +
            '<label>' + getMessage('new_time', 'Novo horário') + '</label>' +
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
        // Aguarda 2 segundos para o usuário ver a mensagem de sucesso antes de recarregar
        setTimeout(function(){ location.reload(); }, 2000);
      } else {
        showToast(resp.data ? resp.data.message : getMessage('error', 'Erro ao reagendar.'), 'error');
        btn.prop('disabled', false).text(getMessage('save', 'Salvar'));
      }
    }).fail(function(){
      showToast(getMessage('error', 'Erro ao reagendar.'), 'error');
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
          showToast(getMessage('no_history', 'Sem histórico de alterações.'), 'info');
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

        // Exibe histórico em alert - formato de texto longo requer modal dedicado
        // TODO: Criar modal genérico de conteúdo para substituir alert em histórico
        alert(content);
      } else {
        showToast(resp.data ? resp.data.message : getMessage('error', 'Erro ao buscar histórico.'), 'error');
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
        showToast(message, 'error');
        // Reabilita botões em caso de erro
        row.find('.dps-taxidog-action-btn').prop('disabled', false).css('opacity', 1);
      }
    }).fail(function(){
      showToast('Erro de comunicação ao atualizar TaxiDog.', 'error');
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
        showToast(message, 'error');
        btn.prop('disabled', false).text('🔄 Reenviar');
      }
    }).fail(function(){
      showToast('Erro de comunicação ao reenviar link.', 'error');
      btn.prop('disabled', false).text('🔄 Reenviar');
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

  // Handler para dropdown de confirmação (Tab1)
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
      nonce: DPS_AG_Addon.nonce_confirmation
    }, function(resp){
      if (resp && resp.success) {
        // Atualiza a classe do dropdown para refletir o novo status
        select.removeClass('dps-dropdown--confirmed dps-dropdown--not-confirmed dps-dropdown--cancelled');
        if (confirmationStatus === 'confirmed') {
          select.addClass('dps-dropdown--confirmed');
        } else if (confirmationStatus === 'denied') {
          select.addClass('dps-dropdown--cancelled');
        } else {
          select.addClass('dps-dropdown--not-confirmed');
        }

        // Feedback visual
        row.css('background-color', '#d1fae5');
        setTimeout(function(){
          row.css('background-color', '');
        }, 1000);
      } else {
        showToast(resp && resp.data ? resp.data.message : 'Erro ao atualizar confirmação.', 'error');
      }
    }).fail(function(){
      showToast('Erro de comunicação.', 'error');
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
      nonce: DPS_AG_Addon.nonce_status
    }, function(resp){
      if (resp && resp.success) {
        select.data('current-status', status);
        if (resp.data && resp.data.version) {
          select.data('appt-version', resp.data.version);
        }

        // Atualiza a classe do dropdown
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

        // Atualiza classes da linha
        row.removeClass('status-pendente status-finalizado status-finalizado_pago status-cancelado')
           .addClass('status-' + status);

        // Feedback visual
        row.css('background-color', '#d1fae5');
        setTimeout(function(){
          row.css('background-color', '');
          // Se status é finalizado ou finalizado_pago, abre popup do checklist operacional
          if (status === 'finalizado' || status === 'finalizado_pago') {
            openChecklistPopup(apptId);
          } else {
            location.reload();
          }
        }, 800);
      } else {
        if (resp && resp.data && resp.data.error_code === 'version_conflict') {
          showToast(getMessage('versionConflict', 'Esse agendamento foi atualizado por outro usuário. Atualize a página para ver as alterações.'), 'warning');
        } else {
          showToast(resp && resp.data ? resp.data.message : 'Erro ao atualizar status.', 'error');
        }
        select.val(previous);
      }
    }).fail(function(){
      showToast('Erro de comunicação.', 'error');
      select.val(previous);
    }).always(function(){
      select.prop('disabled', false).removeClass('is-loading');
    });
  });

  // Handler para botão de popup de serviços (Tab1)
  $(document).on('click', '.dps-services-popup-btn', function(e){
    e.preventDefault();
    var btn = $(this);
    var apptId = btn.data('appt-id');

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

        // Monta o HTML do modal melhorado para funcionários
        var modalHtml = '<div class="dps-services-modal">' +
          '<div class="dps-services-modal-content">' +
            '<div class="dps-services-modal-header">' +
              '<h3 class="dps-services-modal-title">🐾 O Que Fazer</h3>' +
              '<button type="button" class="dps-services-modal-close" aria-label="Fechar">&times;</button>' +
            '</div>' +
            '<div class="dps-services-modal-body">';

        // Seção do pet (se disponível)
        if (pet && pet.name) {
          var petSizeInfo = getPetSizeInfo(pet.size);

          modalHtml += '<div class="dps-services-pet-info">' +
            '<span class="dps-services-pet-icon">' + petSizeInfo.icon + '</span>' +
            '<div class="dps-services-pet-details">' +
              '<span class="dps-services-pet-name">' + escapeHtml(pet.name) + '</span>';

          var petMeta = [];
          if (petSizeInfo.label) petMeta.push(petSizeInfo.label);
          if (pet.breed) petMeta.push(escapeHtml(pet.breed));
          if (pet.weight) petMeta.push(escapeHtml(pet.weight) + ' kg');

          if (petMeta.length > 0) {
            modalHtml += '<span class="dps-services-pet-meta">' + petMeta.join(' • ') + '</span>';
          }

          modalHtml += '</div></div>';
        }

        // Resumo rápido no topo
        if (services.length > 0 || totalDuration > 0) {
          modalHtml += '<div class="dps-services-summary">';
          modalHtml += '<div class="dps-services-summary-item"><span class="dps-services-summary-icon">📋</span><span class="dps-services-summary-value">' + services.length + '</span><span class="dps-services-summary-label">serviço' + (services.length !== 1 ? 's' : '') + '</span></div>';
          if (totalDuration > 0) {
            var hours = Math.floor(totalDuration / 60);
            var mins = totalDuration % 60;
            var durationText = hours > 0 ? hours + 'h' + (mins > 0 ? mins + 'min' : '') : mins + 'min';
            modalHtml += '<div class="dps-services-summary-item"><span class="dps-services-summary-icon">⏱️</span><span class="dps-services-summary-value">' + durationText + '</span><span class="dps-services-summary-label">estimado</span></div>';
          }
          modalHtml += '</div>';
        }

        // Lista de serviços
        if (services.length > 0) {
          modalHtml += '<div class="dps-services-checklist">';
          modalHtml += '<h4 class="dps-services-section-title">Serviços a Realizar</h4>';

          var total = 0;
          for (var i = 0; i < services.length; i++) {
            var srv = services[i];
            var price = parseFloat(srv.price) || 0;
            total += price;

            // Usa função helper para obter informações visuais do serviço
            var visualInfo = getServiceVisualInfo(srv);

            modalHtml += '<div class="dps-service-card ' + visualInfo.typeClass + '">' +
              '<div class="dps-service-card-header">' +
                '<span class="dps-service-card-icon">' + visualInfo.icon + '</span>' +
                '<div class="dps-service-card-info">' +
                  '<span class="dps-service-card-name">' + escapeHtml(srv.name) + '</span>' +
                  '<span class="dps-service-card-meta">';

            var cardMeta = [];
            if (visualInfo.typeLabel && srv.type !== 'padrao') cardMeta.push(visualInfo.typeLabel);
            if (srv.duration && srv.duration > 0) cardMeta.push(srv.duration + ' min');

            modalHtml += cardMeta.length > 0 ? cardMeta.join(' • ') : '';
            modalHtml += '</span></div>' +
                '<span class="dps-service-card-price">R$ ' + price.toFixed(2).replace('.', ',') + '</span>' +
              '</div>';

            // Descrição do serviço (instruções para o funcionário)
            if (srv.description && srv.description.trim()) {
              var escapedDesc = escapeHtml(srv.description).replace(/\n/g, '<br>');
              modalHtml += '<div class="dps-service-card-description">' +
                '<span class="dps-service-card-desc-icon">💡</span>' +
                '<span>' + escapedDesc + '</span>' +
              '</div>';
            }

            modalHtml += '</div>';
          }

          // Linha de total
          modalHtml += '<div class="dps-services-total-row">' +
            '<span class="dps-services-total-label">Total</span>' +
            '<span class="dps-services-total-value">R$ ' + total.toFixed(2).replace('.', ',') + '</span>' +
          '</div>';

          modalHtml += '</div>'; // .dps-services-checklist
        } else {
          modalHtml += '<div class="dps-services-empty">' +
            '<span class="dps-services-empty-icon">📋</span>' +
            '<span>Nenhum serviço registrado para este atendimento.</span>' +
          '</div>';
        }

        // Observações do cliente (importante para o funcionário)
        if (notes) {
          var escapedNotes = escapeHtml(notes).replace(/\n/g, '<br>');
          modalHtml += '<div class="dps-services-notes dps-services-notes-highlight">' +
            '<div class="dps-services-notes-title">' +
              '<span class="dps-services-notes-icon">⚠️</span>' +
              '<span>Observações do Cliente</span>' +
            '</div>' +
            '<div class="dps-services-notes-content">' + escapedNotes + '</div>' +
          '</div>';
        }

        modalHtml += '</div></div></div>'; // Fecha body, content, modal

        $('body').append(modalHtml);
      } else {
        showToast(resp && resp.data ? resp.data.message : 'Erro ao carregar serviços.', 'error');
      }
    }).fail(function(){
      showToast('Erro de comunicação.', 'error');
    }).always(function(){
      btn.prop('disabled', false);
    });
  });

  // Fechar modal de serviços
  $(document).on('click', '.dps-services-modal-close', function(){
    $('.dps-services-modal').remove();
  });

  $(document).on('click', '.dps-services-modal', function(e){
    if ($(e.target).hasClass('dps-services-modal')) {
      $(this).remove();
    }
  });

  // Handler para botão de popup de pagamento (Tab2)
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

    // Formata o número de WhatsApp (Brasil)
    // Remove todos os caracteres não numéricos
    var whatsappNumber = (clientPhone || '').replace(/\D/g, '');
    // Adiciona código do país apenas se necessário
    // 10 dígitos = DDD (2) + número fixo (8) - formato antigo celular
    // 11 dígitos = DDD (2) + número celular (9)
    // 12 dígitos = código país (2) + DDD (2) + número fixo (8)
    // 13 dígitos = código país (2) + DDD (2) + número celular (9)
    if (whatsappNumber.length === 10 || whatsappNumber.length === 11) {
      // Número brasileiro sem código do país
      whatsappNumber = '55' + whatsappNumber;
    } else if (whatsappNumber.length >= 12 && whatsappNumber.substring(0, 2) === '55') {
      // Número já tem código do país (mantém como está)
    } else if (whatsappNumber.length < 10) {
      // Número muito curto - mantém para o usuário corrigir
    }

    var whatsappUrl = 'https://wa.me/' + whatsappNumber + '?text=' + encodeURIComponent(whatsappMsg);

    // Monta o HTML do modal
    // XSS FIX: Escape de dados de texto inseridos no HTML
    // URLs em href não precisam de escapeHtml (já são URL-encoded), mas data-attributes sim
    var modalHtml = '<div class="dps-payment-modal">' +
      '<div class="dps-payment-modal-content">' +
        '<div class="dps-payment-modal-header">' +
          '<h3 class="dps-payment-modal-title">💳 Enviar Link de Pagamento</h3>' +
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
              '📱 Enviar por WhatsApp' +
            '</a>' +
            '<button type="button" class="dps-payment-action-btn dps-payment-action-btn--copy" data-link="' + escapeHtml(paymentLink) + '">' +
              '📋 Copiar Link de Pagamento' +
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
        btn.addClass('copied').text('✅ Link Copiado!');
        setTimeout(function(){
          btn.removeClass('copied').text('📋 Copiar Link de Pagamento');
        }, 2000);
      });
    } else {
      // Fallback para navegadores antigos
      var tempInput = $('<input>');
      $('body').append(tempInput);
      tempInput.val(link).select();
      document.execCommand('copy');
      tempInput.remove();
      btn.addClass('copied').text('✅ Link Copiado!');
      setTimeout(function(){
        btn.removeClass('copied').text('📋 Copiar Link de Pagamento');
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
      nonce: DPS_AG_Addon.nonce_taxidog
    }, function(resp){
      if (resp && resp.success) {
        // Feedback visual
        row.css('background-color', '#d1fae5');
        setTimeout(function(){
          row.css('background-color', '');
        }, 1000);
      } else {
        showToast(resp && resp.data ? resp.data.message : 'Erro ao atualizar TaxiDog.', 'error');
      }
    }).fail(function(){
      showToast('Erro de comunicação.', 'error');
    }).always(function(){
      select.prop('disabled', false).removeClass('is-loading');
    });
  });

  // Handler para botão de solicitar TaxiDog (Tab3)
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
      nonce: DPS_AG_Addon.nonce_taxidog
    }, function(resp){
      if (resp && resp.success) {
        // Recarrega a página para atualizar a UI
        location.reload();
      } else {
        showToast(resp && resp.data ? resp.data.message : 'Erro ao solicitar TaxiDog.', 'error');
        btn.prop('disabled', false).text('🚐 SOLICITAR TAXIDOG');
      }
    }).fail(function(){
      showToast('Erro de comunicação.', 'error');
      btn.prop('disabled', false).text('🚐 SOLICITAR TAXIDOG');
    });
  });

  /* ===========================
     CHECKLIST OPERACIONAL — POPUP
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
            '<h3 id="dps-checklist-modal-title">📋 ' + titleMsg + '</h3>' +
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
        var errorMsg = getMessage('checklistError', 'Não foi possível carregar o checklist.');
        $('.dps-checklist-modal-body').html('<p class="dps-checklist-modal-error">' + escapeHtml(errorMsg) + '</p>');
      }
    }).fail(function() {
      var errorMsg = getMessage('checklistError', 'Não foi possível carregar o checklist.');
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
      $('.dps-services-modal, .dps-payment-modal').remove();
      closeAppointmentModal();
    }
  });

})(jQuery);
