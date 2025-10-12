/*
 * Script de frontend para o plugin Desi Pet Shower Base.
 * Fornece navegação por abas e filtragem dinâmica de pets em agendamentos.
 */
(function($){
  $(document).ready(function(){
    // Controle das abas
    var $tabs = $('.dps-nav .dps-tab-link');
    var $sections = $('.dps-section');

    // Determina tab inicial via parâmetro de URL (tab) ou pela primeira aba padrão
    var urlParams = new URLSearchParams(window.location.search);
    var initialTab = urlParams.get('tab');
    if (!initialTab) {
      var dpsEdit = urlParams.get('dps_edit');
      if (dpsEdit) {
        var map = {
          'client': 'clientes',
          'pet': 'pets',
          'appointment': 'agendas'
        };
        initialTab = map[dpsEdit];
      }
    }

    function activateTab(tab){
      $tabs.removeClass('active');
      $sections.removeClass('active');
      $tabs.filter('[data-tab="' + tab + '"]').addClass('active');
      $('#dps-section-' + tab).addClass('active');
    }

    if (initialTab && $tabs.filter('[data-tab="' + initialTab + '"]').length) {
      activateTab(initialTab);
    } else {
      var $first = $tabs.eq(0);
      activateTab($first.data('tab'));
    }

    // Clique das abas
    $tabs.on('click', function(e){
      e.preventDefault();
      activateTab($(this).data('tab'));
    });

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

    function refreshPetVisibility(){
      var ownerId = $clientSelect.val();
      var term = ($petSearch.val() || '').toLowerCase();
      var hasOwner = !!ownerId;
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
        var cardText = $card.text().toLowerCase();
        $card.toggle(cardText.indexOf(term) > -1);
      });
      if (hasOwner) {
        $petContainer.show();
      } else {
        $petContainer.hide();
      }
    }

    $clientSelect.on('change', function(){
      $petSearch.val('');
      refreshPetVisibility();
    });

    $petSearch.on('input', refreshPetVisibility);

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
      var defaultOrder = ['agendas','historico','clientes','pets','servicos','assinaturas','financeiro','estatisticas','backup','notificacoes'];
      var desiredOrder = window.dpsNavOrder || defaultOrder;
      var $nav = $('.dps-nav');
      if (!$nav.length) {
        return;
      }
      var itemsMap = {};
      $nav.find('li').each(function(){
        var $link = $(this).find('.dps-tab-link');
        var key   = $link.data('tab');
        if (key) {
          itemsMap[key] = $(this);
        }
      });
      desiredOrder.forEach(function(key){
        if (itemsMap[key]) {
          $nav.append(itemsMap[key]);
        }
      });
    })();
  });
})(jQuery);
