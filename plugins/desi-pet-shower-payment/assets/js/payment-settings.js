/**
 * Payment Add-on Settings Page JavaScript
 *
 * Handles form submission with double-click prevention and loading state.
 *
 * @package DPS_Payment_Addon
 * @since 1.2.0
 */

(function($) {
    'use strict';

    /**
     * Initialize settings page functionality
     */
    function initSettingsPage() {
        var $form = $('#dps-payment-settings-form');
        var $submitBtn = $('#dps-payment-submit');
        
        if (!$form.length || !$submitBtn.length) {
            return;
        }

        var originalText = $submitBtn.val();
        var isSubmitting = false;

        // Prevent double click and show loading state
        $form.on('submit', function(e) {
            if (isSubmitting) {
                e.preventDefault();
                return false;
            }
            
            isSubmitting = true;
            $submitBtn.prop('disabled', true);
            $submitBtn.val(dpsPaymentSettings.savingText);
            $submitBtn.css('opacity', '0.7');
        });
    }

    // Initialize when DOM is ready
    $(document).ready(initSettingsPage);

})(jQuery);
