/**
 * Checklist operacional e fluxo de check-in / check-out.
 *
 * @package DPS_Agenda_Addon
 * @since   1.2.0
 */
/* global jQuery, DPS_Checklist_Checkin */
(function ($) {
  'use strict';

  if (typeof DPS_Checklist_Checkin === 'undefined') {
    return;
  }

  var cfg = DPS_Checklist_Checkin;

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;')
      .replace(/\//g, '&#x2F;');
  }

  function showFeedback(message, type, duration) {
    if (window.DPSAgendaShared && typeof window.DPSAgendaShared.showToast === 'function') {
      window.DPSAgendaShared.showToast(message, type || 'error', duration || 3200);
      return;
    }

    if (window.DPSToast && typeof window.DPSToast.show === 'function') {
      window.DPSToast.show(message, type || 'error', duration || 3200);
      return;
    }

    window.console.error(message);
  }

  function closeReworkModal(target) {
    if (window.DPSAgendaDialog && typeof window.DPSAgendaDialog.close === 'function') {
      window.DPSAgendaDialog.close(target || $('.dps-agenda-dialog-overlay--rework').last(), 'dismiss');
    }

    $('.dps-rework-modal-overlay').remove();
    $(document).off('keydown.dpsRework');
  }

  function getAgendaRow(appointmentId) {
    return $('tr[data-appt-id="' + appointmentId + '"]').first();
  }

  function replaceAgendaRow(appointmentId, rowHtml) {
    var currentRow = getAgendaRow(appointmentId);

    if (!currentRow.length || !rowHtml) {
      return currentRow;
    }

    if (window.DPSAgendaShared && typeof window.DPSAgendaShared.replaceRow === 'function') {
      return window.DPSAgendaShared.replaceRow(currentRow, rowHtml);
    }

        var parsedRows = $($.parseHTML(String(rowHtml || '').trim(), document, true)).filter('tr');
    if (!parsedRows.length) {
      parsedRows = $(rowHtml).filter('tr');
    }

    if (!parsedRows.length) {
      return currentRow;
    }

    currentRow.replaceWith(parsedRows);
    return parsedRows.first();
  }

  function getOperationDialog(appointmentId) {
    return $('.dps-agenda-dialog-overlay[data-operation-appt-id="' + appointmentId + '"]').last();
  }

  function updateOperationTrigger(dialog, appointmentId, focusTarget) {
    if (!dialog || !dialog.length) {
      return;
    }

    var row = getAgendaRow(appointmentId);
    if (!row.length) {
      return;
    }

    var selector = '[data-operation-focus="' + focusTarget + '"]';
    if (focusTarget === 'checkout') {
      selector += ', .dps-operation-action-btn--checkout';
    }

    var trigger = row.find(selector).filter(':visible').first();
    if (!trigger.length) {
      trigger = row.find('.dps-operation-action-btn, .dps-operation-modal-btn, .dps-expand-panels-btn').filter(':visible').first();
    }

    if (trigger.length) {
      dialog.data('dialogTrigger', trigger);
    }
  }

  function refreshOperationUi(appointmentId, data, options) {
    var settings = $.extend({
      focusTarget: 'checklist',
      message: ''
    }, options || {});
    var dialog = getOperationDialog(appointmentId);

    if (data && (data.row_html || data.card_html)) {
      if (window.DPSAgendaShared && typeof window.DPSAgendaShared.refreshMarkup === 'function') {
        window.DPSAgendaShared.refreshMarkup(getAgendaRow(appointmentId), data);
      } else {
        if (data.row_html) {
          replaceAgendaRow(appointmentId, data.row_html);
        }

        if (data.card_html && window.DPSAgendaShared && typeof window.DPSAgendaShared.replaceCard === 'function') {
          window.DPSAgendaShared.replaceCard(appointmentId, data.card_html);
        }
      }
    }

    if (dialog.length && data && data.operation_html) {
      dialog.find('.dps-agenda-dialog__body').html(data.operation_html);
      updateOperationTrigger(dialog, appointmentId, settings.focusTarget);

      if (window.DPSAgendaOperation && typeof window.DPSAgendaOperation.focus === 'function') {
        window.DPSAgendaOperation.focus(dialog, settings.focusTarget);
      }
    }

    if (settings.message) {
      showFeedback(settings.message, 'success', 2400);
    }
  }

  function collectCheckinData($stage) {
    var data = {
      observations: $stage.find('.dps-checkin-observations textarea').val() || '',
      safety_items: {}
    };

    $stage.find('.dps-safety-item').each(function () {
      var $item = $(this);
      var slug = $item.data('slug');
      if (!slug) {
        return;
      }

      data.safety_items[slug] = {
        checked: $item.find('input[type="checkbox"]').is(':checked') ? 1 : 0,
        notes: $item.find('.dps-safety-item-notes').val() || ''
      };
    });

    return data;
  }

  function submitStageAction($btn, actionName, focusTarget) {
    var $stage = $btn.closest('.dps-checkin-stage');
    var $panel = $btn.closest('.dps-checkin-panel');
    var appointmentId = $panel.data('appointment');
    var originalHtml = $btn.html();
    var formData = collectCheckinData($stage);

    formData.action = actionName;
    formData.nonce = cfg.nonce_checkin;
    formData.appointment_id = appointmentId;

    $stage.addClass('dps-checkin-stage--saving');
    $btn.prop('disabled', true).attr('aria-disabled', 'true').text(cfg.messages.saving);

    $.post(cfg.ajax, formData, function (response) {
      if (response && response.success && response.data) {
        refreshOperationUi(appointmentId, response.data, {
          focusTarget: focusTarget,
          message: response.data.message || ''
        });
      } else {
        showFeedback(response && response.data ? response.data.message : cfg.messages.error, 'error');
        $btn.prop('disabled', false).removeAttr('aria-disabled').html(originalHtml);
      }
    }).fail(function () {
      showFeedback(cfg.messages.error, 'error');
      $btn.prop('disabled', false).removeAttr('aria-disabled').html(originalHtml);
    }).always(function () {
      $stage.removeClass('dps-checkin-stage--saving');
    });
  }

  function ajaxChecklist(appointmentId, stepKey, status, reason, $step) {
    var data = {
      action: 'dps_checklist_update',
      nonce: cfg.nonce_checklist,
      appointment_id: appointmentId,
      step_key: stepKey,
      status: status,
      reason: reason
    };

    $step.css('opacity', '0.5').attr('aria-busy', 'true');

    $.post(cfg.ajax, data, function (response) {
      if (response && response.success && response.data) {
        refreshOperationUi(appointmentId, response.data, {
          focusTarget: 'checklist',
          message: response.data.message || ''
        });
      } else {
        showFeedback(response && response.data ? response.data.message : cfg.messages.error, 'error');
      }
    }).fail(function () {
      showFeedback(cfg.messages.error, 'error');
    }).always(function () {
      $step.css('opacity', '1').removeAttr('aria-busy');
    });
  }

  function showReworkModal(appointmentId, stepKey, stepLabel) {
    var safeReworkPlaceholder = escapeHtml(cfg.messages.reworkPlaceholder);
    var safeCancel = escapeHtml(cfg.messages.cancel);
    var safeConfirmRework = escapeHtml(cfg.messages.confirmRework);

    function submitRework(reason, dialogTarget) {
      $.post(cfg.ajax, {
        action: 'dps_checklist_rework',
        nonce: cfg.nonce_checklist,
        appointment_id: appointmentId,
        step_key: stepKey,
        reason: reason
      }, function (response) {
        if (response && response.success && response.data) {
          refreshOperationUi(appointmentId, response.data, {
            focusTarget: 'checklist',
            message: response.data.message || ''
          });
        } else {
          showFeedback(response && response.data ? response.data.message : cfg.messages.error, 'error');
        }

        closeReworkModal(dialogTarget);
      }).fail(function () {
        showFeedback(cfg.messages.error, 'error');
        closeReworkModal(dialogTarget);
      });
    }

    if (window.DPSAgendaDialog && typeof window.DPSAgendaDialog.open === 'function') {
      var dialog = window.DPSAgendaDialog.open({
        title: cfg.messages.reworkTitle,
        subtitle: stepLabel,
        size: 'small',
        overlayClass: 'dps-agenda-dialog-overlay--rework',
        bodyHtml: '<textarea id="dps-rework-reason" class="dps-agenda-dialog__textarea" placeholder="' + safeReworkPlaceholder + '"></textarea>',
        footerHtml:
          '<button type="button" class="dps-agenda-dialog__action dps-agenda-dialog__action--secondary" data-dialog-close="true">' + safeCancel + '</button>' +
          '<button type="button" class="dps-agenda-dialog__action dps-agenda-dialog__action--primary dps-rework-confirm">' + safeConfirmRework + '</button>',
        initialFocus: '#dps-rework-reason'
      });

      dialog.on('click', '.dps-rework-confirm', function () {
        submitRework(dialog.find('#dps-rework-reason').val(), dialog);
      });

      return;
    }

    var html =
      '<div class="dps-rework-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="dps-rework-title">' +
        '<div class="dps-rework-modal">' +
          '<h4 id="dps-rework-title">' + escapeHtml(cfg.messages.reworkTitle) + ': ' + escapeHtml(stepLabel) + '</h4>' +
          '<textarea id="dps-rework-reason" placeholder="' + safeReworkPlaceholder + '"></textarea>' +
          '<div class="dps-rework-modal-actions">' +
            '<button class="dps-checklist-btn dps-checklist-btn--undo dps-rework-cancel" type="button">' + safeCancel + '</button>' +
            '<button class="dps-checklist-btn dps-checklist-btn--rework dps-rework-confirm" type="button">' + safeConfirmRework + '</button>' +
          '</div>' +
        '</div>' +
      '</div>';

    $('.dps-rework-modal-overlay').remove();
    $('body').append(html);
    $('#dps-rework-reason').trigger('focus');

    $('.dps-rework-cancel').on('click', function () {
      closeReworkModal();
    });

    $(document).on('keydown.dpsRework', function (event) {
      if (event.key === 'Escape') {
        closeReworkModal();
      }
    });

    $('.dps-rework-confirm').on('click', function () {
      submitRework($('#dps-rework-reason').val());
    });
  }

  $(document).on('click', '.dps-checklist-btn--done', function (event) {
    event.preventDefault();
    var $step = $(this).closest('.dps-checklist-step');
    ajaxChecklist($step.closest('.dps-checklist-panel').data('appointment'), $step.data('step'), 'done', '', $step);
  });

  $(document).on('click', '.dps-checklist-btn--undo', function (event) {
    event.preventDefault();
    var $step = $(this).closest('.dps-checklist-step');
    ajaxChecklist($step.closest('.dps-checklist-panel').data('appointment'), $step.data('step'), 'pending', '', $step);
  });

  $(document).on('click', '.dps-checklist-btn--skip', function (event) {
    event.preventDefault();
    var $step = $(this).closest('.dps-checklist-step');
    ajaxChecklist($step.closest('.dps-checklist-panel').data('appointment'), $step.data('step'), 'skipped', '', $step);
  });

  $(document).on('click', '.dps-checklist-btn--rework', function (event) {
    event.preventDefault();
    var $step = $(this).closest('.dps-checklist-step');
    showReworkModal(
      $step.closest('.dps-checklist-panel').data('appointment'),
      $step.data('step'),
      $step.find('.dps-checklist-step-label').text().trim()
    );
  });

  $(document).on('change', '.dps-safety-item input[type="checkbox"]', function () {
    $(this).closest('.dps-safety-item').toggleClass('dps-safety-item--checked', this.checked);
  });

  $(document).on('click', '.dps-checkin-btn--checkin', function (event) {
    event.preventDefault();
    submitStageAction($(this), 'dps_appointment_checkin', 'checkin');
  });

  $(document).on('click', '.dps-checkin-btn--checkout', function (event) {
    event.preventDefault();
    submitStageAction($(this), 'dps_appointment_checkout', 'checkout');
  });

  $(document).on('click', '.dps-expand-panels-btn, .dps-operation-modal-btn', function (event) {
    event.preventDefault();

    if (!window.DPSAgendaOperation || typeof window.DPSAgendaOperation.open !== 'function') {
      return;
    }

    window.DPSAgendaOperation.open($(this).data('appt-id'), {
      focusPanel: true,
      focusTarget: 'checklist'
    });
  });

  $(document).on('click', '.dps-operation-action-btn', function (event) {
    event.preventDefault();

    if (!window.DPSAgendaOperation || typeof window.DPSAgendaOperation.open !== 'function') {
      return;
    }

    var $btn = $(this);
    var focusTarget = $btn.data('operation-focus') || ($btn.hasClass('dps-operation-action-btn--checkout') ? 'checkout' : 'checkin');

    window.DPSAgendaOperation.open($btn.data('appt-id'), {
      focusPanel: true,
      focusTarget: focusTarget
    });
  });
})(jQuery);
