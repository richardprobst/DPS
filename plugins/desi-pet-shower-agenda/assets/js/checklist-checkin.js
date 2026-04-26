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
        if (typeof window.DPSAgendaOperation.applyMode === 'function') {
          window.DPSAgendaOperation.applyMode(dialog, dialog.data('operationMode') || settings.focusTarget, settings.focusTarget);
        }

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

  function getEvidenceMessage(key, fallback) {
    return cfg.messages && cfg.messages[key] ? cfg.messages[key] : fallback;
  }

  function setEvidenceBusy($panel, isBusy) {
    var $trigger = $panel.find('.dps-pet-evidence__trigger');

    $panel.toggleClass('dps-pet-evidence--busy', !!isBusy).attr('aria-busy', isBusy ? 'true' : 'false');
    $trigger
      .prop('disabled', !!isBusy)
      .attr('aria-disabled', isBusy ? 'true' : 'false')
      .text(isBusy ? getEvidenceMessage('evidenceUploading', 'Enviando evidência...') : getEvidenceMessage('evidenceUpload', 'Adicionar foto/vídeo'));
  }

  function getEvidenceFileLimitBytes() {
    var maxSizeMb = cfg.evidence && cfg.evidence.maxSizeMb ? parseFloat(cfg.evidence.maxSizeMb) : 0;
    return maxSizeMb > 0 ? maxSizeMb * 1024 * 1024 : 0;
  }

  function uploadPetEvidence($input, droppedFile) {
    var $panel = $input.closest('.dps-pet-evidence');
    var $checkinPanel = $panel.closest('.dps-checkin-panel');
    var appointmentId = $checkinPanel.data('appointment');
    var stage = $panel.data('stage') || $panel.closest('.dps-checkin-stage').data('stage') || 'checkin';
    var safetySlug = $panel.data('safety-slug') || $panel.closest('.dps-safety-item').data('slug');
    var file = droppedFile || ($input[0] && $input[0].files ? $input[0].files[0] : null);
    var fileLimit = getEvidenceFileLimitBytes();
    var formData = new FormData();

    if (!file) {
      showFeedback(getEvidenceMessage('evidenceSelectFile', 'Selecione uma foto ou vídeo para anexar.'), 'error');
      return;
    }

    if (fileLimit && file.size > fileLimit) {
      showFeedback('Arquivo acima do limite de ' + (cfg.evidence.maxSizeMb || '50') + ' MB.', 'error');
      return;
    }

    formData.append('action', 'dps_pet_evidence_upload');
    formData.append('nonce', cfg.nonce_evidence || '');
    formData.append('appointment_id', appointmentId);
    formData.append('stage', stage);
    formData.append('safety_slug', safetySlug);
    formData.append('caption', $panel.find('.dps-pet-evidence__caption').val() || '');
    formData.append('evidence_file', file);

    setEvidenceBusy($panel, true);

    $.ajax({
      url: cfg.ajax,
      method: 'POST',
      data: formData,
      processData: false,
      contentType: false
    }).done(function (response) {
      if (response && response.success && response.data) {
        refreshOperationUi(appointmentId, response.data, {
          focusTarget: stage,
          message: response.data.message || ''
        });
      } else {
        showFeedback(response && response.data ? response.data.message : cfg.messages.error, 'error');
      }
    }).fail(function () {
      showFeedback(cfg.messages.error, 'error');
    }).always(function () {
      $input.val('');
      setEvidenceBusy($panel, false);
    });
  }

  function closeEvidenceConfirm(dialog) {
    if (dialog && dialog.length && window.DPSAgendaDialog && typeof window.DPSAgendaDialog.close === 'function') {
      window.DPSAgendaDialog.close(dialog, 'confirmed');
      return;
    }

    $('.dps-agenda-dialog-overlay--pet-evidence-remove').remove();
  }

  function confirmPetEvidenceRemoval($btn, onConfirm) {
    var message = getEvidenceMessage(
      'evidenceConfirmRemove',
      'Remover esta evidência do atendimento? O arquivo permanecerá preservado na Biblioteca de Mídia.'
    );

    if (window.DPSAgendaDialog && typeof window.DPSAgendaDialog.open === 'function') {
      var dialog = window.DPSAgendaDialog.open({
        title: getEvidenceMessage('evidenceRemove', 'Remover evidência'),
        size: 'small',
        overlayClass: 'dps-agenda-dialog-overlay--pet-evidence-remove',
        bodyHtml: '<p class="dps-pet-evidence-confirm-text">' + escapeHtml(message) + '</p>',
        footerHtml:
          '<button type="button" class="dps-agenda-dialog__action dps-agenda-dialog__action--secondary" data-dialog-close="true">' + escapeHtml(cfg.messages.cancel || 'Cancelar') + '</button>' +
          '<button type="button" class="dps-agenda-dialog__action dps-agenda-dialog__action--primary dps-pet-evidence-confirm-remove">' + escapeHtml(getEvidenceMessage('evidenceRemove', 'Remover evidência')) + '</button>'
      });

      dialog.on('click', '.dps-pet-evidence-confirm-remove', function () {
        onConfirm(function () {
          closeEvidenceConfirm(dialog);
        });
      });
      return;
    }

    if (window.confirm(message)) {
      onConfirm(function () {});
    }
  }

  function removePetEvidence($btn) {
    var $panel = $btn.closest('.dps-pet-evidence');
    var $checkinPanel = $panel.closest('.dps-checkin-panel');
    var appointmentId = $checkinPanel.data('appointment');
    var stage = $panel.data('stage') || $panel.closest('.dps-checkin-stage').data('stage') || 'checkin';
    var evidenceId = $btn.data('evidence-id');

    confirmPetEvidenceRemoval($btn, function (closeConfirm) {
      setEvidenceBusy($panel, true);

      $.post(cfg.ajax, {
        action: 'dps_pet_evidence_remove',
        nonce: cfg.nonce_evidence || '',
        appointment_id: appointmentId,
        evidence_id: evidenceId
      }, function (response) {
        if (response && response.success && response.data) {
          refreshOperationUi(appointmentId, response.data, {
            focusTarget: stage,
            message: response.data.message || ''
          });
          closeConfirm();
        } else {
          showFeedback(response && response.data ? response.data.message : cfg.messages.error, 'error');
        }
      }).fail(function () {
        showFeedback(cfg.messages.error, 'error');
      }).always(function () {
        setEvidenceBusy($panel, false);
      });
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

  $(document).on('click', '.dps-pet-evidence__trigger', function (event) {
    event.preventDefault();
    $(this).closest('.dps-pet-evidence').find('.dps-pet-evidence__input').trigger('click');
  });

  $(document).on('change', '.dps-pet-evidence__input', function () {
    uploadPetEvidence($(this));
  });

  $(document).on('click', '.dps-pet-evidence-card__remove', function (event) {
    event.preventDefault();
    removePetEvidence($(this));
  });

  $(document).on('dragover', '.dps-pet-evidence', function (event) {
    if ($(this).find('.dps-pet-evidence__input').prop('disabled')) {
      return;
    }

    event.preventDefault();
    $(this).addClass('dps-pet-evidence--dragover');
  });

  $(document).on('dragleave drop', '.dps-pet-evidence', function () {
    $(this).removeClass('dps-pet-evidence--dragover');
  });

  $(document).on('drop', '.dps-pet-evidence', function (event) {
    var transfer = event.originalEvent && event.originalEvent.dataTransfer;
    var files = transfer && transfer.files ? transfer.files : null;
    var $input = $(this).find('.dps-pet-evidence__input');

    if ($input.prop('disabled')) {
      return;
    }

    event.preventDefault();

    if (files && files.length) {
      uploadPetEvidence($input, files[0]);
    }
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
      focusTarget: 'checklist',
      mode: 'checklist'
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
      focusTarget: focusTarget,
      mode: focusTarget
    });
  });
})(jQuery);
