/**
 * Scripts da interface administrativa do White Label Add-on.
 *
 * @package DPS_WhiteLabel_Addon
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Inicialização
    $(document).ready(function() {
        initColorPickers();
        initMediaUploaders();
        initLoginBackgroundToggle();
        initTestEmail();
    });

    /**
     * Inicializa os color pickers.
     */
    function initColorPickers() {
        $('.dps-color-picker').each(function() {
            $(this).wpColorPicker();
        });
    }

    /**
     * Inicializa os media uploaders.
     */
    function initMediaUploaders() {
        var mediaFrame;

        // Botão de upload
        $(document).on('click', '.dps-media-upload-btn', function(e) {
            e.preventDefault();

            var $button = $(this);
            var $container = $button.closest('.dps-media-uploader');
            var $input = $container.find('.dps-media-url');
            var $removeBtn = $container.find('.dps-media-remove-btn');
            var $preview = $container.find('.dps-media-preview');

            // Cria ou reutiliza o frame
            mediaFrame = wp.media({
                title: dpsWhiteLabelL10n.selectImage || 'Selecionar Imagem',
                button: {
                    text: dpsWhiteLabelL10n.useImage || 'Usar esta imagem'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });

            // Quando uma imagem é selecionada
            mediaFrame.on('select', function() {
                var attachment = mediaFrame.state().get('selection').first().toJSON();
                $input.val(attachment.url);
                $removeBtn.show();
                
                // Atualiza ou cria preview
                if ($preview.length) {
                    $preview.find('img').attr('src', attachment.url);
                } else {
                    $container.append('<div class="dps-media-preview"><img src="' + attachment.url + '" alt=""></div>');
                }
            });

            mediaFrame.open();
        });

        // Botão de remover
        $(document).on('click', '.dps-media-remove-btn', function(e) {
            e.preventDefault();

            var $button = $(this);
            var $container = $button.closest('.dps-media-uploader');
            var $input = $container.find('.dps-media-url');
            var $preview = $container.find('.dps-media-preview');

            $input.val('');
            $button.hide();
            $preview.remove();
        });
    }

    /**
     * Toggle de tipo de background no login.
     */
    function initLoginBackgroundToggle() {
        var $select = $('#login_background_type');
        
        if (!$select.length) {
            return;
        }

        function toggleBackgroundFields() {
            var type = $select.val();
            
            $('.login-bg-color, .login-bg-image, .login-bg-gradient').hide();
            
            switch (type) {
                case 'color':
                    $('.login-bg-color').show();
                    break;
                case 'image':
                    $('.login-bg-image').show();
                    break;
                case 'gradient':
                    $('.login-bg-gradient').show();
                    break;
            }
        }

        $select.on('change', toggleBackgroundFields);
        toggleBackgroundFields(); // Estado inicial
    }

    /**
     * Envio de e-mail de teste.
     */
    function initTestEmail() {
        var $button = $('#dps-send-test-email');
        var $result = $('#test-email-result');
        var $emailInput = $('#test_email');

        if (!$button.length) {
            return;
        }

        $button.on('click', function(e) {
            e.preventDefault();

            var email = $emailInput.val();

            if (!email || !isValidEmail(email)) {
                $result
                    .removeClass('success loading')
                    .addClass('error')
                    .text(dpsWhiteLabelL10n.testEmailError || 'E-mail inválido.');
                return;
            }

            // Estado de loading
            $button.prop('disabled', true);
            $result
                .removeClass('success error')
                .addClass('loading')
                .text(dpsWhiteLabelL10n.testEmailSending || 'Enviando...');

            // Requisição AJAX
            $.ajax({
                url: dpsWhiteLabelL10n.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dps_whitelabel_test_email',
                    nonce: dpsWhiteLabelL10n.nonce,
                    email: email
                },
                success: function(response) {
                    $button.prop('disabled', false);
                    
                    if (response.success) {
                        $result
                            .removeClass('loading error')
                            .addClass('success')
                            .text(response.data.message || dpsWhiteLabelL10n.testEmailSuccess);
                    } else {
                        $result
                            .removeClass('loading success')
                            .addClass('error')
                            .text(response.data.message || dpsWhiteLabelL10n.testEmailError);
                    }
                },
                error: function() {
                    $button.prop('disabled', false);
                    $result
                        .removeClass('loading success')
                        .addClass('error')
                        .text(dpsWhiteLabelL10n.testEmailError || 'Erro na requisição.');
                }
            });
        });
    }

    /**
     * Valida formato de e-mail.
     * 
     * @param {string} email E-mail a validar.
     * @return {boolean} True se válido.
     */
    function isValidEmail(email) {
        var pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return pattern.test(email);
    }

})(jQuery);
