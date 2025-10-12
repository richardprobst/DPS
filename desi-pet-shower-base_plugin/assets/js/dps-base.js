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
    $tabs.removeClass('active');
    $sections.removeClass('active');
    if (initialTab) {
      $tabs.filter('[data-tab="' + initialTab + '"]').addClass('active');
      $('#dps-section-' + initialTab).addClass('active');
    } else {
      var $first = $tabs.eq(0);
      $first.addClass('active');
      $('#dps-section-' + $first.data('tab')).addClass('active');
    }
    // Clique das abas
    $tabs.on('click', function(e){
      e.preventDefault();
      var tab = $(this).data('tab');
      $tabs.removeClass('active');
      $(this).addClass('active');
      $sections.removeClass('active');
      $('#dps-section-' + tab).addClass('active');
    });
    // Filtro de busca nas tabelas
    $('.dps-search').on('input', function(){
      var $input   = $(this);
      var query    = $input.val().toLowerCase();
      var $section = $input.closest('.dps-section');
      $section.find('table.dps-table tbody tr').each(function(){
        var $row = $(this);
        var text = $row.text().toLowerCase();
        if ( text.indexOf( query ) > -1 ) {
          $row.show();
        } else {
          $row.hide();
        }
      });
    });
    // Filtra pets no agendamento de acordo com o cliente selecionado
    $('#dps-appointment-cliente').on('change', function(){
      var ownerId = $(this).val();
      var $petSelect = $('#dps-appointment-pet');
      $petSelect.find('option').each(function(){
        var $opt = $(this);
        var optOwner = $opt.data('owner');
        // Sempre mostra opção vazia
        if ($opt.val() === '') {
          $opt.show();
          return;
        }
        if (!ownerId) {
          $opt.show();
        } else {
          if (String(optOwner) === String(ownerId)) {
            $opt.show();
          } else {
            $opt.hide();
          }
        }
      });
      // Reseta seleção
      $petSelect.val('');
      // Oculta campo se nenhum tutor
      var $petField = $petSelect.closest('p');
      if (!ownerId) {
        $petField.hide();
      } else {
        $petField.show();
      }
    });
    // Esconde campo de pet inicialmente se nenhum cliente estiver selecionado e filtra pets se houver pré‑seleção
    (function(){
      var $petSelect = $('#dps-appointment-pet');
      var $petField  = $petSelect.closest('p');
      var $clientSel = $('#dps-appointment-cliente');
      var clientVal  = $clientSel.val();
      if (!clientVal) {
        $petField.hide();
      } else {
        // Gatilho de mudança para filtrar pets
        $clientSel.trigger('change');
      }
    })();

    /*
     * Reordena as abas de navegação conforme a ordem desejada.
     * Muitos add-ons adicionam suas próprias abas no final da lista padrão do plugin base. Para garantir
     * que a navegação siga a sequência definida pelo usuário (Clientes, Pets, Serviços, Agendamentos,
     * Assinaturas, Financeiro, Estatísticas, Senhas), recolhemos os itens existentes e os reaplicamos
     * na ordem especificada. Caso uma aba não exista (por exemplo, se um add‑on não estiver ativo), ela
     * simplesmente será ignorada.
     */
    (function(){
      // Nova ordem de abas: coloca Agendamentos como primeira e Notificações como última.
      // A ordem foi ajustada para melhorar a usabilidade conforme solicitação: Agendamentos, Clientes, Pets, Serviços,
      // Assinaturas, Financeiro, Estatísticas, Notificações. Se algumas abas não existirem (por exemplo, senhas
      // foram removidas e notificações podem não estar presentes), elas serão ignoradas.
      var desiredOrder = ['agendas','clientes','pets','servicos','assinaturas','financeiro','estatisticas','notificacoes'];
      var $nav = $('.dps-nav');
      if ($nav.length) {
        var $items = {};
        // Armazena cada item de navegação pela sua chave data‑tab
        $nav.find('li').each(function(){
          var $link = $(this).find('.dps-tab-link');
          var key   = $link.data('tab');
          if (key) {
            $items[key] = $(this);
          }
        });
        // Reaplica na ordem desejada
        desiredOrder.forEach(function(key){
          if ($items[key]) {
            $nav.append($items[key]);
          }
        });
      }
    })();
  });
})(jQuery);