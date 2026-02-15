(function($){
  'use strict';

  function closePetProfileModal() {
    $('.dps-pet-profile-modal').remove();
  }

  function escapeHtml(value) {
    return $('<div>').text(value || '').html();
  }

  $(document).on('click', '.dps-pet-profile-trigger', function(e){
    e.preventDefault();

    var btn = $(this);
    var data = {
      petName: btn.data('pet-name') || '—',
      petSpecies: btn.data('pet-species') || '—',
      petBreed: btn.data('pet-breed') || '—',
      petSize: btn.data('pet-size') || '—',
      petWeight: btn.data('pet-weight') || '—',
      petSex: btn.data('pet-sex') || '—',
      clientName: btn.data('client-name') || '—',
      clientPhone: btn.data('client-phone') || '—',
      clientEmail: btn.data('client-email') || '—',
      clientAddress: btn.data('client-address') || '—'
    };

    var petWeight = data.petWeight !== '—' ? escapeHtml(data.petWeight) + ' kg' : '—';

    var modalHtml = '<div class="dps-pet-profile-modal" role="dialog" aria-modal="true" aria-labelledby="dps-pet-profile-modal-title">' +
      '<div class="dps-pet-profile-modal-content">' +
        '<div class="dps-pet-profile-modal-header">' +
          '<h3 id="dps-pet-profile-modal-title" class="dps-pet-profile-modal-title">🐾 Perfil rápido do pet</h3>' +
          '<button type="button" class="dps-pet-profile-modal-close" aria-label="Fechar">&times;</button>' +
        '</div>' +
        '<div class="dps-pet-profile-modal-body">' +
          '<div class="dps-pet-profile-grid">' +
            '<div class="dps-pet-profile-section">' +
              '<h4>Pet</h4>' +
              '<p><strong>Nome:</strong> ' + escapeHtml(data.petName) + '</p>' +
              '<p><strong>Espécie:</strong> ' + escapeHtml(data.petSpecies) + '</p>' +
              '<p><strong>Raça:</strong> ' + escapeHtml(data.petBreed) + '</p>' +
              '<p><strong>Porte:</strong> ' + escapeHtml(data.petSize) + '</p>' +
              '<p><strong>Peso:</strong> ' + petWeight + '</p>' +
              '<p><strong>Sexo:</strong> ' + escapeHtml(data.petSex) + '</p>' +
            '</div>' +
            '<div class="dps-pet-profile-section">' +
              '<h4>Tutor</h4>' +
              '<p><strong>Nome:</strong> ' + escapeHtml(data.clientName) + '</p>' +
              '<p><strong>Telefone:</strong> ' + escapeHtml(data.clientPhone) + '</p>' +
              '<p><strong>E-mail:</strong> ' + escapeHtml(data.clientEmail) + '</p>' +
              '<p><strong>Endereço:</strong> ' + escapeHtml(data.clientAddress) + '</p>' +
            '</div>' +
          '</div>' +
        '</div>' +
      '</div>' +
    '</div>';

    closePetProfileModal();
    $('body').append(modalHtml);
  });

  $(document).on('click', '.dps-pet-profile-modal-close', closePetProfileModal);

  $(document).on('click', '.dps-pet-profile-modal', function(e){
    if ($(e.target).hasClass('dps-pet-profile-modal')) {
      closePetProfileModal();
    }
  });

  $(document).on('keydown', function(e){
    if (e.key === 'Escape' && $('.dps-pet-profile-modal').length) {
      closePetProfileModal();
    }
  });

})(jQuery);
