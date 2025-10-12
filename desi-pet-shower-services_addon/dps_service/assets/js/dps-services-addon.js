// Script to update the total value of services dynamically
jQuery(document).ready(function ($) {
  /**
   * Calcula o valor total somando o valor de cada serviço selecionado.
   * Habilita ou desabilita o campo de valor conforme o checkbox.
   */
  function updateTotal() {
    var total = 0;
    $('.dps-service-checkbox').each(function () {
      var checkbox = $(this);
      var priceInput = checkbox.closest('label').find('.dps-service-price');
      var price = parseFloat(priceInput.val()) || 0;
      if (checkbox.is(':checked')) {
        total += price;
        priceInput.prop('disabled', false);
      } else {
        priceInput.prop('disabled', true);
      }
    });
    $('#dps-appointment-total').val(total.toFixed(2));
  }

  /**
   * Aplica preços de acordo com o porte do(s) pet(s) selecionado(s).
   * Se houver múltiplos pets, usa o primeiro selecionado. Se não houver
   * variação para o porte, cai no valor padrão do serviço. Após ajustar
   * os valores, recalcula o total.
   */
  function applyPricesByPetSize() {
    var $petSelect = $('#dps-appointment-pet');
    if ($petSelect.length === 0) {
      updateTotal();
      return;
    }
    // Obtém o tamanho do primeiro pet selecionado
    var selectedSize = null;
    var $selectedOptions = $petSelect.find('option:selected');
    if ($selectedOptions.length > 0) {
      var sizeAttr = $($selectedOptions[0]).data('size');
      // Mapas para traduzir o valor armazenado no meta (pequeno, medio, grande) em chaves
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
  // Aplica preços por porte quando o select de pets é modificado
  $(document).on('change', '#dps-appointment-pet', function () {
    applyPricesByPetSize();
  });
  // Impede valores negativos e formata campos
  $(document).on('input', '.dps-service-price, #dps-appointment-total', function () {
    var val = parseFloat($(this).val());
    if (isNaN(val) || val < 0) {
      $(this).val('0.00');
    }
  });
  // Ao carregar a página, aplica preços e atualiza total
  applyPricesByPetSize();
});