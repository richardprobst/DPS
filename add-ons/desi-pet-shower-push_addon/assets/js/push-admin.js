/**
 * Desi Pet Shower - Push Notifications Add-on
 * Admin JavaScript
 *
 * @package DesiPetShower
 * @subpackage Push
 * @since 1.1.0
 */

(function($) {
    'use strict';

    var DPSPushAdmin = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initSectionToggle();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Test buttons
            $(document).on('click', '.dps-push-test-btn', this.handleTestClick.bind(this));
            
            // Telegram test connection
            $(document).on('click', '#dps-test-telegram', this.handleTelegramTest.bind(this));

            // Section toggle
            $(document).on('click', '.dps-push-section-header', this.toggleSection);

            // Enable/disable toggle - update status card
            $(document).on('change', '.dps-push-enable-toggle', this.updateStatusCard);
        },

        /**
         * Initialize section toggle state from storage
         */
        initSectionToggle: function() {
            var collapsed = localStorage.getItem('dps_push_collapsed_sections');
            if (collapsed) {
                try {
                    var sections = JSON.parse(collapsed);
                    sections.forEach(function(id) {
                        $('#' + id).addClass('collapsed');
                    });
                } catch (e) {
                    // Ignore parse errors
                }
            }
        },

        /**
         * Toggle section collapse
         */
        toggleSection: function(e) {
            // Don't toggle if clicking on switch or button
            if ($(e.target).closest('.dps-push-switch, .dps-push-test-btn').length) {
                return;
            }

            var $section = $(this).closest('.dps-push-section');
            $section.toggleClass('collapsed');

            // Save state
            var collapsed = [];
            $('.dps-push-section.collapsed').each(function() {
                if (this.id) {
                    collapsed.push(this.id);
                }
            });
            localStorage.setItem('dps_push_collapsed_sections', JSON.stringify(collapsed));
        },

        /**
         * Handle test button click
         */
        handleTestClick: function(e) {
            e.preventDefault();
            
            var $btn = $(e.currentTarget);
            var type = $btn.data('type');

            if ($btn.prop('disabled') || $btn.hasClass('loading')) {
                return;
            }

            this.sendTest(type, $btn);
        },

        /**
         * Send test notification
         */
        sendTest: function(type, $btn) {
            var self = this;
            var originalHtml = $btn.html();

            $btn.addClass('loading').prop('disabled', true);
            $btn.find('.dashicons').removeClass('dashicons-email-alt dashicons-controls-play')
                .addClass('dashicons-update');

            $.ajax({
                url: dps_push_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'dps_push_send_test',
                    type: type,
                    nonce: dps_push_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotice('success', response.data.message);
                    } else {
                        self.showNotice('error', response.data.message || dps_push_admin.i18n.error);
                    }
                },
                error: function() {
                    self.showNotice('error', dps_push_admin.i18n.error);
                },
                complete: function() {
                    $btn.removeClass('loading').prop('disabled', false);
                    $btn.html(originalHtml);
                }
            });
        },

        /**
         * Handle Telegram test connection
         */
        handleTelegramTest: function(e) {
            e.preventDefault();

            var $btn = $(e.currentTarget);
            var $status = $('.dps-telegram-status');
            var token = $('#telegram_token').val();
            var chatId = $('#telegram_chat').val();

            if (!token || !chatId) {
                this.showNotice('error', dps_push_admin.i18n.telegram_missing);
                return;
            }

            if ($btn.prop('disabled') || $btn.hasClass('loading')) {
                return;
            }

            $btn.addClass('loading').prop('disabled', true);
            $status.removeClass('success error pending').addClass('pending')
                .html('<span class="dashicons dashicons-update"></span> ' + dps_push_admin.i18n.testing);

            var self = this;

            $.ajax({
                url: dps_push_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'dps_push_test_telegram',
                    token: token,
                    chat_id: chatId,
                    nonce: dps_push_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.removeClass('pending error').addClass('success')
                            .html('<span class="dashicons dashicons-yes-alt"></span> ' + response.data.message);
                    } else {
                        $status.removeClass('pending success').addClass('error')
                            .html('<span class="dashicons dashicons-warning"></span> ' + (response.data.message || dps_push_admin.i18n.telegram_error));
                    }
                },
                error: function() {
                    $status.removeClass('pending success').addClass('error')
                        .html('<span class="dashicons dashicons-warning"></span> ' + dps_push_admin.i18n.error);
                },
                complete: function() {
                    $btn.removeClass('loading').prop('disabled', false);
                }
            });
        },

        /**
         * Update status card when toggling enable/disable
         */
        updateStatusCard: function() {
            var $toggle = $(this);
            var type = $toggle.data('type');
            var enabled = $toggle.is(':checked');
            var $statusItem = $('.dps-push-status-item[data-type="' + type + '"]');

            if (enabled) {
                $statusItem.removeClass('status-disabled').addClass('status-enabled');
                $statusItem.find('.dashicons').removeClass('dashicons-no-alt').addClass('dashicons-yes-alt');
            } else {
                $statusItem.removeClass('status-enabled').addClass('status-disabled');
                $statusItem.find('.dashicons').removeClass('dashicons-yes-alt').addClass('dashicons-no-alt');
            }
        },

        /**
         * Show admin notice
         */
        showNotice: function(type, message) {
            var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            var $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
            
            // Remove existing notices
            $('.wrap > .notice').remove();
            
            // Add new notice after title
            $('.wrap > h1').first().after($notice);

            // Trigger WP dismiss button
            if (typeof wp !== 'undefined' && wp.notices) {
                wp.notices.initialize();
            } else {
                // Fallback: add dismiss button manually
                $notice.append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button>');
                $notice.find('.notice-dismiss').on('click', function() {
                    $notice.fadeOut(function() { $(this).remove(); });
                });
            }

            // Scroll to notice
            $('html, body').animate({
                scrollTop: $notice.offset().top - 50
            }, 300);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        DPSPushAdmin.init();
    });

})(jQuery);
