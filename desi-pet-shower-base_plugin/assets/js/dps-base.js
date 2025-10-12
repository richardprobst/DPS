/*
 * Script de frontend para o plugin Desi Pet Shower Base.
 * Fornece navegação por abas e filtragem dinâmica de pets em agendamentos.
 */
(function($){
  $(document).ready(function(){
    // Controle das abas
    var $nav = $('.dps-nav');
    var $tabs = $nav.find('.dps-tab-link');
    var $sections = $('.dps-section');
    $sections.attr('aria-hidden', 'true');

    function sanitizeOrderValue(value){
      if (!value && value !== 0) {
        return '';
      }
      return String(value).trim();
    }

    var navDefaultOrderAttr = sanitizeOrderValue($nav.data('default-order'));
    var baseDefaultOrder = navDefaultOrderAttr
      ? navDefaultOrderAttr.split(',').map(sanitizeOrderValue).filter(Boolean)
      : ['agendas','historico','clientes','pets','servicos','assinaturas','financeiro','estatisticas','backup','notificacoes'];
    var navDefaultTab = sanitizeOrderValue($nav.data('default-tab'));

    if ($tabs.length) {
      // Determina tab inicial via parâmetro de URL (tab), hash (#tab=) ou valor padrão
      var urlParams = new URLSearchParams(window.location.search);
      var initialTab = sanitizeOrderValue(urlParams.get('tab'));
      if (!initialTab) {
        var hashMatch = (window.location.hash || '').match(/tab=([A-Za-z0-9_-]+)/);
        if (hashMatch && hashMatch[1]) {
          initialTab = sanitizeOrderValue(hashMatch[1]);
        }
      }
      if (!initialTab) {
        var dpsEdit = sanitizeOrderValue(urlParams.get('dps_edit'));
        if (dpsEdit) {
          var map = {
            'client': 'clientes',
            'pet': 'pets',
            'appointment': 'agendas'
          };
          initialTab = map[dpsEdit];
        }
      }
      if (!initialTab && navDefaultTab) {
        initialTab = navDefaultTab;
      }

      function updateUrlWithTab(tab){
        if (!tab || !window.history || !window.history.replaceState) {
          return;
        }
        try {
          var currentUrl = new URL(window.location.href);
          currentUrl.searchParams.set('tab', tab);
          window.history.replaceState({}, '', currentUrl.toString());
        } catch (err) {
          var baseUrl = window.location.href.split('#')[0];
          window.history.replaceState({}, '', baseUrl + '#tab=' + tab);
        }
      }

      function activateTab(tab, options){
        options = options || {};
        if (!tab) {
          return;
        }
        var $currentTab = $tabs.filter('[data-tab="' + tab + '"]').first();
        var $targetSection = $('#dps-section-' + tab);
        if (!$currentTab.length || !$targetSection.length) {
          return;
        }
        $tabs
          .removeClass('active')
          .attr('aria-selected', 'false')
          .attr('tabindex', '-1');
        $sections
          .removeClass('active')
          .attr('aria-hidden', 'true');
        $currentTab
          .addClass('active')
          .attr('aria-selected', 'true')
          .attr('tabindex', '0');
        $targetSection
          .addClass('active')
          .attr('aria-hidden', 'false');

        if (!options.skipUrlUpdate) {
          updateUrlWithTab(tab);
        }

        $(document).trigger('dps:tabChanged', [tab, $targetSection]);
      }

      if (initialTab && $tabs.filter('[data-tab="' + initialTab + '"]').length) {
        activateTab(initialTab, { skipUrlUpdate: true });
      } else {
        var $first = $tabs.eq(0);
        activateTab($first.data('tab') || '', { skipUrlUpdate: true });
      }

      // Clique das abas
      $tabs.on('click', function(e){
        e.preventDefault();
        activateTab($(this).data('tab'));
      });
    }

    // Filtro de busca nas tabelas
    $('.dps-search').on('input', function(){
      var query    = $(this).val().toLowerCase();
      var $section = $(this).closest('.dps-section');
      $section.find('table.dps-table tbody tr').each(function(){
        var text = $(this).text().toLowerCase();
        $(this).toggle(text.indexOf(query) > -1);
      });
    });

    // Seleção de pets no agendamento
    var $petContainer = $('#dps-appointment-pet-container');
    var $petGrid = $('#dps-appointment-pet-grid');
    var $petSearch = $('#dps-appointment-pet-search');
    var $clientSelect = $('#dps-appointment-cliente');

    function refreshPetVisibility(options){
      options = options || {};
      if (options.resetSearch) {
        $petSearch.val('');
      }
      var ownerId = options.hasOwnProperty('ownerId') ? options.ownerId : $clientSelect.val();
      var term = options.hasOwnProperty('searchTerm') ? String(options.searchTerm || '').toLowerCase() : ($petSearch.val() || '').toLowerCase();
      var hasOwner = ownerId !== undefined && ownerId !== null && sanitizeOrderValue(ownerId) !== '';

      if (!$petGrid.length) {
        return;
      }

      $petGrid.find('.dps-pet-card').each(function(){
        var $card = $(this);
        var matchesOwner = !hasOwner || String($card.data('owner')) === String(ownerId);
        if (!matchesOwner) {
          $card.hide();
          $card.find('input[type="checkbox"]').prop('checked', false).trigger('change');
          return;
        }
        if (!term) {
          $card.show();
          return;
        }
        var datasetText = $card.data('search');
        var cardText = datasetText ? String(datasetText).toLowerCase() : $card.text().toLowerCase();
        $card.toggle(cardText.indexOf(term) > -1);
      });

      if (hasOwner) {
        $petContainer.attr('aria-hidden', 'false').show();
      } else {
        $petContainer.attr('aria-hidden', 'true').hide();
      }

      $(document).trigger('dps:petSelectorUpdated', {
        ownerId: ownerId,
        term: term,
        hasOwner: hasOwner
      });
    }

    $clientSelect.on('change', function(){
      var ownerId = $(this).val();
      refreshPetVisibility({ ownerId: ownerId, resetSearch: true });
    });

    $petSearch.on('input', function(){
      refreshPetVisibility();
    });

    $(document).on('dps:refreshPetSelector', function(event, args){
      refreshPetVisibility(args || {});
    });

    // Estado inicial
    refreshPetVisibility();

    /*
     * Reordena as abas de navegação conforme a ordem desejada.
     * Muitos add-ons adicionam suas próprias abas no final da lista padrão do plugin base. Para garantir
     * que a navegação siga a sequência definida pelo usuário, recolhemos os itens existentes e os reaplicamos
     * na ordem especificada. Caso uma aba não exista (por exemplo, se um add-on não estiver ativo), ela
     * simplesmente será ignorada.
     */
    (function(){
      if (!$nav.length) {
        return;
      }
      var desiredOrder = window.dpsNavOrder || [];
      if (typeof desiredOrder === 'string') {
        desiredOrder = desiredOrder.split(',');
      }
      if (!Array.isArray(desiredOrder)) {
        desiredOrder = [];
      }
      var mergedOrder = desiredOrder.concat(baseDefaultOrder);
      var seen = {};
      mergedOrder = mergedOrder.map(sanitizeOrderValue).filter(function(key){
        if (!key || seen[key]) {
          return false;
        }
        seen[key] = true;
        return true;
      });

      var itemsMap = {};
      $nav.find('li').each(function(){
        var $link = $(this).find('.dps-tab-link');
        var key   = sanitizeOrderValue($link.data('tab'));
        if (key) {
          itemsMap[key] = $(this);
        }
      });

      mergedOrder.forEach(function(key){
        if (itemsMap[key]) {
          $nav.append(itemsMap[key]);
          delete itemsMap[key];
        }
      });

      Object.keys(itemsMap).forEach(function(key){
        $nav.append(itemsMap[key]);
      });
    })();
  });
})(jQuery);
