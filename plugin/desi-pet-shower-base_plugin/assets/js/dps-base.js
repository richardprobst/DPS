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
      var $petList       = $('#dps-appointment-pet-list');
      var $petOptions    = $petWrapper.find('.dps-pet-option');
      var $petCheckboxes = $petWrapper.find('.dps-pet-checkbox');
      var $noPetsMsg     = $('#dps-no-pets-message');
      var $summary       = $('#dps-pet-summary');
      var $selectHint    = $('#dps-pet-select-client');
      var $pendingAlert  = $('#dps-client-pending-alert');
      var $searchInput   = $('#dps-pet-search');
      var $loadMore      = $petWrapper.find('.dps-pet-load-more');
      var restConfig     = window.dpsBaseData || {};
      var petState = {
        page: parseInt($petWrapper.data('currentPage'), 10) || 1,
        totalPages: parseInt($petWrapper.data('totalPages'), 10) || 1,
        search: '',
        loading: false
      };
      var searchTimer;

      function updateSummary(){
        var selected = $petCheckboxes.filter(':checked');
        var $counter = $('#dps-pet-counter');
        
        if (selected.length) {
          var names = selected.map(function(){
            return $(this).closest('.dps-pet-option').find('.dps-pet-name').text();
          }).get();
          $summary.text(selected.length === 1 ?
            dpsBaseL10n.summarySingle.replace('%s', names[0]) :
            dpsBaseL10n.summaryMultiple.replace('%d', selected.length).replace('%s', names.join(', '))
          );
          $summary.show();
          
          // Update counter
          $counter.text(selected.length + ' ' + (selected.length === 1 ? dpsBaseL10n.selectedSingle : dpsBaseL10n.selectedMultiple)).show();
        } else {
          $summary.hide();
          $counter.hide();
        }
      }

      function updatePendingAlert(){
        if (!$pendingAlert.length) {
          return;
        }
        var $selected = $clientSelect.find('option:selected');
        var hasPending = $selected.data('hasPending');
        if (hasPending === undefined) {
          hasPending = $selected.attr('data-has-pending');
        }
        if (!hasPending || String(hasPending) === '0') {
          $pendingAlert.attr('aria-hidden', 'true').hide().empty();
          $petWrapper.removeClass('dps-pet-picker--warning');
          $clientSelect.removeClass('dps-client-select--warning');
          return;
        }
        var pendingInfo = $selected.data('pendingInfo');
        if (typeof pendingInfo === 'string') {
          try {
            pendingInfo = JSON.parse(pendingInfo);
          } catch (err) {
            pendingInfo = [];
          }
        }
        if (!Array.isArray(pendingInfo) || !pendingInfo.length) {
          $pendingAlert.attr('aria-hidden', 'true').hide().empty();
          $petWrapper.removeClass('dps-pet-picker--warning');
          $clientSelect.removeClass('dps-client-select--warning');
          return;
        }
        var clientName = $.trim($selected.text());
        var titleText = clientName ?
          dpsBaseL10n.pendingTitle.replace('%s', clientName) :
          dpsBaseL10n.pendingGenericTitle;
        $pendingAlert.empty();
        $('<strong/>').text(titleText).appendTo($pendingAlert);
        var $list = $('<ul/>');
        pendingInfo.forEach(function(row){
          var date = row.date || '';
          var value = row.value || '0,00';
          var desc = row.description || dpsBaseL10n.pendingItemNoDate;
          var label;
          if (date) {
            label = dpsBaseL10n.pendingItem
              .replace('%1$s', date)
              .replace('%2$s', value)
              .replace('%3$s', desc);
          } else {
            label = dpsBaseL10n.pendingItemNoDate
              .replace('%1$s', value)
              .replace('%2$s', desc);
          }
          $('<li/>').text(label).appendTo($list);
        });
        $pendingAlert.append($list);
        $pendingAlert.attr('aria-hidden', 'false').show();
        $petWrapper.addClass('dps-pet-picker--warning');
        $clientSelect.addClass('dps-client-select--warning');
      }

      function updateLoadMore(page, total){
        if (!$loadMore.length) {
          return;
        }
        if (!restConfig.restUrl || page >= total) {
          $loadMore.hide();
          return;
        }
        $loadMore.show()
          .attr('data-next-page', page + 1)
          .attr('aria-busy', 'false')
          .text(dpsBaseL10n.petLoadMore);
      }

      function buildPetOption(pet, selectedIds){
        var $label = $('<label/>', {
          'class': 'dps-pet-option'
        });
        // Set data attributes using .attr() to ensure they are DOM attributes
        // (not jQuery internal data). This is required for .attr() reads in applyPetFilters.
        $label.attr('data-owner', pet.owner_id || '');
        $label.attr('data-search', (pet.name + ' ' + (pet.breed || '') + ' ' + (pet.owner_name || '')).toLowerCase());

        if (pet.size) {
          $label.attr('data-size', String(pet.size).toLowerCase());
        }

        var $checkbox = $('<input/>', {
          type: 'checkbox',
          'class': 'dps-pet-checkbox',
          name: 'appointment_pet_ids[]',
          value: pet.id
        });

        if (selectedIds.indexOf(String(pet.id)) !== -1) {
          $checkbox.prop('checked', true);
        }

        $label.append($checkbox);
        $('<span/>', { 'class': 'dps-pet-name', text: pet.name }).appendTo($label);

        if (pet.breed) {
          $('<span/>', { 'class': 'dps-pet-breed', text: ' – ' + pet.breed }).appendTo($label);
        }

        if (pet.owner_name) {
          $('<span/>', { 'class': 'dps-pet-owner', text: ' (' + pet.owner_name + ')' }).appendTo($label);
        }

        if (pet.size) {
          var label = String(pet.size).charAt(0).toUpperCase() + String(pet.size).slice(1);
          $('<span/>', { 'class': 'dps-pet-size', text: ' · ' + label }).appendTo($label);
        }

        return $label;
      }

      function renderPets(items, append){
        var previousSelection = $petWrapper.find('.dps-pet-checkbox:checked').map(function(){
          return String($(this).val());
        }).get();

        if (!append) {
          $petList.empty();
        }

        if (!items.length && !append) {
          $petList.hide();
          $noPetsMsg.text(dpsBaseL10n.petNoResults).show();
          $petOptions = $();
          $petCheckboxes = $();
          updateSummary();
          return;
        }

        $noPetsMsg.hide();
        $petList.show();

        items.forEach(function(pet){
          var $option = buildPetOption(pet, previousSelection);
          $petList.append($option);
        });

        $petOptions = $petWrapper.find('.dps-pet-option');
        $petCheckboxes = $petWrapper.find('.dps-pet-checkbox');
        applyPetFilters(true);
      }

      function applyPetFilters(skipReset){
        var ownerId = $clientSelect.val();
        var visible = 0;

        $petOptions.each(function(){
          var $option = $(this);
          var optionOwner = $option.attr('data-owner');
          var matchesOwner = ownerId ? String(optionOwner) === String(ownerId) : false;

          if (!matchesOwner && !skipReset) {
            $option.find('.dps-pet-checkbox').prop('checked', false);
          }

          $option.toggle(matchesOwner);

          if (matchesOwner) {
            visible++;
          }
        });

        $petList.toggle(visible > 0);
        $noPetsMsg.toggle(visible === 0 && !!ownerId);
        $selectHint.toggle(!ownerId);
        $petWrapper.toggleClass('dps-pet-picker--disabled', !ownerId);
        $petWrapper.find('.dps-pet-toggle').prop('disabled', !ownerId || visible === 0);
        if (!ownerId && $loadMore.length) {
          $loadMore.hide();
        }
        updateSummary();
        updatePendingAlert();
        $(document).trigger('dps-pet-selection-updated');
      }

      function fetchPets(targetPage, append){
        if (petState.loading) {
          return;
        }

        var ownerId = $clientSelect.val();
        if (!ownerId) {
          $petList.empty();
          $petOptions = $();
          $petCheckboxes = $();
          applyPetFilters();
          return;
        }

        if (!restConfig.restUrl) {
          applyPetFilters();
          return;
        }

        petState.loading = true;

        if ($loadMore.length) {
          $loadMore.attr('aria-busy', 'true').text(dpsBaseL10n.petLoading);
        }

        $.ajax({
          url: restConfig.restUrl,
          method: 'GET',
          data: {
            page: targetPage,
            owner: ownerId,
            search: petState.search
          },
          beforeSend: function(xhr){
            if (restConfig.restNonce) {
              xhr.setRequestHeader('X-WP-Nonce', restConfig.restNonce);
            }
          }
        }).done(function(resp){
          petState.page = resp.page || targetPage;
          petState.totalPages = resp.total_pages || 1;
          renderPets(resp.items || [], append);
          updateLoadMore(petState.page, petState.totalPages);
        }).fail(function(){
          applyPetFilters();
          updateLoadMore(petState.page, petState.totalPages);
        }).always(function(){
          petState.loading = false;
          if ($loadMore.length) {
            $loadMore.attr('aria-busy', 'false').text(dpsBaseL10n.petLoadMore);
          }
        });
      }

      $clientSelect.on('change', function(){
        petState.page = 1;
        petState.search = '';
        $searchInput.val('');
        fetchPets(1, false);
      });

      $petWrapper.on('change', '.dps-pet-checkbox', function(){
        updateSummary();
        $(document).trigger('dps-pet-selection-updated');
      });

      $petWrapper.on('click', '.dps-pet-toggle', function(e){
        e.preventDefault();
        var action = $(this).data('action');
        var ownerId = $clientSelect.val();
        $petOptions.each(function(){
          var $option = $(this);
          // Use .attr() instead of .data() to read DOM attributes set by buildPetOption
          var optionOwner = $option.attr('data-owner');
          var matches = ownerId && String(optionOwner) === String(ownerId);
          if (matches) {
            $option.find('.dps-pet-checkbox').prop('checked', action === 'select');
          }
        });
        updateSummary();
        $(document).trigger('dps-pet-selection-updated');
      });

      $searchInput.on('input', function(){
        petState.search = $(this).val();
        petState.page = 1;
        if (searchTimer) {
          clearTimeout(searchTimer);
        }
        searchTimer = setTimeout(function(){
          fetchPets(1, false);
        }, 300);
      });

      $petWrapper.on('click', '.dps-pet-load-more', function(e){
        e.preventDefault();
        var nextPage = parseInt($(this).attr('data-next-page'), 10) || (petState.page + 1);
        if (petState.page < petState.totalPages) {
          fetchPets(nextPage, true);
        }
      });

      // Impede envio do formulário sem pet selecionado
      $petWrapper.closest('form').on('submit', function(){
        if ($petCheckboxes.filter(':checked').length === 0) {
          alert(dpsBaseL10n.selectPetWarning);
          return false;
        }
        return true;
      });

      applyPetFilters(true);
      updateLoadMore(petState.page, petState.totalPages);
    })();

    $(document).on('change', '.dps-inline-status-form select[name="appointment_status"]', function(){
      var $select = $(this);
      var $form = $select.closest('form');
      if ($form.data('submitting')) {
        return;
      }
      $form.data('submitting', true);
      $form.addClass('is-updating');
      $select.prop('disabled', true);
      $form.trigger('submit');
    });

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

    /*
     * Melhorias de UX para formulários de cadastro
     * - Desabilita botão de submit durante envio
     * - Preview de upload de foto
     */
    (function(){
      // Desabilita botão durante submit
      $('.dps-form').on('submit', function(){
        var $form = $(this);
        var $btn  = $form.find('.dps-submit-btn, button[type="submit"]');
        
        if ($btn.data('submitting')) {
          return false;
        }
        
        $btn.data('submitting', true);
        $btn.prop('disabled', true);
        
        var originalText = $btn.text();
        $btn.text(dpsBaseL10n.savingText || 'Salvando...');
        
        // Restaura após 5 segundos caso falhe
        setTimeout(function(){
          $btn.prop('disabled', false);
          $btn.text(originalText);
          $btn.removeData('submitting');
        }, 5000);
      });
      
      // Preview de upload de foto
      $('.dps-file-upload__input').on('change', function(){
        var $input   = $(this);
        var $preview = $input.closest('.dps-file-upload').find('.dps-file-upload__preview');
        var file     = this.files[0];
        
        if (!file || !file.type.match('image.*')) {
          $preview.empty();
          return;
        }
        
        var reader = new FileReader();
        reader.onload = function(e){
          $preview.html('<img src="' + e.target.result + '" alt="Preview">');
        };
        reader.readAsDataURL(file);
      });
    })();
  });
})(jQuery);
