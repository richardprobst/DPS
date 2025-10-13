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
    // Nova seleção de pets baseada em checkboxes filtradas por tutor
    (function(){
      var $clientSelect = $('#dps-appointment-cliente');
      var $petWrapper   = $('#dps-appointment-pet-wrapper');
      if (!$petWrapper.length) {
        return;
      }
      var $petOptions   = $petWrapper.find('.dps-pet-option');
      var $petCheckboxes = $petWrapper.find('.dps-pet-checkbox');
      var $filterInput  = $('#dps-pet-filter');
      var $noPetsMsg    = $('#dps-no-pets-message');
      var $summary      = $('#dps-pet-summary');
      var $selectHint   = $('#dps-pet-select-client');

      function updateSummary(){
        var selected = $petCheckboxes.filter(':checked');
        if (selected.length) {
          var names = selected.map(function(){
            return $(this).closest('.dps-pet-option').find('.dps-pet-name').text();
          }).get();
          $summary.text(selected.length === 1 ?
            dpsBaseL10n.summarySingle.replace('%s', names[0]) :
            dpsBaseL10n.summaryMultiple.replace('%d', selected.length).replace('%s', names.join(', '))
          );
          $summary.show();
        } else {
          $summary.hide();
        }
      }

      function applyPetFilters(){
        var ownerId = $clientSelect.val();
        var query   = ($filterInput.val() || '').toLowerCase();
        var visible = 0;
        $petOptions.each(function(){
          var $option = $(this);
          var optionOwner = $option.data('owner');
          var matchesOwner = !ownerId || String(optionOwner) === String(ownerId);
          if (!matchesOwner) {
            $option.find('.dps-pet-checkbox').prop('checked', false);
          }
          var searchable = ($option.data('search') || '').toLowerCase();
          var matchesQuery = !query || searchable.indexOf(query) !== -1;
          var show = matchesOwner && matchesQuery;
          $option.toggle(show);
          if (show) {
            visible++;
          }
        });
        $noPetsMsg.toggle(visible === 0 && !!ownerId);
        $selectHint.toggle(!ownerId);
        $petWrapper.toggleClass('dps-pet-picker--disabled', !ownerId);
        $filterInput.prop('disabled', !ownerId);
        $petWrapper.find('.dps-pet-toggle').prop('disabled', !ownerId || visible === 0);
        updateSummary();
        $(document).trigger('dps-pet-selection-updated');
      }

      $clientSelect.on('change', function(){
        applyPetFilters();
      });

      $filterInput.on('input', function(){
        applyPetFilters();
      });

      $petWrapper.on('change', '.dps-pet-checkbox', function(){
        updateSummary();
        $(document).trigger('dps-pet-selection-updated');
      });

      $petWrapper.on('click', '.dps-pet-toggle', function(e){
        e.preventDefault();
        var action = $(this).data('action');
        var ownerId = $clientSelect.val();
        var query   = ($filterInput.val() || '').toLowerCase();
        $petOptions.each(function(){
          var $option = $(this);
          var optionOwner = $option.data('owner');
          var searchable = ($option.data('search') || '').toLowerCase();
          var matches = (!ownerId || String(optionOwner) === String(ownerId)) && (!query || searchable.indexOf(query) !== -1);
          if (matches) {
            $option.find('.dps-pet-checkbox').prop('checked', action === 'select');
          }
        });
        updateSummary();
        $(document).trigger('dps-pet-selection-updated');
      });

      // Impede envio do formulário sem pet selecionado
      $petWrapper.closest('form').on('submit', function(){
        if ($petCheckboxes.filter(':checked').length === 0) {
          alert(dpsBaseL10n.selectPetWarning);
          return false;
        }
        return true;
      });

      // Aplica filtros iniciais considerando pré-seleções
      applyPetFilters();
    })();

    (function(){
      var $historyTable = $('#dps-history-table');
      if (!$historyTable.length) {
        return;
      }
      var $rows      = $historyTable.find('tbody tr');
      var $search    = $('#dps-history-search');
      var $client    = $('#dps-history-client');
      var $status    = $('#dps-history-status');
      var $start     = $('#dps-history-start');
      var $end       = $('#dps-history-end');
      var $pending   = $('#dps-history-pending');
      var $summary   = $('#dps-history-summary');
      var baseText   = $summary.find('strong').text();
      var $clearBtn  = $('#dps-history-clear');
      var $exportBtn = $('#dps-history-export');

      function formatCurrencyBR(value){
        return value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      }

      function applyHistoryFilters(){
        var searchTerm  = ($search.val() || '').toLowerCase();
        var clientVal   = $client.val();
        var statusVal   = $status.val();
        var startVal    = $start.val();
        var endVal      = $end.val();
        var pendingOnly = $pending.is(':checked');
        var visibleCount = 0;
        var visibleTotal = 0;

        $rows.each(function(){
          var $row = $(this);
          var rowText = $row.text().toLowerCase();
          var matchesSearch = !searchTerm || rowText.indexOf(searchTerm) !== -1;
          var rowClient = String($row.data('client') || '');
          var matchesClient = !clientVal || rowClient === String(clientVal);
          var rowStatus = String($row.data('status') || '');
          var matchesStatus = !statusVal || rowStatus === statusVal;
          var rowDate = $row.data('date');
          var matchesDate = true;
          if (startVal && (!rowDate || rowDate < startVal)) {
            matchesDate = false;
          }
          if (endVal && (!rowDate || rowDate > endVal)) {
            matchesDate = false;
          }
          var rowPaid = String($row.data('paid') || '1');
          if (pendingOnly && rowPaid !== '0') {
            matchesDate = false;
          }
          var show = matchesSearch && matchesClient && matchesStatus && matchesDate;
          $row.toggle(show);
          if (show) {
            visibleCount++;
            var total = parseFloat($row.data('total'));
            if (!isNaN(total)) {
              visibleTotal += total;
            }
          }
        });

        if (visibleCount) {
          var summaryText = dpsBaseL10n.historySummary
            .replace('%1$s', visibleCount.toLocaleString('pt-BR'))
            .replace('%2$s', formatCurrencyBR(visibleTotal));
          $summary.find('strong').text(summaryText);
        } else {
          $summary.find('strong').text(dpsBaseL10n.historyEmpty);
        }
      }

      function exportHistory(){
        var $visibleRows = $rows.filter(':visible');
        if (!$visibleRows.length) {
          alert(dpsBaseL10n.historyExportEmpty);
          return;
        }
        var headers = [];
        $historyTable.find('thead th').each(function(){
          headers.push($(this).text().trim());
        });
        var csvLines = [];
        csvLines.push(headers.map(function(text){
          return '"' + text.replace(/"/g, '""') + '"';
        }).join(';'));
        $visibleRows.each(function(){
          var columns = [];
          $(this).find('td').each(function(){
            var value = $(this).text().replace(/\s+/g, ' ').trim();
            columns.push('"' + value.replace(/"/g, '""') + '"');
          });
          csvLines.push(columns.join(';'));
        });
        var blob = new Blob([csvLines.join('\n')], { type: 'text/csv;charset=utf-8;' });
        var url = URL.createObjectURL(blob);
        var anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = dpsBaseL10n.historyExportFileName.replace('%s', new Date().toISOString().split('T')[0]);
        document.body.appendChild(anchor);
        anchor.click();
        document.body.removeChild(anchor);
        URL.revokeObjectURL(url);
      }

      function clearHistoryFilters(){
        $search.val('');
        $client.val('');
        $status.val('');
        $start.val('');
        $end.val('');
        $pending.prop('checked', false);
        $summary.find('strong').text(baseText);
        applyHistoryFilters();
      }

      $search.on('input', applyHistoryFilters);
      $client.on('change', applyHistoryFilters);
      $status.on('change', applyHistoryFilters);
      $start.on('change', applyHistoryFilters);
      $end.on('change', applyHistoryFilters);
      $pending.on('change', applyHistoryFilters);
      $clearBtn.on('click', function(e){
        e.preventDefault();
        clearHistoryFilters();
      });
      $exportBtn.on('click', function(e){
        e.preventDefault();
        exportHistory();
      });

      applyHistoryFilters();
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