(function ($) {
  'use strict';

  var activeDialog = null;

  function escapeHtml(value) {
    return $('<div>').text(value || '').html();
  }

  function applyAccentFixes(value) {
    var text = String(value || '');
    var replacements = [
      [/\brapido\b/gi, 'r\u00E1pido'],
      [/\bespecie\b/gi, 'esp\u00E9cie'],
      [/\braca\b/gi, 'ra\u00E7a'],
      [/\bendereco\b/gi, 'endere\u00E7o'],
      [/\bfemea\b/gi, 'f\u00EAmea'],
      [/\bmedio\b/gi, 'm\u00E9dio'],
      [/\bnao\b/gi, 'n\u00E3o'],
      [/\bcao\b/gi, 'c\u00E3o'],
      [/\bcaes\b/gi, 'c\u00E3es'],
      [/\bnumero\b/gi, 'n\u00FAmero'],
      [/\bvacinacao\b/gi, 'vacina\u00E7\u00E3o'],
      [/\bobservacao\b/gi, 'observa\u00E7\u00E3o']
    ];

    replacements.forEach(function (pair) {
      text = text.replace(pair[0], pair[1]);
    });

    return text;
  }

  function normalizeValue(value) {
    var normalized = String(value || '').trim();
    return normalized.length ? applyAccentFixes(normalized) : '\u2014';
  }

  function buildProfileItem(label, value) {
    return '<div class="dps-pet-profile-item">' +
      '<dt>' + escapeHtml(label) + '</dt>' +
      '<dd>' + escapeHtml(value) + '</dd>' +
    '</div>';
  }

  function buildSection(title, sectionClass, itemsHtml) {
    return '<section class="dps-pet-profile-section ' + sectionClass + '" aria-label="' + escapeHtml(title) + '">' +
      '<h4>' + escapeHtml(title) + '</h4>' +
      '<dl class="dps-pet-profile-list">' + itemsHtml + '</dl>' +
    '</section>';
  }

  function buildDialogBody(data) {
    var petItems = '';
    petItems += buildProfileItem('Nome', data.petName);
    petItems += buildProfileItem('Esp\u00E9cie', data.petSpecies);
    petItems += buildProfileItem('Ra\u00E7a', data.petBreed);
    petItems += buildProfileItem('Porte', data.petSize);
    petItems += buildProfileItem('Peso', data.petWeight);
    petItems += buildProfileItem('Sexo', data.petSex);

    var tutorItems = '';
    tutorItems += buildProfileItem('Nome', data.clientName);
    tutorItems += buildProfileItem('Telefone', data.clientPhone);
    tutorItems += buildProfileItem('E-mail', data.clientEmail);
    tutorItems += buildProfileItem('Endere\u00E7o', data.clientAddress);

    return '<div class="dps-pet-profile-grid">' +
      buildSection('Pet', 'dps-pet-profile-section--pet', petItems) +
      buildSection('Tutor', 'dps-pet-profile-section--tutor', tutorItems) +
    '</div>';
  }

  function collectTriggerData(trigger) {
    return {
      petName: normalizeValue(trigger.data('pet-name')),
      petSpecies: normalizeValue(trigger.data('pet-species')),
      petBreed: normalizeValue(trigger.data('pet-breed')),
      petSize: normalizeValue(trigger.data('pet-size')),
      petWeight: normalizeValue(trigger.data('pet-weight') ? String(trigger.data('pet-weight')) + ' kg' : ''),
      petSex: normalizeValue(trigger.data('pet-sex')),
      clientName: normalizeValue(trigger.data('client-name')),
      clientPhone: normalizeValue(trigger.data('client-phone')),
      clientEmail: normalizeValue(trigger.data('client-email')),
      clientAddress: normalizeValue(trigger.data('client-address'))
    };
  }

  function closeActivePetDialog(reason) {
    if (activeDialog && activeDialog.length && window.DPSAgendaDialog && typeof window.DPSAgendaDialog.close === 'function') {
      window.DPSAgendaDialog.close(activeDialog, reason || 'dismiss');
    }
  }

  function openPetProfileModal(trigger) {
    if (!window.DPSAgendaDialog || typeof window.DPSAgendaDialog.content !== 'function') {
      return;
    }

    closeActivePetDialog('redirect');
    $('.dps-pet-profile-trigger[aria-expanded="true"]').attr('aria-expanded', 'false');

    var data = collectTriggerData(trigger);
    trigger.attr('aria-expanded', 'true');

    activeDialog = window.DPSAgendaDialog.content({
      eyebrow: 'Agenda',
      title: 'Perfil r\u00E1pido do pet',
      subtitle: data.petName + ' | ' + data.clientName,
      size: 'medium',
      trigger: trigger,
      dialogClass: 'dps-agenda-dialog--pet-profile',
      bodyHtml: buildDialogBody(data),
      onClose: function () {
        trigger.attr('aria-expanded', 'false');
        activeDialog = null;
      }
    });
  }

  $(document).on('click', '.dps-pet-profile-trigger', function (event) {
    event.preventDefault();
    openPetProfileModal($(this));
  });
})(jQuery);
