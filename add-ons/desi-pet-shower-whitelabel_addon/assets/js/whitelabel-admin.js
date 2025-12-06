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
        initUrlValidation();
        initColorPresets();
        initSaveScrollBehavior();
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

    /**
     * Valida URLs em tempo real.
     */
    function initUrlValidation() {
        var $urlInputs = $(
            'input[name="brand_logo_url"], ' +
            'input[name="brand_logo_dark_url"], ' +
            'input[name="brand_favicon_url"], ' +
            'input[name="website_url"], ' +
            'input[name="support_url"], ' +
            'input[name="redirect_url"], ' +
            'input[name="docs_url"], ' +
            'input[name="terms_url"], ' +
            'input[name="privacy_url"]'
        );
        
        $urlInputs.on('blur', function() {
            var $input = $(this);
            var url = $input.val().trim();
            var $feedback = $input.next('.url-validation-feedback');
            
            // Remove feedback anterior
            $feedback.remove();
            $input.removeClass('url-valid url-invalid');
            
            if ( ! url ) {
                return; // Campo vazio é válido (opcional)
            }
            
            // Valida formato básico de URL
            var urlPattern = /^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/i;
            
            if ( urlPattern.test( url ) ) {
                $input.addClass('url-valid');
                $input.after('<span class="url-validation-feedback valid">✓ URL válida</span>');
            } else {
                $input.addClass('url-invalid');
                $input.after('<span class="url-validation-feedback invalid">✗ URL inválida</span>');
            }
        });
    }

    /**
     * Paletas de cores pré-definidas.
     */
    function initColorPresets() {
        var presets = {
            'default': {
                primary: '#0ea5e9',
                secondary: '#10b981',
                accent: '#f59e0b',
                background: '#f9fafb',
                text: '#374151'
            },
            'ocean': {
                primary: '#0891b2',
                secondary: '#06b6d4',
                accent: '#6366f1',
                background: '#f0f9ff',
                text: '#0c4a6e'
            },
            'forest': {
                primary: '#059669',
                secondary: '#10b981',
                accent: '#84cc16',
                background: '#f0fdf4',
                text: '#14532d'
            },
            'sunset': {
                primary: '#f97316',
                secondary: '#fb923c',
                accent: '#fbbf24',
                background: '#fff7ed',
                text: '#7c2d12'
            },
            'modern': {
                primary: '#8b5cf6',
                secondary: '#a78bfa',
                accent: '#ec4899',
                background: '#faf5ff',
                text: '#581c87'
            }
        };
        
        $('.dps-preset-btn').on('click', function(e) {
            e.preventDefault();
            
            var presetName = $(this).data('preset');
            var colors = presets[presetName];
            
            if ( ! colors ) {
                return;
            }
            
            // Aplica cores nos inputs
            $('#color_primary').val(colors.primary).wpColorPicker('color', colors.primary);
            $('#color_secondary').val(colors.secondary).wpColorPicker('color', colors.secondary);
            $('#color_accent').val(colors.accent).wpColorPicker('color', colors.accent);
            $('#color_background').val(colors.background).wpColorPicker('color', colors.background);
            $('#color_text').val(colors.text).wpColorPicker('color', colors.text);
            
            // Feedback visual
            $('.dps-preset-btn').removeClass('preset-applied');
            $(this).addClass('preset-applied');
            setTimeout(function() {
                $('.dps-preset-btn').removeClass('preset-applied');
            }, 1000);
        });
    }

    /**
     * Scroll automático para mensagens de sucesso/erro.
     */
    function initSaveScrollBehavior() {
        var $form = $('.dps-whitelabel-wrap form');
        
        if ( ! $form.length ) {
            return;
        }
        
        // Após carregar página, verifica se há mensagens e scroll para elas
        if ( $('.notice, .dps-alert, .settings-error').length ) {
            $('html, body').animate({
                scrollTop: $('.dps-whitelabel-wrap').offset().top - 50
            }, 300);
        }
    }

})(jQuery);
