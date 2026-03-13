(function ($) {
  'use strict';

  var MODAL_SELECTOR = '.dps-pet-profile-modal';
  var MODAL_CONTENT_SELECTOR = '.dps-pet-profile-modal-content';
  var MODAL_CLOSE_SELECTOR = '.dps-pet-profile-modal-close, .dps-pet-profile-modal-dismiss';
  var FOCUSABLE_SELECTOR = 'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"]), input:not([disabled]), select:not([disabled]), textarea:not([disabled])';

  var modalCounter = 0;
  var lastTrigger = null;

  function escapeHtml(value) {
    return $('<div>').text(value || '').html();
  }

  function normalizeValue(value) {
    var normalized = $.trim(String(value || ''));
    return normalized.length ? normalized : '—';
  }

  function closePetProfileModal() {
    var modal = $(MODAL_SELECTOR);

    if (!modal.length) {
      return;
    }

    modal.remove();
    $('body').removeClass('dps-pet-profile-modal-open');
    $(document).off('keydown.dpsPetProfileModal');
    $('.dps-pet-profile-trigger[aria-expanded="true"]').attr('aria-expanded', 'false');

    if (lastTrigger && lastTrigger.length) {
      lastTrigger.trigger('focus');
    }

    lastTrigger = null;
  }

  function getFocusables(modal) {
    return modal.find(FOCUSABLE_SELECTOR).filter(':visible');
  }

  function trapFocus(event) {
    if (event.key !== 'Tab') {
      return;
    }

    var modal = $(MODAL_SELECTOR);
    if (!modal.length) {
      return;
    }

    var focusables = getFocusables(modal);

    if (!focusables.length) {
      event.preventDefault();
      modal.find(MODAL_CONTENT_SELECTOR).trigger('focus');
      return;
    }

    var first = focusables.first()[0];
    var last = focusables.last()[0];

    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
      return;
    }

    if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
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

  function buildModalHtml(data) {
    modalCounter += 1;

    var titleId = 'dps-pet-profile-modal-title-' + modalCounter;
    var descId = 'dps-pet-profile-modal-desc-' + modalCounter;

    var petItems = '';
    petItems += buildProfileItem('Nome', data.petName);
    petItems += buildProfileItem('Especie', data.petSpecies);
    petItems += buildProfileItem('Raca', data.petBreed);
    petItems += buildProfileItem('Porte', data.petSize);
    petItems += buildProfileItem('Peso', data.petWeight);
    petItems += buildProfileItem('Sexo', data.petSex);

    var tutorItems = '';
    tutorItems += buildProfileItem('Nome', data.clientName);
    tutorItems += buildProfileItem('Telefone', data.clientPhone);
    tutorItems += buildProfileItem('E-mail', data.clientEmail);
    tutorItems += buildProfileItem('Endereco', data.clientAddress);

    return '<div class="dps-pet-profile-modal" role="dialog" aria-modal="true" aria-labelledby="' + titleId + '" aria-describedby="' + descId + '">' +
      '<div class="dps-pet-profile-modal-content" role="document" tabindex="-1">' +
        '<div class="dps-pet-profile-modal-header">' +
          '<div>' +
            '<p class="dps-pet-profile-modal-eyebrow">Perfil do atendimento</p>' +
            '<h3 id="' + titleId + '" class="dps-pet-profile-modal-title">Perfil rapido do pet</h3>' +
          '</div>' +
          '<button type="button" class="dps-pet-profile-modal-close" aria-label="Fechar modal">&times;</button>' +
        '</div>' +
        '<div class="dps-pet-profile-modal-body" id="' + descId + '">' +
          '<p class="dps-pet-profile-modal-subtitle">Dados essenciais do pet e do tutor para agilizar o atendimento.</p>' +
          '<div class="dps-pet-profile-grid">' +
            buildSection('Pet', 'dps-pet-profile-section--pet', petItems) +
            buildSection('Tutor', 'dps-pet-profile-section--tutor', tutorItems) +
          '</div>' +
        '</div>' +
        '<div class="dps-pet-profile-modal-footer">' +
          '<button type="button" class="dps-btn dps-btn--ghost dps-pet-profile-modal-dismiss">Fechar</button>' +
        '</div>' +
      '</div>' +
    '</div>';
  }

  function openPetProfileModal(trigger) {
    var data = {
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

    closePetProfileModal();

    lastTrigger = trigger;
    trigger.attr('aria-expanded', 'true');

    $('body').addClass('dps-pet-profile-modal-open').append(buildModalHtml(data));

    $(document).on('keydown.dpsPetProfileModal', function (event) {
      if (event.key === 'Escape') {
        event.preventDefault();
        closePetProfileModal();
        return;
      }

      trapFocus(event);
    });

    window.setTimeout(function () {
      var modal = $(MODAL_SELECTOR);
      var focusables = getFocusables(modal);

      if (focusables.length) {
        focusables.first().trigger('focus');
      } else {
        modal.find(MODAL_CONTENT_SELECTOR).trigger('focus');
      }
    }, 0);
  }

  $(document).on('click', '.dps-pet-profile-trigger', function (event) {
    event.preventDefault();
    openPetProfileModal($(this));
  });

  $(document).on('click', MODAL_CLOSE_SELECTOR, function (event) {
    event.preventDefault();
    closePetProfileModal();
  });

  $(document).on('click', MODAL_SELECTOR, function (event) {
    if ($(event.target).is(MODAL_SELECTOR)) {
      closePetProfileModal();
    }
  });
})(jQuery);
