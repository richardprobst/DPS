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
    var activeNavOrder = [];
    $sections.attr('aria-hidden', 'true');

    function sanitizeOrderValue(value){
      if (!value && value !== 0) {
        return '';
      }
      return String(value).trim();
    }

    function collectNavInfo(){
      var info = {
        order: [],
        map: {}
      };
      if (!$nav.length) {
        return info;
      }
      $nav.find('li').each(function(){
        var $li = $(this);
        var $link = $li.find('.dps-tab-link').first();
        var key = sanitizeOrderValue($link.data('tab'));
        if (!key) {
          return;
        }
        if (!info.map[key]) {
          info.order.push(key);
          info.map[key] = $li;
        }
      });
      return info;
    }

    function normalizeOrderList(order){
      if (!order && order !== 0) {
        return [];
      }
      var list = [];
      if (Array.isArray(order)) {
        list = order;
      } else if (typeof order === 'string') {
        list = order.split(',');
      }
      return list.map(sanitizeOrderValue).filter(Boolean);
    }

    var navDefaultOrderAttr = sanitizeOrderValue($nav.data('default-order'));
    var navInitialInfo = collectNavInfo();
    var baseDefaultOrder = navDefaultOrderAttr
      ? navDefaultOrderAttr.split(',').map(sanitizeOrderValue).filter(Boolean)
      : navInitialInfo.order.slice();
    if (!baseDefaultOrder.length) {
      baseDefaultOrder = ['agendas','historico','clientes','pets','servicos','assinaturas','financeiro','estatisticas','backup','notificacoes'];
    }
    var navDefaultTab = sanitizeOrderValue($nav.data('default-tab'));

    function reorderNavigation(order, options){
      options = options || {};
      if (!$nav.length) {
        return baseDefaultOrder.slice();
      }
      var info = collectNavInfo();
      var normalized = normalizeOrderList(order);
      if (!normalized.length) {
        normalized = baseDefaultOrder.slice();
      }
      var fallback = baseDefaultOrder.concat(info.order);
      var seen = {};
      normalized = normalized
        .concat(fallback)
        .map(sanitizeOrderValue)
        .filter(function(key){
          if (!key || seen[key]) {
            return false;
          }
          seen[key] = true;
          return true;
        });

      var fragment = $(document.createDocumentFragment());
      normalized.forEach(function(key){
        if (info.map[key]) {
          fragment.append(info.map[key]);
          delete info.map[key];
        }
      });

      Object.keys(info.map).forEach(function(key){
        fragment.append(info.map[key]);
      });

      $nav.append(fragment);
      $tabs = $nav.find('.dps-tab-link');
      activeNavOrder = normalized.slice();

      if (!options.silent) {
        $(document).trigger('dps:navReordered', [$nav, normalized.slice()]);
      }

      return normalized;
    }

    activeNavOrder = reorderNavigation(baseDefaultOrder, { silent: true });
    if (typeof window.dpsNavOrder !== 'undefined' && window.dpsNavOrder !== null) {
      activeNavOrder = reorderNavigation(window.dpsNavOrder);
    }

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
      if (!initialTab && activeNavOrder.length) {
        initialTab = activeNavOrder[0];
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

      window.DPSBaseTabs = window.DPSBaseTabs || {};
      window.DPSBaseTabs.activate = activateTab;
      window.DPSBaseTabs.getActive = function(){
        var $active = $tabs.filter('.active').first();
        return sanitizeOrderValue($active.data('tab'));
      };
      window.dpsActivateTab = activateTab;
      $(document).on('dps:activateTab', function(event, tab, options){
        activateTab(tab, options || {});
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
     * Expõe utilitários para reordenar as abas e mantém compatibilidade com integrações antigas.
     */
    (function(){
      if (!$nav.length) {
        return;
      }
      window.DPSBaseNav = window.DPSBaseNav || {};
      window.DPSBaseNav.getOrder = function(){
        return collectNavInfo().order.slice();
      };
      window.DPSBaseNav.getDefaultOrder = function(){
        return baseDefaultOrder.slice();
      };
      window.DPSBaseNav.getActiveOrder = function(){
        return activeNavOrder.slice();
      };
      window.DPSBaseNav.getDefaultTab = function(){
        if (navDefaultTab) {
          return navDefaultTab;
        }
        return activeNavOrder[0] || '';
      };
      window.DPSBaseNav.reorder = function(order, options){
        return reorderNavigation(order, options || {});
      };
      window.dpsReorderNavigation = window.DPSBaseNav.reorder;

      $(document).on('dps:applyNavOrder', function(event, order, opts){
        reorderNavigation(order, opts || {});
      });
    })();
  });
})(jQuery);
