/**
 * ⚠️ ARQUIVO LEGADO - NÃO UTILIZADO ⚠️
 * 
 * Este arquivo contém código antigo do FullCalendar que não é mais utilizado.
 * NÃO é enfileirado via wp_enqueue_script() e pode ser removido fisicamente.
 * 
 * Data de depreciação: 2025-11-23
 * Consulte DEPRECATED_FILES.md para mais informações.
 */

(function($){
  $(document).ready(function(){
    // Verifica se FullCalendar está disponível e se o contêiner existe
    if ( typeof FullCalendar !== 'undefined' && document.getElementById('dps-agenda-calendar') ) {
      var calendarEl = document.getElementById('dps-agenda-calendar');
      var calendar = new FullCalendar.Calendar(calendarEl, {
        // Mostra apenas a visualização semanal (timeGridWeek)
        initialView: 'timeGridWeek',
        locale: 'pt-br',
        headerToolbar: {
          left: 'prev,next today',
          center: 'title',
          right: ''
        },
        events: DPS_AG.events
      });
      calendar.render();
    }
  });
})(jQuery);