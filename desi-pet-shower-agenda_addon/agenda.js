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