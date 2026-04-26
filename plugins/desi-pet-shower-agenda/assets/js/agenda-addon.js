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
      error: '',
      success: 'OK',
      warning: 'AL',
      info: 'IN'
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
      title: getMessage('confirmAction', 'Confirmar ação'),
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

  function getOperationModeConfig(mode, focusTarget) {
    var normalizedMode = mode || '';
    var normalizedFocus = focusTarget || '';

    if (!normalizedMode && (normalizedFocus === 'checkin' || normalizedFocus === 'checkout')) {
      normalizedMode = normalizedFocus;
    }

    if (!normalizedMode) {
      normalizedMode = 'full';
    }

    var configs = {
      full: {
        title: getMessage('operationDialogTitle', 'Opera\u00e7\u00e3o do atendimento'),
        subtitle: getMessage('operationDialogSubtitle', 'Checklist, check-in e check-out em um fluxo \u00fanico.')
      },
      checklist: {
        title: 'Checklist Operacional',
        subtitle: 'Revise as etapas operacionais deste atendimento.'
      },
      checkin: {
        title: 'Check-in',
        subtitle: 'Registre ou edite a entrada do pet no atendimento.'
      },
      checkout: {
        title: 'Check-out',
        subtitle: 'Registre ou edite a sa\u00edda do pet no atendimento.'
      },
      checkinout: {
        title: 'Check-in / Check-out',
        subtitle: 'Escolha entre check-in e check-out e acompanhe o que j\u00e1 foi registrado.'
      }
    };

    return $.extend({ mode: normalizedMode }, configs[normalizedMode] || configs.full);
  }

  function setOperationSectionVisibility(target, visible) {
    if (!target || !target.length) {
      return;
    }

    target.prop('hidden', !visible).attr('aria-hidden', visible ? 'false' : 'true');
  }

  function applyOperationPanelMode(dialog, mode, focusTarget) {
    if (!dialog || !dialog.length) {
      return;
    }

    var config = getOperationModeConfig(mode || dialog.data('operationMode'), focusTarget || dialog.data('operationFocusTarget'));
    var body = dialog.find('.dps-agenda-dialog__body');
    var shell = body.find('.dps-operation-modal-shell').first();
    var showChecklist = config.mode === 'full' || config.mode === 'checklist';
    var showCheckio = config.mode === 'full' || config.mode === 'checkinout' || config.mode === 'checkin' || config.mode === 'checkout';
    var showCheckinStage = config.mode !== 'checkout';
    var showCheckoutStage = config.mode !== 'checkin';

    dialog.data('operationMode', config.mode);
    dialog.data('operationFocusTarget', focusTarget || dialog.data('operationFocusTarget') || 'checklist');
    dialog.attr('data-operation-mode', config.mode);

    dialog.find('.dps-agenda-dialog__title').first().text(config.title);
    dialog.find('.dps-agenda-dialog__subtitle').first().text(config.subtitle);

    if (shell.length) {
      shell.removeClass('dps-operation-modal-shell--mode-full dps-operation-modal-shell--mode-checklist dps-operation-modal-shell--mode-checkin dps-operation-modal-shell--mode-checkout dps-operation-modal-shell--mode-checkinout')
        .addClass('dps-operation-modal-shell--mode-' + config.mode);
    }

    setOperationSectionVisibility(body.find('.dps-checklist-panel'), showChecklist);
    setOperationSectionVisibility(body.find('.dps-checkin-panel'), showCheckio);
    setOperationSectionVisibility(body.find('.dps-checkin-stage[data-stage="checkin"]'), showCheckio && showCheckinStage);
    setOperationSectionVisibility(body.find('.dps-checkin-stage[data-stage="checkout"]'), showCheckio && showCheckoutStage);

    setOperationSectionVisibility(body.find('.dps-operational-metric--checklist, .dps-operational-metric--rework'), showChecklist);
    setOperationSectionVisibility(body.find('.dps-operational-metric--checkin'), config.mode === 'full' || config.mode === 'checkin' || config.mode === 'checkinout' || config.mode === 'checkout');
    setOperationSectionVisibility(body.find('.dps-operational-metric--checkout, .dps-operational-metric--summary'), config.mode === 'full' || config.mode === 'checkinout' || config.mode === 'checkout');

    setOperationSectionVisibility(body.find('.dps-checkin-status-badge--out, .dps-checkin-status-badge--duration'), config.mode !== 'checkin');

    if (config.mode === 'checkin') {
      body.find('.dps-checkin-panel > h4').first().text('Check-in');
    } else if (config.mode === 'checkout') {
      body.find('.dps-checkin-panel > h4').first().text('Check-out');
    } else {
      body.find('.dps-checkin-panel > h4').first().text('Check-in / Check-out');
    }
  }

  function normalizeAjaxJsonResponse(resp) {
    if (typeof resp !== 'string') {
      return resp;
    }

    try {
      return JSON.parse(resp.replace(/^\uFEFF/, ''));
    } catch (e) {
      return null;
    }
  }

  function openOperationPanel(apptId, options) {
    var settings = $.extend({
      focusPanel: false,
      focusTarget: 'checklist',
      mode: '',
      toast: ''
    }, options || {});
    var modeConfig = getOperationModeConfig(settings.mode, settings.focusTarget);
    var row = $('tr[data-appt-id="' + apptId + '"], .dps-operational-card[data-appt-id="' + apptId + '"]').first();
    var trigger = row.find('[data-operation-focus="' + settings.focusTarget + '"]').first();

    if (!row.length) {
      return false;
    }

    var dialog = showAgendaContentDialog({
      title: modeConfig.title,
      subtitle: modeConfig.subtitle,
      size: 'large',
      trigger: trigger.length ? trigger : null,
      bodyHtml: '<p class="dps-checklist-modal-loading">' + escapeHtml(getMessage('checklistLoading', 'Carregando checklist...')) + '</p>'
    });

    dialog.attr('data-operation-appt-id', apptId);
    dialog.attr('data-operation-mode', modeConfig.mode);
    dialog.data('operationMode', modeConfig.mode);
    dialog.data('operationFocusTarget', settings.focusTarget);

    $.ajax({
      url: DPS_AG_Addon.ajax,
      method: 'POST',
      dataType: 'json',
      data: {
        action: 'dps_get_operation_panel',
        appointment_id: apptId,
        nonce: DPS_AG_Addon.nonce_checklist
      }
    }).done(function(resp) {
      resp = normalizeAjaxJsonResponse(resp);
      if (resp && resp.success && resp.data && resp.data.html) {
        dialog.find('.dps-agenda-dialog__body').html(resp.data.html);
        applyOperationPanelMode(dialog, modeConfig.mode, settings.focusTarget);
        focusOperationModal(dialog, settings.focusTarget);
      } else {
        dialog.find('.dps-agenda-dialog__body').html('<p class="dps-checklist-modal-error">' + escapeHtml(getMessage('checklistError', 'Não foi possível carregar o checklist.')) + '</p>');
      }
    }).fail(function(xhr) {
      var fallback = normalizeAjaxJsonResponse(xhr && xhr.responseText ? xhr.responseText : null);
      if (fallback && fallback.success && fallback.data && fallback.data.html) {
        dialog.find('.dps-agenda-dialog__body').html(fallback.data.html);
        applyOperationPanelMode(dialog, modeConfig.mode, settings.focusTarget);
        focusOperationModal(dialog, settings.focusTarget);
        return;
      }
      dialog.find('.dps-agenda-dialog__body').html('<p class="dps-checklist-modal-error">' + escapeHtml(getMessage('checklistError', 'Não foi possível carregar o checklist.')) + '</p>');
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
    focus: focusOperationModal,
    applyMode: applyOperationPanelMode
  };

  window.DPSAgendaShared = {
    replaceRow: replaceAgendaRow,
    replaceCard: replaceAgendaCard,
    refreshMarkup: refreshAgendaMarkup,
    syncInspector: syncOperationalInspector,
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
   * Obtém o label e ícone do porte do pet.
   * @param {string} size Porte do pet (pequeno, medio, grande, small, medium, large).
   * @return {object} Objeto com label e icon.
   */
  function getPetSizeInfo(size) {
    if (!size) return { label: '', icon: '' };

    var sizeLower = size.toLowerCase();
    var sizeMap = {
      'pequeno': { label: 'Pequeno', icon: '' },
      'small': { label: 'Pequeno', icon: '' },
      'medio': { label: 'Médio', icon: '🦮' },
      'médio': { label: 'Médio', icon: '🦮' },
      'medium': { label: 'Médio', icon: '🦮' },
      'grande': { label: 'Grande', icon: '🦺' },
      'large': { label: 'Grande', icon: '🦺' }
    };

    return sizeMap[sizeLower] || { label: '', icon: '' };
  }

  /**
   * Obtém informações visuais do serviço (ícone, classe, label).
   * @param {object} service Objeto do serviço com name, type, category, is_taxidog.
   * @return {object} Objeto com icon, typeClass, typeLabel.
   */
  function getServiceVisualInfo(service) {
    // Mapas de ícones por tipo e categoria
    var typeIcons = {
      'taxidog': { icon: '', typeClass: 'dps-service-type-taxidog', typeLabel: 'Transporte' },
      'extra': { icon: '✨', typeClass: 'dps-service-type-extra', typeLabel: 'Extra' },
      'package': { icon: '📦', typeClass: 'dps-service-type-pacote', typeLabel: 'Pacote' }
    };

    var categoryIcons = {
      'banho': '',
      'tosa': '✂',
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
   * @return {string} cone emoji.
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

    // cone padrão
    return '✂';
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

    // Evento de alteração de status

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
        nonce: DPS_AG_Addon.nonce_status
      }).done(function(resp){
        if ( resp && resp.success ) {
          select.data('current-status', status);
          if ( resp.data && resp.data.version ) {
            select.data('appt-version', resp.data.version);
          }

          if ( resp.data && resp.data.row_html ) {
            refreshAgendaMarkup(row, resp.data);
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
          feedback.addClass('dps-status-feedback--error').text(getMessage('versionConflict', 'Esse agendamento foi atualizado por outro usuário. Atualize a página para ver as alterações.'));
          select.val(previous);
          return;
        }

        feedback.addClass('dps-status-feedback--error').text(getAjaxErrorMessage(response, getMessage('error', fallback)));
        if ( previous ) {
          select.val(previous);
        }
      }
    });
    $(document).on('click', '.dps-services-link', function(e){
      e.preventDefault();
      fetchAndOpenServicesDialog($(this), parseInt($(this).data('appt-id'), 10) || 0);
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
        nonce: DPS_AG_Addon.nonce_quick_action
      }).done(function(resp){
        if ( resp && resp.success ) {
          if ( resp.data && resp.data.row_html ) {
            refreshAgendaMarkup(row, resp.data);
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
        showToast(getAjaxErrorMessage(response, 'Erro ao executar a ação. Tente novamente.'), 'error');
        row.find('.dps-quick-action-btn').prop('disabled', false).removeClass('is-loading');
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

      row.find('.dps-confirmation-btn').prop('disabled', true).addClass('is-loading');

      $.post(DPS_AG_Addon.ajax, {
        action: 'dps_agenda_update_confirmation',
        appt_id: apptId,
        confirmation_status: confirmationStatus,
        nonce: DPS_AG_Addon.nonce_confirmation
      }).done(function(resp){
        if ( resp && resp.success ) {
          if ( resp.data && resp.data.row_html ) {
            refreshAgendaMarkup(row, resp.data);
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
        showToast(getAjaxErrorMessage(response, 'Erro ao atualizar confirmação. Tente novamente.'), 'error');
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
  function replaceAgendaRow(row, rowHtml){
    var currentRow = row && row.jquery ? row : $(row);
    if ( ! currentRow.length || ! rowHtml ) {
      return currentRow;
    }

      var parsedRows = $($.parseHTML(String(rowHtml || '').trim(), document, true)).filter('tr');
    if ( ! parsedRows.length ) {
      parsedRows = $(rowHtml).filter('tr');
    }
    if ( ! parsedRows.length ) {
      return currentRow;
    }

    var apptId = currentRow.data('appt-id');
    var detailRow = currentRow.next('.dps-detail-row[data-appt-id="' + apptId + '"]');
    var keepExpanded = detailRow.length && detailRow.is(':visible');
    var keepSelected = currentRow.hasClass('is-selected');

    currentRow.replaceWith(parsedRows);
    if ( detailRow.length ) {
      detailRow.remove();
    }

    if ( keepExpanded ) {
      parsedRows.filter('.dps-detail-row').show();
      parsedRows.first().find('.dps-expand-panels-btn').attr('aria-expanded', 'true');
    }

    if ( keepSelected ) {
      parsedRows.first().addClass('is-selected');
    }

    parsedRows.addClass('dps-row-updated');
    setTimeout(function(){
      parsedRows.removeClass('dps-row-updated');
    }, 1500);

    return parsedRows.first();
  }

  function getCanonicalCard(apptId) {
    return $('.dps-operational-card[data-appt-id="' + apptId + '"]').first();
  }

  function replaceAgendaCard(apptId, cardHtml) {
    var currentCard = getCanonicalCard(apptId);
    if ( ! currentCard.length || ! cardHtml ) {
      return currentCard;
    }

    var parsedCard = $($.parseHTML(String(cardHtml || '').trim(), document, true)).filter('.dps-operational-card');
    if ( ! parsedCard.length ) {
      parsedCard = $(cardHtml).filter('.dps-operational-card');
    }
    if ( ! parsedCard.length ) {
      return currentCard;
    }

    var keepSelected = currentCard.hasClass('is-selected');
    currentCard.replaceWith(parsedCard);

    if ( keepSelected ) {
      parsedCard.addClass('is-selected');
    }

    parsedCard.addClass('dps-row-updated');
    setTimeout(function(){
      parsedCard.removeClass('dps-row-updated');
    }, 1500);

    return parsedCard.first();
  }

  function getOperationalTarget(element) {
    return $(element).closest('tr[data-appt-id], .dps-operational-card[data-appt-id]');
  }

  function getCanonicalRow(apptId) {
    return $('tr[data-appt-id="' + apptId + '"]').first();
  }

  function refreshAgendaMarkup(target, payload) {
    var currentTarget = target && target.jquery ? target : $(target);
    var apptId = (currentTarget.length ? currentTarget.data('appt-id') : '') || (payload && payload.appointment_id) || '';
    var updatedRow = currentTarget;
    var wasSelected = currentTarget.hasClass('is-selected');

    if ( apptId && ( ! updatedRow.length || !updatedRow.is('tr') ) ) {
      updatedRow = getCanonicalRow(apptId);
    }

    if ( apptId && !wasSelected ) {
      wasSelected = getCanonicalCard(apptId).hasClass('is-selected');
    }

    if ( payload && payload.row_html && updatedRow.length ) {
      updatedRow = replaceAgendaRow(updatedRow, payload.row_html);
    }

    if ( payload && payload.card_html && apptId ) {
      replaceAgendaCard(apptId, payload.card_html);
    }

    if ( $('.dps-agenda-operational-workspace[data-dps-agenda-mode="operacional"]').length ) {
      filterOperationalList();
    }

    if ( wasSelected && updatedRow.length ) {
      syncOperationalInspector(updatedRow);
    }

    return updatedRow;
  }

  function syncOperationalInspector(source) {
    var target = source && source.jquery ? source : $(source);
    if (!target.length || !$('.dps-operational-inspector').length) {
      return;
    }

    var fields = {
      pet: target.data('dps-pet') || '',
      tutor: target.data('dps-tutor') || '',
      stage: target.data('dps-stage') || '',
      stageClass: String(target.data('dps-stage-class') || 'confirm').replace(/[^a-z0-9_-]/gi, ''),
      service: target.data('dps-service') || '',
      payment: target.data('dps-payment') || '',
      logistics: target.data('dps-logistics') || '',
      notes: target.data('dps-notes') || '',
      progress: String(target.data('dps-progress') || '0') + '%',
      checkin: target.data('dps-checkin') || '',
      checkout: target.data('dps-checkout') || '',
      reworks: target.data('dps-reworks') || '',
      extras: target.data('dps-extra-actions') || 'Mais: serviços, checklist operacional, check-in/check-out, logística, reagendar e histórico.'
    };

    $('[data-inspector-field="pet"]').text(fields.pet);
    $('[data-inspector-field="tutor"]').text(fields.tutor);
    $('[data-inspector-field="stage"]').text(fields.stage);
    $('[data-inspector-field="service"]').text(fields.service);
    $('[data-inspector-field="payment"]').text(fields.payment);
    $('[data-inspector-field="logistics"]').text(fields.logistics);
    $('[data-inspector-field="notes"]').text(fields.notes);
    $('[data-inspector-field="progress"]').text(fields.progress);
    $('[data-inspector-field="checkin"]').text(fields.checkin);
    $('[data-inspector-field="checkout"]').text(fields.checkout);
    $('[data-inspector-field="reworks"]').text(fields.reworks);
    $('[data-inspector-field="extras"]').text(fields.extras);
    $('[data-inspector-progress-bar]').css('width', fields.progress);
    $('[data-inspector-action]').attr('data-appt-id', target.data('appt-id')).data('appt-id', target.data('appt-id'));
    $('[data-inspector-section="stage"]')
      .removeClass('dps-operational-inspector__section--stage-confirm dps-operational-inspector__section--stage-ready dps-operational-inspector__section--stage-service dps-operational-inspector__section--stage-done dps-operational-inspector__section--stage-danger')
      .addClass('dps-operational-inspector__section--stage-' + fields.stageClass);

    $('tr[data-appt-id], .dps-operational-card[data-appt-id]').removeClass('is-selected');
    $('tr[data-appt-id="' + target.data('appt-id') + '"], .dps-operational-card[data-appt-id="' + target.data('appt-id') + '"]').addClass('is-selected');
  }

  function updateOperationalPanelCount(panel, matchedCount, filter) {
    var counter = panel.find('[data-dps-operational-day-count]').first();
    if (!counter.length) {
      return;
    }

    if (filter === 'all') {
      counter.text(counter.attr('data-count-default') || counter.text());
      return;
    }

    var template = matchedCount === 1 ? counter.attr('data-count-singular') : counter.attr('data-count-plural');
    counter.text(String(template || '%d atendimentos no filtro').replace('%d', matchedCount));
  }

  function filterOperationalList() {
    var filter = $('.dps-agenda-filter-btn--active').data('agenda-filter') || 'all';
    var matchedIds = {};

    $('tr.dps-operational-row, .dps-operational-card').each(function(){
      var item = $(this);
      var hasTaxidog = String(item.attr('data-dps-taxidog') || item.data('dps-taxidog') || '0') === '1';
      var isLate = String(item.attr('data-dps-late') || item.data('dps-late') || (item.hasClass('is-late') ? '1' : '0')) === '1';
      var matchesFilter = filter === 'all' ||
        (filter === 'late' && isLate) ||
        (filter === 'taxidog' && hasTaxidog);

      item.toggleClass('is-filter-hidden', !matchesFilter);
      item.attr('aria-hidden', matchesFilter ? 'false' : 'true');

      if (matchesFilter && item.attr('data-appt-id')) {
        matchedIds[item.attr('data-appt-id')] = true;
      }
    });

    $('.dps-agenda-day-panel--operational').each(function(){
      var panel = $(this);
      var matchedItems = panel.find('tr.dps-operational-row, .dps-operational-card').filter(function(){
        return !$(this).hasClass('is-filter-hidden');
      });
      var panelMatchedIds = {};
      matchedItems.each(function(){
        var apptId = $(this).attr('data-appt-id');
        if (apptId) {
          panelMatchedIds[apptId] = true;
        }
      });
      var panelMatchedCount = Object.keys(panelMatchedIds).length;
      panel.prop('hidden', panelMatchedCount === 0);
      updateOperationalPanelCount(panel, panelMatchedCount, filter);
    });

    var hasVisibleItems = Object.keys(matchedIds).length > 0;
    var emptyState = $('[data-dps-operational-filter-empty]');
    if (emptyState.length) {
      var defaultKicker = emptyState.attr('data-empty-default-kicker') || 'Recorte operacional';
      var defaultTitle = emptyState.attr('data-empty-default-title') || 'Nenhum atendimento encontrado para este filtro.';
      var defaultMessage = emptyState.attr('data-empty-default-message') || 'Volte para Todos ou ajuste o filtro para continuar a operação.';
      var kicker = defaultKicker;
      var title = defaultTitle;
      var message = defaultMessage;

      if (filter === 'late') {
        kicker = emptyState.attr('data-empty-late-kicker') || kicker;
        title = emptyState.attr('data-empty-late-title') || title;
        message = emptyState.attr('data-empty-late-message') || message;
      } else if (filter === 'taxidog') {
        kicker = emptyState.attr('data-empty-taxidog-kicker') || kicker;
        title = emptyState.attr('data-empty-taxidog-title') || title;
        message = emptyState.attr('data-empty-taxidog-message') || message;
      }

      emptyState.find('[data-dps-operational-filter-empty-kicker]').text(kicker);
      emptyState.find('[data-dps-operational-filter-empty-title]').text(title);
      emptyState.find('[data-dps-operational-filter-empty-message]').text(message);
      emptyState.prop('hidden', hasVisibleItems);
    }

    if ( $('.dps-operational-inspector').length ) {
      $('.dps-operational-inspector').prop('hidden', !hasVisibleItems);
      if ( !hasVisibleItems ) {
        return;
      }

      var visibleItems = $('tr.dps-operational-row, .dps-operational-card').filter(function(){
        return !$(this).hasClass('is-filter-hidden') && $(this).is(':visible');
      });
      var selectedVisible = $('tr.dps-operational-row.is-selected:visible, .dps-operational-card.is-selected:visible').first();
      if ( selectedVisible.length ) {
        return;
      }

      var nextVisible = visibleItems.first();
      if ( nextVisible.length ) {
        syncOperationalInspector(nextVisible);
      }
    }
  }

  function syncOperationalFilterState() {
    $('.dps-agenda-filter-btn').each(function(){
      var btn = $(this);
      btn.attr('aria-pressed', btn.hasClass('dps-agenda-filter-btn--active') ? 'true' : 'false');
    });
  }

  if ( $('.dps-agenda-operational-workspace[data-dps-agenda-mode="operacional"]').length ) {
    syncOperationalFilterState();
    filterOperationalList();
  }

  $(document).on('click', 'tr.dps-operational-row, .dps-operational-card', function(e){
    if ($(e.target).closest('a, button, input, select, textarea, label').length) {
      return;
    }
    syncOperationalInspector($(this));
  });

  $(document).on('click', '.dps-agenda-filter-btn', function(){
    $('.dps-agenda-filter-btn').removeClass('dps-agenda-filter-btn--active');
    $(this).addClass('dps-agenda-filter-btn--active');
    syncOperationalFilterState();
    filterOperationalList();
  });

  function getWorkflowTarget(source, apptId) {
    var sourceTarget = getOperationalTarget(source);
    var row = getCanonicalRow(apptId);
    return row.length ? row : sourceTarget;
  }

  function refreshWorkflowTarget(target, payload) {
    var updatedTarget = refreshAgendaMarkup(target, payload);
    if (updatedTarget && updatedTarget.length) {
      syncOperationalInspector(updatedTarget);
    }
    return updatedTarget;
  }

  function triggerWorkflowReschedule(apptId, source) {
    var sourceTarget = getOperationalTarget(source);
    var row = getCanonicalRow(apptId);
    var card = getCanonicalCard(apptId);
    var dataSource = row.length ? row : (card.length ? card : sourceTarget);
    var date = dataSource.data('dps-date') || '';
    var time = dataSource.data('dps-time') || '';

    $('<button type="button" class="dps-quick-reschedule" data-appt-id="' + parseInt(apptId, 10) + '" data-date="' + escapeHtml(date) + '" data-time="' + escapeHtml(time) + '"></button>')
      .appendTo('body')
      .trigger('click')
      .remove();
  }

  function getOperationalAttr(target, name, fallback) {
    var value = target && target.length ? target.attr('data-dps-' + name) : '';
    if (value === undefined || value === null || value === '') {
      return fallback || '';
    }
    return value;
  }

  function getSecondaryActionData(apptId, source) {
    var sourceTarget = getOperationalTarget(source);
    var row = getCanonicalRow(apptId);
    var card = getCanonicalCard(apptId);
    var dataSource = sourceTarget.length ? sourceTarget : (card.length ? card : row);
    var hasTaxidog = getOperationalAttr(dataSource, 'taxidog', '0') === '1';

    return {
      apptId: parseInt(apptId, 10) || 0,
      date: getOperationalAttr(dataSource, 'date', ''),
      time: getOperationalAttr(dataSource, 'time', ''),
      pet: getOperationalAttr(dataSource, 'pet', ''),
      tutor: getOperationalAttr(dataSource, 'tutor', ''),
      stage: getOperationalAttr(dataSource, 'stage', ''),
      service: getOperationalAttr(dataSource, 'service', ''),
      payment: getOperationalAttr(dataSource, 'payment', ''),
      logistics: getOperationalAttr(dataSource, 'logistics', ''),
      progress: getOperationalAttr(dataSource, 'progress', '0'),
      checkin: getOperationalAttr(dataSource, 'checkin', ''),
      checkout: getOperationalAttr(dataSource, 'checkout', ''),
      reworks: getOperationalAttr(dataSource, 'reworks', ''),
      hasTaxidog: hasTaxidog,
      taxidogStatus: getOperationalAttr(dataSource, 'taxidog-status', hasTaxidog ? 'requested' : 'none'),
      taxidogLabel: getOperationalAttr(dataSource, 'taxidog-label', hasTaxidog ? 'TaxiDog solicitado' : 'Sem TaxiDog'),
      address: getOperationalAttr(dataSource, 'address', ''),
      mapUrl: getOperationalAttr(dataSource, 'map-url', ''),
      routeUrl: getOperationalAttr(dataSource, 'route-url', '')
    };
  }

  function getInspectorActionSource(apptId) {
    var row = getCanonicalRow(apptId);
    var card = getCanonicalCard(apptId);

    if (row.length && row.is(':visible')) {
      return row;
    }

    if (card.length && card.is(':visible')) {
      return card;
    }

    return row.length ? row : card;
  }

  function openInspectorPaymentSummary(apptId, source, trigger) {
    var data = getSecondaryActionData(apptId, source);
    var bodyHtml = '<div class="dps-payment-info">' +
      '<div class="dps-payment-info-item"><span class="dps-payment-info-label">Pet</span><span class="dps-payment-info-value">' + escapeHtml(data.pet || 'Atendimento') + '</span></div>' +
      '<div class="dps-payment-info-item"><span class="dps-payment-info-label">Tutor</span><span class="dps-payment-info-value">' + escapeHtml(data.tutor || 'Tutor não definido') + '</span></div>' +
      '<div class="dps-payment-info-item"><span class="dps-payment-info-label">Financeiro</span><span class="dps-payment-info-value">' + escapeHtml(data.payment || 'Financeiro em aberto') + '</span></div>' +
      '</div>';

    openAgendaDialog({
      title: 'Financeiro do atendimento',
      subtitle: data.pet || '',
      size: 'small',
      trigger: trigger && trigger.jquery ? trigger : $(trigger),
      dialogClass: 'dps-agenda-dialog--payment-summary',
      bodyHtml: bodyHtml,
      footerHtml: '<button type="button" class="dps-agenda-dialog__action dps-agenda-dialog__action--secondary" data-dialog-close="true">' + escapeHtml(getMessage('close', 'Fechar')) + '</button>'
    });
  }

  function buildSecondaryActionCard(action, title, meta, tone) {
    var toneClass = tone ? ' dps-secondary-action-card--' + tone : '';
    return '<button type="button" class="dps-secondary-action-card' + toneClass + '" data-secondary-action="' + escapeHtml(action) + '">' +
      '<span class="dps-secondary-action-card__title">' + escapeHtml(title) + '</span>' +
      '<small>' + escapeHtml(meta || '') + '</small>' +
    '</button>';
  }

  function formatOperationalDate(dateValue) {
    var match = String(dateValue || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!match) {
      return dateValue || '';
    }

    return match[3] + '/' + match[2] + '/' + match[1];
  }

  function formatOperationalDateTime(dateValue, timeValue) {
    var dateText = formatOperationalDate(dateValue);
    return [dateText, timeValue || ''].filter(Boolean).join(' ');
  }

  function buildSecondaryActionsBody(data) {
    var scheduleText = formatOperationalDateTime(data.date, data.time);
    var summary = [
      data.service || 'Sem serviços',
      data.payment || 'Financeiro em aberto',
      data.taxidogLabel || data.logistics || 'Sem logística'
    ].filter(Boolean).join(' | ');

    return '<div class="dps-secondary-action-panel">' +
      '<div class="dps-secondary-action-summary">' +
        '<strong>' + escapeHtml(data.pet || 'Atendimento') + '</strong>' +
        '<span>' + escapeHtml(summary) + '</span>' +
      '</div>' +
      '<div class="dps-secondary-action-grid dps-secondary-action-grid--complete">' +
        buildSecondaryActionCard('services', 'Serviços e observações', data.service || 'Abrir composição do atendimento', 'primary') +
        buildSecondaryActionCard('checklist', 'Checklist Operacional', 'Progresso ' + (data.progress || '0') + '%', 'primary') +
        buildSecondaryActionCard('checkinout', 'Check-in / Check-out', [data.checkin, data.checkout].filter(Boolean).join(' | ') || 'Registrar entrada e saída', 'primary') +
        buildSecondaryActionCard('logistics', 'Logística e TaxiDog', data.taxidogLabel || data.logistics || 'Mapa, rota e transporte', 'primary') +
        buildSecondaryActionCard('reschedule', 'Reagendar', scheduleText || 'Abrir reagendamento', '') +
        buildSecondaryActionCard('history', 'Histórico e logs', 'Linha do tempo auditável', '') +
      '</div>' +
    '</div>';
  }

  function isCheckioDone(label) {
    var value = String(label || '').toLowerCase();
    return !!value && value.indexOf('pendente') === -1 && value.indexOf('ainda') === -1;
  }

  function buildCheckioChoiceCard(stage, title, meta, disabled) {
    var disabledAttr = disabled ? ' disabled aria-disabled="true"' : '';
    var stateClass = disabled ? ' dps-checkio-choice-card--disabled' : '';

    return '<button type="button" class="dps-checkio-choice-card' + stateClass + '" data-checkio-choice="' + escapeHtml(stage) + '"' + disabledAttr + '>' +
      '<span class="dps-checkio-choice-card__title">' + escapeHtml(title) + '</span>' +
      '<small>' + escapeHtml(meta || '') + '</small>' +
    '</button>';
  }

  function buildCheckioChoiceBody(data) {
    var checkinDone = isCheckioDone(data.checkin);
    var checkoutDone = isCheckioDone(data.checkout);
    var checkinMeta = checkinDone ? 'J\u00e1 feito: ' + data.checkin : (data.checkin || 'Check-in pendente');
    var checkoutMeta = checkoutDone ? 'J\u00e1 feito: ' + data.checkout : (data.checkout || 'Check-out pendente');

    if (!checkinDone) {
      checkoutMeta = 'Liberado ap\u00f3s o check-in';
    }

    return '<div class="dps-checkio-choice-panel">' +
      '<div class="dps-secondary-action-summary">' +
        '<strong>' + escapeHtml(data.pet || 'Atendimento') + '</strong>' +
        '<span>' + escapeHtml([data.checkin || 'Check-in pendente', data.checkout || 'Check-out pendente'].filter(Boolean).join(' | ')) + '</span>' +
      '</div>' +
      '<div class="dps-checkio-choice-grid">' +
        buildCheckioChoiceCard('checkin', checkinDone ? 'Editar check-in' : 'Registrar check-in', checkinMeta, false) +
        buildCheckioChoiceCard('checkout', checkoutDone ? 'Editar check-out' : 'Registrar check-out', checkoutMeta, !checkinDone) +
      '</div>' +
    '</div>';
  }

  function openCheckioChoiceDialog(apptId, source) {
    var actionData = getSecondaryActionData(apptId, source);
    var dialog = showAgendaContentDialog({
      title: 'Check-in / Check-out',
      subtitle: 'Escolha qual registro deseja abrir. Altera\u00e7\u00f5es salvas continuam entrando nos logs do atendimento.',
      size: 'medium',
      trigger: source && source.length ? source : null,
      dialogClass: 'dps-agenda-dialog--checkio-choice',
      bodyHtml: buildCheckioChoiceBody(actionData)
    });

    dialog.on('click', '[data-checkio-choice]', function() {
      var choice = $(this).data('checkio-choice');
      if ($(this).is(':disabled')) {
        return;
      }

      closeAgendaDialog(dialog, 'checkio-choice');
      openOperationPanel(apptId, {
        focusPanel: true,
        focusTarget: choice,
        mode: choice,
        toast: choice === 'checkin' ? 'Check-in aberto.' : 'Check-out aberto.'
      });
    });
  }

  function getTaxidogNextActions(status) {
    var actions = {
      none: [
        { status: 'requested', label: 'Solicitar TaxiDog', tone: 'primary' }
      ],
      requested: [
        { status: 'driver_on_way', label: 'Motorista a caminho', tone: 'primary' },
        { status: 'none', label: 'Cancelar TaxiDog', tone: 'danger' }
      ],
      driver_on_way: [
        { status: 'pet_on_board', label: 'Pet a bordo', tone: 'primary' },
        { status: 'none', label: 'Cancelar TaxiDog', tone: 'danger' }
      ],
      pet_on_board: [
        { status: 'completed', label: 'Finalizar TaxiDog', tone: 'primary' },
        { status: 'none', label: 'Cancelar TaxiDog', tone: 'danger' }
      ],
      completed: []
    };

    return actions[status] || actions.none;
  }

  function buildLogisticsDialogBody(data) {
    var links = '';
    var taxidogActions = getTaxidogNextActions(data.taxidogStatus || 'none');
    var actionHtml = '';

    if (data.mapUrl) {
      links += '<a class="dps-secondary-logistics-link" href="' + escapeHtml(data.mapUrl) + '" target="_blank" rel="noopener noreferrer">Abrir mapa</a>';
    }

    if (data.routeUrl) {
      links += '<a class="dps-secondary-logistics-link dps-secondary-logistics-link--route" href="' + escapeHtml(data.routeUrl) + '" target="_blank" rel="noopener noreferrer">Abrir rota</a>';
    }

    if (!links) {
      links = '<p class="dps-secondary-logistics-empty">Sem mapa ou rota cadastrados para este atendimento.</p>';
    }

    taxidogActions.forEach(function(item){
      actionHtml += '<button type="button" class="dps-agenda-dialog__action dps-agenda-dialog__action--' + (item.tone === 'danger' ? 'danger' : 'secondary') + '" data-taxidog-status="' + escapeHtml(item.status) + '">' + escapeHtml(item.label) + '</button>';
    });

    if (!actionHtml) {
      actionHtml = '<p class="dps-secondary-logistics-empty">TaxiDog sem ações pendentes.</p>';
    }

    return '<div class="dps-secondary-logistics-panel">' +
      '<dl class="dps-secondary-logistics-facts">' +
        '<div><dt>Status TaxiDog</dt><dd>' + escapeHtml(data.taxidogLabel || 'Sem TaxiDog') + '</dd></div>' +
        '<div><dt>Endereço</dt><dd>' + escapeHtml(data.address || 'Sem endereço cadastrado') + '</dd></div>' +
      '</dl>' +
      '<div class="dps-secondary-logistics-links">' + links + '</div>' +
      '<div class="dps-secondary-logistics-actions">' + actionHtml + '</div>' +
    '</div>';
  }

  function postSecondaryTaxidogStatus(apptId, status, source) {
    var target = getWorkflowTarget(source, apptId);
    var payload = {
      appt_id: apptId,
      nonce: DPS_AG_Addon.nonce_taxidog
    };

    if (status === 'requested') {
      payload.action = 'dps_agenda_request_taxidog';
    } else {
      payload.action = 'dps_agenda_update_taxidog';
      payload.taxidog_status = status;
    }

    $.post(DPS_AG_Addon.ajax, payload, function(resp){
      if (resp && resp.success) {
        if (resp.data && (resp.data.row_html || resp.data.card_html)) {
          refreshWorkflowTarget(target, resp.data);
        }
        showToast(resp.data && resp.data.message ? resp.data.message : 'TaxiDog atualizado.', 'success', 1600);
      } else {
        showToast(resp && resp.data ? resp.data.message : 'Erro ao atualizar TaxiDog.', 'error');
      }
    }).fail(function(xhr){
      showToast(getAjaxErrorMessage(xhr, 'Erro de comunicação.'), 'error');
    });
  }

  function openSecondaryLogisticsDialog(apptId, source) {
    var data = getSecondaryActionData(apptId, source);
    var dialog = openAgendaDialog({
      title: 'Logística e TaxiDog',
      subtitle: data.pet || '',
      size: 'small',
      trigger: source && source.jquery ? source : $(source),
      dialogClass: 'dps-agenda-dialog--secondary-logistics',
      bodyHtml: buildLogisticsDialogBody(data),
      footerHtml: '<button type="button" class="dps-agenda-dialog__action dps-agenda-dialog__action--secondary" data-dialog-close="true">' + escapeHtml(getMessage('close', 'Fechar')) + '</button>'
    });

    dialog.on('click', '[data-taxidog-status]', function(){
      var btn = $(this);
      var status = btn.data('taxidog-status');
      var label = btn.text();

      closeAgendaDialog(dialog, 'taxidog-action');
      showAgendaConfirmDialog({
        title: 'Atualizar TaxiDog',
        subtitle: label,
        bodyHtml: '<p class="dps-agenda-dialog-copy">Confirmar alteração de TaxiDog para este atendimento?</p>',
        confirmLabel: getMessage('confirmProceed', 'Confirmar'),
        trigger: btn,
        onConfirm: function() {
          postSecondaryTaxidogStatus(apptId, status, source);
        }
      });
    });
  }

  function postWorkflowConfirmation(source, apptId, confirmationStatus, successMessage, afterSuccess) {
    var sourceBtn = source && source.jquery ? source : $(source);
    var target = getWorkflowTarget(sourceBtn, apptId);

    sourceBtn.prop('disabled', true).addClass('is-loading');

    $.post(DPS_AG_Addon.ajax, {
      action: 'dps_agenda_update_confirmation',
      appt_id: apptId,
      confirmation_status: confirmationStatus,
      nonce: DPS_AG_Addon.nonce_confirmation
    }, function(resp){
      if (resp && resp.success) {
        if (resp.data && (resp.data.row_html || resp.data.card_html)) {
          target = refreshWorkflowTarget(target, resp.data);
        }
        showToast(successMessage || (resp.data && resp.data.message ? resp.data.message : 'Confirmação atualizada.'), 'success', 1600);
        if (typeof afterSuccess === 'function') {
          afterSuccess(target);
        }
      } else {
        showToast(resp && resp.data ? resp.data.message : 'Erro ao atualizar confirmação.', 'error');
      }
    }).fail(function(xhr){
      showToast(getAjaxErrorMessage(xhr, 'Erro de comunicação.'), 'error');
    }).always(function(){
      sourceBtn.prop('disabled', false).removeClass('is-loading');
    });
  }

  function postWorkflowStatus(source, apptId, status, version, successMessage, afterSuccess) {
    var sourceBtn = source && source.jquery ? source : $(source);
    var target = getWorkflowTarget(sourceBtn, apptId);

    sourceBtn.prop('disabled', true).addClass('is-loading');

    $.post(DPS_AG_Addon.ajax, {
      action: 'dps_update_status',
      id: apptId,
      status: status,
      version: version,
      nonce: DPS_AG_Addon.nonce_status
    }, function(resp){
      if (resp && resp.success) {
        if (resp.data && (resp.data.row_html || resp.data.card_html)) {
          target = refreshWorkflowTarget(target, resp.data);
        }
        showToast(successMessage || (resp.data && resp.data.message ? resp.data.message : 'Status atualizado.'), 'success', 1600);
        if (typeof afterSuccess === 'function') {
          afterSuccess(target, resp.data || {});
        }
      } else if (resp && resp.data && resp.data.error_code === 'version_conflict') {
        showToast(getMessage('versionConflict', 'Esse agendamento foi atualizado por outro usuário. Atualize a página para ver as alterações.'), 'warning');
      } else {
        showToast(resp && resp.data ? resp.data.message : 'Erro ao atualizar status.', 'error');
      }
    }).fail(function(xhr){
      showToast(getAjaxErrorMessage(xhr, 'Erro de comunicação.'), 'error');
    }).always(function(){
      sourceBtn.prop('disabled', false).removeClass('is-loading');
    });
  }

  function openWorkflowReschedulePrompt(source, apptId) {
    openAgendaDialog({
      title: 'Reagendar atendimento?',
      subtitle: 'O atendimento ficará marcado como não confirmado até uma nova data ser salva.',
      size: 'small',
      dialogClass: 'dps-agenda-dialog--workflow',
      trigger: source,
      bodyHtml: '<p class="dps-workflow-dialog-copy">Escolha se deseja manter o registro como está ou abrir o reagendamento agora.</p>',
      footerHtml:
        '<button type="button" class="dps-agenda-dialog__action dps-agenda-dialog__action--secondary" data-dialog-close="true">Manter</button>' +
        '<button type="button" class="dps-agenda-dialog__action dps-agenda-dialog__action--primary" data-workflow-reschedule-confirm="true">Reagendar</button>'
    });

    $(document).off('click.dpsWorkflowReschedulePrompt').one('click.dpsWorkflowReschedulePrompt', '[data-workflow-reschedule-confirm]', function(){
      closeAgendaDialog($(this), 'workflow-reschedule');
      triggerWorkflowReschedule(apptId, source);
    });
  }

  function openWorkflowConfirmDialog(source, apptId) {
    var bodyHtml = '<p class="dps-workflow-dialog-copy">Defina se o atendimento está confirmado para seguir para a etapa de finalização.</p>' +
      '<div class="dps-workflow-action-grid">' +
        '<button type="button" class="dps-agenda-dialog__action dps-agenda-dialog__action--primary" data-workflow-confirm-choice="confirmed">Confirmar</button>' +
        '<button type="button" class="dps-agenda-dialog__action dps-agenda-dialog__action--secondary" data-workflow-confirm-choice="no_answer">Não confirmado</button>' +
      '</div>';

    openAgendaDialog({
      title: 'Confirmar atendimento',
      subtitle: 'A escolha atualiza a etapa operacional da Agenda.',
      size: 'small',
      dialogClass: 'dps-agenda-dialog--workflow',
      trigger: source,
      bodyHtml: bodyHtml,
      footerHtml: '<button type="button" class="dps-agenda-dialog__action dps-agenda-dialog__action--secondary" data-dialog-close="true">' + escapeHtml(getMessage('close', 'Fechar')) + '</button>'
    });

    $(document).off('click.dpsWorkflowConfirm').one('click.dpsWorkflowConfirm', '[data-workflow-confirm-choice]', function(){
      var choice = $(this).data('workflow-confirm-choice');
      closeAgendaDialog($(this), 'workflow-confirm');

      if (choice === 'confirmed') {
        postWorkflowConfirmation(source, apptId, 'confirmed', 'Atendimento confirmado.');
        return;
      }

      postWorkflowConfirmation(source, apptId, 'no_answer', 'Atendimento marcado como não confirmado.', function(){
        setTimeout(function(){
          openWorkflowReschedulePrompt(source, apptId);
        }, 120);
      });
    });
  }

  function openWorkflowCancelDialog(source, apptId, version) {
    var bodyHtml = '<p class="dps-workflow-dialog-copy">Escolha se este atendimento deve ser cancelado agora ou se a próxima ação é reagendar.</p>' +
      '<div class="dps-workflow-action-grid">' +
        '<button type="button" class="dps-agenda-dialog__action dps-agenda-dialog__action--danger" data-workflow-cancel-choice="cancel">Cancelar</button>' +
        '<button type="button" class="dps-agenda-dialog__action dps-agenda-dialog__action--primary" data-workflow-cancel-choice="reschedule">Reagendar</button>' +
      '</div>';

    openAgendaDialog({
      title: 'Cancelar ou reagendar',
      subtitle: 'Cancelar encerra o atendimento. Reagendar mantém o fluxo ativo em outra data.',
      size: 'small',
      dialogClass: 'dps-agenda-dialog--workflow',
      trigger: source,
      bodyHtml: bodyHtml,
      footerHtml: '<button type="button" class="dps-agenda-dialog__action dps-agenda-dialog__action--secondary" data-dialog-close="true">' + escapeHtml(getMessage('close', 'Fechar')) + '</button>'
    });

    $(document).off('click.dpsWorkflowCancel').one('click.dpsWorkflowCancel', '[data-workflow-cancel-choice]', function(){
      var choice = $(this).data('workflow-cancel-choice');
      closeAgendaDialog($(this), 'workflow-cancel');

      if (choice === 'cancel') {
        postWorkflowStatus(source, apptId, 'cancelado', version, 'Atendimento cancelado.');
        return;
      }

      triggerWorkflowReschedule(apptId, source);
    });
  }

  function openWorkflowCancelledDialog(source, apptId, version, reopenStatus) {
    var safeReopenStatus = /^(pendente|finalizado|finalizado_pago)$/.test(reopenStatus || '') ? reopenStatus : 'pendente';
    var bodyHtml = '<p class="dps-workflow-dialog-copy">Este atendimento está cancelado. Escolha se deseja corrigir o cancelamento ou reagendar para uma nova data.</p>' +
      '<div class="dps-workflow-action-grid">' +
        '<button type="button" class="dps-agenda-dialog__action dps-agenda-dialog__action--primary" data-workflow-cancelled-choice="reopen">Reabrir atendimento</button>' +
        '<button type="button" class="dps-agenda-dialog__action dps-agenda-dialog__action--secondary" data-workflow-cancelled-choice="reschedule">Reagendar</button>' +
      '</div>';

    openAgendaDialog({
      title: 'Atendimento cancelado',
      subtitle: 'Reabrir corrige um cancelamento acidental. Reagendar mantém a decisão registrada até a nova data ser salva.',
      size: 'small',
      dialogClass: 'dps-agenda-dialog--workflow',
      trigger: source,
      bodyHtml: bodyHtml,
      footerHtml: '<button type="button" class="dps-agenda-dialog__action dps-agenda-dialog__action--secondary" data-dialog-close="true">' + escapeHtml(getMessage('close', 'Fechar')) + '</button>'
    });

    $(document).off('click.dpsWorkflowCancelled').one('click.dpsWorkflowCancelled', '[data-workflow-cancelled-choice]', function(){
      var choice = $(this).data('workflow-cancelled-choice');
      closeAgendaDialog($(this), 'workflow-cancelled');

      if (choice === 'reopen') {
        postWorkflowStatus(source, apptId, safeReopenStatus, version, 'Atendimento reaberto.');
        return;
      }

      triggerWorkflowReschedule(apptId, source);
    });
  }

  function openWorkflowFinalizeDialog(source, apptId, version) {
    var bodyHtml = '<p class="dps-workflow-dialog-copy">Selecione como este atendimento deve sair da fila operacional.</p>' +
      '<div class="dps-workflow-action-grid dps-workflow-action-grid--status">' +
        '<button type="button" class="dps-agenda-dialog__action dps-agenda-dialog__action--secondary" data-workflow-status-choice="finalizado">Finalizado</button>' +
        '<button type="button" class="dps-agenda-dialog__action dps-agenda-dialog__action--primary" data-workflow-status-choice="finalizado_pago">Finalizado e pago</button>' +
        '<button type="button" class="dps-agenda-dialog__action dps-agenda-dialog__action--danger" data-workflow-status-choice="cancelado">Cancelado</button>' +
      '</div>';

    openAgendaDialog({
      title: 'Finalizar atendimento',
      subtitle: 'A decisão ajusta o status e libera a próxima ação visível na Agenda.',
      size: 'medium',
      dialogClass: 'dps-agenda-dialog--workflow',
      trigger: source,
      bodyHtml: bodyHtml,
      footerHtml: '<button type="button" class="dps-agenda-dialog__action dps-agenda-dialog__action--secondary" data-dialog-close="true">' + escapeHtml(getMessage('close', 'Fechar')) + '</button>'
    });

    $(document).off('click.dpsWorkflowStatus').one('click.dpsWorkflowStatus', '[data-workflow-status-choice]', function(){
      var status = $(this).data('workflow-status-choice');
      closeAgendaDialog($(this), 'workflow-status');

      if (status === 'cancelado') {
        openWorkflowCancelDialog(source, apptId, version);
        return;
      }

      var openChecklistAfterFinish = function(){
        setTimeout(function(){
          openOperationPanel(apptId, {
            focusPanel: true,
            focusTarget: 'checklist',
            mode: 'checklist',
            toast: getMessage('operationChecklistRequired', 'Revise o Checklist Operacional antes de encerrar o atendimento.')
          });
        }, 140);
      };

      if (status === 'finalizado_pago') {
        postWorkflowStatus(source, apptId, status, version, 'Atendimento finalizado e pago.', openChecklistAfterFinish);
        return;
      }

      postWorkflowStatus(source, apptId, status, version, 'Atendimento finalizado. Cobrança disponível.', openChecklistAfterFinish);
    });
  }

  $(document).on('click', '.dps-agenda-flow-action', function(e){
    var btn = $(this);
    if (btn.hasClass('dps-quick-reschedule')) {
      return;
    }

    e.preventDefault();

    var apptId = btn.data('appt-id');
    var step = btn.data('workflow-step') || '';
    var version = parseInt(btn.data('appt-version'), 10) || 1;

    if (step === 'confirm') {
      openWorkflowConfirmDialog(btn, apptId);
      return;
    }

    if (step === 'cancelled') {
      openWorkflowCancelledDialog(btn, apptId, version, btn.data('reopen-status') || 'pendente');
      return;
    }

    openWorkflowFinalizeDialog(btn, apptId, version);
  });

  $(document).on('click', '.dps-agenda-primary-confirm', function(e){
    e.preventDefault();
    var btn = $(this);
    var apptId = btn.data('appt-id');
    var row = getCanonicalRow(apptId);

    btn.prop('disabled', true).addClass('is-loading');
    $.post(DPS_AG_Addon.ajax, {
      action: 'dps_agenda_update_confirmation',
      appt_id: apptId,
      confirmation_status: 'confirmed',
      nonce: DPS_AG_Addon.nonce_confirmation
    }, function(resp){
      if (resp && resp.success) {
        if (resp.data && resp.data.row_html && row.length) {
          row = refreshAgendaMarkup(row, resp.data);
          syncOperationalInspector(row);
        }
        showToast(resp.data && resp.data.message ? resp.data.message : 'Atendimento confirmado.', 'success', 1600);
      } else {
        showToast(resp && resp.data ? resp.data.message : 'Erro ao confirmar atendimento.', 'error');
      }
    }).fail(function(xhr){
      showToast(getAjaxErrorMessage(xhr, 'Erro de comunicação.'), 'error');
    }).always(function(){
      btn.prop('disabled', false).removeClass('is-loading');
    });
  });

  $(document).on('click', '.dps-agenda-finalize-btn', function(e){
    e.preventDefault();
    var btn = $(this);
    var apptId = btn.data('appt-id');
    var version = parseInt(btn.data('appt-version'), 10) || 1;
    var row = getCanonicalRow(apptId);

    btn.prop('disabled', true).addClass('is-loading');
    $.post(DPS_AG_Addon.ajax, {
      action: 'dps_update_status',
      id: apptId,
      status: 'finalizado',
      version: version,
      nonce: DPS_AG_Addon.nonce_status
    }, function(resp){
      if (resp && resp.success) {
        if (resp.data && resp.data.row_html && row.length) {
          row = refreshAgendaMarkup(row, resp.data);
          syncOperationalInspector(row);
        }
        setTimeout(function(){
          openOperationPanel(apptId, {
            focusPanel: true,
            focusTarget: 'checklist',
            mode: 'checklist',
            toast: getMessage('operationPanelContinue', 'Continue o fluxo operacional no modal do atendimento.')
          });
        }, 120);
      } else if (resp && resp.data && resp.data.error_code === 'version_conflict') {
        showToast(getMessage('versionConflict', 'Esse agendamento foi atualizado por outro usuário. Atualize a página para ver as alterações.'), 'warning');
      } else {
        showToast(resp && resp.data ? resp.data.message : 'Erro ao finalizar atendimento.', 'error');
      }
    }).fail(function(xhr){
      showToast(getAjaxErrorMessage(xhr, 'Erro de comunicação.'), 'error');
    }).always(function(){
      btn.prop('disabled', false).removeClass('is-loading');
    });
  });

  $(document).on('click', '.dps-agenda-checkio-action', function(e){
    e.preventDefault();

    var btn = $(this);
    var apptId = btn.data('appt-id');
    var stage = btn.data('checkio-stage') || 'choice';

    if (stage === 'checkin') {
      openOperationPanel(apptId, {
        focusPanel: true,
        focusTarget: 'checkin',
        mode: 'checkin',
        toast: 'Check-in aberto.'
      });
      return;
    }

    openCheckioChoiceDialog(apptId, btn);
  });

  $(document).on('click', '.dps-agenda-secondary-actions', function(e){
    e.preventDefault();
    var btn = $(this);
    var apptId = btn.data('appt-id');
    var actionData = getSecondaryActionData(apptId, btn);
    var dialog = openAgendaDialog({
      title: 'Mais ações',
      subtitle: 'Hub de interações extras do atendimento.',
      size: 'medium',
      trigger: btn,
      dialogClass: 'dps-agenda-dialog--secondary-actions',
      bodyHtml: buildSecondaryActionsBody(actionData),
      footerHtml: '<button type="button" class="dps-agenda-dialog__action dps-agenda-dialog__action--secondary" data-dialog-close="true">' + escapeHtml(getMessage('close', 'Fechar')) + '</button>'
    });

    dialog.on('click', '[data-secondary-action]', function(){
      var action = $(this).data('secondary-action');
      closeAgendaDialog(dialog, 'secondary-action');
      if (action === 'services') {
        fetchAndOpenServicesDialog(btn, apptId);
      } else if (action === 'checklist') {
        openOperationPanel(apptId, {
          focusPanel: true,
          focusTarget: 'checklist',
          mode: 'checklist',
          toast: getMessage('operationChecklistOpened', 'Checklist Operacional aberto.')
        });
      } else if (action === 'checkinout') {
        openCheckioChoiceDialog(apptId, btn);
      } else if (action === 'logistics') {
        openSecondaryLogisticsDialog(apptId, btn);
      } else if (action === 'history') {
        $('<button type="button" class="dps-history-indicator" data-appt-id="' + apptId + '"></button>').appendTo('body').trigger('click').remove();
      } else if (action === 'reschedule') {
        triggerWorkflowReschedule(apptId, btn);
      }
    });
  });

  $(document).on('click', '.dps-operational-inspector__fact-action', function(e){
    e.preventDefault();

    var btn = $(this);
    var action = btn.data('inspector-action') || '';
    var apptId = parseInt(btn.attr('data-appt-id') || btn.data('appt-id'), 10) || 0;
    var source = getInspectorActionSource(apptId);
    var paymentBtn;

    if (!apptId) {
      showToast('Atendimento inválido.', 'error');
      return;
    }

    if (action === 'services') {
      fetchAndOpenServicesDialog(btn, apptId);
      return;
    }

    if (action === 'payment') {
      paymentBtn = getCanonicalRow(apptId).find('.dps-payment-popup-btn').first();
      if (!paymentBtn.length) {
        paymentBtn = getCanonicalCard(apptId).find('.dps-payment-popup-btn').first();
      }

      if (paymentBtn.length) {
        paymentBtn.trigger('click');
      } else {
        openInspectorPaymentSummary(apptId, source, btn);
      }
      return;
    }

    if (action === 'logistics') {
      openSecondaryLogisticsDialog(apptId, source.length ? source : btn);
    }
  });

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

  // Abrir dialog de reagendamento
  $(document).on('click', '.dps-quick-reschedule', function(e){
    e.preventDefault();
    var btn = $(this);
    var apptId = btn.data('appt-id');
    var currentDate = btn.data('date');
    var currentTime = btn.data('time');

    openAgendaDialog({
      title: getMessage('rescheduleDialogTitle', getMessage('reschedule_title', 'Reagendar atendimento')),
      subtitle: 'Atualize data e horário sem sair da lista.',
      size: 'small',
      trigger: btn,
      bodyHtml:
        '<div class="dps-reschedule-fields">' +
          '<div class="dps-reschedule-field">' +
            '<label>' + escapeHtml(getMessage('new_date', 'Nova data')) + '</label>' +
            '<input type="date" id="dps-reschedule-date" value="' + escapeHtml(currentDate) + '" required>' +
          '</div>' +
          '<div class="dps-reschedule-field">' +
            '<label>' + escapeHtml(getMessage('new_time', 'Novo horário')) + '</label>' +
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
        // Aguarda 2 segundos para o usuário ver a mensagem de sucesso antes de recarregar
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
  // FASE 5: Histórico de Alterações
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
        '<div class="dps-agenda-history-item__meta">' +
          '<span>' + escapeHtml(entry.user || '') + '</span>' +
          (entry.source_label ? '<span class="dps-agenda-history-item__source dps-agenda-history-item__source--' + escapeHtml(entry.source || 'system') + '">' + escapeHtml(entry.source_label) + '</span>' : '') +
        '</div>';

      if ( entry.details ) {
        if ( entry.details.field && (entry.details.old_value || entry.details.new_value) ) {
          html += '<div class="dps-agenda-history-item__detail"><strong>Campo alterado:</strong> ' + escapeHtml(entry.details.field) + '</div>';
          html += '<div class="dps-agenda-history-item__detail"><strong>Valor anterior:</strong> ' + escapeHtml(entry.details.old_value || '-') + '</div>';
          html += '<div class="dps-agenda-history-item__detail"><strong>Novo valor:</strong> ' + escapeHtml(entry.details.new_value || '-') + '</div>';
        }
        if ( entry.details.old_status && entry.details.new_status && !entry.details.field ) {
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
          showToast(getMessage('no_history', 'Sem histórico de alterações.'), 'info');
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
        showToast(resp.data ? resp.data.message : getMessage('error', 'Erro ao buscar histórico.'), 'error');
      }
    }).fail(function(xhr){
      showToast(getAjaxErrorMessage(xhr, getMessage('error', 'Erro ao buscar histórico.')), 'error');
    });
  });

  function getActionLabel(action){
    var labels = {
      'created': getMessage('action_created', 'Criado'),
      'status_change': getMessage('action_status_change', 'Status alterado'),
      'confirmation_change': getMessage('action_confirmation_change', 'Confirmação alterada'),
      'rescheduled': getMessage('action_rescheduled', 'Reagendado'),
      'taxidog_update': getMessage('action_taxidog_update', 'TaxiDog atualizado'),
      'taxidog_requested': getMessage('action_taxidog_requested', 'TaxiDog solicitado'),
      'payment_resend': getMessage('action_payment_resend', 'Cobrança reenviada'),
      'checklist_update': getMessage('action_checklist_update', 'Checklist atualizado'),
      'checklist_rework': getMessage('action_checklist_rework', 'Retrabalho registrado'),
      'checkin_created': getMessage('action_checkin_created', 'Check-in registrado'),
      'checkin_updated': getMessage('action_checkin_updated', 'Check-in atualizado'),
      'checkout_created': getMessage('action_checkout_created', 'Check-out registrado'),
      'checkout_updated': getMessage('action_checkout_updated', 'Check-out atualizado'),
      'pet_evidence_added': getMessage('action_pet_evidence_added', 'Evidência do pet adicionada'),
      'pet_evidence_removed': getMessage('action_pet_evidence_removed', 'Evidência do pet removida')
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

    row.find('.dps-taxidog-action-btn').prop('disabled', true).css('opacity', 0.5);

    $.post(DPS_AG_Addon.ajax, {
      action: 'dps_agenda_update_taxidog',
      appt_id: apptId,
      taxidog_status: taxidogStatus,
      nonce: DPS_AG_Addon.nonce_taxidog
    }, function(resp){
      if ( resp && resp.success ) {
        if ( resp.data && resp.data.row_html ) {
          refreshAgendaMarkup(row, resp.data);
        } else {
          location.reload();
        }
      } else {
        showToast(resp && resp.data ? resp.data.message : 'Erro ao atualizar TaxiDog.', 'error');
        row.find('.dps-taxidog-action-btn').prop('disabled', false).css('opacity', 1);
      }
    }).fail(function(xhr){
      showToast(getAjaxErrorMessage(xhr, 'Erro de comunicação ao atualizar TaxiDog.'), 'error');
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
      title: getMessage('confirmAction', 'Confirmar ação'),
      subtitle: 'Cobrança',
      bodyHtml: '<p class="dps-agenda-dialog-copy">' + escapeHtml(getMessage('confirmResendPayment', 'Deseja reenviar o link de pagamento para este atendimento?')) + '</p>',
      trigger: btn,
      onConfirm: function() {
        btn.prop('disabled', true).text('Reenviando...');

        $.post(DPS_AG_Addon.ajax, {
          action: 'dps_agenda_resend_payment',
          appt_id: apptId,
          nonce: DPS_AG_Addon.nonce_resend_payment
        }, function(resp){
          if (resp && resp.success) {
            if (resp.data && resp.data.row_html) {
              refreshAgendaMarkup(row, resp.data);
            } else {
              location.reload();
            }
          } else {
            showToast(resp && resp.data ? resp.data.message : 'Erro ao reenviar link.', 'error');
            btn.prop('disabled', false).text('Reenviar');
          }
        }).fail(function(xhr){
          showToast(getAjaxErrorMessage(xhr, 'Erro de comunicação ao reenviar link.'), 'error');
          btn.prop('disabled', false).text('Reenviar');
        });
      }
    });
  });

  // =========================================================================
  // FASE 7: Novos Handlers para Dropdowns e Popups
  // =========================================================================

  // Handler para dropdown de confirmação do atendimento

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
        if (resp.data && resp.data.row_html) {
          row = refreshAgendaMarkup(row, resp.data);
          showToast(resp.data.message || 'Confirmação atualizada.', 'success', 1600);
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
        showToast(resp && resp.data ? resp.data.message : 'Erro ao atualizar confirmação.', 'error');
      }
    }).fail(function(xhr){
      showToast(getAjaxErrorMessage(xhr, 'Erro de comunicação.'), 'error');
    }).always(function(){
      select.prop('disabled', false).removeClass('is-loading');
    });
  });

  // Handler para dropdown de status operacional

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

        if (resp.data && resp.data.row_html) {
          row = refreshAgendaMarkup(row, resp.data);
          if (status === 'finalizado' || status === 'finalizado_pago') {
            setTimeout(function(){
              openOperationPanel(apptId, {
              focusPanel: true,
              focusTarget: 'checklist',
              mode: 'checklist',
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
              focusTarget: 'checklist',
              mode: 'checklist',
              toast: getMessage('operationPanelContinue', 'Continue no painel operacional.')
            });
            } else {
              location.reload();
            }
          }, 800);
        }
      } else {
        if (resp && resp.data && resp.data.error_code === 'version_conflict') {
          showToast(getMessage('versionConflict', 'Esse agendamento foi atualizado por outro usuário. Atualize a página para ver as alterações.'), 'warning');
        } else {
          showToast(resp && resp.data ? resp.data.message : 'Erro ao atualizar status.', 'error');
        }
        select.val(previous);
      }
    }).fail(function(xhr){
      showToast(getAjaxErrorMessage(xhr, 'Erro de comunicação.'), 'error');
      select.val(previous);
    }).always(function(){
      select.prop('disabled', false).removeClass('is-loading');
    });
  });

  // Handler para serviços no dialog canônico DPS Signature
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

    return { icon: '\u2702\uFE0F', typeClass: 'dps-service-type-padrao', typeLabel: 'Servi\u00e7o' };
  }

  function getPetSizeLabel(size) {
    var normalized = String(size || '').toLowerCase();

    if (normalized === 'pequeno' || normalized === 'small') {
      return 'Pequeno';
    }

    if (normalized === 'medio' || normalized === 'médio' || normalized === 'medium') {
      return 'M\u00e9dio';
    }

    if (normalized === 'grande' || normalized === 'large') {
      return 'Grande';
    }

    return '';
  }

  function buildServicesDialogBody(services, notes, pet, totalDuration) {
    var total = 0;
    var durationText = formatServiceDuration(totalDuration);
    var bodyHtml = '<div class="dps-services-dialog">';

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

      bodyHtml += '<section class="dps-services-dialog__section dps-services-dialog__section--summary">' +
        '<div class="dps-services-dialog__hero">' +
          '<span class="dps-services-dialog__eyebrow">Atendimento</span>' +
          '<strong class="dps-services-dialog__pet">' + escapeHtml(pet.name) + '</strong>';

      if (petMeta.length > 0) {
        bodyHtml += '<span class="dps-services-dialog__pet-meta">' + escapeHtml(petMeta.join(' \u2022 ')) + '</span>';
      }

      bodyHtml += '</div>';
    } else {
      bodyHtml += '<section class="dps-services-dialog__section dps-services-dialog__section--summary">' +
        '<div class="dps-services-dialog__hero">' +
          '<span class="dps-services-dialog__eyebrow">Atendimento</span>' +
          '<strong class="dps-services-dialog__pet">Servi\u00e7os do atendimento</strong>' +
        '</div>';
    }

    if (services.length > 0 || durationText) {
      bodyHtml += '<div class="dps-services-dialog__stats">';
      bodyHtml += '<div class="dps-services-dialog__stat">' +
        '<span class="dps-services-dialog__stat-label">Servi\u00e7os</span>' +
        '<strong class="dps-services-dialog__stat-value">' + services.length + '</strong>' +
      '</div>';

      if (durationText) {
        bodyHtml += '<div class="dps-services-dialog__stat">' +
          '<span class="dps-services-dialog__stat-label">Tempo estimado</span>' +
          '<strong class="dps-services-dialog__stat-value">' + escapeHtml(durationText) + '</strong>' +
        '</div>';
      }

      bodyHtml += '</div>';
    }

    bodyHtml += '</section>';

    if (services.length > 0) {
      bodyHtml += '<section class="dps-services-dialog__section">' +
        '<span class="dps-services-dialog__section-label">Ordem de execu\u00e7\u00e3o</span>' +
        '<div class="dps-services-dialog__list">';

      for (var i = 0; i < services.length; i++) {
        var srv = services[i] || {};
        var price = parseFloat(srv.price) || 0;
        var visualInfo = getServiceCardVisual(srv);
        var serviceName = String(srv.name || 'Servi\u00e7o');
        var cardMeta = [];

        total += price;

        if (srv.duration && parseInt(srv.duration, 10) > 0) {
          cardMeta.push(String(srv.duration) + ' min');
        }

        bodyHtml += '<article class="dps-services-dialog__item ' + visualInfo.typeClass + '">' +
          '<div class="dps-services-dialog__item-main">' +
            '<span class="dps-services-dialog__item-type">Servi\u00e7o</span>' +
            '<strong class="dps-services-dialog__item-name">' + escapeHtml(serviceName) + '</strong>';

        if (cardMeta.length) {
          bodyHtml += '<span class="dps-services-dialog__item-meta">' + escapeHtml(cardMeta.join(' \u2022 ')) + '</span>';
        }

        if (srv.description && String(srv.description).trim()) {
          bodyHtml += '<p class="dps-services-dialog__item-description">' + escapeHtml(String(srv.description)).replace(/\n/g, '<br>') + '</p>';
        }

        bodyHtml += '</div>' +
          '<div class="dps-services-dialog__item-side">';

        if (visualInfo.typeLabel && srv.type !== 'padrao') {
          bodyHtml += '<span class="dps-services-dialog__item-tag">' + escapeHtml(visualInfo.typeLabel) + '</span>';
        }

        bodyHtml += '<strong class="dps-services-dialog__item-price">R$ ' + price.toFixed(2).replace('.', ',') + '</strong>' +
          '</div>' +
        '</article>';
      }

      bodyHtml += '<div class="dps-services-dialog__total">' +
        '<span class="dps-services-dialog__total-label">Total previsto</span>' +
        '<strong class="dps-services-dialog__total-value">R$ ' + total.toFixed(2).replace('.', ',') + '</strong>' +
      '</div>' +
      '</div>' +
      '</section>';
    } else {
      bodyHtml += '<section class="dps-services-dialog__section dps-services-dialog__section--empty">' +
        '<span class="dps-services-dialog__section-label">Ordem de execu\u00e7\u00e3o</span>' +
        '<p class="dps-services-dialog__empty">Nenhum servi\u00e7o registrado para este atendimento.</p>' +
      '</section>';
    }

    if (notes && String(notes).trim()) {
      bodyHtml += '<section class="dps-services-dialog__section dps-services-dialog__section--notes">' +
        '<span class="dps-services-dialog__section-label">Observa\u00e7\u00f5es do cliente</span>' +
        '<div class="dps-services-dialog__notes">' +
          '<p class="dps-services-dialog__notes-copy">' + escapeHtml(String(notes)).replace(/\n/g, '<br>') + '</p>' +
        '</div>' +
      '</section>';
    }

    bodyHtml += '</div>';

    return bodyHtml;
  }

  function openServicesDialog(trigger, services, notes, pet, totalDuration) {
    if (trigger && trigger.length) {
      trigger.attr('aria-expanded', 'true');
    }

    showAgendaContentDialog({
      title: 'Servi\u00e7os do atendimento',
      subtitle: 'Mesmo shell operacional da Agenda, com serviços, tempo e observações do atendimento.',
      size: 'large',
      trigger: trigger,
      dialogClass: 'dps-agenda-dialog--services',
      bodyHtml: buildServicesDialogBody(services, notes, pet, totalDuration),
      initialFocus: '.dps-agenda-dialog__close',
      onClose: function() {
        if (trigger && trigger.length) {
          trigger.attr('aria-expanded', 'false');
        }
      }
    });
  }

  function fetchAndOpenServicesDialog(trigger, apptId) {
    if (trigger && trigger.length) {
      trigger.attr('aria-haspopup', 'dialog');
      if (!trigger.attr('aria-expanded')) {
        trigger.attr('aria-expanded', 'false');
      }
      trigger.prop('disabled', true);
    }

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

        openServicesDialog(trigger, services, notes, pet, totalDuration);
      } else {
        showToast(resp && resp.data ? resp.data.message : 'Erro ao carregar serviços.', 'error');
      }
    }).fail(function(xhr){
      showToast(getAjaxErrorMessage(xhr, 'Erro de comunicação.'), 'error');
    }).always(function(){
      if (trigger && trigger.length) {
        trigger.prop('disabled', false);
      }
    });
  }

  $(document).on('click', '.dps-services-popup-btn', function(e) {
    e.preventDefault();
    var btn = $(this);
    var apptId = parseInt(btn.data('appt-id'), 10) || 0;

    if (!apptId) {
      showToast('Agendamento inv\u00e1lido.', 'error');
      return;
    }

    fetchAndOpenServicesDialog(btn, apptId);
  });

  if (!window.__dpsAgendaServicesCaptureBound) {
    document.addEventListener('click', function(event) {
      var target = event.target && event.target.closest ? event.target.closest('.dps-services-link, .dps-services-popup-btn') : null;
      var trigger;
      var apptId;

      if (!target) {
        return;
      }

      trigger = $(target);
      apptId = parseInt(trigger.data('appt-id'), 10) || 0;

      if (!apptId) {
        return;
      }

      event.preventDefault();
      event.stopImmediatePropagation();

      fetchAndOpenServicesDialog(trigger, apptId);
    }, true);

    window.__dpsAgendaServicesCaptureBound = true;
  }

  $(document).on('click', '.dps-payment-popup-btn', function(e){
    e.preventDefault();
    var btn = $(this);
    var paymentLink = btn.data('payment-link');
    var clientPhone = btn.data('client-phone');
    var whatsappMsg = btn.data('whatsapp-msg');
    var clientName = btn.data('client-name');
    var petName = btn.data('pet-name');
    var totalValue = btn.data('total-value');

    var whatsappNumber = String(clientPhone || '').replace(/\D/g, '');
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
      title: getMessage('paymentDialogTitle', 'Cobrança do atendimento'),
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

  // Handler para dropdown de TaxiDog

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
        if (resp.data && resp.data.row_html) {
          row = refreshAgendaMarkup(row, resp.data);
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
      showToast(getAjaxErrorMessage(xhr, 'Erro de comunicação.'), 'error');
    }).always(function(){
      select.prop('disabled', false).removeClass('is-loading');
    });
  });

  // Handler para botão de solicitar TaxiDog

  $(document).on('click', '.dps-taxidog-request-btn', function(e){
    e.preventDefault();
    var btn = $(this);
    var apptId = btn.data('appt-id');
    var row = btn.closest('tr');

    showAgendaConfirmDialog({
      title: getMessage('confirmAction', 'Confirmar ação'),
      subtitle: 'TaxiDog',
      bodyHtml: '<p class="dps-agenda-dialog-copy">' + escapeHtml(getMessage('confirmTaxidogRequest', 'Deseja solicitar TaxiDog para este atendimento?')) + '</p>',
      trigger: btn,
      onConfirm: function() {
        btn.prop('disabled', true).text('Solicitando...');

        $.post(DPS_AG_Addon.ajax, {
          action: 'dps_agenda_request_taxidog',
          appt_id: apptId,
          nonce: DPS_AG_Addon.nonce_taxidog
        }, function(resp){
          if (resp && resp.success) {
            if (resp.data && resp.data.row_html) {
              row = refreshAgendaMarkup(row, resp.data);
              showToast(resp.data.message || 'TaxiDog solicitado com sucesso!', 'success', 1600);
            } else {
              location.reload();
            }
          } else {
            showToast(resp && resp.data ? resp.data.message : 'Erro ao solicitar TaxiDog.', 'error');
            btn.prop('disabled', false).text('Solicitar TaxiDog');
          }
        }).fail(function(xhr){
          showToast(getAjaxErrorMessage(xhr, 'Erro de comunicação.'), 'error');
          btn.prop('disabled', false).text('Solicitar TaxiDog');
        });
      }
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
    if (openOperationPanel(apptId, {
      focusPanel: true,
      focusTarget: 'checklist',
      mode: 'checklist',
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

    $.ajax({
      url: DPS_AG_Addon.ajax,
      method: 'POST',
      dataType: 'json',
      data: {
        action: 'dps_get_checklist_panel',
        appointment_id: apptId,
        nonce: DPS_AG_Addon.nonce_checklist
      }
    }).done(function(resp) {
      resp = normalizeAjaxJsonResponse(resp);
      if (resp && resp.success && resp.data && resp.data.html) {
        dialog.find('.dps-agenda-dialog__body').html(resp.data.html);
      } else {
        dialog.find('.dps-agenda-dialog__body').html('<p class="dps-checklist-modal-error">' + escapeHtml(getMessage('checklistError', 'Não foi possível carregar o checklist.')) + '</p>');
      }
    }).fail(function(xhr) {
      var fallback = normalizeAjaxJsonResponse(xhr && xhr.responseText ? xhr.responseText : null);
      if (fallback && fallback.success && fallback.data && fallback.data.html) {
        dialog.find('.dps-agenda-dialog__body').html(fallback.data.html);
        return;
      }
      dialog.find('.dps-agenda-dialog__body').html('<p class="dps-checklist-modal-error">' + escapeHtml(getMessage('checklistError', 'Não foi possível carregar o checklist.')) + '</p>');
    });
  }

  $(document).on('keydown', function(e){
    if (e.key === 'Escape') {
      if ($(AGENDA_DIALOG_SELECTOR).length) {
        e.preventDefault();
        closeAgendaDialog($(AGENDA_DIALOG_SELECTOR).last(), 'escape');
        return;
      }
    }
  });

})(jQuery);
