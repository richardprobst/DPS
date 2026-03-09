(function($) {
    'use strict';

    var state = {
        selectedClientId: 0,
        selectedRow: null,
        emailModalClientId: 0,
        emailModalAccessUrl: ''
    };

    function getConfig() {
        return window.dpsPortalAdmin || { ajaxUrl: window.ajaxurl || '', nonce: '', i18n: {} };
    }

    function getMessage(key, fallback) {
        var config = getConfig();
        return config.i18n && config.i18n[key] ? config.i18n[key] : fallback;
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function openModal(selector) {
        $(selector).prop('hidden', false).addClass('is-open');
        $('body').addClass('dps-admin-modal-open');
    }

    function closeModals() {
        $('.dps-admin-modal').prop('hidden', true).removeClass('is-open');
        $('body').removeClass('dps-admin-modal-open');
    }

    function ajaxPost(action, data) {
        var config = getConfig();
        return $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: $.extend(
                {
                    action: action,
                    nonce: config.nonce
                },
                data || {}
            )
        });
    }

    function setRowFeedback($row, type, message) {
        var $target = $row.find('[data-row-feedback]').first();
        if (!$target.length) {
            return;
        }

        $target.removeClass('is-success is-error').empty();

        if (!message) {
            return;
        }

        $target.addClass(type === 'success' ? 'is-success' : 'is-error').text(message);
    }

    function withButtonLoading($button, loadingText) {
        var originalText = $button.data('original-text') || $button.text();
        $button.data('original-text', originalText);
        $button.prop('disabled', true).text(loadingText);

        return function restore() {
            $button.prop('disabled', false).text(originalText);
        };
    }

    function copyText(text, onSuccess, onError) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(onSuccess).catch(onError);
            return;
        }

        var $temp = $('<input type="text" />').val(text).appendTo('body');
        $temp[0].select();

        try {
            var copied = document.execCommand('copy');
            $temp.remove();
            if (copied) {
                onSuccess();
                return;
            }
        } catch (error) {
            $temp.remove();
        }

        onError();
    }

    function renderGeneratedToken($row, payload) {
        var phone = $row.data('client-phone') || '';
        var email = $row.data('client-email') || '';
        var $target = $row.find('[data-generated-token]').first();

        if (!$target.length || !payload || !payload.url) {
            return;
        }

        var buttons = [
            '<button type="button" class="button button-small dps-copy-token" data-copy-text="' + escapeHtml(payload.url) + '">Copiar</button>'
        ];

        if (phone) {
            buttons.push('<button type="button" class="button button-small dps-open-whatsapp" data-access-url="' + escapeHtml(payload.url) + '">WhatsApp</button>');
        }

        if (email) {
            buttons.push('<button type="button" class="button button-small dps-preview-email" data-access-url="' + escapeHtml(payload.url) + '">Preparar e-mail</button>');
        }

        $target.html(
            '<div class="dps-generated-token__card">' +
                '<label class="dps-generated-token__label">Link gerado agora</label>' +
                '<div class="dps-generated-token__field">' +
                    '<input type="text" readonly value="' + escapeHtml(payload.url) + '" />' +
                '</div>' +
                '<div class="dps-generated-token__actions">' + buttons.join('') + '</div>' +
                '<small class="dps-generated-token__note">' + escapeHtml(payload.validityLabel || '') + '</small>' +
            '</div>'
        );
    }

    function openWhatsApp($row, accessUrl) {
        var phone = (($row.data('client-phone') || '') + '').replace(/\D+/g, '');
        if (!phone) {
            setRowFeedback($row, 'error', getMessage('whatsappMissing', 'Este cliente nao possui telefone cadastrado para WhatsApp.'));
            return;
        }

        ajaxPost('dps_get_whatsapp_message', {
            client_id: $row.data('client-id'),
            access_url: accessUrl
        }).done(function(response) {
            if (!response || !response.success || !response.data || !response.data.message) {
                setRowFeedback($row, 'error', getMessage('genericError', 'Nao foi possivel concluir esta acao agora.'));
                return;
            }

            window.open('https://wa.me/' + phone + '?text=' + encodeURIComponent(response.data.message), '_blank', 'noopener');
        }).fail(function() {
            setRowFeedback($row, 'error', getMessage('genericError', 'Nao foi possivel concluir esta acao agora.'));
        });
    }

    function bindCopyButtons() {
        $(document).on('click', '[data-copy-text]', function(event) {
            event.preventDefault();

            var $button = $(this);
            var restore = withButtonLoading($button, getMessage('copySuccess', 'Copiado!'));
            var text = $button.data('copy-text') || '';

            copyText(
                text,
                function() {
                    setTimeout(restore, 900);
                },
                function() {
                    $button.text(getMessage('copyError', 'Nao foi possivel copiar.'));
                    setTimeout(restore, 1200);
                }
            );
        });
    }

    function bindGenerateTokenFlow() {
        $(document).on('click', '.dps-generate-token-btn', function() {
            state.selectedRow = $(this).closest('[data-client-row]');
            state.selectedClientId = $(this).data('client-id');
            $('#dps-token-client-name').text('Cliente: ' + ($(this).data('client-name') || ''));
            $('#dps-token-type-modal input[name="dps_token_type"][value="login"]').prop('checked', true);
            openModal('#dps-token-type-modal');
        });

        $(document).on('click', '#dps-confirm-generate-token', function() {
            if (!state.selectedClientId || !state.selectedRow || !state.selectedRow.length) {
                closeModals();
                return;
            }

            var $button = $(this);
            var restore = withButtonLoading($button, getMessage('generating', 'Gerando link...'));
            var type = $('#dps-token-type-modal input[name="dps_token_type"]:checked').val() || 'login';

            ajaxPost('dps_generate_client_token', {
                client_id: state.selectedClientId,
                type: type
            }).done(function(response) {
                if (!response || !response.success) {
                    setRowFeedback(state.selectedRow, 'error', response && response.data && response.data.message ? response.data.message : getMessage('genericError', 'Nao foi possivel concluir esta acao agora.'));
                    return;
                }

                renderGeneratedToken(state.selectedRow, response.data);
                setRowFeedback(state.selectedRow, 'success', 'Link gerado com sucesso.');
                closeModals();
            }).fail(function() {
                setRowFeedback(state.selectedRow, 'error', getMessage('genericError', 'Nao foi possivel concluir esta acao agora.'));
            }).always(function() {
                restore();
            });
        });
    }

    function bindRevokeFlow() {
        $(document).on('click', '.dps-revoke-token-btn', function() {
            var $button = $(this);
            var $row = $button.closest('[data-client-row]');

            if (!window.confirm(getMessage('confirmRevoke', 'Tem certeza que deseja revogar todos os links ativos deste cliente?'))) {
                return;
            }

            var restore = withButtonLoading($button, getMessage('revoking', 'Revogando links...'));

            ajaxPost('dps_revoke_client_tokens', {
                client_id: $button.data('client-id')
            }).done(function(response) {
                if (!response || !response.success) {
                    setRowFeedback($row, 'error', response && response.data && response.data.message ? response.data.message : getMessage('genericError', 'Nao foi possivel concluir esta acao agora.'));
                    return;
                }

                setRowFeedback($row, 'success', response.data.message || 'Links revogados com sucesso.');
                window.setTimeout(function() {
                    window.location.reload();
                }, 600);
            }).fail(function() {
                setRowFeedback($row, 'error', getMessage('genericError', 'Nao foi possivel concluir esta acao agora.'));
            }).always(function() {
                restore();
            });
        });
    }

    function bindPasswordAccessFlow() {
        $(document).on('click', '.dps-send-password-access-btn', function() {
            var $button = $(this);
            var $row = $button.closest('[data-client-row]');
            var restore = withButtonLoading($button, getMessage('sendingPasswordMail', 'Enviando acesso por senha...'));

            ajaxPost('dps_send_password_access_email', {
                client_id: $button.data('client-id')
            }).done(function(response) {
                if (!response || !response.success) {
                    setRowFeedback($row, 'error', response && response.data && response.data.message ? response.data.message : getMessage('genericError', 'Nao foi possivel concluir esta acao agora.'));
                    return;
                }

                setRowFeedback($row, 'success', response.data.message || 'As instrucoes de senha foram enviadas.');
                window.setTimeout(function() {
                    window.location.reload();
                }, 600);
            }).fail(function() {
                setRowFeedback($row, 'error', getMessage('genericError', 'Nao foi possivel concluir esta acao agora.'));
            }).always(function() {
                restore();
            });
        });
    }

    function bindSyncUserFlow() {
        $(document).on('click', '.dps-sync-portal-user-btn', function() {
            var $button = $(this);
            var $row = $button.closest('[data-client-row]');
            var restore = withButtonLoading($button, getMessage('syncingUser', 'Sincronizando usuario...'));

            ajaxPost('dps_sync_portal_user', {
                client_id: $button.data('client-id')
            }).done(function(response) {
                if (!response || !response.success) {
                    setRowFeedback($row, 'error', response && response.data && response.data.message ? response.data.message : getMessage('genericError', 'Nao foi possivel concluir esta acao agora.'));
                    return;
                }

                setRowFeedback($row, 'success', response.data.message || 'Usuario do portal sincronizado com sucesso.');
                window.setTimeout(function() {
                    window.location.reload();
                }, 600);
            }).fail(function() {
                setRowFeedback($row, 'error', getMessage('genericError', 'Nao foi possivel concluir esta acao agora.'));
            }).always(function() {
                restore();
            });
        });
    }

    function bindGeneratedTokenActions() {
        $(document).on('click', '.dps-open-whatsapp', function() {
            var $button = $(this);
            var $row = $button.closest('[data-client-row]');
            openWhatsApp($row, $button.data('access-url'));
        });

        $(document).on('click', '.dps-preview-email', function() {
            var $button = $(this);
            var $row = $button.closest('[data-client-row]');
            state.emailModalClientId = $row.data('client-id');
            state.emailModalAccessUrl = $button.data('access-url') || '';

            $('#dps-email-subject').val('Carregando...');
            $('#dps-email-body').val('Carregando...');
            openModal('#dps-email-preview-modal');

            ajaxPost('dps_preview_email', {
                client_id: state.emailModalClientId,
                access_url: state.emailModalAccessUrl
            }).done(function(response) {
                if (!response || !response.success || !response.data) {
                    closeModals();
                    setRowFeedback($row, 'error', response && response.data && response.data.message ? response.data.message : getMessage('genericError', 'Nao foi possivel concluir esta acao agora.'));
                    return;
                }

                $('#dps-email-subject').val(response.data.subject || '');
                $('#dps-email-body').val(response.data.body || '');
            }).fail(function() {
                closeModals();
                setRowFeedback($row, 'error', getMessage('genericError', 'Nao foi possivel concluir esta acao agora.'));
            });
        });
    }

    function bindSendEmailFlow() {
        $(document).on('click', '#dps-confirm-send-email', function() {
            var $button = $(this);
            var restore = withButtonLoading($button, getMessage('sendingEmail', 'Enviando e-mail...'));
            var $row = $('[data-client-row][data-client-id="' + state.emailModalClientId + '"]');

            ajaxPost('dps_send_email_with_token', {
                client_id: state.emailModalClientId,
                subject: $('#dps-email-subject').val(),
                body: $('#dps-email-body').val()
            }).done(function(response) {
                if (!response || !response.success) {
                    setRowFeedback($row, 'error', response && response.data && response.data.message ? response.data.message : getMessage('genericError', 'Nao foi possivel concluir esta acao agora.'));
                    return;
                }

                closeModals();
                setRowFeedback($row, 'success', response.data.message || 'E-mail enviado com sucesso.');
            }).fail(function() {
                setRowFeedback($row, 'error', getMessage('genericError', 'Nao foi possivel concluir esta acao agora.'));
            }).always(function() {
                restore();
            });
        });
    }

    function bindModalClose() {
        $(document).on('click', '[data-modal-close]', function() {
            closeModals();
        });

        $(document).on('keyup', function(event) {
            if (event.key === 'Escape') {
                closeModals();
            }
        });
    }

    $(document).ready(function() {
        bindCopyButtons();
        bindGenerateTokenFlow();
        bindRevokeFlow();
        bindPasswordAccessFlow();
        bindSyncUserFlow();
        bindGeneratedTokenActions();
        bindSendEmailFlow();
        bindModalClose();
    });
})(jQuery);
