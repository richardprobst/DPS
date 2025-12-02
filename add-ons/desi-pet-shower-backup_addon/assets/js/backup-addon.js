/**
 * JavaScript do Backup Add-on
 *
 * @package DesiPetShower
 * @subpackage DPS_Backup_Addon
 * @since 1.1.0
 */

(function($) {
    'use strict';

    /**
     * Inicialização
     */
    $(document).ready(function() {
        DPSBackup.init();
    });

    /**
     * Objeto principal do Backup
     */
    var DPSBackup = {

        /**
         * Inicializa os handlers
         */
        init: function() {
            this.bindEvents();
            this.initUploadArea();
            this.initSelectAll();
        },

        /**
         * Vincula eventos
         */
        bindEvents: function() {
            // Confirmação de restauração
            $(document).on('submit', '.dps-restore-form', this.confirmRestore);
            
            // Toggle de agendamento
            $(document).on('change', '#dps-schedule-enabled', this.toggleScheduleFields);
            
            // Comparar backup antes de restaurar
            $(document).on('click', '.dps-compare-backup', this.compareBackup);
            
            // Deletar backup do histórico
            $(document).on('click', '.dps-delete-backup', this.deleteBackup);
            
            // Baixar backup do histórico
            $(document).on('click', '.dps-download-backup', this.downloadBackup);
            
            // Restaurar backup do histórico
            $(document).on('click', '.dps-restore-from-history', this.restoreFromHistory);
            
            // Fechar modal
            $(document).on('click', '.dps-modal-close, .dps-modal-overlay', this.closeModal);
            $(document).on('click', '.dps-modal', function(e) {
                e.stopPropagation();
            });
            
            // Esc para fechar modal
            $(document).on('keyup', function(e) {
                if (e.key === 'Escape') {
                    DPSBackup.closeModal();
                }
            });
        },

        /**
         * Inicializa área de upload com drag and drop
         */
        initUploadArea: function() {
            var $uploadArea = $('.dps-upload-area');
            var $fileInput = $uploadArea.find('input[type="file"]');

            $uploadArea.on('click', function() {
                $fileInput.trigger('click');
            });

            $uploadArea.on('dragover dragenter', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('dragover');
            });

            $uploadArea.on('dragleave drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragover');
            });

            $uploadArea.on('drop', function(e) {
                var files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    $fileInput[0].files = files;
                    DPSBackup.updateFileInfo(files[0]);
                }
            });

            $fileInput.on('change', function() {
                if (this.files.length > 0) {
                    DPSBackup.updateFileInfo(this.files[0]);
                }
            });
        },

        /**
         * Atualiza informações do arquivo selecionado
         */
        updateFileInfo: function(file) {
            var $info = $('.dps-file-info');
            if ($info.length === 0) {
                $info = $('<div class="dps-file-info"></div>');
                $('.dps-upload-area').after($info);
            }

            var size = this.formatBytes(file.size);
            $info.html('<p><strong>' + file.name + '</strong> (' + size + ')</p>');
        },

        /**
         * Formata bytes para exibição
         */
        formatBytes: function(bytes) {
            if (bytes >= 1073741824) {
                return (bytes / 1073741824).toFixed(2) + ' GB';
            } else if (bytes >= 1048576) {
                return (bytes / 1048576).toFixed(2) + ' MB';
            } else if (bytes >= 1024) {
                return (bytes / 1024).toFixed(2) + ' KB';
            }
            return bytes + ' bytes';
        },

        /**
         * Inicializa checkbox de selecionar todos
         */
        initSelectAll: function() {
            $(document).on('change', '#dps-select-all-components', function() {
                var checked = $(this).is(':checked');
                $('.dps-component-item input[type="checkbox"]').prop('checked', checked);
            });

            $(document).on('change', '.dps-component-item input[type="checkbox"]', function() {
                var total = $('.dps-component-item input[type="checkbox"]').length;
                var checked = $('.dps-component-item input[type="checkbox"]:checked').length;
                $('#dps-select-all-components').prop('checked', total === checked);
            });
        },

        /**
         * Confirma restauração
         */
        confirmRestore: function(e) {
            var $form = $(this);
            var $checkbox = $form.find('#dps-confirm-restore');
            
            if (!$checkbox.is(':checked')) {
                e.preventDefault();
                alert(dpsBackupL10n.confirmRequired || 'Você precisa confirmar que entende as consequências da restauração.');
                return false;
            }

            var confirmed = confirm(dpsBackupL10n.confirmRestore || 'ATENÇÃO: Esta ação irá substituir todos os dados do Desi Pet Shower. Deseja continuar?');
            
            if (!confirmed) {
                e.preventDefault();
                return false;
            }

            // Mostrar progress
            $('.dps-progress-container').addClass('active');
        },

        /**
         * Toggle dos campos de agendamento
         */
        toggleScheduleFields: function() {
            var enabled = $(this).is(':checked');
            $('.dps-schedule-fields').toggle(enabled);
        },

        /**
         * Compara backup antes de restaurar
         */
        compareBackup: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var backupId = $button.data('backup-id');
            
            $button.prop('disabled', true).text(dpsBackupL10n.comparing || 'Comparando...');

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'dps_compare_backup',
                    backup_id: backupId,
                    nonce: dpsBackupL10n.nonce
                },
                success: function(response) {
                    if (response.success) {
                        DPSBackup.showComparisonModal(response.data);
                    } else {
                        alert(response.data.message || dpsBackupL10n.error);
                    }
                },
                error: function() {
                    alert(dpsBackupL10n.error || 'Erro ao comparar backup.');
                },
                complete: function() {
                    $button.prop('disabled', false).text(dpsBackupL10n.compare || 'Comparar');
                }
            });
        },

        /**
         * Mostra modal de comparação
         */
        showComparisonModal: function(html) {
            var $modal = $('#dps-comparison-modal');
            
            if ($modal.length === 0) {
                $modal = $(
                    '<div class="dps-modal-overlay" id="dps-comparison-modal">' +
                    '<div class="dps-modal">' +
                    '<div class="dps-modal-header">' +
                    '<h3>' + (dpsBackupL10n.comparisonTitle || 'Comparação de Backup') + '</h3>' +
                    '<button type="button" class="dps-modal-close"><span class="dashicons dashicons-no-alt"></span></button>' +
                    '</div>' +
                    '<div class="dps-modal-body"></div>' +
                    '<div class="dps-modal-footer">' +
                    '<button type="button" class="button dps-modal-close">' + (dpsBackupL10n.close || 'Fechar') + '</button>' +
                    '</div>' +
                    '</div>' +
                    '</div>'
                );
                $('body').append($modal);
            }

            $modal.find('.dps-modal-body').html(html);
            $modal.addClass('active');
        },

        /**
         * Fecha modal
         */
        closeModal: function(e) {
            if (e && $(e.target).hasClass('dps-modal')) {
                return;
            }
            $('.dps-modal-overlay').removeClass('active');
        },

        /**
         * Deleta backup do histórico
         */
        deleteBackup: function(e) {
            e.preventDefault();
            
            if (!confirm(dpsBackupL10n.confirmDelete || 'Tem certeza que deseja excluir este backup?')) {
                return;
            }

            var $button = $(this);
            var $row = $button.closest('tr');
            var backupId = $button.data('backup-id');

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'dps_delete_backup',
                    backup_id: backupId,
                    nonce: dpsBackupL10n.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $row.fadeOut(300, function() {
                            $(this).remove();
                            // Verificar se não há mais backups
                            if ($('.dps-history-table tbody tr').length === 0) {
                                $('.dps-history-table').replaceWith(
                                    '<p class="description">' + (dpsBackupL10n.noBackups || 'Nenhum backup realizado ainda.') + '</p>'
                                );
                            }
                        });
                    } else {
                        alert(response.data.message || dpsBackupL10n.error);
                    }
                },
                error: function() {
                    alert(dpsBackupL10n.error || 'Erro ao excluir backup.');
                }
            });
        },

        /**
         * Baixa backup do histórico
         */
        downloadBackup: function(e) {
            e.preventDefault();
            
            var backupId = $(this).data('backup-id');
            var downloadUrl = ajaxurl + '?action=dps_download_backup&backup_id=' + backupId + '&nonce=' + dpsBackupL10n.nonce;
            
            window.location.href = downloadUrl;
        },

        /**
         * Restaura backup do histórico
         */
        restoreFromHistory: function(e) {
            e.preventDefault();
            
            if (!confirm(dpsBackupL10n.confirmRestore || 'ATENÇÃO: Esta ação irá substituir todos os dados. Deseja continuar?')) {
                return;
            }

            var $button = $(this);
            var backupId = $button.data('backup-id');

            $button.prop('disabled', true).text(dpsBackupL10n.restoring || 'Restaurando...');
            $('.dps-progress-container').addClass('active');

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'dps_restore_from_history',
                    backup_id: backupId,
                    nonce: dpsBackupL10n.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message || dpsBackupL10n.restoreSuccess);
                        window.location.reload();
                    } else {
                        alert(response.data.message || dpsBackupL10n.error);
                        $button.prop('disabled', false).text(dpsBackupL10n.restore || 'Restaurar');
                        $('.dps-progress-container').removeClass('active');
                    }
                },
                error: function() {
                    alert(dpsBackupL10n.error || 'Erro ao restaurar backup.');
                    $button.prop('disabled', false).text(dpsBackupL10n.restore || 'Restaurar');
                    $('.dps-progress-container').removeClass('active');
                }
            });
        },

        /**
         * Atualiza barra de progresso
         */
        updateProgress: function(percent, message) {
            $('.dps-progress-bar .progress').css('width', percent + '%');
            $('.dps-progress-text').text(message || percent + '%');
        }
    };

    // Expor para uso externo
    window.DPSBackup = DPSBackup;

})(jQuery);
