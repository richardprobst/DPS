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
    // Usa a nova função que considera múltiplos pets
    updateSimpleTotalMultiPet();
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
   * Converte o tamanho do pet (string em português) para chave de preço.
   */
  function normalizePetSize(sizeAttr) {
    if (typeof sizeAttr !== 'string') {
      return null;
    }
    sizeAttr = sizeAttr.toLowerCase();
    if (sizeAttr === 'pequeno') {
      return 'small';
    } else if (sizeAttr === 'medio' || sizeAttr === 'médio') {
      return 'medium';
    } else if (sizeAttr === 'grande') {
      return 'large';
    }
    return null;
  }

  /**
   * Retorna o preço do serviço para um determinado porte de pet.
   */
  function getServicePriceForSize(checkbox, size) {
    var defaultPrice = checkbox.data('price-default');
    var priceSmall  = checkbox.data('price-small');
    var priceMedium = checkbox.data('price-medium');
    var priceLarge  = checkbox.data('price-large');
    var price = defaultPrice;
    
    if (size === 'small' && priceSmall !== undefined && priceSmall !== null && priceSmall !== '') {
      price = priceSmall;
    } else if (size === 'medium' && priceMedium !== undefined && priceMedium !== null && priceMedium !== '') {
      price = priceMedium;
    } else if (size === 'large' && priceLarge !== undefined && priceLarge !== null && priceLarge !== '') {
      price = priceLarge;
    }
    
    return parseCurrency(price);
  }

  /**
   * Obtém os pets selecionados com seus respectivos portes.
   * @returns {Array} Lista de objetos {id, name, size, sizeLabel}
   */
  function getSelectedPetsWithSize() {
    var pets = [];
    $('.dps-pet-checkbox:checked').each(function () {
      var $checkbox = $(this);
      var $option = $checkbox.closest('.dps-pet-option');
      var name = $option.find('.dps-pet-name').text().trim();
      var sizeAttr = $option.data('size') || $option.attr('data-size');
      var size = normalizePetSize(sizeAttr);
      var sizeLabel = sizeAttr ? sizeAttr.charAt(0).toUpperCase() + sizeAttr.slice(1) : '';
      
      pets.push({
        id: $checkbox.val(),
        name: name,
        size: size,
        sizeLabel: sizeLabel
      });
    });
    return pets;
  }

  /**
   * Calcula o valor total dos serviços considerando múltiplos pets.
   * Para cada pet selecionado, soma o valor do serviço ajustado pelo porte.
   * @returns {Object} {total: number, breakdown: Array}
   */
  function calculateMultiPetServicesTotal() {
    var pets = getSelectedPetsWithSize();
    var petCount = pets.length;
    var total = 0;
    var breakdown = []; // Detalhamento por pet
    
    if (petCount === 0) {
      // Se nenhum pet selecionado, usa o cálculo tradicional (valor base)
      $('.dps-service-checkbox:checked').each(function () {
        var checkbox = $(this);
        var priceInput = checkbox.closest('label').find('.dps-service-price');
        var price = parseCurrency(priceInput.val());
        total += price;
      });
      return { total: total, breakdown: [], pets: [] };
    }
    
    // Para cada pet selecionado, calcula o valor dos serviços
    pets.forEach(function (pet) {
      var petTotal = 0;
      var petServices = [];
      
      $('.dps-service-checkbox:checked').each(function () {
        var checkbox = $(this);
        var serviceName = checkbox.closest('label').text().split('(R$')[0].trim();
        var price = getServicePriceForSize(checkbox, pet.size);
        petTotal += price;
        petServices.push({
          name: serviceName,
          price: price
        });
      });
      
      breakdown.push({
        pet: pet,
        total: petTotal,
        services: petServices
      });
      
      total += petTotal;
    });
    
    return { total: total, breakdown: breakdown, pets: pets };
  }

  /**
   * Verifica se há pets de portes diferentes selecionados.
   * @returns {Object} { hasDifferentSizes: boolean, sizes: Array, sizeLabels: Object }
   */
  function checkSelectedPetSizes() {
    var pets = getSelectedPetsWithSize();
    var sizes = [];
    var sizeLabels = {};
    
    pets.forEach(function(pet) {
      if (pet.size && sizes.indexOf(pet.size) === -1) {
        sizes.push(pet.size);
        sizeLabels[pet.size] = pet.sizeLabel;
      }
    });
    
    return {
      hasDifferentSizes: sizes.length > 1,
      sizes: sizes,
      sizeLabels: sizeLabels,
      petCount: pets.length,
      pets: pets
    };
  }

  /**
   * Formata a faixa de preços de um serviço baseado nos portes selecionados.
   * @returns {Object} { min: number, max: number, range: string, hasDifferentPrices: boolean }
   */
  function getServicePriceRange(checkbox, sizeInfo) {
    var prices = [];
    
    sizeInfo.sizes.forEach(function(size) {
      var price = getServicePriceForSize(checkbox, size);
      if (price > 0) {
        prices.push(price);
      }
    });
    
    if (prices.length === 0) {
      var defaultPrice = parseCurrency(checkbox.data('price-default'));
      return { min: defaultPrice, max: defaultPrice, range: 'R$ ' + defaultPrice.toFixed(2), hasDifferentPrices: false };
    }
    
    var min = Math.min.apply(null, prices);
    var max = Math.max.apply(null, prices);
    var hasDifferentPrices = Math.abs(min - max) > 0.01;
    
    var range = hasDifferentPrices 
      ? 'R$ ' + min.toFixed(2) + ' – ' + max.toFixed(2)
      : 'R$ ' + min.toFixed(2);
    
    return { min: min, max: max, range: range, hasDifferentPrices: hasDifferentPrices };
  }

  /**
   * Atualiza a mensagem informativa sobre múltiplos pets.
   */
  function updateMultiPetInfoMessage(sizeInfo) {
    var $infoContainer = $('#dps-multi-pet-info');
    
    // Remove mensagem existente se não há múltiplos pets com portes diferentes
    if (!sizeInfo.hasDifferentSizes || sizeInfo.petCount < 2) {
      $infoContainer.slideUp(200, function() { $(this).empty(); });
      // Remove indicadores de faixa de preço
      $('.dps-service-price-range').remove();
      return;
    }
    
    // Monta a lista de pets por porte
    var porteSummary = [];
    var porteLabels = { small: 'Pequeno', medium: 'Médio', large: 'Grande' };
    var porteCounts = {};
    
    sizeInfo.pets.forEach(function(pet) {
      if (pet.size) {
        if (!porteCounts[pet.size]) {
          porteCounts[pet.size] = [];
        }
        porteCounts[pet.size].push(pet.name);
      }
    });
    
    Object.keys(porteCounts).forEach(function(size) {
      var label = porteLabels[size] || size;
      var names = porteCounts[size].join(', ');
      porteSummary.push('<span class="dps-porte-badge dps-porte-' + size + '">' + label + '</span>: ' + names);
    });
    
    var message = '<div class="dps-multi-pet-message">' +
      '<span class="dps-info-icon">ℹ️</span>' +
      '<div class="dps-info-content">' +
      '<strong>Múltiplos pets com portes diferentes</strong>' +
      '<p>Os preços dos serviços variam por porte. O valor total será calculado individualmente para cada pet:</p>' +
      '<div class="dps-pets-by-size">' + porteSummary.join(' | ') + '</div>' +
      '</div></div>';
    
    // Fallback: cria container se PHP não o renderizou (compatibilidade com templates customizados)
    if ($infoContainer.length === 0) {
      $('.dps-services-fields .dps-simple-fields').prepend('<div id="dps-multi-pet-info"></div>');
      $infoContainer = $('#dps-multi-pet-info');
    }
    
    $infoContainer.html(message).slideDown(200);
  }

  /**
   * Atualiza indicadores visuais de faixa de preço para cada serviço.
   */
  function updateServicePriceRangeIndicators(sizeInfo) {
    $('.dps-service-checkbox').each(function() {
      var $checkbox = $(this);
      var $label = $checkbox.closest('label');
      var $priceWrapper = $label.find('.dps-service-price-wrapper');
      
      // Remove indicador existente
      $priceWrapper.find('.dps-service-price-range').remove();
      
      if (!sizeInfo.hasDifferentSizes || sizeInfo.petCount < 2) {
        return;
      }
      
      var priceRange = getServicePriceRange($checkbox, sizeInfo);
      
      if (priceRange.hasDifferentPrices) {
        var rangeHtml = '<span class="dps-service-price-range" title="Preço varia de acordo com o porte do pet">' +
          '<small>(' + priceRange.range + ')</small></span>';
        $priceWrapper.append(rangeHtml);
      }
    });
  }

  /**
   * Atualiza a exibição de preços dos serviços baseado nos portes dos pets selecionados.
   * Formato: "serviço - (Porte R$ valor)" para cada porte selecionado
   */
  function updateServicePricesDisplay(sizeInfo) {
    var sizeLabelsMap = {
      small: 'Pequeno',
      medium: 'Médio',
      large: 'Grande'
    };
    
    // Helper para verificar se um preço é válido
    function hasValidPrice(price) {
      return price !== undefined && price !== null && price !== '';
    }
    
    $('.dps-service-checkbox').each(function() {
      var $checkbox = $(this);
      var $item = $checkbox.closest('.dps-service-item');
      var $pricesDisplay = $item.find('.dps-service-prices-display');
      
      if (!$pricesDisplay.length) {
        return;
      }
      
      var defaultPrice = parseCurrency($checkbox.data('price-default'));
      var priceSmall = $checkbox.data('price-small');
      var priceMedium = $checkbox.data('price-medium');
      var priceLarge = $checkbox.data('price-large');
      
      // Verifica se há preços por porte
      var hasSmall = hasValidPrice(priceSmall);
      var hasMedium = hasValidPrice(priceMedium);
      var hasLarge = hasValidPrice(priceLarge);
      var hasSizePrices = hasSmall || hasMedium || hasLarge;
      
      if (!hasSizePrices) {
        // Preço único - mostra só o valor padrão
        $pricesDisplay.html('<span class="dps-service-price-single">R$ ' + defaultPrice.toFixed(2).replace('.', ',') + '</span>');
        return;
      }
      
      // Se nenhum pet selecionado, mostra todos os portes disponíveis
      if (sizeInfo.petCount === 0) {
        var allPrices = [];
        if (hasSmall) {
          allPrices.push('<span class="dps-price-size" data-size="small"><span class="dps-price-label">P</span> R$ ' + parseCurrency(priceSmall).toFixed(2).replace('.', ',') + '</span>');
        }
        if (hasMedium) {
          allPrices.push('<span class="dps-price-size" data-size="medium"><span class="dps-price-label">M</span> R$ ' + parseCurrency(priceMedium).toFixed(2).replace('.', ',') + '</span>');
        }
        if (hasLarge) {
          allPrices.push('<span class="dps-price-size" data-size="large"><span class="dps-price-label">G</span> R$ ' + parseCurrency(priceLarge).toFixed(2).replace('.', ',') + '</span>');
        }
        $pricesDisplay.html('<span class="dps-service-prices-multi">' + allPrices.join(' ') + '</span>');
        return;
      }
      
      // Mostra apenas os portes dos pets selecionados
      var filteredPrices = [];
      sizeInfo.sizes.forEach(function(size) {
        var price = 0;
        var label = '';
        
        if (size === 'small' && hasSmall) {
          price = parseCurrency(priceSmall);
          label = 'P';
        } else if (size === 'medium' && hasMedium) {
          price = parseCurrency(priceMedium);
          label = 'M';
        } else if (size === 'large' && hasLarge) {
          price = parseCurrency(priceLarge);
          label = 'G';
        } else {
          // Usa o preço padrão se não houver preço específico para o porte
          price = defaultPrice;
          // Usa a primeira letra do nome do porte como fallback
          label = sizeLabelsMap[size] ? sizeLabelsMap[size].charAt(0) : '-';
        }
        
        filteredPrices.push('<span class="dps-price-size" data-size="' + size + '"><span class="dps-price-label">' + label + '</span> R$ ' + price.toFixed(2).replace('.', ',') + '</span>');
      });
      
      if (filteredPrices.length > 0) {
        $pricesDisplay.html('<span class="dps-service-prices-multi">' + filteredPrices.join(' ') + '</span>');
      } else {
        $pricesDisplay.html('<span class="dps-service-price-single">R$ ' + defaultPrice.toFixed(2).replace('.', ',') + '</span>');
      }
    });
  }

  /**
   * Aplica preços de acordo com o porte do(s) pet(s) selecionado(s).
   * Se houver múltiplos pets, usa o primeiro selecionado para exibir no campo de preço,
   * mas o cálculo do total considera todos os pets.
   */
  function applyPricesByPetSize() {
    var $petChoices = $('.dps-pet-checkbox');
    if ($petChoices.length === 0) {
      updateTotal();
      return;
    }
    
    // Verifica se há portes diferentes selecionados
    var sizeInfo = checkSelectedPetSizes();
    
    // Atualiza mensagem informativa e indicadores visuais
    updateMultiPetInfoMessage(sizeInfo);
    updateServicePriceRangeIndicators(sizeInfo);
    
    // Atualiza exibição de preços baseado nos portes selecionados
    updateServicePricesDisplay(sizeInfo);
    
    // Usa o primeiro pet para atualizar os campos de preço visíveis
    var $selectedPet = $petChoices.filter(':checked').first();
    var selectedSize = null;
    if ($selectedPet.length) {
      var sizeAttr = $selectedPet.closest('.dps-pet-option').data('size') || 
                     $selectedPet.closest('.dps-pet-option').attr('data-size');
      selectedSize = normalizePetSize(sizeAttr);
    }
    
    // Itera sobre cada serviço e define o preço apropriado (do primeiro pet)
    $('.dps-service-checkbox').each(function () {
      var checkbox = $(this);
      var priceInput = checkbox.closest('label').find('.dps-service-price');
      var newPrice = getServicePriceForSize(checkbox, selectedSize);
      
      if (newPrice !== undefined && !isNaN(newPrice)) {
        priceInput.val(newPrice.toFixed(2));
      }
    });
    
    // Após ajustes, recalcula o total considerando TODOS os pets
    updateTotal();
  }

  /**
   * Atualiza o campo de total do agendamento considerando múltiplos pets.
   */
  function updateSimpleTotalMultiPet() {
    var multiPetResult = calculateMultiPetServicesTotal();
    var total = multiPetResult.total;
    
    // Desabilita campos de preço de serviços não selecionados
    $('.dps-service-checkbox').each(function () {
      var checkbox = $(this);
      var priceInput = checkbox.closest('label').find('.dps-service-price');
      priceInput.prop('disabled', !checkbox.is(':checked'));
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
    
    // Subtrai o desconto se houver
    if ($('#dps-discount-container').is(':visible')) {
      var discount = parseCurrency($('.dps-discount-value-input').val());
      total = Math.max(0, total - discount);
    }
    
    $('#dps-appointment-total').val(total.toFixed(2));
    
    // Dispara evento customizado para outros scripts reagirem
    $(document).trigger('dps-multi-pet-total-updated', [multiPetResult]);
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
  $(document).on('input', '.dps-service-price, #dps-appointment-total, #dps-taxidog-price, #dps-simple-extra-value, #dps-subscription-base, #dps-subscription-total, #dps-subscription-extra-value, #dps-tosa-price, .dps-discount-value-input, .dps-subscription-pet-value', function () {
    var val = parseFloat($(this).val());
    if (isNaN(val) || val < 0) {
      $(this).val('0.00');
    }
  });
  
  // === NOVO: Toggle do botão de desconto ===
  $(document).on('click', '.dps-discount-toggle', function (event) {
    event.preventDefault();
    var $button = $(this);
    var targetSelector = $button.data('target');
    var $target = $(targetSelector);
    var isExpanded = $button.attr('aria-expanded') === 'true';
    
    if (isExpanded) {
      $target.slideUp(200);
      $button.attr('aria-expanded', 'false');
    } else {
      $target.slideDown(200);
      $button.attr('aria-expanded', 'true');
    }
  });
  
  // === NOVO: Atualizar total quando desconto é alterado ===
  $(document).on('input', '.dps-discount-value-input', function () {
    updateTotal();
  });
  
  // === NOVO: Geração de campos de valor por pet para assinaturas ===
  function updateSubscriptionPetValues() {
    var $container = $('#dps-subscription-pets-values');
    if (!$container.length) {
      return;
    }
    
    var appointmentType = getAppointmentType();
    if (appointmentType !== 'subscription') {
      return;
    }
    
    var pets = getSelectedPetsWithSize();
    
    if (pets.length === 0) {
      $container.html('<p class="dps-subscription-pets-hint">Selecione os pets acima para definir os valores individuais da assinatura.</p>');
      return;
    }
    
    var html = '<h4 class="dps-subscription-pets-title">Valor por Pet</h4>';
    
    pets.forEach(function(pet, index) {
      var sizeText = pet.sizeLabel || 'Porte não definido';
      var sizeClass = pet.size || 'unknown';
      
      html += '<div class="dps-subscription-pet-value-row" data-pet-id="' + pet.id + '">';
      html += '<div class="dps-subscription-pet-info">';
      html += '<span class="dps-subscription-pet-name">' + pet.name + '</span>';
      html += '<span class="dps-subscription-pet-size"><span class="dps-subscription-pet-size-badge dps-size-' + sizeClass + '">' + sizeText + '</span></span>';
      html += '</div>';
      html += '<div class="dps-subscription-pet-value-field">';
      html += '<div class="dps-input-with-prefix">';
      html += '<span class="dps-input-prefix">R$</span>';
      html += '<input type="number" step="0.01" min="0" name="subscription_pet_values[' + pet.id + ']" value="" placeholder="0,00" class="dps-subscription-pet-value">';
      html += '</div>';
      html += '</div>';
      html += '</div>';
    });
    
    $container.html(html);
  }
  
  // Atualiza valores por pet quando pets são selecionados
  $(document).on('change', '.dps-pet-checkbox', function () {
    if (getAppointmentType() === 'subscription') {
      updateSubscriptionPetValues();
    }
  });
  
  // Also update when custom pet selection event is triggered
  $(document).on('dps-pet-selection-updated', function () {
    if (getAppointmentType() === 'subscription') {
      updateSubscriptionPetValues();
    }
  });
  
  $(document).on('change', 'input[name="appointment_type"]', function () {
    if ($(this).val() === 'subscription') {
      updateSubscriptionPetValues();
    }
  });
  
  // Atualiza total quando valores de pets da assinatura mudam
  $(document).on('input', '.dps-subscription-pet-value', function () {
    var total = 0;
    $('.dps-subscription-pet-value').each(function () {
      total += parseCurrency($(this).val());
    });
    
    // Adiciona extras se houver
    if ($('#dps-subscription-extras-container').is(':visible')) {
      total += calculateExtrasTotal('#dps-subscription-extras-list');
    }
    
    $('#dps-subscription-total').val(total.toFixed(2));
  });
  
  // Ao carregar a página, aplica preços e atualiza total
  applyPricesByPetSize();
  updateTotal();
  
  // Inicializa valores por pet se for assinatura
  if (getAppointmentType() === 'subscription') {
    updateSubscriptionPetValues();
  }
});