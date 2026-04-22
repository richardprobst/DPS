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

  var AGENDA_DIALOG_SELECTOR = '.dps-agenda-dialog-overlay';
  var AGENDA_DIALOG_FOCUSABLE_SELECTOR = 'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"]), input:not([disabled]), select:not([disabled]), textarea:not([disabled])';
  var agendaDialogCounter = 0;

  function getAgendaDialogFocusables(dialog) {
    return dialog.find(AGENDA_DIALOG_FOCUSABLE_SELECTOR).filter(':visible');
  }

  function trapAgendaDialogFocus(event, dialog) {
    var focusables = getAgendaDialogFocusables(dialog);
    if (!focusables.length) {
      event.preventDefault();
      dialog.find('.dps-agenda-dialog').trigger('focus');
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

  function closeAgendaDialog(target, reason) {
    var dialog = target ? $(target).closest(AGENDA_DIALOG_SELECTOR) : $(AGENDA_DIALOG_SELECTOR).last();
    if (!dialog.length) {
      return;
    }

    var dialogOptions = dialog.data('dialogOptions') || {};
    var trigger = dialog.data('dialogTrigger');

    if (typeof dialogOptions.onClose === 'function') {
      dialogOptions.onClose(reason || 'dismiss', dialog);
    }

    dialog.remove();

    if (!$(AGENDA_DIALOG_SELECTOR).length) {
      $('body').removeClass('dps-agenda-dialog-open');
    }

    if (trigger && trigger.length) {
      trigger.trigger('focus');
    }
  }

  function openAgendaDialog(options) {
    var settings = $.extend({
      title: '',
      eyebrow: '',
      subtitle: '',
      bodyHtml: '',
      footerHtml: '',
      size: 'medium',
      trigger: null,
      overlayClass: '',
      dialogClass: '',
      showClose: true,
      initialFocus: ''
    }, options || {});

    agendaDialogCounter += 1;

    var dialogId = 'dps-agenda-dialog-' + agendaDialogCounter;
    var titleId = dialogId + '-title';
    var bodyId = dialogId + '-body';
    var overlayClass = 'dps-agenda-dialog-overlay';
    var dialogClass = 'dps-agenda-dialog dps-agenda-dialog--' + settings.size;

    if (settings.overlayClass) {
      overlayClass += ' ' + settings.overlayClass;
    }

    if (settings.dialogClass) {
      dialogClass += ' ' + settings.dialogClass;
    }

    var headerHtml = '<div class="dps-agenda-dialog__header">' +
      '<div class="dps-agenda-dialog__heading">' +
        (settings.eyebrow ? '<span class="dps-agenda-dialog__eyebrow">' + escapeHtml(settings.eyebrow) + '</span>' : '') +
        '<h3 id="' + titleId + '" class="dps-agenda-dialog__title">' + escapeHtml(settings.title) + '</h3>' +
        (settings.subtitle ? '<p class="dps-agenda-dialog__subtitle">' + escapeHtml(settings.subtitle) + '</p>' : '') +
      '</div>' +
      (settings.showClose ? '<button type="button" class="dps-agenda-dialog__close" aria-label="' + escapeHtml(getMessage('close', 'Fechar')) + '">&times;</button>' : '') +
    '</div>';

    var footerHtml = settings.footerHtml ? '<div class="dps-agenda-dialog__footer">' + settings.footerHtml + '</div>' : '';
    var dialog = $('<div class="' + overlayClass + '" role="dialog" aria-modal="true" aria-labelledby="' + titleId + '" aria-describedby="' + bodyId + '">' +
      '<div class="' + dialogClass + '" role="document" tabindex="-1">' +
        headerHtml +
        '<div id="' + bodyId + '" class="dps-agenda-dialog__body">' + settings.bodyHtml + '</div>' +
        footerHtml +
      '</div>' +
    '</div>');

    dialog.data('dialogOptions', settings);
    dialog.data('dialogTrigger', settings.trigger && settings.trigger.length ? settings.trigger : $(document.activeElement));

    $('body').addClass('dps-agenda-dialog-open').append(dialog);

    if (typeof settings.onReady === 'function') {
      settings.onReady(dialog);
    }

    window.setTimeout(function() {
      var initialTarget = settings.initialFocus ? dialog.find(settings.initialFocus).filter(':visible').first() : $();
      var focusTarget = initialTarget.length ? initialTarget : getAgendaDialogFocusables(dialog).first();
      if (focusTarget.length) {
        focusTarget.trigger('focus');
      } else {
        dialog.find('.dps-agenda-dialog').trigger('focus');
      }
    }, 0);

    return dialog;
  }

  function showAgendaContentDialog(options) {
    return openAgendaDialog($.extend({
      footerHtml: '<button type="button" class="dps-agenda-dialog__action dps-agenda-dialog__action--secondary" data-dialog-close="true">' + escapeHtml(getMessage('close', 'Fechar')) + '</button>'
    }, options || {}));
  }

  function showAgendaConfirmDialog(options) {
    var settings = $.extend({
      title: getMessage('confirmAction', 'Confirmar acao'),
      subtitle: '',
      bodyHtml: '',
      confirmLabel: getMessage('confirmProceed', 'Confirmar'),
      cancelLabel: getMessage('cancel', 'Cancelar'),
      onConfirm: null,
      onCancel: null
    }, options || {});

    var dialog = openAgendaDialog({
      title: settings.title,
      subtitle: settings.subtitle,
      bodyHtml: settings.bodyHtml,
      size: settings.size || 'small',
      trigger: settings.trigger || null,
      overlayClass: settings.overlayClass || '',
      dialogClass: settings.dialogClass || '',
      footerHtml:
        '<button type="button" class="dps-agenda-dialog__action dps-agenda-dialog__action--secondary" data-dialog-action="cancel">' + escapeHtml(settings.cancelLabel) + '</button>' +
        '<button type="button" class="dps-agenda-dialog__action dps-agenda-dialog__action--primary" data-dialog-action="confirm">' + escapeHtml(settings.confirmLabel) + '</button>'
    });

    dialog.on('click', '[data-dialog-action="cancel"]', function() {
      if (typeof settings.onCancel === 'function') {
        settings.onCancel(dialog);
      }
      closeAgendaDialog(dialog, 'cancel');
    });

    dialog.on('click', '[data-dialog-action="confirm"]', function() {
      if (typeof settings.onConfirm === 'function') {
        settings.onConfirm(dialog);
      }
      closeAgendaDialog(dialog, 'confirm');
    });

    return dialog;
  }

  function focusOperationModal(dialog, focusTarget) {
    if (!dialog || !dialog.length) {
      return;
    }

    var selectorMap = {
      checklist: '.dps-checklist-panel .dps-checklist-btn, .dps-checklist-panel textarea, .dps-checklist-panel button',
      checkin: '.dps-checkin-stage[data-stage="checkin"] .dps-checkin-btn, .dps-checkin-stage[data-stage="checkin"] textarea, .dps-checkin-stage[data-stage="checkin"] input[type="checkbox"]',
      checkout: '.dps-checkin-stage[data-stage="checkout"] .dps-checkin-btn, .dps-checkin-stage[data-stage="checkout"] textarea, .dps-checkin-stage[data-stage="checkout"] input[type="checkbox"]'
    };
    var selector = selectorMap[focusTarget] || selectorMap.checklist;

    window.setTimeout(function() {
      var focusable = dialog.find(selector).filter(':visible:not(:disabled)').first();
      if (!focusable.length) {
        focusable = dialog.find(AGENDA_DIALOG_FOCUSABLE_SELECTOR).filter(':visible:not(:disabled)').first();
      }
      if (focusable.length) {
        focusable.trigger('focus');
      }
    }, 120);
  }

  function openOperationPanel(apptId, options) {
    var settings = $.extend({
      focusPanel: false,
      focusTarget: 'checklist',
      toast: ''
    }, options || {});
    var row = $('tr[data-appt-id="' + apptId + '"]').first();
    var trigger = row.find('[data-operation-focus="' + settings.focusTarget + '"]').first();

    if (!row.length) {
      return false;
    }

    var dialog = showAgendaContentDialog({
      title: getMessage('operationDialogTitle', 'OperaÃ§Ã£o do atendimento'),
      subtitle: getMessage('operationDialogSubtitle', 'Checklist, check-in e check-out em um fluxo Ãºnico.'),
      size: 'large',
      trigger: trigger.length ? trigger : null,
      bodyHtml: '<p class="dps-checklist-modal-loading">' + escapeHtml(getMessage('checklistLoading', 'Carregando checklist...')) + '</p>'
    });

    dialog.attr('data-operation-appt-id', apptId);

    $.post(DPS_AG_Addon.ajax, {
      action: 'dps_get_operation_panel',
      appointment_id: apptId,
      agenda_tab: getCurrentAgendaTab(),
      nonce: DPS_AG_Addon.nonce_checklist
    }, function(resp) {
      if (resp && resp.success && resp.data && resp.data.html) {
        dialog.find('.dps-agenda-dialog__body').html(resp.data.html);
        focusOperationModal(dialog, settings.focusTarget);
      } else {
        dialog.find('.dps-agenda-dialog__body').html('<p class="dps-checklist-modal-error">' + escapeHtml(getMessage('checklistError', 'Nao foi possivel carregar o checklist.')) + '</p>');
      }
    }).fail(function() {
      dialog.find('.dps-agenda-dialog__body').html('<p class="dps-checklist-modal-error">' + escapeHtml(getMessage('checklistError', 'Nao foi possivel carregar o checklist.')) + '</p>');
    });

    if (settings.toast) {
      showToast(settings.toast, 'info', 2200);
    }

    return true;
  }

  window.DPSAgendaDialog = {
    open: openAgendaDialog,
    close: closeAgendaDialog,
    content: showAgendaContentDialog,
    confirm: showAgendaConfirmDialog
  };

  window.DPSAgendaOperation = {
    open: openOperationPanel,
    focus: focusOperationModal
  };

  window.DPSAgendaShared = {
    getCurrentTab: getCurrentAgendaTab,
    replaceRow: replaceAgendaRow,
    showToast: showToast
  };

  $(document).on('click', '.dps-agenda-dialog__close, [data-dialog-close="true"]', function(event) {
    event.preventDefault();
    closeAgendaDialog($(this), 'dismiss');
  });

  $(document).on('click', AGENDA_DIALOG_SELECTOR, function(event) {
    if ($(event.target).is(AGENDA_DIALOG_SELECTOR)) {
      closeAgendaDialog($(this), 'backdrop');
    }
  });

  $(document).on('keydown', AGENDA_DIALOG_SELECTOR, function(event) {
    if (event.key === 'Tab') {
      trapAgendaDialogFocus(event, $(this));
    }
  });

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
          feedback.addClass('dps-status-feedback--error').text(getMessage('versionConflict', 'Esse agendamento foi atualizado por outro usuÃ¡rio. Atualize a pÃ¡gina para ver as alteraÃ§Ãµes.'));
          select.val(previous);
          return;
        }

        feedback.addClass('dps-status-feedback--error').text(getAjaxErrorMessage(response, getMessage('error', fallback)));
        if ( previous ) {
          select.val(previous);
        }
      }
    });
    // Evento para visualizar serviÃ§os de um agendamento
    // Usa modal customizado em vez de alert() para melhor UX
    $(document).on('click', '.dps-services-link', function(e){
      e.preventDefault();
      var link = $(this);
      var apptId = parseInt(link.data('appt-id'), 10) || 0;

      if (!apptId) {
        showToast('Agendamento invÃ¡lido.', 'error');
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
              var bodyHtml = '<div class="dps-agenda-dialog-list">';
              for ( var i = 0; i < services.length; i++ ) {
                var srv = services[i];
                bodyHtml += '<div class="dps-agenda-dialog-list__item">' +
                  '<span class="dps-agenda-dialog-list__label">' + escapeHtml(srv.name || 'Servico') + '</span>' +
                  '<strong class="dps-agenda-dialog-list__value">R$ ' + parseFloat(srv.price || 0).toFixed(2) + '</strong>' +
                '</div>';
              }
              bodyHtml += '</div>';
              showAgendaContentDialog({
                title: 'Servicos do atendimento',
                size: 'medium',
                bodyHtml: bodyHtml,
                trigger: link
              });
            }
          } else {
            // Lista vazia - exibe modal com mensagem apropriada se disponÃ­vel
            if ( typeof window.DPSServicesModal !== 'undefined' ) {
              window.DPSServicesModal.show([]);
            } else {
              showToast('Nenhum serviÃ§o encontrado para este agendamento.', 'info');
            }
          }
        } else {
          showToast(resp && resp.data ? resp.data.message : 'Erro ao buscar serviÃ§os.', 'error');
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

  // Abrir dialog de reagendamento
  $(document).on('click', '.dps-quick-reschedule', function(e){
    e.preventDefault();
    var btn = $(this);
    var apptId = btn.data('appt-id');
    var currentDate = btn.data('date');
    var currentTime = btn.data('time');

    openAgendaDialog({
      title: getMessage('rescheduleDialogTitle', getMessage('reschedule_title', 'Reagendar atendimento')),
      subtitle: 'Atualize data e horario sem sair da lista.',
      size: 'small',
      trigger: btn,
      bodyHtml:
        '<div class="dps-reschedule-fields">' +
          '<div class="dps-reschedule-field">' +
            '<label>' + escapeHtml(getMessage('new_date', 'Nova data')) + '</label>' +
            '<input type="date" id="dps-reschedule-date" value="' + escapeHtml(currentDate) + '" required>' +
          '</div>' +
          '<div class="dps-reschedule-field">' +
            '<label>' + escapeHtml(getMessage('new_time', 'Novo horario')) + '</label>' +
            '<input type="time" id="dps-reschedule-time" value="' + escapeHtml(currentTime) + '" required>' +
          '</div>' +
        '</div>',
      footerHtml:
        '<button type="button" class="dps-agenda-dialog__action dps-agenda-dialog__action--secondary" data-dialog-close="true">' + escapeHtml(getMessage('cancel', 'Cancelar')) + '</button>' +
        '<button type="button" class="dps-agenda-dialog__action dps-agenda-dialog__action--primary dps-reschedule-btn--save" data-appt-id="' + parseInt(apptId, 10) + '">' + escapeHtml(getMessage('save', 'Salvar')) + '</button>',
      initialFocus: '#dps-reschedule-date'
    });
  });

  // Salvar reagendamento
  $(document).on('click', '.dps-reschedule-btn--save', function(){
    var btn = $(this);
    var apptId = btn.data('appt-id');
    var dialog = btn.closest(AGENDA_DIALOG_SELECTOR);
    var newDate = dialog.find('#dps-reschedule-date').val();
    var newTime = dialog.find('#dps-reschedule-time').val();

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
        closeAgendaDialog(dialog, 'saved');
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

  // =========================================================================
  // FASE 5: HistÃ³rico de AlteraÃ§Ãµes
  // =========================================================================

  function buildHistoryDialogBody(history) {
    var html = '<div class="dps-agenda-history-list">';

    history.forEach(function(entry){
      var actionLabel = getActionLabel(entry.action);
      html += '<article class="dps-agenda-history-item">' +
        '<div class="dps-agenda-history-item__head">' +
          '<strong>' + escapeHtml(actionLabel) + '</strong>' +
          '<span>' + escapeHtml(entry.date || '') + '</span>' +
        '</div>' +
        '<div class="dps-agenda-history-item__meta">' + escapeHtml(entry.user || '') + '</div>';

      if ( entry.details ) {
        if ( entry.details.old_status && entry.details.new_status ) {
          html += '<div class="dps-agenda-history-item__detail">De ' + escapeHtml(entry.details.old_status) + ' para ' + escapeHtml(entry.details.new_status) + '</div>';
        }
        if ( entry.details.old_date && entry.details.new_date ) {
          html += '<div class="dps-agenda-history-item__detail">De ' + escapeHtml(entry.details.old_date + ' ' + (entry.details.old_time || '')) + '</div>';
          html += '<div class="dps-agenda-history-item__detail">Para ' + escapeHtml(entry.details.new_date + ' ' + (entry.details.new_time || '')) + '</div>';
        }
        if ( entry.details.message ) {
          html += '<div class="dps-agenda-history-item__detail">' + escapeHtml(entry.details.message) + '</div>';
        }
      }

      html += '</article>';
    });

    html += '</div>';
    return html;
  }

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
          showToast(getMessage('no_history', 'Sem historico de alteracoes.'), 'info');
          return;
        }

        showAgendaContentDialog({
          title: getMessage('historyDialogTitle', getMessage('history_title', 'Linha do tempo do atendimento')),
          size: 'medium',
          trigger: btn,
          bodyHtml: buildHistoryDialogBody(history),
          footerHtml: '<button type="button" class="dps-agenda-dialog__action dps-agenda-dialog__action--secondary" data-dialog-close="true">' + escapeHtml(getMessage('historyClose', getMessage('close', 'Fechar'))) + '</button>'
        });
      } else {
        showToast(resp.data ? resp.data.message : getMessage('error', 'Erro ao buscar historico.'), 'error');
      }
    }).fail(function(xhr){
      showToast(getAjaxErrorMessage(xhr, getMessage('error', 'Erro ao buscar historico.')), 'error');
    });
  });

  function getActionLabel(action){
    var labels = {
      'created': getMessage('action_created', 'Criado'),
      'status_change': getMessage('action_status_change', 'Status alterado'),
      'rescheduled': getMessage('action_rescheduled', 'Reagendado'),
      'checklist_update': getMessage('action_checklist_update', 'Checklist atualizado'),
      'checklist_rework': getMessage('action_checklist_rework', 'Retrabalho registrado'),
      'checkin_created': getMessage('action_checkin_created', 'Check-in registrado'),
      'checkin_updated': getMessage('action_checkin_updated', 'Check-in atualizado'),
      'checkout_created': getMessage('action_checkout_created', 'Check-out registrado'),
      'checkout_updated': getMessage('action_checkout_updated', 'Check-out atualizado')
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

    showAgendaConfirmDialog({
      title: getMessage('confirmAction', 'Confirmar acao'),
      subtitle: 'Cobranca',
      bodyHtml: '<p class="dps-agenda-dialog-copy">' + escapeHtml(getMessage('confirmResendPayment', 'Deseja reenviar o link de pagamento para este atendimento?')) + '</p>',
      trigger: btn,
      onConfirm: function() {
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
      }
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
              openOperationPanel(apptId, {
              focusPanel: true,
              toast: getMessage('operationPanelContinue', 'Continue no painel operacional.')
            });
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
              openOperationPanel(apptId, {
              focusPanel: true,
              toast: getMessage('operationPanelContinue', 'Continue no painel operacional.')
            });
            } else {
              location.reload();
            }
          }, 800);
        }
      } else {
        if (resp && resp.data && resp.data.error_code === 'version_conflict') {
          showToast(getMessage('versionConflict', 'Esse agendamento foi atualizado por outro usuÃ¡rio. Atualize a pÃ¡gina para ver as alteraÃ§Ãµes.'), 'warning');
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

  // Handler para botÃ£o de popup de serviÃ§os (Tab1)
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

    if (normalized === 'medio' || normalized === 'mÃ©dio' || normalized === 'medium') {
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
    var paymentLink = btn.data('payment-link');
    var clientPhone = btn.data('client-phone');
    var whatsappMsg = btn.data('whatsapp-msg');
    var clientName = btn.data('client-name');
    var petName = btn.data('pet-name');
    var totalValue = btn.data('total-value');

    var whatsappNumber = (clientPhone || '').replace(/\D/g, '');
    if (whatsappNumber.length === 10 || whatsappNumber.length === 11) {
      whatsappNumber = '55' + whatsappNumber;
    }

    var whatsappUrl = 'https://wa.me/' + whatsappNumber + '?text=' + encodeURIComponent(whatsappMsg);
    var bodyHtml = '<div class="dps-payment-info">' +
      '<div class="dps-payment-info-item"><span class="dps-payment-info-label">Cliente</span><span class="dps-payment-info-value">' + escapeHtml(clientName || '') + '</span></div>' +
      '<div class="dps-payment-info-item"><span class="dps-payment-info-label">Pet</span><span class="dps-payment-info-value">' + escapeHtml(petName || '') + '</span></div>' +
      '<div class="dps-payment-info-item"><span class="dps-payment-info-label">Valor</span><span class="dps-payment-info-value">R$ ' + escapeHtml(totalValue || '') + '</span></div>' +
      '</div>' +
      '<div class="dps-payment-actions">' +
        '<a href="' + whatsappUrl + '" target="_blank" rel="noopener noreferrer" class="dps-payment-action-btn dps-payment-action-btn--whatsapp">Enviar por WhatsApp</a>' +
        '<button type="button" class="dps-payment-action-btn dps-payment-action-btn--copy" data-link="' + escapeHtml(paymentLink || '') + '">' + escapeHtml(getMessage('copyPaymentLink', 'Copiar link de pagamento')) + '</button>' +
      '</div>';

    openAgendaDialog({
      title: getMessage('paymentDialogTitle', 'Cobranca do atendimento'),
      subtitle: 'Envie o link pelo canal preferido do cliente.',
      size: 'small',
      trigger: btn,
      bodyHtml: bodyHtml,
      footerHtml: '<button type="button" class="dps-agenda-dialog__action dps-agenda-dialog__action--secondary" data-dialog-close="true">' + escapeHtml(getMessage('close', 'Fechar')) + '</button>'
    });
  });

  // Copiar link de pagamento
  $(document).on('click', '.dps-payment-action-btn--copy', function(){
    var btn = $(this);
    var link = btn.data('link');
    var defaultLabel = getMessage('copyPaymentLink', 'Copiar link de pagamento');

    function markCopied() {
      btn.addClass('copied').text('Link copiado!');
      setTimeout(function(){
        btn.removeClass('copied').text(defaultLabel);
      }, 2000);
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(link).then(markCopied);
    } else {
      var tempInput = $('<input>');
      $('body').append(tempInput);
      tempInput.val(link).select();
      document.execCommand('copy');
      tempInput.remove();
      markCopied();
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

    showAgendaConfirmDialog({
      title: getMessage('confirmAction', 'Confirmar acao'),
      subtitle: 'TaxiDog',
      bodyHtml: '<p class="dps-agenda-dialog-copy">' + escapeHtml(getMessage('confirmTaxidogRequest', 'Deseja solicitar TaxiDog para este atendimento?')) + '</p>',
      trigger: btn,
      onConfirm: function() {
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
      }
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
    if (openOperationPanel(apptId, {
      focusPanel: true,
      toast: getMessage('operationPanelOpened', 'Painel operacional aberto.')
    })) {
      return;
    }

    var loadingMsg = escapeHtml(getMessage('checklistLoading', 'Carregando checklist...'));
    var dialog = showAgendaContentDialog({
      title: getMessage('checklistTitle', 'Checklist operacional'),
      size: 'large',
      bodyHtml: '<p class="dps-checklist-modal-loading">' + loadingMsg + '</p>'
    });

    $.post(DPS_AG_Addon.ajax, {
      action: 'dps_get_checklist_panel',
      appointment_id: apptId,
      nonce: DPS_AG_Addon.nonce_checklist
    }, function(resp) {
      if (resp && resp.success && resp.data && resp.data.html) {
        dialog.find('.dps-agenda-dialog__body').html(resp.data.html);
      } else {
        dialog.find('.dps-agenda-dialog__body').html('<p class="dps-checklist-modal-error">' + escapeHtml(getMessage('checklistError', 'Nao foi possivel carregar o checklist.')) + '</p>');
      }
    }).fail(function() {
      dialog.find('.dps-agenda-dialog__body').html('<p class="dps-checklist-modal-error">' + escapeHtml(getMessage('checklistError', 'Nao foi possivel carregar o checklist.')) + '</p>');
    });
  }

  $(document).on('keydown', function(e){
    if (e.key === 'Escape') {
      if ($(AGENDA_DIALOG_SELECTOR).length) {
        e.preventDefault();
        closeAgendaDialog($(AGENDA_DIALOG_SELECTOR).last(), 'escape');
        return;
      }
      if ($('.dps-services-modal').length) {
        closeServicesModal();
      }
    }
  });

})(jQuery);
