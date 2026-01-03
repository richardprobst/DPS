// Script to update the total value of services dynamically
jQuery(document).ready(function ($) {
  /**
   * Calcula o valor total somando o valor de cada serviço selecionado.
   * Habilita ou desabilita o campo de valor conforme o checkbox.
   */
  function getAppointmentType() {
    return $('input[name="appointment_type"]:checked').val() || 'simple';
  }

  function parseCurrency(value) {
    var num = parseFloat(value);
    return isNaN(num) ? 0 : num;
  }

  /**
   * Calcula o total de todos os extras em uma lista.
   */
  function calculateExtrasTotal(listSelector) {
    var total = 0;
    $(listSelector).find('.dps-extra-value-input').each(function () {
      total += parseCurrency($(this).val());
    });
    return total;
  }

  function updateSimpleTotal() {
    var total = 0;
    $('.dps-service-checkbox').each(function () {
      var checkbox = $(this);
      var priceInput = checkbox.closest('label').find('.dps-service-price');
      var price = parseCurrency(priceInput.val());
      if (checkbox.is(':checked')) {
        total += price;
        priceInput.prop('disabled', false);
      } else {
        priceInput.prop('disabled', true);
      }
    });
    
    // Adiciona extras (novo formato)
    if ($('#dps-simple-extras-container').is(':visible')) {
      total += calculateExtrasTotal('#dps-simple-extras-list');
    }
    // Compatibilidade com formato antigo
    if ($('#dps-simple-extra-fields').is(':visible')) {
      total += parseCurrency($('#dps-simple-extra-value').val());
    }
    
    if ($('#dps-taxidog-toggle').is(':checked')) {
      total += parseCurrency($('#dps-taxidog-price').val());
    }
    $('#dps-appointment-total').val(total.toFixed(2));
  }

  function updateSubscriptionTotal() {
    var total = parseCurrency($('#dps-subscription-base').val());
    if ($('#dps-tosa-toggle').is(':checked')) {
      total += parseCurrency($('#dps-tosa-price').val());
    }
    
    // Adiciona extras (novo formato)
    if ($('#dps-subscription-extras-container').is(':visible')) {
      total += calculateExtrasTotal('#dps-subscription-extras-list');
    }
    // Compatibilidade com formato antigo
    if ($('#dps-subscription-extra-fields').is(':visible')) {
      total += parseCurrency($('#dps-subscription-extra-value').val());
    }
    
    $('#dps-subscription-total').val(total.toFixed(2));
  }

  function updateTotal() {
    if (getAppointmentType() === 'subscription') {
      updateSubscriptionTotal();
    } else {
      updateSimpleTotal();
    }
  }

  /**
   * Aplica preços de acordo com o porte do(s) pet(s) selecionado(s).
   * Se houver múltiplos pets, usa o primeiro selecionado. Se não houver
   * variação para o porte, cai no valor padrão do serviço. Após ajustar
   * os valores, recalcula o total.
   */
  function applyPricesByPetSize() {
    var $petChoices = $('.dps-pet-checkbox');
    if ($petChoices.length === 0) {
      updateTotal();
      return;
    }
    var $selectedPet = $petChoices.filter(':checked').first();
    var selectedSize = null;
    if ($selectedPet.length) {
      var sizeAttr = $selectedPet.closest('.dps-pet-option').data('size');
      if (typeof sizeAttr === 'string') {
        sizeAttr = sizeAttr.toLowerCase();
        if (sizeAttr === 'pequeno') {
          selectedSize = 'small';
        } else if (sizeAttr === 'medio' || sizeAttr === 'médio') {
          selectedSize = 'medium';
        } else if (sizeAttr === 'grande') {
          selectedSize = 'large';
        }
      }
    }
    // Itera sobre cada serviço e define o preço apropriado
    $('.dps-service-checkbox').each(function () {
      var checkbox = $(this);
      var priceInput = checkbox.closest('label').find('.dps-service-price');
      // Valores definidos nas data attributes
      var defaultPrice = checkbox.data('price-default');
      var priceSmall  = checkbox.data('price-small');
      var priceMedium = checkbox.data('price-medium');
      var priceLarge  = checkbox.data('price-large');
      var newPrice = defaultPrice;
      // Se o campo de preço for um número vazio, parseFloat retornará NaN; portanto, usamos fallback
      if (selectedSize === 'small' && priceSmall !== undefined && priceSmall !== null && priceSmall !== '') {
        newPrice = priceSmall;
      } else if (selectedSize === 'medium' && priceMedium !== undefined && priceMedium !== null && priceMedium !== '') {
        newPrice = priceMedium;
      } else if (selectedSize === 'large' && priceLarge !== undefined && priceLarge !== null && priceLarge !== '') {
        newPrice = priceLarge;
      }
      // Define o valor no campo de preço somente se este campo ainda não foi editado manualmente.
      // Para simplificar, sempre atualizamos o valor quando o pet muda.
      if (newPrice !== undefined && newPrice !== null && newPrice !== '') {
        // Converte string para número e formata com duas casas decimais
        var floatVal = parseFloat(newPrice);
        if (!isNaN(floatVal)) {
          priceInput.val(floatVal.toFixed(2));
        }
      }
    });
    // Após ajustes, recalcula o total
    updateTotal();
  }

  // Atualiza total ao mudar os checkboxes ou valores individuais
  $(document).on('change', '.dps-service-checkbox, .dps-service-price', function () {
    updateTotal();
  });
  
  // === NOVO: Toggle para seção de extras redesenhada ===
  $(document).on('click', '.dps-extras-toggle', function (event) {
    event.preventDefault();
    var target = $(this).data('target');
    if (target) {
      var $target = $(target);
      if ($target.length) {
        var isExpanded = $(this).attr('aria-expanded') === 'true';
        if (isExpanded) {
          $target.slideUp(200);
          $(this).attr('aria-expanded', 'false');
        } else {
          $target.slideDown(200);
          $(this).attr('aria-expanded', 'true');
          // Adiciona uma linha vazia se não houver nenhuma
          var $list = $target.find('.dps-extras-list');
          if ($list.children().length === 0) {
            var type = $(this).closest('.dps-extras-section').find('.dps-add-extra-btn').data('type') || 'simple';
            addExtraRow($list, type);
          }
        }
        updateTotal();
      }
    }
  });
  
  // Compatibilidade: toggle antigo
  $(document).on('click', '.dps-extra-toggle', function (event) {
    event.preventDefault();
    var target = $(this).data('target');
    if (target) {
      var $target = $(target);
      if ($target.length) {
        $target.toggle();
        updateTotal();
      }
    }
  });
  
  // === NOVO: Adicionar linha de extra ===
  function addExtraRow($list, type) {
    var prefix = (type === 'subscription') ? 'subscription_extras' : 'appointment_extras';
    var index = $list.children().length;
    var html = '<div class="dps-extra-row" data-index="' + index + '">' +
      '<div class="dps-extra-row-fields">' +
      '<div class="dps-extra-description-field">' +
      '<input type="text" name="' + prefix + '_descriptions[]" value="" placeholder="Descrição do serviço" class="dps-extra-description-input">' +
      '</div>' +
      '<div class="dps-extra-value-field">' +
      '<div class="dps-input-with-prefix">' +
      '<span class="dps-input-prefix">R$</span>' +
      '<input type="number" step="0.01" min="0" name="' + prefix + '_values[]" value="" placeholder="0,00" class="dps-extra-value-input">' +
      '</div>' +
      '</div>' +
      '<button type="button" class="dps-btn dps-btn--icon dps-remove-extra-btn" title="Remover">' +
      '<span>✕</span>' +
      '</button>' +
      '</div>' +
      '</div>';
    $list.append(html);
  }
  
  $(document).on('click', '.dps-add-extra-btn', function (event) {
    event.preventDefault();
    var $list = $($(this).data('list'));
    var type = $(this).data('type') || 'simple';
    addExtraRow($list, type);
  });
  
  // === NOVO: Remover linha de extra ===
  $(document).on('click', '.dps-remove-extra-btn', function (event) {
    event.preventDefault();
    $(this).closest('.dps-extra-row').fadeOut(200, function () {
      $(this).remove();
      updateTotal();
    });
  });
  
  // === NOVO: Atualizar total quando valores de extras mudam ===
  $(document).on('input', '.dps-extra-value-input', function () {
    updateTotal();
  });
  
  $(document).on('change', '#dps-taxidog-toggle, input[name="appointment_type"]', function () {
    updateTotal();
  });
  $(document).on('input', '#dps-taxidog-price, #dps-simple-extra-value, #dps-subscription-base, #dps-subscription-extra-value, #dps-tosa-price', function () {
    updateTotal();
  });
  $(document).on('change', '#dps-tosa-toggle', function () {
    updateTotal();
  });
  // Aplica preços por porte quando o select de pets é modificado
  $(document).on('change', '.dps-pet-checkbox', function () {
    applyPricesByPetSize();
  });
  $(document).on('dps-pet-selection-updated', function () {
    applyPricesByPetSize();
  });
  // Impede valores negativos e formata campos
  $(document).on('input', '.dps-service-price, #dps-appointment-total, #dps-taxidog-price, #dps-simple-extra-value, #dps-subscription-base, #dps-subscription-total, #dps-subscription-extra-value, #dps-tosa-price', function () {
    var val = parseFloat($(this).val());
    if (isNaN(val) || val < 0) {
      $(this).val('0.00');
    }
  });
  // Ao carregar a página, aplica preços e atualiza total
  applyPricesByPetSize();
  updateTotal();
});