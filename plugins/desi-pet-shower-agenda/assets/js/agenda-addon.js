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
      toast: ''
    }, options || {});
    var row = $('tr[data-appt-id="' + apptId + '"], .dps-operational-card[data-appt-id="' + apptId + '"]').first();
    var trigger = row.find('[data-operation-focus="' + settings.focusTarget + '"]').first();

    if (!row.length) {
      return false;
    }

    var dialog = showAgendaContentDialog({
      title: getMessage('operationDialogTitle', 'Operação do atendimento'),
      subtitle: getMessage('operationDialogSubtitle', 'Checklist, check-in e check-out em um fluxo único.'),
      size: 'large',
      trigger: trigger.length ? trigger : null,
      bodyHtml: '<p class="dps-checklist-modal-loading">' + escapeHtml(getMessage('checklistLoading', 'Carregando checklist...')) + '</p>'
    });

    dialog.attr('data-operation-appt-id', apptId);

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
        focusOperationModal(dialog, settings.focusTarget);
      } else {
        dialog.find('.dps-agenda-dialog__body').html('<p class="dps-checklist-modal-error">' + escapeHtml(getMessage('checklistError', 'Não foi possível carregar o checklist.')) + '</p>');
      }
    }).fail(function(xhr) {
      var fallback = normalizeAjaxJsonResponse(xhr && xhr.responseText ? xhr.responseText : null);
      if (fallback && fallback.success && fallback.data && fallback.data.html) {
        dialog.find('.dps-agenda-dialog__body').html(fallback.data.html);
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
    focus: focusOperationModal
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
        showToast(getAjaxErrorMessage(response, 'Erro ao executar a acao. Tente novamente.'), 'error');
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
      service: target.data('dps-service') || '',
      payment: target.data('dps-payment') || '',
      logistics: target.data('dps-logistics') || '',
      notes: target.data('dps-notes') || '',
      progress: String(target.data('dps-progress') || '0') + '%'
    };

    $('[data-inspector-field="pet"]').text(fields.pet);
    $('[data-inspector-field="tutor"]').text(fields.tutor);
    $('[data-inspector-field="stage"]').text(fields.stage);
    $('[data-inspector-field="service"]').text(fields.service);
    $('[data-inspector-field="payment"]').text(fields.payment);
    $('[data-inspector-field="logistics"]').text(fields.logistics);
    $('[data-inspector-field="notes"]').text(fields.notes);
    $('[data-inspector-field="progress"]').text(fields.progress);
    $('[data-inspector-progress-bar]').css('width', fields.progress);

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

  $(document).on('click', '.dps-agenda-secondary-actions', function(e){
    e.preventDefault();
    var btn = $(this);
    var apptId = btn.data('appt-id');
    var row = getCanonicalRow(apptId);
    var date = row.data('dps-date') || '';
    var time = row.data('dps-time') || '';
    var bodyHtml = '<div class="dps-secondary-action-grid">' +
      '<button type="button" class="dps-agenda-dialog__action dps-agenda-dialog__action--secondary" data-secondary-action="services">Serviços</button>' +
      '<button type="button" class="dps-agenda-dialog__action dps-agenda-dialog__action--secondary" data-secondary-action="operation">Operação</button>' +
      '<button type="button" class="dps-agenda-dialog__action dps-agenda-dialog__action--secondary" data-secondary-action="reschedule">Reagendar</button>' +
      '<button type="button" class="dps-agenda-dialog__action dps-agenda-dialog__action--secondary" data-secondary-action="history">Histórico</button>' +
      '</div>';

    openAgendaDialog({
      title: 'Ações do atendimento',
      subtitle: 'Ações secundárias agrupadas para manter a fila limpa.',
      size: 'small',
      trigger: btn,
      bodyHtml: bodyHtml,
      footerHtml: '<button type="button" class="dps-agenda-dialog__action dps-agenda-dialog__action--secondary" data-dialog-close="true">' + escapeHtml(getMessage('close', 'Fechar')) + '</button>'
    });

    $(document).one('click.dpsSecondaryActions', '[data-secondary-action]', function(){
      var action = $(this).data('secondary-action');
      closeAgendaDialog($(AGENDA_DIALOG_SELECTOR).last(), 'secondary-action');
      if (action === 'services') {
        row.find('.dps-services-popup-btn').trigger('click');
      } else if (action === 'operation') {
        openOperationPanel(apptId, { focusPanel: true, focusTarget: 'checklist' });
      } else if (action === 'history') {
        $('<button type="button" class="dps-history-indicator" data-appt-id="' + apptId + '"></button>').appendTo('body').trigger('click').remove();
      } else if (action === 'reschedule') {
        $('<button type="button" class="dps-quick-reschedule" data-appt-id="' + apptId + '" data-date="' + escapeHtml(date) + '" data-time="' + escapeHtml(time) + '"></button>').appendTo('body').trigger('click').remove();
      }
    });
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
          showToast(getAjaxErrorMessage(xhr, 'Erro de comunicacao ao reenviar link.'), 'error');
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
      showToast(getAjaxErrorMessage(xhr, 'Erro de comunicacao.'), 'error');
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
          showToast(getMessage('versionConflict', 'Esse agendamento foi atualizado por outro usuário. Atualize a página para ver as alterações.'), 'warning');
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

  // Handler para servicos no dialog canonico DPS Signature
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
      subtitle: 'Mesmo shell operacional da Agenda, com servicos, tempo e observacoes do atendimento.',
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
        showToast(resp && resp.data ? resp.data.message : 'Erro ao carregar servicos.', 'error');
      }
    }).fail(function(xhr){
      showToast(getAjaxErrorMessage(xhr, 'Erro de comunicacao.'), 'error');
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
      showToast(getAjaxErrorMessage(xhr, 'Erro de comunicacao.'), 'error');
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
      title: getMessage('confirmAction', 'Confirmar acao'),
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
          showToast(getAjaxErrorMessage(xhr, 'Erro de comunicacao.'), 'error');
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
