/**
 * Checklist Operacional e Check-in / Check-out — Interações JS.
 *
 * Depende de jQuery e do objeto localizado DPS_Checklist_Checkin.
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

    // Simple HTML-escaping helper to prevent XSS when inserting text into HTML strings.
    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/\//g, '&#x2F;');
    }

    /* ===========================
       CHECKLIST
       =========================== */

    /**
     * Marca etapa como concluída.
     */
    $(document).on('click', '.dps-checklist-btn--done', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var $step = $btn.closest('.dps-checklist-step');
        var stepKey = $step.data('step');
        var apptId = $step.closest('.dps-checklist-panel').data('appointment');

        ajaxChecklist(apptId, stepKey, 'done', '', $step);
    });

    /**
     * Desfaz conclusão (volta para pending).
     */
    $(document).on('click', '.dps-checklist-btn--undo', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var $step = $btn.closest('.dps-checklist-step');
        var stepKey = $step.data('step');
        var apptId = $step.closest('.dps-checklist-panel').data('appointment');

        ajaxChecklist(apptId, stepKey, 'pending', '', $step);
    });

    /**
     * Pula etapa.
     */
    $(document).on('click', '.dps-checklist-btn--skip', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var $step = $btn.closest('.dps-checklist-step');
        var stepKey = $step.data('step');
        var apptId = $step.closest('.dps-checklist-panel').data('appointment');

        ajaxChecklist(apptId, stepKey, 'skipped', '', $step);
    });

    /**
     * Abre modal de retrabalho.
     */
    $(document).on('click', '.dps-checklist-btn--rework', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var $step = $btn.closest('.dps-checklist-step');
        var stepKey = $step.data('step');
        var apptId = $step.closest('.dps-checklist-panel').data('appointment');
        var stepLabel = $step.find('.dps-checklist-step-label').text().trim();

        showReworkModal(apptId, stepKey, stepLabel);
    });

    /**
     * Envia atualização de checklist via AJAX.
     */
    function ajaxChecklist(apptId, stepKey, status, reason, $step) {
        var data = {
            action: 'dps_checklist_update',
            nonce: cfg.nonce_checklist,
            appointment_id: apptId,
            step_key: stepKey,
            status: status,
            reason: reason
        };

        $step.css('opacity', '0.5');

        $.post(cfg.ajax, data, function (response) {
            if (response.success) {
                updateStepUI($step, status, response.data.progress, response.data.rework_count);
            } else {
                window.alert(response.data.message || cfg.messages.error);
            }
        }).fail(function () {
            window.alert(cfg.messages.error);
        }).always(function () {
            $step.css('opacity', '1');
        });
    }

    /**
     * Atualiza a UI de uma etapa após ação.
     */
    function updateStepUI($step, status, progress, reworkCount) {
        var $panel = $step.closest('.dps-checklist-panel');

        $step.attr('data-status', status);

        // Atualiza botões
        var $actions = $step.find('.dps-checklist-step-actions');
        $actions.empty();

        if (status === 'pending') {
            $actions.append(
                '<button class="dps-checklist-btn dps-checklist-btn--done" type="button">✓ ' + cfg.messages.markDone + '</button>' +
                '<button class="dps-checklist-btn dps-checklist-btn--skip" type="button">' + cfg.messages.skip + '</button>'
            );
        } else if (status === 'done') {
            $actions.append(
                '<button class="dps-checklist-btn dps-checklist-btn--undo" type="button">↩ ' + cfg.messages.undo + '</button>' +
                '<button class="dps-checklist-btn dps-checklist-btn--rework" type="button">🔄 ' + cfg.messages.rework + '</button>'
            );
        } else if (status === 'skipped') {
            $actions.append(
                '<button class="dps-checklist-btn dps-checklist-btn--undo" type="button">↩ ' + cfg.messages.undo + '</button>'
            );
        }

        // Atualiza rework badge
        var $badge = $step.find('.dps-checklist-rework-badge');
        if (reworkCount > 0) {
            if ($badge.length) {
                $badge.text('🔄 ' + reworkCount);
            } else {
                $step.find('.dps-checklist-step-label').after(
                    '<span class="dps-checklist-rework-badge">🔄 ' + reworkCount + '</span>'
                );
            }
        }

        // Atualiza barra de progresso
        if (typeof progress !== 'undefined') {
            $panel.find('.dps-checklist-progress-fill').css('width', progress + '%');
            $panel.find('.dps-checklist-progress-text').text(progress + '%');
        }
    }

    /**
     * Exibe modal de retrabalho.
     */
    function showReworkModal(apptId, stepKey, stepLabel) {
        // Remove modal existente
        $('.dps-rework-modal-overlay').remove();

        // Escape dynamic text to avoid interpreting it as HTML.
        var safeStepLabel = escapeHtml(stepLabel);
        var safeReworkTitle = escapeHtml(cfg.messages.reworkTitle);
        var safeReworkPlaceholder = escapeHtml(cfg.messages.reworkPlaceholder);
        var safeCancel = escapeHtml(cfg.messages.cancel);
        var safeConfirmRework = escapeHtml(cfg.messages.confirmRework);

        var html =
            '<div class="dps-rework-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="dps-rework-title">' +
                '<div class="dps-rework-modal">' +
                    '<h4 id="dps-rework-title">🔄 ' + safeReworkTitle + ': ' + safeStepLabel + '</h4>' +
                    '<textarea id="dps-rework-reason" placeholder="' + safeReworkPlaceholder + '"></textarea>' +
                    '<div class="dps-rework-modal-actions">' +
                        '<button class="dps-checklist-btn dps-checklist-btn--undo dps-rework-cancel" type="button">' + safeCancel + '</button>' +
                        '<button class="dps-checklist-btn dps-checklist-btn--rework dps-rework-confirm" type="button">' + safeConfirmRework + '</button>' +
                    '</div>' +
                '</div>' +
            '</div>';

        $('body').append(html);

        // Foca no textarea
        $('#dps-rework-reason').trigger('focus');

        // Cancelar
        $('.dps-rework-cancel').on('click', function () {
            $('.dps-rework-modal-overlay').remove();
            $(document).off('keydown.dpsRework');
        });

        // Fechar com Escape
        $(document).on('keydown.dpsRework', function (e) {
            if (e.key === 'Escape') {
                $('.dps-rework-modal-overlay').remove();
                $(document).off('keydown.dpsRework');
            }
        });

        // Confirmar retrabalho
        $('.dps-rework-confirm').on('click', function () {
            var reason = $('#dps-rework-reason').val();

            $.post(cfg.ajax, {
                action: 'dps_checklist_rework',
                nonce: cfg.nonce_checklist,
                appointment_id: apptId,
                step_key: stepKey,
                reason: reason
            }, function (response) {
                if (response.success) {
                    var $panel = $('.dps-checklist-panel[data-appointment="' + apptId + '"]');
                    var $step = $panel.find('.dps-checklist-step[data-step="' + stepKey + '"]');
                    updateStepUI($step, 'pending', response.data.progress, response.data.rework_count);
                } else {
                    window.alert(response.data.message || cfg.messages.error);
                }
                $('.dps-rework-modal-overlay').remove();
                $(document).off('keydown.dpsRework');
            }).fail(function () {
                window.alert(cfg.messages.error);
                $('.dps-rework-modal-overlay').remove();
                $(document).off('keydown.dpsRework');
            });
        });
    }

    /* ===========================
       CHECK-IN / CHECK-OUT
       =========================== */

    /**
     * Toggle de item de segurança.
     */
    $(document).on('change', '.dps-safety-item input[type="checkbox"]', function () {
        var $item = $(this).closest('.dps-safety-item');
        $item.toggleClass('dps-safety-item--checked', this.checked);
    });

    /**
     * Botão Check-in.
     */
    $(document).on('click', '.dps-checkin-btn--checkin', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var $panel = $btn.closest('.dps-checkin-panel');
        var apptId = $panel.data('appointment');

        var formData = collectCheckinData($panel);
        formData.action = 'dps_appointment_checkin';
        formData.nonce = cfg.nonce_checkin;
        formData.appointment_id = apptId;

        $btn.prop('disabled', true).text(cfg.messages.saving);

        $.post(cfg.ajax, formData, function (response) {
            if (response.success) {
                refreshCheckinPanel($panel, response.data);
            } else {
                window.alert(response.data.message || cfg.messages.error);
                $btn.prop('disabled', false).html('📥 ' + cfg.messages.checkin);
            }
        }).fail(function () {
            window.alert(cfg.messages.error);
            $btn.prop('disabled', false).html('📥 ' + cfg.messages.checkin);
        });
    });

    /**
     * Botão Check-out.
     */
    $(document).on('click', '.dps-checkin-btn--checkout', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var $panel = $btn.closest('.dps-checkin-panel');
        var apptId = $panel.data('appointment');

        var formData = collectCheckinData($panel);
        formData.action = 'dps_appointment_checkout';
        formData.nonce = cfg.nonce_checkin;
        formData.appointment_id = apptId;

        $btn.prop('disabled', true).text(cfg.messages.saving);

        $.post(cfg.ajax, formData, function (response) {
            if (response.success) {
                refreshCheckinPanel($panel, response.data);
            } else {
                window.alert(response.data.message || cfg.messages.error);
                $btn.prop('disabled', false).html('📤 ' + cfg.messages.checkout);
            }
        }).fail(function () {
            window.alert(cfg.messages.error);
            $btn.prop('disabled', false).html('📤 ' + cfg.messages.checkout);
        });
    });

    /**
     * Coleta dados do formulário de check-in/check-out.
     */
    function collectCheckinData($panel) {
        var data = {};

        // Observações
        data.observations = $panel.find('.dps-checkin-observations textarea').val() || '';

        // Itens de segurança
        data.safety_items = {};
        $panel.find('.dps-safety-item').each(function () {
            var $item = $(this);
            var slug = $item.data('slug');
            var checked = $item.find('input[type="checkbox"]').is(':checked');
            var notes = $item.find('.dps-safety-item-notes').val() || '';
            data.safety_items[slug] = {
                checked: checked ? 1 : 0,
                notes: notes
            };
        });

        return data;
    }

    /**
     * Atualiza o painel de check-in/check-out após ação.
     */
    function refreshCheckinPanel($panel, data) {
        var statusHtml = '';

        if (data.checkin_time) {
            statusHtml += '<span class="dps-checkin-status-badge dps-checkin-status-badge--in">📥 Check-in: ' + data.checkin_time + '</span>';
        }

        if (data.checkout_time) {
            statusHtml += '<span class="dps-checkin-status-badge dps-checkin-status-badge--out">📤 Check-out: ' + data.checkout_time + '</span>';
        }

        if (data.duration) {
            statusHtml += '<span class="dps-checkin-status-badge dps-checkin-status-badge--duration">⏱️ ' + data.duration + '</span>';
        }

        $panel.find('.dps-checkin-status').html(statusHtml);

        // Atualiza botões
        var $actions = $panel.find('.dps-checkin-actions');
        $actions.empty();

        if (!data.has_checkin) {
            $actions.append('<button type="button" class="dps-checkin-btn dps-checkin-btn--checkin">📥 ' + cfg.messages.checkin + '</button>');
        } else if (!data.has_checkout) {
            $actions.append('<button type="button" class="dps-checkin-btn dps-checkin-btn--checkout">📤 ' + cfg.messages.checkout + '</button>');
        }

        // Mostra resumo de safety items se check-in foi feito
        if (data.safety_summary && data.safety_summary.length > 0) {
            var summaryHtml = '<div class="dps-safety-summary">';
            $.each(data.safety_summary, function (_, item) {
                summaryHtml += '<span class="dps-safety-tag dps-safety-tag--' + item.severity + '">' + item.icon + ' ' + item.label + '</span>';
            });
            summaryHtml += '</div>';

            // Insere ou atualiza summary
            var $existing = $panel.find('.dps-safety-summary');
            if ($existing.length) {
                $existing.replaceWith(summaryHtml);
            } else {
                $panel.find('.dps-checkin-status').after(summaryHtml);
            }
        }

        // Se ambos check-in e check-out foram feitos, oculta o formulário
        if (data.has_checkin && data.has_checkout) {
            $panel.find('.dps-safety-items, .dps-checkin-observations').slideUp(200);
        }
    }

})(jQuery);
